<?php
/**
 * Appointments Admin AJAX Handlers
 * 
 * Handles admin-side appointment management actions
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX: Fetch appointments for admin table
 */
add_action('wp_ajax_hng_admin_fetch_appointments', function() {
    check_ajax_referer('hng-commerce-admin', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Permissão negada.', 'hng-commerce')]);
    }
    
    global $wpdb;
    
    // Carregar helper de DB
    if (file_exists(HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php')) {
        require_once HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php';
    }
    
    $appointments_table_full = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_appointments') : ($wpdb->prefix . 'hng_appointments');
    $appointments_table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_appointments') : ('`' . str_replace('`','', $appointments_table_full) . '`');
    
    // Check if table exists
    $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $appointments_table_full));
    
    if ($table_exists !== $appointments_table_full) {
        wp_send_json_success([]); // Return empty array if table doesn't exist yet
    }
    
    // Get filters
    $post = wp_unslash($_POST);
    $status = sanitize_text_field($post['status'] ?? 'all');
    $date_filter = sanitize_text_field($post['date_filter'] ?? 'all');
    
    // Build query
    $where = [];
    
    if ($status !== 'all') {
        $where[] = $wpdb->prepare("status = %s", $status);
    }
    
    // Date filters
    if ($date_filter === 'today') {
        $where[] = $wpdb->prepare("appointment_date = %s", current_time('Y-m-d'));
    } elseif ($date_filter === 'week') {
        $start_date = gmdate('Y-m-d', current_time('timestamp'));
        $end_date = gmdate('Y-m-d', strtotime($start_date . '+7 days'));
        $where[] = $wpdb->prepare("appointment_date >= %s AND appointment_date <= %s", 
            $start_date, 
            $end_date);
    } elseif ($date_filter === 'month') {
        $start_date = gmdate('Y-m-01', strtotime(gmdate('Y-m-d', current_time('timestamp'))));
        $end_date = gmdate('Y-m-t', strtotime(gmdate('Y-m-d', current_time('timestamp'))));
        $where[] = $wpdb->prepare("appointment_date >= %s AND appointment_date <= %s", 
            $start_date, 
            $end_date);
    }
    
    $where_sql = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';
    
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $appointments = $wpdb->get_results(
        "SELECT * FROM {$appointments_table_sql}{$where_sql} ORDER BY appointment_date DESC, appointment_time DESC LIMIT 200",
        ARRAY_A
    );
    
    $out = [];
    foreach ($appointments as $appt) {
        // Get product name
        $product_name = '';
        if ($appt['product_id']) {
            $product = get_post($appt['product_id']);
            $product_name = $product ? $product->post_title : __('Produto excluído', 'hng-commerce');
        }
        
        // Format date/time
        $date_formatted = date_i18n('d/m/Y', strtotime($appt['appointment_date']));
        $time_formatted = date_i18n('H:i', strtotime($appt['appointment_time']));
        
        $out[] = [
            'id' => intval($appt['id']),
            'customer_name' => esc_html($appt['customer_name'] ?? ''),
            'customer_email' => esc_html($appt['customer_email'] ?? ''),
            'product_name' => esc_html($product_name),
            'product_id' => intval($appt['product_id'] ?? 0),
            'date' => $date_formatted,
            'time' => $time_formatted,
            'duration' => intval($appt['duration'] ?? 60),
            'status' => $appt['status'] ?? 'pending',
            'notes' => esc_html($appt['notes'] ?? ''),
            'created_at' => $appt['created_at'] ?? '',
        ];
    }
    
    wp_send_json_success($out);
});

/**
 * AJAX: Update appointment status
 */
add_action('wp_ajax_hng_admin_update_appointment_status', function() {
    check_ajax_referer('hng-commerce-admin', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Permissão negada.', 'hng-commerce')]);
    }
    
    $post = wp_unslash($_POST);
    $appointment_id = absint($post['appointment_id'] ?? 0);
    $new_status = sanitize_text_field($post['new_status'] ?? '');
    
    if (!$appointment_id || !in_array($new_status, ['pending', 'confirmed', 'cancelled', 'completed'])) {
        wp_send_json_error(['message' => __('Dados inválidos.', 'hng-commerce')]);
    }
    
    $appointment = new HNG_Appointment($appointment_id);
    
    if (!$appointment->get_id()) {
        wp_send_json_error(['message' => __('Agendamento não encontrado.', 'hng-commerce')]);
    }
    
    $result = $appointment->update_status($new_status);
    
    if ($result) {
        wp_send_json_success([
            'message' => __('Status atualizado com sucesso.', 'hng-commerce'),
            'new_status' => $new_status
        ]);
    } else {
        wp_send_json_error(['message' => __('Erro ao atualizar status.', 'hng-commerce')]);
    }
});

/**
 * AJAX: Send confirmation email
 */
add_action('wp_ajax_hng_admin_send_appointment_email', function() {
    check_ajax_referer('hng-commerce-admin', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Permissão negada.', 'hng-commerce')]);
    }
    
    $post = wp_unslash($_POST);
    $appointment_id = absint($post['appointment_id'] ?? 0);
    
    if (!$appointment_id) {
        wp_send_json_error(['message' => __('Agendamento não encontrado.', 'hng-commerce')]);
    }
    
    $appointment = new HNG_Appointment($appointment_id);
    
    if (!$appointment->get_id()) {
        wp_send_json_error(['message' => __('Agendamento não encontrado.', 'hng-commerce')]);
    }
    
    // Enviar email de confirmação
    if (method_exists($appointment, 'send_confirmation_email')) {
        $result = $appointment->send_confirmation_email();
        if ($result) {
            wp_send_json_success(['message' => __('E-mail enviado com sucesso.', 'hng-commerce')]);
        }
    }
    
    wp_send_json_error(['message' => __('Erro ao enviar e-mail.', 'hng-commerce')]);
});

/**
 * AJAX: Create appointment manually (admin)
 */
add_action('wp_ajax_hng_admin_create_appointment', function() {
    check_ajax_referer('hng-commerce-admin', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Permissão negada.', 'hng-commerce')]);
    }
    
    global $wpdb;
    
    $post = wp_unslash($_POST);
    
    // Validar dados obrigatórios
    $product_id = absint($post['product_id'] ?? 0);
    $customer_name = sanitize_text_field($post['customer_name'] ?? '');
    $customer_email = sanitize_email($post['customer_email'] ?? '');
    $appointment_date = sanitize_text_field($post['appointment_date'] ?? '');
    $appointment_time = sanitize_text_field($post['appointment_time'] ?? '');
    $duration = absint($post['duration'] ?? 60);
    $status = sanitize_text_field($post['status'] ?? 'pending');
    $professional_id = absint($post['professional_id'] ?? 0);
    
    // Validação básica
    if (!$product_id || !$customer_name || !$customer_email || !$appointment_date || !$appointment_time) {
        wp_send_json_error(['message' => __('Preencha todos os campos obrigatórios.', 'hng-commerce')]);
    }
    
    if (!is_email($customer_email)) {
        wp_send_json_error(['message' => __('E-mail inválido.', 'hng-commerce')]);
    }
    
    // Verificar se produto existe
    $product = get_post($product_id);
    if (!$product || $product->post_type !== 'hng_product') {
        wp_send_json_error(['message' => __('Produto não encontrado.', 'hng-commerce')]);
    }
    
    // Carregar helper de DB
    if (file_exists(HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php')) {
        require_once HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php';
    }
    
    $appointments_table = function_exists('hng_db_full_table_name') 
        ? hng_db_full_table_name('hng_appointments') 
        : ($wpdb->prefix . 'hng_appointments');
    
    // Inserir agendamento
    $result = $wpdb->insert(
        $appointments_table,
        [
            'product_id' => $product_id,
            'customer_name' => $customer_name,
            'customer_email' => $customer_email,
            'appointment_date' => $appointment_date,
            'appointment_time' => $appointment_time,
            'duration' => $duration,
            'status' => in_array($status, ['pending', 'confirmed', 'completed', 'cancelled']) ? $status : 'pending',
            'professional_id' => $professional_id,
            'created_at' => current_time('mysql'),
            'notes' => sanitize_textarea_field($post['notes'] ?? '')
        ],
        ['%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s']
    );
    
    if ($result) {
        $appointment_id = $wpdb->insert_id;
        wp_send_json_success([
            'message' => __('Agendamento criado com sucesso.', 'hng-commerce'),
            'appointment_id' => $appointment_id
        ]);
    } else {
        wp_send_json_error(['message' => __('Erro ao criar agendamento.', 'hng-commerce')]);
    }
});
