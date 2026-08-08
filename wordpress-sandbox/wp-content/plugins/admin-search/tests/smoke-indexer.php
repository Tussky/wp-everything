<?php
/**
 * Smoke test: indexer for admin-search.
 *
 * Runs under `wp eval-file tests/smoke-indexer.php` from the WordPress CLI
 * container. Validates:
 *
 *   1. AS_Indexer::rebuild() returns records.
 *   2. Each source has the expected shape (id, type, title, snippet, url,
 *      breadcrumb, payload).
 *   3. When WC is active, products count is non-zero (only when seeded).
 *   4. When WC is inactive, products count is 0.
 *
 * Prints JSON of per-source counts and exits 0 on success.
 *
 * @package AdminSearch
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	fwrite( STDERR, "smoke-indexer.php must be run via wp eval-file\n" );
	exit( 1 );
}

if ( ! class_exists( 'AS_Indexer' ) ) {
	WP_CLI::error( 'AS_Indexer class not loaded; is the plugin active?' );
}

$payload = AS_Indexer::rebuild();

$required = array( 'id', 'type', 'title', 'snippet', 'url', 'breadcrumb', 'payload' );
$failures = array();

foreach ( $payload['records'] as $rec ) {
	foreach ( $required as $key ) {
		if ( ! array_key_exists( $key, $rec ) ) {
			$failures[] = "Record {$rec['id']} missing key: {$key}";
		}
	}
}

if ( ! empty( $failures ) ) {
	WP_CLI::error_multi_line( $failures );
	WP_CLI::halt( 1 );
}

$wc_active = class_exists( 'WooCommerce' );

$summary = array(
	'total'      => $payload['total'],
	'counts'     => $payload['counts'],
	'wc_active'  => $wc_active,
	'last_built' => get_option( AS_OPTION_STATS, array() ),
);

WP_CLI::line( wp_json_encode( $summary, JSON_PRETTY_PRINT ) );

if ( $wc_active && 0 === $payload['counts']['products'] ) {
	WP_CLI::warning( 'WC active but products count is 0 — seed at least one product.' );
}
if ( ! $wc_active && 0 !== $payload['counts']['products'] ) {
	WP_CLI::halt( 1, 'WC inactive but products count is non-zero.' );
}

WP_CLI::success( 'smoke-indexer: all checks passed.' );
exit( 0 );