<?php
if (!defined('ABSPATH')) { exit; }

class HNG_Widget_Mini_Cart extends HNG_Commerce_Elementor_Widget_Base {
    
    public function get_name() { 
        return 'hng_mini_cart'; 
    }
    
    public function get_title() { 
        return __('Mini Carrinho', 'hng-commerce'); 
    }
    
    public function get_icon() { 
        return 'eicon-cart-light'; 
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
                $this->add_control('label_cart_title', [ 'label' => __('Título Mini Carrinho', 'hng-commerce'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Meu Carrinho', 'hng-commerce') ]);
                $this->add_control('label_count', [ 'label' => __('Texto Contagem', 'hng-commerce'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Itens', 'hng-commerce') ]);
                $this->add_control('label_total', [ 'label' => __('Texto Total', 'hng-commerce'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Total', 'hng-commerce') ]);
                $this->add_control('label_empty', [ 'label' => __('Mensagem Carrinho Vazio', 'hng-commerce'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Seu carrinho está vazio.', 'hng-commerce') ]);
                $this->end_controls_section();

                // Variação de layout (horizontal/vertical)
                $this->start_controls_section(
                    'layout_section',
                    [
                        'label' => __('Layout', 'hng-commerce'),
                        'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
                    ]
                );
                $this->add_control('mini_cart_layout', [
                    'label' => __('Layout do Mini Carrinho', 'hng-commerce'),
                    'type' => \Elementor\Controls_Manager::SELECT,
                    'options' => [
                        'horizontal' => __('Horizontal', 'hng-commerce'),
                        'vertical' => __('Vertical', 'hng-commerce'),
                    ],
                    'default' => 'horizontal',
                ]);
                $this->end_controls_section();
        
        // CONTEÚDO
        $this->start_controls_section(
            'section_content',
            [
                'label' => __('Configuração', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );
        
        $this->add_control(
            'show_count',
            [
                'label' => __('Mostrar Contagem', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );
        
        $this->add_control(
            'show_total',
            [
                'label' => __('Mostrar Total', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );
        
        $this->add_control(
            'cart_icon',
            [
                'label' => __('Ícone', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::ICONS,
                'default' => [
                    'value' => 'fas fa-shopping-cart',
                    'library' => 'fa-solid',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // ESTILOS - CONTAINER
        $this->start_controls_section(
            'section_container_style',
            [
                'label' => __('Container', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );
        
        $this->add_control(
            'container_background',
            [
                'label' => __('Background', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#3498db',
                'selectors' => [
                    '{{WRAPPER}} .hng-mini-cart-container' => 'background-color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'container_border',
                'selector' => '{{WRAPPER}} .hng-mini-cart-container',
            ]
        );
        
        $this->add_responsive_control(
            'container_border_radius',
            [
                'label' => __('Raio da Borda', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'default' => ['top' => 50, 'right' => 50, 'bottom' => 50, 'left' => 50, 'unit' => 'px'],
                'selectors' => [
                    '{{WRAPPER}} .hng-mini-cart-container' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_responsive_control(
            'container_padding',
            [
                'label' => __('Padding', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'default' => ['top' => 15, 'right' => 20, 'bottom' => 15, 'left' => 20, 'unit' => 'px'],
                'selectors' => [
                    '{{WRAPPER}} .hng-mini-cart-container' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'container_shadow',
                'selector' => '{{WRAPPER}} .hng-mini-cart-container',
            ]
        );
        
        // Hover
        $this->add_control(
            'container_hover_heading',
            [
                'label' => __('Hover', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );
        
        $this->add_control(
            'container_hover_background',
            [
                'label' => __('Background (Hover)', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#2980b9',
                'selectors' => [
                    '{{WRAPPER}} .hng-mini-cart-container:hover' => 'background-color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'container_transition',
            [
                'label' => __('Transição (ms)', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 300,
                'selectors' => [
                    '{{WRAPPER}} .hng-mini-cart-container' => 'transition: all {{VALUE}}ms ease;',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // ESTILOS - ÍCONE
        $this->start_controls_section(
            'section_icon_style',
            [
                'label' => __('Ícone', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );
        
        $this->add_control(
            'icon_color',
            [
                'label' => __('Cor', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .hng-mini-cart-icon' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .hng-mini-cart-icon svg' => 'fill: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_responsive_control(
            'icon_size',
            [
                'label' => __('Tamanho', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => ['min' => 10, 'max' => 100],
                ],
                'default' => ['size' => 24, 'unit' => 'px'],
                'selectors' => [
                    '{{WRAPPER}} .hng-mini-cart-icon' => 'font-size: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .hng-mini-cart-icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // ESTILOS - BADGE (CONTADOR)
        $this->start_controls_section(
            'section_badge_style',
            [
                'label' => __('Badge (Contador)', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => ['show_count' => 'yes'],
            ]
        );
        
        $this->add_control(
            'badge_color',
            [
                'label' => __('Cor do Texto', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .hng-mini-cart-count' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'badge_background',
            [
                'label' => __('Background', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#e74c3c',
                'selectors' => [
                    '{{WRAPPER}} .hng-mini-cart-count' => 'background-color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'badge_typography',
                'selector' => '{{WRAPPER}} .hng-mini-cart-count',
            ]
        );
        
        $this->add_responsive_control(
            'badge_size',
            [
                'label' => __('Tamanho', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => ['min' => 15, 'max' => 50],
                ],
                'default' => ['size' => 20, 'unit' => 'px'],
                'selectors' => [
                    '{{WRAPPER}} .hng-mini-cart-count' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}; line-height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // ESTILOS - TEXTO (TOTAL)
        $this->start_controls_section(
            'section_text_style',
            [
                'label' => __('Texto (Total)', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => ['show_total' => 'yes'],
            ]
        );
        
        $this->add_control(
            'text_color',
            [
                'label' => __('Cor', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .hng-mini-cart-total' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'text_typography',
                'selector' => '{{WRAPPER}} .hng-mini-cart-total',
            ]
        );
        
        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $layout = $settings['mini_cart_layout'] ?? 'horizontal';
        $is_edit_mode = $this->is_edit_mode();
        
        // Inicializar variáveis
        $cart_count = 0;
        $cart_total = 0;
        $use_simulated = false;
        
        // Buscar carrinho da sessão
        if (class_exists('HNG_Cart')) {
            $cart = HNG_Cart::instance();
            $cart_count = $cart->get_cart_count();
            $cart_total = $cart->get_cart_total();
        }
        
        // Modo de edição: sempre simular dados para preview se carrinho vazio
        if ($is_edit_mode && $cart_count === 0) {
            $use_simulated = true;
            $cart_count = 3;
            $cart_total = 299.99;
        }
        
        $layout_class = 'hng-mini-cart-' . $layout;
        ?>
        <?php if ($is_edit_mode && $use_simulated) : ?>
        <div class="elementor-alert elementor-alert-info" style="font-size: 11px; padding: 5px 10px; margin-bottom: 5px;">
            <?php esc_html_e('Prévia (3 itens simulados)', 'hng-commerce'); ?>
        </div>
        <?php endif; ?>
        <div class="hng-mini-cart-container <?php echo esc_attr($layout_class); ?>" data-layout="<?php echo esc_attr($layout); ?>">
            <div class="hng-mini-cart-icon" style="position: relative; display: flex; align-items: center;">
                <?php \Elementor\Icons_Manager::render_icon($settings['cart_icon'], ['aria-hidden' => 'true']); ?>
                
                <?php if ($settings['show_count'] === 'yes' && $cart_count > 0) : ?>
                    <span class="hng-mini-cart-count">
                        <?php echo absint($cart_count); ?>
                    </span>
                <?php endif; ?>
            </div>
            
            <?php if ($settings['show_total'] === 'yes') : ?>
                <div class="hng-mini-cart-total" style="font-weight: 600;">
                    <?php echo esc_html(hng_price($cart_total)); ?>
                </div>
            <?php endif; ?>
        </div>
        
        <style>
            {{WRAPPER}} .hng-mini-cart-horizontal {
                display: inline-flex;
                align-items: center;
                gap: 10px;
                cursor: pointer;
                position: relative;
            }
            {{WRAPPER}} .hng-mini-cart-vertical {
                display: inline-flex;
                flex-direction: column;
                align-items: center;
                gap: 5px;
                cursor: pointer;
                position: relative;
            }
            {{WRAPPER}} .hng-mini-cart-count {
                position: absolute;
                top: -8px;
                right: -8px;
                border-radius: 50%;
                font-size: 11px;
                font-weight: bold;
                display: flex;
                align-items: center;
                justify-content: center;
                min-width: 18px;
                height: 18px;
                padding: 0 4px;
            }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('.hng-mini-cart-container').on('click', function() {
                window.location.href = '<?php echo esc_url(home_url('/carrinho')); ?>';
            });
        });
        </script>
        <?php
    }
}
