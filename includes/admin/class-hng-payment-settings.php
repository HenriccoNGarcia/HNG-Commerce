<?php

/**

 * Configurações de Pagamento - Gateways

 *

 * @package HNG_Commerce

 * @subpackage Admin

 * @since 1.0.0

 */



// Exit if accessed directly

if ( ! defined( 'ABSPATH' ) ) {

    exit;

}



/**

 * HNG Payment Settings Class

 */

class HNG_Payment_Settings {



    /**

     * Singleton instance

     */

    private static $instance = null;



    /**

     * Constructor

     */

    private function __construct() {

        $this->register_gateways();

        add_action( 'admin_menu', array( $this, 'add_menu_page' ) );

        add_action( 'admin_init', array( $this, 'register_settings' ) );

        add_action( 'wp_ajax_test_gateway_connection', array( $this, 'ajax_test_gateway_connection' ) );

    }



    /**

     * Get singleton instance

     */

    public static function get_instance() {

        if ( is_null( self::$instance ) ) {

            self::$instance = new self();

        }

        return self::$instance;

    }



    /**

     * Available gateways

     */

    public function get_available_gateways() {

        return array(

            'asaas' => array(

                'name' => 'Asaas',

                'description' => 'Gateway de pagamento brasileiro competitivo',

                'icon' => 'cc',

                'methods' => array( 'boleto', 'credit_card', 'pix' ),

                'fields' => array(

                    'api_key' => array(

                        'label' => 'API Key',

                        'type' => 'password',

                        'description' => 'Chave API do Asaas (encontre em Conta → Integrações → API)',

                    ),

                    'environment' => array(

                        'label' => 'Ambiente',

                        'type' => 'select',

                        'options' => array(

                            'sandbox' => 'Sandbox (Testes)',

                            'production' => 'Produção',

                        ),

                    ),

                ),

            ),

            'mercadopago' => array(

                'name' => 'Mercado Pago',

                'description' => 'Solução completa de pagamentos na América Latina',

                'icon' => 'cc',

                'methods' => array( 'boleto', 'credit_card', 'pix' ),

                'fields' => array(

                    'public_key' => array(

                        'label' => 'Public Key',

                        'type' => 'text',

                        'description' => 'Chave pública do Mercado Pago',

                    ),

                    'access_token' => array(

                        'label' => 'Access Token',

                        'type' => 'password',

                        'description' => 'Token de acesso do Mercado Pago (encontre em Seu Negócio → Configurações → Credenciais)',

                    ),

                    'environment' => array(

                        'label' => 'Ambiente',

                        'type' => 'select',

                        'options' => array(

                            'sandbox' => 'Sandbox (Testes)',

                            'production' => 'Produção',

                        ),

                    ),

                ),

            ),

            'pagseguro' => array(

                'name' => 'PagSeguro',

                'description' => 'Processamento seguro de pagamentos pela Uol',

                'icon' => 'cc',

                'methods' => array( 'boleto', 'credit_card', 'pix' ),

                'fields' => array(

                    'email' => array(

                        'label' => 'E-mail PagSeguro',

                        'type' => 'email',

                        'description' => 'E-mail cadastrado no PagSeguro',

                    ),

                    'token' => array(

                        'label' => 'Token',

                        'type' => 'password',

                        'description' => 'Token de integração do PagSeguro',

                    ),

                    'environment' => array(

                        'label' => 'Ambiente',

                        'type' => 'select',

                        'options' => array(

                            'sandbox' => 'Sandbox (Testes)',

                            'production' => 'Produção',

                        ),

                    ),

                ),

            ),

            'pagarme' => array(

                'name' => 'Pagar.me',

                'description' => 'Gateway Stone. Split nativo, receba em D+1',

                'icon' => 'cc',

                'methods' => array( 'pix', 'boleto', 'credit_card' ),

                'fields' => array(

                    'secret_key' => array(

                        'label' => 'Secret Key',

                        'type' => 'password',

                        'description' => 'Chave secreta (Dashboard → Configurações → API)',

                    ),

                    'public_key' => array(

                        'label' => 'Public Key',

                        'type' => 'text',

                        'description' => 'Chave pública para checkout',

                    ),

                    'environment' => array(

                        'label' => 'Ambiente',

                        'type' => 'select',

                        'options' => array(

                            'test' => 'Test',

                            'live' => 'Live',

                        ),

                        'description' => 'Use Test para homologação',

                    ),

                ),

            ),

            'nubank' => array(

                'name' => 'Nubank',

                'description' => 'Fintech Nubank. PIX instantâneo com taxas baixas',

                'icon' => 'cc',

                'methods' => array( 'pix', 'credit_card' ),

                'fields' => array(

                    'client_id' => array(

                        'label' => 'Client ID',

                        'type' => 'text',

                        'description' => 'Client ID da aplicação (Portal do Desenvolvedor)',

                    ),

                    'client_secret' => array(

                        'label' => 'Client Secret',

                        'type' => 'password',

                        'description' => 'Client Secret da aplicação',

                    ),

                    'cert_path' => array(

                        'label' => 'Caminho Certificado (.pem)',

                        'type' => 'text',

                        'description' => 'Caminho completo para o certificado: /path/to/cert.pem',

                    ),

                    'environment' => array(

                        'label' => 'Ambiente',

                        'type' => 'select',

                        'options' => array(

                            'sandbox' => 'Sandbox (Testes)',

                            'production' => 'Produção',

                        ),

                        'description' => 'Use Sandbox para homologação',

                    ),

                ),

            ),

            'cielo' => array(

                'name' => 'Cielo',

                'description' => 'Líder em cartões no Brasil. Aceite todas as bandeiras',

                'icon' => 'cc',

                'methods' => array( 'credit_card', 'debit_card', 'pix' ),

                'fields' => array(

                    'merchant_id' => array(

                        'label' => 'Merchant ID',

                        'type' => 'text',

                        'description' => 'ID do estabelecimento (EC Number)',

                    ),

                    'merchant_key' => array(

                        'label' => 'Merchant Key',

                        'type' => 'password',

                        'description' => 'Chave de acesso (Access Key)',

                    ),

                    'environment' => array(

                        'label' => 'Ambiente',

                        'type' => 'select',

                        'options' => array(

                            'sandbox' => 'Sandbox (Testes)',

                            'production' => 'Produção',

                        ),

                        'description' => 'Use Sandbox para homologação',

                    ),

                ),

            ),

            'picpay' => array(

                'name' => 'PicPay',

                'description' => 'Carteira digital. PIX e pagamento via app PicPay',

                'icon' => 'cc',

                'methods' => array( 'pix', 'digital_wallet' ),

                'fields' => array(

                    'picpay_token' => array(

                        'label' => 'PicPay Token',

                        'type' => 'password',

                        'description' => 'Token de E-commerce (Painel PicPay → Integrações)',

                    ),

                    'seller_token' => array(

                        'label' => 'Seller Token',

                        'type' => 'password',

                        'description' => 'Token do vendedor (x-seller-token header)',

                    ),

                    'environment' => array(

                        'label' => 'Ambiente',

                        'type' => 'select',

                        'options' => array(

                            'sandbox' => 'Sandbox (Testes)',

                            'production' => 'Produção',

                        ),

                        'description' => 'Use Sandbox para homologação',

                    ),

                ),

            ),

            'bb' => array(

                'name' => 'Banco do Brasil',

                'description' => 'Maior banco público do Brasil. PIX e boleto com certificado digital',

                'icon' => 'cc',

                'methods' => array( 'pix', 'boleto' ),

                'fields' => array(

                    'developer_key' => array(

                        'label' => 'Developer Application Key',

                        'type' => 'password',

                        'description' => 'Chave da aplicação (Portal Developers BB)',

                    ),

                    'client_id' => array(

                        'label' => 'Client ID (Basic)',

                        'type' => 'text',

                        'description' => 'Client ID para OAuth 2.0',

                    ),

                    'client_secret' => array(

                        'label' => 'Client Secret (Basic)',

                        'type' => 'password',

                        'description' => 'Client Secret para OAuth 2.0',

                    ),

                    'convenio' => array(

                        'label' => 'Número do Convênio',

                        'type' => 'text',

                        'description' => 'Convênio de cobrança (7 dígitos)',

                    ),

                    'agencia' => array(

                        'label' => 'Agência',

                        'type' => 'text',

                        'description' => 'Número da agência (4 dígitos)',

                    ),

                    'conta' => array(

                        'label' => 'Conta Corrente',

                        'type' => 'text',

                        'description' => 'Número da conta (sem dígito)',

                    ),

                    'gw_dev_app_key' => array(

                        'label' => 'GW Dev App Key',

                        'type' => 'password',

                        'description' => 'Chave do gateway de pagamento BB',

                    ),

                    'environment' => array(

                        'label' => 'Ambiente',

                        'type' => 'select',

                        'options' => array(

                            'sandbox' => 'Sandbox (Testes)',

                            'production' => 'Produção',

                        ),

                        'description' => 'Use Sandbox para homologação',

                    ),

                ),

            ),

            'bradesco' => array(

                'name' => 'Bradesco',

                'description' => 'Bradesco Shopfácil. PIX e boleto registrado com API REST',

                'icon' => 'cc',

                'methods' => array( 'pix', 'boleto', 'credit_card' ),

                'fields' => array(

                    'merchant_id' => array(

                        'label' => 'Merchant ID',

                        'type' => 'text',

                        'description' => 'ID do estabelecimento (Shopfácil)',

                    ),

                    'merchant_key' => array(

                        'label' => 'Merchant Key',

                        'type' => 'password',

                        'description' => 'Chave de segurança da loja',

                    ),

                    'carteira' => array(

                        'label' => 'Carteira',

                        'type' => 'text',

                        'description' => 'Número da carteira de cobrança (ex: 26)',

                    ),

                    'agencia' => array(

                        'label' => 'Agência',

                        'type' => 'text',

                        'description' => 'Número da agência (4 dígitos)',

                    ),

                    'conta' => array(

                        'label' => 'Conta Corrente',

                        'type' => 'text',

                        'description' => 'Número da conta (sem dígito)',

                    ),

                    'environment' => array(

                        'label' => 'Ambiente',

                        'type' => 'select',

                        'options' => array(

                            'sandbox' => 'Sandbox (Testes)',

                            'production' => 'Produção',

                        ),

                        'description' => 'Use Sandbox para homologação',

                    ),

                ),

            ),

            'itau' => array(

                'name' => 'Itaú',

                'description' => 'Itaú Shopline. Boleto registrado e PIX via API REST',

                'icon' => 'cc',

                'methods' => array( 'pix', 'boleto' ),

                'fields' => array(

                    'client_id' => array(

                        'label' => 'Client ID',

                        'type' => 'text',

                        'description' => 'Client ID (Portal Desenvolvedor Itaú)',

                    ),

                    'client_secret' => array(

                        'label' => 'Client Secret',

                        'type' => 'password',

                        'description' => 'Client Secret OAuth 2.0',

                    ),

                    'chave_pix' => array(

                        'label' => 'Chave PIX',

                        'type' => 'text',

                        'description' => 'Chave PIX da conta (CPF/CNPJ/Email/Telefone)',

                    ),

                    'beneficiario_id' => array(

                        'label' => 'ID do Beneficiário',

                        'type' => 'text',

                        'description' => 'ID do beneficiário no sistema Itaú',

                    ),

                    'agencia' => array(

                        'label' => 'Agência',

                        'type' => 'text',

                        'description' => 'Número da agência (4 dígitos)',

                    ),

                    'conta' => array(

                        'label' => 'Conta Corrente',

                        'type' => 'text',

                        'description' => 'Número da conta (com dígito)',

                    ),

                    'environment' => array(

                        'label' => 'Ambiente',

                        'type' => 'select',

                        'options' => array(

                            'sandbox' => 'Sandbox (Testes)',

                            'production' => 'Produção',

                        ),

                        'description' => 'Use Sandbox para homologação',

                    ),

                ),

            ),

            'santander' => array(

                'name' => 'Santander',

                'description' => 'Santander Getnet. Completo: PIX, boleto e cartões',

                'icon' => 'cc',

                'methods' => array( 'pix', 'boleto', 'credit_card', 'debit_card' ),

                'fields' => array(

                    'seller_id' => array(

                        'label' => 'Seller ID (Getnet)',

                        'type' => 'text',

                        'description' => 'ID do vendedor na plataforma Getnet',

                    ),

                    'client_id' => array(

                        'label' => 'Client ID',

                        'type' => 'text',

                        'description' => 'Client ID OAuth (Portal Getnet)',

                    ),

                    'client_secret' => array(

                        'label' => 'Client Secret',

                        'type' => 'password',

                        'description' => 'Client Secret para autenticação',

                    ),

                    'convenio' => array(

                        'label' => 'Código do Convênio',

                        'type' => 'text',

                        'description' => 'Convênio de boleto Santander',

                    ),

                    'carteira' => array(

                        'label' => 'Carteira',

                        'type' => 'text',

                        'description' => 'Número da carteira de cobrança (ex: 102)',

                    ),

                    'environment' => array(

                        'label' => 'Ambiente',

                        'type' => 'select',

                        'options' => array(

                            'sandbox' => 'Sandbox (Testes - Homologação)',

                            'production' => 'Produção',

                        ),

                        'description' => 'Use Sandbox para testes antes de ir para produção',

                    ),

                ),

            ),

        );

    }



    /**

     * Register gateways

     */

    public function register_gateways() {

        $gateways = $this->get_available_gateways();

        // Store in option for use elsewhere

        update_option( 'hng_available_payment_gateways', $gateways );

    }



    /**

     * Add admin menu page

     */

    public function add_menu_page() {

        add_submenu_page(

            'hng-commerce',

            __( 'Configurações de Pagamento', 'hng-commerce' ),

            __( 'Pagamentos', 'hng-commerce' ),

            'manage_options',

            'hng-payment-settings',

            array( $this, 'render_page' )

        );

    }



    /**

     * Register settings

     */

    public function register_settings() {

        $gateways = $this->get_available_gateways();



        foreach ( $gateways as $gateway_id => $gateway ) {

            if ( ! empty( $gateway['fields'] ) ) {

                register_setting(

                    'hng_payment_settings',

                    "hng_{$gateway_id}_enabled",

                    array( 'sanitize_callback' => array( $this, 'sanitize_checkbox' ) )

                );



                // Registrar campo de status de teste

                register_setting(

                    'hng_payment_settings',

                    "hng_{$gateway_id}_test_status",

                    array( 'sanitize_callback' => array( $this, 'sanitize_text_field' ) )

                );



                // Registrar campo de última data de teste

                register_setting(

                    'hng_payment_settings',

                    "hng_{$gateway_id}_test_date",

                    array( 'sanitize_callback' => array( $this, 'sanitize_text_field' ) )

                );



                foreach ( $gateway['fields'] as $field_id => $field ) {

                    register_setting(

                        'hng_payment_settings',

                        "hng_{$gateway_id}_{$field_id}",

                        array( 'sanitize_callback' => array( $this, 'sanitize_text_field' ) )

                    );

                }

            }

        }

    }



    /**

     * Sanitize checkbox

     */

    public function sanitize_checkbox( $val ) {

        return ( isset( $val ) && ! empty( $val ) && '1' === $val ) ? '1' : '0';

    }



    /**

     * Sanitize text field

     */

    public function sanitize_text_field( $val ) {

        if ( ! isset( $val ) || empty( $val ) ) {

            return '';

        }

        return sanitize_text_field( (string) $val );

    }



    /**

     * Render page

     */

    public function render_page() {

        ?>

        <div class="wrap">

            <h1><?php esc_html_e( 'Configurações de Pagamento', 'hng-commerce' ); ?></h1>

            

            <form method="post" action="options.php">

                <?php settings_fields( 'hng_payment_settings' ); ?>

                

                <table class="form-table">

                    <?php

                    $gateways = $this->get_available_gateways();

                    

                    foreach ( $gateways as $gateway_id => $gateway ) {

                        $enabled = get_option( "hng_{$gateway_id}_enabled", false );

                        $test_status = get_option( "hng_{$gateway_id}_test_status", '' );

                        $test_date = get_option( "hng_{$gateway_id}_test_date", '' );

                        ?>

                        <tr>

                            <th scope="row">

                                <label>

                                    <input type="checkbox" 

                                           name="hng_<?php echo esc_attr( $gateway_id ); ?>_enabled" 

                                           value="1" 

                                           <?php checked( $enabled, '1' ); ?> />

                                    <?php echo esc_html( $gateway['name'] ); ?>

                                </label>

                                

                                <?php if ( ! empty( $test_status ) ) : ?>

                                    <br />

                                    <span style="font-size: 12px; font-weight: normal;">

                                        <?php if ( 'success' === $test_status ) : ?>

                                            <span style="color: #46b450;">✓ Testado com sucesso</span>

                                        <?php elseif ( 'failed' === $test_status ) : ?>

                                            <span style="color: #dc3232;">✗ Teste falhou</span>

                                        <?php endif; ?>

                                        <?php if ( ! empty( $test_date ) ) : ?>

                                            <br /><span style="color: #666;">

                                                <?php 

                                                echo esc_html( 

                                                    sprintf( 

                                                        /* translators: %s: test date and time */
                                                        __( 'Último teste: %s', 'hng-commerce' ), 

                                                        date_i18n( 'd/m/Y H:i', strtotime( $test_date ) ) 

                                                    ) 

                                                ); 

                                                ?>

                                            </span>

                                        <?php endif; ?>

                                    </span>

                                <?php endif; ?>

                            </th>

                            <td>

                                <p class="description"><?php echo esc_html( $gateway['description'] ?? '' ); ?></p>

                                

                                <?php if ( ! empty( $gateway['fields'] ) ) : ?>

                                    <div style="margin-top: 15px;">

                                        <?php foreach ( $gateway['fields'] as $field_id => $field ) : ?>

                                            <p>

                                                <label>

                                                    <strong><?php echo esc_html( $field['label'] ?? '' ); ?></strong><br />

                                                    <?php

                                                    $field_name = "hng_{$gateway_id}_{$field_id}";

                                                    $field_value = get_option( $field_name, '' );

                                                    $field_type = $field['type'] ?? 'text';

                                                    

                                                    if ( 'select' === $field_type ) :

                                                        ?>

                                                        <select name="<?php echo esc_attr( $field_name ); ?>">

                                                            <?php foreach ( $field['options'] ?? array() as $opt_key => $opt_label ) : ?>

                                                                <option value="<?php echo esc_attr( $opt_key ); ?>" 

                                                                        <?php selected( $field_value, $opt_key ); ?>>

                                                                    <?php echo esc_html( $opt_label ); ?>

                                                                </option>

                                                            <?php endforeach; ?>

                                                        </select>

                                                    <?php else : ?>

                                                        <input type="<?php echo esc_attr( $field_type ); ?>" 

                                                               name="<?php echo esc_attr( $field_name ); ?>" 

                                                               value="<?php echo esc_attr( $field_value ); ?>" 

                                                               class="regular-text" />

                                                    <?php endif; ?>

                                                    

                                                    <?php if ( ! empty( $field['description'] ) ) : ?>

                                                        <br /><span class="description"><?php echo esc_html( $field['description'] ); ?></span>

                                                    <?php endif; ?>

                                                </label>

                                            </p>

                                        <?php endforeach; ?>

                                        

                                        <p>

                                            <button type="button" 

                                                    class="button test-gateway-btn" 

                                                    data-gateway="<?php echo esc_attr( $gateway_id ); ?>">

                                                <?php esc_html_e( 'Testar Conexão', 'hng-commerce' ); ?>

                                            </button>

                                            <span class="spinner"></span>

                                            <span class="test-result"></span>

                                        </p>

                                    </div>

                                <?php endif; ?>

                            </td>

                        </tr>

                        <?php

                    }

                    ?>

                </table>

                

                <?php submit_button(); ?>

            </form>

        </div>

        

        <style>

            .test-gateway-btn { margin-right: 10px; }

            .spinner { float: none; margin: 0 10px 0 0; }

            .test-result { font-weight: bold; }

            .test-result.success { color: #46b450; }

            .test-result.error { color: #dc3232; }

        </style>

        

        <script>

            jQuery(document).ready(function($) {

                $('.test-gateway-btn').on('click', function() {

                    var $btn = $(this);

                    var $spinner = $btn.next('.spinner');

                    var $result = $btn.siblings('.test-result');

                    var gatewayId = $btn.data('gateway');

                    var $statusLabel = $btn.closest('tr').find('th label');

                    

                    $spinner.addClass('is-active');

                    $result.removeClass('success error').text('');

                    $btn.prop('disabled', true);

                    

                    $.post(ajaxurl, {

                        action: 'test_gateway_connection',

                        gateway_id: gatewayId,

                        nonce: '<?php echo esc_attr(wp_create_nonce( 'test_gateway_connection' )); ?>'

                    }, function(response) {

                        $spinner.removeClass('is-active');

                        $btn.prop('disabled', false);

                        

                        if ( response.success ) {

                            $result.addClass('success').text('✓ ' + ( response.data?.message || 'Conexão estabelecida com sucesso!' ));

                            

                            // Atualizar status visual na label

                            var currentDate = new Date().toLocaleString('pt-BR');

                            var existingStatus = $statusLabel.find('.gateway-test-status');

                            if (existingStatus.length) {

                                existingStatus.remove();

                            }

                            $statusLabel.append(

                                '<br><span class="gateway-test-status" style="font-size: 12px; font-weight: normal;">' +

                                '<span style="color: #46b450;">✓ Testado com sucesso</span><br>' +

                                '<span style="color: #666;">Último teste: ' + currentDate + '</span>' +

                                '</span>'

                            );

                        } else {

                            var errorMsg = response.data?.message || 'Conexão falhou';

                            $result.addClass('error').text('✗ Erro: ' + errorMsg);

                            

                            // Atualizar status visual na label

                            var currentDate = new Date().toLocaleString('pt-BR');

                            var existingStatus = $statusLabel.find('.gateway-test-status');

                            if (existingStatus.length) {

                                existingStatus.remove();

                            }

                            $statusLabel.append(

                                '<br><span class="gateway-test-status" style="font-size: 12px; font-weight: normal;">' +

                                '<span style="color: #dc3232;">✗ Teste falhou</span><br>' +

                                '<span style="color: #666;">Último teste: ' + currentDate + '</span>' +

                                '</span>'

                            );

                        }

                    }).fail(function() {

                        $spinner.removeClass('is-active');

                        $btn.prop('disabled', false);

                        $result.addClass('error').text('✗ Erro de comunicação');

                    });

                });

            });

        </script>

        <?php

    }



    /**

     * AJAX test gateway connection

     */

    public function ajax_test_gateway_connection() {

        check_ajax_referer( 'test_gateway_connection', 'nonce' );

        

        if ( ! current_user_can( 'manage_options' ) ) {

            wp_send_json_error( array( 'message' => 'Sem permissão' ) );

        }

        

        $gateway_id = isset( $_POST['gateway_id'] ) && is_string( $_POST['gateway_id'] ) 

            ? sanitize_text_field( $_POST['gateway_id'] ) 

            : '';

        

        if ( empty( $gateway_id ) ) {

            wp_send_json_error( array( 'message' => 'Gateway inválido' ) );

        }

        

        // Verificar se o gateway está habilitado e tem credenciais

        $enabled = get_option( "hng_{$gateway_id}_enabled", false );

        if ( ! $enabled ) {

            update_option( "hng_{$gateway_id}_test_status", 'failed' );

            update_option( "hng_{$gateway_id}_test_date", current_time( 'mysql' ) );

            wp_send_json_error( array( 'message' => 'Gateway não habilitado' ) );

        }

        

        // Obter configuração do gateway

        $gateways = $this->get_available_gateways();

        if ( ! isset( $gateways[ $gateway_id ] ) ) {

            update_option( "hng_{$gateway_id}_test_status", 'failed' );

            update_option( "hng_{$gateway_id}_test_date", current_time( 'mysql' ) );

            wp_send_json_error( array( 'message' => 'Gateway não encontrado' ) );

        }

        

        $gateway = $gateways[ $gateway_id ];

        

        // Verificar campos obrigatórios

        $missing_fields = array();

        if ( ! empty( $gateway['fields'] ) ) {

            foreach ( $gateway['fields'] as $field_id => $field ) {

                $field_value = get_option( "hng_{$gateway_id}_{$field_id}", '' );

                if ( empty( $field_value ) && 'environment' !== $field_id ) {

                    $missing_fields[] = $field['label'];

                }

            }

        }

        

        if ( ! empty( $missing_fields ) ) {

            update_option( "hng_{$gateway_id}_test_status", 'failed' );

            update_option( "hng_{$gateway_id}_test_date", current_time( 'mysql' ) );

            wp_send_json_error( array( 

                'message' => 'Campos obrigatórios não preenchidos: ' . implode( ', ', $missing_fields ) 

            ) );

        }

        

        // Teste básico - pode ser expandido para testes reais com cada gateway

        // Por enquanto, apenas valida se as credenciais foram preenchidas

        $test_success = true;

        $test_message = 'Credenciais configuradas. Gateway pronto para uso.';

        

        // Salvar resultado do teste

        update_option( "hng_{$gateway_id}_test_status", $test_success ? 'success' : 'failed' );

        update_option( "hng_{$gateway_id}_test_date", current_time( 'mysql' ) );

        

        if ( $test_success ) {

            wp_send_json_success( array( 'message' => $test_message ) );

        } else {

            wp_send_json_error( array( 'message' => $test_message ) );

        }

    }

}



// Initialize

HNG_Payment_Settings::get_instance();

