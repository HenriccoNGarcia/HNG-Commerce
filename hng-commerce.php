<?php

/**

 * Plugin Name: HNG Commerce

 * Plugin URI: https://hngdesenvolvimentos.com.br/plugins/hng-commerce/

 * Description: Solução completa de e-commerce para WordPress focada no mercado brasileiro. Alternativa ao WooCommerce com integrações nativas de pagamento e frete brasileiros.

 * Version: 1.2.17
 
 * Author: HNG Desenvolvimentos

 * Author URI: https://hngdesenvolvimentos.com.br

 * License: GPL v2 or later

 * License URI: https://www.gnu.org/licenses/gpl-2.0.html

 * Text Domain: hng-commerce

 * Domain Path: /languages

 * Requires at least: 5.8

 * Requires PHP: 7.4

 *

 * @package HNG_Commerce

 */



if (!defined('ABSPATH')) {

    exit; // Exit if accessed directly

}



// Define constantes do plugin

define('HNG_COMMERCE_VERSION', '1.2.17');

define('HNG_COMMERCE_FILE', __FILE__);

define('HNG_COMMERCE_PATH', plugin_dir_path(__FILE__));

define('HNG_COMMERCE_URL', plugin_dir_url(__FILE__));

define('HNG_COMMERCE_BASENAME', plugin_basename(__FILE__));

define('HNG_COMMERCE_SLUG', 'hng-commerce');



// Load permission fixer (auto-corrects file permissions after installation)

$permission_fixer = HNG_COMMERCE_PATH . 'includes/class-hng-setup-permissions.php';

if (file_exists($permission_fixer)) {

    require_once $permission_fixer;

    HNG_Setup_Permissions::init();

}



// Requisitos mínimos (usar if para evitar redefinição)

if (!defined('HNG_COMMERCE_MIN_PHP')) {

    define('HNG_COMMERCE_MIN_PHP', '7.4');

}

if (!defined('HNG_COMMERCE_MIN_WP')) {

    define('HNG_COMMERCE_MIN_WP', '5.8');

}



// Carregar correções de compatibilidade WordPress 6.8+

if (file_exists(HNG_COMMERCE_PATH . 'includes/class-hng-wp68-compatibility.php')) {

    require_once HNG_COMMERCE_PATH . 'includes/class-hng-wp68-compatibility.php';

}



// Verificar OpenSSL

if (extension_loaded('openssl') && !in_array('aes-256-gcm', openssl_get_cipher_methods())) {

    add_action('admin_notices', function() {

        echo '<div class="notice notice-error"><p>' . esc_html__('HNG Commerce: OpenSSL com AES-256-GCM é necessário.', 'hng-commerce') . '</p></div>';

    });

}
// Aviso: ingestão de webhooks é feita obrigatoriamente pelo _api-server
// Removido aviso de webhooks no admin por solicitação



// Carregar autoloader (guard para evitar fatal se o arquivo estiver ausente)

$autoloader = HNG_COMMERCE_PATH . 'includes/class-hng-autoloader.php';

if (file_exists($autoloader)) {

    require_once $autoloader;

} else {

    if (is_admin()) {

        add_action('admin_notices', function() {

            echo '<div class="notice notice-error"><p>';

            echo esc_html__('HNG Commerce: arquivo de autoloader ausente. Plugin não foi carregado para evitar erro fatal. Faça upload de `includes/class-hng-autoloader.php` ou restaure o plugin.', 'hng-commerce');

            echo '</p></div>';

        });

    }

    // Interrompe o carregamento do plugin para evitar erros fatais posteriores

    return;

}



// Rate Limiter utilitário

$rate_limiter = HNG_COMMERCE_PATH . 'includes/security/class-rate-limiter.php';

if (file_exists($rate_limiter)) {

    require_once $rate_limiter;

}



// Carregar Database Installer para migrações automáticas

$db_installer = HNG_COMMERCE_PATH . 'includes/database/class-database-installer.php';

if (file_exists($db_installer)) {

    require_once $db_installer;

}



// Carregar Updater cedo (antes dos hooks)

if (file_exists(HNG_COMMERCE_PATH . 'includes/class-hng-commerce-updater.php')) {

    require_once HNG_COMMERCE_PATH . 'includes/class-hng-commerce-updater.php';

}



// Internacionalização: desde o WordPress 4.6+, o repositório .org carrega

// automaticamente as traduções com base no cabeçalho Text Domain/slug.

// Nenhuma chamada explícita é necessária aqui para conformidade com o Plugin Check.



/**

 * Fallback routers para callbacks do admin.

 * Definimos funções globais para serem usadas como callbacks nas páginas do menu

 * evitando depender diretamente de métodos de instância durante o carregamento.

 */

if (!function_exists('hng_financial_page_router')) {

    function hng_financial_page_router() {

        if (class_exists('HNG_Admin')) {

            $admin = HNG_Admin::instance();

            if (method_exists($admin, 'financial_page_safe')) {

                return $admin->financial_page_safe();

            }

            if (method_exists($admin, 'financial_page')) {

                return $admin->financial_page();

            }

        }

        echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__('Página financeira indisponível.', 'hng-commerce') . '</p></div></div>';

    }

}



if (!function_exists('hng_shipping_page_router')) {

    function hng_shipping_page_router() {

        if (class_exists('HNG_Admin')) {

            $admin = HNG_Admin::instance();

            if (method_exists($admin, 'shipping_page_safe')) {

                return $admin->shipping_page_safe();

            }

            if (method_exists($admin, 'shipping_page')) {

                return $admin->shipping_page();

            }

        }

        echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__('Página de Frete indisponível.', 'hng-commerce') . '</p></div></div>';

    }

}



if (!function_exists('hng_customers_page_router')) {

    function hng_customers_page_router() {

        if (function_exists('hng_render_customers_page_enhanced')) {

            return hng_render_customers_page_enhanced();

        }

        if (class_exists('HNG_Admin')) {

            $admin = HNG_Admin::instance();

            if (method_exists($admin, 'customers_page')) {

                return $admin->customers_page();

            }

        }

        echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__('Página de Clientes indisponível.', 'hng-commerce') . '</p></div></div>';

    }

}



/**

 * Classe principal do HNG Commerce

 */

final class HNG_Commerce {



    /**

     * Instância única (Singleton)

     */

    private static $instance = null;

    

    /**

     * Retorna instância única

     */

    public static function instance() {

        if (is_null(self::$instance)) {

            self::$instance = new self();

        }

        return self::$instance;

    }

    

    /**

     * Construtor privado (Singleton)

     */

    private function __construct() {

        $this->check_requirements();

        $this->includes();

        $this->init_hooks();

        // Hooks adicionais para escopo CSS e instrumentação do editor Elementor

        add_filter('body_class', array($this, 'filter_body_class'));

        add_action('elementor/editor/after_enqueue_scripts', array($this, 'elementor_editor_scripts'));

    }

    

    /**

     * Previne clonagem

     */

    private function __clone() {}

    

    /**

     * Previne desserialização

     */

    public function __wakeup() {

        throw new Exception('Cannot unserialize singleton');

    }

    

    /**

     * Verifica requisitos mínimos

     */

    public function check_requirements() {

        if (version_compare(PHP_VERSION, HNG_COMMERCE_MIN_PHP, '<')) {

            add_action('admin_notices', function() {

                echo '<div class="error"><p>';

                printf(

                    /* translators: %1$s: minimum PHP version required, %2$s: current PHP version */

                    esc_html__( 'HNG Commerce requer PHP %1$s ou superior. Você está usando %2$s.', 'hng-commerce'),

                    esc_html( HNG_COMMERCE_MIN_PHP ),

                    esc_html( PHP_VERSION )

                );

                echo '</p></div>';

            });

            return false;

        }



        if (version_compare(get_bloginfo('version'), HNG_COMMERCE_MIN_WP, '<')) {

            add_action('admin_notices', function() {

                echo '<div class="error"><p>';

                printf(

                    /* translators: %1$s: minimum WordPress version required, %2$s: current WordPress version */

                    esc_html__( 'HNG Commerce requer WordPress %1$s ou superior. Você está usando %2$s.', 'hng-commerce'),

                    esc_html( HNG_COMMERCE_MIN_WP ),

                    esc_html( get_bloginfo('version') )

                );

                echo '</p></div>';

            });

            return false;

        }

        

        return true;

    }

    

    /**

     * Incluir arquivos necessários

     */

    private function includes() {

        // Core - Funções globais

        require_once HNG_COMMERCE_PATH . 'includes/functions.php';



        // Core - Sistema

        require_once HNG_COMMERCE_PATH . 'includes/class-hng-commerce-install.php';

        require_once HNG_COMMERCE_PATH . 'includes/class-hng-commerce-updater.php';

        // Compatibility já foi carregado no início do arquivo

        require_once HNG_COMMERCE_PATH . 'includes/class-hng-commerce-post-types.php';

        require_once HNG_COMMERCE_PATH . 'includes/class-hng-product-types.php';

        

        // Security

        require_once HNG_COMMERCE_PATH . 'includes/class-hng-fee-calculator.php';

        require_once HNG_COMMERCE_PATH . 'includes/class-hng-ledger.php';

        require_once HNG_COMMERCE_PATH . 'includes/class-hng-api-client.php';

        require_once HNG_COMMERCE_PATH . 'includes/class-hng-payment-orchestrator.php';

        require_once HNG_COMMERCE_PATH . 'includes/class-hng-webhook-handler.php';

        require_once HNG_COMMERCE_PATH . 'includes/security/class-hng-signature.php';

        require_once HNG_COMMERCE_PATH . 'includes/class-hng-checkout-intent-handler.php';

        require_once HNG_COMMERCE_PATH . 'includes/class-hng-domain-heartbeat.php';

        require_once HNG_COMMERCE_PATH . 'includes/class-hng-rate-limiter.php';

        

        // Performance & Monitoring (novas classes para otimização)

        if (file_exists(HNG_COMMERCE_PATH . 'includes/class-hng-logger.php')) {
            require_once HNG_COMMERCE_PATH . 'includes/class-hng-logger.php';
        }

        if (file_exists(HNG_COMMERCE_PATH . 'includes/class-hng-health-check.php')) {
            require_once HNG_COMMERCE_PATH . 'includes/class-hng-health-check.php';
        }

        if (file_exists(HNG_COMMERCE_PATH . 'includes/class-hng-config-cache.php')) {
            require_once HNG_COMMERCE_PATH . 'includes/class-hng-config-cache.php';
        }

        if (file_exists(HNG_COMMERCE_PATH . 'includes/class-hng-query-optimizer.php')) {
            require_once HNG_COMMERCE_PATH . 'includes/class-hng-query-optimizer.php';
        }

        // AJAX

        require_once HNG_COMMERCE_PATH . 'includes/class-hng-ajax.php';
        
        require_once HNG_COMMERCE_PATH . 'includes/class-hng-security-headers.php';

        require_once HNG_COMMERCE_PATH . 'includes/class-hng-download-routes.php';

        require_once HNG_COMMERCE_PATH . 'includes/class-hng-subscription.php';

        require_once HNG_COMMERCE_PATH . 'includes/class-hng-subscription-cron.php';

        require_once HNG_COMMERCE_PATH . 'includes/class-hng-appointment.php';

        require_once HNG_COMMERCE_PATH . 'includes/class-hng-appointment-ajax.php';

        require_once HNG_COMMERCE_PATH . 'includes/class-hng-pix-installment.php';

        require_once HNG_COMMERCE_PATH . 'includes/class-hng-pix-installment-ajax.php';

        

        // Analytics & Reports

        if (file_exists(HNG_COMMERCE_PATH . 'includes/analytics/class-conversion-tracker.php')) {

            require_once HNG_COMMERCE_PATH . 'includes/analytics/class-conversion-tracker.php';

        }

        if (file_exists(HNG_COMMERCE_PATH . 'includes/reports/class-reports-generator.php')) {

            require_once HNG_COMMERCE_PATH . 'includes/reports/class-reports-generator.php';

        }

        if (file_exists(HNG_COMMERCE_PATH . 'includes/ajax/ajax-reports.php')) {

            require_once HNG_COMMERCE_PATH . 'includes/ajax/ajax-reports.php';

        }

        if (file_exists(HNG_COMMERCE_PATH . 'includes/ajax/ajax-event-tracking.php')) {

            require_once HNG_COMMERCE_PATH . 'includes/ajax/ajax-event-tracking.php';

        }

        

        // Quote Products (Produtos de Orçamento)

        if (file_exists(HNG_COMMERCE_PATH . 'includes/products/class-product-type-quote.php')) {

            require_once HNG_COMMERCE_PATH . 'includes/products/class-product-type-quote.php';

        }

        if (is_admin() && file_exists(HNG_COMMERCE_PATH . 'includes/admin/class-quote-approval.php')) {

            require_once HNG_COMMERCE_PATH . 'includes/admin/class-quote-approval.php';

        }

        // Frontend controller for quote payment links

        if (!is_admin() && file_exists(HNG_COMMERCE_PATH . 'includes/controllers/class-hng-quote-payment-controller.php')) {

            require_once HNG_COMMERCE_PATH . 'includes/controllers/class-hng-quote-payment-controller.php';

        }

        

        // Authentication - Google OAuth

        if (file_exists(HNG_COMMERCE_PATH . 'includes/auth/class-google-oauth.php')) {

            require_once HNG_COMMERCE_PATH . 'includes/auth/class-google-oauth.php';

        }

        

        // Quote Chat System

        if (file_exists(HNG_COMMERCE_PATH . 'includes/chat/class-quote-chat.php')) {

            require_once HNG_COMMERCE_PATH . 'includes/chat/class-quote-chat.php';

        }

        

        // Customer Registration

        if (file_exists(HNG_COMMERCE_PATH . 'includes/class-hng-customer-registration.php')) {

            require_once HNG_COMMERCE_PATH . 'includes/class-hng-customer-registration.php';

        }

        

        // Google OAuth Authentication

        if (file_exists(HNG_COMMERCE_PATH . 'includes/class-hng-google-auth.php')) {

            require_once HNG_COMMERCE_PATH . 'includes/class-hng-google-auth.php';

        }

        

        // User Profile Handler (LGPD, updates, etc.)

        if (file_exists(HNG_COMMERCE_PATH . 'includes/class-hng-user-profile.php')) {

            require_once HNG_COMMERCE_PATH . 'includes/class-hng-user-profile.php';

        }

        

        // Shipping

        require_once HNG_COMMERCE_PATH . 'includes/shipping/class-shipping-method.php';

        require_once HNG_COMMERCE_PATH . 'includes/shipping/class-shipping-manager.php';

        require_once HNG_COMMERCE_PATH . 'includes/shipping/class-shipping-correios.php';

        require_once HNG_COMMERCE_PATH . 'includes/shipping/class-shipping-melhorenvio.php';

        require_once HNG_COMMERCE_PATH . 'includes/shipping/class-shipping-jadlog.php';

        if (file_exists(HNG_COMMERCE_PATH . 'includes/shipping/class-shipping-loggi.php')) {

            require_once HNG_COMMERCE_PATH . 'includes/shipping/class-shipping-loggi.php';

        }

        if (file_exists(HNG_COMMERCE_PATH . 'includes/shipping/class-shipping-total-express.php')) {

            require_once HNG_COMMERCE_PATH . 'includes/shipping/class-shipping-total-express.php';

        }

        if (file_exists(HNG_COMMERCE_PATH . 'includes/shipping/class-shipping-labels.php')) {

            require_once HNG_COMMERCE_PATH . 'includes/shipping/class-shipping-labels.php';

        }
        
        // Custom Shipping (Transportadora Própria)
        if (file_exists(HNG_COMMERCE_PATH . 'includes/shipping/class-shipping-custom.php')) {
            require_once HNG_COMMERCE_PATH . 'includes/shipping/class-shipping-custom.php';
        }
        
        if (file_exists(HNG_COMMERCE_PATH . 'includes/shipping/class-custom-shipping-settings.php')) {
            require_once HNG_COMMERCE_PATH . 'includes/shipping/class-custom-shipping-settings.php';
        }
        
        if (file_exists(HNG_COMMERCE_PATH . 'includes/shipping/class-custom-shipping-label.php')) {
            require_once HNG_COMMERCE_PATH . 'includes/shipping/class-custom-shipping-label.php';
        }

        // Diagnóstico de frete (apenas admin)

        if (file_exists(HNG_COMMERCE_PATH . 'includes/shipping/shipping-diagnostic.php')) {

            require_once HNG_COMMERCE_PATH . 'includes/shipping/shipping-diagnostic.php';

        }

        

        // Financial

        require_once HNG_COMMERCE_PATH . 'includes/financial/class-cost-tracker.php';

        require_once HNG_COMMERCE_PATH . 'includes/financial/class-profit-calculator.php';

        require_once HNG_COMMERCE_PATH . 'includes/financial/class-financial-dashboard.php';

        

        // Financial Analytics Export

        if (file_exists(HNG_COMMERCE_PATH . 'includes/admin/class-analytics-export.php')) {

            require_once HNG_COMMERCE_PATH . 'includes/admin/class-analytics-export.php';

        }

        

        // Import/Export

        if (file_exists(HNG_COMMERCE_PATH . 'includes/import-export/class-csv-importer-exporter.php')) {

            require_once HNG_COMMERCE_PATH . 'includes/import-export/class-csv-importer-exporter.php';

        }

        if (file_exists(HNG_COMMERCE_PATH . 'includes/import-export/class-woocommerce-importer.php')) {

            require_once HNG_COMMERCE_PATH . 'includes/import-export/class-woocommerce-importer.php';

        }


        // Refund System

        require_once HNG_COMMERCE_PATH . 'includes/class-hng-refund-requests.php';

        require_once HNG_COMMERCE_PATH . 'includes/class-hng-refund-processor.php';



        // Admin

        if (is_admin()) {

            require_once HNG_COMMERCE_PATH . 'includes/admin/class-hng-admin.php';

            require_once HNG_COMMERCE_PATH . 'includes/admin/class-hng-reports.php';
            
            require_once HNG_COMMERCE_PATH . 'includes/admin/class-hng-admin-notifications.php';

            require_once HNG_COMMERCE_PATH . 'admin/payment-settings-page.php';

            require_once HNG_COMMERCE_PATH . 'includes/admin/class-hng-merchant-registration.php';

            require_once HNG_COMMERCE_PATH . 'includes/admin/product-categories-admin.php';

            require_once HNG_COMMERCE_PATH . 'includes/admin/subscriptions-admin.php';

            require_once HNG_COMMERCE_PATH . 'includes/admin/professionals-admin.php';

            require_once HNG_COMMERCE_PATH . 'includes/admin/appointments-admin.php';

            require_once HNG_COMMERCE_PATH . 'includes/admin/class-hng-admin-connection-page.php';

            require_once HNG_COMMERCE_PATH . 'includes/admin/ajax-order-status.php';

            require_once HNG_COMMERCE_PATH . 'includes/admin/class-user-customer-relationship.php';

            require_once HNG_COMMERCE_PATH . 'includes/admin/class-hng-refund-requests-meta-box.php';

            
            // Settings
            if (file_exists(HNG_COMMERCE_PATH . 'includes/admin/settings/class-admin-settings.php')) {
                require_once HNG_COMMERCE_PATH . 'includes/admin/settings/class-admin-settings.php';
            }

            if (file_exists(HNG_COMMERCE_PATH . 'includes/admin/settings/class-roles-manager.php')) {
                require_once HNG_COMMERCE_PATH . 'includes/admin/settings/class-roles-manager.php';
            }

            // Setup Wizard - carregar apenas a classe, instanciar depois no plugins_loaded

            if (file_exists(HNG_COMMERCE_PATH . 'includes/admin/class-setup-wizard.php')) {

                require_once HNG_COMMERCE_PATH . 'includes/admin/class-setup-wizard.php';

                // Instanciar no hook plugins_loaded para garantir que WP está pronto

                add_action('plugins_loaded', function() {

                    HNG_Setup_Wizard::instance();

                }, 20);

            }

        }
        
        // Live Chat System - load after admin to ensure menu parent exists
        if (file_exists(HNG_COMMERCE_PATH . 'includes/chat/class-hng-live-chat.php')) {
            require_once HNG_COMMERCE_PATH . 'includes/chat/class-hng-live-chat.php';
        }

        

        // Gateway data management

        if (file_exists(HNG_COMMERCE_PATH . 'includes/helpers/hng-gateway-data.php')) {

            require_once HNG_COMMERCE_PATH . 'includes/helpers/hng-gateway-data.php';

        }

        

        // Enhanced customer management (merge order + CRM customers)

        if (file_exists(HNG_COMMERCE_PATH . 'includes/admin/customers-enhanced.php')) {

            require_once HNG_COMMERCE_PATH . 'includes/admin/customers-enhanced.php';

        }

        

        // Database migrations

        if (file_exists(HNG_COMMERCE_PATH . 'database/migrations/add-data-source-column.php')) {

            require_once HNG_COMMERCE_PATH . 'database/migrations/add-data-source-column.php';

        }

        if (file_exists(HNG_COMMERCE_PATH . 'database/migrations/create-coupon-usage-table.php')) {

            require_once HNG_COMMERCE_PATH . 'database/migrations/create-coupon-usage-table.php';

        }

        if (file_exists(HNG_COMMERCE_PATH . 'database/migrations/create-security-log-table.php')) {

            require_once HNG_COMMERCE_PATH . 'database/migrations/create-security-log-table.php';

        }
        
        if (file_exists(HNG_COMMERCE_PATH . 'includes/database/migration-orders-viewed.php')) {

            require_once HNG_COMMERCE_PATH . 'includes/database/migration-orders-viewed.php';

        }

        

        // Frontend

        if (!is_admin()) {

            require_once HNG_COMMERCE_PATH . 'includes/class-hng-frontend.php';

            require_once HNG_COMMERCE_PATH . 'includes/class-hng-shortcodes.php';

        }

        

        // Emails

        require_once HNG_COMMERCE_PATH . 'includes/class-hng-email.php';

        require_once HNG_COMMERCE_PATH . 'includes/emails/class-hng-email-manager.php';
        
        require_once HNG_COMMERCE_PATH . 'includes/emails/quote-email-functions.php';

        

        // Core classes

        require_once HNG_COMMERCE_PATH . 'includes/class-hng-checkout.php';

        require_once HNG_COMMERCE_PATH . 'includes/class-hng-account.php';

        require_once HNG_COMMERCE_PATH . 'includes/class-hng-order.php';

        

        // Carregar gateways opcionais (não bloqueantes)

        // Gateways e módulos que não devem impedir o funcionamento do core

        $this->load_optional_gateways();



        // Capabilities (gateway)

        if (file_exists(HNG_COMMERCE_PATH . 'includes/class-hng-gateway-capabilities.php')) {

            require_once HNG_COMMERCE_PATH . 'includes/class-hng-gateway-capabilities.php';

        }

        if (file_exists(HNG_COMMERCE_PATH . 'includes/class-hng-pix-manager.php')) {

            require_once HNG_COMMERCE_PATH . 'includes/class-hng-pix-manager.php';

        }



        // Integrações Avançadas

        if (file_exists(HNG_COMMERCE_PATH . 'includes/integrations/class-hng-asaas-advanced-integration.php')) {

            require_once HNG_COMMERCE_PATH . 'includes/integrations/class-hng-asaas-advanced-integration.php';

        }

    }

    

    /**

     * Carregar gateways opcionais – não deve bloquear o core

     */

    private function load_optional_gateways() {

        // Gateways de pagamento (carregar se os arquivos existirem)

        if (file_exists(HNG_COMMERCE_PATH . 'gateways/class-gateway-base.php')) {

            require_once HNG_COMMERCE_PATH . 'gateways/class-gateway-base.php';

            // Carregar classes de integração especializadas de gateways
            if (file_exists(HNG_COMMERCE_PATH . 'gateways/pagseguro/class-pagseguro-split-integration.php')) {
                require_once HNG_COMMERCE_PATH . 'gateways/pagseguro/class-pagseguro-split-integration.php';
            }
            if (file_exists(HNG_COMMERCE_PATH . 'gateways/pagseguro/class-pagseguro-settings.php')) {
                require_once HNG_COMMERCE_PATH . 'gateways/pagseguro/class-pagseguro-settings.php';
            }

            // Lista simples de gateways – se o arquivo existir, incluir e instanciar

            $gatewayFiles = [

                'gateways/asaas/class-gateway-asaas.php',

                'gateways/pagseguro/class-gateway-pagseguro.php',

                'gateways/cielo/class-gateway-cielo.php',

                'gateways/rede/class-gateway-rede.php',

                'gateways/stone/class-gateway-stone.php',

                'gateways/getnet/class-gateway-getnet.php',

            ];



            foreach ($gatewayFiles as $gf) {

                $path = HNG_COMMERCE_PATH . $gf;

                if (file_exists($path)) {

                    require_once $path;

                    // Derivar o nome da classe do arquivo (convenção HNG_Gateway_<Nome>)

                    $parts = explode('/', $gf);

                    $file = end($parts);

                    $class = 'HNG_Gateway_' . ucfirst(str_replace(['class-gateway-', '.php'], ['', ''], $file));

                    if (class_exists($class)) {

                        try {

                            new $class();

                        } catch (Exception $e) {

                            // Evitar que uma falha em um gateway quebre o plugin

                                $msg = 'HNG Commerce: falha ao instanciar gateway ' . $class . ': ' . $e->getMessage();

                                if (function_exists('hng_files_log_append')) {

                                    hng_files_log_append(HNG_COMMERCE_PATH . 'logs/gateways.log', $msg . PHP_EOL);

                                }

                        }

                    }

                }

            }

            // Load generic integrations that try to generate one-off / renewal payments for gateways

            $integration = HNG_COMMERCE_PATH . 'includes/gateways-integration.php';

            if (file_exists($integration)) {

                require_once $integration;

            }

        }

    }

    

    /**

     * Inicializar hooks

     */

    private function init_hooks() {

        // Limpeza de cache ao atualizar plugin

        add_action('upgrader_process_complete', array($this, 'clear_cache_on_update'), 10, 2);

        

        // Inicialização

        add_action('init', array($this, 'init'), 0);

        add_action('plugins_loaded', array($this, 'load_textdomain'));

        add_action('plugins_loaded', array('HNG_Commerce_Updater', 'check_updates'), 11);

        // Compatibility já foi inicializado no carregamento do arquivo
        
        // Fix para URLs de imagens (Global)
        add_filter('option_siteurl', array($this, 'fix_siteurl'), 10, 1);

        

        // Admin

        if (is_admin()) {

            add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));

            add_action('admin_init', array($this, 'maybe_clear_cache'));
            
            // Fix para URLs de imagens no Media Library
            add_filter('wp_get_attachment_image_src', array($this, 'fix_attachment_urls'), 10, 4);
            add_filter('wp_calculate_image_srcset', array($this, 'fix_srcset_urls'), 10, 5);

        }

        

        // Frontend

        if (!is_admin()) {

            add_action('wp_enqueue_scripts', array($this, 'frontend_scripts'));

            add_action('wp_head', array($this, 'frontend_head'));

        }

        

        // AJAX

        add_action('wp_ajax_hng_add_to_cart', array('HNG_Commerce_Ajax', 'add_to_cart'));

        add_action('wp_ajax_nopriv_hng_add_to_cart', array('HNG_Commerce_Ajax', 'add_to_cart'));



        // REST API

        add_action('rest_api_init', array($this, 'register_payment_routes'));

    }

    

    /**

     * Inicializar plugin

     */

    public function init() {

        // Registrar post types

        HNG_Commerce_Post_Types::register();

        

        // Inicializar classes

        if (is_admin()) {

            HNG_Admin::instance();

        } else {

            HNG_Frontend::instance();

            HNG_Shortcodes::instance();

            // Conversão: iniciar rastreamento no frontend
            if (class_exists('HNG_Conversion_Tracker')) {
                HNG_Conversion_Tracker::instance();
            }

        }

        

        HNG_Ajax::instance();

        HNG_Checkout::instance();

        HNG_Email::instance();

        HNG_Account::instance();

        

        // Registrar AJAX hooks de Gateway Management globalmente

        // (Precisa ser fora de is_admin() para funcionar em admin-ajax.php)

        if (class_exists('HNG_Gateway_Management_Page')) {

            HNG_Gateway_Management_Page::register_ajax_hooks();

        }

        // Hook de inicialização

        do_action('hng_commerce_init');

    }



    

    

    /**

     * Carregar tradução

     *

     * @deprecated 1.1.0 WordPress carrega automaticamente

     */

    public function load_textdomain() {

        // WordPress.org plugins do not need to call load_plugin_textdomain()

    }

    

    /**

     * Scripts do admin

     */

    public function admin_scripts($hook) {

        global $post_type;

        

        // Carregar em páginas do plugin OU na edição de produtos HNG

        $is_hng_page = strpos($hook, 'hng-commerce') !== false;

        $is_product_edit = (($hook === 'post.php' || $hook === 'post-new.php') && $post_type === 'hng_product');

        

        if ($is_hng_page || $is_product_edit) {

            // Temporariamente desabilitado: evita aplicação de CSS admin do plugin que

            // pode esconder itens nativos do admin. Reativar após validação.

            // wp_enqueue_style(

            //     'hng-commerce-admin',

            //     HNG_COMMERCE_URL . 'assets/css/admin.css',

            //     array(),

            //     HNG_COMMERCE_VERSION

            // );

            

            // Garantir que o sistema de notifications seja carregado antes do admin.js

            wp_enqueue_script(

                'hng-commerce-notifications',

                HNG_COMMERCE_URL . 'assets/js/notifications.js',

                array('jquery'),

                HNG_COMMERCE_VERSION,

                true

            );



            wp_enqueue_script(

                'hng-commerce-admin',

                HNG_COMMERCE_URL . 'assets/js/admin.js',

                array('jquery', 'hng-commerce-notifications'),

                HNG_COMMERCE_VERSION,

                true

            );

            

                wp_localize_script('hng-commerce-admin', 'hngCommerceAdmin', array(

                'ajax_url' => admin_url('admin-ajax.php'),

                'nonce' => wp_create_nonce('hng-commerce-admin'),

                'i18n' => array(

                    'confirm_delete' => __('Tem certeza que deseja excluir?', 'hng-commerce'),

                    'loading' => __('Carregando...', 'hng-commerce'),

                    'enter_valid_price' => __('Informe um preço válido.', 'hng-commerce'),

                    'error' => __('Erro ao processar.', 'hng-commerce'),

                    'generated' => __('Link gerado com sucesso!', 'hng-commerce'),

                    'copied' => __('Link copiado!', 'hng-commerce'),

                    'link_unavailable' => __('Link não disponível no momento.', 'hng-commerce'),

                    'generate_link' => __('Gerar Link', 'hng-commerce')

                )

            ));

        }

    }

    

    /**

     * Scripts do frontend

     */

    public function frontend_scripts() {

        // Enfileirar somente em páginas relevantes do plugin para reduzir impacto e conflitos.

        $should_enqueue = false;

        // Condições: página produto, arquivos relacionados ao checkout, shortcodes hng_* presentes ou Elementor editor carregando widgets do plugin.

        if (is_singular('hng_product') || is_post_type_archive('hng_product')) {

            $should_enqueue = true;

        }

        // Detectar presença de shortcodes do plugin no conteúdo principal.

        global $post;

        if (!$should_enqueue && $post instanceof WP_Post) {

            if (has_shortcode($post->post_content, 'hng_account') || has_shortcode($post->post_content, 'hng_checkout') || has_shortcode($post->post_content, 'hng_cart')) {

                $should_enqueue = true;

            }

        }

        // Se for página de checkout/cart/account via query var custom (placeholder futuro).

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only query vars for asset loading

        if (!$should_enqueue && (isset($_GET['hng_checkout']) || isset($_GET['hng_account']) || isset($_GET['hng_cart']))) {

            $should_enqueue = true;

        }

        // Elementor editor preview (evita falta de estilos ao arrastar widgets do plugin).

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Elementor preview mode detection

        if (!$should_enqueue && defined('ELEMENTOR_VERSION') && isset($_GET['elementor-preview'])) {

            $should_enqueue = true;

        }



        if ($should_enqueue) {

            wp_enqueue_style(

                'HNG Commerce',

                HNG_COMMERCE_URL . 'assets/css/frontend.css',

                array(),

                HNG_COMMERCE_VERSION

            );

            if (is_singular('hng_product')) {
                wp_add_inline_style(
                    'HNG Commerce',
                    '.hng-product-gallery { opacity: 0; transition: opacity 0.3s; } .hng-product-gallery.loaded { opacity: 1; }'
                );
            }

            wp_enqueue_script('HNG Commerce', HNG_COMMERCE_URL . 'assets/js/cart.js', array('jquery'), HNG_COMMERCE_VERSION, true);

            wp_localize_script('HNG Commerce', 'hngCommerce', array(

                'ajax_url' => admin_url('admin-ajax.php'),

                'nonce' => wp_create_nonce('HNG Commerce'),

                'currency' => 'R$',

                'i18n' => array(

                    'add_to_cart' => __('Adicionar ao Carrinho', 'hng-commerce'),

                    'loading' => __('Carregando...', 'hng-commerce'),

                    'added_to_cart' => __('Produto adicionado ao carrinho!', 'hng-commerce')
                )
            ));
            
            // Conversion tracking script
            wp_enqueue_script(
                'hng-conversion-tracking',
                HNG_COMMERCE_URL . 'assets/js/conversion-tracking.js',
                array('jquery'),
                HNG_COMMERCE_VERSION,
                true
            );
            // Build tracking context
            $is_checkout = false;
            // Detect checkout via shortcode or query var
            if ($post instanceof WP_Post) {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only
                $has_checkout_shortcode = has_shortcode($post->post_content, 'hng_checkout');
                $is_checkout = $has_checkout_shortcode || (isset($_GET['hng_checkout']) && $_GET['hng_checkout'] !== '');
            } else {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only
                $is_checkout = isset($_GET['hng_checkout']) && $_GET['hng_checkout'] !== '';
            }

            $product_id = is_singular('hng_product') ? get_the_ID() : null;

            wp_localize_script('hng-conversion-tracking', 'hngTrackingData', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('hng_tracking'),
                'pageId' => get_the_ID(),
                'productId' => $product_id,
                'isCheckout' => $is_checkout,
                'templateId' => $product_id,
                'templateName' => $product_id ? get_the_title($product_id) : null
            ));
        }

    }



    /**

     * Adiciona classe ao body para escopo de estilos somente em páginas da conta.

     */

    public function filter_body_class($classes) {

        if (function_exists('is_hng_account') && is_hng_account()) {

            $classes[] = 'hng-account-page';

        }

        return $classes;

    }



    /**

     * Scripts somente para o editor do Elementor (instrumentação/diagnóstico).

     */

    public function elementor_editor_scripts() {

        // Desabilitado temporariamente para evitar conflitos

        // wp_register_script(

        //     'hng-commerce-editor-inspect',

        //     HNG_COMMERCE_URL . 'assets/js/editor-inspect.js',

        //     array('jquery'),

        //     HNG_COMMERCE_VERSION,

        //     true

        // );

        // wp_enqueue_script('hng-commerce-editor-inspect');

    }

    

    /**

     * Output no head do frontend

     */

    public function frontend_head() {

        // Variáveis globais CSS ou meta tags

        echo '<meta name="generator" content="HNG Commerce ' . esc_attr(HNG_COMMERCE_VERSION) . '" />' . "\n";

    }

    

    /**

     * Limpar cache ao atualizar plugin

     */

    public function clear_cache_on_update($upgrader_object, $options) {

        // Verificar se é atualização do nosso plugin

        if ($options['action'] == 'update' && $options['type'] == 'plugin') {

            if (isset($options['plugins'])) {

                foreach ($options['plugins'] as $plugin) {

                    if ($plugin == HNG_COMMERCE_BASENAME) {

                        $this->force_cache_clear();

                        break;

                    }

                }

            }

        }

    }

    

    /**

     * Forçar limpeza total de cache

     */

    public function force_cache_clear() {

        // Limpar cache do WordPress

        wp_cache_flush();

        

        // Limpar transients do HNG Commerce

        global $wpdb;

        $wpdb->query(

            "DELETE FROM {$wpdb->options} 

             WHERE option_name LIKE '_transient_hng_%' 

             OR option_name LIKE '_transient_timeout_hng_%'"

        );

        

        // Forçar atualização de timestamp para CSS/JS

        update_option('hng_assets_version', time());

        

        // Limpar cache de plugins populares

        // W3 Total Cache

        if (function_exists('w3tc_flush_all')) {

            w3tc_flush_all();

        }

        

        // WP Super Cache

        if (function_exists('wp_cache_clear_cache')) {

            wp_cache_clear_cache();

        }

        

        // WP Rocket

        if (function_exists('rocket_clean_domain')) {

            rocket_clean_domain();

        }

        

        // LiteSpeed Cache

        if (class_exists('LiteSpeed_Cache_API') && method_exists('LiteSpeed_Cache_API', 'purge_all')) {

            \LiteSpeed_Cache_API::purge_all();

        }

        

        // Autoptimize

        if (class_exists('autoptimizeCache')) {

            autoptimizeCache::clearall();

        }

    }

    

    /**

     * Verificar se deve limpar cache manualmente

     */

    public function maybe_clear_cache() {

        if (isset($_GET['hng_clear_cache']) && $_GET['hng_clear_cache'] === '1') {

            if (current_user_can('manage_options')) {

                check_admin_referer('hng_clear_cache');

                $this->force_cache_clear();

                

                wp_safe_redirect(admin_url('admin.php?page=hng-commerce&cache_cleared=1'));

                exit;

            }

        }

    }
    
    /**
     * Fix para URLs de imagens no Media Library
     * Corrige URLs que apontam para domínios incorretos
     */
    public function fix_attachment_urls($image, $attachment_id, $size, $icon) {
        if (!$image || !is_array($image) || empty($image[0])) {
            return $image;
        }
        
        // Corrigir URL para usar o upload_dir correto
        $upload_dir = wp_upload_dir();
        $image[0] = $this->fix_attachment_url($image[0], $upload_dir);
        
        return $image;
    }
    
    /**
     * Fix para srcset de imagens no Media Library
     */
    public function fix_srcset_urls($sources, $size_array, $image_src, $image_meta, $attachment_id) {
        if (!$sources || !is_array($sources)) {
            return $sources;
        }
        
        $upload_dir = wp_upload_dir();
        
        foreach ($sources as &$source) {
            if (isset($source['url'])) {
                $source['url'] = $this->fix_attachment_url($source['url'], $upload_dir);
            }
        }
        
        return $sources;
    }
    
    /**
     * Corrige a URL de uma imagem
     */
    private function fix_attachment_url($url, $upload_dir) {
        // Se a URL contém /wp-content/uploads/, extrai o path relativo
        if (preg_match('/wp-content\/uploads\/(.+)$/', $url, $matches)) {
            // Retorna a URL corrigida usando o baseurl correto
            return $upload_dir['baseurl'] . '/' . $matches[1];
        }
        
        // Se já é uma URL de acesso direto ao arquivo, deixar como está
        return $url;
    }
    
    /**
     * Fix para o URL do site (para resolver conflitos de multi-site/multi-domain)
     */
    public function fix_siteurl($url) {
        // Apenas aplicar em contexto admin para evitar efeitos colaterais
        if (!is_admin()) {
            return $url;
        }
        return $url;
    }

    

    /**

     * Retorna carrinho

     */

    public function cart() {

        return HNG_Cart::instance();

    }



    /**

     * Retorna sessão

     */

    public function session() {

        return HNG_Session::instance();

    }



    /**

     * Register payment routes (REST API)

     */

    public function register_payment_routes() {

        if (function_exists('register_rest_route')) {

            register_rest_route('hng/v1', '/payment/(?P<action>\w+)', [

                'methods' => WP_REST_Server::READABLE,

                'callback' => [$this, 'handle_payment_route'],

                'permission_callback' => '__return_true',

            ]);

        }

    }



    /**

     * Handle payment route callbacks

     */

    public function handle_payment_route( $request ) {

        $action = $request->get_param('action');



        try {

            switch ($action) {

                case 'status':

                    return $this->handle_payment_status($request);



                case 'create':

                    return $this->handle_payment_create($request);



                case 'process':

                    return $this->handle_payment_process($request);



                default:

                    return new WP_REST_Response([

                        'success' => false,

                        'error' => 'Ação de pagamento não suportada: ' . $action,

                        'available_actions' => ['status', 'create', 'process']

                    ], 400);

            }

        } catch (Exception $e) {

            return new WP_REST_Response([

                'success' => false,

                'error' => 'Erro interno: ' . $e->getMessage()

            ], 500);

        }

    }



    /**

     * Handle payment status check

     */

    private function handle_payment_status($request) {

        $payment_id = $request->get_param('payment_id');

        $gateway_id = $request->get_param('gateway') ?: 'asaas';



        if (empty($payment_id)) {

            return new WP_REST_Response([

                'success' => false,

                'error' => 'ID do pagamento é obrigatório'

            ], 400);

        }



        $gateway = $this->get_gateway($gateway_id);

        if (!$gateway) {

            return new WP_REST_Response([

                'success' => false,

                'error' => 'Gateway não encontrado: ' . $gateway_id

            ], 404);

        }



        $status = $gateway->get_payment_status($payment_id);



        if (is_wp_error($status)) {

            return new WP_REST_Response([

                'success' => false,

                'error' => $status->get_error_message()

            ], 400);

        }



        return new WP_REST_Response([

            'success' => true,

            'payment_id' => $payment_id,

            'status' => $status,

            'gateway' => $gateway_id

        ], 200);

    }



    /**

     * Handle payment creation

     */

    private function handle_payment_create($request) {

        $order_id = $request->get_param('order_id');

        $payment_method = $request->get_param('payment_method') ?: 'pix';

        $gateway_id = $request->get_param('gateway') ?: 'asaas';



        if (empty($order_id)) {

            return new WP_REST_Response([

                'success' => false,

                'error' => 'ID do pedido é obrigatório'

            ], 400);

        }



        $gateway = $this->get_gateway($gateway_id);

        if (!$gateway) {

            return new WP_REST_Response([

                'success' => false,

                'error' => 'Gateway não encontrado: ' . $gateway_id

            ], 404);

        }



        $payment_data = [

            'order_id' => $order_id,

            'method' => $payment_method,

            'amount' => $request->get_param('amount'),

            'description' => $request->get_param('description'),

        ];



        $result = $gateway->process_payment($order_id, $payment_data);



        if (is_wp_error($result)) {

            return new WP_REST_Response([

                'success' => false,

                'error' => $result->get_error_message()

            ], 400);

        }



        return new WP_REST_Response([

            'success' => true,

            'payment' => $result,

            'gateway' => $gateway_id

        ], 200);

    }



    /**

     * Handle payment processing (credit card)

     */

    private function handle_payment_process($request) {

        $order_id = $request->get_param('order_id');

        $gateway_id = $request->get_param('gateway') ?: 'asaas';



        if (empty($order_id)) {

            return new WP_REST_Response([

                'success' => false,

                'error' => 'ID do pedido é obrigatório'

            ], 400);

        }



        $gateway = $this->get_gateway($gateway_id);

        if (!$gateway) {

            return new WP_REST_Response([

                'success' => false,

                'error' => 'Gateway não encontrado: ' . $gateway_id

            ], 404);

        }



        $card_data = [

            'order_id' => $order_id,

            'method' => 'credit_card',

            'card_number' => $request->get_param('card_number'),

            'card_holder' => $request->get_param('card_holder'),

            'card_expiry' => $request->get_param('card_expiry'),

            'card_cvv' => $request->get_param('card_cvv'),

            'installments' => $request->get_param('installments') ?: 1,

        ];



        $result = $gateway->create_credit_card_payment($order_id, $card_data);



        if (is_wp_error($result)) {

            return new WP_REST_Response([

                'success' => false,

                'error' => $result->get_error_message()

            ], 400);

        }



        return new WP_REST_Response([

            'success' => true,

            'payment' => $result,

            'gateway' => $gateway_id

        ], 200);

    }



    /**

     * Get gateway instance by ID

     */

    private function get_gateway($gateway_id) {

        $gateways = $this->get_available_gateways();

        return isset($gateways[$gateway_id]) ? $gateways[$gateway_id] : null;

    }



    /**

     * Get available gateways

     */

    private function get_available_gateways() {

        static $gateways = null;



        if ($gateways === null) {

            $gateways = [];



            // List of possible gateways

            $possible_gateways = [

                'asaas' => 'HNG_Gateway_Asaas',

                'pagseguro' => 'HNG_Gateway_Pagseguro',

                'cielo' => 'HNG_Gateway_Cielo',

                'mercadopago' => 'HNG_Gateway_Mercadopago',

                'rede' => 'HNG_Gateway_Rede',

                'stone' => 'HNG_Gateway_Stone',

                'getnet' => 'HNG_Gateway_Getnet',

            ];



            foreach ($possible_gateways as $id => $class) {

                if (class_exists($class) && method_exists($class, 'get_instance')) {

                    try {

                        $gateways[$id] = $class::get_instance();

                    } catch (Exception $e) {

                        // Skip if gateway can't be instantiated

                    }

                }

            }

        }



        return $gateways;

    }

}



/**

 * Retorna instância do HNG Commerce

 */

if (!function_exists('hng_is_debug_enabled')) {

    /**

     * Verifica se o modo debug do plugin está habilitado.

     * Retorna true se WP_DEBUG estiver ativo ou se a opção `hng_enable_debug` estiver marcada.

     * Uso recomendado: verificar antes de gravar logs ou informações sensíveis.

     *

     * @return bool

     */

    function hng_is_debug_enabled() {

        if (defined('WP_DEBUG') && WP_DEBUG) {

            return true;

        }

        return (bool) get_option('hng_enable_debug', false);

    }

}



if (!function_exists('HNG_Commerce')) {

    function HNG_Commerce() {

        return HNG_Commerce::instance();

    }

}



// Carregar e registrar o hook de ativação ANTES de inicializar

if (!class_exists('HNG_Commerce_Install')) {

    require_once HNG_COMMERCE_PATH . 'includes/class-hng-commerce-install.php';

}

register_activation_hook(HNG_COMMERCE_FILE, array('HNG_Commerce_Install', 'activate'));

register_deactivation_hook(HNG_COMMERCE_FILE, array('HNG_Commerce_Install', 'deactivate'));



// Inicializar o plugin

HNG_Commerce();



// Carregamento tardio do Elementor

if (!function_exists('hng_commerce_bootstrap_elementor')) {

    function hng_commerce_bootstrap_elementor() {

        // Verificar se Elementor está realmente ativo e carregado

        if (!class_exists('Elementor\\Plugin')) {

            if (function_exists('hng_files_log_append')) {

                hng_files_log_append(HNG_COMMERCE_PATH . 'logs/elementor.log', '[HNG Commerce] Elementor\\Plugin não encontrado.' . PHP_EOL);

            }

            return; // Elementor não está ativo, não carregar integração

        }



        if (defined('HNG_COMMERCE_PATH')) {

            $loader = HNG_COMMERCE_PATH . 'includes/elementor/loader.php';

            if (file_exists($loader) && !class_exists('HNG_Commerce_Elementor_Loader')) {

                require_once $loader;

                

                // Log de debug

                if (function_exists('hng_files_log_append')) {

                    hng_files_log_append(HNG_COMMERCE_PATH . 'logs/elementor.log', '[HNG Commerce] Loader carregado via bootstrap.' . PHP_EOL);

                }

            }

        }

    }



    // Usar hook 'elementor/init' que roda DEPOIS do Elementor carregar todas as classes

    add_action('elementor/init', 'hng_commerce_bootstrap_elementor', 20);

    

    // Se Elementor já estiver carregado (plugin ativado depois), carregar imediatamente

    if (did_action('elementor/init')) {

        hng_commerce_bootstrap_elementor();

    }

}

/**
 * Send refund request email to admin when customer requests a refund
 */
add_action('hng_refund_request_submitted', function($order_id, $refund_data) {
    hng_send_refund_request_email($order_id, $refund_data);
}, 10, 2);

/**
 * Send refund email when payment is refunded
 */
add_action('hng_payment_refunded', function($order_id, $payment_data) {
    hng_send_refund_email($order_id, $payment_data);
}, 10, 2);

/**
 * Send refund request email to admin
 * 
 * @param int $order_id
 * @param array $refund_data
 */
function hng_send_refund_request_email($order_id, $refund_data) {
    $order = new HNG_Order($order_id);
    $customer_name = $order->get_customer_name();
    $refund_amount = $refund_data['amount'] ?? $order->get_total();
    $reason = $refund_data['reason'] ?? __('Não especificado', 'hng-commerce');
    
    // Format amount
    $currency_symbol = get_option('hng_currency_symbol', '$');
    $refund_amount_formatted = $currency_symbol . number_format($refund_amount, 2, ',', '.');
    
    // Build admin link
    $admin_link = admin_url("admin.php?page=hng-orders&action=view&order_id={$order_id}");
    
    // Prepare email variables
    $email_vars = [
        '{{customer_name}}' => $customer_name,
        '{{order_id}}' => $order_id,
        '{{refund_amount}}' => $refund_amount_formatted,
        '{{reason}}' => $reason,
        '{{request_date}}' => date_i18n(get_option('date_format') . ' ' . get_option('time_format')),
        '{{admin_link}}' => $admin_link,
    ];
    
    // Get saved template
    $template = get_option('hng_email_template_refund_request', []);
    $global_settings = get_option('hng_email_global_settings', []);
    
    // Get subject and content
    $subject = isset($template['subject']) && !empty($template['subject']) 
        ? $template['subject']
        : sprintf(
            /* translators: %d: order ID */
            __('Nova Solicitação de Reembolso - Pedido #%d', 'hng-commerce'),
            $order_id
        );
    
    $content = isset($template['content']) && !empty($template['content'])
        ? $template['content']
        : '';
    
    // Replace variables
    foreach ($email_vars as $var_key => $var_value) {
        $subject = str_replace($var_key, $var_value, $subject);
        $content = str_replace($var_key, $var_value, $content);
    }
    
    // Build HTML email with global settings
    $logo = isset($template['logo']) && !empty($template['logo']) 
        ? $template['logo']
        : (isset($global_settings['logo_url']) ? $global_settings['logo_url'] : '');
    
    $header_color = isset($template['header_color']) && !empty($template['header_color'])
        ? $template['header_color']
        : (isset($global_settings['header_color']) ? $global_settings['header_color'] : '#0073aa');
    
    $text_color = isset($template['text_color']) && !empty($template['text_color'])
        ? $template['text_color']
        : (isset($global_settings['text_color']) ? $global_settings['text_color'] : '#333333');
    
    $footer_text = isset($template['footer']) && !empty($template['footer'])
        ? $template['footer']
        : (isset($global_settings['footer_text']) ? $global_settings['footer_text'] : get_bloginfo('name'));
    
    $message = '<html><body style="font-family: Arial, sans-serif; color: ' . esc_attr($text_color) . ';">';
    
    // Logo
    if ($logo) {
        $message .= '<div style="text-align: center; margin-bottom: 20px;">';
        $message .= '<img src="' . esc_url($logo) . '" alt="' . esc_attr(get_bloginfo('name')) . '" style="max-width: 200px; height: auto;">';
        $message .= '</div>';
    }
    
    // Header
    if (isset($template['header']) && !empty($template['header'])) {
        $message .= '<div style="background-color: ' . esc_attr($header_color) . '; color: white; padding: 20px; text-align: center; border-radius: 5px; margin-bottom: 20px;">';
        $message .= wp_kses_post($template['header']);
        $message .= '</div>';
    }
    
    // Content
    $message .= '<div style="padding: 20px; color: ' . esc_attr($text_color) . ';">';
    $message .= wp_kses_post(wpautop($content));
    $message .= '</div>';
    
    // Footer
    if ($footer_text) {
        $message .= '<div style="border-top: 1px solid #ddd; padding-top: 20px; margin-top: 20px; text-align: center; font-size: 12px; color: #666;">';
        $message .= wp_kses_post($footer_text);
        $message .= '</div>';
    }
    
    $message .= '</body></html>';
    
    // Set up email headers for HTML
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    
    // Send to admin
    $admin_email = get_option('admin_email');
    wp_mail($admin_email, $subject, $message, $headers);
}

/**
 * Send refund processed email
 * 
 * @param int $order_id
 * @param array $payment_data
 */
function hng_send_refund_email($order_id, $payment_data) {
    $order = new HNG_Order($order_id);
    $customer_name = $order->get_customer_name();
    $customer_email = $order->get_customer_email();
    $original_amount = $order->get_total();
    
    // Get refund amount (usually same as original, but could be partial)
    $refund_amount = $payment_data['amount'] ?? $original_amount;
    
    // Get payment method
    $payment_method = get_post_meta($order->get_post_id(), '_payment_method', true);
    $payment_method = $payment_method ?: __('Desconhecido', 'hng-commerce');
    
    // Calculate estimated arrival (usually 3-5 business days)
    $estimated_days = 5;
    $estimated_arrival = date_i18n(
        get_option('date_format'),
        strtotime("+{$estimated_days} days")
    );
    
    // Format amounts
    $currency_symbol = get_option('hng_currency_symbol', '$');
    $original_amount_formatted = $currency_symbol . number_format($original_amount, 2, ',', '.');
    $refund_amount_formatted = $currency_symbol . number_format($refund_amount, 2, ',', '.');
    
    // Prepare email variables
    $email_vars = [
        '{{customer_name}}' => $customer_name,
        '{{order_id}}' => $order_id,
        '{{refund_amount}}' => $refund_amount_formatted,
        '{{original_amount}}' => $original_amount_formatted,
        '{{refund_date}}' => date_i18n(get_option('date_format') . ' ' . get_option('time_format')),
        '{{refund_reason}}' => 'Reembolso conforme solicitado',
        '{{estimated_arrival}}' => $estimated_arrival,
        '{{payment_method}}' => $payment_method,
    ];
    
    // Get saved template
    $template = get_option('hng_email_template_refund_processed', []);
    $global_settings = get_option('hng_email_global_settings', []);
    
    // Get subject and content
    $subject = isset($template['subject']) && !empty($template['subject']) 
        ? $template['subject']
        : __('Reembolso Processado', 'hng-commerce');
    
    $content = isset($template['content']) && !empty($template['content'])
        ? $template['content']
        : '';
    
    // Replace variables
    foreach ($email_vars as $var_key => $var_value) {
        $subject = str_replace($var_key, $var_value, $subject);
        $content = str_replace($var_key, $var_value, $content);
    }
    
    // Build HTML email with global settings
    $logo = isset($template['logo']) && !empty($template['logo']) 
        ? $template['logo']
        : (isset($global_settings['logo_url']) ? $global_settings['logo_url'] : '');
    
    $header_color = isset($template['header_color']) && !empty($template['header_color'])
        ? $template['header_color']
        : (isset($global_settings['header_color']) ? $global_settings['header_color'] : '#0073aa');
    
    $text_color = isset($template['text_color']) && !empty($template['text_color'])
        ? $template['text_color']
        : (isset($global_settings['text_color']) ? $global_settings['text_color'] : '#333333');
    
    $footer_text = isset($template['footer']) && !empty($template['footer'])
        ? $template['footer']
        : (isset($global_settings['footer_text']) ? $global_settings['footer_text'] : get_bloginfo('name'));
    
    $message = '<html><body style="font-family: Arial, sans-serif; color: ' . esc_attr($text_color) . ';">';
    
    // Logo
    if ($logo) {
        $message .= '<div style="text-align: center; margin-bottom: 20px;">';
        $message .= '<img src="' . esc_url($logo) . '" alt="' . esc_attr(get_bloginfo('name')) . '" style="max-width: 200px; height: auto;">';
        $message .= '</div>';
    }
    
    // Header
    if (isset($template['header']) && !empty($template['header'])) {
        $message .= '<div style="background-color: ' . esc_attr($header_color) . '; color: white; padding: 20px; text-align: center; border-radius: 5px; margin-bottom: 20px;">';
        $message .= wp_kses_post($template['header']);
        $message .= '</div>';
    }
    
    // Content
    $message .= '<div style="padding: 20px; color: ' . esc_attr($text_color) . ';">';
    $message .= wp_kses_post(wpautop($content));
    $message .= '</div>';
    
    // Footer
    if ($footer_text) {
        $message .= '<div style="border-top: 1px solid #ddd; padding-top: 20px; margin-top: 20px; text-align: center; font-size: 12px; color: #666;">';
        $message .= wp_kses_post($footer_text);
        $message .= '</div>';
    }
    
    $message .= '</body></html>';
    
    // Set up email headers for HTML
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    
    // Send email
    wp_mail($customer_email, $subject, $message, $headers);
}







