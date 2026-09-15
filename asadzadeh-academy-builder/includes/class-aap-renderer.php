<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AAP_Renderer {
	private $assets_enqueued = false;
	private $instance_counter = 0;

	public function __construct() {
		// no-op, assets registered via plugin class
	}

	/* ==================== Data Layer - Tutor = edu, Woo = commercial ==================== */

	private function tutor_available() {
		return function_exists( 'tutor' ) && function_exists( 'tutor_utils' );
	}

	private function woocommerce_available() {
		return function_exists( 'WC' ) && class_exists( 'WooCommerce' );
	}

	private function course_post_type() {
		if ( $this->tutor_available() && ! empty( tutor()->course_post_type ) ) {
			return sanitize_key( tutor()->course_post_type );
		}
		return post_type_exists( 'courses' ) ? 'courses' : 'tutor_course';
	}

	private function course_taxonomy() {
		if ( $this->tutor_available() && ! empty( tutor()->course_taxonomy ) ) {
			return sanitize_key( tutor()->course_taxonomy );
		}
		foreach ( array( 'course-category', 'course_cat', 'tutor_course_category' ) as $tax ) {
			if ( taxonomy_exists( $tax ) ) {
				return $tax;
			}
		}
		return '';
	}

	private function is_featured_course( $course_id ) {
		foreach ( array( '_tutor_course_featured', '_tutor_is_featured', 'is_featured' ) as $key ) {
			if ( in_array( strtolower( (string) get_post_meta( $course_id, $key, true ) ), array( '1', 'yes', 'true', 'on' ), true ) ) {
				return true;
			}
		}
		return false;
	}

	private function course_ids( $limit, $category = '' ) {
		$args = array(
			'post_type'              => $this->course_post_type(),
			'post_status'            => 'publish',
			'posts_per_page'         => max( 12, min( 60, $limit * 4 ) ),
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'fields'                 => 'ids',
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
		);
		$taxonomy = $this->course_taxonomy();
		if ( $taxonomy && $category ) {
			$term = term_exists( $category, $taxonomy );
			if ( $term ) {
				$args['tax_query'] = array(
					array(
						'taxonomy' => $taxonomy,
						'field'    => is_numeric( $category ) ? 'term_id' : 'slug',
						'terms'    => is_numeric( $category ) ? absint( $category ) : sanitize_title( $category ),
					),
				);
			}
		}
		$ids = get_posts( $args );
		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
		usort(
			$ids,
			function ( $left, $right ) {
				$left_featured  = $this->is_featured_course( $left ) ? 1 : 0;
				$right_featured = $this->is_featured_course( $right ) ? 1 : 0;
				if ( $left_featured !== $right_featured ) {
					return $right_featured <=> $left_featured;
				}
				$left_order  = (int) get_post_field( 'menu_order', $left );
				$right_order = (int) get_post_field( 'menu_order', $right );
				if ( $left_order !== $right_order ) {
					return $left_order <=> $right_order;
				}
				return (int) get_post_time( 'U', true, $right ) <=> (int) get_post_time( 'U', true, $left );
			}
		);
		return array_slice( $ids, 0, $limit );
	}

	private function product_map( $course_ids ) {
		$map         = array();
		$product_ids = array();
		foreach ( $course_ids as $course_id ) {
			$product_id = absint( get_post_meta( $course_id, '_tutor_course_product_id', true ) );
			$map[ $course_id ] = $product_id;
			if ( $product_id ) {
				$product_ids[] = $product_id;
			}
		}
		if ( ! $this->woocommerce_available() || empty( $product_ids ) ) {
			return array_fill_keys( array_keys( $map ), false );
		}
		$products = wc_get_products(
			array(
				'include' => array_values( array_unique( $product_ids ) ),
				'limit'   => -1,
				'status'  => array( 'publish', 'private' ),
				'return'  => 'objects',
			)
		);
		$products_by_id = array();
		foreach ( $products as $product ) {
			if ( is_object( $product ) && method_exists( $product, 'get_id' ) ) {
				$products_by_id[ (int) $product->get_id() ] = $product;
			}
		}
		foreach ( $map as $course_id => $product_id ) {
			$map[ $course_id ] = $product_id && isset( $products_by_id[ $product_id ] ) ? $products_by_id[ $product_id ] : false;
		}
		return $map;
	}

	private function enrolled_ids() {
		if ( ! is_user_logged_in() || ! $this->tutor_available() ) {
			return array();
		}
		$enrolled = tutor_utils()->get_enrolled_courses_by_user( get_current_user_id(), array( 'publish', 'private' ) );
		$posts    = $enrolled instanceof WP_Query ? $enrolled->posts : ( is_array( $enrolled ) ? $enrolled : array() );
		$ids      = array();
		foreach ( $posts as $course ) {
			$id = is_object( $course ) && isset( $course->ID ) ? $course->ID : $course;
			if ( absint( $id ) ) {
				$ids[] = absint( $id );
			}
		}
		return array_values( array_unique( $ids ) );
	}

	private function progress( $course_id ) {
		if ( ! $this->tutor_available() || ! is_user_logged_in() ) {
			return array( 'percent' => 0, 'completed' => 0, 'total' => 0 );
		}
		$stats = tutor_utils()->get_course_completed_percent( $course_id, get_current_user_id(), true );
		if ( ! is_array( $stats ) ) {
			return array( 'percent' => max( 0, min( 100, (int) $stats ) ), 'completed' => 0, 'total' => 0 );
		}
		return array(
			'percent'   => max( 0, min( 100, (int) ( $stats['completed_percent'] ?? 0 ) ) ),
			'completed' => absint( $stats['completed_count'] ?? 0 ),
			'total'     => absint( $stats['total_count'] ?? 0 ),
		);
	}

	private function continue_url( $course_id, $percent ) {
		$course_url = get_permalink( $course_id );
		if ( $percent <= 0 || ! $this->tutor_available() ) {
			return $course_url;
		}
		$lesson = tutor_utils()->get_course_first_lesson( $course_id );
		if ( is_numeric( $lesson ) ) {
			$lesson = get_permalink( absint( $lesson ) );
		} elseif ( $lesson instanceof WP_Post ) {
			$lesson = get_permalink( $lesson->ID );
		}
		return $lesson ? $lesson : $course_url;
	}

	private function course_detail( $course_id, $kind ) {
		if ( $this->tutor_available() ) {
			$method = 'level' === $kind ? 'get_course_level' : 'get_course_duration_context';
			if ( is_object( tutor_utils() ) && method_exists( tutor_utils(), $method ) ) {
				$value = tutor_utils()->$method( $course_id );
				if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
					return wp_strip_all_tags( (string) $value );
				}
			}
		}
		$meta_keys = 'level' === $kind ? array( '_tutor_course_level', 'course_level' ) : array( '_tutor_course_duration', 'course_duration' );
		foreach ( $meta_keys as $meta_key ) {
			$value = get_post_meta( $course_id, $meta_key, true );
			if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
				return wp_strip_all_tags( (string) $value );
			}
		}
		return '';
	}

	private function cart_contains( $product_id ) {
		if ( ! $product_id || ! $this->woocommerce_available() ) {
			return false;
		}
		if ( function_exists( 'wc_load_cart' ) && ( ! WC()->cart || ! WC()->session ) ) {
			wc_load_cart();
		}
		if ( ! WC()->cart ) {
			return false;
		}
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( $product_id === absint( $item['product_id'] ?? 0 ) || $product_id === absint( $item['variation_id'] ?? 0 ) ) {
				return true;
			}
		}
		return false;
	}

	private function course_data( $course_ids ) {
		if ( empty( $course_ids ) ) {
			return array();
		}
		$products     = $this->product_map( $course_ids );
		$enrolled_ids = $this->enrolled_ids();
		$author_ids   = array();
		foreach ( $course_ids as $course_id ) {
			$author_ids[] = (int) get_post_field( 'post_author', $course_id );
		}
		$authors = array();
		foreach ( array_unique( $author_ids ) as $author_id ) {
			$authors[ $author_id ] = get_the_author_meta( 'display_name', $author_id );
		}
		$rows = array();
		foreach ( $course_ids as $course_id ) {
			$product     = $products[ $course_id ] ?? false;
			$progress    = $this->progress( $course_id );
			$is_enrolled = in_array( $course_id, $enrolled_ids, true );
			$is_free     = ! $product || ( method_exists( $product, 'is_free' ) ? $product->is_free() : (float) $product->get_price() <= 0 );
			$taxonomy    = $this->course_taxonomy();
			$terms       = $taxonomy ? get_the_terms( $course_id, $taxonomy ) : array();
			$term_names  = is_array( $terms ) ? wp_list_pluck( array_slice( $terms, 0, 2 ), 'name' ) : array();
			$image       = get_the_post_thumbnail_url( $course_id, 'medium_large' );
			$course_url  = get_permalink( $course_id );
			$action_url  = $is_enrolled ? $this->continue_url( $course_id, $progress['percent'] ) : $course_url;

			$rows[] = array(
				'id'          => $course_id,
				'title'       => get_the_title( $course_id ),
				'url'         => $course_url,
				'action_url'  => $action_url,
				'image'       => $image,
				'author'      => $authors[ (int) get_post_field( 'post_author', $course_id ) ] ?? '',
				'terms'       => $term_names,
				'product'     => $product,
				'is_free'     => $is_free,
				'is_enrolled' => $is_enrolled,
				'progress'    => $progress,
				'in_cart'     => $product && $this->cart_contains( $product->get_id() ),
			);
		}
		return $rows;
	}

	private function price_html( $product ) {
		if ( ! $product || ! is_object( $product ) ) {
			return '<span class="aap-price-free">رایگان</span>';
		}
		return wp_kses_post( $product->get_price_html() );
	}

	private function learning_categories() {
		$taxonomy = $this->course_taxonomy();
		if ( ! $taxonomy ) {
			return array();
		}
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
				'number'     => 8,
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);
		return is_wp_error( $terms ) ? array() : $terms;
	}

	private function active_courses( $limit = 3 ) {
		$ids = array_slice( $this->enrolled_ids(), 0, $limit );
		return $ids ? $this->course_data( $ids ) : array();
	}

	private function enqueue_assets() {
		if ( $this->assets_enqueued ) {
			return;
		}
		$this->assets_enqueued = true;
		if ( wp_style_is( 'aap-frontend', 'registered' ) ) {
			wp_enqueue_style( 'aap-frontend' );
		}
		if ( wp_script_is( 'aap-frontend', 'registered' ) ) {
			wp_enqueue_script( 'aap-frontend' );
		}
	}

	private function sanitize_classes( $value ) {
		$classes = preg_split( '/\s+/', trim( (string) $value ) );
		$classes = array_filter( array_map( 'sanitize_html_class', $classes ) );
		return implode( ' ', $classes );
	}

	/* ==================== Shared UI helpers ==================== */

	private function course_card( $row, $index ) {
		$progress = $row['progress'];
		$status   = '';
		$label    = '';
		$url      = $row['action_url'];
		$is_owned_class = '';

		if ( $row['is_enrolled'] ) {
			$is_owned_class = 'is-owned';
			if ( $progress['percent'] >= 100 ) {
				$status = 'تکمیل شده';
				$label  = 'مرور دوره';
			} elseif ( $progress['percent'] > 0 ) {
				$status = 'در حال یادگیری';
				$label  = 'ادامه دوره';
			} else {
				$status = 'دسترسی فعال';
				$label  = 'مشاهده دوره';
			}
		} elseif ( $row['in_cart'] ) {
			$status = 'در سبد خرید';
			$label  = 'مشاهده سبد خرید';
			$url    = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/' );
		} elseif ( $row['is_free'] ) {
			$status = 'رایگان';
			$label  = is_user_logged_in() ? 'شروع دوره' : 'ورود برای ثبت نام';
			$url    = is_user_logged_in() ? $row['url'] : ( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url( $row['url'] ) );
		} else {
			$status = 'ثبت نام دوره';
			$label  = 'افزودن به سبد خرید';
			$url    = $row['product'] ? add_query_arg( 'add-to-cart', $row['product']->get_id(), home_url( '/' ) ) : $row['url'];
		}
		?>
		<article class="aap-course-card <?php echo esc_attr( $is_owned_class ); ?>" data-reveal style="--aap-delay: <?php echo esc_attr( min( $index * 70, 420 ) ); ?>ms">
			<div class="aap-course-shell">
				<div class="aap-course-inner">
					<a class="aap-course-media" href="<?php echo esc_url( $row['url'] ); ?>" aria-label="<?php echo esc_attr( 'مشاهده ' . $row['title'] ); ?>">
						<?php if ( $row['image'] ) : ?>
							<img src="<?php echo esc_url( $row['image'] ); ?>" alt="<?php echo esc_attr( $row['title'] ); ?>" loading="lazy" decoding="async">
						<?php else : ?>
							<img src="https://picsum.photos/seed/<?php echo esc_attr( 'aap-course-' . $row['id'] ); ?>/640/360" alt="<?php echo esc_attr( $row['title'] ); ?>" loading="lazy" decoding="async">
						<?php endif; ?>
						<span class="aap-course-badge <?php echo esc_attr( $is_owned_class ); ?>"><?php echo esc_html( $status ); ?></span>
					</a>
					<div class="aap-course-body">
						<div class="aap-course-top">
							<h3><a href="<?php echo esc_url( $row['url'] ); ?>"><?php echo esc_html( $row['title'] ); ?></a></h3>
							<?php if ( $row['terms'] ) : ?>
								<span class="aap-course-cat"><?php echo esc_html( implode( '، ', $row['terms'] ) ); ?></span>
							<?php endif; ?>
						</div>
						<p class="aap-course-teacher"><?php echo esc_html( $row['author'] ? 'مدرس: ' . $row['author'] : 'آکادمی اسدزاده' ); ?></p>
						<div class="aap-course-meta">
							<span><?php echo esc_html( $this->course_detail( $row['id'], 'level' ) ?: 'همه سطوح' ); ?></span>
							<?php $dur = $this->course_detail( $row['id'], 'duration' ); if ( $dur ) : ?><span><?php echo esc_html( $dur ); ?></span><?php endif; ?>
						</div>
						<?php if ( $row['is_enrolled'] ) : ?>
							<div class="aap-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( $progress['percent'] ); ?>">
								<div class="aap-progress-head"><span>پیشرفت شما</span><strong><?php echo esc_html( $progress['percent'] ); ?>٪</strong></div>
								<span class="aap-progress-track"><i style="width: <?php echo esc_attr( $progress['percent'] ); ?>%"></i></span>
								<div class="aap-progress-meta"><span><?php echo esc_html( $progress['completed'] ); ?> از <?php echo esc_html( $progress['total'] ); ?> بخش</span></div>
							</div>
						<?php elseif ( ! $row['is_free'] ) : ?>
							<div class="aap-course-price"><?php echo $this->price_html( $row['product'] ); ?></div>
						<?php else : ?>
							<div class="aap-course-price"><span class="aap-price-free">رایگان</span></div>
						<?php endif; ?>
						<a class="aap-btn is-course <?php echo esc_attr( $is_owned_class ); ?>" href="<?php echo esc_url( $url ); ?>">
							<span class="aap-btn-label"><?php echo esc_html( $label ); ?></span>
							<span class="aap-btn-icon" aria-hidden="true">
								<svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M10 3L13 8L10 13" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path d="M13 8H3" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
							</span>
						</a>
					</div>
				</div>
			</div>
		</article>
		<?php
	}

	/* ==================== Render: Home ==================== */

	public function render_home( $raw_settings = array() ) {
		$this->enqueue_assets();
		$settings = shortcode_atts(
			array(
				'courses'      => '6',
				'category'     => '',
				'custom_class' => '',
			),
			(array) $raw_settings,
			'asadzadeh_home'
		);
		$limit       = max( 1, min( 12, absint( $settings['courses'] ) ?: 6 ) );
		$category    = sanitize_text_field( (string) $settings['category'] );
		$custom_cls  = $this->sanitize_classes( $settings['custom_class'] );
		$instance    = 'aap-home-' . wp_unique_id();
		$course_rows = $this->tutor_available() ? $this->course_data( $this->course_ids( $limit, $category ) ) : array();
		$categories  = $this->tutor_available() ? $this->learning_categories() : array();
		$active      = is_user_logged_in() && $this->tutor_available() ? $this->active_courses( 3 ) : array();
		$account_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url();
		$courses_url = $this->get_courses_url();
		$dashboard_url = function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'my-courses' ) : home_url( '/my-account/my-courses/' );

		$fallback_cats = array(
			array( 'name' => 'قالی بافی', 'desc' => 'آموزش حرفه‌ای قالیبافی از مقدماتی تا پیشرفته', 'seed' => 'qali-bafi', 'count' => '12' ),
			array( 'name' => 'گلیم بافی', 'desc' => 'آشنایی با نقش، رنگ و بافت گلیم‌های اصیل ایرانی', 'seed' => 'gelim-bafi', 'count' => '8' ),
			array( 'name' => 'گبه بافی', 'desc' => 'از پشم تا نقش، آموزش ساده و کاربردی گبه بافی', 'seed' => 'gabbeh-bafi', 'count' => '6' ),
			array( 'name' => 'هنرهای سنتی', 'desc' => 'آشنایی با هنرهای اصیل ایرانی، از بافت تا تزئینات', 'seed' => 'honar-sonnati', 'count' => '10' ),
		);

		ob_start();
		?>
		<div id="<?php echo esc_attr( $instance ); ?>" class="aap-home aap-elementor-compatible <?php echo esc_attr( $custom_cls ); ?>" dir="rtl" data-aap-page="home">
			<?php if ( ! $this->tutor_available() || ! $this->woocommerce_available() ) : ?>
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
						<div class="aap-visual-shell">
							<div class="aap-visual-inner">
								<img src="https://asadzadehacademy.ir/wp-content/uploads/2026/09/%DA%A9%D8%A7%D8%B1%DA%AF%D8%A7%D9%87-%D9%82%D8%A7%D9%84%DB%8C%D8%A8%D8%A7%D9%81%DB%8C-1024x768.png" alt="کارگاه قالیبافی آکادمی اسدزاده" loading="eager" decoding="async" onerror="this.src='https://picsum.photos/seed/asadzadeh-hero/1024/768'">
								<div class="aap-visual-grain" aria-hidden="true"></div>
							</div>
						</div>
						<div class="aap-float-card is-top">
							<span class="aap-float-num">+20</span>
							<span class="aap-float-label">دوره آموزشی آنلاین و حضوری</span>
						</div>
						<div class="aap-float-card is-bottom">
							<span class="aap-float-quote">هنر راهی برای زندگی زیباتر</span>
							<span class="aap-float-sub">با دست‌ها می‌آموزیم، با دل‌ها ماندگار می‌کنیم</span>
						</div>
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
					<div class="aap-head-stack">
						<span class="aap-eyebrow">دوره‌های ویژه</span>
						<h2 id="<?php echo esc_attr( $instance . '-courses' ); ?>">برای قدم بعدی یادگیری</h2>
					</div>
					<a class="aap-btn is-secondary is-sm" href="<?php echo esc_url( $courses_url ); ?>"><span class="aap-btn-label">همه دوره‌ها</span></a>
				</div>
				<?php if ( $course_rows ) : ?>
					<div class="aap-course-grid">
						<?php foreach ( $course_rows as $i => $row ) : $this->course_card( $row, $i ); endforeach; ?>
					</div>
				<?php elseif ( ! $this->tutor_available() ) : ?>
					<div class="aap-empty" data-reveal><div class="aap-empty-shell"><h3>Tutor LMS فعال نیست</h3><p>پس از فعال‌سازی، دوره‌ها اینجا نمایش داده می‌شوند</p></div></div>
				<?php else : ?>
					<div class="aap-empty" data-reveal><div class="aap-empty-shell"><h3>هنوز دوره‌ای منتشر نشده</h3><p>به زودی دوره‌های قالیبافی، گلیم‌بافی و گبه‌بافی در این بخش قرار می‌گیرد</p><a class="aap-btn is-primary" href="<?php echo esc_url( $courses_url ); ?>"><span class="aap-btn-label">مشاهده دسته‌ها</span></a></div></div>
				<?php endif; ?>
			</section>

			<section class="aap-section aap-cats" aria-labelledby="<?php echo esc_attr( $instance . '-cats' ); ?>">
				<div class="aap-section-head">
					<h2 id="<?php echo esc_attr( $instance . '-cats' ); ?>">دسته‌های آموزشی</h2>
					<a class="aap-text-link" href="<?php echo esc_url( $courses_url ); ?>">مشاهده همه دسته‌ها <span aria-hidden="true">←</span></a>
				</div>
				<div class="aap-bento">
					<?php
					$cats_to_show = $categories ? array_slice( $categories, 0, 4 ) : array();
					if ( $cats_to_show ) {
						$bento_layout = array( 'is-large', 'is-small', 'is-small', 'is-wide' );
						foreach ( $cats_to_show as $idx => $term ) {
							$term_link = get_term_link( $term );
							$term_name = $term->name;
							$term_count = $term->count;
							$seed = sanitize_title( $term->slug ) . '-' . $term->term_id;
							$layout_class = $bento_layout[ $idx % 4 ];
							?>
							<a class="aap-bento-card <?php echo esc_attr( $layout_class ); ?>" href="<?php echo esc_url( is_wp_error( $term_link ) ? $courses_url : $term_link ); ?>" data-reveal style="--aap-delay: <?php echo esc_attr( $idx * 90 ); ?>ms">
								<div class="aap-bento-shell"><div class="aap-bento-inner">
									<div class="aap-bento-media"><img src="https://picsum.photos/seed/<?php echo esc_attr( $seed ); ?>/800/450" alt="<?php echo esc_attr( $term_name ); ?>" loading="lazy"></div>
									<div class="aap-bento-body"><h3><?php echo esc_html( $term_name ); ?></h3><p><?php echo esc_html( $term->description ? wp_trim_words( $term->description, 12 ) : 'آموزش تخصصی و کاربردی هنرهای اصیل' ); ?></p><span class="aap-bento-meta"><?php echo esc_html( $term_count ); ?> دوره</span></div>
								</div></div>
							</a>
							<?php
						}
					} else {
						foreach ( $fallback_cats as $idx => $cat ) {
							$layout_class = array( 'is-large', 'is-small', 'is-small', 'is-wide' )[ $idx % 4 ];
							?>
							<a class="aap-bento-card <?php echo esc_attr( $layout_class ); ?>" href="<?php echo esc_url( $courses_url ); ?>" data-reveal style="--aap-delay: <?php echo esc_attr( $idx * 90 ); ?>ms">
								<div class="aap-bento-shell"><div class="aap-bento-inner">
									<div class="aap-bento-media"><img src="https://picsum.photos/seed/<?php echo esc_attr( $cat['seed'] ); ?>/800/450" alt="<?php echo esc_attr( $cat['name'] ); ?>" loading="lazy"></div>
									<div class="aap-bento-body"><h3><?php echo esc_html( $cat['name'] ); ?></h3><p><?php echo esc_html( $cat['desc'] ); ?></p><span class="aap-bento-meta"><?php echo esc_html( $cat['count'] ); ?> دوره</span></div>
								</div></div>
							</a>
							<?php
						}
					}
					?>
				</div>
			</section>

			<section class="aap-section aap-why" aria-labelledby="<?php echo esc_attr( $instance . '-why' ); ?>">
				<div class="aap-why-grid">
					<div class="aap-why-head" data-reveal>
						<h2 id="<?php echo esc_attr( $instance . '-why' ); ?>">چرا آکادمی اسدزاده</h2>
						<p>آموزش اصولی هنرهای سنتی، از تجربه استاد تا همراهی هنرجو</p>
					</div>
					<div class="aap-why-list">
						<div class="aap-why-item" data-reveal><span class="aap-why-num">01</span><div><h3>آموزش عملی و اصولی</h3><p>یادگیری با تمرین، پروژه و تجربه واقعی در کارگاه مجهز</p></div></div>
						<div class="aap-why-item" data-reveal style="--aap-delay: 80ms"><span class="aap-why-num">02</span><div><h3>مدرس باتجربه</h3><p>آموزش بر پایه سال‌ها تجربه عملی و آموزشی استاد اسدزاده</p></div></div>
						<div class="aap-why-item" data-reveal style="--aap-delay: 160ms"><span class="aap-why-num">03</span><div><h3>همراهی هنرجو</h3><p>پشتیبانی و راهنمایی در طول مسیر یادگیری تا خلق اثر نهایی</p></div></div>
						<div class="aap-why-item" data-reveal style="--aap-delay: 240ms"><span class="aap-why-num">04</span><div><h3>گواهی پایان دوره</h3><p>ارائه گواهی پس از تکمیل دوره‌های واجد شرایط</p></div></div>
					</div>
				</div>
			</section>

			<section class="aap-section aap-master" aria-labelledby="<?php echo esc_attr( $instance . '-master' ); ?>">
				<div class="aap-master-grid">
					<div class="aap-master-visual" data-reveal>
						<div class="aap-master-shell"><div class="aap-master-inner"><img src="https://picsum.photos/seed/ostad-naser-aa/800/1000" alt="استاد ناصر اسدزاده" loading="lazy"></div></div>
						<div class="aap-master-quote"><p>حفظ هنرهای سنتی، حفظ بخشی از هویت و فرهنگ ماست</p></div>
					</div>
					<div class="aap-master-copy" data-reveal style="--aap-delay: 120ms">
						<h2 id="<?php echo esc_attr( $instance . '-master' ); ?>">استاد ناصر اسدزاده</h2>
						<span class="aap-master-role">مدرس و بنیان‌گذار آکادمی</span>
						<p>با بیش از دو دهه تجربه در آموزش قالیبافی، گلیم‌بافی و هنرهای بافت ایرانی، هدف استاد اسدزاده انتقال اصول صحیح و تجربه عملی این هنرها به نسل جدید هنرجویان است.</p>
						<p>ما در آکادمی اسدزاده باور داریم که هنرهای سنتی تنها یک مهارت نیستند، بلکه پلی هستند میان گذشته و آینده.</p>
						<div class="aap-master-stats">
							<div><strong>بیش از 20 سال</strong><span>تجربه آموزش هنرهای سنتی</span></div>
							<div><strong>صدها هنرجو</strong><span>آموزش و همراهی هنرجویان</span></div>
						</div>
						<a class="aap-text-link" href="<?php echo esc_url( $courses_url ); ?>">درباره استاد <span aria-hidden="true">←</span></a>
					</div>
				</div>
			</section>

			<?php if ( $active ) : ?>
				<section class="aap-section aap-continue" aria-labelledby="<?php echo esc_attr( $instance . '-continue' ); ?>">
					<div class="aap-section-head">
						<h2 id="<?php echo esc_attr( $instance . '-continue' ); ?>">ادامه یادگیری</h2>
						<a class="aap-btn is-secondary is-sm" href="<?php echo esc_url( $dashboard_url ); ?>"><span class="aap-btn-label">دوره‌های من</span></a>
					</div>
					<div class="aap-course-grid">
						<?php foreach ( $active as $idx => $row ) : $this->course_card( $row, $idx ); endforeach; ?>
					</div>
				</section>
			<?php endif; ?>

			<section class="aap-section aap-workshops" aria-labelledby="<?php echo esc_attr( $instance . '-workshops' ); ?>">
				<div class="aap-section-head">
					<h2 id="<?php echo esc_attr( $instance . '-workshops' ); ?>">کارگاه‌های حضوری هنرهای سنتی</h2>
					<p>تجربه‌ای عملی و الهام بخش در کنار استاد برای یادگیری اصولی</p>
				</div>
				<div class="aap-workshop-grid">
					<article class="aap-workshop-card is-main" data-reveal>
						<div class="aap-workshop-shell"><div class="aap-workshop-inner">
							<div class="aap-workshop-media"><img src="https://picsum.photos/seed/workshop-main-aa/800/450" alt="کارگاه حضوری قالیبافی" loading="lazy"></div>
							<div class="aap-workshop-body"><span class="aap-workshop-tag">حضوری</span><h3>کارگاه حضوری قالیبافی مقدماتی</h3><p>از اولین گره تا بافت کامل، همراه با استاد در کارگاه مجهز</p><div class="aap-workshop-meta"><span>6 ساعت</span><span>متوسط</span></div><a class="aap-btn is-primary is-sm" href="<?php echo esc_url( $courses_url ); ?>"><span class="aap-btn-label">مشاهده کارگاه</span></a></div>
						</div></div>
					</article>
					<div class="aap-workshop-stack">
						<article class="aap-workshop-card" data-reveal style="--aap-delay: 90ms"><div class="aap-workshop-shell"><div class="aap-workshop-inner"><div class="aap-workshop-body"><span class="aap-workshop-tag">آنلاین</span><h3>گلیم بافی از پایه</h3><p>نقش، رنگ و بافت گلیم‌های اصیل</p><a class="aap-text-link" href="<?php echo esc_url( $courses_url ); ?>">مشاهده دوره <span>←</span></a></div></div></div></article>
						<article class="aap-workshop-card" data-reveal style="--aap-delay: 180ms"><div class="aap-workshop-shell"><div class="aap-workshop-inner"><div class="aap-workshop-body"><span class="aap-workshop-tag">حضوری و آنلاین</span><h3>گبه بافی کاربردی</h3><p>از پشم تا نقش</p><a class="aap-text-link" href="<?php echo esc_url( $courses_url ); ?>">مشاهده دوره <span>←</span></a></div></div></div></article>
					</div>
				</div>
			</section>

			<section class="aap-section aap-testimonials" aria-labelledby="<?php echo esc_attr( $instance . '-test' ); ?>">
				<div class="aap-section-head"><h2 id="<?php echo esc_attr( $instance . '-test' ); ?>">تجربه هنرجویان</h2><p>روایت هنرجویانی که مسیر یادگیری را با آکادمی اسدزاده تجربه کرده‌اند</p></div>
				<div class="aap-test-grid">
					<article class="aap-test-card is-large" data-reveal><p>آموزش بسیار اصولی و کاربردی بود. از اولین گره تا بافت کامل را قدم به قدم یاد گرفتم. همراهی استاد در طول دوره عالی بود.</p><div class="aap-test-foot"><img src="https://picsum.photos/seed/aa-test-1/80/80" alt="هنرجو" loading="lazy"><div><strong>سارا احمدی</strong><span>دوره قالیبافی مقدماتی</span></div></div></article>
					<article class="aap-test-card" data-reveal style="--aap-delay: 90ms"><p>کارگاه حضوری تجربه‌ای متفاوت بود. فضای کارگاه و آموزش عملی باعث شد خیلی سریع پیشرفت کنم.</p><div class="aap-test-foot"><img src="https://picsum.photos/seed/aa-test-2/80/80" alt="هنرجو" loading="lazy"><div><strong>محمد حسینی</strong><span>کارگاه حضوری</span></div></div></article>
					<article class="aap-test-card" data-reveal style="--aap-delay: 180ms"><p>پشتیبانی عالی و دسترسی آسان به محتوای دوره. حتی بعد از اتمام دوره هم پاسخگوی سوالاتم بودند.</p><div class="aap-test-foot"><img src="https://picsum.photos/seed/aa-test-3/80/80" alt="هنرجو" loading="lazy"><div><strong>فاطمه کریمی</strong><span>دوره گلیم بافی</span></div></div></article>
				</div>
			</section>

			<section class="aap-section aap-faq" aria-labelledby="<?php echo esc_attr( $instance . '-faq' ); ?>">
				<div class="aap-faq-grid">
					<div class="aap-faq-head" data-reveal><h2 id="<?php echo esc_attr( $instance . '-faq' ); ?>">سوالات متداول</h2><p>پاسخ سوال‌هایی که ممکن است قبل از شروع دوره یا ثبت نام داشته باشید</p></div>
					<div class="aap-faq-list" data-reveal style="--aap-delay: 100ms">
						<details class="aap-faq-item" open><summary>آیا برای شرکت در دوره‌ها نیاز به تجربه قبلی دارم<span aria-hidden="true"></span></summary><p>خیر. دوره‌های مقدماتی از پایه طراحی شده‌اند و برای شروع نیاز به تجربه قبلی ندارید.</p></details>
						<details class="aap-faq-item"><summary>دوره‌ها به صورت آنلاین هستند یا حضوری<span aria-hidden="true"></span></summary><p>بسته به دوره، آموزش‌ها می‌توانند آنلاین یا حضوری باشند. نوع برگزاری در صفحه هر دوره مشخص می‌شود.</p></details>
						<details class="aap-faq-item"><summary>کارگاه‌های حضوری کجا برگزار می‌شوند<span aria-hidden="true"></span></summary><p>محل برگزاری هر کارگاه در صفحه همان کارگاه اعلام می‌شود و قبل از ثبت نام قابل مشاهده است.</p></details>
						<details class="aap-faq-item"><summary>بعد از ثبت نام چطور به دوره دسترسی پیدا می‌کنم<span aria-hidden="true"></span></summary><p>پس از تکمیل ثبت نام، دوره در حساب کاربری شما فعال می‌شود و از پنل هنرجویی قابل دسترسی است.</p></details>
						<details class="aap-faq-item"><summary>آیا پس از پایان دوره گواهی دریافت می‌کنم<span aria-hidden="true"></span></summary><p>برای دوره‌هایی که شرایط دریافت گواهی دارند، پس از تکمیل دوره گواهی پایان دوره صادر می‌شود.</p></details>
					</div>
				</div>
			</section>

			<section class="aap-final" aria-labelledby="<?php echo esc_attr( $instance . '-cta' ); ?>" data-reveal>
				<div class="aap-final-shell"><div class="aap-final-inner">
					<div class="aap-final-copy">
						<h2 id="<?php echo esc_attr( $instance . '-cta' ); ?>"><?php echo esc_html( is_user_logged_in() ? 'دوره بعدی‌ات را انتخاب کن' : 'آماده‌ای اولین گره را بزنی' ); ?></h2>
						<p>دوره‌ها و کارگاه‌های آکادمی اسدزاده برای شروع از پایه طراحی شده‌اند. مسیر مناسب خودت را انتخاب کن و قدم به قدم پیش برو.</p>
					</div>
					<div class="aap-final-actions">
						<a class="aap-btn is-light" href="<?php echo esc_url( is_user_logged_in() ? $courses_url : $account_url ); ?>"><span class="aap-btn-label"><?php echo esc_html( is_user_logged_in() ? 'دیدن دوره‌ها' : 'ورود و ثبت نام' ); ?></span><span class="aap-btn-icon" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M10 3L13 8L10 13" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path d="M13 8H3" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg></span></a>
						<?php if ( is_user_logged_in() ) : ?>
							<a class="aap-btn is-ghost" href="<?php echo esc_url( $dashboard_url ); ?>"><span class="aap-btn-label">دوره‌های من</span></a>
						<?php else : ?>
							<a class="aap-btn is-ghost" href="<?php echo esc_url( $courses_url ); ?>"><span class="aap-btn-label">کارگاه‌های حضوری</span></a>
						<?php endif; ?>
					</div>
				</div></div>
			</section>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/* ==================== Render: Courses ==================== */

	public function render_courses( $raw_settings = array() ) {
		$this->enqueue_assets();
		$settings = shortcode_atts(
			array(
				'courses'      => '9',
				'category'     => '',
				'search'       => '',
				'custom_class' => '',
			),
			(array) $raw_settings,
			'asadzadeh_courses'
		);
		$limit      = max( 1, min( 24, absint( $settings['courses'] ) ?: 9 ) );
		$category   = sanitize_text_field( (string) $settings['category'] );
		$search     = sanitize_text_field( (string) $settings['search'] );
		$custom_cls = $this->sanitize_classes( $settings['custom_class'] );
		$instance   = 'aap-courses-' . wp_unique_id();
		$course_ids = $this->tutor_available() ? $this->course_ids( $limit, $category ) : array();
		$course_rows = $this->course_data( $course_ids );
		$categories  = $this->learning_categories();
		$courses_url = $this->get_courses_url();

		ob_start();
		?>
		<div id="<?php echo esc_attr( $instance ); ?>" class="aap-courses-page aap-elementor-compatible <?php echo esc_attr( $custom_cls ); ?>" dir="rtl" data-aap-page="courses">
			<section class="aap-page-hero" data-reveal>
				<div class="aap-page-hero-inner">
					<span class="aap-eyebrow">مسیر یادگیری شما</span>
					<h1>دوره‌های آکادمی اسدزاده</h1>
					<p>از مقدماتی تا پیشرفته، قالیبافی، گلیم‌بافی، گبه‌بافی و هنرهای سنتی را اصولی و عملی بیاموزید</p>
					<div class="aap-search-bar">
						<form role="search" method="get" action="<?php echo esc_url( $courses_url ); ?>">
							<input type="search" name="s" placeholder="جستجوی دوره..." value="<?php echo esc_attr( $search ); ?>">
							<button class="aap-btn is-primary is-sm" type="submit"><span class="aap-btn-label">جستجو</span></button>
						</form>
					</div>
				</div>
			</section>

			<?php if ( $categories ) : ?>
				<section class="aap-section" data-reveal>
					<div class="aap-filter-grid">
						<?php foreach ( array_slice( $categories, 0, 8 ) as $term ) : $link = get_term_link( $term ); ?>
							<a class="aap-filter-card" href="<?php echo esc_url( is_wp_error( $link ) ? $courses_url : $link ); ?>">
								<strong><?php echo esc_html( $term->name ); ?></strong>
								<span><?php echo esc_html( $term->count ); ?> دوره</span>
							</a>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>

			<section class="aap-section aap-courses">
				<?php if ( $course_rows ) : ?>
					<div class="aap-course-grid">
						<?php foreach ( $course_rows as $i => $row ) : $this->course_card( $row, $i ); endforeach; ?>
					</div>
				<?php else : ?>
					<div class="aap-empty" data-reveal><div class="aap-empty-shell"><h3>دوره‌ای یافت نشد</h3><p>فیلتر دسته را تغییر دهید یا بعداً دوباره سر بزنید</p></div></div>
				<?php endif; ?>
			</section>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/* ==================== Render: About ==================== */

	public function render_about( $raw_settings = array() ) {
		$this->enqueue_assets();
		$settings = shortcode_atts(
			array(
				'custom_class' => '',
			),
			(array) $raw_settings,
			'asadzadeh_about'
		);
		$custom_cls = $this->sanitize_classes( $settings['custom_class'] );
		$instance   = 'aap-about-' . wp_unique_id();
		$courses_url = $this->get_courses_url();

		ob_start();
		?>
		<div id="<?php echo esc_attr( $instance ); ?>" class="aap-about-page aap-elementor-compatible <?php echo esc_attr( $custom_cls ); ?>" dir="rtl" data-aap-page="about">
			<section class="aap-page-hero" data-reveal>
				<div class="aap-page-hero-inner">
					<span class="aap-eyebrow">درباره آکادمی</span>
					<h1>حفظ هنر، انتقال تجربه</h1>
					<p>آکادمی اسدزاده با تکیه بر دو دهه آموزش هنرهای بافت ایرانی، پلی است میان اصالت و آینده</p>
				</div>
				<div class="aap-about-hero-visual" data-reveal style="--aap-delay: 120ms">
					<div class="aap-visual-inner"><img src="https://picsum.photos/seed/asadzadeh-about-hero/1280/500" alt="کارگاه آکادمی اسدزاده" loading="lazy"></div>
				</div>
			</section>

			<section class="aap-section aap-story">
				<div class="aap-story-grid">
					<div class="aap-story-copy" data-reveal>
						<h2>داستان آکادمی</h2>
						<p>از کارگاهی کوچک با چند دار قالی شروع کردیم. امروز آکادمی اسدزاده میزبان صدها هنرجو است که از اولین گره تا خلق اثری اصیل را با ما تجربه کرده‌اند.</p>
						<p>باور ما این است که هنرهای سنتی فقط مهارت نیستند، هویت‌اند. هر گره، حافظه‌ای از فرهنگ این سرزمین است.</p>
						<ul class="aap-check-list">
							<li><i></i><span>آموزش اصولی از پایه تا پیشرفته</span></li>
							<li><i></i><span>کارگاه مجهز حضوری و محتوای آنلاین</span></li>
							<li><i></i><span>پشتیبانی و همراهی تا خلق اثر نهایی</span></li>
							<li><i></i><span>گواهی پایان دوره برای دوره‌های واجد شرایط</span></li>
						</ul>
					</div>
					<div class="aap-story-stats" data-reveal style="--aap-delay: 100ms">
						<div><strong>+20</strong><span>دوره آموزشی</span></div>
						<div><strong>+500</strong><span>هنرجو</span></div>
						<div><strong>20 سال</strong><span>تجربه</span></div>
						<div><strong>گواهی</strong><span>معتبر پایان دوره</span></div>
					</div>
				</div>
			</section>

			<section class="aap-section aap-master">
				<div class="aap-master-grid">
					<div class="aap-master-visual" data-reveal>
						<div class="aap-master-shell"><div class="aap-master-inner"><img src="https://picsum.photos/seed/ostad-naser-about/800/1000" alt="استاد ناصر اسدزاده" loading="lazy"></div></div>
					</div>
					<div class="aap-master-copy" data-reveal style="--aap-delay: 120ms">
						<h2>استاد ناصر اسدزاده</h2>
						<span class="aap-master-role">بنیان‌گذار و مدرس آکادمی</span>
						<p>با بیش از دو دهه تجربه در آموزش قالیبافی و هنرهای بافت، هدف ایشان انتقال تجربه عملی و اصول صحیح به نسل جدید است.</p>
						<p>شعار آکادمی: «با دست‌ها می‌آموزیم، با دل‌ها ماندگار می‌کنیم»</p>
						<a class="aap-btn is-primary" href="<?php echo esc_url( $courses_url ); ?>"><span class="aap-btn-label">مشاهده دوره‌ها</span></a>
					</div>
				</div>
			</section>

			<section class="aap-section aap-why">
				<div class="aap-why-grid">
					<div class="aap-why-head" data-reveal><h2>چرا آکادمی اسدزاده</h2><p>تفاوت ما در اصالت آموزش و همراهی واقعی است</p></div>
					<div class="aap-why-list">
						<div class="aap-why-item" data-reveal><span class="aap-why-num">01</span><div><h3>آموزش عملی</h3><p>یادگیری با تمرین واقعی در کارگاه مجهز</p></div></div>
						<div class="aap-why-item" data-reveal style="--aap-delay: 80ms"><span class="aap-why-num">02</span><div><h3>استاد باتجربه</h3><p>آموزش بر پایه سال‌ها تجربه عملی</p></div></div>
						<div class="aap-why-item" data-reveal style="--aap-delay: 160ms"><span class="aap-why-num">03</span><div><h3>همراهی هنرجو</h3><p>پشتیبانی تا خلق اثر نهایی</p></div></div>
						<div class="aap-why-item" data-reveal style="--aap-delay: 240ms"><span class="aap-why-num">04</span><div><h3>گواهی معتبر</h3><p>ارائه گواهی پایان دوره</p></div></div>
					</div>
				</div>
			</section>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/* ==================== Render: Contact ==================== */

	public function render_contact( $raw_settings = array() ) {
		$this->enqueue_assets();
		$settings = shortcode_atts(
			array(
				'custom_class' => '',
			),
			(array) $raw_settings,
			'asadzadeh_contact'
		);
		$custom_cls = $this->sanitize_classes( $settings['custom_class'] );
		$instance   = 'aap-contact-' . wp_unique_id();

		ob_start();
		?>
		<div id="<?php echo esc_attr( $instance ); ?>" class="aap-contact-page aap-elementor-compatible <?php echo esc_attr( $custom_cls ); ?>" dir="rtl" data-aap-page="contact">
			<section class="aap-page-hero" data-reveal>
				<div class="aap-page-hero-inner">
					<span class="aap-eyebrow">ارتباط با ما</span>
					<h1>پاسخگوی شما هستیم</h1>
					<p>سوال، پیشنهاد یا درخواست مشاوره دارید؟ فرم را پر کنید یا از راه‌های زیر با ما در تماس باشید</p>
				</div>
			</section>

			<section class="aap-section">
				<div class="aap-contact-grid">
					<div class="aap-contact-shell" data-reveal>
						<div class="aap-contact-inner">
							<h2>پیام خود را بفرستید</h2>
							<p>فرم زیر را پر کنید، در اسرع وقت پاسخ می‌دهیم</p>
							<form class="aap-form" method="post" action="">
								<div class="aap-form-row is-double">
									<label><span>نام و نام خانوادگی *</span><input type="text" name="aap_name" required placeholder="نام شما"></label>
									<label><span>شماره تماس *</span><input type="tel" name="aap_phone" required placeholder="09xx xxx xxxx"></label>
								</div>
								<label><span>ایمیل</span><input type="email" name="aap_email" placeholder="email@example.com"></label>
								<label><span>موضوع</span>
									<select name="aap_subject">
										<option>مشاوره دوره‌ها</option>
										<option>کارگاه حضوری</option>
										<option>پشتیبانی هنرجویان</option>
										<option>سایر</option>
									</select>
								</label>
								<label><span>پیام شما *</span><textarea name="aap_message" required placeholder="پیام خود را بنویسید..."></textarea></label>
								<button class="aap-btn is-primary" type="submit"><span class="aap-btn-label">ارسال پیام</span></button>
								<p class="aap-form-note">ارسال فرم به صورت نمایشی است. برای اتصال به افزونه فرم‌ساز، شورت‌کد فرم خود را جایگزین کنید.</p>
							</form>
						</div>
					</div>
					<div class="aap-contact-info" data-reveal style="--aap-delay: 100ms">
						<div class="aap-info-card">
							<strong>تماس مستقیم</strong>
							<a href="tel:+989000000000">+98 900 000 0000</a>
							<span>شنبه تا چهارشنبه ۹ تا ۱۷</span>
						</div>
						<div class="aap-info-card">
							<strong>ایمیل</strong>
							<a href="mailto:info@asadzadehacademy.ir">info@asadzadehacademy.ir</a>
							<span>پاسخ در کمتر از ۲۴ ساعت</span>
						</div>
						<div class="aap-info-card">
							<strong>آدرس کارگاه</strong>
							<span>آدرس کارگاه حضوری در صفحه هر کارگاه اعلام می‌شود</span>
						</div>
						<div class="aap-map-card">
							<div class="aap-map-placeholder">
								<span>نقشه کارگاه</span>
								<small>اینجا می‌توانید iframe نقشه یا تصویر کارگاه را قرار دهید</small>
							</div>
						</div>
						<div class="aap-faq-list" style="border-top:none">
							<details class="aap-faq-item" open><summary>آیا برای شرکت در دوره‌ها نیاز به تجربه قبلی دارم<span></span></summary><p>خیر. دوره‌های مقدماتی از پایه طراحی شده‌اند.</p></details>
							<details class="aap-faq-item"><summary>دوره‌ها آنلاین یا حضوری هستند<span></span></summary><p>بسته به دوره، هر دو حالت وجود دارد و در صفحه دوره مشخص است.</p></details>
							<details class="aap-faq-item"><summary>بعد از ثبت نام دسترسی چگونه است<span></span></summary><p>پس از ثبت نام، دوره در حساب کاربری شما فعال می‌شود.</p></details>
						</div>
					</div>
				</div>
			</section>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/* Generic dispatcher for Elementor widget */
	public function render( $settings = array() ) {
		$type = isset( $settings['aap_type'] ) ? sanitize_key( $settings['aap_type'] ) : 'home';
		switch ( $type ) {
			case 'courses':
				return $this->render_courses( $settings );
			case 'about':
				return $this->render_about( $settings );
			case 'contact':
				return $this->render_contact( $settings );
			case 'home':
			default:
				return $this->render_home( $settings );
		}
	}

	private function get_courses_url() {
		if ( $this->tutor_available() && is_object( tutor_utils() ) && method_exists( tutor_utils(), 'get_courses_page_url' ) ) {
			$maybe = tutor_utils()->get_courses_page_url();
			if ( $maybe ) {
				return $maybe;
			}
		}
		return home_url( '/doreha/' );
	}
}
