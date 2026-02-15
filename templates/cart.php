<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Template: Carrinho
 *
 * @package HNG_Commerce
 */

if (!defined('ABSPATH')) {
    exit;
}

$cart = hng_cart();
?>

<div class="hng-cart" role="region" aria-label="Carrinho de compras">
    <?php hng_print_notices(); ?>
    
    <?php
    /**
     * Hook: hng_before_cart
     */
    do_action('hng_before_cart');
    ?>
    <?php if ($cart->is_empty()): ?>
        <p class="hng-cart-empty" aria-live="polite"><?php esc_html_e('Seu carrinho está vazio.', 'hng-commerce'); ?></p>
        <a href="<?php echo esc_url(hng_get_shop_url()); ?>" class="hng-button">
            <?php esc_html_e('Continuar Comprando', 'hng-commerce'); ?>
        </a>
    <?php else: ?>
        <form class="hng-cart-form" method="post" action="" aria-label="Formulário do carrinho">
            <?php wp_nonce_field('hng_update_cart', 'hng_cart_nonce'); ?>
            <?php do_action('hng_before_cart_table'); ?>
            <div class="hng-cart-table-responsive" tabindex="0" aria-label="Tabela de produtos do carrinho - role region">
            <table class="hng-cart-table" role="table" aria-label="Tabela de produtos do carrinho">
                <thead>
                    <tr>
                        <th class="hng-product-remove">&nbsp;</th>
                        <th class="hng-product-thumbnail">&nbsp;</th>
                        <th class="hng-product-name"><?php esc_html_e('Produto', 'hng-commerce'); ?></th>
                        <th class="hng-product-price"><?php esc_html_e('Preço', 'hng-commerce'); ?></th>
                        <th class="hng-product-quantity"><?php esc_html_e('Quantidade', 'hng-commerce'); ?></th>
                        <th class="hng-product-subtotal"><?php esc_html_e('Subtotal', 'hng-commerce'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cart->get_cart() as $cart_id => $item): 
                        $product = $item['data'];
                        $product_id = $item['product_id'];
                        $quantity = $item['quantity'];
                        ?>
                        <tr class="hng-cart-item" data-cart-id="<?php echo esc_attr($cart_id); ?>">
                            <td class="hng-product-remove">
                                <a href="#" 
                                   class="hng-remove-from-cart" 
                                   data-cart-id="<?php echo esc_attr($cart_id); ?>"
                                   title="<?php echo esc_attr__( 'Remover', 'hng-commerce'); ?>"
                                   aria-label="<?php echo esc_attr__( 'Remover produto do carrinho', 'hng-commerce'); ?>">
                                    &times;
                                </a>
                            </td>
                            <td class="hng-product-thumbnail">
                                <?php 
                                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML image tag from trusted product method
                                echo $product->get_image('thumbnail'); 
                                ?>
                            </td>
                            <td class="hng-product-name">
                                <a href="<?php echo esc_url($product->get_permalink()); ?>">
                                    <?php echo esc_html($product->get_name()); ?>
                                </a>
                            </td>
                            <td class="hng-product-price">
                                <?php echo esc_html(hng_price($product->get_price())); ?>
                            </td>
                            <td class="hng-product-quantity">
                                <div class="hng-quantity">
                                    <input type="number" 
                                           name="cart[<?php echo esc_attr($cart_id); ?>][quantity]" 
                                           value="<?php echo esc_attr($quantity); ?>" 
                                           min="1" 
                                           step="1"
                                           class="hng-quantity-input"
                                           data-cart-id="<?php echo esc_attr($cart_id); ?>"
                                           aria-label="<?php echo esc_attr__( 'Quantidade', 'hng-commerce'); ?>" />
                                </div>
                            </td>
                            <td class="hng-product-subtotal">
                                <?php echo esc_html(hng_price($product->get_price() * $quantity)); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php do_action('hng_after_cart_table'); ?>
            <div class="hng-cart-actions">
                    <a href="<?php echo esc_url(hng_get_shop_url()); ?>" class="hng-button hng-button-secondary" tabindex="0" style="outline-offset:2px;">
                    <?php esc_html_e('Continuar Comprando', 'hng-commerce'); ?>
                </a>
                    <button type="submit" name="update_cart" class="hng-button hng-button-secondary" style="outline-offset:2px;">
                    <?php esc_html_e('Atualizar Carrinho', 'hng-commerce'); ?>
                </button>
            </div>
        </form>
        <div class="hng-cart-collaterals">
            <div class="hng-cart-coupon" role="region" aria-label="Cupom de desconto">
                <h3><?php esc_html_e('Cupom de Desconto', 'hng-commerce'); ?></h3>
                <?php if ($cart->has_coupon()): ?>
                    <div class="hng-applied-coupons">
                        <?php foreach ($cart->get_applied_coupons() as $code => $coupon): ?>
                            <div class="hng-coupon-item">
                                <span class="hng-coupon-code"><?php echo esc_html($code); ?></span>
                                <button type="button" 
                                        class="hng-remove-coupon" 
                                        data-coupon="<?php echo esc_attr($code); ?>"
                                        aria-label="<?php echo esc_attr__( 'Remover cupom', 'hng-commerce'); ?>">
                                    <?php esc_html_e('Remover', 'hng-commerce'); ?>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="hng-coupon-form">
                    <input type="text" 
                           name="coupon_code" 
                           class="hng-coupon-input" 
                           id="hng_coupon_code"
                           placeholder="<?php echo esc_attr__( 'Código do cupom', 'hng-commerce'); ?>"
                           aria-label="<?php echo esc_attr__( 'Código do cupom', 'hng-commerce'); ?>" />
                    <button type="button" 
                            class="hng-button hng-apply-coupon" 
                            id="hng_apply_coupon_btn"
                            aria-label="<?php echo esc_attr__( 'Aplicar cupom', 'hng-commerce'); ?>"
                            style="outline-offset:2px;">
                        <?php esc_html_e('Aplicar Cupom', 'hng-commerce'); ?>
                    </button>
                </div>
                <div class="hng-coupon-message" style="display:none;" aria-live="polite"></div>
            </div>
            <div class="hng-cart-totals" role="region" aria-label="Totais do carrinho">
                <h2><?php esc_html_e('Total do Carrinho', 'hng-commerce'); ?></h2>
                <table class="hng-totals-table" role="table" aria-label="Tabela de totais do carrinho">
                    <tr class="hng-cart-subtotal">
                        <th><?php esc_html_e('Subtotal', 'hng-commerce'); ?></th>
                        <td><?php echo esc_html(hng_price($cart->get_subtotal())); ?></td>
                    </tr>
                    <?php if ($cart->needs_shipping()): ?>
                        <tr class="hng-shipping">
                            <th><?php esc_html_e('Frete', 'hng-commerce'); ?></th>
                            <td>
                                <?php if ($cart->get_shipping_total() > 0): ?>
                                    <?php echo esc_html(hng_price($cart->get_shipping_total())); ?>
                                <?php else: ?>
                                    <?php esc_html_e('Calcular no checkout', 'hng-commerce'); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php if ($cart->get_discount_total() > 0): ?>
                        <tr class="hng-discount">
                            <th><?php esc_html_e('Desconto', 'hng-commerce'); ?></th>
                            <td>-<?php echo esc_html(hng_price($cart->get_discount_total())); ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr class="hng-order-total">
                            <th><?php esc_html_e('Total', 'hng-commerce'); ?></th>
                        <td><strong><?php echo esc_html(hng_price($cart->get_total())); ?></strong></td>
                    </tr>
                </table>
                <div class="hng-proceed-to-checkout">
                    <a href="<?php echo esc_url(hng_get_checkout_url()); ?>" class="hng-button hng-button-large" tabindex="0" style="outline-offset:2px;">
                        <?php esc_html_e('Finalizar Compra', 'hng-commerce'); ?>
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <?php
    /**
     * Hook: hng_after_cart
     */
    do_action('hng_after_cart');
    ?>
</div>
