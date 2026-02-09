<?php

/**

 * Email Manager - Gerenciamento de templates de email

 *

 * @package HNG_Commerce

 * @since 3.8.0

 */



if (!defined('ABSPATH')) {

    exit;

}



class HNG_Email_Manager {

    

    /**

     * Lista de todos os templates de email disponáÂ­veis

     */

    public static function get_email_types() {

        return [

            'customer_new_order' => [
                'name' => __('Novo Pedido - Cliente', 'hng-commerce'),
                'description' => __('Email enviado ao cliente quando um novo pedido é criado', 'hng-commerce'),
                'recipient' => 'customer',
                'variables' => [
                    '{customer_name}' => 'Nome do cliente',
                    '{order_number}' => 'Número do pedido',
                    '{order_date}' => 'Data do pedido',
                    '{order_total}' => 'Valor total',
                    '{payment_method}' => 'Forma de pagamento',
                    '{order_items}' => 'Lista de produtos',
                    '{shipping_address}' => 'Endereço de entrega',
                    '{order_link}' => 'Link para ver pedido',
                    '{site_name}' => 'Nome do site',
                    '{site_url}' => 'URL do site'
                ]
            ],

            'admin_new_order' => [

                'name' => __('Novo Pedido - Admin', 'hng-commerce'),

                'description' => __('Email enviado ao admin quando um novo pedido é recebido', 'hng-commerce'),

                'recipient' => 'admin',

                'variables' => [

                    '{order_number}' => 'NáÂumero do pedido',

                    '{order_date}' => 'Data do pedido',

                    '{order_total}' => 'Valor total',

                    '{commission}' => 'Comissáo HNG',

                    '{payment_method}' => 'Forma de pagamento',

                    '{customer_name}' => 'Nome do cliente',

                    '{customer_email}' => 'Email do cliente',

                    '{customer_phone}' => 'Telefone do cliente',

                    '{customer_cpf}' => 'CPF/CNPJ do cliente',

                    '{order_items}' => 'Lista de produtos',

                    '{shipping_address}' => 'Endereço de entrega'

                ]

            ],

            'customer_order_pending' => [

                'name' => __('Pedido Pendente - Cliente', 'hng-commerce'),

                'description' => __('Email enviado quando pedido está aguardando pagamento', 'hng-commerce'),

                'recipient' => 'customer',

                'variables' => [

                    '{customer_name}' => 'Nome do cliente',

                    '{order_number}' => 'NáÂumero do pedido',

                    '{order_total}' => 'Valor total',

                    '{payment_method}' => 'Forma de pagamento',

                    '{payment_link}' => 'Link para pagamento',

                    '{order_link}' => 'Link para ver pedido'

                ]

            ],

            'customer_order_processing' => [
                'name' => __('Pedido em Processamento - Cliente', 'hng-commerce'),
                'description' => __('Email enviado quando pagamento é confirmado', 'hng-commerce'),
                'recipient' => 'customer',
                'variables' => [
                    '{customer_name}' => 'Nome do cliente',
                    '{order_number}' => 'NáÂumero do pedido',
                    '{order_total}' => 'Valor total',
                    '{order_link}' => 'Link para ver pedido'
                ]
            ],

            'customer_order_preparing' => [

                'name' => __('Pedido em Preparação - Cliente', 'hng-commerce'),

                'description' => __('Email enviado quando pedido está sendo preparado', 'hng-commerce'),

                'recipient' => 'customer',

                'variables' => [

                    '{customer_name}' => 'Nome do cliente',

                    '{order_number}' => 'NáÂumero do pedido',

                    '{estimated_delivery}' => 'Previsáo de entrega',

                    '{order_link}' => 'Link para ver pedido'

                ]

            ],

            'customer_order_shipped' => [ // Added missing key for this array element

                'name' => __('Pedido Enviado - Cliente', 'hng-commerce'),

                'description' => __('Email enviado quando pedido áÂ© despachado', 'hng-commerce'),

                'recipient' => 'customer',

                'variables' => [

                    '{customer_name}' => 'Nome do cliente',

                    '{order_number}' => 'NáÂumero do pedido',

                    '{tracking_code}' => 'CáÂ³digo de rastreamento',

                    '{tracking_link}' => 'Link de rastreamento',

                    '{estimated_delivery}' => 'Previsáo de entrega',

                    '{order_link}' => 'Link para ver pedido'

                ]

            ],

            'customer_order_completed' => [ // Added missing key for this array element

                'name' => __('Pedido Concluído - Cliente', 'hng-commerce'),
                'description' => __('Email enviado quando pedido é entregue', 'hng-commerce'),
                'recipient' => 'customer',
                'variables' => [
                    '{customer_name}' => 'Nome do cliente',
                    '{order_number}' => 'Número do pedido',
                    '{order_total}' => 'Valor total',
                    '{order_link}' => 'Link para ver pedido',
                    '{review_link}' => 'Link para avaliar produtos'
                ]
            ],

            'customer_pix_installment' => [

                'name' => __('Parcela PIX Gerada - Cliente', 'hng-commerce'),

                'description' => __('Email enviado quando uma nova parcela PIX áÂ© gerada', 'hng-commerce'),

                'recipient' => 'customer',

                'variables' => [

                    '{customer_name}' => 'Nome do cliente',

                    '{order_number}' => 'NáÂumero do pedido',

                    '{installment_number}' => 'NáÂumero da parcela (ex: 1/12)',

                    '{installment_value}' => 'Valor da parcela',

                    '{due_date}' => 'Data de vencimento',

                    '{pix_qrcode}' => 'QR Code PIX',

                    '{pix_code}' => 'CáÂ³digo PIX copia e cola',

                    '{remaining_installments}' => 'Parcelas restantes',

                    '{order_link}' => 'Link para ver pedido'

                ]

            ],

            'customer_subscription_renewal' => [

                'name' => __('Renovaçáo de Assinatura - Cliente', 'hng-commerce'),

                'description' => __('Email enviado antes da renovaçáo de assinatura', 'hng-commerce'),

                'recipient' => 'customer',

                'variables' => [

                    // translators: %s = product name
                    '{customer_name}' => 'Nome do cliente',

                    '{subscription_id}' => 'ID da assinatura',

                    '{renewal_date}' => 'Data de renovação',

                    '{renewal_amount}' => 'Valor da renovação',

                    '{payment_method}' => 'Forma de pagamento',

                    '{manage_link}' => 'Link para gerenciar assinatura'

                ]

            ],

            // EMAILS DE ORÇAMENTO

            'quote_request' => [

                'name' => __('Pedido de Orçamento - Cliente', 'hng-commerce'),

                'description' => __('Email enviado ao cliente quando solicita um orçamento', 'hng-commerce'),

                'recipient' => 'customer',

                'variables' => [

                    '{customer_name}' => 'Nome do cliente',

                    '{quote_id}' => 'ID do orçamento',

                    '{quote_date}' => 'Data da solicitação',

                    '{products}' => 'Lista de produtos',

                    '{quote_link}' => 'Link para acompanhar orçamento',

                    '{site_name}' => 'Nome do site',

                    '{site_url}' => 'URL do site'

                ]

            ],

            'quote_admin_new' => [

                'name' => __('Novo Pedido de Orçamento - Admin', 'hng-commerce'),

                'description' => __('Email enviado ao admin quando um novo orçamento é solicitado', 'hng-commerce'),

                'recipient' => 'admin',

                'variables' => [

                    '{customer_name}' => 'Nome do cliente',

                    '{customer_email}' => 'Email do cliente',

                    '{customer_phone}' => 'Telefone do cliente',

                    '{quote_id}' => 'ID do orçamento',

                    '{quote_date}' => 'Data da solicitação',

                    '{products}' => 'Lista de produtos',

                    '{admin_link}' => 'Link para o painel admin',

                    '{site_name}' => 'Nome do site'

                ]

            ],

            'quote_approved' => [

                'name' => __('Orçamento Aprovado - Cliente', 'hng-commerce'),

                'description' => __('Email enviado ao cliente quando o orçamento é aprovado pelo admin', 'hng-commerce'),

                'recipient' => 'customer',

                'variables' => [

                    '{customer_name}' => 'Nome do cliente',

                    '{quote_id}' => 'ID do orçamento',

                    '{approved_price}' => 'Preço aprovado',

                    '{approved_shipping}' => 'Frete aprovado',

                    '{total}' => 'Total aprovado',

                    '{approval_notes}' => 'Observações do admin',

                    '{payment_link}' => 'Link para pagamento',

                    '{quote_link}' => 'Link para visualizar orçamento',

                    '{site_name}' => 'Nome do site'

                ]

            ],

            'quote_message' => [

                'name' => __('Nova Mensagem no Orçamento - Cliente', 'hng-commerce'),

                'description' => __('Email enviado ao cliente quando há nova mensagem do admin no chat', 'hng-commerce'),

                'recipient' => 'customer',

                'variables' => [

                    '{customer_name}' => 'Nome do cliente',

                    '{quote_id}' => 'ID do orçamento',

                    '{message}' => 'Mensagem do admin',

                    '{quote_link}' => 'Link para responder',

                    '{site_name}' => 'Nome do site'

                ]

            ]

        ];

    }

    

    /**

     * Pega template customizado ou padráo

     */

    public static function get_template($email_type) {

        $custom = get_option('hng_email_template_' . $email_type, '');

        

        if (!empty($custom)) {

            return $custom;

        }

        

        return self::get_default_template($email_type);

    }

    

    /**

     * Template padráo baseado nas configuraçÁµes globais

     */

    public static function get_default_template($email_type) {

        $logo = get_option('hng_email_logo', '');

        $header_color = get_option('hng_email_header_color', '#3498db');

        $button_color = get_option('hng_email_button_color', '#27ae60');

        

        $header = self::get_email_header($logo, $header_color);

        $footer = self::get_email_footer();

        

        $content = self::get_default_content($email_type);

        

        return $header . $content . $footer;

    }

    

    /**

     * Cabeçalho padráo do email

     */

    private static function get_email_header($logo = '', $header_color = '#3498db') {

        ob_start();

        ?>

        <!DOCTYPE html>

        <html>

        <head>

            <meta charset="UTF-8">

            <meta name="viewport" content="width=device-width, initial-scale=1.0">

            <style>

                body {

                    margin: 0;

                    padding: 0;

                    font-family: Arial, sans-serif;

                    background-color: #f4f4f4;

                    color: #333;

                }

                .email-container {

                    max-width: 600px;

                    margin: 20px auto;

                    background: #ffffff;

                    border-radius: 8px;

                    overflow: hidden;

                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);

                }

                .email-header {

                    background-color: <?php echo esc_html(esc_attr($header_color)); ?>;

                    padding: 30px 20px;

                    text-align: center;

                }

                .email-header img {

                    max-width: 200px;

                    height: auto;

                }

                .email-body {

                    padding: 30px 20px;

                }

                .order-details {

                    background: #f9f9f9;

                    padding: 20px;

                    border-radius: 5px;

                    margin: 20px 0;

                }

                .order-items {

                    width: 100%;

                    border-collapse: collapse;

                    margin: 20px 0;

                }

                .order-items th {

                    background: #f4f4f4;

                    padding: 12px;

                    text-align: left;

                    border-bottom: 2px solid #ddd;

                }

                .order-items td {

                    padding: 10px 12px;

                    border-bottom: 1px solid #eee;

                }

                .order-total {

                    font-size: 1.2em;

                    color: <?php echo esc_html(esc_attr($header_color)); ?>;

                }

                .button {

                    display: inline-block;

                    padding: 12px 30px;

                    background: <?php echo esc_attr(get_option('hng_email_button_color', '#27ae60')); ?>;

                    color: #ffffff !important;

                    text-decoration: none;

                    border-radius: 5px;

                    margin: 10px 0;

                    font-weight: bold;

                }

                .email-footer {

                    background: #333;

                    color: #fff;

                    padding: 20px;

                    text-align: center;

                    font-size: 12px;

                }

                .email-footer a {

                    color: #fff;

                    text-decoration: none;

                }

            </style>

        </head>

        <body>

            <div class="email-container">

                <div class="email-header">

                    <?php if (!empty($logo)): ?>

                        <img src="<?php echo esc_html(esc_url($logo)); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">

                    <?php else: ?>

                        <h1 style="color: white; margin: 0;"><?php echo esc_html(get_bloginfo('name')); ?></h1>

                    <?php endif; ?>

                </div>

                <div class="email-body">

        <?php

        return ob_get_clean();

    }

    

    /**

     * RodapáÂ© padráo do email

     */

    private static function get_email_footer() {

        $footer_text = get_option('hng_email_footer_text', '');

        if (empty($footer_text)) {

            // translators: %s = email template name
            $footer_text = sprintf(esc_html__('Á‚Â© %1$s %2$s - Todos os direitos reservados.', 'hng-commerce'),

                gmdate('Y'),

                get_bloginfo('name')

            );

        }

        

        ob_start();

        ?>

                </div>

                <div class="email-footer">

                    <p><?php echo esc_html(wp_kses_post($footer_text)); ?></p>

                    <p><a href="{site_url}"><?php echo esc_html(get_bloginfo('name')); ?></a></p>

                </div>

            </div>

        </body>

        </html>

        <?php

        return ob_get_clean();

    }

    

    /**

     * ConteáÂudo padráo por tipo de email

     */

    private static function get_default_content($email_type) {

        switch ($email_type) {

            case 'customer_new_order':

                return '<h2>OláÂ¡, {customer_name}!</h2>

                        <p>Obrigado por fazer seu pedido em nossa loja. Recebemos seu pedido e ele estáÂ¡ sendo processado.</p>

                        <div class="order-details">

                            <h3>Detalhes do Pedido</h3>

                            <p><strong>Número do Pedido:</strong> {order_number}<br>

                            <strong>Data:</strong> {order_date}<br>

                            <strong>Total:</strong> {order_total}<br>

                            <strong>Forma de Pagamento:</strong> {payment_method}</p>

                        </div>

                        {order_items}

                        <p style="text-align: center;">

                            <a href="{order_link}" class="button">Ver Detalhes do Pedido</a>

                        </p>';

            

            case 'customer_pix_installment':

                return '<h2>Olá, {customer_name}!</h2>

                        <p>Uma nova parcela do seu pedido #{order_number} está disponível para pagamento.</p>

                        <div class="order-details">

                            <h3>Informações da Parcela</h3>

                            <p><strong>Parcela:</strong> {installment_number}<br>

                            <strong>Valor:</strong> {installment_value}<br>

                            <strong>Vencimento:</strong> {due_date}<br>

                            <strong>Parcelas Restantes:</strong> {remaining_installments}</p>

                        </div>

                        <div style="text-align: center; padding: 20px;">

                            {pix_qrcode}

                            <p><small>Código PIX: {pix_code}</small></p>

                        </div>

                        <p style="text-align: center;">

                            <a href="{order_link}" class="button">Ver Detalhes do Pedido</a>

                        </p>';

            // EMAILS DE ORÇAMENTO
            
            case 'quote_request':
                return '<h2>Olá, {customer_name}!</h2>
                        <p>Recebemos seu <strong>pedido de orçamento</strong> com sucesso! Nossa equipe irá analisar sua solicitação e entrar em contato em breve com uma proposta personalizada.</p>
                        <div class="order-details">
                            <h3>Informações do Orçamento</h3>
                            <p><strong>ID:</strong> #{quote_id}<br>
                            <strong>Data:</strong> {quote_date}</p>
                        </div>
                        <h3>Produtos Solicitados:</h3>
                        {products}
                        <p style="text-align: center;">
                            <a href="{quote_link}" class="button">Acompanhar Orçamento</a>
                        </p>
                        <p style="font-size: 14px; color: #999;">Você pode acompanhar o status do seu orçamento e trocar mensagens com nossa equipe através do link acima.</p>';
            
            case 'quote_admin_new':
                return '<h2>Novo Pedido de Orçamento Recebido!</h2>
                        <p>Um novo pedido de orçamento aguarda sua análise.</p>
                        <div class="order-details" style="background-color: #fff3cd; border-left: 4px solid #ffc107;">
                            <p><strong>⏰ Ação necessária:</strong> Analise e responda o orçamento o mais breve possível.</p>
                        </div>
                        <h3>Informações do Cliente:</h3>
                        <div class="order-details">
                            <p><strong>Nome:</strong> {customer_name}<br>
                            <strong>Email:</strong> {customer_email}<br>
                            <strong>Telefone:</strong> {customer_phone}</p>
                        </div>
                        <h3>Detalhes do Orçamento:</h3>
                        <div class="order-details">
                            <p><strong>ID:</strong> #{quote_id}<br>
                            <strong>Data:</strong> {quote_date}</p>
                        </div>
                        <h3>Produtos Solicitados:</h3>
                        {products}
                        <p style="text-align: center;">
                            <a href="{admin_link}" class="button" style="background-color: #e74c3c;">Responder Orçamento no Painel</a>
                        </p>';
            
            case 'quote_approved':
                return '<h2>Seu Orçamento foi Aprovado! 🎉</h2>
                        <p>Olá, <strong>{customer_name}</strong>!</p>
                        <p>Temos uma ótima notícia! Seu orçamento <strong>#{quote_id}</strong> foi aprovado e está pronto para finalização.</p>
                        <div class="order-details" style="background-color: #d4edda; border-left: 4px solid #28a745;">
                            <p><strong>✓ Próximo passo:</strong> Efetue o pagamento para iniciarmos o processamento do seu pedido.</p>
                        </div>
                        <h3>Valores Aprovados:</h3>
                        <table class="order-items">
                            <tr>
                                <td><strong>Produtos:</strong></td>
                                <td style="text-align: right;">{approved_price}</td>
                            </tr>
                            <tr>
                                <td><strong>Frete:</strong></td>
                                <td style="text-align: right;">{approved_shipping}</td>
                            </tr>
                            <tr style="background-color: #e8f5e9;">
                                <td><strong>Total:</strong></td>
                                <td style="text-align: right; color: #27ae60; font-size: 18px;"><strong>{total}</strong></td>
                            </tr>
                        </table>
                        {approval_notes}
                        <p style="text-align: center;">
                            <a href="{payment_link}" class="button" style="background-color: #27ae60;">💳 Pagar Agora</a>
                            <a href="{quote_link}" class="button" style="background-color: #6c757d;">Ver Detalhes</a>
                        </p>';
            
            case 'quote_message':
                return '<h2>Nova Mensagem no seu Orçamento 💬</h2>
                        <p>Olá, <strong>{customer_name}</strong>!</p>
                        <p>Nossa equipe enviou uma nova mensagem sobre seu orçamento <strong>#{quote_id}</strong>.</p>
                        <div class="order-details" style="background-color: #e3f2fd; border-left: 4px solid #2196f3;">
                            <p style="font-size: 12px; color: #1976d2; text-transform: uppercase; font-weight: bold;">Mensagem da Equipe:</p>
                            <p style="font-size: 15px; color: #333; white-space: pre-wrap;">{message}</p>
                        </div>
                        <p style="text-align: center;">
                            <a href="{quote_link}" class="button">Responder Mensagem</a>
                        </p>';

            

            default:

                return '<p>Template não configurado para: ' . $email_type . '</p>';

        }

    }

    

    /**

     * Obter apenas o conteáÂudo do template (sem header/footer)

     * Usado no customizador visual

     */

    public static function get_template_content($email_type) {

        // Tentar pegar conteáÂudo customizado salvo

        $custom = get_option('hng_email_content_' . $email_type, '');

        

        if (!empty($custom)) {

            return $custom;

        }

        

        // Retornar conteáÂudo padráo

        return self::get_default_content($email_type);

    }

    

    /**

     * Salva template customizado

     */

    public static function save_template($email_type, $content) {

        return update_option('hng_email_template_' . $email_type, wp_kses_post($content));

    }

    

    /**

     * Salvar apenas o conteáÂudo (sem wrapper)

     */

    public static function save_template_content($email_type, $content) {

        return update_option('hng_email_content_' . $email_type, wp_kses_post($content));

    }

    

    /**

     * Processa variáÂ¡veis no template

     */

    public static function process_variables($template, $data) {

        foreach ($data as $key => $value) {

            // Ignorar objetos e arrays - só processar valores escalares

            if (is_object($value) || is_array($value)) {

                continue;

            }

            $template = str_replace('{' . $key . '}', (string) ($value ?? ''), $template);

        }

        

        // VariáÂ¡veis globais

        $template = str_replace('{site_name}', get_bloginfo('name'), $template);

        $template = str_replace('{site_url}', get_site_url(), $template);

        

        return $template;

    }

    

    /**

     * Processa template com variáÂ¡veis (alias para process_variables)

     */

    public static function process_template_variables($template, $data) {

        return self::process_variables($template, $data);

    }

    

    /**

     * Gera HTML completo do email

     */

    public static function generate_email_html($content, $settings = []) {

        $defaults = [

            'logo' => '',

            'header_color' => '#2196f3',

            'button_color' => '#4caf50',

            'text_color' => '#333333',

            'bg_color' => '#f5f5f5',

            'custom_css' => '',

        ];

        $settings = array_merge($defaults, $settings);

        

        ob_start();

        ?>

        <!DOCTYPE html>

        <html>

        <head>

            <meta charset="UTF-8">

            <meta name="viewport" content="width=device-width, initial-scale=1.0">

            <style>

                body {

                    margin: 0;

                    padding: 0;

                    font-family: Arial, sans-serif;

                    background-color: <?php echo esc_html($settings['bg_color']); ?>;

                    color: <?php echo esc_html($settings['text_color']); ?>;

                }

                .email-container {

                    max-width: 600px;

                    margin: 20px auto;

                    background: #ffffff;

                    border-radius: 8px;

                    overflow: hidden;

                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);

                }

                .email-header {

                    background-color: <?php echo esc_html($settings['header_color']); ?>;

                    padding: 30px 20px;

                    text-align: center;

                }

                .email-header img {

                    max-width: 200px;

                    height: auto;

                }

                .email-body {

                    padding: 30px 20px;

                }

                .order-details {

                    background: #f9f9f9;

                    padding: 20px;

                    border-radius: 5px;

                    margin: 20px 0;

                }

                .order-items {

                    width: 100%;

                    border-collapse: collapse;

                    margin: 20px 0;

                }

                .order-items th {

                    background: #f4f4f4;

                    padding: 12px;

                    text-align: left;

                    border-bottom: 2px solid #ddd;

                }

                .order-items td {

                    padding: 10px 12px;

                    border-bottom: 1px solid #eee;

                }

                .order-total {

                    font-size: 1.2em;

                    color: <?php echo esc_html($settings['header_color']); ?>;

                }

                .button {

                    display: inline-block;

                    padding: 12px 30px;

                    background: <?php echo esc_html($settings['button_color']); ?>;

                    color: #ffffff !important;

                    text-decoration: none;

                    border-radius: 5px;

                    margin: 10px 0;

                    font-weight: bold;

                }

                .email-footer {

                    background: #333;

                    color: #fff;

                    padding: 20px;

                    text-align: center;

                    font-size: 12px;

                }

                .email-footer a {

                    color: #fff;

                    text-decoration: none;

                }

                <?php echo esc_html($settings['custom_css']); ?>

            </style>

        </head>

        <body>

            <div class="email-container">

                <div class="email-header">

                    <?php if (!empty($settings['logo'])): ?>

                        <img src="<?php echo esc_html(esc_url($settings['logo'])); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">

                    <?php else: ?>

                        <h1 style="color: white; margin: 0;"><?php echo esc_html(get_bloginfo('name')); ?></h1>

                    <?php endif; ?>

                </div>

                <div class="email-body">

                    <?php echo wp_kses_post($content); ?>

                </div>

                <div class="email-footer">

                    <p>&copy; <?php echo esc_html(gmdate('Y')); ?> <?php echo esc_html(get_bloginfo('name')); ?>. Todos os direitos reservados.</p>

                    <p>Este áÂ© um email automáÂ¡tico. Por favor, náo responda.</p>

                </div>

            </div>

        </body>

        </html>

        <?php

        return ob_get_clean();

    }

}
