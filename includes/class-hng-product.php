<?php
/**
 * Classe de Produto
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Product {
    
    /**
     * ID do produto
     */
    protected $id = 0;
    
    /**
     * Dados do produto
     */
    protected $data = [];
    
    /**
     * Tipo do produto
     */
    protected $product_type = 'simple';
    
    /**
     * Meta data
     */
    protected $meta_data = [];
    
    /**
     * Construtor
     */
    public function __construct($product = 0) {
        if (is_numeric($product) && $product > 0) {
            $this->id = absint($product);
            $this->load();
        } elseif ($product instanceof WP_Post) {
            $this->id = absint($product->ID);
            $this->load();
        }
    }
    
    /**
     * Carregar produto
     */
    protected function load() {
        $post = get_post($this->id);
        if (!$post || !isset($post->post_type) || $post->post_type !== 'hng_product') {
            return false;
        }
        $data = [
            'name' => isset($post->post_title) ? $post->post_title : '',
            'slug' => isset($post->post_name) ? $post->post_name : '',
            'description' => $post->post_content,
            'short_description' => $post->post_excerpt,
            'status' => $post->post_status,
            'featured' => get_post_meta($this->id, '_featured', true) === 'yes',
            'catalog_visibility' => get_post_meta($this->id, '_catalog_visibility', true) ?: 'visible',
            'sku' => get_post_meta($this->id, '_sku', true),
            'price' => (float) get_post_meta($this->id, '_price', true),
            'regular_price' => (float) get_post_meta($this->id, '_regular_price', true),
            'sale_price' => (float) get_post_meta($this->id, '_sale_price', true),
            'on_sale' => $this->is_on_sale(),
            'stock_quantity' => (int) get_post_meta($this->id, '_stock_quantity', true),
            'stock_status' => get_post_meta($this->id, '_stock_status', true) ?: 'instock',
            'manage_stock' => get_post_meta($this->id, '_manage_stock', true) === 'yes',
            'rating_count' => (int) get_post_meta($this->id, '_rating_count', true),
            'average_rating' => (float) get_post_meta($this->id, '_average_rating', true),
            'total_sales' => (int) get_post_meta($this->id, '_total_sales', true),
        ];
        // Carregar tipo de produto a partir do meta, com fallback e normalização
        $pt_meta = get_post_meta($this->id, '_hng_product_type', true);
        if (empty($pt_meta)) {
            $pt_meta = get_post_meta($this->id, '_product_type', true);
        }
        if (class_exists('HNG_Product_Types')) {
            $data['product_type'] = HNG_Product_Types::normalize($pt_meta ?: 'physical');
        } else {
            $type = sanitize_key($pt_meta ?: 'physical');
            $data['product_type'] = in_array($type, ['physical','digital','subscription','appointment','quote'], true) ? $type : 'physical';
        }
        // Filtro para customizar dados do produto ao carregar
        $this->data = apply_filters('hng_product_data_loaded', $data, $this->id);
        do_action('hng_product_loaded', $this->id, $this->data);
        return true;
    }
    
    /**
     * Obter ID
     */
    public function get_id() {
        return $this->id;
    }
    
    /**
     * Obter nome
     */
    public function get_name() {
        return $this->data['name'] ?? '';
    }
    
    /**
     * Obter slug
     */
    public function get_slug() {
        return $this->data['slug'] ?? '';
    }
    
    /**
     * Obter permalink
     */
    public function get_permalink() {
        return get_permalink($this->id);
    }
    
    /**
     * Obter descrição¡o
     */
    public function get_description() {
        return $this->data['description'] ?? '';
    }
    
    /**
     * Obter descrição curta
     */
    public function get_short_description() {
        return $this->data['short_description'] ?? '';
    }
    
    /**
     * Obter SKU
     */
    public function get_sku() {
        return $this->data['sku'] ?? '';
    }
    
    /**
     * Obter preço
     */
    public function get_price() {
        $price = $this->data['price'] ?? 0;
        $price = apply_filters('hng_product_price', $price, $this->id, $this->data);
        return $price;
    }
    
    /**
     * Obter preço regular
     */
    public function get_regular_price() {
        return $this->data['regular_price'] ?? 0;
    }
    
    /**
     * Obter preço promocional
     */
    public function get_sale_price() {
        return $this->data['sale_price'] ?? 0;
    }
    
    /**
     * Está em promoção?
     */
    public function is_on_sale() {
        $sale_price = (float) get_post_meta($this->id, '_sale_price', true);
        $regular_price = (float) get_post_meta($this->id, '_regular_price', true);
        
        return $sale_price > 0 && $sale_price < $regular_price;
    }
    
    /**
     * Obter preço formatado
     */
    public function get_price_html() {
        $price = $this->get_price();
        
        if ($price <= 0) {
            return '';
        }
        
        $formatted = $this->format_price($price);
        
        if ($this->is_on_sale()) {
            $regular = $this->format_price($this->get_regular_price());
            return '<del>' . $regular . '</del> <ins>' . $formatted . '</ins>';
        }
        
        return '<span class="price">' . $formatted . '</span>';
    }
    
    /**
     * Formatar preço
     */
    protected function format_price($price) {
        $currency = get_option('hng_currency', 'BRL');
        $position = get_option('hng_currency_position', 'left_space');
        $thousand_sep = get_option('hng_thousand_separator', '.');
        $decimal_sep = get_option('hng_decimal_separator', ',');
        $decimals = (int) get_option('hng_number_decimals', 2);
        
        $formatted = number_format($price, $decimals, $decimal_sep, $thousand_sep);
        
        $symbol = 'R$';
        
        switch ($position) {
            case 'left':
                return $symbol . $formatted;
            case 'right':
                return $formatted . $symbol;
            case 'left_space':
                return $symbol . ' ' . $formatted;
            case 'right_space':
                return $formatted . ' ' . $symbol;
            default:
                return $symbol . ' ' . $formatted;
        }
    }
    
    /**
     * Obter estoque
     */
    public function get_stock_quantity() {
        return $this->data['stock_quantity'] ?? null;
    }
    
    /**
     * Obter status do estoque
     */
    public function get_stock_status() {
        return $this->data['stock_status'] ?? 'instock';
    }
    
    /**
     * Está em estoque?
     */
    public function is_in_stock() {
        $status = $this->get_stock_status();
        
        if ($status === 'outofstock') {
            return false;
        }
        
        if ($this->data['manage_stock'] ?? false) {
            $quantity = $this->get_stock_quantity();
            return $quantity === null || $quantity > 0;
        }
        
        return true;
    }
    
    /**
     * Pode ser comprado?
     */
    public function is_purchasable() {
        // Produtos de orçamento são sempre "compráveis" (podem ser adicionados ao carrinho para solicitar orçamento)
        if ($this->get_product_type() === 'quote') {
            return $this->is_in_stock();
        }
        return $this->is_in_stock() && $this->get_price() > 0;
    }
    
    /**
     * Gerencia estoque?
     */
    public function manages_stock() {
        return $this->data['manage_stock'] ?? false;
    }
    
    /**
     * É vendido individualmente?
     */
    public function is_sold_individually() {
        return get_post_meta($this->id, '_sold_individually', true) === 'yes';
    }
    
    /**
     * Obter imagem
     */
    public function get_image($size = 'thumbnail', $attr = []) {
        if (has_post_thumbnail($this->id)) {
            return get_the_post_thumbnail($this->id, $size, $attr);
        }
        
        return $this->get_placeholder_image($size);
    }
    
    /**
     * Obter URL da imagem
     */
    public function get_image_url($size = 'full') {
        if (has_post_thumbnail($this->id)) {
            return get_the_post_thumbnail_url($this->id, $size);
        }
        
        return HNG_COMMERCE_URL . 'assets/images/placeholder.svg';
    }
    
    /**
     * Obter imagem placeholder
     */
    protected function get_placeholder_image($size = 'thumbnail') {
        $placeholder_url = HNG_COMMERCE_URL . 'assets/images/placeholder.svg';
        
        $dimensions = wp_get_registered_image_subsizes();
        $width = $dimensions[$size]['width'] ?? 300;
        $height = $dimensions[$size]['height'] ?? 300;
        
        return '<img src="' . esc_url($placeholder_url) . '" alt="' . esc_attr($this->get_name()) . '" width="' . $width . '" height="' . $height . '" />';
    }
    
    /**
     * Obter tipo do produto (Alias para get_product_type para compatibilidade)
     */
    public function get_type() {
        return $this->get_product_type();
    }

    /**
     * Obter tipo do produto
     */
    public function get_product_type() {
        if (class_exists('HNG_Product_Types')) {
            return HNG_Product_Types::normalize($this->data['product_type'] ?? 'physical');
        }

        $type = sanitize_key($this->data['product_type'] ?? 'physical');
        if ($type === 'simple') {
            $type = 'physical';
        }
        $allowed = ['physical', 'digital', 'subscription', 'quote', 'appointment'];
        return in_array($type, $allowed, true) ? $type : 'physical';
    }
    
    /**
     * Obter taxa de comissáo
     */
    public function get_commission_rate() {
        $type = $this->get_product_type();
        
        switch ($type) {
            case 'physical':
                return (float) get_option('hng_commission_physical', 3.4);
            case 'digital':
                return (float) get_option('hng_commission_digital', 6.4);
            case 'subscription':
                return (float) get_option('hng_commission_subscription', 6.7);
            default:
                return (float) get_option('hng_commission_physical', 3.4);
        }
    }
    
    /**
     * Calcular comissáo
     */
    public function calculate_commission($amount) {
        $rate = $this->get_commission_rate();
        return ($amount * $rate) / 100;
    }
    
    /**
     * Obter categorias
     */
    public function get_categories($sep = ', ', $before = '', $after = '') {
        return get_the_term_list($this->id, 'hng_product_cat', $before, $sep, $after);
    }
    
    /**
     * Obter tags
     */
    public function get_tags($sep = ', ', $before = '', $after = '') {
        return get_the_term_list($this->id, 'hng_product_tag', $before, $sep, $after);
    }
    
    /**
     * Está em destaque?
     */
    public function is_featured() {
        return $this->data['featured'] ?? false;
    }
    
    /**
     * áâ€° produto digital?
     */
    public function is_downloadable() {
        return $this->data['downloadable'] ?? false;
    }
    
    /**
     * áâ€° produto virtual?
     */
    public function is_virtual() {
        return $this->data['virtual'] ?? false;
    }
    
    /**
     * Obter avaliação média
     */
    public function get_average_rating() {
        return $this->data['average_rating'] ?? 0;
    }
    
    /**
     * Obter contagem de avaliaçãoµes
     */
    public function get_rating_count() {
        return $this->data['rating_count'] ?? 0;
    }
    
    /**
     * Obter total de vendas
     */
    public function get_total_sales() {
        return $this->data['total_sales'] ?? 0;
    }
    
    /**
     * Reduzir estoque
     */
    public function reduce_stock($quantity = 1) {
        if (!$this->data['manage_stock']) {
            return false;
        }
        $current = $this->get_stock_quantity();
        $new_stock = $current - $quantity;
        $new_stock = apply_filters('hng_product_new_stock_on_reduce', $new_stock, $current, $quantity, $this->id);
        update_post_meta($this->id, '_stock_quantity', max(0, $new_stock));
        // Atualizar status se necessário
        if ($new_stock <= 0) {
            update_post_meta($this->id, '_stock_status', 'outofstock');
        }
        do_action('hng_product_stock_reduced', $this->id, $quantity, $new_stock);
        return $new_stock;
    }
    
    /**
     * Aumentar estoque
     */
    public function increase_stock($quantity = 1) {
        if (!$this->data['manage_stock']) {
            return false;
        }
        $current = $this->get_stock_quantity();
        $new_stock = $current + $quantity;
        $new_stock = apply_filters('hng_product_new_stock_on_increase', $new_stock, $current, $quantity, $this->id);
        update_post_meta($this->id, '_stock_quantity', $new_stock);
        // Atualizar status
        if ($new_stock > 0) {
            update_post_meta($this->id, '_stock_status', 'instock');
        }
        do_action('hng_product_stock_increased', $this->id, $quantity, $new_stock);
        return $new_stock;
    }
    
    /**
     * Incrementar vendas
     */
    public function increment_sales($quantity = 1) {
        $current = $this->get_total_sales();
        $new_total = $current + $quantity;
        $new_total = apply_filters('hng_product_new_sales_total', $new_total, $current, $quantity, $this->id);
        update_post_meta($this->id, '_total_sales', $new_total);
        do_action('hng_product_sales_incremented', $this->id, $quantity, $new_total);
        return $new_total;
    }
    
    /**
     * Obter dados como array
     */
    public function to_array() {
        return [
            'id' => $this->get_id(),
            'name' => $this->get_name(),
            'slug' => $this->get_slug(),
            'permalink' => $this->get_permalink(),
            'description' => $this->get_description(),
            'short_description' => $this->get_short_description(),
            'sku' => $this->get_sku(),
            'price' => $this->get_price(),
            'regular_price' => $this->get_regular_price(),
            'sale_price' => $this->get_sale_price(),
            'on_sale' => $this->is_on_sale(),
            'price_html' => $this->get_price_html(),
            'stock_quantity' => $this->get_stock_quantity(),
            'stock_status' => $this->get_stock_status(),
            'in_stock' => $this->is_in_stock(),
            'purchasable' => $this->is_purchasable(),
            'image_url' => $this->get_image_url(),
            'product_type' => $this->get_product_type(),
            'commission_rate' => $this->get_commission_rate(),
            'featured' => $this->is_featured(),
            'downloadable' => $this->is_downloadable(),
            'virtual' => $this->is_virtual(),
            'average_rating' => $this->get_average_rating(),
            'rating_count' => $this->get_rating_count(),
            'total_sales' => $this->get_total_sales(),
        ];
    }
}
