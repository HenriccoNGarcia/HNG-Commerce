<?php
/**
 * Handlers AJAX
 * 
 * @package HNG_Commerce
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Commerce_Ajax {
    
    /**
     * Adicionar ao carrinho (AJAX)
     */
    public static function add_to_cart() {
        // Log da requisição ANTES da verificação do nonce
        error_log('HNG: add_to_cart AJAX called');
        error_log('HNG: POST data: ' . print_r($_POST, true));
        error_log('HNG: nonce received: ' . (isset($_POST['nonce']) ? $_POST['nonce'] : 'NONE'));
        
        // Verificar nonce
        $nonce_check = wp_verify_nonce($_POST['nonce'] ?? '', 'hng_add_to_cart');
        if (!$nonce_check) {
            error_log('HNG: Nonce verification FAILED!');
            wp_send_json_error(array('message' => 'Nonce inválido'));
            return;
        }
        error_log('HNG: Nonce verification passed!');
        
        $post = wp_unslash( $_POST );
        $product_id = isset($post['product_id']) ? absint( $post['product_id'] ) : 0;
        $quantity = isset($post['quantity']) ? absint( $post['quantity'] ) : 1;
        $variation_id = isset($post['variation_id']) ? absint( $post['variation_id'] ) : 0;
        
        if (!$product_id) {
            wp_send_json_error(array(
                'message' => __('Produto inválido.', 'hng-commerce')
            ));
        }
        
        // Verificar se produto existe
        $product = get_post($product_id);
        if (!$product || !isset($product->post_type) || $product->post_type !== 'hng_product') {
            wp_send_json_error(array(
                'message' => __('Produto não encontrado.', 'hng-commerce')
            ));
        }
        
        // Adicionar ao carrinho
        $cart = HNG_Commerce()->cart();
        $added = $cart->add_to_cart($product_id, $quantity, $variation_id);
        
        if ($added) {
            // Forçar salvamento do carrinho na sessão ANTES de retornar o JSON
            $cart->save_cart();
            
            wp_send_json_success(array(
                'message' => __('Produto adicionado ao carrinho!', 'hng-commerce'),
                'cart_count' => $cart->get_cart_contents_count(),
                'cart_total' => $cart->get_total()
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Não foi possível adicionar o produto ao carrinho.', 'hng-commerce')
            ));
        }
    }
    
    /**
     * Atualizar carrinho (AJAX)
     */
    public static function update_cart() {
        check_ajax_referer('HNG Commerce', 'nonce');
        
        // Implementar lógica de atualização
        wp_send_json_success();
    }
    
    /**
     * Remover item do carrinho (AJAX)
     */
    public static function remove_from_cart() {
        check_ajax_referer('hng_cart_actions', 'nonce', true);
        
        $post = wp_unslash( $_POST );
        $item_key = isset($post['item_key']) ? sanitize_text_field( $post['item_key'] ) : '';
        
        if (!$item_key) {
            wp_send_json_error(array(
                'message' => __('Item inválido.', 'hng-commerce')
            ));
        }
        
        $cart = HNG_Commerce()->cart();
        $removed = $cart->remove_cart_item($item_key);
        
        if ($removed) {
            // Forçar salvamento do carrinho na sessão ANTES de retornar o JSON
            $cart->save_cart();
            
            wp_send_json_success(array(
                'message' => __('Item removido do carrinho.', 'hng-commerce'),
                'cart_count' => $cart->get_cart_contents_count(),
                'cart_total' => $cart->get_total()
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Não foi possível remover o item.', 'hng-commerce')
            ));
        }
    }
    
    /**
     * Aplicar cupom (AJAX)
     */
    public static function apply_coupon() {
        check_ajax_referer('HNG Commerce', 'nonce');
        
        $post = wp_unslash( $_POST );
        $code = isset($post['coupon_code']) ? sanitize_text_field( $post['coupon_code'] ) : '';
        
        if (!$code) {
            wp_send_json_error(array(
                'message' => __('Digite um código de cupom.', 'hng-commerce')
            ));
        }
        
        // Implementar lógica de cupom
        wp_send_json_success(array(
            'message' => __('Cupom aplicado com sucesso!', 'hng-commerce')
        ));
    }
    
    /**
     * Calcular frete (AJAX)
     */
    public static function calculate_shipping() {
        check_ajax_referer('HNG Commerce', 'nonce');
        
        $post = wp_unslash( $_POST );
        $postcode = isset($post['postcode']) ? sanitize_text_field( $post['postcode'] ) : '';
        
        if (!$postcode) {
            wp_send_json_error(array(
                'message' => __('Digite um CEP válido.', 'hng-commerce')
            ));
        }
        
        // Implementar cálculo de frete
        wp_send_json_success(array(
            'shipping_options' => array(
                array(
                    'id' => 'pac',
                    'name' => 'PAC - Correios',
                    'price' => 15.50,
                    'days' => '5-7 dias úteis'
                ),
                array(
                    'id' => 'sedex',
                    'name' => 'SEDEX - Correios',
                    'price' => 25.90,
                    'days' => '2-3 dias úteis'
                )
            )
        ));
    }
}
