=== Noghte Smart Headers ===
Contributors: noghtegroup
Tags: header, elementor, menu, woocommerce, responsive, rtl
Requires at least: 6.2
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

ده هدر حرفه‌ای، سبک و فول‌ریسپانسیو با شورت‌کد و ویجت اختصاصی المنتور.

== امکانات ==

* ده طراحی واقعی و متفاوت: کلاسیک، لوگوی مرکزی، فروشگاهی، شیشه‌ای، آف‌کانواس، کپسولی، Split، ادیتوریال، Accent Rail و فروشگاهی Pro
* ویجت اختصاصی Elementor در دسته «گروه نقطه»
* دو شورت‌کد هم‌معنی: [noghte_header] و [ngt_header]
* انتخاب منوی وردپرس و لوگوی اختصاصی
* پاپ‌آپ جست‌وجوی سبک بدون AJAX
* جست‌وجو در کل سایت، محصولات یا نوشته‌ها
* لینک حساب کاربری و سبد خرید ووکامرس
* شمارنده سبد خرید سازگار با WooCommerce fragments
* رنگ مستقل آیکون جست‌وجو و منوی همبرگری
* کنترل اندازه آیکون دسکتاپ و موبایل
* کنترل نقطه شکست ریسپانسیو و ارتفاع هدر موبایل
* نمایش یا مخفی‌کردن جداگانه جست‌وجو، سبد و پروفایل در موبایل
* منوی چندسطحی دسکتاپ و منوی آکاردئونی موبایل
* Focus Trap، بستن با Escape و مدیریت صحیح aria
* پشتیبانی RTL و LTR
* SVG داخلی؛ بدون Font Awesome، Bootstrap، jQuery یا کتابخانه خارجی
* استایل‌ها با پیشوند اختصاصی ngt-hdr-
* بدون جدول دیتابیس و بدون تنظیمات اضافی در مدیریت

== نصب ==

1. فایل ZIP را از مسیر افزونه‌ها > افزودن > بارگذاری افزونه نصب کنید.
2. اگر نسخه قبلی نصب است، فایل جدید را روی همان افزونه جایگزین کنید.
3. افزونه «Noghte Smart Headers» را فعال نگه دارید.
4. در Elementor از ویجت «هدر حرفه‌ای نقطه» در دسته «گروه نقطه» استفاده کنید.
5. اگر قالب خودش هدر دارد، هدر پیش‌فرض قالب را برای همان صفحه یا قالب سراسری غیرفعال کنید.

== مدل‌های هدر ==

1. کلاسیک حرفه‌ای
2. لوگوی مرکزی دو ردیفه
3. فروشگاهی با نوار رنگی
4. شناور شیشه‌ای
5. مینیمال با منوی کناری
6. کپسولی مدرن
7. لوکس Split با لوگوی مرکزی
8. ادیتوریال دو ردیفه
9. Accent Rail خلاقانه
10. فروشگاهی Pro

== نمونه شورت‌کدها ==

مدل کپسولی:
[noghte_header style="6" menu="primary" sticky="yes" accent_color="#2f6f58"]

مدل Split:
[noghte_header style="7" menu="primary" logo_id="123" mobile_breakpoint="1100"]

مدل ادیتوریال:
[noghte_header style="8" menu="primary" height="96" text_color="#171717"]

مدل Accent Rail:
[noghte_header style="9" menu="primary" accent_color="#7a563b" menu_icon_color="#7a563b"]

مدل فروشگاهی Pro:
[noghte_header style="10" menu="main-menu" search_type="product" cta_text="فروشگاه" cta_url="/shop/"]

نمونه کامل:
[noghte_header
 style="6"
 menu="primary"
 logo_id="123"
 logo_width="150"
 mobile_logo_width="112"
 search="yes"
 search_type="product"
 cart="yes"
 account="yes"
 mobile_search="yes"
 mobile_cart="yes"
 mobile_account="no"
 cta_text="مشاهده محصولات"
 cta_url="/shop/"
 cta_target="_self"
 sticky="yes"
 transparent="no"
 full_width="no"
 container_width="1280"
 height="78"
 mobile_height="64"
 mobile_breakpoint="1024"
 icon_size="21"
 mobile_icon_size="19"
 background="#ffffff"
 text_color="#15171a"
 muted_color="#68707a"
 accent_color="#2f6f58"
 border_color="#e9ecef"
 icon_color="#15171a"
 search_icon_color="#c17c42"
 menu_icon_color="#2f6f58"
 close_icon_color="#15171a"
 icon_hover_color="#2f6f58"
 custom_class="my-custom-header"
]

== پارامترهای شورت‌کد ==

style: عدد 1 تا 10
menu: شناسه، نام، slug یا location منوی وردپرس
logo_id: شناسه تصویر در رسانه وردپرس
logo_url: آدرس مستقیم لوگو؛ اگر logo_id وارد شده باشد اولویت با logo_id است
logo_alt: متن جایگزین لوگو
logo_width: عرض لوگوی دسکتاپ بین 50 تا 320
mobile_logo_width: عرض لوگوی موبایل بین 50 تا 240
search: yes/no
search_type: all/product/post
cart: yes/no
account: yes/no
mobile_search: نمایش جست‌وجو در حالت موبایل؛ yes/no
mobile_cart: نمایش سبد در حالت موبایل؛ yes/no
mobile_account: نمایش پروفایل در حالت موبایل؛ yes/no
cta_text: متن دکمه فراخوان
cta_url: لینک دکمه فراخوان
cta_target: _self یا _blank
sticky: yes/no
transparent: yes/no
full_width: yes/no
container_width: حداکثر عرض محتوا بین 720 تا 1920
height: ارتفاع دسکتاپ بین 56 تا 140
mobile_height: ارتفاع موبایل بین 54 تا 100
mobile_breakpoint: نقطه تبدیل منوی دسکتاپ به موبایل بین 640 تا 1280
icon_size: اندازه آیکون دسکتاپ بین 15 تا 34
mobile_icon_size: اندازه آیکون موبایل بین 15 تا 30
background: رنگ پس‌زمینه
text_color: رنگ متن و منو
muted_color: رنگ ثانویه
accent_color: رنگ اصلی
border_color: رنگ خطوط
icon_color: رنگ آیکون‌های عمومی مانند سبد و پروفایل
search_icon_color: رنگ مستقل آیکون جست‌وجو
menu_icon_color: رنگ مستقل منوی همبرگری
close_icon_color: رنگ مستقل دکمه بستن پاپ‌آپ جست‌وجو و منوی موبایل
icon_hover_color: رنگ هاور تمام آیکون‌ها
custom_class: کلاس‌های اضافه دلخواه

== نکات ریسپانسیو ==

* برای منوهای شلوغ، mobile_breakpoint را روی 1100 یا 1280 قرار دهید.
* اگر فضای موبایل کم است، mobile_account را no کنید.
* هیچ دکمه‌ای به‌صورت اجباری در عرض 390 پیکسل مخفی نمی‌شود؛ نمایش هر بخش تحت کنترل شماست.
* عرض لوگو در موبایل علاوه بر مقدار انتخابی، به‌صورت خودکار با فضای موجود محدود می‌شود تا اسکرول افقی ایجاد نشود.

== توسعه‌دهندگان ==

دارایی‌ها فاقد dependency خارجی هستند و از CSS و JavaScript خام استفاده می‌کنند.

برای جلوگیری از بارگذاری سراسری و اتکا به enqueue زمان رندر:

add_filter( 'ngt_hdr_enqueue_assets', '__return_false' );

نسخه افزونه: NGT_HDR_VERSION
پیشوند PHP: NGT_HDR_
پیشوند CSS/JS: ngt-hdr-

== Changelog ==


= 1.2.0 =
* نسخه نهایی کنترل رنگ آیکون‌ها.
* افزودن ID یکتا و کلاس اختصاصی برای دکمه جست‌وجو، همبرگری، بستن جست‌وجو و بستن منوی موبایل.
* اجبار SVGها به استفاده از currentColor برای جلوگیری از سفیدشدن توسط استایل قالب یا المنتور.
* افزودن کنترل مستقل رنگ آیکون بستن در المنتور و شورت‌کد با گزینه close_icon_color.
* تکمیل گزینه‌های ریسپانسیو و رنگ آیکون‌ها در شورت‌کد.

= 1.1.0 =
* افزایش مدل‌های هدر از 5 به 10.
* افزودن کنترل مستقل رنگ آیکون جست‌وجو و همبرگری.
* افزودن کنترل اندازه آیکون در دسکتاپ و موبایل.
* افزودن کنترل breakpoint و ارتفاع هدر موبایل.
* افزودن کنترل نمایش اکشن‌ها در موبایل.
* اصلاح overflow و اسکرول افقی در عرض‌های کوچک.
* اصلاح عرض پنل موبایل و مودال جست‌وجو.
* تبدیل زیرمنوهای موبایل به آکاردئون.
* افزودن Focus Trap و بهبود دسترسی‌پذیری.
* بهبود RTL، زیرمنوهای دسکتاپ و بسته‌شدن منو هنگام تغییر عرض.

= 1.0.0 =
* انتشار اولیه با پنج هدر، شورت‌کد، Elementor، WooCommerce، جست‌وجو و طراحی واکنش‌گرا.
