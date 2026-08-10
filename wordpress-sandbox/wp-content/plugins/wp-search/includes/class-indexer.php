<?php
/**
 * Abstract Indexer
 *
 * Defines the contract for all wp->search indexers. Implementations provide
 * their own index source and search strategy while sharing a common shape for
 * search results.
 *
 * @package WP_Search
 */

namespace WP_Search;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base class for wp->search indexers.
 *
 * @since 1.0.0
 */
abstract class Indexer {

	/**
	 * Return the source label attached to every result from this indexer.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	abstract public function get_source(): string;

	/**
	 * Search the index for records matching the query.
	 *
	 * @since 1.0.0
	 * @param string $query Search query.
	 * @return array<mixed> List of matching records.
	 */
	abstract public function search( string $query ): array;

	/**
	 * Rebuild the index and return the number of items indexed.
	 *
	 * @since 1.0.0
	 * @return int
	 */
	abstract public function reindex(): int;

	/**
	 * Lowercase and trim a query string for case-insensitive matching.
	 *
	 * @since 1.0.0
	 * @param string $query Raw search query.
	 * @return string
	 */
	protected function normalize_query( string $query ): string {
		return mb_strtolower( trim( $query ) );
	}

	/**
	 * Ensure every result carries the indexer's source label.
	 *
	 * @since 1.0.0
	 * @param array<mixed> $record Result record.
	 * @return array<mixed>
	 */
	protected function normalize_record( array $record ): array {
		$record['source'] = $this->get_source();
		return $record;
	}
}
