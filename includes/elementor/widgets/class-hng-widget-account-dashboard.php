<?php
if (!defined('ABSPATH')) { exit; }

class HNG_Widget_Account_Dashboard extends HNG_Commerce_Elementor_Widget_Base {
    public function get_name() { return 'hng_account_dashboard'; }
    public function get_title() { return __('Painel da Conta', 'hng-commerce'); }
    public function get_icon() { return 'eicon-user-circle-o'; }

    protected function register_controls() {
                // Títulos, descrições e placeholders editáveis
                $this->start_controls_section(
                    'labels_section',
                    [
                        'label' => __('Textos e Placeholders', 'hng-commerce'),
                        'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
                    ]
                );
                $this->add_control('label_dashboard_title', [ 'label' => __('Título do Painel', 'hng-commerce'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Minha Conta', 'hng-commerce') ]);
                $this->add_control('label_orders', [ 'label' => __('Título Pedidos', 'hng-commerce'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Meus Pedidos', 'hng-commerce') ]);
                $this->add_control('label_subscriptions', [ 'label' => __('Título Assinaturas', 'hng-commerce'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Minhas Assinaturas', 'hng-commerce') ]);
                $this->add_control('label_empty_orders', [ 'label' => __('Mensagem Sem Pedidos', 'hng-commerce'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Nenhum pedido encontrado.', 'hng-commerce') ]);
                $this->add_control('label_empty_subscriptions', [ 'label' => __('Mensagem Sem Assinaturas', 'hng-commerce'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Nenhuma assinatura encontrada.', 'hng-commerce') ]);
                $this->end_controls_section();

                // Variação de layout (abas, lista, cards)
                $this->start_controls_section(
                    'layout_section',
                    [
                        'label' => __('Layout', 'hng-commerce'),
                        'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
                    ]
                );
                $this->add_control('account_dashboard_layout', [
                    'label' => __('Layout do Painel', 'hng-commerce'),
                    'type' => \Elementor\Controls_Manager::SELECT,
                    'options' => [
                        'tabs' => __('Abas', 'hng-commerce'),
                        'list' => __('Lista', 'hng-commerce'),
                        'cards' => __('Cards', 'hng-commerce'),
                    ],
                    'default' => 'tabs',
                ]);
                $this->end_controls_section();
        // Content Section
        $this->start_controls_section(
            'content_section',
            [
                'label' => __('Configurações', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'show_orders',
            [
                'label' => __('Mostrar Pedidos', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_subscriptions',
            [
                'label' => __('Mostrar Assinaturas', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'orders_per_page',
            [
                'label' => __('Pedidos por Página', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 10,
                'min' => 1,
            ]
        );

        $this->end_controls_section();

        // Container Style
        $this->start_controls_section(
            'container_style_section',
            [
                'label' => __('Container', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'container_background',
            [
                'label' => __('Cor de Fundo', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-account-dashboard' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'container_padding',
            [
                'label' => __('Padding', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .hng-account-dashboard' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Navigation Style
        $this->start_controls_section(
            'nav_style_section',
            [
                'label' => __('Navegação', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->start_controls_tabs('nav_tabs');

        $this->start_controls_tab(
            'nav_normal_tab',
            [
                'label' => __('Normal', 'hng-commerce'),
            ]
        );

        $this->add_control(
            'nav_color',
            [
                'label' => __('Cor', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-account-nav a' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'nav_background',
            [
                'label' => __('Cor de Fundo', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-account-nav a' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'nav_active_tab',
            [
                'label' => __('Ativo', 'hng-commerce'),
            ]
        );

        $this->add_control(
            'nav_active_color',
            [
                'label' => __('Cor', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-account-nav a.active' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'nav_active_background',
            [
                'label' => __('Cor de Fundo', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-account-nav a.active' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'nav_typography',
                'selector' => '{{WRAPPER}} .hng-account-nav a',
                'separator' => 'before',
            ]
        );

        $this->end_controls_section();

        // Table Style
        $this->start_controls_section(
            'table_style_section',
            [
                'label' => __('Tabelas', 'hng-commerce'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'table_border',
                'selector' => '{{WRAPPER}} .hng-account-table',
            ]
        );

        $this->add_control(
            'table_header_background',
            [
                'label' => __('Cor de Fundo do Cabeçalho', 'hng-commerce'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .hng-account-table thead th' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        if (!is_user_logged_in()) {
            echo '<div class="hng-account-login-required" role="alert">';
            echo '<p>' . (!empty($settings['label_login_required']) ? esc_html($settings['label_login_required']) : esc_html__('Você precisa estar logado para ver esta página.', 'hng-commerce')) . '</p>';
            echo '<a href="' . esc_url(wp_login_url(get_permalink())) . '">' . (!empty($settings['label_login_button']) ? esc_html($settings['label_login_button']) : esc_html__('Fazer Login', 'hng-commerce')) . '</a>';
            echo '</div>';
            return;
        }

        $current_user = wp_get_current_user();
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET parameter for tab navigation in account dashboard, no data modification
        $current_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'orders';
        $layout = !empty($settings['account_dashboard_layout']) ? $settings['account_dashboard_layout'] : 'tabs';
        ?>
        <div class="hng-account-dashboard layout-<?php echo esc_attr($layout); ?>" aria-label="Painel da Conta">
            <div class="hng-account-header">
                <?php /* translators: %s: user display name */ ?>
                <h2><?php echo !empty($settings['label_dashboard_title']) ? esc_html($settings['label_dashboard_title']) : sprintf(esc_html__('Olá, %s', 'hng-commerce'), esc_html($current_user->display_name)); ?></h2>
            </div>

            <nav class="hng-account-nav" role="tablist">
                <a href="?tab=orders" class="<?php echo esc_attr( $current_tab === 'orders' ? 'active' : '' ); ?>" role="tab" aria-selected="<?php echo esc_attr( $current_tab === 'orders' ? 'true' : 'false' ); ?>">
                    <?php echo !empty($settings['label_orders']) ? esc_html($settings['label_orders']) : esc_html__('Meus Pedidos', 'hng-commerce'); ?>
                </a>
                <?php if ($settings['show_subscriptions'] === 'yes') : ?>
                    <a href="?tab=subscriptions" class="<?php echo esc_attr( $current_tab === 'subscriptions' ? 'active' : '' ); ?>" role="tab" aria-selected="<?php echo esc_attr( $current_tab === 'subscriptions' ? 'true' : 'false' ); ?>">
                        <?php echo !empty($settings['label_subscriptions']) ? esc_html($settings['label_subscriptions']) : esc_html__('Minhas Assinaturas', 'hng-commerce'); ?>
                    </a>
                <?php endif; ?>
                <a href="?tab=profile" class="<?php echo esc_attr( $current_tab === 'profile' ? 'active' : '' ); ?>" role="tab" aria-selected="<?php echo esc_attr( $current_tab === 'profile' ? 'true' : 'false' ); ?>">
                    <?php echo !empty($settings['label_profile']) ? esc_html($settings['label_profile']) : esc_html__('Meus Dados', 'hng-commerce'); ?>
                </a>
                <a href="<?php echo esc_url(wp_logout_url(get_permalink())); ?>">
                    <?php echo !empty($settings['label_logout']) ? esc_html($settings['label_logout']) : esc_html__('Sair', 'hng-commerce'); ?>
                </a>
            </nav>

            <div class="hng-account-content" tabindex="0">
                <?php if ($current_tab === 'orders' && $settings['show_orders'] === 'yes') : ?>
                    <?php $this->render_orders_tab($current_user->ID, $settings['orders_per_page'], $settings); ?>
                <?php elseif ($current_tab === 'subscriptions' && $settings['show_subscriptions'] === 'yes') : ?>
                    <?php $this->render_subscriptions_tab($current_user->ID, $settings); ?>
                <?php elseif ($current_tab === 'profile') : ?>
                    <?php $this->render_profile_tab($current_user, $settings); ?>
                <?php endif; ?>
            </div>
        </div>
        <style>
            {{WRAPPER}} .hng-account-header {
                margin-bottom: 30px;
            }
            {{WRAPPER}} .hng-account-nav {
                display: flex;
                gap: 10px;
                margin-bottom: 30px;
                flex-wrap: wrap;
            }
            {{WRAPPER}}.layout-list .hng-account-nav {
                flex-direction: column;
                gap: 0;
            }
            {{WRAPPER}}.layout-cards .hng-account-nav a {
                border: 1px solid #eee;
                box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            }
            {{WRAPPER}} .hng-account-nav a {
                padding: 10px 20px;
                text-decoration: none;
                border-radius: 4px;
                transition: all 0.3s ease;
            }
            {{WRAPPER}} .hng-account-table {
                width: 100%;
                border-collapse: collapse;
            }
            {{WRAPPER}} .hng-account-table th,
            {{WRAPPER}} .hng-account-table td {
                padding: 12px;
                text-align: left;
                border-bottom: 1px solid #eee;
            }
            {{WRAPPER}} .hng-profile-field {
                margin-bottom: 15px;
            }
            {{WRAPPER}} .hng-profile-field label {
                display: block;
                margin-bottom: 5px;
                font-weight: 600;
            }
            {{WRAPPER}} .hng-profile-field input {
                width: 100%;
                padding: 10px;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
        </style>
        <?php
    }

    private function render_orders_tab($user_id, $per_page, $settings = []) {
        $args = [
            'post_type' => 'hng_order',
            'posts_per_page' => $per_page,
            'meta_query' => [
                [
                    'key' => '_customer_id',
                    'value' => $user_id,
                ],
            ],
        ];

        $orders = new WP_Query($args);

        if ($orders->have_posts()) {
            echo '<table class="hng-account-table" aria-label="Pedidos">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__('Pedido', 'hng-commerce') . '</th>';
            echo '<th>' . esc_html__('Data', 'hng-commerce') . '</th>';
            echo '<th>' . esc_html__('Status', 'hng-commerce') . '</th>';
            echo '<th>' . esc_html__('Total', 'hng-commerce') . '</th>';
            echo '<th>' . esc_html__('Ações', 'hng-commerce') . '</th>';
            echo '</tr></thead><tbody>';

            while ($orders->have_posts()) {
                $orders->the_post();
                $order = new HNG_Order(get_the_ID());
                echo '<tr>';
                echo '<td>#' . esc_html(get_the_ID()) . '</td>';
                echo '<td>' . esc_html(get_the_date()) . '</td>';
                echo '<td>' . esc_html($order->get_status()) . '</td>';
                echo '<td>' . esc_html(hng_price($order->get_total())) . '</td>';
                echo '<td><a href="?tab=order&order_id=' . esc_attr(get_the_ID()) . '">' . esc_html__('Ver', 'hng-commerce') . '</a></td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
            wp_reset_postdata();
        } else {
            $msg = !empty($settings['label_empty_orders']) ? esc_html($settings['label_empty_orders']) : esc_html__('Você ainda não fez nenhum pedido.', 'hng-commerce');
            echo '<p>' . esc_html($msg) . '</p>';
        }
    }

    private function render_subscriptions_tab($user_id, $settings = []) {
        $args = [
            'post_type' => 'hng_subscription',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_customer_id',
                    'value' => $user_id,
                ],
            ],
        ];

        $subscriptions = new WP_Query($args);

        if ($subscriptions->have_posts()) {
            echo '<table class="hng-account-table" aria-label="Assinaturas">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__('Assinatura', 'hng-commerce') . '</th>';
            echo '<th>' . esc_html__('Status', 'hng-commerce') . '</th>';
            echo '<th>' . esc_html__('Próxima Cobrança', 'hng-commerce') . '</th>';
            echo '<th>' . esc_html__('Ações', 'hng-commerce') . '</th>';
            echo '</tr></thead><tbody>';

            while ($subscriptions->have_posts()) {
                $subscriptions->the_post();
                $subscription = new HNG_Subscription(get_the_ID());
                echo '<tr>';
                echo '<td>#' . esc_attr(get_the_ID()) . '</td>';
                echo '<td>' . esc_html($subscription->get_status()) . '</td>';
                echo '<td>' . esc_html(date_i18n(get_option('date_format'), strtotime($subscription->get_next_payment_date()))) . '</td>';
                echo '<td><a href="?tab=subscription&subscription_id=' . esc_attr(get_the_ID()) . '">' . esc_html__('Ver', 'hng-commerce') . '</a></td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
            wp_reset_postdata();
        } else {
            $msg = !empty($settings['label_empty_subscriptions']) ? esc_html($settings['label_empty_subscriptions']) : esc_html__('Você não possui assinaturas ativas.', 'hng-commerce');
            echo '<p>' . esc_html($msg) . '</p>';
        }
    }

    private function render_profile_tab($user, $settings = []) {
        echo '<form method="post" action="" class="hng-profile-form" aria-label="Editar Perfil">';
        echo '<div class="hng-profile-field">';
        echo '<label>' . (!empty($settings['label_profile_name']) ? esc_html($settings['label_profile_name']) : esc_html__('Nome', 'hng-commerce')) . '</label>';
        echo '<input type="text" name="first_name" value="' . esc_attr($user->first_name) . '" />';
        echo '</div>';
        echo '<div class="hng-profile-field">';
        echo '<label>' . (!empty($settings['label_profile_lastname']) ? esc_html($settings['label_profile_lastname']) : esc_html__('Sobrenome', 'hng-commerce')) . '</label>';
        echo '<input type="text" name="last_name" value="' . esc_attr($user->last_name) . '" />';
        echo '</div>';
        echo '<div class="hng-profile-field">';
        echo '<label>' . (!empty($settings['label_profile_email']) ? esc_html($settings['label_profile_email']) : esc_html__('Email', 'hng-commerce')) . '</label>';
        echo '<input type="email" name="user_email" value="' . esc_attr($user->user_email) . '" />';
        echo '</div>';
        echo '<button type="submit" class="hng-profile-submit">' . (!empty($settings['label_profile_save']) ? esc_html($settings['label_profile_save']) : esc_html__('Salvar Alterações', 'hng-commerce')) . '</button>';
        wp_nonce_field('hng-update-profile', 'hng_profile_nonce');
        echo '</form>';
    }
}
