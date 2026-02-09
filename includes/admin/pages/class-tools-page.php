<?php
/**
 * Tools Page - Import/Export and System Tools
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Tools_Page {
    
    public static function render() {
        // Enqueue scripts
        wp_enqueue_script(
            'hng-admin-tools',
            HNG_COMMERCE_URL . 'assets/js/admin-tools.js',
            ['jquery'],
            HNG_COMMERCE_VERSION,
            true
        );
        
        wp_localize_script('hng-admin-tools', 'hngToolsPage', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('hng-commerce-admin'),
            'importNonce' => wp_create_nonce('hng_import_nonce'),
            'clearCacheNonce' => wp_create_nonce('hng-tools'),
            'recreatePagesNonce' => wp_create_nonce('hng-recreate-pages'),
            'recreateTablesNonce' => wp_create_nonce('hng-tools'),
            'i18n' => [
                'confirmMigrate' => __('Confirmar migração de notas de assinaturas? Isto importará as notas antigas para a nova tabela.', 'hng-commerce'),
                'starting' => __('Iniciando...', 'hng-commerce'),
                'error' => __('Erro', 'hng-commerce'),
                'migrated' => __('Migradas', 'hng-commerce'),
                'requestError' => __('Erro na requisição', 'hng-commerce'),
                'confirmClearCache' => __('Limpar todo o cache do plugin?', 'hng-commerce'),
                'cacheCleared' => __('✔ Cache limpo!', 'hng-commerce'),
                'cacheError' => __('✖ Erro ao limpar cache', 'hng-commerce'),
                'confirmRecreatePages' => __('Recriar páginas padrão? Páginas existentes não serão duplicadas.', 'hng-commerce'),
                'pagesRecreated' => __('✔ Páginas recriadas!', 'hng-commerce'),
                'confirmRecreateTables' => __('ATENÇÁO: Recriar tabelas? Dados existentes serão preservados.', 'hng-commerce'),
                'tablesRecreated' => __('✔ Tabelas recriadas!', 'hng-commerce'),
            ]
        ]);
        
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET parameter for tab navigation, no data modification
        $active_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'import_woo';
        
        echo '<div class="hng-wrap">';
        echo '<div class="hng-header">';
        echo '<h1>';
        echo '<span class="dashicons dashicons-admin-tools"></span>';
        esc_html_e('Ferramentas', 'hng-commerce');
        echo '</h1>';
        echo '</div>';

        echo '<div class="hng-tabs-wrapper" style="margin-bottom: 20px;">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=hng-tools&tab=import_data')) . '" class="hng-tool-tab ' . ($active_tab === 'import_data' || $active_tab === 'import_woo' || $active_tab === 'import_csv' ? 'active' : '') . '">';
        echo '<span class="dashicons dashicons-database-import"></span>';
        esc_html_e('Importar Dados', 'hng-commerce');
        echo '</a>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=hng-tools&tab=export')) . '" class="hng-tool-tab ' . ($active_tab === 'export' ? 'active' : '') . '">';
        echo '<span class="dashicons dashicons-database-export"></span>';
        esc_html_e('Exportar Dados', 'hng-commerce');
        echo '</a>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=hng-tools&tab=system')) . '" class="hng-tool-tab ' . ($active_tab === 'system' ? 'active' : '') . '">';
        echo '<span class="dashicons dashicons-admin-generic"></span>';
        esc_html_e('Sistema', 'hng-commerce');
        echo '</a>';
        echo '</div>';

        if ($active_tab === 'import_data' || $active_tab === 'import_woo' || $active_tab === 'import_csv') {
            echo '<div class="hng-card">';
            echo '<div class="hng-card-header">';
            echo '<h2 class="hng-card-title">' . esc_html__('Importar Produtos e Pedidos', 'hng-commerce') . '</h2>';
            echo '<p class="description">' . esc_html__('Importe dados via CSV para popular sua loja. Use esta ferramenta para migrações ou atualizações em massa.', 'hng-commerce') . '</p>';
            echo '</div>';
            echo '<div class="hng-card-content">';
            
            echo '<div class="hng-grid hng-grid-2" style="gap:30px;">';
            
            echo '<div>';
            echo '<h3>' . esc_html__('1. Configuração da Importação', 'hng-commerce') . '</h3>';
            echo '<div class="hng-form-group">';
            echo '<label class="hng-label">' . esc_html__('O que você quer importar?', 'hng-commerce') . '</label>';
            echo '<div style="display: flex; gap: 15px; margin-top: 5px;">';
            echo '<label><input type="radio" name="hng_import_type" value="products" checked> ' . esc_html__('Produtos', 'hng-commerce') . '</label>';
            echo '<label><input type="radio" name="hng_import_type" value="orders"> ' . esc_html__('Pedidos', 'hng-commerce') . '</label>';
            echo '</div>';
            echo '</div>';

            echo '<div class="hng-form-group">';
            echo '<label class="hng-label">' . esc_html__('Arquivo CSV:', 'hng-commerce') . '</label>';
            echo '<div style="display:flex; gap:10px; align-items:center;">';
            echo '<button class="button button-secondary" onclick="document.getElementById(\'hng-woo-file\').click()"><span class="dashicons dashicons-upload"></span> ' . esc_html__('Selecionar Arquivo', 'hng-commerce') . '</button>';
            echo '<span id="hng-woo-filename" style="color:#666; font-style:italic;">' . esc_html__('Nenhum arquivo selecionado', 'hng-commerce') . '</span>';
            echo '<input type="file" id="hng-woo-file" accept=".csv" style="display:none;">';
            echo '</div>';
            echo '</div>';

            echo '<div class="hng-form-group" style="margin-top:20px;">';
            echo '<button id="hng-start-woo-import" class="button button-primary button-large" disabled>';
            echo '<span class="dashicons dashicons-database-import"></span> ' . esc_html__('Iniciar Importação', 'hng-commerce');
            echo '</button>';
            echo '</div>';
            
            echo '<div id="hng-woo-progress" style="margin-top:20px; display:none;">';
            echo '<div style="background:#f0f0f1; border-radius:4px; height:20px; overflow:hidden;"><div style="background:#2271b1; height:100%; width:0%; transition:width 0.3s;"></div></div>';
            echo '<p class="progress-text" style="font-weight:bold; color:#2271b1; margin-top:5px;"></p>';
            echo '</div>';
            echo '</div>';

            echo '<div>';
            echo '<h3>' . esc_html__('2. Formato Obrigatório (CSV)', 'hng-commerce') . '</h3>';
            echo '<p class="description">' . esc_html__('Os arquivos devem estar codificados em UTF-8 e usar vírgula (,) como separador.', 'hng-commerce') . '</p>';
            
            // Format Info: Products
            echo '<div id="hng-fmt-products" class="hng-fmt-info">';
            echo '<h4>' . esc_html__('Colunas para Produtos:', 'hng-commerce') . '</h4>';
            echo '<ul style="list-style:disc; padding-left:20px; color:#555;">';
            echo '<li><strong>name</strong>: Nome do produto (Obrigatório)</li>';
            echo '<li><strong>price</strong>: Preço atual (Obrigatório)</li>';
            echo '<li><strong>sku</strong>: Código único</li>';
            echo '<li><strong>stock</strong>: Quantidade em estoque</li>';
            echo '<li><strong>description</strong>: Descrição do produto</li>';
            echo '<li><strong>categories</strong>: Ex: Roupas|Verão</li>';
            echo '<li><strong>image_url</strong>: URL da imagem</li>';
            echo '</ul>';
            echo '<a href="' . esc_url(admin_url('admin-ajax.php?action=hng_download_csv_template')) . '" class="button button-secondary" style="margin-top: 15px;">';
            echo '<span class="dashicons dashicons-download"></span> ' . esc_html__('Baixar Modelo Exemplo', 'hng-commerce');
            echo '</a>';
            echo '</div>';

            // Format Info: Orders
            echo '<div id="hng-fmt-orders" class="hng-fmt-info" style="display:none;">';
            echo '<h4>' . esc_html__('Colunas para Pedidos:', 'hng-commerce') . '</h4>';
            echo '<ul style="list-style:disc; padding-left:20px; color:#555;">';
            echo '<li><strong>order_number</strong>: Número do pedido (Obrigatório)</li>';
            echo '<li><strong>email</strong>: E-mail do cliente (Obrigatório)</li>';
            echo '<li><strong>total</strong>: Valor total (Obrigatório)</li>';
            echo '<li><strong>status</strong>: completed, processing, etc.</li>';
            echo '<li><strong>created_at</strong>: AAAA-MM-DD HH:MM:SS</li>';
            echo '<li><strong>customer_name</strong>: Nome do cliente</li>';
            echo '</ul>';
            echo '<p class="description" style="margin-top:10px;"><em>Nota: O sistema tentará vincular pedidos a clientes existentes pelo e-mail.</em></p>';
            echo '</div>';
            
            echo '</div>'; // End col 2
            
            echo '</div>'; // End grid
            echo '</div>';
            echo '</div>';
        } elseif ($active_tab === 'export') {
            echo '<div class="hng-grid hng-grid-2">';
            
            echo '<div class="hng-card">';
            echo '<div class="hng-card-header"><h2 class="hng-card-title">' . esc_html__('Exportar Produtos', 'hng-commerce') . '</h2></div>';
            echo '<div class="hng-card-content">';
            echo '<p>' . esc_html__('Exporte todos os produtos para arquivo CSV.', 'hng-commerce') . '</p>';
            echo '<div style="margin-top: 15px;">';
            echo '<button id="hng-export-products" class="button button-primary">';
            echo '<span class="dashicons dashicons-download"></span>' . esc_html__('Exportar Produtos (CSV)', 'hng-commerce');
            echo '</button>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            
            echo '<div class="hng-card">';
            echo '<div class="hng-card-header"><h2 class="hng-card-title">' . esc_html__('Exportar Pedidos', 'hng-commerce') . '</h2></div>';
            echo '<div class="hng-card-content">';
            echo '<p>' . esc_html__('Exporte todos os pedidos para arquivo CSV.', 'hng-commerce') . '</p>';
            echo '<div style="margin-top: 15px;">';
            echo '<div class="hng-form-group">';
            echo '<label>' . esc_html__('Período:', 'hng-commerce') . '</label>';
            echo '<select id="hng-export-period" class="hng-input">';
            echo '<option value="all">' . esc_html__('Todos os pedidos', 'hng-commerce') . '</option>';
            echo '<option value="30days">' . esc_html__('Últimos 30 dias', 'hng-commerce') . '</option>';
            echo '<option value="90days">' . esc_html__('Últimos 90 dias', 'hng-commerce') . '</option>';
            echo '<option value="year">' . esc_html__('Este ano', 'hng-commerce') . '</option>';
            echo '</select>';
            echo '</div>';
            echo '<button id="hng-export-orders" class="button button-primary">';
            echo '<span class="dashicons dashicons-download"></span>' . esc_html__('Exportar Pedidos (CSV)', 'hng-commerce');
            echo '</button>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            
            echo '<div class="hng-card">';
            echo '<div class="hng-card-header"><h2 class="hng-card-title">' . esc_html__('Exportar Configurações', 'hng-commerce') . '</h2></div>';
            echo '<div class="hng-card-content">';
            echo '<p>' . esc_html__('Exporte todas as configurações do plugin para backup.', 'hng-commerce') . '</p>';
            echo '<p class="description">⚠️ ' . esc_html__('Credenciais de gateways NÁO são incluídas por segurança.', 'hng-commerce') . '</p>';
            echo '<div style="margin-top: 15px;">';
            echo '<button id="hng-export-settings" class="button button-primary">';
            echo '<span class="dashicons dashicons-download"></span>' . esc_html__('Exportar Configurações (JSON)', 'hng-commerce');
            echo '</button>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            
            echo '</div>';
        } else {
            // System tab
            echo '<div class="hng-card">';
            echo '<div class="hng-card-header"><h2 class="hng-card-title">' . esc_html__('Backup e Restauração', 'hng-commerce') . '</h2></div>';
            echo '<div class="hng-card-content">';
            echo '<div class="hng-grid hng-grid-2">';
            
            echo '<div>';
            echo '<h3>' . esc_html__('Exportar Configurações', 'hng-commerce') . '</h3>';
            echo '<p>' . esc_html__('Faça backup de todas as configurações do plugin.', 'hng-commerce') . '</p>';
            echo '<button id="hng-export-config" class="button button-primary">';
            echo '<span class="dashicons dashicons-download"></span>' . esc_html__('Exportar', 'hng-commerce');
            echo '</button>';
            echo '</div>';
            
            echo '<div>';
            echo '<h3>' . esc_html__('Importar Configurações', 'hng-commerce') . '</h3>';
            echo '<p>' . esc_html__('Restaure configurações de um arquivo de backup.', 'hng-commerce') . '</p>';
            echo '<input type="file" id="hng-import-config-file" accept=".json" style="display:none;">';
            echo '<button id="hng-import-config" class="button button-secondary">';
            echo '<span class="dashicons dashicons-upload"></span>' . esc_html__('Importar', 'hng-commerce');
            echo '</button>';
            echo '</div>';
            
            echo '<div>';
            echo '<h3>' . esc_html__('Importar DB (SQL)', 'hng-commerce') . '</h3>';
            echo '<p>' . esc_html__('Executar arquivo .sql no banco de dados. (Avançado)', 'hng-commerce') . '</p>';
            echo '<input type="file" id="hng-import-sql-file" accept=".sql" style="display:none;">';
            echo '<button class="button button-secondary" onclick="document.getElementById(\'hng-import-sql-file\').click()">';
            echo '<span class="dashicons dashicons-database-import"></span>' . esc_html__('Executar SQL', 'hng-commerce');
            echo '</button>';
            echo '</div>';
            
            echo '</div>';
            echo '</div>';
            echo '</div>';
            
            echo '<div class="hng-card">';
            echo '<div class="hng-card-header"><h2 class="hng-card-title">' . esc_html__('Ferramentas do Sistema', 'hng-commerce') . '</h2></div>';
            echo '<div class="hng-card-content">';
            echo '<div class="hng-grid hng-grid-3">';
            
            echo '<div class="hng-quick-action" style="cursor:pointer;" onclick="hngClearCache()">';
            echo '<span class="dashicons dashicons-database"></span>';
            echo '<div><strong>' . esc_html__('Limpar Cache', 'hng-commerce') . '</strong>';
            echo '<p class="description">' . esc_html__('Limpar cache do plugin', 'hng-commerce') . '</p></div>';
            echo '</div>';
            
            echo '<div class="hng-quick-action" style="cursor:pointer;" onclick="hngRecreatePages()">';
            echo '<span class="dashicons dashicons-admin-page"></span>';
            echo '<div><strong>' . esc_html__('Recriar Páginas', 'hng-commerce') . '</strong>';
            echo '<p class="description">' . esc_html__('Recriar páginas padrão', 'hng-commerce') . '</p></div>';
            echo '</div>';
            
            echo '<div class="hng-quick-action" style="cursor:pointer;" onclick="hngRecreateTables()">';
            echo '<span class="dashicons dashicons-update"></span>';
            echo '<div><strong>' . esc_html__('Recriar Tabelas', 'hng-commerce') . '</strong>';
            echo '<p class="description">' . esc_html__('Recriar tabelas do banco', 'hng-commerce') . '</p></div>';
            echo '</div>';
            
            echo '</div>';
            
            echo '<div style="margin-top: 30px;">';
            echo '<h3>' . esc_html__('Informações do Sistema', 'hng-commerce') . '</h3>';
            echo '<table class="widefat">';
            echo '<tbody>';
            echo '<tr><td><strong>' . esc_html__('Versão do HNG Commerce', 'hng-commerce') . '</strong></td><td>' . esc_html(defined('HNG_COMMERCE_VERSION') ? HNG_COMMERCE_VERSION : 'N/A') . '</td></tr>';
            echo '<tr><td><strong>' . esc_html__('Versão do WordPress', 'hng-commerce') . '</strong></td><td>' . esc_html(get_bloginfo('version')) . '</td></tr>';
            echo '<tr><td><strong>' . esc_html__('Versão do PHP', 'hng-commerce') . '</strong></td><td>' . esc_html(phpversion()) . '</td></tr>';
            echo '<tr><td><strong>' . esc_html__('Total de Produtos', 'hng-commerce') . '</strong></td><td>';
            $products_count = wp_count_posts('hng_product');
            echo (isset($products_count->publish) ? (int)$products_count->publish : 0);
            echo '</td></tr>';
            echo '<tr><td><strong>' . esc_html__('Total de Pedidos', 'hng-commerce') . '</strong></td><td>';
            global $wpdb;
            $t_orders = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_orders') : ($wpdb->prefix . 'hng_orders');
            $safe_table = '`' . str_replace('`','', $t_orders) . '`';
            $sql = "SELECT COUNT(*) FROM {$safe_table}";
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Nome de tabela sanitizado via hng_db_full_table_name
            echo (int) $wpdb->get_var($sql);
            echo '</td></tr>';
            echo '</tbody></table>';
            echo '<div style="margin-top:20px;">';
            echo '<h3>' . esc_html__('Migrações', 'hng-commerce') . '</h3>';
            echo '<p class="description">' . esc_html__('Migrar notas de assinaturas armazenadas em options para a nova tabela. Execute apenas uma vez.', 'hng-commerce') . '</p>';
            echo '<button id="hng-migrate-notes" class="button button-primary">' . esc_html__('Migrar Notas de Assinaturas', 'hng-commerce') . '</button>';
            echo '<span id="hng-migrate-notes-result" style="margin-left:10px;"></span>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        
        echo '</div>';
        
        // Scripts already enqueued in render() method above
        
        echo '<style>


        .hng-tabs-wrapper {
            display: flex;
            gap: 5px;
            border-bottom: 2px solid var(--hng-border);
        }
        .hng-tabs-wrapper .hng-tool-tab {
            padding: 12px 20px;
            text-decoration: none;
            color: var(--hng-text-main);
            border-bottom: 3px solid transparent;
            transition: all 0.2s;
        }
        .hng-tabs-wrapper .hng-tool-tab:hover {
            color: var(--hng-primary);
            background-color: rgba(99, 102, 241, 0.05);
        }
        .hng-tabs-wrapper .hng-tool-tab.active {
            color: var(--hng-primary);
            border-bottom-color: var(--hng-primary);
            font-weight: 600;
        }
        </style>';
    }
}
