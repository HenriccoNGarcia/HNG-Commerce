<?php
/**
 * Classe de Sessï¿½o - HNG_Session
 * Gerencia dados de sessï¿½o do usuï¿½rio para o HNG Commerce
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Session {
    protected $session_key = 'hng_session';
    protected $data = [];

    public function __construct() {
        if (!session_id()) {
            session_start();
        }

        // Normalize and sanitize any previously stored session data to avoid tainted globals
        $raw = isset($_SESSION[$this->session_key]) ? wp_unslash($_SESSION[$this->session_key]) : [];
        $this->data = $this->sanitize_value($raw);
    }

    public function get($key, $default = null) {
        return isset($this->data[$key]) ? $this->data[$key] : $default;
    }

    public function set($key, $value) {
        $this->data[$key] = $value;
        $_SESSION[$this->session_key] = $this->data;
    }

    public function all() {
        return $this->data;
    }

    public function destroy() {
        unset($_SESSION[$this->session_key]);
        $this->data = [];
    }

    /**
     * Recursively sanitize session payload to keep only safe scalars/arrays.
     */
    private function sanitize_value($value) {
        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $k => $v) {
                $sanitized_key = is_string($k) ? sanitize_key($k) : $k;
                $sanitized[$sanitized_key] = $this->sanitize_value($v);
            }
            return $sanitized;
        }

        if (is_object($value)) {
            return []; // drop unexpected objects
        }

        if (is_numeric($value)) {
            return $value + 0; // cast to int/float
        }

        return is_string($value) ? sanitize_text_field($value) : $value;
    }
}
