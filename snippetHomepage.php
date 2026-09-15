/**
 * Luxury Academy Homepage - Tutor LMS + WooCommerce
 *
 * Shortcode:
 * [luxury_academy_home]
 * [luxury_academy_home courses="6" category=""]
 *
 * Paste into WPCode without the PHP opening tag and run everywhere.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Luxury_Academy_Homepage' ) ) {

    final class Luxury_Academy_Homepage {

        const SHORTCODE = 'luxury_academy_home';

        private static $instance = 0;

        public static function init() {
            add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
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

            foreach ( array( 'course-category', 'course_cat', 'tutor_course_category' ) as $taxonomy ) {
                if ( taxonomy_exists( $taxonomy ) ) {
                    return $taxonomy;
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
            $map        = array();
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
                return array(
                    'percent'   => 0,
                    'completed' => 0,
                    'total'     => 0,
                );
            }

            $stats = tutor_utils()->get_course_completed_percent( $course_id, get_current_user_id(), true );
            if ( ! is_array( $stats ) ) {
                return array(
                    'percent'   => max( 0, min( 100, (int) $stats ) ),
                    'completed' => 0,
                    'total'     => 0,
                );
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

            $meta_keys = 'level' === $kind
                ? array( '_tutor_course_level', 'course_level' )
                : array( '_tutor_course_duration', 'course_duration' );

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
            $products      = self::product_map( $course_ids );
            $enrolled_ids  = self::enrolled_ids();
            $course_rows   = array();
            $author_ids    = array();

            foreach ( $course_ids as $course_id ) {
                $author_ids[] = (int) get_post_field( 'post_author', $course_id );
            }

            $authors = array();
            foreach ( array_unique( $author_ids ) as $author_id ) {
                $authors[ $author_id ] = get_the_author_meta( 'display_name', $author_id );
            }

            foreach ( $course_ids as $course_id ) {
                $product    = $products[ $course_id ] ?? false;
                $progress   = self::progress( $course_id );
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
                return '<span class="lah-price-free">رایگان</span>';
            }

            return wp_kses_post( $product->get_price_html() );
        }

        private static function course_card( $row, $index ) {
            $progress = $row['progress'];
            $status   = '';
            $label    = '';
            $url      = $row['action_url'];

            if ( $row['is_enrolled'] ) {
                if ( $progress['percent'] >= 100 ) {
                    $status = 'تکمیل‌شده';
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
                $status = 'شروع رایگان';
                $label  = is_user_logged_in() ? 'شروع دوره' : 'ورود برای ثبت‌نام';
                $url    = is_user_logged_in() ? $row['url'] : ( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url( $row['url'] ) );
            } else {
                $status = 'ثبت‌نام دوره';
                $label  = 'افزودن به سبد خرید';
                $url    = $row['product'] ? add_query_arg( 'add-to-cart', $row['product']->get_id(), home_url( '/' ) ) : $row['url'];
            }
            ?>
            <article class="lah-course-card" style="--lah-delay: <?php echo esc_attr( min( $index * 55, 330 ) ); ?>ms">
                <a class="lah-course-image" href="<?php echo esc_url( $row['url'] ); ?>" aria-label="<?php echo esc_attr( 'مشاهده ' . $row['title'] ); ?>">
                    <?php if ( $row['image'] ) : ?>
                        <img src="<?php echo esc_url( $row['image'] ); ?>" alt="<?php echo esc_attr( $row['title'] ); ?>" loading="lazy">
                    <?php else : ?>
                        <span class="lah-image-fallback" aria-hidden="true">آ</span>
                    <?php endif; ?>
                    <span class="lah-course-status"><?php echo esc_html( $status ); ?></span>
                </a>
                <div class="lah-course-content">
                    <h3><a href="<?php echo esc_url( $row['url'] ); ?>"><?php echo esc_html( $row['title'] ); ?></a></h3>
                    <p class="lah-course-author"><?php echo esc_html( $row['author'] ? 'مدرس: ' . $row['author'] : 'دوره آموزشی آکادمی' ); ?></p>
                    <div class="lah-course-meta">
                        <?php if ( $row['terms'] ) : ?><span><?php echo esc_html( implode( '، ', $row['terms'] ) ); ?></span><?php endif; ?>
                        <span><?php echo esc_html( self::course_detail( $row['id'], 'level' ) ?: 'همه سطوح' ); ?></span>
                        <?php $duration = self::course_detail( $row['id'], 'duration' ); ?>
                        <?php if ( $duration ) : ?><span><?php echo esc_html( $duration ); ?></span><?php endif; ?>
                    </div>
                    <?php if ( $row['is_enrolled'] ) : ?>
                        <div class="lah-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( $progress['percent'] ); ?>">
                            <div class="lah-progress-head"><span>پیشرفت شما</span><strong><?php echo esc_html( $progress['percent'] ); ?>٪</strong></div>
                            <span class="lah-progress-track"><i style="width: <?php echo esc_attr( $progress['percent'] ); ?>%"></i></span>
                        </div>
                    <?php elseif ( ! $row['is_free'] ) : ?>
                        <div class="lah-course-price"><?php echo self::price_html( $row['product'] ); ?></div>
                    <?php else : ?>
                        <div class="lah-course-price"><span class="lah-price-free">رایگان</span></div>
                    <?php endif; ?>
                    <a class="lah-course-action <?php echo $row['is_enrolled'] ? 'is-owned' : ''; ?>" href="<?php echo esc_url( $url ); ?>">
                        <?php echo esc_html( $label ); ?><span aria-hidden="true">←</span>
                    </a>
                </div>
            </article>
            <?php
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
                    'number'     => 6,
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

        public static function render( $atts = array() ) {
            $atts = shortcode_atts(
                array(
                    'courses'  => 6,
                    'category' => '',
                ),
                $atts,
                self::SHORTCODE
            );

            $limit    = max( 1, min( 12, absint( $atts['courses'] ) ?: 6 ) );
            $category = sanitize_text_field( (string) $atts['category'] );
            $instance = 'lah-home-' . (++self::$instance);
            $course_rows = self::tutor_available() ? self::course_data( self::course_ids( $limit, $category ) ) : array();
            $categories  = self::tutor_available() ? self::learning_categories() : array();
            $active      = is_user_logged_in() && self::tutor_available() ? self::active_courses( 3 ) : array();
            $account_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url();
            $courses_url = home_url( '/' );
            if ( self::tutor_available() && is_object( tutor_utils() ) && method_exists( tutor_utils(), 'get_courses_page_url' ) ) {
                $courses_url = tutor_utils()->get_courses_page_url();
            }
            $dashboard_url = function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'my-courses' ) : home_url( '/my-account/my-courses/' );

            ob_start();
            ?>
            <div id="<?php echo esc_attr( $instance ); ?>" class="lah-homepage" dir="rtl">
                <?php echo self::assets( $instance ); ?>
                <?php if ( ! self::tutor_available() || ! self::woocommerce_available() ) : ?>
                    <div class="lah-system-notice" role="alert">برای نمایش صفحه اصلی آموزشی، Tutor LMS و WooCommerce باید فعال باشند.</div>
                <?php endif; ?>

                <section class="lah-hero" aria-labelledby="<?php echo esc_attr( $instance . '-title' ); ?>">
                    <div class="lah-hero-copy">
                        <span class="lah-eyebrow">آکادمی آموزش تخصصی</span>
                        <h1 id="<?php echo esc_attr( $instance . '-title' ); ?>">یادگیری را به یک مسیر روشن تبدیل کن</h1>
                        <p>دوره‌های کاربردی را انتخاب کن، قدم‌به‌قدم پیش برو و نتیجه یادگیری‌ات را در پنل شخصی دنبال کن.</p>
                        <div class="lah-hero-actions">
                            <a class="lah-button is-primary" href="<?php echo esc_url( $courses_url ); ?>">مشاهده دوره‌ها</a>
                            <a class="lah-button is-secondary" href="<?php echo esc_url( is_user_logged_in() ? $dashboard_url : $account_url ); ?>"><?php echo esc_html( is_user_logged_in() ? 'ادامه یادگیری' : 'ورود به پیشخوان' ); ?></a>
                        </div>
                    </div>
                    <div class="lah-hero-visual" aria-hidden="true">
                        <span class="lah-hero-ring"></span>
                        <span class="lah-hero-number">مسیر<br>یادگیری</span>
                        <span class="lah-hero-note">از انتخاب دوره تا پیشرفت واقعی</span>
                    </div>
                </section>

                <section class="lah-section lah-courses-section" aria-labelledby="<?php echo esc_attr( $instance . '-courses' ); ?>">
                    <div class="lah-section-head">
                        <div><span class="lah-eyebrow">انتخاب‌های آموزشی</span><h2 id="<?php echo esc_attr( $instance . '-courses' ); ?>">دوره‌هایی برای قدم بعدی</h2></div>
                        <a href="<?php echo esc_url( $courses_url ); ?>">همه دوره‌ها <span aria-hidden="true">←</span></a>
                    </div>
                    <?php if ( $course_rows ) : ?>
                        <div class="lah-course-grid"><?php foreach ( $course_rows as $index => $row ) : self::course_card( $row, $index ); endforeach; ?></div>
                    <?php elseif ( ! self::tutor_available() ) : ?>
                        <p class="lah-empty-state">Tutor LMS در دسترس نیست. پس از فعال‌سازی افزونه، دوره‌ها در این بخش نمایش داده می‌شوند.</p>
                    <?php else : ?>
                        <p class="lah-empty-state">هنوز دوره‌ای برای نمایش در این مسیر منتشر نشده است.</p>
                    <?php endif; ?>
                </section>

                <?php if ( $categories ) : ?>
                    <section class="lah-section lah-paths-section" aria-labelledby="<?php echo esc_attr( $instance . '-paths' ); ?>">
                        <div class="lah-section-head"><div><span class="lah-eyebrow">مسیرهای یادگیری</span><h2 id="<?php echo esc_attr( $instance . '-paths' ); ?>">موضوعی را انتخاب کن و جلو برو</h2></div></div>
                        <div class="lah-category-grid">
                            <?php foreach ( $categories as $term ) : ?>
                                <a class="lah-category" href="<?php echo esc_url( get_term_link( $term ) ); ?>"><strong><?php echo esc_html( $term->name ); ?></strong><span><?php echo esc_html( $term->count ); ?> دوره <b aria-hidden="true">←</b></span></a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <section class="lah-benefits" aria-labelledby="<?php echo esc_attr( $instance . '-benefits' ); ?>">
                    <div><span class="lah-eyebrow">تجربه آکادمی</span><h2 id="<?php echo esc_attr( $instance . '-benefits' ); ?>">همه‌چیز برای ادامه دادن آماده است</h2></div>
                    <div class="lah-benefit-list">
                        <article><strong>۰۱</strong><h3>مسیر روشن</h3><p>دوره‌ها و سرفصل‌ها در Tutor LMS منظم می‌شوند تا بدانید قدم بعدی چیست.</p></article>
                        <article><strong>۰۲</strong><h3>پیشرفت قابل مشاهده</h3><p>درصد پیشرفت و بخش‌های تکمیل‌شده را از پنل شخصی‌تان دنبال کنید.</p></article>
                        <article><strong>۰۳</strong><h3>دسترسی در حساب شما</h3><p>بعد از خرید موفق، دسترسی آموزشی به حساب شما متصل می‌شود.</p></article>
                    </div>
                </section>

                <?php if ( $active ) : ?>
                    <section class="lah-section lah-continue-section" aria-labelledby="<?php echo esc_attr( $instance . '-continue' ); ?>">
                        <div class="lah-section-head"><div><span class="lah-eyebrow">برای شما</span><h2 id="<?php echo esc_attr( $instance . '-continue' ); ?>">یادگیری را ادامه بده</h2></div><a href="<?php echo esc_url( $dashboard_url ); ?>">دوره‌های من <span aria-hidden="true">←</span></a></div>
                        <div class="lah-continue-grid"><?php foreach ( $active as $index => $row ) : self::course_card( $row, $index ); endforeach; ?></div>
                    </section>
                <?php endif; ?>

                <section class="lah-final-cta" aria-labelledby="<?php echo esc_attr( $instance . '-cta' ); ?>">
                    <div><span class="lah-eyebrow">شروع مسیر</span><h2 id="<?php echo esc_attr( $instance . '-cta' ); ?>"><?php echo esc_html( is_user_logged_in() ? 'دوره بعدی‌ات را انتخاب کن' : 'مسیر یادگیری‌ات را شروع کن' ); ?></h2></div>
                    <a class="lah-button is-light" href="<?php echo esc_url( is_user_logged_in() ? $courses_url : $account_url ); ?>"><?php echo esc_html( is_user_logged_in() ? 'دیدن دوره‌ها' : 'ورود و ثبت‌نام' ); ?></a>
                </section>
            </div>
            <?php
            return ob_get_clean();
        }

        private static function assets( $instance ) {
            ob_start();
            ?>
            <style>
                #<?php echo esc_attr( $instance ); ?>{--lah-brick:#8f2e32;--lah-brick-dark:#672126;--lah-cream:#f7f2ea;--lah-paper:#fffdfa;--lah-ink:#2d2220;--lah-muted:#776b66;--lah-line:#e7ddd4;--lah-green:#176b4d;color:var(--lah-ink);font-family:"Vazirmatn",Tahoma,sans-serif;line-height:1.8;background:var(--lah-paper);padding:clamp(18px,4vw,48px) 0 0}
                #<?php echo esc_attr( $instance ); ?> *{box-sizing:border-box}#<?php echo esc_attr( $instance ); ?> a{color:inherit}#<?php echo esc_attr( $instance ); ?> .lah-hero,#<?php echo esc_attr( $instance ); ?> .lah-section,#<?php echo esc_attr( $instance ); ?> .lah-benefits,#<?php echo esc_attr( $instance ); ?> .lah-final-cta{width:min(1160px,calc(100% - 32px));margin-right:auto;margin-left:auto}
                #<?php echo esc_attr( $instance ); ?> .lah-hero{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(300px,.95fr);gap:clamp(32px,7vw,96px);align-items:center;min-height:520px;padding:40px 0 72px}
                #<?php echo esc_attr( $instance ); ?> .lah-eyebrow{display:block;margin-bottom:9px;color:var(--lah-brick);font-size:11px;font-weight:900;letter-spacing:.08em}
                #<?php echo esc_attr( $instance ); ?> h1,#<?php echo esc_attr( $instance ); ?> h2,#<?php echo esc_attr( $instance ); ?> h3{margin:0;font-weight:900;letter-spacing:-.035em}#<?php echo esc_attr( $instance ); ?> h1{max-width:640px;font-size:clamp(34px,5.5vw,68px);line-height:1.2}#<?php echo esc_attr( $instance ); ?> .lah-hero-copy>p{max-width:510px;margin:18px 0 0;color:var(--lah-muted);font-size:16px;line-height:2}
                #<?php echo esc_attr( $instance ); ?> .lah-hero-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:28px}.lah-button{display:inline-flex;align-items:center;justify-content:center;min-height:48px;padding:0 21px;border-radius:10px;text-decoration:none;font-size:12px;font-weight:900;transition:transform .2s ease,background .2s ease,color .2s ease}.lah-button:hover{transform:translateY(-2px)}.lah-button.is-primary{background:var(--lah-brick);color:#fff}.lah-button.is-secondary{border:1px solid var(--lah-line);background:#fff;color:var(--lah-ink)}.lah-button.is-light{background:var(--lah-cream);color:var(--lah-brick-dark)}
                #<?php echo esc_attr( $instance ); ?> .lah-hero-visual{position:relative;min-height:350px;overflow:hidden;border-radius:24px;background:var(--lah-brick-dark);color:#fff;isolation:isolate}.lah-hero-visual:before{content:"";position:absolute;inset:16px;border:1px solid rgba(255,255,255,.2);border-radius:18px}.lah-hero-ring{position:absolute;width:270px;height:270px;right:16%;top:13%;border:1px solid rgba(245,231,210,.5);border-radius:50%;box-shadow:0 0 0 26px rgba(245,231,210,.06),0 0 0 52px rgba(245,231,210,.04)}.lah-hero-number{position:absolute;right:12%;bottom:16%;font-size:clamp(28px,4vw,50px);font-weight:900;line-height:1.1}.lah-hero-note{position:absolute;left:12%;top:16%;max-width:125px;color:#eaded6;font-size:11px;line-height:1.8}
                #<?php echo esc_attr( $instance ); ?> .lah-section{padding:72px 0}.lah-section-head{display:flex;justify-content:space-between;align-items:end;gap:24px;margin-bottom:25px}.lah-section-head h2,#<?php echo esc_attr( $instance ); ?> .lah-benefits h2,#<?php echo esc_attr( $instance ); ?> .lah-final-cta h2{font-size:clamp(24px,3vw,38px);line-height:1.35}.lah-section-head>a{color:var(--lah-brick);font-size:12px;font-weight:900;text-decoration:none;white-space:nowrap}.lah-section-head>a span{margin-right:6px}
                #<?php echo esc_attr( $instance ); ?> .lah-course-grid,#<?php echo esc_attr( $instance ); ?> .lah-continue-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.lah-course-card{display:flex;flex-direction:column;min-width:0;overflow:hidden;border:1px solid var(--lah-line);border-radius:16px;background:#fff;animation:lahRise .5s both;animation-delay:var(--lah-delay)}.lah-course-image{position:relative;display:block;aspect-ratio:16/9;overflow:hidden;background:var(--lah-cream);text-decoration:none}.lah-course-image img{width:100%;height:100%;object-fit:cover;transition:transform .35s ease}.lah-course-card:hover .lah-course-image img{transform:scale(1.03)}.lah-image-fallback{display:grid;place-items:center;height:100%;color:var(--lah-brick);font-size:54px;font-weight:900}.lah-course-status{position:absolute;right:12px;top:12px;padding:3px 9px;border-radius:999px;background:rgba(255,253,250,.94);color:var(--lah-brick-dark);font-size:10px;font-weight:900}.lah-course-content{display:flex;flex:1;flex-direction:column;padding:17px}.lah-course-content h3{min-height:54px;font-size:16px;line-height:1.7}.lah-course-content h3 a{text-decoration:none}.lah-course-author{margin:5px 0 10px;color:var(--lah-muted);font-size:11px}.lah-course-meta{display:flex;gap:7px;min-height:25px;color:#958983;font-size:10px}.lah-course-meta span+span:before{content:"•";margin-left:7px;color:var(--lah-line)}.lah-course-price{min-height:34px;margin:9px 0;color:var(--lah-brick);font-size:14px;font-weight:900}.lah-course-price del{margin-left:6px;color:#a79b95;font-size:11px}.lah-course-price ins{text-decoration:none}.lah-price-free{color:var(--lah-green)}.lah-progress{margin:10px 0 12px}.lah-progress-head{display:flex;justify-content:space-between;margin-bottom:5px;color:var(--lah-muted);font-size:10px}.lah-progress-head strong{color:var(--lah-brick)}.lah-progress-track{display:block;height:6px;overflow:hidden;border-radius:999px;background:#eee7df}.lah-progress-track i{display:block;height:100%;border-radius:inherit;background:var(--lah-brick)}.lah-course-action{display:flex;align-items:center;justify-content:center;gap:8px;min-height:43px;margin-top:auto;border:1px solid var(--lah-brick);border-radius:9px;background:var(--lah-brick);color:#fff!important;text-decoration:none;font-size:11px;font-weight:900}.lah-course-action.is-owned{background:var(--lah-cream);color:var(--lah-brick-dark)!important}.lah-course-action span{font-size:16px}.lah-empty-state,.lah-system-notice{padding:20px;border:1px dashed var(--lah-line);border-radius:14px;background:var(--lah-cream);color:var(--lah-muted);font-size:13px}.lah-system-notice{width:min(1160px,calc(100% - 32px));margin:0 auto 20px;border-style:solid;color:var(--lah-brick-dark)}
                #<?php echo esc_attr( $instance ); ?> .lah-paths-section{padding-top:20px}.lah-category-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.lah-category{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:18px 19px;border-top:1px solid var(--lah-line);border-bottom:1px solid var(--lah-line);text-decoration:none}.lah-category strong{font-size:14px}.lah-category span{color:var(--lah-muted);font-size:10px;white-space:nowrap}.lah-category b{margin-right:7px;color:var(--lah-brick);font-size:15px}
                #<?php echo esc_attr( $instance ); ?> .lah-benefits{display:grid;grid-template-columns:.8fr 1.7fr;gap:50px;padding:84px 0}.lah-benefit-list{display:grid;grid-template-columns:repeat(3,1fr);gap:24px}.lah-benefit-list article{padding-right:18px;border-right:2px solid var(--lah-brick)}.lah-benefit-list strong{color:var(--lah-brick);font-size:12px}.lah-benefit-list h3{margin-top:12px;font-size:16px}.lah-benefit-list p{margin:6px 0 0;color:var(--lah-muted);font-size:11px;line-height:1.9}
                #<?php echo esc_attr( $instance ); ?> .lah-continue-section{padding-top:28px}.lah-final-cta{display:flex;align-items:center;justify-content:space-between;gap:24px;margin-top:42px;padding:36px 42px;border-radius:20px;background:var(--lah-brick);color:#fff}.lah-final-cta .lah-eyebrow{color:#f0d9c7}.lah-final-cta h2{color:#fff}.lah-final-cta .lah-button{flex:0 0 auto}
                @keyframes lahRise{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}@media (prefers-reduced-motion:reduce){#<?php echo esc_attr( $instance ); ?> *{animation:none!important;transition:none!important}}@media (max-width:900px){#<?php echo esc_attr( $instance ); ?> .lah-hero{grid-template-columns:1fr;min-height:0;padding-top:18px}#<?php echo esc_attr( $instance ); ?> .lah-hero-visual{min-height:280px}#<?php echo esc_attr( $instance ); ?> .lah-course-grid,#<?php echo esc_attr( $instance ); ?> .lah-continue-grid{grid-template-columns:repeat(2,minmax(0,1fr))}#<?php echo esc_attr( $instance ); ?> .lah-benefits{grid-template-columns:1fr;gap:28px}.lah-benefit-list{gap:14px}}
                @media (max-width:600px){#<?php echo esc_attr( $instance ); ?> .lah-hero,#<?php echo esc_attr( $instance ); ?> .lah-section,#<?php echo esc_attr( $instance ); ?> .lah-benefits,#<?php echo esc_attr( $instance ); ?> .lah-final-cta{width:min(100% - 24px,520px)}#<?php echo esc_attr( $instance ); ?> .lah-hero{padding-bottom:48px}#<?php echo esc_attr( $instance ); ?> h1{font-size:clamp(32px,11vw,47px)}#<?php echo esc_attr( $instance ); ?> .lah-course-grid,#<?php echo esc_attr( $instance ); ?> .lah-continue-grid,#<?php echo esc_attr( $instance ); ?> .lah-category-grid,#<?php echo esc_attr( $instance ); ?> .lah-benefit-list{grid-template-columns:1fr}#<?php echo esc_attr( $instance ); ?> .lah-section-head{align-items:start;flex-direction:column;gap:8px}#<?php echo esc_attr( $instance ); ?> .lah-benefits{padding:58px 0}#<?php echo esc_attr( $instance ); ?> .lah-final-cta{align-items:start;flex-direction:column;padding:28px 22px}.lah-final-cta .lah-button{width:100%}}
            </style>
            <?php
            return ob_get_clean();
        }
    }

    Luxury_Academy_Homepage::init();
}
