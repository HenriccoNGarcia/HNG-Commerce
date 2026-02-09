<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Template: My Account - Quotes (Meus Orçamentos)
 * 
 * Exibe os orçamentos do cliente com sistema de chat
 * 
 * @package HNG_Commerce
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$user_id = get_current_user_id();

// Check if viewing a specific quote
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET parameter
$viewing_order = isset($_GET['order']) ? intval($_GET['order']) : 0;

if ($viewing_order) {
    // Verify order belongs to user
    $order = new HNG_Order($viewing_order);
    if ($order->get_customer_id() != $user_id) {
        echo '<div class="hng-notice hng-notice-error">' . esc_html__('Orçamento não encontrado.', 'hng-commerce') . '</div>';
        return;
    }
    
    // Show single quote with chat
    include __DIR__ . '/my-account-quote-single.php';
    return;
}

// Get user's quote orders
global $wpdb;
$orders_table = hng_db_full_table_name('hng_orders');

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$quotes = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$orders_table} 
     WHERE customer_id = %d 
     AND status IN ('hng-pending-approval', 'hng-awaiting-payment', 'hng-processing', 'hng-completed', 'hng-cancelled')
     ORDER BY created_at DESC",
    $user_id
));
?>

<div class="hng-my-quotes">
    <h2><?php esc_html_e('Meus Orçamentos', 'hng-commerce'); ?></h2>
    
    <?php if (empty($quotes)) : ?>
        <div class="hng-empty-state">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="16" y1="13" x2="8" y2="13"/>
                <line x1="16" y1="17" x2="8" y2="17"/>
                <polyline points="10 9 9 9 8 9"/>
            </svg>
            <h3><?php esc_html_e('Nenhum orçamento encontrado', 'hng-commerce'); ?></h3>
            <p><?php esc_html_e('Quando você solicitar um orçamento, ele aparecerá aqui.', 'hng-commerce'); ?></p>
            <a href="<?php echo esc_url(home_url('/loja/')); ?>" class="hng-btn-primary">
                <?php esc_html_e('Ver Produtos', 'hng-commerce'); ?>
            </a>
        </div>
    <?php else : ?>
        <div class="hng-quotes-list">
            <?php foreach ($quotes as $quote) : 
                $order = new HNG_Order($quote->id);
                $status_label = $order->get_status_label();
                $status_class = $order->get_status();
                
                // Get pending terms
                $pending_terms = hng_quote_chat()->get_pending_terms($quote->id);
                
                // Get unread messages count
                $unread = hng_quote_chat()->get_unread_count($quote->id, 'customer');
            ?>
                <div class="hng-quote-card glass-card">
                    <div class="hng-quote-header">
                        <div class="hng-quote-number">
                            <strong>#<?php echo esc_html($quote->order_number); ?></strong>
                            <span class="hng-quote-date">
                                <?php echo esc_html(date_i18n('d/m/Y', strtotime($quote->created_at))); ?>
                            </span>
                        </div>
                        <span class="hng-quote-status hng-status-<?php echo esc_attr($status_class); ?>">
                            <?php echo esc_html($status_label); ?>
                        </span>
                    </div>
                    
                    <div class="hng-quote-body">
                        <?php 
                        $items = $order->get_items();
                        if (!empty($items)) :
                            $item = reset($items);
                        ?>
                            <div class="hng-quote-product">
                                <?php 
                                $thumb = get_the_post_thumbnail_url($item['product_id'], 'thumbnail');
                                if ($thumb) :
                                ?>
                                    <img src="<?php echo esc_url($thumb); ?>" alt="">
                                <?php endif; ?>
                                <div class="hng-quote-product-info">
                                    <h4><?php echo esc_html($item['name']); ?></h4>
                                    <?php if (count($items) > 1) : ?>
                                        <span class="hng-quote-more">
                                            <?php 
                                            /* translators: %d: number of additional items */
                                            printf(esc_html__('+ %d outros itens', 'hng-commerce'), count($items) - 1); 
                                            ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="hng-quote-details">
                            <?php if ($quote->total > 0) : ?>
                                <div class="hng-quote-total">
                                    <span><?php esc_html_e('Valor:', 'hng-commerce'); ?></span>
                                    <strong><?php echo esc_html(hng_price($quote->total)); ?></strong>
                                </div>
                            <?php else : ?>
                                <div class="hng-quote-total pending">
                                    <span><?php esc_html_e('Valor:', 'hng-commerce'); ?></span>
                                    <em><?php esc_html_e('Aguardando orçamento', 'hng-commerce'); ?></em>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($pending_terms) : ?>
                            <div class="hng-quote-alert">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                                    <line x1="12" y1="9" x2="12" y2="13"/>
                                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                                </svg>
                                <?php esc_html_e('Termos pendentes de aceite', 'hng-commerce'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="hng-quote-footer">
                        <a href="<?php echo esc_url(add_query_arg(['account-page' => 'quotes', 'order' => $quote->id], hng_get_myaccount_url())); ?>" 
                           class="hng-btn-outline">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                            </svg>
                            <?php esc_html_e('Ver Detalhes', 'hng-commerce'); ?>
                            <?php if ($unread > 0) : ?>
                                <span class="hng-badge"><?php echo esc_html($unread); ?></span>
                            <?php endif; ?>
                        </a>
                        
                        <?php if ($quote->status === 'hng-awaiting-payment') : 
                            $can_pay = !$pending_terms;
                            $payment_link = get_post_meta($quote->post_id, '_quote_payment_link', true);
                        ?>
                            <?php if ($can_pay && $payment_link) : ?>
                                <a href="<?php echo esc_url($payment_link); ?>" class="hng-btn-primary">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                                        <line x1="1" y1="10" x2="23" y2="10"/>
                                    </svg>
                                    <?php esc_html_e('Pagar Agora', 'hng-commerce'); ?>
                                </a>
                            <?php elseif (!$can_pay) : ?>
                                <button type="button" class="hng-btn-disabled" disabled title="<?php esc_attr_e('Aceite os termos para pagar', 'hng-commerce'); ?>">
                                    <?php esc_html_e('Aceite os Termos', 'hng-commerce'); ?>
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.hng-my-quotes h2 {
    font-size: 1.5rem;
    margin-bottom: 1.5rem;
    color: white;
}

.hng-empty-state {
    text-align: center;
    padding: 3rem;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 1rem;
    border: 1px dashed rgba(255, 255, 255, 0.1);
}

.hng-empty-state svg {
    color: rgba(255, 255, 255, 0.2);
    margin-bottom: 1rem;
}

.hng-empty-state h3 {
    color: white;
    margin-bottom: 0.5rem;
}

.hng-empty-state p {
    color: rgba(255, 255, 255, 0.5);
    margin-bottom: 1.5rem;
}

.hng-quotes-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.hng-quote-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 1rem;
    overflow: hidden;
    transition: all 0.3s ease;
}

.hng-quote-card:hover {
    border-color: rgba(42, 255, 163, 0.3);
}

.hng-quote-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.25rem;
    background: rgba(255, 255, 255, 0.02);
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.hng-quote-number {
    display: flex;
    flex-direction: column;
}

.hng-quote-number strong {
    color: white;
    font-size: 1.1rem;
}

.hng-quote-date {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
}

.hng-quote-status {
    display: inline-block;
    padding: 0.375rem 0.75rem;
    border-radius: 2rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.hng-status-hng-pending-approval {
    background: rgba(245, 158, 11, 0.2);
    color: #fbbf24;
}

.hng-status-hng-awaiting-payment {
    background: rgba(59, 130, 246, 0.2);
    color: #60a5fa;
}

.hng-status-hng-processing {
    background: rgba(168, 85, 247, 0.2);
    color: #c084fc;
}

.hng-status-hng-completed {
    background: rgba(34, 197, 94, 0.2);
    color: #4ade80;
}

.hng-status-hng-cancelled {
    background: rgba(239, 68, 68, 0.2);
    color: #f87171;
}

.hng-quote-body {
    padding: 1.25rem;
}

.hng-quote-product {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.hng-quote-product img {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 0.5rem;
    background: rgba(255, 255, 255, 0.05);
}

.hng-quote-product h4 {
    margin: 0 0 0.25rem;
    color: white;
    font-size: 1rem;
}

.hng-quote-more {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.5);
}

.hng-quote-details {
    margin-top: 0.5rem;
}

.hng-quote-total {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.95rem;
}

.hng-quote-total span {
    color: rgba(255, 255, 255, 0.6);
}

.hng-quote-total strong {
    color: var(--neon-green, #2AFFA3);
    font-size: 1.1rem;
}

.hng-quote-total.pending em {
    color: rgba(255, 255, 255, 0.4);
    font-style: italic;
}

.hng-quote-alert {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 1rem;
    padding: 0.75rem 1rem;
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.3);
    border-radius: 0.5rem;
    color: #fbbf24;
    font-size: 0.85rem;
}

.hng-quote-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    background: rgba(255, 255, 255, 0.02);
    border-top: 1px solid rgba(255, 255, 255, 0.05);
}

.hng-btn-outline {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 1rem;
    background: transparent;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 0.5rem;
    color: white;
    font-size: 0.875rem;
    text-decoration: none;
    transition: all 0.3s ease;
}

.hng-btn-outline:hover {
    border-color: var(--neon-green, #2AFFA3);
    color: var(--neon-green, #2AFFA3);
}

.hng-btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 1rem;
    background: var(--neon-green, #2AFFA3);
    border: none;
    border-radius: 0.5rem;
    color: var(--brand-black, #050505);
    font-size: 0.875rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s ease;
}

.hng-btn-primary:hover {
    background: #25e892;
    transform: translateY(-2px);
}

.hng-btn-disabled {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 1rem;
    background: rgba(255, 255, 255, 0.1);
    border: none;
    border-radius: 0.5rem;
    color: rgba(255, 255, 255, 0.4);
    font-size: 0.875rem;
    cursor: not-allowed;
}

.hng-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    background: #ef4444;
    border-radius: 9px;
    color: white;
    font-size: 0.7rem;
    font-weight: 700;
}

@media (max-width: 640px) {
    .hng-quote-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }
    
    .hng-quote-footer {
        flex-direction: column;
    }
    
    .hng-quote-footer a,
    .hng-quote-footer button {
        width: 100%;
        justify-content: center;
    }
}
</style>
