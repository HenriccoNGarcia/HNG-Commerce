<?php
if (!defined('ABSPATH')) { exit; }

class HNG_Widget_Product_Grid extends HNG_Commerce_Elementor_Widget_Base {
    
    public function get_name() { 
        return 'hng_product_grid'; 
    }
    
    public function get_title() { 
        return __('Grade de Produtos', 'hng-commerce'); 
    }
    
    public function get_icon() { 
        return 'eicon-posts-grid'; 
    }
    
    public function get_categories() {
        return ['hng-commerce'];
    }

    protected function register_controls() {
                // Títulos, descrições e placeholders editáveis
                $this->start_controls_section(
                    'labels_section',
                    [
                        'label' => __('Textos e Placeholders', 'hng-commerce'),
                        'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
                    ]
                );
                $this->add_control('label_grid_title', [ 'label' => __('Título da Grade', 'hng-commerce'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Produtos em Destaque', 'hng-commerce') ]);
                $this->add_control('label_empty', [ 'label' => __('Mensagem Sem Produtos', 'hng-commerce'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Nenhum produto encontrado.', 'hng-commerce') ]);
                $this->add_control('placeholder_search', [ 'label' => __('Placeholder Busca', 'hng-commerce'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Buscar produtos...', 'hng-commerce') ]);
                $this->end_controls_section();

                // Variação de layout (grid/lista/carrossel)
                $this->start_controls_section(
                    'layout_section',
                    [
                        'label' => __('Layout', 'hng-commerce'),
                        'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
                    ]
                );
                $this->add_control('product_grid_layout', [
                    'label' => __('Layout da Grade', 'hng-commerce'),
                    'type' => \Elementor\Controls_Manager::SELECT,
                    'options' => [
                        'grid' => __('Grid', 'hng-commerce'),
                        'list' => __('Lista', 'hng-commerce'),
                        'carousel' => __('Carrossel', 'hng-commerce'),
                    ],
                    'default' => 'grid',
                ]);
                $this->end_controls_section();
        
        // ====================
        // CONTEÚDO
        // ====================
        $this->start_controls_section(
            'section_content',
            [
                'label' => __('Configuração', 'hng-commerce'),
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
            'products_per_page',
            [
                'label' => __('Produtos por página', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 8,
                'min' => 1,
                'max' => 50,
            ]
        );
        
        $this->add_control(
            'orderby',
            [
                'label' => __('Ordenar por', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'date',
                'options' => [
                    'date' => __('Data', 'hng-commerce'),
                    'title' => __('Título', 'hng-commerce'),
                    'price' => __('Preço', 'hng-commerce'),
                    'rand' => __('Aleatório', 'hng-commerce'),
                ],
            ]
        );
        
        $this->add_control(
            'order',
            [
                'label' => __('Ordem', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'DESC',
                'options' => [
                    'ASC' => __('Crescente', 'hng-commerce'),
                    'DESC' => __('Decrescente', 'hng-commerce'),
                ],
            ]
        );
        
        // Filtro por tipo de produto
        $this->add_control(
            'product_type_filter',
            [
                'label' => __('Filtrar por Tipo', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    '' => __('Todos os Tipos', 'hng-commerce'),
                    'physical' => __('Produtos Físicos', 'hng-commerce'),
                    'digital' => __('Produtos Digitais', 'hng-commerce'),
                    'subscription' => __('Assinaturas', 'hng-commerce'),
                    'appointment' => __('Agendamentos', 'hng-commerce'),
                    'quote' => __('Orçamentos', 'hng-commerce'),
                ],
                'default' => '',
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
            'show_product_type_badge',
            [
                'label' => __('Mostrar Badge de Tipo', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'no',
                'description' => __('Exibe um badge indicando o tipo do produto', 'hng-commerce'),
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
        
        $this->add_control(
            'button_text',
            [
                'label' => __('Texto do Botão', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Comprar', 'hng-commerce'),
                'condition' => ['show_button' => 'yes'],
            ]
        );
        
        $this->add_control(
            'dynamic_button_text',
            [
                'label' => __('Texto Dinâmico por Tipo', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'description' => __('Adapta o texto do botão ao tipo de produto (Orçamento, Agendar, etc)', 'hng-commerce'),
                'condition' => ['show_button' => 'yes'],
            ]
        );
        
        $this->end_controls_section();
        
        // ====================
        // ESTILOS - CONTAINER
        // ====================
        $this->start_controls_section(
            'section_container_style',
            [
                'label' => __('Container', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );
        
        $this->add_responsive_control(
            'column_gap',
            [
                'label' => __('Espaçamento entre Colunas', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', '%'],
                'range' => [
                    'px' => ['min' => 0, 'max' => 100],
                    '%' => ['min' => 0, 'max' => 10],
                ],
                'default' => ['size' => 20, 'unit' => 'px'],
                'selectors' => [
                    '{{WRAPPER}} .hng-product-grid' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // ====================
        // ESTILOS - CARD
        // ====================
        $this->start_controls_section(
            'section_card_style',
            [
                'label' => __('Card do Produto', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );
        
        $this->add_control(
            'card_background',
            [
                'label' => __('Background', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .hng-product-card' => 'background-color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'card_border',
                'selector' => '{{WRAPPER}} .hng-product-card',
            ]
        );
        
        $this->add_responsive_control(
            'card_border_radius',
            [
                'label' => __('Raio da Borda', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .hng-product-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'card_box_shadow',
                'selector' => '{{WRAPPER}} .hng-product-card',
            ]
        );
        
        $this->add_responsive_control(
            'card_padding',
            [
                'label' => __('Padding', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'default' => ['top' => 15, 'right' => 15, 'bottom' => 15, 'left' => 15, 'unit' => 'px'],
                'selectors' => [
                    '{{WRAPPER}} .hng-product-card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        // Hover
        $this->add_control(
            'card_hover_heading',
            [
                'label' => __('Hover', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );
        
        $this->add_control(
            'card_hover_background',
            [
                'label' => __('Background (Hover)', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-product-card:hover' => 'background-color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'card_hover_box_shadow',
                'label' => __('Sombra (Hover)', 'hng-commerce'),
                'selector' => '{{WRAPPER}} .hng-product-card:hover',
            ]
        );
        
        $this->add_control(
            'card_hover_transition',
            [
                'label' => __('Transição (ms)', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 300,
                'selectors' => [
                    '{{WRAPPER}} .hng-product-card' => 'transition: all {{VALUE}}ms ease;',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // ====================
        // ESTILOS - IMAGEM
        // ====================
        $this->start_controls_section(
            'section_image_style',
            [
                'label' => __('Imagem', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => ['show_image' => 'yes'],
            ]
        );
        
        $this->add_responsive_control(
            'image_height',
            [
                'label' => __('Altura', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', '%'],
                'range' => [
                    'px' => ['min' => 100, 'max' => 600],
                    '%' => ['min' => 50, 'max' => 100],
                ],
                'default' => ['size' => 250, 'unit' => 'px'],
                'selectors' => [
                    '{{WRAPPER}} .hng-product-image img' => 'height: {{SIZE}}{{UNIT}}; object-fit: cover;',
                ],
            ]
        );
        
        $this->add_responsive_control(
            'image_border_radius',
            [
                'label' => __('Raio da Borda', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .hng-product-image img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // ====================
        // ESTILOS - TÍTULO
        // ====================
        $this->start_controls_section(
            'section_title_style',
            [
                'label' => __('Título', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => ['show_title' => 'yes'],
            ]
        );
        
        $this->add_control(
            'title_color',
            [
                'label' => __('Cor', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#333333',
                'selectors' => [
                    '{{WRAPPER}} .hng-product-title' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'selector' => '{{WRAPPER}} .hng-product-title',
            ]
        );
        
        $this->add_responsive_control(
            'title_margin',
            [
                'label' => __('Margem', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'default' => ['top' => 10, 'right' => 0, 'bottom' => 5, 'left' => 0, 'unit' => 'px'],
                'selectors' => [
                    '{{WRAPPER}} .hng-product-title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_control(
            'title_hover_color',
            [
                'label' => __('Cor (Hover)', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-product-title:hover' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // ====================
        // ESTILOS - PREÇO
        // ====================
        $this->start_controls_section(
            'section_price_style',
            [
                'label' => __('Preço', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => ['show_price' => 'yes'],
            ]
        );
        
        $this->add_control(
            'price_color',
            [
                'label' => __('Cor', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#27ae60',
                'selectors' => [
                    '{{WRAPPER}} .hng-product-price' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'price_typography',
                'selector' => '{{WRAPPER}} .hng-product-price',
            ]
        );
        
        $this->add_responsive_control(
            'price_margin',
            [
                'label' => __('Margem', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'default' => ['top' => 5, 'right' => 0, 'bottom' => 10, 'left' => 0, 'unit' => 'px'],
                'selectors' => [
                    '{{WRAPPER}} .hng-product-price' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // ====================
        // ESTILOS - BOTÃO
        // ====================
        $this->start_controls_section(
            'section_button_style',
            [
                'label' => __('Botão', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => ['show_button' => 'yes'],
            ]
        );
        
        $this->add_control(
            'button_color',
            [
                'label' => __('Cor do Texto', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .hng-product-button' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'button_background',
            [
                'label' => __('Background', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#3498db',
                'selectors' => [
                    '{{WRAPPER}} .hng-product-button' => 'background-color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'button_typography',
                'selector' => '{{WRAPPER}} .hng-product-button',
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'button_border',
                'selector' => '{{WRAPPER}} .hng-product-button',
            ]
        );
        
        $this->add_responsive_control(
            'button_border_radius',
            [
                'label' => __('Raio da Borda', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'default' => ['top' => 4, 'right' => 4, 'bottom' => 4, 'left' => 4, 'unit' => 'px'],
                'selectors' => [
                    '{{WRAPPER}} .hng-product-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_responsive_control(
            'button_padding',
            [
                'label' => __('Padding', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'default' => ['top' => 10, 'right' => 20, 'bottom' => 10, 'left' => 20, 'unit' => 'px'],
                'selectors' => [
                    '{{WRAPPER}} .hng-product-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        // Hover
        $this->add_control(
            'button_hover_heading',
            [
                'label' => __('Hover', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );
        
        $this->add_control(
            'button_hover_color',
            [
                'label' => __('Cor do Texto (Hover)', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-product-button:hover' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'button_hover_background',
            [
                'label' => __('Background (Hover)', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#2980b9',
                'selectors' => [
                    '{{WRAPPER}} .hng-product-button:hover' => 'background-color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'button_hover_transition',
            [
                'label' => __('Transição (ms)', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 300,
                'selectors' => [
                    '{{WRAPPER}} .hng-product-button' => 'transition: all {{VALUE}}ms ease;',
                ],
            ]
        );
        
        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $layout = $settings['product_grid_layout'] ?? 'grid';
        $is_edit_mode = $this->is_edit_mode();
        $product_type_filter = isset($settings['product_type_filter']) ? $settings['product_type_filter'] : '';
        
        // Buscar produtos
        $args = [
            'post_type' => 'hng_product',
            'posts_per_page' => $settings['products_per_page'],
            'orderby' => $settings['orderby'],
            'order' => $settings['order'],
            'post_status' => 'publish',
        ];
        
        // Filtrar por tipo de produto
        if (!empty($product_type_filter)) {
            $args['meta_query'] = [
                'relation' => 'OR',
                [
                    'key' => '_hng_product_type',
                    'value' => $product_type_filter,
                    'compare' => '='
                ],
                [
                    'key' => '_product_type',
                    'value' => $product_type_filter,
                    'compare' => '='
                ],
            ];
        }
        
        $products = new WP_Query($args);
        
        if (!$products->have_posts()) {
            if ($is_edit_mode) {
                $type_label = !empty($product_type_filter) ? ' do tipo "' . esc_html($product_type_filter) . '"' : '';
                /* translators: %s: product type label (already escaped) */
                echo '<div class="elementor-alert elementor-alert-info">' . sprintf(esc_html__('Nenhum produto%s encontrado. Crie alguns produtos para visualizá-los aqui.', 'hng-commerce'), esc_html($type_label)) . '</div>';
            } else {
                echo '<p>' . esc_html__('Nenhum produto encontrado.', 'hng-commerce') . '</p>';
            }
            return;
        }
        
        $columns = absint($settings['columns']);
        $grid_class = 'hng-product-grid-' . $layout;
        $show_type_badge = isset($settings['show_product_type_badge']) && $settings['show_product_type_badge'] === 'yes';
        $dynamic_button = isset($settings['dynamic_button_text']) && $settings['dynamic_button_text'] === 'yes';
        ?>
        
        <div class="hng-product-grid <?php echo esc_attr($grid_class); ?>" data-layout="<?php echo esc_attr($layout); ?>" data-columns="<?php echo esc_attr($columns); ?>">
            <?php while ($products->have_posts()) : $products->the_post(); 
                $product = new HNG_Product(get_the_ID());
                $thumbnail = get_the_post_thumbnail_url(get_the_ID(), 'medium');
                if (!$thumbnail) {
                    $thumbnail = HNG_COMMERCE_URL . 'assets/images/placeholder.svg';
                }
                
                // Obter tipo do produto
                $product_type = method_exists($product, 'get_product_type') ? $product->get_product_type() : 'physical';
                
                // Definir texto do botão baseado no tipo
                $button_text = $settings['button_text'];
                if ($dynamic_button) {
                    switch ($product_type) {
                        case 'quote':
                            $button_text = __('Solicitar Orçamento', 'hng-commerce');
                            break;
                        case 'appointment':
                            $button_text = __('Agendar', 'hng-commerce');
                            break;
                        case 'subscription':
                            $button_text = __('Assinar', 'hng-commerce');
                            break;
                        case 'digital':
                            $button_text = __('Comprar Digital', 'hng-commerce');
                            break;
                    }
                }
            ?>
                <div class="hng-product-card" data-product-type="<?php echo esc_attr($product_type); ?>">
                    <?php if ($settings['show_image'] === 'yes') : ?>
                        <div class="hng-product-image">
                            <a href="<?php echo esc_url(get_permalink()); ?>">
                                <img src="<?php echo esc_url($thumbnail); ?>" alt="<?php echo esc_attr(get_the_title()); ?>" style="width: 100%; display: block;">
                            </a>
                            <?php if ($show_type_badge && class_exists('HNG_Elementor_Helpers')) : ?>
                                <div class="hng-product-type-overlay" style="position: absolute; top: 10px; left: 10px;">
                                    <?php 
                                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Badge HTML is safely generated by HNG_Elementor_Helpers with proper escaping
                                    echo HNG_Elementor_Helpers::render_product_type_badge($product_type); 
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($settings['show_title'] === 'yes') : ?>
                        <h3 class="hng-product-title">
                            <a href="<?php echo esc_url(get_permalink()); ?>" style="text-decoration: none; color: inherit;">
                                <?php echo esc_html(get_the_title()); ?>
                            </a>
                        </h3>
                    <?php endif; ?>
                    
                    <?php if ($settings['show_price'] === 'yes') : ?>
                        <div class="hng-product-price">
                            <?php 
                            if ($product_type === 'quote') {
                                esc_html_e('Sob Consulta', 'hng-commerce');
                            } else {
                                echo esc_html(hng_price($product->get_price())); 
                            }
                            ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($settings['show_button'] === 'yes') : ?>
                        <button class="hng-product-button hng-product-button-<?php echo esc_attr($product_type); ?>" data-product-id="<?php echo esc_attr(get_the_ID()); ?>" data-product-type="<?php echo esc_attr($product_type); ?>" style="width: 100%; border: none; cursor: pointer; text-align: center; display: block;">
                            <?php echo esc_html($button_text); ?>
                        </button>
                    <?php endif; ?>
                </div>
            <?php endwhile; wp_reset_postdata(); ?>
        </div>
        
        <style>
            {{WRAPPER}} .hng-product-grid-grid {
                display: grid;
                grid-template-columns: repeat(<?php echo esc_attr($columns); ?>, 1fr);
                gap: 20px;
                width: 100%;
            }
            {{WRAPPER}} .hng-product-grid-list {
                display: flex;
                flex-direction: column;
                gap: 15px;
                width: 100%;
            }
            {{WRAPPER}} .hng-product-grid-list .hng-product-card {
                display: flex;
                flex-direction: row;
                align-items: center;
                gap: 20px;
            }
            {{WRAPPER}} .hng-product-grid-list .hng-product-image {
                flex: 0 0 150px;
            }
            {{WRAPPER}} .hng-product-grid-carousel {
                display: flex;
                overflow-x: auto;
                gap: 20px;
                scroll-snap-type: x mandatory;
                padding-bottom: 10px;
            }
            {{WRAPPER}} .hng-product-grid-carousel .hng-product-card {
                flex: 0 0 300px;
                scroll-snap-align: start;
            }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('.hng-product-button').on('click', function() {
                var productId = $(this).data('product-id');
                // Adicionar ao carrinho via AJAX
                $.post(ajaxurl, {
                    action: 'hng_add_to_cart',
                    product_id: productId,
                    quantity: 1
                }, function(response) {
                    if (response.success) {
                        alert('Produto adicionado ao carrinho!');
                    }
                });
            });
        });
        </script>
        <?php
    }
}
