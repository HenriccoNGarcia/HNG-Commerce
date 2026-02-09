<?php
/**
 * PIX Installment AJAX Handlers
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// DB helper
require_once HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php';

/**
 * AJAX: Generate PIX for installment
 */
add_action('wp_ajax_hng_generate_installment_pix', function() {
    check_ajax_referer('HNG Commerce', 'nonce');
    
    $post = function_exists('wp_unslash') ? wp_unslash($_POST) : $_POST;
    $installment_id = absint( $post['installment_id'] ?? 0 );
    
    if (!$installment_id) {
        wp_send_json_error(['message' => __('ID invï¿½lido.', 'hng-commerce')]);
    }
    
    // Verify ownership
    global $wpdb;
    $installments_table = hng_db_full_table_name('hng_pix_installments');
    // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $installment = $wpdb->get_row($wpdb->prepare(
        "SELECT i.*, o.post_author 
         FROM {$installments_table} i
         INNER JOIN {$wpdb->prefix}posts o ON i.order_id = o.ID
         WHERE i.id = %d",
        $installment_id
    ), ARRAY_A);
    
    if (!$installment) {
        wp_send_json_error(['message' => __('Parcela nï¿½o encontrada.', 'hng-commerce')]);
    }
    
    $order = new HNG_Order($installment['order_id']);
    
    if ($order->get_customer_email() !== wp_get_current_user()->user_email) {
        wp_send_json_error(['message' => __('Acesso negado.', 'hng-commerce')]);
    }
    
    $result = HNG_PIX_Installment::generate_installment_pix($installment_id);
    
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }
    
    wp_send_json_success($result);
});

/**
 * Webhook handler for installment payment confirmation
 */
add_action('hng_pix_payment_confirmed', function($payment_data) {
    // Check if this is an installment payment
    if (isset($payment_data['installment_id'])) {
        HNG_PIX_Installment::mark_as_paid($payment_data['installment_id']);
    }
}, 10, 1);
