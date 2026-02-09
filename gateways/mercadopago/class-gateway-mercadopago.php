<?php
/**
 * Mercado Pago Gateway
 * 
 * Full integration with Mercado Pago (PIX, Card, Boleto)
 * Direct payment to merchant account
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Gateway_MercadoPago extends HNG_Gateway_Base {
    
    /**
     * Gateway ID
     */
    public $id = 'mercadopago';
    
    /**
     * Gateway title
     */
    public $title = 'Mercado Pago';
    
    /**
     * API URLs
     */
    public $api_url = 'https://api.mercadopago.com';
    private $sandbox_url = 'https://api.mercadopago.com';
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
        
        $this->supports = ['pix', 'credit_card', 'boleto', 'customers', 'subscriptions', 'split_payment', 'webhooks'];
        
        // Hooks
        add_action('wp_ajax_hng_mp_create_payment', [$this, 'ajax_create_payment']);
        add_action('wp_ajax_nopriv_hng_mp_create_payment', [$this, 'ajax_create_payment']);
        add_action('wp_ajax_hng_mp_check_payment', [$this, 'ajax_check_payment']);
        add_action('wp_ajax_nopriv_hng_mp_check_payment', [$this, 'ajax_check_payment']);
    }
    private function is_advanced_enabled() {
        return get_option('hng_mercadopago_advanced_integration', 'no') === 'yes';
    }
    
    /**
     * Get OAuth credentials from HNG API
     * Returns seller's access_token if connected via OAuth
     */
    private function get_oauth_credentials() {
        $merchant_id = get_option('hng_merchant_id', '');
        $api_key = get_option('hng_merchant_api_key', '');
        
        if (empty($merchant_id) || empty($api_key)) {
            return null;
        }
        
        // Cache OAuth credentials for 5 minutes
        $cache_key = 'hng_mp_oauth_creds_' . $merchant_id;
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // Fetch from API
        $response = wp_remote_post('https://api.hngdesenvolvimentos.com.br/oauth/mercadopago/credentials', [
            'timeout' => 10,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode([
                'merchant_id' => $merchant_id,
                'api_key' => $api_key
            ])
        ]);
        
        if (is_wp_error($response)) {
            $this->log('OAuth credentials fetch error: ' . $response->get_error_message());
            return null;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($body['success']) || empty($body['credentials'])) {
            $this->log('OAuth not connected or invalid: ' . wp_json_encode($body));
            return null;
        }
        
        $credentials = [
            'access_token' => $body['credentials']['access_token'],
            'public_key' => $body['credentials']['public_key'],
            'user_id' => $body['credentials']['user_id'],
            'marketplace_token' => $body['marketplace']['access_token'] ?? '',
            'collector_id' => $body['marketplace']['collector_id'] ?? ''
        ];
        
        // Cache for 5 minutes
        set_transient($cache_key, $credentials, 300);
        
        return $credentials;
    }
    
    /**
     * Check if OAuth is connected
     */
    public function is_oauth_connected() {
        $oauth = $this->get_oauth_credentials();
        return !empty($oauth) && !empty($oauth['access_token']);
    }
    
    /**
     * Get HNG Commerce fee percentage for split payment
     */
    private function get_hng_fee_percentage() {
        // Get tier-based fee from merchant settings
        $merchant_tier = get_option('hng_merchant_tier', 1);
        $tier_fees = [
            1 => 3.0,  // Tier 1: 3%
            2 => 2.5,  // Tier 2: 2.5%
            3 => 2.0,  // Tier 3: 2%
            4 => 1.5,  // Tier 4: 1.5%
            5 => 1.0,  // Tier 5: 1%
        ];
        return $tier_fees[$merchant_tier] ?? 3.0;
    }
    
    /**
     * Get settings
     */
    public function get_settings() {
        return [
            'enabled' => get_option('hng_gateway_mercadopago_enabled', 'no'),
            'sandbox' => get_option('hng_mercadopago_sandbox', 'no'),
            'public_key' => get_option('hng_mercadopago_public_key', ''),
            'access_token' => get_option('hng_mercadopago_access_token', ''),
            'webhook_secret' => get_option('hng_mercadopago_webhook_secret', ''),
        ];
    }
    
    /**
     * Process payment
     */
    public function process_payment($order_id, $payment_data) {
        $order = new HNG_Order($order_id);
        $method = $payment_data['payment_method'] ?? 'pix';
        
        try {
            switch ($method) {
                case 'pix':
                    return $this->process_pix($order, $payment_data);
                case 'credit_card':
                    return $this->process_credit_card($order, $payment_data);
                case 'boleto':
                    return $this->process_boleto($order, $payment_data);
                default:
                    throw new Exception(__('Método de pagamento inválido.', 'hng-commerce'));
            }
        } catch (Exception $e) {
            $this->log('Payment Processing Error: ' . $e->getMessage());
            return new WP_Error('payment_error', $e->getMessage());
        }
    }

    /**
     * Check configuration
     */
    public function is_configured() {
        $settings = $this->get_settings();
        return !empty($settings['access_token']);
    }

    /**
     * Create customer
     */
    public function create_customer($data) {
        if (!$this->is_advanced_enabled()) {
            return new WP_Error('hng_mp_adv_off', 'Integraá§á¡o avaná§ada desativada para Mercado Pago');
        }
        $payload = [
            'email' => $data['email'] ?? '',
            'first_name' => $data['first_name'] ?? '',
            'last_name' => $data['last_name'] ?? '',
            'identification' => [
                'type' => 'CPF',
                'number' => preg_replace('/\D/', '', $data['document'] ?? ''),
            ],
        ];
        return $this->request('POST', '/v1/customers', $payload);
    }

    /**
     * Create preapproval (subscription)
     */
    public function create_subscription($customer_id, $plan_data) {
        if (!$this->is_advanced_enabled()) {
            return new WP_Error('hng_mp_adv_off', 'Integraá§á¡o avaná§ada desativada para Mercado Pago');
        }
        $payload = [
            'payer_email' => $plan_data['email'] ?? '',
            'back_url' => $plan_data['back_url'] ?? home_url('/assinatura/retorno'),
            'reason' => $plan_data['reason'] ?? 'Assinatura HNG',
            'auto_recurring' => [
                'frequency' => $plan_data['frequency'] ?? 1,
                'frequency_type' => $plan_data['frequency_type'] ?? 'months',
                'transaction_amount' => $plan_data['amount'] ?? 0,
                'currency_id' => 'BRL'
            ],
        ];
        return $this->request('POST', '/preapproval', $payload);
    }

    /**
     * Create split payment (marketplace)
     */
    public function create_split_payment($order, $payment_data, $split) {
        if (!$this->is_advanced_enabled()) {
            return new WP_Error('hng_mp_adv_off', 'Integraá§á¡o avaná§ada desativada para Mercado Pago');
        }
        $payload = [
            'transaction_amount' => $order->get_total(),
            /* translators: %1$s: order ID */
            'description' => sprintf(esc_html__('Pedido #%1$s', 'hng-commerce'), $order->get_id()),
            'payment_method_id' => $payment_data['payment_method_id'] ?? 'credit_card',
            'payer' => [
                'email' => $order->get_customer_email(),
            ],
            'additional_info' => [
                'items' => $this->format_items($order),
            ],
            'payment_split' => $split,
            'external_reference' => $order->get_id(),
            'notification_url' => home_url('/hng-webhook/mercadopago'),
        ];
        return $this->request('POST', '/v1/payments', $payload);
    }
    
    /**
     * Process PIX payment
     */
    private function process_pix($order, $payment_data) {
        $settings = $this->get_settings();
        
        $payload = [
            'transaction_amount' => $order->get_total(),
            /* translators: %1$s: order ID */
            'description' => sprintf(esc_html__('Pedido #%1$s', 'hng-commerce'), $order->get_id()),
            'payment_method_id' => 'pix',
            'payer' => [
                'email' => $order->get_customer_email(),
                'first_name' => $payment_data['customer_name'] ?? '',
            ],
            'notification_url' => home_url('/hng-webhook/mercadopago'),
            'external_reference' => $order->get_id(),
        ];
        
        $response = $this->request('POST', '/v1/payments', $payload);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Save payment ID
        update_post_meta($order->get_id(), '_mp_payment_id', $response['id']);
        update_post_meta($order->get_id(), '_mp_status', $response['status']);
        
        // Get PIX info
        $pix_data = [
            'qr_code' => $response['point_of_interaction']['transaction_data']['qr_code'] ?? '',
            'qr_code_base64' => $response['point_of_interaction']['transaction_data']['qr_code_base64'] ?? '',
            'ticket_url' => $response['point_of_interaction']['transaction_data']['ticket_url'] ?? '',
            'payment_id' => $response['id'],
            'expiration_date' => $response['date_of_expiration'] ?? '',
        ];
        
        update_post_meta($order->get_id(), '_mp_pix_data', $pix_data);
        
        // Calcular e registrar taxas do plugin HNG
        $this->register_hng_fees($order, 'pix', $response['id']);
        
        return [
            'success' => true,
            'payment_method' => 'pix',
            'pix_data' => $pix_data,
            'redirect_url' => home_url('/pagamento/pix?order_id=' . $order->get_id()),
        ];
    }
    
    /**
     * Process credit card payment
     */
    private function process_credit_card($order, $payment_data) {
        $settings = $this->get_settings();
        
        // Card data comes from frontend (encrypted token)
        $token = $payment_data['card_token'] ?? '';
        $installments = absint($payment_data['installments'] ?? 1);
        
        if (!$token) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is translatable string, escaping handled by exception handler
            throw new Exception(__('Token do cartão não fornecido.', 'hng-commerce'));
        }
        
        $payload = [
            'transaction_amount' => $order->get_total(),
            'token' => $token,
            /* translators: %1$s: order ID */
            'description' => sprintf(esc_html__('Pedido #%1$s', 'hng-commerce'), $order->get_id()),
            'installments' => $installments,
            'payment_method_id' => $payment_data['payment_method_id'] ?? 'visa',
            'payer' => [
                'email' => $order->get_customer_email(),
                'identification' => [
                    'type' => 'CPF',
                    'number' => preg_replace('/\D/', '', $payment_data['document'] ?? ''),
                ]
            ],
            'notification_url' => home_url('/hng-webhook/mercadopago'),
            'external_reference' => $order->get_id(),
        ];
        
        $response = $this->request('POST', '/v1/payments', $payload);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        update_post_meta($order->get_id(), '_mp_payment_id', $response['id']);
        update_post_meta($order->get_id(), '_mp_status', $response['status']);
        
        // Calcular e registrar taxas do plugin HNG
        $this->register_hng_fees($order, 'credit_card', $response['id']);
        
        // Check if approved
        if ($response['status'] === 'approved') {
            $order->update_status('processing');
            
            return [
                'success' => true,
                'payment_method' => 'credit_card',
                'status' => 'approved',
                'message' => __('Pagamento aprovado!', 'hng-commerce'),
                'redirect_url' => $order->get_order_received_url(),
            ];
        } else {
            return [
                'success' => false,
                'payment_method' => 'credit_card',
                'status' => $response['status'],
                'message' => $this->get_status_message($response['status']),
            ];
        }
    }
    
    /**
     * Process Boleto payment
     */
    private function process_boleto($order, $payment_data) {
        $settings = $this->get_settings();
        
        $payload = [
            'transaction_amount' => $order->get_total(),
            /* translators: %1$s: order ID */
            'description' => sprintf(esc_html__('Pedido #%1$s', 'hng-commerce'), $order->get_id()),
            'payment_method_id' => 'bolbradesco', // or other boleto methods
            'payer' => [
                'email' => $order->get_customer_email(),
                'first_name' => $payment_data['customer_name'] ?? '',
                'last_name' => '',
                'identification' => [
                    'type' => 'CPF',
                    'number' => preg_replace('/\D/', '', $payment_data['document'] ?? ''),
                ],
                'address' => [
                    'zip_code' => preg_replace('/\D/', '', $payment_data['zip_code'] ?? ''),
                    'street_name' => $payment_data['street'] ?? '',
                    'street_number' => $payment_data['number'] ?? '',
                    'neighborhood' => $payment_data['neighborhood'] ?? '',
                    'city' => $payment_data['city'] ?? '',
                    'federal_unit' => $payment_data['state'] ?? '',
                ]
            ],
            'notification_url' => home_url('/hng-webhook/mercadopago'),
            'external_reference' => $order->get_id(),
        ];
        
        $response = $this->request('POST', '/v1/payments', $payload);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        update_post_meta($order->get_id(), '_mp_payment_id', $response['id']);
        update_post_meta($order->get_id(), '_mp_status', $response['status']);
        
        $boleto_data = [
            'barcode' => $response['barcode']['content'] ?? '',
            'ticket_url' => $response['transaction_details']['external_resource_url'] ?? '',
            'payment_id' => $response['id'],
            'due_date' => $response['date_of_expiration'] ?? '',
        ];
        
        update_post_meta($order->get_id(), '_mp_boleto_data', $boleto_data);
        
        // Calcular e registrar taxas do plugin HNG
        $this->register_hng_fees($order, 'boleto', $response['id']);
        
        return [
            'success' => true,
            'payment_method' => 'boleto',
            'boleto_data' => $boleto_data,
            'redirect_url' => home_url('/pagamento/boleto?order_id=' . $order->get_id()),
        ];
    }
    
    /**
     * Make API request
     * Uses OAuth credentials if available for split payment
     */
    private function request($method, $endpoint, $data = [], $use_oauth = true) {
        $settings = $this->get_settings();
        $oauth_creds = null;
        
        // Try OAuth credentials first for payments (split payment)
        if ($use_oauth && strpos($endpoint, '/v1/payments') !== false) {
            $oauth_creds = $this->get_oauth_credentials();
        }
        
        // Determine which access token to use
        if ($oauth_creds && !empty($oauth_creds['access_token'])) {
            $access_token = $oauth_creds['access_token'];
            $this->log('Using OAuth seller token for split payment');
            
            // Add application_fee for split payment (only on payment creation)
            if ($method === 'POST' && strpos($endpoint, '/v1/payments') !== false && !empty($data['transaction_amount'])) {
                $fee_percent = $this->get_hng_fee_percentage();
                $fee_amount = round($data['transaction_amount'] * ($fee_percent / 100), 2);
                
                if ($fee_amount > 0) {
                    $data['application_fee'] = $fee_amount;
                    $this->log('Split payment enabled: application_fee = ' . $fee_amount . ' (' . $fee_percent . '%)');
                }
            }
        } else {
            // Fallback to regular access token
            $access_token = $settings['access_token'];
        }
        
        if (!$access_token) {
            return new WP_Error('no_token', __('Access token não configurado.', 'hng-commerce'));
        }
        
        $url = $this->api_url . $endpoint;
        
        $args = [
            'method' => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
                'X-Idempotency-Key' => wp_generate_uuid4(),
            ],
            'timeout' => 30,
        ];
        
        if ($method !== 'GET' && !empty($data)) {
            $args['body'] = wp_json_encode($data);
        }
        
        $this->log('Request: ' . $method . ' ' . $url, $data);
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            $this->log('Error: ' . $response->get_error_message());
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $code = wp_remote_retrieve_response_code($response);
        $response_data = json_decode($body, true);
        
        $this->log('Response: ' . $code, $response_data);
        
        if ($code >= 400) {
            $error_message = $response_data['message'] ?? __('Erro na API do Mercado Pago', 'hng-commerce');
            return new WP_Error('api_error', $error_message, $response_data);
        }
        
        return $response_data;
    }
    
    /**
     * Handle webhook
     */
    public function handle_webhook($request) {
        $data = $request->get_json_params();
        
        $this->log('Webhook received', $data);
        
        // Mercado Pago sends notification with payment ID
        $type = $data['type'] ?? '';
        
        if ($type !== 'payment') {
            return;
        }
        
        $payment_id = $data['data']['id'] ?? '';
        
        if (!$payment_id) {
            return;
        }
        
        // Get payment details
        $payment = $this->request('GET', '/v1/payments/' . $payment_id);
        
        if (is_wp_error($payment)) {
            $this->log('Error fetching payment: ' . $payment->get_error_message());
            return;
        }
        
        // Find order
        $order_id = $payment['external_reference'] ?? 0;
        
        if (!$order_id) {
            $this->log('No order ID in payment');
            return;
        }
        
        $order = new HNG_Order($order_id);
        
        // Update order based on payment status
        $status = $payment['status'] ?? '';
        
        switch ($status) {
            case 'approved':
                $order->update_status('processing');
                $order->add_note(__('Pagamento aprovado no Mercado Pago.', 'hng-commerce'));
                break;
            case 'pending':
                $order->update_status('pending');
                break;
            case 'rejected':
            case 'cancelled':
                $order->update_status('failed');
                $order->add_note(__('Pagamento rejeitado/cancelado no Mercado Pago.', 'hng-commerce'));
                break;
            case 'refunded':
                $order->update_status('refunded');
                $order->add_note(__('Pagamento reembolsado no Mercado Pago.', 'hng-commerce'));
                break;
        }
        
        update_post_meta($order_id, '_mp_status', $status);
    }
    
    /**
     * Get status message
     */
    private function get_status_message($status) {
        $messages = [
            'approved' => __('Pagamento aprovado', 'hng-commerce'),
            'pending' => __('Pagamento pendente', 'hng-commerce'),
            'in_process' => __('Pagamento em processamento', 'hng-commerce'),
            'rejected' => __('Pagamento rejeitado', 'hng-commerce'),
            'cancelled' => __('Pagamento cancelado', 'hng-commerce'),
            'refunded' => __('Pagamento reembolsado', 'hng-commerce'),
        ];
        
        return $messages[$status] ?? __('Status desconhecido', 'hng-commerce');
    }
    
    /**
     * AJAX: Create payment
     */
    public function ajax_create_payment() {
        // Nonce validation (specific nonce or legacy generic for backward compatibility)
        $nonce = isset($_REQUEST['nonce']) ? wp_unslash($_REQUEST['nonce']) : '';
        
        // Accept both specific nonce (hng_mp_create_payment) and generic legacy nonce (HNG Commerce)
        $nonce_verified = wp_verify_nonce($nonce, 'hng_mp_create_payment') || 
                         wp_verify_nonce($nonce, 'HNG Commerce');
        
        if (!$nonce_verified) {
            wp_send_json_error(['message' => __('Verificação de segurança falhou.', 'hng-commerce')]);
        }
        
        $post = function_exists('wp_unslash') ? wp_unslash($_POST) : $_POST;
        $order_id = isset($post['order_id']) ? absint($post['order_id']) : 0;
        
        // Sanitize payment data array (if present)
        $payment_data = isset($post['payment_data']) ? wp_unslash($post['payment_data']) : [];
        if (is_array($payment_data)) {
            $payment_data = array_map(function($value) {
                if (is_string($value)) {
                    return sanitize_text_field($value);
                }
                return $value;
            }, $payment_data);
        }
        
        if (!$order_id) {
            wp_send_json_error(['message' => __('Pedido inválido.', 'hng-commerce')]);
        }
        
        $result = $this->process_payment($order_id, $payment_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Check payment status
     */
    public function ajax_check_payment() {
        // Nonce validation (specific nonce or legacy generic for backward compatibility)
        $nonce = isset($_REQUEST['nonce']) ? wp_unslash($_REQUEST['nonce']) : '';
        
        // Accept both specific nonce (hng_mp_check_payment) and generic legacy nonce (HNG Commerce)
        $nonce_verified = wp_verify_nonce($nonce, 'hng_mp_check_payment') || 
                         wp_verify_nonce($nonce, 'HNG Commerce');
        
        if (!$nonce_verified) {
            wp_send_json_error(['message' => __('Verificação de segurança falhou.', 'hng-commerce')]);
        }
        
        $post = function_exists('wp_unslash') ? wp_unslash($_POST) : $_POST;
        $order_id = isset($post['order_id']) ? absint($post['order_id']) : 0;
        
        if (!$order_id) {
            wp_send_json_error(['message' => __('Pedido inválido.', 'hng-commerce')]);
        }
        
        $payment_id = get_post_meta($order_id, '_mp_payment_id', true);
        
        if (!$payment_id) {
            wp_send_json_error(['message' => __('Pagamento não encontrado.', 'hng-commerce')]);
        }
        
        $payment = $this->request('GET', '/v1/payments/' . $payment_id);
        
        if (is_wp_error($payment)) {
            wp_send_json_error(['message' => $payment->get_error_message()]);
        }
        
        wp_send_json_success([
            'status' => $payment['status'],
            'status_detail' => $payment['status_detail'] ?? '',
        ]);
    }
    
    /**
     * Calcular e registrar taxas do plugin HNG
     * 
     * @param HNG_Order $order Objeto do pedido
     * @param string $payment_method Método de pagamento (pix, credit_card, boleto)
     * @param string $external_ref Referência externa do pagamento
     */
    private function register_hng_fees($order, $payment_method, $external_ref = '') {
        if (!class_exists('HNG_Fee_Calculator')) {
            return;
        }
        
        $calc = HNG_Fee_Calculator::instance();
        
        // Determinar tipo de produto (físico por padrão, pode ser melhorado)
        $product_type = 'physical';
        
        // Calcular todas as taxas
        $fee_data = $calc->calculate_all_fees(
            $order->get_total(),
            $product_type,
            $this->id,
            $payment_method
        );
        
        // Salvar dados das taxas no pedido
        update_post_meta($order->get_id(), '_hng_fee_data', $fee_data);
        update_post_meta($order->get_id(), '_hng_plugin_fee', $fee_data['plugin_fee']);
        update_post_meta($order->get_id(), '_hng_gateway_fee', $fee_data['gateway_fee']);
        update_post_meta($order->get_id(), '_hng_net_amount', $fee_data['net_amount']);
        update_post_meta($order->get_id(), '_hng_tier', $fee_data['tier']);
        
        // Registrar no Ledger se disponível
        if (class_exists('HNG_Ledger')) {
            HNG_Ledger::add_entry([
                'type' => 'charge',
                'order_id' => $order->get_id(),
                'external_ref' => $external_ref,
                'gross_amount' => $fee_data['gross_amount'],
                'fee_amount' => $fee_data['plugin_fee'] + $fee_data['gateway_fee'],
                'net_amount' => $fee_data['net_amount'],
                'status' => 'pending',
                'meta' => [
                    'gateway' => $this->id,
                    'method' => $payment_method,
                    'plugin_fee' => $fee_data['plugin_fee'],
                    'gateway_fee' => $fee_data['gateway_fee'],
                    'tier' => $fee_data['tier']
                ]
            ]);
        }
        
        // Registrar transação no Fee Calculator
        $calc->register_transaction($order->get_id(), $fee_data);
        
        $this->log('HNG Fees registered', $fee_data);
    }
}

// Initialize gateway
new HNG_Gateway_MercadoPago();
