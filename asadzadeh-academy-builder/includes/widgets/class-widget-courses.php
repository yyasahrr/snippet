<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AAP_Widget_Courses extends \Elementor\Widget_Base {

    public function get_name() {
        return 'aap-courses';
    }

    public function get_title() {
        return esc_html__( 'آرشیو دوره‌ها', 'asadzadeh-academy-builder' );
    }

    public function get_icon() {
        return 'eicon-archive';
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
            'courses_count',
            array(
                'label'   => esc_html__( 'تعداد دوره‌ها', 'asadzadeh-academy-builder' ),
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
                'label'   => esc_html__( 'دسته‌بندی', 'asadzadeh-academy-builder' ),
                'type'    => \Elementor\Controls_Manager::TEXT,
                'default' => '',
            )
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $courses = absint( $settings['courses_count'] ) ?: 9;
        $category = sanitize_text_field( $settings['category'] );
        echo do_shortcode( '[asadzadeh_courses courses="' . $courses . '" category="' . esc_attr( $category ) . '"]' );
    }
}
