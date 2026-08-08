<?php
/**
 * Admin UI: top-level menu page that renders the interactive tree.
 *
 * @package SiteMapRedirects
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SMR_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function menu() {
		add_menu_page(
			__( 'SiteMap Redirects', 'site-map-redirects' ),
			__( 'SiteMap Redirects', 'site-map-redirects' ),
			'manage_options',
			'site-map-redirects',
			array( __CLASS__, 'render' ),
			'dashicons-networking',
			76
		);
	}

	public static function assets( $hook ) {
		if ( 'toplevel_page_site-map-redirects' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'smr-admin', SMR_URL . 'assets/admin.css', array(), SMR_VERSION );
		wp_enqueue_script( 'smr-admin', SMR_URL . 'assets/admin.js', array(), SMR_VERSION, true );

		// D3 from CDN (no build tooling required).
		wp_enqueue_script( 'd3', 'https://cdn.jsdelivr.net/npm/d3@7/dist/d3.min.js', array(), '7.9.0', true );

		wp_localize_script(
			'smr-admin',
			'SMR',
			array(
				'root'        => esc_url_raw( rest_url( self::NS() ) ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'last_index'  => get_option( 'smr_last_index', '' ),
				'i18n'        => array(
					'reindex'      => __( 'Re-index site', 'site-map-redirects' ),
					'reindexing'   => __( 'Re-indexing…', 'site-map-redirects' ),
					'loading'      => __( 'Loading site map…', 'site-map-redirects' ),
					'no_redirects' => __( 'No redirects on this page.', 'site-map-redirects' ),
					'expand'       => __( 'Expand', 'site-map-redirects' ),
					'collapse'     => __( 'Collapse', 'site-map-redirects' ),
					'priority'     => __( 'Priority order', 'site-map-redirects' ),
					'status'       => __( 'HTTP status', 'site-map-redirects' ),
					'destination'  => __( 'Destination', 'site-map-redirects' ),
					'source'       => __( 'Why does this redirect happen?', 'site-map-redirects' ),
					'open_page'    => __( 'Open this page', 'site-map-redirects' ),
					'edit_page'    => __( 'Edit this page', 'site-map-redirects' ),
					'legend'       => __( 'Legend', 'site-map-redirects' ),
					'perm'         => __( 'Permanent redirect', 'site-map-redirects' ),
					'temp'         => __( 'Temporary redirect', 'site-map-redirects' ),
					'core'         => __( 'WordPress core redirects happen automatically and apply to every URL (e.g. trailing-slash fixes). They run after plugin and .htaccess rules.', 'site-map-redirects' ),
				),
			)
		);
	}

	protected static function NS() {
		return SMR_REST_NAMESPACE;
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'site-map-redirects' ) );
		}
		?>
		<div class="wrap smr-wrap">
			<h1 class="smr-title">
				<span class="dashicons dashicons-networking"></span>
				<?php esc_html_e( 'SiteMap Redirects', 'site-map-redirects' ); ?>
			</h1>
			<p class="smr-subtitle">
				<?php esc_html_e( 'A visual tree map of every page on your site, with redirects shown in the order they actually run.', 'site-map-redirects' ); ?>
			</p>

			<div class="smr-toolbar">
				<button type="button" class="button button-primary" id="smr-reindex">
					<span class="dashicons dashicons-update"></span>
					<?php esc_html_e( 'Re-index site', 'site-map-redirects' ); ?>
				</button>
				<span class="smr-last-index" id="smr-last-index">
					<?php
					$last = get_option( 'smr_last_index', '' );
					if ( $last ) {
						echo esc_html( sprintf( __( 'Last indexed: %s', 'site-map-redirects' ), $last ) );
					} else {
						esc_html_e( 'Not indexed yet.', 'site-map-redirects' );
					}
					?>
				</span>
				<span class="smr-counts" id="smr-counts"></span>
			</div>

			<div class="smr-layout">
				<div class="smr-tree-panel" id="smr-tree-panel">
					<div class="smr-loading"><?php esc_html_e( 'Loading site map…', 'site-map-redirects' ); ?></div>
					<svg id="smr-tree" width="100%" height="600" aria-label="<?php esc_attr_e( 'Site map tree', 'site-map-redirects' ); ?>"></svg>
				</div>
				<aside class="smr-detail-panel" id="smr-detail-panel">
					<div class="smr-detail-empty">
						<span class="dashicons dashicons-arrow-left-alt"></span>
						<p><?php esc_html_e( 'Click a page in the tree to see its redirects, priority order, and destination.', 'site-map-redirects' ); ?></p>
					</div>
				</aside>
			</div>

			<div class="smr-legend" id="smr-legend"></div>
		</div>
		<?php
	}
}
