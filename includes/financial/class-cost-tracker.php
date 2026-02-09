<?php
/**
 * Cost Tracking System
 * 
 * Track all costs associated with products and orders
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Cost_Tracker {
    
    /**
     * Get product cost
     */
    public static function get_product_cost($product_id) {
        return floatval(get_post_meta($product_id, '_product_cost', true));
    }
    
    /**
     * Set product cost
     */
    public static function set_product_cost($product_id, $cost) {
        update_post_meta($product_id, '_product_cost', floatval($cost));
    }
    
    /**
     * Calculate total order cost
     */
    public static function calculate_order_cost($order_id) {
        global $wpdb;
        
        $order = new HNG_Order($order_id);
        
        $costs = [
            'products' => 0,
            'shipping' => 0,
            'gateway_fee' => 0,
            'total' => 0,
        ];
        
        // Product costs
        $items = $order->get_items();
        foreach ($items as $item) {
            $product_cost = self::get_product_cost($item['product_id']);
            $costs['products'] += $product_cost * $item['quantity'];
        }
        
        // Shipping cost (actual cost, not what customer paid)
        $shipping_cost = floatval(get_post_meta($order_id, '_actual_shipping_cost', true));
        $costs['shipping'] = $shipping_cost;
        
        // Gateway fee
        $gateway_fee = self::calculate_gateway_fee($order);
        $costs['gateway_fee'] = $gateway_fee;
        
        $costs['total'] = $costs['products'] + $costs['shipping'] + $costs['gateway_fee'];
        
        // Save costs
        update_post_meta($order_id, '_order_costs', $costs);
        
        return $costs;
    }
    
    /**
     * Calculate gateway fee
     */
    private static function calculate_gateway_fee($order) {
        $payment_method = get_post_meta($order->get_id(), '_payment_method', true);
        $order_total = $order->get_total();
        
        // Gateway fee percentages (configurable)
        $gateway_fees = [
            'asaas' => 1.99, // 1.99%
            'mercadopago' => 4.99, // 4.99%
            'pagseguro' => 4.99,
            'pix' => 0.99, // PIX typically cheaper
            'boleto' => 3.00, // Fixed fee per boleto
        ];
        
        $fee_percent = $gateway_fees[$payment_method] ?? 0;
        
        // PIX installment adds HNG fee
        if ($payment_method === 'pix_installment') {
            $plugin_fee = HNG_PIX_Installment::get_plugin_fee();
            $fee_percent += $plugin_fee;
        }
        
        return ($order_total * $fee_percent) / 100;
    }
    
    /**
     * Set actual shipping cost (what merchant paid, not customer)
     */
    public static function set_shipping_cost($order_id, $cost) {
        update_post_meta($order_id, '_actual_shipping_cost', floatval($cost));
    }
    
    /**
     * Get order costs
     */
    public static function get_order_costs($order_id) {
        $costs = get_post_meta($order_id, '_order_costs', true);
        
        if (empty($costs)) {
            // Calculate if not cached
            $costs = self::calculate_order_cost($order_id);
        }
        
        return $costs;
    }
}
