<?php
/**
 * HNG Admin - Customers Page
 *
 * Página de gerenciamento de clientes com histórico de pedidos.
 *
 * @package HNG_Commerce
 * @subpackage Admin/Pages
 * @since 1.1.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Admin_Customers_Page {
    
    /**
     * Renderizar página
     */
    public static function render() {
        global $wpdb;
        
        $breadcrumbs = [
            ['text' => 'HNG Commerce', 'url' => admin_url('admin.php?page=hng-commerce')],
            ['text' => __('Clientes', 'hng-commerce')]
        ];
        
        echo '<div class="hng-wrap">';
        self::render_breadcrumbs($breadcrumbs);
        
        echo '<div class="hng-header">';
        echo '<h1>';
        echo '<span class="dashicons dashicons-groups"></span>';
        echo esc_html__('Clientes', 'hng-commerce');
        echo '<span class="hng-help-icon" data-hng-tip="' . esc_attr__('Lista de clientes com histórico de pedidos e total gasto. Clique em \'Ver Pedidos\' para filtrar pelo cliente.', 'hng-commerce') . '">?</span>';
        echo '</h1>';
        echo '</div>';
        
        echo '<div class="hng-card">';
        echo '<div class="hng-card-content">';
        
        self::render_customers_table();
        
        echo '</div>';
        echo '</div>';
        
        // Modal de detalhes do cliente
        self::render_customer_details_modal();
        
        echo '</div>';
    }
    
    /**
     * Renderizar breadcrumbs
     */
    private static function render_breadcrumbs($items) {
        if (empty($items)) {
            return;
        }
        
        echo '<nav class="hng-breadcrumbs">';
        foreach ($items as $i => $item) {
            if ($i > 0) {
                echo ' <span class="separator">/</span> ';
            }
            
            if (isset($item['url'])) {
                echo '<a href="' . esc_url($item['url']) . '">' . esc_html($item['text']) . '</a>';
            } else {
                echo '<span>' . esc_html($item['text']) . '</span>';
            }
        }
        echo '</nav>';
    }
    
    /**
     * Renderizar tabela de clientes
     */
    private static function render_customers_table() {
        global $wpdb;
        
        echo '<table class="hng-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . esc_html__('Cliente', 'hng-commerce') . '</th>';
        echo '<th>' . esc_html__('Email', 'hng-commerce') . '</th>';
        echo '<th>' . esc_html__('Pedidos', 'hng-commerce') . '</th>';
        echo '<th>' . esc_html__('Total Gasto', 'hng-commerce') . '</th>';
        echo '<th>' . esc_html__('Ações', 'hng-commerce') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        // Usar helper para nome de tabela sanitizado
        $t_orders_sql = function_exists('hng_db_backtick_table') 
            ? hng_db_backtick_table('hng_orders') 
            : ('`' . $wpdb->prefix . 'hng_orders`');
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tables sanitized via hng_db_backtick_table()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Dashboard query for top customers by revenue
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Tables sanitized via hng_db_backtick_table()
        $customers = $wpdb->get_results($wpdb->prepare(
            "SELECT customer_email, customer_name, COUNT(*) as order_count, SUM(total) as total_spent
            FROM {$t_orders_sql}
            GROUP BY customer_email
            ORDER BY total_spent DESC
            LIMIT %d",
            50
        ));
        
        if (empty($customers)) {
            echo '<tr><td colspan="5">' . esc_html__('Nenhum cliente encontrado.', 'hng-commerce') . '</td></tr>';
        } else {
            foreach ($customers as $customer) {
                self::render_customer_row($customer);
            }
        }
        
        echo '</tbody>';
        echo '</table>';
    }
    
    /**
     * Renderizar linha de cliente
     */
    private static function render_customer_row($customer) {
        echo '<tr>';
        
        // Nome
        echo '<td><strong>' . esc_html($customer->customer_name) . '</strong></td>';
        
        // Email
        echo '<td>' . esc_html($customer->customer_email) . '</td>';
        
        // Quantidade de pedidos
        echo '<td>' . esc_html(number_format($customer->order_count, 0, ',', '.')) . '</td>';

        // Total gasto
        echo '<td><strong>' . esc_html(hng_price($customer->total_spent)) . '</strong></td>';
        
        // Ações
        echo '<td class="hng-actions-cell">';
        echo '<div class="hng-action-buttons">';
        
        // Ver Detalhes
        echo '<button type="button" class="button button-small hng-view-customer-details" data-email="' . esc_attr($customer->customer_email) . '" data-name="' . esc_attr($customer->customer_name) . '">';
        echo '<span class="dashicons dashicons-visibility" style="vertical-align: middle; margin-right: 3px;"></span>';
        echo esc_html__('Ver Detalhes', 'hng-commerce');
        echo '</button>';
        
        // Ver Pedidos
        $orders_url = admin_url('admin.php?page=hng-orders&customer_email=' . urlencode($customer->customer_email));
        echo '<a href="' . esc_url($orders_url) . '" class="button button-small">';
        echo '<span class="dashicons dashicons-list-view" style="vertical-align: middle; margin-right: 3px;"></span>';
        echo esc_html__('Ver Pedidos', 'hng-commerce');
        echo '</a>';
        
        echo '</div>';
        echo '</td>';
        
        echo '</tr>';
    }
    
    /**
     * Renderizar modal de detalhes do cliente
     */
    public static function render_customer_details_modal() {
        ?>
        <div id="hng-customer-details-modal" class="hng-modal" style="display: none;">
            <div class="hng-modal-overlay"></div>
            <div class="hng-modal-content" style="max-width: 600px;">
                <div class="hng-modal-header">
                    <h2 id="hng-customer-modal-title">
                        <span class="dashicons dashicons-businessperson"></span>
                        <?php esc_html_e('Detalhes do Cliente', 'hng-commerce'); ?>
                    </h2>
                    <button type="button" class="hng-modal-close">&times;</button>
                </div>
                <div class="hng-modal-body" id="hng-customer-details-content">
                    <div class="hng-loading-spinner">
                        <span class="spinner is-active"></span>
                        <?php esc_html_e('Carregando...', 'hng-commerce'); ?>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Check if nonce is available
            if (typeof hngCommerceAdmin === 'undefined' || !hngCommerceAdmin.nonce) {
                console.error('HNG Commerce: Admin nonce not available!');
                alert('ERRO: Nonce não disponível. O script hng-admin não foi carregado corretamente.');
                return;
            }
            
            // Open modal
            $(document).on('click', '.hng-view-customer-details', function() {
                console.log('Button clicked!', this);
                var email = $(this).data('email');
                var name = $(this).data('name');
                
                console.log('Email:', email, 'Name:', name);
                
                $('#hng-customer-modal-title').html('<span class="dashicons dashicons-businessperson"></span> ' + name);
                $('#hng-customer-details-content').html('<div class="hng-loading-spinner"><span class="spinner is-active"></span> <?php echo esc_js(__('Carregando...', 'hng-commerce')); ?></div>');
                $('#hng-customer-details-modal').show().addClass('active');
                
                // Fetch customer details
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hng_get_customer_details',
                        email: email,
                        nonce: hngCommerceAdmin.nonce
                    },
                    success: function(response) {
                        console.log('HNG Customer Details Response:', response);
                        if (response.success) {
                            renderCustomerDetails(response.data);
                        } else {
                            $('#hng-customer-details-content').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#hng-customer-details-content').html('<div class="notice notice-error"><p><?php echo esc_js(__('Erro ao carregar dados.', 'hng-commerce')); ?></p></div>');
                    }
                });
            });
            
            // Close modal
            $(document).on('click', '.hng-modal-close, .hng-modal-overlay', function() {
                $('#hng-customer-details-modal').hide().removeClass('active');
            });
            
            // Render customer details
            function renderCustomerDetails(data) {
                if (!data.has_account) {
                    var html = '<div class="notice notice-warning" style="margin: 0; margin-bottom: 20px;">' +
                        '<p><span class="dashicons dashicons-info"></span> ' + data.message + '</p>' +
                        '<p><strong>E-mail:</strong> ' + data.email + '</p>' +
                        '</div>';
                    
                    // Add action buttons for non-account customers
                    html += '<div class="hng-customer-actions">';
                    html += '<button type="button" class="button button-primary hng-create-account-btn" data-email="' + escapeHtml(data.email) + '">';
                    html += '<?php echo esc_js(__('Criar Conta', 'hng-commerce')); ?>';
                    html += '</button>';
                    html += '<button type="button" class="button hng-send-registration-link-btn" data-email="' + escapeHtml(data.email) + '">';
                    html += '<?php echo esc_js(__('Enviar Link de Registro', 'hng-commerce')); ?>';
                    html += '</button>';
                    html += '</div>';
                    
                    $('#hng-customer-details-content').html(html);
                    return;
                }
                
                var html = '<div class="hng-customer-details-grid">';
                
                // Badge de tipo
                var typeLabel = data.type_label ? data.type_label : (data.type === 'company' ? '<?php echo esc_js(__('Empresa', 'hng-commerce')); ?>' : '<?php echo esc_js(__('Prestador de Serviços', 'hng-commerce')); ?>');
                var typeClass = 'hng-badge-green';
                var typeStyle = 'background:#e8f8f1;color:#1e7e34;';
                if (data.type === 'company') {
                    typeClass = 'hng-badge-blue';
                    typeStyle = 'background:#e8f1fb;color:#1d4ed8;';
                } else if (data.type === 'customer') {
                    typeClass = 'hng-badge-purple';
                    typeStyle = 'background:#f3e8ff;color:#6b21a8;';
                }
                html += '<div class="hng-customer-type-badge ' + typeClass + '" style="' + typeStyle + '">' + typeLabel + '</div>';
                
                // Informações básicas
                html += '<div class="hng-detail-section">';
                html += '<h4><span class="dashicons dashicons-admin-users"></span> <?php echo esc_js(__('Informações Básicas', 'hng-commerce')); ?></h4>';
                html += '<table class="hng-details-table">';
                
                if (data.type === 'company') {
                    if (data.company_name) html += '<tr><th><?php echo esc_js(__('Razão Social', 'hng-commerce')); ?></th><td>' + escapeHtml(data.company_name) + '</td></tr>';
                    if (data.cnpj) html += '<tr><th><?php echo esc_js(__('CNPJ', 'hng-commerce')); ?></th><td>' + escapeHtml(data.cnpj) + '</td></tr>';
                } else {
                    if (data.name) html += '<tr><th><?php echo esc_js(__('Nome', 'hng-commerce')); ?></th><td>' + escapeHtml(data.name) + '</td></tr>';
                }
                
                html += '<tr><th><?php echo esc_js(__('E-mail', 'hng-commerce')); ?></th><td><a href="mailto:' + escapeHtml(data.email) + '">' + escapeHtml(data.email) + '</a></td></tr>';
                
                if (data.phone) html += '<tr><th><?php echo esc_js(__('Telefone', 'hng-commerce')); ?></th><td>' + escapeHtml(data.phone) + '</td></tr>';
                if (data.whatsapp) {
                    var whatsappNumber = data.whatsapp.replace(/\D/g, '');
                    html += '<tr><th><?php echo esc_js(__('WhatsApp', 'hng-commerce')); ?></th><td><a href="https://wa.me/55' + whatsappNumber + '" target="_blank" class="hng-whatsapp-link">' + escapeHtml(data.whatsapp) + ' <span class="dashicons dashicons-external"></span></a></td></tr>';
                }
                
                html += '</table>';
                html += '</div>';
                
                // Área de atuação e serviços
                if (data.area || data.social_networks || data.services_provided) {
                    html += '<div class="hng-detail-section">';
                    html += '<h4><span class="dashicons dashicons-portfolio"></span> <?php echo esc_js(__('Informações Profissionais', 'hng-commerce')); ?></h4>';
                    html += '<table class="hng-details-table">';
                    
                    if (data.area) html += '<tr><th><?php echo esc_js(__('Área de Atuação', 'hng-commerce')); ?></th><td>' + escapeHtml(data.area) + '</td></tr>';
                    if (data.social_networks) html += '<tr><th><?php echo esc_js(__('Redes Sociais', 'hng-commerce')); ?></th><td style="white-space: pre-line;">' + escapeHtml(data.social_networks) + '</td></tr>';
                    if (data.services_provided) html += '<tr><th><?php echo esc_js(__('Serviços Prestados', 'hng-commerce')); ?></th><td style="white-space: pre-line;">' + escapeHtml(data.services_provided) + '</td></tr>';
                    
                    html += '</table>';
                    html += '</div>';
                }
                
                // Serviço necessário
                if (data.service_needed || data.other_service) {
                    html += '<div class="hng-detail-section">';
                    html += '<h4><span class="dashicons dashicons-clipboard"></span> <?php echo esc_js(__('Interesse em Serviço', 'hng-commerce')); ?></h4>';
                    html += '<table class="hng-details-table">';
                    
                    if (data.service_needed_name) {
                        html += '<tr><th><?php echo esc_js(__('Serviço', 'hng-commerce')); ?></th><td>' + escapeHtml(data.service_needed_name) + '</td></tr>';
                    } else if (data.service_needed === 'other' && data.other_service) {
                        html += '<tr><th><?php echo esc_js(__('Descrição', 'hng-commerce')); ?></th><td style="white-space: pre-line;">' + escapeHtml(data.other_service) + '</td></tr>';
                    }
                    
                    html += '</table>';
                    html += '</div>';
                }
                
                // Dados da conta
                html += '<div class="hng-detail-section hng-detail-section-muted">';
                html += '<h4><span class="dashicons dashicons-admin-settings"></span> <?php echo esc_js(__('Dados da Conta', 'hng-commerce')); ?></h4>';
                html += '<table class="hng-details-table">';
                html += '<tr><th><?php echo esc_js(__('ID do Usuário', 'hng-commerce')); ?></th><td>#' + data.user_id + '</td></tr>';
                html += '<tr><th><?php echo esc_js(__('Usuário', 'hng-commerce')); ?></th><td>' + escapeHtml(data.username) + '</td></tr>';
                if (data.registered_at) html += '<tr><th><?php echo esc_js(__('Cadastro em', 'hng-commerce')); ?></th><td>' + escapeHtml(data.registered_at) + '</td></tr>';
                html += '</table>';
                html += '</div>';
                
                // Action buttons
                html += '<div class="hng-customer-actions">';
                html += '<button type="button" class="button hng-send-email-btn" data-email="' + escapeHtml(data.email) + '">';
                html += '<span class="dashicons dashicons-email-alt" style="vertical-align: middle; margin-right: 3px;"></span>';
                html += '<?php echo esc_js(__('Enviar E-mail', 'hng-commerce')); ?>';
                html += '</button>';
                html += '<a href="' + escapeHtml('<?php echo esc_url(admin_url("user-edit.php")); ?>' + '?user_id=' + data.user_id) + '" class="button">';
                html += '<span class="dashicons dashicons-edit" style="vertical-align: middle; margin-right: 3px;"></span>';
                html += '<?php echo esc_js(__('Editar Usuário', 'hng-commerce')); ?>';
                html += '</a>';
                html += '</div>';
                
                html += '</div>';
                
                $('#hng-customer-details-content').html(html);
            }
            
            // Escape HTML helper
            function escapeHtml(text) {
                if (!text) return '';
                var div = document.createElement('div');
                div.appendChild(document.createTextNode(text));
                return div.innerHTML;
            }
            
            // Event handlers for action buttons
            
            // Create account button
            $(document).on('click', '.hng-create-account-btn', function() {
                var email = $(this).data('email');
                var name = $('#hng-customer-modal-title').text().replace('Detalhes do Cliente', '').trim();
                
                if (!confirm('<?php echo esc_js(__('Tem certeza que deseja criar uma conta para este cliente?', 'hng-commerce')); ?>')) {
                    return;
                }
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hng_create_customer_account',
                        customer_email: email,
                        customer_name: name,
                        nonce: hngCommerceAdmin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            $('#hng-customer-details-modal').hide();
                        } else {
                            alert('<?php echo esc_js(__('Erro:', 'hng-commerce')); ?> ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('<?php echo esc_js(__('Erro ao criar conta.', 'hng-commerce')); ?>');
                    }
                });
            });
            
            // Send registration link button
            $(document).on('click', '.hng-send-registration-link-btn', function() {
                var email = $(this).data('email');
                var name = $('#hng-customer-modal-title').text().replace('Detalhes do Cliente', '').trim();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hng_generate_registration_link',
                        customer_email: email,
                        customer_name: name,
                        nonce: hngCommerceAdmin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                        } else {
                            alert('<?php echo esc_js(__('Erro:', 'hng-commerce')); ?> ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('<?php echo esc_js(__('Erro ao enviar link.', 'hng-commerce')); ?>');
                    }
                });
            });
            
            // Send email button
            $(document).on('click', '.hng-send-email-btn', function() {
                var email = $(this).data('email');
                window.location.href = 'mailto:' + email;
            });
        });
        </script>
        
        <style>
        /* Modal Base */
        .hng-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 100000;
            display: none;
        }
        .hng-modal.active {
            display: flex !important;
            align-items: center;
            justify-content: center;
        }
        .hng-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 100001;
        }
        .hng-modal-content {
            position: relative;
            background: #fff;
            border-radius: 8px;
            max-width: 90%;
            max-height: 90vh;
            overflow: auto;
            z-index: 100002;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .hng-modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .hng-modal-header h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .hng-modal-close {
            background: transparent;
            border: none;
            font-size: 28px;
            line-height: 1;
            cursor: pointer;
            color: #6b7280;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
        }
        .hng-modal-close:hover {
            background: #f3f4f6;
            color: #1f2937;
        }
        .hng-modal-body {
            padding: 24px;
        }
        
        /* Customer Details */
        .hng-customer-details-grid {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .hng-customer-type-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .hng-badge-green {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        .hng-badge-blue {
            background: rgba(59, 130, 246, 0.15);
            color: #3b82f6;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }
        .hng-detail-section {
            background: var(--hng-card-bg, #f9fafb);
            padding: 16px;
            border-radius: 8px;
            border: 1px solid var(--hng-border, #e5e7eb);
        }
        .hng-detail-section h4 {
            margin: 0 0 12px 0;
            font-size: 14px;
            font-weight: 600;
            color: var(--hng-text-main, #1f2937);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .hng-detail-section h4 .dashicons {
            font-size: 16px;
            width: 16px;
            height: 16px;
        }
        .hng-detail-section-muted {
            background: var(--hng-bg, #f3f4f6);
            opacity: 0.8;
        }
        .hng-details-table {
            width: 100%;
            border-collapse: collapse;
        }
        .hng-details-table tr {
            border-bottom: 1px solid var(--hng-border, #e5e7eb);
        }
        .hng-details-table tr:last-child {
            border-bottom: none;
        }
        .hng-details-table th {
            text-align: left;
            padding: 8px 12px 8px 0;
            font-weight: 500;
            color: var(--hng-text-muted, #6b7280);
            width: 140px;
            vertical-align: top;
        }
        .hng-details-table td {
            padding: 8px 0;
            color: var(--hng-text-main, #1f2937);
        }
        .hng-whatsapp-link {
            color: #25d366;
            text-decoration: none;
        }
        .hng-whatsapp-link:hover {
            text-decoration: underline;
        }
        .hng-action-buttons {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        .hng-action-buttons .button .dashicons {
            font-size: 14px;
            width: 14px;
            height: 14px;
            line-height: 1.3;
        }
        .hng-loading-spinner {
            text-align: center;
            padding: 40px;
            color: var(--hng-text-muted, #6b7280);
        }
        .hng-loading-spinner .spinner {
            float: none;
            margin: 0 10px 0 0;
        }
        .hng-customer-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--hng-border, #e5e7eb);
        }
        .hng-customer-actions .button {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin: 0;
        }
        .hng-customer-actions .button .dashicons {
            font-size: 14px;
            width: 14px;
            height: 14px;
            line-height: 1.3;
        }
        </style>
        <?php
    }
}
