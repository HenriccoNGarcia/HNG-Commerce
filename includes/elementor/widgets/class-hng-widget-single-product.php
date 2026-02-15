<?php
if (!defined('ABSPATH')) { exit; }

class HNG_Widget_Single_Product extends HNG_Commerce_Elementor_Widget_Base {
    public function get_name() { return 'hng_single_product'; }
    public function get_title() { return __('Produto Único', 'hng-commerce'); }
    public function get_icon() { return 'eicon-product-price'; }

    protected function register_controls() {
                // Títulos, descrições e placeholders editáveis
                $this->start_controls_section(
                    'labels_section',
                    [
                        'label' => __('Textos e Placeholders', 'hng-commerce'),
                        'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
                    ]
                );
                $this->add_control('label_product_title', [ 'label' => __('Título do Produto', 'hng-commerce'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Detalhes do Produto', 'hng-commerce') ]);
                $this->add_control('label_price', [ 'label' => __('Texto Preço', 'hng-commerce'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Preço', 'hng-commerce') ]);
                $this->add_control('label_description', [ 'label' => __('Texto Descrição', 'hng-commerce'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Descrição', 'hng-commerce') ]);
                $this->add_control('label_add_to_cart', [ 'label' => __('Botão Adicionar ao Carrinho', 'hng-commerce'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Comprar', 'hng-commerce') ]);
                $this->add_control('label_empty', [ 'label' => __('Mensagem Produto Não Encontrado', 'hng-commerce'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Produto não encontrado.', 'hng-commerce') ]);
                $this->end_controls_section();

                // Variação de layout (vertical/horizontal/carrossel)
                $this->start_controls_section(
                    'layout_section',
                    [
                        'label' => __('Layout', 'hng-commerce'),
                        'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
                    ]
                );
                $this->add_control('single_product_layout', [
                    'label' => __('Layout do Produto', 'hng-commerce'),
                    'type' => \Elementor\Controls_Manager::SELECT,
                    'options' => [
                        'vertical' => __('Vertical', 'hng-commerce'),
                        'horizontal' => __('Horizontal', 'hng-commerce'),
                        'carousel' => __('Carrossel', 'hng-commerce'),
                    ],
                    'default' => 'vertical',
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

        // Origem do produto
        $this->add_control(
            'product_source',
            [
                'label' => __('Origem do Produto', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    'current' => __('Produto Atual (Página de Produto)', 'hng-commerce'),
                    'specific' => __('Produto Específico (por ID)', 'hng-commerce'),
                    'latest' => __('Produto Mais Recente', 'hng-commerce'),
                    'by_type' => __('Por Tipo de Produto', 'hng-commerce'),
                ],
                'default' => 'current',
            ]
        );

        $this->add_control(
            'product_id',
            [
                'label' => __('ID do Produto', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 0,
                'description' => __('Insira o ID do produto HNG Commerce', 'hng-commerce'),
                'condition' => [
                    'product_source' => 'specific',
                ],
            ]
        );
        
        $this->add_control(
            'product_type_filter',
            [
                'label' => __('Tipo de Produto', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    '' => __('Qualquer Tipo', 'hng-commerce'),
                    'physical' => __('Produto Físico', 'hng-commerce'),
                    'digital' => __('Produto Digital', 'hng-commerce'),
                    'subscription' => __('Assinatura', 'hng-commerce'),
                    'appointment' => __('Agendamento', 'hng-commerce'),
                    'quote' => __('Orçamento', 'hng-commerce'),
                ],
                'default' => '',
                'condition' => [
                    'product_source' => 'by_type',
                ],
            ]
        );

        $this->add_control(
            'show_gallery',
            [
                'label' => __('Mostrar Galeria', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Sim', 'hng-commerce'),
                'label_off' => __('Não', 'hng-commerce'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_title',
            [
                'label' => __('Mostrar Título', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Sim', 'hng-commerce'),
                'label_off' => __('Não', 'hng-commerce'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_price',
            [
                'label' => __('Mostrar Preço', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Sim', 'hng-commerce'),
                'label_off' => __('Não', 'hng-commerce'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_description',
            [
                'label' => __('Mostrar Descrição', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Sim', 'hng-commerce'),
                'label_off' => __('Não', 'hng-commerce'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_add_to_cart',
            [
                'label' => __('Mostrar Adicionar ao Carrinho', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Sim', 'hng-commerce'),
                'label_off' => __('Não', 'hng-commerce'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_meta',
            [
                'label' => __('Mostrar Meta (SKU, Categorias)', 'hng-commerce'),
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
                    '{{WRAPPER}} .hng-single-product-layout' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'container_background',
            [
                'label' => __('Cor de Fundo', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-single-product-layout' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'container_border',
                'selector' => '{{WRAPPER}} .hng-single-product-layout',
            ]
        );

        $this->add_responsive_control(
            'container_border_radius',
            [
                'label' => __('Border Radius', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .hng-single-product-layout' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
                    '{{WRAPPER}} .hng-single-product-layout' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Gallery Style
        $this->start_controls_section(
            'gallery_style_section',
            [
                'label' => __('Galeria', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_gallery' => 'yes',
                ],
            ]
        );

        $this->add_responsive_control(
            'gallery_width',
            [
                'label' => __('Largura', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['%'],
                'range' => [
                    '%' => [
                        'min' => 30,
                        'max' => 70,
                    ],
                ],
                'default' => [
                    'unit' => '%',
                    'size' => 50,
                ],
                'selectors' => [
                    '{{WRAPPER}} .hng-product-gallery' => 'width: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'gallery_border_radius',
            [
                'label' => __('Border Radius', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .hng-product-gallery img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Title Style
        $this->start_controls_section(
            'title_style_section',
            [
                'label' => __('Título', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_title' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'title_color',
            [
                'label' => __('Cor', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
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
                'selectors' => [
                    '{{WRAPPER}} .hng-product-title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Price Style
        $this->start_controls_section(
            'price_style_section',
            [
                'label' => __('Preço', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_price' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'price_color',
            [
                'label' => __('Cor', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
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
                'selectors' => [
                    '{{WRAPPER}} .hng-product-price' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Description Style
        $this->start_controls_section(
            'description_style_section',
            [
                'label' => __('Descrição', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_description' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'description_color',
            [
                'label' => __('Cor', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-product-description' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'description_typography',
                'selector' => '{{WRAPPER}} .hng-product-description',
            ]
        );

        $this->add_responsive_control(
            'description_margin',
            [
                'label' => __('Margem', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .hng-product-description' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Button Style
        $this->start_controls_section(
            'button_style_section',
            [
                'label' => __('Botão Adicionar', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_add_to_cart' => 'yes',
                ],
            ]
        );

        $this->start_controls_tabs('button_style_tabs');

        $this->start_controls_tab(
            'button_normal_tab',
            [
                'label' => __('Normal', 'hng-commerce'),
            ]
        );

        $this->add_control(
            'button_color',
            [
                'label' => __('Cor do Texto', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-add-to-cart-button' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_background',
            [
                'label' => __('Cor de Fundo', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-add-to-cart-button' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'button_hover_tab',
            [
                'label' => __('Hover', 'hng-commerce'),
            ]
        );

        $this->add_control(
            'button_hover_color',
            [
                'label' => __('Cor do Texto', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-add-to-cart-button:hover' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_hover_background',
            [
                'label' => __('Cor de Fundo', 'hng-commerce'),
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

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'button_border',
                'selector' => '{{WRAPPER}} .hng-add-to-cart-button',
            ]
        );

        $this->add_responsive_control(
            'button_border_radius',
            [
                'label' => __('Border Radius', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .hng-add-to-cart-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'button_padding',
            [
                'label' => __('Padding', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .hng-add-to-cart-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Meta Style
        $this->start_controls_section(
            'meta_style_section',
            [
                'label' => __('Meta Info', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_meta' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'meta_color',
            [
                'label' => __('Cor', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-product-meta' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'meta_typography',
                'selector' => '{{WRAPPER}} .hng-product-meta',
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $layout = $settings['single_product_layout'] ?? 'vertical';
        $is_edit_mode = $this->is_edit_mode();
        $product_source = isset($settings['product_source']) ? $settings['product_source'] : 'current';
        $product_id = 0;
        $product = null;
        
        // Determinar o produto baseado na origem selecionada
        switch ($product_source) {
            case 'specific':
                $product_id = intval($settings['product_id'] ?? 0);
                if ($product_id > 0) {
                    $product = new HNG_Product($product_id);
                }
                break;
                
            case 'latest':
                $latest = get_posts([
                    'post_type' => 'hng_product',
                    'numberposts' => 1,
                    'post_status' => 'publish',
                    'orderby' => 'date',
                    'order' => 'DESC'
                ]);
                if (!empty($latest)) {
                    $product_id = $latest[0]->ID;
                    $product = new HNG_Product($product_id);
                }
                break;
                
            case 'by_type':
                $type_filter = isset($settings['product_type_filter']) ? $settings['product_type_filter'] : '';
                $query_args = [
                    'post_type' => 'hng_product',
                    'numberposts' => 1,
                    'post_status' => 'publish',
                ];
                if (!empty($type_filter)) {
                    $query_args['meta_query'] = [
                        'relation' => 'OR',
                        ['key' => '_hng_product_type', 'value' => $type_filter],
                        ['key' => '_product_type', 'value' => $type_filter],
                    ];
                }
                $typed = get_posts($query_args);
                if (!empty($typed)) {
                    $product_id = $typed[0]->ID;
                    $product = new HNG_Product($product_id);
                }
                break;
                
            case 'current':
            default:
                global $post;
                if ($post && $post->post_type === 'hng_product') {
                    $product_id = $post->ID;
                    $product = new HNG_Product($product_id);
                } elseif ($is_edit_mode) {
                    // No modo de edição, pegar o primeiro produto disponível
                    $first_product = get_posts([
                        'post_type' => 'hng_product',
                        'numberposts' => 1,
                        'post_status' => 'publish'
                    ]);
                    if (!empty($first_product)) {
                        $product_id = $first_product[0]->ID;
                        $product = new HNG_Product($product_id);
                    }
                }
                break;
        }

        if (!$product || !$product->get_id()) {
            echo '<div class="hng-single-product-placeholder elementor-alert elementor-alert-warning">';
            if ($is_edit_mode) {
                echo '<p><strong>' . esc_html__('Widget Produto Único', 'hng-commerce') . '</strong></p>';
                echo '<p>' . esc_html__('Nenhum produto encontrado. Opções:', 'hng-commerce') . '</p>';
                echo '<ul style="margin: 10px 0; padding-left: 20px;">';
                echo '<li>' . esc_html__('Selecione "Produto Específico" e insira um ID válido', 'hng-commerce') . '</li>';
                echo '<li>' . esc_html__('Selecione "Produto Mais Recente" para exibir automaticamente', 'hng-commerce') . '</li>';
                echo '<li>' . esc_html__('Crie um produto no HNG Commerce primeiro', 'hng-commerce') . '</li>';
                echo '</ul>';
            } else {
                echo '<p>' . esc_html__('Nenhum produto selecionado ou produto não encontrado.', 'hng-commerce') . '</p>';
            }
            echo '</div>';
            return;
        }

        $layout_class = 'hng-single-product-' . $layout;
        $product_type = method_exists($product, 'get_product_type') ? $product->get_product_type() : 'physical';
        ?>
        <div class="hng-single-product-layout <?php echo esc_attr($layout_class); ?>" data-layout="<?php echo esc_attr($layout); ?>" data-product-type="<?php echo esc_attr($product_type); ?>">
            <?php if ($settings['show_gallery'] === 'yes') : ?>
                <div class="hng-product-gallery">
                    <?php
                    $image_id = get_post_thumbnail_id($product_id);
                    if ($image_id) {
                        echo wp_get_attachment_image($image_id, 'full');
                    } else {
                        echo '<img src="' . esc_url(HNG_COMMERCE_URL . 'assets/images/placeholder.svg') . '" alt="' . esc_attr($product->get_name()) . '" />';
                    }
                    ?>
                </div>
            <?php endif; ?>

            <div class="hng-product-info">
                <?php if ($is_edit_mode) : ?>
                    <div style="margin-bottom: 10px;">
                        <?php 
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Badge HTML is safely generated by HNG_Elementor_Helpers with proper escaping
                        echo HNG_Elementor_Helpers::render_product_type_badge($product_type); 
                        ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($settings['show_title'] === 'yes') : ?>
                    <h1 class="hng-product-title"><?php echo esc_html($product->get_name()); ?></h1>
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

                <?php if ($settings['show_description'] === 'yes') : ?>
                    <div class="hng-product-description">
                        <?php echo wp_kses_post(wpautop($product->get_description())); ?>
                    </div>
                <?php endif; ?>

                <?php if ($settings['show_add_to_cart'] === 'yes') : ?>
                    <?php $is_quote = (method_exists($product, 'get_product_type') && $product->get_product_type() === 'quote'); ?>
                    <button class="hng-add-to-cart-button" data-product-id="<?php echo esc_attr($product_id); ?>" data-is-quote="<?php echo $is_quote ? 'yes' : 'no'; ?>">
                        <?php echo $is_quote ? esc_html__('Solicitar Orçamento', 'hng-commerce') : esc_html__('Adicionar ao Carrinho', 'hng-commerce'); ?>
                    </button>
                <?php endif; ?>

                <?php if ($settings['show_meta'] === 'yes') : ?>
                    <div class="hng-product-meta">
                        <?php
                        $sku = $product->get_sku();
                        if ($sku) {
                            echo '<span class="sku"><strong>' . esc_html__('SKU:', 'hng-commerce') . '</strong> ' . esc_html($sku) . '</span>';
                        }
                        
                        $categories = get_the_terms($product_id, 'hng_product_category');
                        if ($categories && !is_wp_error($categories)) {
                            echo '<span class="categories"><strong>' . esc_html__('Categorias:', 'hng-commerce') . '</strong> ';
                            $cat_names = array_map(function($cat) { return $cat->name; }, $categories);
                            echo esc_html(implode(', ', $cat_names));
                            echo '</span>';
                        }
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <style>
            {{WRAPPER}} .hng-single-product-vertical {
                display: flex;
                flex-direction: column;
                align-items: flex-start;
            }
            {{WRAPPER}} .hng-single-product-horizontal {
                display: flex;
                flex-direction: row;
                flex-wrap: wrap;
                align-items: flex-start;
                gap: 30px;
            }
            {{WRAPPER}} .hng-single-product-carousel .hng-product-gallery {
                position: relative;
            }
            {{WRAPPER}} .hng-product-gallery {
                flex: 0 0 auto;
            }
            {{WRAPPER}} .hng-product-gallery img {
                width: 100%;
                height: auto;
                object-fit: cover;
            }
            {{WRAPPER}} .hng-product-info {
                flex: 1;
                min-width: 300px;
            }
            {{WRAPPER}} .hng-add-to-cart-button {
                cursor: pointer;
                border: none;
                transition: all 0.3s ease;
                width: 100%;
                max-width: 300px;
            }
            {{WRAPPER}} .hng-product-meta {
                margin-top: 20px;
                display: flex;
                flex-direction: column;
                gap: 5px;
            }
            @media (max-width: 768px) {
                {{WRAPPER}} .hng-single-product-layout {
                    flex-direction: column !important;
                }
                {{WRAPPER}} .hng-product-gallery {
                    width: 100% !important;
                }
            }
        </style>

        <script>
        jQuery(document).ready(function($) {
            $('.hng-add-to-cart-button').on('click', function(e) {
                e.preventDefault();
                var $button = $(this);
                var productId = $button.data('product-id');
                var isQuote = ($button.data('is-quote') === 'yes');
                var isLogged = <?php echo is_user_logged_in() ? 'true' : 'false'; ?>;
                var checkoutUrl = '<?php echo esc_url(hng_get_checkout_url()); ?>';
                var loginUrl = '<?php echo esc_url(wp_login_url(hng_get_checkout_url())); ?>';
                
                $button.prop('disabled', true).text(isQuote ? '<?php esc_html_e('Solicitando...', 'hng-commerce'); ?>' : '<?php esc_html_e('Adicionando...', 'hng-commerce'); ?>');
                
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
                            if (isQuote) {
                                // Para orçamento: exigir login e ir direto ao checkout para enviar os dados
                                if (!isLogged) {
                                    window.location.href = loginUrl;
                                    return;
                                }
                                window.location.href = checkoutUrl;
                                return;
                            }
                            $button.text('<?php esc_html_e('Adicionado!', 'hng-commerce'); ?>');
                            setTimeout(function() {
                                $button.prop('disabled', false).text('<?php esc_html_e('Adicionar ao Carrinho', 'hng-commerce'); ?>');
                            }, 2000);
                            $(document.body).trigger('hng_cart_updated');
                        } else {
                            alert(response.data.message || (isQuote ? '<?php esc_html_e('Erro ao solicitar orçamento', 'hng-commerce'); ?>' : '<?php esc_html_e('Erro ao adicionar ao carrinho', 'hng-commerce'); ?>'));
                            $button.prop('disabled', false).text(isQuote ? '<?php esc_html_e('Solicitar Orçamento', 'hng-commerce'); ?>' : '<?php esc_html_e('Adicionar ao Carrinho', 'hng-commerce'); ?>');
                        }
                    },
                    error: function() {
                        alert(isQuote ? '<?php esc_html_e('Erro ao solicitar orçamento', 'hng-commerce'); ?>' : '<?php esc_html_e('Erro ao adicionar ao carrinho', 'hng-commerce'); ?>');
                        $button.prop('disabled', false).text(isQuote ? '<?php esc_html_e('Solicitar Orçamento', 'hng-commerce'); ?>' : '<?php esc_html_e('Adicionar ao Carrinho', 'hng-commerce'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }
}
