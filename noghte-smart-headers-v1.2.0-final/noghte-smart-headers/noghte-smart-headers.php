<?php
/**
 * Plugin Name: Noghte Smart Headers
 * Plugin URI:  https://noghte-group.ir/
 * Description: ده هدر سبک، حرفه‌ای و واکنش‌گرا با شورت‌کد و ویجت المنتور، ساخته‌شده توسط گروه نقطه.
 * Version:     1.2.0
 * Author:      Noghte Group
 * Author URI:  https://noghte-group.ir/
 * Text Domain: noghte-smart-headers
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NGT_HDR_VERSION', '1.2.0' );
define( 'NGT_HDR_FILE', __FILE__ );
define( 'NGT_HDR_PATH', plugin_dir_path( __FILE__ ) );
define( 'NGT_HDR_URL', plugin_dir_url( __FILE__ ) );

require_once NGT_HDR_PATH . 'includes/class-ngt-hdr-plugin.php';

NGT_HDR_Plugin::instance();
