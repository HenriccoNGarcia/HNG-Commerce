<?php
/**
 * Frontend - Gerenciamento do frontend
 *
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Frontend {
    
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
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_filter('template_include', [$this, 'template_loader'], 10);
        add_filter('body_class', [$this, 'body_class']);
        
        // Shortcodes para páginas
        add_shortcode('hng_cart', [$this, 'cart_shortcode']);
        add_shortcode('hng_checkout', [$this, 'checkout_shortcode']);
        add_shortcode('hng_my_account', [$this, 'my_account_shortcode']);
        add_shortcode('hng_products', [$this, 'products_shortcode']);
        add_shortcode('hng_order_received', [$this, 'order_received_shortcode']);
    }
    
    /**
     * Enfileirar scripts e estilos
     */
    public function enqueue_scripts() {
        // CSS Principal
        wp_enqueue_style(
            'hng-commerce-frontend',
            HNG_COMMERCE_URL . 'assets/css/frontend.css',
            [],
            HNG_COMMERCE_VERSION
        );
        
        // CSS dos Templates
        wp_enqueue_style(
            'hng-commerce-templates',
            HNG_COMMERCE_URL . 'assets/css/templates.css',
            ['hng-commerce-frontend'],
            HNG_COMMERCE_VERSION
        );
        
        // JavaScript
        wp_enqueue_script(
            'hng-commerce-frontend',
            HNG_COMMERCE_URL . 'assets/js/frontend.js',
            ['jquery'],
            HNG_COMMERCE_VERSION,
            true
        );
        
        // Localizar script
        $settings = get_option('hng_commerce_settings', []);
        $redirect_to_checkout = ($settings['redirect_to_checkout_after_add'] ?? 'no') === 'yes';
        
        wp_localize_script('hng-commerce-frontend', 'hng_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'cart_nonce' => wp_create_nonce('hng_cart_actions'),
            'add_to_cart_nonce' => wp_create_nonce('hng_add_to_cart'),
            'checkout_url' => function_exists('hng_get_checkout_url') ? hng_get_checkout_url() : home_url('/finalizar-compra/'),
            'cart_url' => function_exists('hng_get_cart_url') ? hng_get_cart_url() : home_url('/carrinho/'),
            'account_url' => function_exists('hng_get_account_url') ? hng_get_account_url() : home_url('/minha-conta/'),
            'redirect_to_checkout' => $redirect_to_checkout,
            'i18n' => [
                'add_to_cart' => __('Adicionar ao Carrinho', 'hng-commerce'),
                'adding' => __('Adicionando...', 'hng-commerce'),
                'added' => __('Adicionado!', 'hng-commerce'),
                'view_cart' => __('Ver Carrinho', 'hng-commerce'),
                'error' => __('Erro', 'hng-commerce'),
                'confirm_remove' => __('Remover este item?', 'hng-commerce'),
            ],
        ]);
    }
    
    /**
     * Carregar templates customizados
     * 
     * Ordem de prioridade:
     * 1. Template do Elementor (se página foi criada com Elementor)
     * 2. Template do tema (pasta hng-commerce/ ou single-hng_product.php)
     * 3. Template padrão do plugin
     */
    public function template_loader($template) {
        // Verificar se é uma página do Elementor
        if ($this->is_elementor_template()) {
            return $template;
        }
        
        // Produto individual
        if (is_singular('hng_product')) {
            return $this->locate_template('single-product.php', $template);
        }
        
        // Arquivo de produtos
        if (is_post_type_archive('hng_product') || is_tax(['hng_product_cat', 'hng_product_tag'])) {
            return $this->locate_template('archive-product.php', $template);
        }
        
        return $template;
    }
    
    /**
     * Localizar template com fallback
     */
    private function locate_template($template_name, $default) {
        // Nome alternativo para WordPress (single-hng_product.php)
        $wp_template_name = '';
        if ($template_name === 'single-product.php') {
            $wp_template_name = 'single-hng_product.php';
        } elseif ($template_name === 'archive-product.php') {
            $wp_template_name = 'archive-hng_product.php';
        }
        
        $template_paths = [
            // 1. Template WordPress padrão no tema filho
            get_stylesheet_directory() . '/' . $wp_template_name,
            // 2. Template WordPress padrão no tema pai
            get_template_directory() . '/' . $wp_template_name,
            // 3. Pasta hng-commerce no tema filho
            get_stylesheet_directory() . '/hng-commerce/' . $template_name,
            // 4. Pasta hng-commerce no tema pai
            get_template_directory() . '/hng-commerce/' . $template_name,
        ];
        
        foreach ($template_paths as $path) {
            if (!empty($path) && file_exists($path)) {
                return $path;
            }
        }
        
        // Fallback: template do plugin
        $plugin_template = HNG_COMMERCE_PATH . 'templates/' . $template_name;
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
        
        return $default;
    }
    
    /**
     * Verificar se a página usa Elementor
     */
    private function is_elementor_template() {
        if (!class_exists('\Elementor\Plugin')) {
            return false;
        }
        
        global $post;
        if (!$post) {
            return false;
        }
        
        // Verificar se Elementor está ativo para este post
        $elementor_data = get_post_meta($post->ID, '_elementor_data', true);
        $elementor_edit = get_post_meta($post->ID, '_elementor_edit_mode', true);
        
        return !empty($elementor_data) && $elementor_edit === 'builder';
    }
    
    /**
     * Adicionar classes ao body
     */
    public function body_class($classes) {
        if (is_singular('hng_product')) {
            $classes[] = 'hng-commerce';
            $classes[] = 'hng-product-page';
            
            // Adicionar classe do tipo de produto
            global $post;
            $product_type = get_post_meta($post->ID, '_hng_product_type', true);
            if ($product_type) {
                $classes[] = 'hng-product-type-' . sanitize_html_class($product_type);
            }
        }
        
        if (is_post_type_archive('hng_product') || is_tax(['hng_product_cat', 'hng_product_tag'])) {
            $classes[] = 'hng-commerce';
            $classes[] = 'hng-shop-page';
        }
        
        // Detectar páginas por shortcode
        global $post;
        if ($post && is_a($post, 'WP_Post')) {
            if (has_shortcode($post->post_content, 'hng_cart')) {
                $classes[] = 'hng-commerce';
                $classes[] = 'hng-cart-page';
            }
            if (has_shortcode($post->post_content, 'hng_checkout')) {
                $classes[] = 'hng-commerce';
                $classes[] = 'hng-checkout-page';
            }
            if (has_shortcode($post->post_content, 'hng_my_account')) {
                $classes[] = 'hng-commerce';
                $classes[] = 'hng-account-page';
            }
        }
        
        return array_unique($classes);
    }
    
    /**
     * Shortcode: Carrinho
     */
    public function cart_shortcode($atts) {
        ob_start();
        $this->load_template_part('cart');
        return ob_get_clean();
    }
    
    /**
     * Shortcode: Checkout
     */
    public function checkout_shortcode($atts) {
        ob_start();
        $this->load_template_part('checkout');
        return ob_get_clean();
    }
    
    /**
     * Shortcode: Minha Conta
     */
    public function my_account_shortcode($atts) {
        ob_start();
        $this->load_template_part('my-account');
        return ob_get_clean();
    }
    
    /**
     * Shortcode: Pedido Recebido
     */
    public function order_received_shortcode($atts) {
        ob_start();
        $this->load_template_part('order-received');
        return ob_get_clean();
    }
    
    /**
     * Shortcode: Lista de Produtos
     */
    public function products_shortcode($atts) {
        $atts = shortcode_atts([
            'limit' => 12,
            'columns' => 4,
            'category' => '',
            'tag' => '',
            'orderby' => 'date',
            'order' => 'DESC',
            'ids' => '',
        ], $atts, 'hng_products');
        
        $args = [
            'post_type' => 'hng_product',
            'posts_per_page' => intval($atts['limit']),
            'orderby' => $atts['orderby'],
            'order' => $atts['order'],
            'post_status' => 'publish',
        ];
        
        if (!empty($atts['ids'])) {
            $args['post__in'] = array_map('intval', explode(',', $atts['ids']));
        }
        
        if (!empty($atts['category'])) {
            $args['tax_query'][] = [
                'taxonomy' => 'hng_product_cat',
                'field' => 'slug',
                'terms' => explode(',', $atts['category']),
            ];
        }
        
        if (!empty($atts['tag'])) {
            $args['tax_query'][] = [
                'taxonomy' => 'hng_product_tag',
                'field' => 'slug',
                'terms' => explode(',', $atts['tag']),
            ];
        }
        
        $products = new WP_Query($args);
        
        ob_start();
        
        if ($products->have_posts()) {
            echo '<div class="hng-products-grid hng-columns-' . intval($atts['columns']) . '">';
            while ($products->have_posts()) {
                $products->the_post();
                $this->load_template_part('content-product');
            }
            echo '</div>';
            wp_reset_postdata();
        } else {
            echo '<p class="hng-no-products">' . esc_html__('Nenhum produto encontrado.', 'hng-commerce') . '</p>';
        }
        
        return ob_get_clean();
    }
    
    /**
     * Carregar parte do template
     */
    private function load_template_part($name) {
        $paths = [
            get_stylesheet_directory() . '/hng-commerce/' . $name . '.php',
            get_template_directory() . '/hng-commerce/' . $name . '.php',
            HNG_COMMERCE_PATH . 'templates/' . $name . '.php',
        ];
        
        foreach ($paths as $path) {
            if (file_exists($path)) {
                include $path;
                return;
            }
        }
    }
}
