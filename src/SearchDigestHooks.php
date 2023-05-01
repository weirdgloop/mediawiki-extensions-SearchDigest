<?php

/**
 * Hooks for the SearchDigest extension
 *
 * @file
 * @ingroup Extensions
 */
class SearchDigestHooks {
	/**
	 * Called when MediaWiki's update script is ran
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/LoadExtensionSchemaUpdates
	 */
	public static function onLoadExtensionSchemaUpdates ( DatabaseUpdater $updater ) {
		$updater->addExtensionTable( 'searchdigest', __DIR__ . '/../sql/searchdigest.sql' );
	}

	/**
	 * Called when the Special:Search 'go' feature is triggered and the target page doesn't exist
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/SpecialSearchNogomatch
	 */
	public static function onSpecialSearchNogomatch ( &$title ) {
		DeferredUpdates::addCallableUpdate( static function () use ( $title ) {
			try {
				if ( !is_object( $title ) ) {
					return;
				}

				$query = $title->getFullText();
				$query = trim(mb_convert_encoding($query, 'UTF-8'));
				wfDebugLog( 'searchdigest', "Preparing to record missed query: {$query}" );

				$record = SearchDigestRecord::getFromQuery( $query );
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
		} );
	}
}
