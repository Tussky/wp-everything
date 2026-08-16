<?php
/**
 * Spotlight engine.
 *
 * Stateless matcher and response builder for the spotlight-data.json shape.
 * `search.terms` is the ONLY text the matcher scans (case-insensitive
 * substring). `search.weight` ranks records within their facet section.
 *
 * @package WP_Search
 */

namespace WP_Search;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Matches and groups spotlight records into the four-facet response shape.
 *
 * @since 1.0.0
 */
class Spotlight {

	/**
	 * Schema version reported in the response `_meta` block.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const SCHEMA_VERSION = '1.0';

	/**
	 * Canonical facet order. Drives grouping, ordering, and counts.
	 *
	 * @since 1.0.0
	 * @var array<string>
	 */
	const FACET_ORDER = array( 'users', 'plugins', 'options', 'settings' );

	/**
	 * Match a collection of spotlight records against a query and return the
	 * full four-facet response payload, including `_meta`.
	 *
	 * An empty query returns every record (still sorted by weight within
	 * each facet), which is how a "browse" request is served.
	 *
	 * @since 1.0.0
	 * @param array<mixed> $records Spotlight records from all providers.
	 * @param string       $query   Raw search query.
	 * @return array<mixed> Response: {_meta, users, plugins, options, settings}.
	 */
	public static function build_response( array $records, string $query ): array {
		$term    = self::normalize( $query );
		$matched = array();

		foreach ( $records as $record ) {
			if ( ! is_array( $record ) ) {
				continue;
			}
			if ( '' === $term || self::record_matches( $record, $term ) ) {
				$matched[] = $record;
			}
		}

		$facets = self::group_and_sort( $matched );

		return array_merge(
			array( '_meta' => self::build_meta( $facets ) ),
			$facets
		);
	}

	/**
	 * Test a single record's `search.terms` against the normalized query.
	 *
	 * @since 1.0.0
	 * @param array<mixed> $record Spotlight record.
	 * @param string        $term   Lowercase query.
	 * @return bool
	 */
	public static function record_matches( array $record, string $term ): bool {
		$terms = $record['search']['terms'] ?? array();
		if ( ! is_array( $terms ) ) {
			return false;
		}

		foreach ( $terms as $haystack ) {
			if ( ! is_string( $haystack ) ) {
				continue;
			}
			if ( false !== strpos( self::normalize( $haystack ), $term ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Group records into facet arrays per FACET_ORDER and sort each facet by
	 * `search.weight` descending (stable on id as tiebreaker).
	 *
	 * @since 1.0.0
	 * @param array<mixed> $records Matched records.
	 * @return array<array<mixed>> keyed by facet name.
	 */
	public static function group_and_sort( array $records ): array {
		$facets = array();
		foreach ( self::FACET_ORDER as $facet ) {
			$facets[ $facet ] = array();
		}

		foreach ( $records as $record ) {
			$facet = isset( $record['facet'] ) ? (string) $record['facet'] : '';
			if ( ! isset( $facets[ $facet ] ) ) {
				continue; // Unknown facet — ignored, keeps the shape stable.
			}
			$facets[ $facet ][] = $record;
		}

		foreach ( $facets as $facet => $rows ) {
			usort(
				$rows,
				static function ( $a, $b ) {
					$wa = isset( $a['search']['weight'] ) ? (int) $a['search']['weight'] : 0;
					$wb = isset( $b['search']['weight'] ) ? (int) $b['search']['weight'] : 0;
					if ( $wa === $wb ) {
						$ia = isset( $a['id'] ) ? (string) $a['id'] : '';
						$ib = isset( $b['id'] ) ? (string) $b['id'] : '';
						return $ia <=> $ib;
					}
					return $wb <=> $wa;
				}
			);
			$facets[ $facet ] = $rows;
		}

		return $facets;
	}

	/**
	 * Build the `_meta` block with schema version, facet order, and counts.
	 *
	 * @since 1.0.0
	 * @param array<array<mixed>> $facets Grouped facet arrays.
	 * @return array<mixed>
	 */
	public static function build_meta( array $facets ): array {
		$counts = array();
		foreach ( self::FACET_ORDER as $facet ) {
			$counts[ $facet ] = isset( $facets[ $facet ] ) ? count( $facets[ $facet ] ) : 0;
		}

		return array(
			'schemaVersion' => self::SCHEMA_VERSION,
			'facetOrder'    => self::FACET_ORDER,
			'counts'        => $counts,
		);
	}

	/**
	 * Lowercase + trim a string for case-insensitive substring matching.
	 *
	 * @since 1.0.0
	 * @param string $value Raw value.
	 * @return string
	 */
	private static function normalize( string $value ): string {
		return mb_strtolower( trim( $value ) );
	}
}