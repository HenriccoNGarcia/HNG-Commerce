<?php
/**
 * HNG Commerce: Conversion Tracker
 * 
 * Tracks user conversion events for analytics
 * 
 * @package HNG_Commerce
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Conversion_Tracker {
    
    private static $instance = null;
    private $table_name;
    private $session_id;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'hng_conversion_events';
        $this->init_session();
        $this->setup_hooks();
    }
    
    /**
     * Initialize or resume session
     */
    private function init_session() {
        if (!session_id()) {
            @session_start();
        }
        
        if (!isset($_SESSION['hng_session_id'])) {
            $_SESSION['hng_session_id'] = $this->generate_session_id();
        }
        
        // Normalize session id to avoid tainted globals
        $this->session_id = sanitize_text_field(wp_unslash($_SESSION['hng_session_id']));
    }
    
    /**
     * Generate unique session ID
     */
    private function generate_session_id() {
        return 'hng_' . wp_generate_password(32, false);
    }
    
    /**
     * Setup WordPress hooks
     */
    private function setup_hooks() {
        // Track page views
        add_action('wp', [$this, 'track_page_view']);
        
        // Track product views
        add_action('hng_product_view', [$this, 'track_product_view'], 10, 1);
        
        // Track add to cart
        add_action('hng_add_to_cart', [$this, 'track_add_to_cart'], 10, 2);
        
        // Track checkout start
        add_action('hng_checkout_page_load', [$this, 'track_checkout_start']);
        
        // Track purchase
        add_action('hng_order_created', [$this, 'track_purchase'], 10, 1);
    }
    
    /**
     * Create database table
     */
    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'hng_conversion_events';
        $charset_collate = $wpdb->get_charset_collate();
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via $wpdb->prefix, dbDelta requires literal SQL
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Database schema installation
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names sanitized via $wpdb->prefix
        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `event_type` VARCHAR(50) NOT NULL,
          `session_id` VARCHAR(64) NOT NULL,
          `user_id` BIGINT UNSIGNED DEFAULT NULL,
          `page_id` BIGINT UNSIGNED DEFAULT NULL,
          `page_url` VARCHAR(512) DEFAULT NULL,
          `product_id` BIGINT UNSIGNED DEFAULT NULL,
          `order_id` BIGINT UNSIGNED DEFAULT NULL,
          `template_id` BIGINT UNSIGNED DEFAULT NULL,
          `template_name` VARCHAR(255) DEFAULT NULL,
          `referrer` VARCHAR(512) DEFAULT NULL,
          `metadata` TEXT DEFAULT NULL,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `session_id` (`session_id`),
          KEY `user_id` (`user_id`),
          KEY `event_type` (`event_type`),
          KEY `created_at` (`created_at`),
          KEY `product_id` (`product_id`),
          KEY `template_id` (`template_id`),
          KEY `event_session` (`event_type`, `session_id`)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Track page view
     */
    public function track_page_view() {
        if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
            return;
        }
        
        $event_data = [
            'event_type' => 'page_view',
            'page_id' => get_queried_object_id(),
            'page_url' => home_url(add_query_arg(null, null)),
            'referrer' => wp_get_referer()
        ];
        
        // Detect Elementor template
        if (function_exists('elementor_theme_do_location')) {
            $template_id = $this->get_elementor_template_id();
            if ($template_id) {
                $event_data['template_id'] = $template_id;
                $event_data['template_name'] = get_the_title($template_id);
            }
        }
        
        $this->insert_event($event_data);
    }
    
    /**
     * Track product view
     * 
     * @param int $product_id
     */
    public function track_product_view($product_id) {
        $event_data = [
            'event_type' => 'product_view',
            'product_id' => $product_id,
            'page_id' => get_queried_object_id(),
            'page_url' => get_permalink($product_id),
            'referrer' => wp_get_referer()
        ];
        
        $this->insert_event($event_data);
    }
    
    /**
     * Track add to cart
     * 
     * @param int $product_id
     * @param int $quantity
     */
    public function track_add_to_cart($product_id, $quantity = 1) {
        $event_data = [
            'event_type' => 'add_to_cart',
            'product_id' => $product_id,
            'metadata' => wp_json_encode(['quantity' => $quantity])
        ];
        
        $this->insert_event($event_data);
    }
    
    /**
     * Track checkout start
     */
    public function track_checkout_start() {
        $event_data = [
            'event_type' => 'checkout_start',
            'page_id' => get_queried_object_id(),
            'page_url' => home_url(add_query_arg(null, null))
        ];
        
        // Track Elementor checkout template
        $template_id = $this->get_elementor_template_id();
        if ($template_id) {
            $event_data['template_id'] = $template_id;
            $event_data['template_name'] = get_the_title($template_id);
        }
        
        $this->insert_event($event_data);
    }
    
    /**
     * Track purchase
     * 
     * @param int $order_id
     */
    public function track_purchase($order_id) {
        if (!$order_id) {
            return;
        }
        
        $order = new HNG_Order($order_id);
        
        $event_data = [
            'event_type' => 'purchase',
            'order_id' => $order_id,
            'metadata' => wp_json_encode([
                'total' => $order->get_total(),
                'products' => count($order->get_items())
            ])
        ];
        
        $this->insert_event($event_data);
    }
    
    /**
     * Insert event into database
     * 
     * @param array $data
     * @return int|false
     */
    private function insert_event($data) {
        global $wpdb;
        
        $defaults = [
            'session_id' => $this->session_id,
            'user_id' => get_current_user_id() ?: null,
            'created_at' => current_time('mysql')
        ];
        
        $event = array_merge($defaults, $data);
        
        $result = $wpdb->insert($this->table_name, $event, [
            '%s', // event_type
            '%s', // session_id
            '%d', // user_id
            '%d', // page_id
            '%s', // page_url
            '%d', // product_id
            '%d', // order_id
            '%d', // template_id
            '%s', // template_name
            '%s', // referrer
            '%s', // metadata
            '%s'  // created_at
        ]);
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Get currently active Elementor template ID
     * 
     * @return int|false
     */
    private function get_elementor_template_id() {
        if (!did_action('elementor/loaded')) {
            return false;
        }
        
        // Try to get template from Elementor Theme Builder
        $document = \Elementor\Plugin::$instance->documents->get_current();
        
        if ($document && method_exists($document, 'get_main_id')) {
            return $document->get_main_id();
        }
        
        return false;
    }
    
    /**
     * Get conversion funnel data
     * 
     * @param string $start_date
     * @param string $end_date
     * @return array
     */
    public function get_funnel_data($start_date = null, $end_date = null) {
        global $wpdb;
        
        if (!$start_date) {
            $start_date = gmdate('Y-m-d', strtotime('-30 days'));
        }
        if (!$end_date) {
            $end_date = gmdate('Y-m-d');
        }
        
        $query = $wpdb->prepare("
            SELECT 
                event_type,
                COUNT(DISTINCT session_id) as count
            FROM {$this->table_name}
            WHERE created_at BETWEEN %s AND %s
            GROUP BY event_type
        ", $start_date . ' 00:00:00', $end_date . ' 23:59:59');
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $results = $wpdb->get_results($query);
        
        $funnel = [
            'page_view' => 0,
            'product_view' => 0,
            'add_to_cart' => 0,
            'checkout_start' => 0,
            'purchase' => 0
        ];
        
        foreach ($results as $row) {
            $funnel[$row->event_type] = (int) $row->count;
        }
        
        return $funnel;
    }
    
    /**
     * Get top converting pages
     * 
     * @param int $limit
     * @param string $start_date
     * @param string $end_date
     * @return array
     */
    public function get_top_pages($limit = 10, $start_date = null, $end_date = null) {
        global $wpdb;
        
        if (!$start_date) {
            $start_date = gmdate('Y-m-d', strtotime('-30 days'));
        }
        if (!$end_date) {
            $end_date = gmdate('Y-m-d');
        }
        
        $query = $wpdb->prepare("
            SELECT 
                e.page_id,
                e.page_url,
                COUNT(DISTINCT e.session_id) as views,
                COUNT(DISTINCT p.session_id) as conversions
            FROM {$this->table_name} e
            LEFT JOIN {$this->table_name} p ON e.session_id = p.session_id AND p.event_type = 'purchase'
            WHERE e.event_type = 'page_view'
            AND e.created_at BETWEEN %s AND %s
            AND e.page_id IS NOT NULL
            GROUP BY e.page_id
            HAVING views > 10
            ORDER BY conversions DESC
            LIMIT %d
        ", $start_date . ' 00:00:00', $end_date . ' 23:59:59', $limit);
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_results($query);
    }
    
    /**
     * Get top converting products
     * 
     * @param int $limit
     * @param string $start_date
     * @param string $end_date
     * @return array
     */
    public function get_top_products($limit = 10, $start_date = null, $end_date = null) {
        global $wpdb;
        
        if (!$start_date) {
            $start_date = gmdate('Y-m-d', strtotime('-30 days'));
        }
        if (!$end_date) {
            $end_date = gmdate('Y-m-d');
        }
        
        $query = $wpdb->prepare("
            SELECT 
                product_id,
                COUNT(DISTINCT CASE WHEN event_type = 'product_view' THEN session_id END) as views,
                COUNT(DISTINCT CASE WHEN event_type = 'add_to_cart' THEN session_id END) as adds,
                COUNT(DISTINCT CASE WHEN event_type = 'purchase' THEN session_id END) as purchases
            FROM {$this->table_name}
            WHERE product_id IS NOT NULL
            AND created_at BETWEEN %s AND %s
            GROUP BY product_id
            HAVING views > 10
            ORDER BY (purchases * 1.0 / views) DESC
            LIMIT %d
        ", $start_date . ' 00:00:00', $end_date . ' 23:59:59', $limit);
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_results($query);
    }
    
    /**
     * Get Elementor template performance
     * 
     * @param int $limit
     * @param string $start_date
     * @param string $end_date
     * @return array
     */
    public function get_template_performance($limit = 10, $start_date = null, $end_date = null) {
        global $wpdb;
        
        if (!$start_date) {
            $start_date = gmdate('Y-m-d', strtotime('-30 days'));
        }
        if (!$end_date) {
            $end_date = gmdate('Y-m-d');
        }
        
        $query = $wpdb->prepare("
            SELECT 
                e.template_id,
                e.template_name,
                COUNT(DISTINCT e.session_id) as views,
                COUNT(DISTINCT p.session_id) as conversions
            FROM {$this->table_name} e
            LEFT JOIN {$this->table_name} p ON e.session_id = p.session_id AND p.event_type = 'purchase'
            WHERE e.template_id IS NOT NULL
            AND e.created_at BETWEEN %s AND %s
            GROUP BY e.template_id
            HAVING views > 10
            ORDER BY (conversions * 1.0 / views) DESC
            LIMIT %d
        ", $start_date . ' 00:00:00', $end_date . ' 23:59:59', $limit);
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_results($query);
    }
}
