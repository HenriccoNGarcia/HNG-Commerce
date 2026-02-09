<?php

if (!defined('ABSPATH')) {
    exit;
}

/**

 * E-mail: Pedido Concluído - Cliente

 * 

 * @var HNG_Order $order

 * @var string $customer_name

 */



if ( ! defined( 'ABSPATH' ) ) {

    exit;

}



// Validate variables before using them

$customer_name = isset( $customer_name ) && is_string( $customer_name ) ? $customer_name : '';

$order_number = ( $order && method_exists( $order, 'get_order_number' ) ) ? $order->get_order_number() : '';

?>



<h2>Olá, <?php echo esc_html( $customer_name ); ?>!</h2>



<p>Seu pedido foi <strong>concluído e entregue</strong>! 🎉</p>



<div style="background: #d4edda; border: 1px solid #28a745; padding: 20px; border-radius: 5px; margin: 20px 0;">

    <h3 style="margin-top: 0; color: #155724;">✓ Pedido Entregue com Sucesso!</h3>

    <p style="margin: 0;">Esperamos que você goste dos produtos. Se tiver alguma dúvida ou problema, entre em contato conosco.</p>

</div>



<div class="order-details">

    <h3>Detalhes do Pedido #<?php echo esc_html( $order_number ); ?></h3>

    

    <?php if ( $order && method_exists( $order, 'get_items' ) ) : ?>

        <table class="order-items" style="width: 100%; border-collapse: collapse; margin: 20px 0;">

            <thead>

                <tr style="background: #f8f9fa;">

                    <th style="padding: 10px; text-align: left; border-bottom: 2px solid #dee2e6;">Produto</th>

                    <th style="padding: 10px; text-align: center; border-bottom: 2px solid #dee2e6;">Qtd</th>

                    <th style="padding: 10px; text-align: right; border-bottom: 2px solid #dee2e6;">Total</th>

                </tr>

            </thead>

            <tbody>

                <?php foreach ( $order->get_items() as $item ) : 

                    $product_name = isset( $item['product_name'] ) && is_string( $item['product_name'] ) ? $item['product_name'] : '';

                    $quantity = isset( $item['quantity'] ) ? absint( $item['quantity'] ) : 0;

                    $subtotal = isset( $item['subtotal'] ) ? floatval( $item['subtotal'] ) : 0;

                ?>

                    <tr>

                        <td style="padding: 10px; border-bottom: 1px solid #dee2e6;"><?php echo esc_html( $product_name ); ?></td>

                        <td style="padding: 10px; text-align: center; border-bottom: 1px solid #dee2e6;"><?php echo esc_html( $quantity ); ?></td>

                        <td style="padding: 10px; text-align: right; border-bottom: 1px solid #dee2e6;"><?php echo function_exists( 'hng_price' ) ? esc_html( hng_price( $subtotal ) ) : esc_html( number_format( $subtotal, 2, ',', '.' ) ); ?></td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

            <tfoot>

                <tr style="background: #f8f9fa; font-weight: bold;">

                    <td colspan="2" style="padding: 15px; text-align: right;">Total:</td>

                    <td style="padding: 15px; text-align: right; font-size: 1.2em; color: #28a745;">

                        <?php 

                        if ( $order && method_exists( $order, 'get_total' ) ) {

                            $total = $order->get_total();

                            echo function_exists( 'hng_price' ) ? esc_html( hng_price( $total ) ) : esc_html( number_format( $total, 2, ',', '.' ) );

                        }

                        ?>

                    </td>

                </tr>

            </tfoot>

        </table>

    <?php endif; ?>

</div>



<div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 5px;">

    <h3 style="margin-top: 0;">Deixe sua avaliação!</h3>

    <p>Sua opinião é muito importante para nós. <a href="<?php echo esc_url( home_url( '/minha-conta/pedidos/' ) ); ?>" style="color: #007bff; text-decoration: none;">Avalie este pedido</a></p>

</div>



<p style="margin-top: 30px; color: #6c757d; font-size: 0.9em;">

    <strong>Dúvidas?</strong> Entre em contato conosco através do nosso suporte.

</p>

