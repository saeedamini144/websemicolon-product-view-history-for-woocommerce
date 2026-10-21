=== WebSemicolon Recently Viewed Products for WooCommerce – Price Drop & Back in Stock Badges ===
Contributors: saeedamini144
Donate link: https://github.com/saeedamini144
Tags: recently viewed products, woocommerce recently viewed, recently viewed, price drop, back in stock
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.0.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Show recently viewed products to WooCommerce shoppers, with price drop and back in stock badges. Cache compatible. Block, shortcode, RTL.

== Description ==

**WebSemicolon Recently Viewed Products for WooCommerce** shows every shopper the products they recently viewed in your store, and tells them when something changed since their visit: the price dropped, the product is back in stock, or stock is running low. That gives visitors a concrete reason to return to a product instead of just seeing a list of thumbnails.

The history belongs to the visitor and is kept in a cookie in their own browser. Logged-in customers can also have it saved in their account, so it follows them from their phone to their laptop. No data is sent to third parties.

= Features =

* **Grid, carousel or list.** Pick the layout in the settings, per block or per shortcode. The carousel is a lightweight native scroller with previous / next buttons (touch and keyboard friendly, RTL aware) and needs no slider library.
* **"Add to cart" button on every card.** Uses the standard WooCommerce button, so it follows your theme and supports AJAX add to cart. Variable products show "Select options".
* **Follows customers across devices.** For logged-in customers the history is merged with the cookie of the current browser and saved in their account. Guests keep using a cookie only. You can turn the account sync off, and it is covered by the WordPress personal data export and erase tools.
* **Report: viewed but not purchased.** A page under **View History > Report** lists your most viewed products with the units sold while views were being counted, so you can spot products that get attention but no sales.
* **Live preview.** The settings page shows a preview that updates as you change the layout, fonts, sizes and colors.
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
* **Appearance settings.** Choose the font, the text sizes, the number of columns, the corner radius and every color (section, cards, text, price and each badge) from the settings page with the WordPress color picker. Only system fonts are offered, so no font files are loaded from external servers.
* **Responsive grid.** Up to 6 columns on desktop (5 by default), up to 3 on tablets and 2 on phones.
* **RTL ready.** The layout uses logical CSS properties, so it mirrors correctly on right-to-left sites. A complete Persian (fa_IR) translation is included, also for the block editor.
* **Lightweight and clean.** One small script and a few lines of inline CSS are loaded only on pages that show the list or on single product pages (to record the view). No custom database tables, no external requests, and all settings and stored data are removed when the plugin is deleted.
* **Compatible with High-Performance Order Storage (HPOS).**

= How it works =

1. A visitor opens a product page. The plugin records the product, together with its price, stock status and the time of the view, in a cookie (up to 10 products, kept for 30 days). For logged-in customers the same list is saved in their account.
2. On any page that shows the list, the plugin asks the server for the visitor's products, compares each one with how it looked at the time of the visit and builds the layout you chose.
3. If a product changed in a way worth mentioning, one badge is shown on its card. Badge priority: back in stock, price dropped, out of stock, low stock.
4. Each product page view is also added to an anonymous per-product counter used by the report.

= Settings =

Open **View History** in the admin menu to set the default number of products (1 to 10), the section title, the layout, whether to show prices, the "Add to cart" button and the change badges, whether to sync history for logged-in customers, and where to display the list automatically. The **Appearance** section controls fonts, sizes, columns, corner radius and colors, with a live preview. The count, title, layout, price, "Add to cart" and badge options can be overridden per block or per shortcode.

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

`[product_view_history_for_woocommerce limit="6" title="Recently Seen" layout="carousel" show_price="no" badges="no" add_to_cart="yes"]`

Attributes (all optional, the defaults come from the plugin settings):

* `limit` - number of products, 1 to 10.
* `title` - section title.
* `layout` - `grid`, `carousel` or `list`.
* `show_price` - `yes` or `no`.
* `badges` - `yes` or `no`. Turns the change badges on or off.
* `add_to_cart` - `yes` or `no`. Shows the WooCommerce "Add to cart" button on each product.

== Frequently Asked Questions ==

= Does this work without WooCommerce? =
No. WooCommerce must be active.

= Does it work with page caching plugins and CDNs? =
Yes. The list is loaded after the page has loaded, so cached pages work correctly. Make sure `admin-ajax.php` is not cached by your caching plugin or CDN (it normally is not, because the requests are POST requests).

= Does it need JavaScript? =
Yes. Recording views and loading the list are done in the browser. Without JavaScript nothing is recorded and nothing is shown.

= Can I change the number of products shown? =
Yes, from the settings screen (up to 10), from the block sidebar, or with the `limit` shortcode attribute.

= Can I change the colors, fonts and sizes? =
Yes. Open **View History > Appearance** to set the font, text sizes, columns, corner radius and all colors. Colors use the WordPress color picker and each one has a "Default" button. If your page cache serves old styling after you save, purge it once.

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

= Does the history follow customers across devices? =
Yes, for logged-in customers. Their history is saved in their account and merged with the cookie of the browser they are using. Guests only have the cookie, so for them the history is per browser. You can turn the account sync off under **View History > Sync for logged-in customers**.

= How do the carousel and the list layout work? =
Choose **Layout** in the settings, in the block sidebar, or with `layout="carousel"` / `layout="list"` in the shortcode. The carousel shows as many products as the "Columns on desktop" setting (3 or fewer on tablets, 2 on phones) and scrolls with touch, trackpad, keyboard or the arrow buttons. The list shows one product per row.

= Does the "Add to cart" button work with AJAX? =
Yes, when AJAX add to cart is enabled in WooCommerce settings (WooCommerce > Settings > Products). Otherwise the button adds the product with a normal page reload. Variable and grouped products show "Select options" and link to the product page.

= What does the "Viewed but not purchased" report show? =
Under **View History > Report** you see your most viewed products, the units sold since views started being counted for that product, and the purchases per 100 views. A visitor is counted once per product every 30 minutes, and shop managers are not counted. Use **Reset statistics** to start counting from zero.

= Does the live preview use my real products? =
No. The preview on the settings page uses sample products so you can judge fonts, sizes and colors without needing any viewing history.

= How do I translate it? =
The plugin is translation ready (text domain `websemicolon-product-view-history-for-woocommerce`). A `.pot` file is included in the `languages` folder.

== Privacy ==

The plugin sets one first-party cookie when a visitor opens a single product page:

* **Name:** `product_view_history_for_woocommerce`
* **Content:** a short list (up to 10) of product IDs, with the price and stock status each product had when it was viewed and the time of the view. It contains no name, email, IP address or user ID.
* **Lifetime:** 30 days.
* **Flags:** `HttpOnly`, `SameSite=Lax`, and `Secure` on HTTPS sites.

The cookie is used only to show the visitor their own recently viewed products. No data is sent to any external service. Depending on where your visitors live, you may need to list this cookie as a functional cookie in your cookie notice or privacy policy.

Other data the plugin stores:

* **Logged-in customers (optional):** the same short list is saved in the customer's user meta (`product_view_history_for_woocommerce_items`) so it follows them across devices. It is included in the WordPress personal data export, removed by the personal data erase tool, and removed when the plugin is deleted. It can be turned off in the settings.
* **Product view counters:** each product has an anonymous view count (`_pvh_views`) used by the report. It is not linked to any visitor.

The plugin adds suggested text for your privacy policy under **Settings > Privacy**.

== Changelog ==

= 1.0.1 =
* New: change badges on viewed products: price dropped, back in stock, low stock and out of stock.
* New: page-cache friendly. Views are recorded and the list is loaded in the browser through AJAX.
* New: "Recently Viewed Products" block with sidebar settings.
* New: automatic placement on single product pages, the empty cart page, the empty shop results page and the order received page.
* New: grid, carousel and list layouts.
* New: optional WooCommerce "Add to cart" button on every card.
* New: history sync for logged-in customers across devices, with WordPress personal data export / erase support and suggested privacy policy text.
* New: report "Viewed but not purchased" with an anonymous per-product view counter.
* New: shortcode attributes `badges`, `layout` and `add_to_cart`.
* New: Appearance settings for font, text sizes, columns, corner radius and colors, with the WordPress color picker and a live preview.
* New: RTL-ready layout and a complete Persian (fa_IR) translation.
* New: declared compatibility with High-Performance Order Storage.
* Fix: setting "Show product price" to "No" was not saved.
* Fix: viewing a product again now moves it to the front of the list.
* Fix: the product currently being viewed and catalog-hidden products are left out of the list; products without an image show the WooCommerce placeholder.
* Fix: the section title is now a heading, the default title is "Recently Viewed Products", and the cookie uses `SameSite=Lax`.
* Plugin settings are removed when the plugin is deleted.

= 1.0.0 =
* Added advanced admin settings for title, price display, and number of products.
* Increased maximum supported display count to 10.
* Improved mobile responsiveness for two-column layout.

== Upgrade Notice ==

= 1.0.1 =
Adds price drop and back in stock badges, carousel and list layouts, an "Add to cart" button, cross-device history for logged-in customers, a "viewed but not purchased" report, page cache support, a block, automatic placements and appearance settings with a live preview. Fixes the "Show product price" setting not saving.

= 1.0.0 =
First release.
