<?php
/**
 * HNG Commerce Setup Wizard
 * 
 * Assistente de configuração inicial do plugin
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Setup_Wizard {
    
    private static $instance = null;
    
    /**
     * Etapas do wizard
     */
    private $steps = [];
    
    /**
     * Etapa atual
     */
    private $current_step = '';
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Sempre registrar a página do wizard (para acesso manual)
        add_action('admin_menu', [$this, 'add_wizard_page']);
        
        // Redirecionar apenas se deve mostrar o wizard (verificar no admin_init quando WP está pronto)
        add_action('admin_init', [$this, 'maybe_redirect_to_wizard']);
        
        // AJAX handlers
        add_action('wp_ajax_hng_wizard_save_step', [$this, 'ajax_save_step']);
        add_action('wp_ajax_hng_wizard_skip', [$this, 'ajax_skip_wizard']);
        add_action('wp_ajax_hng_wizard_complete', [$this, 'ajax_complete_wizard']);
        add_action('wp_ajax_hng_wizard_restart', [$this, 'ajax_restart_wizard']);
        
        // Processar reset via URL
        add_action('admin_init', [$this, 'handle_restart_wizard']);
    }
    
    /**
     * Verifica se deve mostrar o wizard
     */
    private function should_show_wizard() {
        // Verificar se as funções do WordPress estão disponíveis
        if (!function_exists('current_user_can')) {
            return false;
        }
        
        // Não mostrar se já foi completado ou pulado
        if (get_option('hng_setup_wizard_completed') || get_option('hng_setup_wizard_skipped')) {
            return false;
        }
        
        // Mostrar apenas para admins
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Redireciona para o wizard após ativação
     */
    public function maybe_redirect_to_wizard() {
        // Verificar se deve mostrar o wizard
        if (!$this->should_show_wizard()) {
            return;
        }
        
        if (!get_transient('hng_commerce_activation_redirect')) {
            return;
        }
        
        delete_transient('hng_commerce_activation_redirect');
        
        // Não redirecionar em ativação em massa ou AJAX
        if (wp_doing_ajax() || is_network_admin() || isset($_GET['activate-multi'])) {
            return;
        }
        
        // Redirecionar para o wizard
        wp_safe_redirect(admin_url('admin.php?page=hng-setup-wizard'));
        exit;
    }
    
    /**
     * Adiciona página do wizard (oculta no menu)
     */
    public function add_wizard_page() {
        add_submenu_page(
            null, // Parent slug null = página oculta
            __('Configuração HNG Commerce', 'hng-commerce'),
            __('Setup Wizard', 'hng-commerce'),
            'manage_options',
            'hng-setup-wizard',
            [$this, 'render_wizard']
        );
    }
    
    /**
     * Define as etapas do wizard
     */
    private function get_steps() {
        return [
            'welcome' => [
                'name' => __('Bem-vindo', 'hng-commerce'),
                'icon' => 'dashicons-smiley',
            ],
            'store' => [
                'name' => __('Sua Loja', 'hng-commerce'),
                'icon' => 'dashicons-store',
            ],
            'gateways' => [
                'name' => __('Gateways de Pagamento', 'hng-commerce'),
                'icon' => 'dashicons-credit',
            ],
            'products' => [
                'name' => __('Produtos', 'hng-commerce'),
                'icon' => 'dashicons-products',
            ],
            'payments' => [
                'name' => __('Pagamentos', 'hng-commerce'),
                'icon' => 'dashicons-money-alt',
            ],
            'shipping' => [
                'name' => __('Frete', 'hng-commerce'),
                'icon' => 'dashicons-truck',
            ],
            'ready' => [
                'name' => __('Pronto!', 'hng-commerce'),
                'icon' => 'dashicons-yes-alt',
            ],
        ];
    }
    
    /**
     * Renderiza o wizard
     */
    public function render_wizard() {
        $this->steps = $this->get_steps();
        $this->current_step = isset($_GET['step']) ? sanitize_key($_GET['step']) : 'welcome';
        
        if (!isset($this->steps[$this->current_step])) {
            $this->current_step = 'welcome';
        }
        
        // Enqueue assets
        $this->enqueue_assets();
        
        // Render
        include HNG_COMMERCE_PATH . 'templates/admin-setup-wizard.php';
    }
    
    /**
     * Enqueue CSS e JS do wizard
     */
    private function enqueue_assets() {
        wp_enqueue_style(
            'hng-setup-wizard',
            HNG_COMMERCE_URL . 'assets/css/setup-wizard.css',
            [],
            HNG_COMMERCE_VERSION
        );
        
        wp_enqueue_script(
            'hng-setup-wizard',
            HNG_COMMERCE_URL . 'assets/js/setup-wizard.js',
            ['jquery'],
            HNG_COMMERCE_VERSION,
            true
        );
        
        wp_localize_script('hng-setup-wizard', 'hng_wizard', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('hng_setup_wizard'),
            'admin_url' => admin_url('admin.php?page=hng-commerce'),
            'strings' => [
                'saving' => __('Salvando...', 'hng-commerce'),
                'saved' => __('Salvo!', 'hng-commerce'),
                'error' => __('Erro ao salvar. Tente novamente.', 'hng-commerce'),
                'confirmSkip' => __('Tem certeza que deseja pular a configuração? Você pode configurar manualmente depois.', 'hng-commerce'),
            ],
        ]);
    }
    
    /**
     * AJAX: Salvar etapa
     */
    public function ajax_save_step() {
        check_ajax_referer('hng_setup_wizard', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Sem permissão.', 'hng-commerce')]);
        }
        
        $step = sanitize_key($_POST['step'] ?? '');
        $data = $_POST['data'] ?? [];
        
        $result = $this->save_step_data($step, $data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        // Determinar próxima etapa
        $steps = array_keys($this->get_steps());
        $current_index = array_search($step, $steps);
        $next_step = isset($steps[$current_index + 1]) ? $steps[$current_index + 1] : 'ready';
        $next_url = admin_url('admin.php?page=hng-setup-wizard&step=' . $next_step);
        
        wp_send_json_success([
            'message' => __('Configurações salvas!', 'hng-commerce'),
            'next_url' => $next_url,
        ]);
    }
    
    /**
     * Salva dados de uma etapa
     */
    private function save_step_data($step, $data) {
        switch ($step) {
            case 'store':
                // Dados da loja
                if (!empty($data['store_name'])) {
                    update_option('hng_store_name', sanitize_text_field($data['store_name']));
                }
                if (!empty($data['store_email'])) {
                    update_option('hng_store_email', sanitize_email($data['store_email']));
                }
                if (!empty($data['store_phone'])) {
                    update_option('hng_store_phone', sanitize_text_field($data['store_phone']));
                }
                if (!empty($data['store_cnpj'])) {
                    update_option('hng_store_cnpj', sanitize_text_field($data['store_cnpj']));
                }
                if (!empty($data['store_address'])) {
                    update_option('hng_store_address', sanitize_text_field($data['store_address']));
                }
                if (!empty($data['store_number'])) {
                    update_option('hng_store_number', sanitize_text_field($data['store_number']));
                }
                if (!empty($data['store_district'])) {
                    update_option('hng_store_district', sanitize_text_field($data['store_district']));
                }
                if (!empty($data['store_city'])) {
                    update_option('hng_store_city', sanitize_text_field($data['store_city']));
                }
                if (!empty($data['store_state'])) {
                    update_option('hng_store_state', sanitize_text_field($data['store_state']));
                }
                if (!empty($data['store_zipcode'])) {
                    update_option('hng_store_zipcode', sanitize_text_field($data['store_zipcode']));
                }
                
                // Também salvar como dados do remetente para frete
                if (!empty($data['store_name'])) {
                    update_option('hng_shipping_correios_remetente_nome', sanitize_text_field($data['store_name']));
                }
                if (!empty($data['store_address'])) {
                    update_option('hng_shipping_correios_remetente_logradouro', sanitize_text_field($data['store_address']));
                }
                if (!empty($data['store_number'])) {
                    update_option('hng_shipping_correios_remetente_numero', sanitize_text_field($data['store_number']));
                }
                if (!empty($data['store_district'])) {
                    update_option('hng_shipping_correios_remetente_bairro', sanitize_text_field($data['store_district']));
                }
                if (!empty($data['store_city'])) {
                    update_option('hng_shipping_correios_remetente_cidade', sanitize_text_field($data['store_city']));
                }
                if (!empty($data['store_state'])) {
                    update_option('hng_shipping_correios_remetente_uf', sanitize_text_field($data['store_state']));
                }
                if (!empty($data['store_phone'])) {
                    update_option('hng_shipping_correios_remetente_telefone', sanitize_text_field($data['store_phone']));
                }
                break;
            
            case 'gateways':
                // Preferência de usar gateways HNG (padrão: não usar até o usuário escolher)
                $use_hng_gateways = isset($data['use_hng_gateways']) ? (bool) $data['use_hng_gateways'] : false;
                update_option('hng_use_hng_gateways', $use_hng_gateways);
                
                if ($use_hng_gateways) {
                    // Termos devem ser aceitos para usar gateways nativos
                    $terms_accept = isset($data['hng_terms_accept']) && $data['hng_terms_accept'] === 'yes';
                    if (!$terms_accept) {
                        return new WP_Error('terms_not_accepted', __('Para usar os gateways nativos é necessário aceitar os termos, taxas e modo de integração.', 'hng-commerce'));
                    }

                    update_option('hng_gateway_terms_accepted', true);
                    // Configurar automaticamente a conexão com a API
                    update_option('hng_api_url', 'https://api.hngdesenvolvimentos.com.br/');
                    $api_key = get_option('hng_api_key');
                    if (empty($api_key)) {
                        $api_key = bin2hex(random_bytes(32));
                        update_option('hng_api_key', $api_key);
                    }
                    update_option('hng_connected', true);
                    update_option('hng_api_heartbeat_last', time());
                } else {
                    // Desabilitar gateways HNG (respeitar compliance do WordPress.org)
                    update_option('hng_connected', false);
                    delete_option('hng_api_key');
                    update_option('hng_gateway_terms_accepted', false);
                }
                break;
            
            case 'products':
                // Tipos de produto selecionados
                $product_types = isset($data['product_types']) ? (array) $data['product_types'] : [];
                
                // Obter configurações existentes
                $options = get_option('hng_commerce_settings', []);
                
                // Definir todos os tipos de produto
                $all_types = ['simple', 'variable', 'digital', 'subscription', 'quote', 'appointment'];
                foreach ($all_types as $type) {
                    $enabled = in_array($type, $product_types) ? 'yes' : 'no';
                    $options['product_type_' . $type . '_enabled'] = $enabled;
                }
                
                // Simples sempre habilitado
                $options['product_type_simple_enabled'] = 'yes';
                
                update_option('hng_commerce_settings', $options);
                break;
                
            case 'payments':
                // Gateways selecionados
                $gateways = isset($data['gateways']) ? (array) $data['gateways'] : [];
                
                // Habilitar/desabilitar gateways
                $all_gateways = ['pix', 'boleto', 'credit_card', 'asaas', 'pagseguro', 'mercadopago'];
                foreach ($all_gateways as $gateway) {
                    $enabled = in_array($gateway, $gateways) ? 'yes' : 'no';
                    update_option("hng_gateway_{$gateway}_enabled", $enabled);
                }
                
                // Chave PIX
                if (!empty($data['pix_key'])) {
                    update_option('hng_gateway_pix_key', sanitize_text_field($data['pix_key']));
                }
                if (!empty($data['pix_key_type'])) {
                    update_option('hng_gateway_pix_key_type', sanitize_text_field($data['pix_key_type']));
                }
                if (!empty($data['pix_holder_name'])) {
                    update_option('hng_gateway_pix_holder_name', sanitize_text_field($data['pix_holder_name']));
                }
                
                // Parcelamento PIX
                $pix_installment_enabled = isset($data['pix_installment_enabled']) && $data['pix_installment_enabled'] === 'yes' ? 'yes' : 'no';
                $options = get_option('hng_commerce_settings', []);
                $options['pix_installment_enabled'] = $pix_installment_enabled;
                
                if (!empty($data['pix_installment_max'])) {
                    $options['pix_installment_max'] = max(2, min(12, absint($data['pix_installment_max'])));
                }
                if (!empty($data['pix_installment_min_value'])) {
                    $options['pix_installment_min_value'] = floatval($data['pix_installment_min_value']);
                }
                
                update_option('hng_commerce_settings', $options);
                break;
                
            case 'shipping':
                // Métodos de frete
                $methods = isset($data['methods']) ? (array) $data['methods'] : [];
                
                // CEP de origem (obrigatório para todos)
                if (!empty($data['origin_zipcode'])) {
                    $origin_zip = sanitize_text_field($data['origin_zipcode']);
                    update_option('hng_shipping_correios_origin_zipcode', $origin_zip);
                    update_option('hng_shipping_jadlog_origin_zipcode', $origin_zip);
                    update_option('hng_shipping_melhorenvio_origin_zipcode', $origin_zip);
                    update_option('hng_shipping_total_express_origin_zipcode', $origin_zip);
                }
                
                // Habilitar/desabilitar métodos
                $all_methods = ['correios', 'jadlog', 'melhorenvio', 'total_express'];
                foreach ($all_methods as $method) {
                    $enabled = in_array($method, $methods) ? 'yes' : 'no';
                    update_option("hng_shipping_{$method}_enabled", $enabled);
                }
                
                // Configurar serviços padrão
                if (in_array('correios', $methods)) {
                    update_option('hng_shipping_correios_services', ['04014', '04510']);
                }
                break;
        }
        
        return true;
    }
    
    /**
     * AJAX: Pular wizard
     */
    public function ajax_skip_wizard() {
        check_ajax_referer('hng_setup_wizard', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Sem permissão.', 'hng-commerce')]);
        }
        
        update_option('hng_setup_wizard_skipped', true);
        update_option('hng_setup_wizard_skipped_at', current_time('mysql'));
        
        wp_send_json_success(['redirect_url' => admin_url('admin.php?page=hng-commerce')]);
    }
    
    /**
     * AJAX: Completar wizard
     */
    public function ajax_complete_wizard() {
        check_ajax_referer('hng_setup_wizard', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Sem permissão.', 'hng-commerce')]);
        }
        
        update_option('hng_setup_wizard_completed', true);
        update_option('hng_setup_wizard_completed_at', current_time('mysql'));
        
        wp_send_json_success(['redirect_url' => admin_url('admin.php?page=hng-commerce')]);
    }
    
    /**
     * Retorna dados salvos da loja
     */
    public static function get_store_data() {
        return [
            'store_name' => get_option('hng_store_name', get_bloginfo('name')),
            'store_email' => get_option('hng_store_email', get_option('admin_email')),
            'store_phone' => get_option('hng_store_phone', ''),
            'store_cnpj' => get_option('hng_store_cnpj', ''),
            'store_address' => get_option('hng_store_address', ''),
            'store_number' => get_option('hng_store_number', ''),
            'store_district' => get_option('hng_store_district', ''),
            'store_city' => get_option('hng_store_city', ''),
            'store_state' => get_option('hng_store_state', ''),
            'store_zipcode' => get_option('hng_store_zipcode', ''),
        ];
    }
    
    /**
     * Retorna se está usando gateways HNG
     */
    public static function is_using_hng_gateways() {
        return (bool) get_option('hng_use_hng_gateways', true); // Default true para backward compatibility
    }
    
    /**
     * Verifica se uma configuração específica está completa
     */
    public static function is_step_complete($step) {
        switch ($step) {
            case 'store':
                $data = self::get_store_data();
                return !empty($data['store_name']) && !empty($data['store_zipcode']);
            
            case 'gateways':
                // Etapa de gateways sempre é completa após salvar
                return true;            case 'products':
                // Verifica se pelo menos um tipo de produto está ativo
                $options = get_option('hng_commerce_settings', []);
                return !empty($options['product_type_simple_enabled']) && $options['product_type_simple_enabled'] === 'yes';
                
            case 'payments':
                // Verifica se pelo menos um gateway está ativo
                $gateways = ['pix', 'boleto', 'credit_card', 'asaas', 'pagseguro', 'mercadopago'];
                foreach ($gateways as $gateway) {
                    if (get_option("hng_gateway_{$gateway}_enabled") === 'yes') {
                        return true;
                    }
                }
                return false;
                
            case 'shipping':
                // Verifica se pelo menos um método está ativo com CEP configurado
                $methods = ['correios', 'jadlog', 'melhorenvio'];
                foreach ($methods as $method) {
                    if (get_option("hng_shipping_{$method}_enabled") === 'yes') {
                        $zip = get_option("hng_shipping_{$method}_origin_zipcode");
                        if (!empty($zip)) {
                            return true;
                        }
                    }
                }
                return false;
        }
        
        return false;
    }
    
    /**
     * Processar restart do wizard via URL
     */
    public function handle_restart_wizard() {
        if (!isset($_GET['hng_restart_wizard']) || $_GET['hng_restart_wizard'] !== '1') {
            return;
        }
        
        $restart_nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if (!wp_verify_nonce($restart_nonce, 'hng_restart_wizard')) {
            wp_die(esc_html__('Link inválido ou expirado.', 'hng-commerce'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Sem permissão.', 'hng-commerce'));
        }
        
        // Resetar flags do wizard
        delete_option('hng_setup_wizard_completed');
        delete_option('hng_setup_wizard_completed_at');
        delete_option('hng_setup_wizard_skipped');
        delete_option('hng_setup_wizard_skipped_at');
        
        // Redirecionar para o wizard
        wp_safe_redirect(admin_url('admin.php?page=hng-setup-wizard'));
        exit;
    }
    
    /**
     * AJAX: Reiniciar wizard
     */
    public function ajax_restart_wizard() {
        check_ajax_referer('hng_setup_wizard', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Sem permissão.', 'hng-commerce')]);
        }
        
        // Resetar flags do wizard
        delete_option('hng_setup_wizard_completed');
        delete_option('hng_setup_wizard_completed_at');
        delete_option('hng_setup_wizard_skipped');
        delete_option('hng_setup_wizard_skipped_at');
        
        wp_send_json_success(['redirect_url' => admin_url('admin.php?page=hng-setup-wizard')]);
    }
    
    /**
     * Gera link para reiniciar o wizard
     */
    public static function get_restart_url() {
        return wp_nonce_url(
            admin_url('admin.php?hng_restart_wizard=1'),
            'hng_restart_wizard'
        );
    }
}