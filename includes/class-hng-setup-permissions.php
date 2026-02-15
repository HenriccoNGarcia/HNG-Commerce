<?php
/**
 * HNG Commerce Setup Permissions
 * 
 * Automatically fixes file permissions after installation/update
 * This runs on plugin activation to ensure all files are readable by WordPress
 * 
 * @package HNG_Commerce
 * @since 1.2.16
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Setup_Permissions {
    
    /**
     * Initialize permission fix on activation
     */
    public static function init() {
        // Hook to activation
        if (!has_action('hng_commerce_activated', [__CLASS__, 'fix_permissions'])) {
            add_action('hng_commerce_activated', [__CLASS__, 'fix_permissions']);
        }
    }
    
    /**
     * Fix file and directory permissions recursively
     * 
     * This method ensures that all plugin files are readable by the web server
     * regardless of how they were uploaded or how the file system is configured
     * 
     * @return array Status array with results
     */
    public static function fix_permissions() {
        $plugin_path = HNG_COMMERCE_PATH;
        $results = [
            'success' => false,
            'dirs_changed' => 0,
            'files_changed' => 0,
            'errors' => []
        ];
        
        // Define standard permissions
        // Directories should be readable/writable
        // Files should be readable
        $dir_permission = 0755;  // rwxr-xr-x
        $file_permission = 0644; // rw-r--r--
        $writable_dir_permission = 0755; // rwxr-xr-x for dirs that need writing
        
        // Directories that need write permissions
        $writable_dirs = [
            'logs' => true,
            'uploads' => true,
            'cache' => true,
            'temp' => true
        ];
        
        try {
            // Recursively process all files and directories
            self::process_permissions_recursive(
                $plugin_path,
                $dir_permission,
                $file_permission,
                $writable_dirs,
                $results
            );
            
            // Mark as completed
            update_option('hng_permissions_fixed', time());
            $results['success'] = true;
            
            return $results;
            
        } catch (Exception $e) {
            $results['errors'][] = 'Exception: ' . $e->getMessage();
            return $results;
        }
    }
    
    /**
     * Recursively process file permissions
     * 
     * @param string $path Directory to process
     * @param int $dir_perm Directory permission mode
     * @param int $file_perm File permission mode
     * @param array $writable_dirs Directories that should be writable
     * @param array &$results Results array to update
     */
    private static function process_permissions_recursive($path, $dir_perm, $file_perm, $writable_dirs, &$results) {
        // Check if path exists
        if (!is_dir($path)) {
            return;
        }
        
        try {
            $dir = scandir($path);
        } catch (Exception $e) {
            $results['errors'][] = "Cannot read: $path";
            return;
        }
        
        // Process each item in directory
        foreach ($dir as $item) {
            // Skip . and .. and hidden files
            if ($item === '.' || $item === '..' || strpos($item, '.') === 0) {
                continue;
            }
            
            $full_path = $path . DIRECTORY_SEPARATOR . $item;
            $relative_path = str_replace(HNG_COMMERCE_PATH, '', $full_path);
            
            // Skip certain directories to save time
            if (self::should_skip($relative_path)) {
                continue;
            }
            
            if (is_dir($full_path)) {
                // Handle directory
                try {
                    // Check if this directory should be writable
                    $should_be_writable = false;
                    foreach ($writable_dirs as $writable_dir => $check) {
                        if ($check && strpos($relative_path, DIRECTORY_SEPARATOR . $writable_dir) !== false) {
                            $should_be_writable = true;
                            break;
                        }
                    }
                    
                    $target_perm = $should_be_writable ? 0755 : $dir_perm;
                    
                    if (@chmod($full_path, $target_perm)) {
                        $results['dirs_changed']++;
                    }
                } catch (Exception $e) {
                    $results['errors'][] = "Cannot chmod dir: {$relative_path}";
                }
                
                // Recursively process subdirectory
                self::process_permissions_recursive($full_path, $dir_perm, $file_perm, $writable_dirs, $results);
                
            } elseif (is_file($full_path)) {
                // Handle file
                try {
                    if (@chmod($full_path, $file_perm)) {
                        $results['files_changed']++;
                    }
                } catch (Exception $e) {
                    $results['errors'][] = "Cannot chmod file: {$relative_path}";
                }
            }
        }
    }
    
    /**
     * Check if path should be skipped to save processing time
     * 
     * @param string $relative_path Relative path from plugin root
     * @return bool True if should skip
     */
    private static function should_skip($relative_path) {
        $skip_patterns = [
            'node_modules',
            'vendor',
            '.git',
            '.svn',
            'composer',
            'build',
        ];
        
        foreach ($skip_patterns as $pattern) {
            if (strpos($relative_path, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if permissions are correct
     * 
     * Used to verify if fix was successful
     * 
     * @return bool True if permissions appear correct
     */
    public static function verify_permissions() {
        $plugin_path = HNG_COMMERCE_PATH;
        
        // Just check the main plugin file is readable
        if (!is_readable($plugin_path . 'hng-commerce.php')) {
            return false;
        }
        
        // Check a few key directories are accessible
        $key_dirs = ['includes', 'gateways', 'templates'];
        foreach ($key_dirs as $dir) {
            $dir_path = $plugin_path . $dir;
            if (is_dir($dir_path) && !is_readable($dir_path)) {
                return false;
            }
        }
        
        return true;
    }
}

// Hook activation event
register_activation_hook(HNG_COMMERCE_FILE, function() {
    HNG_Setup_Permissions::fix_permissions();
    do_action('hng_commerce_activated');
});
