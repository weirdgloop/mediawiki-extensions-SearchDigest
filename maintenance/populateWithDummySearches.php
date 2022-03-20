<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class PopulateDummySearchDigest extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Populate SearchDigest table with dummy data' );

		$this->requireExtension( 'SearchDigest' );
	}
  
  public function generateRandomString($length = 30) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
  }

	public function execute() {
		$db = $this->getDB( DB_PRIMARY );

    for ($x = 0; $x <= 1000; $x++) {
      $vals = [
        'sd_query' => $this->generateRandomString(),
        'sd_misses' => rand( 1, 2000 ),
        'sd_touched' => date("Y-m-d H:i:s")
      ];
  
      $dbw = wfGetDB( DB_PRIMARY );
      $dbw->upsert(
        'searchdigest',
        $vals,
        [
          'sd_query'
        ],
        $vals,
        __METHOD__
      );
    }

    return true;
	}
}

$maintClass = 'PopulateDummySearchDigest';
require_once RUN_MAINTENANCE_IF_MAIN;
