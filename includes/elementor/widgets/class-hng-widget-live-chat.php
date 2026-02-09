<?php
/**
 * HNG Commerce - Elementor Live Chat Widget
 * 
 * Widget para configurar e exibir o botão de chat ao vivo
 * 
 * @package HNG_Commerce
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Widget_Live_Chat extends \Elementor\Widget_Base {
    
    /**
     * Get widget name
     */
    public function get_name() {
        return 'hng-live-chat';
    }
    
    /**
     * Get widget title
     */
    public function get_title() {
        return esc_html__('Chat ao Vivo', 'hng-commerce');
    }
    
    /**
     * Get widget icon
     */
    public function get_icon() {
        return 'eicon-comments';
    }
    
    /**
     * Get widget categories
     */
    public function get_categories() {
        return ['hng-commerce'];
    }
    
    /**
     * Get widget keywords
     */
    public function get_keywords() {
        return ['chat', 'live', 'support', 'atendimento', 'suporte', 'hng'];
    }
    
    /**
     * Register widget controls
     */
    protected function register_controls() {
        // Content Section
        $this->start_controls_section(
            'section_content',
            [
                'label' => esc_html__('Conteúdo', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );
        
        $this->add_control(
            'widget_type',
            [
                'label' => esc_html__('Tipo de Widget', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'button',
                'options' => [
                    'button' => esc_html__('Botão', 'hng-commerce'),
                    'inline' => esc_html__('Inline (Embutido)', 'hng-commerce'),
                    'floating' => esc_html__('Flutuante (Configura o global)', 'hng-commerce'),
                ],
            ]
        );
        
        $this->add_control(
            'button_text',
            [
                'label' => esc_html__('Texto do Botão', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__('Iniciar Chat', 'hng-commerce'),
                'condition' => [
                    'widget_type' => 'button',
                ],
            ]
        );
        
        $this->add_control(
            'show_icon',
            [
                'label' => esc_html__('Mostrar Ícone', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'condition' => [
                    'widget_type' => 'button',
                ],
            ]
        );
        
        $this->add_control(
            'icon_position',
            [
                'label' => esc_html__('Posição do Ícone', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'left',
                'options' => [
                    'left' => esc_html__('Esquerda', 'hng-commerce'),
                    'right' => esc_html__('Direita', 'hng-commerce'),
                ],
                'condition' => [
                    'widget_type' => 'button',
                    'show_icon' => 'yes',
                ],
            ]
        );
        
        $this->add_control(
            'show_status',
            [
                'label' => esc_html__('Mostrar Status Online', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'condition' => [
                    'widget_type!' => 'floating',
                ],
            ]
        );
        
        $this->add_control(
            'online_text',
            [
                'label' => esc_html__('Texto Online', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__('Estamos online', 'hng-commerce'),
                'condition' => [
                    'show_status' => 'yes',
                    'widget_type!' => 'floating',
                ],
            ]
        );
        
        $this->add_control(
            'offline_text',
            [
                'label' => esc_html__('Texto Offline', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__('Deixe uma mensagem', 'hng-commerce'),
                'condition' => [
                    'show_status' => 'yes',
                    'widget_type!' => 'floating',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // Floating Settings (Global Override)
        $this->start_controls_section(
            'section_floating',
            [
                'label' => esc_html__('Configurações Globais', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
                'condition' => [
                    'widget_type' => 'floating',
                ],
            ]
        );
        
        $this->add_control(
            'floating_notice',
            [
                'type' => \Elementor\Controls_Manager::RAW_HTML,
                'raw' => sprintf(
                    '<div style="padding: 10px; background: #f0f0f0; border-radius: 4px;">%s <a href="%s" target="_blank">%s</a></div>',
                    esc_html__('Este widget configura o chat flutuante global. Para configurações avançadas, acesse', 'hng-commerce'),
                    admin_url('admin.php?page=hng-commerce&tab=live_chat'),
                    esc_html__('Configurações do Chat', 'hng-commerce')
                ),
            ]
        );
        
        $this->add_control(
            'override_position',
            [
                'label' => esc_html__('Posição', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => '',
                'options' => [
                    '' => esc_html__('Padrão (Configurações)', 'hng-commerce'),
                    'bottom-right' => esc_html__('Inferior Direito', 'hng-commerce'),
                    'bottom-left' => esc_html__('Inferior Esquerdo', 'hng-commerce'),
                ],
            ]
        );
        
        $this->add_control(
            'override_color',
            [
                'label' => esc_html__('Cor Principal', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '',
            ]
        );
        
        $this->end_controls_section();
        
        // Style Section - Button
        $this->start_controls_section(
            'section_button_style',
            [
                'label' => esc_html__('Estilo do Botão', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'widget_type' => 'button',
                ],
            ]
        );
        
        $this->add_control(
            'button_color',
            [
                'label' => esc_html__('Cor do Texto', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .hng-chat-button' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'button_background',
            [
                'label' => esc_html__('Cor de Fundo', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#0073aa',
                'selectors' => [
                    '{{WRAPPER}} .hng-chat-button' => 'background-color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'button_hover_color',
            [
                'label' => esc_html__('Cor do Texto (Hover)', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .hng-chat-button:hover' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'button_hover_background',
            [
                'label' => esc_html__('Cor de Fundo (Hover)', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#005a87',
                'selectors' => [
                    '{{WRAPPER}} .hng-chat-button:hover' => 'background-color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'button_typography',
                'selector' => '{{WRAPPER}} .hng-chat-button',
            ]
        );
        
        $this->add_responsive_control(
            'button_padding',
            [
                'label' => esc_html__('Padding', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'default' => [
                    'top' => 12,
                    'right' => 24,
                    'bottom' => 12,
                    'left' => 24,
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .hng-chat-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_control(
            'button_border_radius',
            [
                'label' => esc_html__('Border Radius', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'default' => [
                    'top' => 8,
                    'right' => 8,
                    'bottom' => 8,
                    'left' => 8,
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .hng-chat-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'button_shadow',
                'selector' => '{{WRAPPER}} .hng-chat-button',
            ]
        );
        
        $this->end_controls_section();
        
        // Style Section - Status
        $this->start_controls_section(
            'section_status_style',
            [
                'label' => esc_html__('Estilo do Status', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_status' => 'yes',
                    'widget_type!' => 'floating',
                ],
            ]
        );
        
        $this->add_control(
            'online_indicator_color',
            [
                'label' => esc_html__('Cor Indicador Online', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#4caf50',
            ]
        );
        
        $this->add_control(
            'offline_indicator_color',
            [
                'label' => esc_html__('Cor Indicador Offline', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#9e9e9e',
            ]
        );
        
        $this->add_control(
            'status_text_color',
            [
                'label' => esc_html__('Cor do Texto', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#666666',
                'selectors' => [
                    '{{WRAPPER}} .hng-chat-status-text' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'status_typography',
                'selector' => '{{WRAPPER}} .hng-chat-status-text',
            ]
        );
        
        $this->end_controls_section();
        
        // Style Section - Inline
        $this->start_controls_section(
            'section_inline_style',
            [
                'label' => esc_html__('Estilo Inline', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'widget_type' => 'inline',
                ],
            ]
        );
        
        $this->add_control(
            'inline_background',
            [
                'label' => esc_html__('Cor de Fundo', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#f5f5f5',
                'selectors' => [
                    '{{WRAPPER}} .hng-chat-inline-container' => 'background-color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'inline_border_radius',
            [
                'label' => esc_html__('Border Radius', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px'],
                'default' => [
                    'top' => 12,
                    'right' => 12,
                    'bottom' => 12,
                    'left' => 12,
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .hng-chat-inline-container' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_responsive_control(
            'inline_padding',
            [
                'label' => esc_html__('Padding', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'default' => [
                    'top' => 20,
                    'right' => 20,
                    'bottom' => 20,
                    'left' => 20,
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .hng-chat-inline-container' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->end_controls_section();
    }
    
    /**
     * Render widget
     */
    protected function render() {
        $settings = $this->get_settings_for_display();
        $widget_type = $settings['widget_type'];
        
        // Check if chat is enabled
        $chat_enabled = get_option('hng_live_chat_enabled', 'no') === 'yes';
        
        // In editor mode, show preview
        $is_editor = \Elementor\Plugin::$instance->editor->is_edit_mode();
        
        if (!$chat_enabled && !$is_editor) {
            return;
        }
        
        // Get online status
        $live_chat = HNG_Live_Chat::instance();
        $operators_online = $live_chat->get_online_operators_count();
        $is_online = $operators_online > 0;
        
        if ($widget_type === 'floating') {
            $this->render_floating_config($settings);
        } elseif ($widget_type === 'inline') {
            $this->render_inline($settings, $is_online, $is_editor);
        } else {
            $this->render_button($settings, $is_online, $is_editor);
        }
    }
    
    /**
     * Render button type
     */
    private function render_button($settings, $is_online, $is_editor) {
        $button_text = $settings['button_text'];
        $show_icon = $settings['show_icon'] === 'yes';
        $icon_position = $settings['icon_position'];
        $show_status = $settings['show_status'] === 'yes';
        $online_text = $settings['online_text'];
        $offline_text = $settings['offline_text'];
        $online_color = $settings['online_indicator_color'] ?: '#4caf50';
        $offline_color = $settings['offline_indicator_color'] ?: '#9e9e9e';
        
        $icon_html = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>';
        
        ?>
        <div class="hng-chat-widget-container">
            <?php if ($show_status) : ?>
                <div class="hng-chat-status-indicator">
                    <span class="hng-chat-status-dot" style="background-color: <?php echo $is_online ? esc_attr($online_color) : esc_attr($offline_color); ?>"></span>
                    <span class="hng-chat-status-text"><?php echo esc_html($is_online ? $online_text : $offline_text); ?></span>
                </div>
            <?php endif; ?>
            
            <button type="button" class="hng-chat-button hng-chat-trigger" data-action="open-chat">
                <?php if ($show_icon && $icon_position === 'left') : ?>
                    <span class="hng-chat-button-icon"><?php echo $icon_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icon HTML contains safe SVG/icon element ?></span>
                <?php endif; ?>
                
                <span class="hng-chat-button-text"><?php echo esc_html($button_text); ?></span>
                
                <?php if ($show_icon && $icon_position === 'right') : ?>
                    <span class="hng-chat-button-icon"><?php echo $icon_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icon HTML contains safe SVG/icon element ?></span>
                <?php endif; ?>
            </button>
        </div>
        
        <style>
            .hng-chat-widget-container {
                display: inline-flex;
                flex-direction: column;
                align-items: center;
                gap: 8px;
            }
            
            .hng-chat-button {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                border: none;
                cursor: pointer;
                transition: all 0.2s ease;
                font-family: inherit;
            }
            
            .hng-chat-button-icon {
                display: flex;
                align-items: center;
            }
            
            .hng-chat-status-indicator {
                display: flex;
                align-items: center;
                gap: 6px;
            }
            
            .hng-chat-status-dot {
                width: 8px;
                height: 8px;
                border-radius: 50%;
                animation: pulse 2s infinite;
            }
            
            @keyframes pulse {
                0%, 100% { opacity: 1; }
                50% { opacity: 0.5; }
            }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('.hng-chat-trigger').on('click', function() {
                // Open the global chat widget
                if (window.HNGLiveChat) {
                    window.HNGLiveChat.openChat();
                } else {
                    // Fallback - click the chat bubble
                    $('.hng-chat-bubble').click();
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render inline type
     */
    private function render_inline($settings, $is_online, $is_editor) {
        $show_status = $settings['show_status'] === 'yes';
        $online_text = $settings['online_text'];
        $offline_text = $settings['offline_text'];
        $online_color = $settings['online_indicator_color'] ?: '#4caf50';
        $offline_color = $settings['offline_indicator_color'] ?: '#9e9e9e';
        
        ?>
        <div class="hng-chat-inline-container">
            <div class="hng-chat-inline-header">
                <div class="hng-chat-inline-icon">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                </div>
                <div class="hng-chat-inline-info">
                    <h4><?php echo esc_html(get_option('hng_live_chat_title', __('Atendimento ao Vivo', 'hng-commerce'))); ?></h4>
                    <?php if ($show_status) : ?>
                        <div class="hng-chat-status-indicator">
                            <span class="hng-chat-status-dot" style="background-color: <?php echo $is_online ? esc_attr($online_color) : esc_attr($offline_color); ?>"></span>
                            <span class="hng-chat-status-text"><?php echo esc_html($is_online ? $online_text : $offline_text); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <p class="hng-chat-inline-message">
                <?php echo esc_html(get_option('hng_live_chat_welcome_message', __('Olá! Como podemos ajudá-lo hoje?', 'hng-commerce'))); ?>
            </p>
            
            <button type="button" class="hng-chat-button hng-chat-trigger">
                <?php esc_html_e('Iniciar Conversa', 'hng-commerce'); ?>
            </button>
        </div>
        
        <style>
            .hng-chat-inline-container {
                text-align: center;
            }
            
            .hng-chat-inline-header {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 12px;
                margin-bottom: 16px;
            }
            
            .hng-chat-inline-icon {
                color: #0073aa;
            }
            
            .hng-chat-inline-info h4 {
                margin: 0 0 4px;
                font-size: 18px;
            }
            
            .hng-chat-inline-message {
                margin: 0 0 20px;
                color: #666;
            }
            
            .hng-chat-inline-container .hng-chat-button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                border: none;
                cursor: pointer;
                transition: all 0.2s ease;
                font-family: inherit;
                background: #0073aa;
                color: #fff;
                padding: 12px 24px;
                border-radius: 8px;
                font-size: 15px;
                font-weight: 500;
            }
            
            .hng-chat-inline-container .hng-chat-button:hover {
                background: #005a87;
            }
            
            .hng-chat-status-indicator {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
            }
            
            .hng-chat-status-dot {
                width: 8px;
                height: 8px;
                border-radius: 50%;
            }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('.hng-chat-trigger').on('click', function() {
                if (window.HNGLiveChat) {
                    window.HNGLiveChat.openChat();
                } else {
                    $('.hng-chat-bubble').click();
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render floating config
     */
    private function render_floating_config($settings) {
        $override_position = $settings['override_position'];
        $override_color = $settings['override_color'];
        
        ?>
        <div class="hng-chat-floating-config" style="padding: 20px; background: #f0f0f0; border-radius: 8px; text-align: center;">
            <p style="margin: 0 0 10px;">
                <strong><?php esc_html_e('Widget de Chat Flutuante', 'hng-commerce'); ?></strong>
            </p>
            <p style="margin: 0; font-size: 13px; color: #666;">
                <?php esc_html_e('Este widget configura o chat flutuante global. O botão aparecerá no canto da página.', 'hng-commerce'); ?>
            </p>
        </div>
        
        <?php if ($override_position || $override_color) : ?>
        <script>
        jQuery(document).ready(function($) {
            <?php if ($override_position) : ?>
            $('#hng-live-chat-widget')
                .removeClass('hng-chat-bottom-right hng-chat-bottom-left')
                .addClass('hng-chat-<?php echo esc_js($override_position); ?>');
            <?php endif; ?>
            
            <?php if ($override_color) : ?>
            $('#hng-live-chat-widget').css('--hng-chat-primary', '<?php echo esc_js($override_color); ?>');
            <?php endif; ?>
        });
        </script>
        <?php endif;
    }
    
    /**
     * Render content template (for Elementor editor)
     */
    protected function content_template() {
        ?>
        <#
        var widget_type = settings.widget_type;
        var button_text = settings.button_text || 'Iniciar Chat';
        var show_icon = settings.show_icon === 'yes';
        var icon_position = settings.icon_position;
        var show_status = settings.show_status === 'yes';
        var online_text = settings.online_text || 'Estamos online';
        
        var icon_html = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>';
        #>
        
        <# if (widget_type === 'floating') { #>
            <div class="hng-chat-floating-config" style="padding: 20px; background: #f0f0f0; border-radius: 8px; text-align: center;">
                <p style="margin: 0 0 10px;"><strong>Widget de Chat Flutuante</strong></p>
                <p style="margin: 0; font-size: 13px; color: #666;">
                    Este widget configura o chat flutuante global.
                </p>
            </div>
        <# } else if (widget_type === 'inline') { #>
            <div class="hng-chat-inline-container" style="background: #f5f5f5; padding: 20px; border-radius: 12px; text-align: center;">
                <div style="margin-bottom: 16px;">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#0073aa" stroke-width="2">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                    <h4 style="margin: 8px 0 4px;">Atendimento ao Vivo</h4>
                    <# if (show_status) { #>
                        <div style="display: flex; align-items: center; justify-content: center; gap: 6px;">
                            <span style="width: 8px; height: 8px; border-radius: 50%; background: #4caf50;"></span>
                            <span style="color: #666; font-size: 13px;">{{{ online_text }}}</span>
                        </div>
                    <# } #>
                </div>
                <p style="margin: 0 0 20px; color: #666;">Olá! Como podemos ajudá-lo hoje?</p>
                <button type="button" style="background: #0073aa; color: #fff; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer;">
                    Iniciar Conversa
                </button>
            </div>
        <# } else { #>
            <div class="hng-chat-widget-container" style="display: inline-flex; flex-direction: column; align-items: center; gap: 8px;">
                <# if (show_status) { #>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span style="width: 8px; height: 8px; border-radius: 50%; background: #4caf50;"></span>
                        <span class="hng-chat-status-text">{{{ online_text }}}</span>
                    </div>
                <# } #>
                
                <button type="button" class="hng-chat-button" style="display: inline-flex; align-items: center; gap: 8px;">
                    <# if (show_icon && icon_position === 'left') { #>
                        <span>{{{ icon_html }}}</span>
                    <# } #>
                    
                    <span>{{{ button_text }}}</span>
                    
                    <# if (show_icon && icon_position === 'right') { #>
                        <span>{{{ icon_html }}}</span>
                    <# } #>
                </button>
            </div>
        <# } #>
        <?php
    }
}
