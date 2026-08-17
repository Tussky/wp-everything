<?php
/**
 * REST Controller unit tests.
 *
 * @package WP_Search
 */

namespace WP_Search\Tests;

use Brain\Monkey\Functions;
use Mockery;
use WP_Search\REST_Controller;
use WP_Search\Spotlight_Provider;

/**
 * Tests for REST_Controller.
 */
class REST_Controller_Tests extends Test_Case {

	/**
	 * Helper to create a controller with all indexers stubbed.
	 *
	 * @param array<mixed> $stubs Facet-keyed arrays of spotlight records.
	 * @return REST_Controller
	 */
	private function controller_with_stubbed_indexers( array $stubs = array() ): REST_Controller {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'rest_ensure_response' )->alias(
			function ( $data ) {
				return $data;
			}
		);

		$controller = Mockery::mock( REST_Controller::class )->makePartial();
		$controller->shouldAllowMockingProtectedMethods();

		$indexers = array();
		foreach ( $stubs as $facet => $records ) {
			$indexer = Mockery::mock( Spotlight_Provider::class );
			$indexer->shouldReceive( 'get_records' )->andReturn( $records );
			$indexers[] = $indexer;
		}

		$controller->shouldReceive( 'get_indexers' )->andReturn( $indexers );
		return $controller;
	}

	/**
	 * The search and spotlight routes should be registered with the expected args.
	 *
	 * @return void
	 */
	public function test_register_routes(): void {
		$registered = array();

		Functions\when( 'register_rest_route' )->alias(
			function ( $namespace, $route, $args ) use ( &$registered ) {
				$registered[ $route ] = compact( 'namespace', 'route', 'args' );
			}
		);

		$controller = new REST_Controller();
		$controller->register_routes();

		$this->assertCount( 2, $registered );

		foreach ( array( REST_Controller::ROUTE, REST_Controller::SPOTLIGHT_ROUTE ) as $route ) {
			$this->assertArrayHasKey( $route, $registered );
			$this->assertSame( REST_Controller::NAMESPACE, $registered[ $route ]['namespace'] );
			$this->assertSame( $route, $registered[ $route ]['route'] );
			$this->assertArrayHasKey( 'methods', $registered[ $route ]['args'] );
			$this->assertArrayHasKey( 'callback', $registered[ $route ]['args'] );
			$this->assertArrayHasKey( 'permission_callback', $registered[ $route ]['args'] );
			$this->assertArrayHasKey( 'args', $registered[ $route ]['args'] );
			$this->assertArrayHasKey( 'q', $registered[ $route ]['args']['args'] );
		}
	}

	/**
	 * Empty / short queries should be accepted.
	 *
	 * @return void
	 */
	public function test_validate_query_accepts_valid_query(): void {
		Functions\when( '__' )->returnArg();

		$controller = new REST_Controller();
		$request  = Mockery::mock( 'WP_REST_Request' );

		$this->assertTrue( $controller->validate_query( 'admin', $request, 'q' ) );
	}

	/**
	 * Validation should fail when the parameter is not 'q'.
	 *
	 * @return void
	 */
	public function test_validate_query_rejects_wrong_param(): void {
		Functions\when( '__' )->returnArg();

		$controller = new REST_Controller();
		$request  = Mockery::mock( 'WP_REST_Request' );
		$result   = $controller->validate_query( 'admin', $request, 'foo' );

		$this->assertInstanceOf( '\WP_Error', $result );
		$this->assertSame( 'wp_search_invalid_param', $result->get_error_code() );
	}

	/**
	 * Queries over 200 characters should be rejected.
	 *
	 * @return void
	 */
	public function test_validate_query_rejects_long_query(): void {
		Functions\when( '__' )->returnArg();

		$controller = new REST_Controller();
		$request  = Mockery::mock( 'WP_REST_Request' );
		$result   = $controller->validate_query( str_repeat( 'a', 201 ), $request, 'q' );

		$this->assertInstanceOf( '\WP_Error', $result );
		$this->assertSame( 'wp_search_query_too_long', $result->get_error_code() );
	}

	/**
	 * Administrators are allowed to search.
	 *
	 * @return void
	 */
	public function test_check_permission_allows_admin(): void {
		Functions\when( 'wp_verify_nonce' )->justReturn( true );
		Functions\when( 'current_user_can' )->justReturn( true );

		$controller = new REST_Controller();
		$this->assertTrue( $controller->check_permission() );
	}

	/**
	 * Non-admin users should receive a 403 error.
	 *
	 * @return void
	 */
	public function test_check_permission_denies_non_admin(): void {
		Functions\when( 'wp_verify_nonce' )->justReturn( true );
		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\when( '__' )->returnArg();

		$controller = new REST_Controller();
		$result = $controller->check_permission();

		$this->assertInstanceOf( '\WP_Error', $result );
		$this->assertSame( 'wp_search_forbidden', $result->get_error_code() );
		$this->assertSame( 403, $result->get_error_data()['status'] );
	}

	/**
	 * Invalid or expired nonce should return a distinct 403 error.
	 *
	 * @return void
	 */
	public function test_check_permission_denies_bad_nonce(): void {
		$_SERVER['HTTP_X_WP_NONCE'] = 'badnonce123';

		Functions\when( 'wp_verify_nonce' )->justReturn( false );
		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( '__' )->returnArg();

		$controller = new REST_Controller();
		$result = $controller->check_permission();

		unset( $_SERVER['HTTP_X_WP_NONCE'] );

		$this->assertInstanceOf( '\WP_Error', $result );
		$this->assertSame( 'wp_search_bad_nonce', $result->get_error_code() );
		$this->assertSame( 403, $result->get_error_data()['status'] );
	}

	/**
	 * Search results should be merged from all indexers.
	 *
	 * @return void
	 */
	public function test_search_items_merges_indexer_results(): void {
		$request = Mockery::mock( 'WP_REST_Request' );
		$request->shouldReceive( 'get_param' )->with( 'q' )->andReturn( 'admin' );

		$stubs = array(
			'settings' => array(
				array(
					'id'      => 's-1',
					'facet'   => 'settings',
					'search'  => array( 'terms' => array( 'admin', 'settings' ), 'weight' => 80 ),
					'display' => array( 'title' => 'Settings', 'url' => '/options-general.php' ),
				),
			),
			'users'    => array(
				array(
					'id'      => 'u-1',
					'facet'   => 'users',
					'search'  => array( 'terms' => array( 'admin', 'user' ), 'weight' => 90 ),
					'display' => array( 'name' => 'Admin User', 'url' => '/users.php' ),
				),
			),
		);

		$controller = $this->controller_with_stubbed_indexers( $stubs );
		$response   = $controller->search_items( $request );

		$this->assertSame( 'admin', $response['query'] );
		$this->assertCount( 2, $response['results'] );

		$sources = array_column( $response['results'], 'source' );
		$this->assertContains( 'settings', $sources );
		$this->assertContains( 'users', $sources );
	}

	/**
	 * Search should return an empty result set when no indexers return data.
	 *
	 * @return void
	 */
	public function test_search_items_returns_empty_when_no_matches(): void {
		$request = Mockery::mock( 'WP_REST_Request' );
		$request->shouldReceive( 'get_param' )->with( 'q' )->andReturn( 'xyz' );

		$controller = $this->controller_with_stubbed_indexers( array() );
		$response   = $controller->search_items( $request );

		$this->assertSame( 'xyz', $response['query'] );
		$this->assertCount( 0, $response['results'] );
	}

	/**
	 * The spotlight route should return the flat contract object — four facet
	 * arrays keyed as { users, plugins, options, settings }, no _meta/facets
	 * wrapper, every record carrying a non-empty url, and no passwordHash key.
	 *
	 * @return void
	 */
	public function test_spotlight_items_returns_flat_contract(): void {
		Functions\when( 'admin_url' )->alias(
			function ( $path ) {
				return 'https://example.com/wp-admin/' . $path;
			}
		);

		$request = Mockery::mock( 'WP_REST_Request' );
		$request->shouldReceive( 'get_param' )->with( 'q' )->andReturn( 'admin' );
		$request->shouldReceive( 'get_param' )->with( 'facet' )->andReturn( '' );

		$stubs = array(
			'users'    => array(
				array(
					'id'      => 'u-1',
					'facet'   => 'users',
					'search'  => array( 'terms' => array( 'admin' ), 'weight' => 100 ),
					'display' => array(
						'hue'         => 264,
						'displayName' => 'Admin',
						'username'    => 'admin',
						'role'        => 'Administrator',
						'email'       => 'admin@example.com',
						'capabilities' => array( 'manage_options' ),
						'registered'  => '2026-01-01',
						'lastLogin'   => '',
						'url'         => 'https://example.com/wp-admin/user-edit.php?user_id=1',
					),
				),
			),
			'plugins'  => array(
				array(
					'id'      => 'p-1',
					'facet'   => 'plugins',
					'search'  => array( 'terms' => array( 'admin' ), 'weight' => 90 ),
					'display' => array(
						'name'            => 'Admin',
						'slug'             => 'admin/admin.php',
						'active'          => true,
						'version'         => '1.0',
						'updateAvailable' => null,
						'author'          => 'Paperclip',
						'description'     => 'A plugin.',
						'url'             => 'https://example.com/wp-admin/plugins.php',
					),
				),
			),
			'options'  => array(
				array(
					'id'      => 'o-1',
					'facet'   => 'options',
					'search'  => array( 'terms' => array( 'admin_email' ), 'weight' => 80 ),
					'display' => array(
						'name'      => 'admin_email',
						'value'     => 'admin@example.com',
						'autoload'  => 'yes',
						'protected' => false,
						'explainer' => 'Admin notifications address.',
						'url'       => 'https://example.com/wp-admin/options-general.php',
					),
				),
			),
			'settings' => array(
				array(
					'id'      => 's-1',
					'facet'   => 'settings',
					'search'  => array( 'terms' => array( 'admin' ), 'weight' => 85 ),
					'display' => array(
						'source'     => 'WordPress Core',
						'sourceKind' => 'core',
						'breadcrumb' => array( 'Settings', 'General' ),
						'language'   => 'html',
						'snippet'    => '<label>Site Title</label>',
						'url'        => 'https://example.com/wp-admin/options-general.php',
					),
				),
			),
		);

		$controller = $this->controller_with_stubbed_indexers( $stubs );
		$response   = $controller->get_spotlight_items( $request );

		// Flat contract: exactly the four facet keys, in order, no wrappers.
		$this->assertSame( array( 'users', 'plugins', 'options', 'settings' ), array_keys( $response ) );
		$this->assertArrayNotHasKey( '_meta', $response );
		$this->assertArrayNotHasKey( 'facets', $response );

		foreach ( \WP_Search\Spotlight::FACET_ORDER as $facet ) {
			$this->assertNotEmpty( $response[ $facet ], "Facet {$facet} should not be empty" );
			foreach ( $response[ $facet ] as $record ) {
				// No nested internal wrappers leak onto the wire.
				$this->assertArrayNotHasKey( 'facet', $record );
				$this->assertArrayNotHasKey( 'search', $record );
				$this->assertArrayNotHasKey( 'display', $record );
				$this->assertArrayNotHasKey( 'passwordHash', $record );
				$this->assertArrayHasKey( 'url', $record );
				$this->assertNotEmpty( $record['url'] );
			}
		}

		// Protected option contract: value null when protected === true.
		$protected = array(
			'id'      => 'o-2',
			'facet'   => 'options',
			'search'  => array( 'terms' => array( 'secret' ), 'weight' => 70 ),
			'display' => array(
				'name'      => 'akismet_api_key',
				'value'     => null,
				'autoload'  => 'yes',
				'protected' => true,
				'explainer' => 'Akismet key.',
				'url'       => 'https://example.com/wp-admin/admin.php?page=akismet-key-config',
			),
		);
		$controller2 = $this->controller_with_stubbed_indexers( array( 'options' => array( $protected ) ) );
		$req2 = Mockery::mock( 'WP_REST_Request' );
		$req2->shouldReceive( 'get_param' )->with( 'q' )->andReturn( '' );
		$req2->shouldReceive( 'get_param' )->with( 'facet' )->andReturn( '' );
		$flat = $controller2->get_spotlight_items( $req2 );

		$this->assertNotEmpty( $flat['options'] );
		$this->assertTrue( $flat['options'][0]['protected'] );
		$this->assertNull( $flat['options'][0]['value'] );
	}
}
