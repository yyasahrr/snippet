/**
 * Luxury Academy WooCommerce Checkout
 * Shortcode: [luxury_checkout]
 *
 * Options:
 * [luxury_checkout fields="contact"]              نام، نام خانوادگی، کشور، تلفن و ایمیل
 * [luxury_checkout fields="full"]                 تمام فیلدهای صورتحساب ووکامرس
 * [luxury_checkout hide_chrome="yes"]             مخفی کردن هدر و فوتر قالب
 *
 * این کد برای افزونه Code Snippets نوشته شده است؛ آن را بدون تگ آغازین PHP وارد کنید.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Luxury_Academy_Checkout_Shortcode' ) ) {

    final class Luxury_Academy_Checkout_Shortcode {

        const SHORTCODE = 'luxury_checkout';

        private static $rendering      = false;
        private static $compact_fields = true;

        public static function init() {
            add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
            add_filter( 'woocommerce_checkout_fields', array( __CLASS__, 'compact_checkout_fields' ), 99 );
        }

        private static function ensure_woocommerce() {
            if ( ! function_exists( 'WC' ) || ! class_exists( 'WooCommerce' ) ) {
                return false;
            }

            if ( function_exists( 'wc_load_cart' ) && ( is_null( WC()->cart ) || is_null( WC()->session ) ) ) {
                wc_load_cart();
            }

            return true;
        }

        public static function compact_checkout_fields( $fields ) {
            $use_compact_fields = self::$rendering && self::$compact_fields;

            if ( ! self::$rendering && function_exists( 'WC' ) && WC()->session ) {
                $use_compact_fields = 'yes' === WC()->session->get( 'lux_academy_compact_checkout', 'no' );
            }

            if ( ! $use_compact_fields ) {
                return $fields;
            }

            $remove = array(
                'billing_company',
                'billing_address_1',
                'billing_address_2',
                'billing_city',
                'billing_state',
                'billing_postcode',
            );

            foreach ( $remove as $key ) {
                unset( $fields['billing'][ $key ] );
            }

            if ( isset( $fields['billing']['billing_first_name'] ) ) {
                $fields['billing']['billing_first_name']['priority'] = 10;
                $fields['billing']['billing_first_name']['class']    = array( 'form-row-first' );
            }

            if ( isset( $fields['billing']['billing_last_name'] ) ) {
                $fields['billing']['billing_last_name']['priority'] = 20;
                $fields['billing']['billing_last_name']['class']    = array( 'form-row-last' );
            }

            if ( isset( $fields['billing']['billing_country'] ) ) {
                $fields['billing']['billing_country']['priority'] = 30;
                $fields['billing']['billing_country']['class']    = array( 'form-row-wide', 'lux-country-field' );
            }

            if ( isset( $fields['billing']['billing_phone'] ) ) {
                $fields['billing']['billing_phone']['priority'] = 40;
                $fields['billing']['billing_phone']['class']    = array( 'form-row-first' );
                $fields['billing']['billing_phone']['label']    = 'شماره موبایل';
            }

            if ( isset( $fields['billing']['billing_email'] ) ) {
                $fields['billing']['billing_email']['priority'] = 50;
                $fields['billing']['billing_email']['class']    = array( 'form-row-last' );
                $fields['billing']['billing_email']['label']    = 'ایمیل';
            }

            return $fields;
        }

        public static function render( $atts = array() ) {
            if ( ! self::ensure_woocommerce() ) {
                return '<div class="lux-checkout-system-message">برای استفاده از این شورت‌کد باید ووکامرس فعال باشد.</div>';
            }

            $atts = shortcode_atts(
                array(
                    'fields'      => 'contact',
                    'hide_chrome' => 'no',
                ),
                $atts,
                self::SHORTCODE
            );

            self::$compact_fields = 'full' !== strtolower( trim( (string) $atts['fields'] ) );
            self::$rendering      = true;

            if ( WC()->session ) {
                WC()->session->set( 'lux_academy_compact_checkout', self::$compact_fields ? 'yes' : 'no' );
            }

            $checkout_html = do_shortcode( '[woocommerce_checkout]' );

            self::$rendering = false;

            $instance_id = 'lux-checkout-' . wp_rand( 10000, 99999 );
            $hide_chrome = in_array( strtolower( (string) $atts['hide_chrome'] ), array( 'yes', 'true', '1', 'on' ), true );
            $is_received = function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' );

            ob_start();
            ?>
            <div
                id="<?php echo esc_attr( $instance_id ); ?>"
                class="lux-checkout-wrap <?php echo $is_received ? 'is-order-received' : ''; ?>"
                data-hide-chrome="<?php echo $hide_chrome ? '1' : '0'; ?>"
                dir="rtl"
            >
                <?php echo self::assets( $instance_id ); ?>

                <main class="lux-checkout-shell">
                    <header class="lux-checkout-hero">
                        <div>
                            <span class="lux-checkout-kicker">تکمیل ثبت‌نام دوره</span>
                            <h1><?php echo $is_received ? 'ثبت‌نام شما تکمیل شد' : 'پرداخت امن و سریع'; ?></h1>
                            <p><?php echo $is_received ? 'جزئیات سفارش و دسترسی شما در ادامه نمایش داده شده است.' : 'اطلاعاتتان را وارد کنید، سفارش را بررسی کنید و پرداخت را انجام دهید.'; ?></p>
                        </div>

                        <?php if ( ! $is_received ) : ?>
                            <div class="lux-secure-badge" aria-label="پرداخت امن">
                                <span class="lux-symbol" aria-hidden="true">lock</span>
                                <span><strong>پرداخت امن</strong><small>اطلاعات شما رمزنگاری می‌شود</small></span>
                            </div>
                        <?php endif; ?>
                    </header>

                    <nav class="lux-checkout-steps" aria-label="مراحل ثبت سفارش">
                        <button type="button" class="lux-step is-active" data-lux-step-button="1" aria-current="step">
                            <span class="lux-step-number">۱</span>
                            <span class="lux-step-copy"><strong>اطلاعات خریدار</strong><small>مشخصات دریافت دسترسی</small></span>
                        </button>
                        <span class="lux-step-line" aria-hidden="true"></span>
                        <button type="button" class="lux-step" data-lux-step-button="2" tabindex="-1">
                            <span class="lux-step-number">۲</span>
                            <span class="lux-step-copy"><strong>بازبینی و پرداخت</strong><small>کنترل سفارش و درگاه</small></span>
                        </button>
                        <span class="lux-step-line" aria-hidden="true"></span>
                        <span class="lux-step <?php echo $is_received ? 'is-active is-complete' : ''; ?>" data-lux-step-button="3">
                            <span class="lux-step-number"><?php echo $is_received ? '✓' : '۳'; ?></span>
                            <span class="lux-step-copy"><strong>تکمیل سفارش</strong><small>دریافت نتیجه ثبت‌نام</small></span>
                        </span>
                    </nav>

                    <section class="lux-checkout-stage">
                        <?php echo $checkout_html; ?>
                    </section>

                    <?php if ( ! $is_received ) : ?>
                        <footer class="lux-checkout-trust" aria-label="مزایای خرید">
                            <span><i class="lux-symbol" aria-hidden="true">verified_user</i> پرداخت از درگاه امن</span>
                            <span><i class="lux-symbol" aria-hidden="true">school</i> دسترسی به دوره پس از پرداخت</span>
                            <span><i class="lux-symbol" aria-hidden="true">support_agent</i> پشتیبانی ثبت‌نام</span>
                        </footer>
                    <?php endif; ?>
                </main>
            </div>
            <?php
            return ob_get_clean();
        }

        private static function assets( $instance_id ) {
            ob_start();
            ?>
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@300..600&display=swap" rel="stylesheet">
            <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css">

            <style>
                #<?php echo esc_attr( $instance_id ); ?>,
                #<?php echo esc_attr( $instance_id ); ?> * { box-sizing: border-box; }

                #<?php echo esc_attr( $instance_id ); ?> {
                    --lux-primary: var(--e-global-color-primary, var(--wp--preset--color--primary, #3525cd));
                    --lux-secondary: var(--e-global-color-secondary, var(--wp--preset--color--secondary, #6b38d4));
                    --lux-ink: var(--e-global-color-text, var(--wp--preset--color--contrast, #101828));
                    --lux-muted: #667085;
                    --lux-border: #e4e7ec;
                    --lux-soft: #f7f7fb;
                    --lux-surface: #ffffff;
                    --lux-danger: #b42318;
                    --lux-success: #067647;
                    width: 100%;
                    color: var(--lux-ink);
                    direction: rtl;
                    font-family: "Vazirmatn", Tahoma, sans-serif;
                    position: relative;
                    isolation: isolate;
                }

                #<?php echo esc_attr( $instance_id ); ?> button,
                #<?php echo esc_attr( $instance_id ); ?> input,
                #<?php echo esc_attr( $instance_id ); ?> select,
                #<?php echo esc_attr( $instance_id ); ?> textarea { font-family: inherit; }

                #<?php echo esc_attr( $instance_id ); ?> .lux-symbol {
                    font-family: "Material Symbols Outlined";
                    font-weight: normal;
                    font-style: normal;
                    font-size: 22px;
                    line-height: 1;
                    letter-spacing: normal;
                    text-transform: none;
                    display: inline-block;
                    white-space: nowrap;
                    word-wrap: normal;
                    direction: ltr;
                    -webkit-font-feature-settings: "liga";
                    -webkit-font-smoothing: antialiased;
                    font-feature-settings: "liga";
                }

                #<?php echo esc_attr( $instance_id ); ?> .lux-checkout-shell {
                    width: min(1180px, calc(100% - 32px));
                    margin: 0 auto;
                    padding: 38px 0 54px;
                }

                #<?php echo esc_attr( $instance_id ); ?> .lux-checkout-hero {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-end;
                    gap: 28px;
                    margin-bottom: 28px;
                }

                #<?php echo esc_attr( $instance_id ); ?> .lux-checkout-kicker {
                    display: block;
                    margin-bottom: 8px;
                    color: var(--lux-primary);
                    font-size: 13px;
                    font-weight: 800;
                }

                #<?php echo esc_attr( $instance_id ); ?> .lux-checkout-hero h1 {
                    margin: 0 0 8px;
                    color: var(--lux-ink);
                    font-size: clamp(30px, 4vw, 48px);
                    font-weight: 900;
                    line-height: 1.25;
                    letter-spacing: -.035em;
                }

                #<?php echo esc_attr( $instance_id ); ?> .lux-checkout-hero p {
                    margin: 0;
                    color: var(--lux-muted);
                    font-size: 16px;
                    line-height: 1.9;
                }

                #<?php echo esc_attr( $instance_id ); ?> .lux-secure-badge {
                    display: flex;
                    align-items: center;
                    gap: 11px;
                    min-width: 245px;
                    padding: 13px 16px;
                    border: 1px solid #d1fadf;
                    border-radius: 16px;
                    background: #ecfdf3;
                    color: var(--lux-success);
                }

                #<?php echo esc_attr( $instance_id ); ?> .lux-secure-badge > .lux-symbol {
                    display: grid;
                    place-items: center;
                    width: 38px;
                    height: 38px;
                    border-radius: 50%;
                    background: #d1fadf;
                }

                #<?php echo esc_attr( $instance_id ); ?> .lux-secure-badge span:last-child { display: grid; gap: 2px; }
                #<?php echo esc_attr( $instance_id ); ?> .lux-secure-badge strong { font-size: 13px; }
                #<?php echo esc_attr( $instance_id ); ?> .lux-secure-badge small { font-size: 11px; color: #27815c; }

                #<?php echo esc_attr( $instance_id ); ?> .lux-checkout-steps {
                    display: grid;
                    grid-template-columns: max-content minmax(30px, 1fr) max-content minmax(30px, 1fr) max-content;
                    align-items: center;
                    gap: 14px;
                    padding: 18px 22px;
                    margin-bottom: 26px;
                    border: 1px solid var(--lux-border);
                    border-radius: 20px;
                    background: var(--lux-surface);
                    box-shadow: 0 16px 42px rgba(16, 24, 40, .055);
                }

                #<?php echo esc_attr( $instance_id ); ?> .lux-step {
                    appearance: none;
                    display: flex;
                    align-items: center;
                    gap: 11px;
                    padding: 0;
                    border: 0;
                    background: transparent;
                    color: #98a2b3;
                    text-align: right;
                }

                #<?php echo esc_attr( $instance_id ); ?> button.lux-step { cursor: pointer; }

                #<?php echo esc_attr( $instance_id ); ?> .lux-step-number {
                    display: grid;
                    place-items: center;
                    width: 38px;
                    height: 38px;
                    flex: 0 0 38px;
                    border: 1px solid var(--lux-border);
                    border-radius: 12px;
                    background: var(--lux-soft);
                    font-size: 14px;
                    font-weight: 900;
                    transition: .25s ease;
                }

                #<?php echo esc_attr( $instance_id ); ?> .lux-step-copy { display: grid; gap: 2px; }
                #<?php echo esc_attr( $instance_id ); ?> .lux-step-copy strong { font-size: 13px; color: inherit; }
                #<?php echo esc_attr( $instance_id ); ?> .lux-step-copy small { font-size: 10px; color: #98a2b3; }

                #<?php echo esc_attr( $instance_id ); ?> .lux-step.is-active { color: var(--lux-ink); }
                #<?php echo esc_attr( $instance_id ); ?> .lux-step.is-active .lux-step-number {
                    border-color: var(--lux-primary);
                    background: var(--lux-primary);
                    color: #fff;
                    box-shadow: 0 8px 20px color-mix(in srgb, var(--lux-primary) 28%, transparent);
                }

                #<?php echo esc_attr( $instance_id ); ?> .lux-step.is-complete .lux-step-number {
                    border-color: var(--lux-success);
                    background: var(--lux-success);
                }

                #<?php echo esc_attr( $instance_id ); ?> .lux-step-line {
                    height: 1px;
                    background: var(--lux-border);
                }

                #<?php echo esc_attr( $instance_id ); ?> .woocommerce { width: 100%; }
                #<?php echo esc_attr( $instance_id ); ?> .woocommerce > .woocommerce-notices-wrapper:empty { display: none; }

                #<?php echo esc_attr( $instance_id ); ?> .woocommerce-error,
                #<?php echo esc_attr( $instance_id ); ?> .woocommerce-message,
                #<?php echo esc_attr( $instance_id ); ?> .woocommerce-info {
                    margin: 0 0 18px !important;
                    padding: 15px 18px !important;
                    border: 1px solid var(--lux-border) !important;
                    border-top: 0 !important;
                    border-radius: 14px !important;
                    background: #fff !important;
                    color: var(--lux-ink) !important;
                    box-shadow: none !important;
                    line-height: 1.8;
                }

                #<?php echo esc_attr( $instance_id ); ?> .woocommerce-error { border-right: 4px solid var(--lux-danger) !important; }
                #<?php echo esc_attr( $instance_id ); ?> .woocommerce-message { border-right: 4px solid var(--lux-success) !important; }
                #<?php echo esc_attr( $instance_id ); ?> .woocommerce-info { border-right: 4px solid var(--lux-primary) !important; }
                #<?php echo esc_attr( $instance_id ); ?> .woocommerce-error::before,
                #<?php echo esc_attr( $instance_id ); ?> .woocommerce-message::before,
                #<?php echo esc_attr( $instance_id ); ?> .woocommerce-info::before { display: none; }

                #<?php echo esc_attr( $instance_id ); ?> .woocommerce-form-login,
                #<?php echo esc_attr( $instance_id ); ?> .checkout_coupon {
                    margin: -4px 0 22px !important;
                    padding: 20px !important;
                    border: 1px solid var(--lux-border) !important;
                    border-radius: 16px !important;
                    background: var(--lux-soft);
                }

                #<?php echo esc_attr( $instance_id ); ?> form.checkout {
                    width: 100%;
                    margin: 0;
                }

                #<?php echo esc_attr( $instance_id ); ?> .lux-checkout-panel {
                    display: none;
                    animation: luxCheckoutIn .35s cubic-bezier(.22, 1, .36, 1) both;
                }

                #<?php echo esc_attr( $instance_id ); ?> .lux-checkout-panel.is-active { display: block; }

                #<?php echo esc_attr( $instance_id ); ?> .lux-panel-grid {
                    display: grid;
                    grid-template-columns: minmax(0, 1fr) 330px;
                    gap: 24px;
                    align-items: start;
                }

                #<?php echo esc_attr( $instance_id ); ?> .lux-panel-card,
                #<?php echo esc_attr( $instance_id ); ?> #customer_details,
                #<?php echo esc_attr( $instance_id ); ?> #order_review {
                    border: 1px solid var(--lux-border);
                    border-radius: 20px;
                    background: var(--lux-surface);
                    box-shadow: 0 18px 55px rgba(16, 24, 40, .06);
                }

                #<?php echo esc_attr( $instance_id ); ?> #customer_details {
                    float: none;
                    width: 100%;
                    margin: 0;
                    padding: 28px;
                }

                #<?php echo esc_attr( $instance_id ); ?> #customer_details::after { content: ""; display: table; clear: both; }
                #<?php echo esc_attr( $instance_id ); ?> #customer_details .col-1,
                #<?php echo esc_attr( $instance_id ); ?> #customer_details .col-2 {
                    float: none;
                    width: 100%;
                }

                #<?php echo esc_attr( $instance_id ); ?> #customer_details .col-2 { margin-top: 18px; }
                #<?php echo esc_attr( $instance_id ); ?> #ship-to-different-address { display: none; }

                #<?php echo esc_attr( $instance_id ); ?> h3,
                #<?php echo esc_attr( $instance_id ); ?> #order_review_heading {
                    margin: 0 0 20px;
                    color: var(--lux-ink);
                    font-size: 20px;
                    font-weight: 900;
                    line-height: 1.5;
                }

                #<?php echo esc_attr( $instance_id ); ?> .woocommerce-billing-fields__field-wrapper,
                #<?php echo esc_attr( $instance_id ); ?> .woocommerce-shipping-fields__field-wrapper {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 0 16px;
                }

                #<?php echo esc_attr( $instance_id ); ?> .form-row {
                    float: none !important;
                    width: 100% !important;
                    margin: 0 0 16px !important;
                    padding: 0 !important;
                }

                #<?php echo esc_attr( $instance_id ); ?> .form-row-first,
                #<?php echo esc_attr( $instance_id ); ?> .form-row-last {
                    width: calc(50% - 8px) !important;
                }

                #<?php echo esc_attr( $instance_id ); ?> label {
                    display: block;
                    margin: 0 0 7px;
                    color: #344054;
                    font-size: 13px;
                    font-weight: 750;
                    line-height: 1.7;
                }

                #<?php echo esc_attr( $instance_id ); ?> abbr.required { color: var(--lux-danger); text-decoration: none; }

                #<?php echo esc_attr( $instance_id ); ?> input.input-text,
                #<?php echo esc_attr( $instance_id ); ?> textarea,
                #<?php echo esc_attr( $instance_id ); ?> select,
                #<?php echo esc_attr( $instance_id ); ?> .select2-container .select2-selection--single {
                    width: 100%;
                    min-height: 52px;
                    padding: 0 14px;
                    border: 1px solid #d0d5dd !important;
                    border-radius: 12px !important;
                    background: #fff !important;
                    color: var(--lux-ink);
                    outline: none;
                    box-shadow: none !important;
                    font-size: 14px;
                    transition: border-color .2s ease, box-shadow .2s ease;
                }

                #<?php echo esc_attr( $instance_id ); ?> textarea { min-height: 100px; padding-top: 13px; resize: vertical; }
                #<?php echo esc_attr( $instance_id ); ?> .select2-container { width: 100% !important; }
                #<?php echo esc_attr( $instance_id ); ?> .select2-selection__rendered { padding: 11px 0 !important; line-height: 28px !important; }
                #<?php echo esc_attr( $instance_id ); ?> .select2-selection__arrow { top: 13px !important; left: 10px !important; right: auto !important; }

                #<?php echo esc_attr( $instance_id ); ?> input.input-text:focus,
                #<?php echo esc_attr( $instance_id ); ?> textarea:focus,
                #<?php echo esc_attr( $instance_id ); ?> select:focus,
                #<?php echo esc_attr( $instance_id ); ?> .select2-container--focus .select2-selection {
                    border-color: var(--lux-primary) !important;
                    box-shadow: 0 0 0 4px color-mix(in srgb, var(--lux-primary) 12%, transparent) !important;
                }

                #<?php echo esc_attr( $instance_id ); ?> .woocommerce-invalid input.input-text,
                #<?php echo esc_attr( $instance_id ); ?> .lux-field-invalid input,
                #<?php echo esc_attr( $instance_id ); ?> .lux-field-invalid select,
                #<?php echo esc_attr( $instance_id ); ?> .lux-field-invalid .select2-selection {
                    border-color: var(--lux-danger) !important;
                    box-shadow: 0 0 0 3px rgba(180, 35, 24, .1) !important;
                }

                #<?php echo esc_attr( $instance_id ); ?> .lux-form-help {
                    display: flex;
                    align-items: flex-start;
                    gap: 10px;
                    padding: 15px 16px;
                    border-radius: 14px;
                    background: color-mix(in srgb, var(--lux-primary) 6%, #fff);
                    color: #475467;
                    font-size: 12px;
                    line-height: 1.8;
                }

                #<?php echo esc_attr( $instance_id ); ?> .lux-form-help .lux-symbol { color: var(--lux-primary); }

                #<?php echo esc_attr( $instance_id ); ?> .lux-side-note {
                    position: sticky;
                    top: 26px;
                    padding: 22px;
                }

                #<?php echo esc_attr( $instance_id ); ?> .lux-side-note h3 { font-size: 16px; margin-bottom: 14px; }
                #<?php echo esc_attr( $instance_id ); ?> .lux-side-note ul { display: grid; gap: 12px; margin: 0; padding: 0; list-style: none; }
                #<?php echo esc_attr( $instance_id ); ?> .lux-side-note li { display: flex; gap: 9px; color: #475467; font-size: 12px; line-height: 1.8; }
                #<?php echo esc_attr( $instance_id ); ?> .lux-side-note .lux-symbol { color: var(--lux-success); font-size: 19px; }

                #<?php echo esc_attr( $instance_id ); ?> .lux-panel-actions {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 12px;
                    margin-top: 18px;
                }

                #<?php echo esc_attr( $instance_id ); ?> .lux-next,
                #<?php echo esc_attr( $instance_id ); ?> .lux-back,
                #<?php echo esc_attr( $instance_id ); ?> #place_order,
                #<?php echo esc_attr( $instance_id ); ?> .button {
                    appearance: none;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    gap: 8px;
                    min-height: 52px;
                    padding: 0 22px;
                    border: 1px solid transparent;
                    border-radius: 12px;
                    font-family: inherit;
                    font-size: 14px;
                    font-weight: 850;
                    text-decoration: none;
                    cursor: pointer;
                    transition: transform .2s ease, box-shadow .2s ease, background .2s ease;
                }

                #<?php echo esc_attr( $instance_id ); ?> .lux-next,
                #<?php echo esc_attr( $instance_id ); ?> #place_order,
                #<?php echo esc_attr( $instance_id ); ?> .button.alt {
                    background: var(--lux-primary) !important;
                    color: #fff !important;
                    box-shadow: 0 10px 25px color-mix(in srgb, var(--lux-primary) 23%, transparent);
                }

                #<?php echo esc_attr( $instance_id ); ?> .lux-next:hover,
                #<?php echo esc_attr( $instance_id ); ?> #place_order:hover { transform: translateY(-2px); filter: brightness(.96); }

                #<?php echo esc_attr( $instance_id ); ?> .lux-back {
                    border-color: var(--lux-border);
                    background: #fff;
                    color: #344054;
                    box-shadow: none;
                }

                #<?php echo esc_attr( $instance_id ); ?> #order_review_heading {
                    float: none;
                    width: 100%;
                    margin: 0;
                    padding: 22px 24px 0;
                    border: 1px solid var(--lux-border);
                    border-bottom: 0;
                    border-radius: 20px 20px 0 0;
                    background: #fff;
                }

                #<?php echo esc_attr( $instance_id ); ?> #order_review {
                    float: none;
                    width: 100%;
                    padding: 14px 24px 24px;
                    border-radius: 0 0 20px 20px;
                    box-shadow: 0 18px 55px rgba(16, 24, 40, .06);
                }

                #<?php echo esc_attr( $instance_id ); ?> table.shop_table {
                    width: 100%;
                    margin: 0 0 22px !important;
                    border: 0 !important;
                    border-collapse: collapse !important;
                    border-radius: 0 !important;
                    font-size: 13px;
                }

                #<?php echo esc_attr( $instance_id ); ?> table.shop_table th,
                #<?php echo esc_attr( $instance_id ); ?> table.shop_table td {
                    padding: 14px 8px !important;
                    border: 0 !important;
                    border-bottom: 1px solid var(--lux-border) !important;
                    text-align: right;
                    vertical-align: middle;
                }

                #<?php echo esc_attr( $instance_id ); ?> table.shop_table td:last-child,
                #<?php echo esc_attr( $instance_id ); ?> table.shop_table th:last-child { text-align: left; }
                #<?php echo esc_attr( $instance_id ); ?> table.shop_table .order-total th,
                #<?php echo esc_attr( $instance_id ); ?> table.shop_table .order-total td { padding-top: 19px !important; border-bottom: 0 !important; font-size: 17px; }

                #<?php echo esc_attr( $instance_id ); ?> #payment {
                    margin-top: 18px;
                    border: 1px solid var(--lux-border);
                    border-radius: 16px;
                    background: var(--lux-soft) !important;
                }

                #<?php echo esc_attr( $instance_id ); ?> #payment ul.payment_methods { padding: 10px 18px !important; border-bottom-color: var(--lux-border) !important; }
                #<?php echo esc_attr( $instance_id ); ?> #payment ul.payment_methods li { padding: 11px 0; }
                #<?php echo esc_attr( $instance_id ); ?> #payment ul.payment_methods label { display: inline; cursor: pointer; font-size: 14px; }
                #<?php echo esc_attr( $instance_id ); ?> #payment div.payment_box { border-radius: 12px; background: #fff !important; color: #475467; font-size: 12px; line-height: 1.9; }
                #<?php echo esc_attr( $instance_id ); ?> #payment div.payment_box::before { border-bottom-color: #fff !important; }
                #<?php echo esc_attr( $instance_id ); ?> #payment .place-order { padding: 18px !important; }
                #<?php echo esc_attr( $instance_id ); ?> #place_order { float: none; width: 100%; min-height: 58px; }

                #<?php echo esc_attr( $instance_id ); ?> .woocommerce-privacy-policy-text { color: var(--lux-muted); font-size: 11px; line-height: 1.9; }
                #<?php echo esc_attr( $instance_id ); ?> .woocommerce-terms-and-conditions-wrapper { margin-bottom: 14px; }

                #<?php echo esc_attr( $instance_id ); ?> .lux-checkout-trust {
                    display: flex;
                    justify-content: center;
                    flex-wrap: wrap;
                    gap: 18px 42px;
                    margin-top: 24px;
                    padding: 18px;
                    border-top: 1px solid var(--lux-border);
                    color: var(--lux-muted);
                    font-size: 12px;
                }

                #<?php echo esc_attr( $instance_id ); ?> .lux-checkout-trust span { display: flex; align-items: center; gap: 7px; }
                #<?php echo esc_attr( $instance_id ); ?> .lux-checkout-trust .lux-symbol { color: var(--lux-primary); font-size: 19px; }

                #<?php echo esc_attr( $instance_id ); ?> .woocommerce-order {
                    padding: clamp(22px, 5vw, 48px);
                    border: 1px solid var(--lux-border);
                    border-radius: 22px;
                    background: #fff;
                    box-shadow: 0 20px 60px rgba(16, 24, 40, .07);
                }

                #<?php echo esc_attr( $instance_id ); ?> .woocommerce-thankyou-order-received {
                    padding: 16px 18px;
                    border-radius: 14px;
                    background: #ecfdf3;
                    color: var(--lux-success);
                    font-weight: 800;
                }

                #<?php echo esc_attr( $instance_id ); ?> ul.order_details {
                    display: grid;
                    grid-template-columns: repeat(4, 1fr);
                    gap: 1px;
                    margin: 24px 0 !important;
                    padding: 1px !important;
                    border-radius: 14px;
                    overflow: hidden;
                    background: var(--lux-border);
                }

                #<?php echo esc_attr( $instance_id ); ?> ul.order_details li {
                    float: none;
                    width: auto;
                    margin: 0;
                    padding: 16px !important;
                    border: 0;
                    background: #fff;
                    color: var(--lux-muted);
                    font-size: 11px;
                    line-height: 1.8;
                }

                #<?php echo esc_attr( $instance_id ); ?> ul.order_details li strong { color: var(--lux-ink); font-size: 13px; }

                @keyframes luxCheckoutIn {
                    from { opacity: 0; transform: translateY(9px); }
                    to { opacity: 1; transform: translateY(0); }
                }

                @media (max-width: 820px) {
                    #<?php echo esc_attr( $instance_id ); ?> .lux-checkout-shell { width: min(100% - 22px, 680px); padding: 24px 0 38px; }
                    #<?php echo esc_attr( $instance_id ); ?> .lux-checkout-hero { align-items: flex-start; }
                    #<?php echo esc_attr( $instance_id ); ?> .lux-secure-badge { display: none; }
                    #<?php echo esc_attr( $instance_id ); ?> .lux-checkout-steps { gap: 8px; padding: 13px; }
                    #<?php echo esc_attr( $instance_id ); ?> .lux-step-copy small { display: none; }
                    #<?php echo esc_attr( $instance_id ); ?> .lux-panel-grid { grid-template-columns: 1fr; }
                    #<?php echo esc_attr( $instance_id ); ?> .lux-side-note { position: static; order: 2; }
                    #<?php echo esc_attr( $instance_id ); ?> ul.order_details { grid-template-columns: repeat(2, 1fr); }
                }

                @media (max-width: 560px) {
                    #<?php echo esc_attr( $instance_id ); ?> .lux-checkout-hero h1 { font-size: 30px; }
                    #<?php echo esc_attr( $instance_id ); ?> .lux-checkout-hero p { font-size: 13px; }
                    #<?php echo esc_attr( $instance_id ); ?> .lux-checkout-steps { grid-template-columns: 38px 1fr 38px 1fr 38px; }
                    #<?php echo esc_attr( $instance_id ); ?> .lux-step { gap: 0; }
                    #<?php echo esc_attr( $instance_id ); ?> .lux-step-copy { display: none; }
                    #<?php echo esc_attr( $instance_id ); ?> #customer_details { padding: 20px 16px; }
                    #<?php echo esc_attr( $instance_id ); ?> .form-row-first,
                    #<?php echo esc_attr( $instance_id ); ?> .form-row-last { width: 100% !important; }
                    #<?php echo esc_attr( $instance_id ); ?> #order_review_heading { padding: 19px 17px 0; }
                    #<?php echo esc_attr( $instance_id ); ?> #order_review { padding: 10px 17px 17px; }
                    #<?php echo esc_attr( $instance_id ); ?> .lux-panel-actions { flex-direction: column; }
                    #<?php echo esc_attr( $instance_id ); ?> .lux-next,
                    #<?php echo esc_attr( $instance_id ); ?> .lux-back { width: 100%; }
                    #<?php echo esc_attr( $instance_id ); ?> .lux-checkout-trust { justify-content: flex-start; }
                    #<?php echo esc_attr( $instance_id ); ?> ul.order_details { grid-template-columns: 1fr; }
                }

                @media (prefers-reduced-motion: reduce) {
                    #<?php echo esc_attr( $instance_id ); ?> *,
                    #<?php echo esc_attr( $instance_id ); ?> *::before,
                    #<?php echo esc_attr( $instance_id ); ?> *::after { scroll-behavior: auto !important; animation: none !important; transition: none !important; }
                }
            </style>

            <script>
                (function () {
                    'use strict';

                    var root = document.getElementById(<?php echo wp_json_encode( $instance_id ); ?>);
                    if (!root || root.dataset.luxReady === '1') return;
                    root.dataset.luxReady = '1';

                    function hideThemeChrome() {
                        if (root.getAttribute('data-hide-chrome') !== '1') return;
                        var selectors = [
                            'header', '#masthead', '.site-header', '.elementor-location-header',
                            'footer', '#colophon', '.site-footer', '.elementor-location-footer'
                        ];
                        selectors.forEach(function (selector) {
                            document.querySelectorAll(selector).forEach(function (node) {
                                if (!root.contains(node)) node.style.setProperty('display', 'none', 'important');
                            });
                        });
                    }

                    hideThemeChrome();

                    if (root.classList.contains('is-order-received')) {
                        root.querySelectorAll('[data-lux-step-button]').forEach(function (step, index) {
                            step.classList.toggle('is-active', index === 2);
                            if (index < 2) step.classList.add('is-complete');
                        });
                        return;
                    }

                    var form = root.querySelector('form.checkout');
                    var customer = root.querySelector('#customer_details');
                    var review = root.querySelector('#order_review');
                    var reviewHeading = root.querySelector('#order_review_heading');

                    if (!form || !customer || !review) return;

                    var panelOne = document.createElement('section');
                    panelOne.className = 'lux-checkout-panel lux-checkout-panel-one is-active';
                    panelOne.setAttribute('data-lux-panel', '1');

                    var panelOneGrid = document.createElement('div');
                    panelOneGrid.className = 'lux-panel-grid';

                    var customerColumn = document.createElement('div');
                    customerColumn.className = 'lux-customer-column';

                    var helper = document.createElement('div');
                    helper.className = 'lux-form-help';
                    helper.innerHTML = '<span class="lux-symbol" aria-hidden="true">info</span><span>ایمیل و شماره موبایل را دقیق وارد کنید؛ اطلاعات دسترسی به دوره و پیگیری سفارش از این راه در اختیار شما قرار می‌گیرد.</span>';

                    var nextActions = document.createElement('div');
                    nextActions.className = 'lux-panel-actions';
                    nextActions.innerHTML = '<span></span><button type="button" class="lux-next">بررسی سفارش و پرداخت <span class="lux-symbol" aria-hidden="true">arrow_back</span></button>';

                    var sideNote = document.createElement('aside');
                    sideNote.className = 'lux-panel-card lux-side-note';
                    sideNote.innerHTML = '<h3>پس از پرداخت چه می‌شود؟</h3><ul><li><span class="lux-symbol" aria-hidden="true">check_circle</span><span>سفارش شما بلافاصله در ووکامرس ثبت می‌شود.</span></li><li><span class="lux-symbol" aria-hidden="true">check_circle</span><span>رسید خرید برای ایمیل واردشده ارسال می‌شود.</span></li><li><span class="lux-symbol" aria-hidden="true">check_circle</span><span>دسترسی دوره مطابق تنظیمات فروشگاه فعال خواهد شد.</span></li></ul>';

                    form.insertBefore(panelOne, form.firstChild);
                    panelOne.appendChild(panelOneGrid);
                    panelOneGrid.appendChild(customerColumn);
                    customerColumn.appendChild(customer);
                    customerColumn.appendChild(helper);
                    customerColumn.appendChild(nextActions);
                    panelOneGrid.appendChild(sideNote);

                    var panelTwo = document.createElement('section');
                    panelTwo.className = 'lux-checkout-panel lux-checkout-panel-two';
                    panelTwo.setAttribute('data-lux-panel', '2');
                    panelTwo.setAttribute('aria-hidden', 'true');
                    form.appendChild(panelTwo);

                    if (reviewHeading) panelTwo.appendChild(reviewHeading);
                    panelTwo.appendChild(review);

                    var backActions = document.createElement('div');
                    backActions.className = 'lux-panel-actions';
                    backActions.innerHTML = '<button type="button" class="lux-back"><span class="lux-symbol" aria-hidden="true">arrow_forward</span> ویرایش اطلاعات</button><span></span>';
                    panelTwo.appendChild(backActions);

                    var currentStep = 1;

                    function scrollToStage() {
                        var steps = root.querySelector('.lux-checkout-steps');
                        if (!steps) return;
                        var top = steps.getBoundingClientRect().top + window.pageYOffset - 22;
                        window.scrollTo({ top: top, behavior: 'smooth' });
                    }

                    function setStep(step, shouldScroll) {
                        currentStep = step;
                        panelOne.classList.toggle('is-active', step === 1);
                        panelTwo.classList.toggle('is-active', step === 2);
                        panelOne.setAttribute('aria-hidden', step === 1 ? 'false' : 'true');
                        panelTwo.setAttribute('aria-hidden', step === 2 ? 'false' : 'true');

                        root.querySelectorAll('[data-lux-step-button]').forEach(function (button) {
                            var number = parseInt(button.getAttribute('data-lux-step-button'), 10);
                            button.classList.toggle('is-active', number === step);
                            button.classList.toggle('is-complete', number < step);
                            if (button.tagName === 'BUTTON') button.tabIndex = number <= step ? 0 : -1;
                            if (number === step) button.setAttribute('aria-current', 'step');
                            else button.removeAttribute('aria-current');
                        });

                        if (shouldScroll) scrollToStage();
                    }

                    function fieldValue(field) {
                        if (field.type === 'checkbox' || field.type === 'radio') return field.checked ? field.value : '';
                        return (field.value || '').trim();
                    }

                    function validateCustomer() {
                        var valid = true;
                        var firstInvalid = null;
                        var required = customer.querySelectorAll('[required], .validate-required input, .validate-required select, .validate-required textarea');

                        required.forEach(function (field) {
                            if (field.disabled || field.offsetParent === null) return;
                            var row = field.closest('.form-row') || field.parentElement;
                            var value = fieldValue(field);
                            var fieldValid = value !== '';

                            if (field.type === 'email' && value) {
                                fieldValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
                            }

                            if (row) row.classList.toggle('lux-field-invalid', !fieldValid);
                            field.setAttribute('aria-invalid', fieldValid ? 'false' : 'true');

                            if (!fieldValid) {
                                valid = false;
                                if (!firstInvalid) firstInvalid = field;
                            }
                        });

                        if (firstInvalid) {
                            firstInvalid.focus({ preventScroll: true });
                            firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }

                        return valid;
                    }

                    customer.addEventListener('input', function (event) {
                        var row = event.target.closest('.form-row');
                        if (row) row.classList.remove('lux-field-invalid');
                    });

                    panelOne.querySelector('.lux-next').addEventListener('click', function () {
                        if (!validateCustomer()) return;
                        setStep(2, true);

                        if (window.jQuery) {
                            window.jQuery(document.body).trigger('update_checkout');
                        }
                    });

                    panelTwo.querySelector('.lux-back').addEventListener('click', function () {
                        setStep(1, true);
                    });

                    var firstStepButton = root.querySelector('[data-lux-step-button="1"]');
                    var secondStepButton = root.querySelector('[data-lux-step-button="2"]');
                    if (firstStepButton) firstStepButton.addEventListener('click', function () { setStep(1, true); });
                    if (secondStepButton) secondStepButton.addEventListener('click', function () {
                        if (currentStep === 2 || validateCustomer()) setStep(2, true);
                    });

                    if (window.jQuery) {
                        window.jQuery(document.body).on('checkout_error', function () {
                            window.setTimeout(function () {
                                var invalidCustomer = customer.querySelector('.woocommerce-invalid, .lux-field-invalid');
                                setStep(invalidCustomer ? 1 : 2, true);
                            }, 30);
                        });
                    }
                })();
            </script>
            <?php
            return ob_get_clean();
        }
    }

    Luxury_Academy_Checkout_Shortcode::init();
}
