<?php
/**
 * Plugin Name: WebSemicolon Recently Viewed Products for WooCommerce – Price Drop & Back in Stock Badges
 * Description: Show recently viewed products to WooCommerce shoppers, with price drop and back in stock badges. Cache compatible. Block, shortcode, RTL.
 * Version: 1.0.1
 * Author: Saeed Amini
 * Author URI: https://github.com/saeedamini144
 * Text Domain: websemicolon-product-view-history-for-woocommerce
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WEBSEMICOLON_PVH_VERSION', '1.0.1' );
define( 'WEBSEMICOLON_PVH_FILE', __FILE__ );
define( 'WEBSEMICOLON_PVH_PATH', plugin_dir_path( __FILE__ ) );
define( 'WEBSEMICOLON_PVH_URL', plugin_dir_url( __FILE__ ) );

// The plugin does not read or write orders, so it is compatible with High-Performance Order Storage.
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

require_once WEBSEMICOLON_PVH_PATH . 'includes/functions.php';
