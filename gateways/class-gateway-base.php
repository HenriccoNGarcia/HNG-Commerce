<?php
/**
 * Classe Base para Gateways de Pagamento
 *
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class HNG_Payment_Gateway {
    
    /**
     * ID do gateway
     */
    public $id = '';
    
    /**
     * Título do gateway
     */
    public $title = '';
    
    /**
     * Descrição
     */
    public $description = '';
    
    /**
     * Habilitado
     */
    public $enabled = false;
    
    /**
     * Ícone
     */
    public $icon = '';
    
    /**
     * Métodos suportados
     */
    public $supported_methods = [];

    /**
     * Compatibilidade: propriedade usada por gateways legados
     */
    public $supports = [];

    /**
     * Propriedades comuns usadas por gateways (declarações para evitar
     * criação dinâmica de propriedades e depreciações em PHP 8.2+).
     */
    public $api_url = '';
    public $access_token = '';
    public $public_key = '';
    public $sandbox = false;
    public $environment = 'sandbox';
    public $api_key = '';
    public $hng_wallet_id = '';
    
    /**
     * Rate limiting settings
     */
    private $rate_limit_max = 60; // Max requests per window
    private $rate_limit_window = 60; // Window in seconds
    
    /**
     * Retry settings
     */
    private $max_retries = 3;
    private $retry_delay = 1; // seconds
    
    /**
     * Construtor
     */
    public function __construct() {
        // Registrar hooks após inicialização para permitir que gateways
        // configurem propriedades (como $id) em seus construtores antes
        // do registro efetivo.
        add_action('init', [$this, 'register_hooks']);
    }

    /**
     * Registra hooks que dependem de propriedades do gateway
     */
    public function register_hooks() {
        // Carregar configuração genérica salva (se houver) antes de registrar hooks
        if (!empty($this->id)) {
            $this->load_config_from_option($this->id);
            add_action('hng_process_payment_' . $this->id, [$this, 'process_payment'], 10, 2);
        }
    }
    
    /**
     * Verificar se gateway está habilitado
     */
    public function is_enabled() {
        return $this->enabled === true;
    }
    
    /**
     * Verificar se gateway está configurado
     */
    public function is_configured() {
        // Considera configurado se existir alguma credencial mínima
        return !empty($this->api_key) || !empty($this->access_token) || !empty($this->public_key) || !empty($this->api_url);
    }

    /**
     * Carrega configuração genérica (JSON) salva na option `hng_gateway_{id}_config`
     * e popula propriedades públicas do gateway quando os nomes coincidem.
     *
     * @param string $gateway_id
     * @return void
     */
    protected function load_config_from_option($gateway_id) {
        if (empty($gateway_id)) {
            return;
        }

        $opt_name = 'hng_gateway_' . $gateway_id . '_config';
        $raw = get_option($opt_name, '');
        if (empty($raw)) {
            return;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return;
        }

        // Mapeamento seguro: apenas algumas chaves são aplicadas automaticamente
        $map = [
            'api_url' => 'api_url',
            'api_key' => 'api_key',
            'token' => 'api_key',
            'secret_key' => 'api_key',
            'access_token' => 'access_token',
            'public_key' => 'public_key',
            'wallet_id' => 'hng_wallet_id',
            'hng_wallet_id' => 'hng_wallet_id',
            'sandbox' => 'sandbox',
            'environment' => 'environment'
        ];

        foreach ($map as $key => $prop) {
            // 1) procurar em opções específicas padrão: hng_{gateway}_{key}
            $opt_name = 'hng_' . $gateway_id . '_' . $key;
            $value = get_option($opt_name, null);

            // 2) alguns gateways possuem opções abreviadas (ex: PagSeguro usa hng_ps_...)
            if ($value === null && $gateway_id === 'pagseguro') {
                if ($key === 'token' || $key === 'api_key' || $key === 'secret_key') {
                    $value = get_option('hng_ps_token', null);
                }
                if ($key === 'sandbox') {
                    $s = get_option('hng_ps_sandbox', null);
                    if ($s !== null) $value = ($s === 'yes' ? 1 : 0);
                }
                if ($key === 'api_url') {
                    // leave null, class has default
                }
            }

            // 3) fallback para opções genéricas JSON
            if ($value === null && is_array($data) && array_key_exists($key, $data)) {
                $value = $data[$key];
            }

            if ($value === null) {
                continue;
            }

            // Se for campo sensível, tentar descriptografar (se armazenado criptografado)
            if (is_string($value) && trim($value) !== '') {
                $dec = HNG_Crypto::instance()->decrypt($value);
                if ($dec !== false) {
                    $value = $dec;
                }
            }

            // Aplicar valor ao objeto
            if ($prop === 'sandbox') {
                $this->sandbox = (bool)$value;
            } else {
                $this->{$prop} = $value;
            }
        }
    }

    /**
     * Processar pagamento
     * 
     * @param int $order_id
     * @param array $payment_data
     * @return array|WP_Error
     */
    public function process_payment($order_id, $payment_data) {
        $method = $payment_data['method'] ?? '';
        
        switch ($method) {
            case 'pix':
                return $this->create_pix_payment($order_id, $payment_data);
            
            case 'boleto':
                return $this->create_boleto_payment($order_id, $payment_data);
            
            case 'credit_card':
                return $this->create_credit_card_payment($order_id, $payment_data);
            
            default:
                return new WP_Error('invalid_method', 'Método de pagamento inválido');
        }
    }
    
    /**
     * Criar pagamento PIX
     */
    public function create_pix_payment($order_id, $payment_data) {
        return new WP_Error('not_implemented', 'create_pix_payment not implemented for this gateway');
    }

    /**
     * Criar pagamento Boleto
     */
    public function create_boleto_payment($order_id, $payment_data) {
        return new WP_Error('not_implemented', 'create_boleto_payment not implemented for this gateway');
    }

    /**
     * Criar pagamento Cartão
     */
    public function create_credit_card_payment($order_id, $payment_data) {
        return new WP_Error('not_implemented', 'create_credit_card_payment not implemented for this gateway');
    }
    
    /**
     * Obter campos do formulário de pagamento
     */
    public function get_payment_fields($method) {
        return [];
    }
    
    /**
     * Validar dados do pagamento
     */
    public function validate_payment_data($payment_data) {
        return true;
    }
    
    /**
     * Make API request with rate limiting and retry logic
     * 
     * @param string $url API endpoint URL
     * @param array $args wp_remote_request arguments
     * @param bool $retry Enable retry logic for failed requests
     * @return array|WP_Error Response or error
     */
    protected function api_request($url, $args = [], $retry = true) {
        // Check rate limit
        if (!$this->check_rate_limit()) {
            return new WP_Error(
                'rate_limit_exceeded',
                __('Taxa de requisições excedida. Tente novamente em alguns segundos.', 'hng-commerce')
            );
        }
        
        $attempts = $retry ? $this->max_retries : 1;
        $last_error = null;
        
        for ($i = 0; $i < $attempts; $i++) {
            // Record request for rate limiting
            $this->record_api_request();
            
            // Make the request
            $response = wp_remote_request($url, $args);
            
            // Check for errors
            if (is_wp_error($response)) {
                $last_error = $response;
                
                // Retry only on timeout or connection errors
                $error_code = $response->get_error_code();
                $retryable_errors = ['http_request_failed', 'http_request_timeout'];
                
                if (!in_array($error_code, $retryable_errors) || !$retry) {
                    return $response;
                }
                
                // Wait before retry (exponential backoff)
                if ($i < $attempts - 1) {
                    sleep($this->retry_delay * pow(2, $i));
                    continue;
                }
            } else {
                // Success - check HTTP status
                $status = wp_remote_retrieve_response_code($response);
                
                // Retry on 5xx errors (server errors)
                if ($status >= 500 && $status < 600 && $retry && $i < $attempts - 1) {
                    sleep($this->retry_delay * pow(2, $i));
                    continue;
                }
                
                return $response;
            }
        }
        
        // All retries failed
        return $last_error ?: new WP_Error('api_request_failed', __('Falha na comunicação com gateway de pagamento', 'hng-commerce'));
    }
    
    /**
     * Check rate limit for API requests
     * 
     * @return bool True if within limits
     */
    private function check_rate_limit() {
        $key = 'hng_gateway_rate_' . $this->id;
        $requests = get_transient($key);
        
        if (false === $requests) {
            return true; // No rate limit data yet
        }
        
        return (int) $requests < $this->rate_limit_max;
    }
    
    /**
     * Record API request for rate limiting
     * 
     * @return void
     */
    private function record_api_request() {
        $key = 'hng_gateway_rate_' . $this->id;
        $requests = (int) get_transient($key);
        
        set_transient($key, $requests + 1, $this->rate_limit_window);
    }
}

// Backwards compatibility: some gateways extend HNG_Gateway_Base
if (!class_exists('HNG_Gateway_Base')) {
    abstract class HNG_Gateway_Base extends HNG_Payment_Gateway {}
}

// Generic handler for one-off payment generation: gateways may implement their own
add_action('hng_generate_oneoff_payment', function($order_id) {
    if (empty($order_id)) return;

    // Try to determine gateway from order meta
    $gateway = get_post_meta($order_id, '_gateway', true);
    if (empty($gateway)) {
        // fallback to configured default gateway option
        $gateway = get_option('hng_default_gateway', '');
    }

    if (empty($gateway)) return;

    $class = 'HNG_Gateway_' . ucfirst($gateway);
    if (!class_exists($class)) {
        // try to load file convention: gateways/{gateway}/class-gateway-{gateway}.php
        $path = HNG_COMMERCE_PATH . 'gateways/' . $gateway . '/class-gateway-' . $gateway . '.php';
        if (file_exists($path)) require_once $path;
    }

    if (!class_exists($class)) return;

    try {
        $gw = new $class();
        if (!$gw->is_configured()) return;

        $total = floatval(get_post_meta($order_id, '_total', true));
        $method = get_post_meta($order_id, '_payment_method', true) ?: 'pix';

        $payment_data = [
            'amount' => $total,
            'method' => $method,
            'order_id' => $order_id,
        ];

        // Allow gateway to process and return URL/data
        $result = $gw->process_payment($order_id, $payment_data);

        if (is_wp_error($result)) {
            if (function_exists('hng_files_log_append')) {
                hng_files_log_append(HNG_COMMERCE_PATH . 'logs/gateways.log', sprintf('[HNG Oneoff] Gateway %s failed to generate payment for order %d: %s', $gateway, $order_id, $result->get_error_message()) . PHP_EOL);
            }
            return;
        }

        // If gateway returned array with url or payment_url, save it
        $payment_url = '';
        if (is_array($result)) {
            $payment_url = $result['payment_url'] ?? $result['url'] ?? $result['checkout_url'] ?? '';
            update_post_meta($order_id, '_payment_data', $result);
        }

        if (!empty($payment_url)) {
            update_post_meta($order_id, '_payment_url', $payment_url);
        }

    } catch (Exception $e) {
        if (function_exists('hng_files_log_append')) { hng_files_log_append(HNG_COMMERCE_PATH . 'logs/gateways.log', '[HNG Oneoff] Exception: ' . $e->getMessage() . PHP_EOL); }
    }
});
