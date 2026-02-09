<?php
/**
 * Compatibilidade com WordPress 6.8+
 * Corrige erro: "wp_is_block_theme was called incorrectly"
 * 
 * @package HNG_Commerce
 * @since 1.0.6
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_WP68_Compatibility {

    /**
     * Inicializar correcoes
     */
    public static function init() {
        // Suprime avisos incorretos do WordPress 6.8
        add_action('init', array(__CLASS__, 'suppress_wp68_notices'), 1);
        
        // Garante que nao ha output antes dos headers
        add_action('plugins_loaded', array(__CLASS__, 'start_output_buffer'), -9999);
    }

    /**
     * Inicia buffer de output para prevenir "headers already sent"
     */
    public static function start_output_buffer() {
        if (!is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
            return;
        }
        
        // Inicia output buffering apenas se ainda nao iniciado
        if (ob_get_level() === 0) {
            ob_start();
        }
    }

    /**
     * Suprime avisos incorretos do WP 6.8 sobre wp_is_block_theme
     */
    public static function suppress_wp68_notices() {
        global $wp_version;
        
        // Apenas para WordPress 6.8+
        if (version_compare($wp_version, '6.8', '<')) {
            return;
        }

        // Remove o hook que causa o aviso prematuro
        remove_action('init', 'wp_register_theme_directory');
        
        // Re-adiciona com prioridade mais baixa para executar depois
        add_action('init', 'wp_register_theme_directory', 20);
        
        // Filtro para suprimir avisos incorretos
        add_filter('doing_it_wrong_trigger_error', array(__CLASS__, 'filter_wp68_warnings'), 10, 4);
    }

    /**
     * Filtra avisos específicos do WP 6.8
     * 
     * @param bool $trigger Se deve disparar o erro
     * @param string $function Nome da função
     * @param string $message Mensagem de erro
     * @param string $version Versão do WP
     * @return bool
     */
    public static function filter_wp68_warnings($trigger, $function, $message, $version) {
        // Suprime apenas o aviso específico sobre wp_is_block_theme
        // Cast para string para evitar deprecated warning com null
        $function = (string) $function;
        $message = (string) $message;
        
        if ($function === 'wp_is_block_theme' && 
            strpos($message, 'theme directory is registered') !== false) {
            return false;
        }
        
        return $trigger;
    }
}

// Inicializa MUITO cedo para capturar todos os outputs
add_action('muplugins_loaded', array('HNG_WP68_Compatibility', 'init'), 1);
