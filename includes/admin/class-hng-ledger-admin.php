<?php
if (!defined('ABSPATH')) { exit; }

class HNG_Ledger_Admin_Page {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'menu']);
    }

    public static function menu() {
        add_submenu_page(
            'hng-commerce',
            __('Ledger Financeiro', 'hng-commerce'),
            __('Ledger', 'hng-commerce'),
            'manage_options',
            'hng-ledger',
            [__CLASS__, 'render']
        );
    }

    public static function render() {
        if (!current_user_can('manage_options')) { return; }
        global $wpdb; $table = HNG_Ledger::get_table_name();

        // Filtros simples
        $get = wp_unslash($_GET);
        $type = isset($get['type']) ? sanitize_key($get['type']) : '';
        $order_id = isset($get['order_id']) ? absint($get['order_id']) : 0;
        echo '<div class="wrap"><h1>'.esc_html__('Ledger Financeiro', 'hng-commerce').'</h1>';
        echo '<form method="get" style="margin-bottom:15px;">';
        echo '<input type="hidden" name="post_type" value="hng_product" />';
        echo '<input type="hidden" name="page" value="hng-ledger" />';
        echo '<label>'.esc_html__('Tipo', 'hng-commerce').': <select name="type"><option value="">'.esc_html__('Todos', 'hng-commerce').'</option>';
        foreach (['charge','fee','refund','settlement','adjustment'] as $t) {
            printf('<option value="%s" %s>%s</option>', esc_attr($t), selected($type, $t, false), esc_html(ucfirst($t))); }
        echo '</select></label> ';
        echo '<label>'.esc_html__('Pedido', 'hng-commerce').': <input type="number" name="order_id" value="'.esc_attr($order_id ? intval($order_id) : '').'" /></label> ';
        submit_button(__('Filtrar', 'hng-commerce'), 'secondary', '', false);
        echo '</form>';

        if (!$rows) { echo '<p>'.esc_html__('Nenhum registro encontrado.', 'hng-commerce').'</p></div>'; return; }

        echo '<table class="widefat fixed"><thead><tr>';
        $cols = ['id'=>'ID','type'=>'Tipo','order_id'=>'Pedido','gross_amount'=>'Bruto','fee_amount'=>'Taxa','net_amount'=>'Líquido','status'=>'Status','external_ref'=>'Ref','created_at'=>'Criado'];
        foreach ($cols as $k=>$label) { echo '<th>'.esc_html($label).'</th>'; }
        echo '</tr></thead><tbody>';
        foreach ($rows as $r) {
            echo '<tr>'; 
            echo '<td>'.intval($r['id']).'</td>';
            echo '<td>'.esc_html($r['type']).'</td>';
            echo '<td>'.intval($r['order_id']).'</td>';
            echo '<td>R$ '.number_format($r['gross_amount'],2,',','.').'</td>';
            echo '<td>R$ '.number_format($r['fee_amount'],2,',','.').'</td>';
            echo '<td>R$ '.number_format($r['net_amount'],2,',','.').'</td>';
            echo '<td>'.esc_html($r['status']).'</td>';
            echo '<td>'.esc_html($r['external_ref']).'</td>';
            echo '<td>'.esc_html($r['created_at']).'</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
}

HNG_Ledger_Admin_Page::init();
