<?php
/**
 * Appointment AJAX Handlers
 *
 * Manages appointment booking and actions
 *
 * @package HNG_Commerce
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX: Get available time slots.
 *
 * @return void
 */
add_action( 'wp_ajax_hng_get_available_slots', 'hng_get_available_slots' );
add_action( 'wp_ajax_nopriv_hng_get_available_slots', 'hng_get_available_slots' );

/**
 * Get available appointment time slots.
 *
 * Handles AJAX request to retrieve available time slots.
 *
 * @since 1.0.0
 * @return void
 */
function hng_get_available_slots() {
	check_ajax_referer( 'HNG Commerce', 'nonce' );

	$post       = wp_unslash( $_POST );
	$product_id = absint( $post['product_id'] ?? 0 );
	$date       = sanitize_text_field( $post['date'] ?? '' );

	if ( ! $product_id || ! $date ) {
		wp_send_json_error( array( 'message' => __( 'Dados inválidos.', 'hng-commerce' ) ) );
	}

	$slots = HNG_Appointment::get_available_slots( $product_id, $date );

	wp_send_json_success( array( 'slots' => $slots ) );
}

/**
 * AJAX: Book appointment.
 *
 * @return void
 */
add_action( 'wp_ajax_hng_book_appointment', 'hng_book_appointment' );

/**
 * Book a new appointment.
 *
 * Handles AJAX request to book an appointment for the logged-in user.
 *
 * @since 1.0.0
 * @return void
 */
function hng_book_appointment() {
	check_ajax_referer( 'HNG Commerce', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => __( 'Você precisa estar logado para agendar.', 'hng-commerce' ) ), 401 );
	}
	$current_user = wp_get_current_user();
	if ( ! $current_user || ! $current_user->ID ) {
		wp_send_json_error( array( 'message' => __( 'Usuário inválido. Faça login novamente.', 'hng-commerce' ) ), 401 );
	}

	$post             = wp_unslash( $_POST );
	$product_id       = absint( $post['product_id'] ?? 0 );
	$data             = array(
		'product_id'       => $product_id,
		'appointment_date' => sanitize_text_field( $post['appointment_date'] ?? '' ),
		'appointment_time' => sanitize_text_field( $post['appointment_time'] ?? '' ),
		'customer_name'    => sanitize_text_field( $post['customer_name'] ?? $current_user->display_name ),
		'customer_email'   => $current_user->user_email,
		'customer_phone'   => sanitize_text_field( $post['customer_phone'] ?? '' ),
		'notes'            => sanitize_textarea_field( $post['notes'] ?? '' ),
	);
	$data['duration'] = get_post_meta( $product_id, '_appointment_duration', true );
	if ( empty( $data['duration'] ) ) {
		$data['duration'] = 60;
	}

	// Validation.
	if ( ! $data['product_id'] || ! $data['appointment_date'] || ! $data['appointment_time'] ) {
		wp_send_json_error( array( 'message' => __( 'Preencha todos os campos obrigatórios.', 'hng-commerce' ) ) );
	}

	if ( ! is_email( $data['customer_email'] ) ) {
		wp_send_json_error( array( 'message' => __( 'E-mail inválido.', 'hng-commerce' ) ) );
	}

	$appointment = HNG_Appointment::create( $data );

	if ( is_wp_error( $appointment ) ) {
		wp_send_json_error( array( 'message' => $appointment->get_error_message() ) );
	}

	if ( $appointment ) {
		// Create order for the appointment.
		$product = new HNG_Product( $data['product_id'] );

		$order_data = array(
			'customer_email' => $data['customer_email'],
			'customer_name'  => $data['customer_name'],
			'total'          => $product->get_price(),
			'status'         => 'pending',
			'items'          => array(
				array(
					'product_id' => $data['product_id'],
					'quantity'   => 1,
					'price'      => $product->get_price(),
				),
			),
		);

		// TODO: Create order and process payment.

		wp_send_json_success(
			array(
				'message'        => __( 'Agendamento realizado com sucesso!', 'hng-commerce' ),
				'appointment_id' => $appointment->get_id(),
			)
		);
	}

	wp_send_json_error( array( 'message' => __( 'Erro ao criar agendamento.', 'hng-commerce' ) ) );
}

/**
 * AJAX: Cancel appointment.
 *
 * @return void
 */
add_action(
	'wp_ajax_hng_cancel_appointment',
	function () {
		check_ajax_referer( 'HNG Commerce', 'nonce' );

		$appointment_id = absint( wp_unslash( $_POST['appointment_id'] ?? 0 ) );

		if ( ! $appointment_id ) {
			wp_send_json_error( array( 'message' => __( 'ID inválido.', 'hng-commerce' ) ) );
		}

		$appointment = new HNG_Appointment( $appointment_id );

		// Verify ownership.
		if ( $appointment->get_customer_email() !== wp_get_current_user()->user_email ) {
			wp_send_json_error( array( 'message' => __( 'Acesso negado.', 'hng-commerce' ) ) );
		}

		// Check if can cancel (e.g., not too close to appointment time).
		$min_cancel_hours      = 24; // 24 hours before.
		$appointment_timestamp = strtotime( $appointment->get_date() . ' ' . $appointment->get_time() );
		$now                   = time();

		if ( $appointment_timestamp - $now < ( $min_cancel_hours * 3600 ) ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %d: minimum hours required for cancellation */
						esc_html__( 'Agendamentos só podem ser cancelados com no mínimo %d horas de antecedência.', 'hng-commerce' ),
						$min_cancel_hours
					),
				)
			);
		}

		$appointment->cancel();

		wp_send_json_success( array( 'message' => __( 'Agendamento cancelado.', 'hng-commerce' ) ) );
	}
);

/**
 * Auto-confirm appointments after payment.
 *
 * @param int    $order_id   Order ID.
 * @param string $old_status Previous order status.
 * @param string $new_status New order status.
 * @return void
 */
add_action(
	'hng_order_status_changed',
	function ( $order_id, $old_status, $new_status ) {
		if ( ! in_array( $new_status, array( 'processing', 'completed' ), true ) ) {
			return;
		}

		global $wpdb;

		// Find appointments for this order.
		$appointments_table_full = function_exists( 'hng_db_full_table_name' ) ? hng_db_full_table_name( 'hng_appointments' ) : ( $wpdb->prefix . 'hng_appointments' );
		$appointments_table_sql  = function_exists( 'hng_db_backtick_table' ) ? hng_db_backtick_table( 'hng_appointments' ) : ( '`' . str_replace( '`', '', $appointments_table_full ) . '`' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$appointments = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Dynamic table name is controlled.
				"SELECT id FROM {$appointments_table_sql} WHERE order_id = %d AND status = 'pending'",
				$order_id
			),
			ARRAY_A
		);

		foreach ( $appointments as $appt ) {
			$appointment = new HNG_Appointment( $appt['id'] );
			$appointment->confirm();
		}
	},
	10,
	3
);
