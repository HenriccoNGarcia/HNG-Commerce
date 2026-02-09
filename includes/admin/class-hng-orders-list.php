<?php

/**

 * Lista de Pedidos - Admin

 *

 * @package HNG_Commerce

 * @since 1.0.0

 */



if (!defined('ABSPATH')) {

    exit;

}



// DB helper

require_once HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php';



if (!class_exists('WP_List_Table')) {

    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

}



class HNG_Orders_List extends WP_List_Table {

    

    /**

     * Construtor

     */

    public function __construct() {

        parent::__construct([

            'singular' => 'pedido',

            'plural'   => 'pedidos',

            'ajax'     => false

        ]);

    }

    

    /**

     * Colunas da tabela

     */

    public function get_columns() {

        return [

            'cb'              => '<input type="checkbox" />',

            'order_number'    => __('Pedido', 'hng-commerce'),

            'customer'        => __('Cliente', 'hng-commerce'),

            'status'          => __('Status', 'hng-commerce'),

            'total'           => __('Total', 'hng-commerce'),

            'payment_method'  => __('Pagamento', 'hng-commerce'),

            'date'            => __('Data', 'hng-commerce'),

            'actions'         => __('Ações', 'hng-commerce')

        ];

    }

    

    /**

     * Colunas ordenáveis

     */

    public function get_sortable_columns() {

        return [

            'order_number' => ['order_number', false],

            'customer'     => ['billing_first_name', false],

            'status'       => ['status', false],

            'total'        => ['total', true],

            'date'         => ['created_at', true]

        ];

    }

    

    /**

     * Bulk actions

     */

    public function get_bulk_actions() {

        return [

            'mark_processing' => __('Marcar como Processando', 'hng-commerce'),

            'mark_completed'  => __('Marcar como Concluído', 'hng-commerce'),

            'mark_cancelled'  => __('Marcar como Cancelado', 'hng-commerce'),

            'export_csv'      => __('Exportar CSV', 'hng-commerce')

        ];

    }

    

    /**

     * Filtros extras (status)

     */

    protected function get_views() {

        global $wpdb;

        

        $orders_table = hng_db_full_table_name('hng_orders');

        $orders_table_sql = '`' . str_replace('`','', $orders_table) . '`';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        $status_counts = $wpdb->get_results(

            "SELECT status, COUNT(*) as count FROM {$orders_table_sql} GROUP BY status",

            OBJECT_K

        );

        

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        $current_status = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : 'all';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        $total_count = $wpdb->get_var("SELECT COUNT(*) FROM {$orders_table_sql}");

        

        $status_labels = [

            'all'            => __('Todos', 'hng-commerce'),

            'hng-pending'    => __('Pendente', 'hng-commerce'),

            'hng-pending-approval' => __('Aguardando Aprovação', 'hng-commerce'),

            'hng-awaiting-payment' => __('Aguardando Pagamento', 'hng-commerce'),

            'hng-processing' => __('Processando', 'hng-commerce'),

            'hng-completed'  => __('Concluído', 'hng-commerce'),

            'hng-cancelled'  => __('Cancelado', 'hng-commerce'),

            'hng-refunded'   => __('Reembolsado', 'hng-commerce'),

        ];

        

        $views = [];

        

        // Link "Todos"

        $class = ($current_status === 'all') ? 'current' : '';

        $views['all'] = sprintf(

            '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',

            esc_url(admin_url('admin.php?page=hng-orders')),

            esc_attr($class),

            esc_html($status_labels['all']),

            (int) $total_count

        );

        

        // Links por status

        foreach ($status_labels as $status => $label) {

            if ($status === 'all') continue;

            

            $count = isset($status_counts[$status]) ? $status_counts[$status]->count : 0;

            if ($count === 0) continue;

            

            $class = ($current_status === $status) ? 'current' : '';

            $views[$status] = sprintf(

                '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',

                esc_url(admin_url('admin.php?page=hng-orders&status=' . $status)),

                esc_attr($class),

                esc_html($label),

                (int) $count

            );

        }

        

        return $views;

    }

    

    /**

     * Filtros extras (data, busca)

     */

    protected function extra_tablenav($which) {

        if ($which !== 'top') return;

        

        ?>

        <div class="alignleft actions">

            <!-- Filtro por data -->

            <select name="date_filter" id="date_filter">

                <option value=""><?php esc_html_e('Todas as datas', 'hng-commerce'); ?></option>

                <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>

                <option value="today" <?php selected(isset($_GET['date_filter']) && $_GET['date_filter'] === 'today'); ?>>

                    <?php esc_html_e('Hoje', 'hng-commerce'); ?>

                </option>

                <option value="yesterday" <?php selected(isset($_GET['date_filter']) && $_GET['date_filter'] === 'yesterday'); ?>>

                    <?php esc_html_e('Ontem', 'hng-commerce'); ?>

                </option>

                <option value="this_week" <?php selected(isset($_GET['date_filter']) && $_GET['date_filter'] === 'this_week'); ?>>

                    <?php esc_html_e('Esta semana', 'hng-commerce'); ?>

                </option>

                <option value="last_week" <?php selected(isset($_GET['date_filter']) && $_GET['date_filter'] === 'last_week'); ?>>

                    <?php esc_html_e('Semana passada', 'hng-commerce'); ?>

                </option>

                <option value="this_month" <?php selected(isset($_GET['date_filter']) && $_GET['date_filter'] === 'this_month'); ?>>

                    <?php esc_html_e('Este mês', 'hng-commerce'); ?>

                </option>

                <option value="last_month" <?php selected(isset($_GET['date_filter']) && $_GET['date_filter'] === 'last_month'); ?>>

                    <?php esc_html_e('Mês passado', 'hng-commerce'); ?>

                </option>

            </select>

            

            <!-- Filtro por método de pagamento -->

            <select name="payment_method_filter" id="payment_method_filter">

                <option value=""><?php esc_html_e('Todos os pagamentos', 'hng-commerce'); ?></option>

                <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>

                <option value="pix" <?php selected(isset($_GET['payment_method_filter']) && $_GET['payment_method_filter'] === 'pix'); ?>>PIX</option>

                <option value="credit_card" <?php selected(isset($_GET['payment_method_filter']) && $_GET['payment_method_filter'] === 'credit_card'); ?>>

                    <?php esc_html_e('Cartão de Crédito', 'hng-commerce'); ?>

                </option>

                <option value="boleto" <?php selected(isset($_GET['payment_method_filter']) && $_GET['payment_method_filter'] === 'boleto'); ?>>Boleto</option>

            </select>

            

            <input type="submit" class="button" value="<?php esc_html_e('Filtrar', 'hng-commerce'); ?>">

        </div>

        <?php

    }

    

    /**

     * Checkbox para bulk actions

     */

    public function column_cb($item) {

        return sprintf('<input type="checkbox" name="order_ids[]" value="%d" />', $item->id);

    }

    

    /**

     * Coluna: Número do pedido

     */

    public function column_order_number($item) {

        $edit_url = admin_url('admin.php?page=hng-orders&action=view&order_id=' . $item->id);

        

        $actions = [

            'view' => sprintf(

                '<a href="%s">%s</a>',

                esc_url($edit_url),

                esc_html__('Ver detalhes', 'hng-commerce')

            ),

            'email' => sprintf(

                '<a href="#" data-order-id="%d" class="hng-resend-email">%s</a>',

                (int) $item->id,

                esc_html__('Reenviar email', 'hng-commerce')

            )

        ];

        

        return sprintf(

            '<strong><a href="%s">#%s</a></strong> %s',

            esc_url($edit_url),

            esc_html($item->order_number),

            $this->row_actions($actions)

        );

    }

    

    /**

     * Coluna: Cliente

     */

    public function column_customer($item) {

        // Usar campos de billing se disponíveis, fallback para campos antigos

        $customer_name = trim(($item->billing_first_name ?? '') . ' ' . ($item->billing_last_name ?? ''));

        if (empty($customer_name)) {

            $customer_name = $item->customer_name ?? __('N/A', 'hng-commerce');

        }

        $customer_email = $item->billing_email ?? ($item->customer_email ?? '');

        

        $output = '<strong>' . esc_html($customer_name) . '</strong><br>';

        $output .= '<small>' . esc_html($customer_email) . '</small>';

        return $output;

    }

    

    /**

     * Coluna: Status

     */

    public function column_status($item) {

        $status_colors = [

            'hng-pending'    => '#f0ad4e',

            'hng-processing' => '#5bc0de',

            'hng-completed'  => '#5cb85c',

            'hng-cancelled'  => '#d9534f',

            'hng-refunded'   => '#777'

        ];

        

        $status_labels = [

            'hng-pending'    => __('Pendente', 'hng-commerce'),

            'hng-processing' => __('Processando', 'hng-commerce'),

            'hng-completed'  => __('Concluído', 'hng-commerce'),

            'hng-cancelled'  => __('Cancelado', 'hng-commerce'),

            'hng-refunded'   => __('Reembolsado', 'hng-commerce'),

        ];

        

        $color = isset($status_colors[$item->status]) ? $status_colors[$item->status] : '#999';

        $label = isset($status_labels[$item->status]) ? $status_labels[$item->status] : $item->status;

        

        return sprintf(

            '<span class="hng-order-status" style="display: inline-block; padding: 4px 8px; border-radius: 3px; background: %s; color: white; font-size: 11px; font-weight: bold;">%s</span>',

            esc_attr($color),

            esc_html($label)

        );

    }

    

    /**

     * Coluna: Total

     */

    public function column_total($item) {

        return esc_html(hng_price($item->total));

    }

    

    /**

     * Coluna: Comissão

     */

    public function column_commission($item) {

        return sprintf(

            '%s <small>(%s%%)</small>',

            esc_html(hng_price($item->commission)),

            esc_html(number_format($item->commission_rate, 1, ',', '.'))

        );

    }

    

    /**

     * Coluna: Método de pagamento

     */

    public function column_payment_method($item) {

        $methods = [

            'pix'         => 'PIX',

            'credit_card' => __('Cartão', 'hng-commerce'),

            'boleto'      => 'Boleto'

        ];

        

        return esc_html(isset($methods[$item->payment_method]) ? $methods[$item->payment_method] : $item->payment_method);

    }

    

    /**

     * Coluna: Data

     */

    public function column_date($item) {

        $date = new DateTime($item->created_at);

        return sprintf(

            '%s<br><small>%s</small>',

            esc_html($date->format('d/m/Y')),

            esc_html($date->format('H:i'))

        );

    }

    

    /**

     * Coluna: Ações rápidas

     */

    public function column_actions($item) {

        $actions = [];

        

        // Verificar pagamento para pedidos pendentes com Asaas

        if (in_array($item->status, ['hng-pending', 'hng-awaiting-payment']) && $item->payment_method === 'asaas') {

            $payment_id = get_post_meta($item->post_id, '_asaas_payment_id', true);

            if ($payment_id) {

                $actions[] = sprintf(

                    '<a href="#" class="button button-small hng-check-payment" data-order-id="%d" data-post-id="%d" title="%s">%s</a>',

                    (int) $item->id,

                    (int) $item->post_id,

                    esc_attr__('Consultar status do pagamento na API Asaas', 'hng-commerce'),

                    esc_html__('🔄 Verificar Pagamento', 'hng-commerce')

                );

            }

        }

        

        if ($item->status === 'hng-pending') {

            $actions[] = sprintf(

                '<a href="#" class="button button-small hng-mark-processing" data-order-id="%d">%s</a>',

                (int) $item->id,

                esc_html__('Processar', 'hng-commerce')

            );

        }

        

        if ($item->status === 'hng-processing') {

            $actions[] = sprintf(

                '<a href="#" class="button button-small button-primary hng-mark-completed" data-order-id="%d">%s</a>',

                (int) $item->id,

                esc_html__('Concluir', 'hng-commerce')

            );

        }

        

        return implode(' ', $actions);

    }

    

    /**

     * Preparar items

     */

    public function prepare_items() {

        global $wpdb;

        

        // Colunas

        $columns = $this->get_columns();

        $hidden = [];

        $sortable = $this->get_sortable_columns();

        $this->_column_headers = [$columns, $hidden, $sortable];

        

        // Paginação

        $per_page = 20;

        $current_page = $this->get_pagenum();

        $offset = ($current_page - 1) * $per_page;

        

        // Query base (clauses + params)

        $where_clauses = ['1=1'];

        $where_params = [];



        // Filtro por status

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        if (isset($_GET['status']) && $_GET['status'] !== 'all') {

            $status = sanitize_text_field(wp_unslash($_GET['status']));

            $where_clauses[] = 'status = %s';

            $where_params[] = $status;

        }



        // Filtro por data (clauses are static SQL expressions)

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        if (isset($_GET['date_filter']) && !empty($_GET['date_filter'])) {

            $date_filter = sanitize_text_field(wp_unslash($_GET['date_filter']));



            switch ($date_filter) {

                case 'today':

                    $where_clauses[] = "DATE(created_at) = CURDATE()";

                    break;

                case 'yesterday':

                    $where_clauses[] = "DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";

                    break;

                case 'this_week':

                    $where_clauses[] = "YEARWEEK(created_at) = YEARWEEK(NOW())";

                    break;

                case 'last_week':

                    $where_clauses[] = "YEARWEEK(created_at) = YEARWEEK(NOW()) - 1";

                    break;

                case 'this_month':

                    $where_clauses[] = "YEAR(created_at) = YEAR(NOW()) AND MONTH(created_at) = MONTH(NOW())";

                    break;

                case 'last_month':

                    $where_clauses[] = "YEAR(created_at) = YEAR(NOW()) AND MONTH(created_at) = MONTH(NOW()) - 1";

                    break;

            }

        }



        // Filtro por método de pagamento

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        if (isset($_GET['payment_method_filter']) && !empty($_GET['payment_method_filter'])) {

            $pm = sanitize_text_field(wp_unslash($_GET['payment_method_filter']));

            $where_clauses[] = 'payment_method = %s';

            $where_params[] = $pm;

        }        // Busca

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        if (isset($_GET['s']) && !empty($_GET['s'])) {

            $s = sanitize_text_field(wp_unslash($_GET['s']));

            $like = '%' . $wpdb->esc_like($s) . '%';

            $where_clauses[] = '(order_number LIKE %s OR billing_first_name LIKE %s OR billing_last_name LIKE %s OR billing_email LIKE %s OR CONCAT(billing_first_name, " ", billing_last_name) LIKE %s)';

            $where_params[] = $like;

            $where_params[] = $like;

            $where_params[] = $like;

            $where_params[] = $like;

            $where_params[] = $like;

        }



        $where_sql = implode(' AND ', $where_clauses);

        

        // Ordenação: usar whitelist de colunas e colchetes seguros

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        $requested_orderby = isset($_GET['orderby']) ? sanitize_text_field(wp_unslash($_GET['orderby'])) : 'created_at';

        // Mapear os valores de `orderby` (chaves do WP_List_Table) para as colunas reais

        $allowed_orderby = [

            'order_number' => 'order_number', // header key => db column

            'customer'     => 'billing_first_name',

            'status'       => 'status',

            'total'        => 'total',

            'date'         => 'created_at',

        ];

        $orderby_col = isset($allowed_orderby[$requested_orderby]) ? $allowed_orderby[$requested_orderby] : 'created_at';

        $orderby_sql = hng_db_backtick_column($orderby_col);

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        $order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';

        

        // Total de items

        $orders_table = hng_db_full_table_name('hng_orders');

        $orders_table_sql = hng_db_backtick_table('hng_orders');



        if (!empty($where_params)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Nomes de tabela/coluna sanitizados via hng_db_backtick_*
            $count_sql = "SELECT COUNT(*) FROM {$orders_table_sql} WHERE $where_sql";

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $total_items = $wpdb->get_var( $wpdb->prepare( $count_sql, ...$where_params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Nomes de tabela/coluna sanitizados via hng_db_backtick_*
            $count_sql = "SELECT COUNT(*) FROM {$orders_table_sql} WHERE $where_sql";

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $total_items = $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        }



        // Buscar pedidos (preparar query com parâmetros dinâmicos)
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Nomes de tabela/coluna sanitizados via hng_db_backtick_*
        $query = "SELECT * FROM {$orders_table_sql} WHERE $where_sql ORDER BY {$orderby_sql} {$order} LIMIT %d OFFSET %d";

        $query_params = array_merge($where_params, [$per_page, $offset]);

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $this->items = $wpdb->get_results( $wpdb->prepare( $query, ...$query_params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        

        // Configurar paginação

        $this->set_pagination_args([

            'total_items' => $total_items,

            'per_page'    => $per_page,

            'total_pages' => ceil($total_items / $per_page)

        ]);

        

        // Processar bulk actions

        $this->process_bulk_action();

    }

    

    /**

     * Processar bulk actions

     */

    public function process_bulk_action() {

        // phpcs:ignore WordPress.Security.NonceVerification.Missing

        $post = function_exists('wp_unslash') ? wp_unslash($_POST) : $_POST;



        if (empty($post['order_ids'])) {

            return;

        }



        // Verificar nonce

        $bulk_nonce = isset($post['_wpnonce']) ? sanitize_text_field(wp_unslash($post['_wpnonce'])) : '';

        if (!$bulk_nonce || !wp_verify_nonce($bulk_nonce, 'bulk-pedidos')) {

            wp_die(esc_html__('Ação inválida', 'hng-commerce'));

        }



        $order_ids = array_map('intval', (array) $post['order_ids']);

        $action = $this->current_action();

        

        global $wpdb;

        

        switch ($action) {

            case 'mark_processing':

                foreach ($order_ids as $order_id) {

                    $order = new HNG_Order($order_id);

                    $order->update_status('hng-processing', __('Status alterado via ao em massa', 'hng-commerce'));

                }

                /* translators: %d: number of orders */

                $message = sprintf(esc_html__('%d pedidos marcados como processando', 'hng-commerce'), count($order_ids));

                break;

                

            case 'mark_completed':

                foreach ($order_ids as $order_id) {

                    $order = new HNG_Order($order_id);

                    $order->update_status('hng-completed', __('Status alterado via ao em massa', 'hng-commerce'));

                }

                /* translators: %d: number of orders */

                $message = sprintf(esc_html__('%d pedidos marcados como conclus', 'hng-commerce'), count($order_ids));

                break;

                

            case 'mark_cancelled':

                foreach ($order_ids as $order_id) {

                    $order = new HNG_Order($order_id);

                    $order->update_status('hng-cancelled', __('Status alterado via ao em massa', 'hng-commerce'));

                }

                /* translators: %d: number of orders */

                $message = sprintf(esc_html__('%d pedidos cancelados', 'hng-commerce'), count($order_ids));

                break;

                

            case 'export_csv':

                $this->export_csv($order_ids);

                exit;

        }

        

        if (isset($message)) {

            wp_safe_redirect(add_query_arg('message', urlencode($message), wp_get_referer()));

            exit;

        }

    }

    

    /**

     * Exportar para CSV

     */

    private function export_csv($order_ids) {

        global $wpdb;

        

        if (empty($order_ids)) {

            return;

        }



        $order_ids = array_map('intval', $order_ids);

        $placeholders = implode(',', array_fill(0, count($order_ids), '%d'));

        $orders_table = hng_db_full_table_name('hng_orders');

        $orders_table_sql = '`' . str_replace('`','', $orders_table) . '`';



        // Preparar consulta com placeholders e pará¡Â¢metros explicitamente
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Nomes de tabela sanitizados via hng_db_full_table_name
        $sql = "SELECT * FROM {$orders_table_sql} WHERE id IN ($placeholders)";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $orders = $wpdb->get_results( $wpdb->prepare($sql, ...$order_ids) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        

        header('Content-Type: text/csv; charset=utf-8');

        $filename = 'pedidos-' . gmdate('Y-m-d') . '.csv';

        header('Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"');



        // Clear output buffers to avoid corrupting CSV

        while ( ob_get_level() ) {

            @ob_end_clean();

        }



        $output = @fopen('php://output', 'w');



        // If cannot open output stream (rare on some hosting), fallback to building CSV in memory

        if ($output === false) {

            // Header

            echo "\"Pedido\",\"Cliente\",\"Email\",\"Telefone\",\"CPF\",\"Status\",\"Total\",\"Pagamento\",\"Data\"\n";



            foreach ($orders as $order) {

                $customer_name = trim(($order->billing_first_name ?? '') . ' ' . ($order->billing_last_name ?? ''));

                if (empty($customer_name)) {

                    $customer_name = $order->customer_name ?? '';

                }

                $customer_email = $order->billing_email ?? ($order->customer_email ?? '');

                

                $line = [

                    $order->order_number,

                    $customer_name,

                    $customer_email,

                    $order->billing_phone ?? '',

                    $order->billing_cpf ?? '',

                    $order->status,

                    $order->total,

                    $order->payment_method,

                    $order->created_at

                ];



                // Basic CSV escaping

                $escaped = array_map(function($v) {

                    $v = (string) $v;

                    $v = str_replace('"', '""', $v);

                    return '"' . $v . '"';

                }, $line);



                // Use proper CSV escaping                echo "\"" . implode("\",\"", array_map(function($v) { return str_replace("\"", "\"\"", (string)$v); }, $line)) . "\"\n";
            }



            return;

        }



        // Header

        fputcsv($output, [

            'Pedido', 'Cliente', 'Email', 'Telefone', 'CPF', 'Status', 'Total', 

            'Pagamento', 'Data'

        ]);



        // Linhas

        foreach ($orders as $order) {

            $customer_name = trim(($order->billing_first_name ?? '') . ' ' . ($order->billing_last_name ?? ''));

            if (empty($customer_name)) {

                $customer_name = $order->customer_name ?? '';

            }

            $customer_email = $order->billing_email ?? ($order->customer_email ?? '');

            

            fputcsv($output, [

                $order->order_number,

                $customer_name,

                $customer_email,

                $order->billing_phone ?? '',

                $order->billing_cpf ?? '',

                $order->status,

                $order->total,

                $order->payment_method,

                $order->created_at

            ]);

        }



        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Usando php://output para stream direto

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Usando php://output para stream direto
        fclose($output);

    }

}

