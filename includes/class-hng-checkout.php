<?php

/**

 * Checkout - Processamento de Checkout

 *

 * @package HNG_Commerce

 * @since 1.0.0

 */



if (!defined('ABSPATH')) {

    exit;

}



class HNG_Checkout {

    

    /**

     * Instï¿½ncia ï¿½nica

     */

    private static $instance = null;

    

    /**

     * Obter instï¿½ncia

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

        // Processar checkout no hook 'init' (antes de qualquer output)

        add_action('init', [$this, 'process_checkout'], 20);

    }

    

    /**

     * Processar checkout

     */

    public function process_checkout() {

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verificado em hng_validate_checkout_nonce() antes do processamento

        $post = function_exists('wp_unslash') ? wp_unslash($_POST) : $_POST;



        if (!isset($post['hng_place_order'])) {

            return;

        }



        // Verificar nonce

        if (!isset($post['hng_checkout_nonce']) || !wp_verify_nonce($post['hng_checkout_nonce'], 'hng_checkout')) {

            hng_add_notice(__('Erro de seguranï¿½a. Recarregue a pï¿½gina e tente novamente.', 'hng-commerce'), 'error');

            return;

        }

        // ========================================
        // FLUXO DE CHECKOUT PARA PEDIDOS DE ORÇAMENTO
        // ========================================
        if (!empty($post['quote_order_id'])) {
            $this->process_quote_checkout($post);
            return;
        }

        

        // Verificar carrinho

        $cart = hng_cart();

        if (!$cart || $cart->is_empty()) {

            wp_safe_redirect(hng_get_cart_url());

            exit;

        }



        // Verificar se login é requerido (configuração global)

        $settings = get_option('hng_commerce_settings', []);

        $require_login = ($settings['require_login_to_purchase'] ?? 'no') === 'yes';

        

        if ($require_login && !is_user_logged_in()) {

            hng_add_notice(__('Você precisa estar logado para finalizar uma compra.', 'hng-commerce'), 'error');

            return;

        }



        // Exigir login para tipos sensíveis (assinatura, agendamento, orçamento)

        $types_requiring_login = ['subscription', 'appointment', 'quote'];

        $cart_types = $this->get_cart_product_types($cart);

        if (!is_user_logged_in()) {

            $needs_login = array_intersect($types_requiring_login, $cart_types);

            if (!empty($needs_login)) {

                hng_add_notice(__('Você precisa estar logado para comprar assinaturas, agendamentos ou orçamentos.', 'hng-commerce'), 'error');

                return;

            }

        }

        

        // Validar campos

        $errors = $this->validate_checkout_fields($post);



        // Capturar campos personalizados do cliente enviados no checkout

        $custom_fields_checkout = isset($post['hng_cf_checkout']) ? $post['hng_cf_checkout'] : [];

        if (!empty($custom_fields_checkout) && is_array($custom_fields_checkout)) {

            // Sanitizar e salvar temporariamente na sessï¿½o para uso na criaï¿½ï¿½o do pedido

            $cf_sanitized = [];

            foreach ($custom_fields_checkout as $k => $v) {

                $cf_sanitized[sanitize_key($k)] = is_array($v) ? array_map('sanitize_text_field', $v) : sanitize_text_field($v);

            }

            $_SESSION['hng_cf_checkout'] = $cf_sanitized;

        } else {

            unset($_SESSION['hng_cf_checkout']);

        }

        

        if (!empty($errors)) {

            foreach ($errors as $error) {

                hng_add_notice($error, 'error');

            }

            return;

        }

        // Validar estoque

        foreach ($cart->get_cart() as $item) {

            $product = $item['data'];



            if (!$product->is_in_stock()) {

                hng_add_notice(

                    /* translators: %1$s: product name */

                    sprintf(esc_html__('Produto "%1$s" est fora de estoque.', 'hng-commerce'), $product->get_name()),

                    'error'

                );

                return;

            }



            if ($product->manages_stock()) {

                if ($item['quantity'] > $product->get_stock_quantity()) {

                    hng_add_notice(

                        /* translators: %1$s: product name, %2$d: available stock quantity */

                        sprintf(esc_html__('Quantidade indisponï¿½vel para "%1$s". Estoque: %2$d unidades.', 'hng-commerce'),

                                $product->get_name(),

                                $product->get_stock_quantity()

                            ),

                        'error'

                    );

                    return;

                }

            }

        }



            // Preparar dados do pedido

            $order_data = $this->prepare_order_data($post);

            $order_data['product_type'] = $this->determine_order_product_type($cart);

            $order_data = apply_filters('hng_checkout_prepare_order_data', $order_data, $post);

            // Hook: apï¿½s preparaï¿½ï¿½o dos dados do pedido

            do_action('hng_checkout_after_prepare_order_data', $order_data, $post);



            // Criar pedido

            $order = apply_filters('hng_checkout_create_order', HNG_Order::create_from_cart($order_data), $order_data, $post);

            // Hook: apï¿½s criaï¿½ï¿½o do pedido

            do_action('hng_checkout_after_create_order', $order, $order_data, $post);



            if (is_wp_error($order)) {

                hng_add_notice($order->get_error_message(), 'error');

                return;

            }



            // Se for produto do tipo orçamento, pular processamento de pagamento

            if (($order_data['product_type'] ?? '') === 'quote') {

                // Atualiza status para aguardar aprovação do administrador

                $order->update_status('hng-pending-approval', __('Pedido de orçamento recebido. Aguardando aprovação do administrador.', 'hng-commerce'));

                // Marcar resultado como sucesso sem pagamento

                $payment_result = true;

            } else {

                // Processar pagamento (usar dados preparados e sanitizados)

                $payment_result = apply_filters('hng_checkout_process_payment', $this->process_payment($order, $order_data), $order, $order_data);

            }

            // Hook: apï¿½s processamento do pagamento

            do_action('hng_checkout_after_process_payment', $payment_result, $order, $order_data);



            if (is_wp_error($payment_result)) {

                hng_add_notice($payment_result->get_error_message(), 'error');

                $order->update_status('hng-failed', $payment_result->get_error_message());

                return;

            }



            // Enviar e-mail

            do_action('hng_order_created', $order->get_id());



            // Incrementar uso dos cupons

            $this->increment_coupon_usage($order);



            // Limpar carrinho

            $cart->empty_cart();



            // Hook: finalizaï¿½ï¿½o do checkout (antes do redirect)

            do_action('hng_checkout_complete', $order, $post);



            // Redirecionar para página de confirmação

            wp_safe_redirect($this->get_order_received_url($order));

            exit;

    }

    

    /**
     * Processar checkout de pedido de orçamento (ordem já existe)
     *
     * @param array $post Dados do formulário
     */
    private function process_quote_checkout($post) {
        $quote_post_id = absint($post['quote_order_id']);

        // Validar que o post existe e é do tipo hng_order
        $order_post = get_post($quote_post_id);
        if (!$order_post || $order_post->post_type !== 'hng_order') {
            hng_add_notice(__('Pedido de orçamento não encontrado.', 'hng-commerce'), 'error');
            return;
        }

        // Validar que o pedido pertence ao cliente logado
        if (!is_user_logged_in()) {
            hng_add_notice(__('Você precisa estar logado para finalizar este pedido.', 'hng-commerce'), 'error');
            return;
        }
        $customer_id = get_post_meta($quote_post_id, '_hng_customer_id', true);
        if (intval($customer_id) !== get_current_user_id()) {
            hng_add_notice(__('Você não tem permissão para acessar este pedido.', 'hng-commerce'), 'error');
            return;
        }

        // Validar status do pedido (deve estar aprovado ou aguardando pagamento)
        $order_status = get_post_meta($quote_post_id, '_hng_order_status', true);
        $allowed_statuses = ['quote_approved', 'awaiting_payment', 'quote_sent'];
        if (!in_array($order_status, $allowed_statuses, true)) {
            hng_add_notice(__('Este pedido não está disponível para pagamento.', 'hng-commerce'), 'error');
            return;
        }

        // Obter o order_id do banco de dados
        $db_order_id = get_post_meta($quote_post_id, '_order_id', true);
        if (!$db_order_id) {
            // Fallback: tentar buscar pelo post_id na tabela
            global $wpdb;
            $table = hng_db_full_table_name('hng_orders');
            $db_order_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM `{$table}` WHERE post_id = %d",
                $quote_post_id
            ));
        }

        if (!$db_order_id) {
            hng_add_notice(__('Pedido não encontrado no banco de dados.', 'hng-commerce'), 'error');
            return;
        }

        // Carregar o pedido existente
        $order = new HNG_Order(intval($db_order_id));
        if (!$order->get_id()) {
            hng_add_notice(__('Erro ao carregar pedido.', 'hng-commerce'), 'error');
            return;
        }

        // Validar campos obrigatórios (sem shipping se não necessário)
        $errors = $this->validate_quote_checkout_fields($post, $quote_post_id);
        if (!empty($errors)) {
            foreach ($errors as $error) {
                hng_add_notice($error, 'error');
            }
            return;
        }

        // Atualizar dados de faturamento no pedido
        $billing_data = [
            'billing_first_name' => sanitize_text_field($post['billing_first_name'] ?? ''),
            'billing_last_name'  => sanitize_text_field($post['billing_last_name'] ?? ''),
            'billing_email'      => sanitize_email($post['billing_email'] ?? ''),
            'billing_phone'      => sanitize_text_field($post['billing_phone'] ?? ''),
            'billing_cpf'        => isset($post['billing_cpf']) ? preg_replace('/[^0-9]/', '', $post['billing_cpf']) : '',
        ];

        // Atualizar dados de endereço se houver shipping
        $quote_needs_shipping = get_post_meta($quote_post_id, '_quote_needs_shipping', true);
        if ($quote_needs_shipping !== '0') {
            $billing_data['billing_postcode']     = isset($post['billing_postcode']) ? preg_replace('/[^0-9]/', '', $post['billing_postcode']) : '';
            $billing_data['billing_address_1']    = sanitize_text_field($post['billing_address_1'] ?? '');
            $billing_data['billing_number']       = sanitize_text_field($post['billing_number'] ?? '');
            $billing_data['billing_address_2']    = sanitize_text_field($post['billing_address_2'] ?? '');
            $billing_data['billing_neighborhood'] = sanitize_text_field($post['billing_neighborhood'] ?? '');
            $billing_data['billing_city']         = sanitize_text_field($post['billing_city'] ?? '');
            $billing_data['billing_state']        = sanitize_text_field($post['billing_state'] ?? '');
        }

        // Salvar dados de faturamento como post meta
        foreach ($billing_data as $key => $value) {
            update_post_meta($quote_post_id, '_' . $key, $value);
        }

        // Atualizar dados no banco de dados também
        global $wpdb;
        $table = hng_db_full_table_name('hng_orders');
        $update_db = [];
        if (!empty($billing_data['billing_email'])) {
            $update_db['customer_email'] = $billing_data['billing_email'];
        }
        $update_db['payment_method'] = sanitize_text_field($post['payment_method'] ?? '');
        $update_db['payment_method_title'] = hng_get_payment_method_title($update_db['payment_method']);
        
        if (!empty($update_db)) {
            $wpdb->update($table, $update_db, ['id' => $db_order_id]);
        }

        // Salvar método de pagamento como post meta
        $payment_method = sanitize_text_field($post['payment_method'] ?? '');
        update_post_meta($quote_post_id, '_payment_method', $payment_method);
        update_post_meta($quote_post_id, '_payment_method_title', hng_get_payment_method_title($payment_method));

        // Atualizar status para aguardando pagamento
        update_post_meta($quote_post_id, '_hng_order_status', 'awaiting_payment');

        // Preparar order_data para process_payment
        $order_data = [
            'payment_method' => $payment_method,
            'billing_cpf'    => $billing_data['billing_cpf'] ?? '',
            'product_type'   => 'quote',
        ];

        // Processar pagamento
        $payment_result = $this->process_payment($order, $order_data);

        if (is_wp_error($payment_result)) {
            hng_add_notice($payment_result->get_error_message(), 'error');
            $order->update_status('hng-failed', $payment_result->get_error_message());
            return;
        }

        // Enviar e-mail de pedido
        do_action('hng_order_created', $order->get_id());

        // Hook: finalização do checkout
        do_action('hng_checkout_complete', $order, $post);

        // Redirecionar para página de confirmação
        wp_safe_redirect($this->get_order_received_url($order));
        exit;
    }

    /**
     * Validar campos do checkout para orçamento
     *
     * @param array $data Dados do formulário
     * @param int $quote_post_id Post ID do pedido
     * @return array Lista de erros
     */
    private function validate_quote_checkout_fields($data, $quote_post_id) {
        $errors = [];

        $required_fields = [
            'billing_first_name' => __('Nome', 'hng-commerce'),
            'billing_last_name'  => __('Sobrenome', 'hng-commerce'),
            'billing_email'      => __('E-mail', 'hng-commerce'),
            'billing_phone'      => __('Telefone', 'hng-commerce'),
        ];

        // Se precisar de endereço de entrega
        $quote_needs_shipping = get_post_meta($quote_post_id, '_quote_needs_shipping', true);
        if ($quote_needs_shipping !== '0') {
            $required_fields['billing_postcode']  = __('CEP', 'hng-commerce');
            $required_fields['billing_address_1'] = __('Endereço', 'hng-commerce');
            $required_fields['billing_city']      = __('Cidade', 'hng-commerce');
            $required_fields['billing_state']     = __('Estado', 'hng-commerce');
        }

        foreach ($required_fields as $field => $label) {
            if (empty($data[$field])) {
                /* translators: %1$s = Rótulo do campo requerido */
                $errors[] = sprintf(esc_html__('O campo "%1$s" é obrigatório.', 'hng-commerce'), $label);
            }
        }

        // Validar e-mail
        if (!empty($data['billing_email']) && !is_email($data['billing_email'])) {
            $errors[] = __('E-mail inválido.', 'hng-commerce');
        }

        // Validar CPF/CNPJ
        if (!empty($data['billing_cpf'])) {
            $cpf = preg_replace('/[^0-9]/', '', $data['billing_cpf']);
            if (strlen($cpf) !== 11 && strlen($cpf) !== 14) {
                $errors[] = __('CPF/CNPJ inválido.', 'hng-commerce');
            }
        }

        // Validar método de pagamento
        $enabled_methods = hng_get_active_gateway_methods();
        if (empty($data['payment_method'])) {
            $errors[] = __('Selecione um método de pagamento.', 'hng-commerce');
        } elseif (!in_array($data['payment_method'], $enabled_methods, true)) {
            $errors[] = __('Método de pagamento não disponível.', 'hng-commerce');
        }

        // Validar termos
        if (empty($data['terms'])) {
            $errors[] = __('Você deve concordar com os termos e condições.', 'hng-commerce');
        }

        return $errors;
    }

    /**

     * Validar campos do checkout

     *

     * @param array $data Dados do formulário

     * @return array Lista de erros

     */

    private function validate_checkout_fields($data) {

        $errors = [];

        

        $required_fields = [

            'billing_first_name' => __('Nome', 'hng-commerce'),

            'billing_last_name' => __('Sobrenome', 'hng-commerce'),

            'billing_email' => __('E-mail', 'hng-commerce'),

            'billing_phone' => __('Telefone', 'hng-commerce'),

        ];

        

        // Se precisar de endereço de entrega

        $cart = hng_cart();

        if ($cart && $cart->needs_shipping()) {

            $required_fields['billing_postcode'] = __('CEP', 'hng-commerce');

            $required_fields['billing_address_1'] = __('Endereço', 'hng-commerce');

            $required_fields['billing_city'] = __('Cidade', 'hng-commerce');

            $required_fields['billing_state'] = __('Estado', 'hng-commerce');

        }

        

        foreach ($required_fields as $field => $label) {

            if (empty($data[$field])) {

                /* translators: %1$s: field label (e.g. Nome, E-mail) */

                $errors[] = sprintf(esc_html__('O campo "%1$s" ï¿½ obrigatï¿½rio.', 'hng-commerce'), $label);

            }

        }

        

        // Validar e-mail

        if (!empty($data['billing_email']) && !is_email($data['billing_email'])) {

            $errors[] = __('E-mail invï¿½lido.', 'hng-commerce');

        }

        

        // Validar CPF/CNPJ

        if (!empty($data['billing_cpf'])) {

            $cpf = preg_replace('/[^0-9]/', '', $data['billing_cpf']);

            if (strlen($cpf) !== 11 && strlen($cpf) !== 14) {

                $errors[] = __('CPF/CNPJ invï¿½lido.', 'hng-commerce');

            }

        }

        

        // Validar CEP

        if (!empty($data['billing_postcode'])) {

            $cep = preg_replace('/[^0-9]/', '', $data['billing_postcode']);

            if (strlen($cep) !== 8) {

                $errors[] = __('CEP invï¿½lido.', 'hng-commerce');

            }

        }

        

        // Validar mï¿½todo de pagamento dinï¿½mico

        $enabled_methods = hng_get_active_gateway_methods();

        if (empty($data['payment_method'])) {

            $errors[] = __('Selecione um mï¿½todo de pagamento.', 'hng-commerce');

        } elseif (!in_array($data['payment_method'], $enabled_methods, true)) {

            $errors[] = __('Mï¿½todo de pagamento nï¿½o disponï¿½vel.', 'hng-commerce');

        }

        

        // Validar termos

        if (empty($data['terms'])) {

            $errors[] = __('Vocï¿½ deve concordar com os termos e condiï¿½ï¿½es.', 'hng-commerce');

        }

        

        return $errors;

    }

    

    /**

     * Normalizar tipo de produto para valores suportados

     */

    private function normalize_product_type($type) {

        if (class_exists('HNG_Product_Types')) {

            return HNG_Product_Types::normalize($type);

        }



        $type = sanitize_key($type ?: 'physical');

        if ($type === 'simple') {

            $type = 'physical';

        }

        $allowed = ['physical', 'digital', 'subscription', 'quote', 'appointment'];

        return in_array($type, $allowed, true) ? $type : 'physical';

    }



    /**

     * Obter tipos de produto presentes no carrinho

     */

    private function get_cart_product_types($cart) {

        $types = [];

        foreach ($cart->get_cart() as $item) {

            $product = $item['data'];

            $types[] = $this->normalize_product_type($product->get_product_type());

        }

        return array_values(array_unique($types));

    }



    /**

     * Determinar tipo predominante do pedido (prioridade: assinatura > agendamento > orçamento > digital > físico)

     */

    private function determine_order_product_type($cart) {

        $types = $this->get_cart_product_types($cart);

        $priority = ['subscription', 'appointment', 'quote', 'digital', 'physical'];

        foreach ($priority as $p) {

            if (in_array($p, $types, true)) {

                return $p;

            }

        }

        return 'physical';

    }



    /**

     * Preparar dados do pedido

     */

    private function prepare_order_data($data) {

        $cart = hng_cart();

        

        // Calcular frete

        $shipping_cost = 0;

        if ($cart->needs_shipping() && !empty($data['shipping_method'])) {

            $shipping_cost = $this->calculate_shipping($data['billing_postcode'], $data['shipping_method']);

        }

        

        // Verificar se tem frete grï¿½tis por cupom

        if ($cart->has_free_shipping()) {

            $shipping_cost = 0;

        }

        

        // Calcular desconto dos cupons

        $discount = $cart->get_discount_total();

        $coupon_codes = $cart->get_coupon_codes();

        

        // Mï¿½todo de pagamento dinï¿½mico conforme configuraï¿½ï¿½es

        $enabled_methods = hng_get_active_gateway_methods();

        $payment_methods = [];

        foreach ($enabled_methods as $method) {

            $payment_methods[$method] = hng_get_payment_method_title($method);

        }

        

        // Sanitizar campos de cliente antes de retornar

        $billing_first_name = sanitize_text_field($data['billing_first_name'] ?? '');

        $billing_last_name = sanitize_text_field($data['billing_last_name'] ?? '');

        $billing_email = sanitize_email($data['billing_email'] ?? '');

        $billing_phone = sanitize_text_field($data['billing_phone'] ?? '');

        $billing_cpf = isset($data['billing_cpf']) ? preg_replace('/[^0-9]/', '', $data['billing_cpf']) : '';

        $billing_postcode = isset($data['billing_postcode']) ? preg_replace('/[^0-9]/', '', $data['billing_postcode']) : '';

        $billing_address_1 = sanitize_text_field($data['billing_address_1'] ?? '');

        $billing_number = sanitize_text_field($data['billing_number'] ?? '');

        $billing_address_2 = sanitize_text_field($data['billing_address_2'] ?? '');

        $billing_neighborhood = sanitize_text_field($data['billing_neighborhood'] ?? '');

        $billing_city = sanitize_text_field($data['billing_city'] ?? '');

        $billing_state = sanitize_text_field($data['billing_state'] ?? '');

        $shipping_method = sanitize_text_field($data['shipping_method'] ?? '');

        $order_comments = isset($data['order_comments']) ? sanitize_textarea_field($data['order_comments']) : '';

        $payment_method = sanitize_text_field($data['payment_method'] ?? '');



        return [

            'billing_first_name' => $billing_first_name,

            'billing_last_name' => $billing_last_name,

            'billing_email' => $billing_email,

            'billing_phone' => $billing_phone,

            'billing_cpf' => $billing_cpf,

            'billing_postcode' => $billing_postcode,

            'billing_address_1' => $billing_address_1,

            'billing_number' => $billing_number,

            'billing_address_2' => $billing_address_2,

            'billing_neighborhood' => $billing_neighborhood,

            'billing_city' => $billing_city,

            'billing_state' => $billing_state,

            'shipping_method' => $shipping_method,

            'shipping_cost' => $shipping_cost,

            'discount' => $discount,

            'coupon_codes' => implode(',', $coupon_codes),

            'payment_method' => $payment_method,

            'payment_method_title' => $payment_methods[$payment_method] ?? '',

            'order_comments' => $order_comments,

        ];

    }

    

    /**

     * Calcular frete

     */

    private function calculate_shipping($postcode, $method) {

        $zipcode = preg_replace('/\D/', '', (string) $postcode);

        if (strlen($zipcode) !== 8) {

            return 0;

        }



        $cart = hng_cart();

        $manager = HNG_Shipping_Manager::instance();



        // Reutilizar cotações se forem do mesmo CEP

        $available = $cart->get_available_shipping_rates();

        if (!empty($available['rates']) && ($available['postcode'] ?? '') === $zipcode) {

            $rates = $available['rates'];

        } else {

            $package = $manager->build_package_from_cart($zipcode);

            $rates = $manager->calculate_shipping($package);



            if (!is_wp_error($rates)) {

                $cart->set_available_shipping_rates($zipcode, $rates);

            }

        }



        if (is_wp_error($rates) || empty($rates)) {

            return 0;

        }



        foreach ($rates as $rate) {

            if (!isset($rate['id'])) {

                continue;

            }



            if ((string) $rate['id'] === (string) $method) {

                // Persistir seleção para recalcular total corretamente

                $cart->select_shipping_rate($rate['id']);

                return floatval($rate['cost'] ?? 0);

            }

        }



        return 0;

    }

    

    /**

     * Processar pagamento

     */

    private function process_payment($order, $data) {

        $payment_method = $data['payment_method'];



        // Selecionar gateway dinamicamente por m e9todo

        $gateway = $this->resolve_gateway_for_method($payment_method);

        if (!$gateway) {

            // Fallback manual quando nenhum gateway est e1 configurado para o m e9todo

            switch ($payment_method) {

                case 'pix':

                    $order->update_status('hng-pending', __('Aguardando pagamento PIX (manual).', 'hng-commerce'));

                    break;

                case 'credit_card':

                    $order->update_status('hng-pending', __('Aguardando processamento do cart e3o (manual).', 'hng-commerce'));

                    break;

                case 'boleto':

                    $order->update_status('hng-pending', __('Aguardando pagamento do boleto (manual).', 'hng-commerce'));

                    break;

                default:

                    return new WP_Error('invalid_payment', __('M e9todo de pagamento inv e1lido.', 'hng-commerce'));

            }

            return true;

        }

        

        // === SEGURANï¿½A: CALCULAR TAXAS NA VPS (FONTE DA VERDADE) ===

        

        $api_client = HNG_API_Client::instance();

        

        // 1. Verificar status do merchant (Kill Switch)

        $merchant_status = $api_client->verify_merchant();

        

        if (is_wp_error($merchant_status)) {

            // Conta banida ou offline?

            if ($merchant_status->get_error_code() === 'banned') {

                return new WP_Error('merchant_banned', 

                    __('Sua conta foi suspensa. Entre em contato com o suporte.', 'hng-commerce'));

            }

            

            // VPS offline: continuar com fallback (serï¿½ calculado localmente)

            $msg = 'HNG: VPS offline no checkout - ' . $merchant_status->get_error_message();

            if (function_exists('hng_files_log_append')) { hng_files_log_append(HNG_COMMERCE_PATH . 'logs/checkout.log', $msg . PHP_EOL); }

        }

        

        // 2. Calcular taxas na VPS (com validaï¿½ï¿½o HMAC)

        $product_type = $data['product_type'] ?? 'physical';

        if (class_exists('HNG_Product_Types')) {

            $product_type = HNG_Product_Types::normalize($product_type);

        } else {

            $product_type = sanitize_key($product_type);

        }

        // Nome do gateway atual (para c e1lculo de taxas na VPS)

        $gateway_name = method_exists($gateway, 'id') ? $gateway->id : (property_exists($gateway, 'id') ? $gateway->id : 'generic');

        

        $fee_data = $api_client->calculate_fee([

            'order_id' => $order->get_post_id(),

            'amount' => $order->get_total(),

            'product_type' => $product_type,

            'gateway' => $gateway_name,

            'payment_method' => $payment_method

        ]);

        

        // Se VPS offline, $fee_data terá flag 'is_fallback' = true

        if (isset($fee_data['is_fallback']) && $fee_data['is_fallback']) {

            $msg = 'HNG: Usando cálculo local (fallback) para Order #' . $order->get_post_id();

            if (function_exists('hng_files_log_append')) { hng_files_log_append(HNG_COMMERCE_PATH . 'logs/checkout.log', $msg . PHP_EOL); }

        }

        

        // 3. Salvar taxas no pedido (para auditoria)

        update_post_meta($order->get_post_id(), '_hng_plugin_fee', $fee_data['plugin_fee']);

        update_post_meta($order->get_post_id(), '_hng_gateway_fee', $fee_data['gateway_fee']);

        update_post_meta($order->get_post_id(), '_hng_net_amount', $fee_data['net_amount']);

        update_post_meta($order->get_post_id(), '_hng_tier', $fee_data['tier']);

        update_post_meta($order->get_post_id(), '_hng_is_fallback', $fee_data['is_fallback'] ?? false);

        

        // Verificar configura e7 e3o do gateway selecionado

        if (!method_exists($gateway, 'is_configured') || !$gateway->is_configured()) {

            return new WP_Error('gateway_not_configured', __('Gateway de pagamento n e3o configurado.', 'hng-commerce'));

        }

        

        // Preparar dados do pagamento

        $payment_data = [

            'method' => $payment_method,

            'cpf' => $data['billing_cpf'],

        ];

        

        // Adicionar dados especï¿½ficos do mï¿½todo

        switch ($payment_method) {

            case 'pix':

                // PIX nï¿½o precisa de dados adicionais

                $order->update_status('hng-pending', __('Aguardando pagamento PIX.', 'hng-commerce'));

                break;

                

            case 'credit_card':

                // Validar dados do cartï¿½o

                if (empty($data['card_holder_name']) || empty($data['card_number']) || 

                    empty($data['card_expiry_month']) || empty($data['card_expiry_year']) || 

                    empty($data['card_cvv'])) {

                    return new WP_Error('invalid_card', __('Dados do cartï¿½o incompletos.', 'hng-commerce'));

                }

                

                $payment_data['card_holder_name'] = $data['card_holder_name'];

                $payment_data['card_number'] = $data['card_number'];

                $payment_data['card_expiry_month'] = $data['card_expiry_month'];

                $payment_data['card_expiry_year'] = $data['card_expiry_year'];

                $payment_data['card_cvv'] = $data['card_cvv'];

                $payment_data['installments'] = $data['installments'] ?? 1;

                

                $order->update_status('hng-pending', __('Processando cartï¿½o de crï¿½dito...', 'hng-commerce'));

                break;

                

            case 'boleto':

                $order->update_status('hng-pending', __('Gerando boleto bancï¿½rio...', 'hng-commerce'));

                break;

                

            default:

                return new WP_Error('invalid_payment', __('Mï¿½todo de pagamento invï¿½lido.', 'hng-commerce'));

        }

        

        // Processar pagamento via gateway din e2mico

        $result = $gateway->process_payment($order->get_id(), $payment_data);

        

        if (is_wp_error($result)) {

            return $result;

        }

        

        // Se for assinatura, criar registro e integrar com gateway avan e7ado quando poss edvel

        $product_type = $data['product_type'] ?? 'physical';

        if (class_exists('HNG_Product_Types')) {

            $product_type = HNG_Product_Types::normalize($product_type);

        }

        if ($product_type === 'subscription') {

            $gateway_id = property_exists($gateway, 'id') ? $gateway->id : 'generic';

            $gateway_subscription_id = '';



            // Tentar integra e7 e3o avan e7ada por gateway

            if (method_exists($gateway, 'create_customer') && method_exists($gateway, 'create_subscription')) {

                $customer_payload = [

                    'name' => $order->get_customer_name(),

                    'email' => $order->get_customer_email(),

                    'document' => get_post_meta($order->get_id(), '_billing_cpf', true),

                ];

                $customer = $gateway->create_customer($customer_payload);

                if (!is_wp_error($customer)) {

                    $customer_id = $customer['id'] ?? ($customer['customer']['id'] ?? '');

                    // Mercado Pago: usa preapproval sem plan_id

                    if ($gateway_id === 'mercadopago') {

                        $plan_data = [

                            'email' => $order->get_customer_email(),

                            'reason' => 'Assinatura HNG',

                            'frequency' => 1,

                            'frequency_type' => 'months',

                            'amount' => $order->get_total(),

                        ];

                        $sub = $gateway->create_subscription($customer_id, $plan_data);

                        if (!is_wp_error($sub)) {

                            $gateway_subscription_id = $sub['id'] ?? '';

                        }

                    }

                    // Pagar.me: requer plan_id (opcional via config)

                    if ($gateway_id === 'pagarme') {

                        $plan_id = get_option('hng_pagarme_plan_id', '');

                        if (!empty($plan_id)) {

                            $sub = $gateway->create_subscription($plan_id, $customer_id, $payment_method);

                            if (!is_wp_error($sub)) {

                                $gateway_subscription_id = $sub['id'] ?? '';

                            }

                        }

                    }

                    // Asaas: criar assinatura recorrente mensal ao invés de cobrança avulsa

                    if ($gateway_id === 'asaas') {

                        $next_due = gmdate('Y-m-d', strtotime('+1 month'));

                        $sub = $gateway->create_subscription($customer_id, [

                            'amount' => $order->get_total(),

                            'cycle' => 'MONTHLY',

                            'next_due_date' => $next_due,

                            'description' => 'Assinatura HNG',

                        ]);

                        if (!is_wp_error($sub)) {

                            $gateway_subscription_id = $sub['id'] ?? '';

                        }

                    }

                }

            }



            // Determinar produto principal

            $items = $order->get_items();

            $first_item = is_array($items) && !empty($items) ? $items[0] : [];

            $product_id = isset($first_item['product_id']) ? intval($first_item['product_id']) : 0;

            $status = in_array($payment_method, ['credit_card','debit_card'], true) ? 'active' : 'pending';



            // Criar assinatura local

            if (class_exists('HNG_Subscription')) {

                $sub_data = [

                    'order_id' => $order->get_id(),

                    'product_id' => $product_id,

                    'customer_email' => $order->get_customer_email(),

                    'status' => $status,

                    'billing_period' => 'monthly',

                    'billing_interval' => 1,

                    'next_payment_date' => gmdate('Y-m-d H:i:s', strtotime('+1 month')),

                    'amount' => $order->get_total(),

                    'gateway' => $gateway_id,

                    'gateway_subscription_id' => $gateway_subscription_id,

                    'payment_method' => $payment_method,

                ];



                // Se Asaas em modo avançado, também persistir coluna específica para facilitar webhooks

                if ($gateway_id === 'asaas' && get_option('hng_asaas_advanced_integration', 'no') === 'yes' && !empty($gateway_subscription_id)) {

                    $sub_data['asaas_subscription_id'] = $gateway_subscription_id;

                }



                HNG_Subscription::create($sub_data);

            }

        }



        // Sucesso no processamento

        return true;

    }

    

    /**

     * Obter URL da página de confirmação

     */

    private function get_order_received_url($order) {

        $payment_method = get_post_meta($order->get_id(), '_payment_method', true);

        

        // Sempre redirecionar para página de obrigado com parâmetros do pedido

        // O template order-received.php já trata cada método de pagamento (PIX, Boleto, etc)

        $url = hng_get_page_url('obrigado');

        return add_query_arg([

            'order_id' => $order->get_id(), 

            'key' => $order->get_order_number(),

            'payment_method' => $payment_method

        ], $url);

    }



    /**

     * Resolver gateway para o m e9todo selecionado

     */

    private function resolve_gateway_for_method($method) {        // DEBUG: Log para rastrear resolução de gateway

        error_log("HNG Checkout: resolve_gateway_for_method chamado para método: " . $method);

                // Ordem prefer e1vel por m e9todo

        $candidates = [];

        if ($method === 'pix') {

            $candidates = [

                ['opt' => 'hng_gateway_pagarme_enabled', 'class' => 'HNG_Gateway_Pagarme'],

                ['opt' => 'hng_gateway_mercadopago_enabled', 'class' => 'HNG_Gateway_MercadoPago'],

                ['opt' => 'hng_gateway_pagseguro_enabled', 'class' => 'HNG_Gateway_PagSeguro'],

                ['opt' => 'hng_gateway_asaas_enabled', 'class' => 'HNG_Gateway_Asaas'],

            ];

        } elseif ($method === 'credit_card') {

            $candidates = [

                ['opt' => 'hng_gateway_pagarme_enabled', 'class' => 'HNG_Gateway_Pagarme'],

                ['opt' => 'hng_gateway_mercadopago_enabled', 'class' => 'HNG_Gateway_MercadoPago'],

                ['opt' => 'hng_gateway_asaas_enabled', 'class' => 'HNG_Gateway_Asaas'],

            ];

        } elseif ($method === 'boleto') {

            $candidates = [

                ['opt' => 'hng_gateway_pagarme_enabled', 'class' => 'HNG_Gateway_Pagarme'],

                ['opt' => 'hng_gateway_pagseguro_enabled', 'class' => 'HNG_Gateway_PagSeguro'],

                ['opt' => 'hng_gateway_asaas_enabled', 'class' => 'HNG_Gateway_Asaas'],

            ];

        }



        foreach ($candidates as $cand) {

            // DEBUG: Log das opções verificadas

            $opt_value = get_option($cand['opt'], 'no');

            error_log("HNG Checkout: Verificando opção {$cand['opt']} = '{$opt_value}' (esperado: 'yes')");

            

            if ($opt_value === 'yes') {

                error_log("HNG Checkout: Gateway encontrado: {$cand['class']}");

                if (!class_exists($cand['class'])) {

                    // Tentar carregar arquivo do gateway (paths padr e3o)

                    $map = [

                        'HNG_Gateway_Pagarme' => HNG_COMMERCE_PATH . 'gateways/pagarme/class-gateway-pagarme.php',

                        'HNG_Gateway_MercadoPago' => HNG_COMMERCE_PATH . 'gateways/mercadopago/class-gateway-mercadopago.php',

                        'HNG_Gateway_PagSeguro' => HNG_COMMERCE_PATH . 'gateways/pagseguro/class-gateway-pagseguro.php',

                        'HNG_Gateway_Asaas' => HNG_COMMERCE_PATH . 'gateways/asaas/class-gateway-asaas.php',

                    ];

                    if (isset($map[$cand['class']]) && file_exists($map[$cand['class']])) {

                        require_once $map[$cand['class']];

                    }

                }

                if (class_exists($cand['class'])) {

                    return new $cand['class']();

                }

            }

        }



        error_log("HNG Checkout: NENHUM gateway habilitado encontrado!");

        return null;

    }

    

    /**

     * Incrementar uso dos cupons

     */

    private function increment_coupon_usage($order) {

        $coupon_codes = $order->get_meta('coupon_codes');

        

        if (empty($coupon_codes)) {

            return;

        }

        

        $codes = explode(',', $coupon_codes);

        $user_id = get_current_user_id();

        

        foreach ($codes as $code) {

            $coupon = HNG_Coupon::get_by_code(trim($code));

            

            if ($coupon) {

                $coupon->increment_usage_count($user_id, $order->get_id());

            }

        }

    }

}

