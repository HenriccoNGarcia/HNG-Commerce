/**

 * HNG Commerce - Frontend JavaScript

 */



(function ($) {

    'use strict';



    var HNG_Commerce = {



        init: function () {

            this.addToCart();

            this.removeFromCart();

            this.updateCartQuantity();

            this.applyCoupon();

            this.removeCoupon();

        },



        /**

         * Adicionar ao carrinho via AJAX

         */

        addToCart: function () {

            $(document).on('submit', '.hng-add-to-cart-form', function (e) {

                e.preventDefault();



                var $form = $(this);

                var $button = $form.find('.hng-add-to-cart-button, .add-to-cart-btn, #add-to-cart-btn, button[type="submit"]');

                var buttonText = $button.text();



                // Desabilitar botão

                $button.prop('disabled', true).text('Adicionando...');



                // Build form data

                var formData = new FormData($form[0]);

                formData.set('action', 'hng_add_to_cart');



                // Use the correct nonce

                var nonce = hng_ajax.add_to_cart_nonce || $form.find('[name="hng_cart_nonce"]').val() || '';

                formData.set('nonce', nonce);

                
                console.log('HNG: Adding to cart', {
                    nonce: nonce,
                    product_id: formData.get('product_id'),
                    quantity: formData.get('quantity')
                });


                $.ajax({

                    url: hng_ajax.ajax_url,

                    type: 'POST',

                    data: formData,

                    processData: false,

                    contentType: false,

                    success: function (response) {

                        if (response.success) {

                            $('.hng-cart-count, .cart-count').text(response.data.cart_count);



                            // Check if should redirect to checkout

                            if (hng_ajax.redirect_to_checkout) {

                                window.location.href = hng_ajax.checkout_url;

                                return;

                            }



                            HNG_Commerce.showNotice(response.data.message, 'success');

                            $button.prop('disabled', false).text('Adicionado!');

                            setTimeout(function () { $button.text(buttonText); }, 2000);

                        } else {

                            HNG_Commerce.showNotice(response.data.message || 'Erro ao adicionar produto.', 'error');

                            $button.prop('disabled', false).text(buttonText);

                        }

                    },

                    error: function () {

                        HNG_Commerce.showNotice('Erro ao adicionar produto.', 'error');

                        $button.prop('disabled', false).text(buttonText);

                    }

                });

            });

        },



        // Fallback REST para add_to_cart quando admin-ajax é bloqueado (WAF/modsec)

        restAddToCartFallback: function ($form, $button, buttonText) {

            var payload = {

                product_id: $form.find('input[name="product_id"]').val(),

                quantity: $form.find('input[name="quantity"]').val(),

                variation_id: $form.find('input[name="variation_id"]').val() || 0,

            };



            var restUrl = (window.location.origin || '') + '/wp-json/hng/v1/add-to-cart';



            $.ajax({

                url: restUrl,

                type: 'POST',

                contentType: 'application/json',

                data: JSON.stringify(payload),

                success: function (response) {

                    if (response && !response.data) {

                        // WP REST pode retornar os dados diretamente

                        response = { success: true, data: response };

                    }



                    if (response.success) {

                        $('.hng-cart-count').text(response.data.cart_count);

                        HNG_Commerce.showNotice(response.data.message, 'success');

                        $button.prop('disabled', false).text('Adicionado!');

                        setTimeout(function () { $button.text(buttonText); }, 2000);

                    } else {

                        HNG_Commerce.showNotice(response.data.message || 'Erro ao adicionar produto.', 'error');

                        $button.prop('disabled', false).text(buttonText);

                    }

                },

                error: function () {

                    HNG_Commerce.showNotice('Erro ao adicionar produto (REST).', 'error');

                    $button.prop('disabled', false).text(buttonText);

                }

            });

        },



        /**

         * Remover do carrinho via AJAX

         */

        removeFromCart: function () {

            $(document).on('click', '.hng-remove-from-cart', function (e) {

                e.preventDefault();



                var $button = $(this);

                var cartId = $button.data('cart-id');



                if (!confirm('Deseja remover este produto?')) {

                    return;

                }



                $.ajax({

                    url: hng_ajax.ajax_url,

                    type: 'POST',

                    data: {

                        action: 'hng_remove_from_cart',

                        nonce: hng_ajax.cart_nonce,

                        cart_id: cartId

                    },

                    success: function (response) {

                        if (response.success) {

                            // Remover linha da tabela

                            $button.closest('.hng-cart-item').fadeOut(300, function () {

                                $(this).remove();



                                // Atualizar totais

                                $('.hng-cart-subtotal td').text(response.data.cart_subtotal);

                                $('.hng-cart-subtotal').data('subtotal', parseFloat(String(response.data.cart_subtotal_raw || 0).replace(',', '.')));

                                $('.hng-order-total td strong').text(response.data.cart_total);

                                $('.hng-cart-count').text(response.data.cart_count);



                                // Se estiver no checkout e existir a função de atualizar total

                                if (typeof window.hngUpdateCheckoutTotal === 'function') {

                                    window.hngUpdateCheckoutTotal();

                                }



                                // Se carrinho vazio, recarregar página

                                if (response.data.cart_count === 0) {

                                    location.reload();

                                }

                            });



                            HNG_Commerce.showNotice(response.data.message, 'success');

                        } else {

                            HNG_Commerce.showNotice(response.data.message, 'error');

                        }

                    },

                    error: function () {

                        HNG_Commerce.showNotice('Erro ao remover produto.', 'error');

                    }

                });

            });

        },



        /**

         * Atualizar quantidade via AJAX

         */

        updateCartQuantity: function () {

            var timeout;



            $(document).on('change', '.hng-quantity-input', function () {

                var $input = $(this);

                var cartId = $input.data('cart-id');

                var quantity = parseInt($input.val());



                if (quantity < 1) {

                    $input.val(1);

                    return;

                }



                clearTimeout(timeout);



                timeout = setTimeout(function () {

                    $.ajax({

                        url: hng_ajax.ajax_url,

                        type: 'POST',

                        data: {

                            action: 'hng_update_cart_quantity',

                            nonce: hng_ajax.cart_nonce,

                            cart_id: cartId,

                            quantity: quantity

                        },

                        success: function (response) {

                            if (response.success) {

                                // Atualizar subtotal do item

                                $input.closest('.hng-cart-item')

                                    .find('.hng-product-subtotal')

                                    .text(response.data.item_subtotal);



                                // Atualizar totais

                                $('.hng-cart-subtotal td').text(response.data.cart_subtotal);

                                $('.hng-cart-subtotal').data('subtotal', parseFloat(String(response.data.cart_subtotal_raw || 0).replace(',', '.')));

                                $('.hng-order-total td strong').text(response.data.cart_total);

                                $('.hng-cart-count').text(response.data.cart_count);



                                // Se estiver no checkout e existir a função de atualizar total

                                if (typeof window.hngUpdateCheckoutTotal === 'function') {

                                    window.hngUpdateCheckoutTotal();

                                }



                                HNG_Commerce.showNotice(response.data.message, 'success');

                            } else {

                                HNG_Commerce.showNotice(response.data.message, 'error');

                            }

                        },

                        error: function () {

                            HNG_Commerce.showNotice('Erro ao atualizar quantidade.', 'error');

                        }

                    });

                }, 500);

            });

        },



        /**

         * Aplicar cupom

         */

        applyCoupon: function () {

            $(document).on('click', '#hng_apply_coupon_btn', function (e) {

                e.preventDefault();



                var $button = $(this);

                var $input = $('#hng_coupon_code');

                var code = $input.val().trim();

                var $message = $('.hng-coupon-message');



                if (!code) {

                    $message.removeClass('hng-success').addClass('hng-error')

                        .text('Digite um código de cupom.').show();

                    return;

                }



                $button.prop('disabled', true).text('Aplicando...');

                $message.hide();



                $.ajax({

                    url: hng_ajax.ajax_url,

                    type: 'POST',

                    data: {

                        action: 'hng_apply_coupon',

                        nonce: hng_ajax.cart_nonce,

                        coupon_code: code

                    },

                    success: function (response) {

                        if (response.success) {

                            $message.removeClass('hng-error').addClass('hng-success')

                                .text(response.data.message).show();



                            // Recarregar página após 1 segundo

                            setTimeout(function () {

                                location.reload();

                            }, 1000);

                        } else {

                            $message.removeClass('hng-success').addClass('hng-error')

                                .text(response.data.message).show();

                            $button.prop('disabled', false).text('Aplicar Cupom');

                        }

                    },

                    error: function () {

                        $message.removeClass('hng-success').addClass('hng-error')

                            .text('Erro ao aplicar cupom.').show();

                        $button.prop('disabled', false).text('Aplicar Cupom');

                    }

                });

            });



            // Aplicar ao pressionar Enter

            $(document).on('keypress', '#hng_coupon_code', function (e) {

                if (e.which === 13) {

                    e.preventDefault();

                    $('#hng_apply_coupon_btn').trigger('click');

                }

            });

        },



        /**

         * Remover cupom

         */

        removeCoupon: function () {

            $(document).on('click', '.hng-remove-coupon', function (e) {

                e.preventDefault();



                var $button = $(this);

                var code = $button.data('coupon');



                if (!confirm('Deseja remover este cupom?')) {

                    return;

                }



                $button.prop('disabled', true).text('Removendo...');



                $.ajax({

                    url: hng_ajax.ajax_url,

                    type: 'POST',

                    data: {

                        action: 'hng_remove_coupon',

                        nonce: hng_ajax.cart_nonce,

                        coupon_code: code

                    },

                    success: function (response) {

                        if (response.success) {

                            // Recarregar página

                            location.reload();

                        } else {

                            alert(response.data.message);

                            $button.prop('disabled', false).text('Remover');

                        }

                    },

                    error: function () {

                        alert('Erro ao remover cupom.');

                        $button.prop('disabled', false).text('Remover');

                    }

                });

            });

        },



        /**

         * Mostrar notificação

         */

        showNotice: function (message, type) {

            var $notice = $('<div class="hng-notice hng-notice-' + type + '">' + message + '</div>');



            // Remover notices antigas

            $('.hng-notice').remove();



            // Adicionar nova notice

            if ($('.hng-notices').length) {

                $('.hng-notices').prepend($notice);

            } else {

                $('body').prepend('<div class="hng-notices"></div>');

                $('.hng-notices').append($notice);

            }



            // Scroll para o topo

            $('html, body').animate({ scrollTop: 0 }, 300);



            // Auto-remover após 5 segundos

            setTimeout(function () {

                $notice.fadeOut(300, function () {

                    $(this).remove();

                });

            }, 5000);

        }

    };



    // Inicializar

    $(document).ready(function () {

        HNG_Commerce.init();

    });



})(jQuery);

