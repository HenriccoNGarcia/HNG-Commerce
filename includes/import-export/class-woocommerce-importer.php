<?php
/**
 * WooCommerce Importer
 * 
 * Import products and orders from WooCommerce to HNG Commerce
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// DB helper
require_once HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php';

class HNG_WooCommerce_Importer {
    
    /**
     * Check if WooCommerce is installed
     */
    public static function is_woocommerce_active() {
        return class_exists('WooCommerce');
    }
    
    /**
     * Import all products from WooCommerce
     */
    public static function import_products($args = []) {
        if (!self::is_woocommerce_active()) {
            return new WP_Error('no_woocommerce', __('WooCommerce nï¿½o estï¿½ instalado.', 'hng-commerce'));
        }
        
        $defaults = [
            'limit' => -1,
            'status' => 'publish',
            'offset' => 0,
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $wc_products = wc_get_products($args);
        
        $imported = [];
        $errors = [];
        
        foreach ($wc_products as $wc_product) {
            $result = self::import_single_product($wc_product);
            
            if (is_wp_error($result)) {
                $errors[] = [
                    'wc_id' => $wc_product->get_id(),
                    'name' => $wc_product->get_name(),
                    'error' => $result->get_error_message(),
                ];
            } else {
                $imported[] = [
                    'wc_id' => $wc_product->get_id(),
                    'hng_id' => $result,
                    'name' => $wc_product->get_name(),
                ];
            }
        }
        
        return [
            'imported' => $imported,
            'errors' => $errors,
            'total' => count($wc_products),
            'success' => count($imported),
            'failed' => count($errors),
        ];
    }
    
    /**
     * Import single product
     */
    public static function import_single_product($wc_product) {
        // Create HNG Commerce product
        $product_data = [
            'post_title' => $wc_product->get_name(),
            'post_content' => $wc_product->get_description(),
            'post_excerpt' => $wc_product->get_short_description(),
            'post_status' => $wc_product->get_status(),
            'post_type' => 'hng_product',
        ];
        
        $product_id = wp_insert_post($product_data);
        
        if (is_wp_error($product_id)) {
            return $product_id;
        }
        
        // Import meta data
        update_post_meta($product_id, '_price', $wc_product->get_regular_price());
        update_post_meta($product_id, '_sale_price', $wc_product->get_sale_price());
        update_post_meta($product_id, '_sku', $wc_product->get_sku());
        update_post_meta($product_id, '_stock', $wc_product->get_stock_quantity());
        update_post_meta($product_id, '_stock_status', $wc_product->get_stock_status());
        update_post_meta($product_id, '_manage_stock', $wc_product->get_manage_stock() ? 'yes' : 'no');
        update_post_meta($product_id, '_backorders', $wc_product->get_backorders());
        
        // Dimensions and weight
        update_post_meta($product_id, '_weight', $wc_product->get_weight());
        update_post_meta($product_id, '_length', $wc_product->get_length());
        update_post_meta($product_id, '_width', $wc_product->get_width());
        update_post_meta($product_id, '_height', $wc_product->get_height());
        
        // Product type
        $product_type = $wc_product->get_type();
        update_post_meta($product_id, '_product_type', $product_type);
        
        // Handle downloadable products
        if ($wc_product->is_downloadable()) {
            update_post_meta($product_id, '_downloadable', 'yes');
            update_post_meta($product_id, '_is_digital', 'yes');
            
            $downloads = $wc_product->get_downloads();
            if (!empty($downloads)) {
                $download_files = [];
                foreach ($downloads as $download) {
                    $download_files[] = [
                        'name' => $download->get_name(),
                        'file' => $download->get_file(),
                    ];
                }
                update_post_meta($product_id, '_downloadable_files', $download_files);
            }
        }
        
        // Import images
        $image_id = $wc_product->get_image_id();
        if ($image_id) {
            set_post_thumbnail($product_id, $image_id);
        }
        
        $gallery_ids = $wc_product->get_gallery_image_ids();
        if (!empty($gallery_ids)) {
            update_post_meta($product_id, '_product_image_gallery', implode(',', $gallery_ids));
        }
        
        // Import categories
        $category_ids = $wc_product->get_category_ids();
        if (!empty($category_ids)) {
            // Map WC categories to HNG categories
            $hng_categories = [];
            foreach ($category_ids as $cat_id) {
                $wc_cat = get_term($cat_id, 'product_cat');
                if ($wc_cat) {
                    // Create HNG category if doesn't exist
                    $hng_cat = get_term_by('name', $wc_cat->name, 'hng_product_cat');
                    if (!$hng_cat) {
                        $hng_cat = wp_insert_term($wc_cat->name, 'hng_product_cat', [
                            'description' => $wc_cat->description,
                            'slug' => $wc_cat->slug,
                        ]);
                        if (!is_wp_error($hng_cat)) {
                            $hng_categories[] = $hng_cat['term_id'];
                        }
                    } else {
                        $hng_categories[] = $hng_cat->term_id;
                    }
                }
            }
            wp_set_object_terms($product_id, $hng_categories, 'hng_product_cat');
        }
        
        // Store WooCommerce ID for reference
        update_post_meta($product_id, '_wc_product_id', $wc_product->get_id());
        
        return $product_id;
    }
    
    /**
     * Import orders
     */
    public static function import_orders($args = []) {
        if (!self::is_woocommerce_active()) {
            return new WP_Error('no_woocommerce', __('WooCommerce nï¿½o estï¿½ instalado.', 'hng-commerce'));
        }
        
        $defaults = [
            'limit' => -1,
            'status' => 'any',
            'offset' => 0,
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $wc_orders = wc_get_orders($args);
        
        $imported = [];
        $errors = [];
        
        foreach ($wc_orders as $wc_order) {
            $result = self::import_single_order($wc_order);
            
            if (is_wp_error($result)) {
                $errors[] = [
                    'wc_id' => $wc_order->get_id(),
                    'error' => $result->get_error_message(),
                ];
            } else {
                $imported[] = [
                    'wc_id' => $wc_order->get_id(),
                    'hng_id' => $result,
                ];
            }
        }
        
        return [
            'imported' => $imported,
            'errors' => $errors,
            'total' => count($wc_orders),
            'success' => count($imported),
            'failed' => count($errors),
        ];
    }
    
    /**
     * Import single order
     */
    public static function import_single_order($wc_order) {
        global $wpdb;
        
        // Extract order data
        $order_data = [
            'customer_email' => $wc_order->get_billing_email(),
            'customer_name' => $wc_order->get_billing_first_name() . ' ' . $wc_order->get_billing_last_name(),
            'customer_phone' => $wc_order->get_billing_phone(),
            'total' => $wc_order->get_total(),
            'subtotal' => $wc_order->get_subtotal(),
            'shipping_total' => $wc_order->get_shipping_total(),
            'tax_total' => $wc_order->get_total_tax(),
            'payment_method' => $wc_order->get_payment_method_title(),
            'status' => self::map_order_status($wc_order->get_status()),
            'currency' => $wc_order->get_currency(),
            'created_at' => $wc_order->get_date_created()->format('Y-m-d H:i:s'),
        ];
        
        // Insert into HNG orders table
        $wpdb->insert(
            hng_db_full_table_name('hng_orders'),
            $order_data,
            ['%s', '%s', '%s', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%s']
        );
        
        $order_id = $wpdb->insert_id;
        
        if (!$order_id) {
            return new WP_Error('order_insert_failed', __('Falha ao inserir pedido.', 'hng-commerce'));
        }
        
        // Import order items
        foreach ($wc_order->get_items() as $item) {
            $product_id = $item->get_product_id();
            
            // Try to find HNG product by WooCommerce ID
            $hng_product_id = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wc_product_id' AND meta_value = %d",
                $product_id
            ));
            
            if ($hng_product_id) {
                $wpdb->insert(
                    hng_db_full_table_name('hng_order_items'),
                    [
                        'order_id' => $order_id,
                        'product_id' => $hng_product_id,
                        'quantity' => $item->get_quantity(),
                        'price' => $item->get_total() / $item->get_quantity(),
                    ],
                    ['%d', '%d', '%d', '%f']
                );
            }
        }
        
        // Store WooCommerce order ID for reference
        update_post_meta($order_id, '_wc_order_id', $wc_order->get_id());
        
        return $order_id;
    }
    
    /**
     * Map WooCommerce status to HNG status
     */
    private static function map_order_status($wc_status) {
        $status_map = [
            'pending' => 'pending',
            'processing' => 'processing',
            'on-hold' => 'on-hold',
            'completed' => 'completed',
            'cancelled' => 'cancelled',
            'refunded' => 'refunded',
            'failed' => 'failed',
        ];
        
        return $status_map[$wc_status] ?? 'pending';
    }
}
