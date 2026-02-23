<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName
/**
 * HNG Commerce - Email Customizer Page
 *
 * @package HNG_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable Squiz.Commenting.InlineComment.InvalidEndChar
// phpcs:disable Squiz.Commenting.FunctionComment.MissingParamTag
// phpcs:disable Universal.Operators.DisallowShortTernary.Found
// phpcs:disable WordPress.PHP.YodaConditions.NotYoda
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
// phpcs:disable Squiz.Commenting.ClassComment.Missing

class HNG_Email_Customizer_Page {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Register AJAX handlers
		add_action( 'wp_ajax_hng_save_email_template', array( $this, 'save_email_template' ) );
		add_action( 'wp_ajax_hng_get_email_preview', array( $this, 'get_email_preview' ) );
		add_action( 'wp_ajax_hng_send_test_email', array( $this, 'send_test_email' ) );
		add_action( 'wp_ajax_hng_reset_email_template', array( $this, 'reset_email_template' ) );
		add_action( 'wp_ajax_hng_use_global_settings', array( $this, 'use_global_settings' ) );
	}

	/**
	 * Render the page
	 */
	public static function render() {
		// Get current email type from query parameter
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET parameter
		$current_type = isset( $_GET['email_type'] ) ? sanitize_text_field( wp_unslash( $_GET['email_type'] ) ) : 'global_settings';

		// Check if we're on global settings
		$is_global_settings = ( $current_type === 'global_settings' );

		// Available email types - organized by category
		$all_email_types = array(
			'global_settings'          => array(
				'name'        => __( '⚙️ Configurações Globais', 'hng-commerce' ),
				'category'    => 'settings',
				'always_show' => true,
			),
			// Pedidos Normais (sempre visível)
			'new_order'                => array(
				'name'        => __( 'Novo Pedido', 'hng-commerce' ),
				'category'    => 'orders',
				'always_show' => true,
			),
			'order_paid'               => array(
				'name'        => __( 'Pedido Pago', 'hng-commerce' ),
				'category'    => 'orders',
				'always_show' => true,
			),
			'order_cancelled'          => array(
				'name'        => __( 'Pedido Cancelado', 'hng-commerce' ),
				'category'    => 'orders',
				'always_show' => true,
			),
			// Orçamentos (quote)
			'quote_request'            => array(
				'name'         => __( 'Pedido de Orçamento - Cliente', 'hng-commerce' ),
				'category'     => 'quote',
				'product_type' => 'quote',
			),
			'quote_admin_new'          => array(
				'name'         => __( 'Novo Orçamento - Admin', 'hng-commerce' ),
				'category'     => 'quote',
				'product_type' => 'quote',
			),
			'quote_received'           => array(
				'name'         => __( 'Orçamento Recebido - Cliente', 'hng-commerce' ),
				'category'     => 'quote',
				'product_type' => 'quote',
			),
			'quote_approved'           => array(
				'name'         => __( 'Orçamento Aprovado - Admin', 'hng-commerce' ),
				'category'     => 'quote',
				'product_type' => 'quote',
			),
			'quote_message'            => array(
				'name'         => __( 'Nova Mensagem - Orçamento', 'hng-commerce' ),
				'category'     => 'quote',
				'product_type' => 'quote',
			),
			// Assinaturas (subscription)
			'new_subscription'         => array(
				'name'         => __( 'Nova Assinatura', 'hng-commerce' ),
				'category'     => 'subscription',
				'product_type' => 'subscription',
			),
			'subscription_renewed'     => array(
				'name'         => __( 'Assinatura Renovada', 'hng-commerce' ),
				'category'     => 'subscription',
				'product_type' => 'subscription',
			),
			'subscription_cancelled'   => array(
				'name'         => __( 'Assinatura Cancelada', 'hng-commerce' ),
				'category'     => 'subscription',
				'product_type' => 'subscription',
			),
			// Agendamentos (appointment)
			'appointment_confirmation' => array(
				'name'         => __( 'Agendamento Confirmado', 'hng-commerce' ),
				'category'     => 'appointment',
				'product_type' => 'appointment',
			),
			'appointment_cancelled'    => array(
				'name'         => __( 'Agendamento Cancelado', 'hng-commerce' ),
				'category'     => 'appointment',
				'product_type' => 'appointment',
			),
			// Produtos Digitais
			'digital_access_granted'   => array(
				'name'         => __( 'Acesso Digital Liberado', 'hng-commerce' ),
				'category'     => 'digital',
				'product_type' => 'digital',
			),
			// Reembolsos
			'refund_request'           => array(
				'name'        => __( 'Solicitação de Reembolso', 'hng-commerce' ),
				'category'    => 'payments',
				'always_show' => true,
			),
			'refund_processed'         => array(
				'name'        => __( 'Reembolso Processado', 'hng-commerce' ),
				'category'    => 'payments',
				'always_show' => true,
			),
			// Pagamentos
			'payment_received'         => array(
				'name'        => __( 'Pagamento Recebido', 'hng-commerce' ),
				'category'    => 'payments',
				'always_show' => true,
			),
			'payment_failed'           => array(
				'name'        => __( 'Pagamento Falhou', 'hng-commerce' ),
				'category'    => 'payments',
				'always_show' => true,
			),
		);

		// Filter email types based on enabled product types
		$email_types = self::filter_email_types_by_active_products( $all_email_types );

		// Validate current type
		if ( $current_type !== 'global_settings' && ! isset( $email_types[ $current_type ] ) ) {
			$current_type = 'global_settings';
		}

		// Define available variables for each email type
		$email_variables = array(
			'new_order'                => array(
				'{{customer_name}}'  => __( 'Nome do cliente', 'hng-commerce' ),
				'{{order_id}}'       => __( 'ID do Pedido', 'hng-commerce' ),
				'{{order_date}}'     => __( 'Data do Pedido', 'hng-commerce' ),
				'{{order_total}}'    => __( 'Total do Pedido', 'hng-commerce' ),
				'{{products}}'       => __( 'Lista de Produtos', 'hng-commerce' ),
				'{{payment_method}}' => __( 'Método de Pagamento', 'hng-commerce' ),
				'{{tracking_url}}'   => __( 'URL de Rastreamento', 'hng-commerce' ),
			),
			'order_paid'               => array(
				'{{customer_name}}' => __( 'Nome do cliente', 'hng-commerce' ),
				'{{order_id}}'      => __( 'ID do Pedido', 'hng-commerce' ),
				'{{amount_paid}}'   => __( 'Valor Pago', 'hng-commerce' ),
				'{{payment_date}}'  => __( 'Data do Pagamento', 'hng-commerce' ),
				'{{receipt_url}}'   => __( 'URL do Recibo', 'hng-commerce' ),
			),
			'order_cancelled'          => array(
				'{{customer_name}}'       => __( 'Nome do cliente', 'hng-commerce' ),
				'{{order_id}}'            => __( 'ID do Pedido', 'hng-commerce' ),
				'{{cancellation_reason}}' => __( 'Motivo do Cancelamento', 'hng-commerce' ),
				'{{refund_amount}}'       => __( 'Valor do Reembolso', 'hng-commerce' ),
			),
			'new_subscription'         => array(
				'{{customer_name}}'   => __( 'Nome do cliente', 'hng-commerce' ),
				'{{subscription_id}}' => __( 'ID da Assinatura', 'hng-commerce' ),
				'{{plan_name}}'       => __( 'Nome do Plano', 'hng-commerce' ),
				'{{renewal_date}}'    => __( 'Data de Renovação', 'hng-commerce' ),
			),
			'subscription_renewed'     => array(
				'{{customer_name}}'   => __( 'Nome do cliente', 'hng-commerce' ),
				'{{subscription_id}}' => __( 'ID da Assinatura', 'hng-commerce' ),
				'{{renewal_amount}}'  => __( 'Valor da Renovação', 'hng-commerce' ),
				'{{next_renewal}}'    => __( 'Próxima Renovação', 'hng-commerce' ),
			),
			'subscription_cancelled'   => array(
				'{{customer_name}}'     => __( 'Nome do cliente', 'hng-commerce' ),
				'{{subscription_id}}'   => __( 'ID da Assinatura', 'hng-commerce' ),
				'{{cancellation_date}}' => __( 'Data do Cancelamento', 'hng-commerce' ),
			),
			'payment_received'         => array(
				'{{customer_name}}' => __( 'Nome do cliente', 'hng-commerce' ),
				'{{amount}}'        => __( 'Valor Recebido', 'hng-commerce' ),
				'{{payment_date}}'  => __( 'Data do Pagamento', 'hng-commerce' ),
				'{{payment_id}}'    => __( 'ID do Pagamento', 'hng-commerce' ),
			),
			'payment_failed'           => array(
				'{{customer_name}}'  => __( 'Nome do cliente', 'hng-commerce' ),
				'{{order_id}}'       => __( 'ID do Pedido', 'hng-commerce' ),
				'{{failure_reason}}' => __( 'Motivo da Falha', 'hng-commerce' ),
				'{{retry_url}}'      => __( 'URL para Tentar Novamente', 'hng-commerce' ),
			),
			'quote_request'            => array(
				'{{customer_name}}' => __( 'Nome do cliente', 'hng-commerce' ),
				'{{quote_id}}'      => __( 'ID do Orçamento', 'hng-commerce' ),
				'{{quote_date}}'    => __( 'Data do Orçamento', 'hng-commerce' ),
				'{{products}}'      => __( 'Lista de Produtos', 'hng-commerce' ),
				'{{quote_link}}'    => __( 'Link do Orçamento', 'hng-commerce' ),
			),
			'quote_admin_new'          => array(
				'{{customer_name}}'  => __( 'Nome do cliente', 'hng-commerce' ),
				'{{customer_email}}' => __( 'Email do cliente', 'hng-commerce' ),
				'{{customer_phone}}' => __( 'Telefone do cliente', 'hng-commerce' ),
				'{{quote_id}}'       => __( 'ID do Orçamento', 'hng-commerce' ),
				'{{quote_date}}'     => __( 'Data do Orçamento', 'hng-commerce' ),
				'{{products}}'       => __( 'Lista de Produtos', 'hng-commerce' ),
				'{{admin_link}}'     => __( 'Link Admin', 'hng-commerce' ),
			),
			'quote_received'           => array(
				'{{customer_name}}'  => __( 'Nome do cliente', 'hng-commerce' ),
				'{{quote_id}}'       => __( 'ID do Orçamento', 'hng-commerce' ),
				'{{quote_date}}'     => __( 'Data do Orçamento', 'hng-commerce' ),
				'{{quote_price}}'    => __( 'Valor do Orçamento', 'hng-commerce' ),
				'{{quote_shipping}}' => __( 'Valor do Frete', 'hng-commerce' ),
				'{{quote_total}}'    => __( 'Total do Orçamento', 'hng-commerce' ),
				'{{quote_link}}'     => __( 'Link do Orçamento', 'hng-commerce' ),
				'{{products}}'       => __( 'Lista de Produtos', 'hng-commerce' ),
			),
			'quote_approved'           => array(
				'{{customer_name}}'     => __( 'Nome do cliente', 'hng-commerce' ),
				'{{quote_id}}'          => __( 'ID do Orçamento', 'hng-commerce' ),
				'{{approved_price}}'    => __( 'Preço Aprovado', 'hng-commerce' ),
				'{{approved_shipping}}' => __( 'Frete Aprovado', 'hng-commerce' ),
				'{{total}}'             => __( 'Total', 'hng-commerce' ),
				'{{approval_notes}}'    => __( 'Observações da Aprovação', 'hng-commerce' ),
				'{{quote_link}}'        => __( 'Link do Orçamento', 'hng-commerce' ),
				'{{admin_link}}'        => __( 'Link Admin', 'hng-commerce' ),
			),
			'quote_message'            => array(
				'{{customer_name}}' => __( 'Nome do cliente', 'hng-commerce' ),
				'{{quote_id}}'      => __( 'ID do Orçamento', 'hng-commerce' ),
				'{{message}}'       => __( 'Mensagem', 'hng-commerce' ),
				'{{quote_link}}'    => __( 'Link do Orçamento', 'hng-commerce' ),
			),
			'appointment_confirmation' => array(
				'{{customer_name}}'    => __( 'Nome do cliente', 'hng-commerce' ),
				'{{service_name}}'     => __( 'Nome do Serviço', 'hng-commerce' ),
				'{{appointment_date}}' => __( 'Data do Agendamento', 'hng-commerce' ),
				'{{appointment_time}}' => __( 'Horário do Agendamento', 'hng-commerce' ),
				'{{duration}}'         => __( 'Duração (minutos)', 'hng-commerce' ),
				'{{location}}'         => __( 'Local/Informações de Encontro', 'hng-commerce' ),
			),
			'appointment_cancelled'    => array(
				'{{customer_name}}'       => __( 'Nome do cliente', 'hng-commerce' ),
				'{{service_name}}'        => __( 'Nome do Serviço', 'hng-commerce' ),
				'{{appointment_date}}'    => __( 'Data do Agendamento', 'hng-commerce' ),
				'{{appointment_time}}'    => __( 'Horário do Agendamento', 'hng-commerce' ),
				'{{cancellation_reason}}' => __( 'Motivo do Cancelamento', 'hng-commerce' ),
			),
			'digital_access_granted'   => array(
				'{{customer_name}}'            => __( 'Nome do cliente', 'hng-commerce' ),
				'{{product_name}}'             => __( 'Nome do Produto', 'hng-commerce' ),
				'{{download_link}}'            => __( 'Link de Download', 'hng-commerce' ),
				'{{download_count_remaining}}' => __( 'Downloads Restantes', 'hng-commerce' ),
				'{{expires_at}}'               => __( 'Data de Expiração', 'hng-commerce' ),
				'{{order_id}}'                 => __( 'ID do Pedido', 'hng-commerce' ),
				'{{file_count}}'               => __( 'Número de Arquivos', 'hng-commerce' ),
			),
			'refund_request'           => array(
				'{{customer_name}}' => __( 'Nome do cliente', 'hng-commerce' ),
				'{{order_id}}'      => __( 'ID do Pedido', 'hng-commerce' ),
				'{{refund_amount}}' => __( 'Valor Solicitado', 'hng-commerce' ),
				'{{reason}}'        => __( 'Motivo da Solicitação', 'hng-commerce' ),
				'{{request_date}}'  => __( 'Data da Solicitação', 'hng-commerce' ),
				'{{admin_link}}'    => __( 'Link para Aprovar/Rejeitar', 'hng-commerce' ),
			),
			'refund_processed'         => array(
				'{{customer_name}}'     => __( 'Nome do cliente', 'hng-commerce' ),
				'{{order_id}}'          => __( 'ID do Pedido', 'hng-commerce' ),
				'{{refund_amount}}'     => __( 'Valor do Reembolso', 'hng-commerce' ),
				'{{original_amount}}'   => __( 'Valor Original', 'hng-commerce' ),
				'{{refund_date}}'       => __( 'Data do Reembolso', 'hng-commerce' ),
				'{{refund_reason}}'     => __( 'Motivo do Reembolso', 'hng-commerce' ),
				'{{estimated_arrival}}' => __( 'Previsão de Chegada', 'hng-commerce' ),
				'{{payment_method}}'    => __( 'Método de Pagamento', 'hng-commerce' ),
			),
		);

		// Get email info for current type
		$email_info = array(
			'variables' => isset( $email_variables[ $current_type ] ) ? $email_variables[ $current_type ] : array(),
		);

		// Carregar configurações globais
		$global_settings = get_option( 'hng_email_global_settings', array() );

		// Get saved template (específico do tipo de email)
		$saved_template = get_option( "hng_email_template_{$current_type}", array() );

		// Preparar settings com defaults que incluem configurações globais
		$settings = array(
			'subject'      => isset( $saved_template['subject'] ) ? $saved_template['subject'] : '',
			'from_name'    => isset( $saved_template['from_name'] ) ? $saved_template['from_name'] : get_bloginfo( 'name' ),
			'from_email'   => isset( $saved_template['from_email'] ) ? $saved_template['from_email'] : get_option( 'admin_email' ),
			'header'       => isset( $saved_template['header'] ) ? $saved_template['header'] : '',
			'body'         => isset( $saved_template['body'] ) ? $saved_template['body'] : '',
			'content'      => isset( $saved_template['content'] ) ? $saved_template['content'] : '',
			'footer'       => isset( $saved_template['footer'] ) && ! empty( $saved_template['footer'] ) ? $saved_template['footer'] : ( ! empty( $global_settings['footer_text'] ) ? $global_settings['footer_text'] : '' ),
			'styles'       => isset( $saved_template['styles'] ) ? $saved_template['styles'] : array(),
			'logo'         => isset( $saved_template['logo'] ) && ! empty( $saved_template['logo'] ) ? $saved_template['logo'] : ( ! empty( $global_settings['logo_url'] ) ? $global_settings['logo_url'] : '' ),
			'header_color' => isset( $saved_template['header_color'] ) && ! empty( $saved_template['header_color'] ) ? $saved_template['header_color'] : ( ! empty( $global_settings['header_color'] ) ? $global_settings['header_color'] : '#0073aa' ),
			'button_color' => isset( $saved_template['button_color'] ) && ! empty( $saved_template['button_color'] ) ? $saved_template['button_color'] : ( ! empty( $global_settings['button_color'] ) ? $global_settings['button_color'] : '#0073aa' ),
			'text_color'   => isset( $saved_template['text_color'] ) && ! empty( $saved_template['text_color'] ) ? $saved_template['text_color'] : ( ! empty( $global_settings['text_color'] ) ? $global_settings['text_color'] : '#333333' ),
			'bg_color'     => isset( $saved_template['bg_color'] ) && ! empty( $saved_template['bg_color'] ) ? $saved_template['bg_color'] : ( ! empty( $global_settings['content_bg_color'] ) ? $global_settings['content_bg_color'] : '#ffffff' ),
			'custom_css'   => isset( $saved_template['custom_css'] ) ? $saved_template['custom_css'] : '',
		);

		// Se não tem conteúdo, usar template padrão
		if ( empty( $settings['content'] ) ) {
			$settings['content'] = self::get_default_content_for_type( $current_type );
		}

		// Get recent orders for preview selector
		$orders = array();
		// TODO: Fetch real orders if you have an Order class

		// Enqueue assets
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_media();

		// Enqueue inline styles
		wp_enqueue_style( 'wp-editor' );
		wp_enqueue_script( 'jquery' );

		// Include template file
		$template_file = HNG_COMMERCE_PATH . 'includes/admin/pages/email-customizer-template.php';
		if ( file_exists( $template_file ) ) {
			include $template_file;
		}

		// Include styles inline
		$styles_file = HNG_COMMERCE_PATH . 'includes/admin/pages/email-customizer-styles.php';
		if ( file_exists( $styles_file ) ) {
			include $styles_file;
		}

		// Define global variables for inline script
		echo '<script>
            var hngEmailCustomizer = {
                ajax_url: "' . esc_url( admin_url( 'admin-ajax.php' ) ) . '",
                nonce: "' . esc_attr( wp_create_nonce( 'hng_email_customizer' ) ) . '",
                current_type: "' . esc_attr( $current_type ) . '",
                i18n: {
                    saving: "' . esc_attr__( 'Salvando...', 'hng-commerce' ) . '",
                    saved: "' . esc_attr__( 'Salvo!', 'hng-commerce' ) . '",
                    error: "' . esc_attr__( 'Erro ao salvar', 'hng-commerce' ) . '",
                    sending: "' . esc_attr__( 'Enviando...', 'hng-commerce' ) . '",
                    sent: "' . esc_attr__( 'Email enviado!', 'hng-commerce' ) . '",
                    send_error: "' . esc_attr__( 'Erro ao enviar email', 'hng-commerce' ) . '"
                }
            };
            var ajaxurl = "' . esc_url( admin_url( 'admin-ajax.php' ) ) . '";
        </script>';

		// Include scripts inline
		$scripts_file = HNG_COMMERCE_PATH . 'includes/admin/pages/email-customizer-scripts.php';
		if ( file_exists( $scripts_file ) ) {
			include $scripts_file;
		}
	}

	/**
	 * AJAX: Save email template
	 */
	public function save_email_template() {
		check_ajax_referer( 'hng_email_customizer', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão', 'hng-commerce' ) ) );
		}

		// Get and sanitize POST data
		$post       = function_exists( 'wp_unslash' ) ? wp_unslash( $_POST ) : $_POST;
		$email_type = isset( $post['email_type'] ) ? sanitize_text_field( $post['email_type'] ) : '';

		if ( empty( $email_type ) ) {
			wp_send_json_error( array( 'message' => __( 'Tipo de email inválido', 'hng-commerce' ) ) );
		}

		// Carregar configurações globais para comparação
		$global_settings = get_option( 'hng_email_global_settings', array() );

		$template = array(
			'subject'    => isset( $post['subject'] ) ? sanitize_text_field( $post['subject'] ) : '',
			'from_name'  => isset( $post['from_name'] ) ? sanitize_text_field( $post['from_name'] ) : get_bloginfo( 'name' ),
			'from_email' => isset( $post['from_email'] ) ? sanitize_email( $post['from_email'] ) : get_option( 'admin_email' ),
			'content'    => isset( $post['email_content'] ) ? wp_kses_post( $post['email_content'] ) : '',
		);

		// Salvar cores, logo e footer APENAS se não forem vazios
		// Se estão vazios, não salvamos (assim usaremos as configurações globais)
		if ( isset( $post['logo'] ) && ! empty( $post['logo'] ) ) {
			$template['logo'] = esc_url_raw( $post['logo'] );
		}
		if ( isset( $post['header_color'] ) && ! empty( $post['header_color'] ) ) {
			$template['header_color'] = sanitize_hex_color( $post['header_color'] );
		}
		if ( isset( $post['button_color'] ) && ! empty( $post['button_color'] ) ) {
			$template['button_color'] = sanitize_hex_color( $post['button_color'] );
		}
		if ( isset( $post['text_color'] ) && ! empty( $post['text_color'] ) ) {
			$template['text_color'] = sanitize_hex_color( $post['text_color'] );
		}
		if ( isset( $post['bg_color'] ) && ! empty( $post['bg_color'] ) ) {
			$template['bg_color'] = sanitize_hex_color( $post['bg_color'] );
		}

		update_option( "hng_email_template_{$email_type}", $template );

		wp_send_json_success( array( 'message' => __( 'Template salvo com sucesso!', 'hng-commerce' ) ) );
	}

	/**
	 * AJAX: Get email preview
	 */
	public function get_email_preview() {
		check_ajax_referer( 'hng_email_customizer', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão', 'hng-commerce' ) ) );
		}

		// Get POST data
		$post       = function_exists( 'wp_unslash' ) ? wp_unslash( $_POST ) : $_POST;
		$email_type = isset( $post['email_type'] ) ? sanitize_text_field( $post['email_type'] ) : '';

		if ( empty( $email_type ) ) {
			wp_send_json_error( array( 'message' => __( 'Tipo de email inválido', 'hng-commerce' ) ) );
		}

		// Verificar se há dados "live" (preview em tempo real)
		$has_live_data = isset( $post['live_content'] );

		if ( $has_live_data ) {
			// Usar dados do formulário para preview em tempo real
			$template = array(
				'content'      => isset( $post['live_content'] ) ? wp_kses_post( $post['live_content'] ) : '',
				'logo'         => isset( $post['live_logo'] ) ? esc_url_raw( $post['live_logo'] ) : '',
				'header_color' => isset( $post['live_header_color'] ) && ! empty( $post['live_header_color'] ) ? sanitize_hex_color( $post['live_header_color'] ) : '#0073aa',
				'button_color' => isset( $post['live_button_color'] ) && ! empty( $post['live_button_color'] ) ? sanitize_hex_color( $post['live_button_color'] ) : '#0073aa',
				'text_color'   => isset( $post['live_text_color'] ) && ! empty( $post['live_text_color'] ) ? sanitize_hex_color( $post['live_text_color'] ) : '#333333',
				'bg_color'     => isset( $post['live_bg_color'] ) && ! empty( $post['live_bg_color'] ) ? sanitize_hex_color( $post['live_bg_color'] ) : '#ffffff',
			);
		} else {
			// Carregar configurações globais
			$global_settings = get_option( 'hng_email_global_settings', array() );

			// Preparar defaults com configurações globais
			$defaults = array(
				'content'      => '',
				'logo'         => ! empty( $global_settings['logo_url'] ) ? $global_settings['logo_url'] : '',
				'header_color' => ! empty( $global_settings['header_color'] ) ? $global_settings['header_color'] : '#0073aa',
				'button_color' => ! empty( $global_settings['button_color'] ) ? $global_settings['button_color'] : '#0073aa',
				'text_color'   => ! empty( $global_settings['text_color'] ) ? $global_settings['text_color'] : '#333333',
				'bg_color'     => ! empty( $global_settings['content_bg_color'] ) ? $global_settings['content_bg_color'] : '#ffffff',
			);

			// Get saved template
			$saved_template = get_option( "hng_email_template_{$email_type}", array() );

			// Merge: começar com defaults (configurações globais) e sobrescrever com valores específicos se não vazios
			$template = $defaults;
			foreach ( $saved_template as $key => $value ) {
				if ( isset( $value ) && $value !== '' ) {
					$template[ $key ] = $value;
				}
			}
		}

		// Se não tem conteúdo, usar template padrão
		if ( empty( $template['content'] ) ) {
			$template['content'] = $this->get_default_template_content( $email_type );
		}

		// Substituir variáveis por dados de exemplo
		$content = $this->replace_variables_with_sample_data( $template['content'], $email_type );

		// Build email HTML with proper styling
		$html = $this->build_email_html( $template, $content );

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * Obter conteúdo padrão do template por tipo (estático para uso no render)
	 */
	public static function get_default_content_for_type( $email_type ) {
		$defaults = array(
			'new_order'                => '<h2>Olá {{customer_name}}!</h2>
<p>Recebemos seu pedido <strong>#{{order_id}}</strong> em {{order_date}}.</p>
<p><strong>Itens do Pedido:</strong></p>
{{products}}
<p><strong>Total:</strong> {{order_total}}</p>
<p><strong>Forma de Pagamento:</strong> {{payment_method}}</p>
<p>Você receberá uma atualização quando seu pedido for enviado.</p>
<p>Obrigado por comprar conosco!</p>',

			'order_paid'               => '<h2>Pagamento Confirmado!</h2>
<p>Olá {{customer_name}},</p>
<p>Confirmamos o pagamento do seu pedido <strong>#{{order_id}}</strong>.</p>
<p><strong>Valor Pago:</strong> {{amount_paid}}</p>
<p><strong>Data:</strong> {{payment_date}}</p>
<p>Agora estamos preparando seu pedido para envio.</p>',

			'order_cancelled'          => '<h2>Pedido Cancelado</h2>
<p>Olá {{customer_name}},</p>
<p>Infelizmente seu pedido <strong>#{{order_id}}</strong> foi cancelado.</p>
<p><strong>Motivo:</strong> {{cancellation_reason}}</p>
<p>Se houver reembolso, o valor de {{refund_amount}} será processado em breve.</p>',

			'new_subscription'         => '<h2>Bem-vindo à sua Assinatura!</h2>
<p>Olá {{customer_name}},</p>
<p>Sua assinatura <strong>#{{subscription_id}}</strong> foi ativada com sucesso!</p>
<p><strong>Plano:</strong> {{plan_name}}</p>
<p><strong>Próxima renovação:</strong> {{renewal_date}}</p>',

			'subscription_renewed'     => '<h2>Assinatura Renovada!</h2>
<p>Olá {{customer_name}},</p>
<p>Sua assinatura <strong>#{{subscription_id}}</strong> foi renovada automaticamente.</p>
<p><strong>Valor:</strong> {{renewal_amount}}</p>
<p><strong>Próxima renovação:</strong> {{next_renewal}}</p>',

			'subscription_cancelled'   => '<h2>Assinatura Cancelada</h2>
<p>Olá {{customer_name}},</p>
<p>Sua assinatura <strong>#{{subscription_id}}</strong> foi cancelada em {{cancellation_date}}.</p>
<p>Esperamos vê-lo novamente em breve!</p>',

			'payment_received'         => '<h2>Pagamento Recebido!</h2>
<p>Olá {{customer_name}},</p>
<p>Recebemos seu pagamento de <strong>{{amount}}</strong> em {{payment_date}}.</p>
<p><strong>ID do Pagamento:</strong> {{payment_id}}</p>',

			'payment_failed'           => '<h2>Problema no Pagamento</h2>
<p>Olá {{customer_name}},</p>
<p>Não conseguimos processar o pagamento do pedido <strong>#{{order_id}}</strong>.</p>
<p><strong>Motivo:</strong> {{failure_reason}}</p>
<p><a href="{{retry_url}}" style="display:inline-block;background:#0073aa;color:white;padding:12px 24px;text-decoration:none;border-radius:4px;">Tentar Novamente</a></p>',

			'quote_request'            => '<h2>Olá {{customer_name}}!</h2>
<p>Recebemos seu <strong>pedido de orçamento</strong> com sucesso! Nossa equipe irá analisar sua solicitação e entrar em contato em breve com uma proposta personalizada.</p>
<div style="background: #f9f9f9; padding: 20px; border-radius: 5px; margin: 20px 0;">
    <h3>Informações do Orçamento</h3>
    <p><strong>ID:</strong> #{{quote_id}}<br>
    <strong>Data:</strong> {{quote_date}}</p>
</div>
<h3>Produtos Solicitados:</h3>
{{products}}
<p style="text-align: center;">
    <a href="{{quote_link}}" style="display:inline-block;background:#0073aa;color:white;padding:12px 24px;text-decoration:none;border-radius:4px;">Acompanhar Orçamento</a>
</p>
<p style="font-size: 14px; color: #999;">Você pode acompanhar o status do seu orçamento e trocar mensagens com nossa equipe através do link acima.</p>',

			'quote_received'           => '<h2>Seu Orçamento está Pronto! 📋</h2>
<p>Olá <strong>{{customer_name}}</strong>,</p>
<p>Respondemos à sua solicitação! Aqui está o orçamento detalhado para sua análise.</p>
<div style="background: #e3f2fd; border-left: 4px solid #2196f3; padding: 20px; border-radius: 5px; margin: 20px 0;">
    <h3 style="color: #2196f3; margin-top: 0;">💰 Detalhes do Orçamento:</h3>
    <table style="width: 100%; border-collapse: collapse; color: #333;">
        <tr style="border-bottom: 1px solid #bee5eb;">
            <td style="padding: 10px;"><strong>ID do Orçamento:</strong></td>
            <td style="padding: 10px; text-align: right;">#{{quote_id}}</td>
        </tr>
        <tr style="border-bottom: 1px solid #bee5eb;">
            <td style="padding: 10px;"><strong>Data:</strong></td>
            <td style="padding: 10px; text-align: right;">{{quote_date}}</td>
        </tr>
        <tr style="border-bottom: 1px solid #bee5eb;">
            <td style="padding: 10px;"><strong>Subtotal (Produtos):</strong></td>
            <td style="padding: 10px; text-align: right;">{{quote_price}}</td>
        </tr>
        <tr style="border-bottom: 1px solid #bee5eb;">
            <td style="padding: 10px;"><strong>Frete:</strong></td>
            <td style="padding: 10px; text-align: right;">{{quote_shipping}}</td>
        </tr>
        <tr style="background: #e8f5e9;">
            <td style="padding: 10px;"><strong>Total do Orçamento:</strong></td>
            <td style="padding: 10px; text-align: right; color: #2196f3; font-size: 18px;"><strong>{{quote_total}}</strong></td>
        </tr>
    </table>
</div>
<h3>📦 Produtos Orçados:</h3>
{{products}}
<p style="text-align: center; margin: 30px 0;">
    <a href="{{quote_link}}" style="display:inline-block;background:#2196f3;color:white;padding:15px 30px;text-decoration:none;border-radius:5px;font-size:16px;font-weight:bold;">✓ Aprovar Orçamento</a>
</p>
<p style="font-size: 13px; color: #666; background: #f8f9fa; padding: 15px; border-radius: 5px;">
    <strong>Próximas etapas:</strong>
    <ul style="margin: 10px 0; padding-left: 20px;">
        <li>Revise o orçamento e todos os detalhes</li>
        <li>Clique no botão acima para aprovar e prosseguir com a compra</li>
        <li>Se tiver dúvidas, responda direto neste email ou entre em contato</li>
    </ul>
</p>
<p>Agradecemos a confiança e queremos esclarecer qualquer dúvida que possa ter!</p>',

			'quote_admin_new'          => '<h2>Novo Pedido de Orçamento Recebido!</h2>
<p>Um novo pedido de orçamento aguarda sua análise.</p>
<div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0;">
    <p><strong>⏰ Ação necessária:</strong> Analise e responda o orçamento o mais breve possível.</p>
</div>
<h3>Informações do Cliente:</h3>
<div style="background: #f9f9f9; padding: 20px; border-radius: 5px; margin: 20px 0;">
    <p><strong>Nome:</strong> {{customer_name}}<br>
    <strong>Email:</strong> {{customer_email}}<br>
    <strong>Telefone:</strong> {{customer_phone}}</p>
</div>
<h3>Detalhes do Orçamento:</h3>
<div style="background: #f9f9f9; padding: 20px; border-radius: 5px; margin: 20px 0;">
    <p><strong>ID:</strong> #{{quote_id}}<br>
    <strong>Data:</strong> {{quote_date}}</p>
</div>
<h3>Produtos Solicitados:</h3>
{{products}}
<p style="text-align: center;">
    <a href="{{admin_link}}" style="display:inline-block;background:#e74c3c;color:white;padding:12px 24px;text-decoration:none;border-radius:4px;">Responder Orçamento no Painel</a>
</p>',

			'quote_approved'           => '<h2>Cliente Aprovou o Orçamento! 🎉</h2>
<p>Ótimas notícias!</p>
<p><strong>{{customer_name}}</strong> aprovou o orçamento <strong>#{{quote_id}}</strong> e segue para a próxima etapa!</p>
<div style="background: #d4edda; border-left: 4px solid #28a745; padding: 20px; border-radius: 5px; margin: 20px 0;">
    <h3 style="color: #28a745; margin-top: 0;">✓ Orçamento Aprovado</h3>
    <table style="width: 100%; border-collapse: collapse; color: #333;">
        <tr style="border-bottom: 1px solid #c3e6cb;">
            <td style="padding: 10px;"><strong>Valor dos Produtos:</strong></td>
            <td style="padding: 10px; text-align: right;">{{approved_price}}</td>
        </tr>
        <tr style="border-bottom: 1px solid #c3e6cb;">
            <td style="padding: 10px;"><strong>Frete:</strong></td>
            <td style="padding: 10px; text-align: right;">{{approved_shipping}}</td>
        </tr>
        <tr style="background: #28a745; color: white;">
            <td style="padding: 10px;"><strong>Total:</strong></td>
            <td style="padding: 10px; text-align: right; font-size: 18px;"><strong>{{total}}</strong></td>
        </tr>
    </table>
</div>
<p>{{approval_notes}}</p>
<p style="text-align: center; margin: 30px 0;">
    <a href="{{admin_link}}" style="display:inline-block;background:#28a745;color:white;padding:15px 30px;text-decoration:none;border-radius:5px;font-size:16px;font-weight:bold;">📋 Processar no Painel</a>
</p>
<p style="font-size: 13px; color: #666;">O cliente aguarda as próximas instruções de pagamento.</p>',

			'quote_message'            => '<h2>Nova Mensagem no seu Orçamento 💬</h2>
<p>Olá, <strong>{{customer_name}}</strong>!</p>
<p>Nossa equipe enviou uma nova mensagem sobre seu orçamento <strong>#{{quote_id}}</strong>.</p>
<div style="background: #e3f2fd; border-left: 4px solid #2196f3; padding: 20px; margin: 20px 0;">
    <p style="font-size: 12px; color: #1976d2; text-transform: uppercase; font-weight: bold; margin: 0 0 10px 0;">Mensagem da Equipe:</p>
    <p style="font-size: 15px; color: #333; white-space: pre-wrap; margin: 0;">{{message}}</p>
</div>
<p style="text-align: center;">
    <a href="{{quote_link}}" style="display:inline-block;background:#0073aa;color:white;padding:12px 24px;text-decoration:none;border-radius:4px;">Responder Mensagem</a>
</p>',

			'appointment_confirmation' => '<h2>Seu Agendamento foi Confirmado! ✓</h2>
<p>Olá <strong>{{customer_name}}</strong>,</p>
<p>Agradecemos por agendar conosco! Seu agendamento foi confirmado com sucesso.</p>
<div style="background: #d4edda; border-left: 4px solid #28a745; padding: 20px; border-radius: 5px; margin: 20px 0;">
    <h3 style="color: #28a745; margin-top: 0;">Detalhes do Seu Agendamento:</h3>
    <table style="width: 100%; color: #333;">
        <tr style="border-bottom: 1px solid #c3e6cb;">
            <td style="padding: 8px;"><strong>Serviço:</strong></td>
            <td style="padding: 8px; text-align: right;">{{service_name}}</td>
        </tr>
        <tr style="border-bottom: 1px solid #c3e6cb;">
            <td style="padding: 8px;"><strong>Data:</strong></td>
            <td style="padding: 8px; text-align: right;">{{appointment_date}}</td>
        </tr>
        <tr style="border-bottom: 1px solid #c3e6cb;">
            <td style="padding: 8px;"><strong>Horário:</strong></td>
            <td style="padding: 8px; text-align: right;">{{appointment_time}}</td>
        </tr>
        <tr style="border-bottom: 1px solid #c3e6cb;">
            <td style="padding: 8px;"><strong>Duração:</strong></td>
            <td style="padding: 8px; text-align: right;">{{duration}} minutos</td>
        </tr>
        {{location}}
    </table>
</div>
<p style="font-size: 14px; color: #666;">
    <strong>Observações importantes:</strong>
    <ul>
        <li>Por favor, chegue 10 minutos antes do horário agendado</li>
        <li>Caso não pueda comparecer, cancele com antecedência</li>
        <li>Teremos prazer em ajudá-lo!</li>
    </ul>
</p>
<p>Obrigado e até breve!</p>',

			'digital_access_granted'   => '<h2>Seu Download está Pronto! 🎉</h2>
<p>Olá <strong>{{customer_name}}</strong>,</p>
<p>Agradecemos sua compra! Seu acesso ao produto digital foi liberado com sucesso.</p>
<div style="background: #d1ecf1; border-left: 4px solid #17a2b8; padding: 20px; border-radius: 5px; margin: 20px 0;">
    <h3 style="color: #17a2b8; margin-top: 0;">📥 Informações do Download:</h3>
    <table style="width: 100%; color: #333;">
        <tr style="border-bottom: 1px solid #bee5eb;">
            <td style="padding: 8px;"><strong>Produto:</strong></td>
            <td style="padding: 8px; text-align: right;">{{product_name}}</td>
        </tr>
        <tr style="border-bottom: 1px solid #bee5eb;">
            <td style="padding: 8px;"><strong>Número de Arquivos:</strong></td>
            <td style="padding: 8px; text-align: right;">{{file_count}}</td>
        </tr>
        <tr style="border-bottom: 1px solid #bee5eb;">
            <td style="padding: 8px;"><strong>Downloads Restantes:</strong></td>
            <td style="padding: 8px; text-align: right;">{{download_count_remaining}}</td>
        </tr>
        <tr>
            <td style="padding: 8px;"><strong>Expira em:</strong></td>
            <td style="padding: 8px; text-align: right;">{{expires_at}}</td>
        </tr>
    </table>
</div>
<p style="text-align: center; margin: 30px 0;">
    <a href="{{download_link}}" style="display:inline-block;background:#17a2b8;color:white;padding:15px 30px;text-decoration:none;border-radius:5px;font-size:16px;font-weight:bold;">⬇️ Clique aqui para Baixar</a>
</p>
<p style="font-size: 13px; color: #666; background: #f8f9fa; padding: 15px; border-radius: 5px;">
    <strong>Dicas:</strong>
    <ul style="margin: 10px 0; padding-left: 20px;">
        <li>Salve o arquivo em um local seguro no seu computador</li>
        <li>Se a conexão cair, você pode fazer o download novamente usando este email</li>
        <li>Certifique-se de ter espaço em disco antes de fazer o download</li>
    </ul>
</p>
<p>Se tiver dúvidas ou problemas no download, entre em contato conosco!</p>',

			'refund_request'           => '<h2>Solicitação de Reembolso Recebida 📋</h2>
<p>Recebemos uma solicitação de reembolso para análise.</p>
<div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0;">
    <p><strong>⏰ Ação necessária:</strong> Revise a solicitação e aprove ou rejeite conforme apropriado.</p>
</div>
<h3>Detalhes da Solicitação:</h3>
<div style="background: #f9f9f9; padding: 20px; border-radius: 5px; margin: 20px 0;">
    <p><strong>Cliente:</strong> {{customer_name}}<br>
    <strong>Pedido:</strong> #{{order_id}}<br>
    <strong>Valor Solicitado:</strong> {{refund_amount}}<br>
    <strong>Data da Solicitação:</strong> {{request_date}}</p>
</div>
<h3>Motivo da Solicitação:</h3>
<div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #6c757d;">
    <p>{{reason}}</p>
</div>
<p style="text-align: center; margin: 30px 0;">
    <a href="{{admin_link}}" style="display:inline-block;background:#28a745;color:white;padding:15px 30px;text-decoration:none;border-radius:5px;font-size:16px;font-weight:bold;">✓ Processar Solicitação</a>
</p>
<p style="font-size: 13px; color: #666;">Acesse o painel de administração para aprovar, processar ou rejeitar esta solicitação de reembolso.</p>',

			'refund_processed'         => '<h2>Reembolso Processado ✓</h2>
<p>Olá <strong>{{customer_name}}</strong>,</p>
<p>Confirmamos que seu reembolso foi processado com sucesso! O valor será creditado em sua conta em breve.</p>
<div style="background: #d4edda; border-left: 4px solid #28a745; padding: 20px; border-radius: 5px; margin: 20px 0;">
    <h3 style="color: #28a745; margin-top: 0;">💰 Detalhes do Reembolso:</h3>
    <table style="width: 100%; color: #333;">
        <tr style="border-bottom: 1px solid #c3e6cb;">
            <td style="padding: 8px;"><strong>Pedido:</strong></td>
            <td style="padding: 8px; text-align: right;">#{{order_id}}</td>
        </tr>
        <tr style="border-bottom: 1px solid #c3e6cb;">
            <td style="padding: 8px;"><strong>Valor Original:</strong></td>
            <td style="padding: 8px; text-align: right;">{{original_amount}}</td>
        </tr>
        <tr style="border-bottom: 1px solid #c3e6cb;">
            <td style="padding: 8px;"><strong>Valor Reembolsado:</strong></td>
            <td style="padding: 8px; text-align: right; color: #27ae60; font-weight: bold;">{{refund_amount}}</td>
        </tr>
        <tr style="border-bottom: 1px solid #c3e6cb;">
            <td style="padding: 8px;"><strong>Data do Processamento:</strong></td>
            <td style="padding: 8px; text-align: right;">{{refund_date}}</td>
        </tr>
        <tr style="border-bottom: 1px solid #c3e6cb;">
            <td style="padding: 8px;"><strong>Método de Pagamento:</strong></td>
            <td style="padding: 8px; text-align: right;">{{payment_method}}</td>
        </tr>
        <tr>
            <td style="padding: 8px;"><strong>Previsão de Chegada:</strong></td>
            <td style="padding: 8px; text-align: right;">{{estimated_arrival}}</td>
        </tr>
    </table>
</div>
<p style="font-size: 13px; color: #666; background: #f8f9fa; padding: 15px; border-radius: 5px;">
    <strong>Informações importantes:</strong>
    <ul style="margin: 10px 0; padding-left: 20px;">
        <li><strong>Tempo de processamento:</strong> O reembolso pode levar entre 1-5 dias úteis dependendo do seu banco</li>
        <li><strong>Verificar sua conta:</strong> Acompanhe sua conta bancária ou cartão de crédito para confirmar o depósito</li>
        <li><strong>Taxas:</strong> Algumas instituições financeiras podem descontar pequenas taxas de processamento</li>
        <li><strong>Motivo:</strong> {{refund_reason}}</li>
    </ul>
</p>
<p>Se não receber o reembolso dentro do prazo estimado ou tiver dúvidas, entre em contato conosco. Estamos aqui para ajudar!</p>',

			'appointment_cancelled'    => '<h2>Seu Agendamento foi Cancelado</h2>
<p>Olá <strong>{{customer_name}}</strong>,</p>
<p>Informamos que seu agendamento foi cancelado.</p>
<div style="background: #f8d7da; border-left: 4px solid #dc3545; padding: 20px; border-radius: 5px; margin: 20px 0;">
    <h3 style="color: #dc3545; margin-top: 0;">Informações do Agendamento Cancelado:</h3>
    <table style="width: 100%; color: #333;">
        <tr style="border-bottom: 1px solid #f5c6cb;">
            <td style="padding: 8px;"><strong>Serviço:</strong></td>
            <td style="padding: 8px; text-align: right;">{{service_name}}</td>
        </tr>
        <tr style="border-bottom: 1px solid #f5c6cb;">
            <td style="padding: 8px;"><strong>Data Original:</strong></td>
            <td style="padding: 8px; text-align: right;">{{appointment_date}}</td>
        </tr>
        <tr style="border-bottom: 1px solid #f5c6cb;">
            <td style="padding: 8px;"><strong>Horário Original:</strong></td>
            <td style="padding: 8px; text-align: right;">{{appointment_time}}</td>
        </tr>
        <tr>
            <td style="padding: 8px;"><strong>Motivo:</strong></td>
            <td style="padding: 8px; text-align: right;">{{cancellation_reason}}</td>
        </tr>
    </table>
</div>
<p>Se tiver dúvidas ou gostaria de agendar novamente, entre em contato conosco. Você é sempre bem-vindo!</p>',
		);

		return $defaults[ $email_type ] ?? '<p>Escreva o conteúdo do seu email aqui...</p>';
	}

	/**
	 * Obter conteúdo padrão do template por tipo
	 */
	private function get_default_template_content( $email_type ) {
		return self::get_default_content_for_type( $email_type );
	}

	/**
	 * Substituir variáveis por dados de exemplo
	 */
	private function replace_variables_with_sample_data( $content, $email_type ) {
		$sample_data = array(
			'{{customer_name}}'       => 'João Silva',
			'{{order_id}}'            => '12345',
			'{{order_date}}'          => date_i18n( 'd/m/Y H:i' ),
			'{{order_total}}'         => 'R$ 299,90',
			'{{products}}'            => '<ul><li>Produto Exemplo x1 - R$ 149,90</li><li>Produto Teste x1 - R$ 150,00</li></ul>',
			'{{payment_method}}'      => 'PIX',
			'{{tracking_url}}'        => '#',
			'{{amount_paid}}'         => 'R$ 299,90',
			'{{payment_date}}'        => date_i18n( 'd/m/Y' ),
			'{{receipt_url}}'         => '#',
			'{{cancellation_reason}}' => 'Solicitação do cliente',
			'{{refund_amount}}'       => 'R$ 299,90',
			'{{subscription_id}}'     => 'SUB-001',
			'{{plan_name}}'           => 'Plano Premium',
			'{{renewal_date}}'        => date_i18n( 'd/m/Y', strtotime( '+30 days' ) ),
			'{{renewal_amount}}'      => 'R$ 49,90',
			'{{next_renewal}}'        => date_i18n( 'd/m/Y', strtotime( '+30 days' ) ),
			'{{cancellation_date}}'   => date_i18n( 'd/m/Y' ),
			'{{amount}}'              => 'R$ 299,90',
			'{{payment_id}}'          => 'PAY-78901',
			'{{failure_reason}}'      => 'Cartão recusado',
			'{{retry_url}}'           => '#',
		);

		return str_replace( array_keys( $sample_data ), array_values( $sample_data ), $content );
	}

	/**
	 * Construir HTML do email
	 */
	private function build_email_html( $template, $content ) {
		$header_color = $template['header_color'] ?: '#0073aa';
		$button_color = $template['button_color'] ?: '#0073aa';
		$text_color   = $template['text_color'] ?: '#333333';
		$bg_color     = $template['bg_color'] ?: '#ffffff';
		$logo         = $template['logo'] ?: '';

		$html  = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>';
		$html .= 'body { font-family: Arial, sans-serif; line-height: 1.6; color: ' . esc_attr( $text_color ) . '; background-color: #f5f5f5; margin: 0; padding: 0; }';
		$html .= '.email-container { max-width: 600px; margin: 0 auto; background-color: ' . esc_attr( $bg_color ) . '; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }';
		$html .= '.email-header { background-color: ' . esc_attr( $header_color ) . '; color: white; padding: 30px; text-align: center; }';
		$html .= '.email-logo { max-width: 200px; height: auto; margin-bottom: 10px; }';
		$html .= '.email-body { padding: 30px; }';
		$html .= '.email-body h2 { color: ' . esc_attr( $header_color ) . '; margin-top: 0; }';
		$html .= '.email-button, a[style*="background"] { background-color: ' . esc_attr( $button_color ) . ' !important; color: white !important; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block; }';
		$html .= '.email-footer { background-color: #f9f9f9; padding: 20px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #eee; }';
		$html .= 'ul { padding-left: 20px; }';
		$html .= '</style></head><body>';

		$html .= '<div class="email-container">';

		// Header com logo
		$html .= '<div class="email-header">';
		if ( ! empty( $logo ) ) {
			$html .= '<img src="' . esc_url( $logo ) . '" class="email-logo" alt="Logo">';
		} else {
			$html .= '<h1 style="margin:0;font-size:24px;">' . esc_html( get_bloginfo( 'name' ) ) . '</h1>';
		}
		$html .= '</div>';

		// Body com conteúdo
		$html .= '<div class="email-body">' . wp_kses_post( $content ) . '</div>';

		// Footer
		$html .= '<div class="email-footer">';
		$html .= '<p>&copy; ' . gmdate( 'Y' ) . ' ' . esc_html( get_bloginfo( 'name' ) ) . '. Todos os direitos reservados.</p>';
		$html .= '</div>';

		$html .= '</div>';
		$html .= '</body></html>';

		return $html;
	}

	/**
	 * AJAX: Send test email
	 */
	public function send_test_email() {
		check_ajax_referer( 'hng_email_customizer', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão', 'hng-commerce' ) ) );
		}

		// Get POST data
		$post       = function_exists( 'wp_unslash' ) ? wp_unslash( $_POST ) : $_POST;
		$email_type = isset( $post['email_type'] ) ? sanitize_text_field( $post['email_type'] ) : '';
		$test_email = isset( $post['test_email'] ) ? sanitize_email( $post['test_email'] ) : '';

		if ( empty( $email_type ) || empty( $test_email ) || ! is_email( $test_email ) ) {
			wp_send_json_error( array( 'message' => __( 'Email inválido', 'hng-commerce' ) ) );
		}

		// Get template
		$template = get_option( "hng_email_template_{$email_type}", array() );
		$subject  = isset( $template['subject'] ) ? $template['subject'] : __( 'Email de Teste', 'hng-commerce' );
		$header   = isset( $template['header'] ) ? $template['header'] : '';
		$body     = isset( $template['body'] ) ? $template['body'] : '';
		$footer   = isset( $template['footer'] ) ? $template['footer'] : '';

		// Build email content
		$content  = '<html><body>';
		$content .= '<div style="max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif;">';
		$content .= wp_kses_post( $header );
		$content .= wp_kses_post( $body );
		$content .= wp_kses_post( $footer );
		$content .= '</div>';
		$content .= '</body></html>';

		// Send email
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$sent    = wp_mail( $test_email, $subject, $content, $headers );

		if ( $sent ) {
			wp_send_json_success( array( 'message' => __( 'Email de teste enviado com sucesso!', 'hng-commerce' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Erro ao enviar email de teste', 'hng-commerce' ) ) );
		}
	}

	/**
	 * AJAX: Reset email template to default
	 */
	public function reset_email_template() {
		check_ajax_referer( 'hng_email_customizer', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão', 'hng-commerce' ) ) );
		}

		// Get POST data
		$post       = function_exists( 'wp_unslash' ) ? wp_unslash( $_POST ) : $_POST;
		$email_type = isset( $post['email_type'] ) ? sanitize_text_field( $post['email_type'] ) : '';

		if ( empty( $email_type ) ) {
			wp_send_json_error( array( 'message' => __( 'Tipo de email inválido', 'hng-commerce' ) ) );
		}

		// Delete custom template to revert to default
		delete_option( "hng_email_template_{$email_type}" );

		wp_send_json_success( array( 'message' => __( 'Template restaurado ao padrão!', 'hng-commerce' ) ) );
	}

	/**
	 * AJAX: Usar configurações globais (limpar cores/logo personalizadas)
	 */
	public function use_global_settings() {
		check_ajax_referer( 'hng_email_customizer', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão', 'hng-commerce' ) ) );
		}

		$post       = function_exists( 'wp_unslash' ) ? wp_unslash( $_POST ) : $_POST;
		$email_type = isset( $post['email_type'] ) ? sanitize_text_field( $post['email_type'] ) : '';

		if ( empty( $email_type ) ) {
			wp_send_json_error( array( 'message' => __( 'Tipo de email inválido', 'hng-commerce' ) ) );
		}

		// Carregar template atual
		$template = get_option( "hng_email_template_{$email_type}", array() );

		// Remover cores e logo personalizados
		unset( $template['logo'] );
		unset( $template['header_color'] );
		unset( $template['button_color'] );
		unset( $template['text_color'] );
		unset( $template['bg_color'] );

		// Salvar template sem as cores/logo
		update_option( "hng_email_template_{$email_type}", $template );

		wp_send_json_success( array( 'message' => __( 'Configurações globais aplicadas com sucesso!', 'hng-commerce' ) ) );
	}

	/**
	 * Filter email types based on active product types
	 */
	private static function filter_email_types_by_active_products( $all_types ) {
		$filtered = array();
		$options  = get_option( 'hng_commerce_settings', array() );

		foreach ( $all_types as $key => $type ) {
			// Always show if marked as always_show
			if ( ! empty( $type['always_show'] ) ) {
				$filtered[ $key ] = $type;
				continue;
			}

			// Check if product type is enabled
			if ( isset( $type['product_type'] ) ) {
				$product_type = $type['product_type'];
				$is_enabled   = ( $options[ 'product_type_' . $product_type . '_enabled' ] ?? 'no' ) === 'yes';

				// Se nunca foi configurado, mostrar tudo
				$has_settings = false;
				foreach ( array( 'simple', 'variable', 'digital', 'subscription', 'quote', 'appointment' ) as $pt ) {
					if ( isset( $options[ 'product_type_' . $pt . '_enabled' ] ) ) {
						$has_settings = true;
						break;
					}
				}

				if ( ! $has_settings || $is_enabled ) {
					$filtered[ $key ] = $type;
				}
			} else {
				// Sem restrição de produto, sempre mostrar
				$filtered[ $key ] = $type;
			}
		}

		return $filtered;
	}
}

// Initialize
new HNG_Email_Customizer_Page();
