<?php
/**
 * HNG Admin - Appointments Page
 *
 * Página de gerenciamento de agendamentos de serviços.
 *
 * @package HNG_Commerce
 * @subpackage Admin/Pages
 * @since 1.1.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Admin_Appointments_Page {
    
    /**
     * Renderizar página
     */
    public static function render() {
        // Carregar classe de agendamentos se necessário
        if (!class_exists('HNG_Appointment')) {
            $appointment_file = HNG_COMMERCE_PATH . 'includes/class-hng-appointment.php';
            if (file_exists($appointment_file)) {
                require_once $appointment_file;
            }
        }

        $breadcrumbs = [
            ['text' => 'HNG Commerce', 'url' => admin_url('admin.php?page=hng-commerce')],
            ['text' => __('Agendamentos', 'hng-commerce')]
        ];

        echo '<div class="hng-wrap">';
        self::render_breadcrumbs($breadcrumbs);
        
        self::render_header();
        self::render_filters();
        self::render_table();
        self::render_javascript();
        
        echo '</div>';
    }
    
    /**
     * Renderizar breadcrumbs
     */
    private static function render_breadcrumbs($items) {
        if (empty($items)) {
            return;
        }
        
        echo '<nav class="hng-breadcrumbs">';
        foreach ($items as $i => $item) {
            if ($i > 0) {
                echo ' <span class="separator">/</span> ';
            }
            
            if (isset($item['url'])) {
                echo '<a href="' . esc_url($item['url']) . '">' . esc_html($item['text']) . '</a>';
            } else {
                echo '<span>' . esc_html($item['text']) . '</span>';
            }
        }
        echo '</nav>';
    }
    
    /**
     * Renderizar cabeçalho
     */
    private static function render_header() {
        echo '<div class="hng-header">';
        echo '<h1>';
        echo '<span class="dashicons dashicons-calendar-alt"></span>';
        echo esc_html__('Agendamentos', 'hng-commerce');
        echo '<span class="hng-help-icon" data-hng-tip="' . esc_attr__('Gerencie todos os agendamentos de serviços. Confirme, cancele ou marque como completo.', 'hng-commerce') . '">?</span>';
        echo '</h1>';
        echo '</div>';
    }
    
    /**
     * Renderizar filtros
     */
    private static function render_filters() {
        echo '<div class="hng-card" style="margin-bottom: 20px;">';
        echo '<div class="hng-card-content">';
        echo '<div style="display:flex;gap:10px;align-items:center;margin-bottom:15px;">';
        
        // Botão para adicionar novo agendamento
        echo '<button id="hng-add-appointment-btn" class="button button-primary">';
        echo '<span class="dashicons dashicons-plus" style="margin-right: 5px;"></span>';
        echo esc_html__('Novo Agendamento', 'hng-commerce');
        echo '</button>';
        
        echo '</div>';
        echo '<div style="display:flex;gap:10px;align-items:center;margin-bottom:0;">';
        
        // Filtro de status
        echo '<label>' . esc_html__('Status:', 'hng-commerce') . '</label>';
        echo '<select id="hng-appointments-status-filter" class="hng-input" style="width:auto;">';
        echo '<option value="all">' . esc_html__('Todos', 'hng-commerce') . '</option>';
        echo '<option value="pending">' . esc_html__('Pendente', 'hng-commerce') . '</option>';
        echo '<option value="confirmed">' . esc_html__('Confirmado', 'hng-commerce') . '</option>';
        echo '<option value="completed">' . esc_html__('Completo', 'hng-commerce') . '</option>';
        echo '<option value="cancelled">' . esc_html__('Cancelado', 'hng-commerce') . '</option>';
        echo '</select>';
        
        // Filtro de período
        echo '<label style="margin-left:15px;">' . esc_html__('Período:', 'hng-commerce') . '</label>';
        echo '<select id="hng-appointments-date-filter" class="hng-input" style="width:auto;">';
        echo '<option value="all">' . esc_html__('Todos', 'hng-commerce') . '</option>';
        echo '<option value="today">' . esc_html__('Hoje', 'hng-commerce') . '</option>';
        echo '<option value="week">' . esc_html__('Esta Semana', 'hng-commerce') . '</option>';
        echo '<option value="month">' . esc_html__('Este Mês', 'hng-commerce') . '</option>';
        echo '</select>';
        
        echo '<button class="button" id="hng-appointments-apply-filter" style="margin-left:auto;">' . esc_html__('Atualizar', 'hng-commerce') . '</button>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Renderizar tabela
     */
    private static function render_table() {
        echo '<div class="hng-card">';
        echo '<div class="hng-card-content">';
        echo '<table class="widefat striped" id="hng-appointments-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . esc_html__('ID', 'hng-commerce') . '</th>';
        echo '<th>' . esc_html__('Cliente', 'hng-commerce') . '</th>';
        echo '<th>' . esc_html__('Serviço', 'hng-commerce') . '</th>';
        echo '<th>' . esc_html__('Data', 'hng-commerce') . '</th>';
        echo '<th>' . esc_html__('Horário', 'hng-commerce') . '</th>';
        echo '<th>' . esc_html__('Duração', 'hng-commerce') . '</th>';
        echo '<th>' . esc_html__('Status', 'hng-commerce') . '</th>';
        echo '<th>' . esc_html__('Ações', 'hng-commerce') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        echo '<tr><td colspan="8" style="text-align:center;">' . esc_html__('Carregando...', 'hng-commerce') . '</td></tr>';
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
        echo '</div>';
        
        // Modal para criar novo agendamento
        echo '<div id="hng-appointment-modal" class="hng-modal" style="display:none;">';
        echo '<div class="hng-modal-content" style="max-width: 500px;">';
        echo '<div class="hng-modal-header">';
        echo '<h2>' . esc_html__('Novo Agendamento', 'hng-commerce') . '</h2>';
        echo '<button class="hng-modal-close" data-modal="hng-appointment-modal">&times;</button>';
        echo '</div>';
        echo '<form id="hng-new-appointment-form" class="hng-form">';
        
        // Campos do formulário
        echo '<div class="hng-form-group">';
        echo '<label>' . esc_html__('Serviço *', 'hng-commerce') . '</label>';
        echo '<select id="hng-appt-product-id" name="product_id" required style="width:100%;">';
        echo '<option value="">' . esc_html__('Selecione um serviço...', 'hng-commerce') . '</option>';
        self::render_appointment_services_options();
        echo '</select>';
        echo '</div>';
        
        echo '<div class="hng-form-group">';
        echo '<label>' . esc_html__('Nome do Cliente *', 'hng-commerce') . '</label>';
        echo '<input type="text" id="hng-appt-customer-name" name="customer_name" required style="width:100%;" />';
        echo '</div>';
        
        echo '<div class="hng-form-group">';
        echo '<label>' . esc_html__('E-mail do Cliente *', 'hng-commerce') . '</label>';
        echo '<input type="email" id="hng-appt-customer-email" name="customer_email" required style="width:100%;" />';
        echo '</div>';
        
        echo '<div class="hng-form-group">';
        echo '<label>' . esc_html__('Data *', 'hng-commerce') . '</label>';
        echo '<input type="date" id="hng-appt-date" name="appointment_date" required style="width:100%;" />';
        echo '</div>';
        
        echo '<div class="hng-form-group">';
        echo '<label>' . esc_html__('Horário *', 'hng-commerce') . '</label>';
        echo '<input type="time" id="hng-appt-time" name="appointment_time" required style="width:100%;" />';
        echo '</div>';
        
        echo '<div class="hng-form-group">';
        echo '<label>' . esc_html__('Duração (minutos)', 'hng-commerce') . '</label>';
        echo '<input type="number" id="hng-appt-duration" name="duration" value="60" min="15" step="15" style="width:100%;" />';
        echo '</div>';
        
        echo '<div class="hng-form-group">';
        echo '<label>' . esc_html__('Profissional', 'hng-commerce') . '</label>';
        echo '<select id="hng-appt-professional-id" name="professional_id" style="width:100%;">';
        echo '<option value="0">' . esc_html__('Sem profissional atribuído', 'hng-commerce') . '</option>';
        echo '</select>';
        echo '</div>';
        
        echo '<div class="hng-form-group">';
        echo '<label>' . esc_html__('Status', 'hng-commerce') . '</label>';
        echo '<select id="hng-appt-status" name="status" style="width:100%;">';
        echo '<option value="pending">' . esc_html__('Pendente', 'hng-commerce') . '</option>';
        echo '<option value="confirmed">' . esc_html__('Confirmado', 'hng-commerce') . '</option>';
        echo '<option value="completed">' . esc_html__('Completo', 'hng-commerce') . '</option>';
        echo '</select>';
        echo '</div>';
        
        echo '<div class="hng-form-group">';
        echo '<label>' . esc_html__('Notas', 'hng-commerce') . '</label>';
        echo '<textarea id="hng-appt-notes" name="notes" style="width:100%;min-height:80px;"></textarea>';
        echo '</div>';
        
        echo '<div class="hng-modal-footer">';
        echo '<button type="submit" class="button button-primary">' . esc_html__('Criar Agendamento', 'hng-commerce') . '</button>';
        echo '<button type="button" class="button" data-modal="hng-appointment-modal">' . esc_html__('Cancelar', 'hng-commerce') . '</button>';
        echo '</div>';
        
        echo '</form>';
        echo '</div>';
        echo '</div>';
        
        // Backdrop
        echo '<div id="hng-modal-backdrop" class="hng-modal-backdrop" style="display:none;"></div>';
    }
    
    /**
     * Render appointment services options
     */
    private static function render_appointment_services_options() {
        $args = [
            'post_type' => 'hng_product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => '_product_type',
                    'value' => 'appointment'
                ]
            ]
        ];
        
        $products = get_posts($args);
        
        foreach ($products as $product) {
            echo '<option value="' . intval($product->ID) . '">' . esc_html($product->post_title) . '</option>';
        }
    }
    
    /**
     * Renderizar JavaScript (será movido para arquivo externo na Fase 1.5)
     */
    private static function render_javascript() {
        $nonce = wp_create_nonce('hng-commerce-admin');
        
        // Enqueue appointments scripts
        wp_enqueue_script(
            'hng-admin-appointments',
            HNG_COMMERCE_URL . 'assets/js/admin-appointments.js',
            array('jquery'),
            HNG_COMMERCE_VERSION,
            true
        );
        
        wp_localize_script('hng-admin-appointments', 'hngAppointmentsPage', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => $nonce,
            'i18n' => array(
                'loadError' => __('Erro ao carregar agendamentos.', 'hng-commerce'),
                'noAppointments' => __('Nenhum agendamento encontrado.', 'hng-commerce'),
                'confirmCancel' => __('Tem certeza que deseja cancelar este agendamento?', 'hng-commerce'),
                'confirmStatus' => __('Confirmar alteração de status?', 'hng-commerce'),
                'updateSuccess' => __('Status atualizado com sucesso!', 'hng-commerce'),
                'updateError' => __('Erro ao atualizar status.', 'hng-commerce'),
                'confirmEmail' => __('Enviar e-mail de confirmação para o cliente?', 'hng-commerce'),
                'emailSuccess' => __('E-mail enviado com sucesso!', 'hng-commerce'),
                'emailError' => __('Erro ao enviar e-mail.', 'hng-commerce'),
            ),
        ));
    }
}
