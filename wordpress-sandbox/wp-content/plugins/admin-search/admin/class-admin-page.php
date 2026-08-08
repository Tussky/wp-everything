<?php
/**
 * AS_Admin_Page — admin page + admin-bar search UI shell.
 *
 * Registers the plugin admin page under Tools → Admin Search, adds a
 * magnifier search node to the admin bar, enqueues the vanilla CSS/JS
 * assets, and localizes the JS boot contract (restUrl + nonce + strings).
 *
 * UI is assembled client-side by `assets/admin-search.js`; this class only
 * provides the page container, asset wires, and the localized contract per
 * the IA-47 plan (Component 5 / Component 6).
 *
 * @package AdminSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AS_Admin_Page {

	const MENU_SLUG   = 'admin-search';
	const ADMIN_BAR_ID = 'as-admin-search';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'register_admin_bar_node' ), 90 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Page slug for the Tools submenu.
	 *
	 * Matches the admin bar node slug so the same localize/asset path is
	 * used on every admin screen.
	 */
	public static function page_slug() {
		return 'tools.php?page=' . self::MENU_SLUG;
	}

	/**
	 * Admin menu: Tools → Admin Search.
	 */
	public static function register_menu() {
		add_submenu_page(
			'tools.php',
			__( 'Admin Search', 'admin-search' ),
			__( 'Admin Search', 'admin-search' ),
			'manage_options',
			self::MENU_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Admin bar magnifier node. Clicking it opens the search modal
	 * (client-side) so admins can search from any screen.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 */
	public static function register_admin_bar_node( $wp_admin_bar ) {
		if ( ! is_admin_bar_showing() ) {
			return;
		}

		$wp_admin_bar->add_node(
			array(
				'id'     => self::ADMIN_BAR_ID,
				'parent' => 'top-secondary',
				'title'  => '<span class="ab-icon dashicons dashicons-search" aria-hidden="true"></span><span class="ab-label" aria-hidden="true">Search</span><span class="screen-reader-text">' . esc_html__( 'Search the admin', 'admin-search' ) . '</span>',
				'href'   => admin_url( self::page_slug() ),
				'meta'   => array(
					'title'   => __( 'Search the admin (Ctrl/Cmd+K)', 'admin-search' ),
					'class'   => 'as-admin-bar-node',
					'onclick' => 'return false;', // Handled by JS; never navigate away.
				),
			)
		);
	}

	/**
	 * Enqueue assets on the plugin page and on any admin screen when the
	 * admin bar is showing (the modal needs the data from any page).
	 *
	 * @param string $hook Current page hook.
	 */
	public static function enqueue_assets( $hook ) {
		$is_plugin_page = ( 'tools_page_' . self::MENU_SLUG === $hook );

		if ( ! $is_plugin_page && ! is_admin_bar_showing() ) {
			return;
		}

		wp_enqueue_style(
			'as-admin-search',
			AS_URL . 'admin/assets/admin-search.css',
			array(),
			AS_VERSION
		);

		wp_enqueue_script(
			'as-admin-search',
			AS_URL . 'admin/assets/admin-search.js',
			array(),
			AS_VERSION,
			true
		);

		wp_localize_script(
			'as-admin-search',
			'ASAdminSearch',
			array(
				'restUrl' => esc_url_raw( rest_url( AS_REST_NAMESPACE . '/search' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'adminUrl' => esc_url_raw( admin_url() ),
				'pageUrl' => esc_url_raw( admin_url( self::page_slug() ) ),
				'strings' => array(
					'placeholder' => __( 'Search the admin…', 'admin-search' ),
					'empty'       => __( 'No matches', 'admin-search' ),
					'searching'   => __( 'Searching…', 'admin-search' ),
					'resultsFor'  => __( 'results for', 'admin-search' ),
					'seconds'     => __( 's', 'admin-search' ),
					'seeAll'      => __( 'See all results', 'admin-search' ),
					'error'       => __( 'Search is unavailable right now. Try again in a moment.', 'admin-search' ),
					'types'       => array(
						'settings' => __( 'Settings', 'admin-search' ),
						'user'     => __( 'People', 'admin-search' ),
						'product'  => __( 'Products', 'admin-search' ),
						'content'  => __( 'Content', 'admin-search' ),
					),
				),
			)
		);
	}

	/**
	 * Render the plugin admin page container. The JS mounts the modal/search
	 * into .as-wrap and manages everything after that.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'admin-search' ) );
		}
		?>
		<div class="wrap">
			<h1 class="as-title"><?php esc_html_e( 'Admin Search', 'admin-search' ); ?></h1>
			<div id="as-page-root" class="as-wrap as-page" role="search" aria-label="<?php esc_attr_e( 'Search the admin', 'admin-search' ); ?>">
				<!-- Rendered by admin-search.js -->
			</div>
		</div>
		<?php
	}
}