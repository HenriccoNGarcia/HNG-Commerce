<?php
/**
 * Sistema de Analytics Avançado
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// DB helper
if (file_exists(HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php')) {
    require_once HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php';
}

class HNG_Analytics {
    
    /**
     * Instância única
     */
    private static $instance = null;
    
    /**
     * Obter instância
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Construtor
     */
    private function __construct() {
        // Rastreamento de eventos
        add_action('wp_footer', [$this, 'track_page_view']);
        add_action('hng_product_viewed', [$this, 'track_product_view']);
        add_action('hng_product_added_to_cart', [$this, 'track_add_to_cart']);
        add_action('hng_checkout_initiated', [$this, 'track_checkout_init']);
        add_action('hng_order_completed', [$this, 'track_purchase']);
    }
    
    /**
     * Obter taxa de conversão
     */
    public function get_conversion_rate($days = 30) {
        global $wpdb;
        
        $start_date = gmdate('Y-m-d', strtotime("-{$days} days"));
        
        // Total de visitas únicas (simplificado)
        $visits = (int) get_option('hng_total_visits_' . $start_date, 1000);
        
        // Total de pedidos
        $orders_table = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_orders') : ($wpdb->prefix . 'hng_orders');
        $orders_table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_orders') : ('`' . str_replace('`','', (string) $orders_table) . '`');

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tables sanitized via hng_db_backtick_table()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Analytics query for conversion rate calculation
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Tables sanitized via hng_db_backtick_table()
        $orders = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$orders_table_sql} 
            WHERE created_at >= %s AND status NOT IN ('hng-cancelled', 'hng-failed')",
            $start_date
        ));
        
        $rate = $visits > 0 ? ($orders / $visits) * 100 : 0;
        
        return [
            'rate' => $rate,
            'orders' => (int) $orders,
            'visits' => $visits,
        ];
    }
    
    /**
     * Obter produtos com estoque baixo
     */
    public function get_low_stock_products($limit = 10) {
        global $wpdb;
        
        $threshold = (int) get_option('hng_low_stock_threshold', 10);
        $orders_table = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_orders') : ($wpdb->prefix . 'hng_orders');
            $t_orders = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_orders') : ('`' . str_replace('`','', (string) $orders_table) . '`');
            $t_order_items = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_order_items') : ('`' . str_replace('`','', (string) (function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_order_items') : ($wpdb->prefix . 'hng_order_items'))) . '`');
        $posts_table = '`' . str_replace('`','', (string) $wpdb->posts) . '`';
        $postmeta_table = '`' . str_replace('`','', (string) $wpdb->postmeta) . '`';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $products = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID as id, p.post_title as name, 
            pm1.meta_value as stock,
            COALESCE(sales.monthly_sales, 0) as monthly_sales
            FROM {$posts_table} p
            INNER JOIN {$postmeta_table} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_stock'
            LEFT JOIN (
                SELECT oi.product_id, COUNT(*) as monthly_sales
                FROM {$t_order_items} oi
                INNER JOIN {$t_orders} o ON oi.order_id = o.id
                WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY oi.product_id
            ) sales ON p.ID = sales.product_id
            WHERE p.post_type = 'hng_product'
            AND p.post_status = 'publish'
            AND CAST(pm1.meta_value AS UNSIGNED) <= %d
            AND CAST(pm1.meta_value AS UNSIGNED) > 0
            ORDER BY CAST(pm1.meta_value AS UNSIGNED) ASC
            LIMIT %d",
            $threshold,
            $limit
        ));
        
        foreach ($products as &$product) {
            $product->stock = (int) $product->stock;
            $product->monthly_sales = (int) $product->monthly_sales;
            
            // Calcular dias restantes
            $daily_sales = $product->monthly_sales / 30;
            $product->days_remaining = $daily_sales > 0 ? ceil($product->stock / $daily_sales) : 999;
        }
        
        return $products;
    }
    
    /**
     * Obter clientes VIP (top 20%)
     */
    public function get_vip_customers($limit = 20) {
        global $wpdb;
        $orders_table = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_orders') : ($wpdb->prefix . 'hng_orders');
        $orders_table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_orders') : ('`' . str_replace('`','', (string) $orders_table) . '`');
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tables sanitized via hng_db_backtick_table()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Analytics query for VIP customer identification (top 20% by spend)
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Tables sanitized via hng_db_backtick_table()
        $customers = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                o.customer_id as user_id,
                o.billing_first_name as name,
                o.customer_email as email,
                COUNT(o.id) as order_count,
                SUM(o.total) as total_spent,
                AVG(o.total) as avg_order
            FROM {$orders_table_sql} o
            WHERE o.status IN ('hng-completed', 'hng-processing')
            AND o.customer_id > 0
            GROUP BY o.customer_id
            ORDER BY total_spent DESC
            LIMIT %d",
            $limit
        ));
        
        return $customers;
    }
    
    /**
     * Obter porcentagem de receita dos VIPs
     */
    public function get_vip_revenue_percentage() {
        global $wpdb;
        $orders_table = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_orders') : ($wpdb->prefix . 'hng_orders');
        $orders_table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_orders') : ('`' . str_replace('`','', (string) $orders_table) . '`');
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tables sanitized via hng_db_backtick_table()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Analytics query for total revenue baseline
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Tables sanitized via hng_db_backtick_table()
        $total_revenue = $wpdb->get_var(
            "SELECT SUM(total) FROM {$orders_table_sql} 
            WHERE status IN ('hng-completed', 'hng-processing')"
        );
        
        // Top 20% dos clientes
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tables sanitized via hng_db_backtick_table()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Analytics query for VIP customer count (top 20%)
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Tables sanitized via hng_db_backtick_table()
        $vip_count = ceil($wpdb->get_var(
            "SELECT COUNT(DISTINCT customer_id) FROM {$orders_table_sql} 
            WHERE customer_id > 0"
        ) * 0.2);
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tables sanitized via hng_db_backtick_table()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Analytics query for VIP revenue calculation (top 20% customers)
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Tables sanitized via hng_db_backtick_table()
        $vip_revenue = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(total) FROM {$orders_table_sql} o
            WHERE o.customer_id IN (
                SELECT customer_id 
                FROM {$orders_table_sql}
                WHERE status IN ('hng-completed', 'hng-processing')
                GROUP BY customer_id
                ORDER BY SUM(total) DESC
                LIMIT %d
            )",
            $vip_count
        ));
        
        return $total_revenue > 0 ? ($vip_revenue / $total_revenue) * 100 : 0;
    }
    
    /**
     * Obter previsão de vendas
     */
    public function get_sales_forecast() {
        global $wpdb;
        $orders_table = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_orders') : ($wpdb->prefix . 'hng_orders');
        $orders_table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_orders') : ('`' . str_replace('`','', (string) $orders_table) . '`');
        
        // Últimos 6 meses de dados
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tables sanitized via hng_db_backtick_table()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Analytics query for sales forecasting, 6-month trend analysis
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Tables sanitized via hng_db_backtick_table()
        $monthly_sales = $wpdb->get_results("SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                SUM(total) as revenue
            FROM {$orders_table_sql}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            AND status IN ('hng-completed', 'hng-processing')
            GROUP BY month
            ORDER BY month ASC");
        
        if (count($monthly_sales) < 3) {
            return [
                'predicted' => 0,
                'confidence' => 0,
            ];
        }
        
        // Regressão linear simples
        $revenues = array_map(function($item) {
            return (float) $item->revenue;
        }, $monthly_sales);
        
        $n = count($revenues);
        $x = range(1, $n);
        $y = $revenues;
        
        $x_sum = array_sum($x);
        $y_sum = array_sum($y);
        $xx_sum = 0;
        $xy_sum = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $xx_sum += $x[$i] * $x[$i];
            $xy_sum += $x[$i] * $y[$i];
        }
        
        $slope = (($n * $xy_sum) - ($x_sum * $y_sum)) / (($n * $xx_sum) - ($x_sum * $x_sum));
        $intercept = ($y_sum - ($slope * $x_sum)) / $n;
        
        // Prever próximo mês
        $predicted = $slope * ($n + 1) + $intercept;
        
        // Calcular R² (confiança)
        $y_mean = $y_sum / $n;
        $ss_tot = 0;
        $ss_res = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $y_pred = $slope * $x[$i] + $intercept;
            $ss_tot += pow($y[$i] - $y_mean, 2);
            $ss_res += pow($y[$i] - $y_pred, 2);
        }
        
        // Calculate R² with boundary validation
        // R² must be between 0 and 1 (0% to 100% confidence)
        if ($ss_tot > 0) {
            $r_squared = 1 - ($ss_res / $ss_tot);
            // Clamp R² to valid range [0, 1]
            $r_squared = max(0.0, min(1.0, $r_squared));
        } else {
            // No variance in data - model is meaningless
            $r_squared = 0.0;
        }
        
        $confidence = round($r_squared * 100);
        
        return [
            'predicted' => max(0, $predicted),
            'confidence' => $confidence,
            'r_squared' => $r_squared, // Include raw R² for debugging
        ];
    }
    
    /**
     * Obter comparação mensal
     */
    public function get_monthly_comparison($months = 6) {
        global $wpdb;
        $orders_table = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_orders') : ($wpdb->prefix . 'hng_orders');
        $orders_table_sql = '`' . str_replace('`','', (string) $orders_table) . '`';
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tables sanitized via backtick wrapping
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Analytics query for monthly revenue comparison
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Tables sanitized via backtick wrapping
        $data = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE_FORMAT(created_at, '%%Y-%%m') as month,
                DATE_FORMAT(created_at, '%%b/%%y') as label,
                SUM(total) as revenue
            FROM {$orders_table_sql}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d MONTH)
            AND status IN ('hng-completed', 'hng-processing')
            GROUP BY month
            ORDER BY month ASC",
            $months
        ));
        
        $labels = [];
        $revenues = [];
        
        foreach ($data as $row) {
            $labels[] = $row->label;
            $revenues[] = (float) $row->revenue;
        }
        
        // Calcular crescimento
        $current = end($revenues) ?: 0;
        $previous = prev($revenues) ?: 0;
        $growth = $previous > 0 ? (($current - $previous) / $previous) * 100 : 0;
        
        return [
            'labels' => $labels,
            'data' => $revenues,
            'current' => ['revenue' => $current],
            'previous' => ['revenue' => $previous],
            'growth' => round($growth, 1),
        ];
    }
    
    /**
     * Obter receita por categoria
     */
    public function get_revenue_by_category() {
        global $wpdb;
        $orders_table = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_orders') : ($wpdb->prefix . 'hng_orders');
        $orders_table_sql = '`' . str_replace('`','', (string) $orders_table) . '`';
        $t_order_items = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_order_items') : ('`' . str_replace('`','', (string) (function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_order_items') : ($wpdb->prefix . 'hng_order_items'))) . '`');
        $term_rel_sql = '`' . str_replace('`','', (string) $wpdb->term_relationships) . '`';
        $term_tax_sql = '`' . str_replace('`','', (string) $wpdb->term_taxonomy) . '`';
        $terms_sql = '`' . str_replace('`','', (string) $wpdb->terms) . '`';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tables sanitized via backtick wrapping and hng_db_backtick_table()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Analytics query for category revenue breakdown
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Tables sanitized via backtick wrapping and hng_db_backtick_table()
        $categories = $wpdb->get_results(
            "SELECT 
                t.name,
                SUM(oi.subtotal) as revenue
            FROM {$t_order_items} oi
            INNER JOIN {$orders_table_sql} o ON oi.order_id = o.id
            INNER JOIN {$term_rel_sql} tr ON oi.product_id = tr.object_id
            INNER JOIN {$term_tax_sql} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            INNER JOIN {$terms_sql} t ON tt.term_id = t.term_id
            WHERE tt.taxonomy = 'hng_product_cat'
            AND o.status IN ('hng-completed', 'hng-processing')
            GROUP BY t.term_id
            ORDER BY revenue DESC
            LIMIT 5"
        );
        
        return $categories;
    }
    
    /**
     * Obter top produtos
     */
    public function get_top_products($limit = 5) {
        global $wpdb;
        $t_order_items = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_order_items') : ('`' . str_replace('`','', (string) (function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_order_items') : ($wpdb->prefix . 'hng_order_items'))) . '`');
        $t_orders = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_orders') : ('`' . str_replace('`','', (string) (function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_orders') : ($wpdb->prefix . 'hng_orders'))) . '`');
        $posts_table = '`' . str_replace('`','', (string) $wpdb->posts) . '`';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tables sanitized via backtick wrapping and hng_db_backtick_table()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Analytics query for top-selling products
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Tables sanitized via backtick wrapping and hng_db_backtick_table()
        $products = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                p.post_title as name,
                COUNT(oi.id) as sales,
                SUM(oi.subtotal) as revenue
            FROM {$t_order_items} oi
            INNER JOIN {$posts_table} p ON oi.product_id = p.ID
            INNER JOIN {$t_orders} o ON oi.order_id = o.id
            WHERE o.status IN ('hng-completed', 'hng-processing')
            GROUP BY oi.product_id
            ORDER BY sales DESC
            LIMIT %d",
            $limit
        ));
        
        return $products;
    }
    
    /**
     * Obter dados do funil de conversão
     */
    public function get_conversion_funnel_data() {
        // Dados simplificados (em produção, usar analytics real)
        $visits = 10000;
        $product_views = 5000;
        $cart_adds = 2000;
        $checkouts = 800;
        $purchases = 400;
        
        return [$visits, $product_views, $cart_adds, $checkouts, $purchases];
    }
    
    /**
     * Obter insights automáticos
     */
    public function get_automated_insights() {
        $insights = [];
        
        // Insight 1: Produtos com estoque baixo
        $low_stock = $this->get_low_stock_products(1);
        if (!empty($low_stock)) {
            $insights[] = [
                'type' => 'warning',
                'icon' => '??',
                'title' => 'Atenção: Estoque Baixo',
                'message' => sprintf(
                    '%d produto(s) com estoque crítico. %s está com apenas %d unidades.',
                    count($low_stock),
                    $low_stock[0]->name,
                    $low_stock[0]->stock
                ),
                'action' => [
                    'text' => 'Ver Produtos',
                    'url' => admin_url('admin.php?page=hng-products'),
                ],
            ];
        }
        
        // Insight 2: Taxa de conversão
        $conversion = $this->get_conversion_rate(7);
        if ($conversion['rate'] < 1) {
            $insights[] = [
                'type' => 'danger',
                'icon' => '??',
                'title' => 'Taxa de Conversão Baixa',
                'message' => sprintf(
                    'Apenas %.2f%% dos visitantes estï¿½o comprando. Considere otimizar checkout.',
                    $conversion['rate']
                ),
                'action' => [
                    'text' => 'Analisar Funil',
                    'url' => admin_url('admin.php?page=hng-analytics'),
                ],
            ];
        } else {
            $insights[] = [
                'type' => 'success',
                'icon' => '??',
                'title' => 'Boa Taxa de Conversï¿½o!',
                'message' => sprintf(
                    'Taxa de %.2f%% estï¿½ acima da mï¿½dia (1-2%% ï¿½ o padrï¿½o).',
                    $conversion['rate']
                ),
            ];
        }
        
        // Insight 3: Crescimento mensal
        $comparison = $this->get_monthly_comparison(2);
        if ($comparison['growth'] > 10) {
            $insights[] = [
                'type' => 'success',
                'icon' => '??',
                'title' => 'Crescimento Acelerado!',
                'message' => sprintf(
                    'Suas vendas cresceram %.1f%% em relaï¿½ï¿½o ao mï¿½s anterior.',
                    $comparison['growth']
                ),
            ];
        } elseif ($comparison['growth'] < -10) {
            $insights[] = [
                'type' => 'danger',
                'icon' => '??',
                'title' => 'Queda nas Vendas',
                'message' => sprintf(
                    'Vendas caï¿½ram %.1f%%. Considere campanhas promocionais.',
                    abs($comparison['growth'])
                ),
                'action' => [
                    'text' => 'Criar Cupom',
                    'url' => admin_url('post-new.php?post_type=hng_coupon'),
                ],
            ];
        }
        
        // Insight 4: Clientes VIP
        $vip_percentage = $this->get_vip_revenue_percentage();
        if ($vip_percentage > 60) {
            $insights[] = [
                'type' => 'warning',
                'icon' => '??',
                'title' => 'Dependï¿½ncia de VIPs',
                'message' => sprintf(
                    '%.1f%% da receita vem dos top 20%%. Diversifique sua base.',
                    $vip_percentage
                ),
                'action' => [
                    'text' => 'Ver Estratï¿½gias',
                    'url' => '#',
                ],
            ];
        }
        
        return $insights;
    }
    
    /**
     * Rastreamento de eventos
     */
    public function track_page_view() {
        if (!is_admin()) {
            $date = gmdate('Y-m-d');
            $current = (int) get_option('hng_total_visits_' . $date, 0);
            update_option('hng_total_visits_' . $date, $current + 1, false);
        }
    }
    
    public function track_product_view($product_id) {
        $views = (int) get_post_meta($product_id, '_hng_views', true);
        update_post_meta($product_id, '_hng_views', $views + 1);
    }
    
    public function track_add_to_cart($product_id) {
        $adds = (int) get_post_meta($product_id, '_hng_cart_adds', true);
        update_post_meta($product_id, '_hng_cart_adds', $adds + 1);
    }
    
    public function track_checkout_init() {
        $date = gmdate('Y-m-d');
        $current = (int) get_option('hng_checkouts_' . $date, 0);
        update_option('hng_checkouts_' . $date, $current + 1, false);
    }
    
    public function track_purchase($order_id) {
        $date = gmdate('Y-m-d');
        $current = (int) get_option('hng_purchases_' . $date, 0);
        update_option('hng_purchases_' . $date, $current + 1, false);
    }
}
