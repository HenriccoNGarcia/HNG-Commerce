<?php
/**
 * Página Admin: Conectar Conta HNG
 * Registro de merchant no servidor central
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Merchant_Registration {
    
    /**
     * Construtor
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu'], 20);
        add_action('admin_notices', [$this, 'show_connection_notice']);
        add_action('admin_post_hng_register_merchant', [$this, 'handle_registration']);
        add_action('admin_post_nopriv_hng_register_merchant', [$this, 'handle_registration']);
        add_action('admin_post_hng_disconnect_merchant', [$this, 'handle_disconnection']);
        add_action('admin_post_hng_regenerate_api_key', [$this, 'handle_regenerate']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }
    
    /**
     * Registrar menu
     */
    public function register_menu() {
        add_submenu_page(
            'HNG Commerce',
            __('Conectar Conta', 'hng-commerce'),
            __('Conectar Conta', 'hng-commerce'),
            'read',
            'hng-merchant-registration',
            [$this, 'render_page']
        );
    }
    
    /**
    * Mostrar aviso se não conectado
     */
    public function show_connection_notice() {
        // Já está conectado?
        if ($this->is_connected()) {
            return;
        }
        
        // Ignorar em certas páginas
        $screen = get_current_screen();
        if ($screen && $screen->id === 'hng-commerce_page_hng-merchant-registration') {
            return;
        }
        
        // Usuário pode ignorar temporariamente
        if (get_transient('hng_dismiss_connection_notice')) {
            return;
        }
        
        ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php esc_html_e('HNG Commerce:', 'hng-commerce'); ?></strong>
                <?php esc_html_e('Conecte sua loja ao servidor central para processar pagamentos com segurança.', 'hng-commerce'); ?>
            </p>
            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=hng-merchant-registration')); ?>" class="button button-primary">
                    <?php esc_html_e('Conectar Agora', 'hng-commerce'); ?>
                </a>
                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=hng-commerce&hng_dismiss_notice=1'), 'hng_dismiss')); ?>" class="button button-secondary">
                    <?php esc_html_e('Lembrar Depois', 'hng-commerce'); ?>
                </a>
            </p>
        </div>
        <?php
    }
    
    /**
    * Verificar se está conectado
     */
    private function is_connected() {
        $merchant_id = get_option('hng_merchant_id');
        $api_key = get_option('hng_api_key');
        
        return !empty($merchant_id) && !empty($api_key);
    }
    
    /**
     * Carregar scripts
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'hng-commerce_page_hng-merchant-registration') {
            return;
        }
        
        wp_enqueue_style('hng-merchant-registration', HNG_COMMERCE_URL . 'assets/css/merchant-registration.css', [], HNG_COMMERCE_VERSION);
    }
    
    /**
    * Renderizar página
     */
    public function render_page() {
        // Desativar Elementor nesta página admin
        add_filter('elementor/admin/localize_settings', array($this, 'disable_elementor_for_this_page'));
        
        ?>
        <div class="wrap hng-merchant-registration">
            <h1><?php esc_html_e('Conectar Loja ao HNG Commerce', 'hng-commerce'); ?></h1>
            
            <?php if ($this->is_connected()): ?>
                <?php $this->render_connected_status(); ?>
            <?php else: ?>
                <?php $this->render_registration_form(); ?>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Renderizar status conectado
     */
    private function render_connected_status() {
        $merchant_id = get_option('hng_merchant_id');
        $status = get_option('hng_merchant_status', 'active');
        $tier = get_option('hng_current_tier', 1);
        
        // Obter GMV da VPS
        $api_client = HNG_API_Client::instance();
        $gmv_stats = $api_client->get_gmv_stats();
        
        $gmv_current = 0;
        if (!is_wp_error($gmv_stats)) {
            $gmv_current = $gmv_stats['gmv_current_month'] ?? 0;
        }
        
        // Nomes dos tiers
        $tier_names = [
            1 => __('Iniciante', 'hng-commerce'),
            2 => __('Crescendo', 'hng-commerce'),
            3 => __('Consolidado', 'hng-commerce'),
            4 => __('Enterprise', 'hng-commerce')
        ];
        
        // Status badges
        $status_colors = [
            'active' => 'success',
            'suspended' => 'warning',
            'banned' => 'error'
        ];
        
        $status_labels = [
            'active' => __('Ativo', 'hng-commerce'),
            'suspended' => __('Suspenso', 'hng-commerce'),
            'banned' => __('Banido', 'hng-commerce')
        ];
        
        ?>
        <div class="hng-connection-status">
            <div class="hng-status-card hng-status-<?php echo esc_attr($status_colors[$status]); ?>">
                <div class="hng-status-header">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <h2><?php esc_html_e('Loja Conectada', 'hng-commerce'); ?></h2>
                </div>
                
                <div class="hng-status-body">
                    <div class="hng-status-row">
                        <span class="hng-label"><?php esc_html_e('Merchant ID:', 'hng-commerce'); ?></span>
                        <span class="hng-value"><code><?php echo esc_html($merchant_id); ?></code></span>
                    </div>
                    
                    <div class="hng-status-row">
                        <span class="hng-label"><?php esc_html_e('Status:', 'hng-commerce'); ?></span>
                        <span class="hng-badge hng-badge-<?php echo esc_attr($status_colors[$status]); ?>">
                            <?php echo esc_html($status_labels[$status]); ?>
                        </span>
                    </div>
                    
                    <div class="hng-status-row">
                        <span class="hng-label"><?php esc_html_e('Tier Atual:', 'hng-commerce'); ?></span>
                        <span class="hng-value">
                            <strong><?php echo esc_html($tier_names[$tier]); ?></strong> (Tier <?php echo esc_html($tier); ?>)
                        </span>
                    </div>
                    
                    <div class="hng-status-row">
                        <span class="hng-label"><?php esc_html_e('GMV do Mês:', 'hng-commerce'); ?></span>
                        <span class="hng-value">
                            <strong>R$ <?php echo esc_html(number_format($gmv_current, 2, ',', '.')); ?></strong>
                        </span>
                    </div>
                </div>
                
                <div class="hng-status-actions">
                    <button type="button" class="button button-secondary" id="hng-test-connection">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e('Testar Conexão', 'hng-commerce'); ?>
                    </button>
                    
                    <button type="button" class="button button-link-delete" id="hng-disconnect">
                        <?php esc_html_e('Desconectar', 'hng-commerce'); ?>
                    </button>
            
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline; margin-left:10px;">
                        <?php
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_nonce_field() outputs safely escaped HTML
                        echo wp_nonce_field('hng_regenerate_api_key_nonce', '_hng_regenerate_nonce', true, false);
                        ?>
                        <input type="hidden" name="action" value="hng_regenerate_api_key">
                        <button type="submit" class="button button-secondary"><?php esc_html_e('Regenerar API Key', 'hng-commerce'); ?></button>
                    </form>
                </div>
                
                <div id="hng-connection-result"></div>
            </div>
            
            <?php if ($status === 'banned'): ?>
                <div class="notice notice-error">
                    <p>
                        <strong><?php esc_html_e('Sua conta foi suspensa.', 'hng-commerce'); ?></strong><br>
                        <?php esc_html_e('Entre em contato com o suporte para resolver este problema:', 'hng-commerce'); ?>
                        <a href="mailto:suporte@hngplugins.com">suporte@hngplugins.com</a>
                    </p>
                </div>
            <?php endif; ?>
        </div>
        <?php
        // Merchant registration handlers movidos para assets/js/admin.js
        // Usar data-nonce nos botões #hng-test-connection e #hng-disconnect
        ?>
        <script type="application/json" id="hng-merchant-data">
        <?php echo wp_json_encode([
            'testNonce' => wp_create_nonce('hng-test-connection'),
            'disconnectUrl' => wp_nonce_url(admin_url('admin-post.php?action=hng_disconnect_merchant'), 'hng-disconnect'),
            'i18n' => [
                'testError' => __('Erro ao testar conexão.', 'hng-commerce'),
                'disconnectConfirm' => __('Desconectar sua loja? Você não poderá processar pagamentos.', 'hng-commerce')
            ]
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>
        </script>
        <?php
    }
    
    /**
    * Renderizar formulário de registro
     */
    private function render_registration_form() {
        $current_user = wp_get_current_user();
        
        ?>
        <div class="hng-registration-form">
            <div class="hng-intro">
                <p><?php esc_html_e('Conecte sua loja ao servidor central HNG Commerce para processar pagamentos com segurança e transparência.', 'hng-commerce'); ?></p>
                
                <h3><?php esc_html_e('Benefícios:', 'hng-commerce'); ?></h3>
                <ul>
                    <li><?php esc_html_e('• Cálculo de taxas 100% seguro (não pode ser adulterado)', 'hng-commerce'); ?></li>
                    <li><?php esc_html_e('• Sistema de tiers automático (quanto mais vende, menos paga)', 'hng-commerce'); ?></li>
                    <li><?php esc_html_e('• Atualizações de segurança em tempo real', 'hng-commerce'); ?></li>
                    <li><?php esc_html_e('• Suporte técnico prioritário', 'hng-commerce'); ?></li>
                </ul>
            </div>
            
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_nonce_field() outputs safely escaped HTML
                wp_nonce_field('hng-merchant-registration', 'hng_nonce');
                ?>
                <input type="hidden" name="action" value="hng_register_merchant">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="site_url"><?php esc_html_e('URL do Site', 'hng-commerce'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="site_url" name="site_url" value="<?php echo esc_attr(home_url()); ?>" class="regular-text" readonly>
                            <p class="description"><?php esc_html_e('URL detectada automaticamente.', 'hng-commerce'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="email"><?php esc_html_e('Email', 'hng-commerce'); ?> *</label>
                        </th>
                        <td>
                            <input type="email" id="email" name="email" value="<?php echo esc_attr($current_user->user_email); ?>" class="regular-text" required>
                            <p class="description"><?php esc_html_e('Email para notificações e recuperação de conta.', 'hng-commerce'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="phone"><?php esc_html_e('Telefone', 'hng-commerce'); ?></label>
                        </th>
                        <td>
                            <input type="tel" id="phone" name="phone" value="" class="regular-text" placeholder="(11) 98765-4321">
                            <p class="description"><?php esc_html_e('Opcional. Para contato em caso de problemas críticos.', 'hng-commerce'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="terms"><?php esc_html_e('Termos de Uso', 'hng-commerce'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="terms" name="terms" value="1" required>
                                <?php 
                                /* translators: %1$s: Terms of Use URL, %2$s: Privacy Policy URL */
                                printf(esc_html__('Li e aceito os <a href="%1$s" target="_blank">Termos de Uso</a> e <a href="%2$s" target="_blank">Política de Privacidade</a>.', 'hng-commerce'),
                                    'https://hngplugins.com/termos',
                                    'https://hngplugins.com/privacidade'
                                ); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary button-hero">
                        <?php esc_html_e('Conectar Minha Loja', 'hng-commerce'); ?>
                    </button>
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
    * Processar registro
     */
    public function handle_registration() {
        check_admin_referer('hng-merchant-registration', 'hng_nonce');
        
        // Verificar se usuário está logado (não precisa ser admin)
        if (!is_user_logged_in()) {
            wp_die(esc_html__('Você precisa estar logado para conectar sua loja.', 'hng-commerce'));
        }
        
        $post = function_exists('wp_unslash') ? wp_unslash($_POST) : $_POST;
        $email = sanitize_email($post['email'] ?? '');
        $phone = sanitize_text_field($post['phone'] ?? '');
        
        if (!is_email($email)) {
            wp_die(esc_html__('Email inválido.', 'hng-commerce'));
        }
        
        // Chamar API de registro
        $api_client = HNG_API_Client::instance();
        $result = $api_client->register_merchant([
            'email' => $email,
            'phone' => $phone
        ]);
        
        if (is_wp_error($result)) {
            wp_die(sprintf(
                /* translators: %s: Error message */
                esc_html__('Erro ao conectar: %s', 'hng-commerce'),
                esc_html($result->get_error_message())
            ));
        }
        
        // Sucesso!
        // Sucesso!
        wp_safe_redirect(admin_url('admin.php?page=hng-commerce&registered=1'));
        exit;
    }
    
    /**
    * Processar desconexão
     */
    public function handle_disconnection() {
        check_admin_referer('hng-disconnect');
        
        // Apenas admins podem desconectar
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Apenas administradores podem desconectar a loja.', 'hng-commerce'));
        }
        
        // Remover credenciais
        delete_option('hng_merchant_id');
        delete_option('hng_api_key');
        delete_option('hng_merchant_status');
        delete_option('hng_current_tier');
        
        // Redirecionar
        wp_safe_redirect(admin_url('admin.php?page=hng-merchant-registration&disconnected=1'));
        exit;
    }

    /**
     * Handler: Regenerar API Key
     */
    public function handle_regenerate() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Apenas administradores podem regenerar a API Key.', 'hng-commerce'));
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified 2 lines below with wp_verify_nonce()
        $post = function_exists('wp_unslash') ? wp_unslash($_POST) : $_POST;
        $regen_nonce = isset($post['_hng_regenerate_nonce']) ? sanitize_text_field($post['_hng_regenerate_nonce']) : '';
        if (!$regen_nonce || !wp_verify_nonce($regen_nonce, 'hng_regenerate_api_key_nonce')) {
            wp_die(esc_html__('Nonce inválido.', 'hng-commerce'));
        }

        $api_client = HNG_API_Client::instance();
        $result = $api_client->regenerate_api_key();

        if (is_wp_error($result)) {
            wp_die(sprintf(
                /* translators: %s: Error message */
                esc_html__('Erro ao regenerar API Key: %s', 'hng-commerce'),
                esc_html($result->get_error_message())
            ));
        }

        if (!empty($result['api_key'])) {
            // Salvar nova chave localmente (criptografada)
            update_option('hng_api_key', HNG_Crypto::instance()->encrypt($result['api_key']));
            wp_safe_redirect(admin_url('admin.php?page=hng-merchant-registration&regenerated=1'));
            exit;
        }

        wp_die(esc_html__('Erro inesperado ao regenerar a API Key.', 'hng-commerce'));
    }
    
    /**
    * Desativar Elementor nesta página admin
     */
    public function disable_elementor_for_this_page($settings) {
        // Remove this page from Elementor's scope
        return $settings;
    }
}

new HNG_Merchant_Registration();
