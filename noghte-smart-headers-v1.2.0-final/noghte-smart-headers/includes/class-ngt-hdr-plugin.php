<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NGT_HDR_Plugin {
	private static $instance = null;

	/** @var NGT_HDR_Renderer */
	private $renderer;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		require_once NGT_HDR_PATH . 'includes/class-ngt-hdr-renderer.php';
		$this->renderer = new NGT_HDR_Renderer();

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'register_assets' ) );
		add_action( 'init', array( $this, 'register_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'plugins_loaded', array( $this, 'maybe_boot_elementor' ) );
		add_filter( 'woocommerce_add_to_cart_fragments', array( $this, 'cart_count_fragment' ) );
	}

	public function load_textdomain() {
		load_plugin_textdomain(
			'noghte-smart-headers',
			false,
			dirname( plugin_basename( NGT_HDR_FILE ) ) . '/languages'
		);
	}

	public function register_assets() {
		wp_register_style(
			'ngt-hdr-frontend',
			NGT_HDR_URL . 'assets/css/ngt-hdr-frontend.css',
			array(),
			NGT_HDR_VERSION
		);

		wp_register_script(
			'ngt-hdr-frontend',
			NGT_HDR_URL . 'assets/js/ngt-hdr-frontend.js',
			array(),
			NGT_HDR_VERSION,
			true
		);
	}


	public function enqueue_frontend_assets() {
		/**
		 * Header assets are intentionally tiny and loaded site-wide because a header
		 * normally exists on every frontend route. Developers can disable this and
		 * rely on render-time enqueueing by returning false from this filter.
		 */
		if ( ! apply_filters( 'ngt_hdr_enqueue_assets', true ) ) {
			return;
		}

		wp_enqueue_style( 'ngt-hdr-frontend' );
		wp_enqueue_script( 'ngt-hdr-frontend' );
	}

	public function register_shortcode() {
		add_shortcode( 'noghte_header', array( $this, 'render_shortcode' ) );
		add_shortcode( 'ngt_header', array( $this, 'render_shortcode' ) );
	}

	public function render_shortcode( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'style'             => '1',
				'menu'              => '',
				'logo_id'           => '0',
				'logo_url'          => '',
				'logo_alt'          => '',
				'logo_width'        => '142',
				'mobile_logo_width' => '118',
				'search'            => 'yes',
				'search_type'       => 'all',
				'cart'              => 'yes',
				'account'           => 'yes',
				'cta_text'          => '',
				'cta_url'           => '',
				'cta_target'        => '_self',
				'sticky'            => 'no',
				'transparent'       => 'no',
				'full_width'        => 'no',
				'container_width'    => '1280',
				'height'             => '78',
				'mobile_height'      => '64',
				'mobile_breakpoint'  => '1024',
				'icon_size'          => '21',
				'mobile_icon_size'   => '20',
				'mobile_search'      => 'yes',
				'mobile_cart'        => 'yes',
				'mobile_account'     => 'yes',
				'background'         => '#ffffff',
				'text_color'         => '#15171a',
				'muted_color'        => '#68707a',
				'accent_color'       => '#2f6f58',
				'border_color'       => '#e9ecef',
				'icon_color'         => '#15171a',
				'search_icon_color'  => '',
				'menu_icon_color'    => '',
				'close_icon_color'   => '',
				'icon_hover_color'   => '#2f6f58',
				'custom_class'       => '',
			),
			(array) $atts,
			'noghte_header'
		);

		return $this->renderer->render( $atts );
	}

	public function maybe_boot_elementor() {
		if ( ! did_action( 'elementor/loaded' ) ) {
			return;
		}

		require_once NGT_HDR_PATH . 'includes/class-ngt-hdr-elementor.php';
		NGT_HDR_Elementor::instance( $this->renderer );
	}

	public function cart_count_fragment( $fragments ) {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $fragments;
		}

		$count = (int) WC()->cart->get_cart_contents_count();
		$html  = '<span class="ngt-hdr-cart-count" aria-hidden="true">' . esc_html( $count ) . '</span>';

		$fragments['.ngt-hdr-cart-count'] = $html;
		return $fragments;
	}
}
