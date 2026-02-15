<?php
/**
 * Shortcodes do HNG Commerce
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Shortcodes {
    
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
        add_shortcode('hng_products', [$this, 'products_shortcode']);
        add_shortcode('hng_product', [$this, 'product_shortcode']);
        add_shortcode('hng_cart', [$this, 'cart_shortcode']);
        add_shortcode('hng_checkout', [$this, 'checkout_shortcode']);
        add_shortcode('hng_my_account', [$this, 'my_account_shortcode']);
        add_shortcode('hng_order_received', [$this, 'order_received_shortcode']);
    }
    
    /**
     * Shortcode: Lista de produtos
     * 
     * [hng_products limit="12" columns="4" orderby="date" order="DESC" category="" featured="no"]
     */
    public function products_shortcode($atts) {
        $atts = shortcode_atts([
            'limit' => 12,
            'columns' => 4,
            'orderby' => 'date',
            'order' => 'DESC',
            'category' => '',
            'featured' => 'no',
            'on_sale' => 'no',
        ], $atts, 'hng_products');
        
        $args = [
            'post_type' => 'hng_product',
            'posts_per_page' => intval($atts['limit']),
            'orderby' => $atts['orderby'],
            'order' => $atts['order'],
            'post_status' => 'publish',
        ];
        
        // Filtro por categoria
        if (!empty($atts['category'])) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'hng_product_cat',
                    'field' => 'slug',
                    'terms' => explode(',', $atts['category']),
                ],
            ];
        }
        
        // Produtos em destaque
        if ($atts['featured'] === 'yes') {
            $args['meta_query'][] = [
                'key' => '_featured',
                'value' => 'yes',
            ];
        }
        
        // Produtos em promoï¿½ï¿½o
        if ($atts['on_sale'] === 'yes') {
            $args['meta_query'][] = [
                'key' => '_sale_price',
                'value' => 0,
                'compare' => '>',
                'type' => 'NUMERIC',
            ];
        }
        
        $query = new WP_Query($args);
        
        ob_start();
        
        if ($query->have_posts()) {
            echo '<div class="hng-products columns-' . esc_attr($atts['columns']) . '">';
            
            while ($query->have_posts()) {
                $query->the_post();
                hng_get_template_part('content', 'product');
            }
            
            echo '</div>';
        } else {
            echo '<p>' . esc_html__('Nenhum produto encontrado.', 'hng-commerce') . '</p>';
        }
        
        wp_reset_postdata();
        
        return ob_get_clean();
    }
    
    /**
     * Shortcode: Produto ï¿½nico
     * 
     * [hng_product id="123"]
     */
    public function product_shortcode($atts) {
        $atts = shortcode_atts([
            'id' => 0,
        ], $atts, 'hng_product');
        
        if (empty($atts['id'])) {
            return '';
        }
        
        $product = hng_get_product($atts['id']);
        
        if (!$product->get_id()) {
            return '';
        }
        
        ob_start();
        hng_get_template('single-product.php', ['product' => $product]);
        return ob_get_clean();
    }
    
    /**
     * Shortcode: Carrinho
     * 
     * [hng_cart]
     */
    public function cart_shortcode($atts) {
        ob_start();
        hng_get_template('cart.php');
        return ob_get_clean();
    }
    
    /**
     * Shortcode: Checkout
     * 
     * [hng_checkout]
     */
    public function checkout_shortcode($atts) {
        ob_start();
        hng_get_template('checkout.php');
        return ob_get_clean();
    }
    
    /**
     * Shortcode: Minha Conta
     * 
     * [hng_my_account]
     */
    public function my_account_shortcode($atts) {
        ob_start();
        hng_get_template('my-account.php');
        return ob_get_clean();
    }
    
    /**
     * Shortcode: Pedido Recebido
     * 
     * [hng_order_received]
     */
    public function order_received_shortcode($atts) {
        ob_start();
        hng_get_template('order-received.php');
        return ob_get_clean();
    }
}

/**
 * Obter template part
 */
function hng_get_template_part($slug, $name = '') {
    $template = '';
    
    if ($name) {
        $template = locate_template([
            "hng-commerce/{$slug}-{$name}.php",
            "hng-commerce/{$slug}.php",
        ]);
    }
    
    if (!$template) {
        $template = locate_template(["hng-commerce/{$slug}.php"]);
    }
    
    // Fallback para templates do plugin
    if (!$template) {
        if ($name && file_exists(HNG_COMMERCE_PATH . "templates/{$slug}-{$name}.php")) {
            $template = HNG_COMMERCE_PATH . "templates/{$slug}-{$name}.php";
        } elseif (file_exists(HNG_COMMERCE_PATH . "templates/{$slug}.php")) {
            $template = HNG_COMMERCE_PATH . "templates/{$slug}.php";
        }
    }
    
    if ($template) {
        load_template($template, false);
    }
}

/**
 * Obter template
 */
function hng_get_template($template_name, $args = []) {
    if (!empty($args) && is_array($args)) {
        extract($args);
    }
    
    $template = locate_template(["hng-commerce/{$template_name}"]);
    
    if (!$template) {
        $template = HNG_COMMERCE_PATH . "templates/{$template_name}";
    }
    
    if (file_exists($template)) {
        load_template($template, false);
    }
}
