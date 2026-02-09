<?php
if (!defined('ABSPATH')) { exit; }

class HNG_Widget_Product_Search extends HNG_Commerce_Elementor_Widget_Base {
    public function get_name() { return 'hng_product_search'; }
    public function get_title() { return __('Busca de Produtos', 'hng-commerce'); }
    public function get_icon() { return 'eicon-search'; }

    protected function register_controls() {
                // Títulos, descrições e placeholders editáveis
                $this->start_controls_section(
                    'labels_section',
                    [
                        'label' => __('Textos e Placeholders', 'hng-commerce'),
                        'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
                    ]
                );
                $this->add_control('label_search_title', [ 'label' => __('Título da Busca', 'hng-commerce'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Buscar Produtos', 'hng-commerce') ]);
                $this->add_control('label_no_results', [ 'label' => __('Mensagem Sem Resultados', 'hng-commerce'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Nenhum produto encontrado.', 'hng-commerce') ]);
                $this->add_control('placeholder_search', [ 'label' => __('Placeholder Busca', 'hng-commerce'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Buscar produtos...', 'hng-commerce') ]);
                $this->end_controls_section();

                // Variação de layout (inline, barra, modal)
                $this->start_controls_section(
                    'layout_section',
                    [
                        'label' => __('Layout', 'hng-commerce'),
                        'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
                    ]
                );
                $this->add_control('product_search_layout', [
                    'label' => __('Layout da Busca', 'hng-commerce'),
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
                'default' => __('Buscar produtos...', 'hng-commerce'),
            ]
        );

        $this->add_control(
            'show_suggestions',
            [
                'label' => __('Mostrar Sugestões', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Sim', 'hng-commerce'),
                'label_off' => __('Não', 'hng-commerce'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'min_characters',
            [
                'label' => __('Caracteres Mínimos', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 3,
                'min' => 1,
                'condition' => [
                    'show_suggestions' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'results_count',
            [
                'label' => __('Número de Sugestões', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 5,
                'min' => 1,
                'max' => 10,
                'condition' => [
                    'show_suggestions' => 'yes',
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

        $this->add_responsive_control(
            'container_width',
            [
                'label' => __('Largura', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['%', 'px'],
                'range' => [
                    '%' => [
                        'min' => 10,
                        'max' => 100,
                    ],
                    'px' => [
                        'min' => 200,
                        'max' => 1000,
                    ],
                ],
                'default' => [
                    'unit' => '%',
                    'size' => 100,
                ],
                'selectors' => [
                    '{{WRAPPER}} .hng-search-container' => 'width: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Input Style
        $this->start_controls_section(
            'input_style_section',
            [
                'label' => __('Campo de Busca', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'input_background',
            [
                'label' => __('Cor de Fundo', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-search-input' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'input_color',
            [
                'label' => __('Cor do Texto', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-search-input' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'input_border',
                'selector' => '{{WRAPPER}} .hng-search-input',
            ]
        );

        $this->add_responsive_control(
            'input_border_radius',
            [
                'label' => __('Border Radius', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .hng-search-input' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
                    '{{WRAPPER}} .hng-search-input' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'input_typography',
                'selector' => '{{WRAPPER}} .hng-search-input',
            ]
        );

        $this->end_controls_section();

        // Suggestions Style
        $this->start_controls_section(
            'suggestions_style_section',
            [
                'label' => __('Sugestões', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_suggestions' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'suggestions_background',
            [
                'label' => __('Cor de Fundo', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-search-suggestions' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'suggestion_item_hover',
            [
                'label' => __('Cor de Fundo (Hover)', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-search-suggestion:hover' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'suggestions_border',
                'selector' => '{{WRAPPER}} .hng-search-suggestions',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'suggestions_box_shadow',
                'selector' => '{{WRAPPER}} .hng-search-suggestions',
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $show_suggestions = $settings['show_suggestions'] === 'yes';
        $min_chars = intval($settings['min_characters']);
        $results_count = intval($settings['results_count']);
        ?>
        <div class="hng-search-container">
            <div class="hng-search-form">
                <input type="text" 
                       class="hng-search-input" 
                       placeholder="<?php echo esc_attr($settings['placeholder']); ?>"
                       data-show-suggestions="<?php echo esc_attr( $show_suggestions ? '1' : '0' ); ?>"
                       data-min-chars="<?php echo esc_attr($min_chars); ?>"
                       data-results-count="<?php echo esc_attr($results_count); ?>" />
                <button type="button" class="hng-search-button">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="m21 21-4.35-4.35"></path>
                    </svg>
                </button>
            </div>
            
            <?php if ($show_suggestions) : ?>
                <div class="hng-search-suggestions" style="display: none;"></div>
            <?php endif; ?>
        </div>

        <style>
            {{WRAPPER}} .hng-search-container {
                position: relative;
            }
            {{WRAPPER}} .hng-search-form {
                position: relative;
                display: flex;
                align-items: center;
            }
            {{WRAPPER}} .hng-search-input {
                width: 100%;
                padding-right: 50px;
            }
            {{WRAPPER}} .hng-search-button {
                position: absolute;
                right: 10px;
                background: none;
                border: none;
                cursor: pointer;
                padding: 5px;
                display: flex;
                align-items: center;
            }
            {{WRAPPER}} .hng-search-suggestions {
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                z-index: 1000;
                max-height: 400px;
                overflow-y: auto;
                margin-top: 5px;
            }
            {{WRAPPER}} .hng-search-suggestion {
                padding: 10px 15px;
                cursor: pointer;
                display: flex;
                align-items: center;
                gap: 10px;
                border-bottom: 1px solid #eee;
            }
            {{WRAPPER}} .hng-search-suggestion img {
                width: 50px;
                height: 50px;
                object-fit: cover;
            }
            {{WRAPPER}} .hng-search-suggestion-info {
                flex: 1;
            }
            {{WRAPPER}} .hng-search-suggestion-title {
                font-weight: 500;
                margin-bottom: 5px;
            }
            {{WRAPPER}} .hng-search-suggestion-price {
                font-size: 0.9em;
                opacity: 0.7;
            }
        </style>

        <script>
        jQuery(document).ready(function($) {
            var searchTimeout;
            var $input = $('.hng-search-input');
            var $suggestions = $('.hng-search-suggestions');
            var showSuggestions = $input.data('show-suggestions');
            var minChars = $input.data('min-chars');
            var resultsCount = $input.data('results-count');

            // Search on input
            $input.on('input', function() {
                var query = $(this).val();

                if (!showSuggestions) return;

                clearTimeout(searchTimeout);

                if (query.length < minChars) {
                    $suggestions.hide().empty();
                    return;
                }

                searchTimeout = setTimeout(function() {
                    $.ajax({
                        url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                        type: 'GET',
                        data: {
                            action: 'hng_search_products',
                            query: query,
                            limit: resultsCount
                        },
                        success: function(response) {
                            if (response.success && response.data.length > 0) {
                                var html = '';
                                response.data.forEach(function(product) {
                                    html += '<a href="' + product.url + '" class="hng-search-suggestion">';
                                    html += '<img src="' + product.image + '" alt="' + product.title + '" />';
                                    html += '<div class="hng-search-suggestion-info">';
                                    html += '<div class="hng-search-suggestion-title">' + product.title + '</div>';
                                    html += '<div class="hng-search-suggestion-price">' + product.price + '</div>';
                                    html += '</div>';
                                    html += '</a>';
                                });
                                $suggestions.html(html).show();
                            } else {
                                $suggestions.html('<div class="hng-search-suggestion"><?php esc_html_e('Nenhum produto encontrado', 'hng-commerce'); ?></div>').show();
                            }
                        }
                    });
                }, 300);
            });

            // Search on button click
            $('.hng-search-button').on('click', function() {
                var query = $input.val();
                if (query) {
                    window.location.href = '<?php echo esc_js(home_url('/produtos')); ?>?s=' + encodeURIComponent(query);
                }
            });

            // Search on Enter
            $input.on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    var query = $(this).val();
                    if (query) {
                        window.location.href = '<?php echo esc_js(home_url('/produtos')); ?>?s=' + encodeURIComponent(query);
                    }
                }
            });

            // Close suggestions on click outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.hng-search-container').length) {
                    $suggestions.hide();
                }
            });
        });
        </script>
        <?php
    }
}
