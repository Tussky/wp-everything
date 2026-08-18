<?php
/**
 * Users Indexer unit tests.
 *
 * @package WP_Search
 */

namespace WP_Search\Tests;

use Brain\Monkey\Functions;
use Mockery;
use WP_Search\Users_Indexer;

/**
 * Tests for Users_Indexer.
 */
class Users_Indexer_Tests extends Test_Case {

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
				'user_email'      => 'admin@example.com',
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
	 * Common stubs used by most tests.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'admin_url' )->alias(
			function ( $path = '' ) {
				return 'https://example.com/wp-admin/' . ltrim( (string) $path, '/' );
			}
		);
		Functions\when( 'get_avatar_url' )->justReturn( 'https://example.com/avatar.jpg' );
		Functions\when( 'get_user_meta' )->justReturn( '' );
		Functions\when( 'translate_user_role' )->returnArg();
		$roles_stub = Mockery::mock( 'WP_Roles' );
		$roles_stub->shouldReceive( 'get_names' )->andReturn( array() );
		Functions\when( 'wp_roles' )->justReturn( $roles_stub );
	}

	/**
	 * Source label must be users.
	 *
	 * @return void
	 */
	public function test_source_constant(): void {
		$this->assertSame( 'users', Users_Indexer::SOURCE );
	}

	/**
	 * No permission should yield an empty result set.
	 *
	 * @return void
	 */
	public function test_search_requires_list_users(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$indexer = new Users_Indexer();
		$this->assertEmpty( $indexer->search( 'admin' ) );
	}

	/**
	 * Empty queries should return an empty result set.
	 *
	 * @return void
	 */
	public function test_search_returns_empty_for_empty_query(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		$indexer = new Users_Indexer();
		$this->assertEmpty( $indexer->search( '' ) );
		$this->assertEmpty( $indexer->search( '   ' ) );
	}

	/**
	 * A matching user should be returned with the expected shape.
	 *
	 * @return void
	 */
	public function test_search_returns_users(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		$user = $this->make_user(
			array(
				'ID'           => 7,
				'display_name' => 'Jane Doe',
				'user_login'   => 'jane',
				'user_email'   => 'jane@example.com',
			)
		);

		$query = Mockery::mock( 'overload:WP_User_Query' );
		$query->shouldReceive( 'get_results' )->andReturn( array( $user ) );

		$indexer = new Users_Indexer();
		$results = $indexer->search( 'jane' );

		$this->assertCount( 1, $results );
		$this->assertSame( 'users', $results[0]['source'] );
		$this->assertSame( 'Jane Doe', $results[0]['title'] );
		$this->assertSame( 'jane', $results[0]['user_login'] );
		$this->assertSame( 'jane@example.com', $results[0]['email'] );
		$this->assertSame( 'https://example.com/avatar.jpg', $results[0]['avatar_url'] );
	}

	/**
	 * User objects that are not WP_User instances should be skipped.
	 *
	 * @return void
	 */
	public function test_search_skips_invalid_user_objects(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		$valid   = $this->make_user();
		$invalid = new \stdClass();

		$query = Mockery::mock( 'overload:WP_User_Query' );
		$query->shouldReceive( 'get_results' )->andReturn( array( $valid, $invalid ) );

		$indexer = new Users_Indexer();
		$results = $indexer->search( 'admin' );

		$this->assertCount( 1, $results );
		$this->assertSame( 'admin', $results[0]['user_login'] );
	}

	/**
	 * get_records should expose every user as a spotlight record.
	 *
	 * @return void
	 */
	public function test_get_records_returns_spotlight_records(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		$user_one = $this->make_user(
			array(
				'ID'              => 1,
				'display_name'    => 'Admin User',
				'user_login'      => 'admin',
				'user_email'      => 'admin@example.com',
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
				'user_email'      => 'jane@example.com',
				'user_registered' => '2022-06-02 12:00:00',
				'roles'           => array( 'editor' ),
				'allcaps'         => array( 'edit_posts' => true ),
			)
		);

		$query = Mockery::mock( 'overload:WP_User_Query' );
		$query->shouldReceive( 'get_results' )->andReturn( array( $user_one, $user_two ) );

		$indexer = new Users_Indexer();
		$records = $indexer->get_records();

		$this->assertCount( 2, $records );

		$urls = array();
		foreach ( $records as $record ) {
			$this->assertSame( 'users', $record['facet'] );
			$this->assertArrayHasKey( 'search', $record );
			$this->assertArrayHasKey( 'display', $record );
			$this->assertArrayHasKey( 'url', $record['display'] );
			$this->assertNotEmpty( $record['display']['url'] );
			$this->assertStringEndsWith( 'user-edit.php?user_id=' . (int) substr( $record['id'], 2 ), $record['display']['url'] );
			$urls[] = $record['display']['url'];
		}

		$this->assertSame( array_unique( $urls ), $urls, 'User URLs must be unique.' );
	}

	/**
	 * User records from search() also carry the expected deep link and user id.
	 *
	 * @return void
	 */
	public function test_search_user_url_ends_with_user_id(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		$user = $this->make_user(
			array(
				'ID' => 42,
			)
		);

		$query = Mockery::mock( 'overload:WP_User_Query' );
		$query->shouldReceive( 'get_results' )->andReturn( array( $user ) );

		$indexer = new Users_Indexer();
		$results = $indexer->search( 'admin' );

		$this->assertCount( 1, $results );
		$this->assertStringEndsWith( 'user-edit.php?user_id=42', $results[0]['url'] );
	}
}
