<?php

/**

 * Carrinho de Compras

 * 

 * @package HNG_Commerce

 * @since 1.0.0

 */



if (!defined('ABSPATH')) {

    exit;

}



class HNG_Cart {

    

    /**

     * Instância única

     */

    private static $instance = null;

    

    /**

     * Itens do carrinho

     */

    protected $cart_contents = [];

    

    /**

     * Cupons aplicados

     */

    protected $applied_coupons = [];

    

    /**

     * Session handler

     */

    protected $session_key = 'hng_cart';

    protected $coupon_session_key = 'hng_cart_coupons';

    protected $shipping_session_key = 'hng_cart_shipping';

    protected $shipping_rates_session_key = 'hng_cart_shipping_rates';



    /**

     * Shipping selection and available rates (persisted in session)

     */

    protected $shipping_data = [

        'id' => '',

        'method_id' => '',

        'label' => '',

        'cost' => 0,

        'postcode' => '',

    ];



    protected $available_shipping = [

        'postcode' => '',

        'rates' => [],

        'generated_at' => 0,

    ];

    

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

        $this->load_cart();

        

        // Método init não existe, removido
        add_action('shutdown', [$this, 'save_cart']);

    }

    

    /**

     * Carregar carrinho da sessão

     */

    public function load_cart() {

        if (!session_id()) {

            session_start();

        }

        

        $cart_data = isset($_SESSION[$this->session_key]) ? $_SESSION[$this->session_key] : [];

        

        if (is_array($cart_data)) {

            $this->cart_contents = $cart_data;

        }

        

        // Carregar cupons

        $coupon_codes = isset($_SESSION[$this->coupon_session_key]) ? $_SESSION[$this->coupon_session_key] : [];

        

        if (is_array($coupon_codes)) {

            foreach ($coupon_codes as $code) {

                $coupon = HNG_Coupon::get_by_code($code);

                if ($coupon && $coupon->is_valid()) {

                    $this->applied_coupons[$code] = $coupon;

                }

            }

        }



        // Carregar seleção de frete

        $shipping = isset($_SESSION[$this->shipping_session_key]) ? $_SESSION[$this->shipping_session_key] : [];

        if (is_array($shipping) && !empty($shipping)) {

            $this->shipping_data = array_merge($this->shipping_data, $shipping);

        }



        // Carregar últimas cotações de frete

        $rates = isset($_SESSION[$this->shipping_rates_session_key]) ? $_SESSION[$this->shipping_rates_session_key] : [];

        if (is_array($rates) && !empty($rates)) {

            $this->available_shipping = array_merge($this->available_shipping, $rates);

        }

    }

    

    /**

     * Salvar carrinho na sessão

     */

    public function save_cart() {

        // Sessão já iniciada no construtor

        if (session_id()) {

            $_SESSION[$this->session_key] = $this->cart_contents;

            $_SESSION[$this->coupon_session_key] = array_keys($this->applied_coupons);

            $_SESSION[$this->shipping_session_key] = $this->shipping_data;

            $_SESSION[$this->shipping_rates_session_key] = $this->available_shipping;

        }

    }

    

    /**

     * Adicionar ao carrinho

     */

    public function add_to_cart($product_id, $quantity = 1, $variation_id = 0, $variation = []) {

        // Validar produto

        $product = new HNG_Product($product_id);

        

        if (!$product->get_id()) {

            return false;

        }

        

        if (!$product->is_purchasable()) {

            return false;

        }

        

        // Gerar chave única

        $cart_id = $this->generate_cart_id($product_id, $variation_id, $variation);

        

        // Validar quantidade

        $quantity = absint($quantity);

        if ($quantity <= 0) {

            $quantity = 1;

        }

        

        // Verificar estoque

        if ($product->manages_stock()) {

            $stock_quantity = $product->get_stock_quantity();

            $current_in_cart = $this->get_cart_item_quantity($cart_id);

            $total_quantity = $current_in_cart + $quantity;

            

            if ($stock_quantity < $total_quantity) {

                do_action('hng_cart_insufficient_stock', $product_id, $stock_quantity, $total_quantity);

                return false;

            }

        }

        

        // Produto vendido individualmente

        if ($product->is_sold_individually()) {

            if ($this->find_product_in_cart($product_id)) {

                return false;

            }

            $quantity = 1;

        }

        

        // Filtro para customizar dados do item antes de adicionar ao carrinho

        // Capturar campos personalizados do cliente (enviados via $variation['custom_fields'] ou $_POST)

        $custom_fields = [];

        if (isset($variation['custom_fields'])) {

            $custom_fields = $variation['custom_fields'];

        } else {

            $post = function_exists('wp_unslash') ? wp_unslash( $_POST ) : $_POST;

            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Custom fields sá¡o sanitizados; nonce verificado no checkout

            if (!empty($post['hng_cf']) && is_array($post['hng_cf'])) {

                foreach ($post['hng_cf'] as $slug => $value) {

                    $custom_fields[$slug] = sanitize_text_field($value);

                }

            }

        }



        $cart_item_data = [

            'product_id' => $product_id,

            'quantity' => $quantity,

            'variation_id' => $variation_id,

            'variation' => $variation,

            'data' => $product,

            'custom_fields' => $custom_fields,

        ];

        $cart_item_data = apply_filters('hng_cart_item_data_before_add', $cart_item_data, $product_id, $quantity, $variation_id, $variation);



        // Adicionar ou atualizar

        if (isset($this->cart_contents[$cart_id])) {

            $this->cart_contents[$cart_id]['quantity'] += $quantity;

        } else {

            $this->cart_contents[$cart_id] = $cart_item_data;

        }



        do_action('hng_add_to_cart', $cart_id, $product_id, $quantity, $variation_id, $variation, $cart_item_data);



        return $cart_id;

    }

    

    /**

     * Remover do carrinho

     */

    public function remove_cart_item($cart_id) {

        if (isset($this->cart_contents[$cart_id])) {

            $product_id = $this->cart_contents[$cart_id]['product_id'];

            $cart_item = $this->cart_contents[$cart_id];

            unset($this->cart_contents[$cart_id]);



            do_action('hng_cart_item_removed', $cart_id, $product_id, $cart_item);



            return true;

        }



        return false;

    }

    

    /**

     * Atualizar quantidade

     */

    public function set_quantity($cart_id, $quantity = 1) {

        if (!isset($this->cart_contents[$cart_id])) {

            return false;

        }

        

        $quantity = absint($quantity);

        

        if ($quantity <= 0) {

            return $this->remove_cart_item($cart_id);

        }

        

        // Verificar estoque

        $product = $this->cart_contents[$cart_id]['data'];

        if ($product->manages_stock()) {

            $stock_quantity = $product->get_stock_quantity();

            

            if ($stock_quantity < $quantity) {

                do_action('hng_cart_insufficient_stock', $product->get_id(), $stock_quantity, $quantity);

                return false;

            }

        }

        

        $old_quantity = $this->cart_contents[$cart_id]['quantity'];

        $this->cart_contents[$cart_id]['quantity'] = $quantity;



        do_action('hng_cart_item_quantity_updated', $cart_id, $quantity, $old_quantity);



        return true;

    }

    

    /**

     * Limpar carrinho

     */

    public function empty_cart() {

        $old_cart = $this->cart_contents;

        $this->cart_contents = [];

        $this->shipping_data = [

            'id' => '',

            'method_id' => '',

            'label' => '',

            'cost' => 0,

            'postcode' => '',

        ];

        $this->available_shipping = [

            'postcode' => '',

            'rates' => [],

            'generated_at' => 0,

        ];



        do_action('hng_cart_emptied', $old_cart);

    }

    

    /**

     * Obter conteúdo do carrinho

     */

    public function get_cart() {

        $cart = apply_filters('hng_cart_contents', $this->cart_contents, $this);

        return $cart;

    }

    

    /**

     * Obter item do carrinho

     */

    public function get_cart_item($cart_id) {

        return $this->cart_contents[$cart_id] ?? null;

    }

    

    /**

     * Obter quantidade de um item

     */

    protected function get_cart_item_quantity($cart_id) {

        return $this->cart_contents[$cart_id]['quantity'] ?? 0;

    }

    

    /**

     * Procurar produto no carrinho

     */

    protected function find_product_in_cart($product_id) {

        foreach ($this->cart_contents as $item) {

            if ($item['product_id'] == $product_id) {

                return true;

            }

        }

        return false;

    }

    

    /**

     * Contar itens

     */

    public function get_cart_contents_count() {

        $count = 0;

        

        foreach ($this->cart_contents as $item) {

            $count += $item['quantity'];

        }

        

        return $count;

    }

    

    /**

     * Calcular subtotal

     */

    public function get_subtotal() {

        $subtotal = 0;

        

        foreach ($this->cart_contents as $item) {

            $product = $item['data'];

            $subtotal += $product->get_price() * $item['quantity'];

        }

        

        return $subtotal;

    }

    

    /**

     * Calcular total

     */

    public function get_total() {

        $total = $this->get_subtotal();

        

        // Adicionar frete (se houver)

        $shipping = $this->get_shipping_total();

        $total += $shipping;

        

        // Subtrair desconto (se houver)

        $discount = $this->get_discount_total();

        $total -= $discount;

        

        return max(0, $total);

    }

    

    /**

     * Obter total de frete

     */

    public function get_shipping_total() {

        return floatval($this->shipping_data['cost'] ?? 0);

    }



    /**

     * Guardar cotações disponíveis para o CEP informado

     */

    public function set_available_shipping_rates($postcode, $rates) {

        $this->available_shipping = [

            'postcode' => preg_replace('/\D/', '', (string) $postcode),

            'rates' => is_array($rates) ? $rates : [],

            'generated_at' => time(),

        ];

    }



    /**

     * Obter cotações em cache

     */

    public function get_available_shipping_rates() {

        return $this->available_shipping;

    }



    /**

     * Selecionar um método de frete a partir das cotações disponíveis

     */

    public function select_shipping_rate($rate_id) {

        $rates = $this->available_shipping['rates'] ?? [];

        foreach ($rates as $rate) {

            if (!isset($rate['id'])) {

                continue;

            }



            if ((string) $rate['id'] === (string) $rate_id) {

                $this->shipping_data = [

                    'id' => (string) $rate['id'],

                    'method_id' => (string) ($rate['method_id'] ?? ''),

                    'label' => (string) ($rate['label'] ?? ($rate['service_name'] ?? '')),

                    'cost' => floatval($rate['cost'] ?? 0),

                    'postcode' => $this->available_shipping['postcode'] ?? '',

                ];

                return true;

            }

        }



        return false;

    }



    /**

     * Obter seleção de frete atual

     */

    public function get_selected_shipping() {

        return $this->shipping_data;

    }

    

    /**

     * Calcular comissão total

     */

    public function get_commission_total() {

        $commission = 0;

        

        foreach ($this->cart_contents as $item) {

            $product = $item['data'];

            $item_total = $product->get_price() * $item['quantity'];

            $commission += $product->calculate_commission($item_total);

        }

        

        return $commission;

    }

    

    /**

     * Carrinho está vazio?

     */

    public function is_empty() {

        return empty($this->cart_contents);

    }

    

    /**

     * Precisa de frete?

     */

    public function needs_shipping() {

        foreach ($this->cart_contents as $item) {

            $product = $item['data'];

            if (!$product->is_virtual()) {

                return true;

            }

        }

        return false;

    }

    

    /**

     * Gerar ID único para item

     */

    protected function generate_cart_id($product_id, $variation_id = 0, $variation = []) {

        $id_parts = [$product_id];

        

        if ($variation_id) {

            $id_parts[] = $variation_id;

        }

        

        if (!empty($variation)) {

            ksort($variation);

            $id_parts[] = md5(serialize($variation));

        }

        

        return md5(implode('_', $id_parts));

    }

    

    /**

     * Aplicar cupom

     */

    public function apply_coupon($code) {

        $code = strtoupper(sanitize_text_field($code));

        

        // Verificar se já está aplicado

        if (isset($this->applied_coupons[$code])) {

            throw new Exception(esc_html(__('Este cupom já está aplicado.', 'hng-commerce')));

        }

        

        // Buscar cupom

        $coupon = HNG_Coupon::get_by_code($code);

        

        if (!$coupon) {

            throw new Exception(esc_html(__('Cupom inválido.', 'hng-commerce')));

        }

        

        // Validar cupom

        if (!$coupon->is_valid()) {

            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not output directly

        }

        

        // Validar para o carrinho

        $coupon->validate_for_cart($this);

        

        // Adicionar à lista de cupons aplicados

        $this->applied_coupons[$code] = $coupon;

        

        do_action('hng_applied_coupon', $code);

        

        return true;

    }

    

    /**

     * Remover cupom

     */

    public function remove_coupon($code) {

        $code = strtoupper(sanitize_text_field($code));

        

        if (isset($this->applied_coupons[$code])) {

            unset($this->applied_coupons[$code]);

            

            do_action('hng_removed_coupon', $code);

            

            return true;

        }

        

        return false;

    }

    

    /**

     * Obter cupons aplicados

     */

    public function get_applied_coupons() {

        return $this->applied_coupons;

    }

    

    /**

     * Obter códigos dos cupons

     */

    public function get_coupon_codes() {

        return array_keys($this->applied_coupons);

    }

    

    /**

     * Tem cupom aplicado?

     */

    public function has_coupon($code = null) {

        if ($code) {

            $code = strtoupper($code);

            return isset($this->applied_coupons[$code]);

        }

        

        return !empty($this->applied_coupons);

    }

    

    /**

     * Calcular desconto total dos cupons

     */

    public function get_discount_total() {

        $discount = 0;

        

        foreach ($this->applied_coupons as $coupon) {

            $discount += $coupon->get_discount_amount_for_cart($this);

        }

        

        return $discount;

    }

    

    /**

     * Tem frete grátis por cupom?

     */

    public function has_free_shipping() {

        foreach ($this->applied_coupons as $coupon) {

            if ($coupon->get_free_shipping()) {

                return true;

            }

        }

        

        return false;

    }

    

    /**

     * Obter dados do carrinho como array

     */

    public function get_cart_for_session() {

        $cart_session = [];

        

        foreach ($this->cart_contents as $cart_id => $item) {

            $cart_session[$cart_id] = [

                'product_id' => $item['product_id'],

                'quantity' => $item['quantity'],

                'variation_id' => $item['variation_id'],

                'variation' => $item['variation'],

            ];

        }

        

        return $cart_session;

    }

}

