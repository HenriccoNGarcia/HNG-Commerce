<?php
/**
 * Shipping Manager
 *
 * Manages all shipping methods and calculations
 *
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Shipping_Manager {
    
    private static $instance = null;
    private $methods = [];
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->load_methods();
    }
    
    /**
     * Load all shipping methods
     */
    private function load_methods() {
        $methods = apply_filters('hng_shipping_methods', []);
        
        foreach ($methods as $id => $class) {
            if (class_exists($class)) {
                $this->methods[$id] = new $class();
            }
        }
    }
    
    /**
     * Get all enabled shipping methods
     */
    public function get_enabled_methods() {
        $enabled = [];
        
        foreach ($this->methods as $id => $method) {
            if ($method->is_enabled()) {
                $enabled[$id] = $method;
            }
        }
        
        return $enabled;
    }
    
    /**
     * Get all registered shipping methods
     */
    public function get_all_methods() {
        return $this->methods;
    }
    
    /**
     * Calculate shipping for package
     */
    public function calculate_shipping($package) {
        // Generate cache key based on package data
        $cache_key = $this->generate_cache_key($package);
        
        // Check cache first (2 hour TTL) - disable cache for debugging
        $use_cache = apply_filters('hng_shipping_use_cache', true);
        if ($use_cache) {
            $cached_rates = get_transient($cache_key);
            if (false !== $cached_rates && is_array($cached_rates)) {
                return $cached_rates;
            }
        }
        
        $all_rates = [];
        $enabled_methods = $this->get_enabled_methods();
        
        // Log para debug
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[HNG Shipping] Calculando frete para CEP: ' . ($package['destination']['postcode'] ?? 'N/A'));
            error_log('[HNG Shipping] Métodos habilitados: ' . implode(', ', array_keys($enabled_methods)));
        }
        
        foreach ($enabled_methods as $id => $method) {
            $rates = $method->calculate_shipping($package);
            
            // Log resultado de cada método
            if (defined('WP_DEBUG') && WP_DEBUG) {
                if (is_wp_error($rates)) {
                    error_log("[HNG Shipping] {$id}: ERRO - " . $rates->get_error_message());
                } elseif (is_array($rates)) {
                    error_log("[HNG Shipping] {$id}: " . count($rates) . " opções encontradas");
                } else {
                    error_log("[HNG Shipping] {$id}: Retorno inválido - " . gettype($rates));
                }
            }
            
            if (!is_wp_error($rates) && is_array($rates)) {
                $all_rates = array_merge($all_rates, $rates);
            }
        }
        
        // Sort by cost (cheapest first)
        usort($all_rates, function($a, $b) {
            return $a['cost'] <=> $b['cost'];
        });
        
        // Cache for 2 hours (only if we have results)
        if ($use_cache && !empty($all_rates)) {
            set_transient($cache_key, $all_rates, 2 * HOUR_IN_SECONDS);
        }
        
        return $all_rates;
    }
    
    /**
     * Generate cache key for shipping calculation
     * 
     * @param array $package Package data
     * @return string Cache key
     */
    private function generate_cache_key($package) {
        // Build a unique key based on destination + items
        $key_parts = [
            'hng_shipping',
            $package['destination']['postcode'] ?? '',
        ];
        
        // Add product IDs and quantities
        foreach ($package['items'] as $item) {
            $key_parts[] = $item['product_id'] . 'x' . $item['quantity'];
        }
        
        return 'hng_ship_' . md5(implode('_', $key_parts));
    }
    
    /**
     * Clear shipping cache
     * 
     * @param string $destination_zip Optional specific zip code
     */
    public function clear_cache($destination_zip = null) {
        global $wpdb;
        
        if ($destination_zip) {
            // Clear specific destination cache
            $pattern = '%hng_ship_%' . preg_replace('/\D/', '', $destination_zip) . '%';
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    $pattern
                )
            );
        } else {
            // Clear all shipping cache
            $wpdb->query(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_hng_ship_%'"
            );
            $wpdb->query(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_hng_ship_%'"
            );
        }
    }
    
    /**
     * Build package from cart items
     */
    public function build_package_from_cart($destination_zip) {
        $cart = HNG_Cart::instance();
        $items = $cart->get_cart();
        
        $package_items = [];
        $cart_total = 0;
        
        foreach ($items as $item) {
            $package_items[] = [
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
            ];
            
            $product = new HNG_Product($item['product_id']);
            $cart_total += $product->get_price() * $item['quantity'];
        }
        
        return [
            'destination' => [
                'postcode' => $destination_zip,
            ],
            'items' => $package_items,
            'cart_total' => $cart_total,
        ];
    }
    
    /**
     * Build package from single product
     */
    public function build_package_from_product($product_id, $quantity, $destination_zip) {
        $product = new HNG_Product($product_id);
        
        return [
            'destination' => [
                'postcode' => $destination_zip,
            ],
            'items' => [
                [
                    'product_id' => $product_id,
                    'quantity' => $quantity,
                ]
            ],
            'cart_total' => $product->get_price() * $quantity,
        ];
    }
}
