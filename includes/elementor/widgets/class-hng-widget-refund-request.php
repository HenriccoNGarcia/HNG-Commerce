<?php
/**
 * Elementor Refund Request Widget
 *
 * Permite que clientes solicitem reembolsos na página Minha Conta
 *
 * @package HNG_Commerce
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Elementor Refund Request Widget
 */
class HNG_Widget_Refund_Request extends \Elementor\Widget_Base {

    /**
     * Get widget name
     */
    public function get_name() {
        return 'hng_refund_request';
    }

    /**
     * Get widget title
     */
    public function get_title() {
        return __('Formulário de Reembolso HNG', 'hng-commerce');
    }

    /**
     * Get widget icon
     */
    public function get_icon() {
        return 'eicon-form-horizontal';
    }

    /**
     * Get widget categories
     */
    public function get_categories() {
        return ['hng-commerce'];
    }

    /**
     * Register widget controls
     */
    protected function register_controls() {
        // Content Tab
        $this->start_controls_section(
            'content_section',
            [
                'label' => __('Configurações', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'show_past_requests',
            [
                'label' => __('Mostrar Solicitações Anteriores', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Sim', 'hng-commerce'),
                'label_off' => __('Não', 'hng-commerce'),
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_order_history',
            [
                'label' => __('Mostrar Histórico de Pedidos', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Sim', 'hng-commerce'),
                'label_off' => __('Não', 'hng-commerce'),
                'default' => 'yes',
            ]
        );

        $this->end_controls_section();

        // Style Tab
        $this->start_controls_section(
            'style_section',
            [
                'label' => __('Estilo', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'button_color',
            [
                'label' => __('Cor do Botão', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#007cba',
                'selectors' => [
                    '{{WRAPPER}} .hng-refund-submit-btn' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Render widget output
     */
    protected function render() {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            echo '<div class="hng-notice-info">';
            echo '<p>' . esc_html__('Você precisa estar logado para solicitar um reembolso.', 'hng-commerce') . '</p>';
            echo '<a href="' . esc_url(wp_login_url(get_permalink())) . '" class="button">' . esc_html__('Fazer Login', 'hng-commerce') . '</a>';
            echo '</div>';
            return;
        }

        $user_id = get_current_user_id();
        $settings = get_option('hng_commerce_settings', []);

        // Check if refund system is enabled
        if (($settings['refund_enabled'] ?? 'yes') !== 'yes') {
            echo '<div class="hng-notice-warning">';
            echo '<p>' . esc_html__('O sistema de reembolsos não está habilitado no momento.', 'hng-commerce') . '</p>';
            echo '</div>';
            return;
        }

        $max_days = intval($settings['refund_max_days'] ?? 30);
        $require_reason = ($settings['refund_require_reason'] ?? 'yes') === 'yes';
        $allow_evidence = ($settings['refund_allow_evidence'] ?? 'yes') === 'yes';

        // Get user orders
        $orders = $this->get_user_orders($user_id, $max_days);

        if (empty($orders)) {
            echo '<div class="hng-notice-info">';
            echo '<p>' . esc_html(
                sprintf(
                    /* translators: %d: number of days */
                    __('Você não tem pedidos elegíveis para reembolso nos últimos %d dias.', 'hng-commerce'),
                    intval($max_days)
                )
            ) . '</p>';
            echo '</div>';
            return;
        }

        // Show past requests if enabled
        if ($this->get_settings('show_past_requests') === 'yes') {
            $this->render_past_requests($user_id);
        }

        // Show refund request form
        $this->render_refund_form($orders, $require_reason, $allow_evidence);
    }

    /**
     * Get user orders
     */
    private function get_user_orders($user_id, $max_days) {
        global $wpdb;

        $cutoff_date = gmdate('Y-m-d', time() - (intval($max_days) * 86400));

        $orders = $wpdb->get_results($wpdb->prepare(
            "SELECT id, order_number, total, status, created_at 
            FROM {$wpdb->prefix}hng_orders 
            WHERE user_id = %d 
            AND created_at >= %s
            AND status IN ('completed', 'paid')
            ORDER BY created_at DESC",
            $user_id,
            $cutoff_date
        ));

        return $orders ?: [];
    }

    /**
     * Render past refund requests
     */
    private function render_past_requests($user_id) {
        global $wpdb;

        $requests = $wpdb->get_results($wpdb->prepare(
            "SELECT id, order_id, amount, reason, status, created_at 
            FROM {$wpdb->prefix}hng_refund_requests 
            WHERE user_id = %d
            ORDER BY created_at DESC
            LIMIT 10",
            $user_id
        ));

        if (empty($requests)) {
            return;
        }

        ?>
        <div class="hng-past-requests">
            <h3><?php esc_html_e('Suas Solicitações de Reembolso', 'hng-commerce'); ?></h3>
            <table class="hng-refund-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Pedido', 'hng-commerce'); ?></th>
                        <th><?php esc_html_e('Valor', 'hng-commerce'); ?></th>
                        <th><?php esc_html_e('Status', 'hng-commerce'); ?></th>
                        <th><?php esc_html_e('Data', 'hng-commerce'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $request) : ?>
                        <tr>
                            <td><?php echo esc_html('#' . $request->order_id); ?></td>
                            <td><?php echo esc_html(hng_format_price($request->amount)); ?></td>
                            <td><span class="hng-status hng-status-<?php echo esc_attr($request->status); ?>"><?php echo esc_html($this->get_status_label($request->status)); ?></span></td>
                            <td><?php echo esc_html(date_i18n('d/m/Y', strtotime($request->created_at))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render refund request form
     */
    private function render_refund_form($orders, $require_reason, $allow_evidence) {
        ?>
        <div class="hng-refund-form-wrapper">
            <h3><?php esc_html_e('Solicitar Reembolso', 'hng-commerce'); ?></h3>

            <form id="hng-refund-request-form" method="POST" enctype="multipart/form-data" class="hng-refund-form">
                <?php wp_nonce_field('hng_refund_request', 'hng_refund_nonce'); ?>

                <!-- Order Selection -->
                <div class="hng-form-group">
                    <label for="refund_order"><?php esc_html_e('Qual pedido você deseja reembolsar?', 'hng-commerce'); ?> <span class="required">*</span></label>
                    <select name="refund_order" id="refund_order" required>
                        <option value=""><?php esc_html_e('Selecione um pedido...', 'hng-commerce'); ?></option>
                        <?php foreach ($orders as $order) : ?>
                            <option value="<?php echo esc_attr($order->id); ?>">
                                <?php
                                printf(
                                    '#%s - %s (%s)',
                                    esc_html($order->order_number),
                                    esc_html(hng_format_price($order->total)),
                                    esc_html(date_i18n('d/m/Y', strtotime($order->created_at)))
                                );
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Refund Amount -->
                <div class="hng-form-group">
                    <label for="refund_amount"><?php esc_html_e('Valor do Reembolso', 'hng-commerce'); ?> <span class="required">*</span></label>
                    <div class="hng-amount-input">
                        <span class="currency-symbol"><?php echo esc_html(hng_get_currency_symbol()); ?></span>
                        <input type="number" 
                               name="refund_amount" 
                               id="refund_amount" 
                               step="0.01" 
                               min="0.01" 
                               required 
                               placeholder="0.00">
                    </div>
                </div>

                <!-- Reason (if required) -->
                <?php if ($require_reason) : ?>
                    <div class="hng-form-group">
                        <label for="refund_reason"><?php esc_html_e('Motivo do Reembolso', 'hng-commerce'); ?> <span class="required">*</span></label>
                        <?php
                        $settings = get_option('hng_commerce_settings', []);
                        $reasons = isset($settings['refund_reasons']) ? (array) $settings['refund_reasons'] : [];

                        if (!empty($reasons)) :
                        ?>
                            <select name="refund_reason" id="refund_reason" required>
                                <option value=""><?php esc_html_e('Selecione um motivo...', 'hng-commerce'); ?></option>
                                <?php foreach ($reasons as $reason) : ?>
                                    <option value="<?php echo esc_attr(trim($reason)); ?>"><?php echo esc_html(trim($reason)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php else : ?>
                            <input type="text" name="refund_reason" id="refund_reason" required placeholder="<?php esc_attr_e('Explique o motivo...', 'hng-commerce'); ?>">
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Description -->
                <div class="hng-form-group">
                    <label for="refund_description"><?php esc_html_e('Detalhes Adicionais', 'hng-commerce'); ?></label>
                    <textarea name="refund_description" 
                              id="refund_description" 
                              rows="4" 
                              placeholder="<?php esc_attr_e('Forneça mais informações sobre seu reembolso...', 'hng-commerce'); ?>"></textarea>
                </div>

                <!-- Evidence Upload -->
                <?php if ($allow_evidence) : ?>
                    <div class="hng-form-group">
                        <label for="refund_evidence"><?php esc_html_e('Adicione Evidências (Opcional)', 'hng-commerce'); ?></label>
                        <input type="file" 
                               name="refund_evidence[]" 
                               id="refund_evidence" 
                               multiple 
                               accept="image/*,.pdf,.doc,.docx"
                               class="hng-file-input">
                        <p class="description">
                            <?php esc_html_e('Você pode enviar screenshots, fotos ou documentos. Max 5 arquivos, 5MB cada.', 'hng-commerce'); ?>
                        </p>
                    </div>
                <?php endif; ?>

                <!-- Submit Button -->
                <div class="hng-form-actions">
                    <button type="submit" class="button button-primary hng-refund-submit-btn">
                        <?php esc_html_e('Solicitar Reembolso', 'hng-commerce'); ?>
                    </button>
                    <div class="hng-loading-spinner" style="display: none;">
                        <span class="spinner"></span>
                        <?php esc_html_e('Processando...', 'hng-commerce'); ?>
                    </div>
                </div>
            </form>
        </div>

        <style>
            .hng-refund-form-wrapper {
                max-width: 600px;
                margin: 20px 0;
            }

            .hng-form-group {
                margin-bottom: 20px;
            }

            .hng-form-group label {
                display: block;
                margin-bottom: 8px;
                font-weight: 500;
                color: #333;
            }

            .hng-form-group input[type="text"],
            .hng-form-group input[type="number"],
            .hng-form-group input[type="file"],
            .hng-form-group select,
            .hng-form-group textarea {
                width: 100%;
                padding: 10px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 14px;
                transition: border-color 0.3s;
            }

            .hng-form-group input[type="text"]:focus,
            .hng-form-group input[type="number"]:focus,
            .hng-form-group input[type="file"]:focus,
            .hng-form-group select:focus,
            .hng-form-group textarea:focus {
                outline: none;
                border-color: #007cba;
                box-shadow: 0 0 0 3px rgba(0, 124, 186, 0.1);
            }

            .hng-amount-input {
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .currency-symbol {
                font-weight: 500;
                min-width: 25px;
            }

            .hng-amount-input input {
                flex: 1;
            }

            .required {
                color: #dc3545;
            }

            .description {
                font-size: 13px;
                color: #666;
                margin-top: 5px;
            }

            .hng-form-actions {
                margin-top: 30px;
                display: flex;
                gap: 10px;
                align-items: center;
            }

            .hng-refund-submit-btn {
                min-width: 200px;
            }

            .hng-loading-spinner {
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .hng-past-requests {
                background: #f9f9f9;
                padding: 20px;
                border-radius: 4px;
                margin-bottom: 30px;
            }

            .hng-refund-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 15px;
            }

            .hng-refund-table th,
            .hng-refund-table td {
                padding: 12px;
                text-align: left;
                border-bottom: 1px solid #ddd;
            }

            .hng-refund-table th {
                background: #f0f0f0;
                font-weight: 600;
            }

            .hng-status {
                display: inline-block;
                padding: 4px 12px;
                border-radius: 4px;
                font-size: 12px;
                font-weight: 500;
            }

            .hng-status-pending {
                background: #fff3cd;
                color: #856404;
            }

            .hng-status-approved {
                background: #d4edda;
                color: #155724;
            }

            .hng-status-rejected {
                background: #f8d7da;
                color: #721c24;
            }

            .hng-notice-info,
            .hng-notice-warning {
                padding: 12px 15px;
                border-left: 4px solid #007cba;
                background: #e7f3ff;
                color: #003d82;
                border-radius: 4px;
                margin-bottom: 20px;
            }

            .hng-notice-warning {
                border-left-color: #dc3545;
                background: #f8d7da;
                color: #721c24;
            }
        </style>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const form = document.getElementById('hng-refund-request-form');
                if (!form) return;

                const orderSelect = form.querySelector('#refund_order');
                const amountInput = form.querySelector('#refund_amount');

                // Update max amount when order changes
                if (orderSelect) {
                    orderSelect.addEventListener('change', function() {
                        const selectedOption = this.options[this.selectedIndex];
                        if (selectedOption.value) {
                            // Extract amount from option text
                            const text = selectedOption.text;
                            const amountMatch = text.match(/R\$\s*([\d,.]+)/);
                            if (amountMatch && amountInput) {
                                const amount = parseFloat(amountMatch[1].replace(',', '.'));
                                amountInput.max = amount;
                            }
                        }
                    });
                }

                // Form submission
                form.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const submitBtn = form.querySelector('.hng-refund-submit-btn');
                    const spinner = form.querySelector('.hng-loading-spinner');

                    if (submitBtn) submitBtn.disabled = true;
                    if (spinner) spinner.style.display = 'flex';

                    const formData = new FormData(form);

                    fetch('<?php echo esc_js(rest_url('hng/v1/refund/request')); ?>', {
                        method: 'POST',
                        headers: {
                            'X-WP-Nonce': formData.get('hng_refund_nonce')
                        },
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('<?php esc_attr_e('Reembolso solicitado com sucesso! Em breve você receberá um email com mais informações.', 'hng-commerce'); ?>');
                            form.reset();
                            location.reload();
                        } else {
                            alert('<?php esc_attr_e('Erro ao solicitar reembolso:', 'hng-commerce'); ?>' + (data.message || ''));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('<?php esc_attr_e('Erro ao processar sua solicitação.', 'hng-commerce'); ?>');
                    })
                    .finally(() => {
                        if (submitBtn) submitBtn.disabled = false;
                        if (spinner) spinner.style.display = 'none';
                    });
                });
            });
        </script>
        <?php
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
