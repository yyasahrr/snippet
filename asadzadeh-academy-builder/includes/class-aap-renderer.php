<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AAP_Renderer {
	private $assets_enqueued = false;

	public function __construct() {}

	/* ==================== Data Layer ==================== */
	private function tutor_available(){ return function_exists('tutor') && function_exists('tutor_utils'); }
	private function woocommerce_available(){ return function_exists('WC') && class_exists('WooCommerce'); }
	private function course_post_type(){
		if($this->tutor_available() && !empty(tutor()->course_post_type)) return sanitize_key(tutor()->course_post_type);
		return post_type_exists('courses') ? 'courses' : 'tutor_course';
	}
	private function course_taxonomy(){
		if($this->tutor_available() && !empty(tutor()->course_taxonomy)) return sanitize_key(tutor()->course_taxonomy);
		foreach(array('course-category','course_cat','tutor_course_category') as $tax){ if(taxonomy_exists($tax)) return $tax; }
		return '';
	}
	private function is_featured_course($course_id){
		foreach(array('_tutor_course_featured','_tutor_is_featured','is_featured') as $key){
			if(in_array(strtolower((string)get_post_meta($course_id,$key,true)), array('1','yes','true','on'), true)) return true;
		}
		return false;
	}
	private function course_ids($limit,$category=''){
		$args=array(
			'post_type'=>$this->course_post_type(),
			'post_status'=>'publish',
			'posts_per_page'=>max(12,min(60,$limit*4)),
			'orderby'=>'date','order'=>'DESC','no_found_rows'=>true,'ignore_sticky_posts'=>true,'fields'=>'ids',
			'update_post_meta_cache'=>true,'update_post_term_cache'=>true,
		);
		$taxonomy=$this->course_taxonomy();
		if($taxonomy && $category){
			$term=term_exists($category,$taxonomy);
			if($term){
				$args['tax_query']=array(array('taxonomy'=>$taxonomy,'field'=>is_numeric($category)?'term_id':'slug','terms'=>is_numeric($category)?absint($category):sanitize_title($category)));
			}
		}
		$ids=get_posts($args);
		$ids=array_values(array_filter(array_map('absint',$ids)));
		usort($ids,function($l,$r){
			$lf=$this->is_featured_course($l)?1:0; $rf=$this->is_featured_course($r)?1:0;
			if($lf!==$rf) return $rf<=>$lf;
			$lo=(int)get_post_field('menu_order',$l); $ro=(int)get_post_field('menu_order',$r);
			if($lo!==$ro) return $lo<=>$ro;
			return (int)get_post_time('U',true,$r) <=> (int)get_post_time('U',true,$l);
		});
		return array_slice($ids,0,$limit);
	}
	private function product_map($course_ids){
		$map=array(); $pids=array();
		foreach($course_ids as $cid){ $pid=absint(get_post_meta($cid,'_tutor_course_product_id',true)); $map[$cid]=$pid; if($pid) $pids[]=$pid; }
		if(!$this->woocommerce_available() || empty($pids)) return array_fill_keys(array_keys($map), false);
		$products=wc_get_products(array('include'=>array_values(array_unique($pids)),'limit'=>-1,'status'=>array('publish','private'),'return'=>'objects'));
		$byId=array(); foreach($products as $p){ if(is_object($p) && method_exists($p,'get_id')) $byId[(int)$p->get_id()]=$p; }
		foreach($map as $cid=>$pid){ $map[$cid]=$pid && isset($byId[$pid]) ? $byId[$pid] : false; }
		return $map;
	}
	private function enrolled_ids(){
		if(!is_user_logged_in() || !$this->tutor_available()) return array();
		$enrolled=tutor_utils()->get_enrolled_courses_by_user(get_current_user_id(), array('publish','private'));
		$posts=$enrolled instanceof WP_Query ? $enrolled->posts : (is_array($enrolled)?$enrolled:array());
		$ids=array(); foreach($posts as $c){ $id=is_object($c)&&isset($c->ID)?$c->ID:$c; if(absint($id)) $ids[]=absint($id); }
		return array_values(array_unique($ids));
	}
	private function progress($course_id){
		if(!$this->tutor_available() || !is_user_logged_in()) return array('percent'=>0,'completed'=>0,'total'=>0);
		$stats=tutor_utils()->get_course_completed_percent($course_id,get_current_user_id(),true);
		if(!is_array($stats)) return array('percent'=>max(0,min(100,(int)$stats)),'completed'=>0,'total'=>0);
		return array('percent'=>max(0,min(100,(int)($stats['completed_percent']??0))),'completed'=>absint($stats['completed_count']??0),'total'=>absint($stats['total_count']??0));
	}
	private function continue_url($course_id,$percent){
		$course_url=get_permalink($course_id);
		if($percent<=0 || !$this->tutor_available()) return $course_url;
		$lesson=tutor_utils()->get_course_first_lesson($course_id);
		if(is_numeric($lesson)) $lesson=get_permalink(absint($lesson));
		elseif($lesson instanceof WP_Post) $lesson=get_permalink($lesson->ID);
		return $lesson ? $lesson : $course_url;
	}
	private function course_detail($course_id,$kind){
		if($this->tutor_available()){
			$method='level'===$kind?'get_course_level':'get_course_duration_context';
			if(is_object(tutor_utils()) && method_exists(tutor_utils(),$method)){
				$v=tutor_utils()->$method($course_id);
				if(is_scalar($v) && ''!==trim((string)$v)) return wp_strip_all_tags((string)$v);
			}
		}
		$keys='level'===$kind?array('_tutor_course_level','course_level'):array('_tutor_course_duration','course_duration');
		foreach($keys as $k){ $v=get_post_meta($course_id,$k,true); if(is_scalar($v) && ''!==trim((string)$v)) return wp_strip_all_tags((string)$v); }
		return '';
	}
	private function cart_contains($product_id){
		if(!$product_id || !$this->woocommerce_available()) return false;
		if(function_exists('wc_load_cart') && (!WC()->cart || !WC()->session)) wc_load_cart();
		if(!WC()->cart) return false;
		foreach(WC()->cart->get_cart() as $item){ if($product_id===absint($item['product_id']??0) || $product_id===absint($item['variation_id']??0)) return true; }
		return false;
	}
	private function course_data($course_ids){
		if(empty($course_ids)) return array();
		$products=$this->product_map($course_ids);
		$enrolled=$this->enrolled_ids();
		$author_ids=array(); foreach($course_ids as $cid) $author_ids[]=(int)get_post_field('post_author',$cid);
		$authors=array(); foreach(array_unique($author_ids) as $aid) $authors[$aid]=get_the_author_meta('display_name',$aid);
		$rows=array();
		foreach($course_ids as $cid){
			$product=$products[$cid]??false;
			$prog=$this->progress($cid);
			$is_enrolled=in_array($cid,$enrolled,true);
			$is_free=!$product || (method_exists($product,'is_free')?$product->is_free():(float)$product->get_price()<=0);
			$tax=$this->course_taxonomy();
			$terms=$tax?get_the_terms($cid,$tax):array();
			$tnames=is_array($terms)?wp_list_pluck(array_slice($terms,0,2),'name'):array();
			$img=get_the_post_thumbnail_url($cid,'medium_large');
			$c_url=get_permalink($cid);
			$a_url=$is_enrolled?$this->continue_url($cid,$prog['percent']):$c_url;
			$rows[]=array('id'=>$cid,'title'=>get_the_title($cid),'url'=>$c_url,'action_url'=>$a_url,'image'=>$img,'author'=>$authors[(int)get_post_field('post_author',$cid)]??'','terms'=>$tnames,'product'=>$product,'is_free'=>$is_free,'is_enrolled'=>$is_enrolled,'progress'=>$prog,'in_cart'=>$product&&$this->cart_contains($product->get_id()));
		}
		return $rows;
	}
	private function price_html($product){
		if(!$product || !is_object($product)) return '<span class="aap-price-free">رایگان</span>';
		return wp_kses_post($product->get_price_html());
	}
	private function learning_categories(){
		$tax=$this->course_taxonomy(); if(!$tax) return array();
		$terms=get_terms(array('taxonomy'=>$tax,'hide_empty'=>true,'number'=>8,'orderby'=>'count','order'=>'DESC'));
		return is_wp_error($terms)?array():$terms;
	}
	private function active_courses($limit=3){ $ids=array_slice($this->enrolled_ids(),0,$limit); return $ids?$this->course_data($ids):array(); }
	private function enqueue_assets(){
		if($this->assets_enqueued) return; $this->assets_enqueued=true;
		if(wp_style_is('aap-frontend','registered')) wp_enqueue_style('aap-frontend');
		if(wp_script_is('aap-frontend','registered')) wp_enqueue_script('aap-frontend');
	}
	private function sanitize_classes($v){ $c=preg_split('/\s+/',trim((string)$v)); $c=array_filter(array_map('sanitize_html_class',$c)); return implode(' ',$c); }
	private function get_courses_url(){
		if($this->tutor_available() && is_object(tutor_utils()) && method_exists(tutor_utils(),'get_courses_page_url')){
			$m=tutor_utils()->get_courses_page_url(); if($m) return $m;
		}
		return home_url('/doreha/');
	}

	/* ==================== Course Card - distinctive, no SaaS kit ==================== */
	private function course_card($row,$index,$featured=false){
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
		<article class="aap-course <?php echo esc_attr($owned); ?> <?php echo $featured?'is-featured':''; ?>" data-reveal style="--aap-delay: <?php echo esc_attr(min($index*60,300)); ?>ms">
			<a class="aap-course-media" href="<?php echo esc_url($row['url']); ?>" aria-label="<?php echo esc_attr('مشاهده '.$row['title']); ?>">
				<?php if($row['image']): ?><img src="<?php echo esc_url($row['image']); ?>" alt="<?php echo esc_attr($row['title']); ?>" loading="lazy" decoding="async">
				<?php else: ?><img src="https://picsum.photos/seed/<?php echo esc_attr('aap-'.$row['id']); ?>/640/480" alt="<?php echo esc_attr($row['title']); ?>" loading="lazy"><?php endif; ?>
				<span class="aap-course-badge <?php echo esc_attr($owned); ?>"><?php echo esc_html($status); ?></span>
			</a>
			<div class="aap-course-body">
				<h3><a href="<?php echo esc_url($row['url']); ?>"><?php echo esc_html($row['title']); ?></a></h3>
				<p class="aap-course-teacher"><?php echo esc_html($row['author']?'مدرس: '.$row['author']:'آکادمی اسدزاده'); ?></p>
				<div class="aap-course-meta">
					<span><?php echo esc_html($this->course_detail($row['id'],'level')?:'همه سطوح'); ?></span>
					<?php $d=$this->course_detail($row['id'],'duration'); if($d): ?><span><?php echo esc_html($d); ?></span><?php endif; ?>
					<?php if($row['terms']): ?><span><?php echo esc_html(implode(' / ',$row['terms'])); ?></span><?php endif; ?>
				</div>
				<?php if($row['is_enrolled']): ?>
					<div class="aap-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr($prog['percent']); ?>">
						<div class="aap-progress-head"><span>پیشرفت</span><strong><?php echo esc_html($prog['percent']); ?>٪</strong></div>
						<span class="aap-progress-track"><i style="width:<?php echo esc_attr($prog['percent']); ?>%"></i></span>
						<div class="aap-progress-meta"><span><?php echo esc_html($prog['completed']); ?> از <?php echo esc_html($prog['total']); ?> بخش</span></div>
					</div>
				<?php elseif(!$row['is_free']): ?>
					<div class="aap-course-price"><?php echo $this->price_html($row['product']); ?></div>
				<?php else: ?>
					<div class="aap-course-price"><span class="aap-price-free">رایگان</span></div>
				<?php endif; ?>
				<div class="aap-course-actions">
					<a class="aap-btn <?php echo $row['is_enrolled']?'is-secondary':'is-primary'; ?> is-sm" href="<?php echo esc_url($url); ?>"><span><?php echo esc_html($label); ?></span></a>
					<a class="aap-btn is-secondary is-sm" href="<?php echo esc_url($row['url']); ?>"><span>جزئیات</span></a>
				</div>
			</div>
		</article>
		<?php
	}

	/* ==================== Home ==================== */
	public function render_home($raw=array()){
		$this->enqueue_assets();
		$s=shortcode_atts(array('courses'=>'6','category'=>'','custom_class'=>''),(array)$raw,'asadzadeh_home');
		$limit=max(1,min(12,absint($s['courses'])?:6)); $cat=sanitize_text_field((string)$s['category']); $cls=$this->sanitize_classes($s['custom_class']);
		$instance='aap-home-'.wp_unique_id();
		$course_rows=$this->tutor_available()?$this->course_data($this->course_ids($limit,$cat)):array();
		$categories=$this->tutor_available()?$this->learning_categories():array();
		$active=is_user_logged_in()&&$this->tutor_available()?$this->active_courses(3):array();
		$account_url=function_exists('wc_get_page_permalink')?wc_get_page_permalink('myaccount'):wp_login_url();
		$courses_url=$this->get_courses_url();
		$dashboard_url=function_exists('wc_get_account_endpoint_url')?wc_get_account_endpoint_url('my-courses'):home_url('/my-account/my-courses/');
		$fallback_cats=array(
			array('name'=>'قالی بافی','desc'=>'آموزش حرفه‌ای از اولین گره تا بافت کامل','count'=>'12'),
			array('name'=>'گلیم بافی','desc'=>'نقش، رنگ و بافت گلیم‌های اصیل','count'=>'8'),
			array('name'=>'گبه بافی','desc'=>'از پشم تا نقش، ساده و کاربردی','count'=>'6'),
			array('name'=>'هنرهای سنتی','desc'=>'آشنایی با هنرهای اصیل ایرانی','count'=>'10'),
		);
		ob_start();
		?>
		<div id="<?php echo esc_attr($instance); ?>" class="aap-home <?php echo esc_attr($cls); ?>" dir="rtl" data-aap-page="home">
			<?php if(!$this->tutor_available() || !$this->woocommerce_available()): ?>
				<div class="aap-system-notice" role="alert">برای نمایش کامل، Tutor LMS و WooCommerce باید فعال باشند.</div>
			<?php endif; ?>

			<section class="aap-hero" aria-labelledby="<?php echo esc_attr($instance.'-title'); ?>">
				<div class="aap-wrap">
					<div class="aap-hero-grid">
						<div class="aap-hero-media" data-reveal>
							<img src="https://asadzadehacademy.ir/wp-content/uploads/2026/09/%DA%A9%D8%A7%D8%B1%DA%AF%D8%A7%D9%87-%D9%82%D8%A7%D9%84%DB%8C%D8%A8%D8%A7%D9%81%DB%8C-1024x768.png" alt="کارگاه قالیبافی" loading="eager" decoding="async" onerror="this.src='https://picsum.photos/seed/asadzadeh-hero/800/600'">
						</div>
						<div class="aap-hero-copy" data-reveal style="--aap-delay: 80ms">
							<span class="aap-kicker is-madder">تجربه‌ای که منتقل می‌شود</span>
							<h1 id="<?php echo esc_attr($instance.'-title'); ?>">به آکادمی اسدزاده خوش آمدید</h1>
							<p>آموزش حرفه‌ای قالیبافی، گلیم‌بافی و گبه‌بافی — از اولین گره تا خلق اثری اصیل، با تکیه بر دو دهه تجربه کارگاهی.</p>
							<div class="aap-hero-actions">
								<a class="aap-btn is-primary" href="<?php echo esc_url($courses_url); ?>"><span>مشاهده دوره‌ها</span></a>
								<a class="aap-btn is-secondary" href="<?php echo esc_url(is_user_logged_in()?$dashboard_url:$account_url); ?>"><span><?php echo esc_html(is_user_logged_in()?'ادامه یادگیری':'ورود به پیشخوان'); ?></span></a>
							</div>
							<div class="aap-hero-marginalia">
								<div class="aap-marginalia-item"><strong>گواهی معتبر</strong><span>پس از اتمام دوره</span></div>
								<div class="aap-marginalia-item"><strong>کارگاه مجهز</strong><span>حضوری و آنلاین</span></div>
								<div class="aap-marginalia-item"><strong>+500 هنرجو</strong><span>همراه مسیر یادگیری</span></div>
							</div>
						</div>
					</div>
				</div>
			</section>

			<section class="aap-metrics" aria-label="آمار">
				<div class="aap-wrap">
					<div class="aap-metrics-grid">
						<div class="aap-metric" data-reveal><strong>+20</strong><span>دوره آموزشی</span></div>
						<div class="aap-metric" data-reveal style="--aap-delay: 60ms"><strong>+500</strong><span>هنرجوی فعال</span></div>
						<div class="aap-metric" data-reveal style="--aap-delay: 120ms"><strong>20 سال</strong><span>تجربه آموزش</span></div>
						<div class="aap-metric" data-reveal style="--aap-delay: 180ms"><strong>گواهی</strong><span>پایان دوره معتبر</span></div>
					</div>
				</div>
			</section>

			<section class="aap-section">
				<div class="aap-wrap">
					<div class="aap-section-head">
						<div>
							<span class="aap-kicker is-madder">دوره‌های ویژه</span>
							<h2>برای قدم بعدی یادگیری</h2>
						</div>
						<a class="aap-btn is-secondary is-sm" href="<?php echo esc_url($courses_url); ?>"><span>همه دوره‌ها</span></a>
					</div>
					<?php if($course_rows): ?>
						<?php
						// First as featured full-width, rest as 2-col grid
						$first=array_shift($course_rows);
						if($first){
							echo '<div class="aap-course-list" style="margin-bottom:1px">';
							$this->course_card($first,0,true);
							echo '</div>';
						}
						if($course_rows){
							echo '<div class="aap-course-grid">';
							foreach($course_rows as $i=>$r) $this->course_card($r,$i+1,false);
							echo '</div>';
						}
						?>
					<?php else: ?>
						<div class="aap-empty" data-reveal><h3>هنوز دوره‌ای منتشر نشده</h3><p>به زودی دوره‌های قالیبافی و گلیم‌بافی اینجا قرار می‌گیرد</p></div>
					<?php endif; ?>
				</div>
			</section>

			<section class="aap-section">
				<div class="aap-wrap">
					<div class="aap-section-head">
						<h2>دسته‌های آموزشی</h2>
						<a class="aap-btn is-secondary is-sm" href="<?php echo esc_url($courses_url); ?>"><span>مشاهده همه</span></a>
					</div>
					<div class="aap-threads" data-reveal>
						<?php
						$cats_to_show=$categories?array_slice($categories,0,4):array();
						if($cats_to_show){
							foreach($cats_to_show as $term){
								$link=get_term_link($term); $name=$term->name; $count=$term->count; $desc=$term->description?wp_trim_words($term->description,10):'آموزش تخصصی و کاربردی';
								?>
								<a class="aap-thread" href="<?php echo esc_url(is_wp_error($link)?$courses_url:$link); ?>">
									<span class="aap-thread-main"><strong class="aap-thread-name"><?php echo esc_html($name); ?></strong><span class="aap-thread-desc"><?php echo esc_html($desc); ?></span></span>
									<span class="aap-thread-count"><?php echo esc_html($count); ?> دوره</span>
								</a>
								<?php
							}
						}else{
							foreach($fallback_cats as $c){
								?>
								<a class="aap-thread" href="<?php echo esc_url($courses_url); ?>">
									<span class="aap-thread-main"><strong class="aap-thread-name"><?php echo esc_html($c['name']); ?></strong><span class="aap-thread-desc"><?php echo esc_html($c['desc']); ?></span></span>
									<span class="aap-thread-count"><?php echo esc_html($c['count']); ?> دوره</span>
								</a>
								<?php
							}
						}
						?>
					</div>
				</div>
			</section>

			<section class="aap-section">
				<div class="aap-wrap">
					<div class="aap-why-grid">
						<div class="aap-why-head" data-reveal>
							<h2>چرا آکادمی اسدزاده</h2>
							<p>آموزش اصولی هنرهای سنتی، از تجربه استاد تا همراهی هنرجو — نه وعده، بلکه کارگاه واقعی.</p>
						</div>
						<div class="aap-why-list" data-reveal style="--aap-delay: 80ms">
							<div class="aap-why-item"><h3>آموزش عملی و اصولی</h3><p>یادگیری با تمرین، پروژه و تجربه واقعی در کارگاه مجهز، نه فقط ویدیو.</p></div>
							<div class="aap-why-item"><h3>مدرس باتجربه</h3><p>آموزش بر پایه سال‌ها تجربه عملی و آموزشی استاد اسدزاده.</p></div>
							<div class="aap-why-item"><h3>همراهی هنرجو</h3><p>پشتیبانی و راهنمایی در طول مسیر تا خلق اثر نهایی.</p></div>
							<div class="aap-why-item"><h3>گواهی پایان دوره</h3><p>ارائه گواهی برای دوره‌های واجد شرایط پس از تکمیل.</p></div>
						</div>
					</div>
				</div>
			</section>

			<section class="aap-section">
				<div class="aap-wrap">
					<div class="aap-master-grid">
						<div class="aap-master-media" data-reveal>
							<img src="https://picsum.photos/seed/ostad-naser-aa/800/1000" alt="استاد ناصر اسدزاده" loading="lazy">
						</div>
						<div class="aap-master-copy" data-reveal style="--aap-delay: 80ms">
							<h2>استاد ناصر اسدزاده</h2>
							<span class="aap-master-role">مدرس و بنیان‌گذار آکادمی</span>
							<p>با بیش از دو دهه تجربه در آموزش قالیبافی، گلیم‌بافی و هنرهای بافت ایرانی، هدف استاد اسدزاده انتقال اصول صحیح و تجربه عملی به نسل جدید است.</p>
							<p>ما باور داریم هنرهای سنتی تنها مهارت نیستند، بلکه پلی هستند میان گذشته و آینده.</p>
							<div class="aap-master-quote">حفظ هنرهای سنتی، حفظ بخشی از هویت و فرهنگ ماست</div>
							<div class="aap-master-stats">
								<div><strong>بیش از 20 سال</strong><span>تجربه آموزش هنرهای سنتی</span></div>
								<div><strong>صدها هنرجو</strong><span>آموزش و همراهی هنرجویان</span></div>
							</div>
						</div>
					</div>
				</div>
			</section>

			<?php if($active): ?>
				<section class="aap-section">
					<div class="aap-wrap">
						<div class="aap-section-head"><h2>ادامه یادگیری</h2><a class="aap-btn is-secondary is-sm" href="<?php echo esc_url($dashboard_url); ?>"><span>دوره‌های من</span></a></div>
						<div class="aap-course-grid">
							<?php foreach($active as $i=>$r) $this->course_card($r,$i,false); ?>
						</div>
					</div>
				</section>
			<?php endif; ?>

			<section class="aap-section">
				<div class="aap-wrap">
					<div class="aap-section-head"><h2>تجربه هنرجویان</h2><p>روایت هنرجویانی که مسیر یادگیری را با آکادمی تجربه کرده‌اند</p></div>
					<div class="aap-test-list" data-reveal>
						<article class="aap-test is-featured"><p>آموزش بسیار اصولی و کاربردی بود. از اولین گره تا بافت کامل را قدم به قدم یاد گرفتم. همراهی استاد در طول دوره عالی بود.</p><div class="aap-test-foot"><img src="https://picsum.photos/seed/aa-test-1/80/80" alt="هنرجو" loading="lazy"><div><strong>سارا احمدی</strong><span>دوره قالیبافی مقدماتی</span></div></div></article>
						<article class="aap-test"><p>کارگاه حضوری تجربه‌ای متفاوت بود. فضای کارگاه و آموزش عملی باعث شد خیلی سریع پیشرفت کنم.</p><div class="aap-test-foot"><img src="https://picsum.photos/seed/aa-test-2/80/80" alt="هنرجو" loading="lazy"><div><strong>محمد حسینی</strong><span>کارگاه حضوری</span></div></div></article>
						<article class="aap-test"><p>پشتیبانی عالی و دسترسی آسان به محتوای دوره. حتی بعد از اتمام دوره هم پاسخگوی سوالاتم بودند.</p><div class="aap-test-foot"><img src="https://picsum.photos/seed/aa-test-3/80/80" alt="هنرجو" loading="lazy"><div><strong>فاطمه کریمی</strong><span>دوره گلیم بافی</span></div></div></article>
					</div>
				</div>
			</section>

			<section class="aap-section">
				<div class="aap-wrap">
					<div class="aap-faq-grid">
						<div class="aap-faq-head" data-reveal><h2>سوالات متداول</h2><p>پاسخ سوال‌هایی که ممکن است قبل از شروع داشته باشید</p></div>
						<div class="aap-faq-list" data-reveal style="--aap-delay: 80ms">
							<details class="aap-faq-item" open><summary>آیا برای شرکت در دوره‌ها نیاز به تجربه قبلی دارم</summary><p>خیر. دوره‌های مقدماتی از پایه طراحی شده‌اند.</p></details>
							<details class="aap-faq-item"><summary>دوره‌ها آنلاین هستند یا حضوری</summary><p>بسته به دوره، هر دو حالت وجود دارد و در صفحه دوره مشخص است.</p></details>
							<details class="aap-faq-item"><summary>کارگاه‌های حضوری کجا برگزار می‌شوند</summary><p>محل برگزاری هر کارگاه در صفحه همان کارگاه اعلام می‌شود.</p></details>
							<details class="aap-faq-item"><summary>بعد از ثبت نام چطور به دوره دسترسی پیدا می‌کنم</summary><p>پس از ثبت نام، دوره در حساب کاربری شما فعال می‌شود.</p></details>
							<details class="aap-faq-item"><summary>آیا پس از پایان دوره گواهی دریافت می‌کنم</summary><p>برای دوره‌های واجد شرایط، پس از تکمیل گواهی صادر می‌شود.</p></details>
						</div>
					</div>
				</div>
			</section>

			<section class="aap-final" data-reveal>
				<div class="aap-final-inner">
					<div class="aap-final-copy">
						<h2><?php echo esc_html(is_user_logged_in()?'دوره بعدی‌ات را انتخاب کن':'آماده‌ای اولین گره را بزنی'); ?></h2>
						<p>دوره‌ها و کارگاه‌ها برای شروع از پایه طراحی شده‌اند. مسیر مناسب خودت را انتخاب کن و قدم به قدم پیش برو.</p>
					</div>
					<div class="aap-final-actions">
						<a class="aap-btn is-primary" href="<?php echo esc_url(is_user_logged_in()?$courses_url:$account_url); ?>"><span><?php echo esc_html(is_user_logged_in()?'دیدن دوره‌ها':'ورود و ثبت نام'); ?></span></a>
						<?php if(is_user_logged_in()): ?><a class="aap-btn is-ghost" href="<?php echo esc_url($dashboard_url); ?>"><span>دوره‌های من</span></a>
						<?php else: ?><a class="aap-btn is-ghost" href="<?php echo esc_url($courses_url); ?>"><span>کارگاه‌های حضوری</span></a><?php endif; ?>
					</div>
				</div>
			</section>
		</div>
		<?php
		return (string)ob_get_clean();
	}

	/* ==================== Courses ==================== */
	public function render_courses($raw=array()){
		$this->enqueue_assets();
		$s=shortcode_atts(array('courses'=>'9','category'=>'','search'=>'','custom_class'=>''),(array)$raw,'asadzadeh_courses');
		$limit=max(1,min(24,absint($s['courses'])?:9)); $cat=sanitize_text_field((string)$s['category']); $search=sanitize_text_field((string)$s['search']); $cls=$this->sanitize_classes($s['custom_class']);
		$instance='aap-courses-'.wp_unique_id();
		$course_ids=$this->tutor_available()?$this->course_ids($limit,$cat):array();
		$course_rows=$this->course_data($course_ids);
		$categories=$this->learning_categories();
		$courses_url=$this->get_courses_url();
		ob_start();
		?>
		<div id="<?php echo esc_attr($instance); ?>" class="aap-courses-page <?php echo esc_attr($cls); ?>" dir="rtl" data-aap-page="courses">
			<section class="aap-page-hero">
				<div class="aap-wrap">
					<div class="aap-page-hero-inner" data-reveal>
						<span class="aap-kicker is-madder">مسیر یادگیری شما</span>
						<h1>دوره‌های آکادمی اسدزاده</h1>
						<p>از مقدماتی تا پیشرفته، قالیبافی، گلیم‌بافی و گبه‌بافی را اصولی و عملی بیاموزید</p>
						<div class="aap-search-bar">
							<form role="search" method="get" action="<?php echo esc_url($courses_url); ?>">
								<input type="search" name="s" placeholder="جستجوی دوره..." value="<?php echo esc_attr($search); ?>">
								<button class="aap-btn is-primary is-sm" type="submit"><span>جستجو</span></button>
							</form>
						</div>
					</div>
				</div>
			</section>
			<?php if($categories): ?>
				<section class="aap-section">
					<div class="aap-wrap">
						<div class="aap-filter-grid" data-reveal>
							<?php foreach(array_slice($categories,0,8) as $term): $link=get_term_link($term); ?>
								<a class="aap-filter-card" href="<?php echo esc_url(is_wp_error($link)?$courses_url:$link); ?>"><strong><?php echo esc_html($term->name); ?></strong><span><?php echo esc_html($term->count); ?> دوره</span></a>
							<?php endforeach; ?>
						</div>
					</div>
				</section>
			<?php endif; ?>
			<section class="aap-section">
				<div class="aap-wrap">
					<?php if($course_rows): ?>
						<div class="aap-course-grid">
							<?php foreach($course_rows as $i=>$r) $this->course_card($r,$i,false); ?>
						</div>
					<?php else: ?>
						<div class="aap-empty" data-reveal><h3>دوره‌ای یافت نشد</h3><p>فیلتر را تغییر دهید یا بعداً سر بزنید</p></div>
					<?php endif; ?>
				</div>
			</section>
		</div>
		<?php
		return (string)ob_get_clean();
	}

	/* ==================== About ==================== */
	public function render_about($raw=array()){
		$this->enqueue_assets();
		$s=shortcode_atts(array('custom_class'=>''),(array)$raw,'asadzadeh_about');
		$cls=$this->sanitize_classes($s['custom_class']); $instance='aap-about-'.wp_unique_id(); $courses_url=$this->get_courses_url();
		ob_start();
		?>
		<div id="<?php echo esc_attr($instance); ?>" class="aap-about-page <?php echo esc_attr($cls); ?>" dir="rtl" data-aap-page="about">
			<section class="aap-page-hero">
				<div class="aap-wrap">
					<div class="aap-page-hero-inner" data-reveal>
						<span class="aap-kicker is-madder">درباره آکادمی</span>
						<h1>حفظ هنر، انتقال تجربه</h1>
						<p>آکادمی اسدزاده با تکیه بر دو دهه آموزش هنرهای بافت ایرانی، پلی است میان اصالت و آینده</p>
					</div>
					<div class="aap-about-visual" data-reveal style="--aap-delay: 80ms"><img src="https://picsum.photos/seed/asadzadeh-about-hero/1280/500" alt="کارگاه آکادمی" loading="lazy"></div>
				</div>
			</section>
			<section class="aap-section">
				<div class="aap-wrap">
					<div class="aap-story-grid">
						<div class="aap-story-copy" data-reveal>
							<h2>داستان آکادمی</h2>
							<p>از کارگاهی کوچک با چند دار قالی شروع کردیم. امروز میزبان صدها هنرجو هستیم که از اولین گره تا خلق اثری اصیل را با ما تجربه کرده‌اند.</p>
							<p>باور ما این است که هنرهای سنتی فقط مهارت نیستند، هویت‌اند. هر گره، حافظه‌ای از فرهنگ این سرزمین است.</p>
							<ul class="aap-check-list">
								<li><i></i><span>آموزش اصولی از پایه تا پیشرفته</span></li>
								<li><i></i><span>کارگاه مجهز حضوری و محتوای آنلاین</span></li>
								<li><i></i><span>پشتیبانی تا خلق اثر نهایی</span></li>
								<li><i></i><span>گواهی پایان دوره برای دوره‌های واجد شرایط</span></li>
							</ul>
						</div>
						<div class="aap-story-stats" data-reveal style="--aap-delay: 80ms">
							<div><strong>+20</strong><span>دوره آموزشی</span></div>
							<div><strong>+500</strong><span>هنرجو</span></div>
							<div><strong>20 سال</strong><span>تجربه</span></div>
							<div><strong>گواهی</strong><span>معتبر پایان دوره</span></div>
						</div>
					</div>
				</div>
			</section>
			<section class="aap-section">
				<div class="aap-wrap">
					<div class="aap-master-grid">
						<div class="aap-master-media" data-reveal><img src="https://picsum.photos/seed/ostad-naser-about/800/1000" alt="استاد ناصر اسدزاده" loading="lazy"></div>
						<div class="aap-master-copy" data-reveal style="--aap-delay: 80ms">
							<h2>استاد ناصر اسدزاده</h2>
							<span class="aap-master-role">بنیان‌گذار و مدرس آکادمی</span>
							<p>با بیش از دو دهه تجربه در آموزش قالیبافی و هنرهای بافت، هدف ایشان انتقال تجربه عملی و اصول صحیح به نسل جدید است.</p>
							<p>شعار آکادمی: «با دست‌ها می‌آموزیم، با دل‌ها ماندگار می‌کنیم»</p>
							<a class="aap-btn is-primary" href="<?php echo esc_url($courses_url); ?>"><span>مشاهده دوره‌ها</span></a>
						</div>
					</div>
				</div>
			</section>
		</div>
		<?php
		return (string)ob_get_clean();
	}

	/* ==================== Contact ==================== */
	public function render_contact($raw=array()){
		$this->enqueue_assets();
		$s=shortcode_atts(array('custom_class'=>''),(array)$raw,'asadzadeh_contact');
		$cls=$this->sanitize_classes($s['custom_class']); $instance='aap-contact-'.wp_unique_id();
		ob_start();
		?>
		<div id="<?php echo esc_attr($instance); ?>" class="aap-contact-page <?php echo esc_attr($cls); ?>" dir="rtl" data-aap-page="contact">
			<section class="aap-page-hero">
				<div class="aap-wrap">
					<div class="aap-page-hero-inner" data-reveal>
						<span class="aap-kicker is-madder">ارتباط با ما</span>
						<h1>پاسخگوی شما هستیم</h1>
						<p>سوال یا درخواست مشاوره دارید؟ فرم را پر کنید یا از راه‌های زیر تماس بگیرید</p>
					</div>
				</div>
			</section>
			<section class="aap-section">
				<div class="aap-wrap">
					<div class="aap-contact-grid" data-reveal>
						<div class="aap-contact-main">
							<h2>پیام خود را بفرستید</h2>
							<p>فرم را پر کنید، در اسرع وقت پاسخ می‌دهیم</p>
							<form class="aap-form" method="post" action="">
								<div class="aap-form-row is-double">
									<label><span>نام و نام خانوادگی *</span><input type="text" name="aap_name" required placeholder="نام شما"></label>
									<label><span>شماره تماس *</span><input type="tel" name="aap_phone" required placeholder="09xx xxx xxxx"></label>
								</div>
								<label><span>ایمیل</span><input type="email" name="aap_email" placeholder="email@example.com"></label>
								<label><span>موضوع</span>
									<select name="aap_subject"><option>مشاوره دوره‌ها</option><option>کارگاه حضوری</option><option>پشتیبانی هنرجویان</option><option>سایر</option></select>
								</label>
								<label><span>پیام شما *</span><textarea name="aap_message" required placeholder="پیام خود را بنویسید..."></textarea></label>
								<button class="aap-btn is-primary" type="submit"><span>ارسال پیام</span></button>
								<p class="aap-form-note">ارسال فرم نمایشی است. شورت‌کد فرم‌ساز خود را جایگزین کنید.</p>
							</form>
						</div>
						<div class="aap-contact-side">
							<div class="aap-info"><strong>تماس مستقیم</strong><a href="tel:+989000000000">+98 900 000 0000</a><span>شنبه تا چهارشنبه ۹ تا ۱۷</span></div>
							<div class="aap-info"><strong>ایمیل</strong><a href="mailto:info@asadzadehacademy.ir">info@asadzadehacademy.ir</a><span>پاسخ در کمتر از ۲۴ ساعت</span></div>
							<div class="aap-info"><strong>آدرس کارگاه</strong><span>آدرس کارگاه حضوری در صفحه هر کارگاه اعلام می‌شود</span></div>
							<div class="aap-map"><span>نقشه کارگاه</span><small>iframe نقشه را اینجا قرار دهید</small></div>
						</div>
					</div>
				</div>
			</section>
		</div>
		<?php
		return (string)ob_get_clean();
	}

	public function render($settings=array()){
		$type=isset($settings['aap_type'])?sanitize_key($settings['aap_type']):'home';
		switch($type){
			case 'courses': return $this->render_courses($settings);
			case 'about': return $this->render_about($settings);
			case 'contact': return $this->render_contact($settings);
			default: return $this->render_home($settings);
		}
	}
}
