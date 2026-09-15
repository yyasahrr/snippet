<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AAP_Plugin {
	private static $instance = null;

	/** @var AAP_Renderer */
	private $renderer;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		require_once AAP_PATH . 'includes/class-aap-renderer.php';
		$this->renderer = new AAP_Renderer();

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'register_assets' ) );
		add_action( 'init', array( $this, 'register_shortcodes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'plugins_loaded', array( $this, 'maybe_boot_elementor' ) );
		add_filter( 'woocommerce_add_to_cart_fragments', array( $this, 'cart_count_fragment' ) );
	}

	public function load_textdomain() {
		load_plugin_textdomain(
			'asadzadeh-smart-pages',
			false,
			dirname( AAP_BASENAME ) . '/languages'
		);
	}

	public function register_assets() {
		wp_register_style(
			'aap-frontend',
			AAP_URL . 'assets/css/aap-frontend.css',
			array(),
			AAP_VERSION
		);

		wp_register_script(
			'aap-frontend',
			AAP_URL . 'assets/js/aap-frontend.js',
			array(),
			AAP_VERSION,
			true
		);
	}

	public function enqueue_frontend_assets() {
		/**
		 * Frontend assets are intentionally tiny and can be disabled via filter.
		 * Return false from aap_enqueue_assets to handle enqueue manually.
		 */
		if ( ! apply_filters( 'aap_enqueue_assets', true ) ) {
			return;
		}
		wp_enqueue_style( 'aap-frontend' );
		wp_enqueue_script( 'aap-frontend' );
	}

	public function register_shortcodes() {
		// Homepage - two aliases for backward compat with snippetHomepage.php
		add_shortcode( 'asadzadeh_home', array( $this, 'render_home_shortcode' ) );
		add_shortcode( 'luxury_academy_home', array( $this, 'render_home_shortcode' ) );

		// Courses archive
		add_shortcode( 'asadzadeh_courses', array( $this, 'render_courses_shortcode' ) );
		add_shortcode( 'asadzadeh_archive', array( $this, 'render_courses_shortcode' ) );

		// About
		add_shortcode( 'asadzadeh_about', array( $this, 'render_about_shortcode' ) );

		// Contact
		add_shortcode( 'asadzadeh_contact', array( $this, 'render_contact_shortcode' ) );
	}

	public function render_home_shortcode( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'courses'  => '6',
				'category' => '',
			),
			(array) $atts,
			'asadzadeh_home'
		);
		return $this->renderer->render_home( $atts );
	}

	public function render_courses_shortcode( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'courses'  => '9',
				'category' => '',
				'search'   => '',
			),
			(array) $atts,
			'asadzadeh_courses'
		);
		return $this->renderer->render_courses( $atts );
	}

	public function render_about_shortcode( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'custom_class' => '',
			),
			(array) $atts,
			'asadzadeh_about'
		);
		return $this->renderer->render_about( $atts );
	}

	public function render_contact_shortcode( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'custom_class' => '',
			),
			(array) $atts,
			'asadzadeh_contact'
		);
		return $this->renderer->render_contact( $atts );
	}

	public function maybe_boot_elementor() {
		if ( ! did_action( 'elementor/loaded' ) ) {
			return;
		}
		require_once AAP_PATH . 'includes/class-aap-elementor.php';
		AAP_Elementor::instance( $this->renderer );
	}

	public function cart_count_fragment( $fragments ) {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $fragments;
		}
		$count = (int) WC()->cart->get_cart_contents_count();
		$html  = '<span class="aap-cart-count" aria-hidden="true">' . esc_html( $count ) . '</span>';
		$fragments['.aap-cart-count'] = $html;
		return $fragments;
	}

	/**
	 * Helpers used in templates
	 */
	public function get_courses_url() {
		if ( function_exists( 'tutor_utils' ) && is_object( tutor_utils() ) && method_exists( tutor_utils(), 'get_courses_page_url' ) ) {
			$maybe = tutor_utils()->get_courses_page_url();
			if ( $maybe ) {
				return $maybe;
			}
		}
		return home_url( '/doreha/' );
	}

	public function get_dashboard_url() {
		if ( function_exists( 'wc_get_account_endpoint_url' ) ) {
			return wc_get_account_endpoint_url( 'my-courses' );
		}
		return home_url( '/my-account/my-courses/' );
	}

	public function get_account_url() {
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			return wc_get_page_permalink( 'myaccount' );
		}
		return wp_login_url();
	}
}
