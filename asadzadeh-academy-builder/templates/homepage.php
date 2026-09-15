<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
$courses_url = AAP_Plugin::get_courses_url();
$dashboard_url = AAP_Plugin::get_dashboard_url();
$account_url = AAP_Plugin::get_account_url();
$cart_url = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );

$fallback_cats = array(
    array( 'name' => 'قالی بافی', 'desc' => 'آموزش حرفه‌ای قالیبافی از مقدماتی تا پیشرفته', 'seed' => 'qali-bafi' ),
    array( 'name' => 'گلیم بافی', 'desc' => 'آشنایی با نقش، رنگ و بافت گلیم‌های اصیل ایرانی', 'seed' => 'gelim-bafi' ),
    array( 'name' => 'گبه بافی', 'desc' => 'از پشم تا نقش، آموزش ساده و کاربردی گبه بافی', 'seed' => 'gabbeh-bafi' ),
    array( 'name' => 'هنرهای سنتی', 'desc' => 'آشنایی با هنرهای اصیل ایرانی، از بافت تا تزئینات', 'seed' => 'honar-sonnati' ),
);
?>
<div id="<?php echo esc_attr( $instance ); ?>" class="aap-home aap-elementor-compatible" dir="rtl">
    <?php echo AAP_Renderer::enqueue_frontend( $instance ); ?>
    <link rel="stylesheet" href="<?php echo esc_url( AAP_URL . 'assets/css/frontend.css' ); ?>">
    <?php if ( ! AAP_Data::tutor_available() || ! AAP_Data::woo_available() ) : ?>
        <div class="aap-system-notice" role="alert">برای نمایش صفحه اصلی، Tutor LMS و WooCommerce باید فعال باشند.</div>
    <?php endif; ?>

    <section class="aap-hero" aria-labelledby="<?php echo esc_attr( $instance . '-title' ); ?>">
        <div class="aap-hero-grid">
            <div class="aap-hero-copy" data-reveal>
                <span class="aap-eyebrow">تجربه‌ای که منتقل می‌شود</span>
                <h1 id="<?php echo esc_attr( $instance . '-title' ); ?>">به آکادمی اسدزاده خوش آمدید</h1>
                <p>آموزش حرفه‌ای قالیبافی، گلیم‌بافی، گبه‌بافی و هنرهای سنتی ایران از اولین گره تا خلق اثری اصیل</p>
                <div class="aap-hero-actions">
                    <a class="aap-btn is-primary" href="<?php echo esc_url( $courses_url ); ?>">
                        <span class="aap-btn-label">مشاهده دوره‌ها</span>
                        <span class="aap-btn-icon" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M10 3L13 8L10 13" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path d="M13 8H3" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg></span>
                    </a>
                    <a class="aap-btn is-secondary" href="<?php echo esc_url( is_user_logged_in() ? $dashboard_url : $account_url ); ?>">
                        <span class="aap-btn-label"><?php echo esc_html( is_user_logged_in() ? 'ادامه یادگیری' : 'ورود به پیشخوان' ); ?></span>
                    </a>
                </div>
                <div class="aap-hero-proof">
                    <div class="aap-proof-item"><strong>گواهینامه معتبر</strong><span>پس از اتمام دوره</span></div>
                    <div class="aap-proof-item"><strong>اساتید باتجربه</strong><span>با سال‌ها تجربه</span></div>
                    <div class="aap-proof-item"><strong>+500 هنرجو</strong><span>همراه مسیر یادگیری</span></div>
                </div>
            </div>
            <div class="aap-hero-visual" data-reveal style="--aap-delay: 120ms">
                <div class="aap-visual-shell"><div class="aap-visual-inner">
                    <img src="https://asadzadehacademy.ir/wp-content/uploads/2026/09/%DA%A9%D8%A7%D8%B1%DA%AF%D8%A7%D9%87-%D9%82%D8%A7%D9%84%DB%8C%D8%A8%D8%A7%D9%81%DB%8C-1024x768.png" alt="کارگاه قالیبافی" loading="eager" onerror="this.src='https://picsum.photos/seed/asadzadeh-hero/1024/768'">
                    <div class="aap-visual-grain" aria-hidden="true"></div>
                </div></div>
                <div class="aap-float-card is-top"><span class="aap-float-num">+20</span><span class="aap-float-label">دوره آموزشی آنلاین و حضوری</span></div>
                <div class="aap-float-card is-bottom"><span class="aap-float-quote">هنر راهی برای زندگی زیباتر</span><span class="aap-float-sub">با دست‌ها می‌آموزیم، با دل‌ها ماندگار می‌کنیم</span></div>
            </div>
        </div>
    </section>

    <section class="aap-metrics" aria-label="آمار آکادمی">
        <div class="aap-metrics-grid">
            <div class="aap-metric" data-reveal><strong>+20</strong><span>دوره آموزشی</span></div>
            <div class="aap-metric" data-reveal style="--aap-delay: 80ms"><strong>+500</strong><span>هنرجوی فعال</span></div>
            <div class="aap-metric" data-reveal style="--aap-delay: 160ms"><strong>20 سال</strong><span>تجربه آموزش</span></div>
            <div class="aap-metric" data-reveal style="--aap-delay: 240ms"><strong>گواهی</strong><span>پایان دوره معتبر</span></div>
        </div>
    </section>

    <section class="aap-section aap-courses" aria-labelledby="<?php echo esc_attr( $instance . '-courses' ); ?>">
        <div class="aap-section-head">
            <div class="aap-head-stack"><span class="aap-eyebrow">دوره‌های ویژه</span><h2 id="<?php echo esc_attr( $instance . '-courses' ); ?>">برای قدم بعدی یادگیری</h2></div>
            <a class="aap-btn is-secondary is-sm" href="<?php echo esc_url( $courses_url ); ?>"><span class="aap-btn-label">همه دوره‌ها</span></a>
        </div>
        <?php if ( $course_rows ) : ?>
            <div class="aap-course-grid"><?php foreach ( $course_rows as $i => $row ) : AAP_Renderer::course_card( $row, $i ); endforeach; ?></div>
        <?php else : ?>
            <div class="aap-empty" data-reveal><div class="aap-empty-shell"><h3>هنوز دوره‌ای منتشر نشده</h3><p>به زودی دوره‌های قالیبافی، گلیم‌بافی و گبه‌بافی در این بخش قرار می‌گیرد</p></div></div>
        <?php endif; ?>
    </section>

    <section class="aap-section aap-cats" aria-labelledby="<?php echo esc_attr( $instance . '-cats' ); ?>">
        <div class="aap-section-head"><h2 id="<?php echo esc_attr( $instance . '-cats' ); ?>">دسته‌های آموزشی</h2><a class="aap-text-link" href="<?php echo esc_url( $courses_url ); ?>">مشاهده همه دسته‌ها <span aria-hidden="true">←</span></a></div>
        <div class="aap-bento">
            <?php
            $cats_to_show = $categories ? array_slice( $categories, 0, 4 ) : array();
            if ( $cats_to_show ) {
                $layout = array( 'is-large', 'is-small', 'is-small', 'is-wide' );
                foreach ( $cats_to_show as $idx => $term ) {
                    $link = get_term_link( $term );
                    $seed = sanitize_title( $term->slug ) . '-' . $term->term_id;
                    $cls = $layout[ $idx % 4 ];
                    ?>
                    <a class="aap-bento-card <?php echo esc_attr( $cls ); ?>" href="<?php echo esc_url( is_wp_error( $link ) ? $courses_url : $link ); ?>" data-reveal style="--aap-delay: <?php echo esc_attr( $idx * 90 ); ?>ms">
                        <div class="aap-bento-shell"><div class="aap-bento-inner"><div class="aap-bento-media"><img src="https://picsum.photos/seed/<?php echo esc_attr( $seed ); ?>/800/450" alt="<?php echo esc_attr( $term->name ); ?>" loading="lazy"></div><div class="aap-bento-body"><h3><?php echo esc_html( $term->name ); ?></h3><p><?php echo esc_html( $term->description ? wp_trim_words( $term->description, 12 ) : 'آموزش تخصصی و کاربردی' ); ?></p><span class="aap-bento-meta"><?php echo esc_html( $term->count ); ?> دوره</span></div></div></div>
                    </a>
                    <?php
                }
            } else {
                foreach ( $fallback_cats as $idx => $cat ) {
                    $cls = array( 'is-large', 'is-small', 'is-small', 'is-wide' )[ $idx % 4 ];
                    ?>
                    <a class="aap-bento-card <?php echo esc_attr( $cls ); ?>" href="<?php echo esc_url( $courses_url ); ?>" data-reveal style="--aap-delay: <?php echo esc_attr( $idx * 90 ); ?>ms">
                        <div class="aap-bento-shell"><div class="aap-bento-inner"><div class="aap-bento-media"><img src="https://picsum.photos/seed/<?php echo esc_attr( $cat['seed'] ); ?>/800/450" alt="<?php echo esc_attr( $cat['name'] ); ?>" loading="lazy"></div><div class="aap-bento-body"><h3><?php echo esc_html( $cat['name'] ); ?></h3><p><?php echo esc_html( $cat['desc'] ); ?></p></div></div></div>
                    </a>
                    <?php
                }
            }
            ?>
        </div>
    </section>

    <section class="aap-section aap-why" aria-labelledby="<?php echo esc_attr( $instance . '-why' ); ?>">
        <div class="aap-why-grid">
            <div class="aap-why-head" data-reveal><h2 id="<?php echo esc_attr( $instance . '-why' ); ?>">چرا آکادمی اسدزاده</h2><p>آموزش اصولی هنرهای سنتی، از تجربه استاد تا همراهی هنرجو</p></div>
            <div class="aap-why-list">
                <div class="aap-why-item" data-reveal><span class="aap-why-num">01</span><div><h3>آموزش عملی و اصولی</h3><p>یادگیری با تمرین، پروژه و تجربه واقعی در کارگاه مجهز</p></div></div>
                <div class="aap-why-item" data-reveal style="--aap-delay: 80ms"><span class="aap-why-num">02</span><div><h3>مدرس باتجربه</h3><p>آموزش بر پایه سال‌ها تجربه عملی و آموزشی استاد اسدزاده</p></div></div>
                <div class="aap-why-item" data-reveal style="--aap-delay: 160ms"><span class="aap-why-num">03</span><div><h3>همراهی هنرجو</h3><p>پشتیبانی و راهنمایی در طول مسیر یادگیری تا خلق اثر نهایی</p></div></div>
                <div class="aap-why-item" data-reveal style="--aap-delay: 240ms"><span class="aap-why-num">04</span><div><h3>گواهی پایان دوره</h3><p>ارائه گواهی پس از تکمیل دوره‌های واجد شرایط</p></div></div>
            </div>
        </div>
    </section>

    <?php if ( ! empty( $active ) ) : ?>
        <section class="aap-section aap-continue" aria-labelledby="<?php echo esc_attr( $instance . '-continue' ); ?>">
            <div class="aap-section-head"><h2 id="<?php echo esc_attr( $instance . '-continue' ); ?>">ادامه یادگیری</h2><a class="aap-btn is-secondary is-sm" href="<?php echo esc_url( $dashboard_url ); ?>"><span class="aap-btn-label">دوره‌های من</span></a></div>
            <div class="aap-course-grid"><?php foreach ( $active as $i => $row ) : AAP_Renderer::course_card( $row, $i ); endforeach; ?></div>
        </section>
    <?php endif; ?>

    <section class="aap-final" aria-labelledby="<?php echo esc_attr( $instance . '-cta' ); ?>" data-reveal>
        <div class="aap-final-shell"><div class="aap-final-inner">
            <div class="aap-final-copy"><h2 id="<?php echo esc_attr( $instance . '-cta' ); ?>"><?php echo esc_html( is_user_logged_in() ? 'دوره بعدی‌ات را انتخاب کن' : 'آماده‌ای اولین گره را بزنی' ); ?></h2><p>دوره‌ها و کارگاه‌های آکادمی اسدزاده برای شروع از پایه طراحی شده‌اند.</p></div>
            <div class="aap-final-actions">
                <a class="aap-btn is-light" href="<?php echo esc_url( is_user_logged_in() ? $courses_url : $account_url ); ?>"><span class="aap-btn-label"><?php echo esc_html( is_user_logged_in() ? 'دیدن دوره‌ها' : 'ورود و ثبت نام' ); ?></span></a>
                <a class="aap-btn is-ghost" href="<?php echo esc_url( $dashboard_url ); ?>"><span class="aap-btn-label">دوره‌های من</span></a>
            </div>
        </div></div>
    </section>
</div>
