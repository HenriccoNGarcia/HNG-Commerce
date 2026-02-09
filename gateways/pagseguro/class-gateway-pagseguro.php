<?php
/**
 * PagSeguro Gateway
 * 
 * Full integration with PagSeguro (PIX, Card, Boleto)
 * Direct payment to merchant account
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Gateway_PagSeguro extends HNG_Gateway_Base {
    
    /**
     * Gateway ID
     */
    public $id = 'pagseguro';
    
    /**
     * Gateway title
     */
    public $title = 'PagSeguro';
    
    /**
     * API URLs
     */
    public $api_url = 'https://api.pagseguro.com';
    private $sandbox_url = 'https://sandbox.api.pagseguro.com';
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
        
        $this->supports = ['pix', 'credit_card', 'boleto'];
        
        // Hooks
        add_action('wp_ajax_hng_ps_create_payment', [$this, 'ajax_create_payment']);
        add_action('wp_ajax_nopriv_hng_ps_create_payment', [$this, 'ajax_create_payment']);
        add_action('wp_ajax_hng_ps_check_payment', [$this, 'ajax_check_payment']);
        add_action('wp_ajax_nopriv_hng_ps_check_payment', [$this, 'ajax_check_payment']);
    }

    /** Integração avançada habilitada? */
    private function is_advanced_enabled() {
        return get_option('hng_pagseguro_advanced_integration', 'no') === 'yes';
    }
    
    /**
     * Get settings
     */
    public function get_settings() {
        return [
            'enabled' => get_option('hng_ps_enabled', 'no'),
            'sandbox' => get_option('hng_ps_sandbox', 'yes'),
            'token' => get_option('hng_ps_token', ''),
            'email' => get_option('hng_ps_email', ''),
        ];
    }
    
    /**
     * Get API URL
     */
    private function get_api_url() {
        $settings = $this->get_settings();
        return ($settings['sandbox'] === 'yes') ? $this->sandbox_url : $this->api_url;
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
     * Criar cliente (placeholder para futura integração avançada)
     */
    public function create_customer($data) {
        if (!$this->is_advanced_enabled()) {
            return new WP_Error('hng_ps_adv_off', __('Integração avançada desativada para PagSeguro.', 'hng-commerce'));
        }
        // PagSeguro não expõe criação explícita de cliente na API pública v4 do fluxo atual usado aqui.
        // Retorna dados mínimos para manter o contrato com o checkout.
        return ['id' => $data['email'] ?? uniqid('ps_', true)];
    }

    /**
     * Criar assinatura (não suportado no fluxo atual)
     */
    public function create_subscription($customer_id, $plan_data) {
        if (!$this->is_advanced_enabled()) {
            return new WP_Error('hng_ps_adv_off', __('Integração avançada desativada para PagSeguro.', 'hng-commerce'));
        }
        return new WP_Error('hng_ps_sub_not_supported', __('Assinaturas não suportadas neste gateway no fluxo atual.', 'hng-commerce'));
    }
    
    /**
     * Process PIX payment
     */
    private function process_pix($order, $payment_data) {
        $settings = $this->get_settings();
        
        // NOVO: Calcular split payment via API
        $split_data = $this->calculate_and_prepare_split($order, 'pix');
        
        if (is_wp_error($split_data)) {
            $this->log('Split calculation error: ' . $split_data->get_error_message());
            // Continuar mesmo com erro (fallback para pagamento simples)
        }
        
        // Create order in PagSeguro
        $payload = [
            'reference_id' => 'ORDER_' . $order->get_id(),
            'customer' => [
                'name' => $payment_data['customer_name'] ?? '',
                'email' => $order->get_customer_email(),
                'tax_id' => preg_replace('/\D/', '', $payment_data['document'] ?? ''),
            ],
            'items' => $this->format_items($order),
            'qr_codes' => [
                [
                    'amount' => [
                        'value' => intval($order->get_total() * 100), // cents
                    ],
                    'expiration_date' => gmdate('Y-m-d\TH:i:s', strtotime('+30 minutes')),
                ]
            ],
            'notification_urls' => [
                home_url('/hng-webhook/pagseguro')
            ],
        ];
        
        // NOVO: Adicionar split_rules se disponível
        if (!is_wp_error($split_data) && !empty($split_data['split_rules'])) {
            $payload['split'] = [
                'rules' => $split_data['split_rules']
            ];
        }
        
        $response = $this->request('POST', '/orders', $payload);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        update_post_meta($order->get_id(), '_ps_order_id', $response['id']);
        
        $pix_data = [
            'qr_code' => $response['qr_codes'][0]['text'] ?? '',
            'qr_code_base64' => $response['qr_codes'][0]['links'][0]['href'] ?? '',
            'order_id' => $response['id'],
            'expiration_date' => $response['qr_codes'][0]['expiration_date'] ?? '',
        ];
        
        update_post_meta($order->get_id(), '_ps_pix_data', $pix_data);
        
        // NOVO: Registrar transação confirmada no servidor central
        HNG_PagSeguro_Split::register_confirmed_transaction($order, $response);
        
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
        $settings = $this->get_settings();
        
        // NOVO: Calcular split payment via API
        $split_data = $this->calculate_and_prepare_split($order, 'credit_card');
        
        if (is_wp_error($split_data)) {
            $this->log('Split calculation error: ' . $split_data->get_error_message());
            // Continuar mesmo com erro (fallback para pagamento simples)
        }
        
        // Create charge in PagSeguro
        $payload = [
            'reference_id' => 'CHARGE_' . $order->get_id(),
            'customer' => [
                'name' => $payment_data['customer_name'] ?? '',
                'email' => $order->get_customer_email(),
                'tax_id' => preg_replace('/\D/', '', $payment_data['document'] ?? ''),
            ],
            'items' => $this->format_items($order),
            'charges' => [
                [
                    'reference_id' => 'CHARGE_' . $order->get_id(),
                    /* translators: %s: order ID */
                    'description' => sprintf(__('Pedido #%s', 'hng-commerce'), $order->get_id()),
                    'amount' => [
                        'value' => intval($order->get_total() * 100),
                        'currency' => 'BRL',
                    ],
                    'payment_method' => [
                        'type' => 'CREDIT_CARD',
                        'installments' => intval($payment_data['installments'] ?? 1),
                        'capture' => true,
                        'card' => [
                            'number' => preg_replace('/\D/', '', $payment_data['card_number'] ?? ''),
                            'exp_month' => $payment_data['expiry_month'] ?? '',
                            'exp_year' => $payment_data['expiry_year'] ?? '',
                            'security_code' => $payment_data['cvv'] ?? '',
                            'holder' => [
                                'name' => $payment_data['holder_name'] ?? '',
                            ],
                        ],
                    ],
                ]
            ],
            'notification_urls' => [
                home_url('/hng-webhook/pagseguro')
            ],
        ];
        
        // NOVO: Adicionar split_rules se disponível
        if (!is_wp_error($split_data) && !empty($split_data['split_rules'])) {
            $payload['split'] = [
                'rules' => $split_data['split_rules']
            ];
        }
        
        $response = $this->request('POST', '/orders', $payload);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $charge = $response['charges'][0] ?? [];
        $status = $charge['status'] ?? '';
        
        update_post_meta($order->get_id(), '_ps_order_id', $response['id']);
        update_post_meta($order->get_id(), '_ps_charge_id', $charge['id'] ?? '');
        update_post_meta($order->get_id(), '_ps_status', $status);
        
        // NOVO: Registrar transação confirmada no servidor central
        HNG_PagSeguro_Split::register_confirmed_transaction($order, $response);
        
        if ($status === 'PAID') {
            $order->update_status('processing');
            
            return [
                'success' => true,
                'payment_method' => 'credit_card',
                'status' => 'approved',
                'message' => __('Pagamento aprovado!', 'hng-commerce'),
                'redirect_url' => $order->get_order_received_url(),
            ];
        } elseif ($status === 'AUTHORIZED') {
            return [
                'success' => true,
                'payment_method' => 'credit_card',
                'status' => 'authorized',
                'message' => __('Pagamento autorizado.', 'hng-commerce'),
                'redirect_url' => $order->get_order_received_url(),
            ];
        } else {
            return [
                'success' => false,
                'payment_method' => 'credit_card',
                'status' => $status,
                'message' => __('Pagamento não aprovado.', 'hng-commerce'),
            ];
        }
    }
    
    /**
     * Process Boleto payment
     */
    private function process_boleto($order, $payment_data) {
        $settings = $this->get_settings();
        
        // NOVO: Calcular split payment via API
        $split_data = $this->calculate_and_prepare_split($order, 'boleto');
        
        if (is_wp_error($split_data)) {
            $this->log('Split calculation error: ' . $split_data->get_error_message());
            // Continuar mesmo com erro (fallback para pagamento simples)
        }
        
        // Calculate due date (3 days from now)
        $due_date = gmdate('Y-m-d', strtotime('+3 days'));
        
        $payload = [
            'reference_id' => 'CHARGE_' . $order->get_id(),
            'customer' => [
                'name' => $payment_data['customer_name'] ?? '',
                'email' => $order->get_customer_email(),
                'tax_id' => preg_replace('/\D/', '', $payment_data['document'] ?? ''),
            ],
            'items' => $this->format_items($order),
            'charges' => [
                [
                    'reference_id' => 'CHARGE_' . $order->get_id(),
                    /* translators: %s: order ID */
                    'description' => sprintf(__('Pedido #%s', 'hng-commerce'), $order->get_id()),
                    'amount' => [
                        'value' => intval($order->get_total() * 100),
                        'currency' => 'BRL',
                    ],
                    'payment_method' => [
                        'type' => 'BOLETO',
                        'boleto' => [
                            'due_date' => $due_date,
                            'instruction_lines' => [
                                'line_1' => __('Pagamento referente ao pedido na loja online.', 'hng-commerce'),
                                'line_2' => __('Não receber após o vencimento.', 'hng-commerce'),
                            ],
                            'holder' => [
                                'name' => $payment_data['customer_name'] ?? '',
                                'tax_id' => preg_replace('/\D/', '', $payment_data['document'] ?? ''),
                                'email' => $order->get_customer_email(),
                                'address' => [
                                    'country' => 'BRA',
                                    'region' => $payment_data['state'] ?? 'SP',
                                    'region_code' => $payment_data['state'] ?? 'SP',
                                    'city' => $payment_data['city'] ?? '',
                                    'postal_code' => preg_replace('/\D/', '', $payment_data['zip_code'] ?? ''),
                                    'street' => $payment_data['street'] ?? '',
                                    'number' => $payment_data['number'] ?? '',
                                    'locality' => $payment_data['neighborhood'] ?? '',
                                ],
                            ],
                        ],
                    ],
                ]
            ],
            'notification_urls' => [
                home_url('/hng-webhook/pagseguro')
            ],
        ];
        
        // NOVO: Adicionar split_rules se disponível
        if (!is_wp_error($split_data) && !empty($split_data['split_rules'])) {
            $payload['split'] = [
                'rules' => $split_data['split_rules']
            ];
        }
        
        $response = $this->request('POST', '/orders', $payload);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $charge = $response['charges'][0] ?? [];
        $boleto = $charge['payment_method']['boleto'] ?? [];
        
        $boleto_data = [
            'barcode' => $boleto['barcode'] ?? '',
            'formatted_barcode' => $boleto['formatted_barcode'] ?? '',
            'pdf_url' => $charge['links'][0]['href'] ?? '',
            'due_date' => $boleto['due_date'] ?? $due_date,
            'charge_id' => $charge['id'] ?? '',
        ];
        
        update_post_meta($order->get_id(), '_ps_order_id', $response['id']);
        update_post_meta($order->get_id(), '_ps_charge_id', $charge['id'] ?? '');
        update_post_meta($order->get_id(), '_ps_boleto_data', $boleto_data);
        
        // NOVO: Registrar transação confirmada no servidor central
        HNG_PagSeguro_Split::register_confirmed_transaction($order, $response);
        
        return [
            'success' => true,
            'payment_method' => 'boleto',
            'boleto_data' => $boleto_data,
            'redirect_url' => home_url('/pagamento/boleto?order_id=' . $order->get_id()),
        ];
    }
    
    /**
     * Format order items for PagSeguro
     */
    private function format_items($order) {
        $items = [];
        
        foreach ($order->get_items() as $item) {
            $items[] = [
                'reference_id' => 'ITEM_' . $item['product_id'],
                'name' => substr($item['name'], 0, 100),
                'quantity' => $item['quantity'],
                'unit_amount' => intval($item['price'] * 100),
            ];
        }
        
        return $items;
    }
    
    /**
     * Make API request
     */
    private function request($method, $endpoint, $data = []) {
        $settings = $this->get_settings();
        $token = $settings['token'];
        
        if (!$token) {
            return new WP_Error('no_token', __('Token não configurado.', 'hng-commerce'));
        }
        
        $url = $this->get_api_url() . $endpoint;
        
        $args = [
            'method' => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'x-api-version' => '4.0',
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
        $data = json_decode($body, true);
        
        $this->log('Response: ' . $code, $data);
        
        if ($code >= 400) {
            $error_message = $data['error_messages'][0]['description'] ?? __('Erro na API do PagSeguro', 'hng-commerce');
            return new WP_Error('api_error', $error_message, $data);
        }
        
        return $data;
    }
    
    /**
     * Handle webhook
     */
    public function handle_webhook($request) {
        $data = $request->get_json_params();
        
        $this->log('Webhook received', $data);
        
        // Get charge ID
        $charge_id = $data['charges'][0]['id'] ?? '';
        
        if (!$charge_id) {
            return;
        }
        
        // Get charge details
        $charge = $this->request('GET', '/charges/' . $charge_id);
        
        if (is_wp_error($charge)) {
            $this->log('Error fetching charge: ' . $charge->get_error_message());
            return;
        }
        
        // Find order
        $reference = $charge['reference_id'] ?? '';
        $order_id = str_replace('CHARGE_', '', $reference);
        
        if (!$order_id) {
            $this->log('No order ID in charge');
            return;
        }
        
        $order = new HNG_Order($order_id);
        
        // Update order based on charge status
        $status = $charge['status'] ?? '';
        
        switch ($status) {
            case 'PAID':
                $order->update_status('processing');
                $order->add_note(__('Pagamento confirmado no PagSeguro.', 'hng-commerce'));
                break;
            case 'WAITING':
            case 'IN_ANALYSIS':
                $order->update_status('pending');
                break;
            case 'DECLINED':
            case 'CANCELED':
                $order->update_status('failed');
                $order->add_note(__('Pagamento recusado/cancelado no PagSeguro.', 'hng-commerce'));
                break;
        }
        
        update_post_meta($order_id, '_ps_status', $status);
    }
    
    /** AJAX handlers */
    public function ajax_create_payment() {
        // Nonce validation (specific nonce or legacy generic for backward compatibility)
        $nonce = isset($_REQUEST['nonce']) ? wp_unslash($_REQUEST['nonce']) : '';
        $nonce_verified = wp_verify_nonce($nonce, 'hng_ps_create_payment') || 
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
            $payment_data['card_number'] = isset($raw['number']) ? preg_replace('/[^0-9]/', '', $raw['number']) : '';
            $payment_data['holder_name'] = isset($raw['holder_name']) ? sanitize_text_field($raw['holder_name']) : '';
            $payment_data['expiry_month'] = isset($raw['expiry_month']) ? preg_replace('/[^0-9]/', '', $raw['expiry_month']) : '';
            $payment_data['expiry_year'] = isset($raw['expiry_year']) ? preg_replace('/[^0-9]/', '', $raw['expiry_year']) : '';
            $payment_data['cvv'] = isset($raw['cvv']) ? preg_replace('/[^0-9]/', '', $raw['cvv']) : '';
        }

        $result = $this->process_payment($order_id, $payment_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success($result);
    }
    
    public function ajax_check_payment() {
        // Nonce validation (specific nonce or legacy generic for backward compatibility)
        $nonce = isset($_REQUEST['nonce']) ? wp_unslash($_REQUEST['nonce']) : '';
        $nonce_verified = wp_verify_nonce($nonce, 'hng_ps_check_payment') || 
                         wp_verify_nonce($nonce, 'HNG Commerce');
        if (!$nonce_verified) {
            wp_send_json_error(['message' => __('Verificação de segurança falhou.', 'hng-commerce')]);
        }
        
        $post = function_exists('wp_unslash') ? wp_unslash( $_POST ) : $_POST;
        $order_id = absint( $post['order_id'] ?? 0 );
        
        if (!$order_id) {
            wp_send_json_error(['message' => __('Pedido inválido.', 'hng-commerce')]);
        }
        
        $ps_order_id = get_post_meta($order_id, '_ps_order_id', true);
        
        if (!$ps_order_id) {
            wp_send_json_error(['message' => __('Pagamento não encontrado.', 'hng-commerce')]);
        }
        
        $order_data = $this->request('GET', '/orders/' . $ps_order_id);
        
        if (is_wp_error($order_data)) {
            wp_send_json_error(['message' => $order_data->get_error_message()]);
        }
        
        wp_send_json_success([
            'status' => $order_data['charges'][0]['status'] ?? '',
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
    
    /**
     * NOVO: Calcular e preparar dados de split payment
     * Wrapper para HNG_PagSeguro_Split::calculate_split_rules
     * 
     * @param WC_Order $order
     * @param string $payment_method
     * @return array|WP_Error
     */
    private function calculate_and_prepare_split($order, $payment_method) {
        // Verificar se classe de split existe
        if (!class_exists('HNG_PagSeguro_Split')) {
            return new WP_Error('split_class_missing', 'Classe de split payment não carregada');
        }
        
        return HNG_PagSeguro_Split::calculate_split_rules($order, $payment_method);
    }
}

// Initialize gateway
new HNG_Gateway_PagSeguro();

// Handler de renovação manual de assinatura (PagSeguro)
add_action('hng_subscription_manual_renewal', function($subscription_id, $order_id, $payment_method) {
    try {
        $subscription = new HNG_Subscription($subscription_id);
        if ($subscription->get_gateway() !== 'pagseguro') return;

        $class = 'HNG_Gateway_PagSeguro';
        if (!class_exists($class)) {
            $path = HNG_COMMERCE_PATH . 'gateways/pagseguro/class-gateway-pagseguro.php';
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
                hng_files_log_append(HNG_COMMERCE_PATH . 'logs/gateways-pagseguro.log', '[PagSeguro Renovaá§á¡o] ' . $result->get_error_message() . PHP_EOL);
            }
            return;
        }
        if (is_array($result)) update_post_meta($order_id, '_payment_data', $result);

        $candidates = ['redirect_url','payment_url','paymentUrl','pix_data','qr_code','boleto_url','ticket_url'];
        $payment_url = '';
        if (is_array($result)) { foreach ($candidates as $k) { if (!empty($result[$k])) { $payment_url = $result[$k]; break; } } }
        if (!empty($payment_url)) update_post_meta($order_id, '_payment_url', $payment_url);

    } catch (Exception $e) {
        if (function_exists('hng_files_log_append')) {
            hng_files_log_append(HNG_COMMERCE_PATH . 'logs/gateways-pagseguro.log', '[PagSeguro Renovação] Exception: ' . $e->getMessage() . PHP_EOL);
        }
    }
}, 10, 3);
