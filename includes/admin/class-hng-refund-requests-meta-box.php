<?php
/**
 * HNG Commerce - Refund Requests Meta Box
 * 
 * Meta box para gerenciar refund requests na página de edição de pedidos
 *
 * @package HNG_Commerce
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Refund_Requests_Meta_Box {

    /**
     * Instância única
     */
    private static $instance = null;

    /**
     * Singleton
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        add_action('add_meta_boxes', [$this, 'register_meta_box']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    /**
     * Registrar meta box
     */
    public function register_meta_box() {
        if (get_post_type() !== 'hng_order') {
            return;
        }

        add_meta_box(
            'hng-refund-requests',
            __('Solicitações de Reembolso', 'hng-commerce'),
            [$this, 'render_meta_box'],
            'hng_order',
            'normal',
            'high'
        );
    }

    /**
     * Renderizar meta box
     */
    public function render_meta_box($post) {
        $order_id = $post->ID;
        $refund_requests = HNG_Refund_Requests::instance()->get_refunds_by_order($order_id);

        if (empty($refund_requests)) {
            echo '<p style="color: #666;">' . esc_html__('Nenhuma solicitação de reembolso para este pedido.', 'hng-commerce') . '</p>';
            return;
        }

        wp_nonce_field('hng_refund_admin_action', 'hng_refund_admin_nonce');

        ?>
        <div class="hng-refund-requests-container">
            <?php foreach ($refund_requests as $request) : ?>
                <div class="hng-refund-request-card" data-refund-id="<?php echo esc_attr($request->id); ?>" style="border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 4px; background: #fafafa;">
                    <!-- Header -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <h4 style="margin: 0;">
                            <?php /* translators: %d: refund ID */ printf(
                                esc_html__('Reembolso #%d', 'hng-commerce'),
                                intval($request->id)
                            ); ?>
                        </h4>
                        <span class="hng-refund-status hng-status-<?php echo esc_attr($request->status); ?>" style="padding: 4px 12px; border-radius: 4px; font-size: 12px; font-weight: 500; background: <?php echo esc_attr($this->get_status_color($request->status)); ?>;">
                            <?php echo esc_html($this->get_status_label($request->status)); ?>
                        </span>
                    </div>

                    <!-- Info -->
                    <table style="width: 100%; margin-bottom: 12px; font-size: 14px;">
                        <tr>
                            <td style="width: 30%; font-weight: 600; padding: 6px 0;"><?php esc_html_e('Cliente:', 'hng-commerce'); ?></td>
                            <td style="padding: 6px 0;">
                                <?php
                                $user = get_userdata($request->user_id);
                                if ($user) {
                                    echo esc_html($user->display_name) . ' (' . esc_html($user->user_email) . ')';
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="width: 30%; font-weight: 600; padding: 6px 0;"><?php esc_html_e('Valor:', 'hng-commerce'); ?></td>
                            <td style="padding: 6px 0;"><strong><?php echo esc_html(hng_format_price($request->amount)); ?></strong></td>
                        </tr>
                        <tr>
                            <td style="width: 30%; font-weight: 600; padding: 6px 0;"><?php esc_html_e('Motivo:', 'hng-commerce'); ?></td>
                            <td style="padding: 6px 0;"><?php echo esc_html($request->reason); ?></td>
                        </tr>
                        <tr>
                            <td style="width: 30%; font-weight: 600; padding: 6px 0; vertical-align: top;"><?php esc_html_e('Descrição:', 'hng-commerce'); ?></td>
                            <td style="padding: 6px 0;"><?php echo wp_kses_post(wpautop($request->description)); ?></td>
                        </tr>
                        <tr>
                            <td style="width: 30%; font-weight: 600; padding: 6px 0;"><?php esc_html_e('Data:', 'hng-commerce'); ?></td>
                            <td style="padding: 6px 0;"><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($request->created_at))); ?></td>
                        </tr>
                        <?php if ($request->status === 'rejected' && !empty($request->rejection_reason)) : ?>
                            <tr>
                                <td style="width: 30%; font-weight: 600; padding: 6px 0; color: #d32f2f; vertical-align: top;"><?php esc_html_e('Motivo da Rejeição:', 'hng-commerce'); ?></td>
                                <td style="padding: 6px 0; color: #d32f2f;"><?php echo wp_kses_post(wpautop($request->rejection_reason)); ?></td>
                            </tr>
                        <?php endif; ?>
                    </table>

                    <!-- Evidence -->
                    <?php
                    $evidence = !empty($request->evidence) ? json_decode($request->evidence, true) : [];
                    if (!empty($evidence)) :
                    ?>
                        <div style="margin-bottom: 12px; padding: 10px; background: white; border: 1px solid #e0e0e0; border-radius: 4px;">
                            <strong><?php esc_html_e('Arquivos Enviados:', 'hng-commerce'); ?></strong>
                            <ul style="margin: 8px 0 0 0; padding-left: 20px;">
                                <?php foreach ($evidence as $file) : ?>
                                    <li>
                                        <a href="<?php echo esc_url(wp_upload_dir()['baseurl'] . '/hng-refund-evidence/' . $file['name']); ?>" target="_blank">
                                            <?php echo esc_html($file['name']); ?>
                                        </a>
                                        <small style="color: #666; display: block;"><?php echo esc_html($file['uploaded_at']); ?></small>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <!-- Actions -->
                    <?php if ($request->status === 'pending') : ?>
                        <div class="hng-refund-actions" style="display: flex; gap: 10px;">
                            <button type="button" class="button button-primary hng-approve-refund" data-refund-id="<?php echo esc_attr($request->id); ?>">
                                <?php esc_html_e('Aprovar Reembolso', 'hng-commerce'); ?>
                            </button>
                            <button type="button" class="button hng-reject-refund" data-refund-id="<?php echo esc_attr($request->id); ?>">
                                <?php esc_html_e('Rejeitar', 'hng-commerce'); ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <style>
            .hng-refund-status {
                border: 1px solid #999;
                color: white;
            }

            .hng-status-pending {
                background: #ff9800 !important;
            }

            .hng-status-approved {
                background: #4caf50 !important;
            }

            .hng-status-rejected {
                background: #d32f2f !important;
            }

            .hng-status-completed {
                background: #2196f3 !important;
            }

            .hng-refund-actions button {
                margin-top: 10px;
            }
        </style>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const approveButtons = document.querySelectorAll('.hng-approve-refund');
                const rejectButtons = document.querySelectorAll('.hng-reject-refund');

                approveButtons.forEach(button => {
                    button.addEventListener('click', function(e) {
                        e.preventDefault();
                        const refundId = this.getAttribute('data-refund-id');
                        
                        if (confirm('<?php esc_attr_e('Tem certeza que deseja aprovar este reembolso?', 'hng-commerce'); ?>')) {
                            const formData = new FormData();
                            formData.append('action', 'hng_approve_refund');
                            formData.append('refund_id', refundId);
                            formData.append('nonce', document.querySelector('input[name="hng_refund_admin_nonce"]').value);

                            fetch('<?php echo esc_js(admin_url('admin-ajax.php')); ?>', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    alert('<?php esc_attr_e('Reembolso aprovado com sucesso!', 'hng-commerce'); ?>');
                                    location.reload();
                                } else {
                                    alert('<?php esc_attr_e('Erro ao aprovar reembolso.', 'hng-commerce'); ?>');
                                }
                            });
                        }
                    });
                });

                rejectButtons.forEach(button => {
                    button.addEventListener('click', function(e) {
                        e.preventDefault();
                        const refundId = this.getAttribute('data-refund-id');
                        const reason = prompt('<?php esc_attr_e('Informe o motivo da rejeição (opcional):', 'hng-commerce'); ?>');
                        
                        if (reason !== null) {
                            const formData = new FormData();
                            formData.append('action', 'hng_reject_refund');
                            formData.append('refund_id', refundId);
                            formData.append('rejection_reason', reason);
                            formData.append('nonce', document.querySelector('input[name="hng_refund_admin_nonce"]').value);

                            fetch('<?php echo esc_js(admin_url('admin-ajax.php')); ?>', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    alert('<?php esc_attr_e('Reembolso rejeitado e cliente notificado.', 'hng-commerce'); ?>');
                                    location.reload();
                                } else {
                                    alert('<?php esc_attr_e('Erro ao rejeitar reembolso.', 'hng-commerce'); ?>');
                                }
                            });
                        }
                    });
                });
            });
        </script>
        <?php
    }

    /**
     * Enfileirar scripts
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'post.php') {
            return;
        }

        if (get_post_type() !== 'hng_order') {
            return;
        }

        wp_enqueue_style('wp-codemirror');
        wp_enqueue_script('wp-codemirror');
    }

    /**
     * Get status color
     */
    private function get_status_color($status) {
        $colors = [
            'pending' => '#ff9800',
            'approved' => '#4caf50',
            'rejected' => '#d32f2f',
            'completed' => '#2196f3',
        ];

        return $colors[$status] ?? '#999';
    }

    /**
     * Get status label
     */
    private function get_status_label($status) {
        $labels = [
            'pending' => __('Pendente', 'hng-commerce'),
            'approved' => __('Aprovado', 'hng-commerce'),
            'rejected' => __('Rejeitado', 'hng-commerce'),
            'completed' => __('Concluído', 'hng-commerce'),
        ];

        return $labels[$status] ?? ucfirst($status);
    }
}

// Inicializar apenas se estamos no admin
if (is_admin()) {
    HNG_Refund_Requests_Meta_Box::instance();
}
