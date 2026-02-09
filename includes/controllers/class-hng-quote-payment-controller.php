<?php
/**
 * Frontend Controller - Quote Payment
 * 
 * Handles customer payment link generated from approved quotes.
 * Validates token and redirects to the appropriate payment method page.
 *
 * @package HNG_Commerce
 * @since 1.1.2
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Quote_Payment_Controller {
    /** @var HNG_Quote_Payment_Controller */
    private static $instance = null;

    /**
     * Singleton accessor
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action('template_redirect', [$this, 'maybe_handle_quote_payment']);
    }

    /**
     * Handle quote payment link
     */
    public function maybe_handle_quote_payment() {
        // Only handle on front-end requests
        if (is_admin()) { return; }

        // Validate query args
        $token = isset($_GET['quote_payment']) ? sanitize_text_field(wp_unslash($_GET['quote_payment'])) : '';
        $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
        $method = isset($_GET['method']) ? sanitize_key(wp_unslash($_GET['method'])) : '';

        if (empty($token) || !$order_id) { return; }

        // Load order
        if (!class_exists('HNG_Order')) { return; }
        $order = new HNG_Order($order_id);
        if (!$order->get_id()) { return; }

        // Get post_id to read meta
        $post_id = $order->get_post_id();
        if (!$post_id) { return; }

        $saved_token = get_post_meta($post_id, '_quote_payment_token', true);
        if (!$saved_token || !hash_equals($saved_token, $token)) {
            wp_die(esc_html__('Link de pagamento inválido ou expirado.', 'hng-commerce'));
        }
        
        // Check if there are pending terms that need to be accepted
        if (function_exists('hng_quote_chat')) {
            $pending_terms = hng_quote_chat()->get_pending_terms($order_id);
            if ($pending_terms) {
                // Redirect to quote page with message
                hng_add_notice(
                    __('Você precisa aceitar os termos de serviço antes de prosseguir com o pagamento.', 'hng-commerce'),
                    'error'
                );
                $quote_url = add_query_arg([
                    'account-page' => 'quotes',
                    'order' => $order_id
                ], hng_get_myaccount_url());
                wp_safe_redirect($quote_url);
                exit;
            }
        }

        // Default method: PIX if not provided
        if (empty($method)) {
            $method = get_option('hng_quote_default_method', 'pix');
        }

        // Update status to pending before redirecting to payment
        $order->update_status('hng-pending', __('Cliente iniciou pagamento de orçamento aprovado.', 'hng-commerce'));

        // Build payment page URL
        $payment_url = home_url('/pagamento/' . $method . '/');
        $redirect = add_query_arg([
            'order_id' => $order->get_id(),
            'key' => $order->get_order_number(),
        ], $payment_url);

        wp_safe_redirect($redirect);
        exit;
    }
}

// Initialize controller if quote products are enabled
if (get_option('hng_enable_quote_products', 'no') === 'yes') {
    HNG_Quote_Payment_Controller::instance();
}
