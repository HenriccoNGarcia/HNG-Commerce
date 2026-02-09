<?php
/**
 * Subscription Class
 * 
 * Handles recurring subscription products with automatic renewal
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// DB helper
require_once HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php';

class HNG_Subscription {
    
    /**
     * Subscription ID
     */
    private $id;
    
    /**
     * Data da assinatura
     */
    private $data = [];
    
    /**
     * Construtor
     */
    public function __construct($subscription_id = 0) {
        if ($subscription_id) {
            $this->id = $subscription_id;
            $this->load();
        }
    }
    
    /**
     * Carregar assinatura
     */
    private function load() {
        global $wpdb;
        
        $subscriptions_table_full = hng_db_full_table_name('hng_subscriptions');
        $subscriptions_table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_subscriptions') : ('`' . str_replace('`','', $subscriptions_table_full) . '`');
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $subscriptions_table_sql sanitized via hng_db_backtick_table()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom subscriptions table query, load single subscription by ID
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $subscriptions_table_sql sanitized via hng_db_backtick_table()
        $subscription = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$subscriptions_table_sql} WHERE id = %d",
            $this->id
        ), ARRAY_A);
        
        if ($subscription) {
            $this->data = $subscription;
        }
    }
    
    /**
     * Criar nova assinatura
     */
    public static function create($data) {
        global $wpdb;
        
        $defaults = [
            'order_id' => 0,
            'product_id' => 0,
            'customer_email' => '',
            'status' => 'pending',
            'billing_period' => 'monthly',
            'billing_interval' => 1,
            'start_date' => current_time('mysql'),
            'next_billing_date' => gmdate('Y-m-d H:i:s', strtotime('+1 month')),
            'amount' => 0,
            'gateway' => '',
            'gateway_subscription_id' => '',
            'payment_method' => 'credit_card',
            'data_source' => 'local',
            'created_at' => current_time('mysql'),
        ];
        
        $data = wp_parse_args($data, $defaults);
        
        $result = $wpdb->insert(
            hng_db_full_table_name('hng_subscriptions'),
            $data,
            ['%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s']
        );
        
        if ($result) {
            return new self($wpdb->insert_id);
        }
        
        return false;
    }
    
    /**
     * Get subscription ID
     */
    public function get_id() {
        return $this->id;
    }
    
    /**
     * Get customer email
     */
    public function get_customer_email() {
        return $this->data['customer_email'] ?? '';
    }
    
    /**
     * Get status
     */
    public function get_status() {
        return $this->data['status'] ?? 'pending';
    }
    
    /**
     * Get amount
     */
    public function get_amount() {
        return floatval($this->data['amount'] ?? 0);
    }
    
    /**
     * Get billing period
     */
    public function get_billing_period() {
        return $this->data['billing_period'] ?? 'monthly';
    }
    
    /**
     * Get next payment date
     */
    public function get_next_payment_date() {
        return $this->data['next_billing_date'] ?? '';
    }
    
    /**
     * Get gateway subscription ID
     */
    public function get_gateway_subscription_id() {
        return $this->data['gateway_subscription_id'] ?? '';
    }
    
    /**
     * Get gateway name
     */
    public function get_gateway() {
        return $this->data['gateway'] ?? 'asaas';
    }
    
    /**
     * Get original order ID
     */
    public function get_order_id() {
        return absint($this->data['order_id'] ?? 0);
    }
    
    /**
     * Update status
     */
    public function update_status($new_status, $note = '') {
        global $wpdb;
        
        $old_status = $this->get_status();
        
        $wpdb->update(
            hng_db_full_table_name('hng_subscriptions'),
            ['status' => $new_status, 'updated_at' => current_time('mysql')],
            ['id' => $this->id],
            ['%s', '%s'],
            ['%d']
        );
        
        $this->data['status'] = $new_status;
        
        // Log status change
        /* translators: %1$s: old status, %2$s: new status */
        $this->add_note(sprintf(esc_html__('Status alterado de %1$s para %2$s.', 'hng-commerce'),
            $old_status,
            $new_status
        ) . ($note ? ' ' . $note : ''));
        
        // Fire action
        do_action('hng_subscription_status_changed', $this->id, $old_status, $new_status);
    }
    
    /**
     * Update next payment date
     */
    public function update_next_payment_date($date) {
        global $wpdb;
        
        $wpdb->update(
            hng_db_full_table_name('hng_subscriptions'),
            ['next_billing_date' => $date],
            ['id' => $this->id],
            ['%s'],
            ['%d']
        );
        
        $this->data['next_billing_date'] = $date;
    }
    
    /**
     * Add note
     */
    public function add_note($note) {
        global $wpdb;
        
        $wpdb->insert(
            hng_db_full_table_name('hng_subscription_notes'),
            [
                'subscription_id' => $this->id,
                'note' => sanitize_textarea_field($note),
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s']
        );
    }
    
    /**
     * Get notes
     */
    public function get_notes() {
        global $wpdb;
        
        $notes_table = hng_db_full_table_name('hng_subscription_notes');
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = $wpdb->prepare(
            "SELECT * FROM {$notes_table} 
             WHERE subscription_id = %d 
             ORDER BY created_at DESC",
            $this->id
        );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_results($sql, ARRAY_A);
    }
    
    /**
     * Cancel subscription
     */
    public function cancel($immediately = false) {
        if ($immediately) {
            $this->update_status('cancelled', __('Cancelada imediatamente.', 'hng-commerce'));
        } else {
            $this->update_status('pending_cancellation', __('Cancelamento agendado para o fim do perodo atual.', 'hng-commerce'));
        }
        
        // Cancel on gateway
        if ($this->get_gateway_subscription_id()) {
            $gateway = $this->data['gateway'] ?? 'asaas';
            
            if ($gateway === 'asaas' && class_exists('HNG_Gateway_Asaas')) {
                $asaas = new HNG_Gateway_Asaas();
                // Implementar mtodo cancel_subscription no gateway Asaas
            }
        }
        
        do_action('hng_subscription_cancelled', $this->id, $immediately);
    }
    
    /**
     * Pause subscription
     */
    public function pause() {
        $this->update_status('on_hold', __('Assinatura pausada.', 'hng-commerce'));
        do_action('hng_subscription_paused', $this->id);
    }
    
    /**
     * Resume subscription
     */
    public function resume() {
        $this->update_status('active', __('Assinatura reativada.', 'hng-commerce'));
        do_action('hng_subscription_resumed', $this->id);
    }
    
    /**
     * Process renewal
     */
    public function process_renewal() {
        $payment_method = $this->data['payment_method'] ?? 'credit_card';
        
        // Criar dados base do pedido de renovao
        $order_data = [
            'customer_email' => $this->get_customer_email(),
            'total' => $this->get_amount(),
            'status' => 'pending',
            'subscription_id' => $this->id,
            'type' => 'renewal',
            'payment_method' => $payment_method,
        ];
        
        // Distinguir entre renovao automtica (carto) e manual (PIX/Boleto)
        if (in_array($payment_method, ['credit_card', 'debit_card'])) {
            // RENOVAO AUTOMTICA: Gateway cobra automaticamente
            do_action('hng_subscription_renewal_payment', $this->id, $order_data);
            $this->add_note(__('Renovao automtica processada via gateway.', 'hng-commerce'));
            
        } else {
            // RENOVAO MANUAL (PIX/Boleto): Criar pedido e notificar cliente
            $order_id = $this->create_renewal_order($order_data);
            
            if ($order_id) {
                // Disparar ao para gateway gerar novo PIX/Boleto
                do_action('hng_subscription_manual_renewal', $this->id, $order_id, $payment_method);
                
                // Enviar email com novo PIX/Boleto
                $this->notify_manual_renewal($order_id);
                
                // Atualizar status: aguardando pagamento
                $this->update_status('pending_renewal', __('Aguardando pagamento da renovao.', 'hng-commerce'));
                /* translators: %1$s: payment method, %2$d: order id */
                $this->add_note(sprintf(esc_html__('Renovao manual: novo %1$s gerado. Pedido #%2$d', 'hng-commerce'),
                    $payment_method === 'pix' ? 'PIX' : 'Boleto',
                    $order_id
                ));
            } else {
                $this->add_note(__('Erro ao criar pedido de renovao manual.', 'hng-commerce'));
                return;
            }
        }
        
        // Atualizar prxima data de pagamento (independente do tipo)
        $next_date = $this->calculate_next_date();
        $this->update_next_payment_date(gmdate('Y-m-d H:i:s', $next_date));
    }
    
    /**
     * Calcular prxima data de pagamento
     */
    private function calculate_next_date() {
        $billing_period = $this->get_billing_period();
        $interval = $this->data['billing_interval'] ?? 1;
        $next_date = strtotime($this->get_next_payment_date());
        
        switch ($billing_period) {
            case 'daily':
                return strtotime("+{$interval} days", $next_date);
            case 'weekly':
                return strtotime("+{$interval} weeks", $next_date);
            case 'monthly':
                return strtotime("+{$interval} months", $next_date);
            case 'yearly':
                return strtotime("+{$interval} years", $next_date);
            default:
                return strtotime('+1 month', $next_date);
        }
    }
    
    /**
     * Criar pedido de renovao manual
     */
    private function create_renewal_order($order_data) {
        // Buscar produto
        $product = new HNG_Product($this->data['product_id']);
        if (!$product) {
            return false;
        }
        
        // Criar novo pedido
        $order_id = wp_insert_post([
            'post_type' => 'hng_order',
            /* translators: %d: subscription id */
            'post_title' => sprintf(esc_html__('Renovao Assinatura #%d', 'hng-commerce'), $this->id),
            'post_status' => 'hng-pending',
            'post_author' => 0,
        ]);
        
        if (!$order_id || is_wp_error($order_id)) {
            return false;
        }
        
        // Meta dados do pedido
        update_post_meta($order_id, '_customer_email', $order_data['customer_email']);
        update_post_meta($order_id, '_total', $this->get_amount());
        update_post_meta($order_id, '_payment_method', $order_data['payment_method']);
        update_post_meta($order_id, '_gateway', $this->data['gateway'] ?? 'asaas');
        update_post_meta($order_id, '_subscription_id', $this->id);
        update_post_meta($order_id, '_is_renewal', 'yes');
        update_post_meta($order_id, '_created_date', current_time('mysql'));
        update_post_meta($order_id, '_order_status', 'pending');
        
        // Itens do pedido
        update_post_meta($order_id, '_order_items', [
            [
                'product_id' => $this->data['product_id'],
                'product_name' => $product->get_name(),
                'quantity' => 1,
                'price' => $this->get_amount(),
            ]
        ]);
        
        // Dados do cliente (se existir usurio)
        $user = get_user_by('email', $order_data['customer_email']);
        if ($user) {
            update_post_meta($order_id, '_customer_id', $user->ID);
            update_post_meta($order_id, '_billing_first_name', $user->first_name);
            update_post_meta($order_id, '_billing_last_name', $user->last_name);
        }
        
        return $order_id;
    }
    
    /**
     * Notificar cliente sobre renovao manual
     */
    private function notify_manual_renewal($order_id) {
        $customer_email = $this->get_customer_email();
        $payment_method = $this->data['payment_method'];
        
        // Buscar dados de pagamento do pedido (gerados pelo gateway)
        $payment_data = get_post_meta($order_id, '_payment_data', true);
        $payment_url = get_post_meta($order_id, '_payment_url', true);
        
        if (empty($payment_data) && empty($payment_url)) {
            // Aguardar gateway gerar dados (pode levar alguns segundos)
            sleep(2);
            $payment_data = get_post_meta($order_id, '_payment_data', true);
            $payment_url = get_post_meta($order_id, '_payment_url', true);
        }
        
        // Montar email
        /* translators: %1$s: site name, %2$d: subscription id */
        $subject = sprintf(esc_html__('[%1$s] Renovao da sua assinatura #%2$d', 'hng-commerce'),
            get_bloginfo('name'),
            $this->id
        );
        
        /* translators: %d: subscription id */
        $message = sprintf(esc_html__('Ol!\n\nSua assinatura #%d precisa ser renovada.\n\n', 'hng-commerce'),
            $this->id
        );
        
        if ($payment_method === 'pix') {
            $message .= __('Mtodo de pagamento: PIX\n\n', 'hng-commerce');
            
            if (!empty($payment_data['qr_code'])) {
                $message .= __('Copie o cdigo PIX abaixo:\n', 'hng-commerce');
                $message .= $payment_data['qr_code'] . "\n\n";
            }
            
            if (!empty($payment_url)) {
                $message .= __('Ou acesse o link para visualizar o QR Code:\n', 'hng-commerce');
                $message .= $payment_url . "\n\n";
            }
            
        } elseif ($payment_method === 'boleto') {
            $message .= __('Mtodo de pagamento: Boleto Bancrio\n\n', 'hng-commerce');
            
            if (!empty($payment_data['boleto_url'])) {
                $message .= __('Acesse o link abaixo para imprimir seu boleto:\n', 'hng-commerce');
                $message .= $payment_data['boleto_url'] . "\n\n";
            } elseif (!empty($payment_url)) {
                $message .= __('Acesse o link abaixo para visualizar seu boleto:\n', 'hng-commerce');
                $message .= $payment_url . "\n\n";
            }
            
            if (!empty($payment_data['due_date'])) {
                /* translators: %s: due date */
                $message .= sprintf(esc_html__('Vencimento: %s\n\n', 'hng-commerce'),
                    date_i18n('d/m/Y', strtotime($payment_data['due_date']))
                );
            }
        }
        
        /* translators: %s: amount */
        $message .= sprintf(esc_html__('Valor: %s\n\n', 'hng-commerce'),
            hng_price($this->get_amount())
        );
        
        /* translators: %d: order id */
        $message .= sprintf(esc_html__('Pedido: #%d\n', 'hng-commerce'),
            $order_id
        );
        
        $message .= "\n" . __('Obrigado por sua preferncia!\n', 'hng-commerce');
        $message .= get_bloginfo('name');
        
        // Enviar email
        $sent = wp_mail($customer_email, $subject, $message);
        
        // Hook para integraes (WhatsApp, SMS, etc)
        do_action('hng_subscription_renewal_notification_sent', $this->id, $order_id, $customer_email, $sent);
        
        return $sent;
    }
    
    /**
     * Get customer subscriptions
     */
    public static function get_customer_subscriptions($customer_email, $status = 'any') {
        global $wpdb;
        $subscriptions_table = hng_db_full_table_name('hng_subscriptions');
        $sql = "SELECT * FROM {$subscriptions_table} WHERE customer_email = %s";
        $params = [$customer_email];

        if ($status !== 'any') {
            $sql .= " AND status = %s";
            $params[] = $status;
        }

        $sql .= ' ORDER BY created_at DESC';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $prepared = $wpdb->prepare($sql, ...$params);

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_results($prepared, ARRAY_A);
    }
    
    /**
     * Check for due renewals (cron job)
     */
    public static function check_due_renewals() {
        global $wpdb;
        
        $subscriptions_table = hng_db_full_table_name('hng_subscriptions');
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $due_subscriptions = $wpdb->get_results(
            "SELECT id FROM {$subscriptions_table} 
             WHERE status = 'active' 
             AND next_billing_date <= NOW()
             AND next_billing_date > DATE_SUB(NOW(), INTERVAL 1 DAY)",
            ARRAY_A
        );
        
        foreach ($due_subscriptions as $sub) {
            $subscription = new self($sub['id']);
            $subscription->process_renewal();
        }
    }
}
