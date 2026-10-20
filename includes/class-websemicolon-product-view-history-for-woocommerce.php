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

	private static $styles_printed = false;

	public function __construct() {
		add_action( 'init', array( $this, 'register_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_tracking' ) );
		add_action( 'wp_ajax_product_view_history_track', array( $this, 'ajax_track' ) );
		add_action( 'wp_ajax_nopriv_product_view_history_track', array( $this, 'ajax_track' ) );
		add_action( 'wp_ajax_product_view_history_render', array( $this, 'ajax_render' ) );
		add_action( 'wp_ajax_nopriv_product_view_history_render', array( $this, 'ajax_render' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function register_shortcode() {
		add_shortcode( 'product_view_history_for_woocommerce', array( $this, 'render_products' ) );
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
	}

	public function sanitize_display_count( $value ) {
		return min( self::MAX_PRODUCTS, max( 1, absint( $value ) ) );
	}

	public function sanitize_yes_no( $value ) {
		$value = is_string( $value ) ? strtolower( $value ) : '';
		return 'no' === $value ? 'no' : 'yes';
	}

	private function get_default_title() {
		return __( 'Recently Viewed Products', 'websemicolon-product-view-history-for-woocommerce' );
	}

	/**
	 * Reads the viewed product IDs (oldest first) from the visitor's cookie.
	 *
	 * @return int[]
	 */
	private function get_viewed_ids() {
		if ( ! isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return array();
		}

		$cookie_value = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );
		$decoded      = json_decode( $cookie_value, true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}

		$ids = array_map( 'absint', array_filter( $decoded, 'is_scalar' ) );

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$display_count = min( self::MAX_PRODUCTS, max( 1, absint( get_option( 'product_view_history_for_woocommerce_display_count', 5 ) ) ) );
		$display_title = sanitize_text_field( get_option( 'product_view_history_for_woocommerce_title', $this->get_default_title() ) );
		$show_price    = $this->sanitize_yes_no( get_option( 'product_view_history_for_woocommerce_show_price', 'yes' ) );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Product View History for WooCommerce', 'websemicolon-product-view-history-for-woocommerce' ); ?></h1>
			<p><?php echo esc_html__( 'Use the shortcode below to display the product view history anywhere on your site.', 'websemicolon-product-view-history-for-woocommerce' ); ?></p>
			<p><code>[product_view_history_for_woocommerce]</code></p>
			<p><?php echo esc_html__( 'You can also override the default settings with shortcode attributes:', 'websemicolon-product-view-history-for-woocommerce' ); ?></p>
			<p><code>[product_view_history_for_woocommerce limit="6" title="Recently Seen" show_price="no"]</code></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'product_view_history_for_woocommerce_settings' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="product_view_history_for_woocommerce_display_count"><?php echo esc_html__( 'Number of products to display', 'websemicolon-product-view-history-for-woocommerce' ); ?></label>
						</th>
						<td>
							<input type="number" min="1" max="10" name="product_view_history_for_woocommerce_display_count" id="product_view_history_for_woocommerce_display_count" value="<?php echo esc_attr( $display_count ); ?>" class="small-text" />
							<p class="description"><?php echo esc_html__( 'Maximum value is 10.', 'websemicolon-product-view-history-for-woocommerce' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="product_view_history_for_woocommerce_title"><?php echo esc_html__( 'Section title', 'websemicolon-product-view-history-for-woocommerce' ); ?></label>
						</th>
						<td>
							<input type="text" name="product_view_history_for_woocommerce_title" id="product_view_history_for_woocommerce_title" value="<?php echo esc_attr( $display_title ); ?>" class="regular-text" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="product_view_history_for_woocommerce_show_price"><?php echo esc_html__( 'Show product price', 'websemicolon-product-view-history-for-woocommerce' ); ?></label>
						</th>
						<td>
							<select name="product_view_history_for_woocommerce_show_price" id="product_view_history_for_woocommerce_show_price">
								<option value="yes" <?php selected( $show_price, 'yes' ); ?>><?php echo esc_html__( 'Yes', 'websemicolon-product-view-history-for-woocommerce' ); ?></option>
								<option value="no" <?php selected( $show_price, 'no' ); ?>><?php echo esc_html__( 'No', 'websemicolon-product-view-history-for-woocommerce' ); ?></option>
							</select>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Loads the tracking script on single product pages.
	 *
	 * Tracking runs in the browser (via AJAX) instead of during page generation,
	 * so it still works when the page itself is served from a page cache.
	 */
	public function maybe_enqueue_tracking() {
		if ( class_exists( 'WooCommerce' ) && is_singular( 'product' ) ) {
			$this->enqueue_assets();
		}
	}

	private function enqueue_assets() {
		if ( ! self::$styles_printed ) {
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
			self::$styles_printed = true;
		}
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

		// Drop any earlier occurrence so the product moves to the "most recent" end of the list.
		$viewed_products   = array_diff( $this->get_viewed_ids(), array( $product_id ) );
		$viewed_products[] = $product_id;
		$viewed_products   = array_slice( array_values( $viewed_products ), -self::MAX_PRODUCTS );

		$cookie_value = wp_json_encode( $viewed_products );
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

	public function render_products( $atts = array() ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return '<p>' . esc_html__( 'WooCommerce is required for this plugin to work.', 'websemicolon-product-view-history-for-woocommerce' ) . '</p>';
		}

		$atts = shortcode_atts(
			array(
				'limit'      => get_option( 'product_view_history_for_woocommerce_display_count', 5 ),
				'title'      => get_option( 'product_view_history_for_woocommerce_title', $this->get_default_title() ),
				'show_price' => get_option( 'product_view_history_for_woocommerce_show_price', 'yes' ),
			),
			$atts,
			'product_view_history_for_woocommerce'
		);

		$this->enqueue_assets();

		// The list is per visitor, so it is filled in by the browser (see assets/js/view-history.js).
		// This keeps the page HTML identical for everyone and therefore safe to cache.
		return sprintf(
			'<div class="pvh-async" data-limit="%1$d" data-title="%2$s" data-show-price="%3$s"></div>',
			min( self::MAX_PRODUCTS, max( 1, absint( $atts['limit'] ) ) ),
			esc_attr( sanitize_text_field( $atts['title'] ) ),
			'yes' === strtolower( $atts['show_price'] ) ? 'yes' : 'no'
		);
	}

	public function ajax_render() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$limit      = isset( $_POST['limit'] ) ? absint( wp_unslash( $_POST['limit'] ) ) : 5;
		$title      = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : $this->get_default_title();
		$show_price = isset( $_POST['show_price'] ) && 'yes' === $_POST['show_price'];
		$current_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		wp_send_json_success(
			array(
				'html' => $this->build_markup( $limit, $title, $show_price, $current_id ),
			)
		);
	}

	private function build_markup( $limit, $title, $show_price, $current_id ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return '';
		}

		$limit = min( self::MAX_PRODUCTS, max( 1, $limit ) );

		$viewed_products_ids = array_reverse( $this->get_viewed_ids() );

		// Do not list the product that is currently being viewed.
		if ( $current_id ) {
			$viewed_products_ids = array_values( array_diff( $viewed_products_ids, array( $current_id ) ) );
		}

		$viewed_products_ids = array_slice( $viewed_products_ids, 0, $limit );

		if ( empty( $viewed_products_ids ) ) {
			return '';
		}

		$products_query = new WP_Query(
			array(
				'post_type'           => 'product',
				'post_status'         => 'publish',
				'posts_per_page'      => count( $viewed_products_ids ),
				'post__in'            => $viewed_products_ids,
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
					?>
					<div class="pvh-product-item">
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
