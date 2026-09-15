<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
$courses_url = AAP_Plugin::get_courses_url();
$admin_email = antispambot( sanitize_email( get_option( 'admin_email' ) ) );
?>
<div id="<?php echo esc_attr( $instance ); ?>" class="aap-contact-page aap-elementor-compatible" dir="rtl">
    <link rel="stylesheet" href="<?php echo esc_url( AAP_URL . 'assets/css/frontend.css' ); ?>">
    
    <section class="aap-page-hero is-contact" data-reveal>
        <div class="aap-page-hero-inner">
            <span class="aap-eyebrow">ارتباط با ما</span>
            <h1>همراه مسیر یادگیری شما هستیم</h1>
            <p>اگر درباره دوره‌ها، کارگاه‌های حضوری یا دسترسی به پیشخوان سوالی دارید، با ما در ارتباط باشید</p>
        </div>
    </section>

    <section class="aap-section aap-contact-grid">
        <div class="aap-contact-form-card" data-reveal>
            <div class="aap-contact-shell"><div class="aap-contact-inner">
                <h2>پیام خود را ارسال کنید</h2>
                <p>فرم زیر را پر کنید تا تیم پشتیبانی آکادمی با شما تماس بگیرد</p>
                <form class="aap-form" action="#" method="post" onsubmit="return false;">
                    <div class="aap-form-row is-double">
                        <label><span>نام</span><input type="text" name="name" placeholder="نام شما" required></label>
                        <label><span>شماره تماس</span><input type="tel" name="phone" placeholder="09xxxxxxxxx" required></label>
                    </div>
                    <label><span>ایمیل</span><input type="email" name="email" placeholder="example@mail.com"></label>
                    <label><span>موضوع</span><select name="subject"><option>سوال درباره دوره‌ها</option><option>کارگاه حضوری</option><option>مشکل در دسترسی دوره</option><option>سایر</option></select></label>
                    <label><span>پیام</span><textarea name="message" rows="5" placeholder="پیام خود را بنویسید..." required></textarea></label>
                    <button type="submit" class="aap-btn is-primary"><span class="aap-btn-label">ارسال پیام</span></button>
                    <span class="aap-form-note">این فرم نمایشی است. برای اتصال به افزونه فرم‌ساز، شورت‌کد فرم خود را جایگزین کنید.</span>
                </form>
            </div></div>
        </div>
        <div class="aap-contact-info" data-reveal style="--aap-delay: 120ms">
            <div class="aap-info-card"><strong>ایمیل پشتیبانی</strong><a href="mailto:<?php echo esc_attr( $admin_email ); ?>"><?php echo esc_html( $admin_email ); ?></a><span>پاسخگویی در ساعات کاری</span></div>
            <div class="aap-info-card"><strong>دوره‌ها</strong><a href="<?php echo esc_url( $courses_url ); ?>">مشاهده همه دوره‌ها</a><span>آنلاین و حضوری</span></div>
            <div class="aap-info-card"><strong>پیشخوان دانشجو</strong><a href="<?php echo esc_url( AAP_Plugin::get_dashboard_url() ); ?>">ورود به دوره‌های من</a><span>مشاهده پیشرفت و ادامه یادگیری</span></div>
            <div class="aap-map-card">
                <div class="aap-map-placeholder">
                    <span>نقشه کارگاه حضوری</span>
                    <small>محل برگزاری کارگاه‌ها در صفحه هر کارگاه اعلام می‌شود</small>
                </div>
            </div>
        </div>
    </section>

    <section class="aap-section aap-faq">
        <div class="aap-faq-grid">
            <div class="aap-faq-head" data-reveal><h2>سوالات متداول</h2><p>پاسخ سوال‌هایی که ممکن است قبل از ثبت نام داشته باشید</p></div>
            <div class="aap-faq-list" data-reveal style="--aap-delay: 100ms">
                <details class="aap-faq-item" open><summary>آیا برای شرکت در دوره‌ها نیاز به تجربه قبلی دارم<span></span></summary><p>خیر. دوره‌های مقدماتی از پایه طراحی شده‌اند.</p></details>
                <details class="aap-faq-item"><summary>دوره‌ها آنلاین هستند یا حضوری<span></span></summary><p>بسته به دوره، آنلاین یا حضوری هستند و نوع برگزاری در صفحه دوره مشخص شده.</p></details>
                <details class="aap-faq-item"><summary>بعد از ثبت نام چطور به دوره دسترسی پیدا می‌کنم<span></span></summary><p>پس از تکمیل ثبت نام، دوره در حساب کاربری شما فعال می‌شود.</p></details>
            </div>
        </div>
    </section>
</div>
