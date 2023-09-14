<?php

namespace MediaWiki\Extension\SearchDigest;

use Exception;
use Message;

class SearchDigestException extends Exception {
  public function __construct( Message $message, $code = 0, $previous = null ) {
		parent::__construct( $message, $code, $previous );
	}
}
