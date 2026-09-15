# Asadzadeh Academy - Smart Pages Builder

افزونه صفحه‌ساز هوشمند آکادمی اسدزاده - مطابق ساختار **Noghte Smart Headers** (هدر و فوتر ساز) ساخته شده.

## ساختار (مثل Noghte Smart Headers)

```
asadzadeh-academy-builder/
  asadzadeh-academy-builder.php (main - مثل noghte-smart-headers.php)
  includes/
    class-aap-plugin.php (singleton - مثل class-ngt-hdr-plugin.php)
    class-aap-data.php (data layer Tutor+Woo)
    class-aap-renderer.php (renderer)
    class-aap-shortcodes.php (shortcodes)
    widgets/
      class-widget-homepage.php
      class-widget-courses.php
      class-widget-about.php
      class-widget-contact.php
  templates/
    homepage.php
    courses.php
    about.php
    contact.php
  assets/
    css/frontend.css
    js/frontend.js
```

## شورت‌کدها

```
[asadzadeh_home]
[asadzadeh_home courses="6" category="قالیبافی"]
[luxury_academy_home] // backward compat

[asadzadeh_courses]
[asadzadeh_courses courses="9" category=""]

[asadzadeh_about]
[asadzadeh_contact]
```

## ویجت‌های المنتور

دسته: **آکادمی اسدزاده**

- صفحه اصلی آکادمی
- آرشیو دوره‌ها
- درباره آکادمی
- تماس با آکادمی

## نصب

1. پوشه `asadzadeh-academy-builder` را در `/wp-content/plugins/` آپلود کن
2. از پیشخوان > افزونه‌ها فعال کن
3. در المنتور ویجت‌ها در دسته آکادمی اسدزاده ظاهر می‌شوند
4. یا شورت‌کد را در برگه بگذار

## سازگاری

- Tutor LMS منبع آموزشی (title, image, teacher, level, duration, enrollment, progress)
- WooCommerce منبع تجاری (price, cart, coupon) - اتصال via `_tutor_course_product_id`
- سازگار با اسنیپت‌های قبلی:
  - snippetCart.php (luxury_ajax_cart)
  - snippetCheckout.php (luxury_checkout)
  - snippetDashboard.php (my-courses endpoint)
  - snippetTutorCards.php (owned cards)

## طراحی

- پالت آجری/زرشکی #8f2e32 + کرم #fdfcfa + جنگلی #1f3128
- Vazirmatn
- double-bezel، bento، 3 ستونه دسکتاپ، 2 تبلت، 1 موبایل
- Motion سبک با IntersectionObserver + prefers-reduced-motion
