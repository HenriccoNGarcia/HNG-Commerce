<?php
/**
 * HNG Commerce - Quote Chat System
 * 
 * Sistema de chat/negociação para orçamentos entre cliente e administrador.
 * Permite troca de mensagens, envio de arquivos e termos de serviço.
 *
 * @package HNG_Commerce
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Quote_Chat {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Table name
     */
    private $table_name;
    
    /**
     * Get instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'hng_quote_messages';
        
        // Create table on activation
        add_action('hng_commerce_activated', [$this, 'create_table']);
        
        // AJAX handlers
        add_action('wp_ajax_hng_send_quote_message', [$this, 'ajax_send_message']);
        add_action('wp_ajax_hng_get_quote_messages', [$this, 'ajax_get_messages']);
        add_action('wp_ajax_hng_send_quote_terms', [$this, 'ajax_send_terms']);
        add_action('wp_ajax_hng_accept_quote_terms', [$this, 'ajax_accept_terms']);
        add_action('wp_ajax_hng_mark_messages_read', [$this, 'ajax_mark_read']);
        
        // Metabox for admin
        add_action('add_meta_boxes_hng_order', [$this, 'add_chat_meta_box']);
        
        // Enqueue scripts
        add_action('admin_enqueue_scripts', [$this, 'admin_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'frontend_scripts']);
        
        // Email notifications
        add_action('hng_quote_message_sent', [$this, 'send_notification_email'], 10, 3);
        
        // Check table on init
        add_action('init', [$this, 'maybe_create_table']);
    }
    
    /**
     * Maybe create table
     */
    public function maybe_create_table() {
        global $wpdb;
        $table = $this->table_name;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        
        if (!$table_exists) {
            $this->create_table();
        }
    }
    
    /**
     * Create database table
     */
    public function create_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table = $this->table_name;
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT UNSIGNED NOT NULL,
            sender_id BIGINT UNSIGNED NOT NULL,
            sender_type ENUM('customer', 'admin') NOT NULL DEFAULT 'customer',
            message_type ENUM('text', 'file', 'terms', 'system') NOT NULL DEFAULT 'text',
            message TEXT NOT NULL,
            attachment_url VARCHAR(500) DEFAULT NULL,
            attachment_name VARCHAR(255) DEFAULT NULL,
            terms_content LONGTEXT DEFAULT NULL,
            terms_accepted TINYINT(1) DEFAULT 0,
            terms_accepted_at DATETIME DEFAULT NULL,
            is_read TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY sender_id (sender_id),
            KEY created_at (created_at)
        ) {$charset_collate};";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
    
    /**
     * Add chat meta box to order
     */
    public function add_chat_meta_box() {
        global $post;
        
        if (!$post) {
            return;
        }
        
        $order_id = get_post_meta($post->ID, '_order_id', true);
        if (!$order_id) {
            return;
        }
        
        // Only show for quote orders
        $order = new HNG_Order($order_id);
        if (!$order->get_id()) {
            return;
        }
        
        // Check if has quote product
        $has_quote = false;
        $items = $order->get_items();
        foreach ($items as $item) {
            $product_type = get_post_meta($item['product_id'], '_product_type', true);
            if ($product_type === 'quote') {
                $has_quote = true;
                break;
            }
        }
        
        if (!$has_quote && !in_array($order->get_status(), ['hng-pending-approval', 'hng-awaiting-payment'])) {
            return;
        }
        
        add_meta_box(
            'hng_quote_chat',
            __('💬 Chat com Cliente', 'hng-commerce'),
            [$this, 'render_admin_chat_box'],
            'hng_order',
            'normal',
            'high'
        );
    }
    
    /**
     * Render admin chat box
     */
    public function render_admin_chat_box($post) {
        $order_id = get_post_meta($post->ID, '_order_id', true);
        if (!$order_id) {
            echo '<p>' . esc_html__('Pedido não encontrado.', 'hng-commerce') . '</p>';
            return;
        }
        
        $order = new HNG_Order($order_id);
        $messages = $this->get_messages($order_id);
        $pending_terms = $this->get_pending_terms($order_id);
        
        wp_nonce_field('hng_quote_chat', 'hng_quote_chat_nonce');
        ?>
        <div class="hng-quote-chat" id="hng-quote-chat" data-order-id="<?php echo esc_attr($order_id); ?>">
            <!-- Chat Header -->
            <div class="hng-chat-header">
                <div class="hng-chat-customer">
                    <?php echo get_avatar($order->get_customer_id(), 40); ?>
                    <div class="hng-chat-customer-info">
                        <strong><?php echo esc_html($order->get_customer_name()); ?></strong>
                        <span><?php echo esc_html($order->get_customer_email()); ?></span>
                    </div>
                </div>
                <div class="hng-chat-actions">
                    <button type="button" class="button" id="hng-send-terms-btn">
                        <span class="dashicons dashicons-media-document"></span>
                        <?php esc_html_e('Enviar Termos', 'hng-commerce'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Messages Container -->
            <div class="hng-chat-messages" id="hng-chat-messages">
                <?php if (empty($messages)) : ?>
                    <div class="hng-chat-empty">
                        <span class="dashicons dashicons-format-chat"></span>
                        <p><?php esc_html_e('Nenhuma mensagem ainda. Inicie a conversa!', 'hng-commerce'); ?></p>
                    </div>
                <?php else : ?>
                    <?php foreach ($messages as $msg) : ?>
                        <?php $this->render_message($msg, 'admin'); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Pending Terms Alert -->
            <?php if ($pending_terms) : ?>
            <div class="hng-pending-terms-alert">
                <span class="dashicons dashicons-warning"></span>
                <?php esc_html_e('Aguardando cliente aceitar os termos de serviço.', 'hng-commerce'); ?>
            </div>
            <?php endif; ?>
            
            <!-- Input Area -->
            <div class="hng-chat-input">
                <div class="hng-chat-input-wrapper">
                    <textarea id="hng-chat-message" 
                              placeholder="<?php esc_attr_e('Digite sua mensagem...', 'hng-commerce'); ?>"
                              rows="2"></textarea>
                    <div class="hng-chat-input-actions">
                        <label class="hng-chat-attach">
                            <span class="dashicons dashicons-paperclip"></span>
                            <input type="file" id="hng-chat-file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" style="display:none;">
                        </label>
                        <button type="button" class="button button-primary" id="hng-send-message-btn">
                            <span class="dashicons dashicons-arrow-right-alt"></span>
                            <?php esc_html_e('Enviar', 'hng-commerce'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Terms Modal -->
        <div id="hng-terms-modal" class="hng-modal" style="display:none;">
            <div class="hng-modal-content">
                <div class="hng-modal-header">
                    <h3><?php esc_html_e('Enviar Termos de Serviço', 'hng-commerce'); ?></h3>
                    <button type="button" class="hng-modal-close">&times;</button>
                </div>
                <div class="hng-modal-body">
                    <p><?php esc_html_e('O cliente precisará aceitar estes termos antes de prosseguir com o pagamento.', 'hng-commerce'); ?></p>
                    
                    <div class="hng-form-group">
                        <label for="hng-terms-title"><?php esc_html_e('Título', 'hng-commerce'); ?></label>
                        <input type="text" id="hng-terms-title" value="<?php esc_attr_e('Termos de Serviço', 'hng-commerce'); ?>" class="widefat">
                    </div>
                    
                    <div class="hng-form-group">
                        <label for="hng-terms-content"><?php esc_html_e('Conteúdo dos Termos', 'hng-commerce'); ?></label>
                        <?php 
                        wp_editor('', 'hng-terms-content', [
                            'textarea_name' => 'terms_content',
                            'textarea_rows' => 10,
                            'media_buttons' => false,
                            'teeny' => true,
                            'quicktags' => false
                        ]); 
                        ?>
                    </div>
                </div>
                <div class="hng-modal-footer">
                    <button type="button" class="button" id="hng-cancel-terms"><?php esc_html_e('Cancelar', 'hng-commerce'); ?></button>
                    <button type="button" class="button button-primary" id="hng-confirm-terms">
                        <span class="dashicons dashicons-yes"></span>
                        <?php esc_html_e('Enviar Termos', 'hng-commerce'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <style>
        .hng-quote-chat {
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            background: #fff;
        }
        
        .hng-chat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px;
            background: #f9f9f9;
            border-bottom: 1px solid #ddd;
        }
        
        .hng-chat-customer {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .hng-chat-customer img {
            border-radius: 50%;
        }
        
        .hng-chat-customer-info {
            display: flex;
            flex-direction: column;
        }
        
        .hng-chat-customer-info span {
            font-size: 12px;
            color: #666;
        }
        
        .hng-chat-messages {
            height: 400px;
            overflow-y: auto;
            padding: 20px;
            background: #fafafa;
        }
        
        .hng-chat-empty {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .hng-chat-empty .dashicons {
            font-size: 48px;
            width: 48px;
            height: 48px;
            margin-bottom: 10px;
        }
        
        .hng-chat-message {
            display: flex;
            margin-bottom: 15px;
            gap: 10px;
        }
        
        .hng-chat-message.admin {
            flex-direction: row-reverse;
        }
        
        .hng-chat-message .avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        
        .hng-chat-bubble {
            max-width: 70%;
            padding: 12px 16px;
            border-radius: 16px;
            background: #fff;
            border: 1px solid #e0e0e0;
        }
        
        .hng-chat-message.admin .hng-chat-bubble {
            background: #2271b1;
            color: #fff;
            border-color: #2271b1;
        }
        
        .hng-chat-bubble-header {
            font-size: 11px;
            margin-bottom: 5px;
            opacity: 0.7;
        }
        
        .hng-chat-bubble-text {
            line-height: 1.5;
        }
        
        .hng-chat-bubble-time {
            font-size: 10px;
            margin-top: 5px;
            opacity: 0.6;
            text-align: right;
        }
        
        /* Terms Message */
        .hng-chat-terms {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 12px;
            padding: 20px;
            margin: 15px 0;
        }
        
        .hng-chat-terms-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            font-weight: bold;
        }
        
        .hng-chat-terms-header .dashicons {
            color: #856404;
        }
        
        .hng-chat-terms-content {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            max-height: 200px;
            overflow-y: auto;
            margin-bottom: 15px;
        }
        
        .hng-chat-terms-accepted {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #155724;
            font-weight: bold;
        }
        
        .hng-chat-terms-pending {
            color: #856404;
            font-style: italic;
        }
        
        /* System Message */
        .hng-chat-system {
            text-align: center;
            padding: 10px;
            color: #666;
            font-size: 12px;
            font-style: italic;
        }
        
        /* Pending Terms Alert */
        .hng-pending-terms-alert {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            background: #fff3cd;
            border-top: 1px solid #ffc107;
            color: #856404;
            font-size: 13px;
        }
        
        /* Input Area */
        .hng-chat-input {
            padding: 15px;
            background: #fff;
            border-top: 1px solid #ddd;
        }
        
        .hng-chat-input-wrapper {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        
        .hng-chat-input textarea {
            flex: 1;
            resize: none;
            border-radius: 8px;
            padding: 10px;
        }
        
        .hng-chat-input-actions {
            display: flex;
            gap: 5px;
        }
        
        .hng-chat-attach {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #f0f0f0;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .hng-chat-attach:hover {
            background: #e0e0e0;
        }
        
        /* Modal */
        .hng-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 100000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .hng-modal-content {
            background: #fff;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .hng-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 20px;
            border-bottom: 1px solid #ddd;
        }
        
        .hng-modal-header h3 {
            margin: 0;
        }
        
        .hng-modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
        
        .hng-modal-body {
            padding: 20px;
            overflow-y: auto;
            flex: 1;
        }
        
        .hng-modal-footer {
            padding: 15px 20px;
            border-top: 1px solid #ddd;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .hng-form-group {
            margin-bottom: 15px;
        }
        
        .hng-form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            var orderId = $('#hng-quote-chat').data('order-id');
            var nonce = $('#hng_quote_chat_nonce').val();
            
            // Auto-scroll to bottom
            function scrollToBottom() {
                var container = $('#hng-chat-messages');
                container.scrollTop(container[0].scrollHeight);
            }
            scrollToBottom();
            
            // Send message
            $('#hng-send-message-btn').on('click', function() {
                var message = $('#hng-chat-message').val().trim();
                if (!message) return;
                
                var $btn = $(this);
                $btn.prop('disabled', true);
                
                $.post(ajaxurl, {
                    action: 'hng_send_quote_message',
                    nonce: nonce,
                    order_id: orderId,
                    message: message
                }, function(response) {
                    $btn.prop('disabled', false);
                    if (response.success) {
                        $('#hng-chat-message').val('');
                        $('.hng-chat-empty').remove();
                        $('#hng-chat-messages').append(response.data.html);
                        scrollToBottom();
                    } else {
                        alert(response.data.message || 'Erro ao enviar mensagem');
                    }
                });
            });
            
            // Enter to send
            $('#hng-chat-message').on('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    $('#hng-send-message-btn').click();
                }
            });
            
            // File upload
            $('#hng-chat-file').on('change', function() {
                var file = this.files[0];
                if (!file) return;
                
                var formData = new FormData();
                formData.append('action', 'hng_send_quote_message');
                formData.append('nonce', nonce);
                formData.append('order_id', orderId);
                formData.append('message', 'Arquivo: ' + file.name);
                formData.append('file', file);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            $('.hng-chat-empty').remove();
                            $('#hng-chat-messages').append(response.data.html);
                            scrollToBottom();
                        }
                    }
                });
                
                this.value = '';
            });
            
            // Terms modal
            $('#hng-send-terms-btn').on('click', function() {
                $('#hng-terms-modal').show();
            });
            
            $('.hng-modal-close, #hng-cancel-terms').on('click', function() {
                $('#hng-terms-modal').hide();
            });
            
            // Send terms
            $('#hng-confirm-terms').on('click', function() {
                var title = $('#hng-terms-title').val();
                var content = typeof tinyMCE !== 'undefined' && tinyMCE.get('hng-terms-content') 
                    ? tinyMCE.get('hng-terms-content').getContent()
                    : $('#hng-terms-content').val();
                
                if (!content.trim()) {
                    alert('<?php esc_html_e('Por favor, insira o conteúdo dos termos.', 'hng-commerce'); ?>');
                    return;
                }
                
                var $btn = $(this);
                $btn.prop('disabled', true);
                
                $.post(ajaxurl, {
                    action: 'hng_send_quote_terms',
                    nonce: nonce,
                    order_id: orderId,
                    title: title,
                    content: content
                }, function(response) {
                    $btn.prop('disabled', false);
                    if (response.success) {
                        $('#hng-terms-modal').hide();
                        $('.hng-chat-empty').remove();
                        $('#hng-chat-messages').append(response.data.html);
                        scrollToBottom();
                    } else {
                        alert(response.data.message || 'Erro ao enviar termos');
                    }
                });
            });
            
            // Poll for new messages every 15 seconds
            setInterval(function() {
                var lastId = $('#hng-chat-messages .hng-chat-message:last').data('id') || 0;
                
                $.post(ajaxurl, {
                    action: 'hng_get_quote_messages',
                    nonce: nonce,
                    order_id: orderId,
                    after_id: lastId
                }, function(response) {
                    if (response.success && response.data.html) {
                        $('#hng-chat-messages').append(response.data.html);
                        scrollToBottom();
                    }
                });
            }, 15000);
        });
        </script>
        <?php
    }
    
    /**
     * Render single message
     */
    private function render_message($msg, $viewer = 'admin') {
        $is_own = ($viewer === 'admin' && $msg->sender_type === 'admin') ||
                  ($viewer === 'customer' && $msg->sender_type === 'customer');
        
        $sender = get_user_by('id', $msg->sender_id);
        $avatar = get_avatar($msg->sender_id, 36);
        $name = $sender ? $sender->display_name : __('Usuário', 'hng-commerce');
        $time = date_i18n('d/m H:i', strtotime($msg->created_at));
        
        if ($msg->message_type === 'terms') {
            $this->render_terms_message($msg, $viewer);
            return;
        }
        
        if ($msg->message_type === 'system') {
            echo '<div class="hng-chat-system">' . esc_html($msg->message) . '</div>';
            return;
        }
        
        $class = $is_own ? 'admin' : 'customer';
        ?>
        <div class="hng-chat-message <?php echo esc_attr($class); ?>" data-id="<?php echo esc_attr($msg->id); ?>">
            <?php echo wp_kses_post($avatar); ?>
            <div class="hng-chat-bubble">
                <div class="hng-chat-bubble-header"><?php echo esc_html($name); ?></div>
                <div class="hng-chat-bubble-text">
                    <?php echo nl2br(esc_html($msg->message)); ?>
                    
                    <?php if ($msg->attachment_url) : ?>
                        <div class="hng-chat-attachment">
                            <a href="<?php echo esc_url($msg->attachment_url); ?>" target="_blank">
                                <span class="dashicons dashicons-media-default"></span>
                                <?php echo esc_html($msg->attachment_name); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="hng-chat-bubble-time"><?php echo esc_html($time); ?></div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render terms message
     */
    private function render_terms_message($msg, $viewer) {
        $time = date_i18n('d/m H:i', strtotime($msg->created_at));
        ?>
        <div class="hng-chat-terms" data-id="<?php echo esc_attr($msg->id); ?>">
            <div class="hng-chat-terms-header">
                <span class="dashicons dashicons-media-document"></span>
                <?php echo esc_html($msg->message); ?>
            </div>
            
            <div class="hng-chat-terms-content">
                <?php echo wp_kses_post($msg->terms_content); ?>
            </div>
            
            <?php if ($msg->terms_accepted) : ?>
                <div class="hng-chat-terms-accepted">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php 
                    printf(
                        /* translators: %s: date and time when terms were accepted */
                        esc_html__('Termos aceitos em %s', 'hng-commerce'),
                        esc_html(date_i18n('d/m/Y H:i', strtotime($msg->terms_accepted_at)))
                    ); 
                    ?>
                </div>
            <?php else : ?>
                <?php if ($viewer === 'customer') : ?>
                    <button type="button" class="button button-primary hng-accept-terms-btn" data-terms-id="<?php echo esc_attr($msg->id); ?>">
                        <span class="dashicons dashicons-yes"></span>
                        <?php esc_html_e('Aceitar Termos', 'hng-commerce'); ?>
                    </button>
                <?php else : ?>
                    <div class="hng-chat-terms-pending">
                        <?php esc_html_e('Aguardando aceite do cliente...', 'hng-commerce'); ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <div class="hng-chat-bubble-time"><?php echo esc_html($time); ?></div>
        </div>
        <?php
    }
    
    /**
     * Get messages for order
     */
    public function get_messages($order_id, $after_id = 0) {
        global $wpdb;
        
        $table = $this->table_name;
        
        if ($after_id > 0) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} WHERE order_id = %d AND id > %d ORDER BY created_at ASC",
                $order_id,
                $after_id
            ));
        }
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE order_id = %d ORDER BY created_at ASC",
            $order_id
        ));
    }
    
    /**
     * Get pending terms for order
     */
    public function get_pending_terms($order_id) {
        global $wpdb;
        
        $table = $this->table_name;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE order_id = %d AND message_type = 'terms' AND terms_accepted = 0 ORDER BY created_at DESC LIMIT 1",
            $order_id
        ));
    }
    
    /**
     * Check if order has accepted terms
     */
    public function order_has_accepted_terms($order_id) {
        $pending = $this->get_pending_terms($order_id);
        
        if (!$pending) {
            // No terms required or all accepted
            return true;
        }
        
        return false;
    }
    
    /**
     * AJAX: Send message
     */
    public function ajax_send_message() {
        check_ajax_referer('hng_quote_chat', 'nonce');
        
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
        
        if (!$order_id || !$message) {
            wp_send_json_error(['message' => __('Dados inválidos.', 'hng-commerce')]);
        }
        
        // Determine sender type
        $sender_type = current_user_can('manage_options') ? 'admin' : 'customer';
        
        // Handle file upload
        $attachment_url = '';
        $attachment_name = '';
        
        if (!empty($_FILES['file'])) {
            $uploaded = $this->handle_file_upload($_FILES['file'], $order_id);
            if (!is_wp_error($uploaded)) {
                $attachment_url = $uploaded['url'];
                $attachment_name = $uploaded['name'];
            }
        }
        
        // Insert message
        global $wpdb;
        $table = $this->table_name;
        
        $wpdb->insert($table, [
            'order_id' => $order_id,
            'sender_id' => get_current_user_id(),
            'sender_type' => $sender_type,
            'message_type' => 'text',
            'message' => $message,
            'attachment_url' => $attachment_url,
            'attachment_name' => $attachment_name
        ], ['%d', '%d', '%s', '%s', '%s', '%s', '%s']);
        
        $msg_id = $wpdb->insert_id;
        
        // Get message for rendering
        $msg = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $msg_id
        ));
        
        // Trigger notification
        do_action('hng_quote_message_sent', $msg, $order_id, $sender_type);
        
        ob_start();
        $this->render_message($msg, $sender_type);
        $html = ob_get_clean();
        
        wp_send_json_success(['html' => $html]);
    }
    
    /**
     * AJAX: Get messages
     */
    public function ajax_get_messages() {
        check_ajax_referer('hng_quote_chat', 'nonce');
        
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $after_id = isset($_POST['after_id']) ? intval($_POST['after_id']) : 0;
        
        if (!$order_id) {
            wp_send_json_error(['message' => __('Pedido inválido.', 'hng-commerce')]);
        }
        
        $messages = $this->get_messages($order_id, $after_id);
        
        if (empty($messages)) {
            wp_send_json_success(['html' => '']);
        }
        
        $viewer = current_user_can('manage_options') ? 'admin' : 'customer';
        
        ob_start();
        foreach ($messages as $msg) {
            $this->render_message($msg, $viewer);
        }
        $html = ob_get_clean();
        
        wp_send_json_success(['html' => $html]);
    }
    
    /**
     * AJAX: Send terms
     */
    public function ajax_send_terms() {
        check_ajax_referer('hng_quote_chat', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Sem permissão.', 'hng-commerce')]);
        }
        
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : __('Termos de Serviço', 'hng-commerce');
        $content = isset($_POST['content']) ? wp_kses_post(wp_unslash($_POST['content'])) : '';
        
        if (!$order_id || !$content) {
            wp_send_json_error(['message' => __('Dados inválidos.', 'hng-commerce')]);
        }
        
        // Insert terms message
        global $wpdb;
        $table = $this->table_name;
        
        $wpdb->insert($table, [
            'order_id' => $order_id,
            'sender_id' => get_current_user_id(),
            'sender_type' => 'admin',
            'message_type' => 'terms',
            'message' => $title,
            'terms_content' => $content,
            'terms_accepted' => 0
        ], ['%d', '%d', '%s', '%s', '%s', '%s', '%d']);
        
        $msg_id = $wpdb->insert_id;
        
        // Get message for rendering
        $msg = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $msg_id
        ));
        
        // Trigger notification
        do_action('hng_quote_terms_sent', $msg, $order_id);
        
        ob_start();
        $this->render_terms_message($msg, 'admin');
        $html = ob_get_clean();
        
        wp_send_json_success(['html' => $html]);
    }
    
    /**
     * AJAX: Accept terms
     */
    public function ajax_accept_terms() {
        check_ajax_referer('hng_quote_chat', 'nonce');
        
        $terms_id = isset($_POST['terms_id']) ? intval($_POST['terms_id']) : 0;
        
        if (!$terms_id) {
            wp_send_json_error(['message' => __('Termos não encontrados.', 'hng-commerce')]);
        }
        
        global $wpdb;
        $table = $this->table_name;
        
        // Update terms
        $wpdb->update($table, [
            'terms_accepted' => 1,
            'terms_accepted_at' => current_time('mysql')
        ], ['id' => $terms_id], ['%d', '%s'], ['%d']);
        
        // Add system message
        $terms = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $terms_id
        ));
        
        if ($terms) {
            $wpdb->insert($table, [
                'order_id' => $terms->order_id,
                'sender_id' => get_current_user_id(),
                'sender_type' => 'customer',
                'message_type' => 'system',
                'message' => __('✓ Cliente aceitou os termos de serviço', 'hng-commerce')
            ], ['%d', '%d', '%s', '%s', '%s']);
            
            // Trigger action
            do_action('hng_quote_terms_accepted', $terms->order_id, $terms_id);
        }
        
        wp_send_json_success(['message' => __('Termos aceitos com sucesso!', 'hng-commerce')]);
    }
    
    /**
     * AJAX: Mark messages as read
     */
    public function ajax_mark_read() {
        check_ajax_referer('hng_quote_chat', 'nonce');
        
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        
        if (!$order_id) {
            wp_send_json_error();
        }
        
        global $wpdb;
        $table = $this->table_name;
        
        $reader_type = current_user_can('manage_options') ? 'customer' : 'admin';
        
        $wpdb->update($table, [
            'is_read' => 1
        ], [
            'order_id' => $order_id,
            'sender_type' => $reader_type
        ], ['%d'], ['%d', '%s']);
        
        wp_send_json_success();
    }
    
    /**
     * Handle file upload
     */
    private function handle_file_upload($file, $order_id) {
        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        
        $allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed)) {
            return new WP_Error('invalid_file', __('Tipo de arquivo não permitido.', 'hng-commerce'));
        }
        
        // Max 5MB
        if ($file['size'] > 5 * 1024 * 1024) {
            return new WP_Error('file_too_large', __('Arquivo muito grande. Máximo 5MB.', 'hng-commerce'));
        }
        
        $upload = wp_handle_upload($file, ['test_form' => false]);
        
        if (isset($upload['error'])) {
            return new WP_Error('upload_error', $upload['error']);
        }
        
        return [
            'url' => $upload['url'],
            'name' => $file['name']
        ];
    }
    
    /**
     * Send email notification
     */
    public function send_notification_email($msg, $order_id, $sender_type) {
        $order = new HNG_Order($order_id);
        
        if ($sender_type === 'admin') {
            // Notify customer
            $to = $order->get_customer_email();
            /* translators: %s: site name */
            $subject = sprintf(__('[%s] Nova mensagem sobre seu orçamento', 'hng-commerce'), get_bloginfo('name'));
        } else {
            // Notify admin
            $to = get_option('admin_email');
            /* translators: %1$s: site name, %2$s: order number */
            $subject = sprintf(__('[%1$s] Nova mensagem no orçamento #%2$s', 'hng-commerce'), get_bloginfo('name'), $order->get_order_number());
        }
        
        $message = sprintf(
            /* translators: %1$s: order number, %2$s: message content, %3$s: link to view chat */
            __("Nova mensagem no orçamento #%1\$s:\n\n%2\$s\n\nAcesse para responder: %3\$s", 'hng-commerce'),
            $order->get_order_number(),
            wp_strip_all_tags($msg->message),
            $sender_type === 'admin' 
                ? hng_get_myaccount_url() . '?account-page=quotes&order=' . $order_id
                : admin_url('post.php?post=' . $order->get_post_id() . '&action=edit')
        );
        
        wp_mail($to, $subject, $message);
    }
    
    /**
     * Admin scripts
     */
    public function admin_scripts($hook) {
        if ($hook !== 'post.php') {
            return;
        }
        
        global $post;
        if (!$post || $post->post_type !== 'hng_order') {
            return;
        }
        
        // Scripts are inline in the meta box for now
    }
    
    /**
     * Frontend scripts
     */
    public function frontend_scripts() {
        if (!is_page() || !is_user_logged_in()) {
            return;
        }
        
        // Load scripts on my-account quotes page
        // Scripts will be loaded inline with the template
    }
    
    /**
     * Get unread message count
     * 
     * @param int    $order_id    Order ID
     * @param string $for_type    Who is checking - 'admin' or 'customer'
     * @return int
     */
    public function get_unread_count($order_id, $for_type = 'customer') {
        global $wpdb;
        
        // Unread messages are those sent by the OTHER party
        $sender_type = $for_type === 'customer' ? 'admin' : 'customer';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} 
            WHERE order_id = %d 
            AND sender_type = %s 
            AND is_read = 0",
            $order_id,
            $sender_type
        ));
        
        return (int) $count;
    }
    
    /**
     * Render a message for customer view
     * 
     * @param object $msg Message object
     */
    public function render_customer_message($msg) {
        $current_user_id = get_current_user_id();
        $is_own = $msg->sender_type === 'customer' && (int) $msg->sender_id === $current_user_id;
        $avatar = get_avatar_url($msg->sender_id, ['size' => 32]);
        $time = date_i18n('H:i', strtotime($msg->created_at));
        
        // Terms message
        if ($msg->message_type === 'terms') {
            ?>
            <div class="hng-chat-terms-card" data-id="<?php echo esc_attr($msg->id); ?>">
                <h4>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                    </svg>
                    <?php echo esc_html($msg->message); ?>
                </h4>
                
                <div class="hng-terms-content">
                    <?php echo wp_kses_post($msg->terms_content); ?>
                </div>
                
                <?php if ($msg->terms_accepted) : ?>
                    <div class="hng-terms-accepted-badge">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                            <polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                        <?php 
                        printf(
                            /* translators: %s: date and time */
                            esc_html__('Aceito em %s', 'hng-commerce'),
                            esc_html(date_i18n('d/m/Y H:i', strtotime($msg->terms_accepted_at)))
                        ); 
                        ?>
                    </div>
                <?php else : ?>
                    <button type="button" class="hng-accept-terms-btn" data-terms-id="<?php echo esc_attr($msg->id); ?>">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        <?php esc_html_e('Aceitar Termos', 'hng-commerce'); ?>
                    </button>
                <?php endif; ?>
            </div>
            <?php
            return;
        }
        
        // System message
        if ($msg->message_type === 'system') {
            ?>
            <div class="hng-chat-system" data-id="<?php echo esc_attr($msg->id); ?>">
                <?php echo esc_html($msg->message); ?>
            </div>
            <?php
            return;
        }
        
        // Regular message
        $class = $is_own ? 'own' : '';
        ?>
        <div class="hng-chat-msg <?php echo esc_attr($class); ?>" data-id="<?php echo esc_attr($msg->id); ?>">
            <img src="<?php echo esc_url($avatar); ?>" alt="" class="hng-chat-avatar">
            <div class="hng-chat-bubble">
                <div class="hng-chat-bubble-text">
                    <?php echo nl2br(esc_html($msg->message)); ?>
                    
                    <?php if ($msg->attachment_url) : ?>
                        <div class="hng-chat-attachment">
                            <a href="<?php echo esc_url($msg->attachment_url); ?>" target="_blank" style="color: var(--neon-green); text-decoration: none;">
                                📎 <?php echo esc_html($msg->attachment_name); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="hng-chat-bubble-time"><?php echo esc_html($time); ?></div>
            </div>
        </div>
        <?php
    }
}

/**
 * Get Quote Chat instance
 */
function hng_quote_chat() {
    return HNG_Quote_Chat::instance();
}

// Initialize
HNG_Quote_Chat::instance();
