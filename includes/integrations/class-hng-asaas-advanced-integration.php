<?php
/**
 * HNG Commerce - Asaas Advanced Integration
 *
 * Handles activation, deactivation, and core logic for the advanced integration.
 *
 * @package HNG_Commerce
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Asaas_Advanced_Integration {

    /**
     * Instance
     *
     * @var HNG_Asaas_Advanced_Integration
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return HNG_Asaas_Advanced_Integration
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Hooks de ativaçáo/desativaçáo disparados pelo gateway settings
        add_action('hng_asaas_advanced_integration_activated', [$this, 'on_activation']);
        add_action('hng_asaas_advanced_integration_deactivated', [$this, 'on_deactivation']);
        
        // Inicializar se estiver ativo
        if (get_option('hng_asaas_advanced_integration') === 'yes') {
            $this->init();
        }

        // Registrar webhooks sempre (não depender do flag de integração avançada)
        if (file_exists(HNG_COMMERCE_PATH . 'includes/integrations/class-hng-asaas-webhooks-handler.php')) {
            require_once HNG_COMMERCE_PATH . 'includes/integrations/class-hng-asaas-webhooks-handler.php';
            new HNG_Asaas_Webhooks_Handler();
        }
    }

    /**
     * Initialize integration
     */
    public function init() {
        // Agendar cron se náo existir
        if (!wp_next_scheduled('hng_asaas_sync_event')) {
            wp_schedule_event(time(), 'hourly', 'hng_asaas_sync_event');
        }
        
        // Registrar hooks de cron
        add_action('hng_asaas_sync_event', [$this, 'run_sync']);
    }

    /**
     * Activation logic
     */
    public function on_activation() {
        $this->create_tables();
        $this->init();
        
        // Log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('HNG Asaas: Advanced integration activated.');
        }
    }

    /**
     * Deactivation logic
     */
    public function on_deactivation() {
        // Remover cron
        $timestamp = wp_next_scheduled('hng_asaas_sync_event');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'hng_asaas_sync_event');
        }
        
        // Log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('HNG Asaas: Advanced integration deactivated.');
        }
    }

    /**
     * Create necessary database tables
     */
    private function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Tabela de Logs de Webhook
        $table_name = $wpdb->prefix . 'hng_asaas_webhook_log';
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via $wpdb->prefix, dbDelta requires literal SQL
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Database schema installation
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names sanitized via $wpdb->prefix
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            payload longtext NOT NULL,
            processed tinyint(1) DEFAULT 0,
            processed_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            error_message text DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY event_type (event_type),
            KEY processed (processed)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Adicionar colunas em tabelas existentes (se náo existirem)
        $table_subscriptions = $wpdb->prefix . 'hng_subscriptions';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via $wpdb->prefix
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Database schema installation, table existence check
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names sanitized via $wpdb->prefix
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_subscriptions'") == $table_subscriptions) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via $wpdb->prefix
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Database schema installation, column existence check
            // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names sanitized via $wpdb->prefix
            if (!$wpdb->get_var("SHOW COLUMNS FROM `$table_subscriptions` LIKE 'asaas_subscription_id'")) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via $wpdb->prefix
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Database schema installation
                // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names sanitized via $wpdb->prefix
                $wpdb->query("ALTER TABLE `$table_subscriptions` ADD COLUMN `asaas_subscription_id` varchar(50) DEFAULT NULL AFTER `product_id`");
            }
        }
        
        $table_customers = $wpdb->prefix . 'hng_customers';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via $wpdb->prefix
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Database schema installation, table existence check
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names sanitized via $wpdb->prefix
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_customers'") == $table_customers) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via $wpdb->prefix
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Database schema installation, column existence check
            // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names sanitized via $wpdb->prefix
            if (!$wpdb->get_var("SHOW COLUMNS FROM `$table_customers` LIKE 'asaas_customer_id'")) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via $wpdb->prefix
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Database schema installation
                // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names sanitized via $wpdb->prefix
                $wpdb->query("ALTER TABLE `$table_customers` ADD COLUMN `asaas_customer_id` varchar(50) DEFAULT NULL AFTER `id`");
            }
        }
        
        update_option('hng_asaas_db_version', '1.0.0');
        // AJAX Handlers for Manual Sync
        add_action('wp_ajax_hng_asaas_sync_subscriptions', [$this, 'ajax_sync_subscriptions']);
        add_action('wp_ajax_hng_asaas_sync_customers', [$this, 'ajax_sync_customers']);

        // Initialize Webhooks Handler
        if (file_exists(HNG_COMMERCE_PATH . 'includes/integrations/class-hng-asaas-webhooks-handler.php')) {
            require_once HNG_COMMERCE_PATH . 'includes/integrations/class-hng-asaas-webhooks-handler.php';
            new HNG_Asaas_Webhooks_Handler();
        }
    }

    /**
     * AJAX: Sync Subscriptions
     */
    public function ajax_sync_subscriptions() {
        check_ajax_referer('hng_asaas_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['error' => 'Permissáo negada']);
        }

        if (!class_exists('HNG_Asaas_Sync')) {
            if (file_exists(HNG_COMMERCE_PATH . 'includes/integrations/class-hng-asaas-sync.php')) {
                require_once HNG_COMMERCE_PATH . 'includes/integrations/class-hng-asaas-sync.php';
            }
        }

        if (class_exists('HNG_Asaas_Sync')) {
            $sync = new HNG_Asaas_Sync();
            $result = $sync->import_subscriptions();
            
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }
        } else {
            wp_send_json_error(['error' => 'Classe de sincronizaçáo náo encontrada']);
        }
    }

    /**
     * AJAX: Sync Customers
     */
    public function ajax_sync_customers() {
        check_ajax_referer('hng_asaas_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['error' => 'Permissáo negada']);
        }

        if (!class_exists('HNG_Asaas_Sync')) {
            if (file_exists(HNG_COMMERCE_PATH . 'includes/integrations/class-hng-asaas-sync.php')) {
                require_once HNG_COMMERCE_PATH . 'includes/integrations/class-hng-asaas-sync.php';
            }
        }

        if (class_exists('HNG_Asaas_Sync')) {
            $sync = new HNG_Asaas_Sync();
            $result = $sync->import_customers();
            
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }
        } else {
            wp_send_json_error(['error' => 'Classe de sincronizaçáo náo encontrada']);
        }
    }

    /**
     * Run synchronization (Cron)
     */
    public function run_sync() {
        if (!class_exists('HNG_Asaas_Sync')) {
            // Autoloader should handle this now, or manual require if needed
            if (file_exists(HNG_COMMERCE_PATH . 'includes/integrations/class-hng-asaas-sync.php')) {
                require_once HNG_COMMERCE_PATH . 'includes/integrations/class-hng-asaas-sync.php';
            }
        }

        if (class_exists('HNG_Asaas_Sync')) {
            $sync = new HNG_Asaas_Sync();
            
            // Sync Customers
            $sync->import_customers();
            
            // Sync Subscriptions
            $sync->import_subscriptions();
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('HNG Asaas: Scheduled sync completed.');
            }
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('HNG Asaas: Sync class not found.');
            }
        }
    }
}

// Initialize
HNG_Asaas_Advanced_Integration::instance();
