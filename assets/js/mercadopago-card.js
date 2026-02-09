jQuery(document).ready(function($) {
    const mp_public_key = (typeof hngMercadoPago !== 'undefined' && hngMercadoPago.publicKey) ? hngMercadoPago.publicKey : '';

    if (!mp_public_key) {
        console.error('Mercado Pago: Chave pública não configurada');
        return;
    }

    const mp = new MercadoPago(mp_public_key);
    let currentPaymentMethod = null;

    // Máscara de Cartão
    $('#mp-card-number').on('input', function() {
        let value = $(this).val().replace(/\s/g, '');
        let formatted = value.match(/.{1,4}/g)?.join(' ') || value;
        $(this).val(formatted);

        // Detectar bandeira
        if (value.length >= 6) {
            detectCardBrand(value);
        }
    });

    // Máscara de Validade
    $('#mp-card-expiry').on('input', function() {
        let value = $(this).val().replace(/\D/g, '');
        if (value.length >= 2) {
            value = value.substring(0, 2) + '/' + value.substring(2, 4);
        }
        $(this).val(value);

        // Atualizar campos hidden
        const parts = value.split('/');
        if (parts.length === 2) {
            $('#mp-card-expiry-month').val(parts[0]);
            $('#mp-card-expiry-year').val('20' + parts[1]);
        }
    });

    // Máscara de CVV
    $('#mp-card-cvv').on('input', function() {
        $(this).val($(this).val().replace(/\D/g, ''));
    });

    // Máscara de CPF
    $('#mp-card-holder-cpf').on('input', function() {
        let value = $(this).val().replace(/\D/g, '');
        value = value.replace(/(\d{3})(\d)/, '$1.$2');
        value = value.replace(/(\d{3})(\d)/, '$1.$2');
        value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        $(this).val(value);
    });

    // Detectar bandeira do cartão
    function detectCardBrand(cardNumber) {
        const bin = cardNumber.substring(0, 6);

        $.ajax({
            url: 'https://api.mercadopago.com/v1/payment_methods',
            method: 'GET',
            data: {
                public_key: mp_public_key,
                bin: bin
            },
            success: function(response) {
                if (response.results && response.results.length > 0) {
                    const paymentMethod = response.results[0];
                    currentPaymentMethod = paymentMethod;

                    // Exibir logo da bandeira
                    $('#mp-card-brand').css('background-image', 'url(' + paymentMethod.secure_thumbnail + ')');
                    $('#mp-payment-method-id').val(paymentMethod.id);

                    // Carregar opções de parcelamento
                    loadInstallments(paymentMethod.id);
                }
            }
        });
    }

    // Carregar opções de parcelamento
    function loadInstallments(paymentMethodId) {
        const amount = parseFloat($('#checkout-total-amount').data('amount') || 0);

        if (amount <= 0) return;

        $.ajax({
            url: 'https://api.mercadopago.com/v1/payment_methods/installments',
            method: 'GET',
            data: {
                public_key: mp_public_key,
                amount: amount,
                payment_method_id: paymentMethodId
            },
            success: function(response) {
                if (response[0] && response[0].payer_costs) {
                    const $select = $('#mp-installments');
                    $select.empty();

                    response[0].payer_costs.forEach(function(option) {
                        const label = option.recommended_message ||
                                    option.installments + 'x de R$ ' +
                                    (option.installment_amount).toFixed(2).replace('.', ',');

                        $select.append(
                            $('<option>', {
                                value: option.installments,
                                text: label,
                                'data-rate': option.installment_rate
                            })
                        );
                    });

                    // Selecionar primeira opção
                    $select.val(response[0].payer_costs[0].installments);
                }
            }
        });
    }

    // Validar e criar token ao confirmar pedido
    $('#place-order-btn').on('click', function(e) {
        if ($('input[name="payment_method"]:checked').val() !== 'mercadopago') {
            return;
        }

        e.preventDefault();

        // Limpar erros
        $('.hng-field-error').text('');
        $('.error').removeClass('error');

        // Validar campos
        let hasError = false;

        const cardNumber = $('#mp-card-number').val().replace(/\s/g, '');
        if (cardNumber.length < 13) {
            $('#error-card-number').text('Número do cartão inválido');
            $('#mp-card-number').addClass('error');
            hasError = true;
        }

        const cardHolder = $('#mp-card-holder-name').val();
        if (cardHolder.length < 3) {
            $('#error-card-holder').text('Nome do titular obrigatório');
            $('#mp-card-holder-name').addClass('error');
            hasError = true;
        }

        const expiry = $('#mp-card-expiry').val();
        if (!/^\d{2}\/\d{2}$/.test(expiry)) {
            $('#error-expiry').text('Validade inválida');
            $('#mp-card-expiry').addClass('error');
            hasError = true;
        }

        const cvv = $('#mp-card-cvv').val();
        if (cvv.length < 3) {
            $('#error-cvv').text('CVV inválido');
            $('#mp-card-cvv').addClass('error');
            hasError = true;
        }

        const cpf = $('#mp-card-holder-cpf').val().replace(/\D/g, '');
        if (cpf.length !== 11) {
            $('#error-cpf').text('CPF inválido');
            $('#mp-card-holder-cpf').addClass('error');
            hasError = true;
        }

        if (hasError) {
            return false;
        }

        // Criar token do cartão
        $('#place-order-btn').prop('disabled', true).text('Processando...');

        const form = document.getElementById('hng-mercadopago-form');
        mp.createCardToken(form).then(function(result) {
            if (result.error) {
                alert('Erro ao processar cartão: ' + result.error.message);
                $('#place-order-btn').prop('disabled', false).text('Finalizar Pedido');
                return;
            }

            // Salvar token
            $('#mp-card-token').val(result.id);

            // Submeter checkout
            if (typeof submitCheckout === 'function') {
                submitCheckout();
            }

        }).catch(function(error) {
            console.error('Erro Mercado Pago:', error);
            alert('Erro ao processar pagamento. Tente novamente.');
            $('#place-order-btn').prop('disabled', false).text('Finalizar Pedido');
        });
    });
});
