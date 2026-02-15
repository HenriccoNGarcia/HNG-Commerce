<?php
/**
 * HNG Leads Admin Page
 * 
 * Lists registered users/leads with their profile data
 * 
 * @package HNG_Commerce
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Leads_Admin {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', [$this, 'add_menu'], 20);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_styles']);
        add_action('wp_ajax_hng_export_leads_csv', [$this, 'export_csv']);
    }
    
    public function add_menu() {
        add_submenu_page(
            'hng-commerce',
            __('Leads & Clientes', 'hng-commerce'),
            __('📋 Leads', 'hng-commerce'),
            'manage_options',
            'hng-leads',
            [$this, 'render_page']
        );
    }
    
    public function enqueue_styles($hook) {
        if (strpos($hook, 'hng-leads') === false) {
            return;
        }
        
        wp_add_inline_style('wp-admin', '
            .hng-leads-wrap { max-width: 1400px; margin: 20px auto; }
            .hng-leads-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
            .hng-leads-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
            .hng-stat-card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
            .hng-stat-card h3 { margin: 0 0 5px; color: #666; font-size: 13px; text-transform: uppercase; }
            .hng-stat-card .number { font-size: 32px; font-weight: 700; color: #1d2327; }
            .hng-stat-card.providers .number { color: #a3e635; }
            .hng-stat-card.companies .number { color: #3b82f6; }
            .hng-leads-table { width: 100%; background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); overflow: hidden; }
            .hng-leads-table th { background: #f8f9fa; padding: 15px; text-align: left; font-weight: 600; border-bottom: 2px solid #e5e7eb; }
            .hng-leads-table td { padding: 15px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
            .hng-leads-table tr:hover { background: #f8fafc; }
            .hng-lead-name { font-weight: 600; color: #1d2327; display: block; }
            .hng-lead-email { color: #6b7280; font-size: 13px; }
            .hng-lead-phone { color: #059669; }
            .hng-badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
            .hng-badge-provider { background: #ecfccb; color: #4d7c0f; }
            .hng-badge-company { background: #dbeafe; color: #1d4ed8; }
            .hng-lead-services { max-width: 300px; }
            .hng-lead-services span { display: inline-block; background: #f3f4f6; padding: 2px 8px; border-radius: 4px; margin: 2px; font-size: 12px; }
            .hng-filters { display: flex; gap: 15px; margin-bottom: 20px; align-items: center; flex-wrap: wrap; }
            .hng-filters select, .hng-filters input { padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; }
            .hng-btn { display: inline-flex; align-items: center; gap: 5px; padding: 10px 20px; border-radius: 8px; font-weight: 600; text-decoration: none; cursor: pointer; border: none; }
            .hng-btn-primary { background: #3b82f6; color: #fff; }
            .hng-btn-primary:hover { background: #2563eb; color: #fff; }
            .hng-btn-success { background: #10b981; color: #fff; }
            .hng-btn-success:hover { background: #059669; color: #fff; }
            .hng-lead-detail { background: #f8fafc; padding: 20px; margin: -15px; margin-top: 10px; border-top: 1px solid #e5e7eb; }
            .hng-lead-detail h4 { margin: 0 0 10px; color: #374151; }
            .hng-lead-detail p { margin: 5px 0; color: #6b7280; }
            .hng-lead-detail strong { color: #1d2327; }
            .hng-social-links a { display: inline-block; margin-right: 10px; color: #3b82f6; }
            .hng-empty { text-align: center; padding: 60px; color: #9ca3af; }
            .hng-empty-icon { font-size: 48px; margin-bottom: 15px; }
            .hng-pagination { display: flex; justify-content: center; gap: 5px; margin-top: 20px; }
            .hng-pagination a, .hng-pagination span { padding: 8px 15px; border-radius: 6px; background: #fff; border: 1px solid #d1d5db; color: #374151; text-decoration: none; }
            .hng-pagination .current { background: #3b82f6; color: #fff; border-color: #3b82f6; }
        ');
    }
    
    public function render_page() {
        // Get filters
        $filter_type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
        $filter_service = isset($_GET['service']) ? sanitize_text_field($_GET['service']) : '';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        
        // Get leads data
        $leads_data = $this->get_leads($filter_type, $filter_service, $search, $paged, $per_page);
        $leads = $leads_data['leads'];
        $total = $leads_data['total'];
        $total_pages = ceil($total / $per_page);
        
        // Get stats
        $stats = $this->get_stats();
        
        // Get services for filter
        $services = $this->get_available_services();
        
        ?>
        <div class="wrap hng-leads-wrap">
            <div class="hng-leads-header">
                <h1>
                    <span class="dashicons dashicons-businessperson" style="font-size: 28px; margin-right: 10px;"></span>
                    <?php esc_html_e('Leads & Clientes Cadastrados', 'hng-commerce'); ?>
                </h1>
                <div>
                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-ajax.php?action=hng_export_leads_csv'), 'hng_export_leads')); ?>" class="hng-btn hng-btn-success">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e('Exportar CSV', 'hng-commerce'); ?>
                    </a>
                </div>
            </div>
            
            <!-- Stats -->
            <div class="hng-leads-stats">
                <div class="hng-stat-card">
                    <h3><?php esc_html_e('Total de Leads', 'hng-commerce'); ?></h3>
                    <div class="number"><?php echo esc_html($stats['total']); ?></div>
                </div>
                <div class="hng-stat-card providers">
                    <h3><?php esc_html_e('Prestadores', 'hng-commerce'); ?></h3>
                    <div class="number"><?php echo esc_html($stats['providers']); ?></div>
                </div>
                <div class="hng-stat-card companies">
                    <h3><?php esc_html_e('Empresas', 'hng-commerce'); ?></h3>
                    <div class="number"><?php echo esc_html($stats['companies']); ?></div>
                </div>
                <div class="hng-stat-card">
                    <h3><?php esc_html_e('Últimos 7 dias', 'hng-commerce'); ?></h3>
                    <div class="number"><?php echo esc_html($stats['last_7_days']); ?></div>
                </div>
            </div>
            
            <!-- Filters -->
            <form method="get" class="hng-filters">
                <input type="hidden" name="page" value="hng-leads">
                
                <select name="type">
                    <option value=""><?php esc_html_e('Todos os tipos', 'hng-commerce'); ?></option>
                    <option value="provider" <?php selected($filter_type, 'provider'); ?>><?php esc_html_e('Prestadores', 'hng-commerce'); ?></option>
                    <option value="company" <?php selected($filter_type, 'company'); ?>><?php esc_html_e('Empresas', 'hng-commerce'); ?></option>
                </select>
                
                <select name="service">
                    <option value=""><?php esc_html_e('Todos os serviços', 'hng-commerce'); ?></option>
                    <?php foreach ($services as $service) : ?>
                        <option value="<?php echo esc_attr($service); ?>" <?php selected($filter_service, $service); ?>><?php echo esc_html($service); ?></option>
                    <?php endforeach; ?>
                </select>
                
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Buscar por nome, email, telefone...', 'hng-commerce'); ?>" style="min-width: 250px;">
                
                <button type="submit" class="hng-btn hng-btn-primary">
                    <span class="dashicons dashicons-search"></span>
                    <?php esc_html_e('Filtrar', 'hng-commerce'); ?>
                </button>
                
                <?php if ($filter_type || $filter_service || $search) : ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=hng-leads')); ?>" style="color: #6b7280;"><?php esc_html_e('Limpar filtros', 'hng-commerce'); ?></a>
                <?php endif; ?>
            </form>
            
            <!-- Table -->
            <?php if (empty($leads)) : ?>
                <div class="hng-empty">
                    <div class="hng-empty-icon">📭</div>
                    <h2><?php esc_html_e('Nenhum lead encontrado', 'hng-commerce'); ?></h2>
                    <p><?php esc_html_e('Os leads aparecerão aqui quando usuários se cadastrarem no site.', 'hng-commerce'); ?></p>
                </div>
            <?php else : ?>
                <table class="hng-leads-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Cliente', 'hng-commerce'); ?></th>
                            <th><?php esc_html_e('Tipo', 'hng-commerce'); ?></th>
                            <th><?php esc_html_e('Contato', 'hng-commerce'); ?></th>
                            <th><?php esc_html_e('Área/Serviços', 'hng-commerce'); ?></th>
                            <th><?php esc_html_e('Necessidade', 'hng-commerce'); ?></th>
                            <th><?php esc_html_e('Data', 'hng-commerce'); ?></th>
                            <th><?php esc_html_e('Ações', 'hng-commerce'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leads as $lead) : ?>
                            <tr>
                                <td>
                                    <span class="hng-lead-name"><?php echo esc_html($lead['name']); ?></span>
                                    <span class="hng-lead-email"><?php echo esc_html($lead['email']); ?></span>
                                </td>
                                <td>
                                    <?php if ($lead['type'] === 'provider') : ?>
                                        <span class="hng-badge hng-badge-provider">Prestador</span>
                                    <?php else : ?>
                                        <span class="hng-badge hng-badge-company">Empresa</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($lead['phone']) : ?>
                                        <span class="hng-lead-phone">
                                            <a href="https://wa.me/55<?php echo esc_attr(preg_replace('/[^0-9]/', '', $lead['phone'])); ?>" target="_blank" title="WhatsApp">
                                                📱 <?php echo esc_html($lead['phone']); ?>
                                            </a>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="hng-lead-services">
                                    <?php if ($lead['area']) : ?>
                                        <strong><?php echo esc_html($lead['area']); ?></strong><br>
                                    <?php endif; ?>
                                    <?php if ($lead['services']) : ?>
                                        <?php foreach (explode(',', $lead['services']) as $service) : ?>
                                            <span><?php echo esc_html(trim($service)); ?></span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($lead['service_needed']) : ?>
                                        <strong><?php echo esc_html($lead['service_needed']); ?></strong>
                                    <?php else : ?>
                                        <span style="color: #9ca3af;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo esc_html(date_i18n('d/m/Y', strtotime($lead['registered']))); ?>
                                    <br>
                                    <small style="color: #9ca3af;"><?php echo esc_html(human_time_diff(strtotime($lead['registered']), current_time('timestamp'))); ?> atrás</small>
                                </td>
                                <td>
                                    <a href="mailto:<?php echo esc_attr($lead['email']); ?>" class="hng-btn hng-btn-primary" style="padding: 5px 10px; font-size: 12px;">
                                        ✉️ Email
                                    </a>
                                    <?php if ($lead['phone']) : ?>
                                        <a href="https://wa.me/55<?php echo esc_attr(preg_replace('/[^0-9]/', '', $lead['phone'])); ?>?text=<?php echo urlencode('Olá ' . explode(' ', $lead['name'])[0] . '! Vi seu cadastro no site e gostaria de conversar sobre nossos serviços.'); ?>" target="_blank" class="hng-btn hng-btn-success" style="padding: 5px 10px; font-size: 12px;">
                                            💬 WhatsApp
                                        </a>
                                    <?php endif; ?>
                                    
                                    <!-- Expandable details -->
                                    <details style="margin-top: 10px;">
                                        <summary style="cursor: pointer; color: #3b82f6; font-size: 12px;">Ver mais detalhes</summary>
                                        <div class="hng-lead-detail">
                                            <h4><?php echo esc_html($lead['name']); ?></h4>
                                            
                                            <?php if ($lead['cpf_cnpj']) : ?>
                                                <p><strong>CPF/CNPJ:</strong> <?php echo esc_html($lead['cpf_cnpj']); ?></p>
                                            <?php endif; ?>
                                            
                                            <?php if ($lead['address']) : ?>
                                                <p><strong>Endereço:</strong> <?php echo esc_html($lead['address']); ?></p>
                                            <?php endif; ?>
                                            
                                            <?php if ($lead['social_networks']) : ?>
                                                <p><strong>Redes Sociais:</strong></p>
                                                <div class="hng-social-links">
                                                    <?php 
                                                    $socials = json_decode($lead['social_networks'], true) ?: [];
                                                    foreach ($socials as $network => $link) :
                                                        if ($link) :
                                                    ?>
                                                        <a href="<?php echo esc_url($link); ?>" target="_blank"><?php echo esc_html(ucfirst($network)); ?></a>
                                                    <?php 
                                                        endif;
                                                    endforeach; 
                                                    ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($lead['other_service']) : ?>
                                                <p><strong>Outro Serviço:</strong> <?php echo esc_html($lead['other_service']); ?></p>
                                            <?php endif; ?>
                                            
                                            <p><strong>ID do Usuário:</strong> <?php echo esc_html($lead['user_id']); ?></p>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1) : ?>
                    <div class="hng-pagination">
                        <?php
                        $base_url = add_query_arg([
                            'page' => 'hng-leads',
                            'type' => $filter_type,
                            'service' => $filter_service,
                            's' => $search,
                        ], admin_url('admin.php'));
                        
                        for ($i = 1; $i <= $total_pages; $i++) :
                            $url = add_query_arg('paged', $i, $base_url);
                        ?>
                            <?php if ($i === $paged) : ?>
                                <span class="current"><?php echo esc_html($i); ?></span>
                            <?php else : ?>
                                <a href="<?php echo esc_url($url); ?>"><?php echo esc_html($i); ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }
    
    private function get_leads($type = '', $service = '', $search = '', $paged = 1, $per_page = 20) {
        $args = [
            'role__in' => ['subscriber', 'customer', 'hng_customer'],
            'meta_key' => 'user_registered',
            'orderby' => 'registered',
            'order' => 'DESC',
            'number' => $per_page,
            'offset' => ($paged - 1) * $per_page,
        ];
        
        $meta_query = [];
        
        // Filter by type
        if ($type) {
            $meta_query[] = [
                'key' => '_hng_client_type',
                'value' => $type,
                'compare' => '=',
            ];
        }
        
        // Filter by service
        if ($service) {
            $meta_query[] = [
                'relation' => 'OR',
                [
                    'key' => '_hng_services_provided',
                    'value' => $service,
                    'compare' => 'LIKE',
                ],
                [
                    'key' => '_hng_service_needed',
                    'value' => $service,
                    'compare' => 'LIKE',
                ],
            ];
        }
        
        if (!empty($meta_query)) {
            $args['meta_query'] = $meta_query;
        }
        
        // Search
        if ($search) {
            $args['search'] = '*' . $search . '*';
            $args['search_columns'] = ['user_login', 'user_email', 'display_name'];
        }
        
        $query = new WP_User_Query($args);
        $users = $query->get_results();
        
        // Get total for pagination
        $args_count = $args;
        unset($args_count['number'], $args_count['offset']);
        $args_count['count_total'] = true;
        $query_count = new WP_User_Query($args_count);
        $total = $query_count->get_total();
        
        $leads = [];
        foreach ($users as $user) {
            $leads[] = $this->format_lead_data($user);
        }
        
        return [
            'leads' => $leads,
            'total' => $total,
        ];
    }
    
    private function format_lead_data($user) {
        $user_id = $user->ID;
        
        // Build address
        $address_parts = array_filter([
            get_user_meta($user_id, '_hng_customer_address', true),
            get_user_meta($user_id, '_hng_customer_number', true),
            get_user_meta($user_id, '_hng_customer_district', true),
            get_user_meta($user_id, '_hng_customer_city', true),
            get_user_meta($user_id, '_hng_customer_state', true),
        ]);
        
        return [
            'user_id' => $user_id,
            'name' => get_user_meta($user_id, '_hng_customer_name', true) ?: $user->display_name,
            'email' => $user->user_email,
            'phone' => get_user_meta($user_id, '_hng_customer_phone', true),
            'cpf_cnpj' => get_user_meta($user_id, '_hng_customer_cpf', true),
            'type' => get_user_meta($user_id, '_hng_client_type', true) ?: 'company',
            'area' => get_user_meta($user_id, '_hng_area', true),
            'services' => get_user_meta($user_id, '_hng_services_provided', true),
            'service_needed' => get_user_meta($user_id, '_hng_service_needed', true),
            'other_service' => get_user_meta($user_id, '_hng_other_service', true),
            'social_networks' => get_user_meta($user_id, '_hng_social_networks', true),
            'address' => implode(', ', $address_parts),
            'registered' => $user->user_registered,
        ];
    }
    
    private function get_stats() {
        global $wpdb;
        
        // Total leads
        $total = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->users} u
            INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
            WHERE um.meta_key = '{$wpdb->prefix}capabilities'
            AND (um.meta_value LIKE '%subscriber%' OR um.meta_value LIKE '%customer%' OR um.meta_value LIKE '%hng_customer%')
        ");
        
        // Providers
        $providers = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->usermeta}
            WHERE meta_key = %s AND meta_value = %s
        ", '_hng_client_type', 'provider'));
        
        // Companies
        $companies = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->usermeta}
            WHERE meta_key = %s AND meta_value = %s
        ", '_hng_client_type', 'company'));
        
        // Last 7 days
        $last_7_days = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->users}
            WHERE user_registered >= %s
        ", date('Y-m-d H:i:s', strtotime('-7 days'))));
        
        return [
            'total' => (int) $total,
            'providers' => (int) $providers,
            'companies' => (int) $companies,
            'last_7_days' => (int) $last_7_days,
        ];
    }
    
    private function get_available_services() {
        global $wpdb;
        
        $services = [];
        
        // Get from services_provided
        $results = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT meta_value FROM {$wpdb->usermeta}
            WHERE meta_key = %s AND meta_value != ''
        ", '_hng_services_provided'));
        
        foreach ($results as $row) {
            foreach (explode(',', $row) as $service) {
                $service = trim($service);
                if ($service && !in_array($service, $services)) {
                    $services[] = $service;
                }
            }
        }
        
        // Get from service_needed
        $results = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT meta_value FROM {$wpdb->usermeta}
            WHERE meta_key = %s AND meta_value != ''
        ", '_hng_service_needed'));
        
        foreach ($results as $service) {
            $service = trim($service);
            if ($service && !in_array($service, $services)) {
                $services[] = $service;
            }
        }
        
        sort($services);
        return $services;
    }
    
    public function export_csv() {
        if (!current_user_can('manage_options')) {
            wp_die('Acesso negado');
        }
        
        check_admin_referer('hng_export_leads');
        
        $leads_data = $this->get_leads('', '', '', 1, 9999);
        $leads = $leads_data['leads'];
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=leads-' . date('Y-m-d') . '.csv');
        
        $output = fopen('php://output', 'w');
        
        // BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Header
        fputcsv($output, [
            'Nome',
            'Email',
            'Telefone',
            'CPF/CNPJ',
            'Tipo',
            'Área',
            'Serviços',
            'Necessidade',
            'Outro Serviço',
            'Endereço',
            'Data Cadastro',
        ], ';');
        
        // Data
        foreach ($leads as $lead) {
            fputcsv($output, [
                $lead['name'],
                $lead['email'],
                $lead['phone'],
                $lead['cpf_cnpj'],
                $lead['type'] === 'provider' ? 'Prestador' : 'Empresa',
                $lead['area'],
                $lead['services'],
                $lead['service_needed'],
                $lead['other_service'],
                $lead['address'],
                date('d/m/Y H:i', strtotime($lead['registered'])),
            ], ';');
        }
        
        fclose($output);
        exit;
    }
}

// Initialize
HNG_Leads_Admin::instance();
