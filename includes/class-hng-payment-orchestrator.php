<?php
/**
 * HNG Payment Orchestrator Client
 * 
 * Client for calling _api-server payment orchestration endpoints.
 * Handles payment creation via centralized multi-gateway system.
 * 
 * @package HNG_Commerce
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
if (!class_exists('HNG_Signature')) {
    require_once HNG_COMMERCE_PATH . 'includes/security/class-hng-signature.php';
}
if (!class_exists('HNG_Fee_Calculator')) {
    require_once HNG_COMMERCE_PATH . 'includes/class-hng-fee-calculator.php';
}

class HNG_Payment_Orchestrator {
    
    /**
     * @var string Base URL for _api-server
     */
    private static $api_base_url;
    
    /**
     * @var int Timeout for API requests in seconds
     */
    private static $timeout = 30;
    
    /**
     * Initialize orchestrator client
     */
    public static function init() {
        // Auto-detect _api-server URL based on WordPress site URL
        // Updated to use api.hngdesenvolvimentos.com.br (Session 9)
        self::$api_base_url = 'https://api.hngdesenvolvimentos.com.br/';
        
        // Allow override via constant or option
        if (defined('HNG_API_SERVER_URL')) {
            self::$api_base_url = trailingslashit(HNG_API_SERVER_URL);
        } elseif (get_option('hng_api_server_url')) {
            self::$api_base_url = trailingslashit(get_option('hng_api_server_url'));
        }
    }
    
    /**
     * Validate transaction and get authentication token
     * 
     * @param float $amount Transaction amount
     * @param string $merchant_id Merchant identifier
     * @param string $gateway Gateway identifier (default: asaas)
     * @param string $payment_method Payment method (default: pix)
     * @return array|WP_Error Array with auth_token or WP_Error on failure
     */
    public static function validate_transaction($amount, $merchant_id = '', $gateway = 'asaas', $payment_method = 'pix') {
        if (empty(self::$api_base_url)) {
            self::init();
        }
        
        $url = self::$api_base_url . 'endpoints/transactions-validate.php';
        
        $body = [
            'amount' => floatval($amount),
            'merchant_id' => !empty($merchant_id) ? $merchant_id : get_current_user_id(),
            'expected_fee' => HNG_Fee_Calculator::instance()->calculate_plugin_fee(floatval($amount), 'physical'),
            'gateway' => strtolower($gateway),
            'payment_method' => strtolower($payment_method),
            'timestamp' => time(),
        ];
        
        $response = wp_remote_post($url, [
            'timeout' => self::$timeout,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Hng-Api-Key' => get_option('hng_api_key', ''),
            ],
            'body' => json_encode($body),
        ]);
        
        if (is_wp_error($response)) {
            self::log_error('validate_transaction', $response->get_error_message());
            return $response;
        }
        
        $status = wp_remote_retrieve_response_code($response);
        $body_response = wp_remote_retrieve_body($response);
        $data = json_decode($body_response, true);
        
        if ($status !== 200) {
            $error_msg = $data['error'] ?? 'Transaction validation failed';
            self::log_error('validate_transaction', sprintf('Status %d: %s', $status, $error_msg));
            return new WP_Error('validation_failed', $error_msg);
        }
        
        if (empty($data['auth_token'])) {
            self::log_error('validate_transaction', 'No auth_token in response');
            return new WP_Error('invalid_response', 'No authentication token received');
        }

        // Verificar assinatura do payload de validação se disponível
        if (isset($data['signed']) && class_exists('HNG_Signature')) {
            $expected = [
                'amount' => floatval($amount),
            ];
            $merchant_opt = get_option('hng_merchant_id');
            if (!empty($merchant_opt)) {
                $expected['merchant_id'] = $merchant_opt;
            }
            $verify = HNG_Signature::verify_signed_block($data['signed'], $expected);
            if (is_wp_error($verify)) {
                self::log_error('validate_transaction', 'Assinatura inválida: ' . $verify->get_error_message(), ['response' => $data]);
                return new WP_Error('invalid_signature', 'Assinatura inválida na resposta de validação');
            }
        }
        
        return $data;
    }
    
    /**
     * Create payment via _api-server orchestration
     * 
     * @param string $auth_token Authentication token from validate_transaction
     * @param string $gateway Gateway identifier (asaas, pagarme, mercadopago, pagseguro, etc)
     * @param array $payment_data Payment data including amount, method, customer, etc
     * @return array|WP_Error Payment response or WP_Error on failure
     */
    public static function create_payment($auth_token, $gateway, $payment_data) {
        if (empty(self::$api_base_url)) {
            self::init();
        }
        
        $url = self::$api_base_url . 'endpoints/payments-create.php';
        
        // Build request body
        $body = array_merge($payment_data, [
            'auth_token' => $auth_token,
            'gateway' => strtolower($gateway),
        ]);
        
        // Add merchant_id if not present
        if (empty($body['merchant_id'])) {
            $body['merchant_id'] = get_current_user_id();
        }
        
        $response = wp_remote_post($url, [
            'timeout' => self::$timeout,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Hng-Api-Key' => get_option('hng_api_key', ''),
            ],
            'body' => json_encode($body),
        ]);
        
        if (is_wp_error($response)) {
            self::log_error('create_payment', $response->get_error_message(), $body);
            return $response;
        }
        
        $status = wp_remote_retrieve_response_code($response);
        $body_response = wp_remote_retrieve_body($response);
        $data = json_decode($body_response, true);
        
        if ($status !== 200 && $status !== 201) {
            $error_msg = $data['error'] ?? 'Payment creation failed';
            self::log_error('create_payment', sprintf('Status %d: %s', $status, $error_msg), $body);
            return new WP_Error('payment_failed', $error_msg);
        }

        // Verificar assinatura do payload de criação de pagamento se disponível
        if (isset($data['signed']) && class_exists('HNG_Signature')) {
            $expected = [
                'gateway' => strtolower($gateway),
                'amount' => floatval($body['amount'] ?? 0),
            ];
            if (!empty($data['payment_id'])) { $expected['payment_id'] = $data['payment_id']; }
            $verify = HNG_Signature::verify_signed_block($data['signed'], $expected);
            if (is_wp_error($verify)) {
                self::log_error('create_payment', 'Assinatura inválida: ' . $verify->get_error_message(), ['response' => $data]);
                return new WP_Error('invalid_signature', 'Assinatura inválida na resposta de pagamento');
            }
        }
        
        return $data;
    }
    
    /**
     * Create payment with automatic validation (one-step call)
     * 
     * @param string $gateway Gateway identifier
     * @param array $payment_data Payment data
     * @return array|WP_Error Payment response or WP_Error
     */
    public static function create_payment_with_validation($gateway, $payment_data) {
        // Step 1: Validate transaction
        $amount = $payment_data['amount'] ?? 0;
        $merchant_id = $payment_data['merchant_id'] ?? '';
        $method = $payment_data['method'] ?? 'pix';
        
        $validation = self::validate_transaction($amount, $merchant_id, $gateway, $method);
        
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        $auth_token = $validation['auth_token'];
        
        // Step 2: Create payment
        return self::create_payment($auth_token, $gateway, $payment_data);
    }
    
    /**
     * Get payment status
     * 
     * @param string $payment_id Payment ID from _api-server
     * @return array|WP_Error Payment data or WP_Error
     */
    public static function get_payment($payment_id) {
        if (empty(self::$api_base_url)) {
            self::init();
        }
        
        $url = self::$api_base_url . 'endpoints/payments-get.php?payment_id=' . urlencode($payment_id);
        
        $response = wp_remote_get($url, [
            'timeout' => self::$timeout,
        ]);
        
        if (is_wp_error($response)) {
            self::log_error('get_payment', $response->get_error_message(), ['payment_id' => $payment_id]);
            return $response;
        }
        
        $status = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($status !== 200) {
            $error_msg = $data['error'] ?? 'Failed to get payment';
            return new WP_Error('get_payment_failed', $error_msg);
        }
        
        return $data;
    }
    
    /**
     * Create checkout intent (JWT-based, one-time use)
     * 
     * @param float $amount Transaction amount
     * @param string $gateway Gateway identifier
     * @param string $payment_method Payment method
     * @param array $extra_data Additional data (merchant_id, order_id, etc)
     * @return array|WP_Error Intent response with JWT token or WP_Error
     */
    public static function create_checkout_intent($amount, $gateway = 'asaas', $payment_method = 'pix', $extra_data = []) {
        if (empty(self::$api_base_url)) {
            self::init();
        }
        
        $url = self::$api_base_url . 'endpoints/checkout-intents-create.php';
        
        $body = array_merge($extra_data, [
            'amount' => floatval($amount),
            'gateway' => strtolower($gateway),
            'payment_method' => strtolower($payment_method),
            'expected_fee' => HNG_Fee_Calculator::instance()->calculate_plugin_fee(floatval($amount), 'physical'),
        ]);
        
        if (empty($body['merchant_id'])) {
            $body['merchant_id'] = get_option('hng_merchant_id', get_current_user_id());
        }
        
        $response = wp_remote_post($url, [
            'timeout' => self::$timeout,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Hng-Api-Key' => get_option('hng_api_key', ''),
            ],
            'body' => json_encode($body),
        ]);
        
        if (is_wp_error($response)) {
            self::log_error('create_checkout_intent', $response->get_error_message());
            return $response;
        }
        
        $status = wp_remote_retrieve_response_code($response);
        $body_response = wp_remote_retrieve_body($response);
        $data = json_decode($body_response, true);
        
        if ($status !== 201) {
            $error_msg = $data['message'] ?? 'Failed to create checkout intent';
            self::log_error('create_checkout_intent', sprintf('Status %d: %s', $status, $error_msg));
            return new WP_Error('intent_failed', $error_msg);
        }
        
        return $data;
    }
    
    /**
     * Verify and consume checkout intent JWT
     * 
     * @param string $intent_token JWT token from create_checkout_intent
     * @return array|WP_Error Decoded intent payload or WP_Error
     */
    public static function verify_checkout_intent($intent_token) {
        if (empty($intent_token) || !class_exists('HNG_Signature')) {
            return new WP_Error('invalid_intent', 'Intent token ausente ou HNG_Signature não disponível');
        }
        
        $payload = HNG_Signature::verify_jwt_eddsa($intent_token);
        
        if (is_wp_error($payload)) {
            self::log_error('verify_checkout_intent', 'JWT inválido: ' . $payload->get_error_message());
            return $payload;
        }
        
        // Validações adicionais de contexto
        $expected_fields = ['aud', 'auth_token', 'jti', 'exp'];
        foreach ($expected_fields as $field) {
            if (empty($payload[$field])) {
                return new WP_Error('intent_incomplete', 'Campo obrigatório ausente: ' . $field);
            }
        }
        
        return $payload;
    }
    
    /**
     * Process gateway payment via centralized API
     * This is the main integration point that replaces direct gateway calls
     * 
     * @param string $gateway Gateway identifier
     * @param int $order_id WordPress order post ID
     * @param array $payment_data Payment data
     * @return array|WP_Error
     */
    public static function process_gateway_payment($gateway, $order_id, $payment_data) {
        // Get order details
        $total = floatval(get_post_meta($order_id, '_total', true));
        $customer_email = get_post_meta($order_id, '_customer_email', true);
        $customer_name = get_post_meta($order_id, '_customer_name', true);
        $customer_cpf = get_post_meta($order_id, '_customer_cpf', true);
        
        // Build standardized payment request
        $request_data = [
            'amount' => $payment_data['amount'] ?? $total,
            'method' => $payment_data['method'] ?? 'pix',
            'description' => sprintf('Pedido #%d', $order_id),
            'external_id' => (string)$order_id,
            'customer' => [
                'name' => $customer_name,
                'email' => $customer_email,
                'cpf' => $customer_cpf,
            ],
        ];
        
        // Add optional fields if present
        if (!empty($payment_data['installments'])) {
            $request_data['installments'] = intval($payment_data['installments']);
        }
        
        if (!empty($payment_data['card_token'])) {
            $request_data['card_token'] = $payment_data['card_token'];
        }
        
        if (!empty($payment_data['card_data'])) {
            $request_data['card_data'] = $payment_data['card_data'];
        }
        
        // Call centralized API
        $result = self::create_payment_with_validation($gateway, $request_data);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Store API payment ID for future reference
        if (!empty($result['payment_id'])) {
            update_post_meta($order_id, '_api_payment_id', $result['payment_id']);
        }
        
        // Store gateway transaction ID
        if (!empty($result['gateway_id'])) {
            update_post_meta($order_id, '_gateway_payment_id', $result['gateway_id']);
        }
        
        // Store raw API response
        update_post_meta($order_id, '_api_payment_data', $result);
        
        // Extract standard fields for compatibility
        $response = [
            'success' => true,
            'payment_id' => $result['payment_id'] ?? '',
            'gateway_id' => $result['gateway_id'] ?? '',
            'status' => $result['status'] ?? 'PENDING',
        ];
        
        // Extract payment URL/QR code based on method
        if (!empty($result['pix_qrcode'])) {
            $response['pix_qrcode'] = $result['pix_qrcode'];
            $response['pix_qrcode_text'] = $result['pix_qrcode_text'] ?? '';
        }
        
        if (!empty($result['payment_url'])) {
            $response['payment_url'] = $result['payment_url'];
            $response['url'] = $result['payment_url']; // Compatibility alias
        }
        
        if (!empty($result['checkout_url'])) {
            $response['checkout_url'] = $result['checkout_url'];
        }
        
        if (!empty($result['boleto_url'])) {
            $response['boleto_url'] = $result['boleto_url'];
            $response['boleto_barcode'] = $result['boleto_barcode'] ?? '';
        }
        
        return $response;
    }
    
    /**
     * Check if gateway should use centralized API
     * 
     * @param string $gateway Gateway identifier
     * @return bool
     */
    public static function is_centralized_gateway($gateway) {
        $centralized_gateways = [
            'asaas',
            'pagarme',
            'mercadopago',
            'pagseguro',
            'picpay',
            'nubank',
            'stripe',
            'paypal',
            'cielo',
            'rede',
            'getnet',
            'stone',
        ];
        
        // Allow filtering
        $centralized_gateways = apply_filters('hng_centralized_gateways', $centralized_gateways);
        
        return in_array(strtolower($gateway), $centralized_gateways);
    }
    
    /**
     * Log error to file
     * 
     * @param string $method Method name
     * @param string $message Error message
     * @param array $context Additional context
     */
    private static function log_error($method, $message, $context = []) {
        $log_path = WP_CONTENT_DIR . '/plugins/hng-commerce/logs/orchestrator.log';
        
        // Create logs directory if doesn't exist
        $log_dir = dirname($log_path);
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }
        
        $log_entry = sprintf(
            "[%s] %s: %s\n",
            gmdate('Y-m-d H:i:s'),
            $method,
            $message
        );
        
        if (!empty($context)) {
            $log_entry .= "Context: " . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        }
        
        $log_entry .= "---\n";
        
        @error_log($log_entry, 3, $log_path);
    }
    
    /**
     * Get API base URL
     * 
     * @return string
     */
    public static function get_api_base_url() {
        if (empty(self::$api_base_url)) {
            self::init();
        }
        return self::$api_base_url;
    }
}

// Initialize on load
HNG_Payment_Orchestrator::init();
