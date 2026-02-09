<?php
/**
 * Helpers centralizados para widgets Elementor do HNG Commerce
 * 
 * Fornece métodos para busca de produtos, simulação de dados,
 * e suporte a todos os tipos de produto.
 * 
 * @package HNG_Commerce
 * @since 1.2.16
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Elementor_Helpers {
    
    /**
     * Instância singleton
     */
    private static $instance = null;
    
    /**
     * Obter instância única
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Tipos de produto suportados pelo HNG Commerce
     */
    public static function get_product_types() {
        return [
            'physical' => __('Produto Físico', 'hng-commerce'),
            'digital' => __('Produto Digital', 'hng-commerce'),
            'subscription' => __('Assinatura', 'hng-commerce'),
            'appointment' => __('Agendamento', 'hng-commerce'),
            'quote' => __('Orçamento', 'hng-commerce'),
        ];
    }
    
    /**
     * Buscar produtos do HNG Commerce
     * 
     * @param array $args Argumentos de busca
     * @return array Array de objetos HNG_Product
     */
    public static function get_products($args = []) {
        $defaults = [
            'numberposts' => 10,
            'post_type' => 'hng_product',
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC',
            'product_type' => '', // Filtrar por tipo
            'category' => '', // Filtrar por categoria
            'search' => '', // Buscar por termo
            'include_ids' => [], // IDs específicos
            'exclude_ids' => [], // Excluir IDs
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $query_args = [
            'post_type' => 'hng_product',
            'post_status' => $args['post_status'],
            'posts_per_page' => $args['numberposts'],
            'orderby' => $args['orderby'],
            'order' => $args['order'],
        ];
        
        // Filtrar por IDs específicos
        if (!empty($args['include_ids'])) {
            $query_args['post__in'] = array_map('absint', (array) $args['include_ids']);
        }
        
        // Excluir IDs
        if (!empty($args['exclude_ids'])) {
            $query_args['post__not_in'] = array_map('absint', (array) $args['exclude_ids']);
        }
        
        // Filtrar por tipo de produto
        if (!empty($args['product_type'])) {
            $query_args['meta_query'] = isset($query_args['meta_query']) ? $query_args['meta_query'] : [];
            $query_args['meta_query'][] = [
                'relation' => 'OR',
                [
                    'key' => '_hng_product_type',
                    'value' => $args['product_type'],
                    'compare' => '='
                ],
                [
                    'key' => '_product_type',
                    'value' => $args['product_type'],
                    'compare' => '='
                ],
            ];
        }
        
        // Filtrar por categoria
        if (!empty($args['category'])) {
            $query_args['tax_query'] = [
                [
                    'taxonomy' => 'hng_product_category',
                    'field' => is_numeric($args['category']) ? 'term_id' : 'slug',
                    'terms' => $args['category'],
                ],
            ];
        }
        
        // Buscar por termo
        if (!empty($args['search'])) {
            $query_args['s'] = sanitize_text_field($args['search']);
        }
        
        $posts = get_posts($query_args);
        $products = [];
        
        foreach ($posts as $post) {
            if (class_exists('HNG_Product')) {
                $products[] = new HNG_Product($post->ID);
            }
        }
        
        return $products;
    }
    
    /**
     * Buscar produto por ID
     * 
     * @param int $product_id ID do produto
     * @return HNG_Product|null Objeto produto ou null
     */
    public static function get_product($product_id) {
        if (!$product_id || !class_exists('HNG_Product')) {
            return null;
        }
        
        $product = new HNG_Product($product_id);
        return $product->get_id() ? $product : null;
    }
    
    /**
     * Buscar primeiro produto disponível
     * 
     * @param string $product_type Tipo de produto (opcional)
     * @return HNG_Product|null
     */
    public static function get_first_product($product_type = '') {
        $args = [
            'numberposts' => 1,
            'product_type' => $product_type,
        ];
        
        $products = self::get_products($args);
        return !empty($products) ? $products[0] : null;
    }
    
    /**
     * Gerar dados simulados de produto para prévia no Elementor
     * 
     * @param string $type Tipo de produto
     * @return array Dados simulados
     */
    public static function get_simulated_product($type = 'physical') {
        $types = [
            'physical' => [
                'name' => __('Produto Exemplo', 'hng-commerce'),
                'price' => 99.90,
                'regular_price' => 129.90,
                'description' => __('Este é um produto de demonstração para visualização no Elementor.', 'hng-commerce'),
                'sku' => 'HNG-DEMO-001',
                'stock_status' => 'instock',
                'stock_quantity' => 50,
            ],
            'digital' => [
                'name' => __('E-book Exemplo', 'hng-commerce'),
                'price' => 49.90,
                'regular_price' => 79.90,
                'description' => __('Produto digital de demonstração - Download instantâneo.', 'hng-commerce'),
                'sku' => 'HNG-DIGITAL-001',
                'download_url' => '#',
            ],
            'subscription' => [
                'name' => __('Plano Mensal Premium', 'hng-commerce'),
                'price' => 29.90,
                'regular_price' => 29.90,
                'description' => __('Assinatura recorrente de demonstração.', 'hng-commerce'),
                'sku' => 'HNG-SUB-001',
                'billing_period' => 'month',
                'billing_interval' => 1,
            ],
            'appointment' => [
                'name' => __('Consulta Agendada', 'hng-commerce'),
                'price' => 150.00,
                'regular_price' => 150.00,
                'description' => __('Serviço de agendamento de demonstração.', 'hng-commerce'),
                'sku' => 'HNG-AGENDA-001',
                'duration' => 60,
                'duration_unit' => 'minutes',
            ],
            'quote' => [
                'name' => __('Produto Sob Consulta', 'hng-commerce'),
                'price' => 0,
                'regular_price' => 0,
                'description' => __('Produto que requer orçamento personalizado.', 'hng-commerce'),
                'sku' => 'HNG-QUOTE-001',
                'requires_quote' => true,
            ],
        ];
        
        return isset($types[$type]) ? $types[$type] : $types['physical'];
    }
    
    /**
     * Gerar itens simulados de carrinho para prévia
     * 
     * @param int $count Quantidade de itens
     * @return array Itens simulados do carrinho
     */
    public static function get_simulated_cart_items($count = 2) {
        $items = [];
        
        // Tentar buscar produtos reais primeiro
        $real_products = self::get_products(['numberposts' => $count]);
        
        if (!empty($real_products)) {
            foreach ($real_products as $index => $product) {
                $items['demo_' . $index] = [
                    'product_id' => $product->get_id(),
                    'quantity' => $index + 1,
                    'name' => $product->get_name(),
                    'price' => $product->get_price(),
                    'image_url' => $product->get_image_url('thumbnail'),
                    'is_simulated' => true,
                ];
            }
        } else {
            // Usar dados completamente simulados
            $demo_products = [
                [
                    'name' => __('Produto Demo 1', 'hng-commerce'),
                    'price' => 99.90,
                    'quantity' => 2,
                ],
                [
                    'name' => __('Produto Demo 2', 'hng-commerce'),
                    'price' => 149.90,
                    'quantity' => 1,
                ],
                [
                    'name' => __('Assinatura Demo', 'hng-commerce'),
                    'price' => 29.90,
                    'quantity' => 1,
                ],
            ];
            
            for ($i = 0; $i < min($count, count($demo_products)); $i++) {
                $items['demo_' . $i] = [
                    'product_id' => 0,
                    'quantity' => $demo_products[$i]['quantity'],
                    'name' => $demo_products[$i]['name'],
                    'price' => $demo_products[$i]['price'],
                    'image_url' => HNG_COMMERCE_URL . 'assets/images/placeholder.svg',
                    'is_simulated' => true,
                ];
            }
        }
        
        return $items;
    }
    
    /**
     * Gerar pedido simulado para prévia
     * 
     * @return array Dados simulados de pedido
     */
    public static function get_simulated_order() {
        return [
            'id' => 9999,
            'number' => '#HNG-9999',
            'status' => 'processing',
            'status_label' => __('Processando', 'hng-commerce'),
            'date_created' => current_time('mysql'),
            'date_formatted' => date_i18n(get_option('date_format'), current_time('timestamp')),
            'total' => 349.70,
            'subtotal' => 349.70,
            'shipping' => 0,
            'discount' => 0,
            'payment_method' => 'pix',
            'payment_method_title' => __('PIX', 'hng-commerce'),
            'items' => self::get_simulated_cart_items(2),
            'billing' => [
                'first_name' => 'João',
                'last_name' => 'Silva',
                'email' => 'joao@exemplo.com',
                'phone' => '(11) 99999-9999',
                'address_1' => 'Rua Exemplo, 123',
                'city' => 'São Paulo',
                'state' => 'SP',
                'postcode' => '01234-567',
            ],
            'is_simulated' => true,
        ];
    }
    
    /**
     * Gerar assinatura simulada para prévia
     * 
     * @return array Dados simulados de assinatura
     */
    public static function get_simulated_subscription() {
        return [
            'id' => 8888,
            'status' => 'active',
            'status_label' => __('Ativa', 'hng-commerce'),
            'start_date' => gmdate('Y-m-d', strtotime('-30 days')),
            'next_payment' => gmdate('Y-m-d', strtotime('+30 days')),
            'next_payment_formatted' => date_i18n(get_option('date_format'), strtotime('+30 days')),
            'billing_period' => 'month',
            'billing_interval' => 1,
            'recurring_total' => 29.90,
            'product_name' => __('Plano Premium Mensal', 'hng-commerce'),
            'is_simulated' => true,
        ];
    }
    
    /**
     * Gerar controles Elementor para seleção de produto
     * 
     * @param object $widget Instância do widget
     * @param string $prefix Prefixo para IDs dos controles
     */
    public static function add_product_selector_controls($widget, $prefix = '') {
        $widget->add_control(
            $prefix . 'product_source',
            [
                'label' => __('Origem do Produto', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    'current' => __('Produto Atual (Página de Produto)', 'hng-commerce'),
                    'specific' => __('Produto Específico (por ID)', 'hng-commerce'),
                    'latest' => __('Produto Mais Recente', 'hng-commerce'),
                    'random' => __('Produto Aleatório', 'hng-commerce'),
                    'by_type' => __('Por Tipo de Produto', 'hng-commerce'),
                ],
                'default' => 'current',
            ]
        );
        
        $widget->add_control(
            $prefix . 'product_id',
            [
                'label' => __('ID do Produto', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 0,
                'description' => __('Insira o ID do produto HNG Commerce', 'hng-commerce'),
                'condition' => [
                    $prefix . 'product_source' => 'specific',
                ],
            ]
        );
        
        $widget->add_control(
            $prefix . 'product_type_filter',
            [
                'label' => __('Tipo de Produto', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => array_merge(
                    ['' => __('Qualquer Tipo', 'hng-commerce')],
                    self::get_product_types()
                ),
                'default' => '',
                'condition' => [
                    $prefix . 'product_source' => 'by_type',
                ],
            ]
        );
    }
    
    /**
     * Obter produto baseado nas configurações do widget
     * 
     * @param array $settings Configurações do widget
     * @param string $prefix Prefixo dos controles
     * @param bool $is_edit_mode Se está em modo de edição
     * @return HNG_Product|null
     */
    public static function get_product_from_settings($settings, $prefix = '', $is_edit_mode = false) {
        $source = isset($settings[$prefix . 'product_source']) ? $settings[$prefix . 'product_source'] : 'current';
        
        switch ($source) {
            case 'specific':
                $product_id = isset($settings[$prefix . 'product_id']) ? absint($settings[$prefix . 'product_id']) : 0;
                if ($product_id > 0) {
                    return self::get_product($product_id);
                }
                break;
                
            case 'latest':
                return self::get_first_product();
                
            case 'random':
                $products = self::get_products(['numberposts' => 1, 'orderby' => 'rand']);
                return !empty($products) ? $products[0] : null;
                
            case 'by_type':
                $type = isset($settings[$prefix . 'product_type_filter']) ? $settings[$prefix . 'product_type_filter'] : '';
                return self::get_first_product($type);
                
            case 'current':
            default:
                global $post;
                if ($post && $post->post_type === 'hng_product') {
                    return self::get_product($post->ID);
                }
                // Em modo de edição, pegar primeiro produto
                if ($is_edit_mode) {
                    return self::get_first_product();
                }
                break;
        }
        
        return null;
    }
    
    /**
     * Formatar preço para exibição
     * 
     * @param float $price Preço
     * @return string Preço formatado
     */
    public static function format_price($price) {
        if (function_exists('hng_price')) {
            return hng_price($price);
        }
        return 'R$ ' . number_format($price, 2, ',', '.');
    }
    
    /**
     * Obter URL da imagem placeholder
     * 
     * @return string URL do placeholder
     */
    public static function get_placeholder_image() {
        return HNG_COMMERCE_URL . 'assets/images/placeholder.svg';
    }
    
    /**
     * Renderizar badge de tipo de produto
     * 
     * @param string $type Tipo de produto
     * @return string HTML do badge
     */
    public static function render_product_type_badge($type) {
        $types = self::get_product_types();
        $label = isset($types[$type]) ? $types[$type] : $type;
        
        $colors = [
            'physical' => '#28a745',
            'digital' => '#007bff',
            'subscription' => '#6f42c1',
            'appointment' => '#fd7e14',
            'quote' => '#17a2b8',
        ];
        
        $color = isset($colors[$type]) ? $colors[$type] : '#6c757d';
        
        return sprintf(
            '<span class="hng-product-type-badge" style="background-color: %s; color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 11px; text-transform: uppercase;">%s</span>',
            esc_attr($color),
            esc_html($label)
        );
    }
    
    /**
     * Renderizar alerta de prévia do Elementor
     * 
     * @param string $message Mensagem
     * @param string $type Tipo (info, warning, danger)
     * @return string HTML do alerta
     */
    public static function render_elementor_alert($message, $type = 'info') {
        return sprintf(
            '<div class="elementor-alert elementor-alert-%s" style="margin: 10px 0; padding: 15px; border-radius: 5px;"><p style="margin: 0;">%s</p></div>',
            esc_attr($type),
            wp_kses_post($message)
        );
    }
    
    /**
     * Verificar se produto existe no banco de dados
     * 
     * @param int $product_id ID do produto
     * @return bool
     */
    public static function product_exists($product_id) {
        $post = get_post($product_id);
        return $post && $post->post_type === 'hng_product' && $post->post_status === 'publish';
    }
    
    /**
     * Obter lista de produtos para dropdown no Elementor
     * 
     * @param int $limit Limite de produtos
     * @return array Array [id => nome]
     */
    public static function get_products_for_dropdown($limit = 100) {
        $products = get_posts([
            'post_type' => 'hng_product',
            'post_status' => 'publish',
            'numberposts' => $limit,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        
        $options = [0 => __('Selecione um produto...', 'hng-commerce')];
        
        foreach ($products as $product) {
            $type = get_post_meta($product->ID, '_hng_product_type', true);
            if (empty($type)) {
                $type = get_post_meta($product->ID, '_product_type', true);
            }
            $type_label = isset(self::get_product_types()[$type]) ? ' [' . self::get_product_types()[$type] . ']' : '';
            $options[$product->ID] = $product->post_title . $type_label . ' (ID: ' . $product->ID . ')';
        }
        
        return $options;
    }
    
    /**
     * Obter categorias de produtos para dropdown
     * 
     * @return array Array [id => nome]
     */
    public static function get_categories_for_dropdown() {
        $categories = get_terms([
            'taxonomy' => 'hng_product_category',
            'hide_empty' => false,
        ]);
        
        $options = ['' => __('Todas as categorias', 'hng-commerce')];
        
        if (!is_wp_error($categories)) {
            foreach ($categories as $category) {
                $options[$category->term_id] = $category->name . ' (' . $category->count . ')';
            }
        }
        
        return $options;
    }
}
