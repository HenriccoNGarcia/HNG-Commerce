<?php
/**
 * Email Template: Novo Pedido de Orçamento - Admin
 *
 * Enviado ao admin quando um novo orçamento é solicitado
 *
 * @package HNG_Commerce
 * @version 1.0.0
 */

// phpcs:disable Squiz.Commenting.InlineComment.InvalidEndChar
// phpcs:disable Squiz.PHP.CommentedOutCode.Found

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Variáveis disponíveis:
// $customer_name, $customer_email, $customer_phone, $quote_id, $quote_date, $products, $admin_link, $site_name
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( $site_name ); ?> - Novo Orçamento</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
	<table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px;">
		<tr>
			<td align="center">
				<table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden;">
					<!-- Header -->
					<tr>
						<td style="background-color: #e74c3c; padding: 30px; text-align: center;">
							<h1 style="margin: 0; color: #ffffff; font-size: 28px;">🔔 Novo Pedido de Orçamento!</h1>
						</td>
					</tr>
					
					<!-- Content -->
					<tr>
						<td style="padding: 40px 30px;">
							<p style="font-size: 16px; color: #333; margin: 0 0 20px;">
								Um novo pedido de orçamento foi recebido e aguarda sua análise.
							</p>
							
							<div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 20px; margin: 20px 0;">
								<p style="margin: 0 0 10px; font-size: 14px; color: #856404;">
									<strong>⏰ Ação necessária:</strong> Analise e responda o orçamento o mais breve possível.
								</p>
							</div>
							
							<h3 style="color: #333; font-size: 18px; margin: 30px 0 15px;">Informações do Cliente:</h3>
							<div style="background-color: #f8f9fa; border-radius: 4px; padding: 20px; margin: 20px 0;">
								<p style="margin: 0 0 10px; font-size: 14px; color: #666;">
									<strong>Nome:</strong> <?php echo esc_html( $customer_name ); ?>
								</p>
								<p style="margin: 0 0 10px; font-size: 14px; color: #666;">
									<strong>Email:</strong> <a href="mailto:<?php echo esc_attr( $customer_email ); ?>" style="color: #3498db;"><?php echo esc_html( $customer_email ); ?></a>
								</p>
								<p style="margin: 0; font-size: 14px; color: #666;">
									<strong>Telefone:</strong> <?php echo esc_html( $customer_phone ); ?>
								</p>
							</div>
							
							<h3 style="color: #333; font-size: 18px; margin: 30px 0 15px;">Detalhes do Orçamento:</h3>
							<div style="background-color: #f8f9fa; border-left: 4px solid #e74c3c; padding: 20px; margin: 20px 0;">
								<p style="margin: 0 0 10px; font-size: 14px; color: #666;">
									<strong>ID:</strong> #<?php echo esc_html( $quote_id ); ?>
								</p>
								<p style="margin: 0; font-size: 14px; color: #666;">
									<strong>Data:</strong> <?php echo esc_html( $quote_date ); ?>
								</p>
							</div>
							
							<h3 style="color: #333; font-size: 18px; margin: 30px 0 15px;">Produtos Solicitados:</h3>
							<div style="border: 1px solid #e0e0e0; border-radius: 4px; padding: 15px;">
								<?php echo wp_kses_post( $products ); ?>
							</div>
							
							<div style="text-align: center; margin: 30px 0;">
								<a href="<?php echo esc_url( $admin_link ); ?>" 
									style="display: inline-block; padding: 15px 40px; background-color: #e74c3c; color: #ffffff; text-decoration: none; border-radius: 4px; font-size: 16px; font-weight: bold;">
									Responder Orçamento no Painel
								</a>
							</div>
						</td>
					</tr>
					
					<!-- Footer -->
					<tr>
						<td style="background-color: #f8f9fa; padding: 20px 30px; text-align: center; border-top: 1px solid #e0e0e0;">
							<p style="margin: 0; font-size: 14px; color: #999;">
								© <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( $site_name ); ?> - Painel Administrativo
							</p>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>
