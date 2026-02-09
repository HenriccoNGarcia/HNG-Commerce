<?php
/**
 * HNG PagSeguro Settings
 * NÃO configura Receiver IDs aqui - isso fica na API Manager
 * 
 * @package HNG_Commerce
 * @since 1.2.16
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_PagSeguro_Settings {
    
    public static function init() {
        add_action('wp_loaded', [__CLASS__, 'register_settings']);
    }
    
    /**
     * Registrar opções
     */
    public static function register_settings() {
        register_setting('hng_pagseguro_split', 'hng_split_logging_enabled', [
            'sanitize_callback' => function($value) {
                return $value ? '1' : '0';
            }
        ]);
    }
    
    /**
     * Adicionar página de configuração
     */
    public static function add_submenu() {
        add_submenu_page(
            'hng-commerce',
            'PagBank Split Payment',
            'PagBank Split Payment',
            'manage_options',
            'hng-pagseguro-split-settings',
            [__CLASS__, 'render_settings_page']
        );
    }
    
    /**
     * Renderizar página de configuração
     */
    public static function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Permissão negada');
        }
        
        // Salvar configurações
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'hng_save_pagseguro_split') {
            check_admin_referer('hng_pagseguro_split_nonce');
            
            $logging_enabled = isset($_POST['hng_split_logging_enabled']) ? '1' : '0';
            update_option('hng_split_logging_enabled', $logging_enabled);
            
            echo '<div class="notice notice-success is-dismissible"><p>Configurações salvas com sucesso!</p></div>';
        }
        
        $logging_enabled = get_option('hng_split_logging_enabled', true);
        $api_key = self::get_api_key();
        $merchant_id = get_option('hng_merchant_id', '');
        ?>
        
        <div class="wrap">
            <h1>Configuração de Split Payment - PagBank</h1>
            
            <div class="notice notice-info"><p>
                <strong>ℹ️ IMPORTANTE:</strong> Os Receiver IDs são configurados na <strong>API Manager</strong>, não aqui.<br>
                Esta página serve apenas para monitorar logs e configurações de sistema.
            </p></div>
            
            <div class="notice notice-success"><p>
                <strong>Status da Integração:</strong><br>
                ✓ API Key configurada: <?php echo $api_key ? '✓' : '✗'; ?><br>
                ✓ Merchant ID: <?php echo $merchant_id ? esc_html($merchant_id) : 'não configurado'; ?><br>
                ✓ Split Payment: ✓ Funcionando automaticamente
            </p></div>
            
            <form method="post" action="">
                <?php wp_nonce_field('hng_pagseguro_split_nonce'); ?>
                <input type="hidden" name="action" value="hng_save_pagseguro_split">
                
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">
                            <label for="hng_split_logging_enabled">Habilitar Logging de Split Payments</label>
                        </th>
                        <td>
                            <input type="checkbox" id="hng_split_logging_enabled" name="hng_split_logging_enabled" 
                                   value="1" <?php checked($logging_enabled, '1'); ?>>
                            <label for="hng_split_logging_enabled">
                                Registrar todos os cálculos de split payment em log
                            </label>
                            <p class="description">
                                Útil para auditoria e debugging. Os logs são salvos em wp-content/hng-split-payments.log
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Salvar Configurações'); ?>
            </form>
            
            <h2>Como Funciona o Split Payment</h2>
            <ol>
                <li><strong>Cliente coloca credenciais no plugin:</strong>
                    <ul>
                        <li>Acessa: https://minhaconta.pagbank.com.br</li>
                        <li>Obtém seu Account ID (ACCO_XXXXX)</li>
                        <li>Configura no plugin (gateway PagBank)</li>
                    </ul>
                </li>
                <li><strong>Cliente faz uma venda:</strong> 
                    <ul>
                        <li>Sistema calcula taxa automaticamente</li>
                        <li>Envia para PagBank com split rules</li>
                    </ul>
                </li>
                <li><strong>PagBank distribui automaticamente:</strong>
                    <ul>
                        <li>Cliente recebe 97,52% (R$ 97,52 de R$ 100)</li>
                        <li>HNG recebe 1,49% (sua comissão)</li>
                        <li>PagBank recebe 0,99% (taxa de gateway)</li>
                    </ul>
                </li>
                <li><strong>Configuração de split na API Manager:</strong>
                    <ul>
                        <li>Você configura quantos % HNG recebe</li>
                        <li>Quantos % o PagBank recebe</li>
                        <li>O resto vai para o cliente</li>
                    </ul>
                </li>
            </ol>
            
            <h2>Informações Técnicas</h2>
            <div class="card" style="max-width: 100%; padding: 20px; margin-top: 20px;">
                <h3>Fluxo de Split Payment</h3>
                <pre style="background: #f5f5f5; padding: 10px; border-radius: 4px;">
Cliente faz pagamento (via credenciais dele)
        ↓
Sistema calcula taxas via API Manager
        ↓
Cria split_rules com:
  • Account ID do cliente → Valor líquido
  • Account ID de HNG → Comissão
  • Account ID do PagBank → Taxa
        ↓
Envia para PagBank com as regras
        ↓
PagBank distribui automaticamente:
  • Cliente recebe: 97,52
  • HNG recebe: 1,49
  • PagBank recebe: 0,99
        ↓
Sistema registra no servidor central
                </pre>
            </div>
            
            <h2>Logs de Split Payment</h2>
            <div class="card" style="max-width: 100%; padding: 20px; margin-top: 20px;">
                <?php self::render_logs(); ?>
            </div>
        </div>
        
        <?php
    }
    
    /**
     * Renderizar logs
     */
    private static function render_logs() {
        $log_file = WP_CONTENT_DIR . '/hng-split-payments.log';
        
        if (!file_exists($log_file)) {
            echo '<p><em>Nenhum log encontrado ainda.</em></p>';
            return;
        }
        
        $logs = file_get_contents($log_file);
        $lines = explode("\n", trim($logs));
        
        // Mostrar últimas 50 linhas
        $lines = array_slice($lines, -50);
        
        echo '<pre style="background: #f5f5f5; padding: 10px; border-radius: 4px; max-height: 400px; overflow-y: auto; font-size: 12px;">';
        foreach (array_reverse($lines) as $line) {
            echo esc_html($line) . "\n";
        }
        echo '</pre>';
    }
    
    /**
     * Obter API Key descriptografada
     */
    private static function get_api_key() {
        $encrypted = get_option('hng_api_key');
        if (!$encrypted) {
            return false;
        }
        
        if (!class_exists('HNG_Crypto')) {
            return '*** (Classe de criptografia não disponível)';
        }
        
        return HNG_Crypto::instance()->decrypt($encrypted);
    }
}

// Inicializar
HNG_PagSeguro_Settings::init();
