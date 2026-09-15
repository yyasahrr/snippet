<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
$courses_url = AAP_Plugin::get_courses_url();
$dashboard_url = AAP_Plugin::get_dashboard_url();
?>
<div id="<?php echo esc_attr( $instance ); ?>" class="aap-courses-page aap-elementor-compatible" dir="rtl">
    <link rel="stylesheet" href="<?php echo esc_url( AAP_URL . 'assets/css/frontend.css' ); ?>">
    <section class="aap-page-hero" data-reveal>
        <div class="aap-page-hero-inner">
            <span class="aap-eyebrow">مسیرهای یادگیری</span>
            <h1>دوره‌های آکادمی اسدزاده</h1>
            <p>از اولین گره تا خلق اثری اصیل، مسیر مناسب خودت را انتخاب کن و قدم به قدم پیش برو</p>
            <div class="aap-search-bar">
                <form action="<?php echo esc_url( $courses_url ); ?>" method="get" role="search">
                    <input type="search" name="s" placeholder="جستجوی دوره، مثلا قالیبافی..." value="<?php echo esc_attr( $search ); ?>">
                    <button type="submit" class="aap-btn is-primary is-sm"><span class="aap-btn-label">جستجو</span></button>
                </form>
            </div>
        </div>
    </section>

    <?php if ( $categories ) : ?>
        <section class="aap-section aap-cat-filter" data-reveal>
            <div class="aap-filter-grid">
                <?php foreach ( array_slice( $categories, 0, 8 ) as $term ) : 
                    $link = get_term_link( $term );
                    ?>
                    <a class="aap-filter-card" href="<?php echo esc_url( is_wp_error( $link ) ? $courses_url : $link ); ?>">
                        <strong><?php echo esc_html( $term->name ); ?></strong>
                        <span><?php echo esc_html( $term->count ); ?> دوره</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="aap-section">
        <div class="aap-section-head">
            <h2>همه دوره‌ها</h2>
            <span class="aap-count"><?php echo esc_html( count( $course_rows ) ); ?> دوره</span>
        </div>
        <?php if ( $course_rows ) : ?>
            <div class="aap-course-grid">
                <?php foreach ( $course_rows as $i => $row ) : AAP_Renderer::course_card( $row, $i ); endforeach; ?>
            </div>
        <?php else : ?>
            <div class="aap-empty" data-reveal><div class="aap-empty-shell"><h3>دوره‌ای یافت نشد</h3><p>دسته‌بندی دیگری را امتحان کنید یا با پشتیبانی تماس بگیرید</p></div></div>
        <?php endif; ?>
    </section>
</div>
