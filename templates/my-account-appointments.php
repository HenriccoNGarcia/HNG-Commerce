<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * My Account - Appointments Page
 * 
 * Shows customer's appointments
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$customer_email = wp_get_current_user()->user_email;
$appointments = HNG_Appointment::get_customer_appointments($customer_email);
?>

<div class="hng-my-appointments" role="main" aria-label="Meus agendamentos">
    <h2><?php esc_html_e('Meus Agendamentos', 'hng-commerce'); ?></h2>
    <?php do_action('hng_before_my_appointments'); ?>
    <?php if (empty($appointments)) : ?>
        <p class="hng-no-appointments" aria-live="polite">
            <?php esc_html_e('Você ainda não tem agendamentos.', 'hng-commerce'); ?>
        </p>
    <?php else : ?>
        <table class="hng-appointments-table" role="table" aria-label="Tabela de agendamentos">
            <thead>
                <tr>
                    <th scope="col"><?php esc_html_e('Serviï¿½o', 'hng-commerce'); ?></th>
                    <th scope="col"><?php esc_html_e('Data', 'hng-commerce'); ?></th>
                    <th scope="col"><?php esc_html_e('Horï¿½rio', 'hng-commerce'); ?></th>
                    <th scope="col"><?php esc_html_e('Duraï¿½ï¿½o', 'hng-commerce'); ?></th>
                    <th scope="col"><?php esc_html_e('Status', 'hng-commerce'); ?></th>
                    <th scope="col"><?php esc_html_e('Aï¿½ï¿½es', 'hng-commerce'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($appointments as $appt_data) : 
                    $appointment = new HNG_Appointment($appt_data['id']);
                    $product = new HNG_Product($appt_data['product_id']);
                    $status = $appointment->get_status();
                    $status_labels = [
                        'pending' => __('Pendente', 'hng-commerce'),
                        'confirmed' => __('Confirmado', 'hng-commerce'),
                        'completed' => __('Concluï¿½do', 'hng-commerce'),
                        'cancelled' => __('Cancelado', 'hng-commerce'),
                        'no_show' => __('Nï¿½o Compareceu', 'hng-commerce'),
                    ];
                    $status_class = 'status-' . $status;
                    $is_future = strtotime($appointment->get_date() . ' ' . $appointment->get_time()) > time();
                ?>
                <tr>
                    <td class="appointment-service">
                        <strong><?php echo esc_html($product->get_name()); ?></strong>
                    </td>
                    <td class="appointment-date">
                        <?php echo esc_html( date_i18n(get_option('date_format'), strtotime($appointment->get_date())) ); ?>
                    </td>
                    <td class="appointment-time">
                        <?php echo esc_html($appointment->get_time()); ?>
                    </td>
                    <td class="appointment-duration">
                        <?php /* translators: %d: duration in minutes */ ?>
                        <?php printf( esc_html__('%d min', 'hng-commerce'), intval($appointment->get_duration()) ); ?>
                    </td>
                    <td class="appointment-status">
                        <span class="status-badge <?php echo esc_attr($status_class); ?>">
                            <?php echo esc_html($status_labels[$status] ?? $status); ?>
                        </span>
                    </td>
                    <td class="appointment-actions">
                        <?php if ($is_future && in_array($status, ['pending', 'confirmed'])) : ?>
                            <a href="#" class="button small cancel-appointment" data-appointment-id="<?php echo esc_attr($appointment->get_id()); ?>">
                                <?php esc_html_e('Cancelar', 'hng-commerce'); ?>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<style>
.hng-appointments-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

.hng-appointments-table th,
.hng-appointments-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.hng-appointments-table th {
    background-color: #f5f5f5;
    font-weight: bold;
}

.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
}

.status-badge.status-pending {
    background-color: #FF9800;
    color: white;
}

.status-badge.status-confirmed {
    background-color: #4CAF50;
    color: white;
}

.status-badge.status-completed {
    background-color: #2196F3;
    color: white;
}

.status-badge.status-cancelled,
.status-badge.status-no_show {
    background-color: #F44336;
    color: white;
}

.button.small {
    padding: 6px 12px;
    font-size: 13px;
}

.hng-no-appointments {
    padding: 40px;
    text-align: center;
    background-color: #f5f5f5;
    border-radius: 4px;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('.cancel-appointment').on('click', function(e) {
    e.preventDefault();
    if (confirm(<?php echo wp_json_encode( __( 'Tem certeza que deseja cancelar este agendamento?', 'hng-commerce') ); ?>)) {
            var appointmentId = $(this).data('appointment-id');
            $.post(hngCommerce.ajax_url, {
                action: 'hng_cancel_appointment',
                appointment_id: appointmentId,
                nonce: hngCommerce.nonce
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message);
                }
            });
        }
    });
});
</script>
