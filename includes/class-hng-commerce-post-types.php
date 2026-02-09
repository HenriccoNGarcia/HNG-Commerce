<?php
/**
 * Registro de Custom Post Types
 *
 * @package HNG_Commerce
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Commerce_Post_Types {
    
    /**
     * Registrar post types
     */
    public static function register() {
        self::register_product();
        self::register_coupon();
        self::register_product_category();
        self::register_product_tag();
        
        // Adicionar colunas personalizadas
        add_filter('manage_hng_product_posts_columns', [__CLASS__, 'add_product_columns']);
        add_action('manage_hng_product_posts_custom_column', [__CLASS__, 'render_product_column'], 10, 2);
    }
    
    /**
     * Registrar post type de produto
     */
    private static function register_product() {
        $labels = array(
            'name' => __('Produtos', 'hng-commerce'),
            'singular_name' => __('Produto', 'hng-commerce'),
            'menu_name' => __('Produtos', 'hng-commerce'),
            'add_new' => __('Adicionar Novo', 'hng-commerce'),
            'add_new_item' => __('Adicionar Novo Produto', 'hng-commerce'),
            'edit_item' => __('Editar Produto', 'hng-commerce'),
            'new_item' => __('Novo Produto', 'hng-commerce'),
            'view_item' => __('Ver Produto', 'hng-commerce'),
            'search_items' => __('Buscar Produtos', 'hng-commerce'),
            'not_found' => __('Nenhum produto encontrado', 'hng-commerce'),
            'not_found_in_trash' => __('Nenhum produto na lixeira', 'hng-commerce'),
        );
        
        $args = array(
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => 'hng-commerce',
            'show_in_rest' => true,
            'has_archive' => true,
            'query_var' => true,
            'rewrite' => array(
                'slug' => 'produto',
                'with_front' => false,
                'feeds' => true,
                'pages' => true,
            ),
            'supports' => array('title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'),
            'taxonomies' => array('hng_product_cat', 'hng_product_tag'),
            'capability_type' => 'post',
            'map_meta_cap' => true,
        );
        
        register_post_type('hng_product', $args);
    }
    
    /**
     * Registrar post type de cupom
     */
    private static function register_coupon() {
        $labels = array(
            'name' => __('Cupons', 'hng-commerce'),
            'singular_name' => __('Cupom', 'hng-commerce'),
            'menu_name' => __('Cupons', 'hng-commerce'),
            'add_new' => __('Adicionar Novo', 'hng-commerce'),
            'add_new_item' => __('Adicionar Novo Cupom', 'hng-commerce'),
            'edit_item' => __('Editar Cupom', 'hng-commerce'),
            'new_item' => __('Novo Cupom', 'hng-commerce'),
            'view_item' => __('Ver Cupom', 'hng-commerce'),
            'search_items' => __('Buscar Cupons', 'hng-commerce'),
            'not_found' => __('Nenhum cupom encontrado', 'hng-commerce'),
            'not_found_in_trash' => __('Nenhum cupom na lixeira', 'hng-commerce'),
        );
        
        $args = array(
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'hng-commerce',
            'show_in_rest' => false,
            'supports' => array('title'),
            'capability_type' => 'post',
            'map_meta_cap' => true,
        );
        
        register_post_type('hng_coupon', $args);
    }
    
    /**
     * Registrar taxonomia de categoria
     */
    private static function register_product_category() {
        $labels = array(
            'name' => __('Categorias', 'hng-commerce'),
            'singular_name' => __('Categoria', 'hng-commerce'),
            'menu_name' => __('Categorias', 'hng-commerce'),
            'search_items' => __('Buscar Categorias', 'hng-commerce'),
            'all_items' => __('Todas as Categorias', 'hng-commerce'),
            'parent_item' => __('Categoria Pai', 'hng-commerce'),
            'parent_item_colon' => __('Categoria Pai:', 'hng-commerce'),
            'edit_item' => __('Editar Categoria', 'hng-commerce'),
            'update_item' => __('Atualizar Categoria', 'hng-commerce'),
            'add_new_item' => __('Adicionar Nova Categoria', 'hng-commerce'),
            'new_item_name' => __('Novo Nome de Categoria', 'hng-commerce'),
        );
        
        $args = array(
            'labels' => $labels,
            'hierarchical' => true,
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'categoria-produto')
        );
        
        register_taxonomy('hng_product_cat', array('hng_product'), $args);
    }
    
    /**
     * Registrar taxonomia de tag
     */
    private static function register_product_tag() {
        $labels = array(
            'name' => __('Tags', 'hng-commerce'),
            'singular_name' => __('Tag', 'hng-commerce'),
            'menu_name' => __('Tags', 'hng-commerce'),
            'search_items' => __('Buscar Tags', 'hng-commerce'),
            'all_items' => __('Todas as Tags', 'hng-commerce'),
            'edit_item' => __('Editar Tag', 'hng-commerce'),
            'update_item' => __('Atualizar Tag', 'hng-commerce'),
            'add_new_item' => __('Adicionar Nova Tag', 'hng-commerce'),
            'new_item_name' => __('Novo Nome de Tag', 'hng-commerce'),
        );
        
        $args = array(
            'labels' => $labels,
            'hierarchical' => false,
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'tag-produto')
        );
        
        register_taxonomy('hng_product_tag', array('hng_product'), $args);
    }
    
    /**
     * Adicionar colunas personalizadas à lista de produtos
     */
    public static function add_product_columns($columns) {
        // Inserir ID logo após o checkbox
        $new_columns = [];
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'cb') {
                $new_columns['product_id'] = __('ID', 'hng-commerce');
            }
        }
        return $new_columns;
    }
    
    /**
     * Renderizar conteúdo da coluna personalizada
     */
    public static function render_product_column($column, $post_id) {
        if ($column === 'product_id') {
            echo '<strong>#' . esc_html($post_id) . '</strong>';
        }
    }
}
