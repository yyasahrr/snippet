<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AAP_Widget_Home extends \Elementor\Widget_Base {
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
		return 'aap-home';
	}

	public function get_title() {
		return esc_html__( 'صفحه اصلی آکادمی', 'asadzadeh-smart-pages' );
	}

	public function get_icon() {
		return 'eicon-home';
	}

	public function get_categories() {
		return array( 'asadzadeh-academy' );
	}

	public function get_keywords() {
		return array( 'home', 'academy', 'asadzadeh', 'صفحه اصلی', 'آکادمی' );
	}

	public function get_style_depends() {
		return array( 'aap-frontend' );
	}

	public function get_script_depends() {
		return array( 'aap-frontend' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'aap_home_content',
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
				'max'     => 12,
				'step'    => 1,
				'default' => 6,
			)
		);

		$this->add_control(
			'category',
			array(
				'label'       => esc_html__( 'دسته دوره (اسلاگ یا ID)', 'asadzadeh-smart-pages' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => esc_html__( 'مثال: ghali-bafi', 'asadzadeh-smart-pages' ),
				'default'     => '',
			)
		);

		$this->add_control(
			'custom_class',
			array(
				'label'       => esc_html__( 'کلاس سفارشی', 'asadzadeh-smart-pages' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => 'my-custom-class',
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$settings['aap_type'] = 'home';

		if ( $this->renderer ) {
			echo $this->renderer->render_home( $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			// Fallback to shortcode if renderer not injected
			echo do_shortcode( '[asadzadeh_home courses="' . esc_attr( $settings['courses'] ) . '" category="' . esc_attr( $settings['category'] ) . '"]' );
		}
	}
}
