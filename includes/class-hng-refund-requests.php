<?php
/**
 * HNG Commerce - Refund Requests Management
 *
 * Gerencia solicitações de reembolso dos clientes
 *
 * @package HNG_Commerce
 */

// phpcs:disable Squiz.Commenting
// phpcs:disable Generic.CodeAnalysis
// phpcs:disable Universal.Operators.StrictComparisons
// phpcs:disable WordPress.PHP.StrictInArray
// phpcs:disable WordPress.WP.AlternativeFunctions

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HNG_Refund_Requests {
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- CRUD uses a controlled internal plugin table name.

	/**
	 * Instância única
	 */
	private static $instance = null;

	/**
	 * Nome da tabela de refund requests
	 */
	private $table_name;

	/**
	 * Singleton
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Construtor
	 */
	private function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'hng_refund_requests';
		$this->init_hooks();
	}

	/**
	 * Inicializar hooks
	 */
	private function init_hooks() {
		// REST API
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// AJAX handlers for admin
		add_action( 'wp_ajax_hng_approve_refund', array( $this, 'ajax_approve_refund' ) );
		add_action( 'wp_ajax_hng_reject_refund', array( $this, 'ajax_reject_refund' ) );
	}

	/**
	 * Registrar rotas REST API
	 */
	public function register_rest_routes() {
		register_rest_route(
			'hng/v1',
			'/refund/request',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'api_submit_refund_request' ),
				'permission_callback' => function () {
					return is_user_logged_in();
				},
			)
		);

		register_rest_route(
			'hng/v1',
			'/refund/requests',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_get_user_refunds' ),
				'permission_callback' => function () {
					return is_user_logged_in();
				},
			)
		);
	}

	/**
	 * API: Submeter novo reembolso
	 */
	public function api_submit_refund_request( \WP_REST_Request $request ) {
		global $wpdb;

		// Check nonce
		$nonce = $request->get_param( 'hng_refund_nonce' );
		if ( ! wp_verify_nonce( $nonce, 'hng_refund_request' ) ) {
			return new \WP_Error(
				'invalid_nonce',
				__( 'Token de segurança inválido.', 'hng-commerce' ),
				array( 'status' => 403 )
			);
		}

		$user_id     = get_current_user_id();
		$order_id    = intval( $request->get_param( 'refund_order' ) );
		$amount      = floatval( $request->get_param( 'refund_amount' ) );
		$reason      = sanitize_text_field( $request->get_param( 'refund_reason' ) );
		$description = sanitize_textarea_field( $request->get_param( 'refund_description' ) );

		// Validations
		if ( ! $order_id || $amount <= 0 ) {
			return new \WP_Error(
				'invalid_data',
				__( 'Dados inválidos.', 'hng-commerce' ),
				array( 'status' => 400 )
			);
		}

		// Check if order belongs to user
		$order = new HNG_Order( $order_id );
		if ( $order->get_user_id() != $user_id ) {
			return new \WP_Error(
				'order_not_found',
				__( 'Pedido não encontrado.', 'hng-commerce' ),
				array( 'status' => 404 )
			);
		}

		// Check refund max days
		$settings   = get_option( 'hng_commerce_settings', array() );
		$max_days   = intval( $settings['refund_max_days'] ?? 30 );
		$order_date = strtotime( $order->get_created_at() );
		$max_date   = strtotime( "+{$max_days} days", $order_date );

		if ( time() > $max_date ) {
			return new \WP_Error(
				'refund_expired',
				sprintf(
					/* translators: %1$d = days, %2$s = date */
					__( 'Prazo para solicitar reembolso expirou. Você tinha %1$d dias a partir de %2$s.', 'hng-commerce' ),
					$max_days,
					date_i18n( get_option( 'date_format' ), $order_date )
				),
				array( 'status' => 400 )
			);
		}

		// Check for existing refund request
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM {$this->table_name} WHERE user_id = %d AND order_id = %d AND status != %s",
				$user_id,
				$order_id,
				'rejected'
			)
		);

		if ( $existing ) {
			return new \WP_Error(
				'existing_request',
				__( 'Você já tem uma solicitação de reembolso para este pedido.', 'hng-commerce' ),
				array( 'status' => 400 )
			);
		}

		// Handle file uploads
		$evidence_files = array();
		if ( isset( $_FILES['refund_evidence'] ) && ! empty( $_FILES['refund_evidence']['name'][0] ) ) {
			$upload_dir        = wp_upload_dir();
			$refund_upload_dir = $upload_dir['basedir'] . '/hng-refund-evidence/';

			// Use WordPress filesystem API to create directory
			if ( ! is_dir( $refund_upload_dir ) ) {
				wp_mkdir_p( $refund_upload_dir );
			}

			$allowed_types = array( 'image/jpeg', 'image/png', 'image/gif', 'application/pdf' );
			$max_file_size = 5 * 1024 * 1024; // 5MB

			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Filename is sanitized immediately in the first statement inside the loop.
			foreach ( $_FILES['refund_evidence']['name'] as $idx => $filename ) {
				$filename = sanitize_file_name( wp_unslash( $filename ) );
				if ( empty( $filename ) ) {
					continue;
				}

				if ( ! isset( $_FILES['refund_evidence']['type'][ $idx ], $_FILES['refund_evidence']['size'][ $idx ], $_FILES['refund_evidence']['tmp_name'][ $idx ] ) ) {
					continue;
				}

				$file_type = sanitize_text_field( wp_unslash( $_FILES['refund_evidence']['type'][ $idx ] ) );
				$file_size = absint( wp_unslash( $_FILES['refund_evidence']['size'][ $idx ] ) );

				// Validate
				if ( ! in_array( $file_type, $allowed_types ) ) {
					continue;
				}

				if ( $file_size > $max_file_size ) {
					continue;
				}

				// Move file using WordPress API
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Temporary upload path is validated by PHP upload handling and existence check before copy.
				$tmp_file     = wp_unslash( $_FILES['refund_evidence']['tmp_name'][ $idx ] );
				$new_filename = time() . '_' . sanitize_file_name( $filename );
				$new_filepath = $refund_upload_dir . $new_filename;

				// Use WordPress file upload handling
				if ( file_exists( $tmp_file ) && copy( $tmp_file, $new_filepath ) ) {
					$evidence_files[] = array(
						'name'        => $new_filename,
						'path'        => $new_filepath,
						'uploaded_at' => current_time( 'mysql' ),
					);
				}
			}
		}

		// Create refund request
		$insert_result = $wpdb->insert(
			$this->table_name,
			array(
				'user_id'     => $user_id,
				'order_id'    => $order_id,
				'amount'      => $amount,
				'reason'      => $reason,
				'description' => $description,
				'evidence'    => ! empty( $evidence_files ) ? json_encode( $evidence_files ) : null,
				'status'      => 'pending',
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $insert_result ) {
			return new \WP_Error(
				'insert_failed',
				__( 'Erro ao processar sua solicitação.', 'hng-commerce' ),
				array( 'status' => 500 )
			);
		}

		$refund_id = $wpdb->insert_id;

		// Send admin notification email
		$this->send_admin_notification( $refund_id );

		// Check if auto-approve is enabled
		$auto_approve = ( $settings['refund_auto_approve'] ?? 'no' ) === 'yes';
		if ( $auto_approve ) {
			$this->approve_refund( $refund_id );
		}

		return array(
			'success'   => true,
			'message'   => __( 'Solicitação de reembolso enviada com sucesso!', 'hng-commerce' ),
			'refund_id' => $refund_id,
		);
	}

	/**
	 * API: Recuperar refund requests do usuário
	 */
	public function api_get_user_refunds( \WP_REST_Request $request ) {
		global $wpdb;

		$user_id = get_current_user_id();

		$requests = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE user_id = %d ORDER BY created_at DESC LIMIT 10",
				$user_id
			)
		);

		if ( empty( $requests ) ) {
			return new \WP_Error(
				'not_found',
				__( 'Nenhuma solicitação encontrada.', 'hng-commerce' ),
				array( 'status' => 404 )
			);
		}

		return array(
			'success'  => true,
			'requests' => $requests,
		);
	}

	/**
	 * AJAX: Aprovar reembolso (admin)
	 */
	public function ajax_approve_refund() {
		check_ajax_referer( 'hng_refund_admin_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Sem permissão.', 'hng-commerce' ) );
		}

		$refund_id = isset( $_POST['refund_id'] ) ? intval( wp_unslash( $_POST['refund_id'] ) ) : 0;
		if ( ! $refund_id ) {
			wp_send_json_error( __( 'ID de reembolso inválido.', 'hng-commerce' ) );
		}
		$this->approve_refund( $refund_id );

		wp_send_json_success( __( 'Reembolso aprovado com sucesso!', 'hng-commerce' ) );
	}

	/**
	 * AJAX: Rejeitar reembolso (admin)
	 */
	public function ajax_reject_refund() {
		check_ajax_referer( 'hng_refund_admin_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Sem permissão.', 'hng-commerce' ) );
		}

		global $wpdb;

		$refund_id        = isset( $_POST['refund_id'] ) ? intval( wp_unslash( $_POST['refund_id'] ) ) : 0;
		$rejection_reason = isset( $_POST['rejection_reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['rejection_reason'] ) ) : '';

		if ( ! $refund_id ) {
			wp_send_json_error( __( 'ID de reembolso inválido.', 'hng-commerce' ) );
		}

		$wpdb->update(
			$this->table_name,
			array(
				'status'           => 'rejected',
				'rejection_reason' => $rejection_reason,
				'updated_at'       => current_time( 'mysql' ),
			),
			array( 'id' => $refund_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		// Send rejection email
		$this->send_rejection_email( $refund_id, $rejection_reason );

		wp_send_json_success( __( 'Reembolso rejeitado e customer notificado.', 'hng-commerce' ) );
	}

	/**
	 * Aprovar refund request
	 */
	private function approve_refund( $refund_id ) {
		global $wpdb;

		$refund = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE id = %d",
				$refund_id
			)
		);

		if ( ! $refund ) {
			return;
		}

		// Update status
		$wpdb->update(
			$this->table_name,
			array(
				'status'     => 'approved',
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $refund_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		// Send approval email
		$this->send_approval_email( $refund_id );

		// Trigger refund via payment gateway
		do_action( 'hng_refund_approved', $refund->user_id, $refund->order_id, $refund->amount );
	}

	/**
	 * Enviar email de notificação para admin
	 */
	private function send_admin_notification( $refund_id ) {
		global $wpdb;

		$refund = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE id = %d",
				$refund_id
			)
		);

		if ( ! $refund ) {
			return;
		}

		$user  = get_userdata( $refund->user_id );
		$order = new HNG_Order( $refund->order_id );

		$admin_emails = array( get_option( 'admin_email' ) );
		/* translators: %d: refund ID */
		$subject = sprintf( __( 'Novo Pedido de Reembolso #%d', 'hng-commerce' ), $refund_id );

		/* translators: %1$s = customer name, %2$s = email, %3$d = order ID, %4$s = amount, %5$s = reason, %6$s = description, %7$s = admin URL */
		$message = sprintf(
			__(
				"Cliente: %1\$s (%2\$s)\nPedido: #%3\$d\nValor: %4\$s\nMotivo: %5\$s\n\nDescrição:\n%6\$s\n\nLink para gerenciar: %7\$s",
				'hng-commerce'
			),
			$user->display_name,
			$user->user_email,
			$refund->order_id,
			hng_format_price( $refund->amount ),
			$refund->reason,
			$refund->description,
			admin_url( "edit.php?post_type=hng_order&refund_id={$refund_id}" )
		);

		foreach ( $admin_emails as $email ) {
			wp_mail( $email, $subject, $message );
		}
	}

	/**
	 * Enviar email de aprovação
	 */
	private function send_approval_email( $refund_id ) {
		global $wpdb;

		$refund = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE id = %d",
				$refund_id
			)
		);

		if ( ! $refund ) {
			return;
		}

		$user  = get_userdata( $refund->user_id );
		$order = new HNG_Order( $refund->order_id );

		// Call refund email function
		hng_send_refund_email(
			$refund->order_id,
			array(
				'amount'    => $refund->amount,
				'refund_id' => $refund_id,
			)
		);
	}

	/**
	 * Enviar email de rejeição
	 */
	private function send_rejection_email( $refund_id, $reason ) {
		global $wpdb;

		$refund = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE id = %d",
				$refund_id
			)
		);

		if ( ! $refund ) {
			return;
		}

		$user           = get_userdata( $refund->user_id );
		$customer_email = $user->user_email;

		$template        = get_option( 'hng_email_template_refund_rejected', array() );
		$global_settings = get_option( 'hng_email_global_settings', array() );

		$subject = isset( $template['subject'] ) && ! empty( $template['subject'] )
			? $template['subject']
			: __( 'Reembolso Rejeitado', 'hng-commerce' );

		$content = isset( $template['content'] ) && ! empty( $template['content'] )
			? $template['content']
			: sprintf(
				/* translators: %s = rejection reason */
				__( 'Sua solicitação de reembolso foi rejeitada. Motivo: %s', 'hng-commerce' ),
				$reason
			);

		// Replace variables
		$email_vars = array(
			'{{customer_name}}' => $user->display_name,
			'{{order_id}}'      => $refund->order_id,
			'{{reason}}'        => $reason,
			'{{support_email}}' => get_option( 'admin_email' ),
		);

		foreach ( $email_vars as $var_key => $var_value ) {
			$subject = str_replace( $var_key, $var_value, $subject );
			$content = str_replace( $var_key, $var_value, $content );
		}

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		wp_mail( $customer_email, $subject, $content, $headers );
	}

	/**
	 * Criar tabela de refund requests no banco de dados
	 */
	public static function create_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'hng_refund_requests';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) NOT NULL,
            order_id BIGINT(20) NOT NULL,
            amount DECIMAL(10, 2) NOT NULL,
            reason VARCHAR(255),
            description LONGTEXT,
            evidence LONGTEXT,
            status VARCHAR(20) DEFAULT 'pending',
            rejection_reason LONGTEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY order_id (order_id),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Obter refund request por ID
	 */
	public function get_refund( $refund_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE id = %d",
				$refund_id
			)
		);
	}

	/**
	 * Obter refund requests por ordem
	 */
	public function get_refunds_by_order( $order_id ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE order_id = %d ORDER BY created_at DESC",
				$order_id
			)
		);
	}
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Inicializar
HNG_Refund_Requests::instance();
