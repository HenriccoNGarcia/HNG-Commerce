<?php

/**

 * Gateways integration helpers ï¿½ generic handlers for one-off and manual renewal

 */



if (!defined('ABSPATH')) {

    exit;

}



// One-off payment generator: best-effort fallback for gateways that don't implement their own handler

add_action('hng_generate_oneoff_payment', function($order_id) {

    if (empty($order_id)) return;



    // If payment url already set by gateway-specific handler, skip

    $existing = get_post_meta($order_id, '_payment_url', true);

    if (!empty($existing)) return;



    $gateway = get_post_meta($order_id, '_gateway', true);

    if (empty($gateway)) $gateway = get_option('hng_default_gateway', '');

    if (empty($gateway)) return;



    $class = 'HNG_Gateway_' . ucfirst($gateway);

    if (!class_exists($class)) {

        $path = HNG_COMMERCE_PATH . 'gateways/' . $gateway . '/class-gateway-' . $gateway . '.php';

        if (file_exists($path)) require_once $path;

    }

    if (!class_exists($class)) return;



    try {

        $gw = new $class();

        if (!$gw->is_configured()) return;



        $total = floatval(get_post_meta($order_id, '_total', true));

        $method = get_post_meta($order_id, '_payment_method', true) ?: 'pix';



        $payment_data = [

            'amount' => $total,

            'method' => $method,

            'order_id' => $order_id,

        ];



        // Check if should use centralized orchestrator

        if (HNG_Payment_Orchestrator::is_centralized_gateway($gateway)) {

            // Use centralized _api-server for gateways: asaas, pagarme, mercadopago, pagseguro

            $result = HNG_Payment_Orchestrator::process_gateway_payment($gateway, $order_id, $payment_data);

        } else {

            // Use legacy direct gateway call for other gateways

            // Prefer specific creator methods

            if ($method === 'pix' && method_exists($gw, 'create_pix_payment')) {

                $result = $gw->create_pix_payment($order_id, $payment_data);

            } elseif ($method === 'boleto' && method_exists($gw, 'create_boleto_payment')) {

                $result = $gw->create_boleto_payment($order_id, $payment_data);

            } else {

                // generic

                $result = $gw->process_payment($order_id, $payment_data);

            }

        }



        if (is_wp_error($result)) {

            if (function_exists('hng_files_log_append')) {

                hng_files_log_append(HNG_COMMERCE_PATH . 'logs/gateways.log', sprintf('[HNG Oneoff Generic] Gateway %s failed for order %d: %s', $gateway, $order_id, $result->get_error_message()) . PHP_EOL);

            }

            return;

        }



        $payment_url = '';

        if (is_array($result)) {

            $payment_url = $result['payment_url'] ?? $result['url'] ?? $result['checkout_url'] ?? '';

            update_post_meta($order_id, '_payment_data', $result);

        }



        if (!empty($payment_url)) {

            update_post_meta($order_id, '_payment_url', $payment_url);

        }



    } catch (Exception $e) {

        if (function_exists('hng_files_log_append')) {

            hng_files_log_append(HNG_COMMERCE_PATH . 'logs/gateways.log', '[HNG Oneoff Generic] Exception: ' . $e->getMessage() . PHP_EOL);

        }

    }

});





// Manual renewal generic fallback

add_action('hng_subscription_manual_renewal', function($subscription_id, $order_id, $payment_method = 'pix') {

    if (empty($order_id) || empty($subscription_id)) return;



    // If payment url already set, skip

    $existing = get_post_meta($order_id, '_payment_url', true);

    if (!empty($existing)) return;



    // Try to find gateway from subscription or order

    $gateway = '';

    if (class_exists('HNG_Subscription')) {

        try {

            $sub = new HNG_Subscription($subscription_id);

            if (method_exists($sub, 'get_gateway')) {

                $gateway = $sub->get_gateway();

            }

        } catch (Exception $e) {

            // ignore

        }

    }



    if (empty($gateway)) {

        $gateway = get_post_meta($order_id, '_gateway', true);

    }

    if (empty($gateway)) $gateway = get_option('hng_default_gateway', '');

    if (empty($gateway)) return;



    $class = 'HNG_Gateway_' . ucfirst($gateway);

    if (!class_exists($class)) {

        $path = HNG_COMMERCE_PATH . 'gateways/' . $gateway . '/class-gateway-' . $gateway . '.php';

        if (file_exists($path)) require_once $path;

    }

    if (!class_exists($class)) return;



    try {

        $gw = new $class();

        if (!$gw->is_configured()) return;



        $total = floatval(get_post_meta($order_id, '_total', true));

        $payment_data = [

            'amount' => $total,

            'method' => $payment_method,

            'order_id' => $order_id,

            'subscription_id' => $subscription_id,

        ];



        // Check if should use centralized orchestrator

        if (HNG_Payment_Orchestrator::is_centralized_gateway($gateway)) {

            // Use centralized _api-server for gateways: asaas, pagarme, mercadopago, pagseguro

            $result = HNG_Payment_Orchestrator::process_gateway_payment($gateway, $order_id, $payment_data);

        } else {

            // Use legacy direct gateway call for other gateways

            if ($payment_method === 'pix' && method_exists($gw, 'create_pix_payment')) {

                $result = $gw->create_pix_payment($order_id, $payment_data);

            } elseif ($payment_method === 'boleto' && method_exists($gw, 'create_boleto_payment')) {

                $result = $gw->create_boleto_payment($order_id, $payment_data);

            } else {

                $result = $gw->process_payment($order_id, $payment_data);

            }

        }



        if (is_wp_error($result)) {

            if (function_exists('hng_files_log_append')) {

                hng_files_log_append(HNG_COMMERCE_PATH . 'logs/gateways.log', sprintf('[HNG Renewal Generic] Gateway %s failed for sub %d order %d: %s', $gateway, $subscription_id, $order_id, $result->get_error_message()) . PHP_EOL);

            }

            return;

        }



        $payment_url = '';

        if (is_array($result)) {

            $payment_url = $result['payment_url'] ?? $result['url'] ?? $result['checkout_url'] ?? '';

            update_post_meta($order_id, '_payment_data', $result);

        }



        if (!empty($payment_url)) {

            update_post_meta($order_id, '_payment_url', $payment_url);

        }



    } catch (Exception $e) {

        if (function_exists('hng_files_log_append')) {

            hng_files_log_append(HNG_COMMERCE_PATH . 'logs/gateways.log', '[HNG Renewal Generic] Exception: ' . $e->getMessage() . PHP_EOL);

        }

    }

});
