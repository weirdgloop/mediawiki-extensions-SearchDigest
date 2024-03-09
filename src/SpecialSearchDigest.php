<?php

namespace MediaWiki\Extension\SearchDigest;

use HTMLForm;
use MediaWiki\MediaWikiServices;
use QueryPage;
use Title;

class SpecialSearchDigest extends QueryPage {
	protected string $prefix = '';

	protected bool $sortAlpha = false;

	function __construct() {
		parent::__construct( 'SearchDigest', 'searchdigest-reader' );
		$this->linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
	}

	function execute( $par ) {
		global $wgSearchDigestCreateRedirect;

		// If a character prefix was provided, we will only return results that start with that character.
		$prefix = strtolower( $this->getRequest()->getRawVal( 'prefix' ) ?? '' );
		if ( ctype_alpha( $prefix ) ) {
			$this->prefix = $prefix;
		}

		$this->sortAlpha = $this->getRequest()->getBool( 'sortalpha' );

		// Add intro text before we execute parent function so that it renders before.
		$out = $this->getOutput();
		$out->addModuleStyles( [ 'ext.searchdigest.styles' ] );
		$this->displayViewForm();

		parent::execute( $par );
		$this->setLinkBatchFactory( MediaWikiServices::getInstance()->getLinkBatchFactory() );

		$out->enableOOUI();

		// If the user has the searchdigest-admin permission, add admin tools to page
		if ( $this->getUser()->isAllowed( 'searchdigest-admin' ) ) {
			$out->addModuleStyles( [ 'oojs-ui.styles.icons-moderation' ] );

			$out->addHtml('<div class="sd-admin-tools"><p>' . wfMessage( 'searchdigest-admintools-help' )->plain() . '</p>');
			$this->displayAdminForm();
			$out->addHtml('</div>');
		}

		// Additional client JS for redirect button
		if ( $wgSearchDigestCreateRedirect === true ) {
			$out->addModules( 'ext.searchdigest.redirect' );
		}
	}

	protected function displayViewForm() {
		$fields = [
			'prefix' => [
				'type' => 'text',
				'name' => 'prefix',
				'label-message' => 'searchdigest-form-prefix',
				'required' => false,
			],
			'sortalpha' => [
				'type' => 'check',
				'name' => 'sortalpha',
				'default' => $this->sortAlpha,
				'label-message' => 'searchdigest-form-sortalpha',
				'required' => false,
			]
		];

		$form = HTMLForm::factory( 'ooui', $fields, $this->getContext() )
			->setMethod( 'get' )
			->setTitle( $this->getPageTitle() ) // Remove subpage
			->setSubmitCallback( [ $this, 'onSubmit' ] )
			->setWrapperLegendMsg( 'searchdigest' )
			->addHeaderHtml( $this->msg( 'searchdigest-help' )->parseAsBlock() )
			->setSubmitTextMsg( 'searchdigest-form-submit' )
			->prepareForm();
		$form->displayForm( false );
	}

	protected function displayAdminForm() {
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

	public function isSyndicated() {
		return false;
	}

	public function getQueryInfo() {
		global $wgSearchDigestMinimumMisses, $wgSearchDigestDateThreshold;
		$db = $this->getRecacheDB();

		// Get the threshold
		$dateLimit = date( 'Y-m-d', ( wfTimestamp( TS_UNIX ) - $wgSearchDigestDateThreshold ) );

		$conds = [
			'sd_touched > ' . $db->addQuotes( $dateLimit ),
			'sd_misses >= ' . $wgSearchDigestMinimumMisses
		];

		if ( $this->prefix != '' ) {
			$conds[] = 'sd_query ' . $db->buildLike( $this->prefix, $db->anyString() );
		}

		return [
			'tables' => [ 'searchdigest' ],
			'fields' => [ 'sd_query', 'sd_misses' ],
			'conds' => $conds,
		];
	}

	protected function getOrderFields() {
		if ( $this->sortAlpha ) {
			return [ 'sd_query' ];
		}

		return [ 'sd_misses' ];
	}

	protected function formatResult( $skin, $result ) {
		$title = Title::newFromText( $result->sd_query );

		if ( $title === null || $title->isKnown() ) {
			// If the title is null or is a valid page, don't show this row.
			return false;
		}

		$link = $this->linkRenderer->makeLink( $title );

		return $link . ' (' . $result->sd_misses . ') <span class="sd-cr-btn" data-page="' . htmlspecialchars( $result->sd_query, ENT_QUOTES ) . '"></span>';
	}

	protected function getGroupName() {
		return 'pages';
	}

	protected function linkParameters() {
		$params = [];

		if ( $this->prefix != '' ) {
			$params['prefix'] = $this->prefix;
		}
		if ( $this->sortAlpha ) {
			$params['sortalpha'] = $this->sortAlpha;
		}

		return $params;
	}

	protected function sortDescending() {
		if ( $this->sortAlpha ) {
			return false;
		}

		return true;
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
