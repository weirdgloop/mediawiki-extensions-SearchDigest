<?php

/**
 * Maintenance functions for SearchDigest
 */
class SearchDigestMaintenance {
  public function __construct() {
    $this->dbw = wfGetDB( DB_MASTER );
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