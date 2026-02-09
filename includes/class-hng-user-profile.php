<?php
/**
 * HNG Commerce - User Profile Handler
 * 
 * Handles user profile updates, password changes, data export and account deletion (LGPD)
 * 
 * @package HNG_Commerce
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_User_Profile {
    
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
        // AJAX handlers - profile update
        add_action('wp_ajax_hng_update_profile', [$this, 'ajax_update_profile']);
        add_action('wp_ajax_hng_update_address', [$this, 'ajax_update_address']);
        add_action('wp_ajax_hng_update_password', [$this, 'ajax_update_password']);
        
        // AJAX handlers - LGPD
        add_action('wp_ajax_hng_export_user_data', [$this, 'ajax_export_data']);
        add_action('wp_ajax_hng_delete_account', [$this, 'ajax_delete_account']);
    }
    
    /**
     * Update user profile
     */
    public function ajax_update_profile() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'hng_update_profile')) {
            wp_send_json_error(['message' => 'Sessão expirada. Recarregue a página.']);
            return;
        }
        
        // Must be logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Você precisa estar logado.']);
            return;
        }
        
        $user_id = get_current_user_id();
        
        // Update name
        if (!empty($_POST['name'])) {
            $name = sanitize_text_field(wp_unslash($_POST['name']));
            update_user_meta($user_id, '_hng_customer_name', $name);
            wp_update_user([
                'ID' => $user_id,
                'display_name' => $name,
            ]);
        }
        
        // Update phone
        if (isset($_POST['phone'])) {
            $phone = preg_replace('/[^0-9]/', '', sanitize_text_field(wp_unslash($_POST['phone'])));
            update_user_meta($user_id, '_hng_customer_phone', $phone);
        }
        
        // Update whatsapp
        if (isset($_POST['whatsapp'])) {
            $whatsapp = preg_replace('/[^0-9]/', '', sanitize_text_field(wp_unslash($_POST['whatsapp'])));
            update_user_meta($user_id, '_hng_customer_whatsapp', $whatsapp);
        }
        
        // Update CPF (provider)
        if (isset($_POST['cpf'])) {
            $cpf = preg_replace('/[^0-9]/', '', sanitize_text_field(wp_unslash($_POST['cpf'])));
            update_user_meta($user_id, '_hng_customer_cpf', $cpf);
        }
        
        // Update company fields
        $company_fields = ['company_name', 'cnpj', 'company_email', 'responsible_name', 'responsible_role'];
        foreach ($company_fields as $field) {
            if (isset($_POST[$field])) {
                $value = sanitize_text_field(wp_unslash($_POST[$field]));
                if ($field === 'cnpj') {
                    $value = preg_replace('/[^0-9]/', '', $value);
                }
                update_user_meta($user_id, '_hng_customer_' . $field, $value);
            }
        }
        
        wp_send_json_success([
            'message' => 'Perfil atualizado com sucesso!'
        ]);
    }
    
    /**
     * Update user address
     */
    public function ajax_update_address() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'hng_update_profile')) {
            wp_send_json_error(['message' => 'Sessão expirada. Recarregue a página.']);
            return;
        }
        
        // Must be logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Você precisa estar logado.']);
            return;
        }
        
        $user_id = get_current_user_id();
        
        // Address fields
        $address_fields = ['cep', 'address', 'number', 'complement', 'district', 'city', 'state'];
        foreach ($address_fields as $field) {
            if (isset($_POST[$field])) {
                $value = sanitize_text_field(wp_unslash($_POST[$field]));
                if ($field === 'cep') {
                    $value = preg_replace('/[^0-9]/', '', $value);
                }
                update_user_meta($user_id, '_hng_customer_' . $field, $value);
            }
        }
        
        wp_send_json_success([
            'message' => 'Endereço atualizado com sucesso!'
        ]);
    }
    
    /**
     * Update user password
     */
    public function ajax_update_password() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'hng_update_password')) {
            wp_send_json_error(['message' => 'Sessão expirada. Recarregue a página.']);
            return;
        }
        
        // Must be logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Você precisa estar logado.']);
            return;
        }
        
        $user_id = get_current_user_id();
        $user = get_user_by('id', $user_id);
        
        // Check if user has Google ID (can't change password)
        if (get_user_meta($user_id, '_hng_google_id', true)) {
            wp_send_json_error(['message' => 'Usuários com login Google devem alterar a senha no Google.']);
            return;
        }
        
        // Validate current password
        $current_password = isset($_POST['current_password']) ? sanitize_text_field(wp_unslash($_POST['current_password'])) : '';
        if (!wp_check_password($current_password, $user->user_pass, $user_id)) {
            wp_send_json_error(['message' => 'Senha atual incorreta.']);
            return;
        }
        
        // Validate new password
        $new_password = isset($_POST['new_password']) ? sanitize_text_field(wp_unslash($_POST['new_password'])) : '';
        $confirm_password = isset($_POST['confirm_password']) ? sanitize_text_field(wp_unslash($_POST['confirm_password'])) : '';
        
        if (strlen($new_password) < 8) {
            wp_send_json_error(['message' => 'A nova senha deve ter pelo menos 8 caracteres.']);
            return;
        }
        
        if ($new_password !== $confirm_password) {
            wp_send_json_error(['message' => 'As senhas não conferem.']);
            return;
        }
        
        // Update password
        wp_set_password($new_password, $user_id);
        
        // Re-login user
        wp_set_auth_cookie($user_id, true);
        
        wp_send_json_success([
            'message' => 'Senha alterada com sucesso!'
        ]);
    }
    
    /**
     * Export user data (LGPD)
     */
    public function ajax_export_data() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'hng_export_data')) {
            wp_send_json_error(['message' => 'Sessão expirada. Recarregue a página.']);
            return;
        }
        
        // Must be logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Você precisa estar logado.']);
            return;
        }
        
        $user_id = get_current_user_id();
        $user = get_user_by('id', $user_id);
        
        // Collect user data
        $user_data = [
            'informacoes_basicas' => [
                'nome' => $user->display_name,
                'email' => $user->user_email,
                'registrado_em' => $user->user_registered,
            ],
            'dados_cadastrais' => [],
            'metadados' => [],
        ];
        
        // Get all user meta with _hng_ prefix
        $all_meta = get_user_meta($user_id);
        foreach ($all_meta as $key => $values) {
            if (strpos($key, '_hng_') === 0) {
                $clean_key = str_replace('_hng_customer_', '', $key);
                $clean_key = str_replace('_hng_', '', $clean_key);
                $user_data['dados_cadastrais'][$clean_key] = $values[0];
            }
        }
        
        // Convert to JSON
        $json_data = wp_json_encode($user_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        // Send email with data
        $subject = 'Seus Dados - HNG Desenvolvimentos';
        $message = "Olá {$user->display_name},\n\n";
        $message .= "Conforme sua solicitação, segue abaixo uma cópia de todos os dados que armazenamos sobre você:\n\n";
        $message .= $json_data;
        $message .= "\n\nAtenciosamente,\nEquipe HNG Desenvolvimentos";
        
        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        
        $sent = wp_mail($user->user_email, $subject, $message, $headers);
        
        if ($sent) {
            wp_send_json_success([
                'message' => 'Seus dados foram enviados para seu e-mail!'
            ]);
        } else {
            wp_send_json_error(['message' => 'Erro ao enviar e-mail. Tente novamente.']);
        }
    }
    
    /**
     * Delete user account (LGPD)
     */
    public function ajax_delete_account() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'hng_delete_account')) {
            wp_send_json_error(['message' => 'Sessão expirada. Recarregue a página.']);
            return;
        }
        
        // Must be logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Você precisa estar logado.']);
            return;
        }
        
        // Confirm checkbox
        if (empty($_POST['confirm_delete'])) {
            wp_send_json_error(['message' => 'Você precisa confirmar a exclusão.']);
            return;
        }
        
        $user_id = get_current_user_id();
        $user = get_user_by('id', $user_id);
        
        // Check password for non-Google users
        $has_google = get_user_meta($user_id, '_hng_google_id', true);
        if (!$has_google) {
            $password = isset($_POST['password']) ? sanitize_text_field(wp_unslash($_POST['password'])) : '';
            if (!wp_check_password($password, $user->user_pass, $user_id)) {
                wp_send_json_error(['message' => 'Senha incorreta.']);
                return;
            }
        }
        
        // Don't allow admin deletion
        if (user_can($user_id, 'manage_options')) {
            wp_send_json_error(['message' => 'Administradores não podem excluir suas contas por aqui.']);
            return;
        }
        
        // Delete all user meta first
        $all_meta = get_user_meta($user_id);
        foreach (array_keys($all_meta) as $key) {
            if (strpos($key, '_hng_') === 0) {
                delete_user_meta($user_id, $key);
            }
        }
        
        // Log out user
        wp_logout();
        
        // Delete user (without reassigning posts - they're deleted too)
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($user_id);
        
        wp_send_json_success([
            'message' => 'Sua conta foi excluída com sucesso.',
            'redirect' => home_url()
        ]);
    }
    
    /**
     * Get account URL helper
     */
    public static function get_account_url() {
        return home_url('/minha-conta/');
    }
}

// Initialize
HNG_User_Profile::instance();

/**
 * Helper function to get account URL
 */
if (!function_exists('hng_get_account_url')) {
    function hng_get_account_url() {
        return HNG_User_Profile::get_account_url();
    }
}
