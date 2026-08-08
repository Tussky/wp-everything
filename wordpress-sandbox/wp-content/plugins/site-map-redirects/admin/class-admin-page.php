<?php
/**
 * Module C (PHP shell) — Admin page registration and asset enqueue.
 *
 * Registers the top-level "SiteMap Redirects" admin menu, enqueues the JS/CSS
 * bundle from assets/dist/, and passes the JS boot contract
 * (window.SiteMapRedirects) via wp_localize_script per IA-6 plan section 2.5.
 *
 * The interactive tree/graph rendering is owned by the JS bundle (T5+); this
 * shell only provides the page container, the asset wires, and the localized
 * contract. All user-facing strings come from the `labels` array so the UI
 * can be built before final copy is settled.
 *
 * Error handling contract:
 *   - The admin notice for "last error" is rendered server-side using
 *     SMR_Logger::get_last_error(). The notice is dismissable per-user.
 *   - The JS bundle receives a copy of `last_error` and the
 *     `errorMessages` map so it can render a friendly error state without
 *     having to translate error codes itself.
 *
 * @package SiteMapRedirects
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin page registration and asset enqueue.
 *
 * @package SiteMapRedirects
 */
class SMR_Admin_Page {

	/**
	 * User meta key used to dismiss the "last error" admin notice.
	 *
	 * @var string
	 */
	const NOTICE_DISMISS_KEY = 'smr_dismiss_last_error';

	/**
	 * Wire admin hooks (menu + asset enqueue).
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'maybe_render_error_notice' ) );
		add_action( 'admin_post_smr_dismiss_last_error', array( __CLASS__, 'handle_dismiss' ) );
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
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_assets( $hook ) {
		if ( 'toplevel_page_site-map-redirects' !== $hook ) {
			return;
		}

		// Enqueue D3.js from CDN for tree rendering.
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

		// JS boot contract (IA-6 plan section 2.5). Labels are placeholders; Isaac
		// can change any of them later (reversible).
		wp_localize_script(
			'smr-admin',
			'SiteMapRedirects',
			array(
				'restUrl'       => esc_url_raw( rest_url( SMR_REST_NAMESPACE ) . '/' ),
				'nonce'         => wp_create_nonce( 'wp_rest' ),
				'adminUrl'      => esc_url_raw( admin_url( 'admin-post.php' ) ),
				'labels'        => self::labels(),
				'errorMessages' => self::error_messages(),
				'lastError'     => SMR_Logger::get_last_error(),
			)
		);
	}

	/**
	 * Render a dismissable admin notice when the plugin has a recorded
	 * "last error". Server-side rendering means the notice appears even if
	 * the JS bundle fails to load.
	 */
	public static function maybe_render_error_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		// Only show on our admin page so we don't spam other screens.
		if ( ! $screen || 'toplevel_page_site-map-redirects' !== $screen->id ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$dismissed = (int) get_user_meta( get_current_user_id(), self::NOTICE_DISMISS_KEY, true );
		$error     = SMR_Logger::get_last_error();
		if ( ! is_array( $error ) || empty( $error['code'] ) ) {
			return;
		}
		// Skip dismissed-by-this-user entries.
		if ( $dismissed && isset( $error['time'] ) && strtotime( $error['time'] ) <= $dismissed ) {
			return;
		}

		$message = isset( $error['message'] ) ? $error['message'] : __( 'SiteMap Redirects encountered an error.', 'site-map-redirects' );
		$code    = isset( $error['code'] ) ? $error['code'] : '';
		$time    = isset( $error['time'] ) ? $error['time'] : '';
		$dismiss = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'smr_dismiss_last_error',
				),
				admin_url( 'admin-post.php' )
			),
			'smr_dismiss_last_error'
		);
		?>
		<div class="notice notice-warning is-dismissible smr-error-notice">
			<p>
				<strong><?php esc_html_e( 'SiteMap Redirects:', 'site-map-redirects' ); ?></strong>
				<?php echo esc_html( $message ); ?>
			</p>
			<?php if ( $code || $time ) : ?>
				<p class="description">
					<?php if ( $code ) : ?>
						<code><?php echo esc_html( $code ); ?></code>
					<?php endif; ?>
					<?php if ( $time ) : ?>
						<?php echo esc_html( sprintf( __( 'at %s', 'site-map-redirects' ), $time ) ); ?>
					<?php endif; ?>
				</p>
			<?php endif; ?>
			<p>
				<a href="<?php echo esc_url( $dismiss ); ?>" class="button">
					<?php esc_html_e( 'Dismiss for now', 'site-map-redirects' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Handle the admin-post dismissal.
	 */
	public static function handle_dismiss() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'site-map-redirects' ) );
		}
		check_admin_referer( 'smr_dismiss_last_error' );
		update_user_meta( get_current_user_id(), self::NOTICE_DISMISS_KEY, time() );
		wp_safe_redirect( remove_query_arg( array( 'action', '_wpnonce' ), wp_get_referer() ) );
		exit;
	}

	/**
	 * Localized label set. Placeholder copy — Isaac may override any string.
	 *
	 * @return array Map of label id => translated string.
	 */
	protected static function labels() {
		return array(
			'title'         => __( 'Site Map', 'site-map-redirects' ),
			'subtitle'      => __( 'A visual tree map of every page on your site, with redirects shown in the order they actually run.', 'site-map-redirects' ),
			'reindexButton' => __( 'Re-index', 'site-map-redirects' ),
			'noData'        => __( 'No redirects found.', 'site-map-redirects' ),
			'legend'        => __( 'Legend', 'site-map-redirects' ),
			'redirects'     => __( 'Redirects', 'site-map-redirects' ),
			'status301'     => __( 'Permanent Redirect (301)', 'site-map-redirects' ),
			'status302'     => __( 'Temporary Redirect (302)', 'site-map-redirects' ),
			'status307'     => __( 'Temporary Redirect (307)', 'site-map-redirects' ),
			'status308'     => __( 'Permanent Redirect (308)', 'site-map-redirects' ),
			'statusUnknown' => __( 'Unknown Redirect', 'site-map-redirects' ),
			'whyTooltip'    => __( 'Because: {source} — rule #{priority}', 'site-map-redirects' ),
			'nodeTypes'     => array(
				'front'   => __( 'Homepage', 'site-map-redirects' ),
				'page'    => __( 'Page', 'site-map-redirects' ),
				'post'    => __( 'Post', 'site-map-redirects' ),
				'cpt'     => __( 'Custom Post', 'site-map-redirects' ),
				'tax'     => __( 'Category / Tag', 'site-map-redirects' ),
				'archive' => __( 'Archive', 'site-map-redirects' ),
			),
			'loading'       => __( 'Loading site map…', 'site-map-redirects' ),
			'errorLoading'  => __( 'We couldn\'t load the site map. Please try again, or reindex.', 'site-map-redirects' ),
			'errorRetry'    => __( 'Retry', 'site-map-redirects' ),
		);
	}

	/**
	 * Map of error codes → user-facing messages, shipped to the JS bundle
	 * so it can render a friendly error state without translating codes.
	 *
	 * @return array Map of error code => translated string.
	 */
	protected static function error_messages() {
		return array(
			SMR_Indexer::ERR_PIPELINE     => __( 'The site map could not be built. Please try again, or reindex.', 'site-map-redirects' ),
			SMR_Indexer::ERR_CACHE_WRITE  => __( 'Could not save the site map cache. The next page load will try again.', 'site-map-redirects' ),
			SMR_Indexer::ERR_EMPTY        => __( 'No pages were found while building the site map.', 'site-map-redirects' ),
			SMR_Redirect_Resolver::ERR_DISCOVERY => __( 'Redirect rules could not be loaded. The site map will still render.', 'site-map-redirects' ),
			SMR_Redirect_Resolver::ERR_CACHE     => __( 'Could not cache redirect rules. The plugin will keep working without a redirect cache.', 'site-map-redirects' ),
			SMR_REST::ERR_FORBIDDEN       => __( 'You do not have permission to do that.', 'site-map-redirects' ),
			SMR_REST::ERR_INTERNAL        => __( 'The site map could not be assembled. Please try again, or reindex.', 'site-map-redirects' ),
			SMR_REST::ERR_PERM_CHECK      => __( 'Could not verify your permissions. Please reload the page.', 'site-map-redirects' ),
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
			<div class="smr-error" style="display: none;" role="alert" aria-live="assertive">
				<p class="smr-error-message">
					<?php esc_html_e( 'We couldn\'t load the site map. Please try again, or reindex.', 'site-map-redirects' ); ?>
				</p>
				<button class="smr-btn smr-retry">
					<?php esc_html_e( 'Retry', 'site-map-redirects' ); ?>
				</button>
			</div>
		</div>
		<?php
	}
}