<?php
/**
 * Sistema de Avaliaá¯Â¿Â½á¯Â¿Â½es de Produtos
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// DB helper
require_once HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php';

class HNG_Reviews {
    
    /**
     * Instá¯Â¿Â½ncia á¯Â¿Â½nica
     */
    private static $instance = null;
    
    /**
     * Obter instá¯Â¿Â½ncia
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
        // Criar tabela no activate
        add_action('hng_commerce_activate', [$this, 'create_table']);
        
        // AJAX handlers
        add_action('wp_ajax_hng_submit_review', [$this, 'submit_review']);
        add_action('wp_ajax_nopriv_hng_submit_review', [$this, 'submit_review']);
        
        add_action('wp_ajax_hng_load_reviews', [$this, 'load_reviews']);
        add_action('wp_ajax_nopriv_hng_load_reviews', [$this, 'load_reviews']);
        
        // Admin
        add_action('wp_ajax_hng_moderate_review', [$this, 'moderate_review']);
        add_action('wp_ajax_hng_delete_review', [$this, 'delete_review']);
        
        // SEO - Rich Snippets
        add_action('wp_head', [$this, 'add_rich_snippets']);
    }
    
    /**
     * Criar tabela de avaliaá¯Â¿Â½á¯Â¿Â½es
     */
    public function create_table() {
        global $wpdb;
        
        $table_name = hng_db_full_table_name('hng_reviews');
        $charset_collate = $wpdb->get_charset_collate();
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via hng_db_full_table_name(), dbDelta requires literal SQL
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Database schema installation
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names sanitized via hng_db_full_table_name()
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            user_id bigint(20) DEFAULT 0,
            author_name varchar(255) NOT NULL,
            author_email varchar(255) NOT NULL,
            rating int(1) NOT NULL,
            title varchar(255) DEFAULT '',
            comment text NOT NULL,
            status varchar(20) DEFAULT 'pending',
            verified_purchase tinyint(1) DEFAULT 0,
            helpful_count int(11) DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY user_id (user_id),
            KEY status (status),
            KEY rating (rating)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
    
    /**
     * Submeter avaliaá¯Â¿Â½á¯Â¿Â½o
     */
    public function submit_review() {
        // Verificar nonce
        $nonce = wp_unslash($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'hng_submit_review')) {
            wp_send_json_error(['message' => __('Erro de seguraná¯Â¿Â½a.', 'hng-commerce')]);
        }
        
        $post = wp_unslash($_POST);
        $product_id = absint($post['product_id'] ?? 0);
        $rating = absint($post['rating'] ?? 0);
        $title = sanitize_text_field($post['title'] ?? '');
        $comment = sanitize_textarea_field($post['comment'] ?? '');
        
        // Validaá¯Â¿Â½á¯Â¿Â½es
        if (empty($product_id)) {
            wp_send_json_error(['message' => __('Produto invá¯Â¿Â½lido.', 'hng-commerce')]);
        }
        
        if ($rating < 1 || $rating > 5) {
            wp_send_json_error(['message' => __('Avaliaá¯Â¿Â½á¯Â¿Â½o deve ser entre 1 e 5 estrelas.', 'hng-commerce')]);
        }
        
        if (strlen($comment) < 10) {
            wp_send_json_error(['message' => __('Comentá¯Â¿Â½rio muito curto. Má¯Â¿Â½nimo 10 caracteres.', 'hng-commerce')]);
        }
        
        // Obter dados do usuá¯Â¿Â½rio
        $user_id = get_current_user_id();
        
        if ($user_id > 0) {
            $user = get_userdata($user_id);
            $author_name = sanitize_text_field($user->display_name);
            $author_email = sanitize_email($user->user_email);
        } else {
            $author_name = sanitize_text_field($post['author_name'] ?? '');
            $author_email = sanitize_email($post['author_email'] ?? '');
            
            if (empty($author_name) || empty($author_email)) {
                wp_send_json_error(['message' => __('Nome e email são obrigatórios.', 'hng-commerce')]);
            }
        }
        
        // Verificar se já¯Â¿Â½ avaliou
        if ($this->user_has_reviewed($product_id, $user_id, $author_email)) {
            wp_send_json_error(['message' => __('Vocá¯Â¿Â½ já¯Â¿Â½ avaliou este produto.', 'hng-commerce')]);
        }
        
        // Verificar se á¯Â¿Â½ compra verificada
        $verified_purchase = $this->is_verified_purchase($product_id, $user_id, $author_email);
        
        // Status: aprovaá¯Â¿Â½á¯Â¿Â½o automá¯Â¿Â½tica para compras verificadas
        $auto_approve = get_option('hng_reviews_auto_approve', 'yes');
        $status = ($auto_approve === 'yes' && $verified_purchase) ? 'approved' : 'pending';
        
        // Inserir avaliaá¯Â¿Â½á¯Â¿Â½o
        global $wpdb;

        $inserted = $wpdb->insert(
            hng_db_full_table_name('hng_reviews'),
            [
                'product_id' => $product_id,
                'user_id' => $user_id,
                'author_name' => $author_name,
                'author_email' => $author_email,
                'rating' => $rating,
                'title' => $title,
                'comment' => $comment,
                'status' => $status,
                'verified_purchase' => $verified_purchase ? 1 : 0,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s']
        );
        
        if (!$inserted) {
            wp_send_json_error(['message' => __('Erro ao salvar avaliaá¯Â¿Â½á¯Â¿Â½o.', 'hng-commerce')]);
        }
        
        // Atualizar má¯Â¿Â½dia do produto
        $this->update_product_rating($product_id);
        
        // Notificar admin se for pendente
        if ($status === 'pending') {
            $this->notify_admin_new_review($wpdb->insert_id);
        }
        
        wp_send_json_success([
            'message' => $status === 'approved' 
                ? __('Avaliaá¯Â¿Â½á¯Â¿Â½o publicada com sucesso!', 'hng-commerce')
                : __('Avaliaá¯Â¿Â½á¯Â¿Â½o recebida! Será¯Â¿Â½ publicada apá¯Â¿Â½s moderaá¯Â¿Â½á¯Â¿Â½o.', 'hng-commerce'),
            'status' => $status,
        ]);
    }
    
    /**
     * Carregar avaliaá¯Â¿Â½á¯Â¿Â½es
     */
    public function load_reviews() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Endpoint AJAX pá¡Âºblico de leitura; dados sanitizados com absint()
        $post = wp_unslash($_POST);
        $product_id = absint($post['product_id'] ?? 0);
        $page = absint($post['page'] ?? 1);
        $per_page = 10;
        $offset = ($page - 1) * $per_page;
        
        if (empty($product_id)) {
            wp_send_json_error(['message' => __('Produto invá¯Â¿Â½lido.', 'hng-commerce')]);
        }
        
        global $wpdb;
        
        // Buscar avaliaá¯Â¿Â½á¯Â¿Â½es aprovadas
            $reviews_table_full = hng_db_full_table_name('hng_reviews');
            $reviews_table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_reviews') : ('`' . str_replace('`','', $reviews_table_full) . '`');
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $reviews_table_sql sanitized via hng_db_backtick_table()
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for reviews management, product ratings
            // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $reviews_table_sql sanitized via hng_db_backtick_table()
            $reviews = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$reviews_table_sql} 
                WHERE product_id = %d AND status = 'approved'
                ORDER BY created_at DESC
                LIMIT %d OFFSET %d",
                $product_id,
                $per_page,
                $offset
            ));
        
        // Total de avaliaá¯Â¿Â½á¯Â¿Â½es
        $reviews_table_full = hng_db_full_table_name('hng_reviews');
        $reviews_table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_reviews') : ('`' . str_replace('`','', $reviews_table_full) . '`');
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $reviews_table_sql sanitized via hng_db_backtick_table()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for reviews management, product ratings
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $reviews_table_sql sanitized via hng_db_backtick_table()
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$reviews_table_sql} 
            WHERE product_id = %d AND status = 'approved'",
            $product_id
        ));
        
        // Formatar reviews
        $formatted_reviews = array_map([$this, 'format_review'], $reviews);
        
        wp_send_json_success([
            'reviews' => $formatted_reviews,
            'total' => (int) $total,
            'has_more' => ($offset + $per_page) < $total,
            'current_page' => $page,
        ]);
    }
    
    /**
     * Moderar avaliaá¯Â¿Â½á¯Â¿Â½o (Admin)
     */
    public function moderate_review() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Sem permissá¯Â¿Â½o.', 'hng-commerce')]);
        }
        
        $post = wp_unslash($_POST);
        check_ajax_referer('hng_moderate_review', 'nonce');
        
        $review_id = absint($post['review_id'] ?? 0);
        $new_status = sanitize_key($post['status'] ?? '');
        
        if (empty($review_id) || !in_array($new_status, ['approved', 'pending', 'spam'], true)) {
            wp_send_json_error(['message' => __('Dados inválidos.', 'hng-commerce')]);
        }
        
        global $wpdb;

        $updated = $wpdb->update(
            hng_db_full_table_name('hng_reviews'),
            ['status' => $new_status],
            ['id' => $review_id],
            ['%s'],
            ['%d']
        );
        
        if ($updated === false) {
            wp_send_json_error(['message' => __('Erro ao atualizar status.', 'hng-commerce')]);
        }
        
        // Atualizar má¯Â¿Â½dia do produto
        $reviews_table_full = hng_db_full_table_name('hng_reviews');
        $reviews_table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_reviews') : ('`' . str_replace('`','', $reviews_table_full) . '`');
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $reviews_table_sql sanitized via hng_db_backtick_table()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for reviews management, product ratings
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $reviews_table_sql sanitized via hng_db_backtick_table()
        $review = $wpdb->get_row($wpdb->prepare(
            "SELECT product_id FROM {$reviews_table_sql} WHERE id = %d",
            $review_id
        ));
        
        if ($review) {
            $this->update_product_rating($review->product_id);
        }
        
        wp_send_json_success(['message' => __('Status atualizado.', 'hng-commerce')]);
    }
    
    /**
     * Deletar avaliaá¯Â¿Â½á¯Â¿Â½o (Admin)
     */
    public function delete_review() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Sem permissá¯Â¿Â½o.', 'hng-commerce')]);
        }
        
        $post = wp_unslash($_POST);
        check_ajax_referer('hng_delete_review', 'nonce');
        
        $review_id = absint($post['review_id'] ?? 0);
        
        if (empty($review_id)) {
            wp_send_json_error(['message' => __('ID invá¯Â¿Â½lido.', 'hng-commerce')]);
        }
        
        global $wpdb;

        // Obter produto antes de deletar
        $reviews_table_full = hng_db_full_table_name('hng_reviews');
        $reviews_table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_reviews') : ('`' . str_replace('`','', $reviews_table_full) . '`');
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $reviews_table_sql sanitized via hng_db_backtick_table()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for reviews management, product ratings
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $reviews_table_sql sanitized via hng_db_backtick_table()
        $review = $wpdb->get_row($wpdb->prepare(
            "SELECT product_id FROM {$reviews_table_sql} WHERE id = %d",
            $review_id
        ));

        // Nome de tabela sem backticks para operaá§áµes de escrita
        $reviews_table = $reviews_table_full;

        $deleted = $wpdb->delete(
            $reviews_table,
            ['id' => $review_id],
            ['%d']
        );
        
        if ($deleted === false) {
            wp_send_json_error(['message' => __('Erro ao deletar.', 'hng-commerce')]);
        }
        
        // Atualizar má¯Â¿Â½dia do produto
        if ($review) {
            $this->update_product_rating($review->product_id);
        }
        
        wp_send_json_success(['message' => __('Avaliaá¯Â¿Â½á¯Â¿Â½o deletada.', 'hng-commerce')]);
    }
    
    /**
     * Verificar se usuá¯Â¿Â½rio já¯Â¿Â½ avaliou
     */
    private function user_has_reviewed($product_id, $user_id, $email) {
        global $wpdb;
        
        $reviews_table_full = hng_db_full_table_name('hng_reviews');
        $reviews_table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_reviews') : ('`' . str_replace('`','', $reviews_table_full) . '`');
        if ($user_id > 0) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $reviews_table_sql sanitized via hng_db_backtick_table()
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for reviews management, product ratings
            // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $reviews_table_sql sanitized via hng_db_backtick_table()
            $count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$reviews_table_sql} 
                    WHERE product_id = %d AND user_id = %d",
                    $product_id,
                    $user_id
                ));
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $reviews_table_sql sanitized via hng_db_backtick_table()
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for reviews management, product ratings
            // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $reviews_table_sql sanitized via hng_db_backtick_table()
            $count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$reviews_table_sql} 
                    WHERE product_id = %d AND author_email = %s",
                    $product_id,
                    $email
                ));
        }
        
        return $count > 0;
    }
    
    /**
     * Verificar se á¯Â¿Â½ compra verificada
     */
    private function is_verified_purchase($product_id, $user_id, $email) {
        global $wpdb;
        $orders_table_full = hng_db_full_table_name('hng_orders');
        $orders_table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_orders') : ('`' . str_replace('`','', $orders_table_full) . '`');
        $items_table_full = hng_db_full_table_name('hng_order_items');
        $items_table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_order_items') : ('`' . str_replace('`','', $items_table_full) . '`');

        if ($user_id > 0) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $orders_table_sql/$items_table_sql sanitized via hng_db_backtick_table()
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for reviews management, product ratings
            // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $orders_table_sql/$items_table_sql sanitized via hng_db_backtick_table()
            $query = $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$orders_table_sql} o
                    INNER JOIN {$items_table_sql} oi ON o.id = oi.order_id
                    WHERE o.customer_id = %d 
                    AND oi.product_id = %d
                    AND o.status IN ('hng-completed', 'hng-processing')",
                    $user_id,
                    $product_id
                );
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $orders_table_sql/$items_table_sql sanitized via hng_db_backtick_table()
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for reviews management, product ratings
            // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $orders_table_sql/$items_table_sql sanitized via hng_db_backtick_table()
            $query = $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$orders_table_sql} o
                    INNER JOIN {$items_table_sql} oi ON o.id = oi.order_id
                    WHERE o.customer_email = %s 
                    AND oi.product_id = %d
                    AND o.status IN ('hng-completed', 'hng-processing')",
                    $email,
                    $product_id
                );
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $count = $wpdb->get_var($query);
        
        return $count > 0;
    }
    
    /**
     * Atualizar má¯Â¿Â½dia de avaliaá¯Â¿Â½á¯Â¿Â½o do produto
     */
    private function update_product_rating($product_id) {
        global $wpdb;
        
        $reviews_table = hng_db_full_table_name('hng_reviews');
        $reviews_table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_reviews') : ('`' . str_replace('`','', $reviews_table) . '`');
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $reviews_table_sql sanitized via hng_db_backtick_table()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for reviews management, product ratings
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $reviews_table_sql sanitized via hng_db_backtick_table()
        $stats = $wpdb->get_row($wpdb->prepare(
                "SELECT 
                    COUNT(*) as total,
                    AVG(rating) as average,
                    SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as star_5,
                    SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as star_4,
                    SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as star_3,
                    SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as star_2,
                    SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as star_1
                FROM {$reviews_table_sql} 
                WHERE product_id = %d AND status = 'approved'",
            $product_id
        ));
        
        update_post_meta($product_id, '_review_count', (int) $stats->total);
        update_post_meta($product_id, '_average_rating', round($stats->average, 2));
        update_post_meta($product_id, '_rating_breakdown', [
            '5' => (int) $stats->star_5,
            '4' => (int) $stats->star_4,
            '3' => (int) $stats->star_3,
            '2' => (int) $stats->star_2,
            '1' => (int) $stats->star_1,
        ]);
    }
    
    /**
     * Formatar avaliaá¯Â¿Â½á¯Â¿Â½o para exibiá¯Â¿Â½á¯Â¿Â½o
     */
    private function format_review($review) {
        return [
            'id' => $review->id,
            'author_name' => esc_html($review->author_name),
            'rating' => $review->rating,
            'title' => esc_html($review->title),
            'comment' => nl2br(esc_html($review->comment)),
            'verified_purchase' => (bool) $review->verified_purchase,
            'helpful_count' => $review->helpful_count,
            'date' => date_i18n('j \d\e F \d\e Y', strtotime($review->created_at)),
            'date_relative' => human_time_diff(strtotime($review->created_at), current_time('timestamp')) . ' atrá¯Â¿Â½s',
        ];
    }
    
    /**
     * Notificar admin sobre nova avaliaá¯Â¿Â½á¯Â¿Â½o pendente
     */
    private function notify_admin_new_review($review_id) {
        global $wpdb;
        
        $reviews_table = hng_db_full_table_name('hng_reviews');
        $reviews_table_sql = '`' . str_replace('`','', $reviews_table) . '`';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $reviews_table_sql sanitized via backtick escaping
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for reviews management, product ratings
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $reviews_table_sql sanitized via backtick escaping
        $review = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$reviews_table_sql} WHERE id = %d",
            $review_id
        ));
        
        if (!$review) {
            return;
        }
        
        $product = get_post($review->product_id);
        
        
        if (!$product || !isset($product->post_title)) {
            return;
        }
        
        $to = get_option('admin_email');
        /* translators: %1$s: site name */
        $subject = sprintf(esc_html__('[%1$s] Nova avaliaá§á¡o pendente', 'hng-commerce'), get_bloginfo('name'));
        
        /* translators: %1$s: product name, %2$s: author name, %3$d: rating, %4$s: comment, %5$s: moderation URL */
        $message = sprintf(esc_html__('Nova avaliaá§á¡o aguardando moderaá§á¡o:

Produto: %1$s
Autor: %2$s
Avaliaá§á¡o: %3$d estrelas
Comentá¡Â¡rio: %4$s

Moderar: %5$s', 'hng-commerce'),
            $product->post_title,
            $review->author_name,
            $review->rating,
            $review->comment,
            admin_url('admin.php?page=hng-reviews')
        );
        
        wp_mail($to, $subject, $message);
    }
    
    /**
     * Obter estatá¯Â¿Â½sticas de avaliaá¯Â¿Â½á¯Â¿Â½es do produto
     */
    public function get_product_stats($product_id) {
        $count = (int) get_post_meta($product_id, '_review_count', true);
        $average = (float) get_post_meta($product_id, '_average_rating', true);
        $breakdown = get_post_meta($product_id, '_rating_breakdown', true) ?: [
            '5' => 0, '4' => 0, '3' => 0, '2' => 0, '1' => 0
        ];
        
        return [
            'count' => $count,
            'average' => $average,
            'breakdown' => $breakdown,
        ];
    }
    
    /**
     * Adicionar Rich Snippets (Schema.org)
     */
    public function add_rich_snippets() {
        if (!is_singular('hng_product')) {
            return;
        }
        
        global $post;
        
        $stats = $this->get_product_stats($post->ID);
        
        if ($stats['count'] === 0) {
            return;
        }
        
        $product = hng_get_product($post->ID);
        
        $schema = [
            '@context' => 'https://schema.org/',
            '@type' => 'Product',
            'name' => get_the_title(),
            'description' => wp_strip_all_tags(get_the_excerpt()),
            'image' => get_the_post_thumbnail_url($post->ID, 'full'),
            'offers' => [
                '@type' => 'Offer',
                'price' => $product->get_price(),
                'priceCurrency' => 'BRL',
                'availability' => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            ],
            'aggregateRating' => [
                '@type' => 'AggregateRating',
                'ratingValue' => $stats['average'],
                'reviewCount' => $stats['count'],
                'bestRating' => 5,
                'worstRating' => 1,
            ],
        ];
        
        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
    }
}
