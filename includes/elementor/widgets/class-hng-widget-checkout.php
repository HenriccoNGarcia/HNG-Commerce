<?php
if (!defined('ABSPATH')) { exit; }

class HNG_Widget_Checkout extends HNG_Commerce_Elementor_Widget_Base {
    public function get_name() { return 'hng_checkout'; }
    public function get_title() { return __('Checkout', 'hng-commerce'); }
    public function get_icon() { return 'eicon-form-horizontal'; }

    protected function register_controls() {
                // Banner/Carrossel Section
                $this->start_controls_section(
                    'banner_section',
                    [
                        'label' => __('Banner/Carrossel', 'hng-commerce'),
                        'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
                    ]
                );
                $this->add_control(
                    'show_banner',
                    [
                        'label' => __('Exibir Banner/Carrossel', 'hng-commerce'),
                        'type' => \Elementor\Controls_Manager::SWITCHER,
                        'label_on' => __('Sim', 'hng-commerce'),
                        'label_off' => __('Não', 'hng-commerce'),
                        'return_value' => 'yes',
                        'default' => 'no',
                    ]
                );
                $this->add_control(
                    'banner_type',
                    [
                        'label' => __('Tipo', 'hng-commerce'),
                        'type' => \Elementor\Controls_Manager::SELECT,
                        'options' => [
                            'image' => __('Imagem única', 'hng-commerce'),
                            'carousel' => __('Carrossel', 'hng-commerce'),
                        ],
                        'default' => 'image',
                        'condition' => [ 'show_banner' => 'yes' ],
                    ]
                );
                $this->add_control(
                    'banner_images',
                    [
                        'label' => __('Imagens do Banner', 'hng-commerce'),
                        'type' => \Elementor\Controls_Manager::GALLERY,
                        'condition' => [ 'show_banner' => 'yes' ],
                    ]
                );
                $this->add_control(
                    'banner_title',
                    [
                        'label' => __('Título do Banner', 'hng-commerce'),
                        'type' => \Elementor\Controls_Manager::TEXT,
                        'default' => __('Bem-vindo ao Checkout!', 'hng-commerce'),
                        'condition' => [ 'show_banner' => 'yes' ],
                    ]
                );
                $this->add_control(
                    'banner_description',
                    [
                        'label' => __('Descrição do Banner', 'hng-commerce'),
                        'type' => \Elementor\Controls_Manager::TEXTAREA,
                        'default' => __('Finalize sua compra com segurança e aproveite nossas ofertas.', 'hng-commerce'),
                        'condition' => [ 'show_banner' => 'yes' ],
                    ]
                );
                $this->end_controls_section();

                // Up Sell / Cross Sell Section
                $this->start_controls_section(
                    'upsell_section',
                    [
                        'label' => __('Up Sell / Cross Sell', 'hng-commerce'),
                        'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
                    ]
                );
                $this->add_control(
                    'show_upsell',
                    [
                        'label' => __('Exibir Up Sell', 'hng-commerce'),
                        'type' => \Elementor\Controls_Manager::SWITCHER,
                        'label_on' => __('Sim', 'hng-commerce'),
                        'label_off' => __('Não', 'hng-commerce'),
                        'return_value' => 'yes',
                        'default' => 'no',
                    ]
                );
                $this->add_control(
                    'upsell_products',
                    [
                        'label' => __('Produtos Up Sell', 'hng-commerce'),
                        'type' => \Elementor\Controls_Manager::TEXT,
                        'description' => __('IDs dos produtos separados por vírgula.', 'hng-commerce'),
                        'condition' => [ 'show_upsell' => 'yes' ],
                    ]
                );
                $this->add_control(
                    'show_crosssell',
                    [
                        'label' => __('Exibir Cross Sell', 'hng-commerce'),
                        'type' => \Elementor\Controls_Manager::SWITCHER,
                        'label_on' => __('Sim', 'hng-commerce'),
                        'label_off' => __('Não', 'hng-commerce'),
                        'return_value' => 'yes',
                        'default' => 'no',
                    ]
                );
                $this->add_control(
                    'crosssell_products',
                    [
                        'label' => __('Produtos Cross Sell', 'hng-commerce'),
                        'type' => \Elementor\Controls_Manager::TEXT,
                        'description' => __('IDs dos produtos separados por vírgula.', 'hng-commerce'),
                        'condition' => [ 'show_crosssell' => 'yes' ],
                    ]
                );
                $this->end_controls_section();

                // Títulos, descrições e placeholders editáveis
                $this->start_controls_section(
                    'labels_section',
                    [
                        'label' => __('Textos e Placeholders', 'hng-commerce'),
                        'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
                    ]
                );
                $this->add_control('label_billing_title', [ 'label' => __('Título Dados de Cobrança', 'hng-commerce'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Detalhes de Cobrança', 'hng-commerce') ]);
                $this->add_control('label_billing_first_name', [ 'label' => __('Nome', 'hng-commerce'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Nome *', 'hng-commerce') ]);
                $this->add_control('placeholder_billing_first_name', [ 'label' => __('Placeholder Nome', 'hng-commerce'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Digite seu nome', 'hng-commerce') ]);
                $this->add_control('label_billing_last_name', [ 'label' => __('Sobrenome', 'hng-commerce'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Sobrenome *', 'hng-commerce') ]);
                $this->add_control('placeholder_billing_last_name', [ 'label' => __('Placeholder Sobrenome', 'hng-commerce'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Digite seu sobrenome', 'hng-commerce') ]);
                $this->add_control('label_billing_email', [ 'label' => __('Email', 'hng-commerce'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Email *', 'hng-commerce') ]);
                $this->add_control('placeholder_billing_email', [ 'label' => __('Placeholder Email', 'hng-commerce'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Digite seu email', 'hng-commerce') ]);
                $this->add_control('label_billing_phone', [ 'label' => __('Telefone', 'hng-commerce'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Telefone *', 'hng-commerce') ]);
                $this->add_control('placeholder_billing_phone', [ 'label' => __('Placeholder Telefone', 'hng-commerce'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Digite seu telefone', 'hng-commerce') ]);
                $this->add_control('label_billing_document', [ 'label' => __('CPF/CNPJ', 'hng-commerce'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('CPF/CNPJ *', 'hng-commerce') ]);
                $this->add_control('placeholder_billing_document', [ 'label' => __('Placeholder CPF/CNPJ', 'hng-commerce'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Digite seu CPF ou CNPJ', 'hng-commerce') ]);
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
            'show_coupon_form',
            [
                'label' => __('Mostrar Formulário de Cupom', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Sim', 'hng-commerce'),
                'label_off' => __('Não', 'hng-commerce'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_order_notes',
            [
                'label' => __('Mostrar Observações do Pedido', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Sim', 'hng-commerce'),
                'label_off' => __('Não', 'hng-commerce'),
                'return_value' => 'yes',
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
                'label' => __('Espaçamento entre Colunas', 'hng-commerce'),
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
                    'size' => 30,
                ],
                'selectors' => [
                    '{{WRAPPER}} .hng-checkout-layout' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'container_background',
            [
                'label' => __('Cor de Fundo', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-checkout-layout' => 'background-color: {{VALUE}};',
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
                    '{{WRAPPER}} .hng-checkout-layout' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Form Section Style
        $this->start_controls_section(
            'section_heading_style',
            [
                'label' => __('Títulos das Seções', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'section_heading_color',
            [
                'label' => __('Cor', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-checkout-section-title' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'section_heading_typography',
                'selector' => '{{WRAPPER}} .hng-checkout-section-title',
            ]
        );

        $this->add_responsive_control(
            'section_heading_margin',
            [
                'label' => __('Margem', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .hng-checkout-section-title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Input Fields Style
        $this->start_controls_section(
            'input_style_section',
            [
                'label' => __('Campos de Entrada', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'input_background',
            [
                'label' => __('Cor de Fundo', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-checkout-input' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'input_color',
            [
                'label' => __('Cor do Texto', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-checkout-input' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'input_border',
                'selector' => '{{WRAPPER}} .hng-checkout-input',
            ]
        );

        $this->add_responsive_control(
            'input_border_radius',
            [
                'label' => __('Border Radius', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .hng-checkout-input' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'input_padding',
            [
                'label' => __('Padding', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .hng-checkout-input' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'input_typography',
                'selector' => '{{WRAPPER}} .hng-checkout-input',
            ]
        );

        $this->end_controls_section();

        // Labels Style
        $this->start_controls_section(
            'label_style_section',
            [
                'label' => __('Labels', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'label_color',
            [
                'label' => __('Cor', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-checkout-label' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'label_typography',
                'selector' => '{{WRAPPER}} .hng-checkout-label',
            ]
        );

        $this->end_controls_section();

        // Order Summary Style
        $this->start_controls_section(
            'order_summary_style_section',
            [
                'label' => __('Resumo do Pedido', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'order_summary_background',
            [
                'label' => __('Cor de Fundo', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-order-summary' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'order_summary_border',
                'selector' => '{{WRAPPER}} .hng-order-summary',
            ]
        );

        $this->add_responsive_control(
            'order_summary_border_radius',
            [
                'label' => __('Border Radius', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .hng-order-summary' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'order_summary_padding',
            [
                'label' => __('Padding', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .hng-order-summary' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Place Order Button Style
        $this->start_controls_section(
            'place_order_button_style_section',
            [
                'label' => __('Botão Finalizar Pedido', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->start_controls_tabs('place_order_button_tabs');

        $this->start_controls_tab(
            'place_order_button_normal',
            [
                'label' => __('Normal', 'hng-commerce'),
            ]
        );

        $this->add_control(
            'place_order_button_color',
            [
                'label' => __('Cor do Texto', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-place-order-button' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'place_order_button_background',
            [
                'label' => __('Cor de Fundo', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-place-order-button' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'place_order_button_hover',
            [
                'label' => __('Hover', 'hng-commerce'),
            ]
        );

        $this->add_control(
            'place_order_button_hover_color',
            [
                'label' => __('Cor do Texto', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-place-order-button:hover' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'place_order_button_hover_background',
            [
                'label' => __('Cor de Fundo', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-place-order-button:hover' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'place_order_button_typography',
                'selector' => '{{WRAPPER}} .hng-place-order-button',
                'separator' => 'before',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'place_order_button_border',
                'selector' => '{{WRAPPER}} .hng-place-order-button',
            ]
        );

        $this->add_responsive_control(
            'place_order_button_border_radius',
            [
                'label' => __('Border Radius', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .hng-place-order-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'place_order_button_padding',
            [
                'label' => __('Padding', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .hng-place-order-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $is_edit_mode = $this->is_edit_mode();
        $use_simulated = false;
        $simulated_total = 0;
        
        if (!class_exists('HNG_Cart')) {
            echo '<div class="hng-checkout-placeholder">';
            echo '<p>' . esc_html__('Sistema de checkout não disponível.', 'hng-commerce') . '</p>';
            echo '</div>';
            return;
        }

        $cart = HNG_Cart::instance();
        $cart_items = $cart->get_cart();
        
        // Modo de edição: simular produtos para prévia
        if ($is_edit_mode && empty($cart_items)) {
            $use_simulated = true;
            
            if (class_exists('HNG_Elementor_Helpers')) {
                $cart_items = HNG_Elementor_Helpers::get_simulated_cart_items(2);
            } else {
                // Buscar produtos reais
                $sample_products = get_posts([
                    'post_type' => 'hng_product',
                    'numberposts' => 2,
                    'post_status' => 'publish'
                ]);
                
                if (!empty($sample_products)) {
                    foreach ($sample_products as $index => $product_post) {
                        $product = new HNG_Product($product_post->ID);
                        $cart_items['demo_' . $index] = [
                            'product_id' => $product_post->ID,
                            'quantity' => $index + 1,
                            'name' => $product->get_name(),
                            'price' => $product->get_price(),
                            'is_simulated' => true,
                        ];
                    }
                } else {
                    // Dados fictícios
                    $cart_items = [
                        'demo_0' => [
                            'product_id' => 0,
                            'quantity' => 1,
                            'name' => __('Produto Exemplo', 'hng-commerce'),
                            'price' => 199.90,
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

        if (empty($cart_items)) {
            echo '<div class="hng-checkout-empty">';
            echo '<p>' . esc_html__('Seu carrinho está vazio. Adicione produtos antes de finalizar a compra.', 'hng-commerce') . '</p>';
            echo '<a href="' . esc_url(home_url('/produtos')) . '">' . esc_html__('Ir para Produtos', 'hng-commerce') . '</a>';
            echo '</div>';
            return;
        }
        
        // Calcular totais para exibição
        $display_subtotal = $use_simulated ? $simulated_total : $cart->get_cart_subtotal();
        $display_total = $use_simulated ? $simulated_total : $cart->get_cart_total();

        ?>
        <div class="hng-checkout-layout">
            <div class="hng-checkout-form-container">
                <form id="hng-checkout-form" method="post">
                    <?php if ($settings['show_coupon_form'] === 'yes') : ?>
                        <div class="hng-coupon-section">
                            <p><?php esc_html_e('Tem um cupom?', 'hng-commerce'); ?> <a href="#" class="show-coupon-form"><?php esc_html_e('Clique aqui para inserir seu código', 'hng-commerce'); ?></a></p>
                            <div class="hng-coupon-form" style="display:none;">
                                <input type="text" name="coupon_code" class="hng-checkout-input" placeholder="<?php esc_attr_e('Código do cupom', 'hng-commerce'); ?>" />
                                <button type="button" class="hng-apply-coupon"><?php esc_html_e('Aplicar Cupom', 'hng-commerce'); ?></button>
                            </div>
                        </div>
                    <?php endif; ?>

                    <h3 class="hng-checkout-section-title"><?php esc_html_e('Detalhes de Cobrança', 'hng-commerce'); ?></h3>
                    
                    <div class="hng-form-row">
                        <div class="hng-form-field">
                            <label class="hng-checkout-label"><?php esc_html_e('Nome *', 'hng-commerce'); ?></label>
                            <input type="text" name="billing_first_name" class="hng-checkout-input" required />
                        </div>
                        <div class="hng-form-field">
                            <label class="hng-checkout-label"><?php esc_html_e('Sobrenome *', 'hng-commerce'); ?></label>
                            <input type="text" name="billing_last_name" class="hng-checkout-input" required />
                        </div>
                    </div>

                    <div class="hng-form-field">
                        <label class="hng-checkout-label"><?php esc_html_e('Email *', 'hng-commerce'); ?></label>
                        <input type="email" name="billing_email" class="hng-checkout-input" required />
                    </div>

                    <div class="hng-form-field">
                        <label class="hng-checkout-label"><?php esc_html_e('Telefone *', 'hng-commerce'); ?></label>
                        <input type="tel" name="billing_phone" class="hng-checkout-input" required />
                    </div>

                    <div class="hng-form-field">
                        <label class="hng-checkout-label"><?php esc_html_e('CPF/CNPJ *', 'hng-commerce'); ?></label>
                        <input type="text" name="billing_document" class="hng-checkout-input" required />
                    </div>

                    <div class="hng-form-field">
                        <label class="hng-checkout-label"><?php esc_html_e('CEP *', 'hng-commerce'); ?></label>
                        <input type="text" name="billing_postcode" class="hng-checkout-input hng-cep-input" required />
                    </div>

                    <div class="hng-form-field">
                        <label class="hng-checkout-label"><?php esc_html_e('Endereço *', 'hng-commerce'); ?></label>
                        <input type="text" name="billing_address" class="hng-checkout-input" required />
                    </div>

                    <div class="hng-form-row">
                        <div class="hng-form-field">
                            <label class="hng-checkout-label"><?php esc_html_e('Número *', 'hng-commerce'); ?></label>
                            <input type="text" name="billing_number" class="hng-checkout-input" required />
                        </div>
                        <div class="hng-form-field">
                            <label class="hng-checkout-label"><?php esc_html_e('Complemento', 'hng-commerce'); ?></label>
                            <input type="text" name="billing_complement" class="hng-checkout-input" />
                        </div>
                    </div>

                    <div class="hng-form-row">
                        <div class="hng-form-field">
                            <label class="hng-checkout-label"><?php esc_html_e('Bairro *', 'hng-commerce'); ?></label>
                            <input type="text" name="billing_neighborhood" class="hng-checkout-input" required />
                        </div>
                        <div class="hng-form-field">
                            <label class="hng-checkout-label"><?php esc_html_e('Cidade *', 'hng-commerce'); ?></label>
                            <input type="text" name="billing_city" class="hng-checkout-input" required />
                        </div>
                    </div>

                    <div class="hng-form-field">
                        <label class="hng-checkout-label"><?php esc_html_e('Estado *', 'hng-commerce'); ?></label>
                        <select name="billing_state" class="hng-checkout-input" required>
                            <option value=""><?php esc_html_e('Selecione...', 'hng-commerce'); ?></option>
                            <?php
                            $estados = array('AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO');
                            foreach ($estados as $estado) {
                                echo '<option value="' . esc_attr($estado) . '">' . esc_html($estado) . '</option>';
                            }
                            ?>
                        </select>
                    </div>

                    <?php if ($settings['show_order_notes'] === 'yes') : ?>
                        <div class="hng-form-field">
                            <label class="hng-checkout-label"><?php esc_html_e('Observações do Pedido (opcional)', 'hng-commerce'); ?></label>
                            <textarea name="order_notes" class="hng-checkout-input" rows="4"></textarea>
                        </div>
                    <?php endif; ?>

                    <h3 class="hng-checkout-section-title"><?php esc_html_e('Método de Pagamento', 'hng-commerce'); ?></h3>
                    
                    <div class="hng-payment-methods">
                        <?php
                        $gateways = hng_get_available_gateways();
                        foreach ($gateways as $gateway_id => $gateway) :
                        ?>
                            <label class="hng-payment-method">
                                <input type="radio" name="payment_method" value="<?php echo esc_attr($gateway_id); ?>" <?php checked($gateway_id, 'credit_card'); ?> required />
                                <span><?php echo esc_html($gateway->get_title()); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <button type="submit" class="hng-place-order-button">
                        <?php esc_html_e('Finalizar Pedido', 'hng-commerce'); ?>
                    </button>

                    <?php wp_nonce_field('hng-checkout', 'hng_checkout_nonce'); ?>
                </form>
            </div>

            <div class="hng-order-summary">
                <h3 class="hng-checkout-section-title"><?php esc_html_e('Seu Pedido', 'hng-commerce'); ?></h3>
                
                <?php if ($use_simulated && $is_edit_mode) : ?>
                <div class="elementor-alert elementor-alert-info" style="margin-bottom: 15px;">
                    <p style="margin: 0; font-size: 12px;"><strong><?php esc_html_e('Modo de Prévia', 'hng-commerce'); ?></strong></p>
                </div>
                <?php endif; ?>
                
                <div class="hng-order-summary-items">
                    <?php foreach ($cart_items as $cart_item) : 
                        $product_id = isset($cart_item['product_id']) ? $cart_item['product_id'] : 0;
                        $quantity = isset($cart_item['quantity']) ? $cart_item['quantity'] : 1;
                        $is_item_simulated = isset($cart_item['is_simulated']) && $cart_item['is_simulated'];
                        
                        if ($is_item_simulated) {
                            $product_name = isset($cart_item['name']) ? $cart_item['name'] : __('Produto', 'hng-commerce');
                            $product_price = isset($cart_item['price']) ? $cart_item['price'] : 0;
                        } else {
                            $product = new HNG_Product($product_id);
                            $product_name = $product->get_name();
                            $product_price = $product->get_price();
                        }
                    ?>
                        <div class="hng-order-item">
                            <span class="item-name"><?php echo esc_html($product_name); ?> × <?php echo esc_html($quantity); ?></span>
                            <span class="item-total"><?php echo esc_html(hng_price($product_price * $quantity)); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="hng-order-totals">
                    <div class="hng-order-total-row">
                        <span><?php esc_html_e('Subtotal:', 'hng-commerce'); ?></span>
                        <span><?php echo esc_html(hng_price($display_subtotal)); ?></span>
                    </div>
                    <div class="hng-order-total-row hng-order-total">
                        <strong><?php esc_html_e('Total:', 'hng-commerce'); ?></strong>
                        <strong><?php echo esc_html(hng_price($display_total)); ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <style>
            {{WRAPPER}} .hng-checkout-layout {
                display: grid;
                grid-template-columns: 1fr 400px;
                align-items: flex-start;
            }
            {{WRAPPER}} .hng-form-row {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 15px;
            }
            {{WRAPPER}} .hng-form-field {
                margin-bottom: 20px;
            }
            {{WRAPPER}} .hng-checkout-label {
                display: block;
                margin-bottom: 8px;
                font-weight: 500;
            }
            {{WRAPPER}} .hng-checkout-input {
                width: 100%;
                box-sizing: border-box;
            }
            {{WRAPPER}} .hng-coupon-form {
                display: flex;
                gap: 10px;
                margin-top: 15px;
            }
            {{WRAPPER}} .hng-apply-coupon {
                white-space: nowrap;
                padding: 10px 20px;
                cursor: pointer;
            }
            {{WRAPPER}} .hng-payment-methods {
                margin: 20px 0;
            }
            {{WRAPPER}} .hng-payment-method {
                display: block;
                padding: 15px;
                border: 1px solid #ddd;
                margin-bottom: 10px;
                cursor: pointer;
                border-radius: 4px;
            }
            {{WRAPPER}} .hng-payment-method:hover {
                background: #f8f8f8;
            }
            {{WRAPPER}} .hng-payment-method input {
                margin-right: 10px;
            }
            {{WRAPPER}} .hng-place-order-button {
                width: 100%;
                cursor: pointer;
                border: none;
                transition: all 0.3s ease;
                font-size: 18px;
            }
            {{WRAPPER}} .hng-order-summary {
                position: sticky;
                top: 20px;
            }
            {{WRAPPER}} .hng-order-item {
                display: flex;
                justify-content: space-between;
                padding: 10px 0;
                border-bottom: 1px solid #eee;
            }
            {{WRAPPER}} .hng-order-total-row {
                display: flex;
                justify-content: space-between;
                padding: 10px 0;
                border-bottom: 1px solid #eee;
            }
            {{WRAPPER}} .hng-order-total {
                font-size: 1.2em;
                margin-top: 10px;
            }
            @media (max-width: 1024px) {
                {{WRAPPER}} .hng-checkout-layout {
                    grid-template-columns: 1fr;
                }
                {{WRAPPER}} .hng-order-summary {
                    position: static;
                    margin-top: 30px;
                }
            }
            @media (max-width: 768px) {
                {{WRAPPER}} .hng-form-row {
                    grid-template-columns: 1fr;
                }
            }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Show/hide coupon form
            $('.show-coupon-form').on('click', function(e) {
                e.preventDefault();
                $('.hng-coupon-form').slideToggle();
            });

            // Apply coupon
            $('.hng-apply-coupon').on('click', function() {
                var couponCode = $('input[name="coupon_code"]').val();
                if (!couponCode) {
                    alert('<?php esc_html_e('Digite o código do cupom', 'hng-commerce'); ?>');
                    return;
                }

                $.ajax({
                    url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                    type: 'POST',
                    data: {
                        action: 'hng_apply_coupon',
                        coupon_code: couponCode,
                        nonce: '<?php echo esc_attr(wp_create_nonce('hng-coupon')); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data.message);
                        }
                    }
                });
            });

            // CEP autocomplete
            $('.hng-cep-input').on('blur', function() {
                var cep = $(this).val().replace(/\D/g, '');
                if (cep.length === 8) {
                    $.ajax({
                        url: 'https://viacep.com.br/ws/' + cep + '/json/',
                        dataType: 'json',
                        success: function(data) {
                            if (!data.erro) {
                                $('input[name="billing_address"]').val(data.logradouro);
                                $('input[name="billing_neighborhood"]').val(data.bairro);
                                $('input[name="billing_city"]').val(data.localidade);
                                $('select[name="billing_state"]').val(data.uf);
                            }
                        }
                    });
                }
            });

            // Form submission
            $('#hng-checkout-form').on('submit', function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var $button = $form.find('.hng-place-order-button');
                
                $button.prop('disabled', true).text('<?php esc_html_e('Processando...', 'hng-commerce'); ?>');

                $.ajax({
                    url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                    type: 'POST',
                    data: $form.serialize() + '&action=hng_process_checkout',
                    success: function(response) {
                        if (response.success) {
                            window.location.href = response.data.redirect;
                        } else {
                            alert(response.data.message || '<?php esc_html_e('Erro ao processar pedido', 'hng-commerce'); ?>');
                            $button.prop('disabled', false).text('<?php esc_html_e('Finalizar Pedido', 'hng-commerce'); ?>');
                        }
                    },
                    error: function() {
                        alert('<?php esc_html_e('Erro ao processar pedido', 'hng-commerce'); ?>');
                        $button.prop('disabled', false).text('<?php esc_html_e('Finalizar Pedido', 'hng-commerce'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }
}
