<?php
/**
 * Stone Gateway
 * 
 * Full integration with Stone (PIX, Card)
 * Split payment support for marketplaces
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Gateway_Stone extends HNG_Gateway_Base {
    
    public $id = 'stone';
    public $title = 'Stone';
    public $api_url_sandbox = 'https://sandbox-api.stone.com.br/v1';
    public $api_url_production = 'https://api.stone.com.br/v1';
    
    public function __construct() {
        parent::__construct();
        $this->supports = ['pix', 'credit_card', 'debit_card', 'split_payment', 'webhooks'];
        
        add_action('wp_ajax_hng_stone_create_payment', [$this, 'ajax_create_payment']);
        add_action('wp_ajax_nopriv_hng_stone_create_payment', [$this, 'ajax_create_payment']);
        add_action('wp_ajax_hng_stone_webhook', [$this, 'ajax_webhook']);
        add_action('wp_ajax_nopriv_hng_stone_webhook', [$this, 'ajax_webhook']);
    }
    
    private function get_api_url() {
        $environment = get_option('hng_stone_environment', 'sandbox');
        return $environment === 'production' ? $this->api_url_production : $this->api_url_sandbox;
    }
    
    public function get_settings() {
        return [
            'enabled' => get_option('hng_stone_enabled', 'no'),
            'api_key' => get_option('hng_stone_api_key', ''),
            'seller_key' => get_option('hng_stone_seller_key', ''),
            'environment' => get_option('hng_stone_environment', 'sandbox'),
        ];
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
                default:
                    return new WP_Error('invalid_method', 'Método de pagamento inválido');
            }
        } catch (Exception $e) {
            return new WP_Error('payment_error', $e->getMessage());
        }
    }
    
    private function process_pix($order, $payment_data) {
        $body = [
            'amount' => (int) ($order->get_total() * 100),
            'payment_method' => 'pix',
            'customer' => [
                'name' => $order->get_customer_name(),
                'document' => preg_replace('/\D/', '', $order->get_meta('_billing_cpf')),
                'email' => $order->get_customer_email()
            ],
            'metadata' => [
                'order_id' => $order->get_id(),
                'integration' => 'hng_commerce'
            ]
        ];
        
        // Split payment
        if ($order->has_split()) {
            $body['split'] = $this->prepare_split($order);
        }
        
        $response = $this->make_request('/charges', 'POST', $body);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $qr_code = $response['pix']['qr_code'] ?? '';
        $qr_code_url = $response['pix']['qr_code_url'] ?? '';
        
        $order->add_meta('_stone_charge_id', $response['id']);
        $order->add_meta('_stone_pix_qr_code', $qr_code);
        $order->set_status('pending');
        $order->save();
        
        return [
            'success' => true,
            'charge_id' => $response['id'],
            'qr_code' => $qr_code,
            'qr_code_url' => $qr_code_url,
            'expires_at' => $response['expires_at'] ?? time() + 3600
        ];
    }
    
    private function process_credit_card($order, $payment_data) {
        $card_data = $payment_data['card'] ?? [];
        $installments = $payment_data['installments'] ?? 1;
        
        $body = [
            'amount' => (int) ($order->get_total() * 100),
            'payment_method' => 'credit_card',
            'installments' => (int) $installments,
            'capture' => true,
            'card' => [
                'number' => preg_replace('/\s+/', '', $card_data['number'] ?? ''),
                'holder_name' => $card_data['holder_name'] ?? '',
                'exp_month' => str_pad($card_data['exp_month'] ?? '', 2, '0', STR_PAD_LEFT),
                'exp_year' => $card_data['exp_year'] ?? '',
                'cvv' => $card_data['cvv'] ?? ''
            ],
            'customer' => [
                'name' => $order->get_customer_name(),
                'document' => preg_replace('/\D/', '', $order->get_meta('_billing_cpf')),
                'email' => $order->get_customer_email()
            ],
            'billing' => [
                'address' => [
                    'line_1' => $order->get_billing_address_1(),
                    'line_2' => $order->get_billing_address_2(),
                    'zip_code' => preg_replace('/\D/', '', $order->get_billing_postcode()),
                    'city' => $order->get_billing_city(),
                    'state' => $order->get_billing_state(),
                    'country' => 'BR'
                ]
            ],
            'metadata' => [
                'order_id' => $order->get_id(),
                'integration' => 'hng_commerce'
            ]
        ];
        
        // Split payment
        if ($order->has_split()) {
            $body['split'] = $this->prepare_split($order);
        }
        
        $response = $this->make_request('/charges', 'POST', $body);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $order->add_meta('_stone_charge_id', $response['id']);
        $order->add_meta('_stone_transaction_id', $response['last_transaction']['id'] ?? '');
        
        if ($response['status'] === 'paid') {
            $order->set_status('processing');
            $order->add_note('Pagamento aprovado via Stone - ID: ' . $response['id']);
        } elseif ($response['status'] === 'pending') {
            $order->set_status('pending');
            $order->add_note('Pagamento pendente via Stone');
        } else {
            $order->set_status('failed');
            $order->add_note('Pagamento recusado via Stone: ' . ($response['last_transaction']['gateway_message'] ?? ''));
        }
        
        $order->save();
        
        return [
            'success' => $response['status'] === 'paid',
            'charge_id' => $response['id'],
            'status' => $response['status'],
            'message' => $response['last_transaction']['gateway_message'] ?? ''
        ];
    }
    
    private function process_debit_card($order, $payment_data) {
        $card_data = $payment_data['card'] ?? [];
        
        $body = [
            'amount' => (int) ($order->get_total() * 100),
            'payment_method' => 'debit_card',
            'capture' => true,
            'card' => [
                'number' => preg_replace('/\s+/', '', $card_data['number'] ?? ''),
                'holder_name' => $card_data['holder_name'] ?? '',
                'exp_month' => str_pad($card_data['exp_month'] ?? '', 2, '0', STR_PAD_LEFT),
                'exp_year' => $card_data['exp_year'] ?? '',
                'cvv' => $card_data['cvv'] ?? ''
            ],
            'customer' => [
                'name' => $order->get_customer_name(),
                'document' => preg_replace('/\D/', '', $order->get_meta('_billing_cpf')),
                'email' => $order->get_customer_email()
            ],
            'metadata' => [
                'order_id' => $order->get_id(),
                'integration' => 'hng_commerce'
            ]
        ];
        
        $response = $this->make_request('/charges', 'POST', $body);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $order->add_meta('_stone_charge_id', $response['id']);
        $order->set_status('processing');
        $order->save();
        
        return [
            'success' => true,
            'charge_id' => $response['id'],
            'authentication_url' => $response['last_transaction']['authentication_url'] ?? ''
        ];
    }
    
    private function prepare_split($order) {
        $splits = [];
        $split_data = $order->get_split_data();
        
        foreach ($split_data as $recipient) {
            $splits[] = [
                'recipient_id' => $recipient['recipient_id'], // ID do recebedor Stone
                'amount' => (int) ($recipient['amount'] * 100),
                'charge_processing_fee' => $recipient['charge_processing_fee'] ?? true,
                'charge_remainder_fee' => $recipient['charge_remainder_fee'] ?? false
            ];
        }
        
        return $splits;
    }
    
    public function create_pix_payment($order_id, $payment_data) {
        return $this->process_pix(new HNG_Order($order_id), $payment_data);
    }
    
    public function get_pix_status($charge_id) {
        $response = $this->make_request('/charges/' . $charge_id, 'GET');
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return $this->normalize_status($response['status'] ?? '');
    }
    
    private function normalize_status($status) {
        $status_map = [
            'paid' => 'paid',
            'pending' => 'pending',
            'failed' => 'failed',
            'canceled' => 'cancelled',
            'refunded' => 'refunded'
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
        
        error_log('Stone Webhook: ' . print_r($data, true));
        
        if (!isset($data['id'])) {
            wp_send_json_error(['message' => 'Invalid webhook data'], 400);
        }
        
        $event_type = $data['type'] ?? '';
        $charge_data = $data['data'] ?? [];
        
        // Buscar pedido pelo charge_id
        $orders = HNG_Order::query(['meta_key' => '_stone_charge_id', 'meta_value' => $charge_data['id']]);
        
        if (empty($orders)) {
            wp_send_json_error(['message' => 'Order not found'], 404);
        }
        
        $order = $orders[0];
        
        // Atualizar status baseado no webhook
        switch ($event_type) {
            case 'charge.paid':
                $order->set_status('completed');
                $order->add_note('Pagamento confirmado via webhook Stone');
                break;
            case 'charge.refunded':
                $order->set_status('refunded');
                $order->add_note('Pagamento reembolsado via webhook Stone');
                break;
            case 'charge.failed':
            case 'charge.canceled':
                $order->set_status('cancelled');
                $order->add_note('Pagamento cancelado via webhook Stone');
                break;
        }
        
        $order->save();
        
        wp_send_json_success(['message' => 'Webhook processed']);
    }
    
    private function make_request($endpoint, $method = 'GET', $body = null) {
        $settings = $this->get_settings();
        $url = $this->get_api_url() . $endpoint;
        
        $args = [
            'method' => $method,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $settings['api_key']
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
                'stone_api_error',
                $body['message'] ?? 'Erro na API Stone',
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
