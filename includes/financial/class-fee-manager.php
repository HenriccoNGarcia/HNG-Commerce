<?php
/**
 * HNG Fee Manager
 * 
 * Handles progressive fee logic based on revenue tiers.
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Fee_Manager {
    
    /**
     * Get revenue tiers
     * Format: [max_revenue => fee_percentage]
     */
    public static function get_tiers() {
        return [
            10000 => 2.0,  // Up to R$ 10k: 2.0%
            50000 => 1.5,  // Up to R$ 50k: 1.5%
            PHP_INT_MAX => 1.0 // Above R$ 50k: 1.0%
        ];
    }
    
    /**
     * Get current fee percentage based on last 30 days revenue
     */
    public static function get_current_fee() {
        $revenue = self::calculate_revenue('30days');
        $tiers = self::get_tiers();
        
        foreach ($tiers as $limit => $fee) {
            if ($revenue <= $limit) {
                return $fee;
            }
        }
        
        return 1.0; // Fallback lowest fee
    }
    
    /**
     * Get information about the next tier
     */
    public static function get_next_tier() {
        $revenue = self::calculate_revenue('30days');
        $tiers = self::get_tiers();
        
        $current_fee = 2.0;
        $next_limit = 0;
        $next_fee = 0;
        
        foreach ($tiers as $limit => $fee) {
            if ($revenue <= $limit) {
                $current_fee = $fee;
                $next_limit = $limit;
                
                // Find next fee
                $keys = array_keys($tiers);
                $current_index = array_search($limit, $keys);
                if (isset($keys[$current_index + 1])) {
                    $next_fee = $tiers[$keys[$current_index + 1]];
                } else {
                    // Already at top tier
                    return false;
                }
                
                break;
            }
        }
        
        if ($next_limit > 0) {
            return [
                'current_revenue' => $revenue,
                'target_revenue' => $next_limit,
                'remaining' => $next_limit - $revenue,
                'current_fee' => $current_fee,
                'next_fee' => $next_fee,
                'progress' => min(100, ($revenue / $next_limit) * 100)
            ];
        }
        
        return false;
    }
    
    /**
     * Calculate revenue for a period
     */
    public static function calculate_revenue($period = '30days') {
        global $wpdb;
        
        $date_clause = '';
        $date_params = [];

        switch ($period) {
            case 'today':
                $date_clause = "AND DATE(post_date) = CURDATE()";
                break;
            case '7days':
                $date_clause = "AND post_date >= DATE_SUB(NOW(), INTERVAL %d DAY)";
                $date_params[] = 7;
                break;
            case '30days':
                $date_clause = "AND post_date >= DATE_SUB(NOW(), INTERVAL %d DAY)";
                $date_params[] = 30;
                break;
            case 'month':
                $date_clause = "AND MONTH(post_date) = MONTH(CURRENT_DATE()) AND YEAR(post_date) = YEAR(CURRENT_DATE())";
                break;
        }

        // Calculate completed orders revenue
        $sql = "
            SELECT SUM(m.meta_value)
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} m ON p.ID = m.post_id
            WHERE p.post_type = 'hng_order'
            AND p.post_status = 'completed'
            AND m.meta_key = '_order_total'
            " . $date_clause . "
        ";

        if (!empty($date_params)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $prepared = $wpdb->prepare($sql, ...$date_params);
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            return (float) $wpdb->get_var($prepared);
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return (float) $wpdb->get_var($sql);
    }
}
