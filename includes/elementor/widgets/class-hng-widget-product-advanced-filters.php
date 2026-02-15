<?php

/**

 * Widget Elementor: Filtros Avançados de Produtos HNG Commerce

 */

if ( ! defined( 'ABSPATH' ) ) exit;



class HNG_Widget_Product_Advanced_Filters extends \Elementor\Widget_Base {

    public function get_name() {

        return 'hng-product-advanced-filters';

    }

    public function get_title() {

        return __( 'Filtros Avançados de Produtos (HNG)', 'hng-commerce');

    }

    public function get_icon() {

        return 'eicon-filter';

    }

    public function get_categories() {

        return ['hng-commerce'];

    }

    public function get_keywords() {

        return [ 'filtro', 'produtos', 'avançado', 'atributo', 'hng', 'woocommerce' ];

    }



    protected function register_controls() {

        $this->start_controls_section(

            'section_filters',

            [

                'label' => __( 'Filtros Avançados', 'hng-commerce'),

                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,

            ]

        );

        $this->add_control('show_attributes', [

            'label' => __('Atributos do produto', 'hng-commerce'),

            'type' => \Elementor\Controls_Manager::SWITCHER,

            'default' => 'yes',

        ]);

        $this->add_control('show_tags', [

            'label' => __('Tags', 'hng-commerce'),

            'type' => \Elementor\Controls_Manager::SWITCHER,

            'default' => 'yes',

        ]);

        $this->add_control('show_custom_fields', [

            'label' => __('Campos personalizados', 'hng-commerce'),

            'type' => \Elementor\Controls_Manager::SWITCHER,

            'default' => 'no',

        ]);

        $this->add_control('custom_fields_list', [

            'label' => __('Lista de campos personalizados (slug separados por vírgula)', 'hng-commerce'),

            'type' => \Elementor\Controls_Manager::TEXT,

            'default' => '',

            'condition' => [ 'show_custom_fields' => 'yes' ]

        ]);

        $this->end_controls_section();

    }



    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- All GET parameters in this widget are read-only product filter attributes (taxonomies, tags), no data modification

    protected function render() {

        $settings = $this->get_settings_for_display();

        

        echo '<form class="hng-product-advanced-filters" method="get" action="' . esc_url( get_post_type_archive_link( 'hng_product' ) ) . '" aria-label="Filtros avançados de produtos">';

        

        if ( $settings['show_attributes'] === 'yes' ) {

            echo '<div class="hng-filter-attributes">';

            echo '<label>' . esc_html_e('Atributos', 'hng-commerce') . '</label>';

            

            $taxonomies = get_object_taxonomies('hng_product', 'objects');

            foreach ($taxonomies as $tax) {

                if (strpos((string) $tax->name, 'pa_') === 0) {

                    $terms = get_terms([ 'taxonomy' => $tax->name, 'hide_empty' => false ]);

                    if ($terms && !is_wp_error($terms)) {

                        echo '<div class="hng-filter-attribute-group">';

                        echo '<label>' . esc_html($tax->label) . '</label>';

                        echo '<select name="' . esc_attr($tax->name) . '">';

                        echo '<option value="">' . esc_html__('Todos', 'hng-commerce') . '</option>';

                        $selected_tax = isset($_GET[$tax->name]) ? sanitize_text_field(wp_unslash($_GET[$tax->name])) : '';

                        foreach ($terms as $term) {

                            $selected = ($selected_tax === $term->slug) ? 'selected' : '';

                            echo '<option value="' . esc_attr($term->slug) . '" ' . esc_attr($selected) . '>' . esc_html($term->name) . '</option>';

                        }

                        echo '</select>';

                        echo '</div>';

                    }

                }

            }

            

            echo '</div>';

        }

        

        if ( $settings['show_tags'] === 'yes' ) {

            echo '<div class="hng-filter-tags">';

            echo '<label for="hng_filter_tag">' . esc_html_e('Tags', 'hng-commerce') . '</label>';

            

            $tags = get_terms([ 'taxonomy' => 'hng_product_tag', 'hide_empty' => false ]);

            if ($tags && !is_wp_error($tags)) {

                echo '<select name="hng_product_tag" id="hng_filter_tag">';

                echo '<option value="">' . esc_html__('Todas', 'hng-commerce') . '</option>';

                $selected_tag = isset($_GET['hng_product_tag']) ? sanitize_text_field(wp_unslash($_GET['hng_product_tag'])) : '';

                foreach ($tags as $tag) {

                    $selected = ($selected_tag === $tag->slug) ? 'selected' : '';

                    echo '<option value="' . esc_attr($tag->slug) . '" ' . esc_attr($selected) . '>' . esc_html($tag->name) . '</option>';

                }

                echo '</select>';

            }

            

            echo '</div>';

        }

        

        if ( $settings['show_custom_fields'] === 'yes' && !empty($settings['custom_fields_list']) ) {

            echo '<div class="hng-filter-custom-fields">';

            echo '<label>' . esc_html_e('Campos personalizados', 'hng-commerce') . '</label>';

            

            $fields = array_map('trim', explode(',', $settings['custom_fields_list']));

            foreach ($fields as $field) {

                if ($field) {

                    $val = isset($_GET[$field]) ? esc_attr($_GET[$field]) : '';

                    echo '<input type="text" name="' . esc_attr($field) . '" placeholder="' . esc_attr($field) . '" value="' . esc_attr($val) . '" />';

                }

            }

            

            echo '</div>';

        }

        

        echo '<button type="submit" class="hng-filter-submit">' . esc_html_e('Filtrar', 'hng-commerce') . '</button>';

        echo '</form>';

    }

}



add_action('elementor/widgets/widgets_registered', function($widgets_manager){

    require_once __FILE__;

    $widgets_manager->register_widget_type( new HNG_Widget_Product_Advanced_Filters() );

});

