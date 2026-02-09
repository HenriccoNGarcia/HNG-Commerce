<?php
/**
 * Customer Management Page - Enhanced Version
 * 
 * Lists customers from orders + gateway CRM
 * Allows editing and sending signup/password reset links
 * 
 * @package HNG_Commerce
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render enhanced customers page
 */
function hng_render_customers_page_enhanced() {
    // Detectar ação de edição
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET parameters for page navigation
    if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
        hng_render_edit_customer_page();
        return;
    }
    
    global $wpdb;
    
    echo '<div class="hng-wrap">';
    echo '<div class="hng-header">';
    echo '<h1>';
    echo '<span class="dashicons dashicons-groups"></span>';
    esc_html_e('Clientes', 'hng-commerce');
    echo '<span class="hng-help-icon" data-hng-tip="Lista de clientes da loja e CRM integrado de gateways.">?</span>';
    echo '</h1>';
    echo '</div>';
    
    echo '<div class="hng-card">';
    echo '<div class="hng-card-content">';
    echo '<table class="hng-table">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>' . esc_html_e('Cliente', 'hng-commerce') . '</th>';
    echo '<th>' . esc_html_e('Email', 'hng-commerce') . '</th>';
    echo '<th>' . esc_html_e('Origem', 'hng-commerce') . '</th>';
    echo '<th>' . esc_html_e('Pedidos', 'hng-commerce') . '</th>';
    echo '<th>' . esc_html_e('Total Gasto', 'hng-commerce') . '</th>';
    echo '<th>' . esc_html_e('Status', 'hng-commerce') . '</th>';
    echo '<th>' . esc_html_e('Ações', 'hng-commerce') . '</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    // Merge: clientes de pedidos + clientes CRM gateway
    $customers_data = [];
    
    // 1. Clientes de pedidos (origem: local)
    $t_orders_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_orders') : ('`' . $wpdb->prefix . 'hng_orders`');
    // Justificativa: identificador de tabela sanitizado (helper/backticks); LIMIT é preparado via placeholder.
    // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $order_customers = $wpdb->get_results($wpdb->prepare(
        "SELECT customer_email, customer_name, COUNT(*) as order_count, SUM(total) as total_spent
        FROM {$t_orders_sql}
        GROUP BY customer_email
        ORDER BY total_spent DESC
        LIMIT %d",
        100
    ));
    
    foreach ($order_customers as $c) {
        $customers_data[$c->customer_email] = [
            'name' => $c->customer_name,
            'email' => $c->customer_email,
            'source' => 'local',
            'order_count' => $c->order_count,
            'total_spent' => $c->total_spent,
            'crm_id' => null
        ];
    }
    
    // 2. Clientes CRM (gateway) se integração avançada ativa
    if (function_exists('hng_filter_data_sources_sql')) {
        $where = hng_filter_data_sources_sql('hng_customers');
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        // Justificativa: $where é gerado por helper que usa $wpdb->prepare; tabela é estática com prefixo WP.
        $crm_customers = $wpdb->get_results("SELECT id, first_name, last_name, email, data_source FROM {$wpdb->prefix}hng_customers{$where} LIMIT 100");
        
        foreach ($crm_customers as $c) {
            $name = trim($c->first_name . ' ' . $c->last_name);
            if (!isset($customers_data[$c->email])) {
                $customers_data[$c->email] = [
                    'name' => $name,
                    'email' => $c->email,
                    'source' => $c->data_source ?? 'local',
                    'order_count' => 0,
                    'total_spent' => 0,
                    'crm_id' => $c->id
                ];
            } else {
                // Atualizar origem se vier de gateway
                if ($c->data_source && $c->data_source !== 'local') {
                    $customers_data[$c->email]['source'] = $c->data_source;
                    $customers_data[$c->email]['crm_id'] = $c->id;
                }
            }
        }
    }
    
    if (empty($customers_data)) {
        echo '<tr><td colspan="7">' . esc_html__('Nenhum cliente encontrado.', 'hng-commerce') . '</td></tr>';
    } else {
        foreach ($customers_data as $customer) {
            $user = get_user_by('email', $customer['email']);
            $has_account = $user ? true : false;
            
            $source_label = [
                'local' => '<span class="hng-badge">Loja</span>',
                'asaas' => '<span class="hng-badge hng-badge-info">Asaas</span>',
                'mercadopago' => '<span class="hng-badge hng-badge-warning">Mercado Pago</span>',
                'pagarme' => '<span class="hng-badge hng-badge-success">Pagar.me</span>'
            ];
            $source_display = $source_label[$customer['source']] ?? '<span class="hng-badge">' . esc_html($customer['source']) . '</span>';
            
            $status_badge = $has_account 
                ? '<span class="hng-badge hng-badge-success">✓ Conta Ativa</span>' 
                : '<span class="hng-badge hng-badge-secondary">Sem Conta</span>';
            
            echo '<tr>';
            echo '<td><strong>' . esc_html($customer['name']) . '</strong></td>';
            echo '<td>' . esc_html($customer['email']) . '</td>';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $source_display contains pre-defined safe HTML badges
            echo '<td>' . $source_display . '</td>';
            echo '<td>' . esc_html(number_format($customer['order_count'], 0, ',', '.')) . '</td>';
            echo '<td><strong>' . esc_html(hng_price($customer['total_spent'])) . '</strong></td>';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $status_badge contains pre-defined safe HTML badges
            echo '<td>' . $status_badge . '</td>';
            echo '<td>';
            
            if ($customer['crm_id']) {
                echo '<a href="' . esc_url(admin_url('admin.php?page=hng-customers&action=edit&id=' . $customer['crm_id'])) . '" class="button button-small">Editar</a> ';
            }
            
            if ($customer['order_count'] > 0) {
                echo '<a href="' . esc_url(admin_url('admin.php?page=hng-orders&customer_email=' . urlencode($customer['email']))) . '" class="button button-small">Ver Pedidos</a>';
            }
            
            echo '</td>';
            echo '</tr>';
        }
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
}

/**
 * Render edit customer page
 */
function hng_render_edit_customer_page() {
    global $wpdb;
    
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET parameter for customer ID
    $customer_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
    if (!$customer_id) {
        wp_die('ID de cliente inválido');
    }
    
    // Processar salvamento
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hng_save_customer'])) {
        check_admin_referer('hng_save_customer_' . $customer_id);
        
        $post = wp_unslash($_POST);
        
        $wpdb->update(
            $wpdb->prefix . 'hng_customers',
            [
                'first_name' => sanitize_text_field($post['first_name'] ?? ''),
                'last_name' => sanitize_text_field($post['last_name'] ?? ''),
                'email' => sanitize_email($post['email'] ?? ''),
                'phone' => sanitize_text_field($post['phone'] ?? ''),
                'document' => sanitize_text_field($post['document'] ?? ''),
                'updated_at' => current_time('mysql')
            ],
            ['id' => $customer_id],
            ['%s', '%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );
        
        echo '<div class="notice notice-success"><p>Cliente atualizado com sucesso!</p></div>';
    }
    
    // Carregar dados
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix is safe
    $customer = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}hng_customers WHERE id = %d",
        $customer_id
    ));
    
    if (!$customer) {
        wp_die('Cliente não encontrado');
    }
    
    $user = get_user_by('email', $customer->email);
    $has_account = $user ? true : false;
    
    ?>
    <div class="hng-wrap">
        <div class="hng-header">
            <h1>
                <span class="dashicons dashicons-admin-users"></span>
                Editar Cliente: <?php echo esc_html($customer->first_name . ' ' . $customer->last_name); ?>
            </h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=hng-customers')); ?>" class="button">← Voltar</a>
        </div>
        
        <div class="hng-card">
            <div class="hng-card-header">
                <h2>Informações do Cliente</h2>
            </div>
            <div class="hng-card-content">
                <form method="post">
                    <?php wp_nonce_field('hng_save_customer_' . $customer_id); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="first_name">Nome</label></th>
                            <td><input type="text" id="first_name" name="first_name" value="<?php echo esc_attr($customer->first_name); ?>" class="regular-text" required /></td>
                        </tr>
                        <tr>
                            <th><label for="last_name">Sobrenome</label></th>
                            <td><input type="text" id="last_name" name="last_name" value="<?php echo esc_attr($customer->last_name); ?>" class="regular-text" required /></td>
                        </tr>
                        <tr>
                            <th><label for="email">Email</label></th>
                            <td><input type="email" id="email" name="email" value="<?php echo esc_attr($customer->email); ?>" class="regular-text" required /></td>
                        </tr>
                        <tr>
                            <th><label for="phone">Telefone</label></th>
                            <td><input type="text" id="phone" name="phone" value="<?php echo esc_attr($customer->phone ?? ''); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th><label for="document">CPF/CNPJ</label></th>
                            <td><input type="text" id="document" name="document" value="<?php echo esc_attr($customer->document ?? ''); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th>Origem</th>
                            <td>
                                <?php
                                $source_label = [
                                    'local' => '<span class="hng-badge">Loja</span>',
                                    'asaas' => '<span class="hng-badge hng-badge-info">Asaas</span>',
                                    'mercadopago' => '<span class="hng-badge hng-badge-warning">Mercado Pago</span>',
                                    'pagarme' => '<span class="hng-badge hng-badge-success">Pagar.me</span>'
                                ];
                                echo wp_kses_post($source_label[$customer->data_source ?? 'local'] ?? '<span class="hng-badge">' . esc_html($customer->data_source) . '</span>');
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Status da Conta</th>
                            <td>
                                <?php if ($has_account): ?>
                                    <span class="hng-badge hng-badge-success">✓ Conta WordPress Ativa</span>
                                    <p class="description">Usuário ID: <?php echo esc_html($user->ID); ?> | Login: <?php echo esc_html($user->user_login); ?></p>
                                <?php else: ?>
                                    <span class="hng-badge hng-badge-secondary">Sem Conta WordPress</span>
                                    <p class="description">Este cliente não possui uma conta de acesso ao site.</p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" name="hng_save_customer" class="button button-primary">
                            <span class="dashicons dashicons-saved"></span> Salvar Alterações
                        </button>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=hng-customers')); ?>" class="button">Cancelar</a>
                    </p>
                </form>
            </div>
        </div>
        
        <div class="hng-card">
            <div class="hng-card-header">
                <h2>Ações de Conta</h2>
            </div>
            <div class="hng-card-content">
                <?php if (!$has_account): ?>
                    <div class="hng-action-card">
                        <h3><span class="dashicons dashicons-email"></span> Enviar Link de Cadastro</h3>
                        <p>Envia um email para o cliente com link para criar sua conta e definir senha.</p>
                        <button type="button" class="button button-primary" id="hng-send-signup-link" data-email="<?php echo esc_attr($customer->email); ?>" data-name="<?php echo esc_attr($customer->first_name); ?>">
                            <span class="dashicons dashicons-email-alt"></span> Enviar Link de Cadastro
                        </button>
                    </div>
                <?php else: ?>
                    <div class="hng-action-card">
                        <h3><span class="dashicons dashicons-lock"></span> Redefinir Senha</h3>
                        <p>Envia um email para o cliente com link para redefinir sua senha de acesso.</p>
                        <button type="button" class="button button-secondary" id="hng-send-password-reset" data-email="<?php echo esc_attr($customer->email); ?>">
                            <span class="dashicons dashicons-unlock"></span> Enviar Link de Redefinição
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('#hng-send-signup-link').on('click', function() {
            var $btn = $(this);
            var email = $btn.data('email');
            var name = $btn.data('name');
            
            if (!confirm('Enviar email de cadastro para ' + email + '?')) return;
            
            $btn.prop('disabled', true).text('Enviando...');
            
            $.post(ajaxurl, {
                action: 'hng_send_signup_link',
                email: email,
                name: name,
                nonce: '<?php echo esc_attr(wp_create_nonce('hng_customer_actions')); ?>'
            }, function(response) {
                if (response.success) {
                    alert('✓ Email enviado com sucesso!');
                } else {
                    alert('✗ Erro: ' + (response.data.message || 'Falha ao enviar'));
                }
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-email-alt"></span> Enviar Link de Cadastro');
            });
        });
        
        $('#hng-send-password-reset').on('click', function() {
            var $btn = $(this);
            var email = $btn.data('email');
            
            if (!confirm('Enviar email de redefinição de senha para ' + email + '?')) return;
            
            $btn.prop('disabled', true).text('Enviando...');
            
            $.post(ajaxurl, {
                action: 'hng_send_password_reset',
                email: email,
                nonce: '<?php echo esc_attr(wp_create_nonce('hng_customer_actions')); ?>'
            }, function(response) {
                if (response.success) {
                    alert('✓ Email enviado com sucesso!');
                } else {
                    alert('✗ Erro: ' + (response.data.message || 'Falha ao enviar'));
                }
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-unlock"></span> Enviar Link de Redefinição');
            });
        });
    });
    </script>
    
    <style>
    .hng-action-card {
        padding: 20px;
        border: 1px solid #e0e0e0;
        border-radius: 4px;
        margin-bottom: 15px;
    }
    .hng-action-card h3 {
        margin-top: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .hng-action-card .dashicons {
        color: #2271b1;
    }
    </style>
    <?php
}

/**
 * AJAX: Send signup link to customer
 */
add_action('wp_ajax_hng_send_signup_link', function() {
    check_ajax_referer('hng_customer_actions', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permissão negada']);
    }
    
    $post = wp_unslash($_POST);
    $email = sanitize_email($post['email'] ?? '');
    $name = sanitize_text_field($post['name'] ?? '');
    
    if (!is_email($email)) {
        wp_send_json_error(['message' => 'Email inválido']);
    }
    
    // Gerar token único
    $token = wp_generate_password(32, false);
    set_transient('hng_signup_token_' . md5($email), ['email' => $email, 'name' => $name], DAY_IN_SECONDS);
    
    // Criar link
    $signup_link = add_query_arg(['action' => 'hng_complete_signup', 'token' => $token], home_url('/'));
    
    // Enviar email
    $subject = sprintf('[%s] Complete seu cadastro', get_bloginfo('name'));
    $message = sprintf(
        "Olá %s,\n\nVocê foi convidado a criar uma conta em %s.\n\nClique no link abaixo para definir sua senha e ativar sua conta:\n%s\n\nEste link é válido por 24 horas.\n\nSe você não solicitou este convite, ignore este email.",
        $name,
        get_bloginfo('name'),
        $signup_link
    );
    
    $sent = wp_mail($email, $subject, $message);
    
    if ($sent) {
        wp_send_json_success(['message' => 'Email enviado com sucesso!']);
    } else {
        wp_send_json_error(['message' => 'Falha ao enviar email']);
    }
});

/**
 * AJAX: Send password reset link
 */
add_action('wp_ajax_hng_send_password_reset', function() {
    check_ajax_referer('hng_customer_actions', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permissão negada']);
    }
    
    $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    
    if (!is_email($email)) {
        wp_send_json_error(['message' => 'Email inválido']);
    }
    
    $user = get_user_by('email', $email);
    if (!$user) {
        wp_send_json_error(['message' => 'Usuário não encontrado']);
    }
    
    // Usar função nativa do WordPress
    $result = retrieve_password($email);
    
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }
    
    wp_send_json_success(['message' => 'Email de redefinição enviado!']);
});
