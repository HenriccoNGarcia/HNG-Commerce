<?php
/**
 * HNG Security - Camada de Segurança
 *
 * Implementa proteções adicionais:
 * - Rate limiting
 * - CSRF protection
 * - XSS/SQL injection prevention
 * - Security headers
 * - Integração com HNG Painel de Segurança
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Security {
    
    /**
    * Instância única
     */
    private static $instance = null;
    
    /**
    * HNG Painel de Segurança ativo?
     */
    private $hng_security_active = false;
    
    /**
    * Obter instância
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Construtor
     */
    private function __construct() {
        $this->init_hooks();
        $this->check_hng_security_integration();
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // CSRF protection
        add_action('init', [$this, 'init_csrf_protection']);
        
        // Rate limiting
        add_action('init', [$this, 'check_rate_limit']);
        
        // Input sanitization
        add_filter('hng_sanitize_input', [$this, 'sanitize_input'], 10, 2);
        
        // Security headers
        add_action('send_headers', [$this, 'send_security_headers']);
        
        // Prevenir SQL injection
        add_filter('hng_prepare_query', [$this, 'prepare_query'], 10, 2);
        
        // Log de atividades suspeitas
        add_action('hng_suspicious_activity', [$this, 'log_suspicious_activity'], 10, 2);
        
        // Rotação automática de logs (daily)
        add_action('hng_daily_maintenance', [$this, 'rotate_security_logs']);
        
        // Schedule daily maintenance if not scheduled
        if (!wp_next_scheduled('hng_daily_maintenance')) {
            wp_schedule_event(time(), 'daily', 'hng_daily_maintenance');
        }
    }
    
    /**
    * Verificar integração com HNG Segurança
     */
    private function check_hng_security_integration() {
        // Verificar se HNG Painel de Segurança está ativo
        if (class_exists('HNG_Security_Panel')) {
            $this->hng_security_active = true;
            
            // Hooks de integração
            add_action('hng_commerce_suspicious_activity', [$this, 'forward_to_hng_security'], 10, 3);
            add_filter('hng_commerce_check_ip', [$this, 'check_ip_with_hng_security'], 10, 1);
        }
    }
    
    /**
    * Inicializar proteção CSRF
     */
    public function init_csrf_protection() {
        if (!session_id()) {
            session_start();
        }
        
        // Gerar token CSRF se não existir
        if (empty($_SESSION['hng_csrf_token'])) {
            $_SESSION['hng_csrf_token'] = bin2hex(random_bytes(32));
        }
    }
    
    /**
     * Obter token CSRF
     */
    public function get_csrf_token() {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- CSRF token is cryptographically secure string
        return $_SESSION['hng_csrf_token'] ?? '';
    }
    
    /**
     * Verificar token CSRF
     * 
     * @param string $token Token recebido
    * @return bool True se válido
     */
    public function verify_csrf_token($token) {
        $session_token = $this->get_csrf_token();
        
        if (empty($session_token) || empty($token)) {
            return false;
        }
        
        // Comparação timing-safe
        if (hash_equals($session_token, $token)) {
            return true;
        }
        
        // Log de tentativa CSRF
            do_action('hng_suspicious_activity', 'csrf_failure', [
                'ip' => $this->get_client_ip(),
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '',
            ]);
        
        return false;
    }
    
    /**
     * Check rate limiting
     */
    public function check_rate_limit() {
        $ip = $this->get_client_ip();
        $endpoint = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        
        // Endpoints críticos com rate limit
        $critical_endpoints = [
            '/checkout' => ['limit' => 10, 'window' => 300], // 10 req/5min
            '/minha-conta/login' => ['limit' => 5, 'window' => 300], // 5 req/5min
            '/carrinho/add' => ['limit' => 30, 'window' => 60], // 30 req/1min
        ];
        
        foreach ($critical_endpoints as $path => $config) {
            if (strpos($endpoint, $path) !== false) {
                if (!$this->check_rate($ip, $path, $config['limit'], $config['window'])) {
                    // Rate limit excedido
                    do_action('hng_suspicious_activity', 'rate_limit_exceeded', [
                        'ip' => $ip,
                        'endpoint' => $path,
                    ]);
                    
                    wp_die(
                        esc_html__('Muitas requisições. Tente novamente em alguns minutos.', 'hng-commerce'),
                        esc_html__('Rate Limit', 'hng-commerce'),
                        ['response' => 429]
                    );
                }
            }
        }
    }
    
    /**
     * Verificar rate limit
     */
    private function check_rate($ip, $endpoint, $limit, $window) {
        $transient_key = 'hng_rate_' . md5($ip . $endpoint);
        $requests = get_transient($transient_key);
        
        if ($requests === false) {
            // Primeira requisição
            set_transient($transient_key, 1, $window);
            return true;
        }
        
        if ($requests >= $limit) {
            return false;
        }
        
        // Incrementar contador
        set_transient($transient_key, $requests + 1, $window);
        return true;
    }
    
    /**
     * Sanitizar input
     * 
     * @param mixed $input Dado de entrada
     * @param string $type Tipo (text, email, url, int, float, html)
     * @return mixed Dado sanitizado
     */
    public function sanitize_input($input, $type = 'text') {
        switch ($type) {
            case 'email':
                return sanitize_email($input);
            
            case 'url':
                return esc_url_raw($input);
            
            case 'int':
                return intval($input);
            
            case 'float':
                return floatval($input);
            
            case 'html':
                return wp_kses_post($input);
            
            case 'textarea':
                return sanitize_textarea_field($input);
            
            case 'text':
            default:
                return sanitize_text_field($input);
        }
    }
    
    /**
     * Preparar query SQL
     */
    public function prepare_query($query, $args = []) {
        global $wpdb;
        
        if (empty($args)) {
            return $query;
        }

        // Desempacotar parâmetros como argumentos individuais para wpdb->prepare
        if (is_array($args)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            return $wpdb->prepare($query, ...$args);
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->prepare($query, $args);
    }
    
    /**
     * Enviar headers de segurança
     */
    public function send_security_headers() {
        if (headers_sent()) {
            return;
        }
        
        // X-Frame-Options
        header('X-Frame-Options: SAMEORIGIN');
        
        // X-Content-Type-Options
        header('X-Content-Type-Options: nosniff');
        
        // X-XSS-Protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Referrer-Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Content-Security-Policy
        $csp_directives = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://www.google.com https://www.gstatic.com https://js.stripe.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "img-src 'self' data: https: blob:",
            "font-src 'self' data: https://fonts.gstatic.com",
            "connect-src 'self' https://api.asaas.com https://api.mercadopago.com https://api.pagar.me",
            "frame-src 'self' https://www.google.com https://js.stripe.com",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ];
        
        header('Content-Security-Policy: ' . implode('; ', $csp_directives));
        
        // HSTS (apenas em HTTPS)
        if (is_ssl()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
        
        // Permissions-Policy
        $permissions = [
            'geolocation=()',
            'microphone=()',
            'camera=()',
            'payment=(self)',
        ];
        header('Permissions-Policy: ' . implode(', ', $permissions));
    }
    
    /**
     * Obter IP do cliente
     */
    public function get_client_ip() {
        $ip_keys = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- IP validated with filter_var
                $ip = $_SERVER[$key]; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                
                // Pegar primeiro IP se houver múltiplos
                if (strpos((string) $ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Validar cartão de crédito (Algoritmo de Luhn)
     */
    public function validate_card_number($number) {
        $number = preg_replace('/\D/', '', $number);
        
        if (strlen($number) < 13 || strlen($number) > 19) {
            return false;
        }
        
        $sum = 0;
        $length = strlen($number);
        
        for ($i = 0; $i < $length; $i++) {
            $digit = intval($number[$length - $i - 1]);
            
            if ($i % 2 === 1) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            
            $sum += $digit;
        }
        
        return ($sum % 10 === 0);
    }
    
    /**
     * Log de atividade suspeita
     */
    public function log_suspicious_activity($type, $data) {
        global $wpdb;
        
        $table_full = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_security_log') : ($wpdb->prefix . 'hng_security_log');

        $wpdb->insert($table_full, [
            'type' => $type,
            'ip' => $this->get_client_ip(),
            'user_id' => get_current_user_id(),
            'data' => wp_json_encode($data),
            'created_at' => current_time('mysql'),
        ]);
        
        // Notificar admin em casos críticos
        $critical_types = ['csrf_failure', 'sql_injection_attempt', 'xss_attempt'];
        if (in_array($type, $critical_types)) {
            $this->notify_admin_security_alert($type, $data);
        }
    }
    
    /**
     * Rotate security logs - Keep only last 90 days
     * 
     * @return int Number of deleted rows
     */
    public function rotate_security_logs() {
        global $wpdb;
        
        $table_full = function_exists('hng_db_full_table_name') 
            ? hng_db_full_table_name('hng_security_log') 
            : ($wpdb->prefix . 'hng_security_log');
        
        // Delete logs older than 90 days
        $retention_days = apply_filters('hng_security_log_retention_days', 90);
        
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM `{$table_full}` WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $retention_days
            )
        );
        
        // Log rotation event
        if ($deleted > 0) {
            error_log(sprintf(
                'HNG Security: Rotated %d log entries older than %d days',
                $deleted,
                $retention_days
            ));
        }
        
        return $deleted;
    }
    
    /**
     * Notificar admin sobre alerta
     */
    private function notify_admin_security_alert($type, $data) {
        $admin_email = get_option('admin_email');
        /* translators: %1$s: site name */
        $subject = sprintf(esc_html__('[%1$s] Alerta de Segurança HNG Commerce', 'hng-commerce'), get_bloginfo('name'));
        
        $message = sprintf(esc_html__('Atividade suspeita detectada:

Tipo: %1\$s
IP: %2\$s
Data: %3\$s
Detalhes: %4\$s', 'hng-commerce'),
            $type,
            $this->get_client_ip(),
            current_time('mysql'),
            wp_json_encode($data)
        );
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Encaminhar para HNG Segurança
     */
    public function forward_to_hng_security($type, $data, $severity) {
        if ($this->hng_security_active && class_exists('HNG_Security_Panel')) {
            do_action('hng_security_log_event', [
                'source' => 'HNG Commerce',
                'type' => $type,
                'severity' => $severity,
                'ip' => $this->get_client_ip(),
                'data' => $data,
            ]);
        }
    }
    
    /**
     * Verificar IP com HNG Segurança
     */
    public function check_ip_with_hng_security($ip) {
        if ($this->hng_security_active && class_exists('HNG_IP_Manager')) {
            $ip_manager = HNG_IP_Manager::instance();
            return !$ip_manager->is_blocked($ip);
        }
        
        return true; // Permitir se HNG Segurança não estiver ativo
    }
    
    /**
     * Obter informações de diagnóstico
     */
    public function get_diagnostics() {
        return [
            'hng_security_integration' => $this->hng_security_active ? 'Ativo' : 'Inativo',
            'csrf_protection' => !empty($this->get_csrf_token()) ? 'Ativo' : 'Inativo',
            'rate_limiting' => 'Ativo',
            'security_headers' => 'Ativo',
            'https_enforced' => is_ssl() ? 'Sim' : 'Não',
            'client_ip' => $this->get_client_ip(),
        ];
    }
}
