/**
 * Luxury Academy Student Dashboard — WooCommerce + Tutor LMS
 *
 * امکانات:
 * - افزودن «دوره‌های من» به حساب کاربری ووکامرس
 * - انتقال /my-account/ به /my-account/my-courses/ برای کاربران واردشده
 * - انتقال نتیجه پرداخت موفق به دوره‌های من
 * - نمایش دوره‌های Tutor LMS، درصد پیشرفت و دکمه ادامه یادگیری
 *
 * این کد را در Code Snippets بدون تگ آغازین PHP قرار دهید و روی Run Everywhere بگذارید.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Luxury_Academy_Student_Dashboard' ) ) {

    final class Luxury_Academy_Student_Dashboard {

        const ENDPOINT        = 'my-courses';
        const REWRITE_VERSION = '1.0.0';
        const REWRITE_OPTION  = 'lux_academy_dashboard_rewrite_version';

        private static $assets_printed = false;

        public static function init() {
            add_action( 'init', array( __CLASS__, 'register_endpoint' ) );
            add_action( 'init', array( __CLASS__, 'maybe_flush_rewrite_rules' ), 99 );

            add_filter( 'query_vars', array( __CLASS__, 'register_query_var' ) );
            add_filter( 'woocommerce_get_query_vars', array( __CLASS__, 'register_wc_query_var' ) );
            add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'account_menu_items' ), 40 );
            add_filter( 'woocommerce_get_return_url', array( __CLASS__, 'payment_return_url' ), 999, 2 );
            add_filter( 'woocommerce_registration_redirect', array( __CLASS__, 'registration_redirect' ), 999 );
            add_filter( 'body_class', array( __CLASS__, 'body_classes' ) );

            add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( __CLASS__, 'render_dashboard' ) );
            add_action( 'woocommerce_account_orders_endpoint', array( __CLASS__, 'render_orders_header' ), 5 );
            add_action( 'woocommerce_account_edit-account_endpoint', array( __CLASS__, 'render_edit_account_header' ), 5 );
            add_action( 'woocommerce_account_view-order_endpoint', array( __CLASS__, 'render_view_order_header' ), 5, 1 );
            add_action( 'woocommerce_before_customer_login_form', array( __CLASS__, 'render_login_intro' ) );
            add_action( 'woocommerce_register_form_start', array( __CLASS__, 'render_registration_note' ) );
            add_action( 'wp_head', array( __CLASS__, 'print_account_assets' ), 40 );
            add_action( 'template_redirect', array( __CLASS__, 'redirect_tutor_dashboard' ), 5 );
            add_action( 'template_redirect', array( __CLASS__, 'redirect_account_home' ), 20 );
        }

        public static function register_endpoint() {
            add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
        }

        public static function register_query_var( $vars ) {
            $vars[] = self::ENDPOINT;
            return array_unique( $vars );
        }

        public static function register_wc_query_var( $vars ) {
            $vars[ self::ENDPOINT ] = self::ENDPOINT;
            return $vars;
        }

        public static function maybe_flush_rewrite_rules() {
            if ( get_option( self::REWRITE_OPTION ) === self::REWRITE_VERSION ) {
                return;
            }

            flush_rewrite_rules( false );
            update_option( self::REWRITE_OPTION, self::REWRITE_VERSION, false );
        }

        private static function endpoint_url() {
            if ( function_exists( 'wc_get_account_endpoint_url' ) ) {
                return wc_get_account_endpoint_url( self::ENDPOINT );
            }

            return home_url( '/my-account/' . self::ENDPOINT . '/' );
        }

        public static function account_menu_items( $items ) {
            if ( ! is_user_logged_in() ) {
                return array();
            }

            $logout = isset( $items['customer-logout'] ) ? $items['customer-logout'] : 'خروج';
            $orders = isset( $items['orders'] ) ? $items['orders'] : 'سفارش‌ها';
            $edit   = isset( $items['edit-account'] ) ? $items['edit-account'] : 'مشخصات حساب';

            $new_items = array(
                'dashboard'       => 'پیشخوان',
                self::ENDPOINT    => 'دوره‌های من',
                'orders'          => $orders,
                'edit-account'    => $edit,
                'customer-logout' => $logout,
            );

            /**
             * دانلودها و آدرس‌ها برای فروش دوره در منوی اصلی نمایش داده نمی‌شوند.
             * افزونه‌های دیگر همچنان می‌توانند آیتم اختصاصی خود را نگه دارند.
             */
            foreach ( $items as $key => $label ) {
                if ( isset( $new_items[ $key ] ) || in_array( $key, array( 'downloads', 'edit-address' ), true ) ) {
                    continue;
                }

                $new_items[ $key ] = $label;
            }

            return $new_items;
        }

        public static function body_classes( $classes ) {
            if ( function_exists( 'is_account_page' ) && is_account_page() ) {
                $classes[] = 'lux-academy-account';
            }

            return $classes;
        }

        public static function redirect_tutor_dashboard() {
            if ( is_admin() || wp_doing_ajax() || ! function_exists( 'tutor_utils' ) ) {
                return;
            }

            $dashboard_page_id = (int) tutor_utils()->get_option( 'tutor_dashboard_page_id' );

            if ( ! $dashboard_page_id || ! is_page( $dashboard_page_id ) ) {
                return;
            }

            // مدیر سایت در صورت نیاز می‌تواند داشبورد اصلی Tutor را با این پارامتر باز کند.
            $allow_legacy = isset( $_GET['tutor_original'] )
                && '1' === sanitize_text_field( wp_unslash( $_GET['tutor_original'] ) )
                && current_user_can( 'manage_options' );

            if ( $allow_legacy ) {
                return;
            }

            $destination = is_user_logged_in()
                ? self::endpoint_url()
                : ( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' ) );

            wp_safe_redirect( $destination, 302 );
            exit;
        }

        public static function redirect_account_home() {
            if ( is_admin() || wp_doing_ajax() || ! is_user_logged_in() || ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
                return;
            }

            if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url() ) {
                return;
            }

            global $wp;

            if ( ! empty( $wp->query_vars ) ) {
                $known_endpoints = array(
                    'orders', 'view-order', 'downloads', 'edit-account', 'edit-address',
                    'payment-methods', 'add-payment-method', 'customer-logout', self::ENDPOINT,
                );

                foreach ( $known_endpoints as $endpoint ) {
                    if ( isset( $wp->query_vars[ $endpoint ] ) ) {
                        return;
                    }
                }
            }

            wp_safe_redirect( self::endpoint_url() );
            exit;
        }

        public static function payment_return_url( $return_url, $order ) {
            if ( ! function_exists( 'wc_get_account_endpoint_url' ) || ! $order instanceof WC_Order ) {
                return $return_url;
            }

            $user_id = (int) $order->get_user_id();

            // خرید مهمان باید صفحه استاندارد دریافت سفارش را ببیند.
            if ( $user_id <= 0 ) {
                return $return_url;
            }

            if ( in_array( $order->get_status(), array( 'failed', 'cancelled', 'refunded' ), true ) ) {
                return $return_url;
            }

            return add_query_arg(
                array(
                    'welcome'  => '1',
                    'order_id' => $order->get_id(),
                ),
                self::endpoint_url()
            );
        }

        public static function registration_redirect( $redirect ) {
            return add_query_arg( 'new_account', '1', self::endpoint_url() );
        }

        private static function tutor_is_available() {
            return function_exists( 'tutor' ) && function_exists( 'tutor_utils' );
        }

        private static function enrolled_courses( $user_id ) {
            if ( ! self::tutor_is_available() ) {
                return array();
            }

            $courses = tutor_utils()->get_enrolled_courses_by_user( $user_id, array( 'publish', 'private' ) );

            if ( $courses instanceof WP_Query ) {
                return $courses->posts;
            }

            if ( is_array( $courses ) ) {
                return $courses;
            }

            return array();
        }

        private static function course_stats( $course_id, $user_id ) {
            $fallback = array(
                'completed_percent' => 0,
                'completed_count'   => 0,
                'total_count'       => 0,
            );

            if ( ! self::tutor_is_available() ) {
                return $fallback;
            }

            $stats = tutor_utils()->get_course_completed_percent( $course_id, $user_id, true );

            if ( ! is_array( $stats ) ) {
                $fallback['completed_percent'] = max( 0, min( 100, (int) $stats ) );
                return $fallback;
            }

            return wp_parse_args( $stats, $fallback );
        }

        private static function course_continue_url( $course_id, $progress ) {
            $course_url = get_permalink( $course_id );

            if ( $progress <= 0 || ! self::tutor_is_available() ) {
                return $course_url;
            }

            $lesson_url = tutor_utils()->get_course_first_lesson( $course_id );

            if ( is_numeric( $lesson_url ) ) {
                $lesson_url = get_permalink( (int) $lesson_url );
            }

            if ( $lesson_url instanceof WP_Post ) {
                $lesson_url = get_permalink( $lesson_url->ID );
            }

            return $lesson_url ? $lesson_url : $course_url;
        }

        private static function current_order() {
            if ( empty( $_GET['order_id'] ) || ! function_exists( 'wc_get_order' ) ) {
                return false;
            }

            $order_id = absint( wp_unslash( $_GET['order_id'] ) );
            $order    = wc_get_order( $order_id );

            if ( ! $order || (int) $order->get_user_id() !== get_current_user_id() ) {
                return false;
            }

            return $order;
        }

        private static function recent_orders( $user_id ) {
            if ( ! function_exists( 'wc_get_orders' ) ) {
                return array();
            }

            return wc_get_orders(
                array(
                    'customer_id' => $user_id,
                    'limit'       => 3,
                    'orderby'     => 'date',
                    'order'       => 'DESC',
                    'status'      => array_keys( wc_get_order_statuses() ),
                )
            );
        }

        private static function greeting_name( $user ) {
            if ( $user->first_name ) {
                return $user->first_name;
            }

            return $user->display_name ? $user->display_name : $user->user_login;
        }

        public static function print_account_assets() {
            if ( self::$assets_printed || ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
                return;
            }

            self::$assets_printed = true;
            echo self::assets(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        public static function render_login_intro() {
            if ( is_user_logged_in() ) {
                return;
            }

            self::print_account_assets();

            $registration_enabled = 'yes' === get_option( 'woocommerce_enable_myaccount_registration' );
            ?>
            <section class="lux-auth-intro" dir="rtl">
                <div class="lux-auth-mark" aria-hidden="true">
                    <span class="lux-dash-icon">school</span>
                </div>
                <span class="lux-auth-eyebrow">ورود به فضای یادگیری</span>
                <h1>دوره‌هایت منتظر تو هستند</h1>
                <p>برای ادامه یادگیری، مشاهده پیشرفت دوره‌ها و پیگیری سفارش‌ها وارد حساب خودت شو.</p>
                <div class="lux-auth-benefits" aria-label="امکانات حساب کاربری">
                    <span><i class="lux-dash-icon" aria-hidden="true">play_circle</i> ادامه دوره‌ها</span>
                    <span><i class="lux-dash-icon" aria-hidden="true">monitoring</i> مشاهده پیشرفت</span>
                    <span><i class="lux-dash-icon" aria-hidden="true">receipt_long</i> پیگیری سفارش</span>
                </div>
            </section>

            <?php if ( $registration_enabled ) : ?>
                <div class="lux-auth-switch" data-lux-auth-switch dir="rtl" aria-label="انتخاب ورود یا ثبت‌نام">
                    <button type="button" class="is-active" data-lux-auth-mode="login">ورود به حساب</button>
                    <button type="button" data-lux-auth-mode="register">ساخت حساب جدید</button>
                </div>
            <?php endif; ?>
            <?php
        }

        public static function render_registration_note() {
            $password_link_enabled = 'yes' === get_option( 'woocommerce_registration_generate_password' );
            ?>
            <div class="lux-register-note">
                <span class="lux-dash-icon" aria-hidden="true"><?php echo $password_link_enabled ? 'mark_email_read' : 'person_add'; ?></span>
                <p>
                    <?php if ( $password_link_enabled ) : ?>
                        بعد از ساخت حساب، لینک تنظیم رمز عبور به ایمیل شما ارسال می‌شود.
                    <?php else : ?>
                        اطلاعات حساب را کامل کنید تا پیشخوان یادگیری شما ساخته شود.
                    <?php endif; ?>
                </p>
            </div>
            <?php
        }

        public static function render_orders_header() {
            self::print_account_assets();
            ?>
            <header class="lux-account-section-head" dir="rtl">
                <span class="lux-account-section-icon"><i class="lux-dash-icon" aria-hidden="true">receipt_long</i></span>
                <div>
                    <span>سوابق خرید</span>
                    <h1>سفارش‌های من</h1>
                    <p>وضعیت پرداخت و جزئیات خرید دوره‌ها را اینجا بررسی کن.</p>
                </div>
            </header>
            <?php
        }

        public static function render_edit_account_header() {
            self::print_account_assets();
            ?>
            <header class="lux-account-section-head" dir="rtl">
                <span class="lux-account-section-icon"><i class="lux-dash-icon" aria-hidden="true">manage_accounts</i></span>
                <div>
                    <span>تنظیمات شخصی</span>
                    <h1>مشخصات حساب</h1>
                    <p>نام، ایمیل و رمز عبور حساب کاربری خودت را مدیریت کن.</p>
                </div>
            </header>
            <?php
        }

        public static function render_view_order_header( $order_id ) {
            $order = function_exists( 'wc_get_order' ) ? wc_get_order( absint( $order_id ) ) : false;

            if ( ! $order || (int) $order->get_user_id() !== get_current_user_id() ) {
                return;
            }

            self::print_account_assets();
            ?>
            <header class="lux-account-section-head is-order-view" dir="rtl">
                <span class="lux-account-section-icon"><i class="lux-dash-icon" aria-hidden="true">description</i></span>
                <div>
                    <span>جزئیات خرید</span>
                    <h1>سفارش شماره <?php echo esc_html( $order->get_order_number() ); ?></h1>
                    <p>اقلام سفارش، مبلغ پرداخت و وضعیت فعلی را مشاهده کن.</p>
                </div>
            </header>
            <?php
        }

        public static function render_dashboard() {
            if ( ! is_user_logged_in() ) {
                echo '<p>برای مشاهده دوره‌ها وارد حساب کاربری شوید.</p>';
                return;
            }

            self::print_account_assets();

            $user_id       = get_current_user_id();
            $user          = wp_get_current_user();
            $courses       = self::enrolled_courses( $user_id );
            $recent_orders = self::recent_orders( $user_id );
            $order         = self::current_order();
            $is_welcome    = isset( $_GET['welcome'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['welcome'] ) );
            $is_new_account = isset( $_GET['new_account'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['new_account'] ) );

            $completed_count = 0;
            $active_count    = 0;
            $progress_sum    = 0;
            $course_rows     = array();

            foreach ( $courses as $course ) {
                $course_id = is_object( $course ) ? (int) $course->ID : (int) $course;

                if ( ! $course_id ) {
                    continue;
                }

                $stats    = self::course_stats( $course_id, $user_id );
                $progress = max( 0, min( 100, (int) $stats['completed_percent'] ) );

                if ( $progress >= 100 ) {
                    ++$completed_count;
                } else {
                    ++$active_count;
                }

                $progress_sum += $progress;

                $course_rows[] = array(
                    'id'        => $course_id,
                    'stats'     => $stats,
                    'progress'  => $progress,
                    'title'     => get_the_title( $course_id ),
                    'image'     => get_the_post_thumbnail_url( $course_id, 'medium_large' ),
                    'url'       => self::course_continue_url( $course_id, $progress ),
                    'course_url'=> get_permalink( $course_id ),
                );
            }

            $total_courses    = count( $course_rows );
            $average_progress = $total_courses ? (int) round( $progress_sum / $total_courses ) : 0;
            $shop_url         = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
            $edit_url         = function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'edit-account' ) : '#';
            $support_url      = 'mailto:' . antispambot( sanitize_email( get_option( 'admin_email' ) ) );

            ?>
            <div class="lux-student-dashboard" dir="rtl">
                <?php if ( $is_new_account ) : ?>
                    <div class="lux-dashboard-notice is-success">
                        <span class="lux-dash-icon" aria-hidden="true">person_check</span>
                        <div>
                            <strong>حساب کاربری شما ساخته شد</strong>
                            <p>از این پس دوره‌ها، پیشرفت یادگیری و سفارش‌های شما در همین پیشخوان قرار می‌گیرد.</p>
                        </div>
                        <a href="<?php echo esc_url( $edit_url ); ?>">تکمیل مشخصات</a>
                    </div>
                <?php endif; ?>

                <?php if ( $is_welcome && $order ) : ?>
                    <div class="lux-dashboard-notice <?php echo $order->is_paid() ? 'is-success' : 'is-pending'; ?>">
                        <span class="lux-dash-icon" aria-hidden="true"><?php echo $order->is_paid() ? 'check_circle' : 'schedule'; ?></span>
                        <div>
                            <strong><?php echo $order->is_paid() ? 'پرداخت با موفقیت انجام شد' : 'سفارش شما ثبت شد'; ?></strong>
                            <p>
                                سفارش شماره <?php echo esc_html( $order->get_order_number() ); ?> ثبت شده است.
                                <?php echo $order->is_paid() ? 'دسترسی دوره در پیشخوان شما قرار گرفت.' : 'پس از تأیید پرداخت، دسترسی دوره فعال می‌شود.'; ?>
                            </p>
                        </div>
                        <a href="<?php echo esc_url( $order->get_view_order_url() ); ?>">مشاهده سفارش</a>
                    </div>
                <?php endif; ?>

                <header class="lux-dashboard-hero">
                    <div class="lux-dashboard-profile">
                        <div class="lux-dashboard-avatar">
                            <?php echo get_avatar( $user_id, 96, '', self::greeting_name( $user ) ); ?>
                            <span aria-hidden="true"></span>
                        </div>
                        <div>
                            <span class="lux-dashboard-eyebrow">پیشخوان یادگیری</span>
                            <h1>سلام <?php echo esc_html( self::greeting_name( $user ) ); ?>، آماده‌ای ادامه بدیم؟</h1>
                            <p>همه دوره‌ها و مسیر پیشرفتت اینجا در دسترس است.</p>
                        </div>
                    </div>
                    <a class="lux-profile-link" href="<?php echo esc_url( $edit_url ); ?>">
                        <span class="lux-dash-icon" aria-hidden="true">manage_accounts</span>
                        ویرایش حساب
                    </a>
                </header>

                <section class="lux-dashboard-stats" aria-label="خلاصه یادگیری">
                    <article>
                        <span class="lux-stat-icon primary"><i class="lux-dash-icon" aria-hidden="true">auto_stories</i></span>
                        <div><strong><?php echo esc_html( $total_courses ); ?></strong><span>دوره ثبت‌نام‌شده</span></div>
                    </article>
                    <article>
                        <span class="lux-stat-icon amber"><i class="lux-dash-icon" aria-hidden="true">play_circle</i></span>
                        <div><strong><?php echo esc_html( $active_count ); ?></strong><span>در حال یادگیری</span></div>
                    </article>
                    <article>
                        <span class="lux-stat-icon green"><i class="lux-dash-icon" aria-hidden="true">workspace_premium</i></span>
                        <div><strong><?php echo esc_html( $completed_count ); ?></strong><span>دوره تکمیل‌شده</span></div>
                    </article>
                    <article>
                        <span class="lux-stat-icon violet"><i class="lux-dash-icon" aria-hidden="true">monitoring</i></span>
                        <div><strong><?php echo esc_html( $average_progress ); ?>٪</strong><span>میانگین پیشرفت</span></div>
                    </article>
                </section>

                <div class="lux-dashboard-layout">
                    <section class="lux-courses-section">
                        <div class="lux-section-heading">
                            <div>
                                <span>مسیرهای فعال شما</span>
                                <h2>دوره‌های من</h2>
                            </div>
                            <?php if ( $total_courses > 1 ) : ?>
                                <label class="lux-course-search">
                                    <span class="lux-dash-icon" aria-hidden="true">search</span>
                                    <input type="search" placeholder="جست‌وجوی دوره" data-lux-course-search autocomplete="off">
                                </label>
                            <?php endif; ?>
                        </div>

                        <?php if ( ! self::tutor_is_available() ) : ?>
                            <div class="lux-empty-courses is-error">
                                <span class="lux-dash-icon" aria-hidden="true">extension_off</span>
                                <h3>Tutor LMS در دسترس نیست</h3>
                                <p>برای نمایش دوره‌ها، افزونه Tutor LMS را فعال کنید.</p>
                            </div>
                        <?php elseif ( empty( $course_rows ) ) : ?>
                            <div class="lux-empty-courses">
                                <span class="lux-dash-icon" aria-hidden="true">school</span>
                                <h3><?php echo $is_welcome ? 'دسترسی دوره در حال فعال‌سازی است' : 'هنوز دوره‌ای اینجا نیست'; ?></h3>
                                <p><?php echo $is_welcome ? 'اگر پرداخت را همین حالا انجام داده‌اید، چند لحظه دیگر صفحه را تازه کنید.' : 'با ثبت‌نام در اولین دوره، مسیر یادگیری شما اینجا نمایش داده می‌شود.'; ?></p>
                                <a href="<?php echo esc_url( $shop_url ); ?>">مشاهده دوره‌ها</a>
                            </div>
                        <?php else : ?>
                            <div class="lux-course-grid" data-lux-course-grid>
                                <?php foreach ( $course_rows as $index => $row ) : ?>
                                    <article
                                        class="lux-course-card"
                                        data-lux-course-card
                                        data-course-title="<?php echo esc_attr( wp_strip_all_tags( $row['title'] ) ); ?>"
                                        style="--lux-delay: <?php echo esc_attr( min( $index * 60, 360 ) ); ?>ms"
                                    >
                                        <a class="lux-course-media" href="<?php echo esc_url( $row['course_url'] ); ?>" aria-label="مشاهده <?php echo esc_attr( $row['title'] ); ?>">
                                            <?php if ( $row['image'] ) : ?>
                                                <img src="<?php echo esc_url( $row['image'] ); ?>" alt="<?php echo esc_attr( $row['title'] ); ?>" loading="lazy">
                                            <?php else : ?>
                                                <span class="lux-course-placeholder"><i class="lux-dash-icon" aria-hidden="true">menu_book</i></span>
                                            <?php endif; ?>

                                            <span class="lux-course-status <?php echo $row['progress'] >= 100 ? 'is-completed' : ''; ?>">
                                                <?php echo $row['progress'] >= 100 ? 'تکمیل‌شده' : 'در حال یادگیری'; ?>
                                            </span>
                                        </a>

                                        <div class="lux-course-body">
                                            <h3><a href="<?php echo esc_url( $row['course_url'] ); ?>"><?php echo esc_html( $row['title'] ); ?></a></h3>

                                            <div class="lux-progress-head">
                                                <span>پیشرفت دوره</span>
                                                <strong><?php echo esc_html( $row['progress'] ); ?>٪</strong>
                                            </div>
                                            <div class="lux-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( $row['progress'] ); ?>">
                                                <span style="width: <?php echo esc_attr( $row['progress'] ); ?>%"></span>
                                            </div>

                                            <div class="lux-course-meta">
                                                <span><i class="lux-dash-icon" aria-hidden="true">task_alt</i><?php echo esc_html( (int) $row['stats']['completed_count'] ); ?> از <?php echo esc_html( (int) $row['stats']['total_count'] ); ?> بخش</span>
                                            </div>

                                            <a class="lux-course-action" href="<?php echo esc_url( $row['url'] ); ?>">
                                                <?php echo $row['progress'] > 0 ? 'ادامه یادگیری' : 'شروع یادگیری'; ?>
                                                <i class="lux-dash-icon" aria-hidden="true">arrow_back</i>
                                            </a>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>

                            <div class="lux-no-search-result" data-lux-no-result hidden>
                                دوره‌ای با این عنوان پیدا نشد.
                            </div>
                        <?php endif; ?>
                    </section>

                    <aside class="lux-dashboard-sidebar">
                        <section class="lux-side-card lux-help-card">
                            <span class="lux-dash-icon" aria-hidden="true">support_agent</span>
                            <h2>برای یادگیری کمک می‌خواهی؟</h2>
                            <p>اگر درباره دسترسی دوره یا سفارش سؤالی داری، پشتیبانی همراه توست.</p>
                            <a href="<?php echo esc_url( $support_url ); ?>">ارتباط با پشتیبانی</a>
                        </section>

                        <section class="lux-side-card lux-orders-card">
                            <div class="lux-side-title">
                                <h2>سفارش‌های اخیر</h2>
                                <?php if ( function_exists( 'wc_get_account_endpoint_url' ) ) : ?>
                                    <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>">همه</a>
                                <?php endif; ?>
                            </div>

                            <?php if ( empty( $recent_orders ) ) : ?>
                                <p class="lux-orders-empty">سفارشی ثبت نشده است.</p>
                            <?php else : ?>
                                <div class="lux-order-list">
                                    <?php foreach ( $recent_orders as $recent_order ) : ?>
                                        <a href="<?php echo esc_url( $recent_order->get_view_order_url() ); ?>">
                                            <span>
                                                <strong>#<?php echo esc_html( $recent_order->get_order_number() ); ?></strong>
                                                <small><?php echo esc_html( wc_format_datetime( $recent_order->get_date_created(), 'Y/m/d' ) ); ?></small>
                                            </span>
                                            <span>
                                                <strong><?php echo wp_kses_post( $recent_order->get_formatted_order_total() ); ?></strong>
                                                <small><?php echo esc_html( wc_get_order_status_name( $recent_order->get_status() ) ); ?></small>
                                            </span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </section>
                    </aside>
                </div>
            </div>
            <?php
        }

        private static function assets() {
            ob_start();
            ?>
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@300..600&display=swap" rel="stylesheet">
            <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css">

            <style>
                .lux-academy-account .woocommerce {
                    --lux-primary: var(--e-global-color-primary, var(--wp--preset--color--primary, #3525cd));
                    --lux-secondary: var(--e-global-color-secondary, var(--wp--preset--color--secondary, #6b38d4));
                    --lux-ink: var(--e-global-color-text, var(--wp--preset--color--contrast, #101828));
                    --lux-muted: #667085;
                    --lux-line: #e4e7ec;
                    --lux-soft: #f7f7fb;
                    --lux-white: #fff;
                    width: min(1320px, calc(100% - 32px));
                    margin: 34px auto 58px;
                    direction: rtl;
                    font-family: "Vazirmatn", Tahoma, sans-serif;
                }

                .lux-academy-account .page-header,
                .lux-academy-account main > .entry-header { display: none; }

                .lux-academy-account .woocommerce::after { content: ""; display: table; clear: both; }

                .lux-academy-account .woocommerce-MyAccount-navigation {
                    float: right;
                    width: 220px;
                    padding: 14px;
                    border: 1px solid var(--lux-line);
                    border-radius: 20px;
                    background: #fff;
                    box-shadow: 0 16px 46px rgba(16, 24, 40, .055);
                    position: sticky;
                    top: 24px;
                }

                .lux-academy-account .woocommerce-MyAccount-navigation ul { display: grid; gap: 5px; margin: 0; padding: 0; list-style: none; }
                .lux-academy-account .woocommerce-MyAccount-navigation li { margin: 0; padding: 0; border: 0; }
                .lux-academy-account .woocommerce-MyAccount-navigation a {
                    display: flex;
                    align-items: center;
                    min-height: 46px;
                    padding: 0 14px;
                    border-radius: 11px;
                    color: #475467;
                    font-size: 13px;
                    font-weight: 750;
                    text-decoration: none;
                    transition: background .2s ease, color .2s ease, transform .2s ease;
                }

                .lux-academy-account .woocommerce-MyAccount-navigation a:hover { background: var(--lux-soft); color: var(--lux-primary); transform: translateX(-2px); }
                .lux-academy-account .woocommerce-MyAccount-navigation li.is-active a { background: var(--lux-primary); color: #fff; }

                .lux-academy-account .woocommerce-MyAccount-content {
                    float: left;
                    width: calc(100% - 248px);
                    min-height: 500px;
                }

                .lux-student-dashboard,
                .lux-student-dashboard * { box-sizing: border-box; }

                .lux-student-dashboard { color: var(--lux-ink); }
                .lux-student-dashboard button,
                .lux-student-dashboard input { font-family: inherit; }

                .lux-dash-icon {
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

                .lux-dashboard-notice {
                    display: grid;
                    grid-template-columns: 42px 1fr auto;
                    align-items: center;
                    gap: 14px;
                    margin-bottom: 18px;
                    padding: 15px 17px;
                    border: 1px solid #fedf89;
                    border-radius: 16px;
                    background: #fffaeb;
                    color: #93370d;
                }

                .lux-dashboard-notice.is-success { border-color: #abefc6; background: #ecfdf3; color: #067647; }
                .lux-dashboard-notice > .lux-dash-icon { display: grid; place-items: center; width: 42px; height: 42px; border-radius: 12px; background: rgba(255,255,255,.7); font-size: 25px; }
                .lux-dashboard-notice strong { display: block; margin-bottom: 2px; font-size: 14px; }
                .lux-dashboard-notice p { margin: 0; color: inherit; font-size: 12px; line-height: 1.8; }
                .lux-dashboard-notice a { color: inherit; font-size: 12px; font-weight: 850; text-underline-offset: 4px; }

                .lux-dashboard-hero {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 24px;
                    padding: 27px 30px;
                    border: 1px solid color-mix(in srgb, var(--lux-primary) 18%, #fff);
                    border-radius: 22px;
                    background:
                        radial-gradient(circle at 8% 10%, color-mix(in srgb, var(--lux-secondary) 14%, transparent), transparent 30%),
                        color-mix(in srgb, var(--lux-primary) 5%, #fff);
                    overflow: hidden;
                    position: relative;
                }

                .lux-dashboard-hero::after {
                    content: "";
                    width: 155px;
                    height: 155px;
                    border: 28px solid color-mix(in srgb, var(--lux-primary) 7%, transparent);
                    border-radius: 50%;
                    position: absolute;
                    left: -48px;
                    bottom: -80px;
                    pointer-events: none;
                }

                .lux-dashboard-profile { display: flex; align-items: center; gap: 17px; min-width: 0; position: relative; z-index: 1; }
                .lux-dashboard-avatar { width: 70px; height: 70px; flex: 0 0 70px; position: relative; }
                .lux-dashboard-avatar img { width: 70px; height: 70px; border: 3px solid #fff; border-radius: 20px; object-fit: cover; box-shadow: 0 8px 24px rgba(16,24,40,.12); }
                .lux-dashboard-avatar > span { width: 14px; height: 14px; border: 3px solid #fff; border-radius: 50%; background: #12b76a; position: absolute; left: -3px; bottom: 2px; }
                .lux-dashboard-eyebrow { display: block; margin-bottom: 4px; color: var(--lux-primary); font-size: 11px; font-weight: 850; }
                .lux-dashboard-hero h1 { margin: 0 0 5px; color: var(--lux-ink); font-size: clamp(21px, 2.5vw, 30px); font-weight: 950; line-height: 1.45; letter-spacing: -.025em; }
                .lux-dashboard-hero p { margin: 0; color: var(--lux-muted); font-size: 13px; }

                .lux-profile-link {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    gap: 8px;
                    min-height: 46px;
                    padding: 0 16px;
                    border: 1px solid color-mix(in srgb, var(--lux-primary) 22%, #fff);
                    border-radius: 12px;
                    background: rgba(255,255,255,.82);
                    color: var(--lux-primary);
                    font-size: 12px;
                    font-weight: 850;
                    text-decoration: none;
                    position: relative;
                    z-index: 1;
                }

                .lux-dashboard-stats {
                    display: grid;
                    grid-template-columns: repeat(4, 1fr);
                    gap: 12px;
                    margin: 16px 0 28px;
                }

                .lux-dashboard-stats article {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    min-width: 0;
                    padding: 16px;
                    border: 1px solid var(--lux-line);
                    border-radius: 16px;
                    background: #fff;
                }

                .lux-stat-icon { display: grid; place-items: center; width: 42px; height: 42px; flex: 0 0 42px; border-radius: 12px; }
                .lux-stat-icon.primary { background: #eef4ff; color: #3538cd; }
                .lux-stat-icon.amber { background: #fffaeb; color: #dc6803; }
                .lux-stat-icon.green { background: #ecfdf3; color: #079455; }
                .lux-stat-icon.violet { background: #f4f3ff; color: #6938ef; }
                .lux-dashboard-stats article > div { display: grid; min-width: 0; }
                .lux-dashboard-stats strong { color: var(--lux-ink); font-size: 20px; font-weight: 950; line-height: 1.25; }
                .lux-dashboard-stats article > div span { overflow: hidden; color: var(--lux-muted); font-size: 10px; text-overflow: ellipsis; white-space: nowrap; }

                .lux-dashboard-layout { display: grid; grid-template-columns: minmax(0, 1fr) 280px; gap: 22px; align-items: start; }
                .lux-section-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; margin-bottom: 16px; }
                .lux-section-heading > div > span { display: block; margin-bottom: 3px; color: var(--lux-primary); font-size: 10px; font-weight: 850; }
                .lux-section-heading h2 { margin: 0; color: var(--lux-ink); font-size: 22px; font-weight: 950; }

                .lux-course-search { display: flex; align-items: center; width: min(230px, 100%); min-height: 43px; margin: 0; padding: 0 12px; border: 1px solid var(--lux-line); border-radius: 11px; background: #fff; color: #98a2b3; }
                .lux-course-search .lux-dash-icon { font-size: 19px; }
                .lux-course-search input { width: 100%; height: 41px; padding: 0 8px; border: 0 !important; outline: 0; background: transparent; color: var(--lux-ink); box-shadow: none !important; font-size: 12px; }

                .lux-course-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
                .lux-course-card { overflow: hidden; border: 1px solid var(--lux-line); border-radius: 18px; background: #fff; box-shadow: 0 12px 36px rgba(16,24,40,.045); animation: luxCardIn .45s cubic-bezier(.22,1,.36,1) both; animation-delay: var(--lux-delay); transition: transform .25s ease, box-shadow .25s ease; }
                .lux-course-card:hover { transform: translateY(-4px); box-shadow: 0 19px 46px rgba(16,24,40,.09); }
                .lux-course-card[hidden] { display: none; }

                .lux-course-media { display: block; height: 175px; overflow: hidden; background: var(--lux-soft); position: relative; }
                .lux-course-media img { width: 100%; height: 100%; object-fit: cover; transition: transform .5s cubic-bezier(.22,1,.36,1); }
                .lux-course-card:hover .lux-course-media img { transform: scale(1.035); }
                .lux-course-placeholder { display: grid; place-items: center; width: 100%; height: 100%; color: color-mix(in srgb, var(--lux-primary) 60%, #fff); background: linear-gradient(145deg, color-mix(in srgb, var(--lux-primary) 12%, #fff), #f7f7fb); }
                .lux-course-placeholder .lux-dash-icon { font-size: 48px; }

                .lux-course-status { position: absolute; top: 12px; right: 12px; padding: 6px 9px; border: 1px solid rgba(255,255,255,.6); border-radius: 999px; background: rgba(16,24,40,.76); backdrop-filter: blur(8px); color: #fff; font-size: 9px; font-weight: 850; }
                .lux-course-status.is-completed { background: rgba(6,118,71,.86); }

                .lux-course-body { padding: 17px; }
                .lux-course-body h3 { min-height: 50px; margin: 0 0 14px; font-size: 15px; font-weight: 900; line-height: 1.75; }
                .lux-course-body h3 a { color: var(--lux-ink); text-decoration: none; }
                .lux-progress-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 7px; color: var(--lux-muted); font-size: 10px; }
                .lux-progress-head strong { color: var(--lux-primary); font-size: 11px; }
                .lux-progress-track { height: 7px; overflow: hidden; border-radius: 999px; background: #eaecf0; }
                .lux-progress-track span { display: block; height: 100%; border-radius: inherit; background: var(--lux-primary); }
                .lux-course-meta { display: flex; align-items: center; margin: 11px 0 15px; color: var(--lux-muted); font-size: 10px; }
                .lux-course-meta span { display: flex; align-items: center; gap: 5px; }
                .lux-course-meta .lux-dash-icon { color: #98a2b3; font-size: 16px; }

                .lux-course-action { display: flex; align-items: center; justify-content: center; gap: 7px; width: 100%; min-height: 45px; border-radius: 11px; background: var(--lux-primary); color: #fff; font-size: 12px; font-weight: 900; text-decoration: none; box-shadow: 0 9px 22px color-mix(in srgb, var(--lux-primary) 20%, transparent); transition: filter .2s ease, transform .2s ease; }
                .lux-course-action:hover { color: #fff; filter: brightness(.95); transform: translateY(-1px); }
                .lux-course-action .lux-dash-icon { font-size: 18px; }

                .lux-dashboard-sidebar { display: grid; gap: 16px; position: sticky; top: 24px; }
                .lux-side-card { padding: 19px; border: 1px solid var(--lux-line); border-radius: 17px; background: #fff; }
                .lux-help-card { border-color: color-mix(in srgb, var(--lux-primary) 18%, #fff); background: color-mix(in srgb, var(--lux-primary) 5%, #fff); }
                .lux-help-card > .lux-dash-icon { display: grid; place-items: center; width: 42px; height: 42px; margin-bottom: 14px; border-radius: 13px; background: var(--lux-primary); color: #fff; }
                .lux-help-card h2, .lux-side-title h2 { margin: 0; color: var(--lux-ink); font-size: 15px; font-weight: 900; line-height: 1.7; }
                .lux-help-card p { margin: 7px 0 15px; color: var(--lux-muted); font-size: 11px; line-height: 1.9; }
                .lux-help-card a { display: inline-flex; align-items: center; color: var(--lux-primary); font-size: 11px; font-weight: 900; text-underline-offset: 4px; }
                .lux-side-title { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 11px; }
                .lux-side-title a { color: var(--lux-primary); font-size: 10px; font-weight: 850; }
                .lux-order-list { display: grid; }
                .lux-order-list > a { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 11px 0; border-bottom: 1px solid var(--lux-line); color: var(--lux-ink); text-decoration: none; }
                .lux-order-list > a:last-child { border-bottom: 0; padding-bottom: 0; }
                .lux-order-list > a > span { display: grid; gap: 2px; }
                .lux-order-list > a > span:last-child { text-align: left; }
                .lux-order-list strong { font-size: 10px; font-weight: 850; }
                .lux-order-list small { color: var(--lux-muted); font-size: 9px; }
                .lux-orders-empty { margin: 0; color: var(--lux-muted); font-size: 11px; }

                .lux-empty-courses { display: grid; justify-items: center; padding: 48px 24px; border: 1px dashed #d0d5dd; border-radius: 18px; background: #fafafa; text-align: center; }
                .lux-empty-courses > .lux-dash-icon { display: grid; place-items: center; width: 58px; height: 58px; margin-bottom: 14px; border-radius: 18px; background: color-mix(in srgb, var(--lux-primary) 9%, #fff); color: var(--lux-primary); font-size: 30px; }
                .lux-empty-courses h3 { margin: 0 0 6px; color: var(--lux-ink); font-size: 17px; }
                .lux-empty-courses p { max-width: 430px; margin: 0 0 17px; color: var(--lux-muted); font-size: 12px; line-height: 1.9; }
                .lux-empty-courses a { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 0 17px; border-radius: 11px; background: var(--lux-primary); color: #fff; font-size: 11px; font-weight: 900; text-decoration: none; }
                .lux-empty-courses.is-error > .lux-dash-icon { background: #fef3f2; color: #b42318; }
                .lux-no-search-result { margin-top: 14px; padding: 14px; border-radius: 12px; background: var(--lux-soft); color: var(--lux-muted); font-size: 12px; text-align: center; }

                .lux-academy-account .woocommerce-MyAccount-content > .woocommerce-notices-wrapper:empty { display: none; }
                .lux-academy-account .woocommerce-error,
                .lux-academy-account .woocommerce-message,
                .lux-academy-account .woocommerce-info { border-radius: 13px; font-family: "Vazirmatn", Tahoma, sans-serif; }

                /* Login and registration */
                .lux-academy-account:not(.logged-in) .woocommerce {
                    width: min(1060px, calc(100% - 28px));
                    margin-top: 42px;
                }

                .lux-academy-account:not(.logged-in) .woocommerce-MyAccount-navigation { display: none !important; }

                .lux-academy-account:not(.logged-in) .woocommerce > h2,
                .lux-academy-account:not(.logged-in) .woocommerce > .woocommerce-form-login,
                .lux-academy-account:not(.logged-in) .woocommerce > .woocommerce-form-register {
                    width: min(480px, 100%);
                    margin-right: auto;
                    margin-left: auto;
                }

                .lux-auth-intro {
                    max-width: 720px;
                    margin: 0 auto 24px;
                    text-align: center;
                }

                .lux-auth-mark {
                    display: grid;
                    place-items: center;
                    width: 58px;
                    height: 58px;
                    margin: 0 auto 13px;
                    border: 1px solid color-mix(in srgb, var(--lux-primary) 20%, #fff);
                    border-radius: 18px;
                    background: color-mix(in srgb, var(--lux-primary) 8%, #fff);
                    color: var(--lux-primary);
                    transform: rotate(-4deg);
                }

                .lux-auth-mark .lux-dash-icon { font-size: 31px; transform: rotate(4deg); }
                .lux-auth-eyebrow { display: block; margin-bottom: 5px; color: var(--lux-primary); font-size: 11px; font-weight: 900; }
                .lux-auth-intro h1 { margin: 0 0 7px; color: var(--lux-ink); font-size: clamp(27px, 4vw, 40px); font-weight: 950; line-height: 1.4; letter-spacing: -.035em; }
                .lux-auth-intro > p { max-width: 600px; margin: 0 auto; color: var(--lux-muted); font-size: 13px; line-height: 1.9; }

                .lux-auth-benefits {
                    display: flex;
                    justify-content: center;
                    flex-wrap: wrap;
                    gap: 9px;
                    margin-top: 15px;
                }

                .lux-auth-benefits > span { display: flex; align-items: center; gap: 5px; padding: 7px 10px; border: 1px solid var(--lux-line); border-radius: 999px; background: #fff; color: #475467; font-size: 10px; font-weight: 750; }
                .lux-auth-benefits .lux-dash-icon { color: var(--lux-primary); font-size: 16px; }

                .lux-auth-switch {
                    display: grid;
                    grid-template-columns: repeat(2, 1fr);
                    gap: 5px;
                    width: min(410px, 100%);
                    margin: 0 auto 15px;
                    padding: 5px;
                    border: 1px solid var(--lux-line);
                    border-radius: 13px;
                    background: var(--lux-soft);
                }

                .lux-auth-switch button {
                    min-height: 42px;
                    padding: 0 12px;
                    border: 0;
                    border-radius: 9px;
                    background: transparent;
                    color: var(--lux-muted);
                    font-family: inherit;
                    font-size: 11px;
                    font-weight: 850;
                    cursor: pointer;
                    transition: background .2s ease, color .2s ease, box-shadow .2s ease;
                }

                .lux-auth-switch button.is-active { background: #fff; color: var(--lux-primary); box-shadow: 0 5px 15px rgba(16,24,40,.08); }
                .lux-academy-account:not(.logged-in) .woocommerce .lux-auth-switch ~ .u-columns.col2-set { grid-template-columns: minmax(0, 480px); justify-content: center; }
                .lux-auth-switch ~ .u-columns.col2-set .u-column2 { display: none; }
                .lux-academy-account .woocommerce[data-lux-auth-mode="register"] .lux-auth-switch ~ .u-columns.col2-set .u-column1 { display: none; }
                .lux-academy-account .woocommerce[data-lux-auth-mode="register"] .lux-auth-switch ~ .u-columns.col2-set .u-column2 { display: block; }

                .lux-academy-account:not(.logged-in) .u-columns.col2-set {
                    display: grid;
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                    gap: 18px;
                    max-width: 920px;
                    margin: 0 auto;
                }

                .lux-register-note {
                    display: flex;
                    align-items: flex-start;
                    gap: 9px;
                    margin: 0 0 16px;
                    padding: 11px 12px;
                    border: 1px solid color-mix(in srgb, var(--lux-primary) 15%, #fff);
                    border-radius: 11px;
                    background: color-mix(in srgb, var(--lux-primary) 5%, #fff);
                    color: #475467;
                }

                .lux-register-note .lux-dash-icon { flex: 0 0 auto; color: var(--lux-primary); font-size: 19px; }
                .lux-register-note p { margin: 0; color: inherit; font-size: 10px; line-height: 1.85; }

                .lux-academy-account:not(.logged-in) .u-column1,
                .lux-academy-account:not(.logged-in) .u-column2 { float: none; width: 100%; }

                .lux-academy-account:not(.logged-in) .woocommerce h2 {
                    margin: 0 0 10px;
                    color: var(--lux-ink);
                    font-size: 18px;
                    font-weight: 950;
                    text-align: center;
                }

                .lux-academy-account .woocommerce-form-login,
                .lux-academy-account .woocommerce-form-register {
                    margin: 0 !important;
                    padding: 25px !important;
                    border: 1px solid var(--lux-line) !important;
                    border-radius: 19px !important;
                    background: #fff;
                    box-shadow: 0 18px 55px rgba(16,24,40,.065);
                }

                .lux-academy-account .woocommerce-form-row,
                .lux-academy-account .woocommerce-form-login .form-row,
                .lux-academy-account .woocommerce-form-register .form-row,
                .lux-academy-account .woocommerce-EditAccountForm .form-row {
                    float: none;
                    width: 100%;
                    margin: 0 0 15px;
                    padding: 0;
                }

                .lux-academy-account .woocommerce-form label,
                .lux-academy-account .woocommerce-EditAccountForm label {
                    display: block;
                    margin-bottom: 7px;
                    color: #344054;
                    font-size: 12px;
                    font-weight: 800;
                    line-height: 1.7;
                }

                .lux-academy-account .woocommerce-form input.input-text,
                .lux-academy-account .woocommerce-EditAccountForm input.input-text {
                    width: 100%;
                    min-height: 50px;
                    padding: 0 13px;
                    border: 1px solid #d0d5dd;
                    border-radius: 11px;
                    outline: 0;
                    background: #fff;
                    color: var(--lux-ink);
                    box-shadow: none;
                    font-family: inherit;
                    font-size: 13px;
                    transition: border-color .2s ease, box-shadow .2s ease;
                }

                .lux-academy-account .woocommerce-form input.input-text:focus,
                .lux-academy-account .woocommerce-EditAccountForm input.input-text:focus {
                    border-color: var(--lux-primary);
                    box-shadow: 0 0 0 4px color-mix(in srgb, var(--lux-primary) 11%, transparent);
                }

                .lux-academy-account .password-input { width: 100%; }
                .lux-academy-account .show-password-input { top: 15px !important; left: 13px !important; right: auto !important; color: var(--lux-muted); }
                .lux-academy-account .woocommerce-form__label-for-checkbox { display: inline-flex; align-items: center; gap: 7px; margin: 0; cursor: pointer; }
                .lux-academy-account .woocommerce-form__label-for-checkbox input { accent-color: var(--lux-primary); }

                .lux-academy-account .woocommerce-button,
                .lux-academy-account .woocommerce-Button,
                .lux-academy-account button.button,
                .lux-academy-account a.button {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 45px;
                    padding: 0 17px !important;
                    border: 0 !important;
                    border-radius: 11px !important;
                    background: var(--lux-primary) !important;
                    color: #fff !important;
                    box-shadow: 0 9px 22px color-mix(in srgb, var(--lux-primary) 18%, transparent);
                    font-family: inherit;
                    font-size: 11px !important;
                    font-weight: 900 !important;
                    line-height: 1.4;
                    text-decoration: none;
                    transition: transform .2s ease, filter .2s ease;
                }

                .lux-academy-account .woocommerce-button:hover,
                .lux-academy-account button.button:hover,
                .lux-academy-account a.button:hover { color: #fff !important; filter: brightness(.95); transform: translateY(-1px); }
                .lux-academy-account .woocommerce-form-login__submit { min-width: 112px; margin-left: 12px !important; }
                .lux-academy-account .lost_password { margin: 14px 0 0; padding-top: 14px; border-top: 1px solid var(--lux-line); font-size: 11px; }
                .lux-academy-account .lost_password a { color: var(--lux-primary); font-weight: 850; text-underline-offset: 4px; }
                .lux-academy-account .woocommerce-privacy-policy-text { color: var(--lux-muted); font-size: 10px; line-height: 1.9; }

                /* Shared account sections */
                .lux-account-section-head {
                    display: flex;
                    align-items: center;
                    gap: 15px;
                    margin-bottom: 17px;
                    padding: 22px 24px;
                    border: 1px solid color-mix(in srgb, var(--lux-primary) 17%, #fff);
                    border-radius: 18px;
                    background: color-mix(in srgb, var(--lux-primary) 5%, #fff);
                }

                .lux-account-section-icon { display: grid; place-items: center; width: 48px; height: 48px; flex: 0 0 48px; border-radius: 14px; background: var(--lux-primary); color: #fff; box-shadow: 0 10px 24px color-mix(in srgb, var(--lux-primary) 22%, transparent); }
                .lux-account-section-icon .lux-dash-icon { font-size: 25px; }
                .lux-account-section-head > div > span { display: block; margin-bottom: 2px; color: var(--lux-primary); font-size: 9px; font-weight: 900; }
                .lux-account-section-head h1 { margin: 0 0 3px; color: var(--lux-ink); font-size: 23px; font-weight: 950; line-height: 1.5; }
                .lux-account-section-head p { margin: 0; color: var(--lux-muted); font-size: 11px; line-height: 1.8; }

                /* Orders list and order details */
                .lux-academy-account table.woocommerce-orders-table,
                .lux-academy-account .woocommerce-order-details table,
                .lux-academy-account table.shop_table {
                    width: 100%;
                    margin: 0 0 18px;
                    border: 1px solid var(--lux-line);
                    border-collapse: separate;
                    border-spacing: 0;
                    border-radius: 17px;
                    background: #fff;
                    box-shadow: 0 14px 42px rgba(16,24,40,.05);
                    overflow: hidden;
                }

                .lux-academy-account table.woocommerce-orders-table th,
                .lux-academy-account .woocommerce-order-details table th,
                .lux-academy-account table.shop_table th {
                    padding: 13px 14px;
                    border: 0;
                    border-bottom: 1px solid var(--lux-line);
                    background: var(--lux-soft);
                    color: #475467;
                    font-size: 10px;
                    font-weight: 900;
                    text-align: right;
                    white-space: nowrap;
                }

                .lux-academy-account table.woocommerce-orders-table td,
                .lux-academy-account .woocommerce-order-details table td,
                .lux-academy-account table.shop_table td {
                    padding: 14px;
                    border: 0;
                    border-bottom: 1px solid var(--lux-line);
                    color: #344054;
                    font-size: 11px;
                    line-height: 1.8;
                    text-align: right;
                    vertical-align: middle;
                }

                .lux-academy-account table tr:last-child td { border-bottom: 0; }
                .lux-academy-account table .woocommerce-orders-table__cell-order-number a { color: var(--lux-primary); font-weight: 900; text-decoration: none; }
                .lux-academy-account table .woocommerce-orders-table__cell-order-actions { text-align: left; }
                .lux-academy-account table .woocommerce-orders-table__cell-order-actions .button { min-height: 36px; margin: 2px; padding: 0 11px !important; font-size: 9px !important; }
                .lux-academy-account .woocommerce-pagination { margin-top: 14px; }
                .lux-academy-account .woocommerce-pagination .button { min-width: 90px; }

                .lux-academy-account .woocommerce-info {
                    padding: 17px 18px !important;
                    border: 1px solid var(--lux-line) !important;
                    border-right: 4px solid var(--lux-primary) !important;
                    border-top: 1px solid var(--lux-line) !important;
                    background: #fff !important;
                    color: var(--lux-ink) !important;
                    box-shadow: 0 12px 35px rgba(16,24,40,.05);
                    font-size: 12px;
                    line-height: 1.8;
                }

                .lux-academy-account .woocommerce-info::before { display: none; }
                .lux-academy-account .woocommerce-info .button { float: left; margin-right: 12px; }
                .lux-academy-account .woocommerce-order-details,
                .lux-academy-account .woocommerce-customer-details { margin-top: 22px; }
                .lux-academy-account .woocommerce-order-details__title,
                .lux-academy-account .woocommerce-column__title { margin: 0 0 12px; color: var(--lux-ink); font-size: 17px; font-weight: 950; }
                .lux-academy-account .woocommerce-customer-details address { padding: 18px !important; border: 1px solid var(--lux-line) !important; border-radius: 15px !important; background: #fff; color: #475467; font-size: 12px; line-height: 2; }
                .lux-academy-account .woocommerce-MyAccount-content > p { color: #475467; font-size: 12px; line-height: 1.9; }

                /* Edit account */
                .lux-academy-account .woocommerce-EditAccountForm {
                    padding: 25px;
                    border: 1px solid var(--lux-line);
                    border-radius: 18px;
                    background: #fff;
                    box-shadow: 0 16px 48px rgba(16,24,40,.055);
                }

                .lux-academy-account .woocommerce-EditAccountForm::after { content: ""; display: table; clear: both; }
                .lux-academy-account .woocommerce-EditAccountForm .form-row-first,
                .lux-academy-account .woocommerce-EditAccountForm .form-row-last { width: calc(50% - 8px); }
                .lux-academy-account .woocommerce-EditAccountForm .form-row-first { float: right; }
                .lux-academy-account .woocommerce-EditAccountForm .form-row-last { float: left; }
                .lux-academy-account .woocommerce-EditAccountForm em { display: block; margin-top: 5px; color: var(--lux-muted); font-size: 9px; line-height: 1.7; }
                .lux-academy-account .woocommerce-EditAccountForm fieldset { margin: 23px 0 18px; padding: 20px; border: 1px solid var(--lux-line); border-radius: 15px; background: var(--lux-soft); }
                .lux-academy-account .woocommerce-EditAccountForm legend { padding: 0 9px; color: var(--lux-ink); font-size: 13px; font-weight: 950; }
                .lux-academy-account .woocommerce-EditAccountForm button[type="submit"] { min-width: 150px; min-height: 49px; }

                .lux-academy-account :is(a, button, input):focus-visible { outline: 3px solid color-mix(in srgb, var(--lux-primary) 28%, transparent); outline-offset: 3px; }

                @keyframes luxCardIn {
                    from { opacity: 0; transform: translateY(10px); }
                    to { opacity: 1; transform: translateY(0); }
                }

                @media (max-width: 1050px) {
                    .lux-dashboard-stats { grid-template-columns: repeat(2, 1fr); }
                    .lux-dashboard-layout { grid-template-columns: 1fr; }
                    .lux-dashboard-sidebar { grid-template-columns: repeat(2, 1fr); position: static; }
                }

                @media (max-width: 780px) {
                    .lux-academy-account .woocommerce { width: min(100% - 22px, 680px); margin-top: 22px; }
                    .lux-academy-account .woocommerce-MyAccount-navigation { float: none; width: 100%; margin-bottom: 16px; padding: 8px; position: static; overflow-x: auto; scrollbar-width: none; }
                    .lux-academy-account .woocommerce-MyAccount-navigation::-webkit-scrollbar { display: none; }
                    .lux-academy-account .woocommerce-MyAccount-navigation ul { display: flex; width: max-content; }
                    .lux-academy-account .woocommerce-MyAccount-navigation a { min-height: 40px; padding: 0 12px; white-space: nowrap; }
                    .lux-academy-account .woocommerce-MyAccount-content { float: none; width: 100%; }
                    .lux-dashboard-hero { align-items: flex-start; padding: 22px; }
                    .lux-profile-link { width: 43px; min-width: 43px; padding: 0; font-size: 0; }
                    .lux-profile-link .lux-dash-icon { font-size: 21px; }
                    .lux-academy-account table.woocommerce-orders-table thead { display: none; }
                    .lux-academy-account table.woocommerce-orders-table,
                    .lux-academy-account table.woocommerce-orders-table tbody,
                    .lux-academy-account table.woocommerce-orders-table tr,
                    .lux-academy-account table.woocommerce-orders-table td { display: block; width: 100%; }
                    .lux-academy-account table.woocommerce-orders-table tr { padding: 10px 14px; border-bottom: 1px solid var(--lux-line); }
                    .lux-academy-account table.woocommerce-orders-table tr:last-child { border-bottom: 0; }
                    .lux-academy-account table.woocommerce-orders-table td { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 7px 0; border: 0; text-align: left !important; }
                    .lux-academy-account table.woocommerce-orders-table td::before { color: var(--lux-muted); font-size: 9px; font-weight: 850; text-align: right; }
                }

                @media (max-width: 560px) {
                    .lux-academy-account:not(.logged-in) .u-columns.col2-set { grid-template-columns: 1fr; }
                    .lux-auth-intro h1 { font-size: 27px; }
                    .lux-auth-benefits { gap: 6px; }
                    .lux-auth-benefits > span { padding: 6px 8px; }
                    .lux-academy-account .woocommerce-form-login,
                    .lux-academy-account .woocommerce-form-register { padding: 20px 16px !important; }
                    .lux-account-section-head { align-items: flex-start; padding: 18px; }
                    .lux-account-section-icon { width: 42px; height: 42px; flex-basis: 42px; }
                    .lux-account-section-head h1 { font-size: 20px; }
                    .lux-academy-account .woocommerce-EditAccountForm { padding: 20px 16px; }
                    .lux-academy-account .woocommerce-EditAccountForm .form-row-first,
                    .lux-academy-account .woocommerce-EditAccountForm .form-row-last { float: none; width: 100%; }
                    .lux-dashboard-notice { grid-template-columns: 38px 1fr; }
                    .lux-dashboard-notice > a { grid-column: 2; }
                    .lux-dashboard-profile { align-items: flex-start; }
                    .lux-dashboard-avatar, .lux-dashboard-avatar img { width: 54px; height: 54px; }
                    .lux-dashboard-avatar { flex-basis: 54px; }
                    .lux-dashboard-hero h1 { font-size: 20px; }
                    .lux-dashboard-hero p { display: none; }
                    .lux-dashboard-stats { gap: 8px; }
                    .lux-dashboard-stats article { padding: 12px; }
                    .lux-stat-icon { width: 36px; height: 36px; flex-basis: 36px; }
                    .lux-dashboard-stats strong { font-size: 17px; }
                    .lux-section-heading { align-items: stretch; flex-direction: column; }
                    .lux-course-search { width: 100%; }
                    .lux-course-grid { grid-template-columns: 1fr; }
                    .lux-course-media { height: 190px; }
                    .lux-dashboard-sidebar { grid-template-columns: 1fr; }
                }

                @media (prefers-reduced-motion: reduce) {
                    .lux-student-dashboard *,
                    .lux-student-dashboard *::before,
                    .lux-student-dashboard *::after { animation: none !important; transition: none !important; scroll-behavior: auto !important; }
                }
            </style>

            <script>
                (function () {
                    'use strict';

                    function initAccount() {
                        var account = document.querySelector('.lux-academy-account .woocommerce');
                        var switcher = account ? account.querySelector('[data-lux-auth-switch]') : null;
                        if (!account || !switcher || switcher.dataset.luxReady === '1') return;
                        switcher.dataset.luxReady = '1';

                        function setAuthMode(mode, moveFocus) {
                            mode = mode === 'register' ? 'register' : 'login';
                            account.setAttribute('data-lux-auth-mode', mode);

                            switcher.querySelectorAll('[data-lux-auth-mode]').forEach(function (button) {
                                var active = button.getAttribute('data-lux-auth-mode') === mode;
                                button.classList.toggle('is-active', active);
                                button.setAttribute('aria-pressed', active ? 'true' : 'false');
                            });

                            if (moveFocus) {
                                var panel = account.querySelector(mode === 'register' ? '.u-column2' : '.u-column1');
                                var field = panel ? panel.querySelector('input:not([type="hidden"])') : null;
                                if (field) field.focus();
                            }
                        }

                        switcher.addEventListener('click', function (event) {
                            var button = event.target.closest('[data-lux-auth-mode]');
                            if (!button) return;
                            setAuthMode(button.getAttribute('data-lux-auth-mode'), true);
                        });

                        setAuthMode(window.location.hash === '#register' ? 'register' : 'login', false);
                    }

                    function initDashboard() {
                        var dashboard = document.querySelector('.lux-student-dashboard');
                        if (!dashboard || dashboard.dataset.luxReady === '1') return;
                        dashboard.dataset.luxReady = '1';

                        var search = dashboard.querySelector('[data-lux-course-search]');
                        var cards = dashboard.querySelectorAll('[data-lux-course-card]');
                        var noResult = dashboard.querySelector('[data-lux-no-result]');

                        if (!search || !cards.length) return;

                        search.addEventListener('input', function () {
                            var query = (search.value || '').trim().toLocaleLowerCase('fa');
                            var visible = 0;

                            cards.forEach(function (card) {
                                var title = (card.getAttribute('data-course-title') || '').toLocaleLowerCase('fa');
                                var match = !query || title.indexOf(query) !== -1;
                                card.hidden = !match;
                                if (match) visible++;
                            });

                            if (noResult) noResult.hidden = visible !== 0;
                        });
                    }

                    function initAll() {
                        initAccount();
                        initDashboard();
                    }

                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', initAll);
                    } else {
                        initAll();
                    }
                })();
            </script>
            <?php
            return ob_get_clean();
        }
    }

    Luxury_Academy_Student_Dashboard::init();
}
