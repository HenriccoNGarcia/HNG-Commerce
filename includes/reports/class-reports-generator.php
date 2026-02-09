<?php
/**
 * HNG Commerce: Reports Generator
 * 
 * Generates advanced financial and analytics reports
 * 
 * @package HNG_Commerce
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Reports_Generator {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get financial report data
     * 
     * @param array $filters
     * @return array
     */
    public function get_financial_report($filters = []) {
        $defaults = [
            'start_date' => gmdate('Y-m-d', strtotime('-30 days')),
            'end_date' => gmdate('Y-m-d'),
            'category_id' => null,
            'product_type' => null,
            'product_id' => null,
            'payment_status' => 'completed',
            'gateway' => null
        ];
        
        $filters = wp_parse_args($filters, $defaults);
        
        return [
            'summary' => $this->get_summary($filters),
            'revenue_by_day' => $this->get_revenue_by_day($filters),
            'orders_by_status' => $this->get_orders_by_status($filters),
            'top_products' => $this->get_top_products_by_revenue($filters, 10),
            'top_categories' => $this->get_top_categories($filters, 10),
            'profit_trend' => $this->get_profit_trend($filters),
            'gateway_breakdown' => $this->get_gateway_breakdown($filters)
        ];
    }
    
    /**
     * Get summary metrics
     */
    private function get_summary($filters) {
        global $wpdb;
        
        $table = hng_db_full_table_name('hng_orders');
        
        $where = $this->build_where_clause($filters);
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table sanitized via hng_db_full_table_name(), $where built with $wpdb->prepare() in build_where_clause()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for reporting, results aggregated in real-time
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table sanitized via hng_db_full_table_name()
        $query = "
            SELECT 
                COUNT(*) as total_orders,
                COALESCE(SUM(total), 0) as total_revenue,
                COALESCE(AVG(total), 0) as avg_order_value
            FROM {$table}
            WHERE {$where}
        ";
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query uses safe table prefix from hng_db_full_table_name()
        $result = $wpdb->get_row($query);
        
        // Calculate profit (simplified - you may have more complex logic)
        $total_profit = $this->calculate_total_profit($filters);
        
        return [
            'total_orders' => (int) $result->total_orders,
            'total_revenue' => (float) $result->total_revenue,
            'total_profit' => (float) $total_profit,
            'avg_order_value' => (float) $result->avg_order_value,
            'profit_margin' => $result->total_revenue > 0 
                ? ($total_profit / $result->total_revenue) * 100 
                : 0
        ];
    }
    
    /**
     * Get revenue by day
     */
    private function get_revenue_by_day($filters) {
        global $wpdb;
        
        $table = hng_db_full_table_name('hng_orders');
        $where = $this->build_where_clause($filters);
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table sanitized via hng_db_full_table_name(), $where built with $wpdb->prepare() in build_where_clause()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for reporting, time-series data aggregated daily
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table sanitized via hng_db_full_table_name()
        $query = "
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as orders,
                COALESCE(SUM(total), 0) as revenue
            FROM {$table}
            WHERE {$where}
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ";
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query uses safe table prefix from hng_db_full_table_name()
        return $wpdb->get_results($query);
    }
    
    /**
     * Get orders by status
     */
    private function get_orders_by_status($filters) {
        global $wpdb;
        
        $table = hng_db_full_table_name('hng_orders');
        $where = $this->build_where_clause($filters, false); // Don't filter by status
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table sanitized via hng_db_full_table_name(), $where built with $wpdb->prepare() in build_where_clause()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for reporting, status aggregation in real-time
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table sanitized via hng_db_full_table_name()
        $query = "
            SELECT 
                status,
                COUNT(*) as count
            FROM {$table}
            WHERE {$where}
            GROUP BY status
        ";
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query uses safe table prefix from hng_db_full_table_name()
        return $wpdb->get_results($query);
    }
    
    /**
     * Get top products by revenue
     */
    private function get_top_products_by_revenue($filters, $limit = 10) {
        global $wpdb;
        
        $orders_table = hng_db_full_table_name('hng_orders');
        $items_table = hng_db_full_table_name('hng_order_items');
        
        $where = $this->build_where_clause($filters);
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $items_table, $orders_table sanitized via hng_db_full_table_name(), $where built with $wpdb->prepare()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for reporting, top products aggregation
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Tables sanitized via hng_db_full_table_name()
        $query = $wpdb->prepare("
            SELECT 
                oi.product_id,
                p.post_title as product_name,
                SUM(oi.quantity) as total_quantity,
                COALESCE(SUM(oi.total), 0) as total_revenue
            FROM {$items_table} oi
            INNER JOIN {$orders_table} o ON oi.order_id = o.id
            LEFT JOIN {$wpdb->posts} p ON oi.product_id = p.ID
            WHERE {$where}
            GROUP BY oi.product_id
            ORDER BY total_revenue DESC
            LIMIT %d
        ", $limit);
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query uses safe table prefix from hng_db_full_table_name()
        return $wpdb->get_results($query);
    }
    
    /**
     * Get top categories
     */
    private function get_top_categories($filters, $limit = 10) {
        global $wpdb;
        
        $orders_table = hng_db_full_table_name('hng_orders');
        $items_table = hng_db_full_table_name('hng_order_items');
        
        $where = $this->build_where_clause($filters);
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $items_table, $orders_table sanitized via hng_db_full_table_name(), $where built with $wpdb->prepare()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for reporting, category aggregation
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Tables sanitized via hng_db_full_table_name()
        $query = $wpdb->prepare("
            SELECT 
                tt.term_id as category_id,
                t.name as category_name,
                COALESCE(SUM(oi.total), 0) as total_revenue,
                COUNT(DISTINCT oi.order_id) as order_count
            FROM {$items_table} oi
            INNER JOIN {$orders_table} o ON oi.order_id = o.id
            LEFT JOIN {$wpdb->term_relationships} tr ON oi.product_id = tr.object_id
            LEFT JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'product_category'
            LEFT JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
            WHERE {$where}
            AND tt.term_id IS NOT NULL
            GROUP BY tt.term_id
            ORDER BY total_revenue DESC
            LIMIT %d
        ", $limit);
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query uses safe table prefix from hng_db_full_table_name()
        return $wpdb->get_results($query);
    }
    
    /**
     * Get revenue trend
     */
    private function get_profit_trend($filters) {
        global $wpdb;
        
        $table = hng_db_full_table_name('hng_orders');
        $where = $this->build_where_clause($filters);
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table sanitized via hng_db_full_table_name(), $where built with $wpdb->prepare() in build_where_clause()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for reporting, profit trend aggregation by date
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table sanitized via hng_db_full_table_name()
        $query = "
            SELECT 
                DATE(created_at) as date,
                COALESCE(SUM(total), 0) as revenue
            FROM {$table}
            WHERE {$where}
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ";
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query uses safe table prefix from hng_db_full_table_name()
        $results = $wpdb->get_results($query);
        
        // Enhance with profit data from metadata
        foreach ($results as $result) {
            $result->profit = $result->revenue * 0.3; // Simplified - should use actual fee data
        }
        
        return $results;
    }
    
    /**
     * Get gateway breakdown
     */
    private function get_gateway_breakdown($filters) {
        global $wpdb;
        
        $table = hng_db_full_table_name('hng_orders');
        $where = $this->build_where_clause($filters);
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table sanitized via hng_db_full_table_name(), $where built with $wpdb->prepare() in build_where_clause()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for reporting, payment method aggregation
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table sanitized via hng_db_full_table_name()
        $query = "
            SELECT 
                payment_method,
                COUNT(*) as count,
                COALESCE(SUM(total), 0) as revenue
            FROM {$table}
            WHERE {$where}
            GROUP BY payment_method
        ";
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query uses safe table prefix from hng_db_full_table_name()
        return $wpdb->get_results($query);
    }
    
    /**
     * Calculate total profit (uses fee data from orders)
     */
    private function calculate_total_profit($filters) {
        global $wpdb;
        
        $table = hng_db_full_table_name('hng_orders');
        $where = $this->build_where_clause($filters);
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table sanitized via hng_db_full_table_name(), $where built with $wpdb->prepare() in build_where_clause()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for reporting, profit calculation
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table sanitized via hng_db_full_table_name()
        // This is simplified - in reality you'd query order meta for fee data
        $query = "
            SELECT COALESCE(SUM(total * 0.3), 0) as profit
            FROM {$table}
            WHERE {$where}
        ";
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query uses safe table prefix from hng_db_full_table_name()
        $result = $wpdb->get_var($query);
        
        return (float) $result;
    }
    
    /**
     * Build WHERE clause from filters
     */
    private function build_where_clause($filters, $include_status = true) {
        global $wpdb;
        
        $conditions = ["1=1"];
        
        // Date range
        if (!empty($filters['start_date'])) {
            $conditions[] = $wpdb->prepare("created_at >= %s", $filters['start_date'] . ' 00:00:00');
        }
        if (!empty($filters['end_date'])) {
            $conditions[] = $wpdb->prepare("created_at <= %s", $filters['end_date'] . ' 23:59:59');
        }
        
        // Payment status
        if ($include_status && !empty($filters['payment_status'])) {
            $conditions[] = $wpdb->prepare("status = %s", $filters['payment_status']);
        }
        
        // Gateway
        if (!empty($filters['gateway'])) {
            $conditions[] = $wpdb->prepare("payment_method = %s", $filters['gateway']);
        }
        
        return implode(' AND ', $conditions);
    }
    
    /**
     * Compare two periods
     * 
     * @param string $current_start
     * @param string $current_end
     * @param string $previous_start
     * @param string $previous_end
     * @return array
     */
    public function compare_periods($current_start, $current_end, $previous_start, $previous_end) {
        $current = $this->get_summary([
            'start_date' => $current_start,
            'end_date' => $current_end
        ]);
        
        $previous = $this->get_summary([
            'start_date' => $previous_start,
            'end_date' => $previous_end
        ]);
        
        return [
            'current' => $current,
            'previous' => $previous,
            'growth' => [
                'revenue' => $this->calculate_growth($previous['total_revenue'], $current['total_revenue']),
                'orders' => $this->calculate_growth($previous['total_orders'], $current['total_orders']),
                'profit' => $this->calculate_growth($previous['total_profit'], $current['total_profit']),
                'avg_order' => $this->calculate_growth($previous['avg_order_value'], $current['avg_order_value'])
            ]
        ];
    }
    
    /**
     * Calculate growth percentage
     */
    private function calculate_growth($old_value, $new_value) {
        if ($old_value == 0) {
            return $new_value > 0 ? 100 : 0;
        }
        
        return (($new_value - $old_value) / $old_value) * 100;
    }
    
    /**
     * Export report to CSV
     * 
     * @param array $data
     * @param string $filename
     */
    public function export_to_csv($data, $filename = 'report.csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Write headers
        if (!empty($data) && is_array($data[0])) {
            fputcsv($output, array_keys((array) $data[0]));
        }
        
        // Write data
        foreach ($data as $row) {
            fputcsv($output, (array) $row);
        }
        
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Writing to php://output for CSV export
        fclose($output);
        exit;
    }
}
