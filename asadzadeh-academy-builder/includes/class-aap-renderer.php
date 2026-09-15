<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class AAP_Renderer {

    public static function price_html( $product ) {
        if ( ! $product || ! is_object( $product ) ) {
            return '<span class="aap-price-free">رایگان</span>';
        }
        return wp_kses_post( $product->get_price_html() );
    }

    public static function course_card( $row, $index = 0 ) {
        $progress = $row['progress'];
        $status = '';
        $label = '';
        $url = $row['action_url'];
        $is_owned = '';

        if ( $row['is_enrolled'] ) {
            $is_owned = 'is-owned';
            if ( $progress['percent'] >= 100 ) {
                $status = 'تکمیل شده';
                $label = 'مرور دوره';
            } elseif ( $progress['percent'] > 0 ) {
                $status = 'در حال یادگیری';
                $label = 'ادامه دوره';
            } else {
                $status = 'دسترسی فعال';
                $label = 'مشاهده دوره';
            }
        } elseif ( $row['in_cart'] ) {
            $status = 'در سبد خرید';
            $label = 'مشاهده سبد خرید';
            $url = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/' );
        } elseif ( $row['is_free'] ) {
            $status = 'رایگان';
            $label = is_user_logged_in() ? 'شروع دوره' : 'ورود برای ثبت نام';
            $url = is_user_logged_in() ? $row['url'] : ( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url( $row['url'] ) );
        } else {
            $status = 'ثبت نام دوره';
            $label = 'افزودن به سبد خرید';
            $url = $row['product'] ? add_query_arg( 'add-to-cart', $row['product']->get_id(), home_url( '/' ) ) : $row['url'];
        }
        ?>
        <article class="aap-course-card <?php echo esc_attr( $is_owned ); ?>" data-reveal style="--aap-delay: <?php echo esc_attr( min( $index * 70, 420 ) ); ?>ms">
            <div class="aap-course-shell"><div class="aap-course-inner">
                <a class="aap-course-media" href="<?php echo esc_url( $row['url'] ); ?>" aria-label="<?php echo esc_attr( 'مشاهده ' . $row['title'] ); ?>">
                    <?php if ( $row['image'] ) : ?>
                        <img src="<?php echo esc_url( $row['image'] ); ?>" alt="<?php echo esc_attr( $row['title'] ); ?>" loading="lazy" decoding="async">
                    <?php else : ?>
                        <img src="https://picsum.photos/seed/<?php echo esc_attr( 'aap-course-' . $row['id'] ); ?>/640/360" alt="<?php echo esc_attr( $row['title'] ); ?>" loading="lazy">
                    <?php endif; ?>
                    <span class="aap-course-badge <?php echo esc_attr( $is_owned ); ?>"><?php echo esc_html( $status ); ?></span>
                </a>
                <div class="aap-course-body">
                    <div class="aap-course-top">
                        <h3><a href="<?php echo esc_url( $row['url'] ); ?>"><?php echo esc_html( $row['title'] ); ?></a></h3>
                        <?php if ( $row['terms'] ) : ?><span class="aap-course-cat"><?php echo esc_html( implode( '، ', $row['terms'] ) ); ?></span><?php endif; ?>
                    </div>
                    <p class="aap-course-teacher"><?php echo esc_html( $row['author'] ? 'مدرس: ' . $row['author'] : 'آکادمی اسدزاده' ); ?></p>
                    <div class="aap-course-meta">
                        <span><?php echo esc_html( AAP_Data::course_detail( $row['id'], 'level' ) ?: 'همه سطوح' ); ?></span>
                        <?php $d = AAP_Data::course_detail( $row['id'], 'duration' ); if ( $d ) : ?><span><?php echo esc_html( $d ); ?></span><?php endif; ?>
                    </div>
                    <?php if ( $row['is_enrolled'] ) : ?>
                        <div class="aap-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( $progress['percent'] ); ?>">
                            <div class="aap-progress-head"><span>پیشرفت شما</span><strong><?php echo esc_html( $progress['percent'] ); ?>٪</strong></div>
                            <span class="aap-progress-track"><i style="width: <?php echo esc_attr( $progress['percent'] ); ?>%"></i></span>
                            <div class="aap-progress-meta"><span><?php echo esc_html( $progress['completed'] ); ?> از <?php echo esc_html( $progress['total'] ); ?> بخش</span></div>
                        </div>
                    <?php elseif ( ! $row['is_free'] ) : ?>
                        <div class="aap-course-price"><?php echo self::price_html( $row['product'] ); ?></div>
                    <?php else : ?>
                        <div class="aap-course-price"><span class="aap-price-free">رایگان</span></div>
                    <?php endif; ?>
                    <a class="aap-btn is-course <?php echo esc_attr( $is_owned ); ?>" href="<?php echo esc_url( $url ); ?>">
                        <span class="aap-btn-label"><?php echo esc_html( $label ); ?></span>
                        <span class="aap-btn-icon" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M10 3L13 8L10 13" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path d="M13 8H3" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg></span>
                    </a>
                </div>
            </div></div>
        </article>
        <?php
    }

    public static function enqueue_frontend( $instance_id ) {
        wp_enqueue_style( 'aap-frontend' );
        wp_enqueue_script( 'aap-frontend' );
        // Inline instance specific CSS will be printed via template, global CSS in file
        ob_start();
        ?>
        <style id="<?php echo esc_attr( $instance_id ); ?>-css">
            #<?php echo esc_attr( $instance_id ); ?>{--aap-primary: var(--e-global-color-primary, var(--wp--preset--color--primary, #8f2e32)); --aap-primary-dark: color-mix(in srgb, var(--aap-primary) 82%, #1a1a1a); --aap-paper: var(--wp--preset--color--base, #fdfcfa); --aap-paper-2:#f2f0eb; --aap-ink: var(--e-global-color-text, var(--wp--preset--color--contrast, #181a18)); --aap-muted:#6e756b; --aap-line:#e6e1d6; --aap-line-strong:#d1cbc0; --aap-forest:#1f3128; --aap-moss:#eef1ec; --aap-green:#067647; --aap-radius-pill:999px; --aap-radius-card:22px; --aap-radius-inner:14px; --aap-shadow-soft:0 12px 40px rgba(31,49,40,.08); --aap-shadow-medium:0 20px 60px rgba(31,49,40,.12); --aap-ease:cubic-bezier(.16,1,.3,1); --aap-ease-spring:cubic-bezier(.32,.72,0,1)}
        </style>
        <?php
        return ob_get_clean();
    }
}
