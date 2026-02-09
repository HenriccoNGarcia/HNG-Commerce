<?php
/**
 * Order - Gerenciamento de Pedidos
 *
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// DB helper
require_once HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php';

class HNG_Order {
    
    /**
     * ID do pedido
     */
    public $id = 0;
    
    /**
     * Dados do pedido
     */
    public $data = [];
    
    /**
     * Items do pedido
     */
    public $items = [];
    
    /**
     * Construtor
     */
    public function __construct($order_id = 0) {
        if ($order_id > 0) {
            $this->load($order_id);
        }
    }
    
    /**
     * Carregar pedido
     */
    private function load($order_id) {
        global $wpdb;
        
        $table_full = hng_db_full_table_name('hng_orders');
        $table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_orders') : ('`' . str_replace('`','', $table_full) . '`');
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_sql sanitized via hng_db_backtick_table()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom orders table query, load single order by ID
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table_sql sanitized via hng_db_backtick_table()
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_sql} WHERE id = %d",
            $order_id
        ), ARRAY_A);
        
        if ($order) {
            $this->id = $order['id'];
            $this->data = $order;
            $this->load_items();
        }
    }
    
    /**
     * Carregar items do pedido
     */
    private function load_items() {
        global $wpdb;
        
        $table_full = hng_db_full_table_name('hng_order_items');
        $table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_order_items') : ('`' . str_replace('`','', $table_full) . '`');
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_sql sanitized via hng_db_backtick_table()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom order items table query, load items for specific order
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table_sql sanitized via hng_db_backtick_table()
        $this->items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_sql} WHERE order_id = %d",
            $this->id
        ), ARRAY_A);
    }
    
    /**
     * Criar pedido do carrinho
     */
    public static function create_from_cart($data = []) {
        global $wpdb;
        
        $cart = hng_cart();
        
        if ($cart->is_empty()) {
            return new WP_Error('empty_cart', __('Carrinho vazio.', 'hng-commerce'));
        }
        
        // Validar dados obrigatrios
        $required = ['billing_first_name', 'billing_last_name', 'billing_email', 'billing_phone', 'billing_cpf'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                /* translators: %1$s: field name */
                return new WP_Error('missing_field', sprintf(esc_html__('Campo obrigatrio: %1$s', 'hng-commerce'), $field));
            }
        }
        

        // Gerar nmero do pedido
        $order_number = self::generate_order_number();

        // Calcular totais
        $subtotal = $cart->get_subtotal();
        $shipping = isset($data['shipping_cost']) ? floatval($data['shipping_cost']) : 0;
        $discount = $cart->get_discount_total();
        $total = $subtotal + $shipping - $discount;
        $commission = $cart->get_commission_total();

        // Filtro para customizar totais antes de criar pedido
        $totals = apply_filters('hng_order_calculated_totals', [
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'discount' => $discount,
            'total' => $total,
            'commission' => $commission,
        ], $cart, $data);
        $subtotal = $totals['subtotal'];
        $shipping = $totals['shipping'];
        $discount = $totals['discount'];
        $total = $totals['total'];
        $commission = $totals['commission'];

        // Determinar tipo predominante de produto
        $product_type = isset($data['product_type']) ? $data['product_type'] : '';
        if (empty($product_type)) {
            $product_type = 'physical';
            foreach ($cart->get_cart() as $cart_item) {
                $product = $cart_item['data'];
                $pt = $product->get_product_type();
                if (in_array($pt, ['subscription', 'appointment', 'quote'], true)) { $product_type = $pt; break; }
                if ($pt === 'digital' && $product_type === 'physical') { $product_type = 'digital'; }
            }
        }
        if (class_exists('HNG_Product_Types')) {
            $product_type = HNG_Product_Types::normalize($product_type);
        } else {
            $product_type = sanitize_key($product_type);
            if ($product_type === 'simple') {
                $product_type = 'physical';
            }
            $allowed_types = ['physical', 'digital', 'subscription', 'quote', 'appointment'];
            if (!in_array($product_type, $allowed_types, true)) {
                $product_type = 'physical';
            }
        }

        // Preparar dados do pedido
        $order_data = [
            'order_number' => $order_number,
            'customer_id' => get_current_user_id(),
            'status' => 'hng-pending',
            'currency' => 'BRL',
            'subtotal' => $subtotal,
            'shipping_total' => $shipping,
            'discount_total' => $discount,
            'total' => $total,
            'commission' => $commission,
            'product_type' => $product_type,
            'payment_method' => isset($data['payment_method']) ? sanitize_text_field($data['payment_method']) : '',
            'payment_method_title' => isset($data['payment_method_title']) ? sanitize_text_field($data['payment_method_title']) : '',
            'billing_first_name' => sanitize_text_field($data['billing_first_name']),
            'billing_last_name' => sanitize_text_field($data['billing_last_name']),
            'billing_email' => sanitize_email($data['billing_email']),
            'billing_phone' => sanitize_text_field($data['billing_phone']),
            'billing_cpf' => sanitize_text_field($data['billing_cpf']),
            'billing_postcode' => sanitize_text_field($data['billing_postcode'] ?? ''),
            'billing_address_1' => sanitize_text_field($data['billing_address_1'] ?? ''),
            'billing_number' => sanitize_text_field($data['billing_number'] ?? ''),
            'billing_address_2' => sanitize_text_field($data['billing_address_2'] ?? ''),
            'billing_neighborhood' => sanitize_text_field($data['billing_neighborhood'] ?? ''),
            'billing_city' => sanitize_text_field($data['billing_city'] ?? ''),
            'billing_state' => sanitize_text_field($data['billing_state'] ?? ''),
            'shipping_method' => isset($data['shipping_method']) ? sanitize_text_field($data['shipping_method']) : '',
            'customer_note' => isset($data['order_comments']) ? sanitize_textarea_field($data['order_comments']) : '',
            'customer_ip' => self::get_client_ip(),
            'customer_user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized with sanitize_text_field
            'created_at' => current_time('mysql'),
        ];

        // Filtro para customizar dados do pedido antes de inserir
        $order_data = apply_filters('hng_order_data_before_insert', $order_data, $cart, $data);
        
        // Inserir pedido
        $table = hng_db_full_table_name('hng_orders');
        $inserted = $wpdb->insert(
            $table,
            $order_data,
            [
                '%s', // order_number
                '%d', // customer_id
                '%s', // status
                '%s', // currency
                '%f', // subtotal
                '%f', // shipping_total
                '%f', // discount_total
                '%f', // total
                '%f', // commission
                '%s', // product_type
                '%s', // payment_method
                '%s', // payment_method_title
                '%s', // billing_first_name
                '%s', // billing_last_name
                '%s', // billing_email
                '%s', // billing_phone
                '%s', // billing_cpf
                '%s', // billing_postcode
                '%s', // billing_address_1
                '%s', // billing_number
                '%s', // billing_address_2
                '%s', // billing_neighborhood
                '%s', // billing_city
                '%s', // billing_state
                '%s', // shipping_method
                '%s', // customer_note
                '%s', // customer_ip
                '%s', // customer_user_agent
                '%s', // created_at
            ]
        );

        if (!$inserted) {
            do_action('hng_order_creation_failed', $order_data, $cart, $data);
            return new WP_Error('db_error', __('Erro ao criar pedido.', 'hng-commerce'));
        }

        $order_id = $wpdb->insert_id;

        do_action('hng_order_created', $order_id, $order_data, $cart, $data);

        // Inserir items
        $items_table = hng_db_full_table_name('hng_order_items');
        // Recuperar campos personalizados do cliente da sesso (checkout)
        $custom_fields_checkout = isset($_SESSION['hng_cf_checkout']) ? $_SESSION['hng_cf_checkout'] : [];
        foreach ($cart->get_cart() as $cart_item_id => $cart_item) {
            $product = $cart_item['data'];
            $product_id = $cart_item['product_id'];

            // Buscar custo do produto (opcional)
            $product_cost = (float) get_post_meta($product_id, '_product_cost', true);
            $item_subtotal = floatval($product->get_price()) * absint($cart_item['quantity']);

            $item_data = [
                'order_id' => $order_id,
                'product_id' => $product_id,
                'product_name' => sanitize_text_field($product->get_name()),
                'quantity' => absint($cart_item['quantity']),
                'price' => floatval($product->get_price()),
                'product_cost' => floatval($product_cost),
                'subtotal' => $item_subtotal,
                'total' => $item_subtotal,
                'commission_rate' => floatval($product->get_commission_rate()),
                'commission' => floatval($product->calculate_commission($item_subtotal)),
            ];

            // Adicionar campos personalizados do cliente (se existirem)
            if (isset($custom_fields_checkout[$cart_item_id]) && is_array($custom_fields_checkout[$cart_item_id])) {
                foreach ($custom_fields_checkout[$cart_item_id] as $slug => $value) {
                    // Serializar arrays (checkboxes)
                    if (is_array($value)) {
                        $value = implode(',', $value);
                    }
                    $item_data['cf_' . sanitize_key($slug)] = sanitize_text_field($value);
                }
            }

            // Filtro para customizar dados do item antes de inserir
            $item_data = apply_filters('hng_order_item_data_before_insert', $item_data, $cart_item, $order_id);

            $item_inserted = $wpdb->insert($items_table, $item_data);
            
            // Log para debug se a inserção falhar
            if ($item_inserted === false) {
                error_log('HNG Commerce: Erro ao inserir item do pedido #' . $order_id . ' - ' . $wpdb->last_error);
                error_log('HNG Commerce: Item data: ' . print_r($item_data, true));
            }

            // Reduzir estoque
            $product->reduce_stock($cart_item['quantity']);

            // Incrementar vendas
            $product->increment_sales($cart_item['quantity']);

            do_action('hng_order_item_created', $order_id, $item_data, $cart_item);
        }
        // Limpar campos personalizados da sesso aps criar pedido
        unset($_SESSION['hng_cf_checkout']);

        // Limpar carrinho
        $cart->empty_cart();

        // Criar post do pedido
        $post_id = wp_insert_post([
            /* translators: %1$s: order number */
            'post_title' => sprintf(esc_html__('Pedido %1$s', 'hng-commerce'), $order_number),
            'post_status' => 'publish',
            'post_type' => 'hng_order',
            'post_author' => get_current_user_id(),
        ]);

        // Salvar relacionamento
        update_post_meta($post_id, '_order_id', $order_id);
        $wpdb->update($table, ['post_id' => $post_id], ['id' => $order_id]);

        // Log de segurana
        /* translators: %1$s: IP address */
        self::add_order_note($order_id, sprintf(esc_html__('Pedido criado. IP: %1$s', 'hng-commerce'), $order_data['customer_ip']));

        do_action('hng_order_post_created', $order_id, $post_id, $order_data);

        // Retornar objeto do pedido
        return new self($order_id);
    }
    
    /**
     * Gerar nmero do pedido
     */
    private static function generate_order_number() {
        $prefix = get_option('hng_order_prefix', 'HNG');
        $number = str_pad(get_option('hng_last_order_number', 0) + 1, 6, '0', STR_PAD_LEFT);
        update_option('hng_last_order_number', intval($number));
        
        return $prefix . '-' . $number;
    }
    
    /**
     * Obter IP do cliente
     */
    private static function get_client_ip() {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (isset($_SERVER[$key]) && filter_var($_SERVER[$key], FILTER_VALIDATE_IP)) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- IP validated with filter_var
                return sanitize_text_field($_SERVER[$key]); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized with sanitize_text_field
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Adicionar nota ao pedido
     */
    public static function add_order_note($order_id, $note) {
        global $wpdb;
        
        $table = hng_db_full_table_name('hng_order_notes');

        // Criar tabela se não existir (sanitizar identificador)
        $table_sql = '`' . str_replace('`','', $table) . '`';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via hng_db_full_table_name() and backtick escaping, dbDelta requires literal SQL
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Database schema installation
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names sanitized via hng_db_full_table_name()
        $sql = "CREATE TABLE IF NOT EXISTS {$table_sql} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            note text NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY order_id (order_id)
        )";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
        $wpdb->query($sql);
        
        $result = $wpdb->insert($table, [
            'order_id' => $order_id,
            'note' => sanitize_textarea_field($note),
            'created_at' => current_time('mysql'),
        ]);
        do_action('hng_order_note_added', $order_id, $note);
        return $result;
    }
    
    /**
     * Atualizar status
     */
    public function update_status($new_status, $note = '') {
        global $wpdb;
        
        $new_status = sanitize_key($new_status);
        $old_status = $this->get_status();
        
        if ($old_status === $new_status) {
            return false;
        }
        
        $table = hng_db_full_table_name('hng_orders');
        $updated = $wpdb->update(
            $table,
            ['status' => $new_status, 'updated_at' => current_time('mysql')],
            ['id' => $this->id]
        );
        
        if ($updated) {
            $this->data['status'] = $new_status;
            
            // Adicionar nota
            if (empty($note)) {
                /* translators: %1$s: old status, %2$s: new status */
                $note = sprintf(esc_html__('Status alterado de %1$s para %2$s.', 'hng-commerce'),
                    $old_status,
                    $new_status
                );
            }
            self::add_order_note($this->id, $note);
            
            // Hook de status
            do_action('hng_order_status_changed', $this->id, $old_status, $new_status);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Getters
     */
    public function get_id() { return $this->id; }
    public function get_order_number() { return $this->data['order_number'] ?? ''; }
    public function get_status() { return $this->data['status'] ?? ''; }
    public function get_total() { return floatval($this->data['total'] ?? 0); }
    public function get_subtotal() { return floatval($this->data['subtotal'] ?? 0); }
    public function get_shipping_total() { return floatval($this->data['shipping_total'] ?? 0); }
    public function get_discount_total() { return floatval($this->data['discount_total'] ?? 0); }
    public function get_commission() { return floatval($this->data['commission'] ?? 0); }
    public function get_customer_email() { return $this->data['billing_email'] ?? ''; }
    public function get_customer_name() {
        return trim(($this->data['billing_first_name'] ?? '') . ' ' . ($this->data['billing_last_name'] ?? ''));
    }
    public function get_payment_method() { return $this->data['payment_method'] ?? ''; }
    public function get_payment_method_title() { return $this->data['payment_method_title'] ?? ''; }
    public function get_items() { return $this->items; }
    public function get_created_at() { return $this->data['created_at'] ?? ''; }
    public function get_post_id() { return intval($this->data['post_id'] ?? 0); }
    public function get_billing_first_name() { return $this->data['billing_first_name'] ?? ''; }
    public function get_billing_last_name() { return $this->data['billing_last_name'] ?? ''; }
    public function get_billing_email() { return $this->data['billing_email'] ?? ''; }
    public function get_billing_phone() { return $this->data['billing_phone'] ?? ''; }
    public function get_billing_cpf() { return $this->data['billing_cpf'] ?? ''; }
    public function get_billing_address_1() { return $this->data['billing_address_1'] ?? ''; }
    public function get_billing_address() { return $this->get_billing_address_1(); } // Alias
    public function get_billing_number() { return $this->data['billing_number'] ?? ''; }
    public function get_billing_address_2() { return $this->data['billing_address_2'] ?? ''; }
    public function get_billing_complement() { return $this->get_billing_address_2(); } // Alias
    public function get_billing_neighborhood() { return $this->data['billing_neighborhood'] ?? ''; }
    public function get_billing_city() { return $this->data['billing_city'] ?? ''; }
    public function get_billing_state() { return $this->data['billing_state'] ?? ''; }
    public function get_billing_postcode() { return $this->data['billing_postcode'] ?? ''; }
    public function get_shipping_method() { return $this->data['shipping_method'] ?? ''; }
    
    /**
     * Obter metadado do pedido
     *
     * @param string $key Chave do metadado
     * @param mixed $default Valor padrão
     * @return mixed
     */
    public function get_meta($key, $default = '') {
        // Primeiro tenta no array data
        if (isset($this->data[$key])) {
            return $this->data[$key];
        }
        // Depois tenta no post_meta
        $post_id = $this->get_post_id();
        if ($post_id > 0) {
            $value = get_post_meta($post_id, '_' . $key, true);
            if ($value !== '') {
                return $value;
            }
        }
        return $default;
    }
    
    /**
     * Verificar se pedido foi pago
     */
    public function is_paid() {
        return in_array($this->get_status(), ['hng-processing', 'hng-completed']);
    }
    
    /**
     * Obter total formatado
     */
    public function get_formatted_total() {
        return hng_price($this->get_total());
    }
    
    /**
     * Alias para get_created_at() - compatibilidade
     */
    public function get_date_created() {
        $date = $this->get_created_at();
        if (!empty($date)) {
            return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($date));
        }
        return '';
    }
    
    /**
     * Obter telefone do cliente
     */
    public function get_customer_phone() {
        return $this->data['billing_phone'] ?? '';
    }
    
    /**
     * Obter CPF do cliente
     */
    public function get_customer_cpf() {
        return $this->data['billing_cpf'] ?? '';
    }
    
    /**
     * Obter HTML dos itens do pedido
     */
    public function get_order_items_html() {
        $items = $this->get_items();
        if (empty($items)) {
            return '<p>' . esc_html__('Nenhum item no pedido.', 'hng-commerce') . '</p>';
        }
        
        $html = '<table style="width:100%; border-collapse:collapse; margin:15px 0;">';
        $html .= '<thead><tr style="background:#f5f5f5;">';
        $html .= '<th style="padding:10px; text-align:left; border:1px solid #ddd;">' . esc_html__('Produto', 'hng-commerce') . '</th>';
        $html .= '<th style="padding:10px; text-align:center; border:1px solid #ddd;">' . esc_html__('Qtd', 'hng-commerce') . '</th>';
        $html .= '<th style="padding:10px; text-align:right; border:1px solid #ddd;">' . esc_html__('Total', 'hng-commerce') . '</th>';
        $html .= '</tr></thead><tbody>';
        
        foreach ($items as $item) {
            $name = isset($item['product_name']) ? $item['product_name'] : (isset($item['name']) ? $item['name'] : '');
            $qty = isset($item['quantity']) ? intval($item['quantity']) : 1;
            $total = isset($item['total']) ? floatval($item['total']) : 0;
            
            $html .= '<tr>';
            $html .= '<td style="padding:10px; border:1px solid #ddd;">' . esc_html($name) . '</td>';
            $html .= '<td style="padding:10px; text-align:center; border:1px solid #ddd;">' . esc_html($qty) . '</td>';
            $html .= '<td style="padding:10px; text-align:right; border:1px solid #ddd;">' . hng_price($total) . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        
        return $html;
    }
    
    /**
     * Obter endereço de entrega formatado
     */
    public function get_formatted_shipping_address() {
        $parts = [];
        
        $address = $this->get_billing_address_1();
        $number = $this->data['billing_number'] ?? '';
        $complement = $this->data['billing_address_2'] ?? '';
        $neighborhood = $this->data['billing_neighborhood'] ?? '';
        $city = $this->get_billing_city();
        $state = $this->get_billing_state();
        $postcode = $this->get_billing_postcode();
        
        if (!empty($address)) {
            $line = $address;
            if (!empty($number)) {
                $line .= ', ' . $number;
            }
            if (!empty($complement)) {
                $line .= ' - ' . $complement;
            }
            $parts[] = $line;
        }
        
        if (!empty($neighborhood)) {
            $parts[] = $neighborhood;
        }
        
        if (!empty($city) || !empty($state)) {
            $city_state = [];
            if (!empty($city)) $city_state[] = $city;
            if (!empty($state)) $city_state[] = $state;
            $parts[] = implode(' - ', $city_state);
        }
        
        if (!empty($postcode)) {
            $parts[] = 'CEP: ' . $postcode;
        }
        
        return implode('<br>', $parts);
    }
    
    /**
     * Obter URL para visualizar o pedido
     */
    public function get_view_order_url() {
        $my_account_page = get_option('hng_my_account_page_id');
        if ($my_account_page) {
            return add_query_arg([
                'view-order' => $this->get_id()
            ], get_permalink($my_account_page));
        }
        return home_url('/minha-conta/?view-order=' . $this->get_id());
    }
}
