<?php
/**
 * HNG Commerce - Google OAuth Authentication
 * 
 * Handles Google OAuth login/register and user profile completion
 * 
 * @package HNG_Commerce
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Google_Auth {
    
    /**
     * Google OAuth credentials
     */
    private $client_id;
    private $client_secret;
    private $redirect_uri;
    
    /**
     * Google OAuth endpoints
     */
    const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    const USERINFO_URL = 'https://www.googleapis.com/oauth2/v2/userinfo';
    
    /**
     * Meta key for Google ID
     */
    const META_GOOGLE_ID = '_hng_google_id';
    const META_NEEDS_PROFILE_COMPLETION = '_hng_needs_profile_completion';
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Get credentials from options (MUST be configured in WordPress settings)
        $this->client_id = get_option('hng_google_client_id', '');
        $this->client_secret = get_option('hng_google_client_secret', '');
        $this->redirect_uri = home_url('/auth/google/callback/');
        
        // Check if credentials are configured
        if (empty($this->client_id) || empty($this->client_secret)) {
            error_log('HNG Commerce: Google OAuth credentials not configured. Please set them in WordPress settings.');
        }
        
        // Register handlers
        add_action('init', [$this, 'handle_auth_routes']);
        add_action('template_redirect', [$this, 'check_profile_completion']);
        
        // AJAX handlers
        add_action('wp_ajax_hng_complete_google_profile', [$this, 'ajax_complete_profile']);
        add_action('wp_ajax_nopriv_hng_complete_google_profile', [$this, 'ajax_complete_profile']);
    }
    
    /**
     * Handle custom auth routes
     */
    public function handle_auth_routes() {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        
        // Start OAuth flow
        if (strpos($request_uri, '/auth/google/') !== false && strpos($request_uri, '/callback') === false) {
            $this->start_oauth();
            exit;
        }
        
        // Handle callback
        if (strpos($request_uri, '/auth/google/callback') !== false) {
            $this->handle_callback();
            exit;
        }
    }
    
    /**
     * Start OAuth flow - redirect to Google
     */
    private function start_oauth() {
        // Validate credentials are configured
        if (empty($this->client_id) || empty($this->client_secret)) {
            wp_die(__('Google OAuth não está configurado. Contate o administrador.', 'hng-commerce'), 403);
        }
        
        // Save action (login or register) to session
        $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : 'login';
        
        // Generate state token for CSRF protection
        $state = wp_create_nonce('hng_google_auth');
        set_transient('hng_google_state_' . $state, $action, 600); // 10 minutes
        
        $params = [
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'select_account',
        ];
        
        $auth_url = self::AUTH_URL . '?' . http_build_query($params);
        
        wp_redirect($auth_url);
        exit;
    }
    
    /**
     * Handle OAuth callback from Google
     */
    private function handle_callback() {
        // Check for errors
        if (isset($_GET['error'])) {
            $this->redirect_with_error(__('Autenticação cancelada ou falhou.', 'hng-commerce'));
            return;
        }
        
        // Verify state
        $state = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';
        $action = get_transient('hng_google_state_' . $state);
        
        if (!$action) {
            $this->redirect_with_error(__('Token de segurança inválido. Tente novamente.', 'hng-commerce'));
            return;
        }
        
        delete_transient('hng_google_state_' . $state);
        
        // Get authorization code
        $code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';
        if (empty($code)) {
            $this->redirect_with_error(__('Código de autorização não recebido.', 'hng-commerce'));
            return;
        }
        
        // Exchange code for tokens
        $tokens = $this->exchange_code_for_tokens($code);
        if (is_wp_error($tokens)) {
            $this->redirect_with_error($tokens->get_error_message());
            return;
        }
        
        // Get user info from Google
        $google_user = $this->get_google_user($tokens['access_token']);
        if (is_wp_error($google_user)) {
            $this->redirect_with_error($google_user->get_error_message());
            return;
        }
        
        // Process login or registration
        $result = $this->process_google_user($google_user, $action);
        if (is_wp_error($result)) {
            $this->redirect_with_error($result->get_error_message());
            return;
        }
        
        // Set auth cookie
        wp_set_current_user($result['user_id']);
        wp_set_auth_cookie($result['user_id'], true);
        
        // Redirect based on profile completion status
        if ($result['needs_profile_completion']) {
            wp_redirect(home_url('/completar-cadastro/'));
        } else {
            $redirect_url = function_exists('hng_get_account_url') ? hng_get_account_url() : home_url('/minha-conta/');
            wp_redirect($redirect_url);
        }
        exit;
    }
    
    /**
     * Exchange authorization code for tokens
     */
    private function exchange_code_for_tokens($code) {
        $response = wp_remote_post(self::TOKEN_URL, [
            'body' => [
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $this->redirect_uri,
            ],
        ]);
        
        if (is_wp_error($response)) {
            return new WP_Error('token_error', __('Erro ao obter tokens do Google.', 'hng-commerce'));
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            return new WP_Error('token_error', $body['error_description'] ?? __('Erro ao obter tokens.', 'hng-commerce'));
        }
        
        return $body;
    }
    
    /**
     * Get user info from Google
     */
    private function get_google_user($access_token) {
        $response = wp_remote_get(self::USERINFO_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
            ],
        ]);
        
        if (is_wp_error($response)) {
            return new WP_Error('userinfo_error', __('Erro ao obter informações do usuário.', 'hng-commerce'));
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            return new WP_Error('userinfo_error', __('Erro ao obter informações do usuário.', 'hng-commerce'));
        }
        
        return $body;
    }
    
    /**
     * Process Google user - login or register
     */
    private function process_google_user($google_user, $action) {
        $google_id = sanitize_text_field($google_user['id']);
        $email = sanitize_email($google_user['email']);
        $name = sanitize_text_field($google_user['name'] ?? '');
        $picture = esc_url_raw($google_user['picture'] ?? '');
        
        // Check if user exists by Google ID
        $users_by_google = get_users([
            'meta_key' => self::META_GOOGLE_ID,
            'meta_value' => $google_id,
            'number' => 1,
        ]);
        
        if (!empty($users_by_google)) {
            // Existing user with Google ID - just login
            return [
                'user_id' => $users_by_google[0]->ID,
                'needs_profile_completion' => (bool) get_user_meta($users_by_google[0]->ID, self::META_NEEDS_PROFILE_COMPLETION, true),
            ];
        }
        
        // Check if user exists by email
        $existing_user = get_user_by('email', $email);
        
        if ($existing_user) {
            // Link Google ID to existing account
            update_user_meta($existing_user->ID, self::META_GOOGLE_ID, $google_id);
            
            // Check if profile is complete
            $has_phone = get_user_meta($existing_user->ID, '_hng_customer_phone', true);
            $needs_completion = empty($has_phone);
            
            if ($needs_completion) {
                update_user_meta($existing_user->ID, self::META_NEEDS_PROFILE_COMPLETION, true);
            }
            
            return [
                'user_id' => $existing_user->ID,
                'needs_profile_completion' => $needs_completion,
            ];
        }
        
        // Create new user
        $username = $this->generate_unique_username($email, $name);
        $password = wp_generate_password(16, true, true);
        
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            return $user_id;
        }
        
        // Update user data
        wp_update_user([
            'ID' => $user_id,
            'display_name' => $name,
            'first_name' => explode(' ', $name)[0] ?? '',
            'last_name' => count(explode(' ', $name)) > 1 ? end(explode(' ', $name)) : '',
        ]);
        
        // Set role
        $user = new WP_User($user_id);
        $user->set_role('hng_customer');
        
        // Save Google-specific meta
        update_user_meta($user_id, self::META_GOOGLE_ID, $google_id);
        update_user_meta($user_id, self::META_NEEDS_PROFILE_COMPLETION, true);
        update_user_meta($user_id, '_hng_customer_name', $name);
        update_user_meta($user_id, '_hng_customer_email', $email);
        update_user_meta($user_id, '_hng_customer_google_picture', $picture);
        update_user_meta($user_id, '_hng_customer_type', 'provider'); // Default, can change later
        update_user_meta($user_id, '_hng_customer_registered_via', 'google');
        
        // Trigger action for other plugins
        do_action('hng_customer_registered', $user_id, 'google');
        
        return [
            'user_id' => $user_id,
            'needs_profile_completion' => true,
        ];
    }
    
    /**
     * Generate unique username from email or name
     */
    private function generate_unique_username($email, $name) {
        // Try email prefix first
        $base = sanitize_user(explode('@', $email)[0], true);
        
        if (empty($base)) {
            $base = sanitize_user(str_replace(' ', '', $name), true);
        }
        
        if (empty($base)) {
            $base = 'user';
        }
        
        $username = $base;
        $counter = 1;
        
        while (username_exists($username)) {
            $username = $base . $counter;
            $counter++;
        }
        
        return $username;
    }
    
    /**
     * Check if current user needs profile completion
     */
    public function check_profile_completion() {
        if (!is_user_logged_in()) {
            return;
        }
        
        $user_id = get_current_user_id();
        $needs_completion = get_user_meta($user_id, self::META_NEEDS_PROFILE_COMPLETION, true);
        
        // Don't redirect if already on the completion page or if completing
        $current_url = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        if (strpos($current_url, '/completar-cadastro') !== false) {
            return;
        }
        
        // Don't redirect on admin or AJAX
        if (is_admin() || wp_doing_ajax()) {
            return;
        }
        
        // Redirect to completion page if needed
        if ($needs_completion) {
            wp_redirect(home_url('/completar-cadastro/'));
            exit;
        }
    }
    
    /**
     * AJAX handler for completing profile
     */
    public function ajax_complete_profile() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'hng_complete_profile')) {
            wp_send_json_error(['message' => __('Erro de segurança. Recarregue a página.', 'hng-commerce')]);
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Você precisa estar logado.', 'hng-commerce')]);
        }
        
        $user_id = get_current_user_id();
        
        // Get and validate data
        $client_type = isset($_POST['client_type']) ? sanitize_text_field(wp_unslash($_POST['client_type'])) : 'provider';
        $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
        $whatsapp = isset($_POST['whatsapp']) ? sanitize_text_field(wp_unslash($_POST['whatsapp'])) : '';
        
        if (empty($phone)) {
            wp_send_json_error(['message' => __('O telefone é obrigatório.', 'hng-commerce')]);
        }
        
        // Save common data
        update_user_meta($user_id, '_hng_customer_type', $client_type);
        update_user_meta($user_id, '_hng_customer_phone', $phone);
        update_user_meta($user_id, '_hng_customer_whatsapp', $whatsapp ?: $phone);
        
        // Provider-specific data
        if ($client_type === 'provider') {
            $cpf = isset($_POST['cpf']) ? sanitize_text_field(wp_unslash($_POST['cpf'])) : '';
            update_user_meta($user_id, '_hng_customer_cpf', $cpf);
        }
        
        // Company-specific data
        if ($client_type === 'company') {
            $company_name = isset($_POST['company_name']) ? sanitize_text_field(wp_unslash($_POST['company_name'])) : '';
            $cnpj = isset($_POST['cnpj']) ? sanitize_text_field(wp_unslash($_POST['cnpj'])) : '';
            $company_email = isset($_POST['company_email']) ? sanitize_email(wp_unslash($_POST['company_email'])) : '';
            $responsible_name = isset($_POST['responsible_name']) ? sanitize_text_field(wp_unslash($_POST['responsible_name'])) : '';
            $responsible_role = isset($_POST['responsible_role']) ? sanitize_text_field(wp_unslash($_POST['responsible_role'])) : '';
            
            update_user_meta($user_id, '_hng_customer_company_name', $company_name);
            update_user_meta($user_id, '_hng_customer_cnpj', $cnpj);
            update_user_meta($user_id, '_hng_customer_company_email', $company_email);
            update_user_meta($user_id, '_hng_customer_responsible_name', $responsible_name);
            update_user_meta($user_id, '_hng_customer_responsible_role', $responsible_role);
            
            // Update display name
            if (!empty($company_name)) {
                wp_update_user([
                    'ID' => $user_id,
                    'display_name' => $company_name,
                ]);
            }
        }
        
        // Address data (optional)
        $address_fields = ['cep', 'address', 'number', 'complement', 'district', 'city', 'state'];
        foreach ($address_fields as $field) {
            if (isset($_POST[$field])) {
                update_user_meta($user_id, '_hng_customer_' . $field, sanitize_text_field(wp_unslash($_POST[$field])));
            }
        }
        
        // Mark profile as complete
        delete_user_meta($user_id, self::META_NEEDS_PROFILE_COMPLETION);
        
        // Trigger action
        do_action('hng_customer_profile_completed', $user_id, $client_type);
        
        $redirect_url = function_exists('hng_get_account_url') ? hng_get_account_url() : home_url('/minha-conta/');
        
        wp_send_json_success([
            'message' => __('Cadastro completado com sucesso!', 'hng-commerce'),
            'redirect' => $redirect_url,
        ]);
    }
    
    /**
     * Redirect with error message
     */
    private function redirect_with_error($message) {
        $url = add_query_arg([
            'auth_error' => urlencode($message),
        ], home_url('/seja-nosso-cliente/'));
        
        wp_redirect($url);
        exit;
    }
    
    /**
     * Check if a user was registered via Google
     */
    public static function is_google_user($user_id) {
        return (bool) get_user_meta($user_id, self::META_GOOGLE_ID, true);
    }
}

// Initialize
HNG_Google_Auth::instance();
