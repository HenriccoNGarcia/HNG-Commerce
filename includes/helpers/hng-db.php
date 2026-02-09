<?php
/**
 * DB helper utilities
 *
 * @package HNG_Commerce
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sanitize a database identifier (table or column) by allowing only a-z, 0-9 and underscore.
 * Does not add quotes â€” caller should use backticks if embedding into SQL.
 */
function hng_db_sanitize_identifier( $name ) {
    if ( ! is_string( $name ) ) {
        return '';
    }
    return preg_replace('/[^a-z0-9_]/i', '', $name);
}

/**
 * Return a full table name with WP prefix, sanitized.
 * Example: hng_orders -> {$wpdb->prefix}hng_orders
 */
function hng_db_full_table_name( $short_name ) {
    global $wpdb;
    $clean = hng_db_sanitize_identifier( $short_name );
    if ( empty( $clean ) ) {
        return '';
    }
    return $wpdb->prefix . $clean;
}

/**
 * Sanitize column name and wrap with backticks for safe embedding.
 */
function hng_db_backtick_column( $col ) {
    $clean = hng_db_sanitize_identifier( $col );
    if ( empty( $clean ) ) {
        return '';
    }
    return "`" . $clean . "`";
}

/**
 * Return a full table name (with WP prefix) sanitized and wrapped in backticks.
 * Accepts the short table name (without prefix) or a full name; always returns
 * a safe backticked identifier like `wp_hng_orders` or empty string on invalid input.
 */
function hng_db_backtick_table( $short_name ) {
    global $wpdb;
    if ( ! is_string( $short_name ) ) {
        return '';
    }
    $clean = hng_db_sanitize_identifier( $short_name );
    if ( empty( $clean ) ) {
        return '';
    }
    return "`" . $wpdb->prefix . $clean . "`";
}
