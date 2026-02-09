<?php
/**
 * Domain Binding & Heartbeat
 * 
 * Valida que o plugin está rodando no domínio correto.
 * Impede roubo de chaves API detectando mudanças de domínio.
 * 
 * @package HNG_Commerce
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Domain_Heartbeat {
    
    const HEARTBEAT_INTERVAL = HOUR_IN_SECONDS; // Check a cada hora
    const DOMAIN_MISMATCH_ACTION = 'disable_payments'; // 'alert', 'disable_payments', 'shutdown'
    
    /**
     * Inicializar hooks
     */
    public static function init() {
        // Hourly heartbeat check
        add_action('wp_loaded', [__CLASS__, 'check_domain_binding']);
        
        // Desabilitar pagamentos se domínio errado
        add_filter('hng_can_process_payment', [__CLASS__, 'filter_payment_allowed']);
    }
    
    /**
     * Verificar binding de domínio
     * 
     * @return bool True se domínio está correto
     */
    public static function check_domain_binding() {
        $bound_domain = get_option('hng_bound_domain');
        $current_domain = self::get_current_domain();
        $last_check = get_option('hng_domain_check_last', 0);
        
        // Check no máximo a cada hora
        if (time() - $last_check < self::HEARTBEAT_INTERVAL) {
            return true;
        }
        
        update_option('hng_domain_check_last', time());
        
        // Primeira execução: registrar domínio
        if (empty($bound_domain)) {
            update_option('hng_bound_domain', $current_domain);
            return true;
        }
        
        // Verificar se domínio mudou
        if ($bound_domain !== $current_domain) {
            return self::handle_domain_mismatch($bound_domain, $current_domain);
        }
        
        return true;
    }
    
    /**
     * Obter domínio atual (sem protocolo/porta)
     * 
     * @return string
     */
    public static function get_current_domain() {
        $site_url = get_option('siteurl', '');
        $parsed = wp_parse_url($site_url);
        $host = isset($parsed['host']) ? strtolower($parsed['host']) : '';
        
        // Remover www se presente
        if (strpos($host, 'www.') === 0) {
            $host = substr($host, 4);
        }
        
        return $host;
    }
    
    /**
     * Manejar mismatch de domínio
     * 
     * @param string $expected Domínio esperado
     * @param string $current Domínio atual
     * @return bool
     */
    private static function handle_domain_mismatch($expected, $current) {
        $action = apply_filters('hng_domain_mismatch_action', self::DOMAIN_MISMATCH_ACTION);
        $mismatch_count = (int)get_option('hng_domain_mismatch_count', 0);
        
        // Registrar tentativa
        $mismatch_count++;
        update_option('hng_domain_mismatch_count', $mismatch_count);
        
        // Log
        error_log(sprintf(
            '[HNG] Domain mismatch detected #%d: Expected %s, got %s. Action: %s',
            $mismatch_count,
            $expected,
            $current,
            $action
        ));
        
        // Disparar alerta
        do_action('hng_domain_mismatch', $expected, $current, $mismatch_count);
        
        // Aplicar ação
        if ($action === 'shutdown') {
            wp_die('HNG Commerce: Plugin foi movido para domínio não autorizado. Contacte administrador.');
        }
        
        if ($action === 'disable_payments') {
            add_action('admin_notices', [__CLASS__, 'show_domain_warning']);
            return false;
        }
        
        return true;
    }
    
    /**
     * Mostrar aviso de domínio em admin
     */
    public static function show_domain_warning() {
        $bound_domain = get_option('hng_bound_domain');
        $current_domain = self::get_current_domain();
        
        if ($bound_domain === $current_domain) {
            return;
        }
        
        ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <strong>HNG Commerce - Aviso de Segurança:</strong><br>
                O plugin foi configurado para rodar em <code><?php echo esc_html($bound_domain); ?></code>,
                mas está rodando em <code><?php echo esc_html($current_domain); ?></code>.<br>
                <strong>Pagamentos estão desabilitados.</strong> Contacte o administrador.
            </p>
        </div>
        <?php
    }
    
    /**
     * Filtro para bloquear pagamentos se domínio errado
     * 
     * @param bool $allowed
     * @return bool
     */
    public static function filter_payment_allowed($allowed) {
        if (!$allowed) {
            return false;
        }
        
        return self::check_domain_binding();
    }
    
    /**
     * Rebind domínio (após movimentação autorizada)
     * 
     * @param string $new_domain Novo domínio (vazio = usar atual)
     * @return bool
     */
    public static function rebind_domain($new_domain = '') {
        if (empty($new_domain)) {
            $new_domain = self::get_current_domain();
        }
        
        update_option('hng_bound_domain', $new_domain);
        delete_option('hng_domain_mismatch_count');
        
        do_action('hng_domain_rebound', $new_domain);
        
        return true;
    }
    
    /**
     * Obter status de domínio
     * 
     * @return array
     */
    public static function get_status() {
        $bound = get_option('hng_bound_domain', 'não configurado');
        $current = self::get_current_domain();
        $mismatches = (int)get_option('hng_domain_mismatch_count', 0);
        $last_check = get_option('hng_domain_check_last', 0);
        
        return [
            'bound_domain' => $bound,
            'current_domain' => $current,
            'is_valid' => ($bound === $current),
            'mismatch_count' => $mismatches,
            'last_check' => $last_check > 0 ? gmdate('Y-m-d H:i:s', $last_check) : 'nunca',
        ];
    }
}

// Initialize on load
if (!is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
    HNG_Domain_Heartbeat::init();
}
