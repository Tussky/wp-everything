<?php
/**
 * Cross-facet URL contract unit tests.
 *
 * Locks in §3.3 binding requirements that span more than one facet:
 *   - T5.2: users + plugins + settings URLs are unique when combined
 *            (options are exempt because many options intentionally share
 *            a destination screen).
 *   - T5.3: every spotlight URL is same-origin admin.
 *
 * @package WP_Search
 */

namespace WP_Search\Tests;

use Brain\Monkey\Functions;
use Mockery;
use WP_Search\Plugins_Indexer;
use WP_Search\Settings_Indexer;
use WP_Search\Users_Indexer;
use WP_Search\Options_Indexer;

/**
 * Tests for cross-facet URL contracts.
 */
class Cross_Facet_URL_Tests extends Test_Case {

	/**
	 * Shared in-memory transient store for deterministic settings indexing.
	 *
	 * @var array<mixed>
	 */
	private static $transients = array();

	/**
	 * Set up function stubs and shared state before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		self::$transients = array();

		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'wp_strip_all_tags' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( '__' )->returnArg();

		// Use a non-default origin so the test proves URLs come from admin_url(),
		// not a hard-coded example.com string in the indexer source.
		Functions\when( 'admin_url' )->alias(
			function ( $path = '' ) {
				return 'https://spotlight.test/wp-admin/' . ltrim( (string) $path, '/' );
			}
		);

		// Users indexer helpers.
		Functions\when( 'get_avatar_url' )->justReturn( 'https://spotlight.test/avatar.jpg' );
		Functions\when( 'get_user_meta' )->justReturn( '' );
		Functions\when( 'translate_user_role' )->returnArg();
		$roles_stub = Mockery::mock( 'WP_Roles' );
		$roles_stub->shouldReceive( 'get_names' )->andReturn( array() );
		Functions\when( 'wp_roles' )->justReturn( $roles_stub );

		// Plugins indexer helpers.
		Functions\when( 'is_plugin_active' )->justReturn( true );
		Functions\when( 'get_site_transient' )->justReturn( false );
		Functions\when( 'is_serialized_string' )->justReturn( false );

		// Settings indexer helpers.
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'is_user_logged_in' )->justReturn( true );
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
	 * Clean up globals between tests.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	/**
	 * Build user records that exercise the same seam the per-facet suite uses.
	 *
	 * @return array<mixed>
	 */
	private function users_records(): array {
		$user_one = $this->make_user(
			array(
				'ID'              => 1,
				'display_name'    => 'Admin User',
				'user_login'      => 'admin',
				'user_email'      => 'admin@spotlight.test',
				'user_registered' => '2021-03-14 12:00:00',
				'roles'           => array( 'administrator' ),
				'allcaps'         => array( 'manage_options' => true ),
			)
		);
		$user_two = $this->make_user(
			array(
				'ID'              => 2,
				'display_name'    => 'Jane Doe',
				'user_login'      => 'jane',
				'user_email'      => 'jane@spotlight.test',
				'user_registered' => '2022-06-02 12:00:00',
				'roles'           => array( 'editor' ),
				'allcaps'         => array( 'edit_posts' => true ),
			)
		);

		$query = Mockery::mock( 'overload:WP_User_Query' );
		$query->shouldReceive( 'get_results' )->andReturn( array( $user_one, $user_two ) );

		$indexer = new Users_Indexer();
		return $indexer->get_records();
	}

	/**
	 * Build plugin records covering both directory/file.php and single-file shapes.
	 *
	 * @return array<mixed>
	 */
	private function plugins_records(): array {
		Functions\when( 'get_plugins' )->justReturn(
			array(
				'hello/hello.php'     => array(
					'Name'        => 'Hello Dolly',
					'Description' => '',
					'Author'      => '',
					'Version'     => '1.0',
				),
				'akismet/akismet.php' => array(
					'Name'        => 'Akismet',
					'Description' => '',
					'Author'      => '',
					'Version'     => '1.0',
				),
				'single-file.php'     => array(
					'Name'        => 'Single File',
					'Description' => '',
					'Author'      => '',
					'Version'     => '1.0',
				),
			)
		);

		$indexer = new Plugins_Indexer();
		return $indexer->get_records();
	}

	/**
	 * Build settings records from the deterministic core index.
	 *
	 * @return array<mixed>
	 */
	private function settings_records(): array {
		$indexer = new Settings_Indexer();
		$indexer->reindex();
		return $indexer->get_records();
	}

	/**
	 * Build option records for the same-origin admin assertion.
	 *
	 * @return array<mixed>
	 */
	private function options_records(): array {
		global $wpdb;
		$wpdb = Mockery::mock( '\wpdb' );
		$wpdb->options = 'wp_options';
		$wpdb->shouldReceive( 'get_results' )->andReturnUsing(
			function () {
				return array(
					(object) array(
						'option_name'  => 'blogname',
						'option_value' => 'Spotlight Test',
						'autoload'     => 'yes',
					),
					(object) array(
						'option_name'  => 'permalink_structure',
						'option_value' => '/%postname%/',
						'autoload'     => 'yes',
					),
				);
			}
		);
		$wpdb->shouldReceive( 'prepare' )->andReturn( 'SELECT option_name, option_value, autoload FROM wp_options WHERE option_name IN (?, ?)' );

		$indexer = new Options_Indexer();
		$records = $indexer->get_records();
		unset( $GLOBALS['wpdb'] );
		return $records;
	}

	/**
	 * Create a fake WP_User object.
	 *
	 * @param array<mixed> $props User properties.
	 * @return \WP_User
	 */
	private function make_user( array $props = array() ): \WP_User {
		$props = array_merge(
			array(
				'ID'              => 1,
				'display_name'    => 'Admin User',
				'user_login'      => 'admin',
				'user_email'      => 'admin@spotlight.test',
			),
			$props
		);

		$user = Mockery::mock( 'WP_User' );
		foreach ( $props as $key => $value ) {
			$user->{$key} = $value;
		}
		return $user;
	}

	/**
	 * T5.2 — URLs from users, plugins and settings are unique when pooled.
	 *
	 * Options are intentionally exempt: several options legitimately route to the
	 * same admin screen when no field-level anchor exists.
	 *
	 * @return void
	 */
	public function test_cross_facet_uniqueness_exempting_options(): void {
		$records = array_merge(
			$this->users_records(),
			$this->plugins_records(),
			$this->settings_records()
		);

		$urls = array();
		foreach ( $records as $record ) {
			$this->assertArrayHasKey( 'display', $record );
			$this->assertArrayHasKey( 'url', $record['display'] );
			$this->assertNotEmpty( $record['display']['url'] );
			$urls[] = $record['display']['url'];
		}

		$this->assertGreaterThan( 1, count( $urls ), 'Need multiple cross-facet URLs to test uniqueness.' );
		$this->assertSame(
			array_unique( $urls ),
			$urls,
			'Combined users+plugins+settings URLs must be unique; options are exempt.'
		);
	}

	/**
	 * T5.3 — Every spotlight URL is produced by admin_url() and stays on the same
	 * WordPress admin origin.
	 *
	 * @return void
	 */
	public function test_all_spotlight_urls_are_same_origin_admin(): void {
		$records = array_merge(
			$this->users_records(),
			$this->plugins_records(),
			$this->settings_records(),
			$this->options_records()
		);

		$this->assertNotEmpty( $records, 'Need at least one record to verify same-origin admin.' );

		$origin   = 'https://spotlight.test';
		$admin_path = '/wp-admin/';
		foreach ( $records as $record ) {
			$url = $record['display']['url'];
			$this->assertStringStartsWith( $origin, $url, 'URL must be same-origin.' );
			$this->assertStringContainsString( $admin_path, $url, 'URL must point to wp-admin.' );
		}
	}
}
