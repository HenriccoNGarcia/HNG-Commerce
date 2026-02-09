<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**

 * Template do Setup Wizard

 * 

 * @package HNG_Commerce

 */



if (!defined('ABSPATH')) {

    exit;

}



$steps = $this->steps;

$current_step = $this->current_step;

$step_keys = array_keys($steps);

$current_index = array_search($current_step, $step_keys);



// Dados existentes

$store_data = HNG_Setup_Wizard::get_store_data();



// Estados brasileiros

$states = [

    'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas',

    'BA' => 'Bahia', 'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo',

    'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul',

    'MG' => 'Minas Gerais', 'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná',

    'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte',

    'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina',

    'SP' => 'São Paulo', 'SE' => 'Sergipe', 'TO' => 'Tocantins',

];

?>

<!DOCTYPE html>

<html <?php language_attributes(); ?> class="hng-wizard-page">

<head>

    <meta charset="<?php bloginfo('charset'); ?>">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?php esc_html_e('Configuração HNG Commerce', 'hng-commerce'); ?></title>

    <?php wp_head(); ?>

</head>

<body class="hng-wizard-body hng-wizard-page">

    <div class="hng-wizard-wrapper">

        <!-- Header -->

        <header class="hng-wizard-header">

            <div class="hng-wizard-logo">

                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">

                    <rect width="24" height="24" rx="6" fill="#6366f1"/>

                    <path d="M7 8h10M7 12h10M7 16h6" stroke="white" stroke-width="2" stroke-linecap="round"/>

                </svg>

                <span>HNG Commerce</span>

            </div>

            <button type="button" class="hng-wizard-skip" id="skipWizard">

                <?php esc_html_e('Pular configuração', 'hng-commerce'); ?>

            </button>

        </header>



        <!-- Progress Steps -->

        <nav class="hng-wizard-steps">

            <?php foreach ($steps as $key => $step) : 

                $index = array_search($key, $step_keys);

                $is_active = ($key === $current_step);

                $is_completed = ($index < $current_index);

                $class = $is_active ? 'active' : ($is_completed ? 'completed' : '');

            ?>

            <div class="hng-wizard-step <?php echo esc_attr($class); ?>" data-step="<?php echo esc_attr($key); ?>">

                <div class="step-icon">

                    <?php if ($is_completed) : ?>

                        <span class="dashicons dashicons-yes"></span>

                    <?php else : ?>

                        <span class="dashicons <?php echo esc_attr($step['icon']); ?>"></span>

                    <?php endif; ?>

                </div>

                <span class="step-name"><?php echo esc_html($step['name']); ?></span>

            </div>

            <?php endforeach; ?>

        </nav>



        <!-- Content -->

        <main class="hng-wizard-content">

            <form id="wizardForm" class="hng-wizard-form">

                <input type="hidden" name="step" value="<?php echo esc_attr($current_step); ?>">

                

                <!-- Step: Welcome -->

                <div class="hng-wizard-panel" data-panel="welcome" <?php echo $current_step !== 'welcome' ? 'style="display:none"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Hardcoded safe string ?>>

                    <div class="panel-icon">

                        <span class="dashicons dashicons-welcome-learn-more"></span>

                    </div>

                    <h1><?php esc_html_e('Bem-vindo ao HNG Commerce!', 'hng-commerce'); ?></h1>

                    <p class="panel-description">

                        <?php esc_html_e('Obrigado por escolher o HNG Commerce para sua loja virtual. Este assistente vai ajudá-lo a configurar as opções essenciais em poucos minutos.', 'hng-commerce'); ?>

                    </p>

                    

                    <div class="feature-list">

                        <div class="feature-item">

                            <span class="dashicons dashicons-yes-alt"></span>

                            <span><?php esc_html_e('Configurar dados da sua loja', 'hng-commerce'); ?></span>

                        </div>

                        <div class="feature-item">

                            <span class="dashicons dashicons-yes-alt"></span>

                            <span><?php esc_html_e('Ativar métodos de pagamento', 'hng-commerce'); ?></span>

                        </div>

                        <div class="feature-item">

                            <span class="dashicons dashicons-yes-alt"></span>

                            <span><?php esc_html_e('Configurar opções de frete', 'hng-commerce'); ?></span>

                        </div>

                    </div>

                    

                    <p class="panel-note">

                        <span class="dashicons dashicons-info"></span>

                        <?php esc_html_e('Você pode alterar todas essas configurações posteriormente nas opções do plugin.', 'hng-commerce'); ?>

                    </p>

                </div>



                <!-- Step: Store -->

                <div class="hng-wizard-panel" data-panel="store" <?php echo $current_step !== 'store' ? 'style="display:none"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Hardcoded safe string ?>>

                    <h1><?php esc_html_e('Dados da Sua Loja', 'hng-commerce'); ?></h1>

                    <p class="panel-description">

                        <?php esc_html_e('Informe os dados básicos da sua loja. Essas informações serão usadas em notas fiscais, etiquetas de envio e comunicações com clientes.', 'hng-commerce'); ?>

                    </p>

                    

                    <div class="form-section">

                        <h3><?php esc_html_e('Informações Básicas', 'hng-commerce'); ?></h3>

                        

                        <div class="form-row">

                            <div class="form-group form-group-lg">

                                <label for="store_name"><?php esc_html_e('Nome da Loja / Razão Social', 'hng-commerce'); ?> <span class="required">*</span></label>

                                <input type="text" id="store_name" name="data[store_name]" value="<?php echo esc_attr($store_data['store_name']); ?>" required>

                            </div>

                        </div>

                        

                        <div class="form-row form-row-2">

                            <div class="form-group">

                                <label for="store_email"><?php esc_html_e('E-mail', 'hng-commerce'); ?></label>

                                <input type="email" id="store_email" name="data[store_email]" value="<?php echo esc_attr($store_data['store_email']); ?>">

                            </div>

                            <div class="form-group">

                                <label for="store_phone"><?php esc_html_e('Telefone', 'hng-commerce'); ?></label>

                                <input type="text" id="store_phone" name="data[store_phone]" value="<?php echo esc_attr($store_data['store_phone']); ?>" placeholder="(00) 00000-0000">

                            </div>

                        </div>

                        

                        <div class="form-row">

                            <div class="form-group">

                                <label for="store_cnpj"><?php esc_html_e('CNPJ / CPF', 'hng-commerce'); ?></label>

                                <input type="text" id="store_cnpj" name="data[store_cnpj]" value="<?php echo esc_attr($store_data['store_cnpj']); ?>" placeholder="00.000.000/0001-00">

                            </div>

                        </div>

                    </div>

                    

                    <div class="form-section">

                        <h3><?php esc_html_e('Endereço', 'hng-commerce'); ?></h3>

                        

                        <div class="form-row form-row-2">

                            <div class="form-group">

                                <label for="store_zipcode"><?php esc_html_e('CEP', 'hng-commerce'); ?></label>

                                <input type="text" id="store_zipcode" name="data[store_zipcode]" value="<?php echo esc_attr($store_data['store_zipcode']); ?>" placeholder="00000-000">

                                <button type="button" class="btn-search-cep" id="searchCep"><?php esc_html_e('Buscar', 'hng-commerce'); ?></button>

                                <p class="description"><?php esc_html_e('Opcional - preencha se você tiver loja física.', 'hng-commerce'); ?></p>

                            </div>

                            <div class="form-group"></div>

                        </div>

                        

                        <div class="form-row form-row-3">

                            <div class="form-group form-group-lg">

                                <label for="store_address"><?php esc_html_e('Logradouro', 'hng-commerce'); ?></label>

                                <input type="text" id="store_address" name="data[store_address]" value="<?php echo esc_attr($store_data['store_address']); ?>">

                            </div>

                            <div class="form-group form-group-sm">

                                <label for="store_number"><?php esc_html_e('Número', 'hng-commerce'); ?></label>

                                <input type="text" id="store_number" name="data[store_number]" value="<?php echo esc_attr($store_data['store_number']); ?>">

                            </div>

                        </div>

                        

                        <div class="form-row form-row-3">

                            <div class="form-group">

                                <label for="store_district"><?php esc_html_e('Bairro', 'hng-commerce'); ?></label>

                                <input type="text" id="store_district" name="data[store_district]" value="<?php echo esc_attr($store_data['store_district']); ?>">

                            </div>

                            <div class="form-group">

                                <label for="store_city"><?php esc_html_e('Cidade', 'hng-commerce'); ?></label>

                                <input type="text" id="store_city" name="data[store_city]" value="<?php echo esc_attr($store_data['store_city']); ?>">

                            </div>

                            <div class="form-group form-group-sm">

                                <label for="store_state"><?php esc_html_e('UF', 'hng-commerce'); ?></label>

                                <select id="store_state" name="data[store_state]">

                                    <option value=""><?php esc_html_e('Selecione', 'hng-commerce'); ?></option>

                                    <?php foreach ($states as $uf => $name) : ?>

                                        <option value="<?php echo esc_attr($uf); ?>" <?php selected($store_data['store_state'], $uf); ?>><?php echo esc_html($uf); ?></option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                        </div>

                    </div>

                </div>



                <!-- Step: Gateways -->
                <div class="hng-wizard-panel" data-panel="gateways" <?php echo $current_step !== 'gateways' ? 'style="display:none"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Hardcoded safe string ?>>
                    <h1><?php esc_html_e('Gateways de Pagamento', 'hng-commerce'); ?></h1>
                    <p class="panel-description">
                        <?php esc_html_e('Escolha como deseja receber pagamentos na sua loja.', 'hng-commerce'); ?>
                    </p>

                    <div class="gateway-options">
                        <!-- Option 1: Gateways nativos HNG -->
                        <label class="gateway-option-card">
                            <input type="radio"
                                   name="data[use_hng_gateways]"
                                   value="1"
                                   <?php checked(HNG_Setup_Wizard::is_using_hng_gateways(), true); ?>
                                   class="gateway-radio" />
                            <div class="card-content">
                                <div class="card-header">
                                    <strong><?php esc_html_e('Usar gateways nativos do HNG Commerce', 'hng-commerce'); ?></strong>
                                    <span class="recommended-badge"><?php esc_html_e('Nativo', 'hng-commerce'); ?></span>
                                </div>
                                <p class="card-description">
                                    <?php esc_html_e('Integração pronta com os principais gateways brasileiros (PIX, boleto e cartão) usando o motor avançado do HNG.', 'hng-commerce'); ?>
                                </p>
                                <ul class="feature-list">
                                    <li><?php esc_html_e('✓ Ativação imediata e configuração guiada', 'hng-commerce'); ?></li>
                                    <li><?php esc_html_e('✓ Cálculo e cobrança de taxas automáticas', 'hng-commerce'); ?></li>
                                    <li><?php esc_html_e('✓ Integração avançada e reconciliação', 'hng-commerce'); ?></li>
                                </ul>

                                <div class="terms-box" id="hng-terms-box">
                                    <label class="terms-label">
                                        <input type="checkbox" 
                                               id="hng_terms_accept" 
                                               name="data[hng_terms_accept]" 
                                               value="yes" 
                                               class="terms-checkbox" />
                                        <span>
                                            <?php esc_html_e('Declaro que aceito os termos de uso dos gateways nativos, as taxas envolvidas e estou ciente do modo de integração avançada.', 'hng-commerce'); ?>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </label>

                        <!-- Option 2: Gateways de terceiros -->
                        <label class="gateway-option-card">
                            <input type="radio"
                                   name="data[use_hng_gateways]"
                                   value="0"
                                   <?php checked(HNG_Setup_Wizard::is_using_hng_gateways(), false); ?>
                                   class="gateway-radio" />
                            <div class="card-content">
                                <div class="card-header">
                                    <strong><?php esc_html_e('Usar plugin de terceiros', 'hng-commerce'); ?></strong>
                                    <span class="compliance-badge"><?php esc_html_e('Livre escolha', 'hng-commerce'); ?></span>
                                </div>
                                <p class="card-description">
                                    <?php esc_html_e('Conectar sua própria solução de pagamentos através de um plugin externo. Nenhuma taxa do HNG é aplicada.', 'hng-commerce'); ?>
                                </p>
                                <ul class="feature-list">
                                    <li><?php esc_html_e('✓ Utilize qualquer gateway suportado por terceiros', 'hng-commerce'); ?></li>
                                    <li><?php esc_html_e('✓ Sem cobrança de taxas pelo HNG', 'hng-commerce'); ?></li>
                                    <li><?php esc_html_e('✓ Configuração e suporte feitos pelo fornecedor do plugin escolhido', 'hng-commerce'); ?></li>
                                </ul>
                            </div>
                        </label>
                    </div>

                    <div class="gateway-note info-box">
                        <span class="dashicons dashicons-lock"></span>
                        <div>
                            <strong><?php esc_html_e('Importante', 'hng-commerce'); ?></strong>
                            <p><?php esc_html_e('Se você optar pelos gateways nativos do HNG, é necessário aceitar os termos, as taxas aplicáveis e o modo de integração avançada. Se preferir um plugin de terceiros, nenhuma configuração de API do HNG será usada.', 'hng-commerce'); ?></p>
                        </div>
                    </div>

                    <style>
                        .gateway-options {
                            display: grid;
                            grid-template-columns: 1fr 1fr;
                            gap: 20px;
                            margin: 30px 0;
                        }

                        .gateway-option-card {
                            position: relative;
                            cursor: pointer;
                        }

                        .gateway-option-card input[type="radio"] {
                            position: absolute;
                            opacity: 0;
                        }

                        .gateway-option-card .card-content {
                            padding: 25px;
                            border: 2px solid #e5e7eb;
                            border-radius: 8px;
                            transition: all 0.3s ease;
                            background: #fff;
                        }

                        .gateway-option-card input[type="radio"]:checked + .card-content {
                            border-color: #6366f1;
                            background: #f0f4ff;
                            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
                        }

                        .card-header {
                            display: flex;
                            align-items: center;
                            gap: 10px;
                            margin-bottom: 15px;
                        }

                        .card-header strong {
                            font-size: 16px;
                        }

                        .recommended-badge, .compliance-badge {
                            display: inline-block;
                            padding: 4px 8px;
                            border-radius: 4px;
                            font-size: 12px;
                            font-weight: 500;
                        }

                        .recommended-badge {
                            background: #d4edda;
                            color: #155724;
                        }

                        .compliance-badge {
                            background: #e5e7eb;
                            color: #111827;
                        }

                        .card-description {
                            margin: 10px 0 15px 0;
                            font-size: 14px;
                            line-height: 1.5;
                            color: #6b7280;
                        }

                        .gateway-options .feature-list {
                            list-style: none;
                            padding: 0;
                            margin: 0;
                        }

                        .gateway-options .feature-list li {
                            padding: 5px 0;
                            font-size: 13px;
                            color: #4b5563;
                        }

                        .terms-box {
                            margin-top: 18px;
                            padding: 14px;
                            background: #f8fafc;
                            border: 1px dashed #cbd5e1;
                            border-radius: 6px;
                        }

                        .terms-label {
                            display: flex;
                            gap: 10px;
                            align-items: flex-start;
                            font-size: 13px;
                            color: #374151;
                        }

                        .terms-checkbox {
                            margin-top: 3px;
                        }

                        .info-box {
                            display: flex;
                            gap: 15px;
                            padding: 20px;
                            background: #f8fafc;
                            border-left: 4px solid #6366f1;
                            border-radius: 4px;
                            margin-top: 30px;
                        }

                        .info-box .dashicons {
                            color: #4f46e5;
                            flex-shrink: 0;
                        }

                        .info-box strong {
                            display: block;
                            margin-bottom: 5px;
                        }

                        .info-box p {
                            margin: 0;
                            font-size: 14px;
                            line-height: 1.5;
                            color: #6b7280;
                        }

                        @media (max-width: 768px) {
                            .gateway-options {
                                grid-template-columns: 1fr;
                            }
                        }
                    </style>

                    <script>
                        (function() {
                            const radios = document.querySelectorAll('.gateway-radio');
                            const termsBox = document.getElementById('hng-terms-box');
                            const termsCheckbox = document.getElementById('hng_terms_accept');
                            const gatewaysPanel = document.querySelector('.hng-wizard-panel[data-panel="gateways"]');

                            function toggleTerms() {
                                const useHng = document.querySelector('.gateway-radio[value="1"]')?.checked;
                                const panelVisible = gatewaysPanel && gatewaysPanel.style.display !== 'none';
                                const shouldRequire = !!useHng && !!panelVisible;

                                termsBox.style.display = shouldRequire ? 'block' : 'none';
                                termsCheckbox.required = shouldRequire;
                                termsCheckbox.disabled = !shouldRequire;

                                if (!shouldRequire) {
                                    termsCheckbox.checked = false;
                                }
                            }

                            radios.forEach(r => r.addEventListener('change', toggleTerms));
                            document.addEventListener('DOMContentLoaded', toggleTerms);
                            // Fallback for immediate execution when DOMContentLoaded já ocorreu
                            toggleTerms();
                        })();
                    </script>
                </div>



                <!-- Step: Products -->

                <?php 

                $options = get_option('hng_commerce_settings', []);

                $product_types = [

                    'simple' => [

                        'label' => __('Produto Simples', 'hng-commerce'),

                        'icon' => '📦',

                        'description' => __('Produto físico padrão', 'hng-commerce'),

                        'always_enabled' => true,

                    ],

                    'variable' => [

                        'label' => __('Produto Variável', 'hng-commerce'),

                        'icon' => '🔀',

                        'description' => __('Com tamanhos, cores, etc.', 'hng-commerce'),

                    ],

                    'digital' => [

                        'label' => __('Produto Digital', 'hng-commerce'),

                        'icon' => '💾',

                        'description' => __('Downloads, e-books, cursos', 'hng-commerce'),

                    ],

                    'subscription' => [

                        'label' => __('Assinatura', 'hng-commerce'),

                        'icon' => '🔄',

                        'description' => __('Pagamento recorrente mensal', 'hng-commerce'),

                    ],

                    'quote' => [

                        'label' => __('Orçamento', 'hng-commerce'),

                        'icon' => '📋',

                        'description' => __('Preço sob consulta', 'hng-commerce'),

                    ],

                    'appointment' => [

                        'label' => __('Agendamento', 'hng-commerce'),

                        'icon' => '📅',

                        'description' => __('Serviços com horário marcado', 'hng-commerce'),

                    ],

                ];

                ?>

                <div class="hng-wizard-panel" data-panel="products" <?php echo $current_step !== 'products' ? 'style="display:none"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Hardcoded safe string ?>>

                    <h1><?php esc_html_e('Tipos de Produto', 'hng-commerce'); ?></h1>

                    <p class="panel-description">

                        <?php esc_html_e('Selecione os tipos de produto que sua loja irá vender. Isso ativa recursos específicos para cada tipo.', 'hng-commerce'); ?>

                    </p>

                    

                    <div class="product-types-grid">

                        <?php foreach ($product_types as $type_key => $type) : 

                            $is_enabled = ($options['product_type_' . $type_key . '_enabled'] ?? ($type_key === 'simple' ? 'yes' : 'no')) === 'yes';

                            $always_enabled = !empty($type['always_enabled']);

                        ?>

                        <label class="product-type-card <?php echo esc_attr( $always_enabled ? 'always-enabled' : '' ); ?>">

                            <input type="checkbox" 

                                   name="data[product_types][]" 

                                   value="<?php echo esc_attr($type_key); ?>" 

                                   <?php checked($is_enabled); ?>

                                   <?php echo esc_attr( $always_enabled ? 'checked disabled' : '' ); ?>>

                            <?php if ($always_enabled) : ?>

                                <input type="hidden" name="data[product_types][]" value="<?php echo esc_attr($type_key); ?>">

                            <?php endif; ?>

                            <div class="card-content">

                                <div class="card-emoji"><?php echo esc_html($type['icon']); ?></div>

                                <div class="card-info">

                                    <strong><?php echo esc_html($type['label']); ?></strong>

                                    <span><?php echo esc_html($type['description']); ?></span>

                                </div>

                                <span class="card-check"></span>

                            </div>

                        </label>

                        <?php endforeach; ?>

                    </div>

                    

                    <div class="product-type-note">

                        <span class="dashicons dashicons-info"></span>

                        <p><?php esc_html_e('Você pode alterar isso depois em Configurações. O tipo "Produto Simples" está sempre ativo.', 'hng-commerce'); ?></p>

                    </div>

                </div>



                <!-- Step: Payments -->

                <div class="hng-wizard-panel" data-panel="payments" <?php echo $current_step !== 'payments' ? 'style="display:none"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Hardcoded safe string ?>>

                    <h1><?php esc_html_e('Métodos de Pagamento', 'hng-commerce'); ?></h1>

                    <p class="panel-description">

                        <?php esc_html_e('Selecione os métodos de pagamento que deseja oferecer. Você pode configurar detalhes adicionais depois.', 'hng-commerce'); ?>

                    </p>

                    

                    <div class="payment-methods-grid">

                        <!-- PIX -->

                        <label class="payment-method-card">

                            <input type="checkbox" name="data[gateways][]" value="pix" <?php checked(get_option('hng_gateway_pix_enabled'), 'yes'); ?>>

                            <div class="card-content">

                                <div class="card-icon pix">

                                    <svg viewBox="0 0 24 24" width="32" height="32" fill="currentColor">

                                        <path d="M12.5 2.1l9.4 9.4c.4.4.4 1 0 1.4l-9.4 9.4c-.4.4-1 .4-1.4 0l-9.4-9.4c-.4-.4-.4-1 0-1.4l9.4-9.4c.4-.4 1-.4 1.4 0zm-.7 2.1L4.1 12l7.7 7.8 7.8-7.8-7.8-7.8z"/>

                                    </svg>

                                </div>

                                <div class="card-info">

                                    <strong>PIX</strong>

                                    <span><?php esc_html_e('Pagamento instantâneo', 'hng-commerce'); ?></span>

                                </div>

                                <span class="card-check"></span>

                            </div>

                        </label>

                        

                        <!-- Boleto -->

                        <label class="payment-method-card">

                            <input type="checkbox" name="data[gateways][]" value="boleto" <?php checked(get_option('hng_gateway_boleto_enabled'), 'yes'); ?>>

                            <div class="card-content">

                                <div class="card-icon boleto">

                                    <span class="dashicons dashicons-media-text"></span>

                                </div>

                                <div class="card-info">

                                    <strong><?php esc_html_e('Boleto Bancário', 'hng-commerce'); ?></strong>

                                    <span><?php esc_html_e('Compensação em 1-3 dias', 'hng-commerce'); ?></span>

                                </div>

                                <span class="card-check"></span>

                            </div>

                        </label>

                        

                        <!-- Cartão -->

                        <label class="payment-method-card">

                            <input type="checkbox" name="data[gateways][]" value="credit_card" <?php checked(get_option('hng_gateway_credit_card_enabled'), 'yes'); ?>>

                            <div class="card-content">

                                <div class="card-icon credit">

                                    <span class="dashicons dashicons-credit-card"></span>

                                </div>

                                <div class="card-info">

                                    <strong><?php esc_html_e('Cartão de Crédito', 'hng-commerce'); ?></strong>

                                    <span><?php esc_html_e('Parcelamento disponível', 'hng-commerce'); ?></span>

                                </div>

                                <span class="card-check"></span>

                            </div>

                        </label>

                    </div>

                    

                    <div class="form-section pix-config" style="display:none;">

                        <h3><?php esc_html_e('Configuração do PIX', 'hng-commerce'); ?></h3>

                        

                        <div class="form-row form-row-2">

                            <div class="form-group">

                                <label for="pix_key_type"><?php esc_html_e('Tipo de Chave', 'hng-commerce'); ?></label>

                                <select id="pix_key_type" name="data[pix_key_type]">

                                    <option value="cpf"><?php esc_html_e('CPF', 'hng-commerce'); ?></option>

                                    <option value="cnpj"><?php esc_html_e('CNPJ', 'hng-commerce'); ?></option>

                                    <option value="email"><?php esc_html_e('E-mail', 'hng-commerce'); ?></option>

                                    <option value="phone"><?php esc_html_e('Telefone', 'hng-commerce'); ?></option>

                                    <option value="random"><?php esc_html_e('Chave Aleatória', 'hng-commerce'); ?></option>

                                </select>

                            </div>

                            <div class="form-group">

                                <label for="pix_key"><?php esc_html_e('Chave PIX', 'hng-commerce'); ?></label>

                                <input type="text" id="pix_key" name="data[pix_key]" value="<?php echo esc_attr(get_option('hng_gateway_pix_key')); ?>">

                            </div>

                        </div>

                        

                        <div class="form-row">

                            <div class="form-group">

                                <label for="pix_holder_name"><?php esc_html_e('Nome do Titular', 'hng-commerce'); ?></label>

                                <input type="text" id="pix_holder_name" name="data[pix_holder_name]" value="<?php echo esc_attr(get_option('hng_gateway_pix_holder_name', $store_data['store_name'])); ?>">

                            </div>

                        </div>

                    </div>

                    

                    <!-- Parcelamento PIX -->

                    <?php 

                    $pix_installment_enabled = ($options['pix_installment_enabled'] ?? 'no') === 'yes';

                    $pix_installment_max = $options['pix_installment_max'] ?? 6;

                    $pix_installment_min = $options['pix_installment_min_value'] ?? 100;

                    ?>

                    <div class="form-section pix-installment-section">

                        <h3><?php esc_html_e('Parcelamento via PIX', 'hng-commerce'); ?></h3>

                        <p class="section-description"><?php esc_html_e('Permita que clientes paguem em parcelas mensais usando PIX. Cada parcela gera um novo QR Code.', 'hng-commerce'); ?></p>

                        

                        <div class="form-row">

                            <div class="form-group">

                                <label class="toggle-switch">

                                    <input type="checkbox" 

                                           id="pix_installment_enabled" 

                                           name="data[pix_installment_enabled]" 

                                           value="yes" 

                                           <?php checked($pix_installment_enabled); ?>>

                                    <span class="toggle-slider"></span>

                                    <span class="toggle-label"><?php esc_html_e('Ativar parcelamento via PIX', 'hng-commerce'); ?></span>

                                </label>

                            </div>

                        </div>

                        

                        <div class="pix-installment-options" style="<?php echo esc_attr( $pix_installment_enabled ? '' : 'display:none;' ); ?>">

                            <div class="form-row form-row-2">

                                <div class="form-group">

                                    <label for="pix_installment_max"><?php esc_html_e('Máximo de Parcelas', 'hng-commerce'); ?></label>

                                    <select id="pix_installment_max" name="data[pix_installment_max]">

                                        <?php for ($i = 2; $i <= 12; $i++) : ?>

                                            <option value="<?php echo esc_attr($i); ?>" <?php selected($pix_installment_max, $i); ?>><?php echo esc_html($i); ?>x</option>

                                        <?php endfor; ?>

                                    </select>

                                </div>

                                <div class="form-group">

                                    <label for="pix_installment_min_value"><?php esc_html_e('Valor Mínimo por Parcela', 'hng-commerce'); ?></label>

                                    <input type="number" 

                                           id="pix_installment_min_value" 

                                           name="data[pix_installment_min_value]" 

                                           value="<?php echo esc_attr($pix_installment_min); ?>" 

                                           min="10" 

                                           step="10"

                                           placeholder="100">

                                    <p class="field-description"><?php esc_html_e('Valor mínimo de cada parcela em R$', 'hng-commerce'); ?></p>

                                </div>

                            </div>

                        </div>

                    </div>

                    

                    <div class="gateway-note">

                        <span class="dashicons dashicons-info"></span>

                        <p><?php esc_html_e('Para usar gateways como Asaas, PagSeguro ou Mercado Pago, configure as credenciais na página de Pagamentos após concluir este assistente.', 'hng-commerce'); ?></p>

                    </div>

                </div>



                <!-- Step: Shipping -->

                <div class="hng-wizard-panel" data-panel="shipping" <?php echo $current_step !== 'shipping' ? 'style="display:none"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Hardcoded safe string ?>>

                    <h1><?php esc_html_e('Configuração de Frete', 'hng-commerce'); ?></h1>

                    <p class="panel-description">

                        <?php esc_html_e('Selecione as transportadoras que deseja usar e informe o CEP de onde sairão os envios.', 'hng-commerce'); ?>

                    </p>

                    

                    <div class="form-section">

                        <div class="form-row">

                            <div class="form-group">

                                <label for="origin_zipcode"><?php esc_html_e('CEP de Origem (Envio)', 'hng-commerce'); ?></label>

                                <input type="text" id="origin_zipcode" name="data[origin_zipcode]" value="<?php echo esc_attr($store_data['store_zipcode'] ?: get_option('hng_shipping_correios_origin_zipcode')); ?>" placeholder="00000-000">

                                <p class="field-description"><?php esc_html_e('CEP de onde sairão os produtos para envio. Pode ser configurado depois.', 'hng-commerce'); ?></p>

                            </div>

                        </div>

                    </div>

                    

                    <div class="shipping-methods-grid">

                        <!-- Correios -->

                        <label class="shipping-method-card">

                            <input type="checkbox" name="data[methods][]" value="correios" <?php checked(get_option('hng_shipping_correios_enabled'), 'yes'); ?>>

                            <div class="card-content">

                                <div class="card-icon correios">

                                    <span class="dashicons dashicons-email-alt"></span>

                                </div>

                                <div class="card-info">

                                    <strong><?php esc_html_e('Correios', 'hng-commerce'); ?></strong>

                                    <span><?php esc_html_e('PAC, SEDEX e mais', 'hng-commerce'); ?></span>

                                </div>

                                <span class="card-badge recommended"><?php esc_html_e('Recomendado', 'hng-commerce'); ?></span>

                                <span class="card-check"></span>

                            </div>

                        </label>

                        

                        <!-- Jadlog -->

                        <label class="shipping-method-card">

                            <input type="checkbox" name="data[methods][]" value="jadlog" <?php checked(get_option('hng_shipping_jadlog_enabled'), 'yes'); ?>>

                            <div class="card-content">

                                <div class="card-icon jadlog">

                                    <span class="dashicons dashicons-car"></span>

                                </div>

                                <div class="card-info">

                                    <strong><?php esc_html_e('Jadlog', 'hng-commerce'); ?></strong>

                                    <span><?php esc_html_e('Requer contrato', 'hng-commerce'); ?></span>

                                </div>

                                <span class="card-check"></span>

                            </div>

                        </label>

                        

                        <!-- Melhor Envio -->

                        <label class="shipping-method-card">

                            <input type="checkbox" name="data[methods][]" value="melhorenvio" <?php checked(get_option('hng_shipping_melhorenvio_enabled'), 'yes'); ?>>

                            <div class="card-content">

                                <div class="card-icon melhorenvio">

                                    <span class="dashicons dashicons-cloud"></span>

                                </div>

                                <div class="card-info">

                                    <strong><?php esc_html_e('Melhor Envio', 'hng-commerce'); ?></strong>

                                    <span><?php esc_html_e('Múltiplas transportadoras', 'hng-commerce'); ?></span>

                                </div>

                                <span class="card-check"></span>

                            </div>

                        </label>

                    </div>

                    

                    <div class="shipping-note">

                        <span class="dashicons dashicons-info"></span>

                        <p><?php esc_html_e('Os Correios funcionam sem credenciais adicionais. Para Jadlog e Melhor Envio, configure os tokens na página de Frete.', 'hng-commerce'); ?></p>

                    </div>

                    

                    <!-- Botão de pular frete -->

                    <div class="skip-shipping-section">

                        <a href="<?php echo esc_url(add_query_arg('step', 'ready')); ?>" class="btn btn-link" id="skipShipping">

                            <span class="dashicons dashicons-arrow-right-alt"></span>

                            <?php esc_html_e('Minha loja não precisa de frete (produtos digitais ou serviços)', 'hng-commerce'); ?>

                        </a>

                    </div>

                </div>



                <!-- Step: Ready -->

                <div class="hng-wizard-panel" data-panel="ready" <?php echo $current_step !== 'ready' ? 'style="display:none"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Hardcoded safe string ?>>

                    <div class="panel-icon success">

                        <span class="dashicons dashicons-yes-alt"></span>

                    </div>

                    <h1><?php esc_html_e('Tudo Pronto!', 'hng-commerce'); ?></h1>

                    <p class="panel-description">

                        <?php esc_html_e('Parabéns! Sua loja está configurada e pronta para receber pedidos.', 'hng-commerce'); ?>

                    </p>

                    

                    <div class="ready-summary">

                        <div class="summary-item <?php echo esc_attr( HNG_Setup_Wizard::is_step_complete('store') ? 'complete' : 'incomplete' ); ?>">

                            <span class="dashicons <?php echo esc_attr( HNG_Setup_Wizard::is_step_complete('store') ? 'dashicons-yes' : 'dashicons-warning' ); ?>"></span>

                            <span><?php esc_html_e('Dados da loja', 'hng-commerce'); ?></span>

                        </div>

                        <div class="summary-item <?php echo esc_attr( HNG_Setup_Wizard::is_step_complete('products') ? 'complete' : 'incomplete' ); ?>">

                            <span class="dashicons <?php echo esc_attr( HNG_Setup_Wizard::is_step_complete('products') ? 'dashicons-yes' : 'dashicons-warning' ); ?>"></span>

                            <span><?php esc_html_e('Tipos de produto', 'hng-commerce'); ?></span>

                        </div>

                        <div class="summary-item <?php echo esc_attr( HNG_Setup_Wizard::is_step_complete('payments') ? 'complete' : 'incomplete' ); ?>">

                            <span class="dashicons <?php echo esc_attr( HNG_Setup_Wizard::is_step_complete('payments') ? 'dashicons-yes' : 'dashicons-warning' ); ?>"></span>

                            <span><?php esc_html_e('Pagamentos', 'hng-commerce'); ?></span>

                        </div>

                        <div class="summary-item <?php echo esc_attr( HNG_Setup_Wizard::is_step_complete('shipping') ? 'complete' : 'incomplete' ); ?>">

                            <span class="dashicons <?php echo esc_attr( HNG_Setup_Wizard::is_step_complete('shipping') ? 'dashicons-yes' : 'dashicons-warning' ); ?>"></span>

                            <span><?php esc_html_e('Frete', 'hng-commerce'); ?></span>

                        </div>

                    </div>

                    

                    <div class="next-steps">

                        <h3><?php esc_html_e('Próximos Passos', 'hng-commerce'); ?></h3>

                        <ul>

                            <li>

                                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=hng_product')); ?>">

                                    <span class="dashicons dashicons-plus-alt"></span>

                                    <?php esc_html_e('Adicionar seu primeiro produto', 'hng-commerce'); ?>

                                </a>

                            </li>

                            <li>

                                <a href="<?php echo esc_url(admin_url('admin.php?page=hng-payments')); ?>">

                                    <span class="dashicons dashicons-admin-settings"></span>

                                    <?php esc_html_e('Configurar gateways de pagamento', 'hng-commerce'); ?>

                                </a>

                            </li>

                            <li>

                                <a href="<?php echo esc_url(admin_url('admin.php?page=hng-shipping')); ?>">

                                    <span class="dashicons dashicons-admin-settings"></span>

                                    <?php esc_html_e('Ajustar configurações de frete', 'hng-commerce'); ?>

                                </a>

                            </li>

                        </ul>

                    </div>

                </div>

            </form>

        </main>



        <!-- Footer Navigation -->

        <footer class="hng-wizard-footer">

            <div class="footer-left">

                <?php if ($current_index > 0) : ?>

                    <a href="<?php echo esc_url(add_query_arg('step', $step_keys[$current_index - 1])); ?>" class="btn btn-secondary" id="prevStep">

                        <span class="dashicons dashicons-arrow-left-alt"></span>

                        <?php esc_html_e('Voltar', 'hng-commerce'); ?>

                    </a>

                <?php endif; ?>

            </div>

            <div class="footer-right">

                <?php if ($current_step === 'ready') : ?>

                    <button type="button" class="btn btn-primary btn-lg" id="completeWizard">

                        <?php esc_html_e('Ir para o Painel', 'hng-commerce'); ?>

                        <span class="dashicons dashicons-arrow-right-alt"></span>

                    </button>

                <?php elseif ($current_step === 'welcome') : ?>

                    <a href="<?php echo esc_url(add_query_arg('step', 'store')); ?>" class="btn btn-primary btn-lg">

                        <?php esc_html_e('Começar', 'hng-commerce'); ?>

                        <span class="dashicons dashicons-arrow-right-alt"></span>

                    </a>

                <?php else : ?>

                    <button type="submit" form="wizardForm" class="btn btn-primary btn-lg" id="nextStep">

                        <?php esc_html_e('Continuar', 'hng-commerce'); ?>

                        <span class="dashicons dashicons-arrow-right-alt"></span>

                    </button>

                <?php endif; ?>

            </div>

        </footer>

    </div>



    <?php wp_footer(); ?>

</body>

</html>

