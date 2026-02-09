<?php
/**
 * Gateway de Pagamento Asaas
 *
 * Integração completa com Asaas para PIX, Boleto e Cartão de Crédito
 *
 * @package HNG_Commerce
 * @since 1.0.0
 * @link https://docs.asaas.com/
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Gateway_Asaas extends HNG_Payment_Gateway {
    
    /**

     * ID do gateway

     */

    public $id = 'asaas';

    

    /**

     * Nome do gateway

     */

    public $title = 'Asaas';

    

    /**

     * Descrição

     */

    public $description = 'Aceite pagamentos via PIX, Boleto e Cartão de Crédito';

    

    /**

     * API Key

     */

    public $api_key = '';

    

    /**

     * Ambiente (sandbox ou production)

     */

    public $environment = 'sandbox';

    


    /**

     * URLs da API

     */

    protected $api_urls = [

        'sandbox' => 'https://sandbox.asaas.com/api/v3',

        'production' => 'https://api.asaas.com/v3',

    ];

    

    /**

     * Métodos de pagamento suportados

     */

    public $supported_methods = ['pix', 'boleto', 'credit_card'];



    /**

     * Capacidades padronizadas deste provider (usada pelo Capabilities Provider)

     */

    public static function get_capabilities() {

        return [

            'provider' => 'asaas',

            'version' => '1.0',

            'capabilities' => [

                'pix' => [

                    'supported' => true,

                    'dynamic_qr' => true,

                    'expiration_control' => true,

                    'status_map' => [ 'PENDING' => 'created', 'RECEIVED' => 'paid', 'CONFIRMED' => 'paid', 'OVERDUE' => 'expired', 'REFUNDED' => 'refunded' ]

                ],

                'boleto' => [

                    'supported' => true,

                    'registration' => true,

                    'automatic_baixa' => true

                ],

                'cartao' => [

                    'supported' => true,

                    '3ds' => true,

                    'antifraude' => 'basico',

                    'installments' => true

                ],

                'split' => [

                    'native' => true,

                    'mode' => 'wallet'

                ],

                'webhook' => [

                    'hmac' => false, /* Asaas usa access_token + callback, sem HMAC */

                    'idempotency' => true,

                    'retry' => true

                ],

                'refund' => [

                    'partial' => true,

                    'pix' => true,

                    'cartao' => true,

                    'boleto' => true

                ],

                'settlement' => [

                    'pix' => 'D+1',

                    'boleto' => 'D+1..D+3',

                    'cartao' => 'D+28'

                ]

            ]

        ];

    }

    

    /**

     * Construtor

     */

    public function __construct() {

        parent::__construct();

        

        // Carregar configurações

        $this->api_key = get_option('hng_asaas_api_key', '');

        $this->environment = get_option('hng_asaas_environment', 'sandbox');

        // Verificar ambos os nomes de opção para compatibilidade

        $this->enabled = get_option('hng_gateway_asaas_enabled', 'no') === 'yes' || 

                         get_option('hng_asaas_enabled', 'no') === 'yes';


        // Hooks

        add_action('wp_ajax_hng_check_payment_status', [$this, 'ajax_check_payment_status']);

        add_action('wp_ajax_nopriv_hng_check_payment_status', [$this, 'ajax_check_payment_status']);

    }



    /**

     * Integração avançada habilitada?

     */

    private function is_advanced_enabled() {

        return get_option('hng_asaas_advanced_integration', 'no') === 'yes';

    }

    

    /**

     * Verificar se o gateway está configurado

     */

    public function is_configured() {

        return !empty($this->api_key);

    }

    

    /**

     * Obter URL da API

     */

    protected function get_api_url() {

        return $this->api_urls[$this->environment];

    }

    

    /**

     * Fazer requisição à API

     * 

     * @param string $endpoint

     * @param array $data

     * @param string $method GET, POST, PUT, DELETE

     * @return array|WP_Error

     */

    protected function make_request($endpoint, $data = [], $method = 'POST') {

        $url = $this->get_api_url() . '/' . ltrim($endpoint, '/');

        

        $args = [

            'method' => $method,

            'headers' => [

                'access_token' => $this->api_key,

                'Content-Type' => 'application/json',

            ],

            'timeout' => 30,

        ];

        

        if (!empty($data) && in_array($method, ['POST', 'PUT'])) {

            $args['body'] = wp_json_encode($data);

        }

        

        // Log da requisição

        $this->log('REQUEST', [

            'method' => $method,

            'url' => $url,

            'data' => $data,

        ]);

        

        $response = wp_remote_request($url, $args);

        

        if (is_wp_error($response)) {

            $this->log('ERROR', $response->get_error_message());

            return $response;

        }

        

        $body = wp_remote_retrieve_body($response);

        $code = wp_remote_retrieve_response_code($response);

        $decoded = json_decode($body, true);

        

        // Log da resposta

        $this->log('RESPONSE', [

            'code' => $code,

            'body' => $decoded,

        ]);

        

        if ($code >= 400) {

            $error_message = isset($decoded['errors'][0]['description']) 

                ? $decoded['errors'][0]['description'] 

                : 'Erro ao processar pagamento';

            

            return new WP_Error('asaas_error', $error_message, $decoded);

        }

        

        return $decoded;

    }

    

    /**

     * Validar transação com API central ANTES de criar cobrança

     * 

     * @param int $order_id

     * @param float $amount

     * @param float $expected_fee

     * @param string $payment_method

     * @return array|WP_Error Array com wallet_id e auth_token, ou WP_Error se não autorizado

     */

    protected function validate_transaction_with_api($order_id, $amount, $expected_fee, $payment_method = 'pix') {

        if (!class_exists('HNG_Payment_Orchestrator')) {

            require_once HNG_COMMERCE_PATH . 'includes/class-hng-payment-orchestrator.php';

        }


        if (!class_exists('HNG_Payment_Orchestrator')) {

            return new WP_Error('api_client_missing', 'Sistema de validação indisponível');

        }


        $merchant_id = get_option('hng_merchant_id', '');

        $validation = HNG_Payment_Orchestrator::validate_transaction(

            $amount,

            $merchant_id,

            'asaas',

            $payment_method

        );


        if (is_wp_error($validation)) {

            return $validation;

        }


        return [

            'authorized' => true,

            'auth_token' => $validation['auth_token'] ?? '',

            'wallet_id' => $validation['wallet_id'] ?? ''

        ];
    }

    

    /**

     * Criar cliente no Asaas

     * 

     * @param array $customer_data

     * @return array|WP_Error

     */

    public function create_customer($customer_data) {

        // Verificar se cliente já existe

        $existing = $this->get_customer_by_cpf_cnpj($customer_data['cpfCnpj']);

        

        if (!is_wp_error($existing) && isset($existing['id'])) {

            return $existing;

        }

        

        // Criar novo cliente

        $data = [

            'name' => sanitize_text_field($customer_data['name']),

            'email' => sanitize_email($customer_data['email']),

            'cpfCnpj' => preg_replace('/[^0-9]/', '', $customer_data['cpfCnpj']),

            'mobilePhone' => preg_replace('/[^0-9]/', '', $customer_data['phone'] ?? ''),

            'postalCode' => preg_replace('/[^0-9]/', '', $customer_data['postalCode'] ?? ''),

            'address' => sanitize_text_field($customer_data['address'] ?? ''),

            'addressNumber' => sanitize_text_field($customer_data['addressNumber'] ?? ''),

            'complement' => sanitize_text_field($customer_data['complement'] ?? ''),

            'province' => sanitize_text_field($customer_data['province'] ?? ''),

            'notificationDisabled' => false,

        ];

        

        return $this->make_request('/customers', $data, 'POST');

    }



    /**

     * Criar assinatura no Asaas (recorrência mensal por padrão)

     * Requer integração avançada ativa.

     *

     * @param string $customer_id

     * @param array $data ['amount'=>float,'next_due_date'=>Y-m-d,'cycle'=>'MONTHLY']

     * @return array|WP_Error

     */

    public function create_subscription($customer_id, $data = []) {

        if (!$this->is_advanced_enabled()) {

            return new WP_Error('hng_asaas_adv_off', __('Integração avançada desativada para Asaas.', 'hng-commerce'));

        }

        if (empty($customer_id)) {

            return new WP_Error('asaas_no_customer', __('Cliente inválido para criar assinatura.', 'hng-commerce'));

        }

        $amount = isset($data['amount']) ? (float) $data['amount'] : 0.0;

        if ($amount <= 0) {

            return new WP_Error('asaas_invalid_amount', __('Valor da assinatura inválido.', 'hng-commerce'));

        }

        $payload = [

            'customer' => $customer_id,

            'value' => $amount,

            'cycle' => strtoupper($data['cycle'] ?? 'MONTHLY'),

            'description' => $data['description'] ?? __('Assinatura HNG Commerce', 'hng-commerce'),

            'nextDueDate' => isset($data['next_due_date']) ? $data['next_due_date'] : gmdate('Y-m-d', strtotime('+1 month')),

        ];

        return $this->make_request('/subscriptions', $payload, 'POST');

    }

    

    /**

     * Buscar cliente por CPF/CNPJ

     * 

     * @param string $cpf_cnpj

     * @return array|WP_Error

     */

    protected function get_customer_by_cpf_cnpj($cpf_cnpj) {

        $cpf_cnpj = preg_replace('/[^0-9]/', '', $cpf_cnpj);

        $response = $this->make_request('/customers?cpfCnpj=' . $cpf_cnpj, [], 'GET');

        

        if (is_wp_error($response)) {

            return $response;

        }

        

        if (isset($response['data'][0])) {

            return $response['data'][0];

        }

        

        return new WP_Error('customer_not_found', 'Cliente não encontrado');

    }

    

    /**

     * Criar cobrança PIX

     * 

     * @param int $order_id

     * @param array $payment_data

     * @return array|WP_Error

     */

    public function create_pix_payment($order_id, $payment_data) {

        $order = new HNG_Order($order_id);

        

        // Log para debug

        error_log('HNG Asaas PIX: Iniciando criação de pagamento PIX para pedido #' . $order_id);

        error_log('HNG Asaas PIX: Post ID do pedido: ' . $order->get_post_id());

        error_log('HNG Asaas PIX: Total do pedido: ' . $order->get_total());

        

        // Verificar se o order foi carregado corretamente

        if (!$order->get_id()) {

            error_log('HNG Asaas PIX: ERRO - Pedido não encontrado: ' . $order_id);

            return new WP_Error('order_not_found', __('Pedido não encontrado.', 'hng-commerce'));

        }

        

        // Criar ou obter cliente

        $customer = $this->create_customer([

            'name' => $order->get_customer_name(),

            'email' => $order->get_customer_email(),

            'cpfCnpj' => $payment_data['cpf'],

            'phone' => $order->get_billing_phone(),

            'postalCode' => $order->get_billing_postcode(),

            'address' => $order->get_billing_address(),

            'addressNumber' => $order->get_billing_number(),

            'complement' => $order->get_billing_complement(),

            'province' => $order->get_billing_neighborhood(),

        ]);

        

        if (is_wp_error($customer)) {

            return $customer;

        }

        

        $customer_id = $customer['id'];

        

        // Calcular split (taxa do plugin)

        $plugin_fee_amount = 0;

        if (class_exists('HNG_Fee_Calculator')) {

            $calc = HNG_Fee_Calculator::instance();

            $fee_data = $calc->calculate_all_fees($order->get_total(), 'physical', $this->id, 'pix');

            $plugin_fee_amount = $fee_data['plugin_fee'];

        }

        

        // VALIDAR COM API ANTES DE CRIAR COBRANÇA

        $validation = $this->validate_transaction_with_api(

            $order_id,

            $order->get_total(),

            $plugin_fee_amount,

            'pix'

        );

        

        if (is_wp_error($validation)) {

            // Log do erro

            $this->log('VALIDATION_FAILED', [

                'order_id' => $order_id,

                'error' => $validation->get_error_message()

            ]);

            

            // Adicionar nota ao pedido para admin

            $order->add_note(

                '?? Erro ao processar pagamento: ' . $validation->get_error_message() . 

                ' | Entre em contato com o suporte HNG Commerce.'

            );

            

            return $validation;

        }

        

        // Usar wallet_id retornado pela API (garantia de que é o correto)

        $api_wallet_id = $validation['wallet_id'];

        

        // Criar cobrança PIX

        $charge_data = [

            'customer' => $customer_id,

            'billingType' => 'PIX',

            'value' => $order->get_total(),

            'dueDate' => gmdate('Y-m-d', strtotime('+1 day')),

            'description' => sprintf('Pedido #%s - %s', $order->get_order_number(), get_bloginfo('name')),

            'externalReference' => (string) $order_id,

        ];

        

        if (!empty($api_wallet_id) && $plugin_fee_amount > 0) {
            $charge_data['split'] = [
                [
                    'walletId' => $api_wallet_id,
                    'fixedValue' => $plugin_fee_amount,
                    'description' => 'Taxa HNG Commerce Plugin'
                ]
            ];
        }

        

        $charge = $this->make_request('/payments', $charge_data, 'POST');

        

        if (is_wp_error($charge)) {

            error_log('HNG Asaas PIX: ERRO ao criar cobrança - ' . $charge->get_error_message());

            return $charge;

        }

        

        error_log('HNG Asaas PIX: Cobrança criada: ' . $charge['id']);

        

        // Salvar dados da cobrança no pedido

        $post_id = $order->get_post_id();

        error_log('HNG Asaas PIX: Salvando meta no post_id: ' . $post_id);

        

        if ($post_id > 0) {

            update_post_meta($post_id, '_asaas_payment_id', $charge['id']);

            update_post_meta($post_id, '_asaas_customer_id', $customer_id);

            update_post_meta($post_id, '_payment_method', 'pix');

            error_log('HNG Asaas PIX: Metas salvos com sucesso');

        } else {

            error_log('HNG Asaas PIX: ERRO - post_id inválido (0)');

        }

        

        // Obter QR Code PIX

        $qrcode = $this->get_pix_qrcode($charge['id']);

        

        if (!is_wp_error($qrcode)) {

            $charge['pixQrCode'] = $qrcode;

            error_log('HNG Asaas PIX: QR Code obtido com sucesso');

        } else {

            error_log('HNG Asaas PIX: ERRO ao obter QR Code - ' . $qrcode->get_error_message());

        }



        // Taxas + ledger (PIX)

        if (class_exists('HNG_Fee_Calculator') && class_exists('HNG_Ledger')) {

            $calc = HNG_Fee_Calculator::instance();

            $fee_data = $calc->calculate_all_fees($order->get_total(), 'physical', $this->id, 'pix');

            update_post_meta($order->get_post_id(), '_hng_fee_data', $fee_data);

            HNG_Ledger::add_entry([

                'type' => 'charge',

                'order_id' => $order_id,

                'external_ref' => $charge['id'],

                'gross_amount' => $fee_data['gross_amount'],

                'fee_amount' => $fee_data['plugin_fee'] + $fee_data['gateway_fee'],

                'net_amount' => $fee_data['net_amount'],

                'status' => 'pending',

                'meta' => [

                    'gateway' => $this->id,

                    'method' => 'pix',

                    'plugin_fee' => $fee_data['plugin_fee'],

                    'gateway_fee' => $fee_data['gateway_fee'],

                    'tier' => $fee_data['tier']

                ]

            ]);

        }

        

        return $charge;

    }



    /**

     * Obter status da cobrança PIX

     * @param string $charge_id

     * @return array|WP_Error

     */

    public function get_pix_status($charge_id) {

        if (empty($charge_id)) {

            return new WP_Error('pix_invalid_id', 'ID da cobrança vazio');

        }

        $resp = $this->make_request('/payments/' . urlencode($charge_id), [], 'GET');

        if (is_wp_error($resp)) { return $resp; }

        return [ 'status' => $resp['status'] ?? 'UNKNOWN', 'raw' => $resp ];

    }



    /**

     * Cancelar cobrança PIX (DELETE /payments/{id})

     */

    public function cancel_pix($charge_id) {

        if (empty($charge_id)) { return new WP_Error('pix_invalid_id', 'ID inválido'); }

        $resp = $this->make_request('/payments/' . urlencode($charge_id), [], 'DELETE');

        if (is_wp_error($resp)) { return $resp; }

        return $resp;

    }



    /**

     * Reembolso PIX total ou parcial

     */

    public function refund_pix($charge_id, $amount = null) {

        if (empty($charge_id)) { return new WP_Error('pix_invalid_id', 'ID inválido'); }

        $payload = [];

        if (!is_null($amount)) { $payload['value'] = (float) $amount; }

        $resp = $this->make_request('/payments/' . urlencode($charge_id) . '/refund', $payload, 'POST');

        if (is_wp_error($resp)) { return $resp; }

        return $resp;

    }

    

    /**

     * Buscar dados do QR Code PIX

     * 

     * @param string $payment_id

     * @return array|WP_Error

     */

    public function get_pix_qrcode($payment_id) {

        if (!$this->is_configured()) {

            return new WP_Error('not_configured', 'Gateway Asaas não configurado');

        }

        

        return $this->make_request("/payments/{$payment_id}/pixQrCode", [], 'GET');

    }

    

    /**

     * Buscar dados do Boleto

     * 

     * @param string $payment_id

     * @return array|WP_Error

     */

    public function get_boleto_data($payment_id) {

        if (!$this->is_configured()) {

            return new WP_Error('not_configured', 'Gateway Asaas não configurado');

        }

        

        $payment = $this->make_request("/payments/{$payment_id}", [], 'GET');

        

        if (is_wp_error($payment)) {

            return $payment;

        }

        

        return [

            'id' => $payment['id'],

            'status' => $payment['status'],

            'value' => $payment['value'],

            'dueDate' => $payment['dueDate'],

            'identificationField' => $payment['identificationField'] ?? '',

            'barCode' => $payment['barCode'] ?? '',

            'bankSlipUrl' => $payment['bankSlipUrl'] ?? '',

            'invoiceUrl' => $payment['invoiceUrl'] ?? ''

        ];

    }

    

    /**

     * Criar cobrança de Boleto

     * 

     * @param int $order_id

     * @param array $payment_data

     * @return array|WP_Error

     */

    public function create_boleto_payment($order_id, $payment_data) {

        $order = new HNG_Order($order_id);

        

        // Criar ou obter cliente

        $customer = $this->create_customer([

            'name' => $order->get_customer_name(),

            'email' => $order->get_customer_email(),

            'cpfCnpj' => $payment_data['cpf'],

            'phone' => $order->get_billing_phone(),

            'postalCode' => $order->get_billing_postcode(),

            'address' => $order->get_billing_address(),

            'addressNumber' => $order->get_billing_number(),

            'complement' => $order->get_billing_complement(),

            'province' => $order->get_billing_neighborhood(),

        ]);

        

        if (is_wp_error($customer)) {

            return $customer;

        }

        

        $customer_id = $customer['id'];

        

        // Calcular split (taxa do plugin)

        $plugin_fee_amount = 0;

        if (class_exists('HNG_Fee_Calculator')) {

            $calc = HNG_Fee_Calculator::instance();

            $fee_data = $calc->calculate_all_fees($order->get_total(), 'physical', $this->id, 'boleto');

            $plugin_fee_amount = $fee_data['plugin_fee'];

        }

        

        // VALIDAR COM API ANTES DE CRIAR COBRANÇA

        $validation = $this->validate_transaction_with_api(

            $order_id,

            $order->get_total(),

            $plugin_fee_amount,

            'boleto'

        );

        

        if (is_wp_error($validation)) {

            $this->log('VALIDATION_FAILED', [

                'order_id' => $order_id,

                'error' => $validation->get_error_message()

            ]);

            

            $order->add_note(

                '?? Erro ao processar pagamento: ' . $validation->get_error_message() . 

                ' | Entre em contato com o suporte HNG Commerce.'

            );

            

            return $validation;

        }

        

        $api_wallet_id = $validation['wallet_id'];

        

        // Criar cobrança de Boleto

        $charge_data = [

            'customer' => $customer_id,

            'billingType' => 'BOLETO',

            'value' => $order->get_total(),

            'dueDate' => gmdate('Y-m-d', strtotime('+3 days')),

            'description' => sprintf('Pedido #%s - %s', $order->get_order_number(), get_bloginfo('name')),

            'externalReference' => (string) $order_id,

            'discount' => [

                'value' => 0,

                'dueDateLimitDays' => 0,

            ],

            'fine' => [

                'value' => 2.00, // 2%

            ],

            'interest' => [

                'value' => 1.00, // 1% ao mês

            ],

        ];

        

        // Determinar wallet alvo para split: usar somente a wallet retornada pela API

        $target_wallet = !empty($api_wallet_id) ? $api_wallet_id : '';

        if (!empty($target_wallet) && $plugin_fee_amount > 0) {
            $charge_data['split'] = [
                [
                    'walletId' => $target_wallet,
                    'fixedValue' => $plugin_fee_amount,
                    'description' => 'Taxa HNG Commerce Plugin'
                ]
            ];
        }

        

        $charge = $this->make_request('/payments', $charge_data, 'POST');

        

        if (is_wp_error($charge)) {

            return $charge;

        }

        

        // Salvar dados da cobrança no pedido

        update_post_meta($order->get_post_id(), '_asaas_payment_id', $charge['id']);

        update_post_meta($order->get_post_id(), '_asaas_customer_id', $customer_id);

        update_post_meta($order->get_post_id(), '_payment_method', 'boleto');

        // Taxas + ledger (Boleto)

        if (class_exists('HNG_Fee_Calculator') && class_exists('HNG_Ledger')) {

            $calc = HNG_Fee_Calculator::instance();

            $fee_data = $calc->calculate_all_fees($order->get_total(), 'physical', $this->id, 'boleto');

            update_post_meta($order->get_post_id(), '_hng_fee_data', $fee_data);

            HNG_Ledger::add_entry([

                'type' => 'charge',

                'order_id' => $order_id,

                'external_ref' => $charge['id'],

                'gross_amount' => $fee_data['gross_amount'],

                'fee_amount' => $fee_data['plugin_fee'] + $fee_data['gateway_fee'],

                'net_amount' => $fee_data['net_amount'],

                'status' => 'pending',

                'meta' => [

                    'gateway' => $this->id,

                    'method' => 'boleto',

                    'plugin_fee' => $fee_data['plugin_fee'],

                    'gateway_fee' => $fee_data['gateway_fee'],

                    'tier' => $fee_data['tier']

                ]

            ]);

        }



        return $charge;

    }

    

    /**

     * Criar cobrança de Cartão de Crédito

     * 

     * @param int $order_id

     * @param array $payment_data

     * @return array|WP_Error

     */

    public function create_credit_card_payment($order_id, $payment_data) {

        $order = new HNG_Order($order_id);

        

        // Criar ou obter cliente

        $customer = $this->create_customer([

            'name' => $order->get_customer_name(),

            'email' => $order->get_customer_email(),

            'cpfCnpj' => $payment_data['cpf'],

            'phone' => $order->get_billing_phone(),

            'postalCode' => $order->get_billing_postcode(),

            'address' => $order->get_billing_address(),

            'addressNumber' => $order->get_billing_number(),

            'complement' => $order->get_billing_complement(),

            'province' => $order->get_billing_neighborhood(),

        ]);

        

        if (is_wp_error($customer)) {

            return $customer;

        }

        

        $customer_id = $customer['id'];

        

        // Calcular split (taxa do plugin)

        $plugin_fee_amount = 0;

        if (class_exists('HNG_Fee_Calculator')) {

            $calc = HNG_Fee_Calculator::instance();

            $fee_data = $calc->calculate_all_fees($order->get_total(), 'physical', $this->id, 'credit_card');

            $plugin_fee_amount = $fee_data['plugin_fee'];

        }

        

        // VALIDAR COM API ANTES DE CRIAR COBRANÇA

        $validation = $this->validate_transaction_with_api(

            $order_id,

            $order->get_total(),

            $plugin_fee_amount,

            'credit_card'

        );

        

        if (is_wp_error($validation)) {

            $this->log('VALIDATION_FAILED', [

                'order_id' => $order_id,

                'error' => $validation->get_error_message()

            ]);

            

            $order->add_note(

                '?? Erro ao processar pagamento: ' . $validation->get_error_message() . 

                ' | Entre em contato com o suporte HNG Commerce.'

            );

            

            return $validation;

        }

        

        $api_wallet_id = $validation['wallet_id'];

        

        // Criar cobrança de Cartão

        $charge_data = [

            'customer' => $customer_id,

            'billingType' => 'CREDIT_CARD',

            'value' => $order->get_total(),

            'dueDate' => gmdate('Y-m-d'),

            'description' => sprintf('Pedido #%s - %s', $order->get_order_number(), get_bloginfo('name')),

            'externalReference' => (string) $order_id,

            'creditCard' => [

                'holderName' => sanitize_text_field($payment_data['card_holder_name']),

                'number' => preg_replace('/\s+/', '', $payment_data['card_number']),

                'expiryMonth' => sanitize_text_field($payment_data['card_expiry_month']),

                'expiryYear' => sanitize_text_field($payment_data['card_expiry_year']),

                'ccv' => sanitize_text_field($payment_data['card_cvv']),

            ],

            'creditCardHolderInfo' => [

                'name' => sanitize_text_field($payment_data['card_holder_name']),

                'email' => $order->get_customer_email(),

                'cpfCnpj' => preg_replace('/[^0-9]/', '', $payment_data['cpf']),

                'postalCode' => preg_replace('/[^0-9]/', '', $order->get_billing_postcode()),

                'addressNumber' => $order->get_billing_number(),

                'phone' => preg_replace('/[^0-9]/', '', $order->get_billing_phone()),

            ],

        ];

        

        // Determinar wallet alvo para split: usar somente a wallet retornada pela API

        $target_wallet = !empty($api_wallet_id) ? $api_wallet_id : '';

        if (!empty($target_wallet) && $plugin_fee_amount > 0) {

            $charge_data['split'] = [

                [

                    'walletId' => $target_wallet,

                    'fixedValue' => $plugin_fee_amount,

                    'description' => 'Taxa HNG Commerce Plugin'

                ]

            ];

        }

        

        // Adicionar parcelamento se houver

        if (isset($payment_data['installments']) && $payment_data['installments'] > 1) {

            $charge_data['installmentCount'] = (int) $payment_data['installments'];

            $charge_data['installmentValue'] = $order->get_total() / $payment_data['installments'];

        }

        

        $charge = $this->make_request('/payments', $charge_data, 'POST');

        

        if (is_wp_error($charge)) {

            return $charge;

        }

        

        // Salvar dados da cobrança no pedido

        update_post_meta($order->get_post_id(), '_asaas_payment_id', $charge['id']);

        update_post_meta($order->get_post_id(), '_asaas_customer_id', $customer_id);

        update_post_meta($order->get_post_id(), '_payment_method', 'credit_card');

        

        // Salvar últimos 4 dígitos do cartão (para exibição)

        $last4 = substr(preg_replace('/\s+/', '', $payment_data['card_number']), -4);

        update_post_meta($order->get_post_id(), '_card_last4', $last4);

        // Taxas + ledger (Cartão)

        if (class_exists('HNG_Fee_Calculator') && class_exists('HNG_Ledger')) {

            $calc = HNG_Fee_Calculator::instance();

            $fee_data = $calc->calculate_all_fees($order->get_total(), 'physical', $this->id, 'credit_card');

            update_post_meta($order->get_post_id(), '_hng_fee_data', $fee_data);

            HNG_Ledger::add_entry([

                'type' => 'charge',

                'order_id' => $order_id,

                'external_ref' => $charge['id'],

                'gross_amount' => $fee_data['gross_amount'],

                'fee_amount' => $fee_data['plugin_fee'] + $fee_data['gateway_fee'],

                'net_amount' => $fee_data['net_amount'],

                'status' => 'pending',

                'meta' => [

                    'gateway' => $this->id,

                    'method' => 'credit_card',

                    'plugin_fee' => $fee_data['plugin_fee'],

                    'gateway_fee' => $fee_data['gateway_fee'],

                    'tier' => $fee_data['tier']

                ]

            ]);

        }



        return $charge;

    }

    

    /**

     * Verificar status do pagamento

     * 

     * @param string $payment_id

     * @return array|WP_Error

     */

    public function get_payment_status($payment_id) {

        return $this->make_request("/payments/{$payment_id}", [], 'GET');

    }

    

    /**

     * AJAX: Verificar status do pagamento

     */

    public function ajax_check_payment_status() {

        // Nonce validation (specific nonce or legacy for backward compatibility)
        $nonce = isset($_REQUEST['nonce']) ? wp_unslash($_REQUEST['nonce']) : '';
        $nonce_verified = wp_verify_nonce($nonce, 'hng_asaas_check_payment') || 
                         wp_verify_nonce($nonce, 'hng_payment_check');
        if (!$nonce_verified) {
            wp_send_json_error(['message' => __('Verificação de segurança falhou.', 'hng-commerce')]);
        }

        $post = function_exists('wp_unslash') ? wp_unslash($_POST) : $_POST;



        $order_id = absint($post['order_id'] ?? 0);

        

        if (!$order_id) {

            wp_send_json_error(['message' => 'ID do pedido inválido']);

        }

        

        $payment_id = get_post_meta($order_id, '_asaas_payment_id', true);

        

        if (!$payment_id) {

            wp_send_json_error(['message' => 'Cobrança não encontrada']);

        }

        

        $status = $this->get_payment_status($payment_id);

        

        if (is_wp_error($status)) {

            wp_send_json_error(['message' => $status->get_error_message()]);

        }

        

        // Atualizar status do pedido se necessário

        if ($status['status'] === 'RECEIVED' || $status['status'] === 'CONFIRMED') {

            $order = new HNG_Order($order_id);

            // Usar status interno com prefixo hng- para desbloquear e-mails e colunas
            $order->update_status('hng-processing', 'Pagamento confirmado via Asaas');

        }

        

        wp_send_json_success([

            'status' => $status['status'],

            'paid' => in_array($status['status'], ['RECEIVED', 'CONFIRMED']),

        ]);

    }

    

    /**

     * Testar conexão com API

     */

    public function test_connection() {

        if (!$this->is_configured()) {

            return new WP_Error('not_configured', 'Gateway Asaas não configurado. Verifique a API Key.');

        }

        

        // Fazer uma requisição simples para verificar a conexão

        $response = $this->make_request('/customers?limit=1', [], 'GET');

        

        if (is_wp_error($response)) {

            return $response;

        }

        

        return true;

    }

    

    /**

     * Registrar log

     * 

     * @param string $type

     * @param mixed $data

     */

    protected function log($type, $data) {

        if (get_option('hng_asaas_debug', 'no') !== 'yes') {

            return;

        }

        

        $log_file = WP_CONTENT_DIR . '/hng-asaas-logs.txt';

        $timestamp = gmdate('Y-m-d H:i:s');

        $message = sprintf("[%s] %s: %s\n", $timestamp, $type, print_r($data, true));

        

        if (function_exists('hng_files_log_put_contents')) {

            hng_files_log_put_contents($log_file, $message);

        }

    }

}



/**

 * Hook para renovação manual de assinatura (PIX/Boleto)

 * Gera novo pagamento quando assinatura precisa ser renovada manualmente

 */

add_action('hng_subscription_manual_renewal', function($subscription_id, $order_id, $payment_method) {

    // Buscar dados da assinatura

    $subscription = new HNG_Subscription($subscription_id);

    $gateway_name = $subscription->get_gateway();

    

    // Verificar se é Asaas

    if ($gateway_name !== 'asaas') {

        return;

    }

    

    // Instanciar gateway

    $gateway = new HNG_Gateway_Asaas();

    

    // Buscar dados do pedido

    $order = new HNG_Order($order_id);

    $customer_email = $order->get_customer_email();

    

    // Buscar dados do cliente (CPF do pedido original se existir)

    $original_order_id = $subscription->get_order_id();

    $cpf = get_post_meta($original_order_id, '_billing_cpf', true);

    

    if (empty($cpf)) {

        // Tentar buscar do usuário

        $user = get_user_by('email', $customer_email);

        if ($user) {

            $cpf = get_user_meta($user->ID, 'billing_cpf', true);

        }

    }

    

    // Preparar dados de pagamento

    $payment_data = [

        'cpf' => $cpf ?: '00000000000', // Fallback se não tiver CPF

    ];

    

    try {

        // Gerar novo pagamento

        if ($payment_method === 'pix') {

            $result = $gateway->create_pix_payment($order_id, $payment_data);

            

            if (!is_wp_error($result)) {

                // Salvar dados do PIX no pedido

                update_post_meta($order_id, '_payment_data', [

                    'qr_code' => $result['pixQrCode']['payload'] ?? '',

                    'qr_code_image' => $result['pixQrCode']['encodedImage'] ?? '',

                    'expires_at' => $result['pixQrCode']['expirationDate'] ?? '',

                    'payment_id' => $result['id'] ?? '',

                ]);

                

                // URL de visualização (pode ser personalizada)

                $payment_url = home_url('/checkout/view-pix/?order=' . $order_id);

                update_post_meta($order_id, '_payment_url', $payment_url);

                

                // Log sucesso

                if (function_exists('hng_files_log_append')) { hng_files_log_append(HNG_COMMERCE_PATH . 'logs/gateways-asaas.log', sprintf('[Asaas Renovação] PIX gerado para assinatura #%d, pedido #%d' . PHP_EOL, $subscription_id, $order_id)); }

            } else {

                if (function_exists('hng_files_log_append')) { hng_files_log_append(HNG_COMMERCE_PATH . 'logs/gateways-asaas.log', sprintf('[Asaas Renovação] Erro ao gerar PIX: %s' . PHP_EOL, $result->get_error_message())); }

            }

            

        } elseif ($payment_method === 'boleto') {

            $result = $gateway->create_boleto_payment($order_id, $payment_data);

            

            if (!is_wp_error($result)) {

                // Salvar dados do Boleto no pedido

                update_post_meta($order_id, '_payment_data', [

                    'boleto_url' => $result['bankSlipUrl'] ?? '',

                    'barcode' => $result['nossoNumero'] ?? '',

                    'due_date' => $result['dueDate'] ?? gmdate('Y-m-d', strtotime('+3 days')),

                    'payment_id' => $result['id'] ?? '',

                ]);

                

                // URL do boleto

                $payment_url = $result['bankSlipUrl'] ?? '';

                update_post_meta($order_id, '_payment_url', $payment_url);

                

                // Log sucesso

                if (function_exists('hng_files_log_append')) { hng_files_log_append(HNG_COMMERCE_PATH . 'logs/gateways-asaas.log', sprintf('[Asaas Renovação] Boleto gerado para assinatura #%d, pedido #%d' . PHP_EOL, $subscription_id, $order_id)); }

            } else {

                if (function_exists('hng_files_log_append')) { hng_files_log_append(HNG_COMMERCE_PATH . 'logs/gateways-asaas.log', sprintf('[Asaas Renovação] Erro ao gerar Boleto: %s' . PHP_EOL, $result->get_error_message())); }

            }

        }

        

        } catch (Exception $e) {

            if (function_exists('hng_files_log_append')) { hng_files_log_append(HNG_COMMERCE_PATH . 'logs/gateways-asaas.log', sprintf('[Asaas Renovação] Exception: %s' . PHP_EOL, $e->getMessage())); }

        }

}, 10, 3);

