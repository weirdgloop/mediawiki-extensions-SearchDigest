<?php

namespace MediaWiki\Extension\SearchDigest;

use Wikimedia\Rdbms\IDatabase;
use MediaWiki\MediaWikiServices;

/**
 * Maintenance functions for SearchDigest
 */
class SearchDigestMaintenance {
	/**
	 * @var IDatabase
	 */
	private IDatabase $dbw;

	/**
	 * @var string
	 */
	private string $table;

	public function __construct() {
    $this->dbw = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->getMainLB()->getConnection( DB_PRIMARY );
    $this->table = 'searchdigest';
  }

  public function doTableWipe () {
    $res = $this->dbw->delete($this->table, "*");
    return $res;
  }

  public function removeOldEntries () {
    $dateLimit = date( 'Y-m-d', ( wfTimestamp( TS_UNIX ) - 604800 ) ); // 1 week
    $res = $this->dbw->delete( $this->table, [ 'sd_touched < ' . $this->dbw->addQuotes( $dateLimit ) ]);
    return $res;
  }
}
