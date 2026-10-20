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
