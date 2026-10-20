<?php
/**
 * Main plugin class for WebSemicolon Product View History for WooCommerce.
 *
 * @package WebSemicolon_Product_View_History_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebSemicolon_Product_View_History_For_WooCommerce_Plugin {

	const COOKIE_NAME  = 'product_view_history_for_woocommerce';
	const MAX_PRODUCTS = 10;

	/**
	 * Automatic placements: slug => WooCommerce template hook and priority.
	 * Labels are translated in get_placement_labels() so nothing is translated before `init`.
	 */
	const PLACEMENT_HOOKS = array(
		'after_single_product' => array( 'woocommerce_after_single_product_summary', 25 ),
		'empty_cart'           => array( 'woocommerce_cart_is_empty', 20 ),
		'no_results'           => array( 'woocommerce_no_products_found', 20 ),
		'thankyou'             => array( 'woocommerce_thankyou', 20 ),
	);

	private static $assets_enqueued = false;

	public function __construct() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'register_shortcode' ) );
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_tracking' ) );
		add_action( 'wp_ajax_product_view_history_track', array( $this, 'ajax_track' ) );
		add_action( 'wp_ajax_nopriv_product_view_history_track', array( $this, 'ajax_track' ) );
		add_action( 'wp_ajax_product_view_history_render', array( $this, 'ajax_render' ) );
		add_action( 'wp_ajax_nopriv_product_view_history_render', array( $this, 'ajax_render' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		foreach ( $this->get_selected_placements() as $slug ) {
			add_action( self::PLACEMENT_HOOKS[ $slug ][0], array( $this, 'render_placement' ), self::PLACEMENT_HOOKS[ $slug ][1] );
		}
	}

	public function load_textdomain() {
		load_plugin_textdomain(
			'websemicolon-product-view-history-for-woocommerce',
			false,
			dirname( plugin_basename( WEBSEMICOLON_PVH_FILE ) ) . '/languages'
		);
	}

	public function register_shortcode() {
		add_shortcode( 'product_view_history_for_woocommerce', array( $this, 'render_products' ) );
	}

	/**
	 * Registers the dynamic "Recently Viewed Products" block (no build step required).
	 */
	public function register_block() {
		wp_register_script(
			'websemicolon-pvh-block-editor',
			WEBSEMICOLON_PVH_URL . 'blocks/product-view-history/index.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ),
			WEBSEMICOLON_PVH_VERSION,
			true
		);
		wp_set_script_translations( 'websemicolon-pvh-block-editor', 'websemicolon-product-view-history-for-woocommerce', WEBSEMICOLON_PVH_PATH . 'languages' );

		register_block_type(
			WEBSEMICOLON_PVH_PATH . 'blocks/product-view-history',
			array(
				'render_callback' => array( $this, 'render_block' ),
			)
		);
	}

	public function register_admin_menu() {
		add_menu_page(
			__( 'Product View History for WooCommerce', 'websemicolon-product-view-history-for-woocommerce' ),
			__( 'View History', 'websemicolon-product-view-history-for-woocommerce' ),
			'manage_options',
			'websemicolon-product-view-history-for-woocommerce',
			array( $this, 'render_admin_page' ),
			'dashicons-visibility',
			26
		);
	}

	public function register_settings() {
		register_setting(
			'product_view_history_for_woocommerce_settings',
			'product_view_history_for_woocommerce_display_count',
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( $this, 'sanitize_display_count' ),
			)
		);
		register_setting(
			'product_view_history_for_woocommerce_settings',
			'product_view_history_for_woocommerce_title',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		register_setting(
			'product_view_history_for_woocommerce_settings',
			'product_view_history_for_woocommerce_show_price',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_yes_no' ),
			)
		);
		register_setting(
			'product_view_history_for_woocommerce_settings',
			'product_view_history_for_woocommerce_show_badges',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_yes_no' ),
			)
		);
		register_setting(
			'product_view_history_for_woocommerce_settings',
			'product_view_history_for_woocommerce_placements',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_placements' ),
			)
		);
	}

	public function sanitize_display_count( $value ) {
		return min( self::MAX_PRODUCTS, max( 1, absint( $value ) ) );
	}

	public function sanitize_yes_no( $value ) {
		$value = is_string( $value ) ? strtolower( $value ) : '';
		return 'no' === $value ? 'no' : 'yes';
	}

	public function sanitize_placements( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		return array_values( array_intersect( array_keys( self::PLACEMENT_HOOKS ), array_map( 'sanitize_key', $value ) ) );
	}

	private function get_selected_placements() {
		return $this->sanitize_placements( get_option( 'product_view_history_for_woocommerce_placements', array() ) );
	}

	private function get_placement_labels() {
		return array(
			'after_single_product' => __( 'Single product page (below the product details)', 'websemicolon-product-view-history-for-woocommerce' ),
			'empty_cart'           => __( 'Empty cart page', 'websemicolon-product-view-history-for-woocommerce' ),
			'no_results'           => __( 'Shop page when no products are found', 'websemicolon-product-view-history-for-woocommerce' ),
			'thankyou'             => __( 'Order received (thank you) page', 'websemicolon-product-view-history-for-woocommerce' ),
		);
	}

	private function get_default_title() {
		return __( 'Recently Viewed Products', 'websemicolon-product-view-history-for-woocommerce' );
	}

	/**
	 * Saved plugin settings, with defaults applied.
	 *
	 * @return array
	 */
	private function get_settings() {
		return array(
			'limit'      => $this->sanitize_display_count( get_option( 'product_view_history_for_woocommerce_display_count', 5 ) ),
			'title'      => sanitize_text_field( get_option( 'product_view_history_for_woocommerce_title', $this->get_default_title() ) ),
			'show_price' => $this->sanitize_yes_no( get_option( 'product_view_history_for_woocommerce_show_price', 'yes' ) ),
			'badges'     => $this->sanitize_yes_no( get_option( 'product_view_history_for_woocommerce_show_badges', 'yes' ) ),
		);
	}

	/**
	 * Reads the visitor's history from the cookie, oldest first.
	 *
	 * Each item records the product ID plus its price and stock status at the time it was
	 * viewed, which is what allows "price dropped" / "back in stock" badges later. Cookies
	 * written by older versions (a plain list of IDs) are still understood.
	 *
	 * @return array[] Items shaped array( 'id' => int, 'price' => float|null, 'stock' => bool|null ).
	 */
	private function get_viewed_items() {
		if ( ! isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return array();
		}

		$decoded = json_decode( sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) ), true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}

		$items = array();
		foreach ( $decoded as $entry ) {
			if ( is_array( $entry ) ) {
				$id    = isset( $entry['i'] ) && is_scalar( $entry['i'] ) ? absint( $entry['i'] ) : 0;
				$price = isset( $entry['p'] ) && is_numeric( $entry['p'] ) ? (float) $entry['p'] : null;
				$stock = isset( $entry['s'] ) ? (bool) $entry['s'] : null;
			} elseif ( is_scalar( $entry ) ) {
				$id    = absint( $entry );
				$price = null;
				$stock = null;
			} else {
				continue;
			}

			if ( ! $id ) {
				continue;
			}

			// Re-adding an ID moves it to the "most recent" end.
			unset( $items[ $id ] );
			$items[ $id ] = array(
				'id'    => $id,
				'price' => $price,
				'stock' => $stock,
			);
		}

		return array_values( $items );
	}

	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings           = $this->get_settings();
		$selected_placement = $this->get_selected_placements();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Product View History for WooCommerce', 'websemicolon-product-view-history-for-woocommerce' ); ?></h1>
			<p><?php echo esc_html__( 'Show visitors the products they looked at recently. You can use the shortcode, the "Recently Viewed Products" block, or the automatic placements below.', 'websemicolon-product-view-history-for-woocommerce' ); ?></p>
			<p><code>[product_view_history_for_woocommerce]</code></p>
			<p><?php echo esc_html__( 'You can also override the default settings with shortcode attributes:', 'websemicolon-product-view-history-for-woocommerce' ); ?></p>
			<p><code>[product_view_history_for_woocommerce limit="6" title="Recently Seen" show_price="no" badges="no"]</code></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'product_view_history_for_woocommerce_settings' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="product_view_history_for_woocommerce_display_count"><?php echo esc_html__( 'Number of products to display', 'websemicolon-product-view-history-for-woocommerce' ); ?></label>
						</th>
						<td>
							<input type="number" min="1" max="10" name="product_view_history_for_woocommerce_display_count" id="product_view_history_for_woocommerce_display_count" value="<?php echo esc_attr( $settings['limit'] ); ?>" class="small-text" />
							<p class="description"><?php echo esc_html__( 'Maximum value is 10.', 'websemicolon-product-view-history-for-woocommerce' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="product_view_history_for_woocommerce_title"><?php echo esc_html__( 'Section title', 'websemicolon-product-view-history-for-woocommerce' ); ?></label>
						</th>
						<td>
							<input type="text" name="product_view_history_for_woocommerce_title" id="product_view_history_for_woocommerce_title" value="<?php echo esc_attr( $settings['title'] ); ?>" class="regular-text" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="product_view_history_for_woocommerce_show_price"><?php echo esc_html__( 'Show product price', 'websemicolon-product-view-history-for-woocommerce' ); ?></label>
						</th>
						<td>
							<select name="product_view_history_for_woocommerce_show_price" id="product_view_history_for_woocommerce_show_price">
								<option value="yes" <?php selected( $settings['show_price'], 'yes' ); ?>><?php echo esc_html__( 'Yes', 'websemicolon-product-view-history-for-woocommerce' ); ?></option>
								<option value="no" <?php selected( $settings['show_price'], 'no' ); ?>><?php echo esc_html__( 'No', 'websemicolon-product-view-history-for-woocommerce' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="product_view_history_for_woocommerce_show_badges"><?php echo esc_html__( 'Show change badges', 'websemicolon-product-view-history-for-woocommerce' ); ?></label>
						</th>
						<td>
							<select name="product_view_history_for_woocommerce_show_badges" id="product_view_history_for_woocommerce_show_badges">
								<option value="yes" <?php selected( $settings['badges'], 'yes' ); ?>><?php echo esc_html__( 'Yes', 'websemicolon-product-view-history-for-woocommerce' ); ?></option>
								<option value="no" <?php selected( $settings['badges'], 'no' ); ?>><?php echo esc_html__( 'No', 'websemicolon-product-view-history-for-woocommerce' ); ?></option>
							</select>
							<p class="description"><?php echo esc_html__( 'Highlights what changed since the visitor viewed a product: price dropped, back in stock, low stock or out of stock.', 'websemicolon-product-view-history-for-woocommerce' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Automatic placement', 'websemicolon-product-view-history-for-woocommerce' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php echo esc_html__( 'Automatic placement', 'websemicolon-product-view-history-for-woocommerce' ); ?></legend>
								<?php foreach ( $this->get_placement_labels() as $slug => $label ) : ?>
									<label>
										<input type="checkbox" name="product_view_history_for_woocommerce_placements[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $selected_placement, true ) ); ?> />
										<?php echo esc_html( $label ); ?>
									</label><br />
								<?php endforeach; ?>
							</fieldset>
							<p class="description"><?php echo esc_html__( 'Show the history without adding a shortcode. These locations use standard WooCommerce template hooks, so block-based cart and checkout pages may not trigger them; use the block there instead.', 'websemicolon-product-view-history-for-woocommerce' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Loads the front-end assets once per request.
	 *
	 * Tracking and rendering run in the browser (via AJAX) instead of during page generation,
	 * so they keep working when the page itself is served from a page cache.
	 */
	public function maybe_enqueue_tracking() {
		if ( class_exists( 'WooCommerce' ) && is_singular( 'product' ) ) {
			$this->enqueue_assets();
		}
	}

	private function enqueue_assets() {
		if ( self::$assets_enqueued ) {
			return;
		}

		wp_register_style( 'websemicolon-product-view-history-for-woocommerce', false, array(), WEBSEMICOLON_PVH_VERSION );
		wp_enqueue_style( 'websemicolon-product-view-history-for-woocommerce' );
		wp_add_inline_style( 'websemicolon-product-view-history-for-woocommerce', $this->get_inline_styles() );

		wp_enqueue_script(
			'websemicolon-product-view-history-for-woocommerce',
			WEBSEMICOLON_PVH_URL . 'assets/js/view-history.js',
			array(),
			WEBSEMICOLON_PVH_VERSION,
			true
		);
		wp_localize_script(
			'websemicolon-product-view-history-for-woocommerce',
			'pvhData',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'productId' => is_singular( 'product' ) ? absint( get_queried_object_id() ) : 0,
			)
		);

		self::$assets_enqueued = true;
	}

	/**
	 * Records a product view. Public endpoint without a nonce on purpose: nonces
	 * baked into cached pages expire, and this only writes the caller's own cookie.
	 */
	public function ajax_track() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		$this->save_viewed_product( $product_id );
		wp_send_json_success();
	}

	private function save_viewed_product( $product_id ) {
		if ( ! class_exists( 'WooCommerce' ) || ! $product_id || 'product' !== get_post_type( $product_id ) || 'publish' !== get_post_status( $product_id ) ) {
			return;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return;
		}

		$items = array_filter(
			$this->get_viewed_items(),
			function ( $item ) use ( $product_id ) {
				return $item['id'] !== $product_id;
			}
		);

		// Snapshot of the product as the visitor saw it, used later to detect changes.
		$price   = $product->get_price();
		$items[] = array(
			'id'    => $product_id,
			'price' => '' === $price ? null : (float) $price,
			'stock' => $product->is_in_stock(),
		);
		$items   = array_slice( array_values( $items ), -self::MAX_PRODUCTS );

		$payload = array();
		foreach ( $items as $item ) {
			$entry = array( 'i' => $item['id'] );
			if ( null !== $item['price'] ) {
				$entry['p'] = $item['price'];
			}
			if ( null !== $item['stock'] ) {
				$entry['s'] = $item['stock'] ? 1 : 0;
			}
			$payload[] = $entry;
		}

		$cookie_value = wp_json_encode( $payload );
		if ( ! $cookie_value ) {
			$cookie_value = '[]';
		}

		if ( ! headers_sent() ) {
			setcookie(
				self::COOKIE_NAME,
				$cookie_value,
				array(
					'expires'  => time() + ( 30 * DAY_IN_SECONDS ),
					'path'     => defined( 'COOKIEPATH' ) ? COOKIEPATH : '/',
					'domain'   => defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
		}
	}

	/**
	 * Outputs the placeholder for an enabled automatic placement.
	 */
	public function render_placement() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		$settings = $this->get_settings();
		// The placeholder is built entirely from escaped values in get_placeholder().
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->get_placeholder( $settings['limit'], $settings['title'], $settings['show_price'], $settings['badges'] );
	}

	public function render_products( $atts = array() ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return '<p>' . esc_html__( 'WooCommerce is required for this plugin to work.', 'websemicolon-product-view-history-for-woocommerce' ) . '</p>';
		}

		$settings = $this->get_settings();
		$atts     = shortcode_atts(
			array(
				'limit'      => $settings['limit'],
				'title'      => $settings['title'],
				'show_price' => $settings['show_price'],
				'badges'     => $settings['badges'],
			),
			$atts,
			'product_view_history_for_woocommerce'
		);

		return $this->get_placeholder( $atts['limit'], $atts['title'], $atts['show_price'], $atts['badges'] );
	}

	/**
	 * Server-side render callback of the block. Empty attributes fall back to the saved settings.
	 */
	public function render_block( $attributes ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return '';
		}

		$settings   = $this->get_settings();
		$limit      = ! empty( $attributes['limit'] ) ? $attributes['limit'] : $settings['limit'];
		$title      = isset( $attributes['title'] ) && '' !== $attributes['title'] ? $attributes['title'] : $settings['title'];
		$show_price = isset( $attributes['showPrice'] ) && 'default' !== $attributes['showPrice'] ? $attributes['showPrice'] : $settings['show_price'];
		$badges     = isset( $attributes['badges'] ) && 'default' !== $attributes['badges'] ? $attributes['badges'] : $settings['badges'];

		return $this->get_placeholder( $limit, $title, $show_price, $badges );
	}

	/**
	 * The list is per visitor, so it is filled in by the browser (see assets/js/view-history.js).
	 * This keeps the page HTML identical for everyone and therefore safe to cache.
	 */
	private function get_placeholder( $limit, $title, $show_price, $badges ) {
		$this->enqueue_assets();

		return sprintf(
			'<div class="pvh-async" data-limit="%1$d" data-title="%2$s" data-show-price="%3$s" data-badges="%4$s"></div>',
			min( self::MAX_PRODUCTS, max( 1, absint( $limit ) ) ),
			esc_attr( sanitize_text_field( $title ) ),
			esc_attr( $this->sanitize_yes_no( $show_price ) ),
			esc_attr( $this->sanitize_yes_no( $badges ) )
		);
	}

	public function ajax_render() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$limit      = isset( $_POST['limit'] ) ? absint( wp_unslash( $_POST['limit'] ) ) : 5;
		$title      = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : $this->get_default_title();
		$show_price = isset( $_POST['show_price'] ) && 'yes' === $_POST['show_price'];
		$badges     = isset( $_POST['badges'] ) && 'yes' === $_POST['badges'];
		$current_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		wp_send_json_success(
			array(
				'html' => $this->build_markup( $limit, $title, $show_price, $badges, $current_id ),
			)
		);
	}

	/**
	 * Decides which single badge (if any) to show for a product the visitor saw before.
	 *
	 * @param WC_Product $product Current product.
	 * @param array      $item    Snapshot from the visitor's cookie.
	 * @return array|null array( type, label ) or null.
	 */
	private function get_badge( $product, $item ) {
		$in_stock = $product->is_in_stock();

		if ( false === $item['stock'] && $in_stock ) {
			return array( 'restock', __( 'Back in stock', 'websemicolon-product-view-history-for-woocommerce' ) );
		}

		$price_now = $product->get_price();
		if ( null !== $item['price'] && $item['price'] > 0 && '' !== $price_now && (float) $price_now < $item['price'] - 0.005 ) {
			$percent = (int) round( ( $item['price'] - (float) $price_now ) / $item['price'] * 100 );
			if ( $percent >= 1 ) {
				/* translators: %d: percentage by which the price dropped since the visitor viewed the product. */
				return array( 'drop', sprintf( __( 'Price dropped %d%%', 'websemicolon-product-view-history-for-woocommerce' ), $percent ) );
			}
			return array( 'drop', __( 'Price dropped', 'websemicolon-product-view-history-for-woocommerce' ) );
		}

		if ( ! $in_stock ) {
			return array( 'out', __( 'Out of stock', 'websemicolon-product-view-history-for-woocommerce' ) );
		}

		if ( $product->managing_stock() ) {
			$quantity = $product->get_stock_quantity();
			if ( null !== $quantity && $quantity > 0 && $quantity <= wc_get_low_stock_amount( $product ) ) {
				/* translators: %d: number of items left in stock. */
				return array( 'low', sprintf( _n( 'Only %d left', 'Only %d left', $quantity, 'websemicolon-product-view-history-for-woocommerce' ), $quantity ) );
			}
		}

		return null;
	}

	private function build_markup( $limit, $title, $show_price, $show_badges, $current_id ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return '';
		}

		$limit = min( self::MAX_PRODUCTS, max( 1, $limit ) );

		$items = array();
		foreach ( array_reverse( $this->get_viewed_items() ) as $item ) {
			// Do not list the product that is currently being viewed.
			if ( $item['id'] !== $current_id ) {
				$items[ $item['id'] ] = $item;
			}
		}
		$items = array_slice( $items, 0, $limit, true );

		if ( empty( $items ) ) {
			return '';
		}

		$products_query = new WP_Query(
			array(
				'post_type'           => 'product',
				'post_status'         => 'publish',
				'posts_per_page'      => count( $items ),
				'post__in'            => array_keys( $items ),
				'orderby'             => 'post__in',
				'ignore_sticky_posts' => true,
			)
		);
		if ( ! $products_query->have_posts() ) {
			wp_reset_postdata();
			return '';
		}

		ob_start();
		?>
		<div class="product-view-history-for-woocommerce-wrapper">
			<h2 class="pvh-section-title"><?php echo esc_html( $title ); ?></h2>
			<div class="pvh-product-grid">
				<?php
				while ( $products_query->have_posts() ) {
					$products_query->the_post();
					$product = wc_get_product( get_the_ID() );
					if ( ! $product || 'hidden' === $product->get_catalog_visibility() ) {
						continue;
					}
					$badge = $show_badges ? $this->get_badge( $product, $items[ get_the_ID() ] ) : null;
					?>
					<div class="pvh-product-item">
						<?php if ( $badge ) : ?>
							<span class="pvh-badge pvh-badge--<?php echo esc_attr( $badge[0] ); ?>"><?php echo esc_html( $badge[1] ); ?></span>
						<?php endif; ?>
						<a class="pvh-product-link" href="<?php echo esc_url( get_permalink() ); ?>">
							<?php echo wp_kses_post( $product->get_image( 'woocommerce_thumbnail' ) ); ?>
							<h3 class="pvh-product-title"><?php echo esc_html( get_the_title() ); ?></h3>
							<?php if ( $show_price && $product->get_price_html() ) : ?>
								<span class="pvh-product-price"><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
							<?php endif; ?>
						</a>
					</div>
					<?php
				}
				wp_reset_postdata();
				?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	private function get_inline_styles() {
		return '
		.product-view-history-for-woocommerce-wrapper {
			margin: 2rem 0;
			padding: 1.25rem;
			background: #ffffff;
			border: 1px solid #e5e7eb;
			border-radius: 12px;
			box-shadow: 0 6px 18px rgba(15, 23, 42, 0.06);
			text-align: start;
		}
		.pvh-section-title {
			display: block;
			margin: 0 0 1rem;
			padding: 0;
			font-size: 1.25rem;
			font-weight: 700;
			line-height: 1.3;
			color: #111827;
		}
		.pvh-product-grid {
			display: grid;
			grid-template-columns: repeat(5, minmax(0, 1fr));
			gap: 1rem;
		}
		.pvh-product-item {
			position: relative;
			background: #f9fafb;
			border: 1px solid #e5e7eb;
			border-radius: 10px;
			padding: 0.75rem;
			transition: transform 0.2s ease, box-shadow 0.2s ease;
		}
		.pvh-product-item:hover {
			transform: translateY(-2px);
			box-shadow: 0 10px 20px rgba(15, 23, 42, 0.08);
		}
		.pvh-product-link {
			display: block;
			color: inherit;
			text-decoration: none;
		}
		.pvh-product-link img {
			width: 100%;
			height: auto;
			max-height: 200px;
			object-fit: scale-down;
			border-radius: 8px;
			margin-bottom: 0.75rem;
			display: block;
		}
		.pvh-product-title {
			margin: 0 0 0.4rem;
			font-size: 0.95rem;
			font-weight: 600;
			line-height: 1.4;
			color: #1f2937;
		}
		.pvh-product-price {
			display: inline-block;
			font-size: 0.95rem;
			font-weight: 700;
			color: #2563eb;
		}
		.pvh-badge {
			position: absolute;
			z-index: 1;
			top: 1.1rem;
			inset-inline-start: 1.1rem;
			padding: 0.2rem 0.55rem;
			border-radius: 999px;
			font-size: 0.75rem;
			font-weight: 700;
			line-height: 1.4;
			color: #ffffff;
			background: #6b7280;
			pointer-events: none;
		}
		.pvh-badge--drop {
			background: #dc2626;
		}
		.pvh-badge--restock {
			background: #16a34a;
		}
		.pvh-badge--low {
			background: #b45309;
		}
		.pvh-badge--out {
			background: #4b5563;
		}
		@media (max-width: 1024px) {
			.pvh-product-grid {
				grid-template-columns: repeat(3, minmax(0, 1fr));
			}
		}
		@media (max-width: 767px) {
			.pvh-product-grid {
				grid-template-columns: repeat(2, minmax(0, 1fr));
			}
		}
		';
	}
}
