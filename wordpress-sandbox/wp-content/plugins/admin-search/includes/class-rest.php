<?php
/**
 * AS_REST — REST controller for the admin-search plugin.
 *
 * Routes (namespace admin-search/v1):
 *   GET  /search?q=<term>&limit=<n>       permission edit_posts
 *   POST /reindex                         permission manage_options, requires X-WP-Nonce
 *   GET  /stats                           permission edit_posts
 *
 * @package AdminSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AS_REST {

	const NS             = AS_REST_NAMESPACE;
	const NONCE_ACTION   = 'wp_rest';
	const MAX_LIMIT      = 100;

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 */
	public static function register_routes() {
		register_rest_route(
			self::NS,
			'/search',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'route_search' ),
				'permission_callback' => array( __CLASS__, 'perm_search' ),
				'args'                => array(
					'q'     => array(
						'type'              => 'string',
						'required'          => false,
						'default'           => '',
						'sanitize_callback' => array( __CLASS__, 'sanitize_query' ),
					),
					'limit' => array(
						'type'              => 'integer',
						'required'          => false,
						'default'           => 25,
						'sanitize_callback' => array( __CLASS__, 'sanitize_limit' ),
					),
				),
				'schema' => array( __CLASS__, 'schema_search' ),
			)
		);

		register_rest_route(
			self::NS,
			'/reindex',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'route_reindex' ),
				'permission_callback' => array( __CLASS__, 'perm_reindex' ),
				'args'                => array(),
				'schema'              => array( __CLASS__, 'schema_reindex' ),
			)
		);

		register_rest_route(
			self::NS,
			'/stats',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'route_stats' ),
				'permission_callback' => array( __CLASS__, 'perm_search' ),
				'schema'              => array( __CLASS__, 'schema_stats' ),
			)
		);
	}

	/**
	 * Sanitize the search query argument.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_query( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}
		return AS_Query::normalize_query( $value );
	}

	/**
	 * Sanitize the limit argument.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public static function sanitize_limit( $value ) {
		$n = is_numeric( $value ) ? (int) $value : 25;
		if ( $n < 1 ) {
			$n = 1;
		}
		if ( $n > self::MAX_LIMIT ) {
			$n = self::MAX_LIMIT;
		}
		return $n;
	}

	/**
	 * Permission for /search and /stats: user must be able to edit posts.
	 *
	 * @return bool|WP_Error
	 */
	public static function perm_search() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error(
				'as_rest_forbidden',
				__( 'You do not have permission to search.', 'admin-search' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		return true;
	}

	/**
	 * Permission for /reindex: manage_options + valid X-WP-Nonce header.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public static function perm_reindex( WP_REST_Request $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'as_rest_forbidden',
				__( 'You do not have permission to reindex.', 'admin-search' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		// Require X-WP-Nonce header. WP REST API authenticates the user already;
		// the nonce check guards against CSRF on state-changing endpoints.
		$nonce = $request->get_header( 'x_wp_nonce' );
		if ( empty( $nonce ) ) {
			return new WP_Error(
				'as_rest_nonce_missing',
				__( 'Missing X-WP-Nonce header.', 'admin-search' ),
				array( 'status' => 403 )
			);
		}
		$valid = wp_verify_nonce( $nonce, self::NONCE_ACTION );
		if ( ! $valid ) {
			return new WP_Error(
				'as_rest_nonce_invalid',
				__( 'Invalid X-WP-Nonce header.', 'admin-search' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	/**
	 * REST schema for /search response.
	 *
	 * @return array
	 */
	public static function schema_search() {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'admin-search-search',
			'type'       => 'object',
			'properties' => array(
				'results' => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'id'         => array( 'type' => 'string' ),
							'type'       => array( 'type' => 'string' ),
							'title'      => array( 'type' => 'string' ),
							'snippet'    => array( 'type' => 'string' ),
							'url'        => array( 'type' => 'string' ),
							'breadcrumb' => array( 'type' => 'string' ),
							'payload'    => array( 'type' => 'object' ),
						),
					),
				),
				'total'   => array( 'type' => 'integer' ),
				'took_ms' => array( 'type' => 'integer' ),
				'stale'   => array( 'type' => 'boolean' ),
			),
		);
	}

	/**
	 * REST schema for /reindex response.
	 *
	 * @return array
	 */
	public static function schema_reindex() {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'admin-search-reindex',
			'type'       => 'object',
			'properties' => array(
				'last_built_at' => array( 'type' => 'string' ),
				'counts'        => array(
					'type'       => 'object',
					'properties' => array(
						'settings' => array( 'type' => 'integer' ),
						'users'    => array( 'type' => 'integer' ),
						'products' => array( 'type' => 'integer' ),
						'content'  => array( 'type' => 'integer' ),
					),
				),
				'total'         => array( 'type' => 'integer' ),
			),
		);
	}

	/**
	 * REST schema for /stats response.
	 *
	 * @return array
	 */
	public static function schema_stats() {
		return self::schema_reindex();
	}

	/**
	 * GET /admin-search/v1/search handler.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function route_search( WP_REST_Request $request ) {
		$q     = $request->get_param( 'q' );
		$limit = (int) $request->get_param( 'limit' );

		$result = AS_Query::search( $q, $limit );

		$response = new WP_REST_Response(
			array(
				'results' => $result['results'],
				'total'   => (int) $result['total'],
				'took_ms' => (int) $result['took_ms'],
				'stale'   => (bool) $result['stale'],
			),
			200
		);
		return $response;
	}

	/**
	 * POST /admin-search/v1/reindex handler.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function route_reindex( WP_REST_Request $request ) {
		$payload = AS_Indexer::rebuild();
		$stats   = array(
			'last_built_at' => current_time( 'mysql' ),
			'counts'        => $payload['counts'],
			'total'         => $payload['total'],
		);
		return new WP_REST_Response( $stats, 200 );
	}

	/**
	 * GET /admin-search/v1/stats handler.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function route_stats( WP_REST_Request $request ) {
		$stats = AS_Indexer::get_stats();
		// Always coerce shape: missing keys -> defaults so consumers don't have
		// to null-check.
		$out = array(
			'last_built_at' => isset( $stats['last_built_at'] ) ? (string) $stats['last_built_at'] : '',
			'counts'        => array(
				'settings' => isset( $stats['counts']['settings'] ) ? (int) $stats['counts']['settings'] : 0,
				'users'    => isset( $stats['counts']['users'] ) ? (int) $stats['counts']['users'] : 0,
				'products' => isset( $stats['counts']['products'] ) ? (int) $stats['counts']['products'] : 0,
				'content'  => isset( $stats['counts']['content'] ) ? (int) $stats['counts']['content'] : 0,
			),
			'total'         => isset( $stats['total'] ) ? (int) $stats['total'] : 0,
		);
		return new WP_REST_Response( $out, 200 );
	}
}