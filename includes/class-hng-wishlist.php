<?php
/**
 * Sistema de Lista de Desejos (Wishlist)
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Helpers DB
if (file_exists(HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php')) {
    require_once HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php';
}

class HNG_Wishlist {
    
    /**
     * Inst ncia  nica
     */
    private static $instance = null;
    
    /**
     * Obter inst ncia
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
        // Criar tabela
        add_action('hng_commerce_activate', [$this, 'create_table']);
        
        // AJAX handlers
        add_action('wp_ajax_hng_add_to_wishlist', [$this, 'add_to_wishlist']);
        add_action('wp_ajax_nopriv_hng_add_to_wishlist', [$this, 'add_to_wishlist']);
        
        add_action('wp_ajax_hng_remove_from_wishlist', [$this, 'remove_from_wishlist']);
        add_action('wp_ajax_nopriv_hng_remove_from_wishlist', [$this, 'remove_from_wishlist']);
        
        add_action('wp_ajax_hng_get_wishlist', [$this, 'get_wishlist']);
        add_action('wp_ajax_nopriv_hng_get_wishlist', [$this, 'get_wishlist']);
        
        // Shortcodes
        add_shortcode('hng_wishlist', [$this, 'wishlist_page']);
        
        // Enqueue scripts
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }
    
    /**
     * Criar tabela de wishlist
     */
    public function create_table() {
        global $wpdb;
        
        $table_name = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_wishlist') : ($wpdb->prefix . 'hng_wishlist');
        $charset_collate = $wpdb->get_charset_collate();
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via helper/prefix, dbDelta requires literal SQL
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Database schema installation
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names sanitized via hng_db_full_table_name()
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) DEFAULT 0,
            session_id varchar(255) DEFAULT '',
            product_id bigint(20) NOT NULL,
            added_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY session_id (session_id),
            KEY product_id (product_id),
            UNIQUE KEY user_product (user_id, product_id),
            UNIQUE KEY session_product (session_id, product_id)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
    
    /**
     * Enfileirar scripts
     */
    public function enqueue_scripts() {
        $current_post = get_post();
        $post_content = $current_post ? $current_post->post_content : '';
        
        if (!is_singular('hng_product') && !is_post_type_archive('hng_product') && !has_shortcode($post_content, 'hng_wishlist')) {
            return;
        }
        
        wp_enqueue_style('hng-wishlist', HNG_COMMERCE_URL . 'assets/css/wishlist.css', [], HNG_COMMERCE_VERSION);
        wp_enqueue_script('hng-wishlist', HNG_COMMERCE_URL . 'assets/js/wishlist.js', ['jquery'], HNG_COMMERCE_VERSION, true);
        
        wp_localize_script('hng-wishlist', 'hngWishlist', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('hng_wishlist'),
            'addedText' => __('Adicionado aos favoritos!', 'hng-commerce'),
            'removedText' => __('Removido dos favoritos.', 'hng-commerce'),
            'errorText' => __('Erro. Tente novamente.', 'hng-commerce'),
        ]);
    }
    
    /**
     * Adicionar   wishlist
     */
    public function add_to_wishlist() {
        check_ajax_referer('hng_wishlist', 'nonce');
        
        $product_id = absint(wp_unslash($_POST['product_id'] ?? 0));
        
        if (empty($product_id)) {
            wp_send_json_error(['message' => __('Produto inv lido.', 'hng-commerce')]);
        }
        
        // Verificar se produto existe
        $product = get_post($product_id);
        if (!$product || $product->post_type !== 'hng_product') {
            wp_send_json_error(['message' => __('Produto n o encontrado.', 'hng-commerce')]);
        }
        
        global $wpdb;
        $table_full = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_wishlist') : ($wpdb->prefix . 'hng_wishlist');
        $table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_wishlist') : ('`' . str_replace('`','', $table_full) . '`');

        $user_id = get_current_user_id();
        $session_id = $this->get_session_id();

        // Verificar se j  existe
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_sql sanitized via hng_db_backtick_table()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for wishlist management, user product tracking
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table_sql sanitized via hng_db_backtick_table()
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_sql} 
            WHERE product_id = %d AND (user_id = %d OR session_id = %s)",
            $product_id,
            $user_id,
            $session_id
        ));
        
        if ($exists) {
            wp_send_json_error(['message' => __('Produto j  est  nos favoritos.', 'hng-commerce')]);
        }
        
        // Inserir
        $inserted = $wpdb->insert(
            $table_full,
            [
                'user_id' => $user_id,
                'session_id' => $session_id,
                'product_id' => $product_id,
                'added_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%d', '%s']
        );
        
        if (!$inserted) {
            wp_send_json_error(['message' => __('Erro ao adicionar aos favoritos.', 'hng-commerce')]);
        }
        
        // Obter contagem atualizada
        $count = $this->get_wishlist_count();
        
        wp_send_json_success([
            'message' => __('Produto adicionado aos favoritos!', 'hng-commerce'),
            'count' => $count,
        ]);
    }
    
    /**
     * Remover da wishlist
     */
    public function remove_from_wishlist() {
        check_ajax_referer('hng_wishlist', 'nonce');
        
        $product_id = absint(wp_unslash($_POST['product_id'] ?? 0));
        
        if (empty($product_id)) {
            wp_send_json_error(['message' => __('Produto inv lido.', 'hng-commerce')]);
        }
        
        global $wpdb;
        
        $user_id = get_current_user_id();
        $session_id = $this->get_session_id();
        
        $table = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_wishlist') : ($wpdb->prefix . 'hng_wishlist');

        $where = ['product_id' => $product_id];
        $formats = ['%d'];

        if ($user_id > 0) {
            $where['user_id'] = $user_id;
            $formats[] = '%d';
        } else {
            $where['session_id'] = $session_id;
            $formats[] = '%s';
        }

        $deleted = $wpdb->delete(
            $table,
            $where,
            $formats
        );
        
        if ($deleted === false) {
            wp_send_json_error(['message' => __('Erro ao remover dos favoritos.', 'hng-commerce')]);
        }
        
        $count = $this->get_wishlist_count();
        
        wp_send_json_success([
            'message' => __('Produto removido dos favoritos.', 'hng-commerce'),
            'count' => $count,
        ]);
    }
    
    /**
     * Obter wishlist
     */
    public function get_wishlist() {
        $user_id = get_current_user_id();
        $session_id = $this->get_session_id();
        
        global $wpdb;

        $table_full = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_wishlist') : ($wpdb->prefix . 'hng_wishlist');
        $table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_wishlist') : ('`' . str_replace('`','', $table_full) . '`');

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_sql sanitized via hng_db_backtick_table()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for wishlist management, user product tracking
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table_sql sanitized via hng_db_backtick_table()
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_sql} 
            WHERE user_id = %d OR session_id = %s
            ORDER BY added_at DESC",
            $user_id,
            $session_id
        ));
        
        $products = [];
        
        foreach ($items as $item) {
            $product = hng_get_product($item->product_id);
            
            if (!$product->get_id()) {
                continue;
            }
            
            $products[] = [
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'price' => $product->get_price(),
                'price_formatted' => hng_price($product->get_price()),
                'image' => get_the_post_thumbnail_url($product->get_id(), 'medium'),
                'url' => get_permalink($product->get_id()),
                'in_stock' => $product->is_in_stock(),
                'added_at' => date_i18n('j/m/Y', strtotime($item->added_at)),
            ];
        }
        
        wp_send_json_success([
            'products' => $products,
            'count' => count($products),
        ]);
    }
    
    /**
     * P gina de wishlist (shortcode)
     */
    public function wishlist_page() {
        ob_start();
        include HNG_COMMERCE_PATH . 'templates/wishlist/wishlist-page.php';
        return ob_get_clean();
    }
    
    /**
     * Verificar se produto est  na wishlist
     */
    public function is_in_wishlist($product_id) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        $session_id = $this->get_session_id();
            $table_full = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_wishlist') : ($wpdb->prefix . 'hng_wishlist');
            $table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_wishlist') : ('`' . str_replace('`','', $table_full) . '`');

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_sql sanitized via hng_db_backtick_table()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for wishlist management, user product tracking
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table_sql sanitized via hng_db_backtick_table()
        $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_sql} 
            WHERE product_id = %d AND (user_id = %d OR session_id = %s)",
            $product_id,
            $user_id,
            $session_id
        ));
        
        return $count > 0;
    }
    
    /**
     * Obter contagem da wishlist
     */
    public function get_wishlist_count() {
        global $wpdb;
        
        $user_id = get_current_user_id();
        $session_id = $this->get_session_id();

        $table_full = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_wishlist') : ($wpdb->prefix . 'hng_wishlist');
        $table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_wishlist') : ('`' . str_replace('`','', $table_full) . '`');

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_sql sanitized via hng_db_backtick_table()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for wishlist management, user product tracking
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table_sql sanitized via hng_db_backtick_table()
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_sql} 
            WHERE user_id = %d OR session_id = %s",
            $user_id,
            $session_id
        ));
        
        return (int) $count;
    }
    
    /**
     * Obter ID da sess o
     */
    private function get_session_id() {
        if (!isset($_COOKIE['hng_session_id'])) {
            $session_id = wp_generate_password(32, false);
            setcookie('hng_session_id', $session_id, time() + (86400 * 30), '/');
        } else {
            $session_id = sanitize_text_field(wp_unslash($_COOKIE['hng_session_id']));
        }
        
        return $session_id;
    }
    
    /**
     * Migrar wishlist ao fazer login
     */
    public function merge_wishlist_on_login($user_login, $user) {
        $session_id = $this->get_session_id();
        
        global $wpdb;

        $table_full = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_wishlist') : ($wpdb->prefix . 'hng_wishlist');
        $table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_wishlist') : ('`' . str_replace('`','', $table_full) . '`');

        // Atualizar itens da sess o para o usu rio
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_sql sanitized via hng_db_backtick_table()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for wishlist management, user product tracking
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table_sql sanitized via hng_db_backtick_table()
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table_sql} 
            SET user_id = %d, session_id = ''
            WHERE session_id = %s",
            $user->ID,
            $session_id
        ));
    }
    
    /**
     * Compartilhar wishlist
     */
    public function generate_share_link() {
        $user_id = get_current_user_id();
        
        if ($user_id === 0) {
            return false;
        }
        
        // Gerar token  nico
        $token = wp_generate_password(20, false);
        update_user_meta($user_id, '_wishlist_share_token', $token);
        
        return add_query_arg(['wishlist' => $token], home_url('/wishlist/'));
    }
    
    /**
     * Obter wishlist compartilhada
     */
    public function get_shared_wishlist($token) {
        // Buscar usu rio pelo token
        $users = get_users([
            'meta_key' => '_wishlist_share_token',
            'meta_value' => $token,
            'number' => 1,
        ]);
        
        if (empty($users)) {
            return false;
        }
        
        $user_id = $users[0]->ID;
        
        global $wpdb;

        $table_full = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_wishlist') : ($wpdb->prefix . 'hng_wishlist');
        $table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_wishlist') : ('`' . str_replace('`','', $table_full) . '`');

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_sql sanitized via hng_db_backtick_table()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for wishlist management, user product tracking
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table_sql sanitized via hng_db_backtick_table()
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT product_id FROM {$table_sql} 
            WHERE user_id = %d
            ORDER BY added_at DESC",
            $user_id
        ));
        
        return $items;
    }
    
    /**
     * Notificar sobre desconto
     */
    public function notify_price_drop($product_id, $old_price, $new_price) {
        global $wpdb;

        $table_full = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_wishlist') : ($wpdb->prefix . 'hng_wishlist');
        $table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_wishlist') : ('`' . str_replace('`','', $table_full) . '`');

        // Buscar usu rios que t m este produto na wishlist
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_sql sanitized via hng_db_backtick_table()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for wishlist management, user product tracking
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table_sql sanitized via hng_db_backtick_table()
        $wishlist_items = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id, session_id FROM {$table_sql} 
            WHERE product_id = %d AND user_id > 0",
            $product_id
        ));
        
        if (empty($wishlist_items)) {
            return;
        }
        
        $product = hng_get_product($product_id);
        $discount_percentage = (($old_price - $new_price) / $old_price) * 100;
        
        foreach ($wishlist_items as $item) {
            $user = get_userdata($item->user_id);
            
            if (!$user) {
                continue;
            }
            
            // Enviar email
            $to = $user->user_email;
            /* translators: %s: site name */
            $subject = sprintf(esc_html__('[%s] Desconto no produto da sua lista de desejos!', 'hng-commerce'), get_bloginfo('name'));
            
            /* translators: %1$s: user name, %2$s: product name, %3$s: old price, %4$s: new price, %5$s: discount percentage, %6$s: product URL */
            $message = sprintf(esc_html__('Ola %1$s, Boas noticias! Um produto da sua lista de desejos esta com desconto: %2$s - De: R$ %3$s - Por: R$ %4$s - Desconto: %5$s%% - Aproveite: %6$s', 'hng-commerce'),
                $user->display_name,
                $product->get_name(),
                number_format($old_price, 2, ',', '.'),
                number_format($new_price, 2, ',', '.'),
                number_format($discount_percentage, 0),
                get_permalink($product_id)
            );
            
            wp_mail($to, $subject, $message);
        }
    }
}
