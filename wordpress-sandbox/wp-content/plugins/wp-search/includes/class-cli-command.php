<?php
/**
 * WP-CLI Spotlight command.
 *
 * `wp wp-search spotlight` builds its output by calling the
 * `/wp-search/v1/spotlight` REST route via `rest_do_request()` — it never
 * instantiates the indexers directly. The route is the single source of truth
 * for the flat Spotlight data contract.
 *
 * @package WP_Search
 */

namespace WP_Search;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Drives the Spotlight route from the command line.
 *
 * @since 1.0.0
 */
class CLI_Command {

	/**
	 * Canonical facet order, mirrored from the route contract.
	 *
	 * @since 1.0.0
	 * @var array<string>
	 */
	const FACET_ORDER = array( 'users', 'plugins', 'options', 'settings' );

	/**
	 * Print Spotlight data either as a five-line contract summary or as the
	 * raw flat JSON payload.
	 *
	 * ## OPTIONS
	 *
	 * [--q=<query>]
	 * : Optional search query forwarded to the route's `q` param.
	 *
	 * [--facet=<facet>]
	 * : Restrict the payload to one facet (users|plugins|options|settings).
	 * Filtered server-side by the route.
	 *
	 * [--format=<format>]
	 * : Output format: `summary` (default) or `json`.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wp-search spotlight
	 *     wp wp-search spotlight --q=stripe --format=json
	 *     wp wp-search spotlight --facet=options
	 *
	 * @since 1.0.0
	 * @param array<string> $args       Positional args (unused).
	 * @param array<string> $assoc_args  Named options.
	 * @return void
	 */
	public function spotlight( array $args, array $assoc_args ): void {
		$format = isset( $assoc_args['format'] ) ? (string) $assoc_args['format'] : 'summary';
		$q      = isset( $assoc_args['q'] ) ? (string) $assoc_args['q'] : '';
		$facet  = isset( $assoc_args['facet'] ) ? (string) $assoc_args['facet'] : '';

		$payload = $this->fetch_payload( $q, $facet );

		if ( 'json' === $format ) {
			$this->print_json( $payload );
			return;
		}

		$this->print_summary( $payload );
	}

	/**
	 * Call the Spotlight REST route via rest_do_request and return its data,
	 * or null when the caller is not authorized (e.g. a subscriber) so the
	 * command prints nothing.
	 *
	 * @since 1.0.0
	 * @param string $q     Search query.
	 * @param string $facet Optional facet filter.
	 * @return array<mixed>|null
	 */
	private function fetch_payload( string $q, string $facet ): ?array {
		/*
		 * `wp wp-search spotlight` with no --user runs without an authenticated
		 * WordPress user, so the REST permission_callback (manage_options) would
		 * 403 and the command would print nothing. Default to the primary admin
		 * (user 1) for the REST call only when no user is already set, so the
		 * bare command surfaces data. An explicit --user=<subscriber> is set up
		 * by WP-CLI before this runs, so get_current_user_id() is non-zero and
		 * the override is skipped — the subscriber 403s and prints nothing.
		 */
		if ( function_exists( 'get_current_user_id' ) && ! get_current_user_id() ) {
			if ( function_exists( 'wp_set_current_user' ) ) {
				wp_set_current_user( 1 );
			}
		}

		$request = new \WP_REST_Request( 'GET', '/wp-search/v1/spotlight' );
		$request->set_param( 'q', $q );

		if ( '' !== $facet ) {
			$request->set_param( 'facet', $facet );
		}

		$response = rest_do_request( $request );

		if ( is_wp_error( $response ) ) {
			return null;
		}

		// A 403 (e.g. a subscriber without manage_options) comes back as a
		// WP_REST_Response whose get_data() is the {code,message,data} error
		// payload, not a WP_Error. Treat any >= 400 status as "not authorized
		// / no data" so the command prints nothing instead of an error trace.
		if ( is_object( $response ) && method_exists( $response, 'get_status' ) ) {
			$status = (int) $response->get_status();
			if ( $status >= 400 ) {
				return null;
			}
		}

		$data = $response->get_data();
		if ( ! is_array( $data ) || isset( $data['code'] ) ) {
			return null;
		}

		return $data;
	}

	/**
	 * Print exactly five contract-check lines. A passing check prints
	 * `CHECK_OK`; a failing check prints `FAIL_<NAME>: <reason>`.
	 *
	 * @since 1.0.0
	 * @param array<mixed>|null $payload Flat Spotlight payload.
	 * @return void
	 */
	private function print_summary( ?array $payload ): void {
		if ( null === $payload ) {
			return;
		}

		$lines = array(
			$this->check_facet_order( $payload ),
			$this->check_all_facets_populated( $payload ),
			$this->check_urls_present( $payload ),
			$this->check_no_password_hash( $payload ),
			$this->check_protected_values_null( $payload ),
		);

		foreach ( $lines as $line ) {
			\WP_CLI::log( $line );
		}
	}

	/**
	 * Print the raw flat payload as JSON.
	 *
	 * @since 1.0.0
	 * @param array<mixed>|null $payload Flat Spotlight payload.
	 * @return void
	 */
	private function print_json( ?array $payload ): void {
		if ( null === $payload ) {
			return;
		}

		\WP_CLI::log( wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
	}

	/**
	 * FACET_ORDER_OK: the payload is a flat object with exactly the four
	 * facets, in order, and no `_meta`/`facets` wrapper.
	 *
	 * @since 1.0.0
	 * @param array<mixed> $payload Flat payload.
	 * @return string
	 */
	private function check_facet_order( array $payload ): string {
		$keys = array_keys( $payload );
		if ( $keys === self::FACET_ORDER ) {
			return 'CHECK_OK';
		}

		return 'FAIL_FACET_ORDER: expected keys [users,plugins,options,settings], got [' . implode( ',', $keys ) . ']';
	}

	/**
	 * ALL_FACETS_POPULATED: every facet array has at least one record.
	 *
	 * @since 1.0.0
	 * @param array<mixed> $payload Flat payload.
	 * @return string
	 */
	private function check_all_facets_populated( array $payload ): string {
		$empty = array();
		foreach ( self::FACET_ORDER as $facet ) {
			if ( empty( $payload[ $facet ] ) || ! is_array( $payload[ $facet ] ) ) {
				$empty[] = $facet;
			}
		}

		if ( empty( $empty ) ) {
			return 'CHECK_OK';
		}

		return 'FAIL_ALL_FACETS_POPULATED: empty facets [' . implode( ',', $empty ) . ']';
	}

	/**
	 * URLS_PRESENT: every record in every facet carries a non-empty `url`.
	 *
	 * @since 1.0.0
	 * @param array<mixed> $payload Flat payload.
	 * @return string
	 */
	private function check_urls_present( array $payload ): string {
		foreach ( self::FACET_ORDER as $facet ) {
			if ( empty( $payload[ $facet ] ) || ! is_array( $payload[ $facet ] ) ) {
				continue;
			}

			foreach ( $payload[ $facet ] as $i => $record ) {
				if ( ! is_array( $record ) ) {
					return 'FAIL_URLS_PRESENT: ' . $facet . '[' . $i . '] is not a record';
				}
				$url = $record['url'] ?? '';
				if ( ! is_string( $url ) || '' === trim( $url ) ) {
					return 'FAIL_URLS_PRESENT: ' . $facet . '[' . $i . '] missing url';
				}
			}
		}

		return 'CHECK_OK';
	}

	/**
	 * NO_PASSWORD_HASH: no key named `passwordHash` exists anywhere in the
	 * payload.
	 *
	 * @since 1.0.0
	 * @param array<mixed> $payload Flat payload.
	 * @return string
	 */
	private function check_no_password_hash( array $payload ): string {
		if ( $this->contains_key( $payload, 'passwordHash' ) ) {
			return 'FAIL_NO_PASSWORD_HASH: passwordHash key present';
		}

		return 'CHECK_OK';
	}

	/**
	 * PROTECTED_VALUES_NULL: every options record with `protected === true`
	 * has `value === null`.
	 *
	 * @since 1.0.0
	 * @param array<mixed> $payload Flat payload.
	 * @return string
	 */
	private function check_protected_values_null( array $payload ): string {
		if ( empty( $payload['options'] ) || ! is_array( $payload['options'] ) ) {
			return 'CHECK_OK';
		}

		foreach ( $payload['options'] as $i => $record ) {
			if ( ! is_array( $record ) ) {
				return 'FAIL_PROTECTED_VALUES_NULL: options[' . $i . '] is not a record';
			}

			$protected = isset( $record['protected'] ) ? (bool) $record['protected'] : false;
			if ( ! $protected ) {
				continue;
			}

			$value = array_key_exists( 'value', $record ) ? $record['value'] : null;
			if ( null !== $value ) {
				return 'FAIL_PROTECTED_VALUES_NULL: options[' . $i . '] protected but value not null';
			}
		}

		return 'CHECK_OK';
	}

	/**
	 * Recursively test whether any array key in the payload equals `$needle`.
	 *
	 * @since 1.0.0
	 * @param array<mixed> $data   Data to scan.
	 * @param string       $needle Key name to find.
	 * @return bool
	 */
	private function contains_key( array $data, string $needle ): bool {
		foreach ( $data as $key => $value ) {
			if ( $needle === (string) $key ) {
				return true;
			}

			if ( is_array( $value ) && $this->contains_key( $value, $needle ) ) {
				return true;
			}
		}

		return false;
	}
}