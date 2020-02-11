<?php

use MediaWiki\MediaWikiServices;

class SpecialSearchDigest extends QueryPage {
  function __construct() {
    parent::__construct( 'SearchDigest' );
    $this->linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
  }

  function execute( $par ) {
		global $wgSearchDigestCreateRedirect;

		// Add intro text before we execute parent function so that it renders before
		$out = $this->getOutput();
		$out->addModuleStyles( [ 'ext.searchdigest.styles' ] );
		$out->addWikiText( wfMessage( 'searchdigest-help' )->text() );

		parent::execute( $par );

		$out->enableOOUI();

		// If the user has the searchdigest-admin permission, add admin tools to page
		if ( $this->getUser()->isAllowed( 'searchdigest-admin' ) ) {
			$out->addModuleStyles( [ 'oojs-ui.styles.icons-moderation' ] );

			$out->addHtml('<div class="sd-admin-tools"><p>' . wfMessage( 'searchdigest-admintools-help' )->plain() . '</p>');
			$this->createAdminForm();
			$out->addHtml('</div>');

			// $btnGrp = new OOUI\ButtonGroupWidget( [
			// 	'items' => [
			// 		new OOUI\ButtonWidget( [
			// 			'infusable' => true,
			// 			'icon' => 'trash',
			// 			'label' => wfMessage( 'searchdigest-admintools-clear' )->plain()
			// 		] )
			// 	]
			// ] );
			// $out->addHtml('<div class="sd-admin-tools"><p>' . wfMessage( 'searchdigest-admintools-help' )->plain() . '</p>' . $btnGrp . '</div>');
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
		};
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


	public function reallyDoQuery( $limit, $offset = false ) {
		$fname = static::class . '::reallyDoQuery';
		$dbr = $this->getRecacheDB();
		$query = $this->getQueryInfo();
		$order = $this->getOrderFields();
		if ( $this->sortDescending() ) {
			foreach ( $order as &$field ) {
				$field .= ' DESC';
			}
		}
		$tables = isset( $query['tables'] ) ? (array)$query['tables'] : [];
		$fields = isset( $query['fields'] ) ? (array)$query['fields'] : [];
    $conds = isset( $query['conds'] ) ? (array)$query['conds'] : [];
		$options = isset( $query['options'] ) ? (array)$query['options'] : [];
		$join_conds = isset( $query['join_conds'] ) ? (array)$query['join_conds'] : [];
		if ( $limit !== false ) {
			$options['LIMIT'] = intval( $limit );
		}
		if ( $offset !== false ) {
			$options['OFFSET'] = intval( $offset );
		}
    $options['INNER ORDER BY'] = $order;
    $options['ORDER BY'] = [ 'sd_misses DESC' ];
    $res = $dbr->select( $tables, $fields, $conds, $fname,
      $options, $join_conds
    );
		return $res;
  }
  
  function getOrderFields() {
    return [ 'sd_misses' ];
  }
  
  function formatResult( $skin, $row ) {
		global $wgSearchDigestStrikeValidPages;

		$title = Title::newFromText( $row->sd_query );
		$link = $this->linkRenderer->makeLink( $title );
		$isKnown = $title->isKnown() === true;
		if ( ( $isKnown ) && ( $wgSearchDigestStrikeValidPages === true ) ) {
			$link = '<s>' . $link . '</s>';
		}

    return $link . ' (' . $row->sd_misses . ') ' . ( $isKnown ? '' : '<span class="sd-cr-btn" data-page="' . htmlspecialchars($row->sd_query, ENT_QUOTES) . '"></span>' );
  }

  function getGroupName() {
    return 'pages';
  }
}
