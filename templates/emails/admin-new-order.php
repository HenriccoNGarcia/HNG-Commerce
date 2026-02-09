<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * E-mail: Novo Pedido - Admin
 * 
 * @var HNG_Order $order
 */

if (!defined('ABSPATH')) exit;
?>

<h2>Novo Pedido Recebido!</h2>

<p>Você recebeu um novo pedido em sua loja.</p>

<div class="order-details">
    <h3>Informações do Pedido</h3>
    <p>
        <strong>Número do Pedido:</strong> <?php echo esc_html($order->get_order_number()); ?><br>
        <strong>Data:</strong> <?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($order->get_created_at()))); ?><br>
        <strong>Status:</strong> <?php echo esc_html($order->get_status()); ?><br>
        <strong>Total:</strong> <?php echo esc_html($order->get_formatted_total()); ?><br>
        <strong>Comissão HNG:</strong> <?php echo esc_html(hng_price($order->get_commission())); ?> (<?php 
            $commission_percent = ($order->get_commission() / $order->get_total()) * 100;
            echo esc_html(number_format($commission_percent, 2, ',', '.')); 
        ?>%)<br>
        <strong>Forma de Pagamento:</strong> <?php echo esc_html($order->get_payment_method_title()); ?>
    </p>
</div>

<h3>Dados do Cliente</h3>
<p>
    <strong>Nome:</strong> <?php echo esc_html($order->get_customer_name()); ?><br>
    <strong>E-mail:</strong> <?php echo esc_html($order->get_customer_email()); ?><br>
    <strong>Telefone:</strong> <?php echo esc_html($order->get_billing_phone()); ?><br>
    <strong>CPF/CNPJ:</strong> <?php echo esc_html($order->get_meta('billing_cpf')); ?><br>
    <strong>IP:</strong> <?php echo esc_html($order->get_meta('customer_ip')); ?>
</p>

<h3>Produtos</h3>
<table class="order-items">
    <thead>
        <tr>
            <th>Produto</th>
            <th style="text-align: center;">Qtd</th>
            <th style="text-align: right;">Preço</th>
            <th style="text-align: right;">Subtotal</th>
            <th style="text-align: right;">Comissão</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($order->get_items() as $item): ?>
        <tr>
            <td>
                <?php echo esc_html($item['product_name']); ?>
                <?php $item_cp = $item; include dirname(__FILE__,2) . '/partials/order-item-custom-fields.php'; ?>
            </td>
            <td style="text-align: center;"><?php echo esc_html($item['quantity']); ?></td>
            <td style="text-align: right;"><?php echo esc_html(hng_price($item['price'])); ?></td>
            <td style="text-align: right;"><?php echo esc_html(hng_price($item['subtotal'])); ?></td>
            <td style="text-align: right;"><?php echo esc_html(hng_price($item['commission'])); ?> (<?php echo esc_html(number_format($item['commission_rate'], 1)); ?>%)</td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="3" style="text-align: right;"><strong>Subtotal:</strong></td>
            <td style="text-align: right;"><?php echo esc_html(hng_price($order->get_subtotal())); ?></td>
            <td></td>
        </tr>
        <?php if ($order->get_shipping_total() > 0): ?>
        <tr>
            <td colspan="3" style="text-align: right;"><strong>Frete:</strong></td>
            <td style="text-align: right;"><?php echo esc_html(hng_price($order->get_shipping_total())); ?></td>
            <td></td>
        </tr>
        <?php endif; ?>
        <tr>
            <td colspan="3" style="text-align: right;"><strong class="order-total">TOTAL:</strong></td>
            <td style="text-align: right;"><strong class="order-total"><?php echo esc_html($order->get_formatted_total()); ?></strong></td>
            <td style="text-align: right;"><strong><?php echo esc_html(hng_price($order->get_commission())); ?></strong></td>
        </tr>
    </tfoot>
</table>

<h3>Endereço de Entrega</h3>
<p>
    <?php echo esc_html($order->get_billing_address_1()); ?>, <?php echo esc_html($order->get_meta('billing_number')); ?>
    <?php if (!empty($order->get_meta('billing_address_2'))): ?>
        - <?php echo esc_html($order->get_meta('billing_address_2')); ?>
    <?php endif; ?><br>
    <?php echo esc_html($order->get_meta('billing_neighborhood')); ?><br>
    <?php echo esc_html($order->get_billing_city()); ?> - <?php echo esc_html($order->get_billing_state()); ?><br>
    CEP: <?php echo esc_html($order->get_billing_postcode()); ?>
</p>

<?php if (!empty($order->get_meta('customer_note'))): ?>
<h3>Observações do Cliente</h3>
<p style="background: #f9f9f9; padding: 15px; border-left: 3px solid #3498db;">
    <?php echo nl2br(esc_html($order->get_meta('customer_note'))); ?>
</p>
<?php endif; ?>

<p style="text-align: center;">
    <a href="<?php echo esc_url(admin_url('post.php?post=' . $order->get_post_id() . '&action=edit')); ?>" class="button">
        Gerenciar Pedido no Admin
    </a>
</p>
