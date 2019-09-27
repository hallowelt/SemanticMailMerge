<?php

namespace MediaWiki\Extension\SemanticMailMerge;

class Extension {
	public static function registration() {
		$GLOBALS['srfgFormats'][] = 'mailmerge';
		$GLOBALS['smwgResultFormats']['mailmerge'] =  ResultFormat::class;
	}
}