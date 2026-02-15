<?php
/**
 * Email Template: Orçamento Aprovado - Cliente
 * 
 * Enviado quando o admin aprova o orçamento
 *
 * @package HNG_Commerce
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Variáveis disponíveis:
// $customer_name, $quote_id, $approved_price, $approved_shipping, $total, $approval_notes, $payment_link, $quote_link, $site_name
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($site_name); ?> - Orçamento Aprovado</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden;">
                    <!-- Header -->
                    <tr>
                        <td style="background-color: #27ae60; padding: 30px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px;">🎉 Seu Orçamento foi Aprovado!</h1>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <p style="font-size: 16px; color: #333; margin: 0 0 20px;">
                                Olá <strong><?php echo esc_html($customer_name); ?></strong>,
                            </p>
                            
                            <p style="font-size: 16px; color: #666; margin: 0 0 20px; line-height: 1.6;">
                                Temos uma ótima notícia! Seu orçamento <strong>#<?php echo esc_html($quote_id); ?></strong> foi aprovado e está pronto para finalização.
                            </p>
                            
                            <div style="background-color: #d4edda; border-left: 4px solid #28a745; padding: 20px; margin: 20px 0;">
                                <p style="margin: 0; font-size: 14px; color: #155724;">
                                    <strong>✓ Próximo passo:</strong> Efetue o pagamento para iniciarmos o processamento do seu pedido.
                                </p>
                            </div>
                            
                            <h3 style="color: #333; font-size: 18px; margin: 30px 0 15px;">Valores Aprovados:</h3>
                            <table width="100%" cellpadding="10" cellspacing="0" style="border: 1px solid #e0e0e0; border-radius: 4px; margin: 20px 0;">
                                <tr style="background-color: #f8f9fa;">
                                    <td style="border-bottom: 1px solid #e0e0e0; font-size: 14px; color: #666;">
                                        <strong>Produtos:</strong>
                                    </td>
                                    <td style="border-bottom: 1px solid #e0e0e0; text-align: right; font-size: 14px; color: #666;">
                                        <?php echo esc_html($approved_price); ?>
                                    </td>
                                </tr>
                                <tr style="background-color: #f8f9fa;">
                                    <td style="border-bottom: 1px solid #e0e0e0; font-size: 14px; color: #666;">
                                        <strong>Frete:</strong>
                                    </td>
                                    <td style="border-bottom: 1px solid #e0e0e0; text-align: right; font-size: 14px; color: #666;">
                                        <?php echo esc_html($approved_shipping); ?>
                                    </td>
                                </tr>
                                <tr style="background-color: #e8f5e9;">
                                    <td style="font-size: 16px; color: #333; padding: 15px 10px;">
                                        <strong>Total:</strong>
                                    </td>
                                    <td style="text-align: right; font-size: 18px; color: #27ae60; padding: 15px 10px;">
                                        <strong><?php echo esc_html($total); ?></strong>
                                    </td>
                                </tr>
                            </table>
                            
                            <?php if (!empty($approval_notes)) : ?>
                            <h3 style="color: #333; font-size: 18px; margin: 30px 0 15px;">Observações:</h3>
                            <div style="background-color: #f8f9fa; border-radius: 4px; padding: 20px; margin: 20px 0;">
                                <p style="margin: 0; font-size: 14px; color: #666; line-height: 1.6;">
                                    <?php echo wp_kses_post(nl2br($approval_notes)); ?>
                                </p>
                            </div>
                            <?php endif; ?>
                            
                            <div style="text-align: center; margin: 30px 0;">
                                <a href="<?php echo esc_url($payment_link); ?>" 
                                   style="display: inline-block; padding: 15px 40px; background-color: #27ae60; color: #ffffff; text-decoration: none; border-radius: 4px; font-size: 16px; font-weight: bold; margin-right: 10px;">
                                    💳 Pagar Agora
                                </a>
                                <a href="<?php echo esc_url($quote_link); ?>" 
                                   style="display: inline-block; padding: 15px 40px; background-color: #6c757d; color: #ffffff; text-decoration: none; border-radius: 4px; font-size: 16px; font-weight: bold;">
                                    Ver Detalhes
                                </a>
                            </div>
                            
                            <p style="font-size: 14px; color: #999; margin: 20px 0 0; text-align: center; line-height: 1.6;">
                                Tem alguma dúvida? Entre em contato conosco através do chat no orçamento.
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 20px 30px; text-align: center; border-top: 1px solid #e0e0e0;">
                            <p style="margin: 0; font-size: 14px; color: #999;">
                                © <?php echo esc_html(gmdate('Y')); ?> <a href="<?php echo esc_url($site_url); ?>" style="color: #27ae60; text-decoration: none;"><?php echo esc_html($site_name); ?></a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
