<?php
/**
 * Main plugin class for WebSemicolon Recently Viewed Products for WooCommerce.
 *
 * @package WebSemicolon_Product_View_History_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebSemicolon_Product_View_History_For_WooCommerce_Plugin {

	const COOKIE_NAME        = 'product_view_history_for_woocommerce';
	const MAX_PRODUCTS       = 10;
	const MENU_SLUG          = 'websemicolon-product-view-history-for-woocommerce';
	const USER_META_KEY      = 'product_view_history_for_woocommerce_items';
	const META_VIEWS         = '_pvh_views';
	const META_SALES_BASE    = '_pvh_sales_base';
	const VIEW_DEDUP_SECONDS = 1800;
	const LAYOUTS            = array( 'grid', 'carousel', 'list' );

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

	/**
	 * Appearance settings and their built-in defaults.
	 * Empty or invalid saved values fall back to these.
	 */
	const STYLE_DEFAULTS = array(
		'font_family'      => 'inherit',
		'columns'          => 5,
		'radius'           => 12,
		'title_size'       => 20,
		'item_title_size'  => 15,
		'price_size'       => 15,
		'badge_size'       => 12,
		'title_color'      => '#111827',
		'item_title_color' => '#1f2937',
		'price_color'      => '#2563eb',
		'background_color' => '#ffffff',
		'card_color'       => '#f9fafb',
		'border_color'     => '#e5e7eb',
		'drop_color'       => '#dc2626',
		'restock_color'    => '#16a34a',
		'low_color'        => '#b45309',
		'out_color'        => '#4b5563',
	);

	/**
	 * Allowed range (min, max) of the numeric appearance settings.
	 */
	const STYLE_RANGES = array(
		'columns'         => array( 2, 6 ),
		'radius'          => array( 0, 32 ),
		'title_size'      => array( 12, 48 ),
		'item_title_size' => array( 10, 28 ),
		'price_size'      => array( 10, 28 ),
		'badge_size'      => array( 9, 20 ),
	);

	/**
	 * Font choices. Only system fonts are offered, so no font files are downloaded from third parties.
	 */
	const FONT_STACKS = array(
		'inherit' => 'inherit',
		'sans'    => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
		'tahoma'  => 'Tahoma, "Segoe UI", Arial, sans-serif',
		'serif'   => 'Georgia, "Times New Roman", serif',
		'mono'    => 'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace',
	);

	private static $assets_enqueued = false;

	private $admin_hook  = '';
	private $report_hook = '';

	/**
	 * Page the visitor is on, used to build working "add to cart" links inside AJAX responses.
	 *
	 * @var string
	 */
	private $page_url = '';

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
		add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_post_product_view_history_reset_stats', array( $this, 'handle_reset_stats' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_privacy_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_privacy_eraser' ) );

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
		$this->admin_hook = add_menu_page(
			__( 'Product View History for WooCommerce', 'websemicolon-product-view-history-for-woocommerce' ),
			__( 'View History', 'websemicolon-product-view-history-for-woocommerce' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_admin_page' ),
			'dashicons-visibility',
			26
		);

		// The first submenu entry replaces the duplicated top-level label.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Product View History for WooCommerce', 'websemicolon-product-view-history-for-woocommerce' ),
			__( 'Settings', 'websemicolon-product-view-history-for-woocommerce' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_admin_page' )
		);

		$this->report_hook = add_submenu_page(
			self::MENU_SLUG,
			__( 'Viewed but not purchased', 'websemicolon-product-view-history-for-woocommerce' ),
			__( 'Report', 'websemicolon-product-view-history-for-woocommerce' ),
			'manage_options',
			self::MENU_SLUG . '-report',
			array( $this, 'render_report_page' )
		);
	}

	public function register_settings() {
		$group = 'product_view_history_for_woocommerce_settings';

		register_setting(
			$group,
			'product_view_history_for_woocommerce_display_count',
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( $this, 'sanitize_display_count' ),
			)
		);
		register_setting(
			$group,
			'product_view_history_for_woocommerce_title',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		register_setting(
			$group,
			'product_view_history_for_woocommerce_show_price',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_yes_no' ),
			)
		);
		register_setting(
			$group,
			'product_view_history_for_woocommerce_show_badges',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_yes_no' ),
			)
		);
		register_setting(
			$group,
			'product_view_history_for_woocommerce_layout',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_layout' ),
			)
		);
		register_setting(
			$group,
			'product_view_history_for_woocommerce_add_to_cart',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_no_yes' ),
			)
		);
		register_setting(
			$group,
			'product_view_history_for_woocommerce_sync_users',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_yes_no' ),
			)
		);
		register_setting(
			$group,
			'product_view_history_for_woocommerce_style',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_style' ),
			)
		);
		register_setting(
			$group,
			'product_view_history_for_woocommerce_placements',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_placements' ),
			)
		);
	}

	public function enqueue_admin_assets( $hook ) {
		$is_settings = $this->admin_hook && $hook === $this->admin_hook;
		$is_report   = $this->report_hook && $hook === $this->report_hook;

		if ( ! $is_settings && ! $is_report ) {
			return;
		}

		$dependencies = array( 'jquery' );
		if ( $is_settings ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_add_inline_style( 'wp-color-picker', $this->get_inline_styles() . $this->get_preview_styles() );
			$dependencies[] = 'wp-color-picker';
		}

		wp_enqueue_script(
			'websemicolon-product-view-history-for-woocommerce-admin',
			WEBSEMICOLON_PVH_URL . 'assets/js/admin.js',
			$dependencies,
			WEBSEMICOLON_PVH_VERSION,
			true
		);
		wp_localize_script(
			'websemicolon-product-view-history-for-woocommerce-admin',
			'pvhAdmin',
			array(
				'fonts'        => self::FONT_STACKS,
				'defaultTitle' => $this->get_default_title(),
				'confirmReset' => __( 'Reset all view statistics? This cannot be undone.', 'websemicolon-product-view-history-for-woocommerce' ),
			)
		);
	}

	/**
	 * Validates the appearance settings: fonts against a whitelist, numbers clamped to their
	 * range and colors as hex. Anything else falls back to the default, so only safe values
	 * can ever reach the generated CSS.
	 */
	public function sanitize_style( $value ) {
		$value = is_array( $value ) ? $value : array();
		$clean = array();

		foreach ( self::STYLE_DEFAULTS as $key => $default ) {
			$raw = isset( $value[ $key ] ) && is_scalar( $value[ $key ] ) ? (string) $value[ $key ] : '';

			if ( 'font_family' === $key ) {
				$clean[ $key ] = isset( self::FONT_STACKS[ $raw ] ) ? $raw : $default;
			} elseif ( isset( self::STYLE_RANGES[ $key ] ) ) {
				$clean[ $key ] = '' === $raw ? $default : min( self::STYLE_RANGES[ $key ][1], max( self::STYLE_RANGES[ $key ][0], (int) $raw ) );
			} else {
				$color         = sanitize_hex_color( $raw );
				$clean[ $key ] = $color ? strtolower( $color ) : $default;
			}
		}

		return $clean;
	}

	private function get_style() {
		return $this->sanitize_style( get_option( 'product_view_history_for_woocommerce_style', array() ) );
	}

	private function get_font_labels() {
		return array(
			'inherit' => __( 'Theme font (default)', 'websemicolon-product-view-history-for-woocommerce' ),
			'sans'    => __( 'System sans-serif', 'websemicolon-product-view-history-for-woocommerce' ),
			'tahoma'  => __( 'Tahoma (good for Persian and Arabic)', 'websemicolon-product-view-history-for-woocommerce' ),
			'serif'   => __( 'Serif', 'websemicolon-product-view-history-for-woocommerce' ),
			'mono'    => __( 'Monospace', 'websemicolon-product-view-history-for-woocommerce' ),
		);
	}

	/**
	 * Appearance fields. "var" is the CSS custom property the live preview updates.
	 */
	private function get_style_fields() {
		return array(
			'font_family'      => array(
				'type'  => 'font',
				'var'   => 'font',
				'label' => __( 'Font', 'websemicolon-product-view-history-for-woocommerce' ),
				'help'  => __( 'Only system fonts are offered, so nothing is downloaded from external servers.', 'websemicolon-product-view-history-for-woocommerce' ),
			),
			'columns'          => array(
				'type'  => 'number',
				'var'   => 'visible',
				'label' => __( 'Columns on desktop', 'websemicolon-product-view-history-for-woocommerce' ),
				'help'  => __( 'Tablets show up to 3 columns and phones show 2. In the carousel this is the number of visible products.', 'websemicolon-product-view-history-for-woocommerce' ),
			),
			'radius'           => array(
				'type'  => 'number',
				'var'   => 'radius',
				'unit'  => 'px',
				'label' => __( 'Corner radius (px)', 'websemicolon-product-view-history-for-woocommerce' ),
			),
			'title_size'       => array(
				'type'  => 'number',
				'var'   => 'title-size',
				'unit'  => 'px',
				'label' => __( 'Section title size (px)', 'websemicolon-product-view-history-for-woocommerce' ),
			),
			'item_title_size'  => array(
				'type'  => 'number',
				'var'   => 'item-title-size',
				'unit'  => 'px',
				'label' => __( 'Product title size (px)', 'websemicolon-product-view-history-for-woocommerce' ),
			),
			'price_size'       => array(
				'type'  => 'number',
				'var'   => 'price-size',
				'unit'  => 'px',
				'label' => __( 'Price size (px)', 'websemicolon-product-view-history-for-woocommerce' ),
			),
			'badge_size'       => array(
				'type'  => 'number',
				'var'   => 'badge-size',
				'unit'  => 'px',
				'label' => __( 'Badge text size (px)', 'websemicolon-product-view-history-for-woocommerce' ),
			),
			'title_color'      => array(
				'type'  => 'color',
				'var'   => 'title-color',
				'label' => __( 'Section title color', 'websemicolon-product-view-history-for-woocommerce' ),
			),
			'item_title_color' => array(
				'type'  => 'color',
				'var'   => 'item-title-color',
				'label' => __( 'Product title color', 'websemicolon-product-view-history-for-woocommerce' ),
			),
			'price_color'      => array(
				'type'  => 'color',
				'var'   => 'price-color',
				'label' => __( 'Price color', 'websemicolon-product-view-history-for-woocommerce' ),
			),
			'background_color' => array(
				'type'  => 'color',
				'var'   => 'background',
				'label' => __( 'Section background', 'websemicolon-product-view-history-for-woocommerce' ),
			),
			'card_color'       => array(
				'type'  => 'color',
				'var'   => 'card',
				'label' => __( 'Product card background', 'websemicolon-product-view-history-for-woocommerce' ),
			),
			'border_color'     => array(
				'type'  => 'color',
				'var'   => 'border',
				'label' => __( 'Border color', 'websemicolon-product-view-history-for-woocommerce' ),
			),
			'drop_color'       => array(
				'type'  => 'color',
				'var'   => 'drop',
				'label' => __( 'Price drop badge color', 'websemicolon-product-view-history-for-woocommerce' ),
			),
			'restock_color'    => array(
				'type'  => 'color',
				'var'   => 'restock',
				'label' => __( 'Back in stock badge color', 'websemicolon-product-view-history-for-woocommerce' ),
			),
			'low_color'        => array(
				'type'  => 'color',
				'var'   => 'low',
				'label' => __( 'Low stock badge color', 'websemicolon-product-view-history-for-woocommerce' ),
			),
			'out_color'        => array(
				'type'  => 'color',
				'var'   => 'out',
				'label' => __( 'Out of stock badge color', 'websemicolon-product-view-history-for-woocommerce' ),
			),
		);
	}

	public function sanitize_display_count( $value ) {
		return min( self::MAX_PRODUCTS, max( 1, absint( $value ) ) );
	}

	public function sanitize_yes_no( $value ) {
		$value = is_string( $value ) ? strtolower( $value ) : '';
		return 'no' === $value ? 'no' : 'yes';
	}

	/**
	 * Like sanitize_yes_no(), but anything that is not an explicit "yes" means "no".
	 */
	public function sanitize_no_yes( $value ) {
		$value = is_string( $value ) ? strtolower( $value ) : '';
		return 'yes' === $value ? 'yes' : 'no';
	}

	public function sanitize_layout( $value ) {
		$value = is_string( $value ) ? strtolower( $value ) : '';
		return in_array( $value, self::LAYOUTS, true ) ? $value : 'grid';
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

	private function get_layout_labels() {
		return array(
			'grid'     => __( 'Grid', 'websemicolon-product-view-history-for-woocommerce' ),
			'carousel' => __( 'Carousel (slider)', 'websemicolon-product-view-history-for-woocommerce' ),
			'list'     => __( 'List', 'websemicolon-product-view-history-for-woocommerce' ),
		);
	}

	private function get_default_title() {
		return __( 'Recently Viewed Products', 'websemicolon-product-view-history-for-woocommerce' );
	}

	private function is_sync_enabled() {
		return 'yes' === $this->sanitize_yes_no( get_option( 'product_view_history_for_woocommerce_sync_users', 'yes' ) );
	}

	/**
	 * Saved plugin settings, with defaults applied.
	 *
	 * @return array
	 */
	private function get_settings() {
		return array(
			'limit'       => $this->sanitize_display_count( get_option( 'product_view_history_for_woocommerce_display_count', 5 ) ),
			'title'       => sanitize_text_field( get_option( 'product_view_history_for_woocommerce_title', $this->get_default_title() ) ),
			'show_price'  => $this->sanitize_yes_no( get_option( 'product_view_history_for_woocommerce_show_price', 'yes' ) ),
			'badges'      => $this->sanitize_yes_no( get_option( 'product_view_history_for_woocommerce_show_badges', 'yes' ) ),
			'layout'      => $this->sanitize_layout( get_option( 'product_view_history_for_woocommerce_layout', 'grid' ) ),
			'add_to_cart' => $this->sanitize_no_yes( get_option( 'product_view_history_for_woocommerce_add_to_cart', 'no' ) ),
		);
	}

	/**
	 * Turns decoded cookie / user meta data into clean history items, oldest first.
	 *
	 * Each item records the product ID plus its price and stock status at the time it was
	 * viewed (which is what allows "price dropped" / "back in stock" badges) and the time of
	 * the view. Data written by older versions (a plain list of IDs) is still understood.
	 *
	 * @param mixed $entries Decoded cookie or user meta value.
	 * @return array[] Items shaped array( 'id' => int, 'price' => float|null, 'stock' => bool|null, 'time' => int ).
	 */
	private function normalize_entries( $entries ) {
		if ( ! is_array( $entries ) ) {
			return array();
		}

		$items = array();
		foreach ( $entries as $entry ) {
			if ( is_array( $entry ) ) {
				$id    = isset( $entry['i'] ) && is_scalar( $entry['i'] ) ? absint( $entry['i'] ) : 0;
				$price = isset( $entry['p'] ) && is_numeric( $entry['p'] ) ? (float) $entry['p'] : null;
				$stock = isset( $entry['s'] ) ? (bool) $entry['s'] : null;
				$time  = isset( $entry['t'] ) && is_numeric( $entry['t'] ) ? absint( $entry['t'] ) : 0;
			} elseif ( is_scalar( $entry ) ) {
				$id    = absint( $entry );
				$price = null;
				$stock = null;
				$time  = 0;
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
				'time'  => $time,
			);
		}

		return array_values( $items );
	}

	/**
	 * Compact form of the history items, as stored in the cookie and in user meta.
	 */
	private function encode_items( array $items ) {
		$payload = array();
		foreach ( $items as $item ) {
			$entry = array( 'i' => $item['id'] );
			if ( null !== $item['price'] ) {
				$entry['p'] = $item['price'];
			}
			if ( null !== $item['stock'] ) {
				$entry['s'] = $item['stock'] ? 1 : 0;
			}
			if ( $item['time'] > 0 ) {
				$entry['t'] = $item['time'];
			}
			$payload[] = $entry;
		}

		return $payload;
	}

	private function get_cookie_items() {
		if ( ! isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return array();
		}

		return $this->normalize_entries( json_decode( sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) ), true ) );
	}

	private function get_user_items() {
		if ( ! $this->is_sync_enabled() || ! is_user_logged_in() ) {
			return array();
		}

		return $this->normalize_entries( get_user_meta( get_current_user_id(), self::USER_META_KEY, true ) );
	}

	/**
	 * Merges two histories. When a product is in both, the more recent view wins; the result is
	 * ordered oldest first.
	 */
	private function merge_items( array $first, array $second ) {
		$by_id = array();
		foreach ( array_merge( $first, $second ) as $item ) {
			if ( ! isset( $by_id[ $item['id'] ] ) || $item['time'] >= $by_id[ $item['id'] ]['time'] ) {
				$by_id[ $item['id'] ] = $item;
			}
		}

		// Tie-break on the original position so the order is stable on every PHP version.
		$indexed = array();
		$position = 0;
		foreach ( $by_id as $item ) {
			$indexed[] = array( $position++, $item );
		}
		usort(
			$indexed,
			function ( $a, $b ) {
				return $a[1]['time'] === $b[1]['time'] ? $a[0] - $b[0] : $a[1]['time'] - $b[1]['time'];
			}
		);

		return array_column( $indexed, 1 );
	}

	/**
	 * The visitor's history, oldest first. Logged-in customers get their account history
	 * merged with the cookie of the current browser, so it follows them across devices.
	 *
	 * @return array[]
	 */
	private function get_viewed_items() {
		$cookie_items = $this->get_cookie_items();
		$user_items   = $this->get_user_items();

		return $user_items ? $this->merge_items( $user_items, $cookie_items ) : $cookie_items;
	}

	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings           = $this->get_settings();
		$selected_placement = $this->get_selected_placements();
		$style              = $this->get_style();
		$sync               = $this->sanitize_yes_no( get_option( 'product_view_history_for_woocommerce_sync_users', 'yes' ) );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Product View History for WooCommerce', 'websemicolon-product-view-history-for-woocommerce' ); ?></h1>
			<p><?php echo esc_html__( 'Show visitors the products they looked at recently. You can use the shortcode, the "Recently Viewed Products" block, or the automatic placements below.', 'websemicolon-product-view-history-for-woocommerce' ); ?></p>
			<p><code>[product_view_history_for_woocommerce]</code></p>
			<p><?php echo esc_html__( 'You can also override the default settings with shortcode attributes:', 'websemicolon-product-view-history-for-woocommerce' ); ?></p>
			<p><code>[product_view_history_for_woocommerce limit="6" title="Recently Seen" layout="carousel" show_price="no" badges="no" add_to_cart="yes"]</code></p>
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
							<input type="text" name="product_view_history_for_woocommerce_title" id="product_view_history_for_woocommerce_title" value="<?php echo esc_attr( $settings['title'] ); ?>" class="regular-text" data-pvh-preview="title" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="product_view_history_for_woocommerce_layout"><?php echo esc_html__( 'Layout', 'websemicolon-product-view-history-for-woocommerce' ); ?></label>
						</th>
						<td>
							<select name="product_view_history_for_woocommerce_layout" id="product_view_history_for_woocommerce_layout" data-pvh-preview="layout">
								<?php foreach ( $this->get_layout_labels() as $layout_key => $layout_label ) : ?>
									<option value="<?php echo esc_attr( $layout_key ); ?>" <?php selected( $settings['layout'], $layout_key ); ?>><?php echo esc_html( $layout_label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="product_view_history_for_woocommerce_show_price"><?php echo esc_html__( 'Show product price', 'websemicolon-product-view-history-for-woocommerce' ); ?></label>
						</th>
						<td>
							<select name="product_view_history_for_woocommerce_show_price" id="product_view_history_for_woocommerce_show_price" data-pvh-preview="show_price">
								<option value="yes" <?php selected( $settings['show_price'], 'yes' ); ?>><?php echo esc_html__( 'Yes', 'websemicolon-product-view-history-for-woocommerce' ); ?></option>
								<option value="no" <?php selected( $settings['show_price'], 'no' ); ?>><?php echo esc_html__( 'No', 'websemicolon-product-view-history-for-woocommerce' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="product_view_history_for_woocommerce_add_to_cart"><?php echo esc_html__( 'Show "Add to cart" button', 'websemicolon-product-view-history-for-woocommerce' ); ?></label>
						</th>
						<td>
							<select name="product_view_history_for_woocommerce_add_to_cart" id="product_view_history_for_woocommerce_add_to_cart" data-pvh-preview="add_to_cart">
								<option value="yes" <?php selected( $settings['add_to_cart'], 'yes' ); ?>><?php echo esc_html__( 'Yes', 'websemicolon-product-view-history-for-woocommerce' ); ?></option>
								<option value="no" <?php selected( $settings['add_to_cart'], 'no' ); ?>><?php echo esc_html__( 'No', 'websemicolon-product-view-history-for-woocommerce' ); ?></option>
							</select>
							<p class="description"><?php echo esc_html__( 'Uses the standard WooCommerce button, including AJAX add to cart when it is enabled in WooCommerce. Variable products show "Select options".', 'websemicolon-product-view-history-for-woocommerce' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="product_view_history_for_woocommerce_show_badges"><?php echo esc_html__( 'Show change badges', 'websemicolon-product-view-history-for-woocommerce' ); ?></label>
						</th>
						<td>
							<select name="product_view_history_for_woocommerce_show_badges" id="product_view_history_for_woocommerce_show_badges" data-pvh-preview="badges">
								<option value="yes" <?php selected( $settings['badges'], 'yes' ); ?>><?php echo esc_html__( 'Yes', 'websemicolon-product-view-history-for-woocommerce' ); ?></option>
								<option value="no" <?php selected( $settings['badges'], 'no' ); ?>><?php echo esc_html__( 'No', 'websemicolon-product-view-history-for-woocommerce' ); ?></option>
							</select>
							<p class="description"><?php echo esc_html__( 'Highlights what changed since the visitor viewed a product: price dropped, back in stock, low stock or out of stock.', 'websemicolon-product-view-history-for-woocommerce' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="product_view_history_for_woocommerce_sync_users"><?php echo esc_html__( 'Sync for logged-in customers', 'websemicolon-product-view-history-for-woocommerce' ); ?></label>
						</th>
						<td>
							<select name="product_view_history_for_woocommerce_sync_users" id="product_view_history_for_woocommerce_sync_users">
								<option value="yes" <?php selected( $sync, 'yes' ); ?>><?php echo esc_html__( 'Yes', 'websemicolon-product-view-history-for-woocommerce' ); ?></option>
								<option value="no" <?php selected( $sync, 'no' ); ?>><?php echo esc_html__( 'No', 'websemicolon-product-view-history-for-woocommerce' ); ?></option>
							</select>
							<p class="description"><?php echo esc_html__( 'Saves the history in the customer account so it follows them across devices. Guests only use a cookie in their own browser.', 'websemicolon-product-view-history-for-woocommerce' ); ?></p>
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

				<h2><?php echo esc_html__( 'Appearance', 'websemicolon-product-view-history-for-woocommerce' ); ?></h2>
				<p class="description"><?php echo esc_html__( 'Customize the fonts, sizes and colors. Use the "Default" button of a color to go back to the built-in color.', 'websemicolon-product-view-history-for-woocommerce' ); ?></p>

				<?php $this->render_preview( $settings ); ?>

				<table class="form-table">
					<?php foreach ( $this->get_style_fields() as $key => $field ) : ?>
						<?php
						$field_id   = 'product_view_history_for_woocommerce_style_' . $key;
						$field_name = 'product_view_history_for_woocommerce_style[' . $key . ']';
						$unit       = isset( $field['unit'] ) ? $field['unit'] : '';
						?>
						<tr>
							<th scope="row">
								<label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
							</th>
							<td>
								<?php if ( 'font' === $field['type'] ) : ?>
									<select name="<?php echo esc_attr( $field_name ); ?>" id="<?php echo esc_attr( $field_id ); ?>" data-pvh-var="<?php echo esc_attr( $field['var'] ); ?>">
										<?php foreach ( $this->get_font_labels() as $font_key => $font_label ) : ?>
											<option value="<?php echo esc_attr( $font_key ); ?>" <?php selected( $style[ $key ], $font_key ); ?>><?php echo esc_html( $font_label ); ?></option>
										<?php endforeach; ?>
									</select>
								<?php elseif ( 'number' === $field['type'] ) : ?>
									<input type="number" min="<?php echo esc_attr( self::STYLE_RANGES[ $key ][0] ); ?>" max="<?php echo esc_attr( self::STYLE_RANGES[ $key ][1] ); ?>" name="<?php echo esc_attr( $field_name ); ?>" id="<?php echo esc_attr( $field_id ); ?>" value="<?php echo esc_attr( $style[ $key ] ); ?>" class="small-text" data-pvh-var="<?php echo esc_attr( $field['var'] ); ?>" data-pvh-unit="<?php echo esc_attr( $unit ); ?>" />
								<?php else : ?>
									<input type="text" name="<?php echo esc_attr( $field_name ); ?>" id="<?php echo esc_attr( $field_id ); ?>" value="<?php echo esc_attr( $style[ $key ] ); ?>" class="pvh-color-field" data-default-color="<?php echo esc_attr( self::STYLE_DEFAULTS[ $key ] ); ?>" data-pvh-var="<?php echo esc_attr( $field['var'] ); ?>" />
								<?php endif; ?>
								<?php if ( ! empty( $field['help'] ) ) : ?>
									<p class="description"><?php echo esc_html( $field['help'] ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Live preview: the same markup and CSS as the front end, with sample products. admin.js updates
	 * its CSS variables and classes while the settings are being edited.
	 */
	private function render_preview( array $settings ) {
		$badges = array(
			1 => array( 'drop', __( 'Price dropped 20%', 'websemicolon-product-view-history-for-woocommerce' ) ),
			2 => array( 'restock', __( 'Back in stock', 'websemicolon-product-view-history-for-woocommerce' ) ),
			3 => array( 'low', sprintf( _n( 'Only %d left', 'Only %d left', 2, 'websemicolon-product-view-history-for-woocommerce' ), 2 ) ),
			4 => array( 'out', __( 'Out of stock', 'websemicolon-product-view-history-for-woocommerce' ) ),
		);
		?>
		<div class="pvh-preview-wrap">
			<p class="pvh-preview-label"><strong><?php echo esc_html__( 'Live preview', 'websemicolon-product-view-history-for-woocommerce' ); ?></strong></p>
			<div id="pvh-preview" class="product-view-history-for-woocommerce-wrapper pvh-layout-<?php echo esc_attr( $settings['layout'] ); ?><?php echo 'yes' === $settings['show_price'] ? '' : ' pvh-hide-price'; ?><?php echo 'yes' === $settings['badges'] ? '' : ' pvh-hide-badges'; ?><?php echo 'yes' === $settings['add_to_cart'] ? '' : ' pvh-hide-cart'; ?>">
				<h2 class="pvh-section-title" id="pvh-preview-title"><?php echo esc_html( $settings['title'] ); ?></h2>
				<div class="pvh-viewport">
					<button type="button" class="pvh-carousel-btn pvh-carousel-prev" tabindex="-1"><span aria-hidden="true">&#8249;</span></button>
					<div class="pvh-product-grid">
						<?php for ( $i = 1; $i <= 6; $i++ ) : ?>
							<div class="pvh-product-item">
								<?php if ( isset( $badges[ $i ] ) ) : ?>
									<span class="pvh-badge pvh-badge--<?php echo esc_attr( $badges[ $i ][0] ); ?>"><?php echo esc_html( $badges[ $i ][1] ); ?></span>
								<?php endif; ?>
								<span class="pvh-product-link">
									<span class="pvh-preview-img"></span>
									<span class="pvh-product-info">
										<span class="pvh-product-title">
											<?php
											/* translators: %d: sample product number. */
											echo esc_html( sprintf( __( 'Sample product %d', 'websemicolon-product-view-history-for-woocommerce' ), $i ) );
											?>
										</span>
										<span class="pvh-product-price">$<?php echo esc_html( number_format_i18n( 19 + $i * 5, 2 ) ); ?></span>
									</span>
								</span>
								<div class="pvh-product-actions">
									<span class="button"><?php echo esc_html__( 'Add to cart', 'websemicolon-product-view-history-for-woocommerce' ); ?></span>
								</div>
							</div>
						<?php endfor; ?>
					</div>
					<button type="button" class="pvh-carousel-btn pvh-carousel-next" tabindex="-1"><span aria-hidden="true">&#8250;</span></button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Extra CSS that only the settings page needs (the preview box and its toggles).
	 */
	private function get_preview_styles() {
		return '
		.pvh-preview-wrap {
			position: sticky;
			top: 40px;
			z-index: 9;
			margin: 1rem 0;
			padding: 1rem;
			max-width: 900px;
			background: #f0f0f1;
			border: 1px solid #c3c4c7;
			box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
		}
		.pvh-preview-wrap .pvh-preview-label { margin: 0 0 0.5rem; }
		.pvh-preview-wrap .product-view-history-for-woocommerce-wrapper { margin: 0; }
		.pvh-preview-img { display: block; height: 70px; margin-bottom: 0.6rem; background: #e5e7eb; border-radius: calc(var(--pvh-radius) * 0.7); }
		.pvh-preview-wrap .pvh-product-title { display: block; }
		.pvh-preview-wrap .pvh-product-actions .button { display: block; text-align: center; }
		.pvh-preview-wrap .pvh-layout-list .pvh-product-grid { max-height: 45vh; overflow-y: auto; }
		.pvh-hide-price .pvh-product-price,
		.pvh-hide-badges .pvh-badge,
		.pvh-hide-cart .pvh-product-actions { display: none; }
		';
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
	 * baked into cached pages expire, and this only writes the caller's own cookie
	 * (and, for logged-in customers, their own history).
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

		$now      = time();
		$existing = $this->get_viewed_items();

		// A refresh or a quick return to the same product is not counted as another view.
		$recently_viewed = false;
		foreach ( $existing as $item ) {
			if ( $item['id'] === $product_id && $item['time'] > $now - self::VIEW_DEDUP_SECONDS ) {
				$recently_viewed = true;
			}
		}
		if ( ! $recently_viewed ) {
			$this->count_view( $product );
		}

		$items = array_filter(
			$existing,
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
			'time'  => $now,
		);
		$payload = $this->encode_items( array_slice( array_values( $items ), -self::MAX_PRODUCTS ) );

		$cookie_value = wp_json_encode( $payload );
		if ( ! $cookie_value ) {
			$cookie_value = '[]';
		}

		if ( ! headers_sent() ) {
			setcookie(
				self::COOKIE_NAME,
				$cookie_value,
				array(
					'expires'  => $now + ( 30 * DAY_IN_SECONDS ),
					'path'     => defined( 'COOKIEPATH' ) ? COOKIEPATH : '/',
					'domain'   => defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
		}

		if ( $this->is_sync_enabled() && is_user_logged_in() ) {
			update_user_meta( get_current_user_id(), self::USER_META_KEY, $payload );
		}
	}

	/**
	 * Adds one view to the product's anonymous counter used by the report. Shop managers are
	 * not counted so that checking products does not skew the numbers.
	 */
	private function count_view( $product ) {
		if ( current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$product_id = $product->get_id();
		$views      = (int) get_post_meta( $product_id, self::META_VIEWS, true );

		// Remember the sales total when tracking starts, so the report compares views and sales over the same period.
		if ( '' === get_post_meta( $product_id, self::META_SALES_BASE, true ) ) {
			update_post_meta( $product_id, self::META_SALES_BASE, (int) $product->get_total_sales() );
		}

		update_post_meta( $product_id, self::META_VIEWS, $views + 1 );
	}

	/**
	 * Outputs the placeholder for an enabled automatic placement.
	 */
	public function render_placement() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		// The placeholder is built entirely from escaped values in get_placeholder().
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->get_placeholder( $this->get_settings() );
	}

	public function render_products( $atts = array() ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return '<p>' . esc_html__( 'WooCommerce is required for this plugin to work.', 'websemicolon-product-view-history-for-woocommerce' ) . '</p>';
		}

		$atts = shortcode_atts(
			$this->get_settings(),
			$atts,
			'product_view_history_for_woocommerce'
		);

		return $this->get_placeholder( $atts );
	}

	/**
	 * Server-side render callback of the block. "default" or empty attributes fall back to the saved settings.
	 */
	public function render_block( $attributes ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return '';
		}

		$args = $this->get_settings();

		if ( ! empty( $attributes['limit'] ) ) {
			$args['limit'] = $attributes['limit'];
		}
		if ( isset( $attributes['title'] ) && '' !== $attributes['title'] ) {
			$args['title'] = $attributes['title'];
		}
		$map = array(
			'showPrice' => 'show_price',
			'badges'    => 'badges',
			'layout'    => 'layout',
			'addToCart' => 'add_to_cart',
		);
		foreach ( $map as $attribute => $key ) {
			if ( isset( $attributes[ $attribute ] ) && 'default' !== $attributes[ $attribute ] && '' !== $attributes[ $attribute ] ) {
				$args[ $key ] = $attributes[ $attribute ];
			}
		}

		return $this->get_placeholder( $args );
	}

	/**
	 * The list is per visitor, so it is filled in by the browser (see assets/js/view-history.js).
	 * This keeps the page HTML identical for everyone and therefore safe to cache.
	 *
	 * @param array $args Keys: limit, title, show_price, badges, layout, add_to_cart.
	 */
	private function get_placeholder( array $args ) {
		$this->enqueue_assets();

		return sprintf(
			'<div class="pvh-async" data-limit="%1$d" data-title="%2$s" data-show-price="%3$s" data-badges="%4$s" data-layout="%5$s" data-add-to-cart="%6$s"></div>',
			min( self::MAX_PRODUCTS, max( 1, absint( isset( $args['limit'] ) ? $args['limit'] : 5 ) ) ),
			esc_attr( sanitize_text_field( isset( $args['title'] ) ? $args['title'] : $this->get_default_title() ) ),
			esc_attr( $this->sanitize_yes_no( isset( $args['show_price'] ) ? $args['show_price'] : 'yes' ) ),
			esc_attr( $this->sanitize_yes_no( isset( $args['badges'] ) ? $args['badges'] : 'yes' ) ),
			esc_attr( $this->sanitize_layout( isset( $args['layout'] ) ? $args['layout'] : 'grid' ) ),
			esc_attr( $this->sanitize_no_yes( isset( $args['add_to_cart'] ) ? $args['add_to_cart'] : 'no' ) )
		);
	}

	public function ajax_render() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$page_url = isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : '';
		$page_url = $page_url ? wp_validate_redirect( $page_url, home_url( '/' ) ) : home_url( '/' );

		$this->page_url = remove_query_arg( array( 'add-to-cart', 'added-to-cart' ), $page_url );

		$args = array(
			'limit'       => isset( $_POST['limit'] ) ? absint( wp_unslash( $_POST['limit'] ) ) : 5,
			'title'       => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : $this->get_default_title(),
			'show_price'  => isset( $_POST['show_price'] ) && 'yes' === $_POST['show_price'],
			'badges'      => isset( $_POST['badges'] ) && 'yes' === $_POST['badges'],
			'layout'      => isset( $_POST['layout'] ) ? $this->sanitize_layout( sanitize_key( wp_unslash( $_POST['layout'] ) ) ) : 'grid',
			'add_to_cart' => isset( $_POST['add_to_cart'] ) && 'yes' === $_POST['add_to_cart'],
			'current_id'  => isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0,
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( $args['add_to_cart'] ) {
			add_filter( 'woocommerce_product_add_to_cart_url', array( $this, 'filter_add_to_cart_url' ), 10, 2 );
		}

		wp_send_json_success(
			array(
				'html' => $this->build_markup( $args ),
			)
		);
	}

	/**
	 * WooCommerce builds "add to cart" links from the current request URL, which inside an AJAX
	 * response is admin-ajax.php. Point them at the page the visitor is actually on.
	 */
	public function filter_add_to_cart_url( $url, $product ) {
		if ( false !== strpos( $url, 'add-to-cart=' ) ) {
			return add_query_arg( 'add-to-cart', $product->get_id(), $this->page_url );
		}

		return $url;
	}

	/**
	 * Decides which single badge (if any) to show for a product the visitor saw before.
	 *
	 * @param WC_Product $product Current product.
	 * @param array      $item    Snapshot from the visitor's history.
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

	/**
	 * @param array $args Keys: limit, title, show_price, badges, layout, add_to_cart (bools), current_id.
	 */
	private function build_markup( array $args ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return '';
		}

		$limit       = min( self::MAX_PRODUCTS, max( 1, absint( $args['limit'] ) ) );
		$title       = $args['title'];
		$show_price  = ! empty( $args['show_price'] );
		$show_badges = ! empty( $args['badges'] );
		$add_to_cart = ! empty( $args['add_to_cart'] ) && function_exists( 'woocommerce_template_loop_add_to_cart' );
		$layout      = $this->sanitize_layout( $args['layout'] );
		$current_id  = absint( $args['current_id'] );

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
		<div class="product-view-history-for-woocommerce-wrapper pvh-layout-<?php echo esc_attr( $layout ); ?>">
			<h2 class="pvh-section-title"><?php echo esc_html( $title ); ?></h2>
			<div class="pvh-viewport">
				<?php if ( 'carousel' === $layout ) : ?>
					<button type="button" class="pvh-carousel-btn pvh-carousel-prev" aria-label="<?php echo esc_attr__( 'Previous products', 'websemicolon-product-view-history-for-woocommerce' ); ?>" hidden><span aria-hidden="true">&#8249;</span></button>
				<?php endif; ?>
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
								<div class="pvh-product-info">
									<h3 class="pvh-product-title"><?php echo esc_html( get_the_title() ); ?></h3>
									<?php if ( $show_price && $product->get_price_html() ) : ?>
										<span class="pvh-product-price"><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
									<?php endif; ?>
								</div>
							</a>
							<?php if ( $add_to_cart ) : ?>
								<div class="pvh-product-actions">
									<?php
									$GLOBALS['product'] = $product;
									// Output is escaped by WooCommerce's own template.
									woocommerce_template_loop_add_to_cart();
									?>
								</div>
							<?php endif; ?>
						</div>
						<?php
					}
					wp_reset_postdata();
					?>
				</div>
				<?php if ( 'carousel' === $layout ) : ?>
					<button type="button" class="pvh-carousel-btn pvh-carousel-next" aria-label="<?php echo esc_attr__( 'Next products', 'websemicolon-product-view-history-for-woocommerce' ); ?>"><span aria-hidden="true">&#8250;</span></button>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Report: most viewed products and how many of them were bought since tracking started.
	 */
	public function render_report_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$only_unsold = isset( $_GET['pvh_filter'] ) && 'unsold' === $_GET['pvh_filter'];
		$was_reset   = isset( $_GET['pvh_reset'] );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$report_url = admin_url( 'admin.php?page=' . self::MENU_SLUG . '-report' );
		$rows       = array();

		if ( class_exists( 'WooCommerce' ) ) {
			$query = new WP_Query(
				array(
					'post_type'      => 'product',
					'post_status'    => 'publish',
					'posts_per_page' => 200,
					'meta_key'       => self::META_VIEWS, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'orderby'        => 'meta_value_num',
					'order'          => 'DESC',
					'no_found_rows'  => true,
				)
			);

			foreach ( $query->posts as $post ) {
				$product = wc_get_product( $post->ID );
				if ( ! $product ) {
					continue;
				}

				$views = (int) get_post_meta( $post->ID, self::META_VIEWS, true );
				$sold  = max( 0, (int) $product->get_total_sales() - (int) get_post_meta( $post->ID, self::META_SALES_BASE, true ) );
				if ( $only_unsold && $sold > 0 ) {
					continue;
				}

				$rows[] = array(
					'product' => $product,
					'views'   => $views,
					'sold'    => $sold,
				);
			}
			$rows = array_slice( $rows, 0, 50 );
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Viewed but not purchased', 'websemicolon-product-view-history-for-woocommerce' ); ?></h1>
			<?php if ( $was_reset ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'View statistics were reset.', 'websemicolon-product-view-history-for-woocommerce' ); ?></p></div>
			<?php endif; ?>
			<p><?php echo esc_html__( 'Your most viewed products and how many units were sold while views were being counted. Products with many views and no sales may need a better price, photos or description. Each visit counts once per 30 minutes per visitor, and shop managers are not counted.', 'websemicolon-product-view-history-for-woocommerce' ); ?></p>

			<ul class="subsubsub">
				<li><a href="<?php echo esc_url( $report_url ); ?>" class="<?php echo $only_unsold ? '' : 'current'; ?>"><?php echo esc_html__( 'All viewed products', 'websemicolon-product-view-history-for-woocommerce' ); ?></a> | </li>
				<li><a href="<?php echo esc_url( add_query_arg( 'pvh_filter', 'unsold', $report_url ) ); ?>" class="<?php echo $only_unsold ? 'current' : ''; ?>"><?php echo esc_html__( 'Not purchased yet', 'websemicolon-product-view-history-for-woocommerce' ); ?></a></li>
			</ul>

			<table class="wp-list-table widefat striped" style="margin-top: 1rem;">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Product', 'websemicolon-product-view-history-for-woocommerce' ); ?></th>
						<th><?php echo esc_html__( 'Views', 'websemicolon-product-view-history-for-woocommerce' ); ?></th>
						<th><?php echo esc_html__( 'Units sold', 'websemicolon-product-view-history-for-woocommerce' ); ?></th>
						<th><?php echo esc_html__( 'Purchases per 100 views', 'websemicolon-product-view-history-for-woocommerce' ); ?></th>
						<th><?php echo esc_html__( 'Price', 'websemicolon-product-view-history-for-woocommerce' ); ?></th>
						<th><?php echo esc_html__( 'Stock', 'websemicolon-product-view-history-for-woocommerce' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="6"><?php echo esc_html__( 'No data yet. Views are counted as visitors open product pages.', 'websemicolon-product-view-history-for-woocommerce' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td>
								<a href="<?php echo esc_url( get_edit_post_link( $row['product']->get_id() ) ); ?>"><strong><?php echo esc_html( $row['product']->get_name() ); ?></strong></a>
							</td>
							<td><?php echo esc_html( number_format_i18n( $row['views'] ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $row['sold'] ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $row['views'] ? $row['sold'] / $row['views'] * 100 : 0, 1 ) ); ?></td>
							<td><?php echo wp_kses_post( $row['product']->get_price_html() ); ?></td>
							<td><?php echo $row['product']->is_in_stock() ? esc_html__( 'In stock', 'websemicolon-product-view-history-for-woocommerce' ) : esc_html__( 'Out of stock', 'websemicolon-product-view-history-for-woocommerce' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pvh-reset-form" style="margin-top: 1.5rem;">
				<input type="hidden" name="action" value="product_view_history_reset_stats" />
				<?php wp_nonce_field( 'product_view_history_reset_stats' ); ?>
				<button type="submit" class="button"><?php echo esc_html__( 'Reset statistics', 'websemicolon-product-view-history-for-woocommerce' ); ?></button>
			</form>
		</div>
		<?php
	}

	public function handle_reset_stats() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'websemicolon-product-view-history-for-woocommerce' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'product_view_history_reset_stats' );

		delete_post_meta_by_key( self::META_VIEWS );
		delete_post_meta_by_key( self::META_SALES_BASE );

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG . '-report&pvh_reset=1' ) );
		exit;
	}

	public function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		wp_add_privacy_policy_content(
			__( 'Recently Viewed Products for WooCommerce', 'websemicolon-product-view-history-for-woocommerce' ),
			wp_kses_post(
				wpautop(
					__( 'This site remembers the products a visitor viewed in a cookie named product_view_history_for_woocommerce. It holds product IDs, the price and stock status the product had at that moment, and the time of the view, and it lasts 30 days. For logged-in customers the same list is also saved in their account so it can follow them across devices; it is included in personal data exports and removed by erasure requests. The site also keeps an anonymous view counter per product, which is not linked to any visitor.', 'websemicolon-product-view-history-for-woocommerce' )
				)
			)
		);
	}

	public function register_privacy_exporter( $exporters ) {
		$exporters['websemicolon-product-view-history'] = array(
			'exporter_friendly_name' => __( 'Recently viewed products', 'websemicolon-product-view-history-for-woocommerce' ),
			'callback'               => array( $this, 'export_personal_data' ),
		);

		return $exporters;
	}

	public function register_privacy_eraser( $erasers ) {
		$erasers['websemicolon-product-view-history'] = array(
			'eraser_friendly_name' => __( 'Recently viewed products', 'websemicolon-product-view-history-for-woocommerce' ),
			'callback'             => array( $this, 'erase_personal_data' ),
		);

		return $erasers;
	}

	public function export_personal_data( $email_address ) {
		$user = get_user_by( 'email', $email_address );
		$data = array();

		if ( $user ) {
			foreach ( $this->normalize_entries( get_user_meta( $user->ID, self::USER_META_KEY, true ) ) as $item ) {
				$data[] = array(
					'group_id'    => 'product-view-history',
					'group_label' => __( 'Recently viewed products', 'websemicolon-product-view-history-for-woocommerce' ),
					'item_id'     => 'product-view-history-' . $item['id'],
					'data'        => array(
						array(
							'name'  => __( 'Product', 'websemicolon-product-view-history-for-woocommerce' ),
							'value' => get_the_title( $item['id'] ),
						),
						array(
							'name'  => __( 'Viewed on', 'websemicolon-product-view-history-for-woocommerce' ),
							'value' => $item['time'] ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $item['time'] ) : '',
						),
					),
				);
			}
		}

		return array(
			'data' => $data,
			'done' => true,
		);
	}

	public function erase_personal_data( $email_address ) {
		$user    = get_user_by( 'email', $email_address );
		$removed = false;

		if ( $user && metadata_exists( 'user', $user->ID, self::USER_META_KEY ) ) {
			delete_user_meta( $user->ID, self::USER_META_KEY );
			$removed = true;
		}

		return array(
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	private function get_inline_styles() {
		$style = $this->get_style();

		// Every value here was validated in sanitize_style(): whitelisted font, clamped integers, hex colors.
		$variables = array(
			'font'             => self::FONT_STACKS[ $style['font_family'] ],
			'visible'          => (string) $style['columns'],
			'radius'           => $style['radius'] . 'px',
			'title-size'       => $style['title_size'] . 'px',
			'item-title-size'  => $style['item_title_size'] . 'px',
			'price-size'       => $style['price_size'] . 'px',
			'badge-size'       => $style['badge_size'] . 'px',
			'title-color'      => $style['title_color'],
			'item-title-color' => $style['item_title_color'],
			'price-color'      => $style['price_color'],
			'background'       => $style['background_color'],
			'card'             => $style['card_color'],
			'border'           => $style['border_color'],
			'drop'             => $style['drop_color'],
			'restock'          => $style['restock_color'],
			'low'              => $style['low_color'],
			'out'              => $style['out_color'],
		);

		$declarations = '';
		foreach ( $variables as $name => $value ) {
			$declarations .= '--pvh-' . $name . ':' . $value . ';';
		}

		$tablet_columns = min( 3, (int) $style['columns'] );

		return '
		.product-view-history-for-woocommerce-wrapper {
			' . $declarations . '
			margin: 2rem 0;
			padding: 1.25rem;
			background: var(--pvh-background);
			border: 1px solid var(--pvh-border);
			border-radius: calc(var(--pvh-radius) + 2px);
			box-shadow: 0 6px 18px rgba(15, 23, 42, 0.06);
			font-family: var(--pvh-font);
			text-align: start;
		}
		.pvh-section-title {
			display: block;
			margin: 0 0 1rem;
			padding: 0;
			font-family: inherit;
			font-size: var(--pvh-title-size);
			font-weight: 700;
			line-height: 1.3;
			color: var(--pvh-title-color);
		}
		.pvh-viewport {
			position: relative;
		}
		.pvh-product-grid {
			display: grid;
			grid-template-columns: repeat(var(--pvh-visible), minmax(0, 1fr));
			gap: 1rem;
		}
		.pvh-product-item {
			position: relative;
			display: flex;
			flex-direction: column;
			background: var(--pvh-card);
			border: 1px solid var(--pvh-border);
			border-radius: var(--pvh-radius);
			padding: 0.75rem;
			transition: transform 0.2s ease, box-shadow 0.2s ease;
		}
		.pvh-product-item:hover {
			transform: translateY(-2px);
			box-shadow: 0 10px 20px rgba(15, 23, 42, 0.08);
		}
		.pvh-product-link {
			display: block;
			flex: 1 1 auto;
			color: inherit;
			text-decoration: none;
		}
		.pvh-product-link img {
			width: 100%;
			height: auto;
			max-height: 200px;
			object-fit: scale-down;
			border-radius: calc(var(--pvh-radius) * 0.7);
			margin-bottom: 0.75rem;
			display: block;
		}
		.pvh-product-title {
			margin: 0 0 0.4rem;
			font-family: inherit;
			font-size: var(--pvh-item-title-size);
			font-weight: 600;
			line-height: 1.4;
			color: var(--pvh-item-title-color);
		}
		.pvh-product-price {
			display: inline-block;
			font-family: inherit;
			font-size: var(--pvh-price-size);
			font-weight: 700;
			color: var(--pvh-price-color);
		}
		.pvh-product-actions {
			margin-top: 0.6rem;
		}
		.pvh-product-actions .button {
			display: block;
			width: 100%;
			box-sizing: border-box;
			margin: 0;
			text-align: center;
		}
		.pvh-product-actions .added_to_cart {
			display: block;
			margin-top: 0.4rem;
			font-size: 0.85em;
			text-align: center;
		}
		.pvh-badge {
			position: absolute;
			z-index: 1;
			top: 1.1rem;
			inset-inline-start: 1.1rem;
			padding: 0.2rem 0.55rem;
			border-radius: 999px;
			font-size: var(--pvh-badge-size);
			font-weight: 700;
			line-height: 1.4;
			color: #ffffff;
			background: #6b7280;
			pointer-events: none;
		}
		.pvh-badge--drop {
			background: var(--pvh-drop);
		}
		.pvh-badge--restock {
			background: var(--pvh-restock);
		}
		.pvh-badge--low {
			background: var(--pvh-low);
		}
		.pvh-badge--out {
			background: var(--pvh-out);
		}
		.pvh-carousel-btn {
			display: none;
			position: absolute;
			top: 50%;
			z-index: 2;
			width: 2.25rem;
			height: 2.25rem;
			margin: 0;
			padding: 0;
			align-items: center;
			justify-content: center;
			transform: translateY(-50%);
			border: 1px solid var(--pvh-border);
			border-radius: 999px;
			background: var(--pvh-background);
			color: var(--pvh-title-color);
			font-size: 1.5rem;
			line-height: 1;
			cursor: pointer;
			box-shadow: 0 2px 8px rgba(15, 23, 42, 0.18);
		}
		.pvh-carousel-prev {
			inset-inline-start: -0.75rem;
		}
		.pvh-carousel-next {
			inset-inline-end: -0.75rem;
		}
		.pvh-layout-carousel .pvh-carousel-btn {
			display: flex;
		}
		.pvh-layout-carousel .pvh-carousel-btn[hidden] {
			display: none;
		}
		html[dir="rtl"] .pvh-carousel-btn span {
			display: inline-block;
			transform: scaleX(-1);
		}
		.pvh-layout-carousel .pvh-product-grid {
			display: flex;
			overflow-x: auto;
			padding: 0.25rem 0;
			scroll-snap-type: x mandatory;
			scroll-behavior: smooth;
			scrollbar-width: thin;
		}
		.pvh-layout-carousel .pvh-product-item {
			flex: 0 0 calc((100% - (var(--pvh-visible) - 1) * 1rem) / var(--pvh-visible));
			scroll-snap-align: start;
		}
		.pvh-layout-list .pvh-product-grid {
			grid-template-columns: 1fr;
			gap: 0.75rem;
		}
		.pvh-layout-list .pvh-product-item {
			flex-direction: row;
			flex-wrap: wrap;
			align-items: center;
			gap: 1rem;
		}
		.pvh-layout-list .pvh-product-link {
			display: flex;
			align-items: center;
			gap: 1rem;
			min-width: 0;
		}
		.pvh-layout-list .pvh-product-link img,
		.pvh-layout-list .pvh-preview-img {
			width: 96px;
			max-height: none;
			flex: 0 0 96px;
			margin: 0;
		}
		.pvh-layout-list .pvh-product-info {
			flex: 1 1 auto;
			min-width: 0;
		}
		.pvh-layout-list .pvh-product-actions {
			margin: 0;
			flex: 0 0 auto;
		}
		.pvh-layout-list .pvh-product-actions .button {
			width: auto;
		}
		@media (max-width: 1024px) {
			.product-view-history-for-woocommerce-wrapper {
				--pvh-visible: ' . $tablet_columns . ';
			}
		}
		@media (max-width: 767px) {
			.product-view-history-for-woocommerce-wrapper {
				--pvh-visible: 2;
			}
		}
		';
	}
}
