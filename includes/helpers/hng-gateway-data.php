<?php
/**
 * Gateway Data Management Helpers
 * 
 * Manages visibility and cleanup of gateway-synced data
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Hide gateway-synced data when advanced integration is disabled
 * 
 * @param string $gateway Gateway identifier (asaas, mercadopago, pagarme)
 * @param bool $hard_delete Whether to permanently delete (default: false, just mark hidden)
 */
function hng_hide_gateway_data($gateway, $hard_delete = false) {
    global $wpdb;
    
    $gateway = sanitize_key($gateway);
    
    if ($hard_delete) {
        // Permanently delete gateway-synced records
        $wpdb->delete(
            $wpdb->prefix . 'hng_customers',
            ['data_source' => $gateway],
            ['%s']
        );
        
        $wpdb->delete(
            $wpdb->prefix . 'hng_subscriptions',
            ['data_source' => $gateway],
            ['%s']
        );
        
        // Delete related subscription notes
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}hng_subscription_notes 
             WHERE subscription_id IN (
                 SELECT id FROM {$wpdb->prefix}hng_subscriptions WHERE data_source = %s
             )",
            $gateway
        ));
        
        do_action('hng_gateway_data_deleted', $gateway);
    } else {
        // Soft delete: mark as hidden
        $wpdb->update(
            $wpdb->prefix . 'hng_customers',
            ['data_source' => $gateway . '_hidden'],
            ['data_source' => $gateway],
            ['%s'],
            ['%s']
        );
        
        $wpdb->update(
            $wpdb->prefix . 'hng_subscriptions',
            ['data_source' => $gateway . '_hidden'],
            ['data_source' => $gateway],
            ['%s'],
            ['%s']
        );
        
        do_action('hng_gateway_data_hidden', $gateway);
    }
    
    // Log action
    if (function_exists('hng_log')) {
        hng_log(sprintf(
            'Gateway data %s: %s',
            $hard_delete ? 'deleted' : 'hidden',
            $gateway
        ));
    }
}

/**
 * Restore hidden gateway data when advanced integration is re-enabled
 * 
 * @param string $gateway Gateway identifier
 */
function hng_restore_gateway_data($gateway) {
    global $wpdb;
    
    $gateway = sanitize_key($gateway);
    $hidden_source = $gateway . '_hidden';
    
    $wpdb->update(
        $wpdb->prefix . 'hng_customers',
        ['data_source' => $gateway],
        ['data_source' => $hidden_source],
        ['%s'],
        ['%s']
    );
    
    $wpdb->update(
        $wpdb->prefix . 'hng_subscriptions',
        ['data_source' => $gateway],
        ['data_source' => $hidden_source],
        ['%s'],
        ['%s']
    );
    
    do_action('hng_gateway_data_restored', $gateway);
    
    if (function_exists('hng_log')) {
        hng_log('Gateway data restored: ' . $gateway);
    }
}

/**
 * Get active data sources based on enabled advanced integrations
 * 
 * @return array List of active gateway sources
 */
function hng_get_active_data_sources() {
    $sources = ['local']; // Always include local shop data
    
    if (get_option('hng_asaas_advanced_integration') === 'yes') {
        $sources[] = 'asaas';
    }
    if (get_option('hng_mercadopago_advanced_integration') === 'yes') {
        $sources[] = 'mercadopago';
    }
    if (get_option('hng_pagarme_advanced_integration') === 'yes') {
        $sources[] = 'pagarme';
    }
    
    return apply_filters('hng_active_data_sources', $sources);
}

/**
 * Filter query to show only data from active sources
 * 
 * @param string $table_name Table to filter (hng_customers, hng_subscriptions)
 * @return string SQL WHERE clause
 */
function hng_filter_data_sources_sql($table_name = 'hng_customers') {
    global $wpdb;
    
    $active_sources = hng_get_active_data_sources();
    
    if (empty($active_sources)) {
        return " WHERE (data_source IS NULL OR data_source = 'local')";
    }
    
    $placeholders = implode(',', array_fill(0, count($active_sources), '%s'));
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Dynamic IN clause with proper prepare
    return $wpdb->prepare(" WHERE (data_source IS NULL OR data_source IN ($placeholders))", $active_sources);
}

/**
 * Check if a gateway's advanced integration is active
 * 
 * @param string $gateway Gateway identifier
 * @return bool
 */
function hng_is_gateway_advanced_active($gateway) {
    $gateway = sanitize_key($gateway);
    return get_option('hng_' . $gateway . '_advanced_integration') === 'yes';
}

/**
 * Tag imported/synced record with data source
 * 
 * @param string $table Table name (customers, subscriptions)
 * @param int $record_id Record ID
 * @param string $source Source gateway
 */
function hng_tag_data_source($table, $record_id, $source) {
    global $wpdb;
    
    $table = sanitize_key($table);
    $source = sanitize_key($source);
    $record_id = absint($record_id);
    
    if (!$record_id) {
        return false;
    }
    
    $wpdb->update(
        $wpdb->prefix . 'hng_' . $table,
        ['data_source' => $source],
        ['id' => $record_id],
        ['%s'],
        ['%d']
    );
    
    return true;
}
