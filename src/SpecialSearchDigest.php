<?php

namespace MediaWiki\Extension\SearchDigest;

use HTMLForm;
use Html;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\PermissionManager;
use QueryPage;
use MediaWiki\Title\Title;

class SpecialSearchDigest extends QueryPage {
	private LinkRenderer $linkRenderer;

	private PermissionManager $permManager;

	/** @var string */
	protected string $par = '';

	protected string $prefix = '';

	protected string $query = '';

	protected bool $sortAlpha = false;

	protected string $startTimestamp;

	protected $blockedQueries = [];

	function __construct() {
		parent::__construct( 'SearchDigest', 'searchdigest-reader' );
		$this->linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		$this->permManager = MediaWikiServices::getInstance()->getPermissionManager();
	}

	protected function addSubtitle() {
		$main = $this->linkRenderer->makePreloadedLink( Title::newFromText( 'SearchDigest', NS_SPECIAL ), $this->msg( 'searchdigest-nav-main' )->text() );
		$stats = $this->linkRenderer->makePreloadedLink( Title::newFromText( 'SearchDigest/stats', NS_SPECIAL ), $this->msg( 'searchdigest-nav-stats' )->text() );

		$links = [ $main, $stats ];
		if ( $this->permManager->userHasRight( $this->getUser(), 'searchdigest-block' ) ) {
			$links[] = $this->linkRenderer->makePreloadedLink( Title::newFromText( 'SearchDigest/block', NS_SPECIAL ), $this->msg( 'searchdigest-nav-block' )->text() );
		}

		$this->getOutput()->addSubtitle( implode( ' | ', $links ) );
	}

	function execute( $par ) {
		global $wgSearchDigestCreateRedirect;
		$this->par = $par ?? '';

		$out = $this->getOutput();
		$this->setHeaders();
		$this->addSubtitle();

		$out->addModuleStyles( [ 'ext.searchdigest.styles' ] );

		$this->query = $this->getRequest()->getText('query');

		if ( $this->par === 'block' ) {
			$this->checkUserCanBlock();
			$this->displayBlockForm();
		} else if ( $this->par === 'stats' ) {
			$this->executeStats();

			// Return early so that we don't do any of the standard QueryPage stuff
			return;
		} else if ( $this->par === 'unblock' ) {
			$this->executeUnblock();

			// Return early so that we don't do any of the standard QueryPage stuff
			return;
		} else {
			$this->setStartTimestamp();

			// If a character prefix was provided, we will only return results that start with that character.
			$this->prefix = Title::makeTitleSafe( NS_MAIN, $this->getRequest()->getRawVal( 'prefix' ) ) ?? '';
			$this->sortAlpha = $this->getRequest()->getBool( 'sortalpha' );

			$this->displayViewForm();
		}

		parent::execute( $par );
		$this->setLinkBatchFactory( MediaWikiServices::getInstance()->getLinkBatchFactory() );

		if ( $this->par === 'block' ) {
			$out->setPageTitle( $this->msg( 'searchdigest-block' ) );
		} else {
			// If the user has the searchdigest-admin permission, add admin tools to page
			if ( $this->permManager->userHasRight( $this->getUser(), 'searchdigest-admin' ) ) {
				$out->addModuleStyles( [ 'oojs-ui.styles.icons-moderation' ] );

				$out->addHtml('<div class="searchdigest-admin-tools"><p>' . wfMessage( 'searchdigest-admintools-help' )->plain() . '</p>');
				$this->displayAdminForm();
				$out->addHtml('</div>');
			}

			// Additional client JS for redirect button
			if ( $wgSearchDigestCreateRedirect === true ) {
				$out->addModules( 'ext.searchdigest.redirect' );
			}

		}
	}

	protected function checkUserCanBlock(): bool {
		$userCanBlock = $this->permManager->userHasRight( $this->getUser(), 'searchdigest-block' );
		if ( !$userCanBlock ) {
			throw new \PermissionsError( 'searchdigest-block' );
		}

		return true;
	}

	protected function checkUserCanViewStats(): bool {
		$userCanBlock = $this->permManager->userHasRight( $this->getUser(), 'searchdigest-reader-stats' );
		if ( !$userCanBlock ) {
			throw new \PermissionsError( 'searchdigest-reader-stats' );
		}

		return true;
	}

	protected function setStartTimestamp() {
		global $wgSearchDigestDateThreshold;

		// Set the threshold, and allow overriding with a query parameter
		$currentTs = wfTimestamp();
		$fromTs = $this->getRequest()->getInt( 'from' );
		if ( $fromTs > $currentTs ) {
			$this->getOutput()->showErrorPage( 'error', 'searchdigest-error-fromtoohigh' );
			return;
		}
		$this->startTimestamp = ( $fromTs > 0 ) ? wfTimestamp( TS_UNIX, $fromTs ) : ( $currentTs - $wgSearchDigestDateThreshold );
	}

	protected function executeUnblock() {
		$this->checkUserCanBlock();

		$out = $this->getOutput();
		$out->setPageTitle($this->msg('searchdigest-unblock'));

		$fields = [
			'query' => [
				'type' => 'text',
				'name' => 'query',
				'default' => $this->query,
				'required' => true,
			]
		];

		$form = HTMLForm::factory( 'ooui', $fields, $this->getContext() )
			->setWrapperLegendMsg( 'searchdigest-unblock' )
			->addHeaderHtml( $this->msg( 'searchdigest-unblock-help', $this->linkRenderer->makePreloadedLink( Title::newFromText( 'SearchDigest', NS_SPECIAL ) ) )->plain() )
			->setFormIdentifier( 'searchdigest-unblock' )
			->setSubmitTextMsg( 'searchdigest-unblock-form-submit' )
			->setSubmitCallback( [ $this, 'onUnblockSubmit' ] )
			->show();
	}

	protected function executeStats() {
		$out = $this->getOutput();
		$out->setPageTitle($this->msg('searchdigeststats'));

		$this->checkUserCanViewStats();
		$this->setStartTimestamp();

		$out->addWikiMsg( 'searchdigest-stats-intro', date( 'Y-m-d', $this->startTimestamp ) );

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
			$page_exists_text = $this->getLanguage()->formatNum( $page_exists );
			$total_text = $this->getLanguage()->formatNum( $total );

			if ( $total === 0 ) {
				$perc_exists = number_format( 0, 2 );
			} else {
				$perc_exists = number_format( ($page_exists / $total) * 100, 2 );
			}

			$link = $this->linkRenderer->makePreloadedLink(
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
	<td>$page_exists_text / $total_text</td>
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

	protected function displayViewForm() {
		$fields = [
			'prefix' => [
				'type' => 'text',
				'name' => 'prefix',
				'label-message' => 'searchdigest-form-prefix',
				'required' => false,
			],
			'sortalpha' => [
				'type' => 'check',
				'name' => 'sortalpha',
				'default' => $this->sortAlpha,
				'label-message' => 'searchdigest-form-sortalpha',
				'required' => false,
			]
		];

		$form = HTMLForm::factory( 'ooui', $fields, $this->getContext() )
			->setMethod( 'get' )
			->setTitle( $this->getPageTitle() ) // Remove subpage
			->setWrapperLegendMsg( 'searchdigest' )
			->addHeaderHtml( $this->msg( 'searchdigest-help', date( 'Y-m-d', $this->startTimestamp ) )->parseAsBlock() )
			->setSubmitTextMsg( 'searchdigest-form-submit' )
			->addHiddenField( 'from', $this->startTimestamp )
			->prepareForm();
		$form->displayForm( false );
	}

	protected function displayBlockForm() {
		$fields = [
			'query' => [
				'type' => 'text',
				'name' => 'query',
				'default' => $this->query,
				'label-message' => 'searchdigest-block-form-query',
				'required' => true,
			]
		];

		$form = HTMLForm::factory( 'ooui', $fields, $this->getContext() )
			->setWrapperLegendMsg( 'searchdigest-block-title' )
			->addHeaderHtml( $this->msg( 'searchdigest-block-help' ) )
			->setFormIdentifier( 'searchdigest-block' )
			->setSubmitTextMsg( 'searchdigest-block-form-submit' )
			->setSubmitDestructive()
			->setSubmitCallback( [ $this, 'onBlockSubmit' ] );
		$form->showAlways();
	}

	protected function displayAdminForm() {
		$desc = [
			'select' => [
				'type' => 'select',
				'options' => [
					wfMessage( 'searchdigest-admintools-rmold' )->plain() => 'rmold',
					wfMessage( 'searchdigest-admintools-dbwipe' )->plain() => 'dbwipe'
				]
			]
		];
		$form = HTMLForm::factory( 'ooui', $desc, $this->getContext(), 'searchdigest' );
		$form
			->setSubmitText( wfMessage( 'searchdigest-admintools-submit' )->plain() )
			->setSubmitDestructive()
			->setSubmitCallback( [ $this, 'onAdminFormSubmit' ] )
			->setFormIdentifier( 'searchdigest-admin' )
			->show();
	}

	/**
	 * @param $formData
	 * @param HTMLForm $form
	 */
	public function onBlockSubmit( $formData ) {
		$this->checkUserCanBlock();
		$query = $formData[ 'query' ];

		// Check it is not already blocked
		if ( SearchDigestBlocksRecord::newFromQuery( $query ) !== null ) {
			return wfMessage( 'searchdigest-error-blockalreadyblocked' );
		}

		$blockRecord = new SearchDigestBlocksRecord();
		$blockRecord->setQuery( $query );
		$blockRecord->setActor( $this->getUser() );
		$blockRecord->save();

		$out = $this->getOutput();
		$out->addHTML(Html::successBox(
			$out->msg( 'searchdigest-block-complete', $query )
				->parse()
		));

		return true;
	}

	public function onUnblockSubmit( $formData ) {
		$this->checkUserCanBlock();
		$query = $formData[ 'query' ];

		// Check it is actually blocked
		$rec = SearchDigestBlocksRecord::newFromQuery( $query );
		if ( $rec === null ) {
			return wfMessage( 'searchdigest-error-notblocked' );
		}

		$rec->remove();

		$out = $this->getOutput();
		$out->addHTML(Html::successBox(
			$out->msg( 'searchdigest-unblock-complete', $query )
				->parse()
		));

		$out->returnToMain( null, Title::newFromText( 'SearchDigest/block', NS_SPECIAL ) );

		return true;
	}

	public function onAdminFormSubmit( $formData ) {
		// redundancy permission check
		if ( $this->getUser()->isAllowed( 'searchdigest-admin' ) ) {
			$maint = new SearchDigestMaintenance();
			switch ( $formData[ 'select' ] ) {
				case 'rmold':
					$maint->removeOldEntries();
					break;
				case 'dbwipe':
					$maint->doTableWipe();
					break;
			};
			return false;
		} else {
			return 'searchdigest-admintools-noperms';
		}
	}

	public function isSyndicated() {
		return false;
	}

	public function getQueryInfo() {
		global $wgSearchDigestMinimumMisses;
		$db = $this->getRecacheDB();

		$conds = [];

		if ( $this->par === 'block' ) {
			$tables = [ 'searchdigest_blocks' ];
			$fields = [ 'sd_blocks_query', 'sd_blocks_added', 'sd_blocks_actor' ];
		} else {
			$tables = [ 'searchdigest' ];
			$fields = [ 'sd_query', 'sd_misses', 'sd_touched' ];
			$conds = [
				'sd_touched > ' . $db->addQuotes( date( 'Y-m-d', $this->startTimestamp ) ),
				'sd_misses >= ' . $wgSearchDigestMinimumMisses
			];

			if ( $this->prefix != '' ) {
				$conds[] = 'sd_query ' . $db->buildLike( $this->prefix, $db->anyString() );
			}
		}

		return [
			'tables' => $tables,
			'fields' => $fields,
			'conds' => $conds,
		];
	}

	protected function getOrderFields() {
		if ( $this->par === 'block' ) {
			return [ 'sd_blocks_added' ];
		} else {
			if ( $this->sortAlpha ) {
				return [ 'sd_query' ];
			}

			return [ 'sd_misses' ];
		}
	}

	protected function formatResult( $skin, $result ) {
		if ( $this->par === 'block' ) {
			$blocksRecord = SearchDigestBlocksRecord::fromRow( $result );
			$user = MediaWikiServices::getInstance()->getUserFactory()->newFromUserIdentity( $blocksRecord->getActor() );
			$userLink = $this->linkRenderer->makeLink( $user->getUserPage() );

			$unblockText = $this->linkRenderer->makePreloadedLink(
				Title::newFromText( 'SearchDigest/unblock', NS_SPECIAL ),
				$this->msg( 'searchdigest-unblock-buttontext' )->escaped(),
				'',
				[],
				[ 'query' => $blocksRecord->getQuery() ]
			);

			$added = $this->getLanguage()->userTimeAndDate( $blocksRecord->getAdded(), $this->getUser() );

			return $blocksRecord->getQuery() . ' (' .
				$this->msg( 'searchdigest-block-actor', $userLink, $added )->plain() . ') (' . $unblockText . ')';
		} else {
			$title = Title::newFromText( $result->sd_query );

			if ( $title === null || $title->isKnown() ) {
				// If the title is null or is a valid page, don't show this row.
				return false;
			}

			if ( in_array( $result->sd_query, $this->blockedQueries ) ) {
				return false;
			}

			$link = $this->linkRenderer->makeLink( $title );
			$blockText = '';

			if ( $this->permManager->userHasRight( $this->getUser(), 'searchdigest-block' ) ) {
				$blockText = ' &#183; ' . $this->linkRenderer->makePreloadedLink(
						Title::newFromText( 'SearchDigest/block', NS_SPECIAL ),
						$this->msg( 'searchdigest-block-buttontext' )->escaped(),
						'',
						[],
						[ 'query' => $result->sd_query ]
					);
			}

			return $link . ' (' . $result->sd_misses . ') (<a role="button" class="sd-cr-btn" data-page="' .
				htmlspecialchars( $result->sd_query, ENT_QUOTES ) . '">'
				. $this->msg( 'searchdigest-redirect-buttontext' )->escaped() . '</a>' . $blockText . ')';
		}
	}

	protected function getGroupName() {
		return 'pages';
	}

	protected function linkParameters() {
		$params = [];

		if ( !$this->par ) {
			if ( $this->prefix != '' ) {
				$params['prefix'] = $this->prefix;
			}
			if ( $this->sortAlpha ) {
				$params['sortalpha'] = $this->sortAlpha;
			}
			if ( $this->startTimestamp ) {
				$params['from'] = $this->startTimestamp;
			}
		}

		return $params;
	}

	protected function sortDescending() {
		if ( $this->sortAlpha ) {
			return false;
		}

		return true;
	}

	protected function preprocessResults( $db, $res ) {
		if ( !$this->par ) {
			$queries = [];

			/**
			 * This pre-processing is similar to QueryPage::executeLBFromResultWrapper(), but slightly different as the
			 * row is called "sd_query" instead of "title". We're also assuming that the namespace is going to be NS_MAIN,
			 * but this may not always be true.
			 */
			if ( !$res->numRows() ) {
				return;
			}

			$batch = $this->getLinkBatchFactory()->newLinkBatch();

			foreach ( $res as $row ) {
				$batch->add( NS_MAIN, $row->sd_query );
				$queries[] = $row->sd_query;
			}
			$batch->execute();

			$res->seek( 0 );

			/**
			 * Our list of queries should now be checked to see if any of them are blocked by a user with the
			 * searchdigest-block right. We're pre-processing this as a batch here, rather than individually,
			 * for speed and performance (to avoid 500 database calls for each 500 result page).
			 */
			$this->blockedQueries = SearchDigestBlocksRecord::checkQueries( $queries );
		}
	}
}
