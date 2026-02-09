<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Exibe campos personalizados do cliente nos itens do pedido (admin e cliente)
 * Inclua este template onde desejar mostrar os campos customizados do item
 * Uso: include 'templates/partials/order-item-custom-fields.php';
 * Espera: $item (array do item do pedido)
 */

if (!defined('ABSPATH')) {
    exit;
}
if (!isset($item) || !is_array($item)) return;

$custom_fields = array_filter($item, function($v, $k) {
    return strpos($k, 'cf_') === 0;
}, ARRAY_FILTER_USE_BOTH);

if (!empty($custom_fields)) {
    echo '<ul class="hng-order-item-custom-fields">';
    foreach ($custom_fields as $slug => $value) {
        $label = ucwords(str_replace(['cf_', '_'], ['', ' '], $slug));
        echo '<li><strong>' . esc_html($label) . ':</strong> ' . esc_html($value) . '</li>';
    }
    echo '</ul>';
}
