<?php
if (!defined('ABSPATH')) { exit; }

class HNG_Widget_Order_Summary extends HNG_Commerce_Elementor_Widget_Base {
    public function get_name() { return 'hng_order_summary'; }
    public function get_title() { return __('Resumo de Pedido', 'hng-commerce'); }
    public function get_icon() { return 'eicon-order'; }

    protected function register_controls() {
        $this->start_controls_section(
            'content_section',
            [
                'label' => __('ConfiguraçÁµes', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control('order_id', [
            'label' => __('ID do Pedido (opcional)', 'hng-commerce'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'description' => __('Informe um ID de pedido para mostrar o resumo (ou deixe vazio para usar o da URL).', 'hng-commerce')
        ]);

        $this->add_control('show_billing', [
            'label' => __('Mostrar Cobrança', 'hng-commerce'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default' => 'yes',
        ]);

        $this->add_control('show_shipping', [
            'label' => __('Mostrar Entrega', 'hng-commerce'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default' => 'yes',
        ]);

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $order_id = intval($settings['order_id']);
        $is_edit_mode = $this->is_edit_mode();

        if ($order_id === 0) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET parameter for order display in widget, no data modification
            $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
        }
        
        // Modo de edição: buscar o pedido mais recente
        if ($order_id === 0 && $is_edit_mode) {
            $recent_orders = get_posts([
                'post_type' => 'hng_order',
                'numberposts' => 1,
                'post_status' => 'any',
                'orderby' => 'date',
                'order' => 'DESC'
            ]);
            if (!empty($recent_orders)) {
                $order_id = $recent_orders[0]->ID;
            }
        }

        if ($order_id === 0 || !class_exists('HNG_Order')) {
            echo '<div class="hng-order-summary-placeholder elementor-alert elementor-alert-warning">';
            if ($is_edit_mode) {
                echo '<p>' . esc_html__('Informe um ID de pedido nas configurações ou crie um pedido para visualizar.', 'hng-commerce') . '</p>';
            } else {
                echo '<p>' . esc_html__('Nenhum pedido selecionado.', 'hng-commerce') . '</p>';
            }
            echo '</div>';
            return;
        }

        $order = new HNG_Order($order_id);
        if (!$order->get_id()) {
            echo '<div class="hng-order-summary-placeholder elementor-alert elementor-alert-danger"><p>' . esc_html__('Pedido não encontrado. ID: ', 'hng-commerce') . esc_html($order_id) . '</p></div>';
            return;
        }

        ?>
        <div class="hng-order-summary-container">
            <div class="hng-order-header">
                <?php /* translators: %s: order ID */ ?>
                <h2 class="hng-order-heading"><?php printf( esc_html__( 'Pedido #%s', 'hng-commerce'), esc_html( $order_id ) ); ?></h2>
                <span class="hng-order-status"><?php echo esc_html($order->get_status()); ?></span>
            </div>

            <div class="hng-order-info">
                <p><strong><?php esc_html_e( 'Data:', 'hng-commerce'); ?></strong> <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $order->get_date() ) ) ); ?></p>
                <p><strong><?php esc_html_e( 'Total:', 'hng-commerce'); ?></strong> <?php echo wp_kses_post( function_exists('hng_price') ? hng_price( $order->get_total() ) : esc_html($order->get_total()) ); ?></p>
                <p><strong><?php esc_html_e( 'Má étodo de Pagamento:', 'hng-commerce'); ?></strong> <?php echo esc_html( $order->get_payment_method_title() ); ?></p>
            </div>

            <div class="hng-order-items">
                <h3 class="hng-order-heading"><?php esc_html_e( 'Itens do Pedido', 'hng-commerce'); ?></h3>
                <table class="hng-order-items-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Produto', 'hng-commerce'); ?></th>
                            <th><?php esc_html_e( 'Quantidade', 'hng-commerce'); ?></th>
                            <th><?php esc_html_e( 'Preço', 'hng-commerce'); ?></th>
                            <th><?php esc_html_e( 'Subtotal', 'hng-commerce'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order->get_items() as $item) : ?>
                            <tr>
                                <td><?php echo esc_html(isset($item['name']) ? $item['name'] : ''); ?></td>
                                <td><?php echo esc_html(isset($item['quantity']) ? $item['quantity'] : ''); ?></td>
                                <td><?php echo function_exists('hng_price') ? esc_html(hng_price($item['price'])) : esc_html($item['price']); ?></td>
                                <td><?php echo function_exists('hng_price') ? esc_html(hng_price($item['subtotal'])) : esc_html($item['subtotal']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3"><strong><?php esc_html_e( 'Subtotal:', 'hng-commerce'); ?></strong></td>
                            <td><?php echo function_exists('hng_price') ? esc_html(hng_price($order->get_subtotal())) : esc_html($order->get_subtotal()); ?></td>
                        </tr>
                        <tr>
                            <td colspan="3"><strong><?php esc_html_e( 'Total:', 'hng-commerce'); ?></strong></td>
                            <td><?php echo function_exists('hng_price') ? esc_html(hng_price($order->get_total())) : esc_html($order->get_total()); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <?php if ($settings['show_billing'] === 'yes') : ?>
                <div class="hng-order-address">
                    <h3 class="hng-order-heading"><?php esc_html_e( 'Endereço de Cobrança', 'hng-commerce'); ?></h3>
                    <address><?php echo nl2br(esc_html($order->get_billing_address_formatted())); ?></address>
                </div>
            <?php endif; ?>

            <?php if ($settings['show_shipping'] === 'yes' && $order->get_shipping_address()) : ?>
                <div class="hng-order-address">
                    <h3 class="hng-order-heading"><?php esc_html_e( 'Endereço de Entrega', 'hng-commerce'); ?></h3>
                    <address><?php echo nl2br(esc_html($order->get_shipping_address_formatted())); ?></address>
                </div>
            <?php endif; ?>
        </div>

        <style>
            {{WRAPPER}} .hng-order-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; padding-bottom:15px; border-bottom:1px solid #eee; }
            {{WRAPPER}} .hng-order-status { display:inline-block; padding:5px 15px; border-radius:20px; }
            {{WRAPPER}} .hng-order-info { margin-bottom:30px; }
            {{WRAPPER}} .hng-order-items-table { width:100%; border-collapse:collapse; margin-bottom:30px; }
            {{WRAPPER}} .hng-order-items-table th, {{WRAPPER}} .hng-order-items-table td { padding:12px; text-align:left; border-bottom:1px solid #eee; }
            {{WRAPPER}} .hng-order-items-table th { font-weight:600; background:#f8f8f8; }
        </style>
        <?php
    }
}
