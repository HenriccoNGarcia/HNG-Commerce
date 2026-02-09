<?php
/**
 * Database Migration: Create hng_coupon_usage table
 *
 * Tracks coupon usage per order/customer.
 *
 * @package HNG_Commerce
 * @since 1.1.2
 */

if (!defined('ABSPATH')) {
    exit;
}

function hng_migration_create_coupon_usage_table() {
    global $wpdb;

    $table = $wpdb->prefix . 'hng_coupon_usage';
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
        coupon_code VARCHAR(100) NOT NULL,
        order_id BIGINT(20) UNSIGNED DEFAULT 0,
        customer_id BIGINT(20) UNSIGNED DEFAULT 0,
        discount_amount DECIMAL(10,2) DEFAULT 0.00,
        used_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX (coupon_code),
        INDEX (order_id),
        INDEX (customer_id)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    return 'created';
}

function hng_admin_run_coupon_usage_migration() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    check_admin_referer('hng_run_migration');
    $result = hng_migration_create_coupon_usage_table();
    echo '<div class="wrap"><h1>Migration: Coupon Usage</h1><p>Result: ' . esc_html($result) . '</p></div>';
}
add_action('admin_post_hng_migrate_coupon_usage', 'hng_admin_run_coupon_usage_migration');

function hng_maybe_run_coupon_usage_migration() {
    $migration_version = get_option('hng_coupon_usage_migration_version', '0');
    $current_version = '1.1.2';
    if (version_compare($migration_version, $current_version, '<')) {
        $result = hng_migration_create_coupon_usage_table();
        update_option('hng_coupon_usage_migration_version', $current_version);
        if (function_exists('hng_log')) {
            hng_log('Coupon usage migration: ' . $result);
        }
    }
}
add_action('admin_init', 'hng_maybe_run_coupon_usage_migration', 5);
