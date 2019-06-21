<?php

class SearchDigestException extends Exception {
  public function __construct( Message $message, $code = 0, Exception $previous = null ) {
		$this->parsedMessage = $message->parse();
		parent::__construct( $this->parsedMessage, $code, $previous );
	}

	/**
	 * Get the message text.
	 *
	 * For some reason, php decides to add the stack trace to the exception message
	 * which make it unsuitable for being used for user-facing errors.
	 * This removes that issue.
	 *
	 * @return string
	 */
	public function getParsedMessage() {
		return $this->parsedMessage();
	}
}