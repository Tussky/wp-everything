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
		Functions\when( 'admin_url' )->justReturn( 'https://example.com/wp-admin/user-edit.php' );
		Functions\when( 'get_avatar_url' )->justReturn( 'https://example.com/avatar.jpg' );
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
	 * Reindex is a no-op and should return 0.
	 *
	 * @return void
	 */
	public function test_reindex_returns_zero(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		$indexer = new Users_Indexer();
		$this->assertSame( 0, $indexer->reindex() );
	}
}
