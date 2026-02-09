<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<!-- Widget de Cálculo de Frete -->
<div class="hng-shipping-calculator" id="hng-shipping-calculator" role="region" aria-label="Calculadora de frete" tabindex="0">
    <h3><?php esc_html_e( 'Calcular Frete', 'hng-commerce'); ?></h3>
    
    <?php do_action('hng_before_shipping_calculator'); ?>
    <form id="hng-calculate-shipping-form" class="hng-shipping-form" aria-label="Formulário de cálculo de frete" tabindex="0" autocomplete="on">
        <div class="hng-form-row">
            <label for="shipping_postcode">
                <?php esc_html_e( 'CEP de Entrega', 'hng-commerce'); ?>
                <span class="required">*</span>
            </label>
            <div class="hng-input-group">
                <input 
                    type="text" 
                    id="shipping_postcode" 
                    name="shipping_postcode" 
                    placeholder="00000-000"
                    maxlength="9"
                    required
                    aria-required="true"
                    aria-label="CEP de Entrega"
                    value="<?php echo esc_attr(WP_Session::get('shipping_postcode', '')); ?>"
                    style="max-width:140px;"
                >
                <button type="submit" class="hng-btn hng-btn-primary" id="hng-calculate-btn" aria-label="<?php echo esc_attr__( 'Calcular', 'hng-commerce'); ?>" style="outline-offset:2px;">
                    <span class="btn-text"><?php esc_html_e( 'Calcular', 'hng-commerce'); ?></span>
                    <span class="btn-loading" style="display: none;">
                        <span class="spinner"></span> <?php esc_html_e( 'Calculando...', 'hng-commerce'); ?>
                    </span>
                </button>
            </div>
            <a href="https://buscacepinter.correios.com.br/app/endereco/index.php" target="_blank" class="hng-find-cep" style="outline-offset:2px;">
                <?php esc_html_e( 'Não sei meu CEP', 'hng-commerce'); ?>
            </a>
        </div>
    </form>
    
    <!-- Resultado do Frete -->
    <div id="hng-shipping-results" class="hng-shipping-results" style="display: none;" aria-live="polite">
        <h4><?php esc_html_e( 'Opções de Entrega', 'hng-commerce'); ?></h4>
        <div id="hng-shipping-options"></div>
    </div>
    
    <!-- Mensagens de Erro -->
    <div id="hng-shipping-error" class="hng-alert hng-alert-error" style="display: none;" aria-live="assertive"></div>
</div>

<style>
.hng-shipping-calculator {
    background: #f9f9f9;
    padding: 20px;
    border-radius: 8px;
    margin: 20px 0;
}

.hng-shipping-calculator h3 {
    margin: 0 0 15px 0;
    font-size: 18px;
    color: #333;
}

.hng-shipping-form {
    margin-bottom: 15px;
}

.hng-form-row {
    margin-bottom: 15px;
}

.hng-form-row label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #555;
}

.required {
    color: #e74c3c;
}

.hng-input-group {
    display: flex;
    gap: 10px;
}

.hng-input-group input {
    flex: 1;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.hng-input-group input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.hng-btn {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.hng-btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.hng-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.hng-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

.btn-loading {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.spinner {
    width: 14px;
    height: 14px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.hng-find-cep {
    display: inline-block;
    margin-top: 5px;
    font-size: 12px;
    color: #667eea;
    text-decoration: none;
}

.hng-find-cep:hover {
    text-decoration: underline;
}

.hng-shipping-results {
    margin-top: 20px;
    animation: fadeIn 0.3s;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.hng-shipping-results h4 {
    margin: 0 0 15px 0;
    font-size: 16px;
    color: #333;
}

.hng-shipping-option {
    background: white;
    padding: 15px;
    margin-bottom: 10px;
    border-radius: 6px;
    border: 2px solid #e0e0e0;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.hng-shipping-option:hover {
    border-color: #667eea;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
}

.hng-shipping-option.selected {
    border-color: #667eea;
    background: #f5f7ff;
}

.hng-shipping-option-info {
    flex: 1;
}

.hng-shipping-option-name {
    font-weight: 600;
    color: #333;
    margin-bottom: 5px;
}

.hng-shipping-option-time {
    font-size: 13px;
    color: #666;
}

.hng-shipping-option-price {
    font-size: 18px;
    font-weight: bold;
    color: #667eea;
}

.hng-shipping-option-free {
    background: #4caf50;
    color: white;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.hng-alert {
    padding: 12px 15px;
    border-radius: 6px;
    margin-top: 15px;
    animation: fadeIn 0.3s;
}

.hng-alert-error {
    background: #ffebee;
    border-left: 4px solid #e74c3c;
    color: #c62828;
}

.hng-alert-success {
    background: #e8f5e9;
    border-left: 4px solid #4caf50;
    color: #2e7d32;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Máscara de CEP
    $('#shipping_postcode').on('input', function() {
        let value = $(this).val().replace(/\D/g, '');
        if (value.length > 5) {
            value = value.slice(0, 5) + '-' + value.slice(5, 8);
        }
        $(this).val(value);
    });
    
    // Calcular frete
    $('#hng-calculate-shipping-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $btn = $('#hng-calculate-btn');
        const $results = $('#hng-shipping-results');
        const $error = $('#hng-shipping-error');
        const postcode = $('#shipping_postcode').val().replace(/\D/g, '');
        
        // Validar CEP
        if (postcode.length !== 8) {
            $error.text('<?php echo esc_js( esc_html__( 'CEP inválido. Digite 8 dígitos.', 'hng-commerce') ); ?>').fadeIn();
            return;
        }
        
        // Loading state
        $btn.prop('disabled', true);
        $btn.find('.btn-text').hide();
        $btn.find('.btn-loading').show();
        $error.hide();
        $results.hide();
        
        // AJAX
        $.ajax({
            url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
            method: 'POST',
            data: {
                action: 'hng_calculate_shipping',
                postcode: postcode,
                nonce: '<?php echo esc_attr(wp_create_nonce('hng_calculate_shipping')); ?>'
            },
            success: function(response) {
                if (response.success) {
                    displayShippingOptions(response.data.methods);
                    $results.fadeIn();
                } else {
                    $error.text(response.data.message || '<?php echo esc_js( esc_html__( 'Erro ao calcular frete', 'hng-commerce') ); ?>').fadeIn();
                }
            },
            error: function() {
                $error.text('<?php echo esc_js( esc_html__( 'Erro de conexão. Tente novamente.', 'hng-commerce') ); ?>').fadeIn();
            },
            complete: function() {
                $btn.prop('disabled', false);
                $btn.find('.btn-text').show();
                $btn.find('.btn-loading').hide();
            }
        });
    });
    
    // Exibir opções de frete
    function displayShippingOptions(methods) {
        const $container = $('#hng-shipping-options');
        $container.empty();
        
        if (!methods || methods.length === 0) {
            $container.html('<p><?php esc_html_e('Nenhum método de frete disponível para este CEP.', 'hng-commerce'); ?></p>');
            return;
        }
        
        methods.forEach(function(method) {
            const isFree = method.cost === 0;
            const priceHtml = isFree 
                ? '<span class="hng-shipping-option-free">GRÁTIS</span>'
                : '<span class="hng-shipping-option-price">R$ ' + formatPrice(method.cost) + '</span>';
            const eta = method.delivery_time_text || method.delivery_time || method.delivery_time_label || '';
            const label = method.label || method.name || method.service || '';

            const html = `
                <div class="hng-shipping-option" data-method-id="${method.id}" data-cost="${method.cost}">
                    <div class="hng-shipping-option-info">
                        <div class="hng-shipping-option-name">${label}</div>
                        <div class="hng-shipping-option-time">${eta}</div>
                    </div>
                    ${priceHtml}
                </div>
            `;
            
            $container.append(html);
        });
        
        // Selecionar método
        $('.hng-shipping-option').on('click', function() {
            $('.hng-shipping-option').removeClass('selected');
            $(this).addClass('selected');
            
            const methodId = $(this).data('method-id');
            const cost = $(this).data('cost');
            
            // Salvar no carrinho via AJAX
            updateCartShipping(methodId, cost);
        });
        
        // Auto-selecionar primeira opção
        $('.hng-shipping-option').first().trigger('click');
    }
    
    // Atualizar frete no carrinho
    function updateCartShipping(methodId, cost) {
        $.ajax({
            url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
            method: 'POST',
            data: {
                action: 'hng_update_cart_shipping',
                method_id: methodId,
                cost: cost,
                nonce: '<?php echo esc_attr(wp_create_nonce('hng_update_cart_shipping')); ?>'
            },
            success: function(response) {
                if (response.success) {
                    // Atualizar totais do carrinho na página
                    location.reload(); // Temporário, depois implementar AJAX
                }
            }
        });
    }
    
    // Formatar preço
    function formatPrice(value) {
        return parseFloat(value).toFixed(2).replace('.', ',');
    }
});
</script>
