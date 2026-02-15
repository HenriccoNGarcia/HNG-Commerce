<?php

if (!defined('ABSPATH')) {
	exit;
}

/**

 * E-mail: Pedido Enviado - Cliente

 * 

 * @var HNG_Order $order

 * @var string $customer_name

 * @var string $tracking_code (optional)

 */



if ( ! defined( 'ABSPATH' ) ) {

    exit;

}



// Validate variables before using them - FIX for PHP 8.1+ null deprecation

$customer_name = isset( $customer_name ) && is_string( $customer_name ) ? $customer_name : '';

$order_number = ( $order && method_exists( $order, 'get_order_number' ) ) ? $order->get_order_number() : '';

$tracking_code = isset( $tracking_code ) && is_string( $tracking_code ) && ! empty( trim( $tracking_code ) ) ? $tracking_code : '';

?>



<h2>Olá, <?php echo esc_html( $customer_name ); ?>!</h2>



<p>Seu pedido foi <strong>enviado</strong>! 🚚</p>



<div style="background: #d4edda; border: 1px solid #28a745; padding: 20px; border-radius: 5px; margin: 20px 0;">

    <h3 style="margin-top: 0; color: #155724;">🚚 Pedido a Caminho!</h3>

    <p style="margin: 0;">Seu pedido já saiu para entrega. Acompanhe o status da entrega com o código de rastreamento abaixo.</p>

</div>



<?php if ( ! empty( $tracking_code ) ) : ?>

    <div style="margin: 20px 0; padding: 20px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 5px; text-align: center;">

        <h3 style="margin-top: 0; color: #856404;">Código de Rastreamento</h3>

        <p style="font-size: 1.5em; font-weight: bold; color: #000; margin: 10px 0; letter-spacing: 2px; font-family: monospace;">

            <?php echo esc_html( $tracking_code ); ?>

        </p>

        <p style="margin-bottom: 0;">

            <a href="https://rastreamento.correios.com.br/app/index.php" 

               target="_blank"

               style="display: inline-block; padding: 10px 20px; background: #ffc107; color: #000; text-decoration: none; border-radius: 5px; font-weight: bold; margin-top: 10px;">

                Rastrear no Site dos Correios

            </a>

        </p>

    </div>

<?php endif; ?>



<div class="order-details">

    <h3>Pedido #<?php echo esc_html( $order_number ); ?></h3>

    

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

                <?php 

                foreach ( $order->get_items() as $item ) : 

                    // Validate item data before using strpos/string functions

                    $product_name = isset( $item['product_name'] ) && is_string( $item['product_name'] ) ? $item['product_name'] : '';

                    $quantity = isset( $item['quantity'] ) ? absint( $item['quantity'] ) : 0;

                    $subtotal = isset( $item['subtotal'] ) ? floatval( $item['subtotal'] ) : 0;

                ?>

                    <tr>

                        <td style="padding: 10px; border-bottom: 1px solid #dee2e6;"><?php echo esc_html( $product_name ); ?></td>

                        <td style="padding: 10px; text-align: center; border-bottom: 1px solid #dee2e6;"><?php echo esc_html( $quantity ); ?></td>

                        <td style="padding: 10px; text-align: right; border-bottom: 1px solid #dee2e6;">

                            <?php echo function_exists( 'hng_price' ) ? esc_html( hng_price( $subtotal ) ) : esc_html( number_format( $subtotal, 2, ',', '.' ) ); ?>

                        </td>

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



<?php if ( $order && method_exists( $order, 'get_shipping_address' ) ) : 

    $shipping_address = $order->get_shipping_address();

    if ( isset( $shipping_address ) && is_array( $shipping_address ) && ! empty( $shipping_address ) ) :

?>

    <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 5px;">

        <h3 style="margin-top: 0;">Endereço de Entrega</h3>

        <p style="margin: 0;">

            <?php 

            $address_parts = array_filter( array_map( function( $part ) {

                return isset( $part ) && is_string( $part ) && ! empty( trim( $part ) ) ? esc_html( $part ) : '';

            }, $shipping_address ) );

            echo wp_kses_post( implode( '<br>', $address_parts ) );

            ?>

        </p>

    </div>

<?php 

    endif;

endif; 

?>



<div style="margin-top: 30px; padding: 20px; background: #e7f3ff; border-left: 4px solid #007bff; border-radius: 5px;">

    <h3 style="margin-top: 0; color: #004085;">💡 Dica</h3>

    <p style="margin: 0;">O prazo de entrega começa a contar a partir da postagem. Fique atento ao seu e-mail e telefone para atualizações da transportadora.</p>

</div>



<p style="margin-top: 30px;">

    <a href="<?php echo esc_url( home_url( '/minha-conta/pedidos/' ) ); ?>" 

       style="display: inline-block; padding: 12px 30px; background: #007bff; color: #ffffff; text-decoration: none; border-radius: 5px; font-weight: bold;">

        Acompanhar Entrega

    </a>

</p>



<p style="margin-top: 30px; color: #6c757d; font-size: 0.9em;">

    <strong>Dúvidas?</strong> Entre em contato conosco através do nosso suporte.

</p>

