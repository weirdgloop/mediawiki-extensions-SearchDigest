<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class PopulateDummySearches extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Populate SearchDigest table with dummy data for testing/debugging purposes' );
		$this->addOption( 'purge', 'Whether to remove existing entries in the table' );
		$this->addArg( 'entries', 'Number of entries to insert' );

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
		$purge = (bool)$this->getOption( 'purge', false );
		$numEntries = (int)$this->getArg( 0, 1000 );
		$dbw = $this->getDB( DB_PRIMARY );
		$this->beginTransaction( $dbw, __METHOD__ );

		$this->output( "Working...\n" );
		$rows = [];

		if ( $purge ) {
			$this->output( "Purging searchdigest table...\n" );
			$dbw->delete( 'searchdigest', IDatabase::ALL_ROWS );
		}

		for ($x = 0; $x < $numEntries; $x++) {
			$rows[] = [
				'sd_query' => Title::makeTitleSafe( NS_MAIN, $this->generateRandomString() )->getFullText(),
				'sd_misses' => rand(1, 2000),
				'sd_touched' => date("Y-m-d H:i:s")
			];
		}

		$this->output( "Inserting entries...\n" );

		$dbw->insert(
			'searchdigest',
			$rows,
			__METHOD__,
			[ 'IGNORE' ]
		);
		$this->commitTransaction( $dbw, __METHOD__ );
		$this->output( "Done!\n" );

		return true;
	}
}

$maintClass = 'PopulateDummySearches';
require_once RUN_MAINTENANCE_IF_MAIN;
