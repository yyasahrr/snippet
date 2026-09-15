<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AAP_Widget_Courses extends \Elementor\Widget_Base {
	/** @var AAP_Renderer */
	private $renderer;

	public function __construct( $data = array(), $args = null ) {
		if ( $data instanceof AAP_Renderer ) {
			$this->renderer = $data;
			$data = array();
		} elseif ( is_array( $args ) && isset( $args['renderer'] ) ) {
			$this->renderer = $args['renderer'];
		}
		parent::__construct( $data, $args );
	}

	public function get_name() {
		return 'aap-courses';
	}

	public function get_title() {
		return esc_html__( 'آرشیو دوره‌ها', 'asadzadeh-smart-pages' );
	}

	public function get_icon() {
		return 'eicon-products';
	}

	public function get_categories() {
		return array( 'asadzadeh-academy' );
	}

	public function get_keywords() {
		return array( 'courses', 'archive', 'tutor', 'دوره‌ها', 'آرشیو' );
	}

	public function get_style_depends() {
		return array( 'aap-frontend' );
	}

	public function get_script_depends() {
		return array( 'aap-frontend' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'aap_courses_content',
			array(
				'label' => esc_html__( 'محتوا', 'asadzadeh-smart-pages' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'courses',
			array(
				'label'   => esc_html__( 'تعداد دوره‌ها', 'asadzadeh-smart-pages' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => 24,
				'step'    => 1,
				'default' => 9,
			)
		);

		$this->add_control(
			'category',
			array(
				'label'       => esc_html__( 'فیلتر دسته (اسلاگ)', 'asadzadeh-smart-pages' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => 'ghali-bafi',
			)
		);

		$this->add_control(
			'search',
			array(
				'label'       => esc_html__( 'عبارت جستجو پیش‌فرض', 'asadzadeh-smart-pages' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => esc_html__( 'جستجو...', 'asadzadeh-smart-pages' ),
			)
		);

		$this->add_control(
			'custom_class',
			array(
				'label' => esc_html__( 'کلاس سفارشی', 'asadzadeh-smart-pages' ),
				'type'  => \Elementor\Controls_Manager::TEXT,
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$settings['aap_type'] = 'courses';

		if ( $this->renderer ) {
			echo $this->renderer->render_courses( $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			echo do_shortcode( '[asadzadeh_courses courses="' . esc_attr( $settings['courses'] ) . '" category="' . esc_attr( $settings['category'] ) . '"]' );
		}
	}
}
