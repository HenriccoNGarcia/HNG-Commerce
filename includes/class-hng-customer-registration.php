<?php
/**
 * HNG Commerce - Customer Registration Handler
 * 
 * Handles AJAX registration for new customers (providers and companies)
 * Creates WordPress user and saves customer metadata
 * 
 * @package HNG_Commerce
 * @since 1.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Customer_Registration {
    
    /**

     * Customer type constants

     */

    const TYPE_PROVIDER = 'provider';

    const TYPE_COMPANY = 'company';

    

    /**

     * Meta keys for customer data

     */

    const META_PREFIX = '_hng_customer_';

    

    /**

     * Constructor - register AJAX handlers

     */

    public function __construct() {

        add_action('wp_ajax_hng_register_client', [$this, 'handle_registration']);

        add_action('wp_ajax_nopriv_hng_register_client', [$this, 'handle_registration']);

        add_action('wp_ajax_hng_client_login', [$this, 'handle_login']);

        add_action('wp_ajax_nopriv_hng_client_login', [$this, 'handle_login']);

        add_action('wp_ajax_hng_get_customer_details', [$this, 'get_customer_details']);

    }

    

    /**

     * Handle customer login AJAX request

     */

    public function handle_login() {

        // Verify nonce

        if (!isset($_POST['hng_login_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['hng_login_nonce'])), 'hng_client_login')) {

            wp_send_json_error(['message' => __('Erro de segurança. Recarregue a página e tente novamente.', 'hng-commerce')]);

        }

        

        // Get credentials

        $email = isset($_POST['login_email']) ? sanitize_email(wp_unslash($_POST['login_email'])) : '';

        $password = isset($_POST['login_password']) ? wp_unslash($_POST['login_password']) : '';

        $remember = isset($_POST['remember_me']) && $_POST['remember_me'] === 'on';

        

        if (empty($email) || empty($password)) {

            wp_send_json_error(['message' => __('Por favor, preencha e-mail e senha.', 'hng-commerce')]);

        }

        

        // Try to authenticate

        $user = wp_authenticate($email, $password);

        

        if (is_wp_error($user)) {

            // Translate common error messages

            $error_code = $user->get_error_code();

            switch ($error_code) {

                case 'invalid_email':

                case 'invalid_username':

                    $message = __('E-mail não encontrado.', 'hng-commerce');

                    break;

                case 'incorrect_password':

                    $message = __('Senha incorreta.', 'hng-commerce');

                    break;

                default:

                    $message = __('Credenciais inválidas.', 'hng-commerce');

            }

            wp_send_json_error(['message' => $message]);

        }

        

        // Set auth cookie

        wp_set_current_user($user->ID);

        wp_set_auth_cookie($user->ID, $remember);

        

        // Determine redirect URL

        $redirect_url = function_exists('hng_get_account_url') ? hng_get_account_url() : home_url('/minha-conta/');

        

        /* translators: %s: user display name */

        wp_send_json_success([

            /* translators: %s: user display name */
            'message' => sprintf(__('Bem-vindo(a) de volta, %s!', 'hng-commerce'), $user->display_name),

            'user_id' => $user->ID,

            'redirect' => $redirect_url,

        ]);

    }

    

    /**

     * Handle customer registration AJAX request

     */

    public function handle_registration() {

        // Verify nonce

        if (!isset($_POST['hng_client_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['hng_client_nonce'])), 'hng_client_registration')) {

            wp_send_json_error(['message' => __('Erro de segurança. Recarregue a página e tente novamente.', 'hng-commerce')]);

        }

        

        // Get client type

        $client_type = isset($_POST['client_type']) ? sanitize_text_field(wp_unslash($_POST['client_type'])) : '';

        

        if (!in_array($client_type, [self::TYPE_PROVIDER, self::TYPE_COMPANY], true)) {

            wp_send_json_error(['message' => __('Tipo de cliente inválido.', 'hng-commerce')]);

        }

        

        // Validate and sanitize data based on type

        if ($client_type === self::TYPE_PROVIDER) {

            $result = $this->register_provider();

        } else {

            $result = $this->register_company();

        }

        

        if (is_wp_error($result)) {

            wp_send_json_error(['message' => $result->get_error_message()]);

        }

        

        // Auto login the user

        wp_set_current_user($result['user_id']);

        wp_set_auth_cookie($result['user_id'], true);

        

        // Determine redirect URL

        $redirect_url = function_exists('hng_get_account_url') ? hng_get_account_url() : home_url('/minha-conta/');

        

        wp_send_json_success([

            'message' => __('Conta criada com sucesso! Bem-vindo(a)!', 'hng-commerce'),

            'user_id' => $result['user_id'],

            'redirect' => $redirect_url,

        ]);

    }

    

    /**

     * Register a service provider (pessoa física)

     * 

     * @return array|WP_Error

     */

    private function register_provider() {

        // Required fields

        $name = isset($_POST['provider_name']) ? sanitize_text_field(wp_unslash($_POST['provider_name'])) : '';

        $email = isset($_POST['provider_email']) ? sanitize_email(wp_unslash($_POST['provider_email'])) : '';

        $phone = isset($_POST['provider_phone']) ? sanitize_text_field(wp_unslash($_POST['provider_phone'])) : '';

        $whatsapp = isset($_POST['provider_whatsapp']) ? sanitize_text_field(wp_unslash($_POST['provider_whatsapp'])) : '';

        $password = isset($_POST['password']) ? $_POST['password'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Password should not be sanitized

        

        // Optional fields

        $area = isset($_POST['provider_area']) ? sanitize_text_field(wp_unslash($_POST['provider_area'])) : '';

        $social = isset($_POST['provider_social']) ? sanitize_textarea_field(wp_unslash($_POST['provider_social'])) : '';

        $services = isset($_POST['provider_services']) ? sanitize_textarea_field(wp_unslash($_POST['provider_services'])) : '';

        

        // Service needed

        $service_needed = isset($_POST['service_needed']) ? sanitize_text_field(wp_unslash($_POST['service_needed'])) : '';

        $other_service = isset($_POST['other_service']) ? sanitize_textarea_field(wp_unslash($_POST['other_service'])) : '';

        

        // Validate required fields

        if (empty($name) || empty($email) || empty($phone) || empty($whatsapp) || empty($password)) {

            return new WP_Error('missing_fields', __('Por favor, preencha todos os campos obrigatórios.', 'hng-commerce'));

        }

        

        if (!is_email($email)) {

            return new WP_Error('invalid_email', __('Por favor, informe um e-mail válido.', 'hng-commerce'));

        }

        

        if (strlen($password) < 6) {

            return new WP_Error('weak_password', __('A senha deve ter pelo menos 6 caracteres.', 'hng-commerce'));

        }

        

        // Check if email already exists

        if (email_exists($email)) {

            return new WP_Error('email_exists', __('Este e-mail já está cadastrado. Tente fazer login.', 'hng-commerce'));

        }

        

        // Create username from email

        $username = $this->generate_username($email);

        

        // Create WordPress user

        $user_id = wp_insert_user([

            'user_login' => $username,

            'user_email' => $email,

            'user_pass' => $password,

            'display_name' => $name,

            'first_name' => $this->get_first_name($name),

            'last_name' => $this->get_last_name($name),

            'role' => 'hng_customer',

        ]);

        

        if (is_wp_error($user_id)) {

            return $user_id;

        }

        

        // Save customer metadata

        update_user_meta($user_id, self::META_PREFIX . 'type', self::TYPE_PROVIDER);

        update_user_meta($user_id, self::META_PREFIX . 'name', $name);

        update_user_meta($user_id, self::META_PREFIX . 'phone', $phone);

        update_user_meta($user_id, self::META_PREFIX . 'whatsapp', $whatsapp);

        update_user_meta($user_id, self::META_PREFIX . 'area', $area);

        update_user_meta($user_id, self::META_PREFIX . 'social_networks', $social);

        update_user_meta($user_id, self::META_PREFIX . 'services_provided', $services);

        update_user_meta($user_id, self::META_PREFIX . 'service_needed', $service_needed);

        update_user_meta($user_id, self::META_PREFIX . 'other_service', $other_service);

        update_user_meta($user_id, self::META_PREFIX . 'registered_at', current_time('mysql'));

        

        // Allow extensions

        do_action('hng_customer_registered', $user_id, self::TYPE_PROVIDER, $_POST);

        

        return ['user_id' => $user_id];

    }

    

    /**

     * Register a company (pessoa jurídica)

     * 

     * @return array|WP_Error

     */

    private function register_company() {

        // Required fields

        $name = isset($_POST['company_name']) ? sanitize_text_field(wp_unslash($_POST['company_name'])) : '';

        $email = isset($_POST['company_email']) ? sanitize_email(wp_unslash($_POST['company_email'])) : '';

        $cnpj = isset($_POST['company_cnpj']) ? sanitize_text_field(wp_unslash($_POST['company_cnpj'])) : '';

        $phone = isset($_POST['company_phone']) ? sanitize_text_field(wp_unslash($_POST['company_phone'])) : '';

        $password = isset($_POST['password']) ? $_POST['password'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Password should not be sanitized

        

        // Optional fields

        $area = isset($_POST['company_area']) ? sanitize_text_field(wp_unslash($_POST['company_area'])) : '';

        

        // Service needed

        $service_needed = isset($_POST['service_needed']) ? sanitize_text_field(wp_unslash($_POST['service_needed'])) : '';

        $other_service = isset($_POST['other_service']) ? sanitize_textarea_field(wp_unslash($_POST['other_service'])) : '';

        

        // Validate required fields

        if (empty($name) || empty($email) || empty($cnpj) || empty($phone) || empty($password)) {

            return new WP_Error('missing_fields', __('Por favor, preencha todos os campos obrigatórios.', 'hng-commerce'));

        }

        

        if (!is_email($email)) {

            return new WP_Error('invalid_email', __('Por favor, informe um e-mail válido.', 'hng-commerce'));

        }

        

        if (strlen($password) < 6) {

            return new WP_Error('weak_password', __('A senha deve ter pelo menos 6 caracteres.', 'hng-commerce'));

        }

        

        // Validate CNPJ format

        $cnpj_clean = preg_replace('/\D/', '', $cnpj);

        if (strlen($cnpj_clean) !== 14) {

            return new WP_Error('invalid_cnpj', __('Por favor, informe um CNPJ válido.', 'hng-commerce'));

        }

        

        // Check if email already exists

        if (email_exists($email)) {

            return new WP_Error('email_exists', __('Este e-mail já está cadastrado. Tente fazer login.', 'hng-commerce'));

        }

        

        // Create username from email

        $username = $this->generate_username($email);

        

        // Create WordPress user

        $user_id = wp_insert_user([

            'user_login' => $username,

            'user_email' => $email,

            'user_pass' => $password,

            'display_name' => $name,

            'first_name' => $name,

            'role' => 'hng_customer',

        ]);

        

        if (is_wp_error($user_id)) {

            return $user_id;

        }

        

        // Save customer metadata

        update_user_meta($user_id, self::META_PREFIX . 'type', self::TYPE_COMPANY);

        update_user_meta($user_id, self::META_PREFIX . 'company_name', $name);

        update_user_meta($user_id, self::META_PREFIX . 'cnpj', $cnpj);

        update_user_meta($user_id, self::META_PREFIX . 'phone', $phone);

        update_user_meta($user_id, self::META_PREFIX . 'whatsapp', $phone); // Same as phone for company

        update_user_meta($user_id, self::META_PREFIX . 'area', $area);

        update_user_meta($user_id, self::META_PREFIX . 'service_needed', $service_needed);

        update_user_meta($user_id, self::META_PREFIX . 'other_service', $other_service);

        update_user_meta($user_id, self::META_PREFIX . 'registered_at', current_time('mysql'));

        

        // Allow extensions

        do_action('hng_customer_registered', $user_id, self::TYPE_COMPANY, $_POST);

        

        return ['user_id' => $user_id];

    }

    

    /**

     * Generate unique username from email

     * 

     * @param string $email

     * @return string

     */

    private function generate_username($email) {

        $base = sanitize_user(explode('@', $email)[0], true);

        $username = $base;

        $counter = 1;

        

        while (username_exists($username)) {

            $username = $base . $counter;

            $counter++;

        }

        

        return $username;

    }

    

    /**

     * Get first name from full name

     * 

     * @param string $full_name

     * @return string

     */

    private function get_first_name($full_name) {

        $parts = explode(' ', trim($full_name));

        return $parts[0] ?? '';

    }

    

    /**

     * Get last name from full name

     * 

     * @param string $full_name

     * @return string

     */

    private function get_last_name($full_name) {

        $parts = explode(' ', trim($full_name));

        array_shift($parts);

        return implode(' ', $parts);

    }

    

    /**

     * Get customer details for admin modal

     */

    public function get_customer_details() {

        // Verify nonce and permissions

        if (!current_user_can('manage_options')) {

            wp_send_json_error(['message' => __('Permissão negada.', 'hng-commerce')]);

        }

        

        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'hng-commerce-admin')) {

            wp_send_json_error(['message' => __('Erro de segurança.', 'hng-commerce')]);

        }

        

        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';

        

        if (empty($email)) {

            wp_send_json_error(['message' => __('E-mail não informado.', 'hng-commerce')]);

        }

        

        // Get user by email

        $user = get_user_by('email', $email);

        

        if (!$user) {

            // Return basic info from orders if user doesn't exist as WP user

            wp_send_json_success([

                'has_account' => false,

                'email' => $email,

                'message' => __('Cliente não possui conta cadastrada no sistema.', 'hng-commerce'),

            ]);

        }

        

        // Get all customer meta

        $customer_data = [

            'has_account' => true,

            'user_id' => $user->ID,

            'username' => $user->user_login,

            'email' => $user->user_email,

            'display_name' => $user->display_name,

            'registered' => $user->user_registered,

            'type' => get_user_meta($user->ID, self::META_PREFIX . 'type', true),

            'name' => get_user_meta($user->ID, self::META_PREFIX . 'name', true),

            'company_name' => get_user_meta($user->ID, self::META_PREFIX . 'company_name', true),

            'cnpj' => get_user_meta($user->ID, self::META_PREFIX . 'cnpj', true),

            'phone' => get_user_meta($user->ID, self::META_PREFIX . 'phone', true),

            'whatsapp' => get_user_meta($user->ID, self::META_PREFIX . 'whatsapp', true),

            'area' => get_user_meta($user->ID, self::META_PREFIX . 'area', true),

            'social_networks' => get_user_meta($user->ID, self::META_PREFIX . 'social_networks', true),

            'services_provided' => get_user_meta($user->ID, self::META_PREFIX . 'services_provided', true),

            'service_needed' => get_user_meta($user->ID, self::META_PREFIX . 'service_needed', true),

            'other_service' => get_user_meta($user->ID, self::META_PREFIX . 'other_service', true),

            'registered_at' => get_user_meta($user->ID, self::META_PREFIX . 'registered_at', true),

        ];

        

        // Get service name if it's a product ID

        if (!empty($customer_data['service_needed']) && is_numeric($customer_data['service_needed'])) {

            $product = get_post($customer_data['service_needed']);

            if ($product) {

                $customer_data['service_needed_name'] = $product->post_title;

            }

        }

        

        // Force type and label based on user role or linked customer

        $user_roles = is_array($user->roles) ? $user->roles : [];

        $type = $customer_data['type'] && $customer_data['type'] !== '' ? $customer_data['type'] : 'supplier';

        

        // Se o usuário tem papel de cliente, força o tipo para cliente

        if (in_array('hng_customer', $user_roles, true) || in_array('customer', $user_roles, true)) {

            $type = 'customer';

        }

        

        // Check if customer is linked in hng_customers table

        global $wpdb;

        $linked_customer = $wpdb->get_row($wpdb->prepare(

            "SELECT id, wp_user_id FROM {$wpdb->prefix}hng_customers WHERE wp_user_id = %d LIMIT 1",

            $user->ID

        ));

        if ($linked_customer) {

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

        

        $customer_data['type'] = $type;

        $customer_data['type_label'] = $type_label;

        

        wp_send_json_success($customer_data);

    }

    

    /**

     * Get all customer meta fields

     * 

     * @param int $user_id

     * @return array

     */

    public static function get_customer_meta($user_id) {

        $fields = [

            'type', 'name', 'company_name', 'cnpj', 'phone', 'whatsapp',

            'area', 'social_networks', 'services_provided', 

            'service_needed', 'other_service', 'registered_at'

        ];

        

        $data = [];

        foreach ($fields as $field) {

            $data[$field] = get_user_meta($user_id, self::META_PREFIX . $field, true);

        }

        

        return $data;

    }

    

    /**

     * Check if user is a HNG customer

     * 

     * @param int $user_id

     * @return bool

     */

    public static function is_hng_customer($user_id) {

        $user = get_userdata($user_id);

        if (!$user) {

            return false;

        }

        

        return in_array('hng_customer', (array) $user->roles, true);

    }

}



// Initialize

new HNG_Customer_Registration();

