<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * My Account - Subscriptions Page
 *
 * @package HNG_Commerce
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_user = wp_get_current_user();
$customer_email = isset($current_user->user_email) ? $current_user->user_email : '';
$subscriptions = array();
if (class_exists('HNG_Subscription') && $customer_email) {
    $subscriptions = HNG_Subscription::get_customer_subscriptions($customer_email);
}
?>

<div class="hng-my-subscriptions" role="main" aria-label="Minhas assinaturas">
    <h2><?php esc_html_e('Minhas Assinaturas', 'hng-commerce'); ?></h2>
    <?php do_action('hng_before_my_subscriptions'); ?>

    <?php if (empty($subscriptions)) : ?>
        <div class="hng-no-subscriptions" aria-live="polite">
            <p><?php esc_html_e('Você ainda não possui assinaturas.', 'hng-commerce'); ?></p>
        </div>
    <?php else : ?>
        <div class="hng-subscriptions-table-responsive" tabindex="0" aria-label="Tabela de assinaturas">
            <table class="hng-subscriptions-table" role="table" aria-label="Tabela de assinaturas">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e('Plano', 'hng-commerce'); ?></th>
                        <th scope="col"><?php esc_html_e('Status', 'hng-commerce'); ?></th>
                        <th scope="col"><?php esc_html_e('Próximo pagamento', 'hng-commerce'); ?></th>
                        <th scope="col"><?php esc_html_e('Valor', 'hng-commerce'); ?></th>
                        <th scope="col"><?php esc_html_e('Período', 'hng-commerce'); ?></th>
                        <th scope="col"><?php esc_html_e('Ações', 'hng-commerce'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($subscriptions as $sub_data) :
                    $subscription = (class_exists('HNG_Subscription') ? new HNG_Subscription(isset($sub_data['id']) ? $sub_data['id'] : 0) : null);
                    $product = (class_exists('HNG_Product') && !empty($sub_data['product_id']) ? new HNG_Product($sub_data['product_id']) : null);
                    if (!$subscription) continue;
                    $status = $subscription->get_status();
                    $status_labels = array(
                        'active' => __('Ativa', 'hng-commerce'),
                        'pending' => __('Pendente', 'hng-commerce'),
                        'on_hold' => __('Em Espera', 'hng-commerce'),
                        'cancelled' => __('Cancelada', 'hng-commerce'),
                        'expired' => __('Expirada', 'hng-commerce'),
                        'pending_cancellation' => __('Cancelamento Agendado', 'hng-commerce'),
                    );
                    $status_class = 'status-' . $status;
                ?>
                    <tr>
                        <td class="subscription-product">
                            <strong><?php echo esc_html($product ? $product->get_name() : ''); ?></strong>
                            <br>
                            <small>#<?php echo esc_html($subscription->get_id()); ?></small>
                        </td>
                        <td class="subscription-status">
                            <span class="status-badge <?php echo esc_attr($status_class); ?>">
                                <?php echo esc_html(isset($status_labels[$status]) ? $status_labels[$status] : $status); ?>
                            </span>
                        </td>
                        <td class="subscription-next-payment">
                            <?php
                            if (in_array($status, array('active', 'on_hold'), true)) {
                                $next = $subscription->get_next_payment_date();
                                $next_ts = $next ? strtotime($next) : 0;
                                echo $next_ts ? esc_html(date_i18n(get_option('date_format'), $next_ts)) : '-';
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td class="subscription-amount">
                            <strong><?php echo esc_html(number_format($subscription->get_amount(), 2, ',', '.')); ?></strong>
                        </td>
                        <td class="subscription-period">
                            <?php
                            $periods = array(
                                'daily' => __('Diário', 'hng-commerce'),
                                'weekly' => __('Semanal', 'hng-commerce'),
                                'monthly' => __('Mensal', 'hng-commerce'),
                                'yearly' => __('Anual', 'hng-commerce'),
                            );
                            echo esc_html(isset($periods[$subscription->get_billing_period()]) ? $periods[$subscription->get_billing_period()] : '-');
                            ?>
                        </td>
                        <td class="subscription-actions">
                            <?php if ($status === 'active') : ?>
                                <a href="#" class="button small pause-subscription" data-subscription-id="<?php echo esc_attr($subscription->get_id()); ?>"><?php esc_html_e('Pausar', 'hng-commerce'); ?></a>
                                <a href="#" class="button small cancel-subscription" data-subscription-id="<?php echo esc_attr($subscription->get_id()); ?>"><?php esc_html_e('Cancelar', 'hng-commerce'); ?></a>
                            <?php elseif ($status === 'on_hold') : ?>
                                <a href="#" class="button small resume-subscription" data-subscription-id="<?php echo esc_attr($subscription->get_id()); ?>"><?php esc_html_e('Reativar', 'hng-commerce'); ?></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<style>
.hng-subscriptions-table { width:100%; border-collapse:collapse; margin-top:20px; }
.hng-subscriptions-table th, .hng-subscriptions-table td { padding:12px; text-align:left; border-bottom:1px solid #ddd; }
.hng-subscriptions-table th { background:#f5f5f5; font-weight:bold; }
.status-badge { display:inline-block; padding:4px 12px; border-radius:12px; font-size:12px; font-weight:bold; }
.status-badge.status-active{background:#4CAF50;color:#fff}
.status-badge.status-pending{background:#FF9800;color:#fff}
.status-badge.status-on_hold{background:#9E9E9E;color:#fff}
.status-badge.status-cancelled,.status-badge.status-expired{background:#F44336;color:#fff}
.status-badge.status-pending_cancellation{background:#FF5722;color:#fff}
.button.small{padding:6px 12px;font-size:13px;margin-right:8px}
.hng-no-subscriptions{padding:40px;text-align:center;background:#f5f5f5;border-radius:4px}
</style>

<script>
jQuery(function($){
    var ajaxUrl = (typeof hngCommerce !== 'undefined' && hngCommerce.ajax_url) ? hngCommerce.ajax_url : '';
    var nonce = (typeof hngCommerce !== 'undefined' && hngCommerce.nonce) ? hngCommerce.nonce : '';

    function postAction(action, subscriptionId, confirmText) {
        if (confirmText && !confirm(confirmText)) return;
        $.post(ajaxUrl, { action: action, subscription_id: subscriptionId, nonce: nonce }, function(response){
            if (response && response.success) {
                location.reload();
            } else {
                alert((response && response.data && response.data.message) ? response.data.message : 'Erro');
            }
        });
    }

    // translators: confirmation shown when user pauses a subscription
    $(document).on('click', '.pause-subscription', function(e){ e.preventDefault(); postAction('hng_pause_subscription', $(this).data('subscription-id'), '<?php echo esc_js( __("Deseja pausar esta assinatura?", "hng-commerce") ); ?>'); });
    $(document).on('click', '.resume-subscription', function(e){ e.preventDefault(); postAction('hng_resume_subscription', $(this).data('subscription-id')); });
    // translators: confirmation shown when user cancels a subscription
    $(document).on('click', '.cancel-subscription', function(e){ e.preventDefault(); postAction('hng_cancel_subscription', $(this).data('subscription-id'), '<?php echo esc_js( __("Tem certeza que deseja cancelar esta assinatura?", "hng-commerce") ); ?>'); });
});
</script>
