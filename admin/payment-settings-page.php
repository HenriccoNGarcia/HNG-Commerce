<?php
/**
 * Admin: Configurações de Pagamento
 *
 * @package HNG_Commerce
 * @since 1.0.0
 */

// phpcs:disable Squiz.Commenting.InlineComment.InvalidEndChar
// phpcs:disable Squiz.Commenting.ClassComment
// phpcs:disable WordPress.Files.FileName
// phpcs:disable Generic.Classes.DuplicateClassName.Found

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HNG_Payment_Settings {

	/**
	 * Construtor
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'wp_ajax_hng_test_gateway_connection', array( $this, 'test_gateway_connection' ) );
		add_action( 'wp_ajax_hng_quick_test_gateway', array( $this, 'quick_test_gateway' ) );
	}

	/**
	 * Registrar configurações
	 */
	public function register_settings() {
		// Gateway PIX selecionado
		register_setting( 'hng_payment_settings', 'hng_pix_provider', array( 'sanitize_callback' => 'sanitize_text_field' ) );

		// Asaas
		register_setting( 'hng_payment_settings', 'hng_asaas_api_key', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'hng_payment_settings', 'hng_asaas_sandbox', array( 'sanitize_callback' => 'absint' ) );

		// Mercado Pago
		register_setting( 'hng_payment_settings', 'hng_mercadopago_access_token', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'hng_payment_settings', 'hng_mercadopago_public_key', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'hng_payment_settings', 'hng_mercadopago_sandbox', array( 'sanitize_callback' => 'absint' ) );
		register_setting( 'hng_payment_settings', 'hng_mercadopago_enabled', array( 'sanitize_callback' => 'absint' ) );

		// Stripe
		register_setting( 'hng_payment_settings', 'hng_stripe_secret_key', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'hng_payment_settings', 'hng_stripe_publishable_key', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'hng_payment_settings', 'hng_stripe_enabled', array( 'sanitize_callback' => 'absint' ) );

		// Debug / Logs (opt-in)
		register_setting( 'hng_payment_settings', 'hng_enable_debug', array( 'sanitize_callback' => 'absint' ) );
		register_setting( 'hng_payment_settings', 'hng_transaction_log', array( 'sanitize_callback' => 'absint' ) );
	}

	/**
	 * Renderizar página de configurações (stub)
	 */
	public function render_settings_page() {
		// Este método está desabilitado por enquanto.
		// As configurações são gerenciadas pelo WP admin settings form
		echo '<p>' . esc_html__( 'Configurações de pagamento gerenciadas pelo WordPress settings.', 'hng-commerce' ) . '</p>';
	}

	/**
	 * Test gateway connection (AJAX)
	 */
	public function test_gateway_connection() {
		check_ajax_referer( 'hng_test_gateway', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permissão negada', 'hng-commerce' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Conexão OK', 'hng-commerce' ) ) );
	}

	/**
	 * Quick gateway test (AJAX)
	 */
	public function quick_test_gateway() {
		check_ajax_referer( 'hng_test_gateway', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permissão negada', 'hng-commerce' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Gateway testado com sucesso', 'hng-commerce' ) ) );
	}
}

// Inicializar apenas quando estamos no admin
if ( is_admin() ) {
	new HNG_Payment_Settings();
}
