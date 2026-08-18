<?php
/**
 * Options Indexer unit tests.
 *
 * @package WP_Search
 */

namespace WP_Search\Tests;

use Brain\Monkey\Functions;
use WP_Search\Options_Indexer;
use WP_Search\Spotlight;

/**
 * Tests for Options_Indexer.
 */
class Options_Indexer_Tests extends Test_Case {

	/**
	 * Fake rows returned by $wpdb->get_results.
	 *
	 * @var array<object>
	 */
	private static $fake_rows = array();

	/**
	 * Set up stubs before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'wp_strip_all_tags' )->returnArg();
		Functions\when( 'is_serialized_string' )->justReturn( false );
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'admin_url' )->alias(
			function ( $path = '' ) {
				return 'https://example.com/wp-admin/' . ltrim( (string) $path, '/' );
			}
		);

		self::$fake_rows = array();

		$GLOBALS['wpdb'] = \Mockery::mock( '\wpdb' );
		$GLOBALS['wpdb']->options = 'wp_options';
		$GLOBALS['wpdb']->shouldReceive( 'get_results' )->andReturnUsing(
			function () {
				return self::$fake_rows;
			}
		);
		$GLOBALS['wpdb']->shouldReceive( 'prepare' )->andReturn( 'SELECT option_name, option_value, autoload FROM wp_options WHERE option_name IN (?,?,?,?,?,?,?,?,?,?,?,?,?)' );
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	/**
	 * Source label must be options.
	 *
	 * @return void
	 */
	public function test_source_constant(): void {
		$this->assertSame( 'options', Options_Indexer::SOURCE );
	}

	/**
	 * Missing permission should yield no records.
	 *
	 * @return void
	 */
	public function test_get_records_requires_manage_options(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$indexer = new Options_Indexer();
		$this->assertEmpty( $indexer->get_records() );
	}

	/**
	 * Empty database results should yield empty array.
	 *
	 * @return void
	 */
	public function test_get_records_returns_empty_with_no_rows(): void {
		self::$fake_rows = array();

		$indexer = new Options_Indexer();
		$this->assertEmpty( $indexer->get_records() );
	}

	/**
	 * A simple unprotected option should produce a full spotlight record.
	 *
	 * @return void
	 */
	public function test_get_records_returns_unprotected_option(): void {
		self::$fake_rows = array(
			(object) array(
				'option_name'  => 'blogname',
				'option_value' => 'Northwind Goods',
				'autoload'     => 'yes',
			),
		);

		$indexer = new Options_Indexer();
		$records = $indexer->get_records();

		$this->assertCount( 1, $records );
		$record = $records[0];

		$this->assertSame( 'o-1', $record['id'] );
		$this->assertSame( 'options', $record['facet'] );
		$this->assertSame( 90, $record['search']['weight'] );
		$this->assertContains( 'blogname', $record['search']['terms'] );
		$this->assertContains( 'Northwind Goods', $record['search']['terms'] );
		$this->assertContains( 'The', $record['search']['terms'] );
		$this->assertContains( 'site', $record['search']['terms'] );
		$this->assertSame( 'blogname', $record['display']['name'] );
		$this->assertSame( 'Northwind Goods', $record['display']['value'] );
		$this->assertSame( 'yes', $record['display']['autoload'] );
		$this->assertFalse( $record['display']['protected'] );
		$this->assertStringContainsString( 'options-general.php', $record['display']['url'] );
	}

	/**
	 * A protected option should mask the value and exclude it from terms.
	 *
	 * @return void
	 */
	public function test_get_records_returns_protected_option(): void {
		self::$fake_rows = array(
			(object) array(
				'option_name'  => 'akismet_api_key',
				'option_value' => 'abc123secret',
				'autoload'     => 'yes',
			),
		);

		$indexer = new Options_Indexer();
		$records = $indexer->get_records();

		$this->assertCount( 1, $records );
		$record = $records[0];

		$this->assertNull( $record['display']['value'] );
		$this->assertTrue( $record['display']['protected'] );
		$this->assertNotContains( 'abc123secret', $record['search']['terms'] );
		$this->assertContains( 'akismet_api_key', $record['search']['terms'] );
	}

	/**
	 * A serialized value should be excluded from search terms.
	 *
	 * @return void
	 */
	public function test_get_records_excludes_serialized_value_from_terms(): void {
		Functions\when( 'is_serialized_string' )->justReturn( true );

		self::$fake_rows = array(
			(object) array(
				'option_name'  => 'active_plugins',
				'option_value' => 'a:2:{i:0;s:27:"woocommerce/woocommerce.php";i:1;s:19:"akismet/akismet.php";}',
				'autoload'     => 'yes',
			),
		);

		$indexer = new Options_Indexer();
		$records = $indexer->get_records();

		$this->assertCount( 1, $records );
		$record = $records[0];

		$this->assertNotContains( 'a:2:', $record['search']['terms'] );
		$this->assertContains( 'active_plugins', $record['search']['terms'] );
	}

	/**
	 * A value longer than 500 characters should be excluded from search terms.
	 *
	 * @return void
	 */
	public function test_get_records_excludes_long_value_from_terms(): void {
		self::$fake_rows = array(
			(object) array(
				'option_name'  => 'long_option',
				'option_value' => str_repeat( 'x', 501 ),
				'autoload'     => 'yes',
			),
		);

		$indexer = new Options_Indexer();
		$records = $indexer->get_records();

		$this->assertCount( 1, $records );
		$this->assertNotContains( str_repeat( 'x', 501 ), $records[0]['search']['terms'] );
	}

	/**
	 * MariaDB ON/OFF autoload values should be normalized to yes/no.
	 *
	 * @return void
	 */
	public function test_get_records_normalizes_autoload_on_off(): void {
		self::$fake_rows = array(
			(object) array(
				'option_name'  => 'siteurl',
				'option_value' => 'https://example.com',
				'autoload'     => 'on',
			),
		);

		$indexer = new Options_Indexer();
		$records = $indexer->get_records();

		$this->assertSame( 'yes', $records[0]['display']['autoload'] );
	}

	/**
	 * search() returns empty array — matching is done by Spotlight engine.
	 *
	 * @return void
	 */
	public function test_search_returns_empty(): void {
		$indexer = new Options_Indexer();
		$this->assertEmpty( $indexer->search( 'anything' ) );
	}

	/**
	 * get_source returns the source string.
	 *
	 * @return void
	 */
	public function test_get_source(): void {
		$indexer = new Options_Indexer();
		$this->assertSame( 'options', $indexer->get_source() );
	}

	/**
	 * reindex returns 0 — options are live-read.
	 *
	 * @return void
	 */
	public function test_reindex_returns_zero(): void {
		$indexer = new Options_Indexer();
		$this->assertSame( 0, $indexer->reindex() );
	}

	/**
	 * Mapped options route to their dedicated admin screen.
	 *
	 * @return void
	 */
	public function test_mapped_option_routes_to_dedicated_screen(): void {
		self::$fake_rows = array(
			(object) array(
				'option_name'  => 'permalink_structure',
				'option_value' => '/%postname%/',
				'autoload'     => 'yes',
			),
		);

		$indexer = new Options_Indexer();
		$records = $indexer->get_records();

		$this->assertStringEndsWith( 'options-permalink.php', $records[0]['display']['url'] );
	}

	/**
	 * Unmapped options fall back to the general options screen.
	 *
	 * @return void
	 */
	public function test_unmapped_option_falls_back_to_options_general(): void {
		self::$fake_rows = array(
			(object) array(
				'option_name'  => 'some_custom_option',
				'option_value' => 'value',
				'autoload'     => 'yes',
			),
		);

		$indexer = new Options_Indexer();
		$records = $indexer->get_records();

		$this->assertStringEndsWith( 'options-general.php', $records[0]['display']['url'] );
	}

	/**
	 * Mapped options may share the same destination screen intentionally.
	 *
	 * @return void
	 */
	public function test_mapped_options_can_share_destination(): void {
		self::$fake_rows = array(
			(object) array(
				'option_name'  => 'siteurl',
				'option_value' => 'https://example.com',
				'autoload'     => 'yes',
			),
			(object) array(
				'option_name'  => 'blogname',
				'option_value' => 'Example Site',
				'autoload'     => 'yes',
			),
		);

		$indexer = new Options_Indexer();
		$records = $indexer->get_records();

		$this->assertCount( 2, $records );
		$urls = array_column( array_column( $records, 'display' ), 'url' );
		$this->assertSame( $urls[0], $urls[1], 'Mapped options may intentionally share a destination.' );
		foreach ( $urls as $url ) {
			$this->assertStringEndsWith( 'options-general.php', $url );
		}
	}

	/**
	 * Searching for "permalink" via the Spotlight engine should produce
	 * records with a non-empty title (display.name), a non-empty url
	 * (display.url → deep link), and source === options (facet).
	 *
	 * This mirrors the REST controller data path: get_records() →
	 * Spotlight::build_response() → flatten.
	 *
	 * @return void
	 */
	public function test_permalink_search_records_have_title_url_and_source(): void {
		self::$fake_rows = array(
			(object) array(
				'option_name'  => 'permalink_structure',
				'option_value' => '/%postname%/',
				'autoload'     => 'yes',
			),
			(object) array(
				'option_name'  => 'blogname',
				'option_value' => 'My Site',
				'autoload'     => 'yes',
			),
		);

		$indexer = new Options_Indexer();
		$records = $indexer->get_records();

		$response = Spotlight::build_response( $records, 'permalink' );

		$this->assertArrayHasKey( 'options', $response['facets'] );
		$this->assertNotEmpty( $response['facets']['options'] );

		foreach ( $response['facets']['options'] as $record ) {
			$this->assertSame( 'options', $record['facet'] );
			$this->assertNotEmpty( $record['display']['name'] );
			$this->assertNotEmpty( $record['display']['url'] );
			$this->assertStringContainsString( 'options-permalink.php', $record['display']['url'] );
		}
	}
}