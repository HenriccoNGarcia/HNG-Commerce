<?php
/**
 * Orders Page - Gerenciamento de Pedidos
 * 
 * Página dedicada para gerenciar pedidos do sistema HNG Commerce.
 * Exibe lista de pedidos com filtros, paginação e visualização detalhada.
 * 
 * @package HNG_Commerce
 * @since 1.1.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Orders_Page {
    
    /**
     * Render orders page
     */
    public static function render() {
        // Ensure orders list table is loaded
        if (!class_exists('HNG_Orders_List')) {
            if (file_exists(HNG_COMMERCE_PATH . 'includes/admin/class-hng-orders-list.php')) {
                require_once HNG_COMMERCE_PATH . 'includes/admin/class-hng-orders-list.php';
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html__('Classe HNG_Orders_List não encontrada.', 'hng-commerce') . '</p></div>';
                return;
            }
        }
        
        // Handle actions
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Action parameter verified below with nonce
        $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : '';
        
        if ($action === 'view') {
            self::render_order_details();
            return;
        }
        
        if ($action === 'bulk_action' && isset($_POST['hng_orders_nonce'])) {
            self::handle_bulk_actions();
        }
        
        // Render list view
        self::render_list_view();
    }
    
    /**
     * Render list view
     */
    private static function render_list_view() {
        $orders_table = new HNG_Orders_List();
        $orders_table->prepare_items();
        
        ?>
        <div class="wrap hng-admin-wrap">
            <h1 class="hng-page-title">
                <span class="dashicons dashicons-cart"></span>
                <?php esc_html_e('Pedidos', 'hng-commerce'); ?>
            </h1>
            
            <div class="hng-card" style="margin-top: 20px;">
                <div class="hng-card-content">
                    <form method="post">
                        <?php
                        $orders_table->views();
                        $orders_table->search_box(__('Buscar pedidos', 'hng-commerce'), 'orders');
                        $orders_table->display();
                        ?>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render order details view
     */
    private static function render_order_details() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET parameter, order data display only
        if (!isset($_GET['order_id'])) {
            echo '<div class="notice notice-error"><p>' . esc_html__('ID do pedido não fornecido.', 'hng-commerce') . '</p></div>';
            return;
        }
        
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET parameter for order display
        $order_id = absint(wp_unslash($_GET['order_id']));
        
        if ($order_id <= 0) {
            echo '<div class="notice notice-error"><p>' . esc_html__('ID do pedido inválido.', 'hng-commerce') . '</p></div>';
            return;
        }
        
        global $wpdb;
        
        // Get order
        $orders_table = hng_db_full_table_name('hng_orders');
        $orders_table_sql = '`' . str_replace('`', '', $orders_table) . '`';
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Order details query with sanitized table name
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name sanitized via hng_db_full_table_name and backtick escaping
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$orders_table_sql} WHERE id = %d",
            $order_id
        ));
        
        if (!$order) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Pedido não encontrado.', 'hng-commerce') . '</p></div>';
            return;
        }
        
        // Get order items
        $items_table = hng_db_full_table_name('hng_order_items');
        $items_table_sql = '`' . str_replace('`', '', $items_table) . '`';
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Order items query with sanitized table name
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name sanitized via hng_db_full_table_name and backtick escaping
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$items_table_sql} WHERE order_id = %d",
            $order_id
        ));
        
        // Detectar tipos de produto no pedido
        $has_physical = false;
        $has_digital = false;
        $has_quote = false;
        $has_appointment = false;
        $has_subscription = false;
        
        // Verificar também na coluna product_type da tabela de pedidos
        if (!empty($order->product_type) && $order->product_type === 'quote') {
            $has_quote = true;
        }
        
        foreach ($items as $item) {
            // Primeiro verificar na tabela hng_order_items
            $product_type = !empty($item->product_type) ? $item->product_type : '';
            
            // Fallback para post meta do produto
            if (empty($product_type) && !empty($item->product_id)) {
                $product_type = get_post_meta($item->product_id, '_hng_product_type', true);
            }
            
            if (empty($product_type)) {
                $product_type = 'physical'; // default
            }
            
            switch ($product_type) {
                case 'physical':
                    $has_physical = true;
                    break;
                case 'digital':
                    $has_digital = true;
                    break;
                case 'quote':
                    $has_quote = true;
                    break;
                case 'appointment':
                    $has_appointment = true;
                    break;
                case 'subscription':
                    $has_subscription = true;
                    break;
            }
        }
        
        // Determinar se precisa de envio (somente produtos físicos)
        $needs_shipping = $has_physical;
        
        // Determinar se precisa de chat (orçamentos e agendamentos)
        $needs_chat = $has_quote || $has_appointment;
        
        // Debug temporário
        error_log("HNG Order Debug - Order #{$order_id}: product_type={$order->product_type}, has_quote=" . ($has_quote ? 'true' : 'false') . ", needs_chat=" . ($needs_chat ? 'true' : 'false'));
        
        ?>
        <div class="wrap hng-admin-wrap">
            <h1 class="hng-page-title">
                <span class="dashicons dashicons-cart"></span>
                <?php
                /* translators: %d: Order ID */
                echo esc_html(sprintf(__('Pedido #%d', 'hng-commerce'), $order_id));
                ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=hng-orders')); ?>" class="page-title-action">
                    <?php esc_html_e('← Voltar', 'hng-commerce'); ?>
                </a>
            </h1>
            
            <div class="hng-grid hng-grid-2" style="margin-top: 20px;">
                <!-- Order Details -->
                <div class="hng-card">
                    <div class="hng-card-header">
                        <h2 class="hng-card-title"><?php esc_html_e('Detalhes do Pedido', 'hng-commerce'); ?></h2>
                    </div>
                    <div class="hng-card-content">
                        <table class="widefat striped">
                            <tbody>
                                <tr>
                                    <th><?php esc_html_e('Status:', 'hng-commerce'); ?></th>
                                    <td>
                                        <?php
                                        $status_labels = array(
                                            'pending' => __('Pendente', 'hng-commerce'),
                                            'processing' => __('Processando', 'hng-commerce'),
                                            'completed' => __('Concluído', 'hng-commerce'),
                                            'cancelled' => __('Cancelado', 'hng-commerce'),
                                            'refunded' => __('Reembolsado', 'hng-commerce'),
                                        );
                                        $status_badge = isset($status_labels[$order->status]) ? $status_labels[$order->status] : ucfirst($order->status);
                                        echo '<span class="hng-badge hng-badge-' . esc_attr($order->status) . '">' . esc_html($status_badge) . '</span>';
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e('Data:', 'hng-commerce'); ?></th>
                                    <td><?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $order->created_at)); ?></td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e('Cliente:', 'hng-commerce'); ?></th>
                                    <td>
                                        <?php 
                                        $customer_name = trim(($order->billing_first_name ?? '') . ' ' . ($order->billing_last_name ?? ''));
                                        if (empty($customer_name)) {
                                            $customer_name = $order->customer_name ?? __('N/A', 'hng-commerce');
                                        }
                                        $customer_email = $order->billing_email ?? ($order->customer_email ?? '');
                                        echo esc_html($customer_name); 
                                        ?><br>
                                        <small><?php echo esc_html($customer_email); ?></small>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e('Total:', 'hng-commerce'); ?></th>
                                    <td><strong><?php echo esc_html('R$ ' . number_format($order->total, 2, ',', '.')); ?></strong></td>
                                </tr>
                                <?php if (!empty($order->payment_method)): ?>
                                <tr>
                                    <th><?php esc_html_e('Método de Pagamento:', 'hng-commerce'); ?></th>
                                    <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $order->payment_method))); ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <th><?php esc_html_e('Tipo de Produto:', 'hng-commerce'); ?></th>
                                    <td>
                                        <?php
                                        $type_labels = [];
                                        if ($has_physical) $type_labels[] = '📦 ' . __('Físico', 'hng-commerce');
                                        if ($has_digital) $type_labels[] = '💾 ' . __('Digital', 'hng-commerce');
                                        if ($has_quote) $type_labels[] = '📋 ' . __('Orçamento', 'hng-commerce');
                                        if ($has_appointment) $type_labels[] = '📅 ' . __('Agendamento', 'hng-commerce');
                                        if ($has_subscription) $type_labels[] = '🔄 ' . __('Assinatura', 'hng-commerce');
                                        echo esc_html(implode(', ', $type_labels) ?: __('N/A', 'hng-commerce'));
                                        ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Customer Address (only for physical products) -->
                <?php if ($needs_shipping): ?>
                <div class="hng-card">
                    <div class="hng-card-header">
                        <h2 class="hng-card-title"><?php esc_html_e('Endereço de Entrega', 'hng-commerce'); ?></h2>
                    </div>
                    <div class="hng-card-content">
                        <?php
                        // Primeiro tenta os novos campos de billing
                        $has_new_billing = !empty($order->billing_address_1);
                        
                        if ($has_new_billing):
                        ?>
                            <address>
                                <?php
                                echo esc_html($order->billing_address_1);
                                if (!empty($order->billing_number)) {
                                    echo ', ' . esc_html($order->billing_number);
                                }
                                echo '<br>';
                                if (!empty($order->billing_address_2)) {
                                    echo esc_html($order->billing_address_2) . '<br>';
                                }
                                if (!empty($order->billing_neighborhood)) {
                                    echo esc_html($order->billing_neighborhood) . '<br>';
                                }
                                if (!empty($order->billing_city) && !empty($order->billing_state)) {
                                    echo esc_html($order->billing_city) . ' - ' . esc_html($order->billing_state) . '<br>';
                                }
                                if (!empty($order->billing_postcode)) {
                                    echo esc_html__('CEP:', 'hng-commerce') . ' ' . esc_html($order->billing_postcode) . '<br>';
                                }
                                if (!empty($order->billing_phone)) {
                                    echo esc_html__('Tel:', 'hng-commerce') . ' ' . esc_html($order->billing_phone) . '<br>';
                                }
                                if (!empty($order->billing_cpf)) {
                                    echo esc_html__('CPF:', 'hng-commerce') . ' ' . esc_html($order->billing_cpf);
                                }
                                ?>
                            </address>
                        <?php 
                        else:
                            // Fallback para endereço serializado antigo
                            $billing_address = maybe_unserialize($order->billing_address ?? '');
                            if (!empty($billing_address) && is_array($billing_address)):
                        ?>
                            <address>
                                <?php
                                if (!empty($billing_address['street'])) {
                                    echo esc_html($billing_address['street']);
                                    if (!empty($billing_address['number'])) {
                                        echo ', ' . esc_html($billing_address['number']);
                                    }
                                    echo '<br>';
                                }
                                if (!empty($billing_address['complement'])) {
                                    echo esc_html($billing_address['complement']) . '<br>';
                                }
                                if (!empty($billing_address['district'])) {
                                    echo esc_html($billing_address['district']) . '<br>';
                                }
                                if (!empty($billing_address['city']) && !empty($billing_address['state'])) {
                                    echo esc_html($billing_address['city']) . ' - ' . esc_html($billing_address['state']) . '<br>';
                                }
                                if (!empty($billing_address['postcode'])) {
                                    echo esc_html__('CEP:', 'hng-commerce') . ' ' . esc_html($billing_address['postcode']);
                                }
                                ?>
                            </address>
                        <?php else: ?>
                            <p><?php esc_html_e('Endereço não disponível.', 'hng-commerce'); ?></p>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Shipping & Label Section (only for physical products) -->
            <?php
            $shipping_method = get_post_meta($order_id, '_shipping_method', true);
            $shipping_data = get_post_meta($order_id, '_shipping_data', true);
            $tracking_code = get_post_meta($order_id, '_hng_tracking_code', true);
            $label_data = get_post_meta($order_id, '_hng_shipping_label', true);
            
            if ($needs_shipping):
            ?>
            <div class="hng-card" style="margin-top: 20px;">
                <div class="hng-card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h2 class="hng-card-title">
                        <span class="dashicons dashicons-car" style="vertical-align: middle;"></span>
                        <?php esc_html_e('Envio e Etiqueta', 'hng-commerce'); ?>
                    </h2>
                </div>
                <div class="hng-card-content">
                    <table class="widefat striped">
                        <tbody>
                            <?php if (!empty($shipping_method)): ?>
                            <tr>
                                <th style="width: 200px;"><?php esc_html_e('Método de Envio:', 'hng-commerce'); ?></th>
                                <td><?php echo esc_html(ucfirst(str_replace(['_', ':'], [' ', ' - '], $shipping_method))); ?></td>
                            </tr>
                            <?php endif; ?>
                            
                            <?php 
                            $shipping_cost = $order->shipping_total ?? ($order->shipping_cost ?? ($shipping_data['cost'] ?? 0));
                            if (!empty($shipping_cost)): 
                            ?>
                            <tr>
                                <th><?php esc_html_e('Custo do Frete:', 'hng-commerce'); ?></th>
                                <td><?php echo esc_html('R$ ' . number_format(floatval($shipping_cost), 2, ',', '.')); ?></td>
                            </tr>
                            <?php endif; ?>
                            
                            <tr>
                                <th><?php esc_html_e('Código de Rastreamento:', 'hng-commerce'); ?></th>
                                <td>
                                    <?php if (!empty($tracking_code)): ?>
                                        <code style="font-size: 14px; padding: 5px 10px; background: #f0f0f0; border-radius: 4px;"><?php echo esc_html($tracking_code); ?></code>
                                        <a href="https://www.linkcorreios.com.br/?id=<?php echo esc_attr($tracking_code); ?>" target="_blank" class="button button-small" style="margin-left: 10px;">
                                            <?php esc_html_e('Rastrear', 'hng-commerce'); ?>
                                        </a>
                                    <?php else: ?>
                                        <em><?php esc_html_e('Não disponível', 'hng-commerce'); ?></em>
                                        <input type="text" id="hng_tracking_code" placeholder="<?php esc_attr_e('Inserir código', 'hng-commerce'); ?>" style="width: 200px; margin-left: 10px;">
                                        <button type="button" class="button button-small" id="hng_save_tracking">
                                            <?php esc_html_e('Salvar', 'hng-commerce'); ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            
                            <tr>
                                <th><?php esc_html_e('Etiqueta de Envio:', 'hng-commerce'); ?></th>
                                <td>
                                    <?php if (!empty($label_data)): ?>
                                        <?php if ($label_data['status'] === 'generated' && !empty($label_data['label_url'])): ?>
                                            <span class="dashicons dashicons-yes-alt" style="color: #46b450; vertical-align: middle;"></span>
                                            <strong style="color: #46b450;"><?php esc_html_e('Etiqueta Gerada', 'hng-commerce'); ?></strong>
                                            <a href="<?php echo esc_url($label_data['label_url']); ?>" target="_blank" class="button button-primary" style="margin-left: 15px;">
                                                <span class="dashicons dashicons-download" style="vertical-align: middle;"></span>
                                                <?php esc_html_e('Baixar Etiqueta', 'hng-commerce'); ?>
                                            </a>
                                        <?php elseif ($label_data['status'] === 'manual'): ?>
                                            <span class="dashicons dashicons-warning" style="color: #ffb900; vertical-align: middle;"></span>
                                            <?php echo esc_html($label_data['message'] ?? __('Geração manual necessária.', 'hng-commerce')); ?>
                                            <?php if (!empty($label_data['manual_url'])): ?>
                                                <a href="<?php echo esc_url($label_data['manual_url']); ?>" target="_blank" class="button" style="margin-left: 10px;">
                                                    <?php esc_html_e('Acessar Portal', 'hng-commerce'); ?>
                                                </a>
                                            <?php endif; ?>
                                        <?php elseif (!empty($label_data['portal_url'])): ?>
                                            <span class="dashicons dashicons-external" style="color: #0073aa; vertical-align: middle;"></span>
                                            <?php echo esc_html($label_data['message'] ?? ''); ?>
                                            <a href="<?php echo esc_url($label_data['portal_url']); ?>" target="_blank" class="button" style="margin-left: 10px;">
                                                <?php esc_html_e('Acessar Portal', 'hng-commerce'); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="dashicons dashicons-clock" style="color: #ffb900; vertical-align: middle;"></span>
                                            <?php echo esc_html($label_data['message'] ?? __('Processando...', 'hng-commerce')); ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <em><?php esc_html_e('Nenhuma etiqueta gerada', 'hng-commerce'); ?></em>
                                        <button type="button" class="button button-primary" id="hng_generate_label" style="margin-left: 15px;">
                                            <span class="dashicons dashicons-printer" style="vertical-align: middle;"></span>
                                            <?php esc_html_e('Gerar Etiqueta', 'hng-commerce'); ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                // Gerar etiqueta
                $('#hng_generate_label').on('click', function() {
                    var $btn = $(this);
                    $btn.prop('disabled', true).text('<?php echo esc_js(__('Gerando...', 'hng-commerce')); ?>');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'hng_generate_shipping_label',
                            order_id: <?php echo (int) $order_id; ?>,
                            nonce: '<?php echo esc_js(wp_create_nonce('hng_shipping_label')); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert(response.data.message || '<?php echo esc_js(__('Erro ao gerar etiqueta.', 'hng-commerce')); ?>');
                                $btn.prop('disabled', false).html('<span class="dashicons dashicons-printer" style="vertical-align: middle;"></span> <?php echo esc_js(__('Gerar Etiqueta', 'hng-commerce')); ?>');
                            }
                        },
                        error: function() {
                            alert('<?php echo esc_js(__('Erro de conexão.', 'hng-commerce')); ?>');
                            $btn.prop('disabled', false).html('<span class="dashicons dashicons-printer" style="vertical-align: middle;"></span> <?php echo esc_js(__('Gerar Etiqueta', 'hng-commerce')); ?>');
                        }
                    });
                });
                
                // Salvar código de rastreamento
                $('#hng_save_tracking').on('click', function() {
                    var code = $('#hng_tracking_code').val().trim();
                    if (!code) {
                        alert('<?php echo esc_js(__('Digite um código de rastreamento.', 'hng-commerce')); ?>');
                        return;
                    }
                    
                    var $btn = $(this);
                    $btn.prop('disabled', true).text('<?php echo esc_js(__('Salvando...', 'hng-commerce')); ?>');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'hng_save_tracking_code',
                            order_id: <?php echo (int) $order_id; ?>,
                            tracking_code: code,
                            nonce: '<?php echo esc_js(wp_create_nonce('hng_shipping_label')); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert(response.data.message || '<?php echo esc_js(__('Erro ao salvar.', 'hng-commerce')); ?>');
                                $btn.prop('disabled', false).text('<?php echo esc_js(__('Salvar', 'hng-commerce')); ?>');
                            }
                        },
                        error: function() {
                            alert('<?php echo esc_js(__('Erro de conexão.', 'hng-commerce')); ?>');
                            $btn.prop('disabled', false).text('<?php echo esc_js(__('Salvar', 'hng-commerce')); ?>');
                        }
                    });
                });
            });
            </script>
            <?php endif; ?>
            
            <?php 
            // ==========================================
            // SEÇÃO ESPECIAL PARA ORÇAMENTOS
            // ==========================================
            if ($has_quote): 
                $quote_status = get_post_meta($order_id, '_quote_status', true) ?: 'pending';
                $quote_shipping = get_post_meta($order_id, '_quote_shipping_cost', true);
                $quote_needs_shipping = get_post_meta($order_id, '_quote_needs_shipping', true);
                $quote_approved = get_post_meta($order_id, '_quote_approved', true);
                $quote_price = $order->subtotal ?: 0;
                $quote_notes = $order->notes ?: '';
            ?>
            <div class="hng-card" style="margin-top: 20px;">
                <div class="hng-card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h2 class="hng-card-title">
                        <span class="dashicons dashicons-clipboard" style="vertical-align: middle;"></span>
                        <?php esc_html_e('Gerenciamento do Orçamento', 'hng-commerce'); ?>
                    </h2>
                    <span class="hng-badge hng-badge-<?php echo esc_attr($quote_status); ?>">
                        <?php 
                        $quote_status_labels = [
                            'pending' => __('Aguardando Orçamento', 'hng-commerce'),
                            'quoted' => __('Orçamento Enviado', 'hng-commerce'),
                            'approved' => __('Aprovado pelo Cliente', 'hng-commerce'),
                            'rejected' => __('Rejeitado', 'hng-commerce'),
                            'paid' => __('Pago', 'hng-commerce'),
                        ];
                        echo esc_html($quote_status_labels[$quote_status] ?? ucfirst($quote_status)); 
                        ?>
                    </span>
                </div>
                <div class="hng-card-content">
                    <?php if (!empty($quote_notes)) : ?>
                    <div class="hng-quote-notes" style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                        <h4 style="margin: 0 0 10px 0; display: flex; align-items: center; gap: 8px;">
                            <span class="dashicons dashicons-editor-quote"></span>
                            <?php esc_html_e('Observações do Cliente:', 'hng-commerce'); ?>
                        </h4>
                        <p style="margin: 0; white-space: pre-wrap;"><?php echo esc_html($quote_notes); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="hng-quote-management" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <!-- Definir Preço do Orçamento -->
                        <div class="hng-quote-price-config">
                            <h4><?php esc_html_e('Valor do Orçamento', 'hng-commerce'); ?></h4>
                            
                            <div style="margin-bottom: 15px;">
                                <label for="quote_price"><?php esc_html_e('Valor do Produto/Serviço:', 'hng-commerce'); ?></label>
                                <div style="display: flex; gap: 10px; align-items: center;">
                                    <span>R$</span>
                                    <input type="number" id="quote_price" name="quote_price" 
                                           value="<?php echo esc_attr($quote_price); ?>" 
                                           step="0.01" min="0" style="width: 150px; font-size: 18px; font-weight: bold;">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Configuração de Frete -->
                        <div class="hng-quote-shipping-config">
                            <h4><?php esc_html_e('Configuração de Frete', 'hng-commerce'); ?></h4>
                            
                            <div style="margin-bottom: 15px;">
                                <label>
                                    <input type="checkbox" id="quote_needs_shipping" name="quote_needs_shipping" 
                                           value="1" <?php checked($quote_needs_shipping, '1'); ?>>
                                    <?php esc_html_e('Este orçamento precisa de entrega/frete', 'hng-commerce'); ?>
                                </label>
                            </div>
                            
                            <div id="quote_shipping_fields" style="<?php echo $quote_needs_shipping !== '1' ? 'display:none;' : ''; ?>">
                                <div style="margin-bottom: 15px;">
                                    <label for="quote_shipping_cost"><?php esc_html_e('Valor do Frete:', 'hng-commerce'); ?></label>
                                    <div style="display: flex; gap: 10px; align-items: center;">
                                        <span>R$</span>
                                        <input type="number" id="quote_shipping_cost" name="quote_shipping_cost" 
                                               value="<?php echo esc_attr($quote_shipping ?: '0'); ?>" 
                                               step="0.01" min="0" style="width: 150px;">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Total e Botão Salvar -->
                    <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong style="font-size: 18px;"><?php esc_html_e('Total do Orçamento:', 'hng-commerce'); ?></strong>
                                <span id="quote_total_display" style="font-size: 24px; color: #2271b1; font-weight: bold; margin-left: 10px;">
                                    R$ <?php echo number_format($quote_price + floatval($quote_shipping), 2, ',', '.'); ?>
                                </span>
                            </div>
                            <button type="button" class="button button-primary button-hero" id="save_quote_config">
                                <span class="dashicons dashicons-clipboard" style="vertical-align: middle;"></span>
                                <?php esc_html_e('Definir Orçamento', 'hng-commerce'); ?>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Ações do Orçamento -->
                    <div class="hng-quote-actions" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
                        <h4><?php esc_html_e('Ações', 'hng-commerce'); ?></h4>
                        
                        <?php if ($quote_status === 'pending'): ?>
                        <p class="description">
                            <?php esc_html_e('Configure o valor do orçamento e frete acima. Ao clicar em "Definir Orçamento", o valor será enviado automaticamente para o cliente aprovar.', 'hng-commerce'); ?>
                        </p>
                        <?php elseif ($quote_status === 'quoted'): ?>
                        <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin-bottom: 15px; border-radius: 4px;">
                            <p style="margin: 0 0 10px 0; color: #856404;">
                                <span class="dashicons dashicons-clock" style="vertical-align: middle;"></span>
                                <?php esc_html_e('Aguardando aprovação do cliente. O cliente pode aprovar ou rejeitar na área "Minha Conta > Pedidos".', 'hng-commerce'); ?>
                            </p>
                            <p style="margin: 0; font-size: 12px; color: #666;">
                                <?php esc_html_e('Você ainda pode editar o valor acima e salvar. O cliente verá o valor atualizado.', 'hng-commerce'); ?>
                            </p>
                        </div>
                        <button type="button" class="button button-secondary" id="resend_quote_notification" style="margin-top: 5px;">
                            <span class="dashicons dashicons-email-alt" style="vertical-align: middle;"></span>
                            <?php esc_html_e('Reenviar Notificação por Email', 'hng-commerce'); ?>
                        </button>
                        <?php elseif ($quote_status === 'approved'): ?>
                        <p class="description" style="color: #46b450;">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php esc_html_e('Cliente aprovou o orçamento! Aguardando pagamento.', 'hng-commerce'); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                // Update total display
                function updateTotal() {
                    var price = parseFloat($('#quote_price').val()) || 0;
                    var shipping = $('#quote_needs_shipping').is(':checked') ? (parseFloat($('#quote_shipping_cost').val()) || 0) : 0;
                    var total = price + shipping;
                    $('#quote_total_display').text('R$ ' + total.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                }
                
                // Toggle shipping fields
                $('#quote_needs_shipping').on('change', function() {
                    if ($(this).is(':checked')) {
                        $('#quote_shipping_fields').slideDown();
                    } else {
                        $('#quote_shipping_fields').slideUp();
                    }
                    updateTotal();
                });
                
                // Update total on price/shipping change
                $('#quote_price, #quote_shipping_cost').on('input change', updateTotal);
                
                // Save and send quote config (price + shipping) - all in one
                $('#save_quote_config').on('click', function() {
                    var price = parseFloat($('#quote_price').val()) || 0;
                    if (price <= 0) {
                        alert('<?php echo esc_js(__('Por favor, defina o valor do orçamento antes de prosseguir.', 'hng-commerce')); ?>');
                        $('#quote_price').focus();
                        return;
                    }
                    
                    var $btn = $(this);
                    $btn.prop('disabled', true).text('<?php echo esc_js(__('Definindo...', 'hng-commerce')); ?>');
                    
                    // First save the quote config
                    $.post(ajaxurl, {
                        action: 'hng_save_quote_shipping',
                        order_id: <?php echo (int) $order_id; ?>,
                        quote_price: $('#quote_price').val(),
                        needs_shipping: $('#quote_needs_shipping').is(':checked') ? 1 : 0,
                        shipping_cost: $('#quote_shipping_cost').val(),
                        nonce: '<?php echo esc_js(wp_create_nonce('hng_quote_shipping')); ?>'
                    }, function(response) {
                        if (!response.success) {
                            $btn.prop('disabled', false).html('<span class="dashicons dashicons-clipboard" style="vertical-align: middle;"></span> <?php echo esc_js(__('Definir Orçamento', 'hng-commerce')); ?>');
                            alert(response.data.message || '<?php echo esc_js(__('Erro ao salvar orçamento.', 'hng-commerce')); ?>');
                            return;
                        }
                        
                        // Then automatically send to client if status is 'pending'
                        var quoteStatus = '<?php echo esc_js($quote_status); ?>';
                        if (quoteStatus === 'pending' || quoteStatus === 'quoted') {
                            $.post(ajaxurl, {
                                action: 'hng_send_quote_to_client',
                                order_id: <?php echo (int) $order_id; ?>,
                                nonce: '<?php echo esc_js(wp_create_nonce('hng_send_quote')); ?>'
                            }, function(sendResponse) {
                                $btn.prop('disabled', false).html('<span class="dashicons dashicons-clipboard" style="vertical-align: middle;"></span> <?php echo esc_js(__('Definir Orçamento', 'hng-commerce')); ?>');
                                if (sendResponse.success) {
                                    alert('<?php echo esc_js(__('Orçamento definido e enviado com sucesso! Cliente receberá um email.', 'hng-commerce')); ?>');
                                    location.reload();
                                } else {
                                    alert('<?php echo esc_js(__('Orçamento salvo, mas houve erro ao enviar. Tente novamente.', 'hng-commerce')); ?>');
                                }
                            });
                        } else {
                            $btn.prop('disabled', false).html('<span class="dashicons dashicons-clipboard" style="vertical-align: middle;"></span> <?php echo esc_js(__('Definir Orçamento', 'hng-commerce')); ?>');
                            alert('<?php echo esc_js(__('Orçamento definido com sucesso!', 'hng-commerce')); ?>');
                            location.reload();
                        }
                    });
                });
                
                // Resend quote notification
                $('#resend_quote_notification').on('click', function() {
                    if (!confirm('<?php echo esc_js(__('Reenviar notificação por email para o cliente?', 'hng-commerce')); ?>')) {
                        return;
                    }
                    
                    var $btn = $(this);
                    $btn.prop('disabled', true);
                    
                    // First save the updated quote config
                    $.post(ajaxurl, {
                        action: 'hng_save_quote_shipping',
                        order_id: <?php echo (int) $order_id; ?>,
                        quote_price: $('#quote_price').val(),
                        needs_shipping: $('#quote_needs_shipping').is(':checked') ? 1 : 0,
                        shipping_cost: $('#quote_shipping_cost').val(),
                        nonce: '<?php echo esc_js(wp_create_nonce('hng_quote_shipping')); ?>'
                    }, function() {
                        // Then resend notification
                        $.post(ajaxurl, {
                            action: 'hng_resend_quote_notification',
                            order_id: <?php echo (int) $order_id; ?>,
                            nonce: '<?php echo esc_js(wp_create_nonce('hng_send_quote')); ?>'
                        }, function(response) {
                            $btn.prop('disabled', false);
                            if (response.success) {
                                alert('<?php echo esc_js(__('Notificação reenviada com sucesso!', 'hng-commerce')); ?>');
                            } else {
                                alert(response.data.message || '<?php echo esc_js(__('Erro ao reenviar.', 'hng-commerce')); ?>');
                            }
                        });
                    });
                });
            });
            </script>
            <?php endif; ?>
            
            <?php 
            // ==========================================
            // SEÇÃO DE CHAT PARA ORÇAMENTOS E AGENDAMENTOS
            // ==========================================
            if ($needs_chat): 
                $chat_type = $has_quote ? 'quote' : 'appointment';
                $chat_title = $has_quote ? __('Chat do Orçamento', 'hng-commerce') : __('Chat do Agendamento', 'hng-commerce');
                
                // Para orçamentos, precisamos usar o Post ID para o chat (não o DB order ID)
                // porque o cliente usa o Post ID para enviar mensagens
                $chat_order_id = $order_id;
                
                if ($has_quote && !empty($order->order_number)) {
                    // Tentar encontrar o post ID pelo order_number
                    // O order_number geralmente é ORC-000XXX onde XXX é o post ID
                    $post_id_from_number = preg_replace('/^ORC-0*/', '', $order->order_number);
                    if ($post_id_from_number && is_numeric($post_id_from_number)) {
                        $post = get_post(intval($post_id_from_number));
                        if ($post && $post->post_type === 'hng_order') {
                            $chat_order_id = intval($post_id_from_number);
                        }
                    }
                    
                    // Fallback: buscar post que tenha _hng_db_order_id = $order_id
                    if ($chat_order_id === $order_id) {
                        $related_post = get_posts([
                            'post_type' => 'hng_order',
                            'posts_per_page' => 1,
                            'meta_query' => [
                                [
                                    'key' => '_hng_db_order_id',
                                    'value' => $order_id,
                                    'compare' => '='
                                ]
                            ]
                        ]);
                        if (!empty($related_post)) {
                            $chat_order_id = $related_post[0]->ID;
                        }
                    }
                }
            ?>
            <div class="hng-card" style="margin-top: 20px;">
                <div class="hng-card-header">
                    <h2 class="hng-card-title">
                        <span class="dashicons dashicons-format-chat" style="vertical-align: middle;"></span>
                        <?php echo esc_html($chat_title); ?>
                    </h2>
                </div>
                <div class="hng-card-content" style="padding: 0;">
                    <?php self::render_order_chat($chat_order_id, $order, $chat_type); ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Order Items -->
            <div class="hng-card" style="margin-top: 20px;">
                <div class="hng-card-header">
                    <h2 class="hng-card-title"><?php esc_html_e('Itens do Pedido', 'hng-commerce'); ?></h2>
                </div>
                <div class="hng-card-content">
                    <?php if (!empty($items)): ?>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Produto', 'hng-commerce'); ?></th>
                                    <th><?php esc_html_e('Quantidade', 'hng-commerce'); ?></th>
                                    <th><?php esc_html_e('Preço Unit.', 'hng-commerce'); ?></th>
                                    <th><?php esc_html_e('Subtotal', 'hng-commerce'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td><?php echo esc_html($item->product_name); ?></td>
                                        <td><?php echo esc_html($item->quantity); ?></td>
                                        <td><?php echo esc_html('R$ ' . number_format($item->price, 2, ',', '.')); ?></td>
                                        <td><?php echo esc_html('R$ ' . number_format($item->price * $item->quantity, 2, ',', '.')); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3" style="text-align: right;"><?php esc_html_e('Total:', 'hng-commerce'); ?></th>
                                    <th><?php echo esc_html('R$ ' . number_format($order->total, 2, ',', '.')); ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    <?php else: ?>
                        <p><?php esc_html_e('Nenhum item encontrado neste pedido.', 'hng-commerce'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle bulk actions
     */
    private static function handle_bulk_actions() {
        // Verify nonce
        if (!isset($_POST['hng_orders_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['hng_orders_nonce'])), 'hng_bulk_orders')) {
            wp_die(esc_html__('Erro de segurança. Por favor, tente novamente.', 'hng-commerce'));
        }
        
        // Get bulk action
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above
        $bulk_action = isset($_POST['action']) && $_POST['action'] !== '-1' ? sanitize_text_field(wp_unslash($_POST['action'])) : (isset($_POST['action2']) ? sanitize_text_field(wp_unslash($_POST['action2'])) : '');
        
        if (empty($bulk_action)) {
            return;
        }
        
        // Get selected orders
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above
        $order_ids = isset($_POST['order']) ? array_map('absint', wp_unslash($_POST['order'])) : array();
        
        if (empty($order_ids)) {
            return;
        }
        
        global $wpdb;
        $orders_table = hng_db_full_table_name('hng_orders');
        $orders_table_sql = '`' . str_replace('`', '', $orders_table) . '`';
        
        // Map bulk actions to statuses
        $status_map = array(
            'mark_processing' => 'processing',
            'mark_completed' => 'completed',
            'mark_cancelled' => 'cancelled',
        );
        
        if (isset($status_map[$bulk_action])) {
            $new_status = $status_map[$bulk_action];
            
            foreach ($order_ids as $order_id) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk update with sanitized table name
                // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name sanitized via hng_db_full_table_name and backtick escaping
                $wpdb->update(
                    $orders_table,
                    array('status' => $new_status),
                    array('id' => $order_id),
                    array('%s'),
                    array('%d')
                );
            }
            
            // Show success message
            add_action('admin_notices', function() use ($order_ids, $new_status) {
                echo '<div class="notice notice-success is-dismissible"><p>';
                /* translators: 1: Number of orders updated, 2: New status */
                echo esc_html(sprintf(__('%1$d pedido(s) atualizado(s) para status: %2$s', 'hng-commerce'), count($order_ids), $new_status));
                echo '</p></div>';
            });
        }
    }
    
    /**
     * Render embedded chat for orders (quotes/appointments)
     * 
     * @param int $order_id Order ID
     * @param object $order Order object
     * @param string $chat_type Type of chat (quote/appointment)
     */
    private static function render_order_chat($order_id, $order, $chat_type = 'quote') {
        global $wpdb;
        
        // Get messages from quote_messages table
        $table = $wpdb->prefix . 'hng_quote_messages';
        
        // Check if table exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        
        if (!$table_exists) {
            // Create table if needed
            if (class_exists('HNG_Quote_Chat')) {
                HNG_Quote_Chat::instance()->create_table();
            }
        }
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE order_id = %d ORDER BY created_at ASC",
            $order_id
        ));
        
        $customer_name = trim(($order->billing_first_name ?? '') . ' ' . ($order->billing_last_name ?? ''));
        if (empty($customer_name)) {
            $customer_name = $order->customer_name ?? __('Cliente', 'hng-commerce');
        }
        $customer_email = $order->billing_email ?? ($order->customer_email ?? '');
        $customer_id = $order->customer_id ?? 0;
        
        $chat_nonce = wp_create_nonce('hng_order_chat');
        ?>
        <input type="hidden" id="hng_order_chat_nonce" value="<?php echo esc_attr($chat_nonce); ?>">
        <div class="hng-order-chat" id="hng-order-chat" data-order-id="<?php echo esc_attr($order_id); ?>" data-chat-type="<?php echo esc_attr($chat_type); ?>">
            <!-- Chat Header -->
            <div class="hng-ochat-header">
                <div class="hng-ochat-customer">
                    <?php echo get_avatar($customer_id, 40); ?>
                    <div class="hng-ochat-customer-info">
                        <strong><?php echo esc_html($customer_name); ?></strong>
                        <span><?php echo esc_html($customer_email); ?></span>
                    </div>
                </div>
                <div class="hng-ochat-actions">
                    <button type="button" id="hng-ochat-sound-toggle" class="button" title="<?php esc_attr_e('Ativar/Desativar som', 'hng-commerce'); ?>">
                        <span class="dashicons dashicons-megaphone"></span>
                    </button>
                    <span class="hng-ochat-status">
                        <span class="dashicons dashicons-<?php echo count($messages) > 0 ? 'yes' : 'minus'; ?>"></span>
                        <?php 
                        /* translators: %d: number of messages */
                        echo esc_html(sprintf(_n('%d mensagem', '%d mensagens', count($messages), 'hng-commerce'), count($messages))); 
                        ?>
                    </span>
                </div>
            </div>
            
            <!-- Messages Container -->
            <div class="hng-ochat-messages" id="hng-ochat-messages">
                <?php if (empty($messages)) : ?>
                    <div class="hng-ochat-empty">
                        <span class="dashicons dashicons-format-chat"></span>
                        <p><?php esc_html_e('Nenhuma mensagem ainda. Inicie a conversa com o cliente!', 'hng-commerce'); ?></p>
                    </div>
                <?php else : ?>
                    <?php foreach ($messages as $msg) : 
                        $is_admin = $msg->sender_type === 'admin';
                        $sender_name = $is_admin ? __('Você', 'hng-commerce') : $customer_name;
                        $avatar = $is_admin ? get_avatar(get_current_user_id(), 36) : get_avatar($customer_id, 36);
                    ?>
                        <div class="hng-ochat-message <?php echo $is_admin ? 'admin' : 'customer'; ?>" data-id="<?php echo esc_attr($msg->id); ?>">
                            <div class="hng-ochat-avatar"><?php echo $avatar; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Avatar HTML is safely generated by get_avatar() ?></div>
                            <div class="hng-ochat-bubble">
                                <div class="hng-ochat-bubble-header">
                                    <strong><?php echo esc_html($sender_name); ?></strong>
                                </div>
                                <div class="hng-ochat-bubble-text">
                                    <?php 
                                    if ($msg->message_type === 'file' && !empty($msg->attachment_url)) {
                                        echo '<div class="hng-ochat-file">';
                                        echo '<span class="dashicons dashicons-media-default"></span>';
                                        echo '<a href="' . esc_url($msg->attachment_url) . '" target="_blank">' . esc_html($msg->attachment_name ?: __('Arquivo', 'hng-commerce')) . '</a>';
                                        echo '</div>';
                                    }
                                    echo wp_kses_post(nl2br($msg->message)); 
                                    ?>
                                </div>
                                <div class="hng-ochat-bubble-time">
                                    <?php echo esc_html(mysql2date(get_option('time_format'), $msg->created_at)); ?>
                                    <?php if (!$msg->is_read && !$is_admin): ?>
                                        <span class="hng-ochat-unread"><?php esc_html_e('Não lida', 'hng-commerce'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Input Area -->
            <div class="hng-ochat-input">
                <div class="hng-ochat-input-wrapper">
                    <textarea id="hng-ochat-message" 
                              placeholder="<?php esc_attr_e('Digite sua mensagem...', 'hng-commerce'); ?>"
                              rows="2"></textarea>
                    <div class="hng-ochat-input-actions">
                        <label class="hng-ochat-attach" title="<?php esc_attr_e('Anexar arquivo', 'hng-commerce'); ?>">
                            <span class="dashicons dashicons-paperclip"></span>
                            <input type="file" id="hng-ochat-file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif" style="display:none;">
                        </label>
                        <button type="button" class="button button-primary" id="hng-ochat-send-btn">
                            <span class="dashicons dashicons-arrow-right-alt" style="vertical-align: middle;"></span>
                            <?php esc_html_e('Enviar', 'hng-commerce'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .hng-order-chat {
            border-top: 1px solid #e0e0e0;
        }
        
        .hng-ochat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 20px;
            background: #f9f9f9;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .hng-ochat-customer {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .hng-ochat-customer img {
            border-radius: 50%;
            width: 40px;
            height: 40px;
        }
        
        .hng-ochat-customer-info {
            display: flex;
            flex-direction: column;
        }
        
        .hng-ochat-customer-info span {
            font-size: 12px;
            color: #666;
        }
        
        .hng-ochat-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .hng-ochat-actions #hng-ochat-sound-toggle {
            padding: 0 8px;
            background: #f0f0f0;
        }
        
        .hng-ochat-actions #hng-ochat-sound-toggle.sound-active {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        
        .hng-ochat-actions #hng-ochat-sound-toggle .dashicons {
            line-height: 28px;
        }
        
        .hng-ochat-status {
            font-size: 13px;
            color: #666;
        }
        
        .hng-ochat-messages {
            height: 350px;
            overflow-y: auto;
            padding: 20px;
            background: #fafafa;
        }
        
        .hng-ochat-empty {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .hng-ochat-empty .dashicons {
            font-size: 48px;
            width: 48px;
            height: 48px;
            margin-bottom: 10px;
        }
        
        .hng-ochat-message {
            display: flex;
            margin-bottom: 15px;
            gap: 10px;
        }
        
        .hng-ochat-message.admin {
            flex-direction: row-reverse;
        }
        
        .hng-ochat-avatar img {
            width: 36px;
            height: 36px;
            border-radius: 50%;
        }
        
        .hng-ochat-bubble {
            max-width: 70%;
            padding: 12px 16px;
            border-radius: 16px;
            background: #fff;
            border: 1px solid #e0e0e0;
        }
        
        .hng-ochat-message.admin .hng-ochat-bubble {
            background: #2271b1;
            color: #fff;
            border-color: #2271b1;
        }
        
        .hng-ochat-bubble-header {
            font-size: 11px;
            margin-bottom: 5px;
            opacity: 0.8;
        }
        
        .hng-ochat-bubble-text {
            line-height: 1.5;
        }
        
        .hng-ochat-bubble-time {
            font-size: 10px;
            margin-top: 5px;
            opacity: 0.6;
            text-align: right;
        }
        
        .hng-ochat-unread {
            background: #dc3232;
            color: #fff;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 9px;
            margin-left: 5px;
        }
        
        .hng-ochat-file {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 5px;
        }
        
        .hng-ochat-file a {
            color: inherit;
        }
        
        .hng-ochat-message.admin .hng-ochat-file a {
            color: #fff;
        }
        
        .hng-ochat-input {
            padding: 15px 20px;
            background: #fff;
            border-top: 1px solid #e0e0e0;
        }
        
        .hng-ochat-input-wrapper {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        
        .hng-ochat-input textarea {
            flex: 1;
            resize: none;
            border-radius: 8px;
            padding: 10px;
            border: 1px solid #ddd;
        }
        
        .hng-ochat-input-actions {
            display: flex;
            gap: 5px;
        }
        
        .hng-ochat-attach {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #f0f0f0;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .hng-ochat-attach:hover {
            background: #e0e0e0;
        }
        
        /* New message animation */
        @keyframes pulseNew {
            0% { opacity: 0.5; transform: scale(0.95); }
            50% { opacity: 1; transform: scale(1.02); }
            100% { opacity: 1; transform: scale(1); }
        }
        .hng-ochat-new {
            animation: pulseNew 0.4s ease-out;
        }
        .hng-ochat-new .hng-ochat-bubble {
            box-shadow: 0 0 15px rgba(34, 113, 177, 0.4);
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            console.log('HNG Order Chat: Inicializando...');
            
            var orderId = $('#hng-order-chat').data('order-id');
            var chatType = $('#hng-order-chat').data('chat-type');
            var nonce = $('#hng_order_chat_nonce').val();
            var lastMessageId = 0;
            var isFirstLoad = true;
            var pollInterval = null;
            var originalTitle = document.title;
            var flashingInterval = null;
            var soundEnabled = true;
            var unreadCount = 0;
            
            console.log('HNG Order Chat: orderId=' + orderId + ', chatType=' + chatType + ', nonce=' + (nonce ? 'presente' : 'AUSENTE'));
            
            if (!orderId || !nonce) {
                console.error('HNG Order Chat: Falha na inicialização - orderId ou nonce ausente');
                return;
            }
            
            // Sound toggle button
            $('#hng-ochat-sound-toggle').on('click', function() {
                soundEnabled = !soundEnabled;
                var $btn = $(this);
                
                if (soundEnabled) {
                    $btn.addClass('sound-active');
                    $btn.attr('title', 'Som ativado');
                    playNotificationSound(); // Test sound
                } else {
                    $btn.removeClass('sound-active');
                    $btn.attr('title', 'Som desativado');
                }
            });
            
            // Update title with unread count
            function updateTitleCount(count) {
                unreadCount = count;
                if (count > 0) {
                    document.title = '(' + count + ') ' + originalTitle;
                } else {
                    document.title = originalTitle;
                }
            }
            
            // Play notification sound
            function playNotificationSound() {
                if (!soundEnabled) return;
                
                try {
                    var audioContext = new (window.AudioContext || window.webkitAudioContext)();
                    var osc1 = audioContext.createOscillator();
                    var osc2 = audioContext.createOscillator();
                    var gainNode = audioContext.createGain();
                    
                    osc1.connect(gainNode);
                    osc2.connect(gainNode);
                    gainNode.connect(audioContext.destination);
                    
                    osc1.frequency.value = 784;
                    osc2.frequency.value = 659;
                    osc1.type = 'sine';
                    osc2.type = 'sine';
                    
                    var now = audioContext.currentTime;
                    gainNode.gain.setValueAtTime(0, now);
                    gainNode.gain.linearRampToValueAtTime(0.3, now + 0.05);
                    gainNode.gain.linearRampToValueAtTime(0.1, now + 0.15);
                    gainNode.gain.linearRampToValueAtTime(0, now + 0.4);
                    
                    osc1.start(now);
                    osc2.start(now + 0.15);
                    osc1.stop(now + 0.2);
                    osc2.stop(now + 0.4);
                } catch (e) {
                    console.log('Audio not supported:', e);
                }
            }
            
            // Flash tab title for new messages
            function flashTabTitle() {
                if (flashingInterval) return;
                
                var isFlashing = true;
                flashingInterval = setInterval(function() {
                    document.title = isFlashing ? '💬 Nova mensagem!' : originalTitle;
                    isFlashing = !isFlashing;
                }, 1000);
                
                setTimeout(stopFlashing, 10000);
                $(window).one('focus', stopFlashing);
            }
            
            function stopFlashing() {
                if (flashingInterval) {
                    clearInterval(flashingInterval);
                    flashingInterval = null;
                    document.title = originalTitle;
                }
            }
            
            // Auto-scroll to bottom
            function scrollToBottom() {
                var container = $('#hng-ochat-messages');
                container.scrollTop(container[0].scrollHeight);
            }
            scrollToBottom();
            
            // Mark messages as read
            $.post(ajaxurl, {
                action: 'hng_mark_order_messages_read',
                order_id: orderId,
                nonce: nonce
            });
            
            // Get last message ID from current messages
            $('#hng-ochat-messages .hng-ochat-message').each(function() {
                var msgId = $(this).data('id');
                if (msgId > lastMessageId) {
                    lastMessageId = msgId;
                }
            });
            
            // Poll for new messages
            function pollMessages() {
                $.post(ajaxurl, {
                    action: 'hng_get_order_chat_messages',
                    order_id: orderId,
                    after_id: lastMessageId,
                    nonce: nonce
                }, function(response) {
                    if (response.success && response.data.messages && response.data.messages.length > 0) {
                        var hasNewCustomerMessage = false;
                        
                        response.data.messages.forEach(function(msg) {
                            if (msg.id > lastMessageId) {
                                // Append new message
                                appendMessage(msg);
                                lastMessageId = msg.id;
                                
                                if (msg.sender_type === 'customer') {
                                    hasNewCustomerMessage = true;
                                }
                            }
                        });
                        
                        // Play sound for new customer messages
                        if (hasNewCustomerMessage && !isFirstLoad) {
                            playNotificationSound();
                            flashTabTitle();
                            updateTitleCount(unreadCount + 1);
                            
                            // Show browser notification
                            if ('Notification' in window && Notification.permission === 'granted') {
                                new Notification('Nova mensagem do cliente', {
                                    body: 'Você recebeu uma nova mensagem no pedido #' + orderId,
                                    icon: '<?php echo esc_url(admin_url('images/wordpress-logo.svg')); ?>'
                                });
                            }
                        }
                        
                        scrollToBottom();
                    }
                    isFirstLoad = false;
                });
            }
            
            // Append a single message
            var adminAvatarHtml = <?php echo wp_json_encode(get_avatar(get_current_user_id(), 36)); ?>;
            var customerAvatarHtml = <?php echo wp_json_encode(get_avatar($customer_id, 36)); ?>;
            
            function appendMessage(msg) {
                var isAdmin = msg.sender_type === 'admin';
                var senderName = isAdmin ? '<?php echo esc_js(__('Você', 'hng-commerce')); ?>' : '<?php echo esc_js($customer_name); ?>';
                var time = msg.created_at_formatted || '';
                
                var html = '<div class="hng-ochat-message ' + (isAdmin ? 'admin' : 'customer') + ' hng-ochat-new" data-id="' + msg.id + '">';
                html += '<div class="hng-ochat-avatar">' + (isAdmin ? adminAvatarHtml : customerAvatarHtml) + '</div>';
                html += '<div class="hng-ochat-bubble">';
                html += '<div class="hng-ochat-bubble-header"><strong>' + senderName + '</strong></div>';
                html += '<div class="hng-ochat-bubble-text">' + msg.message.replace(/\n/g, '<br>') + '</div>';
                html += '<div class="hng-ochat-bubble-time">' + time + '</div>';
                html += '</div></div>';
                
                $('.hng-ochat-empty').remove();
                $('#hng-ochat-messages').append(html);
                
                // Remove new animation after 1 second
                setTimeout(function() {
                    $('.hng-ochat-new').removeClass('hng-ochat-new');
                }, 1000);
            }
            
            // Request notification permission
            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission();
            }
            
            // Send message
            $('#hng-ochat-send-btn').on('click', function() {
                console.log('HNG Order Chat: Botão enviar clicado');
                var message = $('#hng-ochat-message').val().trim();
                if (!message) {
                    console.log('HNG Order Chat: Mensagem vazia, ignorando');
                    return;
                }
                
                console.log('HNG Order Chat: Enviando mensagem:', message);
                var $btn = $(this);
                $btn.prop('disabled', true);
                
                $.post(ajaxurl, {
                    action: 'hng_send_order_chat_message',
                    nonce: nonce,
                    order_id: orderId,
                    message: message,
                    chat_type: chatType
                }, function(response) {
                    console.log('HNG Order Chat: Resposta AJAX:', response);
                    $btn.prop('disabled', false);
                    if (response.success) {
                        $('#hng-ochat-message').val('');
                        if (response.data.message) {
                            appendMessage(response.data.message);
                            lastMessageId = Math.max(lastMessageId, response.data.message.id);
                        } else if (response.data.html) {
                            $('.hng-ochat-empty').remove();
                            $('#hng-ochat-messages').append(response.data.html);
                        }
                        scrollToBottom();
                    } else {
                        alert(response.data.message || '<?php echo esc_js(__('Erro ao enviar mensagem', 'hng-commerce')); ?>');
                    }
                });
            });
            
            // Enter to send
            $('#hng-ochat-message').on('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    $('#hng-ochat-send-btn').click();
                }
            });
            
            // File upload
            $('#hng-ochat-file').on('change', function() {
                var file = this.files[0];
                if (!file) return;
                
                var formData = new FormData();
                formData.append('action', 'hng_upload_order_chat_file');
                formData.append('nonce', nonce);
                formData.append('order_id', orderId);
                formData.append('file', file);
                formData.append('chat_type', chatType);
                
                var $btn = $('#hng-ochat-send-btn');
                $btn.prop('disabled', true).text('<?php echo esc_js(__('Enviando...', 'hng-commerce')); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-arrow-right-alt" style="vertical-align: middle;"></span> <?php echo esc_js(__('Enviar', 'hng-commerce')); ?>');
                        if (response.success) {
                            $('.hng-ochat-empty').remove();
                            $('#hng-ochat-messages').append(response.data.html);
                            scrollToBottom();
                        } else {
                            alert(response.data.message || '<?php echo esc_js(__('Erro ao enviar arquivo', 'hng-commerce')); ?>');
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-arrow-right-alt" style="vertical-align: middle;"></span> <?php echo esc_js(__('Enviar', 'hng-commerce')); ?>');
                        alert('<?php echo esc_js(__('Erro de conexão.', 'hng-commerce')); ?>');
                    }
                });
                
                // Clear file input
                $(this).val('');
            });
            
            // Start polling for new messages (every 5 seconds)
            pollInterval = setInterval(pollMessages, 5000);
            
            // When page is hidden, slow down polling but keep it running for notifications
            $(document).on('visibilitychange', function() {
                if (pollInterval) clearInterval(pollInterval);
                
                if (document.hidden) {
                    // Slower polling when hidden (every 15 seconds)
                    pollInterval = setInterval(pollMessages, 15000);
                } else {
                    // Faster polling when visible (every 5 seconds)
                    pollMessages();
                    pollInterval = setInterval(pollMessages, 5000);
                }
            });
        });
        </script>
        <?php
    }
}
