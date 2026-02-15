<?php
/**
 * HNG Commerce: AJAX Reports Handlers
 * 
 * Handles AJAX requests for reports generation
 * 
 * @package HNG_Commerce
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get financial report data
 */
add_action('wp_ajax_hng_get_financial_report', 'hng_ajax_get_financial_report');

function hng_ajax_get_financial_report() {
    check_ajax_referer('hng_reports', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permissáo negada']);
    }
    
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_referer() above
    $post = function_exists('wp_unslash') ? wp_unslash($_POST) : $_POST;
    $filters = isset($post['filters']) ? $post['filters'] : [];
    
    // Sanitize filters
    $sanitized_filters = [
        'start_date' => isset($filters['start_date']) ? sanitize_text_field($filters['start_date']) : '',
        'end_date' => isset($filters['end_date']) ? sanitize_text_field($filters['end_date']) : '',
        'category_id' => isset($filters['category_id']) ? absint($filters['category_id']) : null,
        'product_type' => isset($filters['product_type']) ? sanitize_text_field($filters['product_type']) : null,
        'gateway' => isset($filters['gateway']) ? sanitize_text_field($filters['gateway']) : null,
        'payment_status' => isset($filters['payment_status']) ? sanitize_text_field($filters['payment_status']) : 'completed'
    ];
    
    // Handle predefined periods
    if (!empty($filters['period'])) {
        $period = sanitize_text_field($filters['period']);
        $dates = hng_get_period_dates($period);
        $sanitized_filters['start_date'] = $dates['start'];
        $sanitized_filters['end_date'] = $dates['end'];
    }
    
    $generator = HNG_Reports_Generator::instance();
    $report = $generator->get_financial_report($sanitized_filters);
    
    wp_send_json_success($report);
}

/**
 * Get conversion analytics data
 */
add_action('wp_ajax_hng_get_conversion_data', 'hng_ajax_get_conversion_data');

function hng_ajax_get_conversion_data() {
    check_ajax_referer('hng_reports', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permissáo negada']);
    }
    
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_referer() above
    $post = function_exists('wp_unslash') ? wp_unslash($_POST) : $_POST;
    $period = isset($post['period']) ? sanitize_text_field($post['period']) : '30days';
    
    $dates = hng_get_period_dates($period);
    
    $tracker = HNG_Conversion_Tracker::instance();
    
    $data = [
        'funnel' => $tracker->get_funnel_data($dates['start'], $dates['end']),
        'top_pages' => $tracker->get_top_pages(10, $dates['start'], $dates['end']),
        'top_products' => $tracker->get_top_products(10, $dates['start'], $dates['end']),
        'templates' => $tracker->get_template_performance(10, $dates['start'], $dates['end'])
    ];
    
    wp_send_json_success($data);
}

/**
 * Export report to CSV
 */
add_action('wp_ajax_hng_export_report_csv', 'hng_ajax_export_report_csv');

function hng_ajax_export_report_csv() {
    check_ajax_referer('hng_reports', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_die('Permissáo negada');
    }
    
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET parameter for period selection
    $period = isset($_GET['period']) ? sanitize_text_field(wp_unslash($_GET['period'])) : '30days';
    $dates = hng_get_period_dates($period);
    
    $filters = [
        'start_date' => $dates['start'],
        'end_date' => $dates['end']
    ];
    
    $generator = HNG_Reports_Generator::instance();
    $report = $generator->get_financial_report($filters);
    
    // Export revenue by day
    $generator->export_to_csv(
        $report['revenue_by_day'], 
        'hng-financial-report-' . gmdate('Y-m-d') . '.csv'
    );
}

/**
 * Helper: Get start and end dates for predefined periods
 */
function hng_get_period_dates($period) {
    $end = gmdate('Y-m-d');
    $start = gmdate('Y-m-d');
    
    switch ($period) {
        case 'today':
            // Already set
            break;
        case '7days':
            $start = gmdate('Y-m-d', strtotime('-7 days'));
            break;
        case '30days':
            $start = gmdate('Y-m-d', strtotime('-30 days'));
            break;
        case '90days':
            $start = gmdate('Y-m-d', strtotime('-90 days'));
            break;
        case 'year':
            $start = gmdate('Y-01-01');
            break;
    }
    
    return [
        'start' => $start,
        'end' => $end
    ];
}
