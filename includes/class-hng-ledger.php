<?php
/**
 * Ledger Interno de Transaá¯Â¿Â½á¯Â¿Â½es
 * Registra eventos financeiros: cobraná¯Â¿Â½a, fee, estorno, liquidaá¯Â¿Â½á¯Â¿Â½o.
 */
if (!defined('ABSPATH')) { exit; }

class HNG_Ledger {
    const TABLE = 'hng_ledger';

    /**
     * Registrar entrada no ledger
     * @param array $data campos: type, order_id, external_ref, gross_amount, fee_amount, net_amount, status, meta (array)
     * @return int|WP_Error ID da linha
     */
    public static function add_entry($data) {
        global $wpdb;
        $table = self::get_table_name();

        $defaults = [
            'type' => '', // charge|fee|refund|settlement|adjustment
            'order_id' => 0,
            'external_ref' => '',
            'gross_amount' => 0.0,
            'fee_amount' => 0.0,
            'net_amount' => 0.0,
            'status' => 'pending', // pending|confirmed|failed|refunded|settled
            'meta' => [],
        ];
        // Permitir passagem com slashes (ex.: vindo de $_POST)
        if (function_exists('wp_unslash')) {
            $data = wp_unslash($data);
        }

        $entry = wp_parse_args($data, $defaults);

        // Sanitizaá§á¡o bá¡Â¡sica
        $entry['type'] = sanitize_key($entry['type']);
        $entry['external_ref'] = sanitize_text_field($entry['external_ref']);
        $entry['status'] = sanitize_key($entry['status']);
        $entry['gross_amount'] = floatval($entry['gross_amount']);
        $entry['fee_amount'] = floatval($entry['fee_amount']);
        $entry['net_amount'] = floatval($entry['net_amount']);

        // Sanitize meta recursively to avoid unsafe payloads
        $meta_array = is_array($entry['meta']) ? $entry['meta'] : [];
        $meta_array = self::sanitize_meta_array($meta_array);
        $meta_json = wp_json_encode($meta_array);

        $inserted = $wpdb->insert(
            $table,
            [
                'type' => $entry['type'],
                'order_id' => intval($entry['order_id']),
                'external_ref' => $entry['external_ref'],
                'gross_amount' => $entry['gross_amount'],
                'fee_amount' => $entry['fee_amount'],
                'net_amount' => $entry['net_amount'],
                'status' => $entry['status'],
                'meta' => $meta_json,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            [ '%s','%d','%s','%f','%f','%f','%s','%s','%s','%s' ]
        );

        if (!$inserted) {
            return new WP_Error('ledger_insert_failed', __('Falha ao registrar transaá¯Â¿Â½á¯Â¿Â½o no ledger.', 'hng-commerce'));
        }
        return $wpdb->insert_id;
    }

    /**
     * Registrar estorno (refund) simplificado
     */
    public static function add_refund($order_id, $external_ref, $amount, $meta = []) {
        return self::add_entry([
            'type' => 'refund',
            'order_id' => $order_id,
            'external_ref' => $external_ref,
            'gross_amount' => $amount,
            'fee_amount' => 0,
            'net_amount' => -abs(floatval($amount)),
            'status' => 'confirmed',
            'meta' => $meta
        ]);
    }

    /** Atualizar status */
    public static function update_status($id, $status) {
        global $wpdb; $table = self::get_table_name();
        if (function_exists('wp_unslash')) {
            $status = wp_unslash($status);
        }

        $wpdb->update(
            $table,
            [ 'status' => sanitize_key($status), 'updated_at' => current_time('mysql') ],
            [ 'id' => intval($id) ],
            [ '%s','%s' ],
            [ '%d' ]
        );
    }

    /** Obter entradas por pedido */
    public static function get_by_order($order_id) {
        global $wpdb; $table = self::get_table_name();
        $order_id = intval($order_id);
            $table_full = $table;
            $table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_ledger') : ('`' . str_replace('`','', $table_full) . '`');
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_sql sanitized via hng_db_backtick_table()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for financial ledger, transaction records
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table_sql sanitized via hng_db_backtick_table()
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table_sql} WHERE order_id = %d ORDER BY id ASC", $order_id), ARRAY_A);

        foreach ($rows as &$r) {
            $r_meta = json_decode($r['meta'], true);
            $r['meta'] = is_array($r_meta) ? $r_meta : [];
        }

        return $rows;
    }

    /**
     * Sanitiza recursivamente o array de meta antes de guardar
     */
    private static function sanitize_meta_array($meta) {
        if (!is_array($meta)) {
            return [];
        }

        $clean = [];
        foreach ($meta as $k => $v) {
            $key = sanitize_text_field((string) $k);
            if (is_array($v)) {
                $clean[$key] = self::sanitize_meta_array($v);
            } else {
                $clean[$key] = is_scalar($v) ? sanitize_text_field((string) $v) : '';
            }
        }
        return $clean;
    }

    /** Nome completo da tabela */
    public static function get_table_name() {
        if (function_exists('hng_db_full_table_name')) {
            $tbl = hng_db_full_table_name(self::TABLE);
            if (!empty($tbl)) {
                return $tbl;
            }
        }
        global $wpdb;
        return $wpdb->prefix . preg_replace('/[^a-z0-9_]/i', '', self::TABLE);
    }
}

/**
 * Criaá¯Â¿Â½á¯Â¿Â½o da tabela ledger (chamar em rotina de instalaá¯Â¿Â½á¯Â¿Â½o/atualizaá¯Â¿Â½á¯Â¿Â½o)
 */
function hng_ledger_create_table() {
    global $wpdb; $table = HNG_Ledger::get_table_name();
    $charset = $wpdb->get_charset_collate();
    $table_sql = '`' . str_replace('`','', $table) . '`';
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via HNG_Ledger::get_table_name() and backtick escaping, dbDelta requires literal SQL
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Database schema installation
    // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names sanitized via HNG_Ledger::get_table_name()
    $sql = "CREATE TABLE {$table_sql} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        type VARCHAR(32) NOT NULL,
        order_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        external_ref VARCHAR(64) DEFAULT '',
        gross_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        fee_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        net_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        status VARCHAR(32) NOT NULL DEFAULT 'pending',
        meta LONGTEXT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY order_idx (order_id),
        KEY status_idx (status),
        KEY type_idx (type)
    ) {$charset};";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
