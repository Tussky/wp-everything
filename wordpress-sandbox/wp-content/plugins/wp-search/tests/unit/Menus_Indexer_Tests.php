<?php
/**
 * Menus Indexer unit tests.
 *
 * @package WP_Search
 */

namespace WP_Search\Tests;

use Brain\Monkey\Functions;
use WP_Search\Menus_Indexer;

/**
 * Tests for Menus_Indexer.
 */
class Menus_Indexer_Tests extends Test_Case {

	/**
	 * In-memory transient store.
	 *
	 * @var array<mixed>
	 */
	private static $transients = array();

	/**
	 * Set up common stubs and seed admin menu globals.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		self::$transients = array();

		global $menu, $submenu;
		$menu    = array(
			array( 'Settings', 'manage_options', 'options-general.php', '', 'menu-top', 'menu-settings', 'dashicons-admin-settings' ),
		);
		$submenu = array(
			'options-general.php' => array(
				array( 'General', 'manage_options', 'options-general.php' ),
			),
		);

		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'admin_url' )->justReturn( 'https://example.com/wp-admin/' );
		Functions\when( 'wp_strip_all_tags' )->returnArg();
		Functions\when( 'apply_filters' )->alias(
			function ( $tag, $value ) {
				return $value;
			}
		);

		Functions\when( 'set_transient' )->alias(
			function ( $key, $value, $ttl = 0 ) {
				self::$transients[ $key ] = $value;
				return true;
			}
		);
		Functions\when( 'get_transient' )->alias(
			function ( $key ) {
				return self::$transients[ $key ] ?? false;
			}
		);
	}

	/**
	 * Source label must be menus.
	 *
	 * @return void
	 */
	public function test_source_constant(): void {
		$this->assertSame( 'menus', Menus_Indexer::SOURCE );
	}

	/**
	 * No permission should yield no results.
	 *
	 * @return void
	 */
	public function test_search_requires_manage_options(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$indexer = new Menus_Indexer();
		$this->assertEmpty( $indexer->search( 'settings' ) );
	}

	/**
	 * Empty query should return every cached menu record.
	 *
	 * @return void
	 */
	public function test_search_empty_query_returns_all(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		$indexer = new Menus_Indexer();
		$indexer->reindex();

		$results = $indexer->search( '' );
		$this->assertCount( 2, $results );
		$this->assertSame( 'menus', $results[0]['source'] );
	}

	/**
	 * Searching should match menu titles.
	 *
	 * @return void
	 */
	public function test_search_matches_title(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		$indexer = new Menus_Indexer();
		$indexer->reindex();

		$results = $indexer->search( 'settings' );
		$this->assertCount( 1, $results );
		$this->assertSame( 'Settings', $results[0]['menu_title'] );
	}

	/**
	 * No-match queries should return an empty array.
	 *
	 * @return void
	 */
	public function test_search_returns_empty_for_no_match(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		$indexer = new Menus_Indexer();
		$indexer->reindex();

		$results = $indexer->search( 'xyz-no-match' );
		$this->assertEmpty( $results );
	}

	/**
	 * Results should respect the limit.
	 *
	 * @return void
	 */
	public function test_search_respects_results_limit(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		global $menu, $submenu;
		$menu    = array();
		$submenu = array();
		for ( $i = 1; $i <= 25; $i++ ) {
			$menu[] = array( "Menu {$i}", 'manage_options', "menu-{$i}.php" );
		}

		$indexer = new Menus_Indexer();
		$indexer->reindex();

		$results = $indexer->search( '' );
		$this->assertSame( Menus_Indexer::RESULTS_LIMIT, count( $results ) );
	}

	/**
	 * Reindex should build the cache and return the record count.
	 *
	 * @return void
	 */
	public function test_reindex_counts_records(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		$indexer = new Menus_Indexer();
		$count   = $indexer->reindex();

		$this->assertSame( 2, $count );
		$this->assertSame( 2, count( get_transient( Menus_Indexer::INDEX_TRANSIENT_KEY ) ) );
	}

	/**
	 * Reindex outside admin (e.g. a REST request) must not fatal and must
	 * return 0 when the $menu/$submenu globals are undefined.
	 *
	 * Regression: reindex() passed these globals to collect( array $menu,
	 * array $submenu ) which is strictly typed to array, producing a
	 * TypeError -> HTTP 500 when the menus transient was empty/missing.
	 *
	 * @return void
	 */
	public function test_reindex_returns_zero_when_menu_globals_absent(): void {
		Functions\when( 'is_admin' )->justReturn( false );

		global $menu, $submenu;
		unset( $menu, $submenu );

		$indexer = new Menus_Indexer();
		$count   = $indexer->reindex();

		$this->assertSame( 0, $count );
		$this->assertFalse( get_transient( Menus_Indexer::INDEX_TRANSIENT_KEY ) );
	}

	/**
	 * Search outside admin with no cached index must not fatal and must
	 * return an empty result set rather than triggering a rebuild that
	 * reads undefined menu globals (the HTTP 500 regression).
	 *
	 * @return void
	 */
	public function test_search_outside_admin_without_index_returns_empty(): void {
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'current_user_can' )->justReturn( true );

		global $menu, $submenu;
		unset( $menu, $submenu );

		$indexer = new Menus_Indexer();
		$results = $indexer->search( 'general' );

		$this->assertSame( array(), $results );
	}
}
