<?php
/**
 * PIX Installments (clean, consistent implementation)
 *
 * Provides helper methods to manage PIX installment plans securely.
 * Uses prepared queries for values and a small helper to resolve table names.
 *
 * @package HNG_Commerce
 */

if (!defined('ABSPATH')) {
    exit;
}

// Optional DB helper
if (file_exists(HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php')) {
    require_once HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php';
}

class HNG_PIX_Installment {

    public static function is_enabled() {
        return get_option('hng_pix_installment_enabled', 'no') === 'yes';
    }

    public static function product_accepts_installment($product_id) {
        if (!self::is_enabled()) {
            return false;
        }

        return get_post_meta($product_id, '_pix_installment_enabled', true) === 'yes';
    }

    public static function get_product_max_installments($product_id) {
        $max = get_post_meta($product_id, '_pix_installment_max', true);
        return $max ? absint($max) : 12;
    }

    public static function get_global_max_installments() {
        return absint(get_option('hng_pix_installment_max', 12));
    }

    public static function get_installment_fee() {
        return floatval(get_option('hng_pix_installment_fee', 0));
    }

    public static function get_plugin_fee() {
        if (!class_exists('HNG_Fee_Manager')) {
            if (file_exists(HNG_COMMERCE_PATH . 'includes/financial/class-fee-manager.php')) {
                require_once HNG_COMMERCE_PATH . 'includes/financial/class-fee-manager.php';
            }
        }

        if (class_exists('HNG_Fee_Manager')) {
            return HNG_Fee_Manager::get_current_fee();
        }

        return 0;
    }

    public static function has_custom_installment_price($product_id) {
        return get_post_meta($product_id, '_pix_installment_custom_price_enabled', true) === 'yes';
    }

    public static function get_installment_price($product_id, $regular_price) {
        if (!self::has_custom_installment_price($product_id)) {
            return $regular_price;
        }

        $custom_price = get_post_meta($product_id, '_pix_installment_price', true);
        if ($custom_price && floatval($custom_price) > 0) {
            return floatval($custom_price);
        }

        $markup_percent = get_post_meta($product_id, '_pix_installment_markup', true);
        if ($markup_percent && floatval($markup_percent) > 0) {
            return $regular_price * (1 + (floatval($markup_percent) / 100));
        }

        return $regular_price;
    }

    public static function calculate_installment($total, $installments, $with_fee = true) {
        $installments = max(1, (int) $installments);
        $final_total = floatval($total);

        if ($with_fee) {
            $installment_fee = self::get_installment_fee();
            if ($installment_fee > 0) {
                $final_total = $final_total * (1 + ($installment_fee / 100));
            }

            $plugin_fee = self::get_plugin_fee();
            if ($plugin_fee > 0) {
                $final_total = $final_total * (1 + ($plugin_fee / 100));
            }
        }

        return $final_total / $installments;
    }

    public static function get_total_with_fees($total, $installments) {
        return self::calculate_installment($total, $installments, true) * max(1, (int) $installments);
    }

    public static function get_installment_options($product_id, $price) {
        if (!self::product_accepts_installment($product_id)) {
            return [];
        }

        $installment_base_price = self::get_installment_price($product_id, $price);
        $max_installments = min(self::get_product_max_installments($product_id), self::get_global_max_installments());
        $min_installment_value = floatval(get_option('hng_pix_installment_min_value', 5));

        $options = [];
        $installment_fee = self::get_installment_fee();
        $plugin_fee = self::get_plugin_fee();
        $total_fee_percent = $installment_fee + $plugin_fee;

        for ($i = 1; $i <= $max_installments; $i++) {
            $installment_value = self::calculate_installment($installment_base_price, $i);
            if ($installment_value < $min_installment_value) break;

            $total_with_fees = $installment_value * $i;
            /* translators: %1$d = número de parcelas, %2$s = valor da parcela formatado (ex.: 1.234,56) */
            $label = sprintf(esc_html__('%1$dx de R$ %2$s', 'hng-commerce'), $i, number_format($installment_value, 2, ',', '.'));

            if ($total_fee_percent > 0) {
                /* translators: %1$s = valor total formatado (ex.: 1.234,56) */
                $label .= sprintf(esc_html__(' (total: R$ %1$s)', 'hng-commerce'), number_format($total_with_fees, 2, ',', '.'));
            }

            $options[$i] = [
                'installments' => $i,
                'installment_value' => $installment_value,
                'base_price' => $installment_base_price,
                'total' => $total_with_fees,
                'total_fees' => $total_with_fees - $installment_base_price,
                'fee_percent' => $total_fee_percent,
                'label' => $label,
            ];
        }

        return $options;
    }

    public static function create_installment_plan($order_id, $installments) {
        global $wpdb;

        $installments = max(1, (int) $installments);
        $order = new HNG_Order($order_id);
        if (!$order) return [];

        $total = $order->get_total();
        $installment_value = self::calculate_installment($total, $installments);

        $table_full = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_pix_installments') : ($wpdb->prefix . 'hng_pix_installments');
        $table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_pix_installments') : ('`' . str_replace('`', '', $table_full) . '`');

        $plan = [];
        for ($i = 1; $i <= $installments; $i++) {
            $due_date = gmdate('Y-m-d H:i:s', strtotime("+{$i} month"));

            $wpdb->insert($table_full, [
                'order_id' => $order_id,
                'installment_number' => $i,
                'total_installments' => $installments,
                'amount' => $installment_value,
                'due_date' => $due_date,
                'status' => 'pending',
                'created_at' => current_time('mysql'),
            ], ['%d', '%d', '%d', '%f', '%s', '%s', '%s']);

            $plan[] = [
                'id' => $wpdb->insert_id ?: 0,
                'number' => $i,
                'amount' => $installment_value,
                'due_date' => $due_date,
            ];
        }

        update_post_meta($order_id, '_pix_installment_plan', $plan);
        update_post_meta($order_id, '_payment_method', 'pix_installment');

        return $plan;
    }

    public static function generate_installment_pix($installment_id) {
        global $wpdb;

        $table_full = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_pix_installments') : ($wpdb->prefix . 'hng_pix_installments');
        $table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_pix_installments') : ('`' . str_replace('`', '', $table_full) . '`');

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom PIX installments table query, load installment data for PIX generation
        $installment = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_sql} WHERE id = %d", $installment_id), ARRAY_A);
        if (empty($installment)) return new WP_Error('not_found', __('Parcela não encontrada.', 'hng-commerce'));

        $order = new HNG_Order($installment['order_id']);

        $gateway_id = get_option('hng_pix_installment_gateway', 'asaas');
        $gateway_class = 'HNG_Gateway_' . ucfirst(sanitize_key($gateway_id));
        if (!class_exists($gateway_class)) return new WP_Error('no_gateway', __('Gateway não disponível.', 'hng-commerce'));

        $gateway = new $gateway_class();
        if (!method_exists($gateway, 'generate_pix')) return new WP_Error('no_method', __('Gateway não implementa geração de PIX.', 'hng-commerce'));

        $payment_data = [
            'amount' => $installment['amount'],
            /* translators: %1$d = número da parcela, %2$d = total de parcelas, %3$d = ID do pedido */
            'description' => sprintf(__('Parcela %1$d/%2$d do pedido #%3$d', 'hng-commerce'), $installment['installment_number'], $installment['total_installments'], $order->get_id()),
            'customer_email' => $order->get_customer_email(),
        ];

        $result = $gateway->generate_pix($payment_data);
        if (is_wp_error($result)) return $result;

        $pix_data = maybe_serialize($result['pix_data'] ?? $result);
        $wpdb->update($table_full, [
            'pix_data' => $pix_data,
            'pix_generated_at' => current_time('mysql'),
            'status' => 'generated',
        ], ['id' => $installment_id], ['%s', '%s', '%s'], ['%d']);

        return $result;
    }

    public static function mark_as_paid($installment_id) {
        global $wpdb;

        $table_full = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_pix_installments') : ($wpdb->prefix . 'hng_pix_installments');
        $table_sql = '`' . str_replace('`', '', $table_full) . '`';

        $updated = $wpdb->update($table_full, ['status' => 'paid', 'paid_at' => current_time('mysql')], ['id' => $installment_id], ['%s', '%s'], ['%d']);
        if ($updated === false) return false;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom PIX installments table query, get installment data after payment
        $installment = $wpdb->get_row($wpdb->prepare("SELECT order_id, total_installments FROM {$table_sql} WHERE id = %d", $installment_id), ARRAY_A);
        if (empty($installment)) return false;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom PIX installments table query, count paid installments to check order completion
        $paid_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_sql} WHERE order_id = %d AND status = 'paid'", $installment['order_id']));
        if ($paid_count >= (int) $installment['total_installments']) {
            $order = new HNG_Order($installment['order_id']);
            if (method_exists($order, 'update_status')) {
                $order->update_status('hng-completed');
            }
        }

        return true;
    }

    public static function get_customer_installments($customer_email) {
        global $wpdb;
        $table_full = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_pix_installments') : ($wpdb->prefix . 'hng_pix_installments');
        $table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_pix_installments') : ('`' . str_replace('`', '', $table_full) . '`');

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom PIX installments table query, get customer's installments list
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table_sql} WHERE customer_email = %s ORDER BY due_date ASC", $customer_email), ARRAY_A);
    }

    public static function check_overdue_installments() {
        global $wpdb;
        $table_full = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_pix_installments') : ($wpdb->prefix . 'hng_pix_installments');
        $table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_pix_installments') : ('`' . str_replace('`', '', $table_full) . '`');

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom PIX installments table query, check overdue installments for notifications
        $overdue = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table_sql} WHERE status = %s AND due_date < NOW()", 'pending'), ARRAY_A);
        foreach ($overdue as $installment) {
            $order = new HNG_Order($installment['order_id']);
            $to = $order->get_customer_email();
            $subject = __('Lembrete: Parcela PIX vencida', 'hng-commerce');
            /* translators: %1$d = parcela atual, %2$d = total de parcelas, %3$d = número do pedido, %4$s = valor formatado (ex.: 1.234,56) */
            $message = sprintf(__('A parcela %1$d/%2$d do pedido #%3$d está vencida. Valor: %4$s', 'hng-commerce'), $installment['installment_number'], $installment['total_installments'], $order->get_id(), number_format($installment['amount'], 2, ',', '.'));
            wp_mail($to, $subject, $message);

            $wpdb->update($table_full, ['status' => 'overdue'], ['id' => $installment['id']], ['%s'], ['%d']);
        }
    }

}

// Cron schedule for overdue checks
add_action('init', function() {
    if (!wp_next_scheduled('hng_check_overdue_installments')) {
        wp_schedule_event(time(), 'daily', 'hng_check_overdue_installments');
    }
});

add_action('hng_check_overdue_installments', function() {
    HNG_PIX_Installment::check_overdue_installments();
});
