<?php
/**
 * HNG Commerce - Ajax Customers Management
 *
 * Handlers AJAX para:
 * - Obter detalhes do cliente
 * - Relacionar cliente com usuário WordPress
 * - Desunir cliente de usuário
 * - Gerar link de registro para cliente
 *
 * @package HNG_Commerce
 * @subpackage Admin/Ajax
 * @since 1.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX: Get customer details
 */
function hng_ajax_get_customer_details() {
    // Verify nonce
    $nonce_check = wp_verify_nonce($_POST['nonce'] ?? '', 'hng-commerce-admin');
    
    if (!$nonce_check) {
        wp_send_json_error(['message' => __('Erro de segurança. Recarregue a página e tente novamente.', 'hng-commerce')], 403);
    }
    
    // Check capability
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Permissão negada.', 'hng-commerce')], 403);
    }
    
    // Get email
    // phpcs:ignore WordPress.Security.ValidatedInput.InputNotSanitized -- Email will be validated with is_email()
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    
    if (empty($email) || !is_email($email)) {
        wp_send_json_error(['message' => __('E-mail inválido.', 'hng-commerce')]);
    }
    
    global $wpdb;
    
    // 1. Check if customer has account
    $user = get_user_by('email', $email);
    
    if (!$user) {
        // No account - show message and option to create account
        wp_send_json_success([
            'has_account' => false,
            'email' => $email,
            'message' => __('Este cliente não possui conta WordPress. Você pode criar uma conta para ele ou enviar um link de registro.', 'hng-commerce')
        ]);
    }
    
    // 2. Get customer data from hng_customers table
    $customers_table = $wpdb->prefix . 'hng_customers';
    $customer = null;
    
    // Check if customers table exists
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SHOW TABLES doesn't support prepared statements
    if ($wpdb->get_var("SHOW TABLES LIKE '{$customers_table}'") === $customers_table) {
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$customers_table} WHERE email = %s LIMIT 1",
            $email
        ));
    }
    
    // If no customer record, try to get from orders
    if (!$customer) {
        $orders_table = $wpdb->prefix . 'hng_orders';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT customer_email, customer_name FROM {$orders_table} WHERE customer_email = %s LIMIT 1",
            $email
        ));
        
        if (!$order) {
            wp_send_json_error(['message' => __('Cliente não encontrado.', 'hng-commerce')]);
        }
        
        // Minimal customer data from order
        $customer = (object)[
            'id' => null,
            'first_name' => '',
            'last_name' => '',
            'email' => $order->customer_email,
            'name' => $order->customer_name,
            'phone' => '',
            'whatsapp' => '',
            'type' => 'supplier',
            'company_name' => '',
            'cnpj' => '',
            'area' => '',
            'social_networks' => '',
            'services_provided' => '',
            'service_needed' => '',
            'other_service' => ''
        ];
    }
    
    $user_roles = is_array($user->roles) ? $user->roles : [];
    $type = isset($customer->type) && $customer->type !== '' ? $customer->type : 'supplier';
    $debug_info = array(
        'customer_has_wp_user_id' => property_exists($customer, 'wp_user_id'),
        'customer_wp_user_id' => property_exists($customer, 'wp_user_id') ? $customer->wp_user_id : null,
        'user_id' => $user->ID,
        'user_roles' => $user_roles,
        'customer_type_from_db' => $type
    );

    // Se o usuário tem papel de cliente, força o tipo para cliente
    if (in_array('hng_customer', $user_roles, true) || in_array('customer', $user_roles, true)) {
        $type = 'customer';
    }

    // Se o registro de cliente está vinculado a este usuário, também força cliente
    if ($customer && property_exists($customer, 'wp_user_id') && intval($customer->wp_user_id) === intval($user->ID)) {
        $type = 'customer';
    }

    // Normaliza possíveis aliases
    if ($type === 'provider') {
        $type = 'supplier';
    }

    $type_label = $type === 'company'
        ? __('Empresa', 'hng-commerce')
        : ($type === 'customer'
            ? __('Cliente HNG', 'hng-commerce')
            : __('Prestador de Serviços', 'hng-commerce'));

    // 3. Build response
    $response = [
        'has_account' => true,
        'user_id' => $user->ID,
        'username' => $user->user_login,
        'email' => $user->user_email,
        'registered_at' => mysql2date(__('d/m/Y H:i', 'hng-commerce'), $user->user_registered),
        'type' => $type,
        'type_label' => $type_label,
        'debug' => $debug_info,
        'name' => isset($customer->name) ? $customer->name : trim($user->first_name . ' ' . $user->last_name),
        'company_name' => isset($customer->company_name) ? $customer->company_name : '',
        'cnpj' => isset($customer->cnpj) ? $customer->cnpj : '',
        'phone' => isset($customer->phone) ? $customer->phone : '',
        'whatsapp' => isset($customer->whatsapp) ? $customer->whatsapp : '',
        'area' => isset($customer->area) ? $customer->area : '',
        'social_networks' => isset($customer->social_networks) ? $customer->social_networks : '',
        'services_provided' => isset($customer->services_provided) ? $customer->services_provided : '',
        'service_needed' => isset($customer->service_needed) ? $customer->service_needed : '',
        'service_needed_name' => isset($customer->service_needed_name) ? $customer->service_needed_name : '',
        'other_service' => isset($customer->other_service) ? $customer->other_service : ''
    ];
    
    wp_send_json_success($response);
}
add_action('wp_ajax_hng_get_customer_details', 'hng_ajax_get_customer_details');
add_action('wp_ajax_nopriv_hng_get_customer_details', 'hng_ajax_get_customer_details');

/**
 * AJAX: Link customer to WordPress user
 */
function hng_ajax_link_customer_to_user() {
    // Verify nonce   
    $nonce_check = wp_verify_nonce($_POST['nonce'] ?? '', 'hng-commerce-admin');
    
    if (!$nonce_check) {
        wp_send_json_error(['message' => __('Erro de segurança. Recarregue a página e tente novamente.', 'hng-commerce')], 403);
    }
    
    // Check capability
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Permissão negada.', 'hng-commerce')], 403);
    }
    
    // Get parameters
    // phpcs:ignore WordPress.Security.ValidatedInput.InputNotSanitized -- Email will be validated with is_email()
    $customer_email = isset($_POST['customer_email']) ? sanitize_email($_POST['customer_email']) : '';
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    
    if (empty($customer_email) || !is_email($customer_email)) {
        wp_send_json_error(['message' => __('E-mail do cliente inválido.', 'hng-commerce')]);
    }
    
    if (empty($user_id)) {
        wp_send_json_error(['message' => __('ID do usuário inválido.', 'hng-commerce')]);
    }
    
    // Verify user exists
    $user = get_user_by('id', $user_id);
    if (!$user) {
        wp_send_json_error(['message' => __('Usuário não encontrado.', 'hng-commerce')]);
    }
    
    global $wpdb;
    
    // 1. Get or create customer record
    $customers_table = $wpdb->prefix . 'hng_customers';
    
    // Check if table exists
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SHOW TABLES doesn't support prepared statements
    if ($wpdb->get_var("SHOW TABLES LIKE '{$customers_table}'") !== $customers_table) {
        wp_send_json_error(['message' => __('Tabela de clientes não encontrada. Execute as migrações do plugin.', 'hng-commerce')]);
    }
    
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe
    $customer = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$customers_table} WHERE email = %s LIMIT 1",
        $customer_email
    ));
    
    if (!$customer) {
        // Create customer record
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe
        $insert_result = $wpdb->insert(
            $customers_table,
            [
                'email' => $customer_email,
                'name' => $user->display_name,
                'wp_user_id' => $user_id,
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%d', '%s']
        );
        
        if (!$insert_result) {
            wp_send_json_error(['message' => __('Erro ao criar registro de cliente.', 'hng-commerce')]);
        }
        
        $customer_id = $wpdb->insert_id;
    } else {
        // Update existing customer record
        $customer_id = $customer->id;
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe
        $wpdb->update(
            $customers_table,
            ['wp_user_id' => $user_id],
            ['id' => $customer_id],
            ['%d'],
            ['%d']
        );
    }
    
    // 2. Store metadata in user meta
    update_user_meta($user_id, 'hng_customer_email', $customer_email);
    update_user_meta($user_id, 'hng_customer_id', $customer_id);
    
    wp_send_json_success([
        'message' => sprintf(
            /* translators: %1$s = customer email, %2$s = user display name */
            __('Cliente %1$s vinculado com sucesso ao usuário %2$s.', 'hng-commerce'),
            $customer_email,
            $user->display_name
        ),
        'customer_id' => $customer_id,
        'user_id' => $user_id
    ]);
}
add_action('wp_ajax_hng_link_customer_to_user', 'hng_ajax_link_customer_to_user');

/**
 * AJAX: Unlink customer from WordPress user
 */
function hng_ajax_unlink_customer_from_user() {
    // Verify nonce
    $nonce_check = wp_verify_nonce($_POST['nonce'] ?? '', 'hng-commerce-admin');
    
    if (!$nonce_check) {
        wp_send_json_error(['message' => __('Erro de segurança. Recarregue a página e tente novamente.', 'hng-commerce')], 403);
    }
    
    // Check capability
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Permissão negada.', 'hng-commerce')], 403);
    }
    
    // Get parameters
    $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
    
    if (empty($customer_id)) {
        wp_send_json_error(['message' => __('ID do cliente inválido.', 'hng-commerce')]);
    }
    
    global $wpdb;
    
    $customers_table = $wpdb->prefix . 'hng_customers';
    
    // Check if table exists
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SHOW TABLES doesn't support prepared statements
    if ($wpdb->get_var("SHOW TABLES LIKE '{$customers_table}'") !== $customers_table) {
        wp_send_json_error(['message' => __('Tabela de clientes não encontrada.', 'hng-commerce')]);
    }
    
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe
    $customer = $wpdb->get_row($wpdb->prepare(
        "SELECT wp_user_id FROM {$customers_table} WHERE id = %d",
        $customer_id
    ));
    
    if (!$customer || empty($customer->wp_user_id)) {
        wp_send_json_error(['message' => __('Cliente não vinculado com nenhum usuário.', 'hng-commerce')]);
    }
    
    $old_user_id = $customer->wp_user_id;
    
    // Update customer record
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe
    $wpdb->update(
        $customers_table,
        ['wp_user_id' => null],
        ['id' => $customer_id],
        ['%d'],
        ['%d']
    );
    
    // Remove user metadata
    delete_user_meta($old_user_id, 'hng_customer_email');
    delete_user_meta($old_user_id, 'hng_customer_id');
    
    wp_send_json_success([
        'message' => __('Cliente desvinculado com sucesso.', 'hng-commerce')
    ]);
}
add_action('wp_ajax_hng_unlink_customer_from_user', 'hng_ajax_unlink_customer_from_user');

/**
 * AJAX: Generate registration link for customer
 */
function hng_ajax_generate_registration_link() {
    // Verify nonce
    $nonce_check = wp_verify_nonce($_POST['nonce'] ?? '', 'hng-commerce-admin');
    
    if (!$nonce_check) {
        wp_send_json_error(['message' => __('Erro de segurança. Recarregue a página e tente novamente.', 'hng-commerce')], 403);
    }
    
    // Check capability
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Permissão negada.', 'hng-commerce')], 403);
    }
    
    // Get parameters
    // phpcs:ignore WordPress.Security.ValidatedInput.InputNotSanitized -- Email will be validated with is_email()
    $customer_email = isset($_POST['customer_email']) ? sanitize_email($_POST['customer_email']) : '';
    $customer_name = isset($_POST['customer_name']) ? sanitize_text_field($_POST['customer_name']) : '';
    
    if (empty($customer_email) || !is_email($customer_email)) {
        wp_send_json_error(['message' => __('E-mail do cliente inválido.', 'hng-commerce')]);
    }
    
    global $wpdb;
    
    // Check if customer already has account
    $user = get_user_by('email', $customer_email);
    if ($user) {
        wp_send_json_error([
            'message' => sprintf(
                /* translators: %s = username */
                __('Este cliente já possui uma conta: %s', 'hng-commerce'),
                $user->user_login
            )
        ]);
    }
    
    // Generate token
    $token = wp_generate_password(32, false);
    $expiration = time() + (7 * DAY_IN_SECONDS); // 7 days validity
    
    // Store token in options
    set_transient('hng_registration_token_' . $token, [
        'email' => $customer_email,
        'name' => $customer_name
    ], 7 * DAY_IN_SECONDS);
    
    // Generate registration URL
    $registration_url = add_query_arg([
        'action' => 'hng_register',
        'token' => $token
    ], home_url('/'));
    
    // Send email with registration link
    $subject = sprintf(
        /* translators: %s = blog name */
        __('Bem-vindo(a) - Complete seu cadastro em %s', 'hng-commerce'),
        get_bloginfo('name')
    );
    
    $message = sprintf(
        /* translators: %1$s = customer name, %2$s = registration URL */
        __('
        <h2>Olá %1$s!</h2>
        <p>Você foi convidado para criar uma conta em nosso sistema.</p>
        <p><a href="%2$s" style="background-color: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Completar Cadastro</a></p>
        <p>Este link expira em 7 dias.</p>
        <p>Se você não solicitou este convite, ignore este e-mail.</p>
        ', 'hng-commerce'),
        $customer_name,
        $registration_url
    );
    
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    
    $email_sent = wp_mail($customer_email, $subject, $message, $headers);
    
    if (!$email_sent) {
        wp_send_json_error(['message' => __('Erro ao enviar e-mail de registro.', 'hng-commerce')]);
    }
    
    wp_send_json_success([
        'message' => sprintf(
            /* translators: %s = customer email */
            __('Link de registro enviado para %s', 'hng-commerce'),
            $customer_email
        ),
        'token' => $token,
        'url' => $registration_url,
        'expiration' => mysql2date(__('d/m/Y H:i', 'hng-commerce'), gmdate('Y-m-d H:i:s', $expiration))
    ]);
}
add_action('wp_ajax_hng_generate_registration_link', 'hng_ajax_generate_registration_link');

/**
 * AJAX: Create WordPress account for customer
 */
function hng_ajax_create_customer_account() {
    // Verify nonce
    $nonce_check = wp_verify_nonce($_POST['nonce'] ?? '', 'hng-commerce-admin');
    
    if (!$nonce_check) {
        wp_send_json_error(['message' => __('Erro de segurança. Recarregue a página e tente novamente.', 'hng-commerce')], 403);
    }
    
    // Check capability
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Permissão negada.', 'hng-commerce')], 403);
    }
    
    // Get parameters
    // phpcs:ignore WordPress.Security.ValidatedInput.InputNotSanitized -- Email will be validated with is_email()
    $customer_email = isset($_POST['customer_email']) ? sanitize_email($_POST['customer_email']) : '';
    $customer_name = isset($_POST['customer_name']) ? sanitize_text_field($_POST['customer_name']) : '';
    
    if (empty($customer_email) || !is_email($customer_email)) {
        wp_send_json_error(['message' => __('E-mail do cliente inválido.', 'hng-commerce')]);
    }
    
    // Check if user already exists
    if (email_exists($customer_email)) {
        wp_send_json_error([
            'message' => __('Já existe uma conta com este e-mail.', 'hng-commerce')
        ]);
    }
    
    // Generate username from email
    $username = sanitize_user(strstr($customer_email, '@', true), true);
    
    // Check if username is available
    $counter = 1;
    $original_username = $username;
    while (username_exists($username)) {
        $username = $original_username . $counter;
        $counter++;
    }
    
    // Generate temporary password
    $password = wp_generate_password(16, true);
    
    // Create user
    $user_id = wp_create_user($username, $password, $customer_email);
    
    if (is_wp_error($user_id)) {
        wp_send_json_error([
            'message' => $user_id->get_error_message()
        ]);
    }
    
    // Set display name
    wp_update_user([
        'ID' => $user_id,
        'display_name' => $customer_name ?: $username,
        'first_name' => $customer_name
    ]);
    
    // Send welcome email with password
    $subject = sprintf(
        /* translators: %s = blog name */
        __('Sua conta foi criada em %s', 'hng-commerce'),
        get_bloginfo('name')
    );
    
    $message = sprintf(
        /* translators: %1$s = customer name, %2$s = username, %3$s = password, %4$s = login URL */
        __('
        <h2>Olá %1$s!</h2>
        <p>Sua conta foi criada com sucesso.</p>
        <p><strong>Usuário:</strong> %2$s</p>
        <p><strong>Senha Temporária:</strong> %3$s</p>
        <p>Você pode fazer login <a href="%4$s">aqui</a>.</p>
        <p>Recomendamos alterar sua senha após o primeiro login.</p>
        ', 'hng-commerce'),
        $customer_name,
        $username,
        $password,
        wp_login_url()
    );
    
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    wp_mail($customer_email, $subject, $message, $headers);
    
    // Link customer to user
    global $wpdb;
    $customers_table = $wpdb->prefix . 'hng_customers';
    
    // Check if table exists - if not, it will be created on first migration
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SHOW TABLES doesn't support prepared statements
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$customers_table}'") === $customers_table;
    
    if ($table_exists) {
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$customers_table} WHERE email = %s LIMIT 1",
            $customer_email
        ));
        
        if ($customer) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe
            $wpdb->update(
                $customers_table,
                ['wp_user_id' => $user_id],
                ['id' => $customer->id],
                ['%d'],
                ['%d']
            );
        } else {
            // Create customer record
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe
            $wpdb->insert(
                $customers_table,
                [
                    'email' => $customer_email,
                    'name' => $customer_name,
                    'wp_user_id' => $user_id,
                    'created_at' => current_time('mysql')
                ],
                ['%s', '%s', '%d', '%s']
            );
        }
    }
    
    update_user_meta($user_id, 'hng_customer_email', $customer_email);
    
    wp_send_json_success([
        'message' => sprintf(
            /* translators: %s = customer email */
            __('Conta criada com sucesso para %s. Credenciais enviadas por e-mail.', 'hng-commerce'),
            $customer_email
        ),
        'user_id' => $user_id,
        'username' => $username
    ]);
}
add_action('wp_ajax_hng_create_customer_account', 'hng_ajax_create_customer_account');
