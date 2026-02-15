<?php
/**
 * Shipping Method Base Class
 * 
 * Abstract base for all shipping methods
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class HNG_Shipping_Method {
    
    /**
     * Method ID
     */
    public $id;
    
    /**
     * Method title
     */
    public $title;
    
    /**
     * Is enabled
     */
    public $enabled = false;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init();
        $this->enabled = $this->get_option('enabled', 'no') === 'yes';
    }
    
    /**
     * Initialize method
     */
    abstract public function init();
    
    /**
     * Get settings
     */
    abstract public function get_settings();
    
    /**
     * Calculate shipping rates
     * 
     * @param array $package Package data (items, destination, origin)
     * @return array|WP_Error Array of rates or error
     */
    abstract public function calculate_shipping($package);
    
    /**
     * Get option
     */
    protected function get_option($key, $default = '') {
        return get_option("hng_shipping_{$this->id}_{$key}", $default);
    }
    
    /**
     * Get method title
     */
    public function get_title() {
        return $this->title;
    }

    /**
     * Get method description
     */
    public function get_description() {
        return isset($this->description) ? $this->description : '';
    }

    /**
     * Check if method is enabled
     */
    public function is_enabled() {
        return $this->enabled;
    }
    
    /**
     * Format rate for display
     */
    protected function format_rate($rate_data) {
        return [
            'id' => $this->id . ':' . ($rate_data['service'] ?? ''),
            'method_id' => $this->id,
            'method_title' => $this->title,
            'service_name' => $rate_data['name'] ?? '',
            'cost' => floatval($rate_data['cost'] ?? 0),
            'delivery_time' => absint($rate_data['delivery_time'] ?? 0),
            /* translators: 1: number of days */
            'delivery_time_label' => (function($days){
                $d = absint($days);
                /* translators: %1$d: number of days (singular/plural handled by _n) */
                $format_days = _n('%1$d dia útil', '%1$d dias úteis', $d, 'hng-commerce');
                return sprintf($format_days, $d);
            })($rate_data['delivery_time'] ?? 0),
            /* translators: 1: service name, 2: cost, 3: delivery time label */
            'label' => (function($name, $cost, $days){
                $d = absint($days);
                /* translators: %1$d: number of days (singular/plural handled by _n) */
                $format_days = _n('%1$d dia útil', '%1$d dias úteis', $d, 'hng-commerce');
                $delivery_label = sprintf($format_days, $d);
                /* translators: 1: service name, 2: cost (formatted), 3: delivery time label */
                $format_label = esc_html__( '%1$s - R$ %2$s (%3$s)', 'hng-commerce' );
                return sprintf( $format_label, esc_html( $name ), esc_html( number_format( floatval($cost), 2, ',', '.' ) ), esc_html( $delivery_label ) );
            })($rate_data['name'] ?? '', $rate_data['cost'] ?? 0, $rate_data['delivery_time'] ?? 0),
        ];
    }
    
    /**
     * Log message
     */
    protected function log($message, $data = null) {
        $entry = "[HNG Shipping - {$this->id}] " . $message;
        if (function_exists('hng_files_log_append')) {
            hng_files_log_append(HNG_COMMERCE_PATH . 'logs/shipping.log', $entry . PHP_EOL);
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
            if ($data) { hng_files_log_append(HNG_COMMERCE_PATH . 'logs/shipping.log', print_r($data, true) . PHP_EOL); }
        }
    }

    /**
     * Defaults helpers (weight in kg, dimensions in cm)
     */
    protected function get_default_weight_kg() {
        return floatval(get_option('hng_shipping_default_weight', 0.3));
    }

    protected function get_default_length_cm() {
        return floatval(get_option('hng_shipping_default_length', 16));
    }

    protected function get_default_width_cm() {
        return floatval(get_option('hng_shipping_default_width', 11));
    }

    protected function get_default_height_cm() {
        return floatval(get_option('hng_shipping_default_height', 2));
    }
}
