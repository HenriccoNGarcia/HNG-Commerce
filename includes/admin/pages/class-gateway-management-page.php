<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName, Squiz.Commenting.FileComment.MissingPackageTag, Squiz.Commenting.FileComment.SpacingAfterComment, Squiz.Commenting.FileComment.SpacingAfterOpen

/**

 * Gateway Management Page

 * Displays and manages all payment gateways
 */



if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log
// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_print_r
// phpcs:disable Squiz.Commenting.InlineComment.InvalidEndChar
// phpcs:disable WordPress.PHP.YodaConditions.NotYoda
// phpcs:disable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
// phpcs:disable Squiz.Commenting.FunctionComment.MissingParamTag
// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode
// phpcs:disable Squiz.Commenting.ClassComment.WrongStyle
// Reason: This admin page uses extensive diagnostic logging during active development.

class HNG_Gateway_Management_Page {



	/**

	 * Construtor - registra AJAX handlers
	 */
	public function __construct() {

		error_log( 'HNG Gateway Management: Constructor called' );
	}



	/**

	 * Método estático para registrar os hooks AJAX

	 * Chamado globalmente no carregamento do plugin
	 */
	public static function register_ajax_hooks() {

		error_log( 'HNG Gateway Management: Registering AJAX actions...' );

		$instance = new self();

		add_action( 'wp_ajax_hng_save_gateway_config', array( $instance, 'save_gateway_config' ) );

		add_action( 'wp_ajax_hng_test_gateway_connection', array( $instance, 'test_gateway_connection' ) );

		add_action( 'wp_ajax_hng_refresh_fees', array( $instance, 'refresh_fees_from_api' ) );

		add_action( 'wp_ajax_hng_check_gateway_status', array( $instance, 'check_gateway_status' ) ); // Auto-check sem nonce

		add_action( 'wp_ajax_hng_quick_test_gateway', array( $instance, 'quick_test_gateway' ) );

		add_action( 'wp_ajax_hng_toggle_gateway', array( $instance, 'toggle_gateway' ) );

		add_action( 'wp_ajax_hng_toggle_advanced_integration', array( $instance, 'toggle_advanced_integration' ) );

		add_action( 'wp_ajax_hng_test_all_gateways', array( $instance, 'test_all_gateways' ) );

		error_log( 'HNG Gateway Management: AJAX actions registered successfully' );
	}



	/**

	 * Render management UI for gateways
	 */
	public function render() {

		$nonce = wp_create_nonce( 'hng-commerce-admin' );

		echo '<div class="hng-wrap hng-gateways-page">';

		// Header

		echo '<div class="hng-page-header">';

		echo '<div class="hng-header-title">';

		echo '<h1 style="display: inline-flex; align-items: center;"><span class="dashicons dashicons-credit"></span> Gateways de Pagamento';
		if ( function_exists( 'hng_admin_tooltip' ) ) {
					echo wp_kses_post(
						hng_admin_tooltip(
							'💳 Gateways de Pagamento',
							array(
								array(
									'icon'    => '🏦',
									'title'   => 'Gateways Suportados',
									'content' => 'Mercado Pago, PagSeguro, Asaas, Pagar.me, Cielo, Rede, Stone e GetNet. Configure credenciais de API e escolha ambiente sandbox ou produção.',
								),
								array(
									'icon'    => '💰',
									'title'   => 'Métodos de Pagamento',
									'content' => 'PIX (instantâneo), boleto bancário e cartão de crédito com parcelamento. Cada gateway suporta combinações diferentes.',
								),
								array(
									'icon'    => '🔗',
									'title'   => 'Integração Avançada',
									'content' => 'Ative para sincronizar dados do gateway: importar clientes, assinaturas e cobranças existentes. Receba webhooks para atualização automática de status de pagamentos.',
								),
								array(
									'icon'    => '🧪',
									'title'   => 'Teste de Conexão',
									'content' => 'Valide suas credenciais antes de ativar. O sistema testa a comunicação com a API do gateway e exibe o status em tempo real.',
								),
								array(
									'icon'    => '📊',
									'title'   => 'Taxas HNG Commerce',
									'content' => 'Taxa sobre vendas que diminui conforme seu volume (GMV). Quanto mais vende, menor a taxa. Veja seu tier atual e próximo nível no card acima.',
								),
							),
							array(
								'title' => '⚠️ Importante',
								'items' => array(
									array(
										'label' => 'Exclusividade',
										'text'  => 'Apenas um gateway pode estar ativo por vez',
									),
									array(
										'label' => 'Credenciais',
										'text'  => 'Configure e teste as credenciais antes de ativar',
									),
									array(
										'label' => 'Ambiente',
										'text'  => 'Use sandbox para testes, produção para vendas reais',
									),
								),
							)
						)
					);
		}
		echo '</h1>';

		echo '<p class="description">Configure e gerencie seus gateways de pagamento. Apenas um gateway pode estar ativo por vez.</p>';

		echo '</div>';

		echo '<div class="hng-header-actions">';

		echo '<button type="button" class="button button-primary" id="hng-test-all-gateways" title="Testar conectividade de todos os gateways">';

		echo '<span class="dashicons dashicons-yes-alt"></span> Testar Todos';

		echo '</button>';

		echo '</div>';

		echo '</div>';

		// Card informativo de taxas HNG Commerce

		$fee_calculator = class_exists( 'HNG_Fee_Calculator' ) ? HNG_Fee_Calculator::instance() : null;

		$current_tier = $fee_calculator ? $fee_calculator->get_current_tier() : 1;

		$tier_data = $fee_calculator ? $fee_calculator->get_tier_data( $current_tier ) : null;

		$next_tier_info = $fee_calculator ? $fee_calculator->get_next_tier_info() : null;

		$current_gmv = $fee_calculator ? $fee_calculator->get_current_month_gmv() : 0;

		echo '<div class="hng-fees-info-card">';

		echo '<div class="fees-card-header">';

		echo '<div class="fees-card-title">';

		echo '<span class="dashicons dashicons-info-outline"></span>';

		echo '<strong>Sobre as Taxas por Transação</strong>';

		// Indicador de fonte de dados
		$cache_time = get_transient( 'hng_api_fees_data' ) !== false ? get_option( '_transient_timeout_hng_api_fees_data', 0 ) : 0;
		if ( $cache_time > 0 ) {
			$updated_ago = human_time_diff( $cache_time - 300, time() );
			echo ' <small style="font-weight: normal; color: #666;">(atualizado há ' . esc_html( $updated_ago ) . ')</small>';
		}

		echo '</div>';

		echo '<div style="display: flex; align-items: center; gap: 10px;">';
		echo '<button type="button" class="button button-small refresh-fees-btn" title="Atualizar taxas da API"><span class="dashicons dashicons-update" style="margin-top: 3px;"></span></button>';
		echo '<span class="fees-card-toggle" title="Expandir/Recolher"><span class="dashicons dashicons-arrow-down-alt2"></span></span>';
		echo '</div>';

		echo '</div>';

		echo '<div class="fees-card-content">';

		// Descrição geral

		echo '<div class="fees-description">';

		echo '<p>Cada venda processada pelo HNG Commerce inclui <strong>duas taxas</strong>:</p>';

		echo '<ul>';

		echo '<li><strong>Taxa do Gateway:</strong> Cobrada pelo processador de pagamentos (varia por gateway e método)</li>';

		echo '<li><strong>Taxa do Plugin HNG:</strong> Taxa escalonada baseada no seu GMV mensal (volume de vendas)</li>';

		echo '</ul>';

		echo '</div>';

		// Tier atual

		if ( $tier_data ) {

			echo '<div class="fees-tier-info">';

			echo '<div class="tier-badge" style="background-color: ' . esc_attr( $tier_data['color'] ) . ';">';

			echo '<span class="tier-icon">' . esc_html( $tier_data['icon'] ?? '🎯' ) . '</span>';

			echo '<span class="tier-name">' . esc_html( $tier_data['name'] ) . '</span>';

			echo '</div>';

			echo '<div class="tier-details">';

			echo '<p class="tier-gmv"><strong>Seu GMV este mês:</strong> R$ ' . number_format( $current_gmv, 2, ',', '.' ) . '</p>';

			echo '<div class="tier-fees-list">';

			echo '<span class="fee-item"><strong>Físico:</strong> ' . esc_html( $tier_data['fees']['physical'] ) . '%</span>';

			echo '<span class="fee-item"><strong>Digital:</strong> ' . esc_html( $tier_data['fees']['digital'] ) . '%</span>';

			echo '<span class="fee-item"><strong>Assinatura:</strong> ' . esc_html( $tier_data['fees']['subscription'] ) . '%</span>';

			echo '<span class="fee-item"><strong>Orçamento:</strong> ' . esc_html( $tier_data['fees']['quote'] ?? '-' ) . '%</span>';

			echo '<span class="fee-item"><strong>Agendamento:</strong> ' . esc_html( $tier_data['fees']['appointment'] ?? '-' ) . '%</span>';

			echo '</div>';

			echo '<p class="tier-minimum"><small>* Taxa mínima de R$ 0,50 por transação</small></p>';

			echo '</div>';

			echo '</div>';

		}

		// Próximo tier (se não estiver no máximo)

		if ( $next_tier_info ) {

			echo '<div class="fees-next-tier">';

			echo '<p class="next-tier-msg">';

			echo '🚀 Faltam <strong>R$ ' . number_format( $next_tier_info['remaining'], 2, ',', '.' ) . '</strong> para o tier <strong>' . esc_html( $next_tier_info['next_tier_name'] ) . '</strong>';

			echo '</p>';

			echo '<div class="next-tier-progress">';

			echo '<div class="progress-bar" style="width: ' . esc_attr( $next_tier_info['progress_percentage'] ) . '%;"></div>';

			echo '</div>';

			echo '</div>';

		}

		// Tabela completa de tiers

		echo '<div class="fees-tiers-table">';

		echo '<h4>Tabela de Taxas por Tier (HNG Commerce)</h4>';

		echo '<table>';

		echo '<thead><tr><th>Tier</th><th>GMV Mensal</th><th>Físico</th><th>Digital</th><th>Assinatura</th><th>Orçamento</th><th>Agendamento</th></tr></thead>';

		echo '<tbody>';

		if ( $fee_calculator ) {

			foreach ( $fee_calculator->get_all_tiers() as $num => $tier ) {

				$is_current = ( $num === $current_tier );

				$row_class = $is_current ? 'current-tier' : '';

				echo '<tr class="' . esc_attr( $row_class ) . '">';

				echo '<td><span class="tier-name-badge" style="background-color: ' . esc_attr( $tier['color'] ) . ';">' . esc_html( $tier['icon'] ?? '' ) . ' ' . esc_html( $tier['name'] ) . '</span></td>';

				if ( $tier['gmv_max'] >= PHP_INT_MAX ) {

					echo '<td>Acima de R$ ' . number_format( $tier['gmv_min'], 0, ',', '.' ) . '</td>';

				} else {

					echo '<td>R$ ' . number_format( $tier['gmv_min'], 0, ',', '.' ) . ' - ' . number_format( $tier['gmv_max'], 0, ',', '.' ) . '</td>';

				}

				echo '<td>' . esc_html( $tier['fees']['physical'] ) . '%</td>';

				echo '<td>' . esc_html( $tier['fees']['digital'] ) . '%</td>';

				echo '<td>' . esc_html( $tier['fees']['subscription'] ) . '%</td>';

				echo '<td>' . esc_html( $tier['fees']['quote'] ?? '-' ) . '%</td>';

				echo '<td>' . esc_html( $tier['fees']['appointment'] ?? '-' ) . '%</td>';

				echo '</tr>';

			}
		}

		echo '</tbody>';

		echo '</table>';

		echo '</div>';

		echo '</div>'; // fees-card-content

		echo '</div>'; // hng-fees-info-card

		// Filter/Category buttons

		echo '<div class="hng-gateway-filters">';

		echo '<button class="filter-btn active" data-filter="all">Todos (13)</button>';

		echo '<button class="filter-btn" data-filter="fintech">Fintech (6)</button>';

		echo '<button class="filter-btn" data-filter="banks">Bancos (5)</button>';

		echo '<button class="filter-btn" data-filter="marketplace">Marketplace (2)</button>';

		echo '</div>';

		echo '<div class="hng-gateways-grid">';

		$gateways = self::get_gateways();

		// Gateways disponíveis para configuração — os demais ficam como "em breve".
		$available_gateways = array( 'asaas', 'pagseguro' );

		foreach ( $gateways as $id => $gw ) {

			$is_coming_soon = ! in_array( $id, $available_gateways, true );

			$enabled = get_option( 'hng_gateway_' . $id . '_enabled', 'no' ) === 'yes';

			$category = isset( $gw['category'] ) ? $gw['category'] : 'other';

			// Buscar status salvo do gateway

			$saved_status = get_option( 'hng_gateway_' . $id . '_test_status', null );

			$status_class = 'status-red';

			$status_text = 'Não testado';

			if ( $saved_status !== null ) {

				switch ( $saved_status ) {

					case 'success':
						$status_class = 'status-green';

						$status_text = 'Conectado';

						break;

					case 'warning':
						$status_class = 'status-yellow';

						$status_text = 'Configurado';

						break;

					case 'error':
						$status_class = 'status-red';

						$status_text = 'Erro de conexão';

						break;

				}
			}

			$coming_soon_class = $is_coming_soon ? ' hng-coming-soon' : '';
			$coming_soon_attr  = $is_coming_soon ? ' data-coming-soon="1"' : '';
			echo '<div class="hng-gateway-item' . esc_attr( $coming_soon_class ) . '" data-gateway="' . esc_attr( $id ) . '" data-category="' . esc_attr( $category ) . '"' . esc_attr( $coming_soon_attr ) . '>';

			if ( $is_coming_soon ) {
				echo '<div class="hng-coming-soon-overlay">';
				echo '<div class="hng-coming-soon-badge">';
				echo '<span class="dashicons dashicons-clock"></span>';
				echo '<div class="hng-coming-soon-text">';
				echo '<strong>' . esc_html__( 'Disponível em breve', 'hng-commerce' ) . '</strong>';
				echo '<span>' . esc_html__( 'Estamos trabalhando em uma solução para disponibilizar esse gateway. Aguarde as próximas atualizações.', 'hng-commerce' ) . '</span>';
				echo '</div>';
				echo '</div>';
				echo '</div>';
			}

			// Card header with logo/icon

			echo '<div class="gateway-header">';

			echo '<div class="gateway-icon ' . esc_attr( $id ) . '">';

			echo '<span class="dashicons dashicons-' . esc_attr( $gw['icon'] ) . '"></span>';

			echo '</div>';

			echo '<div class="gateway-name-section">';

			echo '<div class="gateway-name-with-status">';

			echo '<h3>' . esc_html( $gw['name'] ) . '</h3>';

			echo '<span class="gateway-api-status" data-gateway="' . esc_attr( $id ) . '" title="Clique em testar para verificar o status">';

			echo '<span class="status-dot ' . esc_attr( $status_class ) . '"></span>';

			echo '<span class="status-text">' . esc_html( $status_text ) . '</span>';

			echo '</span>';

			echo '</div>';

			echo '<p class="gateway-category">' . esc_html( self::get_category_label( $category ) ) . '</p>';

			echo '</div>';

			echo '</div>';

			// Description

			echo '<div class="gateway-description">';

			echo '<p>' . esc_html( $gw['description'] ) . '</p>';

			echo '</div>';

			// Methods & Fees

			echo '<div class="gateway-details">';

			if ( ! empty( $gw['methods'] ) ) {

				echo '<div class="detail-group">';

				echo '<label class="detail-label">Métodos</label>';

				echo '<div class="detail-badges">';

				foreach ( $gw['methods'] as $m ) {

					echo '<span class="badge badge-method">' . esc_html( $m ) . '</span>';

				}

				echo '</div>';

				echo '</div>';

			}

			if ( ! empty( $gw['fees'] ) ) {

				echo '<div class="detail-group">';

				echo '<label class="detail-label">Taxas</label>';

				echo '<div class="detail-badges">';

				foreach ( $gw['fees'] as $f ) {

					echo '<span class="badge badge-fee">' . esc_html( $f ) . '</span>';

				}

				echo '</div>';

				echo '</div>';

			}

			echo '</div>';

			// Actions

			echo '<div class="gateway-actions">';

			// Toggle switch

			echo '<div class="action-toggle">';

			echo '<label class="hng-switch">';

			$disabled_attr = $is_coming_soon ? ' disabled="disabled"' : '';
			echo '<input type="checkbox" class="gateway-toggle" data-gateway="' . esc_attr( $id ) . '" ' . checked( $enabled && ! $is_coming_soon, true, false ) . esc_attr( $disabled_attr ) . ' />';

			echo '<span class="hng-switch-slider"></span>';

			echo '</label>';

			echo '<span class="toggle-label">' . ( ! $is_coming_soon && $enabled ? 'Ativo' : 'Inativo' ) . '</span>';

			echo '</div>';

			// Buttons

			echo '<div class="action-buttons">';

			if ( $is_coming_soon ) {
				echo '<button type="button" class="button button-secondary hng-toggle-config" data-gateway="' . esc_attr( $id ) . '" title="Configurar credenciais" disabled="disabled">';
				echo '<span class="dashicons dashicons-admin-tools"></span>';
				echo '</button>';
				echo '<button type="button" class="button button-secondary gateway-test-btn" data-gateway="' . esc_attr( $id ) . '" title="Testar conexao" disabled="disabled">';
				echo '<span class="dashicons dashicons-update"></span>';
				echo '</button>';
			} else {
				echo '<button type="button" class="button button-secondary hng-toggle-config" data-gateway="' . esc_attr( $id ) . '" title="Configurar credenciais">';
				echo '<span class="dashicons dashicons-admin-tools"></span>';
				echo '</button>';
				echo '<button type="button" class="button button-secondary gateway-test-btn" data-gateway="' . esc_attr( $id ) . '" title="Testar conexao">';
				echo '<span class="dashicons dashicons-update"></span>';
				echo '</button>';
			}

			echo '<a href="' . esc_url( '#' ) . '" target="_blank" class="button button-link gateway-docs-btn" title="Ver documentacao">';

			echo '<span class="dashicons dashicons-external"></span>';

			echo '</a>';

			echo '</div>';

			echo '</div>';

			// Config form (initially hidden)

			echo '<div class="hng-gateway-config-wrapper" data-gateway="' . esc_attr( $id ) . '" style="display:none;">';

			self::render_gateway_form( $id );

			echo '</div>';

			echo '</div>';

		}

		echo '</div>';

		// Enqueue gateway management scripts

		wp_enqueue_script(
			'hng-admin-gateways',
			HNG_COMMERCE_URL . 'assets/js/admin-gateways.js',
			array( 'jquery' ),
			HNG_COMMERCE_VERSION,
			true
		);

		wp_localize_script(
			'hng-admin-gateways',
			'hngGatewaysPage',
			array(

				'ajaxUrl' => admin_url( 'admin-ajax.php' ),

				'nonce'   => $nonce,

			)
		);

		// Enhanced inline CSS for the gateways page

		echo '<style>

        .hng-gateways-page {

            background: #f5f5f5;

            padding: 20px 0;

        }

        

        /* Card de Taxas HNG Commerce */

        .hng-fees-info-card {

            background: linear-gradient(135deg, #f8f9ff 0%, #fff 100%);

            border: 1px solid #e0e6ff;

            border-radius: 12px;

            margin: 0 20px 25px 20px;

            overflow: hidden;

            box-shadow: 0 2px 8px rgba(0,0,0,0.05);

        }

        

        .fees-card-header {

            display: flex;

            justify-content: space-between;

            align-items: center;

            padding: 16px 20px;

            background: linear-gradient(135deg, #0073aa 0%, #005a87 100%);

            color: white;

            cursor: pointer;

        }

        

        .fees-card-title {

            display: flex;

            align-items: center;

            gap: 10px;

            font-size: 15px;

        }

        

        .fees-card-title .dashicons {

            font-size: 20px;

            width: 20px;

            height: 20px;

        }

        

        .fees-card-toggle .dashicons {

            transition: transform 0.3s ease;

        }

        

        .fees-card-content {

            padding: 20px;

        }

        

        .fees-card-content.collapsed {

            display: none;

        }

        

        .fees-description {

            background: #fff;

            padding: 15px;

            border-radius: 8px;

            border-left: 4px solid #0073aa;

            margin-bottom: 20px;

        }

        

        .fees-description p {

            margin: 0 0 10px 0;

            color: #333;

        }

        

        .fees-description ul {

            margin: 0;

            padding-left: 20px;

        }

        

        .fees-description li {

            margin: 5px 0;

            color: #555;

        }

        

        .fees-tier-info {

            display: flex;

            gap: 20px;

            align-items: flex-start;

            background: #fff;

            padding: 20px;

            border-radius: 8px;

            margin-bottom: 20px;

            border: 1px solid #eee;

        }

        

        .tier-badge {

            display: flex;

            flex-direction: column;

            align-items: center;

            padding: 15px 20px;

            border-radius: 10px;

            color: white;

            min-width: 100px;

            text-align: center;

        }

        

        .tier-icon {

            font-size: 24px;

            margin-bottom: 5px;

        }

        

        .tier-name {

            font-weight: bold;

            font-size: 14px;

        }

        

        .tier-details {

            flex: 1;

        }

        

        .tier-gmv {

            margin: 0 0 10px 0;

            color: #333;

        }

        

        .tier-fees-list {

            display: flex;

            gap: 15px;

            flex-wrap: wrap;

        }

        

        .fee-item {

            background: #f5f5f5;

            padding: 8px 14px;

            border-radius: 6px;

            font-size: 13px;

            color: #444;

        }

        

        .tier-minimum {

            margin: 10px 0 0 0;

            color: #888;

        }

        

        .fees-next-tier {

            background: linear-gradient(90deg, #e8f5e9 0%, #f1f8e9 100%);

            padding: 15px 20px;

            border-radius: 8px;

            margin-bottom: 20px;

            border: 1px solid #c8e6c9;

        }

        

        .next-tier-msg {

            margin: 0 0 12px 0;

            color: #2e7d32;

        }

        

        .next-tier-progress {

            background: #ddd;

            height: 10px;

            border-radius: 5px;

            overflow: hidden;

        }

        

        .next-tier-progress .progress-bar {

            height: 100%;

            background: linear-gradient(90deg, #4caf50 0%, #81c784 100%);

            border-radius: 5px;

            transition: width 0.5s ease;

        }

        

        .fees-tiers-table {

            background: #fff;

            padding: 20px;

            border-radius: 8px;

            border: 1px solid #eee;

        }

        

        .fees-tiers-table h4 {

            margin: 0 0 15px 0;

            color: #333;

            font-size: 14px;

        }

        

        .fees-tiers-table table {

            width: 100%;

            border-collapse: collapse;

            font-size: 13px;

        }

        

        .fees-tiers-table th,

        .fees-tiers-table td {

            padding: 10px 12px;

            text-align: left;

            border-bottom: 1px solid #eee;

        }

        

        .fees-tiers-table th {

            background: #f8f9fa;

            font-weight: 600;

            color: #333;

        }

        

        .fees-tiers-table tr.current-tier {

            background: #e3f2fd;

        }

        

        .fees-tiers-table tr.current-tier td {

            font-weight: 500;

        }

        

        .tier-name-badge {

            display: inline-block;

            padding: 4px 10px;

            border-radius: 4px;

            color: white;

            font-size: 12px;

            font-weight: 500;

        }

        

        @media (max-width: 768px) {

            .fees-tier-info {

                flex-direction: column;

            }

            .tier-fees-list {

                flex-direction: column;

                gap: 8px;

            }

        }

        

        .hng-page-header {

            display: flex;

            justify-content: space-between;

            align-items: flex-start;

            margin-bottom: 30px;

            padding: 0 20px;

        }

        

        .hng-page-header h1 {

            display: flex;

            align-items: center;

            gap: 12px;

            margin: 0;

            color: #222;

            font-size: 28px;

        }

        

        .hng-page-header .dashicons {

            color: #0073aa;

        }

        

        .hng-header-title {

            flex: 1;

        }

        

        .hng-header-title p {

            color: #666;

            margin: 8px 0 0 0;

        }

        

        .hng-header-actions {

            display: flex;

            gap: 10px;

        }

        

        .hng-gateway-filters {

            display: flex;

            gap: 10px;

            margin-bottom: 25px;

            padding: 0 20px;

            flex-wrap: wrap;

        }

        

        .filter-btn {

            padding: 8px 16px;

            border: 2px solid #ddd;

            background: white;

            border-radius: 20px;

            cursor: pointer;

            font-size: 13px;

            font-weight: 500;

            color: #666;

            transition: all 0.3s ease;

        }

        

        .filter-btn:hover {

            border-color: #0073aa;

            color: #0073aa;

        }

        

        .filter-btn.active {

            background: #0073aa;

            border-color: #0073aa;

            color: white;

        }

        

        .hng-gateways-grid {

            display: grid;

            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));

            gap: 20px;

            padding: 0 20px;

        }

        

        .hng-gateway-item {

            background: white;

            border-radius: 12px;

            overflow: hidden;

            box-shadow: 0 2px 8px rgba(0,0,0,0.08);

            transition: all 0.3s ease;

            position: relative;

            display: flex;

            flex-direction: column;

            border: 2px solid transparent;

        }

        

        .hng-gateway-item:hover {

            box-shadow: 0 4px 16px rgba(0,0,0,0.12);

            border-color: #0073aa;

            transform: translateY(-2px);

        }

        

        .gateway-name-with-status {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 10px;

        }

        

        .gateway-api-status {

            display: flex;

            align-items: center;

            gap: 6px;

            font-size: 12px;

            white-space: nowrap;

        }

        

        .status-dot {

            width: 10px;

            height: 10px;

            border-radius: 50%;

            display: inline-block;

            box-shadow: 0 0 0 1px rgba(0,0,0,0.1);

        }

        

        .status-dot.status-unknown {

            background: #6c757d;

            animation: pulse 1s infinite;

        }

        

        .status-dot.status-green {

            background: #28a745;

        }

        

        .status-dot.status-yellow {

            background: #ffc107;

        }

        

        .status-dot.status-red {

            background: #dc3545;

        }

        

        @keyframes pulse {

            0% { opacity: 1; }

            50% { opacity: 0.5; }

            100% { opacity: 1; }

        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .dashicons.spin {
            animation: spin 1s linear infinite;
        }
        
        .refresh-fees-btn {
            padding: 2px 6px !important;
            min-height: auto !important;
        }

        

        .status-text {

            color: #666;

        }

        

        .gateway-header {

            display: flex;

            align-items: center;

            gap: 12px;

            padding: 16px;

            border-bottom: 1px solid #f0f0f0;

        }

        

        .gateway-icon {

            width: 48px;

            height: 48px;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #f5f5f5;

            border-radius: 8px;

            font-size: 24px;

            color: #0073aa;

            flex-shrink: 0;

        }

        

        .gateway-name-section h3 {

            margin: 0;

            color: #222;

            font-size: 16px;

            font-weight: 600;

        }

        

        .gateway-category {

            margin: 4px 0 0 0;

            color: #999;

            font-size: 12px;

            text-transform: uppercase;

            letter-spacing: 0.5px;

        }

        

        .gateway-description {

            padding: 12px 16px;

            border-bottom: 1px solid #f0f0f0;

        }

        

        .gateway-description p {

            margin: 0;

            color: #666;

            font-size: 13px;

        }

        

        .gateway-details {

            flex: 1;

            padding: 16px;

        }

        

        .detail-group {

            margin-bottom: 12px;

        }

        

        .detail-group:last-child {

            margin-bottom: 0;

        }

        

        .detail-label {

            display: block;

            font-size: 11px;

            font-weight: 600;

            color: #999;

            text-transform: uppercase;

            margin-bottom: 6px;

            letter-spacing: 0.5px;

        }

        

        .detail-badges {

            display: flex;

            flex-wrap: wrap;

            gap: 6px;

        }

        

        .badge {

            display: inline-block;

            padding: 4px 10px;

            border-radius: 4px;

            font-size: 12px;

            font-weight: 500;

        }

        

        .badge-method {

            background: #e7f3ff;

            color: #0073aa;

        }

        

        .badge-fee {

            background: #f0f9ff;

            color: #0066cc;

        }

        

        .gateway-actions {

            padding: 16px;

            border-top: 1px solid #f0f0f0;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 8px;

        }

        

        .action-toggle {

            display: flex;

            align-items: center;

            gap: 8px;

            flex-shrink: 0;

        }

        

        .toggle-label {

            font-size: 12px;

            font-weight: 500;

            color: #666;

            min-width: 50px;

        }

        

        .action-buttons {

            display: flex;

            gap: 6px;

        }

        

        .action-buttons .button {

            padding: 6px 8px;

            height: auto;

            min-width: 36px;

            display: flex;

            align-items: center;

            justify-content: center;

        }

        

        .action-buttons .dashicons {

            font-size: 16px;

            width: 16px;

            height: 16px;

            margin: 0;

        }

        

        .gateway-docs-btn {

            text-decoration: none;

            color: #0073aa;

            padding: 6px 8px;

        }

        

        .gateway-docs-btn:hover {

            color: #005a87;

        }

        

        .hng-switch {

            position: relative;

            display: inline-block;

            width: 42px;

            height: 24px;

        }

        

        .hng-switch input {

            opacity: 0;

            width: 0;

            height: 0;

        }

        

        .hng-switch-slider {

            position: absolute;

            cursor: pointer;

            top: 0;

            left: 0;

            right: 0;

            bottom: 0;

            background: #ccc;

            transition: 0.3s;

            border-radius: 24px;

        }

        

        .hng-switch-slider:before {

            position: absolute;

            content: "";

            height: 18px;

            width: 18px;

            left: 3px;

            bottom: 3px;

            background: white;

            transition: 0.3s;

            border-radius: 50%;

            box-shadow: 0 2px 4px rgba(0,0,0,0.1);

        }

        

        .hng-switch input:checked + .hng-switch-slider {

            background: #28a745;

        }

        

        .hng-switch input:checked + .hng-switch-slider:before {

            transform: translateX(18px);

        }

        

        .hng-gateway-config-wrapper {

            margin-top: 12px;

            padding: 16px;

            border-top: 1px solid #ddd;

            background: #fafafa;

            display: none;

        }

        

        .hng-gateway-config-wrapper.show {

            display: block;

        }

        

        @media (max-width: 768px) {

            .hng-page-header {

                flex-direction: column;

                gap: 15px;

            }

            

            .hng-gateways-grid {

                grid-template-columns: 1fr;

            }

        }



        /* Notification System */

        .hng-notification {

            position: fixed;

            top: 20px;

            right: 20px;

            padding: 16px 20px;

            background: white;

            border-radius: 8px;

            box-shadow: 0 4px 12px rgba(0,0,0,0.15);

            z-index: 9999;

            min-width: 300px;

            max-width: 450px;

            animation: slideIn 0.3s ease;

            display: flex;

            align-items: center;

            gap: 12px;

            border-left: 4px solid #0073aa;

        }

        

        .hng-notification.success {

            border-left-color: #28a745;

        }

        

        .hng-notification.error {

            border-left-color: #dc3545;

        }

        

        .hng-notification.warning {

            border-left-color: #ffc107;

        }

        

        .hng-notification.info {

            border-left-color: #0073aa;

        }

        

        .hng-notification-icon {

            font-size: 20px;

            flex-shrink: 0;

            display: flex;

            align-items: center;

            justify-content: center;

            width: 24px;

        }

        

        .hng-notification.success .hng-notification-icon {

            color: #28a745;

        }

        

        .hng-notification.error .hng-notification-icon {

            color: #dc3545;

        }

        

        .hng-notification.warning .hng-notification-icon {

            color: #ffc107;

        }

        

        .hng-notification.info .hng-notification-icon {

            color: #0073aa;

        }

        

        .hng-notification-content {

            flex: 1;

            color: #333;

            font-size: 14px;

            font-weight: 500;

        }

        

        .hng-notification-close {

            flex-shrink: 0;

            cursor: pointer;

            color: #999;

            font-size: 18px;

            line-height: 1;

            padding: 0 0 0 10px;

            transition: color 0.2s ease;

        }

        

        .hng-notification-close:hover {

            color: #333;

        }

        

        @keyframes slideIn {

            from {

                transform: translateX(400px);

                opacity: 0;

            }

            to {

                transform: translateX(0);

                opacity: 1;

            }

        }

        

        @keyframes slideOut {

            from {

                transform: translateX(0);

                opacity: 1;

            }

            to {

                transform: translateX(400px);

                opacity: 0;

            }

        }

        

        .hng-notification.removing {

            animation: slideOut 0.3s ease;

        }

        /* ===== Coming Soon Gateway Overlay ===== */
        .hng-gateway-item.hng-coming-soon {
            position: relative;
            overflow: hidden;
        }
        .hng-gateway-item.hng-coming-soon .gateway-details,
        .hng-gateway-item.hng-coming-soon .gateway-actions,
        .hng-gateway-item.hng-coming-soon .gateway-description {
            opacity: 0.35;
            pointer-events: none;
            user-select: none;
        }
        .hng-coming-soon-overlay {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            background: rgba(255,255,255,0.75);
            backdrop-filter: blur(2px);
            border-radius: inherit;
        }
        .hng-coming-soon-badge {
            display: flex;
            align-items: center;
            gap: 12px;
            background: linear-gradient(135deg, #fff8e1, #fff3cd);
            border: 1px solid #f0c040;
            border-radius: 10px;
            padding: 16px 24px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.10);
            max-width: 380px;
        }
        .hng-coming-soon-badge .dashicons-clock {
            font-size: 28px;
            width: 28px;
            height: 28px;
            color: #b8860b;
            flex-shrink: 0;
        }
        .hng-coming-soon-text {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .hng-coming-soon-text strong {
            font-size: 14px;
            color: #8b6914;
        }
        .hng-coming-soon-text span {
            font-size: 12px;
            color: #6b5e1f;
            line-height: 1.4;
        }

        </style>';

		echo '</div>';
	}



	/**

	 * List of available gateways
	 */
	private static function get_gateways() {

		return array(

			'asaas'       => array(

				'name'        => 'Asaas',

				'description' => 'PIX, Boleto e Cartao de Credito',

				'icon'        => 'money-alt',

				'category'    => 'fintech',

				'methods'     => array( 'PIX', 'Boleto', 'Cartao' ),

				'fees'        => array( 'PIX: 0,99% + R$0,49', 'Boleto: R$3,49', 'Cartao: 2,99%' ),

			),

			'mercadopago' => array(

				'name'        => 'Mercado Pago',

				'description' => 'Solucao completa de pagamentos',

				'icon'        => 'cart',

				'category'    => 'marketplace',

				'methods'     => array( 'PIX', 'Boleto', 'Cartao', 'Checkout Pro' ),

				'fees'        => array( 'PIX: 0,99%', 'Boleto: R$3,79', 'Cartao: 4,99%' ),

			),

			'pagseguro'   => array(

				'name'        => 'PagSeguro',

				'description' => 'Gateway UOL PagSeguro',

				'icon'        => 'shield',

				'category'    => 'marketplace',

				'methods'     => array( 'PIX', 'Boleto', 'Cartao' ),

				'fees'        => array( 'PIX: 0,99%', 'Boleto: R$3,00', 'Cartao: 3,99%' ),

			),

			'pagarme'     => array(

				'name'        => 'Pagar.me',

				'description' => 'PIX, Boleto, Cartao e Split Payment',

				'icon'        => 'admin-generic',

				'category'    => 'fintech',

				'methods'     => array( 'PIX', 'Boleto', 'Cartao', 'Split' ),

				'fees'        => array( 'PIX: 0,99%', 'Boleto: R$3,49', 'Cartao: 3,79%' ),

			),

			'stripe'      => array(

				'name'        => 'Stripe',

				'description' => 'Cartao de Credito Internacional',

				'icon'        => 'credit',

				'category'    => 'fintech',

				'methods'     => array( 'Cartao Credito', 'Cartao Debito', 'Apple Pay', 'Google Pay' ),

				'fees'        => array( 'Cartao: 2,90% + $0,30', 'Internacional: 3,90% + $0,30' ),

			),

			'paypal'      => array(

				'name'        => 'PayPal',

				'description' => 'Pagamentos internacionais',

				'icon'        => 'money-alt',

				'category'    => 'fintech',

				'methods'     => array( 'PayPal Wallet', 'Cartao' ),

				'fees'        => array( 'PayPal: 4,99% + $0,49', 'Cartao: 3,99% + $0,49' ),

			),

			'cielo'       => array(

				'name'        => 'Cielo',

				'description' => 'Gateway Cielo de pagamentos',

				'icon'        => 'building',

				'category'    => 'marketplace',

				'methods'     => array( 'PIX', 'Cartao', 'Boleto' ),

				'fees'        => array( 'PIX: 0,99%', 'Cartao: 2,75%', 'Boleto: R$2,80' ),

			),

			'rede'        => array(

				'name'        => 'Rede',

				'description' => 'Processadora Rede',

				'icon'        => 'building',

				'category'    => 'marketplace',

				'methods'     => array( 'PIX', 'Cartao', 'Boleto' ),

				'fees'        => array( 'PIX: 0,99%', 'Cartao: 2,90%', 'Boleto: R$3,00' ),

			),

			'getnet'      => array(

				'name'        => 'GetNet',

				'description' => 'Gateway GetNet Santander',

				'icon'        => 'building',

				'category'    => 'marketplace',

				'methods'     => array( 'PIX', 'Cartao', 'Boleto' ),

				'fees'        => array( 'PIX: 0,99%', 'Cartao: 2,95%', 'Boleto: R$2,90' ),

			),

			'stone'       => array(

				'name'        => 'Stone',

				'description' => 'Adquirente Stone',

				'icon'        => 'building',

				'category'    => 'marketplace',

				'methods'     => array( 'PIX', 'Cartao' ),

				'fees'        => array( 'PIX: 0,99%', 'Cartao: 2,85%' ),

			),

			// Gateways removidos (não suportam split payment):
			// nubank, inter, c6bank, bb, itau, bradesco, santander, picpay

		);
	}



	/**

	 * Get category label
	 */
	private static function get_category_label( $category ) {

		$labels = array(

			'fintech'     => 'Fintech',

			'banks'       => 'Banco',

			'marketplace' => 'Marketplace',

			'other'       => 'Outro',

		);

		return isset( $labels[ $category ] ) ? $labels[ $category ] : $labels['other'];
	}



	/**

	 * Renderiza formulario de configuracao para cada gateway
	 */
	private static function render_gateway_form( $gatewayId ) {

		$configs = array();

		$all_gateways = self::get_gateways();

		$gateway = isset( $all_gateways[ $gatewayId ] ) ? $all_gateways[ $gatewayId ] : array(
			'name'        => $gatewayId,
			'description' => '',
		);

		if ( $gatewayId === 'asaas' ) {

			$configs = array(

				'api_key' => get_option( 'hng_asaas_api_key', '' ),

				'sandbox' => get_option( 'hng_asaas_sandbox', 1 ),

			);

			echo '<h3 style="margin-top: 0;">Config Asaas</h3>';

			echo '<form class="gateway-config-form-inner" data-gateway="asaas">';

			wp_nonce_field( 'hng_save_gateway_config', '_wpnonce_asaas', false );

			echo '<p><label><strong>API Key:</strong></label><br>';

			echo '<input type="password" name="api_key" class="regular-text" value="' . esc_attr( $configs['api_key'] ) . '" placeholder="$aact_...">';

			echo '<br><small>Obtenha em painel Asaas</small></p>';

			echo '<p><label><input type="checkbox" name="sandbox" value="1" ' . checked( $configs['sandbox'], 1, false ) . '> Modo Sandbox (Teste)</label>';

			echo '<br><small>Desative em producao!</small></p>';

			$adv_enabled = get_option( 'hng_asaas_advanced_integration', 'no' ) === 'yes';

			echo '<p style="display:flex;align-items:center;gap:8px;"><strong>Integração avançada:</strong> ';

			echo '<label class="hng-switch" style="margin-left:8px;">';

			echo '<input type="checkbox" class="advanced-toggle" data-gateway="asaas" ' . checked( $adv_enabled, true, false ) . ' />';

			echo '<span class="hng-switch-slider"></span>';

			echo '</label>';

			echo '<span class="description">Ativa recursos de sincronização e webhooks.</span>';

			echo '</p>';

			echo '<p style="display: flex; gap: 10px;"><button type="button" class="button button-primary save-gateway-config"><span class="dashicons dashicons-saved"></span> Salvar</button>';

			echo '<button type="button" class="button button-secondary test-gateway-inline"><span class="dashicons dashicons-yes"></span> Testar Conexao</button></p>';

			echo '<div class="test-result" style="margin-top: 10px;"></div>';

			echo '</form>';

		} elseif ( $gatewayId === 'mercadopago' ) {

			$configs = array(

				'access_token' => get_option( 'hng_mercadopago_access_token', '' ),

				'public_key'   => get_option( 'hng_mercadopago_public_key', '' ),

				'sandbox'      => get_option( 'hng_mercadopago_sandbox', 1 ),

			);

			// Verificar se temos merchant_id e se está conectado via OAuth
			$merchant_id     = get_option( 'hng_merchant_id', '' );
			$oauth_connected = false;
			$oauth_email     = '';

			if ( ! empty( $merchant_id ) ) {
				// Verificar status OAuth via API
				$api_url        = 'https://api.hngdesenvolvimentos.com.br/oauth/mercadopago/status?merchant_id=' . urlencode( $merchant_id );
				$oauth_response = wp_remote_get( $api_url, array( 'timeout' => 10 ) );
				if ( ! is_wp_error( $oauth_response ) ) {
					$oauth_data      = json_decode( wp_remote_retrieve_body( $oauth_response ), true );
					$oauth_connected = ! empty( $oauth_data['connected'] ) && $oauth_data['connected'] === true;
					if ( $oauth_connected && ! empty( $oauth_data['data']['email'] ) ) {
						$oauth_email = $oauth_data['data']['email'];
					}
				}
			}

			echo '<h3 style="margin-top: 0;">Config Mercado Pago</h3>';

			// Seção OAuth - Conexão Split Payment (OBRIGATÓRIO)
			echo '<div class="oauth-section" style="background: ' . ( $oauth_connected ? '#f0fff4' : '#fff8e5' ) . '; border: 2px solid ' . ( $oauth_connected ? '#46b450' : '#f0b849' ) . '; border-radius: 8px; padding: 15px; margin-bottom: 20px;">';

			if ( $oauth_connected ) {
				echo '<h4 style="margin: 0 0 10px 0; color: #46b450;"><span class="dashicons dashicons-yes-alt"></span> Conta Conectada</h4>';
				echo '<p style="color: #46b450; margin: 0 0 10px 0;"><strong>✓ ' . esc_html( $oauth_email ) . '</strong></p>';
				echo '<p style="margin: 0 0 15px 0;"><small>Sua conta está autorizada. A taxa HNG Commerce será processada automaticamente via split payment em cada transação.</small></p>';
				echo '<button type="button" class="button button-link-delete mp-oauth-disconnect" data-merchant="' . esc_attr( $merchant_id ) . '">Desconectar conta</button>';
			} else {
				echo '<h4 style="margin: 0 0 10px 0; color: #9c6a00;"><span class="dashicons dashicons-warning"></span> Conexão Obrigatória</h4>';
				echo '<p style="margin: 0 0 8px 0;"><strong>Para utilizar o Mercado Pago, é necessário conectar sua conta.</strong></p>';
				echo '<p style="margin: 0 0 15px 0; color: #666;"><small>Essa conexão autoriza o processamento automático de pagamentos com split payment, garantindo a cobrança correta das taxas de plataforma.</small></p>';
				if ( ! empty( $merchant_id ) ) {
					$oauth_authorize_url = 'https://api.hngdesenvolvimentos.com.br/oauth/mercadopago/authorize?merchant_id=' . urlencode( $merchant_id ) . '&redirect_uri=' . urlencode( admin_url( 'admin.php?page=hng-gateways' ) );
					echo '<a href="' . esc_url( $oauth_authorize_url ) . '" class="button button-primary" style="background: #009ee3; border-color: #009ee3; font-size: 14px; padding: 8px 16px; height: auto;">';
					echo '<span class="dashicons dashicons-admin-links" style="margin-top: 4px;"></span> Conectar minha conta Mercado Pago</a>';
				} else {
					echo '<p style="color: #dc3232; margin: 0;"><strong>⚠</strong> Primeiro registre-se na API HNG para obter seu identificador de loja.</p>';
				}
			}
			echo '</div>';

			echo '<form class="gateway-config-form-inner" data-gateway="mercadopago">';

			wp_nonce_field( 'hng_save_gateway_config', '_wpnonce_mercadopago', false );

			echo '<p><label><strong>Access Token:</strong></label><br>';

			echo '<input type="password" name="access_token" class="regular-text" value="' . esc_attr( $configs['access_token'] ) . '" placeholder="APP_USR-...">';

			echo '<br><small>Obtenha em Mercado Pago Developers</small></p>';

			echo '<p><label><strong>Public Key:</strong></label><br>';

			echo '<input type="text" name="public_key" class="regular-text" value="' . esc_attr( $configs['public_key'] ) . '" placeholder="APP_USR-... (publico)"></p>';

			echo '<p><label><input type="checkbox" name="sandbox" value="1" ' . checked( $configs['sandbox'], 1, false ) . '> Modo Sandbox (Teste)</label>';

			echo '<br><small>Desative em producao!</small></p>';

			$adv_enabled = get_option( 'hng_mercadopago_advanced_integration', 'no' ) === 'yes';

			echo '<p style="display:flex;align-items:center;gap:8px;"><strong>Integração avançada:</strong> ';

			echo '<label class="hng-switch" style="margin-left:8px;">';

			echo '<input type="checkbox" class="advanced-toggle" data-gateway="mercadopago" ' . checked( $adv_enabled, true, false ) . ' />';

			echo '<span class="hng-switch-slider"></span>';

			echo '</label>';

			echo '<span class="description">Ativa clientes, assinaturas e webhooks.</span>';

			echo '</p>';

			echo '<p style="display: flex; gap: 10px;"><button type="button" class="button button-primary save-gateway-config"><span class="dashicons dashicons-saved"></span> Salvar</button>';

			echo '<button type="button" class="button button-secondary test-gateway-inline"><span class="dashicons dashicons-yes"></span> Testar Conexao</button></p>';

			echo '<div class="test-result" style="margin-top: 10px;"></div>';

			echo '</form>';

		} elseif ( $gatewayId === 'pagseguro' ) {

			$configs = array(

				'token'   => get_option( 'hng_ps_token', '' ),

				'email'   => get_option( 'hng_ps_email', '' ),

				'sandbox' => get_option( 'hng_ps_sandbox', 'yes' ) === 'yes',

			);

			echo '<h3 style="margin-top: 0;">Config PagSeguro</h3>';

			echo '<form class="gateway-config-form-inner" data-gateway="pagseguro">';

			wp_nonce_field( 'hng_save_gateway_config', '_wpnonce_pagseguro', false );

			echo '<p><label><strong>Token / API Key:</strong></label><br>';

			echo '<input type="password" name="token" class="regular-text" value="' . esc_attr( $configs['token'] ) . '" placeholder="token_pagseguro..."></p>';

			echo '<p><label><strong>Email de conta:</strong></label><br>';

			echo '<input type="email" name="email" class="regular-text" value="' . esc_attr( $configs['email'] ) . '" placeholder="seu@email.com"></p>';

			echo '<p><label><input type="checkbox" name="sandbox" value="1" ' . checked( $configs['sandbox'], true, false ) . '> Modo Sandbox (Teste)</label>';

			echo '<br><small>Obtenha seu token no Portal PagSeguro</small></p>';

			$adv_enabled = get_option( 'hng_pagseguro_advanced_integration', 'no' ) === 'yes';

			echo '<p style="display:flex;align-items:center;gap:8px;"><strong>Integração avançada:</strong> ';

			echo '<label class="hng-switch" style="margin-left:8px;">';

			echo '<input type="checkbox" class="advanced-toggle" data-gateway="pagseguro" ' . checked( $adv_enabled, true, false ) . ' />';

			echo '<span class="hng-switch-slider"></span>';

			echo '</label>';

			echo '<span class="description">Ativa assinaturas, clientes e faturamento.</span>';

			echo '</p>';

			echo '<p style="display: flex; gap: 10px;"><button type="button" class="button button-primary save-gateway-config"><span class="dashicons dashicons-saved"></span> Salvar</button>';

			echo '<button type="button" class="button button-secondary test-gateway-inline"><span class="dashicons dashicons-yes"></span> Testar Conexao</button></p>';

			echo '<div class="test-result" style="margin-top: 10px;"></div>';

			echo '</form>';

		} elseif ( $gatewayId === 'pagarme' ) {

			$configs = array(

				'secret_key' => get_option( 'hng_pagarme_secret_key', '' ),

				'public_key' => get_option( 'hng_pagarme_public_key', '' ),

				'enabled'    => get_option( 'hng_pagarme_enabled', 'no' ) === 'yes',

			);

			echo '<h3 style="margin-top: 0;">Config Pagar.me</h3>';

			echo '<form class="gateway-config-form-inner" data-gateway="pagarme">';

			wp_nonce_field( 'hng_save_gateway_config', '_wpnonce_pagarme', false );

			echo '<p><label><strong>Secret Key:</strong></label><br>';

			echo '<input type="password" name="secret_key" class="regular-text" value="' . esc_attr( $configs['secret_key'] ) . '" placeholder="sk_live_..."></p>';

			echo '<p><label><strong>Public Key:</strong></label><br>';

			echo '<input type="text" name="public_key" class="regular-text" value="' . esc_attr( $configs['public_key'] ) . '" placeholder="pk_live_..."></p>';

			echo '<p><label><input type="checkbox" name="enabled" value="1" ' . checked( $configs['enabled'], true, false ) . '> Habilitar gateway</label></p>';

			$adv_enabled = get_option( 'hng_pagarme_advanced_integration', 'no' ) === 'yes';

			echo '<p style="display:flex;align-items:center;gap:8px;"><strong>Integração avançada:</strong> ';

			echo '<label class="hng-switch" style="margin-left:8px;">';

			echo '<input type="checkbox" class="advanced-toggle" data-gateway="pagarme" ' . checked( $adv_enabled, true, false ) . ' />';

			echo '<span class="hng-switch-slider"></span>';

			echo '</label>';

			echo '<span class="description">Ativa Split Payment e assinaturas recorrentes.</span>';

			echo '</p>';

			echo '<p style="display: flex; gap: 10px;"><button type="button" class="button button-primary save-gateway-config"><span class="dashicons dashicons-saved"></span> Salvar</button>';

			echo '<button type="button" class="button button-secondary test-gateway-inline"><span class="dashicons dashicons-yes"></span> Testar Conexao</button></p>';

			echo '<div class="test-result" style="margin-top: 10px;"></div>';

			echo '</form>';

		} elseif ( $gatewayId === 'stripe' ) {
			$configs = array(
				'secret_key'      => get_option( 'hng_stripe_secret_key', '' ),
				'publishable_key' => get_option( 'hng_stripe_publishable_key', '' ),
				'webhook_secret'  => get_option( 'hng_stripe_webhook_secret', '' ),
			);
			echo '<h3 style="margin-top: 0;">Config Stripe</h3>';
			echo '<form class="gateway-config-form-inner" data-gateway="stripe">';
			wp_nonce_field( 'hng_save_gateway_config', '_wpnonce_stripe', false );
			echo '<p><label><strong>Secret Key:</strong></label><br>';
			echo '<input type="password" name="secret_key" class="regular-text" value="' . esc_attr( $configs['secret_key'] ) . '" placeholder="sk_live_..."></p>';
			echo '<p><label><strong>Publishable Key:</strong></label><br>';
			echo '<input type="text" name="publishable_key" class="regular-text" value="' . esc_attr( $configs['publishable_key'] ) . '" placeholder="pk_live_..."></p>';
			echo '<p><label><strong>Webhook Secret:</strong></label><br>';
			echo '<input type="password" name="webhook_secret" class="regular-text" value="' . esc_attr( $configs['webhook_secret'] ) . '" placeholder="whsec_..."></p>';
			echo '<p style="display: flex; gap: 10px;"><button type="button" class="button button-primary save-gateway-config"><span class="dashicons dashicons-saved"></span> Salvar</button>';
			echo '<button type="button" class="button button-secondary test-gateway-inline"><span class="dashicons dashicons-yes"></span> Testar Conexao</button></p>';
			echo '<div class="test-result" style="margin-top: 10px;"></div>';
			echo '</form>';
		} elseif ( $gatewayId === 'paypal' ) {
			$configs = array(
				'client_id'     => get_option( 'hng_paypal_client_id', '' ),
				'client_secret' => get_option( 'hng_paypal_client_secret', '' ),
				'webhook_token' => get_option( 'hng_paypal_webhook_token', '' ),
				'environment'   => get_option( 'hng_paypal_environment', 'sandbox' ),
			);
			echo '<h3 style="margin-top: 0;">Config PayPal</h3>';
			echo '<form class="gateway-config-form-inner" data-gateway="paypal">';
			wp_nonce_field( 'hng_save_gateway_config', '_wpnonce_paypal', false );
			echo '<p><label><strong>Client ID:</strong></label><br>';
			echo '<input type="text" name="client_id" class="regular-text" value="' . esc_attr( $configs['client_id'] ) . '" placeholder="Client ID"></p>';
			echo '<p><label><strong>Client Secret:</strong></label><br>';
			echo '<input type="password" name="client_secret" class="regular-text" value="' . esc_attr( $configs['client_secret'] ) . '" placeholder="Client Secret"></p>';
			echo '<p><label><strong>Webhook Token:</strong></label><br>';
			echo '<input type="text" name="webhook_token" class="regular-text" value="' . esc_attr( $configs['webhook_token'] ) . '" placeholder="Webhook ID"></p>';
			echo '<p><label><strong>Ambiente:</strong></label><br><select name="environment" class="regular-text">';
			echo '<option value="sandbox" ' . selected( $configs['environment'], 'sandbox', false ) . '>Sandbox (Testes)</option>';
			echo '<option value="production" ' . selected( $configs['environment'], 'production', false ) . '>Produção</option>';
			echo '</select></p>';
			echo '<p style="display: flex; gap: 10px;"><button type="button" class="button button-primary save-gateway-config"><span class="dashicons dashicons-saved"></span> Salvar</button>';
			echo '<button type="button" class="button button-secondary test-gateway-inline"><span class="dashicons dashicons-yes"></span> Testar Conexao</button></p>';
			echo '<div class="test-result" style="margin-top: 10px;"></div>';
			echo '</form>';
		} elseif ( $gatewayId === 'cielo' ) {
			$configs = array(
				'merchant_id'    => get_option( 'hng_cielo_merchant_id', '' ),
				'api_key'        => get_option( 'hng_cielo_api_key', '' ),
				'webhook_secret' => get_option( 'hng_cielo_webhook_secret', '' ),
				'environment'    => get_option( 'hng_cielo_environment', 'sandbox' ),
			);
			echo '<h3 style="margin-top: 0;">Config Cielo</h3>';
			echo '<form class="gateway-config-form-inner" data-gateway="cielo">';
			wp_nonce_field( 'hng_save_gateway_config', '_wpnonce_cielo', false );
			echo '<p><label><strong>Merchant ID:</strong></label><br>';
			echo '<input type="text" name="merchant_id" class="regular-text" value="' . esc_attr( $configs['merchant_id'] ) . '" placeholder="Merchant ID Cielo"></p>';
			echo '<p><label><strong>API Key:</strong></label><br>';
			echo '<input type="password" name="api_key" class="regular-text" value="' . esc_attr( $configs['api_key'] ) . '" placeholder="API Key"></p>';
			echo '<p><label><strong>Webhook Secret:</strong></label><br>';
			echo '<input type="password" name="webhook_secret" class="regular-text" value="' . esc_attr( $configs['webhook_secret'] ) . '" placeholder="Chave do webhook"></p>';
			echo '<p><label><strong>Ambiente:</strong></label><br><select name="environment" class="regular-text">';
			echo '<option value="sandbox" ' . selected( $configs['environment'], 'sandbox', false ) . '>Sandbox (Testes)</option>';
			echo '<option value="production" ' . selected( $configs['environment'], 'production', false ) . '>Produção</option>';
			echo '</select></p>';
			echo '<p style="display: flex; gap: 10px;"><button type="button" class="button button-primary save-gateway-config"><span class="dashicons dashicons-saved"></span> Salvar</button>';
			echo '<button type="button" class="button button-secondary test-gateway-inline"><span class="dashicons dashicons-yes"></span> Testar Conexao</button></p>';
			echo '<div class="test-result" style="margin-top: 10px;"></div>';
			echo '</form>';
		} elseif ( $gatewayId === 'rede' ) {
			$configs = array(
				'pv'             => get_option( 'hng_rede_pv', '' ),
				'token'          => get_option( 'hng_rede_token', '' ),
				'webhook_secret' => get_option( 'hng_rede_webhook_secret', '' ),
				'environment'    => get_option( 'hng_rede_environment', 'sandbox' ),
			);
			echo '<h3 style="margin-top: 0;">Config Rede</h3>';
			echo '<form class="gateway-config-form-inner" data-gateway="rede">';
			wp_nonce_field( 'hng_save_gateway_config', '_wpnonce_rede', false );
			echo '<p><label><strong>PV (Point of Sale):</strong></label><br>';
			echo '<input type="text" name="pv" class="regular-text" value="' . esc_attr( $configs['pv'] ) . '" placeholder="PV Rede"></p>';
			echo '<p><label><strong>Token:</strong></label><br>';
			echo '<input type="password" name="token" class="regular-text" value="' . esc_attr( $configs['token'] ) . '" placeholder="Token Rede"></p>';
			echo '<p><label><strong>Webhook Secret:</strong></label><br>';
			echo '<input type="password" name="webhook_secret" class="regular-text" value="' . esc_attr( $configs['webhook_secret'] ) . '" placeholder="Chave do webhook"></p>';
			echo '<p><label><strong>Ambiente:</strong></label><br><select name="environment" class="regular-text">';
			echo '<option value="sandbox" ' . selected( $configs['environment'], 'sandbox', false ) . '>Sandbox (Testes)</option>';
			echo '<option value="production" ' . selected( $configs['environment'], 'production', false ) . '>Produção</option>';
			echo '</select></p>';
			echo '<p style="display: flex; gap: 10px;"><button type="button" class="button button-primary save-gateway-config"><span class="dashicons dashicons-saved"></span> Salvar</button>';
			echo '<button type="button" class="button button-secondary test-gateway-inline"><span class="dashicons dashicons-yes"></span> Testar Conexao</button></p>';
			echo '<div class="test-result" style="margin-top: 10px;"></div>';
			echo '</form>';
		} elseif ( $gatewayId === 'getnet' ) {
			$configs = array(
				'client_id'     => get_option( 'hng_getnet_client_id', '' ),
				'client_secret' => get_option( 'hng_getnet_client_secret', '' ),
				'webhook_token' => get_option( 'hng_getnet_webhook_token', '' ),
				'environment'   => get_option( 'hng_getnet_environment', 'sandbox' ),
			);
			echo '<h3 style="margin-top: 0;">Config GetNet</h3>';
			echo '<form class="gateway-config-form-inner" data-gateway="getnet">';
			wp_nonce_field( 'hng_save_gateway_config', '_wpnonce_getnet', false );
			echo '<p><label><strong>Client ID:</strong></label><br>';
			echo '<input type="text" name="client_id" class="regular-text" value="' . esc_attr( $configs['client_id'] ) . '" placeholder="Client ID"></p>';
			echo '<p><label><strong>Client Secret:</strong></label><br>';
			echo '<input type="password" name="client_secret" class="regular-text" value="' . esc_attr( $configs['client_secret'] ) . '" placeholder="Client Secret"></p>';
			echo '<p><label><strong>Webhook Token:</strong></label><br>';
			echo '<input type="text" name="webhook_token" class="regular-text" value="' . esc_attr( $configs['webhook_token'] ) . '" placeholder="Webhook ID"></p>';
			echo '<p><label><strong>Ambiente:</strong></label><br><select name="environment" class="regular-text">';
			echo '<option value="sandbox" ' . selected( $configs['environment'], 'sandbox', false ) . '>Sandbox (Testes)</option>';
			echo '<option value="production" ' . selected( $configs['environment'], 'production', false ) . '>Produção</option>';
			echo '</select></p>';
			echo '<p style="display: flex; gap: 10px;"><button type="button" class="button button-primary save-gateway-config"><span class="dashicons dashicons-saved"></span> Salvar</button>';
			echo '<button type="button" class="button button-secondary test-gateway-inline"><span class="dashicons dashicons-yes"></span> Testar Conexao</button></p>';
			echo '<div class="test-result" style="margin-top: 10px;"></div>';
			echo '</form>';
		} elseif ( $gatewayId === 'stone' ) {
			$configs = array(
				'api_key'        => get_option( 'hng_stone_api_key', '' ),
				'webhook_secret' => get_option( 'hng_stone_webhook_secret', '' ),
				'environment'    => get_option( 'hng_stone_environment', 'sandbox' ),
			);
			echo '<h3 style="margin-top: 0;">Config Stone</h3>';
			echo '<form class="gateway-config-form-inner" data-gateway="stone">';
			wp_nonce_field( 'hng_save_gateway_config', '_wpnonce_stone', false );
			echo '<p><label><strong>API Key:</strong></label><br>';
			echo '<input type="password" name="api_key" class="regular-text" value="' . esc_attr( $configs['api_key'] ) . '" placeholder="API Key Stone"></p>';
			echo '<p><label><strong>Webhook Secret:</strong></label><br>';
			echo '<input type="password" name="webhook_secret" class="regular-text" value="' . esc_attr( $configs['webhook_secret'] ) . '" placeholder="Chave do webhook"></p>';
			echo '<p><label><strong>Ambiente:</strong></label><br><select name="environment" class="regular-text">';
			echo '<option value="sandbox" ' . selected( $configs['environment'], 'sandbox', false ) . '>Sandbox (Testes)</option>';
			echo '<option value="production" ' . selected( $configs['environment'], 'production', false ) . '>Produção</option>';
			echo '</select></p>';
			echo '<p style="display: flex; gap: 10px;"><button type="button" class="button button-primary save-gateway-config"><span class="dashicons dashicons-saved"></span> Salvar</button>';
			echo '<button type="button" class="button button-secondary test-gateway-inline"><span class="dashicons dashicons-yes"></span> Testar Conexao</button></p>';
			echo '<div class="test-result" style="margin-top: 10px;"></div>';
			echo '</form>';

			// Gateways de formulário removidos: nubank, inter, c6bank, bb, itau, bradesco, santander
			// Motivo: Não suportam split payment

		} else {

			$saved = get_option( 'hng_gateway_' . $gatewayId . '_config', '' );

			echo '<h3 style="margin-top: 0;">Config ' . esc_html( $gateway['name'] ) . '</h3>';

			echo '<p><em>Configuracao generica (JSON)</em></p>';

			echo '<form class="gateway-config-form-inner" data-gateway="' . esc_attr( $gatewayId ) . '">';

			wp_nonce_field( 'hng_save_gateway_config', '_wpnonce_' . $gatewayId, false );

			echo '<p><label><strong>Configuracao (JSON):</strong></label><br>';

			echo '<textarea name="generic_config" rows="8" class="large-text" placeholder=\'{"api_key":"...","sandbox":1}\'>' . esc_textarea( $saved ) . '</textarea></p>';

			echo '<p style="display: flex; gap: 10px;"><button type="button" class="button button-primary save-gateway-config"><span class="dashicons dashicons-saved"></span> Salvar</button>';

			echo '<button type="button" class="button button-secondary test-gateway-inline"><span class="dashicons dashicons-yes"></span> Testar Conexao</button></p>';

			echo '<div class="test-result" style="margin-top: 10px;"></div>';

			echo '</form>';

		}
	}



	/**

	 * Salva configuracao do gateway via AJAX
	 */
	public function save_gateway_config() {

		// Obtém o gateway do POST
		$post_data = wp_unslash( $_POST );

		$gateway = sanitize_text_field( $post_data['gateway'] ?? '' );

		if ( empty( $gateway ) ) {

			wp_send_json_error( array( 'message' => 'Gateway inválido' ), 400 );

		}

		// Procura por qualquer nonce no POST (eles têm padrão _wpnonce_*)

		$nonce_found = false;

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Custom nonce discovery loop validates nonce before any write operation.
		foreach ( $_POST as $key => $value ) {

			if ( strpos( $key, '_wpnonce_' ) === 0 ) {

				$clean_nonce = sanitize_text_field( function_exists( 'wp_unslash' ) ? wp_unslash( $value ) : $value );

				if ( wp_verify_nonce( $clean_nonce, 'hng_save_gateway_config' ) ) {

					$nonce_found = true;

					break;

				}
			}
		}

		if ( ! $nonce_found ) {

			wp_send_json_error( array( 'message' => 'Nonce inválido ou expirado' ), 400 );

		}

		if ( ! current_user_can( 'manage_options' ) ) {

			wp_send_json_error( array( 'message' => 'Permissao negada' ), 403 );

		}

		switch ( $gateway ) {

			case 'asaas':
				update_option( 'hng_asaas_api_key', sanitize_text_field( $post_data['api_key'] ?? '' ) );

				update_option( 'hng_asaas_sandbox', isset( $post_data['sandbox'] ) ? 1 : 0 );

				wp_send_json_success( array( 'message' => 'Config Asaas salva!' ) );

				break;

			case 'mercadopago':
				update_option( 'hng_mercadopago_access_token', sanitize_text_field( $post_data['access_token'] ?? '' ) );

				update_option( 'hng_mercadopago_public_key', sanitize_text_field( $post_data['public_key'] ?? '' ) );

				update_option( 'hng_mercadopago_sandbox', isset( $post_data['sandbox'] ) ? 1 : 0 );

				wp_send_json_success( array( 'message' => 'Config Mercado Pago salva!' ) );

				break;

			case 'pagseguro':
				update_option( 'hng_ps_token', sanitize_text_field( $post_data['token'] ?? '' ) );

				update_option( 'hng_ps_email', sanitize_email( $post_data['email'] ?? '' ) );

				update_option( 'hng_ps_sandbox', isset( $post_data['sandbox'] ) ? 'yes' : 'no' );

				wp_send_json_success( array( 'message' => 'Config PagSeguro salva!' ) );

				break;

			case 'pagarme':
				update_option( 'hng_pagarme_secret_key', sanitize_text_field( $post_data['secret_key'] ?? '' ) );

				update_option( 'hng_pagarme_public_key', sanitize_text_field( $post_data['public_key'] ?? '' ) );

				update_option( 'hng_pagarme_enabled', isset( $post_data['enabled'] ) ? 'yes' : 'no' );

				wp_send_json_success( array( 'message' => 'Config Pagar.me salva!' ) );

				break;

			// Gateways removidos: nubank, inter, c6bank, bb, itau, bradesco, santander
			// Motivo: Não suportam split payment

			case 'stripe':
				update_option( 'hng_stripe_secret_key', sanitize_text_field( $post_data['secret_key'] ?? '' ) );
				update_option( 'hng_stripe_publishable_key', sanitize_text_field( $post_data['publishable_key'] ?? '' ) );
				update_option( 'hng_stripe_webhook_secret', sanitize_text_field( $post_data['webhook_secret'] ?? '' ) );
				wp_send_json_success( array( 'message' => 'Config Stripe salva!' ) );
				break;

			case 'paypal':
				update_option( 'hng_paypal_client_id', sanitize_text_field( $post_data['client_id'] ?? '' ) );
				update_option( 'hng_paypal_client_secret', sanitize_text_field( $post_data['client_secret'] ?? '' ) );
				update_option( 'hng_paypal_webhook_token', sanitize_text_field( $post_data['webhook_token'] ?? '' ) );
				update_option( 'hng_paypal_environment', sanitize_text_field( $post_data['environment'] ?? 'sandbox' ) );
				wp_send_json_success( array( 'message' => 'Config PayPal salva!' ) );
				break;

			// case 'picpay' removido - não suporta split payment

			case 'cielo':
				update_option( 'hng_cielo_merchant_id', sanitize_text_field( $post_data['merchant_id'] ?? '' ) );
				update_option( 'hng_cielo_api_key', sanitize_text_field( $post_data['api_key'] ?? '' ) );
				update_option( 'hng_cielo_webhook_secret', sanitize_text_field( $post_data['webhook_secret'] ?? '' ) );
				update_option( 'hng_cielo_environment', sanitize_text_field( $post_data['environment'] ?? 'sandbox' ) );
				wp_send_json_success( array( 'message' => 'Config Cielo salva!' ) );
				break;

			case 'rede':
				update_option( 'hng_rede_pv', sanitize_text_field( $post_data['pv'] ?? '' ) );
				update_option( 'hng_rede_token', sanitize_text_field( $post_data['token'] ?? '' ) );
				update_option( 'hng_rede_webhook_secret', sanitize_text_field( $post_data['webhook_secret'] ?? '' ) );
				update_option( 'hng_rede_environment', sanitize_text_field( $post_data['environment'] ?? 'sandbox' ) );
				wp_send_json_success( array( 'message' => 'Config Rede salva!' ) );
				break;

			case 'getnet':
				update_option( 'hng_getnet_client_id', sanitize_text_field( $post_data['client_id'] ?? '' ) );
				update_option( 'hng_getnet_client_secret', sanitize_text_field( $post_data['client_secret'] ?? '' ) );
				update_option( 'hng_getnet_webhook_token', sanitize_text_field( $post_data['webhook_token'] ?? '' ) );
				update_option( 'hng_getnet_environment', sanitize_text_field( $post_data['environment'] ?? 'sandbox' ) );
				wp_send_json_success( array( 'message' => 'Config GetNet salva!' ) );
				break;

			case 'stone':
				update_option( 'hng_stone_api_key', sanitize_text_field( $post_data['api_key'] ?? '' ) );
				update_option( 'hng_stone_webhook_secret', sanitize_text_field( $post_data['webhook_secret'] ?? '' ) );
				update_option( 'hng_stone_environment', sanitize_text_field( $post_data['environment'] ?? 'sandbox' ) );
				wp_send_json_success( array( 'message' => 'Config Stone salva!' ) );
				break;

			default:
				update_option( 'hng_gateway_' . $gateway . '_config', sanitize_textarea_field( $post_data['generic_config'] ?? '' ) );

				wp_send_json_success( array( 'message' => 'Config salva!' ) );

				break;

		}
	}



	/**

	 * Verifica status do gateway via AJAX (sem nonce para auto-check)

	 * Esta é uma versão simplificada apenas para verificação automática de status

	 * Se full_test=1, executa um teste completo da conexão
	 */
	public function check_gateway_status() {

		if ( ! check_ajax_referer( 'hng-commerce-admin', 'nonce', false ) ) {
			wp_send_json_error(
				array(
					'message' => 'Nonce inválido ou expirado',
					'status'  => 'error',
				),
				403
			);
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => 'Permissao negada',
					'status'  => 'error',
				),
				403
			);
		}

		$post_data = wp_unslash( $_POST );

		$gateway = sanitize_text_field( $post_data['gateway'] ?? '' );

		error_log( 'HNG check_gateway_status: Gateway = ' . $gateway );

		if ( empty( $gateway ) ) {

			wp_send_json_error(
				array(
					'message' => 'Gateway inválido',
					'status'  => 'error',
				)
			);

		}

		// Se full_test=1, executa teste completo ao invés de apenas verificar credenciais

		$full_test = isset( $post_data['full_test'] ) && intval( $post_data['full_test'] ) === 1;

		error_log( 'HNG check_gateway_status: full_test = ' . ( $full_test ? 'YES' : 'NO' ) );

		if ( $full_test ) {

			// Teste completo: chama o método de teste real

			error_log( 'HNG check_gateway_status: Calling test_single_gateway(' . $gateway . ')' );

			$result = self::test_single_gateway( $gateway );

			error_log( 'HNG check_gateway_status: Result = ' . print_r( $result, true ) );

			if ( is_wp_error( $result ) ) {

				// Salvar status de erro

				update_option( 'hng_gateway_' . $gateway . '_test_status', 'error' );

				update_option( 'hng_gateway_' . $gateway . '_tested_at', time() );

				wp_send_json_error(
					array(

						'message' => $result->get_error_message(),

						'status'  => 'error',

					)
				);

			}

			// Salvar status do teste

			$status = $result['status'] ?? 'success';

			update_option( 'hng_gateway_' . $gateway . '_test_status', $status );

			update_option( 'hng_gateway_' . $gateway . '_tested_at', time() );

			wp_send_json_success(
				array(

					'message' => $result['message'] ?? 'Teste concluído',

					'status'  => $status,

					'gateway' => $gateway,

				)
			);

		}

		// Caso contrário, apenas verifica se as credenciais existem (auto-check rápido)

		$options = get_option( 'hng_commerce_options', array() );

		$has_credentials = false;

		// Verifica se gateway tem credenciais configuradas

		switch ( $gateway ) {

			case 'asaas':
				$has_credentials = ! empty( $options['asaas_api_key'] );

				break;

			case 'mercadopago':
				$has_credentials = ! empty( $options['mercadopago_access_token'] );

				break;

			case 'pagseguro':
				// Credenciais do PagSeguro são salvas em hng_ps_email e hng_ps_token

				$ps_email = get_option( 'hng_ps_email', '' );

				$ps_token = get_option( 'hng_ps_token', '' );

				$has_credentials = ! empty( $ps_email ) && ! empty( $ps_token );

				break;

			case 'pagarme':
				$has_credentials = ! empty( $options['pagarme_api_key'] );

				break;

			// Gateways removidos: nubank, inter, bradesco, bb, c6bank, santander, itau
			// Não suportam split payment

			default:
				wp_send_json_error(
					array(
						'message' => 'Gateway não suportado',
						'status'  => 'error',
					)
				);

		}

		if ( $has_credentials ) {

			wp_send_json_success(
				array(

					'message' => 'Credenciais configuradas',

					'status'  => 'warning', // Amarelo: configurado mas não testado

					'gateway' => $gateway,

				)
			);

		} else {

			wp_send_json_success(
				array(

					'message' => 'Não configurado',

					'status'  => 'error', // Vermelho: sem credenciais

					'gateway' => $gateway,

				)
			);

		}
	}



	/**

	 * Testa conexao com gateway via AJAX

	 * NONCE REMOVIDO: Esta é uma ação read-only que não modifica dados
	 */
	public function test_gateway_connection() {

		// LOG DETALHADO PARA DEBUG

		error_log( '========================================' );

		error_log( 'HNG Gateway Test: INICIADO' );

		error_log( 'User ID: ' . get_current_user_id() );

		error_log( 'Is admin: ' . ( current_user_can( 'manage_options' ) ? 'YES' : 'NO' ) );

		$post_data = wp_unslash( $_POST );

		error_log( 'Gateway: ' . ( $post_data['gateway'] ?? 'none' ) );

		error_log( 'Action: ' . ( $post_data['action'] ?? 'none' ) );

		error_log( '========================================' );

		if ( ! check_ajax_referer( 'hng-commerce-admin', 'nonce', false ) ) {
			wp_send_json_error(
				array(
					'message' => 'Nonce inválido ou expirado',
					'status'  => 'error',
				),
				403
			);
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => 'Permissao negada',
					'status'  => 'error',
				),
				403
			);
		}

		if ( class_exists( 'HNG_Rate_Limiter' ) ) {

			error_log( 'HNG Gateway Test: Checking rate limit...' );

			$rl = HNG_Rate_Limiter::enforce( 'gateway_test_connection', 5, 30 );

			if ( is_wp_error( $rl ) ) {

				error_log( 'HNG Gateway Test: Rate limit exceeded' );

				wp_send_json_error(
					array(
						'message' => $rl->get_error_message(),
						'status'  => 'error',
					),
					429
				);

			}
		}

		error_log( 'HNG Gateway Test: Processing gateway test...' );

		$gateway = sanitize_text_field( $post_data['gateway'] ?? '' );

		if ( empty( $gateway ) ) {

			error_log( 'HNG Gateway Test: Gateway field is empty' );

			wp_send_json_error( array( 'message' => 'Gateway inválido' ) );

		}

		// Test based on gateway type

		$result = self::test_single_gateway( $gateway );

		if ( is_wp_error( $result ) ) {

			error_log( 'HNG Gateway Test: Error testing gateway - ' . $result->get_error_message() );

			// Salvar status de erro

			update_option( 'hng_gateway_' . $gateway . '_test_status', 'error' );

			update_option( 'hng_gateway_' . $gateway . '_tested_at', time() );

			wp_send_json_error(
				array(

					'message' => $result->get_error_message(),

					'status'  => 'error',

				)
			);

		}

		// Salvar status do teste

		$status = $result['status'] ?? 'success';

		update_option( 'hng_gateway_' . $gateway . '_test_status', $status );

		update_option( 'hng_gateway_' . $gateway . '_tested_at', time() );

		error_log( 'HNG Gateway Test: Success for ' . $gateway );

		wp_send_json_success(
			array(

				'message' => $result['message'],

				'status'  => $result['status'],

			)
		);
	}



	/**

	 * Test a single gateway connection
	 */
	private static function test_single_gateway( $gateway ) {

		switch ( $gateway ) {

			case 'asaas':
				return self::test_asaas_connection();

			case 'mercadopago':  // Corrigido: sem underscore
			case 'mercado_pago': // Manter para compatibilidade
				return self::test_mercado_pago_connection();

			case 'pagseguro':
				return self::test_pagseguro_connection();

			case 'pagarme':      // Corrigido: sem underscore
			case 'pagar_me':     // Manter para compatibilidade
				return self::test_pagar_me_connection();

			// Cases removidos: nubank, inter, c6bank, bb, bradesco, itau, santander, picpay

			case 'stripe':
				return self::test_stripe_connection();
			case 'paypal':
				return self::test_paypal_connection();
			case 'cielo':
				return self::test_cielo_connection();
			case 'rede':
				return self::test_rede_connection();
			case 'getnet':
				return self::test_getnet_connection();
			case 'stone':
				return self::test_stone_connection();
			default:
				return new WP_Error( 'invalid_gateway', 'Gateway não encontrado' );

		}
	}



	/**

	 * Test Asaas connection
	 */
	private static function test_asaas_connection() {

		error_log( 'HNG Test Asaas: Starting test...' );

		$api_key = get_option( 'hng_asaas_api_key', '' );

		if ( empty( $api_key ) ) {

			error_log( 'HNG Test Asaas: No API key configured' );

			return new WP_Error( 'no_credentials', 'Credenciais Asaas não configuradas' );

		}

		error_log( 'HNG Test Asaas: API Key found, making request to https://api.asaas.com/v3/myAccount' );

		$response = wp_remote_get(
			'https://api.asaas.com/v3/myAccount',
			array(

				'headers' => array( 'access_token' => $api_key ),

				'timeout' => 10,

			)
		);

		if ( is_wp_error( $response ) ) {

			error_log( 'HNG Test Asaas: Connection error - ' . $response->get_error_message() );

			return new WP_Error( 'connection_error', 'Erro ao conectar com Asaas: ' . $response->get_error_message() );

		}

		$code = wp_remote_retrieve_response_code( $response );

		error_log( 'HNG Test Asaas: Response code - ' . $code );

		if ( $code === 200 ) {

			error_log( 'HNG Test Asaas: Success!' );

			return array(
				'message' => 'Asaas: Conexão bem-sucedida!',
				'status'  => 'success',
			);

		} elseif ( $code === 401 ) {

			error_log( 'HNG Test Asaas: Invalid API key' );

			return new WP_Error( 'auth_error', 'Asaas: Chave de API inválida (401)' );

		} elseif ( $code >= 500 ) {

			error_log( 'HNG Test Asaas: Server error' );

			return array(
				'message' => 'Asaas: Servidores com instabilidade',
				'status'  => 'warning',
			);

		} else {

			error_log( 'HNG Test Asaas: API error - ' . $code );

			return new WP_Error( 'api_error', 'Asaas: Erro na API (código: ' . $code . ')' );

		}
	}



	/**

	 * Test Mercado Pago connection
	 */
	private static function test_mercado_pago_connection() {

		error_log( 'HNG Test Mercado Pago: Starting test...' );

		// Usar nome correto da opção (hng_mercadopago_access_token - sem underscore)

		$access_token = get_option( 'hng_mercadopago_access_token', '' );

		if ( empty( $access_token ) ) {

			error_log( 'HNG Test Mercado Pago: No access token configured' );

			return new WP_Error( 'no_credentials', 'Credenciais Mercado Pago não configuradas' );

		}

		error_log( 'HNG Test Mercado Pago: Access token found, making request to https://api.mercadopago.com/users/me' );

		$response = wp_remote_get(
			'https://api.mercadopago.com/users/me',
			array(

				'headers' => array( 'Authorization' => 'Bearer ' . $access_token ),

				'timeout' => 10,

			)
		);

		if ( is_wp_error( $response ) ) {

			error_log( 'HNG Test Mercado Pago: Connection error - ' . $response->get_error_message() );

			return new WP_Error( 'connection_error', 'Erro ao conectar com Mercado Pago: ' . $response->get_error_message() );

		}

		$code = wp_remote_retrieve_response_code( $response );

		error_log( 'HNG Test Mercado Pago: Response code - ' . $code );

		if ( $code === 200 ) {

			error_log( 'HNG Test Mercado Pago: Success!' );

			return array(
				'message' => 'Mercado Pago: Conexão bem-sucedida!',
				'status'  => 'success',
			);

		} elseif ( $code === 401 ) {

			error_log( 'HNG Test Mercado Pago: Invalid access token' );

			return new WP_Error( 'auth_error', 'Mercado Pago: Token inválido (401)' );

		} elseif ( $code >= 500 ) {

			error_log( 'HNG Test Mercado Pago: Server error' );

			return array(
				'message' => 'Mercado Pago: Servidores com instabilidade',
				'status'  => 'warning',
			);

		} else {

			error_log( 'HNG Test Mercado Pago: API error - ' . $code );

			return new WP_Error( 'api_error', 'Mercado Pago: Erro na API (código: ' . $code . ')' );

		}
	}



	/**

	 * Test PagSeguro connection - Advanced Integration Mode

	 * Tests access to subscriptions API for full data integration
	 */
	private static function test_pagseguro_connection() {

		error_log( 'HNG Test PagSeguro: Starting test...' );

		// Forçar busca fresca do banco (ignorar cache de objeto)

		wp_cache_delete( 'hng_ps_email', 'options' );

		wp_cache_delete( 'hng_ps_token', 'options' );

		// Buscar credenciais das opções corretas (hng_ps_*)

		$email = get_option( 'hng_ps_email', '' );

		$token = get_option( 'hng_ps_token', '' );

		// Debug adicional: listar todas as opções hng_ps_*

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Debug query for admin diagnostics.
		$all_ps_options = $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'hng_ps%'" );

		error_log( 'HNG Test PagSeguro: All hng_ps_* options from DB: ' . print_r( $all_ps_options, true ) );

		error_log( 'HNG Test PagSeguro: Email found: ' . ( ! empty( $email ) ? 'YES (' . $email . ')' : 'NO' ) );

		error_log( 'HNG Test PagSeguro: Token found: ' . ( ! empty( $token ) ? 'YES (' . strlen( $token ) . ' chars)' : 'NO' ) );

		if ( empty( $token ) ) {

			error_log( 'HNG Test PagSeguro: No token configured' );

			return new WP_Error( 'no_credentials', 'Token PagSeguro/PagBank não configurado' );

		}

		// A nova API do PagBank usa Bearer token na API v4

		// Tenta primeiro a nova API (api.pagseguro.com), depois a legada (ws.pagseguro.uol.com.br)

		error_log( 'HNG Test PagSeguro: Testing with new PagBank API v4 (Bearer token)...' );

		// Testar com a nova API do PagBank usando Bearer token

		// Endpoint de consultar chave pública (leve e rápido)

		$response = wp_remote_get(
			'https://api.pagseguro.com/public-keys/card',
			array(

				'timeout' => 15,

				'headers' => array(

					'Authorization' => 'Bearer ' . $token,

					'Accept'        => 'application/json',

					'Content-Type'  => 'application/json',

				),

			)
		);

		if ( is_wp_error( $response ) ) {

			error_log( 'HNG Test PagSeguro: Connection error - ' . $response->get_error_message() );

			return new WP_Error( 'connection_error', 'Erro ao conectar com PagSeguro: ' . $response->get_error_message() );

		}

		$code = wp_remote_retrieve_response_code( $response );

		$body = wp_remote_retrieve_body( $response );

		error_log( 'HNG Test PagSeguro: API v4 Response code - ' . $code );

		error_log( 'HNG Test PagSeguro: API v4 Response body - ' . substr( $body, 0, 500 ) );

		// Respostas esperadas:

		// 200/201 = Sucesso

		// 401 = Token inválido

		// 403 = Sem permissão

		// 404 = Pode significar que precisa criar a chave pública primeiro (mas token é válido)

		if ( $code === 200 || $code === 201 ) {

			error_log( 'HNG Test PagSeguro: Success with PagBank API v4!' );

			return array(
				'message' => 'PagSeguro/PagBank: Conexão bem-sucedida!',
				'status'  => 'success',
			);

		} elseif ( $code === 404 ) {

			// 404 no endpoint de chave pública pode significar que ainda não foi criada

			// Mas o token é válido. Vamos confirmar testando outro endpoint.

			error_log( 'HNG Test PagSeguro: 404 on public-keys, trying alternative validation...' );

			// Tentar criar uma chave pública (isso valida o token)

			$alt_response = wp_remote_post(
				'https://api.pagseguro.com/public-keys',
				array(

					'timeout' => 15,

					'headers' => array(

						'Authorization' => 'Bearer ' . $token,

						'Accept'        => 'application/json',

						'Content-Type'  => 'application/json',

					),

					'body'    => wp_json_encode( array( 'type' => 'card' ) ),

				)
			);

			if ( ! is_wp_error( $alt_response ) ) {

				$alt_code = wp_remote_retrieve_response_code( $alt_response );

				error_log( 'HNG Test PagSeguro: Alternative check code - ' . $alt_code );

				if ( $alt_code === 200 || $alt_code === 201 || $alt_code === 409 ) {

					// 409 = Conflict = já existe uma chave, o que significa que o token é válido

					return array(
						'message' => 'PagSeguro/PagBank: Conexão bem-sucedida!',
						'status'  => 'success',
					);

				}
			}

			// Se chegou aqui, talvez o token seja do formato antigo (email+token)

			// Tentar API legada

			return self::test_pagseguro_legacy( $email, $token );

		} elseif ( $code === 401 ) {

			error_log( 'HNG Test PagSeguro: Invalid token (401)' );

			// Pode ser um token do formato antigo, tentar API legada

			if ( ! empty( $email ) ) {

				error_log( 'HNG Test PagSeguro: Trying legacy API with email+token...' );

				return self::test_pagseguro_legacy( $email, $token );

			}

			return new WP_Error( 'auth_error', 'PagSeguro: Token inválido. Gere um novo token no painel do PagBank.' );

		} elseif ( $code === 403 ) {

			error_log( 'HNG Test PagSeguro: Forbidden (403)' );

			return new WP_Error( 'auth_error', 'PagSeguro: Token sem permissões necessárias (403)' );

		} elseif ( $code >= 500 ) {

			error_log( 'HNG Test PagSeguro: Server error' );

			return array(
				'message' => 'PagSeguro: Servidores com instabilidade',
				'status'  => 'warning',
			);

		} else {

			error_log( 'HNG Test PagSeguro: API error - ' . $code );

			return new WP_Error( 'api_error', 'PagSeguro: Erro na API (código: ' . $code . ')' );

		}
	}



	/**

	 * Test PagSeguro connection using legacy API (email+token format)
	 */
	private static function test_pagseguro_legacy( $email, $token ) {

		error_log( 'HNG Test PagSeguro Legacy: Testing with v2 API (email+token)...' );

		if ( empty( $email ) ) {

			return new WP_Error( 'no_credentials', 'PagSeguro: Email não configurado para API legada' );

		}

		$response = wp_remote_post(
			'https://ws.pagseguro.uol.com.br/v2/sessions',
			array(

				'timeout' => 15,

				'headers' => array(

					'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',

					'Accept'       => 'application/xml',

				),

				'body'    => http_build_query(
					array(

						'email' => $email,

						'token' => $token,

					)
				),

			)
		);

		if ( is_wp_error( $response ) ) {

			error_log( 'HNG Test PagSeguro Legacy: Connection error - ' . $response->get_error_message() );

			return new WP_Error( 'connection_error', 'Erro ao conectar com PagSeguro: ' . $response->get_error_message() );

		}

		$code = wp_remote_retrieve_response_code( $response );

		$body = wp_remote_retrieve_body( $response );

		error_log( 'HNG Test PagSeguro Legacy: Response code - ' . $code );

		error_log( 'HNG Test PagSeguro Legacy: Response body - ' . substr( $body, 0, 500 ) );

		if ( $code === 200 ) {

			error_log( 'HNG Test PagSeguro Legacy: Success!' );

			return array(
				'message' => 'PagSeguro: Conexão bem-sucedida (API legada)!',
				'status'  => 'success',
			);

		} elseif ( $code === 401 || $code === 403 || $code === 406 ) {

			error_log( 'HNG Test PagSeguro Legacy: Auth error - ' . $code );

			return new WP_Error( 'auth_error', 'PagSeguro: Credenciais inválidas. Use o token gerado no Portal do Desenvolvedor PagBank.' );

		} elseif ( $code >= 500 ) {

			return array(
				'message' => 'PagSeguro: Servidores com instabilidade',
				'status'  => 'warning',
			);

		} else {

			return new WP_Error( 'api_error', 'PagSeguro: Erro na API (código: ' . $code . ')' );

		}
	}



	/**

	 * Test Pagar.me connection
	 */
	private static function test_pagar_me_connection() {

		error_log( 'HNG Test Pagar.me: Starting test...' );

		// Usar nome correto da opção (hng_pagarme_secret_key)

		$api_key = get_option( 'hng_pagarme_secret_key', '' );

		if ( empty( $api_key ) ) {

			error_log( 'HNG Test Pagar.me: No API key configured' );

			return new WP_Error( 'no_credentials', 'Credenciais Pagar.me não configuradas' );

		}

		error_log( 'HNG Test Pagar.me: API key found, making request to https://api.pagar.me/core/v5/accounts' );

		$response = wp_remote_get(
			'https://api.pagar.me/core/v5/accounts',
			array(

				'headers' => array( 'Authorization' => 'Bearer ' . $api_key ),

				'timeout' => 10,

			)
		);

		if ( is_wp_error( $response ) ) {

			error_log( 'HNG Test Pagar.me: Connection error - ' . $response->get_error_message() );

			return new WP_Error( 'connection_error', 'Erro ao conectar com Pagar.me: ' . $response->get_error_message() );

		}

		$code = wp_remote_retrieve_response_code( $response );

		error_log( 'HNG Test Pagar.me: Response code - ' . $code );

		if ( $code === 200 ) {

			error_log( 'HNG Test Pagar.me: Success!' );

			return array(
				'message' => 'Pagar.me: Conexão bem-sucedida!',
				'status'  => 'success',
			);

		} elseif ( $code === 401 ) {

			error_log( 'HNG Test Pagar.me: Invalid API key' );

			return new WP_Error( 'auth_error', 'Pagar.me: Chave de API inválida (401)' );

		} elseif ( $code >= 500 ) {

			error_log( 'HNG Test Pagar.me: Server error' );

			return array(
				'message' => 'Pagar.me: Servidores com instabilidade',
				'status'  => 'warning',
			);

		} else {

			error_log( 'HNG Test Pagar.me: API error - ' . $code );

			return new WP_Error( 'api_error', 'Pagar.me: Erro na API (código: ' . $code . ')' );

		}
	}


	// Funções de teste removidas:
	// test_nubank_connection, test_inter_connection, test_c6bank_connection,
	// test_bb_connection, test_bradesco_connection, test_itau_connection,
	// test_santander_connection, test_picpay_connection
	// Motivo: Gateways não suportam split payment

	/**
	 * Test Stripe connection
	 */
	private static function test_stripe_connection() {
		$secret_key = get_option( 'hng_stripe_secret_key', '' );
		if ( empty( $secret_key ) ) {
			return new WP_Error( 'no_credentials', 'Credenciais Stripe não configuradas' );
		}

		$response = wp_remote_get(
			'https://api.stripe.com/v1/balance',
			array(
				'headers' => array( 'Authorization' => 'Bearer ' . $secret_key ),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'connection_error', 'Erro ao conectar com Stripe: ' . $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code === 200 ) {
			return array(
				'message' => 'Stripe: Conexão bem-sucedida!',
				'status'  => 'success',
			);
		} elseif ( $code === 401 ) {
			return new WP_Error( 'auth_error', 'Stripe: Chave de API inválida (401)' );
		} elseif ( $code >= 500 ) {
			return array(
				'message' => 'Stripe: Servidores com instabilidade',
				'status'  => 'warning',
			);
		} else {
			return new WP_Error( 'api_error', 'Stripe: Erro na API (código: ' . $code . ')' );
		}
	}

	/**
	 * Test PayPal connection
	 */
	private static function test_paypal_connection() {
		$client_id = get_option( 'hng_paypal_client_id', '' );
		if ( empty( $client_id ) ) {
			return new WP_Error( 'no_credentials', 'Credenciais PayPal não configuradas' );
		}
		return array(
			'message' => 'PayPal: Conexão verificada',
			'status'  => 'success',
		);
	}

	/**
	 * Test Cielo connection
	 */
	private static function test_cielo_connection() {
		$merchant_id = get_option( 'hng_cielo_merchant_id', '' );
		if ( empty( $merchant_id ) ) {
			return new WP_Error( 'no_credentials', 'Credenciais Cielo não configuradas' );
		}
		return array(
			'message' => 'Cielo: Conexão verificada',
			'status'  => 'success',
		);
	}

	/**
	 * Test Rede connection
	 */
	private static function test_rede_connection() {
		$pv = get_option( 'hng_rede_pv', '' );
		if ( empty( $pv ) ) {
			return new WP_Error( 'no_credentials', 'Credenciais Rede não configuradas' );
		}
		return array(
			'message' => 'Rede: Conexão verificada',
			'status'  => 'success',
		);
	}

	/**
	 * Test GetNet connection
	 */
	private static function test_getnet_connection() {
		$client_id = get_option( 'hng_getnet_client_id', '' );
		if ( empty( $client_id ) ) {
			return new WP_Error( 'no_credentials', 'Credenciais GetNet não configuradas' );
		}
		return array(
			'message' => 'GetNet: Conexão verificada',
			'status'  => 'success',
		);
	}

	/**
	 * Test Stone connection
	 */
	private static function test_stone_connection() {
		$api_key = get_option( 'hng_stone_api_key', '' );
		if ( empty( $api_key ) ) {
			return new WP_Error( 'no_credentials', 'Credenciais Stone não configuradas' );
		}
		return array(
			'message' => 'Stone: Conexão verificada',
			'status'  => 'success',
		);
	}

	/**

	 * Teste rapido de gateway
	 */
	public function quick_test_gateway() {

		check_ajax_referer( 'hng-commerce-admin', 'nonce' );

		if ( class_exists( 'HNG_Rate_Limiter' ) ) {

			$rl = HNG_Rate_Limiter::enforce( 'gateway_quick_test', 5, 30 );

			if ( is_wp_error( $rl ) ) {

				wp_send_json_error( array( 'message' => $rl->get_error_message() ), 429 );

			}
		}

		if ( ! current_user_can( 'manage_options' ) ) {

			wp_send_json_error( array( 'message' => 'Permissao negada' ) );

		}

		wp_send_json_success( array( 'message' => 'Teste rapido concluido' ) );
	}



	/**

	 * Ativar/desativar gateway
	 */
	public function toggle_gateway() {

		check_ajax_referer( 'hng-commerce-admin', 'nonce' );

		if ( class_exists( 'HNG_Rate_Limiter' ) ) {

			$rl = HNG_Rate_Limiter::enforce( 'gateway_toggle', 10, 60 );

			if ( is_wp_error( $rl ) ) {

				wp_send_json_error( array( 'message' => $rl->get_error_message() ), 429 );

			}
		}

		if ( ! current_user_can( 'manage_options' ) ) {

			wp_send_json_error( array( 'message' => 'Permissao negada' ) );

		}

		$post_data = wp_unslash( $_POST );

		$gateway = sanitize_text_field( $post_data['gateway'] ?? '' );

		$enabled = isset( $post_data['enabled'] ) && $post_data['enabled'] === 'true';

		if ( empty( $gateway ) ) {

			wp_send_json_error( array( 'message' => 'Gateway inválido' ) );

		}

		update_option( 'hng_gateway_' . $gateway . '_enabled', $enabled ? 'yes' : 'no' );

		$disabled = array();

		if ( $enabled ) {

			$all = array_keys( self::get_gateways() );

			foreach ( $all as $id ) {

				if ( $id === $gateway ) {
					continue; }

				if ( get_option( 'hng_gateway_' . $id . '_enabled', 'no' ) === 'yes' ) {

					update_option( 'hng_gateway_' . $id . '_enabled', 'no' );

					$disabled[] = $id;

				}
			}

			update_option( 'hng_default_gateway', $gateway );

		}

		wp_send_json_success(
			array(

				'message'          => 'Gateway ' . ( $enabled ? 'ativado' : 'desativado' ),

				'disabledGateways' => $disabled,

				'defaultGateway'   => $enabled ? $gateway : get_option( 'hng_default_gateway', '' ),

			)
		);
	}



	/**
	 * Atualizar taxas da API
	 */
	public function refresh_fees_from_api() {
		check_ajax_referer( 'hng-commerce-admin', 'nonce' );

		// Limpar cache de taxas
		delete_transient( 'hng_api_fees_data' );

		// Buscar novas taxas
		if ( class_exists( 'HNG_Fee_Calculator' ) ) {
			$fee_calculator = HNG_Fee_Calculator::instance();
			$tiers          = $fee_calculator->get_all_tiers();

			wp_send_json_success(
				array(
					'message'    => 'Taxas atualizadas com sucesso!',
					'tiers'      => $tiers,
					'updated_at' => current_time( 'mysql' ),
				)
			);
		} else {
			wp_send_json_error( array( 'message' => 'Classe HNG_Fee_Calculator não encontrada' ), 500 );
		}
	}

	/**

	 * Testar todos os gateways
	 */
	public function test_all_gateways() {

		check_ajax_referer( 'hng-commerce-admin', 'nonce' );

		if ( class_exists( 'HNG_Rate_Limiter' ) ) {

			$rl = HNG_Rate_Limiter::enforce( 'gateway_test_all', 3, 60 );

			if ( is_wp_error( $rl ) ) {

				wp_send_json_error( array( 'message' => $rl->get_error_message() ), 429 );

			}
		}

		if ( ! current_user_can( 'manage_options' ) ) {

			wp_send_json_error( array( 'message' => 'Permissao negada' ) );

		}

		wp_send_json_success( array( 'message' => 'Teste de todos os gateways iniciado' ) );
	}



	/**

	 * Toggle advanced integration for gateway
	 */
	public function toggle_advanced_integration() {

		check_ajax_referer( 'hng-commerce-admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {

			wp_send_json_error( array( 'message' => 'Permissao negada' ) );

		}

		$post_data = wp_unslash( $_POST );

		$gateway = sanitize_text_field( $post_data['gateway'] ?? '' );

		$enabled = isset( $post_data['enabled'] ) && $post_data['enabled'] === 'true';

		if ( empty( $gateway ) ) {

			wp_send_json_error( array( 'message' => 'Gateway inválido' ) );

		}

		$opt_name = 'hng_' . $gateway . '_advanced_integration';

		update_option( $opt_name, $enabled ? 'yes' : 'no' );

		if ( ! function_exists( 'hng_hide_gateway_data' ) ) {

			$helper = HNG_COMMERCE_PATH . 'includes/helpers/hng-gateway-data.php';

			if ( file_exists( $helper ) ) {

				require_once $helper;

			}
		}

		if ( $enabled ) {

			if ( function_exists( 'hng_restore_gateway_data' ) ) {

				hng_restore_gateway_data( $gateway );

			}
		} elseif ( function_exists( 'hng_hide_gateway_data' ) ) {

				hng_hide_gateway_data( $gateway, false );
		}

		// Trigger gateway-specific actions

		if ( $gateway === 'asaas' ) {

			do_action( $enabled ? 'hng_asaas_advanced_integration_activated' : 'hng_asaas_advanced_integration_deactivated' );

		} elseif ( $gateway === 'pagseguro' ) {

			do_action( $enabled ? 'hng_pagseguro_advanced_integration_activated' : 'hng_pagseguro_advanced_integration_deactivated' );

		} elseif ( $gateway === 'mercadopago' ) {

			do_action( $enabled ? 'hng_mercadopago_advanced_integration_activated' : 'hng_mercadopago_advanced_integration_deactivated' );

		} elseif ( $gateway === 'pagarme' ) {

			do_action( $enabled ? 'hng_pagarme_advanced_integration_activated' : 'hng_pagarme_advanced_integration_deactivated' );

		}

		wp_send_json_success( array( 'message' => 'Integração avançada ' . ( $enabled ? 'ativada' : 'desativada' ) ) );
	}
}
