<?php
/**
 * HNG Commerce - Live Chat System
 *
 * Sistema completo de chat ao vivo para atendimento ao cliente.
 * Funciona independente do tipo de produto.
 * Suporta usuários logados e visitantes.
 *
 * @package HNG_Commerce
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Uses plugin's own hng_live_chat_* tables with dynamic table names.
// phpcs:disable Squiz.Commenting.InlineComment.InvalidEndChar
// phpcs:disable Squiz.Commenting.FunctionComment.MissingParamTag
// phpcs:disable Squiz.Commenting.VariableComment.Missing
// phpcs:disable Squiz.Commenting.VariableComment.MissingVar
// phpcs:disable WordPress.PHP.YodaConditions.NotYoda
// phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
// phpcs:disable WordPress.WP.Capabilities
// phpcs:disable WordPress.Security.NonceVerification
// phpcs:disable WordPress.PHP.IniSet
// phpcs:disable Squiz.Commenting.ClassComment.Missing
// phpcs:disable Universal.Operators.StrictComparisons
// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions
// phpcs:disable WordPress.WP.AlternativeFunctions
class HNG_Live_Chat {

	/**
	 * Singleton instance
	 */
	private static $instance = null;

	/**
	 * Table names
	 */
	private $sessions_table;
	private $messages_table;
	private $attachments_table;

	/**
	 * Upload directory
	 */
	private $upload_dir;

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
		global $wpdb;

		$this->sessions_table    = $wpdb->prefix . 'hng_live_chat_sessions';
		$this->messages_table    = $wpdb->prefix . 'hng_live_chat_messages';
		$this->attachments_table = $wpdb->prefix . 'hng_live_chat_attachments';

		// Setup upload directory
		$this->setup_upload_dir();

		// Register query var for file access (must be before init)
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );

		// Hooks
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'init', array( $this, 'maybe_create_tables' ) );

		// Serve files
		add_action( 'template_redirect', array( $this, 'serve_chat_file' ) );

		// AJAX handler for file serving (alternative to rewrite rule)
		add_action( 'wp_ajax_hng_chat_get_file', array( $this, 'ajax_serve_file' ) );
		add_action( 'wp_ajax_nopriv_hng_chat_get_file', array( $this, 'ajax_serve_file' ) );

		// AJAX handlers - Frontend
		add_action( 'wp_ajax_hng_live_chat_start', array( $this, 'ajax_start_chat' ) );
		add_action( 'wp_ajax_nopriv_hng_live_chat_start', array( $this, 'ajax_start_chat' ) );
		add_action( 'wp_ajax_hng_live_chat_send', array( $this, 'ajax_send_message' ) );
		add_action( 'wp_ajax_nopriv_hng_live_chat_send', array( $this, 'ajax_send_message' ) );
		add_action( 'wp_ajax_hng_live_chat_get_messages', array( $this, 'ajax_get_messages' ) );
		add_action( 'wp_ajax_nopriv_hng_live_chat_get_messages', array( $this, 'ajax_get_messages' ) );
		add_action( 'wp_ajax_hng_live_chat_upload', array( $this, 'ajax_upload_file' ) );
		add_action( 'wp_ajax_nopriv_hng_live_chat_upload', array( $this, 'ajax_upload_file' ) );
		add_action( 'wp_ajax_hng_live_chat_end', array( $this, 'ajax_end_chat' ) );
		add_action( 'wp_ajax_nopriv_hng_live_chat_end', array( $this, 'ajax_end_chat' ) );
		add_action( 'wp_ajax_hng_live_chat_typing', array( $this, 'ajax_typing_indicator' ) );
		add_action( 'wp_ajax_nopriv_hng_live_chat_typing', array( $this, 'ajax_typing_indicator' ) );

		// Long Polling handlers
		add_action( 'wp_ajax_hng_live_chat_long_poll', array( $this, 'ajax_long_poll' ) );
		add_action( 'wp_ajax_nopriv_hng_live_chat_long_poll', array( $this, 'ajax_long_poll' ) );

		// Server-Sent Events handlers
		add_action( 'wp_ajax_hng_live_chat_sse', array( $this, 'ajax_sse_stream' ) );
		add_action( 'wp_ajax_nopriv_hng_live_chat_sse', array( $this, 'ajax_sse_stream' ) );

		// AJAX handlers - Admin
		add_action( 'wp_ajax_hng_live_chat_admin_get_sessions', array( $this, 'ajax_admin_get_sessions' ) );
		add_action( 'wp_ajax_hng_live_chat_admin_get_messages', array( $this, 'ajax_admin_get_messages' ) );
		add_action( 'wp_ajax_hng_live_chat_admin_send', array( $this, 'ajax_admin_send_message' ) );
		add_action( 'wp_ajax_hng_live_chat_admin_upload', array( $this, 'ajax_admin_upload_file' ) );
		add_action( 'wp_ajax_hng_live_chat_admin_close', array( $this, 'ajax_admin_close_session' ) );
		add_action( 'wp_ajax_hng_live_chat_admin_transfer', array( $this, 'ajax_admin_transfer_session' ) );
		add_action( 'wp_ajax_hng_live_chat_admin_notifications', array( $this, 'ajax_admin_notifications' ) );
		add_action( 'wp_ajax_hng_live_chat_admin_notifications_sse', array( $this, 'ajax_admin_notifications_sse' ) );

		// Admin menu - priority 50 to run after main menu
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 50 );

		// Enqueue scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_global_notification_scripts' ), 20 );

		// Footer widget
		add_action( 'wp_footer', array( $this, 'render_chat_widget' ) );

		// Settings - register tab in admin settings
		add_action( 'admin_init', array( $this, 'register_settings_tab' ) );

		// Legacy filters (keep for compatibility)
		add_filter( 'hng_commerce_settings_tabs', array( $this, 'add_settings_tab' ) );
		add_filter( 'hng_commerce_settings_fields', array( $this, 'add_settings_fields' ) );

		// Heartbeat for real-time updates
		add_filter( 'heartbeat_received', array( $this, 'heartbeat_received' ), 10, 2 );
		add_filter( 'heartbeat_nopriv_received', array( $this, 'heartbeat_received' ), 10, 2 );
	}

	/**
	 * Init
	 */
	public function init() {
		// Register rewrite rules for file access
		add_rewrite_rule(
			'hng-chat-file/([^/]+)/?$',
			'index.php?hng_chat_file=$matches[1]',
			'top'
		);
	}

	/**
	 * Add query vars for file access
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'hng_chat_file';
		return $vars;
	}

	/**
	 * Setup upload directory
	 */
	private function setup_upload_dir() {
		$upload_base      = wp_upload_dir();
		$this->upload_dir = $upload_base['basedir'] . '/hng-chat-files';

		if ( ! file_exists( $this->upload_dir ) ) {
			wp_mkdir_p( $this->upload_dir );

			// Create index.php to prevent directory listing
			$index = $this->upload_dir . '/index.php';
			if ( ! file_exists( $index ) ) {
				file_put_contents( $index, "<?php // Silence is golden\n" );
			}
		}
	}

	/**
	 * Serve chat file securely
	 */
	public function serve_chat_file() {
		$file_key = get_query_var( 'hng_chat_file' );
		if ( ! $file_key ) {
			return;
		}

		global $wpdb;

		// Get file info
		$file = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->attachments_table} WHERE file_key = %s",
				sanitize_text_field( $file_key )
			)
		);

		if ( ! $file ) {
			status_header( 404 );
			exit( 'Arquivo não encontrado' );
		}

		// Check if file exists in the expected path
		$file_path = $file->file_path;

		// If file doesn't exist in stored path, try to find it
		if ( ! file_exists( $file_path ) ) {
			// Try alternative paths (public_html vs www)
			$alt_paths = array(
				str_replace( '/public_html/', '/www/', $file_path ),
				str_replace( '/www/', '/public_html/', $file_path ),
			);

			foreach ( $alt_paths as $alt_path ) {
				if ( file_exists( $alt_path ) ) {
					$file_path = $alt_path;
					break;
				}
			}
		}

		if ( ! file_exists( $file_path ) ) {
			status_header( 404 );
			exit( 'Arquivo não encontrado' );
		}

		// Security: The file_key is a 32-character random string
		// This provides enough security for chat file access
		// No additional permission check needed since the key is unguessable

		// Serve file
		$mime_type = function_exists( 'mime_content_type' ) ? mime_content_type( $file_path ) : 'application/octet-stream';

		// Force correct MIME types for common files
		$ext        = strtolower( pathinfo( $file->original_name, PATHINFO_EXTENSION ) );
		$mime_types = array(
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'gif'  => 'image/gif',
			'webp' => 'image/webp',
			'pdf'  => 'application/pdf',
			'doc'  => 'application/msword',
			'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'txt'  => 'text/plain',
		);

		if ( isset( $mime_types[ $ext ] ) ) {
			$mime_type = $mime_types[ $ext ];
		}

		header( 'Content-Type: ' . $mime_type );
		header( 'Content-Disposition: inline; filename="' . rawurlencode( $file->original_name ) . '"' );
		header( 'Content-Length: ' . filesize( $file_path ) );
		header( 'Cache-Control: public, max-age=86400' );
		header( 'X-Content-Type-Options: nosniff' );

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Outputting file to browser requires readfile for large files
		readfile( $file_path );
		exit;
	}

	/**
	 * AJAX: Serve file (alternative to rewrite rule)
	 */
	public function ajax_serve_file() {
		$file_key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';

		if ( empty( $file_key ) ) {
			status_header( 400 );
			exit( 'Missing file key' );
		}

		global $wpdb;

		$file = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->attachments_table} WHERE file_key = %s",
				$file_key
			)
		);

		if ( ! $file ) {
			status_header( 404 );
			exit( 'Arquivo não encontrado' );
		}

		// Find file path
		$file_path = $file->file_path;

		if ( ! file_exists( $file_path ) ) {
			$alt_paths = array(
				str_replace( '/public_html/', '/www/', $file_path ),
				str_replace( '/www/', '/public_html/', $file_path ),
			);

			foreach ( $alt_paths as $alt_path ) {
				if ( file_exists( $alt_path ) ) {
					$file_path = $alt_path;
					break;
				}
			}
		}

		if ( ! file_exists( $file_path ) ) {
			status_header( 404 );
			exit( 'Arquivo não encontrado' );
		}

		// Get MIME type
		$ext        = strtolower( pathinfo( $file->original_name, PATHINFO_EXTENSION ) );
		$mime_types = array(
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'gif'  => 'image/gif',
			'webp' => 'image/webp',
			'pdf'  => 'application/pdf',
			'doc'  => 'application/msword',
			'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'txt'  => 'text/plain',
		);

		$mime_type = isset( $mime_types[ $ext ] ) ? $mime_types[ $ext ] : 'application/octet-stream';

		// Send headers
		header( 'Content-Type: ' . $mime_type );
		header( 'Content-Disposition: inline; filename="' . rawurlencode( $file->original_name ) . '"' );
		header( 'Content-Length: ' . filesize( $file_path ) );
		header( 'Cache-Control: public, max-age=86400' );
		header( 'X-Content-Type-Options: nosniff' );

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Outputting file to browser requires readfile for large files
		readfile( $file_path );
		exit;
	}

	/**
	 * Check if chat is enabled
	 */
	public function is_enabled() {
		return get_option( 'hng_live_chat_enabled', 'no' ) === 'yes';
	}

	/**
	 * Check if guest chat is allowed
	 */
	public function allow_guests() {
		return get_option( 'hng_live_chat_allow_guests', 'yes' ) === 'yes';
	}

	/**
	 * Get online operators count
	 */
	public function get_online_operators_count() {
		$operators = get_option( 'hng_live_chat_online_operators', array() );
		$timeout   = 5 * MINUTE_IN_SECONDS;
		$online    = 0;

		foreach ( $operators as $user_id => $last_seen ) {
			if ( time() - $last_seen < $timeout ) {
				++$online;
			}
		}

		return $online;
	}

	/**
	 * Set operator online
	 */
	public function set_operator_online( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return;
		}

		$operators             = get_option( 'hng_live_chat_online_operators', array() );
		$operators[ $user_id ] = time();
		update_option( 'hng_live_chat_online_operators', $operators );
	}

	/**
	 * Create database tables
	 */
	public function maybe_create_tables() {
		global $wpdb;

		$installed_version = get_option( 'hng_live_chat_db_version', '0' );
		$current_version   = '1.0.0';

		if ( version_compare( $installed_version, $current_version, '<' ) ) {
			$this->create_tables();
			update_option( 'hng_live_chat_db_version', $current_version );
		}
	}

	/**
	 * Create tables
	 */
	public function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Sessions table
		$sql1 = "CREATE TABLE IF NOT EXISTS {$this->sessions_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_key VARCHAR(64) NOT NULL,
            user_id BIGINT UNSIGNED DEFAULT NULL,
            guest_token VARCHAR(64) DEFAULT NULL,
            guest_name VARCHAR(100) DEFAULT NULL,
            guest_email VARCHAR(100) DEFAULT NULL,
            operator_id BIGINT UNSIGNED DEFAULT NULL,
            status ENUM('waiting', 'active', 'closed') DEFAULT 'waiting',
            department VARCHAR(100) DEFAULT 'general',
            subject VARCHAR(255) DEFAULT NULL,
            page_url VARCHAR(500) DEFAULT NULL,
            user_ip VARCHAR(45) DEFAULT NULL,
            user_agent TEXT DEFAULT NULL,
            started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_activity DATETIME DEFAULT CURRENT_TIMESTAMP,
            closed_at DATETIME DEFAULT NULL,
            rating TINYINT UNSIGNED DEFAULT NULL,
            rating_comment TEXT DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY session_key (session_key),
            KEY user_id (user_id),
            KEY operator_id (operator_id),
            KEY status (status),
            KEY started_at (started_at)
        ) {$charset_collate};";

		// Messages table
		$sql2 = "CREATE TABLE IF NOT EXISTS {$this->messages_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id BIGINT UNSIGNED NOT NULL,
            sender_type ENUM('customer', 'operator', 'system') NOT NULL,
            sender_id BIGINT UNSIGNED DEFAULT NULL,
            sender_name VARCHAR(100) DEFAULT NULL,
            message TEXT NOT NULL,
            message_type ENUM('text', 'file', 'image', 'system') DEFAULT 'text',
            attachment_id BIGINT UNSIGNED DEFAULT NULL,
            is_read TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY created_at (created_at)
        ) {$charset_collate};";

		// Attachments table
		$sql3 = "CREATE TABLE IF NOT EXISTS {$this->attachments_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id BIGINT UNSIGNED NOT NULL,
            message_id BIGINT UNSIGNED DEFAULT NULL,
            file_key VARCHAR(64) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_size BIGINT UNSIGNED DEFAULT 0,
            file_type VARCHAR(100) DEFAULT NULL,
            uploaded_by BIGINT UNSIGNED DEFAULT NULL,
            uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY file_key (file_key),
            KEY session_id (session_id),
            KEY message_id (message_id)
        ) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql1 );
		dbDelta( $sql2 );
		dbDelta( $sql3 );
	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'hng-commerce',
			__( 'Chat ao Vivo', 'hng-commerce' ),
			__( 'Chat ao Vivo', 'hng-commerce' ),
			'manage_options',
			'hng-live-chat',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Add settings tab to HNG Admin Settings
	 */
	public function add_settings_tab( $tabs ) {
		// This method is kept for backwards compatibility
		// The actual tab registration is done in register_settings_tab()
		return $tabs;
	}

	/**
	 * Register the live chat settings tab
	 */
	public function register_settings_tab() {
		// Register in the settings manager if available
		if ( class_exists( 'HNG_Admin_Settings' ) && method_exists( 'HNG_Admin_Settings', 'instance' ) ) {
			$settings = HNG_Admin_Settings::instance();
			if ( method_exists( $settings, 'register_tab' ) ) {
				$settings->register_tab( 'live_chat', __( 'Chat ao Vivo', 'hng-commerce' ), array( $this, 'render_settings_tab' ) );
			}
		}

		// Process form submission early (before headers sent)
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['page'] ) && $_GET['page'] === 'hng-settings' && isset( $_GET['tab'] ) && $_GET['tab'] === 'live_chat' ) {
			if ( isset( $_POST['hng_live_chat_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hng_live_chat_settings_nonce'] ) ), 'hng_live_chat_settings' ) ) {
				$this->save_settings( $_POST );
				// Redirect to prevent resubmission
				wp_safe_redirect( admin_url( 'admin.php?page=hng-settings&tab=live_chat&saved=1' ) );
				exit;
			}
		}
	}

	/**
	 * Render live chat settings tab
	 */
	public function render_settings_tab() {
		// Show success message if saved
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['saved'] ) && $_GET['saved'] === '1' ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Configurações salvas com sucesso!', 'hng-commerce' ) . '</p></div>';
		}

		// Get current values
		$enabled               = get_option( 'hng_live_chat_enabled', 'no' );
		$allow_guests          = get_option( 'hng_live_chat_allow_guests', 'yes' );
		$require_email         = get_option( 'hng_live_chat_require_email', 'no' );
		$operators             = get_option( 'hng_live_chat_operators', array() );
		$operator_sound        = get_option( 'hng_live_chat_operator_sound', 'yes' );
		$show_operator_photo   = get_option( 'hng_live_chat_show_operator_photo', 'yes' );
		$operator_name_display = get_option( 'hng_live_chat_operator_name_display', 'display_name' );
		$operator_custom_name  = get_option( 'hng_live_chat_operator_custom_name', __( 'Atendente', 'hng-commerce' ) );
		$title                 = get_option( 'hng_live_chat_title', __( 'Atendimento ao Vivo', 'hng-commerce' ) );
		$welcome_message       = get_option( 'hng_live_chat_welcome_message', __( 'Olá! Como podemos ajudá-lo hoje?', 'hng-commerce' ) );
		$offline_message       = get_option( 'hng_live_chat_offline_message', '' );
		$position              = get_option( 'hng_live_chat_position', 'bottom-right' );
		$primary_color         = get_option( 'hng_live_chat_primary_color', '#2984f1' );
		$bubble_icon           = get_option( 'hng_live_chat_bubble_icon', 'chat' );
		$sound_enabled         = get_option( 'hng_live_chat_sound_enabled', 'yes' );
		$admin_sound_active    = get_option( 'hng_live_chat_admin_sound_active', 'yes' );
		$start_button_color    = get_option( 'hng_live_chat_start_button_color', '' );
		$button_text_color     = get_option( 'hng_live_chat_button_text_color', '#ffffff' );
		$header_color          = get_option( 'hng_live_chat_header_color', '' );
		$header_text_color     = get_option( 'hng_live_chat_header_text_color', '#ffffff' );
		$chat_bg_color         = get_option( 'hng_live_chat_bg_color', '#ffffff' );
		$message_text_color    = get_option( 'hng_live_chat_message_text_color', '#333333' );

		// Gradient settings
		$gradient_enabled   = get_option( 'hng_live_chat_gradient_enabled', 'no' );
		$gradient_color1    = get_option( 'hng_live_chat_gradient_color1', '#2984f1' );
		$gradient_color2    = get_option( 'hng_live_chat_gradient_color2', '#7c3aed' );
		$gradient_direction = get_option( 'hng_live_chat_gradient_direction', '135deg' );
		$gradient_balance   = intval( get_option( 'hng_live_chat_gradient_balance', 50 ) );

		// Business hours settings
		$business_hours_enabled = get_option( 'hng_live_chat_business_hours_enabled', 'no' );
		$business_hours         = get_option( 'hng_live_chat_business_hours', array() );
		$time_format            = get_option( 'hng_live_chat_time_format', '24h' );
		$closed_dates           = get_option( 'hng_live_chat_closed_dates', '' );
		$outside_hours_message  = get_option( 'hng_live_chat_outside_hours_message', __( 'Nosso atendimento funciona de segunda a sexta, das 8h às 18h.', 'hng-commerce' ) );

		$max_file_size      = get_option( 'hng_live_chat_max_file_size', 5 );
		$allowed_file_types = get_option( 'hng_live_chat_allowed_file_types', 'jpg,jpeg,png,gif,pdf,doc,docx,txt' );
		$show_on_pages      = get_option( 'hng_live_chat_show_on_pages', '' );

		// Get all users for operator selection
		$users = get_users( array( 'role__in' => array( 'administrator', 'editor', 'shop_manager' ) ) );
		?>
		</form><!-- Close the main settings form to prevent options.php redirect -->
		
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=hng-settings&tab=live_chat' ) ); ?>">
		<div class="hng-live-chat-settings">
			<?php wp_nonce_field( 'hng_live_chat_settings', 'hng_live_chat_settings_nonce' ); ?>
			
			<h2 style="display: inline-flex; align-items: center;">
				<?php esc_html_e( 'Configurações Gerais', 'hng-commerce' ); ?>
				<?php
				echo wp_kses_post(
					hng_admin_tooltip(
						'💬 Chat ao Vivo - Configurações Gerais',
						array(
							array(
								'icon'    => '✅',
								'title'   => 'Ativar Chat',
								'content' => 'Habilita o widget de chat flutuante em todas as páginas do site (ou páginas específicas).',
							),
							array(
								'icon'    => '👤',
								'title'   => 'Visitantes',
								'content' => 'Permite que usuários não logados iniciem conversas. Útil para suporte pré-venda.',
							),
							array(
								'icon'    => '📧',
								'title'   => 'Email Obrigatório',
								'content' => 'Se ativado, visitantes precisam informar email antes de iniciar chat. Útil para follow-up.',
							),
						),
						array(
							'title' => '💡 Dicas',
							'items' => array(
								array(
									'label' => 'Captura de leads',
									'text'  => 'Ative email obrigatório',
								),
								array(
									'label' => 'Máximo suporte',
									'text'  => 'Permita visitantes sem email',
								),
							),
						)
					)
				);
				?>
			</h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Ativar Chat ao Vivo', 'hng-commerce' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="hng_live_chat_enabled" value="yes" <?php checked( $enabled, 'yes' ); ?> />
							<?php esc_html_e( 'Ativa o widget de chat ao vivo no frontend', 'hng-commerce' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Permitir Visitantes', 'hng-commerce' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="hng_live_chat_allow_guests" value="yes" <?php checked( $allow_guests, 'yes' ); ?> />
							<?php esc_html_e( 'Permite que usuários não logados iniciem um chat', 'hng-commerce' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Exigir Email de Visitantes', 'hng-commerce' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="hng_live_chat_require_email" value="yes" <?php checked( $require_email, 'yes' ); ?> />
							<?php esc_html_e( 'Visitantes precisam fornecer email para iniciar chat', 'hng-commerce' ); ?>
						</label>
					</td>
				</tr>
			</table>
			
			<h2 style="display: inline-flex; align-items: center;">
				<?php esc_html_e( 'Atendentes', 'hng-commerce' ); ?>
				<?php
				echo wp_kses_post(
					hng_admin_tooltip(
						'👥 Equipe de Atendimento',
						array(
							array(
								'icon'    => '✅',
								'title'   => 'Selecionar Atendentes',
								'content' => 'Marque os usuários que poderão atender chats. Apenas usuários com função Admin, Editor ou Gerente de Loja aparecem.',
							),
							array(
								'icon'    => '🔔',
								'title'   => 'Som de Notificação',
								'content' => 'Toca um som quando um novo cliente inicia um chat, alertando o atendente.',
							),
							array(
								'icon'    => '📷',
								'title'   => 'Foto e Nome',
								'content' => 'Configure como o atendente aparece para o cliente: foto do Gravatar, nome real ou personalizado.',
							),
						),
						array(
							'title' => '⚙️ Boas Práticas',
							'items' => array(
								array(
									'label' => 'Equipe pequena',
									'text'  => 'Marque todos os atendentes',
								),
								array(
									'label' => 'Privacidade',
									'text'  => 'Use nome personalizado genérico',
								),
							),
						)
					)
				);
				?>
			</h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Usuários Atendentes', 'hng-commerce' ); ?></th>
					<td>
						<div class="hng-operators-list" style="max-height: 250px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9; border-radius: 4px;">
							<?php if ( ! empty( $users ) ) : ?>
								<?php
								foreach ( $users as $user ) :
									$is_checked = in_array( $user->ID, (array) $operators, true );
									$is_admin   = in_array( 'administrator', $user->roles, true );
									?>
									<label style="display: flex; align-items: center; padding: 8px; margin-bottom: 4px; background: #fff; border-radius: 4px; cursor: pointer; <?php echo $is_admin ? 'border-left: 3px solid #2271b1;' : ''; ?>">
										<input type="checkbox" name="hng_live_chat_operators[]" value="<?php echo esc_attr( $user->ID ); ?>" <?php checked( $is_checked, true ); ?> style="margin-right: 10px;" />
										<?php echo get_avatar( $user->ID, 32, '', '', array( 'style' => 'border-radius: 50%; margin-right: 10px;' ) ); ?>
										<span style="flex: 1;">
											<strong><?php echo esc_html( $user->display_name ); ?></strong>
											<br>
											<small style="color: #666;"><?php echo esc_html( $user->user_email ); ?> 
												<?php if ( $is_admin ) : ?>
													<span style="background: #2271b1; color: #fff; padding: 1px 6px; border-radius: 3px; font-size: 10px; margin-left: 5px;">Admin</span>
												<?php endif; ?>
											</small>
										</span>
									</label>
								<?php endforeach; ?>
							<?php else : ?>
								<p style="margin: 0; color: #666;"><?php esc_html_e( 'Nenhum usuário encontrado.', 'hng-commerce' ); ?></p>
							<?php endif; ?>
						</div>
						<p class="description" style="margin-top: 8px;">
							<?php esc_html_e( 'Marque os usuários que poderão atender chats. Administradores sempre têm acesso mesmo se não marcados.', 'hng-commerce' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Notificação Sonora', 'hng-commerce' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="hng_live_chat_operator_sound" value="yes" <?php checked( $operator_sound, 'yes' ); ?> />
							<?php esc_html_e( 'Toca som quando há novo cliente aguardando', 'hng-commerce' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Mostrar Foto do Atendente', 'hng-commerce' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="hng_live_chat_show_operator_photo" value="yes" <?php checked( $show_operator_photo, 'yes' ); ?> />
							<?php esc_html_e( 'Exibe foto do atendente nas mensagens', 'hng-commerce' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Nome do Atendente', 'hng-commerce' ); ?></th>
					<td>
						<select name="hng_live_chat_operator_name_display">
							<option value="display_name" <?php selected( $operator_name_display, 'display_name' ); ?>><?php esc_html_e( 'Nome de Exibição do WordPress', 'hng-commerce' ); ?></option>
							<option value="first_name" <?php selected( $operator_name_display, 'first_name' ); ?>><?php esc_html_e( 'Primeiro Nome', 'hng-commerce' ); ?></option>
							<option value="nickname" <?php selected( $operator_name_display, 'nickname' ); ?>><?php esc_html_e( 'Apelido', 'hng-commerce' ); ?></option>
							<option value="custom" <?php selected( $operator_name_display, 'custom' ); ?>><?php esc_html_e( 'Nome Personalizado', 'hng-commerce' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Nome Personalizado', 'hng-commerce' ); ?></th>
					<td>
						<input type="text" name="hng_live_chat_operator_custom_name" value="<?php echo esc_attr( $operator_custom_name ); ?>" class="regular-text" />
						<p class="description"><?php esc_html_e( 'Nome usado quando a opção "Nome Personalizado" está selecionada', 'hng-commerce' ); ?></p>
					</td>
				</tr>
			</table>
			
			<h2 style="display: inline-flex; align-items: center;">
				<?php esc_html_e( 'Aparência', 'hng-commerce' ); ?>
				<?php
				echo wp_kses_post(
					hng_admin_tooltip(
						'🎨 Personalização Visual',
						array(
							array(
								'icon'    => '🌈',
								'title'   => 'Cores',
								'content' => 'Personalize a cor do botão, cabeçalho, fundo e texto para combinar com sua marca.',
							),
							array(
								'icon'    => '📍',
								'title'   => 'Posição',
								'content' => 'Escolha onde o widget aparece: canto inferior direito ou esquerdo da tela.',
							),
							array(
								'icon'    => '🔔',
								'title'   => 'Som',
								'content' => 'Ative notificação sonora para alertar o cliente quando o atendente responder.',
							),
							array(
								'icon'    => '🎭',
								'title'   => 'Ícone',
								'content' => 'Escolha o ícone do botão: chat, mensagem, suporte, headset ou WhatsApp.',
							),
						),
						array(
							'title' => '💡 Dica de Design',
							'items' => array(
								array(
									'label' => 'Contraste',
									'text'  => 'Use cor que destaque do fundo do site',
								),
								array(
									'label' => 'Consistência',
									'text'  => 'Use as cores da sua marca',
								),
							),
						)
					)
				);
				?>
			</h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Título do Chat', 'hng-commerce' ); ?></th>
					<td>
						<input type="text" name="hng_live_chat_title" value="<?php echo esc_attr( $title ); ?>" class="regular-text" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Mensagem de Boas-vindas', 'hng-commerce' ); ?></th>
					<td>
						<textarea name="hng_live_chat_welcome_message" rows="3" class="large-text"><?php echo esc_textarea( $welcome_message ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Mensagem Offline', 'hng-commerce' ); ?></th>
					<td>
						<textarea name="hng_live_chat_offline_message" rows="3" class="large-text"><?php echo esc_textarea( $offline_message ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Mensagem quando não há atendentes disponíveis', 'hng-commerce' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Som de Notificação (Cliente)', 'hng-commerce' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="hng_live_chat_sound_enabled" value="yes" <?php checked( $sound_enabled, 'yes' ); ?> />
							<?php esc_html_e( 'Toca som quando cliente recebe mensagem', 'hng-commerce' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Som em Chats Ativos (Atendente)', 'hng-commerce' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="hng_live_chat_admin_sound_active" value="yes" <?php checked( $admin_sound_active, 'yes' ); ?> />
							<?php esc_html_e( 'Toca som quando receber mensagem em chat que já está sendo atendido', 'hng-commerce' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Cores e Posição', 'hng-commerce' ); ?></th>
					<td>
						<p class="description" style="background: #f0f6fc; padding: 12px; border-left: 4px solid #2984f1; margin: 0;">
							<strong>🎨 <?php esc_html_e( 'Configurações visuais centralizadas', 'hng-commerce' ); ?></strong><br>
							<?php
							printf(
								/* translators: %s: Link para a aba de layouts */
								esc_html__( 'As configurações de cores, posição e ícone do chat foram movidas para a aba %s para centralizar todas as opções de layout em um único lugar.', 'hng-commerce' ),
								'<a href="' . esc_url( admin_url( 'admin.php?page=hng-settings&tab=layouts' ) ) . '"><strong>' . esc_html__( 'Layouts', 'hng-commerce' ) . '</strong></a>'
							);
							?>
						</p>
					</td>
				</tr>
			</table>
			
			<h2 style="display: inline-flex; align-items: center;">
				<?php esc_html_e( 'Horário de Atendimento', 'hng-commerce' ); ?>
				<?php
				echo wp_kses_post(
					hng_admin_tooltip(
						'🕒 Horário Comercial',
						array(
							array(
								'icon'    => '📅',
								'title'   => 'Dias da Semana',
								'content' => 'Defina quais dias sua equipe está disponível para atendimento.',
							),
							array(
								'icon'    => '⏰',
								'title'   => 'Horários',
								'content' => 'Configure o horário de início e fim do atendimento para cada dia.',
							),
							array(
								'icon'    => '🌴',
								'title'   => 'Feriados',
								'content' => 'Adicione datas específicas onde o chat ficará indisponível (Natal, Ano Novo, etc).',
							),
							array(
								'icon'    => '💬',
								'title'   => 'Mensagem Offline',
								'content' => 'Texto exibido quando o chat está fora do horário. Informe quando volta a funcionar.',
							),
						),
						array(
							'title' => '⚠️ Importante',
							'items' => array(
								array(
									'label' => 'Fuso horário',
									'text'  => 'Usa o fuso configurado no WordPress',
								),
								array(
									'label' => 'Se desativado',
									'text'  => 'Chat fica disponível 24/7',
								),
							),
						)
					)
				);
				?>
			</h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Ativar Horário de Atendimento', 'hng-commerce' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="hng_live_chat_business_hours_enabled" value="yes" <?php checked( $business_hours_enabled, 'yes' ); ?> />
							<?php esc_html_e( 'Mostrar chat apenas durante o horário de atendimento', 'hng-commerce' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Formato de Horário', 'hng-commerce' ); ?></th>
					<td>
						<select name="hng_live_chat_time_format">
							<option value="24h" <?php selected( $time_format, '24h' ); ?>><?php esc_html_e( '24 horas (14:00)', 'hng-commerce' ); ?></option>
							<option value="12h" <?php selected( $time_format, '12h' ); ?>><?php esc_html_e( '12 horas (2:00 PM)', 'hng-commerce' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Dias e Horários', 'hng-commerce' ); ?></th>
					<td>
						<div class="hng-business-hours-grid" style="display: grid; gap: 8px;">
							<?php
							$days = array(
								'monday'    => __( 'Segunda-feira', 'hng-commerce' ),
								'tuesday'   => __( 'Terça-feira', 'hng-commerce' ),
								'wednesday' => __( 'Quarta-feira', 'hng-commerce' ),
								'thursday'  => __( 'Quinta-feira', 'hng-commerce' ),
								'friday'    => __( 'Sexta-feira', 'hng-commerce' ),
								'saturday'  => __( 'Sábado', 'hng-commerce' ),
								'sunday'    => __( 'Domingo', 'hng-commerce' ),
							);
							foreach ( $days as $day_key => $day_name ) :
								$day_enabled = isset( $business_hours[ $day_key ]['enabled'] ) ? $business_hours[ $day_key ]['enabled'] : ( $day_key !== 'saturday' && $day_key !== 'sunday' );
								$day_start   = isset( $business_hours[ $day_key ]['start'] ) ? $business_hours[ $day_key ]['start'] : '08:00';
								$day_end     = isset( $business_hours[ $day_key ]['end'] ) ? $business_hours[ $day_key ]['end'] : '18:00';
								?>
							<div style="display: flex; align-items: center; gap: 10px; padding: 8px; background: #f9f9f9; border-radius: 4px;">
								<label style="width: 130px; display: flex; align-items: center; gap: 5px;">
									<input type="checkbox" name="hng_live_chat_business_hours[<?php echo esc_attr( $day_key ); ?>][enabled]" value="1" <?php checked( $day_enabled, true ); ?> />
									<?php echo esc_html( $day_name ); ?>
								</label>
								<input type="time" name="hng_live_chat_business_hours[<?php echo esc_attr( $day_key ); ?>][start]" value="<?php echo esc_attr( $day_start ); ?>" style="width: 100px;" />
								<span>até</span>
								<input type="time" name="hng_live_chat_business_hours[<?php echo esc_attr( $day_key ); ?>][end]" value="<?php echo esc_attr( $day_end ); ?>" style="width: 100px;" />
							</div>
							<?php endforeach; ?>
						</div>
						<p class="description" style="margin-top: 10px;"><?php esc_html_e( 'Marque os dias de funcionamento e defina o horário de início e fim.', 'hng-commerce' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Dias Especiais (Não Funcionamento)', 'hng-commerce' ); ?></th>
					<td>
						<textarea name="hng_live_chat_closed_dates" rows="3" class="large-text" placeholder="<?php esc_attr_e( '25/12/2026, 01/01/2027, 21/04/2026', 'hng-commerce' ); ?>"><?php echo esc_textarea( $closed_dates ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Datas em que o chat ficará indisponível (feriados, etc). Formato: DD/MM/AAAA, separadas por vírgula.', 'hng-commerce' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Mensagem Fora do Horário', 'hng-commerce' ); ?></th>
					<td>
						<textarea name="hng_live_chat_outside_hours_message" rows="2" class="large-text"><?php echo esc_textarea( $outside_hours_message ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Mensagem exibida quando o chat está fora do horário de atendimento.', 'hng-commerce' ); ?></p>
					</td>
				</tr>
			</table>
			
			<h2 style="display: inline-flex; align-items: center;">
				<?php esc_html_e( 'Arquivos', 'hng-commerce' ); ?>
				<?php
				echo wp_kses_post(
					hng_admin_tooltip_simple(
						'Configure o envio de arquivos no chat. Defina tamanho máximo e tipos permitidos (jpg, pdf, doc, etc). Arquivos são armazenados em pasta protegida.',
						280
					)
				);
				?>
			</h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Tamanho Máximo (MB)', 'hng-commerce' ); ?></th>
					<td>
						<input type="number" name="hng_live_chat_max_file_size" value="<?php echo esc_attr( $max_file_size ); ?>" min="1" max="50" class="small-text" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Tipos Permitidos', 'hng-commerce' ); ?></th>
					<td>
						<input type="text" name="hng_live_chat_allowed_file_types" value="<?php echo esc_attr( $allowed_file_types ); ?>" class="regular-text" />
						<p class="description"><?php esc_html_e( 'Extensões separadas por vírgula (ex: jpg,png,pdf)', 'hng-commerce' ); ?></p>
					</td>
				</tr>
			</table>
			
			<h2 style="display: inline-flex; align-items: center;">
				<?php esc_html_e( 'Exibição', 'hng-commerce' ); ?>
				<?php
				echo wp_kses_post(
					hng_admin_tooltip_simple(
						'Restrinja o chat a páginas específicas informando os IDs separados por vírgula. Deixe vazio para exibir em todas as páginas.',
						280
					)
				);
				?>
			</h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Exibir em Páginas Específicas', 'hng-commerce' ); ?></th>
					<td>
						<input type="text" name="hng_live_chat_show_on_pages" value="<?php echo esc_attr( $show_on_pages ); ?>" class="regular-text" />
						<p class="description"><?php esc_html_e( 'IDs de páginas separados por vírgula. Deixe vazio para todas.', 'hng-commerce' ); ?></p>
					</td>
				</tr>
			</table>
			
			<?php
			// Notification system settings
			$notification_method = get_option( 'hng_live_chat_notification_method', 'polling' );
			$polling_interval    = get_option( 'hng_live_chat_polling_interval', 3 );
			$sse_timeout         = get_option( 'hng_live_chat_sse_timeout', 30 );
			$websocket_url       = get_option( 'hng_live_chat_websocket_url', '' );
			?>
			
			<h2>
				<?php esc_html_e( 'Sistema de Notificações em Tempo Real', 'hng-commerce' ); ?>
				<span class="hng-help-icon" style="display: inline-flex; align-items: center; justify-content: center; width: 20px; height: 20px; background: #2271b1; color: #fff; border-radius: 50%; font-size: 12px; font-weight: bold; cursor: help; margin-left: 8px; position: relative;">?
					<div class="hng-help-tooltip" style="display: none; position: absolute; top: 30px; left: 50%; transform: translateX(-50%); width: 450px; background: #1d2327; color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); z-index: 9999; font-weight: normal; font-size: 13px; line-height: 1.6; text-align: left;">
						<div style="position: absolute; top: -8px; left: 50%; transform: translateX(-50%); width: 0; height: 0; border-left: 8px solid transparent; border-right: 8px solid transparent; border-bottom: 8px solid #1d2327;"></div>
						
						<h4 style="margin: 0 0 15px 0; color: #72aee6; font-size: 15px; border-bottom: 1px solid #3c434a; padding-bottom: 10px;">📡 Guia Completo de Notificações</h4>
						
						<div style="margin-bottom: 15px;">
							<strong style="color: #72aee6;">🔄 Polling (Padrão)</strong>
							<div style="margin: 5px 0 0 0; padding-left: 10px; border-left: 2px solid #3c434a;">
								<div style="color: #8bc34a;">✅ Prós: Funciona em 100% das hospedagens, simples, confiável</div>
								<div style="color: #ff9800;">⚠️ Contras: Mais requisições ao servidor, delay de 1-30 segundos</div>
							</div>
						</div>
						
						<div style="margin-bottom: 15px;">
							<strong style="color: #72aee6;">⏳ Long Polling</strong>
							<div style="margin: 5px 0 0 0; padding-left: 10px; border-left: 2px solid #3c434a;">
								<div style="color: #8bc34a;">✅ Prós: 70% menos requisições, resposta quase instantânea</div>
								<div style="color: #ff9800;">⚠️ Contras: Pode ter timeout em hospedagens muito restritivas (30s)</div>
							</div>
						</div>
						
						<div style="margin-bottom: 15px;">
							<strong style="color: #72aee6;">📨 Server-Sent Events (SSE)</strong>
							<div style="margin: 5px 0 0 0; padding-left: 10px; border-left: 2px solid #3c434a;">
								<div style="color: #8bc34a;">✅ Prós: Tempo real verdadeiro, muito eficiente, baixo consumo</div>
								<div style="color: #ff9800;">⚠️ Contras: Requer suporte a conexões longas, pode não funcionar em shared hosting básico</div>
							</div>
						</div>
						
						<div style="margin-bottom: 15px;">
							<strong style="color: #72aee6;">🔌 WebSocket (Externo)</strong>
							<div style="margin: 5px 0 0 0; padding-left: 10px; border-left: 2px solid #3c434a;">
								<div style="color: #8bc34a;">✅ Prós: Performance máxima, bidirecional, ideal para alto volume</div>
								<div style="color: #ff9800;">⚠️ Contras: Requer servidor externo (Pusher/$), não funciona em shared hosting</div>
							</div>
						</div>
						
						<div style="background: #2c3338; padding: 12px; border-radius: 6px; margin-top: 15px;">
							<strong style="color: #f0b849; display: block; margin-bottom: 8px;">🏢 Recomendações para Agências:</strong>
							<div style="font-size: 12px;">
								<div style="margin-bottom: 6px;"><strong style="color: #fff;">Muitos sites em Hospedagem Compartilhada:</strong><br>Use <span style="color: #8bc34a; font-weight: bold;">Long Polling</span> - Equilíbrio entre performance e compatibilidade</div>
								<div style="margin-bottom: 6px;"><strong style="color: #fff;">VPS/Cloud (DigitalOcean, AWS, etc):</strong><br>Use <span style="color: #8bc34a; font-weight: bold;">SSE</span> - Melhor performance sem custo adicional</div>
								<div><strong style="color: #fff;">Alto volume (100+ chats simultâneos):</strong><br>Use <span style="color: #8bc34a; font-weight: bold;">WebSocket</span> com Pusher ou servidor dedicado</div>
							</div>
						</div>
					</div>
				</span>
			</h2>
			
			<style>
				.hng-help-icon:hover .hng-help-tooltip {
					display: block !important;
				}
			</style>
			
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Método de Notificação', 'hng-commerce' ); ?></th>
					<td>
						<fieldset>
							<label style="display: block; margin-bottom: 8px;">
								<input type="radio" name="hng_live_chat_notification_method" value="polling" <?php checked( $notification_method, 'polling' ); ?> />
								<strong><?php esc_html_e( 'Polling (Padrão)', 'hng-commerce' ); ?></strong>
								<span style="color: #50575e; margin-left: 5px;"><?php esc_html_e( '- Compatível com todas as hospedagens', 'hng-commerce' ); ?></span>
							</label>
							<label style="display: block; margin-bottom: 8px;">
								<input type="radio" name="hng_live_chat_notification_method" value="long_polling" <?php checked( $notification_method, 'long_polling' ); ?> />
								<strong><?php esc_html_e( 'Long Polling', 'hng-commerce' ); ?></strong>
								<span style="color: #50575e; margin-left: 5px;"><?php esc_html_e( '- Recomendado para hospedagens compartilhadas', 'hng-commerce' ); ?></span>
							</label>
							<label style="display: block; margin-bottom: 8px;">
								<input type="radio" name="hng_live_chat_notification_method" value="sse" <?php checked( $notification_method, 'sse' ); ?> />
								<strong><?php esc_html_e( 'Server-Sent Events (SSE)', 'hng-commerce' ); ?></strong>
								<span style="color: #50575e; margin-left: 5px;"><?php esc_html_e( '- Melhor para VPS/Dedicado', 'hng-commerce' ); ?></span>
							</label>
							<label style="display: block; margin-bottom: 8px;">
								<input type="radio" name="hng_live_chat_notification_method" value="websocket" <?php checked( $notification_method, 'websocket' ); ?> />
								<strong><?php esc_html_e( 'WebSocket (Externo)', 'hng-commerce' ); ?></strong>
								<span style="color: #50575e; margin-left: 5px;"><?php esc_html_e( '- Requer servidor WebSocket configurado', 'hng-commerce' ); ?></span>
							</label>
						</fieldset>
					</td>
				</tr>
				<tr class="hng-polling-option">
					<th scope="row"><?php esc_html_e( 'Intervalo de Polling', 'hng-commerce' ); ?></th>
					<td>
						<input type="number" name="hng_live_chat_polling_interval" value="<?php echo esc_attr( $polling_interval ); ?>" min="1" max="30" class="small-text" /> <?php esc_html_e( 'segundos', 'hng-commerce' ); ?>
						<p class="description"><?php esc_html_e( 'Intervalo entre verificações de novas mensagens (1-30 segundos). Menor = mais rápido, porém mais uso de recursos.', 'hng-commerce' ); ?></p>
					</td>
				</tr>
				<tr class="hng-sse-option">
					<th scope="row"><?php esc_html_e( 'Timeout SSE', 'hng-commerce' ); ?></th>
					<td>
						<input type="number" name="hng_live_chat_sse_timeout" value="<?php echo esc_attr( $sse_timeout ); ?>" min="10" max="120" class="small-text" /> <?php esc_html_e( 'segundos', 'hng-commerce' ); ?>
						<p class="description"><?php esc_html_e( 'Tempo máximo de conexão SSE antes de reconectar (10-120 segundos). Ajuste conforme limite da sua hospedagem.', 'hng-commerce' ); ?></p>
					</td>
				</tr>
				<tr class="hng-websocket-option">
					<th scope="row"><?php esc_html_e( 'URL do WebSocket', 'hng-commerce' ); ?></th>
					<td>
						<input type="url" name="hng_live_chat_websocket_url" value="<?php echo esc_attr( $websocket_url ); ?>" class="regular-text" placeholder="wss://seu-servidor.com:8080" />
						<p class="description"><?php esc_html_e( 'URL completa do servidor WebSocket (ex: wss://chat.seusite.com:8080)', 'hng-commerce' ); ?></p>
					</td>
				</tr>
			</table>
			
			<script>
			jQuery(document).ready(function($) {
				function toggleNotificationOptions() {
					var method = $('input[name="hng_live_chat_notification_method"]:checked').val();
					$('.hng-polling-option').toggle(method === 'polling' || method === 'long_polling');
					$('.hng-sse-option').toggle(method === 'sse');
					$('.hng-websocket-option').toggle(method === 'websocket');
				}
				toggleNotificationOptions();
				$('input[name="hng_live_chat_notification_method"]').on('change', toggleNotificationOptions);
			});
			</script>
			
			<?php submit_button(); ?>
		</div>
		</form>
		
		<form method="post" action="options.php"><!-- Reopen form for other tabs -->
		<?php
	}

	/**
	 * Save settings
	 */
	private function save_settings( $data ) {
		$checkboxes = array(
			'hng_live_chat_enabled',
			'hng_live_chat_allow_guests',
			'hng_live_chat_require_email',
			'hng_live_chat_operator_sound',
			'hng_live_chat_show_operator_photo',
			'hng_live_chat_sound_enabled',
			'hng_live_chat_admin_sound_active',
			'hng_live_chat_business_hours_enabled',
			'hng_live_chat_gradient_enabled',
		);

		foreach ( $checkboxes as $checkbox ) {
			update_option( $checkbox, isset( $data[ $checkbox ] ) ? 'yes' : 'no' );
		}

		// Operators (array)
		$operators = isset( $data['hng_live_chat_operators'] ) ? array_map( 'intval', (array) $data['hng_live_chat_operators'] ) : array();
		update_option( 'hng_live_chat_operators', $operators );
		$this->sync_operator_capabilities( $operators );

		// Text fields
		$text_fields = array(
			'hng_live_chat_operator_name_display',
			'hng_live_chat_operator_custom_name',
			'hng_live_chat_title',
			'hng_live_chat_position',
			'hng_live_chat_primary_color',
			'hng_live_chat_bubble_icon',
			'hng_live_chat_start_button_color',
			'hng_live_chat_button_text_color',
			'hng_live_chat_header_color',
			'hng_live_chat_header_text_color',
			'hng_live_chat_bg_color',
			'hng_live_chat_message_text_color',
			'hng_live_chat_time_format',
			'hng_live_chat_closed_dates',
			'hng_live_chat_allowed_file_types',
			'hng_live_chat_show_on_pages',
			'hng_live_chat_notification_method',
			'hng_live_chat_websocket_url',
			'hng_live_chat_gradient_color1',
			'hng_live_chat_gradient_color2',
			'hng_live_chat_gradient_direction',
			'hng_live_chat_gradient_balance',
			'hng_live_chat_border_color',
			'hng_live_chat_font_family',
			'hng_live_chat_gradient_balance',
		);

		foreach ( $text_fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				update_option( $field, sanitize_text_field( $data[ $field ] ) );
			}
		}

		// Textarea fields
		if ( isset( $data['hng_live_chat_welcome_message'] ) ) {
			update_option( 'hng_live_chat_welcome_message', sanitize_textarea_field( $data['hng_live_chat_welcome_message'] ) );
		}
		if ( isset( $data['hng_live_chat_offline_message'] ) ) {
			update_option( 'hng_live_chat_offline_message', sanitize_textarea_field( $data['hng_live_chat_offline_message'] ) );
		}
		if ( isset( $data['hng_live_chat_outside_hours_message'] ) ) {
			update_option( 'hng_live_chat_outside_hours_message', sanitize_textarea_field( $data['hng_live_chat_outside_hours_message'] ) );
		}
		if ( isset( $data['hng_live_chat_custom_css'] ) ) {
			update_option( 'hng_live_chat_custom_css', sanitize_textarea_field( $data['hng_live_chat_custom_css'] ) );
		}

		// Business hours (array)
		if ( isset( $data['hng_live_chat_business_hours'] ) ) {
			$business_hours = array();
			$days           = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
			foreach ( $days as $day ) {
				if ( isset( $data['hng_live_chat_business_hours'][ $day ] ) ) {
					$business_hours[ $day ] = array(
						'enabled' => isset( $data['hng_live_chat_business_hours'][ $day ]['enabled'] ),
						'start'   => sanitize_text_field( $data['hng_live_chat_business_hours'][ $day ]['start'] ?? '08:00' ),
						'end'     => sanitize_text_field( $data['hng_live_chat_business_hours'][ $day ]['end'] ?? '18:00' ),
					);
				} else {
					$business_hours[ $day ] = array(
						'enabled' => false,
						'start'   => '08:00',
						'end'     => '18:00',
					);
				}
			}
			update_option( 'hng_live_chat_business_hours', $business_hours );
		}

		// Number fields
		if ( isset( $data['hng_live_chat_max_file_size'] ) ) {
			update_option( 'hng_live_chat_max_file_size', max( 1, min( 50, intval( $data['hng_live_chat_max_file_size'] ) ) ) );
		}
		if ( isset( $data['hng_live_chat_polling_interval'] ) ) {
			update_option( 'hng_live_chat_polling_interval', max( 1, min( 30, intval( $data['hng_live_chat_polling_interval'] ) ) ) );
		}
		if ( isset( $data['hng_live_chat_sse_timeout'] ) ) {
			update_option( 'hng_live_chat_sse_timeout', max( 10, min( 120, intval( $data['hng_live_chat_sse_timeout'] ) ) ) );
		}
		if ( isset( $data['hng_live_chat_radius'] ) ) {
			update_option( 'hng_live_chat_radius', max( 0, min( 32, intval( $data['hng_live_chat_radius'] ) ) ) );
		}
		if ( isset( $data['hng_live_chat_font_size'] ) ) {
			update_option( 'hng_live_chat_font_size', max( 10, min( 24, intval( $data['hng_live_chat_font_size'] ) ) ) );
		}
		if ( isset( $data['hng_live_chat_gradient_balance'] ) ) {
			update_option( 'hng_live_chat_gradient_balance', max( 10, min( 90, intval( $data['hng_live_chat_gradient_balance'] ) ) ) );
		}
	}

	/**
	 * Sincronizar capacidade de operador para usuários selecionados
	 */
	private function sync_operator_capabilities( $operator_ids ) {
		$operator_ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $operator_ids ) ) ) );
		$all_users    = get_users(
			array(
				'fields' => array( 'ID' ),
				'number' => -1,
			)
		);

		foreach ( $all_users as $user_obj ) {
			$uid  = intval( $user_obj->ID );
			$user = get_user_by( 'ID', $uid );
			if ( ! $user ) {
				continue;
			}

			if ( in_array( $uid, $operator_ids, true ) ) {
				if ( ! $user->has_cap( 'hng_chat_operator' ) ) {
					$user->add_cap( 'hng_chat_operator' );
				}
			} elseif ( $user->has_cap( 'hng_chat_operator' ) ) {
					$user->remove_cap( 'hng_chat_operator' );
			}
		}
	}

	/**
	 * Verificar se usuário é atendente autorizado
	 */
	private function can_access_operator_features( $user_id = null ) {
		$uid = $user_id ? intval( $user_id ) : get_current_user_id();
		if ( $uid <= 0 ) {
			return false;
		}

		if ( current_user_can( 'manage_options' ) || current_user_can( 'hng_chat_operator' ) ) {
			return true;
		}

		$operators = array_map( 'intval', (array) get_option( 'hng_live_chat_operators', array() ) );
		return in_array( $uid, $operators, true );
	}

	/**
	 * Obter lista de atendentes disponíveis para transferência
	 */
	private function get_transfer_operators() {
		$configured_ids = array_map( 'intval', (array) get_option( 'hng_live_chat_operators', array() ) );
		$users          = get_users(
			array(
				'number'  => -1,
				'orderby' => 'display_name',
				'order'   => 'ASC',
			)
		);

		$operators = array();
		foreach ( (array) $users as $user ) {
			$uid = intval( $user->ID );

			$is_operator = user_can( $uid, 'manage_options' )
				|| user_can( $uid, 'hng_chat_operator' )
				|| in_array( $uid, $configured_ids, true );

			if ( ! $is_operator ) {
				continue;
			}

			$operators[] = array(
				'id'   => $uid,
				'name' => $this->get_operator_display_name( $uid ),
			);
		}

		return $operators;
	}

	/**
	 * Add settings fields
	 */
	public function add_settings_fields( $fields ) {
		$fields['live_chat'] = array(
			// Configurações Gerais
			array(
				'id'    => 'hng_live_chat_section_general',
				'title' => __( 'Configurações Gerais', 'hng-commerce' ),
				'type'  => 'section',
			),
			array(
				'id'          => 'hng_live_chat_enabled',
				'title'       => __( 'Ativar Chat ao Vivo', 'hng-commerce' ),
				'type'        => 'checkbox',
				'default'     => 'no',
				'description' => __( 'Ativa o widget de chat ao vivo no frontend', 'hng-commerce' ),
			),
			array(
				'id'          => 'hng_live_chat_allow_guests',
				'title'       => __( 'Permitir Visitantes', 'hng-commerce' ),
				'type'        => 'checkbox',
				'default'     => 'yes',
				'description' => __( 'Permite que usuários não logados iniciem um chat', 'hng-commerce' ),
			),
			array(
				'id'      => 'hng_live_chat_require_email',
				'title'   => __( 'Exigir Email de Visitantes', 'hng-commerce' ),
				'type'    => 'checkbox',
				'default' => 'no',
			),

			// Atendentes
			array(
				'id'    => 'hng_live_chat_section_operators',
				'title' => __( 'Atendentes', 'hng-commerce' ),
				'type'  => 'section',
			),
			array(
				'id'          => 'hng_live_chat_operators',
				'title'       => __( 'Usuários Atendentes', 'hng-commerce' ),
				'type'        => 'operators',
				'default'     => array(),
				'description' => __( 'Selecione os usuários que podem atender os chats e receber notificações', 'hng-commerce' ),
			),
			array(
				'id'          => 'hng_live_chat_operator_sound',
				'title'       => __( 'Notificação Sonora para Atendentes', 'hng-commerce' ),
				'type'        => 'checkbox',
				'default'     => 'yes',
				'description' => __( 'Toca um som quando há um novo cliente aguardando atendimento', 'hng-commerce' ),
			),
			array(
				'id'          => 'hng_live_chat_show_operator_photo',
				'title'       => __( 'Mostrar Foto do Atendente', 'hng-commerce' ),
				'type'        => 'checkbox',
				'default'     => 'yes',
				'description' => __( 'Exibe a foto do atendente nas mensagens do chat', 'hng-commerce' ),
			),
			array(
				'id'          => 'hng_live_chat_operator_name_display',
				'title'       => __( 'Nome do Atendente', 'hng-commerce' ),
				'type'        => 'select',
				'options'     => array(
					'display_name' => __( 'Nome de Exibição do WordPress', 'hng-commerce' ),
					'first_name'   => __( 'Primeiro Nome', 'hng-commerce' ),
					'nickname'     => __( 'Apelido', 'hng-commerce' ),
					'custom'       => __( 'Nome Personalizado', 'hng-commerce' ),
				),
				'default'     => 'display_name',
				'description' => __( 'Como o nome do atendente aparece para o cliente', 'hng-commerce' ),
			),
			array(
				'id'          => 'hng_live_chat_operator_custom_name',
				'title'       => __( 'Nome Personalizado do Atendente', 'hng-commerce' ),
				'type'        => 'text',
				'default'     => __( 'Atendente', 'hng-commerce' ),
				'description' => __( 'Nome usado quando a opção "Nome Personalizado" está selecionada', 'hng-commerce' ),
			),

			// Aparência
			array(
				'id'    => 'hng_live_chat_section_appearance',
				'title' => __( 'Aparência', 'hng-commerce' ),
				'type'  => 'section',
			),
			array(
				'id'      => 'hng_live_chat_title',
				'title'   => __( 'Título do Chat', 'hng-commerce' ),
				'type'    => 'text',
				'default' => __( 'Atendimento ao Vivo', 'hng-commerce' ),
			),
			array(
				'id'      => 'hng_live_chat_welcome_message',
				'title'   => __( 'Mensagem de Boas-vindas', 'hng-commerce' ),
				'type'    => 'textarea',
				'default' => __( 'Olá! Como podemos ajudá-lo hoje?', 'hng-commerce' ),
			),
			array(
				'id'      => 'hng_live_chat_offline_message',
				'title'   => __( 'Mensagem Offline', 'hng-commerce' ),
				'type'    => 'textarea',
				'default' => __( 'No momento não há atendentes disponíveis. Deixe sua mensagem que retornaremos em breve.', 'hng-commerce' ),
			),
			array(
				'id'      => 'hng_live_chat_position',
				'title'   => __( 'Posição do Widget', 'hng-commerce' ),
				'type'    => 'select',
				'options' => array(
					'bottom-right' => __( 'Inferior Direito', 'hng-commerce' ),
					'bottom-left'  => __( 'Inferior Esquerdo', 'hng-commerce' ),
				),
				'default' => 'bottom-right',
			),
			array(
				'id'          => 'hng_live_chat_primary_color',
				'title'       => __( 'Cor Principal', 'hng-commerce' ),
				'type'        => 'text',
				'default'     => '#2984f1',
				'description' => __( 'Código hex da cor (ex: #2984f1)', 'hng-commerce' ),
			),

			// Gradient colors section
			array(
				'id'    => 'hng_live_chat_section_gradient',
				'title' => __( 'Degradê (Gradient)', 'hng-commerce' ),
				'type'  => 'section',
			),
			array(
				'id'          => 'hng_live_chat_gradient_enabled',
				'title'       => __( 'Ativar Degradê', 'hng-commerce' ),
				'type'        => 'checkbox',
				'default'     => 'no',
				'description' => __( 'Usa cores em degradê no botão do chat e cabeçalho ao invés de cor sólida', 'hng-commerce' ),
			),
			array(
				'id'          => 'hng_live_chat_gradient_color1',
				'title'       => __( 'Cor Inicial do Degradê', 'hng-commerce' ),
				'type'        => 'text',
				'default'     => '#2984f1',
				'description' => __( 'Primeira cor do degradê (ex: #2984f1)', 'hng-commerce' ),
			),
			array(
				'id'          => 'hng_live_chat_gradient_color2',
				'title'       => __( 'Cor Final do Degradê', 'hng-commerce' ),
				'type'        => 'text',
				'default'     => '#7c3aed',
				'description' => __( 'Segunda cor do degradê (ex: #7c3aed)', 'hng-commerce' ),
			),
			array(
				'id'          => 'hng_live_chat_gradient_direction',
				'title'       => __( 'Direção do Degradê', 'hng-commerce' ),
				'type'        => 'select',
				'options'     => array(
					'0deg'   => __( '↑ Para Cima (0°)', 'hng-commerce' ),
					'45deg'  => __( '↗ Diagonal Superior Direita (45°)', 'hng-commerce' ),
					'90deg'  => __( '→ Para Direita (90°)', 'hng-commerce' ),
					'135deg' => __( '↘ Diagonal Inferior Direita (135°)', 'hng-commerce' ),
					'180deg' => __( '↓ Para Baixo (180°)', 'hng-commerce' ),
					'225deg' => __( '↙ Diagonal Inferior Esquerda (225°)', 'hng-commerce' ),
					'270deg' => __( '← Para Esquerda (270°)', 'hng-commerce' ),
					'315deg' => __( '↖ Diagonal Superior Esquerda (315°)', 'hng-commerce' ),
				),
				'default'     => '135deg',
				'description' => __( 'Direção/ângulo do gradiente de cores', 'hng-commerce' ),
			),
			array(
				'id'      => 'hng_live_chat_bubble_icon',
				'title'   => __( 'Ícone do Botão', 'hng-commerce' ),
				'type'    => 'select',
				'options' => array(
					'chat'     => __( '💬 Chat', 'hng-commerce' ),
					'message'  => __( '✉️ Mensagem', 'hng-commerce' ),
					'support'  => __( '🎧 Suporte', 'hng-commerce' ),
					'headset'  => __( '🎤 Headset', 'hng-commerce' ),
					'whatsapp' => __( '📱 WhatsApp', 'hng-commerce' ),
					'help'     => __( '❓ Ajuda', 'hng-commerce' ),
				),
				'default' => 'chat',
			),
			array(
				'id'          => 'hng_live_chat_sound_enabled',
				'title'       => __( 'Som de Notificação (Cliente)', 'hng-commerce' ),
				'type'        => 'checkbox',
				'default'     => 'yes',
				'description' => __( 'Toca um som quando o cliente recebe uma mensagem', 'hng-commerce' ),
			),
			array(
				'id'          => 'hng_live_chat_admin_sound_active',
				'title'       => __( 'Som em Chats Ativos (Atendente)', 'hng-commerce' ),
				'type'        => 'checkbox',
				'default'     => 'yes',
				'description' => __( 'Toca som quando receber mensagem em chat ativo', 'hng-commerce' ),
			),

			// Arquivos
			array(
				'id'    => 'hng_live_chat_section_files',
				'title' => __( 'Arquivos', 'hng-commerce' ),
				'type'  => 'section',
			),
			array(
				'id'      => 'hng_live_chat_max_file_size',
				'title'   => __( 'Tamanho Máximo de Arquivo (MB)', 'hng-commerce' ),
				'type'    => 'number',
				'default' => 5,
			),
			array(
				'id'          => 'hng_live_chat_allowed_file_types',
				'title'       => __( 'Tipos de Arquivo Permitidos', 'hng-commerce' ),
				'type'        => 'text',
				'default'     => 'jpg,jpeg,png,gif,pdf,doc,docx,txt',
				'description' => __( 'Extensões separadas por vírgula', 'hng-commerce' ),
			),

			// Exibição
			array(
				'id'    => 'hng_live_chat_section_display',
				'title' => __( 'Exibição', 'hng-commerce' ),
				'type'  => 'section',
			),
			array(
				'id'          => 'hng_live_chat_show_on_pages',
				'title'       => __( 'Exibir em Páginas Específicas', 'hng-commerce' ),
				'type'        => 'text',
				'default'     => '',
				'description' => __( 'IDs das páginas separados por vírgula. Deixe vazio para exibir em todas.', 'hng-commerce' ),
			),
		);

		return $fields;
	}

	/**
	 * Get bubble icon SVG
	 */
	public function get_bubble_icon_svg( $icon = 'chat' ) {
		$icons = array(
			'chat'     => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/><path d="M7 9h10v2H7zm0-3h10v2H7z"/></svg>',
			'message'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>',
			'support'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H8c0-2.21 1.79-4 4-4s4 1.79 4 4c0 .88-.36 1.68-.93 2.25z"/></svg>',
			'headset'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white"><path d="M12 1c-4.97 0-9 4.03-9 9v7c0 1.66 1.34 3 3 3h3v-8H5v-2c0-3.87 3.13-7 7-7s7 3.13 7 7v2h-4v8h3c1.66 0 3-1.34 3-3v-7c0-4.97-4.03-9-9-9z"/></svg>',
			'whatsapp' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>',
			'help'     => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H8c0-2.21 1.79-4 4-4s4 1.79 4 4c0 .88-.36 1.68-.93 2.25z"/></svg>',
		);

		return isset( $icons[ $icon ] ) ? $icons[ $icon ] : $icons['chat'];
	}

	/**
	 * Get operator display name based on settings
	 */
	public function get_operator_display_name( $operator_id ) {
		$display_type = get_option( 'hng_live_chat_operator_name_display', 'display_name' );
		$user         = get_user_by( 'ID', $operator_id );

		if ( ! $user ) {
			return __( 'Atendente', 'hng-commerce' );
		}

		switch ( $display_type ) {
			case 'first_name':
				$name = $user->first_name;
				return ! empty( $name ) ? $name : $user->display_name;

			case 'nickname':
				$name = $user->nickname;
				return ! empty( $name ) ? $name : $user->display_name;

			case 'custom':
				return get_option( 'hng_live_chat_operator_custom_name', __( 'Atendente', 'hng-commerce' ) );

			default:
				return $user->display_name;
		}
	}

	/**
	 * Check if current time is within business hours
	 */
	public function is_within_business_hours() {
		// If business hours not enabled, always return true
		if ( get_option( 'hng_live_chat_business_hours_enabled', 'no' ) !== 'yes' ) {
			return true;
		}

		// Get current time in WP timezone
		$wp_timezone = wp_timezone();
		$now         = new DateTime( 'now', $wp_timezone );

		// Check closed dates
		$closed_dates = get_option( 'hng_live_chat_closed_dates', '' );
		if ( ! empty( $closed_dates ) ) {
			$dates = array_map( 'trim', explode( ',', $closed_dates ) );
			$today = $now->format( 'd/m/Y' );
			if ( in_array( $today, $dates, true ) ) {
				return false;
			}
		}

		// Get day of week
		$day_map     = array(
			1 => 'monday',
			2 => 'tuesday',
			3 => 'wednesday',
			4 => 'thursday',
			5 => 'friday',
			6 => 'saturday',
			0 => 'sunday',
		);
		$current_day = $day_map[ (int) $now->format( 'w' ) ];

		// Get business hours
		$business_hours = get_option( 'hng_live_chat_business_hours', array() );

		// Check if day is enabled
		if ( ! isset( $business_hours[ $current_day ] ) || ! $business_hours[ $current_day ]['enabled'] ) {
			return false;
		}

		// Check time range
		$current_time = $now->format( 'H:i' );
		$start_time   = $business_hours[ $current_day ]['start'] ?? '08:00';
		$end_time     = $business_hours[ $current_day ]['end'] ?? '18:00';

		return ( $current_time >= $start_time && $current_time <= $end_time );
	}

	/**
	 * Check if user is a chat operator
	 */
	public function is_operator( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return false;
		}

		// Admins are always operators
		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		$operators = get_option( 'hng_live_chat_operators', array() );
		return in_array( $user_id, (array) $operators, true );
	}

	/**
	 * Get all operators
	 */
	public function get_operators() {
		$operator_ids = get_option( 'hng_live_chat_operators', array() );
		$operators    = array();

		// Add admins
		$admins = get_users( array( 'role' => 'administrator' ) );
		foreach ( $admins as $admin ) {
			$operators[ $admin->ID ] = array(
				'id'       => $admin->ID,
				'name'     => $admin->display_name,
				'email'    => $admin->user_email,
				'avatar'   => get_avatar_url( $admin->ID, array( 'size' => 40 ) ),
				'is_admin' => true,
			);
		}

		// Add configured operators
		foreach ( (array) $operator_ids as $user_id ) {
			if ( ! isset( $operators[ $user_id ] ) ) {
				$user = get_user_by( 'ID', $user_id );
				if ( $user ) {
					$operators[ $user_id ] = array(
						'id'       => $user->ID,
						'name'     => $user->display_name,
						'email'    => $user->user_email,
						'avatar'   => get_avatar_url( $user->ID, array( 'size' => 40 ) ),
						'is_admin' => false,
					);
				}
			}
		}

		return $operators;
	}

	/**
	 * Frontend scripts
	 */
	public function frontend_scripts() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		// Check page restrictions
		$allowed_pages = get_option( 'hng_live_chat_show_on_pages', '' );
		if ( ! empty( $allowed_pages ) ) {
			$page_ids = array_map( 'trim', explode( ',', $allowed_pages ) );
			if ( ! is_page( $page_ids ) ) {
				return;
			}
		}

		wp_enqueue_style(
			'hng-live-chat',
			HNG_COMMERCE_URL . 'assets/css/live-chat.css',
			array(),
			HNG_COMMERCE_VERSION
		);

		wp_enqueue_script(
			'hng-live-chat',
			HNG_COMMERCE_URL . 'assets/js/live-chat.js',
			array( 'jquery' ),
			HNG_COMMERCE_VERSION,
			true
		);

		// Get current user data
		$user_data = array();
		if ( is_user_logged_in() ) {
			$user      = wp_get_current_user();
			$user_data = array(
				'id'     => $user->ID,
				'name'   => $user->display_name,
				'email'  => $user->user_email,
				'avatar' => get_avatar_url( $user->ID, array( 'size' => 40 ) ),
			);
		}

		wp_localize_script(
			'hng-live-chat',
			'hngLiveChatConfig',
			array(
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'hng_live_chat' ),
				'isLoggedIn'      => is_user_logged_in(),
				'userData'        => $user_data,
				'settings'        => array(
					'title'                 => get_option( 'hng_live_chat_title', __( 'Atendimento ao Vivo', 'hng-commerce' ) ),
					'welcomeMessage'        => get_option( 'hng_live_chat_welcome_message', __( 'Olá! Como podemos ajudá-lo hoje?', 'hng-commerce' ) ),
					'offlineMessage'        => get_option( 'hng_live_chat_offline_message', '' ),
					'position'              => get_option( 'hng_live_chat_position', 'bottom-right' ),
					'primaryColor'          => get_option( 'hng_live_chat_primary_color', '#2984f1' ),
					'bubbleIcon'            => get_option( 'hng_live_chat_bubble_icon', 'chat' ),
					'startButtonColor'      => get_option( 'hng_live_chat_start_button_color', '' ),
					'buttonTextColor'       => get_option( 'hng_live_chat_button_text_color', '#ffffff' ),
					'headerColor'           => get_option( 'hng_live_chat_header_color', '' ),
					'headerTextColor'       => get_option( 'hng_live_chat_header_text_color', '#ffffff' ),
					'chatBgColor'           => get_option( 'hng_live_chat_bg_color', '#ffffff' ),
					'messageTextColor'      => get_option( 'hng_live_chat_message_text_color', '#333333' ),
					'gradientEnabled'       => filter_var( get_option( 'hng_live_chat_gradient_enabled', false ), FILTER_VALIDATE_BOOLEAN ),
					'gradientColor1'        => get_option( 'hng_live_chat_gradient_color1', '' ),
					'gradientColor2'        => get_option( 'hng_live_chat_gradient_color2', '' ),
					'gradientDirection'     => get_option( 'hng_live_chat_gradient_direction', '135deg' ),
					'gradientBalance'       => intval( get_option( 'hng_live_chat_gradient_balance', 50 ) ),
					'borderColor'           => get_option( 'hng_live_chat_border_color', '#e5e7eb' ),
					'chatRadius'            => absint( get_option( 'hng_live_chat_radius', 16 ) ),
					'chatFontFamily'        => get_option( 'hng_live_chat_font_family', '' ),
					'chatFontSize'          => absint( get_option( 'hng_live_chat_font_size', 14 ) ),
					'soundEnabled'          => get_option( 'hng_live_chat_sound_enabled', 'yes' ) === 'yes',
					'allowGuests'           => $this->allow_guests(),
					'requireEmail'          => get_option( 'hng_live_chat_require_email', 'no' ) === 'yes',
					'maxFileSize'           => intval( get_option( 'hng_live_chat_max_file_size', 5 ) ) * 1024 * 1024,
					'allowedFileTypes'      => get_option( 'hng_live_chat_allowed_file_types', 'jpg,jpeg,png,gif,pdf,doc,docx,txt' ),
					'businessHoursEnabled'  => get_option( 'hng_live_chat_business_hours_enabled', 'no' ) === 'yes',
					'outsideHoursMessage'   => get_option( 'hng_live_chat_outside_hours_message', __( 'Nosso atendimento funciona de segunda a sexta, das 8h às 18h.', 'hng-commerce' ) ),
					'isWithinBusinessHours' => $this->is_within_business_hours(),
					// Notification system settings
					'notificationMethod'    => get_option( 'hng_live_chat_notification_method', 'polling' ),
					'pollingInterval'       => intval( get_option( 'hng_live_chat_polling_interval', 3 ) ) * 1000, // Convert to milliseconds
					'sseTimeout'            => intval( get_option( 'hng_live_chat_sse_timeout', 30 ) ),
					'websocketUrl'          => get_option( 'hng_live_chat_websocket_url', '' ),
				),
				'i18n'            => array(
					'startChat'       => __( 'Iniciar Chat', 'hng-commerce' ),
					'sendMessage'     => __( 'Enviar', 'hng-commerce' ),
					'typeMessage'     => __( 'Digite sua mensagem...', 'hng-commerce' ),
					'yourName'        => __( 'Seu nome', 'hng-commerce' ),
					'yourEmail'       => __( 'Seu email', 'hng-commerce' ),
					'subject'         => __( 'Assunto', 'hng-commerce' ),
					'connecting'      => __( 'Conectando...', 'hng-commerce' ),
					'waitingOperator' => __( 'Aguardando atendente...', 'hng-commerce' ),
					'operatorJoined'  => __( 'entrou no chat', 'hng-commerce' ),
					'chatEnded'       => __( 'Chat encerrado', 'hng-commerce' ),
					'sendFile'        => __( 'Enviar arquivo', 'hng-commerce' ),
					'uploading'       => __( 'Enviando...', 'hng-commerce' ),
					'endChat'         => __( 'Encerrar chat', 'hng-commerce' ),
					'rateChat'        => __( 'Avalie o atendimento', 'hng-commerce' ),
					'thanks'          => __( 'Obrigado pelo feedback!', 'hng-commerce' ),
					'error'           => __( 'Erro ao enviar mensagem', 'hng-commerce' ),
					'fileTooLarge'    => __( 'Arquivo muito grande', 'hng-commerce' ),
					'fileNotAllowed'  => __( 'Tipo de arquivo não permitido', 'hng-commerce' ),
					'isTyping'        => __( 'está digitando...', 'hng-commerce' ),
				),
				'operatorsOnline' => $this->get_online_operators_count(),
			)
		);
	}

	/**
	 * Admin scripts
	 */
	public function admin_scripts( $hook ) {
		// Check for live chat page - various possible hook formats
		if ( strpos( $hook, 'hng-live-chat' ) === false ) {
			return;
		}

		// Set operator online
		$this->set_operator_online();

		wp_enqueue_style(
			'hng-live-chat-admin',
			HNG_COMMERCE_URL . 'assets/css/live-chat-admin.css',
			array(),
			HNG_COMMERCE_VERSION
		);

		wp_enqueue_script(
			'hng-live-chat-admin',
			HNG_COMMERCE_URL . 'assets/js/live-chat-admin.js',
			array( 'jquery', 'heartbeat' ),
			HNG_COMMERCE_VERSION,
			true
		);

		wp_localize_script(
			'hng-live-chat-admin',
			'hngLiveChatAdmin',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'hng_live_chat_admin' ),
				'currentUserId'    => get_current_user_id(),
				'currentUserName'  => wp_get_current_user()->display_name,
				'operators'        => $this->get_transfer_operators(),
				'soundUrl'         => HNG_COMMERCE_URL . 'assets/sounds/notification.mp3',
				'soundActiveChats' => get_option( 'hng_live_chat_admin_sound_active', 'yes' ) === 'yes',
				'i18n'             => array(
					'noSessions'      => __( 'Nenhuma conversa ativa', 'hng-commerce' ),
					'waiting'         => __( 'Aguardando', 'hng-commerce' ),
					'active'          => __( 'Ativo', 'hng-commerce' ),
					'closed'          => __( 'Encerrado', 'hng-commerce' ),
					'guest'           => __( 'Visitante', 'hng-commerce' ),
					'accept'          => __( 'Aceitar', 'hng-commerce' ),
					'close'           => __( 'Encerrar', 'hng-commerce' ),
					'transfer'        => __( 'Transferir', 'hng-commerce' ),
					'selectOperator'  => __( 'Selecione um atendente', 'hng-commerce' ),
					'transferSuccess' => __( 'Atendimento transferido com sucesso.', 'hng-commerce' ),
					'typeMessage'     => __( 'Digite sua mensagem...', 'hng-commerce' ),
					'send'            => __( 'Enviar', 'hng-commerce' ),
					'selectChat'      => __( 'Selecione uma conversa', 'hng-commerce' ),
					'userInfo'        => __( 'Informações do Usuário', 'hng-commerce' ),
					'sessionInfo'     => __( 'Informações da Sessão', 'hng-commerce' ),
				),
			)
		);
	}

	/**
	 * Enqueue global admin notifier for operators
	 */
	public function admin_global_notification_scripts( $hook ) {
		if ( get_option( 'hng_live_chat_enabled', 'no' ) !== 'yes' ) {
			return;
		}

		if ( ! $this->can_access_operator_features() ) {
			return;
		}

		// Evitar duplicar no console principal do chat
		if ( strpos( (string) $hook, 'hng-live-chat' ) !== false ) {
			return;
		}

		wp_enqueue_script(
			'hng-live-chat-global-notify',
			HNG_COMMERCE_URL . 'assets/js/live-chat-global-notify.js',
			array( 'jquery' ),
			HNG_COMMERCE_VERSION,
			true
		);

		wp_localize_script(
			'hng-live-chat-global-notify',
			'hngLiveChatGlobalNotify',
			array(
				'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
				'nonce'              => wp_create_nonce( 'hng_live_chat_admin' ),
				'soundUrl'           => HNG_COMMERCE_URL . 'assets/sounds/notification.mp3',
				'enabled'            => get_option( 'hng_live_chat_admin_sound_active', 'yes' ) === 'yes',
				'notificationMethod' => get_option( 'hng_live_chat_notification_method', 'polling' ),
				'pollingInterval'    => intval( get_option( 'hng_live_chat_polling_interval', 3 ) ) * 1000,
				'sseTimeout'         => intval( get_option( 'hng_live_chat_sse_timeout', 30 ) ),
				'websocketUrl'       => get_option( 'hng_live_chat_websocket_url', '' ),
				'i18n'               => array(
					'newMessage' => __( 'Nova mensagem no chat', 'hng-commerce' ),
					'newWaiting' => __( 'Novo cliente aguardando atendimento', 'hng-commerce' ),
					'openChat'   => __( 'Abrir Chat', 'hng-commerce' ),
				),
			)
		);
	}

	/**
	 * Obter resumo de notificações para atendente
	 */
	private function get_notifications_summary() {
		global $wpdb;

		$waiting_count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->sessions_table} WHERE status = 'waiting'"
		);

		$unread_count = (int) $wpdb->get_var(
			"SELECT COUNT(*)
             FROM {$this->messages_table} m
             INNER JOIN {$this->sessions_table} s ON s.id = m.session_id
             WHERE m.sender_type = 'customer'
               AND m.is_read = 0
               AND s.status IN ('waiting', 'active')"
		);

		$last_activity = (string) $wpdb->get_var(
			"SELECT MAX(last_activity) FROM {$this->sessions_table}"
		);

		$signature = md5( $waiting_count . '|' . $unread_count . '|' . $last_activity );

		return array(
			'waiting_count' => $waiting_count,
			'unread_count'  => $unread_count,
			'last_activity' => $last_activity,
			'signature'     => $signature,
		);
	}

	/**
	 * AJAX Admin: global notifications summary (supports long polling)
	 */
	public function ajax_admin_notifications() {
		check_ajax_referer( 'hng_live_chat_admin', 'nonce' );

		if ( ! $this->can_access_operator_features() ) {
			wp_send_json_error( array( 'message' => __( 'Acesso negado.', 'hng-commerce' ) ) );
		}

		$is_long_poll   = ! empty( $_POST['long_poll'] );
		$last_signature = isset( $_POST['last_signature'] ) ? sanitize_text_field( wp_unslash( $_POST['last_signature'] ) ) : '';

		if ( $is_long_poll ) {
			$start   = time();
			$timeout = max( 10, min( 60, intval( get_option( 'hng_live_chat_sse_timeout', 30 ) ) ) );

			while ( ( time() - $start ) < $timeout ) {
				$summary = $this->get_notifications_summary();
				if ( $summary['signature'] !== $last_signature ) {
					wp_send_json_success( $summary );
				}
				sleep( 2 );
			}
		}

		wp_send_json_success( $this->get_notifications_summary() );
	}

	/**
	 * AJAX Admin: notifications via SSE
	 */
	public function ajax_admin_notifications_sse() {
		if ( ! $this->can_access_operator_features() ) {
			status_header( 403 );
			exit;
		}

		$nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'hng_live_chat_admin' ) ) {
			status_header( 403 );
			exit;
		}

		@set_time_limit( 0 );
		@ini_set( 'zlib.output_compression', 0 );

		nocache_headers();
		header( 'Content-Type: text/event-stream' );
		header( 'Cache-Control: no-cache' );
		header( 'X-Accel-Buffering: no' );

		$last_signature = isset( $_GET['last_signature'] ) ? sanitize_text_field( wp_unslash( $_GET['last_signature'] ) ) : '';
		$timeout        = max( 10, min( 60, intval( get_option( 'hng_live_chat_sse_timeout', 30 ) ) ) );
		$start          = time();

		while ( ( time() - $start ) < $timeout ) {
			$summary = $this->get_notifications_summary();
			if ( $summary['signature'] !== $last_signature ) {
				echo "event: summary\n";
				echo 'data: ' . wp_json_encode( $summary ) . "\n\n";
				if ( function_exists( 'ob_flush' ) ) {
					@ob_flush();
				}
				@flush();
				exit;
			}

			echo ": ping\n\n";
			if ( function_exists( 'ob_flush' ) ) {
				@ob_flush();
			}
			@flush();
			sleep( 2 );
		}

		echo "event: timeout\n";
		echo "data: {}\n\n";
		if ( function_exists( 'ob_flush' ) ) {
			@ob_flush();
		}
		@flush();
		exit;
	}

	/**
	 * Render chat widget in footer
	 */
	public function render_chat_widget() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		// Check page restrictions
		$allowed_pages = get_option( 'hng_live_chat_show_on_pages', '' );
		if ( ! empty( $allowed_pages ) ) {
			$page_ids = array_map( 'trim', explode( ',', $allowed_pages ) );
			if ( ! is_page( $page_ids ) ) {
				return;
			}
		}

		// Check guest permissions
		if ( ! is_user_logged_in() && ! $this->allow_guests() ) {
			return;
		}

		$position      = get_option( 'hng_live_chat_position', 'bottom-right' );
		$primary_color = get_option( 'hng_live_chat_primary_color', '#2984f1' );
		$bubble_icon   = get_option( 'hng_live_chat_bubble_icon', 'chat' );

		// Color settings
		$start_button_color = get_option( 'hng_live_chat_start_button_color', '' );
		$button_text_color  = get_option( 'hng_live_chat_button_text_color', '#ffffff' );
		$header_color       = get_option( 'hng_live_chat_header_color', '' );
		$header_text_color  = get_option( 'hng_live_chat_header_text_color', '#ffffff' );
		$chat_bg_color      = get_option( 'hng_live_chat_bg_color', '#ffffff' );
		$message_text_color = get_option( 'hng_live_chat_message_text_color', '#333333' );

		// Gradient settings
		$gradient_enabled   = filter_var( get_option( 'hng_live_chat_gradient_enabled', false ), FILTER_VALIDATE_BOOLEAN );
		$gradient_color1    = get_option( 'hng_live_chat_gradient_color1', '' );
		$gradient_color2    = get_option( 'hng_live_chat_gradient_color2', '' );
		$gradient_direction = get_option( 'hng_live_chat_gradient_direction', '135deg' );

		// Typography and form settings
		$chat_border_color = get_option( 'hng_live_chat_border_color', '#e5e7eb' );
		$chat_radius       = absint( get_option( 'hng_live_chat_radius', 16 ) );
		$chat_font_family  = get_option( 'hng_live_chat_font_family', '' );
		$chat_font_size    = absint( get_option( 'hng_live_chat_font_size', 14 ) );
		$chat_custom_css   = get_option( 'hng_live_chat_custom_css', '' );

		// Build inline CSS variables for widget
		$style_vars = '--hng-chat-primary: ' . esc_attr( $primary_color ) . ';';
		if ( ! empty( $button_text_color ) ) {
			$style_vars .= ' --hng-chat-btn-text-color: ' . esc_attr( $button_text_color ) . ';';
		}
		if ( ! empty( $header_color ) ) {
			$style_vars .= ' --hng-chat-header-color: ' . esc_attr( $header_color ) . ';';
		}
		if ( ! empty( $header_text_color ) ) {
			$style_vars .= ' --hng-chat-header-text-color: ' . esc_attr( $header_text_color ) . ';';
		}
		if ( ! empty( $start_button_color ) ) {
			$style_vars .= ' --hng-chat-start-btn-color: ' . esc_attr( $start_button_color ) . ';';
		}
		if ( ! empty( $chat_bg_color ) && $chat_bg_color !== '#ffffff' ) {
			$style_vars .= ' --hng-chat-bg: ' . esc_attr( $chat_bg_color ) . ';';
			$style_vars .= ' --hng-chat-bg-light: ' . esc_attr( $chat_bg_color ) . ';';
		}
		if ( ! empty( $message_text_color ) && $message_text_color !== '#333333' ) {
			$style_vars .= ' --hng-chat-text: ' . esc_attr( $message_text_color ) . ';';
			$style_vars .= ' --hng-chat-message-text-color: ' . esc_attr( $message_text_color ) . ';';
		}
		if ( ! empty( $chat_border_color ) && $chat_border_color !== '#e5e7eb' ) {
			$style_vars .= ' --hng-chat-border-color: ' . esc_attr( $chat_border_color ) . ';';
		}
		if ( $chat_radius !== 16 ) {
			$style_vars .= ' --hng-chat-radius: ' . intval( $chat_radius ) . 'px;';
		}
		if ( ! empty( $chat_font_family ) ) {
			$style_vars .= ' --hng-chat-font-family: ' . esc_attr( $chat_font_family ) . ';';
		}
		if ( $chat_font_size && $chat_font_size !== 14 ) {
			$style_vars .= ' --hng-chat-font-size: ' . intval( $chat_font_size ) . 'px;';
		}

		// Build gradient CSS for bubble
		$bubble_gradient_style = '';
		if ( $gradient_enabled && ! empty( $gradient_color1 ) && ! empty( $gradient_color2 ) ) {
			$gradient_css          = 'linear-gradient(' . esc_attr( $gradient_direction ) . ', ' . esc_attr( $gradient_color1 ) . ' 0%, ' . esc_attr( $gradient_color1 ) . ' ' . intval( $gradient_balance ) . '%, ' . esc_attr( $gradient_color2 ) . ' 100%)';
			$style_vars           .= ' --hng-chat-gradient: ' . $gradient_css . ';';
			$bubble_gradient_style = ' style="background: ' . $gradient_css . ' !important;"';
		}

		// Hover effect
		$hover_effect = get_option( 'hng_live_chat_hover_effect', 'none' );
		$effect_class = ( $hover_effect && $hover_effect !== 'none' ) ? ' hng-hover-effect-' . sanitize_key( $hover_effect ) : '';

		// Hover text - use filter_var to ensure boolean conversion regardless of stored type
		$hover_text_enabled = filter_var( get_option( 'hng_live_chat_hover_text_enabled', false ), FILTER_VALIDATE_BOOLEAN );
		$hover_text         = get_option( 'hng_live_chat_hover_text', 'Chat' );
		$hover_text_class   = $hover_text_enabled ? ' hng-chat-hover-text-enabled' : '';

		// Get icon SVG
		$icon_svg = $this->get_bubble_icon_svg( $bubble_icon );

		// Header gradient style
		$header_gradient_style = '';
		if ( $gradient_enabled && ! empty( $gradient_color1 ) && ! empty( $gradient_color2 ) ) {
			$header_gradient_style = ' style="background: ' . $gradient_css . ' !important;"';
		}

		?>
		<div id="hng-live-chat-widget" class="hng-live-chat-widget hng-chat-<?php echo esc_attr( $position ); ?> hng-chat-icon-<?php echo esc_attr( $bubble_icon ); ?>" style="<?php echo $style_vars; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- All values already escaped above ?>">
			<!-- Chat bubble button -->
			<button type="button" class="hng-chat-bubble<?php echo esc_attr( $effect_class . $hover_text_class ); ?>" aria-label="<?php esc_attr_e( 'Abrir chat', 'hng-commerce' ); ?>"<?php echo $bubble_gradient_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<span class="hng-chat-bubble-icon"><?php echo $icon_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icon SVG is safely generated by get_bubble_icon_svg() ?></span>
				<?php if ( $hover_text_enabled && ! empty( $hover_text ) ) : ?>
					<span class="hng-chat-label"><?php echo esc_html( $hover_text ); ?></span>
				<?php endif; ?>
				<span class="hng-chat-badge" style="display: none;">0</span>
			</button>
			
			<!-- Chat window -->
			<div class="hng-chat-window" style="display: none;">
				<div class="hng-chat-header"<?php echo $header_gradient_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<div class="hng-chat-header-info">
						<span class="hng-chat-title"><?php echo esc_html( get_option( 'hng_live_chat_title', __( 'Atendimento ao Vivo', 'hng-commerce' ) ) ); ?></span>
						<span class="hng-chat-status"></span>
					</div>
					<div class="hng-chat-header-actions">
						<button type="button" class="hng-chat-sound-toggle" title="<?php esc_attr_e( 'Ativar/Desativar som', 'hng-commerce' ); ?>">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
								<polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>
								<path class="sound-on" d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"/>
							</svg>
						</button>
						<button type="button" class="hng-chat-minimize" aria-label="<?php esc_attr_e( 'Minimizar', 'hng-commerce' ); ?>">−</button>
						<button type="button" class="hng-chat-close" aria-label="<?php esc_attr_e( 'Fechar', 'hng-commerce' ); ?>">×</button>
					</div>
				</div>
				
				<div class="hng-chat-body">
					<!-- Pre-chat form -->
					<div class="hng-chat-prechat">
						<p class="hng-chat-welcome"><?php echo esc_html( get_option( 'hng_live_chat_welcome_message', __( 'Olá! Como podemos ajudá-lo hoje?', 'hng-commerce' ) ) ); ?></p>
						<form class="hng-chat-prechat-form">
							<?php
							$current_user  = is_user_logged_in() ? wp_get_current_user() : null;
							$prefill_name  = $current_user ? $current_user->display_name : '';
							$prefill_email = $current_user ? $current_user->user_email : '';
							?>
							<div class="hng-chat-field">
								<input type="text" name="guest_name" placeholder="<?php esc_attr_e( 'Seu nome', 'hng-commerce' ); ?>" value="<?php echo esc_attr( $prefill_name ); ?>" required />
							</div>
							<?php if ( get_option( 'hng_live_chat_require_email', 'no' ) === 'yes' || ! is_user_logged_in() ) : ?>
								<div class="hng-chat-field">
									<input type="email" name="guest_email" placeholder="<?php esc_attr_e( 'Seu email', 'hng-commerce' ); ?>" value="<?php echo esc_attr( $prefill_email ); ?>" <?php echo get_option( 'hng_live_chat_require_email', 'no' ) === 'yes' ? 'required' : ''; ?> />
								</div>
							<?php endif; ?>
							<div class="hng-chat-field">
								<input type="text" name="subject" placeholder="<?php esc_attr_e( 'Assunto (opcional)', 'hng-commerce' ); ?>" />
							</div>
							<div class="hng-chat-field">
								<textarea name="initial_message" placeholder="<?php esc_attr_e( 'Como podemos ajudar?', 'hng-commerce' ); ?>" required rows="3"></textarea>
							</div>
							<button type="submit" class="hng-chat-start-btn"><?php esc_html_e( 'Iniciar Chat', 'hng-commerce' ); ?></button>
						</form>
					</div>
					
					<!-- Messages area -->
					<div class="hng-chat-messages" style="display: none;"></div>
					
					<!-- Typing indicator -->
					<div class="hng-chat-typing" style="display: none;">
						<span class="hng-typing-dots"><span></span><span></span><span></span></span>
						<span class="hng-typing-text"></span>
					</div>
				</div>
				
				<div class="hng-chat-footer" style="display: none;">
					<div class="hng-chat-input-container">
						<button type="button" class="hng-chat-attach" aria-label="<?php esc_attr_e( 'Anexar arquivo', 'hng-commerce' ); ?>">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
						</button>
						<input type="file" class="hng-chat-file-input" style="display: none;" />
						<textarea class="hng-chat-input" placeholder="<?php esc_attr_e( 'Digite sua mensagem...', 'hng-commerce' ); ?>" rows="1"></textarea>
						<button type="button" class="hng-chat-send" aria-label="<?php esc_attr_e( 'Enviar', 'hng-commerce' ); ?>">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22,2 15,22 11,13 2,9"/></svg>
						</button>
					</div>
					<div class="hng-chat-end-container">
						<button type="button" class="hng-chat-end-btn">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18"/><path d="M6 6l12 12"/></svg>
							<?php esc_html_e( 'Encerrar chat', 'hng-commerce' ); ?>
						</button>
					</div>
					<div class="hng-chat-restart-container" style="display:none;">
						<button type="button" class="hng-chat-restart-btn">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
							<?php esc_html_e( 'Iniciar novo chat', 'hng-commerce' ); ?>
						</button>
					</div>
				</div>
				
				<!-- Rating overlay -->
				<div class="hng-chat-rating" style="display: none;">
					<h4><?php esc_html_e( 'Avalie o atendimento', 'hng-commerce' ); ?></h4>
					<p class="hng-chat-rating-subtitle"><?php esc_html_e( 'Sua avaliação nos ajuda a melhorar nosso atendimento.', 'hng-commerce' ); ?></p>
					<div class="hng-chat-stars">
						<button type="button" data-rating="1" aria-label="1 estrela">★</button>
						<button type="button" data-rating="2" aria-label="2 estrelas">★</button>
						<button type="button" data-rating="3" aria-label="3 estrelas">★</button>
						<button type="button" data-rating="4" aria-label="4 estrelas">★</button>
						<button type="button" data-rating="5" aria-label="5 estrelas">★</button>
					</div>
					<textarea class="hng-chat-rating-comment" placeholder="<?php esc_attr_e( 'Deixe um comentário (opcional)', 'hng-commerce' ); ?>" rows="3"></textarea>
					<button type="button" class="hng-chat-rating-submit">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px; margin-right: 4px;"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22,2 15,22 11,13 2,9"/></svg>
						<?php esc_html_e( 'Enviar avaliação', 'hng-commerce' ); ?>
					</button>
					<button type="button" class="hng-chat-rating-skip"><?php esc_html_e( 'Pular', 'hng-commerce' ); ?></button>
				</div>
			</div>
		</div>
		<?php
		// Output custom CSS if set.
		if ( ! empty( $chat_custom_css ) ) {
			echo '<style id="hng-live-chat-custom-css">' . wp_strip_all_tags( $chat_custom_css ) . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS is sanitized via sanitize_textarea_field on save
		}
	}

	/**
	 * AJAX handler — start a new live chat session.
	 *
	 * @return void
	 */
	public function ajax_start_chat() {
		check_ajax_referer( 'hng_live_chat', 'nonce' );

		global $wpdb;

		$user_id         = is_user_logged_in() ? get_current_user_id() : null;
		$guest_name      = isset( $_POST['guest_name'] ) ? sanitize_text_field( wp_unslash( $_POST['guest_name'] ) ) : '';
		$guest_email     = isset( $_POST['guest_email'] ) ? sanitize_email( wp_unslash( $_POST['guest_email'] ) ) : '';
		$subject         = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$initial_message = isset( $_POST['initial_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['initial_message'] ) ) : '';
		$page_url        = isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : '';

		// Validate
		if ( ! $user_id && ! $this->allow_guests() ) {
			wp_send_json_error( array( 'message' => __( 'É necessário estar logado para iniciar um chat.', 'hng-commerce' ) ) );
		}

		if ( ! $user_id ) {
			if ( empty( $guest_name ) ) {
				wp_send_json_error( array( 'message' => __( 'Por favor, informe seu nome.', 'hng-commerce' ) ) );
			}
			if ( get_option( 'hng_live_chat_require_email', 'no' ) === 'yes' && empty( $guest_email ) ) {
				wp_send_json_error( array( 'message' => __( 'Por favor, informe seu email.', 'hng-commerce' ) ) );
			}
		}

		// Generate session key and guest token
		$session_key = wp_generate_password( 32, false );
		$guest_token = $user_id ? null : wp_generate_password( 32, false );

		// Get user info if logged in
		if ( $user_id ) {
			$user       = get_user_by( 'ID', $user_id );
			$guest_name = $user->display_name;
			if ( empty( $guest_email ) ) {
				$guest_email = $user->user_email;
			}
		}

		// Create session
		$wpdb->insert(
			$this->sessions_table,
			array(
				'session_key' => $session_key,
				'user_id'     => $user_id,
				'guest_token' => $guest_token,
				'guest_name'  => $guest_name,
				'guest_email' => $guest_email,
				'subject'     => $subject,
				'page_url'    => $page_url,
				'user_ip'     => $this->get_client_ip(),
				'user_agent'  => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
				'status'      => 'waiting',
			)
		);

		$session_id = $wpdb->insert_id;

		if ( ! $session_id ) {
			wp_send_json_error( array( 'message' => __( 'Erro ao iniciar chat.', 'hng-commerce' ) ) );
		}

		// Set guest cookie
		if ( $guest_token ) {
			setcookie( 'hng_chat_guest_token', $guest_token, time() + DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
		}

		// Add initial message
		$last_message_id = 0;
		if ( ! empty( $initial_message ) ) {
			$wpdb->insert(
				$this->messages_table,
				array(
					'session_id'   => $session_id,
					'sender_type'  => 'customer',
					'sender_id'    => $user_id,
					'sender_name'  => $guest_name,
					'message'      => $initial_message,
					'message_type' => 'text',
				)
			);
			$last_message_id = $wpdb->insert_id;
		}

		// Add system message
		$wpdb->insert(
			$this->messages_table,
			array(
				'session_id'   => $session_id,
				'sender_type'  => 'system',
				/* translators: %s: guest name */
				'message'      => sprintf( __( '%s iniciou uma conversa', 'hng-commerce' ), $guest_name ),
				'message_type' => 'system',
			)
		);

		// Get final last message ID (system message)
		$last_message_id = max( $last_message_id, $wpdb->insert_id );

		// Trigger action for notifications
		do_action(
			'hng_live_chat_session_started',
			$session_id,
			array(
				'user_id'     => $user_id,
				'guest_name'  => $guest_name,
				'guest_email' => $guest_email,
				'subject'     => $subject,
			)
		);

		wp_send_json_success(
			array(
				'session_id'      => $session_id,
				'session_key'     => $session_key,
				'guest_token'     => $guest_token,
				'last_message_id' => $last_message_id,
			)
		);
	}

	/**
	 * AJAX: Send message
	 */
	public function ajax_send_message() {
		check_ajax_referer( 'hng_live_chat', 'nonce' );

		global $wpdb;

		$session_key      = isset( $_POST['session_key'] ) ? sanitize_text_field( wp_unslash( $_POST['session_key'] ) ) : '';
		$message          = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		$guest_token_post = isset( $_POST['guest_token'] ) ? sanitize_text_field( wp_unslash( $_POST['guest_token'] ) ) : '';

		if ( empty( $session_key ) || empty( $message ) ) {
			wp_send_json_error( array( 'message' => __( 'Dados inválidos.', 'hng-commerce' ) ) );
		}

		// Get session
		$session = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->sessions_table} WHERE session_key = %s",
				$session_key
			)
		);

		if ( ! $session || $session->status === 'closed' ) {
			wp_send_json_error( array( 'message' => __( 'Sessão não encontrada ou encerrada.', 'hng-commerce' ) ) );
		}

		// Verify ownership
		$user_id     = is_user_logged_in() ? get_current_user_id() : null;
		$guest_token = isset( $_COOKIE['hng_chat_guest_token'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['hng_chat_guest_token'] ) ) : '';
		if ( empty( $guest_token ) && ! empty( $guest_token_post ) ) {
			$guest_token = $guest_token_post;
		}

		if ( $session->user_id && $session->user_id != $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Acesso negado.', 'hng-commerce' ) ) );
		}

		if ( ! $session->user_id && $session->guest_token !== $guest_token ) {
			wp_send_json_error( array( 'message' => __( 'Acesso negado.', 'hng-commerce' ) ) );
		}

		// Get sender name
		$sender_name = $session->guest_name;
		if ( $user_id ) {
			$user        = get_user_by( 'ID', $user_id );
			$sender_name = $user->display_name;
		}

		// Insert message
		$wpdb->insert(
			$this->messages_table,
			array(
				'session_id'   => $session->id,
				'sender_type'  => 'customer',
				'sender_id'    => $user_id,
				'sender_name'  => $sender_name,
				'message'      => $message,
				'message_type' => 'text',
			)
		);

		$message_id = $wpdb->insert_id;

		// Update session activity
		$wpdb->update(
			$this->sessions_table,
			array( 'last_activity' => current_time( 'mysql' ) ),
			array( 'id' => $session->id )
		);

		// Get the message back
		$msg = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->messages_table} WHERE id = %d",
				$message_id
			)
		);

		wp_send_json_success(
			array(
				'message_id' => $message_id,
				'message'    => $this->format_message( $msg ),
			)
		);
	}

	/**
	 * AJAX: Get messages
	 */
	public function ajax_get_messages() {
		check_ajax_referer( 'hng_live_chat', 'nonce' );

		global $wpdb;

		$session_key = isset( $_POST['session_key'] ) ? sanitize_text_field( wp_unslash( $_POST['session_key'] ) ) : '';
		$last_id     = intval( $_POST['last_id'] ?? 0 );

		if ( empty( $session_key ) ) {
			wp_send_json_error( array( 'message' => __( 'Sessão inválida.', 'hng-commerce' ) ) );
		}

		// Get session
		$session = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->sessions_table} WHERE session_key = %s",
				$session_key
			)
		);

		if ( ! $session ) {
			wp_send_json_error( array( 'message' => __( 'Sessão não encontrada.', 'hng-commerce' ) ) );
		}

		// Get new messages
		$messages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->messages_table} 
            WHERE session_id = %d AND id > %d 
            ORDER BY created_at ASC",
				$session->id,
				$last_id
			)
		);

		// Mark as read
		if ( ! empty( $messages ) ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$this->messages_table} 
                SET is_read = 1 
                WHERE session_id = %d AND sender_type = 'operator' AND is_read = 0",
					$session->id
				)
			);
		}

		$formatted = array();
		foreach ( $messages as $msg ) {
			$formatted[] = $this->format_message( $msg );
		}

		wp_send_json_success(
			array(
				'messages'       => $formatted,
				'session_status' => $session->status,
				'operator_name'  => $session->operator_id ? get_user_by( 'ID', $session->operator_id )->display_name : null,
			)
		);
	}

	/**
	 * AJAX: Long Polling for messages
	 * Keeps connection open until new messages arrive or timeout
	 */
	public function ajax_long_poll() {
		check_ajax_referer( 'hng_live_chat', 'nonce' );

		global $wpdb;

		$session_key = isset( $_POST['session_key'] ) ? sanitize_text_field( wp_unslash( $_POST['session_key'] ) ) : '';
		$last_id     = intval( $_POST['last_id'] ?? 0 );
		$timeout     = intval( get_option( 'hng_live_chat_polling_interval', 3 ) );
		$max_wait    = min( 25, max( 5, $timeout * 5 ) ); // Max wait time based on polling interval

		if ( empty( $session_key ) ) {
			wp_send_json_error( array( 'message' => __( 'Sessão inválida.', 'hng-commerce' ) ) );
		}

		// Get session
		$session = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->sessions_table} WHERE session_key = %s",
				$session_key
			)
		);

		if ( ! $session ) {
			wp_send_json_error( array( 'message' => __( 'Sessão não encontrada.', 'hng-commerce' ) ) );
		}

		// Long polling loop
		$start_time = time();
		$messages   = array();

		while ( ( time() - $start_time ) < $max_wait ) {
			// Check for new messages
			$messages = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$this->messages_table} 
                WHERE session_id = %d AND id > %d 
                ORDER BY created_at ASC",
					$session->id,
					$last_id
				)
			);

			if ( ! empty( $messages ) ) {
				break;
			}

			// Check if session closed
			$session = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT status FROM {$this->sessions_table} WHERE id = %d",
					$session->id
				)
			);

			if ( $session && $session->status === 'closed' ) {
				break;
			}

			// Sleep for 1 second before checking again
			usleep( 500000 ); // 0.5 seconds
		}

		// Mark as read
		if ( ! empty( $messages ) ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$this->messages_table} 
                SET is_read = 1 
                WHERE session_id = %d AND sender_type = 'operator' AND is_read = 0",
					$session->id
				)
			);
		}

		$formatted = array();
		foreach ( $messages as $msg ) {
			$formatted[] = $this->format_message( $msg );
		}

		// Reload session for latest status
		$session = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->sessions_table} WHERE session_key = %s",
				$session_key
			)
		);

		wp_send_json_success(
			array(
				'messages'       => $formatted,
				'session_status' => $session ? $session->status : 'closed',
				'operator_name'  => ( $session && $session->operator_id ) ? get_user_by( 'ID', $session->operator_id )->display_name : null,
			)
		);
	}

	/**
	 * AJAX: Server-Sent Events stream
	 * Maintains persistent connection with real-time updates
	 */
	public function ajax_sse_stream() {
		// Verify nonce via GET parameter for SSE
		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'hng_live_chat' ) ) {
			header( 'HTTP/1.1 403 Forbidden' );
			exit( 'Invalid nonce' );
		}

		global $wpdb;

		$session_key = isset( $_GET['session_key'] ) ? sanitize_text_field( wp_unslash( $_GET['session_key'] ) ) : '';
		$last_id     = intval( $_GET['last_id'] ?? 0 );
		$timeout     = intval( get_option( 'hng_live_chat_sse_timeout', 30 ) );

		if ( empty( $session_key ) ) {
			header( 'HTTP/1.1 400 Bad Request' );
			exit( 'Invalid session' );
		}

		// Get session
		$session = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->sessions_table} WHERE session_key = %s",
				$session_key
			)
		);

		if ( ! $session ) {
			header( 'HTTP/1.1 404 Not Found' );
			exit( 'Session not found' );
		}

		// Set SSE headers
		header( 'Content-Type: text/event-stream' );
		header( 'Cache-Control: no-cache' );
		header( 'Connection: keep-alive' );
		header( 'X-Accel-Buffering: no' ); // Disable nginx buffering

		// Disable output buffering
		if ( function_exists( 'apache_setenv' ) ) {
			@apache_setenv( 'no-gzip', '1' );
		}
		@ini_set( 'zlib.output_compression', '0' );
		@ini_set( 'implicit_flush', '1' );

		while ( ob_get_level() > 0 ) {
			ob_end_flush();
		}
		ob_implicit_flush( true );

		// Send initial connection event
		echo "event: connected\n";
		echo 'data: ' . wp_json_encode(
			array(
				'status'      => 'connected',
				'session_key' => $session_key,
			)
		) . "\n\n";

		if ( ob_get_length() ) {
			ob_flush();
		}
		flush();

		$start_time      = time();
		$current_last_id = $last_id;

		// SSE loop
		while ( ( time() - $start_time ) < $timeout ) {
			// Check for new messages
			$messages = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$this->messages_table} 
                WHERE session_id = %d AND id > %d 
                ORDER BY created_at ASC",
					$session->id,
					$current_last_id
				)
			);

			if ( ! empty( $messages ) ) {
				// Mark as read
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$this->messages_table} 
                    SET is_read = 1 
                    WHERE session_id = %d AND sender_type = 'operator' AND is_read = 0",
						$session->id
					)
				);

				$formatted = array();
				foreach ( $messages as $msg ) {
					$formatted[]     = $this->format_message( $msg );
					$current_last_id = max( $current_last_id, $msg->id );
				}

				echo "event: messages\n";
				echo 'data: ' . wp_json_encode( array( 'messages' => $formatted ) ) . "\n\n";

				if ( ob_get_length() ) {
					ob_flush();
				}
				flush();
			}

			// Check session status
			$session = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$this->sessions_table} WHERE id = %d",
					$session->id
				)
			);

			if ( $session && $session->status === 'closed' ) {
				echo "event: session_closed\n";
				echo 'data: ' . wp_json_encode( array( 'status' => 'closed' ) ) . "\n\n";

				if ( ob_get_length() ) {
					ob_flush();
				}
				flush();
				break;
			}

			// Check operator typing
			$is_typing = get_transient( 'hng_chat_typing_' . $session_key . '_operator' );
			if ( $is_typing && ( time() - $is_typing ) < 5 ) {
				echo "event: operator_typing\n";
				echo 'data: ' . wp_json_encode( array( 'typing' => true ) ) . "\n\n";

				if ( ob_get_length() ) {
					ob_flush();
				}
				flush();
			}

			// Send heartbeat to keep connection alive
			echo ": heartbeat\n\n";
			if ( ob_get_length() ) {
				ob_flush();
			}
			flush();

			// Sleep for 1 second
			sleep( 1 );

			// Check if connection is still alive
			if ( connection_aborted() ) {
				break;
			}
		}

		// Send reconnect event
		echo "event: reconnect\n";
		echo 'data: ' . wp_json_encode( array( 'last_id' => $current_last_id ) ) . "\n\n";

		if ( ob_get_length() ) {
			ob_flush();
		}
		flush();

		exit;
	}

	/**
	 * AJAX: Upload file
	 */
	public function ajax_upload_file() {
		check_ajax_referer( 'hng_live_chat', 'nonce' );

		global $wpdb;

		$session_key = isset( $_POST['session_key'] ) ? sanitize_text_field( wp_unslash( $_POST['session_key'] ) ) : '';

		if ( empty( $session_key ) || empty( $_FILES['file'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Dados inválidos.', 'hng-commerce' ) ) );
		}

		// Get session
		$session = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->sessions_table} WHERE session_key = %s",
				$session_key
			)
		);

		if ( ! $session || $session->status === 'closed' ) {
			wp_send_json_error( array( 'message' => __( 'Sessão não encontrada ou encerrada.', 'hng-commerce' ) ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File handled via move_uploaded_file.
		$file = $_FILES['file'];

		// Validate file size
		$max_size = intval( get_option( 'hng_live_chat_max_file_size', 5 ) ) * 1024 * 1024;
		if ( $file['size'] > $max_size ) {
			wp_send_json_error( array( 'message' => __( 'Arquivo muito grande.', 'hng-commerce' ) ) );
		}

		// Validate file type
		$allowed_types = array_map( 'trim', explode( ',', get_option( 'hng_live_chat_allowed_file_types', 'jpg,jpeg,png,gif,pdf,doc,docx,txt' ) ) );
		$file_ext      = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

		if ( ! in_array( $file_ext, $allowed_types, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Tipo de arquivo não permitido.', 'hng-commerce' ) ) );
		}

		// Generate unique filename
		$file_key     = wp_generate_password( 32, false );
		$new_filename = $file_key . '.' . $file_ext;
		$file_path    = $this->upload_dir . '/' . $new_filename;

		// Move uploaded file
        // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- move_uploaded_file is required for handling HTTP file uploads
		if ( ! move_uploaded_file( $file['tmp_name'], $file_path ) ) {
			wp_send_json_error( array( 'message' => __( 'Erro ao salvar arquivo.', 'hng-commerce' ) ) );
		}

		// Save attachment record
		$wpdb->insert(
			$this->attachments_table,
			array(
				'session_id'    => $session->id,
				'file_key'      => $file_key,
				'original_name' => sanitize_file_name( $file['name'] ),
				'file_path'     => $file_path,
				'file_size'     => $file['size'],
				'file_type'     => $file['type'],
				'uploaded_by'   => is_user_logged_in() ? get_current_user_id() : null,
			)
		);

		$attachment_id = $wpdb->insert_id;

		// Determine message type
		$message_type = in_array( $file_ext, array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ), true ) ? 'image' : 'file';

		// Get sender info
		$user_id     = is_user_logged_in() ? get_current_user_id() : null;
		$sender_name = $session->guest_name;
		if ( $user_id ) {
			$user        = get_user_by( 'ID', $user_id );
			$sender_name = $user->display_name;
		}

		// Create message
		$wpdb->insert(
			$this->messages_table,
			array(
				'session_id'    => $session->id,
				'sender_type'   => 'customer',
				'sender_id'     => $user_id,
				'sender_name'   => $sender_name,
				'message'       => sanitize_file_name( $file['name'] ),
				'message_type'  => $message_type,
				'attachment_id' => $attachment_id,
			)
		);

		$message_id = $wpdb->insert_id;

		// Update attachment with message id
		$wpdb->update(
			$this->attachments_table,
			array( 'message_id' => $message_id ),
			array( 'id' => $attachment_id )
		);

		// Get message
		$msg = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->messages_table} WHERE id = %d",
				$message_id
			)
		);

		wp_send_json_success(
			array(
				'message_id' => $message_id,
				'message'    => $this->format_message( $msg ),
			)
		);
	}

	/**
	 * AJAX: End chat
	 */
	public function ajax_end_chat() {
		check_ajax_referer( 'hng_live_chat', 'nonce' );

		global $wpdb;

		$session_key = isset( $_POST['session_key'] ) ? sanitize_text_field( wp_unslash( $_POST['session_key'] ) ) : '';
		$rating      = intval( $_POST['rating'] ?? 0 );
		$comment     = isset( $_POST['comment'] ) ? sanitize_textarea_field( wp_unslash( $_POST['comment'] ) ) : '';

		if ( empty( $session_key ) ) {
			wp_send_json_error( array( 'message' => __( 'Sessão inválida.', 'hng-commerce' ) ) );
		}

		// Get session
		$session = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->sessions_table} WHERE session_key = %s",
				$session_key
			)
		);

		if ( ! $session ) {
			wp_send_json_error( array( 'message' => __( 'Sessão não encontrada.', 'hng-commerce' ) ) );
		}

		// Update session
		$update_data = array(
			'status'    => 'closed',
			'closed_at' => current_time( 'mysql' ),
		);

		if ( $rating > 0 && $rating <= 5 ) {
			$update_data['rating']         = $rating;
			$update_data['rating_comment'] = $comment;
		}

		$wpdb->update(
			$this->sessions_table,
			$update_data,
			array( 'id' => $session->id )
		);

		// Add system message
		$wpdb->insert(
			$this->messages_table,
			array(
				'session_id'   => $session->id,
				'sender_type'  => 'system',
				'message'      => __( 'Chat encerrado pelo cliente', 'hng-commerce' ),
				'message_type' => 'system',
			)
		);

		// Clear guest cookie
		setcookie( 'hng_chat_guest_token', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );

		do_action( 'hng_live_chat_session_ended', $session->id, $rating, $comment );

		wp_send_json_success( array( 'message' => __( 'Chat encerrado com sucesso.', 'hng-commerce' ) ) );
	}

	/**
	 * AJAX: Typing indicator
	 */
	public function ajax_typing_indicator() {
		check_ajax_referer( 'hng_live_chat', 'nonce' );

		$session_key = isset( $_POST['session_key'] ) ? sanitize_text_field( wp_unslash( $_POST['session_key'] ) ) : '';
		$is_typing   = isset( $_POST['is_typing'] ) ? filter_var( wp_unslash( $_POST['is_typing'] ), FILTER_VALIDATE_BOOLEAN ) : false;

		// Store typing status in transient
		if ( ! empty( $session_key ) ) {
			set_transient( 'hng_chat_typing_' . $session_key . '_customer', $is_typing ? time() : 0, 30 );
		}

		wp_send_json_success();
	}

	/**
	 * AJAX Admin: Get sessions
	 */
	public function ajax_admin_get_sessions() {
		check_ajax_referer( 'hng_live_chat_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'hng_chat_operator' ) ) {
			wp_send_json_error( array( 'message' => __( 'Acesso negado.', 'hng-commerce' ) ) );
		}

		global $wpdb;

		$status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'all';

		$where = 'WHERE 1=1';
		if ( $status !== 'all' ) {
			$where .= $wpdb->prepare( ' AND status = %s', $status );
		}

		$sessions = $wpdb->get_results(
			"SELECT s.*, 
                (SELECT COUNT(*) FROM {$this->messages_table} WHERE session_id = s.id AND sender_type = 'customer' AND is_read = 0) as unread_count,
                (SELECT message FROM {$this->messages_table} WHERE session_id = s.id ORDER BY created_at DESC LIMIT 1) as last_message
            FROM {$this->sessions_table} s 
            {$where}
            ORDER BY 
                CASE status 
                    WHEN 'waiting' THEN 1 
                    WHEN 'active' THEN 2 
                    ELSE 3 
                END,
                last_activity DESC
            LIMIT 100"
		);

		$formatted = array();
		foreach ( $sessions as $session ) {
			$user_info = null;
			if ( $session->user_id ) {
				$user = get_user_by( 'ID', $session->user_id );
				if ( $user ) {
					$user_info = array(
						'id'           => $user->ID,
						'name'         => $user->display_name,
						'email'        => $user->user_email,
						'avatar'       => get_avatar_url( $user->ID, array( 'size' => 40 ) ),
						'registered'   => $user->user_registered,
						'orders_count' => $this->get_user_orders_count( $user->ID ),
					);
				}
			}

			$formatted[] = array(
				'id'              => $session->id,
				'session_key'     => $session->session_key,
				'status'          => $session->status,
				'guest_name'      => $session->guest_name,
				'guest_email'     => $session->guest_email,
				'subject'         => $session->subject,
				'initial_message' => $this->get_session_initial_message( $session->id ),
				'page_url'        => $session->page_url,
				'user_ip'         => $session->user_ip,
				'started_at'      => $session->started_at,
				'last_activity'   => $session->last_activity,
				'unread_count'    => intval( $session->unread_count ),
				'last_message'    => $session->last_message,
				'user_info'       => $user_info,
				'operator_id'     => $session->operator_id,
				'operator_name'   => $session->operator_id ? get_user_by( 'ID', $session->operator_id )->display_name : null,
			);
		}

		wp_send_json_success( array( 'sessions' => $formatted ) );
	}

	/**
	 * Get first customer message from session
	 */
	private function get_session_initial_message( $session_id ) {
		global $wpdb;

		$msg = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT message
             FROM {$this->messages_table}
             WHERE session_id = %d AND sender_type = 'customer'
             ORDER BY id ASC
             LIMIT 1",
				intval( $session_id )
			)
		);

		return $msg ? (string) $msg : '';
	}

	/**
	 * AJAX Admin: Get messages
	 */
	public function ajax_admin_get_messages() {
		check_ajax_referer( 'hng_live_chat_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'hng_chat_operator' ) ) {
			wp_send_json_error( array( 'message' => __( 'Acesso negado.', 'hng-commerce' ) ) );
		}

		global $wpdb;

		$session_id = intval( $_POST['session_id'] ?? 0 );
		$last_id    = intval( $_POST['last_id'] ?? 0 );

		if ( ! $session_id ) {
			wp_send_json_error( array( 'message' => __( 'Sessão inválida.', 'hng-commerce' ) ) );
		}

		// Get session
		$session = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->sessions_table} WHERE id = %d",
				$session_id
			)
		);

		if ( ! $session ) {
			wp_send_json_error( array( 'message' => __( 'Sessão não encontrada.', 'hng-commerce' ) ) );
		}

		// Accept session if waiting
		if ( $session->status === 'waiting' ) {
			$wpdb->update(
				$this->sessions_table,
				array(
					'status'      => 'active',
					'operator_id' => get_current_user_id(),
				),
				array( 'id' => $session_id )
			);

			// Add system message
			$operator = wp_get_current_user();
			$wpdb->insert(
				$this->messages_table,
				array(
					'session_id'   => $session_id,
					'sender_type'  => 'system',
					/* translators: %s: operator name */
					'message'      => sprintf( __( '%s entrou no chat', 'hng-commerce' ), $operator->display_name ),
					'message_type' => 'system',
				)
			);
		}

		// Get messages
		$messages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->messages_table} 
            WHERE session_id = %d AND id > %d 
            ORDER BY created_at ASC",
				$session_id,
				$last_id
			)
		);

		// Mark customer messages as read
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->messages_table} 
            SET is_read = 1 
            WHERE session_id = %d AND sender_type = 'customer' AND is_read = 0",
				$session_id
			)
		);

		$formatted = array();
		foreach ( $messages as $msg ) {
			$formatted[] = $this->format_message( $msg );
		}

		// Check if customer is typing
		$is_typing       = get_transient( 'hng_chat_typing_' . $session->session_key . '_customer' );
		$customer_typing = $is_typing && ( time() - $is_typing ) < 5;

		wp_send_json_success(
			array(
				'messages'        => $formatted,
				'session'         => array(
					'id'          => $session->id,
					'status'      => $session->status,
					'guest_name'  => $session->guest_name,
					'guest_email' => $session->guest_email,
				),
				'customer_typing' => $customer_typing,
			)
		);
	}

	/**
	 * AJAX Admin: Send message
	 */
	public function ajax_admin_send_message() {
		check_ajax_referer( 'hng_live_chat_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'hng_chat_operator' ) ) {
			wp_send_json_error( array( 'message' => __( 'Acesso negado.', 'hng-commerce' ) ) );
		}

		global $wpdb;

		$session_id = intval( $_POST['session_id'] ?? 0 );
		$message    = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

		if ( ! $session_id || empty( $message ) ) {
			wp_send_json_error( array( 'message' => __( 'Dados inválidos.', 'hng-commerce' ) ) );
		}

		$session = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->sessions_table} WHERE id = %d",
				$session_id
			)
		);

		if ( ! $session || $session->status === 'closed' ) {
			wp_send_json_error( array( 'message' => __( 'Sessão não encontrada ou encerrada.', 'hng-commerce' ) ) );
		}

		$operator = wp_get_current_user();

		// Insert message
		$wpdb->insert(
			$this->messages_table,
			array(
				'session_id'   => $session_id,
				'sender_type'  => 'operator',
				'sender_id'    => $operator->ID,
				'sender_name'  => $operator->display_name,
				'message'      => $message,
				'message_type' => 'text',
			)
		);

		$message_id = $wpdb->insert_id;

		// Update session
		$wpdb->update(
			$this->sessions_table,
			array(
				'last_activity' => current_time( 'mysql' ),
				'operator_id'   => $operator->ID,
			),
			array( 'id' => $session_id )
		);

		// Get message
		$msg = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->messages_table} WHERE id = %d",
				$message_id
			)
		);

		wp_send_json_success(
			array(
				'message_id' => $message_id,
				'message'    => $this->format_message( $msg ),
			)
		);
	}

	/**
	 * AJAX Admin: Close session
	 */
	public function ajax_admin_close_session() {
		check_ajax_referer( 'hng_live_chat_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'hng_chat_operator' ) ) {
			wp_send_json_error( array( 'message' => __( 'Acesso negado.', 'hng-commerce' ) ) );
		}

		global $wpdb;

		$session_id = intval( $_POST['session_id'] ?? 0 );

		if ( ! $session_id ) {
			wp_send_json_error( array( 'message' => __( 'Sessão inválida.', 'hng-commerce' ) ) );
		}

		$wpdb->update(
			$this->sessions_table,
			array(
				'status'    => 'closed',
				'closed_at' => current_time( 'mysql' ),
			),
			array( 'id' => $session_id )
		);

		// Add system message
		$operator = wp_get_current_user();
		$wpdb->insert(
			$this->messages_table,
			array(
				'session_id'   => $session_id,
				'sender_type'  => 'system',
				/* translators: %s: operator name */
				'message'      => sprintf( __( 'Chat encerrado por %s', 'hng-commerce' ), $operator->display_name ),
				'message_type' => 'system',
			)
		);

		wp_send_json_success( array( 'message' => __( 'Sessão encerrada.', 'hng-commerce' ) ) );
	}

	/**
	 * AJAX Admin: Transfer session
	 */
	public function ajax_admin_transfer_session() {
		check_ajax_referer( 'hng_live_chat_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'hng_chat_operator' ) ) {
			wp_send_json_error( array( 'message' => __( 'Acesso negado.', 'hng-commerce' ) ) );
		}

		global $wpdb;

		$session_id      = intval( $_POST['session_id'] ?? 0 );
		$new_operator_id = intval( $_POST['operator_id'] ?? 0 );

		if ( ! $session_id || ! $new_operator_id ) {
			wp_send_json_error( array( 'message' => __( 'Dados inválidos.', 'hng-commerce' ) ) );
		}

		$new_operator = get_user_by( 'ID', $new_operator_id );
		if ( ! $new_operator ) {
			wp_send_json_error( array( 'message' => __( 'Operador não encontrado.', 'hng-commerce' ) ) );
		}

		if ( ! $this->can_access_operator_features( $new_operator_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Usuário selecionado não é um atendente válido.', 'hng-commerce' ) ) );
		}

		$session = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->sessions_table} WHERE id = %d",
				$session_id
			)
		);

		if ( ! $session || $session->status === 'closed' ) {
			wp_send_json_error( array( 'message' => __( 'Sessão não encontrada ou encerrada.', 'hng-commerce' ) ) );
		}

		if ( intval( $session->operator_id ) === $new_operator_id ) {
			wp_send_json_success(
				array(
					'message'       => __( 'Essa sessão já está com este atendente.', 'hng-commerce' ),
					'operator_id'   => $new_operator_id,
					'operator_name' => $new_operator->display_name,
				)
			);
		}

		$wpdb->update(
			$this->sessions_table,
			array( 'operator_id' => $new_operator_id ),
			array( 'id' => $session_id )
		);

		// Add system message
		$current_operator = wp_get_current_user();
		$wpdb->insert(
			$this->messages_table,
			array(
				'session_id'   => $session_id,
				'sender_type'  => 'system',
				/* translators: 1: current operator name, 2: new operator name */
				'message'      => sprintf( __( 'Chat transferido de %1$s para %2$s', 'hng-commerce' ), $current_operator->display_name, $new_operator->display_name ),
				'message_type' => 'system',
			)
		);

		wp_send_json_success(
			array(
				'message'       => __( 'Sessão transferida.', 'hng-commerce' ),
				'operator_id'   => $new_operator_id,
				'operator_name' => $new_operator->display_name,
			)
		);
	}

	/**
	 * AJAX Admin: Upload file
	 */
	public function ajax_admin_upload_file() {
		check_ajax_referer( 'hng_live_chat_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'hng_chat_operator' ) ) {
			wp_send_json_error( array( 'message' => __( 'Acesso negado.', 'hng-commerce' ) ) );
		}

		global $wpdb;

		$session_id = intval( $_POST['session_id'] ?? 0 );

		if ( ! $session_id || empty( $_FILES['file'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Dados inválidos.', 'hng-commerce' ) ) );
		}

		// Get session
		$session = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->sessions_table} WHERE id = %d",
				$session_id
			)
		);

		if ( ! $session || $session->status === 'closed' ) {
			wp_send_json_error( array( 'message' => __( 'Sessão não encontrada ou encerrada.', 'hng-commerce' ) ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File handled via move_uploaded_file.
		$file = $_FILES['file'];

		// Validate file size
		$max_size = intval( get_option( 'hng_live_chat_max_file_size', 5 ) ) * 1024 * 1024;
		if ( $file['size'] > $max_size ) {
			wp_send_json_error( array( 'message' => __( 'Arquivo muito grande.', 'hng-commerce' ) ) );
		}

		// Validate file type
		$allowed_types = array_map( 'trim', explode( ',', get_option( 'hng_live_chat_allowed_file_types', 'jpg,jpeg,png,gif,pdf,doc,docx,txt' ) ) );
		$file_ext      = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

		if ( ! in_array( $file_ext, $allowed_types, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Tipo de arquivo não permitido.', 'hng-commerce' ) ) );
		}

		// Generate unique filename
		$file_key     = wp_generate_password( 32, false );
		$new_filename = $file_key . '.' . $file_ext;
		$file_path    = $this->upload_dir . '/' . $new_filename;

		// Move uploaded file
        // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- move_uploaded_file is required for handling HTTP file uploads
		if ( ! move_uploaded_file( $file['tmp_name'], $file_path ) ) {
			wp_send_json_error( array( 'message' => __( 'Erro ao salvar arquivo.', 'hng-commerce' ) ) );
		}

		// Save attachment record
		$wpdb->insert(
			$this->attachments_table,
			array(
				'session_id'    => $session->id,
				'file_key'      => $file_key,
				'original_name' => sanitize_file_name( $file['name'] ),
				'file_path'     => $file_path,
				'file_size'     => $file['size'],
				'file_type'     => $file['type'],
				'uploaded_by'   => get_current_user_id(),
			)
		);

		$attachment_id = $wpdb->insert_id;

		// Determine message type
		$message_type = in_array( $file_ext, array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ), true ) ? 'image' : 'file';

		// Get operator display name
		$operator    = wp_get_current_user();
		$sender_name = $this->get_operator_display_name( $operator->ID );

		// Create message
		$wpdb->insert(
			$this->messages_table,
			array(
				'session_id'    => $session->id,
				'sender_type'   => 'operator',
				'sender_id'     => $operator->ID,
				'sender_name'   => $sender_name,
				'message'       => sanitize_file_name( $file['name'] ),
				'message_type'  => $message_type,
				'attachment_id' => $attachment_id,
			)
		);

		$message_id = $wpdb->insert_id;

		// Update attachment with message id
		$wpdb->update(
			$this->attachments_table,
			array( 'message_id' => $message_id ),
			array( 'id' => $attachment_id )
		);

		// Update session activity
		$wpdb->update(
			$this->sessions_table,
			array( 'last_activity' => current_time( 'mysql' ) ),
			array( 'id' => $session_id )
		);

		// Get message
		$msg = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->messages_table} WHERE id = %d",
				$message_id
			)
		);

		wp_send_json_success(
			array(
				'message_id' => $message_id,
				'message'    => $this->format_message( $msg ),
			)
		);
	}

	/**
	 * Heartbeat received
	 */
	public function heartbeat_received( $response, $data ) {
		if ( isset( $data['hng_live_chat_session'] ) ) {
			global $wpdb;

			$session_key = sanitize_text_field( $data['hng_live_chat_session'] );
			$last_id     = intval( $data['hng_live_chat_last_id'] ?? 0 );

			$session = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$this->sessions_table} WHERE session_key = %s",
					$session_key
				)
			);

			if ( $session ) {
				$messages = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM {$this->messages_table} 
                    WHERE session_id = %d AND id > %d 
                    ORDER BY created_at ASC",
						$session->id,
						$last_id
					)
				);

				$formatted = array();
				foreach ( $messages as $msg ) {
					$formatted[] = $this->format_message( $msg );
				}

				// Check operator typing
				$is_typing = get_transient( 'hng_chat_typing_' . $session_key . '_operator' );

				$response['hng_live_chat'] = array(
					'messages'        => $formatted,
					'session_status'  => $session->status,
					'operator_typing' => $is_typing && ( time() - $is_typing ) < 5,
				);
			}
		}

		// Admin heartbeat
		if ( isset( $data['hng_live_chat_admin'] ) && current_user_can( 'manage_options' ) ) {
			$this->set_operator_online();

			global $wpdb;

			// Get waiting sessions count
			$waiting = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->sessions_table} WHERE status = 'waiting'" );

			$response['hng_live_chat_admin'] = array(
				'waiting_count' => intval( $waiting ),
			);
		}

		return $response;
	}

	/**
	 * Format message for output
	 */
	private function format_message( $msg ) {
		global $wpdb;

		$attachment = null;
		if ( $msg->attachment_id ) {
			$att = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$this->attachments_table} WHERE id = %d",
					$msg->attachment_id
				)
			);

			if ( $att ) {
				// Build direct URL to the file in uploads
				$upload_dir = wp_upload_dir();
				$ext        = strtolower( pathinfo( $att->original_name, PATHINFO_EXTENSION ) );
				$file_url   = $upload_dir['baseurl'] . '/hng-chat-files/' . $att->file_key . '.' . $ext;

				$attachment = array(
					'id'   => $att->id,
					'name' => $att->original_name,
					'size' => $att->file_size,
					'type' => $att->file_type,
					'url'  => $file_url,
				);
			}
		}

		return array(
			'id'           => $msg->id,
			'sender_type'  => $msg->sender_type,
			'sender_name'  => $msg->sender_name,
			'message'      => $msg->message,
			'message_type' => $msg->message_type,
			'attachment'   => $attachment,
			'created_at'   => $msg->created_at,
			'time'         => date_i18n( get_option( 'time_format' ), strtotime( $msg->created_at ) ),
		);
	}

	/**
	 * Get client IP
	 */
	private function get_client_ip() {
		$ip = '';

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- sanitized at the end.
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] )[0];
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = $_SERVER['REMOTE_ADDR'];
		}

		return sanitize_text_field( wp_unslash( $ip ) );
	}

	/**
	 * Get user orders count
	 */
	private function get_user_orders_count( $user_id ) {
		$user_id = intval( $user_id );
		if ( $user_id <= 0 ) {
			return 0;
		}
		global $wpdb;

		$orders_table = function_exists( 'hng_db_full_table_name' )
			? hng_db_full_table_name( 'hng_orders' )
			: ( $wpdb->prefix . 'hng_orders' );

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $orders_table ) ) !== $orders_table ) {
			return 0;
		}

		$orders_table_sql = '`' . str_replace( '`', '', (string) $orders_table ) . '`';
		$orders_columns   = $wpdb->get_col( "SHOW COLUMNS FROM {$orders_table_sql}" );

		$has_orders_user_id        = in_array( 'user_id', (array) $orders_columns, true );
		$has_orders_customer_id    = in_array( 'customer_id', (array) $orders_columns, true );
		$has_orders_customer_email = in_array( 'customer_email', (array) $orders_columns, true );
		$has_orders_billing_email  = in_array( 'billing_email', (array) $orders_columns, true );

		$where_parts = array();
		$params      = array();

		if ( $has_orders_user_id ) {
			$where_parts[] = 'o.user_id = %d';
			$params[]      = $user_id;
		}

		if ( $has_orders_customer_id ) {
			$customers_table = function_exists( 'hng_db_full_table_name' )
				? hng_db_full_table_name( 'hng_customers' )
				: ( $wpdb->prefix . 'hng_customers' );

			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $customers_table ) ) === $customers_table ) {
				$customers_table_sql = '`' . str_replace( '`', '', (string) $customers_table ) . '`';
				$customers_columns   = $wpdb->get_col( "SHOW COLUMNS FROM {$customers_table_sql}" );

				if ( in_array( 'id', (array) $customers_columns, true ) && in_array( 'user_id', (array) $customers_columns, true ) ) {
					$where_parts[] = "o.customer_id IN (SELECT c.id FROM {$customers_table_sql} c WHERE c.user_id = %d)";
					$params[]      = $user_id;
				}
			}
		}

		$user = get_user_by( 'ID', $user_id );
		if ( $user && ! empty( $user->user_email ) ) {
			$email = sanitize_email( $user->user_email );

			if ( $has_orders_customer_email ) {
				$where_parts[] = 'LOWER(o.customer_email) = LOWER(%s)';
				$params[]      = $email;
			}

			if ( $has_orders_billing_email ) {
				$where_parts[] = 'LOWER(o.billing_email) = LOWER(%s)';
				$params[]      = $email;
			}
		}

		if ( empty( $where_parts ) ) {
			return 0;
		}

		$where_sql = implode( ' OR ', $where_parts );
		$query     = "SELECT COUNT(DISTINCT o.id) FROM {$orders_table_sql} o WHERE ({$where_sql})";

		return intval( $wpdb->get_var( $wpdb->prepare( $query, ...$params ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic table/where parts are controlled and values are prepared
	}

	/**
	 * Render admin page
	 */
	public function render_admin_page() {
		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'chats';
		?>
		<style>
			/* Inline critical layout fix for chat - CSS Grid approach */
			.hng-chat-admin-container { height: calc(100vh - 150px) !important; min-height: 500px !important; max-height: 900px !important; overflow: hidden !important; grid-template-rows: minmax(0, 1fr) !important; }
			.hng-chat-admin-main { height: 100% !important; min-height: 0 !important; overflow: hidden !important; display: flex !important; flex-direction: column !important; }
			/* Default: hidden. JS sets display:grid when a session is selected */
			.hng-chat-admin-conversation { display: none !important; }
			/* When JS shows it (sets inline display:grid), use grid layout */
			.hng-chat-admin-conversation[style*="display: grid"],
			.hng-chat-admin-conversation[style*="display:grid"] {
				display: grid !important;
				grid-template-rows: auto 1fr auto auto !important;
				flex: 1 1 0px !important;
				min-height: 0 !important;
				overflow: hidden !important;
			}
			.hng-chat-admin-messages { overflow-y: auto !important; min-height: 0 !important; max-height: 100% !important; }
			.hng-chat-admin-input { min-height: auto !important; }
			.hng-chat-admin-header { min-height: auto !important; }
			.hng-chat-admin-typing { min-height: auto !important; }
		</style>
		<div class="wrap hng-live-chat-admin">
			<h1><?php esc_html_e( 'Chat ao Vivo', 'hng-commerce' ); ?></h1>
			
			<!-- Tabs -->
			<nav class="nav-tab-wrapper hng-chat-tabs">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=hng-live-chat&tab=chats' ) ); ?>" class="nav-tab <?php echo $current_tab === 'chats' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Conversas', 'hng-commerce' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=hng-live-chat&tab=info' ) ); ?>" class="nav-tab <?php echo $current_tab === 'info' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Informações', 'hng-commerce' ); ?>
				</a>
			</nav>
			
			<?php if ( $current_tab === 'info' ) : ?>
				<!-- Info Tab -->
				<div class="hng-chat-info-page">
					<div class="hng-chat-info-card">
						<h2><span class="dashicons dashicons-info-outline"></span> <?php esc_html_e( 'Como Usar o Chat ao Vivo', 'hng-commerce' ); ?></h2>
						
						<div class="hng-chat-info-section">
							<h3><?php esc_html_e( '1. Ativação', 'hng-commerce' ); ?></h3>
							<p><?php esc_html_e( 'Para ativar o chat ao vivo:', 'hng-commerce' ); ?></p>
							<ol>
								<?php /* translators: 1: opening link tag, 2: closing link tag */ ?>
								<li><?php printf( esc_html__( 'Vá em %1$sHNG Commerce > Configurações%2$s', 'hng-commerce' ), '<a href="' . esc_url( admin_url( 'admin.php?page=hng-commerce-settings' ) ) . '">', '</a>' ); ?></li>
								<li><?php esc_html_e( 'Clique na aba "Chat ao Vivo"', 'hng-commerce' ); ?></li>
								<li><?php esc_html_e( 'Marque a opção "Ativar Chat ao Vivo"', 'hng-commerce' ); ?></li>
								<li><?php esc_html_e( 'Salve as configurações', 'hng-commerce' ); ?></li>
							</ol>
						</div>
						
						<div class="hng-chat-info-section">
							<h3><?php esc_html_e( '2. Configurar Atendentes', 'hng-commerce' ); ?></h3>
							<p><?php esc_html_e( 'Para adicionar usuários como atendentes:', 'hng-commerce' ); ?></p>
							<ol>
								<li><?php esc_html_e( 'Nas configurações do Chat ao Vivo, localize a seção "Atendentes"', 'hng-commerce' ); ?></li>
								<li><?php esc_html_e( 'Selecione os usuários que poderão atender os chats', 'hng-commerce' ); ?></li>
								<li><?php esc_html_e( 'Administradores sempre têm acesso automaticamente', 'hng-commerce' ); ?></li>
							</ol>
							<div class="hng-chat-info-tip">
								<strong><?php esc_html_e( 'Dica:', 'hng-commerce' ); ?></strong>
								<?php esc_html_e( 'Ative a "Notificação Sonora para Atendentes" para ser alertado quando um novo cliente está aguardando.', 'hng-commerce' ); ?>
							</div>
						</div>
						
						<div class="hng-chat-info-section">
							<h3><?php esc_html_e( '3. Personalizar Nome do Atendente', 'hng-commerce' ); ?></h3>
							<p><?php esc_html_e( 'O nome que aparece para o cliente pode ser configurado de várias formas:', 'hng-commerce' ); ?></p>
							<ul>
								<li><strong><?php esc_html_e( 'Nome de Exibição do WordPress:', 'hng-commerce' ); ?></strong> <?php esc_html_e( 'Usa o nome configurado no perfil do usuário', 'hng-commerce' ); ?></li>
								<li><strong><?php esc_html_e( 'Primeiro Nome:', 'hng-commerce' ); ?></strong> <?php esc_html_e( 'Usa apenas o primeiro nome do usuário', 'hng-commerce' ); ?></li>
								<li><strong><?php esc_html_e( 'Apelido:', 'hng-commerce' ); ?></strong> <?php esc_html_e( 'Usa o apelido configurado no perfil', 'hng-commerce' ); ?></li>
								<li><strong><?php esc_html_e( 'Nome Personalizado:', 'hng-commerce' ); ?></strong> <?php esc_html_e( 'Usa um nome fixo para todos os atendentes (ex: "Suporte HNG")', 'hng-commerce' ); ?></li>
							</ul>
							<div class="hng-chat-info-tip">
								<strong><?php esc_html_e( 'Para editar o nome de exibição:', 'hng-commerce' ); ?></strong>
								<?php /* translators: 1: opening link tag, 2: closing link tag */ ?>
								<?php printf( esc_html__( 'Vá em %1$sUsuários > Seu Perfil%2$s e altere o campo "Nome de exibição público".', 'hng-commerce' ), '<a href="' . esc_url( admin_url( 'profile.php' ) ) . '">', '</a>' ); ?>
							</div>
						</div>
						
						<div class="hng-chat-info-section">
							<h3><?php esc_html_e( '4. Atendendo Clientes', 'hng-commerce' ); ?></h3>
							<ol>
								<li><?php esc_html_e( 'Os chats aparecem na aba "Conversas" acima', 'hng-commerce' ); ?></li>
								<li><?php esc_html_e( 'Clique em uma conversa para abri-la', 'hng-commerce' ); ?></li>
								<li><?php esc_html_e( 'Digite sua mensagem e clique em "Enviar" ou pressione Enter', 'hng-commerce' ); ?></li>
								<li><?php esc_html_e( 'Use o botão de anexo para enviar arquivos', 'hng-commerce' ); ?></li>
								<li><?php esc_html_e( 'Clique em "Encerrar" quando finalizar o atendimento', 'hng-commerce' ); ?></li>
							</ol>
						</div>
						
						<div class="hng-chat-info-section">
							<h3><?php esc_html_e( '5. Widget Elementor', 'hng-commerce' ); ?></h3>
							<p><?php esc_html_e( 'Você também pode adicionar um botão de chat em qualquer página usando o Elementor:', 'hng-commerce' ); ?></p>
							<ol>
								<li><?php esc_html_e( 'Edite a página com Elementor', 'hng-commerce' ); ?></li>
								<li><?php esc_html_e( 'Procure por "HNG Chat ao Vivo" nos widgets', 'hng-commerce' ); ?></li>
								<li><?php esc_html_e( 'Arraste para a página e configure as opções', 'hng-commerce' ); ?></li>
							</ol>
						</div>
						
						<div class="hng-chat-info-section">
							<h3><?php esc_html_e( 'Status das Conversas', 'hng-commerce' ); ?></h3>
							<ul>
								<li><span class="hng-status-badge waiting"><?php esc_html_e( 'Aguardando', 'hng-commerce' ); ?></span> - <?php esc_html_e( 'Cliente aguardando um atendente', 'hng-commerce' ); ?></li>
								<li><span class="hng-status-badge active"><?php esc_html_e( 'Ativo', 'hng-commerce' ); ?></span> - <?php esc_html_e( 'Atendimento em andamento', 'hng-commerce' ); ?></li>
								<li><span class="hng-status-badge closed"><?php esc_html_e( 'Encerrado', 'hng-commerce' ); ?></span> - <?php esc_html_e( 'Atendimento finalizado', 'hng-commerce' ); ?></li>
							</ul>
						</div>
					</div>
				</div>
				
			<?php else : ?>
				<!-- Chats Tab -->
				<div class="hng-chat-admin-container">
					<!-- Sidebar: Sessions list -->
					<div class="hng-chat-admin-sidebar">
						<div class="hng-chat-admin-filters">
							<select id="hng-chat-status-filter">
								<option value="all" selected><?php esc_html_e( 'Todas', 'hng-commerce' ); ?></option>
								<option value="waiting"><?php esc_html_e( 'Aguardando', 'hng-commerce' ); ?></option>
								<option value="active"><?php esc_html_e( 'Ativas', 'hng-commerce' ); ?></option>
								<option value="closed"><?php esc_html_e( 'Encerradas', 'hng-commerce' ); ?></option>
							</select>
							<button type="button" id="hng-chat-sound-test" class="button sound-active" title="<?php esc_attr_e( 'Som ativado!', 'hng-commerce' ); ?>">
								<span class="dashicons dashicons-controls-volumeon"></span>
							</button>
							<button type="button" id="hng-chat-refresh" class="button">
								<span class="dashicons dashicons-update"></span>
							</button>
						</div>
						<div class="hng-chat-admin-sessions" id="hng-chat-sessions-list">
							<div class="hng-chat-loading"><?php esc_html_e( 'Carregando...', 'hng-commerce' ); ?></div>
						</div>
					</div>
					
					<!-- Main: Chat area -->
					<div class="hng-chat-admin-main">
						<div class="hng-chat-admin-placeholder" id="hng-chat-placeholder">
							<span class="dashicons dashicons-format-chat"></span>
							<p><?php esc_html_e( 'Selecione uma conversa para começar', 'hng-commerce' ); ?></p>
						</div>
						
						<div class="hng-chat-admin-conversation" id="hng-chat-conversation" style="display:none;">
							<div class="hng-chat-admin-header" id="hng-chat-header">
								<div class="hng-chat-admin-user-info">
									<strong class="hng-chat-user-name"></strong>
									<span class="hng-chat-user-email"></span>
								</div>
								<div class="hng-chat-admin-actions">
									<select id="hng-chat-transfer-operator" class="hng-chat-transfer-select">
										<option value=""><?php esc_html_e( 'Selecione um atendente', 'hng-commerce' ); ?></option>
									</select>
									<button type="button" class="button hng-chat-action-transfer" title="<?php esc_attr_e( 'Transferir Atendimento', 'hng-commerce' ); ?>">
										<span class="dashicons dashicons-randomize"></span>
										<?php esc_html_e( 'Transferir', 'hng-commerce' ); ?>
									</button>
									<button type="button" class="button hng-chat-action-close" title="<?php esc_attr_e( 'Encerrar Atendimento', 'hng-commerce' ); ?>">
										<span class="dashicons dashicons-dismiss"></span>
										<?php esc_html_e( 'Encerrar', 'hng-commerce' ); ?>
									</button>
								</div>
							</div>
							
							<div class="hng-chat-admin-messages" id="hng-chat-messages"></div>
							
							<div class="hng-chat-admin-typing" id="hng-chat-typing" style="display: none;">
								<span class="hng-typing-text"></span>
							</div>
							
							<div class="hng-chat-admin-input">
								<div class="hng-chat-admin-input-row">
									<button type="button" id="hng-chat-attach" class="button" title="<?php esc_attr_e( 'Enviar Arquivo', 'hng-commerce' ); ?>">
										<span class="dashicons dashicons-paperclip"></span>
									</button>
									<textarea id="hng-chat-input" placeholder="<?php esc_attr_e( 'Digite sua mensagem...', 'hng-commerce' ); ?>" rows="2"></textarea>
									<button type="button" id="hng-chat-send" class="button button-primary">
										<span class="dashicons dashicons-arrow-right-alt"></span>
										<?php esc_html_e( 'Enviar', 'hng-commerce' ); ?>
									</button>
								</div>
								<input type="file" id="hng-chat-file-input" style="display: none;" accept="<?php echo esc_attr( $this->get_accepted_file_types() ); ?>" />
							</div>
						</div>
					</div>
					
					<!-- Right sidebar: User info -->
					<div class="hng-chat-admin-info" id="hng-chat-info" style="display: none;">
						<h3><?php esc_html_e( 'Informações', 'hng-commerce' ); ?></h3>
						<div class="hng-chat-info-content" id="hng-chat-info-content"></div>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Get accepted file types for input
	 */
	private function get_accepted_file_types() {
		$types      = get_option( 'hng_live_chat_allowed_file_types', 'jpg,jpeg,png,gif,pdf,doc,docx,txt' );
		$extensions = array_map( 'trim', explode( ',', $types ) );
		return '.' . implode( ',.', $extensions );
	}
}

// Initialize
HNG_Live_Chat::instance();
