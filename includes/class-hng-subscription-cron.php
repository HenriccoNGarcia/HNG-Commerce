<?php
/**
 * Subscription Cron and AJAX Handlers
 * 
 * Manages automatic renewals and subscription actions
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Schedule cron for subscription renewals
 */
add_action('init', function() {
    if (!wp_next_scheduled('hng_check_subscription_renewals')) {
        wp_schedule_event(time(), 'hourly', 'hng_check_subscription_renewals');
    }
});

/**
 * Process subscription renewals
 */
add_action('hng_check_subscription_renewals', function() {
    HNG_Subscription::check_due_renewals();
});

/**
 * AJAX: Pause subscription
 */
add_action('wp_ajax_hng_pause_subscription', function() {
    check_ajax_referer('HNG Commerce', 'nonce');
    
    $post = function_exists('wp_unslash') ? wp_unslash($_POST) : $_POST;
    $subscription_id = absint($post['subscription_id'] ?? 0);
    
    if (!$subscription_id) {
        wp_send_json_error(['message' => __('ID invï¿½lido.', 'hng-commerce')]);
    }
    
    $subscription = new HNG_Subscription($subscription_id);
    
    // Verify ownership
    if ($subscription->get_customer_email() !== wp_get_current_user()->user_email) {
        wp_send_json_error(['message' => __('Acesso negado.', 'hng-commerce')]);
    }
    
    $subscription->pause();
    
    wp_send_json_success(['message' => __('Assinatura pausada com sucesso.', 'hng-commerce')]);
});

/**
 * AJAX: Resume subscription
 */
add_action('wp_ajax_hng_resume_subscription', function() {
    check_ajax_referer('HNG Commerce', 'nonce');
    
    $post = function_exists('wp_unslash') ? wp_unslash($_POST) : $_POST;
    $subscription_id = absint($post['subscription_id'] ?? 0);
    
    if (!$subscription_id) {
        wp_send_json_error(['message' => __('ID invï¿½lido.', 'hng-commerce')]);
    }
    
    $subscription = new HNG_Subscription($subscription_id);
    
    if ($subscription->get_customer_email() !== wp_get_current_user()->user_email) {
        wp_send_json_error(['message' => __('Acesso negado.', 'hng-commerce')]);
    }
    
    $subscription->resume();
    
    wp_send_json_success(['message' => __('Assinatura reativada com sucesso.', 'hng-commerce')]);
});

/**
 * AJAX: Cancel subscription
 */
add_action('wp_ajax_hng_cancel_subscription', function() {
    check_ajax_referer('HNG Commerce', 'nonce');
    
    $post = function_exists('wp_unslash') ? wp_unslash($_POST) : $_POST;
    $subscription_id = absint($post['subscription_id'] ?? 0);
    
    if (!$subscription_id) {
        wp_send_json_error(['message' => __('ID invï¿½lido.', 'hng-commerce')]);
    }
    
    $subscription = new HNG_Subscription($subscription_id);
    
    if ($subscription->get_customer_email() !== wp_get_current_user()->user_email) {
        wp_send_json_error(['message' => __('Acesso negado.', 'hng-commerce')]);
    }
    
    $subscription->cancel();
    
    wp_send_json_success(['message' => __('Assinatura cancelada.', 'hng-commerce')]);
});

/**
 * Create subscription after successful payment
 */
add_action('hng_order_status_changed', function($order_id, $old_status, $new_status) {
    // Only create subscription when order is completed or processing
    if (!in_array($new_status, ['processing', 'completed'])) {
        return;
    }
    
    $order = new HNG_Order($order_id);
    $items = $order->get_items();
    
    foreach ($items as $item) {
        $product = new HNG_Product($item['product_id']);
        
        // Check if product is a subscription
        $is_subscription = get_post_meta($item['product_id'], '_is_subscription', true);
        
        if ($is_subscription === 'yes') {
            $billing_period = get_post_meta($item['product_id'], '_subscription_period', true) ?: 'monthly';
            $billing_interval = get_post_meta($item['product_id'], '_subscription_interval', true) ?: 1;
            
            // Calculate next payment date
            $next_date = strtotime('+1 ' . $billing_period);
            
            // Buscar mï¿½todo de pagamento real do pedido
            $payment_method = get_post_meta($order_id, '_payment_method', true);
            if (empty($payment_method)) {
                $payment_method = 'credit_card'; // Fallback
            }
            
            HNG_Subscription::create([
                'order_id' => $order_id,
                'product_id' => $item['product_id'],
                'customer_email' => $order->get_customer_email(),
                'status' => 'active',
                'billing_period' => $billing_period,
                'billing_interval' => $billing_interval,
                'next_billing_date' => gmdate('Y-m-d H:i:s', $next_date),
                'amount' => $product->get_price(),
                'gateway' => $order->get_payment_method(),
                'payment_method' => $payment_method,
            ]);
        }
    }
}, 10, 3);
