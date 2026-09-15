<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NGT_HDR_Elementor {
	private static $instance = null;

	/** @var NGT_HDR_Renderer */
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
		add_action( 'elementor/widgets/register', array( $this, 'register_widget' ) );
	}

	public function register_category( $elements_manager ) {
		$elements_manager->add_category(
			'noghte-group',
			array(
				'title' => esc_html__( 'گروه نقطه', 'noghte-smart-headers' ),
				'icon'  => 'eicon-header',
			)
		);
	}

	public function register_widget( $widgets_manager ) {
		require_once NGT_HDR_PATH . 'includes/widgets/class-ngt-hdr-elementor-widget.php';
		$widgets_manager->register( new NGT_HDR_Elementor_Widget( $this->renderer ) );
	}
}
