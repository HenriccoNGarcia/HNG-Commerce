<?php
/**
 * HNG Commerce - Migration: Add wp_user_id to hng_customers
 *
 * Adiciona coluna wp_user_id para permitir relacionamento com usuários WordPress
 *
 * @package HNG_Commerce
 * @since 1.2.15
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Migration_Customers_WP_User {
    
    /**
     * Run migration
     */
    public static function run() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'hng_customers';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            // Table doesn't exist yet, will be created elsewhere
            return;
        }
        
        // Check if column already exists
        $column_exists = $wpdb->get_results(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_NAME = '{$table_name}' 
             AND COLUMN_NAME = 'wp_user_id'
             AND TABLE_SCHEMA = DATABASE()"
        );
        
        if (!empty($column_exists)) {
            // Column already exists
            return;
        }
        
        // Add wp_user_id column
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- ALTER TABLE doesn't support prepared statements
        $result = $wpdb->query(
            "ALTER TABLE {$table_name} 
             ADD COLUMN wp_user_id BIGINT UNSIGNED NULL DEFAULT NULL 
             AFTER email"
        );
        
        if ($result === false) {
            error_log('HNG Commerce: Failed to add wp_user_id column to hng_customers');
        } else {
            error_log('HNG Commerce: Successfully added wp_user_id column to hng_customers');
        }
    }
}
