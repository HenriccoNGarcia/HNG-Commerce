<?php
/**
 * Pagar.me Gateway
 * 
 * Full integration with Pagar.me (PIX, Card, Boleto)
 * Split payment support
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Gateway_Pagarme extends HNG_Gateway_Base {
    
    public $id = 'pagarme';
    public $title = 'Pagar.me';
    public $api_url = 'https://api.pagar.me/core/v5';
    
    public function __construct() {
        parent::__construct();
        $this->supports = ['pix', 'credit_card', 'boleto', 'split_payment', 'customers', 'subscriptions', 'webhooks'];
        
        add_action('wp_ajax_hng_pagarme_create_payment', [$this, 'ajax_create_payment']);
        add_action('wp_ajax_nopriv_hng_pagarme_create_payment', [$this, 'ajax_create_payment']);

        // Webhook endpoint (admin and public)
        add_action('wp_ajax_hng_pagarme_webhook', [$this, 'ajax_webhook']);
        add_action('wp_ajax_nopriv_hng_pagarme_webhook', [$this, 'ajax_webhook']);
    }
    
    private function is_advanced_enabled() {
        return get_option('hng_pagarme_advanced_integration', 'no') === 'yes';
    }
    
    public function get_settings() {
        return [
            'enabled' => get_option('hng_pagarme_enabled', 'no'),
            'secret_key' => get_option('hng_pagarme_secret_key', ''),
            'public_key' => get_option('hng_pagarme_public_key', ''),
        ];
    }
    
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
                    throw new Exception(__('Método inválido.', 'hng-commerce'));
            }
        } catch (Exception $e) {
            return new WP_Error('payment_error', $e->getMessage());
        }
    }

    /**
     * Check configuration
     */
    public function is_configured() {
        $settings = $this->get_settings();
        return !empty($settings['secret_key']) && !empty($settings['public_key']);
    }
    
    private function process_pix($order, $payment_data) {
        $payload = [
            'amount' => intval($order->get_total() * 100),
            'payment_method' => 'pix',
            'pix' => [
                'expires_in' => 1800, // 30 minutes
            ],
            'customer' => $this->format_customer($order, $payment_data),
            'items' => $this->format_items($order),
            'metadata' => ['order_id' => $order->get_id()],
        ];
        
        // Split payment support
        if ($order->has_split()) {
            $payload['split_rules'] = $this->prepare_split_rules($order);
        }
        
        $response = $this->request('POST', '/orders', $payload);
        
        if (is_wp_error($response)) return $response;
        
        $charge = $response['charges'][0] ?? [];
        $pix_data = [
            'qr_code' => $charge['last_transaction']['qr_code'] ?? '',
            'qr_code_url' => $charge['last_transaction']['qr_code_url'] ?? '',
            'expires_at' => $charge['last_transaction']['expires_at'] ?? '',
        ];
        
        update_post_meta($order->get_id(), '_pagarme_order_id', $response['id']);
        update_post_meta($order->get_id(), '_pagarme_pix_data', $pix_data);
        
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
     * Process Credit Card payment
     */
    private function process_credit_card($order, $payment_data) {
        $payload = [
            'amount' => intval($order->get_total() * 100),
            'payment_method' => 'credit_card',
            'customer' => $this->format_customer($order, $payment_data),
            'items' => $this->format_items($order),
            'metadata' => ['order_id' => $order->get_id()],
        ];
        
        // Split payment support
        if ($order->has_split()) {
            $payload['split_rules'] = $this->prepare_split_rules($order);
        }
        
        // Se tiver card_token (tokenizado no front-end)
        if (!empty($payment_data['card_token'])) {
            $payload['credit_card'] = [
                'card_token' => $payment_data['card_token'],
                'installments' => intval($payment_data['installments'] ?? 1),
            ];
        } else {
            // Dados do cartão direto (não recomendado em produção)
            $payload['credit_card'] = [
                'card' => [
                    'number' => preg_replace('/\D/', '', $payment_data['card_number'] ?? ''),
                    'holder_name' => $payment_data['holder_name'] ?? '',
                    'exp_month' => intval($payment_data['expiry_month'] ?? 1),
                    'exp_year' => intval($payment_data['expiry_year'] ?? gmdate('Y')),
                    'cvv' => $payment_data['cvv'] ?? '',
                ],
                'installments' => intval($payment_data['installments'] ?? 1),
            ];
        }
        
        $response = $this->request('POST', '/orders', $payload);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $charge = $response['charges'][0] ?? [];
        $status = $charge['status'] ?? '';
        
        update_post_meta($order->get_id(), '_pagarme_order_id', $response['id']);
        update_post_meta($order->get_id(), '_pagarme_charge_id', $charge['id'] ?? '');
        update_post_meta($order->get_id(), '_pagarme_status', $status);
        
        // Calcular e registrar taxas do plugin HNG
        $this->register_hng_fees($order, 'credit_card', $charge['id'] ?? $response['id']);
        
        if ($status === 'paid') {
            $order->update_status('processing');
            
            return [
                'success' => true,
                'payment_method' => 'credit_card',
                'status' => 'approved',
                'message' => __('Pagamento aprovado!', 'hng-commerce'),
                'redirect_url' => $order->get_order_received_url(),
            ];
        } elseif ($status === 'pending') {
            return [
                'success' => true,
                'payment_method' => 'credit_card',
                'status' => 'pending',
                'message' => __('Pagamento em análise.', 'hng-commerce'),
                'redirect_url' => $order->get_order_received_url(),
            ];
        } else {
            return [
                'success' => false,
                'payment_method' => 'credit_card',
                'status' => $status,
                'message' => $charge['last_transaction']['gateway_response']['message'] ?? __('Pagamento não aprovado.', 'hng-commerce'),
            ];
        }
    }
    
    /**
     * Process Boleto payment
     */
    private function process_boleto($order, $payment_data) {
        // Calculate due date (3 days from now)
        $due_date = gmdate('Y-m-d', strtotime('+3 days'));
        
        $payload = [
            'amount' => intval($order->get_total() * 100),
            'payment_method' => 'boleto',
            'customer' => $this->format_customer($order, $payment_data),
            'items' => $this->format_items($order),
            'metadata' => ['order_id' => $order->get_id()],
            'boleto' => [
                'due_at' => $due_date . 'T23:59:59Z',
                'instructions' => __('Pagamento referente ao pedido na loja online.', 'hng-commerce'),
            ],
        ];
        
        // Split payment support
        if ($order->has_split()) {
            $payload['split_rules'] = $this->prepare_split_rules($order);
        }
        
        $response = $this->request('POST', '/orders', $payload);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $charge = $response['charges'][0] ?? [];
        $boleto = $charge['last_transaction'] ?? [];
        
        $boleto_data = [
            'barcode' => $boleto['barcode'] ?? $boleto['line'] ?? '',
            'pdf_url' => $boleto['pdf'] ?? '',
            'due_date' => $due_date,
            'charge_id' => $charge['id'] ?? '',
        ];
        
        update_post_meta($order->get_id(), '_pagarme_order_id', $response['id']);
        update_post_meta($order->get_id(), '_pagarme_charge_id', $charge['id'] ?? '');
        update_post_meta($order->get_id(), '_pagarme_boleto_data', $boleto_data);
        
        // Calcular e registrar taxas do plugin HNG
        $this->register_hng_fees($order, 'boleto', $charge['id'] ?? $response['id']);
        
        return [
            'success' => true,
            'payment_method' => 'boleto',
            'boleto_data' => $boleto_data,
            'redirect_url' => home_url('/pagamento/boleto?order_id=' . $order->get_id()),
        ];
    }

    /**
     * Create customer in Pagar.me
     */
    public function create_customer($data) {
        if (!$this->is_advanced_enabled()) {
            return new WP_Error('hng_pg_adv_off', 'Integraá§á¡o avaná§ada desativada para Pagar.me');
        }
        $payload = [
            'name' => $data['name'] ?? '',
            'email' => $data['email'] ?? '',
            'type' => 'individual',
            'document' => preg_replace('/\D/', '', $data['document'] ?? ''),
            'document_type' => strlen(preg_replace('/\D/', '', $data['document'] ?? '')) > 11 ? 'CNPJ' : 'CPF',
        ];
        return $this->request('POST', '/customers', $payload);
    }

    /**
     * Create subscription in Pagar.me (charges card/boleto/pix periodically)
     */
    public function create_subscription($plan_id, $customer_id, $payment_method = 'credit_card', $extra = []) {
        if (!$this->is_advanced_enabled()) {
            return new WP_Error('hng_pg_adv_off', 'Integraá§á¡o avaná§ada desativada para Pagar.me');
        }
        $payload = [
            'plan_id' => $plan_id,
            'customer_id' => $customer_id,
            'payment_method' => $payment_method,
        ] + $extra;
        return $this->request('POST', '/subscriptions', $payload);
    }

    /**
     * Create recipient (for split payments)
     */
    public function create_recipient($data) {
        $payload = [
            'name' => $data['name'] ?? '',
            'email' => $data['email'] ?? '',
            'document' => preg_replace('/\D/', '', $data['document'] ?? ''),
            'transfer_enabled' => true,
        ];
        return $this->request('POST', '/recipients', $payload);
    }

    /**
     * Create order with split payment
     */
    public function create_split_payment($order, $payment_data, $split_rules) {
        if (!$this->is_advanced_enabled()) {
            return new WP_Error('hng_pg_adv_off', 'Integraá§á¡o avaná§ada desativada para Pagar.me');
        }
        $payload = [
            'amount' => intval($order->get_total() * 100),
            'payment_method' => $payment_data['payment_method'] ?? 'credit_card',
            'items' => $this->format_items($order),
            'customer' => $this->format_customer($order, $payment_data),
            'split_rules' => $split_rules,
            'metadata' => ['order_id' => $order->get_id()],
        ];
        return $this->request('POST', '/orders', $payload);
    }

    /**
     * Webhook handler
     */
    public function ajax_webhook() {
        // Minimal webhook: update order status based on event
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading php://input for webhook raw POST data
        $raw = file_get_contents('php://input');
        $event = json_decode($raw, true);
        if (!is_array($event)) {
            wp_send_json_error(['message' => 'Evento invá¡Â¡lido']);
        }
        $type = $event['type'] ?? '';
        $data = $event['data'] ?? [];
        $order_id = isset($data['metadata']['order_id']) ? intval($data['metadata']['order_id']) : 0;
        if ($order_id) {
            $order = new HNG_Order($order_id);
            if ($type === 'charge.paid' || $type === 'order.paid') {
                $order->update_status('processing');
            } elseif ($type === 'charge.refunded') {
                $order->update_status('refunded');
            } elseif ($type === 'order.canceled') {
                $order->update_status('cancelled');
            }
        }
        wp_send_json_success(['received' => true]);
    }
    
    private function prepare_split_rules($order) {
        $split_rules = [];
        $split_data = $order->get_split_data();
        
        foreach ($split_data as $recipient) {
            $split_rules[] = [
                'recipient_id' => $recipient['recipient_id'], // ID do recebedor no Pagar.me
                'amount' => intval($recipient['amount'] * 100), // Valor em centavos
                'charge_processing_fee' => $recipient['charge_processing_fee'] ?? true,
                'liable' => $recipient['liable'] ?? true,
                'charge_remainder' => $recipient['charge_remainder'] ?? false
            ];
        }
        
        return $split_rules;
    }

    
    
    private function format_customer($order, $payment_data) {
        return [
            'name' => $payment_data['customer_name'] ?? '',
            'email' => $order->get_customer_email(),
            'type' => 'individual',
            'document' => preg_replace('/\D/', '', $payment_data['document'] ?? ''),
            'document_type' => 'CPF',
        ];
    }
    
    private function format_items($order) {
        $items = [];
        foreach ($order->get_items() as $item) {
            $items[] = [
                'code' => 'ITEM_' . $item['product_id'],
                'description' => $item['name'],
                'amount' => intval($item['price'] * 100),
                'quantity' => $item['quantity'],
            ];
        }
        return $items;
    }
    
    private function request($method, $endpoint, $data = []) {
        $settings = $this->get_settings();
        $secret_key = $settings['secret_key'];
        
        if (!$secret_key) {
            return new WP_Error('no_key', __('Secret key não configurada.', 'hng-commerce'));
        }
        
        $url = $this->api_url . $endpoint;
        
        $args = [
            'method' => $method,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($secret_key . ':'),
                'Content-Type' => 'application/json',
            ],
                'body' => wp_json_encode($data),
            'timeout' => 30,
        ];
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) return $response;
        
        $body = wp_remote_retrieve_body($response);
        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode($body, true);
        
        if ($code >= 400) {
            return new WP_Error('api_error', $data['message'] ?? 'Erro na API');
        }
        
        return $data;
    }
    
    public function ajax_create_payment() {
        // Nonce validation (specific nonce or legacy generic for backward compatibility)
        $nonce = isset($_REQUEST['nonce']) ? wp_unslash($_REQUEST['nonce']) : '';
        $nonce_verified = wp_verify_nonce($nonce, 'hng_pagarme_create_payment') || 
                         wp_verify_nonce($nonce, 'HNG Commerce');
        if (!$nonce_verified) {
            wp_send_json_error(['message' => __('Verificação de segurança falhou.', 'hng-commerce')]);
        }

        $post = function_exists('wp_unslash') ? wp_unslash($_POST) : $_POST;
        $order_id = absint($post['order_id'] ?? 0);

        if (!$order_id) {
            wp_send_json_error(['message' => __('Pedido inválido.', 'hng-commerce')]);
        }

        $raw = is_array($post['payment_data'] ?? null) ? $post['payment_data'] : (is_array($post) ? $post : []);
        $method = sanitize_text_field($raw['payment_method'] ?? ($post['payment_method'] ?? 'pix'));

        $payment_data = ['payment_method' => $method];

        if ($method === 'pix') {
            $payment_data['customer_name'] = isset($raw['customer_name']) ? sanitize_text_field($raw['customer_name']) : '';
            $payment_data['document'] = isset($raw['document']) ? preg_replace('/\D/', '', $raw['document']) : '';
            $payment_data['amount'] = isset($raw['amount']) ? floatval($raw['amount']) : 0;
        } elseif ($method === 'credit_card') {
            $payment_data['installments'] = isset($raw['installments']) ? absint($raw['installments']) : 1;
            $payment_data['card_token'] = isset($raw['card_token']) ? sanitize_text_field($raw['card_token']) : '';
        }

        $result = $this->process_payment($order_id, $payment_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success($result);
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
        
        // Determinar tipo de produto (físico por padrão)
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

new HNG_Gateway_Pagarme();

// Handler de renovação manual de assinatura (Pagar.me)
add_action('hng_subscription_manual_renewal', function($subscription_id, $order_id, $payment_method) {
    try {
        $subscription = new HNG_Subscription($subscription_id);
        if ($subscription->get_gateway() !== 'pagarme') return;

        $class = 'HNG_Gateway_Pagarme';
        if (!class_exists($class)) {
            $path = HNG_COMMERCE_PATH . 'gateways/pagarme/class-gateway-pagarme.php';
            if (file_exists($path)) require_once $path;
        }
        if (!class_exists($class)) return;

        $gw = method_exists($class, 'instance') ? $class::instance() : new $class();
        if (!$gw->is_configured()) return;

        $order = new HNG_Order($order_id);
        $customer_email = $order->get_customer_email();
        $amount = $subscription->get_amount();

        $payment_data = [
            'order_id' => $order_id,
            'amount' => $amount,
            'customer_email' => $customer_email,
            'description' => sprintf('Renovação Assinatura #%d', $subscription_id),
        ];

        if ($payment_method === 'pix' && method_exists($gw, 'process_pix')) {
            $result = $gw->process_pix(new HNG_Order($order_id), $payment_data);
        } else {
            $result = $gw->process_payment($order_id, $payment_data);
        }

        if (is_wp_error($result)) {
            if (function_exists('hng_files_log_append')) {
                hng_files_log_append(HNG_COMMERCE_PATH . 'logs/gateways-pagarme.log', '[Pagarme Renovaá§á¡o] ' . $result->get_error_message() . PHP_EOL);
            }
            return;
        }
        if (is_array($result)) update_post_meta($order_id, '_payment_data', $result);

        $candidates = ['redirect_url','payment_url','paymentUrl','pix_data','qr_code','qr_code_url','order_id'];
        $payment_url = '';
        if (is_array($result)) { foreach ($candidates as $k) { if (!empty($result[$k])) { $payment_url = $result[$k]; break; } } }
        if (!empty($payment_url)) update_post_meta($order_id, '_payment_url', $payment_url);

    } catch (Exception $e) {
        if (function_exists('hng_files_log_append')) {
            hng_files_log_append(HNG_COMMERCE_PATH . 'logs/gateways-pagarme.log', '[Pagarme Renovaá§á¡o] Exception: ' . $e->getMessage() . PHP_EOL);
        }
    }
}, 10, 3);
