<?php
/**
 * Plugin Name: Asadzadeh Academy - Smart Pages Builder
 * Plugin URI:  https://asadzadehacademy.ir/
 * Description: صفحه‌ساز هوشمند آکادمی اسدزاده - صفحه اصلی، صفحه دوره‌ها، درباره ما و تماس با ما با طراحی اختصاصی، سازگار با Tutor LMS + WooCommerce + Elementor + WPCode
 * Version: 1.0.0
 * Author: Asadzadeh Academy - Noghte Group Style
 * Author URI: https://asadzadehacademy.ir/
 * Text Domain: asadzadeh-academy-builder
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'AAP_VERSION', '1.0.0' );
define( 'AAP_FILE', __FILE__ );
define( 'AAP_PATH', plugin_dir_path( __FILE__ ) );
define( 'AAP_URL', plugin_dir_url( __FILE__ ) );
define( 'AAP_BASENAME', plugin_basename( __FILE__ ) );

require_once AAP_PATH . 'includes/class-aap-plugin.php';

function AAP() {
    return AAP_Plugin::instance();
}

AAP();
