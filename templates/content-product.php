<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Template: Loop de Produtos
 * 
 * @package HNG_Commerce
 */

if (!defined('ABSPATH')) {
    exit;
}

global $post;
$product = hng_get_product($post->ID);

if (!$product->get_id()) {
    return;
}
?>

<div class="hng-product" role="region" aria-label="Produto">
    <?php do_action('hng_before_content_product', $product); ?>
    <a href="<?php echo esc_url($product->get_permalink()); ?>" class="hng-product-link" aria-label="<?php echo esc_attr($product->get_name()); ?>">
        <div class="hng-product-image" role="img" aria-label="<?php echo esc_attr($product->get_name()); ?>">
            <?php echo wp_kses_post($product->get_image('medium')); ?>
            
            <?php if ($product->is_on_sale()): ?>
                <span class="hng-badge hng-badge-sale"><?php esc_html_e( 'Promoção', 'hng-commerce'); ?></span>
            <?php endif; ?>
            
            <?php if ($product->is_featured()): ?>
                <span class="hng-badge hng-badge-featured"><?php esc_html_e( 'Destaque', 'hng-commerce'); ?></span>
            <?php endif; ?>
            
            <?php if ( ! $product->is_in_stock() ): ?>
                <span class="hng-badge hng-badge-out-stock"><?php esc_html_e( 'Esgotado', 'hng-commerce'); ?></span>
            <?php endif; ?>
        </div>
        
        <div class="hng-product-info">
            <h3 class="hng-product-title" aria-label="<?php echo esc_attr($product->get_name()); ?>"><?php echo esc_html($product->get_name()); ?></h3>
            
            <?php if ( $product->get_rating_count() > 0 ): ?>
                <div class="hng-product-rating">
                    <?php $rating_width = ( (float) $product->get_average_rating() / 5 ) * 100; ?>
                    <div class="hng-star-rating" style="width: <?php echo esc_attr( $rating_width ); ?>%">
                        <span><?php echo esc_html( number_format_i18n( (float) $product->get_average_rating(), 1 ) ); ?></span>
                    </div>
                    <span class="hng-rating-count">(<?php echo esc_html( intval( $product->get_rating_count() ) ); ?>)</span>
                </div>
            <?php endif; ?>
            
            <div class="hng-product-price">
                <?php echo wp_kses_post( $product->get_price_html() ); ?>
            </div>
        </div>
    </a>
    
    <?php if ($product->is_purchasable()): ?>
        <div class="hng-product-actions" role="group" aria-label="Ações do produto">
            <form class="hng-add-to-cart-form" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: product name */ 'Adicionar %s ao carrinho', $product->get_name() ) ); ?>">
                <?php wp_nonce_field('hng_add_to_cart', 'hng_add_to_cart_nonce'); ?>
                <input type="hidden" name="product_id" value="<?php echo esc_attr( $product->get_id() ); ?>" />
                <input type="hidden" name="quantity" value="1" />
                
                <button type="submit" class="hng-button hng-add-to-cart-button" aria-label="<?php echo esc_attr__( 'Adicionar ao Carrinho', 'hng-commerce'); ?>">
                    <?php esc_html_e( 'Adicionar ao Carrinho', 'hng-commerce'); ?>
                </button>
            </form>
        </div>
    <?php endif; ?>
    <?php do_action('hng_after_content_product', $product); ?>
</div>
