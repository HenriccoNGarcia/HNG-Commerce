<?php
/**
 * Getnet Gateway (Santander)
 * 
 * Full integration with Getnet (PIX, Card, Boleto)
 * Split payment support for marketplaces
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Gateway_Getnet extends HNG_Gateway_Base {
    
    public $id = 'getnet';
    public $title = 'Getnet';
    public $api_url_sandbox = 'https://api-sandbox.getnet.com.br';
    public $api_url_production = 'https://api.getnet.com.br';
    
    public $access_token = null;
    
    public function __construct() {
        parent::__construct();
        $this->supports = ['pix', 'credit_card', 'debit_card', 'boleto', 'split_payment', 'webhooks'];
        
        add_action('wp_ajax_hng_getnet_create_payment', [$this, 'ajax_create_payment']);
        add_action('wp_ajax_nopriv_hng_getnet_create_payment', [$this, 'ajax_create_payment']);
        add_action('wp_ajax_hng_getnet_webhook', [$this, 'ajax_webhook']);
        add_action('wp_ajax_nopriv_hng_getnet_webhook', [$this, 'ajax_webhook']);
    }
    
    private function get_api_url() {
        $environment = get_option('hng_getnet_environment', 'sandbox');
        return $environment === 'production' ? $this->api_url_production : $this->api_url_sandbox;
    }
    
    public function get_settings() {
        return [
            'enabled' => get_option('hng_getnet_enabled', 'no'),
            'seller_id' => get_option('hng_getnet_seller_id', ''),
            'client_id' => get_option('hng_getnet_client_id', ''),
            'client_secret' => get_option('hng_getnet_client_secret', ''),
            'environment' => get_option('hng_getnet_environment', 'sandbox'),
        ];
    }
    
    private function authenticate() {
        if ($this->access_token !== null) {
            return $this->access_token;
        }
        
        $settings = $this->get_settings();
        $url = $this->get_api_url() . '/auth/oauth/v2/token';
        
        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => 'Basic ' . base64_encode($settings['client_id'] . ':' . $settings['client_secret'])
            ],
            'body' => [
                'scope' => 'oob',
                'grant_type' => 'client_credentials'
            ],
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $this->access_token = $body['access_token'] ?? null;
        
        return $this->access_token;
    }
    
    public function process_payment($order_id, $payment_data) {
        $order = new HNG_Order($order_id);
        $method = $payment_data['payment_method'] ?? 'credit_card';
        
        try {
            switch ($method) {
                case 'pix':
                    return $this->process_pix($order, $payment_data);
                case 'credit_card':
                    return $this->process_credit_card($order, $payment_data);
                case 'debit_card':
                    return $this->process_debit_card($order, $payment_data);
                case 'boleto':
                    return $this->process_boleto($order, $payment_data);
                default:
                    return new WP_Error('invalid_method', 'Método de pagamento inválido');
            }
        } catch (Exception $e) {
            return new WP_Error('payment_error', $e->getMessage());
        }
    }
    
    private function process_pix($order, $payment_data) {
        $settings = $this->get_settings();
        
        $body = [
            'seller_id' => $settings['seller_id'],
            'amount' => (int) ($order->get_total() * 100),
            'currency' => 'BRL',
            'order_id' => 'ORDER_' . $order->get_id(),
            'customer' => [
                'customer_id' => 'CUSTOMER_' . $order->get_customer_id(),
                'first_name' => $order->get_billing_first_name(),
                'last_name' => $order->get_billing_last_name(),
                'document_type' => 'CPF',
                'document_number' => preg_replace('/\D/', '', $order->get_meta('_billing_cpf')),
                'email' => $order->get_customer_email()
            ]
        ];
        
        // Split payment
        if ($order->has_split()) {
            $body['marketplace_subseller_payments'] = $this->prepare_split($order);
        }
        
        $response = $this->make_request('/v1/payments/pix', 'POST', $body);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $qr_code = $response['qr_code'] ?? '';
        $qr_code_image = $response['qr_code_image_url'] ?? '';
        
        $order->add_meta('_getnet_payment_id', $response['payment_id']);
        $order->add_meta('_getnet_pix_qr_code', $qr_code);
        $order->set_status('pending');
        $order->save();
        
        return [
            'success' => true,
            'payment_id' => $response['payment_id'],
            'qr_code' => $qr_code,
            'qr_code_image' => $qr_code_image,
            'expires_at' => time() + 3600
        ];
    }
    
    private function process_credit_card($order, $payment_data) {
        $settings = $this->get_settings();
        $card_data = $payment_data['card'] ?? [];
        $installments = $payment_data['installments'] ?? 1;
        
        // Primeiro, tokeniza o cartão
        $card_token = $this->tokenize_card($card_data);
        
        if (is_wp_error($card_token)) {
            return $card_token;
        }
        
        $body = [
            'seller_id' => $settings['seller_id'],
            'amount' => (int) ($order->get_total() * 100),
            'currency' => 'BRL',
            'order' => [
                'order_id' => 'ORDER_' . $order->get_id(),
                'sales_tax' => 0,
                'product_type' => 'service'
            ],
            'customer' => [
                'customer_id' => 'CUSTOMER_' . $order->get_customer_id(),
                'first_name' => $order->get_billing_first_name(),
                'last_name' => $order->get_billing_last_name(),
                'document_type' => 'CPF',
                'document_number' => preg_replace('/\D/', '', $order->get_meta('_billing_cpf')),
                'email' => $order->get_customer_email(),
                'billing_address' => [
                    'street' => $order->get_billing_address_1(),
                    'number' => $order->get_meta('_billing_number') ?? 'S/N',
                    'complement' => $order->get_billing_address_2(),
                    'district' => $order->get_meta('_billing_neighborhood') ?? '',
                    'city' => $order->get_billing_city(),
                    'state' => $order->get_billing_state(),
                    'country' => 'BR',
                    'postal_code' => preg_replace('/\D/', '', $order->get_billing_postcode())
                ]
            ],
            'credit' => [
                'delayed' => false,
                'authenticated' => false,
                'pre_authorization' => false,
                'save_card_data' => false,
                'transaction_type' => 'FULL',
                'number_installments' => (int) $installments,
                'card' => [
                    'number_token' => $card_token['number_token'],
                    'cardholder_name' => $card_data['holder_name'] ?? '',
                    'security_code' => $card_data['cvv'] ?? '',
                    'expiration_month' => str_pad($card_data['exp_month'] ?? '', 2, '0', STR_PAD_LEFT),
                    'expiration_year' => $card_data['exp_year'] ?? ''
                ]
            ]
        ];
        
        // Split payment
        if ($order->has_split()) {
            $body['marketplace_subseller_payments'] = $this->prepare_split($order);
        }
        
        $response = $this->make_request('/v1/payments/credit', 'POST', $body);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $order->add_meta('_getnet_payment_id', $response['payment_id']);
        $order->add_meta('_getnet_authorization_code', $response['authorization_code'] ?? '');
        
        if ($response['status'] === 'APPROVED') {
            $order->set_status('processing');
            $order->add_note('Pagamento aprovado via Getnet - ID: ' . $response['payment_id']);
        } else {
            $order->set_status('failed');
            $order->add_note('Pagamento recusado via Getnet: ' . ($response['status_detail'] ?? ''));
        }
        
        $order->save();
        
        return [
            'success' => $response['status'] === 'APPROVED',
            'payment_id' => $response['payment_id'],
            'status' => $response['status'],
            'authorization_code' => $response['authorization_code'] ?? ''
        ];
    }
    
    private function process_debit_card($order, $payment_data) {
        $settings = $this->get_settings();
        $card_data = $payment_data['card'] ?? [];
        
        $card_token = $this->tokenize_card($card_data);
        
        if (is_wp_error($card_token)) {
            return $card_token;
        }
        
        $body = [
            'seller_id' => $settings['seller_id'],
            'amount' => (int) ($order->get_total() * 100),
            'currency' => 'BRL',
            'order' => [
                'order_id' => 'ORDER_' . $order->get_id(),
                'sales_tax' => 0,
                'product_type' => 'service'
            ],
            'customer' => [
                'customer_id' => 'CUSTOMER_' . $order->get_customer_id(),
                'first_name' => $order->get_billing_first_name(),
                'last_name' => $order->get_billing_last_name(),
                'document_type' => 'CPF',
                'document_number' => preg_replace('/\D/', '', $order->get_meta('_billing_cpf')),
                'email' => $order->get_customer_email()
            ],
            'debit' => [
                'card' => [
                    'number_token' => $card_token['number_token'],
                    'cardholder_name' => $card_data['holder_name'] ?? '',
                    'security_code' => $card_data['cvv'] ?? '',
                    'expiration_month' => str_pad($card_data['exp_month'] ?? '', 2, '0', STR_PAD_LEFT),
                    'expiration_year' => $card_data['exp_year'] ?? ''
                ]
            ]
        ];
        
        $response = $this->make_request('/v1/payments/debit', 'POST', $body);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $order->add_meta('_getnet_payment_id', $response['payment_id']);
        $order->set_status('pending');
        $order->save();
        
        return [
            'success' => true,
            'payment_id' => $response['payment_id'],
            'authentication_url' => $response['authentication_url'] ?? ''
        ];
    }
    
    private function process_boleto($order, $payment_data) {
        $settings = $this->get_settings();
        $due_date = gmdate('d/m/Y', strtotime('+3 days'));
        
        $body = [
            'seller_id' => $settings['seller_id'],
            'amount' => (int) ($order->get_total() * 100),
            'currency' => 'BRL',
            'order' => [
                'order_id' => 'ORDER_' . $order->get_id(),
                'sales_tax' => 0,
                'product_type' => 'service'
            ],
            'customer' => [
                'customer_id' => 'CUSTOMER_' . $order->get_customer_id(),
                'first_name' => $order->get_billing_first_name(),
                'last_name' => $order->get_billing_last_name(),
                'document_type' => 'CPF',
                'document_number' => preg_replace('/\D/', '', $order->get_meta('_billing_cpf')),
                'email' => $order->get_customer_email(),
                'billing_address' => [
                    'street' => $order->get_billing_address_1(),
                    'number' => $order->get_meta('_billing_number') ?? 'S/N',
                    'city' => $order->get_billing_city(),
                    'state' => $order->get_billing_state(),
                    'postal_code' => preg_replace('/\D/', '', $order->get_billing_postcode())
                ]
            ],
            'boleto' => [
                'our_number' => $order->get_id(),
                'document_number' => $order->get_id(),
                'expiration_date' => $due_date,
                'instructions' => 'Pagamento referente ao pedido ' . $order->get_id()
            ]
        ];
        
        // Split payment
        if ($order->has_split()) {
            $body['marketplace_subseller_payments'] = $this->prepare_split($order);
        }
        
        $response = $this->make_request('/v1/payments/boleto', 'POST', $body);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $order->add_meta('_getnet_payment_id', $response['payment_id']);
        $order->add_meta('_getnet_boleto_barcode', $response['boleto']['typeful_line'] ?? '');
        $order->set_status('pending');
        $order->save();
        
        return [
            'success' => true,
            'payment_id' => $response['payment_id'],
            'barcode' => $response['boleto']['typeful_line'] ?? '',
            'pdf_url' => $response['boleto']['_links']['boleto_pdf']['href'] ?? '',
            'due_date' => $due_date
        ];
    }
    
    private function tokenize_card($card_data) {
        $body = [
            'card_number' => preg_replace('/\s+/', '', $card_data['number'] ?? ''),
            'customer_id' => 'TEMP_' . time()
        ];
        
        $response = $this->make_request('/v1/tokens/card', 'POST', $body);
        
        return $response;
    }
    
    private function prepare_split($order) {
        $splits = [];
        $split_data = $order->get_split_data();
        
        foreach ($split_data as $recipient) {
            $splits[] = [
                'subseller_id' => $recipient['subseller_id'], // ID do subseller no Getnet
                'amount' => (int) ($recipient['amount'] * 100),
                'payment_type' => $recipient['payment_type'] ?? 'SPLIT',
                'settlement_date' => $recipient['settlement_date'] ?? gmdate('Y-m-d', strtotime('+30 days'))
            ];
        }
        
        return $splits;
    }
    
    public function create_pix_payment($order_id, $payment_data) {
        return $this->process_pix(new HNG_Order($order_id), $payment_data);
    }
    
    public function get_pix_status($payment_id) {
        $response = $this->make_request('/v1/payments/' . $payment_id, 'GET');
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return $this->normalize_status($response['status'] ?? '');
    }
    
    private function normalize_status($status) {
        $status_map = [
            'APPROVED' => 'paid',
            'PENDING' => 'pending',
            'DENIED' => 'failed',
            'CANCELED' => 'cancelled',
            'ERROR' => 'failed'
        ];
        
        return $status_map[$status] ?? 'pending';
    }
    
    public function ajax_create_payment() {
        check_ajax_referer('hng_checkout_nonce', 'nonce');
        
        if (!current_user_can('read')) {
            wp_send_json_error(['message' => 'Unauthorized'], 401);
        }
        
        $order_id = intval($_POST['order_id'] ?? 0);
        $payment_data = $_POST['payment_data'] ?? [];
        
        $result = $this->process_payment($order_id, $payment_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success($result);
    }
    
    public function ajax_webhook() {
        $body = file_get_contents('php://input');
        $data = json_decode($body, true);
        
        error_log('Getnet Webhook: ' . print_r($data, true));
        
        if (!isset($data['payment_id'])) {
            wp_send_json_error(['message' => 'Invalid webhook data'], 400);
        }
        
        // Buscar pedido pelo payment_id
        $orders = HNG_Order::query(['meta_key' => '_getnet_payment_id', 'meta_value' => $data['payment_id']]);
        
        if (empty($orders)) {
            wp_send_json_error(['message' => 'Order not found'], 404);
        }
        
        $order = $orders[0];
        
        // Atualizar status baseado no webhook
        $status = $this->normalize_status($data['status'] ?? '');
        
        if ($status === 'paid') {
            $order->set_status('completed');
            $order->add_note('Pagamento confirmado via webhook Getnet');
        } elseif ($status === 'cancelled' || $status === 'failed') {
            $order->set_status('cancelled');
            $order->add_note('Pagamento cancelado via webhook Getnet');
        }
        
        $order->save();
        
        wp_send_json_success(['message' => 'Webhook processed']);
    }
    
    private function make_request($endpoint, $method = 'GET', $body = null) {
        $token = $this->authenticate();
        
        if (is_wp_error($token)) {
            return $token;
        }
        
        $url = $this->get_api_url() . $endpoint;
        
        $args = [
            'method' => $method,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token
            ],
            'timeout' => 30
        ];
        
        if ($body !== null) {
            $args['body'] = json_encode($body);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($code >= 400) {
            return new WP_Error(
                'getnet_api_error',
                $body['message'] ?? $body['details'][0]['description'] ?? 'Erro na API Getnet',
                ['status' => $code]
            );
        }
        
        return $body;
    }
    
    public static function get_instance() {
        static $instance = null;
        if (null === $instance) {
            $instance = new self();
        }
        return $instance;
    }
}
