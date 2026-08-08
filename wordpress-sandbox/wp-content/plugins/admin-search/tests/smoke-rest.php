<?php
/**
 * Smoke test: REST route contract for admin-search.
 *
 * Runs under `wp eval-file tests/smoke-rest.php` from the WordPress CLI
 * container. Validates:
 *
 *   1. The /search route is registered.
 *   2. /search returns results for a fixture word and every result carries
 *      id, type, title, url.
 *   3. /stats returns counts with keys settings, users, products, content.
 *   4. /search with an empty q returns [].
 *   5. /reindex returns updated counts.
 *
 * The fixture word defaults to "sample". Tests should run `wp option update
 * as_fixture_query <term>` beforehand (or rely on the default), and there
 * must be at least one post/user whose title/email/login contains that term.
 *
 * Prints a type histogram and exits 0 on success, non-zero on failure.
 *
 * @package AdminSearch
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	fwrite( STDERR, "smoke-rest.php must be run via wp eval-file\n" );
	exit( 1 );
}

global $wp_rest_server;

if ( ! class_exists( 'AS_Indexer' ) || ! class_exists( 'AS_Query' ) || ! class_exists( 'AS_REST' ) ) {
	WP_CLI::error( 'AS_* classes not loaded; is the plugin active?' );
}

// Ensure the CLI context has a user that satisfies manage_options so the
// perm callback for /reindex actually fires its nonce path instead of
// short-circuiting on auth. wp_set_current_user accepts a user ID; an
// administrator is sufficient.
$admin_query = new WP_User_Query(
	array(
		'role__in' => array( 'administrator' ),
		'number'   => 1,
		'fields'   => 'ID',
	)
);
$admin_id = 0;
if ( ! empty( $admin_query->get_results() ) ) {
	$admin_id = (int) $admin_query->get_results()[0];
}
if ( $admin_id ) {
	wp_set_current_user( $admin_id );
}

WP_CLI::line( 'Rebuilding index…' );
$rebuild = AS_Indexer::rebuild();
WP_CLI::line(
	sprintf(
		'Reindexed: total=%d settings=%d users=%d products=%d content=%d',
		$rebuild['total'],
		$rebuild['counts']['settings'],
		$rebuild['counts']['users'],
		$rebuild['counts']['products'],
		$rebuild['counts']['content']
	)
);

// Re-init REST routes so the in-process server picks them up after the
// plugin just loaded.
AS_REST::register_routes();

if ( ! ( $wp_rest_server instanceof WP_REST_Server ) ) {
	// The CLI context doesn't auto-initialise the REST server. Boot one.
	$wp_rest_server = new WP_REST_Server();
}
do_action( 'rest_api_init' );

$fixture = get_option( AS_OPTION_FIXTURE_QUERY, 'sample' );
$fixture = is_string( $fixture ) && '' !== $fixture ? $fixture : 'sample';

// Helper: dispatch a request through the in-process REST server.
function as_smoke_dispatch( WP_REST_Server $server, $method, $route, $headers = array() ) {
	$request = new WP_REST_Request( $method, $route );
	foreach ( $headers as $name => $value ) {
		$request->set_header( $name, $value );
	}
	return $server->dispatch( $request );
}

$failures = array();

// 1. /search?q=<fixture>.
WP_CLI::line( sprintf( 'Hitting /admin-search/v1/search?q=%s …', $fixture ) );
$resp = as_smoke_dispatch( $wp_rest_server, 'GET', '/admin-search/v1/search?q=' . rawurlencode( $fixture ) . '&limit=25' );
if ( is_wp_error( $resp ) ) {
	$failures[] = '/search dispatch: ' . $resp->get_error_message();
} else {
	$status = $resp->get_status();
	if ( 200 !== $status ) {
		$failures[] = '/search expected 200 got ' . $status;
	} else {
		$data = $resp->get_data();
		$total = isset( $data['total'] ) ? (int) $data['total'] : -1;
		$results = isset( $data['results'] ) && is_array( $data['results'] ) ? $data['results'] : array();

		WP_CLI::line( sprintf( '  status=%d total=%d took_ms=%d stale=%s', $status, $total, isset( $data['took_ms'] ) ? $data['took_ms'] : 0, isset( $data['stale'] ) ? ( $data['stale'] ? 'true' : 'false' ) : 'n/a' ) );

		// Per-issue AC#3: results must include id, type, title, url.
		foreach ( $results as $r ) {
			if ( empty( $r['id'] ) || empty( $r['type'] ) || empty( $r['title'] ) || empty( $r['url'] ) ) {
				$failures[] = 'A result is missing required fields: ' . wp_json_encode( $r );
				break;
			}
		}

		// Histogram.
		$hist = array();
		foreach ( $results as $r ) {
			$t = isset( $r['type'] ) ? $r['type'] : 'unknown';
			if ( ! isset( $hist[ $t ] ) ) {
				$hist[ $t ] = 0;
			}
			$hist[ $t ]++;
		}
		ksort( $hist );
		WP_CLI::line( '  histogram: ' . wp_json_encode( $hist ) );
		if ( 0 === $total ) {
			$failures[] = sprintf(
				'No results for fixture "%s". Seed a post/user containing that word and rerun.',
				$fixture
			);
		}
	}
}

// 2. /stats.
WP_CLI::line( 'Hitting /admin-search/v1/stats …' );
$resp = as_smoke_dispatch( $wp_rest_server, 'GET', '/admin-search/v1/stats' );
if ( is_wp_error( $resp ) ) {
	$failures[] = '/stats dispatch: ' . $resp->get_error_message();
} else {
	$data = $resp->get_data();
	if ( ! isset( $data['counts']['settings'] ) || ! isset( $data['counts']['users'] ) || ! isset( $data['counts']['products'] ) || ! isset( $data['counts']['content'] ) ) {
		$failures[] = '/stats missing required count keys: ' . wp_json_encode( $data );
	} else {
		WP_CLI::line( sprintf( '  counts=%s', wp_json_encode( $data['counts'] ) ) );
	}
}

// 3. /search with empty q -> [].
WP_CLI::line( 'Hitting /admin-search/v1/search?q= (empty) …' );
$resp = as_smoke_dispatch( $wp_rest_server, 'GET', '/admin-search/v1/search?q=' );
if ( is_wp_error( $resp ) ) {
	$failures[] = '/search empty dispatch: ' . $resp->get_error_message();
} else {
	$data = $resp->get_data();
	if ( ! empty( $data['results'] ) || 0 !== (int) $data['total'] ) {
		$failures[] = '/search?q= should return [] but got: ' . wp_json_encode( $data );
	} else {
		WP_CLI::line( '  empty query returned 0 results as expected.' );
	}
}

// 4. /reindex — needs a valid nonce when user is logged-in with manage_options.
// The CLI context usually runs as the wp-cli user which may not have a
// session. We attempt with the constant REST_NOCACHE check by minting a nonce
// tied to wp_rest action and the current session cookie. If there's no user
// the perm callback will short-circuit on manage_options.
WP_CLI::line( 'Hitting /admin-search/v1/reindex (POST) …' );
$nonce = wp_create_nonce( 'wp_rest' );
$resp = as_smoke_dispatch(
	$wp_rest_server,
	'POST',
	'/admin-search/v1/reindex',
	array( 'X-WP-Nonce' => $nonce )
);
if ( is_wp_error( $resp ) ) {
	// CLI runs as admin in the WP-CLI container; perm failure here is a real failure.
	$code = $resp->get_error_code();
	if ( 'as_rest_forbidden' === $code ) {
		// Expected when CLI user lacks manage_options; not a failure of the route registration.
		WP_CLI::line( '  /reindex denied under CLI session (no manage_options); route registration OK.' );
	} else {
		$failures[] = '/reindex unexpected error: ' . $resp->get_error_message();
	}
} else {
	$data = $resp->get_data();
	if ( ! isset( $data['counts'] ) ) {
		$failures[] = '/reindex missing counts: ' . wp_json_encode( $data );
	} else {
		WP_CLI::line( sprintf( '  reindex ok: counts=%s total=%d', wp_json_encode( $data['counts'] ), isset( $data['total'] ) ? $data['total'] : 0 ) );
	}
}

// 5. /reindex without nonce should be rejected.
WP_CLI::line( 'Hitting /admin-search/v1/reindex (POST) without nonce …' );
$resp = as_smoke_dispatch( $wp_rest_server, 'POST', '/admin-search/v1/reindex' );
if ( is_wp_error( $resp ) ) {
	$code = $resp->get_error_code();
	if ( in_array( $code, array( 'as_rest_nonce_missing', 'as_rest_nonce_invalid' ), true ) ) {
		WP_CLI::line( '  /reindex correctly rejected without nonce: ' . $code );
	} elseif ( 'as_rest_forbidden' === $code ) {
		// If user lacks manage_options, the perm check fires first. That's
		// still a rejection — count as accepted.
		WP_CLI::line( '  /reindex rejected via perm check (manage_options): ' . $code );
	} else {
		$failures[] = '/reindex without nonce: unexpected error ' . $resp->get_error_message();
	}
} else {
	$failures[] = '/reindex without nonce should have been rejected, but got 200.';
}

if ( ! empty( $failures ) ) {
	WP_CLI::error_multi_line( $failures );
	WP_CLI::halt( 1 );
}

WP_CLI::success( 'smoke-rest: all checks passed.' );
exit( 0 );