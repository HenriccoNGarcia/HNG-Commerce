<?php
/**
 * HNG Commerce - User Customer Relationship
 *
 * @package HNG_Commerce
 * @subpackage Admin/Users
 * @since 1.2.15
 */

if (!defined('ABSPATH')) {
    exit;
}

// Adicionar campo direto na página de edição de usuário
add_action('show_user_profile', 'hng_render_user_customer_field', 10, 1);
add_action('edit_user_profile', 'hng_render_user_customer_field', 10, 1);

// Também adicionar via hook mais geral
add_action('admin_footer', 'hng_inject_customer_field_js');

function hng_render_user_customer_field($user) {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    global $wpdb;
    
    // Buscar cliente relacionado
    $related_customer = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}hng_customers WHERE wp_user_id = %d LIMIT 1",
        $user->ID
    ));
    
    $related_customer_name = '';
    $related_customer_label = '';
    if ($related_customer) {
        $related_customer_name = trim(($related_customer->first_name ? $related_customer->first_name : '') . ' ' . ($related_customer->last_name ? $related_customer->last_name : ''));
        // Prefer name, but fall back to email so the UI always shows something meaningful
        $related_customer_label = $related_customer_name !== '' ? $related_customer_name : $related_customer->email;
    }
    
    ?>
    <table class="form-table" role="presentation">
        <tbody>
            <tr>
                <th>
                    <label for="hng_customer_id"><?php esc_html_e('Cliente HNG Commerce', 'hng-commerce'); ?></label>
                </th>
                <td>
                    <div id="hng-customer-field" style="position: relative;">
                        <input 
                            type="text" 
                            id="hng_customer_search" 
                            class="regular-text" 
                            placeholder="<?php esc_attr_e('Buscar cliente por email ou nome...', 'hng-commerce'); ?>"
                            value="<?php echo $related_customer ? esc_attr($related_customer_label ? ($related_customer->email . ' (' . $related_customer_label . ')') : $related_customer->email) : ''; ?>"
                            autocomplete="off"
                        />
                        <input 
                            type="hidden" 
                            id="hng_customer_id" 
                            name="hng_customer_id" 
                            value="<?php echo $related_customer ? intval($related_customer->id) : ''; ?>"
                        />
                        
                        <div id="hng-customer-suggestions" style="
                            position: absolute;
                            top: 100%;
                            left: 0;
                            right: 0;
                            background: white;
                            border: 1px solid #ddd;
                            border-top: none;
                            max-height: 300px;
                            overflow-y: auto;
                            display: none;
                            z-index: 1000;
                            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                        "></div>
                        
                        <p class="description">
                            <?php esc_html_e('Selecione um cliente HNG Commerce para vincular a este usuário.', 'hng-commerce'); ?>
                        </p>
                        
                        <?php if ($related_customer): ?>
                            <p class="description" style="color: #27ae60; margin-top: 10px; display: inline-flex; align-items: center; gap: 6px;">
                                <span class="dashicons dashicons-yes-alt" style="vertical-align: middle;"></span>
                                <?php printf(
                                    /* translators: %s = customer label */
                                    esc_html__('Vinculado ao cliente: %s', 'hng-commerce'),
                                    esc_html($related_customer_label)
                                ); ?>
                                <span style="background: #ecfdf3; color: #1e7e34; border: 1px solid #b7f0c0; border-radius: 12px; padding: 2px 8px; font-size: 11px;">ID #<?php echo intval($related_customer->id); ?></span>
                            </p>
                        <?php endif; ?>
    <?php
}

// Salvar relacionamento
add_action('personal_options_update', 'hng_save_user_customer_relationship');
add_action('edit_user_profile_update', 'hng_save_user_customer_relationship');
// Garantir salvamento ao criar um usuário novo
add_action('user_register', 'hng_save_user_customer_relationship');
add_action('edit_user_created_user', 'hng_save_user_customer_relationship');

function hng_save_user_customer_relationship($user_id) {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    global $wpdb;
    
    $customer_id = isset($_POST['hng_customer_id']) ? intval($_POST['hng_customer_id']) : 0;
    
    if (!$customer_id) {
        // Remover relacionamento
        $wpdb->update(
            $wpdb->prefix . 'hng_customers',
            ['wp_user_id' => NULL],
            ['wp_user_id' => $user_id],
            ['%d'],
            ['%d']
        );
        return;
    }
    
    // Desvincula qualquer outro cliente do usuário
    $wpdb->update(
        $wpdb->prefix . 'hng_customers',
        ['wp_user_id' => NULL],
        ['wp_user_id' => $user_id],
        ['%d'],
        ['%d']
    );
    
    // Vincula o cliente ao usuário
    $wpdb->update(
        $wpdb->prefix . 'hng_customers',
        ['wp_user_id' => $user_id],
        ['id' => $customer_id],
        ['%d'],
        ['%d']
    );
}

// Enfileirar JS
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook !== 'user-edit.php' && $hook !== 'user-new.php') {
        return;
    }
    
    if (!current_user_can('manage_options')) {
        return;
    }
    
    wp_enqueue_script(
        'hng-user-customer',
        HNG_COMMERCE_URL . 'assets/js/user-customer-relationship.js',
        ['jquery'],
        HNG_COMMERCE_VERSION,
        true
    );
    
    wp_localize_script('hng-user-customer', 'hngUserCustomer', [
        'ajaxurl' => admin_url('admin-ajax.php')
    ]);
});

// AJAX: Buscar clientes
add_action('wp_ajax_hng_search_customers', function() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Permissão negada.', 'hng-commerce')], 403);
    }
    
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    
    if (strlen($search) < 2) {
        wp_send_json_success([]);
        return;
    }
    
    global $wpdb;
    
    $customers = $wpdb->get_results($wpdb->prepare(
        "SELECT id, email, CONCAT(first_name, ' ', last_name) as customer_name FROM {$wpdb->prefix}hng_customers 
         WHERE email LIKE %s OR first_name LIKE %s OR last_name LIKE %s
         ORDER BY first_name ASC
         LIMIT 10",
        '%' . $wpdb->esc_like($search) . '%',
        '%' . $wpdb->esc_like($search) . '%',
        '%' . $wpdb->esc_like($search) . '%'
    ));
    
    wp_send_json_success($customers);
});

// Função para injetar o campo via JavaScript (fallback se o hook não funcionar)
function hng_inject_customer_field_js() {
    global $pagenow, $wpdb;
    
    // Apenas em página de edição de usuário
    if ($pagenow !== 'user-edit.php' && $pagenow !== 'user-new.php') {
        return;
    }
    
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Obter ID do usuário
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    
    if (!$user_id && $pagenow === 'user-new.php') {
        $user_id = 0; // Novo usuário
    }
    
    // Se for edição e não encontrou o usuário, retorna
    if ($pagenow === 'user-edit.php' && !$user_id) {
        return;
    }
    
    // Buscar cliente relacionado se houver
    $related_customer = null;
    if ($user_id) {
        $related_customer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hng_customers WHERE wp_user_id = %d LIMIT 1",
            $user_id
        ));
    }
    
    $customer_name = '';
    $related_customer_label = '';
    if ($related_customer) {
        $customer_name = trim(($related_customer->first_name ? $related_customer->first_name : '') . ' ' . ($related_customer->last_name ? $related_customer->last_name : ''));
        $related_customer_label = $customer_name !== '' ? $customer_name : $related_customer->email;
    }

    $customer_text = $related_customer ?
        esc_attr($related_customer->email . ' (' . ($customer_name !== '' ? $customer_name : $related_customer->email) . ')') :
        '';
    $customer_id = $related_customer ? intval($related_customer->id) : '';
    
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Verificar se já foi renderizado
        if ($('#hng-customer-field').length > 0) {
            console.log('HNG Customer field already rendered via PHP');
            return;
        }
        
        console.log('HNG Customer field injecting via JavaScript');
        
        // Encontrar a tabela de formulário e adicionar o campo no final
        const formTable = $('table.form-table').last();
        
        if (formTable.length === 0) {
            console.warn('HNG Customer: form-table not found');
            return;
        }
        
        const html = `
            <tr>
                <th>
                    <label for="hng_customer_id"><?php esc_html_e('Cliente HNG Commerce', 'hng-commerce'); ?></label>
                </th>
                <td>
                    <div id="hng-customer-field" style="position: relative;">
                        <input 
                            type="text" 
                            id="hng_customer_search" 
                            class="regular-text" 
                            placeholder="<?php esc_attr_e('Buscar cliente por email ou nome...', 'hng-commerce'); ?>"
                            value="<?php echo esc_attr($customer_text); ?>"
                            autocomplete="off"
                        />
                        <input 
                            type="hidden" 
                            id="hng_customer_id" 
                            name="hng_customer_id" 
                            value="<?php echo esc_attr($customer_id); ?>"
                        />
                        
                        <div id="hng-customer-suggestions" style="
                            position: absolute;
                            top: 100%;
                            left: 0;
                            right: 0;
                            background: white;
                            border: 1px solid #ddd;
                            border-top: none;
                            max-height: 300px;
                            overflow-y: auto;
                            display: none;
                            z-index: 1000;
                            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                        "></div>
                        
                        <p class="description">
                            <?php esc_html_e('Selecione um cliente HNG Commerce para vincular a este usuário.', 'hng-commerce'); ?>
                        </p>
                        
                        <?php if ($related_customer): ?>
                            <p class="description" style="color: #27ae60; margin-top: 10px; display: inline-flex; align-items: center; gap: 6px;">
                                <span class="dashicons dashicons-yes-alt" style="vertical-align: middle;"></span>
                                <?php printf(
                                    /* translators: %s = customer label */
                                    esc_html__('Vinculado ao cliente: %s', 'hng-commerce'),
                                    esc_html($related_customer_label)
                                ); ?>
                                <span style="background: #ecfdf3; color: #1e7e34; border: 1px solid #b7f0c0; border-radius: 12px; padding: 2px 8px; font-size: 11px;">ID #<?php echo intval($related_customer->id); ?></span>
                            </p>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        `;
        
        // Adicionar na tabela
        formTable.find('tbody').append(html);
        console.log('HNG Customer field injected successfully');
    });
    </script>
    <?php
}

