<?php

class SpecialSearchDigest extends SpecialPage {
  function __construct() {
    parent::__construct( 'SearchDigest' );
  }

  function execute( $par ) {
    $output = $this->getOutput();
    $output->setPageTitle( wfMessage( 'searchdigest' )->text() );
    $pager = new SearchDigestPager();

    $html = $pager->getNavigationBar() . $pager->getBody() . $pager->getNavigationBar();

    $output->addWikiText( wfMessage( 'searchdigest-help' )->text() );
    $output->addHTML( $html );
  }

  function getGroupName() {
    return 'pages';
  }
}
