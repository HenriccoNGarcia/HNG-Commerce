<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * My Account - Tracking widget
 * Clean, safe template for showing recent orders with tracking codes
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$customer_id = get_current_user_id();
$orders = array();

// Prefer helper if available
if (function_exists('hng_db_full_table_name')) {
    $orders_table_full = hng_db_full_table_name('hng_orders');
} else {
    $orders_table_full = $wpdb->prefix . 'hng_orders';
}
$orders_table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_orders') : ('`' . str_replace('`', '', $orders_table_full) . '`');

// Query últimos pedidos com código de rastreamento
$orders = $wpdb->get_results($wpdb->prepare(
    "SELECT o.id, o.order_number, o.status, o.created_at, pm1.meta_value as tracking_code, pm2.meta_value as shipping_company
     FROM {$orders_table_sql} o
     LEFT JOIN {$wpdb->postmeta} pm1 ON o.id = pm1.post_id AND pm1.meta_key = '_tracking_code'
     LEFT JOIN {$wpdb->postmeta} pm2 ON o.id = pm2.post_id AND pm2.meta_key = '_shipping_company'
     WHERE o.customer_id = %d
       AND o.status IN ('hng-shipped','hng-completed')
       AND pm1.meta_value IS NOT NULL
       AND pm1.meta_value != ''
     ORDER BY o.created_at DESC
     LIMIT 5",
    $customer_id
));
?>

<div class="hng-my-orders-tracking">
    <h2><?php esc_html_e('Rastreamento de Pedidos', 'hng-commerce'); ?></h2>
    <?php do_action('hng_before_my_tracking'); ?>

    <form class="hng-tracking-form" method="get" action="<?php echo esc_url(home_url('/rastreamento/')); ?>" aria-label="Formulário de rastreamento">
        <label for="tracking_code"><?php esc_html_e('Código de Rastreamento:', 'hng-commerce'); ?></label>
        <input type="text" id="tracking_code" name="code" required aria-required="true" aria-label="Código de Rastreamento" />
        <button type="submit" class="hng-button hng-button-primary"><?php esc_html_e('Rastrear', 'hng-commerce'); ?></button>
    </form>

    <?php if (!empty($orders)) : ?>
        <div class="hng-tracking-list" role="list">
            <?php foreach ($orders as $order) : ?>
                <div class="hng-tracking-item" role="listitem">
                    <div class="hng-tracking-item-header">
                        <div class="hng-order-info">
                            <strong><?php echo esc_html('#' . $order->order_number); ?></strong>
                            <span class="hng-order-date"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($order->created_at))); ?></span>
                        </div>
                        <div class="hng-order-status <?php echo esc_attr($order->status); ?>"><?php echo esc_html($order->status); ?></div>
                    </div>
                    <div class="hng-tracking-item-body">
                        <div class="hng-tracking-code-box">
                            <label><?php esc_html_e('Código de Rastreamento:', 'hng-commerce'); ?></label>
                            <div class="hng-code-group">
                                <input type="text" value="<?php echo esc_attr($order->tracking_code); ?>" readonly id="tracking-<?php echo esc_attr($order->id); ?>">
                                <!-- translators: label for a button that copies the tracking code to clipboard -->
                                <button class="hng-copy-btn" data-code="<?php echo esc_attr($order->tracking_code); ?>" data-target="tracking-<?php echo esc_attr($order->id); ?>"><?php esc_html_e('Copiar', 'hng-commerce'); ?></button>
                            </div>
                            <?php if (!empty($order->shipping_company)) : ?>
                                <small><?php esc_html_e('Transportadora:', 'hng-commerce'); ?> <?php echo esc_html($order->shipping_company); ?></small>
                            <?php endif; ?>
                        </div>

                        <div class="hng-tracking-actions">
                            <a href="<?php echo esc_url(home_url('/rastreamento/?code=' . urlencode($order->tracking_code))); ?>" class="hng-btn hng-btn-sm hng-btn-primary" target="_blank"><?php esc_html_e('Ver Rastreamento Completo', 'hng-commerce'); ?></a>
                            <?php
                            $tracking_url = '';
                            if (!empty($order->shipping_company)) {
                                $company_lower = strtolower((string) $order->shipping_company);
                                if (strpos((string) $company_lower, 'correios') !== false) {
                                    $tracking_url = 'https://rastreamento.correios.com.br/app/index.php';
                                } elseif (strpos((string) $company_lower, 'jadlog') !== false) {
                                    $tracking_url = 'https://www.jadlog.com.br/tracking/' . rawurlencode($order->tracking_code);
                                }
                            }
                            if ($tracking_url) : ?>
                                <!-- translators: link text to open the shipping company's tracking page in a new tab -->
                                <a href="<?php echo esc_url($tracking_url); ?>" class="hng-btn hng-btn-sm hng-btn-outline" target="_blank"><?php esc_html_e('Site da Transportadora', 'hng-commerce'); ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else : ?>
        <div class="hng-empty-tracking">
            <p><?php esc_html_e('Nenhum pedido com código de rastreamento disponível.', 'hng-commerce'); ?></p>
            <p><small><?php esc_html_e('Os códigos aparecem aqui após o envio dos pedidos.', 'hng-commerce'); ?></small></p>
        </div>
    <?php endif; ?>
</div>

<style>
.hng-my-orders-tracking { background:#fff; padding:25px; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.05); margin-bottom:30px }
.hng-tracking-list { display:flex; flex-direction:column; gap:15px }
.hng-tracking-item { border:1px solid #e0e0e0; border-radius:8px; overflow:hidden }
.hng-tracking-item-header { background:#f9f9f9; padding:12px 15px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e0e0e0 }
.hng-order-info strong { font-size:15px; color:#333 }
.hng-order-date { font-size:12px; color:#999 }
.hng-tracking-item-body { padding:15px }
.hng-code-group { display:flex; gap:8px }
.hng-code-group input { flex:1; padding:10px; border:1px solid #ddd; border-radius:4px; font-family:Courier, monospace; background:#f9f9f9 }
.hng-copy-btn { padding:10px 15px; background:#667eea; color:#fff; border:none; border-radius:4px; cursor:pointer }
.hng-copy-btn.copied { background:#4caf50 }
.hng-tracking-actions { display:flex; gap:10px; flex-wrap:wrap }
.hng-btn-sm { padding:8px 16px; font-size:13px }
.hng-btn-outline { background:#fff; border:1px solid #667eea; color:#667eea }
.hng-empty-tracking { text-align:center; padding:40px 20px; color:#666 }
@media (max-width:768px) { .hng-code-group { flex-direction:column } .hng-btn-sm { width:100% } }
</style>

<script>
function copyTrackingCode(inputId, button) {
    var input = document.getElementById(inputId);
    if (!input) return;
    input.select();
    try { document.execCommand('copy'); } catch (e) {}
    var original = button.innerHTML;
    // translators: small transient label shown after successfully copying the tracking code
    button.innerHTML = '<?php echo esc_js(__('Copiado!', 'hng-commerce')); ?>';
    button.classList.add('copied');
    setTimeout(function(){ button.innerHTML = original; button.classList.remove('copied'); }, 2000);
}

document.addEventListener('click', function(e){
    var btn = e.target.closest('.hng-copy-btn');
    if (!btn) return;
    e.preventDefault();
    var target = btn.getAttribute('data-target');
    copyTrackingCode(target, btn);
});
</script>
