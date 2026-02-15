<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * E-mail: Novo Pedido - Cliente
 * 
 * @var HNG_Order $order
 * @var string $customer_name
 */

if (!defined('ABSPATH')) exit;
?>

<h2>Olá, <?php echo esc_html($customer_name); ?>!</h2>

<p>Obrigado por fazer seu pedido em nossa loja. Recebemos seu pedido e ele está sendo processado.</p>

<div class="order-details">
    <h3>Detalhes do Pedido</h3>
    <p>
        <strong>Número do Pedido:</strong> <?php echo esc_html($order->get_order_number()); ?><br>
        <strong>Data:</strong> <?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($order->get_created_at()))); ?><br>
        <strong>Total:</strong> <?php echo esc_html($order->get_formatted_total()); ?><br>
        <strong>Forma de Pagamento:</strong> <?php echo esc_html($order->get_payment_method_title()); ?>
    </p>
</div>

<h3>Produtos</h3>
<table class="order-items">
    <thead>
        <tr>
            <th>Produto</th>
            <th style="text-align: center;">Qtd</th>
            <th style="text-align: right;">Preço</th>
            <th style="text-align: right;">Subtotal</th>
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
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="3" style="text-align: right;"><strong>Subtotal:</strong></td>
            <td style="text-align: right;"><?php echo esc_html(hng_price($order->get_subtotal())); ?></td>
        </tr>
        <?php if ($order->get_shipping_total() > 0): ?>
        <tr>
            <td colspan="3" style="text-align: right;"><strong>Frete:</strong></td>
            <td style="text-align: right;"><?php echo esc_html(hng_price($order->get_shipping_total())); ?></td>
        </tr>
        <?php endif; ?>
        <?php if ($order->get_discount_total() > 0): ?>
        <tr>
            <td colspan="3" style="text-align: right;"><strong>Desconto:</strong></td>
            <td style="text-align: right;">-<?php echo esc_html(hng_price($order->get_discount_total())); ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <td colspan="3" style="text-align: right;"><strong class="order-total">TOTAL:</strong></td>
            <td style="text-align: right;"><strong class="order-total"><?php echo esc_html($order->get_formatted_total()); ?></strong></td>
        </tr>
    </tfoot>
</table>

<?php if ($order->get_payment_method() === 'pix'): ?>
<div style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 5px; margin: 20px 0;">
    <h3 style="margin-top: 0; color: #856404;">Aguardando Pagamento PIX</h3>
    <p>Acesse a página do pedido para visualizar o QR Code e realizar o pagamento.</p>
</div>
<?php endif; ?>

<?php if ($order->get_payment_method() === 'boleto'): ?>
<div style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 5px; margin: 20px 0;">
    <h3 style="margin-top: 0; color: #856404;">Aguardando Pagamento do Boleto</h3>
    <p>Acesse a página do pedido para visualizar e imprimir o boleto bancário.</p>
</div>
<?php endif; ?>

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

<p style="text-align: center;">
    <a href="<?php echo esc_url(add_query_arg(['order_id' => $order->get_id(), 'key' => $order->get_order_number()], hng_get_page_url('obrigado'))); ?>" class="button">
        Ver Detalhes do Pedido
    </a>
</p>

<p>Se tiver dúvidas, entre em contato conosco.</p>

<p>
    Obrigado por comprar conosco!<br>
    <strong><?php echo esc_html(get_bloginfo('name')); ?></strong>
</p>
