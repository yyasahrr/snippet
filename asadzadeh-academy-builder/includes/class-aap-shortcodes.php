<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class AAP_Shortcodes {

    public static function init() {
        add_shortcode( 'asadzadeh_home', array( __CLASS__, 'home' ) );
        add_shortcode( 'luxury_academy_home', array( __CLASS__, 'home' ) );
        add_shortcode( 'asadzadeh_courses', array( __CLASS__, 'courses' ) );
        add_shortcode( 'asadzadeh_about', array( __CLASS__, 'about' ) );
        add_shortcode( 'asadzadeh_contact', array( __CLASS__, 'contact' ) );
    }

    public static function home( $atts = array() ) {
        $atts = shortcode_atts(
            array(
                'courses'  => 6,
                'category' => '',
            ),
            $atts,
            'asadzadeh_home'
        );
        $limit = max( 1, min( 12, absint( $atts['courses'] ) ?: 6 ) );
        $category = sanitize_text_field( (string) $atts['category'] );
        $instance = 'aap-home-' . wp_rand( 10000, 99999 );

        $course_ids = AAP_Data::get_course_ids( $limit, $category );
        $course_rows = AAP_Data::course_data( $course_ids );
        $categories = AAP_Data::categories( 8 );
        $active = AAP_Data::active_courses( 3 );

        ob_start();
        include AAP_PATH . 'templates/homepage.php';
        return ob_get_clean();
    }

    public static function courses( $atts = array() ) {
        $atts = shortcode_atts(
            array(
                'courses'  => 9,
                'category' => '',
                'search'   => '',
            ),
            $atts,
            'asadzadeh_courses'
        );
        $limit = max( 1, min( 24, absint( $atts['courses'] ) ?: 9 ) );
        $category = sanitize_text_field( (string) $atts['category'] );
        $search = sanitize_text_field( (string) $atts['search'] );
        $instance = 'aap-courses-' . wp_rand( 10000, 99999 );

        $course_ids = AAP_Data::get_course_ids( $limit, $category );
        $course_rows = AAP_Data::course_data( $course_ids );
        $categories = AAP_Data::categories( 12 );

        ob_start();
        include AAP_PATH . 'templates/courses.php';
        return ob_get_clean();
    }

    public static function about( $atts = array() ) {
        $atts = shortcode_atts( array(), $atts, 'asadzadeh_about' );
        $instance = 'aap-about-' . wp_rand( 10000, 99999 );
        ob_start();
        include AAP_PATH . 'templates/about.php';
        return ob_get_clean();
    }

    public static function contact( $atts = array() ) {
        $atts = shortcode_atts( array(), $atts, 'asadzadeh_contact' );
        $instance = 'aap-contact-' . wp_rand( 10000, 99999 );
        ob_start();
        include AAP_PATH . 'templates/contact.php';
        return ob_get_clean();
    }
}
