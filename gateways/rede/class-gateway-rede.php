<?php
/**
 * Rede Gateway (e-Rede API)
 * 
 * Full integration with Rede (PIX, Card, Boleto)
 * Split payment support for marketplaces
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Gateway_Rede extends HNG_Gateway_Base {
    
    public $id = 'rede';
    public $title = 'Rede';
    public $api_url_sandbox = 'https://sandbox.gateway.erede.com.br';
    public $api_url_production = 'https://gateway.erede.com.br';
    
    public function __construct() {
        parent::__construct();
        $this->supports = ['pix', 'credit_card', 'debit_card', 'split_payment', 'webhooks'];
        
        add_action('wp_ajax_hng_rede_create_payment', [$this, 'ajax_create_payment']);
        add_action('wp_ajax_nopriv_hng_rede_create_payment', [$this, 'ajax_create_payment']);
        add_action('wp_ajax_hng_rede_webhook', [$this, 'ajax_webhook']);
        add_action('wp_ajax_nopriv_hng_rede_webhook', [$this, 'ajax_webhook']);
    }
    
    private function get_api_url() {
        $environment = get_option('hng_rede_environment', 'sandbox');
        return $environment === 'production' ? $this->api_url_production : $this->api_url_sandbox;
    }
    
    public function get_settings() {
        return [
            'enabled' => get_option('hng_rede_enabled', 'no'),
            'pv' => get_option('hng_rede_pv', ''),
            'token' => get_option('hng_rede_token', ''),
            'environment' => get_option('hng_rede_environment', 'sandbox'),
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
        $settings = $this->get_settings();
        
        $body = [
            'capture' => false,
            'kind' => 'pix',
            'reference' => 'pedido_' . $order->get_id(),
            'amount' => (int) ($order->get_total() * 100), // centavos
            'additionalData' => [
                'paymentType' => 'PIX'
            ],
            'urls' => [
                'callback' => home_url('/wp-json/hng/v1/webhook/rede')
            ]
        ];
        
        // Split payment
        if ($order->has_split()) {
            $body['splits'] = $this->prepare_splits($order);
        }
        
        $response = $this->make_request('/transactions', 'POST', $body);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $qr_code = $response['additionalData']['qrCode'] ?? '';
        $qr_code_base64 = $response['additionalData']['qrCodeImage'] ?? '';
        
        $order->add_meta('_rede_tid', $response['tid']);
        $order->add_meta('_rede_pix_qr_code', $qr_code);
        $order->set_status('pending');
        $order->save();
        
        return [
            'success' => true,
            'tid' => $response['tid'],
            'qr_code' => $qr_code,
            'qr_code_base64' => $qr_code_base64,
            'expires_at' => time() + 3600
        ];
    }
    
    private function process_credit_card($order, $payment_data) {
        $settings = $this->get_settings();
        
        $card_data = $payment_data['card'] ?? [];
        $installments = $payment_data['installments'] ?? 1;
        
        $body = [
            'capture' => true,
            'kind' => 'credit',
            'reference' => 'pedido_' . $order->get_id(),
            'amount' => (int) ($order->get_total() * 100),
            'installments' => (int) $installments,
            'cardHolderName' => $card_data['holder_name'] ?? '',
            'cardNumber' => preg_replace('/\s+/', '', $card_data['number'] ?? ''),
            'expirationMonth' => $card_data['exp_month'] ?? '',
            'expirationYear' => $card_data['exp_year'] ?? '',
            'securityCode' => $card_data['cvv'] ?? '',
            'urls' => [
                'callback' => home_url('/wp-json/hng/v1/webhook/rede')
            ]
        ];
        
        // Split payment
        if ($order->has_split()) {
            $body['splits'] = $this->prepare_splits($order);
        }
        
        $response = $this->make_request('/transactions', 'POST', $body);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $order->add_meta('_rede_tid', $response['tid']);
        $order->add_meta('_rede_authorization_code', $response['authorizationCode'] ?? '');
        
        if (isset($response['returnCode']) && $response['returnCode'] === '00') {
            $order->set_status('processing');
            $order->add_note('Pagamento aprovado via Rede - TID: ' . $response['tid']);
        } else {
            $order->set_status('failed');
            $order->add_note('Pagamento recusado via Rede');
        }
        
        $order->save();
        
        return [
            'success' => isset($response['returnCode']) && $response['returnCode'] === '00',
            'tid' => $response['tid'],
            'authorization_code' => $response['authorizationCode'] ?? '',
            'return_message' => $response['returnMessage'] ?? ''
        ];
    }
    
    private function process_debit_card($order, $payment_data) {
        $card_data = $payment_data['card'] ?? [];
        
        $body = [
            'capture' => true,
            'kind' => 'debit',
            'reference' => 'pedido_' . $order->get_id(),
            'amount' => (int) ($order->get_total() * 100),
            'cardHolderName' => $card_data['holder_name'] ?? '',
            'cardNumber' => preg_replace('/\s+/', '', $card_data['number'] ?? ''),
            'expirationMonth' => $card_data['exp_month'] ?? '',
            'expirationYear' => $card_data['exp_year'] ?? '',
            'securityCode' => $card_data['cvv'] ?? '',
            'urls' => [
                'callback' => home_url('/wp-json/hng/v1/webhook/rede')
            ]
        ];
        
        // Split não disponível para débito na Rede
        
        $response = $this->make_request('/transactions', 'POST', $body);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $order->add_meta('_rede_tid', $response['tid']);
        $order->set_status('processing');
        $order->save();
        
        return [
            'success' => true,
            'tid' => $response['tid'],
            'authentication_url' => $response['threeDSecure']['url'] ?? ''
        ];
    }
    
    private function prepare_splits($order) {
        $splits = [];
        $split_data = $order->get_split_data();
        
        foreach ($split_data as $recipient) {
            $splits[] = [
                'receiver' => $recipient['pv'], // PV do recebedor
                'amount' => (int) ($recipient['amount'] * 100),
                'fares' => [
                    'mdr' => $recipient['mdr'] ?? 0, // Taxa MDR em centavos
                    'fee' => 0
                ]
            ];
        }
        
        return $splits;
    }
    
    public function create_pix_payment($order_id, $payment_data) {
        return $this->process_pix(new HNG_Order($order_id), $payment_data);
    }
    
    public function get_pix_status($charge_id) {
        $response = $this->make_request('/transactions/' . $charge_id, 'GET');
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return $this->normalize_status($response['returnCode'] ?? '');
    }
    
    private function normalize_status($return_code) {
        $status_map = [
            '00' => 'paid',
            '05' => 'pending',
            '57' => 'expired',
            '77' => 'cancelled'
        ];
        
        return $status_map[$return_code] ?? 'pending';
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
        
        error_log('Rede Webhook: ' . print_r($data, true));
        
        if (!isset($data['tid'])) {
            wp_send_json_error(['message' => 'Invalid webhook data'], 400);
        }
        
        // Buscar pedido pelo TID
        $orders = HNG_Order::query(['meta_key' => '_rede_tid', 'meta_value' => $data['tid']]);
        
        if (empty($orders)) {
            wp_send_json_error(['message' => 'Order not found'], 404);
        }
        
        $order = $orders[0];
        
        // Atualizar status baseado no webhook
        if (isset($data['returnCode'])) {
            $status = $this->normalize_status($data['returnCode']);
            
            if ($status === 'paid') {
                $order->set_status('completed');
                $order->add_note('Pagamento confirmado via webhook Rede');
            } elseif ($status === 'cancelled' || $status === 'expired') {
                $order->set_status('cancelled');
                $order->add_note('Pagamento cancelado/expirado via webhook Rede');
            }
            
            $order->save();
        }
        
        wp_send_json_success(['message' => 'Webhook processed']);
    }
    
    private function make_request($endpoint, $method = 'GET', $body = null) {
        $settings = $this->get_settings();
        $url = $this->get_api_url() . $endpoint;
        
        $args = [
            'method' => $method,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($settings['pv'] . ':' . $settings['token'])
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
                'rede_api_error',
                $body['error']['message'] ?? 'Erro na API Rede',
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
