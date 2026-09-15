/**
 * Asadzadeh Academy - Complete Homepage - Tutor LMS + WooCommerce + Elementor + WPCode
 * Design Read: artisan academy landing for Persian learners, editorial heritage language, leaning toward Vazirmatn + brick/cream palette + double-bezel + bento
 * Dials: VARIANCE 8 / MOTION 6 / DENSITY 4 - taste-skill: design-taste-frontend + high-end-visual-design + redesign-existing-projects
 * Palette: brick #8f2e32 / #9d4a2f + cream #fdfcfa + forest #1f3128 - Forest family per skill override for warm-craft heritage, not AI-default beige+brass
 *
 * Architecture:
 * - Tutor LMS is source for educational data: title, image, teacher, level, duration, category, enrollment, progress
 * - WooCommerce is source for commercial data: price, sale price, product, cart, coupon, order
 * - Link via _tutor_course_product_id - no DB merge
 * - Compatible with snippetCart.php (luxury_ajax_cart), snippetCheckout.php (luxury_checkout), snippetDashboard.php (my-courses endpoint), snippetTutorCards.php (owned cards)
 *
 * Shortcode: [luxury_academy_home] and [asadzadeh_home]
 * Attributes: [luxury_academy_home courses="6" category=""]
 *   courses: 1-12, default 6
 *   category: slug or term_id of Tutor course category, optional
 *
 * Install in WPCode:
 * 1. WPCode > Add Snippet > Add Your Custom Code (PHP Snippet)
 * 2. Paste WITHOUT <?php tag, Run Everywhere, Priority 10
 * 3. Save and Activate
 * 4. Elementor > Shortcode widget > [luxury_academy_home] or Gutenberg Shortcode block
 *
 * Paste into WPCode without PHP opening tag and run everywhere.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Asadzadeh_Academy_Home' ) ) {

    class Asadzadeh_Academy_Home {

        const SHORTCODE_NEW = 'asadzadeh_home';
        const SHORTCODE_OLD = 'luxury_academy_home';
        private static $instance = 0;

        public static function init() {
            add_shortcode( self::SHORTCODE_NEW, array( __CLASS__, 'render' ) );
            add_shortcode( self::SHORTCODE_OLD, array( __CLASS__, 'render' ) );
        }

        private static function tutor_available() {
            return function_exists( 'tutor' ) && function_exists( 'tutor_utils' );
        }

        private static function woocommerce_available() {
            return function_exists( 'WC' ) && class_exists( 'WooCommerce' );
        }

        private static function course_post_type() {
            if ( self::tutor_available() && ! empty( tutor()->course_post_type ) ) {
                return sanitize_key( tutor()->course_post_type );
            }
            return post_type_exists( 'courses' ) ? 'courses' : 'tutor_course';
        }

        private static function course_taxonomy() {
            if ( self::tutor_available() && ! empty( tutor()->course_taxonomy ) ) {
                return sanitize_key( tutor()->course_taxonomy );
            }
            foreach ( array( 'course-category', 'course_cat', 'tutor_course_category' ) as $tax ) {
                if ( taxonomy_exists( $tax ) ) {
                    return $tax;
                }
            }
            return '';
        }

        private static function course_ids( $limit, $category = '' ) {
            $args = array(
                'post_type'              => self::course_post_type(),
                'post_status'            => 'publish',
                'posts_per_page'         => max( 12, min( 60, $limit * 4 ) ),
                'orderby'                => 'date',
                'order'                  => 'DESC',
                'no_found_rows'          => true,
                'ignore_sticky_posts'    => true,
                'fields'                 => 'ids',
                'update_post_meta_cache' => true,
                'update_post_term_cache' => true,
            );
            $taxonomy = self::course_taxonomy();
            if ( $taxonomy && $category ) {
                $term = term_exists( $category, $taxonomy );
                if ( $term ) {
                    $args['tax_query'] = array(
                        array(
                            'taxonomy' => $taxonomy,
                            'field'    => is_numeric( $category ) ? 'term_id' : 'slug',
                            'terms'    => is_numeric( $category ) ? absint( $category ) : sanitize_title( $category ),
                        ),
                    );
                }
            }
            $ids = get_posts( $args );
            $ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
            usort(
                $ids,
                static function ( $left, $right ) {
                    $left_featured  = self::is_featured_course( $left ) ? 1 : 0;
                    $right_featured = self::is_featured_course( $right ) ? 1 : 0;
                    if ( $left_featured !== $right_featured ) {
                        return $right_featured <=> $left_featured;
                    }
                    $left_order  = (int) get_post_field( 'menu_order', $left );
                    $right_order = (int) get_post_field( 'menu_order', $right );
                    if ( $left_order !== $right_order ) {
                        return $left_order <=> $right_order;
                    }
                    return (int) get_post_time( 'U', true, $right ) <=> (int) get_post_time( 'U', true, $left );
                }
            );
            return array_slice( $ids, 0, $limit );
        }

        private static function is_featured_course( $course_id ) {
            foreach ( array( '_tutor_course_featured', '_tutor_is_featured', 'is_featured' ) as $key ) {
                if ( in_array( strtolower( (string) get_post_meta( $course_id, $key, true ) ), array( '1', 'yes', 'true', 'on' ), true ) ) {
                    return true;
                }
            }
            return false;
        }

        private static function product_map( $course_ids ) {
            $map = array();
            $product_ids = array();
            foreach ( $course_ids as $course_id ) {
                $product_id = absint( get_post_meta( $course_id, '_tutor_course_product_id', true ) );
                $map[ $course_id ] = $product_id;
                if ( $product_id ) {
                    $product_ids[] = $product_id;
                }
            }
            if ( ! self::woocommerce_available() || empty( $product_ids ) ) {
                return array_fill_keys( array_keys( $map ), false );
            }
            $products = wc_get_products(
                array(
                    'include' => array_values( array_unique( $product_ids ) ),
                    'limit'   => -1,
                    'status'  => array( 'publish', 'private' ),
                    'return'  => 'objects',
                )
            );
            $products_by_id = array();
            foreach ( $products as $product ) {
                if ( is_object( $product ) && method_exists( $product, 'get_id' ) ) {
                    $products_by_id[ (int) $product->get_id() ] = $product;
                }
            }
            foreach ( $map as $course_id => $product_id ) {
                $map[ $course_id ] = $product_id && isset( $products_by_id[ $product_id ] ) ? $products_by_id[ $product_id ] : false;
            }
            return $map;
        }

        private static function enrolled_ids() {
            if ( ! is_user_logged_in() || ! self::tutor_available() ) {
                return array();
            }
            $enrolled = tutor_utils()->get_enrolled_courses_by_user( get_current_user_id(), array( 'publish', 'private' ) );
            $posts    = $enrolled instanceof WP_Query ? $enrolled->posts : ( is_array( $enrolled ) ? $enrolled : array() );
            $ids      = array();
            foreach ( $posts as $course ) {
                $id = is_object( $course ) && isset( $course->ID ) ? $course->ID : $course;
                if ( absint( $id ) ) {
                    $ids[] = absint( $id );
                }
            }
            return array_values( array_unique( $ids ) );
        }

        private static function progress( $course_id ) {
            if ( ! self::tutor_available() || ! is_user_logged_in() ) {
                return array( 'percent' => 0, 'completed' => 0, 'total' => 0 );
            }
            $stats = tutor_utils()->get_course_completed_percent( $course_id, get_current_user_id(), true );
            if ( ! is_array( $stats ) ) {
                return array( 'percent' => max( 0, min( 100, (int) $stats ) ), 'completed' => 0, 'total' => 0 );
            }
            return array(
                'percent'   => max( 0, min( 100, (int) ( $stats['completed_percent'] ?? 0 ) ) ),
                'completed' => absint( $stats['completed_count'] ?? 0 ),
                'total'     => absint( $stats['total_count'] ?? 0 ),
            );
        }

        private static function continue_url( $course_id, $percent ) {
            $course_url = get_permalink( $course_id );
            if ( $percent <= 0 || ! self::tutor_available() ) {
                return $course_url;
            }
            $lesson = tutor_utils()->get_course_first_lesson( $course_id );
            if ( is_numeric( $lesson ) ) {
                $lesson = get_permalink( absint( $lesson ) );
            } elseif ( $lesson instanceof WP_Post ) {
                $lesson = get_permalink( $lesson->ID );
            }
            return $lesson ? $lesson : $course_url;
        }

        private static function course_detail( $course_id, $kind ) {
            if ( self::tutor_available() ) {
                $method = 'level' === $kind ? 'get_course_level' : 'get_course_duration_context';
                if ( is_object( tutor_utils() ) && method_exists( tutor_utils(), $method ) ) {
                    $value = tutor_utils()->$method( $course_id );
                    if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
                        return wp_strip_all_tags( (string) $value );
                    }
                }
            }
            $meta_keys = 'level' === $kind ? array( '_tutor_course_level', 'course_level' ) : array( '_tutor_course_duration', 'course_duration' );
            foreach ( $meta_keys as $meta_key ) {
                $value = get_post_meta( $course_id, $meta_key, true );
                if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
                    return wp_strip_all_tags( (string) $value );
                }
            }
            return '';
        }

        private static function cart_contains( $product_id ) {
            if ( ! $product_id || ! self::woocommerce_available() ) {
                return false;
            }
            if ( function_exists( 'wc_load_cart' ) && ( ! WC()->cart || ! WC()->session ) ) {
                wc_load_cart();
            }
            if ( ! WC()->cart ) {
                return false;
            }
            foreach ( WC()->cart->get_cart() as $item ) {
                if ( $product_id === absint( $item['product_id'] ?? 0 ) || $product_id === absint( $item['variation_id'] ?? 0 ) ) {
                    return true;
                }
            }
            return false;
        }

        private static function course_data( $course_ids ) {
            $products     = self::product_map( $course_ids );
            $enrolled_ids = self::enrolled_ids();
            $course_rows  = array();
            $author_ids   = array();
            foreach ( $course_ids as $course_id ) {
                $author_ids[] = (int) get_post_field( 'post_author', $course_id );
            }
            $authors = array();
            foreach ( array_unique( $author_ids ) as $author_id ) {
                $authors[ $author_id ] = get_the_author_meta( 'display_name', $author_id );
            }
            foreach ( $course_ids as $course_id ) {
                $product     = $products[ $course_id ] ?? false;
                $progress    = self::progress( $course_id );
                $is_enrolled = in_array( $course_id, $enrolled_ids, true );
                $is_free     = ! $product || ( method_exists( $product, 'is_free' ) ? $product->is_free() : (float) $product->get_price() <= 0 );
                $taxonomy    = self::course_taxonomy();
                $terms       = $taxonomy ? get_the_terms( $course_id, $taxonomy ) : array();
                $term_names  = is_array( $terms ) ? wp_list_pluck( array_slice( $terms, 0, 2 ), 'name' ) : array();
                $image       = get_the_post_thumbnail_url( $course_id, 'medium_large' );
                $course_url  = get_permalink( $course_id );
                $action_url  = $is_enrolled ? self::continue_url( $course_id, $progress['percent'] ) : $course_url;

                $course_rows[] = array(
                    'id'          => $course_id,
                    'title'       => get_the_title( $course_id ),
                    'url'         => $course_url,
                    'action_url'  => $action_url,
                    'image'       => $image,
                    'author'      => $authors[ (int) get_post_field( 'post_author', $course_id ) ] ?? '',
                    'terms'       => $term_names,
                    'product'     => $product,
                    'is_free'     => $is_free,
                    'is_enrolled' => $is_enrolled,
                    'progress'    => $progress,
                    'in_cart'     => $product && self::cart_contains( $product->get_id() ),
                );
            }
            return $course_rows;
        }

        private static function price_html( $product ) {
            if ( ! $product || ! is_object( $product ) ) {
                return '<span class="aa-price-free">رایگان</span>';
            }
            return wp_kses_post( $product->get_price_html() );
        }

        private static function learning_categories() {
            $taxonomy = self::course_taxonomy();
            if ( ! $taxonomy ) {
                return array();
            }
            $terms = get_terms(
                array(
                    'taxonomy'   => $taxonomy,
                    'hide_empty' => true,
                    'number'     => 8,
                    'orderby'    => 'count',
                    'order'      => 'DESC',
                )
            );
            return is_wp_error( $terms ) ? array() : $terms;
        }

        private static function active_courses( $limit = 3 ) {
            $ids = array_slice( self::enrolled_ids(), 0, $limit );
            return $ids ? self::course_data( $ids ) : array();
        }

        private static function course_card( $row, $index ) {
            $progress = $row['progress'];
            $status   = '';
            $label    = '';
            $url      = $row['action_url'];
            $is_owned_class = '';

            if ( $row['is_enrolled'] ) {
                $is_owned_class = 'is-owned';
                if ( $progress['percent'] >= 100 ) {
                    $status = 'تکمیل شده';
                    $label  = 'مرور دوره';
                } elseif ( $progress['percent'] > 0 ) {
                    $status = 'در حال یادگیری';
                    $label  = 'ادامه دوره';
                } else {
                    $status = 'دسترسی فعال';
                    $label  = 'مشاهده دوره';
                }
            } elseif ( $row['in_cart'] ) {
                $status = 'در سبد خرید';
                $label  = 'مشاهده سبد خرید';
                $url    = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/' );
            } elseif ( $row['is_free'] ) {
                $status = 'رایگان';
                $label  = is_user_logged_in() ? 'شروع دوره' : 'ورود برای ثبت نام';
                $url    = is_user_logged_in() ? $row['url'] : ( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url( $row['url'] ) );
            } else {
                $status = 'ثبت نام دوره';
                $label  = 'افزودن به سبد خرید';
                $url    = $row['product'] ? add_query_arg( 'add-to-cart', $row['product']->get_id(), home_url( '/' ) ) : $row['url'];
            }
            ?>
            <article class="aa-course-card <?php echo esc_attr( $is_owned_class ); ?>" data-reveal style="--aa-delay: <?php echo esc_attr( min( $index * 70, 420 ) ); ?>ms">
                <div class="aa-course-shell">
                    <div class="aa-course-inner">
                        <a class="aa-course-media" href="<?php echo esc_url( $row['url'] ); ?>" aria-label="<?php echo esc_attr( 'مشاهده ' . $row['title'] ); ?>">
                            <?php if ( $row['image'] ) : ?>
                                <img src="<?php echo esc_url( $row['image'] ); ?>" alt="<?php echo esc_attr( $row['title'] ); ?>" loading="lazy" decoding="async">
                            <?php else : ?>
                                <img src="https://picsum.photos/seed/<?php echo esc_attr( 'aa-course-' . $row['id'] ); ?>/640/360" alt="<?php echo esc_attr( $row['title'] ); ?>" loading="lazy" decoding="async">
                            <?php endif; ?>
                            <span class="aa-course-badge <?php echo esc_attr( $is_owned_class ); ?>"><?php echo esc_html( $status ); ?></span>
                        </a>
                        <div class="aa-course-body">
                            <div class="aa-course-top">
                                <h3><a href="<?php echo esc_url( $row['url'] ); ?>"><?php echo esc_html( $row['title'] ); ?></a></h3>
                                <?php if ( $row['terms'] ) : ?>
                                    <span class="aa-course-cat"><?php echo esc_html( implode( '، ', $row['terms'] ) ); ?></span>
                                <?php endif; ?>
                            </div>
                            <p class="aa-course-teacher"><?php echo esc_html( $row['author'] ? 'مدرس: ' . $row['author'] : 'آکادمی اسدزاده' ); ?></p>
                            <div class="aa-course-meta">
                                <span><?php echo esc_html( self::course_detail( $row['id'], 'level' ) ?: 'همه سطوح' ); ?></span>
                                <?php $dur = self::course_detail( $row['id'], 'duration' ); if ( $dur ) : ?><span><?php echo esc_html( $dur ); ?></span><?php endif; ?>
                            </div>
                            <?php if ( $row['is_enrolled'] ) : ?>
                                <div class="aa-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( $progress['percent'] ); ?>">
                                    <div class="aa-progress-head"><span>پیشرفت شما</span><strong><?php echo esc_html( $progress['percent'] ); ?>٪</strong></div>
                                    <span class="aa-progress-track"><i style="width: <?php echo esc_attr( $progress['percent'] ); ?>%"></i></span>
                                    <div class="aa-progress-meta"><span><?php echo esc_html( $progress['completed'] ); ?> از <?php echo esc_html( $progress['total'] ); ?> بخش</span></div>
                                </div>
                            <?php elseif ( ! $row['is_free'] ) : ?>
                                <div class="aa-course-price"><?php echo self::price_html( $row['product'] ); ?></div>
                            <?php else : ?>
                                <div class="aa-course-price"><span class="aa-price-free">رایگان</span></div>
                            <?php endif; ?>
                            <a class="aa-btn is-course <?php echo esc_attr( $is_owned_class ); ?>" href="<?php echo esc_url( $url ); ?>">
                                <span class="aa-btn-label"><?php echo esc_html( $label ); ?></span>
                                <span class="aa-btn-icon" aria-hidden="true">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M10 3L13 8L10 13" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path d="M13 8H3" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
                                </span>
                            </a>
                        </div>
                    </div>
                </div>
            </article>
            <?php
        }

        public static function render( $atts = array() ) {
            $atts = shortcode_atts(
                array(
                    'courses'  => 6,
                    'category' => '',
                ),
                $atts,
                self::SHORTCODE_NEW
            );
            $limit       = max( 1, min( 12, absint( $atts['courses'] ) ?: 6 ) );
            $category    = sanitize_text_field( (string) $atts['category'] );
            $instance    = 'aa-home-' . ( ++self::$instance );
            $course_rows = self::tutor_available() ? self::course_data( self::course_ids( $limit, $category ) ) : array();
            $categories  = self::tutor_available() ? self::learning_categories() : array();
            $active      = is_user_logged_in() && self::tutor_available() ? self::active_courses( 3 ) : array();
            $account_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url();
            $courses_url = home_url( '/doreha/' );
            if ( self::tutor_available() && is_object( tutor_utils() ) && method_exists( tutor_utils(), 'get_courses_page_url' ) ) {
                $maybe = tutor_utils()->get_courses_page_url();
                if ( $maybe ) {
                    $courses_url = $maybe;
                }
            }
            $dashboard_url = function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'my-courses' ) : home_url( '/my-account/my-courses/' );
            $cart_url      = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );
            $shop_url      = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : $courses_url;

            $fallback_cats = array(
                array( 'name' => 'قالی بافی', 'desc' => 'آموزش حرفه‌ای قالیبافی از مقدماتی تا پیشرفته', 'seed' => 'qali-bafi', 'count' => '12' ),
                array( 'name' => 'گلیم بافی', 'desc' => 'آشنایی با نقش، رنگ و بافت گلیم‌های اصیل ایرانی', 'seed' => 'gelim-bafi', 'count' => '8' ),
                array( 'name' => 'گبه بافی', 'desc' => 'از پشم تا نقش، آموزش ساده و کاربردی گبه بافی', 'seed' => 'gabbeh-bafi', 'count' => '6' ),
                array( 'name' => 'هنرهای سنتی', 'desc' => 'آشنایی با هنرهای اصیل ایرانی، از بافت تا تزئینات', 'seed' => 'honar-sonnati', 'count' => '10' ),
            );

            ob_start();
            ?>
            <div id="<?php echo esc_attr( $instance ); ?>" class="aa-home aa-elementor-compatible" dir="rtl">
                <?php echo self::assets( $instance ); ?>
                <?php if ( ! self::tutor_available() || ! self::woocommerce_available() ) : ?>
                    <div class="aa-system-notice" role="alert">برای نمایش صفحه اصلی، Tutor LMS و WooCommerce باید فعال باشند.</div>
                <?php endif; ?>

                <!-- 1. HERO -->
                <section class="aa-hero" aria-labelledby="<?php echo esc_attr( $instance . '-title' ); ?>">
                    <div class="aa-hero-grid">
                        <div class="aa-hero-copy" data-reveal>
                            <span class="aa-eyebrow">تجربه‌ای که منتقل می‌شود</span>
                            <h1 id="<?php echo esc_attr( $instance . '-title' ); ?>">به آکادمی اسدزاده خوش آمدید</h1>
                            <p>آموزش حرفه‌ای قالیبافی، گلیم‌بافی، گبه‌بافی و هنرهای سنتی ایران از اولین گره تا خلق اثری اصیل</p>
                            <div class="aa-hero-actions">
                                <a class="aa-btn is-primary" href="<?php echo esc_url( $courses_url ); ?>">
                                    <span class="aa-btn-label">مشاهده دوره‌ها</span>
                                    <span class="aa-btn-icon" aria-hidden="true">
                                        <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M10 3L13 8L10 13" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path d="M13 8H3" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
                                    </span>
                                </a>
                                <a class="aa-btn is-secondary" href="<?php echo esc_url( is_user_logged_in() ? $dashboard_url : $account_url ); ?>">
                                    <span class="aa-btn-label"><?php echo esc_html( is_user_logged_in() ? 'ادامه یادگیری' : 'ورود به پیشخوان' ); ?></span>
                                </a>
                            </div>
                            <div class="aa-hero-proof">
                                <div class="aa-proof-item"><strong>گواهینامه معتبر</strong><span>پس از اتمام دوره</span></div>
                                <div class="aa-proof-item"><strong>اساتید باتجربه</strong><span>با سال‌ها تجربه</span></div>
                                <div class="aa-proof-item"><strong>+500 هنرجو</strong><span>همراه مسیر یادگیری</span></div>
                            </div>
                        </div>
                        <div class="aa-hero-visual" data-reveal style="--aa-delay: 120ms">
                            <div class="aa-visual-shell">
                                <div class="aa-visual-inner">
                                    <img src="https://asadzadehacademy.ir/wp-content/uploads/2026/09/%DA%A9%D8%A7%D8%B1%DA%AF%D8%A7%D9%87-%D9%82%D8%A7%D9%84%DB%8C%D8%A8%D8%A7%D9%81%DB%8C-1024x768.png" alt="کارگاه قالیبافی آکادمی اسدزاده" loading="eager" decoding="async" onerror="this.src='https://picsum.photos/seed/asadzadeh-hero/1024/768'">
                                    <div class="aa-visual-grain" aria-hidden="true"></div>
                                </div>
                            </div>
                            <div class="aa-float-card is-top">
                                <span class="aa-float-num">+20</span>
                                <span class="aa-float-label">دوره آموزشی آنلاین و حضوری</span>
                            </div>
                            <div class="aa-float-card is-bottom">
                                <span class="aa-float-quote">هنر راهی برای زندگی زیباتر</span>
                                <span class="aa-float-sub">با دست‌ها می‌آموزیم، با دل‌ها ماندگار می‌کنیم</span>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- METRICS -->
                <section class="aa-metrics" aria-label="آمار آکادمی">
                    <div class="aa-metrics-grid">
                        <div class="aa-metric" data-reveal><strong>+20</strong><span>دوره آموزشی</span></div>
                        <div class="aa-metric" data-reveal style="--aa-delay: 80ms"><strong>+500</strong><span>هنرجوی فعال</span></div>
                        <div class="aa-metric" data-reveal style="--aa-delay: 160ms"><strong>20 سال</strong><span>تجربه آموزش</span></div>
                        <div class="aa-metric" data-reveal style="--aa-delay: 240ms"><strong>گواهی</strong><span>پایان دوره معتبر</span></div>
                    </div>
                </section>

                <!-- 2. FEATURED COURSES -->
                <section class="aa-section aa-courses" aria-labelledby="<?php echo esc_attr( $instance . '-courses' ); ?>">
                    <div class="aa-section-head">
                        <div class="aa-head-stack">
                            <span class="aa-eyebrow">دوره‌های ویژه</span>
                            <h2 id="<?php echo esc_attr( $instance . '-courses' ); ?>">برای قدم بعدی یادگیری</h2>
                        </div>
                        <a class="aa-btn is-secondary is-sm" href="<?php echo esc_url( $courses_url ); ?>"><span class="aa-btn-label">همه دوره‌ها</span></a>
                    </div>
                    <?php if ( $course_rows ) : ?>
                        <div class="aa-course-grid">
                            <?php foreach ( $course_rows as $i => $row ) : self::course_card( $row, $i ); endforeach; ?>
                        </div>
                    <?php elseif ( ! self::tutor_available() ) : ?>
                        <div class="aa-empty" data-reveal><div class="aa-empty-shell"><h3>Tutor LMS فعال نیست</h3><p>پس از فعال‌سازی، دوره‌ها اینجا نمایش داده می‌شوند</p></div></div>
                    <?php else : ?>
                        <div class="aa-empty" data-reveal><div class="aa-empty-shell"><h3>هنوز دوره‌ای منتشر نشده</h3><p>به زودی دوره‌های قالیبافی، گلیم‌بافی و گبه‌بافی در این بخش قرار می‌گیرد</p><a class="aa-btn is-primary" href="<?php echo esc_url( $courses_url ); ?>"><span class="aa-btn-label">مشاهده دسته‌ها</span></a></div></div>
                    <?php endif; ?>
                </section>

                <!-- 3. CATEGORIES / LEARNING PATHS -->
                <section class="aa-section aa-cats" aria-labelledby="<?php echo esc_attr( $instance . '-cats' ); ?>">
                    <div class="aa-section-head">
                        <h2 id="<?php echo esc_attr( $instance . '-cats' ); ?>">دسته‌های آموزشی</h2>
                        <a class="aa-text-link" href="<?php echo esc_url( $courses_url ); ?>">مشاهده همه دسته‌ها <span aria-hidden="true">←</span></a>
                    </div>
                    <div class="aa-bento">
                        <?php
                        $cats_to_show = $categories ? array_slice( $categories, 0, 4 ) : array();
                        if ( $cats_to_show ) {
                            $bento_layout = array( 'is-large', 'is-small', 'is-small', 'is-wide' );
                            foreach ( $cats_to_show as $idx => $term ) {
                                $term_link = get_term_link( $term );
                                $term_name = $term->name;
                                $term_count = $term->count;
                                $seed = sanitize_title( $term->slug ) . '-' . $term->term_id;
                                $layout_class = $bento_layout[ $idx % 4 ];
                                ?>
                                <a class="aa-bento-card <?php echo esc_attr( $layout_class ); ?>" href="<?php echo esc_url( is_wp_error( $term_link ) ? $courses_url : $term_link ); ?>" data-reveal style="--aa-delay: <?php echo esc_attr( $idx * 90 ); ?>ms">
                                    <div class="aa-bento-shell"><div class="aa-bento-inner">
                                        <div class="aa-bento-media"><img src="https://picsum.photos/seed/<?php echo esc_attr( $seed ); ?>/800/450" alt="<?php echo esc_attr( $term_name ); ?>" loading="lazy"></div>
                                        <div class="aa-bento-body"><h3><?php echo esc_html( $term_name ); ?></h3><p><?php echo esc_html( $term->description ? wp_trim_words( $term->description, 12 ) : 'آموزش تخصصی و کاربردی هنرهای اصیل' ); ?></p><span class="aa-bento-meta"><?php echo esc_html( $term_count ); ?> دوره</span></div>
                                    </div></div>
                                </a>
                                <?php
                            }
                        } else {
                            foreach ( $fallback_cats as $idx => $cat ) {
                                $layout_class = array( 'is-large', 'is-small', 'is-small', 'is-wide' )[ $idx % 4 ];
                                ?>
                                <a class="aa-bento-card <?php echo esc_attr( $layout_class ); ?>" href="<?php echo esc_url( $courses_url ); ?>" data-reveal style="--aa-delay: <?php echo esc_attr( $idx * 90 ); ?>ms">
                                    <div class="aa-bento-shell"><div class="aa-bento-inner">
                                        <div class="aa-bento-media"><img src="https://picsum.photos/seed/<?php echo esc_attr( $cat['seed'] ); ?>/800/450" alt="<?php echo esc_attr( $cat['name'] ); ?>" loading="lazy"></div>
                                        <div class="aa-bento-body"><h3><?php echo esc_html( $cat['name'] ); ?></h3><p><?php echo esc_html( $cat['desc'] ); ?></p><span class="aa-bento-meta"><?php echo esc_html( $cat['count'] ); ?> دوره</span></div>
                                    </div></div>
                                </a>
                                <?php
                            }
                        }
                        ?>
                    </div>
                </section>

                <!-- 4. BENEFITS -->
                <section class="aa-section aa-why" aria-labelledby="<?php echo esc_attr( $instance . '-why' ); ?>">
                    <div class="aa-why-grid">
                        <div class="aa-why-head" data-reveal>
                            <h2 id="<?php echo esc_attr( $instance . '-why' ); ?>">چرا آکادمی اسدزاده</h2>
                            <p>آموزش اصولی هنرهای سنتی، از تجربه استاد تا همراهی هنرجو</p>
                        </div>
                        <div class="aa-why-list">
                            <div class="aa-why-item" data-reveal><span class="aa-why-num">01</span><div><h3>آموزش عملی و اصولی</h3><p>یادگیری با تمرین، پروژه و تجربه واقعی در کارگاه مجهز</p></div></div>
                            <div class="aa-why-item" data-reveal style="--aa-delay: 80ms"><span class="aa-why-num">02</span><div><h3>مدرس باتجربه</h3><p>آموزش بر پایه سال‌ها تجربه عملی و آموزشی استاد اسدزاده</p></div></div>
                            <div class="aa-why-item" data-reveal style="--aa-delay: 160ms"><span class="aa-why-num">03</span><div><h3>همراهی هنرجو</h3><p>پشتیبانی و راهنمایی در طول مسیر یادگیری تا خلق اثر نهایی</p></div></div>
                            <div class="aa-why-item" data-reveal style="--aa-delay: 240ms"><span class="aa-why-num">04</span><div><h3>گواهی پایان دوره</h3><p>ارائه گواهی پس از تکمیل دوره‌های واجد شرایط</p></div></div>
                        </div>
                    </div>
                </section>

                <!-- MASTER + ABOUT combined -->
                <section class="aa-section aa-master" aria-labelledby="<?php echo esc_attr( $instance . '-master' ); ?>">
                    <div class="aa-master-grid">
                        <div class="aa-master-visual" data-reveal>
                            <div class="aa-master-shell"><div class="aa-master-inner"><img src="https://picsum.photos/seed/ostad-naser-aa/800/1000" alt="استاد ناصر اسدزاده" loading="lazy"></div></div>
                            <div class="aa-master-quote"><p>حفظ هنرهای سنتی، حفظ بخشی از هویت و فرهنگ ماست</p></div>
                        </div>
                        <div class="aa-master-copy" data-reveal style="--aa-delay: 120ms">
                            <h2 id="<?php echo esc_attr( $instance . '-master' ); ?>">استاد ناصر اسدزاده</h2>
                            <span class="aa-master-role">مدرس و بنیان‌گذار آکادمی</span>
                            <p>با بیش از دو دهه تجربه در آموزش قالیبافی، گلیم‌بافی و هنرهای بافت ایرانی، هدف استاد اسدزاده انتقال اصول صحیح و تجربه عملی این هنرها به نسل جدید هنرجویان است.</p>
                            <p>ما در آکادمی اسدزاده باور داریم که هنرهای سنتی تنها یک مهارت نیستند، بلکه پلی هستند میان گذشته و آینده.</p>
                            <div class="aa-master-stats">
                                <div><strong>بیش از 20 سال</strong><span>تجربه آموزش هنرهای سنتی</span></div>
                                <div><strong>صدها هنرجو</strong><span>آموزش و همراهی هنرجویان</span></div>
                            </div>
                            <a class="aa-text-link" href="<?php echo esc_url( $courses_url ); ?>">درباره استاد <span aria-hidden="true">←</span></a>
                        </div>
                    </div>
                </section>

                <!-- 5. CONTINUE LEARNING - only for logged with enrollment, max 3, real progress -->
                <?php if ( $active ) : ?>
                    <section class="aa-section aa-continue" aria-labelledby="<?php echo esc_attr( $instance . '-continue' ); ?>">
                        <div class="aa-section-head">
                            <h2 id="<?php echo esc_attr( $instance . '-continue' ); ?>">ادامه یادگیری</h2>
                            <a class="aa-btn is-secondary is-sm" href="<?php echo esc_url( $dashboard_url ); ?>"><span class="aa-btn-label">دوره‌های من</span></a>
                        </div>
                        <div class="aa-course-grid">
                            <?php foreach ( $active as $idx => $row ) : self::course_card( $row, $idx ); endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <!-- WORKSHOPS -->
                <section class="aa-section aa-workshops" aria-labelledby="<?php echo esc_attr( $instance . '-workshops' ); ?>">
                    <div class="aa-section-head">
                        <h2 id="<?php echo esc_attr( $instance . '-workshops' ); ?>">کارگاه‌های حضوری هنرهای سنتی</h2>
                        <p>تجربه‌ای عملی و الهام بخش در کنار استاد برای یادگیری اصولی</p>
                    </div>
                    <div class="aa-workshop-grid">
                        <article class="aa-workshop-card is-main" data-reveal>
                            <div class="aa-workshop-shell"><div class="aa-workshop-inner">
                                <div class="aa-workshop-media"><img src="https://picsum.photos/seed/workshop-main-aa/800/450" alt="کارگاه حضوری قالیبافی" loading="lazy"></div>
                                <div class="aa-workshop-body"><span class="aa-workshop-tag">حضوری</span><h3>کارگاه حضوری قالیبافی مقدماتی</h3><p>از اولین گره تا بافت کامل، همراه با استاد در کارگاه مجهز</p><div class="aa-workshop-meta"><span>6 ساعت</span><span>متوسط</span></div><a class="aa-btn is-primary is-sm" href="<?php echo esc_url( $courses_url ); ?>"><span class="aa-btn-label">مشاهده کارگاه</span></a></div>
                            </div></div>
                        </article>
                        <div class="aa-workshop-stack">
                            <article class="aa-workshop-card" data-reveal style="--aa-delay: 90ms"><div class="aa-workshop-shell"><div class="aa-workshop-inner"><div class="aa-workshop-body"><span class="aa-workshop-tag">آنلاین</span><h3>گلیم بافی از پایه</h3><p>نقش، رنگ و بافت گلیم‌های اصیل</p><a class="aa-text-link" href="<?php echo esc_url( $courses_url ); ?>">مشاهده دوره <span>←</span></a></div></div></div></article>
                            <article class="aa-workshop-card" data-reveal style="--aa-delay: 180ms"><div class="aa-workshop-shell"><div class="aa-workshop-inner"><div class="aa-workshop-body"><span class="aa-workshop-tag">حضوری و آنلاین</span><h3>گبه بافی کاربردی</h3><p>از پشم تا نقش</p><a class="aa-text-link" href="<?php echo esc_url( $courses_url ); ?>">مشاهده دوره <span>←</span></a></div></div></div></article>
                        </div>
                    </div>
                </section>

                <!-- TESTIMONIALS -->
                <section class="aa-section aa-testimonials" aria-labelledby="<?php echo esc_attr( $instance . '-test' ); ?>">
                    <div class="aa-section-head"><h2 id="<?php echo esc_attr( $instance . '-test' ); ?>">تجربه هنرجویان</h2><p>روایت هنرجویانی که مسیر یادگیری را با آکادمی اسدزاده تجربه کرده‌اند</p></div>
                    <div class="aa-test-grid">
                        <article class="aa-test-card is-large" data-reveal><p>آموزش بسیار اصولی و کاربردی بود. از اولین گره تا بافت کامل را قدم به قدم یاد گرفتم. همراهی استاد در طول دوره عالی بود.</p><div class="aa-test-foot"><img src="https://picsum.photos/seed/aa-test-1/80/80" alt="هنرجو" loading="lazy"><div><strong>سارا احمدی</strong><span>دوره قالیبافی مقدماتی</span></div></div></article>
                        <article class="aa-test-card" data-reveal style="--aa-delay: 90ms"><p>کارگاه حضوری تجربه‌ای متفاوت بود. فضای کارگاه و آموزش عملی باعث شد خیلی سریع پیشرفت کنم.</p><div class="aa-test-foot"><img src="https://picsum.photos/seed/aa-test-2/80/80" alt="هنرجو" loading="lazy"><div><strong>محمد حسینی</strong><span>کارگاه حضوری</span></div></div></article>
                        <article class="aa-test-card" data-reveal style="--aa-delay: 180ms"><p>پشتیبانی عالی و دسترسی آسان به محتوای دوره. حتی بعد از اتمام دوره هم پاسخگوی سوالاتم بودند.</p><div class="aa-test-foot"><img src="https://picsum.photos/seed/aa-test-3/80/80" alt="هنرجو" loading="lazy"><div><strong>فاطمه کریمی</strong><span>دوره گلیم بافی</span></div></div></article>
                    </div>
                </section>

                <!-- FAQ -->
                <section class="aa-section aa-faq" aria-labelledby="<?php echo esc_attr( $instance . '-faq' ); ?>">
                    <div class="aa-faq-grid">
                        <div class="aa-faq-head" data-reveal><h2 id="<?php echo esc_attr( $instance . '-faq' ); ?>">سوالات متداول</h2><p>پاسخ سوال‌هایی که ممکن است قبل از شروع دوره یا ثبت نام داشته باشید</p></div>
                        <div class="aa-faq-list" data-reveal style="--aa-delay: 100ms">
                            <details class="aa-faq-item" open><summary>آیا برای شرکت در دوره‌ها نیاز به تجربه قبلی دارم<span aria-hidden="true"></span></summary><p>خیر. دوره‌های مقدماتی از پایه طراحی شده‌اند و برای شروع نیاز به تجربه قبلی ندارید.</p></details>
                            <details class="aa-faq-item"><summary>دوره‌ها به صورت آنلاین هستند یا حضوری<span aria-hidden="true"></span></summary><p>بسته به دوره، آموزش‌ها می‌توانند آنلاین یا حضوری باشند. نوع برگزاری در صفحه هر دوره مشخص می‌شود.</p></details>
                            <details class="aa-faq-item"><summary>کارگاه‌های حضوری کجا برگزار می‌شوند<span aria-hidden="true"></span></summary><p>محل برگزاری هر کارگاه در صفحه همان کارگاه اعلام می‌شود و قبل از ثبت نام قابل مشاهده است.</p></details>
                            <details class="aa-faq-item"><summary>بعد از ثبت نام چطور به دوره دسترسی پیدا می‌کنم<span aria-hidden="true"></span></summary><p>پس از تکمیل ثبت نام، دوره در حساب کاربری شما فعال می‌شود و از پنل هنرجویی قابل دسترسی است.</p></details>
                            <details class="aa-faq-item"><summary>آیا پس از پایان دوره گواهی دریافت می‌کنم<span aria-hidden="true"></span></summary><p>برای دوره‌هایی که شرایط دریافت گواهی دارند، پس از تکمیل دوره گواهی پایان دوره صادر می‌شود.</p></details>
                        </div>
                    </div>
                </section>

                <!-- 6. INVITE / CTA -->
                <section class="aa-final" aria-labelledby="<?php echo esc_attr( $instance . '-cta' ); ?>" data-reveal>
                    <div class="aa-final-shell"><div class="aa-final-inner">
                        <div class="aa-final-copy">
                            <h2 id="<?php echo esc_attr( $instance . '-cta' ); ?>"><?php echo esc_html( is_user_logged_in() ? 'دوره بعدی‌ات را انتخاب کن' : 'آماده‌ای اولین گره را بزنی' ); ?></h2>
                            <p>دوره‌ها و کارگاه‌های آکادمی اسدزاده برای شروع از پایه طراحی شده‌اند. مسیر مناسب خودت را انتخاب کن و قدم به قدم پیش برو.</p>
                        </div>
                        <div class="aa-final-actions">
                            <a class="aa-btn is-light" href="<?php echo esc_url( is_user_logged_in() ? $courses_url : $account_url ); ?>"><span class="aa-btn-label"><?php echo esc_html( is_user_logged_in() ? 'دیدن دوره‌ها' : 'ورود و ثبت نام' ); ?></span><span class="aa-btn-icon" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M10 3L13 8L10 13" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path d="M13 8H3" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg></span></a>
                            <?php if ( is_user_logged_in() ) : ?>
                                <a class="aa-btn is-ghost" href="<?php echo esc_url( $dashboard_url ); ?>"><span class="aa-btn-label">دوره‌های من</span></a>
                            <?php else : ?>
                                <a class="aa-btn is-ghost" href="<?php echo esc_url( $courses_url ); ?>"><span class="aa-btn-label">کارگاه‌های حضوری</span></a>
                            <?php endif; ?>
                        </div>
                    </div></div>
                </section>

                <script>
                (function(){
                    var root = document.getElementById('<?php echo esc_js( $instance ); ?>');
                    if(!root) return;
                    var prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                    if(prefersReduced){
                        root.querySelectorAll('[data-reveal]').forEach(function(el){ el.classList.add('is-visible'); });
                        return;
                    }
                    var io = new IntersectionObserver(function(entries){
                        entries.forEach(function(entry){
                            if(entry.isIntersecting){
                                entry.target.classList.add('is-visible');
                                io.unobserve(entry.target);
                            }
                        });
                    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
                    root.querySelectorAll('[data-reveal]').forEach(function(el){ io.observe(el); });
                    var faqs = root.querySelectorAll('.aa-faq-item');
                    faqs.forEach(function(f){
                        f.addEventListener('toggle', function(){
                            if(f.open){
                                faqs.forEach(function(other){ if(other!==f) other.open = false; });
                            }
                        });
                    });
                })();
                </script>
            </div>
            <?php
            return ob_get_clean();
        }

        private static function assets( $instance ) {
            ob_start();
            ?>
            <style id="<?php echo esc_attr( $instance ); ?>-css">
                /* Asadzadeh Academy - Brick/Cream + Elementor vars - aa- prefix scoped */
                #<?php echo esc_attr( $instance ); ?>{
                    --aa-primary: var(--e-global-color-primary, var(--wp--preset--color--primary, #8f2e32));
                    --aa-primary-dark: color-mix(in srgb, var(--aa-primary) 82%, #1a1a1a);
                    --aa-primary-soft: color-mix(in srgb, var(--aa-primary) 8%, #fff);
                    --aa-paper: var(--wp--preset--color--base, #fdfcfa);
                    --aa-paper-2: #f2f0eb;
                    --aa-ink: var(--e-global-color-text, var(--wp--preset--color--contrast, #181a18));
                    --aa-muted: #6e756b;
                    --aa-line: #e6e1d6;
                    --aa-line-strong: #d1cbc0;
                    --aa-forest: #1f3128;
                    --aa-forest-2: #2e4a3d;
                    --aa-moss: #eef1ec;
                    --aa-green: #067647;
                    --aa-radius-pill: 999px;
                    --aa-radius-card: 22px;
                    --aa-radius-inner: 14px;
                    --aa-shadow-soft: 0 12px 40px rgba(31,49,40,.08);
                    --aa-shadow-medium: 0 20px 60px rgba(31,49,40,.12);
                    --aa-ease: cubic-bezier(.16,1,.3,1);
                    --aa-ease-spring: cubic-bezier(.32,.72,0,1);
                    color: var(--aa-ink);
                    background: var(--aa-paper);
                    font-family: "Vazirmatn", system-ui, -apple-system, Tahoma, sans-serif;
                    line-height: 1.85;
                    -webkit-font-smoothing: antialiased;
                    text-rendering: optimizeLegibility;
                    width: 100%;
                    max-width: 100%;
                    overflow: clip;
                    padding: 0;
                    direction: rtl;
                }
                #<?php echo esc_attr( $instance ); ?> *{box-sizing:border-box}
                #<?php echo esc_attr( $instance ); ?> a{color:inherit; text-decoration:none}
                #<?php echo esc_attr( $instance ); ?> img{max-width:100%; display:block}
                #<?php echo esc_attr( $instance ); ?> :is(a,button):focus-visible{outline:3px solid color-mix(in srgb, var(--aa-primary) 28%, transparent); outline-offset:3px; border-radius:8px}
                #<?php echo esc_attr( $instance ); ?> .aa-hero,
                #<?php echo esc_attr( $instance ); ?> .aa-metrics,
                #<?php echo esc_attr( $instance ); ?> .aa-section,
                #<?php echo esc_attr( $instance ); ?> .aa-final{width:min(1240px, calc(100% - 32px)); margin-inline:auto}
                #<?php echo esc_attr( $instance ); ?> [data-reveal]{opacity:0; transform:translateY(18px); transition:opacity .7s var(--aa-ease), transform .7s var(--aa-ease); transition-delay:var(--aa-delay, 0ms); will-change:transform, opacity}
                #<?php echo esc_attr( $instance ); ?> [data-reveal].is-visible{opacity:1; transform:translateY(0)}
                @media (prefers-reduced-motion: reduce){#<?php echo esc_attr( $instance ); ?> [data-reveal]{opacity:1; transform:none; transition:none}}
                #<?php echo esc_attr( $instance ); ?> .aa-eyebrow{display:inline-flex; align-items:center; min-height:28px; padding:0 12px; border-radius:var(--aa-radius-pill); background:var(--aa-moss); border:1px solid rgba(31,49,40,.08); color:var(--aa-forest); font-size:11px; font-weight:800; letter-spacing:.06em; margin-bottom:14px}
                #<?php echo esc_attr( $instance ); ?> .aa-system-notice{padding:18px 20px; border:1px solid #f0c9c0; border-radius:14px; background:#fdf0ed; color:#7a2a1f; font-size:13px; width:min(1240px, calc(100% - 32px)); margin:0 auto 20px}
                #<?php echo esc_attr( $instance ); ?> .aa-btn{display:inline-flex; align-items:center; justify-content:center; gap:10px; min-height:48px; padding:0 22px; border-radius:var(--aa-radius-pill); font-size:13px; font-weight:800; line-height:1; border:1px solid transparent; cursor:pointer; transition:transform .25s var(--aa-ease), background .25s var(--aa-ease), color .25s var(--aa-ease), border-color .25s var(--aa-ease), box-shadow .25s var(--aa-ease); white-space:nowrap; text-decoration:none; position:relative; isolation:isolate}
                #<?php echo esc_attr( $instance ); ?> .aa-btn:hover{transform:translateY(-1px)}
                #<?php echo esc_attr( $instance ); ?> .aa-btn:active{transform:translateY(0) scale(.98)}
                #<?php echo esc_attr( $instance ); ?> .aa-btn.is-primary{background:var(--aa-primary); color:#fff; box-shadow:0 10px 24px color-mix(in srgb, var(--aa-primary) 18%, transparent)}
                #<?php echo esc_attr( $instance ); ?> .aa-btn.is-primary:hover{background:var(--aa-primary-dark)}
                #<?php echo esc_attr( $instance ); ?> .aa-btn.is-secondary{background:#fff; color:var(--aa-ink); border-color:var(--aa-line)}
                #<?php echo esc_attr( $instance ); ?> .aa-btn.is-secondary:hover{border-color:var(--aa-line-strong); background:var(--aa-paper-2)}
                #<?php echo esc_attr( $instance ); ?> .aa-btn.is-light{background:#fff; color:var(--aa-forest)}
                #<?php echo esc_attr( $instance ); ?> .aa-btn.is-ghost{background:rgba(255,255,255,.08); color:#fff; border-color:rgba(255,255,255,.14)}
                #<?php echo esc_attr( $instance ); ?> .aa-btn.is-sm{min-height:40px; padding:0 18px; font-size:12px}
                #<?php echo esc_attr( $instance ); ?> .aa-btn-icon{display:grid; place-items:center; width:28px; height:28px; border-radius:50%; background:rgba(255,255,255,.16); flex:0 0 28px; transition:transform .3s var(--aa-ease-spring)}
                #<?php echo esc_attr( $instance ); ?> .aa-btn.is-secondary .aa-btn-icon{background:var(--aa-paper-2)}
                #<?php echo esc_attr( $instance ); ?> .aa-btn:hover .aa-btn-icon{transform:translateX(-2px)}
                #<?php echo esc_attr( $instance ); ?> .aa-text-link{display:inline-flex; align-items:center; gap:6px; color:var(--aa-primary); font-size:13px; font-weight:800}
                #<?php echo esc_attr( $instance ); ?> .aa-text-link:hover{color:var(--aa-primary-dark)}
                /* HERO */
                #<?php echo esc_attr( $instance ); ?> .aa-hero{padding:56px 0 40px; min-height:min(720px, 92dvh); display:grid; align-items:center}
                #<?php echo esc_attr( $instance ); ?> .aa-hero-grid{display:grid; grid-template-columns:minmax(0,1.05fr) minmax(380px,.95fr); gap:clamp(28px,5vw,72px); align-items:center}
                #<?php echo esc_attr( $instance ); ?> .aa-hero-copy h1{margin:0; max-width:520px; font-size:clamp(34px,5vw,56px); line-height:1.15; letter-spacing:-.04em; font-weight:900; text-wrap:balance}
                #<?php echo esc_attr( $instance ); ?> .aa-hero-copy>p{margin:16px 0 0; max-width:500px; color:var(--aa-muted); font-size:16px; line-height:2}
                #<?php echo esc_attr( $instance ); ?> .aa-hero-actions{display:flex; flex-wrap:wrap; gap:10px; margin-top:28px}
                #<?php echo esc_attr( $instance ); ?> .aa-hero-proof{display:flex; gap:20px; margin-top:32px; padding-top:20px; border-top:1px solid var(--aa-line); flex-wrap:wrap}
                #<?php echo esc_attr( $instance ); ?> .aa-proof-item strong{display:block; font-size:13px; font-weight:800}
                #<?php echo esc_attr( $instance ); ?> .aa-proof-item span{display:block; color:var(--aa-muted); font-size:11px; margin-top:2px}
                #<?php echo esc_attr( $instance ); ?> .aa-hero-visual{position:relative; min-height:520px}
                #<?php echo esc_attr( $instance ); ?> .aa-visual-shell{position:absolute; inset:0; padding:8px; border-radius:28px; background:rgba(31,49,40,.06); border:1px solid rgba(31,49,40,.06)}
                #<?php echo esc_attr( $instance ); ?> .aa-visual-inner{position:relative; width:100%; height:100%; overflow:hidden; border-radius:20px; background:var(--aa-paper-2); box-shadow:var(--aa-shadow-soft); isolation:isolate}
                #<?php echo esc_attr( $instance ); ?> .aa-visual-inner img{width:100%; height:100%; object-fit:cover}
                #<?php echo esc_attr( $instance ); ?> .aa-visual-grain{position:absolute; inset:0; background-image:radial-gradient(rgba(0,0,0,.06) 1px, transparent 1px); background-size:22px 22px; opacity:.18; pointer-events:none; mix-blend-mode:multiply}
                #<?php echo esc_attr( $instance ); ?> .aa-float-card{position:absolute; padding:14px 16px; border-radius:16px; background:rgba(255,253,250,.92); backdrop-filter:blur(16px); border:1px solid rgba(255,255,255,.6); box-shadow:0 18px 40px rgba(31,49,40,.12); max-width:220px}
                #<?php echo esc_attr( $instance ); ?> .aa-float-card.is-top{top:18px; left:18px}
                #<?php echo esc_attr( $instance ); ?> .aa-float-card.is-bottom{bottom:18px; right:18px; max-width:240px}
                #<?php echo esc_attr( $instance ); ?> .aa-float-num{display:block; font-size:28px; font-weight:900; line-height:1; color:var(--aa-forest)}
                #<?php echo esc_attr( $instance ); ?> .aa-float-label{display:block; margin-top:6px; font-size:11px; color:var(--aa-muted); line-height:1.6}
                #<?php echo esc_attr( $instance ); ?> .aa-float-quote{display:block; font-size:13px; font-weight:800; line-height:1.5}
                #<?php echo esc_attr( $instance ); ?> .aa-float-sub{display:block; margin-top:4px; font-size:11px; color:var(--aa-muted)}
                /* METRICS */
                #<?php echo esc_attr( $instance ); ?> .aa-metrics{padding:28px 0; border-top:1px solid var(--aa-line); border-bottom:1px solid var(--aa-line)}
                #<?php echo esc_attr( $instance ); ?> .aa-metrics-grid{display:grid; grid-template-columns:repeat(4,1fr); gap:24px}
                #<?php echo esc_attr( $instance ); ?> .aa-metric{display:flex; flex-direction:column; gap:4px; padding-left:20px; border-left:1px solid var(--aa-line)}
                #<?php echo esc_attr( $instance ); ?> .aa-metric:first-child{border-left:none; padding-left:0}
                #<?php echo esc_attr( $instance ); ?> .aa-metric strong{font-size:22px; font-weight:900; line-height:1; letter-spacing:-.02em}
                #<?php echo esc_attr( $instance ); ?> .aa-metric span{color:var(--aa-muted); font-size:11px; line-height:1.6}
                /* SECTIONS */
                #<?php echo esc_attr( $instance ); ?> .aa-section{padding:88px 0}
                #<?php echo esc_attr( $instance ); ?> .aa-section-head{display:flex; justify-content:space-between; align-items:flex-end; gap:24px; margin-bottom:28px; flex-wrap:wrap}
                #<?php echo esc_attr( $instance ); ?> .aa-section-head h2{margin:0; font-size:clamp(26px,3.2vw,40px); line-height:1.25; letter-spacing:-.03em; font-weight:900; text-wrap:balance}
                #<?php echo esc_attr( $instance ); ?> .aa-section-head p{margin:8px 0 0; max-width:480px; color:var(--aa-muted); font-size:14px; line-height:1.8}
                #<?php echo esc_attr( $instance ); ?> .aa-head-stack{display:flex; flex-direction:column; gap:10px}
                /* BENTO CATEGORIES */
                #<?php echo esc_attr( $instance ); ?> .aa-bento{display:grid; grid-template-columns:repeat(12,1fr); gap:16px; grid-auto-rows:220px}
                #<?php echo esc_attr( $instance ); ?> .aa-bento-card{grid-column:span 6; display:block; min-width:0}
                #<?php echo esc_attr( $instance ); ?> .aa-bento-card.is-large{grid-column:span 7; grid-row:span 2}
                #<?php echo esc_attr( $instance ); ?> .aa-bento-card.is-small{grid-column:span 5}
                #<?php echo esc_attr( $instance ); ?> .aa-bento-card.is-wide{grid-column:span 12; grid-row:span 1}
                #<?php echo esc_attr( $instance ); ?> .aa-bento-shell{height:100%; padding:6px; border-radius:var(--aa-radius-card); background:rgba(31,49,40,.04); border:1px solid rgba(31,49,40,.06); transition:transform .35s var(--aa-ease), box-shadow .35s var(--aa-ease)}
                #<?php echo esc_attr( $instance ); ?> .aa-bento-card:hover .aa-bento-shell{transform:translateY(-3px); box-shadow:var(--aa-shadow-medium)}
                #<?php echo esc_attr( $instance ); ?> .aa-bento-inner{height:100%; display:flex; flex-direction:column; overflow:hidden; border-radius:var(--aa-radius-inner); background:#fff; border:1px solid var(--aa-line)}
                #<?php echo esc_attr( $instance ); ?> .aa-bento-media{position:relative; flex:1; min-height:140px; overflow:hidden; background:var(--aa-paper-2)}
                #<?php echo esc_attr( $instance ); ?> .aa-bento-media img{width:100%; height:100%; object-fit:cover; transition:transform .6s var(--aa-ease)}
                #<?php echo esc_attr( $instance ); ?> .aa-bento-card:hover .aa-bento-media img{transform:scale(1.04)}
                #<?php echo esc_attr( $instance ); ?> .aa-bento-body{padding:16px 18px; display:flex; flex-direction:column; gap:6px}
                #<?php echo esc_attr( $instance ); ?> .aa-bento-body h3{margin:0; font-size:16px; font-weight:900; line-height:1.4}
                #<?php echo esc_attr( $instance ); ?> .aa-bento-body p{margin:0; color:var(--aa-muted); font-size:12px; line-height:1.7; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden}
                #<?php echo esc_attr( $instance ); ?> .aa-bento-meta{margin-top:4px; font-size:11px; font-weight:700; color:var(--aa-primary)}
                /* COURSES - 3 columns desktop per mission, equal height, 16:9 image */
                #<?php echo esc_attr( $instance ); ?> .aa-course-grid{display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); gap:18px; align-items:stretch}
                #<?php echo esc_attr( $instance ); ?> .aa-course-card{display:flex; flex-direction:column; min-width:0; height:100%}
                #<?php echo esc_attr( $instance ); ?> .aa-course-shell{height:100%; display:flex; flex-direction:column; padding:6px; border-radius:var(--aa-radius-card); background:rgba(31,49,40,.04); border:1px solid rgba(31,49,40,.05); transition:transform .35s var(--aa-ease), box-shadow .35s var(--aa-ease)}
                #<?php echo esc_attr( $instance ); ?> .aa-course-card:hover .aa-course-shell{transform:translateY(-3px); box-shadow:var(--aa-shadow-medium)}
                #<?php echo esc_attr( $instance ); ?> .aa-course-inner{flex:1; display:flex; flex-direction:column; overflow:hidden; border-radius:var(--aa-radius-inner); background:#fff; border:1px solid var(--aa-line)}
                #<?php echo esc_attr( $instance ); ?> .aa-course-media{position:relative; aspect-ratio:16/9; overflow:hidden; background:var(--aa-paper-2); flex:0 0 auto}
                #<?php echo esc_attr( $instance ); ?> .aa-course-media img{width:100%; height:100%; object-fit:cover; transition:transform .6s var(--aa-ease)}
                #<?php echo esc_attr( $instance ); ?> .aa-course-card:hover .aa-course-media img{transform:scale(1.04)}
                #<?php echo esc_attr( $instance ); ?> .aa-course-badge{position:absolute; top:12px; right:12px; min-height:26px; padding:0 10px; display:inline-flex; align-items:center; border-radius:var(--aa-radius-pill); background:rgba(255,255,255,.92); backdrop-filter:blur(8px); border:1px solid rgba(255,255,255,.6); font-size:10px; font-weight:800; color:var(--aa-forest)}
                #<?php echo esc_attr( $instance ); ?> .aa-course-badge.is-owned{background:rgba(6,118,71,.9); color:#fff; border-color:rgba(255,255,255,.5)}
                #<?php echo esc_attr( $instance ); ?> .aa-course-body{flex:1; display:flex; flex-direction:column; padding:16px; min-height:0}
                #<?php echo esc_attr( $instance ); ?> .aa-course-top{display:flex; justify-content:space-between; gap:12px; align-items:flex-start}
                #<?php echo esc_attr( $instance ); ?> .aa-course-top h3{margin:0; flex:1; font-size:15px; font-weight:900; line-height:1.6; min-height:48px}
                #<?php echo esc_attr( $instance ); ?> .aa-course-cat{flex:0 0 auto; font-size:10px; font-weight:700; color:var(--aa-muted); background:var(--aa-paper-2); border:1px solid var(--aa-line); padding:4px 8px; border-radius:var(--aa-radius-pill); white-space:nowrap}
                #<?php echo esc_attr( $instance ); ?> .aa-course-teacher{margin:6px 0 0; font-size:11px; color:var(--aa-muted)}
                #<?php echo esc_attr( $instance ); ?> .aa-course-meta{display:flex; gap:8px; margin-top:8px; color:#8b8f8a; font-size:10px; flex-wrap:wrap}
                #<?php echo esc_attr( $instance ); ?> .aa-course-meta span+span:before{content:"•"; margin-left:8px; color:var(--aa-line-strong)}
                #<?php echo esc_attr( $instance ); ?> .aa-course-price{margin:12px 0 14px; font-size:14px; font-weight:900; color:var(--aa-primary); min-height:24px}
                #<?php echo esc_attr( $instance ); ?> .aa-course-price del{color:#a8a9a3; font-size:11px; margin-left:6px}
                #<?php echo esc_attr( $instance ); ?> .aa-course-price ins{text-decoration:none}
                #<?php echo esc_attr( $instance ); ?> .aa-price-free{color:var(--aa-green)}
                #<?php echo esc_attr( $instance ); ?> .aa-progress{margin:12px 0 14px}
                #<?php echo esc_attr( $instance ); ?> .aa-progress-head{display:flex; justify-content:space-between; font-size:10px; color:var(--aa-muted); margin-bottom:6px}
                #<?php echo esc_attr( $instance ); ?> .aa-progress-head strong{color:var(--aa-primary)}
                #<?php echo esc_attr( $instance ); ?> .aa-progress-track{display:block; height:6px; border-radius:var(--aa-radius-pill); background:#eee9df; overflow:hidden}
                #<?php echo esc_attr( $instance ); ?> .aa-progress-track i{display:block; height:100%; background:var(--aa-primary); border-radius:inherit}
                #<?php echo esc_attr( $instance ); ?> .aa-progress-meta{margin-top:6px; color:var(--aa-muted); font-size:10px}
                #<?php echo esc_attr( $instance ); ?> .aa-btn.is-course{margin-top:auto; width:100%; background:var(--aa-forest); color:#fff; border-color:var(--aa-forest)}
                #<?php echo esc_attr( $instance ); ?> .aa-btn.is-course.is-owned{background:var(--aa-paper-2); color:var(--aa-forest); border-color:var(--aa-line)}
                #<?php echo esc_attr( $instance ); ?> .aa-empty{padding:24px; border:1px dashed var(--aa-line); border-radius:20px; background:var(--aa-paper-2)}
                #<?php echo esc_attr( $instance ); ?> .aa-empty-shell{max-width:420px}
                #<?php echo esc_attr( $instance ); ?> .aa-empty-shell h3{margin:0; font-size:16px; font-weight:900}
                #<?php echo esc_attr( $instance ); ?> .aa-empty-shell p{margin:6px 0 14px; color:var(--aa-muted); font-size:13px}
                /* MASTER */
                #<?php echo esc_attr( $instance ); ?> .aa-master-grid{display:grid; grid-template-columns:.9fr 1.1fr; gap:clamp(32px,6vw,88px); align-items:center}
                #<?php echo esc_attr( $instance ); ?> .aa-master-shell{padding:8px; border-radius:28px; background:rgba(31,49,40,.05); border:1px solid rgba(31,49,40,.06)}
                #<?php echo esc_attr( $instance ); ?> .aa-master-inner{border-radius:20px; overflow:hidden; aspect-ratio:4/5; background:var(--aa-paper-2)}
                #<?php echo esc_attr( $instance ); ?> .aa-master-inner img{width:100%; height:100%; object-fit:cover}
                #<?php echo esc_attr( $instance ); ?> .aa-master-quote{margin-top:14px; padding:16px; border-radius:14px; background:var(--aa-forest); color:#fff}
                #<?php echo esc_attr( $instance ); ?> .aa-master-quote p{margin:0; font-size:14px; font-weight:800; line-height:1.6; text-wrap:balance}
                #<?php echo esc_attr( $instance ); ?> .aa-master-copy h2{margin:0; font-size:clamp(26px,3vw,38px); font-weight:900; letter-spacing:-.03em}
                #<?php echo esc_attr( $instance ); ?> .aa-master-role{display:block; margin-top:8px; font-size:12px; font-weight:700; color:var(--aa-primary)}
                #<?php echo esc_attr( $instance ); ?> .aa-master-copy>p{margin:16px 0 0; color:var(--aa-muted); font-size:15px; line-height:2; max-width:54ch}
                #<?php echo esc_attr( $instance ); ?> .aa-master-stats{display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-top:24px; padding-top:20px; border-top:1px solid var(--aa-line)}
                #<?php echo esc_attr( $instance ); ?> .aa-master-stats strong{display:block; font-size:15px; font-weight:900}
                #<?php echo esc_attr( $instance ); ?> .aa-master-stats span{display:block; margin-top:4px; font-size:11px; color:var(--aa-muted); line-height:1.6}
                /* WORKSHOPS */
                #<?php echo esc_attr( $instance ); ?> .aa-workshop-grid{display:grid; grid-template-columns:1.2fr .8fr; gap:16px}
                #<?php echo esc_attr( $instance ); ?> .aa-workshop-card .aa-workshop-shell{height:100%; padding:6px; border-radius:var(--aa-radius-card); background:rgba(31,49,40,.04); border:1px solid rgba(31,49,40,.05)}
                #<?php echo esc_attr( $instance ); ?> .aa-workshop-inner{height:100%; border-radius:var(--aa-radius-inner); overflow:hidden; background:#fff; border:1px solid var(--aa-line); display:flex; flex-direction:column}
                #<?php echo esc_attr( $instance ); ?> .aa-workshop-media{aspect-ratio:16/9; overflow:hidden; background:var(--aa-paper-2)}
                #<?php echo esc_attr( $instance ); ?> .aa-workshop-media img{width:100%; height:100%; object-fit:cover}
                #<?php echo esc_attr( $instance ); ?> .aa-workshop-body{padding:18px; display:flex; flex-direction:column; gap:10px; flex:1}
                #<?php echo esc_attr( $instance ); ?> .aa-workshop-tag{display:inline-flex; align-self:flex-start; min-height:24px; padding:0 10px; border-radius:var(--aa-radius-pill); background:var(--aa-moss); border:1px solid rgba(31,49,40,.08); font-size:10px; font-weight:800; color:var(--aa-forest)}
                #<?php echo esc_attr( $instance ); ?> .aa-workshop-body h3{margin:0; font-size:16px; font-weight:900; line-height:1.5}
                #<?php echo esc_attr( $instance ); ?> .aa-workshop-body p{margin:0; color:var(--aa-muted); font-size:12px; line-height:1.8}
                #<?php echo esc_attr( $instance ); ?> .aa-workshop-meta{display:flex; gap:10px; font-size:11px; color:var(--aa-muted)}
                #<?php echo esc_attr( $instance ); ?> .aa-workshop-stack{display:grid; gap:16px}
                /* WHY */
                #<?php echo esc_attr( $instance ); ?> .aa-why-grid{display:grid; grid-template-columns:.8fr 1.2fr; gap:clamp(28px,5vw,72px)}
                #<?php echo esc_attr( $instance ); ?> .aa-why-head h2{margin:0; font-size:clamp(26px,3vw,38px); font-weight:900; letter-spacing:-.03em}
                #<?php echo esc_attr( $instance ); ?> .aa-why-head p{margin:10px 0 0; color:var(--aa-muted); font-size:14px; line-height:1.8; max-width:36ch}
                #<?php echo esc_attr( $instance ); ?> .aa-why-list{border-top:1px solid var(--aa-line)}
                #<?php echo esc_attr( $instance ); ?> .aa-why-item{display:grid; grid-template-columns:48px 1fr; gap:16px; padding:20px 0; border-bottom:1px solid var(--aa-line)}
                #<?php echo esc_attr( $instance ); ?> .aa-why-item:last-child{border-bottom:none}
                #<?php echo esc_attr( $instance ); ?> .aa-why-num{font-size:12px; font-weight:800; color:var(--aa-primary); padding-top:2px}
                #<?php echo esc_attr( $instance ); ?> .aa-why-item h3{margin:0; font-size:14px; font-weight:800}
                #<?php echo esc_attr( $instance ); ?> .aa-why-item p{margin:6px 0 0; color:var(--aa-muted); font-size:12px; line-height:1.8}
                /* TESTIMONIALS */
                #<?php echo esc_attr( $instance ); ?> .aa-test-grid{display:grid; grid-template-columns:repeat(12,1fr); gap:16px; grid-auto-rows:minmax(180px, auto)}
                #<?php echo esc_attr( $instance ); ?> .aa-test-card{grid-column:span 4; padding:20px; border-radius:20px; background:#fff; border:1px solid var(--aa-line); display:flex; flex-direction:column; justify-content:space-between}
                #<?php echo esc_attr( $instance ); ?> .aa-test-card.is-large{grid-column:span 8; background:var(--aa-forest); color:#fff; border-color:var(--aa-forest)}
                #<?php echo esc_attr( $instance ); ?> .aa-test-card p{margin:0; font-size:14px; line-height:1.9; text-wrap:pretty}
                #<?php echo esc_attr( $instance ); ?> .aa-test-card.is-large p{font-size:16px; font-weight:600}
                #<?php echo esc_attr( $instance ); ?> .aa-test-foot{display:flex; gap:12px; align-items:center; margin-top:18px}
                #<?php echo esc_attr( $instance ); ?> .aa-test-foot img{width:36px; height:36px; border-radius:50%; object-fit:cover; background:var(--aa-paper-2)}
                #<?php echo esc_attr( $instance ); ?> .aa-test-foot strong{display:block; font-size:12px; font-weight:800}
                #<?php echo esc_attr( $instance ); ?> .aa-test-foot span{display:block; font-size:10px; opacity:.7; margin-top:2px}
                #<?php echo esc_attr( $instance ); ?> .aa-test-card.is-large .aa-test-foot span{color:rgba(255,255,255,.7)}
                /* FAQ */
                #<?php echo esc_attr( $instance ); ?> .aa-faq-grid{display:grid; grid-template-columns:.8fr 1.2fr; gap:clamp(28px,5vw,72px)}
                #<?php echo esc_attr( $instance ); ?> .aa-faq-head h2{margin:0; font-size:clamp(26px,3vw,38px); font-weight:900; letter-spacing:-.03em}
                #<?php echo esc_attr( $instance ); ?> .aa-faq-head p{margin:10px 0 0; color:var(--aa-muted); font-size:14px; line-height:1.8; max-width:36ch}
                #<?php echo esc_attr( $instance ); ?> .aa-faq-list{border-top:1px solid var(--aa-line)}
                #<?php echo esc_attr( $instance ); ?> .aa-faq-item{border-bottom:1px solid var(--aa-line); padding:0}
                #<?php echo esc_attr( $instance ); ?> .aa-faq-item summary{list-style:none; cursor:pointer; display:flex; justify-content:space-between; align-items:center; gap:16px; padding:18px 0; font-size:14px; font-weight:700; line-height:1.6}
                #<?php echo esc_attr( $instance ); ?> .aa-faq-item summary::-webkit-details-marker{display:none}
                #<?php echo esc_attr( $instance ); ?> .aa-faq-item summary span{width:28px; height:28px; flex:0 0 28px; border-radius:50%; border:1px solid var(--aa-line); display:grid; place-items:center; position:relative; transition:transform .3s var(--aa-ease), background .3s var(--aa-ease)}
                #<?php echo esc_attr( $instance ); ?> .aa-faq-item summary span:before,
                #<?php echo esc_attr( $instance ); ?> .aa-faq-item summary span:after{content:""; position:absolute; width:10px; height:1.2px; background:var(--aa-ink); border-radius:1px; transition:transform .3s var(--aa-ease)}
                #<?php echo esc_attr( $instance ); ?> .aa-faq-item summary span:after{transform:rotate(90deg)}
                #<?php echo esc_attr( $instance ); ?> .aa-faq-item[open] summary span{background:var(--aa-forest); border-color:var(--aa-forest)}
                #<?php echo esc_attr( $instance ); ?> .aa-faq-item[open] summary span:before,
                #<?php echo esc_attr( $instance ); ?> .aa-faq-item[open] summary span:after{background:#fff}
                #<?php echo esc_attr( $instance ); ?> .aa-faq-item[open] summary span:after{transform:rotate(0deg)}
                #<?php echo esc_attr( $instance ); ?> .aa-faq-item p{margin:0 0 18px; color:var(--aa-muted); font-size:13px; line-height:1.9; max-width:60ch}
                /* FINAL CTA */
                #<?php echo esc_attr( $instance ); ?> .aa-final{padding-bottom:88px}
                #<?php echo esc_attr( $instance ); ?> .aa-final-shell{padding:8px; border-radius:32px; background:rgba(31,49,40,.06); border:1px solid rgba(31,49,40,.06)}
                #<?php echo esc_attr( $instance ); ?> .aa-final-inner{display:flex; justify-content:space-between; align-items:center; gap:24px; padding:36px 40px; border-radius:24px; background:var(--aa-forest); color:#fff; position:relative; overflow:hidden; isolation:isolate}
                #<?php echo esc_attr( $instance ); ?> .aa-final-inner:before{content:""; position:absolute; inset:0; background:radial-gradient(600px 300px at 80% -20%, color-mix(in srgb, var(--aa-primary) 22%, transparent), transparent 60%), radial-gradient(500px 400px at -10% 120%, rgba(238,241,236,.08), transparent 60%); pointer-events:none}
                #<?php echo esc_attr( $instance ); ?> .aa-final-copy{position:relative; max-width:520px}
                #<?php echo esc_attr( $instance ); ?> .aa-final-copy h2{margin:0; font-size:clamp(24px,3vw,34px); font-weight:900; line-height:1.25; letter-spacing:-.02em; text-wrap:balance}
                #<?php echo esc_attr( $instance ); ?> .aa-final-copy p{margin:10px 0 0; font-size:13px; line-height:1.8; color:rgba(255,255,255,.72); max-width:48ch}
                #<?php echo esc_attr( $instance ); ?> .aa-final-actions{position:relative; display:flex; gap:10px; flex-wrap:wrap}
                /* Elementor compat */
                #<?php echo esc_attr( $instance ); ?>.aa-elementor-compatible{margin:0 auto}
                .elementor-widget-container #<?php echo esc_attr( $instance ); ?>{width:100%}
                .elementor #<?php echo esc_attr( $instance ); ?> .aa-hero,
                .elementor #<?php echo esc_attr( $instance ); ?> .aa-metrics,
                .elementor #<?php echo esc_attr( $instance ); ?> .aa-section,
                .elementor #<?php echo esc_attr( $instance ); ?> .aa-final{width:100%; max-width:100%}
                /* Responsive */
                @media (max-width: 1024px){
                    #<?php echo esc_attr( $instance ); ?> .aa-hero-grid{grid-template-columns:1fr; gap:32px}
                    #<?php echo esc_attr( $instance ); ?> .aa-hero-visual{min-height:420px}
                    #<?php echo esc_attr( $instance ); ?> .aa-master-grid,
                    #<?php echo esc_attr( $instance ); ?> .aa-why-grid,
                    #<?php echo esc_attr( $instance ); ?> .aa-faq-grid{grid-template-columns:1fr; gap:28px}
                    #<?php echo esc_attr( $instance ); ?> .aa-workshop-grid{grid-template-columns:1fr}
                    #<?php echo esc_attr( $instance ); ?> .aa-bento{grid-auto-rows:200px}
                    #<?php echo esc_attr( $instance ); ?> .aa-bento-card,
                    #<?php echo esc_attr( $instance ); ?> .aa-bento-card.is-large,
                    #<?php echo esc_attr( $instance ); ?> .aa-bento-card.is-small,
                    #<?php echo esc_attr( $instance ); ?> .aa-bento-card.is-wide{grid-column:span 6}
                    #<?php echo esc_attr( $instance ); ?> .aa-course-grid{grid-template-columns:repeat(2, minmax(0,1fr))}
                    #<?php echo esc_attr( $instance ); ?> .aa-test-card,
                    #<?php echo esc_attr( $instance ); ?> .aa-test-card.is-large{grid-column:span 6}
                    #<?php echo esc_attr( $instance ); ?> .aa-metrics-grid{grid-template-columns:repeat(2,1fr)}
                }
                @media (max-width: 640px){
                    #<?php echo esc_attr( $instance ); ?> .aa-hero,
                    #<?php echo esc_attr( $instance ); ?> .aa-metrics,
                    #<?php echo esc_attr( $instance ); ?> .aa-section,
                    #<?php echo esc_attr( $instance ); ?> .aa-final{width:min(100% - 24px, 560px)}
                    #<?php echo esc_attr( $instance ); ?> .aa-hero{padding-top:28px; min-height:0}
                    #<?php echo esc_attr( $instance ); ?> .aa-hero-visual{min-height:360px}
                    #<?php echo esc_attr( $instance ); ?> .aa-section{padding:56px 0}
                    #<?php echo esc_attr( $instance ); ?> .aa-bento{grid-template-columns:1fr; grid-auto-rows:auto}
                    #<?php echo esc_attr( $instance ); ?> .aa-bento-card,
                    #<?php echo esc_attr( $instance ); ?> .aa-bento-card.is-large,
                    #<?php echo esc_attr( $instance ); ?> .aa-bento-card.is-small,
                    #<?php echo esc_attr( $instance ); ?> .aa-bento-card.is-wide{grid-column:span 1}
                    #<?php echo esc_attr( $instance ); ?> .aa-course-grid{grid-template-columns:1fr}
                    #<?php echo esc_attr( $instance ); ?> .aa-test-grid{grid-template-columns:1fr}
                    #<?php echo esc_attr( $instance ); ?> .aa-test-card,
                    #<?php echo esc_attr( $instance ); ?> .aa-test-card.is-large{grid-column:span 1}
                    #<?php echo esc_attr( $instance ); ?> .aa-metrics-grid{grid-template-columns:1fr 1fr; gap:16px}
                    #<?php echo esc_attr( $instance ); ?> .aa-metric{border-left:none; padding-left:0; border-top:1px solid var(--aa-line); padding-top:12px}
                    #<?php echo esc_attr( $instance ); ?> .aa-metric:first-child{border-top:none; padding-top:0}
                    #<?php echo esc_attr( $instance ); ?> .aa-final-inner{flex-direction:column; align-items:flex-start; padding:28px 22px}
                    #<?php echo esc_attr( $instance ); ?> .aa-final-actions{width:100%}
                    #<?php echo esc_attr( $instance ); ?> .aa-final-actions .aa-btn{flex:1}
                    #<?php echo esc_attr( $instance ); ?> .aa-hero-proof{flex-direction:column; gap:12px}
                }
                @media (max-width: 360px){
                    #<?php echo esc_attr( $instance ); ?> .aa-hero-copy h1{font-size:28px}
                    #<?php echo esc_attr( $instance ); ?> .aa-course-body{padding:14px}
                }
            </style>
            <?php
            return ob_get_clean();
        }
    }

    Asadzadeh_Academy_Home::init();
    if ( ! class_exists( 'Luxury_Academy_Homepage' ) ) {
        class Luxury_Academy_Homepage extends Asadzadeh_Academy_Home {}
    }
}
