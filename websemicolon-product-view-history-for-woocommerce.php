<?php
/**
 * Plugin Name: WebSemicolon Product View History for WooCommerce
 * Description: Displays a clean, responsive grid of the products a visitor has recently viewed on your WooCommerce store.
 * Version: 1.0.0
 * Author: Saeed Amini
 * Author URI: https://github.com/saeedamini144
 * Text Domain: websemicolon-product-view-history-for-woocommerce
 * License: GPL-2.0-or-later
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/functions.php';
