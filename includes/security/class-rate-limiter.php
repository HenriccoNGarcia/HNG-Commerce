<?php
/**
 * HNG Rate Limiter
 * Limita requisições repetidas por ação/usuário (ou IP anon) em janela curta.
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Rate_Limiter {
    /**
     * Enforce a limit for a given action key.
     *
     * @param string $action   Identifier of the protected action (e.g., "gateway_test").
     * @param int    $limit    Max requests allowed in the window.
     * @param int    $window   Window size in seconds.
     *
     * @return true|WP_Error
     */
    public static function enforce($action, $limit = 5, $window = 30) {
        $action   = sanitize_key($action ?: 'hng_action');
        $identity = self::get_identity();
        $cache_key = 'hng_rl_' . md5($action . '|' . $identity);

        $entry = get_transient($cache_key);
        $now   = time();

        if (!is_array($entry) || empty($entry['expires']) || $entry['expires'] < $now) {
            $entry = [
                'count'   => 0,
                'expires' => $now + $window,
            ];
        }

        if ($entry['count'] >= $limit) {
            self::log_block($action, $identity);
            return new WP_Error(
                'hng_rate_limited',
                __('Muitas requisições. Tente novamente em instantes.', 'hng-commerce'),
                ['retry_after' => max(1, $entry['expires'] - $now)]
            );
        }

        $entry['count'] += 1;
        set_transient($cache_key, $entry, $window);

        return true;
    }

    /**
     * Obtain current user or IP to rate-limit against.
     */
    private static function get_identity() {
        $user_id = get_current_user_id();
        if ($user_id) {
            return 'user_' . $user_id;
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        return 'ip_' . sanitize_text_field($ip);
    }

    /**
     * Log blocked attempts to hng_security_log when table exists.
     */
    private static function log_block($action, $identity) {
        global $wpdb;
        $table = $wpdb->prefix . 'hng_security_log';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
            return;
        }

        $wpdb->insert(
            $table,
            [
                'event_type' => 'rate_limit_block',
                'context'    => sanitize_text_field($action),
                'user_id'    => get_current_user_id(),
                'ip_address' => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
                'created_at' => current_time('mysql'),
                'metadata'   => wp_json_encode(['identity' => $identity]),
            ],
            ['%s', '%s', '%d', '%s', '%s', '%s']
        );
    }
}
