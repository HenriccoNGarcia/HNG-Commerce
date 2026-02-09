<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Template: Cartão de Crédito
 * Formulário seguro para pagamento com cartão
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Obter dados do pedido
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET parameter for order lookup in payment page, no data modification
$order_id = isset($_GET['order_id']) ? intval(wp_unslash($_GET['order_id'])) : 0;
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET parameter for order key validation, no data modification
$order_key = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';

if ( ! $order_id ) {
    wp_die( esc_html__( 'Pedido não encontrado.', 'hng-commerce') );
}

$order = new HNG_Order($order_id);

if ( ! $order->exists() || $order->get_order_number() !== $order_key ) {
    wp_die( esc_html__( 'Pedido inválido.', 'hng-commerce') );
}

get_header();
?>

<div class="hng-payment-page hng-credit-card-payment">
    <div class="hng-payment-container">
        
        <!-- Header -->
        <div class="hng-payment-header">
            <h1><?php esc_html_e( 'Pagamento com Cartão', 'hng-commerce'); ?></h1>
            <p class="hng-order-number">
                <?php
                /* translators: %1$s: número do pedido */
                $format_order = __( 'Pedido #%1$s', 'hng-commerce' );
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Format string is escaped, placeholder value is escaped
                echo esc_html( sprintf( $format_order, $order->get_order_number() ) ); ?>
            </p>
        </div>

        <!-- Valor Total -->
        <div class="hng-payment-amount">
            <span class="hng-label"><?php esc_html_e( 'Valor Total:', 'hng-commerce'); ?></span>
            <span class="hng-value">R$ <?php echo esc_html( number_format( $order->get_total(), 2, ',', '.' ) ); ?></span>
        </div>

        <!-- Formulário -->
        <form id="hng-credit-card-form" method="post">
            <?php wp_nonce_field( 'hng_process_card_payment', 'hng_card_nonce' ); ?>
            <input type="hidden" name="order_id" value="<?php echo esc_attr( $order_id ); ?>">
            <input type="hidden" name="order_key" value="<?php echo esc_attr( $order_key ); ?>">
            
            <!-- Número do Cartão -->
            <div class="hng-form-row">
                <label for="card_number">
                    <?php esc_html_e('Número do Cartão', 'hng-commerce'); ?> *
                </label>
                <div class="hng-card-input-wrapper">
                    <input 
                        type="text" 
                        id="card_number" 
                        name="card_number" 
                        class="hng-input" 
                        placeholder="0000 0000 0000 0000"
                        maxlength="19"
                        required
                        autocomplete="cc-number"
                    >
                    <div class="hng-card-brand" id="card_brand"></div>
                </div>
                <span class="hng-error" id="card_number_error"></span>
            </div>

            <!-- Nome no Cartão -->
            <div class="hng-form-row">
                <label for="card_holder_name">
                    <?php esc_html_e('Nome Impresso no Cartão', 'hng-commerce'); ?> *
                </label>
                <input 
                    type="text" 
                    id="card_holder_name" 
                    name="card_holder_name" 
                    class="hng-input" 
                    placeholder="NOME COMPLETO"
                    required
                    autocomplete="cc-name"
                    style="text-transform: uppercase;"
                >
                <span class="hng-error" id="card_holder_name_error"></span>
            </div>

            <!-- Validade e CVV -->
            <div class="hng-form-row hng-form-row-cols">
                <div class="hng-form-col">
                    <label for="card_expiry">
                        <?php esc_html_e('Validade', 'hng-commerce'); ?> *
                    </label>
                    <input 
                        type="text" 
                        id="card_expiry" 
                        name="card_expiry" 
                        class="hng-input" 
                        placeholder="MM/AA"
                        maxlength="5"
                        required
                        autocomplete="cc-exp"
                    >
                    <span class="hng-error" id="card_expiry_error"></span>
                </div>

                <div class="hng-form-col">
                    <label for="card_cvv">
                        <?php esc_html_e('CVV', 'hng-commerce'); ?> *
                        <span class="hng-tooltip" data-tooltip="Código de 3 dígitos no verso do cartão">
                            <span class="dashicons dashicons-info"></span>
                        </span>
                    </label>
                    <input 
                        type="text" 
                        id="card_cvv" 
                        name="card_cvv" 
                        class="hng-input" 
                        placeholder="000"
                        maxlength="4"
                        required
                        autocomplete="cc-csc"
                    >
                    <span class="hng-error" id="card_cvv_error"></span>
                </div>
            </div>

            <!-- CPF do Titular -->
            <div class="hng-form-row">
                <label for="card_holder_cpf">
                    <?php esc_html_e( 'CPF do Titular', 'hng-commerce'); ?> *
                </label>
                <input 
                    type="text" 
                    id="card_holder_cpf" 
                    name="card_holder_cpf" 
                    class="hng-input" 
                    placeholder="000.000.000-00"
                    maxlength="14"
                    required
                >
                <span class="hng-error" id="card_holder_cpf_error"></span>
            </div>

            <!-- Parcelas -->
            <div class="hng-form-row">
                <label for="installments">
                    <?php esc_html_e( 'Parcelamento', 'hng-commerce'); ?> *
                </label>
                <select id="installments" name="installments" class="hng-select" required>
                    <?php
                    $total = $order->get_total();
                    $max_installments = 12;
                    
                    for ($i = 1; $i <= $max_installments; $i++) {
                        $installment_value = $total / $i;
                        
                        // Não permitir parcelas menores que R$ 5
                        if ($installment_value < 5 && $i > 1) {
                            break;
                        }
                        
                        /* translators: 1: número de parcelas; 2: valor por parcela; 3: texto de juros */
                        $format_installment = esc_html__( '%1$dx de R$ %2$s %3$s', 'hng-commerce' );
                        $label = sprintf( $format_installment,
                            $i,
                            number_format( $installment_value, 2, ',', '.' ),
                            $i === 1 ? esc_html__( 'sem juros', 'hng-commerce') : esc_html__( 'sem juros', 'hng-commerce')
                        );

                        printf( '<option value="%d">%s</option>', absint( $i ), esc_html( $label ) );
                    }
                    ?>
                </select>
                <p class="hng-help-text">
                    <?php esc_html_e( 'Pagamento processado em até 2 dias úteis', 'hng-commerce'); ?>
                </p>
            </div>

            <!-- Informações de Segurança -->
            <div class="hng-security-info">
                <div class="hng-security-item">
                    <span class="dashicons dashicons-lock"></span>
                    <span><?php esc_html_e( 'Seus dados estão protegidos com criptografia SSL', 'hng-commerce'); ?></span>
                </div>
                <div class="hng-security-item">
                    <span class="dashicons dashicons-shield"></span>
                    <span><?php esc_html_e( 'Não armazenamos dados do seu cartão', 'hng-commerce'); ?></span>
                </div>
            </div>

            <!-- Mensagem de Erro Global -->
            <div id="hng-payment-error" class="hng-alert hng-alert-error" style="display: none;"></div>

            <!-- Botão de Pagamento -->
            <button type="submit" id="hng-submit-payment" class="hng-btn hng-btn-primary hng-btn-large hng-btn-block">
                <span class="hng-btn-text">
                    <?php
                    /* translators: %1$s: total do pedido formatado */
                    $format_pay = __( 'Pagar R$ %1$s', 'hng-commerce' );
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Format string is escaped, placeholder value is escaped
                    echo esc_html( sprintf( $format_pay, number_format( $order->get_total(), 2, ',', '.' ) ) ); ?>
                </span>
                <span class="hng-btn-loading" style="display: none;">
                    <span class="hng-spinner"></span>
                    <?php esc_html_e( 'Processando...', 'hng-commerce'); ?>
                </span>
            </button>

            <!-- Bandeiras Aceitas -->
            <div class="hng-accepted-cards">
                <span><?php esc_html_e( 'Aceitamos:', 'hng-commerce'); ?></span>
                <div class="hng-card-brands">
                    <span class="hng-brand-icon" data-brand="visa">Visa</span>
                    <span class="hng-brand-icon" data-brand="mastercard">Mastercard</span>
                    <span class="hng-brand-icon" data-brand="elo">Elo</span>
                    <span class="hng-brand-icon" data-brand="amex">Amex</span>
                    <span class="hng-brand-icon" data-brand="hipercard">Hipercard</span>
                </div>
            </div>
        </form>

        <!-- Resumo do Pedido -->
        <div class="hng-order-summary">
            <h3><?php esc_html_e( 'Resumo do Pedido', 'hng-commerce'); ?></h3>
            <table class="hng-order-table">
                <tbody>
                    <?php foreach ($order->get_items() as $item): ?>
                    <tr>
                        <td class="hng-product-name">
                            <?php echo esc_html($item['product_name']); ?>
                            <?php if ($item['quantity'] > 1): ?>
                                <span class="hng-quantity">× <?php echo esc_html($item['quantity']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="hng-product-price">
                            R$ <?php echo esc_html( number_format( $item['subtotal'], 2, ',', '.' ) ); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if ($order->get_shipping_total() > 0): ?>
                    <tr class="hng-shipping-row">
                        <td><?php esc_html_e( 'Frete', 'hng-commerce'); ?></td>
                        <td>R$ <?php echo esc_html( number_format( $order->get_shipping_total(), 2, ',', '.' ) ); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <tr class="hng-total-row">
                        <td><strong><?php esc_html_e( 'Total', 'hng-commerce'); ?></strong></td>
                        <td><strong>R$ <?php echo esc_html( number_format( $order->get_total(), 2, ',', '.' ) ); ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>
</div>

<style>
.hng-payment-page {
    background: #f5f5f5;
    padding: 40px 20px;
    min-height: 100vh;
}

.hng-payment-container {
    max-width: 600px;
    margin: 0 auto;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    padding: 40px;
}

.hng-payment-header {
    text-align: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #f0f0f0;
}

.hng-payment-header h1 {
    font-size: 28px;
    margin: 0 0 10px 0;
    color: #333;
}

.hng-order-number {
    color: #666;
    font-size: 14px;
}

.hng-payment-amount {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    padding: 20px;
    border-radius: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.hng-payment-amount .hng-label {
    font-size: 14px;
    opacity: 0.9;
}

.hng-payment-amount .hng-value {
    font-size: 32px;
    font-weight: bold;
}

.hng-form-row {
    margin-bottom: 20px;
}

.hng-form-row label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
}

.hng-input,
.hng-select {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #ddd;
    border-radius: 6px;
    font-size: 16px;
    transition: border-color 0.3s;
}

.hng-input:focus,
.hng-select:focus {
    outline: none;
    border-color: #667eea;
}

.hng-input.hng-error-input {
    border-color: #e74c3c;
}

.hng-error {
    display: block;
    color: #e74c3c;
    font-size: 13px;
    margin-top: 5px;
}

.hng-card-input-wrapper {
    position: relative;
}

.hng-card-brand {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    width: 40px;
    height: 25px;
    background-size: contain;
    background-repeat: no-repeat;
    background-position: center;
}

.hng-form-row-cols {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.hng-tooltip {
    position: relative;
    display: inline-block;
    cursor: help;
}

.hng-tooltip .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
    vertical-align: middle;
}

.hng-tooltip:hover::after {
    content: attr(data-tooltip);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    background: #333;
    color: #fff;
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 12px;
    white-space: nowrap;
    z-index: 1000;
    margin-bottom: 5px;
}

.hng-help-text {
    font-size: 13px;
    color: #666;
    margin-top: 5px;
}

.hng-security-info {
    background: #f0f8ff;
    border: 1px solid #cce5ff;
    border-radius: 6px;
    padding: 15px;
    margin: 20px 0;
}

.hng-security-item {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
    font-size: 14px;
}

.hng-security-item:last-child {
    margin-bottom: 0;
}

.hng-security-item .dashicons {
    color: #2196f3;
    font-size: 20px;
}

.hng-alert {
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 20px;
}

.hng-alert-error {
    background: #fee;
    border: 1px solid #e74c3c;
    color: #c0392b;
}

.hng-btn {
    padding: 15px 30px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 16px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    transition: all 0.3s;
}

.hng-btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
}

.hng-btn-primary:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.hng-btn-large {
    padding: 18px 40px;
    font-size: 18px;
}

.hng-btn-block {
    width: 100%;
}

.hng-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.hng-spinner {
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.hng-accepted-cards {
    text-align: center;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #f0f0f0;
}

.hng-accepted-cards > span {
    display: block;
    font-size: 13px;
    color: #666;
    margin-bottom: 10px;
}

.hng-card-brands {
    display: flex;
    justify-content: center;
    gap: 15px;
    flex-wrap: wrap;
}

.hng-brand-icon {
    display: inline-block;
    padding: 5px 10px;
    background: #f5f5f5;
    border-radius: 4px;
    font-size: 12px;
    color: #666;
}

.hng-order-summary {
    margin-top: 30px;
    padding-top: 30px;
    border-top: 2px solid #f0f0f0;
}

.hng-order-table {
    width: 100%;
    border-collapse: collapse;
}

.hng-order-table td {
    padding: 12px 0;
    border-bottom: 1px solid #f0f0f0;
}

.hng-order-table .hng-product-price {
    text-align: right;
}

.hng-total-row td {
    border-bottom: none;
    font-size: 18px;
    padding-top: 15px;
}

@media (max-width: 768px) {
    .hng-payment-container {
        padding: 20px;
    }
    
    .hng-form-row-cols {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    
    // Máscaras
    $('#card_number').on('input', function() {
        var value = $(this).val().replace(/\s/g, '');
        var formatted = value.match(/.{1,4}/g);
        $(this).val(formatted ? formatted.join(' ') : value);
        
        // Detectar bandeira
        detectCardBrand(value);
        
        // Validar Luhn
        if (value.length >= 13) {
            validateLuhn(value);
        }
    });
    
    $('#card_expiry').on('input', function() {
        var value = $(this).val().replace(/\D/g, '');
        if (value.length >= 2) {
            $(this).val(value.substring(0, 2) + '/' + value.substring(2, 4));
        }
    });
    
    $('#card_cvv').on('input', function() {
        $(this).val($(this).val().replace(/\D/g, ''));
    });
    
    $('#card_holder_cpf').on('input', function() {
        var value = $(this).val().replace(/\D/g, '');
        if (value.length <= 11) {
            value = value.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
        }
        $(this).val(value);
    });
    
    // Detectar bandeira do cartão
    function detectCardBrand(number) {
        var brand = '';
        
        if (/^4/.test(number)) {
            brand = 'visa';
        } else if (/^5[1-5]/.test(number)) {
            brand = 'mastercard';
        } else if (/^3[47]/.test(number)) {
            brand = 'amex';
        } else if (/^(4011|4312|4389|4514|5067|6277|6363)/.test(number)) {
            brand = 'elo';
        } else if (/^(606282|3841)/.test(number)) {
            brand = 'hipercard';
        }
        
        $('#card_brand').attr('data-brand', brand);
    }
    
    // Validação Luhn (algoritmo de cartão de crédito)
    function validateLuhn(number) {
        var sum = 0;
        var shouldDouble = false;
        
        for (var i = number.length - 1; i >= 0; i--) {
            var digit = parseInt(number.charAt(i));
            
            if (shouldDouble) {
                digit *= 2;
                if (digit > 9) digit -= 9;
            }
            
            sum += digit;
            shouldDouble = !shouldDouble;
        }
        
        var isValid = (sum % 10) === 0;
        
        if (!isValid) {
            $('#card_number').addClass('hng-error-input');
            $('#card_number_error').text('Número de cartão inválido');
        } else {
            $('#card_number').removeClass('hng-error-input');
            $('#card_number_error').text('');
        }
    }
    
    // Submit do formulário
    $('#hng-credit-card-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $btn = $('#hng-submit-payment');
        var $btnText = $btn.find('.hng-btn-text');
        var $btnLoading = $btn.find('.hng-btn-loading');
        var $error = $('#hng-payment-error');
        
        // Limpar erros
        $('.hng-error').text('');
        $('.hng-input').removeClass('hng-error-input');
        $error.hide();
        
        // Validações básicas
        var errors = [];
        
        var cardNumber = $('#card_number').val().replace(/\s/g, '');
        if (cardNumber.length < 13) {
            errors.push('Número do cartão inválido');
            $('#card_number').addClass('hng-error-input');
        }
        
        var expiry = $('#card_expiry').val();
        if (!/^\d{2}\/\d{2}$/.test(expiry)) {
            errors.push('Validade inválida');
            $('#card_expiry').addClass('hng-error-input');
        }
        
        var cvv = $('#card_cvv').val();
        if (cvv.length < 3) {
            errors.push('CVV inválido');
            $('#card_cvv').addClass('hng-error-input');
        }
        
        if (errors.length > 0) {
            $error.html(errors.join('<br>')).show();
            return;
        }
        
        // Processar pagamento
        $btn.prop('disabled', true);
        $btnText.hide();
        $btnLoading.show();
        
        $.ajax({
            url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
            method: 'POST',
            data: $form.serialize() + '&action=hng_process_card_payment',
            success: function(response) {
                if (response.success) {
                    window.location.href = response.data.redirect_url;
                } else {
                    $error.text(response.data.message).show();
                    $btn.prop('disabled', false);
                    $btnText.show();
                    $btnLoading.hide();
                }
            },
            error: function() {
                $error.text('Erro ao processar pagamento. Tente novamente.').show();
                $btn.prop('disabled', false);
                $btnText.show();
                $btnLoading.hide();
            }
        });
    });
});
</script>

<?php
get_footer();
?>
