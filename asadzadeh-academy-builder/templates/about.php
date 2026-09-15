<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
$courses_url = AAP_Plugin::get_courses_url();
?>
<div id="<?php echo esc_attr( $instance ); ?>" class="aap-about-page aap-elementor-compatible" dir="rtl">
    <link rel="stylesheet" href="<?php echo esc_url( AAP_URL . 'assets/css/frontend.css' ); ?>">
    
    <section class="aap-page-hero is-about" data-reveal>
        <div class="aap-page-hero-inner">
            <span class="aap-eyebrow">درباره آکادمی</span>
            <h1>جایی برای یادگیری، تجربه و حفظ هنرهای اصیل ایرانی</h1>
            <p>ما در آکادمی اسدزاده باور داریم که هنرهای سنتی تنها یک مهارت نیستند، بلکه پلی هستند میان گذشته و آینده</p>
        </div>
        <div class="aap-about-hero-visual" data-reveal style="--aap-delay: 120ms">
            <div class="aap-visual-shell"><div class="aap-visual-inner"><img src="https://picsum.photos/seed/aap-about-hero/1200/600" alt="آکادمی اسدزاده" loading="lazy"></div></div>
        </div>
    </section>

    <section class="aap-section aap-story" data-reveal>
        <div class="aap-story-grid">
            <div class="aap-story-copy">
                <h2>از سنت تا آینده</h2>
                <p>آکادمی اسدزاده با هدف آموزش تخصصی و کاربردی قالیبافی، گلیم‌بافی، گبه‌بافی و هنرهای سنتی ایران شکل گرفته است. ما مسیر یادگیری را از پایه طراحی کرده‌ایم تا هر هنرجو، بدون تجربه قبلی، بتواند قدم به قدم پیش برود و در پایان، اثری اصیل خلق کند.</p>
                <p>آموزش‌ها به صورت آنلاین و حضوری برگزار می‌شوند و پس از ثبت نام، دسترسی به دوره از طریق پیشخوان اختصاصی دانشجو در حساب کاربری فعال می‌شود.</p>
            </div>
            <div class="aap-story-stats">
                <div><strong>+20</strong><span>دوره آموزشی</span></div>
                <div><strong>+500</strong><span>هنرجوی فعال</span></div>
                <div><strong>20 سال</strong><span>تجربه آموزش</span></div>
                <div><strong>گواهی</strong><span>پایان دوره</span></div>
            </div>
        </div>
    </section>

    <section class="aap-section aap-master" data-reveal>
        <div class="aap-master-grid">
            <div class="aap-master-visual">
                <div class="aap-master-shell"><div class="aap-master-inner"><img src="https://picsum.photos/seed/ostad-about/800/1000" alt="استاد ناصر اسدزاده" loading="lazy"></div></div>
                <div class="aap-master-quote"><p>حفظ هنرهای سنتی، حفظ بخشی از هویت و فرهنگ ماست</p></div>
            </div>
            <div class="aap-master-copy">
                <h2>استاد ناصر اسدزاده</h2>
                <span class="aap-master-role">مدرس و بنیان‌گذار</span>
                <p>با بیش از دو دهه تجربه در آموزش قالیبافی، گلیم‌بافی و هنرهای بافت ایرانی، هدف استاد اسدزاده انتقال اصول صحیح و تجربه عملی این هنرها به نسل جدید هنرجویان است.</p>
                <ul class="aap-check-list">
                    <li><i></i> آموزش عملی و اصولی با تمرین و پروژه واقعی</li>
                    <li><i></i> همراهی هنرجو و پشتیبانی در طول مسیر یادگیری</li>
                    <li><i></i> ارائه گواهی پایان دوره برای دوره‌های واجد شرایط</li>
                </ul>
                <a class="aap-btn is-primary is-sm" href="<?php echo esc_url( $courses_url ); ?>"><span class="aap-btn-label">مشاهده دوره‌ها</span></a>
            </div>
        </div>
    </section>

    <section class="aap-section aap-why">
        <div class="aap-why-grid">
            <div class="aap-why-head" data-reveal><h2>چرا آکادمی اسدزاده</h2><p>آموزش اصولی هنرهای سنتی، از تجربه استاد تا همراهی هنرجو</p></div>
            <div class="aap-why-list">
                <div class="aap-why-item" data-reveal><span class="aap-why-num">01</span><div><h3>آموزش عملی و اصولی</h3><p>یادگیری با تمرین، پروژه و تجربه واقعی در کارگاه مجهز</p></div></div>
                <div class="aap-why-item" data-reveal style="--aap-delay: 80ms"><span class="aap-why-num">02</span><div><h3>مدرس باتجربه</h3><p>آموزش بر پایه سال‌ها تجربه عملی و آموزشی استاد اسدزاده</p></div></div>
                <div class="aap-why-item" data-reveal style="--aap-delay: 160ms"><span class="aap-why-num">03</span><div><h3>همراهی هنرجو</h3><p>پشتیبانی و راهنمایی در طول مسیر یادگیری تا خلق اثر نهایی</p></div></div>
                <div class="aap-why-item" data-reveal style="--aap-delay: 240ms"><span class="aap-why-num">04</span><div><h3>گواهی پایان دوره</h3><p>ارائه گواهی پس از تکمیل دوره‌های واجد شرایط</p></div></div>
            </div>
        </div>
    </section>
</div>
