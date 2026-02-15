<?php
if (!defined('ABSPATH')) { exit; }

class HNG_Widget_Coupon_Form extends HNG_Commerce_Elementor_Widget_Base {
    public function get_name() { return 'hng_coupon_form'; }
    public function get_title() { return __('Formulário de Cupom', 'hng-commerce'); }
    public function get_icon() { return 'eicon-price-table'; }

    protected function register_controls() {
                // Títulos, descrições e placeholders editáveis
                $this->start_controls_section(
                    'labels_section',
                    [
                        'label' => __('Textos e Placeholders', 'hng-commerce'),
                        'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
                    ]
                );
                $this->add_control('label_coupon_title', [ 'label' => __('Título do Formulário', 'hng-commerce'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Cupom de Desconto', 'hng-commerce') ]);
                $this->add_control('label_success', [ 'label' => __('Mensagem Sucesso', 'hng-commerce'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Cupom aplicado com sucesso!', 'hng-commerce') ]);
                $this->add_control('label_error', [ 'label' => __('Mensagem Erro', 'hng-commerce'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Cupom inválido ou expirado.', 'hng-commerce') ]);
                $this->end_controls_section();

                // Variação de layout (inline, barra, modal)
                $this->start_controls_section(
                    'layout_section',
                    [
                        'label' => __('Layout', 'hng-commerce'),
                        'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
                    ]
                );
                $this->add_control('coupon_form_layout', [
                    'label' => __('Layout do Formulário', 'hng-commerce'),
                    'type' => \Elementor\Controls_Manager::SELECT,
                    'options' => [
                        'inline' => __('Inline', 'hng-commerce'),
                        'bar' => __('Barra', 'hng-commerce'),
                        'modal' => __('Modal', 'hng-commerce'),
                    ],
                    'default' => 'inline',
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
            'placeholder',
            [
                'label' => __('Placeholder', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Código do cupom', 'hng-commerce'),
            ]
        );

        $this->add_control(
            'button_text',
            [
                'label' => __('Texto do Botão', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Aplicar', 'hng-commerce'),
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
                        'max' => 50,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 10,
                ],
                'selectors' => [
                    '{{WRAPPER}} .hng-coupon-form-container' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Input Style
        $this->start_controls_section(
            'input_style_section',
            [
                'label' => __('Campo de Entrada', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'input_background',
            [
                'label' => __('Cor de Fundo', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-coupon-input' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'input_color',
            [
                'label' => __('Cor do Texto', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-coupon-input' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'input_border',
                'selector' => '{{WRAPPER}} .hng-coupon-input',
            ]
        );

        $this->add_responsive_control(
            'input_border_radius',
            [
                'label' => __('Border Radius', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .hng-coupon-input' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
                    '{{WRAPPER}} .hng-coupon-input' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
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
                    '{{WRAPPER}} .hng-coupon-button' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_background',
            [
                'label' => __('Cor de Fundo', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-coupon-button' => 'background-color: {{VALUE}};',
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
                    '{{WRAPPER}} .hng-coupon-button:hover' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_hover_background',
            [
                'label' => __('Cor de Fundo', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-coupon-button:hover' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'button_typography',
                'selector' => '{{WRAPPER}} .hng-coupon-button',
                'separator' => 'before',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'button_border',
                'selector' => '{{WRAPPER}} .hng-coupon-button',
            ]
        );

        $this->add_responsive_control(
            'button_border_radius',
            [
                'label' => __('Border Radius', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .hng-coupon-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
                    '{{WRAPPER}} .hng-coupon-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Message Style
        $this->start_controls_section(
            'message_style_section',
            [
                'label' => __('Mensagens', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'success_color',
            [
                'label' => __('Cor de Sucesso', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-coupon-success' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'error_color',
            [
                'label' => __('Cor de Erro', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-coupon-error' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        ?>
        <div class="hng-coupon-form-container">
            <input type="text" 
                   class="hng-coupon-input" 
                   placeholder="<?php echo esc_attr($settings['placeholder']); ?>" />
            <button type="button" class="hng-coupon-button">
                <?php echo esc_html($settings['button_text']); ?>
            </button>
            <div class="hng-coupon-message"></div>
        </div>

        <style>
            {{WRAPPER}} .hng-coupon-form-container {
                display: flex;
                align-items: center;
                flex-wrap: wrap;
            }
            {{WRAPPER}} .hng-coupon-input {
                flex: 1;
                min-width: 200px;
            }
            {{WRAPPER}} .hng-coupon-button {
                cursor: pointer;
                border: none;
                transition: all 0.3s ease;
                white-space: nowrap;
            }
            {{WRAPPER}} .hng-coupon-message {
                width: 100%;
                margin-top: 10px;
            }
        </style>

        <script>
        jQuery(document).ready(function($) {
            $('.hng-coupon-button').on('click', function() {
                var $button = $(this);
                var $input = $button.siblings('.hng-coupon-input');
                var $message = $button.siblings('.hng-coupon-message');
                var couponCode = $input.val();

                if (!couponCode) {
                    $message.html('<span class="hng-coupon-error"><?php esc_html_e('Digite um código de cupom', 'hng-commerce'); ?></span>');
                    return;
                }

                $button.prop('disabled', true).text('<?php esc_html_e('Aplicando...', 'hng-commerce'); ?>');

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
                            $message.html('<span class="hng-coupon-success">' + response.data.message + '</span>');
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            $message.html('<span class="hng-coupon-error">' + response.data.message + '</span>');
                            $button.prop('disabled', false).text('<?php echo esc_js($settings['button_text']); ?>');
                        }
                    },
                    error: function() {
                        $message.html('<span class="hng-coupon-error"><?php esc_html_e('Erro ao aplicar cupom', 'hng-commerce'); ?></span>');
                        $button.prop('disabled', false).text('<?php echo esc_js($settings['button_text']); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }
}
