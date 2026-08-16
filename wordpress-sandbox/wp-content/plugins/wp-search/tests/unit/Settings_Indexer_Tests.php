<?php
/**
 * Settings Indexer unit tests.
 *
 * @package WP_Search
 */

namespace WP_Search\Tests;

use Brain\Monkey\Functions;
use WP_Search\Settings_Indexer;

/**
 * Tests for Settings_Indexer.
 */
class Settings_Indexer_Tests extends Test_Case {

	/**
	 * In-memory transient store.
	 *
	 * @var array<mixed>
	 */
	private static $transients = array();

	/**
	 * Set up generic function stubs before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		self::$transients = array();

		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'is_user_logged_in' )->justReturn( true );
		Functions\when( 'admin_url' )->justReturn( 'https://example.com/wp-admin/' );
		Functions\when( 'wp_strip_all_tags' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( '__' )->returnArg();
		Functions\when( 'get_option' )->justReturn( '' );
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
	 * Source constant must be settings.
	 *
	 * @return void
	 */
	public function test_source_constant(): void {
		$this->assertSame( 'settings', Settings_Indexer::SOURCE );
	}

	/**
	 * Reindex should build the index and return the record count.
	 *
	 * @return void
	 */
	public function test_reindex_counts_records(): void {
		$indexer = new Settings_Indexer();

		$count = $indexer->reindex();
		$this->assertGreaterThan( 0, $count );
		$this->assertSame( $count, count( get_transient( Settings_Indexer::INDEX_TRANSIENT_KEY ) ) );
	}

	/**
	 * Two consecutive cold-cache rebuilds produce the same records.
	 *
	 * @return void
	 */
	public function test_reindex_is_deterministic(): void {
		$indexer = new Settings_Indexer();

		self::$transients = array();
		$first  = $indexer->get_index();
		self::$transients = array();
		$second = $indexer->get_index();

		$this->assertSame( $first, $second );
	}

	/**
	 * Empty query should return every indexed record.
	 *
	 * @return void
	 */
	public function test_search_empty_query_returns_all(): void {
		$indexer = new Settings_Indexer();
		$indexer->reindex();

		$results = $indexer->search( '' );
		$this->assertIsArray( $results );
		$this->assertNotEmpty( $results );
		$this->assertSame( 'settings', $results[0]['source'] );
	}

	/**
	 * Searching should match records by field id/label.
	 *
	 * @return void
	 */
	public function test_search_matches_title(): void {
		$indexer = new Settings_Indexer();
		$indexer->reindex();

		$results = $indexer->search( 'General' );
		$this->assertNotEmpty( $results );
	}

	/**
	 * Searching should match records by keywords.
	 *
	 * @return void
	 */
	public function test_search_matches_keywords(): void {
		$indexer = new Settings_Indexer();
		$indexer->reindex();

		$results = $indexer->search( 'blogname' );
		$this->assertNotEmpty( $results );
	}

	/**
	 * No-match query should return an empty array.
	 *
	 * @return void
	 */
	public function test_search_returns_empty_for_no_match(): void {
		$indexer = new Settings_Indexer();
		$indexer->reindex();

		$results = $indexer->search( 'xyz-123-nope' );
		$this->assertIsArray( $results );
		$this->assertEmpty( $results );
	}

	/**
	 * get_index should rebuild the index when the transient is empty.
	 *
	 * @return void
	 */
	public function test_get_index_rebuilds_when_empty(): void {
		$indexer = new Settings_Indexer();

		$index = $indexer->get_index();
		$this->assertIsArray( $index );
		$this->assertNotEmpty( $index );
	}

	/**
	 * get_records should expose the cached index as spotlight records.
	 *
	 * @return void
	 */
	public function test_get_records_returns_spotlight_records(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		$indexer = new Settings_Indexer();
		$indexer->reindex();

		$records = $indexer->get_records();
		$this->assertIsArray( $records );
		$this->assertNotEmpty( $records );

		foreach ( $records as $record ) {
			$this->assertSame( 'settings', $record['facet'] );
			$this->assertArrayHasKey( 'search', $record );
			$this->assertArrayHasKey( 'display', $record );
			$this->assertArrayHasKey( 'url', $record['display'] );
			$this->assertNotEmpty( $record['display']['url'] );
		}
	}

	/**
	 * Every settings record must carry the locked W2 display shape.
	 *
	 * @return void
	 */
	public function test_records_have_locked_display_shape(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		$indexer = new Settings_Indexer();
		$indexer->reindex();

		$records = $indexer->get_records();
		$this->assertNotEmpty( $records );

		foreach ( $records as $record ) {
			$d = $record['display'];
			$this->assertNotEmpty( $d['snippet'], 'Snippet must not be empty.' );
			$this->assertContains( $d['language'], array( 'html', 'php', 'css' ) );
			$this->assertContains( $d['sourceKind'], array( 'core', 'plugin' ) );
			$this->assertIsArray( $d['breadcrumb'] );
			$this->assertGreaterThanOrEqual( 2, count( $d['breadcrumb'] ) );
			$this->assertNotEmpty( $d['url'] );
		}
	}

	/**
	 * All six core options pages appear in the settings facet.
	 *
	 * @return void
	 */
	public function test_core_settings_pages_covered(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		$indexer = new Settings_Indexer();
		$indexer->reindex();

		$records   = $indexer->get_records();
		$seen      = array();
		$want      = array( 'General', 'Writing', 'Reading', 'Discussion', 'Media', 'Permalinks' );
		$want_keys = array_flip( $want );

		foreach ( $records as $record ) {
			$last = end( $record['display']['breadcrumb'] );
			if ( isset( $want_keys[ $last ] ) ) {
				$seen[ $last ] = true;
			}
		}

		foreach ( $want as $page ) {
			$this->assertArrayHasKey( $page, $seen, 'Missing core settings page: ' . $page );
		}
	}

	/**
	 * Searching for "blogname" returns Settings > General first.
	 *
	 * @return void
	 */
	public function test_blogname_query_returns_settings_general(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		$indexer = new Settings_Indexer();
		$indexer->reindex();

		$records = $indexer->get_records();
		$match   = null;
		foreach ( $records as $record ) {
			if ( in_array( 'blogname', $record['search']['terms'], true ) ) {
				$match = $record;
				break;
			}
		}

		$this->assertNotNull( $match );
		$this->assertSame( array( 'Settings', 'General' ), $match['display']['breadcrumb'] );
	}
}
