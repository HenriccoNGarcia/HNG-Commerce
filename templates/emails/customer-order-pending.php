<?php

if (!defined('ABSPATH')) {
    exit;
}

/**

 * E-mail: Pedido Pendente - Cliente

 * 

 * @var HNG_Order $order

 * @var string $customer_name

 */



if ( ! defined( 'ABSPATH' ) ) {

    exit;

}



// Validate variables

$customer_name = isset( $customer_name ) && is_string( $customer_name ) ? $customer_name : '';

$order_number = ( $order && method_exists( $order, 'get_order_number' ) ) ? $order->get_order_number() : '';

?>



<h2>Olá, <?php echo esc_html( $customer_name ); ?>!</h2>



<p>Recebemos seu pedido e ele está <strong>aguardando pagamento</strong>.</p>



<div style="background: #fff3cd; border: 1px solid #ffc107; padding: 20px; border-radius: 5px; margin: 20px 0;">

    <h3 style="margin-top: 0; color: #856404;">⏳ Aguardando Pagamento</h3>

    <p style="margin: 0;">Assim que confirmarmos o pagamento, começaremos a preparar seu pedido para envio.</p>

</div>



<div class="order-details">

    <h3>Pedido #<?php echo esc_html( $order_number ); ?></h3>

    

    <?php if ( $order && method_exists( $order, 'get_payment_method_title' ) ) : 

        $payment_method = $order->get_payment_method_title();

        if ( isset( $payment_method ) && is_string( $payment_method ) && ! empty( $payment_method ) ) :

    ?>

        <p><strong>Forma de Pagamento:</strong> <?php echo esc_html( $payment_method ); ?></p>

    <?php 

        endif;

    endif; 

    ?>

    

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

                    <td style="padding: 15px; text-align: right; font-size: 1.2em;">

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



<p style="margin-top: 30px;">

    <a href="<?php echo esc_url( home_url( '/minha-conta/pedidos/' ) ); ?>" 

       style="display: inline-block; padding: 12px 30px; background: #007bff; color: #ffffff; text-decoration: none; border-radius: 5px; font-weight: bold;">

        Ver Meu Pedido

    </a>

</p>



<p style="margin-top: 30px; color: #6c757d; font-size: 0.9em;">

    <strong>Dúvidas?</strong> Entre em contato conosco através do nosso suporte.

</p>

