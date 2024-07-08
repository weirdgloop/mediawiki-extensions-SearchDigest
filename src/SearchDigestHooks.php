<?php

namespace MediaWiki\Extension\SearchDigest;

use DatabaseUpdater;
use MediaWiki\Hook\SpecialSearchNogomatchHook;
use MediaWiki\MediaWikiServices;
use MediaWiki\ResourceLoader\Context;

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
		$updater->addExtensionTable( 'searchdigest', dirname( __DIR__ ) . '/sql/searchdigest.sql' );
		$updater->addExtensionIndex( 'searchdigest', 'sd_misses_touched',
			dirname( __DIR__ ) . '/sql/patch_searchdigest_sd_misses_touched.sql'
		);
		$updater->addExtensionTable( 'searchdigest_blocks', dirname( __DIR__ ) . '/sql/searchdigest_blocks.sql' );
	}

	/**
	 * Called when the Special:Search 'go' feature is triggered and the target page doesn't exist
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/SpecialSearchNogomatch
	 */
	public function onSpecialSearchNogomatch ( &$title ) {
		// Schedule a job to update the count for this page, keeping search requests idempotent.
		MediaWikiServices::getInstance()->getJobQueueGroup()->lazyPush(
			new SearchDigestJob( [
				'query' => $title->getFullText()
			] )
		);
	}

	/**
	 * Expose redirect magic word and edit summary in content language
	 * @return array
	 */
	public static function getModuleData( Context $context ) {
		$factory = MediaWikiServices::getInstance()->getMagicWordFactory();
		return [
			'redirect' => $factory->get( 'redirect' )->getSynonym( 0 ),
			'editsummary' => $context->msg( 'searchdigest-redirect-editsummary' )->inContentLanguage()->plain(),
		];
	}
}
