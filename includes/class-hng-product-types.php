<?php
/**
 * Registro e normalização de tipos de produto
 *
 * @package HNG_Commerce
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Product_Types {
    /**
     * Ativa o registro dos tipos padrões.
     */
    public static function setup() {
        add_filter('hng_product_types', [__CLASS__, 'register_default_types']);
    }

    /**
     * Registra os tipos de produto suportados.
     *
     * @param array $types Tipos já registrados.
     * @return array
     */
    public static function register_default_types($types) {
        $defaults = [
            'physical' => [
                'label' => __('Produto Físico', 'hng-commerce'),
                'supports' => ['shipping', 'stock'],
            ],
            'digital' => [
                'label' => __('Produto Digital', 'hng-commerce'),
                'supports' => ['download'],
            ],
            'subscription' => [
                'label' => __('Assinatura', 'hng-commerce'),
                'supports' => ['recurrence'],
            ],
            'appointment' => [
                'label' => __('Agendamento', 'hng-commerce'),
                'supports' => ['schedule'],
            ],
            'quote' => [
                'label' => __('Orçamento', 'hng-commerce'),
                'supports' => ['manual_pricing'],
            ],
        ];

        return array_merge($defaults, (array) $types);
    }

    /**
     * Slugs permitidos para tipos de produto.
     *
     * @return string[]
     */
    public static function allowed_slugs() {
        $types = apply_filters('hng_product_types', []);
        return array_keys($types);
    }

    /**
     * Normaliza um tipo recebido para um slug permitido.
     *
     * @param string $type Tipo informado.
     * @return string Tipo normalizado.
     */
    public static function normalize($type) {
        $type = sanitize_key($type ?: 'physical');
        if ($type === 'simple') {
            $type = 'physical';
        }

        $allowed = self::allowed_slugs();
        if (empty($allowed)) {
            $allowed = ['physical', 'digital', 'subscription', 'appointment', 'quote'];
        }

        return in_array($type, $allowed, true) ? $type : 'physical';
    }
}

HNG_Product_Types::setup();
