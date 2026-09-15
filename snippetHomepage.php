/**
 * Asadzadeh Academy - Complete Homepage - Tutor LMS + WooCommerce + Elementor + WPCode
 * Frontend-Design Skill Applied:
 * Subject: Persian hand-weaving academy - tactile, slow craft, loom & wool, not generic LMS
 * Audience: Persian learners seeking authentic skill & cultural identity
 * Job: Build trust via material honesty, convert to enrollment
 * Tokens: Warp Ink #161412, Wool Paper #F6F1E8, Madder Root #7A1F1E, Indigo Loom #22344E, Saffron Thread #C9A86A, Loom Line #E7DDD0
 * Type: Vazirmatn 400/700/900 + Amiri for quotes - intentional, not default
 * Layout: Left-aligned editorial, loom grid, varied hierarchy (featured full-width + 2-col), threads not bento cards, no SaaS-card kit
 * Principles: Tactile over glossy (borders not shadows), one bold moment (hero loom image with grain), restraint (no ALL-CAPS eyebrow, no middle-dot meta, no → everywhere, no pill everywhere)
 *
 * Architecture: Tutor edu source, Woo commercial source via _tutor_course_product_id, batch fetch, no N+1
 * Compatible: snippetCart (luxury_ajax_cart), snippetCheckout (luxury_checkout), snippetDashboard (my-courses), snippetTutorCards (owned)
 * Shortcode: [luxury_academy_home] and [asadzadeh_home] with courses="6" category=""
 * Install WPCode without <?php tag
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Asadzadeh_Academy_Home' ) ) {

    class Asadzadeh_Academy_Home {

        const SHORTCODE_NEW = 'asadzadeh_home';
        const SHORTCODE_OLD = 'luxury_academy_home';
        private static $instance = 0;

        public static function init() {
            add_shortcode( self::SHORTCODE_NEW, array( __CLASS__, 'render' ) );
            add_shortcode( self::SHORTCODE_OLD, array( __CLASS__, 'render' ) );
        }

        private static function tutor_available(){ return function_exists('tutor') && function_exists('tutor_utils'); }
        private static function woocommerce_available(){ return function_exists('WC') && class_exists('WooCommerce'); }
        private static function course_post_type(){
            if(self::tutor_available() && !empty(tutor()->course_post_type)) return sanitize_key(tutor()->course_post_type);
            return post_type_exists('courses') ? 'courses' : 'tutor_course';
        }
        private static function course_taxonomy(){
            if(self::tutor_available() && !empty(tutor()->course_taxonomy)) return sanitize_key(tutor()->course_taxonomy);
            foreach(array('course-category','course_cat','tutor_course_category') as $tax){ if(taxonomy_exists($tax)) return $tax; }
            return '';
        }
        private static function is_featured_course($cid){
            foreach(array('_tutor_course_featured','_tutor_is_featured','is_featured') as $k){
                if(in_array(strtolower((string)get_post_meta($cid,$k,true)), array('1','yes','true','on'), true)) return true;
            }
            return false;
        }
        private static function course_ids($limit,$category=''){
            $args=array('post_type'=>self::course_post_type(),'post_status'=>'publish','posts_per_page'=>max(12,min(60,$limit*4)),'orderby'=>'date','order'=>'DESC','no_found_rows'=>true,'ignore_sticky_posts'=>true,'fields'=>'ids','update_post_meta_cache'=>true,'update_post_term_cache'=>true);
            $tax=self::course_taxonomy();
            if($tax && $category){
                $term=term_exists($category,$tax);
                if($term) $args['tax_query']=array(array('taxonomy'=>$tax,'field'=>is_numeric($category)?'term_id':'slug','terms'=>is_numeric($category)?absint($category):sanitize_title($category)));
            }
            $ids=get_posts($args);
            $ids=array_values(array_filter(array_map('absint',$ids)));
            usort($ids,function($l,$r){
                $lf=self::is_featured_course($l)?1:0; $rf=self::is_featured_course($r)?1:0;
                if($lf!==$rf) return $rf<=>$lf;
                $lo=(int)get_post_field('menu_order',$l); $ro=(int)get_post_field('menu_order',$r);
                if($lo!==$ro) return $lo<=>$ro;
                return (int)get_post_time('U',true,$r) <=> (int)get_post_time('U',true,$l);
            });
            return array_slice($ids,0,$limit);
        }
        private static function product_map($cids){
            $map=array(); $pids=array();
            foreach($cids as $cid){ $pid=absint(get_post_meta($cid,'_tutor_course_product_id',true)); $map[$cid]=$pid; if($pid) $pids[]=$pid; }
            if(!self::woocommerce_available() || empty($pids)) return array_fill_keys(array_keys($map), false);
            $products=wc_get_products(array('include'=>array_values(array_unique($pids)),'limit'=>-1,'status'=>array('publish','private'),'return'=>'objects'));
            $byId=array(); foreach($products as $p){ if(is_object($p) && method_exists($p,'get_id')) $byId[(int)$p->get_id()]=$p; }
            foreach($map as $cid=>$pid){ $map[$cid]=$pid && isset($byId[$pid]) ? $byId[$pid] : false; }
            return $map;
        }
        private static function enrolled_ids(){
            if(!is_user_logged_in() || !self::tutor_available()) return array();
            $enrolled=tutor_utils()->get_enrolled_courses_by_user(get_current_user_id(), array('publish','private'));
            $posts=$enrolled instanceof WP_Query ? $enrolled->posts : (is_array($enrolled)?$enrolled:array());
            $ids=array(); foreach($posts as $c){ $id=is_object($c)&&isset($c->ID)?$c->ID:$c; if(absint($id)) $ids[]=absint($id); }
            return array_values(array_unique($ids));
        }
        private static function progress($cid){
            if(!self::tutor_available() || !is_user_logged_in()) return array('percent'=>0,'completed'=>0,'total'=>0);
            $stats=tutor_utils()->get_course_completed_percent($cid,get_current_user_id(),true);
            if(!is_array($stats)) return array('percent'=>max(0,min(100,(int)$stats)),'completed'=>0,'total'=>0);
            return array('percent'=>max(0,min(100,(int)($stats['completed_percent']??0))),'completed'=>absint($stats['completed_count']??0),'total'=>absint($stats['total_count']??0));
        }
        private static function continue_url($cid,$percent){
            $c_url=get_permalink($cid);
            if($percent<=0 || !self::tutor_available()) return $c_url;
            $lesson=tutor_utils()->get_course_first_lesson($cid);
            if(is_numeric($lesson)) $lesson=get_permalink(absint($lesson));
            elseif($lesson instanceof WP_Post) $lesson=get_permalink($lesson->ID);
            return $lesson ? $lesson : $c_url;
        }
        private static function course_detail($cid,$kind){
            if(self::tutor_available()){
                $m='level'===$kind?'get_course_level':'get_course_duration_context';
                if(is_object(tutor_utils()) && method_exists(tutor_utils(),$m)){
                    $v=tutor_utils()->$m($cid);
                    if(is_scalar($v) && ''!==trim((string)$v)) return wp_strip_all_tags((string)$v);
                }
            }
            $keys='level'===$kind?array('_tutor_course_level','course_level'):array('_tutor_course_duration','course_duration');
            foreach($keys as $k){ $v=get_post_meta($cid,$k,true); if(is_scalar($v) && ''!==trim((string)$v)) return wp_strip_all_tags((string)$v); }
            return '';
        }
        private static function cart_contains($pid){
            if(!$pid || !self::woocommerce_available()) return false;
            if(function_exists('wc_load_cart') && (!WC()->cart || !WC()->session)) wc_load_cart();
            if(!WC()->cart) return false;
            foreach(WC()->cart->get_cart() as $item){ if($pid===absint($item['product_id']??0) || $pid===absint($item['variation_id']??0)) return true; }
            return false;
        }
        private static function course_data($cids){
            if(empty($cids)) return array();
            $products=self::product_map($cids);
            $enrolled=self::enrolled_ids();
            $aids=array(); foreach($cids as $cid) $aids[]=(int)get_post_field('post_author',$cid);
            $authors=array(); foreach(array_unique($aids) as $aid) $authors[$aid]=get_the_author_meta('display_name',$aid);
            $rows=array();
            foreach($cids as $cid){
                $product=$products[$cid]??false;
                $prog=self::progress($cid);
                $is_enrolled=in_array($cid,$enrolled,true);
                $is_free=!$product || (method_exists($product,'is_free')?$product->is_free():(float)$product->get_price()<=0);
                $tax=self::course_taxonomy();
                $terms=$tax?get_the_terms($cid,$tax):array();
                $tnames=is_array($terms)?wp_list_pluck(array_slice($terms,0,2),'name'):array();
                $img=get_the_post_thumbnail_url($cid,'medium_large');
                $c_url=get_permalink($cid);
                $a_url=$is_enrolled?self::continue_url($cid,$prog['percent']):$c_url;
                $rows[]=array('id'=>$cid,'title'=>get_the_title($cid),'url'=>$c_url,'action_url'=>$a_url,'image'=>$img,'author'=>$authors[(int)get_post_field('post_author',$cid)]??'','terms'=>$tnames,'product'=>$product,'is_free'=>$is_free,'is_enrolled'=>$is_enrolled,'progress'=>$prog,'in_cart'=>$product&&self::cart_contains($product->get_id()));
            }
            return $rows;
        }
        private static function price_html($product){
            if(!$product || !is_object($product)) return '<span class="aa-price-free">رایگان</span>';
            return wp_kses_post($product->get_price_html());
        }
        private static function learning_categories(){
            $tax=self::course_taxonomy(); if(!$tax) return array();
            $terms=get_terms(array('taxonomy'=>$tax,'hide_empty'=>true,'number'=>8,'orderby'=>'count','order'=>'DESC'));
            return is_wp_error($terms)?array():$terms;
        }
        private static function active_courses($limit=3){ $ids=array_slice(self::enrolled_ids(),0,$limit); return $ids?self::course_data($ids):array(); }

        private static function course_card($row,$index,$featured=false){
            $prog=$row['progress']; $status=''; $label=''; $url=$row['action_url']; $owned='';
            if($row['is_enrolled']){
                $owned='is-owned';
                if($prog['percent']>=100){ $status='تکمیل شده'; $label='مرور دوره'; }
                elseif($prog['percent']>0){ $status='در حال یادگیری'; $label='ادامه دوره'; }
                else{ $status='دسترسی فعال'; $label='مشاهده دوره'; }
            }elseif($row['in_cart']){ $status='در سبد خرید'; $label='مشاهده سبد'; $url=function_exists('wc_get_cart_url')?wc_get_cart_url():home_url('/'); }
            elseif($row['is_free']){ $status='رایگان'; $label=is_user_logged_in()?'شروع دوره':'ورود برای ثبت نام'; $url=is_user_logged_in()?$row['url']:(function_exists('wc_get_page_permalink')?wc_get_page_permalink('myaccount'):wp_login_url($row['url'])); }
            else{ $status='ثبت نام'; $label='افزودن به سبد'; $url=$row['product']?add_query_arg('add-to-cart',$row['product']->get_id(),home_url('/')):$row['url']; }
            ?>
            <article class="aa-course <?php echo esc_attr($owned); ?> <?php echo $featured?'is-featured':''; ?>" data-reveal style="--aa-delay: <?php echo esc_attr(min($index*60,300)); ?>ms">
                <a class="aa-course-media" href="<?php echo esc_url($row['url']); ?>">
                    <?php if($row['image']): ?><img src="<?php echo esc_url($row['image']); ?>" alt="<?php echo esc_attr($row['title']); ?>" loading="lazy">
                    <?php else: ?><img src="https://picsum.photos/seed/<?php echo esc_attr('aa-'.$row['id']); ?>/640/480" alt="<?php echo esc_attr($row['title']); ?>" loading="lazy"><?php endif; ?>
                    <span class="aa-course-badge <?php echo esc_attr($owned); ?>"><?php echo esc_html($status); ?></span>
                </a>
                <div class="aa-course-body">
                    <h3><a href="<?php echo esc_url($row['url']); ?>"><?php echo esc_html($row['title']); ?></a></h3>
                    <p class="aa-course-teacher"><?php echo esc_html($row['author']?'مدرس: '.$row['author']:'آکادمی اسدزاده'); ?></p>
                    <div class="aa-course-meta">
                        <span><?php echo esc_html(self::course_detail($row['id'],'level')?:'همه سطوح'); ?></span>
                        <?php $d=self::course_detail($row['id'],'duration'); if($d): ?><span><?php echo esc_html($d); ?></span><?php endif; ?>
                        <?php if($row['terms']): ?><span><?php echo esc_html(implode(' / ',$row['terms'])); ?></span><?php endif; ?>
                    </div>
                    <?php if($row['is_enrolled']): ?>
                        <div class="aa-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr($prog['percent']); ?>">
                            <div class="aa-progress-head"><span>پیشرفت</span><strong><?php echo esc_html($prog['percent']); ?>٪</strong></div>
                            <span class="aa-progress-track"><i style="width:<?php echo esc_attr($prog['percent']); ?>%"></i></span>
                            <div class="aa-progress-meta"><span><?php echo esc_html($prog['completed']); ?> از <?php echo esc_html($prog['total']); ?> بخش</span></div>
                        </div>
                    <?php elseif(!$row['is_free']): ?>
                        <div class="aa-course-price"><?php echo self::price_html($row['product']); ?></div>
                    <?php else: ?>
                        <div class="aa-course-price"><span class="aa-price-free">رایگان</span></div>
                    <?php endif; ?>
                    <div class="aa-course-actions">
                        <a class="aa-btn <?php echo $row['is_enrolled']?'is-secondary':'is-primary'; ?> is-sm" href="<?php echo esc_url($url); ?>"><span><?php echo esc_html($label); ?></span></a>
                        <a class="aa-btn is-secondary is-sm" href="<?php echo esc_url($row['url']); ?>"><span>جزئیات</span></a>
                    </div>
                </div>
            </article>
            <?php
        }

        public static function render($atts=array()){
            $atts=shortcode_atts(array('courses'=>6,'category'=>''),$atts,self::SHORTCODE_NEW);
            $limit=max(1,min(12,absint($atts['courses'])?:6));
            $category=sanitize_text_field((string)$atts['category']);
            $instance='aa-home-'.(++self::$instance);
            $course_rows=self::tutor_available()?self::course_data(self::course_ids($limit,$category)):array();
            $categories=self::tutor_available()?self::learning_categories():array();
            $active=is_user_logged_in()&&self::tutor_available()?self::active_courses(3):array();
            $account_url=function_exists('wc_get_page_permalink')?wc_get_page_permalink('myaccount'):wp_login_url();
            $courses_url=home_url('/doreha/');
            if(self::tutor_available() && is_object(tutor_utils()) && method_exists(tutor_utils(),'get_courses_page_url')){
                $maybe=tutor_utils()->get_courses_page_url(); if($maybe) $courses_url=$maybe;
            }
            $dashboard_url=function_exists('wc_get_account_endpoint_url')?wc_get_account_endpoint_url('my-courses'):home_url('/my-account/my-courses/');
            $fallback_cats=array(
                array('name'=>'قالی بافی','desc'=>'آموزش حرفه‌ای از اولین گره تا بافت کامل','count'=>'12'),
                array('name'=>'گلیم بافی','desc'=>'نقش، رنگ و بافت گلیم‌های اصیل','count'=>'8'),
                array('name'=>'گبه بافی','desc'=>'از پشم تا نقش، ساده و کاربردی','count'=>'6'),
                array('name'=>'هنرهای سنتی','desc'=>'آشنایی با هنرهای اصیل ایرانی','count'=>'10'),
            );
            ob_start();
            ?>
            <div id="<?php echo esc_attr($instance); ?>" class="aa-home" dir="rtl" data-aap-page="home">
                <?php echo self::assets($instance); ?>
                <?php if(!self::tutor_available() || !self::woocommerce_available()): ?>
                    <div class="aa-system-notice" role="alert">برای نمایش کامل، Tutor LMS و WooCommerce باید فعال باشند.</div>
                <?php endif; ?>

                <section class="aa-hero" aria-labelledby="<?php echo esc_attr($instance.'-title'); ?>">
                    <div class="aa-wrap">
                        <div class="aa-hero-grid">
                            <div class="aa-hero-media" data-reveal>
                                <img src="https://asadzadehacademy.ir/wp-content/uploads/2026/09/%DA%A9%D8%A7%D8%B1%DA%AF%D8%A7%D9%87-%D9%82%D8%A7%D9%84%DB%8C%D8%A8%D8%A7%D9%81%DB%8C-1024x768.png" alt="کارگاه قالیبافی" loading="eager" decoding="async" onerror="this.src='https://picsum.photos/seed/asadzadeh-hero/800/600'">
                            </div>
                            <div class="aa-hero-copy" data-reveal style="--aa-delay: 80ms">
                                <span class="aa-kicker is-madder">تجربه‌ای که منتقل می‌شود</span>
                                <h1 id="<?php echo esc_attr($instance.'-title'); ?>">به آکادمی اسدزاده خوش آمدید</h1>
                                <p>آموزش حرفه‌ای قالیبافی، گلیم‌بافی و گبه‌بافی — از اولین گره تا خلق اثری اصیل، با تکیه بر دو دهه تجربه کارگاهی.</p>
                                <div class="aa-hero-actions">
                                    <a class="aa-btn is-primary" href="<?php echo esc_url($courses_url); ?>"><span>مشاهده دوره‌ها</span></a>
                                    <a class="aa-btn is-secondary" href="<?php echo esc_url(is_user_logged_in()?$dashboard_url:$account_url); ?>"><span><?php echo esc_html(is_user_logged_in()?'ادامه یادگیری':'ورود به پیشخوان'); ?></span></a>
                                </div>
                                <div class="aa-hero-marginalia">
                                    <div class="aa-marginalia-item"><strong>گواهی معتبر</strong><span>پس از اتمام دوره</span></div>
                                    <div class="aa-marginalia-item"><strong>کارگاه مجهز</strong><span>حضوری و آنلاین</span></div>
                                    <div class="aa-marginalia-item"><strong>+500 هنرجو</strong><span>همراه مسیر یادگیری</span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="aa-metrics" aria-label="آمار">
                    <div class="aa-wrap">
                        <div class="aa-metrics-grid">
                            <div class="aa-metric" data-reveal><strong>+20</strong><span>دوره آموزشی</span></div>
                            <div class="aa-metric" data-reveal style="--aa-delay: 60ms"><strong>+500</strong><span>هنرجوی فعال</span></div>
                            <div class="aa-metric" data-reveal style="--aa-delay: 120ms"><strong>20 سال</strong><span>تجربه آموزش</span></div>
                            <div class="aa-metric" data-reveal style="--aa-delay: 180ms"><strong>گواهی</strong><span>پایان دوره معتبر</span></div>
                        </div>
                    </div>
                </section>

                <section class="aa-section">
                    <div class="aa-wrap">
                        <div class="aa-section-head">
                            <div><span class="aa-kicker is-madder">دوره‌های ویژه</span><h2>برای قدم بعدی یادگیری</h2></div>
                            <a class="aa-btn is-secondary is-sm" href="<?php echo esc_url($courses_url); ?>"><span>همه دوره‌ها</span></a>
                        </div>
                        <?php if($course_rows):
                            $first=array_shift($course_rows);
                            if($first){ echo '<div class="aa-course-list" style="margin-bottom:1px">'; self::course_card($first,0,true); echo '</div>'; }
                            if($course_rows){ echo '<div class="aa-course-grid">'; foreach($course_rows as $i=>$r) self::course_card($r,$i+1,false); echo '</div>'; }
                        else: ?>
                            <div class="aa-empty" data-reveal><h3>هنوز دوره‌ای منتشر نشده</h3><p>به زودی دوره‌های قالیبافی و گلیم‌بافی اینجا قرار می‌گیرد</p></div>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="aa-section">
                    <div class="aa-wrap">
                        <div class="aa-section-head"><h2>دسته‌های آموزشی</h2><a class="aa-btn is-secondary is-sm" href="<?php echo esc_url($courses_url); ?>"><span>مشاهده همه</span></a></div>
                        <div class="aa-threads" data-reveal>
                            <?php
                            $cats_to_show=$categories?array_slice($categories,0,4):array();
                            if($cats_to_show){
                                foreach($cats_to_show as $term){
                                    $link=get_term_link($term); $name=$term->name; $count=$term->count; $desc=$term->description?wp_trim_words($term->description,10):'آموزش تخصصی و کاربردی';
                                    echo '<a class="aa-thread" href="'.esc_url(is_wp_error($link)?$courses_url:$link).'"><span class="aa-thread-main"><strong class="aa-thread-name">'.esc_html($name).'</strong><span class="aa-thread-desc">'.esc_html($desc).'</span></span><span class="aa-thread-count">'.esc_html($count).' دوره</span></a>';
                                }
                            }else{
                                foreach($fallback_cats as $c){
                                    echo '<a class="aa-thread" href="'.esc_url($courses_url).'"><span class="aa-thread-main"><strong class="aa-thread-name">'.esc_html($c['name']).'</strong><span class="aa-thread-desc">'.esc_html($c['desc']).'</span></span><span class="aa-thread-count">'.esc_html($c['count']).' دوره</span></a>';
                                }
                            }
                            ?>
                        </div>
                    </div>
                </section>

                <section class="aa-section">
                    <div class="aa-wrap">
                        <div class="aa-why-grid">
                            <div class="aa-why-head" data-reveal><h2>چرا آکادمی اسدزاده</h2><p>آموزش اصولی هنرهای سنتی، از تجربه استاد تا همراهی هنرجو — نه وعده، بلکه کارگاه واقعی.</p></div>
                            <div class="aa-why-list" data-reveal style="--aa-delay: 80ms">
                                <div class="aa-why-item"><h3>آموزش عملی و اصولی</h3><p>یادگیری با تمرین، پروژه و تجربه واقعی در کارگاه مجهز، نه فقط ویدیو.</p></div>
                                <div class="aa-why-item"><h3>مدرس باتجربه</h3><p>آموزش بر پایه سال‌ها تجربه عملی و آموزشی استاد اسدزاده.</p></div>
                                <div class="aa-why-item"><h3>همراهی هنرجو</h3><p>پشتیبانی و راهنمایی در طول مسیر تا خلق اثر نهایی.</p></div>
                                <div class="aa-why-item"><h3>گواهی پایان دوره</h3><p>ارائه گواهی برای دوره‌های واجد شرایط پس از تکمیل.</p></div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="aa-section">
                    <div class="aa-wrap">
                        <div class="aa-master-grid">
                            <div class="aa-master-media" data-reveal><img src="https://picsum.photos/seed/ostad-naser-aa/800/1000" alt="استاد ناصر اسدزاده" loading="lazy"></div>
                            <div class="aa-master-copy" data-reveal style="--aa-delay: 80ms">
                                <h2>استاد ناصر اسدزاده</h2><span class="aa-master-role">مدرس و بنیان‌گذار آکادمی</span>
                                <p>با بیش از دو دهه تجربه در آموزش قالیبافی، گلیم‌بافی و هنرهای بافت ایرانی، هدف استاد اسدزاده انتقال اصول صحیح و تجربه عملی به نسل جدید است.</p>
                                <div class="aa-master-quote">حفظ هنرهای سنتی، حفظ بخشی از هویت و فرهنگ ماست</div>
                                <div class="aa-master-stats"><div><strong>بیش از 20 سال</strong><span>تجربه آموزش هنرهای سنتی</span></div><div><strong>صدها هنرجو</strong><span>آموزش و همراهی هنرجویان</span></div></div>
                            </div>
                        </div>
                    </div>
                </section>

                <?php if($active): ?>
                    <section class="aa-section">
                        <div class="aa-wrap">
                            <div class="aa-section-head"><h2>ادامه یادگیری</h2><a class="aa-btn is-secondary is-sm" href="<?php echo esc_url($dashboard_url); ?>"><span>دوره‌های من</span></a></div>
                            <div class="aa-course-grid"><?php foreach($active as $i=>$r) self::course_card($r,$i,false); ?></div>
                        </div>
                    </section>
                <?php endif; ?>

                <section class="aa-section">
                    <div class="aa-wrap">
                        <div class="aa-section-head"><h2>تجربه هنرجویان</h2><p>روایت هنرجویانی که مسیر یادگیری را با آکادمی تجربه کرده‌اند</p></div>
                        <div class="aa-test-list" data-reveal>
                            <article class="aa-test is-featured"><p>آموزش بسیار اصولی و کاربردی بود. از اولین گره تا بافت کامل را قدم به قدم یاد گرفتم. همراهی استاد در طول دوره عالی بود.</p><div class="aa-test-foot"><img src="https://picsum.photos/seed/aa-test-1/80/80" alt="هنرجو" loading="lazy"><div><strong>سارا احمدی</strong><span>دوره قالیبافی مقدماتی</span></div></div></article>
                            <article class="aa-test"><p>کارگاه حضوری تجربه‌ای متفاوت بود. فضای کارگاه و آموزش عملی باعث شد خیلی سریع پیشرفت کنم.</p><div class="aa-test-foot"><img src="https://picsum.photos/seed/aa-test-2/80/80" alt="هنرجو" loading="lazy"><div><strong>محمد حسینی</strong><span>کارگاه حضوری</span></div></div></article>
                            <article class="aa-test"><p>پشتیبانی عالی و دسترسی آسان به محتوای دوره. حتی بعد از اتمام دوره هم پاسخگوی سوالاتم بودند.</p><div class="aa-test-foot"><img src="https://picsum.photos/seed/aa-test-3/80/80" alt="هنرجو" loading="lazy"><div><strong>فاطمه کریمی</strong><span>دوره گلیم بافی</span></div></div></article>
                        </div>
                    </div>
                </section>

                <section class="aa-section">
                    <div class="aa-wrap">
                        <div class="aa-faq-grid">
                            <div class="aa-faq-head" data-reveal><h2>سوالات متداول</h2><p>پاسخ سوال‌هایی که ممکن است قبل از شروع داشته باشید</p></div>
                            <div class="aa-faq-list" data-reveal style="--aa-delay: 80ms">
                                <details class="aa-faq-item" open><summary>آیا برای شرکت در دوره‌ها نیاز به تجربه قبلی دارم</summary><p>خیر. دوره‌های مقدماتی از پایه طراحی شده‌اند.</p></details>
                                <details class="aa-faq-item"><summary>دوره‌ها آنلاین هستند یا حضوری</summary><p>بسته به دوره، هر دو حالت وجود دارد و در صفحه دوره مشخص است.</p></details>
                                <details class="aa-faq-item"><summary>کارگاه‌های حضوری کجا برگزار می‌شوند</summary><p>محل برگزاری هر کارگاه در صفحه همان کارگاه اعلام می‌شود.</p></details>
                                <details class="aa-faq-item"><summary>بعد از ثبت نام چطور به دوره دسترسی پیدا می‌کنم</summary><p>پس از ثبت نام، دوره در حساب کاربری شما فعال می‌شود.</p></details>
                                <details class="aa-faq-item"><summary>آیا پس از پایان دوره گواهی دریافت می‌کنم</summary><p>برای دوره‌های واجد شرایط، پس از تکمیل گواهی صادر می‌شود.</p></details>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="aa-final" data-reveal>
                    <div class="aa-final-inner">
                        <div class="aa-final-copy"><h2><?php echo esc_html(is_user_logged_in()?'دوره بعدی‌ات را انتخاب کن':'آماده‌ای اولین گره را بزنی'); ?></h2><p>دوره‌ها و کارگاه‌ها برای شروع از پایه طراحی شده‌اند. مسیر مناسب خودت را انتخاب کن و قدم به قدم پیش برو.</p></div>
                        <div class="aa-final-actions">
                            <a class="aa-btn is-primary" href="<?php echo esc_url(is_user_logged_in()?$courses_url:$account_url); ?>"><span><?php echo esc_html(is_user_logged_in()?'دیدن دوره‌ها':'ورود و ثبت نام'); ?></span></a>
                            <?php if(is_user_logged_in()): ?><a class="aa-btn is-ghost" href="<?php echo esc_url($dashboard_url); ?>"><span>دوره‌های من</span></a>
                            <?php else: ?><a class="aa-btn is-ghost" href="<?php echo esc_url($courses_url); ?>"><span>کارگاه‌های حضوری</span></a><?php endif; ?>
                        </div>
                    </div>
                </section>
            </div>
            <?php
            return ob_get_clean();
        }

        private static function assets($instance){
            ob_start();
            ?>
            <style id="<?php echo esc_attr($instance); ?>-css">
                /* Frontend-Design Skill - Distinctive Weave Identity - aa- scoped */
                #<?php echo esc_attr($instance); ?>{
                    --aa-ink:#161412; --aa-paper:#F6F1E8; --aa-paper-2:#EFE7D9; --aa-line:#E7DDD0; --aa-line-strong:#D5C9B6;
                    --aa-madder:#7A1F1E; --aa-madder-dark:#5E1715; --aa-indigo:#22344E; --aa-saffron:#C9A86A; --aa-muted:#6B6560;
                    --aa-radius:8px; --aa-ease:cubic-bezier(.16,1,.3,1);
                    color:var(--aa-ink); background:var(--aa-paper);
                    font-family:"Vazirmatn", system-ui, Tahoma, sans-serif; line-height:2;
                    -webkit-font-smoothing:antialiased; width:100%; max-width:100%; overflow:clip; padding:0; direction:rtl;
                    --e-global-color-primary:var(--aa-madder);
                }
                #<?php echo esc_attr($instance); ?> *{box-sizing:border-box}
                #<?php echo esc_attr($instance); ?> a{color:inherit; text-decoration:none}
                #<?php echo esc_attr($instance); ?> img{max-width:100%; display:block}
                #<?php echo esc_attr($instance); ?> :is(a,button):focus-visible{outline:2px solid var(--aa-madder); outline-offset:3px}
                #<?php echo esc_attr($instance); ?> .aa-wrap{width:min(1240px, calc(100% - 32px)); margin-inline:auto}
                #<?php echo esc_attr($instance); ?> [data-reveal]{opacity:0; transform:translateY(14px); transition:opacity .6s var(--aa-ease), transform .6s var(--aa-ease); transition-delay:var(--aa-delay,0ms)}
                #<?php echo esc_attr($instance); ?> [data-reveal].is-visible{opacity:1; transform:translateY(0)}
                @media (prefers-reduced-motion: reduce){#<?php echo esc_attr($instance); ?> [data-reveal]{opacity:1; transform:none; transition:none}}
                #<?php echo esc_attr($instance); ?> .aa-system-notice{padding:16px 18px; border:1px solid var(--aa-line-strong); background:var(--aa-paper-2); font-size:13px; width:min(1240px, calc(100% - 32px)); margin:0 auto 20px}
                #<?php echo esc_attr($instance); ?> .aa-btn{display:inline-flex; align-items:center; justify-content:center; gap:8px; min-height:44px; padding:0 20px; border-radius:var(--aa-radius); font-size:13px; font-weight:700; line-height:1; border:1px solid transparent; cursor:pointer; transition:background .2s var(--aa-ease), color .2s var(--aa-ease), border-color .2s var(--aa-ease), transform .2s var(--aa-ease); white-space:nowrap}
                #<?php echo esc_attr($instance); ?> .aa-btn:hover{transform:translateY(-1px)}
                #<?php echo esc_attr($instance); ?> .aa-btn.is-primary{background:var(--aa-madder); color:#fff; border-color:var(--aa-madder)}
                #<?php echo esc_attr($instance); ?> .aa-btn.is-primary:hover{background:var(--aa-madder-dark)}
                #<?php echo esc_attr($instance); ?> .aa-btn.is-secondary{background:transparent; color:var(--aa-ink); border-color:var(--aa-line-strong)}
                #<?php echo esc_attr($instance); ?> .aa-btn.is-secondary:hover{background:var(--aa-paper-2); border-color:var(--aa-ink)}
                #<?php echo esc_attr($instance); ?> .aa-btn.is-ghost{background:transparent; color:var(--aa-paper); border-color:rgba(246,241,232,.3)}
                #<?php echo esc_attr($instance); ?> .aa-btn.is-sm{min-height:36px; padding:0 14px; font-size:12px}
                #<?php echo esc_attr($instance); ?> .aa-kicker{display:inline-block; font-size:11px; font-weight:700; color:var(--aa-muted); margin-bottom:12px}
                #<?php echo esc_attr($instance); ?> .aa-kicker.is-madder{color:var(--aa-madder)}
                #<?php echo esc_attr($instance); ?> .aa-hero{padding:48px 0 32px; border-bottom:1px solid var(--aa-line)}
                #<?php echo esc_attr($instance); ?> .aa-hero-grid{display:grid; grid-template-columns:7fr 5fr; gap:0; min-height:560px; border:1px solid var(--aa-line); background:var(--aa-paper)}
                #<?php echo esc_attr($instance); ?> .aa-hero-media{position:relative; overflow:hidden; background:var(--aa-paper-2); border-left:1px solid var(--aa-line)}
                #<?php echo esc_attr($instance); ?> .aa-hero-media img{width:100%; height:100%; object-fit:cover}
                #<?php echo esc_attr($instance); ?> .aa-hero-media:after{content:""; position:absolute; inset:0; background-image:radial-gradient(rgba(22,20,18,.06) 1px, transparent 1px); background-size:18px 18px; opacity:.5; pointer-events:none; mix-blend-mode:multiply}
                #<?php echo esc_attr($instance); ?> .aa-hero-copy{padding:40px 36px; display:flex; flex-direction:column; justify-content:center}
                #<?php echo esc_attr($instance); ?> .aa-hero-copy h1{margin:0; font-size:clamp(32px,4.2vw,52px); line-height:1.15; font-weight:900; letter-spacing:-.03em; text-wrap:balance; max-width:20ch}
                #<?php echo esc_attr($instance); ?> .aa-hero-copy>p{margin:16px 0 0; color:var(--aa-muted); font-size:15px; line-height:2; max-width:42ch}
                #<?php echo esc_attr($instance); ?> .aa-hero-actions{display:flex; flex-wrap:wrap; gap:10px; margin-top:28px}
                #<?php echo esc_attr($instance); ?> .aa-hero-marginalia{margin-top:32px; padding-top:18px; border-top:1px solid var(--aa-line); display:flex; gap:24px; flex-wrap:wrap}
                #<?php echo esc_attr($instance); ?> .aa-marginalia-item{font-size:11px; line-height:1.6}
                #<?php echo esc_attr($instance); ?> .aa-marginalia-item strong{display:block; font-size:13px; font-weight:700; line-height:1.2}
                #<?php echo esc_attr($instance); ?> .aa-marginalia-item span{color:var(--aa-muted)}
                #<?php echo esc_attr($instance); ?> .aa-metrics{padding:18px 0; border-bottom:1px solid var(--aa-line); background:var(--aa-paper-2)}
                #<?php echo esc_attr($instance); ?> .aa-metrics-grid{display:grid; grid-template-columns:repeat(4,1fr); gap:0; border:1px solid var(--aa-line); background:var(--aa-paper)}
                #<?php echo esc_attr($instance); ?> .aa-metric{padding:16px 20px; border-left:1px solid var(--aa-line)}
                #<?php echo esc_attr($instance); ?> .aa-metric:first-child{border-left:none}
                #<?php echo esc_attr($instance); ?> .aa-metric strong{font-size:14px; font-weight:700}
                #<?php echo esc_attr($instance); ?> .aa-metric span{font-size:11px; color:var(--aa-muted); margin-right:6px}
                #<?php echo esc_attr($instance); ?> .aa-section{padding:72px 0; border-bottom:1px solid var(--aa-line)}
                #<?php echo esc_attr($instance); ?> .aa-section-head{display:flex; justify-content:space-between; align-items:flex-end; gap:24px; margin-bottom:32px; flex-wrap:wrap}
                #<?php echo esc_attr($instance); ?> .aa-section-head h2{margin:0; font-size:clamp(24px,2.8vw,36px); line-height:1.25; font-weight:800; letter-spacing:-.02em}
                #<?php echo esc_attr($instance); ?> .aa-section-head p{margin:8px 0 0; color:var(--aa-muted); font-size:13px; line-height:1.9; max-width:48ch}
                #<?php echo esc_attr($instance); ?> .aa-course-list{display:grid; gap:1px; background:var(--aa-line); border:1px solid var(--aa-line)}
                #<?php echo esc_attr($instance); ?> .aa-course{display:grid; grid-template-columns:5fr 7fr; background:var(--aa-paper); position:relative}
                #<?php echo esc_attr($instance); ?> .aa-course.is-featured{grid-template-columns:6fr 6fr}
                #<?php echo esc_attr($instance); ?> .aa-course.is-featured:before{content:""; position:absolute; top:0; right:0; left:0; height:3px; background:var(--aa-madder); z-index:1}
                #<?php echo esc_attr($instance); ?> .aa-course-media{position:relative; aspect-ratio:4/3; overflow:hidden; background:var(--aa-paper-2)}
                #<?php echo esc_attr($instance); ?> .aa-course-media img{width:100%; height:100%; object-fit:cover; transition:transform .5s var(--aa-ease)}
                #<?php echo esc_attr($instance); ?> .aa-course:hover .aa-course-media img{transform:scale(1.02)}
                #<?php echo esc_attr($instance); ?> .aa-course-badge{position:absolute; top:12px; right:12px; background:var(--aa-paper); border:1px solid var(--aa-line); padding:4px 8px; font-size:10px; font-weight:700}
                #<?php echo esc_attr($instance); ?> .aa-course-badge.is-owned{background:var(--aa-indigo); color:#fff; border-color:var(--aa-indigo)}
                #<?php echo esc_attr($instance); ?> .aa-course-body{padding:20px 22px; display:flex; flex-direction:column; gap:10px; min-width:0}
                #<?php echo esc_attr($instance); ?> .aa-course-body h3{margin:0; font-size:17px; font-weight:800; line-height:1.5; max-width:32ch}
                #<?php echo esc_attr($instance); ?> .aa-course-teacher{font-size:11px; color:var(--aa-muted); margin:0}
                #<?php echo esc_attr($instance); ?> .aa-course-meta{font-size:11px; color:var(--aa-muted); display:flex; gap:12px; flex-wrap:wrap}
                #<?php echo esc_attr($instance); ?> .aa-course-price{margin-top:4px; font-size:14px; font-weight:700}
                #<?php echo esc_attr($instance); ?> .aa-course-price del{color:var(--aa-muted); font-size:11px; margin-left:8px; font-weight:400}
                #<?php echo esc_attr($instance); ?> .aa-course-price ins{text-decoration:none}
                #<?php echo esc_attr($instance); ?> .aa-price-free{color:var(--aa-indigo); font-weight:700}
                #<?php echo esc_attr($instance); ?> .aa-progress{margin-top:8px}
                #<?php echo esc_attr($instance); ?> .aa-progress-head{display:flex; justify-content:space-between; font-size:11px; color:var(--aa-muted); margin-bottom:6px}
                #<?php echo esc_attr($instance); ?> .aa-progress-head strong{color:var(--aa-ink); font-weight:700}
                #<?php echo esc_attr($instance); ?> .aa-progress-track{display:block; height:2px; background:var(--aa-line); overflow:hidden}
                #<?php echo esc_attr($instance); ?> .aa-progress-track i{display:block; height:100%; background:var(--aa-madder)}
                #<?php echo esc_attr($instance); ?> .aa-progress-meta{margin-top:6px; font-size:10px; color:var(--aa-muted)}
                #<?php echo esc_attr($instance); ?> .aa-course-actions{margin-top:auto; padding-top:14px; display:flex; gap:8px}
                #<?php echo esc_attr($instance); ?> .aa-course-grid{display:grid; grid-template-columns:repeat(2,1fr); gap:1px; background:var(--aa-line); border:1px solid var(--aa-line)}
                #<?php echo esc_attr($instance); ?> .aa-course-grid .aa-course{grid-template-columns:1fr; display:flex; flex-direction:column}
                #<?php echo esc_attr($instance); ?> .aa-course-grid .aa-course .aa-course-media{aspect-ratio:16/10}
                #<?php echo esc_attr($instance); ?> .aa-course-grid .aa-course .aa-course-body{flex:1}
                #<?php echo esc_attr($instance); ?> .aa-threads{border:1px solid var(--aa-line); background:var(--aa-paper)}
                #<?php echo esc_attr($instance); ?> .aa-thread{display:flex; justify-content:space-between; align-items:center; gap:20px; padding:18px 20px; border-bottom:1px solid var(--aa-line); transition:background .2s var(--aa-ease)}
                #<?php echo esc_attr($instance); ?> .aa-thread:last-child{border-bottom:none}
                #<?php echo esc_attr($instance); ?> .aa-thread:hover{background:var(--aa-paper-2)}
                #<?php echo esc_attr($instance); ?> .aa-thread-main{display:flex; gap:16px; align-items:baseline; min-width:0}
                #<?php echo esc_attr($instance); ?> .aa-thread-name{font-size:16px; font-weight:700; white-space:nowrap}
                #<?php echo esc_attr($instance); ?> .aa-thread-desc{font-size:12px; color:var(--aa-muted); max-width:42ch; white-space:nowrap; overflow:hidden; text-overflow:ellipsis}
                #<?php echo esc_attr($instance); ?> .aa-thread-count{flex:0 0 auto; min-width:56px; text-align:center; padding:6px 10px; border:1px solid var(--aa-line); background:var(--aa-paper-2); font-size:11px; font-weight:700}
                #<?php echo esc_attr($instance); ?> .aa-why-grid{display:grid; grid-template-columns:4fr 8fr; gap:48px}
                #<?php echo esc_attr($instance); ?> .aa-why-head h2{margin:0; font-size:clamp(22px,2.6vw,32px); font-weight:800; line-height:1.3}
                #<?php echo esc_attr($instance); ?> .aa-why-head p{margin:10px 0 0; color:var(--aa-muted); font-size:13px; line-height:1.9; max-width:36ch}
                #<?php echo esc_attr($instance); ?> .aa-why-list{border-top:1px solid var(--aa-line)}
                #<?php echo esc_attr($instance); ?> .aa-why-item{padding:20px 0; border-bottom:1px solid var(--aa-line); display:grid; gap:8px}
                #<?php echo esc_attr($instance); ?> .aa-why-item:last-child{border-bottom:none}
                #<?php echo esc_attr($instance); ?> .aa-why-item h3{margin:0; font-size:14px; font-weight:700}
                #<?php echo esc_attr($instance); ?> .aa-why-item p{margin:0; color:var(--aa-muted); font-size:12px; line-height:1.9; max-width:60ch}
                #<?php echo esc_attr($instance); ?> .aa-master-grid{display:grid; grid-template-columns:5fr 7fr; gap:40px; align-items:start}
                #<?php echo esc_attr($instance); ?> .aa-master-media{border:1px solid var(--aa-line); background:var(--aa-paper-2); padding:6px}
                #<?php echo esc_attr($instance); ?> .aa-master-media img{width:100%; aspect-ratio:4/5; object-fit:cover}
                #<?php echo esc_attr($instance); ?> .aa-master-copy h2{margin:0; font-size:clamp(22px,2.6vw,32px); font-weight:800}
                #<?php echo esc_attr($instance); ?> .aa-master-role{display:block; margin-top:6px; font-size:11px; font-weight:700; color:var(--aa-madder)}
                #<?php echo esc_attr($instance); ?> .aa-master-copy>p{margin:14px 0 0; color:var(--aa-muted); font-size:14px; line-height:2; max-width:56ch}
                #<?php echo esc_attr($instance); ?> .aa-master-quote{margin-top:20px; padding:16px 18px; border-right:3px solid var(--aa-saffron); background:var(--aa-paper-2); font-family:"Amiri", serif; font-size:18px; line-height:1.7}
                #<?php echo esc_attr($instance); ?> .aa-master-stats{margin-top:20px; display:grid; grid-template-columns:1fr 1fr; gap:1px; background:var(--aa-line); border:1px solid var(--aa-line)}
                #<?php echo esc_attr($instance); ?> .aa-master-stats div{padding:14px 16px; background:var(--aa-paper)}
                #<?php echo esc_attr($instance); ?> .aa-master-stats strong{display:block; font-size:13px; font-weight:700}
                #<?php echo esc_attr($instance); ?> .aa-master-stats span{display:block; margin-top:2px; font-size:11px; color:var(--aa-muted); line-height:1.6}
                #<?php echo esc_attr($instance); ?> .aa-test-list{border:1px solid var(--aa-line); background:var(--aa-paper)}
                #<?php echo esc_attr($instance); ?> .aa-test{display:grid; grid-template-columns:8fr 4fr; gap:0; border-bottom:1px solid var(--aa-line); padding:24px 20px}
                #<?php echo esc_attr($instance); ?> .aa-test:last-child{border-bottom:none}
                #<?php echo esc_attr($instance); ?> .aa-test p{margin:0; font-size:15px; line-height:2; max-width:56ch}
                #<?php echo esc_attr($instance); ?> .aa-test.is-featured{background:var(--aa-ink); color:var(--aa-paper)}
                #<?php echo esc_attr($instance); ?> .aa-test.is-featured p{font-family:"Amiri", serif; font-size:19px; line-height:1.8}
                #<?php echo esc_attr($instance); ?> .aa-test-foot{margin-top:16px; display:flex; gap:10px; align-items:center}
                #<?php echo esc_attr($instance); ?> .aa-test-foot img{width:32px; height:32px; border-radius:50%; object-fit:cover; background:var(--aa-paper-2)}
                #<?php echo esc_attr($instance); ?> .aa-test-foot strong{font-size:12px; font-weight:700; display:block}
                #<?php echo esc_attr($instance); ?> .aa-test-foot span{font-size:10px; color:var(--aa-muted); display:block; margin-top:2px}
                #<?php echo esc_attr($instance); ?> .aa-test.is-featured .aa-test-foot span{color:rgba(246,241,232,.7)}
                #<?php echo esc_attr($instance); ?> .aa-faq-grid{display:grid; grid-template-columns:4fr 8fr; gap:48px}
                #<?php echo esc_attr($instance); ?> .aa-faq-head h2{margin:0; font-size:clamp(22px,2.6vw,32px); font-weight:800}
                #<?php echo esc_attr($instance); ?> .aa-faq-head p{margin:10px 0 0; color:var(--aa-muted); font-size:13px; line-height:1.9; max-width:36ch}
                #<?php echo esc_attr($instance); ?> .aa-faq-list{border-top:1px solid var(--aa-line)}
                #<?php echo esc_attr($instance); ?> .aa-faq-item{border-bottom:1px solid var(--aa-line)}
                #<?php echo esc_attr($instance); ?> .aa-faq-item summary{list-style:none; cursor:pointer; display:flex; justify-content:space-between; align-items:center; gap:16px; padding:16px 0; font-size:14px; font-weight:600; line-height:1.6}
                #<?php echo esc_attr($instance); ?> .aa-faq-item summary::-webkit-details-marker{display:none}
                #<?php echo esc_attr($instance); ?> .aa-faq-item summary:after{content:"+"; font-size:16px; color:var(--aa-muted)}
                #<?php echo esc_attr($instance); ?> .aa-faq-item[open] summary:after{content:"−"}
                #<?php echo esc_attr($instance); ?> .aa-faq-item p{margin:0 0 16px; color:var(--aa-muted); font-size:12px; line-height:2; max-width:60ch}
                #<?php echo esc_attr($instance); ?> .aa-final{padding:0; background:var(--aa-ink); color:var(--aa-paper); border-top:1px solid var(--aa-ink)}
                #<?php echo esc_attr($instance); ?> .aa-final-inner{width:min(1240px, calc(100% - 32px)); margin-inline:auto; padding:48px 0; display:flex; justify-content:space-between; align-items:center; gap:32px; flex-wrap:wrap}
                #<?php echo esc_attr($instance); ?> .aa-final-copy h2{margin:0; font-size:clamp(22px,2.8vw,34px); font-weight:800; line-height:1.25; max-width:20ch}
                #<?php echo esc_attr($instance); ?> .aa-final-copy p{margin:10px 0 0; font-size:13px; line-height:1.9; color:rgba(246,241,232,.7); max-width:48ch}
                #<?php echo esc_attr($instance); ?> .aa-final-actions{display:flex; gap:10px; flex-wrap:wrap}
                #<?php echo esc_attr($instance); ?> .aa-empty{padding:24px; border:1px solid var(--aa-line); background:var(--aa-paper-2)}
                #<?php echo esc_attr($instance); ?> .aa-empty h3{margin:0; font-size:15px; font-weight:700}
                #<?php echo esc_attr($instance); ?> .aa-empty p{margin:6px 0 0; color:var(--aa-muted); font-size:12px; line-height:1.9; max-width:48ch}
                @media (max-width: 1024px){
                    #<?php echo esc_attr($instance); ?> .aa-hero-grid{grid-template-columns:1fr; min-height:0}
                    #<?php echo esc_attr($instance); ?> .aa-hero-media{aspect-ratio:16/10; border-left:none; border-bottom:1px solid var(--aa-line)}
                    #<?php echo esc_attr($instance); ?> .aa-hero-copy{padding:28px 22px}
                    #<?php echo esc_attr($instance); ?> .aa-metrics-grid{grid-template-columns:repeat(2,1fr)}
                    #<?php echo esc_attr($instance); ?> .aa-metric{border-bottom:1px solid var(--aa-line)}
                    #<?php echo esc_attr($instance); ?> .aa-metric:nth-child(2n){border-left:none}
                    #<?php echo esc_attr($instance); ?> .aa-course{grid-template-columns:1fr}
                    #<?php echo esc_attr($instance); ?> .aa-course-grid{grid-template-columns:1fr}
                    #<?php echo esc_attr($instance); ?> .aa-why-grid, #<?php echo esc_attr($instance); ?> .aa-master-grid, #<?php echo esc_attr($instance); ?> .aa-faq-grid{grid-template-columns:1fr; gap:28px}
                    #<?php echo esc_attr($instance); ?> .aa-test{grid-template-columns:1fr}
                }
                @media (max-width: 640px){
                    #<?php echo esc_attr($instance); ?> .aa-wrap, #<?php echo esc_attr($instance); ?> .aa-final-inner{width:min(100% - 20px, 560px)}
                    #<?php echo esc_attr($instance); ?> .aa-hero{padding-top:24px}
                    #<?php echo esc_attr($instance); ?> .aa-section{padding:48px 0}
                    #<?php echo esc_attr($instance); ?> .aa-final-inner{flex-direction:column; align-items:flex-start; padding:32px 0}
                }
            </style>
            <script>
            (function(){
                var root=document.getElementById('<?php echo esc_js($instance); ?>');
                if(!root) return;
                var prefersReduced=window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                if(prefersReduced){ root.querySelectorAll('[data-reveal]').forEach(function(el){ el.classList.add('is-visible'); }); return; }
                var io=new IntersectionObserver(function(entries){
                    entries.forEach(function(entry){ if(entry.isIntersecting){ entry.target.classList.add('is-visible'); io.unobserve(entry.target); } });
                }, {threshold:0.12, rootMargin:'0px 0px -40px 0px'});
                root.querySelectorAll('[data-reveal]').forEach(function(el){ io.observe(el); });
                var faqs=root.querySelectorAll('.aa-faq-item');
                faqs.forEach(function(f){ f.addEventListener('toggle', function(){ if(f.open){ faqs.forEach(function(o){ if(o!==f) o.open=false; }); } }); });
            })();
            </script>
            <?php
            return ob_get_clean();
        }
    }

    Asadzadeh_Academy_Home::init();
    if(!class_exists('Luxury_Academy_Homepage')){
        class Luxury_Academy_Homepage extends Asadzadeh_Academy_Home {}
    }
}
