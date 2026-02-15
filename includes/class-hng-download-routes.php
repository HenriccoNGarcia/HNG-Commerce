<?php
/**
 * Download Routes Handler
 * 
 * Manages download routes for digital products
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register download route
 */
add_action('init', function() {
    add_rewrite_rule(
        '^download/([a-zA-Z0-9]+)/?$',
        'index.php?hng_download_id=$matches[1]',
        'top'
    );
    
    add_filter('query_vars', function($vars) {
        $vars[] = 'hng_download_id';
        return $vars;
    });
}, 5);

/**
 * Handle download template redirect
 */
add_action('template_redirect', function() {
    $download_id = get_query_var('hng_download_id');
    
    if ($download_id) {
        $template = HNG_COMMERCE_PATH . 'templates/download-handler.php';
        
        if (file_exists($template)) {
            include $template;
            exit;
        }
    }
});

/**
 * Grant download access after payment confirmation
 */
add_action('hng_order_status_changed', function($order_id, $old_status, $new_status) {
    // Only grant access when order is completed or processing
    if (!in_array($new_status, ['processing', 'completed'])) {
        return;
    }
    
    $order = new HNG_Order($order_id);
    $items = $order->get_items();
    $customer_email = $order->get_customer_email();
    $customer_name = $order->get_customer_name();
    
    foreach ($items as $item) {
        $product = new HNG_Product($item['product_id']);
        
        // Check if product is downloadable
        if ($product->is_downloadable()) {
            $digital_product = new HNG_Digital_Product($item['product_id']);
            $digital_product->grant_access($order_id, $customer_email);
            
            // Send digital access email
            hng_send_digital_access_email($order_id, $item['product_id'], $customer_name, $customer_email);
        }
    }
}, 10, 3);

/**
 * Send digital access granted email
 * 
 * @param int $order_id
 * @param int $product_id
 * @param string $customer_name
 * @param string $customer_email
 */
function hng_send_digital_access_email($order_id, $product_id, $customer_name, $customer_email) {
    // Get product info
    $product = new HNG_Digital_Product($product_id);
    $product_name = $product->get_name();
    $files = $product->get_downloadable_files();
    $file_count = count($files);
    $download_limit = $product->get_download_limit();
    $expiry_days = $product->get_download_expiry();
    
    // Get downloads to create download link
    global $wpdb;
    $downloads_table = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_downloads') : ($wpdb->prefix . 'hng_downloads');
    $downloads_table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_downloads') : ('`' . str_replace('`','', $downloads_table) . '`');
    
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
    $downloads = $wpdb->get_results($wpdb->prepare(
        "SELECT download_id, expires_at FROM {$downloads_table_sql} WHERE order_id = %d AND product_id = %d LIMIT 1",
        $order_id,
        $product_id
    ));
    
    $download_link = '';
    $expires_at = __('Nunca', 'hng-commerce');
    
    if (!empty($downloads)) {
        $download = $downloads[0];
        $download_link = home_url('/download/' . $download->download_id);
        
        if ($download->expires_at) {
            $expires_at = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($download->expires_at));
        }
    }
    
    // Prepare email variables
    $count_remaining = ($download_limit === -1) ? __('Ilimitados', 'hng-commerce') : $download_limit;
    
    $email_vars = [
        '{{customer_name}}' => $customer_name,
        '{{product_name}}' => $product_name,
        '{{download_link}}' => $download_link,
        '{{download_count_remaining}}' => $count_remaining,
        '{{expires_at}}' => $expires_at,
        '{{order_id}}' => $order_id,
        '{{file_count}}' => $file_count,
    ];
    
    // Get saved template
    $template = get_option('hng_email_template_digital_access_granted', []);
    $global_settings = get_option('hng_email_global_settings', []);
    
    // Get subject and content
    $subject = isset($template['subject']) && !empty($template['subject']) 
        ? $template['subject']
        : __('Seu Download está Pronto', 'hng-commerce');
    
    $content = isset($template['content']) && !empty($template['content'])
        ? $template['content']
        : '';
    
    // Replace variables
    foreach ($email_vars as $var_key => $var_value) {
        $subject = str_replace($var_key, $var_value, $subject);
        $content = str_replace($var_key, $var_value, $content);
    }
    
    // Build HTML email with global settings
    $logo = isset($template['logo']) && !empty($template['logo']) 
        ? $template['logo']
        : (isset($global_settings['logo_url']) ? $global_settings['logo_url'] : '');
    
    $header_color = isset($template['header_color']) && !empty($template['header_color'])
        ? $template['header_color']
        : (isset($global_settings['header_color']) ? $global_settings['header_color'] : '#0073aa');
    
    $text_color = isset($template['text_color']) && !empty($template['text_color'])
        ? $template['text_color']
        : (isset($global_settings['text_color']) ? $global_settings['text_color'] : '#333333');
    
    $footer_text = isset($template['footer']) && !empty($template['footer'])
        ? $template['footer']
        : (isset($global_settings['footer_text']) ? $global_settings['footer_text'] : get_bloginfo('name'));
    
    $message = '<html><body style="font-family: Arial, sans-serif; color: ' . esc_attr($text_color) . ';">';
    
    // Logo
    if ($logo) {
        $message .= '<div style="text-align: center; margin-bottom: 20px;">';
        $message .= '<img src="' . esc_url($logo) . '" alt="' . esc_attr(get_bloginfo('name')) . '" style="max-width: 200px; height: auto;">';
        $message .= '</div>';
    }
    
    // Header
    if (isset($template['header']) && !empty($template['header'])) {
        $message .= '<div style="background-color: ' . esc_attr($header_color) . '; color: white; padding: 20px; text-align: center; border-radius: 5px; margin-bottom: 20px;">';
        $message .= wp_kses_post($template['header']);
        $message .= '</div>';
    }
    
    // Content
    $message .= '<div style="padding: 20px; color: ' . esc_attr($text_color) . ';">';
    $message .= wp_kses_post(wpautop($content));
    $message .= '</div>';
    
    // Footer
    if ($footer_text) {
        $message .= '<div style="border-top: 1px solid #ddd; padding-top: 20px; margin-top: 20px; text-align: center; font-size: 12px; color: #666;">';
        $message .= wp_kses_post($footer_text);
        $message .= '</div>';
    }
    
    $message .= '</body></html>';
    
    // Set up email headers for HTML
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    
    // Send email
    wp_mail($customer_email, $subject, $message, $headers);
}
