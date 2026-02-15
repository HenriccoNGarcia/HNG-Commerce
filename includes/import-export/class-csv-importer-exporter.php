<?php
/**
 * CSV Exporter
 * 
 * Export products to CSV format
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_CSV_Exporter {
    
    /**
     * Export products to CSV
     */
    public static function export_products($args = []) {
        $defaults = [
            'post_type' => 'hng_product',
            'posts_per_page' => -1,
            'post_status' => 'any',
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $products = get_posts($args);
        
        $csv_data = [];
        
        // Header
        $csv_data[] = [
            'ID',
            'Nome',
            'Descriá¯Â¿Â½á¯Â¿Â½o',
            'Preá¯Â¿Â½o',
            'Preá¯Â¿Â½o Promocional',
            'SKU',
            'Estoque',
            'Status Estoque',
            'Peso',
            'Comprimento',
            'Largura',
            'Altura',
            'Categorias',
            'Imagem',
            'Status',
        ];
        
        foreach ($products as $post) {
            $product = new HNG_Product($post->ID);
            
            $csv_data[] = [
                $product->get_id(),
                $product->get_name(),
                wp_strip_all_tags($product->get_description()),
                $product->get_price(),
                get_post_meta($product->get_id(), '_sale_price', true),
                get_post_meta($product->get_id(), '_sku', true),
                get_post_meta($product->get_id(), '_stock', true),
                get_post_meta($product->get_id(), '_stock_status', true),
                get_post_meta($product->get_id(), '_weight', true),
                get_post_meta($product->get_id(), '_length', true),
                get_post_meta($product->get_id(), '_width', true),
                get_post_meta($product->get_id(), '_height', true),
                self::get_product_categories($product->get_id()),
                get_the_post_thumbnail_url($product->get_id(), 'full'),
                get_post_status($product->get_id()),
            ];
        }
        
        return self::array_to_csv($csv_data);
    }
    
    /**
     * Get product categories as string
     */
    private static function get_product_categories($product_id) {
        $terms = get_the_terms($product_id, 'hng_product_cat');
        
        if (empty($terms) || is_wp_error($terms)) {
            return '';
        }
        
        $names = array_map(function($term) {
            return $term->name;
        }, $terms);
        
        return implode('|', $names);
    }
    
    /**
     * Convert array to CSV string
     */
    private static function array_to_csv($data) {
        $csv = '';
        $output = new SplTempFileObject();
        
        foreach ($data as $row) {
            $output->fputcsv($row);
        }
        
        $output->rewind();
        while (!$output->eof()) {
            $csv .= $output->fgets();
        }
        
        return $csv;
    }
    
    /**
     * Download CSV file
     */
    public static function download_products_csv() {
        $csv = self::export_products();
        
        $filename = 'hng-products-' . gmdate('Y-m-d-H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV data is sanitized and not HTML
        echo "\xEF\xBB\xBF"; // UTF-8 BOM
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV data is sanitized and not HTML
        echo $csv;
        exit;
    }
}

/**
 * CSV Importer
 */
class HNG_CSV_Importer {
    
    /**
     * Import products from CSV
     */
    public static function import_products($file_path) {
        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', __('Arquivo ná¡o encontrado.', 'hng-commerce'));
        }
        
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        
        $content = $wp_filesystem->get_contents($file_path);
        if (empty($content)) {
             return new WP_Error('empty_file', __('Arquivo vazio ou erro ao ler.', 'hng-commerce'));
        }
        
        $lines = explode("\n", $content);
        $csv = array_map('str_getcsv', $lines);
        
        // Remove empty lines
        $csv = array_filter($csv, function($row) {
            return !empty($row) && !empty($row[0]);
        });
        
        if (empty($csv)) {
            return new WP_Error('empty_file', __('Arquivo vazio.', 'hng-commerce'));
        }
        
        // Remove header
        $header = array_shift($csv);
        
        $imported = [];
        $errors = [];
        $updated = [];
        
        foreach ($csv as $index => $row) {
            $data = array_combine($header, $row);
            
            $result = self::import_single_product($data);
            
            if (is_wp_error($result)) {
                $errors[] = [
                    'row' => $index + 2, // +2 because we removed header and arrays are 0-indexed
                    'data' => $data,
                    'error' => $result->get_error_message(),
                ];
            } elseif (isset($result['updated']) && $result['updated']) {
                $updated[] = $result['product_id'];
            } else {
                $imported[] = $result['product_id'];
            }
        }
        
        return [
            'imported' => $imported,
            'updated' => $updated,
            'errors' => $errors,
            'total' => count($csv),
            'success' => count($imported) + count($updated),
            'failed' => count($errors),
        ];
    }
    
    /**
     * Import single product from CSV row
     */
    private static function import_single_product($data) {
        $product_id = absint($data['ID'] ?? 0);
        $is_update = false;
        
        // Check if product exists
        if ($product_id && get_post($product_id)) {
            $is_update = true;
        } else {
            $product_id = 0;
        }
        
        // Prepare product data
        $product_data = [
            'ID' => $product_id,
            'post_title' => sanitize_text_field($data['Nome'] ?? ''),
            'post_content' => wp_kses_post($data['Descriá¯Â¿Â½á¯Â¿Â½o'] ?? ''),
            'post_status' => sanitize_text_field($data['Status'] ?? 'publish'),
            'post_type' => 'hng_product',
        ];
        
        if (empty($product_data['post_title'])) {
            return new WP_Error('no_title', __('Nome do produto á¯Â¿Â½ obrigatá¯Â¿Â½rio.', 'hng-commerce'));
        }
        
        // Insert or update
        if ($is_update) {
            $result = wp_update_post($product_data);
        } else {
            $result = wp_insert_post($product_data);
        }
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        $product_id = $result;
        
        // Update meta
        if (isset($data['Preá¯Â¿Â½o'])) {
            update_post_meta($product_id, '_price', floatval($data['Preá¯Â¿Â½o']));
        }
        
        if (isset($data['Preá¯Â¿Â½o Promocional']) && !empty($data['Preá¯Â¿Â½o Promocional'])) {
            update_post_meta($product_id, '_sale_price', floatval($data['Preá¯Â¿Â½o Promocional']));
        }
        
        if (isset($data['SKU'])) {
            update_post_meta($product_id, '_sku', sanitize_text_field($data['SKU']));
        }
        
        if (isset($data['Estoque'])) {
            update_post_meta($product_id, '_stock', absint($data['Estoque']));
        }
        
        if (isset($data['Status Estoque'])) {
            update_post_meta($product_id, '_stock_status', sanitize_text_field($data['Status Estoque']));
        }
        
        if (isset($data['Peso'])) {
            update_post_meta($product_id, '_weight', floatval($data['Peso']));
        }
        
        if (isset($data['Comprimento'])) {
            update_post_meta($product_id, '_length', floatval($data['Comprimento']));
        }
        
        if (isset($data['Largura'])) {
            update_post_meta($product_id, '_width', floatval($data['Largura']));
        }
        
        if (isset($data['Altura'])) {
            update_post_meta($product_id, '_height', floatval($data['Altura']));
        }
        
        // Import categories
        if (!empty($data['Categorias'])) {
            $categories = explode('|', $data['Categorias']);
            $category_ids = [];
            
            foreach ($categories as $cat_name) {
                $cat_name = trim($cat_name);
                $term = get_term_by('name', $cat_name, 'hng_product_cat');
                
                if (!$term) {
                    $term = wp_insert_term($cat_name, 'hng_product_cat');
                    if (!is_wp_error($term)) {
                        $category_ids[] = $term['term_id'];
                    }
                } else {
                    $category_ids[] = $term->term_id;
                }
            }
            
            if (!empty($category_ids)) {
                wp_set_object_terms($product_id, $category_ids, 'hng_product_cat');
            }
        }
        
        return [
            'product_id' => $product_id,
            'updated' => $is_update,
        ];
    }
}

/**
 * AJAX Handler for import
 */
add_action('wp_ajax_hng_import_csv', function() {
    check_ajax_referer('hng_import_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Sem permissá¯Â¿Â½o.', 'hng-commerce')]);
    }

    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File upload validated by wp_handle_upload
    if (empty($_FILES['file'])) {
        wp_send_json_error(['message' => __('Nenhum arquivo enviado.', 'hng-commerce')]);
    }

    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File data sanitized below with sanitize_file_name
    $file = $_FILES['file'];
    // sanitize original filename for logging or later use
    if (!empty($file['name'])) {
        $file['name'] = sanitize_file_name($file['name']);
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        wp_send_json_error(['message' => __('Erro no upload.', 'hng-commerce')]);
    }
    
    $result = HNG_CSV_Importer::import_products($file['tmp_name']);
    
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }
    
    wp_send_json_success($result);
});

add_action('wp_ajax_hng_download_csv_template', ['HNG_CSV_Exporter', 'download_products_csv']);
