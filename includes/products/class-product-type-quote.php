<?php
/**
 * Produto de Orçamento - Quote Product Type
 * 
 * Lógica de negócio para produtos que requerem aprovação de orçamento.
 * Os campos de configuração (modo de entrega, campos personalizados) são
 * gerenciados pelo sistema unificado em class-product-type-fields.php
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Product_Type_Quote {
    
    /**

     * Instância única

     */

    private static $instance = null;

    

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

        // Frontend behavior

        add_filter('hng_product_add_to_cart_text', [$this, 'customize_add_to_cart_text'], 10, 2);

        add_filter('hng_product_price_html', [$this, 'customize_price_display'], 10, 2);

        

        // Checkout behavior

        add_action('hng_checkout_process', [$this, 'handle_quote_checkout'], 5);

        

        // AJAX handler para solicitação de orçamento

        add_action('wp_ajax_hng_request_quote', [$this, 'ajax_request_quote']);

        add_action('wp_ajax_nopriv_hng_request_quote', [$this, 'ajax_request_quote']);
        
        // REST API fallback (para contornar WAF que bloqueia admin-ajax.php)
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('hng-commerce/v1', '/request-quote', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_request_quote'],
            'permission_callback' => '__return_true',
        ]);
    }
    
    /**
     * REST API handler for quote request
     */
    public function rest_request_quote($request) {
        // Simulate POST data for the existing AJAX handler
        $params = $request->get_params();
        
        // Map parameters to $_POST
        $_POST = array_merge($_POST, $params);
        
        // Handle nonce - REST requests can skip nonce verification if using REST authentication
        // But we'll still validate the nonce if provided
        if (!empty($params['hng_quote_nonce'])) {
            if (!wp_verify_nonce($params['hng_quote_nonce'], 'hng_request_quote')) {
                return new WP_REST_Response([
                    'success' => false,
                    'data' => ['message' => __('Sessão expirada. Recarregue a página.', 'hng-commerce')]
                ], 400);
            }
        }
        
        // Use the same logic as AJAX handler but return REST response
        return $this->process_quote_request($params);
    }
    
    /**
     * Process quote request (shared logic for AJAX and REST)
     */
    private function process_quote_request($data) {
        // Verificar produto
        $product_id = isset($data['product_id']) ? absint($data['product_id']) : 0;
        if (!$product_id || get_post_type($product_id) !== 'hng_product') {
            return new WP_REST_Response([
                'success' => false,
                'data' => ['message' => __('Produto inválido.', 'hng-commerce')]
            ], 400);
        }
        
        // Dados do cliente
        $customer_id = 0;
        $customer_name = '';
        $customer_email = '';
        $customer_phone = '';
        
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $user = get_user_by('id', $user_id);
            $customer_id = $user_id;
            $customer_name = get_user_meta($user_id, '_hng_customer_name', true) ?: $user->display_name;
            $customer_email = $user->user_email;
            $customer_phone = get_user_meta($user_id, '_hng_customer_phone', true);
        } else {
            $customer_name = isset($data['customer_name']) ? sanitize_text_field($data['customer_name']) : '';
            $customer_email = isset($data['customer_email']) ? sanitize_email($data['customer_email']) : '';
            $customer_phone = isset($data['customer_phone']) ? preg_replace('/[^0-9]/', '', sanitize_text_field($data['customer_phone'])) : '';
            
            if (empty($customer_name) || empty($customer_email) || empty($customer_phone)) {
                return new WP_REST_Response([
                    'success' => false,
                    'data' => ['message' => __('Por favor, preencha todos os campos de contato.', 'hng-commerce')]
                ], 400);
            }
            
            if (!is_email($customer_email)) {
                return new WP_REST_Response([
                    'success' => false,
                    'data' => ['message' => __('E-mail inválido.', 'hng-commerce')]
                ], 400);
            }
        }
        
        // Campos personalizados do orçamento
        $quote_fields = isset($data['quote_fields']) && is_array($data['quote_fields']) 
            ? array_map('sanitize_text_field', $data['quote_fields']) 
            : [];
        
        // Validar campos obrigatórios
        $custom_fields = self::get_custom_fields($product_id);
        foreach ($custom_fields as $field) {
            $field_label = isset($field['label']) ? $field['label'] : (isset($field['name']) ? $field['name'] : '');
            $is_required = isset($field['required']) && $field['required'];
            
            if ($is_required && empty($field_label)) {
                continue;
            }
            
            if ($is_required && (empty($quote_fields[$field_label]) || trim($quote_fields[$field_label]) === '')) {
                return new WP_REST_Response([
                    'success' => false,
                    /* translators: %s: field label */
                    'data' => ['message' => sprintf(__('O campo "%s" é obrigatório.', 'hng-commerce'), $field_label)]
                ], 400);
            }
        }
        
        // Criar pedido de orçamento
        $product = get_post($product_id);
        $product_title = $product->post_title;
        
        // Gerar número do orçamento
        $quote_number = 'ORC-' . gmdate('Ymd') . '-' . wp_rand(1000, 9999);
        
        // Criar pedido
        $order_data = [
            'post_type' => 'hng_order',
            'post_status' => 'publish',
            'post_title' => $quote_number,
            'post_content' => '',
        ];
        
        $order_id = wp_insert_post($order_data);
        
        if (is_wp_error($order_id)) {
            return new WP_REST_Response([
                'success' => false,
                'data' => ['message' => __('Erro ao criar solicitação de orçamento.', 'hng-commerce')]
            ], 500);
        }
        
        // Salvar meta dados
        update_post_meta($order_id, '_order_number', $quote_number);
        update_post_meta($order_id, '_order_status', 'quote-pending');
        update_post_meta($order_id, '_quote_status', 'pending');
        update_post_meta($order_id, '_customer_id', $customer_id);
        update_post_meta($order_id, '_customer_name', $customer_name);
        update_post_meta($order_id, '_customer_email', $customer_email);
        update_post_meta($order_id, '_customer_phone', $customer_phone);
        update_post_meta($order_id, '_quote_fields', $quote_fields);
        update_post_meta($order_id, '_product_id', $product_id);
        update_post_meta($order_id, '_order_total', 0);
        update_post_meta($order_id, '_created_at', current_time('mysql'));
        
        // Salvar também na tabela de orders
        global $wpdb;
        $orders_table = $wpdb->prefix . 'hng_orders';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert(
            $orders_table,
            [
                'order_number' => $quote_number,
                'customer_id' => $customer_id,
                'customer_name' => $customer_name,
                'customer_email' => $customer_email,
                'billing_email' => $customer_email,
                'billing_first_name' => $customer_name,
                'billing_phone' => $customer_phone,
                'subtotal' => 0,
                'total' => 0,
                'status' => 'quote-pending',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%s', '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%s']
        );
        
        $db_order_id = $wpdb->insert_id;
        update_post_meta($order_id, '_order_id', $db_order_id);
        
        // Salvar itens do pedido
        $items = [
            [
                'product_id' => $product_id,
                'name' => $product_title,
                'quantity' => 1,
                'price' => 0,
                'total' => 0,
                'quote_fields' => $quote_fields,
            ]
        ];
        update_post_meta($order_id, '_order_items', $items);
        
        // Enviar e-mail de notificação para o admin
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        
        /* translators: 1: site name, 2: quote number */
        $subject = sprintf('[%1$s] Nova solicitação de orçamento - %2$s', $site_name, $quote_number);
        
        $message = __('Nova solicitação de orçamento recebida:\n\n', 'hng-commerce');
        /* translators: %s: quote number */
        $message .= sprintf(__('Número: %s\n', 'hng-commerce'), $quote_number);
        /* translators: %s: product title */
        $message .= sprintf(__('Produto: %s\n', 'hng-commerce'), $product_title);
        /* translators: %s: customer name */
        $message .= sprintf(__('Cliente: %s\n', 'hng-commerce'), $customer_name);
        /* translators: %s: customer email */
        $message .= sprintf(__('E-mail: %s\n', 'hng-commerce'), $customer_email);
        /* translators: %s: customer phone */
        $message .= sprintf(__('Telefone: %s\n\n', 'hng-commerce'), $customer_phone);
        
        if (!empty($quote_fields)) {
            $message .= __('Detalhes do Orçamento:\n', 'hng-commerce');
            foreach ($quote_fields as $field_name => $field_value) {
                /* translators: 1: field name, 2: field value */
                $message .= sprintf('- %1$s: %2$s\n', $field_name, $field_value);
            }
        }
        
        /* translators: %s: admin panel URL */
        $message .= sprintf(__('\nAcessar painel: %s\n', 'hng-commerce'), admin_url('admin.php?page=hng-orders&action=view&order_id=' . $db_order_id));
        
        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        wp_mail($admin_email, $subject, $message, $headers);
        
        // Enviar e-mail de confirmação para o cliente
        /* translators: 1: site name, 2: quote number */
        $customer_subject = sprintf('[%1$s] Recebemos sua solicitação de orçamento - %2$s', $site_name, $quote_number);
        
        /* translators: %s: customer name */
        $customer_message = sprintf(__('Olá %s,\n\n', 'hng-commerce'), $customer_name);
        $customer_message .= __('Recebemos sua solicitação de orçamento e nossa equipe irá analisá-la em breve.\n\n', 'hng-commerce');
        /* translators: %s: quote number */
        $customer_message .= sprintf(__('Número do orçamento: %s\n', 'hng-commerce'), $quote_number);
        /* translators: %s: product title */
        $customer_message .= sprintf(__('Produto: %s\n\n', 'hng-commerce'), $product_title);
        $customer_message .= __('Entraremos em contato através do e-mail ou telefone informado.\n\n', 'hng-commerce');
        /* translators: %s: site name */
        $customer_message .= sprintf(__('Atenciosamente,\nEquipe %s', 'hng-commerce'), $site_name);
        
        wp_mail($customer_email, $customer_subject, $customer_message, $headers);
        
        // Trigger action para integrações
        do_action('hng_quote_requested', $order_id, $product_id, $quote_fields);
        
        // URL do checkout com o ID do orçamento
        $checkout_url = home_url('/checkout?quote_order=' . $db_order_id);
        
        return new WP_REST_Response([
            'success' => true,
            'data' => [
                'message' => __('Entraremos em contato em breve com seu orçamento personalizado.', 'hng-commerce'),
                'quote_number' => $quote_number,
                'order_id' => $db_order_id,
                'checkout_url' => $checkout_url,
            ]
        ], 200);
    }

    

    /**

     * Customizar texto do botão de adicionar ao carrinho

     */

    public function customize_add_to_cart_text($text, $product) {

        if (is_object($product) && method_exists($product, 'get_type') && $product->get_type() === 'quote') {

            return __('Solicitar Orçamento', 'hng-commerce');

        }

        

        return $text;

    }

    

    /**

     * Customizar exibição de preço

     */

    public function customize_price_display($price_html, $product) {

        if (is_object($product) && method_exists($product, 'get_type') && $product->get_type() === 'quote') {

            return '<span class="hng-quote-price">' . __('Preço sob consulta', 'hng-commerce') . '</span>';

        }

        

        return $price_html;

    }

    

    /**

     * Processar checkout de produto de orçamento

     */

    public function handle_quote_checkout() {

        if (!function_exists('hng_cart')) {

            return;

        }

        

        $cart = hng_cart();

        

        if (!$cart || !method_exists($cart, 'get_cart')) {

            return;

        }

        

        // Verificar se há produtos de orçamento no carrinho

        $has_quote_products = false;

        foreach ($cart->get_cart() as $cart_item) {

            if (isset($cart_item['data']) && is_object($cart_item['data']) && 

                method_exists($cart_item['data'], 'get_type') && 

                $cart_item['data']->get_type() === 'quote') {

                $has_quote_products = true;

                break;

            }

        }

        

        if ($has_quote_products) {

            // Alterar status inicial do pedido para "pending approval"

            add_filter('hng_order_data_before_insert', function($order_data) {

                $order_data['status'] = 'hng-pending-approval';

                return $order_data;

            });

            

            // Desabilitar geração de pagamento imediato

            add_filter('hng_process_payment', '__return_false');

        }

    }

    

    /**

     * Verificar se um produto é do tipo orçamento

     * 

     * @param int $product_id ID do produto

     * @return bool

     */

    public static function is_quote_product($product_id) {

        $product_type = get_post_meta($product_id, '_hng_product_type', true);

        if (empty($product_type)) {

            $product_type = get_post_meta($product_id, '_product_type', true);

        }

        return $product_type === 'quote';

    }

    

    /**

     * Obter campos personalizados de um produto de orçamento

     * 

     * @param int $product_id ID do produto

     * @return array

     */

    public static function get_custom_fields($product_id) {

        $fields = get_post_meta($product_id, '_quote_custom_fields', true);

        return is_array($fields) ? $fields : [];

    }

    

    /**

     * Verificar se produto de orçamento requer frete

     * 

     * @param int $product_id ID do produto

     * @return bool

     */

    public static function requires_shipping($product_id) {

        return (bool) get_post_meta($product_id, '_quote_requires_shipping', true);

    }

    

    /**

     * AJAX handler para solicitação de orçamento

     */

    public function ajax_request_quote() {

        // Verificar nonce

        if (!isset($_POST['hng_quote_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['hng_quote_nonce'])), 'hng_request_quote')) {

            wp_send_json_error(['message' => __('Sessão expirada. Recarregue a página.', 'hng-commerce')]);

            return;

        }

        

        // Verificar produto

        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;

        if (!$product_id || get_post_type($product_id) !== 'hng_product') {

            wp_send_json_error(['message' => __('Produto inválido.', 'hng-commerce')]);

            return;

        }

        

        // Dados do cliente

        $customer_id = 0;

        $customer_name = '';

        $customer_email = '';

        $customer_phone = '';

        

        if (is_user_logged_in()) {

            $user_id = get_current_user_id();

            $user = get_user_by('id', $user_id);

            $customer_id = $user_id;

            $customer_name = get_user_meta($user_id, '_hng_customer_name', true) ?: $user->display_name;

            $customer_email = $user->user_email;

            $customer_phone = get_user_meta($user_id, '_hng_customer_phone', true);

        } else {

            // Validar dados do formulário

            $customer_name = isset($_POST['customer_name']) ? sanitize_text_field(wp_unslash($_POST['customer_name'])) : '';

            $customer_email = isset($_POST['customer_email']) ? sanitize_email(wp_unslash($_POST['customer_email'])) : '';

            $customer_phone = isset($_POST['customer_phone']) ? preg_replace('/[^0-9]/', '', sanitize_text_field(wp_unslash($_POST['customer_phone']))) : '';

            

            if (empty($customer_name) || empty($customer_email) || empty($customer_phone)) {

                wp_send_json_error(['message' => __('Por favor, preencha todos os campos de contato.', 'hng-commerce')]);

                return;

            }

            

            if (!is_email($customer_email)) {

                wp_send_json_error(['message' => __('E-mail inválido.', 'hng-commerce')]);

                return;

            }

        }

        

        // Campos personalizados do orçamento

        $quote_fields = isset($_POST['quote_fields']) && is_array($_POST['quote_fields']) 

            ? array_map('sanitize_text_field', wp_unslash($_POST['quote_fields'])) 

            : [];

        

        // Validar campos obrigatórios

        $custom_fields = self::get_custom_fields($product_id);

        foreach ($custom_fields as $field) {

            $field_label = isset($field['label']) ? $field['label'] : (isset($field['name']) ? $field['name'] : '');

            $is_required = isset($field['required']) && $field['required'];

            

            if ($is_required && !empty($field_label) && empty($quote_fields[$field_label])) {

                /* translators: %s: field label */

                wp_send_json_error([

                    /* translators: %s: field name */
                    'message' => sprintf(__('O campo "%s" é obrigatório.', 'hng-commerce'), $field_label)

                ]);

                return;

            }

        }

        

        // Criar pedido de orçamento

        $product = function_exists('hng_get_product') ? hng_get_product($product_id) : null;

        $product_title = $product ? $product->get_title() : get_the_title($product_id);

        

        // Gerar número do orçamento

        $quote_number = 'ORC-' . strtoupper(wp_generate_password(8, false));

        

        // Criar post do tipo hng_order com status pending-approval

        $order_data = [

            'post_type' => 'hng_order',

            'post_status' => 'publish',

            /* translators: 1: quote number, 2: product title */

            'post_title' => sprintf(__('Orçamento %1$s - %2$s', 'hng-commerce'), $quote_number, $product_title),

            'post_author' => $customer_id ?: 1,

        ];

        

        $order_id = wp_insert_post($order_data);

        

        if (is_wp_error($order_id)) {

            wp_send_json_error(['message' => __('Erro ao criar solicitação. Tente novamente.', 'hng-commerce')]);

            return;

        }

        

        // Salvar metadados do pedido

        update_post_meta($order_id, '_order_number', $quote_number);

        update_post_meta($order_id, '_order_status', 'pending-approval');

        update_post_meta($order_id, '_order_type', 'quote');

        update_post_meta($order_id, '_product_id', $product_id);

        update_post_meta($order_id, '_product_title', $product_title);

        update_post_meta($order_id, '_customer_id', $customer_id);

        update_post_meta($order_id, '_customer_name', $customer_name);

        update_post_meta($order_id, '_customer_email', $customer_email);

        update_post_meta($order_id, '_customer_phone', $customer_phone);

        update_post_meta($order_id, '_quote_fields', $quote_fields);

        update_post_meta($order_id, '_order_total', 0);

        update_post_meta($order_id, '_order_date', current_time('mysql'));

        

        // Items do pedido

        $items = [

            [

                'product_id' => $product_id,

                'name' => $product_title,

                'quantity' => 1,

                'price' => 0,

                'total' => 0,

                'quote_fields' => $quote_fields,

            ]

        ];

        update_post_meta($order_id, '_order_items', $items);

        

        // Enviar e-mail de notificação para o admin

        $admin_email = get_option('admin_email');

        $site_name = get_bloginfo('name');

        

        $subject = sprintf('[%s] Nova solicitação de orçamento - %s', $site_name, $quote_number);

        

        $message = sprintf(__("Nova solicitação de orçamento recebida:\n\n", 'hng-commerce'));

        /* translators: %s: quote number */

        $message .= sprintf(__('Número: %s\n', 'hng-commerce'), $quote_number);

        /* translators: %s: product title */

        $message .= sprintf(__('Produto: %s\n', 'hng-commerce'), $product_title);

        /* translators: %s: customer name */

        $message .= sprintf(__('Cliente: %s\n', 'hng-commerce'), $customer_name);

        /* translators: %s: customer email */

        $message .= sprintf(__('E-mail: %s\n', 'hng-commerce'), $customer_email);

        /* translators: %s: customer phone */

        $message .= sprintf(__("Telefone: %s\n\n", 'hng-commerce'), $customer_phone);

        

        if (!empty($quote_fields)) {

            $message .= __("Detalhes do Orçamento:\n", 'hng-commerce');

            foreach ($quote_fields as $field_name => $field_value) {

                $message .= sprintf("- %s: %s\n", $field_name, $field_value);

            }

        }

        

        /* translators: %s: admin panel URL */
        $message .= sprintf(__('\nAcessar painel: %s\n', 'hng-commerce'), admin_url('edit.php?post_type=hng_order'));

        

        $headers = ['Content-Type: text/plain; charset=UTF-8'];

        wp_mail($admin_email, $subject, $message, $headers);

        

        // Enviar e-mail de confirmação para o cliente

        $customer_subject = sprintf('[%s] Recebemos sua solicitação de orçamento - %s', $site_name, $quote_number);

        

        /* translators: %s: customer name */

        $customer_message = sprintf(__('Olá %s,\n\n', 'hng-commerce'), $customer_name);

        $customer_message .= __('Recebemos sua solicitação de orçamento e nossa equipe irá analisá-la em breve.\n\n', 'hng-commerce');

        /* translators: %s: quote number */

        $customer_message .= sprintf(__('Número do orçamento: %s\n', 'hng-commerce'), $quote_number);

        /* translators: %s: product title */

        $customer_message .= sprintf(__('Produto: %s\n\n', 'hng-commerce'), $product_title);

        $customer_message .= __('Entraremos em contato através do e-mail ou telefone informado.\n\n', 'hng-commerce');

        /* translators: %s: site name */

        $customer_message .= sprintf(__("Atenciosamente,\nEquipe %s", 'hng-commerce'), $site_name);

        

        wp_mail($customer_email, $customer_subject, $customer_message, $headers);

        

        // Trigger action para integrações

        do_action('hng_quote_requested', $order_id, $product_id, $quote_fields);

        

        // URL do checkout com o ID do orçamento

        $checkout_url = home_url('/checkout?quote_order=' . $order_id);

        

        wp_send_json_success([

            'message' => __('Entraremos em contato em breve com seu orçamento personalizado.', 'hng-commerce'),

            'quote_number' => $quote_number,

            'order_id' => $order_id,

            'checkout_url' => $checkout_url,

        ]);

    }

}



// Inicializar sempre para ter os handlers AJAX disponíveis

add_action('init', function() {

    HNG_Product_Type_Quote::instance();

}, 20);

