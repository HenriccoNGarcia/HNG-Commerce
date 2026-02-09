<?php
/**
 * HNG Commerce - Google OAuth Login
 * 
 * Handles authentication via Google OAuth 2.0
 *
 * @package HNG_Commerce
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Google_OAuth {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Google OAuth endpoints
     */
    private const GOOGLE_AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const GOOGLE_TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const GOOGLE_USERINFO_URL = 'https://www.googleapis.com/oauth2/v2/userinfo';
    
    /**
     * Get instance
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
        // Handle OAuth callback
        add_action('template_redirect', [$this, 'handle_oauth_callback']);
        
        // Add settings fields
        add_action('admin_init', [$this, 'register_settings']);
        
        // Add Google login button hook
        add_action('hng_login_form_social', [$this, 'render_google_button']);
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // Settings are registered in HNG_Admin_Settings class
    }
    
    /**
     * Get Client ID
     */
    public function get_client_id() {
        $options = get_option('hng_commerce_settings', []);
        return $options['google_oauth_client_id'] ?? '';
    }
    
    /**
     * Get Client Secret
     */
    public function get_client_secret() {
        $options = get_option('hng_commerce_settings', []);
        return $options['google_oauth_client_secret'] ?? '';
    }
    
    /**
     * Check if enabled
     */
    public function is_enabled() {
        $options = get_option('hng_commerce_settings', []);
        $enabled = $options['google_oauth_enabled'] ?? 'no';
        
        return $enabled === 'yes' 
               && !empty($this->get_client_id()) 
               && !empty($this->get_client_secret());
    }
    
    /**
     * Get redirect URI
     */
    public function get_redirect_uri() {
        return site_url('/') . '?hng_google_oauth=callback';
    }
    
    /**
     * Generate authorization URL
     */
    public function get_auth_url($redirect_to = '') {
        if (!$this->is_enabled()) {
            return '';
        }
        
        // Generate state for CSRF protection
        $state = wp_generate_password(32, false);
        set_transient('hng_google_oauth_state_' . $state, [
            'redirect_to' => $redirect_to ?: hng_get_myaccount_url(),
            'created_at' => time()
        ], 10 * MINUTE_IN_SECONDS);
        
        $params = [
            'client_id' => $this->get_client_id(),
            'redirect_uri' => $this->get_redirect_uri(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'access_type' => 'online',
            'prompt' => 'select_account'
        ];
        
        return self::GOOGLE_AUTH_URL . '?' . http_build_query($params);
    }
    
    /**
     * Handle OAuth callback
     */
    public function handle_oauth_callback() {
        // Check if this is a Google OAuth callback
        if (!isset($_GET['hng_google_oauth']) || $_GET['hng_google_oauth'] !== 'callback') {
            return;
        }
        
        // Get params
        $code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';
        $state = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';
        $error = isset($_GET['error']) ? sanitize_text_field(wp_unslash($_GET['error'])) : '';
        
        // Check for errors
        if ($error) {
            $this->redirect_with_error(__('Autenticação com Google cancelada.', 'hng-commerce'));
            return;
        }
        
        // Validate state
        $state_data = get_transient('hng_google_oauth_state_' . $state);
        if (!$state_data) {
            $this->redirect_with_error(__('Sessão expirada. Tente novamente.', 'hng-commerce'));
            return;
        }
        
        // Delete state transient
        delete_transient('hng_google_oauth_state_' . $state);
        
        // Exchange code for token
        $token_response = $this->exchange_code_for_token($code);
        
        if (is_wp_error($token_response)) {
            $this->redirect_with_error($token_response->get_error_message());
            return;
        }
        
        // Get user info
        $user_info = $this->get_user_info($token_response['access_token']);
        
        if (is_wp_error($user_info)) {
            $this->redirect_with_error($user_info->get_error_message());
            return;
        }
        
        // Find or create user
        $user_id = $this->find_or_create_user($user_info);
        
        if (is_wp_error($user_id)) {
            $this->redirect_with_error($user_id->get_error_message());
            return;
        }
        
        // Log in user
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        
        // Redirect
        $redirect_to = $state_data['redirect_to'] ?: hng_get_myaccount_url();
        wp_safe_redirect($redirect_to);
        exit;
    }
    
    /**
     * Exchange authorization code for access token
     */
    private function exchange_code_for_token($code) {
        $response = wp_remote_post(self::GOOGLE_TOKEN_URL, [
            'body' => [
                'client_id' => $this->get_client_id(),
                'client_secret' => $this->get_client_secret(),
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $this->get_redirect_uri()
            ],
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            return new WP_Error('google_token_error', $body['error_description'] ?? $body['error']);
        }
        
        return $body;
    }
    
    /**
     * Get user info from Google
     */
    private function get_user_info($access_token) {
        $response = wp_remote_get(self::GOOGLE_USERINFO_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token
            ],
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            return new WP_Error('google_userinfo_error', $body['error']['message'] ?? __('Erro ao obter informações do usuário.', 'hng-commerce'));
        }
        
        return $body;
    }
    
    /**
     * Find or create user from Google data
     */
    private function find_or_create_user($user_info) {
        $email = sanitize_email($user_info['email'] ?? '');
        $google_id = sanitize_text_field($user_info['id'] ?? '');
        $name = sanitize_text_field($user_info['name'] ?? '');
        $first_name = sanitize_text_field($user_info['given_name'] ?? '');
        $last_name = sanitize_text_field($user_info['family_name'] ?? '');
        $picture = esc_url_raw($user_info['picture'] ?? '');
        
        if (empty($email)) {
            return new WP_Error('no_email', __('E-mail não fornecido pelo Google.', 'hng-commerce'));
        }
        
        // Check if user exists by Google ID
        $users = get_users([
            'meta_key' => '_hng_google_id',
            'meta_value' => $google_id,
            'number' => 1
        ]);
        
        if (!empty($users)) {
            $user = $users[0];
            // Update profile picture if changed
            update_user_meta($user->ID, '_hng_google_picture', $picture);
            return $user->ID;
        }
        
        // Check if user exists by email
        $user = get_user_by('email', $email);
        
        if ($user) {
            // Link Google account to existing user
            update_user_meta($user->ID, '_hng_google_id', $google_id);
            update_user_meta($user->ID, '_hng_google_picture', $picture);
            
            // Update name if not set
            if (empty($user->first_name) && !empty($first_name)) {
                update_user_meta($user->ID, 'first_name', $first_name);
            }
            if (empty($user->last_name) && !empty($last_name)) {
                update_user_meta($user->ID, 'last_name', $last_name);
            }
            
            return $user->ID;
        }
        
        // Create new user
        $username = $this->generate_unique_username($email, $name);
        $password = wp_generate_password(24, true, true);
        
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            return $user_id;
        }
        
        // Update user data
        wp_update_user([
            'ID' => $user_id,
            'display_name' => $name,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'role' => 'hng_customer'
        ]);
        
        // Save Google data
        update_user_meta($user_id, '_hng_google_id', $google_id);
        update_user_meta($user_id, '_hng_google_picture', $picture);
        update_user_meta($user_id, '_hng_registered_via', 'google');
        
        // Send welcome email
        do_action('hng_customer_created', $user_id, 'google');
        
        return $user_id;
    }
    
    /**
     * Generate unique username
     */
    private function generate_unique_username($email, $name = '') {
        // Try name first
        if (!empty($name)) {
            $base = sanitize_user(strtolower(str_replace(' ', '', $name)), true);
            if (!empty($base) && !username_exists($base)) {
                return $base;
            }
        }
        
        // Use email prefix
        $base = sanitize_user(strtok($email, '@'), true);
        
        if (!username_exists($base)) {
            return $base;
        }
        
        // Add numbers until unique
        $counter = 1;
        while (username_exists($base . $counter)) {
            $counter++;
        }
        
        return $base . $counter;
    }
    
    /**
     * Redirect with error message
     */
    private function redirect_with_error($message) {
        hng_add_notice($message, 'error');
        wp_safe_redirect(hng_get_myaccount_url());
        exit;
    }
    
    /**
     * Render Google login button
     */
    public function render_google_button() {
        if (!$this->is_enabled()) {
            return;
        }
        
        ?>
        <a href="<?php echo esc_url($this->get_auth_url()); ?>" class="hng-google-login-btn">
            <svg width="20" height="20" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
            </svg>
            <?php esc_html_e('Continuar com Google', 'hng-commerce'); ?>
        </a>
        <?php
    }
}

/**
 * Get Google OAuth instance
 */
function hng_google_oauth() {
    return HNG_Google_OAuth::instance();
}

/**
 * Get Google login URL
 */
function hng_get_google_login_url($redirect_to = '') {
    return hng_google_oauth()->get_auth_url($redirect_to);
}

/**
 * Check if Google OAuth is enabled
 */
function hng_is_google_oauth_enabled() {
    return hng_google_oauth()->is_enabled();
}

// Initialize
HNG_Google_OAuth::instance();
