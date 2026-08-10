<?php
/**
 * Admin UI
 *
 * Registers the Tools > wp->search page, admin bar node, search modal assets.
 *
 * @package WP_Search
 */

namespace WP_Search;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin page controller.
 *
 * @since 1.0.0
 */
class Admin {

	/**
	 * Slug for the admin page.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const PAGE_SLUG = 'wp-search';

	/**
	 * Capability required to view the page.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Initialize admin hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_tools_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_node' ), 100 );
	}

	/**
	 * Register the Tools submenu page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_tools_page(): void {
		add_management_page(
			__( 'wp->search', 'wp-search' ),
			__( 'wp->search', 'wp-search' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue modal assets on all admin pages.
	 *
	 * @since 1.0.0
	 * @param string $_hook_suffix The current admin page.
	 * @return void
	 */
	public function enqueue_assets( string $_hook_suffix ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		if ( ! is_admin() ) {
			return;
		}

		wp_enqueue_style(
			'wp-search-modal',
			WP_SEARCH_PLUGIN_URL . 'assets/dist/wp-search-modal.css',
			array(),
			WP_SEARCH_VERSION
		);

		wp_enqueue_script(
			'wp-search-modal',
			WP_SEARCH_PLUGIN_URL . 'assets/dist/wp-search-modal.js',
			array(),
			WP_SEARCH_VERSION,
			true
		);

		wp_localize_script(
			'wp-search-modal',
			'wp_search_modal_config',
			array(
				'rest_url'   => rest_url( REST_Controller::NAMESPACE . REST_Controller::ROUTE ),
				'rest_nonce' => wp_create_nonce( 'wp_rest' ),
				'strings'    => array(
					'placeholder' => __( 'Search settings, content, products…', 'wp-search' ),
					'loading'     => __( 'Loading…', 'wp-search' ),
					'empty'       => __( 'No results found.', 'wp-search' ),
					'error'       => __( 'Something went wrong. Please try again.', 'wp-search' ),
				),
			)
		);
	}

	/**
	 * Add a search icon to the WordPress admin bar.
	 *
	 * @since 1.0.0
	 * @param \WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 * @return void
	 */
	public function add_admin_bar_node( \WP_Admin_Bar $wp_admin_bar ): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$wp_admin_bar->add_node(
			array(
				'id'     => 'wp-search',
				'parent' => 'top-secondary',
				'title'  => '<span class="ab-icon dashicons dashicons-search"></span><span class="ab-label">' . esc_html__( 'Search', 'wp-search' ) . '</span>',
				'href'   => '#',
				'meta'   => array(
					'title' => esc_attr__( 'Open search (Ctrl+K)', 'wp-search' ),
					'class' => 'wp-search-admin-bar-node',
				),
			)
		);
	}

	/**
	 * Render the admin page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<div class="wp-search-tools-trigger" data-wp-search-trigger role="button" tabindex="0" aria-label="<?php esc_attr_e( 'Open search', 'wp-search' ); ?>">
				<span class="wp-search-tools-trigger__icon" aria-hidden="true">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
				</span>
				<span class="wp-search-tools-trigger__text"><?php esc_html_e( 'Search settings, content, products…', 'wp-search' ); ?></span>
				<span class="wp-search-tools-trigger__shortcut"><kbd>Ctrl</kbd><kbd>K</kbd></span>
			</div>

			<form method="get" action="<?php echo esc_url( admin_url( 'tools.php' ) ); ?>">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
				<?php wp_nonce_field( 'wp_search_reindex', 'wp_search_reindex_nonce' ); ?>
				<p>
					<?php submit_button( __( 'Reindex Settings', 'wp-search' ), 'secondary', 'wp_search_reindex', false ); ?>
				</p>
			</form>
		</div>
		<?php
	}
}
