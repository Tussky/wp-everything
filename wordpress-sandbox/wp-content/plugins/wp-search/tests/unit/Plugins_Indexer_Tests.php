<?php
/**
 * Plugins Indexer unit tests.
 *
 * @package WP_Search
 */

namespace WP_Search\Tests;

use Brain\Monkey\Functions;
use WP_Search\Plugins_Indexer;

/**
 * Tests for Plugins_Indexer.
 */
class Plugins_Indexer_Tests extends Test_Case {

	/**
	 * Common setup.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'wp_strip_all_tags' )->returnArg();
		Functions\when( 'admin_url' )->alias(
			function ( $path = '' ) {
				return 'https://example.com/wp-admin/' . ltrim( (string) $path, '/' );
			}
		);
		Functions\when( 'is_plugin_active' )->justReturn( true );
		Functions\when( 'get_plugins' )->justReturn( array() );
		Functions\when( 'get_site_transient' )->justReturn( false );
	}

	/**
	 * Source label must be plugins.
	 *
	 * @return void
	 */
	public function test_source_constant(): void {
		$this->assertSame( 'plugins', Plugins_Indexer::SOURCE );
	}

	/**
	 * Missing permission should yield no results.
	 *
	 * @return void
	 */
	public function test_search_requires_activate_plugins(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$indexer = new Plugins_Indexer();
		$this->assertEmpty( $indexer->search( 'hello' ) );
	}

	/**
	 * Empty query should yield no results.
	 *
	 * @return void
	 */
	public function test_search_returns_empty_for_empty_query(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		$indexer = new Plugins_Indexer();
		$this->assertEmpty( $indexer->search( '' ) );
	}

	/**
	 * Match by plugin name.
	 *
	 * @return void
	 */
	public function test_search_matches_name(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_plugins' )->justReturn(
			array(
				'hello/hello.php' => array(
					'Name'        => 'Hello Dolly',
					'Description' => 'A classic WordPress plugin.',
					'Author'      => 'Matt Mullenweg',
				),
			)
		);

		$indexer = new Plugins_Indexer();
		$results = $indexer->search( 'dolly' );

		$this->assertCount( 1, $results );
		$this->assertSame( 'plugins', $results[0]['source'] );
		$this->assertSame( 'Hello Dolly', $results[0]['title'] );
		$this->assertSame( 'active', $results[0]['status'] );
	}

	/**
	 * Match by plugin description.
	 *
	 * @return void
	 */
	public function test_search_matches_description(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_plugins' )->justReturn(
			array(
				'akismet/akismet.php' => array(
					'Name'        => 'Akismet',
					'Description' => 'Protect your site from spam.',
					'Author'      => 'Automattic',
				),
			)
		);

		$indexer = new Plugins_Indexer();
		$results = $indexer->search( 'spam' );

		$this->assertCount( 1, $results );
		$this->assertSame( 'Akismet', $results[0]['title'] );
	}

	/**
	 * Match by plugin author.
	 *
	 * @return void
	 */
	public function test_search_matches_author(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_plugins' )->justReturn(
			array(
				'jetpack/jetpack.php' => array(
					'Name'        => 'Jetpack',
					'Description' => 'Security, performance, and growth tools.',
					'Author'      => 'Automattic',
				),
			)
		);

		$indexer = new Plugins_Indexer();
		$results = $indexer->search( 'automattic' );

		$this->assertCount( 1, $results );
		$this->assertSame( 'Jetpack', $results[0]['title'] );
	}

	/**
	 * Result count should respect the limit.
	 *
	 * @return void
	 */
	public function test_search_respects_results_limit(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		$plugins = array();
		for ( $i = 1; $i <= 25; $i++ ) {
			$plugins[ "plugin-{$i}/plugin-{$i}.php" ] = array(
				'Name'        => "Plugin {$i}",
				'Description' => '',
				'Author'      => '',
			);
		}
		Functions\when( 'get_plugins' )->justReturn( $plugins );

		$indexer = new Plugins_Indexer();
		$results = $indexer->search( 'plugin' );

		$this->assertSame( Plugins_Indexer::RESULTS_LIMIT, count( $results ) );
		$this->assertSame( 20, count( $results ) );
	}

	/**
	 * get_records should expose every plugin as a spotlight record.
	 *
	 * @return void
	 */
	public function test_get_records_returns_spotlight_records(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_plugins' )->justReturn(
			array(
				'hello/hello.php' => array(
					'Name'        => 'Hello Dolly',
					'Description' => 'A classic WordPress plugin.',
					'Author'      => 'Matt Mullenweg',
					'Version'     => '1.7.2',
				),
			)
		);
		Functions\when( 'is_plugin_active' )->justReturn( true );

		$indexer = new Plugins_Indexer();
		$records = $indexer->get_records();

		$this->assertCount( 1, $records );
		$record = $records[0];

		$this->assertStringStartsWith( 'p-', $record['id'] );
		$this->assertSame( 'plugins', $record['facet'] );
		$this->assertArrayHasKey( 'search', $record );
		$this->assertArrayHasKey( 'display', $record );
		$this->assertSame( 'Hello Dolly', $record['display']['name'] );
		$this->assertTrue( $record['display']['active'] );
		$this->assertArrayHasKey( 'url', $record['display'] );
		$this->assertNotEmpty( $record['display']['url'] );
		$this->assertStringEndsWith( 'plugins.php?s=hello', $record['display']['url'] );
	}

	/**
	 * Plugin URLs carry the plugin slug so each result points to a distinct
	 * plugins.php search. Both directory/file.php and standalone.php file
	 * shapes must resolve to a slug.
	 *
	 * @return void
	 */
	public function test_plugin_urls_are_distinct_per_file_shape(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_plugins' )->justReturn(
			array(
				'hello/hello.php'       => array(
					'Name'        => 'Hello Dolly',
					'Description' => '',
					'Author'      => '',
					'Version'     => '1.0',
				),
				'akismet/akismet.php'     => array(
					'Name'        => 'Akismet',
					'Description' => '',
					'Author'      => '',
					'Version'     => '1.0',
				),
				'standalone/standalone.php' => array(
					'Name'        => 'Standalone',
					'Description' => '',
					'Author'      => '',
					'Version'     => '1.0',
				),
				'single-file.php'         => array(
					'Name'        => 'Single File',
					'Description' => '',
					'Author'      => '',
					'Version'     => '1.0',
				),
			)
		);
		Functions\when( 'is_plugin_active' )->alias(
			function ( $plugin_file ) {
				return 'akismet/akismet.php' !== $plugin_file;
			}
		);

		$indexer = new Plugins_Indexer();
		$records = $indexer->get_records();

		$this->assertCount( 4, $records );

		$expected = array(
			'hello/hello.php'             => 'plugins.php?s=hello',
			'akismet/akismet.php'         => 'plugins.php?s=akismet',
			'standalone/standalone.php'   => 'plugins.php?s=standalone',
			'single-file.php'             => 'plugins.php?s=single-file',
		);
		$urls     = array();

		foreach ( $records as $record ) {
			$slug = $record['display']['slug'];
			$this->assertStringEndsWith( $expected[ $slug ], $record['display']['url'] );
			$urls[] = $record['display']['url'];
		}

		$this->assertSame( array_unique( $urls ), $urls, 'Plugin URLs must be distinct.' );
	}

	/**
	 * Inactive plugins are reachable from the same deep-link shape as active ones.
	 *
	 * @return void
	 */
	public function test_inactive_plugin_has_deep_link_url(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_plugins' )->justReturn(
			array(
				'dormant/dormant.php' => array(
					'Name'        => 'Dormant',
					'Description' => '',
					'Author'      => '',
					'Version'     => '1.0',
				),
			)
		);
		Functions\when( 'is_plugin_active' )->justReturn( false );

		$indexer = new Plugins_Indexer();
		$records = $indexer->get_records();

		$this->assertCount( 1, $records );
		$this->assertFalse( $records[0]['display']['active'] );
		$this->assertStringEndsWith( 'plugins.php?s=dormant', $records[0]['display']['url'] );
	}
}
