<?php
/**
 * HNG Commerce - Compatibilidade (versão única e limpa)
 * Arquivo reescrito para corrigir problemas de sintaxe e manter proteções básicas.
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Commerce_Compatibility {
    public static function init() {
        add_filter('elementor/document/config', array(__CLASS__, 'fix_elementor_null_post'), 10, 2);
        add_filter('the_post', array(__CLASS__, 'validate_post_object'), 10, 1);
        add_action('pre_get_posts', array(__CLASS__, 'fix_admin_queries'), 10);
        add_filter('map_meta_cap', array(__CLASS__, 'fix_map_meta_cap'), 10, 4);
    }

    public static function fix_elementor_null_post($config, $post_id) {
        if (!$post_id) {
            return $config;
        }
        $post = get_post($post_id);
        if (!$post || !isset($post->post_status)) {
            return array();
        }
        return $config;
    }

    public static function validate_post_object($post) {
        if (!$post) {
            return null;
        }
        if (!isset($post->post_status)) {
            $post->post_status = 'publish';
        }
        if (!isset($post->post_type)) {
            $post->post_type = 'post';
        }
        if (!isset($post->post_title)) {
            $post->post_title = '';
        }
        return $post;
    }

    public static function fix_admin_queries($query) {
        if (!is_admin() || !is_object($query)) {
            return;
        }
        if (!$query->get('post_type')) {
            if ($query->get('p') || $query->get('post__in')) {
                return;
            }
        }
        if (!$query->get('post_status')) {
            $query->set('post_status', array('publish', 'draft', 'pending', 'private', 'future'));
        }
    }

    public static function fix_map_meta_cap($caps, $cap, $user_id, $args) {
        $post_caps = array('edit_post', 'read_post', 'delete_post', 'edit_posts', 'publish_posts');
        if (!in_array($cap, $post_caps, true)) {
            return $caps;
        }
        if (empty($args) || !isset($args[0])) {
            if (in_array($cap, array('edit_post', 'read_post'), true)) {
                return array('edit_posts');
            }
        }
        return $caps;
    }

    public static function suppress_notices() {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY) {
            return;
        }
        $suppressed = array('Undefined index', 'Undefined offset', 'map_meta_cap was called incorrectly');
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Custom error handler for compatibility layer to suppress legacy notices
        set_error_handler(function($errno, $errstr) use ($suppressed) {
            foreach ($suppressed as $pattern) {
                if (stripos($errstr, $pattern) !== false) {
                    return true;
                }
            }
            return false;
        }, E_NOTICE | E_USER_NOTICE);
    }
}

add_action('plugins_loaded', array('HNG_Commerce_Compatibility', 'init'), 1);
