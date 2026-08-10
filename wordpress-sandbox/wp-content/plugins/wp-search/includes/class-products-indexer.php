<?php
/**
 * Products Indexer
 *
 * Searches WooCommerce products using WP_Query.
 *
 * @package WP_Search
 */

namespace WP_Search;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Indexes and searches WooCommerce products.
 *
 * @since 1.0.0
 */
class Products_Indexer extends Posts_Indexer {

	/**
	 * Source label for results.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const SOURCE = 'products';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct( array( 'product' ) );
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
	 * Skip searching when the product post type does not exist.
	 *
	 * @since 1.0.0
	 * @param string $query Search query.
	 * @return array<mixed>
	 */
	public function search( string $query ): array {
		if ( ! post_type_exists( 'product' ) ) {
			return array();
		}

		return parent::search( $query );
	}
}
