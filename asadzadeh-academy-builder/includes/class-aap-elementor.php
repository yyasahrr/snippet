<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AAP_Elementor {
	private static $instance = null;

	/** @var AAP_Renderer */
	private $renderer;

	public static function instance( $renderer ) {
		if ( null === self::$instance ) {
			self::$instance = new self( $renderer );
		}
		return self::$instance;
	}

	private function __construct( $renderer ) {
		$this->renderer = $renderer;
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
	}

	public function register_category( $elements_manager ) {
		$elements_manager->add_category(
			'asadzadeh-academy',
			array(
				'title' => esc_html__( 'آکادمی اسدزاده', 'asadzadeh-smart-pages' ),
				'icon'  => 'eicon-academy',
			)
		);
	}

	public function register_widgets( $widgets_manager ) {
		require_once AAP_PATH . 'includes/widgets/class-aap-widget-home.php';
		require_once AAP_PATH . 'includes/widgets/class-aap-widget-courses.php';
		require_once AAP_PATH . 'includes/widgets/class-aap-widget-about.php';
		require_once AAP_PATH . 'includes/widgets/class-aap-widget-contact.php';

		$widgets_manager->register( new AAP_Widget_Home( array(), array( 'renderer' => $this->renderer ) ) );
		$widgets_manager->register( new AAP_Widget_Courses( array(), array( 'renderer' => $this->renderer ) ) );
		$widgets_manager->register( new AAP_Widget_About( array(), array( 'renderer' => $this->renderer ) ) );
		$widgets_manager->register( new AAP_Widget_Contact( array(), array( 'renderer' => $this->renderer ) ) );
	}
}
