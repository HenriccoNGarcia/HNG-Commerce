<?php
/**
 * HNG Commerce - Database Installer
 * 
 * Executa automaticamente as migrations necessárias
 * 
 * @package HNG_Commerce
 * @since 1.2.10
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Database_Installer {
    
    /**
     * Install/Update database tables
     */
    public static function install() {
        // Check if we need to run migrations
        $db_version = get_option('hng_commerce_db_version', '0');
        $current_version = '1.2.15';
        
        if (version_compare($db_version, $current_version, '<')) {
            self::run_migrations();
            update_option('hng_commerce_db_version', $current_version);
        }
    }
    
    /**
     * Run all migrations
     */
    private static function run_migrations() {
        // Load migration files
        require_once HNG_COMMERCE_PATH . 'includes/database/migration-analytics-tables.php';
        require_once HNG_COMMERCE_PATH . 'includes/database/migration-orders-schema.php';
        require_once HNG_COMMERCE_PATH . 'includes/database/migration-customers-wp-user.php';
        
        // Run analytics tables migration
        HNG_Migration_Analytics_Tables::run();
        
        // Run orders schema migration
        HNG_Migration_Orders_Schema::run();
        
        // Run customers WP user migration
        HNG_Migration_Customers_WP_User::run();
        
        // Log success
        error_log('HNG Commerce: Database migrations completed successfully');
    }
    
    /**
     * Check database status
     */
    public static function check_database() {
        global $wpdb;
        
        $prefix = $wpdb->prefix;
        $missing_tables = [];
        $missing_columns = [];
        
        // Check required tables
        $required_tables = [
            'hng_orders',
            'hng_order_items',
            'hng_products',
            'hng_pageviews',
            'hng_abandoned_carts',
            'hng_carts',
            'hng_cart_items',
            'hng_checkout_sessions'
        ];
        
        foreach ($required_tables as $table) {
            $table_name = $prefix . $table;
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from internal list
            $exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
            if (!$exists) {
                $missing_tables[] = $table;
            }
        }
        
        // Check required columns in hng_orders
        if (empty($missing_tables) || !in_array('hng_orders', $missing_tables)) {
            $orders_table = $prefix . 'hng_orders';
            $db_name = DB_NAME; // Database name constant for query
            
            // Check source_page_id
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Database and table names from safe internal variables
            $has_source_page = $wpdb->get_var(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = '{$db_name}' 
                 AND TABLE_NAME = '{$orders_table}' 
                 AND COLUMN_NAME = 'source_page_id'"
            );
            if (!$has_source_page) {
                $missing_columns[] = 'hng_orders.source_page_id';
            }
            
            // Check gateway
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Database and table names from safe internal variables
            $has_gateway = $wpdb->get_var(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = '{$db_name}' 
                 AND TABLE_NAME = '{$orders_table}' 
                 AND COLUMN_NAME = 'gateway'"
            );
            if (!$has_gateway) {
                $missing_columns[] = 'hng_orders.gateway';
            }
        }
        
        return [
            'missing_tables' => $missing_tables,
            'missing_columns' => $missing_columns,
            'status' => (empty($missing_tables) && empty($missing_columns)) ? 'ok' : 'incomplete'
        ];
    }
}

// Run on admin_init to ensure WordPress is fully loaded
add_action('admin_init', function() {
    HNG_Database_Installer::install();
}, 5);

// Also run on plugin activation if HNG_COMMERCE_FILE is defined
if (defined('HNG_COMMERCE_FILE')) {
    register_activation_hook(HNG_COMMERCE_FILE, ['HNG_Database_Installer', 'install']);
}
