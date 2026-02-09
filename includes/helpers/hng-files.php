<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helpers seguros para operaçÁµes de arquivo usados pelo plugin.
 * Usa WP_Filesystem quando disponáÂ­vel, com fallback para funçÁµes nativas
 * com bloqueio (LOCK_EX) e checagens báÂ¡sicas.
 */

if (!function_exists('hng_fs_init')) {
    function hng_fs_init() {
        static $inited = null;
        if ($inited !== null) return $inited;
        $inited = false;

        global $wp_filesystem;

        // If already initialized by other code, reuse it
        if (isset($wp_filesystem) && is_object($wp_filesystem)) {
            $inited = true;
            return $inited;
        }

        // Attempt to load WP_Filesystem if we are running inside WP
        if (defined('ABSPATH') && file_exists(ABSPATH . 'wp-admin/includes/file.php')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            if (function_exists('WP_Filesystem')) {
                // This will populate global $wp_filesystem on success
                $inited = WP_Filesystem();
            }
        }

        return $inited;
    }
}

if (!function_exists('hng_files_get_contents')) {
    function hng_files_get_contents($path, $max_len = null) {
        $path = (string) $path;

        if (hng_fs_init()) {
            global $wp_filesystem;
            if (isset($wp_filesystem) && is_object($wp_filesystem) && method_exists($wp_filesystem, 'get_contents')) {
                return $wp_filesystem->get_contents($path);
            }
        }

        if (!is_readable($path)) {
            return false;
        }

        // If explicit max length provided, use the offset/length version to avoid huge reads
        if ($max_len !== null && is_int($max_len) && $max_len > 0) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Fallback when WP_Filesystem unavailable
            return @file_get_contents($path, false, null, 0, $max_len);
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Fallback when WP_Filesystem unavailable
        return @file_get_contents($path);
    }
}

if (!function_exists('hng_files_put_contents')) {
    function hng_files_put_contents($path, $data, $lock = LOCK_EX) {
        $path = (string) $path;

        if (hng_fs_init()) {
            global $wp_filesystem;
            if (isset($wp_filesystem) && is_object($wp_filesystem) && method_exists($wp_filesystem, 'put_contents')) {
                // try to create directories if needed
                $dir = dirname($path);
                if (! $wp_filesystem->is_dir($dir)) {
                    $wp_filesystem->mkdir($dir, FS_CHMOD_DIR);
                }
                return $wp_filesystem->put_contents($path, $data, FS_CHMOD_FILE);
            }
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            if (hng_fs_init()) {
                global $wp_filesystem;
                if (isset($wp_filesystem) && is_object($wp_filesystem) && method_exists($wp_filesystem, 'is_dir')) {
                    if (! $wp_filesystem->is_dir($dir)) {
                        $wp_filesystem->mkdir($dir, FS_CHMOD_DIR);
                    }
                }
            } elseif (function_exists('wp_mkdir_p')) {
                wp_mkdir_p($dir);
            } else {
                // No safe filesystem helper available; avoid direct mkdir in this environment
                return false;
            }
        }

        // Use native file_put_contents with lock as a fallback
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Fallback when WP_Filesystem unavailable
        return @file_put_contents($path, $data, $lock);
    }
}

if (!function_exists('hng_files_append')) {
    function hng_files_append($path, $data) {
        return hng_files_put_contents($path, $data, FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('hng_files_exists')) {
    /**
     * Check if a path exists using WP_Filesystem when available or native fallback.
     */
    function hng_files_exists($path) {
        $path = (string) $path;
        if (hng_fs_init()) {
            global $wp_filesystem;
            if (isset($wp_filesystem) && is_object($wp_filesystem)) {
                if (method_exists($wp_filesystem, 'exists')) {
                    return (bool) $wp_filesystem->exists($path);
                }
                if (method_exists($wp_filesystem, 'is_file')) {
                    return (bool) $wp_filesystem->is_file($path);
                }
            }
        }

        return is_file($path);
    }
}

if (!function_exists('hng_files_log_put_contents')) {
    /**
     * Escreve arquivo apenas quando o modo de debug ou a opçáo de log estiverem habilitados.
     * Use isto para gravaçáo de logs/transaçÁµes que náo devem ocorrer em produçáo por padráo.
     */
    function hng_files_log_put_contents($path, $data, $lock = LOCK_EX) {
        // Respeitar WP_DEBUG ou opçáo de plugin
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return hng_files_put_contents($path, $data, $lock);
        }

        if (function_exists('get_option') && (bool) get_option('hng_transaction_log', false)) {
            return hng_files_put_contents($path, $data, $lock);
        }

        return false;
    }
}

if (!function_exists('hng_files_log_append')) {
    /**
     * Append para logs condicionado ao modo debug/opçáo de logs.
     */
    function hng_files_log_append($path, $data) {
        return hng_files_log_put_contents($path, $data, FILE_APPEND | LOCK_EX);
    }
}
