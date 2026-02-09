<?php
/**
 * HNG Commerce - Refund Processor
 * 
 * Processa reembolsos via gateway de pagamento
 * Detecta automaticamente o gateway usado e chama a API correta
 *
 * @package HNG_Commerce
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Refund_Processor {

    /**
     * Instância única
     */
    private static $instance = null;

    /**
     * Singleton
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // Escuta quando um reembolso é aprovado
        add_action('hng_refund_approved', [$this, 'process_refund'], 10, 3);
    }

    /**
     * Processar reembolso aprovado
     * 
     * @param int $user_id ID do usuário
     * @param int $order_id ID do pedido
     * @param float $amount Valor do reembolso
     */
    public function process_refund($user_id, $order_id, $amount) {
        global $wpdb;

        // Obter pedido
        $order = new HNG_Order($order_id);
        if (!$order->get_id()) {
            $this->log_error('order_not_found', "Pedido #{$order_id} não encontrado");
            return false;
        }

        // Obter método de pagamento
        $payment_method = get_post_meta($order->get_post_id(), '_payment_method', true);
        if (empty($payment_method)) {
            $this->log_error('payment_method_not_found', "Método de pagamento não encontrado para pedido #{$order_id}");
            return false;
        }

        // Obter gateway usado
        $gateway = $this->get_gateway_from_payment_method($payment_method, $order_id);
        if (!$gateway) {
            $this->log_error('gateway_not_found', "Gateway não identificado para pedido #{$order_id}");
            return false;
        }

        // Obter transaction ID
        $transaction_id = get_post_meta($order->get_post_id(), '_transaction_id', true);
        if (empty($transaction_id)) {
            $this->log_error('transaction_id_not_found', "Transaction ID não encontrado para pedido #{$order_id}");
            return false;
        }

        // Processar reembolso conforme gateway
        $refund_result = $this->process_gateway_refund($gateway, $order_id, $transaction_id, $amount, $payment_method);

        if (is_wp_error($refund_result)) {
            $this->log_error('refund_failed', "Erro ao processar reembolso: " . $refund_result->get_error_message());
            return false;
        }

        // Atualizar status da solicitação de refund
        $refund_request_id = $this->get_refund_request_id($order_id, $user_id);
        if ($refund_request_id) {
            $wpdb->update(
                $wpdb->prefix . 'hng_refund_requests',
                [
                    'status' => 'completed',
                    'updated_at' => current_time('mysql'),
                ],
                ['id' => $refund_request_id],
                ['%s', '%s'],
                ['%d']
            );
        }

        // Log de sucesso
        $this->log_success("Reembolso processado com sucesso via {$gateway} para pedido #{$order_id}");

        return true;
    }

    /**
     * Processar reembolso via gateway
     * 
     * @param string $gateway Gateway (asaas, pagseguro, etc)
     * @param int $order_id ID do pedido
     * @param string $transaction_id Transaction ID
     * @param float $amount Valor
     * @param string $payment_method Método de pagamento
     * @return array|WP_Error
     */
    private function process_gateway_refund($gateway, $order_id, $transaction_id, $amount, $payment_method) {
        switch (strtolower($gateway)) {
            case 'asaas':
                return $this->refund_via_asaas($transaction_id, $amount);
            
            case 'pagseguro':
                return $this->refund_via_pagseguro($transaction_id, $amount);
            
            case 'mercadopago':
                return $this->refund_via_mercadopago($transaction_id, $amount);
            
            case 'pagarme':
                return $this->refund_via_pagarme($transaction_id, $amount);
            
            case 'cielo':
                return $this->refund_via_cielo($transaction_id, $amount);
            
            case 'getnet':
                return $this->refund_via_getnet($transaction_id, $amount);
            
            case 'rede':
                return $this->refund_via_rede($transaction_id, $amount);
            
            case 'stone':
                return $this->refund_via_stone($transaction_id, $amount);
            
            case 'api_server':
                return $this->refund_via_api_server($order_id, $amount);
            
            default:
                return new WP_Error('unsupported_gateway', "Gateway '{$gateway}' não suportado para reembolso");
        }
    }

    /**
     * Reembolsar via Asaas
     * 
     * @param string $payment_id Payment ID no Asaas
     * @param float $amount Valor a reembolsar
     * @return array|WP_Error
     */
    private function refund_via_asaas($payment_id, $amount) {
        // Remover prefixo se existir (ex: asaas_123456 -> 123456)
        if (strpos($payment_id, 'asaas_') === 0) {
            $payment_id = substr($payment_id, 6);
        }

        $asaas_key = get_option('hng_asaas_api_key');
        if (empty($asaas_key)) {
            return new WP_Error('asaas_not_configured', 'Asaas não está configurado');
        }

        $url = 'https://api.asaas.com/v3/payments/' . $payment_id . '/refund';

        $response = wp_remote_post($url, [
            'timeout' => 15,
            'headers' => [
                'Content-Type' => 'application/json',
                'access_token' => $asaas_key,
            ],
            'body' => json_encode([
                'refundAmount' => floatval($amount),
            ]),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status !== 200) {
            $error_msg = isset($body['errors'][0]['description']) 
                ? $body['errors'][0]['description'] 
                : 'Erro ao processar reembolso via Asaas';
            return new WP_Error('asaas_error', $error_msg);
        }

        return [
            'success' => true,
            'gateway' => 'asaas',
            'refund_id' => $body['id'] ?? null,
            'amount' => floatval($amount),
            'status' => $body['status'] ?? 'pending',
        ];
    }

    /**
     * Reembolsar via PagSeguro
     * 
     * @param string $transaction_id Transaction ID no PagSeguro
     * @param float $amount Valor a reembolsar
     * @return array|WP_Error
     */
    private function refund_via_pagseguro($transaction_id, $amount) {
        $pagseguro_token = get_option('hng_pagseguro_token');
        if (empty($pagseguro_token)) {
            return new WP_Error('pagseguro_not_configured', 'PagSeguro não está configurado');
        }

        // PagSeguro usa o padrão: POST /transactions/{code}/refunds
        $url = 'https://ws.pagseguro.com.br/v2/transactions/' . $transaction_id . '/refunds';

        $body = [
            'token' => $pagseguro_token,
            'email' => get_option('admin_email'),
            'refundType' => 3, // Full refund (3 = all, use amount for partial)
        ];

        // Se for reembolso parcial
        if ($amount > 0) {
            $body['refundType'] = 1; // Partial refund
            $body['refundValue'] = number_format($amount, 2, '.', '');
        }

        $response = wp_remote_post($url, [
            'timeout' => 15,
            'body' => http_build_query($body),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body_response = wp_remote_retrieve_body($response);

        // PagSeguro retorna XML
        if ($status !== 200) {
            return new WP_Error('pagseguro_error', 'Erro ao processar reembolso via PagSeguro: HTTP ' . $status);
        }

        // Parse XML response
        $xml = @simplexml_load_string($body_response);
        if (!$xml) {
            return new WP_Error('pagseguro_xml_error', 'Resposta inválida do PagSeguro');
        }

        return [
            'success' => true,
            'gateway' => 'pagseguro',
            'transaction_id' => (string) $xml->code,
            'amount' => floatval($amount),
            'status' => 'completed',
        ];
    }

    /**
     * Reembolsar via MercadoPago
     */
    private function refund_via_mercadopago($transaction_id, $amount) {
        $access_token = get_option('hng_mercadopago_access_token');
        if (empty($access_token)) {
            return new WP_Error('mercadopago_not_configured', 'MercadoPago não está configurado');
        }

        $url = 'https://api.mercadopago.com/v1/payments/' . $transaction_id . '/refunds';

        $response = wp_remote_post($url, [
            'timeout' => 15,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $access_token,
            ],
            'body' => json_encode(['amount' => floatval($amount)]),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status !== 201 && $status !== 200) {
            $error_msg = $body['message'] ?? 'Erro ao processar reembolso via MercadoPago';
            return new WP_Error('mercadopago_error', $error_msg);
        }

        return [
            'success' => true,
            'gateway' => 'mercadopago',
            'refund_id' => $body['id'] ?? null,
            'amount' => floatval($amount),
            'status' => 'completed',
        ];
    }

    /**
     * Reembolsar via Pagarme
     */
    private function refund_via_pagarme($transaction_id, $amount) {
        $api_key = get_option('hng_pagarme_api_key');
        if (empty($api_key)) {
            return new WP_Error('pagarme_not_configured', 'Pagarme não está configurado');
        }

        // Pagarme usa: POST /core/v5/transactions/{id}/refunds
        $url = 'https://api.pagar.me/core/v5/transactions/' . $transaction_id . '/refunds';

        $response = wp_remote_post($url, [
            'timeout' => 15,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($api_key . ':'),
            ],
            'body' => json_encode(['amount' => intval($amount * 100)]), // Pagarme usa centavos
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status !== 200) {
            $error_msg = $body['message'] ?? 'Erro ao processar reembolso via Pagarme';
            return new WP_Error('pagarme_error', $error_msg);
        }

        return [
            'success' => true,
            'gateway' => 'pagarme',
            'refund_id' => $body['id'] ?? null,
            'amount' => floatval($amount),
            'status' => 'completed',
        ];
    }

    /**
     * Reembolsar via Cielo
     */
    private function refund_via_cielo($transaction_id, $amount) {
        $merchant_id = get_option('hng_cielo_merchant_id');
        $merchant_key = get_option('hng_cielo_merchant_key');

        if (empty($merchant_id) || empty($merchant_key)) {
            return new WP_Error('cielo_not_configured', 'Cielo não está configurado');
        }

        // Cielo: PUT /1/sales/{PaymentId}/void
        $url = 'https://api.cieloecommerce.cielo.com.br/1/sales/' . $transaction_id . '/void';

        $response = wp_remote_post($url, [
            'method' => 'PUT',
            'timeout' => 15,
            'headers' => [
                'Content-Type' => 'application/json',
                'MerchantId' => $merchant_id,
                'MerchantKey' => $merchant_key,
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status !== 200) {
            $error_msg = 'Erro ao processar reembolso via Cielo';
            return new WP_Error('cielo_error', $error_msg);
        }

        return [
            'success' => true,
            'gateway' => 'cielo',
            'amount' => floatval($amount),
            'status' => 'completed',
        ];
    }

    /**
     * Reembolsar via GetNet
     */
    private function refund_via_getnet($transaction_id, $amount) {
        $seller_id = get_option('hng_getnet_seller_id');
        $client_id = get_option('hng_getnet_client_id');
        $client_secret = get_option('hng_getnet_client_secret');

        if (empty($seller_id) || empty($client_id) || empty($client_secret)) {
            return new WP_Error('getnet_not_configured', 'GetNet não está configurado');
        }

        // GetNet: POST /v1/payments/{payment_id}/cancel
        $url = 'https://api.getnet.com.br/v1/payments/' . $transaction_id . '/cancel';

        $response = wp_remote_post($url, [
            'timeout' => 15,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . base64_encode($client_id . ':' . $client_secret),
                'Seller-Id' => $seller_id,
            ],
            'body' => json_encode(['amount' => intval($amount * 100)]),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status !== 200) {
            $error_msg = $body['message'] ?? 'Erro ao processar reembolso via GetNet';
            return new WP_Error('getnet_error', $error_msg);
        }

        return [
            'success' => true,
            'gateway' => 'getnet',
            'amount' => floatval($amount),
            'status' => 'completed',
        ];
    }

    /**
     * Reembolsar via Rede
     */
    private function refund_via_rede($transaction_id, $amount) {
        $pv = get_option('hng_rede_pv');
        $token = get_option('hng_rede_token');

        if (empty($pv) || empty($token)) {
            return new WP_Error('rede_not_configured', 'Rede não está configurado');
        }

        // Rede: POST /transactions/{tid}/refund
        $url = 'https://api.userede.com.br/transactions/' . $transaction_id . '/refund';

        $response = wp_remote_post($url, [
            'timeout' => 15,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
            'body' => json_encode([
                'pv' => $pv,
                'amount' => floatval($amount),
            ]),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status !== 200) {
            $error_msg = $body['message'] ?? 'Erro ao processar reembolso via Rede';
            return new WP_Error('rede_error', $error_msg);
        }

        return [
            'success' => true,
            'gateway' => 'rede',
            'amount' => floatval($amount),
            'status' => 'completed',
        ];
    }

    /**
     * Reembolsar via Stone
     */
    private function refund_via_stone($transaction_id, $amount) {
        $api_key = get_option('hng_stone_api_key');

        if (empty($api_key)) {
            return new WP_Error('stone_not_configured', 'Stone não está configurado');
        }

        // Stone: POST /transactions/{id}/refunds
        $url = 'https://api.stone.com.br/v1/transactions/' . $transaction_id . '/refunds';

        $response = wp_remote_post($url, [
            'timeout' => 15,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body' => json_encode(['amount' => intval($amount * 100)]),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status !== 200 && $status !== 201) {
            $error_msg = $body['message'] ?? 'Erro ao processar reembolso via Stone';
            return new WP_Error('stone_error', $error_msg);
        }

        return [
            'success' => true,
            'gateway' => 'stone',
            'refund_id' => $body['id'] ?? null,
            'amount' => floatval($amount),
            'status' => 'completed',
        ];
    }

    /**
     * Reembolsar via API Server HNG
     * 
     * @param int $order_id ID do pedido
     * @param float $amount Valor
     * @return array|WP_Error
     */
    private function refund_via_api_server($order_id, $amount) {
        $api_client = HNG_API_Client::instance();
        if (!$api_client) {
            return new WP_Error('api_client_error', 'Cliente API não disponível');
        }

        $order = new HNG_Order($order_id);
        $transaction_id = get_post_meta($order->get_post_id(), '_transaction_id', true);

        // Chamar API central para processar reembolso
        $response = wp_remote_post('https://api.hngdesenvolvimentos.com.br/endpoints/process-refund.php', [
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Hng-Api-Key' => get_option('hng_api_key', ''),
            ],
            'body' => json_encode([
                'order_id' => $order_id,
                'transaction_id' => $transaction_id,
                'refund_amount' => floatval($amount),
                'merchant_id' => get_option('hng_merchant_id', ''),
                'timestamp' => time(),
            ]),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status !== 200) {
            $error_msg = $body['error'] ?? 'Erro ao processar reembolso via API Server';
            return new WP_Error('api_server_error', $error_msg);
        }

        return [
            'success' => true,
            'gateway' => 'api_server',
            'refund_id' => $body['refund_id'] ?? null,
            'amount' => floatval($amount),
            'status' => 'completed',
        ];
    }

    /**
     * Obter gateway a partir do método de pagamento
     * 
     * @param string $payment_method Método de pagamento (pix, boleto, credit_card)
     * @param int $order_id ID do pedido (para logs)
     * @return string|false Gateway identificado (asaas, pagseguro, etc) ou false
     */
    private function get_gateway_from_payment_method($payment_method, $order_id) {
        // Verificar qual gateway está ativo verificando opções salvas
        $gateways_priority = [
            'asaas' => 'hng_asaas_api_key',
            'pagseguro' => 'hng_pagseguro_token',
            'mercadopago' => 'hng_mercadopago_access_token',
            'pagarme' => 'hng_pagarme_api_key',
            'cielo' => 'hng_cielo_api_key',
            'getnet' => 'hng_getnet_api_key',
            'rede' => 'hng_rede_api_key',
            'stone' => 'hng_stone_api_key',
            'api_server' => 'hng_api_key', // Fallback padrão
        ];

        // Verificar cada gateway em ordem de prioridade
        foreach ($gateways_priority as $gateway_name => $option_key) {
            if (!empty(get_option($option_key))) {
                return $gateway_name;
            }
        }

        return false;
    }

    /**
     * Obter ID da solicitação de refund
     * 
     * @param int $order_id ID do pedido
     * @param int $user_id ID do usuário
     * @return int|false
     */
    private function get_refund_request_id($order_id, $user_id) {
        global $wpdb;

        $refund = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}hng_refund_requests 
            WHERE order_id = %d AND user_id = %d AND status = %s
            ORDER BY created_at DESC LIMIT 1",
            $order_id,
            $user_id,
            'approved'
        ));

        return $refund ? $refund->id : false;
    }

    /**
     * Log de erro
     * 
     * @param string $type Tipo de erro
     * @param string $message Mensagem
     */
    private function log_error($type, $message) {
        if (function_exists('hng_files_log_append')) {
            hng_files_log_append(
                HNG_COMMERCE_PATH . 'logs/refund-processor.log',
                '[ERROR] [' . $type . '] ' . $message . PHP_EOL
            );
        }
    }

    /**
     * Log de sucesso
     * 
     * @param string $message Mensagem
     */
    private function log_success($message) {
        if (function_exists('hng_files_log_append')) {
            hng_files_log_append(
                HNG_COMMERCE_PATH . 'logs/refund-processor.log',
                '[SUCCESS] ' . $message . PHP_EOL
            );
        }
    }
}

// Inicializar
HNG_Refund_Processor::instance();
