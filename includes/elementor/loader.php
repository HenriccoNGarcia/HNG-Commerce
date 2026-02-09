<?php
/**
 * Elementor Loader for HNG Commerce
 */
if (!defined('ABSPATH')) { exit; }

// Log inicial
if (function_exists('hng_files_log_append')) {
    hng_files_log_append(HNG_COMMERCE_PATH . 'logs/elementor.log', '[HNG Commerce] Arquivo loader.php carregado.' . PHP_EOL);
}

// Função¡o para definir classe base quando Elementor estiver pronto
function hng_commerce_define_elementor_base() {
    // Verificar se Elementor carregou suas classes
    if (!class_exists('Elementor\\Widget_Base')) {
        if (function_exists('hng_files_log_append')) {
            hng_files_log_append(HNG_COMMERCE_PATH . 'logs/elementor.log', '[HNG Commerce] ERRO: Elementor\\Widget_Base não encontrada.' . PHP_EOL);
        }
        return false;
    }
    
    if (!class_exists('HNG_Commerce_Elementor_Widget_Base')) {
        abstract class HNG_Commerce_Elementor_Widget_Base extends \Elementor\Widget_Base {
            public function get_categories() { 
                return ['hng-commerce']; 
            }
            
            /**
             * Verifica se está no modo de edição do Elementor
             */
            protected function is_edit_mode() {
                // Múltiplas verificações para garantir detecção correta
                if (isset(\Elementor\Plugin::$instance->editor) && \Elementor\Plugin::$instance->editor->is_edit_mode()) {
                    return true;
                }
                if (isset($_GET['action']) && $_GET['action'] === 'elementor') {
                    return true;
                }
                if (isset($_GET['elementor-preview'])) {
                    return true;
                }
                return false;
            }
            
            protected function register_basic_control($id, $label, $type = \Elementor\Controls_Manager::TEXT, $default = '') {
                $this->add_control($id, [
                    'label' => $label,
                    'type' => $type,
                    'default' => $default
                ]);
            }
        }
        
        if (function_exists('hng_files_log_append')) {
            hng_files_log_append(HNG_COMMERCE_PATH . 'logs/elementor.log', '[HNG Commerce] Classe base definida.' . PHP_EOL);
        }
    }
    
    return true;
}

class HNG_Commerce_Elementor_Loader {
    const CATEGORY = 'hng-commerce';
    private static $bootstrapped = false;

    public static function init() {
        // Garantir single bootstrap
        if (self::$bootstrapped) { return; }
        
        // Só inicializar quando Elementor estiver carregado
        if (!did_action('elementor/loaded')) {
            add_action('elementor/loaded', array(__CLASS__, 'bootstrap'));
        } else {
            self::bootstrap();
        }
    }

    public static function bootstrap() {
        if (self::$bootstrapped) { return; }
        self::$bootstrapped = true;
        
        // Definir classe base agora que Elementor está carregado
        if (!hng_commerce_define_elementor_base()) {
            // Se falhar, não continuar
            return;
        }
        
        // Registrar categoria
        add_action('elementor/elements/categories_registered', array(__CLASS__, 'register_category'));
        // Registrar widgets
        add_action('elementor/widgets/register', array(__CLASS__, 'register_widgets'));
        
        if (function_exists('hng_files_log_append')) {
            hng_files_log_append(HNG_COMMERCE_PATH . 'logs/elementor.log', '[HNG Commerce] Elementor bootstrap concluído.' . PHP_EOL);
        }
    }

    public static function register_category($elements_manager) {
        $elements_manager->add_category(
            self::CATEGORY,
            [
                'title' => __('HNG Commerce', 'hng-commerce'),
                'icon'  => 'fa fa-shopping-cart'
            ]
        );
        if (function_exists('hng_files_log_append')) {
            hng_files_log_append(HNG_COMMERCE_PATH . 'logs/elementor.log', '[HNG Commerce] Categoria registrada: ' . self::CATEGORY . PHP_EOL);
        }
    }

    public static function register_widgets($widgets_manager) {
        // Carregar helpers primeiro
        $helpers_path = plugin_dir_path(__FILE__) . 'class-hng-elementor-helpers.php';
        if (file_exists($helpers_path)) {
            require_once $helpers_path;
        }
        
        $widget_files = [
            'class-hng-widget-product-grid.php',
            'class-hng-widget-single-product.php',
            'class-hng-widget-quote-request.php',
            'class-hng-widget-cart.php',
            'class-hng-widget-mini-cart.php',
            'class-hng-widget-checkout.php',
            'class-hng-widget-order-summary.php',
            'class-hng-widget-account-dashboard.php',
            'class-hng-widget-subscription-status.php',
            'class-hng-widget-coupon-form.php',
            'class-hng-widget-product-search.php',
            'class-hng-widget-product-categories.php',
            'class-hng-widget-product-filters.php',
            'class-hng-widget-product-advanced-filters.php',
            'class-hng-widget-upsell-products.php',
            'class-hng-widget-live-chat.php',
            'class-hng-widget-refund-request.php',
        ];

        foreach ($widget_files as $file) {
            $path = plugin_dir_path(__FILE__) . 'widgets/' . $file;
            if (file_exists($path)) {
                try {
                    require_once $path;
                } catch (\Throwable $e) {
                    if (function_exists('hng_files_log_append')) {
                        hng_files_log_append(HNG_COMMERCE_PATH . 'logs/elementor.log', '[HNG Commerce] Falha ao carregar widget file ' . $file . ': ' . $e->getMessage() . PHP_EOL);
                    }
                }
            } elseif (defined('WP_DEBUG') && WP_DEBUG) {
                if (function_exists('hng_files_log_append')) {
                    hng_files_log_append(HNG_COMMERCE_PATH . 'logs/elementor.log', '[HNG Commerce] Arquivo de widget ausente: ' . $file . PHP_EOL);
                }
            }
        }

        $classes = [
            'HNG_Widget_Product_Grid',
            'HNG_Widget_Single_Product',
            'HNG_Widget_Quote_Request',
            'HNG_Widget_Cart',
            'HNG_Widget_Mini_Cart',
            'HNG_Widget_Checkout',
            'HNG_Widget_Order_Summary',
            'HNG_Widget_Account_Dashboard',
            'HNG_Widget_Subscription_Status',
            'HNG_Widget_Coupon_Form',
            'HNG_Widget_Product_Search',
            'HNG_Widget_Product_Categories',
            'HNG_Widget_Product_Filters',
            'HNG_Widget_Product_Advanced_Filters',
            'HNG_Widget_Upsell_Products',
            'HNG_Widget_Live_Chat',
            'HNG_Widget_Refund_Request',
        ];

        foreach ($classes as $class) {
            if (class_exists($class)) {
                try {
                    $instance = new $class();
                    // Validar se herda de Elementor\Widget_Base
                    if ($instance instanceof \Elementor\Widget_Base) {
                        $widgets_manager->register($instance);
                        if (function_exists('hng_files_log_append')) { hng_files_log_append(HNG_COMMERCE_PATH . 'logs/elementor.log', '[HNG Commerce] Widget registrado: ' . $class . PHP_EOL); }
                    } else {
                        if (function_exists('hng_files_log_append')) { hng_files_log_append(HNG_COMMERCE_PATH . 'logs/elementor.log', '[HNG Commerce] Classe ' . $class . ' não áÂ© instancia de Elementor\\Widget_Base' . PHP_EOL); }
                    }
                } catch (\Throwable $e) {
                    if (function_exists('hng_files_log_append')) { hng_files_log_append(HNG_COMMERCE_PATH . 'logs/elementor.log', '[HNG Commerce] Erro ao instanciar widget ' . $class . ': ' . $e->getMessage() . PHP_EOL); }
                }
            } else {
                if (function_exists('hng_files_log_append')) { hng_files_log_append(HNG_COMMERCE_PATH . 'logs/elementor.log', '[HNG Commerce] Classe de widget não encontrada: ' . $class . PHP_EOL); }
            }
        }
    }
}

// Inicializar o loader
HNG_Commerce_Elementor_Loader::init();
