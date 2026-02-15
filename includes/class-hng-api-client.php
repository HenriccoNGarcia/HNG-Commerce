<?php
/**
 * Cliente API Central (VPS)
 * Comunicação segura com servidor central para cálculo de taxas
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_API_Client {
    
    /**
     * URL da API Central (VPS)
     */
    private $api_url = 'https://api.hngdesenvolvimentos.com.br';
    
    /**
     * Timeout padrão (segundos)
     */
    private $timeout = 10;
    
    /**
     * Instância única
     */
    private static $instance = null;
    
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
        // Permitir alterar URL via constante (útil para testes)
        if (defined('HNG_API_URL')) {
            $this->api_url = HNG_API_URL;
        }
    }
    
    /**
     * Registrar novo merchant
     * 
     * @param array $data Dados do lojista
     * @return array|WP_Error
     */
    public function register_merchant($data) {
        $response = $this->make_request('/merchants/register', [
            'site_url' => home_url(),
            'email' => $data['email'],
            'phone' => $data['phone'] ?? '',
            'business_name' => get_bloginfo('name'),
            'plugin_version' => HNG_COMMERCE_VERSION
        ], 'POST');
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Salvar credenciais localmente (criptografadas)
        if ($response['success']) {
            update_option('hng_merchant_id', $response['merchant_id']);
            update_option('hng_api_key', HNG_Crypto::instance()->encrypt($response['api_key']));
            update_option('hng_webhook_secret', HNG_Crypto::instance()->encrypt($response['webhook_secret']));
            update_option('hng_merchant_status', $response['status']);
            update_option('hng_current_tier', $response['tier']);
        }
        
        return $response;
    }
    
    /**
     * Verificar status do merchant (Kill Switch)
     * 
     * @return array|WP_Error
     */
    public function verify_merchant() {
        $merchant_id = get_option('hng_merchant_id');
        $api_key = $this->get_api_key();
        
        if (!$merchant_id || !$api_key) {
            return new WP_Error('not_configured', 'Merchant não configurado. Acesse HNG Commerce ? Conectar Conta.');
        }
        
        $response = $this->make_request('/merchants/verify', [
            'merchant_id' => $merchant_id,
            'api_key' => $api_key
        ], 'POST');
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Atualizar status local
        update_option('hng_merchant_status', $response['status']);
        update_option('hng_current_tier', $response['tier']);
        
        // Kill Switch ativado?
        if ($response['status'] === 'banned') {
            return new WP_Error('banned', $response['reason'] ?? 'Conta suspensa.');
        }
        
        return $response;
    }
    
    /**
     * Calcular taxas (FONTE DA VERDADE)
     * 
     * @param array $order_data Dados do pedido
     * @return array|WP_Error
     */
    public function calculate_fee($order_data) {
        $merchant_id = get_option('hng_merchant_id');
        $api_key = $this->get_api_key();
        
        if (!$merchant_id || !$api_key) {
            // Fallback: usar cálculo local
            return $this->fallback_calculate($order_data);
        }
        
        $product_type = isset($order_data['product_type']) ? $order_data['product_type'] : 'physical';
        if (class_exists('HNG_Product_Types')) {
            $product_type = HNG_Product_Types::normalize($product_type);
        } else {
            $product_type = sanitize_key($product_type);
        }

        $response = $this->make_request('/transactions/calculate', [
            'merchant_id' => $merchant_id,
            'api_key' => $api_key,
            'amount' => $order_data['amount'],
            'product_type' => $product_type,
            'gateway' => $order_data['gateway'],
            'payment_method' => $order_data['payment_method'],
            'order_id' => $order_data['order_id'] ?? null
        ], 'POST');
        
        if (is_wp_error($response)) {
            // VPS offline: usar fallback (log condicionado)
            $msg = 'HNG: VPS offline, usando cálculo local - ' . $response->get_error_message();
            if (function_exists('hng_files_log_append')) {
                hng_files_log_append(HNG_COMMERCE_PATH . 'logs/api-client.log', $msg . PHP_EOL);
            }
            return $this->fallback_calculate($order_data);
        }
        
        // VALIDAR ASSINATURA (CRÍTICO!)
        if (!$this->verify_signature($response)) {
            $msg = 'HNG: Assinatura inválida detectada! Possível adulteração.';
            if (function_exists('hng_files_log_append')) {
                hng_files_log_append(HNG_COMMERCE_PATH . 'logs/api-client.log', $msg . PHP_EOL);
            }

            // Por segurança, usar cálculo local
            return $this->fallback_calculate($order_data);
        }
        
        // Salvar no log para auditoria
        $this->log_transaction($order_data, $response);
        
        return $response;
    }
    
    /**
     * Registrar transação no servidor central
     * 
     * @param int $order_id
     * @param array $transaction_data
     * @return array|WP_Error
     */
    public function register_transaction($order_id, $transaction_data) {
        $merchant_id = get_option('hng_merchant_id');
        $api_key = $this->get_api_key();
        
        return $this->make_request('/transactions/register', [
            'merchant_id' => $merchant_id,
            'api_key' => $api_key,
            'order_id' => $order_id,
            'amount' => $transaction_data['amount'],
            'gateway' => $transaction_data['gateway'],
            'payment_method' => $transaction_data['payment_method'],
            'plugin_fee' => $transaction_data['plugin_fee'],
            'gateway_fee' => $transaction_data['gateway_fee'],
            'net_amount' => $transaction_data['net_amount'],
            'tier' => $transaction_data['tier']
        ], 'POST');
    }
    
    /**
     * Verificar assinatura HMAC-SHA256
     * 
     * @param array $data Dados recebidos da API
     * @return bool
     */
    private function verify_signature($data) {
        if (!isset($data['signature']) || !isset($data['timestamp'])) {
            return false;
        }
        
        $webhook_secret = $this->get_webhook_secret();
        if (!$webhook_secret) {
            return false;
        }
        
        $received_signature = $data['signature'];
        
        // Reconstruir payload (ordem importante!)
        $payload = wp_json_encode([
            'tier' => $data['tier'],
            'plugin_fee' => $data['plugin_fee'],
            'gateway_fee' => $data['gateway_fee'],
            'net_amount' => $data['net_amount'],
            'timestamp' => $data['timestamp']
        ], JSON_UNESCAPED_SLASHES);
        
        $expected_signature = hash_hmac('sha256', $payload, $webhook_secret);
        
        // Comparação timing-attack resistant
        return hash_equals($expected_signature, $received_signature);
    }
    
    /**
     * Fallback: Cálculo local (modo degradado)
     * 
     * @param array $order_data
     * @return array
     */
    private function fallback_calculate($order_data) {
        $msg = 'HNG: Usando cálculo local (fallback)';
        if (function_exists('hng_files_log_append')) {
            hng_files_log_append(HNG_COMMERCE_PATH . 'logs/api-client.log', $msg . PHP_EOL);
        }
        
        // Avisar admin
        add_action('admin_notices', function() {
            echo '<div class="notice notice-warning is-dismissible">
                <p><strong>HNG Commerce:</strong> Servidor central offline. Usando cálculo de taxas local (pode haver divergências). Verifique a conexão.</p>
            </div>';
        });
        
        $calculator = HNG_Fee_Calculator::instance();
        $fees = $calculator->calculate_all_fees(
            $order_data['amount'],
            $order_data['product_type'],
            $order_data['gateway'],
            $order_data['payment_method']
        );
        
        // Adicionar flag de fallback
        $fees['is_fallback'] = true;
        $fees['fallback_reason'] = 'VPS offline';
        
        return $fees;
    }
    
    /**
     * Fazer requisição HTTP à API
     * 
     * @param string $endpoint
     * @param array $data
     * @param string $method
     * @return array|WP_Error
     */
    private function make_request($endpoint, $data = [], $method = 'POST') {
        $url = $this->api_url . $endpoint;
        
        $args = [
            'method' => $method,
            'timeout' => $this->timeout,
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent' => 'HNG-Commerce/' . HNG_COMMERCE_VERSION,
                'X-HNG-Plugin-Version' => HNG_COMMERCE_VERSION,
                'X-HNG-Site-URL' => home_url()
            ]
        ];
        
        if ($method === 'POST' || $method === 'PUT') {
            $args['body'] = wp_json_encode($data);
        }

        // Inserir API Key e Merchant ID nos headers quando disponível
        $api_key = $this->get_api_key();
        $merchant_id = get_option('hng_merchant_id');
        if ($api_key) {
            $args['headers']['X-Hng-Api-Key'] = $api_key;
        }
        if ($merchant_id) {
            $args['headers']['X-Hng-Merchant-Id'] = $merchant_id;
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);
        
        // Erro HTTP
        if ($status_code >= 400) {
            return new WP_Error(
                'api_error',
                $decoded['message'] ?? 'Erro na comunicação com servidor central',
                ['status' => $status_code, 'response' => $decoded]
            );
        }
        
        return $decoded;
    }
    
    /**
     * Obter API Key descriptografada
     * 
     * @return string|false
     */
    private function get_api_key() {
        $encrypted = get_option('hng_api_key');
        if (!$encrypted) {
            return false;
        }
        
        return HNG_Crypto::instance()->decrypt($encrypted);
    }
    
    /**
     * Obter Webhook Secret descriptografado
     * 
     * @return string|false
     */
    private function get_webhook_secret() {
        $encrypted = get_option('hng_webhook_secret');
        if (!$encrypted) {
            return false;
        }
        
        return HNG_Crypto::instance()->decrypt($encrypted);
    }
    
    /**
     * Registrar transação no log local (auditoria)
     * 
     * @param array $order_data
     * @param array $response
     */
    private function log_transaction($order_data, $response) {
        if (!function_exists('hng_files_log_append')) {
            return;
        }

        // Checar opá§á¡o de log (compatibilidade: aceitamos 1/0 ou 'yes')
        $log_opt = get_option('hng_transaction_log', false);
        if (! $log_opt && $log_opt !== 'yes' && $log_opt !== '1') {
            return;
        }

        $log_entry = sprintf(
            "[%s] Order: %d | Amount: R$ %.2f | Tier: %d | Plugin Fee: R$ %.2f | Gateway Fee: R$ %.2f | Net: R$ %.2f | Signature: %s\n",
            gmdate('Y-m-d H:i:s'),
            $order_data['order_id'] ?? 0,
            $order_data['amount'],
            $response['tier'],
            $response['plugin_fee'],
            $response['gateway_fee'],
            $response['net_amount'],
            substr($response['signature'], 0, 16) . '...'
        );
        $log_file = WP_CONTENT_DIR . '/hng-transactions.log';
        hng_files_log_append($log_file, $log_entry);
    }
    
    /**
     * Verificar atualizações críticas
     * 
     * @return array|false
     */
    public function check_updates() {
        $response = $this->make_request('/updates/check', [
            'merchant_id' => get_option('hng_merchant_id'),
            'current_version' => HNG_COMMERCE_VERSION,
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION
        ], 'POST');
        
        if (is_wp_error($response)) {
            return false;
        }
        
        return $response;
    }
    
    /**
     * Obter GMV atualizado do servidor
     * 
     * @return array|WP_Error
     */
    public function get_gmv_stats() {
        $merchant_id = get_option('hng_merchant_id');
        $api_key = $this->get_api_key();
        
        return $this->make_request('/merchants/gmv', [
            'merchant_id' => $merchant_id,
            'api_key' => $api_key
        ], 'POST');
    }

    /**
     * Regenerar API Key para este merchant
     * @return array|WP_Error
     */
    public function regenerate_api_key() {
        $merchant_id = get_option('hng_merchant_id');
        if (!$merchant_id) {
            return new WP_Error('not_configured', 'Merchant não configurado.');
        }

        $response = $this->make_request('/merchants/regenerate', [
            'merchant_id' => $merchant_id
        ], 'POST');

        return $response;
    }
}
