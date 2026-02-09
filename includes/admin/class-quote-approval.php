<?php
/**
 * Admin - Aprovação de Orçamentos
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Quote_Approval_Admin {
    
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
        // Adicionar meta box na tela de pedido
        add_action('add_meta_boxes_hng_order', [$this, 'add_approval_meta_box']);
        
        // AJAX handlers - DESABILITADO: conflita com handler de cliente em class-hng-ajax.php
        // Esta função é para admin aprovar com preço, mas o cliente usa a mesma ação
        // add_action('wp_ajax_hng_approve_quote', [$this, 'ajax_approve_quote']);
        add_action('wp_ajax_hng_admin_approve_quote', [$this, 'ajax_approve_quote']); // Renamed
        add_action('wp_ajax_hng_reject_quote', [$this, 'ajax_reject_quote']);
        
        // Ações pós-aprovação
        add_action('hng_quote_approved', [$this, 'generate_payment_link'], 10, 2);
        add_action('hng_quote_approved', [$this, 'send_approval_email'], 20, 2);
        
        // Notificações admin
        add_action('hng_order_status_changed', [$this, 'notify_admin_new_quote'], 10, 3);
    }
    
    /**
     * Adicionar meta box de aprovação
     */
    public function add_approval_meta_box() {
        global $post;
        
        if (!$post) {
            return;
        }
        
        $order = new HNG_Order($post->ID);
        
        if ($order->get_status() === 'hng-pending-approval') {
            add_meta_box(
                'hng_quote_approval',
                __('📋 Aprovação de Orçamento', 'hng-commerce'),
                [$this, 'render_approval_meta_box'],
                'hng_order',
                'side',
                'high'
            );
        }
        
        if (in_array($order->get_status(), ['hng-awaiting-payment', 'hng-processing', 'hng-completed'])) {
            add_meta_box(
                'hng_quote_details',
                __('📋 Detalhes do Orçamento Aprovado', 'hng-commerce'),
                [$this, 'render_quote_details_meta_box'],
                'hng_order',
                'side',
                'default'
            );
        }
    }
    
    /**
     * Renderizar meta box de aprovação
     */
    public function render_approval_meta_box($post) {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $order_id = get_post_meta($post->ID, '_order_id', true);
        $order = new HNG_Order($order_id);
        
        $approved_price = get_post_meta($post->ID, '_quote_approved_price', true);
        $approved_shipping = get_post_meta($post->ID, '_quote_approved_shipping', true);
        $approval_notes = get_post_meta($post->ID, '_quote_approval_notes', true);
        
        wp_nonce_field('hng_approve_quote', 'hng_quote_approval_nonce');
        ?>
        <div class="hng-quote-approval-box">
            <div class="approval-status">
                <span class="dashicons dashicons-clock" style="color: #f0ad4e;"></span>
                <strong><?php esc_html_e('Aguardando Aprovação', 'hng-commerce'); ?></strong>
            </div>
            
            <div class="order-summary" style="margin: 15px 0; padding: 10px; background: #f9f9f9; border-radius: 4px;">
                <p style="margin: 5px 0;">
                    <strong><?php esc_html_e('Subtotal Original:', 'hng-commerce'); ?></strong><br>
                    <span style="font-size: 16px; color: #666;"><?php echo esc_html(hng_price($order->get_subtotal())); ?></span>
                </p>
            </div>
            
            <div class="approval-form">
                <p>
                    <label>
                        <strong><?php esc_html_e('Preço Final Aprovado', 'hng-commerce'); ?></strong>
                        <input type="number" 
                               id="quote_approved_price" 
                               name="quote_approved_price"
                               value="<?php echo esc_attr($approved_price ?: $order->get_subtotal()); ?>"
                               step="0.01"
                               min="0"
                               class="widefat"
                               style="margin-top: 5px;">
                    </label>
                    <span class="description"><?php esc_html_e('Valor que o cliente pagará pelo produto', 'hng-commerce'); ?></span>
                </p>
                
                <p>
                    <label>
                        <strong><?php esc_html_e('Valor do Frete', 'hng-commerce'); ?></strong>
                        <input type="number" 
                               id="quote_approved_shipping" 
                               name="quote_approved_shipping"
                               value="<?php echo esc_attr($approved_shipping ?: '0'); ?>"
                               step="0.01"
                               min="0"
                               class="widefat"
                               style="margin-top: 5px;">
                    </label>
                    <span class="description"><?php esc_html_e('Deixe 0 para retirada no local', 'hng-commerce'); ?></span>
                </p>
                
                <p>
                    <label>
                        <strong><?php esc_html_e('Observações da Aprovação', 'hng-commerce'); ?></strong>
                        <textarea id="quote_approval_notes" 
                                  name="quote_approval_notes"
                                  class="widefat" 
                                  rows="3"
                                  style="margin-top: 5px;"><?php echo esc_textarea($approval_notes); ?></textarea>
                    </label>
                    <span class="description"><?php esc_html_e('Informações adicionais para o cliente', 'hng-commerce'); ?></span>
                </p>
                
                <div class="total-preview" style="margin: 15px 0; padding: 12px; background: #f0f6fc; border-left: 4px solid #2271b1; border-radius: 4px;">
                    <p style="margin: 0 0 5px 0;">
                        <strong><?php esc_html_e('Total que o Cliente Pagará:', 'hng-commerce'); ?></strong>
                    </p>
                    <p style="margin: 0; font-size: 20px; font-weight: bold; color: #2271b1;" id="quote_total_preview">
                        <?php echo esc_html(hng_price($order->get_subtotal())); ?>
                    </p>
                </div>
            </div>
            
            <div class="approval-actions" style="display: flex; gap: 10px; margin-top: 15px;">
                <button type="button" 
                        class="button button-primary button-large" 
                        id="approve-quote-btn"
                        style="flex: 1;">
                    <span class="dashicons dashicons-yes" style="margin-top: 3px;"></span>
                    <?php esc_html_e('Aprovar', 'hng-commerce'); ?>
                </button>
                
                <button type="button" 
                        class="button button-link-delete button-large" 
                        id="reject-quote-btn"
                        style="flex: 1;">
                    <span class="dashicons dashicons-no" style="margin-top: 3px;"></span>
                    <?php esc_html_e('Rejeitar', 'hng-commerce'); ?>
                </button>
            </div>
            
            <div id="quote-approval-message" style="margin-top: 15px;"></div>
        </div>
        
        <style>
            .approval-status {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 10px;
                background: #fff3cd;
                border-left: 4px solid #f0ad4e;
                border-radius: 4px;
                margin-bottom: 15px;
            }
            .approval-status .dashicons {
                font-size: 20px;
                width: 20px;
                height: 20px;
            }
            .hng-quote-approval-box input[type="number"],
            .hng-quote-approval-box textarea {
                font-size: 14px;
            }
        </style>
        <?php
        // Quote approval handlers movidos para assets/js/admin.js - usar data attributes
        // Exemplo: data-post-id, data-order-id, data-nonce nos elementos
        ?>
        <script type="application/json" id="hng-quote-data">
        <?php echo wp_json_encode([
            'post_id' => (int) $post->ID,
            'order_id' => (int) $order_id,
            'nonce' => wp_create_nonce('hng_approve_quote'),
            'i18n' => [
                'confirm' => __('Confirma a aprovação deste orçamento? Um link de pagamento será gerado e enviado ao cliente.', 'hng-commerce'),
                'approving' => __('Aprovando...', 'hng-commerce')
            ]
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>
        </script>
        <?php
        // JS handler em admin.js: QuoteApproval.init()
    }
    
    /**
     * Renderizar meta box de detalhes do orçamento aprovado
     */
    public function render_quote_details_meta_box($post) {
        $approved_price = get_post_meta($post->ID, '_quote_approved_price', true);
        $approved_shipping = get_post_meta($post->ID, '_quote_approved_shipping', true);
        $approval_notes = get_post_meta($post->ID, '_quote_approval_notes', true);
        $payment_link = get_post_meta($post->ID, '_quote_payment_link', true);
        
        if (!$approved_price) {
            echo '<p>' . esc_html__('Este pedido não possui dados de orçamento aprovado.', 'hng-commerce') . '</p>';
            return;
        }
        
        echo '<div class="hng-quote-details">';
        
        echo '<p>';
        echo '<strong>' . esc_html_e('Preço Aprovado:', 'hng-commerce') . '</strong><br>';
        echo '<span style="font-size: 16px;">' . esc_html(hng_price($approved_price)) . '</span>';
        echo '</p>';
        
        echo '<p>';
        echo '<strong>' . esc_html_e('Frete:', 'hng-commerce') . '</strong><br>';
        echo '<span style="font-size: 16px;">' . esc_html(hng_price($approved_shipping)) . '</span>';
        echo '</p>';
        
        echo '<p style="padding: 10px; background: #f0f6fc; border-radius: 4px;">';
        echo '<strong>' . esc_html_e('Total:', 'hng-commerce') . '</strong><br>';
        echo '<span style="font-size: 18px; font-weight: bold; color: #2271b1;">';
        echo esc_html(hng_price($approved_price + $approved_shipping));
        echo '</span>';
        echo '</p>';
        
        if ($approval_notes) {
            echo '<p>';
            echo '<strong>' . esc_html_e('Observações:', 'hng-commerce') . '</strong><br>';
            echo '<em>' . esc_html($approval_notes) . '</em>';
            echo '</p>';
        }
        
        if ($payment_link) {
            echo '<p>';
            echo '<strong>' . esc_html_e('Link de Pagamento:', 'hng-commerce') . '</strong><br>';
            echo '<a href="' . esc_url($payment_link) . '" target="_blank" class="button button-small">';
            esc_html_e('Ver Link', 'hng-commerce');
            echo '</a>';
            echo '</p>';
        }
        
        echo '</div>';
    }
    
    /**
     * AJAX: Aprovar orçamento
     */
    public function ajax_approve_quote() {
        check_ajax_referer('hng_approve_quote', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permissão negada.', 'hng-commerce')]);
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $approved_price = isset($_POST['approved_price']) ? floatval(sanitize_text_field(wp_unslash($_POST['approved_price']))) : 0;
        $approved_shipping = isset($_POST['approved_shipping']) ? floatval(sanitize_text_field(wp_unslash($_POST['approved_shipping']))) : 0;
        $approval_notes = isset($_POST['approval_notes']) ? sanitize_textarea_field(wp_unslash($_POST['approval_notes'])) : '';
        
        if (!$post_id || !$order_id || $approved_price <= 0) {
            wp_send_json_error(['message' => __('Dados inválidos.', 'hng-commerce')]);
        }
        
        // Salvar dados da aprovação
        update_post_meta($post_id, '_quote_approved_price', $approved_price);
        update_post_meta($post_id, '_quote_approved_shipping', $approved_shipping);
        update_post_meta($post_id, '_quote_approval_notes', $approval_notes);
        update_post_meta($post_id, '_quote_approved_at', current_time('mysql'));
        update_post_meta($post_id, '_quote_approved_by', get_current_user_id());
        
        // Atualizar status do pedido
        $order = new HNG_Order($order_id);
        $order->update_status('hng-awaiting-payment', __('Orçamento aprovado. Aguardando pagamento do cliente.', 'hng-commerce'));
        
        // Atualizar total do pedido
        global $wpdb;
        $table = hng_db_full_table_name('hng_orders');
        $wpdb->update(
            $table,
            [
                'total' => floatval($approved_price) + floatval($approved_shipping),
                'shipping_total' => floatval($approved_shipping),
            ],
            ['id' => intval($order_id)],
            ['%f', '%f'],
            ['%d']
        );
        
        // Disparar ação para gerar link de pagamento e enviar email
        do_action('hng_quote_approved', $order_id, $post_id);
        
        wp_send_json_success([
            'message' => __('✔ Orçamento aprovado com sucesso! Link de pagamento gerado.', 'hng-commerce')
        ]);
    }
    
    /**
     * AJAX: Rejeitar orçamento
     */
    public function ajax_reject_quote() {
        check_ajax_referer('hng_approve_quote', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permissão negada.', 'hng-commerce')]);
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $rejection_reason = isset($_POST['rejection_reason']) ? sanitize_textarea_field(wp_unslash($_POST['rejection_reason'])) : '';
        
        if (!$post_id || !$order_id) {
            wp_send_json_error(['message' => __('Dados inválidos.', 'hng-commerce')]);
        }
        
        // Salvar motivo da rejeição
        update_post_meta($post_id, '_quote_rejection_reason', $rejection_reason);
        update_post_meta($post_id, '_quote_rejected_at', current_time('mysql'));
        
        // Atualizar status para cancelado
        $order = new HNG_Order($order_id);
        /* translators: %1$s: rejection reason */
        $order->update_status('hng-cancelled', sprintf(__('Orçamento rejeitado. Motivo: %1$s', 'hng-commerce'), $rejection_reason));
        
        // Enviar email de rejeição
        do_action('hng_quote_rejected', $order_id, $post_id, $rejection_reason);
        
        wp_send_json_success([
            'message' => __('Orçamento rejeitado. Cliente será notificado.', 'hng-commerce')
        ]);
    }
    
    /**
     * Gerar link de pagamento após aprovação
     */
    public function generate_payment_link($order_id, $post_id) {
        $approved_price = get_post_meta($post_id, '_quote_approved_price', true);
        $approved_shipping = get_post_meta($post_id, '_quote_approved_shipping', true);
        $total = $approved_price + $approved_shipping;
        
        $order = new HNG_Order($order_id);
        
        // Gerar link de pagamento único
        $payment_token = wp_generate_password(32, false);
        update_post_meta($post_id, '_quote_payment_token', $payment_token);
        
        $payment_link = add_query_arg([
            'quote_payment' => $payment_token,
            'order_id' => $order_id,
        ], home_url('/checkout/quote-payment/'));
        
        update_post_meta($post_id, '_quote_payment_link', $payment_link);
        
        /* translators: %s: payment link URL */
        HNG_Order::add_order_note($order_id, sprintf(
            /* translators: %s: payment link URL */
            __('Link de pagamento gerado: %s', 'hng-commerce'),
            $payment_link
        ));
    }
    
    /**
     * Enviar email de aprovação
     */
    public function send_approval_email($order_id, $post_id) {
        $order = new HNG_Order($order_id);
        $approved_price = get_post_meta($post_id, '_quote_approved_price', true);
        $approved_shipping = get_post_meta($post_id, '_quote_approved_shipping', true);
        $approval_notes = get_post_meta($post_id, '_quote_approval_notes', true);
        $payment_link = get_post_meta($post_id, '_quote_payment_link', true);
        
        $to = $order->get_customer_email();
        /* translators: %1$s: site name */
        $subject = sprintf(__('[%1$s] Seu orçamento foi aprovado!', 'hng-commerce'), get_bloginfo('name'));
        
        ob_start();
        ?>
        <h2>Orçamento Aprovado! 🎉</h2>
        
        <p>Olá <?php echo esc_html($order->get_customer_name()); ?>,</p>
        
        <p>Seu orçamento <strong>#<?php echo esc_html($order->get_order_number()); ?></strong> foi aprovado!</p>
        
        <h3>Detalhes do Orçamento:</h3>
        <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
            <tr>
                <td style="padding: 10px; border: 1px solid #ddd;"><strong>Valor do Produto:</strong></td>
                <td style="padding: 10px; border: 1px solid #ddd;"><?php echo esc_html(hng_price($approved_price)); ?></td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #ddd;"><strong>Frete:</strong></td>
                <td style="padding: 10px; border: 1px solid #ddd;"><?php echo esc_html(hng_price($approved_shipping)); ?></td>
            </tr>
            <tr style="background: #f0f6fc;">
                <td style="padding: 10px; border: 1px solid #ddd;"><strong>TOTAL:</strong></td>
                <td style="padding: 10px; border: 1px solid #ddd; font-size: 18px; font-weight: bold;">
                    <?php echo esc_html(hng_price($approved_price + $approved_shipping)); ?>
                </td>
            </tr>
        </table>
        
        <?php if ($approval_notes): ?>
        <p><strong>Observações:</strong><br><?php echo nl2br(esc_html($approval_notes)); ?></p>
        <?php endif; ?>
        
        <p style="margin: 30px 0;">
            <a href="<?php echo esc_url($payment_link); ?>" 
               style="display: inline-block; padding: 15px 30px; background: #2271b1; color: white; text-decoration: none; border-radius: 4px; font-weight: bold;">
                Pagar Agora
            </a>
        </p>
        
        <p><small>Este link é exclusivo e válido para este orçamento.</small></p>
        <?php
        $message = ob_get_clean();
        
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        
        wp_mail($to, $subject, $message, $headers);
    }
    
    /**
     * Notificar admin sobre novo orçamento
     */
    public function notify_admin_new_quote($order_id, $old_status, $new_status) {
        if ($new_status !== 'hng-pending-approval') {
            return;
        }
        
        $order = new HNG_Order($order_id);
        $admin_email = get_option('admin_email');
        
        /* translators: %1$s: site name */
        $subject = sprintf(__('[%1$s] Novo orçamento aguardando aprovação', 'hng-commerce'), get_bloginfo('name'));
        
        /* translators: 1: newline, 2: order number, 3: customer name, 4: admin URL */
        $message = sprintf(
            /* translators: 1: newline, 2: order number, 3: customer name, 4: admin URL */
            __('Um novo pedido de orçamento foi recebido e aguarda sua aprovação.%1$s%1$sPedido: #%2$s%1$sCliente: %3$s%1$s%1$sAcesse o painel para aprovar: %4$s', 'hng-commerce'),
            "\n",
            $order->get_order_number(),
            $order->get_customer_name(),
            admin_url('post.php?post=' . $order->get_post_id() . '&action=edit')
        );
        
        wp_mail($admin_email, $subject, $message);
    }
}

// Inicializar apenas se habilitado
if (get_option('hng_enable_quote_products', 'no') === 'yes') {
    HNG_Quote_Approval_Admin::instance();
}
