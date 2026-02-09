<?php
/**
 * Configurações de Pagamento - Gateways
 *
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Payment_Settings {
    
    /**
     * Instância única
     */
    private static $instance = null;
    
    /**
     * Gateways disponíveis
     */
    private $available_gateways = [];
    
    /**
     * Obter instância
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Construtor
     */
    private function __construct() {
        $this->register_gateways();
        add_action('admin_menu', [$this, 'add_menu_page'], 60);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_hng_test_gateway_connection', [$this, 'ajax_test_connection']);
    }
    
    /**
     * Registrar gateways disponíveis
     */
    private function register_gateways() {
        $this->available_gateways = [
            'asaas' => [
                'name' => 'Asaas',
                'description' => 'Gateway brasileiro com PIX, Boleto e Cartão. Taxas competitivas.',
                'icon' => '💠',
                'methods' => ['pix', 'boleto', 'credit_card'],
                'fees' => [
                    'pix' => '0.99%',
                    'boleto' => 'R$ 3,49',
                    'credit_card' => '2.99% + R$ 0,49'
                ],
                'fields' => [
                    'api_key' => [
                        'label' => 'API Key',
                        'type' => 'password',
                        'description' => 'Chave de API da Asaas (encontre em: Configurações → Integrações)'
                    ],
                    'environment' => [
                        'label' => 'Ambiente',
                        'type' => 'select',
                        'options' => [
                            'sandbox' => 'Sandbox (Testes)',
                            'production' => 'Produção'
                        ],
                        'description' => 'Use Sandbox para testes, Produção para vendas reais'
                    ]
                ],
                'webhook_url' => home_url('/wp-json/hng/v1/webhook/asaas'),
                'docs_url' => 'https://docs.asaas.com/',
                'class' => 'HNG_Gateway_Asaas'
            ],
            'mercadopago' => [
                'name' => 'Mercado Pago',
                'description' => 'Solução completa do Mercado Livre. Aceite cartões e pagamentos instantâneos.',
                'icon' => '💳',
                'methods' => ['pix', 'credit_card', 'debit_card'],
                'fees' => [
                    'pix' => '0.99%',
                    'credit_card' => '4.99% + R$ 0,39',
                    'debit_card' => '3.49%'
                ],
                'fields' => [
                    'access_token' => [
                        'label' => 'Access Token',
                        'type' => 'password',
                        'description' => 'Token de acesso do Mercado Pago'
                    ],
                    'public_key' => [
                        'label' => 'Public Key',
                        'type' => 'text',
                        'description' => 'Chave pública para checkout transparente'
                    ]
                ],
                'webhook_url' => home_url('/wp-json/hng/v1/webhook/mercadopago'),
                'docs_url' => 'https://www.mercadopago.com.br/developers',
                'class' => 'HNG_Gateway_MercadoPago'
            ],
            'pagseguro' => [
                'name' => 'PagSeguro',
                'description' => 'Gateway do Banco PagBank (antigo UOL PagSeguro).',
                'icon' => '🛡️',
                'methods' => ['pix', 'boleto', 'credit_card'],
                'fees' => [
                    'pix' => '0.99%',
                    'boleto' => 'R$ 3,50',
                    'credit_card' => '3.79% + R$ 0,60'
                ],
                'fields' => [
                    'email' => [
                        'label' => 'E-mail da Conta',
                        'type' => 'email',
                        'description' => 'E-mail cadastrado no PagSeguro'
                    ],
                    'token' => [
                        'label' => 'Token',
                        'type' => 'password',
                        'description' => 'Token de integração'
                    ]
                ],
                'webhook_url' => home_url('/wp-json/hng/v1/webhook/pagseguro'),
                'docs_url' => 'https://dev.pagseguro.uol.com.br/',
                'class' => 'HNG_Gateway_PagSeguro'
            ],
            'pagarme' => [
                'name' => 'Pagar.me',
                'description' => 'Fintech Stone. Receba em 1 dia útil.',
                'icon' => '🏦',
                'methods' => ['pix', 'boleto', 'credit_card'],
                'fees' => [
                    'pix' => '0.99%',
                    'boleto' => 'R$ 3,49',
                    'credit_card' => '2.99% + R$ 0,39'
                ],
                'fields' => [
                    'api_key' => [
                        'label' => 'API Key',
                        'type' => 'password',
                        'description' => 'Chave de API'
                    ],
                    'encryption_key' => [
                        'label' => 'Encryption Key',
                        'type' => 'password',
                        'description' => 'Chave de criptografia'
                    ]
                ],
                'webhook_url' => home_url('/wp-json/hng/v1/webhook/pagarme'),
                'docs_url' => 'https://docs.pagar.me/',
                'class' => 'HNG_Gateway_PagarMe'
            ],
            'pagseguro' => [
                'name' => 'PagSeguro (PagBank)',
                'description' => 'Gateway UOL PagSeguro. Alto volume de transações, aceito em todo Brasil.',
                'icon' => '🛡️',
                'methods' => ['pix', 'boleto', 'credit_card', 'debit_card'],
                'fees' => [
                    'pix' => '0.99%',
                    'boleto' => 'R$ 3,49',
                    'credit_card' => '3.79% + R$ 0,40',
                    'debit_card' => '2.99%'
                ],
                'fields' => [
                    'email' => [
                        'label' => 'E-mail da Conta',
                        'type' => 'email',
                        'description' => 'E-mail cadastrado no PagSeguro'
                    ],
                    'token' => [
                        'label' => 'Token de Produção',
                        'type' => 'password',
                        'description' => 'Token de produção (Preferências → Integrações → Token de Segurança)'
                    ],
                    'environment' => [
                        'label' => 'Ambiente',
                        'type' => 'select',
                        'options' => [
                            'sandbox' => 'Sandbox (Testes)',
                            'production' => 'Produção'
                        ],
                        'description' => 'Use Sandbox para testes, Produção para vendas reais'
                    ]
                ],
                'webhook_url' => home_url('/wp-json/hng/v1/webhook/pagseguro'),
                'docs_url' => 'https://dev.pagseguro.uol.com.br/reference/checkout-api',
                'class' => 'HNG_Gateway_PagSeguro'
            ],
            'cielo' => [
                'name' => 'Cielo',
                'description' => 'Líder em cartões no Brasil. Aceite todas as bandeiras.',
                'icon' => '☁️',
                'methods' => ['pix', 'credit_card', 'debit_card'],
                'fees' => [
                    'pix' => '0,79%',
                    'credit_card' => '3,19% até 12x',
                    'debit_card' => '2,19%'
                ],
                'fields' => [
                    'merchant_id' => [
                        'label' => 'Merchant ID',
                        'type' => 'text',
                        'description' => 'ID do estabelecimento Cielo (EC Number)'
                    ],
                    'merchant_key' => [
                        'label' => 'Merchant Key',
                        'type' => 'password',
                        'description' => 'Chave secreta de API (Access Key)'
                    ],
                    'environment' => [
                        'label' => 'Ambiente',
                        'type' => 'select',
                        'options' => [
                            'sandbox' => 'Sandbox (Testes)',
                            'production' => 'Produção'
                        ],
                        'description' => 'Use Sandbox para testes'
                    ]
                ],
                'webhook_url' => home_url('/wp-json/hng/v1/webhook/cielo'),
                'docs_url' => 'https://developercielo.github.io/',
                'class' => 'HNG_Gateway_Cielo'
            ],
            'rede' => [
                'name' => 'Rede',
                'description' => 'Processadora Rede (e-Rede) com suporte a split payment',
                'icon' => '🏦',
                'methods' => ['pix', 'credit_card', 'debit_card'],
                'fees' => [
                    'pix' => '0,99%',
                    'credit_card' => '2,90%',
                    'debit_card' => '1,99%'
                ],
                'fields' => [
                    'pv' => [
                        'label' => 'PV (Point of Sale)',
                        'type' => 'text',
                        'description' => 'Código PV fornecido pela Rede'
                    ],
                    'token' => [
                        'label' => 'Token',
                        'type' => 'password',
                        'description' => 'Token de autenticação da API Rede'
                    ],
                    'environment' => [
                        'label' => 'Ambiente',
                        'type' => 'select',
                        'options' => [
                            'sandbox' => 'Sandbox (Testes)',
                            'production' => 'Produção'
                        ],
                        'description' => 'Use Sandbox para homologação'
                    ]
                ],
                'webhook_url' => home_url('/wp-json/hng/v1/webhook/rede'),
                'docs_url' => 'https://developers.userede.com.br/',
                'class' => 'HNG_Gateway_Rede'
            ],
            'stone' => [
                'name' => 'Stone',
                'description' => 'Adquirente Stone com suporte a split payment',
                'icon' => '💎',
                'methods' => ['pix', 'credit_card', 'debit_card'],
                'fees' => [
                    'pix' => '0,99%',
                    'credit_card' => '2,85%',
                    'debit_card' => '1,99%'
                ],
                'fields' => [
                    'api_key' => [
                        'label' => 'API Key',
                        'type' => 'password',
                        'description' => 'Chave de API da Stone'
                    ],
                    'seller_key' => [
                        'label' => 'Seller Key',
                        'type' => 'text',
                        'description' => 'Identificador do vendedor'
                    ],
                    'environment' => [
                        'label' => 'Ambiente',
                        'type' => 'select',
                        'options' => [
                            'sandbox' => 'Sandbox (Testes)',
                            'production' => 'Produção'
                        ],
                        'description' => 'Use Sandbox para homologação'
                    ]
                ],
                'webhook_url' => home_url('/wp-json/hng/v1/webhook/stone'),
                'docs_url' => 'https://docs.stone.com.br/',
                'class' => 'HNG_Gateway_Stone'
            ],
            'getnet' => [
                'name' => 'Getnet',
                'description' => 'Getnet Santander com suporte a split payment',
                'icon' => '🔴',
                'methods' => ['pix', 'credit_card', 'debit_card', 'boleto'],
                'fees' => [
                    'pix' => '0,99%',
                    'credit_card' => '2,95%',
                    'debit_card' => '1,99%',
                    'boleto' => 'R$ 2,90'
                ],
                'fields' => [
                    'seller_id' => [
                        'label' => 'Seller ID',
                        'type' => 'text',
                        'description' => 'ID do vendedor no Getnet'
                    ],
                    'client_id' => [
                        'label' => 'Client ID',
                        'type' => 'text',
                        'description' => 'Client ID OAuth 2.0'
                    ],
                    'client_secret' => [
                        'label' => 'Client Secret',
                        'type' => 'password',
                        'description' => 'Client Secret OAuth 2.0'
                    ],
                    'environment' => [
                        'label' => 'Ambiente',
                        'type' => 'select',
                        'options' => [
                            'sandbox' => 'Sandbox (Testes)',
                            'production' => 'Produção'
                        ],
                        'description' => 'Use Sandbox para homologação'
                    ]
                ],
                'webhook_url' => home_url('/wp-json/hng/v1/webhook/getnet'),
                'docs_url' => 'https://developers.getnet.com.br/',
                'class' => 'HNG_Gateway_Getnet'
            ]
        ];
        
        // Filtro para adicionar gateways personalizados
        $this->available_gateways = apply_filters('hng_available_gateways', $this->available_gateways);
    }

    /**
     * Retorna lista completa de gateways disponíveis (id => dados)
     */
    public function get_gateways() {
        return $this->available_gateways;
    }

    /**
     * Sanitiza um toggle yes/no retornando 'yes' ou 'no'
     */
    private function sanitize_yes_no( $val ) {
        $val = sanitize_text_field( (string) $val );
        return $val === 'yes' ? 'yes' : 'no';
    }

    /**
     * Sanitiza arrays de strings (ex: métodos habilitados)
     */
    private function sanitize_array_of_strings( $val ) {
        if ( ! is_array( $val ) ) {
            return array();
        }
        return array_values( array_map( 'sanitize_text_field', $val ) );
    }

    /**
     * Sanitiza tipo de taxa do plugin (percentage|fixed)
     */
    private function sanitize_fee_type( $val ) {
        $val = sanitize_text_field( (string) $val );
        return in_array( $val, array( 'percentage', 'fixed' ), true ) ? $val : 'percentage';
    }

    /**
     * Sanitiza valor de taxa do plugin para float (string armazenada)
     */
    private function sanitize_fee_value( $val ) {
        $val = str_replace( array( ',', ' ' ), array( '.', '' ), (string) $val );
        return (string) floatval( $val );
    }

    /**
     * Retorna métodos suportados pelo gateway ativo intersectados com métodos habilitados
     */
    public function get_active_gateway_methods() {
        $active = get_option('hng_active_gateway', '');
        $enabled = get_option('hng_enabled_payment_methods', ['pix','boleto','credit_card']);
        if (!is_array($enabled)) { $enabled = ['pix','boleto','credit_card']; }
        if (!$active || empty($this->available_gateways[$active])) { return $enabled; }
        $supported = $this->available_gateways[$active]['methods'];
        return array_values(array_intersect($supported, $enabled));
    }
    
    /**
     * Adicionar página no menu admin
     */
    public function add_menu_page() {
        global $menu;
        
        // Verificar se o menu principal existe
        $menu_exists = false;
        if (is_array($menu)) {
            foreach ($menu as $item) {
                if (isset($item[2]) && $item[2] === 'HNG Commerce') {
                    $menu_exists = true;
                    break;
                }
            }
        }
        
        // Se não existir, criar menu principal temporário
        if (!$menu_exists) {
            add_menu_page(
                __('HNG Commerce', 'hng-commerce'),
                __('HNG Commerce', 'hng-commerce'),
                'manage_options',
                'HNG Commerce',
                '__return_null',
                'dashicons-cart',
                56
            );
        }
        
        /*
        add_submenu_page(
            'HNG Commerce',
            __('Configurações de Pagamento', 'hng-commerce'),
            __('Pagamentos', 'hng-commerce'),
            'manage_options',
            'hng-payment-settings',
            [$this, 'render_page']
        );
        */
    }
    
    /**
     * Registrar configurações
     */
    public function register_settings() {
        // Serviço de Processamento HNG (toggle principal)
        register_setting('hng_payment_settings', 'hng_use_processing_service', [ 'sanitize_callback' => [ $this, 'sanitize_yes_no' ] ] );
        
        // Provedor PIX ativo
        register_setting('hng_payment_settings', 'hng_pix_provider', [ 'sanitize_callback' => 'sanitize_text_field' ] );
        
        // Gateway ativo
        register_setting('hng_payment_settings', 'hng_active_gateway', [ 'sanitize_callback' => 'sanitize_text_field' ] );
        
        // Taxa do plugin (será definida pelo usuário no futuro)
        register_setting('hng_payment_settings', 'hng_plugin_fee_type', [ 'sanitize_callback' => [ $this, 'sanitize_fee_type' ] ] ); // 'percentage' ou 'fixed'
        register_setting('hng_payment_settings', 'hng_plugin_fee_value', [ 'sanitize_callback' => [ $this, 'sanitize_fee_value' ] ] ); // Valor da taxa
        
        // Métodos de pagamento habilitados
        register_setting('hng_payment_settings', 'hng_enabled_payment_methods', [ 'sanitize_callback' => [ $this, 'sanitize_array_of_strings' ] ] );
        
        // Debug mode
        register_setting('hng_payment_settings', 'hng_payment_debug', [ 'sanitize_callback' => 'sanitize_text_field' ] );
        
        // Configurações específicas de cada gateway
        foreach ($this->available_gateways as $gateway_id => $gateway) {
            foreach ($gateway['fields'] as $field_id => $field) {
                register_setting('hng_payment_settings', "hng_{$gateway_id}_{$field_id}", [ 'sanitize_callback' => 'sanitize_text_field' ] );
            }
            
            // Habilitar/desabilitar gateway
            register_setting('hng_payment_settings', "hng_{$gateway_id}_enabled", [ 'sanitize_callback' => [ $this, 'sanitize_yes_no' ] ] );
        }
    }
    
    /**
     * Renderizar página
     */
    public function render_page() {
        $active_gateway = get_option('hng_active_gateway', '');
        $pix_provider = get_option('hng_pix_provider', 'asaas');
        $enabled_methods = get_option('hng_enabled_payment_methods', ['pix', 'boleto', 'credit_card']);
        $service_enabled = get_option('hng_use_processing_service', 'yes') === 'yes';
        
        echo '<div class="wrap hng-payment-settings">';
        echo '<h1>' . esc_html_e('Configurações de Pagamento', 'hng-commerce') . '</h1>';
        
        echo '<p class="description">';
        esc_html_e('Configure os gateways de pagamento e defina as taxas do sistema.', 'hng-commerce');
        echo '</p>';
        
        echo '<form method="post" action="options.php">';
        settings_fields('hng_payment_settings');
        submit_button();
        echo '</form>';
        echo '</div>';
        
        echo '<style>';
        echo '.hng-payment-content.active { display: block !important; animation: fadeIn 0.3s; }';
        echo '@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }';
        echo '</style>';
    }
    
    /**
     * AJAX: Testar conexão com gateway
     */
    public function ajax_test_connection() {
        check_ajax_referer('hng_test_gateway', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Sem permissão']);
        }
        
        $post = function_exists('wp_unslash') ? wp_unslash($_POST) : $_POST;
        $gateway_id = sanitize_text_field($post['gateway'] ?? '');
        
        if (!isset($this->available_gateways[$gateway_id])) {
            wp_send_json_error(['message' => 'Gateway inválido']);
        }
        
        $gateway_class = $this->available_gateways[$gateway_id]['class'];
        
        if (!class_exists($gateway_class)) {
            wp_send_json_error(['message' => 'Classe do gateway não encontrada']);
        }
        
        $gateway = new $gateway_class();
        
        if (!method_exists($gateway, 'test_connection')) {
            wp_send_json_error(['message' => 'Método de teste não implementado']);
        }
        
        $result = $gateway->test_connection();
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success(['message' => 'Conexão estabelecida com sucesso!']);
    }
}

// Inicializar
HNG_Payment_Settings::instance();
