<?php
/**
 * HNG Commerce - Quote Email Functions
 * 
 * Funções para envio de emails relacionados a orçamentos
 *
 * @package HNG_Commerce
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Hook para enviar emails quando um orçamento for criado
add_action('hng_order_created', 'hng_maybe_send_quote_emails', 10, 3);

/**
 * Enviar emails de orçamento quando pedido for criado
 */
function hng_maybe_send_quote_emails($order_id, $order_data, $cart) {
    global $wpdb;
    
    // Verificar se é um pedido de orçamento
    $product_type = $wpdb->get_var($wpdb->prepare(
        "SELECT product_type FROM {$wpdb->prefix}hng_orders WHERE id = %d",
        $order_id
    ));
    
    // Se for orçamento, enviar emails específicos
    if ($product_type === 'quote') {
        hng_send_quote_request_email($order_id);
        hng_send_quote_admin_new_email($order_id);
    }
}

/**
 * Envia email quando cliente solicita orçamento
 * 
 * @param int $order_id ID do pedido/orçamento
 * @return bool
 */
function hng_send_quote_request_email($order_id) {
    $order = new HNG_Order($order_id);
    
    if (!$order->id) {
        return false;
    }
    
    $site_name = get_bloginfo('name');
    $site_url = home_url();
    
    // Preparar variáveis para o template
    $variables = [
        'customer_name' => $order->get_customer_name(),
        'quote_id' => $order_id,
        'quote_date' => date_i18n(get_option('date_format'), strtotime($order->data['created_at'])),
        'products' => hng_get_order_items_html($order_id),
        'quote_link' => add_query_arg(['account-page' => 'quotes', 'quote_id' => $order_id], hng_get_myaccount_url()),
        'site_name' => $site_name,
        'site_url' => $site_url,
    ];
    
    // Carregar template
    ob_start();
    extract($variables);
    include HNG_COMMERCE_PATH . 'templates/emails/customer-quote-request.php';
    $html = ob_get_clean();
    
    // Enviar email
    $to = $order->get_customer_email();
    /* translators: %1$s = site name, %2$d = order ID */
    $subject = sprintf(__('[%1$s] Pedido de Orçamento Recebido #%2$d', 'hng-commerce'), $site_name, $order_id);
    
    return hng_send_html_email($to, $subject, $html);
}

/**
 * Envia email ao admin quando há novo orçamento
 * 
 * @param int $order_id ID do pedido/orçamento
 * @return bool
 */
function hng_send_quote_admin_new_email($order_id) {
    $order = new HNG_Order($order_id);
    
    if (!$order->id) {
        return false;
    }
    
    $site_name = get_bloginfo('name');
    
    // Preparar variáveis
    $variables = [
        'customer_name' => $order->get_customer_name(),
        'customer_email' => $order->get_customer_email(),
        'customer_phone' => $order->data['billing_phone'] ?? '',
        'quote_id' => $order_id,
        'quote_date' => date_i18n(get_option('date_format'), strtotime($order->data['created_at'])),
        'products' => hng_get_order_items_html($order_id),
        'admin_link' => admin_url('admin.php?page=hng-orders&order_id=' . $order_id),
        'site_name' => $site_name,
    ];
    
    // Carregar template
    ob_start();
    extract($variables);
    include HNG_COMMERCE_PATH . 'templates/emails/admin-quote-new.php';
    $html = ob_get_clean();
    
    // Enviar para admin
    $to = get_option('admin_email');
    /* translators: %1$s = site name, %2$d = order ID */
    $subject = sprintf(__('[%1$s] Novo Pedido de Orçamento #%2$d', 'hng-commerce'), $site_name, $order_id);
    
    return hng_send_html_email($to, $subject, $html);
}

/**
 * Envia email quando orçamento é aprovado
 * 
 * @param int $order_id ID do pedido/orçamento
 * @return bool
 */
function hng_send_quote_approved_email($order_id) {
    $order = new HNG_Order($order_id);
    
    if (!$order->id) {
        return false;
    }
    
    $post_id = $order->get_post_id();
    $site_name = get_bloginfo('name');
    $site_url = home_url();
    
    $approved_price = get_post_meta($post_id, '_quote_approved_price', true);
    $approved_shipping = get_post_meta($post_id, '_quote_approved_shipping', true);
    $approval_notes = get_post_meta($post_id, '_quote_approval_notes', true);
    $payment_link = get_post_meta($post_id, '_quote_payment_link', true);
    
    $total = floatval($approved_price) + floatval($approved_shipping);
    
    // Preparar variáveis
    $variables = [
        'customer_name' => $order->get_customer_name(),
        'quote_id' => $order_id,
        'approved_price' => 'R$ ' . number_format(floatval($approved_price), 2, ',', '.'),
        'approved_shipping' => 'R$ ' . number_format(floatval($approved_shipping), 2, ',', '.'),
        'total' => 'R$ ' . number_format($total, 2, ',', '.'),
        'approval_notes' => $approval_notes,
        'payment_link' => $payment_link ?: add_query_arg(['account-page' => 'quotes', 'quote_id' => $order_id], hng_get_myaccount_url()),
        'quote_link' => add_query_arg(['account-page' => 'quotes', 'quote_id' => $order_id], hng_get_myaccount_url()),
        'site_name' => $site_name,
        'site_url' => $site_url,
    ];
    
    // Carregar template
    ob_start();
    extract($variables);
    include HNG_COMMERCE_PATH . 'templates/emails/customer-quote-approved.php';
    $html = ob_get_clean();
    
    // Enviar email
    $to = $order->get_customer_email();
    /* translators: %1$s = site name, %2$d = order ID */
    $subject = sprintf(__('[%1$s] Seu Orçamento foi Aprovado! #%2$d', 'hng-commerce'), $site_name, $order_id);
    
    return hng_send_html_email($to, $subject, $html);
}

/**
 * Envia email quando há nova mensagem no chat de orçamento
 * 
 * @param int $order_id ID do pedido/orçamento
 * @param string $message Mensagem enviada
 * @return bool
 */
function hng_send_quote_message_email($order_id, $message) {
    $order = new HNG_Order($order_id);
    
    if (!$order->id) {
        return false;
    }
    
    $site_name = get_bloginfo('name');
    $site_url = home_url();
    
    // Preparar variáveis
    $variables = [
        'customer_name' => $order->get_customer_name(),
        'quote_id' => $order_id,
        'message' => $message,
        'quote_link' => add_query_arg(['account-page' => 'quotes', 'quote_id' => $order_id], hng_get_myaccount_url()),
        'site_name' => $site_name,
        'site_url' => $site_url,
    ];
    
    // Carregar template
    ob_start();
    extract($variables);
    include HNG_COMMERCE_PATH . 'templates/emails/customer-quote-message.php';
    $html = ob_get_clean();
    
    // Enviar email
    $to = $order->get_customer_email();
    /* translators: %1$s = site name, %2$d = order ID */
    $subject = sprintf(__('[%1$s] Nova Mensagem no Orçamento #%2$d', 'hng-commerce'), $site_name, $order_id);
    
    return hng_send_html_email($to, $subject, $html);
}

/**
 * Função auxiliar para enviar emails HTML
 * 
 * @param string $to Destinatário
 * @param string $subject Assunto
 * @param string $html Conteúdo HTML
 * @return bool
 */
function hng_send_html_email($to, $subject, $html) {
    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
    ];
    
    return wp_mail($to, $subject, $html, $headers);
}

/**
 * Gera HTML da lista de produtos do pedido
 * 
 * @param int $order_id
 * @return string
 */
function hng_get_order_items_html($order_id) {
    $order = new HNG_Order($order_id);
    
    if (!$order->id || empty($order->items)) {
        return '<p>' . __('Nenhum produto', 'hng-commerce') . '</p>';
    }
    
    $html = '<table width="100%" cellpadding="10" style="border-collapse: collapse;">';
    
    foreach ($order->items as $item) {
        $product_name = $item['product_name'] ?? $item['name'] ?? '';
        $quantity = $item['quantity'] ?? 1;
        $price = isset($item['price']) ? 'R$ ' . number_format(floatval($item['price']), 2, ',', '.') : '-';
        
        $html .= '<tr style="border-bottom: 1px solid #eee;">';
        $html .= '<td style="padding: 10px;">' . esc_html($product_name) . '</td>';
        $html .= '<td style="padding: 10px; text-align: center;">x' . esc_html($quantity) . '</td>';
        $html .= '<td style="padding: 10px; text-align: right;">' . esc_html($price) . '</td>';
        $html .= '</tr>';
    }
    
    $html .= '</table>';
    
    return $html;
}
