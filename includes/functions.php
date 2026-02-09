<?php
/**
 * Funções Auxiliares Globais
 *
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Obter instância do carrinho
 */
function hng_cart() {
    return HNG_Cart::instance();
}

/**
 * Obter produto por ID
 */
function hng_get_product($product_id) {
    return new HNG_Product($product_id);
}


/**
 * Obter preço formatado
 */
function hng_price($price) {
    // Garantir que price não é null
    if ($price === null || $price === '') {
        $price = 0;
    }
    
    $currency = get_option('hng_currency', 'BRL');
    $position = get_option('hng_currency_position', 'left_space');
    $thousand_sep = get_option('hng_thousand_separator', '.');
    $decimal_sep = get_option('hng_decimal_separator', ',');
    $decimals = (int) get_option('hng_number_decimals', 2);
    
    $formatted = number_format((float)$price, $decimals, $decimal_sep, $thousand_sep);
    
    $symbol = 'R$';
    
    switch ($position) {
        case 'left':
            return $symbol . $formatted;
        case 'right':
            return $formatted . $symbol;
        case 'left_space':
            return $symbol . ' ' . $formatted;
        case 'right_space':
            return $formatted . ' ' . $symbol;
        default:
            return $symbol . ' ' . $formatted;
    }
}

/**
 * Verificar se usuário pode comprar
 */
function hng_customer_can_purchase() {
    return is_user_logged_in() || get_option('hng_enable_guest_checkout', 'yes') === 'yes';
}

/**
 * Obter URL da página
 */
function hng_get_page_url($page) {
    $page_id = get_option('hng_page_' . $page, 0);
    
    if (!empty($page_id) && $page_id > 0) {
        $permalink = get_permalink($page_id);
        if ($permalink) {
            return $permalink;
        }
    }
    
    return home_url('/' . sanitize_title($page));
}

/**
 * URL da loja
 */
function hng_get_shop_url() {
    return hng_get_page_url('loja');
}

/**
 * URL do carrinho
 */
function hng_get_cart_url() {
    return hng_get_page_url('carrinho');
}

/**
 * URL do checkout
 */
function hng_get_checkout_url() {
    return hng_get_page_url('checkout');
}

/**
 * Obter URL da conta
 */
function hng_get_account_url() {
    return hng_get_page_url('minha-conta');
}

/**
 * Verificar se é página do HNG Commerce
 */
function is_hng_page() {
    return is_hng_shop() || is_hng_cart() || is_hng_checkout() || is_hng_account();
}

/**
 * É página da loja?
 */
function is_hng_shop() {
    return is_post_type_archive('hng_product') || is_tax(['hng_product_cat', 'hng_product_tag']);
}

/**
 * É página de produto?
 */
function is_hng_product() {
    return is_singular('hng_product');
}

/**
 * É página do carrinho?
 */
function is_hng_cart() {
    $page_id = get_option('hng_page_carrinho', 0);
    if (empty($page_id)) {
        return false;
    }
    return is_page($page_id);
}

/**
 * É página de checkout?
 */
function is_hng_checkout() {
    $page_id = get_option('hng_page_checkout', 0);
    if (empty($page_id)) {
        return false;
    }
    return is_page($page_id);
}

/**
 * É página da conta?
 */
function is_hng_account() {
    $page_id = get_option('hng_page_minha-conta', 0);
    if (empty($page_id)) {
        return false;
    }
    return is_page($page_id);
}

/**
 * Adicionar notice
 */
function hng_add_notice($message, $type = 'success') {
    if (!session_id()) {
        session_start();
    }
    
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Internal notices array, sanitized on output
    if (!isset($_SESSION['hng_notices'])) {
        $_SESSION['hng_notices'] = [];
    }
    
    $_SESSION['hng_notices'][] = [
        'message' => $message,
        'type' => $type,
    ];
}

/**
 * Obter notices
 */
function hng_get_notices() {
    if (!session_id()) {
        session_start();
    }
    
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Internal notices array, sanitized on output
    $notices = $_SESSION['hng_notices'] ?? [];
    unset($_SESSION['hng_notices']);
    
    return $notices;
}

/**
 * Exibir notices
 */
function hng_print_notices() {
    $notices = hng_get_notices();
    
    if (empty($notices)) {
        return;
    }
    
    foreach ($notices as $notice) {
        $class = 'hng-notice hng-notice-' . esc_attr($notice['type']);
        echo '<div class="' . esc_attr($class) . '">' . esc_html($notice['message']) . '</div>';
    }
}

/**
 * Sanitizar input
 */
function hng_clean($input, $type = 'text') {
    return apply_filters('hng_sanitize_input', $input, $type);
}

/**
 * Verificar nonce
 */
function hng_verify_nonce($nonce, $action) {
    $clean_nonce = is_string($nonce) ? sanitize_text_field($nonce) : '';
    return wp_verify_nonce($clean_nonce, $action);
}

/**
 * Log de debug
 */
function hng_log($message, $level = 'info') {
    if (function_exists('hng_files_log_append')) {
        hng_files_log_append(HNG_COMMERCE_PATH . 'logs/hng.log', '[HNG Commerce ' . strtoupper($level) . '] ' . $message . PHP_EOL);
    }

    do_action('hng_log', $message, $level);
}

/**
 * Métodos de pagamento habilitados (validados por opção de configuração)
 */
function hng_get_enabled_payment_methods() {
    $methods = get_option('hng_enabled_payment_methods', ['pix','boleto','credit_card']);
    if (!is_array($methods)) { return ['pix','boleto','credit_card']; }
    // Normalizar e filtrar apenas suportados atualmente
    $supported = ['pix','boleto','credit_card'];
    return array_values(array_intersect($supported, array_map('sanitize_key', $methods)));
}

/**
 * Obter título amigável do método de pagamento
 */
function hng_get_payment_method_title($method) {
    switch ($method) {
        case 'pix': return __('PIX', 'hng-commerce');
        case 'boleto': return __('Boleto Bancário', 'hng-commerce');
        case 'credit_card': return __('Cartão de Crédito', 'hng-commerce');
        default: return ucfirst(str_replace('_',' ', $method));
    }
}

/**
 * Mï¿½todos habilitados restringidos ao gateway ativo (quando disponï¿½vel)
 */
function hng_get_active_gateway_methods() {
    // Verificar se está usando gateways HNG
    if (class_exists('HNG_Setup_Wizard')) {
        $use_hng_gateways = HNG_Setup_Wizard::is_using_hng_gateways();
        // Se não estiver usando gateways HNG (WordPress.org compliance)
        // Retornar array vazio para desabilitar todos os métodos HNG
        if (!$use_hng_gateways) {
            return [];
        }
    }
    
    if (class_exists('HNG_Payment_Settings')) {
        $settings = HNG_Payment_Settings::get_instance();
        if (method_exists($settings, 'get_active_gateway_methods')) {
            return $settings->get_active_gateway_methods();
        }
    }
    return hng_get_enabled_payment_methods();
}

/**
 * Verificar se estï¿½ em modo de desenvolvimento
 */
function hng_is_dev_mode() {
    return defined('HNG_COMMERCE_DEV_MODE') && HNG_COMMERCE_DEV_MODE;
}

/**
 * Validar CEP brasileiro
 */
function hng_validate_postcode($postcode) {
    $postcode = preg_replace('/[^0-9]/', '', $postcode);
    return strlen($postcode) === 8;
}

/**
 * Validar CPF
 */
function hng_validate_cpf($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    
    if (strlen($cpf) != 11) {
        return false;
    }
    
    // Verificar se todos os dï¿½gitos sï¿½o iguais
    if (preg_match('/(\d)\1{10}/', $cpf)) {
        return false;
    }
    
    // Validar dï¿½gitos verificadores
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) {
            return false;
        }
    }
    
    return true;
}

/**
 * Validar CNPJ
 */
function hng_validate_cnpj($cnpj) {
    $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
    
    if (strlen($cnpj) != 14) {
        return false;
    }
    
    // Verificar se todos os dï¿½gitos sï¿½o iguais
    if (preg_match('/(\d)\1{13}/', $cnpj)) {
        return false;
    }
    
    // Validar dï¿½gitos verificadores
    $b = array(6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2);
    
    for ($i = 0, $n = 0; $i < 12; $n += $cnpj[$i] * $b[++$i]);
    
    if ($cnpj[12] != ((($n %= 11) < 2) ? 0 : 11 - $n)) {
        return false;
    }
    
    for ($i = 0, $n = 0; $i <= 12; $n += $cnpj[$i] * $b[$i++]);
    
    if ($cnpj[13] != ((($n %= 11) < 2) ? 0 : 11 - $n)) {
        return false;
    }
    
    return true;
}

/**
 * Sanitizar telefone
 */
function hng_sanitize_phone($phone) {
    return preg_replace('/[^0-9]/', '', $phone);
}

/**
 * Formatar telefone
 */
function hng_format_phone($phone) {
    $phone = hng_sanitize_phone($phone);
    $len = strlen($phone);
    
    if ($len === 11) {
        return '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 5) . '-' . substr($phone, 7);
    } elseif ($len === 10) {
        return '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 4) . '-' . substr($phone, 6);
    }
    
    return $phone;
}

/**
 * Obter estados brasileiros
 */
function hng_get_brazilian_states() {
    return array(
        'AC' => 'Acre',
        'AL' => 'Alagoas',
        'AP' => 'Amapá',
        'AM' => 'Amazonas',
        'BA' => 'Bahia',
        'CE' => 'Ceará',
        'DF' => 'Distrito Federal',
        'ES' => 'Espírito Santo',
        'GO' => 'Goiás',
        'MA' => 'Maranhão',
        'MT' => 'Mato Grosso',
        'MS' => 'Mato Grosso do Sul',
        'MG' => 'Minas Gerais',
        'PA' => 'Pará',
        'PB' => 'Paraíba',
        'PR' => 'Paraná',
        'PE' => 'Pernambuco',
        'PI' => 'Piauí',
        'RJ' => 'Rio de Janeiro',
        'RN' => 'Rio Grande do Norte',
        'RS' => 'Rio Grande do Sul',
        'RO' => 'Rondônia',
        'RR' => 'Roraima',
        'SC' => 'Santa Catarina',
        'SP' => 'São Paulo',
        'SE' => 'Sergipe',
        'TO' => 'Tocantins'
    );
}

/**
 * Obter URL da página minha conta
 */
function hng_get_myaccount_url() {
    return hng_get_page_url('minha-conta');
}

/**
 * Obter pedidos do cliente
 * 
 * @param int $customer_id ID do cliente
 * @param int $limit Limite de resultados
 * @return array Lista de pedidos
 */
function hng_get_customer_orders($customer_id, $limit = -1) {
    global $wpdb;
    $table = $wpdb->prefix . 'hng_orders';
    
    $sql = $wpdb->prepare(
        "SELECT * FROM {$table} WHERE customer_id = %d ORDER BY created_at DESC",
        $customer_id
    );
    
    if ($limit > 0) {
        $sql .= $wpdb->prepare(" LIMIT %d", $limit);
    }
    
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is safely prefixed with $wpdb->prefix
    return $wpdb->get_results($sql);
}

/**
 * Obter label do status do pedido
 * 
 * @param string $status Status do pedido
 * @return string Label traduzida
 */
function hng_get_order_status_label($status) {
    $statuses = array(
        'pending' => __('Pendente', 'hng-commerce'),
        'pending-payment' => __('Aguardando Pagamento', 'hng-commerce'),
        'processing' => __('Processando', 'hng-commerce'),
        'on-hold' => __('Em espera', 'hng-commerce'),
        'completed' => __('Concluído', 'hng-commerce'),
        'cancelled' => __('Cancelado', 'hng-commerce'),
        'refunded' => __('Reembolsado', 'hng-commerce'),
        'failed' => __('Falhou', 'hng-commerce'),
        'shipped' => __('Enviado', 'hng-commerce'),
        'delivered' => __('Entregue', 'hng-commerce'),
    );
    
    return isset($statuses[$status]) ? $statuses[$status] : ucfirst(str_replace('-', ' ', $status));
}

/**
 * Obter todas as assinaturas do cliente
 * 
 * @param int $customer_id ID do cliente
 * @return array Lista de assinaturas
 */
function hng_get_customer_subscriptions($customer_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'hng_subscriptions';
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} WHERE customer_id = %d ORDER BY created_at DESC",
        $customer_id
    ));
}

/**
 * Obter downloads disponíveis do cliente
 * 
 * @param int $customer_id ID do cliente
 * @return array Lista de downloads
 */
function hng_get_customer_downloads($customer_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'hng_downloads';
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT d.*, o.order_number 
         FROM {$table} d 
         LEFT JOIN {$wpdb->prefix}hng_orders o ON d.order_id = o.id 
         WHERE d.customer_id = %d 
         AND d.downloads_remaining != 0 
         AND (d.expires_at IS NULL OR d.expires_at > NOW())
         ORDER BY d.created_at DESC",
        $customer_id
    ));
}

/**
 * Obter agendamentos do cliente
 * 
 * @param int $customer_id ID do cliente
 * @return array Lista de agendamentos
 */
function hng_get_customer_appointments($customer_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'hng_appointments';
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} WHERE customer_id = %d ORDER BY appointment_date DESC",
        $customer_id
    ));
}
/**
 * Obter instância das configurações admin
 */
function hng_admin_settings() {
    if (class_exists('HNG_Admin_Settings')) {
        return HNG_Admin_Settings::instance();
    }
    return null;
}