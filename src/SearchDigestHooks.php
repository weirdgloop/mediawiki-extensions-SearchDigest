<?php

namespace MediaWiki\Extension\SearchDigest;

use MediaWiki\MediaWikiServices;

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
	public static function onSpecialSearchNogomatch ( $title ) {
		DeferredUpdates::addCallableUpdate( static function () use ( $title ) {
			// Schedule a job to update the count for this page, keeping search requests idempotent.
			MediaWikiServices::getInstance()->getJobQueueGroup()->push(
				new SearchDigestJob( [
					'title' => $title
				] )
			);
		} );
	}
}
