<?php
/**
 * HNG Commerce: AJAX Handlers para Sincronização Asaas
 * 
 * Handlers AJAX para sincronização de dados do Asaas
 *
 * @package HNG_Commerce
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**

 * AJAX: Sincronizar assinaturas do Asaas

 */

add_action('wp_ajax_hng_asaas_sync_subscriptions', 'hng_ajax_sync_asaas_subscriptions');

function hng_ajax_sync_asaas_subscriptions() {

    check_ajax_referer('hng_asaas_sync_nonce', 'nonce');



    if (class_exists('HNG_Rate_Limiter')) {

        $rl = HNG_Rate_Limiter::enforce('asaas_sync_subscriptions', 3, 120);

        if (is_wp_error($rl)) {

            wp_send_json_error(['error' => $rl->get_error_message()], 429);

        }

    }

    

    if (!current_user_can('manage_options')) {

        wp_send_json_error(['error' => 'Permissão negada']);

        return;

    }

    

    // Capturar e validar datas opcionais

    $start_date = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';

    $end_date   = isset($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : '';

    

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {

        $start_date = '';

    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {

        $end_date = '';

    }

    

    // Atualizar timestamp de última sincronização

    update_option('hng_asaas_last_sync_subscriptions', current_time('mysql'));

    

    // Sincronizar assinaturas do Asaas

    $sync_file = HNG_COMMERCE_PATH . 'includes/integrations/class-hng-asaas-sync.php';

    if (!file_exists($sync_file)) {

        wp_send_json_error(['error' => 'Arquivo de sincronização não encontrado']);

        return;

    }

    require_once $sync_file;

    

    $asaas_sync = new HNG_Asaas_Sync();

    $result = $asaas_sync->import_subscriptions(null, $start_date, $end_date);

    

    if (!$result['success']) {

        if (defined('HNG_DEBUG') && HNG_DEBUG === true) {
            error_log('HNG Asaas sync error: ' . wp_json_encode([
                'type' => 'subscriptions',
                'error' => is_wp_error($result) ? $result->get_error_message() : 'Unknown',
                'code' => is_wp_error($result) ? $result->get_error_code() : 0
            ]));
        }

        wp_send_json_error([

            'error' => $result['error'] ?: 'unknown_error'

        ]);

        return;

    }

    

    wp_send_json_success([

        'processed' => $result['processed'],

        'created' => $result['created'],

        'updated' => $result['updated']

    ]);

}



/**

 * AJAX: Sincronizar pagamentos/faturamento do Asaas

 */

add_action('wp_ajax_hng_asaas_sync_payments', 'hng_ajax_sync_asaas_payments');

function hng_ajax_sync_asaas_payments() {

    check_ajax_referer('hng_asaas_sync_nonce', 'nonce');



    if (class_exists('HNG_Rate_Limiter')) {

        $rl = HNG_Rate_Limiter::enforce('asaas_sync_payments', 3, 120);

        if (is_wp_error($rl)) {

            wp_send_json_error(['error' => $rl->get_error_message()], 429);

        }

    }

    

    if (!current_user_can('manage_options')) {

        wp_send_json_error(['error' => 'Permissão negada']);

        return;

    }

    

    // Get date range or days fallback

    $days = isset($_POST['days']) ? intval($_POST['days']) : 30;

    if ($days < 1) $days = 30;

    if ($days > 365) $days = 365; // Max 1 year

    $start_date = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';

    $end_date   = isset($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : '';

    // Basic YYYY-MM-DD validation
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {

        $start_date = '';

    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {

        $end_date = '';

    }

    

    // Atualizar timestamp de última sincronização

    update_option('hng_asaas_last_sync_payments', current_time('mysql'));

    

    // Sincronizar pagamentos do Asaas

    $sync_file = HNG_COMMERCE_PATH . 'includes/integrations/class-hng-asaas-sync.php';

    if (!file_exists($sync_file)) {

        wp_send_json_error(['error' => 'Arquivo de sincronização não encontrado']);

        return;

    }

    require_once $sync_file;

    

    $asaas_sync = new HNG_Asaas_Sync();

    $result = $asaas_sync->import_payments($days, $start_date, $end_date);

    

    if (!$result['success']) {

        if (defined('HNG_DEBUG') && HNG_DEBUG === true) {
            error_log('HNG Asaas sync error: ' . wp_json_encode([
                'type' => 'payments',
                'error' => is_wp_error($result) ? $result->get_error_message() : 'Unknown',
                'code' => is_wp_error($result) ? $result->get_error_code() : 0
            ]));
        }

        wp_send_json_error([

            'error' => $result['error'] ?: 'unknown_error'

        ]);

        return;

    }

    

    wp_send_json_success([

        'processed' => $result['processed'],

        'created' => $result['created'],

        'updated' => $result['updated'],

        'days' => $result['days'],

        'start_date' => $result['start_date'] ?? '',

        'end_date' => $result['end_date'] ?? ''

    ]);

}

/**

 * AJAX: Sincronizar clientes do Asaas

 */

add_action('wp_ajax_hng_asaas_sync_customers', 'hng_ajax_sync_asaas_customers');

function hng_ajax_sync_asaas_customers() {

    check_ajax_referer('hng_asaas_sync_nonce', 'nonce');



    if (class_exists('HNG_Rate_Limiter')) {

        $rl = HNG_Rate_Limiter::enforce('asaas_sync_customers', 3, 120);

        if (is_wp_error($rl)) {

            wp_send_json_error(['error' => $rl->get_error_message()], 429);

        }

    }

    

    if (!current_user_can('manage_options')) {

        wp_send_json_error(['error' => 'Permissão negada']);

        return;

    }

    

    // Capturar e validar datas opcionais

    $start_date = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';

    $end_date   = isset($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : '';

    

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {

        $start_date = '';

    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {

        $end_date = '';

    }

    

    // Atualizar timestamp de última sincronização

    update_option('hng_asaas_last_sync_customers', current_time('mysql'));

    

    // Sincronizar clientes do Asaas

    $sync_file = HNG_COMMERCE_PATH . 'includes/integrations/class-hng-asaas-sync.php';

    if (!file_exists($sync_file)) {

        wp_send_json_error(['error' => 'Arquivo de sincronização não encontrado']);

        return;

    }

    require_once $sync_file;

    

    $asaas_sync = new HNG_Asaas_Sync();

    $result = $asaas_sync->import_customers($start_date, $end_date);

    

    if (!$result['success']) {

        if (defined('HNG_DEBUG') && HNG_DEBUG === true) {
            error_log('HNG Asaas sync error: ' . wp_json_encode([
                'type' => 'customers',
                'error' => is_wp_error($result) ? $result->get_error_message() : 'Unknown',
                'code' => is_wp_error($result) ? $result->get_error_code() : 0
            ]));
        }

        wp_send_json_error([

            'error' => $result['error'] ?: 'unknown_error'

        ]);

        return;

    }

    

    wp_send_json_success([

        'processed' => $result['processed'],

        'created' => $result['created'],

        'updated' => $result['updated']

    ]);

}

