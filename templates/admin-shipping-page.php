<?php
if (!defined('ABSPATH')) {
    exit;
}

$providers = $data ?? [];
$provider_meta = [
    'correios' => ['icon' => 'email-alt', 'name' => 'Correios', 'category' => 'Correios', 'desc' => 'Integração direta com serviços SEDEX e PAC.'],
    'jadlog' => ['icon' => 'car', 'name' => 'Jadlog', 'category' => 'Transportadora', 'desc' => 'Envio de encomendas expressas e logísticas.'],
    'melhorenvio' => ['icon' => 'cloud', 'name' => 'Melhor Envio', 'category' => 'Agregador', 'desc' => 'Cotação simultânea em diversas transportadoras.'],
    'loggi' => ['icon' => 'location-alt', 'name' => 'Loggi', 'category' => 'Transportadora', 'desc' => 'Entregas expressas locais e nacionais.'],
    'total_express' => ['icon' => 'networking', 'name' => 'Total Express', 'category' => 'Transportadora', 'desc' => 'Soluções logísticas para e-commerce.'],
];
?>
<div class="hng-wrap hng-shipping-page">
    <?php if (!empty($providers['saved'])) : ?>
        <div class="notice notice-success inline"><p><?php esc_html_e('Configurações de frete salvas com sucesso.', 'hng-commerce'); ?></p></div>
    <?php endif; ?>

    <div class="hng-header">
        <div class="hng-header-title">
            <h1><span class="dashicons dashicons-truck"></span> <?php esc_html_e('Configurações de Frete', 'hng-commerce'); ?></h1>
            <p class="description"><?php esc_html_e('Gerencie as formas de envio e integre com transportadoras.', 'hng-commerce'); ?></p>
        </div>
        <div class="hng-header-actions">
            <button type="submit" form="hng-shipping-form" class="button button-primary hng-btn hng-btn-primary">
                <span class="dashicons dashicons-saved"></span> <?php esc_html_e('Salvar Alterações', 'hng-commerce'); ?>
            </button>
        </div>
    </div>

    <form method="post" action="" id="hng-shipping-form">
        <?php wp_nonce_field('hng_shipping_settings', 'shipping_nonce'); ?>
        
        <!-- Configurações Gerais -->
        <div class="hng-card hng-mb-4">
            <div class="hng-card-header">
                <h2 class="hng-card-title"><span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e('Padrões de Envio', 'hng-commerce'); ?></h2>
            </div>
            <div class="hng-grid hng-gap-4" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <div class="hng-field">
                    <label><?php esc_html_e('Peso padrão (kg)', 'hng-commerce'); ?></label>
                    <input type="number" name="general[default_weight]" value="<?php echo esc_attr($providers['general']['default_weight'] ?? 0.3); ?>" step="0.001" min="0" class="hng-input" />
                </div>
                <div class="hng-field">
                    <label><?php esc_html_e('Comprimento (cm)', 'hng-commerce'); ?></label>
                    <input type="number" name="general[default_length]" value="<?php echo esc_attr($providers['general']['default_length'] ?? 16); ?>" step="0.1" min="0" class="hng-input" />
                </div>
                <div class="hng-field">
                    <label><?php esc_html_e('Largura (cm)', 'hng-commerce'); ?></label>
                    <input type="number" name="general[default_width]" value="<?php echo esc_attr($providers['general']['default_width'] ?? 11); ?>" step="0.1" min="0" class="hng-input" />
                </div>
                <div class="hng-field">
                    <label><?php esc_html_e('Altura (cm)', 'hng-commerce'); ?></label>
                    <input type="number" name="general[default_height]" value="<?php echo esc_attr($providers['general']['default_height'] ?? 2); ?>" step="0.1" min="0" class="hng-input" />
                </div>
                <div class="hng-field" style="grid-column: 1 / -1;">
                    <label><?php esc_html_e('Frete grátis acima de (R$)', 'hng-commerce'); ?></label>
                    <input type="number" name="general[free_shipping_min]" value="<?php echo esc_attr($providers['general']['free_shipping_min'] ?? ''); ?>" step="0.01" min="0" class="hng-input" placeholder="Opcional" />
                    <p class="description"><?php esc_html_e('Deixe em branco para desativar frete grátis por valor mínimo.', 'hng-commerce'); ?></p>
                </div>
            </div>
        </div>

        <h2 class="hng-section-title" style="margin: 30px 0 20px; font-size: 1.2em; border-bottom: 2px solid #eee; padding-bottom: 10px;"><?php esc_html_e('Transportadoras Disponíveis', 'hng-commerce'); ?></h2>

        <div class="hng-gateways-grid">
            <?php 
            foreach ($provider_meta as $key => $meta): 
                $enabled = ($providers[$key]['enabled'] ?? 'no') === 'yes';
                $data = $providers[$key] ?? [];
            ?>
            <div class="hng-gateway-item <?php echo esc_attr( $enabled ? 'enabled' : '' ); ?>">
                <div class="gateway-header">
                    <div class="gateway-icon">
                        <span class="dashicons dashicons-<?php echo esc_attr($meta['icon']); ?>"></span>
                    </div>
                    <div class="gateway-name-section">
                        <div class="gateway-name-with-status">
                            <h3><?php echo esc_html($meta['name']); ?></h3>
                            <?php if ($enabled): ?>
                                <span class="hng-badge hng-badge-success"><?php esc_html_e('Ativo', 'hng-commerce'); ?></span>
                            <?php endif; ?>
                        </div>
                        <p class="gateway-category"><?php echo esc_html($meta['category']); ?></p>
                    </div>
                </div>

                <div class="gateway-description">
                    <p><?php echo esc_html($meta['desc']); ?></p>
                </div>

                <div class="gateway-actions">
                    <div class="action-toggle">
                        <label class="hng-switch">
                            <input type="checkbox" name="<?php echo esc_attr($key); ?>[enabled]" value="yes" <?php checked($enabled); ?>>
                            <span class="hng-switch-slider"></span>
                        </label>
                        <span class="toggle-label"><?php echo esc_html( $enabled ? __('Ativado', 'hng-commerce') : __('Desativado', 'hng-commerce') ); ?></span>
                    </div>
                    <div class="action-buttons">
                        <button type="button" class="button button-secondary hng-toggle-config" title="<?php esc_attr_e('Configurar', 'hng-commerce'); ?>">
                            <span class="dashicons dashicons-admin-settings" style="margin:0;"></span> <?php esc_html_e('Configurar', 'hng-commerce'); ?>
                        </button>
                    </div>
                </div>

                <div class="hng-gateway-config-wrapper" style="display:none; padding: 20px; background: #fafafa; border-top: 1px solid #eee;">
                    <?php if ($key === 'melhorenvio'): ?>
                        <div class="hng-field hng-mb-4">
                            <label class="hng-switch" style="display:flex; align-items:center; gap:10px; width:auto; height:auto;">
                                <input type="checkbox" name="melhorenvio[sandbox]" value="yes" <?php checked('yes', $data['sandbox'] ?? 'yes'); ?> style="margin:0 !important; width:auto !important;">
                                <span><?php esc_html_e('Modo Sandbox (Ambiente de Testes)', 'hng-commerce'); ?></span>
                            </label>
                        </div>
                    <?php endif; ?>

                    <div class="hng-field">
                        <label><?php esc_html_e('CEP de Origem', 'hng-commerce'); ?></label>
                        <input type="text" name="<?php echo esc_attr($key); ?>[origin_zipcode]" value="<?php echo esc_attr($data['origin_zipcode'] ?? ''); ?>" class="hng-input" placeholder="Ex: 12345-678" />
                    </div>

                    <?php if ($key === 'correios'): ?>
                        <div class="hng-notice hng-notice-info" style="padding: 12px; background: #e7f3ff; border-left: 4px solid #2271b1; margin-bottom: 15px; border-radius: 4px;">
                            <strong><?php esc_html_e('Contrato SIGEP Web', 'hng-commerce'); ?></strong><br>
                            <small><?php esc_html_e('Para geração automática de etiquetas, preencha os dados do contrato. Sem contrato, apenas cotação estará disponível.', 'hng-commerce'); ?></small>
                        </div>
                        <div class="hng-field">
                            <label><?php esc_html_e('Código Administrativo', 'hng-commerce'); ?> <span style="color: #999;">(Contrato)</span></label>
                            <input type="text" name="correios[codigo_empresa]" value="<?php echo esc_attr($data['codigo_empresa'] ?? ''); ?>" class="hng-input" placeholder="Ex: 08082650" />
                            <p class="description"><?php esc_html_e('Código do contrato com os Correios.', 'hng-commerce'); ?></p>
                        </div>
                        <div class="hng-field">
                            <label><?php esc_html_e('Cartão de Postagem', 'hng-commerce'); ?></label>
                            <input type="text" name="correios[cartao_postagem]" value="<?php echo esc_attr($data['cartao_postagem'] ?? ''); ?>" class="hng-input" placeholder="Ex: 0067599079" />
                            <p class="description"><?php esc_html_e('Número do cartão de postagem vinculado ao contrato.', 'hng-commerce'); ?></p>
                        </div>
                        <div class="hng-field">
                            <label><?php esc_html_e('CNPJ do Remetente', 'hng-commerce'); ?></label>
                            <input type="text" name="correios[cnpj]" value="<?php echo esc_attr($data['cnpj'] ?? ''); ?>" class="hng-input" placeholder="00.000.000/0001-00" />
                        </div>
                        <div class="hng-field">
                            <label><?php esc_html_e('Usuário SIGEP', 'hng-commerce'); ?></label>
                            <input type="text" name="correios[usuario_sigep]" value="<?php echo esc_attr($data['usuario_sigep'] ?? ''); ?>" class="hng-input" />
                            <p class="description"><?php esc_html_e('Login de acesso ao SIGEP Web.', 'hng-commerce'); ?></p>
                        </div>
                        <div class="hng-field">
                            <label><?php esc_html_e('Senha SIGEP', 'hng-commerce'); ?></label>
                            <input type="password" name="correios[senha]" value="<?php echo esc_attr($data['senha'] ?? ''); ?>" class="hng-input" />
                        </div>
                        <div class="hng-field hng-mb-4">
                            <label class="hng-switch" style="display:flex; align-items:center; gap:10px; width:auto; height:auto;">
                                <input type="checkbox" name="correios[homologacao]" value="yes" <?php checked('yes', $data['homologacao'] ?? 'yes'); ?> style="margin:0 !important; width:auto !important;">
                                <span><?php esc_html_e('Ambiente de Homologação (Testes)', 'hng-commerce'); ?></span>
                            </label>
                            <p class="description" style="margin-top:5px;"><?php esc_html_e('Desmarque para usar ambiente de produção.', 'hng-commerce'); ?></p>
                        </div>
                        <hr style="margin: 20px 0; border: 0; border-top: 1px dashed #ddd;">
                        <h4 style="margin: 0 0 15px; font-size: 0.95em;"><?php esc_html_e('Dados do Remetente (para etiquetas)', 'hng-commerce'); ?></h4>
                        <div class="hng-field">
                            <label><?php esc_html_e('Nome/Razão Social', 'hng-commerce'); ?></label>
                            <input type="text" name="correios[remetente_nome]" value="<?php echo esc_attr($data['remetente_nome'] ?? get_bloginfo('name')); ?>" class="hng-input" />
                        </div>
                        <div class="hng-field-row" style="display:grid; grid-template-columns: 2fr 1fr; gap:10px;">
                            <div class="hng-field">
                                <label><?php esc_html_e('Logradouro', 'hng-commerce'); ?></label>
                                <input type="text" name="correios[remetente_logradouro]" value="<?php echo esc_attr($data['remetente_logradouro'] ?? ''); ?>" class="hng-input" />
                            </div>
                            <div class="hng-field">
                                <label><?php esc_html_e('Número', 'hng-commerce'); ?></label>
                                <input type="text" name="correios[remetente_numero]" value="<?php echo esc_attr($data['remetente_numero'] ?? ''); ?>" class="hng-input" />
                            </div>
                        </div>
                        <div class="hng-field-row" style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                            <div class="hng-field">
                                <label><?php esc_html_e('Complemento', 'hng-commerce'); ?></label>
                                <input type="text" name="correios[remetente_complemento]" value="<?php echo esc_attr($data['remetente_complemento'] ?? ''); ?>" class="hng-input" />
                            </div>
                            <div class="hng-field">
                                <label><?php esc_html_e('Bairro', 'hng-commerce'); ?></label>
                                <input type="text" name="correios[remetente_bairro]" value="<?php echo esc_attr($data['remetente_bairro'] ?? ''); ?>" class="hng-input" />
                            </div>
                        </div>
                        <div class="hng-field-row" style="display:grid; grid-template-columns: 2fr 1fr; gap:10px;">
                            <div class="hng-field">
                                <label><?php esc_html_e('Cidade', 'hng-commerce'); ?></label>
                                <input type="text" name="correios[remetente_cidade]" value="<?php echo esc_attr($data['remetente_cidade'] ?? ''); ?>" class="hng-input" />
                            </div>
                            <div class="hng-field">
                                <label><?php esc_html_e('UF', 'hng-commerce'); ?></label>
                                <input type="text" name="correios[remetente_uf]" value="<?php echo esc_attr($data['remetente_uf'] ?? ''); ?>" class="hng-input" maxlength="2" style="text-transform:uppercase;" />
                            </div>
                        </div>
                        <div class="hng-field">
                            <label><?php esc_html_e('Telefone', 'hng-commerce'); ?></label>
                            <input type="text" name="correios[remetente_telefone]" value="<?php echo esc_attr($data['remetente_telefone'] ?? ''); ?>" class="hng-input" placeholder="(00) 00000-0000" />
                        </div>
                    <?php endif; ?>

                    <?php if ($key === 'jadlog'): ?>
                        <div class="hng-field">
                            <label><?php esc_html_e('CNPJ', 'hng-commerce'); ?></label>
                            <input type="text" name="jadlog[cnpj]" value="<?php echo esc_attr($data['cnpj'] ?? ''); ?>" class="hng-input" />
                        </div>
                        <div class="hng-field">
                            <label><?php esc_html_e('Token', 'hng-commerce'); ?></label>
                            <input type="text" name="jadlog[token]" value="<?php echo esc_attr($data['token'] ?? ''); ?>" class="hng-input" />
                        </div>
                    <?php endif; ?>

                    <?php if (in_array($key, ['melhorenvio', 'loggi'])): ?>
                        <div class="hng-field">
                            <label><?php esc_html_e('Token de API', 'hng-commerce'); ?></label>
                            <input type="text" name="<?php echo esc_attr($key); ?>[token]" value="<?php echo esc_attr($data['token'] ?? ''); ?>" class="hng-input" />
                        </div>
                    <?php endif; ?>

                    <?php if ($key === 'total_express'): ?>
                        <div class="hng-field">
                            <label><?php esc_html_e('Client Code', 'hng-commerce'); ?></label>
                            <input type="text" name="total_express[client_code]" value="<?php echo esc_attr($data['client_code'] ?? ''); ?>" class="hng-input" />
                        </div>
                        <div class="hng-field">
                            <label><?php esc_html_e('API Key', 'hng-commerce'); ?></label>
                            <input type="text" name="total_express[api_key]" value="<?php echo esc_attr($data['api_key'] ?? ''); ?>" class="hng-input" />
                        </div>
                    <?php endif; ?>

                    <div class="hng-field hng-mt-4">
                        <label><?php esc_html_e('Serviços Habilitados (IDs/Códigos)', 'hng-commerce'); ?></label>
                        <input type="text" name="<?php echo esc_attr($key); ?>[services]" value="<?php echo esc_attr(implode(',', $data['services'] ?? [])); ?>" class="hng-input" />
                        <p class="description"><?php esc_html_e('Separe por vírgulas. Ex: 04014,04510', 'hng-commerce'); ?></p>
                    </div>

                    <div class="hng-field-row hng-mt-4" style="display:flex; gap:10px;">
                        <div class="hng-field" style="flex:1;">
                            <label><?php esc_html_e('Dias Extras', 'hng-commerce'); ?></label>
                            <input type="number" name="<?php echo esc_attr($key); ?>[extra_days]" value="<?php echo esc_attr($data['extra_days'] ?? 0); ?>" min="0" class="hng-input" />
                        </div>
                        <div class="hng-field" style="flex:1;">
                            <label><?php esc_html_e('Taxa Extra (R$)', 'hng-commerce'); ?></label>
                            <input type="number" name="<?php echo esc_attr($key); ?>[handling_fee]" value="<?php echo esc_attr($data['handling_fee'] ?? 0); ?>" min="0" step="0.01" class="hng-input" />
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php 
            // Transportadora Própria / Custom
            $custom_enabled = get_option('hng_shipping_custom_enabled', 'no') === 'yes';
            ?>
            <div class="hng-gateway-item hng-custom-shipping <?php echo esc_attr($custom_enabled ? 'enabled' : ''); ?>">
                <div class="gateway-header">
                    <div class="gateway-icon" style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">
                        <span class="dashicons dashicons-businessperson" style="color: #fff;"></span>
                    </div>
                    <div class="gateway-name-section">
                        <div class="gateway-name-with-status">
                            <h3><?php esc_html_e('Transportadora Própria', 'hng-commerce'); ?></h3>
                            <?php if ($custom_enabled): ?>
                                <span class="hng-badge hng-badge-success"><?php esc_html_e('Ativo', 'hng-commerce'); ?></span>
                            <?php endif; ?>
                        </div>
                        <p class="gateway-category"><?php esc_html_e('Serviço Próprio', 'hng-commerce'); ?></p>
                    </div>
                </div>

                <div class="gateway-description">
                    <p><?php esc_html_e('Configure seu próprio serviço de entrega com zonas de frete, valores por região e geração de etiquetas personalizadas.', 'hng-commerce'); ?></p>
                </div>

                <div class="gateway-actions">
                    <div class="action-toggle">
                        <label class="hng-switch">
                            <input type="checkbox" name="custom_shipping_enabled" value="yes" <?php checked($custom_enabled); ?> onchange="this.form.submit();">
                            <span class="hng-switch-slider"></span>
                        </label>
                        <span class="toggle-label"><?php echo esc_html($custom_enabled ? __('Ativado', 'hng-commerce') : __('Desativado', 'hng-commerce')); ?></span>
                    </div>
                    <div class="action-buttons">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=hng-shipping&tab=custom')); ?>" class="button button-secondary" title="<?php esc_attr_e('Configurar', 'hng-commerce'); ?>">
                            <span class="dashicons dashicons-admin-settings" style="margin:0;"></span> <?php esc_html_e('Configurar', 'hng-commerce'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<style>
/* CSS Styles mimicking Gateway Page */
.hng-shipping-page {
    font-family: 'Inter', -apple-system, system-ui, sans-serif;
    color: #1e293b;
}
.hng-header {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    border: 1px solid #e2e8f0;
}
.hng-header h1 {
    font-size: 1.5rem;
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0 0 5px;
    color: #0f172a;
}
.hng-header .dashicons {
    font-size: 1.2em;
    width: 1.2em;
    height: 1.2em;
    color: #6366f1;
}
.hng-gateways-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
}
.hng-gateway-item {
    background: #fff;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
    overflow: hidden;
}
.hng-gateway-item.enabled {
    border-color: #6366f1;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.1);
}
.hng-gateway-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(0,0,0,0.1);
}
.gateway-header {
    padding: 20px;
    display: flex;
    gap: 15px;
    border-bottom: 1px solid #f1f5f9;
}
.gateway-icon {
    width: 48px;
    height: 48px;
    background: #f8fafc;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6366f1;
}
.gateway-icon .dashicons {
    font-size: 24px;
    width: 24px;
    height: 24px;
}
.gateway-name-section h3 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
}
.gateway-name-with-status {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
    gap: 10px;
}
.gateway-category {
    margin: 5px 0 0;
    font-size: 0.8rem;
    color: #64748b;
    text-transform: uppercase;
    font-weight: 600;
}
.gateway-description {
    padding: 15px 20px;
    color: #475569;
    font-size: 0.9rem;
    line-height: 1.5;
}
.gateway-actions {
    padding: 15px 20px;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.action-toggle {
    display: flex;
    align-items: center;
    gap: 10px;
}
/* Switch */
.hng-switch {
    position: relative;
    display: inline-block;
    width: 44px;
    height: 24px;
}
.hng-switch input { opacity: 0; width: 0; height: 0; }
.hng-switch-slider {
    position: absolute;
    cursor: pointer;
    top: 0; left: 0; right: 0; bottom: 0;
    background-color: #cbd5e1;
    transition: .4s;
    border-radius: 34px;
}
.hng-switch-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}
.hng-switch input:checked + .hng-switch-slider {
    background-color: #6366f1;
}
.hng-switch input:checked + .hng-switch-slider:before {
    transform: translateX(20px);
}
.toggle-label {
    font-size: 0.85rem;
    font-weight: 500;
    color: #64748b;
}
.hng-badge {
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}
.hng-badge-success { background: #dcfce7; color: #166534; }
/* Form Styling */
.hng-field { margin-bottom: 15px; }
.hng-field label { display: block; margin-bottom: 5px; font-weight: 500; font-size: 0.9rem; }
.hng-input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 0.95rem;
}
.hng-input:focus { border-color: #6366f1; outline: 3px solid rgba(99, 102, 241, 0.1); }
</style>

<script>
jQuery(document).ready(function($) {
    // Config Toggle
    $('.hng-toggle-config').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $wrapper = $btn.closest('.hng-gateway-item').find('.hng-gateway-config-wrapper');
        
        $wrapper.slideToggle(200);
        $btn.toggleClass('active');
    });

    // Auto-enable when configuring
    $('.hng-gateway-config-wrapper input').on('change', function() {
        var $item = $(this).closest('.hng-gateway-item');
        var $checkbox = $item.find('.action-toggle input[type="checkbox"]');
        if (!$checkbox.is(':checked') && $(this).val()) {
            // Optional: Auto check enable box if user is typing credentials?
            // Maybe strict user control is better.
        }
    });
});
</script>
