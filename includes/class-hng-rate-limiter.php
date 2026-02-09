<?php
/**
 * Rate Limiter & Audit Log
 * 
 * Monitora e controla acesso à API.
 * Registra todas as operações críticas para compliance.
 * 
 * @package HNG_Commerce
 */

if (!defined('ABSPATH')) {
    exit;
}

// Prevenir carregamento duplicado da classe
if (class_exists('HNG_Rate_Limiter')) {
    return;
}

class HNG_Rate_Limiter {
    
    const CACHE_GROUP = 'hng_rate_limit';
    
    /**
     * Verificar rate limit
     * 
     * @param string $key Identificador único (IP, user ID, API key)
     * @param int $limit Máximo de requisições
     * @param int $window Janela em segundos
     * @return array { allowed: bool, remaining: int, reset_at: timestamp }
     */
    public static function check($key, $limit = 100, $window = 3600) {
        $cache_key = "rl_{$key}";
        $data = wp_cache_get($cache_key, self::CACHE_GROUP);
        
        if (!$data) {
            $data = [
                'count' => 0,
                'reset_at' => time() + $window,
            ];
        }
        
        // Reset se passou da janela
        if (time() >= $data['reset_at']) {
            $data['count'] = 0;
            $data['reset_at'] = time() + $window;
        }
        
        // Incrementar contador
        $data['count']++;
        wp_cache_set($cache_key, $data, self::CACHE_GROUP, $window);
        
        $allowed = $data['count'] <= $limit;
        $remaining = max(0, $limit - $data['count']);
        
        return [
            'allowed' => $allowed,
            'remaining' => $remaining,
            'reset_at' => $data['reset_at'],
            'count' => $data['count'],
        ];
    }
    
    /**
     * Get rate limit headers para resposta
     * 
     * @param array $check_result Resultado de self::check()
     * @return array Pares de header
     */
    public static function get_headers($check_result) {
        return [
            'X-RateLimit-Limit' => '100',
            'X-RateLimit-Remaining' => (string)$check_result['remaining'],
            'X-RateLimit-Reset' => (string)$check_result['reset_at'],
        ];
    }
    
    /**
     * Rejeitar com 429
     * 
     * @param array $check_result Resultado de self::check()
     * @param string $message Mensagem customizada
     */
    public static function reject($check_result, $message = 'Rate limit exceeded') {
        $headers = self::get_headers($check_result);
        
        header('HTTP/1.1 429 Too Many Requests');
        foreach ($headers as $name => $value) {
            header("$name: $value");
        }
        
        wp_send_json_error([
            'message' => $message,
            'retry_after' => $check_result['reset_at'] - time(),
        ], 429);
    }
}

/**
 * Audit Log
 * 
 * Registra operações críticas para compliance e segurança
 */
class HNG_Audit_Log {
    
    const LOG_DIR = WP_CONTENT_DIR . '/logs/hng-audit/';
    const TABLE_NAME = 'hng_audit_log';
    
    /**
     * Log entry
     * 
     * @param string $event Nome do evento
     * @param string $category 'payment', 'fee', 'auth', 'admin', 'security'
     * @param array $data Dados do evento
     * @param int $severity 0=info, 1=warning, 2=critical
     * @return bool
     */
    public static function log($event, $category, $data = [], $severity = 0) {
        global $wpdb;
        
        $log_data = [
            'timestamp' => current_time('mysql', true),
            'event' => $event,
            'category' => $category,
            'severity' => $severity,
            'user_id' => get_current_user_id(),
            'user_ip' => self::get_client_ip(),
            'data' => wp_json_encode($data),
        ];
        
        // Insert na DB
        $result = $wpdb->insert(
            $wpdb->prefix . self::TABLE_NAME,
            $log_data,
            ['%s', '%s', '%s', '%d', '%d', '%s', '%s']
        );
        
        // Também gravar em arquivo para backup offline
        if ($severity >= 1) {
            self::write_file_log($event, $category, $data, $severity);
        }
        
        // Disparar ações customizadas por categoria
        do_action("hng_audit_log_{$category}", $event, $data, $severity);
        
        return (bool)$result;
    }
    
    /**
     * Write file log para crítico/warning
     */
    private static function write_file_log($event, $category, $data, $severity) {
        wp_mkdir_p(self::LOG_DIR);
        
        $severity_label = ['INFO', 'WARNING', 'CRITICAL'][$severity] ?? 'UNKNOWN';
        $filename = self::LOG_DIR . sprintf(
            '%s-%s.log',
            $category,
            gmdate('Y-m-d'),
        );
        
        $line = sprintf(
            "[%s] %s: %s | Data: %s\n",
            gmdate('Y-m-d H:i:s'),
            $severity_label,
            $event,
            wp_json_encode($data)
        );
        
        error_log($line, 3, $filename);
    }
    
    /**
     * Get client IP address
     */
    private static function get_client_ip() {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                return trim($ips[0]);
            }
        }
        return '0.0.0.0';
    }
    
    /**
     * Query logs
     * 
     * @param array $filters [ 'category', 'event', 'severity', 'user_id', 'after', 'before' ]
     * @param int $limit
     * @return array
     */
    public static function query($filters = [], $limit = 100) {
        global $wpdb;
        
        $where = [];
        $values = [];
        
        if (!empty($filters['category'])) {
            $where[] = 'category = %s';
            $values[] = $filters['category'];
        }
        
        if (!empty($filters['event'])) {
            $where[] = 'event = %s';
            $values[] = $filters['event'];
        }
        
        if (isset($filters['severity'])) {
            $where[] = 'severity = %d';
            $values[] = $filters['severity'];
        }
        
        if (!empty($filters['after'])) {
            $where[] = 'timestamp > %s';
            $values[] = $filters['after'];
        }
        
        if (!empty($filters['before'])) {
            $where[] = 'timestamp < %s';
            $values[] = $filters['before'];
        }
        
        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name usa $wpdb->prefix e WHERE clause é sanitizado via prepare()
        $query = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}" . self::TABLE_NAME . " $where_clause ORDER BY id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            array_merge($values, [$limit])
        );
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query já preparada acima
    }
    
    /**
     * Get estatísticas
     * 
     * @param array $filters
     * @return array
     */
    public static function get_stats($filters = []) {
        global $wpdb;
        
        $results = self::query($filters, 1000);
        
        $stats = [
            'total' => count($results),
            'by_category' => [],
            'by_severity' => [0 => 0, 1 => 0, 2 => 0],
            'critical_events' => [],
        ];
        
        foreach ($results as $row) {
            // Count by category
            if (!isset($stats['by_category'][$row->category])) {
                $stats['by_category'][$row->category] = 0;
            }
            $stats['by_category'][$row->category]++;
            
            // Count by severity
            $stats['by_severity'][$row->severity]++;
            
            // Collect critical
            if ($row->severity >= 2) {
                $stats['critical_events'][] = [
                    'timestamp' => $row->timestamp,
                    'event' => $row->event,
                    'data' => json_decode($row->data, true),
                ];
            }
        }
        
        return $stats;
    }
    
    /**
     * Criar tabela na instalação
     */
    public static function create_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            timestamp DATETIME NOT NULL,
            event VARCHAR(128) NOT NULL,
            category VARCHAR(32) NOT NULL,
            severity TINYINT DEFAULT 0,
            user_id BIGINT DEFAULT 0,
            user_ip VARCHAR(45),
            data LONGTEXT,
            INDEX idx_timestamp (timestamp),
            INDEX idx_category (category),
            INDEX idx_severity (severity),
            INDEX idx_event (event)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}

// Hook para criar tabela na ativação do plugin
register_activation_hook(__FILE__, [HNG_Audit_Log::class, 'create_table']);
