<?php
/**
 * Removes plugin data when the plugin is deleted.
 *
 * @package WebSemicolon_Product_View_History_For_WooCommerce
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'product_view_history_for_woocommerce_display_count' );
delete_option( 'product_view_history_for_woocommerce_title' );
delete_option( 'product_view_history_for_woocommerce_show_price' );
delete_option( 'product_view_history_for_woocommerce_show_badges' );
delete_option( 'product_view_history_for_woocommerce_placements' );
delete_option( 'product_view_history_for_woocommerce_style' );
delete_option( 'product_view_history_for_woocommerce_layout' );
delete_option( 'product_view_history_for_woocommerce_add_to_cart' );
delete_option( 'product_view_history_for_woocommerce_sync_users' );

// Customer histories and the anonymous per-product view counters.
delete_metadata( 'user', 0, 'product_view_history_for_woocommerce_items', '', true );
delete_post_meta_by_key( '_pvh_views' );
delete_post_meta_by_key( '_pvh_sales_base' );
