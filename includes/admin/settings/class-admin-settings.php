<?php
/**
 * HNG Admin Settings - Handler Principal
 *
 * Gerencia o registro e renderização de configurações do plugin
 * de forma modular e extensível.
 *
 * @package HNG_Commerce
 * @subpackage Admin/Settings
 * @since 1.1.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Admin_Settings {
    
    /**

     * Tabs de configuração

     */

    private $tabs = [];

    

    /**

     * Tab ativa

     */

    private $active_tab = 'general';

    

    /**

     * Singleton instance

     */

    private static $instance = null;

    

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

        add_action('admin_init', [$this, 'register_settings']);

        $this->register_default_tabs();

    }

    

    /**

     * Registrar tabs padrão

     */

    private function register_default_tabs() {

        $this->register_tab('general', __('Geral', 'hng-commerce'), [$this, 'render_general_tab']);

        $this->register_tab('product_types', __('Tipos de Produto', 'hng-commerce'), [$this, 'render_product_types_tab']);

        $this->register_tab('pages', __('Páginas', 'hng-commerce'), [$this, 'render_pages_tab']);

        $this->register_tab('refund', __('Reembolsos', 'hng-commerce'), [$this, 'render_refund_tab']);

        $this->register_tab('pix_installment', __('Parcelamento PIX', 'hng-commerce'), [$this, 'render_pix_installment_tab']);

        $this->register_tab('security', __('Segurança', 'hng-commerce'), [$this, 'render_security_tab']);

        $this->register_tab('auth', __('Autenticação', 'hng-commerce'), [$this, 'render_auth_tab']);

        
        // Registrar aba de gerenciador de funções
        if (class_exists('HNG_Roles_Manager')) {
            $this->register_tab('roles', __('Gerenciador de Funções', 'hng-commerce'), [HNG_Roles_Manager::instance(), 'render_tab']);
        }

    }

    

    /**

     * Registrar uma tab

     */

    public function register_tab($id, $label, $callback) {

        $this->tabs[$id] = [

            'label' => $label,

            'callback' => $callback,

        ];

    }

    

    /**

     * Registrar configurações

     */

    public function register_settings() {

        // Registrar option group

        register_setting('hng_commerce_settings', 'hng_commerce_settings', [

            'sanitize_callback' => [$this, 'sanitize_settings'],

        ]);

        

        // Seção Geral

        add_settings_section(

            'hng_general_section',

            __('Configurações Gerais', 'hng-commerce'),

            '__return_null',

            'hng-commerce-general'

        );

        

        // Campos Gerais

        add_settings_field(

            'currency',

            __('Moeda', 'hng-commerce'),

            [$this, 'currency_field'],

            'hng-commerce-general',

            'hng_general_section'

        );

        

        add_settings_field(

            'currency_position',

            __('Posição do Símbolo', 'hng-commerce'),

            [$this, 'currency_position_field'],

            'hng-commerce-general',

            'hng_general_section'

        );

        

        add_settings_field(

            'thousand_separator',

            __('Separador de Milhares', 'hng-commerce'),

            [$this, 'thousand_separator_field'],

            'hng-commerce-general',

            'hng_general_section'

        );

        

        add_settings_field(

            'decimal_separator',

            __('Separador de Decimais', 'hng-commerce'),

            [$this, 'decimal_separator_field'],

            'hng-commerce-general',

            'hng_general_section'

        );

        

        add_settings_field(

            'number_decimals',

            __('Número de Decimais', 'hng-commerce'),

            [$this, 'number_decimals_field'],

            'hng-commerce-general',

            'hng_general_section'

        );

        

        add_settings_field(

            'require_login_to_purchase',

            __('Login Requerido para Comprar', 'hng-commerce'),

            [$this, 'require_login_to_purchase_field'],

            'hng-commerce-general',

            'hng_general_section'

        );

        

        add_settings_field(

            'redirect_to_checkout_after_add',

            __('Redirecionar ao Checkout', 'hng-commerce'),

            [$this, 'redirect_to_checkout_after_add_field'],

            'hng-commerce-general',

            'hng_general_section'

        );

        

        // Seção de Páginas

        add_settings_section(

            'hng_pages_section',

            __('Páginas do Sistema', 'hng-commerce'),

            [$this, 'pages_section_description'],

            'hng-commerce-pages'

        );

        

        $pages = ['shop', 'cart', 'checkout', 'my_account', 'order_confirmation'];

        foreach ($pages as $page) {

            add_settings_field(

                $page . '_page',

                $this->get_page_label($page),

                [$this, 'page_field'],

                'hng-commerce-pages',

                'hng_pages_section',

                ['page_key' => $page]

            );

        }

        

        // Seção PIX Parcelado

        add_settings_section(

            'hng_pix_installment_section',

            __('Configurações de Parcelamento PIX', 'hng-commerce'),

            '__return_null',

            'hng-commerce-pix-installment'

        );

        

        add_settings_field(

            'pix_installment_enabled',

            __('Habilitar Parcelamento PIX', 'hng-commerce'),

            [$this, 'pix_installment_enabled_field'],

            'hng-commerce-pix-installment',

            'hng_pix_installment_section'

        );

        

        add_settings_field(

            'pix_installment_max',

            __('Máximo de Parcelas', 'hng-commerce'),

            [$this, 'pix_installment_max_field'],

            'hng-commerce-pix-installment',

            'hng_pix_installment_section'

        );

        

        add_settings_field(

            'pix_installment_min_value',

            __('Valor Mínimo por Parcela', 'hng-commerce'),

            [$this, 'pix_installment_min_value_field'],

            'hng-commerce-pix-installment',

            'hng_pix_installment_section'

        );

        

        add_settings_field(

            'pix_installment_fee',

            __('Taxa de Juros (%)', 'hng-commerce'),

            [$this, 'pix_installment_fee_field'],

            'hng-commerce-pix-installment',

            'hng_pix_installment_section'

        );


        // Seção Reembolsos

        add_settings_section(

            'hng_refund_section',

            __('Configurações de Reembolsos', 'hng-commerce'),

            '__return_null',

            'hng-commerce-refund'

        );


        add_settings_field(

            'refund_enabled',

            __('Habilitar Sistema de Reembolsos', 'hng-commerce'),

            [$this, 'refund_enabled_field'],

            'hng-commerce-refund',

            'hng_refund_section'

        );


        add_settings_field(

            'refund_max_days',

            __('Dias Máximos para Solicitar Reembolso', 'hng-commerce'),

            [$this, 'refund_max_days_field'],

            'hng-commerce-refund',

            'hng_refund_section'

        );


        add_settings_field(

            'refund_require_reason',

            __('Exigir Motivo do Reembolso', 'hng-commerce'),

            [$this, 'refund_require_reason_field'],

            'hng-commerce-refund',

            'hng_refund_section'

        );


        add_settings_field(

            'refund_reasons',

            __('Motivos de Reembolso Disponíveis', 'hng-commerce'),

            [$this, 'refund_reasons_field'],

            'hng-commerce-refund',

            'hng_refund_section'

        );


        add_settings_field(

            'refund_allow_evidence',

            __('Permitir Upload de Evidências', 'hng-commerce'),

            [$this, 'refund_allow_evidence_field'],

            'hng-commerce-refund',

            'hng_refund_section'

        );


        add_settings_field(

            'refund_auto_approve',

            __('Aprovar Reembolsos Automaticamente', 'hng-commerce'),

            [$this, 'refund_auto_approve_field'],

            'hng-commerce-refund',

            'hng_refund_section'

        );

        

        // Seção Tipos de Produto

        add_settings_section(

            'hng_product_types_section',

            __('Tipos de Produto Habilitados', 'hng-commerce'),

            [$this, 'product_types_section_description'],

            'hng-commerce-product-types'

        );



        // Campo para cada tipo de produto

        $product_types = $this->get_all_product_types();

        foreach ($product_types as $type_key => $type_info) {

            add_settings_field(

                'product_type_' . $type_key,

                $type_info['icon'] . ' ' . $type_info['label'],

                [$this, 'product_type_toggle_field'],

                'hng-commerce-product-types',

                'hng_product_types_section',

                ['type_key' => $type_key, 'type_info' => $type_info]

            );

        }



        // Seção Segurança (segredos de webhook)

        $security_gateways = [

            'asaas' => __('Asaas', 'hng-commerce'),

            'mercadopago' => __('Mercado Pago', 'hng-commerce'),

            'pagseguro' => __('PagSeguro', 'hng-commerce'),

        ];



        foreach (array_keys($security_gateways) as $gateway_key) {

            register_setting('hng_security_settings', 'hng_webhook_secret_' . $gateway_key, [

                'sanitize_callback' => [$this, 'sanitize_webhook_secret'],

            ]);

        }



        add_settings_section(

            'hng_security_section',

            __('Segurança de Webhooks', 'hng-commerce'),

            [$this, 'security_section_description'],

            'hng-commerce-security'

        );



        foreach ($security_gateways as $gateway_key => $gateway_label) {

            add_settings_field(

                'webhook_secret_' . $gateway_key,

                /* translators: %s: gateway label */
                sprintf(__('Segredo do webhook (%s)', 'hng-commerce'), $gateway_label),

                [$this, 'webhook_secret_field'],

                'hng-commerce-security',

                'hng_security_section',

                ['gateway' => $gateway_key, 'label' => $gateway_label]

            );

        }



    }

    /**

     * Sanitizar configurações

     */

    public function sanitize_settings($input) {

        // BUGFIX: Preservar configurações existentes ao salvar em diferentes tabs

        $existing = get_option('hng_commerce_settings', []);

        $sanitized = $existing; // Preservar configurações existentes

        

        // Currency

        if (isset($input['currency'])) {

            $sanitized['currency'] = sanitize_text_field($input['currency']);

        }

        

        // Currency position

        if (isset($input['currency_position'])) {

            $allowed = ['left', 'right', 'left_space', 'right_space'];

            $sanitized['currency_position'] = in_array($input['currency_position'], $allowed, true) 

                ? $input['currency_position'] 

                : 'left';

        }

        

        // Separators

        if (isset($input['thousand_separator'])) {

            $sanitized['thousand_separator'] = sanitize_text_field($input['thousand_separator']);

        }

        

        if (isset($input['decimal_separator'])) {

            $sanitized['decimal_separator'] = sanitize_text_field($input['decimal_separator']);

        }

        

        // Number decimals

        if (isset($input['number_decimals'])) {

            $sanitized['number_decimals'] = absint($input['number_decimals']);

        }

        

        // Require login to purchase

        if (isset($input['require_login_to_purchase'])) {

            $sanitized['require_login_to_purchase'] = $input['require_login_to_purchase'] === 'yes' ? 'yes' : 'no';

        }

        

        // Redirect to checkout after add to cart

        if (isset($input['redirect_to_checkout_after_add'])) {

            $sanitized['redirect_to_checkout_after_add'] = $input['redirect_to_checkout_after_add'] === 'yes' ? 'yes' : 'no';

        }

        

        // Pages

        $pages = ['shop', 'cart', 'checkout', 'my_account', 'order_confirmation'];

        foreach ($pages as $page) {

            if (isset($input[$page . '_page'])) {

                $sanitized[$page . '_page'] = absint($input[$page . '_page']);

            }

        }

        

        // PIX Installment

        if (isset($input['pix_installment_enabled'])) {

            $sanitized['pix_installment_enabled'] = $input['pix_installment_enabled'] === 'yes' ? 'yes' : 'no';

        }

        

        if (isset($input['pix_installment_max'])) {

            $sanitized['pix_installment_max'] = max(2, min(12, absint($input['pix_installment_max'])));

        }

        

        if (isset($input['pix_installment_min_value'])) {

            $sanitized['pix_installment_min_value'] = floatval($input['pix_installment_min_value']);

        }

        

        if (isset($input['pix_installment_fee'])) {

            $sanitized['pix_installment_fee'] = floatval($input['pix_installment_fee']);

        }

        

        // Refund Settings

        if (isset($input['refund_enabled'])) {

            $sanitized['refund_enabled'] = $input['refund_enabled'] === 'yes' ? 'yes' : 'no';

        }

        

        if (isset($input['refund_max_days'])) {

            $sanitized['refund_max_days'] = absint($input['refund_max_days']);

        }

        

        if (isset($input['refund_require_reason'])) {

            $sanitized['refund_require_reason'] = $input['refund_require_reason'] === 'yes' ? 'yes' : 'no';

        }

        

        if (isset($input['refund_reasons'])) {

            $sanitized['refund_reasons'] = array_map('sanitize_text_field', explode("\n", $input['refund_reasons']));

        }

        

        if (isset($input['refund_allow_evidence'])) {

            $sanitized['refund_allow_evidence'] = $input['refund_allow_evidence'] === 'yes' ? 'yes' : 'no';

        }

        

        if (isset($input['refund_auto_approve'])) {

            $sanitized['refund_auto_approve'] = $input['refund_auto_approve'] === 'yes' ? 'yes' : 'no';

        }

        

        // Product Types

        $product_types = $this->get_all_product_types();

        foreach (array_keys($product_types) as $type_key) {

            $field_name = 'product_type_' . $type_key . '_enabled';

            if (isset($input[$field_name])) {

                $sanitized[$field_name] = $input[$field_name] === 'yes' ? 'yes' : 'no';

            } else {

                $sanitized[$field_name] = 'no';

            }

        }

        

        return $sanitized;

    }

    

    /**

     * Renderizar página principal

     */

    public function render() {

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading tab selection only

        $this->active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';

        

        ?>

        <div class="wrap hng-settings-page">

            <h1><?php esc_html_e('Configurações do HNG Commerce', 'hng-commerce'); ?></h1>

            

            <h2 class="nav-tab-wrapper">

                <?php foreach ($this->tabs as $tab_id => $tab) : ?>

                    <a href="<?php echo esc_url(admin_url('admin.php?page=hng-settings&tab=' . $tab_id)); ?>" 

                       class="nav-tab <?php echo esc_attr( $this->active_tab === $tab_id ? 'nav-tab-active' : '' ); ?>">

                        <?php echo esc_html($tab['label']); ?>

                    </a>

                <?php endforeach; ?>

            </h2>

            

            <form method="post" action="options.php">

                <?php

                if (isset($this->tabs[$this->active_tab])) {

                    call_user_func($this->tabs[$this->active_tab]['callback']);

                }

                ?>

            </form>

        </div>

        <?php

    }

    

    /**

     * Renderizar tab geral

     */

    public function render_general_tab() {

        settings_fields('hng_commerce_settings');

        do_settings_sections('hng-commerce-general');

        submit_button();

    }

    

    /**

     * Renderizar tab de páginas

     */

    public function render_pages_tab() {

        settings_fields('hng_commerce_settings');

        do_settings_sections('hng-commerce-pages');

        submit_button();

    }

    

    /**

     * Renderizar tab de segurança

     */

    public function render_security_tab() {

        settings_fields('hng_security_settings');

        do_settings_sections('hng-commerce-security');

        submit_button();

    }


    /**

     * Renderizar tab de tipos de produto

     */

    public function render_product_types_tab() {

        settings_fields('hng_commerce_settings');

        do_settings_sections('hng-commerce-product-types');

        submit_button();

    }


    /**

     * Renderizar tab de reembolsos

     */

    public function render_refund_tab() {

        settings_fields('hng_commerce_settings');

        do_settings_sections('hng-commerce-refund');

        submit_button();

    }


    /**

     * Renderizar tab de parcelamento PIX

     */

    public function render_pix_installment_tab() {

        settings_fields('hng_commerce_settings');

        do_settings_sections('hng-commerce-pix-installment');

        submit_button();

    }



    /**

     * Descrição da seção de segurança

     */

    public function security_section_description() {

        echo '<p>' . esc_html__('Cole o segredo do webhook fornecido por cada gateway para validar a assinatura HMAC.', 'hng-commerce') . '</p>';

    }



    /**

     * Campo de segredo do webhook

     */

    public function webhook_secret_field($args) {

        $gateway = $args['gateway'];

        $label = $args['label'] ?? $gateway;

        $option_key = 'hng_webhook_secret_' . $gateway;

        $value = get_option($option_key, '');

        $webhook_url = rest_url('hng/v1/webhook/' . $gateway);

        ?>

        <input type="text" name="<?php echo esc_attr($option_key); ?>" value="<?php echo esc_attr($value); ?>" class="regular-text" autocomplete="off">

        <p class="description">

            <?php /* translators: %s: payment gateway label */ ?>
            <?php printf(esc_html__('Segredo usado para validar a assinatura HMAC enviada pelo %s.', 'hng-commerce'), esc_html($label)); ?>

            <br>

            <?php /* translators: %s: webhook URL */ ?>
            <?php printf(esc_html__('URL do webhook: %s', 'hng-commerce'), esc_html($webhook_url)); ?>

        </p>

        <?php

    }



    /**

     * Sanitiza o segredo do webhook

     */

    public function sanitize_webhook_secret($input) {

        if ($input === null) {

            return '';

        }

        $value = trim((string) $input);

        return $value === '' ? '' : sanitize_text_field($value);

    }

    

    /**

     * Descrição da seção de tipos de produto

     */

    public function product_types_section_description() {

        echo '<p>' . esc_html__('Selecione quais tipos de produto estarão disponíveis na edição de produtos. Tipos desabilitados não aparecerão como opção.', 'hng-commerce') . '</p>';

        echo '<style>

            .hng-product-type-toggle { display: flex; align-items: center; gap: 15px; }

            .hng-toggle-switch { position: relative; width: 50px; height: 26px; }

            .hng-toggle-switch input { opacity: 0; width: 0; height: 0; }

            .hng-toggle-slider { position: absolute; cursor: pointer; inset: 0; background: #ccc; border-radius: 26px; transition: .3s; }

            .hng-toggle-slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background: white; border-radius: 50%; transition: .3s; }

            .hng-toggle-switch input:checked + .hng-toggle-slider { background: #2d1810; }

            .hng-toggle-switch input:checked + .hng-toggle-slider:before { transform: translateX(24px); }

            .hng-type-description { color: #666; font-size: 0.9em; }

        </style>';

    }

    

    /**

     * Campo de toggle para tipo de produto

     */

    public function product_type_toggle_field($args) {

        $type_key = $args['type_key'];

        $type_info = $args['type_info'];

        $options = get_option('hng_commerce_settings', []);

        

        // Verificar se já foi configurado alguma vez

        $all_types = $this->get_all_product_types();

        $has_product_type_settings = false;

        foreach (array_keys($all_types) as $tk) {

            if (isset($options['product_type_' . $tk . '_enabled'])) {

                $has_product_type_settings = true;

                break;

            }

        }

        

        // Por padrão, todos estão habilitados se nunca foi configurado

        $default = $has_product_type_settings ? 'no' : 'yes';

        if ($type_key === 'simple') {

            $default = 'yes'; // Simple sempre habilitado

        }

        

        $value = $options['product_type_' . $type_key . '_enabled'] ?? $default;

        

        ?>

        <div class="hng-product-type-toggle">

            <label class="hng-toggle-switch">

                <input type="checkbox" 

                       name="hng_commerce_settings[product_type_<?php echo esc_attr($type_key); ?>_enabled]" 

                       value="yes" 

                       <?php checked($value, 'yes'); ?>

                       <?php echo esc_attr( $type_key === 'simple' ? 'disabled checked' : '' ); ?>>

                <span class="hng-toggle-slider"></span>

            </label>

            <?php if ($type_key === 'simple'): ?>

                <input type="hidden" name="hng_commerce_settings[product_type_simple_enabled]" value="yes">

            <?php endif; ?>

            <span class="hng-type-description"><?php echo esc_html($type_info['description']); ?></span>

        </div>

        <?php

    }

    

    /**

     * Obter todos os tipos de produto disponíveis

     */

    public function get_all_product_types() {

        // Carregar a classe de tipos se disponível

        if (class_exists('HNG_Product_Type_Fields')) {

            return HNG_Product_Type_Fields::get_product_types();

        }

        

        // Fallback com tipos padrão

        return [

            'simple' => [

                'label' => __('Simples', 'hng-commerce'),

                'icon' => '📦',

                'description' => __('Produto padrão', 'hng-commerce'),

            ],

            'variable' => [

                'label' => __('Variável', 'hng-commerce'),

                'icon' => '🔀',

                'description' => __('Produto com variações', 'hng-commerce'),

            ],

            'digital' => [

                'label' => __('Digital', 'hng-commerce'),

                'icon' => '💾',

                'description' => __('Produto digital/download', 'hng-commerce'),

            ],

            'subscription' => [

                'label' => __('Assinatura', 'hng-commerce'),

                'icon' => '🔄',

                'description' => __('Produto com pagamento recorrente', 'hng-commerce'),

            ],

            'quote' => [

                'label' => __('Orçamento', 'hng-commerce'),

                'icon' => '📋',

                'description' => __('Produto que requer orçamento', 'hng-commerce'),

            ],

            'appointment' => [

                'label' => __('Agendamento', 'hng-commerce'),

                'icon' => '📅',

                'description' => __('Serviço com horário agendado', 'hng-commerce'),

            ],

        ];

    }

    

    /**

     * Obter apenas os tipos de produto habilitados

     */

    public static function get_enabled_product_types() {

        $options = get_option('hng_commerce_settings', []);

        $all_types = self::instance()->get_all_product_types();

        $enabled = [];

        

        // Verificar se já foi configurado alguma vez

        $has_product_type_settings = false;

        foreach (array_keys($all_types) as $type_key) {

            if (isset($options['product_type_' . $type_key . '_enabled'])) {

                $has_product_type_settings = true;

                break;

            }

        }

        

        foreach ($all_types as $type_key => $type_info) {

            // Simple está sempre habilitado

            if ($type_key === 'simple') {

                $enabled[$type_key] = $type_info;

                continue;

            }

            

            // Se nunca foi configurado, habilitar todos por padrão

            if (!$has_product_type_settings) {

                $enabled[$type_key] = $type_info;

                continue;

            }

            

            $is_enabled = $options['product_type_' . $type_key . '_enabled'] ?? 'no';

            if ($is_enabled === 'yes') {

                $enabled[$type_key] = $type_info;

            }

        }

        

        return $enabled;

    }

    

    /**

     * Campo de login requerido

     */

    public function require_login_to_purchase_field() {

        $options = get_option('hng_commerce_settings', []);

        $value = $options['require_login_to_purchase'] ?? 'no';

        ?>

        <label>

            <input type="checkbox" 

                   name="hng_commerce_settings[require_login_to_purchase]" 

                   value="yes" 

                   <?php checked($value, 'yes'); ?>>

            <?php esc_html_e('Exigir que o usuário esteja logado para finalizar uma compra', 'hng-commerce'); ?>

        </label>

        <p class="description">

            <?php esc_html_e('Quando ativado, usuários não logados poderão adicionar produtos ao carrinho e visualizar o checkout, mas não poderão processar o pagamento. Serão redirecionados para login/cadastro mantendo os produtos no carrinho.', 'hng-commerce'); ?>

        </p>

        <?php

    }

    

    /**

     * Campo de redirecionar ao checkout

     */

    public function redirect_to_checkout_after_add_field() {

        $options = get_option('hng_commerce_settings', []);

        $value = $options['redirect_to_checkout_after_add'] ?? 'no';

        ?>

        <label>

            <input type="checkbox" 

                   name="hng_commerce_settings[redirect_to_checkout_after_add]" 

                   value="yes" 

                   <?php checked($value, 'yes'); ?>>

            <?php esc_html_e('Redirecionar automaticamente para o checkout após adicionar produto ao carrinho', 'hng-commerce'); ?>

        </label>

        <p class="description">

            <?php esc_html_e('Quando ativado, o cliente será redirecionado diretamente para a página de finalização após adicionar um produto ao carrinho.', 'hng-commerce'); ?>

        </p>

        <?php

    }

    

    /**

     * Campo de moeda

     */

    public function currency_field() {

        $options = get_option('hng_commerce_settings', []);

        $value = $options['currency'] ?? 'BRL';

        

        $currencies = [

            'BRL' => 'Real Brasileiro (R$)',

            'USD' => 'Dólar Americano ($)',

            'EUR' => 'Euro (€)',

        ];

        

        echo '<select name="hng_commerce_settings[currency]" id="currency">';

        foreach ($currencies as $code => $label) {

            printf(

                '<option value="%s"%s>%s</option>',

                esc_attr($code),

                selected($value, $code, false),

                esc_html($label)

            );

        }

        echo '</select>';

    }

    

    /**

     * Campo de posição da moeda

     */

    public function currency_position_field() {

        $options = get_option('hng_commerce_settings', []);

        $value = $options['currency_position'] ?? 'left';

        

        $positions = [

            'left' => 'Esquerda (R$99)',

            'right' => 'Direita (99R$)',

            'left_space' => 'Esquerda com espaço (R$ 99)',

            'right_space' => 'Direita com espaço (99 R$)',

        ];

        

        echo '<select name="hng_commerce_settings[currency_position]" id="currency_position">';

        foreach ($positions as $pos => $label) {

            printf(

                '<option value="%s"%s>%s</option>',

                esc_attr($pos),

                selected($value, $pos, false),

                esc_html($label)

            );

        }

        echo '</select>';

    }

    

    /**

     * Campo separador de milhares

     */

    public function thousand_separator_field() {

        $options = get_option('hng_commerce_settings', []);

        $value = $options['thousand_separator'] ?? '.';

        

        printf(

            '<input type="text" name="hng_commerce_settings[thousand_separator]" value="%s" size="2">',

            esc_attr($value)

        );

    }

    

    /**

     * Campo separador de decimais

     */

    public function decimal_separator_field() {

        $options = get_option('hng_commerce_settings', []);

        $value = $options['decimal_separator'] ?? ',';

        

        printf(

            '<input type="text" name="hng_commerce_settings[decimal_separator]" value="%s" size="2">',

            esc_attr($value)

        );

    }

    

    /**

     * Campo número de decimais

     */

    public function number_decimals_field() {

        $options = get_option('hng_commerce_settings', []);

        $value = $options['number_decimals'] ?? 2;

        

        printf(

            '<input type="number" name="hng_commerce_settings[number_decimals]" value="%s" min="0" max="4">',

            esc_attr($value)

        );

    }

    

    /**

     * Descrição da seção de páginas

     */

    public function pages_section_description() {

        echo '<p>' . esc_html__('Selecione as páginas que serão usadas para cada funcionalidade do sistema.', 'hng-commerce') . '</p>';

    }

    

    /**

     * Campo de página

     */

    public function page_field($args) {

        $page_key = $args['page_key'];

        $options = get_option('hng_commerce_settings', []);

        $value = $options[$page_key . '_page'] ?? 0;

        

        wp_dropdown_pages([

            'name' => 'hng_commerce_settings[' . esc_attr($page_key) . '_page]',

            'selected' => absint($value),

            'show_option_none' => esc_html__('— Selecione —', 'hng-commerce'),

        ]);

    }

    

    /**

     * Get page label

     */

    private function get_page_label($page_key) {

        $labels = [

            'shop' => __('Página da Loja', 'hng-commerce'),

            'cart' => __('Página do Carrinho', 'hng-commerce'),

            'checkout' => __('Página de Checkout', 'hng-commerce'),

            'my_account' => __('Página da Minha Conta', 'hng-commerce'),

            'order_confirmation' => __('Página de Confirmação', 'hng-commerce'),

        ];

        

        return $labels[$page_key] ?? $page_key;

    }

    

    /**

     * Campo PIX installment enabled

     */

    public function pix_installment_enabled_field() {

        $options = get_option('hng_commerce_settings', []);

        $value = $options['pix_installment_enabled'] ?? 'no';

        

        printf(

            '<label><input type="checkbox" name="hng_commerce_settings[pix_installment_enabled]" value="yes"%s> %s</label>',

            checked($value, 'yes', false),

            esc_html__('Permitir parcelamento via PIX', 'hng-commerce')

        );

    }

    

    /**

     * Campo PIX installment max

     */

    public function pix_installment_max_field() {

        $options = get_option('hng_commerce_settings', []);

        $value = $options['pix_installment_max'] ?? 12;

        

        printf(

            '<input type="number" name="hng_commerce_settings[pix_installment_max]" value="%s" min="2" max="12">',

            esc_attr($value)

        );

    }

    

    /**

     * Campo PIX installment min value

     */

    public function pix_installment_min_value_field() {

        $options = get_option('hng_commerce_settings', []);

        $value = $options['pix_installment_min_value'] ?? 30.00;

        

        printf(

            '<input type="number" name="hng_commerce_settings[pix_installment_min_value]" value="%s" min="0" step="0.01"> <span class="description">%s</span>',

            esc_attr($value),

            esc_html__('Valor mínimo que cada parcela deve ter', 'hng-commerce')

        );

    }

    

    /**

     * Campo PIX installment fee

     */

    public function pix_installment_fee_field() {

        $options = get_option('hng_commerce_settings', []);

        $value = $options['pix_installment_fee'] ?? 0;

        

        printf(

            '<input type="number" name="hng_commerce_settings[pix_installment_fee]" value="%s" min="0" step="0.01"> <span class="description">%%</span>',

            esc_attr($value)

        );

    }

    

    /**

     * Renderizar tab de Autenticação

     */

    public function render_auth_tab() {

        $options = get_option('hng_commerce_settings', []);

        

        // Google OAuth settings

        $google_enabled = $options['google_oauth_enabled'] ?? 'no';

        $google_client_id = $options['google_oauth_client_id'] ?? '';

        $google_client_secret = $options['google_oauth_client_secret'] ?? '';

        

        // Callback URL for Google Console

        $callback_url = site_url('/') . '?hng_google_oauth=callback';

        ?>

        <h2><?php esc_html_e('Configurações de Autenticação', 'hng-commerce'); ?></h2>

        

        <table class="form-table" role="presentation">

            <!-- Google OAuth Section -->

            <tr>

                <th scope="row" colspan="2">

                    <h3 style="margin: 0;">

                        <span class="dashicons dashicons-google" style="color: #4285F4;"></span>

                        <?php esc_html_e('Login com Google', 'hng-commerce'); ?>

                    </h3>

                </th>

            </tr>

            

            <tr>

                <th scope="row">

                    <label for="google_oauth_enabled"><?php esc_html_e('Habilitar Login com Google', 'hng-commerce'); ?></label>

                </th>

                <td>

                    <label>

                        <input type="checkbox" 

                               id="google_oauth_enabled"

                               name="hng_commerce_settings[google_oauth_enabled]" 

                               value="yes" <?php checked($google_enabled, 'yes'); ?>>

                        <?php esc_html_e('Permitir que usuários façam login com suas contas Google', 'hng-commerce'); ?>

                    </label>

                </td>

            </tr>

            

            <tr>

                <th scope="row">

                    <label for="google_oauth_client_id"><?php esc_html_e('Client ID', 'hng-commerce'); ?></label>

                </th>

                <td>

                    <input type="text" 

                           id="google_oauth_client_id"

                           name="hng_commerce_settings[google_oauth_client_id]" 

                           value="<?php echo esc_attr($google_client_id); ?>"

                           class="regular-text"

                           placeholder="XXXXXXXXXX.apps.googleusercontent.com">

                    <p class="description">

                        <?php esc_html_e('Obtido no Google Cloud Console > APIs & Services > Credentials', 'hng-commerce'); ?>

                    </p>

                </td>

            </tr>

            

            <tr>

                <th scope="row">

                    <label for="google_oauth_client_secret"><?php esc_html_e('Client Secret', 'hng-commerce'); ?></label>

                </th>

                <td>

                    <input type="password" 

                           id="google_oauth_client_secret"

                           name="hng_commerce_settings[google_oauth_client_secret]" 

                           value="<?php echo esc_attr($google_client_secret); ?>"

                           class="regular-text"

                           placeholder="GOCSPX-XXXXXXXXXX">

                    <p class="description">

                        <?php esc_html_e('Chave secreta do cliente OAuth 2.0', 'hng-commerce'); ?>

                    </p>

                </td>

            </tr>

            

            <tr>

                <th scope="row">

                    <?php esc_html_e('URL de Callback', 'hng-commerce'); ?>

                </th>

                <td>

                    <code style="padding: 8px 12px; background: #f1f1f1; display: inline-block; margin-bottom: 8px;">

                        <?php echo esc_html($callback_url); ?>

                    </code>

                    <button type="button" class="button button-small" onclick="navigator.clipboard.writeText('<?php echo esc_js($callback_url); ?>'); this.textContent='<?php esc_attr_e('Copiado!', 'hng-commerce'); ?>'; setTimeout(() => this.textContent='<?php esc_attr_e('Copiar', 'hng-commerce'); ?>', 2000);">

                        <?php esc_html_e('Copiar', 'hng-commerce'); ?>

                    </button>

                    <p class="description">

                        <?php esc_html_e('Adicione esta URL como "Authorized redirect URIs" nas configurações do seu OAuth Client no Google Cloud Console.', 'hng-commerce'); ?>

                    </p>

                </td>

            </tr>

        </table>

        

        <hr style="margin: 2em 0;">

        

        <div class="hng-auth-help">

            <h3><?php esc_html_e('Como configurar o Google OAuth', 'hng-commerce'); ?></h3>

            <ol style="line-height: 1.8;">

                <li><?php esc_html_e('Acesse o Google Cloud Console:', 'hng-commerce'); ?> <a href="https://console.cloud.google.com/" target="_blank">console.cloud.google.com</a></li>

                <li><?php esc_html_e('Crie um novo projeto ou selecione um existente', 'hng-commerce'); ?></li>

                <li><?php esc_html_e('Vá para "APIs & Services" > "Credentials"', 'hng-commerce'); ?></li>

                <li><?php esc_html_e('Clique em "Create Credentials" > "OAuth client ID"', 'hng-commerce'); ?></li>

                <li><?php esc_html_e('Selecione "Web application" como tipo', 'hng-commerce'); ?></li>

                <li><?php esc_html_e('Adicione a URL de callback acima em "Authorized redirect URIs"', 'hng-commerce'); ?></li>

                <li><?php esc_html_e('Copie o Client ID e Client Secret para os campos acima', 'hng-commerce'); ?></li>

                <li><?php esc_html_e('Configure a tela de consentimento OAuth (OAuth consent screen) com as informações do seu site', 'hng-commerce'); ?></li>

            </ol>

        </div>

        

        <?php

        settings_fields('hng_commerce_settings');

        submit_button();

    }


    /**

     * Campo: Habilitar Sistema de Reembolsos

     */

    public function refund_enabled_field() {

        $options = get_option('hng_commerce_settings', []);

        $value = $options['refund_enabled'] ?? 'yes';

        ?>

        <label>

            <input type="checkbox" 

                   name="hng_commerce_settings[refund_enabled]" 

                   value="yes" 

                   <?php checked($value, 'yes'); ?>>

            <?php esc_html_e('Habilitar sistema de reembolsos para clientes', 'hng-commerce'); ?>

        </label>

        <p class="description">

            <?php esc_html_e('Quando habilitado, os clientes poderão solicitar reembolsos através da página Minha Conta.', 'hng-commerce'); ?>

        </p>

        <?php

    }


    /**

     * Campo: Dias Máximos para Solicitar Reembolso

     */

    public function refund_max_days_field() {

        $options = get_option('hng_commerce_settings', []);

        $value = $options['refund_max_days'] ?? 30;

        ?>

        <input type="number" 

               name="hng_commerce_settings[refund_max_days]"

               value="<?php echo esc_attr($value); ?>"

               min="1"

               max="365"

               class="small-text">

        <p class="description">

            <?php esc_html_e('Número máximo de dias após a compra para solicitar reembolso.', 'hng-commerce'); ?>

        </p>

        <?php

    }


    /**

     * Campo: Exigir Motivo do Reembolso

     */

    public function refund_require_reason_field() {

        $options = get_option('hng_commerce_settings', []);

        $value = $options['refund_require_reason'] ?? 'yes';

        ?>

        <label>

            <input type="checkbox" 

                   name="hng_commerce_settings[refund_require_reason]" 

                   value="yes" 

                   <?php checked($value, 'yes'); ?>>

            <?php esc_html_e('Exigir que o cliente especifique um motivo para o reembolso', 'hng-commerce'); ?>

        </label>

        <p class="description">

            <?php esc_html_e('Quando habilitado, os clientes terão que informar o motivo ao solicitar um reembolso.', 'hng-commerce'); ?>

        </p>

        <?php

    }


    /**

     * Campo: Motivos de Reembolso Disponíveis

     */

    public function refund_reasons_field() {

        $options = get_option('hng_commerce_settings', []);

        $value = isset($options['refund_reasons']) ? implode("\n", (array) $options['refund_reasons']) : "Produto Defeituoso\nProduto Não Chegou\nNão Gostei do Produto\nCompra por Engano\nMudança de Ideia\nOutro Motivo";

        ?>

        <textarea name="hng_commerce_settings[refund_reasons]"

                  rows="6"

                  cols="50"

                  class="large-text"

                  placeholder="<?php esc_attr_e('Um motivo por linha...', 'hng-commerce'); ?>"><?php echo esc_textarea($value); ?></textarea>

        <p class="description">

            <?php esc_html_e('Motivos disponíveis para que o cliente escolha ao solicitar reembolso. Um motivo por linha.', 'hng-commerce'); ?>

        </p>

        <?php

    }


    /**

     * Campo: Permitir Upload de Evidências

     */

    public function refund_allow_evidence_field() {

        $options = get_option('hng_commerce_settings', []);

        $value = $options['refund_allow_evidence'] ?? 'yes';

        ?>

        <label>

            <input type="checkbox" 

                   name="hng_commerce_settings[refund_allow_evidence]" 

                   value="yes" 

                   <?php checked($value, 'yes'); ?>>

            <?php esc_html_e('Permitir que o cliente envie anexos/evidências para suportar sua solicitação', 'hng-commerce'); ?>

        </label>

        <p class="description">

            <?php esc_html_e('Quando habilitado, os clientes podem enviar screenshots, fotos ou outros arquivos como prova.', 'hng-commerce'); ?>

        </p>

        <?php

    }


    /**

     * Campo: Aprovar Reembolsos Automaticamente

     */

    public function refund_auto_approve_field() {

        $options = get_option('hng_commerce_settings', []);

        $value = $options['refund_auto_approve'] ?? 'no';

        ?>

        <label>

            <input type="checkbox" 

                   name="hng_commerce_settings[refund_auto_approve]" 

                   value="yes" 

                   <?php checked($value, 'yes'); ?>>

            <?php esc_html_e('Aprovar reembolsos automaticamente (sem necessidade de revisão manual)', 'hng-commerce'); ?>

        </label>

        <p class="description">

            <?php esc_html_e('⚠️ CUIDADO: Quando habilitado, todos os reembolsos serão aprovados automaticamente e devolvidos ao cliente.', 'hng-commerce'); ?>

        </p>

        <?php

    }

}



