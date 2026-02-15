<?php
/**
 * HNG Admin bootstrap (menus, pages, settings, meta boxes)
 *
 * Rebuilt after merge corruption: keeps singleton + menu routing and delegates
 * rendering to dedicated page classes.
 *
 * @package HNG_Commerce
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load AJAX handlers
if (is_admin() && file_exists(HNG_COMMERCE_PATH . 'includes/admin/ajax-asaas-sync.php')) {
    require_once HNG_COMMERCE_PATH . 'includes/admin/ajax-asaas-sync.php';
}
if (is_admin() && file_exists(HNG_COMMERCE_PATH . 'includes/admin/ajax-pagseguro-sync.php')) {
    require_once HNG_COMMERCE_PATH . 'includes/admin/ajax-pagseguro-sync.php';
}
if (is_admin() && file_exists(HNG_COMMERCE_PATH . 'includes/admin/ajax-customers-management.php')) {
    require_once HNG_COMMERCE_PATH . 'includes/admin/ajax-customers-management.php';
}

// Ensure admin modules are loaded
if (file_exists(HNG_COMMERCE_PATH . 'includes/admin/settings/class-admin-settings.php')) {
    require_once HNG_COMMERCE_PATH . 'includes/admin/settings/class-admin-settings.php';
}
if (file_exists(HNG_COMMERCE_PATH . 'includes/admin/meta-boxes/class-meta-boxes-manager.php')) {
    require_once HNG_COMMERCE_PATH . 'includes/admin/meta-boxes/class-meta-boxes-manager.php';
}

// Load admin pages
$hng_admin_pages = [
    'class-analytics-hub-page.php',
    'class-appointments-page.php',
    'class-customers-page.php',
    'class-email-customizer-page.php',
    'class-email-global-settings.php',
    'class-feedback-page.php',
    'class-financial-dashboard-page.php',
    'class-gateway-management-page.php',
    'class-hng-asaas-data-page.php',
    'class-professionals-page.php',
    'class-shipping-page.php',
    'class-orders-page.php',
    'class-reports-page.php',
    'class-subscriptions-page.php',
    'class-tools-page.php',
];
foreach ($hng_admin_pages as $page_file) {
    $page_path = __DIR__ . '/pages/' . $page_file;
    if (file_exists($page_path)) {
        require_once $page_path;
    }
}

/**
 * HNG Admin
 */
class HNG_Admin {
    /** @var HNG_Admin */
    private static $instance = null;

    /** @var HNG_Admin_Settings|null */
    private $settings_manager;

    /** @var HNG_Admin_Meta_Boxes|null */
    private $meta_boxes_manager;

    /** @var HNG_Email_Customizer_Page|null */
    private $email_customizer;

    /** @var HNG_Gateway_Management_Page|null */
    private $gateway_manager;

    /**
     * Singleton accessor
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor (private for singleton)
     */
    private function __construct() {
        $this->settings_manager = class_exists('HNG_Admin_Settings') ? HNG_Admin_Settings::instance() : null;
        $this->meta_boxes_manager = class_exists('HNG_Admin_Meta_Boxes') ? HNG_Admin_Meta_Boxes::instance() : null;

        if (class_exists('HNG_Email_Customizer_Page')) {
            $this->email_customizer = new HNG_Email_Customizer_Page();
        }
        if (class_exists('HNG_Gateway_Management_Page')) {
            $this->gateway_manager = new HNG_Gateway_Management_Page();
        }

        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_menu', [$this, 'reorder_submenus'], 999);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }
    
    /**
     * Reordenar submenus para colocar "Início" primeiro
     */
    public function reorder_submenus() {
        global $submenu;
        
        if (!isset($submenu['hng-commerce'])) {
            return;
        }
        
        $menu_items = $submenu['hng-commerce'];
        $inicio_item = null;
        $new_order = [];
        
        // Encontrar item "Início" e separar os outros
        foreach ($menu_items as $key => $item) {
            if ($item[2] === 'hng-commerce') {
                $inicio_item = $item;
            } else {
                $new_order[] = $item;
            }
        }
        
        // Colocar "Início" primeiro
        if ($inicio_item) {
            array_unshift($new_order, $inicio_item);
        }
        
        // Atualizar o submenu
        $submenu['hng-commerce'] = $new_order;
    }
    
    /**
     * Verificar se um tipo de produto está habilitado nas configurações
     *
     * @param string $type_key Chave do tipo (subscription, appointment, quote, etc.)
     * @return bool
     */
    private function is_product_type_enabled($type_key) {
        $options = get_option('hng_commerce_settings', []);
        
        // Verificar se já foi configurado alguma vez
        $has_product_type_settings = false;
        $types = ['simple', 'variable', 'digital', 'subscription', 'quote', 'appointment'];
        foreach ($types as $tk) {
            if (isset($options['product_type_' . $tk . '_enabled'])) {
                $has_product_type_settings = true;
                break;
            }
        }
        
        // Se nunca foi configurado, todos estão habilitados por padrão
        if (!$has_product_type_settings) {
            return true;
        }
        
        // Simple está sempre habilitado
        if ($type_key === 'simple') {
            return true;
        }
        
        return ($options['product_type_' . $type_key . '_enabled'] ?? 'no') === 'yes';
    }

    /**
     * Register admin menu and submenus
     */
    public function add_admin_menu() {
        // Verificar se wizard deve ser mostrado como página inicial
        $wizard_as_home = !get_option('hng_setup_wizard_completed') && !get_option('hng_setup_wizard_skipped');
        
        add_menu_page(
            __('HNG Commerce', 'hng-commerce'),
            __('HNG Commerce', 'hng-commerce'),
            'manage_options',
            'hng-commerce',
            $wizard_as_home ? [$this, 'setup_wizard_page'] : [$this, 'analytics_hub_page'],
            'dashicons-store',
            56
        );

        // Primeiro submenu: Início (substitui o padrão que seria "HNG Commerce")
        add_submenu_page('hng-commerce', __('Início', 'hng-commerce'), __('Início', 'hng-commerce'), 'manage_options', 'hng-commerce', $wizard_as_home ? [$this, 'setup_wizard_page'] : [$this, 'analytics_hub_page']);
        
        add_submenu_page('hng-commerce', __('Dashboard Financeiro', 'hng-commerce'), __('Financeiro', 'hng-commerce'), 'manage_options', 'hng-financial', [$this, 'financial_page_safe']);
        add_submenu_page('hng-commerce', __('Pedidos', 'hng-commerce'), __('Pedidos', 'hng-commerce'), 'manage_options', 'hng-orders', [$this, 'orders_page']);
        add_submenu_page('hng-commerce', __('Categorias', 'hng-commerce'), __('Categorias', 'hng-commerce'), 'manage_options', 'hng-product-categories', [$this, 'categories_page']);
        add_submenu_page('hng-commerce', __('Relatórios', 'hng-commerce'), __('Relatórios', 'hng-commerce'), 'manage_options', 'hng-reports', [$this, 'reports_page']);
        
        // Páginas condicionais baseadas nos tipos de produto habilitados
        if ($this->is_product_type_enabled('subscription')) {
            add_submenu_page('hng-commerce', __('Assinaturas', 'hng-commerce'), __('Assinaturas', 'hng-commerce'), 'manage_options', 'hng-subscriptions', [$this, 'subscriptions_page']);
        }
        add_submenu_page('hng-commerce', __('Clientes', 'hng-commerce'), __('Clientes', 'hng-commerce'), 'manage_options', 'hng-customers', [$this, 'customers_page']);
        if ($this->is_product_type_enabled('appointment')) {
            add_submenu_page('hng-commerce', __('Agendamentos', 'hng-commerce'), __('Agendamentos', 'hng-commerce'), 'manage_options', 'hng-appointments', [$this, 'appointments_page']);
            add_submenu_page('hng-commerce', __('Profissionais', 'hng-commerce'), __('Profissionais', 'hng-commerce'), 'manage_options', 'hng-professionals', [$this, 'professionals_page']);
        }
        add_submenu_page('hng-commerce', __('Frete', 'hng-commerce'), __('Frete', 'hng-commerce'), 'manage_options', 'hng-shipping', [$this, 'shipping_page']);
        add_submenu_page('hng-commerce', __('Ferramentas', 'hng-commerce'), __('Ferramentas', 'hng-commerce'), 'manage_options', 'hng-tools', [$this, 'tools_page']);
        if (get_option('hng_asaas_advanced_integration') === 'yes') {
            add_submenu_page('hng-commerce', __('Dados do Asaas', 'hng-commerce'), __('Dados do Asaas', 'hng-commerce'), 'manage_options', 'hng-asaas-data', [$this, 'asaas_data_page']);
        }
        if (get_option('hng_pagseguro_advanced_integration') === 'yes') {
            add_submenu_page('hng-commerce', __('Dados do PagSeguro', 'hng-commerce'), __('Dados do PagSeguro', 'hng-commerce'), 'manage_options', 'hng-pagseguro-data', [$this, 'pagseguro_data_page']);
        }
        if (get_option('hng_mercadopago_advanced_integration') === 'yes') {
            add_submenu_page('hng-commerce', __('Dados do Mercado Pago', 'hng-commerce'), __('Dados do Mercado Pago', 'hng-commerce'), 'manage_options', 'hng-mercadopago-data', [$this, 'mercado_pago_data_page']);
        }
        if (get_option('hng_pagarme_advanced_integration') === 'yes') {
            add_submenu_page('hng-commerce', __('Dados do Pagar.me', 'hng-commerce'), __('Dados do Pagar.me', 'hng-commerce'), 'manage_options', 'hng-pagarme-data', [$this, 'pagar_me_data_page']);
        }
        $wizard_completed = (bool) get_option('hng_setup_wizard_completed');
        $use_hng_gateways = (bool) get_option('hng_use_hng_gateways', false);

        if ($wizard_completed && $use_hng_gateways) {
            add_submenu_page('hng-commerce', __('Gateways', 'hng-commerce'), __('Gateways', 'hng-commerce'), 'manage_options', 'hng-gateways', [$this, 'gateways_page']);
        }
        add_submenu_page('hng-commerce', __('Emails', 'hng-commerce'), __('Emails', 'hng-commerce'), 'manage_options', 'hng-emails', [$this, 'emails_page']);
        add_submenu_page('hng-commerce', __('Configurações', 'hng-commerce'), __('Configurações', 'hng-commerce'), 'manage_options', 'hng-settings', [$this, 'settings_page']);
        add_submenu_page('hng-commerce', __('Feedback', 'hng-commerce'), __('Feedback', 'hng-commerce'), 'manage_options', 'hng-feedback', [$this, 'feedback_page']);
        
        // API & Segurança - Menu para conexão com API e auditoria (apenas se gateways nativos estiverem ativos e wizard concluído)
        if ($wizard_completed && $use_hng_gateways && class_exists('HNG_Admin_Connection_Page')) {
            add_submenu_page('hng-commerce', __('API & Segurança', 'hng-commerce'), __('API & Segurança', 'hng-commerce'), 'manage_options', 'hng-connection', ['HNG_Admin_Connection_Page', 'render']);
        }
        
        // Sempre adicionar link para reiniciar wizard (para admins)
        if (get_option('hng_setup_wizard_completed') || get_option('hng_setup_wizard_skipped')) {
            add_submenu_page('hng-commerce', __('Assistente de Configuração', 'hng-commerce'), __('Assistente', 'hng-commerce'), 'manage_options', 'hng-setup-wizard', [$this, 'setup_wizard_page']);
        }
    }
    
    /**
     * Setup Wizard page callback
     */
    public function setup_wizard_page() {
        if (class_exists('HNG_Setup_Wizard')) {
            HNG_Setup_Wizard::instance()->render_wizard();
        } else {
            echo '<div class="wrap"><h1>Setup Wizard</h1><p>Carregando...</p></div>';
        }
    }

    /**
     * Enqueue shared admin assets for HNG pages
     */
    public function enqueue_assets($hook) {
        if (strpos($hook, 'hng-commerce') === false && strpos($hook, 'hng_page_hng-') === false) {
            return;
        }

        wp_enqueue_style('hng-admin', HNG_COMMERCE_URL . 'assets/css/admin.css', [], HNG_COMMERCE_VERSION);
        wp_enqueue_style('hng-admin-components', HNG_COMMERCE_URL . 'assets/css/components.css', ['hng-admin'], HNG_COMMERCE_VERSION);
        wp_enqueue_style('hng-admin-dark', HNG_COMMERCE_URL . 'assets/css/dark-mode.css', ['hng-admin'], HNG_COMMERCE_VERSION);
        wp_enqueue_script('hng-admin', HNG_COMMERCE_URL . 'assets/js/admin.js', ['jquery'], HNG_COMMERCE_VERSION, true);

        // Localize admin nonce
        wp_localize_script('hng-admin', 'hngCommerceAdmin', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('hng-commerce-admin'),
            'paymentCheckNonce' => wp_create_nonce('hng_payment_check')
        ]);
    }

    /** Page callbacks */
    public function analytics_hub_page() {
        if (class_exists('HNG_Analytics_Hub_Page')) {
            return HNG_Analytics_Hub_Page::render();
        }
        $this->missing_page(__('Central de Análises indisponível.', 'hng-commerce'));
    }

    public function financial_page_safe() {
        if (class_exists('HNG_Financial_Dashboard_Page')) {
            return HNG_Financial_Dashboard_Page::render();
        }
        $this->missing_page(__('Dashboard Financeiro indisponível.', 'hng-commerce'));
    }

    public function customers_page() {
        if (class_exists('HNG_Admin_Customers_Page')) {
            return HNG_Admin_Customers_Page::render();
        }
        $this->missing_page(__('Página de clientes indisponível.', 'hng-commerce'));
    }

    public function appointments_page() {
        if (class_exists('HNG_Admin_Appointments_Page')) {
            return HNG_Admin_Appointments_Page::render();
        }
        $this->missing_page(__('Página de agendamentos indisponível.', 'hng-commerce'));
    }

    public function professionals_page() {
        if (class_exists('HNG_Admin_Professionals_Page')) {
            return HNG_Admin_Professionals_Page::render();
        }
        $this->missing_page(__('Página de profissionais indisponível.', 'hng-commerce'));
    }

    public function orders_page() {
        if (class_exists('HNG_Orders_Page')) {
            return HNG_Orders_Page::render();
        }
        $this->missing_page(__('Página de pedidos indisponível.', 'hng-commerce'));
    }

    public function categories_page() {
        if (function_exists('hng_product_categories_page')) {
            return hng_product_categories_page();
        }
        $this->missing_page(__('Página de categorias indisponível.', 'hng-commerce'));
    }

    public function reports_page() {
        if (class_exists('HNG_Reports_Page')) {
            return HNG_Reports_Page::render();
        }
        $this->missing_page(__('Página de relatórios indisponível.', 'hng-commerce'));
    }

    public function subscriptions_page() {
        if (class_exists('HNG_Subscriptions_Page')) {
            return HNG_Subscriptions_Page::render();
        }
        $this->missing_page(__('Página de assinaturas indisponível.', 'hng-commerce'));
    }

    public function shipping_page() {
        if (class_exists('HNG_Shipping_Page')) {
            return HNG_Shipping_Page::render();
        }
        $this->missing_page(__('Página de frete indisponível.', 'hng-commerce'));
    }

    public function tools_page() {
        if (class_exists('HNG_Tools_Page')) {
            return HNG_Tools_Page::render();
        }
        $this->missing_page(__('Ferramentas indisponíveis.', 'hng-commerce'));
    }

    public function asaas_data_page() {
        if (get_option('hng_asaas_advanced_integration') !== 'yes') {
            $this->missing_page(__('Integração avançada do Asaas desativada.', 'hng-commerce'));
            return;
        }
        if (class_exists('HNG_Asaas_Data_Page')) {
            return HNG_Asaas_Data_Page::render();
        }
        $this->missing_page(__('Dados do Asaas indisponíveis.', 'hng-commerce'));
    }

    public function pagseguro_data_page() {
        if (get_option('hng_pagseguro_advanced_integration') !== 'yes') {
            $this->missing_page(__('Integração avançada do PagSeguro desativada.', 'hng-commerce'));
            return;
        }
        if (class_exists('HNG_PagSeguro_Data_Page')) {
            return HNG_PagSeguro_Data_Page::render();
        }
        $this->missing_page(__('Dados do PagSeguro indisponíveis.', 'hng-commerce'));
    }

    public function mercado_pago_data_page() {
        if (get_option('hng_mercadopago_advanced_integration') !== 'yes') {
            $this->missing_page(__('Integração avançada do Mercado Pago desativada.', 'hng-commerce'));
            return;
        }
        if (class_exists('HNG_Mercado_Pago_Data_Page')) {
            return HNG_Mercado_Pago_Data_Page::render();
        }
        $this->missing_page(__('Dados do Mercado Pago indisponíveis.', 'hng-commerce'));
    }

    public function pagar_me_data_page() {
        if (get_option('hng_pagarme_advanced_integration') !== 'yes') {
            $this->missing_page(__('Integração avançada do Pagar.me desativada.', 'hng-commerce'));
            return;
        }
        if (class_exists('HNG_Pagar_Me_Data_Page')) {
            return HNG_Pagar_Me_Data_Page::render();
        }
        $this->missing_page(__('Dados do Pagar.me indisponíveis.', 'hng-commerce'));
    }

    public function gateways_page() {
        if (method_exists($this->gateway_manager, 'render')) {
            return $this->gateway_manager->render();
        }
        $this->missing_page(__('Gestão de gateways indisponível.', 'hng-commerce'));
    }

    public function emails_page() {
        if (class_exists('HNG_Email_Customizer_Page')) {
            return HNG_Email_Customizer_Page::render();
        }
        $this->missing_page(__('Personalização de emails indisponível.', 'hng-commerce'));
    }

    public function settings_page() {
        if ($this->settings_manager instanceof HNG_Admin_Settings) {
            return $this->settings_manager->render();
        }
        $this->missing_page(__('Configurações indisponíveis.', 'hng-commerce'));
    }

    /**
     * Fallback shipping page to satisfy routers
     */
    public function shipping_page_safe() {
        return $this->shipping_page();
    }

    /**
     * Generic missing-page notice
     */
    private function missing_page($message) {
        echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html($message) . '</p></div></div>';
    }

    /**
     * Feedback page callback
     */
    public function feedback_page() {
        if (class_exists('HNG_Feedback_Page')) {
            return HNG_Feedback_Page::render();
        }
        $this->missing_page(__('Página de feedback indisponível.', 'hng-commerce'));
    }
}
