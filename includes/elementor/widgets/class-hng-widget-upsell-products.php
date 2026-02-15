<?php
if (!defined('ABSPATH')) { exit; }

class HNG_Widget_Upsell_Products extends HNG_Commerce_Elementor_Widget_Base {
    public function get_name() { return 'hng_upsell_products'; }
    public function get_title() { return __('Produtos Upsell', 'hng-commerce'); }
    public function get_icon() { return 'eicon-products'; }

    protected function register_controls() {
                // Títulos, descrições e placeholders editáveis
                $this->start_controls_section(
                    'labels_section',
                    [
                        'label' => __('Textos e Placeholders', 'hng-commerce'),
                        'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
                    ]
                );
                $this->add_control('label_upsell_title', [ 'label' => __('Título Upsell', 'hng-commerce'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Produtos Recomendados', 'hng-commerce') ]);
                $this->add_control('label_empty', [ 'label' => __('Mensagem Sem Produtos', 'hng-commerce'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Nenhum produto recomendado.', 'hng-commerce') ]);
                $this->end_controls_section();

                // Variação de layout (grid/lista/carrossel)
                $this->start_controls_section(
                    'layout_section',
                    [
                        'label' => __('Layout', 'hng-commerce'),
                        'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
                    ]
                );
                $this->add_control('upsell_layout', [
                    'label' => __('Layout dos Produtos', 'hng-commerce'),
                    'type' => \Elementor\Controls_Manager::SELECT,
                    'options' => [
                        'grid' => __('Grid', 'hng-commerce'),
                        'list' => __('Lista', 'hng-commerce'),
                        'carousel' => __('Carrossel', 'hng-commerce'),
                    ],
                    'default' => 'grid',
                ]);
                $this->end_controls_section();
        // Content Section
        $this->start_controls_section(
            'content_section',
            [
                'label' => __('Configurações', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'columns',
            [
                'label' => __('Colunas', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 4,
                'min' => 1,
                'max' => 6,
            ]
        );

        $this->add_control(
            'products_count',
            [
                'label' => __('Número de Produtos', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 4,
                'min' => 1,
                'max' => 12,
            ]
        );

        $this->add_control(
            'show_image',
            [
                'label' => __('Mostrar Imagem', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_title',
            [
                'label' => __('Mostrar Título', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_price',
            [
                'label' => __('Mostrar Preço', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_button',
            [
                'label' => __('Mostrar Botão', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->end_controls_section();

        // Container Style
        $this->start_controls_section(
            'container_style_section',
            [
                'label' => __('Container', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'container_gap',
            [
                'label' => __('Espaçamento', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 20,
                ],
                'selectors' => [
                    '{{WRAPPER}} .hng-upsell-grid' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Card Style
        $this->start_controls_section(
            'card_style_section',
            [
                'label' => __('Card', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'card_background',
            [
                'label' => __('Cor de Fundo', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-upsell-card' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'card_border',
                'selector' => '{{WRAPPER}} .hng-upsell-card',
            ]
        );

        $this->add_responsive_control(
            'card_border_radius',
            [
                'label' => __('Border Radius', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .hng-upsell-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'card_shadow',
                'selector' => '{{WRAPPER}} .hng-upsell-card',
            ]
        );

        $this->add_responsive_control(
            'card_padding',
            [
                'label' => __('Padding', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .hng-upsell-card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Title, Price, Button styles (similar to Product Grid)
        $this->start_controls_section(
            'title_style_section',
            [
                'label' => __('Título', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'title_color',
            [
                'label' => __('Cor', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-upsell-title' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'selector' => '{{WRAPPER}} .hng-upsell-title',
            ]
        );

        $this->end_controls_section();

        // Price Style
        $this->start_controls_section(
            'price_style_section',
            [
                'label' => __('Preço', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'price_color',
            [
                'label' => __('Cor', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-upsell-price' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'price_typography',
                'selector' => '{{WRAPPER}} .hng-upsell-price',
            ]
        );

        $this->end_controls_section();

        // Button Style
        $this->start_controls_section(
            'button_style_section',
            [
                'label' => __('Botão', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->start_controls_tabs('button_tabs');

        $this->start_controls_tab('button_normal', ['label' => __('Normal', 'hng-commerce')]);

        $this->add_control(
            'button_color',
            [
                'label' => __('Cor', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-add-to-cart-button' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_background',
            [
                'label' => __('Fundo', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-add-to-cart-button' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab('button_hover', ['label' => __('Hover', 'hng-commerce')]);

        $this->add_control(
            'button_hover_color',
            [
                'label' => __('Cor', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-add-to-cart-button:hover' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_hover_background',
            [
                'label' => __('Fundo', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-add-to-cart-button:hover' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();
        $this->end_controls_tabs();

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'button_typography',
                'selector' => '{{WRAPPER}} .hng-add-to-cart-button',
                'separator' => 'before',
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $columns = intval($settings['columns']);
        $count = intval($settings['products_count']);
        
        // Buscar produtos aleatórios ou relacionados
        $args = [
            'post_type' => 'hng_product',
            'posts_per_page' => $count,
            'orderby' => 'rand',
            'post_status' => 'publish',
        ];

        $query = new WP_Query($args);

        if (!$query->have_posts()) {
            echo '<div class="hng-upsell-empty">';
            echo '<p>' . esc_html__('Nenhum produto disponível.', 'hng-commerce') . '</p>';
            echo '</div>';
            return;
        }

        ?>
        <div class="hng-upsell-grid" data-columns="<?php echo esc_attr($columns); ?>">
            <?php while ($query->have_posts()) : $query->the_post();
                $product = new HNG_Product(get_the_ID());
            ?>
                <div class="hng-upsell-card">
                    <?php if ($settings['show_image'] === 'yes') : ?>
                        <div class="hng-upsell-image">
                            <?php
                            if (has_post_thumbnail()) {
                                the_post_thumbnail('medium');
                            } else {
                                echo '<img src="' . esc_url(HNG_COMMERCE_URL . 'assets/images/placeholder.svg') . '" alt="' . esc_attr($product->get_name()) . '" />';
                            }
                            ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($settings['show_title'] === 'yes') : ?>
                        <h3 class="hng-upsell-title">
                            <a href="<?php the_permalink(); ?>"><?php echo esc_html($product->get_name()); ?></a>
                        </h3>
                    <?php endif; ?>

                    <?php if ($settings['show_price'] === 'yes') : ?>
                        <div class="hng-upsell-price">
                            <?php echo esc_html(hng_price($product->get_price())); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($settings['show_button'] === 'yes') : ?>
                        <button class="hng-add-to-cart-button" data-product-id="<?php echo esc_attr(get_the_ID()); ?>">
                            <?php esc_html_e('Adicionar', 'hng-commerce'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            <?php endwhile; wp_reset_postdata(); ?>
        </div>

        <style>
            {{WRAPPER}} .hng-upsell-grid {
                display: grid;
                grid-template-columns: repeat(<?php echo esc_attr($columns); ?>, 1fr);
            }
            {{WRAPPER}} .hng-upsell-card {
                transition: all 0.3s ease;
            }
            {{WRAPPER}} .hng-upsell-image {
                width: 100%;
                overflow: hidden;
                margin-bottom: 15px;
            }
            {{WRAPPER}} .hng-upsell-image img {
                width: 100%;
                height: 250px;
                object-fit: cover;
            }
            {{WRAPPER}} .hng-upsell-title a {
                text-decoration: none;
                color: inherit;
            }
            {{WRAPPER}} .hng-add-to-cart-button {
                width: 100%;
                padding: 10px 20px;
                cursor: pointer;
                border: none;
                transition: all 0.3s ease;
            }
            @media (max-width: 768px) {
                {{WRAPPER}} .hng-upsell-grid {
                    grid-template-columns: repeat(2, 1fr);
                }
            }
        </style>

        <script>
        jQuery(document).ready(function($) {
            $('.hng-add-to-cart-button').on('click', function() {
                var $button = $(this);
                var productId = $button.data('product-id');
                
                $button.prop('disabled', true).text('<?php esc_html_e('Adicionando...', 'hng-commerce'); ?>');
                
                $.ajax({
                    url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                    type: 'POST',
                    data: {
                        action: 'hng_add_to_cart',
                        product_id: productId,
                        quantity: 1,
                        nonce: '<?php echo esc_attr(wp_create_nonce('hng-add-to-cart')); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $button.text('<?php esc_html_e('Adicionado!', 'hng-commerce'); ?>');
                            setTimeout(function() {
                                $button.prop('disabled', false).text('<?php esc_html_e('Adicionar', 'hng-commerce'); ?>');
                            }, 2000);
                        }
                    }
                });
            });
        });
        </script>
        <?php
    }
}
