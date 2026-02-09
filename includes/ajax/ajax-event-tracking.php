<?php
/**
 * HNG Commerce: Event Tracking AJAX Handler
 * 
 * Handles frontend event tracking requests
 * 
 * @package HNG_Commerce
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Track conversion event
 */
add_action('wp_ajax_hng_track_event', 'hng_ajax_track_event');
add_action('wp_ajax_nopriv_hng_track_event', 'hng_ajax_track_event');

function hng_ajax_track_event() {
    check_ajax_referer('hng_tracking', 'nonce');
    
    $post = function_exists('wp_unslash') ? wp_unslash($_POST) : $_POST;
    $event_data = isset($post['event_data']) ? $post['event_data'] : [];
    
    if (empty($event_data['event_type'])) {
        wp_send_json_error(['message' => 'Event type is required']);
    }
    
    // Sanitize event data
    $sanitized_data = [
        'event_type' => sanitize_text_field($event_data['event_type']),
        'session_id' => sanitize_text_field($event_data['session_id'] ?? ''),
        'product_id' => isset($event_data['product_id']) ? absint($event_data['product_id']) : null,
        'page_id' => isset($event_data['page_id']) ? absint($event_data['page_id']) : null,
        'page_url' => isset($event_data['page_url']) ? esc_url_raw($event_data['page_url']) : null,
        'template_id' => isset($event_data['template_id']) ? absint($event_data['template_id']) : null,
        'template_name' => isset($event_data['template_name']) ? sanitize_text_field($event_data['template_name']) : null,
        'referrer' => isset($event_data['referrer']) ? esc_url_raw($event_data['referrer']) : null,
        'metadata' => isset($event_data['metadata']) ? wp_json_encode($event_data['metadata']) : null
    ];
    
    // Insert event
    global $wpdb;
    $table_name = $wpdb->prefix . 'hng_conversion_events';
    
    $result = $wpdb->insert($table_name, array_merge($sanitized_data, [
        'user_id' => get_current_user_id() ?: null,
        'created_at' => current_time('mysql')
    ]));
    
    if ($result) {
        wp_send_json_success(['message' => 'Event tracked']);
    } else {
        wp_send_json_error(['message' => 'Failed to track event']);
    }
}
