<?php

namespace MediaWiki\Extension\SearchDigest;

use DatabaseUpdater;
use DeferredUpdates;
use MediaWiki\Hook\SpecialSearchNogomatchHook;
use MediaWiki\MediaWikiServices;

/**
 * Hooks for the SearchDigest extension
 *
 * @file
 * @ingroup Extensions
 */
class SearchDigestHooks implements SpecialSearchNogomatchHook {
	/**
	 * Called when MediaWiki's update script is ran
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/LoadExtensionSchemaUpdates
	 */
	public function onLoadExtensionSchemaUpdates ( DatabaseUpdater $updater ) {
		$updater->addExtensionTable( 'searchdigest', __DIR__ . '/../sql/searchdigest.sql' );
	}

	/**
	 * Called when the Special:Search 'go' feature is triggered and the target page doesn't exist
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/SpecialSearchNogomatch
	 */
	public function onSpecialSearchNogomatch ( &$title ) {
		// Schedule a job to update the count for this page, keeping search requests idempotent.
		MediaWikiServices::getInstance()->getJobQueueGroup()->push(
			new SearchDigestJob( [
				'query' => $title->getFullText()
			] )
		);
	}

	/**
	 * Expose redirect magic word
	 * @return array
	 */
	public static function getModuleData() {
		$factory = MediaWikiServices::getInstance()->getMagicWordFactory();
		return [
			'redirect' => $factory->get( 'redirect' )->getSynonym( 0 ),
		];
	}
}
