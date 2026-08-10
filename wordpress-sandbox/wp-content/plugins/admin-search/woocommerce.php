<?php
/*
 *	WooCommerce integration for Admin Search
 *
 *	This file is loaded unconditionally from admin-search.php, but every piece of
 *	WC-specific logic below is guarded by a runtime check for `function_exists( 'WC' )`
 *	(or `class_exists( 'WooCommerce' )` as a fallback). When WooCommerce is not active
 *	none of the WC code runs — the file is effectively a no-op.
 *
 *	Provided features:
 *	- Auto-registers searchable meta keys for `product`, `shop_order`, and `shop_coupon`
 *	  via the `admin_search_meta_queries` filter.
 *	- Auto-enables those three post types in `post_types` on first activation (or first
 *	  time WC becomes active after Admin Search is installed), so the WC data is
 *	  immediately searchable without manual settings clicks.
 *	- Provides human-readable labels for WC meta keys via the `admin_search_meta_labels`
 *	  filter, e.g. `_regular_price` → "Regular price".
 *
 *	All hooks in this file run at a priority high enough to override the built-in defaults
 *	defined in settings.php, but low enough to be overridden by theme/plugin authors
 *	who need to customise further.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'admin_search_is_woocommerce_active' ) ) {

	/**
	 *	Return true when WooCommerce is loaded and ready.
	 *
	 *	Checks both the procedural `WC()` helper and the class-based `WooCommerce`
	 *	symbol so the integration works across the WC plugin lifecycle (older
	 *	versions, partial loads, mu-plugin variants).
	 */
	function admin_search_is_woocommerce_active() {
		if ( function_exists( 'WC' ) ) {
			return true;
		}
		if ( class_exists( 'WooCommerce' ) ) {
			return true;
		}
		return false;
	}
}

if ( ! function_exists( 'admin_search_woocommerce_meta_keys' ) ) {

	/**
	 *	Return the searchable meta keys grouped by post type.
	 *
	 *	These are the keys that a store admin would reasonably want to search for.
	 *	Internal `_wp_old_*` style keys are intentionally excluded.
	 *
	 *	@return array<string,string[]> post_type => list of meta keys
	 */
	function admin_search_woocommerce_meta_keys() {
		return array(

			// Product meta — covers simple, variable, downloadable, virtual, and external products.
			'product' => array(
				'_sku',
				'_regular_price',
				'_sale_price',
				'_price',
				'_stock_status',
				'_stock',
				'_manage_stock',
				'_backorders',
				'_weight',
				'_length',
				'_width',
				'_height',
				'_product_type',         // simple/variable/external/grouped
				'_product_attributes',   // serialized attribute definitions
				'_visibility',
				'_featured',
				'_virtual',
				'_downloadable',
				'_tax_status',
				'_tax_class',
				'_low_stock_amount',
				'_wc_average_rating',
				'_wc_review_count',
			),

			// Order meta — covers customer billing fields, order totals, payment info.
			'shop_order' => array(
				'_order_number',          // WC sequential order numbers / custom order number
				'_order_number_formatted',
				'_order_key',
				'_order_total',
				'_order_tax',
				'_order_shipping',
				'_cart_hash',
				'_payment_method',
				'_payment_method_title',
				'_transaction_id',
				'_billing_first_name',
				'_billing_last_name',
				'_billing_email',
				'_billing_phone',
				'_billing_company',
				'_billing_address_1',
				'_billing_city',
				'_billing_postcode',
				'_shipping_first_name',
				'_shipping_last_name',
				'_shipping_company',
				'_shipping_address_1',
				'_shipping_city',
				'_shipping_postcode',
				'_customer_user',
			),

			// Coupon meta — covers discount type, amount, usage limits, expiry.
			'shop_coupon' => array(
				'discount_type',
				'coupon_amount',
				'free_shipping',
				'usage_limit',
				'usage_limit_per_user',
				'usage_count',
				'date_expires',
				'minimum_amount',
				'maximum_amount',
				'individual_use',
				'exclude_sale_items',
				'product_ids',
				'exclude_product_ids',
			),

		);
	}
}

if ( ! function_exists( 'admin_search_woocommerce_meta_labels' ) ) {

	/**
	 *	Return human-readable labels for WC meta keys.
	 *
	 *	Returned array is keyed by raw meta key. Keys that are not present fall back to
	 *	the default display (meta key with leading underscore stripped).
	 *
	 *	@return array<string,string> meta_key => label
	 */
	function admin_search_woocommerce_meta_labels() {
		return array(
			// Product
			'_sku'                       => __( 'SKU', 'admin-search' ),
			'_regular_price'             => __( 'Regular price', 'admin-search' ),
			'_sale_price'                => __( 'Sale price', 'admin-search' ),
			'_price'                     => __( 'Price (effective)', 'admin-search' ),
			'_stock_status'              => __( 'Stock status', 'admin-search' ),
			'_stock'                     => __( 'Stock quantity', 'admin-search' ),
			'_manage_stock'              => __( 'Manage stock', 'admin-search' ),
			'_backorders'                => __( 'Backorders', 'admin-search' ),
			'_weight'                    => __( 'Weight', 'admin-search' ),
			'_length'                    => __( 'Length', 'admin-search' ),
			'_width'                     => __( 'Width', 'admin-search' ),
			'_height'                    => __( 'Height', 'admin-search' ),
			'_product_type'              => __( 'Product type', 'admin-search' ),
			'_product_attributes'        => __( 'Product attributes', 'admin-search' ),
			'_visibility'                => __( 'Visibility', 'admin-search' ),
			'_featured'                  => __( 'Featured', 'admin-search' ),
			'_virtual'                   => __( 'Virtual', 'admin-search' ),
			'_downloadable'              => __( 'Downloadable', 'admin-search' ),
			'_tax_status'                => __( 'Tax status', 'admin-search' ),
			'_tax_class'                 => __( 'Tax class', 'admin-search' ),
			'_low_stock_amount'          => __( 'Low stock threshold', 'admin-search' ),
			'_wc_average_rating'         => __( 'Average rating', 'admin-search' ),
			'_wc_review_count'           => __( 'Review count', 'admin-search' ),

			// Order
			'_order_number'              => __( 'Order number', 'admin-search' ),
			'_order_number_formatted'    => __( 'Order number (formatted)', 'admin-search' ),
			'_order_key'                 => __( 'Order key', 'admin-search' ),
			'_order_total'               => __( 'Order total', 'admin-search' ),
			'_order_tax'                 => __( 'Order tax', 'admin-search' ),
			'_order_shipping'            => __( 'Order shipping', 'admin-search' ),
			'_cart_hash'                 => __( 'Cart hash', 'admin-search' ),
			'_payment_method'            => __( 'Payment method', 'admin-search' ),
			'_payment_method_title'      => __( 'Payment method title', 'admin-search' ),
			'_transaction_id'            => __( 'Transaction ID', 'admin-search' ),
			'_billing_first_name'        => __( 'Billing first name', 'admin-search' ),
			'_billing_last_name'         => __( 'Billing last name', 'admin-search' ),
			'_billing_email'             => __( 'Billing email', 'admin-search' ),
			'_billing_phone'             => __( 'Billing phone', 'admin-search' ),
			'_billing_company'           => __( 'Billing company', 'admin-search' ),
			'_billing_address_1'         => __( 'Billing address', 'admin-search' ),
			'_billing_city'              => __( 'Billing city', 'admin-search' ),
			'_billing_postcode'          => __( 'Billing postcode', 'admin-search' ),
			'_shipping_first_name'       => __( 'Shipping first name', 'admin-search' ),
			'_shipping_last_name'        => __( 'Shipping last name', 'admin-search' ),
			'_shipping_company'         => __( 'Shipping company', 'admin-search' ),
			'_shipping_address_1'        => __( 'Shipping address', 'admin-search' ),
			'_shipping_city'             => __( 'Shipping city', 'admin-search' ),
			'_shipping_postcode'         => __( 'Shipping postcode', 'admin-search' ),
			'_customer_user'             => __( 'Customer user ID', 'admin-search' ),

			// Coupon
			'discount_type'              => __( 'Discount type', 'admin-search' ),
			'coupon_amount'              => __( 'Coupon amount', 'admin-search' ),
			'free_shipping'              => __( 'Free shipping', 'admin-search' ),
			'usage_limit'                => __( 'Usage limit', 'admin-search' ),
			'usage_limit_per_user'       => __( 'Usage limit per user', 'admin-search' ),
			'usage_count'                => __( 'Usage count', 'admin-search' ),
			'date_expires'               => __( 'Expiry date', 'admin-search' ),
			'minimum_amount'             => __( 'Minimum spend', 'admin-search' ),
			'maximum_amount'             => __( 'Maximum spend', 'admin-search' ),
			'individual_use'             => __( 'Individual use only', 'admin-search' ),
			'exclude_sale_items'         => __( 'Exclude sale items', 'admin-search' ),
			'product_ids'                => __( 'Applicable products', 'admin-search' ),
			'exclude_product_ids'        => __( 'Excluded products', 'admin-search' ),
		);
	}
}


/*
 *	───────────────────────────────────────────────────────────────────────────
 *	Below this line every block is gated on WooCommerce being active. The
 *	guards are evaluated at hook-fire time, not at load time, so sites that
 *	activate WC after Admin Search will pick the integration up automatically
 *	on the next request without any re-init step.
 *	───────────────────────────────────────────────────────────────────────────
 */


/*
 *	Auto-enable WC post types on first detection of WooCommerce.
 *
 *	Runs once per request on `admin_init` at priority 5 (before the default
 *	priority 10 settings initialisation). The "seen" flag is stored in a
 *	transient so it survives between requests but can be cleared if WC is
 *	deactivated/reactivated.
 */
add_action( 'admin_init', function() {

	if ( ! admin_search_is_woocommerce_active() ) {
		return;
	}

	if ( get_transient( 'admin_search_woocommerce_autoconfigured' ) ) {
		return;
	}

	$settings = get_option( 'admin_search_settings', array() );

	if ( ! is_array( $settings ) ) {
		$settings = array();
	}

	if ( ! isset( $settings['post_types'] ) || ! is_array( $settings['post_types'] ) ) {
		$settings['post_types'] = array();
	}

	$wc_post_types = array( 'product', 'shop_order', 'shop_coupon' );
	$changed       = false;

	foreach ( $wc_post_types as $slug ) {
		if ( ! post_type_exists( $slug ) ) {
			continue;
		}

		// Skip if the post type is already configured (user explicit choice).
		if ( array_key_exists( $slug, $settings['post_types'] ) ) {
			continue;
		}

		// Auto-enable with the same default field set Admin Search uses for posts/pages:
		// body only. SKU is added by the meta_queries hook below.
		$settings['post_types'][ $slug ] = array( 'body' );
		$changed = true;
	}

	if ( $changed ) {
		update_option( 'admin_search_settings', $settings );
	}

	// 1-day transient — long enough that re-init isn't constant, short enough
	// to recover quickly if WC is swapped out.
	set_transient( 'admin_search_woocommerce_autoconfigured', 1, DAY_IN_SECONDS );

}, 5 );


/*
 *	Register WC meta keys as searchable for each post type.
 *
 *	Priority 100 matches the existing settings.php filters. Both filters run at the
 *	same priority; WordPress invokes them in the order they were registered (this
 *	file is loaded last, so this hook runs after the settings.php one). The settings.php
 *	hook short-circuits when the post type has user-configured settings, so for WC post
 *	types with saved settings only the user's chosen meta keys are returned — the WC
 *	keys are still discoverable in the settings UI because they are present in the
 *	postmeta table once WC creates its first product/order/coupon.
 */
add_filter( 'admin_search_meta_queries', function( $fields, $post_type ) {

	if ( ! admin_search_is_woocommerce_active() ) {
		return $fields;
	}

	$wc_meta = admin_search_woocommerce_meta_keys();

	if ( ! isset( $wc_meta[ $post_type ] ) ) {
		return $fields;
	}

	if ( ! is_array( $fields ) ) {
		$fields = array();
	}

	$fields = array_values( array_unique( array_merge( $fields, $wc_meta[ $post_type ] ) ) );

	return $fields;

}, 100, 2 );


/*
 *	Register human-readable labels for WC meta keys.
 *
 *	The filter accepts a map of meta_key => label. settings.php uses this
 *	map (when present) to replace the default "meta key with leading _ stripped"
 *	display. Keys not in the map fall back to the default display.
 */
add_filter( 'admin_search_meta_labels', function( $labels ) {

	if ( ! admin_search_is_woocommerce_active() ) {
		return $labels;
	}

	if ( ! is_array( $labels ) ) {
		$labels = array();
	}

	return array_merge( $labels, admin_search_woocommerce_meta_labels() );

} );