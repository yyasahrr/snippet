=== Asadzadeh Smart Pages ===
Contributors: asadzadehacademy
Tags: tutor lms, woocommerce, elementor, academy, lms, rtl
Requires at least: 6.2
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

صفحه‌ساز هوشمند آکادمی اسدزاده — صفحه اصلی، دوره‌ها، درباره ما، تماس با ما با شورت‌کد و ویجت المنتور. مطابق ساختار Noghte Smart Headers.

== Description ==

افزونه صفحه‌ساز هوشمند آکادمی اسدزاده، دقیقاً با همان معماری **Noghte Smart Headers** ساخته شده است:

* فایل اصلی `asadzadeh-academy-builder.php` تعریف ثابت‌ها و singleton
* کلاس `AAP_Plugin` در `includes/class-aap-plugin.php` برای asset، شورت‌کد، المنتور
* کلاس `AAP_Renderer` در `includes/class-aap-renderer.php` برای رندر ۴ صفحه
* کلاس `AAP_Elementor` در `includes/class-aap-elementor.php` برای دسته و ویجت‌ها
* ۴ ویجت المنتور در `includes/widgets/`

### صفحات

* **صفحه اصلی** - Hero، آمار، دوره‌های ویژه (۳ ستونه ۱۶:۹)، دسته‌ها bento، چرا آکادمی، استاد، ادامه یادگیری (فقط لاگین با پیشرفت واقعی)، کارگاه‌ها، نظرات، FAQ، CTA نهایی
* **آرشیو دوره‌ها** - Hero + سرچ + فیلتر دسته + گرید دوره‌ها
* **درباره ما** - داستان، آمار، استاد، چرا آکادمی
* **تماس با ما** - فرم + اطلاعات + نقشه + FAQ

### شورت‌کدها

[asadzadeh_home courses="6" category=""]
[luxury_academy_home] // سازگاری با اسنیپت قبلی
[asadzadeh_courses courses="9" category="" search=""]
[asadzadeh_about]
[asadzadeh_contact]

### ویجت‌های المنتور

دسته: **آکادمی اسدزاده**

* صفحه اصلی آکادمی (aap-home)
* آرشیو دوره‌ها (aap-courses)
* درباره آکادمی (aap-about)
* تماس با آکادمی (aap-contact)

### سازگاری

* Tutor LMS منبع آموزشی (title, image, teacher, level, duration, enrollment, progress)
* WooCommerce منبع تجاری (price, cart, product) via _tutor_course_product_id
* سازگار با اسنیپت‌های موجود: snippetCart, snippetCheckout, snippetDashboard, snippetTutorCards

### طراحی

* پالت آجری #8f2e32 + کرم #fdfcfa + جنگلی #1f3128
* Vazirmatn
* double-bezel + bento + ۳ ستونه
* Motion سبک + prefers-reduced-motion + focus-visible

== Installation ==

1. پوشه `asadzadeh-academy-builder` را در `/wp-content/plugins/` آپلود کنید
2. از پیشخوان > افزونه‌ها فعال کنید
3. در برگه‌ای شورت‌کد را قرار دهید یا در المنتور از دسته آکادمی اسدزاده ویجت را بکشید

== Frequently Asked Questions ==

= آیا بدون Tutor LMS کار می‌کند؟ =
بله، ولی بخش دوره‌ها خالی نمایش داده می‌شود و پیام راهنما ظاهر می‌شود. بدون خطای fatal.

= آیا با WPCode snippetHomepage.php تداخل دارد؟ =
خیر. هر دو شورت‌کد یکسان را ثبت می‌کنند و افزونه اولویت نهایی را دارد. کلاس‌ها با پیشوند AAP_ هستند و تداخل ندارند.

= چطور فرم تماس را واقعی کنم؟ =
در `includes/class-aap-renderer.php` متد `render_contact` فرم نمایشی دارد. شورت‌کد فرم‌ساز خود را جایگزین کنید یا از ویجت فرم المنتور استفاده کنید.

== Changelog ==

= 1.0.0 =
* نسخه اول مطابق ساختار Noghte Smart Headers
* ۴ صفحه‌ساز + ۴ شورت‌کد + ۴ ویجت المنتور
* دیتا لیر Tutor + Woo با batch fetch
* کارت دوره ۶ حالته (نخریده، در سبد، دسترسی فعال، در حال یادگیری، تکمیل‌شده، رایگان)

== Upgrade Notice ==

= 1.0.0 =
نسخه اول - نصب کنید و از دسته آکادمی اسدزاده در المنتور استفاده کنید.
