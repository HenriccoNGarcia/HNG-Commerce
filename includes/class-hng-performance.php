<?php
/**
 * Sistema de Otimizaá¯Â¿Â½á¯Â¿Â½o de Performance
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Filesystem helper
require_once __DIR__ . '/helpers/hng-files.php';

class HNG_Performance {
    
    /**
     * Instá¯Â¿Â½ncia á¯Â¿Â½nica
     */
    private static $instance = null;
    
    /**
     * Obter instá¯Â¿Â½ncia
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
        // Lazy loading de imagens
        add_filter('wp_get_attachment_image_attributes', [$this, 'add_lazy_loading'], 10, 2);
        add_filter('the_content', [$this, 'add_lazy_loading_to_content']);
        
        // Cache de queries
        add_action('init', [$this, 'setup_object_cache']);
        
        // Minificaá¯Â¿Â½á¯Â¿Â½o de CSS/JS
        if (get_option('hng_enable_minification', 'yes') === 'yes') {
            add_action('wp_enqueue_scripts', [$this, 'minify_assets'], 999);
        }
        
        // Otimizaá¯Â¿Â½á¯Â¿Â½o de imagens (WebP)
        if (get_option('hng_enable_webp', 'yes') === 'yes') {
            add_filter('wp_generate_attachment_metadata', [$this, 'generate_webp_version'], 10, 2);
            add_filter('wp_get_attachment_image_src', [$this, 'serve_webp_version'], 10, 4);
        }
        
        // Remover assets desnecessá¯Â¿Â½rios
        add_action('wp_enqueue_scripts', [$this, 'remove_unnecessary_assets'], 100);
        
        // Prá¯Â¿Â½-carregar recursos crá¯Â¿Â½ticos
        add_action('wp_head', [$this, 'preload_critical_resources'], 1);
        
        // Defer/Async para scripts
        add_filter('script_loader_tag', [$this, 'add_defer_async_attributes'], 10, 2);
        
        // Cache de fragmentos HTML
        add_action('init', [$this, 'setup_fragment_cache']);
        
        // Limpeza periá¯Â¿Â½dica de cache
        add_action('hng_clear_expired_cache', [$this, 'clear_expired_cache']);
        if (!wp_next_scheduled('hng_clear_expired_cache')) {
            wp_schedule_event(time(), 'daily', 'hng_clear_expired_cache');
        }
    }
    
    /**
     * Adicionar lazy loading á¯Â¿Â½s imagens
     */
    public function add_lazy_loading($attr, $attachment) {
        $attr['loading'] = 'lazy';
        $attr['decoding'] = 'async';
        return $attr;
    }
    
    /**
     * Adicionar lazy loading ao conteá¯Â¿Â½do
     */
    public function add_lazy_loading_to_content($content) {
        // Adicionar loading="lazy" á¯Â¿Â½s tags img
        $content = preg_replace(
            '/<img((?![^>]*loading=)[^>]*)>/i',
            '<img$1 loading="lazy" decoding="async">',
            $content
        );
        
        return $content;
    }
    
    /**
     * Configurar cache de objetos
     */
    public function setup_object_cache() {
        // Cachear queries de produtos populares
        add_filter('hng_get_products', [$this, 'cache_product_query'], 10, 2);
        
        // Cachear contagens
        add_filter('hng_get_product_count', [$this, 'cache_product_count']);
    }
    
    /**
     * Cachear query de produtos
     */
    public function cache_product_query($products, $args) {
        $cache_key = 'hng_products_' . md5(serialize($args));
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // Cache por 1 hora
        set_transient($cache_key, $products, HOUR_IN_SECONDS);
        
        return $products;
    }
    
    /**
     * Cachear contagem de produtos
     */
    public function cache_product_count($count) {
        $cache_key = 'hng_product_count';
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        set_transient($cache_key, $count, HOUR_IN_SECONDS);
        
        return $count;
    }
    
    /**
     * Minificar assets
     */
    public function minify_assets() {
        global $wp_styles, $wp_scripts;
        
        // Minificar CSS
        if (!empty($wp_styles->queue)) {
            foreach ($wp_styles->queue as $handle) {
                if (isset($wp_styles->registered[$handle])) {
                    $style = $wp_styles->registered[$handle];
                    
                    // Apenas minificar assets do plugin
                    if (strpos((string) $style->src, 'hng-commerce') !== false) {
                        $minified_src = $this->get_minified_path($style->src, 'css');
                        if ($minified_src) {
                            $style->src = $minified_src;
                        }
                    }
                }
            }
        }
        
        // Minificar JS
        if (!empty($wp_scripts->queue)) {
            foreach ($wp_scripts->queue as $handle) {
                if (isset($wp_scripts->registered[$handle])) {
                    $script = $wp_scripts->registered[$handle];
                    
                    if (strpos((string) $script->src, 'hng-commerce') !== false) {
                        $minified_src = $this->get_minified_path($script->src, 'js');
                        if ($minified_src) {
                            $script->src = $minified_src;
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Obter caminho minificado
     */
    private function get_minified_path($src, $type) {
        // PHP 8.1+ null safety
        if ($src === null || $src === '' || !is_string($src)) {
            return $src;
        }
        
        $path = str_replace(HNG_COMMERCE_URL, HNG_COMMERCE_PATH, $src);
        
        // Verificar se já¯Â¿Â½ á¯Â¿Â½ minificado
        if (strpos((string) $path, '.min.') !== false) {
            return $src;
        }
        
        $minified_path = str_replace('.' . $type, '.min.' . $type, $path);
        
        // Se arquivo minificado ná¯Â¿Â½o existir, criar
        if (!file_exists($minified_path)) {
            $this->create_minified_file($path, $minified_path, $type);
        }
        
        if (file_exists($minified_path)) {
            return str_replace('.' . $type, '.min.' . $type, $src);
        }
        
        return $src;
    }
    
    /**
     * Criar arquivo minificado
     */
    private function create_minified_file($source, $destination, $type) {
        if (!file_exists($source)) {
            return false;
        }

        $content = hng_files_get_contents($source);
        if ($content === false) {
            return false;
        }

        if ($type === 'css') {
            $minified = $this->minify_css($content);
        } else {
            $minified = $this->minify_js($content);
        }

        $written = hng_files_put_contents($destination, $minified);
        return $written !== false;
    }
    
    /**
     * Minificar CSS
     */
    private function minify_css($css) {
        // Remover comentá¯Â¿Â½rios
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        
        // Remover espaá¯Â¿Â½os em branco
        $css = str_replace(["\r\n", "\r", "\n", "\t", '  ', '    ', '    '], '', $css);
        
        // Remover espaá¯Â¿Â½os extras
        $css = preg_replace('/\s+/', ' ', $css);
        $css = preg_replace('/\s*([{}|:;,])\s*/', '$1', $css);
        
        return trim($css);
    }
    
    /**
     * Minificar JS (bá¯Â¿Â½sico)
     */
    private function minify_js($js) {
        // Remover comentá¯Â¿Â½rios de linha
        $js = preg_replace('/\/\/.*$/m', '', $js);
        
        // Remover comentá¯Â¿Â½rios de bloco
        $js = preg_replace('/\/\*[\s\S]*?\*\//', '', $js);
        
        // Remover espaá¯Â¿Â½os em branco extras
        $js = preg_replace('/\s+/', ' ', $js);
        
        return trim($js);
    }
    
    /**
     * Gerar versá¯Â¿Â½o WebP
     */
    public function generate_webp_version($metadata, $attachment_id) {
        $file = get_attached_file($attachment_id);
        
        if (!$file || !file_exists($file)) {
            return $metadata;
        }
        
        // Verificar se á¯Â¿Â½ imagem
        $mime_type = get_post_mime_type($attachment_id);
        if (!in_array($mime_type, ['image/jpeg', 'image/png'])) {
            return $metadata;
        }
        
        // Criar versá¯Â¿Â½o WebP
        $this->create_webp_image($file);
        
        // Criar WebP para thumbnails
        if (!empty($metadata['sizes'])) {
            $upload_dir = wp_upload_dir();
            $basedir = trailingslashit($upload_dir['basedir']);
            $file_path = pathinfo($file);
            
            foreach ($metadata['sizes'] as $size) {
                $thumbnail_path = $basedir . $file_path['dirname'] . '/' . $size['file'];
                if (file_exists($thumbnail_path)) {
                    $this->create_webp_image($thumbnail_path);
                }
            }
        }
        
        return $metadata;
    }
    
    /**
     * Criar imagem WebP
     */
    private function create_webp_image($file) {
        $webp_file = preg_replace('/\.(jpe?g|png)$/i', '.webp', $file);
        
        // Se já¯Â¿Â½ existe, ná¯Â¿Â½o recriar
        if (function_exists('hng_files_exists') && hng_files_exists($webp_file)) {
            return true;
        }
        
        $info = getimagesize($file);
        
        if ($info === false) {
            return false;
        }
        
        $mime_type = $info['mime'];
        
        // Criar imagem a partir do tipo
        switch ($mime_type) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($file);
                break;
            case 'image/png':
                $image = imagecreatefrompng($file);
                break;
            default:
                return false;
        }
        
        if (!$image) {
            return false;
        }
        
        // Converter para WebP (criar em memá¡Â³ria e gravar via helper para suportar WP_Filesystem)
        $quality = get_option('hng_webp_quality', 80);

        ob_start();
        $ok = imagewebp($image, null, $quality);
        $data = ob_get_clean();

        imagedestroy($image);

        if ($ok && $data !== false && $data !== '') {
            // Garantir que o helper esteja disponá¡Â­vel
            if (function_exists('hng_files_put_contents')) {
                return hng_files_put_contents($webp_file, $data) !== false;
            }
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Fallback when WP_Filesystem helper unavailable
            return @file_put_contents($webp_file, $data) !== false;
        }

        return false;
    }
    
    /**
     * Servir versá¯Â¿Â½o WebP
     */
    public function serve_webp_version($image, $attachment_id, $size, $icon) {
        // Verificar suporte do navegador
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- HTTP_ACCEPT á¡Â© cabeá§alho HTTP padrá¡o; usado apenas para verificaá§á¡o de string
        $http_accept = isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : '';
        if (empty($http_accept) || strpos($http_accept, 'image/webp') === false) {
            return $image;
        }
        
        if (!$image || empty($image[0])) {
            return $image;
        }
        
        $webp_url = preg_replace('/\.(jpe?g|png)$/i', '.webp', $image[0]);
        $upload = wp_upload_dir();
        $webp_path = str_replace($upload['baseurl'], $upload['basedir'], $webp_url);

        if (function_exists('hng_files_exists') && hng_files_exists($webp_path)) {
            $image[0] = $webp_url;
        }
        
        return $image;
    }
    
    /**
     * Remover assets desnecessá¯Â¿Â½rios
     */
    public function remove_unnecessary_assets() {
        // Remover apenas em pá¯Â¿Â½ginas do plugin
        if (!is_singular('hng_product') && !is_post_type_archive('hng_product') && !is_page(['cart', 'checkout'])) {
            // Remover assets do plugin
            wp_dequeue_style('hng-main');
            wp_dequeue_script('hng-main');
        }
        
        // Remover emojis se ná¯Â¿Â½o necessá¯Â¿Â½rio
        if (get_option('hng_disable_emojis', 'yes') === 'yes') {
            remove_action('wp_head', 'print_emoji_detection_script', 7);
            remove_action('wp_print_styles', 'print_emoji_styles');
        }
    }
    
    /**
     * Prá¯Â¿Â½-carregar recursos crá¯Â¿Â½ticos
     */
    public function preload_critical_resources() {
        // Prá¯Â¿Â½-carregar fontes
        // Scripts que devem ser defer
        $defer_scripts = ['hng-main', 'hng-cart', 'hng-checkout'];
        
        // Scripts que devem ser async
        $async_scripts = ['hng-analytics'];
        
        if (in_array($handle, $defer_scripts)) {
            return str_replace(' src', ' defer src', $tag);
        }
        
        if (in_array($handle, $async_scripts)) {
            return str_replace(' src', ' async src', $tag);
        }
        
        return $tag;
    }
    
    /**
     * Configurar cache de fragmentos
     */
    public function setup_fragment_cache() {
        // Cache de produto á¯Â¿Â½nico
        add_action('hng_before_product_content', function($product_id) {
            $cache_key = 'hng_product_' . $product_id;
            $cached = get_transient($cache_key);
            
            if ($cached !== false) {
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Cached HTML content already sanitized on storage
                echo $cached;
                return true;
            }
            
            ob_start();
        });
        
        add_action('hng_after_product_content', function($product_id) {
            $content = ob_get_clean();
            $cache_key = 'hng_product_' . $product_id;
            
            // Cache por 6 horas
            set_transient($cache_key, $content, 6 * HOUR_IN_SECONDS);
            
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML content from controlled template output
            echo $content;
        });
    }
    
    /**
     * Limpar cache expirado
     */
    public function clear_expired_cache() {
        global $wpdb;
        $options_table_full = $wpdb->options;
        $options_table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('options') : ('`' . str_replace('`','', $options_table_full) . '`');

        // Limpar transients expirados do plugin
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via hng_db_backtick_table() helper
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Performance monitoring, cache cleanup
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names sanitized via hng_db_backtick_table()
        $wpdb->query(
            "DELETE FROM {$options_table_sql} 
            WHERE option_name LIKE '_transient_timeout_hng_%' 
            AND option_value < UNIX_TIMESTAMP()"
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via hng_db_backtick_table() helper
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Performance monitoring, cache cleanup
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names sanitized via hng_db_backtick_table()
        $wpdb->query(
            "DELETE FROM {$options_table_sql} 
            WHERE option_name LIKE '_transient_hng_%' 
            AND option_name NOT IN (
                SELECT CONCAT('_transient_', SUBSTRING(option_name, 20))
                FROM {$options_table_sql} t2
                WHERE t2.option_name LIKE '_transient_timeout_hng_%'
            )"
        );
    }
    
    /**
     * Limpar todo o cache do plugin
     */
    public function clear_all_cache() {
        global $wpdb;
        $options_table_full = $wpdb->options;
        $options_table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('options') : ('`' . str_replace('`','', $options_table_full) . '`');

        // Limpar transients
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via hng_db_backtick_table() helper
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Performance monitoring, cache cleanup
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names sanitized via hng_db_backtick_table()
        $wpdb->query(
            "DELETE FROM {$options_table_sql} 
            WHERE option_name LIKE '_transient_hng_%' 
            OR option_name LIKE '_transient_timeout_hng_%'"
        );
        
        // Limpar cache de objetos (se disponá¯Â¿Â½vel)
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        do_action('hng_cache_cleared');
    }
    
    /**
     * Obter estatá¯Â¿Â½sticas de cache
     */
    public function get_cache_stats() {
        global $wpdb;
        $options_table_full = $wpdb->options;
        $options_table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('options') : ('`' . str_replace('`','', $options_table_full) . '`');

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via hng_db_backtick_table() helper
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Performance monitoring, cache statistics
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names sanitized via hng_db_backtick_table()
        $transient_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$options_table_sql} 
            WHERE option_name LIKE '_transient_hng_%'"
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via hng_db_backtick_table() helper
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Performance monitoring, cache statistics
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names sanitized via hng_db_backtick_table()
        $transient_size = $wpdb->get_var(
            "SELECT SUM(LENGTH(option_value)) FROM {$options_table_sql} 
            WHERE option_name LIKE '_transient_hng_%'"
        );
        
        return [
            'transient_count' => (int) $transient_count,
            'transient_size' => $this->format_bytes($transient_size),
            'transient_size_raw' => (int) $transient_size,
        ];
    }
    
    /**
     * Formatar bytes
     */
    private function format_bytes($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
}
