<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName
/**
 * HNG Commerce - Gerenciador de Funções (Roles)
 *
 * Gerencia a criação, edição e remoção de funções personalizadas
 *
 * @package HNG_Commerce
 * @subpackage Admin/Settings
 * @since 1.2.15
 */

// phpcs:disable Squiz.Commenting.InlineComment.InvalidEndChar
// phpcs:disable Squiz.Commenting.FunctionComment.Missing
// phpcs:disable Squiz.Commenting.FunctionComment.MissingParamTag
// phpcs:disable Squiz.Commenting.ClassComment.Missing
// phpcs:disable Squiz.Commenting.VariableComment.MissingVar
// phpcs:disable WordPress.PHP.YodaConditions.NotYoda
// phpcs:disable WordPress.Security.ValidatedSanitizedInput

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HNG_Roles_Manager {

	/**
	 * Funções padrão do WordPress que não podem ser removidas
	 */
	const PROTECTED_ROLES = array(
		'administrator',
		'editor',
		'author',
		'contributor',
		'subscriber',
	);

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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_hng_save_role', array( $this, 'ajax_save_role' ) );
		add_action( 'wp_ajax_hng_delete_role', array( $this, 'ajax_delete_role' ) );
		add_action( 'wp_ajax_hng_get_role_capabilities', array( $this, 'ajax_get_role_capabilities' ) );
		add_action( 'wp_ajax_hng_search_users', array( $this, 'ajax_search_users' ) );
		add_action( 'wp_ajax_hng_get_user_roles', array( $this, 'ajax_get_user_roles' ) );
		add_action( 'wp_ajax_hng_save_user_roles', array( $this, 'ajax_save_user_roles' ) );
		add_action( 'wp_ajax_hng_get_interface_visibility', array( $this, 'ajax_get_interface_visibility' ) );
		add_action( 'wp_ajax_hng_save_interface_visibility', array( $this, 'ajax_save_interface_visibility' ) );

		add_action( 'admin_menu', array( $this, 'apply_admin_menu_visibility' ), 999 );
		add_action( 'admin_bar_menu', array( $this, 'apply_admin_bar_visibility' ), 999 );
		add_filter( 'show_admin_bar', array( $this, 'filter_show_admin_bar' ), 999 );
	}

	/**
	 * Enfileirar scripts
	 */
	public function enqueue_scripts( $hook ) {
		if ( strpos( $hook, 'hng-settings' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'hng-roles-manager',
			HNG_COMMERCE_URL . 'assets/css/roles-manager.css',
			array(),
			HNG_COMMERCE_VERSION
		);

		wp_enqueue_script(
			'hng-roles-manager',
			HNG_COMMERCE_URL . 'assets/js/roles-manager.js',
			array( 'jquery' ),
			HNG_COMMERCE_VERSION,
			true
		);

		wp_localize_script(
			'hng-roles-manager',
			'hngRolesManager',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'hng-roles-manager' ),
				'i18n'    => array(
					'confirmDelete'   => __( 'Tem certeza que deseja remover esta função?', 'hng-commerce' ),
					'saved'           => __( 'Função salva com sucesso!', 'hng-commerce' ),
					'deleted'         => __( 'Função removida com sucesso!', 'hng-commerce' ),
					'error'           => __( 'Erro ao processar solicitação', 'hng-commerce' ),
					'protectedRole'   => __( 'Não é possível remover funções padrão do WordPress', 'hng-commerce' ),
					'userSaved'       => __( 'Capacidades do usuário salvas com sucesso!', 'hng-commerce' ),
					'userLoaded'      => __( 'Usuário carregado.', 'hng-commerce' ),
					'noUsers'         => __( 'Nenhum usuário encontrado com os filtros informados.', 'hng-commerce' ),
					'selectUser'      => __( 'Selecione um usuário para editar suas capacidades.', 'hng-commerce' ),
					'modeRoles'       => __( 'Editar funções', 'hng-commerce' ),
					'modeUsers'       => __( 'Editar usuários', 'hng-commerce' ),
					'profileSaved'    => __( 'Usuário atualizado com sucesso!', 'hng-commerce' ),
					'invalidEmail'    => __( 'Informe um e-mail válido.', 'hng-commerce' ),
					'selectRole'      => __( 'Selecione uma função', 'hng-commerce' ),
					'visibilitySaved' => __( 'Configurações de visibilidade salvas com sucesso!', 'hng-commerce' ),
				),
			)
		);
	}

	/**
	 * Renderizar aba de gerenciador de funções
	 */
	public function render_tab() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Acesso negado.', 'hng-commerce' ) );
		}

		global $wp_roles;
		$roles = $wp_roles->roles;
		?>
		<div class="hng-roles-manager-wrapper">
			<div class="hng-roles-help-box">
				<h3><?php esc_html_e( 'Gerenciador de permissões', 'hng-commerce' ); ?></h3>
				<p><?php esc_html_e( 'Use “Editar funções” para definir permissões por perfil e “Editar usuários” para ajustes individuais (nome, e-mail, função e capacidades extras).', 'hng-commerce' ); ?></p>
			</div>
			<div class="hng-mode-toggle" role="group" aria-label="Alternar modo de edição">
				<label>
					<input type="radio" name="hng-edit-mode" value="roles" checked>
					<?php esc_html_e( 'Editar funções', 'hng-commerce' ); ?>
				</label>
				<label>
					<input type="radio" name="hng-edit-mode" value="users">
					<?php esc_html_e( 'Editar usuários', 'hng-commerce' ); ?>
				</label>
			</div>
			<div class="hng-roles-container">
				<!-- Lista de Funções -->
				<div class="hng-roles-list-panel">
					<div class="hng-roles-section">
						<h3><?php esc_html_e( 'Funções Disponíveis', 'hng-commerce' ); ?></h3>
						
						<button type="button" class="button button-primary hng-add-role-btn" style="margin-bottom: 20px;">
							<span class="dashicons dashicons-plus-alt" style="vertical-align: middle; margin-right: 5px;"></span>
							<?php esc_html_e( 'Adicionar Nova Função', 'hng-commerce' ); ?>
						</button>

						<div class="hng-roles-list">
							<?php foreach ( $roles as $role_slug => $role_data ) : ?>
								<div class="hng-role-item" data-role="<?php echo esc_attr( $role_slug ); ?>">
									<div class="hng-role-header">
										<span class="hng-role-name"><?php echo esc_html( $role_data['name'] ); ?></span>
										<?php if ( in_array( $role_slug, self::PROTECTED_ROLES, true ) ) : ?>
											<span class="hng-badge-protected"><?php esc_html_e( 'Protegida', 'hng-commerce' ); ?></span>
										<?php endif; ?>
									</div>
									<div class="hng-role-actions">
										<button type="button" class="button button-small hng-edit-role-btn" data-role="<?php echo esc_attr( $role_slug ); ?>">
											<span class="dashicons dashicons-edit" style="vertical-align: middle;"></span>
											<?php esc_html_e( 'Editar', 'hng-commerce' ); ?>
										</button>
										<?php if ( ! in_array( $role_slug, self::PROTECTED_ROLES, true ) ) : ?>
											<button type="button" class="button button-small button-link-delete hng-delete-role-btn" data-role="<?php echo esc_attr( $role_slug ); ?>">
												<span class="dashicons dashicons-trash" style="vertical-align: middle;"></span>
												<?php esc_html_e( 'Remover', 'hng-commerce' ); ?>
											</button>
										<?php endif; ?>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>

					<div class="hng-users-section" style="display: none;">
						<h3><?php esc_html_e( 'Usuários', 'hng-commerce' ); ?></h3>

						<div class="hng-user-filters">
							<div class="hng-form-group hng-inline">
								<label for="hng-user-filter-name"><?php esc_html_e( 'Nome', 'hng-commerce' ); ?></label>
								<input type="text" id="hng-user-filter-name" class="regular-text" placeholder="<?php esc_attr_e( 'Buscar por nome', 'hng-commerce' ); ?>" />
							</div>
							<div class="hng-form-group hng-inline">
								<label for="hng-user-filter-email"><?php esc_html_e( 'Email', 'hng-commerce' ); ?></label>
								<input type="text" id="hng-user-filter-email" class="regular-text" placeholder="<?php esc_attr_e( 'Buscar por email', 'hng-commerce' ); ?>" />
							</div>
							<div class="hng-form-group hng-inline narrow">
								<label>&nbsp;</label>
								<button type="button" class="button hng-user-search-btn"><?php esc_html_e( 'Filtrar', 'hng-commerce' ); ?></button>
							</div>
						</div>

						<div class="hng-users-list"></div>
						<div class="hng-users-empty-state hng-empty-state" style="display: none;">
							<p><?php esc_html_e( 'Nenhum usuário encontrado.', 'hng-commerce' ); ?></p>
						</div>
					</div>
				</div>

				<!-- Painel de Edição/Criação -->
				<div class="hng-role-editor-panel">
					<div class="hng-role-editor-section">
						<h3><?php esc_html_e( 'Detalhes da Função', 'hng-commerce' ); ?></h3>
						
						<div id="hng-role-editor" style="display: none;">
							<div class="hng-form-group">
								<label for="hng-role-name">
									<?php esc_html_e( 'Nome da Função', 'hng-commerce' ); ?>
									<span class="required">*</span>
								</label>
								<input type="text" id="hng-role-name" class="regular-text" placeholder="<?php esc_attr_e( 'Ex: Gerente de Projetos', 'hng-commerce' ); ?>" />
								<small><?php esc_html_e( 'Nome exibido para os administradores', 'hng-commerce' ); ?></small>
							</div>

							<div class="hng-form-group">
								<label for="hng-role-slug">
									<?php esc_html_e( 'Identificador (Slug)', 'hng-commerce' ); ?>
									<span class="required">*</span>
								</label>
								<input type="text" id="hng-role-slug" class="regular-text" placeholder="<?php esc_attr_e( 'Ex: project_manager', 'hng-commerce' ); ?>" />
								<small><?php esc_html_e( 'Sem espaços, use apenas letras e underscores. Não pode ser alterado após criação.', 'hng-commerce' ); ?></small>
							</div>

							<div class="hng-form-group">
								<label><?php esc_html_e( 'Capacidades (Permissões)', 'hng-commerce' ); ?></label>
								<div class="hng-capabilities-toolbar">
									<input type="text" id="hng-capabilities-search" class="regular-text" placeholder="<?php esc_attr_e( 'Buscar capacidade...', 'hng-commerce' ); ?>" />
									<button type="button" class="button hng-select-all-caps-btn"><?php esc_html_e( 'Marcar todas', 'hng-commerce' ); ?></button>
									<button type="button" class="button hng-clear-all-caps-btn"><?php esc_html_e( 'Limpar', 'hng-commerce' ); ?></button>
								</div>
								<div id="hng-capabilities-list" class="hng-capabilities-grid">
									<!-- Preenchido via JavaScript -->
								</div>
							</div>

							<div class="hng-form-actions">
								<button type="button" class="button button-primary hng-save-role-btn">
									<?php esc_html_e( 'Salvar Função', 'hng-commerce' ); ?>
								</button>
								<button type="button" class="button hng-cancel-edit-btn">
									<?php esc_html_e( 'Cancelar', 'hng-commerce' ); ?>
								</button>
							</div>
						</div>

						<div id="hng-role-empty-state" class="hng-empty-state">
							<p><?php esc_html_e( 'Selecione uma função na lista para editar suas permissões.', 'hng-commerce' ); ?></p>
						</div>
					</div>

					<div class="hng-user-editor-section" style="display: none;">
						<h3><?php esc_html_e( 'Capacidades por Usuário', 'hng-commerce' ); ?></h3>

						<div id="hng-user-editor" style="display: none;">
							<div class="hng-form-group">
								<label><?php esc_html_e( 'Usuário selecionado', 'hng-commerce' ); ?></label>
								<div class="hng-user-summary">
									<div class="hng-user-name" id="hng-user-name"></div>
									<div class="hng-user-email" id="hng-user-email"></div>
								</div>
							</div>

							<div class="hng-form-group">
								<label for="hng-user-display-name"><?php esc_html_e( 'Nome de exibição', 'hng-commerce' ); ?></label>
								<input type="text" id="hng-user-display-name" class="regular-text" />
							</div>

							<div class="hng-form-group">
								<label for="hng-user-edit-email"><?php esc_html_e( 'E-mail', 'hng-commerce' ); ?></label>
								<input type="email" id="hng-user-edit-email" class="regular-text" />
							</div>

							<div class="hng-form-group">
								<label for="hng-user-primary-role"><?php esc_html_e( 'Função principal', 'hng-commerce' ); ?></label>
								<select id="hng-user-primary-role" class="regular-text"></select>
							</div>

							<div class="hng-form-group">
								<label><?php esc_html_e( 'Capacidades atribuídas', 'hng-commerce' ); ?></label>
								<div class="hng-capabilities-toolbar">
									<input type="text" id="hng-user-capabilities-search" class="regular-text" placeholder="<?php esc_attr_e( 'Buscar capacidade...', 'hng-commerce' ); ?>" />
									<button type="button" class="button hng-select-all-user-caps-btn"><?php esc_html_e( 'Marcar todas', 'hng-commerce' ); ?></button>
									<button type="button" class="button hng-clear-all-user-caps-btn"><?php esc_html_e( 'Limpar', 'hng-commerce' ); ?></button>
								</div>
								<div id="hng-user-capabilities-list" class="hng-capabilities-grid">
									<!-- Preenchido via JavaScript -->
								</div>
								<small><?php esc_html_e( 'Marque as capacidades que este usuário deve possuir individualmente (além das fornecidas pela função).', 'hng-commerce' ); ?></small>
							</div>

							<div class="hng-form-actions">
								<a href="#" target="_blank" rel="noopener noreferrer" class="button hng-open-user-profile-btn" style="display:none;">
									<?php esc_html_e( 'Abrir perfil no WordPress', 'hng-commerce' ); ?>
								</a>
								<button type="button" class="button button-primary hng-save-user-roles-btn">
									<?php esc_html_e( 'Salvar Capacidades do Usuário', 'hng-commerce' ); ?>
								</button>
								<button type="button" class="button hng-cancel-user-btn">
									<?php esc_html_e( 'Cancelar', 'hng-commerce' ); ?>
								</button>
							</div>
						</div>

						<div id="hng-user-empty-state" class="hng-empty-state">
							<p><?php esc_html_e( 'Selecione um usuário na lista para editar suas funções.', 'hng-commerce' ); ?></p>
						</div>
					</div>
				</div>
			</div>

			<div class="hng-visibility-manager-panel">
				<h3><?php esc_html_e( 'Gerenciador de Menus e Admin Bar', 'hng-commerce' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Defina o que cada função ou usuário pode visualizar no painel e na barra superior do WordPress.', 'hng-commerce' ); ?></p>

				<div class="hng-visibility-target">
					<label>
						<input type="radio" name="hng-visibility-target-type" value="role" checked>
						<?php esc_html_e( 'Aplicar por função', 'hng-commerce' ); ?>
					</label>
					<label>
						<input type="radio" name="hng-visibility-target-type" value="user">
						<?php esc_html_e( 'Aplicar por usuário', 'hng-commerce' ); ?>
					</label>
				</div>

				<div class="hng-visibility-target-hint" id="hng-visibility-target-hint">
					<?php esc_html_e( 'Modo atual: por função. Alterações afetam somente a função selecionada.', 'hng-commerce' ); ?>
				</div>

				<div class="hng-visibility-selectors">
					<div class="hng-form-group" id="hng-visibility-role-select-wrap">
						<label for="hng-visibility-role-select"><?php esc_html_e( 'Função', 'hng-commerce' ); ?></label>
						<select id="hng-visibility-role-select" class="regular-text">
							<?php foreach ( $this->get_available_roles() as $role_slug => $role_name ) : ?>
								<option value="<?php echo esc_attr( $role_slug ); ?>"><?php echo esc_html( $role_name ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="hng-form-group" id="hng-visibility-user-select-wrap" style="display:none;">
						<label for="hng-visibility-user-select"><?php esc_html_e( 'Usuário', 'hng-commerce' ); ?></label>
						<select id="hng-visibility-user-select" class="regular-text">
							<option value=""><?php esc_html_e( 'Selecione um usuário', 'hng-commerce' ); ?></option>
							<?php
							$users_for_visibility = get_users(
								array(
									'number'  => 200,
									'orderby' => 'display_name',
									'order'   => 'ASC',
									'fields'  => array( 'ID', 'display_name', 'user_email' ),
								)
							);
							foreach ( $users_for_visibility as $u ) :
								?>
								<option value="<?php echo esc_attr( $u->ID ); ?>"><?php echo esc_html( $u->display_name . ' (' . $u->user_email . ')' ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				<div class="hng-visibility-controls">
					<div class="hng-form-group">
						<label>
							<input type="checkbox" id="hng-visibility-hide-adminbar" value="1">
							<strong><?php esc_html_e( 'Ocultar Admin Bar completamente', 'hng-commerce' ); ?></strong>
						</label>
					</div>

					<div class="hng-form-group">
						<label><?php esc_html_e( 'Menus do painel a ocultar', 'hng-commerce' ); ?></label>
						<div class="hng-capabilities-toolbar">
							<input type="text" id="hng-visibility-menu-search" class="regular-text" placeholder="<?php esc_attr_e( 'Buscar menu...', 'hng-commerce' ); ?>" />
							<button type="button" class="button hng-select-all-menus-btn"><?php esc_html_e( 'Marcar todos', 'hng-commerce' ); ?></button>
							<button type="button" class="button hng-clear-all-menus-btn"><?php esc_html_e( 'Limpar', 'hng-commerce' ); ?></button>
						</div>
						<div id="hng-visibility-menus-list" class="hng-capabilities-grid">
							<?php foreach ( $this->get_admin_menu_choices() as $menu_item ) : ?>
								<div class="hng-capability-item" data-type="menu">
									<input type="checkbox" id="hng-menu-<?php echo esc_attr( md5( $menu_item['id'] ) ); ?>" value="<?php echo esc_attr( $menu_item['id'] ); ?>">
									<label for="hng-menu-<?php echo esc_attr( md5( $menu_item['id'] ) ); ?>">
										<?php echo esc_html( $menu_item['label'] ); ?>
										<code style="font-size: 10px; color: #999;">(<?php echo esc_html( $menu_item['id'] ); ?>)</code>
									</label>
								</div>
							<?php endforeach; ?>
						</div>
					</div>

					<div class="hng-form-group">
						<label><?php esc_html_e( 'Itens da Admin Bar a ocultar', 'hng-commerce' ); ?></label>
						<div class="hng-capabilities-toolbar">
							<input type="text" id="hng-visibility-adminbar-search" class="regular-text" placeholder="<?php esc_attr_e( 'Buscar item...', 'hng-commerce' ); ?>" />
							<button type="button" class="button hng-select-all-adminbar-btn"><?php esc_html_e( 'Marcar todos', 'hng-commerce' ); ?></button>
							<button type="button" class="button hng-clear-all-adminbar-btn"><?php esc_html_e( 'Limpar', 'hng-commerce' ); ?></button>
						</div>
						<div id="hng-visibility-adminbar-list" class="hng-capabilities-grid">
							<?php foreach ( $this->get_admin_bar_choices() as $admin_bar_item ) : ?>
								<div class="hng-capability-item" data-type="adminbar">
									<input type="checkbox" id="hng-adminbar-<?php echo esc_attr( md5( $admin_bar_item['id'] ) ); ?>" value="<?php echo esc_attr( $admin_bar_item['id'] ); ?>">
									<label for="hng-adminbar-<?php echo esc_attr( md5( $admin_bar_item['id'] ) ); ?>">
										<?php echo esc_html( $admin_bar_item['label'] ); ?>
										<code style="font-size: 10px; color: #999;">(<?php echo esc_html( $admin_bar_item['id'] ); ?>)</code>
									</label>
								</div>
							<?php endforeach; ?>
						</div>
					</div>

					<div class="hng-form-actions">
						<button type="button" class="button hng-load-visibility-btn"><?php esc_html_e( 'Carregar configuração', 'hng-commerce' ); ?></button>
						<button type="button" class="button button-primary hng-save-visibility-btn"><?php esc_html_e( 'Salvar configuração', 'hng-commerce' ); ?></button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public function ajax_get_interface_visibility() {
		check_ajax_referer( 'hng-roles-manager', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Acesso negado.', 'hng-commerce' ) ), 403 );
		}

		$target_type = isset( $_POST['target_type'] ) ? sanitize_key( wp_unslash( $_POST['target_type'] ) ) : 'role';
		$target_id   = isset( $_POST['target_id'] ) ? sanitize_text_field( wp_unslash( $_POST['target_id'] ) ) : '';

		$rules   = $this->get_interface_visibility_rules();
		$current = array(
			'hide_admin_bar'        => false,
			'hidden_menus'          => array(),
			'hidden_adminbar_nodes' => array(),
		);

		if ( $target_type === 'role' && isset( $rules['roles'][ $target_id ] ) ) {
			$current = wp_parse_args( $rules['roles'][ $target_id ], $current );
		}

		if ( $target_type === 'user' ) {
			$uid = strval( absint( $target_id ) );
			if ( $uid !== '0' && isset( $rules['users'][ $uid ] ) ) {
				$current = wp_parse_args( $rules['users'][ $uid ], $current );
			}
		}

		wp_send_json_success( $current );
	}

	public function ajax_save_interface_visibility() {
		check_ajax_referer( 'hng-roles-manager', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Acesso negado.', 'hng-commerce' ) ), 403 );
		}

		$target_type           = isset( $_POST['target_type'] ) ? sanitize_key( wp_unslash( $_POST['target_type'] ) ) : 'role';
		$target_id             = isset( $_POST['target_id'] ) ? sanitize_text_field( wp_unslash( $_POST['target_id'] ) ) : '';
		$hide_admin_bar        = ! empty( $_POST['hide_admin_bar'] );
		$hidden_menus          = isset( $_POST['hidden_menus'] ) ? array_map( 'sanitize_text_field', (array) $_POST['hidden_menus'] ) : array();
		$hidden_adminbar_nodes = isset( $_POST['hidden_adminbar_nodes'] ) ? array_map( 'sanitize_text_field', (array) $_POST['hidden_adminbar_nodes'] ) : array();

		if ( ! in_array( $target_type, array( 'role', 'user' ), true ) || $target_id === '' ) {
			wp_send_json_error( array( 'message' => __( 'Destino inválido.', 'hng-commerce' ) ) );
		}

		$rules   = $this->get_interface_visibility_rules();
		$payload = array(
			'hide_admin_bar'        => $hide_admin_bar,
			'hidden_menus'          => array_values( array_unique( $hidden_menus ) ),
			'hidden_adminbar_nodes' => array_values( array_unique( $hidden_adminbar_nodes ) ),
		);

		if ( $target_type === 'role' ) {
			$rules['roles'][ $target_id ] = $payload;
		} else {
			$uid = strval( absint( $target_id ) );
			if ( $uid === '0' ) {
				wp_send_json_error( array( 'message' => __( 'Usuário inválido.', 'hng-commerce' ) ) );
			}
			$rules['users'][ $uid ] = $payload;
		}

		update_option( 'hng_ui_visibility_rules', $rules, false );

		wp_send_json_success(
			array(
				'message' => __( 'Configurações de visibilidade salvas com sucesso.', 'hng-commerce' ),
			)
		);
	}

	/**
	 * AJAX: Salvar função
	 */
	public function ajax_save_role() {
		check_ajax_referer( 'hng-roles-manager', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Acesso negado.', 'hng-commerce' ) ), 403 );
		}

		$role_slug    = isset( $_POST['role_slug'] ) ? sanitize_key( $_POST['role_slug'] ) : '';
		$role_name    = isset( $_POST['role_name'] ) ? sanitize_text_field( $_POST['role_name'] ) : '';
		$capabilities = isset( $_POST['capabilities'] ) ? array_map(
			function ( $cap ) {
				return sanitize_key( $cap );
			},
			(array) $_POST['capabilities']
		) : array();

		if ( empty( $role_slug ) || empty( $role_name ) ) {
			wp_send_json_error( array( 'message' => __( 'Nome e identificador são obrigatórios.', 'hng-commerce' ) ) );
		}

		// Validar slug
		if ( ! preg_match( '/^[a-z0-9_]+$/', $role_slug ) ) {
			wp_send_json_error( array( 'message' => __( 'Identificador inválido. Use apenas letras, números e underscores.', 'hng-commerce' ) ) );
		}

		global $wp_roles;

		// Verificar se é uma role existente
		if ( isset( $wp_roles->roles[ $role_slug ] ) ) {
			// Atualizar permissões de role existente
			$role = $wp_roles->get_role( $role_slug );
			if ( $role ) {
				// Remover todas as capacidades
				foreach ( $role->capabilities as $cap => $grant ) {
					$role->remove_cap( $cap );
				}
				// Adicionar novas capacidades
				foreach ( $capabilities as $cap ) {
					$role->add_cap( $cap );
				}
			}
		} else {
			// Criar nova role
			$caps_array = array();
			foreach ( $capabilities as $cap ) {
				$caps_array[ $cap ] = true;
			}
			add_role( $role_slug, $role_name, $caps_array );
		}

		wp_send_json_success(
			array(
				'message'   => __( 'Função salva com sucesso!', 'hng-commerce' ),
				'role_slug' => $role_slug,
			)
		);
	}

	/**
	 * AJAX: Deletar função
	 */
	public function ajax_delete_role() {
		check_ajax_referer( 'hng-roles-manager', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Acesso negado.', 'hng-commerce' ) ), 403 );
		}

		$role_slug = isset( $_POST['role_slug'] ) ? sanitize_key( $_POST['role_slug'] ) : '';

		if ( empty( $role_slug ) ) {
			wp_send_json_error( array( 'message' => __( 'Função não informada.', 'hng-commerce' ) ) );
		}

		// Verificar se é função protegida
		if ( in_array( $role_slug, self::PROTECTED_ROLES, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Não é possível remover funções padrão do WordPress.', 'hng-commerce' ) ) );
		}

		// Remover função
		if ( remove_role( $role_slug ) ) {
			wp_send_json_success( array( 'message' => __( 'Função removida com sucesso!', 'hng-commerce' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Erro ao remover função.', 'hng-commerce' ) ) );
		}
	}

	/**
	 * AJAX: Obter capacidades de uma função
	 */
	public function ajax_get_role_capabilities() {
		check_ajax_referer( 'hng-roles-manager', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Acesso negado.', 'hng-commerce' ) ), 403 );
		}

		$role_slug = isset( $_POST['role_slug'] ) ? sanitize_key( $_POST['role_slug'] ) : '';

		if ( empty( $role_slug ) ) {
			wp_send_json_error( array( 'message' => __( 'Função não informada.', 'hng-commerce' ) ) );
		}

		global $wp_roles;
		$role = $wp_roles->get_role( $role_slug );

		if ( ! $role ) {
			wp_send_json_error( array( 'message' => __( 'Função não encontrada.', 'hng-commerce' ) ) );
		}

		// Obter todas as capacidades disponíveis
		$all_capabilities = $this->get_all_available_capabilities();

		// Marcar capacidades que a função tem
		$role_capabilities = array_keys( $role->capabilities );

		wp_send_json_success(
			array(
				'role_name'         => $role->name,
				'role_slug'         => $role_slug,
				'is_protected'      => in_array( $role_slug, self::PROTECTED_ROLES, true ),
				'capabilities'      => $all_capabilities,
				'role_capabilities' => $role_capabilities,
			)
		);
	}

	/**
	 * AJAX: Buscar usuários por nome/email
	 */
	public function ajax_search_users() {
		check_ajax_referer( 'hng-roles-manager', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Acesso negado.', 'hng-commerce' ) ), 403 );
		}

		$name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

		$search_terms = array();
		if ( ! empty( $name ) ) {
			$search_terms[] = $name;
		}
		if ( ! empty( $email ) ) {
			$search_terms[] = $email;
		}

		$search_string = implode( ' ', $search_terms );

		$args = array(
			'number'  => 25,
			'orderby' => 'display_name',
			'order'   => 'ASC',
		);

		if ( $search_string ) {
			$args['search']         = '*' . $search_string . '*';
			$args['search_columns'] = array( 'user_login', 'user_email', 'user_nicename', 'display_name' );
		}

		$query = new WP_User_Query( $args );

		$users = array();
		foreach ( $query->get_results() as $user ) {
			$users[] = array(
				'id'    => $user->ID,
				'name'  => $user->display_name,
				'email' => $user->user_email,
				'roles' => $user->roles,
			);
		}

		wp_send_json_success(
			array(
				'users' => $users,
			)
		);
	}

	/**
	 * AJAX: Obter capacidades disponíveis e capacidades de um usuário
	 */
	public function ajax_get_user_roles() {
		check_ajax_referer( 'hng-roles-manager', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Acesso negado.', 'hng-commerce' ) ), 403 );
		}

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Usuário não informado.', 'hng-commerce' ) ) );
		}

		$user = get_user_by( 'ID', $user_id );
		if ( ! $user ) {
			wp_send_json_error( array( 'message' => __( 'Usuário não encontrado.', 'hng-commerce' ) ) );
		}

		$available_capabilities = $this->get_all_available_capabilities();

		$user_all_caps = array_keys( array_filter( (array) $user->allcaps ) );

		// Garantir que nenhuma capacidade especial do WP Core fique oculta
		foreach ( array_keys( (array) $user->caps ) as $cap ) {
			if ( ! isset( $available_capabilities[ $cap ] ) ) {
				$available_capabilities[ $cap ] = $this->get_capability_label( $cap );
			}
		}

		ksort( $available_capabilities );

		wp_send_json_success(
			array(
				'user'                   => array(
					'id'           => $user->ID,
					'name'         => $user->display_name,
					'email'        => $user->user_email,
					'login'        => $user->user_login,
					'primary_role' => $user->roles[0] ?? '',
					'edit_url'     => admin_url( 'user-edit.php?user_id=' . absint( $user->ID ) ),
				),
				'available_capabilities' => $available_capabilities,
				'available_roles'        => $this->get_available_roles(),
				'user_capabilities'      => $user_all_caps,
			)
		);
	}

	/**
	 * AJAX: Salvar capacidades atribuídas a um usuário
	 */
	public function ajax_save_user_roles() {
		check_ajax_referer( 'hng-roles-manager', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Acesso negado.', 'hng-commerce' ) ), 403 );
		}

		$user_id      = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$capabilities = isset( $_POST['capabilities'] ) ? array_map( 'sanitize_key', (array) $_POST['capabilities'] ) : array();
		$display_name = isset( $_POST['display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['display_name'] ) ) : '';
		$user_email   = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : '';
		$primary_role = isset( $_POST['primary_role'] ) ? sanitize_key( wp_unslash( $_POST['primary_role'] ) ) : '';

		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Usuário não informado.', 'hng-commerce' ) ) );
		}

		$user = get_user_by( 'ID', $user_id );
		if ( ! $user ) {
			wp_send_json_error( array( 'message' => __( 'Usuário não encontrado.', 'hng-commerce' ) ) );
		}

		if ( ! empty( $user_email ) && ! is_email( $user_email ) ) {
			wp_send_json_error( array( 'message' => __( 'E-mail inválido.', 'hng-commerce' ) ) );
		}

		if ( ! empty( $primary_role ) ) {
			global $wp_roles;
			if ( ! isset( $wp_roles->roles[ $primary_role ] ) ) {
				wp_send_json_error( array( 'message' => __( 'Função principal inválida.', 'hng-commerce' ) ) );
			}
		}

		$user_update_data = array(
			'ID' => $user_id,
		);

		if ( ! empty( $display_name ) ) {
			$user_update_data['display_name'] = $display_name;
		}
		if ( ! empty( $user_email ) ) {
			$existing = get_user_by( 'email', $user_email );
			if ( $existing && intval( $existing->ID ) !== intval( $user_id ) ) {
				wp_send_json_error( array( 'message' => __( 'Este e-mail já está em uso por outro usuário.', 'hng-commerce' ) ) );
			}
			$user_update_data['user_email'] = $user_email;
		}

		if ( count( $user_update_data ) > 1 ) {
			$update = wp_update_user( $user_update_data );
			if ( is_wp_error( $update ) ) {
				wp_send_json_error( array( 'message' => $update->get_error_message() ) );
			}
		}

		if ( ! empty( $primary_role ) ) {
			$user->set_role( $primary_role );
		}

		$available_caps = array_keys( $this->get_all_available_capabilities() );
		$valid_caps     = array_values( array_intersect( $capabilities, $available_caps ) );

		// Limpar capacidades individuais atuais
		foreach ( (array) $user->caps as $cap => $grant ) {
			$user->remove_cap( $cap );
		}

		// Atribuir novas capacidades individuais
		foreach ( $valid_caps as $cap ) {
			$user->add_cap( $cap );
		}

		wp_send_json_success(
			array(
				'message'      => __( 'Capacidades do usuário atualizadas com sucesso.', 'hng-commerce' ),
				'capabilities' => array_keys( (array) $user->allcaps ),
				'primary_role' => $user->roles[0] ?? '',
			)
		);
	}

	/**
	 * Roles disponíveis no sistema
	 */
	private function get_available_roles() {
		global $wp_roles;

		$roles = array();
		foreach ( $wp_roles->roles as $slug => $data ) {
			$roles[ $slug ] = $data['name'];
		}

		return $roles;
	}

	/**
	 * Obter todas as capacidades disponíveis
	 */
	private function get_all_available_capabilities() {
		global $wp_roles;

		$capabilities = array();

		// Coletar todas as capacidades de todas as roles
		foreach ( $wp_roles->roles as $role ) {
			foreach ( array_keys( $role['capabilities'] ) as $cap ) {
				if ( ! isset( $capabilities[ $cap ] ) ) {
					$capabilities[ $cap ] = $this->get_capability_label( $cap );
				}
			}
		}

		// Ordenar alfabeticamente
		ksort( $capabilities );

		return $capabilities;
	}

	private function get_interface_visibility_rules() {
		$rules = get_option( 'hng_ui_visibility_rules', array() );
		if ( ! is_array( $rules ) ) {
			$rules = array();
		}

		if ( ! isset( $rules['roles'] ) || ! is_array( $rules['roles'] ) ) {
			$rules['roles'] = array();
		}
		if ( ! isset( $rules['users'] ) || ! is_array( $rules['users'] ) ) {
			$rules['users'] = array();
		}

		return $rules;
	}

	private function get_current_user_visibility_rules() {
		$user = wp_get_current_user();
		if ( ! $user || empty( $user->ID ) ) {
			return array(
				'hide_admin_bar'        => false,
				'hidden_menus'          => array(),
				'hidden_adminbar_nodes' => array(),
			);
		}

		$rules  = $this->get_interface_visibility_rules();
		$merged = array(
			'hide_admin_bar'        => false,
			'hidden_menus'          => array(),
			'hidden_adminbar_nodes' => array(),
		);

		foreach ( (array) $user->roles as $role_slug ) {
			if ( ! empty( $rules['roles'][ $role_slug ] ) && is_array( $rules['roles'][ $role_slug ] ) ) {
				$role_rule                       = $rules['roles'][ $role_slug ];
				$merged['hide_admin_bar']        = $merged['hide_admin_bar'] || ! empty( $role_rule['hide_admin_bar'] );
				$merged['hidden_menus']          = array_merge( $merged['hidden_menus'], (array) ( $role_rule['hidden_menus'] ?? array() ) );
				$merged['hidden_adminbar_nodes'] = array_merge( $merged['hidden_adminbar_nodes'], (array) ( $role_rule['hidden_adminbar_nodes'] ?? array() ) );
			}
		}

		$uid = strval( absint( $user->ID ) );
		if ( ! empty( $rules['users'][ $uid ] ) && is_array( $rules['users'][ $uid ] ) ) {
			$user_rule                       = $rules['users'][ $uid ];
			$merged['hide_admin_bar']        = $merged['hide_admin_bar'] || ! empty( $user_rule['hide_admin_bar'] );
			$merged['hidden_menus']          = array_merge( $merged['hidden_menus'], (array) ( $user_rule['hidden_menus'] ?? array() ) );
			$merged['hidden_adminbar_nodes'] = array_merge( $merged['hidden_adminbar_nodes'], (array) ( $user_rule['hidden_adminbar_nodes'] ?? array() ) );
		}

		$merged['hidden_menus']          = array_values( array_unique( array_filter( $merged['hidden_menus'] ) ) );
		$merged['hidden_adminbar_nodes'] = array_values( array_unique( array_filter( $merged['hidden_adminbar_nodes'] ) ) );

		return $merged;
	}

	public function filter_show_admin_bar( $show ) {
		$rules = $this->get_current_user_visibility_rules();
		if ( ! empty( $rules['hide_admin_bar'] ) ) {
			return false;
		}

		return $show;
	}

	public function apply_admin_bar_visibility( $wp_admin_bar ) {
		$rules = $this->get_current_user_visibility_rules();
		if ( empty( $rules['hidden_adminbar_nodes'] ) ) {
			return;
		}

		foreach ( $rules['hidden_adminbar_nodes'] as $node_id ) {
			$wp_admin_bar->remove_node( $node_id );
		}
	}

	public function apply_admin_menu_visibility() {
		if ( ! is_admin() ) {
			return;
		}

		$rules = $this->get_current_user_visibility_rules();
		if ( empty( $rules['hidden_menus'] ) ) {
			return;
		}

		foreach ( $rules['hidden_menus'] as $menu_id ) {
			if ( strpos( $menu_id, 'submenu::' ) === 0 ) {
				$parts = explode( '|', substr( $menu_id, 9 ), 2 );
				if ( count( $parts ) === 2 ) {
					remove_submenu_page( $parts[0], $parts[1] );
				}
				continue;
			}

			if ( strpos( $menu_id, 'menu::' ) === 0 ) {
				$slug = substr( $menu_id, 6 );
				if ( $slug !== '' ) {
					remove_menu_page( $slug );
				}
			}
		}
	}

	private function get_admin_menu_choices() {
		global $menu, $submenu;

		$items = array();

		if ( is_array( $menu ) ) {
			foreach ( $menu as $entry ) {
				$label_raw = isset( $entry[0] ) ? wp_strip_all_tags( (string) $entry[0] ) : '';
				$slug      = isset( $entry[2] ) ? (string) $entry[2] : '';
				if ( $slug === '' || $label_raw === '' ) {
					continue;
				}
				$items[] = array(
					'id'    => 'menu::' . $slug,
					'label' => $label_raw,
				);
			}
		}

		if ( is_array( $submenu ) ) {
			foreach ( $submenu as $parent_slug => $sub_items ) {
				if ( ! is_array( $sub_items ) ) {
					continue;
				}
				foreach ( $sub_items as $sub_entry ) {
					$sub_label = isset( $sub_entry[0] ) ? wp_strip_all_tags( (string) $sub_entry[0] ) : '';
					$sub_slug  = isset( $sub_entry[2] ) ? (string) $sub_entry[2] : '';
					if ( $sub_slug === '' || $sub_label === '' ) {
						continue;
					}
					$items[] = array(
						'id'    => 'submenu::' . $parent_slug . '|' . $sub_slug,
						'label' => sprintf( '%s → %s', $parent_slug, $sub_label ),
					);
				}
			}
		}

		usort(
			$items,
			static function ( $a, $b ) {
				return strcmp( $a['label'], $b['label'] );
			}
		);

		return $items;
	}

	private function get_admin_bar_choices() {
		$defaults = array(
			array(
				'id'    => 'wp-logo',
				'label' => 'Logo WordPress',
			),
			array(
				'id'    => 'site-name',
				'label' => 'Nome do site',
			),
			array(
				'id'    => 'updates',
				'label' => 'Atualizações',
			),
			array(
				'id'    => 'comments',
				'label' => 'Comentários',
			),
			array(
				'id'    => 'new-content',
				'label' => 'Novo',
			),
			array(
				'id'    => 'my-account',
				'label' => 'Minha conta',
			),
			array(
				'id'    => 'search',
				'label' => 'Busca',
			),
			array(
				'id'    => 'top-secondary',
				'label' => 'Topo secundário',
			),
		);

		return $defaults;
	}

	/**
	 * Obter label legível para uma capacidade
	 */
	private function get_capability_label( $capability ) {
		$labels = array(
			// Permissões de Leitura
			'read'                   => __( 'Ler conteúdo', 'hng-commerce' ),
			'read_private_posts'     => __( 'Ler posts privados', 'hng-commerce' ),
			'read_private_pages'     => __( 'Ler páginas privadas', 'hng-commerce' ),

			// Posts
			'edit_posts'             => __( 'Editar posts', 'hng-commerce' ),
			'edit_others_posts'      => __( 'Editar posts de outros autores', 'hng-commerce' ),
			'edit_published_posts'   => __( 'Editar posts publicados', 'hng-commerce' ),
			'edit_private_posts'     => __( 'Editar posts privados', 'hng-commerce' ),
			'publish_posts'          => __( 'Publicar posts', 'hng-commerce' ),
			'delete_posts'           => __( 'Deletar posts', 'hng-commerce' ),
			'delete_others_posts'    => __( 'Deletar posts de outros autores', 'hng-commerce' ),
			'delete_published_posts' => __( 'Deletar posts publicados', 'hng-commerce' ),
			'delete_private_posts'   => __( 'Deletar posts privados', 'hng-commerce' ),
			'create_posts'           => __( 'Criar posts', 'hng-commerce' ),
			'read_post'              => __( 'Ler post', 'hng-commerce' ),
			'delete_post'            => __( 'Deletar post', 'hng-commerce' ),
			'edit_post'              => __( 'Editar post', 'hng-commerce' ),

			// Páginas
			'edit_pages'             => __( 'Editar páginas', 'hng-commerce' ),
			'edit_others_pages'      => __( 'Editar páginas de outros autores', 'hng-commerce' ),
			'edit_published_pages'   => __( 'Editar páginas publicadas', 'hng-commerce' ),
			'edit_private_pages'     => __( 'Editar páginas privadas', 'hng-commerce' ),
			'publish_pages'          => __( 'Publicar páginas', 'hng-commerce' ),
			'delete_pages'           => __( 'Deletar páginas', 'hng-commerce' ),
			'delete_others_pages'    => __( 'Deletar páginas de outros autores', 'hng-commerce' ),
			'delete_published_pages' => __( 'Deletar páginas publicadas', 'hng-commerce' ),
			'delete_private_pages'   => __( 'Deletar páginas privadas', 'hng-commerce' ),

			// HNG Commerce
			'manage_hng_commerce'    => __( 'Gerenciar HNG Commerce', 'hng-commerce' ),
			'manage_products'        => __( 'Gerenciar produtos', 'hng-commerce' ),
			'manage_orders'          => __( 'Gerenciar pedidos', 'hng-commerce' ),
			'view_reports'           => __( 'Visualizar relatórios', 'hng-commerce' ),
			'view_orders'            => __( 'Visualizar pedidos', 'hng-commerce' ),

			// Administração
			'manage_options'         => __( 'Gerenciar configurações', 'hng-commerce' ),
			'edit_users'             => __( 'Editar usuários', 'hng-commerce' ),
			'edit_others_users'      => __( 'Editar outros usuários', 'hng-commerce' ),
			'list_users'             => __( 'Listar usuários', 'hng-commerce' ),
			'promote_users'          => __( 'Promover usuários', 'hng-commerce' ),
			'create_users'           => __( 'Criar usuários', 'hng-commerce' ),
			'delete_users'           => __( 'Deletar usuários', 'hng-commerce' ),
			'remove_users'           => __( 'Remover usuários', 'hng-commerce' ),
			'read_users'             => __( 'Ler usuários', 'hng-commerce' ),
			'edit_user'              => __( 'Editar usuário', 'hng-commerce' ),
			'delete_user'            => __( 'Deletar usuário', 'hng-commerce' ),
			'edit_dashboard'         => __( 'Editar painel', 'hng-commerce' ),
			'manage_network'         => __( 'Gerenciar rede', 'hng-commerce' ),

			// Temas e Plugins
			'manage_themes'          => __( 'Gerenciar temas', 'hng-commerce' ),
			'manage_theme_options'   => __( 'Gerenciar opções do tema', 'hng-commerce' ),
			'edit_theme_options'     => __( 'Editar opções do tema', 'hng-commerce' ),
			'install_plugins'        => __( 'Instalar plugins', 'hng-commerce' ),
			'activate_plugins'       => __( 'Ativar plugins', 'hng-commerce' ),
			'edit_plugins'           => __( 'Editar plugins', 'hng-commerce' ),
			'delete_plugins'         => __( 'Deletar plugins', 'hng-commerce' ),
			'upload_plugins'         => __( 'Enviar plugins', 'hng-commerce' ),
			'unfiltered_upload'      => __( 'Enviar arquivos sem filtro', 'hng-commerce' ),

			// Categorias e Tags
			'manage_categories'      => __( 'Gerenciar categorias', 'hng-commerce' ),
			'manage_links'           => __( 'Gerenciar links', 'hng-commerce' ),
			'manage_post_tags'       => __( 'Gerenciar etiquetas', 'hng-commerce' ),

			// Media
			'upload_files'           => __( 'Enviar arquivos', 'hng-commerce' ),
			'delete_others_files'    => __( 'Deletar arquivos de outros usuários', 'hng-commerce' ),

			// Comentários
			'moderate_comments'      => __( 'Moderar comentários', 'hng-commerce' ),
			'edit_comment'           => __( 'Editar comentários', 'hng-commerce' ),

			// Outros
			'manage_privacy_options' => __( 'Gerenciar opções de privacidade', 'hng-commerce' ),
			'export'                 => __( 'Exportar dados', 'hng-commerce' ),
			'import'                 => __( 'Importar dados', 'hng-commerce' ),
			'unfiltered_html'        => __( 'Usar HTML não filtrado', 'hng-commerce' ),
			'customize'              => __( 'Personalizar site', 'hng-commerce' ),
			'update_core'            => __( 'Atualizar WordPress', 'hng-commerce' ),
			'update_plugins'         => __( 'Atualizar plugins', 'hng-commerce' ),
			'update_themes'          => __( 'Atualizar temas', 'hng-commerce' ),
		);

		if ( isset( $labels[ $capability ] ) ) {
			return $labels[ $capability ];
		}

		// Fallback: exibir "Capacidade: <code>" em pt-BR
		$fallback = ucwords( str_replace( '_', ' ', $capability ) );
		return sprintf(
			/* translators: %s = capability name */
			__( 'Capacidade: %s', 'hng-commerce' ),
			$fallback
		);
	}
}

// Inicializar gerenciador automaticamente quando admin for carregado
add_action(
	'admin_init',
	function () {
		HNG_Roles_Manager::instance();
	},
	1
);
