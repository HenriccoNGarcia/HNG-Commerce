<?php
/**
 * HNG Commerce - Conversion Analytics
 * 
 * Calcula métricas de conversão e abandono:
 * - Templates/páginas com mais conversões
 * - Checkout com mais conversões
 * - Taxa de abandono do carrinho por categoria
 * - Taxa de abandono do carrinho por checkout
 *
 * @package HNG_Commerce
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Conversion_Analytics {

    /**
     * Get detailed conversion analysis
     */
    public static function get_conversion_analysis($start_date, $end_date) {
        return [
            'top_converting_templates' => self::get_top_converting_templates($start_date, $end_date),
            'top_converting_checkouts' => self::get_top_converting_checkouts($start_date, $end_date),
            'cart_abandonment_by_category' => self::get_cart_abandonment_by_category($start_date, $end_date),
            'cart_abandonment_by_checkout' => self::get_cart_abandonment_by_checkout($start_date, $end_date),
            'conversion_funnel' => self::get_conversion_funnel($start_date, $end_date),
        ];
    }

    /**
     * Get top converting templates/pages
     * Mostra TODAS as páginas do site com dados de conversão
     */
    public static function get_top_converting_templates($start_date, $end_date) {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'hng_orders';
        $posts_table = $wpdb->prefix . 'posts';

        // 1. Pegar dados de conversão por página
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are safely prefixed with $wpdb->prefix
        $conversion_data = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                source_page_id,
                COUNT(*) as conversions,
                SUM(total) as revenue
             FROM {$orders_table}
             WHERE status IN ('processing', 'completed', 'hng-processing', 'hng-completed')
             AND created_at BETWEEN %s AND %s
             AND source_page_id IS NOT NULL
             GROUP BY source_page_id",
            $start_date,
            $end_date
        ), ARRAY_A);

        // Criar índice de conversões
        $conversions_by_page = [];
        foreach ($conversion_data as $data) {
            $conversions_by_page[$data['source_page_id']] = $data;
        }

        // 2. Pegar TODAS as páginas do site (posts + páginas padrão)
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safely prefixed with $wpdb->prefix
        $all_pages = $wpdb->get_results(
            "SELECT ID, post_title, post_type, post_name, post_status
             FROM {$posts_table}
             WHERE post_status = 'publish'
             AND post_type IN ('page', 'post', 'hng_product')
             ORDER BY ID DESC"
        );

        $templates = [];
        foreach ($all_pages as $page) {
            $page_id = $page->ID;
            $conversion = $conversions_by_page[$page_id] ?? null;
            
            $templates[] = [
                'page_id' => $page_id,
                'page_title' => $page->post_title,
                'page_type' => $page->post_type, // page, post, product
                'page_url' => get_permalink($page_id),
                'conversions' => intval($conversion ? $conversion['conversions'] : 0),
                'revenue' => floatval($conversion ? $conversion['revenue'] : 0),
                'average_value' => $conversion ? (floatval($conversion['revenue']) / intval($conversion['conversions'])) : 0,
                'conversion_rate' => self::calculate_page_conversion_rate($page_id, $start_date, $end_date),
            ];
        }

        // Ordenar por conversão DESC, depois por revenue DESC
        usort($templates, function($a, $b) {
            if ($b['conversions'] === $a['conversions']) {
                return $b['revenue'] <=> $a['revenue'];
            }
            return $b['conversions'] <=> $a['conversions'];
        });

        // Retornar top 20
        return array_slice($templates, 0, 20);
    }
    
    /**
     * Calculate conversion rate for a specific page
     * (pageviews → conversions)
     */
    private static function calculate_page_conversion_rate($page_id, $start_date, $end_date) {
        global $wpdb;
        $pageviews_table = $wpdb->prefix . 'hng_pageviews';
        $orders_table = $wpdb->prefix . 'hng_orders';
        
        $pageviews = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$pageviews_table}
             WHERE page_id = %d AND created_at BETWEEN %s AND %s",
            $page_id, $start_date, $end_date
        ));
        
        $conversions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$orders_table}
             WHERE source_page_id = %d 
             AND status IN ('processing', 'completed', 'hng-processing', 'hng-completed')
             AND created_at BETWEEN %s AND %s",
            $page_id, $start_date, $end_date
        ));
        
        if ($pageviews == 0) {
            return 0;
        }
        
        return round(($conversions / $pageviews) * 100, 2);
    }

    /**
     * Get top converting checkouts
     */
    public static function get_top_converting_checkouts($start_date, $end_date) {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'hng_orders';

        // Get orders grouped by checkout type/gateway
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safely prefixed with $wpdb->prefix
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                payment_method,
                gateway,
                COUNT(*) as conversions,
                SUM(total) as revenue,
                AVG(total) as average_value
             FROM {$orders_table}
             WHERE status IN ('processing', 'completed', 'hng-processing', 'hng-completed')
             AND created_at BETWEEN %s AND %s
             GROUP BY payment_method, gateway
             ORDER BY conversions DESC",
            $start_date,
            $end_date
        ));

        $checkouts = [];
        foreach ($results as $result) {
            $checkout_name = !empty($result->payment_method) ? ucfirst($result->payment_method) : 'Desconhecido';
            if (!empty($result->gateway)) {
                $checkout_name .= ' (' . ucfirst($result->gateway) . ')';
            }
            
            $checkouts[] = [
                'checkout_name' => $checkout_name,
                'payment_method' => $result->payment_method,
                'gateway' => $result->gateway,
                'conversions' => intval($result->conversions),
                'revenue' => floatval($result->revenue),
                'average_value' => floatval($result->average_value),
                'conversion_rate' => floatval($result->conversions), // Will calculate percentage with total
            ];
        }

        return $checkouts;
    }

    /**
     * Get cart abandonment rate by category
     */
    public static function get_cart_abandonment_by_category($start_date, $end_date) {
        global $wpdb;
        $carts_table = $wpdb->prefix . 'hng_abandoned_carts';
        $orders_table = $wpdb->prefix . 'hng_orders';

        // Get abandoned carts by category
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are safely prefixed with $wpdb->prefix
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                t.name as category_name,
                COUNT(DISTINCT ac.id) as abandoned_count,
                SUM(ac.total_value) as abandoned_value
             FROM {$carts_table} ac
             LEFT JOIN {$wpdb->prefix}hng_cart_items ci ON ac.id = ci.cart_id
             LEFT JOIN {$wpdb->prefix}term_relationships tr ON tr.object_id = ci.product_id
             LEFT JOIN {$wpdb->prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'product_category'
             LEFT JOIN {$wpdb->prefix}terms t ON t.term_id = tt.term_id
             WHERE ac.created_at BETWEEN %s AND %s
             AND ac.recovered = 0
             GROUP BY t.term_id
             ORDER BY abandoned_count DESC",
            $start_date,
            $end_date
        ));

        // Get total orders by category for conversion rate
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are safely prefixed with $wpdb->prefix
        $category_results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                t.name as category_name,
                COUNT(DISTINCT o.id) as orders_count
             FROM {$orders_table} o
             LEFT JOIN {$wpdb->prefix}hng_order_items oi ON o.id = oi.order_id
             LEFT JOIN {$wpdb->prefix}term_relationships tr ON tr.object_id = oi.product_id
             LEFT JOIN {$wpdb->prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'product_category'
             LEFT JOIN {$wpdb->prefix}terms t ON t.term_id = tt.term_id
             WHERE o.status IN ('processing', 'completed', 'hng-processing', 'hng-completed')
             AND o.created_at BETWEEN %s AND %s
             GROUP BY t.term_id",
            $start_date,
            $end_date
        ));

        // Map and calculate rates
        $category_orders = [];
        foreach ($category_results as $cat) {
            $category_orders[$cat->category_name] = intval($cat->orders_count);
        }

        $abandonment = [];
        foreach ($results as $result) {
            if (empty($result->category_name)) {
                continue;
            }
            
            $abandoned = intval($result->abandoned_count);
            $orders = $category_orders[$result->category_name] ?? 0;
            $total_carts = $abandoned + $orders;
            $rate = $total_carts > 0 ? round(($abandoned / $total_carts) * 100, 2) : 0;
            
            $abandonment[] = [
                'category_name' => $result->category_name,
                'abandoned_carts' => $abandoned,
                'completed_orders' => $orders,
                'total_carts' => $total_carts,
                'abandonment_rate' => $rate,
                'abandoned_value' => floatval($result->abandoned_value ?? 0),
            ];
        }

        return $abandonment;
    }

    /**
     * Get cart abandonment rate by checkout type
     */
    public static function get_cart_abandonment_by_checkout($start_date, $end_date) {
        global $wpdb;
        $carts_table = $wpdb->prefix . 'hng_abandoned_carts';
        $orders_table = $wpdb->prefix . 'hng_orders';

        // Get abandoned carts by checkout method
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are safely prefixed with $wpdb->prefix
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                ac.checkout_method,
                COUNT(*) as abandoned_count,
                SUM(ac.total_value) as abandoned_value
             FROM {$carts_table} ac
             WHERE ac.created_at BETWEEN %s AND %s
             AND ac.recovered = 0
             GROUP BY ac.checkout_method
             ORDER BY abandoned_count DESC",
            $start_date,
            $end_date
        ));

        // Get completed orders by checkout
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safely prefixed with $wpdb->prefix
        $order_results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                CONCAT(payment_method, '_', COALESCE(gateway, 'direct')) as checkout_type,
                payment_method,
                gateway,
                COUNT(*) as orders_count
             FROM {$orders_table}
             WHERE status IN ('processing', 'completed', 'hng-processing', 'hng-completed')
             AND created_at BETWEEN %s AND %s
             GROUP BY payment_method, gateway",
            $start_date,
            $end_date
        ));

        // Map orders
        $checkout_orders = [];
        foreach ($order_results as $order) {
            $checkout_key = $order->payment_method . '_' . ($order->gateway ?? 'direct');
            $checkout_orders[$checkout_key] = intval($order->orders_count);
        }

        $abandonment = [];
        foreach ($results as $result) {
            $checkout_method = !empty($result->checkout_method) ? $result->checkout_method : 'Desconhecido';
            $abandoned = intval($result->abandoned_count);
            $orders = $checkout_orders[$checkout_method] ?? 0;
            $total = $abandoned + $orders;
            $rate = $total > 0 ? round(($abandoned / $total) * 100, 2) : 0;
            
            $abandonment[] = [
                'checkout_method' => $checkout_method,
                'abandoned_carts' => $abandoned,
                'completed_orders' => $orders,
                'total_carts' => $total,
                'abandonment_rate' => $rate,
                'abandoned_value' => floatval($result->abandoned_value ?? 0),
            ];
        }

        return $abandonment;
    }

    /**
     * Get conversion funnel
     */
    public static function get_conversion_funnel($start_date, $end_date) {
        global $wpdb;

        // Use conversion events table for funnel metrics
        $events_table = $wpdb->prefix . 'hng_conversion_events';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safely prefixed with $wpdb->prefix
        $visitors = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT session_id) FROM {$events_table}
             WHERE event_type = 'page_view' AND created_at BETWEEN %s AND %s",
            $start_date,
            $end_date
        ));
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safely prefixed with $wpdb->prefix
        $cart_adds = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$events_table}
             WHERE event_type = 'add_to_cart' AND created_at BETWEEN %s AND %s",
            $start_date,
            $end_date
        ));
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safely prefixed with $wpdb->prefix
        $checkout_starts = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT session_id) FROM {$events_table}
             WHERE event_type = 'checkout_start' AND created_at BETWEEN %s AND %s",
            $start_date,
            $end_date
        ));

        // Completed orders
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safely prefixed with $wpdb->prefix
        $orders = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}hng_orders
             WHERE status IN ('processing', 'completed')
             AND created_at BETWEEN %s AND %s",
            $start_date,
            $end_date
        ));

        $visitors = intval($visitors ?? 0);
        $cart_adds = intval($cart_adds ?? 0);
        $checkout_starts = intval($checkout_starts ?? 0);
        $orders = intval($orders ?? 0);

        return [
            'visitors' => $visitors,
            'cart_conversion' => $visitors > 0 ? round(($cart_adds / $visitors) * 100, 2) : 0,
            'checkout_conversion' => $cart_adds > 0 ? round(($checkout_starts / $cart_adds) * 100, 2) : 0,
            'order_conversion' => $checkout_starts > 0 ? round(($orders / $checkout_starts) * 100, 2) : 0,
            'final_conversion' => $visitors > 0 ? round(($orders / $visitors) * 100, 2) : 0,
            'steps' => [
                ['name' => 'Visitantes', 'count' => $visitors, 'conversion' => 100],
                ['name' => 'Carrinho Adicionado', 'count' => $cart_adds, 'conversion' => $visitors > 0 ? round(($cart_adds / $visitors) * 100, 2) : 0],
                ['name' => 'Checkout Iniciado', 'count' => $checkout_starts, 'conversion' => $cart_adds > 0 ? round(($checkout_starts / $cart_adds) * 100, 2) : 0],
                ['name' => 'Pedido Concluído', 'count' => $orders, 'conversion' => $checkout_starts > 0 ? round(($orders / $checkout_starts) * 100, 2) : 0],
            ],
        ];
    }

    /**
     * Export conversion data as CSV
     */
    public static function export_conversion_csv($start_date, $end_date) {
        $analysis = self::get_conversion_analysis($start_date, $end_date);
        
        $csv = "RELATÓRIO DE CONVERSÁO E ABANDONO\n";
        $csv .= "Período: {$start_date} até {$end_date}\n\n";
        
        // Funnel
        $csv .= "FUNIL DE CONVERSÁO\n";
        $funnel = $analysis['conversion_funnel'];
        $csv .= "Etapa,Visitantes,Taxa de Conversão\n";
        foreach ($funnel['steps'] as $step) {
            $csv .= $step['name'] . "," . $step['count'] . "," . $step['conversion'] . "%\n";
        }
        
        $csv .= "\n";
        
        // Top converting pages
        $csv .= "PÁGINAS COM MAIS CONVERSÕES\n";
        $csv .= "Página,Conversões,Receita,Ticket Médio\n";
        foreach ($analysis['top_converting_templates'] as $page) {
            $csv .= $page['page_title'] . ",";
            $csv .= $page['conversions'] . ",";
            $csv .= "R$ " . number_format($page['revenue'], 2, ',', '.') . ",";
            $csv .= "R$ " . number_format($page['average_value'], 2, ',', '.') . "\n";
        }
        
        $csv .= "\n";
        
        // Top converting checkouts
        $csv .= "CHECKOUTS COM MAIS CONVERSÕES\n";
        $csv .= "Checkout,Conversões,Receita,Ticket Médio\n";
        foreach ($analysis['top_converting_checkouts'] as $checkout) {
            $csv .= $checkout['checkout_name'] . ",";
            $csv .= $checkout['conversions'] . ",";
            $csv .= "R$ " . number_format($checkout['revenue'], 2, ',', '.') . ",";
            $csv .= "R$ " . number_format($checkout['average_value'], 2, ',', '.') . "\n";
        }
        
        $csv .= "\n";
        
        // Cart abandonment by category
        $csv .= "TAXA DE ABANDONO POR CATEGORIA\n";
        $csv .= "Categoria,Carrinhos Abandonados,Pedidos Concluídos,Taxa de Abandono\n";
        foreach ($analysis['cart_abandonment_by_category'] as $cat) {
            $csv .= $cat['category_name'] . ",";
            $csv .= $cat['abandoned_carts'] . ",";
            $csv .= $cat['completed_orders'] . ",";
            $csv .= $cat['abandonment_rate'] . "%\n";
        }
        
        return $csv;
    }
}
