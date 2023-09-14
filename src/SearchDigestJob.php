<?php

class SearchDigestJob extends Job implements GenericParameterJob {
	/** @var Title */
	protected $title;

	public function __construct( $params = null ) {
		parent::__construct( 'SearchDigest', $params );

		$this->title = $params['title'];
	}

	public function run() {
		$title = $this->title;

		try {
			if ( !is_object( $title ) ) {
				return;
			}

			$query = $title->getFullText();
			$query = trim(mb_convert_encoding($query, 'UTF-8'));
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
		} catch (Exception $e) {
			wfDebugLog( 'searchdigest', "Problem with logging failed go search for {$title->getFullText()}. Exception: {$e->getMessage()}" );
		}
	}
}
