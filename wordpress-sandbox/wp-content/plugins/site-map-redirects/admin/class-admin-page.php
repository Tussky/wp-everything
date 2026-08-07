<?php
/**
 * Module C (PHP shell) — Admin page registration and asset enqueue.
 *
 * Registers the top-level "SiteMap Redirects" admin menu, enqueues the JS/CSS
 * bundle from assets/dist/, and passes the JS boot contract
 * (window.SiteMapRedirects) via wp_localize_script per IA-6 plan §2.5.
 *
 * The interactive tree/graph rendering is owned by the JS bundle (T5+); this
 * shell only provides the page container, the asset wires, and the localized
 * contract. All user-facing strings come from the `labels` object so the UI
 * can be built before final copy is settled.
 *
 * @package SiteMapRedirects
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SMR_Admin_Page {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Top-level admin menu item.
	 */
	public static function register_menu() {
		add_menu_page(
			__( 'SiteMap Redirects', 'site-map-redirects' ),
			__( 'SiteMap Redirects', 'site-map-redirects' ),
			'manage_options',
			'site-map-redirects',
			array( __CLASS__, 'render_page' ),
			'dashicons-networking',
			76
		);
	}

	/**
 * Enqueue the JS/CSS bundle only on the plugin's admin page.
 */
public static function enqueue_assets( $hook ) {
	if ( 'toplevel_page_site-map-redirects' !== $hook ) {
		return;
	}

	// Enqueue D3.js from CDN for tree rendering
	wp_enqueue_script(
		'smr-d3',
		'https://d3js.org/d3.v7.min.js',
		array(),
		'7.0.0',
		true
	);

	wp_enqueue_style(
		'smr-admin',
		SMR_URL . 'assets/dist/admin.css',
		array(),
		SMR_VERSION
	);

	wp_enqueue_script(
		'smr-admin',
		SMR_URL . 'assets/dist/admin.js',
		array( 'smr-d3' ),
		SMR_VERSION,
		true
	);

	// JS boot contract (IA-6 plan §2.5). Labels are placeholders; Isaac
	// can change any of them later (reversible).
	wp_localize_script(
		'smr-admin',
		'SiteMapRedirects',
		array(
			'restUrl'  => esc_url_raw( rest_url( SMR_REST_NAMESPACE ) . '/' ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'adminUrl' => esc_url_raw( admin_url( 'admin-post.php' ) ),
			'labels'   => self::labels(),
		)
	);
}

	/**
	 * Localized label set. Placeholder copy — Isaac may override any string.
	 */
	protected static function labels() {
		return array(
			'title'           => __( 'Site Map', 'site-map-redirects' ),
			'subtitle'        => __( 'A visual tree map of every page on your site, with redirects shown in the order they actually run.', 'site-map-redirects' ),
			'reindexButton'   => __( 'Re-index', 'site-map-redirects' ),
			'noData'          => __( 'No redirects found.', 'site-map-redirects' ),
			'legend'          => __( 'Legend', 'site-map-redirects' ),
			'redirects'       => __( 'Redirects', 'site-map-redirects' ),
			'status301'       => __( 'Permanent Redirect (301)', 'site-map-redirects' ),
			'status302'       => __( 'Temporary Redirect (302)', 'site-map-redirects' ),
			'status307'       => __( 'Temporary Redirect (307)', 'site-map-redirects' ),
			'status308'       => __( 'Permanent Redirect (308)', 'site-map-redirects' ),
			'statusUnknown'   => __( 'Unknown Redirect', 'site-map-redirects' ),
			'whyTooltip'      => __( 'Because: {source} — rule #{priority}', 'site-map-redirects' ),
			'nodeTypes'       => array(
				'front'   => __( 'Homepage', 'site-map-redirects' ),
				'page'    => __( 'Page', 'site-map-redirects' ),
				'post'    => __( 'Post', 'site-map-redirects' ),
				'cpt'     => __( 'Custom Post', 'site-map-redirects' ),
				'tax'     => __( 'Category / Tag', 'site-map-redirects' ),
				'archive' => __( 'Archive', 'site-map-redirects' ),
			),
		);
	}

	/**
	 * Render the admin page container. The JS bundle mounts into #smr-app.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'site-map-redirects' ) );
		}
		?>
		<div class="wrap" id="smr-app">
			<h1 class="smr-title">
				<span class="dashicons dashicons-networking"></span>
				<?php esc_html_e( 'SiteMap Redirects', 'site-map-redirects' ); ?>
			</h1>
			<p class="smr-subtitle">
				<?php esc_html_e( 'A visual tree map of every page on your site, with redirects shown in the order they actually run.', 'site-map-redirects' ); ?>
			</p>

			<!-- Toolbar -->
			<div class="smr-toolbar">
				<h2>
					<?php echo esc_html( self::labels()['title'] ); ?>
				</h2>
				<div class="smr-toolbar-actions">
					<button id="smr-reindex" class="smr-btn smr-btn-primary">
						<i><?php esc_html_e( 'Re-index', 'site-map-redirects' ); ?></i>
					</button>
				</div>
			</div>

			<!-- Loading State -->
			<div class="smr-loading">
				<?php esc_html_e( 'Loading site map…', 'site-map-redirects' ); ?>
			</div>

			<!-- Error State -->
			<div class="smr-error" style="display: none;">
				<?php esc_html_e( 'Error loading site map.', 'site-map-redirects' ); ?>
			</div>
		</div>
		<?php
	}
}