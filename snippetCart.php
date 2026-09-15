/**
 * Luxury Reload WooCommerce Cart Shortcode - Stable Version - Minimal Qty + Compact Mobile + Minimal Extra Popup
 * Shortcode: [luxury_ajax_cart]
 * Optional: [luxury_ajax_cart hide_chrome="yes"] only if you intentionally want to hide site header/footer.
 *
 * Paste this code in Code Snippets WITHOUT PHP opening tag.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Luxury_Reload_Woo_Cart_Shortcode_V2' ) ) {

    final class Luxury_Reload_Woo_Cart_Shortcode_V2 {

        const SHORTCODE = 'luxury_ajax_cart';
        const NONCE    = 'luxury_reload_cart_nonce';

        public static function init() {
            add_shortcode( self::SHORTCODE, array( __CLASS__, 'render_shortcode' ) );
            add_action( 'wp_loaded', array( __CLASS__, 'handle_cart_post' ), 20 );
        }

        private static function ensure_cart() {
            if ( ! function_exists( 'WC' ) || ! class_exists( 'WooCommerce' ) ) {
                return false;
            }

            if ( function_exists( 'wc_load_cart' ) && ( is_null( WC()->cart ) || is_null( WC()->session ) ) ) {
                wc_load_cart();
            }

            return WC()->cart;
        }

        private static function calculate_cart() {
            $cart = self::ensure_cart();

            if ( ! $cart ) {
                return false;
            }

            if ( $cart->needs_shipping() && WC()->shipping() ) {
                $cart->calculate_shipping();
            }

            $cart->calculate_totals();

            return true;
        }

        private static function persist_cart() {
            self::calculate_cart();

            if ( WC()->cart ) {
                WC()->cart->set_session();

                if ( method_exists( WC()->cart, 'maybe_set_cart_cookies' ) ) {
                    WC()->cart->maybe_set_cart_cookies();
                }
            }
        }

        private static function current_url() {
            $scheme      = is_ssl() ? 'https://' : 'http://';
            $host        = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : wp_parse_url( home_url(), PHP_URL_HOST );
            $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';

            return esc_url_raw( $scheme . $host . $request_uri );
        }

        private static function cart_page_url() {
            if ( function_exists( 'wc_get_cart_url' ) ) {
                $cart_url = wc_get_cart_url();

                if ( $cart_url ) {
                    return esc_url_raw( $cart_url );
                }
            }

            $queried_id = get_queried_object_id();

            if ( $queried_id ) {
                $permalink = get_permalink( $queried_id );

                if ( $permalink ) {
                    return esc_url_raw( $permalink );
                }
            }

            return esc_url_raw( home_url( '/' ) );
        }

        private static function current_page_url() {
            $queried_id = get_queried_object_id();

            if ( $queried_id ) {
                $permalink = get_permalink( $queried_id );

                if ( $permalink ) {
                    return esc_url_raw( $permalink );
                }
            }

            $current = self::current_url();

            if ( self::is_bad_redirect_url( $current ) ) {
                return self::cart_page_url();
            }

            return $current;
        }

        private static function is_bad_redirect_url( $url ) {
            if ( ! $url ) {
                return true;
            }

            $path = wp_parse_url( $url, PHP_URL_PATH );
            $path = '/' . trim( (string) $path, '/' ) . '/';

            $blocked_paths = array(
                '/--/',
                '/wp-admin/admin-ajax.php/',
                '/wp-json/',
            );

            foreach ( $blocked_paths as $blocked_path ) {
                if ( 0 === strpos( $path, $blocked_path ) ) {
                    return true;
                }
            }

            return false;
        }

        private static function clean_redirect_url( $url ) {
            $fallback = self::cart_page_url();
            $url      = esc_url_raw( (string) $url );

            if ( self::is_bad_redirect_url( $url ) ) {
                return $fallback;
            }

            $validated = wp_validate_redirect( $url, $fallback );

            if ( self::is_bad_redirect_url( $validated ) ) {
                return $fallback;
            }

            return $validated;
        }

        private static function redirect_after_post() {
            $redirect = isset( $_POST['lux_current_url'] ) ? wp_unslash( $_POST['lux_current_url'] ) : '';

            if ( ! $redirect ) {
                $redirect = wp_get_referer();
            }

            $redirect = self::clean_redirect_url( $redirect );

            wp_safe_redirect( $redirect );
            exit;
        }

        private static function add_notice( $message, $type = 'success' ) {
            if ( function_exists( 'wc_add_notice' ) ) {
                wc_add_notice( $message, $type );
            }
        }

        public static function handle_cart_post() {
            if ( empty( $_POST['lux_cart_action'] ) ) {
                return;
            }

            if ( ! self::ensure_cart() ) {
                return;
            }

            $nonce = isset( $_POST['lux_cart_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['lux_cart_nonce'] ) ) : '';

            if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
                self::add_notice( 'درخواست نامعتبر است. صفحه را رفرش کنید و دوباره امتحان کنید.', 'error' );
                self::redirect_after_post();
            }

            $action = sanitize_key( wp_unslash( $_POST['lux_cart_action'] ) );

            switch ( $action ) {
                case 'qty_plus':
                    self::handle_quantity_change( 'plus' );
                    break;

                case 'qty_minus':
                    self::handle_quantity_change( 'minus' );
                    break;

                case 'update_qty':
                    self::handle_quantity_change( 'set' );
                    break;

                case 'remove_item':
                    self::handle_remove_item();
                    break;

                case 'apply_coupon':
                    self::handle_apply_coupon();
                    break;

                case 'remove_coupon':
                    self::handle_remove_coupon();
                    break;

                case 'update_shipping_method':
                    self::handle_update_shipping_method();
                    break;

                default:
                    self::add_notice( 'عملیات نامعتبر است.', 'error' );
                    break;
            }

            self::persist_cart();
            self::redirect_after_post();
        }

        private static function posted_cart_item_key() {
            return isset( $_POST['cart_item_key'] ) ? wc_clean( wp_unslash( $_POST['cart_item_key'] ) ) : '';
        }

        private static function clamp_quantity( $quantity, $product ) {
            $quantity = absint( $quantity );

            if ( $product && method_exists( $product, 'get_max_purchase_quantity' ) ) {
                $max_quantity = $product->get_max_purchase_quantity();

                if ( $max_quantity > 0 && $quantity > $max_quantity ) {
                    $quantity = $max_quantity;
                }
            }

            return $quantity;
        }

        private static function handle_quantity_change( $mode ) {
            $key = self::posted_cart_item_key();

            if ( ! $key || ! isset( WC()->cart->cart_contents[ $key ] ) ) {
                self::add_notice( 'این آیتم در سبد خرید پیدا نشد.', 'error' );
                return;
            }

            $cart_item = WC()->cart->cart_contents[ $key ];
            $product   = isset( $cart_item['data'] ) ? $cart_item['data'] : false;
            $current   = isset( $cart_item['quantity'] ) ? absint( $cart_item['quantity'] ) : 0;

            if ( 'plus' === $mode ) {
                $quantity = $current + 1;
            } elseif ( 'minus' === $mode ) {
                $quantity = max( 0, $current - 1 );
            } else {
                $quantity = isset( $_POST['quantity'] ) ? absint( wp_unslash( $_POST['quantity'] ) ) : $current;
            }

            $quantity = self::clamp_quantity( $quantity, $product );

            if ( $quantity <= 0 ) {
                WC()->cart->remove_cart_item( $key );
                self::add_notice( 'محصول از سبد خرید حذف شد.', 'success' );
                return;
            }

            WC()->cart->set_quantity( $key, $quantity, true );
            self::add_notice( 'تعداد محصول به‌روزرسانی شد.', 'success' );
        }

        private static function handle_remove_item() {
            $key = self::posted_cart_item_key();

            if ( $key && isset( WC()->cart->cart_contents[ $key ] ) ) {
                WC()->cart->remove_cart_item( $key );
                self::add_notice( 'محصول از سبد خرید حذف شد.', 'success' );
                return;
            }

            self::add_notice( 'این آیتم در سبد خرید پیدا نشد.', 'error' );
        }

        private static function handle_apply_coupon() {
            if ( ! wc_coupons_enabled() ) {
                self::add_notice( 'کد تخفیف در این فروشگاه فعال نیست.', 'error' );
                return;
            }

            $coupon_code = isset( $_POST['coupon_code'] ) ? wc_format_coupon_code( wc_clean( wp_unslash( $_POST['coupon_code'] ) ) ) : '';

            if ( '' === $coupon_code ) {
                self::add_notice( 'کد تخفیف را وارد کنید.', 'error' );
                return;
            }

            if ( WC()->cart->has_discount( $coupon_code ) ) {
                self::add_notice( 'این کد قبلاً اعمال شده است.', 'notice' );
                return;
            }

            WC()->cart->apply_coupon( $coupon_code );
        }

        private static function handle_remove_coupon() {
            $coupon_code = isset( $_POST['coupon_code'] ) ? wc_format_coupon_code( wc_clean( wp_unslash( $_POST['coupon_code'] ) ) ) : '';

            if ( '' === $coupon_code ) {
                self::add_notice( 'کد تخفیف پیدا نشد.', 'error' );
                return;
            }

            WC()->cart->remove_coupon( $coupon_code );
            self::add_notice( 'کد تخفیف حذف شد.', 'success' );
        }

        private static function handle_update_shipping_method() {
            $package_id = isset( $_POST['package_id'] ) ? absint( wp_unslash( $_POST['package_id'] ) ) : 0;
            $method_id  = isset( $_POST['method_id'] ) ? wc_clean( wp_unslash( $_POST['method_id'] ) ) : '';

            if ( '' === $method_id ) {
                self::add_notice( 'روش ارسال انتخاب نشد.', 'error' );
                return;
            }

            if ( WC()->cart->needs_shipping() && WC()->shipping() ) {
                WC()->cart->calculate_shipping();
            }

            $packages = WC()->shipping() ? WC()->shipping()->get_packages() : array();

            if ( ! isset( $packages[ $package_id ]['rates'][ $method_id ] ) ) {
                self::add_notice( 'این روش ارسال برای سبد خرید فعلی در دسترس نیست.', 'error' );
                return;
            }

            $chosen_methods                = WC()->session ? WC()->session->get( 'chosen_shipping_methods', array() ) : array();
            $chosen_methods[ $package_id ] = $method_id;

            if ( WC()->session ) {
                WC()->session->set( 'chosen_shipping_methods', $chosen_methods );
            }

            WC()->cart->calculate_shipping();
            WC()->cart->calculate_totals();

            self::add_notice( 'روش ارسال به‌روزرسانی شد.', 'success' );
        }

        public static function render_shortcode( $atts = array() ) {
            $atts = shortcode_atts(
                array(
                    'hide_chrome' => 'no',
                ),
                $atts,
                self::SHORTCODE
            );

            if ( ! self::ensure_cart() ) {
                return '<div class="lux-cart-error">برای استفاده از این شورت‌کد باید WooCommerce فعال باشد.</div>';
            }

            self::calculate_cart();

            $instance_id = 'lux-cart-' . wp_rand( 10000, 99999 );
            $hide_chrome = in_array( strtolower( (string) $atts['hide_chrome'] ), array( 'yes', 'true', '1', 'on' ), true );

            ob_start();
            ?>
            <div
                id="<?php echo esc_attr( $instance_id ); ?>"
                class="lux-cart-wrap"
                data-hide-chrome="<?php echo $hide_chrome ? '1' : '0'; ?>"
                data-lux-page-url="<?php echo esc_url( self::current_page_url() ); ?>"
                dir="rtl"
            >
                <?php echo self::render_assets( $instance_id, $hide_chrome ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <div class="lux-cart-content">
                    <?php echo self::render_cart_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        private static function render_form_action_attr() {
            echo ' action="' . esc_url( self::current_page_url() ) . '"';
        }

        private static function render_form_hidden_fields( $cart_item_key = '' ) {
            wp_nonce_field( self::NONCE, 'lux_cart_nonce' );
            ?>
            <input type="hidden" name="lux_current_url" value="<?php echo esc_url( self::current_page_url() ); ?>">
            <?php if ( $cart_item_key ) : ?>
                <input type="hidden" name="cart_item_key" value="<?php echo esc_attr( $cart_item_key ); ?>">
            <?php endif; ?>
            <?php
        }

        private static function render_assets( $instance_id, $hide_chrome ) {
            ob_start();
            ?>
            <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
            <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css">
            <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700;800&display=swap" rel="stylesheet">

            <style>
                .lux-cart-wrap,
                .lux-cart-wrap * {
                    box-sizing: border-box;
                }

                .lux-cart-wrap {
                    --lux-primary: var(--e-global-color-primary, var(--wp--preset--color--primary, #3525cd));
                    --lux-secondary: var(--e-global-color-secondary, var(--wp--preset--color--secondary, #6b38d4));
                    --lux-surface: var(--wp--preset--color--base, #ffffff);
                    --lux-surface-soft: color-mix(in srgb, var(--lux-surface) 94%, var(--lux-primary) 6%);
                    --lux-surface-low: color-mix(in srgb, var(--lux-surface) 90%, var(--lux-primary) 10%);
                    --lux-text: var(--e-global-color-text, var(--wp--preset--color--contrast, #0b1c30));
                    --lux-muted: color-mix(in srgb, var(--lux-text) 62%, transparent);
                    --lux-outline: color-mix(in srgb, var(--lux-text) 16%, transparent);
                    --lux-error: #ba1a1a;
                    --lux-error-bg: #ffdad6;

                    width: 100%;
                    max-width: 100%;
                    margin: 0;
                    padding: 0;
                    background: transparent !important;
                    color: var(--lux-text);
                    font-family: "Vazirmatn", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                    direction: rtl;
                    position: relative;
                    isolation: isolate;
                    overflow-x: clip;
                }

                .lux-cart-wrap::before {
                    display: none !important;
                    content: none !important;
                }

                .lux-cart-wrap button,
                .lux-cart-wrap input {
                    font-family: inherit;
                }

                .lux-cart-shell {
                    width: min(1280px, 100%);
                    margin: 0 auto;
                    padding: 34px 0 48px;
                }

                .lux-cart-hero {
                    margin-bottom: 24px;
                    animation: luxSlideUpFade .58s cubic-bezier(.22, 1, .36, 1) both;
                }

                .lux-cart-hero h1 {
                    margin: 0 0 8px;
                    font-size: clamp(30px, 4vw, 48px);
                    line-height: 1.25;
                    font-weight: 900;
                    letter-spacing: -.04em;
                    color: var(--lux-text);
                }

                .lux-cart-hero p {
                    margin: 0;
                    font-size: 17px;
                    line-height: 1.8;
                    color: var(--lux-muted);
                }

                .lux-cart-grid {
                    display: grid;
                    grid-template-columns: minmax(0, 8fr) minmax(340px, 4fr);
                    gap: 28px;
                    align-items: start;
                }

                .lux-cart-items {
                    display: flex;
                    flex-direction: column;
                    gap: 20px;
                }

                .lux-glass {
                    background: color-mix(in srgb, var(--lux-surface) 82%, transparent);
                    border: 1px solid color-mix(in srgb, var(--lux-text) 10%, transparent);
                    box-shadow: 0 18px 55px rgba(11, 28, 48, .07);
                    backdrop-filter: blur(18px);
                    -webkit-backdrop-filter: blur(18px);
                }

                .lux-cart-item {
                    display: flex;
                    align-items: center;
                    gap: 22px;
                    width: 100%;
                    margin: 0;
                    padding: 22px;
                    border-radius: 18px;
                    position: relative;
                    animation: luxSlideUpFade .62s cubic-bezier(.22, 1, .36, 1) both;
                    transition: border-color .35s ease, transform .35s ease, box-shadow .35s ease;
                }

                .lux-cart-item:hover {
                    border-color: color-mix(in srgb, var(--lux-primary) 36%, transparent);
                    transform: translateY(-3px);
                    box-shadow: 0 24px 70px rgba(11, 28, 48, .10);
                }

                .lux-cart-media {
                    width: 122px;
                    height: 122px;
                    flex: 0 0 122px;
                    border-radius: 16px;
                    overflow: hidden;
                    position: relative;
                    background: var(--lux-surface-soft);
                    filter: drop-shadow(0 0 8px color-mix(in srgb, var(--lux-secondary) 22%, transparent));
                }

                .lux-cart-media::after {
                    content: "";
                    position: absolute;
                    inset: 0;
                    background: linear-gradient(45deg, color-mix(in srgb, var(--lux-primary) 10%, transparent), transparent 62%);
                    pointer-events: none;
                }

                .lux-cart-media img {
                    width: 100%;
                    height: 100%;
                    display: block;
                    object-fit: cover;
                }

                .lux-cart-info {
                    flex: 1 1 auto;
                    min-width: 0;
                    display: flex;
                    align-items: flex-start;
                    justify-content: space-between;
                    gap: 20px;
                }

                .lux-cart-title {
                    display: block;
                    margin: 0 0 8px;
                    font-size: 20px;
                    line-height: 1.5;
                    font-weight: 800;
                    color: var(--lux-text);
                    text-decoration: none;
                }

                .lux-cart-title:hover {
                    color: var(--lux-primary);
                }

                .lux-cart-meta {
                    display: flex;
                    flex-wrap: wrap;
                    align-items: center;
                    gap: 8px;
                    color: var(--lux-muted);
                    font-size: 13px;
                    line-height: 1.8;
                }

                .lux-cart-meta dl,
                .lux-cart-meta p {
                    margin: 0;
                }

                .lux-cart-meta dt,
                .lux-cart-meta dd {
                    display: inline;
                    margin: 0;
                }

                .lux-badge {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 25px;
                    padding: 2px 12px;
                    border-radius: 999px;
                    background: color-mix(in srgb, var(--lux-primary) 11%, transparent);
                    color: var(--lux-primary);
                    font-size: 11px;
                    font-weight: 800;
                    white-space: nowrap;
                }

                .lux-badge.secondary {
                    background: color-mix(in srgb, var(--lux-secondary) 12%, transparent);
                    color: var(--lux-secondary);
                }

                .lux-cart-actions {
                    display: flex;
                    flex-direction: column;
                    align-items: flex-end;
                    gap: 12px;
                    flex: 0 0 auto;
                }

                .lux-cart-price {
                    display: flex;
                    flex-direction: column;
                    align-items: flex-end;
                    gap: 2px;
                    color: var(--lux-primary);
                    font-size: 20px;
                    line-height: 1.4;
                    font-weight: 900;
                }

                .lux-cart-price del,
                .lux-cart-price small {
                    color: var(--lux-muted);
                    opacity: .7;
                    font-size: 12px;
                    font-weight: 500;
                }

                .lux-qty-row {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    flex-wrap: wrap;
                    justify-content: flex-end;
                }

                .lux-qty {
                    display: inline-flex;
                    align-items: center;
                    gap: 10px;
                    padding: 4px;
                    border-radius: 12px;
                    background: var(--lux-surface-low);
                    direction: ltr;
                }

                .lux-qty button,
                .lux-remove,
                .lux-btn,
                .lux-coupon button,
                .lux-coupon-pill button,
                .lux-shipping-method {
                    appearance: none;
                    border: 0;
                    cursor: pointer;
                    font-family: inherit;
					
                    transition: transform .18s ease, background .25s ease, color .25s ease, box-shadow .25s ease, border-color .25s ease;
                }

                .lux-qty button {
                    width: 34px;
                    height: 34px;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    border-radius: 9px;
                    background: var(--lux-surface);
                    color: var(--lux-text);
                    box-shadow: 0 2px 10px rgba(11, 28, 48, .05);
                }

                .lux-qty button:hover {
                    background: var(--lux-primary);
                    color: #fff;
                }

                .lux-qty button[value="qty_minus"]:hover {
                    background: var(--lux-error);
                    color: #fff;
                }

                .lux-qty input {
                    width: 42px !important;
                    min-width: 42px;
                    height: 34px;
                    padding: 0 !important;
                    border: 0 !important;
                    background: transparent !important;
                    color: var(--lux-text) !important;
                    box-shadow: none !important;
                    outline: 0 !important;
                    text-align: center;
                    font-family: "JetBrains Mono", monospace;
                    font-size: 17px;
                    font-weight: 800;
                    -moz-appearance: textfield;
                }

                .lux-qty input::-webkit-outer-spin-button,
                .lux-qty input::-webkit-inner-spin-button {
                    -webkit-appearance: none;
                    margin: 0;
                }

                .lux-update-qty {
                    min-height: 34px;
                    padding: 6px 10px;
                    border-radius: 10px;
                    border: 1px solid var(--lux-outline);
                    background: var(--lux-surface);
                    color: var(--lux-primary);
                    font-size: 12px;
                    font-weight: 900;
                    cursor: pointer;
                }

                .lux-update-qty:hover {
                    background: var(--lux-primary);
                    color: #fff;
                }

                .lux-remove {
                    align-self: flex-start;
                    width: 38px;
                    height: 38px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border-radius: 999px;
                    background: transparent;
                    color: var(--lux-muted);
                    flex: 0 0 auto;
                }

                .lux-remove:hover {
                    background: var(--lux-error-bg);
                    color: var(--lux-error);
                    transform: scale(1.05);
                }

                .lux-summary {
                    position: sticky;
                    top: 28px;
                    border-radius: 24px;
                    padding: 28px;
                    border: 1px solid color-mix(in srgb, var(--lux-primary) 10%, transparent);
                    animation: luxSlideUpFade .7s cubic-bezier(.22, 1, .36, 1) both;
                }

                .lux-summary h2 {
                    margin: 0 0 22px;
                    font-size: 24px;
                    line-height: 1.4;
                    font-weight: 900;
                    color: var(--lux-text);
                }

                .lux-summary-rows {
                    display: flex;
                    flex-direction: column;
                    gap: 14px;
                    margin-bottom: 22px;
                }

                .lux-summary-row {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    gap: 16px;
                    color: var(--lux-muted);
                    font-size: 15px;
                }

                .lux-summary-row strong,
                .lux-summary-row span:last-child {
                    font-family: "JetBrains Mono", "Vazirmatn", monospace;
                    font-size: 16px;
                    font-weight: 800;
                    color: inherit;
                    direction: rtl;
                }

                .lux-summary-row.discount {
                    color: var(--lux-secondary);
                }

                .lux-summary-row.fee {
                    color: var(--lux-text);
                }

                .lux-summary-row.tax {
                    color: var(--lux-muted);
                }

                .lux-summary-row.total {
                    margin-top: 4px;
                    padding-top: 18px;
                    border-top: 1px solid var(--lux-outline);
                    color: var(--lux-text);
                }

                .lux-summary-row.total span:first-child {
                    font-size: 20px;
                    font-weight: 900;
                }

                .lux-summary-row.total strong {
                    color: var(--lux-primary);
                    font-size: clamp(22px, 3vw, 30px);
                    line-height: 1.4;
                }

                .lux-shipping-box {
                    margin: 2px 0 18px;
                    padding: 15px;
                    border-radius: 16px;
                    background: var(--lux-surface-soft);
                    border: 1px solid var(--lux-outline);
                }

                .lux-shipping-title {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 12px;
                    margin-bottom: 10px;
                    color: var(--lux-text);
                    font-size: 14px;
                    font-weight: 900;
                }

                .lux-shipping-package {
                    display: flex;
                    flex-direction: column;
                    gap: 8px;
                }

                .lux-shipping-package + .lux-shipping-package {
                    margin-top: 12px;
                    padding-top: 12px;
                    border-top: 1px dashed var(--lux-outline);
                }

                .lux-shipping-package-name {
                    margin: 0 0 2px;
                    color: var(--lux-muted);
                    font-size: 12px;
                    font-weight: 800;
                }

                .lux-shipping-method-form {
                    margin: 0;
                }

                .lux-shipping-method {
                    width: 100%;
                    display: flex;
                    align-items: center;
                    gap: 9px;
                    padding: 10px;
                    border-radius: 13px;
                    background: color-mix(in srgb, var(--lux-surface) 70%, transparent);
                    border: 1px solid transparent;
                    color: var(--lux-text);
                    font-size: 13px;
                    line-height: 1.7;
                    text-align: right;
                }

                .lux-shipping-method:hover {
                    border-color: color-mix(in srgb, var(--lux-primary) 24%, transparent);
                }

                .lux-radio {
                    width: 18px;
                    height: 18px;
                    flex: 0 0 18px;
                    border-radius: 50%;
                    border: 2px solid color-mix(in srgb, var(--lux-text) 25%, transparent);
                    background: var(--lux-surface);
                    display: inline-block;
                }

                .lux-radio.is-checked {
                    border-color: var(--lux-primary);
                    box-shadow: inset 0 0 0 4px var(--lux-surface), inset 0 0 0 10px var(--lux-primary);
                }

                .lux-shipping-method-text {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 10px;
                    width: 100%;
                }

                .lux-shipping-method-text .amount {
                    font-family: "JetBrains Mono", "Vazirmatn", monospace;
                    font-weight: 800;
                }

                .lux-shipping-empty {
                    margin: 0;
                    color: var(--lux-muted);
                    font-size: 13px;
                    line-height: 1.9;
                }

                .lux-btn {
                    width: 100%;
                    min-height: 58px;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    gap: 10px;
                    border-radius: 16px;
                    padding: 14px 18px;
                    background: linear-gradient(90deg, var(--lux-primary), var(--lux-secondary));
                    color: #fff !important;
                    font-size: 18px;
                    font-weight: 900;
                    text-decoration: none !important;
                    position: relative;
                    overflow: hidden;
                    box-shadow: 0 18px 35px color-mix(in srgb, var(--lux-primary) 26%, transparent);
                }

                .lux-btn::after {
                    content: "";
                    position: absolute;
                    top: -60%;
                    left: -60%;
                    width: 220%;
                    height: 220%;
                    background: linear-gradient(135deg, transparent 0%, rgba(255,255,255,.22) 48%, transparent 70%);
                    transform: translateX(-40%) rotate(35deg);
                    opacity: 0;
                    transition: transform .75s ease, opacity .3s ease;
                }

                .lux-btn:hover {
                    transform: translateY(-3px);
                    box-shadow: 0 24px 45px color-mix(in srgb, var(--lux-primary) 34%, transparent);
                }

                .lux-btn:hover::after {
                    opacity: 1;
                    transform: translateX(34%) rotate(35deg);
                }

                .lux-coupon {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    justify-content: center;
                    min-height: 56px;
                    margin-top: 14px;
                    padding: 8px 12px;
                    border-radius: 14px;
                    border: 1px dashed var(--lux-outline);
                    background: var(--lux-surface-low);
                }

                .lux-coupon input {
                    width: 130px !important;
                    min-width: 0;
                    border: 0 !important;
                    outline: 0 !important;
                    box-shadow: none !important;
                    background: transparent !important;
                    color: var(--lux-text) !important;
                    padding: 8px 4px !important;
                    font-size: 14px;
                    text-align: right;
                }

                .lux-coupon button {
                    background: transparent;
                    color: var(--lux-primary);
                    font-size: 13px;
                    font-weight: 900;
                    white-space: nowrap;
                }

                .lux-coupon button:hover {
                    color: var(--lux-secondary);
                    transform: translateY(-1px);
                }

                .lux-coupons-list {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 8px;
                    margin: 14px 0 0;
                }

                .lux-coupon-pill {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    padding: 7px 10px;
                    border-radius: 999px;
                    background: color-mix(in srgb, var(--lux-secondary) 12%, transparent);
                    color: var(--lux-secondary);
                    font-size: 12px;
                    font-weight: 800;
                }

                .lux-coupon-pill form {
                    margin: 0;
                    display: inline-flex;
                }

                .lux-coupon-pill button {
                    width: 22px;
                    height: 22px;
                    border-radius: 50%;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    background: color-mix(in srgb, var(--lux-surface) 75%, transparent);
                    color: var(--lux-secondary);
                }

                .lux-trust {
                    display: flex;
                    flex-direction: column;
                    gap: 13px;
                    margin-top: 24px;
                    color: var(--lux-muted);
                    font-size: 14px;
                }

                .lux-trust-item {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }

                .lux-trust .material-symbols-outlined {
                    color: var(--lux-secondary);
                    font-variation-settings: 'FILL' 1, 'wght' 500, 'GRAD' 0, 'opsz' 24;
                }

                .lux-continue {
                    margin-top: 18px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 8px;
                    color: var(--lux-muted) !important;
                    text-decoration: none !important;
                    font-weight: 700;
                    transition: color .25s ease, transform .25s ease;
                }

                .lux-continue:hover {
                    color: var(--lux-primary) !important;
                    transform: translateX(-3px);
                }

                .lux-empty {
                    min-height: 48vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    text-align: center;
                }

                .lux-empty-card {
                    max-width: 560px;
                    width: 100%;
                    padding: 42px 28px;
                    border-radius: 28px;
                    animation: luxSlideUpFade .62s cubic-bezier(.22, 1, .36, 1) both;
                }

                .lux-empty-icon {
                    width: 84px;
                    height: 84px;
                    border-radius: 28px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 20px;
                    background: linear-gradient(135deg, var(--lux-primary), var(--lux-secondary));
                    color: #fff;
                    box-shadow: 0 18px 40px color-mix(in srgb, var(--lux-primary) 25%, transparent);
                }

                .lux-empty-icon .material-symbols-outlined {
                    font-size: 42px;
                    font-variation-settings: 'FILL' 1, 'wght' 500, 'GRAD' 0, 'opsz' 48;
                }

                .lux-empty h2 {
                    margin: 0 0 10px;
                    font-size: 28px;
                    font-weight: 900;
                }

                .lux-empty p {
                    margin: 0 0 24px;
                    color: var(--lux-muted);
                    line-height: 1.9;
                }

                .lux-notices {
                    margin: 0 0 22px;
                }

                .lux-notices .woocommerce-message,
                .lux-notices .woocommerce-info,
                .lux-notices .woocommerce-error {
                    margin: 0 0 10px !important;
                    padding: 13px 16px !important;
                    border-radius: 14px !important;
                    border: 1px solid var(--lux-outline) !important;
                    background: color-mix(in srgb, var(--lux-surface) 92%, var(--lux-primary) 8%) !important;
                    color: var(--lux-text) !important;
                    font-size: 14px !important;
                    line-height: 1.9 !important;
                    list-style: none !important;
                }

                .lux-notices .woocommerce-error {
                    background: var(--lux-error-bg) !important;
                    color: var(--lux-error) !important;
                }

                .lux-cart-wrap.is-loading::after {
                    content: "";
                    position: fixed;
                    inset: 0;
                    z-index: 99998;
                    pointer-events: none;
                    background: rgba(255,255,255,.14);
                    backdrop-filter: blur(2px);
                }



                /* Unified checkout-button visual style for every cart form button */
                .lux-cart-wrap form button,
                .lux-cart-wrap .lux-btn {
                    appearance: none !important;
                    -webkit-appearance: none !important;
                    border: 0 !important;
                    outline: 0 !important;
                    background: linear-gradient(90deg, var(--lux-primary), var(--lux-secondary)) !important;
                    color: #fff !important;
                    box-shadow: 0 18px 35px color-mix(in srgb, var(--lux-primary) 26%, transparent) !important;
                    font-family: inherit !important;
                    font-weight: 900 !important;
                    text-decoration: none !important;
                    cursor: pointer !important;
                    position: relative !important;
                    overflow: hidden !important;
                    isolation: isolate !important;
                    display: inline-flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                    gap: 8px !important;
                    border-radius: 16px !important;
                    text-align: center !important;
                    line-height: 1.4 !important;
                    transition: transform .22s ease, box-shadow .25s ease, filter .25s ease, background .25s ease, color .25s ease !important;
                }

                .lux-cart-wrap form button::after,
                .lux-cart-wrap .lux-btn::after {
                    content: "" !important;
                    position: absolute !important;
                    top: -60% !important;
                    left: -60% !important;
                    width: 220% !important;
                    height: 220% !important;
                    background: linear-gradient(135deg, transparent 0%, rgba(255,255,255,.22) 48%, transparent 70%) !important;
                    transform: translateX(-40%) rotate(35deg) !important;
                    opacity: 0 !important;
                    pointer-events: none !important;
                    z-index: -1 !important;
                    transition: transform .75s ease, opacity .3s ease !important;
                }

                .lux-cart-wrap form button:hover,
                .lux-cart-wrap form button:focus-visible,
                .lux-cart-wrap .lux-btn:hover,
                .lux-cart-wrap .lux-btn:focus-visible,
                .lux-cart-wrap .lux-qty button[value="qty_minus"]:hover,
                .lux-cart-wrap .lux-remove:hover {
                    background: linear-gradient(90deg, var(--lux-primary), var(--lux-secondary)) !important;
                    color: #fff !important;
                    transform: translateY(-3px) !important;
                    box-shadow: 0 24px 45px color-mix(in srgb, var(--lux-primary) 34%, transparent) !important;
                    filter: saturate(1.08) !important;
                }

                .lux-cart-wrap form button:hover::after,
                .lux-cart-wrap form button:focus-visible::after,
                .lux-cart-wrap .lux-btn:hover::after,
                .lux-cart-wrap .lux-btn:focus-visible::after {
                    opacity: 1 !important;
                    transform: translateX(34%) rotate(35deg) !important;
                }

                .lux-cart-wrap form button:active,
                .lux-cart-wrap .lux-btn:active {
                    transform: translateY(-1px) scale(.99) !important;
                }

                .lux-cart-wrap .lux-btn {
                    width: 100% !important;
                    min-height: 58px !important;
                    padding: 14px 18px !important;
                    font-size: 18px !important;
                }

                .lux-cart-wrap .lux-qty button {
                    width: 34px !important;
                    min-width: 34px !important;
                    height: 34px !important;
                    min-height: 34px !important;
                    flex: 0 0 34px !important;
                    padding: 0 !important;
                    border-radius: 12px !important;
                }

                .lux-cart-wrap .lux-update-qty {
                    min-height: 38px !important;
                    padding: 7px 13px !important;
                    border-radius: 14px !important;
                    font-size: 12px !important;
                    white-space: nowrap !important;
                }

                .lux-cart-wrap .lux-remove {
                    width: 38px !important;
                    min-width: 38px !important;
                    height: 38px !important;
                    min-height: 38px !important;
                    flex: 0 0 38px !important;
                    padding: 0 !important;
                    border-radius: 14px !important;
                }

                .lux-cart-wrap .lux-coupon button {
                    min-height: 40px !important;
                    padding: 8px 14px !important;
                    border-radius: 14px !important;
                    font-size: 13px !important;
                    white-space: nowrap !important;
                }

                .lux-cart-wrap .lux-coupon-pill button {
                    width: 24px !important;
                    min-width: 24px !important;
                    height: 24px !important;
                    min-height: 24px !important;
                    flex: 0 0 24px !important;
                    padding: 0 !important;
                    border-radius: 10px !important;
                    box-shadow: 0 8px 18px color-mix(in srgb, var(--lux-primary) 20%, transparent) !important;
                }

                .lux-cart-wrap .lux-shipping-method {
                    width: 100% !important;
                    min-height: 46px !important;
                    padding: 10px 12px !important;
                    border-radius: 14px !important;
                    justify-content: flex-start !important;
                    text-align: right !important;
                }

                .lux-cart-wrap .lux-shipping-method,
                .lux-cart-wrap .lux-shipping-method-text,
                .lux-cart-wrap .lux-shipping-method-text span,
                .lux-cart-wrap .lux-shipping-method-text .amount {
                    color: #fff !important;
                }

                .lux-cart-wrap .lux-shipping-method:hover {
                    border-color: transparent !important;
                }

                .lux-cart-wrap .lux-radio {
                    border-color: rgba(255,255,255,.72) !important;
                    background: rgba(255,255,255,.16) !important;
                }

                .lux-cart-wrap .lux-radio.is-checked {
                    border-color: #fff !important;
                    box-shadow: inset 0 0 0 4px rgba(255,255,255,.16), inset 0 0 0 10px #fff !important;
                }


                /* Minimal white quantity controls */
                .lux-cart-wrap .lux-qty {
                    gap: 7px !important;
                    padding: 4px !important;
                    border-radius: 14px !important;
                    background: color-mix(in srgb, var(--lux-surface) 86%, var(--lux-primary) 14%) !important;
                    border: 1px solid color-mix(in srgb, var(--lux-text) 8%, transparent) !important;
                    box-shadow: inset 0 1px 0 rgba(255,255,255,.54) !important;
                }

                .lux-cart-wrap .lux-qty button,
                .lux-cart-wrap .lux-qty button:hover,
                .lux-cart-wrap .lux-qty button:focus-visible,
                .lux-cart-wrap .lux-qty button[value="qty_minus"]:hover {
                    background: #fff !important;
                    color: var(--lux-text) !important;
                    border: 1px solid color-mix(in srgb, var(--lux-text) 10%, transparent) !important;
                    box-shadow: 0 5px 14px rgba(11, 28, 48, .07) !important;
                    filter: none !important;
                }

                .lux-cart-wrap .lux-qty button::after,
                .lux-cart-wrap .lux-qty button:hover::after,
                .lux-cart-wrap .lux-qty button:focus-visible::after {
                    display: none !important;
                    content: none !important;
                }

                .lux-cart-wrap .lux-qty button:hover,
                .lux-cart-wrap .lux-qty button:focus-visible {
                    color: var(--lux-primary) !important;
                    transform: translateY(-1px) scale(1.03) !important;
                    box-shadow: 0 8px 18px rgba(11, 28, 48, .10) !important;
                }

                .lux-cart-wrap .lux-qty button[value="qty_minus"]:hover,
                .lux-cart-wrap .lux-qty button[value="qty_minus"]:focus-visible {
                    color: var(--lux-error) !important;
                }

                .lux-cart-wrap .lux-qty button:active {
                    transform: translateY(0) scale(.97) !important;
                }

                .lux-cart-wrap .material-symbols-outlined {
                    font-family: 'Material Symbols Outlined';
                    font-weight: normal;
                    font-style: normal;
                    font-size: 24px;
                    line-height: 1;
                    letter-spacing: normal;
                    text-transform: none;
                    display: inline-block;
                    white-space: nowrap;
                    word-wrap: normal;
                    direction: ltr;
                    -webkit-font-feature-settings: 'liga';
                    -webkit-font-smoothing: antialiased;
                    vertical-align: middle;
                }

                @keyframes luxSlideUpFade {
                    from {
                        opacity: 0;
                        transform: translateY(22px);
                    }

                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }

                @media (max-width: 980px) {
                    .lux-cart-shell {
                        padding: 30px 0 44px;
                    }

                    .lux-cart-grid {
                        grid-template-columns: 1fr;
                    }

                    .lux-summary {
                        position: relative;
                        top: auto;
                    }
                }

                @media (max-width: 640px) {
                    .lux-cart-hero {
                        margin-bottom: 22px;
                    }

                    .lux-cart-hero p {
                        font-size: 15px;
                    }

                    .lux-cart-item {
                        padding: 18px;
                        flex-direction: column;
                        align-items: center;
                        text-align: center;
                    }

                    .lux-cart-media {
                        width: 132px;
                        height: 132px;
                        flex-basis: 132px;
                    }

                    .lux-cart-info {
                        width: 100%;
                        flex-direction: column;
                        align-items: center;
                    }

                    .lux-cart-meta {
                        justify-content: center;
                    }

                    .lux-cart-actions {
                        align-items: center;
                        width: 100%;
                    }

                    .lux-qty-row {
                        justify-content: center;
                    }

                    .lux-remove {
                        position: absolute;
                        top: 12px;
                        left: 12px;
                        align-self: auto;
                    }

                    .lux-summary {
                        padding: 22px;
                    }

                    .lux-summary-row {
                        font-size: 14px;
                    }

                    .lux-summary-row.total {
                        flex-direction: column;
                        align-items: flex-start;
                    }

                    .lux-coupon {
                        justify-content: space-between;
                    }

                    .lux-shipping-method-text {
                        flex-direction: column;
                        align-items: flex-start;
                        gap: 2px;
                    }
                }
            

                /* Mobile compact product cards */
                @media (max-width: 640px) {
                    .lux-cart-shell {
                        padding: 20px 0 34px !important;
                    }

                    .lux-cart-hero {
                        margin-bottom: 14px !important;
                    }

                    .lux-cart-hero h1 {
                        font-size: 27px !important;
                        margin-bottom: 4px !important;
                    }

                    .lux-cart-hero p {
                        font-size: 13px !important;
                        line-height: 1.65 !important;
                    }

                    .lux-cart-items {
                        gap: 12px !important;
                    }

                    .lux-cart-item {
                        display: grid !important;
                        grid-template-columns: 74px minmax(0, 1fr) 34px !important;
                        grid-template-areas: "media info remove" !important;
                        gap: 10px 11px !important;
                        align-items: center !important;
                        padding: 12px !important;
                        border-radius: 17px !important;
                        text-align: right !important;
                        transform: none !important;
                    }

                    .lux-cart-item:hover {
                        transform: none !important;
                        box-shadow: 0 18px 48px rgba(11, 28, 48, .08) !important;
                    }

                    .lux-cart-media {
                        grid-area: media !important;
                        width: 74px !important;
                        height: 74px !important;
                        flex: 0 0 74px !important;
                        border-radius: 13px !important;
                        filter: drop-shadow(0 0 4px color-mix(in srgb, var(--lux-secondary) 14%, transparent)) !important;
                    }

                    .lux-cart-info {
                        grid-area: info !important;
                        width: 100% !important;
                        min-width: 0 !important;
                        display: flex !important;
                        flex-direction: column !important;
                        align-items: stretch !important;
                        gap: 8px !important;
                    }

                    .lux-cart-copy {
                        min-width: 0 !important;
                    }

                    .lux-cart-title {
                        display: -webkit-box !important;
                        -webkit-line-clamp: 2;
                        -webkit-box-orient: vertical;
                        overflow: hidden !important;
                        margin: 0 0 4px !important;
                        font-size: 14.5px !important;
                        line-height: 1.55 !important;
                        letter-spacing: -.01em !important;
                    }

                    .lux-cart-meta {
                        justify-content: flex-start !important;
                        gap: 5px !important;
                        font-size: 11px !important;
                        line-height: 1.5 !important;
                    }

                    .lux-cart-meta > span:not(.lux-badge):not(:first-child) {
                        display: none !important;
                    }

                    .lux-badge {
                        min-height: 20px !important;
                        padding: 1px 8px !important;
                        font-size: 10px !important;
                    }

                    .lux-cart-actions {
                        width: 100% !important;
                        display: flex !important;
                        flex-direction: row !important;
                        align-items: center !important;
                        justify-content: space-between !important;
                        gap: 8px !important;
                    }

                    .lux-cart-price {
                        flex: 1 1 auto !important;
                        min-width: 0 !important;
                        align-items: flex-start !important;
                        font-size: 14px !important;
                        line-height: 1.35 !important;
                    }

                    .lux-cart-price .amount,
                    .lux-cart-price bdi {
                        font-size: inherit !important;
                        line-height: inherit !important;
                    }


                    .lux-cart-wrap .lux-qty button {
                        width: 29px !important;
                        min-width: 29px !important;
                        height: 29px !important;
                        min-height: 29px !important;
                        flex: 0 0 29px !important;
                        border-radius: 9px !important;
                    }

                    .lux-qty input {
                        width: 31px !important;
                        min-width: 31px !important;
                        height: 29px !important;
                        font-size: 14px !important;
                    }

                    .lux-cart-wrap .lux-qty button .material-symbols-outlined {
                        font-size: 19px !important;
                    }

                    .lux-update-qty {
                        min-height: 29px !important;
                        padding: 4px 8px !important;
                        border-radius: 10px !important;
                        font-size: 10.5px !important;
                        box-shadow: 0 8px 18px color-mix(in srgb, var(--lux-primary) 20%, transparent) !important;
                    }

                    .lux-remove {
                        grid-area: remove !important;
                        position: static !important;
                        top: auto !important;
                        left: auto !important;
                        align-self: start !important;
                        justify-self: end !important;
                        width: 32px !important;
                        min-width: 32px !important;
                        height: 32px !important;
                        min-height: 32px !important;
                        flex: 0 0 32px !important;
                        border-radius: 12px !important;
                    }

                    .lux-remove .material-symbols-outlined {
                        font-size: 19px !important;
                    }

                    .lux-summary {
                        padding: 18px !important;
                        border-radius: 20px !important;
                    }

                    .lux-summary h2 {
                        margin-bottom: 16px !important;
                        font-size: 21px !important;
                    }

                    .lux-summary-rows {
                        gap: 10px !important;
                        margin-bottom: 16px !important;
                    }

                    .lux-coupon {
                        min-height: auto !important;
                        margin-top: 12px !important;
                        padding: 7px 9px !important;
                        border-radius: 13px !important;
                    }

                    .lux-coupon input {
                        width: 100% !important;
                        font-size: 12.5px !important;
                    }

                    .lux-cart-wrap .lux-coupon button {
                        min-height: 34px !important;
                        padding: 7px 11px !important;
                        font-size: 12px !important;
                        border-radius: 12px !important;
                    }
                }

                @media (max-width: 420px) {
                    .lux-cart-item {
                        grid-template-columns: 66px minmax(0, 1fr) 30px !important;
                        gap: 8px 9px !important;
                        padding: 10px !important;
                        border-radius: 15px !important;
                    }

                    .lux-cart-media {
                        width: 66px !important;
                        height: 66px !important;
                        flex-basis: 66px !important;
                        border-radius: 12px !important;
                    }

                    .lux-cart-title {
                        font-size: 13.5px !important;
                        line-height: 1.5 !important;
                    }

                    .lux-cart-meta {
                        font-size: 10.5px !important;
                    }

                    .lux-cart-actions {
                        gap: 6px !important;
                    }

                    .lux-cart-price {
                        font-size: 13px !important;
                    }

                    .lux-cart-wrap .lux-qty button {
                        width: 27px !important;
                        min-width: 27px !important;
                        height: 27px !important;
                        min-height: 27px !important;
                        flex-basis: 27px !important;
                    }

                    .lux-qty input {
                        width: 28px !important;
                        min-width: 28px !important;
                        height: 27px !important;
                        font-size: 13px !important;
                    }

                    .lux-update-qty {
                        min-height: 27px !important;
                        padding: 3px 7px !important;
                        font-size: 10px !important;
                    }

                    .lux-remove {
                        width: 30px !important;
                        min-width: 30px !important;
                        height: 30px !important;
                        min-height: 30px !important;
                    }
                }


                /* Mobile-only product extra data popup - custom scoped classes */
                .lux-cart-mobile-extra-trigger {
                    display: none !important;
                    appearance: none !important;
                    -webkit-appearance: none !important;
                    border: 1px solid color-mix(in srgb, var(--lux-primary) 16%, transparent) !important;
                    background: color-mix(in srgb, var(--lux-surface) 90%, var(--lux-primary) 10%) !important;
                    color: var(--lux-primary) !important;
                    box-shadow: none !important;
                    cursor: pointer !important;
                    font-family: inherit !important;
                    font-weight: 900 !important;
                    align-items: center !important;
                    justify-content: center !important;
                    gap: 5px !important;
                    min-height: 28px !important;
                    padding: 4px 9px !important;
                    border-radius: 999px !important;
                    font-size: 10.5px !important;
                    line-height: 1.2 !important;
                    white-space: nowrap !important;
                    position: relative !important;
                    overflow: hidden !important;
                    isolation: isolate !important;
                    transition: transform .18s ease, background .22s ease, color .22s ease, border-color .22s ease !important;
                }

                .lux-cart-mobile-extra-trigger::after {
                    display: none !important;
                    content: none !important;
                }

                .lux-cart-mobile-extra-trigger .material-symbols-outlined {
                    font-size: 17px !important;
                    line-height: 1 !important;
                }

                .lux-cart-mobile-extra-trigger:hover,
                .lux-cart-mobile-extra-trigger:focus-visible {
                    background: #fff !important;
                    color: var(--lux-primary) !important;
                    border-color: color-mix(in srgb, var(--lux-primary) 28%, transparent) !important;
                    transform: translateY(-1px) !important;
                    box-shadow: 0 8px 18px rgba(11, 28, 48, .08) !important;
                    filter: none !important;
                }

                .lux-cart-extra-modal {
                    --lux-primary: var(--e-global-color-primary, var(--wp--preset--color--primary, #3525cd));
                    --lux-secondary: var(--e-global-color-secondary, var(--wp--preset--color--secondary, #6b38d4));
                    --lux-surface: var(--wp--preset--color--base, #ffffff);
                    --lux-text: var(--e-global-color-text, var(--wp--preset--color--contrast, #0b1c30));
                    --lux-muted: color-mix(in srgb, var(--lux-text) 62%, transparent);
                    --lux-outline: color-mix(in srgb, var(--lux-text) 14%, transparent);
                    position: fixed !important;
                    inset: 0 !important;
                    z-index: 1000002 !important;
                    display: flex !important;
                    align-items: flex-end !important;
                    justify-content: center !important;
                    padding: 12px !important;
                    opacity: 0 !important;
                    visibility: hidden !important;
                    pointer-events: none !important;
                    direction: rtl !important;
                    font-family: "Vazirmatn", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif !important;
                    color: var(--lux-text) !important;
                    transition: opacity .24s ease, visibility .24s ease !important;
                }

                .lux-cart-extra-modal.is-open {
                    opacity: 1 !important;
                    visibility: visible !important;
                    pointer-events: auto !important;
                }

                .lux-cart-extra-backdrop {
                    position: absolute !important;
                    inset: 0 !important;
                    background: rgba(11, 28, 48, .42) !important;
                    backdrop-filter: blur(9px) saturate(1.08) !important;
                    -webkit-backdrop-filter: blur(9px) saturate(1.08) !important;
                }

                .lux-cart-extra-sheet {
                    position: relative !important;
                    z-index: 1 !important;
                    width: min(520px, 100%) !important;
                    max-height: min(72vh, 560px) !important;
                    overflow: auto !important;
                    padding: 20px 18px 18px !important;
                    border-radius: 24px !important;
                    background: color-mix(in srgb, var(--lux-surface) 94%, transparent) !important;
                    border: 1px solid color-mix(in srgb, var(--lux-text) 10%, transparent) !important;
                    box-shadow: 0 24px 80px rgba(11, 28, 48, .24) !important;
                    transform: translateY(22px) scale(.98) !important;
                    transition: transform .28s cubic-bezier(.22, 1, .36, 1) !important;
                }

                .lux-cart-extra-modal.is-open .lux-cart-extra-sheet {
                    transform: translateY(0) scale(1) !important;
                }

                .lux-cart-extra-close {
                    position: absolute !important;
                    top: 12px !important;
                    left: 12px !important;
                    width: 34px !important;
                    height: 34px !important;
                    min-width: 34px !important;
                    min-height: 34px !important;
                    display: inline-flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                    border-radius: 12px !important;
                    border: 1px solid var(--lux-outline) !important;
                    background: #fff !important;
                    color: var(--lux-text) !important;
                    box-shadow: 0 8px 18px rgba(11, 28, 48, .08) !important;
                    cursor: pointer !important;
                    padding: 0 !important;
                }

                .lux-cart-extra-close .material-symbols-outlined {
                    font-size: 19px !important;
                }

                .lux-cart-extra-head {
                    display: flex !important;
                    align-items: center !important;
                    gap: 12px !important;
                    padding-left: 44px !important;
                    margin-bottom: 16px !important;
                }

                .lux-cart-extra-icon {
                    width: 42px !important;
                    height: 42px !important;
                    min-width: 42px !important;
                    display: inline-flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                    border-radius: 15px !important;
                    background: linear-gradient(135deg, var(--lux-primary), var(--lux-secondary)) !important;
                    color: #fff !important;
                    box-shadow: 0 14px 28px color-mix(in srgb, var(--lux-primary) 20%, transparent) !important;
                }

                .lux-cart-extra-heading {
                    min-width: 0 !important;
                    display: flex !important;
                    flex-direction: column !important;
                    gap: 3px !important;
                }

                .lux-cart-extra-heading strong {
                    color: var(--lux-text) !important;
                    font-size: 17px !important;
                    line-height: 1.45 !important;
                    font-weight: 950 !important;
                }

                .lux-cart-extra-heading span {
                    color: var(--lux-muted) !important;
                    font-size: 12.5px !important;
                    line-height: 1.6 !important;
                    font-weight: 750 !important;
                    overflow: hidden !important;
                    text-overflow: ellipsis !important;
                    white-space: nowrap !important;
                }

                .lux-cart-extra-content {
                    display: block !important;
                    padding: 14px !important;
                    border-radius: 18px !important;
                    background: color-mix(in srgb, var(--lux-surface) 90%, var(--lux-primary) 10%) !important;
                    border: 1px solid var(--lux-outline) !important;
                    color: var(--lux-text) !important;
                    font-size: 13px !important;
                    line-height: 1.9 !important;
                }

                .lux-cart-extra-content dl,
                .lux-cart-extra-content p {
                    margin: 0 !important;
                }

                .lux-cart-extra-content dl {
                    display: grid !important;
                    grid-template-columns: minmax(88px, auto) minmax(0, 1fr) !important;
                    gap: 8px 12px !important;
                    align-items: start !important;
                }

                .lux-cart-extra-content dt,
                .lux-cart-extra-content dd {
                    margin: 0 !important;
                    padding: 0 !important;
                    display: block !important;
                    line-height: 1.8 !important;
                }

                .lux-cart-extra-content dt {
                    color: var(--lux-muted) !important;
                    font-weight: 850 !important;
                }

                .lux-cart-extra-content dd {
                    color: var(--lux-text) !important;
                    font-weight: 900 !important;
                    min-width: 0 !important;
                    overflow-wrap: anywhere !important;
                }

                body.lux-cart-extra-modal-open {
                    overflow: hidden !important;
                }

                @media (max-width: 640px) {
                    .lux-cart-item-has-extra .lux-cart-meta {
                        align-items: center !important;
                    }

                    .lux-cart-item-has-extra .lux-cart-extra-inline {
                        display: none !important;
                    }

                    .lux-cart-item-has-extra .lux-cart-mobile-extra-trigger {
                        display: inline-flex !important;
                    }

                    .lux-cart-extra-modal {
                        padding: 10px !important;
                    }

                    .lux-cart-extra-sheet {
                        border-radius: 22px !important;
                        padding: 18px 15px 15px !important;
                    }
                }

                @media (max-width: 420px) {
                    .lux-cart-mobile-extra-trigger {
                        width: 29px !important;
                        min-width: 29px !important;
                        height: 29px !important;
                        min-height: 29px !important;
                        padding: 0 !important;
                        border-radius: 10px !important;
                    }

                    .lux-cart-mobile-extra-trigger-text {
                        display: none !important;
                    }

                    .lux-cart-mobile-extra-trigger .material-symbols-outlined {
                        font-size: 18px !important;
                    }

                    .lux-cart-extra-content dl {
                        grid-template-columns: 1fr !important;
                        gap: 3px !important;
                    }

                    .lux-cart-extra-content dt {
                        font-size: 12px !important;
                    }

                    .lux-cart-extra-content dd {
                        margin-bottom: 8px !important;
                    }
                }



                /* v6 - Minimal mobile refinements + safe scoped extra popup */
                .lux-cart-wrap .lux-cart-mobile-extra-trigger {
                    display: none !important;
                    width: 24px !important;
                    min-width: 24px !important;
                    height: 24px !important;
                    min-height: 24px !important;
                    padding: 0 !important;
                    border-radius: 8px !important;
                    border: 1px solid color-mix(in srgb, var(--lux-text) 10%, transparent) !important;
                    background: #fff !important;
                    color: var(--lux-primary) !important;
                    box-shadow: 0 3px 9px rgba(11, 28, 48, .06) !important;
                    filter: none !important;
                    transform: none !important;
                    gap: 0 !important;
                }

                .lux-cart-wrap .lux-cart-mobile-extra-trigger::after,
                .lux-cart-wrap .lux-remove::after,
                .lux-cart-wrap .lux-qty button::after {
                    display: none !important;
                    content: none !important;
                }

                .lux-cart-wrap .lux-cart-mobile-extra-trigger-text {
                    display: none !important;
                }

                .lux-cart-wrap .lux-cart-mobile-extra-trigger .material-symbols-outlined {
                    font-size: 15px !important;
                    line-height: 1 !important;
                }

                .lux-cart-extra-modal {
                    font-family: "Vazirmatn", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif !important;
                }

                .lux-cart-extra-backdrop {
                    background: rgba(11, 28, 48, .34) !important;
                    backdrop-filter: blur(6px) !important;
                    -webkit-backdrop-filter: blur(6px) !important;
                }

                .lux-cart-extra-sheet {
                    width: min(430px, 100%) !important;
                    max-height: min(70vh, 520px) !important;
                    padding: 16px 14px 14px !important;
                    border-radius: 18px !important;
                    background: #fff !important;
                    border: 1px solid color-mix(in srgb, var(--lux-text) 10%, transparent) !important;
                    box-shadow: 0 18px 55px rgba(11, 28, 48, .18) !important;
                }

                .lux-cart-extra-close {
                    width: 28px !important;
                    min-width: 28px !important;
                    height: 28px !important;
                    min-height: 28px !important;
                    border-radius: 9px !important;
                    background: #fff !important;
                    color: var(--lux-text) !important;
                    box-shadow: 0 4px 12px rgba(11, 28, 48, .08) !important;
                }

                .lux-cart-extra-close .material-symbols-outlined {
                    font-size: 17px !important;
                }

                .lux-cart-extra-head {
                    gap: 8px !important;
                    padding-left: 34px !important;
                    margin-bottom: 12px !important;
                }

                .lux-cart-extra-icon {
                    display: none !important;
                }

                .lux-cart-extra-heading strong {
                    font-size: 15px !important;
                    line-height: 1.5 !important;
                }

                .lux-cart-extra-heading span {
                    font-size: 11.5px !important;
                }

                .lux-cart-extra-content {
                    padding: 12px !important;
                    border-radius: 14px !important;
                    background: color-mix(in srgb, var(--lux-surface) 96%, var(--lux-primary) 4%) !important;
                    font-size: 12.5px !important;
                    line-height: 1.85 !important;
                }

                @media (min-width: 641px) {
                    .lux-cart-wrap .lux-cart-mobile-extra-trigger {
                        display: none !important;
                    }
                }

                @media (max-width: 640px) {
                    .lux-cart-shell {
                        padding: 16px 0 30px !important;
                    }

                    .lux-cart-hero {
                        margin-bottom: 12px !important;
                    }

                    .lux-cart-hero h1 {
                        font-size: 25px !important;
                        line-height: 1.3 !important;
                    }

                    .lux-cart-hero p {
                        font-size: 12.5px !important;
                        line-height: 1.65 !important;
                    }

                    .lux-cart-items {
                        gap: 10px !important;
                    }

                    .lux-cart-wrap .lux-cart-item {
                        grid-template-columns: 58px minmax(0, 1fr) 26px !important;
                        gap: 8px !important;
                        padding: 9px !important;
                        border-radius: 14px !important;
                        align-items: center !important;
                        box-shadow: 0 10px 30px rgba(11, 28, 48, .055) !important;
                    }

                    .lux-cart-wrap .lux-cart-item:hover {
                        transform: none !important;
                        box-shadow: 0 10px 30px rgba(11, 28, 48, .07) !important;
                    }

                    .lux-cart-wrap .lux-cart-media {
                        width: 58px !important;
                        height: 58px !important;
                        flex-basis: 58px !important;
                        border-radius: 11px !important;
                        filter: none !important;
                    }

                    .lux-cart-wrap .lux-cart-info {
                        gap: 6px !important;
                    }

                    .lux-cart-wrap .lux-cart-title {
                        margin: 0 0 2px !important;
                        font-size: 12.8px !important;
                        line-height: 1.45 !important;
                        -webkit-line-clamp: 2 !important;
                    }

                    .lux-cart-wrap .lux-cart-meta {
                        justify-content: flex-start !important;
                        align-items: center !important;
                        gap: 4px !important;
                        font-size: 10px !important;
                        line-height: 1.35 !important;
                    }

                    .lux-cart-wrap .lux-cart-meta > span:not(.lux-badge) {
                        display: none !important;
                    }

                    .lux-cart-wrap .lux-badge {
                        min-height: 18px !important;
                        padding: 1px 6px !important;
                        font-size: 9.5px !important;
                    }

                    .lux-cart-wrap .lux-cart-extra-inline {
                        display: none !important;
                    }

                    .lux-cart-wrap .lux-cart-item-has-extra .lux-cart-mobile-extra-trigger {
                        display: inline-flex !important;
                    }

                    .lux-cart-wrap .lux-cart-mobile-extra-trigger:hover,
                    .lux-cart-wrap .lux-cart-mobile-extra-trigger:focus-visible {
                        background: #fff !important;
                        color: var(--lux-primary) !important;
                        transform: translateY(-1px) !important;
                        box-shadow: 0 5px 12px rgba(11, 28, 48, .08) !important;
                    }

                    .lux-cart-wrap .lux-cart-actions {
                        width: 100% !important;
                        flex-direction: row !important;
                        align-items: center !important;
                        justify-content: space-between !important;
                        gap: 6px !important;
                    }

                    .lux-cart-wrap .lux-cart-price {
                        flex: 1 1 auto !important;
                        min-width: 0 !important;
                        align-items: flex-start !important;
                        font-size: 12px !important;
                        line-height: 1.35 !important;
                        white-space: nowrap !important;
                    }

                    .lux-cart-wrap .lux-cart-price .amount,
                    .lux-cart-wrap .lux-cart-price bdi {
                        font-size: inherit !important;
                        line-height: inherit !important;
                    }

                    .lux-cart-wrap .lux-qty-row {
                        gap: 4px !important;
                        flex-wrap: nowrap !important;
                    }

                    .lux-cart-wrap .lux-qty {
                        gap: 3px !important;
                        padding: 2px !important;
                        border-radius: 10px !important;
                        background: color-mix(in srgb, var(--lux-surface) 90%, var(--lux-primary) 10%) !important;
                    }

                    .lux-cart-wrap .lux-qty button,
                    .lux-cart-wrap .lux-qty button:hover,
                    .lux-cart-wrap .lux-qty button:focus-visible,
                    .lux-cart-wrap .lux-qty button[value="qty_minus"]:hover,
                    .lux-cart-wrap .lux-qty button[value="qty_minus"]:focus-visible {
                        width: 24px !important;
                        min-width: 24px !important;
                        height: 24px !important;
                        min-height: 24px !important;
                        flex: 0 0 24px !important;
                        padding: 0 !important;
                        border-radius: 8px !important;
                        background: #fff !important;
                        color: var(--lux-text) !important;
                        border: 1px solid color-mix(in srgb, var(--lux-text) 9%, transparent) !important;
                        box-shadow: 0 3px 9px rgba(11, 28, 48, .055) !important;
                        filter: none !important;
                    }

                    .lux-cart-wrap .lux-qty button:hover,
                    .lux-cart-wrap .lux-qty button:focus-visible {
                        color: var(--lux-primary) !important;
                        transform: translateY(-1px) !important;
                    }

                    .lux-cart-wrap .lux-qty button[value="qty_minus"]:hover,
                    .lux-cart-wrap .lux-qty button[value="qty_minus"]:focus-visible {
                        color: var(--lux-error) !important;
                    }

                    .lux-cart-wrap .lux-qty button .material-symbols-outlined {
                        font-size: 16px !important;
                    }

                    .lux-cart-wrap .lux-qty input {
                        width: 25px !important;
                        min-width: 25px !important;
                        height: 24px !important;
                        font-size: 12px !important;
                    }

                    .lux-cart-wrap .lux-update-qty {
                        min-height: 25px !important;
                        padding: 3px 7px !important;
                        border-radius: 8px !important;
                        font-size: 9.5px !important;
                        box-shadow: none !important;
                    }

                    .lux-cart-wrap .lux-remove,
                    .lux-cart-wrap .lux-remove:hover,
                    .lux-cart-wrap .lux-remove:focus-visible {
                        width: 26px !important;
                        min-width: 26px !important;
                        height: 26px !important;
                        min-height: 26px !important;
                        flex: 0 0 26px !important;
                        padding: 0 !important;
                        border-radius: 8px !important;
                        background: #fff !important;
                        color: var(--lux-muted) !important;
                        border: 1px solid color-mix(in srgb, var(--lux-text) 9%, transparent) !important;
                        box-shadow: 0 3px 10px rgba(11, 28, 48, .055) !important;
                        transform: none !important;
                        filter: none !important;
                    }

                    .lux-cart-wrap .lux-remove:hover,
                    .lux-cart-wrap .lux-remove:focus-visible {
                        color: var(--lux-error) !important;
                    }

                    .lux-cart-wrap .lux-remove .material-symbols-outlined {
                        font-size: 16px !important;
                    }

                    .lux-cart-extra-modal {
                        padding: 8px !important;
                    }

                    .lux-cart-extra-sheet {
                        border-radius: 16px !important;
                        padding: 15px 13px 13px !important;
                    }
                }

                @media (max-width: 420px) {
                    .lux-cart-wrap .lux-cart-item {
                        grid-template-columns: 54px minmax(0, 1fr) 25px !important;
                        gap: 7px !important;
                        padding: 8px !important;
                    }

                    .lux-cart-wrap .lux-cart-media {
                        width: 54px !important;
                        height: 54px !important;
                        flex-basis: 54px !important;
                    }

                    .lux-cart-wrap .lux-cart-title {
                        font-size: 12.2px !important;
                    }

                    .lux-cart-wrap .lux-cart-price {
                        font-size: 11.5px !important;
                    }

                    .lux-cart-wrap .lux-qty button,
                    .lux-cart-wrap .lux-qty input {
                        width: 23px !important;
                        min-width: 23px !important;
                        height: 23px !important;
                        min-height: 23px !important;
                    }

                    .lux-cart-wrap .lux-update-qty {
                        min-height: 23px !important;
                        padding: 2px 6px !important;
                        font-size: 9px !important;
                    }

                    .lux-cart-wrap .lux-remove {
                        width: 25px !important;
                        min-width: 25px !important;
                        height: 25px !important;
                        min-height: 25px !important;
                    }
                }

</style>

            <script>
                (function() {
                    var root = document.getElementById(<?php echo wp_json_encode( $instance_id ); ?>);
                    if (!root || root.getAttribute('data-lux-ready') === '1') {
                        return;
                    }
                    root.setAttribute('data-lux-ready', '1');

                    var hideChromeEnabled = <?php echo $hide_chrome ? 'true' : 'false'; ?>;
                    var activeModal = null;
                    var activeTrigger = null;
                    var activeHost = null;

                    function matchesElement(el, selector) {
                        if (!el || el.nodeType !== 1) {
                            return false;
                        }

                        var fn = el.matches || el.msMatchesSelector || el.webkitMatchesSelector;
                        return fn ? fn.call(el, selector) : false;
                    }

                    function closestElement(el, selector) {
                        while (el && el !== document) {
                            if (matchesElement(el, selector)) {
                                return el;
                            }
                            el = el.parentNode;
                        }

                        return null;
                    }

                    function hideChrome() {
                        if (!hideChromeEnabled) {
                            return;
                        }

                        document.body.classList.add('lux-cart-page-active');

                        var selectors = [
                            '#masthead',
                            '.site-header',
                            'header.wp-block-template-part',
                            '.wp-site-blocks > header',
                            '.elementor-location-header',
                            '.elementor-header',
                            '#colophon',
                            '.site-footer',
                            'footer.wp-block-template-part',
                            '.wp-site-blocks > footer',
                            '.elementor-location-footer',
                            '.elementor-footer'
                        ];

                        var nodes = document.querySelectorAll(selectors.join(','));

                        for (var i = 0; i < nodes.length; i++) {
                            if (!root.contains(nodes[i])) {
                                nodes[i].setAttribute('data-lux-hidden-chrome', '1');
                                nodes[i].style.setProperty('display', 'none', 'important');
                            }
                        }
                    }

                    function getReturnUrl() {
                        var fallback = root.getAttribute('data-lux-page-url') || window.location.href;
                        var path = window.location && window.location.pathname ? window.location.pathname.replace(/\/+$/, '') : '';

                        if (path === '/--') {
                            return fallback;
                        }

                        return window.location && window.location.href ? window.location.href : fallback;
                    }

                    function syncModalTheme(modal) {
                        if (!modal || !window.getComputedStyle) {
                            return;
                        }

                        var styles = window.getComputedStyle(root);
                        var names = ['--lux-primary', '--lux-secondary', '--lux-surface', '--lux-text', '--lux-muted', '--lux-outline'];

                        for (var i = 0; i < names.length; i++) {
                            var value = styles.getPropertyValue(names[i]);

                            if (value) {
                                modal.style.setProperty(names[i], value.replace(/^\s+|\s+$/g, ''));
                            }
                        }
                    }

                    function closeExtraModal() {
                        if (!activeModal) {
                            return;
                        }

                        var modal = activeModal;
                        var trigger = activeTrigger;
                        var host = activeHost;

                        modal.classList.remove('is-open');
                        modal.setAttribute('aria-hidden', 'true');

                        if (trigger) {
                            trigger.setAttribute('aria-expanded', 'false');
                        }

                        document.body.classList.remove('lux-cart-extra-modal-open');

                        window.setTimeout(function() {
                            if (host && modal.parentNode === document.body) {
                                host.appendChild(modal);
                            }
                        }, 220);

                        activeModal = null;
                        activeTrigger = null;
                        activeHost = null;
                    }

                    function openExtraModal(trigger) {
                        if (!trigger) {
                            return;
                        }

                        var item = closestElement(trigger, '.lux-cart-item');
                        var modal = item ? item.querySelector('[data-lux-cart-extra-modal]') : null;

                        if (!modal) {
                            return;
                        }

                        closeExtraModal();

                        activeModal = modal;
                        activeTrigger = trigger;
                        activeHost = modal.parentNode;

                        syncModalTheme(modal);

                        if (modal.parentNode !== document.body) {
                            document.body.appendChild(modal);
                        }

                        modal.classList.add('is-open');
                        modal.setAttribute('aria-hidden', 'false');
                        trigger.setAttribute('aria-expanded', 'true');
                        document.body.classList.add('lux-cart-extra-modal-open');

                        window.setTimeout(function() {
                            var closeButton = modal.querySelector('[data-lux-cart-extra-close]');

                            if (closeButton && closeButton.focus) {
                                try {
                                    closeButton.focus();
                                } catch (ignore) {}
                            }
                        }, 30);
                    }

                    root.addEventListener('submit', function(e) {
                        var form = e.target;
                        var returnUrl = getReturnUrl();

                        if (form && matchesElement(form, 'form')) {
                            form.setAttribute('action', returnUrl);

                            var field = form.querySelector('input[name="lux_current_url"]');

                            if (field) {
                                field.value = returnUrl;
                            }
                        }

                        root.classList.add('is-loading');
                    }, false);

                    root.addEventListener('keydown', function(e) {
                        if (e.key !== 'Enter') {
                            return;
                        }

                        if (!matchesElement(e.target, '[data-lux-qty-input]')) {
                            return;
                        }

                        var form = closestElement(e.target, 'form');
                        var updateButton = form ? form.querySelector('[data-lux-update-qty]') : null;

                        if (updateButton && form && form.requestSubmit) {
                            e.preventDefault();
                            form.requestSubmit(updateButton);
                        }
                    }, false);

                    root.addEventListener('click', function(e) {
                        var trigger = closestElement(e.target, '[data-lux-cart-extra-open]');

                        if (!trigger || !root.contains(trigger)) {
                            return;
                        }

                        e.preventDefault();
                        e.stopPropagation();
                        openExtraModal(trigger);
                    }, true);

                    document.addEventListener('click', function(e) {
                        if (!activeModal) {
                            return;
                        }

                        var closeTarget = closestElement(e.target, '[data-lux-cart-extra-close]');

                        if (closeTarget && activeModal.contains(closeTarget)) {
                            e.preventDefault();
                            e.stopPropagation();
                            closeExtraModal();
                        }
                    }, true);

                    document.addEventListener('keydown', function(e) {
                        if (e.key === 'Escape' && activeModal) {
                            closeExtraModal();
                        }
                    }, false);

                    hideChrome();
                })();
            </script>
            <?php
            return ob_get_clean();
        }

        private static function render_cart_html() {
            $cart = self::ensure_cart();

            if ( ! $cart ) {
                return '<div class="lux-cart-error">WooCommerce در دسترس نیست.</div>';
            }

            self::calculate_cart();

            ob_start();

            if ( $cart->is_empty() ) {
                self::render_empty_cart();
            } else {
                self::render_filled_cart();
            }

            return ob_get_clean();
        }

        private static function render_notices() {
            if ( ! function_exists( 'wc_print_notices' ) ) {
                return;
            }

            // پیام «به سبد خرید اضافه شد» روی خود صفحه سبد تکراری است.
            // خطاها و پیام‌های مفید دیگر (کوپن، تعداد و حذف محصول) حفظ می‌شوند.
            if ( function_exists( 'wc_get_notices' ) && function_exists( 'wc_set_notices' ) ) {
                $all_notices = wc_get_notices();

                if ( ! empty( $all_notices['success'] ) ) {
                    $all_notices['success'] = array_values(
                        array_filter(
                            $all_notices['success'],
                            static function ( $notice ) {
                                $message = is_array( $notice ) && isset( $notice['notice'] ) ? (string) $notice['notice'] : (string) $notice;
                                $plain   = wp_strip_all_tags( $message );

                                $is_add_to_cart = false !== strpos( $plain, 'به سبد خرید' )
                                    || false !== stripos( $plain, 'added to your cart' )
                                    || ( false !== strpos( $message, 'wc-forward' ) && false !== strpos( $plain, 'مشاهده سبد خرید' ) );

                                return ! $is_add_to_cart;
                            }
                        )
                    );

                    wc_set_notices( $all_notices );
                }
            }

            ob_start();
            wc_print_notices();
            $notices = ob_get_clean();

            if ( $notices ) {
                echo '<div class="lux-notices">' . wp_kses_post( $notices ) . '</div>';
            }
        }

        private static function render_empty_cart() {
            $shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
            ?>
            <main class="lux-cart-shell">
                <?php self::render_notices(); ?>

                <div class="lux-empty">
                    <div class="lux-empty-card lux-glass">
                        <div class="lux-empty-icon">
                            <span class="material-symbols-outlined">shopping_cart</span>
                        </div>
                        <h2>سبد خرید شما خالی است</h2>
                        <p>هنوز محصولی به سبد خرید اضافه نکرده‌اید.</p>
                        <a class="lux-btn" href="<?php echo esc_url( $shop_url ); ?>">بازگشت به فروشگاه</a>
                    </div>
                </div>
            </main>
            <?php
        }

        private static function render_filled_cart() {
            $cart     = WC()->cart;
            $shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
            ?>
            <main class="lux-cart-shell">
                <section class="lux-cart-hero">
                    <h1>سبد خرید شما</h1>
                    <p>محصولات، تعداد، تخفیف و روش ارسال را قبل از پرداخت بررسی کنید.</p>
                </section>

                <?php self::render_notices(); ?>

                <section class="lux-cart-grid" aria-label="سبد خرید">
                    <div class="lux-cart-items">
                        <?php
                        $i = 0;

                        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) :
                            $product = isset( $cart_item['data'] ) ? $cart_item['data'] : false;

                            if ( ! $product || ! $product->exists() || $cart_item['quantity'] <= 0 ) {
                                continue;
                            }

                            $i++;

                            $product_name      = $product->get_name();
                            $product_permalink = $product->is_visible() ? $product->get_permalink( $cart_item ) : '';
                            $thumbnail         = $product->get_image( 'woocommerce_thumbnail' );
                            $quantity          = (int) $cart_item['quantity'];
                            $max_quantity      = $product->get_max_purchase_quantity();
                            $line_price        = $cart->get_product_subtotal( $product, $quantity );
                            $unit_price        = $cart->get_product_price( $product );
                            $item_meta         = wc_get_formatted_cart_item_data( $cart_item );
                            $extra_modal_id    = 'lux-cart-extra-' . md5( $cart_item_key );
                            $badge             = $product->is_on_sale() ? 'تخفیف ویژه' : ( $product->is_in_stock() ? 'موجود' : 'نیازمند بررسی' );
                            $badge_class       = $product->is_on_sale() ? 'secondary' : '';
                            ?>
                            <form class="lux-cart-item lux-glass <?php echo $item_meta ? 'lux-cart-item-has-extra' : ''; ?>" method="post"<?php self::render_form_action_attr(); ?> style="animation-delay: <?php echo esc_attr( min( $i * 0.08, 0.48 ) ); ?>s;">
                                <?php self::render_form_hidden_fields( $cart_item_key ); ?>

                                <div class="lux-cart-media">
                                    <?php echo wp_kses_post( $thumbnail ); ?>
                                </div>

                                <div class="lux-cart-info">
                                    <div class="lux-cart-copy">
                                        <?php if ( $product_permalink ) : ?>
                                            <a class="lux-cart-title" href="<?php echo esc_url( $product_permalink ); ?>">
                                                <?php echo esc_html( $product_name ); ?>
                                            </a>
                                        <?php else : ?>
                                            <h3 class="lux-cart-title"><?php echo esc_html( $product_name ); ?></h3>
                                        <?php endif; ?>

                                        <div class="lux-cart-meta">
                                            <span class="lux-badge <?php echo esc_attr( $badge_class ); ?>">
                                                <?php echo esc_html( $badge ); ?>
                                            </span>

                                            <span><?php echo wp_kses_post( $unit_price ); ?> / عدد</span>

                                            <?php if ( $item_meta ) : ?>
                                                <div class="lux-cart-extra-inline"><?php echo wp_kses_post( $item_meta ); ?></div>

                                                <button
                                                    type="button"
                                                    class="lux-cart-mobile-extra-trigger"
                                                    data-lux-cart-extra-open
                                                    aria-controls="<?php echo esc_attr( $extra_modal_id ); ?>"
                                                    aria-expanded="false"
                                                    aria-label="مشاهده جزئیات و ویژگی‌های محصول"
                                                >
                                                    <span class="material-symbols-outlined" aria-hidden="true">tune</span>
                                                    <span class="lux-cart-mobile-extra-trigger-text">جزئیات</span>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="lux-cart-actions">
                                        <div class="lux-cart-price">
                                            <?php echo wp_kses_post( $line_price ); ?>
                                        </div>

                                        <input type="hidden" name="quantity" value="1">
                                    </div>
                                </div>

                                <button
                                    class="lux-remove"
                                    type="submit"
                                    name="lux_cart_action"
                                    value="remove_item"
                                    aria-label="حذف محصول"
                                >
                                    <span class="material-symbols-outlined">delete</span>
                                </button>

                                <?php if ( $item_meta ) : ?>
                                    <div
                                        id="<?php echo esc_attr( $extra_modal_id ); ?>"
                                        class="lux-cart-extra-modal"
                                        data-lux-cart-extra-modal
                                        aria-hidden="true"
                                    >
                                        <div class="lux-cart-extra-backdrop" data-lux-cart-extra-close></div>

                                        <div class="lux-cart-extra-sheet" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr( $extra_modal_id ); ?>-title">
                                            <button type="button" class="lux-cart-extra-close" data-lux-cart-extra-close aria-label="بستن جزئیات محصول">
                                                <span class="material-symbols-outlined" aria-hidden="true">close</span>
                                            </button>

                                            <div class="lux-cart-extra-head">
                                                <span class="lux-cart-extra-icon">
                                                    <span class="material-symbols-outlined" aria-hidden="true">tune</span>
                                                </span>

                                                <div class="lux-cart-extra-heading">
                                                    <strong id="<?php echo esc_attr( $extra_modal_id ); ?>-title">جزئیات محصول</strong>
                                                    <span><?php echo esc_html( $product_name ); ?></span>
                                                </div>
                                            </div>

                                            <div class="lux-cart-extra-content">
                                                <?php echo wp_kses_post( $item_meta ); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </form>
                        <?php endforeach; ?>
                    </div>

                    <aside class="lux-summary lux-glass" aria-label="خلاصه سفارش">
                        <h2>خلاصه سفارش</h2>

                        <div class="lux-summary-rows">
                            <div class="lux-summary-row">
                                <span>جمع کل اقلام</span>
                                <span><?php echo wp_kses_post( $cart->get_cart_subtotal() ); ?></span>
                            </div>

                            <?php self::render_shipping_methods(); ?>

                            <?php foreach ( $cart->get_fees() as $fee ) : ?>
                                <div class="lux-summary-row fee">
                                    <span><?php echo esc_html( $fee->name ); ?></span>
                                    <span><?php wc_cart_totals_fee_html( $fee ); ?></span>
                                </div>
                            <?php endforeach; ?>

                            <?php if ( $cart->get_discount_total() > 0 ) : ?>
                                <div class="lux-summary-row discount">
                                    <span>تخفیف</span>
                                    <span>-<?php echo wp_kses_post( wc_price( $cart->get_discount_total() ) ); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if ( wc_tax_enabled() && ! $cart->display_prices_including_tax() ) : ?>
                                <?php foreach ( $cart->get_tax_totals() as $tax ) : ?>
                                    <div class="lux-summary-row tax">
                                        <span><?php echo esc_html( $tax->label ); ?></span>
                                        <span><?php echo wp_kses_post( $tax->formatted_amount ); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <div class="lux-summary-row total">
                                <span>مبلغ قابل پرداخت</span>
                                <strong><?php echo wp_kses_post( $cart->get_total() ); ?></strong>
                            </div>
                        </div>

                        <a class="lux-btn" href="<?php echo esc_url( wc_get_checkout_url() ); ?>">
                            ادامه جهت تسویه حساب
                        </a>

                        <?php if ( wc_coupons_enabled() ) : ?>
                            <form class="lux-coupon" method="post"<?php self::render_form_action_attr(); ?>>
                                <?php self::render_form_hidden_fields(); ?>
                                <span class="material-symbols-outlined" aria-hidden="true">local_offer</span>
                                <input type="text" name="coupon_code" placeholder="کد تخفیف" autocomplete="off">
                                <button type="submit" name="lux_cart_action" value="apply_coupon">اعمال کد</button>
                            </form>

                            <?php if ( $cart->get_applied_coupons() ) : ?>
                                <div class="lux-coupons-list">
                                    <?php foreach ( $cart->get_applied_coupons() as $coupon_code ) : ?>
                                        <span class="lux-coupon-pill">
                                            <?php echo esc_html( wc_format_coupon_code( $coupon_code ) ); ?>

                                            <form method="post"<?php self::render_form_action_attr(); ?>>
                                                <?php self::render_form_hidden_fields(); ?>
                                                <input type="hidden" name="coupon_code" value="<?php echo esc_attr( $coupon_code ); ?>">
                                                <button
                                                    type="submit"
                                                    name="lux_cart_action"
                                                    value="remove_coupon"
                                                    aria-label="حذف کد تخفیف"
                                                >
                                                    <span class="material-symbols-outlined" style="font-size:16px">close</span>
                                                </button>
                                            </form>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <div class="lux-trust">
                            <div class="lux-trust-item">
                                <span class="material-symbols-outlined">verified_user</span>
                                <span>پرداخت امن و اتصال مستقیم به تسویه حساب فروشگاه</span>
                            </div>

                            
                        </div>

                        <a class="lux-continue" href="<?php echo esc_url( $shop_url ); ?>">
                            <span class="material-symbols-outlined">arrow_forward</span>
                            <span>بازگشت به فروشگاه و ادامه خرید</span>
                        </a>
                    </aside>
                </section>
            </main>
            <?php
        }

        private static function render_shipping_methods() {
            $cart = WC()->cart;

            if ( ! $cart || ! $cart->needs_shipping() ) {
                ?>
                <div class="lux-summary-row">
                    <span>ارسال</span>
                    <span>نیاز ندارد</span>
                </div>
                <?php
                return;
            }

            if ( WC()->shipping() ) {
                $cart->calculate_shipping();
            }

            $packages = WC()->shipping() ? WC()->shipping()->get_packages() : array();

            if ( empty( $packages ) ) {
                $packages = $cart->get_shipping_packages();

                if ( WC()->shipping() ) {
                    WC()->shipping()->calculate_shipping( $packages );
                    $packages = WC()->shipping()->get_packages();
                }
            }

            ?>
            <div class="lux-summary-row">
                <span>هزینه ارسال</span>
                <span><?php echo wp_kses_post( $cart->get_cart_shipping_total() ); ?></span>
            </div>

            <div class="lux-shipping-box">
                <div class="lux-shipping-title">
                    <span>روش ارسال</span>
                    <span class="material-symbols-outlined" aria-hidden="true">local_shipping</span>
                </div>

                <?php
                if ( empty( $packages ) ) :
                    ?>
                    <p class="lux-shipping-empty">برای این سبد خرید بسته ارسال محاسبه نشد.</p>
                    <?php
                else :
                    $has_rates = false;

                foreach ( $packages as $package_index => $package ) :
                    $rates = isset( $package['rates'] ) && is_array( $package['rates'] ) ? $package['rates'] : array();

                    if ( empty( $rates ) ) {
                        continue;
                    }

                    $has_rates     = true;
                    $package_name  = apply_filters( 'woocommerce_shipping_package_name', ( count( $packages ) > 1 ? sprintf( 'بسته ارسال %d', $package_index + 1 ) : '' ), $package_index, $package );
                    $chosen_method = '';

                    if ( function_exists( 'wc_get_chosen_shipping_method_for_package' ) ) {
                        $chosen_method = wc_get_chosen_shipping_method_for_package( $package_index, $package );
                    } else {
                        $chosen_methods = WC()->session ? WC()->session->get( 'chosen_shipping_methods', array() ) : array();
                        $chosen_method  = isset( $chosen_methods[ $package_index ] ) ? $chosen_methods[ $package_index ] : key( $rates );
                    }
                    ?>
                    <div class="lux-shipping-package">
                        <?php if ( $package_name ) : ?>
                            <p class="lux-shipping-package-name"><?php echo esc_html( $package_name ); ?></p>
                        <?php endif; ?>

                        <?php foreach ( $rates as $rate_id => $rate ) : ?>
                            <form class="lux-shipping-method-form" method="post"<?php self::render_form_action_attr(); ?>>
                                <?php self::render_form_hidden_fields(); ?>
                                <input type="hidden" name="package_id" value="<?php echo esc_attr( $package_index ); ?>">
                                <input type="hidden" name="method_id" value="<?php echo esc_attr( $rate_id ); ?>">

                                <button class="lux-shipping-method" type="submit" name="lux_cart_action" value="update_shipping_method">
                                    <span class="lux-radio <?php echo checked( $rate_id, $chosen_method, false ) ? 'is-checked' : ''; ?>" aria-hidden="true"></span>
                                    <span class="lux-shipping-method-text">
                                        <span><?php echo wp_kses_post( wc_cart_totals_shipping_method_label( $rate ) ); ?></span>
                                    </span>
                                </button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>

                <?php if ( ! $has_rates ) : ?>
                    <p class="lux-shipping-empty">برای آدرس فعلی مشتری، روش ارسال فعالی پیدا نشد. اگر فروشگاه نیاز به شهر، استان یا کدپستی دارد، در مرحله تسویه حساب بعد از وارد کردن آدرس نمایش داده می‌شود.</p>
                <?php endif; ?>

                <?php endif; ?>
            </div>
            <?php
        }
    }

    Luxury_Reload_Woo_Cart_Shortcode_V2::init();
}
