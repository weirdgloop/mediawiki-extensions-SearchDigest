<?php

namespace MediaWiki\Extension\SearchDigest;

use Wikimedia\Rdbms\IDatabase;
use MediaWiki\MediaWikiServices;

class SearchDigestRecord {
  private const TABLE_NAME = 'searchdigest';

  private $query;
  private $misses;
  private $touched;

  /** @var IDatabase */
  private IDatabase $dbw;

  public function __construct() {
	  $this->dbw = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->getMainLB()->getConnection( DB_PRIMARY );
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
    // TODO: Any validation if needed
    $this->query = $val;
  }

  /**
   * Get the count of misses for this record
   * @return int|null
   */
  public function getMisses () {
    return $this->misses;
  }

  /**
   * Set the count of misses for this record
   * @param int $val
   */
  public function setMisses ( $val ) {
    if ( !is_integer( $val ) ) {
      $msg = wfMessage( 'searchdigest-error-invalid-misses', $val, gettype( $val ) );
      throw new SearchDigestException ( $msg );
    }

    $this->misses = $val;
  }

  /**
   * Get the date that this record was touched
   * @return string|null
   */
  public function getTouched () {
    return $this->touched;
  }

  /**
   * Set the date that this record was touched
   * @param string|null
   */
  public function setTouched ( $val ) {
    // TODO: Any validation if needed
    $this->touched = $val;
  }

  /**
   * Returns existing record if there is one, based on the search query.
   * @param string
   * @return SearchDigestRecord|null
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
        'sd_query',
        'sd_misses',
        'sd_touched'
      ],
      [
        'sd_query' => $query
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
      'sd_misses' => $this->getMisses(),
      'sd_touched' => $this->getTouched()
    ];

    $this->dbw->upsert(
      self::TABLE_NAME,
      [
        'sd_query' => $this->getQuery()
      ] + $vals,
      [
        [ 'sd_query' ]
      ],
      $vals,
      __METHOD__
    );
  }

  private static function fromRow ( $row ) {
    $r = new self();
    $r->setQuery( $row->sd_query );
    $r->setMisses( (int)$row->sd_misses );
    $r->setTouched( $row->sd_touched );
    return $r;
  }
}
