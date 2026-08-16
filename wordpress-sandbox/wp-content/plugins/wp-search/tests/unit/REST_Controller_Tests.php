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
	 * The search route should be registered with the expected args.
	 *
	 * @return void
	 */
	public function test_register_routes(): void {
		$registered = null;

		Functions\when( 'register_rest_route' )->alias(
			function ( $namespace, $route, $args ) use ( &$registered ) {
				$registered = compact( 'namespace', 'route', 'args' );
			}
		);

		$controller = new REST_Controller();
		$controller->register_routes();

		$this->assertNotNull( $registered );
		$this->assertSame( REST_Controller::NAMESPACE, $registered['namespace'] );
		$this->assertSame( REST_Controller::ROUTE, $registered['route'] );
		$this->assertArrayHasKey( 'methods', $registered['args'] );
		$this->assertArrayHasKey( 'callback', $registered['args'] );
		$this->assertArrayHasKey( 'permission_callback', $registered['args'] );
		$this->assertArrayHasKey( 'args', $registered['args'] );
		$this->assertArrayHasKey( 'q', $registered['args']['args'] );
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
}
