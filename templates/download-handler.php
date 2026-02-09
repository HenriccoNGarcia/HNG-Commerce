<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Download Handler
 * 
 * Processes secure download requests
 * URL: /download/{download_id}
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get download ID from URL
$download_id = get_query_var('hng_download_id');

if ( ! $download_id ) {
    wp_die( esc_html__( 'Download ID não fornecido.', 'hng-commerce') );
}

// Validate download
$download = HNG_Digital_Product::validate_download($download_id);

if ( is_wp_error( $download ) ) {
    wp_die(
        '<h1>' . esc_html__( 'Erro', 'hng-commerce') . '</h1>' .
        '<p>' . esc_html( $download->get_error_message() ) . '</p>',
        esc_html__( 'Download Indisponível', 'hng-commerce'),
        array( 'response' => 403 )
    );
}

// IDOR Protection: Validar ownership do download
if ( is_user_logged_in() && ! current_user_can( 'manage_options' ) ) {
    $current_user = wp_get_current_user();
    $download_email = sanitize_email( $download['customer_email'] ?? '' );
    
    if ( $current_user->user_email !== $download_email ) {
        error_log( sprintf(
            'HNG Security: IDOR attempt - User %s tried to access download for %s',
            $current_user->user_email,
            $download_email
        ));
        
        wp_die(
            '<h1>' . esc_html__( 'Erro', 'hng-commerce') . '</h1>' .
            '<p>' . esc_html__( 'Você não tem permissão para acessar este download.', 'hng-commerce') . '</p>',
            esc_html__( 'Acesso Negado', 'hng-commerce'),
            array( 'response' => 403 )
        );
    }
}

// Increment download count
// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REMOTE_ADDR is server variable logged for analytics
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- HTTP_USER_AGENT logged for analytics
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
HNG_Digital_Product::increment_download($download_id, $ip, $user_agent);

// Get file path - PHP 8.1+ null safety
$file_path = isset($download['file_path']) ? (string) $download['file_path'] : '';

if (empty($file_path)) {
    wp_die( esc_html__( 'Arquivo inválido.', 'hng-commerce') );
}

// Check if file is a URL or local path
if ( filter_var( $file_path, FILTER_VALIDATE_URL ) ) {
    // External file - redirect (sanitize URL)
    wp_safe_redirect( esc_url_raw( $file_path ) );
    exit;
} else {
    // Local file - serve securely
    $upload_dir = wp_upload_dir();
    $full_path = wp_normalize_path( $upload_dir['basedir'] . '/' . ltrim( $file_path, '/' ) );
    $real_full = realpath( $full_path );
    $uploads_basedir_real = realpath( $upload_dir['basedir'] );

    if ( ! $real_full || strpos( $real_full, $uploads_basedir_real ) !== 0 ) {
        wp_die( esc_html__( 'Arquivo inválido.', 'hng-commerce') );
    }

    if ( ! file_exists( $real_full ) ) {
        wp_die( esc_html__( 'Arquivo não encontrado.', 'hng-commerce') );
    }
    
    // Set headers for download
    header( 'Content-Description: File Transfer' );
    $filetype = wp_check_filetype( $real_full );
    $mime = ! empty( $filetype['type'] ) ? $filetype['type'] : 'application/octet-stream';
    header( 'Content-Type: ' . $mime );
    header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( basename( $real_full ) ) . '"' );
    header( 'Content-Transfer-Encoding: binary' );
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . (int) filesize( $real_full ) );

    // Clear all output buffers safely
    while ( ob_get_level() ) {
        @ob_end_clean();
    }
    flush();

    // Prefer the plugin filesystem helper which uses WP_Filesystem when available.
    // For large files, stream via SplFileObject to avoid high memory usage.
    $max_in_memory = 10 * 1024 * 1024; // 10 MB

    if ( ! function_exists( 'hng_files_get_contents' ) ) {
        require_once HNG_COMMERCE_PATH . 'includes/helpers/hng-files.php';
    }

    $size = filesize( $real_full );

    if ( $size !== false && $size > 0 && $size <= $max_in_memory ) {
        // Small file: safe to read into memory and echo (uses WP_Filesystem when available)
        $content = hng_files_get_contents( $real_full );
        if ( $content !== false ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary file download, not HTML output
            echo $content;
            exit;
        }
        // If helper failed, fall through to streaming fallback
    }

    // Stream file as fallback (SplFileObject) to avoid loading entire file to memory
    try {
        $file = new SplFileObject( $real_full, 'rb' );
        // Seek to start just in case
        $file->fseek(0);
        $file->fpassthru();
        exit;
    } catch ( Exception $e ) {
        $msg = 'Download handler read error: ' . $e->getMessage();
        if (function_exists('hng_files_log_append')) {
            hng_files_log_append(HNG_COMMERCE_PATH . 'logs/download-handler.log', $msg . PHP_EOL);
        }
        wp_die( esc_html__( 'Erro ao ler o arquivo de download.', 'hng-commerce' ) );
    }
    exit;
}
