<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Template: My Account - Single Quote View
 * 
 * Exibe um orçamento específico com chat de negociação
 * 
 * @package HNG_Commerce
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// $order já está definido pelo template pai
$order_id = $order->get_id();
$status = $order->get_status();
$status_label = $order->get_status_label();
$post_id = $order->get_post_id();

// Get quote details
$approved_price = get_post_meta($post_id, '_quote_approved_price', true);
$approved_shipping = get_post_meta($post_id, '_quote_approved_shipping', true);
$approval_notes = get_post_meta($post_id, '_quote_approval_notes', true);
$payment_link = get_post_meta($post_id, '_quote_payment_link', true);

// Get chat messages
$messages = hng_quote_chat()->get_messages($order_id);
$pending_terms = hng_quote_chat()->get_pending_terms($order_id);

// Mark messages as read
hng_quote_chat()->mark_messages_read($order_id, 'admin');

wp_nonce_field('hng_quote_chat', 'hng_quote_chat_nonce');
?>

<div class="hng-quote-single">
    <!-- Back Button -->
    <a href="<?php echo esc_url(add_query_arg('account-page', 'quotes', hng_get_myaccount_url())); ?>" class="hng-back-link">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="19" y1="12" x2="5" y2="12"/>
            <polyline points="12 19 5 12 12 5"/>
        </svg>
        <?php esc_html_e('Voltar aos orçamentos', 'hng-commerce'); ?>
    </a>
    
    <div class="hng-quote-layout">
        <!-- Main Content -->
        <div class="hng-quote-main">
            <!-- Header -->
            <div class="hng-quote-header glass-card">
                <div class="hng-quote-info">
                    <h2>
                        <?php esc_html_e('Orçamento', 'hng-commerce'); ?> 
                        #<?php echo esc_html($order->get_order_number()); ?>
                    </h2>
                    <span class="hng-quote-date">
                        <?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($order->get_date_created()))); ?>
                    </span>
                </div>
                <span class="hng-quote-status hng-status-<?php echo esc_attr($status); ?>">
                    <?php echo esc_html($status_label); ?>
                </span>
            </div>
            
            <!-- Items -->
            <div class="hng-quote-items glass-card">
                <h3>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                        <line x1="3" y1="6" x2="21" y2="6"/>
                        <path d="M16 10a4 4 0 0 1-8 0"/>
                    </svg>
                    <?php esc_html_e('Itens do Orçamento', 'hng-commerce'); ?>
                </h3>
                
                <div class="hng-items-list">
                    <?php 
                    $items = $order->get_items();
                    foreach ($items as $item) : 
                        $thumb = get_the_post_thumbnail_url($item['product_id'], 'thumbnail');
                        
                        // Get quote custom fields answers
                        $quote_data = $item['quote_data'] ?? [];
                    ?>
                        <div class="hng-item">
                            <?php if ($thumb) : ?>
                                <img src="<?php echo esc_url($thumb); ?>" alt="">
                            <?php else : ?>
                                <div class="hng-item-placeholder">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                        <circle cx="8.5" cy="8.5" r="1.5"/>
                                        <polyline points="21 15 16 10 5 21"/>
                                    </svg>
                                </div>
                            <?php endif; ?>
                            
                            <div class="hng-item-details">
                                <h4><?php echo esc_html($item['name']); ?></h4>
                                
                                <?php if (!empty($quote_data)) : ?>
                                    <div class="hng-quote-fields">
                                        <?php foreach ($quote_data as $field) : ?>
                                            <div class="hng-quote-field">
                                                <span class="field-label"><?php echo esc_html($field['label']); ?>:</span>
                                                <span class="field-value"><?php echo esc_html($field['value']); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="hng-item-qty">
                                x<?php echo esc_html($item['quantity']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Pricing (if approved) -->
            <?php if ($approved_price) : ?>
                <div class="hng-quote-pricing glass-card">
                    <h3>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="1" x2="12" y2="23"/>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                        </svg>
                        <?php esc_html_e('Valores Aprovados', 'hng-commerce'); ?>
                    </h3>
                    
                    <div class="hng-pricing-rows">
                        <div class="hng-pricing-row">
                            <span><?php esc_html_e('Produto/Serviço:', 'hng-commerce'); ?></span>
                            <strong><?php echo esc_html(hng_price($approved_price)); ?></strong>
                        </div>
                        
                        <?php if ($approved_shipping) : ?>
                            <div class="hng-pricing-row">
                                <span><?php esc_html_e('Frete:', 'hng-commerce'); ?></span>
                                <strong><?php echo esc_html(hng_price($approved_shipping)); ?></strong>
                            </div>
                        <?php endif; ?>
                        
                        <div class="hng-pricing-row total">
                            <span><?php esc_html_e('Total:', 'hng-commerce'); ?></span>
                            <strong><?php echo esc_html(hng_price($approved_price + $approved_shipping)); ?></strong>
                        </div>
                    </div>
                    
                    <?php if ($approval_notes) : ?>
                        <div class="hng-approval-notes">
                            <strong><?php esc_html_e('Observações:', 'hng-commerce'); ?></strong>
                            <p><?php echo nl2br(esc_html($approval_notes)); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($status === 'hng-awaiting-payment') : ?>
                        <?php if ($pending_terms) : ?>
                            <div class="hng-payment-blocked">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                                    <line x1="12" y1="9" x2="12" y2="13"/>
                                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                                </svg>
                                <div>
                                    <strong><?php esc_html_e('Pagamento Bloqueado', 'hng-commerce'); ?></strong>
                                    <p><?php esc_html_e('Você precisa aceitar os termos de serviço na conversa abaixo antes de prosseguir com o pagamento.', 'hng-commerce'); ?></p>
                                </div>
                            </div>
                        <?php elseif ($payment_link) : ?>
                            <a href="<?php echo esc_url($payment_link); ?>" class="hng-pay-btn">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                                    <line x1="1" y1="10" x2="23" y2="10"/>
                                </svg>
                                <?php esc_html_e('Pagar Agora', 'hng-commerce'); ?>
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Chat Sidebar -->
        <div class="hng-quote-chat-wrapper glass-card">
            <div class="hng-chat-header">
                <h3>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                    <?php esc_html_e('Conversa', 'hng-commerce'); ?>
                </h3>
                <button type="button" class="hng-sound-toggle" id="hng-chat-sound-toggle" title="<?php esc_attr_e('Ativar/Desativar som', 'hng-commerce'); ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>
                        <path class="sound-on" d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"/>
                    </svg>
                </button>
            </div>
            
            <div class="hng-chat-messages" id="hng-chat-messages" data-order-id="<?php echo esc_attr($order_id); ?>">
                <?php if (empty($messages)) : ?>
                    <div class="hng-chat-empty">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                        </svg>
                        <p><?php esc_html_e('Aguardando resposta do vendedor...', 'hng-commerce'); ?></p>
                    </div>
                <?php else : ?>
                    <?php foreach ($messages as $msg) : 
                        hng_quote_chat()->render_customer_message($msg);
                    endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="hng-chat-input">
                <textarea id="hng-chat-message" 
                          placeholder="<?php esc_attr_e('Digite sua mensagem...', 'hng-commerce'); ?>"
                          rows="2"></textarea>
                <div class="hng-chat-actions">
                    <label class="hng-chat-attach">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
                        </svg>
                        <input type="file" id="hng-chat-file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" style="display:none;">
                    </label>
                    <button type="button" id="hng-send-message-btn" class="hng-send-btn">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="22" y1="2" x2="11" y2="13"/>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.hng-quote-single {
    padding: 0;
}

.hng-back-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    color: rgba(255, 255, 255, 0.6);
    text-decoration: none;
    font-size: 0.9rem;
    transition: color 0.3s ease;
}

.hng-back-link:hover {
    color: var(--neon-green, #2AFFA3);
}

.hng-quote-layout {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 1.5rem;
}

.hng-quote-main {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.glass-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 1rem;
    padding: 1.5rem;
}

/* Header */
.hng-quote-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.hng-quote-info h2 {
    margin: 0 0 0.25rem;
    font-size: 1.3rem;
    color: white;
}

.hng-quote-date {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.5);
}

.hng-quote-status {
    display: inline-block;
    padding: 0.5rem 1rem;
    border-radius: 2rem;
    font-size: 0.8rem;
    font-weight: 600;
}

/* Items */
.hng-quote-items h3,
.hng-quote-pricing h3 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0 0 1rem;
    font-size: 1rem;
    color: white;
}

.hng-quote-items h3 svg,
.hng-quote-pricing h3 svg {
    color: var(--neon-green, #2AFFA3);
}

.hng-items-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.hng-item {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.02);
    border-radius: 0.75rem;
}

.hng-item img,
.hng-item-placeholder {
    width: 60px;
    height: 60px;
    border-radius: 0.5rem;
    object-fit: cover;
    flex-shrink: 0;
}

.hng-item-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.05);
    color: rgba(255, 255, 255, 0.3);
}

.hng-item-details {
    flex: 1;
}

.hng-item-details h4 {
    margin: 0 0 0.5rem;
    color: white;
    font-size: 1rem;
}

.hng-quote-fields {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.hng-quote-field {
    font-size: 0.85rem;
}

.hng-quote-field .field-label {
    color: rgba(255, 255, 255, 0.5);
}

.hng-quote-field .field-value {
    color: rgba(255, 255, 255, 0.8);
}

.hng-item-qty {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.9rem;
}

/* Pricing */
.hng-pricing-rows {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.hng-pricing-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.hng-pricing-row span {
    color: rgba(255, 255, 255, 0.6);
}

.hng-pricing-row strong {
    color: white;
}

.hng-pricing-row.total {
    padding-top: 0.75rem;
    margin-top: 0.5rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.hng-pricing-row.total strong {
    font-size: 1.25rem;
    color: var(--neon-green, #2AFFA3);
}

.hng-approval-notes {
    margin-top: 1rem;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 0.5rem;
}

.hng-approval-notes strong {
    display: block;
    margin-bottom: 0.5rem;
    color: white;
    font-size: 0.9rem;
}

.hng-approval-notes p {
    margin: 0;
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.9rem;
}

.hng-payment-blocked {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
    padding: 1rem;
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.3);
    border-radius: 0.75rem;
}

.hng-payment-blocked svg {
    flex-shrink: 0;
    color: #fbbf24;
}

.hng-payment-blocked strong {
    display: block;
    color: #fbbf24;
    margin-bottom: 0.25rem;
}

.hng-payment-blocked p {
    margin: 0;
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.9rem;
}

.hng-pay-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    width: 100%;
    margin-top: 1.5rem;
    padding: 1rem;
    background: var(--neon-green, #2AFFA3);
    border: none;
    border-radius: 0.75rem;
    color: var(--brand-black, #050505);
    font-size: 1rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s ease;
}

.hng-pay-btn:hover {
    background: #25e892;
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(42, 255, 163, 0.3);
}

/* Chat */
.hng-quote-chat-wrapper {
    display: flex;
    flex-direction: column;
    height: fit-content;
    max-height: calc(100vh - 200px);
    position: sticky;
    top: 100px;
}

.hng-chat-header {
    padding-bottom: 1rem;
    margin-bottom: 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.hng-chat-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.hng-chat-header h3 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0;
    font-size: 1rem;
    color: white;
}

.hng-chat-header h3 svg {
    color: var(--neon-green, #2AFFA3);
}

.hng-sound-toggle {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    color: rgba(255, 255, 255, 0.5);
    cursor: pointer;
    transition: all 0.3s ease;
}

.hng-sound-toggle:hover {
    background: rgba(255, 255, 255, 0.1);
    color: white;
}

.hng-sound-toggle.sound-active {
    background: rgba(42, 255, 163, 0.15);
    border-color: var(--neon-green, #2AFFA3);
    color: var(--neon-green, #2AFFA3);
}

.hng-sound-toggle:not(.sound-active) .sound-on {
    display: none;
}

.hng-chat-messages {
    flex: 1;
    overflow-y: auto;
    min-height: 300px;
    max-height: 400px;
    padding-right: 0.5rem;
}

.hng-chat-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 200px;
    text-align: center;
    color: rgba(255, 255, 255, 0.4);
}

.hng-chat-empty svg {
    margin-bottom: 0.75rem;
}

.hng-chat-empty p {
    margin: 0;
    font-size: 0.9rem;
}

/* Chat Messages */
.hng-chat-msg {
    display: flex;
    margin-bottom: 1rem;
    gap: 0.75rem;
}

.hng-chat-msg.own {
    flex-direction: row-reverse;
}

.hng-chat-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    flex-shrink: 0;
}

.hng-chat-bubble {
    max-width: 80%;
    padding: 0.75rem 1rem;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 1rem;
    border-top-left-radius: 0.25rem;
}

.hng-chat-msg.own .hng-chat-bubble {
    background: rgba(42, 255, 163, 0.15);
    border-top-left-radius: 1rem;
    border-top-right-radius: 0.25rem;
}

.hng-chat-bubble-text {
    color: white;
    font-size: 0.9rem;
    line-height: 1.5;
}

.hng-chat-bubble-time {
    margin-top: 0.25rem;
    font-size: 0.7rem;
    color: rgba(255, 255, 255, 0.4);
    text-align: right;
}

/* Terms in Chat */
.hng-chat-terms-card {
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.3);
    border-radius: 0.75rem;
    padding: 1rem;
    margin: 1rem 0;
}

.hng-chat-terms-card h4 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0 0 0.75rem;
    color: #fbbf24;
    font-size: 0.9rem;
}

.hng-terms-content {
    max-height: 150px;
    overflow-y: auto;
    padding: 0.75rem;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 0.5rem;
    margin-bottom: 1rem;
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.85rem;
    line-height: 1.6;
}

.hng-accept-terms-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    width: 100%;
    padding: 0.75rem;
    background: var(--neon-green, #2AFFA3);
    border: none;
    border-radius: 0.5rem;
    color: var(--brand-black, #050505);
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.hng-accept-terms-btn:hover {
    background: #25e892;
}

.hng-terms-accepted-badge {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #4ade80;
    font-size: 0.85rem;
}

/* Chat Input */
.hng-chat-input {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.hng-chat-input textarea {
    flex: 1;
    padding: 0.75rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 0.75rem;
    color: white;
    font-size: 0.9rem;
    resize: none;
}

.hng-chat-input textarea::placeholder {
    color: rgba(255, 255, 255, 0.3);
}

.hng-chat-input textarea:focus {
    outline: none;
    border-color: var(--neon-green, #2AFFA3);
}

.hng-chat-actions {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.hng-chat-attach {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 50%;
    color: rgba(255, 255, 255, 0.5);
    cursor: pointer;
    transition: all 0.3s ease;
}

.hng-chat-attach:hover {
    background: rgba(255, 255, 255, 0.1);
    color: white;
}

.hng-send-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    background: var(--neon-green, #2AFFA3);
    border: none;
    border-radius: 50%;
    color: var(--brand-black, #050505);
    cursor: pointer;
    transition: all 0.3s ease;
}

.hng-send-btn:hover {
    transform: scale(1.1);
}

/* Responsive */
@media (max-width: 991px) {
    .hng-quote-layout {
        grid-template-columns: 1fr;
    }
    
    .hng-quote-chat-wrapper {
        position: relative;
        top: 0;
        max-height: none;
    }
}
</style>

<script>
(function() {
    var orderId = document.getElementById('hng-chat-messages').dataset.orderId;
    var nonce = document.getElementById('hng_quote_chat_nonce').value;
    var container = document.getElementById('hng-chat-messages');
    var originalTitle = document.title;
    var soundEnabled = true;
    var unreadCount = 0;
    
    // Restore sound preference - SEMPRE INICIAR COM SOM ATIVADO se nunca foi configurado
    try {
        var savedPref = localStorage.getItem('hng_quote_chat_sound_enabled');
        if (savedPref === null) {
            // Nunca foi configurado - iniciar ativado
            soundEnabled = true;
            localStorage.setItem('hng_quote_chat_sound_enabled', '1');
            document.getElementById('hng-chat-sound-toggle').classList.add('sound-active');
        } else {
            // Restaurar preferência salva
            soundEnabled = savedPref === '1';
            if (soundEnabled) {
                document.getElementById('hng-chat-sound-toggle').classList.add('sound-active');
            }
        }
    } catch (e) {
        // Fallback: ativar som mesmo em caso de erro
        soundEnabled = true;
        document.getElementById('hng-chat-sound-toggle').classList.add('sound-active');
    }
    
    // Sound toggle button
    document.getElementById('hng-chat-sound-toggle').addEventListener('click', function() {
        soundEnabled = !soundEnabled;
        
        if (soundEnabled) {
            this.classList.add('sound-active');
            this.title = 'Som ativado';
            playNotificationSound(); // Test sound
        } else {
            this.classList.remove('sound-active');
            this.title = 'Som desativado';
        }
        
        // Save preference
        try {
            localStorage.setItem('hng_quote_chat_sound_enabled', soundEnabled ? '1' : '0');
        } catch (e) {}
    });
    
    // Play notification sound
    function playNotificationSound() {
        if (!soundEnabled) return;
        
        try {
            var audioContext = new (window.AudioContext || window.webkitAudioContext)();
            var osc1 = audioContext.createOscillator();
            var osc2 = audioContext.createOscillator();
            var gainNode = audioContext.createGain();
            
            osc1.connect(gainNode);
            osc2.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            osc1.frequency.value = 784;
            osc2.frequency.value = 659;
            osc1.type = 'sine';
            osc2.type = 'sine';
            
            var now = audioContext.currentTime;
            gainNode.gain.setValueAtTime(0, now);
            gainNode.gain.linearRampToValueAtTime(0.3, now + 0.05);
            gainNode.gain.linearRampToValueAtTime(0.1, now + 0.15);
            gainNode.gain.linearRampToValueAtTime(0, now + 0.4);
            
            osc1.start(now);
            osc2.start(now + 0.15);
            osc1.stop(now + 0.2);
            osc2.stop(now + 0.4);
        } catch (e) {
            console.log('Audio not supported:', e);
        }
    }
    
    // Update title with unread count
    function updateTitleCount(count) {
        unreadCount = count;
        if (count > 0) {
            document.title = '(' + count + ') ' + originalTitle;
        } else {
            document.title = originalTitle;
        }
    }
    
    // Scroll to bottom
    function scrollToBottom() {
        container.scrollTop = container.scrollHeight;
    }
    scrollToBottom();
    
    // Send message
    document.getElementById('hng-send-message-btn').addEventListener('click', function() {
        var textarea = document.getElementById('hng-chat-message');
        var message = textarea.value.trim();
        if (!message) return;
        
        this.disabled = true;
        
        var formData = new FormData();
        formData.append('action', 'hng_send_quote_message');
        formData.append('nonce', nonce);
        formData.append('order_id', orderId);
        formData.append('message', message);
        
        fetch(hngCommerce.ajaxUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            this.disabled = false;
            if (data.success) {
                textarea.value = '';
                document.querySelector('.hng-chat-empty')?.remove();
                container.insertAdjacentHTML('beforeend', data.data.html);
                scrollToBottom();
            }
        });
    });
    
    // Enter to send
    document.getElementById('hng-chat-message').addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            document.getElementById('hng-send-message-btn').click();
        }
    });
    
    // Accept terms
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('hng-accept-terms-btn')) {
            var termsId = e.target.dataset.termsId;
            e.target.disabled = true;
            e.target.textContent = '<?php esc_html_e('Processando...', 'hng-commerce'); ?>';
            
            var formData = new FormData();
            formData.append('action', 'hng_accept_quote_terms');
            formData.append('nonce', nonce);
            formData.append('terms_id', termsId);
            
            fetch(hngCommerce.ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reload page to update UI
                    window.location.reload();
                } else {
                    e.target.disabled = false;
                    e.target.textContent = '<?php esc_html_e('Aceitar Termos', 'hng-commerce'); ?>';
                    alert(data.data.message || '<?php esc_html_e('Erro ao aceitar termos', 'hng-commerce'); ?>');
                }
            });
        }
    });
    
    // Poll for new messages
    setInterval(function() {
        var lastMsg = container.querySelector('.hng-chat-msg:last-child, .hng-chat-terms-card:last-child');
        var lastId = lastMsg ? (lastMsg.dataset.id || 0) : 0;
        
        var formData = new FormData();
        formData.append('action', 'hng_get_quote_messages');
        formData.append('nonce', nonce);
        formData.append('order_id', orderId);
        formData.append('after_id', lastId);
        
        fetch(hngCommerce.ajaxUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data.html) {
                document.querySelector('.hng-chat-empty')?.remove();
                container.insertAdjacentHTML('beforeend', data.data.html);
                scrollToBottom();
                
                // Play sound and update title for new messages from admin
                playNotificationSound();
                updateTitleCount(unreadCount + 1);
            }
        });
    }, 10000);
    
    // Clear unread count when page is focused
    window.addEventListener('focus', function() {
        updateTitleCount(0);
    });
})();
</script>
