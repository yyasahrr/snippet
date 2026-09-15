<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AAP_Widget_Homepage extends \Elementor\Widget_Base {

    public function get_name() {
        return 'aap-homepage';
    }

    public function get_title() {
        return esc_html__( 'صفحه اصلی آکادمی', 'asadzadeh-academy-builder' );
    }

    public function get_icon() {
        return 'eicon-home';
    }

    public function get_categories() {
        return array( 'asadzadeh-academy' );
    }

    public function get_keywords() {
        return array( 'asadzadeh', 'academy', 'home', 'homepage', 'tutor', 'course' );
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
                'max'     => 12,
                'step'    => 1,
                'default' => 6,
            )
        );

        $this->add_control(
            'category',
            array(
                'label'       => esc_html__( 'دسته‌بندی (slug یا ID)', 'asadzadeh-academy-builder' ),
                'type'        => \Elementor\Controls_Manager::TEXT,
                'placeholder' => esc_html__( 'مثلا: قالیبافی یا خالی برای همه', 'asadzadeh-academy-builder' ),
                'default'     => '',
            )
        );

        $this->add_control(
            'show_continue',
            array(
                'label'        => esc_html__( 'نمایش ادامه یادگیری', 'asadzadeh-academy-builder' ),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => esc_html__( 'نمایش', 'asadzadeh-academy-builder' ),
                'label_off'    => esc_html__( 'مخفی', 'asadzadeh-academy-builder' ),
                'return_value' => 'yes',
                'default'      => 'yes',
            )
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $courses = absint( $settings['courses_count'] ) ?: 6;
        $category = sanitize_text_field( $settings['category'] );

        echo do_shortcode( '[asadzadeh_home courses="' . $courses . '" category="' . esc_attr( $category ) . '"]' );
    }
}
