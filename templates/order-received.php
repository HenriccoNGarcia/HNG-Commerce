<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Template: Pedido Recebido
 * Clean and validated template for order confirmation
 * @package HNG_Commerce
 */

if (!defined('ABSPATH')) {
    exit;
}

// Validate parameters
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET parameters for order confirmation, validated by order key
if (empty($_GET['order_id']) || empty($_GET['key'])) {
    wp_safe_redirect(hng_get_shop_url());
    exit;
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET parameter for order lookup, validated by order key below
$order_id = absint(wp_unslash($_GET['order_id']));
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET parameter for order key validation
$order_key = sanitize_text_field(wp_unslash($_GET['key']));

if (!class_exists('HNG_Order')) {
    wp_safe_redirect(hng_get_shop_url());
    exit;
}

$order = new HNG_Order($order_id);

// Validate order
if (!$order->get_id() || (string) $order->get_order_number() !== (string) $order_key) {
    wp_safe_redirect(hng_get_shop_url());
    exit;
}
?>

<div class="hng-order-received" role="main" aria-label="Confirmação do pedido">
    <?php do_action('hng_before_order_received', $order); ?>

    <div class="hng-notice hng-notice-success" aria-live="polite" role="status">
        <p><strong><?php esc_html_e('Obrigado! Seu pedido foi recebido.', 'hng-commerce'); ?></strong></p>
    </div>

    <div class="hng-order-details" role="region" aria-label="Detalhes do pedido">
        <h2><?php esc_html_e('Detalhes do Pedido', 'hng-commerce'); ?></h2>

        <table class="hng-order-info" role="table" aria-label="Informações do pedido">
            <tr>
            <th><?php esc_html_e('Número do Pedido:', 'hng-commerce'); ?></th>
                <td><strong><?php echo esc_html($order->get_order_number()); ?></strong></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Data:', 'hng-commerce'); ?></th>
                <td><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($order->get_created_at()))); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Total:', 'hng-commerce'); ?></th>
                <td><strong><?php echo wp_kses_post($order->get_formatted_total()); ?></strong></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Forma de Pagamento:', 'hng-commerce'); ?></th>
                <td><?php echo esc_html($order->get_payment_method_title()); ?></td>
            </tr>
        </table>

        <?php do_action('hng_after_order_info', $order); ?>

        <?php if ($order->get_payment_method() === 'pix') : 
            // Buscar dados do PIX do pedido
            $post_id = $order->get_post_id();
            $asaas_payment_id = get_post_meta($post_id, '_asaas_payment_id', true);
            $pix_qrcode = '';
            $pix_payload = '';
            
            // Debug apenas em desenvolvimento
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log(sprintf(
                    'HNG Order: ID=%d, Post=%d, Payment=%s',
                    $order->get_id(),
                    $post_id,
                    $asaas_payment_id ?: 'none'
                ));
            }
            
            // Tentar gerar QR Code se houver payment_id
            if (!empty($asaas_payment_id) && class_exists('HNG_Gateway_Asaas')) {
                $gateway = new HNG_Gateway_Asaas();
                $qr_data = $gateway->get_pix_qrcode($asaas_payment_id);
                if (!is_wp_error($qr_data)) {
                    $pix_qrcode = $qr_data['encodedImage'] ?? '';
                    $pix_payload = $qr_data['payload'] ?? '';
                    
                    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                        error_log('HNG QR Code: Success');
                    }
                } else {
                    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                        error_log('HNG QR Code Error: ' . $qr_data->get_error_message());
                    }
                }
            }
        ?>
            <div class="hng-payment-info hng-pix-info" role="region" aria-label="Pagamento via PIX">
                <h3><?php esc_html_e('Pagamento via PIX', 'hng-commerce'); ?></h3>
                <p><?php esc_html_e('Escaneie o QR Code abaixo ou copie o código PIX para realizar o pagamento.', 'hng-commerce'); ?></p>

                <div class="hng-qrcode-container" style="text-align: center; margin: 20px 0;">
                    <?php if (!empty($pix_qrcode)) : ?>
                        <img src="data:image/png;base64,<?php echo esc_attr($pix_qrcode); ?>" alt="QR Code PIX" style="max-width: 250px; border: 2px solid #32BCAD; border-radius: 8px; padding: 10px; background: #fff;" />
                    <?php else : ?>
                        <div class="hng-qrcode-placeholder" style="padding: 20px; background: #f0f0f0; border-radius: 8px;">
                            <p><?php esc_html_e('⏳ QR Code PIX será gerado em breve. Se não aparecer nos próximos minutos, verifique seu e-mail ou entre em contato.', 'hng-commerce'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="hng-pix-code">
                    <label><?php esc_html_e('Código PIX Copia e Cola:', 'hng-commerce'); ?></label>
                    <div style="display: flex; gap: 10px; margin: 10px 0;">
                        <input type="text" readonly value="<?php echo esc_attr($pix_payload ?: __('Código não disponível', 'hng-commerce')); ?>" class="hng-pix-code-input" id="pix-copy-code" style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 4px;" />
                        <button type="button" class="hng-copy-pix" onclick="copyPixCode()" style="padding: 10px 20px; background: #32BCAD; color: #fff; border: none; border-radius: 4px; cursor: pointer;"><?php esc_html_e('Copiar', 'hng-commerce'); ?></button>
                    </div>
                </div>

                <p class="hng-pix-instructions" style="margin-top: 15px; padding: 10px; background: #f9f9f9; border-left: 4px solid #32BCAD;">
                    <strong><?php esc_html_e('Como pagar:', 'hng-commerce'); ?></strong><br>
                    <?php esc_html_e('1. Abra o app do seu banco', 'hng-commerce'); ?><br>
                    <?php esc_html_e('2. Escolha pagar com PIX', 'hng-commerce'); ?><br>
                    <?php esc_html_e('3. Escaneie o QR Code ou cole o código', 'hng-commerce'); ?><br>
                    <?php esc_html_e('4. Confirme o pagamento', 'hng-commerce'); ?>
                </p>
                
                <p class="hng-pix-status" style="margin-top: 15px; padding: 10px; background: #fff3cd; border-radius: 4px;">
                    <strong><?php esc_html_e('Status:', 'hng-commerce'); ?></strong> 
                    <?php esc_html_e('⏳ Aguardando pagamento', 'hng-commerce'); ?>
                </p>

                <script>
                function copyPixCode() {
                    const input = document.getElementById('pix-copy-code');
                    if (input.value && input.value !== '<?php echo esc_js(__('Código não disponível', 'hng-commerce')); ?>') {
                        input.select();
                        document.execCommand('copy');
                        alert('<?php echo esc_js(__('Código PIX copiado!', 'hng-commerce')); ?>');
                    } else {
                        alert('<?php echo esc_js(__('Código PIX não disponível', 'hng-commerce')); ?>');
                    }
                }
                </script>
            </div>
        <?php endif; ?>

        <?php if ($order->get_payment_method() === 'boleto') : ?>
            <div class="hng-payment-info hng-boleto-info" role="region" aria-label="Pagamento via Boleto">
                <h3><?php esc_html_e('Pagamento via Boleto', 'hng-commerce'); ?></h3>
                <p><?php esc_html_e('Clique no botão abaixo para visualizar e pagar seu boleto.', 'hng-commerce'); ?></p>
                <!-- Link do boleto pode ser inserido aqui -->
            </div>
        <?php endif; ?>

        <?php do_action('hng_after_order_received', $order); ?>
    </div>

    <div class="hng-order-items" role="region" aria-label="Produtos do pedido">
        <h2><?php esc_html_e('Produtos', 'hng-commerce'); ?></h2>

        <div class="hng-order-items-table-responsive">
            <table class="hng-order-items-table" role="table" aria-label="Produtos do pedido">
                <thead>
                    <tr>
                        <th class="hng-product-name"><?php esc_html_e('Produto', 'hng-commerce'); ?></th>
                        <th class="hng-product-quantity"><?php esc_html_e('Quantidade', 'hng-commerce'); ?></th>
                        <th class="hng-product-total"><?php esc_html_e('Total', 'hng-commerce'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order->get_items() as $item) : ?>
                        <tr>
                            <td class="hng-product-name">
                                <?php echo esc_html(isset($item['product_name']) ? $item['product_name'] : ''); ?>
                                <?php
                                $item_cp = $item;
                                $partial = __DIR__ . '/partials/order-item-custom-fields.php';
                                if (file_exists($partial)) {
                                    include $partial;
                                }
                                ?>
                            </td>
                            <td class="hng-product-quantity"><?php echo esc_html(isset($item['quantity']) ? $item['quantity'] : ''); ?></td>
                            <td class="hng-product-total"><?php echo function_exists('hng_price') ? esc_html(hng_price($item['subtotal'])) : esc_html($item['subtotal']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="hng-subtotal">
                        <th colspan="2"><?php esc_html_e('Subtotal', 'hng-commerce'); ?></th>
                        <td><?php echo function_exists('hng_price') ? esc_html(hng_price($order->get_subtotal())) : esc_html($order->get_subtotal()); ?></td>
                    </tr>
                    <?php if ($order->get_shipping_total() > 0) : ?>
                        <tr class="hng-shipping">
                            <th colspan="2"><?php esc_html_e('Frete', 'hng-commerce'); ?></th>
                            <td><?php echo function_exists('hng_price') ? esc_html(hng_price($order->get_shipping_total())) : esc_html($order->get_shipping_total()); ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php if ($order->get_discount_total() > 0) : ?>
                        <tr class="hng-discount">
                            <th colspan="2"><?php esc_html_e('Desconto', 'hng-commerce'); ?></th>
                            <td>-<?php echo function_exists('hng_price') ? esc_html(hng_price($order->get_discount_total())) : esc_html($order->get_discount_total()); ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr class="hng-order-total">
                        <th colspan="2"><?php esc_html_e('Total', 'hng-commerce'); ?></th>
                        <td><strong><?php echo wp_kses_post($order->get_formatted_total()); ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="hng-customer-details" role="region" aria-label="Endereço do cliente">
        <div class="hng-billing-address">
            <h3><?php esc_html_e('Endereço de Cobrança', 'hng-commerce'); ?></h3>
            <address>
                <?php echo esc_html($order->get_customer_name()); ?><br>
                <?php if (!empty($order->get_billing_address_1())) : ?>
                    <?php echo esc_html($order->get_billing_address_1()); ?>, <?php echo esc_html($order->get_billing_number()); ?>
                    <?php $addr2 = $order->get_billing_address_2(); if (!empty($addr2)) : ?> - <?php echo esc_html($addr2); ?><?php endif; ?><br>
                    <?php echo esc_html($order->get_billing_neighborhood()); ?><br>
                    <?php echo esc_html($order->get_billing_city()); ?> - <?php echo esc_html($order->get_billing_state()); ?><br>
                    CEP: <?php echo esc_html($order->get_billing_postcode()); ?><br>
                <?php endif; ?>
                <?php echo esc_html($order->get_customer_email()); ?><br>
                <?php echo esc_html($order->get_billing_phone()); ?>
            </address>
        </div>
    </div>

    <div class="hng-order-actions" tabindex="0">
        <a href="<?php echo esc_url(hng_get_shop_url()); ?>" class="hng-button hng-button-secondary"><?php esc_html_e('Continuar Comprando', 'hng-commerce'); ?></a>
        <a href="<?php echo esc_url(hng_get_account_url()); ?>" class="hng-button"><?php esc_html_e('Ver Meus Pedidos', 'hng-commerce'); ?></a>
    </div>
</div>

<style>
.hng-order-received { max-width:800px; margin:30px auto; padding:0 20px }
.hng-order-details, .hng-order-items, .hng-customer-details { background:#fff; padding:30px; margin-bottom:30px; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.1) }
.hng-order-info { width:100%; border-collapse:collapse; margin:20px 0 }
.hng-order-info th, .hng-order-info td { padding:10px; text-align:left; border-bottom:1px solid #e5e5e5 }
.hng-payment-info { background:#f9f9f9; padding:20px; border-radius:6px; margin-top:20px }
.hng-qrcode-placeholder { background:#fff; border:2px dashed #ddd; padding:40px; text-align:center; margin:20px 0; border-radius:6px }
.hng-pix-code-input { width:100%; padding:10px; border:1px solid #ddd; border-radius:4px; margin:10px 0; font-family:monospace }
.hng-order-items-table { width:100%; border-collapse:collapse }
.hng-order-items-table th, .hng-order-items-table td { padding:15px; text-align:left; border-bottom:1px solid #e5e5e5 }
.hng-order-total { font-size:18px }
.hng-billing-address address { font-style:normal; line-height:1.8 }
.hng-order-actions { text-align:center; margin-top:30px }
.hng-order-actions .hng-button { margin:0 10px }
</style>

<script>
jQuery(document).ready(function($) {
    $('.hng-copy-pix').on('click', function() {
        var input = $('.hng-pix-code-input');
        input.select();
        document.execCommand('copy');
        
        // translators: short feedback shown after PIX code is copied to clipboard
        $(this).text(<?php echo wp_json_encode( __( 'Copiado!', 'hng-commerce') ); ?>);
        setTimeout(function() {
            // translators: label for button that copies the PIX code
            $('.hng-copy-pix').text(<?php echo wp_json_encode( __( 'Copiar Código', 'hng-commerce') ); ?>);
        }, 2000);
    });
});
</script>
