<?php

namespace MediaWiki\Extension\SearchDigest;

use HTMLForm;
use MediaWiki\MediaWikiServices;
use QueryPage;
use Title;

class SpecialSearchDigest extends QueryPage {
	function __construct() {
		parent::__construct( 'SearchDigest', 'searchdigest-reader' );
		$this->linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
	}

	function execute( $par ) {
		global $wgSearchDigestCreateRedirect;

		// Add intro text before we execute parent function so that it renders before
		$out = $this->getOutput();
		$out->addModuleStyles( [ 'ext.searchdigest.styles' ] );
		$out->addWikiTextAsInterface( wfMessage( 'searchdigest-help' )->text() );

		parent::execute( $par );
		$this->setLinkBatchFactory( MediaWikiServices::getInstance()->getLinkBatchFactory() );

		$out->enableOOUI();

		// If the user has the searchdigest-admin permission, add admin tools to page
		if ( $this->getUser()->isAllowed( 'searchdigest-admin' ) ) {
			$out->addModuleStyles( [ 'oojs-ui.styles.icons-moderation' ] );

			$out->addHtml('<div class="sd-admin-tools"><p>' . wfMessage( 'searchdigest-admintools-help' )->plain() . '</p>');
			$this->createAdminForm();
			$out->addHtml('</div>');
		}

		// Additional client JS for redirect button
		if ( $wgSearchDigestCreateRedirect === true ) {
			$out->addModules( 'ext.searchdigest.redirect' );
		}
	}

	function createAdminForm () {
		$desc = [
			'select' => [
				'type' => 'select',
				'options' => [
					wfMessage( 'searchdigest-admintools-rmold' )->plain() => 'rmold',
					wfMessage( 'searchdigest-admintools-dbwipe' )->plain() => 'dbwipe'
				]
			]
		];
		$form = HTMLForm::factory( 'ooui', $desc, $this->getContext(), 'searchdigest' );
		$form
			->setSubmitText( wfMessage( 'searchdigest-admintools-submit' )->plain() )
			->setSubmitDestructive()
			->setSubmitCallback( [ $this, 'handleFormAction' ] )
			->show();
	}

	public function handleFormAction( $formData ) {
		// redundancy permission check
		if ( $this->getUser()->isAllowed( 'searchdigest-admin' ) ) {
			$maint = new SearchDigestMaintenance();
			switch ( $formData[ 'select' ] ) {
				case 'rmold':
					$maint->removeOldEntries();
					break;
				case 'dbwipe':
					$maint->doTableWipe();
					break;
			};
			return false;
		} else {
			return 'searchdigest-admintools-noperms';
		}
	}

	function isSyndicated() {
		return false;
	}

	function getQueryInfo() {
		global $wgSearchDigestMinimumMisses;

		// Get the date one week ago
		$dateLimit = date( 'Y-m-d', ( wfTimestamp( TS_UNIX ) - 604800 ) );
		return [
			'tables' => [ 'searchdigest' ],
			'fields' => [ 'sd_query', 'sd_misses' ],
			'conds' => [ 'sd_touched > ' . $this->getRecacheDB()->addQuotes( $dateLimit ) . ' AND sd_misses >= ' . $wgSearchDigestMinimumMisses ],
		];
	}

	function getOrderFields() {
		return [ 'sd_misses' ];
	}

	function formatResult( $skin, $result ) {
		global $wgSearchDigestStrikeValidPages;

		$title = Title::newFromText( $result->sd_query );

		if ( $title === null ) {
			// If the title is null or invalid, don't show this row.
			return false;
		}

		$link = $this->linkRenderer->makeLink( $title );
		$isKnown = $title->isKnown() === true;
		if ( ( $isKnown ) && ( $wgSearchDigestStrikeValidPages === true ) ) {
			$link = '<s>' . $link . '</s>';
		}

		return $link . ' (' . $result->sd_misses . ') ' . ( $isKnown ? '' : '<span class="sd-cr-btn" data-page="' . htmlspecialchars( $result->sd_query, ENT_QUOTES ) . '"></span>' );
	}

	function getGroupName() {
		return 'pages';
	}

	protected function preprocessResults( $db, $res ) {
		/**
		 * This pre-processing is similar to QueryPage::executeLBFromResultWrapper(), but slightly different as the
		 * row is called "sd_query" instead of "title". We're also assuming that the namespace is going to be NS_MAIN,
		 * but this may not always be true.
		 */
		if ( !$res->numRows() ) {
			return;
		}

		$batch = $this->getLinkBatchFactory()->newLinkBatch();
		foreach ( $res as $row ) {
			$batch->add( NS_MAIN, $row->sd_query );
		}
		$batch->execute();

		$res->seek( 0 );
	}
}
