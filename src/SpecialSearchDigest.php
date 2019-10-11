<?php

use MediaWiki\MediaWikiServices;

class SpecialSearchDigest extends QueryPage {
  function __construct() {
    parent::__construct( 'SearchDigest' );
    $this->linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
  }

  function execute( $par ) {
		global $wgSearchDigestCreateRedirect;

    $out = $this->getOutput();
    $out->addWikiText( wfMessage( 'searchdigest-help' )->text() );
		parent::execute( $par );
		$out->enableOOUI();
		
		if ( $wgSearchDigestCreateRedirect === true ) {
			$out->addModules( 'ext.searchdigest.redirect' );
		}
  }

  function isSyndicated() {
    return false;
  }

  function getQueryInfo() {
		// Get the date one week ago
		$dateLimit = date( 'Y-m-d', ( wfTimestamp( TS_UNIX ) - 604800 ) );
		return [
			'tables' => [ 'searchdigest' ],
			'fields' => [ 'sd_query', 'sd_misses' ],
			'conds' => [ 'sd_touched > ' . $this->getRecacheDB()->addQuotes( $dateLimit ) ],
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
