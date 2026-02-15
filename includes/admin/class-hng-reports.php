<?php
/**
 * Relatórios de Vendas
 *
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Reports {
    
    /**
     * Instância única
     */
    private static $instance = null;
    
    /**
     * Período atual
     */
    private $date_from = '';
    private $date_to = '';
    
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
        // Definir período padrão (últimos 30 dias)
        $this->date_to = gmdate('Y-m-d');
        $this->date_from = gmdate('Y-m-d', strtotime('-30 days'));
    }
    
    /**
     * Definir período personalizado
     */
    public function set_date_range($from, $to) {
        if (function_exists('wp_unslash')) {
            $from = wp_unslash($from);
            $to = wp_unslash($to);
        }

        $this->date_from = $this->sanitize_date($from);
        $this->date_to = $this->sanitize_date($to);
    }

    /**
     * Sanitiza uma data no formato YYYY-MM-DD
     * Retorna string vazia se inválida.
     */
    private function sanitize_date($date) {
        $date = (string) $date;
        $date = trim($date);
        $date = sanitize_text_field($date);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        return '';
    }
    
    /**
     * Obter estatísticas gerais
     */
    public function get_general_stats() {
        global $wpdb;
        if (file_exists(HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php')) {
            require_once HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php';
        }

        $orders_table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_orders') : ('`' . $wpdb->prefix . 'hng_orders`');

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reporting query for order statistics
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $orders_table_sql sanitized via hng_db_backtick_table()
        $stats = $wpdb->get_row( $wpdb->prepare(
            "SELECT COUNT(*) as total_orders, SUM(total) as total_revenue, AVG(total) as average_order_value 
            FROM {$orders_table_sql} WHERE DATE(created_at) BETWEEN %s AND %s AND status NOT IN ('cancelled', 'refunded')",
            $this->date_from, 
            $this->date_to
        ) );
        
        // Calcular lucro (Receita - Custos - Comissões)
        $order_items_table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_order_items') : ('`' . $wpdb->prefix . 'hng_order_items`');

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reporting query for profit calculation
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Tables sanitized via hng_db_backtick_table()
        $profit_stats = $wpdb->get_row( $wpdb->prepare(
            "SELECT SUM((oi.price - COALESCE(oi.product_cost, 0) - oi.commission) * oi.quantity) as total_profit 
            FROM {$order_items_table_sql} oi INNER JOIN {$orders_table_sql} o ON oi.order_id = o.id 
            WHERE DATE(o.created_at) BETWEEN %s AND %s AND o.status NOT IN ('cancelled', 'refunded')",
            $this->date_from, 
            $this->date_to
        ) );
        
        return [
            'total_orders' => (int) ($stats->total_orders ?? 0),
            'total_revenue' => (float) ($stats->total_revenue ?? 0),
            'total_profit' => (float) ($profit_stats->total_profit ?? 0),
            'average_order_value' => (float) ($stats->average_order_value ?? 0),
        ];
    }
    
    /**
     * Obter vendas por dia
     */
    public function get_sales_by_day() {
        global $wpdb;
        // datas já sanitizadas via set_date_range
        if (file_exists(HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php')) {
            require_once HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php';
        }
        $orders_table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_orders') : ('`' . $wpdb->prefix . 'hng_orders`');
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT DATE(created_at) as date, COUNT(*) as orders, SUM(total) as revenue, SUM(commission) as commission 
            FROM {$orders_table_sql} WHERE DATE(created_at) BETWEEN %s AND %s AND status NOT IN ('cancelled', 'refunded') 
            GROUP BY DATE(created_at) ORDER BY date ASC",
            $this->date_from, 
            $this->date_to
        ) );
    }
    
    /**
     * Obter produtos mais vendidos
     */
    public function get_top_products($limit = 10) {
        global $wpdb;
        $limit = absint($limit);

        if (file_exists(HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php')) {
            require_once HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php';
        }
        $orders_table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_orders') : ('`' . $wpdb->prefix . 'hng_orders`');
        $order_items_table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_order_items') : ('`' . $wpdb->prefix . 'hng_order_items`');

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reporting query for top products
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Tables sanitized via hng_db_backtick_table()
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT oi.product_id, oi.product_name, SUM(oi.quantity) as total_quantity, SUM(oi.subtotal) as total_revenue, COUNT(DISTINCT o.id) as order_count 
            FROM {$order_items_table_sql} oi INNER JOIN {$orders_table_sql} o ON oi.order_id = o.id 
            WHERE DATE(o.created_at) BETWEEN %s AND %s AND o.status NOT IN ('cancelled', 'refunded') 
            GROUP BY oi.product_id, oi.product_name ORDER BY total_quantity DESC LIMIT %d",
            $this->date_from, 
            $this->date_to, 
            $limit
        ) );
    }
    
    /**
     * Obter categorias mais vendidas
     */
    public function get_top_categories($limit = 10) {
        global $wpdb;
        $limit = absint($limit);

        if (file_exists(HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php')) {
            require_once HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php';
        }
        $orders_table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_orders') : ('`' . $wpdb->prefix . 'hng_orders`');
        $order_items_table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_order_items') : ('`' . $wpdb->prefix . 'hng_order_items`');

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reporting query for top categories
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Tables sanitized via hng_db_backtick_table()
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT t.name as category_name, SUM(oi.quantity) as total_sold, SUM(oi.subtotal) as revenue 
            FROM {$order_items_table_sql} oi 
            INNER JOIN {$orders_table_sql} o ON oi.order_id = o.id
            INNER JOIN {$wpdb->term_relationships} tr ON oi.product_id = tr.object_id
            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'product_cat'
            INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
            WHERE DATE(o.created_at) BETWEEN %s AND %s AND o.status NOT IN ('cancelled', 'refunded') 
            GROUP BY t.term_id, t.name 
            ORDER BY revenue DESC 
            LIMIT %d",
            $this->date_from, 
            $this->date_to, 
            $limit
        ) );
    }
    
    /**
     * Obter distribuição por status
     */
    public function get_orders_by_status() {
        global $wpdb;
        // datas já sanitizadas via set_date_range
        if (file_exists(HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php')) {
            require_once HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php';
        }
        $orders_table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_orders') : ('`' . $wpdb->prefix . 'hng_orders`');
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tables sanitized via hng_db_backtick_table()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reporting query for order status distribution
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Tables sanitized via hng_db_backtick_table()
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT status, COUNT(*) as count, SUM(total) as revenue FROM {$orders_table_sql} WHERE DATE(created_at) BETWEEN %s AND %s GROUP BY status ORDER BY count DESC",
            $this->date_from, 
            $this->date_to
        ) );
    }
    
    /**
     * Obter distribuição por método de pagamento
     */
    public function get_orders_by_payment_method() {
        global $wpdb;
        // datas já sanitizadas via set_date_range
        if (file_exists(HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php')) {
            require_once HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php';
        }
        $orders_table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_orders') : ('`' . $wpdb->prefix . 'hng_orders`');
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reporting query for payment methods
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $orders_table_sql sanitized via hng_db_backtick_table()
        return $wpdb->get_results( $wpdb->prepare( "SELECT payment_method, COUNT(*) as count, SUM(total) as revenue FROM {$orders_table_sql} WHERE DATE(created_at) BETWEEN %s AND %s AND status NOT IN ('cancelled', 'refunded') GROUP BY payment_method ORDER BY count DESC", $this->date_from, $this->date_to ) );
    }
    
    /**
     * Obter taxa de conversão (se houver dados de visitas)
     */
    public function get_conversion_rate() {
        global $wpdb;
        // datas já sanitizadas via set_date_range
        if (file_exists(HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php')) {
            require_once HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php';
        }
        $orders_table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_orders') : ('`' . $wpdb->prefix . 'hng_orders`');

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reporting query for conversion rate calculation
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $orders_table_sql sanitized via hng_db_backtick_table()
        $orders = $wpdb->get_var($wpdb->prepare( "SELECT COUNT(*) FROM {$orders_table_sql} WHERE DATE(created_at) BETWEEN %s AND %s AND status NOT IN ('cancelled', 'refunded')", 
            $this->date_from, 
            $this->date_to
        ));
        
        // Para calcular conversão real, precisaria integrar com analytics
        // Por enquanto retorna estimativa baseada em pedidos
        $estimated_visits = $orders * 10; // Estimativa: 10 visitas por pedido
        $conversion_rate = $estimated_visits > 0 ? ($orders / $estimated_visits) * 100 : 0;
        
        return [
            'orders' => (int) $orders,
            'estimated_visits' => $estimated_visits,
            'conversion_rate' => round($conversion_rate, 2),
        ];
    }
    
    /**
     * Obter comissões por produto
     */
    public function get_commission_by_product($limit = 10) {
        global $wpdb;
        $limit = absint($limit);
        $t_order_items = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_order_items') : ('`' . $wpdb->prefix . 'hng_order_items`');
        $t_orders = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_orders') : ('`' . $wpdb->prefix . 'hng_orders`');

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tables sanitized via hng_db_backtick_table()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reporting query for commission analysis
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Tables sanitized via hng_db_backtick_table()
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                oi.product_id,
                oi.product_name,
                SUM(oi.commission) as total_commission,
                SUM(oi.subtotal) as total_revenue,
                (SUM(oi.commission) / SUM(oi.subtotal) * 100) as commission_rate
            FROM {$t_order_items} oi
            INNER JOIN {$t_orders} o ON oi.order_id = o.id
            WHERE DATE(o.created_at) BETWEEN %s AND %s
            AND o.status NOT IN ('cancelled', 'refunded')
            GROUP BY oi.product_id, oi.product_name
            ORDER BY total_commission DESC
            LIMIT %d",
            $this->date_from,
            $this->date_to,
            $limit
        ));
    }
    
    /**
     * Obter clientes com mais pedidos
     */
    public function get_top_customers($limit = 10) {
        global $wpdb;
        $limit = absint($limit);
        $t_orders = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_orders') : ('`' . $wpdb->prefix . 'hng_orders`');

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                o.customer_id,
                o.billing_email,
                CONCAT(o.billing_first_name, ' ', o.billing_last_name) as customer_name,
                COUNT(*) as order_count,
                SUM(o.total) as total_spent
            FROM {$t_orders} o
            WHERE DATE(o.created_at) BETWEEN %s AND %s
            AND o.status NOT IN ('cancelled', 'refunded')
            AND o.customer_id > 0
            GROUP BY o.customer_id, o.billing_email, customer_name
            ORDER BY total_spent DESC
            LIMIT %d",
            $this->date_from,
            $this->date_to,
            $limit
        ));
    }
    
    /**
     * Comparar com período anterior
     */
    public function get_period_comparison() {
        global $wpdb;
        
        // Período atual
        $current = $this->get_general_stats();
        
        // Calcular período anterior (mesmo número de dias)
        $days_diff = (strtotime($this->date_to) - strtotime($this->date_from)) / (60 * 60 * 24);
        $prev_date_to = gmdate('Y-m-d', strtotime($this->date_from . ' -1 day'));
        $prev_date_from = gmdate('Y-m-d', strtotime($prev_date_to . " -{$days_diff} days"));
        
        // Buscar dados do período anterior
        $t_orders = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_orders') : ('`' . $wpdb->prefix . 'hng_orders`');

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reporting query for period comparison
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $t_orders sanitized via hng_db_backtick_table()
        $previous = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_orders,
                SUM(total) as total_revenue
            FROM {$t_orders}
            WHERE DATE(created_at) BETWEEN %s AND %s
            AND status NOT IN ('cancelled', 'refunded')",
            $prev_date_from,
            $prev_date_to
        ));
        
        // Calcular lucro do perï¿½odo anterior
        $t_order_items = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_order_items') : ('`' . $wpdb->prefix . 'hng_order_items`');
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reporting query for previous period profit
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Tables sanitized via hng_db_backtick_table()
        $prev_profit_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                SUM((oi.price - COALESCE(oi.product_cost, 0) - oi.commission) * oi.quantity) as total_profit
            FROM {$t_order_items} oi
            INNER JOIN {$t_orders} o ON oi.order_id = o.id
            WHERE DATE(o.created_at) BETWEEN %s AND %s
            AND o.status NOT IN ('cancelled', 'refunded')",
            $prev_date_from,
            $prev_date_to
        ));
        
        // Calcular variações percentuais
        $prev_orders = (int) ($previous->total_orders ?? 0);
        $prev_revenue = (float) ($previous->total_revenue ?? 0);
        $prev_profit = (float) ($prev_profit_stats->total_profit ?? 0);
        
        return [
            'current' => $current,
            'previous' => [
                'total_orders' => $prev_orders,
                'total_revenue' => $prev_revenue,
                'total_profit' => $prev_profit,
            ],
            'changes' => [
                'orders_change' => $prev_orders > 0 ? round((($current['total_orders'] - $prev_orders) / $prev_orders) * 100, 2) : 0,
                'revenue_change' => $prev_revenue > 0 ? round((($current['total_revenue'] - $prev_revenue) / $prev_revenue) * 100, 2) : 0,
                'profit_change' => $prev_profit > 0 ? round((($current['total_profit'] - $prev_profit) / $prev_profit) * 100, 2) : 0,
            ]
        ];
    }
    
    /**
     * Obter cupons mais usados
     */
    public function get_top_coupons($limit = 10) {
        global $wpdb;
        $limit = absint($limit);

        $t_coupon_usage = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_coupon_usage') : ('`' . $wpdb->prefix . 'hng_coupon_usage`');
        $t_orders = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_orders') : ('`' . $wpdb->prefix . 'hng_orders`');

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tables sanitized via hng_db_backtick_table()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reporting query for coupon usage
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Tables sanitized via hng_db_backtick_table()
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                cu.coupon_id,
                p.post_title as coupon_code,
                COUNT(*) as usage_count,
                COUNT(DISTINCT cu.customer_id) as unique_users,
                COUNT(DISTINCT o.id) as orders_with_coupon,
                SUM(o.discount) as total_discount
            FROM {$t_coupon_usage} cu
            INNER JOIN {$wpdb->posts} p ON cu.coupon_id = p.ID
            LEFT JOIN {$t_orders} o ON cu.order_id = o.id
            WHERE DATE(cu.used_at) BETWEEN %s AND %s
            GROUP BY cu.coupon_id, p.post_title
            ORDER BY usage_count DESC
            LIMIT %d",
            $this->date_from,
            $this->date_to,
            $limit
        ));
    }
    
    /**
     * Obter estatísticas financeiras com taxas reais
     */
    public function get_financial_stats() {
        global $wpdb;
        // datas já sanitizadas via set_date_range
        
        $t_transactions = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_transactions') : ('`' . $wpdb->prefix . 'hng_transactions`');
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tables sanitized via hng_db_backtick_table()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reporting query for financial statistics, aggregates transaction fees and revenue
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Tables sanitized via hng_db_backtick_table()
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_transactions,
                SUM(gross_amount) as receita_bruta,
                SUM(gateway_fee) as taxas_gateway,
                SUM(plugin_fee) as taxas_plugin,
                SUM(net_amount) as lucro_liquido,
                AVG(plugin_fee / gross_amount * 100) as taxa_media_percentual
            FROM {$t_transactions}
            WHERE DATE(created_at) BETWEEN %s AND %s",
            $this->date_from,
            $this->date_to
        ));
        
        return [
            'total_transactions' => (int) ($stats->total_transactions ?? 0),
            'receita_bruta' => (float) ($stats->receita_bruta ?? 0),
            'taxas_gateway' => (float) ($stats->taxas_gateway ?? 0),
            'taxas_plugin' => (float) ($stats->taxas_plugin ?? 0),
            'lucro_liquido' => (float) ($stats->lucro_liquido ?? 0),
            'taxa_media_percentual' => (float) ($stats->taxa_media_percentual ?? 0),
        ];
    }
    
    /**
     * Obter breakdown por gateway
     */
    public function get_stats_by_gateway() {
        global $wpdb;
        // datas já sanitizadas via set_date_range
        
        $t_transactions = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_transactions') : ('`' . $wpdb->prefix . 'hng_transactions`');
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tables sanitized via hng_db_backtick_table()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reporting query for gateway breakdown analysis
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Tables sanitized via hng_db_backtick_table()
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                gateway_name,
                COUNT(*) as total_transacoes,
                SUM(gross_amount) as receita,
                SUM(gateway_fee) as taxa_gateway,
                SUM(plugin_fee) as taxa_plugin,
                SUM(net_amount) as liquido,
                AVG(gateway_fee / gross_amount * 100) as taxa_gateway_media
            FROM {$t_transactions}
            WHERE DATE(created_at) BETWEEN %s AND %s
            GROUP BY gateway_name
            ORDER BY receita DESC",
            $this->date_from,
            $this->date_to
        ));
    }
    
    /**
     * Obter breakdown por tier (faixas de taxa)
     */
    public function get_stats_by_tier() {
        global $wpdb;
        // datas já sanitizadas via set_date_range
        
        $t_transactions = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_transactions') : ('`' . $wpdb->prefix . 'hng_transactions`');
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tables sanitized via hng_db_backtick_table()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reporting query for tier-based fee analysis
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Tables sanitized via hng_db_backtick_table()
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                plugin_tier,
                COUNT(*) as total_transacoes,
                SUM(gross_amount) as receita,
                SUM(plugin_fee) as taxa_total,
                AVG(plugin_fee / gross_amount * 100) as taxa_percentual,
                MIN(created_at) as primeira_transacao,
                MAX(created_at) as ultima_transacao
            FROM {$t_transactions}
            WHERE DATE(created_at) BETWEEN %s AND %s
            GROUP BY plugin_tier
            ORDER BY plugin_tier ASC",
            $this->date_from,
            $this->date_to
        ));
    }
    
    /**
     * Obter breakdown por método de pagamento
     */
    public function get_stats_by_payment_method() {
        global $wpdb;
        // datas já sanitizadas via set_date_range
        
        $t_transactions = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_transactions') : ('`' . $wpdb->prefix . 'hng_transactions`');
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tables sanitized via hng_db_backtick_table()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reporting query for payment method distribution analysis
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Tables sanitized via hng_db_backtick_table()
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                payment_method,
                COUNT(*) as total_transacoes,
                SUM(gross_amount) as receita,
                AVG(plugin_fee / gross_amount * 100) as taxa_plugin_media
            FROM {$t_transactions}
            WHERE DATE(created_at) BETWEEN %s AND %s
            GROUP BY payment_method
            ORDER BY receita DESC",
            $this->date_from,
            $this->date_to
        ));
    }
    
    /**
     * Obter GMV mensal para progressão de tier
     */
    public function get_monthly_gmv() {
        global $wpdb;
        
        $current_month_start = gmdate('Y-m-01');
        $current_month_end = gmdate('Y-m-t');
        
        $t_transactions = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_transactions') : ('`' . $wpdb->prefix . 'hng_transactions`');
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                SUM(gross_amount) as gmv_mes_atual,
                COUNT(*) as total_transacoes
            FROM {$t_transactions}
            WHERE DATE(created_at) BETWEEN %s AND %s",
            $current_month_start,
            $current_month_end
        ));
        
        $gmv = (float) ($stats->gmv_mes_atual ?? 0);
        
        // Determinar tier atual e próximo
        $tier_info = $this->get_tier_info($gmv);
        
        return [
            'gmv_mes_atual' => $gmv,
            'total_transacoes' => (int) ($stats->total_transacoes ?? 0),
            'tier_atual' => $tier_info['tier_atual'],
            'taxa_atual' => $tier_info['taxa_atual'],
            'proximo_tier' => $tier_info['proximo_tier'],
            'taxa_proximo' => $tier_info['taxa_proximo'],
            'faltam' => $tier_info['faltam'],
            'progresso_percentual' => $tier_info['progresso_percentual'],
        ];
    }
    
    /**
     * Determinar tier baseado no GMV
     */
    private function get_tier_info($gmv) {
        $tiers = [
            1 => ['min' => 0, 'max' => 10000, 'taxa' => 1.99],
            2 => ['min' => 10000, 'max' => 50000, 'taxa' => 1.49],
            3 => ['min' => 50000, 'max' => 200000, 'taxa' => 0.99],
            4 => ['min' => 200000, 'max' => PHP_INT_MAX, 'taxa' => 0.79],
        ];
        
        $tier_atual = 1;
        $taxa_atual = 1.99;
        $proximo_tier = 2;
        $taxa_proximo = 1.49;
        $faltam = 0;
        $progresso = 0;
        
        foreach ($tiers as $num => $config) {
            if ($gmv >= $config['min'] && $gmv < $config['max']) {
                $tier_atual = $num;
                $taxa_atual = $config['taxa'];
                
                if (isset($tiers[$num + 1])) {
                    $proximo_tier = $num + 1;
                    $taxa_proximo = $tiers[$num + 1]['taxa'];
                    $faltam = $tiers[$num + 1]['min'] - $gmv;
                    $progresso = (($gmv - $config['min']) / ($config['max'] - $config['min'])) * 100;
                } else {
                    // Já está no tier máximo
                    $proximo_tier = null;
                    $taxa_proximo = null;
                    $faltam = 0;
                    $progresso = 100;
                }
                
                break;
            }
        }
        
        return [
            'tier_atual' => $tier_atual,
            'taxa_atual' => $taxa_atual,
            'proximo_tier' => $proximo_tier,
            'taxa_proximo' => $taxa_proximo,
            'faltam' => max(0, $faltam),
            'progresso_percentual' => min(100, round($progresso, 2)),
        ];
    }
    
    /**
     * Obter evolução mensal (últimos 12 meses)
     */
    public function get_monthly_evolution($months = 12) {
        global $wpdb;
        $months = absint($months);

        $transactions_table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_transactions') : ('`' . $wpdb->prefix . 'hng_transactions`');

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tables sanitized via hng_db_backtick_table()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reporting query for monthly transaction evolution, time-series aggregation
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Tables sanitized via hng_db_backtick_table()
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE_FORMAT(created_at, '%%Y-%%m') as mes,
                COUNT(*) as total_transacoes,
                SUM(gross_amount) as receita,
                SUM(plugin_fee) as taxas,
                AVG(plugin_fee / gross_amount * 100) as taxa_percentual_media
            FROM {$transactions_table_sql}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d MONTH)
            GROUP BY mes
            ORDER BY mes ASC",
            $months
        ));
    }
    
    /**
     * Obter transações com fallback vs VPS
     */
    public function get_fallback_stats() {
        global $wpdb;
        // datas já sanitizadas via set_date_range
        
        $t_transactions = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_transactions') : ('`' . $wpdb->prefix . 'hng_transactions`');
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tables sanitized via hng_db_backtick_table()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reporting query for fallback vs VPS signature validation analysis
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Tables sanitized via hng_db_backtick_table()
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                is_fallback,
                signature_valid,
                COUNT(*) as total,
                SUM(gross_amount) as receita
            FROM {$t_transactions}
            WHERE DATE(created_at) BETWEEN %s AND %s
            GROUP BY is_fallback, signature_valid",
            $this->date_from,
            $this->date_to
        ));
        
        $stats = [
            'vps_validadas' => 0,
            'vps_invalidas' => 0,
            'fallback' => 0,
            'total' => 0,
        ];
        
        foreach ($results as $row) {
            $total = (int) $row->total;
            $stats['total'] += $total;
            
            if ($row->is_fallback) {
                $stats['fallback'] += $total;
            } else {
                if ($row->signature_valid) {
                    $stats['vps_validadas'] += $total;
                } else {
                    $stats['vps_invalidas'] += $total;
                }
            }
        }
        
        return $stats;
    }
}
