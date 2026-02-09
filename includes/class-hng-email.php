<?php
/**
 * Email - Sistema de E-mails Transacionais
 *
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Email {
    
    /**
     * Instï¿½ncia ï¿½nica
     */
    private static $instance = null;
    
    /**
     * Email manager instance
     */
    private $email_manager;
    
    /**
     * Obter instï¿½ncia
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
        $this->email_manager = new HNG_Email_Manager();
        add_action('hng_order_created', [$this, 'send_new_order_emails']);
        add_action('hng_order_status_changed', [$this, 'send_status_change_email'], 10, 3);
    }
    
    /**
     * Enviar e-mails de novo pedido
     */
    public function send_new_order_emails($order_id) {
        $order = new HNG_Order($order_id);
        
        if (!$order->get_id()) {
            return;
        }
        
        // Verificar se é pedido de orçamento
        global $wpdb;
        $product_type = $wpdb->get_var($wpdb->prepare(
            "SELECT product_type FROM {$wpdb->prefix}hng_orders WHERE id = %d",
            $order_id
        ));
        
        // Se for orçamento, não enviar emails de "novo pedido"
        // Os emails de orçamento são enviados pelas funções específicas em quote-email-functions.php
        if ($product_type === 'quote') {
            return;
        }
        
        // E-mail para o cliente
        $this->send_customer_order_email($order);
        
        // E-mail para o admin
        $this->send_admin_new_order_email($order);
    }
    
    /**
     * E-mail de confirmaï¿½ï¿½o para o cliente
     */
    private function send_customer_order_email($order) {
        $to = $order->get_customer_email();
        /* translators: %1$s: site name, %2$s: order number */
        $subject = sprintf(esc_html__('[%1$s] Pedido recebido #%2$s', 'hng-commerce'), get_bloginfo('name'), $order->get_order_number());
        
        $message = HNG_Email_Manager::get_template('customer_new_order');
        $message = HNG_Email_Manager::process_variables($message, [
            'customer_name' => $order->get_customer_name(),
            'order_number' => $order->get_order_number(),
            'order_date' => $order->get_date_created(),
            'order_total' => $order->get_formatted_total(),
            'payment_method' => $order->get_payment_method_title(),
            'order_items' => $order->get_order_items_html(),
            'shipping_address' => $order->get_formatted_shipping_address(),
            'order_link' => $order->get_view_order_url(),
        ]);
        
        $headers = $this->get_email_headers();
        
        wp_mail($to, $subject, $message, $headers);
    }
    
    /**
     * E-mail de novo pedido para admin
     */
    private function send_admin_new_order_email($order) {
        $to = get_option('admin_email');
        /* translators: %1$s: site name, %2$s: order number */
        $subject = sprintf(esc_html__('[%1$s] Novo pedido #%2$s', 'hng-commerce'), get_bloginfo('name'), $order->get_order_number());
        
        $message = HNG_Email_Manager::get_template('admin_new_order');
        $message = HNG_Email_Manager::process_variables($message, [
            'order_number' => $order->get_order_number(),
            'order_date' => $order->get_date_created(),
            'order_total' => $order->get_formatted_total(),
            'commission' => hng_price($order->get_commission()),
            'payment_method' => $order->get_payment_method_title(),
            'customer_name' => $order->get_customer_name(),
            'customer_email' => $order->get_customer_email(),
            'customer_phone' => $order->get_customer_phone(),
            'customer_cpf' => $order->get_customer_cpf(),
            'order_items' => $order->get_order_items_html(),
            'shipping_address' => $order->get_formatted_shipping_address(),
        ]);
        
        $headers = $this->get_email_headers();
        
        wp_mail($to, $subject, $message, $headers);
    }
    
    /**
     * E-mail de mudanï¿½a de status
     */
    public function send_status_change_email($order_id, $old_status, $new_status) {
        $order = new HNG_Order($order_id);
        
        // Enviar e-mails para status especï¿½ficos
        $send_on_status = ['hng-processing', 'hng-preparing', 'hng-shipped', 'hng-completed', 'hng-cancelled', 'hng-refunded'];
        
        if (!in_array($new_status, $send_on_status)) {
            return;
        }
        
        // Mapear status para template
        $template_map = [
            'hng-processing' => 'customer-order-processing',
            'hng-preparing' => 'customer-order-preparing',
            'hng-shipped' => 'customer-order-shipped',
            'hng-completed' => 'customer-order-completed',
            'hng-cancelled' => 'customer-status-change', // Template genrico
            'hng-refunded' => 'customer-status-change', // Template genrico
        ];
        
        // Mapear status para assunto
        $subject_map = [
            /* translators: %1$s: site name, %2$s: order number */
            'hng-processing' => __('[%1$s] Pagamento Confirmado - Pedido #%2$s', 'hng-commerce'),
            /* translators: %1$s: site name, %2$s: order number */
            'hng-preparing' => __('[%1$s] Pedido #%2$s em Separao', 'hng-commerce'),
            /* translators: %1$s: site name, %2$s: order number */
            'hng-shipped' => __('[%1$s] Pedido #%2$s Enviado - Cdigo de Rastreamento', 'hng-commerce'),
            /* translators: %1$s: site name, %2$s: order number */
            'hng-completed' => __('[%1$s] Pedido #%2$s Entregue', 'hng-commerce'),
            /* translators: %1$s: site name, %2$s: order number */
            'hng-cancelled' => __('[%1$s] Pedido #%2$s Cancelado', 'hng-commerce'),
            /* translators: %1$s: site name, %2$s: order number */
            'hng-refunded' => __('[%1$s] Pedido #%2$s Reembolsado', 'hng-commerce'),
        ];
        
        $template = isset($template_map[$new_status]) ? $template_map[$new_status] : 'customer-status-change';
        /* translators: %1$s: site name, %2$s: order number */
        $subject_format = isset($subject_map[$new_status]) ? $subject_map[$new_status] : __('[%1$s] Status do pedido #%2$s atualizado', 'hng-commerce');
        
        $to = $order->get_customer_email();
        $subject = sprintf($subject_format, get_bloginfo('name'), $order->get_order_number());
        
        $message = $this->get_email_template($template, [
            'order' => $order,
            'old_status' => $old_status,
            'new_status' => $new_status,
            'customer_name' => $order->get_customer_name(),
        ]);
        
        $headers = $this->get_email_headers();
        
        wp_mail($to, $subject, $message, $headers);
    }
    
    /**
     * Obter template de e-mail
     */
    private function get_email_template($template_name, $args = []) {
        $template = HNG_Email_Manager::get_template($template_name);
        return HNG_Email_Manager::process_variables($template, $args);
    }
    
    /**
     * Header do e-mail
     */
    private function get_email_header() {
        $site_name = get_bloginfo('name');
        $site_url = home_url();
        
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . esc_html($site_name) . '</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f4f4f4; }
                .email-container { max-width: 600px; margin: 20px auto; background: #fff; }
                .email-header { background: #3498db; color: #fff; padding: 20px; text-align: center; }
                .email-header h1 { margin: 0; font-size: 24px; }
                .email-body { padding: 30px; }
                .email-footer { background: #f9f9f9; padding: 20px; text-align: center; font-size: 12px; color: #666; }
                .order-details { background: #f9f9f9; padding: 20px; margin: 20px 0; border-radius: 5px; }
                .order-items { width: 100%; border-collapse: collapse; margin: 20px 0; }
                .order-items th, .order-items td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
                .order-items th { background: #f0f0f0; font-weight: bold; }
                .order-total { font-size: 18px; font-weight: bold; color: #3498db; }
                .button { display: inline-block; padding: 12px 24px; background: #3498db; color: #fff !important; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .button:hover { background: #2980b9; }
            </style>
        </head>
        <body>
            <div class="email-container">
                <div class="email-header">
                    <h1>' . esc_html($site_name) . '</h1>
                </div>
                <div class="email-body">
        ';
    }
    
    /**
     * Footer do e-mail
     */
    private function get_email_footer() {
        $site_name = get_bloginfo('name');
        $year = gmdate('Y');
        
        return '
                </div>
                <div class="email-footer">
                    <p>&copy; ' . $year . ' ' . esc_html($site_name) . '. Todos os direitos reservados.</p>
                    <p>Este ï¿½ um e-mail automï¿½tico. Por favor, nï¿½o responda.</p>
                </div>
            </div>
        </body>
        </html>
        ';
    }
    
    /**
     * Headers do e-mail
     */
    private function get_email_headers() {
        $from_name = get_bloginfo('name');
        $from_email = get_option('admin_email');
        
        return [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
        ];
    }
}
