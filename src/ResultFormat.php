<?php

namespace MediaWiki\Extension\SemanticMailMerge;

use SMW\TableResultPrinter;
use SMWQueryResult;
use SMWResultArray;
use Sanitizer;
use FormatJson;

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
/**
 * Result Format printer. Output is exactly the same as its parent.
 */
class ResultFormat extends TableResultPrinter {

	/** @var string */
	protected $pageTitle;

	/**
	 * @see SMWResultPrinter::getParamDefinitions
	 *
	 * @since 1.8
	 *
	 * @param $definitions array of IParamDefinition
	 *
	 * @return array of IParamDefinition|array
	 */
	public function getParamDefinitions(array $definitions) {
		$params = parent::getParamDefinitions($definitions);
		$params['template'] = array(
			'name' => 'template',
			'message' => 'smw-paramdesc-template',
			'default' => '',
		);
		return $params;
	}

	/**
	 * Get HTML output (exactly the same as for the 'table' result format) and
	 * prepare mail merge data. Perhaps storing the latter in the DB should be
	 * done elsewhere.
	 *
	 * @uses SMWTableResultPrinter::getResultText()
	 * @return string
	 */
	protected function getResultText( SMWQueryResult $queryResult, $outputmode ) {
		$this->pageTitle = $this->getTitle();

		$table = parent::getResultText( $queryResult, $outputmode );
		$div = \Html::openElement( 'div', [
			'id' => 'mailmerge-wrapper',
			'data-mailmerge-template' => $this->params['template'],
			'data-mailmerge-data' => $this->getMailMergeData( $queryResult )
		] );
		$div .= $table;;
		$div .= \Html::closeElement( 'div' );

		return $div;
	}

	/**
	 * @param SMWQueryResult $queryResult
	 * @return false|string
	 */
	protected function getMailMergeData( SMWQueryResult $queryResult ) {
		$queryResult->reset();
		$data = [];
		while ( $row = $queryResult->getNext() ) {
			$data[] = $this->handleRow( $row );
		}

		return FormatJson::encode( $data );
	}

	/**
	 * @param SMWResultArray[] $row
	 * @return array
	 */
	protected function handleRow( $row ) {
		$templateParams = array();
		$field_num = 0;
		/** @var SMWResultArray $field */
		foreach ( $row as $field ) {
			$field_num++;
			$key = $field->getPrintRequest()->getLabel();
			if (empty($key)) {
				$key = $field_num;
			}
			$value = array();
			while ( ( $object = $field->getNextDataValue() ) !== false ) {
				$value[] = Sanitizer::decodeCharReferences( $object->getWikiValue() );
			}
			$templateParams[$key] = $value;
		}

		return $templateParams;

	}

}
