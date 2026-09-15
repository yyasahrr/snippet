<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NGT_HDR_Elementor_Widget extends \Elementor\Widget_Base {
	/** @var NGT_HDR_Renderer */
	private $renderer;

	public function __construct( $data = array(), $args = null ) {
		if ( $data instanceof NGT_HDR_Renderer ) {
			$this->renderer = $data;
			$data           = array();
		} elseif ( is_array( $args ) && isset( $args['renderer'] ) ) {
			$this->renderer = $args['renderer'];
		}

		parent::__construct( $data, $args );
	}

	public function get_name() {
		return 'ngt-smart-header';
	}

	public function get_title() {
		return esc_html__( 'هدر حرفه‌ای نقطه', 'noghte-smart-headers' );
	}

	public function get_icon() {
		return 'eicon-header';
	}

	public function get_categories() {
		return array( 'noghte-group' );
	}

	public function get_keywords() {
		return array( 'header', 'menu', 'navbar', 'هدر', 'منو', 'نقطه' );
	}

	public function get_style_depends() {
		return array( 'ngt-hdr-frontend' );
	}

	public function get_script_depends() {
		return array( 'ngt-hdr-frontend' );
	}

	protected function register_controls() {
		$this->register_content_controls();
		$this->register_design_controls();
	}

	private function register_content_controls() {
		$this->start_controls_section(
			'ngt_h_content',
			array(
				'label' => esc_html__( 'محتوای هدر', 'noghte-smart-headers' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'style',
			array(
				'label'   => esc_html__( 'مدل هدر', 'noghte-smart-headers' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '1',
				'options' => array(
					'1' => esc_html__( '۱ — کلاسیک حرفه‌ای', 'noghte-smart-headers' ),
					'2' => esc_html__( '۲ — لوگوی مرکزی دو ردیفه', 'noghte-smart-headers' ),
					'3' => esc_html__( '۳ — فروشگاهی با نوار منو', 'noghte-smart-headers' ),
					'4' => esc_html__( '۴ — شناور شیشه‌ای', 'noghte-smart-headers' ),
					'5'  => esc_html__( '۵ — مینیمال با منوی کناری', 'noghte-smart-headers' ),
					'6'  => esc_html__( '۶ — کپسولی مدرن', 'noghte-smart-headers' ),
					'7'  => esc_html__( '۷ — لوکس Split با لوگوی مرکزی', 'noghte-smart-headers' ),
					'8'  => esc_html__( '۸ — ادیتوریال دو ردیفه', 'noghte-smart-headers' ),
					'9'  => esc_html__( '۹ — Accent Rail خلاقانه', 'noghte-smart-headers' ),
					'10' => esc_html__( '۱۰ — فروشگاهی Pro', 'noghte-smart-headers' ),
				),
			)
		);

		$this->add_control(
			'menu',
			array(
				'label'   => esc_html__( 'منوی وردپرس', 'noghte-smart-headers' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => $this->get_menu_options(),
			)
		);

		$this->add_control(
			'logo',
			array(
				'label'   => esc_html__( 'لوگوی اختصاصی', 'noghte-smart-headers' ),
				'type'    => \Elementor\Controls_Manager::MEDIA,
				'dynamic' => array( 'active' => true ),
				'default' => array( 'url' => '' ),
			)
		);

		$this->add_control(
			'logo_alt',
			array(
				'label'       => esc_html__( 'متن جایگزین لوگو', 'noghte-smart-headers' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => get_bloginfo( 'name' ),
			)
		);

		$this->add_control(
			'show_search',
			array(
				'label'        => esc_html__( 'دکمه جست‌وجو', 'noghte-smart-headers' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'نمایش', 'noghte-smart-headers' ),
				'label_off'    => esc_html__( 'مخفی', 'noghte-smart-headers' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'search_type',
			array(
				'label'     => esc_html__( 'محدوده جست‌وجو', 'noghte-smart-headers' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => 'all',
				'options'   => array(
					'all'     => esc_html__( 'کل سایت', 'noghte-smart-headers' ),
					'product' => esc_html__( 'فقط محصولات', 'noghte-smart-headers' ),
					'post'    => esc_html__( 'فقط نوشته‌ها', 'noghte-smart-headers' ),
				),
				'condition' => array( 'show_search' => 'yes' ),
			)
		);

		$this->add_control(
			'show_cart',
			array(
				'label'        => esc_html__( 'سبد خرید ووکامرس', 'noghte-smart-headers' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_account',
			array(
				'label'        => esc_html__( 'حساب کاربری', 'noghte-smart-headers' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'mobile_actions_heading',
			array(
				'label'     => esc_html__( 'نمایش در موبایل', 'noghte-smart-headers' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'mobile_search',
			array(
				'label'        => esc_html__( 'جست‌وجو در موبایل', 'noghte-smart-headers' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => array( 'show_search' => 'yes' ),
			)
		);

		$this->add_control(
			'mobile_cart',
			array(
				'label'        => esc_html__( 'سبد خرید در موبایل', 'noghte-smart-headers' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => array( 'show_cart' => 'yes' ),
			)
		);

		$this->add_control(
			'mobile_account',
			array(
				'label'        => esc_html__( 'پروفایل در موبایل', 'noghte-smart-headers' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => array( 'show_account' => 'yes' ),
			)
		);

		$this->add_control(
			'cta_heading',
			array(
				'label'     => esc_html__( 'دکمه فراخوان', 'noghte-smart-headers' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'cta_text',
			array(
				'label'       => esc_html__( 'متن دکمه', 'noghte-smart-headers' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => esc_html__( 'مشاهده محصولات', 'noghte-smart-headers' ),
			)
		);

		$this->add_control(
			'cta_link',
			array(
				'label'       => esc_html__( 'لینک دکمه', 'noghte-smart-headers' ),
				'type'        => \Elementor\Controls_Manager::URL,
				'dynamic'     => array( 'active' => true ),
				'placeholder' => 'https://example.com',
				'condition'   => array( 'cta_text!' => '' ),
			)
		);

		$this->add_control(
			'behavior_heading',
			array(
				'label'     => esc_html__( 'رفتار هدر', 'noghte-smart-headers' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'sticky',
			array(
				'label'        => esc_html__( 'چسبان هنگام اسکرول', 'noghte-smart-headers' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
			)
		);

		$this->add_control(
			'transparent',
			array(
				'label'        => esc_html__( 'پس‌زمینه شفاف', 'noghte-smart-headers' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
			)
		);

		$this->add_control(
			'full_width',
			array(
				'label'        => esc_html__( 'تمام‌عرض', 'noghte-smart-headers' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
			)
		);

		$this->end_controls_section();
	}

	private function register_design_controls() {
		$this->start_controls_section(
			'ngt_h_layout',
			array(
				'label' => esc_html__( 'اندازه و چیدمان', 'noghte-smart-headers' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'container_width',
			array(
				'label'      => esc_html__( 'حداکثر عرض محتوا', 'noghte-smart-headers' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 720, 'max' => 1920, 'step' => 10 ) ),
				'default'    => array( 'size' => 1280, 'unit' => 'px' ),
			)
		);

		$this->add_control(
			'header_height',
			array(
				'label'      => esc_html__( 'ارتفاع هدر', 'noghte-smart-headers' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 56, 'max' => 140 ) ),
				'default'    => array( 'size' => 78, 'unit' => 'px' ),
			)
		);

		$this->add_control(
			'logo_width',
			array(
				'label'      => esc_html__( 'عرض لوگو دسکتاپ', 'noghte-smart-headers' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 50, 'max' => 320 ) ),
				'default'    => array( 'size' => 142, 'unit' => 'px' ),
			)
		);

		$this->add_control(
			'mobile_logo_width',
			array(
				'label'      => esc_html__( 'عرض لوگو موبایل', 'noghte-smart-headers' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 50, 'max' => 240 ) ),
				'default'    => array( 'size' => 118, 'unit' => 'px' ),
			)
		);


		$this->add_control(
			'mobile_height',
			array(
				'label'      => esc_html__( 'ارتفاع هدر موبایل', 'noghte-smart-headers' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 54, 'max' => 100 ) ),
				'default'    => array( 'size' => 64, 'unit' => 'px' ),
			)
		);

		$this->add_control(
			'mobile_breakpoint',
			array(
				'label'   => esc_html__( 'نقطه تبدیل به منوی موبایل', 'noghte-smart-headers' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '1024',
				'options' => array(
					'1280' => esc_html__( '۱۲۸۰ پیکسل', 'noghte-smart-headers' ),
					'1100' => esc_html__( '۱۱۰۰ پیکسل', 'noghte-smart-headers' ),
					'1024' => esc_html__( '۱۰۲۴ پیکسل', 'noghte-smart-headers' ),
					'880'  => esc_html__( '۸۸۰ پیکسل', 'noghte-smart-headers' ),
					'767'  => esc_html__( '۷۶۷ پیکسل', 'noghte-smart-headers' ),
				),
			)
		);

		$this->add_control(
			'icon_size',
			array(
				'label'      => esc_html__( 'اندازه آیکون دسکتاپ', 'noghte-smart-headers' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 15, 'max' => 34 ) ),
				'default'    => array( 'size' => 21, 'unit' => 'px' ),
			)
		);

		$this->add_control(
			'mobile_icon_size',
			array(
				'label'      => esc_html__( 'اندازه آیکون موبایل', 'noghte-smart-headers' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 15, 'max' => 30 ) ),
				'default'    => array( 'size' => 20, 'unit' => 'px' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'ngt_h_colors',
			array(
				'label' => esc_html__( 'رنگ‌ها', 'noghte-smart-headers' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control( 'background', array( 'label' => esc_html__( 'پس‌زمینه', 'noghte-smart-headers' ), 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#ffffff' ) );
		$this->add_control( 'text_color', array( 'label' => esc_html__( 'متن و منو', 'noghte-smart-headers' ), 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#15171a' ) );
		$this->add_control( 'muted_color', array( 'label' => esc_html__( 'رنگ ثانویه', 'noghte-smart-headers' ), 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#68707a' ) );
		$this->add_control( 'accent_color', array( 'label' => esc_html__( 'رنگ اصلی و هاور', 'noghte-smart-headers' ), 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#2f6f58' ) );
		$this->add_control( 'border_color', array( 'label' => esc_html__( 'خط و حاشیه', 'noghte-smart-headers' ), 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#e9ecef' ) );
		$this->add_control( 'icon_color', array( 'label' => esc_html__( 'رنگ آیکون‌های عمومی', 'noghte-smart-headers' ), 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#15171a' ) );
		$this->add_control( 'search_icon_color', array( 'label' => esc_html__( 'رنگ مستقل آیکون جست‌وجو', 'noghte-smart-headers' ), 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#15171a' ) );
		$this->add_control( 'menu_icon_color', array( 'label' => esc_html__( 'رنگ مستقل آیکون همبرگری', 'noghte-smart-headers' ), 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#15171a' ) );
		$this->add_control( 'close_icon_color', array( 'label' => esc_html__( 'رنگ آیکون بستن پاپ‌آپ و منوی موبایل', 'noghte-smart-headers' ), 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#15171a' ) );
		$this->add_control( 'icon_hover_color', array( 'label' => esc_html__( 'رنگ هاور آیکون‌ها', 'noghte-smart-headers' ), 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#2f6f58' ) );

		$this->end_controls_section();

		$this->start_controls_section(
			'ngt_h_typography',
			array(
				'label' => esc_html__( 'تایپوگرافی', 'noghte-smart-headers' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'menu_typography',
				'label'    => esc_html__( 'فونت منو', 'noghte-smart-headers' ),
				'selector' => '{{WRAPPER}} .ngt-hdr-menu > li > a, {{WRAPPER}} .ngt-hdr-mobile-menu a',
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'button_typography',
				'label'    => esc_html__( 'فونت دکمه', 'noghte-smart-headers' ),
				'selector' => '{{WRAPPER}} .ngt-hdr-cta',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'ngt_h_effects',
			array(
				'label' => esc_html__( 'حاشیه و سایه', 'noghte-smart-headers' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'header_shadow',
				'selector' => '{{WRAPPER}} .ngt-hdr-shell',
			)
		);

		$this->add_responsive_control(
			'header_radius',
			array(
				'label'      => esc_html__( 'گردی گوشه‌ها', 'noghte-smart-headers' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( '{{WRAPPER}} .ngt-hdr-shell' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		if ( ! $this->renderer ) {
			$this->renderer = new NGT_HDR_Renderer();
		}

		$logo_id  = isset( $settings['logo']['id'] ) ? absint( $settings['logo']['id'] ) : 0;
		$logo_url = isset( $settings['logo']['url'] ) ? $settings['logo']['url'] : '';
		$cta_url  = isset( $settings['cta_link']['url'] ) ? $settings['cta_link']['url'] : '';
		$target   = ! empty( $settings['cta_link']['is_external'] ) ? '_blank' : '_self';

		$args = array(
			'style'             => $settings['style'] ?? '1',
			'menu'              => $settings['menu'] ?? '',
			'logo_id'           => $logo_id,
			'logo_url'          => $logo_url,
			'logo_alt'          => $settings['logo_alt'] ?? '',
			'logo_width'        => $this->slider_size( $settings['logo_width'] ?? array(), 142 ),
			'mobile_logo_width' => $this->slider_size( $settings['mobile_logo_width'] ?? array(), 118 ),
			'search'            => $settings['show_search'] ?? '',
			'search_type'       => $settings['search_type'] ?? 'all',
			'cart'              => $settings['show_cart'] ?? '',
			'account'           => $settings['show_account'] ?? '',
			'mobile_search'     => $settings['mobile_search'] ?? 'yes',
			'mobile_cart'       => $settings['mobile_cart'] ?? 'yes',
			'mobile_account'    => $settings['mobile_account'] ?? 'yes',
			'cta_text'          => $settings['cta_text'] ?? '',
			'cta_url'           => $cta_url,
			'cta_target'        => $target,
			'sticky'            => $settings['sticky'] ?? '',
			'transparent'       => $settings['transparent'] ?? '',
			'full_width'        => $settings['full_width'] ?? '',
			'container_width'   => $this->slider_size( $settings['container_width'] ?? array(), 1280 ),
			'height'            => $this->slider_size( $settings['header_height'] ?? array(), 78 ),
			'mobile_height'     => $this->slider_size( $settings['mobile_height'] ?? array(), 64 ),
			'mobile_breakpoint' => $settings['mobile_breakpoint'] ?? '1024',
			'icon_size'         => $this->slider_size( $settings['icon_size'] ?? array(), 21 ),
			'mobile_icon_size'  => $this->slider_size( $settings['mobile_icon_size'] ?? array(), 20 ),
			'background'        => $settings['background'] ?? '#ffffff',
			'text_color'        => $settings['text_color'] ?? '#15171a',
			'muted_color'       => $settings['muted_color'] ?? '#68707a',
			'accent_color'      => $settings['accent_color'] ?? '#2f6f58',
			'border_color'      => $settings['border_color'] ?? '#e9ecef',
			'icon_color'        => $settings['icon_color'] ?? '#15171a',
			'search_icon_color' => $settings['search_icon_color'] ?? '#15171a',
			'menu_icon_color'   => $settings['menu_icon_color'] ?? '#15171a',
			'close_icon_color'  => $settings['close_icon_color'] ?? '#15171a',
			'icon_hover_color'  => $settings['icon_hover_color'] ?? '#2f6f58',
		);

		echo $this->renderer->render( $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	private function get_menu_options() {
		$options = array( '' => esc_html__( 'انتخاب خودکار / منوی اصلی', 'noghte-smart-headers' ) );
		$menus   = wp_get_nav_menus();

		foreach ( $menus as $menu ) {
			$options[ (string) $menu->term_id ] = $menu->name;
		}

		return $options;
	}

	private function slider_size( $value, $default ) {
		return isset( $value['size'] ) && is_numeric( $value['size'] ) ? $value['size'] : $default;
	}
}
