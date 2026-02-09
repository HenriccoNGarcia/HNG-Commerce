<?php
/**
 * Custom Shipping Settings Page
 * 
 * Página de configuração da transportadora própria
 * 
 * @package HNG_Commerce
 * @since 1.2.16
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Custom_Shipping_Settings {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_ajax_hng_save_custom_shipping', [$this, 'ajax_save_settings']);
        add_action('wp_ajax_hng_save_custom_shipping_zone', [$this, 'ajax_save_zone']);
        add_action('wp_ajax_hng_delete_custom_shipping_zone', [$this, 'ajax_delete_zone']);
    }
    
    /**
     * Renderizar página de configurações
     */
    public static function render() {
        $settings = HNG_Shipping_Custom::get_custom_settings();
        $states = self::get_brazilian_states();
        
        // Iniciar output
        self::render_styles();
        ?>
        <div class="wrap hngcs-wrap">
            <div class="hngcs-header">
                <h1><span class="dashicons dashicons-car"></span> <?php esc_html_e('Configuração de Transportadora Própria', 'hng-commerce'); ?></h1>
                <p><?php esc_html_e('Configure sua própria transportadora quando não utilizar serviços integrados como Correios, Melhor Envio, etc.', 'hng-commerce'); ?></p>
            </div>
            
            <form id="hngcs-main-form">
                <?php wp_nonce_field('hng_custom_shipping_nonce', 'hngcs_nonce'); ?>
                
                <!-- Ativar/Desativar -->
                <div class="hngcs-box">
                    <div class="hngcs-box-header">
                        <h2><?php esc_html_e('Ativação', 'hng-commerce'); ?></h2>
                    </div>
                    <div class="hngcs-box-body">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Status', 'hng-commerce'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="enabled" value="yes" <?php checked($settings['enabled'], 'yes'); ?>>
                                        <?php esc_html_e('Ativar Transportadora Própria', 'hng-commerce'); ?>
                                    </label>
                                    <p class="description"><?php esc_html_e('Habilita o uso de transportadora própria para cálculo de frete e geração de etiquetas.', 'hng-commerce'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- Dados da Transportadora -->
                <div class="hngcs-box">
                    <div class="hngcs-box-header">
                        <h2><?php esc_html_e('Dados da Transportadora', 'hng-commerce'); ?></h2>
                    </div>
                    <div class="hngcs-box-body">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="company_name"><?php esc_html_e('Nome da Transportadora', 'hng-commerce'); ?></label></th>
                                <td><input type="text" id="company_name" name="company_name" value="<?php echo esc_attr($settings['company_name']); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="company_cnpj"><?php esc_html_e('CNPJ', 'hng-commerce'); ?></label></th>
                                <td><input type="text" id="company_cnpj" name="company_cnpj" value="<?php echo esc_attr($settings['company_cnpj']); ?>" class="regular-text" placeholder="00.000.000/0000-00"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="company_phone"><?php esc_html_e('Telefone', 'hng-commerce'); ?></label></th>
                                <td><input type="text" id="company_phone" name="company_phone" value="<?php echo esc_attr($settings['company_phone']); ?>" class="regular-text" placeholder="(00) 00000-0000"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="company_email"><?php esc_html_e('E-mail', 'hng-commerce'); ?></label></th>
                                <td><input type="email" id="company_email" name="company_email" value="<?php echo esc_attr($settings['company_email']); ?>" class="regular-text"></td>
                            </tr>
                        </table>
                        
                        <h3 class="hngcs-subtitle"><?php esc_html_e('Endereço de Coleta', 'hng-commerce'); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="company_cep"><?php esc_html_e('CEP', 'hng-commerce'); ?></label></th>
                                <td><input type="text" id="company_cep" name="company_cep" value="<?php echo esc_attr($settings['company_cep']); ?>" class="regular-text" placeholder="00000-000"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="company_address"><?php esc_html_e('Endereço Completo', 'hng-commerce'); ?></label></th>
                                <td><input type="text" id="company_address" name="company_address" value="<?php echo esc_attr($settings['company_address']); ?>" class="large-text"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="company_city"><?php esc_html_e('Cidade', 'hng-commerce'); ?></label></th>
                                <td><input type="text" id="company_city" name="company_city" value="<?php echo esc_attr($settings['company_city']); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="company_state"><?php esc_html_e('Estado', 'hng-commerce'); ?></label></th>
                                <td>
                                    <select id="company_state" name="company_state">
                                        <option value=""><?php esc_html_e('Selecione', 'hng-commerce'); ?></option>
                                        <?php foreach ($states as $uf => $name): ?>
                                            <option value="<?php echo esc_attr($uf); ?>" <?php selected($settings['company_state'], $uf); ?>><?php echo esc_html($name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- Configurações de Etiqueta -->
                <div class="hngcs-box">
                    <div class="hngcs-box-header">
                        <h2><?php esc_html_e('Configurações de Etiqueta', 'hng-commerce'); ?></h2>
                    </div>
                    <div class="hngcs-box-body">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="label_format"><?php esc_html_e('Formato da Etiqueta', 'hng-commerce'); ?></label></th>
                                <td>
                                    <select id="label_format" name="label_format">
                                        <option value="a4" <?php selected($settings['label_format'], 'a4'); ?>>A4 (210x297mm)</option>
                                        <option value="a5" <?php selected($settings['label_format'], 'a5'); ?>>A5 (148x210mm)</option>
                                        <option value="10x15" <?php selected($settings['label_format'], '10x15'); ?>>10x15cm</option>
                                        <option value="zebra" <?php selected($settings['label_format'], 'zebra'); ?>>Zebra 4x6"</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="label_copies"><?php esc_html_e('Cópias por Impressão', 'hng-commerce'); ?></label></th>
                                <td><input type="number" id="label_copies" name="label_copies" value="<?php echo esc_attr($settings['label_copies'] ?: 1); ?>" min="1" max="4" class="small-text"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="tracking_prefix"><?php esc_html_e('Prefixo do Rastreamento', 'hng-commerce'); ?></label></th>
                                <td>
                                    <input type="text" id="tracking_prefix" name="tracking_prefix" value="<?php echo esc_attr($settings['tracking_prefix']); ?>" class="small-text" placeholder="TR">
                                    <p class="description"><?php esc_html_e('Ex: TR gerará códigos como TR20260128000001', 'hng-commerce'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="tracking_url"><?php esc_html_e('URL de Rastreamento', 'hng-commerce'); ?></label></th>
                                <td>
                                    <input type="url" id="tracking_url" name="tracking_url" value="<?php echo esc_attr($settings['tracking_url']); ?>" class="large-text" placeholder="https://seusite.com/rastreio?codigo={tracking_code}">
                                    <p class="description"><?php esc_html_e('Use {tracking_code} como placeholder para o código de rastreamento.', 'hng-commerce'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- Taxa Padrão (fallback) -->
                <div class="hngcs-box">
                    <div class="hngcs-box-header">
                        <h2><?php esc_html_e('Taxa Padrão (Fallback)', 'hng-commerce'); ?></h2>
                    </div>
                    <div class="hngcs-box-body">
                        <p class="description" style="margin-bottom: 15px;"><?php esc_html_e('Esta taxa será aplicada quando o CEP do cliente não estiver em nenhuma zona configurada.', 'hng-commerce'); ?></p>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Status', 'hng-commerce'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="default_enabled" value="yes" <?php checked($settings['default_enabled'], 'yes'); ?>>
                                        <?php esc_html_e('Ativar taxa padrão', 'hng-commerce'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="default_name"><?php esc_html_e('Nome da Opção', 'hng-commerce'); ?></label></th>
                                <td><input type="text" id="default_name" name="default_name" value="<?php echo esc_attr($settings['default_name']); ?>" class="regular-text" placeholder="Frete"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="default_cost"><?php esc_html_e('Valor (R$)', 'hng-commerce'); ?></label></th>
                                <td><input type="number" id="default_cost" name="default_cost" value="<?php echo esc_attr($settings['default_cost']); ?>" step="0.01" min="0" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="default_time"><?php esc_html_e('Prazo de Entrega', 'hng-commerce'); ?></label></th>
                                <td><input type="text" id="default_time" name="default_time" value="<?php echo esc_attr($settings['default_time']); ?>" class="regular-text" placeholder="5 a 10 dias úteis"></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <p class="submit">
                    <button type="submit" class="button button-primary button-large" id="hngcs-save-btn">
                        <span class="dashicons dashicons-saved" style="margin-top: 4px;"></span>
                        <?php esc_html_e('Salvar Configurações', 'hng-commerce'); ?>
                    </button>
                </p>
            </form>
            
            <!-- Zonas de Frete -->
            <div class="hngcs-box hngcs-zones-box">
                <div class="hngcs-box-header">
                    <h2><?php esc_html_e('Zonas de Frete', 'hng-commerce'); ?></h2>
                    <button type="button" class="button button-secondary" id="hngcs-add-zone-btn">
                        <span class="dashicons dashicons-plus-alt2" style="margin-top: 4px;"></span>
                        <?php esc_html_e('Adicionar Zona', 'hng-commerce'); ?>
                    </button>
                </div>
                <div class="hngcs-box-body">
                    <p class="description"><?php esc_html_e('Configure zonas de frete por faixas de CEP com valores e prazos específicos.', 'hng-commerce'); ?></p>
                    
                    <div id="hngcs-zones-container">
                        <?php 
                        $zones = $settings['zones'];
                        if (empty($zones)): 
                        ?>
                            <div class="hngcs-empty-state">
                                <span class="dashicons dashicons-location-alt"></span>
                                <p><?php esc_html_e('Nenhuma zona configurada. Clique em "Adicionar Zona" para começar.', 'hng-commerce'); ?></p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($zones as $zone_id => $zone): ?>
                                <?php self::render_zone_item($zone_id, $zone); ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Modal de Zona -->
        <div id="hngcs-zone-modal" class="hngcs-modal" style="display:none;">
            <div class="hngcs-modal-backdrop"></div>
            <div class="hngcs-modal-dialog">
                <div class="hngcs-modal-header">
                    <h3><?php esc_html_e('Configurar Zona de Frete', 'hng-commerce'); ?></h3>
                    <button type="button" class="hngcs-modal-close" aria-label="Fechar">&times;</button>
                </div>
                <form id="hngcs-zone-form">
                    <?php wp_nonce_field('hng_custom_shipping_zone_nonce', 'hngcs_zone_nonce'); ?>
                    <input type="hidden" name="zone_id" id="hngcs-zone-id" value="">
                    
                    <div class="hngcs-modal-body">
                        <p>
                            <label for="hngcs-zone-name"><strong><?php esc_html_e('Nome da Zona', 'hng-commerce'); ?></strong> *</label><br>
                            <input type="text" id="hngcs-zone-name" name="zone_name" class="large-text" required placeholder="Ex: São Paulo Capital, Sudeste, Nacional...">
                        </p>
                        
                        <p>
                            <strong><?php esc_html_e('Faixas de CEP', 'hng-commerce'); ?></strong> *
                        </p>
                        <div id="hngcs-cep-ranges">
                            <div class="hngcs-cep-row">
                                <input type="text" name="cep_from[]" placeholder="CEP Inicial" class="regular-text" style="width: 120px;">
                                <span> até </span>
                                <input type="text" name="cep_to[]" placeholder="CEP Final" class="regular-text" style="width: 120px;">
                                <button type="button" class="button hngcs-remove-cep">&times;</button>
                            </div>
                        </div>
                        <p><button type="button" class="button" id="hngcs-add-cep">+ Adicionar Faixa</button></p>
                        
                        <p>
                            <label for="hngcs-base-cost"><strong><?php esc_html_e('Custo Base (R$)', 'hng-commerce'); ?></strong></label><br>
                            <input type="number" id="hngcs-base-cost" name="base_cost" step="0.01" min="0" class="regular-text" value="0">
                        </p>
                        
                        <hr>
                        
                        <h4><?php esc_html_e('Métodos de Envio', 'hng-commerce'); ?></h4>
                        <p class="description"><?php esc_html_e('Configure diferentes opções de envio para esta zona (ex: Normal, Expresso).', 'hng-commerce'); ?></p>
                        
                        <div id="hngcs-methods">
                            <div class="hngcs-method-row">
                                <table class="widefat" style="margin-bottom: 10px;">
                                    <tr>
                                        <td style="width: 40%;"><input type="text" name="method_name[]" class="widefat" placeholder="Nome do método"></td>
                                        <td style="width: 20%;"><input type="number" name="method_cost[]" step="0.01" min="0" class="widefat" placeholder="Custo adicional" value="0"></td>
                                        <td style="width: 25%;"><input type="text" name="method_time[]" class="widefat" placeholder="Prazo"></td>
                                        <td style="width: 10%;"><label><input type="checkbox" name="method_enabled[]" value="yes" checked> Ativo</label></td>
                                        <td style="width: 5%;"><button type="button" class="button hngcs-remove-method">&times;</button></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <p><button type="button" class="button" id="hngcs-add-method">+ Adicionar Método</button></p>
                    </div>
                    
                    <div class="hngcs-modal-footer">
                        <button type="button" class="button" id="hngcs-cancel-zone"><?php esc_html_e('Cancelar', 'hng-commerce'); ?></button>
                        <button type="submit" class="button button-primary"><?php esc_html_e('Salvar Zona', 'hng-commerce'); ?></button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php self::render_scripts(); ?>
        <?php
    }
    
    /**
     * Estilos CSS
     */
    private static function render_styles() {
        ?>
        <style>
        /* Layout principal */
        .hngcs-wrap { max-width: 1000px; }
        .hngcs-header { margin-bottom: 20px; }
        .hngcs-header h1 { display: flex; align-items: center; gap: 10px; }
        .hngcs-header h1 .dashicons { font-size: 28px; width: 28px; height: 28px; }
        .hngcs-header p { color: #646970; margin-top: 5px; }
        
        /* Boxes */
        .hngcs-box { 
            background: #fff; 
            border: 1px solid #c3c4c7; 
            box-shadow: 0 1px 1px rgba(0,0,0,.04); 
            margin-bottom: 20px; 
        }
        .hngcs-box-header { 
            padding: 12px 15px; 
            border-bottom: 1px solid #dcdcde; 
            background: #f6f7f7; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }
        .hngcs-box-header h2 { margin: 0; font-size: 14px; font-weight: 600; }
        .hngcs-box-body { padding: 15px; }
        .hngcs-box-body .form-table th { padding-left: 0; width: 200px; }
        
        .hngcs-subtitle { 
            font-size: 13px; 
            font-weight: 600; 
            margin: 25px 0 10px 0; 
            padding-top: 15px; 
            border-top: 1px solid #dcdcde; 
        }
        
        /* Empty state */
        .hngcs-empty-state { 
            text-align: center; 
            padding: 40px 20px; 
            color: #646970; 
        }
        .hngcs-empty-state .dashicons { 
            font-size: 48px; 
            width: 48px; 
            height: 48px; 
            color: #c3c4c7; 
        }
        
        /* Zone items */
        .hngcs-zone-item { 
            background: #f6f7f7; 
            border: 1px solid #dcdcde; 
            padding: 12px 15px; 
            margin-bottom: 10px; 
            display: flex; 
            justify-content: space-between; 
            align-items: flex-start; 
        }
        .hngcs-zone-item:last-child { margin-bottom: 0; }
        .hngcs-zone-name { font-weight: 600; margin-bottom: 4px; }
        .hngcs-zone-ceps { font-size: 12px; color: #646970; }
        .hngcs-zone-methods { margin-top: 8px; }
        .hngcs-zone-method { 
            display: inline-block; 
            background: #e0e0e0; 
            padding: 2px 8px; 
            border-radius: 3px; 
            font-size: 11px; 
            margin-right: 5px; 
            margin-bottom: 3px;
        }
        .hngcs-zone-actions { display: flex; gap: 5px; }
        
        /* Modal */
        .hngcs-modal { 
            position: fixed; 
            top: 0; 
            left: 0; 
            right: 0; 
            bottom: 0; 
            z-index: 100100; 
        }
        .hngcs-modal-backdrop { 
            position: absolute; 
            top: 0; 
            left: 0; 
            right: 0; 
            bottom: 0; 
            background: rgba(0,0,0,0.6); 
        }
        .hngcs-modal-dialog { 
            position: relative; 
            background: #fff; 
            margin: 50px auto; 
            max-width: 650px; 
            max-height: calc(100vh - 100px); 
            overflow-y: auto; 
            box-shadow: 0 5px 20px rgba(0,0,0,0.3); 
        }
        .hngcs-modal-header { 
            padding: 15px 20px; 
            border-bottom: 1px solid #dcdcde; 
            background: #f6f7f7; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }
        .hngcs-modal-header h3 { margin: 0; font-size: 15px; }
        .hngcs-modal-close { 
            background: none; 
            border: none; 
            font-size: 24px; 
            cursor: pointer; 
            color: #646970; 
            padding: 0; 
            line-height: 1; 
        }
        .hngcs-modal-close:hover { color: #d63638; }
        .hngcs-modal-body { padding: 20px; }
        .hngcs-modal-body hr { border: 0; border-top: 1px solid #dcdcde; margin: 20px 0; }
        .hngcs-modal-body h4 { margin: 0 0 10px 0; }
        .hngcs-modal-footer { 
            padding: 15px 20px; 
            border-top: 1px solid #dcdcde; 
            background: #f6f7f7; 
            display: flex; 
            gap: 10px; 
            justify-content: flex-end; 
        }
        
        /* CEP rows */
        .hngcs-cep-row { margin-bottom: 8px; display: flex; align-items: center; gap: 8px; }
        .hngcs-cep-row span { color: #646970; }
        
        /* Method rows */
        .hngcs-method-row { margin-bottom: 8px; }
        </style>
        <?php
    }
    
    /**
     * Scripts JavaScript
     */
    private static function render_scripts() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Salvar configurações
            $('#hngcs-main-form').on('submit', function(e) {
                e.preventDefault();
                var $btn = $('#hngcs-save-btn');
                var orig = $btn.html();
                $btn.prop('disabled', true).html('<span class="dashicons dashicons-update" style="animation: rotation 1s linear infinite; margin-top: 4px;"></span> Salvando...');
                
                $.post(ajaxurl, {
                    action: 'hng_save_custom_shipping',
                    nonce: $('#hngcs_nonce').val(),
                    data: $(this).serialize()
                }, function(res) {
                    $btn.prop('disabled', false).html(orig);
                    if (res.success) {
                        alert('<?php echo esc_js(__('Configurações salvas com sucesso!', 'hng-commerce')); ?>');
                    } else {
                        alert(res.data?.message || '<?php echo esc_js(__('Erro ao salvar.', 'hng-commerce')); ?>');
                    }
                }).fail(function() {
                    $btn.prop('disabled', false).html(orig);
                    alert('<?php echo esc_js(__('Erro de conexão.', 'hng-commerce')); ?>');
                });
            });
            
            // Abrir modal
            $('#hngcs-add-zone-btn').on('click', function() {
                $('#hngcs-zone-id').val('');
                $('#hngcs-zone-form')[0].reset();
                $('#hngcs-cep-ranges').html('<div class="hngcs-cep-row"><input type="text" name="cep_from[]" placeholder="CEP Inicial" class="regular-text" style="width: 120px;"><span> até </span><input type="text" name="cep_to[]" placeholder="CEP Final" class="regular-text" style="width: 120px;"><button type="button" class="button hngcs-remove-cep">&times;</button></div>');
                $('#hngcs-methods').html('<div class="hngcs-method-row"><table class="widefat" style="margin-bottom: 10px;"><tr><td style="width: 40%;"><input type="text" name="method_name[]" class="widefat" placeholder="Nome do método"></td><td style="width: 20%;"><input type="number" name="method_cost[]" step="0.01" min="0" class="widefat" placeholder="Custo adicional" value="0"></td><td style="width: 25%;"><input type="text" name="method_time[]" class="widefat" placeholder="Prazo"></td><td style="width: 10%;"><label><input type="checkbox" name="method_enabled[]" value="yes" checked> Ativo</label></td><td style="width: 5%;"><button type="button" class="button hngcs-remove-method">&times;</button></td></tr></table></div>');
                $('#hngcs-zone-modal').show();
            });
            
            // Fechar modal
            $('#hngcs-cancel-zone, .hngcs-modal-close, .hngcs-modal-backdrop').on('click', function() {
                $('#hngcs-zone-modal').hide();
            });
            
            // Adicionar CEP
            $('#hngcs-add-cep').on('click', function() {
                $('#hngcs-cep-ranges').append('<div class="hngcs-cep-row"><input type="text" name="cep_from[]" placeholder="CEP Inicial" class="regular-text" style="width: 120px;"><span> até </span><input type="text" name="cep_to[]" placeholder="CEP Final" class="regular-text" style="width: 120px;"><button type="button" class="button hngcs-remove-cep">&times;</button></div>');
            });
            
            // Remover CEP
            $(document).on('click', '.hngcs-remove-cep', function() {
                if ($('.hngcs-cep-row').length > 1) {
                    $(this).closest('.hngcs-cep-row').remove();
                }
            });
            
            // Adicionar método
            $('#hngcs-add-method').on('click', function() {
                $('#hngcs-methods').append('<div class="hngcs-method-row"><table class="widefat" style="margin-bottom: 10px;"><tr><td style="width: 40%;"><input type="text" name="method_name[]" class="widefat" placeholder="Nome do método"></td><td style="width: 20%;"><input type="number" name="method_cost[]" step="0.01" min="0" class="widefat" placeholder="Custo adicional" value="0"></td><td style="width: 25%;"><input type="text" name="method_time[]" class="widefat" placeholder="Prazo"></td><td style="width: 10%;"><label><input type="checkbox" name="method_enabled[]" value="yes" checked> Ativo</label></td><td style="width: 5%;"><button type="button" class="button hngcs-remove-method">&times;</button></td></tr></table></div>');
            });
            
            // Remover método
            $(document).on('click', '.hngcs-remove-method', function() {
                if ($('.hngcs-method-row').length > 1) {
                    $(this).closest('.hngcs-method-row').remove();
                }
            });
            
            // Salvar zona
            $('#hngcs-zone-form').on('submit', function(e) {
                e.preventDefault();
                var $btn = $(this).find('button[type="submit"]');
                var orig = $btn.html();
                $btn.prop('disabled', true).html('Salvando...');
                
                $.post(ajaxurl, {
                    action: 'hng_save_custom_shipping_zone',
                    nonce: $('#hngcs_zone_nonce').val(),
                    data: $(this).serialize()
                }, function(res) {
                    $btn.prop('disabled', false).html(orig);
                    if (res.success) {
                        location.reload();
                    } else {
                        alert(res.data?.message || 'Erro ao salvar zona.');
                    }
                }).fail(function() {
                    $btn.prop('disabled', false).html(orig);
                    alert('Erro de conexão.');
                });
            });
            
            // Editar zona
            $(document).on('click', '.hngcs-edit-zone', function() {
                var $item = $(this).closest('.hngcs-zone-item');
                var zoneId = $item.data('zone-id');
                var zone = $item.data('zone');
                
                $('#hngcs-zone-id').val(zoneId);
                $('#hngcs-zone-name').val(zone.name || '');
                $('#hngcs-base-cost').val(zone.base_cost || 0);
                
                // Preencher CEPs
                $('#hngcs-cep-ranges').empty();
                if (zone.cep_ranges && zone.cep_ranges.length) {
                    zone.cep_ranges.forEach(function(range) {
                        $('#hngcs-cep-ranges').append('<div class="hngcs-cep-row"><input type="text" name="cep_from[]" value="' + (range.from || '') + '" class="regular-text" style="width: 120px;"><span> até </span><input type="text" name="cep_to[]" value="' + (range.to || '') + '" class="regular-text" style="width: 120px;"><button type="button" class="button hngcs-remove-cep">&times;</button></div>');
                    });
                } else {
                    $('#hngcs-cep-ranges').html('<div class="hngcs-cep-row"><input type="text" name="cep_from[]" class="regular-text" style="width: 120px;"><span> até </span><input type="text" name="cep_to[]" class="regular-text" style="width: 120px;"><button type="button" class="button hngcs-remove-cep">&times;</button></div>');
                }
                
                // Preencher métodos
                $('#hngcs-methods').empty();
                if (zone.methods && Object.keys(zone.methods).length) {
                    $.each(zone.methods, function(i, method) {
                        var html = '<div class="hngcs-method-row"><table class="widefat" style="margin-bottom:10px;"><tr>';
                        html += '<td style="width:40%;"><input type="text" name="method_name[]" class="widefat" value="' + (method.name || '') + '"></td>';
                        html += '<td style="width:20%;"><input type="number" name="method_cost[]" step="0.01" min="0" class="widefat" value="' + (method.additional_cost || 0) + '"></td>';
                        html += '<td style="width:25%;"><input type="text" name="method_time[]" class="widefat" value="' + (method.delivery_time || '') + '"></td>';
                        html += '<td style="width:10%;"><label><input type="checkbox" name="method_enabled[]" value="yes" ' + (method.enabled === 'yes' ? 'checked' : '') + '> Ativo</label></td>';
                        html += '<td style="width:5%;"><button type="button" class="button hngcs-remove-method">&times;</button></td>';
                        html += '</tr></table></div>';
                        $('#hngcs-methods').append(html);
                    });
                } else {
                    $('#hngcs-methods').html('<div class="hngcs-method-row"><table class="widefat" style="margin-bottom:10px;"><tr><td style="width:40%;"><input type="text" name="method_name[]" class="widefat" placeholder="Nome"></td><td style="width:20%;"><input type="number" name="method_cost[]" step="0.01" min="0" class="widefat" value="0"></td><td style="width:25%;"><input type="text" name="method_time[]" class="widefat" placeholder="Prazo"></td><td style="width:10%;"><label><input type="checkbox" name="method_enabled[]" value="yes" checked> Ativo</label></td><td style="width:5%;"><button type="button" class="button hngcs-remove-method">&times;</button></td></tr></table></div>');
                }
                
                $('#hngcs-zone-modal').show();
            });
            
            // Deletar zona
            $(document).on('click', '.hngcs-delete-zone', function() {
                if (!confirm('<?php echo esc_js(__('Tem certeza que deseja excluir esta zona?', 'hng-commerce')); ?>')) return;
                
                var zoneId = $(this).closest('.hngcs-zone-item').data('zone-id');
                
                $.post(ajaxurl, {
                    action: 'hng_delete_custom_shipping_zone',
                    nonce: '<?php echo esc_attr(wp_create_nonce('hng_custom_shipping_zone_nonce')); ?>',
                    zone_id: zoneId
                }, function(res) {
                    if (res.success) {
                        location.reload();
                    } else {
                        alert(res.data?.message || 'Erro ao excluir.');
                    }
                });
            });
        });
        
        // Animation keyframe
        var style = document.createElement('style');
        style.textContent = '@keyframes rotation { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }';
        document.head.appendChild(style);
        </script>
        <?php
    }
    
    /**
     * Renderizar item de zona
     */
    private static function render_zone_item($zone_id, $zone) {
        $cep_display = [];
        if (!empty($zone['cep_ranges'])) {
            foreach ($zone['cep_ranges'] as $range) {
                $cep_display[] = ($range['from'] ?? '') . ' - ' . ($range['to'] ?? '');
            }
        }
        ?>
        <div class="hngcs-zone-item" data-zone-id="<?php echo esc_attr($zone_id); ?>" data-zone='<?php echo esc_attr(json_encode($zone)); ?>'>
            <div>
                <div class="hngcs-zone-name"><?php echo esc_html($zone['name'] ?? __('Zona sem nome', 'hng-commerce')); ?></div>
                <div class="hngcs-zone-ceps"><?php echo esc_html(implode(', ', $cep_display)); ?></div>
                <?php if (!empty($zone['methods'])): ?>
                <div class="hngcs-zone-methods">
                    <?php foreach ($zone['methods'] as $method): ?>
                        <span class="hngcs-zone-method">
                            <?php echo esc_html($method['name'] ?? 'Método'); ?> 
                            - R$ <?php echo esc_html(number_format(floatval($zone['base_cost'] ?? 0) + floatval($method['additional_cost'] ?? 0), 2, ',', '.')); ?>
                            <?php if (!empty($method['delivery_time'])): ?>
                                (<?php echo esc_html($method['delivery_time']); ?>)
                            <?php endif; ?>
                        </span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="hngcs-zone-actions">
                <button type="button" class="button button-small hngcs-edit-zone">
                    <span class="dashicons dashicons-edit" style="font-size: 16px; width: 16px; height: 16px; margin-top: 2px;"></span>
                </button>
                <button type="button" class="button button-small hngcs-delete-zone">
                    <span class="dashicons dashicons-trash" style="font-size: 16px; width: 16px; height: 16px; margin-top: 2px;"></span>
                </button>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX: Salvar configurações
     */
    public function ajax_save_settings() {
        check_ajax_referer('hng_custom_shipping_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Sem permissão.', 'hng-commerce')]);
        }
        
        parse_str($_POST['data'], $data);
        
        $result = HNG_Shipping_Custom::save_settings($data);
        
        if ($result) {
            wp_send_json_success(['message' => __('Salvo com sucesso!', 'hng-commerce')]);
        } else {
            wp_send_json_error(['message' => __('Erro ao salvar.', 'hng-commerce')]);
        }
    }
    
    /**
     * AJAX: Salvar zona
     */
    public function ajax_save_zone() {
        check_ajax_referer('hng_custom_shipping_zone_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Sem permissão.', 'hng-commerce')]);
        }
        
        parse_str($_POST['data'], $data);
        
        $zones = get_option('hng_shipping_custom_zones', []);
        $zone_id = !empty($data['zone_id']) ? sanitize_key($data['zone_id']) : 'zone_' . time();
        
        // Montar dados da zona
        $zone = [
            'name' => sanitize_text_field($data['zone_name'] ?? ''),
            'base_cost' => floatval($data['base_cost'] ?? 0),
            'cep_ranges' => [],
            'methods' => [],
        ];
        
        // Processar faixas de CEP
        if (!empty($data['cep_from']) && is_array($data['cep_from'])) {
            foreach ($data['cep_from'] as $i => $from) {
                $to = $data['cep_to'][$i] ?? '';
                if (!empty($from) && !empty($to)) {
                    $zone['cep_ranges'][] = [
                        'from' => preg_replace('/\D/', '', $from),
                        'to' => preg_replace('/\D/', '', $to),
                    ];
                }
            }
        }
        
        // Processar métodos
        if (!empty($data['method_name']) && is_array($data['method_name'])) {
            foreach ($data['method_name'] as $i => $name) {
                if (!empty($name)) {
                    $zone['methods'][$i] = [
                        'name' => sanitize_text_field($name),
                        'additional_cost' => floatval($data['method_cost'][$i] ?? 0),
                        'delivery_time' => sanitize_text_field($data['method_time'][$i] ?? ''),
                        'enabled' => isset($data['method_enabled'][$i]) ? 'yes' : 'no',
                    ];
                }
            }
        }
        
        $zones[$zone_id] = $zone;
        update_option('hng_shipping_custom_zones', $zones);
        
        wp_send_json_success(['message' => __('Zona salva!', 'hng-commerce')]);
    }
    
    /**
     * AJAX: Deletar zona
     */
    public function ajax_delete_zone() {
        check_ajax_referer('hng_custom_shipping_zone_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Sem permissão.', 'hng-commerce')]);
        }
        
        $zone_id = sanitize_key($_POST['zone_id'] ?? '');
        
        if (empty($zone_id)) {
            wp_send_json_error(['message' => __('Zona não encontrada.', 'hng-commerce')]);
        }
        
        $zones = get_option('hng_shipping_custom_zones', []);
        unset($zones[$zone_id]);
        update_option('hng_shipping_custom_zones', $zones);
        
        wp_send_json_success(['message' => __('Zona excluída!', 'hng-commerce')]);
    }
    
    /**
     * Lista de estados brasileiros
     */
    private static function get_brazilian_states() {
        return [
            'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas',
            'BA' => 'Bahia', 'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo',
            'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul',
            'MG' => 'Minas Gerais', 'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná',
            'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte',
            'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina',
            'SP' => 'São Paulo', 'SE' => 'Sergipe', 'TO' => 'Tocantins'
        ];
    }
}

// Inicializar
HNG_Custom_Shipping_Settings::instance();
