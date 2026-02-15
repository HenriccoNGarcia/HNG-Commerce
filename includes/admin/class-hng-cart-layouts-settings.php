<?php
/**
 * HNG Commerce - Layouts Tab for Settings
 * Integração com classe HNG_Admin_Settings para configurar layouts do carrinho
 *
 * @package HNG_Commerce
 * @subpackage Admin/Settings
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HNG_Cart_Layouts_Settings {

    /**
     * Hook na inicialização do WordPress
     */
    public static function init() {
        add_action( 'admin_init', [ self::class, 'register_settings' ] );
    }

    /**
     * Registra os settings WordPress para layouts
     */
    public static function register_settings() {
        register_setting(
            'hng_commerce_settings',
            'hng_cart_display_type',
            [
                'type'              => 'string',
                'default'           => 'sidebar',
                'sanitize_callback' => [ self::class, 'sanitize_layout_type' ],
            ]
        );

        register_setting(
            'hng_commerce_settings',
            'hng_cart_position',
            [
                'type'              => 'string',
                'default'           => 'bottom-right',
                'sanitize_callback' => 'sanitize_text_field',
            ]
        );

        register_setting(
            'hng_commerce_settings',
            'hng_cart_animation',
            [
                'type'              => 'boolean',
                'default'           => true,
                'sanitize_callback' => 'rest_sanitize_boolean',
            ]
        );

        register_setting(
            'hng_commerce_settings',
            'hng_cart_primary_color',
            [
                'type'              => 'string',
                'default'           => '#2AFFA3',
                'sanitize_callback' => [ self::class, 'sanitize_color' ],
            ]
        );

        register_setting(
            'hng_commerce_settings',
            'hng_cart_primary_dark_color',
            [
                'type'              => 'string',
                'default'           => '#1dd49a',
                'sanitize_callback' => [ self::class, 'sanitize_color' ],
            ]
        );

        register_setting(
            'hng_commerce_settings',
            'hng_cart_accent_color',
            [
                'type'              => 'string',
                'default'           => '#FF7A00',
                'sanitize_callback' => [ self::class, 'sanitize_color' ],
            ]
        );

        register_setting(
            'hng_commerce_settings',
            'hng_cart_text_color',
            [
                'type'              => 'string',
                'default'           => '#1f2937',
                'sanitize_callback' => [ self::class, 'sanitize_color' ],
            ]
        );

        register_setting(
            'hng_commerce_settings',
            'hng_cart_surface_color',
            [
                'type'              => 'string',
                'default'           => '#ffffff',
                'sanitize_callback' => [ self::class, 'sanitize_color' ],
            ]
        );

        register_setting(
            'hng_commerce_settings',
            'hng_cart_radius',
            [
                'type'              => 'integer',
                'default'           => 8,
                'sanitize_callback' => [ self::class, 'sanitize_radius' ],
            ]
        );

        register_setting(
            'hng_commerce_settings',
            'hng_cart_font_family',
            [
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ]
        );

        register_setting(
            'hng_commerce_settings',
            'hng_cart_font_size',
            [
                'type'              => 'integer',
                'default'           => 14,
                'sanitize_callback' => [ self::class, 'sanitize_font_size' ],
            ]
        );

        register_setting(
            'hng_commerce_settings',
            'hng_cart_button_align',
            [
                'type'              => 'string',
                'default'           => 'center',
                'sanitize_callback' => [ self::class, 'sanitize_button_align' ],
            ]
        );

        register_setting(
            'hng_commerce_settings',
            'hng_cart_overlay',
            [
                'type'              => 'boolean',
                'default'           => true,
                'sanitize_callback' => 'rest_sanitize_boolean',
            ]
        );

        register_setting(
            'hng_commerce_settings',
            'hng_cart_overlay_opacity',
            [
                'type'              => 'integer',
                'default'           => 50,
                'sanitize_callback' => [ self::class, 'sanitize_overlay_opacity' ],
            ]
        );

        register_setting(
            'hng_commerce_settings',
            'hng_cart_button_size',
            [
                'type'              => 'string',
                'default'           => 'medium',
                'sanitize_callback' => [ self::class, 'sanitize_button_size' ],
            ]
        );

        register_setting(
            'hng_commerce_settings',
            'hng_cart_button_style',
            [
                'type'              => 'string',
                'default'           => 'rounded',
                'sanitize_callback' => [ self::class, 'sanitize_button_style' ],
            ]
        );

        register_setting(
            'hng_commerce_settings',
            'hng_cart_header_bg',
            [
                'type'              => 'string',
                'default'           => '#f9fafb',
                'sanitize_callback' => [ self::class, 'sanitize_color' ],
            ]
        );

        register_setting(
            'hng_commerce_settings',
            'hng_cart_border_color',
            [
                'type'              => 'string',
                'default'           => '#e5e7eb',
                'sanitize_callback' => [ self::class, 'sanitize_color' ],
            ]
        );

        register_setting(
            'hng_commerce_settings',
            'hng_cart_custom_css',
            [
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_textarea_field',
            ]
        );

        register_setting(
            'hng_commerce_settings',
            'hng_cart_hover_text_enabled',
            [
                'type'              => 'boolean',
                'default'           => true,
                'sanitize_callback' => 'rest_sanitize_boolean',
            ]
        );

        register_setting(
            'hng_commerce_settings',
            'hng_cart_hover_text',
            [
                'type'              => 'string',
                'default'           => 'Carrinho',
                'sanitize_callback' => 'sanitize_text_field',
            ]
        );

        // Sincronizar chat com carrinho
        register_setting(
            'hng_commerce_settings',
            'hng_cart_sync_chat',
            [
                'type'              => 'boolean',
                'default'           => false,
                'sanitize_callback' => 'rest_sanitize_boolean',
            ]
        );

        register_setting(
            'hng_commerce_settings',
            'hng_cart_chat_spacing',
            [
                'type'              => 'integer',
                'default'           => 10,
                'sanitize_callback' => 'absint',
            ]
        );

        register_setting(
            'hng_commerce_settings',
            'hng_cart_chat_order',
            [
                'type'              => 'string',
                'default'           => 'chat-first',
                'sanitize_callback' => 'sanitize_text_field',
            ]
        );

        register_setting(
            'hng_commerce_settings',
            'hng_cart_chat_hide_mobile',
            [
                'type'              => 'boolean',
                'default'           => false,
                'sanitize_callback' => 'rest_sanitize_boolean',
            ]
        );

        register_setting(
            'hng_commerce_settings',
            'hng_cart_chat_stack_vertical',
            [
                'type'              => 'boolean',
                'default'           => true,
                'sanitize_callback' => 'rest_sanitize_boolean',
            ]
        );
    }

    /**
     * Sanitiza o tipo de layout
     */
    public static function sanitize_layout_type( $value ) {
        $allowed = [ 'default', 'sidebar', 'drawer', 'modal', 'popup', 'sticky' ];
        return in_array( $value, $allowed, true ) ? $value : 'sidebar';
    }

    /**
     * Sanitiza opacidade do overlay (0-100)
     */
    public static function sanitize_overlay_opacity( $value ) {
        $opacity = absint( $value );
        return min( max( $opacity, 0 ), 100 );
    }

    /**
     * Sanitiza tamanho dos botões
     */
    public static function sanitize_button_size( $value ) {
        $allowed = [ 'small', 'medium', 'large' ];
        return in_array( $value, $allowed, true ) ? $value : 'medium';
    }

    /**
     * Sanitiza estilo dos botões
     */
    public static function sanitize_button_style( $value ) {
        $allowed = [ 'square', 'rounded', 'pill' ];
        return in_array( $value, $allowed, true ) ? $value : 'rounded';
    }

    /**
     * Sanitiza cor hex
     */
    public static function sanitize_color( $value ) {
        $color = sanitize_hex_color( $value );
        return $color ? $color : '#2AFFA3';
    }

    /**
     * Sanitiza radius
     */
    public static function sanitize_radius( $value ) {
        $radius = absint( $value );
        return $radius > 32 ? 32 : $radius;
    }

    /**
     * Sanitiza tamanho de fonte
     */
    public static function sanitize_font_size( $value ) {
        $size = absint( $value );
        if ( $size < 12 ) {
            return 12;
        }
        if ( $size > 20 ) {
            return 20;
        }
        return $size;
    }

    /**
     * Sanitiza alinhamento do botao
     */
    public static function sanitize_button_align( $value ) {
        $allowed = [ 'left', 'center', 'right' ];
        return in_array( $value, $allowed, true ) ? $value : 'center';
    }

    /**
     * Renderiza a tab de layouts
     */
    public static function render_tab() {
        $current_layout   = get_option( 'hng_cart_display_type', 'sidebar' );
        $current_position = get_option( 'hng_cart_position', 'bottom-right' );
        $animations       = get_option( 'hng_cart_animation', true );
        $primary_color    = get_option( 'hng_cart_primary_color', '#2AFFA3' );
        $primary_dark     = get_option( 'hng_cart_primary_dark_color', '#1dd49a' );
        $accent_color     = get_option( 'hng_cart_accent_color', '#FF7A00' );
        $text_color       = get_option( 'hng_cart_text_color', '#1f2937' );
        $surface_color    = get_option( 'hng_cart_surface_color', '#ffffff' );
        $radius           = get_option( 'hng_cart_radius', 8 );
        $font_family      = get_option( 'hng_cart_font_family', '' );
        $font_size        = get_option( 'hng_cart_font_size', 14 );
        $button_align     = get_option( 'hng_cart_button_align', 'center' );
        $custom_css       = get_option( 'hng_cart_custom_css', '' );
        
        // Chat sync options
        $sync_chat        = get_option( 'hng_cart_sync_chat', false );
        $chat_order       = get_option( 'hng_cart_chat_order', 'chat-first' );
        $chat_spacing     = get_option( 'hng_cart_chat_spacing', 10 );
        $chat_stack       = get_option( 'hng_cart_chat_stack_vertical', true );
        $chat_hide_mobile = get_option( 'hng_cart_chat_hide_mobile', false );
        $hover_enabled    = get_option( 'hng_cart_hover_text_enabled', true );
        $hover_text       = get_option( 'hng_cart_hover_text', 'Carrinho' );

        $home_url = home_url( '/' );
        $preview_query = [
            'hng_cart_preview' => 1,
            'hng_cart_display_type' => $current_layout,
            'hng_cart_position' => $current_position,
            'hng_cart_animation' => $animations ? 1 : 0,
            'hng_cart_primary_color' => $primary_color,
            'hng_cart_primary_dark_color' => $primary_dark,
            'hng_cart_accent_color' => $accent_color,
            'hng_cart_text_color' => $text_color,
            'hng_cart_surface_color' => $surface_color,
            'hng_cart_radius' => absint( $radius ),
            'hng_cart_font_family' => $font_family,
            'hng_cart_font_size' => absint( $font_size ),
            'hng_cart_button_align' => $button_align,
            // Chat sync parameters
            'hng_cart_sync_chat' => $sync_chat ? 1 : 0,
            'hng_cart_chat_order' => $chat_order,
            'hng_cart_chat_spacing' => absint( $chat_spacing ),
            'hng_cart_chat_stack_vertical' => $chat_stack ? 1 : 0,
            'hng_cart_chat_hide_mobile' => $chat_hide_mobile ? 1 : 0,
            // Hover text parameters
            'hng_cart_hover_text_enabled' => $hover_enabled ? 1 : 0,
            'hng_cart_hover_text' => $hover_text,
        ];
        $preview_url = add_query_arg( $preview_query, $home_url );

        ?>
        <div class="hng-layouts-settings">
            <div style="margin-bottom: 20px;">
                <h2 style="display: inline-flex; align-items: center; margin: 0;">
                    <?php esc_html_e( 'Layouts do Carrinho', 'hng-commerce' ); ?>
                    <?php 
                    if (function_exists('hng_admin_tooltip')) {
                        echo hng_admin_tooltip(
                            '🛒 Layouts do Carrinho Flutuante',
                            [
                                [
                                    'icon' => '📦',
                                    'title' => 'Tipos de Layout',
                                    'content' => '<strong>Sidebar:</strong> Painel lateral deslizante<br><strong>Modal:</strong> Popup centralizado<br><strong>Drawer:</strong> Gaveta inferior<br><strong>Popup:</strong> Balão sobre o botão<br><strong>Sticky Badge:</strong> Ícone fixo compacto'
                                ],
                                [
                                    'icon' => '🎨',
                                    'title' => 'Personalização',
                                    'content' => 'Cores, fontes, bordas e CSS personalizado. Todas as alterações aparecem em tempo real no preview.'
                                ],
                                [
                                    'icon' => '💬',
                                    'title' => 'Sincronização com Chat',
                                    'content' => 'Combine o botão do carrinho com o chat ao vivo em um container unificado. Defina ordem e espaçamento.'
                                ],
                                [
                                    'icon' => '📱',
                                    'title' => 'Responsividade',
                                    'content' => 'Posição e comportamento podem variar entre desktop e mobile. Configure ocultação em dispositivos móveis.'
                                ],
                            ],
                            [
                                'title' => '💡 Recomendações',
                                'items' => [
                                    ['label' => 'E-commerce tradicional', 'text' => 'Use Sidebar ou Modal'],
                                    ['label' => 'Landing page', 'text' => 'Use Sticky Badge (discreto)'],
                                    ['label' => 'Mobile-first', 'text' => 'Use Drawer (gaveta inferior)'],
                                ]
                            ],
                            480
                        );
                    }
                    ?>
                </h2>
            </div>
            <style>
                .hng-layouts-settings {
                    --hng-cart-primary: <?php echo esc_html( $primary_color ); ?>;
                    --hng-cart-primary-dark: <?php echo esc_html( $primary_dark ); ?>;
                    --hng-cart-accent: <?php echo esc_html( $accent_color ); ?>;
                    --hng-cart-text: <?php echo esc_html( $text_color ); ?>;
                    --hng-cart-surface: <?php echo esc_html( $surface_color ); ?>;
                    --hng-cart-radius: <?php echo esc_html( absint( $radius ) ); ?>px;
                    --hng-cart-font-family: <?php echo esc_html( $font_family !== '' ? $font_family : 'inherit' ); ?>;
                    --hng-cart-font-size: <?php echo esc_html( absint( $font_size ) ); ?>px;
                }
            </style>
            <?php settings_fields( 'hng_commerce_settings' ); ?>
            <div class="hng-settings-container">
                    <!-- Sidebar de configurações -->
                    <div class="hng-config-panel">
                        <div class="hng-config-header">
                            <h2><?php esc_html_e( '⚙️ Configurações do Carrinho', 'hng-commerce' ); ?></h2>
                            <button type="button" class="button hng-toggle-config" id="hng-toggle-config">
                                <span class="hng-toggle-text"><?php esc_html_e( 'Ocultar', 'hng-commerce' ); ?></span>
                                <span class="dashicons dashicons-arrow-up-alt2"></span>
                            </button>
                        </div>
                        <div class="hng-config-body" id="hng-config-body">

                        <!-- Layout Type -->
                        <div class="hng-form-group">
                            <label for="hng_cart_display_type">
                                <strong><?php esc_html_e( 'Tipo de Layout', 'hng-commerce' ); ?></strong>
                            </label>

                            <div class="hng-layout-radio-group">
                                <?php self::render_layout_options( $current_layout ); ?>
                            </div>
                        </div>

                        <!-- Position, Button Size, Button Style Row -->
                        <div class="hng-form-row">
                            <div class="hng-form-group">
                                <label for="hng_cart_position">
                                    <strong><?php esc_html_e( 'Posição', 'hng-commerce' ); ?></strong>
                                </label>
                                <select name="hng_cart_position" id="hng_cart_position" class="regular-text">
                                    <option value="bottom-right" <?php selected( $current_position, 'bottom-right' ); ?>>
                                        📍 <?php esc_html_e( 'Embaixo à direita', 'hng-commerce' ); ?>
                                    </option>
                                    <option value="bottom-left" <?php selected( $current_position, 'bottom-left' ); ?>>
                                        📍 <?php esc_html_e( 'Embaixo à esquerda', 'hng-commerce' ); ?>
                                    </option>
                                    <option value="top-right" <?php selected( $current_position, 'top-right' ); ?>>
                                        📍 <?php esc_html_e( 'Topo à direita', 'hng-commerce' ); ?>
                                    </option>
                                    <option value="top-left" <?php selected( $current_position, 'top-left' ); ?>>
                                        📍 <?php esc_html_e( 'Topo à esquerda', 'hng-commerce' ); ?>
                                    </option>
                                </select>
                            </div>

                            <div class="hng-form-group">
                                <label for="hng_cart_button_size">
                                    <strong><?php esc_html_e( 'Tamanho do Botão', 'hng-commerce' ); ?></strong>
                                </label>
                                <select name="hng_cart_button_size" id="hng_cart_button_size" class="regular-text">
                                    <option value="small" <?php selected( get_option( 'hng_cart_button_size', 'medium' ), 'small' ); ?>>
                                        <?php esc_html_e( 'Pequeno (40px)', 'hng-commerce' ); ?>
                                    </option>
                                    <option value="medium" <?php selected( get_option( 'hng_cart_button_size', 'medium' ), 'medium' ); ?>>
                                        <?php esc_html_e( 'Médio (52px)', 'hng-commerce' ); ?>
                                    </option>
                                    <option value="large" <?php selected( get_option( 'hng_cart_button_size', 'medium' ), 'large' ); ?>>
                                        <?php esc_html_e( 'Grande (60px)', 'hng-commerce' ); ?>
                                    </option>
                                </select>
                            </div>

                            <div class="hng-form-group">
                                <label for="hng_cart_button_style">
                                    <strong><?php esc_html_e( 'Estilo', 'hng-commerce' ); ?></strong>
                                </label>
                                <select name="hng_cart_button_style" id="hng_cart_button_style" class="regular-text">
                                    <option value="square" <?php selected( get_option( 'hng_cart_button_style', 'rounded' ), 'square' ); ?>>
                                        <?php esc_html_e( 'Quadrado', 'hng-commerce' ); ?>
                                    </option>
                                    <option value="rounded" <?php selected( get_option( 'hng_cart_button_style', 'rounded' ), 'rounded' ); ?>>
                                        <?php esc_html_e( 'Arredondado', 'hng-commerce' ); ?>
                                    </option>
                                    <option value="pill" <?php selected( get_option( 'hng_cart_button_style', 'rounded' ), 'pill' ); ?>>
                                        <?php esc_html_e( 'Pílula', 'hng-commerce' ); ?>
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Checkboxes Row -->
                        <div class="hng-form-row">
                            <div class="hng-form-group">
                                <label for="hng_cart_animation">
                                    <input type="hidden" name="hng_cart_animation" value="0">
                                    <input type="checkbox" 
                                           name="hng_cart_animation" 
                                           id="hng_cart_animation" 
                                           value="1" 
                                           <?php checked( $animations, true ); ?>>
                                    <strong><?php esc_html_e( 'Animações', 'hng-commerce' ); ?></strong>
                                </label>
                            </div>

                            <div class="hng-form-group">
                                <label for="hng_cart_overlay">
                                    <input type="hidden" name="hng_cart_overlay" value="0">
                                    <input type="checkbox" 
                                           name="hng_cart_overlay" 
                                           id="hng_cart_overlay" 
                                           value="1" 
                                           <?php checked( get_option( 'hng_cart_overlay', true ), true ); ?>>
                                    <strong><?php esc_html_e( 'Overlay', 'hng-commerce' ); ?></strong>
                                </label>
                            </div>

                            <div class="hng-form-group">
                                <label for="hng_cart_hover_text_enabled">
                                    <input type="hidden" name="hng_cart_hover_text_enabled" value="0">
                                    <input type="checkbox" 
                                           name="hng_cart_hover_text_enabled" 
                                           id="hng_cart_hover_text_enabled" 
                                           value="1" 
                                           <?php checked( get_option( 'hng_cart_hover_text_enabled', true ), true ); ?>>
                                    <strong><?php esc_html_e( 'Texto Hover', 'hng-commerce' ); ?></strong>
                                </label>
                            </div>
                        </div>

                        <!-- Overlay Opacity & Hover Text Row -->
                        <div class="hng-form-row">
                            <div class="hng-form-group">
                                <label for="hng_cart_overlay_opacity">
                                    <strong><?php esc_html_e( 'Opacidade Overlay', 'hng-commerce' ); ?></strong>
                                </label>
                                <input type="range" 
                                       id="hng_cart_overlay_opacity" 
                                       name="hng_cart_overlay_opacity" 
                                       min="0" 
                                       max="100" 
                                       value="<?php echo esc_attr( absint( get_option( 'hng_cart_overlay_opacity', 50 ) ) ); ?>" 
                                       class="hng-range-slider">
                                <output for="hng_cart_overlay_opacity" class="hng-range-value">
                                    <?php echo esc_html( absint( get_option( 'hng_cart_overlay_opacity', 50 ) ) ); ?>%
                                </output>
                            </div>

                            <div class="hng-form-group">
                                <label for="hng_cart_hover_text">
                                    <strong><?php esc_html_e( 'Texto do Botão', 'hng-commerce' ); ?></strong>
                                </label>
                                <input type="text" 
                                       name="hng_cart_hover_text" 
                                       id="hng_cart_hover_text" 
                                       value="<?php echo esc_attr( get_option( 'hng_cart_hover_text', 'Carrinho' ) ); ?>" 
                                       class="regular-text"
                                       placeholder="Carrinho">
                            </div>
                        </div>

                        <!-- Chat Sync Section -->
                        <div class="hng-form-group hng-chat-sync-section">
                            <h3><?php esc_html_e( '💬 Integração com Chat', 'hng-commerce' ); ?></h3>
                            <p class="description">
                                <?php esc_html_e( 'Sincronize o botão do chat com o carrinho para facilitar o posicionamento.', 'hng-commerce' ); ?>
                            </p>

                            <div class="hng-form-row">
                                <div class="hng-form-group">
                                    <label for="hng_cart_sync_chat">
                                        <input type="hidden" name="hng_cart_sync_chat" value="0">
                                        <input type="checkbox" 
                                               name="hng_cart_sync_chat" 
                                               id="hng_cart_sync_chat" 
                                               value="1" 
                                               <?php checked( get_option( 'hng_cart_sync_chat', false ), true ); ?>>
                                        <strong><?php esc_html_e( 'Sincronizar Chat', 'hng-commerce' ); ?></strong>
                                    </label>
                                    <p class="description">
                                        <?php esc_html_e( 'Chat usa a mesma posição e fica junto ao carrinho', 'hng-commerce' ); ?>
                                    </p>
                                </div>

                                <div class="hng-form-group">
                                    <label for="hng_cart_chat_order">
                                        <strong><?php esc_html_e( 'Ordem', 'hng-commerce' ); ?></strong>
                                    </label>
                                    <select name="hng_cart_chat_order" id="hng_cart_chat_order" class="regular-text">
                                        <option value="chat-first" <?php selected( get_option( 'hng_cart_chat_order', 'chat-first' ), 'chat-first' ); ?>>
                                            <?php esc_html_e( 'Chat primeiro', 'hng-commerce' ); ?>
                                        </option>
                                        <option value="cart-first" <?php selected( get_option( 'hng_cart_chat_order', 'chat-first' ), 'cart-first' ); ?>>
                                            <?php esc_html_e( 'Carrinho primeiro', 'hng-commerce' ); ?>
                                        </option>
                                    </select>
                                </div>

                                <div class="hng-form-group">
                                    <label for="hng_cart_chat_spacing">
                                        <strong><?php esc_html_e( 'Espaçamento (px)', 'hng-commerce' ); ?></strong>
                                    </label>
                                    <input type="number" 
                                           name="hng_cart_chat_spacing" 
                                           id="hng_cart_chat_spacing" 
                                           min="0" 
                                           max="50" 
                                           value="<?php echo esc_attr( absint( get_option( 'hng_cart_chat_spacing', 10 ) ) ); ?>" 
                                           class="small-text">
                                </div>
                            </div>

                            <!-- Responsive Options -->
                            <div class="hng-form-row">
                                <div class="hng-form-group">
                                    <label for="hng_cart_chat_stack_vertical">
                                        <input type="hidden" name="hng_cart_chat_stack_vertical" value="0">
                                        <input type="checkbox" 
                                               name="hng_cart_chat_stack_vertical" 
                                               id="hng_cart_chat_stack_vertical" 
                                               value="1" 
                                               <?php checked( get_option( 'hng_cart_chat_stack_vertical', true ), true ); ?>>
                                        <strong><?php esc_html_e( 'Empilhar verticalmente', 'hng-commerce' ); ?></strong>
                                    </label>
                                    <p class="description">
                                        <?php esc_html_e( 'Empilha os botões verticalmente (recomendado)', 'hng-commerce' ); ?>
                                    </p>
                                </div>

                                <div class="hng-form-group">
                                    <label for="hng_cart_chat_hide_mobile">
                                        <input type="hidden" name="hng_cart_chat_hide_mobile" value="0">
                                        <input type="checkbox" 
                                               name="hng_cart_chat_hide_mobile" 
                                               id="hng_cart_chat_hide_mobile" 
                                               value="1" 
                                               <?php checked( get_option( 'hng_cart_chat_hide_mobile', false ), true ); ?>>
                                        <strong><?php esc_html_e( 'Esconder Chat no Mobile', 'hng-commerce' ); ?></strong>
                                    </label>
                                    <p class="description">
                                        <?php esc_html_e( 'No mobile, mostra apenas o carrinho', 'hng-commerce' ); ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="hng-form-group">
                            <h3><?php esc_html_e( 'Design e Tipografia', 'hng-commerce' ); ?></h3>
                            <p class="description">
                                <?php esc_html_e( 'Personalize cores, fontes, arredondamento e alinhamento dos botoes.', 'hng-commerce' ); ?>
                            </p>

                            <div class="hng-design-grid">
                                <div class="hng-design-card">
                                    <label for="hng_cart_primary_color"><?php esc_html_e( 'Cor Primaria', 'hng-commerce' ); ?></label>
                                    <input type="color" id="hng_cart_primary_color" name="hng_cart_primary_color" value="<?php echo esc_attr( $primary_color ); ?>">
                                </div>
                                <div class="hng-design-card">
                                    <label for="hng_cart_primary_dark_color"><?php esc_html_e( 'Cor Primaria Escura', 'hng-commerce' ); ?></label>
                                    <input type="color" id="hng_cart_primary_dark_color" name="hng_cart_primary_dark_color" value="<?php echo esc_attr( $primary_dark ); ?>">
                                </div>
                                <div class="hng-design-card">
                                    <label for="hng_cart_accent_color"><?php esc_html_e( 'Cor de Destaque (Badge)', 'hng-commerce' ); ?></label>
                                    <input type="color" id="hng_cart_accent_color" name="hng_cart_accent_color" value="<?php echo esc_attr( $accent_color ); ?>">
                                </div>
                                <div class="hng-design-card">
                                    <label for="hng_cart_text_color"><?php esc_html_e( 'Cor do Texto', 'hng-commerce' ); ?></label>
                                    <input type="color" id="hng_cart_text_color" name="hng_cart_text_color" value="<?php echo esc_attr( $text_color ); ?>">
                                </div>
                                <div class="hng-design-card">
                                    <label for="hng_cart_surface_color"><?php esc_html_e( 'Cor de Fundo', 'hng-commerce' ); ?></label>
                                    <input type="color" id="hng_cart_surface_color" name="hng_cart_surface_color" value="<?php echo esc_attr( $surface_color ); ?>">
                                </div>
                                <div class="hng-design-card">
                                    <label for="hng_cart_header_bg"><?php esc_html_e( 'Header Background', 'hng-commerce' ); ?></label>
                                    <input type="color" id="hng_cart_header_bg" name="hng_cart_header_bg" value="<?php echo esc_attr( get_option( 'hng_cart_header_bg', '#f9fafb' ) ); ?>">
                                </div>
                                <div class="hng-design-card">
                                    <label for="hng_cart_border_color"><?php esc_html_e( 'Cor das Bordas', 'hng-commerce' ); ?></label>
                                    <input type="color" id="hng_cart_border_color" name="hng_cart_border_color" value="<?php echo esc_attr( get_option( 'hng_cart_border_color', '#e5e7eb' ) ); ?>">
                                </div>
                                <div class="hng-design-card">
                                    <label for="hng_cart_radius"><?php esc_html_e( 'Arredondamento (px)', 'hng-commerce' ); ?></label>
                                    <input type="number" id="hng_cart_radius" name="hng_cart_radius" min="0" max="32" value="<?php echo esc_attr( absint( $radius ) ); ?>">
                                </div>
                                <div class="hng-design-card">
                                    <label for="hng_cart_font_family"><?php esc_html_e( 'Fonte (CSS font-family)', 'hng-commerce' ); ?></label>
                                    <input type="text" id="hng_cart_font_family" name="hng_cart_font_family" value="<?php echo esc_attr( $font_family ); ?>" placeholder="'Montserrat', sans-serif">
                                </div>
                                <div class="hng-design-card">
                                    <label for="hng_cart_font_size"><?php esc_html_e( 'Tamanho da Fonte (px)', 'hng-commerce' ); ?></label>
                                    <input type="number" id="hng_cart_font_size" name="hng_cart_font_size" min="12" max="20" value="<?php echo esc_attr( absint( $font_size ) ); ?>">
                                </div>
                                <div class="hng-design-card">
                                    <label for="hng_cart_button_align"><?php esc_html_e( 'Alinhamento dos Botoes', 'hng-commerce' ); ?></label>
                                    <select id="hng_cart_button_align" name="hng_cart_button_align">
                                        <option value="left" <?php selected( $button_align, 'left' ); ?>><?php esc_html_e( 'Esquerda', 'hng-commerce' ); ?></option>
                                        <option value="center" <?php selected( $button_align, 'center' ); ?>><?php esc_html_e( 'Centro', 'hng-commerce' ); ?></option>
                                        <option value="right" <?php selected( $button_align, 'right' ); ?>><?php esc_html_e( 'Direita', 'hng-commerce' ); ?></option>
                                    </select>
                                </div>
                                <div class="hng-design-card" style="grid-column: span 2;">
                                    <label for="hng_cart_custom_css"><?php esc_html_e( 'CSS Personalizado', 'hng-commerce' ); ?></label>
                                    <textarea id="hng_cart_custom_css" name="hng_cart_custom_css" placeholder=".hng-cart-sidebar { box-shadow: none; }\n.hng-cart-trigger { border-radius: 999px; }"><?php echo esc_textarea( $custom_css ); ?></textarea>
                                </div>
                            </div>
                        </div>

                        </div><!-- .hng-config-body -->
                        <?php submit_button( __( '💾 Salvar Configurações', 'hng-commerce' ), 'primary', 'submit', true ); ?>
                    </div>

                    <!-- Preview Panel -->
                    <div class="hng-preview-panel">
                        <div class="hng-live-preview">
                            <h3><?php esc_html_e( '🌐 Preview ao Vivo', 'hng-commerce' ); ?></h3>
                            <p class="description">
                                <?php esc_html_e( 'Veja o carrinho na pagina inicial em tempo real. Use o toggle para simular desktop e mobile.', 'hng-commerce' ); ?>
                            </p>

                            <div class="hng-live-preview-toolbar">
                                <div class="hng-device-toggle">
                                    <button type="button" class="button hng-device-btn is-active" data-width="1200" data-height="760">
                                        <?php esc_html_e( 'Desktop', 'hng-commerce' ); ?>
                                    </button>
                                    <button type="button" class="button hng-device-btn" data-width="820" data-height="760">
                                        <?php esc_html_e( 'Tablet', 'hng-commerce' ); ?>
                                    </button>
                                    <button type="button" class="button hng-device-btn" data-width="390" data-height="760">
                                        <?php esc_html_e( 'Mobile', 'hng-commerce' ); ?>
                                    </button>
                                </div>

                                <div class="hng-preview-mode">
                                    <button type="button" class="button hng-mode-btn is-active" data-mode="logged">
                                        <?php esc_html_e( 'Logado', 'hng-commerce' ); ?>
                                    </button>
                                    <button type="button" class="button hng-mode-btn" data-mode="guest">
                                        <?php esc_html_e( 'Visitante', 'hng-commerce' ); ?>
                                    </button>
                                </div>

                                <button type="button" class="button button-primary hng-live-refresh">
                                    <?php esc_html_e( 'Atualizar Preview', 'hng-commerce' ); ?>
                                </button>
                            </div>

                            <div class="hng-live-preview-frame" data-width="1200" data-height="760">
                                <div class="hng-preview-loading" style="display: none;">
                                    <span class="spinner is-active"></span>
                                    <span><?php esc_html_e( 'Atualizando preview...', 'hng-commerce' ); ?></span>
                                </div>
                                <iframe
                                    id="hng-live-preview-iframe"
                                    title="<?php esc_attr_e( 'Preview ao vivo do carrinho', 'hng-commerce' ); ?>"
                                    src="<?php echo esc_url( $preview_url ); ?>"
                                    loading="lazy">
                                </iframe>
                            </div>
                            <p class="description">
                                <?php esc_html_e( 'O modo visitante oculta a barra de admin. Para um teste real sem login, use uma janela anonima.', 'hng-commerce' ); ?>
                            </p>
                        </div>
                    </div>
                </div>
        </div>

        <style>
            .hng-layouts-settings {
                background: #fff;
                padding: 20px;
                border-radius: 8px;
                max-width: 100%;
                margin: 0 auto;
            }

            .hng-settings-container {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 30px;
                max-width: 100%;
                margin: 0 auto;
            }

            .hng-config-panel,
            .hng-preview-panel {
                padding: 20px;
                overflow: hidden;
                border: 1px solid #e5e7eb;
                border-radius: 8px;
                background: #fafafa;
            }

            /* Config header with toggle button */
            .hng-config-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 16px;
            }

            .hng-config-header h2 {
                margin: 0;
            }

            .hng-toggle-config {
                display: flex;
                align-items: center;
                gap: 4px;
            }

            .hng-toggle-config .dashicons {
                transition: transform 0.3s ease;
            }

            .hng-toggle-config.is-collapsed .dashicons {
                transform: rotate(180deg);
            }

            /* Config body visibility */
            .hng-config-body {
                transition: max-height 0.3s ease, opacity 0.3s ease;
                overflow: hidden;
            }

            .hng-config-body.is-hidden {
                max-height: 0 !important;
                opacity: 0;
                margin: 0;
                padding: 0;
            }

            /* Inline form groups row */
            .hng-form-row {
                display: flex;
                flex-wrap: wrap;
                gap: 16px;
                margin-bottom: 16px;
            }

            .hng-form-row .hng-form-group {
                flex: 1;
                min-width: 180px;
                margin-bottom: 0;
            }

            /* Checkbox in row styling */
            .hng-form-row .hng-form-group label {
                display: flex;
                align-items: center;
                gap: 6px;
                padding: 10px 14px;
                background: #fff;
                border: 1px solid #e5e7eb;
                border-radius: 6px;
                cursor: pointer;
                transition: border-color 0.2s;
            }

            .hng-form-row .hng-form-group label:hover {
                border-color: #2AFFA3;
            }

            .hng-form-row .hng-form-group input[type="checkbox"] {
                margin: 0;
            }

            .hng-form-group {
                margin-bottom: 20px;
            }

            .hng-form-group label {
                display: block;
                margin-bottom: 8px;
                color: #1f2937;
            }

            .hng-form-group .regular-text {
                width: 100%;
                padding: 10px;
                border: 1px solid #d1d5db;
                border-radius: 6px;
                font-size: 14px;
            }

            .hng-form-group .description {
                margin: 8px 0 0 0;
                font-size: 12px;
                color: #6b7280;
            }

            .hng-range-slider {
                width: 100%;
                height: 6px;
                background: #d1d5db;
                border-radius: 5px;
                outline: none;
                appearance: none;
                margin: 8px 0;
            }

            .hng-range-slider::-webkit-slider-thumb {
                appearance: none;
                width: 18px;
                height: 18px;
                background: #2AFFA3;
                cursor: pointer;
                border-radius: 50%;
                border: 2px solid #fff;
                box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            }

            .hng-range-slider::-moz-range-thumb {
                width: 18px;
                height: 18px;
                background: #2AFFA3;
                cursor: pointer;
                border-radius: 50%;
                border: 2px solid #fff;
                box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            }

            .hng-range-value {
                display: inline-block;
                margin-left: 10px;
                font-weight: 600;
                color: #1f2937;
                min-width: 40px;
            }

            .hng-layout-radio-group {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 10px;
            }

            .hng-layout-radio-option {
                display: flex;
                align-items: center;
                padding: 10px;
                border: 2px solid #e5e7eb;
                border-radius: 6px;
                cursor: pointer;
                transition: all 0.2s;
            }

            .hng-layout-radio-option input[type="radio"] {
                margin-right: 8px;
            }

            .hng-layout-info strong {
                display: block;
                font-size: 13px;
            }

            .hng-layout-info small {
                font-size: 11px;
                color: #6b7280;
            }

            @media (max-width: 768px) {
                .hng-layout-radio-group {
                    grid-template-columns: 1fr;
                }
            }

            .hng-design-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 16px;
                margin: 16px 0 8px 0;
            }

            .hng-design-card {
                background: #fff;
                border: 1px solid #e5e7eb;
                border-radius: 10px;
                padding: 12px;
            }

            .hng-design-card label {
                display: block;
                margin-bottom: 6px;
                font-size: 12px;
                color: #6b7280;
                text-transform: uppercase;
                letter-spacing: 0.04em;
            }

            .hng-design-card input[type="color"],
            .hng-design-card input[type="number"],
            .hng-design-card input[type="text"],
            .hng-design-card select {
                width: 100%;
                padding: 8px 10px;
                border: 1px solid #d1d5db;
                border-radius: 8px;
                font-size: 13px;
                background: #fff;
            }

            .hng-design-card textarea {
                width: 100%;
                min-height: 120px;
                padding: 10px;
                border: 1px solid #d1d5db;
                border-radius: 8px;
                font-family: monospace;
                font-size: 12px;
                background: #0f172a;
                color: #e2e8f0;
            }

            .hng-layout-radio-option:hover {
                border-color: #2AFFA3;
                background: #f0fffc;
            }

            .hng-layout-radio-option input[type="radio"] {
                margin-right: 10px;
                width: 18px;
                height: 18px;
                cursor: pointer;
                accent-color: #2AFFA3;
            }

            .hng-layout-radio-option input[type="radio"]:checked + .hng-layout-info strong {
                color: #2AFFA3;
            }

            .hng-layout-info {
                flex: 1;
            }

            .hng-layout-info strong {
                display: block;
                font-weight: 600;
                color: #1f2937;
                margin-bottom: 4px;
            }

            .hng-layout-info small {
                color: #6b7280;
                font-size: 12px;
            }

            .hng-live-preview {
                margin-top: 0;
            }

            .hng-live-preview h3 {
                margin: 0 0 8px 0;
                font-size: 16px;
            }

            .hng-live-preview-toolbar {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                align-items: center;
                margin: 12px 0 16px 0;
            }

            .hng-device-toggle,
            .hng-preview-mode {
                display: flex;
                gap: 8px;
                align-items: center;
            }

            .hng-device-btn.is-active,
            .hng-mode-btn.is-active {
                border-color: #2AFFA3;
                color: #0f172a;
                background: #ecfdf5;
            }

            .hng-live-preview-frame {
                width: 100%;
                background: #0f172a;
                border-radius: 12px;
                padding: 16px;
                display: flex;
                justify-content: center;
                align-items: flex-start;
                overflow: hidden;
                max-width: 100%;
                box-sizing: border-box;
            }
            
            .hng-live-preview-frame iframe {
                flex-shrink: 1;
                width: 100%;
                max-width: 100%;
                height: 760px;
                border: 1px solid #1f2937;
                border-radius: 12px;
                background: #fff;
            }

            /* When desktop preview is selected, use column layout */
            .hng-settings-container.hng-desktop-mode {
                grid-template-columns: 1fr;
            }

            .hng-settings-container.hng-desktop-mode .hng-config-panel {
                max-width: 1000px;
                margin: 0 auto;
            }

            .hng-settings-container.hng-desktop-mode .hng-preview-panel {
                max-width: 100%;
                width: 100%;
                margin: 0 auto;
            }

            .hng-settings-container.hng-desktop-mode .hng-live-preview-frame {
                max-width: 100%;
                overflow-x: auto;
            }

            .hng-settings-container.hng-desktop-mode .hng-live-preview-frame iframe {
                width: 1200px;
                min-width: 1200px;
                flex-shrink: 0;
            }

            .hng-settings-container.hng-desktop-mode .hng-design-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }

            .hng-settings-container.hng-desktop-mode .hng-design-card[style*="span 2"] {
                grid-column: span 4 !important;
            }

            @media (max-width: 1024px) {
                .hng-settings-container {
                    grid-template-columns: 1fr;
                }
            }
            
            /* Preview loading indicator */
            .hng-preview-loading {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: rgba(255, 255, 255, 0.95);
                padding: 15px 25px;
                border-radius: 8px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
                display: flex;
                align-items: center;
                gap: 10px;
                z-index: 10;
            }
            .hng-preview-loading .spinner {
                float: none;
                margin: 0;
            }
            .hng-live-preview-frame {
                position: relative;
            }
            .hng-live-preview-frame.is-loading iframe {
                opacity: 0.4;
                pointer-events: none;
            }
        </style>

        <script>
            jQuery(document).ready(function($) {
                var liveMode = 'logged';
                var liveTimer = null;

                // Prevent form submit on Enter key in text/number inputs
                $('.hng-layouts-settings').on('keydown', 'input[type="text"], input[type="number"], input[type="color"]', function(e) {
                    if (e.key === 'Enter' || e.keyCode === 13) {
                        e.preventDefault();
                        scheduleLiveRefresh();
                        return false;
                    }
                });

                function applyDesignVars() {
                    var container = document.querySelector('.hng-layouts-settings');
                    if (!container) {
                        return;
                    }

                    container.style.setProperty('--hng-cart-primary', $('#hng_cart_primary_color').val());
                    container.style.setProperty('--hng-cart-primary-dark', $('#hng_cart_primary_dark_color').val());
                    container.style.setProperty('--hng-cart-accent', $('#hng_cart_accent_color').val());
                    container.style.setProperty('--hng-cart-text', $('#hng_cart_text_color').val());
                    container.style.setProperty('--hng-cart-surface', $('#hng_cart_surface_color').val());
                    container.style.setProperty('--hng-cart-radius', $('#hng_cart_radius').val() + 'px');
                    container.style.setProperty('--hng-cart-font-family', $('#hng_cart_font_family').val() || 'inherit');
                    container.style.setProperty('--hng-cart-font-size', $('#hng_cart_font_size').val() + 'px');
                }

                function buildPreviewUrl() {
                    var baseUrl = '<?php echo esc_url( $home_url ); ?>';
                    var params = {
                        hng_cart_preview: 1,
                        hng_cart_display_type: $('input[name="hng_cart_display_type"]:checked').val(),
                        hng_cart_position: $('select[name="hng_cart_position"]').val(),
                        hng_cart_animation: $('#hng_cart_animation').is(':checked') ? 1 : 0,
                        hng_cart_overlay: $('#hng_cart_overlay').is(':checked') ? 1 : 0,
                        hng_cart_overlay_opacity: $('#hng_cart_overlay_opacity').val(),
                        hng_cart_button_size: $('#hng_cart_button_size').val(),
                        hng_cart_button_style: $('#hng_cart_button_style').val(),
                        hng_cart_primary_color: $('#hng_cart_primary_color').val(),
                        hng_cart_primary_dark_color: $('#hng_cart_primary_dark_color').val(),
                        hng_cart_accent_color: $('#hng_cart_accent_color').val(),
                        hng_cart_text_color: $('#hng_cart_text_color').val(),
                        hng_cart_surface_color: $('#hng_cart_surface_color').val(),
                        hng_cart_header_bg: $('#hng_cart_header_bg').val(),
                        hng_cart_border_color: $('#hng_cart_border_color').val(),
                        hng_cart_radius: $('#hng_cart_radius').val(),
                        hng_cart_font_family: $('#hng_cart_font_family').val(),
                        hng_cart_font_size: $('#hng_cart_font_size').val(),
                        hng_cart_button_align: $('#hng_cart_button_align').val(),
                        // Chat sync parameters (using ID selectors)
                        hng_cart_sync_chat: $('#hng_cart_sync_chat').is(':checked') ? 1 : 0,
                        hng_cart_chat_order: $('#hng_cart_chat_order').val(),
                        hng_cart_chat_spacing: $('#hng_cart_chat_spacing').val(),
                        hng_cart_chat_stack_vertical: $('#hng_cart_chat_stack_vertical').is(':checked') ? 1 : 0,
                        hng_cart_chat_hide_mobile: $('#hng_cart_chat_hide_mobile').is(':checked') ? 1 : 0,
                        // Hover text parameters (using ID selector)
                        hng_cart_hover_text_enabled: $('#hng_cart_hover_text_enabled').is(':checked') ? 1 : 0,
                        hng_cart_hover_text: $('#hng_cart_hover_text').val(),
                        hng_preview_t: Date.now()
                    };

                    if (liveMode === 'guest') {
                        params.hng_cart_preview_guest = 1;
                    }

                    return baseUrl + '?' + $.param(params);
                }

                function refreshLivePreview() {
                    var frame = $('#hng-live-preview-iframe');
                    var container = $('.hng-live-preview-frame');
                    var loading = $('.hng-preview-loading');
                    
                    if (!frame.length) {
                        return;
                    }
                    
                    // Show loading indicator
                    container.addClass('is-loading');
                    loading.show();
                    
                    // Update iframe src
                    frame.attr('src', buildPreviewUrl());
                }
                
                // Hide loading when iframe finishes loading
                $('#hng-live-preview-iframe').on('load', function() {
                    $('.hng-live-preview-frame').removeClass('is-loading');
                    $('.hng-preview-loading').hide();
                });

                function scheduleLiveRefresh() {
                    if (liveTimer) {
                        clearTimeout(liveTimer);
                    }
                    liveTimer = setTimeout(function() {
                        refreshLivePreview();
                    }, 500);
                }

                function updateLiveFrameSize(width, height) {
                    var frame = $('#hng-live-preview-iframe');
                    var container = $('.hng-live-preview-frame');
                    if (!frame.length || !container.length) {
                        return;
                    }
                    var maxHeight = Math.min(height, 760);
                    
                    // Para desktop (1200px), usar largura total solicitada
                    if (width >= 1200) {
                        frame.css({
                            'width': '1200px',
                            'max-width': '1200px',
                            'height': maxHeight + 'px'
                        });
                    } else {
                        // Para tablet/mobile, limitar ao container
                        var containerWidth = container.innerWidth() - 32;
                        var targetWidth = Math.min(width, containerWidth);
                        frame.css({
                            'width': targetWidth + 'px',
                            'max-width': '100%',
                            'height': maxHeight + 'px'
                        });
                    }
                }

                $('input[name="hng_cart_display_type"]').on('change', function() {
                    scheduleLiveRefresh();
                });
                $('select[name="hng_cart_position"]').on('change', function() {
                    scheduleLiveRefresh();
                });
                $('#hng_cart_animation').on('change', function() {
                    scheduleLiveRefresh();
                });
                $('#hng_cart_overlay').on('change', function() {
                    scheduleLiveRefresh();
                });
                $('#hng_cart_overlay_opacity').on('input', function() {
                    $(this).next('.hng-range-value').text($(this).val() + '%');
                    scheduleLiveRefresh();
                });
                $('#hng_cart_button_size, #hng_cart_button_style').on('change', function() {
                    scheduleLiveRefresh();
                });
                $('#hng_cart_primary_color, #hng_cart_primary_dark_color, #hng_cart_accent_color, #hng_cart_text_color, #hng_cart_surface_color, #hng_cart_header_bg, #hng_cart_border_color, #hng_cart_radius, #hng_cart_font_family, #hng_cart_font_size, #hng_cart_button_align').on('input change', function() {
                    applyDesignVars();
                    scheduleLiveRefresh();
                });
                // Chat sync event listeners (using ID selectors for checkboxes to avoid hidden inputs)
                $('#hng_cart_sync_chat, #hng_cart_chat_stack_vertical, #hng_cart_chat_hide_mobile').on('change', function() {
                    scheduleLiveRefresh();
                });
                $('#hng_cart_chat_order').on('change', function() {
                    scheduleLiveRefresh();
                });
                $('#hng_cart_chat_spacing').on('input change', function() {
                    scheduleLiveRefresh();
                });
                // Hover text event listeners (using ID selector for checkbox)
                $('#hng_cart_hover_text_enabled').on('change', function() {
                    scheduleLiveRefresh();
                });
                $('#hng_cart_hover_text').on('input', function() {
                    scheduleLiveRefresh();
                });
                $('.hng-live-refresh').on('click', function(e) {
                    e.preventDefault();
                    refreshLivePreview();
                });
                $('.hng-mode-btn').on('click', function() {
                    var btn = $(this);
                    $('.hng-mode-btn').removeClass('is-active');
                    btn.addClass('is-active');
                    liveMode = btn.data('mode');
                    refreshLivePreview();
                });

                applyDesignVars();
                updateLiveFrameSize(1200, 760);
                
                // Set initial desktop mode class
                $('.hng-settings-container').addClass('hng-desktop-mode');

                // Store current device dimensions for resize recalculation
                var currentDeviceWidth = 1200;
                var currentDeviceHeight = 760;

                // Recalculate on window resize
                $(window).on('resize', function() {
                    updateLiveFrameSize(currentDeviceWidth, currentDeviceHeight);
                });

                // Update stored dimensions when device button is clicked
                $('.hng-device-btn').off('click').on('click', function() {
                    var btn = $(this);
                    currentDeviceWidth = btn.data('width');
                    currentDeviceHeight = btn.data('height');
                    $('.hng-device-btn').removeClass('is-active');
                    btn.addClass('is-active');
                    updateLiveFrameSize(currentDeviceWidth, currentDeviceHeight);
                    
                    // Toggle desktop mode class for column layout
                    if (currentDeviceWidth >= 1200) {
                        $('.hng-settings-container').addClass('hng-desktop-mode');
                    } else {
                        $('.hng-settings-container').removeClass('hng-desktop-mode');
                    }
                });

                // Toggle config panel visibility
                $('#hng-toggle-config').on('click', function() {
                    var btn = $(this);
                    var body = $('#hng-config-body');
                    var text = btn.find('.hng-toggle-text');
                    
                    if (body.hasClass('is-hidden')) {
                        body.removeClass('is-hidden');
                        btn.removeClass('is-collapsed');
                        text.text('<?php echo esc_js( __( 'Ocultar', 'hng-commerce' ) ); ?>');
                    } else {
                        body.addClass('is-hidden');
                        btn.addClass('is-collapsed');
                        text.text('<?php echo esc_js( __( 'Mostrar', 'hng-commerce' ) ); ?>');
                    }
                });
            });
        </script>
        <?php
    }

    /**
     * Renderiza as opções de layout (radio buttons)
     */
    private static function render_layout_options( $current ) {
        $layouts = [
            'default' => [
                'label'       => '🛒 Carrinho Padrão',
                'description' => __( 'Usa o carrinho nativo do tema/sistema', 'hng-commerce' ),
            ],
            'sidebar' => [
                'label'       => '📌 Sidebar',
                'description' => __( 'Desliza do lado direito', 'hng-commerce' ),
            ],
            'drawer'  => [
                'label'       => '📊 Drawer',
                'description' => __( 'Abre de baixo para cima (mobile-friendly)', 'hng-commerce' ),
            ],
            'modal'   => [
                'label'       => '🎯 Modal',
                'description' => __( 'Pop-up centralizado elegante', 'hng-commerce' ),
            ],
            'popup'   => [
                'label'       => '🔔 Popup',
                'description' => __( 'Ícone flutuante no canto', 'hng-commerce' ),
            ],
            'sticky'  => [
                'label'       => '📍 Sticky Badge',
                'description' => __( 'Pequeno ícone que expande ao hover', 'hng-commerce' ),
            ],
        ];

        foreach ( $layouts as $type => $data ) {
            ?>
            <label class="hng-layout-radio-option">
                <input type="radio" 
                      name="hng_cart_display_type" 
                       value="<?php echo esc_attr( $type ); ?>" 
                       <?php checked( $current, $type ); ?>>
                <div class="hng-layout-info">
                    <strong><?php echo esc_html( $data['label'] ); ?></strong>
                    <small><?php echo esc_html( $data['description'] ); ?></small>
                </div>
            </label>
            <?php
        }
    }

}

// Inicializar
HNG_Cart_Layouts_Settings::init();
