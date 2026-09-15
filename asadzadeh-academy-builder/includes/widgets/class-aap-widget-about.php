<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AAP_Widget_About extends \Elementor\Widget_Base {
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
		return 'aap-about';
	}

	public function get_title() {
		return esc_html__( 'درباره آکادمی', 'asadzadeh-smart-pages' );
	}

	public function get_icon() {
		return 'eicon-info-circle';
	}

	public function get_categories() {
		return array( 'asadzadeh-academy' );
	}

	public function get_keywords() {
		return array( 'about', 'academy', 'درباره', 'آکادمی' );
	}

	public function get_style_depends() {
		return array( 'aap-frontend' );
	}

	public function get_script_depends() {
		return array( 'aap-frontend' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'aap_about_content',
			array(
				'label' => esc_html__( 'محتوا', 'asadzadeh-smart-pages' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'note',
			array(
				'type'            => \Elementor\Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'این ویجت محتوای صفحه درباره ما را از قالب داینامیک نمایش می‌دهد. برای سفارشی‌سازی بیشتر از ابزارک‌های المنتور استفاده کنید.', 'asadzadeh-smart-pages' ),
				'content_classes' => 'elementor-descriptor',
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
		$settings['aap_type'] = 'about';

		if ( $this->renderer ) {
			echo $this->renderer->render_about( $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			echo do_shortcode( '[asadzadeh_about]' );
		}
	}
}
