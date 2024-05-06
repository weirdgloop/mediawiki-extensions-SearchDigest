<?php

namespace MediaWiki\Extension\SearchDigest;

use Wikimedia\Rdbms\IDatabase;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\ActorNormalization;
use MediaWiki\User\ActorStore;
use MediaWiki\User\UserIdentity;

class SearchDigestBlocksRecord {
	private const TABLE_NAME = 'searchdigest_blocks';

	private $query;
	private $added;
	private $actor;

	/** @var IDatabase */
	private IDatabase $dbw;

	private ActorStore $actorStore;

	public function __construct() {
		$this->dbw = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->getMainLB()->getConnection( DB_PRIMARY );
		$this->actorStore = MediaWikiServices::getInstance()->getActorStore();

		$this->setAdded( date("Y-m-d H:i:s") );
	}

	/**
	 * Get the search query associated with this record
	 * @return string|null
	 */
	public function getQuery () {
		return $this->query;
	}

	/**
	 * Sets the search query associated with this record
	 * @param string $val
	 */
	public function setQuery ( $val ) {
		$this->query = $val;
	}

	/**
	 * Get the actor who added this record
	 */
	public function getActor (): UserIdentity {
		return $this->actor;
	}

	/**
	 * Set the actor who added this record
	 * @param mixed $val - An UserIdentity object or an integer representing the actor's ID
	 */
	public function setActor ( $val ) {
		if ( $val instanceof UserIdentity ) {
			$this->actor = $val;
		} else {
			$actor = $this->actorStore->getActorById( $val, $this->dbw );
			$this->actor = $actor;
		}

		if ( is_null( $this->actor ) ) {
			$this->actor = $this->actorStore->getUnknownActor();
		}
	}

	/**
	 * Get the date that this record was added
	 * @return string|null
	 */
	public function getAdded () {
		return $this->added;
	}

	/**
	 * Set the date that this record was added
	 * @param string|null
	 */
	public function setAdded ( $val ) {
		$this->added = $val;
	}

	/**
	 * Returns existing record if there is one, based on the search query.
	 * @param string
	 * @return SearchDigestBlocksRecord|null
	 */
	public static function newFromQuery( $query ) {
		if ( !is_string( $query ) ) {
			$msg = wfMessage( 'searchdigest-error-invalid-query', $query, gettype( $query ) );
			throw new SearchDigestException ( $msg );
		}

		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->getMainLB()->getConnection( DB_PRIMARY );
		$res = $dbw->select(
			self::TABLE_NAME,
			[
				'sd_blocks_query',
				'sd_blocks_added',
				'sd_blocks_actor'
			],
			[
				'sd_blocks_query' => $query
			],
			__METHOD__,
			[
				'LIMIT' => 1
			]
		);

		$ret = null;

		if ( $res->numRows() !== 0 ) {
			$row = $res->current();
			$ret = self::fromRow( $row );
		}

		return $ret;
	}

	public function save () {
		$vals = [
			'sd_blocks_added' => $this->getAdded(),
			'sd_blocks_actor' => MediaWikiServices::getInstance()->getActorNormalization()->findActorId( $this->actor, $this->dbw )
		];

		$this->dbw->upsert(
			self::TABLE_NAME,
			[
				'sd_blocks_query' => $this->getQuery()
			] + $vals,
			[
				[ 'sd_blocks_query' ]
			],
			$vals,
			__METHOD__
		);
	}

	public function remove () {
		$this->dbw->startAtomic( __METHOD__ );
		$this->dbw->delete(
			self::TABLE_NAME,
			[
				'sd_blocks_query' => $this->getQuery()
			],
			__METHOD__
		);
		$this->dbw->endAtomic( __METHOD__ );
	}

	public static function fromRow ( $row ) {
		$r = new self();
		$r->setQuery( $row->sd_blocks_query );
		$r->setAdded( $row->sd_blocks_added );
		$r->setActor( $row->sd_blocks_actor );
		return $r;
	}
}
