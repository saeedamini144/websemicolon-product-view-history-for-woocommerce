=== WebSemicolon Recently Viewed Products for WooCommerce – Price Drop & Back in Stock Badges ===
Contributors: saeedamini144
Donate link: https://github.com/saeedamini144
Tags: recently viewed products, woocommerce recently viewed, recently viewed, price drop, back in stock
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Show recently viewed products to WooCommerce shoppers, with price drop and back in stock badges. Cache compatible. Block, shortcode, RTL.

== Description ==

**WebSemicolon Recently Viewed Products for WooCommerce** shows every shopper the products they recently viewed in your store, and tells them when something changed since their visit: the price dropped, the product is back in stock, or stock is running low. That gives visitors a concrete reason to return to a product instead of just seeing a list of thumbnails.

The history belongs to the visitor and is stored in their own browser cookie. Nothing is written to your database, no accounts are required, and no data is sent to third parties.

= Features =

* **Change badges.** When a visitor returns to the list, each product can show what happened since they viewed it:
    * *Price dropped* (with the percentage when it is 1% or more)
    * *Back in stock*
    * *Only N left* (uses your WooCommerce low-stock threshold)
    * *Out of stock*
* **Works with page caching.** Views are recorded and the list is loaded in the browser through a small AJAX request, so the page HTML is identical for every visitor and can be cached by any page cache or CDN. A plugin that builds the list while the page is generated cannot do this, because cached pages skip PHP entirely and the view is never recorded.
* **Three ways to display it.**
    * A **"Recently Viewed Products" block** for the block editor, with settings in the sidebar.
    * The **`[product_view_history_for_woocommerce]` shortcode** for classic editors, page builders and templates.
    * **Automatic placement** with no shortcode needed: below the product details on single product pages, on an empty cart page, on a shop page with no results, and on the order received (thank you) page.
* **Always the newest first.** Viewing a product again moves it back to the front of the list.
* **Hides what should not be shown.** The product currently being viewed is left out of the list, and products set to "hidden" in catalog visibility are skipped. Products without an image use the WooCommerce placeholder image.
* **Responsive grid.** 5 columns on desktop, 3 on tablets and 2 on phones.
* **RTL ready.** The layout uses logical CSS properties, so it mirrors correctly on right-to-left sites. A complete Persian (fa_IR) translation is included, also for the block editor.
* **Lightweight and clean.** One small script and a few lines of inline CSS are loaded only on pages that show the list or on single product pages (to record the view). No custom database tables, no external requests, and all settings are removed when the plugin is deleted.
* **Compatible with High-Performance Order Storage (HPOS).**

= How it works =

1. A visitor opens a product page. The plugin records the product, together with its price and stock status at that moment, in a cookie (up to 10 products, kept for 30 days).
2. On any page that shows the list, the plugin asks the server for the visitor's products, compares each one with how it looked at the time of the visit and builds the grid.
3. If a product changed in a way worth mentioning, one badge is shown on its card. Badge priority: back in stock, price dropped, out of stock, low stock.

= Settings =

Open **View History** in the admin menu to set the default number of products (1 to 10), the section title, whether to show prices, whether to show change badges, and where to display the list automatically. Every default can be overridden per block or per shortcode.

== Installation ==

1. Make sure WooCommerce is installed and active.
2. Upload the plugin folder to `/wp-content/plugins/`, or install it from the **Plugins > Add New** screen.
3. Activate the plugin through the **Plugins** screen.
4. Go to **View History** in the admin menu and choose your settings, then show the list in one of these ways:
    * Add the **Recently Viewed Products** block to a page, post or block template.
    * Add the shortcode `[product_view_history_for_woocommerce]` to any content or template.
    * Tick one or more locations under **Automatic placement**.

== Usage ==

Default shortcode:

`[product_view_history_for_woocommerce]`

Shortcode with attributes:

`[product_view_history_for_woocommerce limit="6" title="Recently Seen" show_price="no" badges="no"]`

Attributes:

* `limit` - number of products, 1 to 10. Defaults to the value in the plugin settings.
* `title` - section title. Defaults to the value in the plugin settings.
* `show_price` - `yes` or `no`.
* `badges` - `yes` or `no`. Turns the change badges on or off.

== Frequently Asked Questions ==

= Does this work without WooCommerce? =
No. WooCommerce must be active.

= Does it work with page caching plugins and CDNs? =
Yes. The list is loaded after the page has loaded, so cached pages work correctly. Make sure `admin-ajax.php` is not cached by your caching plugin or CDN (it normally is not, because the requests are POST requests).

= Does it need JavaScript? =
Yes. Recording views and loading the list are done in the browser. Without JavaScript nothing is recorded and nothing is shown.

= Can I change the number of products shown? =
Yes, from the settings screen (up to 10), from the block sidebar, or with the `limit` shortcode attribute.

= Can I hide the product price or the badges? =
Yes. Use the settings screen, the block sidebar, or `show_price="no"` and `badges="no"` in the shortcode.

= When does a badge appear? =
A badge appears only for products the visitor has viewed before. "Price dropped" compares the current price with the price the visitor saw; "Back in stock" appears if the product was out of stock when the visitor saw it and is available now. "Out of stock" and "Only N left" reflect the current stock. Visits recorded by older versions of the plugin do not contain price or stock data, so they only get the stock-based badges.

= Will it show the product I am looking at right now? =
No. The current product is excluded from the list on its own page.

= Does it work with Elementor and other page builders? =
Yes. Use the builder's Shortcode widget with `[product_view_history_for_woocommerce]`, or the block if your builder supports blocks.

= The automatic placement does not show on my cart or checkout page. =
The automatic locations rely on the standard WooCommerce template hooks. The block-based Cart and Checkout pages do not fire all of them. Add the Recently Viewed Products block to those pages instead.

= Does it work on right-to-left sites? =
Yes. The layout mirrors automatically and a Persian translation is included.

= Does it track visitors across devices or accounts? =
No. The history lives in a cookie in the visitor's browser, so it is per browser and is not shared between devices.

= How do I translate it? =
The plugin is translation ready (text domain `websemicolon-product-view-history-for-woocommerce`). A `.pot` file is included in the `languages` folder.

== Privacy ==

The plugin sets one first-party cookie when a visitor opens a single product page:

* **Name:** `product_view_history_for_woocommerce`
* **Content:** a short list (up to 10) of product IDs, with the price and stock status each product had when it was viewed. It contains no name, email, IP address or user ID.
* **Lifetime:** 30 days.
* **Flags:** `HttpOnly`, `SameSite=Lax`, and `Secure` on HTTPS sites.

The cookie is used only to show the visitor their own recently viewed products. Nothing is stored in the database and no data is sent to any external service. Depending on where your visitors live, you may need to list this cookie as a functional cookie in your cookie notice or privacy policy.

== Changelog ==

= 1.0.0 =
* Change badges on viewed products: price dropped, back in stock, low stock and out of stock.
* Page-cache friendly: views are recorded and the list is loaded in the browser through AJAX.
* New "Recently Viewed Products" block with sidebar settings.
* Automatic placement on single product pages, the empty cart page, the empty shop results page and the order received page.
* Shortcode attributes `limit`, `title`, `show_price` and `badges`.
* Newest-first ordering: viewing a product again moves it to the front.
* The current product and catalog-hidden products are left out of the list; products without an image show the WooCommerce placeholder.
* RTL-ready layout and a complete Persian (fa_IR) translation.
* Declared compatibility with High-Performance Order Storage.
* Plugin settings are removed when the plugin is deleted.

== Upgrade Notice ==

= 1.0.0 =
First release.
