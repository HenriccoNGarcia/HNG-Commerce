<?php
/**
 * Financial Dashboard
 * 
 * Analytics and reporting dashboard
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// DB helper
require_once HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php';

class HNG_Financial_Dashboard {
    
    /**
     * Get dashboard stats
     */
    public static function get_dashboard_stats($period = 'month') {
        $dates = self::get_period_dates($period);
        
        $stats = [
            'revenue' => self::get_revenue_stats($dates['start'], $dates['end']),
            'profit' => HNG_Profit_Calculator::get_period_profit($dates['start'], $dates['end']),
            'orders' => self::get_orders_stats($dates['start'], $dates['end']),
            'best_sellers' => self::get_best_sellers_with_profit(5),
            'gateway_performance' => self::get_gateway_performance($dates['start'], $dates['end']),
        ];

        // Integraçáo Avançada Asaas
        if (get_option('hng_asaas_advanced_integration') === 'yes') {
            $asaas_stats = self::get_asaas_metrics($dates['start'], $dates['end']);
            $stats['asaas_revenue'] = $asaas_stats['revenue'];
            $stats['asaas_count'] = $asaas_stats['count'];
        }
        
        return $stats;
    }

    /**
     * Get period dates
     */
    public static function get_period_dates($period) {
        $end = current_time('mysql');
        
        switch ($period) {
            case 'today':
                $start = gmdate('Y-m-d 00:00:00');
                break;
            case 'week':
                $start = gmdate('Y-m-d 00:00:00', strtotime('-7 days'));
                break;
            case 'month':
                $start = gmdate('Y-m-d 00:00:00', strtotime('-30 days'));
                break;
            case 'year':
                $start = gmdate('Y-m-d 00:00:00', strtotime('-365 days'));
                break;
            default:
                $start = gmdate('Y-m-d 00:00:00', strtotime('-30 days'));
        }
        
        return ['start' => $start, 'end' => $end];
    }
    
    /**
     * Get revenue stats
     */
    public static function get_revenue_stats($start, $end) {
        global $wpdb;
        $orders_table = hng_db_full_table_name('hng_orders');

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for financial dashboard, real-time revenue statistics
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as orders_count,
                SUM(total) as total_revenue,
                AVG(total) as avg_order_value
             FROM {$orders_table}
             WHERE status IN ('processing', 'completed')
             AND created_at BETWEEN %s AND %s",
            $start,
            $end
        ));
        
        return [
            'total' => floatval($result->total_revenue ?? 0),
            'orders_count' => intval($result->orders_count ?? 0),
            'average_order' => floatval($result->avg_order_value ?? 0),
        ];
    }
    
    /**
     * Get orders stats
     */
    public static function get_orders_stats($start, $end) {
        global $wpdb;
        $orders_table = hng_db_full_table_name('hng_orders');
        
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for financial dashboard, order status aggregation
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT status, COUNT(*) as count 
             FROM {$orders_table}
             WHERE created_at BETWEEN %s AND %s
             GROUP BY status",
            $start,
            $end
        ));
        
        $stats = [];
        foreach ($results as $row) {
            $stats[$row->status] = intval($row->count);
        }
        
        return $stats;
    }

    /**
     * Get best sellers with profit
     */
    public static function get_best_sellers_with_profit($limit = 5, $period = '30days') {
        global $wpdb;
        $orders_table = hng_db_full_table_name('hng_orders');
        $order_items_table = hng_db_full_table_name('hng_order_items');
        
        // Get period dates
        $dates = self::get_period_dates($period);
        
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for financial dashboard, best sellers with profit calculation
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                oi.product_id,
                oi.product_name as name,
                SUM(oi.quantity) as quantity,
                SUM(oi.subtotal) as revenue,
                SUM(oi.quantity * COALESCE(pm.meta_value, 0)) as cost
             FROM {$order_items_table} oi
             INNER JOIN {$orders_table} o ON oi.order_id = o.id
             LEFT JOIN {$wpdb->postmeta} pm ON oi.product_id = pm.post_id AND pm.meta_key = '_cost'
             WHERE o.status IN ('processing', 'completed')
             AND o.created_at BETWEEN %s AND %s
             GROUP BY oi.product_id, oi.product_name
             ORDER BY quantity DESC
             LIMIT %d",
            $dates['start'],
            $dates['end'],
            $limit
        ));
        
        $products = [];
        foreach ($results as $row) {
            $revenue = floatval($row->revenue);
            $cost = floatval($row->cost);
            $profit = $revenue - $cost;
            $margin = $revenue > 0 ? ($profit / $revenue) * 100 : 0;
            
            $products[] = [
                'product_id' => intval($row->product_id),
                'name' => $row->name,
                'quantity' => intval($row->quantity),
                'revenue' => $revenue,
                'cost' => $cost,
                'profit' => $profit,
                'margin' => $margin,
            ];
        }
        
        return $products;
    }

    /**
     * Get gateway performance
     */
    public static function get_gateway_performance($start, $end) {
        global $wpdb;
        $orders_table = hng_db_full_table_name('hng_orders');
        
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for financial dashboard, gateway performance metrics
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT payment_method, COUNT(*) as count, SUM(total) as total
             FROM {$orders_table}
             WHERE status IN ('processing', 'completed')
             AND created_at BETWEEN %s AND %s
             GROUP BY payment_method",
            $start,
            $end
        ));
        
        $stats = [];
        foreach ($results as $row) {
            $stats[$row->payment_method] = [
                'count' => intval($row->count),
                'total' => floatval($row->total)
            ];
        }
        
        return $stats;
    }

    /**
     * Get Asaas Metrics
     * 
     * Fetches financial data directly from Asaas API
     */
    public static function get_asaas_metrics($start, $end) {
        // Cache key baseada nas datas
        $cache_key = 'hng_asaas_metrics_' . md5($start . $end);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        if (!class_exists('HNG_Gateway_Asaas')) {
            return ['revenue' => 0, 'net_revenue' => 0, 'count' => 0];
        }

        // Tentar instanciar para pegar config, ou pegar options direto
        $api_key = get_option('hng_asaas_api_key');
        $environment = get_option('hng_asaas_sandbox', 1) ? 'sandbox' : 'production';
        
        if (empty($api_key)) {
            return ['revenue' => 0, 'net_revenue' => 0, 'count' => 0];
        }

        // Converter datas para formato Y-m-d
        $start_date = gmdate('Y-m-d', strtotime($start));
        $end_date = gmdate('Y-m-d', strtotime($end));

        // Buscar pagamentos recebidos
        $params = [
            'paymentDate[ge]' => $start_date,
            'paymentDate[le]' => $end_date,
            'status' => 'RECEIVED',
            'limit' => 100 
        ];
        
        $url = ($environment === 'sandbox' ? 'https://sandbox.asaas.com/api/v3' : 'https://api.asaas.com/v3') . '/payments';
        $url = add_query_arg($params, $url);
        
        $response = wp_remote_get($url, [
            'headers' => [
                'access_token' => $api_key,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 10
        ]);

        $revenue = 0;
        $net_revenue = 0;
        $count = 0;

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) == 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($body['data'])) {
                foreach ($body['data'] as $payment) {
                    $revenue += floatval($payment['value']);
                    $net_revenue += floatval($payment['netValue'] ?? $payment['value']);
                    $count++;
                }
            }
        }

        $result = ['revenue' => $revenue, 'net_revenue' => $net_revenue, 'count' => $count];
        set_transient($cache_key, $result, HOUR_IN_SECONDS);
        
        return $result;
    }

    /**
     * Compare two periods
     * 
     * @param string $period1 First period identifier
     * @param string $period2 Second period identifier
     * @return array Comparison data with metrics for both periods and variance
     */
    public static function compare_periods($period1 = '30days', $period2 = '30days_previous') {
        // Get dates for both periods
        $dates1 = self::get_period_dates($period1);
        $dates2 = self::get_comparison_period_dates($period1, $period2);
        
        // Get stats for both periods
        $stats1 = [
            'revenue' => self::get_revenue_stats($dates1['start'], $dates1['end']),
            'orders' => self::get_orders_stats($dates1['start'], $dates1['end']),
            'gateway_performance' => self::get_gateway_performance($dates1['start'], $dates1['end']),
        ];
        
        $stats2 = [
            'revenue' => self::get_revenue_stats($dates2['start'], $dates2['end']),
            'orders' => self::get_orders_stats($dates2['start'], $dates2['end']),
            'gateway_performance' => self::get_gateway_performance($dates2['start'], $dates2['end']),
        ];
        
        // Calculate variances
        $comparison = [
            'period1' => [
                'label' => self::get_period_label($period1),
                'dates' => $dates1,
                'stats' => $stats1,
            ],
            'period2' => [
                'label' => self::get_period_label($period2),
                'dates' => $dates2,
                'stats' => $stats2,
            ],
            'variance' => [
                'revenue' => self::calculate_variance($stats1['revenue']['total'], $stats2['revenue']['total']),
                'orders_count' => self::calculate_variance($stats1['revenue']['orders_count'], $stats2['revenue']['orders_count']),
                'average_order' => self::calculate_variance($stats1['revenue']['average_order'], $stats2['revenue']['average_order']),
            ],
        ];
        
        return $comparison;
    }

    /**
     * Get comparison period dates
     * 
     * @param string $base_period Base period
     * @param string $comparison_type Comparison type (previous, last_year, custom)
     * @return array Start and end dates
     */
    public static function get_comparison_period_dates($base_period, $comparison_type = 'previous') {
        $base_dates = self::get_period_dates($base_period);
        
        if ($comparison_type === 'previous' || strpos((string) $comparison_type, '_previous') !== false) {
            // Calculate previous period of same length
            $start = strtotime($base_dates['start']);
            $end = strtotime($base_dates['end']);
            $duration = $end - $start;
            
            return [
                'start' => gmdate('Y-m-d H:i:s', $start - $duration),
                'end' => gmdate('Y-m-d H:i:s', $start - 1),
            ];
        }
        
        if ($comparison_type === 'last_year') {
            // Same period, previous year
            return [
                'start' => gmdate('Y-m-d H:i:s', strtotime('-1 year', strtotime($base_dates['start']))),
                'end' => gmdate('Y-m-d H:i:s', strtotime('-1 year', strtotime($base_dates['end']))),
            ];
        }
        
        // Default to previous period
        return self::get_comparison_period_dates($base_period, 'previous');
    }

    /**
     * Calculate variance percentage
     * 
     * @param float $current Current value
     * @param float $previous Previous value
     * @return array Variance data (value, percentage, direction)
     */
    private static function calculate_variance($current, $previous) {
        $current = floatval($current);
        $previous = floatval($previous);
        
        if ($previous == 0) {
            return [
                'value' => $current,
                'percentage' => $current > 0 ? 100 : 0,
                'direction' => $current > 0 ? 'up' : 'neutral',
            ];
        }
        
        $diff = $current - $previous;
        $percentage = ($diff / $previous) * 100;
        
        return [
            'value' => $diff,
            'percentage' => abs($percentage),
            'direction' => $diff > 0 ? 'up' : ($diff < 0 ? 'down' : 'neutral'),
        ];
    }

    /**
     * Get human-readable period label
     * 
     * @param string $period Period identifier
     * @return string Label
     */
    private static function get_period_label($period) {
        $labels = [
            'today' => __('Hoje', 'hng-commerce'),
            '7days' => __('áÅ¡ltimos 7 dias', 'hng-commerce'),
            '30days' => __('áÅ¡ltimos 30 dias', 'hng-commerce'),
            '90days' => __('áÅ¡ltimos 90 dias', 'hng-commerce'),
            'year' => __('Este ano', 'hng-commerce'),
            'month' => __('Este máÂªs', 'hng-commerce'),
            'week' => __('Esta semana', 'hng-commerce'),
            '30days_previous' => __('30 dias anteriores', 'hng-commerce'),
            'last_year' => __('Mesmo peráÂ­odo ano passado', 'hng-commerce'),
        ];
        
        return isset($labels[$period]) ? $labels[$period] : $period;
    }
}
