<?php
/**
 * Profit Calculator
 * 
 * Calculate profit margins for products and orders
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// DB helper
require_once HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php';

class HNG_Profit_Calculator {
    
    /**
     * Calculate product profit
     */
    public static function calculate_product_profit($product_id, $quantity = 1) {
        $product = new HNG_Product($product_id);
        $selling_price = $product->get_price();
        $cost = HNG_Cost_Tracker::get_product_cost($product_id);
        
        $revenue = $selling_price * $quantity;
        $total_cost = $cost * $quantity;
        $profit = $revenue - $total_cost;
        $margin = $revenue > 0 ? ($profit / $revenue) * 100 : 0;
        
        return [
            'revenue' => $revenue,
            'cost' => $total_cost,
            'profit' => $profit,
            'margin_percent' => $margin,
        ];
    }
    
    /**
     * Calculate order profit
     */
    public static function calculate_order_profit($order_id) {
        $order = new HNG_Order($order_id);
        $costs = HNG_Cost_Tracker::get_order_costs($order_id);
        
        $revenue = $order->get_total();
        $total_cost = $costs['total'];
        $profit = $revenue - $total_cost;
        $margin = $revenue > 0 ? ($profit / $revenue) * 100 : 0;
        
        $result = [
            'revenue' => $revenue,
            'costs' => $costs,
            'total_cost' => $total_cost,
            'profit' => $profit,
            'margin_percent' => $margin,
        ];
        
        // Save profit data
        update_post_meta($order_id, '_order_profit', $result);
        
        return $result;
    }
    
    /**
     * Get total profit for period
     */
    public static function get_period_profit($start_date, $end_date) {
        global $wpdb;
        $orders_table = hng_db_full_table_name('hng_orders');

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $orders_table sanitized via hng_db_full_table_name()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for profit calculation, period-based aggregation
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $orders_table sanitized via hng_db_full_table_name()
        $orders = $wpdb->get_results($wpdb->prepare(
            "SELECT ID FROM {$orders_table} 
             WHERE status = 'completed' 
             AND created_at BETWEEN %s AND %s",
            $start_date,
            $end_date
        ));
        
        $total_revenue = 0;
        $total_cost = 0;
        $total_profit = 0;
        
        foreach ($orders as $order) {
            $profit_data = self::calculate_order_profit($order->ID);
            $total_revenue += $profit_data['revenue'];
            $total_cost += $profit_data['total_cost'];
            $total_profit += $profit_data['profit'];
        }
        
        $margin = $total_revenue > 0 ? ($total_profit / $total_revenue) * 100 : 0;
        
        return [
            'revenue' => $total_revenue,
            'cost' => $total_cost,
            'profit' => $total_profit,
            'margin_percent' => $margin,
            'orders_count' => count($orders),
        ];
    }
    
    /**
     * Get best selling products with profit data
     */
    public static function get_best_sellers_with_profit($limit = 10) {
        global $wpdb;
        $orders_table = hng_db_full_table_name('hng_orders');
        $items_table = hng_db_full_table_name('hng_order_items');

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for profit analysis, best sellers aggregation
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                oi.product_id,
                SUM(oi.quantity) as total_sold,
                SUM(oi.price * oi.quantity) as total_revenue
             FROM {$items_table} oi
             INNER JOIN {$orders_table} o ON oi.order_id = o.id
             WHERE o.status = 'completed'
             GROUP BY oi.product_id
             ORDER BY total_sold DESC
             LIMIT %d",
            $limit
        ));
        
        $products = [];
        
        foreach ($results as $row) {
            $product = new HNG_Product($row->product_id);
            $profit_data = self::calculate_product_profit($row->product_id, $row->total_sold);
            
            $products[] = [
                'product_id' => $row->product_id,
                'name' => $product->get_name(),
                'total_sold' => $row->total_sold,
                'revenue' => $profit_data['revenue'],
                'cost' => $profit_data['cost'],
                'profit' => $profit_data['profit'],
                'margin_percent' => $profit_data['margin_percent'],
            ];
        }
        
        return $products;
    }
}
