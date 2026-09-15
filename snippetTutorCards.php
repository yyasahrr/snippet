/**
 * Tutor LMS Owned Course Cards — Asadzadeh Academy
 *
 * - تشخیص دوره‌های ثبت‌نام‌شده کاربر از Tutor LMS
 * - تبدیل دکمه خرید کارت به مشاهده/ادامه دوره
 * - افزودن نشان «خریداری‌شده» روی کارت
 * - جلوگیری سمت سرور از خرید دوباره محصول متصل به دوره
 * - سازگار با کارت‌های Tutor LMS و ویجت Element Pack (bdt-tutor-course)
 *
 * این کد را در WPCode بدون تگ آغازین PHP و روی Run Everywhere قرار دهید.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Luxury_Tutor_Owned_Course_Cards' ) ) {

    final class Luxury_Tutor_Owned_Course_Cards {

        private static $enrolled_ids        = null;
        private static $product_course_map  = array();

        public static function init() {
            add_action( 'wp_footer', array( __CLASS__, 'render_owned_course_assets' ), 80 );

            add_filter( 'is_course_purchasable', array( __CLASS__, 'filter_course_purchasable' ), 99, 2 );
            add_filter( 'woocommerce_is_purchasable', array( __CLASS__, 'filter_product_purchasable' ), 99, 2 );
            add_filter( 'woocommerce_loop_add_to_cart_link', array( __CLASS__, 'filter_loop_button' ), 99, 3 );
            add_filter( 'woocommerce_loop_product_link', array( __CLASS__, 'filter_loop_product_link' ), 99, 2 );
        }

        private static function tutor_available() {
            return function_exists( 'tutor' ) && function_exists( 'tutor_utils' );
        }

        private static function enrolled_course_ids() {
            if ( is_array( self::$enrolled_ids ) ) {
                return self::$enrolled_ids;
            }

            self::$enrolled_ids = array();

            if ( ! is_user_logged_in() || ! self::tutor_available() ) {
                return self::$enrolled_ids;
            }

            $query = tutor_utils()->get_enrolled_courses_by_user(
                get_current_user_id(),
                array( 'publish', 'private' )
            );

            $courses = $query instanceof WP_Query ? $query->posts : ( is_array( $query ) ? $query : array() );

            foreach ( $courses as $course ) {
                $course_id = is_object( $course ) && isset( $course->ID ) ? (int) $course->ID : (int) $course;

                if ( $course_id > 0 ) {
                    self::$enrolled_ids[] = $course_id;
                }
            }

            self::$enrolled_ids = array_values( array_unique( array_map( 'absint', self::$enrolled_ids ) ) );

            return self::$enrolled_ids;
        }

        private static function user_is_enrolled( $course_id ) {
            if ( ! is_user_logged_in() || ! $course_id ) {
                return false;
            }

            return in_array( (int) $course_id, self::enrolled_course_ids(), true );
        }

        private static function course_id_for_product( $product_id ) {
            $product_id = absint( $product_id );

            if ( ! $product_id || ! self::tutor_available() ) {
                return 0;
            }

            if ( isset( self::$product_course_map[ $product_id ] ) ) {
                return self::$product_course_map[ $product_id ];
            }

            $course_post_type = tutor()->course_post_type;
            $course_ids       = get_posts(
                array(
                    'post_type'              => $course_post_type,
                    'post_status'            => array( 'publish', 'private' ),
                    'posts_per_page'         => 1,
                    'fields'                 => 'ids',
                    'meta_key'               => '_tutor_course_product_id',
                    'meta_value'             => $product_id,
                    'no_found_rows'          => true,
                    'update_post_meta_cache' => false,
                    'update_post_term_cache' => false,
                )
            );

            self::$product_course_map[ $product_id ] = $course_ids ? (int) reset( $course_ids ) : 0;

            return self::$product_course_map[ $product_id ];
        }

        private static function course_progress( $course_id ) {
            if ( ! self::tutor_available() ) {
                return 0;
            }

            return max(
                0,
                min(
                    100,
                    (int) tutor_utils()->get_course_completed_percent( $course_id, get_current_user_id() )
                )
            );
        }

        private static function course_action( $course_id ) {
            $progress   = self::course_progress( $course_id );
            $course_url = get_permalink( $course_id );
            $action_url = $course_url;

            if ( $progress > 0 && self::tutor_available() ) {
                $lesson_url = tutor_utils()->get_course_first_lesson( $course_id );

                if ( is_numeric( $lesson_url ) ) {
                    $lesson_url = get_permalink( (int) $lesson_url );
                } elseif ( $lesson_url instanceof WP_Post ) {
                    $lesson_url = get_permalink( $lesson_url->ID );
                }

                if ( $lesson_url ) {
                    $action_url = $lesson_url;
                }
            }

            return array(
                'course_id'  => (int) $course_id,
                'course_url' => $course_url,
                'action_url' => $action_url,
                'progress'   => $progress,
                'label'      => $progress > 0 && $progress < 100 ? 'ادامه دوره' : 'مشاهده دوره',
            );
        }

        public static function filter_course_purchasable( $purchasable, $course_id ) {
            if ( self::user_is_enrolled( $course_id ) ) {
                return false;
            }

            return $purchasable;
        }

        public static function filter_product_purchasable( $purchasable, $product ) {
            if ( ! is_user_logged_in() || ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
                return $purchasable;
            }

            $product_id = (int) $product->get_id();

            if ( method_exists( $product, 'get_parent_id' ) && $product->get_parent_id() ) {
                $product_id = (int) $product->get_parent_id();
            }

            $course_id = self::course_id_for_product( $product_id );

            return $course_id && self::user_is_enrolled( $course_id ) ? false : $purchasable;
        }

        public static function filter_loop_button( $html, $product, $args ) {
            if ( ! is_user_logged_in() || ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
                return $html;
            }

            $product_id = (int) $product->get_id();

            if ( method_exists( $product, 'get_parent_id' ) && $product->get_parent_id() ) {
                $product_id = (int) $product->get_parent_id();
            }

            $course_id = self::course_id_for_product( $product_id );

            if ( ! $course_id || ! self::user_is_enrolled( $course_id ) ) {
                return $html;
            }

            $action = self::course_action( $course_id );

            return sprintf(
                '<a href="%1$s" class="button lux-owned-course-button" aria-label="%2$s"><span class="lux-owned-button-icon" aria-hidden="true">%3$s</span><span>%4$s</span></a>',
                esc_url( $action['action_url'] ),
                esc_attr( $action['label'] . ': ' . get_the_title( $course_id ) ),
                $action['progress'] > 0 && $action['progress'] < 100 ? '▶' : '←',
                esc_html( $action['label'] )
            );
        }

        public static function filter_loop_product_link( $product_url, $product ) {
            if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
                return $product_url;
            }

            $product_id = (int) $product->get_id();

            if ( method_exists( $product, 'get_parent_id' ) && $product->get_parent_id() ) {
                $product_id = (int) $product->get_parent_id();
            }

            $course_id = self::course_id_for_product( $product_id );

            return $course_id ? get_permalink( $course_id ) : $product_url;
        }

        private static function owned_course_map() {
            $map = array();

            foreach ( self::enrolled_course_ids() as $course_id ) {
                $action = self::course_action( $course_id );
                $map[]  = array(
                    'id'         => $course_id,
                    'courseUrl'  => $action['course_url'],
                    'actionUrl'  => $action['action_url'],
                    'progress'   => $action['progress'],
                    'label'      => $action['label'],
                );
            }

            return $map;
        }

        public static function render_owned_course_assets() {
            if ( is_admin() || ! is_user_logged_in() || ! self::tutor_available() ) {
                return;
            }

            $owned_courses = self::owned_course_map();

            if ( empty( $owned_courses ) ) {
                return;
            }
            ?>
            <style id="lux-owned-tutor-cards-css">
                .bdt-tutor-course-item.lux-course-is-owned,
                .tutor-course-card.lux-course-is-owned,
                .tutor-card.lux-course-is-owned,
                .woocommerce ul.products li.product.lux-course-is-owned {
                    --lux-owned: var(--e-global-color-primary, var(--wp--preset--color--primary, #a42a2a));
                    --lux-owned-dark: color-mix(in srgb, var(--lux-owned) 82%, #101828);
                    border-color: color-mix(in srgb, var(--lux-owned) 25%, #eaded6) !important;
                    position: relative;
                }

                .lux-course-is-owned .lux-owned-course-badge {
                    display: inline-flex;
                    align-items: center;
                    gap: 5px;
                    min-height: 29px;
                    padding: 0 10px;
                    border: 1px solid rgba(255,255,255,.7);
                    border-radius: 999px;
                    background: rgba(6,118,71,.9);
                    backdrop-filter: blur(9px);
                    color: #fff;
                    font-family: "Vazirmatn", Tahoma, sans-serif;
                    font-size: 10px;
                    font-weight: 850;
                    line-height: 1;
                    box-shadow: 0 8px 20px rgba(6,118,71,.18);
                    position: absolute;
                    top: 13px;
                    left: 13px;
                    z-index: 5;
                }

                .lux-course-is-owned .lux-owned-course-badge::before { content: "✓"; display: grid; place-items: center; width: 15px; height: 15px; border-radius: 50%; background: rgba(255,255,255,.18); font-size: 10px; }
                .lux-course-is-owned .lux-owned-access-label { display: inline-flex; align-items: center; color: #067647; font-family: "Vazirmatn", Tahoma, sans-serif; font-size: 11px; font-weight: 900; white-space: nowrap; }

                .lux-course-is-owned .lux-owned-course-button,
                .lux-course-is-owned a.lux-owned-course-button,
                a.lux-owned-course-button {
                    display: inline-flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                    gap: 7px !important;
                    min-height: 45px !important;
                    padding: 0 16px !important;
                    border: 1px solid var(--lux-owned, #a42a2a) !important;
                    border-radius: 11px !important;
                    background: var(--lux-owned, #a42a2a) !important;
                    color: #fff !important;
                    font-family: "Vazirmatn", Tahoma, sans-serif !important;
                    font-size: 11px !important;
                    font-weight: 900 !important;
                    line-height: 1.3 !important;
                    text-decoration: none !important;
                    box-shadow: 0 9px 22px color-mix(in srgb, var(--lux-owned, #a42a2a) 20%, transparent) !important;
                    cursor: pointer;
                    transition: transform .2s ease, filter .2s ease !important;
                }

                .lux-course-is-owned a.lux-owned-course-button:hover,
                a.lux-owned-course-button:hover { color: #fff !important; filter: brightness(.94); transform: translateY(-1px); }

                .lux-owned-button-icon {
                    font-family: inherit !important;
                    font-size: 15px !important;
                    font-weight: 900 !important;
                    line-height: 1 !important;
                    direction: ltr;
                }

                .lux-course-is-owned .bdt-tutor-course-loop-price > .price,
                .lux-course-is-owned .tutor-loop-course-footer .price {
                    align-items: center !important;
                    gap: 12px !important;
                }

                .lux-course-is-owned .bdt-tutor-course-loop-price > .price > del,
                .lux-course-is-owned .bdt-tutor-course-loop-price > .price > ins,
                .lux-course-is-owned .bdt-tutor-course-loop-price > .price > .woocommerce-Price-amount,
                .lux-course-is-owned .bdt-tutor-course-loop-price > .price > .bdt-tutor-price-free,
                .woocommerce ul.products li.product.lux-course-is-owned > .price > del,
                .woocommerce ul.products li.product.lux-course-is-owned > .price > ins,
                .woocommerce ul.products li.product.lux-course-is-owned > .price > .woocommerce-Price-amount {
                    display: none !important;
                }

                @media (max-width: 560px) {
                    .lux-course-is-owned .lux-owned-course-badge { top: 10px; left: 10px; }
                    .lux-course-is-owned .lux-owned-access-label { font-size: 10px; }
                }

                @media (prefers-reduced-motion: reduce) {
                    .lux-course-is-owned *,
                    .lux-course-is-owned *::before,
                    .lux-course-is-owned *::after { transition: none !important; }
                }
            </style>

            <script id="lux-owned-tutor-cards-js">
                (function () {
                    'use strict';

                    var ownedCourses = <?php echo wp_json_encode( $owned_courses, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?>;
                    if (!Array.isArray(ownedCourses) || !ownedCourses.length) return;

                    function normalizePath(url) {
                        try {
                            var path = new URL(url, window.location.origin).pathname;
                            return decodeURIComponent(path).replace(/\/+$/, '').toLocaleLowerCase('fa');
                        } catch (error) {
                            return String(url || '').replace(/\/+$/, '').toLocaleLowerCase('fa');
                        }
                    }

                    var courseMap = {};
                    ownedCourses.forEach(function (course) {
                        courseMap[normalizePath(course.courseUrl)] = course;
                    });

                    function findOwnedCourse(card) {
                        var links = card.querySelectorAll('a[href]');

                        for (var index = 0; index < links.length; index++) {
                            var path = normalizePath(links[index].href);
                            if (courseMap[path]) return courseMap[path];
                        }

                        return null;
                    }

                    function createBadge(card) {
                        if (card.querySelector('.lux-owned-course-badge')) return;
                        var media = card.querySelector('.bdt-tutor-course-header, .tutor-course-thumbnail, .tutor-card-image-top') || card;
                        var badge = document.createElement('span');
                        badge.className = 'lux-owned-course-badge';
                        badge.textContent = 'خریداری‌شده';
                        media.appendChild(badge);
                    }

                    function convertButton(card, course) {
                        var button = card.querySelector(
                            '.tutor-loop-cart-btn-wrap a, ' +
                            '.tutor-course-loop-btn a, ' +
                            'a.add_to_cart_button, ' +
                            'a.product_type_simple'
                        );

                        if (!button) {
                            var buttonHost = card.querySelector('.tutor-loop-cart-btn-wrap');

                            if (!buttonHost) {
                                var footerPrice = card.querySelector('.bdt-tutor-course-loop-price > .price, .tutor-loop-course-footer .price');
                                if (footerPrice) {
                                    buttonHost = document.createElement('div');
                                    buttonHost.className = 'tutor-loop-cart-btn-wrap';
                                    footerPrice.appendChild(buttonHost);
                                }
                            }

                            if (!buttonHost) return;

                            button = document.createElement('a');
                            buttonHost.appendChild(button);
                        }

                        button.href = course.actionUrl;
                        button.classList.remove('add_to_cart_button', 'ajax_add_to_cart', 'product_type_simple');
                        button.classList.add('lux-owned-course-button');
                        button.removeAttribute('data-product_id');
                        button.removeAttribute('data-product_sku');
                        button.removeAttribute('data-quantity');
                        button.removeAttribute('rel');
                        button.setAttribute('aria-label', course.label);
                        button.innerHTML = '<span class="lux-owned-button-icon" aria-hidden="true">' + (course.progress > 0 && course.progress < 100 ? '▶' : '←') + '</span><span>' + course.label + '</span>';

                        var price = card.querySelector('.bdt-tutor-course-loop-price > .price, .tutor-loop-course-footer .price, .price');
                        if (price && !price.querySelector('.lux-owned-access-label')) {
                            var access = document.createElement('span');
                            access.className = 'lux-owned-access-label';
                            access.textContent = course.progress >= 100 ? 'دوره تکمیل شده' : 'دسترسی فعال';
                            price.insertBefore(access, price.firstChild);
                        }
                    }

                    function enhanceCards(scope) {
                        var root = scope && scope.querySelectorAll ? scope : document;
                        var cards = root.querySelectorAll(
                            '.bdt-tutor-course-item:not([data-lux-owned-ready]), ' +
                            '.tutor-course-card:not([data-lux-owned-ready]), ' +
                            '.tutor-card.tutor-course-card:not([data-lux-owned-ready]), ' +
                            '.woocommerce ul.products li.product:not([data-lux-owned-ready])'
                        );

                        cards.forEach(function (card) {
                            var course = findOwnedCourse(card);
                            card.setAttribute('data-lux-owned-ready', '1');

                            if (!course) return;

                            card.classList.add('lux-course-is-owned');
                            createBadge(card);
                            convertButton(card, course);
                        });
                    }

                    function start() {
                        enhanceCards(document);

                        var observer = new MutationObserver(function (mutations) {
                            mutations.forEach(function (mutation) {
                                mutation.addedNodes.forEach(function (node) {
                                    if (node.nodeType === 1) enhanceCards(node.parentNode || node);
                                });
                            });
                        });

                        observer.observe(document.body, { childList: true, subtree: true });
                    }

                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', start);
                    } else {
                        start();
                    }
                })();
            </script>
            <?php
        }
    }

    Luxury_Tutor_Owned_Course_Cards::init();
}
