<?php

use MediaWiki\MediaWikiServices;

class SearchDigestPager extends ReverseChronologicalPager {
  function __construct() {
    parent::__construct();
    $this->mDb = wfGetDB( DB_SLAVE );
    $this->linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
  }

  function formatRow( $row ) {
		global $wgSearchDigestStrikeValidPages;

		$title = Title::newFromText( $row->sd_query );
		$link = $this->linkRenderer->makeLink( $title );
		if ( ( $title->isKnown() === true ) && ( $wgSearchDigestStrikeValidPages === true ) ) {
			$link = '<s>' . $link . '</s>';
		}

    return Xml::tags( 'li', null, $link . ' (' . $row->sd_misses . ')' );
  }

  function getQueryInfo() {
		// Get the date one week ago
		$dateLimit = date( 'Y-m-d', ( wfTimestamp( TS_UNIX ) - 604800 ) );
		return [
			'tables' => [ 'searchdigest' ],
			'fields' => [ 'sd_query', 'sd_misses' ],
			'conds' => [ 'sd_touched > ' . $this->mDb->addQuotes( $dateLimit ) ],
			'options' => [ 'ORDER_BY sd_misses DESC' ],
    ];
  }

	function getIndexField() {
		return 'sd_misses';
  }
  
	function getStartBody() {
		return '<ul>';
	}
	function getEndBody() {
		return '</ul>';
  }
  
	function getPagingQueries() {
		if ( !$this->mQueryDone ) {
			$this->doQuery();
    }

    $this->mOffset = $this->mOffset == '' ? 0 : $this->mOffset;
    $this->mLimit = $this->mLimit == '' ? 0 : $this->mLimit;

		# Don't announce the limit everywhere if it's the default
		$urlLimit = $this->mLimit == $this->mDefaultLimit ? '' : $this->mLimit;
		$minLim = $this->mOffset - $this->mLimit;
		$maxLim = $this->mOffset + $this->mLimit;
		if ( $this->mIsFirst ) {
			$prev = false;
			$first = false;
		} else {
			$prev = array(
				'offset' => ( $minLim < 0 ? 0 : $minLim ),
				'limit' => $urlLimit
			);
			if ( $this->mIsBackwards ) {
				$prev['offset'] = $maxLim;
				$prev['dir'] = 'prev';
			}
			$first = array( 'limit' => $urlLimit );
		}
		if ( $this->mIsLast ) {
			$next = false;
			$last = false;
		} else {
			$next = array( 'offset' => $maxLim, 'limit' => $urlLimit );
			$last = array( 'dir' => 'prev', 'limit' => $urlLimit );
			if ( $this->mIsBackwards ) {
				if ( !$this->mIsFirst ) {
					$next['offset'] = ( $minLim < 0 ? 0 : $minLim );
					$next['dir'] = 'prev';
				} else {
					$next['offset'] = $this->getNumRows();
				}
			}
		}
		return array(
			'prev' => $prev,
			'next' => $next,
			'first' => $first,
			'last' => $last
		);
  }

	function reallyDoQuery( $offset, $limit, $descending ) {
		$fname = __METHOD__ . ' (' . get_class( $this ) . ')';
		$info = $this->getQueryInfo();
		$tables = $info['tables'];
		$fields = $info['fields'];
		$conds = isset( $info['conds'] ) ? $info['conds'] : array();
		$options = isset( $info['options'] ) ? $info['options'] : array();
		$join_conds = isset( $info['join_conds'] ) ? $info['join_conds'] : array();
		if ( $descending ) {
			$options['ORDER BY'] = $this->mIndexField;
		} else {
			$options['ORDER BY'] = $this->mIndexField . ' DESC';
		}
		$options['LIMIT'] = intval( $limit );
		if ( $offset != '' ) {
			if ( $offset < 0 ) {
				$offset = 0;
			}
			$options['OFFSET'] = intval( $offset );
		}
		$res = $this->mDb->select( $tables, $fields, $conds, $fname, $options, $join_conds );
		$ret = new ResultWrapper( $this->mDb, $res );
		return $ret;
	}
}