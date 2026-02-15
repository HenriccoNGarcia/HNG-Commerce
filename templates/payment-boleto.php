<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Template: Boleto Bancário
 * Exibe linha digitável, código de barras e instruções
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

// Obter dados do boleto do Asaas
$payment_id = get_post_meta($order_id, '_asaas_payment_id', true);

if ( ! $payment_id ) {
    wp_die( esc_html__( 'Boleto não encontrado. Entre em contato com o suporte.', 'hng-commerce') );
}

$gateway = new HNG_Gateway_Asaas();
$boleto_data = $gateway->get_boleto_data($payment_id);

if ( is_wp_error( $boleto_data ) ) {
    /* translators: %s: error message returned by boleto API */
    wp_die( sprintf( esc_html__( 'Erro ao obter boleto: %s', 'hng-commerce' ), esc_html( $boleto_data->get_error_message() ) ) );
}

get_header();
?>

<div class="hng-payment-page hng-boleto-payment" role="main" aria-label="Pagamento por boleto" tabindex="0">
    <div class="hng-payment-container">
        
        <!-- Header -->
        <div class="hng-payment-header" role="region" aria-label="Cabeçalho do pagamento" tabindex="0">
            <h1><?php esc_html_e( 'Boleto Bancário', 'hng-commerce'); ?></h1>
            <p class="hng-order-number">
                <?php
                /* translators: %1$s: número do pedido */
                printf( esc_html__( 'Pedido #%1$s', 'hng-commerce' ), esc_html( $order->get_order_number() ) ); ?>
            </p>
        </div>

        <!-- Status -->
        <div class="hng-payment-status hng-status-pending" role="status" aria-live="polite" tabindex="0">
            <span class="hng-status-icon">?</span>
            <div class="hng-status-text">
                <strong><?php esc_html_e( 'Aguardando Pagamento', 'hng-commerce'); ?></strong>
                <p><?php esc_html_e( 'Pague o boleto para confirmar seu pedido', 'hng-commerce'); ?></p>
            </div>
        </div>

        <!-- Informações do Boleto -->
        <div class="hng-boleto-info">
            
            <!-- Valor -->
            <div class="hng-boleto-amount">
                <span class="hng-label"><?php esc_html_e( 'Valor:', 'hng-commerce'); ?></span>
                <span class="hng-value">R$ <?php echo esc_html( number_format( $order->get_total(), 2, ',', '.' ) ); ?></span>
            </div>

            <!-- Vencimento -->
            <div class="hng-boleto-due-date">
                <span class="hng-label"><?php esc_html_e('Vencimento:', 'hng-commerce'); ?></span>
                <span class="hng-value hng-due-date">
                    <?php 
                    $due_date = isset($boleto_data['dueDate']) ? $boleto_data['dueDate'] : '';
                    if ($due_date) {
                        $date = DateTime::createFromFormat('Y-m-d', $due_date);
                        echo esc_html($date->format('d/m/Y'));
                    }
                    ?>
                </span>
            </div>

            <!-- Aviso de vencimento -->
            <div class="hng-boleto-warning">
                <span class="dashicons dashicons-warning"></span>
                <?php esc_html_e('Após o vencimento: multa de 2% + juros de 1% ao mês', 'hng-commerce'); ?>
            </div>
        </div>

        <!-- Linha Digitável -->
        <div class="hng-boleto-code-section">
            <h3><?php esc_html_e( 'Linha Digitável', 'hng-commerce'); ?></h3>
            <div class="hng-boleto-code-container">
                <input 
                    type="text" 
                    id="hng-boleto-code" 
                    class="hng-boleto-code" 
                    value="<?php echo esc_attr($boleto_data['identificationField'] ?? ''); ?>" 
                    readonly
                >
                <button type="button" id="hng-copy-boleto" class="hng-btn hng-btn-copy">
                    <span class="dashicons dashicons-clipboard"></span>
                    <?php esc_html_e( 'Copiar', 'hng-commerce'); ?>
                </button>
            </div>
            <p class="hng-help-text">
                <?php esc_html_e( 'Use esta linha digitável para pagar em qualquer banco ou aplicativo bancário.', 'hng-commerce'); ?>
            </p>
        </div>

        <!-- Código de Barras -->
        <?php if (!empty($boleto_data['barCode'])): ?>
        <div class="hng-barcode-section">
            <h3><?php esc_html_e('Código de Barras', 'hng-commerce'); ?></h3>
            <div class="hng-barcode-container">
                <?php 
                // Gerar código de barras usando biblioteca externa ou imagem do Asaas
                if (!empty($boleto_data['bankSlipUrl'])) {
                    // Asaas gera o PDF com código de barras
                    echo '<p>' . esc_html__('O código de barras está disponível no PDF do boleto.', 'hng-commerce') . '</p>';
                }
                ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Botões de Ação -->
        <div class="hng-payment-actions">
            
            <!-- Baixar PDF -->
            <?php if (!empty($boleto_data['bankSlipUrl'])): ?>
            <a 
                href="<?php echo esc_url($boleto_data['bankSlipUrl']); ?>" 
                target="_blank" 
                class="hng-btn hng-btn-primary hng-btn-large"
            >
                <span class="dashicons dashicons-download"></span>
                <?php esc_html_e('Baixar Boleto (PDF)', 'hng-commerce'); ?>
            </a>
            <?php endif; ?>

            <!-- Imprimir -->
            <button type="button" id="hng-print-boleto" class="hng-btn hng-btn-secondary">
                <span class="dashicons dashicons-printer"></span>
                <?php esc_html_e('Imprimir', 'hng-commerce'); ?>
            </button>

            <!-- Ver Pedido -->
            <a href="<?php echo esc_url(hng_get_page_url('minha-conta')); ?>" class="hng-btn hng-btn-outline">
                <?php esc_html_e('Ver Meus Pedidos', 'hng-commerce'); ?>
            </a>
        </div>

        <!-- Instruções -->
        <div class="hng-payment-instructions">
            <h3><?php esc_html_e('Como Pagar', 'hng-commerce'); ?></h3>
            <ol>
                <li><?php esc_html_e('Copie a linha digitável ou baixe o PDF do boleto', 'hng-commerce'); ?></li>
                <li><?php esc_html_e('Acesse o aplicativo ou site do seu banco', 'hng-commerce'); ?></li>
                <li><?php esc_html_e('Escolha a opção "Pagar Boleto" ou "Pagar Conta"', 'hng-commerce'); ?></li>
                <li><?php esc_html_e('Cole a linha digitável ou leia o código de barras', 'hng-commerce'); ?></li>
                <li><?php esc_html_e('Confirme os dados e finalize o pagamento', 'hng-commerce'); ?></li>
            </ol>

            <div class="hng-info-box">
                <span class="dashicons dashicons-info"></span>
                <div>
                    <strong><?php esc_html_e('Prazo de confirmação:', 'hng-commerce'); ?></strong>
                    <p><?php esc_html_e('O pagamento pode levar até 2 dias úteis para ser confirmado pelo banco.', 'hng-commerce'); ?></p>
                </div>
            </div>
        </div>

        <!-- Onde Pagar -->
        <div class="hng-payment-locations">
            <h3><?php esc_html_e('Onde Posso Pagar?', 'hng-commerce'); ?></h3>
            <div class="hng-payment-methods-grid">
                <div class="hng-payment-method-item">
                    <span class="dashicons dashicons-smartphone"></span>
                    <span><?php esc_html_e('App do Banco', 'hng-commerce'); ?></span>
                </div>
                <div class="hng-payment-method-item">
                    <span class="dashicons dashicons-desktop"></span>
                    <span><?php esc_html_e('Internet Banking', 'hng-commerce'); ?></span>
                </div>
                <div class="hng-payment-method-item">
                    <span class="dashicons dashicons-store"></span>
                    <span><?php esc_html_e('Caixa Eletrônico', 'hng-commerce'); ?></span>
                </div>
                <div class="hng-payment-method-item">
                    <span class="dashicons dashicons-building"></span>
                    <span><?php esc_html_e('Agência Bancária', 'hng-commerce'); ?></span>
                </div>
                <div class="hng-payment-method-item">
                    <span class="dashicons dashicons-cart"></span>
                    <span><?php esc_html_e('Lotéricas', 'hng-commerce'); ?></span>
                </div>
                <div class="hng-payment-method-item">
                    <span class="dashicons dashicons-admin-home"></span>
                    <span><?php esc_html_e('Correspondentes', 'hng-commerce'); ?></span>
                </div>
            </div>
        </div>

        <!-- Resumo do Pedido -->
        <div class="hng-order-summary">
            <h3><?php esc_html_e('Resumo do Pedido', 'hng-commerce'); ?></h3>
            <table class="hng-order-table">
                <tbody>
                    <?php foreach ($order->get_items() as $item): ?>
                    <tr>
                        <td class="hng-product-name">
                            <?php echo esc_html($item['product_name']); ?>
                            <?php if ($item['quantity'] > 1): ?>
                                <span class="hng-quantity">&times; <?php echo esc_html(intval($item['quantity'])); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="hng-product-price">
                            R$ <?php echo esc_html(number_format($item['subtotal'], 2, ',', '.')); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if ($order->get_shipping_total() > 0): ?>
                    <tr class="hng-shipping-row">
                        <td><?php esc_html_e('Frete', 'hng-commerce'); ?></td>
                        <td>R$ <?php echo esc_html(number_format($order->get_shipping_total(), 2, ',', '.')); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <tr class="hng-total-row">
                        <td><strong><?php esc_html_e('Total', 'hng-commerce'); ?></strong></td>
                        <td><strong>R$ <?php echo esc_html(number_format($order->get_total(), 2, ',', '.')); ?></strong></td>
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
    max-width: 700px;
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

.hng-payment-status {
    background: #fff9e6;
    border-left: 4px solid #ffc107;
    padding: 20px;
    margin-bottom: 30px;
    display: flex;
    align-items: center;
    gap: 15px;
    border-radius: 4px;
}

.hng-status-icon {
    font-size: 32px;
}

.hng-status-text strong {
    display: block;
    font-size: 16px;
    margin-bottom: 5px;
}

.hng-status-text p {
    margin: 0;
    color: #666;
}

.hng-boleto-info {
    background: #f9f9f9;
    padding: 20px;
    border-radius: 6px;
    margin-bottom: 30px;
}

.hng-boleto-amount,
.hng-boleto-due-date {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #e0e0e0;
}

.hng-boleto-amount:last-child {
    border-bottom: none;
}

.hng-boleto-amount .hng-value {
    font-size: 24px;
    font-weight: bold;
    color: #2c5aa0;
}

.hng-due-date {
    color: #e74c3c;
    font-weight: 600;
}

.hng-boleto-warning {
    background: #fff3cd;
    border: 1px solid #ffc107;
    padding: 12px;
    margin-top: 15px;
    border-radius: 4px;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.hng-boleto-warning .dashicons {
    color: #856404;
}

.hng-boleto-code-section {
    margin-bottom: 30px;
}

.hng-boleto-code-section h3 {
    margin-bottom: 15px;
    font-size: 18px;
}

.hng-boleto-code-container {
    display: flex;
    gap: 10px;
    margin-bottom: 10px;
}

.hng-boleto-code {
    flex: 1;
    padding: 15px;
    border: 2px solid #ddd;
    border-radius: 6px;
    font-family: monospace;
    font-size: 14px;
    text-align: center;
    background: #f9f9f9;
}

.hng-btn {
    padding: 12px 24px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
}

.hng-btn-copy {
    background: #2c5aa0;
    color: #fff;
    white-space: nowrap;
}

.hng-btn-copy:hover {
    background: #1e3a6f;
}

.hng-btn-primary {
    background: #27ae60;
    color: #fff;
}

.hng-btn-primary:hover {
    background: #229954;
}

.hng-btn-secondary {
    background: #95a5a6;
    color: #fff;
}

.hng-btn-secondary:hover {
    background: #7f8c8d;
}

.hng-btn-outline {
    background: transparent;
    border: 2px solid #ddd;
    color: #333;
}

.hng-btn-outline:hover {
    border-color: #2c5aa0;
    color: #2c5aa0;
}

.hng-btn-large {
    padding: 15px 30px;
    font-size: 16px;
}

.hng-payment-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin: 30px 0;
}

.hng-payment-instructions {
    margin-bottom: 30px;
}

.hng-payment-instructions h3 {
    margin-bottom: 15px;
}

.hng-payment-instructions ol {
    padding-left: 20px;
    line-height: 1.8;
}

.hng-payment-instructions li {
    margin-bottom: 8px;
}

.hng-info-box {
    background: #e3f2fd;
    border-left: 4px solid #2196f3;
    padding: 15px;
    margin-top: 20px;
    display: flex;
    gap: 15px;
    border-radius: 4px;
}

.hng-info-box .dashicons {
    color: #2196f3;
    font-size: 24px;
}

.hng-info-box strong {
    display: block;
    margin-bottom: 5px;
}

.hng-info-box p {
    margin: 0;
    font-size: 14px;
}

.hng-payment-locations h3 {
    margin-bottom: 15px;
}

.hng-payment-methods-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-bottom: 30px;
}

.hng-payment-method-item {
    background: #f9f9f9;
    padding: 15px;
    text-align: center;
    border-radius: 6px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}

.hng-payment-method-item .dashicons {
    font-size: 32px;
    color: #2c5aa0;
}

.hng-order-summary {
    border-top: 2px solid #f0f0f0;
    padding-top: 30px;
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
    
    .hng-boleto-code-container {
        flex-direction: column;
    }
    
    .hng-payment-actions {
        flex-direction: column;
    }
    
    .hng-btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Copiar linha digitável
    $('#hng-copy-boleto').on('click', function() {
        var $code = $('#hng-boleto-code');
        $code.select();
        document.execCommand('copy');
        
        var $btn = $(this);
        var originalText = $btn.html();
        $btn.html('<span class="dashicons dashicons-yes"></span> Copiado!');
        $btn.css('background', '#27ae60');
        
        setTimeout(function() {
            $btn.html(originalText);
            $btn.css('background', '');
        }, 2000);
    });
    
    // Imprimir
    $('#hng-print-boleto').on('click', function() {
        window.print();
    });
});
</script>

<?php
get_footer();
?>
