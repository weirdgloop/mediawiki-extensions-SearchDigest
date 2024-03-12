<?php

namespace MediaWiki\Extension\SearchDigest;

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use SpecialPage;

class SpecialSearchDigestStats extends SpecialPage {
	protected string $startTimestamp;

	function __construct() {
		parent::__construct( 'SearchDigestStats', 'searchdigest-reader-stats' );
	}

	function execute( $par ) {
		global $wgSearchDigestDateThreshold;

		$this->setHeaders();
		$out = $this->getOutput();

		// Set the threshold, and allow overriding with a query parameter
		$currentTs = wfTimestamp();
		$fromTs = $this->getRequest()->getInt( 'from' );
		if ( $fromTs > $currentTs ) {
			$out->showErrorPage( 'error', 'searchdigest-stats-error-fromtoohigh' );
			return;
		}
		$this->startTimestamp = ( $fromTs > 0 ) ? wfTimestamp( TS_UNIX, $fromTs ) : ( $currentTs - $wgSearchDigestDateThreshold );

		$out->addModuleStyles( [ 'ext.searchdigest.stats.styles' ] );
		$out->addWikiMsg( 'searchdigest-stats-intro', date( 'Y-m-d', $this->startTimestamp ) );

		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();

		// Make a database call to get the statistics for all letters
		$res = $this->getStatsFromDatabase();

		// Generate the percentages for A-Z
		$rows = [];
		foreach ( range( 'A', 'Z' ) as $letter ) {
			$page_exists = 0;
			$page_missing = 0;

			if ( array_key_exists( $letter, $res ) ) {
				$page_exists = $res[ $letter ][ 'exists' ] ?? 0;
				$page_missing = $res[ $letter ][ 'missing' ] ?? 0;
			}

			$total = $page_exists + $page_missing;
			if ( $total === 0 ) {
				$perc_exists = number_format( 0, 2 );
			} else {
				$perc_exists = number_format( ($page_exists / $total) * 100, 2 );
			}

			$link = $linkRenderer->makePreloadedLink(
				Title::newFromText(
					'SearchDigest', NS_SPECIAL
				), $letter, '', [], [ 'prefix' => $letter, 'from' => $this->startTimestamp ]
			);
			$rows[] = <<<EOD
<tr>
	<th style="text-align: center;">
		$link
	</th>
	<td class="searchdigest-stats-progress-bar">
		<div class="searchdigest-stats-progress-done" style="width: $perc_exists%" />
	</td>
	<td>$perc_exists%</td>
</tr>
EOD;
		}
		$rowsAsString = implode( "\n", $rows );

		$out->addHTML(<<<EOD
<table class="searchdigest-stats-table">
	<thead>
		<th style="width: 50px; text-align: center;">Initial</th>
		<th colspan="2">Percentage created</th>
	</thead>
	<tbody>
		$rowsAsString
	</tbody>
</table>
EOD
		);
	}

	protected function getStatsFromDatabase() {
		global $wgSearchDigestMinimumMisses;
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );

		$conds = [
			'sd_touched > ' . $dbr->addQuotes( date( 'Y-m-d', $this->startTimestamp ) ),
			'sd_misses >= ' . $wgSearchDigestMinimumMisses
		];

		// Make a database call to get the statistics for all letters
		$dbRes = $dbr->newSelectQueryBuilder()
			->select( [
				$dbr->buildSubString( 'sd_query', 1, 1 ). ' as sd_letter',
				'page_title IS NOT NULL as sd_exists',
				'count(1) as sd_count'
			] )
			->from( 'searchdigest' )
			->leftJoin( 'page', null, [
				'page_namespace = 0',
				'page_title = ' . $dbr->strreplace( 'sd_query', '" "', '"_"' )
			] )
			->where( $conds )
			->groupBy( '1,2' )
			->orderBy( [ 1, 2 ] )
			->fetchResultSet();

		// Add them to a nice multi-dimensional array
		$res = [];
		foreach ( $dbRes as $row ) {
			$letter = strtoupper( $row->sd_letter );
			if ( !ctype_alpha( $letter ) ) {
				continue;
			}
			$array_key = $row->sd_exists ? 'exists' : 'missing';
			$res[ $letter ][ $array_key ] = $row->sd_count;
		}
		return $res;
	}

	protected function getGroupName() {
		return 'pages';
	}
}
