<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AAP_Widget_About extends \Elementor\Widget_Base {

    public function get_name() {
        return 'aap-about';
    }

    public function get_title() {
        return esc_html__( 'درباره آکادمی', 'asadzadeh-academy-builder' );
    }

    public function get_icon() {
        return 'eicon-info-circle';
    }

    public function get_categories() {
        return array( 'asadzadeh-academy' );
    }

    protected function register_controls() {
        $this->start_controls_section(
            'content_section',
            array(
                'label' => esc_html__( 'تنظیمات', 'asadzadeh-academy-builder' ),
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'note',
            array(
                'type'            => \Elementor\Controls_Manager::RAW_HTML,
                'raw'             => esc_html__( 'این ویجت صفحه درباره ما را با طراحی اختصاصی آکادمی نمایش می‌دهد. محتوای پویا از سایت خوانده می‌شود.', 'asadzadeh-academy-builder' ),
                'content_classes' => 'elementor-descriptor',
            )
        );

        $this->end_controls_section();
    }

    protected function render() {
        echo do_shortcode( '[asadzadeh_about]' );
    }
}
