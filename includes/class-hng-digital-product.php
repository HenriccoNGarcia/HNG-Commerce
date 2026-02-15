<?php
/**
 * Digital Product Class
 * 
 * Handles digital product downloads with security and access control
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Digital_Product extends HNG_Product {
    
    /**
     * Get download files
     * 
     * @return array
     */
    public function get_downloadable_files() {
        $files = get_post_meta($this->id, '_downloadable_files', true);
        return is_array($files) ? $files : [];
    }
    
    /**
     * Add downloadable file
     * 
     * @param string $name
     * @param string $file
     * @return bool
     */
    public function add_file($name, $file) {
        $files = $this->get_downloadable_files();
        $file_id = md5($name . time());
        
        $files[$file_id] = [
            'name' => sanitize_text_field($name),
            'file' => sanitize_text_field($file),
            'created' => current_time('mysql')
        ];
        
        return update_post_meta($this->id, '_downloadable_files', $files);
    }
    
    /**
     * Get download limit
     * 
     * @return int -1 = unlimited
     */
    public function get_download_limit() {
        $limit = get_post_meta($this->id, '_download_limit', true);
        return $limit !== '' ? (int) $limit : -1;
    }
    
    /**
     * Set download limit
     * 
     * @param int $limit
     */
    public function set_download_limit($limit) {
        update_post_meta($this->id, '_download_limit', (int) $limit);
    }
    
    /**
     * Get download expiry (in days)
     * 
     * @return int 0 = never expires
     */
    public function get_download_expiry() {
        $expiry = get_post_meta($this->id, '_download_expiry', true);
        return $expiry !== '' ? (int) $expiry : 0;
    }
    
    /**
     * Set download expiry
     * 
     * @param int $days
     */
    public function set_download_expiry($days) {
        update_post_meta($this->id, '_download_expiry', (int) $days);
    }
    
    /**
     * Grant download access to a customer
     * 
     * @param int $order_id
     * @param string $customer_email
     * @return array Download IDs created
     */
    public function grant_access($order_id, $customer_email) {
        global $wpdb;
        
        // Unsplash / sanitize inputs
        if (function_exists('wp_unslash')) {
            $customer_email = wp_unslash($customer_email);
        }
        $customer_email = sanitize_email($customer_email);
        $order_id = intval($order_id);

        $files = $this->get_downloadable_files();
        $limit = $this->get_download_limit();
        $expiry_days = $this->get_download_expiry();
        
        $download_ids = [];
        
        foreach ($files as $file_id => $file) {
            $download_id = $this->generate_download_id();

            $expires_at = null;
            if ($expiry_days > 0) {
                $expires_at = gmdate('Y-m-d H:i:s', strtotime("+{$expiry_days} days"));
            }

            // sanitize file path/name stored in post meta
            $file_path = isset($file['file']) ? sanitize_text_field($file['file']) : '';

            $downloads_table = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_downloads') : ($wpdb->prefix . 'hng_downloads');
            $downloads_table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_downloads') : ('`' . str_replace('`','', $downloads_table) . '`');

            $result = $wpdb->insert(
                $downloads_table,
                [
                    'download_id' => $download_id,
                    'order_id' => $order_id,
                    'product_id' => intval($this->id),
                    'customer_email' => $customer_email,
                    'file_path' => $file_path,
                    'download_limit' => intval($limit),
                    'download_count' => 0,
                    'expires_at' => $expires_at,
                    'created_at' => current_time('mysql')
                ],
                ['%s', '%d', '%d', '%s', '%s', '%d', '%d', '%s', '%s']
            );
            
            if ($result) {
                $download_ids[] = $download_id;
            }
        }
        
        return $download_ids;
    }
    
    /**
     * Generate secure download ID
     * 
     * @return string
     */
    private function generate_download_id() {
        return wp_hash(uniqid('download_', true) . time() . wp_rand());
    }
    
    /**
     * Validate download access
     * 
     * @param string $download_id
     * @return array|WP_Error
     */
    public static function validate_download($download_id) {
        global $wpdb;
        
        // Rate limiting para prevenir brute force de download IDs
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $rate_limit_key = 'hng_download_attempts_' . md5($ip);
        $attempts = (int) get_transient($rate_limit_key);
        
        if ($attempts > 10) {
            error_log(sprintf('HNG Security: Download rate limit exceeded for IP %s', $ip));
            return new WP_Error('rate_limit', __('Muitas tentativas. Aguarde 1 minuto.', 'hng-commerce'));
        }
        
        set_transient($rate_limit_key, $attempts + 1, 60); // 1 minuto
        
        if (function_exists('wp_unslash')) {
            $download_id = wp_unslash($download_id);
        }
        $download_id = sanitize_text_field($download_id);

        $downloads_table = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_downloads') : ($wpdb->prefix . 'hng_downloads');
        $downloads_table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_downloads') : ('`' . str_replace('`','', $downloads_table) . '`');

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $downloads_table_sql sanitized via hng_db_backtick_table()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for digital product delivery, download tracking
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $downloads_table_sql sanitized via hng_db_backtick_table()
        $download = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$downloads_table_sql} WHERE download_id = %s",
            $download_id
        ), ARRAY_A);
        
        if (!$download) {
            return new WP_Error('invalid_download', __('Link de download invï¿½lido.', 'hng-commerce'));
        }
        
        // Check if expired
        if ($download['expires_at'] && strtotime($download['expires_at']) < time()) {
            return new WP_Error('expired', __('Este link de download expirou.', 'hng-commerce'));
        }
        
        // Check download limit
        if ($download['download_limit'] > 0 && $download['download_count'] >= $download['download_limit']) {
            return new WP_Error('limit_exceeded', __('Limite de downloads atingido.', 'hng-commerce'));
        }
        
        return $download;
    }
    
    /**
     * Increment download count
     * 
     * @param string $download_id
     * @param string $ip_address
     * @param string $user_agent
     */
    public static function increment_download($download_id, $ip_address = '', $user_agent = '') {
        global $wpdb;
        if (function_exists('wp_unslash')) {
            $ip_address = wp_unslash($ip_address);
            $user_agent = wp_unslash($user_agent);
            $download_id = wp_unslash($download_id);
        }

        $ip_address = sanitize_text_field($ip_address);
        $user_agent = sanitize_text_field($user_agent);
        $download_id = sanitize_text_field($download_id);

        $downloads_table = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_downloads') : ($wpdb->prefix . 'hng_downloads');
        $downloads_table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_downloads') : ('`' . str_replace('`','', $downloads_table) . '`');

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $downloads_table_sql sanitized via hng_db_backtick_table()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for digital product delivery, download tracking
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $downloads_table_sql sanitized via hng_db_backtick_table()
        $wpdb->query($wpdb->prepare(
            "UPDATE {$downloads_table_sql} 
             SET download_count = download_count + 1,
                 accessed_at = %s,
                 ip_address = %s,
                 user_agent = %s
             WHERE download_id = %s",
            current_time('mysql'),
            $ip_address,
            $user_agent,
            $download_id
        ));
    }
    
    /**
     * Get customer downloads
     * 
     * @param string $customer_email
     * @return array
     */
    public static function get_customer_downloads($customer_email) {
        global $wpdb;
        if (function_exists('wp_unslash')) {
            $customer_email = wp_unslash($customer_email);
        }
        $customer_email = sanitize_email($customer_email);

        $downloads_table = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_downloads') : ($wpdb->prefix . 'hng_downloads');
        $downloads_table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_downloads') : ('`' . str_replace('`','', $downloads_table) . '`');

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $downloads_table_sql sanitized via hng_db_backtick_table()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for digital product delivery, download tracking
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $downloads_table_sql sanitized via hng_db_backtick_table()
        return $wpdb->get_results($wpdb->prepare(
            "SELECT d.*, p.post_title as product_name
             FROM {$downloads_table_sql} d
             LEFT JOIN {$wpdb->posts} p ON d.product_id = p.ID
             WHERE d.customer_email = %s
             ORDER BY d.created_at DESC",
            $customer_email
        ), ARRAY_A);
    }

    /**
     * Normalize/sanitize stored file path for safe usage later
     */
    private static function sanitize_file_path($path) {
        $path = (string) $path;
        $path = trim($path);
        $path = sanitize_text_field($path);
        return $path;
    }
}
