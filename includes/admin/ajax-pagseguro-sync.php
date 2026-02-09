<?php
/**
 * HNG Commerce: AJAX Handlers para Sincronização PagSeguro/PagBank
 * 
 * Handlers AJAX para sincronização de dados do PagSeguro/PagBank
 *
 * @package HNG_Commerce
 * @since 1.2.12
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX: Sincronizar assinaturas do PagSeguro
 */
add_action('wp_ajax_hng_pagseguro_sync_subscriptions', 'hng_ajax_sync_pagseguro_subscriptions');
function hng_ajax_sync_pagseguro_subscriptions() {
    // Validate AJAX nonce (supports two actions)
    $nonce_valid = wp_verify_nonce($_POST['nonce'] ?? '', 'hng_pagseguro_sync_nonce')
                || wp_verify_nonce($_POST['nonce'] ?? '', 'hng-commerce-admin');
    
    if (!$nonce_valid) {
        wp_send_json_error(['error' => __('Sessão expirada. Recarregue a página.', 'hng-commerce')], 403);
    }

    if (class_exists('HNG_Rate_Limiter')) {
        $rl = HNG_Rate_Limiter::enforce('pagseguro_sync_subscriptions', 3, 120);
        if (is_wp_error($rl)) {
            wp_send_json_error(['error' => $rl->get_error_message()], 429);
        }
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['error' => __('Permissão negada.', 'hng-commerce')]);
        return;
    }
    
    // Capture and validate optional date parameters
    $start_date = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';
    $end_date   = isset($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : '';
    
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
        $start_date = '';
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
        $end_date = '';
    }
    
    // Check if advanced integration is enabled
    $advanced_enabled = get_option('hng_pagseguro_advanced_integration', 'no') === 'yes';
    if (!$advanced_enabled) {
        wp_send_json_error(['error' => __('Integração avançada não está habilitada. Ative-a nas configurações do gateway.', 'hng-commerce')]);
        return;
    }
    
    // Load sync class
    $sync_file = HNG_COMMERCE_PATH . 'includes/integrations/class-hng-pagseguro-sync.php';
    if (!file_exists($sync_file)) {
        wp_send_json_error(['error' => __('Arquivo de sincronização não encontrado.', 'hng-commerce')]);
        return;
    }
    require_once $sync_file;
    
    $sync = new HNG_PagSeguro_Sync();
    
    if (!$sync->is_configured()) {
        wp_send_json_error(['error' => __('Token do PagBank não configurado.', 'hng-commerce')]);
        return;
    }
    
    $result = $sync->import_subscriptions(null, $start_date, $end_date);
    
    if (!$result['success']) {
        error_log('HNG PagSeguro sync subscriptions error: ' . print_r($result, true));
        wp_send_json_error([
            'error' => $result['error'] ?: __('Erro desconhecido', 'hng-commerce')
        ]);
        return;
    }
    
    // Build message with source information
    $source_label = '';
    if (isset($result['source'])) {
        if ('legacy_api' === $result['source']) {
            $source_label = ' ' . __('(via API legada)', 'hng-commerce');
        } elseif ('pagbank_api' === $result['source']) {
            $source_label = ' ' . __('(via API PagBank)', 'hng-commerce');
        }
    }
    
    // Use message from result if present, otherwise build it
    $message = '';
    if (!empty($result['message'])) {
        $message = $result['message'];
    } else {
        $message = sprintf(
            /* translators: 1: number processed, 2: number created, 3: number updated */
            __('Sincronização concluída: %1$d processados, %2$d criados, %3$d atualizados%4$s.', 'hng-commerce'),
            $result['processed'],
            $result['created'],
            $result['updated'],
            $source_label
        );
    }
    
    // Add warning if no subscriptions were found
    if (0 === $result['processed'] && empty($result['message'])) {
        $message = __('Nenhuma assinatura encontrada para sincronizar. Verifique se há assinaturas ativas no PagBank.', 'hng-commerce');
    }
    
    wp_send_json_success([
        'processed' => $result['processed'],
        'created' => $result['created'],
        'updated' => $result['updated'],
        'source' => $result['source'] ?? 'unknown',
        'message' => $message
    ]);
}

/**
 * AJAX: Sincronizar pagamentos/faturamento do PagSeguro
 */
add_action('wp_ajax_hng_pagseguro_sync_payments', 'hng_ajax_sync_pagseguro_payments');
function hng_ajax_sync_pagseguro_payments() {
    // Validate AJAX nonce (supports two actions)
    $nonce_valid = wp_verify_nonce($_POST['nonce'] ?? '', 'hng_pagseguro_sync_nonce')
                || wp_verify_nonce($_POST['nonce'] ?? '', 'hng-commerce-admin');
    
    if (!$nonce_valid) {
        wp_send_json_error(['error' => __('Sessão expirada. Recarregue a página.', 'hng-commerce')], 403);
    }

    if (class_exists('HNG_Rate_Limiter')) {
        $rl = HNG_Rate_Limiter::enforce('pagseguro_sync_payments', 3, 120);
        if (is_wp_error($rl)) {
            wp_send_json_error(['error' => $rl->get_error_message()], 429);
        }
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['error' => __('Permissão negada.', 'hng-commerce')]);
        return;
    }
    
    // Check if advanced integration is enabled
    $advanced_enabled = get_option('hng_pagseguro_advanced_integration', 'no') === 'yes';
    if (!$advanced_enabled) {
        wp_send_json_error(['error' => __('Integração avançada não está habilitada.', 'hng-commerce')]);
        return;
    }
    
    // Get date range or days fallback (default 30)
    $days = isset($_POST['days']) ? intval($_POST['days']) : 30;
    if ($days < 1) $days = 30;
    if ($days > 365) $days = 365;

    $start_date = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';
    $end_date   = isset($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : '';

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
        $start_date = '';
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
        $end_date = '';
    }
    
    // Load sync class
    $sync_file = HNG_COMMERCE_PATH . 'includes/integrations/class-hng-pagseguro-sync.php';
    if (!file_exists($sync_file)) {
        wp_send_json_error(['error' => __('Arquivo de sincronização não encontrado.', 'hng-commerce')]);
        return;
    }
    require_once $sync_file;
    
    $sync = new HNG_PagSeguro_Sync();
    
    if (!$sync->is_configured()) {
        wp_send_json_error(['error' => __('Token do PagBank não configurado.', 'hng-commerce')]);
        return;
    }
    
    $result = $sync->import_payments($days, $start_date, $end_date);
    
    if (!$result['success']) {
        error_log('HNG PagSeguro sync payments error: ' . print_r($result, true));
        wp_send_json_error([
            'error' => $result['error'] ?: __('Erro desconhecido', 'hng-commerce')
        ]);
        return;
    }
    
    // Build message
    $message = '';
    if (!empty($result['message'])) {
        $message = $result['message'];
    } else {
        $message = sprintf(
            /* translators: 1: payments processed, 2: created, 3: updated, 4: start, 5: end */
            __('Sincronização concluída: %1$d pagamentos (%2$d criados, %3$d atualizados) de %4$s até %5$s.', 'hng-commerce'),
            $result['processed'],
            $result['created'],
            $result['updated'],
            $result['start_date'] ?? '-',
            $result['end_date'] ?? __('hoje', 'hng-commerce')
        );
    }
    
    // Add warning if no payments were found
    if (0 === $result['processed'] && empty($result['message'])) {
        $message = sprintf(
            /* translators: %d: number of days */
            __('Nenhum pagamento encontrado nos últimos %d dias. Verifique se há pedidos PagSeguro nesse período.', 'hng-commerce'),
            $result['days']
        );
    }
    
    wp_send_json_success([
        'processed' => $result['processed'],
        'created' => $result['created'],
        'updated' => $result['updated'],
        'days' => $result['days'],
        'start_date' => $result['start_date'] ?? '',
        'end_date' => $result['end_date'] ?? '',
        'message' => $message
    ]);
}

/**
 * AJAX: Sincronizar clientes do PagSeguro
 */
add_action('wp_ajax_hng_pagseguro_sync_customers', 'hng_ajax_sync_pagseguro_customers');
function hng_ajax_sync_pagseguro_customers() {
    // Validate AJAX nonce (supports two actions)
    $nonce_valid = wp_verify_nonce($_POST['nonce'] ?? '', 'hng_pagseguro_sync_nonce')
                || wp_verify_nonce($_POST['nonce'] ?? '', 'hng-commerce-admin');
    
    if (!$nonce_valid) {
        wp_send_json_error(['error' => __('Sessão expirada. Recarregue a página.', 'hng-commerce')], 403);
    }

    if (class_exists('HNG_Rate_Limiter')) {
        $rl = HNG_Rate_Limiter::enforce('pagseguro_sync_customers', 3, 120);
        if (is_wp_error($rl)) {
            wp_send_json_error(['error' => $rl->get_error_message()], 429);
        }
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['error' => __('Permissão negada.', 'hng-commerce')]);
        return;
    }
    
    // Capture and validate optional date parameters
    $start_date = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';
    $end_date   = isset($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : '';
    
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
        $start_date = '';
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
        $end_date = '';
    }
    
    // Check if advanced integration is enabled
    $advanced_enabled = get_option('hng_pagseguro_advanced_integration', 'no') === 'yes';
    if (!$advanced_enabled) {
        wp_send_json_error(['error' => __('Integração avançada não está habilitada.', 'hng-commerce')]);
        return;
    }
    
    // Load sync class
    $sync_file = HNG_COMMERCE_PATH . 'includes/integrations/class-hng-pagseguro-sync.php';
    if (!file_exists($sync_file)) {
        wp_send_json_error(['error' => __('Arquivo de sincronização não encontrado.', 'hng-commerce')]);
        return;
    }
    require_once $sync_file;
    
    $sync = new HNG_PagSeguro_Sync();
    
    if (!$sync->is_configured()) {
        wp_send_json_error(['error' => __('Token do PagBank não configurado.', 'hng-commerce')]);
        return;
    }
    
    $result = $sync->import_customers($start_date, $end_date);
    
    if (!$result['success']) {
        error_log('HNG PagSeguro sync customers error: ' . print_r($result, true));
        wp_send_json_error([
            'error' => $result['error'] ?: __('Erro desconhecido', 'hng-commerce')
        ]);
        return;
    }
    
    // Build message with source information
    $source_label = '';
    if (isset($result['source'])) {
        if ('local_orders' === $result['source']) {
            $source_label = ' ' . __('(via pedidos locais)', 'hng-commerce');
        } elseif ('pagbank_api' === $result['source']) {
            $source_label = ' ' . __('(via API PagBank)', 'hng-commerce');
        }
    }
    
    $message = sprintf(
        /* translators: 1: clients processed, 2: created, 3: updated */
        __('Sincronização concluída: %1$d clientes processados (%2$d criados, %3$d atualizados)%4$s', 'hng-commerce'),
        $result['processed'],
        $result['created'],
        $result['updated'],
        $source_label
    );
    
    // Add note if present
    if (!empty($result['note'])) {
        $message .= '. ' . $result['note'];
    }
    
    // Add warning if no customers were found
    if (0 === $result['processed']) {
        $message = __('Nenhum cliente encontrado para sincronizar. Verifique se há clientes cadastrados no PagBank ou pedidos locais com dados de clientes.', 'hng-commerce');
    }
    
    wp_send_json_success([
        'processed' => $result['processed'],
        'created' => $result['created'],
        'updated' => $result['updated'],
        'source' => $result['source'] ?? 'unknown',
        'message' => $message
    ]);
}

/**
 * AJAX: Obter estatísticas de sincronização do PagSeguro
 */
add_action('wp_ajax_hng_pagseguro_get_sync_stats', 'hng_ajax_get_pagseguro_sync_stats');
function hng_ajax_get_pagseguro_sync_stats() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['error' => __('Permissão negada.', 'hng-commerce')]);
        return;
    }
    
    // Load sync class
    $sync_file = HNG_COMMERCE_PATH . 'includes/integrations/class-hng-pagseguro-sync.php';
    if (!file_exists($sync_file)) {
        wp_send_json_error(['error' => __('Arquivo de sincronização não encontrado.', 'hng-commerce')]);
        return;
    }
    require_once $sync_file;
    
    $sync = new HNG_PagSeguro_Sync();
    $stats = $sync->get_sync_stats();
    
    wp_send_json_success($stats);
}

/**
 * AJAX: Listar assinaturas sincronizadas do PagSeguro
 */
add_action('wp_ajax_hng_pagseguro_list_subscriptions', 'hng_ajax_list_pagseguro_subscriptions');
function hng_ajax_list_pagseguro_subscriptions() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['error' => __('Permissão negada.', 'hng-commerce')]);
        return;
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'hng_subscriptions';
    
    // Check if table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
        wp_send_json_success(['subscriptions' => [], 'total' => 0]);
        return;
    }
    
    $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
    $per_page = 20;
    $offset = ($page - 1) * $per_page;
    
    // Get total count
    $total = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM $table WHERE gateway = 'pagseguro'"
    );
    
    // Get subscriptions
    $subscriptions = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE gateway = 'pagseguro' ORDER BY created_at DESC LIMIT %d OFFSET %d",
        $per_page,
        $offset
    ), ARRAY_A);
    
    // Enrich with customer data
    $customers_table = $wpdb->prefix . 'hng_customers';
    foreach ($subscriptions as &$sub) {
        if (!empty($sub['customer_id'])) {
            $customer = $wpdb->get_row($wpdb->prepare(
                "SELECT name, email FROM $customers_table WHERE id = %d",
                $sub['customer_id']
            ), ARRAY_A);
            $sub['customer'] = $customer ?: null;
        }
    }
    
    wp_send_json_success([
        'subscriptions' => $subscriptions,
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => ceil($total / $per_page)
    ]);
}

/**
 * AJAX: Listar clientes sincronizados do PagSeguro
 */
add_action('wp_ajax_hng_pagseguro_list_customers', 'hng_ajax_list_pagseguro_customers');
function hng_ajax_list_pagseguro_customers() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['error' => __('Permissão negada.', 'hng-commerce')]);
        return;
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'hng_customers';
    
    // Check if table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
        wp_send_json_success(['customers' => [], 'total' => 0]);
        return;
    }
    
    $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
    $per_page = 20;
    $offset = ($page - 1) * $per_page;
    
    // Get total count
    $total = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM $table WHERE source = 'pagseguro'"
    );
    
    // Get customers
    $customers = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE source = 'pagseguro' ORDER BY created_at DESC LIMIT %d OFFSET %d",
        $per_page,
        $offset
    ), ARRAY_A);
    
    // Enrich with WordPress user data
    foreach ($customers as &$customer) {
        if (!empty($customer['user_id'])) {
            $user = get_userdata($customer['user_id']);
            if ($user) {
                $customer['wp_user'] = [
                    'ID' => $user->ID,
                    'display_name' => $user->display_name,
                    'user_login' => $user->user_login,
                ];
            }
        }
    }
    
    wp_send_json_success([
        'customers' => $customers,
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => ceil($total / $per_page)
    ]);
}

/**
 * AJAX: Vincular cliente PagSeguro a usuário WordPress
 */
add_action('wp_ajax_hng_pagseguro_link_customer_to_user', 'hng_ajax_link_pagseguro_customer_to_user');
function hng_ajax_link_pagseguro_customer_to_user() {
    // Validate AJAX nonce (supports two actions)
    $nonce_valid = wp_verify_nonce($_POST['nonce'] ?? '', 'hng_pagseguro_sync_nonce')
                || wp_verify_nonce($_POST['nonce'] ?? '', 'hng-commerce-admin');
    
    if (!$nonce_valid) {
        wp_send_json_error(['error' => __('Sessão expirada.', 'hng-commerce')], 403);
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['error' => __('Permissão negada.', 'hng-commerce')]);
        return;
    }
    
    $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    
    if (!$customer_id || !$user_id) {
        wp_send_json_error(['error' => __('Dados inválidos.', 'hng-commerce')]);
        return;
    }
    
    // Verify user exists
    $user = get_userdata($user_id);
    if (!$user) {
        wp_send_json_error(['error' => __('Usuário não encontrado.', 'hng-commerce')]);
        return;
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'hng_customers';
    
    // Update customer
    $updated = $wpdb->update(
        $table,
        ['user_id' => $user_id, 'updated_at' => current_time('mysql')],
        ['id' => $customer_id]
    );
    
    if ($updated === false) {
        wp_send_json_error(['error' => __('Erro ao atualizar cliente.', 'hng-commerce')]);
        return;
    }
    
    // Save reverse link in user meta
    update_user_meta($user_id, '_hng_customer_id', $customer_id);
    
    wp_send_json_success([
        'message' => sprintf(
            /* translators: %s: user display name */
            __('Cliente vinculado ao usuário %s com sucesso.', 'hng-commerce'),
            $user->display_name
        )
    ]);
}
