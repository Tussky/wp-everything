<?php
/**
 * Posts Indexer unit tests.
 *
 * @package WP_Search
 */

namespace WP_Search\Tests;

use Brain\Monkey\Functions;
use Mockery;
use WP_Search\Posts_Indexer;

/**
 * Tests for Posts_Indexer.
 */
class Posts_Indexer_Tests extends Test_Case {

	/**
	 * Create a fake WP_Post object.
	 *
	 * @param array<mixed> $props Post properties.
	 * @return \WP_Post
	 */
	private function make_post( array $props = array() ): \WP_Post {
		$props = array_merge(
			array(
				'ID'         => 42,
				'post_title' => 'Hello World',
				'post_type'  => 'post',
			),
			$props
		);

		$post = Mockery::mock( 'WP_Post' );
		foreach ( $props as $key => $value ) {
			$post->{$key} = $value;
		}
		return $post;
	}

	/**
	 * Common stubs for post lookup helpers.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'get_the_title' )->returnArg();
		Functions\when( 'get_the_excerpt' )->justReturn( 'Excerpt' );
		Functions\when( 'wp_reset_postdata' )->justReturn( null );
		Functions\when( 'get_post_types' )->justReturn( array( 'post', 'page' ) );
	}

	/**
	 * Source label must be content.
	 *
	 * @return void
	 */
	public function test_source_constant(): void {
		$this->assertSame( 'content', Posts_Indexer::SOURCE );
	}

	/**
	 * Empty query must return an empty array.
	 *
	 * @return void
	 */
	public function test_search_returns_empty_for_empty_query(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		$indexer = new Posts_Indexer();
		$this->assertEmpty( $indexer->search( '' ) );
		$this->assertEmpty( $indexer->search( '   ' ) );
	}

	/**
	 * Empty post type list must return an empty array.
	 *
	 * @return void
	 */
	public function test_search_returns_empty_with_no_post_types(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		$indexer = new Posts_Indexer( array() );
		$this->assertEmpty( $indexer->search( 'hello' ) );
	}

	/**
	 * A matching post should be returned with the expected shape.
	 *
	 * @return void
	 */
	public function test_search_returns_posts(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_post' )->alias(
			function ( $post_id ) {
				return $this->make_post( array( 'ID' => $post_id ) );
			}
		);
		Functions\when( 'get_edit_post_link' )->justReturn( 'https://example.com/wp-admin/post.php?action=edit' );

		$query = Mockery::mock( 'overload:WP_Query' );
		$query->shouldReceive( '__construct' )->withAnyArgs()->andSet( 'posts', array( 42 ) );

		$indexer = new Posts_Indexer();
		$results = $indexer->search( 'hello' );

		$this->assertCount( 1, $results );
		$this->assertSame( 'content', $results[0]['source'] );
		$this->assertSame( 'post', $results[0]['type'] );
		$this->assertSame( 'https://example.com/wp-admin/post.php?action=edit', $results[0]['url'] );
	}

	/**
	 * Posts the current user cannot edit should be skipped.
	 *
	 * @return void
	 */
	public function test_search_skips_posts_without_edit_permission(): void {
		Functions\when( 'current_user_can' )->alias(
			function ( $capability, $post_id = null ) {
				return 'edit_post' !== $capability;
			}
		);

		$query = Mockery::mock( 'overload:WP_Query' );
		$query->shouldReceive( '__construct' )->withAnyArgs()->andSet( 'posts', array( 42 ) );

		$indexer = new Posts_Indexer();
		$results = $indexer->search( 'hello' );

		$this->assertEmpty( $results );
	}

	/**
	 * When no edit link is available the permalink should be used.
	 *
	 * @return void
	 */
	public function test_search_uses_permalink_when_edit_link_missing(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_post' )->alias(
			function ( $post_id ) {
				return $this->make_post( array( 'ID' => $post_id ) );
			}
		);
		Functions\when( 'get_edit_post_link' )->justReturn( false );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.com/hello-world/' );

		$query = Mockery::mock( 'overload:WP_Query' );
		$query->shouldReceive( '__construct' )->withAnyArgs()->andSet( 'posts', array( 7 ) );

		$indexer = new Posts_Indexer();
		$results = $indexer->search( 'hello' );

		$this->assertSame( 'https://example.com/hello-world/', $results[0]['url'] );
	}

	/**
	 * When no post types are provided, the indexer should query all public post types.
	 *
	 * @return void
	 */
	public function test_default_post_types_are_all_public_post_types(): void {
		Functions\when( 'get_post_types' )->justReturn( array( 'post', 'page', 'custom_type' ) );

		$indexer  = new Posts_Indexer();
		$property = new \ReflectionProperty( $indexer, 'post_types' );

		$this->assertSame( array( 'post', 'page', 'custom_type' ), $property->getValue( $indexer ) );
	}

	/**
	 * Explicit post types should override the public-post-type default.
	 *
	 * @return void
	 */
	public function test_explicit_post_types_override_public_default(): void {
		$indexer  = new Posts_Indexer( array( 'custom_type' ) );
		$property = new \ReflectionProperty( $indexer, 'post_types' );

		$this->assertSame( array( 'custom_type' ), $property->getValue( $indexer ) );
	}

	/**
	 * Reindex is a no-op and should return 0.
	 *
	 * @return void
	 */
	public function test_reindex_returns_zero(): void {
		$indexer = new Posts_Indexer();
		$this->assertSame( 0, $indexer->reindex() );
	}
}
