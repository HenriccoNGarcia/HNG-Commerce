<?php
/**
 * Email Template: Pedido de Orçamento - Cliente
 * 
 * Enviado quando cliente solicita um orçamento
 *
 * @package HNG_Commerce
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Váriaveis disponíveis:
// $customer_name, $quote_id, $quote_date, $products, $quote_link, $site_name, $site_url
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($site_name); ?> - Pedido de Orçamento</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden;">
                    <!-- Header -->
                    <tr>
                        <td style="background-color: #3498db; padding: 30px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px;">✅ Pedido de Orçamento Recebido!</h1>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <p style="font-size: 16px; color: #333; margin: 0 0 20px;">
                                Olá <strong><?php echo esc_html($customer_name); ?></strong>,
                            </p>
                            
                            <p style="font-size: 16px; color: #666; margin: 0 0 20px; line-height: 1.6;">
                                Recebemos seu pedido de orçamento com sucesso! Nossa equipe irá analisar sua solicitação e entrar em contato em breve com uma proposta personalizada.
                            </p>
                            
                            <div style="background-color: #f8f9fa; border-left: 4px solid #3498db; padding: 20px; margin: 20px 0;">
                                <p style="margin: 0 0 10px; font-size: 14px; color: #666;">
                                    <strong>ID do Orçamento:</strong> #<?php echo esc_html($quote_id); ?>
                                </p>
                                <p style="margin: 0; font-size: 14px; color: #666;">
                                    <strong>Data:</strong> <?php echo esc_html($quote_date); ?>
                                </p>
                            </div>
                            
                            <h3 style="color: #333; font-size: 18px; margin: 30px 0 15px;">Produtos Solicitados:</h3>
                            <div style="border: 1px solid #e0e0e0; border-radius: 4px; padding: 15px;">
                                <?php echo wp_kses_post($products); ?>
                            </div>
                            
                            <div style="text-align: center; margin: 30px 0;">
                                <a href="<?php echo esc_url($quote_link); ?>" 
                                   style="display: inline-block; padding: 15px 40px; background-color: #27ae60; color: #ffffff; text-decoration: none; border-radius: 4px; font-size: 16px; font-weight: bold;">
                                    Acompanhar Orçamento
                                </a>
                            </div>
                            
                            <p style="font-size: 14px; color: #999; margin: 20px 0 0; line-height: 1.6;">
                                Você pode acompanhar o status do seu orçamento e trocar mensagens com nossa equipe através do link acima.
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 20px 30px; text-align: center; border-top: 1px solid #e0e0e0;">
                            <p style="margin: 0; font-size: 14px; color: #999;">
                                © <?php echo esc_html(gmdate('Y')); ?> <a href="<?php echo esc_url($site_url); ?>" style="color: #3498db; text-decoration: none;"><?php echo esc_html($site_name); ?></a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
