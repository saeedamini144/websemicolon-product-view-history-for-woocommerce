=== WebSemicolon Product View History for WooCommerce ===
Contributors: saeedamini144
Donate link: https://github.com/saeedamini144
Tags: woocommerce, products, recently viewed, shortcode
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later

Displays a clean, responsive grid of the products a visitor has recently viewed on a WooCommerce store.

== Description ==
WebSemicolon Product View History for WooCommerce is a lightweight plugin that tracks the products a visitor views and displays them in a polished product grid using the shortcode [product_view_history_for_woocommerce].

It helps increase engagement and encourage repeat purchases by reminding visitors of products they have already explored. The plugin is fully responsive, works with most themes, and can be customized from the WordPress admin area.

== Installation ==
1. Upload the plugin files to the /wp-content/plugins/websemicolon-product-view-history-for-woocommerce directory.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Place the shortcode [product_view_history_for_woocommerce] on any page, post, or template where you want the product view history section to appear.

== Usage ==
Use the default shortcode to display the section:

[product_view_history_for_woocommerce]

You can also customize the output with shortcode attributes:

[product_view_history_for_woocommerce limit="6" title="Recently Seen" show_price="no"]

== Frequently Asked Questions ==

= Does this work without WooCommerce? =
No. The plugin requires WooCommerce to be active.

= Can I change the number of products shown? =
Yes. You can set the number of products from the plugin settings screen or use the limit attribute in the shortcode.

= Can I hide the product price? =
Yes. Use show_price="no" in the shortcode.

== Changelog ==
= 1.0.0 =
* Added advanced admin settings for title, price display, and number of products.
* Increased maximum supported display count to 10.
* Improved mobile responsiveness for two-column layout.
