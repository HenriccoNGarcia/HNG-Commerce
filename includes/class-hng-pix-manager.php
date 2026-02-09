<?php
/**
 * HNG Pix Manager - MVP
 */
if (!defined('ABSPATH')) { exit; }

interface HNG_Pix_Provider_Interface {
    public function create_charge($order_id, $amount, $customer_data, $metadata = []);
    public function get_status($charge_id);
    public function cancel_charge($charge_id);
    public function supports_partial_refund();
    public function refund($charge_id, $amount = null);
}

class HNG_Pix_Manager {
    const META_CHARGE_ID = '_hng_pix_charge_id';
    const META_EXPIRES_AT = '_hng_pix_expires_at';
    const META_STATUS_HISTORY = '_hng_pix_status_history';

    /** Retorna provedor PIX configurado */
    public static function get_active_provider() {
        return get_option('hng_pix_provider', 'asaas');
    }

    /** Atualiza contador de mï¿½tricas PIX */
    private static function bump_metric($key) {
        $metrics = get_option('hng_pix_metrics', []);
        if (!is_array($metrics)) { $metrics = []; }
        $metrics[$key] = isset($metrics[$key]) ? ($metrics[$key] + 1) : 1;
        $metrics['updated_at'] = time();
        update_option('hng_pix_metrics', $metrics, false);
        return $metrics;
    }

    public static function init() {
        // Conectar filtro ï¿½ opï¿½ï¿½o salva
        add_filter('hng_pix_active_provider', [__CLASS__, 'get_active_provider']);
        
        add_action('wp_ajax_hng_pix_init', [__CLASS__, 'ajax_init']);
        add_action('wp_ajax_nopriv_hng_pix_init', [__CLASS__, 'ajax_init']);
        add_action('wp_ajax_hng_pix_poll', [__CLASS__, 'ajax_poll']);
        add_action('wp_ajax_nopriv_hng_pix_poll', [__CLASS__, 'ajax_poll']);
        add_action('wp_ajax_hng_pix_regenerate', [__CLASS__, 'ajax_regenerate']);
        add_action('wp_ajax_nopriv_hng_pix_regenerate', [__CLASS__, 'ajax_regenerate']);
        // Cron de reconciliaï¿½ï¿½o (a cada 10min)
        add_action('hng_pix_reconcile_event', [__CLASS__, 'reconcile_pending']);
        if (!wp_next_scheduled('hng_pix_reconcile_event')) {
            wp_schedule_event(time() + 300, 'ten_minutes', 'hng_pix_reconcile_event');
        }
        // Registrar intervalo custom se ainda nï¿½o existir
            add_filter('cron_schedules', function($schedules){
            if (!isset($schedules['ten_minutes'])) {
                $schedules['ten_minutes'] = [ 'interval' => 600, 'display' => __('A cada 10 minutos', 'hng-commerce') ];
            }
            return $schedules;
        });
    }

    /** Inicia cobranï¿½a PIX para um pedido */
    public static function init_charge($order_id) {
        $order_id = absint($order_id);
        if (!$order_id) { return new WP_Error('pix_invalid_order', 'Pedido invï¿½lido'); }
        if (!class_exists('HNG_Order')) { return new WP_Error('pix_missing_order_class', 'Classe HNG_Order ausente'); }
        $order = new HNG_Order($order_id);
        $amount = (float) $order->get_total();
        if ($amount <= 0) { return new WP_Error('pix_zero_amount', 'Valor do pedido invï¿½lido'); }
        $provider_id = apply_filters('hng_pix_active_provider', 'asaas');
        $provider = self::get_provider($provider_id);
        if (!$provider) { return new WP_Error('pix_provider_unavailable', 'Provider PIX indisponï¿½vel'); }
        $customer = self::extract_customer($order);
        $payload = $provider->create_charge($order_id, $amount, $customer);
        if (is_wp_error($payload)) { return $payload; }
        update_post_meta($order_id, self::META_CHARGE_ID, $payload['charge_id']);
        update_post_meta($order_id, self::META_EXPIRES_AT, $payload['expires_at']);
        self::append_status_history($order_id, 'created');
        if (class_exists('HNG_Ledger')) {
            HNG_Ledger::add_entry([
                'order_id' => $order_id,
                'type' => 'charge',
                'method' => 'pix',
                'status' => 'pending',
                'payment_reference' => $payload['charge_id'],
                'gross_amount' => $amount,
                'fee_amount' => $payload['fee']['total'] ?? 0,
                'net_amount' => $payload['net_amount'] ?? ($amount - ($payload['fee']['total'] ?? 0))
            ]);
        }
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $metrics = self::bump_metric('charge_init');
            $entry = wp_json_encode([
                'event' => 'charge_init',
                'order_id' => $order_id,
                'charge_id' => $payload['charge_id'],
                'expires_at' => $payload['expires_at'],
                'metrics' => $metrics
            ]);
            if (function_exists('hng_files_log_append')) { hng_files_log_append(HNG_COMMERCE_PATH . 'logs/pix.log', '[HNG PIX] ' . $entry . PHP_EOL); }
        }
        return $payload;
    }

    public static function get_provider($provider_id) {
        if ($provider_id === 'asaas' && class_exists('HNG_Gateway_Asaas')) {
            return new HNG_Pix_Asaas_Adapter(new HNG_Gateway_Asaas());
        }
        if ($provider_id === 'pagseguro' && class_exists('HNG_Gateway_PagSeguro')) {
            return new HNG_Pix_PagSeguro_Adapter(new HNG_Gateway_PagSeguro());
        }
        if ($provider_id === 'cielo' && class_exists('HNG_Gateway_Cielo')) {
            return new HNG_Pix_Cielo_Adapter(new HNG_Gateway_Cielo());
        }
        return apply_filters('hng_pix_get_provider', null, $provider_id);
    }

    private static function extract_customer($order) {
        return [
            'name' => $order->get_customer_name(),
            'email' => $order->get_customer_email(),
            'cpf' => preg_replace('/[^0-9]/', '', $order->get_meta('_billing_cpf')),
        ];
    }

    public static function normalize_status($raw) {
        $map = [ 'PENDING' => 'created', 'RECEIVED' => 'paid', 'CONFIRMED' => 'paid', 'OVERDUE' => 'expired', 'REFUNDED' => 'refunded' ];
        return isset($map[$raw]) ? $map[$raw] : strtolower((string) $raw);
    }

    public static function append_status_history($order_id, $status) {
        $history = get_post_meta($order_id, self::META_STATUS_HISTORY, true);
        if (!is_array($history)) { $history = []; }
        $history[] = [ 'status' => $status, 'ts' => time() ];
        update_post_meta($order_id, self::META_STATUS_HISTORY, $history);
    }

    /** AJAX criar cobranï¿½a */
    public static function ajax_init() {
        check_ajax_referer('HNG Commerce', 'nonce');
        $post = function_exists('wp_unslash') ? wp_unslash( $_POST ) : $_POST;
        $order_id = absint( $post['order_id'] ?? 0 );
        $result = self::init_charge($order_id);
        if (is_wp_error($result)) {
            wp_send_json_error([ 'code' => $result->get_error_code(), 'message' => $result->get_error_message() ]);
        }
        wp_send_json_success($result);
    }

    /** AJAX polling status */
    public static function ajax_poll() {
        check_ajax_referer('HNG Commerce', 'nonce');
        $post = function_exists('wp_unslash') ? wp_unslash( $_POST ) : $_POST;
        $order_id = absint( $post['order_id'] ?? 0 );
        $charge_id = get_post_meta($order_id, self::META_CHARGE_ID, true);
        if (!$charge_id) { wp_send_json_error(['code' => 'pix_no_charge', 'message' => 'Cobranï¿½a inexistente']); }
        $provider_id = apply_filters('hng_pix_active_provider', 'asaas');
        $provider = self::get_provider($provider_id);
        if (!$provider) { wp_send_json_error(['code' => 'pix_provider_unavailable', 'message' => 'Provider indisponï¿½vel']); }
        $status_raw = $provider->get_status($charge_id);
        if (is_wp_error($status_raw)) { wp_send_json_error(['code' => $status_raw->get_error_code(), 'message' => $status_raw->get_error_message()]); }
        $normalized = self::normalize_status($status_raw['status']);
        if ($normalized !== 'created') { self::append_status_history($order_id, $normalized); }
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $metricKey = ($normalized === 'paid') ? 'paid' : (($normalized === 'expired') ? 'expired' : 'poll');
            $metrics = self::bump_metric($metricKey);
            $entry = wp_json_encode([
                'event' => 'poll',
                'order_id' => $order_id,
                'status' => $normalized,
                'raw' => $status_raw['status'] ?? '',
                'metrics' => $metrics
            ]);
            if (function_exists('hng_files_log_append')) { hng_files_log_append(HNG_COMMERCE_PATH . 'logs/pix.log', '[HNG PIX] ' . $entry . PHP_EOL); }
        }
        wp_send_json_success([
            'status' => $normalized,
            'raw' => $status_raw,
            'expires_at' => (int) get_post_meta($order_id, self::META_EXPIRES_AT, true)
        ]);
    }

    /** AJAX regenerar cobranï¿½a expirada */
    public static function ajax_regenerate() {
        check_ajax_referer('HNG Commerce', 'nonce');
        $post = function_exists('wp_unslash') ? wp_unslash( $_POST ) : $_POST;
        $order_id = absint( $post['order_id'] ?? 0 );
        $charge_id = get_post_meta($order_id, self::META_CHARGE_ID, true);
        $expires_at = (int) get_post_meta($order_id, self::META_EXPIRES_AT, true);
        $now = time();
        if ($expires_at && $expires_at > $now) {
            wp_send_json_error(['code' => 'pix_not_expired', 'message' => 'Cobranï¿½a ainda vï¿½lida']);
        }
        // Cancelar anterior (melhor esforï¿½o)
        $provider_id = apply_filters('hng_pix_active_provider', 'asaas');
        $provider = self::get_provider($provider_id);
        if ($provider && $charge_id) { $provider->cancel_charge($charge_id); }
        $new = self::init_charge($order_id);
        if (is_wp_error($new)) { wp_send_json_error(['code' => $new->get_error_code(), 'message' => $new->get_error_message()]); }
        // Ledger entrada de regeneraï¿½ï¿½o
        if (class_exists('HNG_Ledger')) {
            HNG_Ledger::add_entry([
                'type' => 'charge_regeneration',
                'order_id' => $order_id,
                'external_ref' => $new['charge_id'],
                'gross_amount' => 0,
                'fee_amount' => 0,
                'net_amount' => 0,
                'status' => 'confirmed',
                'meta' => [ 'previous' => $charge_id ]
            ]);
        }
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $metrics = self::bump_metric('regenerate');
            $entry = wp_json_encode([
                'event' => 'regenerate',
                'order_id' => $order_id,
                'old_charge' => $charge_id,
                'new_charge' => $new['charge_id'],
                'metrics' => $metrics
            ]);
            if (function_exists('hng_files_log_append')) { 
                hng_files_log_append(HNG_COMMERCE_PATH . 'logs/pix.log', '[HNG PIX] ' . $entry . PHP_EOL);
            }
        }
        wp_send_json_success($new);
    }

    /** Reconciliaï¿½ï¿½o de cobranï¿½as pendentes (cron) */
    public static function reconcile_pending() {
        global $wpdb;
        if (!class_exists('HNG_Ledger')) { return; }
        $table_full = HNG_Ledger::get_table_name();
        $table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_ledger') : ('`' . str_replace('`','', $table_full) . '`');
        // Selecionar pendentes há mais de 30min
        $threshold = gmdate('Y-m-d H:i:s', time() - 1800);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via hng_db_backtick_table()
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table_sql} WHERE method = 'pix' AND status = 'pending' AND created_at < %s", $threshold), ARRAY_A);
        if (!$rows) { return; }
        $provider_id = apply_filters('hng_pix_active_provider', 'asaas');
        $provider = self::get_provider($provider_id);
        if (!$provider) { return; }
        foreach ($rows as $r) {
            $ref = $r['external_ref'];
            $status_raw = $provider->get_status($ref);
            if (is_wp_error($status_raw)) { continue; }
            $normalized = self::normalize_status($status_raw['status']);
            if ($normalized === 'paid') {
                HNG_Ledger::update_status($r['id'], 'confirmed');
                self::append_status_history($r['order_id'], 'paid');
                if (defined('WP_DEBUG') && WP_DEBUG) { 
                    $metrics = self::bump_metric('reconcile_paid');
                    $entry = wp_json_encode(['event'=>'reconcile_paid','order_id'=>$r['order_id'],'ref'=>$ref,'metrics'=>$metrics]);
                    if (function_exists('hng_files_log_append')) { hng_files_log_append(HNG_COMMERCE_PATH . 'logs/pix.log', '[HNG PIX] ' . $entry . PHP_EOL); }
                }
            } elseif ($normalized === 'expired') {
                HNG_Ledger::update_status($r['id'], 'failed');
                self::append_status_history($r['order_id'], 'expired');
                if (defined('WP_DEBUG') && WP_DEBUG) { 
                    $metrics = self::bump_metric('reconcile_expired');
                    $entry = wp_json_encode(['event'=>'reconcile_expired','order_id'=>$r['order_id'],'ref'=>$ref,'metrics'=>$metrics]);
                    if (function_exists('hng_files_log_append')) { hng_files_log_append(HNG_COMMERCE_PATH . 'logs/pix.log', '[HNG PIX] ' . $entry . PHP_EOL); }
                }
            }
        }
    }
}

/** Adapter Asaas (traduz para interface) */
class HNG_Pix_Asaas_Adapter implements HNG_Pix_Provider_Interface {
    private $gateway;
    public function __construct($gateway) { $this->gateway = $gateway; }
    public function create_charge($order_id, $amount, $customer_data, $metadata = []) {
        $payment_data = [ 'cpf' => $customer_data['cpf'] ];
        $resp = $this->gateway->create_pix_payment($order_id, $payment_data);
        if (is_wp_error($resp)) { return $resp; }
        return [
            'charge_id' => $resp['id'],
            'qr_code' => $resp['encodedImage'] ?? '',
            'copy_paste' => $resp['payload'] ?? '',
            'expires_at' => isset($resp['expirationDate']) ? strtotime($resp['expirationDate']) : (time() + 3600),
            'fee' => [ 'total' => $resp['fee_total'] ?? 0 ],
            'net_amount' => $resp['net_amount'] ?? null,
            'raw' => $resp
        ];
    }
    public function get_status($charge_id) {
        return $this->gateway->get_pix_status($charge_id);
    }
    public function cancel_charge($charge_id) {
        if (method_exists($this->gateway, 'cancel_pix')) { return $this->gateway->cancel_pix($charge_id); }
        return new WP_Error('pix_cancel_not_supported', 'Cancelamento nï¿½o suportado');
    }
    public function supports_partial_refund() { return true; }
    public function refund($charge_id, $amount = null) { return $this->gateway->refund_pix($charge_id, $amount); }
}

/** Adapter PagSeguro */
class HNG_Pix_PagSeguro_Adapter implements HNG_Pix_Provider_Interface {
    private $gateway;
    public function __construct($gateway) { $this->gateway = $gateway; }
    public function create_charge($order_id, $amount, $customer_data, $metadata = []) {
        $payment_data = [ 'cpf' => $customer_data['cpf'] ];
        $resp = $this->gateway->create_pix_payment($order_id, $payment_data);
        if (is_wp_error($resp)) { return $resp; }
        return [
            'charge_id' => $resp['id'],
            'qr_code' => $resp['encodedImage'] ?? '',
            'copy_paste' => $resp['payload'] ?? '',
            'expires_at' => isset($resp['expirationDate']) ? strtotime($resp['expirationDate']) : (time() + 3600),
            'fee' => [ 'total' => $resp['fee_total'] ?? 0 ],
            'net_amount' => $resp['net_amount'] ?? null,
            'raw' => $resp
        ];
    }
    public function get_status($charge_id) {
        return $this->gateway->get_pix_status($charge_id);
    }
    public function cancel_charge($charge_id) {
        if (method_exists($this->gateway, 'cancel_pix')) { return $this->gateway->cancel_pix($charge_id); }
        return new WP_Error('pix_cancel_not_supported', 'Cancelamento nï¿½o suportado');
    }
    public function supports_partial_refund() { return true; }
    public function refund($charge_id, $amount = null) { return $this->gateway->refund_pix($charge_id, $amount); }
}

/**
 * Adapter PIX Cielo
 */
class HNG_Pix_Cielo_Adapter implements HNG_Pix_Provider_Interface {
    private $gateway;
    public function __construct($gateway) { $this->gateway = $gateway; }
    public function create_charge($order_id, $amount, $customer_data, $metadata = []) {
        $payment_data = [ 'cpf' => $customer_data['cpf'] ];
        $resp = $this->gateway->create_pix_payment($order_id, $payment_data);
        if (is_wp_error($resp)) { return $resp; }
        return [
            'charge_id' => $resp['id'],
            'qr_code' => $resp['encodedImage'] ?? '',
            'copy_paste' => $resp['payload'] ?? '',
            'expires_at' => isset($resp['expirationDate']) ? strtotime($resp['expirationDate']) : (time() + 3600),
            'fee' => [ 'total' => $resp['fee_total'] ?? 0 ],
            'net_amount' => $resp['net_amount'] ?? null,
            'raw' => $resp
        ];
    }
    public function get_status($charge_id) {
        return $this->gateway->get_pix_status($charge_id);
    }
    public function cancel_charge($charge_id) {
        if (method_exists($this->gateway, 'cancel_pix')) { return $this->gateway->cancel_pix($charge_id); }
        return new WP_Error('pix_cancel_not_supported', 'Cancelamento nï¿½o suportado');
    }
    public function supports_partial_refund() { return true; }
    public function refund($charge_id, $amount = null) { return $this->gateway->refund_pix($charge_id, $amount); }
}

HNG_Pix_Manager::init();
