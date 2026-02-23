<?php
/**
 * HNG Commerce - User Profile Handler
 *
 * Handles user profile updates, password changes, data export and account deletion (LGPD)
 *
 * @package HNG_Commerce
 * @since 1.3.0
 */

// phpcs:disable Squiz.Commenting.InlineComment.InvalidEndChar
// phpcs:disable Squiz.Commenting.FunctionComment.MissingParamTag
// phpcs:disable Squiz.Commenting.FunctionComment.Missing
// phpcs:disable Squiz.Commenting.ClassComment.Missing
// phpcs:disable Squiz.Commenting.VariableComment.MissingVar
// phpcs:disable Universal.Operators.DisallowShortTernary.Found
// phpcs:disable WordPress.PHP.YodaConditions.NotYoda
// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed
// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HNG_User_Profile {

	/**
	 * Singleton instance
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		// AJAX handlers - profile update
		add_action( 'wp_ajax_hng_update_profile', array( $this, 'ajax_update_profile' ) );
		add_action( 'wp_ajax_hng_update_address', array( $this, 'ajax_update_address' ) );
		add_action( 'wp_ajax_hng_update_password', array( $this, 'ajax_update_password' ) );

		// AJAX handlers - 2FA for password change
		add_action( 'wp_ajax_hng_send_password_2fa_code', array( $this, 'ajax_send_password_2fa_code' ) );
		add_action( 'wp_ajax_hng_verify_password_2fa_code', array( $this, 'ajax_verify_password_2fa_code' ) );

		// AJAX handlers - LGPD
		add_action( 'wp_ajax_hng_export_user_data', array( $this, 'ajax_export_data' ) );
		add_action( 'wp_ajax_hng_delete_account', array( $this, 'ajax_delete_account' ) );
	}

	/**
	 * Generate and send 2FA code for password change
	 */
	public function ajax_send_password_2fa_code() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'hng_update_password' ) ) {
			wp_send_json_error( array( 'message' => 'Sessão expirada. Recarregue a página.' ) );
			return;
		}

		// Must be logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Você precisa estar logado.' ) );
			return;
		}

		$user_id = get_current_user_id();
		$user    = get_user_by( 'id', $user_id );

		// Check if user has Google ID
		if ( get_user_meta( $user_id, '_hng_google_id', true ) ) {
			wp_send_json_error( array( 'message' => 'Usuários com login Google devem alterar a senha no Google.' ) );
			return;
		}

		// Validate current password first
		$current_password = isset( $_POST['current_password'] ) ? wp_unslash( $_POST['current_password'] ) : '';
		if ( ! wp_check_password( $current_password, $user->user_pass, $user_id ) ) {
			wp_send_json_error( array( 'message' => 'Senha atual incorreta.' ) );
			return;
		}

		// Rate limiting - max 3 codes per 10 minutes
		$rate_key   = 'hng_2fa_rate_' . $user_id;
		$rate_count = get_transient( $rate_key ) ?: 0;
		if ( $rate_count >= 3 ) {
			wp_send_json_error( array( 'message' => 'Muitas tentativas. Aguarde alguns minutos.' ) );
			return;
		}
		set_transient( $rate_key, $rate_count + 1, 600 );

		// Generate 6-digit code
		$code = sprintf( '%06d', random_int( 0, 999999 ) );

		// Store code with 10-minute expiration (use wp_hash_password for proper verification)
		$code_data = array(
			'code'     => wp_hash_password( $code ),
			'attempts' => 0,
			'created'  => time(),
		);
		set_transient( 'hng_password_2fa_' . $user_id, $code_data, 600 );

		// Get user display name
		$name       = get_user_meta( $user_id, '_hng_customer_name', true ) ?: $user->display_name;
		$first_name = explode( ' ', $name )[0];

		// Send email
		$subject = 'Código de Verificação - Alteração de Senha';
		$message = $this->get_2fa_email_template( $first_name, $code );

		// Use domain email as sender (required by server)
		$site_url   = wp_parse_url( home_url(), PHP_URL_HOST );
		$from_email = 'noreply@' . preg_replace( '/^www\./', '', $site_url );

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . get_bloginfo( 'name' ) . ' <' . $from_email . '>',
			'Reply-To: ' . get_option( 'admin_email' ),
		);

		$sent = wp_mail( $user->user_email, $subject, $message, $headers );

		if ( ! $sent ) {
			delete_transient( 'hng_password_2fa_' . $user_id );
			wp_send_json_error( array( 'message' => 'Erro ao enviar e-mail. Tente novamente.' ) );
			return;
		}

		// Mask email for display
		$email_parts  = explode( '@', $user->user_email );
		$masked_email = substr( $email_parts[0], 0, 3 ) . '***@' . $email_parts[1];

		wp_send_json_success(
			array(
				'message'    => 'Código enviado para ' . $masked_email,
				'email'      => $masked_email,
				'expires_in' => 600,
			)
		);
	}

	/**
	 * Verify 2FA code and change password
	 */
	public function ajax_verify_password_2fa_code() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'hng_update_password' ) ) {
			wp_send_json_error( array( 'message' => 'Sessão expirada. Recarregue a página.' ) );
			return;
		}

		// Must be logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Você precisa estar logado.' ) );
			return;
		}

		$user_id = get_current_user_id();
		$user    = get_user_by( 'id', $user_id );

		// Get stored code data
		$code_data = get_transient( 'hng_password_2fa_' . $user_id );
		if ( ! $code_data ) {
			wp_send_json_error( array( 'message' => 'Código expirado. Solicite um novo código.' ) );
			return;
		}

		// Check attempts
		if ( $code_data['attempts'] >= 5 ) {
			delete_transient( 'hng_password_2fa_' . $user_id );
			wp_send_json_error( array( 'message' => 'Muitas tentativas incorretas. Solicite um novo código.' ) );
			return;
		}

		// Verify code
		$submitted_code = isset( $_POST['verification_code'] ) ? sanitize_text_field( wp_unslash( $_POST['verification_code'] ) ) : '';
		$submitted_code = preg_replace( '/[^0-9]/', '', $submitted_code );

		if ( ! wp_check_password( $submitted_code, $code_data['code'] ) ) {
			// Increment attempts
			++$code_data['attempts'];
			set_transient( 'hng_password_2fa_' . $user_id, $code_data, 600 - ( time() - $code_data['created'] ) );

			$remaining = 5 - $code_data['attempts'];
			wp_send_json_error( array( 'message' => "Código incorreto. {$remaining} tentativas restantes." ) );
			return;
		}

		// Code verified - now change password
		$new_password     = isset( $_POST['new_password'] ) ? wp_unslash( $_POST['new_password'] ) : '';
		$confirm_password = isset( $_POST['confirm_password'] ) ? wp_unslash( $_POST['confirm_password'] ) : '';

		if ( strlen( $new_password ) < 8 ) {
			wp_send_json_error( array( 'message' => 'A nova senha deve ter pelo menos 8 caracteres.' ) );
			return;
		}

		if ( $new_password !== $confirm_password ) {
			wp_send_json_error( array( 'message' => 'As senhas não conferem.' ) );
			return;
		}

		// Delete used code
		delete_transient( 'hng_password_2fa_' . $user_id );

		// Update password
		wp_set_password( $new_password, $user_id );

		// Re-login user
		wp_set_auth_cookie( $user_id, true );

		// Log the password change
		do_action( 'hng_user_password_changed', $user_id );

		wp_send_json_success(
			array(
				'message' => 'Senha alterada com sucesso!',
			)
		);
	}

	/**
	 * Get 2FA email template
	 */
	private function get_2fa_email_template( $name, $code ) {
		$site_name = get_bloginfo( 'name' );
		$site_url  = home_url();

		return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="margin:0;padding:0;background-color:#0a0a0a;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Oxygen,Ubuntu,sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#0a0a0a;padding:40px 20px;">
                <tr>
                    <td align="center">
                        <table width="100%" cellpadding="0" cellspacing="0" style="max-width:500px;background:linear-gradient(135deg, rgba(163,230,53,0.1) 0%, rgba(59,130,246,0.1) 100%);border-radius:24px;border:1px solid rgba(255,255,255,0.1);padding:40px;">
                            <tr>
                                <td align="center" style="padding-bottom:30px;">
                                    <h1 style="color:#a3e635;margin:0;font-size:24px;">🔐 Verificação de Segurança</h1>
                                </td>
                            </tr>
                            <tr>
                                <td style="color:#e5e7eb;font-size:16px;line-height:1.6;">
                                    <p>Olá <strong style="color:#fff;">' . esc_html( $name ) . '</strong>,</p>
                                    <p>Você solicitou a alteração de senha da sua conta. Use o código abaixo para confirmar:</p>
                                </td>
                            </tr>
                            <tr>
                                <td align="center" style="padding:30px 0;">
                                    <div style="background:rgba(163,230,53,0.15);border:2px solid #a3e635;border-radius:16px;padding:20px 40px;display:inline-block;">
                                        <span style="font-size:36px;font-weight:bold;color:#a3e635;letter-spacing:8px;font-family:monospace;">' . esc_html( $code ) . '</span>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td style="color:#9ca3af;font-size:14px;text-align:center;padding-top:20px;">
                                    <p>⏱️ Este código expira em <strong style="color:#fff;">10 minutos</strong></p>
                                    <p style="color:#ef4444;margin-top:20px;">⚠️ Se você não solicitou esta alteração, ignore este e-mail e considere trocar sua senha.</p>
                                </td>
                            </tr>
                            <tr>
                                <td style="border-top:1px solid rgba(255,255,255,0.1);padding-top:30px;margin-top:30px;">
                                    <p style="color:#6b7280;font-size:12px;text-align:center;margin:0;">
                                        Este é um e-mail automático de <a href="' . esc_url( $site_url ) . '" style="color:#3b82f6;text-decoration:none;">' . esc_html( $site_name ) . '</a><br>
                                        Não responda a este e-mail.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>';
	}

	/**
	 * Update user profile
	 */
	public function ajax_update_profile() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'hng_update_profile' ) ) {
			wp_send_json_error( array( 'message' => 'Sessão expirada. Recarregue a página.' ) );
			return;
		}

		// Must be logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Você precisa estar logado.' ) );
			return;
		}

		$user_id = get_current_user_id();

		// Update client type if provided
		if ( ! empty( $_POST['client_type'] ) ) {
			$client_type = sanitize_text_field( wp_unslash( $_POST['client_type'] ) );
			if ( in_array( $client_type, array( 'provider', 'company' ), true ) ) {
				update_user_meta( $user_id, '_hng_customer_type', $client_type );
				update_user_meta( $user_id, '_hng_customer_client_type', $client_type );
			}
		}

		// Update name
		if ( ! empty( $_POST['name'] ) ) {
			$name = sanitize_text_field( wp_unslash( $_POST['name'] ) );
			update_user_meta( $user_id, '_hng_customer_name', $name );
			wp_update_user(
				array(
					'ID'           => $user_id,
					'display_name' => $name,
				)
			);
		}

		// Update phone
		if ( isset( $_POST['phone'] ) ) {
			$phone = preg_replace( '/[^0-9]/', '', sanitize_text_field( wp_unslash( $_POST['phone'] ) ) );
			update_user_meta( $user_id, '_hng_customer_phone', $phone );
		}

		// Update whatsapp
		if ( isset( $_POST['whatsapp'] ) ) {
			$whatsapp = preg_replace( '/[^0-9]/', '', sanitize_text_field( wp_unslash( $_POST['whatsapp'] ) ) );
			update_user_meta( $user_id, '_hng_customer_whatsapp', $whatsapp );
		}

		// Update CPF (provider)
		if ( isset( $_POST['cpf'] ) ) {
			$cpf = preg_replace( '/[^0-9]/', '', sanitize_text_field( wp_unslash( $_POST['cpf'] ) ) );
			update_user_meta( $user_id, '_hng_customer_cpf', $cpf );
		}

		// Update provider-specific fields
		if ( isset( $_POST['area'] ) ) {
			update_user_meta( $user_id, '_hng_customer_area', sanitize_text_field( wp_unslash( $_POST['area'] ) ) );
		}
		if ( isset( $_POST['company_area'] ) ) {
			update_user_meta( $user_id, '_hng_customer_area', sanitize_text_field( wp_unslash( $_POST['company_area'] ) ) );
		}
		if ( isset( $_POST['social_networks'] ) ) {
			update_user_meta( $user_id, '_hng_customer_social_networks', sanitize_textarea_field( wp_unslash( $_POST['social_networks'] ) ) );
		}
		if ( isset( $_POST['services_provided'] ) ) {
			update_user_meta( $user_id, '_hng_customer_services_provided', sanitize_textarea_field( wp_unslash( $_POST['services_provided'] ) ) );
		}

		// Update service needed
		if ( isset( $_POST['service_needed'] ) ) {
			update_user_meta( $user_id, '_hng_customer_service_needed', sanitize_text_field( wp_unslash( $_POST['service_needed'] ) ) );
		}
		if ( isset( $_POST['other_service'] ) ) {
			update_user_meta( $user_id, '_hng_customer_other_service', sanitize_textarea_field( wp_unslash( $_POST['other_service'] ) ) );
		}

		// Update company fields
		$company_fields = array( 'company_name', 'cnpj', 'company_email', 'responsible_name', 'responsible_role' );
		foreach ( $company_fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				$value = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
				if ( $field === 'cnpj' ) {
					$value = preg_replace( '/[^0-9]/', '', $value );
				}
				update_user_meta( $user_id, '_hng_customer_' . $field, $value );
			}
		}

		wp_send_json_success(
			array(
				'message' => 'Perfil atualizado com sucesso!',
			)
		);
	}

	/**
	 * Update user address
	 */
	public function ajax_update_address() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'hng_update_profile' ) ) {
			wp_send_json_error( array( 'message' => 'Sessão expirada. Recarregue a página.' ) );
			return;
		}

		// Must be logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Você precisa estar logado.' ) );
			return;
		}

		$user_id = get_current_user_id();

		// Address fields
		$address_fields = array( 'cep', 'address', 'number', 'complement', 'district', 'city', 'state' );
		foreach ( $address_fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				$value = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
				if ( $field === 'cep' ) {
					$value = preg_replace( '/[^0-9]/', '', $value );
				}
				update_user_meta( $user_id, '_hng_customer_' . $field, $value );
			}
		}

		wp_send_json_success(
			array(
				'message' => 'Endereço atualizado com sucesso!',
			)
		);
	}

	/**
	 * Update user password
	 */
	public function ajax_update_password() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'hng_update_password' ) ) {
			wp_send_json_error( array( 'message' => 'Sessão expirada. Recarregue a página.' ) );
			return;
		}

		// Must be logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Você precisa estar logado.' ) );
			return;
		}

		$user_id = get_current_user_id();
		$user    = get_user_by( 'id', $user_id );

		// Check if user has Google ID (can't change password)
		if ( get_user_meta( $user_id, '_hng_google_id', true ) ) {
			wp_send_json_error( array( 'message' => 'Usuários com login Google devem alterar a senha no Google.' ) );
			return;
		}

		// Validate current password
		$current_password = isset( $_POST['current_password'] ) ? sanitize_text_field( wp_unslash( $_POST['current_password'] ) ) : '';
		if ( ! wp_check_password( $current_password, $user->user_pass, $user_id ) ) {
			wp_send_json_error( array( 'message' => 'Senha atual incorreta.' ) );
			return;
		}

		// Validate new password
		$new_password     = isset( $_POST['new_password'] ) ? sanitize_text_field( wp_unslash( $_POST['new_password'] ) ) : '';
		$confirm_password = isset( $_POST['confirm_password'] ) ? sanitize_text_field( wp_unslash( $_POST['confirm_password'] ) ) : '';

		if ( strlen( $new_password ) < 8 ) {
			wp_send_json_error( array( 'message' => 'A nova senha deve ter pelo menos 8 caracteres.' ) );
			return;
		}

		if ( $new_password !== $confirm_password ) {
			wp_send_json_error( array( 'message' => 'As senhas não conferem.' ) );
			return;
		}

		// Update password
		wp_set_password( $new_password, $user_id );

		// Re-login user
		wp_set_auth_cookie( $user_id, true );

		wp_send_json_success(
			array(
				'message' => 'Senha alterada com sucesso!',
			)
		);
	}

	/**
	 * Export user data (LGPD)
	 */
	public function ajax_export_data() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'hng_export_data' ) ) {
			wp_send_json_error( array( 'message' => 'Sessão expirada. Recarregue a página.' ) );
			return;
		}

		// Must be logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Você precisa estar logado.' ) );
			return;
		}

		$user_id = get_current_user_id();
		$user    = get_user_by( 'id', $user_id );

		// Collect user data
		$user_data = array(
			'informacoes_basicas' => array(
				'nome'          => $user->display_name,
				'email'         => $user->user_email,
				'registrado_em' => $user->user_registered,
			),
			'dados_cadastrais'    => array(),
			'metadados'           => array(),
		);

		// Get all user meta with _hng_ prefix
		$all_meta = get_user_meta( $user_id );
		foreach ( $all_meta as $key => $values ) {
			if ( strpos( $key, '_hng_' ) === 0 ) {
				$clean_key                                   = str_replace( '_hng_customer_', '', $key );
				$clean_key                                   = str_replace( '_hng_', '', $clean_key );
				$user_data['dados_cadastrais'][ $clean_key ] = $values[0];
			}
		}

		// Convert to JSON
		$json_data = wp_json_encode( $user_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );

		// Send email with data
		$subject  = 'Seus Dados - HNG Desenvolvimentos';
		$message  = "Olá {$user->display_name},\n\n";
		$message .= "Conforme sua solicitação, segue abaixo uma cópia de todos os dados que armazenamos sobre você:\n\n";
		$message .= $json_data;
		$message .= "\n\nAtenciosamente,\nEquipe HNG Desenvolvimentos";

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		$sent = wp_mail( $user->user_email, $subject, $message, $headers );

		if ( $sent ) {
			wp_send_json_success(
				array(
					'message' => 'Seus dados foram enviados para seu e-mail!',
				)
			);
		} else {
			wp_send_json_error( array( 'message' => 'Erro ao enviar e-mail. Tente novamente.' ) );
		}
	}

	/**
	 * Delete user account (LGPD)
	 */
	public function ajax_delete_account() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'hng_delete_account' ) ) {
			wp_send_json_error( array( 'message' => 'Sessão expirada. Recarregue a página.' ) );
			return;
		}

		// Must be logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Você precisa estar logado.' ) );
			return;
		}

		// Confirm checkbox
		if ( empty( $_POST['confirm_delete'] ) ) {
			wp_send_json_error( array( 'message' => 'Você precisa confirmar a exclusão.' ) );
			return;
		}

		$user_id = get_current_user_id();
		$user    = get_user_by( 'id', $user_id );

		// Check password for non-Google users
		$has_google = get_user_meta( $user_id, '_hng_google_id', true );
		if ( ! $has_google ) {
			$password = isset( $_POST['password'] ) ? sanitize_text_field( wp_unslash( $_POST['password'] ) ) : '';
			if ( ! wp_check_password( $password, $user->user_pass, $user_id ) ) {
				wp_send_json_error( array( 'message' => 'Senha incorreta.' ) );
				return;
			}
		}

		// Don't allow admin deletion
		if ( user_can( $user_id, 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Administradores não podem excluir suas contas por aqui.' ) );
			return;
		}

		// Delete all user meta first
		$all_meta = get_user_meta( $user_id );
		foreach ( array_keys( $all_meta ) as $key ) {
			if ( strpos( $key, '_hng_' ) === 0 ) {
				delete_user_meta( $user_id, $key );
			}
		}

		// Log out user
		wp_logout();

		// Delete user (without reassigning posts - they're deleted too)
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $user_id );

		wp_send_json_success(
			array(
				'message'  => 'Sua conta foi excluída com sucesso.',
				'redirect' => home_url(),
			)
		);
	}

	/**
	 * Get account URL helper
	 */
	public static function get_account_url() {
		return home_url( '/minha-conta/' );
	}
}

// Initialize
HNG_User_Profile::instance();

/**
 * Helper function to get account URL
 */
if ( ! function_exists( 'hng_get_account_url' ) ) {
	function hng_get_account_url() {
		return HNG_User_Profile::get_account_url();
	}
}
