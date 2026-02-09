<?php
/**
 * Classe de Cliente - HNG_Customer
 * Responsável por manipular dados do cliente/usuário no HNG Commerce
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Customer {
    protected $user_id = 0;
    protected $data = [];

    public function __construct($user_id = 0) {
        if ($user_id > 0) {
            $this->user_id = absint($user_id);
            $this->load();
        } elseif (is_user_logged_in()) {
            $this->user_id = get_current_user_id();
            $this->load();
        }
    }

    protected function load() {
        $user = get_userdata($this->user_id);
        if ($user) {
            $this->data = [
                'ID' => $user->ID,
                'user_login' => $user->user_login,
                'user_email' => $user->user_email,
                'display_name' => $user->display_name,
                'first_name' => get_user_meta($user->ID, 'first_name', true),
                'last_name' => get_user_meta($user->ID, 'last_name', true),
                'billing_address' => get_user_meta($user->ID, 'billing_address', true),
                'shipping_address' => get_user_meta($user->ID, 'shipping_address', true),
            ];
        }
    }

    public function get_id() {
        return $this->user_id;
    }

    public function get($key) {
        return isset($this->data[$key]) ? $this->data[$key] : '';
    }

    public function get_data() {
        return $this->data;
    }

    public function update($key, $value) {
        if ($this->user_id > 0) {
            update_user_meta($this->user_id, $key, $value);
            $this->load();
        }
    }
}
