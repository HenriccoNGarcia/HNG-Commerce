<?php

/**

 * Widget Elementor: Filtros de Busca de Produtos HNG Commerce

 */

if ( ! defined( 'ABSPATH' ) ) exit;



class HNG_Widget_Product_Filters extends \Elementor\Widget_Base {

    public function get_name() {

        return 'hng-product-filters';

    }

    public function get_title() {

        return __( 'Filtros de Produtos (HNG)', 'hng-commerce');

    }

    public function get_icon() {

        return 'eicon-filter';

    }

    public function get_categories() {

        return ['hng-commerce'];

    }

    public function get_keywords() {

        return [ 'filtro', 'produtos', 'busca', 'hng', 'woocommerce' ];

    }



    protected function register_controls() {

        $this->start_controls_section(

            'section_filters',

            [

                'label' => __( 'Filtros Disponíveis', 'hng-commerce'),

                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,

            ]

        );

        $this->add_control('show_search', [

            'label' => __('Campo de busca', 'hng-commerce'),

            'type' => \Elementor\Controls_Manager::SWITCHER,

            'default' => 'yes',

        ]);

        $this->add_control('show_categories', [

            'label' => __('Categorias', 'hng-commerce'),

            'type' => \Elementor\Controls_Manager::SWITCHER,

            'default' => 'yes',

        ]);

        $this->add_control('show_price', [

            'label' => __('Faixa de preço', 'hng-commerce'),

            'type' => \Elementor\Controls_Manager::SWITCHER,

            'default' => 'yes',

        ]);

        $this->add_control('show_orderby', [

            'label' => __('Ordenação', 'hng-commerce'),

            'type' => \Elementor\Controls_Manager::SWITCHER,

            'default' => 'yes',

        ]);

        $this->end_controls_section();



        $this->start_controls_section(

            'section_style',

            [

                'label' => __( 'Estilo', 'hng-commerce'),

                'tab' => \Elementor\Controls_Manager::TAB_STYLE,

            ]

        );

        $this->add_control('background_color', [

            'label' => __('Cor de fundo', 'hng-commerce'),

            'type' => \Elementor\Controls_Manager::COLOR,

            'selectors' => [

                '{{WRAPPER}} .hng-product-filters' => 'background-color: {{VALUE}};',

            ],

        ]);

        $this->add_responsive_control('padding', [

            'label' => __('Padding', 'hng-commerce'),

            'type' => \Elementor\Controls_Manager::DIMENSIONS,

            'selectors' => [

                '{{WRAPPER}} .hng-product-filters' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',

            ],

        ]);

        $this->end_controls_section();

    }



    protected function render() {

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- All GET parameters in this widget are read-only product filters (search, category, price, orderby), no data modification

        $settings = $this->get_settings_for_display();

        ?>

        <form class="hng-product-filters" method="get" action="<?php echo esc_url( get_post_type_archive_link( 'hng_product' ) ); ?>" aria-label="Filtros de busca de produtos">

            <?php if ( $settings['show_search'] === 'yes' ) : ?>

                <div class="hng-filter-search">

                    <label for="hng_search_query" class="screen-reader-text"><?php esc_html_e('Buscar produtos', 'hng-commerce'); ?></label>

                    <input type="search" id="hng_search_query" name="s" placeholder="<?php esc_attr_e('Buscar produtos...', 'hng-commerce'); ?>" value="<?php echo isset($_GET['s']) ? esc_attr(sanitize_text_field(wp_unslash($_GET['s']))) : ''; ?>" />

                </div>

            <?php endif; ?>

            <?php if ( $settings['show_categories'] === 'yes' ) : ?>

                <div class="hng-filter-categories">

                    <label for="hng_filter_cat"><?php esc_html_e('Categoria', 'hng-commerce'); ?></label>

                    <?php

                    $hng_filter_cat = isset($_GET['hng_product_cat']) ? sanitize_text_field(wp_unslash($_GET['hng_product_cat'])) : '';

                    wp_dropdown_categories([

                        'show_option_all' => __('Todas', 'hng-commerce'),

                        'taxonomy' => 'hng_product_cat',

                        'name' => 'hng_product_cat',

                        'id' => 'hng_filter_cat',

                        'selected' => $hng_filter_cat,

                        'hide_empty' => false,

                    ]);

                    ?>

                </div>

            <?php endif; ?>

            <?php if ( $settings['show_price'] === 'yes' ) : ?>

                <div class="hng-filter-price">

                    <label><?php esc_html_e('Preço', 'hng-commerce'); ?></label>

                    <?php
                    $min_price = isset($_GET['min_price']) ? absint(wp_unslash($_GET['min_price'])) : '';
                    $max_price = isset($_GET['max_price']) ? absint(wp_unslash($_GET['max_price'])) : '';
                    ?>

                    <input type="number" name="min_price" placeholder="<?php esc_attr_e('Mínimo', 'hng-commerce'); ?>" value="<?php echo $min_price === '' ? '' : esc_attr($min_price); ?>" min="0" />

                    <input type="number" name="max_price" placeholder="<?php esc_attr_e('Máximo', 'hng-commerce'); ?>" value="<?php echo $max_price === '' ? '' : esc_attr($max_price); ?>" min="0" />

                </div>

            <?php endif; ?>

            <?php if ( $settings['show_orderby'] === 'yes' ) : ?>

                <div class="hng-filter-orderby">

                    <label for="hng_orderby"><?php esc_html_e('Ordenar por', 'hng-commerce'); ?></label>

                    <select name="orderby" id="hng_orderby">

                        <?php $orderby = isset($_GET['orderby']) ? sanitize_text_field(wp_unslash($_GET['orderby'])) : ''; ?>

                        <option value="date" <?php selected($orderby, 'date'); ?>><?php esc_html_e('Mais recentes', 'hng-commerce'); ?></option>

                        <option value="price_asc" <?php selected($orderby, 'price_asc'); ?>><?php esc_html_e('Menor preço', 'hng-commerce'); ?></option>

                        <option value="price_desc" <?php selected($orderby, 'price_desc'); ?>><?php esc_html_e('Maior preço', 'hng-commerce'); ?></option>

                        <option value="title" <?php selected($orderby, 'title'); ?>><?php esc_html_e('A-Z', 'hng-commerce'); ?></option>

                    </select>

                </div>

            <?php endif; ?>

            <button type="submit" class="hng-filter-submit"><?php esc_html_e('Filtrar', 'hng-commerce'); ?></button>

        </form>

        <?php

    }

}



// Register widget

add_action('elementor/widgets/widgets_registered', function($widgets_manager){

    require_once __FILE__;

    $widgets_manager->register_widget_type( new HNG_Widget_Product_Filters() );

});
