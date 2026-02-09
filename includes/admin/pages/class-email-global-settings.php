<?php
/**
 * HNG Commerce - Email Global Settings
 * 
 * Gerencia configurações globais que se aplicam a todos os emails
 *
 * @package HNG_Commerce
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Email_Global_Settings {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_hng_save_email_global_settings', [$this, 'save_settings']);
    }
    
    /**
     * Get default settings
     */
    public static function get_defaults() {
        return [
            'logo_url' => '',
            'header_color' => '#3498db',
            'header_text_color' => '#ffffff',
            'button_color' => '#27ae60',
            'button_text_color' => '#ffffff',
            'footer_text' => sprintf(
                /* translators: %s = year */
                __('© %s {site_name}. Todos os direitos reservados.', 'hng-commerce'),
                gmdate('Y')
            ),
            'footer_address' => '',
            'footer_phone' => '',
            'footer_email' => '',
            'social_facebook' => '',
            'social_instagram' => '',
            'social_twitter' => '',
            'social_linkedin' => '',
            'font_family' => 'Arial, sans-serif',
            'body_bg_color' => '#f4f4f4',
            'content_bg_color' => '#ffffff',
            'text_color' => '#333333',
        ];
    }
    
    /**
     * Get current settings
     */
    public static function get_settings() {
        $defaults = self::get_defaults();
        $saved = get_option('hng_email_global_settings', []);
        
        return wp_parse_args($saved, $defaults);
    }
    
    /**
     * Save settings via AJAX
     */
    public function save_settings() {
        check_ajax_referer('hng_email_customizer', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permissão negada', 'hng-commerce')]);
        }
        
        $post = function_exists('wp_unslash') ? wp_unslash($_POST) : $_POST;
        $settings = [];
        $allowed_keys = array_keys(self::get_defaults());
        
        foreach ($allowed_keys as $key) {
            if (isset($post[$key])) {
                $value = $post[$key];
                // Sanitizar baseado no tipo
                if (in_array($key, ['logo_url', 'social_facebook', 'social_instagram', 'social_twitter', 'social_linkedin'])) {
                    $value = esc_url_raw($value);
                } elseif (in_array($key, ['header_color', 'button_color', 'button_text_color', 'header_text_color', 'body_bg_color', 'text_color'])) {
                    $value = sanitize_hex_color($value);
                } else {
                    $value = sanitize_text_field($value);
                }
                $settings[$key] = $value;
            }
        }
        
        // Salvar configurações globais
        update_option('hng_email_global_settings', $settings);
        
        // Aplicar configurações globais em todos os templates existentes
        $this->apply_global_settings_to_all_templates($settings);
        
        wp_send_json_success([
            'message' => __('Configurações globais salvas e aplicadas a todos os templates!', 'hng-commerce'),
            'settings' => $settings
        ]);
    }
    
    /**
     * Aplica configurações globais em todos os templates existentes
     * Remove cores/logo personalizados para forçar uso das configurações globais
     */
    private function apply_global_settings_to_all_templates($global_settings) {
        // Lista de todos os tipos de email possíveis
        $email_types = [
            'new_order',
            'order_paid',
            'order_cancelled',
            'new_subscription',
            'subscription_renewed',
            'subscription_cancelled',
            'payment_received',
            'payment_failed',
            'quote_request',
            'quote_admin_new',
            'quote_approved',
            'quote_message',
        ];
        
        foreach ($email_types as $email_type) {
            $option_name = "hng_email_template_{$email_type}";
            $template = get_option($option_name, []);
            
            // Se template existe, REMOVER cores, logo e footer para forçar uso das globais
            if (!empty($template)) {
                // Remover configurações visuais para usar as globais
                unset($template['logo']);
                unset($template['header_color']);
                unset($template['button_color']);
                unset($template['text_color']);
                unset($template['bg_color']);
                unset($template['footer']);
                
                // Salvar template limpo (mantém apenas subject, from_name, from_email, content)
                update_option($option_name, $template);
            }
        }
    }
    
    /**
     * Render settings page
     */
    public static function render_settings_page() {
        $settings = self::get_settings();
        ?>
        <div class="hng-email-global-settings">
            <h2><?php esc_html_e('Configurações Globais de Email', 'hng-commerce'); ?></h2>
            <p class="description">
                <?php esc_html_e('Estas configurações serão aplicadas a todos os templates de email como padrão. Você pode personalizar cada template individualmente depois.', 'hng-commerce'); ?>
            </p>
            
            <form id="hng-email-global-form" class="hng-email-form">
                <?php wp_nonce_field('hng_email_customizer', 'nonce'); ?>
                
                <!-- Logo -->
                <div class="hng-form-section">
                    <h3><?php esc_html_e('Identidade Visual', 'hng-commerce'); ?></h3>
                    
                    <div class="hng-form-group">
                        <label for="logo_url"><?php esc_html_e('Logo (URL)', 'hng-commerce'); ?></label>
                        <div class="hng-logo-upload">
                            <input type="url" 
                                   id="logo_url" 
                                   name="logo_url" 
                                   value="<?php echo esc_url($settings['logo_url']); ?>" 
                                   placeholder="https://seusite.com/logo.png"
                                   class="regular-text">
                            <button type="button" class="button hng-upload-logo">
                                <?php esc_html_e('Fazer Upload', 'hng-commerce'); ?>
                            </button>
                        </div>
                        <p class="description"><?php esc_html_e('Logo que aparecerá no cabeçalho dos emails', 'hng-commerce'); ?></p>
                    </div>
                </div>
                
                <!-- Cores -->
                <div class="hng-form-section">
                    <h3><?php esc_html_e('Cores', 'hng-commerce'); ?></h3>
                    
                    <div class="hng-color-grid">
                        <div class="hng-form-group">
                            <label for="header_color"><?php esc_html_e('Cor do Cabeçalho', 'hng-commerce'); ?></label>
                            <input type="color" 
                                   id="header_color" 
                                   name="header_color" 
                                   value="<?php echo esc_attr($settings['header_color']); ?>"
                                   class="hng-color-picker">
                        </div>
                        
                        <div class="hng-form-group">
                            <label for="header_text_color"><?php esc_html_e('Cor do Texto do Cabeçalho', 'hng-commerce'); ?></label>
                            <input type="color" 
                                   id="header_text_color" 
                                   name="header_text_color" 
                                   value="<?php echo esc_attr($settings['header_text_color']); ?>"
                                   class="hng-color-picker">
                        </div>
                        
                        <div class="hng-form-group">
                            <label for="button_color"><?php esc_html_e('Cor dos Botões', 'hng-commerce'); ?></label>
                            <input type="color" 
                                   id="button_color" 
                                   name="button_color" 
                                   value="<?php echo esc_attr($settings['button_color']); ?>"
                                   class="hng-color-picker">
                        </div>
                        
                        <div class="hng-form-group">
                            <label for="button_text_color"><?php esc_html_e('Cor do Texto dos Botões', 'hng-commerce'); ?></label>
                            <input type="color" 
                                   id="button_text_color" 
                                   name="button_text_color" 
                                   value="<?php echo esc_attr($settings['button_text_color']); ?>"
                                   class="hng-color-picker">
                        </div>
                        
                        <div class="hng-form-group">
                            <label for="body_bg_color"><?php esc_html_e('Cor de Fundo', 'hng-commerce'); ?></label>
                            <input type="color" 
                                   id="body_bg_color" 
                                   name="body_bg_color" 
                                   value="<?php echo esc_attr($settings['body_bg_color']); ?>"
                                   class="hng-color-picker">
                        </div>
                        
                        <div class="hng-form-group">
                            <label for="text_color"><?php esc_html_e('Cor do Texto', 'hng-commerce'); ?></label>
                            <input type="color" 
                                   id="text_color" 
                                   name="text_color" 
                                   value="<?php echo esc_attr($settings['text_color']); ?>"
                                   class="hng-color-picker">
                        </div>
                    </div>
                </div>
                
                <!-- Rodapé -->
                <div class="hng-form-section">
                    <h3><?php esc_html_e('Rodapé', 'hng-commerce'); ?></h3>
                    
                    <div class="hng-form-group">
                        <label for="footer_text"><?php esc_html_e('Texto do Rodapé', 'hng-commerce'); ?></label>
                        <textarea id="footer_text" 
                                  name="footer_text" 
                                  rows="3" 
                                  class="large-text"><?php echo esc_textarea($settings['footer_text']); ?></textarea>
                        <p class="description"><?php esc_html_e('Você pode usar {site_name} e {site_url}', 'hng-commerce'); ?></p>
                    </div>
                    
                    <div class="hng-form-group">
                        <label for="footer_address"><?php esc_html_e('Endereço', 'hng-commerce'); ?></label>
                        <input type="text" 
                               id="footer_address" 
                               name="footer_address" 
                               value="<?php echo esc_attr($settings['footer_address']); ?>" 
                               class="regular-text">
                    </div>
                    
                    <div class="hng-form-group">
                        <label for="footer_phone"><?php esc_html_e('Telefone', 'hng-commerce'); ?></label>
                        <input type="tel" 
                               id="footer_phone" 
                               name="footer_phone" 
                               value="<?php echo esc_attr($settings['footer_phone']); ?>" 
                               class="regular-text">
                    </div>
                    
                    <div class="hng-form-group">
                        <label for="footer_email"><?php esc_html_e('Email de Contato', 'hng-commerce'); ?></label>
                        <input type="email" 
                               id="footer_email" 
                               name="footer_email" 
                               value="<?php echo esc_attr($settings['footer_email']); ?>" 
                               class="regular-text">
                    </div>
                </div>
                
                <!-- Redes Sociais -->
                <div class="hng-form-section">
                    <h3><?php esc_html_e('Redes Sociais', 'hng-commerce'); ?></h3>
                    
                    <div class="hng-form-group">
                        <label for="social_facebook"><?php esc_html_e('Facebook', 'hng-commerce'); ?></label>
                        <input type="url" 
                               id="social_facebook" 
                               name="social_facebook" 
                               value="<?php echo esc_url($settings['social_facebook']); ?>" 
                               placeholder="https://facebook.com/suapagina"
                               class="regular-text">
                    </div>
                    
                    <div class="hng-form-group">
                        <label for="social_instagram"><?php esc_html_e('Instagram', 'hng-commerce'); ?></label>
                        <input type="url" 
                               id="social_instagram" 
                               name="social_instagram" 
                               value="<?php echo esc_url($settings['social_instagram']); ?>" 
                               placeholder="https://instagram.com/seuperfil"
                               class="regular-text">
                    </div>
                    
                    <div class="hng-form-group">
                        <label for="social_twitter"><?php esc_html_e('Twitter/X', 'hng-commerce'); ?></label>
                        <input type="url" 
                               id="social_twitter" 
                               name="social_twitter" 
                               value="<?php echo esc_url($settings['social_twitter']); ?>" 
                               placeholder="https://twitter.com/seuperfil"
                               class="regular-text">
                    </div>
                    
                    <div class="hng-form-group">
                        <label for="social_linkedin"><?php esc_html_e('LinkedIn', 'hng-commerce'); ?></label>
                        <input type="url" 
                               id="social_linkedin" 
                               name="social_linkedin" 
                               value="<?php echo esc_url($settings['social_linkedin']); ?>" 
                               placeholder="https://linkedin.com/company/suaempresa"
                               class="regular-text">
                    </div>
                </div>
                
                <div class="hng-form-actions">
                    <button type="submit" class="button button-primary button-large">
                        <?php esc_html_e('Salvar Configurações Globais', 'hng-commerce'); ?>
                    </button>
                    <span class="spinner"></span>
                </div>
            </form>
        </div>
        
        <style>
        .hng-email-global-settings {
            max-width: 900px;
        }
        .hng-form-section {
            background: #fff;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .hng-form-section h3 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .hng-form-group {
            margin: 15px 0;
        }
        .hng-form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .hng-color-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
        .hng-color-picker {
            width: 100%;
            height: 40px;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
        }
        .hng-logo-upload {
            display: flex;
            gap: 10px;
        }
        .hng-form-actions {
            padding: 20px;
            background: #f9f9f9;
            border-top: 1px solid #eee;
            position: sticky;
            bottom: 0;
            margin: 0 -20px -20px;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Handle form submission
            $('#hng-email-global-form').on('submit', function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var $button = $form.find('button[type="submit"]');
                var $spinner = $form.find('.spinner');
                
                $button.prop('disabled', true);
                $spinner.addClass('is-active');
                
                // Preparar dados do formulário
                var formData = $form.serializeArray();
                
                // Adicionar nonce
                var nonceValue = $form.find('input[name="hng_email_nonce"][type="hidden"]').val() || 
                                 (typeof wp !== 'undefined' && wp.nonce ? wp.nonce.hng_email_customizer : '');
                
                // Preparar dados para envio
                var data = {
                    action: 'hng_save_email_global_settings',
                    nonce: nonceValue
                };
                
                // Adicionar todos os campos do formulário
                $.each(formData, function(index, item) {
                    data[item.name] = item.value;
                });
                
                console.log('[HNG] Salvando configurações globais...', data);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: data,
                    dataType: 'json',
                    success: function(response) {
                        console.log('[HNG] Resposta:', response);
                        if (response.success) {
                            alert(response.data.message || 'Configurações salvas!');
                            // Recarregar para mostrar alterações em todos os templates
                            setTimeout(function() {
                                location.reload();
                            }, 500);
                        } else {
                            alert(response.data.message || 'Erro ao salvar');
                            $button.prop('disabled', false);
                            $spinner.removeClass('is-active');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('[HNG] Erro AJAX:', error);
                        console.error('[HNG] Response:', xhr.responseText);
                        alert('Erro de conexão ao salvar configurações');
                        $button.prop('disabled', false);
                        $spinner.removeClass('is-active');
                    }
                });
            });
            
            // Handle logo upload
            $('.hng-upload-logo').on('click', function(e) {
                e.preventDefault();
                
                var mediaUploader = wp.media({
                    title: 'Selecionar Logo',
                    button: { text: 'Usar esta imagem' },
                    multiple: false
                });
                
                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#logo_url').val(attachment.url);
                });
                
                mediaUploader.open();
            });
        });
        </script>
        <?php
    }
}

// Initialize
new HNG_Email_Global_Settings();
