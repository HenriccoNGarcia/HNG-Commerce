<?php
/**
 * Migration: Create Analytics Tables
 * 
 * Cria tabelas necessárias para análise de conversão e abandono de carrinho
 * 
 * @package HNG_Commerce
 * @since 1.2.10
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Migration_Analytics_Tables {
    
    /**
     * Run migration
     */
    public static function run() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // 1. Adicionar coluna source_page_id na tabela hng_orders
        self::add_source_page_id_column();
        
        // 2. Adicionar coluna gateway na tabela hng_orders
        self::add_gateway_column();
        
        // 3. Criar tabela hng_pageviews
        self::create_pageviews_table($charset_collate);
        
        // 4. Criar tabela hng_abandoned_carts
        self::create_abandoned_carts_table($charset_collate);
        
        // 5. Criar tabela hng_carts
        self::create_carts_table($charset_collate);
        
        // 6. Criar tabela hng_checkout_sessions
        self::create_checkout_sessions_table($charset_collate);
        
        // 7. Criar tabela hng_cart_items (para abandoned carts)
        self::create_cart_items_table($charset_collate);
        
        return true;
    }
    
    /**
     * Add source_page_id column to hng_orders
     */
    private static function add_source_page_id_column() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'hng_orders';
        
        // Check if column exists
        $column_exists = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = %s 
                 AND TABLE_NAME = %s 
                 AND COLUMN_NAME = 'source_page_id'",
                DB_NAME,
                $table_name
            )
        );
        
        if (empty($column_exists)) {
            // Add column without specifying position (AFTER) to avoid dependency on existing columns
            $wpdb->query(
                "ALTER TABLE {$table_name} 
                 ADD COLUMN source_page_id BIGINT(20) UNSIGNED NULL"
            );
            // Add index separately
            $wpdb->query(
                "ALTER TABLE {$table_name} 
                 ADD INDEX idx_source_page_id (source_page_id)"
            );
        }
    }
    
    /**
     * Add gateway column to hng_orders
     */
    private static function add_gateway_column() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'hng_orders';
        
        // Check if column exists
        $column_exists = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = %s 
                 AND TABLE_NAME = %s 
                 AND COLUMN_NAME = 'gateway'",
                DB_NAME,
                $table_name
            )
        );
        
        if (empty($column_exists)) {
            // Add column without specifying position (AFTER) to avoid dependency on existing columns
            $wpdb->query(
                "ALTER TABLE {$table_name} 
                 ADD COLUMN gateway VARCHAR(50) NULL"
            );
            // Add index separately
            $wpdb->query(
                "ALTER TABLE {$table_name} 
                 ADD INDEX idx_gateway (gateway)"
            );
        }
    }
    
    /**
     * Create hng_pageviews table
     */
    private static function create_pageviews_table($charset_collate) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'hng_pageviews';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            visitor_id VARCHAR(255) NOT NULL,
            page_id BIGINT(20) UNSIGNED NOT NULL,
            page_url TEXT NOT NULL,
            referrer TEXT NULL,
            user_agent TEXT NULL,
            ip_address VARCHAR(45) NULL,
            session_id VARCHAR(255) NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            INDEX idx_visitor_id (visitor_id),
            INDEX idx_page_id (page_id),
            INDEX idx_session_id (session_id),
            INDEX idx_created_at (created_at)
        ) {$charset_collate};";
        
        dbDelta($sql);
    }
    
    /**
     * Create hng_abandoned_carts table
     */
    private static function create_abandoned_carts_table($charset_collate) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'hng_abandoned_carts';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NULL,
            session_id VARCHAR(255) NOT NULL,
            email VARCHAR(255) NULL,
            checkout_method VARCHAR(50) NULL,
            total_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            recovered TINYINT(1) NOT NULL DEFAULT 0,
            order_id BIGINT(20) UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            abandoned_at DATETIME NULL,
            PRIMARY KEY (id),
            INDEX idx_user_id (user_id),
            INDEX idx_session_id (session_id),
            INDEX idx_email (email),
            INDEX idx_recovered (recovered),
            INDEX idx_created_at (created_at),
            INDEX idx_abandoned_at (abandoned_at)
        ) {$charset_collate};";
        
        dbDelta($sql);
    }
    
    /**
     * Create hng_carts table
     */
    private static function create_carts_table($charset_collate) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'hng_carts';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NULL,
            session_id VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            INDEX idx_user_id (user_id),
            INDEX idx_session_id (session_id),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at)
        ) {$charset_collate};";
        
        dbDelta($sql);
    }
    
    /**
     * Create hng_checkout_sessions table
     */
    private static function create_checkout_sessions_table($charset_collate) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'hng_checkout_sessions';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id VARCHAR(255) NOT NULL,
            user_id BIGINT(20) UNSIGNED NULL,
            checkout_method VARCHAR(50) NULL,
            step VARCHAR(50) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'started',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            completed_at DATETIME NULL,
            PRIMARY KEY (id),
            INDEX idx_session_id (session_id),
            INDEX idx_user_id (user_id),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at)
        ) {$charset_collate};";
        
        dbDelta($sql);
    }
    
    /**
     * Create hng_cart_items table
     */
    private static function create_cart_items_table($charset_collate) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'hng_cart_items';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            cart_id BIGINT(20) UNSIGNED NOT NULL,
            product_id BIGINT(20) UNSIGNED NOT NULL,
            quantity INT(11) NOT NULL DEFAULT 1,
            price DECIMAL(10,2) NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            INDEX idx_cart_id (cart_id),
            INDEX idx_product_id (product_id)
        ) {$charset_collate};";
        
        dbDelta($sql);
    }
}

// Run migration if called directly
if (isset($_GET['run_analytics_migration']) && current_user_can('manage_options')) {
    HNG_Migration_Analytics_Tables::run();
    wp_die('Migration completed successfully!');
}
