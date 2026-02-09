<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<!-- Template: Pagamento com Cartão Mercado Pago -->
<div class="hng-payment-method-content" id="payment-mercadopago-card" style="display: none;">
    <div class="hng-mercadopago-card-form">
        <h4>Pagamento com Cartão de Crédito</h4>
        <p class="hng-secure-badge">Pagamento 100% seguro via Mercado Pago</p>
        
        <!-- Card Form -->
        <form id="hng-mercadopago-form">
            <!-- Número do Cartão -->
            <div class="hng-form-group">
                <label for="mp-card-number">Número do Cartão <span class="required">*</span></label>
                <div class="hng-card-input-wrapper">
                    <input 
                        type="text" 
                        id="mp-card-number" 
                        data-checkout="cardNumber"
                        placeholder="0000 0000 0000 0000"
                        maxlength="19"
                        required
                    >
                    <div id="mp-card-brand" class="hng-card-brand"></div>
                </div>
                <small class="hng-field-error" id="error-card-number"></small>
            </div>
            
            <!-- Nome no Cartão -->
            <div class="hng-form-group">
                <label for="mp-card-holder-name">Nome Impresso no Cartão <span class="required">*</span></label>
                <input 
                    type="text" 
                    id="mp-card-holder-name" 
                    data-checkout="cardholderName"
                    placeholder="Como está impresso no cartão"
                    required
                >
                <small class="hng-field-error" id="error-card-holder"></small>
            </div>
            
            <!-- Validade e CVV -->
            <div class="hng-form-row">
                <div class="hng-form-group hng-col-6">
                    <label for="mp-card-expiry">Validade <span class="required">*</span></label>
                    <input 
                        type="text" 
                        id="mp-card-expiry" 
                        placeholder="MM/AA"
                        maxlength="5"
                        required
                    >
                    <input type="hidden" id="mp-card-expiry-month" data-checkout="cardExpirationMonth">
                    <input type="hidden" id="mp-card-expiry-year" data-checkout="cardExpirationYear">
                    <small class="hng-field-error" id="error-expiry"></small>
                </div>
                
                <div class="hng-form-group hng-col-6">
                    <label for="mp-card-cvv">CVV <span class="required">*</span></label>
                    <input 
                        type="text" 
                        id="mp-card-cvv" 
                        data-checkout="securityCode"
                        placeholder="000"
                        maxlength="4"
                        required
                    >
                    <small class="hng-field-error" id="error-cvv"></small>
                </div>
            </div>
            
            <!-- CPF do Titular -->
            <div class="hng-form-group">
                <label for="mp-card-holder-cpf">CPF do Titular <span class="required">*</span></label>
                <input 
                    type="text" 
                    id="mp-card-holder-cpf" 
                    data-checkout="docNumber"
                    placeholder="000.000.000-00"
                    maxlength="14"
                    required
                >
                <small class="hng-field-error" id="error-cpf"></small>
            </div>
            
            <!-- Parcelamento -->
            <div class="hng-form-group">
                <label for="mp-installments">Parcelamento <span class="required">*</span></label>
                <select id="mp-installments" data-checkout="installments" required>
                    <option value="">Carregando opções...</option>
                </select>
                <small class="hng-installments-info"></small>
            </div>
            
            <input type="hidden" id="mp-payment-method-id" data-checkout="paymentMethodId">
            <input type="hidden" id="mp-card-token" name="card_token">
        </form>
        
        <!-- Bandeiras Aceitas -->
        <div class="hng-accepted-cards">
            <small>Aceitamos:</small>
            <div class="hng-card-brands">
                <img src="<?php echo esc_url( plugin_dir_url( __FILE__ ) . '../assets/images/cards/visa.svg' ); ?>" alt="Visa" title="Visa">
                <img src="<?php echo esc_url( plugin_dir_url( __FILE__ ) . '../assets/images/cards/mastercard.svg' ); ?>" alt="Mastercard" title="Mastercard">
                <img src="<?php echo esc_url( plugin_dir_url( __FILE__ ) . '../assets/images/cards/elo.svg' ); ?>" alt="Elo" title="Elo">
                <img src="<?php echo esc_url( plugin_dir_url( __FILE__ ) . '../assets/images/cards/amex.svg' ); ?>" alt="American Express" title="American Express">
            </div>
        </div>
    </div>
</div>

<?php
if (function_exists('wp_add_inline_style')) {
    $hng_mp_css = ".hng-mercadopago-card-form {
    background: white;
    padding: 25px;
    border-radius: 8px;
    border: 1px solid #e0e0e0;
}

.hng-mercadopago-card-form h4 {
    margin: 0 0 10px 0;
    font-size: 18px;
    color: #333;
}

.hng-secure-badge {
    display: inline-block;
    background: #e8f5e9;
    color: #2e7d32;
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 13px;
    margin-bottom: 20px;
}

.hng-form-group {
    margin-bottom: 20px;
}

.hng-form-group label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: #333;
    margin-bottom: 8px;
}

.hng-form-group .required {
    color: #e74c3c;
}

.hng-card-input-wrapper {
    position: relative;
}

.hng-card-input-wrapper input {
    padding-right: 50px;
}

.hng-card-brand {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    width: 35px;
    height: 24px;
    background-size: contain;
    background-repeat: no-repeat;
    background-position: center;
}

.hng-form-group input,
.hng-form-group select {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    transition: all 0.3s;
}

.hng-form-group input:focus,
.hng-form-group select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.hng-form-group input.error {
    border-color: #e74c3c;
}

.hng-field-error {
    display: block;
    color: #e74c3c;
    font-size: 12px;
    margin-top: 5px;
    min-height: 18px;
}

.hng-form-row {
    display: flex;
    gap: 15px;
}

.hng-col-6 {
    flex: 1;
}

.hng-installments-info {
    display: block;
    color: #666;
    font-size: 12px;
    margin-top: 5px;
}

.hng-accepted-cards {
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid #e0e0e0;
}

.hng-accepted-cards small {
    display: block;
    color: #999;
    font-size: 12px;
    margin-bottom: 10px;
}

.hng-card-brands {
    display: flex;
    gap: 10px;
    align-items: center;
}

.hng-card-brands img {
    height: 24px;
    width: auto;
    opacity: 0.7;
    transition: opacity 0.3s;
}

.hng-card-brands img:hover {
    opacity: 1;
}

@media (max-width: 768px) {
    .hng-form-row {
        flex-direction: column;
    }
}";

    wp_add_inline_style('HNG Commerce', $hng_mp_css);
}

wp_enqueue_script(
    'hng-mercadopago-card',
    HNG_COMMERCE_URL . 'assets/js/mercadopago-card.js',
    array('jquery'),
    HNG_COMMERCE_VERSION,
    true
);

wp_localize_script('hng-mercadopago-card', 'hngMercadoPago', array(
    'publicKey' => get_option('hng_mercadopago_public_key', '')
));
?>
