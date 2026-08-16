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
	 * Helper to populate admin menu globals for indexing.
	 *
	 * @return void
	 */
	private function seed_menu_globals(): void {
		global $menu, $submenu, $wp_settings_sections, $wp_registered_settings;

		$menu = array(
			array( 'Settings', 'manage_options', 'options-general.php', '', 'menu-top', 'menu-settings', 'dashicons-admin-settings' ),
		);
		$submenu = array(
			'options-general.php' => array(
				array( 'General', 'manage_options', 'options-general.php' ),
				array( 'Writing', 'manage_options', 'options-writing.php' ),
			),
		);

		$wp_settings_sections = array(
			'options-general.php' => array(
				'default' => array(
					'id'          => 'default',
					'title'       => 'General Settings',
					'description' => 'Basic site options.',
				),
			),
		);

		$wp_registered_settings = array(
			'blogname' => array(
				'type'  => 'string',
				'group' => 'default',
				'args'  => array( 'label' => 'Site Title' ),
			),
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
	 * Empty query should return every indexed record.
	 *
	 * @return void
	 */
	public function test_search_empty_query_returns_all(): void {
		$indexer = new Settings_Indexer();
		$this->seed_menu_globals();
		$indexer->reindex();

		$results = $indexer->search( '' );
		$this->assertIsArray( $results );
		$this->assertNotEmpty( $results );
		$this->assertSame( 'settings', $results[0]['source'] );
	}

	/**
	 * Searching should match records by title.
	 *
	 * @return void
	 */
	public function test_search_matches_title(): void {
		$indexer = new Settings_Indexer();
		$this->seed_menu_globals();
		$indexer->reindex();

		$results = $indexer->search( 'General' );
		$this->assertNotEmpty( $results );
		$types = array_column( $results, 'type' );
		$this->assertContains( 'section', $types );
	}

	/**
	 * Searching should match records by keywords.
	 *
	 * @return void
	 */
	public function test_search_matches_keywords(): void {
		$indexer = new Settings_Indexer();
		$this->seed_menu_globals();
		$indexer->reindex();

		$results = $indexer->search( 'default' );
		$this->assertNotEmpty( $results );
	}

	/**
	 * No-match query should return an empty array.
	 *
	 * @return void
	 */
	public function test_search_returns_empty_for_no_match(): void {
		$indexer = new Settings_Indexer();
		$this->seed_menu_globals();
		$indexer->reindex();

		$results = $indexer->search( 'xyz-123-nope' );
		$this->assertIsArray( $results );
		$this->assertEmpty( $results );
	}

	/**
	 * Reindex should build the index and return the record count.
	 *
	 * @return void
	 */
	public function test_reindex_counts_records(): void {
		$indexer = new Settings_Indexer();
		$this->seed_menu_globals();

		$count = $indexer->reindex();
		$this->assertGreaterThan( 0, $count );
		$this->assertSame( $count, count( get_transient( Settings_Indexer::INDEX_TRANSIENT_KEY ) ) );
	}

	/**
	 * get_index should rebuild the index when the transient is empty.
	 *
	 * @return void
	 */
	public function test_get_index_rebuilds_when_empty(): void {
		$indexer = new Settings_Indexer();
		$this->seed_menu_globals();

		$index = $indexer->get_index();
		$this->assertIsArray( $index );
		$this->assertNotEmpty( $index );
	}
}
