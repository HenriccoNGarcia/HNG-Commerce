<?php
/**
 * HNG Autoloader
 * 
 * Carrega classes automaticamente
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Autoloader {
    
    /**
     * Registrar autoloader
     */
    public static function register() {
        spl_autoload_register([__CLASS__, 'autoload']);
    }
    
    /**
     * Autoload
     */
    public static function autoload($class) {
        // PHP 8.1+ null safety
        if ($class === null || !is_string($class)) {
            return;
        }
        
        // Apenas classes HNG
        if (strpos($class, 'HNG_') !== 0) {
            return;
        }
        
        // Converter nome da classe para arquivo
        $file = self::get_file_name_from_class($class);
        
        // Tentar carregar
        self::load_file($file);
    }
    
    /**
     * Converter nome da classe em nome de arquivo
     */
    private static function get_file_name_from_class($class) {
        // HNG_Commerce_Product -> class-hng-commerce-product.php
        $class = strtolower((string) $class);
        $class = str_replace('_', '-', $class);
        return 'class-' . $class . '.php';
    }
    
    /**
     * Carregar arquivo
     */
    private static function load_file($file) {
        $paths = [
            HNG_COMMERCE_PATH . 'includes/',
            HNG_COMMERCE_PATH . 'includes/admin/',
            HNG_COMMERCE_PATH . 'includes/admin/pages/',
            HNG_COMMERCE_PATH . 'includes/admin/meta-boxes/',
            HNG_COMMERCE_PATH . 'includes/api/',
            HNG_COMMERCE_PATH . 'includes/gateways/',
            HNG_COMMERCE_PATH . 'includes/shipping/',
            HNG_COMMERCE_PATH . 'includes/integrations/',
            HNG_COMMERCE_PATH . 'includes/financial/',
            HNG_COMMERCE_PATH . 'includes/analytics/',
        ];
        
        foreach ($paths as $path) {
            if (file_exists($path . $file)) {
                require_once $path . $file;
                return;
            }
        }
    }
}

HNG_Autoloader::register();
