<?php
/**
 * Gateway Cielo
 * 
 * @package HNG_Commerce
 * @since 1.0.6
 */

if (!defined('ABSPATH')) {
    exit;
}

// Handler de renovação manual de assinatura (gera pagamento para Cielo)
add_action('hng_subscription_manual_renewal', function($subscription_id, $order_id, $payment_method) {
    try {
        $subscription = new HNG_Subscription($subscription_id);
        if ($subscription->get_gateway() !== 'cielo') return;

        $class = 'HNG_Gateway_Cielo';
        if (!class_exists($class)) {
            $path = HNG_COMMERCE_PATH . 'gateways/cielo/class-gateway-cielo.php';
            if (file_exists($path)) require_once $path;
        }
        if (!class_exists($class)) return;

        $gw = method_exists($class, 'instance') ? $class::instance() : new $class();
        if (!$gw->is_configured()) return;

        $order = new HNG_Order($order_id);
        $customer_email = sanitize_email($order->get_customer_email());
        $amount = floatval($subscription->get_amount());

        $payment_data = [
            'order_id' => $order_id,
            'amount' => $amount,
            'customer_email' => $customer_email,
            'description' => sprintf('Renovação Assinatura #%d', $subscription_id),
        ];

        if ($payment_method === 'pix' && method_exists($gw, 'create_pix_payment')) {
            $result = $gw->create_pix_payment($order_id, $payment_data);
        } elseif ($payment_method === 'boleto' && method_exists($gw, 'create_boleto_payment')) {
            $result = $gw->create_boleto_payment($order_id, $payment_data);
        } else {
            $result = $gw->process_payment($order_id, $payment_data);
        }

        if (is_wp_error($result)) {
            if (function_exists('hng_files_log_append')) {
                hng_files_log_append(HNG_COMMERCE_PATH . 'logs/gateway-cielo.log', sprintf('[Cielo Renovação] Erro ao gerar pagamento para assinatura %d pedido %d: %s', $subscription_id, $order_id, $result->get_error_message()) . PHP_EOL);
            }
            return;
        }

        if (is_array($result)) update_post_meta($order_id, '_payment_data', $result);

        $candidates = ['payment_url','paymentUrl','redirect_url','checkout_url','boleto_url','bankSlipUrl','ticket_url','qr_code_url'];
        $payment_url = '';
        if (is_array($result)) {
            foreach ($candidates as $k) {
                if (!empty($result[$k])) { $payment_url = $result[$k]; break; }
            }
        }

        if (!empty($payment_url)) update_post_meta($order_id, '_payment_url', $payment_url);

    } catch (Exception $e) {
        if (function_exists('hng_files_log_append')) { hng_files_log_append(HNG_COMMERCE_PATH . 'logs/gateway-cielo.log', '[Cielo Renovação] Exception: ' . $e->getMessage() . PHP_EOL); }
    }
}, 10, 3);

class HNG_Gateway_Cielo extends HNG_Payment_Gateway {
    
    public $id = 'cielo';
    protected $name = 'Cielo';
    public $description = 'Cielo - Líder em cartões no Brasil';
    
    private $api_url_production = 'https://api.cieloecommerce.cielo.com.br';
    private $api_url_sandbox = 'https://apisandbox.cieloecommerce.cielo.com.br';
    
    private $query_url_production = 'https://apiquery.cieloecommerce.cielo.com.br';
    private $query_url_sandbox = 'https://apiquerysandbox.cieloecommerce.cielo.com.br';
    
    /**
     * Construtor
     */
    public function __construct() {
        parent::__construct();
        
        $this->supports = ['pix', 'credit_card', 'debit_card', 'refunds', 'webhooks'];
        
        // AJAX endpoints
        add_action('wp_ajax_hng_cielo_create_payment', [$this, 'ajax_create_payment']);
        add_action('wp_ajax_nopriv_hng_cielo_create_payment', [$this, 'ajax_create_payment']);
    }
    
    /**
     * Retorna capabilities do gateway
     */
    public function get_capabilities() {
        return [
            'pix' => [
                'enabled' => true,
                'qr_code' => true,
                'expiration' => true,
                'refund' => true,
                'max_amount' => null
            ],
            'credit_card' => [
                'enabled' => true,
                'installments' => 12,
                'tokenization' => true,
                'max_amount' => null,
                'brands' => ['visa', 'mastercard', 'elo', 'amex', 'diners', 'discover', 'jcb', 'aura', 'hipercard']
            ],
            'debit_card' => [
                'enabled' => true,
                'brands' => ['visa', 'mastercard', 'elo'],
                'max_amount' => null
            ],
            'boleto' => [
                'enabled' => false
            ],
            'split' => true,
            'webhook' => true,
            'refund' => [
                'partial' => true,
                'full' => true
            ],
            '3ds' => true // 3D Secure
        ];
    }
    
    /**
     * Criar pagamento PIX
     */
    public function create_pix_payment($order_id, $payment_data) {
        $order = new HNG_Order($order_id);
        $amount = $order->get_total();
        
        // Calcular taxas
        if (!class_exists('HNG_Fee_Calculator')) {
            return new WP_Error('fee_calculator_missing', 'Fee calculator não disponível');
        }
        
        $fee_calc = new HNG_Fee_Calculator();
        $fee_result = $fee_calc->calculate($amount, 'pix', $this->id);
        
        // Montar payload PIX Cielo
        $payload = [
            'MerchantOrderId' => 'ORDER_' . $order_id,
            'Customer' => [
                'Name' => $order->get_customer_name(),
                'Identity' => preg_replace('/[^0-9]/', '', $order->get_meta('_billing_cpf')),
                'IdentityType' => 'CPF'
            ],
            'Payment' => [
                'Type' => 'Pix',
                'Amount' => (int) ($amount * 100), // centavos
                'QrCodeExpiration' => 3600, // 1 hora
                'ModificationAllowed' => false
            ]
        ];
        
        $response = $this->api_request('/1/sales', 'POST', $payload);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $payment = $response['Payment'] ?? null;
        if (!$payment || !isset($payment['QrCodeString'])) {
            return new WP_Error('pix_creation_failed', 'Dados PIX não retornados');
        }
        
        // Salvar no ledger
        if (class_exists('HNG_Ledger')) {
            HNG_Ledger::add_entry([
                'order_id' => $order_id,
                'type' => 'charge',
                'method' => 'pix',
                'status' => 'pending',
                'external_ref' => $payment['PaymentId'],
                'gross_amount' => $amount,
                'fee_amount' => $fee_result['total'],
                'net_amount' => $fee_result['net'],
                'gateway' => $this->id,
                'meta' => [
                    'payment_id' => $payment['PaymentId'],
                    'qr_code_string' => $payment['QrCodeString']
                ]
            ]);
        }
        
        return [
            'id' => $payment['PaymentId'],
            'encodedImage' => $payment['QrCodeBase64Image'] ?? '',
            'payload' => $payment['QrCodeString'],
            'expirationDate' => gmdate('Y-m-d\TH:i:s\Z', time() + 3600),
            'fee_total' => $fee_result['total'],
            'net_amount' => $fee_result['net']
        ];
    }
    
    /**
     * Criar pagamento com cartão de crédito
     */
    public function create_credit_card_payment($order_id, $card_data) {
        $order = new HNG_Order($order_id);
        $amount = $order->get_total();
        
        // Calcular taxas
        $fee_calc = new HNG_Fee_Calculator();
        $fee_result = $fee_calc->calculate($amount, 'credit_card', $this->id);
        
        $installments = isset($card_data['installments']) ? absint($card_data['installments']) : 1;

        // Sanitizar dados do cartá¡o (aceitar apenas caracteres esperados)
        $card_number = isset($card_data['number']) ? preg_replace('/[^0-9]/', '', $card_data['number']) : '';
        $holder_name = isset($card_data['holder_name']) ? sanitize_text_field($card_data['holder_name']) : '';
        $expiry_month = isset($card_data['expiry_month']) ? preg_replace('/[^0-9]/', '', $card_data['expiry_month']) : '';
        $expiry_year = isset($card_data['expiry_year']) ? preg_replace('/[^0-9]/', '', $card_data['expiry_year']) : '';
        $cvv = isset($card_data['cvv']) ? preg_replace('/[^0-9]/', '', $card_data['cvv']) : '';

        $payload = [
            'MerchantOrderId' => 'ORDER_' . $order_id,
            'Customer' => [
                'Name' => $order->get_customer_name(),
                'Email' => $order->get_customer_email(),
                'Identity' => preg_replace('/[^0-9]/', '', $order->get_meta('_billing_cpf')),
                'IdentityType' => 'CPF'
            ],
            'Payment' => [
                'Type' => 'CreditCard',
                'Amount' => (int) ($amount * 100),
                'Installments' => $installments,
                'SoftDescriptor' => substr(get_bloginfo('name'), 0, 13),
                'Capture' => true,
                'CreditCard' => [
                    'CardNumber' => $card_number,
                    'Holder' => $holder_name,
                    'ExpirationDate' => $expiry_month . '/' . $expiry_year,
                    'SecurityCode' => $cvv,
                    'Brand' => $this->detect_card_brand($card_number)
                ]
            ]
        ];
        
        $response = $this->api_request('/1/sales', 'POST', $payload);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $payment = $response['Payment'] ?? null;
        
        // Salvar no ledger
        if (class_exists('HNG_Ledger')) {
            HNG_Ledger::add_entry([
                'order_id' => $order_id,
                'type' => 'charge',
                'method' => 'credit_card',
                'status' => ($payment['Status'] == 2) ? 'confirmed' : 'pending',
                'external_ref' => $payment['PaymentId'],
                'gross_amount' => $amount,
                'fee_amount' => $fee_result['total'],
                'net_amount' => $fee_result['net'],
                'gateway' => $this->id
            ]);
        }
        
        return [
            'id' => $payment['PaymentId'],
            'status' => $this->normalize_status($payment['Status']),
            'tid' => $payment['Tid'] ?? '',
            'authorization_code' => $payment['AuthorizationCode'] ?? '',
            'fee_total' => $fee_result['total'],
            'net_amount' => $fee_result['net']
        ];
    }
    
    /**
     * Detectar bandeira do cartão
     */
    private function detect_card_brand($number) {
        $number = preg_replace('/[^0-9]/', '', $number);
        $first_digit = substr($number, 0, 1);
        $first_two = substr($number, 0, 2);
        $first_four = substr($number, 0, 4);
        
        if ($first_digit == '4') return 'Visa';
        if (in_array($first_two, ['51', '52', '53', '54', '55'])) return 'Master';
        if ($first_four == '6011' || $first_two == '65') return 'Discover';
        if (in_array($first_two, ['34', '37'])) return 'Amex';
        if (in_array($first_two, ['36', '38'])) return 'Diners';
        if ($first_four == '5067' || $first_four == '4576') return 'Elo';
        if ($first_four == '6062') return 'Hipercard';
        
        return 'Visa'; // fallback
    }
    
    /**
     * Consultar status do PIX
     */
    public function get_pix_status($charge_id) {
        $response = $this->api_request('/1/sales/' . $charge_id, 'GET', null, true);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $payment = $response['Payment'] ?? null;
        
        return [
            'status' => $this->normalize_status($payment['Status'] ?? 0),
            'raw' => $response
        ];
    }
    
    /**
     * Cancelar pagamento
     */
    public function cancel_pix($charge_id) {
        return $this->api_request('/1/sales/' . $charge_id . '/void', 'PUT', []);
    }
    
    /**
     * Reembolsar
     */
    public function refund_pix($charge_id, $amount = null) {
        $payload = [];
        if ($amount) {
            $payload['Amount'] = (int) ($amount * 100);
        }
        
        return $this->api_request('/1/sales/' . $charge_id . '/void', 'PUT', $payload);
    }
    
    /**
     * Normalizar status
     */
    private function normalize_status($status) {
        $map = [
            0 => 'PENDING',      // NotFinished
            1 => 'PENDING',      // Authorized
            2 => 'CONFIRMED',    // PaymentConfirmed
            3 => 'OVERDUE',      // Denied
            10 => 'OVERDUE',     // Voided
            11 => 'REFUNDED',    // Refunded
            12 => 'PENDING',     // Pending
            13 => 'OVERDUE'      // Aborted
        ];
        
        return $map[$status] ?? 'PENDING';
    }
    
    /**
     * Requisição à API
     */
    protected function api_request($endpoint, $method = 'GET', $body = null, $is_query = false) {
        $merchant_id = get_option('hng_cielo_merchant_id', '');
        $merchant_key = get_option('hng_cielo_merchant_key', '');
        $environment = get_option('hng_cielo_environment', 'sandbox');
        
        if (empty($merchant_id) || empty($merchant_key)) {
            return new WP_Error('cielo_not_configured', 'Cielo não configurada');
        }
        
        $base_url = $is_query 
            ? (($environment === 'production') ? $this->query_url_production : $this->query_url_sandbox)
            : (($environment === 'production') ? $this->api_url_production : $this->api_url_sandbox);
        
        $url = $base_url . $endpoint;
        
        $args = [
            'method' => $method,
            'headers' => [
                'Content-Type' => 'application/json',
                'MerchantId' => $merchant_id,
                'MerchantKey' => $merchant_key,
                'RequestId' => wp_generate_uuid4()
            ],
            'timeout' => 30
        ];
        
        if ($body && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $args['body'] = wp_json_encode($body);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body_response = wp_remote_retrieve_body($response);
        $data = json_decode($body_response, true);
        
        if ($code < 200 || $code >= 300) {
            $error_message = $data[0]['Message'] ?? $data['Message'] ?? 'Erro desconhecido';
            return new WP_Error('cielo_api_error', $error_message, ['code' => $code, 'data' => $data]);
        }
        
        return $data;
    }
    
    /**
     * AJAX: Criar pagamento
     */
    public function ajax_create_payment() {
        // Nonce validation (specific nonce or legacy for backward compatibility)
        $nonce = isset($_REQUEST['nonce']) ? wp_unslash($_REQUEST['nonce']) : '';
        $nonce_verified = wp_verify_nonce($nonce, 'hng_cielo_create_payment') || 
                         wp_verify_nonce($nonce, 'hng-checkout');
        if (!$nonce_verified) {
            wp_send_json_error(['message' => __('Verificação de segurança falhou.', 'hng-commerce')]);
        }

        $post = function_exists('wp_unslash') ? wp_unslash($_POST) : $_POST;

        $order_id = isset($post['order_id']) ? absint($post['order_id']) : 0;
        $payment_method = isset($post['payment_method']) ? sanitize_text_field($post['payment_method']) : '';
        
        if (!$order_id || !$payment_method) {
            wp_send_json_error(['message' => 'Dados inválidos']);
        }
        
        $result = null;
        
        switch ($payment_method) {
            case 'pix':
                // Sanitizar campos relevantes para PIX
                $pix_data = [];
                if (is_array($post)) {
                    $pix_data['customer_email'] = isset($post['customer_email']) ? sanitize_email($post['customer_email']) : '';
                    $pix_data['description'] = isset($post['description']) ? sanitize_text_field($post['description']) : '';
                    $pix_data['amount'] = isset($post['amount']) ? floatval($post['amount']) : 0;
                }

                $result = $this->create_pix_payment($order_id, $pix_data);
                break;
            case 'credit_card':
                // Sanitizar campos de cartão antes de enviar
                $card_data = [];
                if (is_array($post)) {
                    $card_data['installments'] = isset($post['installments']) ? absint($post['installments']) : 1;
                    $card_data['number'] = isset($post['number']) ? preg_replace('/[^0-9]/', '', $post['number']) : '';
                    $card_data['holder_name'] = isset($post['holder_name']) ? sanitize_text_field($post['holder_name']) : '';
                    $card_data['expiry_month'] = isset($post['expiry_month']) ? preg_replace('/[^0-9]/', '', $post['expiry_month']) : '';
                    $card_data['expiry_year'] = isset($post['expiry_year']) ? preg_replace('/[^0-9]/', '', $post['expiry_year']) : '';
                    $card_data['cvv'] = isset($post['cvv']) ? preg_replace('/[^0-9]/', '', $post['cvv']) : '';
                }

                $result = $this->create_credit_card_payment($order_id, $card_data);
                break;
        }
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success($result);
    }
}
