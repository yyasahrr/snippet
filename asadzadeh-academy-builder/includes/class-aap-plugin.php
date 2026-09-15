<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class AAP_Plugin {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
            self::$instance->init();
        }
        return self::$instance;
    }

    private function init() {
        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
        add_action( 'init', array( $this, 'init_shortcodes' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
        add_action( 'elementor/widgets/register', array( $this, 'register_elementor_widgets' ) );
        add_action( 'elementor/elements/categories_registered', array( $this, 'register_elementor_category' ) );

        require_once AAP_PATH . 'includes/class-aap-data.php';
        require_once AAP_PATH . 'includes/class-aap-renderer.php';
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'asadzadeh-academy-builder', false, dirname( AAP_BASENAME ) . '/languages' );
    }

    public function register_assets() {
        wp_register_style( 'aap-frontend', AAP_URL . 'assets/css/frontend.css', array(), AAP_VERSION );
        wp_register_script( 'aap-frontend', AAP_URL . 'assets/js/frontend.js', array(), AAP_VERSION, true );
    }

    public function init_shortcodes() {
        require_once AAP_PATH . 'includes/class-aap-shortcodes.php';
        AAP_Shortcodes::init();
    }

    public function register_elementor_category( $elements_manager ) {
        $elements_manager->add_category(
            'asadzadeh-academy',
            array(
                'title' => esc_html__( 'آکادمی اسدزاده', 'asadzadeh-academy-builder' ),
                'icon'  => 'fa fa-graduation-cap',
            )
        );
    }

    public function register_elementor_widgets( $widgets_manager ) {
        if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
            return;
        }
        require_once AAP_PATH . 'includes/widgets/class-widget-homepage.php';
        require_once AAP_PATH . 'includes/widgets/class-widget-courses.php';
        require_once AAP_PATH . 'includes/widgets/class-widget-about.php';
        require_once AAP_PATH . 'includes/widgets/class-widget-contact.php';

        $widgets_manager->register( new AAP_Widget_Homepage() );
        $widgets_manager->register( new AAP_Widget_Courses() );
        $widgets_manager->register( new AAP_Widget_About() );
        $widgets_manager->register( new AAP_Widget_Contact() );
    }

    public static function get_courses_url() {
        if ( function_exists( 'tutor_utils' ) && is_object( tutor_utils() ) && method_exists( tutor_utils(), 'get_courses_page_url' ) ) {
            $url = tutor_utils()->get_courses_page_url();
            if ( $url ) {
                return $url;
            }
        }
        return home_url( '/doreha/' );
    }

    public static function get_dashboard_url() {
        if ( function_exists( 'wc_get_account_endpoint_url' ) ) {
            return wc_get_account_endpoint_url( 'my-courses' );
        }
        return home_url( '/my-account/my-courses/' );
    }

    public static function get_account_url() {
        if ( function_exists( 'wc_get_page_permalink' ) ) {
            return wc_get_page_permalink( 'myaccount' );
        }
        return wp_login_url();
    }
}
