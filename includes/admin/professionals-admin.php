<?php
/**
 * Professionals Admin AJAX Handlers
 * 
 * Handles admin-side professional management actions
 * 
 * @package HNG_Commerce
 * @since 1.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX: Fetch all professionals
 */
add_action('wp_ajax_hng_admin_fetch_professionals', function() {
    check_ajax_referer('hng-commerce-admin', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Permissão negada.', 'hng-commerce')]);
    }
    
    global $wpdb;
    
    // Carregar helper de DB
    if (file_exists(HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php')) {
        require_once HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php';
    }
    
    $professionals_table = function_exists('hng_db_full_table_name') 
        ? hng_db_full_table_name('hng_professionals') 
        : ($wpdb->prefix . 'hng_professionals');
    
    $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $professionals_table));
    
    if ($table_exists !== $professionals_table) {
        wp_send_json_success([]);
    }
    
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name é sanitizado via hng_db_backtick_table()
    $professionals = $wpdb->get_results("SELECT * FROM `" . str_replace('`', '', $professionals_table) . "` ORDER BY name ASC", ARRAY_A);
    
    $out = [];
    foreach ($professionals as $prof) {
        $wp_user = get_user_by('id', $prof['wp_user_id']);
        $out[] = [
            'id' => intval($prof['id']),
            'name' => esc_html($prof['name']),
            'email' => esc_html($prof['email']),
            'phone' => esc_html($prof['phone'] ?? ''),
            'wp_user_id' => intval($prof['wp_user_id'] ?? 0),
            'wp_user_name' => $wp_user ? esc_html($wp_user->display_name) : '',
            'active' => intval($prof['active'] ?? 1),
            'notes' => esc_html($prof['notes'] ?? ''),
            'created_at' => $prof['created_at'] ?? '',
        ];
    }
    
    wp_send_json_success($out);
});

/**
 * AJAX: Create or update professional
 */
add_action('wp_ajax_hng_admin_save_professional', function() {
    check_ajax_referer('hng-commerce-admin', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Permissão negada.', 'hng-commerce')]);
    }
    
    global $wpdb;
    
    $post = wp_unslash($_POST);
    
    // Validar dados obrigatórios
    $id = absint($post['id'] ?? 0);
    $name = sanitize_text_field($post['name'] ?? '');
    $email = sanitize_email($post['email'] ?? '');
    $phone = sanitize_text_field($post['phone'] ?? '');
    $wp_user_id = absint($post['wp_user_id'] ?? 0);
    $active = isset($post['active']) ? 1 : 0;
    $notes = sanitize_textarea_field($post['notes'] ?? '');
    
    if (!$name || !$email) {
        wp_send_json_error(['message' => __('Nome e e-mail são obrigatórios.', 'hng-commerce')]);
    }
    
    if (!is_email($email)) {
        wp_send_json_error(['message' => __('E-mail inválido.', 'hng-commerce')]);
    }
    
    // Carregar helper de DB
    if (file_exists(HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php')) {
        require_once HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php';
    }
    
    $professionals_table = function_exists('hng_db_full_table_name') 
        ? hng_db_full_table_name('hng_professionals') 
        : ($wpdb->prefix . 'hng_professionals');
    
    // Criar tabela se não existir
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS `" . str_replace('`', '', $professionals_table) . "` (
        id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        phone VARCHAR(20),
        wp_user_id BIGINT(20),
        active TINYINT(1) DEFAULT 1,
        notes LONGTEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY wp_user_id (wp_user_id),
        KEY active (active)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    if ($id) {
        // Update existing
        $result = $wpdb->update(
            $professionals_table,
            [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'wp_user_id' => $wp_user_id,
                'active' => $active,
                'notes' => $notes,
            ],
            ['id' => $id],
            ['%s', '%s', '%s', '%d', '%d', '%s'],
            ['%d']
        );
        
        if ($result !== false) {
            wp_send_json_success([
                'message' => __('Profissional atualizado com sucesso.', 'hng-commerce'),
                'professional_id' => $id
            ]);
        } else {
            wp_send_json_error(['message' => __('Erro ao atualizar profissional.', 'hng-commerce')]);
        }
    } else {
        // Create new
        $result = $wpdb->insert(
            $professionals_table,
            [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'wp_user_id' => $wp_user_id,
                'active' => $active,
                'notes' => $notes,
            ],
            ['%s', '%s', '%s', '%d', '%d', '%s']
        );
        
        if ($result) {
            wp_send_json_success([
                'message' => __('Profissional criado com sucesso.', 'hng-commerce'),
                'professional_id' => $wpdb->insert_id
            ]);
        } else {
            wp_send_json_error(['message' => __('Erro ao criar profissional.', 'hng-commerce')]);
        }
    }
});

/**
 * AJAX: Delete professional
 */
add_action('wp_ajax_hng_admin_delete_professional', function() {
    check_ajax_referer('hng-commerce-admin', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Permissão negada.', 'hng-commerce')]);
    }
    
    global $wpdb;
    
    $post = wp_unslash($_POST);
    $professional_id = absint($post['professional_id'] ?? 0);
    
    if (!$professional_id) {
        wp_send_json_error(['message' => __('ID do profissional inválido.', 'hng-commerce')]);
    }
    
    // Carregar helper de DB
    if (file_exists(HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php')) {
        require_once HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php';
    }
    
    $professionals_table = function_exists('hng_db_full_table_name') 
        ? hng_db_full_table_name('hng_professionals') 
        : ($wpdb->prefix . 'hng_professionals');
    
    $result = $wpdb->delete(
        $professionals_table,
        ['id' => $professional_id],
        ['%d']
    );
    
    if ($result) {
        wp_send_json_success(['message' => __('Profissional deletado com sucesso.', 'hng-commerce')]);
    } else {
        wp_send_json_error(['message' => __('Erro ao deletar profissional.', 'hng-commerce')]);
    }
});
