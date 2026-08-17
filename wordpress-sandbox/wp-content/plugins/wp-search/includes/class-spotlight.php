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
	 * @return array<mixed> Response: {_meta, facets: {users, plugins, options, settings}}.
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

		return array(
			'_meta'  => self::build_meta( $facets ),
			'facets' => $facets,
		);
	}

	/**
	 * Per-facet cap applied to the flat payload. Mirrors the contract.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const FACET_CAP = 50;

	/**
	 * Exact field list emitted per facet in the flat wire payload.
	 *
	 * Order is the on-the-wire order. Drives projection and the contract tests.
	 *
	 * @since 1.0.0
	 * @var array<string, array<string>>
	 */
	const FACET_FIELDS = array(
		'users'    => array( 'id', 'hue', 'displayName', 'username', 'role', 'email', 'capabilities', 'registered', 'lastLogin', 'url' ),
		'plugins'  => array( 'id', 'name', 'slug', 'active', 'version', 'updateAvailable', 'author', 'description', 'url' ),
		'options'  => array( 'id', 'name', 'value', 'protected', 'autoload', 'explainer', 'url' ),
		'settings' => array( 'id', 'source', 'sourceKind', 'breadcrumb', 'language', 'snippet', 'url' ),
	);

	/**
	 * Build the flat Spotlight wire payload from provider records.
	 *
	 * Unlike {@see build_response()}, this returns the flat contract object
	 * `{ users, plugins, options, settings }` with no `_meta`/`facets` wrapper
	 * and no `search`/`display` sub-objects. Matching stays server-side here on
	 * `search.terms`; each facet is capped at {@see FACET_CAP}, sorted by
	 * `search.weight` descending (id as tiebreaker), and projected to the exact
	 * field list in {@see FACET_FIELDS}.
	 *
	 * @since 1.0.0
	 * @param array<mixed> $records      Spotlight records from all providers (nested internal shape).
	 * @param string       $query        Raw search query.
	 * @param string       $facet_filter Optional facet to restrict to (''|users|plugins|options|settings).
	 * @return array<mixed> Flat payload keyed by facet.
	 */
	public static function to_flat_payload( array $records, string $query, string $facet_filter = '' ): array {
		$term   = self::normalize( $query );
		$groups = array();
		foreach ( self::FACET_ORDER as $facet ) {
			$groups[ $facet ] = array();
		}

		foreach ( $records as $record ) {
			if ( ! is_array( $record ) ) {
				continue;
			}
			$facet = isset( $record['facet'] ) ? (string) $record['facet'] : '';
			if ( ! isset( $groups[ $facet ] ) ) {
				continue;
			}
			if ( '' !== $term && ! self::record_matches( $record, $term ) ) {
				continue;
			}
			$groups[ $facet ][] = $record;
		}

		$payload = array();
		foreach ( self::FACET_ORDER as $facet ) {
			$rows = $groups[ $facet ];
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
			if ( count( $rows ) > self::FACET_CAP ) {
				$rows = array_slice( $rows, 0, self::FACET_CAP );
			}
			$payload[ $facet ] = array_map(
				static function ( $record ) use ( $facet ) {
					return self::project_record( $facet, $record );
				},
				$rows
			);
		}

		if ( '' !== $facet_filter && isset( $payload[ $facet_filter ] ) ) {
			foreach ( self::FACET_ORDER as $f ) {
				if ( $f !== $facet_filter ) {
					$payload[ $f ] = array();
				}
			}
		}

		return $payload;
	}

	/**
	 * Project a single nested internal record to its flat wire shape for a facet.
	 *
	 * Emits exactly the keys in {@see FACET_FIELDS} for that facet — nothing else
	 * — pulling values from the record's `display` block. `passwordHash` is never
	 * projected. Missing display values default to sane empties.
	 *
	 * @since 1.0.0
	 * @param string       $facet  Facet name.
	 * @param array<mixed> $record Nested internal record.
	 * @return array<mixed> Flat record.
	 */
	public static function project_record( string $facet, array $record ): array {
		$display = isset( $record['display'] ) && is_array( $record['display'] ) ? $record['display'] : array();
		$fields  = self::FACET_FIELDS[ $facet ] ?? array();
		$out     = array();

		foreach ( $fields as $field ) {
			if ( 'id' === $field ) {
				$out['id'] = isset( $record['id'] ) ? $record['id'] : '';
				continue;
			}
			$value = $display[ $field ] ?? null;
			// Coerce scalar/empty to the contract's typed empties.
			if ( 'capabilities' === $field || 'breadcrumb' === $field ) {
				$out[ $field ] = is_array( $value ) ? array_values( $value ) : array();
				continue;
			}
			if ( 'active' === $field ) {
				$out[ $field ] = (bool) $value;
				continue;
			}
			if ( 'protected' === $field ) {
				$out[ $field ] = (bool) $value;
				continue;
			}
			if ( 'hue' === $field ) {
				$out[ $field ] = isset( $value ) ? (int) $value : 0;
				continue;
			}
			if ( 'updateAvailable' === $field ) {
				$out[ $field ] = ( null === $value || '' === $value ) ? null : (string) $value;
				continue;
			}
			$out[ $field ] = null === $value ? '' : $value;
		}

		return $out;
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