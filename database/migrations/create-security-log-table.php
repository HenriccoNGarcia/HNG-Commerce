<?php
/**
 * Database Migration: Create hng_security_log table
 *
 * Logs admin/security-sensitive events for observability.
 *
 * @package HNG_Commerce
 * @since 1.1.2
 */

if (!defined('ABSPATH')) {
    exit;
}

function hng_migration_create_security_log_table() {
    global $wpdb;

    $table = $wpdb->prefix . 'hng_security_log';
    $table_sql = '`' . $table . '`';

    // Check if table exists
    $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    if ($exists === $table) {
        return 'exists';
    }

    $charset_collate = $wpdb->get_charset_collate();

    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name backticked above
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Intended migration
    $sql = "CREATE TABLE {$table_sql} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        event_type VARCHAR(100) NOT NULL,
        context TEXT NULL,
        user_id BIGINT(20) UNSIGNED DEFAULT 0,
        user_email VARCHAR(191) DEFAULT '',
        ip_address VARCHAR(64) DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX (event_type),
        INDEX (user_id)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    return 'created';
}

function hng_admin_run_security_log_migration() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    check_admin_referer('hng_run_migration');
    $result = hng_migration_create_security_log_table();
    echo '<div class="wrap"><h1>Migration: Security Log</h1><p>Result: ' . esc_html($result) . '</p></div>';
}
add_action('admin_post_hng_migrate_security_log', 'hng_admin_run_security_log_migration');

function hng_maybe_run_security_log_migration() {
    $migration_version = get_option('hng_security_log_migration_version', '0');
    $current_version = '1.1.2';
    if (version_compare($migration_version, $current_version, '<')) {
        $result = hng_migration_create_security_log_table();
        update_option('hng_security_log_migration_version', $current_version);
        if (function_exists('hng_log')) {
            hng_log('Security log migration: ' . $result);
        }
    }
}
add_action('admin_init', 'hng_maybe_run_security_log_migration', 5);
