<?php
/**
 * HNG Commerce - Financial Analytics Calculator
 * 
 * Calcula métricas detalhadas de análise financeira:
 * - Faturamento total
 * - Lucro total
 * - Lucro por produto, categoria e tipo
 * - Faturamento por categoria e tipo
 *
 * @package HNG_Commerce
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Financial_Analytics {

    /**
     * Get detailed financial analysis for period
     */
    public static function get_detailed_analysis($start_date, $end_date) {
        return [
            'summary' => self::get_summary($start_date, $end_date),
            'summary_split' => self::get_summary_split($start_date, $end_date),
            'by_product' => self::get_profit_by_product($start_date, $end_date),
            'by_category' => self::get_metrics_by_category($start_date, $end_date),
            'by_product_type' => self::get_metrics_by_product_type($start_date, $end_date),
            'top_products' => self::get_top_products($start_date, $end_date, 10),
            'worst_products' => self::get_worst_products($start_date, $end_date, 5),
        ];
    }

    /**
     * Get summary (faturamento e lucro total)
     */
    public static function get_summary($start_date, $end_date) {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'hng_orders';
        $order_items_table = $wpdb->prefix . 'hng_order_items';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are safely prefixed with $wpdb->prefix
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                SUM(o.total) as total_revenue,
                SUM(oi.price * oi.quantity) as revenue_from_items,
                SUM(oi.product_cost * oi.quantity) as total_cost,
                COUNT(o.id) as orders_count
             FROM {$orders_table} o
             LEFT JOIN {$order_items_table} oi ON o.id = oi.order_id
             WHERE o.status IN ('processing', 'completed')
             AND o.created_at BETWEEN %s AND %s",
            $start_date,
            $end_date
        ));

        $total_revenue = floatval($result->total_revenue ?? 0);
        $total_cost = floatval($result->total_cost ?? 0);
        $total_profit = $total_revenue - $total_cost;

        return [
            'total_revenue' => $total_revenue,
            'total_cost' => $total_cost,
            'total_profit' => $total_profit,
            'profit_margin' => $total_revenue > 0 ? round(($total_profit / $total_revenue) * 100, 2) : 0,
            'orders_count' => intval($result->orders_count ?? 0),
            'average_order_value' => $result->orders_count > 0 ? round($total_revenue / $result->orders_count, 2) : 0,
        ];
    }

    /**
     * Get summary split between site-tracked and gateway-only orders
     */
    public static function get_summary_split($start_date, $end_date) {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'hng_orders';
        $order_items_table = $wpdb->prefix . 'hng_order_items';
        $events_table = $wpdb->prefix . 'hng_conversion_events';

        // Build subquery: Site-origin orders (found matching conversion events for any product in the order near order time)
        // Note: Use a 48h window before order creation as heuristic.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names safely prefixed
        $site_order_ids_sql = $wpdb->prepare(
            "SELECT DISTINCT o.id
             FROM {$orders_table} o
             WHERE o.status IN ('processing', 'completed', 'hng-processing', 'hng-completed')
             AND o.created_at BETWEEN %s AND %s
             AND (
                 EXISTS (
                     SELECT 1 FROM {$events_table} e2
                     WHERE e2.event_type = 'purchase' AND e2.order_id = o.id
                 )
                 OR EXISTS (
                     SELECT 1 FROM {$order_items_table} oi2
                     JOIN {$events_table} e ON e.product_id = oi2.product_id
                     WHERE oi2.order_id = o.id
                       AND e.event_type IN ('product_view','add_to_cart','checkout_start')
                       AND e.created_at BETWEEN DATE_SUB(o.created_at, INTERVAL 48 HOUR) AND o.created_at
                 )
             )",
            $start_date,
            $end_date
        );

        // Aggregate site metrics
        $site_revenue = floatval($wpdb->get_var("SELECT COALESCE(SUM(total),0) FROM {$orders_table} WHERE id IN ($site_order_ids_sql)"));
        $site_cost = floatval($wpdb->get_var("SELECT COALESCE(SUM(oi.product_cost * oi.quantity),0) FROM {$order_items_table} oi WHERE oi.order_id IN ($site_order_ids_sql)"));
        $site_orders = intval($wpdb->get_var("SELECT COUNT(*) FROM {$orders_table} WHERE id IN ($site_order_ids_sql)"));

        // Aggregate gateway metrics (orders not classified as site)
        $gateway_revenue = floatval($wpdb->get_var("SELECT COALESCE(SUM(total),0) FROM {$orders_table} WHERE status IN ('processing','completed','hng-processing','hng-completed') AND created_at BETWEEN '{$start_date}' AND '{$end_date}' AND id NOT IN ($site_order_ids_sql)"));
        $gateway_cost = floatval($wpdb->get_var("SELECT COALESCE(SUM(oi.product_cost * oi.quantity),0) FROM {$order_items_table} oi JOIN {$orders_table} o ON o.id = oi.order_id WHERE o.status IN ('processing','completed','hng-processing','hng-completed') AND o.created_at BETWEEN '{$start_date}' AND '{$end_date}' AND o.id NOT IN ($site_order_ids_sql)"));
        $gateway_orders = intval($wpdb->get_var("SELECT COUNT(*) FROM {$orders_table} WHERE status IN ('processing','completed','hng-processing','hng-completed') AND created_at BETWEEN '{$start_date}' AND '{$end_date}' AND id NOT IN ($site_order_ids_sql)"));

        $combined_revenue = $site_revenue + $gateway_revenue;
        $combined_cost = $site_cost + $gateway_cost;
        $combined_orders = $site_orders + $gateway_orders;

        return [
            'site' => [
                'revenue' => $site_revenue,
                'cost' => $site_cost,
                'profit' => $site_revenue - $site_cost,
                'orders' => $site_orders,
                'aov' => $site_orders > 0 ? round($site_revenue / $site_orders, 2) : 0,
            ],
            'gateway' => [
                'revenue' => $gateway_revenue,
                'cost' => $gateway_cost,
                'profit' => $gateway_revenue - $gateway_cost,
                'orders' => $gateway_orders,
                'aov' => $gateway_orders > 0 ? round($gateway_revenue / $gateway_orders, 2) : 0,
            ],
            'combined' => [
                'revenue' => $combined_revenue,
                'cost' => $combined_cost,
                'profit' => $combined_revenue - $combined_cost,
                'orders' => $combined_orders,
                'aov' => $combined_orders > 0 ? round($combined_revenue / $combined_orders, 2) : 0,
            ],
        ];
    }

    /**
     * Get profit by individual products
     */
    public static function get_profit_by_product($start_date, $end_date) {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'hng_orders';
        $order_items_table = $wpdb->prefix . 'hng_order_items';
        $products_table = $wpdb->prefix . 'posts';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are safely prefixed with $wpdb->prefix
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                oi.product_id,
                p.post_title as product_name,
                SUM(oi.quantity) as total_quantity,
                SUM(oi.price * oi.quantity) as total_revenue,
                SUM(oi.product_cost * oi.quantity) as total_cost,
                COUNT(DISTINCT o.id) as orders_count
             FROM {$order_items_table} oi
             JOIN {$orders_table} o ON o.id = oi.order_id
             JOIN {$products_table} p ON p.ID = oi.product_id
            WHERE o.status IN ('processing', 'completed', 'hng-processing', 'hng-completed')
             AND o.created_at BETWEEN %s AND %s
             GROUP BY oi.product_id
             ORDER BY (SUM(oi.price * oi.quantity) - SUM(oi.product_cost * oi.quantity)) DESC",
            $start_date,
            $end_date
        ));

        $products = [];
        foreach ($results as $result) {
            $revenue = floatval($result->total_revenue);
            $cost = floatval($result->total_cost);
            $profit = $revenue - $cost;
            
            $products[] = [
                'product_id' => $result->product_id,
                'product_name' => $result->product_name,
                'quantity' => intval($result->total_quantity),
                'revenue' => $revenue,
                'cost' => $cost,
                'profit' => $profit,
                'profit_margin' => $revenue > 0 ? round(($profit / $revenue) * 100, 2) : 0,
                'orders' => intval($result->orders_count),
            ];
        }

        return $products;
    }

    /**
     * Get metrics by category (faturamento e lucro por categoria)
     */
    public static function get_metrics_by_category($start_date, $end_date) {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'hng_orders';
        $order_items_table = $wpdb->prefix . 'hng_order_items';
        $term_relationships = $wpdb->prefix . 'term_relationships';
        $term_taxonomy = $wpdb->prefix . 'term_taxonomy';
        $terms = $wpdb->prefix . 'terms';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are safely prefixed with $wpdb->prefix
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                t.term_id,
                t.name as category_name,
                SUM(oi.quantity) as total_quantity,
                SUM(oi.price * oi.quantity) as total_revenue,
                SUM(oi.product_cost * oi.quantity) as total_cost,
                COUNT(DISTINCT o.id) as orders_count
             FROM {$order_items_table} oi
             JOIN {$orders_table} o ON o.id = oi.order_id
             LEFT JOIN {$term_relationships} tr ON tr.object_id = oi.product_id
             LEFT JOIN {$term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'product_category'
             LEFT JOIN {$terms} t ON t.term_id = tt.term_id
             WHERE o.status IN ('processing', 'completed')
             AND o.created_at BETWEEN %s AND %s
             GROUP BY t.term_id
             ORDER BY SUM(oi.price * oi.quantity) DESC",
            $start_date,
            $end_date
        ));

        $categories = [];
        foreach ($results as $result) {
            if (empty($result->category_name)) {
                continue; // Skip uncategorized
            }
            
            $revenue = floatval($result->total_revenue);
            $cost = floatval($result->total_cost);
            $profit = $revenue - $cost;
            
            $categories[] = [
                'category_id' => $result->term_id,
                'category_name' => $result->category_name,
                'quantity' => intval($result->total_quantity),
                'revenue' => $revenue,
                'cost' => $cost,
                'profit' => $profit,
                'profit_margin' => $revenue > 0 ? round(($profit / $revenue) * 100, 2) : 0,
                'orders' => intval($result->orders_count),
            ];
        }

        return $categories;
    }

    /**
     * Get metrics by product type (tipo de produto)
     */
    public static function get_metrics_by_product_type($start_date, $end_date) {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'hng_orders';
        $order_items_table = $wpdb->prefix . 'hng_order_items';
        $posts_table = $wpdb->prefix . 'posts';
        $postmeta_table = $wpdb->prefix . 'postmeta';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are safely prefixed with $wpdb->prefix
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                COALESCE(pm.meta_value, 'physical') as product_type,
                SUM(oi.quantity) as total_quantity,
                SUM(oi.price * oi.quantity) as total_revenue,
                SUM(oi.product_cost * oi.quantity) as total_cost,
                COUNT(DISTINCT o.id) as orders_count
             FROM {$order_items_table} oi
             JOIN {$orders_table} o ON o.id = oi.order_id
             LEFT JOIN {$posts_table} p ON p.ID = oi.product_id
             LEFT JOIN {$postmeta_table} pm ON p.ID = pm.post_id AND pm.meta_key = '_product_type'
             WHERE o.status IN ('processing', 'completed')
             AND o.created_at BETWEEN %s AND %s
             GROUP BY COALESCE(pm.meta_value, 'physical')
             ORDER BY SUM(oi.price * oi.quantity) DESC",
            $start_date,
            $end_date
        ));

        $types = [];
        foreach ($results as $result) {
            $type_name = !empty($result->product_type) ? $result->product_type : 'Outro';
            
            $revenue = floatval($result->total_revenue);
            $cost = floatval($result->total_cost);
            $profit = $revenue - $cost;
            
            $types[] = [
                'type_name' => $type_name,
                'quantity' => intval($result->total_quantity),
                'revenue' => $revenue,
                'cost' => $cost,
                'profit' => $profit,
                'profit_margin' => $revenue > 0 ? round(($profit / $revenue) * 100, 2) : 0,
                'orders' => intval($result->orders_count),
            ];
        }

        return $types;
    }

    /**
     * Get top products by profit
     */
    public static function get_top_products($start_date, $end_date, $limit = 10) {
        $products = self::get_profit_by_product($start_date, $end_date);
        return array_slice($products, 0, $limit);
    }

    /**
     * Get worst products by profit
     */
    public static function get_worst_products($start_date, $end_date, $limit = 5) {
        $products = self::get_profit_by_product($start_date, $end_date);
        // Reverse para pegar os piores
        usort($products, function($a, $b) {
            return $a['profit'] <=> $b['profit'];
        });
        return array_slice($products, 0, $limit);
    }

    /**
     * Export analytics data as CSV
     */
    public static function export_analytics_csv($start_date, $end_date) {
        $analysis = self::get_detailed_analysis($start_date, $end_date);
        
        // Create CSV content
        $csv = "RELATÓRIO DE ANÁLISE FINANCEIRA\n";
        $csv .= "Período: {$start_date} até {$end_date}\n\n";
        
        // Summary section
        $summary = $analysis['summary'];
        $csv .= "RESUMO GERAL\n";
        $csv .= "Faturamento Total,R$ " . number_format($summary['total_revenue'], 2, ',', '.') . "\n";
        $csv .= "Custo Total,R$ " . number_format($summary['total_cost'], 2, ',', '.') . "\n";
        $csv .= "Lucro Total,R$ " . number_format($summary['total_profit'], 2, ',', '.') . "\n";
        $csv .= "Margem de Lucro," . $summary['profit_margin'] . "%\n";
        $csv .= "Total de Pedidos," . $summary['orders_count'] . "\n";
        $csv .= "Ticket Médio,R$ " . number_format($summary['average_order_value'], 2, ',', '.') . "\n\n";
        
        // By category
        $csv .= "LUCRO POR CATEGORIA\n";
        $csv .= "Categoria,Faturamento,Custo,Lucro,Margem,Quantidade\n";
        foreach ($analysis['by_category'] as $cat) {
            $csv .= $cat['category_name'] . ",";
            $csv .= "R$ " . number_format($cat['revenue'], 2, ',', '.') . ",";
            $csv .= "R$ " . number_format($cat['cost'], 2, ',', '.') . ",";
            $csv .= "R$ " . number_format($cat['profit'], 2, ',', '.') . ",";
            $csv .= $cat['profit_margin'] . "%,";
            $csv .= $cat['quantity'] . "\n";
        }
        
        $csv .= "\n";
        
        // By product type
        $csv .= "LUCRO POR TIPO DE PRODUTO\n";
        $csv .= "Tipo,Faturamento,Custo,Lucro,Margem,Quantidade\n";
        foreach ($analysis['by_product_type'] as $type) {
            $csv .= $type['type_name'] . ",";
            $csv .= "R$ " . number_format($type['revenue'], 2, ',', '.') . ",";
            $csv .= "R$ " . number_format($type['cost'], 2, ',', '.') . ",";
            $csv .= "R$ " . number_format($type['profit'], 2, ',', '.') . ",";
            $csv .= $type['profit_margin'] . "%,";
            $csv .= $type['quantity'] . "\n";
        }
        
        return $csv;
    }
}
