<?php
/**
 * Uninstall handler for HNG Commerce
 *
 * This file is executed when the plugin is deleted from the Plugins screen.
 * It will remove plugin options and (optionally) custom tables when the
 * constant `HNG_COMMERCE_REMOVE_DATA` is defined and true.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Only remove data when explicitly requested by defining the constant
if (defined('HNG_COMMERCE_REMOVE_DATA') && HNG_COMMERCE_REMOVE_DATA === true) {
    global $wpdb;

    // Options to remove
    $options = [
        'hng_merchant_id',
        'hng_api_key',
        'hng_webhook_secret',
        'hng_merchant_status',
        'hng_current_tier',
        'hng_enable_compat',
        'hng_transaction_log',
        'hng_assets_version',
        'hng_default_gateway',
        // Add other option keys used by the plugin here as needed
    ];

    foreach ($options as $opt) {
        delete_option($opt);
        delete_site_option($opt);
    }

    // Drop plugin custom tables if they exist (only do this if you want full removal)
    $tables_sql = [];
    $tables_sql[] = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_orders') : ('`' . $wpdb->prefix . 'hng_orders`');
    $tables_sql[] = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_subscriptions') : ('`' . $wpdb->prefix . 'hng_subscriptions`');
    $tables_sql[] = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_custom_fields') : ('`' . $wpdb->prefix . 'hng_custom_fields`');

    foreach ($tables_sql as $table_sql) {
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via hng_db_backtick_table() helper
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Plugin cleanup, table removal
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names sanitized via hng_db_backtick_table()
        $wpdb->query("DROP TABLE IF EXISTS {$table_sql}");
    }

    // Remove files created by the plugin in wp-content (logs)
    $log_file = wp_normalize_path(WP_CONTENT_DIR . '/hng-transactions.log');

    if ( ! function_exists( 'WP_Filesystem' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    global $wp_filesystem;
    if ( empty( $wp_filesystem ) ) {
        WP_Filesystem();
    }

    if ( ! empty( $wp_filesystem ) && $wp_filesystem->exists( $log_file ) ) {
        $wp_filesystem->delete( $log_file );
    }
}


