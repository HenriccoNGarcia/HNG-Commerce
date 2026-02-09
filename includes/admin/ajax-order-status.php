<?php
/**
 * AJAX Handlers para Atualização de Status de Pedidos
 *
 * @package HNG_Commerce
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX: Mark order as processing
 */
add_action('wp_ajax_hng_mark_processing', function() {
    error_log('[HNG AJAX] hng_mark_processing handler called');
    
    check_ajax_referer('hng_admin_nonce', 'nonce', true);
    error_log('[HNG AJAX] Nonce verification passed');

    if (!current_user_can('manage_options')) {
        error_log('[HNG AJAX] User cannot manage_options');
        wp_send_json_error(['message' => 'Permissão negada']);
    }

    $order_id = absint($_POST['order_id'] ?? 0);
    if (!$order_id) {
        error_log('[HNG AJAX] Invalid order_id: ' . var_export($_POST['order_id'] ?? 'missing', true));
        wp_send_json_error(['message' => 'ID do pedido inválido']);
    }

    require_once HNG_COMMERCE_PATH . 'includes/class-hng-order.php';
    $order = new HNG_Order($order_id);

    if (!$order->get_id()) {
        error_log('[HNG AJAX] Order not found: ' . $order_id);
        wp_send_json_error(['message' => 'Pedido não encontrado']);
    }

    $current_status = $order->get_status();
    error_log('[HNG AJAX] Attempting to update order ' . $order_id . ' from ' . $current_status . ' to hng-processing');

    $result = $order->update_status('hng-processing', __('Status alterado para "Processando" via admin', 'hng-commerce'));

    error_log('[HNG AJAX] Update result: ' . ($result ? 'SUCCESS' : 'FALSE'));

    if ($result || $current_status === 'hng-processing') {
        error_log('[HNG AJAX] Sending JSON success');
        wp_send_json_success([
            'message' => 'Status atualizado com sucesso',
            'order_id' => $order_id,
            'status' => 'hng-processing'
        ]);
    } else {
        error_log('[HNG AJAX] Sending JSON error');
        wp_send_json_error(['message' => 'Erro ao atualizar status do pedido']);
    }
});

/**
 * AJAX: Mark order as completed
 */
add_action('wp_ajax_hng_mark_completed', function() {
    check_ajax_referer('hng_admin_nonce', 'nonce', true);

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permissão negada']);
    }

    $order_id = absint($_POST['order_id'] ?? 0);
    if (!$order_id) {
        wp_send_json_error(['message' => 'ID do pedido inválido']);
    }

    require_once HNG_COMMERCE_PATH . 'includes/class-hng-order.php';
    $order = new HNG_Order($order_id);

    if (!$order->get_id()) {
        wp_send_json_error(['message' => 'Pedido não encontrado']);
    }

    $current_status = $order->get_status();
    error_log('[HNG AJAX] Attempting to update order ' . $order_id . ' from ' . $current_status . ' to hng-completed');

    $result = $order->update_status('hng-completed', __('Status alterado para "Concluído" via admin', 'hng-commerce'));

    error_log('[HNG AJAX] Update result: ' . ($result ? 'SUCCESS' : 'FALSE'));

    if ($result || $current_status === 'hng-completed') {
        wp_send_json_success([
            'message' => 'Status atualizado com sucesso',
            'order_id' => $order_id,
            'status' => 'hng-completed'
        ]);
    } else {
        wp_send_json_error(['message' => 'Erro ao atualizar status do pedido']);
    }
});
