<?php
/**
 * HNG Commerce - Asaas Webhooks Handler
 *
 * Handles incoming webhooks from Asaas.
 *
 * @package HNG_Commerce
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Asaas_Webhooks_Handler {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        register_rest_route('hng-commerce/v1', '/asaas/webhook', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_webhook'],
            'permission_callback' => '__return_true', // Validation is done inside
        ]);
    }

    /**
     * Handle Webhook Request
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_webhook($request) {
        // Se a ingestão estiver delegada ao `_api-server`, ignorar aqui.
        if ($this->should_delegate_to_external()) {
            return new WP_REST_Response(['error' => 'Webhook handled by external _api-server'], 410);
        }

        $headers = $request->get_headers();
        $body = $request->get_json_params();
        
        // Verify Token (if configured)
        $webhook_token = get_option('hng_asaas_webhook_token');
        $received_token = $headers['asaas_access_token'][0] ?? '';

        if ($webhook_token && $received_token !== $webhook_token) {
            error_log('[HNG Asaas] Webhook token mismatch');
            return new WP_REST_Response(['error' => 'Unauthorized'], 401);
        }

        if (empty($body) || !isset($body['event'])) {
            return new WP_REST_Response(['error' => 'Invalid payload'], 400);
        }

        $event = $body['event'];
        $payment = $body['payment'] ?? [];

        // Log the event (pass headers so we can persist event_id when provided)
        $this->log_event($event, $body, $headers);

        // Debug log to trace incoming webhooks
        $payment_id = isset($payment['id']) ? $payment['id'] : '';
        error_log('[HNG Asaas] Webhook received: event=' . $event . ' payment_id=' . $payment_id);

        // Process Event
        switch ($event) {
            case 'PAYMENT_RECEIVED':
            case 'PAYMENT_CONFIRMED':
                $this->process_payment_received($payment);
                break;
            case 'PAYMENT_OVERDUE':
                $this->process_payment_overdue($payment);
                break;
            case 'PAYMENT_REFUNDED':
                $this->process_payment_refunded($payment);
                break;
            // Add more events as needed
        }

        do_action('hng_asaas_webhook_received', $event, $body);

        return new WP_REST_Response(['status' => 'success'], 200);
    }

    /**
     * Log event to database
     */
    private function log_event($event, $payload, $headers = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'hng_asaas_webhook_log';
        
        // Check if table exists (it should, created by advanced integration)
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via $wpdb->prefix
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            return;
        }

        // If header contains x-event-id or payload contains an identifiable id, ensure it's present in stored payload
        $event_id = '';
        if (!empty($headers['x-event-id'][0])) {
            $event_id = $headers['x-event-id'][0];
        } elseif (!empty($payload['event_id'])) {
            $event_id = $payload['event_id'];
        } elseif (!empty($payload['payment']['id'])) {
            $event_id = $payload['payment']['id'];
        }

        if ($event_id && empty($payload['event_id'])) {
            $payload['event_id'] = $event_id;
        }

        $wpdb->insert($table, [
            'event_type' => $event,
            'payload' => wp_json_encode($payload),
            'created_at' => current_time('mysql')
        ]);
    }

    /**
     * Process Payment Received
     */
    private function process_payment_received($payment) {
        if (empty($payment['id'])) return;

        // Find order by Asaas Payment ID
        $order_id = $this->get_order_id_by_payment_id($payment['id']);

        if ($order_id) {
            $order = new HNG_Order($order_id);

            $current_status = $order->get_status();
            $already_paid_statuses = ['hng-completed', 'hng-processing', 'completed', 'processing'];

            if (!in_array($current_status, $already_paid_statuses, true)) {
                $order->update_status('hng-processing', __('Pagamento confirmado via Asaas Webhook.', 'hng-commerce'));
            }

            if (!empty($payment['id'])) {
                /* translators: %1$s: payment ID */
                HNG_Order::add_order_note($order_id, sprintf(__('Pagamento recebido via Asaas Webhook (ID: %1$s)', 'hng-commerce'), $payment['id']));
            }

            error_log('[HNG Asaas] Payment received processed for order ' . $order_id . ' status=' . $order->get_status());
        }
        
        // Also check subscriptions
        if (isset($payment['subscription'])) {
            $this->process_subscription_payment($payment['subscription'], $payment);
        }
    }

    /**
     * Process Payment Overdue
     */
    private function process_payment_overdue($payment) {
        if (empty($payment['id'])) return;

        $order_id = $this->get_order_id_by_payment_id($payment['id']);
        if ($order_id) {
            $order = new HNG_Order($order_id);
            $current_status = $order->get_status();
            if (in_array($current_status, ['hng-pending', 'pending'], true)) {
                $order->update_status('hng-cancelled', __('Pagamento vencido (Asaas Webhook).', 'hng-commerce'));
            }
        }
    }

    /**
     * Process Payment Refunded
     */
    private function process_payment_refunded($payment) {
        if (empty($payment['id'])) return;

        $order_id = $this->get_order_id_by_payment_id($payment['id']);
        if ($order_id) {
            $order = new HNG_Order($order_id);
            $order->update_status('hng-refunded', __('Pagamento estornado (Asaas Webhook).', 'hng-commerce'));
        }
    }

    /**
     * Process Subscription Payment
     */
    private function process_subscription_payment($subscription_id, $payment) {
        // Update local subscription status/dates
        global $wpdb;
        $table = $wpdb->prefix . 'hng_subscriptions';
        
        // Primeiro, tentar localizar pela coluna específica criada pela integração avançada
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix is safe
        $subscription = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE asaas_subscription_id = %s",
            $subscription_id
        ));

        // Fallback: se não encontrado, tentar por gateway + gateway_subscription_id (compatibilidade)
        if (!$subscription) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix is safe
            $subscription = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE gateway = %s AND gateway_subscription_id = %s",
                'asaas',
                $subscription_id
            ));
        }

        if ($subscription) {
            // Atualizar status para ativo após pagamento
            if ($subscription->status !== 'active') {
                $wpdb->update($table, ['status' => 'active'], ['id' => $subscription->id]);
            }

            // Opcional: futuras melhorias podem sincronizar próxima data com a API Asaas
            if (class_exists('HNG_Asaas_Sync')) {
                // Mantemos dependência mínima; sincronização completa ocorre via cron/importadores
            }
        }
    }

    /**
     * Get Order ID by Asaas Payment ID
     */
    private function get_order_id_by_payment_id($payment_id) {
        global $wpdb;
        
        // Check post meta
        $order_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_asaas_payment_id' AND meta_value = %s",
            $payment_id
        ));

        return $order_id;
    }

    /**
     * Retorna se devemos delegar webhooks ao `_api-server`
     */
    private function should_delegate_to_external() {
        // Desabilitado: processar webhooks diretamente neste site
        return false;
    }
}
