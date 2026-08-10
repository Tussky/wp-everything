<?php
/**
 * Posts Indexer
 *
 * Searches publish posts and pages using WP_Query.
 *
 * @package WP_Search
 */

namespace WP_Search;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Indexes and searches public posts and pages.
 *
 * @since 1.0.0
 */
class Posts_Indexer extends Indexer {

	/**
	 * Source label for results.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const SOURCE = 'content';

	/**
	 * Default post types to search.
	 *
	 * @since 1.0.0
	 * @var array<string>
	 */
	const DEFAULT_POST_TYPES = array( 'post', 'page' );

	/**
	 * Maximum number of results to return.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const RESULTS_LIMIT = 10;

	/**
	 * Post types included in this index.
	 *
	 * @since 1.0.0
	 * @var array<string>
	 */
	private array $post_types;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param array<string> $post_types Post types to search.
	 */
	public function __construct( array $post_types = self::DEFAULT_POST_TYPES ) {
		$this->post_types = array_filter( array_map( 'sanitize_key', $post_types ) );
	}

	/**
	 * Return the source label for these results.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_source(): string {
		return self::SOURCE;
	}

	/**
	 * Search posts and pages with WP_Query.
	 *
	 * An empty query intentionally returns no results; the REST layer can decide
	 * whether to show a default list.
	 *
	 * @since 1.0.0
	 * @param string $query Search query.
	 * @return array<mixed>
	 */
	public function search( string $query ): array {
		if ( empty( $this->post_types ) || '' === trim( $query ) ) {
			return array();
		}

		$args = array(
			'post_type'              => $this->post_types,
			'post_status'            => 'publish',
			's'                      => sanitize_text_field( $query ),
			'posts_per_page'         => self::RESULTS_LIMIT,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		$q = new \WP_Query( $args );

		$results = array();
		foreach ( $q->posts as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}

			$results[] = $this->normalize_record(
				array(
					'title'       => get_the_title( $post ),
					'description' => get_the_excerpt( $post ),
					'keywords'    => '',
					'url'         => get_permalink( $post ),
					'type'        => $post->post_type,
				)
			);
		}
		wp_reset_postdata();

		return $results;
	}

	/**
	 * No persistent cache is needed; WP_Query is used live.
	 *
	 * @since 1.0.0
	 * @return int
	 */
	public function reindex(): int {
		return 0;
	}
}
