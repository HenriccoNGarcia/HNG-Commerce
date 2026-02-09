<?php
/**
 * Standalone Quote Request Handler
 * 
 * Este arquivo processa solicitações de orçamento diretamente,
 * contornando o admin-ajax.php e REST API que podem ser bloqueados por WAF.
 * 
 * NOTA: Este arquivo é um endpoint standalone que carrega o WordPress
 * manualmente. Não deve ser incluído diretamente de outros arquivos.
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

// Direct file access protection - defines HNG_QUOTE_ENDPOINT to allow this file to work as standalone endpoint
if ( ! defined( 'ABSPATH' ) ) {
    // This is a standalone endpoint - load WordPress
    define( 'HNG_QUOTE_ENDPOINT', true );
    
    // Carregar WordPress
    $wp_load_paths = [
        dirname(__FILE__) . '/../../../../wp-load.php',
        dirname(__FILE__) . '/../../../wp-load.php',
        $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php',
    ];

    $wp_loaded = false;
    foreach ($wp_load_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $wp_loaded = true;
            break;
        }
    }

    if (!$wp_loaded) {
        header('Content-Type: application/json');
        echo '{"success":false,"data":{"message":"Erro de configuração do servidor."}}';
        exit;
    }
} else {
    // File was included from WordPress - exit to prevent execution
    return;
}

// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    wp_send_json_error(['message' => __('Método não permitido.', 'hng-commerce')]);
}

// Verificar nonce
if (!isset($_POST['hng_quote_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['hng_quote_nonce'])), 'hng_request_quote')) {
    wp_send_json_error(['message' => __('Sessão expirada. Recarregue a página.', 'hng-commerce')]);
}

// Verificar produto
$product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
if (!$product_id || get_post_type($product_id) !== 'hng_product') {
    wp_send_json_error(['message' => __('Produto inválido.', 'hng-commerce')]);
}

// Dados do cliente
$customer_id = 0;
$customer_name = '';
$customer_email = '';
$customer_phone = '';

if (is_user_logged_in()) {
    $user_id = get_current_user_id();
    $user = get_user_by('id', $user_id);
    $customer_id = $user_id;
    $customer_name = get_user_meta($user_id, '_hng_customer_name', true) ?: $user->display_name;
    $customer_email = $user->user_email;
    $customer_phone = get_user_meta($user_id, '_hng_customer_phone', true);
} else {
    // Cliente não logado - pegar dados do formulário
    $customer_name = isset($_POST['customer_name']) ? sanitize_text_field($_POST['customer_name']) : '';
    $customer_email = isset($_POST['customer_email']) ? sanitize_email($_POST['customer_email']) : '';
    $customer_phone = isset($_POST['customer_phone']) ? sanitize_text_field($_POST['customer_phone']) : '';
    
    // Validar campos obrigatórios para não logados
    if (empty($customer_name) || empty($customer_email)) {
        wp_send_json_error(['message' => __('Por favor, preencha seu nome e email.', 'hng-commerce')]);
    }
}

// Quantidade
$quantity = isset($_POST['quantity']) ? max(1, absint($_POST['quantity'])) : 1;

// Observações/Descrição do orçamento
$observations = isset($_POST['observations']) ? sanitize_textarea_field($_POST['observations']) : '';

// Processar campos personalizados do produto
$custom_fields_data = [];
$product_custom_fields = get_post_meta($product_id, '_hng_product_custom_fields', true);

if (!empty($product_custom_fields) && is_array($product_custom_fields)) {
    foreach ($product_custom_fields as $index => $field) {
        $field_key = 'custom_field_' . $index;
        $field_value = isset($_POST[$field_key]) ? sanitize_text_field($_POST[$field_key]) : '';
        
        // Verificar se campo obrigatório foi preenchido
        if (!empty($field['required']) && $field['required'] === 'yes' && empty($field_value)) {
            wp_send_json_error([
                /* translators: %s: field label */
                'message' => sprintf(__('O campo "%s" é obrigatório.', 'hng-commerce'), $field['label'])
            ]);
        }
        
        $custom_fields_data[] = [
            'label' => $field['label'],
            'type' => $field['type'],
            'value' => $field_value
        ];
    }
}

// Processar arquivos enviados
$uploaded_files = [];
if (!empty($_FILES)) {
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    
    foreach ($_FILES as $field_name => $file) {
        if ($file['error'] === UPLOAD_ERR_OK && $file['size'] > 0) {
            // Verificar se é um campo do tipo arquivo
            if (strpos($field_name, 'custom_field_') === 0) {
                $upload = wp_handle_upload($file, ['test_form' => false]);
                
                if (!isset($upload['error'])) {
                    $field_index = str_replace('custom_field_', '', $field_name);
                    $uploaded_files[$field_name] = [
                        'url' => $upload['url'],
                        'file' => $upload['file'],
                        'type' => $upload['type'],
                        'name' => $file['name']
                    ];
                    
                    // Atualizar o campo personalizado com a URL
                    if (isset($custom_fields_data[$field_index])) {
                        $custom_fields_data[$field_index]['value'] = $upload['url'];
                        $custom_fields_data[$field_index]['file_name'] = $file['name'];
                    }
                }
            }
        }
    }
}

// Criar o pedido de orçamento
$order_data = [
    'post_type' => 'hng_order',
    'post_status' => 'publish',
    'post_title' => sprintf(
        /* translators: 1: product title, 2: customer name */
        __('Orçamento - %1$s - %2$s', 'hng-commerce'),
        get_the_title($product_id),
        $customer_name
    ),
    'post_author' => $customer_id ?: 1
];

$order_id = wp_insert_post($order_data);

if (is_wp_error($order_id)) {
    wp_send_json_error(['message' => __('Erro ao criar solicitação de orçamento.', 'hng-commerce')]);
}

// Gerar número do pedido
$order_number = 'ORC-' . str_pad($order_id, 6, '0', STR_PAD_LEFT);

// Meta dados do pedido
update_post_meta($order_id, '_hng_order_number', $order_number);
update_post_meta($order_id, '_hng_order_type', 'quote');
update_post_meta($order_id, '_hng_order_status', 'quote_pending');
update_post_meta($order_id, '_hng_customer_id', $customer_id);
update_post_meta($order_id, '_hng_customer_name', $customer_name);
update_post_meta($order_id, '_hng_customer_email', $customer_email);
update_post_meta($order_id, '_hng_customer_phone', $customer_phone);
update_post_meta($order_id, '_hng_created_at', current_time('mysql'));
update_post_meta($order_id, '_hng_updated_at', current_time('mysql'));

// Itens do pedido
$product = get_post($product_id);
$product_price = get_post_meta($product_id, '_hng_product_price', true);
$order_items = [
    [
        'product_id' => $product_id,
        'product_name' => $product->post_title,
        'product_type' => 'quote',
        'quantity' => $quantity,
        'price' => 0, // Será definido após aprovação
        'estimated_price' => $product_price,
        'total' => 0,
        'custom_fields' => $custom_fields_data,
        'observations' => $observations,
        'uploaded_files' => $uploaded_files
    ]
];

update_post_meta($order_id, '_hng_order_items', $order_items);
update_post_meta($order_id, '_hng_quote_observations', $observations);
update_post_meta($order_id, '_hng_order_subtotal', 0);
update_post_meta($order_id, '_hng_order_total', 0);

// Modo de entrega do produto
$delivery_mode = get_post_meta($product_id, '_hng_delivery_mode', true) ?: 'both';
update_post_meta($order_id, '_hng_delivery_mode', $delivery_mode);

// Marcar como aguardando orçamento
update_post_meta($order_id, '_hng_quote_status', 'waiting_response');
update_post_meta($order_id, '_hng_quote_sent_at', current_time('mysql'));

// =============================================
// Inserir também na tabela hng_orders para aparecer no admin
// =============================================
global $wpdb;
$orders_table = $wpdb->prefix . 'hng_orders';
$order_items_table = $wpdb->prefix . 'hng_order_items';

// Inserir na tabela de pedidos
$db_order_data = [
    'post_id' => $order_id,
    'order_number' => $order_number,
    'customer_id' => $customer_id,
    'status' => 'hng-pending-approval', // Status para orçamento pendente
    'currency' => 'BRL',
    'is_test' => 0,
    'payment_method' => '',
    'payment_method_title' => '',
    'payment_status' => 'pending',
    'billing_first_name' => $customer_name,
    'billing_last_name' => '',
    'billing_email' => $customer_email,
    'billing_phone' => $customer_phone,
    'total' => 0,
    'subtotal' => 0,
    'shipping_total' => 0,
    'discount_total' => 0,
    'product_type' => 'quote',
    'customer_name' => $customer_name,
    'customer_email' => $customer_email,
    'customer_phone' => $customer_phone,
    'notes' => $observations,
    'created_at' => current_time('mysql'),
    'updated_at' => current_time('mysql'),
];

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Necessário para inserir na tabela customizada
$wpdb->insert($orders_table, $db_order_data);
$db_order_id = $wpdb->insert_id;

if ($db_order_id) {
    // Inserir itens do pedido
    $item_data = [
        'order_id' => $db_order_id,
        'product_id' => $product_id,
        'product_name' => $product->post_title,
        'product_type' => 'quote',
        'quantity' => $quantity,
        'price' => 0,
        'subtotal' => 0,
        'total' => 0,
        'meta' => wp_json_encode([
            'custom_fields' => $custom_fields_data,
            'observations' => $observations,
            'uploaded_files' => $uploaded_files
        ])
    ];
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Necessário para inserir na tabela customizada
    $wpdb->insert($order_items_table, $item_data);
    
    // Atualizar o post meta com o ID da tabela
    update_post_meta($order_id, '_hng_db_order_id', $db_order_id);
}

// Notificar admin
$to = get_option('admin_email');
$subject = sprintf(
    /* translators: 1: site name, 2: order number */
    __('[%1$s] Nova Solicitação de Orçamento #%2$s', 'hng-commerce'), 
    get_bloginfo('name'), 
    $order_number
);
$message = sprintf(
    /* translators: 1: order number, 2: product name, 3: customer name, 4: customer email, 5: customer phone, 6: quantity, 7: observations */
    __('Nova solicitação de orçamento recebida:\n\nPedido: %1$s\nProduto: %2$s\nCliente: %3$s\nEmail: %4$s\nTelefone: %5$s\nQuantidade: %6$d\n\nObservações:\n%7$s\n\nAcesse o painel administrativo para responder.', 'hng-commerce'),
    $order_number,
    $product->post_title,
    $customer_name,
    $customer_email,
    $customer_phone,
    $quantity,
    $observations
);

wp_mail($to, $subject, $message);

// Resposta de sucesso
wp_send_json_success([
    'message' => __('Sua solicitação de orçamento foi enviada com sucesso! Entraremos em contato em breve.', 'hng-commerce'),
    'order_id' => $order_id,
    'order_number' => $order_number
]);
