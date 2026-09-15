<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AAP_Widget_Contact extends \Elementor\Widget_Base {

    public function get_name() {
        return 'aap-contact';
    }

    public function get_title() {
        return esc_html__( 'تماس با آکادمی', 'asadzadeh-academy-builder' );
    }

    public function get_icon() {
        return 'eicon-mail';
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
                'raw'             => esc_html__( 'فرم تماس، اطلاعات و نقشه آکادمی را نمایش می‌دهد.', 'asadzadeh-academy-builder' ),
                'content_classes' => 'elementor-descriptor',
            )
        );

        $this->end_controls_section();
    }

    protected function render() {
        echo do_shortcode( '[asadzadeh_contact]' );
    }
}
