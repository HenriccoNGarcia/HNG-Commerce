<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * E-mail: Mudanï¿½a de Status - Cliente
 * 
 * @var HNG_Order $order
 * @var string $customer_name
 * @var string $old_status
 * @var string $new_status
 */

if (!defined('ABSPATH')) exit;

$status_labels = [
    'hng-pending' => 'Pendente',
    'hng-processing' => 'Processando',
    'hng-on-hold' => 'Em Espera',
    'hng-completed' => 'Concluï¿½do',
    'hng-cancelled' => 'Cancelado',
    'hng-refunded' => 'Reembolsado',
    'hng-failed' => 'Falhou',
];

$status_messages = [
    'hng-processing' => 'Seu pagamento foi confirmado e estamos preparando seu pedido para envio.',
    'hng-completed' => 'Seu pedido foi concluï¿½do e entregue. Esperamos que vocï¿½ goste!',
    'hng-cancelled' => 'Seu pedido foi cancelado conforme solicitado.',
    'hng-refunded' => 'Seu pedido foi reembolsado. O valor serï¿½ estornado em atï¿½ 10 dias ï¿½teis.',
];
?>

<h2>Olï¿½, <?php echo esc_html($customer_name); ?>!</h2>

<p>O status do seu pedido foi atualizado.</p>

<div class="order-details">
    <h3>Atualizaï¿½ï¿½o do Pedido #<?php echo esc_html($order->get_order_number()); ?></h3>
    <p>
        <strong>Status Anterior:</strong> <?php echo esc_html($status_labels[$old_status] ?? $old_status); ?><br>
        <strong>Novo Status:</strong> <span style="color: #3498db; font-weight: bold;"><?php echo esc_html($status_labels[$new_status] ?? $new_status); ?></span>
    </p>
    
    <?php if (isset($status_messages[$new_status])): ?>
    <p style="background: #e8f4fd; padding: 15px; border-left: 3px solid #3498db; margin: 15px 0;">
        <?php echo esc_html($status_messages[$new_status]); ?>
    </p>
    <?php endif; ?>
</div>

<?php if ($new_status === 'hng-processing'): ?>
<div style="background: #d4edda; border: 1px solid #28a745; padding: 15px; border-radius: 5px; margin: 20px 0;">
    <h3 style="margin-top: 0; color: #155724;">? Pagamento Confirmado!</h3>
    <p>Seu pagamento foi aprovado e estamos preparando seu pedido. Em breve vocï¿½ receberï¿½ o cï¿½digo de rastreamento.</p>
</div>
<?php endif; ?>

<?php if ($new_status === 'hng-completed'): ?>
<div style="background: #d4edda; border: 1px solid #28a745; padding: 15px; border-radius: 5px; margin: 20px 0;">
    <h3 style="margin-top: 0; color: #155724;">?? Pedido Entregue!</h3>
    <p>Esperamos que vocï¿½ tenha gostado dos produtos. Se tiver algum problema, entre em contato conosco.</p>
</div>
<?php endif; ?>

<h3>Resumo do Pedido</h3>
<table class="order-items">
    <thead>
        <tr>
            <th>Produto</th>
            <th style="text-align: center;">Qtd</th>
            <th style="text-align: right;">Total</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($order->get_items() as $item): ?>
        <tr>
            <td><?php echo esc_html($item['product_name']); ?></td>
            <td style="text-align: center;"><?php echo esc_html($item['quantity']); ?></td>
            <td style="text-align: right;"><?php echo esc_html(hng_price($item['subtotal'])); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="2" style="text-align: right;"><strong class="order-total">TOTAL:</strong></td>
            <td style="text-align: right;"><strong class="order-total"><?php echo esc_html($order->get_formatted_total()); ?></strong></td>
        </tr>
    </tfoot>
</table>

<p style="text-align: center;">
    <a href="<?php echo esc_url(add_query_arg(['order_id' => $order->get_id(), 'key' => $order->get_order_number()], hng_get_page_url('obrigado'))); ?>" class="button">
        Ver Detalhes do Pedido
    </a>
</p>

<p>
    Obrigado por comprar conosco!<br>
    <strong><?php echo esc_html(get_bloginfo('name')); ?></strong>
</p>
