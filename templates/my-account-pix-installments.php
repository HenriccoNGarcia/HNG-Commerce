<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * My Account - PIX Installments
 * 
 * Manage PIX installment payments
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$customer_email = wp_get_current_user()->user_email;
$installments = HNG_PIX_Installment::get_customer_installments($customer_email);
?>

<div class="hng-pix-installments">
    <h2><?php esc_html_e('Minhas Parcelas PIX', 'hng-commerce'); ?></h2>
    <?php do_action('hng_before_my_pix_installments'); ?>
    <?php if (empty($installments)) : ?>
        <p class="hng-no-installments">
            <?php esc_html_e('Você não tem parcelas PIX pendentes.', 'hng-commerce'); ?>
        </p>
    <?php else : ?>
        <table class="hng-installments-table">
            <thead>
                <tr role="row">
                    <th><?php esc_html_e('Pedido', 'hng-commerce'); ?></th>
                    <th><?php esc_html_e('Parcela', 'hng-commerce'); ?></th>
                    <th><?php esc_html_e('Valor', 'hng-commerce'); ?></th>
                    <th><?php esc_html_e('Vencimento', 'hng-commerce'); ?></th>
                    <th><?php esc_html_e('Status', 'hng-commerce'); ?></th>
                    <th><?php esc_html_e('Ação', 'hng-commerce'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($installments as $inst) : 
                    $status_labels = [
                        'pending' => __('Pendente', 'hng-commerce'),
                        'paid' => __('Pago', 'hng-commerce'),
                        'overdue' => __('Vencido', 'hng-commerce'),
                        'cancelled' => __('Cancelado', 'hng-commerce'),
                    ];
                    $status_class = 'status-' . $inst['status'];
                    $is_overdue = $inst['status'] === 'overdue' || (strtotime($inst['due_date']) < time() && $inst['status'] === 'pending');
                ?>
                <tr class="<?php echo esc_attr( $is_overdue ? 'overdue-installment' : '' ); ?>">
                    <td>
                        <strong>#<?php echo esc_html($inst['order_id']); ?></strong>
                    </td>
                    <td>
                        <?php /* translators: %1$d: current installment, %2$d: total installments */
                        printf(esc_html__('%1$d/%2$d', 'hng-commerce'), esc_html( $inst['installment_number'] ), esc_html( $inst['total_installments'] ) ); ?>
                    </td>
                    <td>
                        <strong>R$ <?php echo esc_html(number_format($inst['amount'], 2, ',', '.')); ?></strong>
                    </td>
                    <td>
                        <?php 
                        $due_date = strtotime($inst['due_date']);
                        echo esc_html( date_i18n(get_option('date_format'), $due_date) );
                        
                        if ($is_overdue && $inst['status'] !== 'paid') {
                            echo ' <span class="overdue-label">' . esc_html__('(vencido)', 'hng-commerce') . '</span>';
                        }
                        ?>
                    </td>
                    <td>
                        <span class="status-badge <?php echo esc_attr($status_class); ?>">
                            <?php echo esc_html($status_labels[$inst['status']] ?? $inst['status']); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($inst['status'] === 'pending' || $inst['status'] === 'overdue') : ?>
                            <button class="button generate-pix-installment" data-installment-id="<?php echo esc_attr($inst['id']); ?>">
                                <?php esc_html_e('Gerar PIX', 'hng-commerce'); ?>
                            </button>
                        <?php elseif ($inst['status'] === 'paid') : ?>
                            <span class="paid-check">?</span>
                        <?php endif; ?>
                    </td>
                </tr>
                                <button class="button generate-pix-installment" data-installment-id="<?php echo esc_attr($inst['id']); ?>" aria-label="<?php esc_html_e('Gerar PIX para a parcela', 'hng-commerce'); ?> #<?php echo esc_html($inst['order_id']); ?>">
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- PIX Modal -->
<div id="pix-installment-modal" class="hng-modal" style="display:none;">
    <div class="modal-content">
        <span class="close-modal">&times;</span>
        <h3><?php esc_html_e('Pagar Parcela via PIX', 'hng-commerce'); ?></h3>
        <div class="pix-content">
            <div class="pix-qr-code"></div>
            <div class="pix-code">
                <label><?php esc_html_e('Código PIX (Copia e Cola):', 'hng-commerce'); ?></label>
                <textarea readonly class="pix-code-text"></textarea>
                <button class="button copy-pix-code"><?php esc_html_e('Copiar Código', 'hng-commerce'); ?></button>
            </div>
        </div>
    </div>
</div>

<style>
.hng-installments-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

.hng-installments-table th,
.hng-installments-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.hng-installments-table th {
    background-color: #f5f5f5;
    font-weight: bold;
}

.overdue-installment {
    background-color: #fff3cd;
}

.overdue-label {
    color: #d32f2f;
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

.status-badge.status-paid {
    background-color: #4CAF50;
    color: white;
}

.status-badge.status-overdue {
    background-color: #F44336;
    color: white;
}

.paid-check {
    color: #4CAF50;
    font-size: 20px;
}

.hng-modal {
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: white;
    margin: 5% auto;
    padding: 30px;
    width: 90%;
    max-width: 500px;
    border-radius: 8px;
    position: relative;
}

.close-modal {
    position: absolute;
    right: 15px;
    top: 10px;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.pix-qr-code {
    text-align: center;
    margin: 20px 0;
}

.pix-qr-code img {
    max-width: 300px;
}

.pix-code-text {
    width: 100%;
    height: 100px;
    margin: 10px 0;
    padding: 10px;
    font-family: monospace;
}

.copy-pix-code {
    width: 100%;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('.generate-pix-installment').on('click', function() {
        var installmentId = $(this).data('installment-id');
        
        $.post(hngCommerce.ajax_url, {
            action: 'hng_generate_installment_pix',
            installment_id: installmentId,
            nonce: hngCommerce.nonce
        }, function(response) {
            if (response.success) {
                showPixModal(response.data);
            } else {
                alert(response.data.message || 'Erro ao gerar PIX');
            }
        });
    });
    
    function showPixModal(pixData) {
        if (pixData.pix_data.qr_code_base64) {
            $('.pix-qr-code').html('<img src="' + pixData.pix_data.qr_code_base64 + '">');
        }
        
        $('.pix-code-text').val(pixData.pix_data.qr_code || '');
        $('#pix-installment-modal').show();
    }
    
    $('.close-modal').on('click', function() {
        $('#pix-installment-modal').hide();
    });
    
    $('.copy-pix-code').on('click', function() {
        $('.pix-code-text').select();
        document.execCommand('copy');
        alert('<?php esc_html_e('Código copiado!', 'hng-commerce'); ?>');
    });
    
    $(window).on('click', function(e) {
        if ($(e.target).is('#pix-installment-modal')) {
            $('#pix-installment-modal').hide();
        }
    });
});
</script>
