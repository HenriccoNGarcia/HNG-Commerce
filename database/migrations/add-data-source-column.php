<?php
/**
 * Database Migration: Add data_source column
 * 
 * Adds data_source tracking to customers and subscriptions tables
 * Run once on plugin update or via admin tools
 * 
 * @package HNG_Commerce
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

function hng_migration_add_data_source_column() {
    global $wpdb;
    
    $tables = [
        'hng_customers' => 'Customers',
        'hng_subscriptions' => 'Subscriptions'
    ];
    
    $results = [];
    
    // Sanitiza nomes de tabela (somente letras, números e underscore)
    $sanitize_identifier = function ($name) {
        $name = (string) $name;
        if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            return false;
        }
        return $name;
    };

    foreach ($tables as $table => $label) {
        $safe_table = $sanitize_identifier($table);
        if ($safe_table === false) {
            $results[$table] = "- Nome de tabela inválido";
            continue;
        }
        $full_table = $wpdb->prefix . $safe_table;
        $table_sql = '`' . $full_table . '`';
        
        // Check if column already exists
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via identifier validation and backticks
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Database schema migration, column existence check
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names sanitized via identifier validation and backticks
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM {$table_sql} LIKE %s",
            'data_source'
        ));
        
        if (empty($column_exists)) {
            // Add data_source column
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via identifier validation and backticks
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Database schema migration
            // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names sanitized via identifier validation and backticks
            $result = $wpdb->query("ALTER TABLE {$table_sql} \
                ADD COLUMN `data_source` VARCHAR(50) DEFAULT 'local' COMMENT 'Origin of data: local, asaas, mercadopago, pagarme' AFTER `id`");
            
            if ($result !== false) {
                $results[$table] = "Column added to {$label}";
                
                // Update existing records to 'local'
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via identifier validation and backticks
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Database schema migration, data population
                // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names sanitized via identifier validation and backticks
                $wpdb->query("UPDATE {$table_sql} SET data_source = 'local' WHERE data_source IS NULL");
                
            } else {
                $results[$table] = "— Failed to add column to {$label}: " . $wpdb->last_error;
            }
        } else {
            $results[$table] = "-> Column already exists in {$label}";
        }
    }
    
    // Add index for better query performance
    $customers_table_sql = '`' . $wpdb->prefix . 'hng_customers' . '`';
    $subscriptions_table_sql = '`' . $wpdb->prefix . 'hng_subscriptions' . '`';
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names sanitized via prefix concatenation and backticks
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Database schema migration, index creation
    // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names sanitized via prefix concatenation and backticks
    $wpdb->query("CREATE INDEX idx_data_source ON {$customers_table_sql} (data_source)");
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names sanitized via prefix concatenation and backticks
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Database schema migration, index creation
    // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names sanitized via prefix concatenation and backticks
    $wpdb->query("CREATE INDEX idx_data_source ON {$subscriptions_table_sql} (data_source)");
    
    return $results;
}

/**
 * Run migration via admin interface
 */
function hng_admin_run_data_source_migration() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    check_admin_referer('hng_run_migration');
    
    $results = hng_migration_add_data_source_column();
    
    echo '<div class="wrap">';
    echo '<h1>Database Migration: Data Source Tracking</h1>';
    echo '<div class="notice notice-info"><p><strong>Migration Results:</strong></p><ul>';
    
    foreach ($results as $table => $message) {
        echo '<li>' . esc_html($message) . '</li>';
    }
    
    echo '</ul></div>';
    echo '<p><a href="' . esc_url(admin_url('admin.php?page=hng-tools')) . '" class="button">Back to Tools</a></p>';
    echo '</div>';
}

// Hook for manual execution via admin
add_action('admin_post_hng_migrate_data_source', 'hng_admin_run_data_source_migration');

/**
 * Auto-run migration on plugin activation/update (version check)
 */
function hng_maybe_run_data_source_migration() {
    $migration_version = get_option('hng_data_source_migration_version', '0');
    $current_version = '1.1.0';
    
    if (version_compare($migration_version, $current_version, '<')) {
        $results = hng_migration_add_data_source_column();
        update_option('hng_data_source_migration_version', $current_version);
        
        // Log results
        if (function_exists('hng_log')) {
            hng_log('Data source migration completed: ' . wp_json_encode($results));
        }
    }
}

// Run on admin init (safe context)
add_action('admin_init', 'hng_maybe_run_data_source_migration', 5);



