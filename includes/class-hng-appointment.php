<?php
/**
 * Appointment Class
 * 
 * Handles appointment booking products with date/time management
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// DB helper
if (file_exists(HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php')) {
    require_once HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php';
}

class HNG_Appointment {
    
    /**
     * Appointment ID
     */
    private $id;
    
    /**
     * Appointment data
     */
    private $data = [];
    
    /**
     * Constructor
     */
    public function __construct($appointment_id = 0) {
        if ($appointment_id) {
            $this->id = $appointment_id;
            $this->load();
        }
    }
    
    /**
     * Load appointment
     */
    private function load() {
        global $wpdb;
        $appointments_table_full = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_appointments') : ($wpdb->prefix . 'hng_appointments');
        $appointments_table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_appointments') : ('`' . str_replace('`','', $appointments_table_full) . '`');

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tables sanitized via hng_db_backtick_table()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom appointments table query, load single appointment by ID
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Tables sanitized via hng_db_backtick_table()
        $appointment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$appointments_table_sql} WHERE id = %d",
            $this->id
        ), ARRAY_A);
        
        if ($appointment) {
            $this->data = $appointment;
        }
    }
    
    /**
     * Create new appointment
     */
    public static function create($data) {
        global $wpdb;
        
        $defaults = [
            'order_id' => 0,
            'product_id' => 0,
            'customer_name' => '',
            'customer_email' => '',
            'customer_phone' => '',
            'appointment_date' => '',
            'appointment_time' => '',
            'duration' => 60, // minutes
            'status' => 'pending',
            'notes' => '',
            'created_at' => current_time('mysql'),
        ];
        
        $data = wp_parse_args($data, $defaults);

        // Sanitize incoming data
        $data['order_id'] = isset($data['order_id']) ? absint($data['order_id']) : 0;
        $data['product_id'] = isset($data['product_id']) ? absint($data['product_id']) : 0;
        $data['customer_name'] = isset($data['customer_name']) ? sanitize_text_field(wp_unslash($data['customer_name'])) : '';
        $data['customer_email'] = isset($data['customer_email']) ? sanitize_email(wp_unslash($data['customer_email'])) : '';
        $data['customer_phone'] = isset($data['customer_phone']) ? sanitize_text_field(wp_unslash($data['customer_phone'])) : '';
        $data['appointment_date'] = isset($data['appointment_date']) ? sanitize_text_field(wp_unslash($data['appointment_date'])) : '';
        $data['appointment_time'] = isset($data['appointment_time']) ? sanitize_text_field(wp_unslash($data['appointment_time'])) : '';
        $data['duration'] = isset($data['duration']) ? absint($data['duration']) : 60;
        $data['status'] = isset($data['status']) ? sanitize_text_field(wp_unslash($data['status'])) : 'pending';
        $data['notes'] = isset($data['notes']) ? sanitize_textarea_field(wp_unslash($data['notes'])) : '';
        
        // Validate availability
        if (!self::is_slot_available($data['product_id'], $data['appointment_date'], $data['appointment_time'], $data['duration'])) {
            return new WP_Error('unavailable', __('Horário não disponível.', 'hng-commerce'));
        }
        
        $appointments_table_full = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_appointments') : ($wpdb->prefix . 'hng_appointments');

        $result = $wpdb->insert(
            $appointments_table_full,
            $data,
            ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s']
        );
        
        if ($result) {
            return new self($wpdb->insert_id);
        }
        
        return false;
    }
    
    /**
     * Get appointment ID
     */
    public function get_id() {
        return $this->id;
    }
    
    /**
     * Get customer email
     */
    public function get_customer_email() {
        return $this->data['customer_email'] ?? '';
    }
    
    /**
     * Get status
     */
    public function get_status() {
        return $this->data['status'] ?? 'pending';
    }
    
    /**
     * Get appointment date
     */
    public function get_date() {
        return $this->data['appointment_date'] ?? '';
    }
    
    /**
     * Get appointment time
     */
    public function get_time() {
        return $this->data['appointment_time'] ?? '';
    }
    
    /**
     * Get duration in minutes
     */
    public function get_duration() {
        return intval($this->data['duration'] ?? 60);
    }
    
    /**
     * Update status
     */
    public function update_status($new_status) {
        global $wpdb;
        
        $old_status = $this->get_status();
        
        $appointments_table_full = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_appointments') : ($wpdb->prefix . 'hng_appointments');

        $wpdb->update(
            $appointments_table_full,
            ['status' => $new_status, 'updated_at' => current_time('mysql')],
            ['id' => $this->id],
            ['%s', '%s'],
            ['%d']
        );
        
        $this->data['status'] = $new_status;
        
        // Send email notifications
        if ($new_status === 'confirmed') {
            $this->send_confirmation_email();
        } elseif ($new_status === 'cancelled') {
            $this->send_cancellation_email();
        }
        
        do_action('hng_appointment_status_changed', $this->id, $old_status, $new_status);
    }
    
    /**
     * Confirm appointment
     */
    public function confirm() {
        $this->update_status('confirmed');
    }
    
    /**
     * Cancel appointment
     */
    public function cancel() {
        $this->update_status('cancelled');
    }
    
    /**
     * Complete appointment
     */
    public function complete() {
        $this->update_status('completed');
    }
    
    /**
     * Send confirmation email using customized template
     */
    private function send_confirmation_email() {
        $this->send_appointment_email('appointment_confirmation');
    }
    
    /**
     * Send cancellation email using customized template
     */
    private function send_cancellation_email() {
        $this->send_appointment_email('appointment_cancelled');
    }
    
    /**
     * Send appointment email with customized template
     * 
     * @param string $email_type Type of email (appointment_confirmation or appointment_cancelled)
     */
    private function send_appointment_email($email_type) {
        // Get customer email
        $to = $this->get_customer_email();
        
        // Get product info
        $product = new HNG_Product($this->data['product_id']);
        $product_name = $product->get_name();
        
        // Prepare email variables
        $email_vars = [
            '{{customer_name}}' => $this->data['customer_name'],
            '{{service_name}}' => $product_name,
            '{{appointment_date}}' => date_i18n(get_option('date_format'), strtotime($this->get_date())),
            '{{appointment_time}}' => $this->get_time(),
            '{{duration}}' => $this->get_duration(),
            '{{location}}' => get_post_meta($this->data['product_id'], '_appointment_location', true) ?: '',
            '{{cancellation_reason}}' => $this->data['notes'] ?? '',
        ];
        
        // Get saved template
        $template = get_option("hng_email_template_{$email_type}", []);
        $global_settings = get_option('hng_email_global_settings', []);
        
        // Get subject and content
        $subject = isset($template['subject']) && !empty($template['subject']) 
            ? $template['subject']
            : ($email_type === 'appointment_confirmation' 
                ? __('Agendamento Confirmado', 'hng-commerce')
                : __('Agendamento Cancelado', 'hng-commerce'));
        
        $content = isset($template['content']) && !empty($template['content'])
            ? $template['content']
            : '';
        
        // Replace variables in subject and content
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
        wp_mail($to, $subject, $message, $headers);
    }
    
    /**
     * Check if time slot is available
     */
    public static function is_slot_available($product_id, $date, $time, $duration) {
        global $wpdb;
        
        // Get product settings
        $product_id = absint($product_id);
        $date = sanitize_text_field(wp_unslash($date));
        $time = sanitize_text_field(wp_unslash($time));
        $duration = absint($duration);

        $max_per_slot = get_post_meta($product_id, '_appointment_max_per_slot', true) ?: 1;
        
        // Calculate end time
        $start_timestamp = strtotime($date . ' ' . $time);
        $end_timestamp = $start_timestamp + ($duration * 60);
        
        // Check overlapping appointments
        $appointments_table_full = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_appointments') : ($wpdb->prefix . 'hng_appointments');
        $appointments_table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_appointments') : ('`' . str_replace('`','', $appointments_table_full) . '`');

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tables sanitized via hng_db_backtick_table()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom appointments table query, check for overlapping appointments to prevent double-booking
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Tables sanitized via hng_db_backtick_table()
        $overlapping = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$appointments_table_sql} 
             WHERE product_id = %d 
             AND appointment_date = %s 
             AND status IN ('pending', 'confirmed')
             AND (
                 (UNIX_TIMESTAMP(CONCAT(appointment_date, ' ', appointment_time)) < %d 
                  AND UNIX_TIMESTAMP(CONCAT(appointment_date, ' ', appointment_time)) + (duration * 60) > %d)
                 OR
                 (UNIX_TIMESTAMP(CONCAT(appointment_date, ' ', appointment_time)) >= %d 
                  AND UNIX_TIMESTAMP(CONCAT(appointment_date, ' ', appointment_time)) < %d)
             )",
            $product_id,
            $date,
            $end_timestamp,
            $start_timestamp,
            $start_timestamp,
            $end_timestamp
        ));
        
        return $overlapping < $max_per_slot;
    }
    
    /**
     * Get available slots for a specific date
     */
    public static function get_available_slots($product_id, $date) {
        $slots = [];
        
        // Get product settings
        $start_time = get_post_meta($product_id, '_appointment_start_time', true) ?: '09:00';
        $end_time = get_post_meta($product_id, '_appointment_end_time', true) ?: '18:00';
        $slot_duration = get_post_meta($product_id, '_appointment_duration', true) ?: 60;
        $buffer_time = get_post_meta($product_id, '_appointment_buffer', true) ?: 0;
        
        $current_time = strtotime($date . ' ' . $start_time);
        $end = strtotime($date . ' ' . $end_time);
        
        while ($current_time < $end) {
            $slot_time = gmdate('H:i', $current_time);
            
            if (self::is_slot_available($product_id, $date, $slot_time, $slot_duration)) {
                $slots[] = [
                    'time' => $slot_time,
                    'display' => gmdate('H:i', $current_time),
                    'available' => true
                ];
            }
            
            $current_time += ($slot_duration + $buffer_time) * 60;
        }
        
        return $slots;
    }
    
    /**
     * Get customer appointments
     */
    public static function get_customer_appointments($customer_email, $status = 'any') {
        global $wpdb;
        $appointments_table_full = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_appointments') : ($wpdb->prefix . 'hng_appointments');
        $appointments_table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_appointments') : ('`' . str_replace('`','', $appointments_table_full) . '`');

        $customer_email = sanitize_email( wp_unslash( $customer_email ) );

        if ($status !== 'any') {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tables sanitized via hng_db_backtick_table()
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom appointments table query, get customer appointments filtered by status
            // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Tables sanitized via hng_db_backtick_table()
            return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$appointments_table_sql} WHERE customer_email = %s AND status = %s ORDER BY appointment_date DESC, appointment_time DESC", $customer_email, $status ), ARRAY_A );
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tables sanitized via hng_db_backtick_table()
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom appointments table query, get all customer appointments
            // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Tables sanitized via hng_db_backtick_table()
            return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$appointments_table_sql} WHERE customer_email = %s ORDER BY appointment_date DESC, appointment_time DESC", $customer_email ), ARRAY_A );
        }
    }
    
    /**
     * Get appointments for a specific date range
     */
    public static function get_appointments_by_date_range($product_id, $start_date, $end_date) {
        global $wpdb;
        $appointments_table_full = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_appointments') : ($wpdb->prefix . 'hng_appointments');
        $appointments_table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_appointments') : ('`' . str_replace('`','', $appointments_table_full) . '`');

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tables sanitized via hng_db_backtick_table()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom appointments table query, get appointments in date range for calendar display
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Tables sanitized via hng_db_backtick_table()
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$appointments_table_sql} 
             WHERE product_id = %d 
             AND appointment_date BETWEEN %s AND %s 
             AND status IN ('pending', 'confirmed')
             ORDER BY appointment_date ASC, appointment_time ASC",
            $product_id,
            $start_date,
            $end_date
        ), ARRAY_A);
    }
}
