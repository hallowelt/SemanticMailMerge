<?php

/**
 * This file is part of the MediaWiki extension 'SemanticMailMerge'.
 *
 * SemanticMailMerge is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * SemanticMailMerge is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with SemanticMailMerge.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @file
 */

use BlueSpice\Services;

require_once __DIR__ . '/../../../maintenance/Maintenance.php';

/**
 * Mail sending class.
 * Called from /extensions/SemanticMailMerge/maintenance/send.php
 */
class Sender extends Maintenance {

	/** @var Title */
	private $title;

	/**
	 * @var WikiPage
	 */
	private $wikipage;

	public function __construct()
	{
		parent::__construct();
		$this->addOption( 'title',
			'Title of a page containing a mailmerge query.',
			true, true, 't'
		);
	}

	/**
	 * Send all emails for the specified page title.
	 *
	 * @return boolean true
	 */
	public function execute()
	{
		$title = str_replace( "\\", "/", $this->getOption( 'title' ) );
		$this->title = Title::newFromText( $title );
		if ( !( $this->title instanceof \Title ) || !$this->title->exists() ) {
			$this->output( "Error: $title is not a valid title!" . PHP_EOL );
		}
		try {
			$this->setAgentUser();
			$this->wikipage = WikiPage::factory( $this->title );

			$data = $this->getEmails();
			$this->output( count( $data['data'] ) . ' mails to be sent' . PHP_EOL );
			foreach ( $data['data'] as $emailData ) {
				$email = $this->prepareTemplate( $data['template'], $emailData );
				$this->sendMail( $email['recipients'], $email['message'] );
			}
		} catch ( MWException $ex ) {
			$this->output( 'Error: ' . $ex->getMessage() . PHP_EOL );
			return true;
		}

		$this->output( 'Done!' . PHP_EOL );
		return true;
	}

	/**
	 * Parse target page and get the mail data
	 *
	 * @return array
	 */
	protected function getEmails() {
		$result = [
			'template' => '',
			'data' => []
		];
		$parser = new Parser();
		$text = $this->wikipage->getContent()->getNativeData();
		if ( empty( $text ) ) {
			return $result;
		}
		$po = $parser->parse( $text, $this->title, new ParserOptions() );
		$html = $po->getText();

		$dom = new DOMDocument();
		$dom->loadHTML( $html );
		$parsedData = [];
		$wrapper = $dom->getElementById( 'mailmerge-wrapper' );
		foreach( $wrapper->attributes as $name => $node ) {
			if ( $name === 'data-mailmerge-template' ) {
				$result['template'] = $wrapper->getAttribute( $name );
			}
			if ( $name === 'data-mailmerge-data' ) {
				$result['data'] = FormatJson::decode( $wrapper->getAttribute( $name ), true );
			}
		}

		return $result;
	}

	/**
	 * Get HTML email message and list of email recipients from given email
	 * info.
	 *
	 * @param string $template
	 * @param array $data
	 * @return array With 'message' and 'recipients' items.
	 */
	protected function prepareTemplate( $template, $data  ) {
		$text = $this->getTemplate( $template, $data );
		$parser = new Parser();
		$message = $parser->parse(
			$text,
			$this->title,
			new ParserOptions()
		)->getText();

		$recipients = [];
		foreach ( $data[ 'To' ] as $to ) {
			$recipients[] = new MailAddress( $to );
		}

		return [ 'message' => $message, 'recipients' => $recipients ];
	}

	/**
	 * Get the template wikitext, populated with parameters. Parameter with
	 * multiple values will have their values joined with commas (for separation
	 * with e.g. the {{#arraymap:}} parser function from SemanticForms).
	 *
	 * @param string $name
	 * @param array $params
	 * @return string
	 */
	protected function getTemplate( $name, $params ) {
		$template_params = '';
		foreach ( $params as $key => $val ) {
			if ( is_array( $val ) ) {
				$val = join( ',', $val );
			}
			$template_params .= "|?$key=$val";
		}
		return '{{' . $name . $template_params . '}}';
	}

	/**
	 * Send an email to one or more recipients.
	 * Outputs an error if email does not send.
	 *
	 * @param MailAddress[] $recipients
	 * @uses UserMailer::send() To actually send the mail.
	 * @global string $wgPasswordSender
	 * @throws MWException
	 */
	protected function sendMail( $recipients, $message ) {
		global $wgPasswordSender;
		$from = new MailAddress( $wgPasswordSender );
		$subject = "$this->title";
		$status = UserMailer::send(
			$recipients, $from, $subject, $message, [ 'contentType' => 'text/html; charset=UTF-8' ]
		);
		if ( !$status->isGood() ) {
			$this->error( $status->getWikiText() );
		}
	}

	/**
	 * Create system user that will be used
	 * to get mail information
	 *
	 * @global $wgUser
	 * @throws MWException
	 */
	private function setAgentUser() {
		global $wgUser;

		$user = User::newSystemUser(
			'SemanticMailMergeSender',
			[
				'validate' => 'valid',
				'steal' => true
			]
		);

		if ( !$user instanceof User ) {
			throw new MWException( 'Agent user cannot be created!' );
		}

		$user->addGroup( 'sysop' );
		$user->addGroup( 'bot' );

		RequestContext::getMain()->setUser( $user );
		$wgUser = $user;
	}

}

$maintClass = Sender::class;
require_once( RUN_MAINTENANCE_IF_MAIN );