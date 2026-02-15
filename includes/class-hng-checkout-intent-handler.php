<?php
/**
 * HNG Checkout Intent Handler
 * 
 * Gerencia fluxo de checkout com intents JWT assinados.
 * Replaces direct auth_token usage with one-time JWT tokens.
 * 
 * @package HNG_Commerce
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Checkout_Intent_Handler {
    
    /**
     * Create an intent and store it for later consumption
     * 
     * @param int $order_id WordPress order post ID
     * @param float $amount Order amount
     * @param string $gateway Gateway identifier
     * @param string $payment_method Payment method
     * @return array|WP_Error Array with intent data or WP_Error
     */
    public static function create_intent_for_order($order_id, $amount, $gateway = 'asaas', $payment_method = 'pix') {
        if (!class_exists('HNG_Payment_Orchestrator')) {
            return new WP_Error('orchestrator_unavailable', 'HNG_Payment_Orchestrator não disponível');
        }
        
        $merchant_id = get_option('hng_merchant_id', get_current_user_id());
        
        $extra_data = [
            'merchant_id' => $merchant_id,
            'order_id' => (string)$order_id,
        ];
        
        $intent = HNG_Payment_Orchestrator::create_checkout_intent(
            $amount,
            $gateway,
            $payment_method,
            $extra_data
        );
        
        if (is_wp_error($intent)) {
            return $intent;
        }
        
        // Store intent token on order meta
        if (!empty($intent['intent']['token'])) {
            update_post_meta($order_id, '_hng_checkout_intent_token', $intent['intent']['token']);
            update_post_meta($order_id, '_hng_checkout_intent_created_at', time());
            update_post_meta($order_id, '_hng_checkout_intent_gateway', $gateway);
            update_post_meta($order_id, '_hng_checkout_intent_method', $payment_method);
        }
        
        return $intent;
    }
    
    /**
     * Verify and consume an intent for payment
     * 
     * @param int $order_id WordPress order post ID
     * @return array|WP_Error Decoded intent payload or WP_Error
     */
    public static function verify_and_consume_intent($order_id) {
        if (!class_exists('HNG_Signature') || !class_exists('HNG_Payment_Orchestrator')) {
            return new WP_Error('handlers_unavailable', 'Handlers necessários não disponíveis');
        }
        
        $intent_token = get_post_meta($order_id, '_hng_checkout_intent_token', true);
        
        if (empty($intent_token)) {
            return new WP_Error('no_intent', 'Nenhum intent encontrado para este pedido');
        }
        
        // Verify JWT
        $payload = HNG_Payment_Orchestrator::verify_checkout_intent($intent_token);
        
        if (is_wp_error($payload)) {
            return $payload;
        }
        
        // Double-check: intent must belong to this order
        if (!empty($payload['order_id']) && (string)$payload['order_id'] !== (string)$order_id) {
            return new WP_Error('intent_order_mismatch', 'Intent não pertence a este pedido');
        }
        
        // Mark intent as consumed
        update_post_meta($order_id, '_hng_checkout_intent_consumed', true);
        update_post_meta($order_id, '_hng_checkout_intent_consumed_at', time());
        
        return $payload;
    }
    
    /**
     * Get stored intent for an order
     * 
     * @param int $order_id WordPress order post ID
     * @return string|false Intent token or false
     */
    public static function get_intent_token($order_id) {
        return get_post_meta($order_id, '_hng_checkout_intent_token', true) ?: false;
    }
    
    /**
     * Check if intent has been consumed
     * 
     * @param int $order_id WordPress order post ID
     * @return bool True if consumed
     */
    public static function is_intent_consumed($order_id) {
        return (bool)get_post_meta($order_id, '_hng_checkout_intent_consumed', true);
    }
    
    /**
     * Process payment using intent JWT
     * Replaces old auth_token flow
     * 
     * @param int $order_id WordPress order post ID
     * @param string $gateway Gateway identifier
     * @return array|WP_Error Payment response or WP_Error
     */
    public static function process_payment_with_intent($order_id, $gateway = 'asaas') {
        if (!class_exists('HNG_Payment_Orchestrator')) {
            return new WP_Error('orchestrator_unavailable', 'HNG_Payment_Orchestrator não disponível');
        }
        
        // Verify and consume intent
        $intent = self::verify_and_consume_intent($order_id);
        
        if (is_wp_error($intent)) {
            return $intent;
        }
        
        // Use auth_token from intent (now authorized by server via JWT)
        $auth_token = $intent['auth_token'];
        
        // Get order details
        $total = floatval(get_post_meta($order_id, '_total', true));
        $customer_email = get_post_meta($order_id, '_customer_email', true);
        $customer_name = get_post_meta($order_id, '_customer_name', true);
        $customer_cpf = get_post_meta($order_id, '_customer_cpf', true);
        $payment_method = get_post_meta($order_id, '_hng_checkout_intent_method', true) ?: 'pix';
        
        $payment_data = [
            'amount' => $total,
            'method' => $payment_method,
            'description' => sprintf('Pedido #%d', $order_id),
            'external_id' => (string)$order_id,
            'customer_name' => $customer_name,
            'customer_email' => $customer_email,
            'customer_cpf' => $customer_cpf,
        ];
        
        // Create payment using auth_token from intent
        $result = HNG_Payment_Orchestrator::create_payment($auth_token, $gateway, $payment_data);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Store result on order
        if (!empty($result['payment_id'])) {
            update_post_meta($order_id, '_api_payment_id', $result['payment_id']);
        }
        
        if (!empty($result['signed'])) {
            update_post_meta($order_id, '_api_payment_signed', $result['signed']);
        }
        
        update_post_meta($order_id, '_api_payment_data', $result);
        
        return $result;
    }
}
