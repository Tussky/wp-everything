<?php
/**
 * Test bootstrap file for wp-search plugin
 *
 * Loads composer autoloader + plugin classes and stubs common WordPress
 * globals so unit tests can run without a full WordPress bootstrap.
 *
 * @package WP_Search
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . DIRECTORY_SEPARATOR );
}

// Minimal WordPress helper stubs needed before constants/classes load.
if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $value ) {
		return rtrim( $value, '/\\' ) . '/';
	}
}
if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( $file ) {
		return trailingslashit( dirname( $file ) );
	}
}
if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( $file ) {
		return 'https://example.com/wp-content/plugins/wp-search/';
	}
}

if ( ! defined( 'WP_SEARCH_VERSION' ) ) {
	define( 'WP_SEARCH_VERSION', '1.0.0' );
}

if ( ! defined( 'WP_SEARCH_PLUGIN_FILE' ) ) {
	define( 'WP_SEARCH_PLUGIN_FILE', dirname( __DIR__ ) . '/wp-search.php' );
}

if ( ! defined( 'WP_SEARCH_PLUGIN_DIR' ) ) {
	define( 'WP_SEARCH_PLUGIN_DIR', plugin_dir_path( WP_SEARCH_PLUGIN_FILE ) );
}

if ( ! defined( 'WP_SEARCH_PLUGIN_URL' ) ) {
	define( 'WP_SEARCH_PLUGIN_URL', plugin_dir_url( WP_SEARCH_PLUGIN_FILE ) );
}

// Load Composer autoloader.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Minimal WordPress stubs used by the plugin classes and tests.
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private string $code;
		private string $message;
		private array $data;

		public function __construct( $code, $message, $data = array() ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_message() {
			return $this->message;
		}

		public function get_error_data() {
			return $this->data;
		}
	}
}

if ( ! class_exists( 'WP_REST_Server' ) ) {
	class WP_REST_Server {
		const READABLE  = 'GET';
		const CREATABLE = 'POST';
	}
}

// Load core plugin classes.
$includes = array(
	'class-indexer.php',
	'class-settings-indexer.php',
	'class-users-indexer.php',
	'class-plugins-indexer.php',
	'class-menus-indexer.php',
	'class-posts-indexer.php',
	'class-products-indexer.php',
	'class-rest-controller.php',
);

foreach ( $includes as $file ) {
	require_once WP_SEARCH_PLUGIN_DIR . 'includes/' . $file;
}
