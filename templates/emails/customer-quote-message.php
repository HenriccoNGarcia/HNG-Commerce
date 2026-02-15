<?php
/**
 * Email Template: Nova Mensagem no Orçamento - Cliente
 * 
 * Enviado quando o admin envia uma mensagem no chat do orçamento
 *
 * @package HNG_Commerce
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Variáveis disponíveis:
// $customer_name, $quote_id, $message, $quote_link, $site_name
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($site_name); ?> - Nova Mensagem</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden;">
                    <!-- Header -->
                    <tr>
                        <td style="background-color: #3498db; padding: 30px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px;">💬 Nova Mensagem no seu Orçamento</h1>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <p style="font-size: 16px; color: #333; margin: 0 0 20px;">
                                Olá <strong><?php echo esc_html($customer_name); ?></strong>,
                            </p>
                            
                            <p style="font-size: 16px; color: #666; margin: 0 0 20px; line-height: 1.6;">
                                Nossa equipe enviou uma nova mensagem sobre seu orçamento <strong>#<?php echo esc_html($quote_id); ?></strong>.
                            </p>
                            
                            <div style="background-color: #e3f2fd; border-left: 4px solid #2196f3; padding: 20px; margin: 20px 0;">
                                <p style="margin: 0 0 10px; font-size: 12px; color: #1976d2; text-transform: uppercase; font-weight: bold;">
                                    Mensagem da Equipe:
                                </p>
                                <p style="margin: 0; font-size: 15px; color: #333; line-height: 1.6; white-space: pre-wrap;">
                                    <?php echo wp_kses_post($message); ?>
                                </p>
                            </div>
                            
                            <div style="text-align: center; margin: 30px 0;">
                                <a href="<?php echo esc_url($quote_link); ?>" 
                                   style="display: inline-block; padding: 15px 40px; background-color: #3498db; color: #ffffff; text-decoration: none; border-radius: 4px; font-size: 16px; font-weight: bold;">
                                    Responder Mensagem
                                </a>
                            </div>
                            
                            <p style="font-size: 14px; color: #999; margin: 20px 0 0; text-align: center; line-height: 1.6;">
                                Clique no botão acima para continuar a conversa e esclarecer suas dúvidas.
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
