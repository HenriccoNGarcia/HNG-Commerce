<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap hng-email-customizer-v2">

    <h1 class="hng-page-title">

        <span class="dashicons dashicons-email"></span>

        <?php esc_html_e('Customização de Emails', 'hng-commerce'); ?>

    </h1>

    

    <!-- Barra de Templates -->

    <div class="hng-email-templates-bar">

        <div class="templates-label"><?php esc_html_e('Selecione o Template:', 'hng-commerce'); ?></div>

        <div class="templates-list">

            <?php foreach ($email_types as $type => $info): ?>

                <a href="<?php echo esc_url(add_query_arg('email_type', $type, admin_url('admin.php?page=hng-emails'))); ?>" 

                   class="template-item <?php echo esc_html(esc_html($type)) === $current_type ? 'active' : ''; ?>">

                    <span class="dashicons dashicons-email-alt"></span>

                    <span class="template-name"><?php echo esc_html($info['name']); ?></span>

                </a>

            <?php endforeach; ?>

        </div>

    </div>

    

    <!-- Layout: Editor + Preview -->

    <div class="hng-email-layout">

        <?php if ($current_type === 'global_settings'): ?>

            <!-- Configurações Globais -->

            <?php HNG_Email_Global_Settings::render_settings_page(); ?>

        <?php else: ?>

        <!-- Editor de Template -->

        <div class="hng-email-editor">

            <input type="hidden" id="current-email-type" value="<?php echo esc_html(esc_attr($current_type)); ?>">

            <?php wp_nonce_field('hng_email_customizer', 'hng_email_nonce'); ?>

            

            <!-- Tabs -->

            <div class="hng-editor-tabs">

                <button type="button" class="tab-btn active" data-tab="design">🎨 Design</button>

                <button type="button" class="tab-btn" data-tab="content">📝 Conteúdo</button>

                <button type="button" class="tab-btn" data-tab="settings">⚙️ Configuração</button>

                <button type="button" class="tab-btn" data-tab="variables">🔧 Variáveis</button>

            </div>

            

            <!-- Tab: Design -->

            <div class="tab-content active" data-tab="design">

                <div class="form-group">

                    <label>Logo do Email</label>

                    <div class="logo-uploader">

                        <input type="hidden" name="logo" id="email-logo" value="<?php echo esc_attr($settings['logo']); ?>">

                        <div class="logo-preview-box">

                            <?php if (!empty($settings['logo'])): ?>

                                <img src="<?php echo esc_url($settings['logo']); ?>" class="logo-img">

                            <?php else: ?>

                                <div class="logo-placeholder">

                                    <span class="dashicons dashicons-format-image"></span>

                                    <p>Clique para adicionar logo</p>

                                </div>

                            <?php endif; ?>

                        </div>

                        <div class="logo-actions">

                            <button type="button" class="button" id="upload-logo">Selecionar Logo</button>

                            <?php if (!empty($settings['logo'])): ?>

                                <button type="button" class="button" id="remove-logo">Remover</button>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>

                

                <div class="color-grid">

                    <div class="form-group">

                        <label>Cor do Cabeçalho</label>

                        <input type="text" id="header-color" name="header_color" value="<?php echo esc_attr($settings['header_color']); ?>" class="color-picker">

                    </div>

                    <div class="form-group">

                        <label>Cor dos Botões</label>

                        <input type="text" id="button-color" name="button_color" value="<?php echo esc_attr($settings['button_color']); ?>" class="color-picker">

                    </div>

                    <div class="form-group">

                        <label>Cor do Texto</label>

                        <input type="text" id="text-color" name="text_color" value="<?php echo esc_attr($settings['text_color']); ?>" class="color-picker">

                    </div>

                    <div class="form-group">

                        <label>Cor de Fundo</label>

                        <input type="text" id="bg-color" name="bg_color" value="<?php echo esc_attr($settings['bg_color']); ?>" class="color-picker">

                    </div>

                </div>

            </div>

            

            <!-- Tab: Conteúdo -->

            <div class="tab-content" data-tab="content">

                <p class="description" style="margin-bottom: 15px;">

                    💡 <strong>Dica:</strong> Arraste variáveis da aba "Variáveis" direto para o preview ao lado →

                </p>

                <div class="editor-resize-wrapper">

                    <?php

                    wp_editor($settings['content'], 'email-content-editor', [

                        'textarea_name' => 'email_content',

                        'textarea_rows' => 15,

                        'media_buttons' => true,

                        'tinymce' => [

                            'toolbar1' => 'formatselect,bold,italic,underline,forecolor,backcolor,alignleft,aligncenter,alignright,link,image',

                            'toolbar2' => 'bullist,numlist,blockquote,undo,redo,removeformat,code,fullscreen',

                        ],

                    ]);

                    ?>

                    <div class="editor-resize-handle">

                        <span class="dashicons dashicons-sort"></span>

                        Arraste para redimensionar

                    </div>

                </div>

            </div>

            

            <!-- Tab: Configurações -->

            <div class="tab-content" data-tab="settings">

                <div class="form-group">

                    <label>Assunto do Email</label>

                    <input type="text" id="email-subject" name="subject" value="<?php echo esc_attr($settings['subject']); ?>" class="widefat" placeholder="Pedido #{order_number} recebido!">

                    <p class="description">Você pode usar variáveis no assunto</p>

                </div>

                

                <div class="form-group">

                    <label>Nome do Remetente</label>

                    <input type="text" id="from-name" name="from_name" value="<?php echo esc_attr($settings['from_name']); ?>" class="widefat">

                </div>

                

                <div class="form-group">

                    <label>Email do Remetente</label>

                    <input type="email" id="from-email" name="from_email" value="<?php echo esc_attr($settings['from_email']); ?>" class="widefat">

                </div>

            </div>

            

            <!-- Tab: Variáveis -->

            <div class="tab-content" data-tab="variables">

                <div class="variables-help" style="margin-bottom: 15px;">

                    <h4>Como usar:</h4>

                    <ul>

                        <li>➜ <strong>Arraste</strong> a variável direto para o <strong>preview ao lado</strong></li>

                        <li>🔧 Ou <strong>clique no ícone</strong> para inserir no editor</li>

                        <li>📝 Edite o texto <strong>direto no preview</strong> clicando nele</li>

                    </ul>

                </div>

                

                <div class="variables-draggable-list">

                    <?php foreach ($email_info['variables'] as $var => $desc): ?>

                        <div class="variable-item" draggable="true" data-variable="<?php echo esc_html(esc_attr($var)); ?>">

                            <span class="drag-handle">

                                <span class="dashicons dashicons-move"></span>

                            </span>

                            <code class="variable-code"><?php echo esc_html(esc_html($var)); ?></code>

                            <span class="variable-desc"><?php echo esc_html(esc_html($desc)); ?></span>

                            <button type="button" class="copy-var-btn" title="Inserir no preview">

                                <span class="dashicons dashicons-insert"></span>

                            </button>

                        </div>

                    <?php endforeach; ?>

                </div>

            </div>

            

            <!-- Botões -->

            <div class="hng-editor-actions">

                <button type="button" class="button button-primary button-large" id="save-email-template">

                    <span class="dashicons dashicons-saved"></span>

                    Salvar Template

                </button>

                <?php if ($current_type !== 'global_settings'): ?>

                <button type="button" class="button button-large" id="use-global-settings">

                    <span class="dashicons dashicons-admin-settings"></span>

                    Usar Configurações Globais

                </button>

                <?php endif; ?>

                <button type="button" class="button button-large" id="reset-template">

                    <span class="dashicons dashicons-update"></span>

                    Restaurar Padrão

                </button>

            </div>

        </div>

        

        <!-- Preview -->

        <div class="hng-email-preview">

            <div class="preview-header">

                <h3>Preview do Email</h3>

                <div class="preview-controls">

                    <div class="preview-mode-toggle">

                        <button type="button" class="mode-btn active" data-mode="visual" title="Modo Visual">

                            <span class="dashicons dashicons-edit"></span>

                            Visual

                        </button>

                        <button type="button" class="mode-btn" data-mode="code" title="Modo Código">

                            <span class="dashicons dashicons-editor-code"></span>

                            Código

                        </button>

                    </div>

                    

                    <select id="preview-order" class="preview-select">

                        <option value="sample">👤 Dados de Exemplo</option>

                        <?php if (!empty($orders)): ?>

                            <optgroup label="Pedidos Reais">

                                <?php foreach ($orders as $order): ?>

                                    <option value="<?php echo esc_attr($order->id); ?>">

                                        <?php echo esc_html($order->order_number); ?> - 

                                        <?php echo esc_html($order->customer_name); ?>

                                    </option>

                                <?php endforeach; ?>

                            </optgroup>

                        <?php endif; ?>

                    </select>

                    

                    <button type="button" class="button" id="refresh-preview" title="Atualizar">

                        <span class="dashicons dashicons-update"></span>

                    </button>

                    

                    <button type="button" class="button" id="send-test-email" title="Enviar Teste">

                        <span class="dashicons dashicons-email-alt"></span>

                        Testar

                    </button>

                </div>

            </div>

            

            <div class="preview-device-tabs">

                <button type="button" class="device-tab active" data-device="desktop">

                    <span class="dashicons dashicons-desktop"></span>

                    Desktop

                </button>

                <button type="button" class="device-tab" data-device="mobile">

                    <span class="dashicons dashicons-smartphone"></span>

                    Mobile

                </button>

            </div>

            

            <div class="preview-container">

                <!-- Modo Visual: Edição Direta -->

                <div class="preview-viewport desktop" id="visual-mode-preview">

                    <div class="email-preview-editor" contenteditable="true" id="email-preview-editable">

                        <!-- Conteúdo será carregado aqui -->

                    </div>

                    <div class="preview-edit-hint">

                        <span class="dashicons dashicons-edit"></span>

                        Clique para editar ou arraste variáveis aqui

                    </div>

                </div>

                

                <!-- Modo Código: HTML Bruto -->

                <div class="preview-viewport desktop" id="code-mode-preview" style="display: none;">

                    <textarea id="email-code-editor" class="code-editor" spellcheck="false"></textarea>

                </div>

                

                <div class="preview-loading">

                    <span class="spinner is-active"></span>

                    <p>Carregando preview...</p>

                </div>

            </div>

        </div>

        <?php endif; ?>

    </div>

</div>

<?php include __DIR__ . '/email-customizer-styles.php'; ?>
<?php include __DIR__ . '/email-customizer-scripts.php'; ?>
