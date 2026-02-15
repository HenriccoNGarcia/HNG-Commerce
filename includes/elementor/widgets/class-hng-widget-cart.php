<?php
if (!defined('ABSPATH')) { exit; }

class HNG_Widget_Cart extends HNG_Commerce_Elementor_Widget_Base {
    public function get_name() { return 'hng_cart'; }
    public function get_title() { return __('Carrinho', 'hng-commerce'); }
    public function get_icon() { return 'eicon-cart-medium'; }

    protected function register_controls() {
                // Títulos, descrições e placeholders editáveis
                $this->start_controls_section(
                    'labels_section',
                    [
                        'label' => __('Textos e Placeholders', 'hng-commerce'),
                        'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
                    ]
                );
                $this->add_control('label_cart_title', [ 'label' => __('Título do Carrinho', 'hng-commerce'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Seu Carrinho', 'hng-commerce') ]);
                $this->add_control('label_product', [ 'label' => __('Coluna Produto', 'hng-commerce'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Produto', 'hng-commerce') ]);
                $this->add_control('label_price', [ 'label' => __('Coluna Preço', 'hng-commerce'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Preço', 'hng-commerce') ]);
                $this->add_control('label_quantity', [ 'label' => __('Coluna Quantidade', 'hng-commerce'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Qtd.', 'hng-commerce') ]);
                $this->add_control('label_total', [ 'label' => __('Coluna Total', 'hng-commerce'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Total', 'hng-commerce') ]);
                $this->add_control('placeholder_coupon', [ 'label' => __('Placeholder Cupom', 'hng-commerce'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Digite o cupom', 'hng-commerce') ]);
                $this->add_control('label_apply_coupon', [ 'label' => __('Botão Aplicar Cupom', 'hng-commerce'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Aplicar', 'hng-commerce') ]);
                $this->add_control('label_empty_cart', [ 'label' => __('Mensagem Carrinho Vazio', 'hng-commerce'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Seu carrinho está vazio.', 'hng-commerce') ]);
                $this->end_controls_section();

                // Variação de layout (grid/lista/carrossel)
                $this->start_controls_section(
                    'layout_section',
                    [
                        'label' => __('Layout', 'hng-commerce'),
                        'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
                    ]
                );
                $this->add_control('cart_layout', [
                    'label' => __('Layout do Carrinho', 'hng-commerce'),
                    'type' => \Elementor\Controls_Manager::SELECT,
                    'options' => [
                        'table' => __('Tabela', 'hng-commerce'),
                        'grid' => __('Grid', 'hng-commerce'),
                        'carousel' => __('Carrossel', 'hng-commerce'),
                    ],
                    'default' => 'table',
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
            'show_product_image',
            [
                'label' => __('Mostrar Imagem do Produto', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Sim', 'hng-commerce'),
                'label_off' => __('Não', 'hng-commerce'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_remove_button',
            [
                'label' => __('Mostrar Botão Remover', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Sim', 'hng-commerce'),
                'label_off' => __('Não', 'hng-commerce'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_continue_shopping',
            [
                'label' => __('Mostrar Continuar Comprando', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Sim', 'hng-commerce'),
                'label_off' => __('Não', 'hng-commerce'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'continue_shopping_text',
            [
                'label' => __('Texto Continuar Comprando', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Continuar Comprando', 'hng-commerce'),
                'condition' => [
                    'show_continue_shopping' => 'yes',
                ],
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

        $this->add_control(
            'container_background',
            [
                'label' => __('Cor de Fundo', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-cart-container' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'container_padding',
            [
                'label' => __('Padding', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .hng-cart-container' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Table Style
        $this->start_controls_section(
            'table_style_section',
            [
                'label' => __('Tabela', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'table_border',
                'selector' => '{{WRAPPER}} .hng-cart-table',
            ]
        );

        $this->add_control(
            'table_header_background',
            [
                'label' => __('Cor de Fundo do Cabeçalho', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-cart-table thead th' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'table_header_color',
            [
                'label' => __('Cor do Texto do Cabeçalho', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-cart-table thead th' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'table_row_background',
            [
                'label' => __('Cor de Fundo da Linha', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-cart-table tbody tr' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Image Style
        $this->start_controls_section(
            'image_style_section',
            [
                'label' => __('Imagem do Produto', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_product_image' => 'yes',
                ],
            ]
        );

        $this->add_responsive_control(
            'image_size',
            [
                'label' => __('Tamanho', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 50,
                        'max' => 200,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 80,
                ],
                'selectors' => [
                    '{{WRAPPER}} .hng-cart-product-image' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'image_border_radius',
            [
                'label' => __('Border Radius', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .hng-cart-product-image' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Product Title Style
        $this->start_controls_section(
            'title_style_section',
            [
                'label' => __('Título do Produto', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'title_color',
            [
                'label' => __('Cor', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-cart-product-title' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'selector' => '{{WRAPPER}} .hng-cart-product-title',
            ]
        );

        $this->end_controls_section();

        // Quantity Style
        $this->start_controls_section(
            'quantity_style_section',
            [
                'label' => __('Quantidade', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'quantity_input_background',
            [
                'label' => __('Cor de Fundo', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-quantity-input' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'quantity_input_color',
            [
                'label' => __('Cor do Texto', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-quantity-input' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'quantity_input_border',
                'selector' => '{{WRAPPER}} .hng-quantity-input',
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
                    '{{WRAPPER}} .hng-cart-price' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'price_typography',
                'selector' => '{{WRAPPER}} .hng-cart-price',
            ]
        );

        $this->end_controls_section();

        // Remove Button Style
        $this->start_controls_section(
            'remove_button_style_section',
            [
                'label' => __('Botão Remover', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_remove_button' => 'yes',
                ],
            ]
        );

        $this->start_controls_tabs('remove_button_tabs');

        $this->start_controls_tab(
            'remove_button_normal',
            [
                'label' => __('Normal', 'hng-commerce'),
            ]
        );

        $this->add_control(
            'remove_button_color',
            [
                'label' => __('Cor', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-remove-item' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'remove_button_hover',
            [
                'label' => __('Hover', 'hng-commerce'),
            ]
        );

        $this->add_control(
            'remove_button_hover_color',
            [
                'label' => __('Cor', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-remove-item:hover' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->end_controls_section();

        // Totals Style
        $this->start_controls_section(
            'totals_style_section',
            [
                'label' => __('Totais', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'totals_background',
            [
                'label' => __('Cor de Fundo', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-cart-totals' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'totals_color',
            [
                'label' => __('Cor do Texto', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-cart-totals' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'totals_typography',
                'selector' => '{{WRAPPER}} .hng-cart-totals',
            ]
        );

        $this->add_responsive_control(
            'totals_padding',
            [
                'label' => __('Padding', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .hng-cart-totals' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Proceed Button Style
        $this->start_controls_section(
            'proceed_button_style_section',
            [
                'label' => __('Botão Finalizar Compra', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->start_controls_tabs('proceed_button_tabs');

        $this->start_controls_tab(
            'proceed_button_normal',
            [
                'label' => __('Normal', 'hng-commerce'),
            ]
        );

        $this->add_control(
            'proceed_button_color',
            [
                'label' => __('Cor do Texto', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-proceed-button' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'proceed_button_background',
            [
                'label' => __('Cor de Fundo', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-proceed-button' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'proceed_button_hover',
            [
                'label' => __('Hover', 'hng-commerce'),
            ]
        );

        $this->add_control(
            'proceed_button_hover_color',
            [
                'label' => __('Cor do Texto', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-proceed-button:hover' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'proceed_button_hover_background',
            [
                'label' => __('Cor de Fundo', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-proceed-button:hover' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'proceed_button_typography',
                'selector' => '{{WRAPPER}} .hng-proceed-button',
                'separator' => 'before',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'proceed_button_border',
                'selector' => '{{WRAPPER}} .hng-proceed-button',
            ]
        );

        $this->add_responsive_control(
            'proceed_button_border_radius',
            [
                'label' => __('Border Radius', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .hng-proceed-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'proceed_button_padding',
            [
                'label' => __('Padding', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .hng-proceed-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $layout = $settings['cart_layout'] ?? 'table';
        $is_edit_mode = $this->is_edit_mode();
        
        if (!class_exists('HNG_Cart')) {
            echo '<div class="hng-cart-placeholder elementor-alert elementor-alert-danger">';
            echo '<p>' . esc_html__('Sistema de carrinho não disponível.', 'hng-commerce') . '</p>';
            echo '</div>';
            return;
        }

        $cart = HNG_Cart::instance();
        $cart_items = $cart->get_cart();
        $use_simulated = false;
        $simulated_total = 0;
        
        // Modo de edição: SEMPRE mostrar produtos simulados para prévia
        if ($is_edit_mode && empty($cart_items)) {
            $use_simulated = true;
            
            // Usar helper se disponível
            if (class_exists('HNG_Elementor_Helpers')) {
                $cart_items = HNG_Elementor_Helpers::get_simulated_cart_items(3);
            } else {
                // Fallback: buscar produtos reais
                $sample_products = get_posts([
                    'post_type' => 'hng_product',
                    'numberposts' => 3,
                    'post_status' => 'publish'
                ]);
                
                if (!empty($sample_products)) {
                    $cart_items = [];
                    foreach ($sample_products as $index => $product_post) {
                        $product = new HNG_Product($product_post->ID);
                        $cart_items['demo_' . $index] = [
                            'product_id' => $product_post->ID,
                            'quantity' => $index + 1,
                            'name' => $product->get_name(),
                            'price' => $product->get_price(),
                            'image_url' => $product->get_image_url('thumbnail'),
                            'is_simulated' => true,
                        ];
                    }
                } else {
                    // Sem produtos: criar dados fictícios
                    $cart_items = [
                        'demo_0' => [
                            'product_id' => 0,
                            'quantity' => 2,
                            'name' => __('Produto Exemplo 1', 'hng-commerce'),
                            'price' => 99.90,
                            'image_url' => HNG_COMMERCE_URL . 'assets/images/placeholder.svg',
                            'is_simulated' => true,
                        ],
                        'demo_1' => [
                            'product_id' => 0,
                            'quantity' => 1,
                            'name' => __('Produto Exemplo 2', 'hng-commerce'),
                            'price' => 149.90,
                            'image_url' => HNG_COMMERCE_URL . 'assets/images/placeholder.svg',
                            'is_simulated' => true,
                        ],
                    ];
                }
            }
            
            // Calcular total simulado
            foreach ($cart_items as $item) {
                $price = isset($item['price']) ? $item['price'] : 0;
                $qty = isset($item['quantity']) ? $item['quantity'] : 1;
                $simulated_total += $price * $qty;
            }
        }

        ?>
        <div class="hng-cart-container hng-cart-layout-<?php echo esc_attr($layout); ?>" data-layout="<?php echo esc_attr($layout); ?>">
            <?php if (empty($cart_items)) : ?>
                <div class="hng-cart-empty">
                    <?php if ($is_edit_mode) : ?>
                        <div class="elementor-alert elementor-alert-info">
                            <p><?php esc_html_e('Carrinho vazio - Crie alguns produtos para visualizar a prévia.', 'hng-commerce'); ?></p>
                        </div>
                    <?php else : ?>
                        <p><?php esc_html_e('Seu carrinho está vazio.', 'hng-commerce'); ?></p>
                    <?php endif; ?>
                    <?php if ($settings['show_continue_shopping'] === 'yes') : ?>
                        <a href="<?php echo esc_url(home_url('/produtos')); ?>" class="hng-continue-shopping">
                            <?php echo esc_html($settings['continue_shopping_text']); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php else : ?>
                <table class="hng-cart-table">
                    <thead>
                        <tr>
                            <?php if ($settings['show_product_image'] === 'yes') : ?>
                                <th><?php esc_html_e('Imagem', 'hng-commerce'); ?></th>
                            <?php endif; ?>
                            <th><?php esc_html_e('Produto', 'hng-commerce'); ?></th>
                            <th><?php esc_html_e('Preço', 'hng-commerce'); ?></th>
                            <th><?php esc_html_e('Quantidade', 'hng-commerce'); ?></th>
                            <th><?php esc_html_e('Subtotal', 'hng-commerce'); ?></th>
                            <?php if ($settings['show_remove_button'] === 'yes') : ?>
                                <th></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart_items as $cart_item_key => $cart_item) : 
                            $product_id = isset($cart_item['product_id']) ? $cart_item['product_id'] : 0;
                            $quantity = isset($cart_item['quantity']) ? $cart_item['quantity'] : 1;
                            $is_item_simulated = isset($cart_item['is_simulated']) && $cart_item['is_simulated'];
                            
                            // Para itens simulados, usar dados diretos
                            if ($is_item_simulated) {
                                $product_name = isset($cart_item['name']) ? $cart_item['name'] : __('Produto', 'hng-commerce');
                                $price = isset($cart_item['price']) ? floatval($cart_item['price']) : 0;
                                $image_url = isset($cart_item['image_url']) ? $cart_item['image_url'] : HNG_COMMERCE_URL . 'assets/images/placeholder.svg';
                            } else {
                                // Para itens reais, carregar do banco
                                $product = new HNG_Product($product_id);
                                $product_name = $product->get_name();
                                $price = $product->get_price();
                                $image_url = $product->get_image_url('thumbnail');
                            }
                            
                            $subtotal = $price * $quantity;
                        ?>
                            <tr data-cart-item-key="<?php echo esc_attr($cart_item_key); ?>" <?php echo $is_item_simulated ? 'class="hng-simulated-item"' : ''; ?>>
                                <?php if ($settings['show_product_image'] === 'yes') : ?>
                                    <td>
                                        <img src="<?php echo esc_url($image_url); ?>" class="hng-cart-product-image" alt="<?php echo esc_attr($product_name); ?>" />
                                    </td>
                                <?php endif; ?>
                                <td class="hng-cart-product-title">
                                    <?php echo esc_html($product_name); ?>
                                    <?php if ($is_item_simulated) : ?>
                                        <span style="color: #666; font-size: 11px; display: block;"><?php esc_html_e('(Prévia)', 'hng-commerce'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="hng-cart-price"><?php echo esc_html(hng_price($price)); ?></td>
                                <td>
                                    <input type="number" 
                                           class="hng-quantity-input" 
                                           value="<?php echo esc_attr($quantity); ?>" 
                                           min="1" 
                                           data-cart-item-key="<?php echo esc_attr($cart_item_key); ?>"
                                           <?php echo $is_item_simulated ? 'disabled' : ''; ?> />
                                </td>
                                <td class="hng-cart-price"><?php echo esc_html(hng_price($subtotal)); ?></td>
                                <?php if ($settings['show_remove_button'] === 'yes') : ?>
                                    <td>
                                        <?php if (!$is_item_simulated) : ?>
                                        <button class="hng-remove-item" data-cart-item-key="<?php echo esc_attr($cart_item_key); ?>">
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($use_simulated && $is_edit_mode) : ?>
                <div class="elementor-alert elementor-alert-info" style="margin: 15px 0;">
                    <p style="margin: 0;"><strong><?php esc_html_e('Modo de Prévia:', 'hng-commerce'); ?></strong> <?php esc_html_e('Estes são produtos simulados. No frontend, o carrinho real será exibido.', 'hng-commerce'); ?></p>
                </div>
                <?php endif; ?>

                <div class="hng-cart-totals">
                    <h3><?php esc_html_e('Total do Carrinho', 'hng-commerce'); ?></h3>
                    <div class="hng-cart-total-row">
                        <span><?php esc_html_e('Subtotal:', 'hng-commerce'); ?></span>
                        <span><?php echo esc_html(hng_price($use_simulated ? $simulated_total : $cart->get_cart_subtotal())); ?></span>
                    </div>
                    <div class="hng-cart-total-row hng-cart-total">
                        <strong><?php esc_html_e('Total:', 'hng-commerce'); ?></strong>
                        <strong><?php echo esc_html(hng_price($use_simulated ? $simulated_total : $cart->get_cart_total())); ?></strong>
                    </div>
                    <a href="<?php echo esc_url(home_url('/checkout')); ?>" class="hng-proceed-button">
                        <?php esc_html_e('Finalizar Compra', 'hng-commerce'); ?>
                    </a>
                </div>

                <?php if ($settings['show_continue_shopping'] === 'yes') : ?>
                    <a href="<?php echo esc_url(home_url('/produtos')); ?>" class="hng-continue-shopping">
                        <?php echo esc_html($settings['continue_shopping_text']); ?>
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <style>
            /* Layout Tabela */
            {{WRAPPER}} .hng-cart-layout-table .hng-cart-table {
                width: 100%;
                border-collapse: collapse;
            }
            {{WRAPPER}} .hng-cart-layout-table .hng-cart-table th,
            {{WRAPPER}} .hng-cart-layout-table .hng-cart-table td {
                padding: 15px;
                text-align: left;
                border-bottom: 1px solid #eee;
            }
            
            /* Layout Grid */
            {{WRAPPER}} .hng-cart-layout-grid .hng-cart-table tbody {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                gap: 20px;
            }
            {{WRAPPER}} .hng-cart-layout-grid .hng-cart-table thead {
                display: none;
            }
            {{WRAPPER}} .hng-cart-layout-grid .hng-cart-table tr {
                display: flex;
                flex-direction: column;
                border: 1px solid #eee;
                padding: 15px;
                border-radius: 8px;
            }
            {{WRAPPER}} .hng-cart-layout-grid .hng-cart-table td {
                border: none;
                padding: 5px 0;
            }
            
            /* Layout Carrossel */
            {{WRAPPER}} .hng-cart-layout-carousel .hng-cart-table tbody {
                display: flex;
                overflow-x: auto;
                gap: 20px;
                scroll-snap-type: x mandatory;
                padding-bottom: 10px;
            }
            {{WRAPPER}} .hng-cart-layout-carousel .hng-cart-table thead {
                display: none;
            }
            {{WRAPPER}} .hng-cart-layout-carousel .hng-cart-table tr {
                display: flex;
                flex-direction: column;
                flex: 0 0 300px;
                scroll-snap-align: start;
                border: 1px solid #eee;
                padding: 15px;
                border-radius: 8px;
            }
            {{WRAPPER}} .hng-cart-layout-carousel .hng-cart-table td {
                border: none;
                padding: 5px 0;
            }
            
            {{WRAPPER}} .hng-cart-product-image {
                object-fit: cover;
            }
            {{WRAPPER}} .hng-quantity-input {
                width: 80px;
                padding: 5px 10px;
                text-align: center;
            }
            {{WRAPPER}} .hng-remove-item {
                background: none;
                border: none;
                font-size: 24px;
                cursor: pointer;
                transition: color 0.3s ease;
            }
            {{WRAPPER}} .hng-cart-totals {
                margin-top: 30px;
                max-width: 400px;
                margin-left: auto;
            }
            {{WRAPPER}} .hng-cart-total-row {
                display: flex;
                justify-content: space-between;
                padding: 10px 0;
                border-bottom: 1px solid #eee;
            }
            {{WRAPPER}} .hng-cart-total {
                font-size: 1.2em;
                margin-top: 10px;
            }
            {{WRAPPER}} .hng-proceed-button {
                display: block;
                width: 100%;
                text-align: center;
                margin-top: 20px;
                cursor: pointer;
                border: none;
                text-decoration: none;
                transition: all 0.3s ease;
            }
            {{WRAPPER}} .hng-continue-shopping {
                display: inline-block;
                margin-top: 20px;
                text-decoration: underline;
            }
            {{WRAPPER}} .hng-cart-empty {
                text-align: center;
                padding: 50px 20px;
            }
            @media (max-width: 768px) {
                {{WRAPPER}} .hng-cart-table {
                    font-size: 14px;
                }
                {{WRAPPER}} .hng-cart-table th,
                {{WRAPPER}} .hng-cart-table td {
                    padding: 10px 5px;
                }
            }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Update quantity
            $('.hng-quantity-input').on('change', function() {
                var $input = $(this);
                var cartItemKey = $input.data('cart-item-key');
                var quantity = parseInt($input.val());

                $.ajax({
                    url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                    type: 'POST',
                    data: {
                        action: 'hng_update_cart_quantity',
                        cart_item_key: cartItemKey,
                        quantity: quantity,
                        nonce: '<?php echo esc_attr(wp_create_nonce('hng-cart')); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        }
                    }
                });
            });

            // Remove item
            $('.hng-remove-item').on('click', function() {
                var $button = $(this);
                var cartItemKey = $button.data('cart-item-key');

                if (!confirm('<?php esc_html_e('Remover este item do carrinho?', 'hng-commerce'); ?>')) {
                    return;
                }

                $.ajax({
                    url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                    type: 'POST',
                    data: {
                        action: 'hng_remove_cart_item',
                        cart_item_key: cartItemKey,
                        nonce: '<?php echo esc_attr(wp_create_nonce('hng-cart')); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        }
                    }
                });
            });
        });
        </script>
        <?php
    }
}
