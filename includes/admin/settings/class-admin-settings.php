<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName
/**
 * HNG Admin Settings - Handler Principal
 *
 * Gerencia o registro e renderização de configurações do plugin
 * de forma modular e extensível.
 *
 * @package HNG_Commerce
 * @subpackage Admin/Settings
 * @since 1.1.1
 */

// phpcs:disable Squiz.Commenting.InlineComment.InvalidEndChar
// phpcs:disable WordPress.PHP.YodaConditions.NotYoda
// phpcs:disable Squiz.Commenting.FunctionComment.MissingParamTag
// phpcs:disable Squiz.Commenting.VariableComment.MissingVar
// phpcs:disable Squiz.Commenting.ClassComment.Missing
// phpcs:disable WordPress.Files.FileName.InvalidClassFileName
// phpcs:disable WordPress.Security.NonceVerification

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HNG_Admin_Settings {

	/**

	 * Tabs de configuração
	 */

	private $tabs = array();



	/**

	 * Tab ativa
	 */

	private $active_tab = 'general';



	/**

	 * Singleton instance
	 */

	private static $instance = null;



	/**

	 * Get instance
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

		add_action( 'admin_init', array( $this, 'register_settings' ) );

		$this->register_default_tabs();
	}



	/**

	 * Registrar tabs padrão
	 */
	private function register_default_tabs() {

		$this->register_tab( 'general', __( 'Geral', 'hng-commerce' ), array( $this, 'render_general_tab' ) );

		$this->register_tab( 'product_types', __( 'Tipos de Produto', 'hng-commerce' ), array( $this, 'render_product_types_tab' ) );

		$this->register_tab( 'pages', __( 'Páginas', 'hng-commerce' ), array( $this, 'render_pages_tab' ) );

		if ( class_exists( 'HNG_Cart_Layouts_Settings' ) ) {
			$this->register_tab( 'layouts', __( 'Layouts', 'hng-commerce' ), array( HNG_Cart_Layouts_Settings::class, 'render_tab' ) );
		}

		$this->register_tab( 'refund', __( 'Reembolsos', 'hng-commerce' ), array( $this, 'render_refund_tab' ) );

		$this->register_tab( 'pix_installment', __( 'Parcelamento PIX', 'hng-commerce' ), array( $this, 'render_pix_installment_tab' ) );

		$this->register_tab( 'security', __( 'Segurança', 'hng-commerce' ), array( $this, 'render_security_tab' ) );

		$this->register_tab( 'auth', __( 'Autenticação', 'hng-commerce' ), array( $this, 'render_auth_tab' ) );

		// Registrar aba de gerenciador de funções
		if ( class_exists( 'HNG_Roles_Manager' ) ) {
			$this->register_tab( 'roles', __( 'Gerenciador de Funções', 'hng-commerce' ), array( HNG_Roles_Manager::instance(), 'render_tab' ) );
		}
	}



	/**

	 * Registrar uma tab
	 */
	public function register_tab( $id, $label, $callback ) {

		$this->tabs[ $id ] = array(

			'label'    => $label,

			'callback' => $callback,

		);
	}



	/**

	 * Registrar configurações
	 */
	public function register_settings() {

		// Registrar option group

		register_setting(
			'hng_commerce_settings',
			'hng_commerce_settings',
			array(

				'sanitize_callback' => array( $this, 'sanitize_settings' ),

			)
		);

		// Seção Geral

		add_settings_section(
			'hng_general_section',
			__( 'Configurações Gerais', 'hng-commerce' ),
			'__return_null',
			'hng-commerce-general'
		);

		// Campos Gerais

		add_settings_field(
			'currency',
			__( 'Moeda', 'hng-commerce' ),
			array( $this, 'currency_field' ),
			'hng-commerce-general',
			'hng_general_section'
		);

		add_settings_field(
			'currency_position',
			__( 'Posição do Símbolo', 'hng-commerce' ),
			array( $this, 'currency_position_field' ),
			'hng-commerce-general',
			'hng_general_section'
		);

		add_settings_field(
			'thousand_separator',
			__( 'Separador de Milhares', 'hng-commerce' ),
			array( $this, 'thousand_separator_field' ),
			'hng-commerce-general',
			'hng_general_section'
		);

		add_settings_field(
			'decimal_separator',
			__( 'Separador de Decimais', 'hng-commerce' ),
			array( $this, 'decimal_separator_field' ),
			'hng-commerce-general',
			'hng_general_section'
		);

		add_settings_field(
			'number_decimals',
			__( 'Número de Decimais', 'hng-commerce' ),
			array( $this, 'number_decimals_field' ),
			'hng-commerce-general',
			'hng_general_section'
		);

		add_settings_field(
			'require_login_to_purchase',
			__( 'Login Requerido para Comprar', 'hng-commerce' ),
			array( $this, 'require_login_to_purchase_field' ),
			'hng-commerce-general',
			'hng_general_section'
		);

		add_settings_field(
			'redirect_to_checkout_after_add',
			__( 'Redirecionar ao Checkout', 'hng-commerce' ),
			array( $this, 'redirect_to_checkout_after_add_field' ),
			'hng-commerce-general',
			'hng_general_section'
		);

		// Seção de Páginas

		add_settings_section(
			'hng_pages_section',
			__( 'Páginas do Sistema', 'hng-commerce' ),
			array( $this, 'pages_section_description' ),
			'hng-commerce-pages'
		);

		$pages = array( 'shop', 'cart', 'checkout', 'my_account', 'order_confirmation' );

		foreach ( $pages as $page ) {

			add_settings_field(
				$page . '_page',
				$this->get_page_label( $page ),
				array( $this, 'page_field' ),
				'hng-commerce-pages',
				'hng_pages_section',
				array( 'page_key' => $page )
			);

		}

		// Seção PIX Parcelado

		add_settings_section(
			'hng_pix_installment_section',
			__( 'Configurações de Parcelamento PIX', 'hng-commerce' ),
			'__return_null',
			'hng-commerce-pix-installment'
		);

		add_settings_field(
			'pix_installment_enabled',
			__( 'Habilitar Parcelamento PIX', 'hng-commerce' ),
			array( $this, 'pix_installment_enabled_field' ),
			'hng-commerce-pix-installment',
			'hng_pix_installment_section'
		);

		add_settings_field(
			'pix_installment_max',
			__( 'Máximo de Parcelas', 'hng-commerce' ),
			array( $this, 'pix_installment_max_field' ),
			'hng-commerce-pix-installment',
			'hng_pix_installment_section'
		);

		add_settings_field(
			'pix_installment_min_value',
			__( 'Valor Mínimo por Parcela', 'hng-commerce' ),
			array( $this, 'pix_installment_min_value_field' ),
			'hng-commerce-pix-installment',
			'hng_pix_installment_section'
		);

		add_settings_field(
			'pix_installment_fee',
			__( 'Taxa de Juros (%)', 'hng-commerce' ),
			array( $this, 'pix_installment_fee_field' ),
			'hng-commerce-pix-installment',
			'hng_pix_installment_section'
		);

		// Seção Reembolsos

		add_settings_section(
			'hng_refund_section',
			__( 'Configurações de Reembolsos', 'hng-commerce' ),
			'__return_null',
			'hng-commerce-refund'
		);

		add_settings_field(
			'refund_enabled',
			__( 'Habilitar Sistema de Reembolsos', 'hng-commerce' ),
			array( $this, 'refund_enabled_field' ),
			'hng-commerce-refund',
			'hng_refund_section'
		);

		add_settings_field(
			'refund_max_days',
			__( 'Dias Máximos para Solicitar Reembolso', 'hng-commerce' ),
			array( $this, 'refund_max_days_field' ),
			'hng-commerce-refund',
			'hng_refund_section'
		);

		add_settings_field(
			'refund_require_reason',
			__( 'Exigir Motivo do Reembolso', 'hng-commerce' ),
			array( $this, 'refund_require_reason_field' ),
			'hng-commerce-refund',
			'hng_refund_section'
		);

		add_settings_field(
			'refund_reasons',
			__( 'Motivos de Reembolso Disponíveis', 'hng-commerce' ),
			array( $this, 'refund_reasons_field' ),
			'hng-commerce-refund',
			'hng_refund_section'
		);

		add_settings_field(
			'refund_allow_evidence',
			__( 'Permitir Upload de Evidências', 'hng-commerce' ),
			array( $this, 'refund_allow_evidence_field' ),
			'hng-commerce-refund',
			'hng_refund_section'
		);

		add_settings_field(
			'refund_auto_approve',
			__( 'Aprovar Reembolsos Automaticamente', 'hng-commerce' ),
			array( $this, 'refund_auto_approve_field' ),
			'hng-commerce-refund',
			'hng_refund_section'
		);

		// Seção Tipos de Produto

		add_settings_section(
			'hng_product_types_section',
			__( 'Tipos de Produto Habilitados', 'hng-commerce' ),
			array( $this, 'product_types_section_description' ),
			'hng-commerce-product-types'
		);

		// Campo para cada tipo de produto

		$product_types = $this->get_all_product_types();

		foreach ( $product_types as $type_key => $type_info ) {

			add_settings_field(
				'product_type_' . $type_key,
				$type_info['icon'] . ' ' . $type_info['label'],
				array( $this, 'product_type_toggle_field' ),
				'hng-commerce-product-types',
				'hng_product_types_section',
				array(
					'type_key'  => $type_key,
					'type_info' => $type_info,
				)
			);

		}

		// Seção Segurança (segredos de webhook)

		$security_gateways = array(

			'asaas'       => __( 'Asaas', 'hng-commerce' ),

			'mercadopago' => __( 'Mercado Pago', 'hng-commerce' ),

			'pagseguro'   => __( 'PagSeguro', 'hng-commerce' ),

		);

		foreach ( array_keys( $security_gateways ) as $gateway_key ) {

			register_setting(
				'hng_security_settings',
				'hng_webhook_secret_' . $gateway_key,
				array(

					'sanitize_callback' => array( $this, 'sanitize_webhook_secret' ),

				)
			);

		}

		add_settings_section(
			'hng_security_section',
			__( 'Segurança de Webhooks', 'hng-commerce' ),
			array( $this, 'security_section_description' ),
			'hng-commerce-security'
		);

		foreach ( $security_gateways as $gateway_key => $gateway_label ) {

			add_settings_field(
				'webhook_secret_' . $gateway_key,
				/* translators: %s: gateway label */
				sprintf( __( 'Segredo do webhook (%s)', 'hng-commerce' ), $gateway_label ),
				array( $this, 'webhook_secret_field' ),
				'hng-commerce-security',
				'hng_security_section',
				array(
					'gateway' => $gateway_key,
					'label'   => $gateway_label,
				)
			);

		}
	}

	/**

	 * Sanitizar configurações
	 */
	public function sanitize_settings( $input ) {

		// BUGFIX: Preservar configurações existentes ao salvar em diferentes tabs

		$existing = get_option( 'hng_commerce_settings', array() );

		$sanitized = $existing; // Preservar configurações existentes

		// Currency

		if ( isset( $input['currency'] ) ) {

			$sanitized['currency'] = sanitize_text_field( $input['currency'] );

		}

		// Currency position

		if ( isset( $input['currency_position'] ) ) {

			$allowed = array( 'left', 'right', 'left_space', 'right_space' );

			$sanitized['currency_position'] = in_array( $input['currency_position'], $allowed, true )

				? $input['currency_position']

				: 'left';

		}

		// Separators

		if ( isset( $input['thousand_separator'] ) ) {

			$sanitized['thousand_separator'] = sanitize_text_field( $input['thousand_separator'] );

		}

		if ( isset( $input['decimal_separator'] ) ) {

			$sanitized['decimal_separator'] = sanitize_text_field( $input['decimal_separator'] );

		}

		// Number decimals

		if ( isset( $input['number_decimals'] ) ) {

			$sanitized['number_decimals'] = absint( $input['number_decimals'] );

		}

		// Require login to purchase

		if ( isset( $input['require_login_to_purchase'] ) ) {

			$sanitized['require_login_to_purchase'] = $input['require_login_to_purchase'] === 'yes' ? 'yes' : 'no';

		}

		// Redirect to checkout after add to cart

		if ( isset( $input['redirect_to_checkout_after_add'] ) ) {

			$sanitized['redirect_to_checkout_after_add'] = $input['redirect_to_checkout_after_add'] === 'yes' ? 'yes' : 'no';

		}

		// Pages

		$pages = array( 'shop', 'cart', 'checkout', 'my_account', 'order_confirmation' );

		foreach ( $pages as $page ) {

			if ( isset( $input[ $page . '_page' ] ) ) {

				$sanitized[ $page . '_page' ] = absint( $input[ $page . '_page' ] );

			}
		}

		// PIX Installment

		if ( isset( $input['pix_installment_enabled'] ) ) {

			$sanitized['pix_installment_enabled'] = $input['pix_installment_enabled'] === 'yes' ? 'yes' : 'no';

		}

		if ( isset( $input['pix_installment_max'] ) ) {

			$sanitized['pix_installment_max'] = max( 2, min( 12, absint( $input['pix_installment_max'] ) ) );

		}

		if ( isset( $input['pix_installment_min_value'] ) ) {

			$sanitized['pix_installment_min_value'] = floatval( $input['pix_installment_min_value'] );

		}

		if ( isset( $input['pix_installment_fee'] ) ) {

			$sanitized['pix_installment_fee'] = floatval( $input['pix_installment_fee'] );

		}

		// Refund Settings

		if ( isset( $input['refund_enabled'] ) ) {

			$sanitized['refund_enabled'] = $input['refund_enabled'] === 'yes' ? 'yes' : 'no';

		}

		if ( isset( $input['refund_max_days'] ) ) {

			$sanitized['refund_max_days'] = absint( $input['refund_max_days'] );

		}

		if ( isset( $input['refund_require_reason'] ) ) {

			$sanitized['refund_require_reason'] = $input['refund_require_reason'] === 'yes' ? 'yes' : 'no';

		}

		if ( isset( $input['refund_reasons'] ) ) {

			$sanitized['refund_reasons'] = array_map( 'sanitize_text_field', explode( "\n", $input['refund_reasons'] ) );

		}

		if ( isset( $input['refund_allow_evidence'] ) ) {

			$sanitized['refund_allow_evidence'] = $input['refund_allow_evidence'] === 'yes' ? 'yes' : 'no';

		}

		if ( isset( $input['refund_auto_approve'] ) ) {

			$sanitized['refund_auto_approve'] = $input['refund_auto_approve'] === 'yes' ? 'yes' : 'no';

		}

		// Product Types

		$product_types = $this->get_all_product_types();

		foreach ( array_keys( $product_types ) as $type_key ) {

			$field_name = 'product_type_' . $type_key . '_enabled';

			if ( isset( $input[ $field_name ] ) ) {

				$sanitized[ $field_name ] = $input[ $field_name ] === 'yes' ? 'yes' : 'no';

			} else {

				$sanitized[ $field_name ] = 'no';

			}
		}

		return $sanitized;
	}



	/**

	 * Renderizar página principal
	 */
	public function render() {

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading tab selection only

		$this->active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';

		?>

		<div class="wrap hng-settings-page">

			<h1><?php esc_html_e( 'Configurações do HNG Commerce', 'hng-commerce' ); ?></h1>

			

			<h2 class="nav-tab-wrapper">

				<?php foreach ( $this->tabs as $tab_id => $tab ) : ?>

					<a href="<?php echo esc_url( admin_url( 'admin.php?page=hng-settings&tab=' . $tab_id ) ); ?>" 

						class="nav-tab <?php echo esc_attr( $this->active_tab === $tab_id ? 'nav-tab-active' : '' ); ?>">

						<?php echo esc_html( $tab['label'] ); ?>

					</a>

				<?php endforeach; ?>

			</h2>

			

			<form method="post" action="options.php">

				<?php

				if ( isset( $this->tabs[ $this->active_tab ] ) ) {

					call_user_func( $this->tabs[ $this->active_tab ]['callback'] );

				}

				?>

			</form>

		</div>

		<?php
	}



	/**

	 * Renderizar tab geral
	 */
	public function render_general_tab() {
		?>
		<div style="margin-bottom: 20px;">
			<h2 style="display: inline-flex; align-items: center;">
				<?php esc_html_e( 'Configurações Gerais', 'hng-commerce' ); ?>
				<?php
				echo wp_kses_post(
					hng_admin_tooltip(
						'⚙️ Configurações Gerais do Plugin',
						array(
							array(
								'icon'    => '💰',
								'title'   => 'Moeda e Formatação',
								'content' => 'Define como os valores monetários serão exibidos em todo o site. Escolha a moeda, posição do símbolo e separadores de milhar/decimal.',
							),
							array(
								'icon'    => '🔐',
								'title'   => 'Login Requerido',
								'content' => 'Se ativado, apenas usuários logados podem finalizar compras. Útil para lojas B2B ou membros.',
							),
							array(
								'icon'    => '🛒',
								'title'   => 'Redirecionamento ao Checkout',
								'content' => 'Quando ativado, o cliente é levado direto ao checkout após adicionar produto ao carrinho. Ideal para produtos únicos ou landing pages.',
							),
						),
						array(
							'title' => '💡 Dica Importante',
							'items' => array(
								array(
									'label' => 'Brasil',
									'text'  => 'Use BRL, símbolo antes, milhar "." e decimal ","',
								),
								array(
									'label' => 'Internacional',
									'text'  => 'Use USD/EUR conforme o público-alvo',
								),
							),
						)
					)
				);
				?>
			</h2>
		</div>
		<?php
		settings_fields( 'hng_commerce_settings' );

		do_settings_sections( 'hng-commerce-general' );

		submit_button();
	}



	/**

	 * Renderizar tab de páginas
	 */
	public function render_pages_tab() {
		?>
		<div style="margin-bottom: 20px;">
			<h2 style="display: inline-flex; align-items: center;">
				<?php esc_html_e( 'Páginas do Sistema', 'hng-commerce' ); ?>
				<?php
				echo wp_kses_post(
					hng_admin_tooltip(
						'📄 Configuração de Páginas',
						array(
							array(
								'icon'    => '🛒',
								'title'   => 'Página do Carrinho',
								'content' => 'Página onde o cliente visualiza os itens do carrinho antes de finalizar. Use o shortcode [hng_cart] na página.',
							),
							array(
								'icon'    => '💳',
								'title'   => 'Página de Checkout',
								'content' => 'Página de finalização da compra com formulário de pagamento. Use o shortcode [hng_checkout].',
							),
							array(
								'icon'    => '👤',
								'title'   => 'Página da Minha Conta',
								'content' => 'Área do cliente com pedidos, downloads e dados pessoais. Use o shortcode [hng_my_account].',
							),
							array(
								'icon'    => '✅',
								'title'   => 'Página de Confirmação',
								'content' => 'Página exibida após compra bem-sucedida. Use o shortcode [hng_order_received].',
							),
							array(
								'icon'    => '❤️',
								'title'   => 'Página de Favoritos',
								'content' => 'Lista de desejos do cliente. Use o shortcode [hng_wishlist].',
							),
						),
						array(
							'title' => '🛠️ Como Configurar',
							'items' => array(
								array(
									'label' => 'Opção 1',
									'text'  => 'Crie páginas e adicione os shortcodes acima',
								),
								array(
									'label' => 'Opção 2',
									'text'  => 'Use Elementor e adicione o widget HNG correspondente',
								),
								array(
									'label' => 'Passo final',
									'text'  => 'Selecione as páginas aqui nas configurações',
								),
							),
						)
					)
				);
				?>
			</h2>
		</div>
		<?php
		settings_fields( 'hng_commerce_settings' );

		do_settings_sections( 'hng-commerce-pages' );

		submit_button();
	}



	/**

	 * Renderizar tab de segurança
	 */
	public function render_security_tab() {
		?>
		<div style="margin-bottom: 20px;">
			<h2 style="display: inline-flex; align-items: center;">
				<?php esc_html_e( 'Configurações de Segurança', 'hng-commerce' ); ?>
				<?php
				echo wp_kses_post(
					hng_admin_tooltip(
						'🔒 Segurança e Proteção',
						array(
							array(
								'icon'    => '🔐',
								'title'   => 'Webhook Secrets',
								'content' => 'Chaves secretas fornecidas pelos gateways de pagamento para validar que as notificações são autênticas (não forjadas).',
							),
							array(
								'icon'    => '🛡️',
								'title'   => 'Rate Limiting',
								'content' => 'Limita requisições por IP para prevenir ataques DDoS e tentativas de força bruta.',
							),
							array(
								'icon'    => '📝',
								'title'   => 'Logs de Segurança',
								'content' => 'Registra tentativas de acesso suspeitas e erros de autenticação para auditoria.',
							),
						),
						array(
							'title' => '⚠️ Importante',
							'items' => array(
								array(
									'label' => 'Webhook Secret',
									'text'  => 'Copie do painel do gateway e cole aqui',
								),
								array(
									'label' => 'URL Webhook',
									'text'  => 'Configure no gateway a URL mostrada abaixo',
								),
							),
						)
					)
				);
				?>
			</h2>
		</div>
		<?php
		settings_fields( 'hng_security_settings' );

		do_settings_sections( 'hng-commerce-security' );

		submit_button();
	}


	/**

	 * Renderizar tab de tipos de produto
	 */
	public function render_product_types_tab() {
		?>
		<div style="margin-bottom: 20px;">
			<h2 style="display: inline-flex; align-items: center;">
				<?php esc_html_e( 'Tipos de Produto', 'hng-commerce' ); ?>
				<?php
				echo wp_kses_post(
					hng_admin_tooltip(
						'📦 Tipos de Produto Disponíveis',
						array(
							array(
								'icon'    => '�',
								'title'   => 'Simples',
								'content' => 'Produto padrão com preço fixo. Ideal para e-commerce tradicional de produtos físicos.',
							),
							array(
								'icon'    => '🔀',
								'title'   => 'Variável',
								'content' => 'Produto com variações (cores, tamanhos, etc). O cliente seleciona a opção desejada antes de comprar.',
							),
							array(
								'icon'    => '💾',
								'title'   => 'Digital',
								'content' => 'Produto digital para download. Ideal para e-books, softwares, arquivos, cursos em vídeo.',
							),
							array(
								'icon'    => '🔄',
								'title'   => 'Assinatura',
								'content' => 'Cobrança recorrente automática (mensal, anual, etc). Ideal para SaaS, clubes, mensalidades.',
							),
							array(
								'icon'    => '📋',
								'title'   => 'Orçamento',
								'content' => 'Cliente solicita orçamento via chat. Você negocia o valor e envia proposta. Ideal para projetos personalizados.',
							),
							array(
								'icon'    => '📅',
								'title'   => 'Agendamento',
								'content' => 'Serviço com data/hora e profissional. Perfeito para clínicas, salões, consultorias, reservas de espaços.',
							),
						),
						array(
							'title' => '💡 Recomendação',
							'items' => array(
								array(
									'label' => 'Loja tradicional',
									'text'  => 'Ative Simples e/ou Variável',
								),
								array(
									'label' => 'Infoprodutos',
									'text'  => 'Ative Digital + Assinatura',
								),
								array(
									'label' => 'Prestador de serviço',
									'text'  => 'Ative Orçamento + Agendamento',
								),
							),
						)
					)
				);
				?>
			</h2>
		</div>
		<?php
		settings_fields( 'hng_commerce_settings' );

		do_settings_sections( 'hng-commerce-product-types' );

		submit_button();
	}


	/**

	 * Renderizar tab de reembolsos
	 */
	public function render_refund_tab() {
		?>
		<div style="margin-bottom: 20px;">
			<h2 style="display: inline-flex; align-items: center;">
				<?php esc_html_e( 'Política de Reembolsos', 'hng-commerce' ); ?>
				<?php
				echo wp_kses_post(
					hng_admin_tooltip(
						'💸 Sistema de Reembolsos',
						array(
							array(
								'icon'    => '⏰',
								'title'   => 'Prazo para Solicitação',
								'content' => 'Define quantos dias após a compra o cliente pode solicitar reembolso. 0 = desabilitado.',
							),
							array(
								'icon'    => '📝',
								'title'   => 'Motivo Obrigatório',
								'content' => 'Se ativado, o cliente deve informar o motivo do reembolso. Útil para análise de qualidade.',
							),
							array(
								'icon'    => '✅',
								'title'   => 'Aprovação Automática',
								'content' => 'Se ativada, reembolsos dentro do prazo são aprovados automaticamente. Desativada = manual.',
							),
							array(
								'icon'    => '📧',
								'title'   => 'Notificações',
								'content' => 'Emails enviados ao cliente e admin quando um reembolso é solicitado/aprovado/recusado.',
							),
						),
						array(
							'title' => '⚖️ Boas Práticas',
							'items' => array(
								array(
									'label' => 'Produtos digitais',
									'text'  => 'Prazo de 7 dias (CDC brasileiro)',
								),
								array(
									'label' => 'Produtos físicos',
									'text'  => 'Prazo de 7 a 30 dias',
								),
								array(
									'label' => 'Serviços',
									'text'  => 'Avalie caso a caso (manual)',
								),
							),
						)
					)
				);
				?>
			</h2>
		</div>
		<?php
		settings_fields( 'hng_commerce_settings' );

		do_settings_sections( 'hng-commerce-refund' );

		submit_button();
	}


	/**

	 * Renderizar tab de parcelamento PIX
	 */
	public function render_pix_installment_tab() {
		?>
		<div style="margin-bottom: 20px;">
			<h2 style="display: inline-flex; align-items: center;">
				<?php esc_html_e( 'Parcelamento via PIX', 'hng-commerce' ); ?>
				<?php
				echo wp_kses_post(
					hng_admin_tooltip(
						'📲 PIX Parcelado - Como Funciona',
						array(
							array(
								'icon'    => '💡',
								'title'   => 'O que é?',
								'content' => 'Permite que o cliente parcele compras pagando cada parcela via PIX. Você recebe notificações a cada pagamento.',
							),
							array(
								'icon'    => '📈',
								'title'   => 'Juros e Taxas',
								'content' => 'Configure taxa de juros por parcela. O sistema calcula automaticamente o valor total e de cada parcela.',
							),
							array(
								'icon'    => '📅',
								'title'   => 'Vencimento',
								'content' => 'Define o intervalo entre parcelas (ex: 30 dias). O cliente recebe lembrete por email/WhatsApp.',
							),
							array(
								'icon'    => '⚠️',
								'title'   => 'Inadimplência',
								'content' => 'Configure ações para parcelas não pagas: bloqueio de acesso, notificações, etc.',
							),
						),
						array(
							'title' => '🎯 Quando Usar',
							'items' => array(
								array(
									'label' => 'Vantagem',
									'text'  => 'Sem taxas de cartão (você recebe 100%)',
								),
								array(
									'label' => 'Ideal para',
									'text'  => 'Cursos, consultorias, serviços de alto valor',
								),
								array(
									'label' => 'Cuidado',
									'text'  => 'Maior risco de inadimplência vs cartão',
								),
							),
						)
					)
				);
				?>
			</h2>
		</div>
		<?php
		settings_fields( 'hng_commerce_settings' );

		do_settings_sections( 'hng-commerce-pix-installment' );

		submit_button();
	}



	/**

	 * Descrição da seção de segurança
	 */
	public function security_section_description() {

		echo '<p>' . esc_html__( 'Cole o segredo do webhook fornecido por cada gateway para validar a assinatura HMAC.', 'hng-commerce' ) . '</p>';
	}



	/**

	 * Campo de segredo do webhook
	 */
	public function webhook_secret_field( $args ) {

		$gateway = $args['gateway'];

		$label = $args['label'] ?? $gateway;

		$option_key = 'hng_webhook_secret_' . $gateway;

		$value = get_option( $option_key, '' );

		$webhook_url = rest_url( 'hng/v1/webhook/' . $gateway );

		?>

		<input type="text" name="<?php echo esc_attr( $option_key ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text" autocomplete="off">

		<p class="description">

			<?php /* translators: %s: payment gateway label */ ?>
			<?php printf( esc_html__( 'Segredo usado para validar a assinatura HMAC enviada pelo %s.', 'hng-commerce' ), esc_html( $label ) ); ?>

			<br>

			<?php /* translators: %s: webhook URL */ ?>
			<?php printf( esc_html__( 'URL do webhook: %s', 'hng-commerce' ), esc_html( $webhook_url ) ); ?>

		</p>

		<?php
	}



	/**

	 * Sanitiza o segredo do webhook
	 */
	public function sanitize_webhook_secret( $input ) {

		if ( $input === null ) {

			return '';

		}

		$value = trim( (string) $input );

		return $value === '' ? '' : sanitize_text_field( $value );
	}



	/**

	 * Descrição da seção de tipos de produto
	 */
	public function product_types_section_description() {

		echo '<p>' . esc_html__( 'Selecione quais tipos de produto estarão disponíveis na edição de produtos. Tipos desabilitados não aparecerão como opção.', 'hng-commerce' ) . '</p>';

		echo '<style>

            .hng-product-type-toggle { display: flex; align-items: center; gap: 15px; }

            .hng-toggle-switch { position: relative; width: 50px; height: 26px; }

            .hng-toggle-switch input { opacity: 0; width: 0; height: 0; }

            .hng-toggle-slider { position: absolute; cursor: pointer; inset: 0; background: #ccc; border-radius: 26px; transition: .3s; }

            .hng-toggle-slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background: white; border-radius: 50%; transition: .3s; }

            .hng-toggle-switch input:checked + .hng-toggle-slider { background: #2d1810; }

            .hng-toggle-switch input:checked + .hng-toggle-slider:before { transform: translateX(24px); }

            .hng-type-description { color: #666; font-size: 0.9em; }

        </style>';
	}



	/**

	 * Campo de toggle para tipo de produto
	 */
	public function product_type_toggle_field( $args ) {

		$type_key = $args['type_key'];

		$type_info = $args['type_info'];

		$options = get_option( 'hng_commerce_settings', array() );

		// Verificar se já foi configurado alguma vez

		$all_types = $this->get_all_product_types();

		$has_product_type_settings = false;

		foreach ( array_keys( $all_types ) as $tk ) {

			if ( isset( $options[ 'product_type_' . $tk . '_enabled' ] ) ) {

				$has_product_type_settings = true;

				break;

			}
		}

		// Por padrão, todos estão habilitados se nunca foi configurado

		$default = $has_product_type_settings ? 'no' : 'yes';

		if ( $type_key === 'simple' ) {

			$default = 'yes'; // Simple sempre habilitado

		}

		$value = $options[ 'product_type_' . $type_key . '_enabled' ] ?? $default;

		?>

		<div class="hng-product-type-toggle">

			<label class="hng-toggle-switch">

				<input type="checkbox" 

						name="hng_commerce_settings[product_type_<?php echo esc_attr( $type_key ); ?>_enabled]" 

						value="yes" 

						<?php checked( $value, 'yes' ); ?>

						<?php echo esc_attr( $type_key === 'simple' ? 'disabled checked' : '' ); ?>>

				<span class="hng-toggle-slider"></span>

			</label>

			<?php if ( $type_key === 'simple' ) : ?>

				<input type="hidden" name="hng_commerce_settings[product_type_simple_enabled]" value="yes">

			<?php endif; ?>

			<span class="hng-type-description"><?php echo esc_html( $type_info['description'] ); ?></span>

		</div>

		<?php
	}



	/**

	 * Obter todos os tipos de produto disponíveis
	 */
	public function get_all_product_types() {

		// Carregar a classe de tipos se disponível

		if ( class_exists( 'HNG_Product_Type_Fields' ) ) {

			return HNG_Product_Type_Fields::get_product_types();

		}

		// Fallback com tipos padrão

		return array(

			'simple'       => array(

				'label'       => __( 'Simples', 'hng-commerce' ),

				'icon'        => '📦',

				'description' => __( 'Produto padrão', 'hng-commerce' ),

			),

			'variable'     => array(

				'label'       => __( 'Variável', 'hng-commerce' ),

				'icon'        => '🔀',

				'description' => __( 'Produto com variações', 'hng-commerce' ),

			),

			'digital'      => array(

				'label'       => __( 'Digital', 'hng-commerce' ),

				'icon'        => '💾',

				'description' => __( 'Produto digital/download', 'hng-commerce' ),

			),

			'subscription' => array(

				'label'       => __( 'Assinatura', 'hng-commerce' ),

				'icon'        => '🔄',

				'description' => __( 'Produto com pagamento recorrente', 'hng-commerce' ),

			),

			'quote'        => array(

				'label'       => __( 'Orçamento', 'hng-commerce' ),

				'icon'        => '📋',

				'description' => __( 'Produto que requer orçamento', 'hng-commerce' ),

			),

			'appointment'  => array(

				'label'       => __( 'Agendamento', 'hng-commerce' ),

				'icon'        => '📅',

				'description' => __( 'Serviço com horário agendado', 'hng-commerce' ),

			),

		);
	}



	/**

	 * Obter apenas os tipos de produto habilitados
	 */
	public static function get_enabled_product_types() {

		$options = get_option( 'hng_commerce_settings', array() );

		$all_types = self::instance()->get_all_product_types();

		$enabled = array();

		// Verificar se já foi configurado alguma vez

		$has_product_type_settings = false;

		foreach ( array_keys( $all_types ) as $type_key ) {

			if ( isset( $options[ 'product_type_' . $type_key . '_enabled' ] ) ) {

				$has_product_type_settings = true;

				break;

			}
		}

		foreach ( $all_types as $type_key => $type_info ) {

			// Simple está sempre habilitado

			if ( $type_key === 'simple' ) {

				$enabled[ $type_key ] = $type_info;

				continue;

			}

			// Se nunca foi configurado, habilitar todos por padrão

			if ( ! $has_product_type_settings ) {

				$enabled[ $type_key ] = $type_info;

				continue;

			}

			$is_enabled = $options[ 'product_type_' . $type_key . '_enabled' ] ?? 'no';

			if ( $is_enabled === 'yes' ) {

				$enabled[ $type_key ] = $type_info;

			}
		}

		return $enabled;
	}



	/**

	 * Campo de login requerido
	 */
	public function require_login_to_purchase_field() {

		$options = get_option( 'hng_commerce_settings', array() );

		$value = $options['require_login_to_purchase'] ?? 'no';

		?>

		<label>

			<input type="checkbox" 

					name="hng_commerce_settings[require_login_to_purchase]" 

					value="yes" 

					<?php checked( $value, 'yes' ); ?>>

			<?php esc_html_e( 'Exigir que o usuário esteja logado para finalizar uma compra', 'hng-commerce' ); ?>

		</label>

		<p class="description">

			<?php esc_html_e( 'Quando ativado, usuários não logados poderão adicionar produtos ao carrinho e visualizar o checkout, mas não poderão processar o pagamento. Serão redirecionados para login/cadastro mantendo os produtos no carrinho.', 'hng-commerce' ); ?>

		</p>

		<?php
	}



	/**

	 * Campo de redirecionar ao checkout
	 */
	public function redirect_to_checkout_after_add_field() {

		$options = get_option( 'hng_commerce_settings', array() );

		$value = $options['redirect_to_checkout_after_add'] ?? 'no';

		?>

		<label>

			<input type="checkbox" 

					name="hng_commerce_settings[redirect_to_checkout_after_add]" 

					value="yes" 

					<?php checked( $value, 'yes' ); ?>>

			<?php esc_html_e( 'Redirecionar automaticamente para o checkout após adicionar produto ao carrinho', 'hng-commerce' ); ?>

		</label>

		<p class="description">

			<?php esc_html_e( 'Quando ativado, o cliente será redirecionado diretamente para a página de finalização após adicionar um produto ao carrinho.', 'hng-commerce' ); ?>

		</p>

		<?php
	}



	/**

	 * Campo de moeda
	 */
	public function currency_field() {

		$options = get_option( 'hng_commerce_settings', array() );

		$value = $options['currency'] ?? 'BRL';

		$currencies = array(

			'BRL' => 'Real Brasileiro (R$)',

			'USD' => 'Dólar Americano ($)',

			'EUR' => 'Euro (€)',

		);

		echo '<select name="hng_commerce_settings[currency]" id="currency">';

		foreach ( $currencies as $code => $label ) {

			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $code ),
				selected( $value, $code, false ),
				esc_html( $label )
			);

		}

		echo '</select>';
	}



	/**

	 * Campo de posição da moeda
	 */
	public function currency_position_field() {

		$options = get_option( 'hng_commerce_settings', array() );

		$value = $options['currency_position'] ?? 'left';

		$positions = array(

			'left'        => 'Esquerda (R$99)',

			'right'       => 'Direita (99R$)',

			'left_space'  => 'Esquerda com espaço (R$ 99)',

			'right_space' => 'Direita com espaço (99 R$)',

		);

		echo '<select name="hng_commerce_settings[currency_position]" id="currency_position">';

		foreach ( $positions as $pos => $label ) {

			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $pos ),
				selected( $value, $pos, false ),
				esc_html( $label )
			);

		}

		echo '</select>';
	}



	/**

	 * Campo separador de milhares
	 */
	public function thousand_separator_field() {

		$options = get_option( 'hng_commerce_settings', array() );

		$value = $options['thousand_separator'] ?? '.';

		printf(
			'<input type="text" name="hng_commerce_settings[thousand_separator]" value="%s" size="2">',
			esc_attr( $value )
		);
	}



	/**

	 * Campo separador de decimais
	 */
	public function decimal_separator_field() {

		$options = get_option( 'hng_commerce_settings', array() );

		$value = $options['decimal_separator'] ?? ',';

		printf(
			'<input type="text" name="hng_commerce_settings[decimal_separator]" value="%s" size="2">',
			esc_attr( $value )
		);
	}



	/**

	 * Campo número de decimais
	 */
	public function number_decimals_field() {

		$options = get_option( 'hng_commerce_settings', array() );

		$value = $options['number_decimals'] ?? 2;

		printf(
			'<input type="number" name="hng_commerce_settings[number_decimals]" value="%s" min="0" max="4">',
			esc_attr( $value )
		);
	}



	/**

	 * Descrição da seção de páginas
	 */
	public function pages_section_description() {

		echo '<p>' . esc_html__( 'Selecione as páginas que serão usadas para cada funcionalidade do sistema.', 'hng-commerce' ) . '</p>';
	}



	/**

	 * Campo de página
	 */
	public function page_field( $args ) {

		$page_key = $args['page_key'];

		$options = get_option( 'hng_commerce_settings', array() );

		$value = $options[ $page_key . '_page' ] ?? 0;

		wp_dropdown_pages(
			array(

				'name'             => 'hng_commerce_settings[' . esc_attr( $page_key ) . '_page]',

				'selected'         => absint( $value ),

				'show_option_none' => esc_html__( '— Selecione —', 'hng-commerce' ),

			)
		);
	}



	/**

	 * Get page label
	 */
	private function get_page_label( $page_key ) {

		$labels = array(

			'shop'               => __( 'Página da Loja', 'hng-commerce' ),

			'cart'               => __( 'Página do Carrinho', 'hng-commerce' ),

			'checkout'           => __( 'Página de Checkout', 'hng-commerce' ),

			'my_account'         => __( 'Página da Minha Conta', 'hng-commerce' ),

			'order_confirmation' => __( 'Página de Confirmação', 'hng-commerce' ),

		);

		return $labels[ $page_key ] ?? $page_key;
	}



	/**

	 * Campo PIX installment enabled
	 */
	public function pix_installment_enabled_field() {

		$options = get_option( 'hng_commerce_settings', array() );

		$value = $options['pix_installment_enabled'] ?? 'no';

		printf(
			'<label><input type="checkbox" name="hng_commerce_settings[pix_installment_enabled]" value="yes"%s> %s</label>',
			checked( $value, 'yes', false ),
			esc_html__( 'Permitir parcelamento via PIX', 'hng-commerce' )
		);
	}



	/**

	 * Campo PIX installment max
	 */
	public function pix_installment_max_field() {

		$options = get_option( 'hng_commerce_settings', array() );

		$value = $options['pix_installment_max'] ?? 12;

		printf(
			'<input type="number" name="hng_commerce_settings[pix_installment_max]" value="%s" min="2" max="12">',
			esc_attr( $value )
		);
	}



	/**

	 * Campo PIX installment min value
	 */
	public function pix_installment_min_value_field() {

		$options = get_option( 'hng_commerce_settings', array() );

		$value = $options['pix_installment_min_value'] ?? 30.00;

		printf(
			'<input type="number" name="hng_commerce_settings[pix_installment_min_value]" value="%s" min="0" step="0.01"> <span class="description">%s</span>',
			esc_attr( $value ),
			esc_html__( 'Valor mínimo que cada parcela deve ter', 'hng-commerce' )
		);
	}



	/**

	 * Campo PIX installment fee
	 */
	public function pix_installment_fee_field() {

		$options = get_option( 'hng_commerce_settings', array() );

		$value = $options['pix_installment_fee'] ?? 0;

		printf(
			'<input type="number" name="hng_commerce_settings[pix_installment_fee]" value="%s" min="0" step="0.01"> <span class="description">%%</span>',
			esc_attr( $value )
		);
	}



	/**

	 * Renderizar tab de Autenticação
	 */
	public function render_auth_tab() {

		$options = get_option( 'hng_commerce_settings', array() );

		// Google OAuth settings

		$google_enabled = $options['google_oauth_enabled'] ?? 'no';

		$google_client_id = $options['google_oauth_client_id'] ?? '';

		$google_client_secret = $options['google_oauth_client_secret'] ?? '';

		// Callback URL for Google Console

		$callback_url = site_url( '/' ) . '?hng_google_oauth=callback';

		?>

		<h2><?php esc_html_e( 'Configurações de Autenticação', 'hng-commerce' ); ?></h2>

		

		<table class="form-table" role="presentation">

			<!-- Google OAuth Section -->

			<tr>

				<th scope="row" colspan="2">

					<h3 style="margin: 0;">

						<span class="dashicons dashicons-google" style="color: #4285F4;"></span>

						<?php esc_html_e( 'Login com Google', 'hng-commerce' ); ?>

					</h3>

				</th>

			</tr>

			

			<tr>

				<th scope="row">

					<label for="google_oauth_enabled"><?php esc_html_e( 'Habilitar Login com Google', 'hng-commerce' ); ?></label>

				</th>

				<td>

					<label>

						<input type="checkbox" 

								id="google_oauth_enabled"

								name="hng_commerce_settings[google_oauth_enabled]" 

								value="yes" <?php checked( $google_enabled, 'yes' ); ?>>

						<?php esc_html_e( 'Permitir que usuários façam login com suas contas Google', 'hng-commerce' ); ?>

					</label>

				</td>

			</tr>

			

			<tr>

				<th scope="row">

					<label for="google_oauth_client_id"><?php esc_html_e( 'Client ID', 'hng-commerce' ); ?></label>

				</th>

				<td>

					<input type="text" 

							id="google_oauth_client_id"

							name="hng_commerce_settings[google_oauth_client_id]" 

							value="<?php echo esc_attr( $google_client_id ); ?>"

							class="regular-text"

							placeholder="XXXXXXXXXX.apps.googleusercontent.com">

					<p class="description">

						<?php esc_html_e( 'Obtido no Google Cloud Console > APIs & Services > Credentials', 'hng-commerce' ); ?>

					</p>

				</td>

			</tr>

			

			<tr>

				<th scope="row">

					<label for="google_oauth_client_secret"><?php esc_html_e( 'Client Secret', 'hng-commerce' ); ?></label>

				</th>

				<td>

					<input type="password" 

							id="google_oauth_client_secret"

							name="hng_commerce_settings[google_oauth_client_secret]" 

							value="<?php echo esc_attr( $google_client_secret ); ?>"

							class="regular-text"

							placeholder="GOCSPX-XXXXXXXXXX">

					<p class="description">

						<?php esc_html_e( 'Chave secreta do cliente OAuth 2.0', 'hng-commerce' ); ?>

					</p>

				</td>

			</tr>

			

			<tr>

				<th scope="row">

					<?php esc_html_e( 'URL de Callback', 'hng-commerce' ); ?>

				</th>

				<td>

					<code style="padding: 8px 12px; background: #f1f1f1; display: inline-block; margin-bottom: 8px;">

						<?php echo esc_html( $callback_url ); ?>

					</code>

					<button type="button" class="button button-small" onclick="navigator.clipboard.writeText('<?php echo esc_js( $callback_url ); ?>'); this.textContent='<?php esc_attr_e( 'Copiado!', 'hng-commerce' ); ?>'; setTimeout(() => this.textContent='<?php esc_attr_e( 'Copiar', 'hng-commerce' ); ?>', 2000);">

						<?php esc_html_e( 'Copiar', 'hng-commerce' ); ?>

					</button>

					<p class="description">

						<?php esc_html_e( 'Adicione esta URL como "Authorized redirect URIs" nas configurações do seu OAuth Client no Google Cloud Console.', 'hng-commerce' ); ?>

					</p>

				</td>

			</tr>

		</table>

		

		<hr style="margin: 2em 0;">

		

		<div class="hng-auth-help">

			<h3><?php esc_html_e( 'Como configurar o Google OAuth', 'hng-commerce' ); ?></h3>

			<ol style="line-height: 1.8;">

				<li><?php esc_html_e( 'Acesse o Google Cloud Console:', 'hng-commerce' ); ?> <a href="https://console.cloud.google.com/" target="_blank">console.cloud.google.com</a></li>

				<li><?php esc_html_e( 'Crie um novo projeto ou selecione um existente', 'hng-commerce' ); ?></li>

				<li><?php esc_html_e( 'Vá para "APIs & Services" > "Credentials"', 'hng-commerce' ); ?></li>

				<li><?php esc_html_e( 'Clique em "Create Credentials" > "OAuth client ID"', 'hng-commerce' ); ?></li>

				<li><?php esc_html_e( 'Selecione "Web application" como tipo', 'hng-commerce' ); ?></li>

				<li><?php esc_html_e( 'Adicione a URL de callback acima em "Authorized redirect URIs"', 'hng-commerce' ); ?></li>

				<li><?php esc_html_e( 'Copie o Client ID e Client Secret para os campos acima', 'hng-commerce' ); ?></li>

				<li><?php esc_html_e( 'Configure a tela de consentimento OAuth (OAuth consent screen) com as informações do seu site', 'hng-commerce' ); ?></li>

			</ol>

		</div>

		

		<?php

		settings_fields( 'hng_commerce_settings' );

		submit_button();
	}


	/**

	 * Campo: Habilitar Sistema de Reembolsos
	 */
	public function refund_enabled_field() {

		$options = get_option( 'hng_commerce_settings', array() );

		$value = $options['refund_enabled'] ?? 'yes';

		?>

		<label>

			<input type="checkbox" 

					name="hng_commerce_settings[refund_enabled]" 

					value="yes" 

					<?php checked( $value, 'yes' ); ?>>

			<?php esc_html_e( 'Habilitar sistema de reembolsos para clientes', 'hng-commerce' ); ?>

		</label>

		<p class="description">

			<?php esc_html_e( 'Quando habilitado, os clientes poderão solicitar reembolsos através da página Minha Conta.', 'hng-commerce' ); ?>

		</p>

		<?php
	}


	/**

	 * Campo: Dias Máximos para Solicitar Reembolso
	 */
	public function refund_max_days_field() {

		$options = get_option( 'hng_commerce_settings', array() );

		$value = $options['refund_max_days'] ?? 30;

		?>

		<input type="number" 

				name="hng_commerce_settings[refund_max_days]"

				value="<?php echo esc_attr( $value ); ?>"

				min="1"

				max="365"

				class="small-text">

		<p class="description">

			<?php esc_html_e( 'Número máximo de dias após a compra para solicitar reembolso.', 'hng-commerce' ); ?>

		</p>

		<?php
	}


	/**

	 * Campo: Exigir Motivo do Reembolso
	 */
	public function refund_require_reason_field() {

		$options = get_option( 'hng_commerce_settings', array() );

		$value = $options['refund_require_reason'] ?? 'yes';

		?>

		<label>

			<input type="checkbox" 

					name="hng_commerce_settings[refund_require_reason]" 

					value="yes" 

					<?php checked( $value, 'yes' ); ?>>

			<?php esc_html_e( 'Exigir que o cliente especifique um motivo para o reembolso', 'hng-commerce' ); ?>

		</label>

		<p class="description">

			<?php esc_html_e( 'Quando habilitado, os clientes terão que informar o motivo ao solicitar um reembolso.', 'hng-commerce' ); ?>

		</p>

		<?php
	}


	/**

	 * Campo: Motivos de Reembolso Disponíveis
	 */
	public function refund_reasons_field() {

		$options = get_option( 'hng_commerce_settings', array() );

		$value = isset( $options['refund_reasons'] ) ? implode( "\n", (array) $options['refund_reasons'] ) : "Produto Defeituoso\nProduto Não Chegou\nNão Gostei do Produto\nCompra por Engano\nMudança de Ideia\nOutro Motivo";

		?>

		<textarea name="hng_commerce_settings[refund_reasons]"

					rows="6"

					cols="50"

					class="large-text"

					placeholder="<?php esc_attr_e( 'Um motivo por linha...', 'hng-commerce' ); ?>"><?php echo esc_textarea( $value ); ?></textarea>

		<p class="description">

			<?php esc_html_e( 'Motivos disponíveis para que o cliente escolha ao solicitar reembolso. Um motivo por linha.', 'hng-commerce' ); ?>

		</p>

		<?php
	}


	/**

	 * Campo: Permitir Upload de Evidências
	 */
	public function refund_allow_evidence_field() {

		$options = get_option( 'hng_commerce_settings', array() );

		$value = $options['refund_allow_evidence'] ?? 'yes';

		?>

		<label>

			<input type="checkbox" 

					name="hng_commerce_settings[refund_allow_evidence]" 

					value="yes" 

					<?php checked( $value, 'yes' ); ?>>

			<?php esc_html_e( 'Permitir que o cliente envie anexos/evidências para suportar sua solicitação', 'hng-commerce' ); ?>

		</label>

		<p class="description">

			<?php esc_html_e( 'Quando habilitado, os clientes podem enviar screenshots, fotos ou outros arquivos como prova.', 'hng-commerce' ); ?>

		</p>

		<?php
	}


	/**

	 * Campo: Aprovar Reembolsos Automaticamente
	 */
	public function refund_auto_approve_field() {

		$options = get_option( 'hng_commerce_settings', array() );

		$value = $options['refund_auto_approve'] ?? 'no';

		?>

		<label>

			<input type="checkbox" 

					name="hng_commerce_settings[refund_auto_approve]" 

					value="yes" 

					<?php checked( $value, 'yes' ); ?>>

			<?php esc_html_e( 'Aprovar reembolsos automaticamente (sem necessidade de revisão manual)', 'hng-commerce' ); ?>

		</label>

		<p class="description">

			<?php esc_html_e( '⚠️ CUIDADO: Quando habilitado, todos os reembolsos serão aprovados automaticamente e devolvidos ao cliente.', 'hng-commerce' ); ?>

		</p>

		<?php
	}
}



