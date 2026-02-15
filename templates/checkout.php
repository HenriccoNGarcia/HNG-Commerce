<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Template: Checkout
 * 
 * @package HNG_Commerce
 */

if (!defined('ABSPATH')) {
    exit;
}

$cart = hng_cart();

// Redirecionar se carrinho vazio
if ($cart->is_empty()) {
    wp_safe_redirect(hng_get_cart_url());
    exit;
}
?>

<div class="hng-checkout" role="region" aria-label="Checkout">
    <?php hng_print_notices(); ?>
    <?php do_action('hng_before_checkout_form'); ?>
    
    <?php
    // Verificar se login é requerido e usuário não está logado
    $settings = get_option('hng_commerce_settings', []);
    $require_login = ($settings['require_login_to_purchase'] ?? 'no') === 'yes';
    
    if ($require_login && !is_user_logged_in()):
        $login_url = wp_login_url(hng_get_checkout_url());
        $register_url = wp_registration_url();
        ?>
        <div class="hng-login-required-notice" style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 20px; margin-bottom: 20px; border-radius: 4px;">
            <h3 style="margin-top: 0; color: #856404;">
                <span style="font-size: 1.2em;">🔒</span> 
                <?php esc_html_e('Login Necessário', 'hng-commerce'); ?>
            </h3>
            <p style="margin: 10px 0; color: #856404;">
                <?php esc_html_e('Você precisa estar logado para finalizar sua compra. Faça login ou crie uma conta para continuar.', 'hng-commerce'); ?>
            </p>
            <p style="margin: 15px 0 0 0;">
                <a href="<?php echo esc_url($login_url); ?>" class="hng-button" style="display: inline-block; margin-right: 10px; padding: 10px 20px; background: #2d1810; color: white; text-decoration: none; border-radius: 4px;">
                    <?php esc_html_e('Fazer Login', 'hng-commerce'); ?>
                </a>
                <?php if (get_option('users_can_register')): ?>
                    <a href="<?php echo esc_url($register_url); ?>" class="hng-button-secondary" style="display: inline-block; padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px;">
                        <?php esc_html_e('Criar Conta', 'hng-commerce'); ?>
                    </a>
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>
    
    <form class="hng-checkout-form" method="post" action="" aria-label="Formulário de checkout">
        <?php wp_nonce_field('hng_checkout', 'hng_checkout_nonce'); ?>
        <div class="hng-checkout-content" tabindex="0" aria-label="Conteúdo do checkout - role region">
            <div class="hng-checkout-billing" role="region" aria-label="Dados de cobrança">
                <h3><?php esc_html_e('Dados de Cobrança', 'hng-commerce'); ?></h3>
                
                <p class="hng-form-row">
                    <label for="billing_first_name"><?php esc_html_e('Nome *', 'hng-commerce'); ?></label>
                    <input type="text" id="billing_first_name" name="billing_first_name" required aria-required="true" aria-label="Nome" />
                </p>
                
                <p class="hng-form-row">
                    <label for="billing_last_name"><?php esc_html_e('Sobrenome *', 'hng-commerce'); ?></label>
                    <input type="text" id="billing_last_name" name="billing_last_name" required aria-required="true" aria-label="Sobrenome" />
                </p>
                
                <p class="hng-form-row">
                    <label for="billing_email"><?php esc_html_e('E-mail *', 'hng-commerce'); ?></label>
                    <input type="email" id="billing_email" name="billing_email" required aria-required="true" aria-label="E-mail" />
                </p>
                
                <p class="hng-form-row">
                    <label for="billing_phone"><?php esc_html_e('Telefone *', 'hng-commerce'); ?></label>
                    <input type="tel" id="billing_phone" name="billing_phone" required aria-required="true" aria-label="Telefone" />
                </p>
                
                <p class="hng-form-row">
                    <label for="billing_cpf"><?php esc_html_e('CPF/CNPJ *', 'hng-commerce'); ?></label>
                    <input type="text" id="billing_cpf" name="billing_cpf" required aria-required="true" aria-label="CPF ou CNPJ" />
                </p>
                
                <p class="hng-form-row">
                    <label for="billing_postcode"><?php esc_html_e('CEP *', 'hng-commerce'); ?></label>
                    <input type="text" id="billing_postcode" name="billing_postcode" class="hng-postcode-input" required aria-required="true" aria-label="CEP" />
                    <button type="button" class="hng-button-secondary hng-find-address" aria-label="<?php echo esc_attr__( 'Buscar endereço pelo CEP', 'hng-commerce'); ?>"><?php esc_html_e('Buscar', 'hng-commerce'); ?></button>
                </p>
                
                <p class="hng-form-row">
                    <label for="billing_address_1"><?php esc_html_e('Endereço *', 'hng-commerce'); ?></label>
                    <input type="text" id="billing_address_1" name="billing_address_1" required />
                </p>
                
                <p class="hng-form-row hng-form-row-half">
                    <label for="billing_number"><?php esc_html_e('Número *', 'hng-commerce'); ?></label>
                    <input type="text" id="billing_number" name="billing_number" required />
                </p>
                
                <p class="hng-form-row hng-form-row-half">
                    <label for="billing_address_2"><?php esc_html_e('Complemento', 'hng-commerce'); ?></label>
                    <input type="text" id="billing_address_2" name="billing_address_2" />
                </p>
                
                <p class="hng-form-row">
                    <label for="billing_neighborhood"><?php esc_html_e('Bairro *', 'hng-commerce'); ?></label>
                    <input type="text" id="billing_neighborhood" name="billing_neighborhood" required />
                </p>
                
                <p class="hng-form-row hng-form-row-half">
                    <label for="billing_city"><?php esc_html_e('Cidade *', 'hng-commerce'); ?></label>
                    <input type="text" id="billing_city" name="billing_city" required />
                </p>
                
                <p class="hng-form-row hng-form-row-half">
                    <label for="billing_state"><?php esc_html_e('Estado *', 'hng-commerce'); ?></label>
                    <select id="billing_state" name="billing_state" required>
                        <option value="">Selecione...</option>
                        <option value="AC">Acre</option>
                        <option value="AL">Alagoas</option>
                        <option value="AP">Amapá</option>
                        <option value="AM">Amazonas</option>
                        <option value="BA">Bahia</option>
                        <option value="CE">Ceará</option>
                        <option value="DF">Distrito Federal</option>
                        <option value="ES">Espírito Santo</option>
                        <option value="GO">Goiás</option>
                        <option value="MA">Maranhão</option>
                        <option value="MT">Mato Grosso</option>
                        <option value="MS">Mato Grosso do Sul</option>
                        <option value="MG">Minas Gerais</option>
                        <option value="PA">Pará</option>
                        <option value="PB">Paraíba</option>
                        <option value="PR">Paraná</option>
                        <option value="PE">Pernambuco</option>
                        <option value="PI">Piauí</option>
                        <option value="RJ">Rio de Janeiro</option>
                        <option value="RN">Rio Grande do Norte</option>
                        <option value="RS">Rio Grande do Sul</option>
                        <option value="RO">Rondônia</option>
                        <option value="RR">Roraima</option>
                        <option value="SC">Santa Catarina</option>
                        <option value="SP">São Paulo</option>
                        <option value="SE">Sergipe</option>
                        <option value="TO">Tocantins</option>
                    </select>
                </p>
                
                <?php if ($cart->needs_shipping()): ?>
                    <h3><?php esc_html_e('Frete', 'hng-commerce'); ?></h3>
                    
                    <div class="hng-shipping-methods" aria-live="polite">
                        <p class="hng-loading"><?php esc_html_e('Calculando frete...', 'hng-commerce'); ?></p>
                    </div>
                <?php endif; ?>
                
                <h3><?php esc_html_e('Informações Adicionais', 'hng-commerce'); ?></h3>
                                <?php
                                // Exibir campos personalizados do cliente agrupados por produto no carrinho
                                foreach ($cart->get_cart() as $cart_id => $item) {
                                    $product = $item['data'];
                                    $custom_fields = $item['custom_fields'] ?? [];
                                    $product_custom_fields = get_post_meta($product->get_id(), '_hng_custom_fields', true);
                                    if (is_array($product_custom_fields)) {
                                        $fields_cliente = array_filter($product_custom_fields, function($f){ return ($f['role'] ?? '') === 'cliente'; });
                                        if (!empty($fields_cliente)) {
                                            echo '<div class="hng-checkout-custom-fields" style="margin-bottom:20px;">';
                                            echo '<strong>' . esc_html($product->get_name()) . '</strong>';
                                            foreach ($fields_cliente as $field) {
                                                $slug = esc_attr($field['slug']);
                                                $label = esc_html($field['label']);
                                                $type = $field['type'] ?? 'text';
                                                $options = isset($field['options']) ? array_map('trim', explode(',', $field['options'])) : [];
                                                $value = $custom_fields[$slug] ?? '';
                                                echo '<div class="hng-custom-field hng-custom-field-' . esc_attr($type) . '">';
                                                echo '<label for="hng_cf_checkout_' . esc_attr($cart_id) . '_' . esc_attr($slug) . '">' . esc_html($label) . '</label>';
                                                switch ($type) {
                                                    case 'textarea':
                                                        echo '<textarea name="hng_cf_checkout[' . esc_attr($cart_id) . '][' . esc_attr($slug) . ']" id="hng_cf_checkout_' . esc_attr($cart_id) . '_' . esc_attr($slug) . '" rows="3">' . esc_textarea($value) . '</textarea>';
                                                        break;
                                                    case 'select':
                                                        echo '<select name="hng_cf_checkout[' . esc_attr($cart_id) . '][' . esc_attr($slug) . ']" id="hng_cf_checkout_' . esc_attr($cart_id) . '_' . esc_attr($slug) . '">';
                                                        echo '<option value="">' . esc_html__( 'Selecione', 'hng-commerce') . '</option>';
                                                        foreach ($options as $opt) echo '<option value="' . esc_attr($opt) . '"' . selected($value, $opt, false) . '>' . esc_html($opt) . '</option>';
                                                        echo '</select>';
                                                        break;
                                                    case 'radio':
                                                        foreach ($options as $opt) {
                                                            echo '<label style="margin-right:10px;"><input type="radio" name="hng_cf_checkout[' . esc_attr($cart_id) . '][' . esc_attr($slug) . ']" value="' . esc_attr($opt) . '"' . checked($value, $opt, false) . '> ' . esc_html($opt) . '</label>';
                                                        }
                                                        break;
                                                    case 'checkbox':
                                                        foreach ($options as $opt) {
                                                            $checked = is_array($value) && in_array($opt, $value) ? 'checked' : '';
                                                            echo '<label style="margin-right:10px;"><input type="checkbox" name="hng_cf_checkout[' . esc_attr($cart_id) . '][' . esc_attr($slug) . '][]" value="' . esc_attr($opt) . '" ' . esc_attr($checked) . '> ' . esc_html($opt) . '</label>';
                                                        }
                                                        break;
                                                    case 'number':
                                                        echo '<input type="number" name="hng_cf_checkout[' . esc_attr($cart_id) . '][' . esc_attr($slug) . ']" id="hng_cf_checkout_' . esc_attr($cart_id) . '_' . esc_attr($slug) . '" value="' . esc_attr($value) . '" />';
                                                        break;
                                                    case 'date':
                                                        echo '<input type="date" name="hng_cf_checkout[' . esc_attr($cart_id) . '][' . esc_attr($slug) . ']" id="hng_cf_checkout_' . esc_attr($cart_id) . '_' . esc_attr($slug) . '" value="' . esc_attr($value) . '" />';
                                                        break;
                                                    case 'dimension':
                                                        echo '<input type="text" name="hng_cf_checkout[' . esc_attr($cart_id) . '][' . esc_attr($slug) . ']" id="hng_cf_checkout_' . esc_attr($cart_id) . '_' . esc_attr($slug) . '" value="' . esc_attr($value) . '" placeholder="Ex: 10x20cm" />';
                                                        break;
                                                    default:
                                                        echo '<input type="text" name="hng_cf_checkout[' . esc_attr($cart_id) . '][' . esc_attr($slug) . ']" id="hng_cf_checkout_' . esc_attr($cart_id) . '_' . esc_attr($slug) . '" value="' . esc_attr($value) . '" />';
                                                }
                                                echo '</div>';
                                            }
                                            echo '</div>';
                                        }
                                    }
                                }
                                ?>

                                <p class="hng-form-row">
                                    <label for="order_comments"><?php esc_html_e('Observações do pedido (opcional)', 'hng-commerce'); ?></label>
                                    <textarea id="order_comments" name="order_comments" rows="3"></textarea>
                                </p>
            </div>
            
            <div class="hng-checkout-sidebar" role="region" aria-label="Resumo do pedido">
                <div class="hng-order-review">
                    <h3><?php esc_html_e('Seu Pedido', 'hng-commerce'); ?></h3>
                    <?php do_action('hng_before_order_review'); ?>
                    <div class="hng-review-order-table-responsive" tabindex="0" aria-label="Tabela de resumo do pedido - role region">
                    <table class="hng-review-order-table" role="table" aria-label="Tabela de resumo do pedido">
                        <thead>
                            <tr>
                                <th class="hng-product-name"><?php esc_html_e('Produto', 'hng-commerce'); ?></th>
                                <th class="hng-product-total"><?php esc_html_e('Subtotal', 'hng-commerce'); ?></th>
                            </tr>
                        </thead>
                        
                        <tbody>
                            <?php foreach ($cart->get_cart() as $key => $item): 
                                $product = $item['data'];
                                $quantity = $item['quantity'];
                                ?>
                                <tr class="hng-cart-item">
                                    <td class="hng-product-name">
                                        <div style="display:flex; align-items:center; gap:10px;">
                                            <?php echo esc_html($product->get_name()); ?>
                                            <a href="#" class="hng-remove-from-cart" data-cart-id="<?php echo esc_attr($key); ?>" style="color: #ef4444; font-size: 1.2em; text-decoration: none;" aria-label="<?php esc_attr_e('Remover item', 'hng-commerce'); ?>">&times;</a>
                                        </div>
                                        <div class="hng-quantity" style="margin-top:5px;">
                                            <input type="number" 
                                                   class="hng-quantity-input" 
                                                   data-cart-id="<?php echo esc_attr($key); ?>" 
                                                   value="<?php echo esc_attr($quantity); ?>" 
                                                   min="1" 
                                                   step="1" 
                                                   style="width: 60px; padding: 5px; border: 1px solid #ddd; border-radius: 4px;"
                                                   aria-label="<?php esc_attr_e('Quantidade', 'hng-commerce'); ?>" />
                                        </div>
                                    </td>
                                    <td class="hng-product-total hng-product-subtotal">
                                        <?php echo esc_html(hng_price($product->get_price() * $quantity)); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        
                        <tfoot>
                            <tr class="hng-cart-subtotal">
                                <th><?php esc_html_e('Subtotal', 'hng-commerce'); ?></th>
                                <td><?php echo esc_html(hng_price($cart->get_subtotal())); ?></td>
                            </tr>
                            
                            <?php if ($cart->needs_shipping()): ?>
                                <tr class="hng-shipping">
                                    <th><?php esc_html_e('Frete', 'hng-commerce'); ?></th>
                                    <td class="hng-shipping-total">
                                        <?php if ($cart->get_shipping_total() > 0): ?>
                                            <?php echo esc_html(hng_price($cart->get_shipping_total())); ?>
                                        <?php else: ?>
                                            <em><?php esc_html_e('A calcular', 'hng-commerce'); ?></em>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            
                            <tr class="hng-order-total">
                                <th><?php esc_html_e('Total', 'hng-commerce'); ?></th>
                                <td><strong><?php echo esc_html( hng_price( $cart->get_total() ) ); ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                    </div>
                    
                    <div class="hng-payment-methods" role="region" aria-label="<?php echo esc_attr__( 'Métodos de pagamento', 'hng-commerce'); ?>">
                        <h3><?php esc_html_e('Pagamento', 'hng-commerce'); ?></h3>
                        <?php 
                        $methods = hng_get_active_gateway_methods();
                        if (empty($methods)) {
                            echo '<p class="hng-error">'.esc_html__('Nenhum método de pagamento disponível. Contate o administrador.', 'hng-commerce').'</p>';
                        } else {
                            echo '<ul class="hng-payment-list">';
                            $first = true;
                            foreach ($methods as $method) {
                                $id = 'payment_'.$method;
                                $title = hng_get_payment_method_title($method);
                                $desc = '';
                                switch ($method) {
                                    case 'pix': $desc = esc_html__('Pagamento instantâneo via PIX', 'hng-commerce'); break;
                                    case 'credit_card': $desc = esc_html__('Pagamento seguro com cartão', 'hng-commerce'); break;
                                    case 'boleto': $desc = esc_html__('Vencimento em 3 dias úteis', 'hng-commerce'); break;
                                    default: $desc = esc_html__('Método de pagamento', 'hng-commerce');
                                }
                                echo '<li>'; 
                                echo '<input type="radio" id="'.esc_attr($id).'" name="payment_method" value="'.esc_attr($method).'" '.($first?'checked':'').' />';
                                echo '<label for="'.esc_attr($id).'"><strong>'.esc_html($title).'</strong><span>'.esc_html($desc).'</span></label>';
                                // Campos específicos (placeholder)
                                if ($method === 'credit_card') {
                                    echo '<div class="hng-payment-fields hng-payment-fields-credit" style="display:none">';
                                    echo '<p><label>'.esc_html__('Número do Cartão', 'hng-commerce').'<br/><input type="text" name="cc_number" autocomplete="off" /></label></p>';
                                    echo '<p class="hng-form-row-half"><label>'.esc_html__('Validade (MM/AA)', 'hng-commerce').'<br/><input type="text" name="cc_expiry" autocomplete="off" /></label></p>';
                                    echo '<p class="hng-form-row-half"><label>'.esc_html__('CVV', 'hng-commerce').'<br/><input type="text" name="cc_cvv" autocomplete="off" /></label></p>';
                                    echo '<p><label>'.esc_html__('Nome no Cartão', 'hng-commerce').'<br/><input type="text" name="cc_holder" autocomplete="off" /></label></p>';
                                    echo '</div>';
                                } elseif ($method === 'boleto') {
                                    echo '<div class="hng-payment-fields hng-payment-fields-boleto" style="display:none">';
                                    echo '<p>'.esc_html__('Após finalizar você receberá o boleto para pagamento.', 'hng-commerce').'</p>';
                                    echo '</div>';
                                } elseif ($method === 'pix') {
                                    echo '<div class="hng-payment-fields hng-payment-fields-pix" style="display:none">';
                                    echo '<p>'.esc_html__('QR Code gerado após confirmar o pedido.', 'hng-commerce').'</p>';
                                    echo '</div>';
                                }
                                echo '</li>';
                                $first = false;
                            }
                            echo '</ul>';
                        }
                        ?>
                    </div>
                    
                    <div class="hng-terms">
                        <label>
                            <input type="checkbox" name="terms" required aria-required="true" aria-label="<?php echo esc_attr__( 'Aceito os termos e condições', 'hng-commerce'); ?>" />
                            <?php esc_html_e('Li e concordo com os', 'hng-commerce'); ?>
                            <a href="#"><?php esc_html_e('termos e condições', 'hng-commerce'); ?></a> *
                        </label>
                    </div>
                    
                    <button type="submit" name="hng_place_order" class="hng-button hng-button-large hng-place-order" aria-label="<?php echo esc_attr__( 'Finalizar Pedido', 'hng-commerce'); ?>" style="outline-offset:2px;" <?php echo ($require_login && !is_user_logged_in()) ? 'disabled' : ''; ?>>
                        <?php esc_html_e('Finalizar Pedido', 'hng-commerce'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php do_action('hng_after_checkout_form'); ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Buscar endereço por CEP
    $('.hng-find-address').on('click', function(e) {
        e.preventDefault();
        
        var cep = $('#billing_postcode').val().replace(/\D/g, '');
        
        if (cep.length !== 8) {
            alert(<?php echo wp_json_encode( __( 'Digite um CEP válido.', 'hng-commerce') ); ?>);
            return;
        }
        
        // Consultar ViaCEP
        $.getJSON('https://viacep.com.br/ws/' + cep + '/json/', function(data) {
            if (!data.erro) {
                $('#billing_address_1').val(data.logradouro);
                $('#billing_neighborhood').val(data.bairro);
                $('#billing_city').val(data.localidade);
                $('#billing_state').val(data.uf);
                $('#billing_number').focus();
            } else {
                alert(<?php echo wp_json_encode( __( 'CEP não encontrado.', 'hng-commerce') ); ?>);
            }
        }).fail(function() {
            alert(<?php echo wp_json_encode( __( 'Erro ao buscar CEP.', 'hng-commerce') ); ?>);
        });
    });
    
    // Calcular frete quando CEP preenchido
    $('#billing_postcode').on('blur', function() {
        var cep = $(this).val().replace(/\D/g, '');
        
        if (cep.length === 8) {
            $('.hng-shipping-methods').html(<?php echo wp_json_encode( '<p class="hng-loading">' . __( 'Calculando frete...', 'hng-commerce') . '</p>' ); ?>);
            
            $.ajax({
                url: <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
                type: 'POST',
                data: {
                    action: 'hng_calculate_shipping',
                    nonce: <?php echo wp_json_encode( wp_create_nonce( 'hng_cart_actions' ) ); ?>,
                    postcode: cep
                },
                success: function(response) {
                    if (response.success) {
                        var html = '<ul class="hng-shipping-list">';
                        $.each(response.data.methods, function(i, method) {
                            html += '<li>';
                            html += '<input type="radio" id="shipping_' + method.id + '" name="shipping_method" value="' + method.id + '" ' + (i === 0 ? 'checked' : '') + ' data-cost="' + method.cost + '" />';
                            html += '<label for="shipping_' + method.id + '">';
                            var label = method.label || method.name || method.service || '';
                            var eta = method.delivery_time_text || method.delivery_time || method.delivery_time_label || '';
                            html += '<strong>' + label + '</strong> - R$ ' + method.cost.toFixed(2).replace('.', ',');
                            html += '<span>' + eta + '</span>';
                            html += '</label>';
                            html += '</li>';
                        });
                        html += '</ul>';
                        
                        $('.hng-shipping-methods').html(html);
                        
                        // Atualizar total ao mudar método
                        $('input[name="shipping_method"]').on('change', updateTotal);
                        updateTotal();
                    } else {
                        $('.hng-shipping-methods').html('<p class="hng-error">' + response.data.message + '</p>');
                    }
                },
                error: function() {
                    $('.hng-shipping-methods').html(<?php echo wp_json_encode( '<p class="hng-error">' . __( 'Erro ao calcular frete.', 'hng-commerce') . '</p>' ); ?>);
                }
            });
        }
    });
    
    // Atualizar total
    function updateTotal() {
        var shippingCost = parseFloat($('input[name="shipping_method"]:checked').data('cost')) || 0;
        var subtotal = <?php echo wp_json_encode( (float) $cart->get_subtotal() ); ?>;
        var total = subtotal + shippingCost;
        
        $('.hng-shipping-total').html('R$ ' + shippingCost.toFixed(2).replace('.', ','));
        $('.hng-order-total td strong').html('R$ ' + total.toFixed(2).replace('.', ','));
    }
});
</script>
