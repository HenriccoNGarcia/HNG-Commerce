<?php
if (!defined('ABSPATH')) { exit; }

class HNG_Widget_Quote_Request extends HNG_Commerce_Elementor_Widget_Base {
    public function get_name() { return 'hng_quote_request'; }
    public function get_title() { return __('Solicitar Orçamento', 'hng-commerce'); }
    public function get_icon() { return 'eicon-document-file'; }

    protected function register_controls() {
        // Textos customizáveis
        $this->start_controls_section(
            'labels_section',
            [
                'label' => __('Textos', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );
        $this->add_control('label_title', [
            'label' => __('Título do Formulário', 'hng-commerce'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => __('Solicitar Orçamento', 'hng-commerce')
        ]);
        $this->add_control('label_submit', [
            'label' => __('Texto do Botão', 'hng-commerce'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => __('Solicitar Orçamento', 'hng-commerce')
        ]);
        $this->add_control('label_submitting', [
            'label' => __('Texto ao Enviar', 'hng-commerce'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => __('Processando...', 'hng-commerce')
        ]);
        $this->add_control('label_success', [
            'label' => __('Mensagem de Sucesso', 'hng-commerce'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => __('Orçamento solicitado! Você será redirecionado para o checkout...', 'hng-commerce')
        ]);
        $this->end_controls_section();

        // Configurações do Produto
        $this->start_controls_section(
            'product_section',
            [
                'label' => __('Produto', 'hng-commerce'),
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
                    'auto_quote' => __('Primeiro Produto de Orçamento', 'hng-commerce'),
                    'any' => __('Qualquer Produto Disponível', 'hng-commerce'),
                ],
                'default' => 'auto_quote',
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
            'show_product_title',
            [
                'label' => __('Mostrar Título do Produto', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Sim', 'hng-commerce'),
                'label_off' => __('Não', 'hng-commerce'),
                'return_value' => 'yes',
                'default' => 'yes',
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
        $this->end_controls_section();

        // Configurações de Campos
        $this->start_controls_section(
            'fields_section',
            [
                'label' => __('Campos Personalizados', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );
        $this->add_control(
            'custom_fields',
            [
                'label' => __('Campos a Coletar (um por linha)', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::TEXTAREA,
                'default' => __('Medidas\nMateriais\nPrazo de Entrega\nObservações', 'hng-commerce'),
                'description' => __('Digite cada campo em uma nova linha. Estes campos serão exibidos no formulário de orçamento.', 'hng-commerce'),
            ]
        );
        $this->add_control(
            'require_login',
            [
                'label' => __('Exigir Login para Solicitar', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Sim', 'hng-commerce'),
                'label_off' => __('Não', 'hng-commerce'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );
        $this->end_controls_section();

        // Estilos - Container
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
                'default' => '#f9f9f9',
                'selectors' => [
                    '{{WRAPPER}} .hng-quote-request-container' => 'background-color: {{VALUE}};',
                ],
            ]
        );
        $this->add_responsive_control(
            'container_padding',
            [
                'label' => __('Padding', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'default' => ['top' => '30', 'right' => '30', 'bottom' => '30', 'left' => '30', 'unit' => 'px'],
                'selectors' => [
                    '{{WRAPPER}} .hng-quote-request-container' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'container_border',
                'selector' => '{{WRAPPER}} .hng-quote-request-container',
            ]
        );
        $this->end_controls_section();

        // Estilos - Campos
        $this->start_controls_section(
            'fields_style_section',
            [
                'label' => __('Campos', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );
        $this->add_responsive_control(
            'field_spacing',
            [
                'label' => __('Espaçamento entre Campos', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => ['px' => ['min' => 0, 'max' => 50]],
                'default' => ['unit' => 'px', 'size' => 15],
                'selectors' => [
                    '{{WRAPPER}} .hng-quote-request-field' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
        $this->add_control(
            'field_background',
            [
                'label' => __('Cor de Fundo dos Campos', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .hng-quote-request-field input' => 'background-color: {{VALUE}};',
                    '{{WRAPPER}} .hng-quote-request-field textarea' => 'background-color: {{VALUE}};',
                ],
            ]
        );
        $this->add_control(
            'field_border_color',
            [
                'label' => __('Cor da Borda dos Campos', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ddd',
                'selectors' => [
                    '{{WRAPPER}} .hng-quote-request-field input' => 'border-color: {{VALUE}};',
                    '{{WRAPPER}} .hng-quote-request-field textarea' => 'border-color: {{VALUE}};',
                ],
            ]
        );
        $this->end_controls_section();

        // Estilos - Botão
        $this->start_controls_section(
            'button_style_section',
            [
                'label' => __('Botão', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );
        $this->add_control(
            'button_background',
            [
                'label' => __('Cor de Fundo', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#2271b1',
                'selectors' => [
                    '{{WRAPPER}} .hng-quote-request-submit' => 'background-color: {{VALUE}};',
                ],
            ]
        );
        $this->add_control(
            'button_text_color',
            [
                'label' => __('Cor do Texto', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .hng-quote-request-submit' => 'color: {{VALUE}};',
                ],
            ]
        );
        $this->add_responsive_control(
            'button_padding',
            [
                'label' => __('Padding', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px'],
                'default' => ['top' => '12', 'right' => '30', 'bottom' => '12', 'left' => '30', 'unit' => 'px'],
                'selectors' => [
                    '{{WRAPPER}} .hng-quote-request-submit' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $require_login = ($settings['require_login'] ?? 'yes') === 'yes';
        $custom_fields = array_filter(array_map('trim', explode("\n", $settings['custom_fields'] ?? '')));
        $is_edit_mode = $this->is_edit_mode();
        $product_source = isset($settings['product_source']) ? $settings['product_source'] : 'auto_quote';
        $product_id = 0;
        $product = null;

        // Determinar o produto baseado na origem selecionada
        switch ($product_source) {
            case 'specific':
                $product_id = absint($settings['product_id'] ?? 0);
                if ($product_id > 0) {
                    $product = new HNG_Product($product_id);
                }
                break;
                
            case 'auto_quote':
                // Buscar primeiro produto de orçamento
                $quote_products = get_posts([
                    'post_type' => 'hng_product',
                    'numberposts' => 1,
                    'post_status' => 'publish',
                    'meta_query' => [
                        'relation' => 'OR',
                        ['key' => '_hng_product_type', 'value' => 'quote'],
                        ['key' => '_product_type', 'value' => 'quote'],
                    ]
                ]);
                if (!empty($quote_products)) {
                    $product_id = $quote_products[0]->ID;
                    $product = new HNG_Product($product_id);
                }
                break;
                
            case 'any':
                // Qualquer produto disponível
                $any_products = get_posts([
                    'post_type' => 'hng_product',
                    'numberposts' => 1,
                    'post_status' => 'publish'
                ]);
                if (!empty($any_products)) {
                    $product_id = $any_products[0]->ID;
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
                    // No modo de edição, buscar produto de orçamento
                    $quote_products = get_posts([
                        'post_type' => 'hng_product',
                        'numberposts' => 1,
                        'post_status' => 'publish',
                        'meta_query' => [
                            'relation' => 'OR',
                            ['key' => '_hng_product_type', 'value' => 'quote'],
                            ['key' => '_product_type', 'value' => 'quote'],
                        ]
                    ]);
                    if (!empty($quote_products)) {
                        $product_id = $quote_products[0]->ID;
                        $product = new HNG_Product($product_id);
                    } else {
                        // Fallback para qualquer produto
                        $any_products = get_posts([
                            'post_type' => 'hng_product',
                            'numberposts' => 1,
                            'post_status' => 'publish'
                        ]);
                        if (!empty($any_products)) {
                            $product_id = $any_products[0]->ID;
                            $product = new HNG_Product($product_id);
                        }
                    }
                }
                break;
        }

        if (!$product || !$product->get_id()) {
            echo '<div class="hng-quote-request-placeholder elementor-alert elementor-alert-warning">';
            if ($is_edit_mode) {
                echo '<p><strong>' . esc_html__('Widget Solicitar Orçamento', 'hng-commerce') . '</strong></p>';
                echo '<p>' . esc_html__('Nenhum produto encontrado. Opções:', 'hng-commerce') . '</p>';
                echo '<ul style="margin: 10px 0; padding-left: 20px;">';
                echo '<li>' . esc_html__('Selecione "Produto Específico" e insira um ID válido', 'hng-commerce') . '</li>';
                echo '<li>' . esc_html__('Selecione "Primeiro Produto de Orçamento" para buscar automaticamente', 'hng-commerce') . '</li>';
                echo '<li>' . esc_html__('Crie um produto do tipo "Orçamento" no HNG Commerce', 'hng-commerce') . '</li>';
                echo '</ul>';
            } else {
                echo '<p>' . esc_html__('Nenhum produto de orçamento selecionado.', 'hng-commerce') . '</p>';
            }
            echo '</div>';
            return;
        }

        // Verificar tipo de produto
        $product_type = method_exists($product, 'get_product_type') ? $product->get_product_type() : '';
        
        // Aviso no modo de edição se não for do tipo orçamento
        if ($is_edit_mode && $product_type !== 'quote') {
            echo '<div class="elementor-alert elementor-alert-info" style="margin-bottom: 15px;">';
            echo '<p style="margin: 0;"><strong>' . esc_html__('Aviso:', 'hng-commerce') . '</strong> ' . esc_html__('Este produto não é do tipo "Orçamento". Considere criar ou selecionar um produto de orçamento.', 'hng-commerce') . '</p>';
            echo '</div>';
        } elseif ($product_type !== 'quote' && !$is_edit_mode) {
            echo '<div class="hng-quote-request-placeholder elementor-alert elementor-alert-info">';
            echo '<p>' . esc_html__('Este produto não é do tipo "Orçamento".', 'hng-commerce') . '</p>';
            echo '</div>';
            return;
        }

        // Se requer login e usuário não está logado, mostrar aviso
        if ($require_login && !is_user_logged_in() && !$is_edit_mode) {
            echo '<div class="hng-quote-request-login-required">';
            echo '<p>' . esc_html__('Você precisa estar logado para solicitar um orçamento.', 'hng-commerce') . '</p>';
            echo '<a href="' . esc_url(wp_login_url(get_permalink())) . '" class="button button-primary">';
            echo esc_html__('Fazer Login', 'hng-commerce');
            echo '</a>';
            echo '</div>';
            return;
        }

        ?>
        <div class="hng-quote-request-container">
            <?php if ($settings['show_product_image'] === 'yes') : ?>
                <div class="hng-quote-request-image">
                    <?php
                    $image_id = get_post_thumbnail_id($product_id);
                    if ($image_id) {
                        echo wp_get_attachment_image($image_id, 'medium');
                    } else {
                        echo '<img src="' . esc_url(HNG_COMMERCE_URL . 'assets/images/placeholder.svg') . '" alt="' . esc_attr($product->get_name()) . '" />';
                    }
                    ?>
                </div>
            <?php endif; ?>

            <div class="hng-quote-request-content">
                <?php if ($settings['show_product_title'] === 'yes') : ?>
                    <h3 class="hng-quote-request-title"><?php echo esc_html($product->get_name()); ?></h3>
                <?php endif; ?>

                <h4 class="hng-quote-request-form-title"><?php echo esc_html($settings['label_title']); ?></h4>

                <form class="hng-quote-request-form" data-product-id="<?php echo esc_attr($product_id); ?>">
                    <?php wp_nonce_field('hng_quote_request', 'hng_quote_nonce'); ?>

                    <?php foreach ($custom_fields as $field_name) :
                        $field_slug = sanitize_key($field_name);
                    ?>
                        <div class="hng-quote-request-field">
                            <label for="hng-field-<?php echo esc_attr($field_slug); ?>">
                                <?php echo esc_html($field_name); ?>
                            </label>
                            <?php if ($field_name === 'Observações' || $field_name === 'Observations') : ?>
                                <textarea
                                    id="hng-field-<?php echo esc_attr($field_slug); ?>"
                                    name="hng_cf[<?php echo esc_attr($field_slug); ?>]"
                                    placeholder="<?php echo esc_attr($field_name); ?>"
                                    rows="4"
                                    style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-family: inherit;"></textarea>
                            <?php else : ?>
                                <input
                                    type="text"
                                    id="hng-field-<?php echo esc_attr($field_slug); ?>"
                                    name="hng_cf[<?php echo esc_attr($field_slug); ?>]"
                                    placeholder="<?php echo esc_attr($field_name); ?>"
                                    style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;" />
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <div id="hng-quote-request-message" style="margin-top: 15px;"></div>

                    <button type="submit" class="hng-quote-request-submit" style="width: 100%; border: none; cursor: pointer; border-radius: 4px; font-weight: bold; transition: all 0.3s ease;">
                        <?php echo esc_html($settings['label_submit']); ?>
                    </button>
                </form>
            </div>
        </div>

        <style>
            .elementor-element-<?php echo esc_attr($this->get_id()); ?> .hng-quote-request-placeholder {
                padding: 20px;
                background: #f0f0f0;
                border-radius: 4px;
                text-align: center;
                color: #666;
            }
            .elementor-element-<?php echo esc_attr($this->get_id()); ?> .hng-quote-request-login-required {
                padding: 30px;
                text-align: center;
                background: #fff3cd;
                border: 1px solid #ffeaa7;
                border-radius: 4px;
            }
            .elementor-element-<?php echo esc_attr($this->get_id()); ?> .hng-quote-request-container {
                display: flex;
                gap: 30px;
                align-items: flex-start;
            }
            .elementor-element-<?php echo esc_attr($this->get_id()); ?> .hng-quote-request-image {
                flex: 0 0 250px;
            }
            .elementor-element-<?php echo esc_attr($this->get_id()); ?> .hng-quote-request-image img {
                width: 100%;
                height: auto;
                border-radius: 4px;
            }
            .elementor-element-<?php echo esc_attr($this->get_id()); ?> .hng-quote-request-content {
                flex: 1;
                min-width: 300px;
            }
            .elementor-element-<?php echo esc_attr($this->get_id()); ?> .hng-quote-request-title {
                margin: 0 0 10px 0;
                font-size: 18px;
                font-weight: 600;
            }
            .elementor-element-<?php echo esc_attr($this->get_id()); ?> .hng-quote-request-form-title {
                margin: 20px 0 15px 0;
                font-size: 16px;
                font-weight: 600;
                border-top: 1px solid #eee;
                padding-top: 15px;
            }
            .elementor-element-<?php echo esc_attr($this->get_id()); ?> .hng-quote-request-field label {
                display: block;
                margin-bottom: 5px;
                font-weight: 500;
                font-size: 14px;
            }
            @media (max-width: 768px) {
                .elementor-element-<?php echo esc_attr($this->get_id()); ?> .hng-quote-request-container {
                    flex-direction: column;
                }
                .elementor-element-<?php echo esc_attr($this->get_id()); ?> .hng-quote-request-image {
                    flex: 0 0 auto;
                    width: 100%;
                }
            }
        </style>

        <script>
        jQuery(document).ready(function($) {
            $('.hng-quote-request-form').on('submit', function(e) {
                e.preventDefault();
                var $form = $(this);
                var $button = $form.find('.hng-quote-request-submit');
                var productId = $form.data('product-id');
                var formData = new FormData(this);
                var isLogged = <?php echo is_user_logged_in() ? 'true' : 'false'; ?>;
                var checkoutUrl = '<?php echo esc_url(hng_get_checkout_url()); ?>';
                var loginUrl = '<?php echo esc_url(wp_login_url(hng_get_checkout_url())); ?>';

                formData.append('action', 'hng_add_to_cart');
                formData.append('product_id', productId);
                formData.append('quantity', 1);
                formData.append('nonce', '<?php echo esc_js(wp_create_nonce('hng-add-to-cart')); ?>');

                var originalText = $button.text();
                $button.prop('disabled', true).text('<?php echo esc_js($settings['label_submitting']); ?>');

                $.ajax({
                    url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            var $msg = $('#hng-quote-request-message');
                            $msg.html('<div style="color: #28a745; padding: 10px; background: #d4edda; border-radius: 4px;"><?php echo esc_js($settings['label_success']); ?></div>');
                            setTimeout(function() {
                                if (!isLogged) {
                                    window.location.href = loginUrl;
                                } else {
                                    window.location.href = checkoutUrl;
                                }
                            }, 1500);
                        } else {
                            var errorMsg = response.data?.message || '<?php echo esc_js('Erro ao solicitar orçamento'); ?>';
                            $('#hng-quote-request-message').html('<div style="color: #dc3545; padding: 10px; background: #f8d7da; border-radius: 4px;">' + errorMsg + '</div>');
                            $button.prop('disabled', false).text(originalText);
                        }
                    },
                    error: function() {
                        $('#hng-quote-request-message').html('<div style="color: #dc3545; padding: 10px; background: #f8d7da; border-radius: 4px;"><?php echo esc_js('Erro ao solicitar orçamento'); ?></div>');
                        $button.prop('disabled', false).text(originalText);
                    }
                });
            });
        });
        </script>
        <?php
    }
}
