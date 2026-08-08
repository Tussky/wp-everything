<?php
/**
 * AS_Query — case-insensitive substring scorer.
 *
 * Loads the persisted index, normalizes the query, scores each record by
 * field (title > snippet > breadcrumb > payload), returns the top $limit
 * records sorted by descending score.
 *
 * @package AdminSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AS_Query {

	const MAX_QUERY_LEN = 100;

	public static function init() {
		// No hooks at MVP.
	}

	/**
	 * Run a search.
	 *
	 * @param string $q     Query.
	 * @param int    $limit Result limit (capped server-side).
	 * @return array { results: [...], total: int, took_ms: int, stale: bool }
	 */
	public static function search( $q, $limit = 25 ) {
		$start = microtime( true );

		$q = self::normalize_query( $q );
		if ( '' === $q ) {
			return array(
				'results' => array(),
				'total'   => 0,
				'took_ms' => (int) round( ( microtime( true ) - $start ) * 1000 ),
				'stale'   => false,
			);
		}

		$stale  = AS_Indexer::is_stale();
		$index  = AS_Indexer::get_index( $stale );
		$records = isset( $index['records'] ) ? $index['records'] : array();

		$limit = max( 1, min( 100, (int) $limit ) );

		$scored = array();
		foreach ( $records as $rec ) {
			$score = self::score_record( $rec, $q );
			if ( $score > 0 ) {
				$scored[] = array(
					'record' => $rec,
					'score'  => $score,
				);
			}
		}

		// Sort by score desc, then by title for stable ordering.
		usort(
			$scored,
			function ( $a, $b ) {
				if ( $b['score'] !== $a['score'] ) {
					return $b['score'] <=> $a['score'];
				}
				return strcmp(
					isset( $a['record']['title'] ) ? $a['record']['title'] : '',
					isset( $b['record']['title'] ) ? $b['record']['title'] : ''
				);
			}
		);

		$top = array_slice( $scored, 0, $limit );
		$results = array_map(
			function ( $row ) {
				return $row['record'];
			},
			$top
		);

		$took_ms = (int) round( ( microtime( true ) - $start ) * 1000 );

		AS_Indexer::record_query( $q, count( $results ), $took_ms );

		return array(
			'results' => $results,
			'total'   => count( $results ),
			'took_ms' => $took_ms,
			'stale'   => $stale,
		);
	}

	/**
	 * Normalize the query: lowercase, strip non-printable, cap to 100 chars.
	 *
	 * @param string $q Raw query.
	 * @return string
	 */
	public static function normalize_query( $q ) {
		if ( ! is_string( $q ) ) {
			return '';
		}
		// Strip non-printable characters.
		$q = preg_replace( '/[^\PC\n\t]/u', ' ', $q );
		$q = strtolower( $q );
		$q = trim( $q );
		if ( strlen( $q ) > self::MAX_QUERY_LEN ) {
			$q = substr( $q, 0, self::MAX_QUERY_LEN );
		}
		return $q;
	}

	/**
	 * Score a record against the normalized query.
	 *
	 * Title +3, snippet +2, breadcrumb +1, payload +0.5.
	 *
	 * @param array  $rec Record.
	 * @param string $q   Normalized query.
	 * @return float
	 */
	protected static function score_record( $rec, $q ) {
		$score = 0.0;

		$title = isset( $rec['title'] ) ? strtolower( (string) $rec['title'] ) : '';
		if ( '' !== $title && false !== strpos( $title, $q ) ) {
			$score += 3.0;
		}

		$snippet = isset( $rec['snippet'] ) ? strtolower( (string) $rec['snippet'] ) : '';
		if ( '' !== $snippet && false !== strpos( $snippet, $q ) ) {
			$score += 2.0;
		}

		$breadcrumb = isset( $rec['breadcrumb'] ) ? strtolower( (string) $rec['breadcrumb'] ) : '';
		if ( '' !== $breadcrumb && false !== strpos( $breadcrumb, $q ) ) {
			$score += 1.0;
		}

		$payload = isset( $rec['payload'] ) ? $rec['payload'] : array();
		if ( is_array( $payload ) ) {
			$payload_str = strtolower( wp_json_encode( $payload ) );
			if ( '' !== $payload_str && false !== strpos( $payload_str, $q ) ) {
				$score += 0.5;
			}
		}

		return $score;
	}
}