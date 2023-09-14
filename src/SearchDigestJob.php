<?php

namespace MediaWiki\Extension\SearchDigest;

use Exception;
use Job;
use GenericParameterJob;

class SearchDigestJob extends Job implements GenericParameterJob {
	/** @var string */
	protected $query;

	public function __construct( $params ) {
		parent::__construct( 'SearchDigestJob', $params );

		$this->query = $params['query'];
	}

	public function run() {
		$query = $this->query;

		try {
			$query = trim( mb_convert_encoding( $query, 'UTF-8' ) );
			wfDebugLog( 'searchdigest', "Preparing to record missed query: $query" );

			$record = SearchDigestRecord::newFromQuery( $query );
			if ( $record === null ) {
				$record = new SearchDigestRecord();
				$record->setQuery( $query );
				$record->setMisses( 1 );
			} else {
				$misses = $record->getMisses();
				$misses++;
				$record->setMisses( $misses );
			}

			$record->setTouched( date("Y-m-d H:i:s") );
			$record->save();
		} catch ( Exception $e ) {
			wfDebugLog( 'searchdigest', "Problem with logging failed go search for \"$query\". Exception: {$e->getMessage()}" );
		}
	}
}
