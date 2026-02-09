<?php
/**
 * Webhook Handler Universal
 * Processa notificaï¿½ï¿½es de pagamento dos gateways
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Webhook_Handler {
    
    /**
     * Instï¿½ncia ï¿½nica
     */
    private static $instance = null;
    
    /**
     * Obter instï¿½ncia
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
        // Registrar endpoint REST
        add_action('rest_api_init', [$this, 'register_routes']);
        
        // Log de webhooks (debug)
        add_action('hng_webhook_received', [$this, 'log_webhook'], 10, 3);

        // Garantir flag de delegação configurada (padrão: usar _api-server)
        add_option('hng_use_external_webhook', 'yes');
    }
    
    /**
     * Registrar rotas REST
     */
    public function register_routes() {
        register_rest_route('hng/v1', '/webhook/(?P<gateway>[a-zA-Z0-9_-]+)', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_webhook'],
            'permission_callback' => '__return_true', // Webhook nï¿½o usa autenticaï¿½ï¿½o WP
        ]);
    }
    
    /**
     * Processar webhook
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_webhook($request) {
        // Se a ingestão estiver delegada ao `_api-server`, não processar aqui.
        if ($this->should_delegate_to_external()) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Webhook handled by external _api-server'
            ], 410);
        }

        $gateway = $request->get_param('gateway');
        $body = $request->get_body();
        $headers = $request->get_headers();
        
        // Validate IP whitelist for gateway
        $client_ip = $this->get_client_ip();
        if (!$this->validate_webhook_ip($gateway, $client_ip)) {
            $this->log_error('Webhook blocked: Invalid IP ' . $client_ip . ' for gateway ' . $gateway);
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Unauthorized IP address',
            ], 403);
        }

        // Rate limiting por gateway/IP
        if (class_exists('HNG_Rate_Limiter')) {
            $rl = HNG_Rate_Limiter::enforce('webhook_' . sanitize_key($gateway), 20, 60);
            if (is_wp_error($rl)) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => $rl->get_error_message(),
                ], 429);
            }
        }

        // Idempotência: se possui event id já processado, retornar sucesso rápido
        // Primeiro tentamos uma verificação persistente na tabela de logs (se existir),
        // em seguida mantemos um fallback por transiente para performance.
        $event_id = $headers['x-event-id'][0] ?? '';
        if ($event_id) {
            global $wpdb;
            $table = $wpdb->prefix . 'hng_asaas_webhook_log';

            // Se tabela existe, procurar por event_id dentro do payload (LIKE)
            $found_in_db = false;
            if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") == $table) {
                $like = '%' . $wpdb->esc_like($event_id) . '%';
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix is safe here
                $row = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE payload LIKE %s LIMIT 1", $like));
                    if ($row) {
                        $found_in_db = true;
                    } else {
                        // Registrar um rastro mínimo para persistir que vimos este event_id
                        $insert_payload = wp_json_encode([
                            'event_id' => $event_id,
                            'gateway' => $gateway,
                            'note' => 'idempotency marker'
                        ]);
                        $wpdb->insert($table, [
                            'event_type' => 'idempotency_marker',
                            'payload' => $insert_payload,
                            'created_at' => current_time('mysql')
                        ]);
                        $this->log_info("Idempotency marker inserted for event_id {$event_id} (gateway: {$gateway})");
                    }
            }

            if ($found_in_db) {
                $this->log_info("Idempotent replay detected (db) for event_id {$event_id} on gateway {$gateway}");
                return new WP_REST_Response(['success' => true, 'message' => 'Idempotent replay ignored (db)'], 200);
            }

            // Fallback: checar transiente em memória para evitar replays rápidos
            $cache_key = 'hng_webhook_evt_' . md5($gateway . '|' . $event_id);
            if (get_transient($cache_key)) {
                $this->log_info("Idempotent replay detected (transient) for event_id {$event_id} on gateway {$gateway}");
                return new WP_REST_Response(['success' => true, 'message' => 'Idempotent replay ignored'], 200);
            }
            // Guardar por 1h
            set_transient($cache_key, 1, HOUR_IN_SECONDS);
        }

        // Verificação de assinatura HMAC SHA256 OBRIGATÓRIA
        $secret = get_option('hng_webhook_secret_' . $gateway, '');
        if (empty($secret)) {
            error_log(sprintf('HNG Security: Webhook rejected for %s - no secret configured', $gateway));
            return new WP_REST_Response([
                'success' => false, 
                'message' => 'Webhook secret not configured. Configure at HNG Commerce > Settings > API'
            ], 403);
        }
        
        $provided = $headers['x-hng-signature'][0] ?? '';
        if (empty($provided)) {
            error_log(sprintf('HNG Security: Webhook rejected for %s - missing signature header', $gateway));
            return new WP_REST_Response(['success' => false, 'message' => 'Missing webhook signature'], 401);
        }
        
        $computed = 'sha256=' . hash_hmac('sha256', $body, $secret);
        if (!hash_equals($computed, $provided)) {
            error_log(sprintf('HNG Security: Webhook rejected for %s - signature mismatch', $gateway));
            return new WP_REST_Response(['success' => false, 'message' => 'Invalid webhook signature'], 401);
        }
        
        // Log do webhook recebido
        $this->log_webhook_request($gateway, $body, $headers);
        
        // Disparar action para o gateway específico
        do_action('hng_webhook_received', $gateway, $body, $headers);
        
        // Processar baseado no gateway
        switch ($gateway) {
            case 'asaas':
                return $this->handle_asaas_webhook($body, $headers);
                
            case 'mercadopago':
                return $this->handle_mercadopago_webhook($body, $headers);
                
            case 'pagseguro':
                return $this->handle_pagseguro_webhook($body, $headers);
                
            default:
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Gateway não suportado'
                ], 400);
        }
    }
    
    /**
     * Validate webhook IP against gateway whitelist
     * 
     * @param string $gateway Gateway identifier
     * @param string $ip Client IP address
     * @return bool True if IP is allowed
     */
    private function validate_webhook_ip($gateway, $ip) {
        // Get whitelist for gateway
        $whitelist = $this->get_gateway_ip_whitelist($gateway);
        
        // Whitelist agora é OBRIGATÓRIA para segurança
        if (empty($whitelist)) {
            error_log(sprintf('HNG Security: Webhook IP whitelist not configured for gateway %s', $gateway));
            // Permitir apenas se explicitamente desabilitado via filtro
            if (apply_filters('hng_disable_webhook_ip_check', false)) {
                return true;
            }
            return false;
        }
        
        // Check if IP is in whitelist
        foreach ($whitelist as $allowed_ip) {
            if ($this->ip_matches($ip, $allowed_ip)) {
                return true;
            }
        }
        
        error_log(sprintf('HNG Security: Webhook rejected - IP %s not in whitelist for %s', $ip, $gateway));
        return false;
    }
    
    /**
     * Get IP whitelist for gateway
     * 
     * @param string $gateway Gateway identifier
     * @return array List of allowed IPs/CIDR ranges
     */
    private function get_gateway_ip_whitelist($gateway) {
        // Known gateway IP ranges (can be extended)
        $default_whitelists = [
            'asaas' => [
                '177.12.178.0/24',  // Asaas IPs
                '177.93.160.0/20',
            ],
            'mercadopago' => [
                '209.225.49.0/24',  // Mercado Pago IPs
                '216.33.197.0/24',
                '216.33.196.0/24',
            ],
            'pagseguro' => [
                '186.234.16.0/20',  // PagSeguro IPs
                '200.147.112.0/20',
            ],
        ];
        
        // Allow custom whitelist override
        $custom_whitelist = get_option('hng_webhook_ip_whitelist_' . $gateway, []);
        
        if (!empty($custom_whitelist)) {
            return $custom_whitelist;
        }
        
        return $default_whitelists[$gateway] ?? [];
    }
    
    /**
     * Check if IP matches allowed pattern (supports CIDR)
     * 
     * @param string $ip IP to check
     * @param string $pattern Allowed IP or CIDR range
     * @return bool True if matches
     */
    private function ip_matches($ip, $pattern) {
        // Exact match
        if ($ip === $pattern) {
            return true;
        }
        
        // CIDR notation
        if (strpos($pattern, '/') !== false) {
            list($subnet, $bits) = explode('/', $pattern);
            
            $ip_long = ip2long($ip);
            $subnet_long = ip2long($subnet);
            $mask = -1 << (32 - (int)$bits);
            
            return ($ip_long & $mask) === ($subnet_long & $mask);
        }
        
        return false;
    }
    
    /**
     * Get client IP address
     * 
     * @return string Client IP
     */
    private function get_client_ip() {
        $ip_keys = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                
                // Handle X-Forwarded-For with multiple IPs
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }

    /**
     * Retorna se devemos delegar webhooks ao `_api-server`
     */
    private function should_delegate_to_external() {
        // Uso obrigatório do _api-server: sempre delegar
        return true;
    }
    
    /**
     * Processar webhook do Asaas
     * 
     * @param string $body
     * @param array $headers
     * @return WP_REST_Response
     */
    private function handle_asaas_webhook($body, $headers) {
        $data = json_decode($body, true);
        
        if (!$data) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Invalid JSON'
            ], 400);
        }
        
        // Validar assinatura do Asaas (opcional mas recomendado)
        if (!$this->validate_asaas_signature($body, $headers)) {
            $this->log_error('Asaas webhook signature validation failed');
            // Continuar mesmo assim (Asaas nï¿½o tem assinatura obrigatï¿½ria)
        }
        
        $event = $data['event'] ?? '';
        $payment = $data['payment'] ?? [];
        $payment_id = $payment['id'] ?? '';
        
        if (!$payment_id) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Payment ID not found'
            ], 400);
        }
        
        // Buscar pedido pelo payment_id
        $order_id = $this->find_order_by_payment_id($payment_id, 'asaas');
        
        if (!$order_id) {
            $this->log_error("Order not found for Asaas payment: {$payment_id}");
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }
        
        $order = new HNG_Order($order_id);
        
        // Processar evento
        switch ($event) {
            case 'PAYMENT_RECEIVED':
            case 'PAYMENT_CONFIRMED':
                $this->process_payment_approved($order, $payment, 'asaas');
                break;
                
            case 'PAYMENT_OVERDUE':
                $this->process_payment_overdue($order, $payment);
                break;
                
            case 'PAYMENT_DELETED':
            case 'PAYMENT_REFUNDED':
                $this->process_payment_refunded($order, $payment);
                break;
                
            default:
                $this->log_info("Asaas event ignored: {$event}");
        }
        
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Webhook processed'
        ], 200);
    }
    
    /**
     * Processar webhook do Mercado Pago
     * 
     * @param string $body
     * @param array $headers
     * @return WP_REST_Response
     */
    private function handle_mercadopago_webhook($body, $headers) {
        $data = json_decode($body, true);
        
        if (!$data) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Invalid JSON'
            ], 400);
        }
        
        // Mercado Pago envia notificaï¿½ï¿½o em dois formatos
        $type = $data['type'] ?? '';
        $payment_id = null;
        
        if ($type === 'payment') {
            $payment_id = $data['data']['id'] ?? null;
        }
        
        if (!$payment_id) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Payment ID not found'
            ], 400);
        }
        
        // Buscar detalhes do pagamento na API do Mercado Pago
        $gateway = new HNG_Gateway_MercadoPago();
        $payment_details = $gateway->get_payment($payment_id);
        
        if (is_wp_error($payment_details)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Failed to fetch payment details'
            ], 500);
        }
        
        // Buscar pedido
        $order_id = $this->find_order_by_payment_id($payment_id, 'mercadopago');
        
        if (!$order_id) {
            // Tentar buscar pelo external_reference
            $external_ref = $payment_details['external_reference'] ?? '';
            if ($external_ref) {
                $order_id = intval($external_ref);
            }
        }
        
        if (!$order_id) {
            $this->log_error("Order not found for MercadoPago payment: {$payment_id}");
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }
        
        $order = new HNG_Order($order_id);
        $status = $payment_details['status'] ?? '';
        
        // Processar status
        switch ($status) {
            case 'approved':
                $this->process_payment_approved($order, $payment_details, 'mercadopago');
                break;
                
            case 'rejected':
            case 'cancelled':
                $this->process_payment_failed($order, $payment_details);
                break;
                
            case 'refunded':
            case 'charged_back':
                $this->process_payment_refunded($order, $payment_details);
                break;
                
            default:
                $this->log_info("MercadoPago status ignored: {$status}");
        }
        
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Webhook processed'
        ], 200);
    }
    
    /**
     * Processar webhook do PagSeguro
     * 
     * @param string $body
     * @param array $headers
     * @return WP_REST_Response
     */
    private function handle_pagseguro_webhook($body, $headers) {
        parse_str($body, $data);
        
        $notification_code = $data['notificationCode'] ?? '';
        
        if (!$notification_code) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Notification code not found'
            ], 400);
        }
        
        // Buscar detalhes da notificaï¿½ï¿½o na API do PagSeguro
        $gateway = new HNG_Gateway_PagSeguro();
        $transaction = $gateway->get_notification($notification_code);
        
        if (is_wp_error($transaction)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Failed to fetch transaction'
            ], 500);
        }
        
        $reference = $transaction['reference'] ?? '';
        $order_id = intval($reference);
        
        if (!$order_id) {
            $this->log_error("Order not found for PagSeguro notification: {$notification_code}");
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }
        
        $order = new HNG_Order($order_id);
        $status = $transaction['status'] ?? 0;
        
        // Status do PagSeguro (cï¿½digo numï¿½rico)
        switch ($status) {
            case 3: // Pago
            case 4: // Disponï¿½vel
                $this->process_payment_approved($order, $transaction, 'pagseguro');
                break;
                
            case 6: // Devolvido
            case 7: // Cancelado
                $this->process_payment_refunded($order, $transaction);
                break;
                
            default:
                $this->log_info("PagSeguro status ignored: {$status}");
        }
        
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Webhook processed'
        ], 200);
    }
    
    /**
     * Processar pagamento aprovado
     * 
     * @param HNG_Order $order
     * @param array $payment_data
     * @param string $gateway
     */
    private function process_payment_approved($order, $payment_data, $gateway) {
        // Evitar processar duas vezes
        if ($order->get_status() === 'hng-processing' || $order->get_status() === 'hng-completed') {
            $this->log_info("Order #{$order->get_id()} already processed");
            return;
        }
        
        // Atualizar status
        $order->update_status('hng-processing', __('Pagamento confirmado via webhook.', 'hng-commerce'));
        
        // Registrar transaï¿½ï¿½o
        $this->register_transaction($order, $payment_data, $gateway);
        
        // Enviar email de confirmaï¿½ï¿½o
        do_action('hng_payment_confirmed', $order->get_id(), $payment_data);
        
        // Reduzir estoque
        $this->reduce_stock($order);
        
        $this->log_info("Payment approved for Order #{$order->get_id()} via {$gateway}");
    }
    
    /**
     * Processar pagamento vencido
     * 
     * @param HNG_Order $order
     * @param array $payment_data
     */
    private function process_payment_overdue($order, $payment_data) {
        $order->update_status('hng-on-hold', __('Pagamento vencido.', 'hng-commerce'));
        
        // Notificar cliente
        do_action('hng_payment_overdue', $order->get_id(), $payment_data);
        
        $this->log_info("Payment overdue for Order #{$order->get_id()}");
    }
    
    /**
     * Processar pagamento recusado/cancelado
     * 
     * @param HNG_Order $order
     * @param array $payment_data
     */
    private function process_payment_failed($order, $payment_data) {
        $order->update_status('hng-cancelled', __('Pagamento recusado.', 'hng-commerce'));
        
        // Restaurar estoque
        $this->restore_stock($order);
        
        do_action('hng_payment_failed', $order->get_id(), $payment_data);
        
        $this->log_info("Payment failed for Order #{$order->get_id()}");
    }
    
    /**
     * Processar reembolso
     * 
     * @param HNG_Order $order
     * @param array $payment_data
     */
    private function process_payment_refunded($order, $payment_data) {
        $order->update_status('hng-refunded', __('Pagamento reembolsado.', 'hng-commerce'));
        
        // Restaurar estoque
        $this->restore_stock($order);
        
        do_action('hng_payment_refunded', $order->get_id(), $payment_data);

        // Registrar estorno no ledger
        if (class_exists('HNG_Ledger')) {
            $amount = $order->get_total(); // Estorno integral (ajustar se parcial no futuro)
            HNG_Ledger::add_refund($order->get_id(), $payment_data['id'] ?? '', $amount, [ 'gateway' => $payment_data['gateway'] ?? 'unknown' ]);
        }
        
        $this->log_info("Payment refunded for Order #{$order->get_id()}");
    }
    
    /**
     * Registrar transaï¿½ï¿½o
     * 
     * @param HNG_Order $order
     * @param array $payment_data
     * @param string $gateway
     */
    private function register_transaction($order, $payment_data, $gateway) {
        global $wpdb;
        
        $payment_method = get_post_meta($order->get_post_id(), '_payment_method', true);
        
        // Obter taxas (se foram salvas durante o checkout)
        $plugin_fee = get_post_meta($order->get_post_id(), '_hng_plugin_fee', true);
        $gateway_fee = get_post_meta($order->get_post_id(), '_hng_gateway_fee', true);
        $tier = get_post_meta($order->get_post_id(), '_hng_tier', true);
        $net_amount = get_post_meta($order->get_post_id(), '_hng_net_amount', true);
        $is_fallback = get_post_meta($order->get_post_id(), '_hng_is_fallback', true);
        
        // Se nï¿½o tem taxas salvas, calcular agora
        if (!$plugin_fee) {
            $calculator = HNG_Fee_Calculator::instance();
            $fees = $calculator->calculate_all_fees(
                $order->get_total(),
                'physical',
                $gateway,
                $payment_method
            );
            
            $plugin_fee = $fees['plugin_fee'];
            $gateway_fee = $fees['gateway_fee'];
            $tier = $fees['tier'];
            $net_amount = $fees['net_amount'];
            $is_fallback = true;
        }
        
        // Inserir na tabela de transaï¿½ï¿½es
        if (class_exists('HNG_Ledger')) {
            HNG_Ledger::add_entry([
                'type' => 'charge',
                'order_id' => $order->get_id(),
                'external_ref' => $payment_data['id'] ?? '',
                'gross_amount' => $order->get_total(),
                'fee_amount' => ($plugin_fee + $gateway_fee),
                'net_amount' => $net_amount,
                'status' => 'confirmed',
                'meta' => [
                    'gateway' => $gateway,
                    'payment_method' => $payment_method,
                    'plugin_fee' => $plugin_fee,
                    'gateway_fee' => $gateway_fee,
                    'tier' => $tier,
                    'fallback' => $is_fallback
                ]
            ]);
        }
        
        // Sincronizar com VPS (se configurado)
        if (class_exists('HNG_API_Client')) {
            $api_client = HNG_API_Client::instance();
            $api_client->register_transaction($order->get_post_id(), [
                'amount' => $order->get_total(),
                'gateway' => $gateway,
                'payment_method' => $payment_method,
                'plugin_fee' => $plugin_fee,
                'gateway_fee' => $gateway_fee,
                'net_amount' => $net_amount,
                'tier' => $tier
            ]);
        }
    }
    
    /**
     * Reduzir estoque
     * 
     * @param HNG_Order $order
     */
    private function reduce_stock($order) {
        foreach ($order->get_items() as $item) {
            $product = new HNG_Product($item['product_id']);
            
            if ($product->exists() && $product->get_manage_stock()) {
                $current_stock = $product->get_stock_quantity();
                $new_stock = $current_stock - $item['quantity'];
                $product->set_stock_quantity($new_stock);
            }
        }
    }
    
    /**
     * Restaurar estoque
     * 
     * @param HNG_Order $order
     */
    private function restore_stock($order) {
        foreach ($order->get_items() as $item) {
            $product = new HNG_Product($item['product_id']);
            
            if ($product->exists() && $product->get_manage_stock()) {
                $current_stock = $product->get_stock_quantity();
                $new_stock = $current_stock + $item['quantity'];
                $product->set_stock_quantity($new_stock);
            }
        }
    }
    
    /**
     * Buscar pedido pelo payment_id
     * 
     * @param string $payment_id
     * @param string $gateway
     * @return int|false
     */
    private function find_order_by_payment_id($payment_id, $gateway) {
        global $wpdb;
        
        $meta_key = "_{$gateway}_payment_id";
        
        $order_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
            $meta_key,
            $payment_id
        ));
        
        return $order_id ? intval($order_id) : false;
    }
    
    /**
     * Validar assinatura do Asaas
     * 
     * @param string $body
     * @param array $headers
     * @return bool
     */
    private function validate_asaas_signature($body, $headers) {
        // Asaas usa token de acesso para validaï¿½ï¿½o
        $asaas_token = $headers['asaas_access_token'] ?? '';
        $expected_token = get_option('hng_asaas_webhook_token', '');
        
        if (!$expected_token) {
            return true; // Se nï¿½o configurou token, aceita
        }
        
        return hash_equals($expected_token, $asaas_token);
    }
    
    /**
     * Log de webhook recebido
     * 
     * @param string $gateway
     * @param string $body
     * @param array $headers
     */
    private function log_webhook_request($gateway, $body, $headers) {
        if (get_option('hng_webhook_debug', 'no') !== 'yes') {
            return;
        }
        
        $log_file = WP_CONTENT_DIR . '/hng-webhooks.log';
        
        $log_entry = sprintf(
            "[%s] Gateway: %s\nHeaders: %s\nBody: %s\n---\n",
            gmdate('Y-m-d H:i:s'),
            $gateway,
            wp_json_encode($headers),
            $body
        );
        
        if (function_exists('hng_files_log_put_contents')) {
            hng_files_log_put_contents($log_file, $log_entry);
        }
    }
    
    /**
     * Log de info
     */
    private function log_info($message) {
        // Only log info when debugging enabled (WP_DEBUG or plugin option)
        if ((defined('WP_DEBUG') && WP_DEBUG) || get_option('hng_webhook_debug', 'no') === 'yes') {
            if (function_exists('hng_files_log_append')) { hng_files_log_append(WP_CONTENT_DIR . '/hng-webhooks.log', "HNG Webhook INFO: {$message}" . PHP_EOL); }
        }
    }
    
    /**
     * Log de erro
     */
    private function log_error($message) {
        // Only log errors to file when debugging enabled
        if ((defined('WP_DEBUG') && WP_DEBUG) || get_option('hng_webhook_debug', 'no') === 'yes') {
            if (function_exists('hng_files_log_append')) { hng_files_log_append(WP_CONTENT_DIR . '/hng-webhooks.log', "HNG Webhook ERROR: {$message}" . PHP_EOL); }
        }
    }
    
    /**
     * Action para log customizado
     */
    public function log_webhook($gateway, $body, $headers) {
        do_action('hng_webhook_logged', $gateway, $body, $headers);
    }
}

// Inicializar
HNG_Webhook_Handler::instance();
