<?php // phpcs:disable Squiz.Commenting.FileComment, WordPress.Files.FileName
/**
 * HNG Commerce - Layouts Tab for Settings
 * Integração com classe HNG_Admin_Settings para configurar layouts do carrinho
 *
 * @package HNG_Commerce
 * @subpackage Admin/Settings
 * @since 1.3.0
 */
// phpcs:disable Squiz.Commenting.FunctionComment.MissingParamTag
// phpcs:disable Squiz.Commenting.InlineComment.InvalidEndChar
// phpcs:disable Squiz.Commenting.ClassComment.Missing
// phpcs:disable Universal.NamingConventions.NoReservedKeywordParameterNames
// phpcs:disable WordPress.PHP.YodaConditions.NotYoda

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HNG_Cart_Layouts_Settings {

	/**
	 * Hook na inicialização do WordPress
	 */
	public static function init() {
		add_action( 'admin_init', array( self::class, 'migrate_legacy_cart_palette' ), 5 );
		add_action( 'admin_init', array( self::class, 'register_settings' ) );
	}

	/**
	 * Migração única: corrige paleta antiga que forçava verde em tudo
	 */
	public static function migrate_legacy_cart_palette() {
		if ( get_option( 'hng_cart_palette_migrated_20260220', false ) ) {
			return;
		}

		$legacy_primary      = '#2affa3';
		$legacy_primary_dark = '#1dd49a';

		$primary = strtolower( (string) get_option( 'hng_cart_primary_color', '#2AFFA3' ) );
		if ( $primary === $legacy_primary ) {
			update_option( 'hng_cart_primary_color', '#2271b1' );
		}

		$primary_dark = strtolower( (string) get_option( 'hng_cart_primary_dark_color', '#1dd49a' ) );
		if ( $primary_dark === $legacy_primary_dark ) {
			update_option( 'hng_cart_primary_dark_color', '#135e96' );
		}

		$text = strtolower( (string) get_option( 'hng_cart_text_color', '#1f2937' ) );
		if ( $text === $legacy_primary ) {
			update_option( 'hng_cart_text_color', '#1f2937' );
		}

		$surface = strtolower( (string) get_option( 'hng_cart_surface_color', '#ffffff' ) );
		if ( $surface === $legacy_primary ) {
			update_option( 'hng_cart_surface_color', '#ffffff' );
		}

		$header_bg = strtolower( (string) get_option( 'hng_cart_header_bg', '#f9fafb' ) );
		if ( $header_bg === $legacy_primary ) {
			update_option( 'hng_cart_header_bg', '#f9fafb' );
		}

		$border = strtolower( (string) get_option( 'hng_cart_border_color', '#e5e7eb' ) );
		if ( $border === $legacy_primary ) {
			update_option( 'hng_cart_border_color', '#e5e7eb' );
		}

		update_option( 'hng_cart_palette_migrated_20260220', 1 );
	}

	/**
	 * Registra os settings WordPress para layouts
	 */
	public static function register_settings() {
		register_setting(
			'hng_commerce_settings',
			'hng_cart_display_type',
			array(
				'type'              => 'string',
				'default'           => 'sidebar',
				'sanitize_callback' => array( self::class, 'sanitize_layout_type' ),
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_cart_position',
			array(
				'type'              => 'string',
				'default'           => 'bottom-right',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_cart_animation',
			array(
				'type'              => 'boolean',
				'default'           => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_cart_primary_color',
			array(
				'type'              => 'string',
				'default'           => '#2271b1',
				'sanitize_callback' => array( self::class, 'sanitize_primary_color' ),
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_cart_primary_dark_color',
			array(
				'type'              => 'string',
				'default'           => '#135e96',
				'sanitize_callback' => array( self::class, 'sanitize_primary_dark_color' ),
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_cart_accent_color',
			array(
				'type'              => 'string',
				'default'           => '#FF7A00',
				'sanitize_callback' => array( self::class, 'sanitize_accent_color' ),
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_cart_text_color',
			array(
				'type'              => 'string',
				'default'           => '#1f2937',
				'sanitize_callback' => array( self::class, 'sanitize_text_color' ),
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_cart_surface_color',
			array(
				'type'              => 'string',
				'default'           => '#ffffff',
				'sanitize_callback' => array( self::class, 'sanitize_surface_color' ),
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_cart_radius',
			array(
				'type'              => 'integer',
				'default'           => 8,
				'sanitize_callback' => array( self::class, 'sanitize_radius' ),
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_cart_font_family',
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_cart_font_size',
			array(
				'type'              => 'integer',
				'default'           => 14,
				'sanitize_callback' => array( self::class, 'sanitize_font_size' ),
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_cart_button_align',
			array(
				'type'              => 'string',
				'default'           => 'center',
				'sanitize_callback' => array( self::class, 'sanitize_button_align' ),
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_cart_overlay',
			array(
				'type'              => 'boolean',
				'default'           => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_cart_overlay_opacity',
			array(
				'type'              => 'integer',
				'default'           => 50,
				'sanitize_callback' => array( self::class, 'sanitize_overlay_opacity' ),
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_cart_button_size',
			array(
				'type'              => 'string',
				'default'           => 'medium',
				'sanitize_callback' => array( self::class, 'sanitize_button_size' ),
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_cart_button_style',
			array(
				'type'              => 'string',
				'default'           => 'rounded',
				'sanitize_callback' => array( self::class, 'sanitize_button_style' ),
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_cart_header_bg',
			array(
				'type'              => 'string',
				'default'           => '#f9fafb',
				'sanitize_callback' => array( self::class, 'sanitize_header_bg_color' ),
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_cart_border_color',
			array(
				'type'              => 'string',
				'default'           => '#e5e7eb',
				'sanitize_callback' => array( self::class, 'sanitize_border_color' ),
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_cart_custom_css',
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_textarea_field',
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_cart_hover_text_enabled',
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_cart_hover_text',
			array(
				'type'              => 'string',
				'default'           => 'Carrinho',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_cart_hover_effect',
			array(
				'type'              => 'string',
				'default'           => 'none',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_live_chat_hover_text_enabled',
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_live_chat_hover_text',
			array(
				'type'              => 'string',
				'default'           => 'Chat',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_live_chat_hover_effect',
			array(
				'type'              => 'string',
				'default'           => 'none',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		// Animation Duration Settings
		register_setting(
			'hng_commerce_settings',
			'hng_cart_animation_duration',
			array(
				'type'              => 'integer',
				'default'           => 400,
				'sanitize_callback' => 'absint',
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_cart_hover_text_animation_duration',
			array(
				'type'              => 'integer',
				'default'           => 300,
				'sanitize_callback' => 'absint',
			)
		);

		// Sincronizar chat com carrinho
		register_setting(
			'hng_commerce_settings',
			'hng_cart_sync_chat',
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_cart_chat_spacing',
			array(
				'type'              => 'integer',
				'default'           => 10,
				'sanitize_callback' => 'absint',
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_cart_chat_order',
			array(
				'type'              => 'string',
				'default'           => 'chat-first',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_cart_chat_hide_mobile',
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_cart_chat_stack_vertical',
			array(
				'type'              => 'boolean',
				'default'           => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
			)
		);

		// ========================================
		// Aparência do Chat (centralizado)
		// ========================================
		register_setting(
			'hng_commerce_settings',
			'hng_live_chat_position',
			array(
				'type'              => 'string',
				'default'           => 'bottom-right',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_live_chat_primary_color',
			array(
				'type'              => 'string',
				'default'           => '#2984f1',
				'sanitize_callback' => 'sanitize_hex_color',
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_live_chat_bubble_icon',
			array(
				'type'              => 'string',
				'default'           => 'chat',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_live_chat_start_button_color',
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_hex_color',
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_live_chat_button_text_color',
			array(
				'type'              => 'string',
				'default'           => '#ffffff',
				'sanitize_callback' => 'sanitize_hex_color',
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_live_chat_header_color',
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_hex_color',
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_live_chat_header_text_color',
			array(
				'type'              => 'string',
				'default'           => '#ffffff',
				'sanitize_callback' => 'sanitize_hex_color',
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_live_chat_bg_color',
			array(
				'type'              => 'string',
				'default'           => '#ffffff',
				'sanitize_callback' => 'sanitize_hex_color',
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_live_chat_message_text_color',
			array(
				'type'              => 'string',
				'default'           => '#333333',
				'sanitize_callback' => 'sanitize_hex_color',
			)
		);

		// ========================================
		// Novas opções de cor do Carrinho
		// ========================================
		register_setting(
			'hng_commerce_settings',
			'hng_cart_button_text_color',
			array(
				'type'              => 'string',
				'default'           => '#ffffff',
				'sanitize_callback' => 'sanitize_hex_color',
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_cart_header_text_color',
			array(
				'type'              => 'string',
				'default'           => '#1f2937',
				'sanitize_callback' => 'sanitize_hex_color',
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_cart_gradient_enabled',
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_cart_gradient_color1',
			array(
				'type'              => 'string',
				'default'           => '#2271b1',
				'sanitize_callback' => 'sanitize_hex_color',
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_cart_gradient_color2',
			array(
				'type'              => 'string',
				'default'           => '#00c6ff',
				'sanitize_callback' => 'sanitize_hex_color',
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_cart_gradient_direction',
			array(
				'type'              => 'string',
				'default'           => '135',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_cart_gradient_balance',
			array(
				'type'              => 'integer',
				'default'           => 50,
				'sanitize_callback' => 'absint',
			)
		);

		// ========================================
		// Novas opções de tipografia/design do Chat
		// ========================================
		register_setting(
			'hng_commerce_settings',
			'hng_live_chat_border_color',
			array(
				'type'              => 'string',
				'default'           => '#e5e7eb',
				'sanitize_callback' => 'sanitize_hex_color',
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_live_chat_radius',
			array(
				'type'              => 'integer',
				'default'           => 16,
				'sanitize_callback' => 'absint',
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_live_chat_font_family',
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_live_chat_font_size',
			array(
				'type'              => 'integer',
				'default'           => 14,
				'sanitize_callback' => 'absint',
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_live_chat_custom_css',
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_textarea_field',
			)
		);

		// Gradient registrations for chat (centralized here)
		register_setting(
			'hng_commerce_settings',
			'hng_live_chat_gradient_enabled',
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_live_chat_gradient_color1',
			array(
				'type'              => 'string',
				'default'           => '#2984f1',
				'sanitize_callback' => 'sanitize_hex_color',
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_live_chat_gradient_color2',
			array(
				'type'              => 'string',
				'default'           => '#00c6ff',
				'sanitize_callback' => 'sanitize_hex_color',
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_live_chat_gradient_direction',
			array(
				'type'              => 'string',
				'default'           => '135',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_setting(
			'hng_commerce_settings',
			'hng_live_chat_gradient_balance',
			array(
				'type'              => 'integer',
				'default'           => 50,
				'sanitize_callback' => 'absint',
			)
		);
	}

	/**
	 * Sanitiza o tipo de layout
	 */
	public static function sanitize_layout_type( $value ) {
		$allowed = array( 'default', 'sidebar', 'drawer', 'modal', 'popup', 'sticky' );
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
		$allowed = array( 'small', 'medium', 'large' );
		return in_array( $value, $allowed, true ) ? $value : 'medium';
	}

	/**
	 * Sanitiza estilo dos botões
	 */
	public static function sanitize_button_style( $value ) {
		$allowed = array( 'square', 'rounded', 'pill' );
		return in_array( $value, $allowed, true ) ? $value : 'rounded';
	}

	/**
	 * Sanitiza cor hex
	 */
	private static function sanitize_color_with_default( $value, $default ) {
		$color = sanitize_hex_color( $value );
		return $color ? $color : $default;
	}

	/**
	 * Sanitiza cor primária do carrinho
	 */
	public static function sanitize_primary_color( $value ) {
		return self::sanitize_color_with_default( $value, '#2271b1' );
	}

	/**
	 * Sanitiza cor primária escura do carrinho
	 */
	public static function sanitize_primary_dark_color( $value ) {
		return self::sanitize_color_with_default( $value, '#135e96' );
	}

	/**
	 * Sanitiza cor de destaque
	 */
	public static function sanitize_accent_color( $value ) {
		return self::sanitize_color_with_default( $value, '#FF7A00' );
	}

	/**
	 * Sanitiza cor de texto
	 */
	public static function sanitize_text_color( $value ) {
		return self::sanitize_color_with_default( $value, '#1f2937' );
	}

	/**
	 * Sanitiza cor de superfície
	 */
	public static function sanitize_surface_color( $value ) {
		return self::sanitize_color_with_default( $value, '#ffffff' );
	}

	/**
	 * Sanitiza cor de fundo do header
	 */
	public static function sanitize_header_bg_color( $value ) {
		return self::sanitize_color_with_default( $value, '#f9fafb' );
	}

	/**
	 * Sanitiza cor de borda
	 */
	public static function sanitize_border_color( $value ) {
		return self::sanitize_color_with_default( $value, '#e5e7eb' );
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
		$allowed = array( 'left', 'center', 'right' );
		return in_array( $value, $allowed, true ) ? $value : 'center';
	}

	/**
	 * Renderiza a tab de layouts
	 */
	public static function render_tab() {
		$current_layout   = get_option( 'hng_cart_display_type', 'sidebar' );
		$current_position = get_option( 'hng_cart_position', 'bottom-right' );
		$animations       = get_option( 'hng_cart_animation', true );
		$primary_color    = get_option( 'hng_cart_primary_color', '#2271b1' );
		$primary_dark     = get_option( 'hng_cart_primary_dark_color', '#135e96' );
		$accent_color     = get_option( 'hng_cart_accent_color', '#FF7A00' );
		$text_color       = get_option( 'hng_cart_text_color', '#1f2937' );
		$surface_color    = get_option( 'hng_cart_surface_color', '#ffffff' );
		$radius           = get_option( 'hng_cart_radius', 8 );
		$font_family      = get_option( 'hng_cart_font_family', '' );
		$font_size        = get_option( 'hng_cart_font_size', 14 );
		$button_align     = get_option( 'hng_cart_button_align', 'center' );
		$custom_css       = get_option( 'hng_cart_custom_css', '' );
		$cart_btn_text    = get_option( 'hng_cart_button_text_color', '#ffffff' );
		$cart_hdr_text    = get_option( 'hng_cart_header_text_color', '#1f2937' );

		// Cart gradient options
		$cart_grad_on  = get_option( 'hng_cart_gradient_enabled', false );
		$cart_grad_c1  = get_option( 'hng_cart_gradient_color1', '#2271b1' );
		$cart_grad_c2  = get_option( 'hng_cart_gradient_color2', '#00c6ff' );
		$cart_grad_dir = get_option( 'hng_cart_gradient_direction', '135' );
		$cart_grad_bal = get_option( 'hng_cart_gradient_balance', 50 );

		// Chat design options
		$chat_primary     = get_option( 'hng_live_chat_primary_color', '#2984f1' );
		$chat_btn_text    = get_option( 'hng_live_chat_button_text_color', '#ffffff' );
		$chat_header      = get_option( 'hng_live_chat_header_color', '#2984f1' );
		$chat_hdr_text    = get_option( 'hng_live_chat_header_text_color', '#ffffff' );
		$chat_bg          = get_option( 'hng_live_chat_bg_color', '#ffffff' );
		$chat_msg_text    = get_option( 'hng_live_chat_message_text_color', '#333333' );
		$chat_start_btn   = get_option( 'hng_live_chat_start_button_color', '#28a745' );
		$chat_border      = get_option( 'hng_live_chat_border_color', '#e5e7eb' );
		$chat_radius      = get_option( 'hng_live_chat_radius', 16 );
		$chat_font_family = get_option( 'hng_live_chat_font_family', '' );
		$chat_font_size   = get_option( 'hng_live_chat_font_size', 14 );
		$chat_custom_css  = get_option( 'hng_live_chat_custom_css', '' );

		// Chat gradient options
		$chat_grad_on  = get_option( 'hng_live_chat_gradient_enabled', false );
		$chat_grad_c1  = get_option( 'hng_live_chat_gradient_color1', '#2984f1' );
		$chat_grad_c2  = get_option( 'hng_live_chat_gradient_color2', '#00c6ff' );
		$chat_grad_dir = get_option( 'hng_live_chat_gradient_direction', '135' );
		$chat_grad_bal = get_option( 'hng_live_chat_gradient_balance', 50 );

		// Chat sync options
		$sync_chat        = get_option( 'hng_cart_sync_chat', false );
		$chat_order       = get_option( 'hng_cart_chat_order', 'chat-first' );
		$chat_spacing     = get_option( 'hng_cart_chat_spacing', 10 );
		$chat_stack       = get_option( 'hng_cart_chat_stack_vertical', true );
		$chat_hide_mobile = get_option( 'hng_cart_chat_hide_mobile', false );
		$hover_enabled    = get_option( 'hng_cart_hover_text_enabled', true );
		$hover_text       = get_option( 'hng_cart_hover_text', 'Carrinho' );

		$home_url      = home_url( '/' );
		$preview_query = array(
			'hng_cart_preview'             => 1,
			'hng_cart_display_type'        => $current_layout,
			'hng_cart_position'            => $current_position,
			'hng_cart_animation'           => $animations ? 1 : 0,
			'hng_cart_primary_color'       => $primary_color,
			'hng_cart_primary_dark_color'  => $primary_dark,
			'hng_cart_accent_color'        => $accent_color,
			'hng_cart_text_color'          => $text_color,
			'hng_cart_surface_color'       => $surface_color,
			'hng_cart_radius'              => absint( $radius ),
			'hng_cart_font_family'         => $font_family,
			'hng_cart_font_size'           => absint( $font_size ),
			'hng_cart_button_align'        => $button_align,
			// Chat sync parameters
			'hng_cart_sync_chat'           => $sync_chat ? 1 : 0,
			'hng_cart_chat_order'          => $chat_order,
			'hng_cart_chat_spacing'        => absint( $chat_spacing ),
			'hng_cart_chat_stack_vertical' => $chat_stack ? 1 : 0,
			'hng_cart_chat_hide_mobile'    => $chat_hide_mobile ? 1 : 0,
			// Hover text parameters
			'hng_cart_hover_text_enabled'  => $hover_enabled ? 1 : 0,
			'hng_cart_hover_text'          => $hover_text,
		);
		$preview_url   = add_query_arg( $preview_query, $home_url );

		?>
		<div class="hng-layouts-settings">
			<div style="margin-bottom: 20px;">
				<h2 style="display: inline-flex; align-items: center; margin: 0;">
					<?php esc_html_e( 'Layouts do Carrinho', 'hng-commerce' ); ?>
					<?php
					if ( function_exists( 'hng_admin_tooltip' ) ) {
						echo wp_kses_post(
							hng_admin_tooltip(
								'🛒 Layouts do Carrinho Flutuante',
								array(
									array(
										'icon'    => '📦',
										'title'   => 'Tipos de Layout',
										'content' => '<strong>Sidebar:</strong> Painel lateral deslizante<br><strong>Modal:</strong> Popup centralizado<br><strong>Drawer:</strong> Gaveta inferior<br><strong>Popup:</strong> Balão sobre o botão<br><strong>Sticky Badge:</strong> Ícone fixo compacto',
									),
									array(
										'icon'    => '🎨',
										'title'   => 'Personalização',
										'content' => 'Cores, fontes, bordas e CSS personalizado. Todas as alterações aparecem em tempo real no preview.',
									),
									array(
										'icon'    => '💬',
										'title'   => 'Sincronização com Chat',
										'content' => 'Combine o botão do carrinho com o chat ao vivo em um container unificado. Defina ordem e espaçamento.',
									),
									array(
										'icon'    => '📱',
										'title'   => 'Responsividade',
										'content' => 'Posição e comportamento podem variar entre desktop e mobile. Configure ocultação em dispositivos móveis.',
									),
								),
								array(
									'title' => '💡 Recomendações',
									'items' => array(
										array(
											'label' => 'E-commerce tradicional',
											'text'  => 'Use Sidebar ou Modal',
										),
										array(
											'label' => 'Landing page',
											'text'  => 'Use Sticky Badge (discreto)',
										),
										array(
											'label' => 'Mobile-first',
											'text'  => 'Use Drawer (gaveta inferior)',
										),
									),
								),
								480
							)
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
						</div>
						
						<!-- ============================================== -->
						<!-- SEÇÃO: EFEITOS HOVER DO CARRINHO              -->
						<!-- ============================================== -->
						<fieldset class="hng-fieldset hng-fieldset-cart-hover">
							<legend>🛒 <?php esc_html_e( 'Efeitos Hover - Carrinho', 'hng-commerce' ); ?></legend>
							
							<div class="hng-fieldset-content">
								<div class="hng-inline-group">
									<div class="hng-form-group hng-form-group-checkbox">
										<label for="hng_cart_hover_text_enabled">
											<input type="hidden" name="hng_cart_hover_text_enabled" value="0">
											<input type="checkbox" 
													name="hng_cart_hover_text_enabled" 
													id="hng_cart_hover_text_enabled" 
													value="1" 
													<?php checked( get_option( 'hng_cart_hover_text_enabled', false ), true ); ?>>
											<?php esc_html_e( 'Exibir texto ao passar mouse', 'hng-commerce' ); ?>
										</label>
									</div>
									
									<div class="hng-form-group">
										<input type="text" 
												name="hng_cart_hover_text" 
												id="hng_cart_hover_text" 
												value="<?php echo esc_attr( get_option( 'hng_cart_hover_text', 'Carrinho' ) ); ?>" 
												class="regular-text"
												placeholder="Carrinho">
									</div>
								</div>
								
								<div class="hng-form-group">
									<label for="hng_cart_hover_effect">
										<strong><?php esc_html_e( 'Efeito de animação', 'hng-commerce' ); ?></strong>
									</label>
									<select name="hng_cart_hover_effect" id="hng_cart_hover_effect" class="regular-text">
										<option value="none" <?php selected( get_option( 'hng_cart_hover_effect', 'none' ), 'none' ); ?>><?php esc_html_e( 'Nenhum', 'hng-commerce' ); ?></option>
										<option value="scale" <?php selected( get_option( 'hng_cart_hover_effect', 'none' ), 'scale' ); ?>><?php esc_html_e( 'Escalar', 'hng-commerce' ); ?></option>
										<option value="pulse" <?php selected( get_option( 'hng_cart_hover_effect', 'none' ), 'pulse' ); ?>><?php esc_html_e( 'Pulsar', 'hng-commerce' ); ?></option>
										<option value="bounce" <?php selected( get_option( 'hng_cart_hover_effect', 'none' ), 'bounce' ); ?>><?php esc_html_e( 'Quicar', 'hng-commerce' ); ?></option>
										<option value="shake" <?php selected( get_option( 'hng_cart_hover_effect', 'none' ), 'shake' ); ?>><?php esc_html_e( 'Tremer', 'hng-commerce' ); ?></option>
										<option value="glow" <?php selected( get_option( 'hng_cart_hover_effect', 'none' ), 'glow' ); ?>><?php esc_html_e( 'Brilhar', 'hng-commerce' ); ?></option>
										<option value="rotate" <?php selected( get_option( 'hng_cart_hover_effect', 'none' ), 'rotate' ); ?>><?php esc_html_e( 'Girar', 'hng-commerce' ); ?></option>
									</select>
								</div>
							</div>
						</fieldset>

						<!-- ============================================== -->
						<!-- SEÇÃO: DURAÇÕES DE ANIMAÇÃO                   -->
						<!-- ============================================== -->
						<fieldset class="hng-fieldset hng-fieldset-animation-duration">
							<legend>⏱️ <?php esc_html_e( 'Duração das Animações', 'hng-commerce' ); ?></legend>
							
							<div class="hng-fieldset-content">
								<p class="description" style="margin-bottom: 15px;">
									<?php esc_html_e( 'Configure a velocidade das animações de entrada do carrinho e efeitos hover. Valores em milissegundos (ms).', 'hng-commerce' ); ?>
								</p>
								
								<div class="hng-inline-group">
									<div class="hng-form-group">
										<label for="hng_cart_animation_duration">
											<strong><?php esc_html_e( 'Animação de Entrada', 'hng-commerce' ); ?></strong>
										</label>
										<div class="hng-input-with-unit">
											<input type="number" 
													name="hng_cart_animation_duration" 
													id="hng_cart_animation_duration" 
													value="<?php echo esc_attr( get_option( 'hng_cart_animation_duration', 400 ) ); ?>" 
													class="small-text"
													min="0"
													max="2000"
													step="50">
											<span class="unit">ms</span>
										</div>
										<p class="description"><?php esc_html_e( 'Sidebar, Drawer, Modal, Popup (padrão: 400ms)', 'hng-commerce' ); ?></p>
									</div>
									
									<div class="hng-form-group">
										<label for="hng_cart_hover_text_animation_duration">
											<strong><?php esc_html_e( 'Efeito Hover Text', 'hng-commerce' ); ?></strong>
										</label>
										<div class="hng-input-with-unit">
											<input type="number" 
													name="hng_cart_hover_text_animation_duration" 
													id="hng_cart_hover_text_animation_duration" 
													value="<?php echo esc_attr( get_option( 'hng_cart_hover_text_animation_duration', 300 ) ); ?>" 
													class="small-text"
													min="0"
													max="2000"
													step="50">
											<span class="unit">ms</span>
										</div>
										<p class="description"><?php esc_html_e( 'Texto ao passar o mouse (padrão: 300ms)', 'hng-commerce' ); ?></p>
									</div>
								</div>
							</div>
						</fieldset>

						<!-- ============================================== -->
						<!-- SEÇÃO: EFEITOS HOVER DO CHAT                  -->
						<!-- ============================================== -->
						<fieldset class="hng-fieldset hng-fieldset-chat-hover">
							<legend>💬 <?php esc_html_e( 'Efeitos Hover - Chat', 'hng-commerce' ); ?></legend>
							
							<div class="hng-fieldset-content">
								<div class="hng-inline-group">
									<div class="hng-form-group hng-form-group-checkbox">
										<label for="hng_live_chat_hover_text_enabled">
											<input type="hidden" name="hng_live_chat_hover_text_enabled" value="0">
											<input type="checkbox" 
													name="hng_live_chat_hover_text_enabled" 
													id="hng_live_chat_hover_text_enabled" 
													value="1" 
													<?php checked( get_option( 'hng_live_chat_hover_text_enabled', false ), true ); ?>>
											<?php esc_html_e( 'Exibir texto ao passar mouse', 'hng-commerce' ); ?>
										</label>
									</div>
									
									<div class="hng-form-group">
										<input type="text" 
												name="hng_live_chat_hover_text" 
												id="hng_live_chat_hover_text" 
												value="<?php echo esc_attr( get_option( 'hng_live_chat_hover_text', 'Chat' ) ); ?>" 
												class="regular-text"
												placeholder="Chat">
									</div>
								</div>
								
								<div class="hng-form-group">
									<label for="hng_live_chat_hover_effect">
										<strong><?php esc_html_e( 'Efeito de animação', 'hng-commerce' ); ?></strong>
									</label>
									<select name="hng_live_chat_hover_effect" id="hng_live_chat_hover_effect" class="regular-text">
										<option value="none" <?php selected( get_option( 'hng_live_chat_hover_effect', 'none' ), 'none' ); ?>><?php esc_html_e( 'Nenhum', 'hng-commerce' ); ?></option>
										<option value="scale" <?php selected( get_option( 'hng_live_chat_hover_effect', 'none' ), 'scale' ); ?>><?php esc_html_e( 'Escalar', 'hng-commerce' ); ?></option>
										<option value="pulse" <?php selected( get_option( 'hng_live_chat_hover_effect', 'none' ), 'pulse' ); ?>><?php esc_html_e( 'Pulsar', 'hng-commerce' ); ?></option>
										<option value="bounce" <?php selected( get_option( 'hng_live_chat_hover_effect', 'none' ), 'bounce' ); ?>><?php esc_html_e( 'Quicar', 'hng-commerce' ); ?></option>
										<option value="shake" <?php selected( get_option( 'hng_live_chat_hover_effect', 'none' ), 'shake' ); ?>><?php esc_html_e( 'Tremer', 'hng-commerce' ); ?></option>
										<option value="glow" <?php selected( get_option( 'hng_live_chat_hover_effect', 'none' ), 'glow' ); ?>><?php esc_html_e( 'Brilhar', 'hng-commerce' ); ?></option>
										<option value="rotate" <?php selected( get_option( 'hng_live_chat_hover_effect', 'none' ), 'rotate' ); ?>><?php esc_html_e( 'Girar', 'hng-commerce' ); ?></option>
									</select>
								</div>
							</div>
						</fieldset>

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

						<!-- ============================================== -->
						<!-- 🛒 CORES E DESIGN DO CARRINHO                 -->
						<!-- ============================================== -->
						<div class="hng-design-panel hng-design-panel--cart">
							<div class="hng-design-panel__header">
								<h3>🛒 <?php esc_html_e( 'Cores e Design do Carrinho', 'hng-commerce' ); ?></h3>
								<p class="description">
									<?php esc_html_e( 'Personalize as cores, tipografia e visual do carrinho flutuante.', 'hng-commerce' ); ?>
								</p>
							</div>

							<!-- Cores do Carrinho -->
							<div class="hng-design-panel__section">
								<h4 class="hng-design-panel__subtitle">
									<span class="dashicons dashicons-art" style="color: var(--hng-cart-primary, #2271b1);"></span>
									<?php esc_html_e( 'Cores', 'hng-commerce' ); ?>
								</h4>
								<div class="hng-design-grid hng-design-grid--3">
									<div class="hng-design-card">
										<label for="hng_cart_primary_color"><?php esc_html_e( 'Cor Primária', 'hng-commerce' ); ?></label>
										<input type="color" id="hng_cart_primary_color" name="hng_cart_primary_color" value="<?php echo esc_attr( $primary_color ); ?>">
									</div>
									<div class="hng-design-card">
										<label for="hng_cart_primary_dark_color"><?php esc_html_e( 'Cor Primária Escura (Hover)', 'hng-commerce' ); ?></label>
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
										<label for="hng_cart_button_text_color"><?php esc_html_e( 'Cor do Texto do Botão', 'hng-commerce' ); ?></label>
										<input type="color" id="hng_cart_button_text_color" name="hng_cart_button_text_color" value="<?php echo esc_attr( $cart_btn_text ); ?>">
									</div>
									<div class="hng-design-card">
										<label for="hng_cart_surface_color"><?php esc_html_e( 'Cor de Fundo', 'hng-commerce' ); ?></label>
										<input type="color" id="hng_cart_surface_color" name="hng_cart_surface_color" value="<?php echo esc_attr( $surface_color ); ?>">
									</div>
									<div class="hng-design-card">
										<label for="hng_cart_header_bg"><?php esc_html_e( 'Cor do Cabeçalho', 'hng-commerce' ); ?></label>
										<input type="color" id="hng_cart_header_bg" name="hng_cart_header_bg" value="<?php echo esc_attr( get_option( 'hng_cart_header_bg', '#f9fafb' ) ); ?>">
									</div>
									<div class="hng-design-card">
										<label for="hng_cart_header_text_color"><?php esc_html_e( 'Cor do Texto do Cabeçalho', 'hng-commerce' ); ?></label>
										<input type="color" id="hng_cart_header_text_color" name="hng_cart_header_text_color" value="<?php echo esc_attr( $cart_hdr_text ); ?>">
									</div>
									<div class="hng-design-card">
										<label for="hng_cart_border_color"><?php esc_html_e( 'Cor das Bordas', 'hng-commerce' ); ?></label>
										<input type="color" id="hng_cart_border_color" name="hng_cart_border_color" value="<?php echo esc_attr( get_option( 'hng_cart_border_color', '#e5e7eb' ) ); ?>">
									</div>
								</div>
							</div>

							<!-- Degradê do Carrinho -->
							<div class="hng-design-panel__section">
								<h4 class="hng-design-panel__subtitle">
									<span class="dashicons dashicons-image-filter" style="color: var(--hng-cart-primary, #2271b1);"></span>
									<?php esc_html_e( 'Degradê (Gradient)', 'hng-commerce' ); ?>
								</h4>
								<p class="description" style="margin-bottom: 12px;">
									<?php esc_html_e( 'Ative para usar um degradê de cores no botão flutuante do carrinho.', 'hng-commerce' ); ?>
								</p>
								<div class="hng-design-grid hng-design-grid--3">
									<div class="hng-design-card">
										<label for="hng_cart_gradient_enabled">
											<input type="hidden" name="hng_cart_gradient_enabled" value="0">
											<input type="checkbox" id="hng_cart_gradient_enabled" name="hng_cart_gradient_enabled" value="1" <?php checked( $cart_grad_on, true ); ?>>
											<?php esc_html_e( 'Ativar Degradê', 'hng-commerce' ); ?>
										</label>
									</div>
									<div class="hng-design-card">
										<label for="hng_cart_gradient_color1"><?php esc_html_e( 'Cor 1 do Degradê', 'hng-commerce' ); ?></label>
										<input type="color" id="hng_cart_gradient_color1" name="hng_cart_gradient_color1" value="<?php echo esc_attr( $cart_grad_c1 ); ?>">
									</div>
									<div class="hng-design-card">
										<label for="hng_cart_gradient_color2"><?php esc_html_e( 'Cor 2 do Degradê', 'hng-commerce' ); ?></label>
										<input type="color" id="hng_cart_gradient_color2" name="hng_cart_gradient_color2" value="<?php echo esc_attr( $cart_grad_c2 ); ?>">
									</div>
									<div class="hng-design-card">
										<label for="hng_cart_gradient_direction"><?php esc_html_e( 'Direção do Degradê', 'hng-commerce' ); ?></label>
										<select id="hng_cart_gradient_direction" name="hng_cart_gradient_direction">
											<?php
											$cart_grad_options = array(
												'0'   => __( '↑ Para cima (0°)', 'hng-commerce' ),
												'45'  => __( '↗ Diagonal superior (45°)', 'hng-commerce' ),
												'90'  => __( '→ Para direita (90°)', 'hng-commerce' ),
												'135' => __( '↘ Diagonal inferior (135°)', 'hng-commerce' ),
												'180' => __( '↓ Para baixo (180°)', 'hng-commerce' ),
												'225' => __( '↙ Diagonal esq. inferior (225°)', 'hng-commerce' ),
												'270' => __( '← Para esquerda (270°)', 'hng-commerce' ),
												'315' => __( '↖ Diagonal esq. superior (315°)', 'hng-commerce' ),
											);
											foreach ( $cart_grad_options as $gval => $glabel ) :
												?>
												<option value="<?php echo esc_attr( $gval ); ?>" <?php selected( $cart_grad_dir, $gval ); ?>><?php echo esc_html( $glabel ); ?></option>
											<?php endforeach; ?>
										</select>
									</div>
									<div class="hng-design-card" style="grid-column: span 2;">
										<label for="hng_cart_gradient_balance"><?php esc_html_e( 'Posição das Cores (%)', 'hng-commerce' ); ?></label>
										<input type="range" id="hng_cart_gradient_balance" name="hng_cart_gradient_balance" min="10" max="90" step="5" value="<?php echo esc_attr( $cart_grad_bal ); ?>" style="width: 100%;">
										<div style="display: flex; justify-content: space-between; font-size: 11px; color: #666;">
											<span><?php esc_html_e( 'Cor 1', 'hng-commerce' ); ?></span>
											<span class="hng-range-display" data-target="hng_cart_gradient_balance"><?php echo esc_html( $cart_grad_bal ); ?>%</span>
											<span><?php esc_html_e( 'Cor 2', 'hng-commerce' ); ?></span>
										</div>
									</div>
								</div>
							</div>

							<!-- Tipografia do Carrinho -->
							<div class="hng-design-panel__section">
								<h4 class="hng-design-panel__subtitle">
									<span class="dashicons dashicons-editor-textcolor" style="color: var(--hng-cart-primary, #2271b1);"></span>
									<?php esc_html_e( 'Tipografia e Forma', 'hng-commerce' ); ?>
								</h4>
								<div class="hng-design-grid">
									<div class="hng-design-card">
										<label for="hng_cart_font_family"><?php esc_html_e( 'Fonte (CSS font-family)', 'hng-commerce' ); ?></label>
										<input type="text" id="hng_cart_font_family" name="hng_cart_font_family" value="<?php echo esc_attr( $font_family ); ?>" placeholder="'Montserrat', sans-serif">
									</div>
									<div class="hng-design-card">
										<label for="hng_cart_font_size"><?php esc_html_e( 'Tamanho da Fonte (px)', 'hng-commerce' ); ?></label>
										<input type="number" id="hng_cart_font_size" name="hng_cart_font_size" min="10" max="24" value="<?php echo esc_attr( absint( $font_size ) ); ?>">
									</div>
									<div class="hng-design-card">
										<label for="hng_cart_radius"><?php esc_html_e( 'Arredondamento (px)', 'hng-commerce' ); ?></label>
										<input type="number" id="hng_cart_radius" name="hng_cart_radius" min="0" max="32" value="<?php echo esc_attr( absint( $radius ) ); ?>">
									</div>
									<div class="hng-design-card">
										<label for="hng_cart_button_align"><?php esc_html_e( 'Alinhamento dos Botões', 'hng-commerce' ); ?></label>
										<select id="hng_cart_button_align" name="hng_cart_button_align">
											<option value="left" <?php selected( $button_align, 'left' ); ?>><?php esc_html_e( 'Esquerda', 'hng-commerce' ); ?></option>
											<option value="center" <?php selected( $button_align, 'center' ); ?>><?php esc_html_e( 'Centro', 'hng-commerce' ); ?></option>
											<option value="right" <?php selected( $button_align, 'right' ); ?>><?php esc_html_e( 'Direita', 'hng-commerce' ); ?></option>
										</select>
									</div>
								</div>
							</div>

							<!-- CSS Personalizado do Carrinho -->
							<div class="hng-design-panel__section">
								<h4 class="hng-design-panel__subtitle">
									<span class="dashicons dashicons-editor-code" style="color: var(--hng-cart-primary, #2271b1);"></span>
									<?php esc_html_e( 'CSS Personalizado', 'hng-commerce' ); ?>
								</h4>
								<div class="hng-design-grid">
									<div class="hng-design-card" style="grid-column: span 2;">
										<textarea id="hng_cart_custom_css" name="hng_cart_custom_css" placeholder=".hng-cart-sidebar { box-shadow: none; }&#10;.hng-cart-trigger { border-radius: 999px; }"><?php echo esc_textarea( $custom_css ); ?></textarea>
									</div>
								</div>
							</div>
						</div>

						<!-- ============================================== -->
						<!-- 💬 CORES E DESIGN DO CHAT                     -->
						<!-- ============================================== -->
						<div class="hng-design-panel hng-design-panel--chat">
							<div class="hng-design-panel__header">
								<h3>💬 <?php esc_html_e( 'Cores e Design do Chat', 'hng-commerce' ); ?></h3>
								<p class="description">
									<?php esc_html_e( 'Personalize as cores, tipografia e visual do widget de chat ao vivo.', 'hng-commerce' ); ?>
								</p>
							</div>

							<!-- Aparência do Chat -->
							<div class="hng-design-panel__section">
								<h4 class="hng-design-panel__subtitle">
									<span class="dashicons dashicons-admin-settings" style="color: #2984f1;"></span>
									<?php esc_html_e( 'Aparência', 'hng-commerce' ); ?>
								</h4>
								<div class="hng-design-grid">
									<div class="hng-design-card">
										<label for="hng_live_chat_position"><?php esc_html_e( 'Posição do Widget', 'hng-commerce' ); ?></label>
										<select name="hng_live_chat_position" id="hng_live_chat_position">
											<option value="bottom-right" <?php selected( get_option( 'hng_live_chat_position', 'bottom-right' ), 'bottom-right' ); ?>>
												<?php esc_html_e( 'Inferior Direito', 'hng-commerce' ); ?>
											</option>
											<option value="bottom-left" <?php selected( get_option( 'hng_live_chat_position', 'bottom-right' ), 'bottom-left' ); ?>>
												<?php esc_html_e( 'Inferior Esquerdo', 'hng-commerce' ); ?>
											</option>
										</select>
									</div>
									<div class="hng-design-card">
										<label for="hng_live_chat_bubble_icon"><?php esc_html_e( 'Ícone do Botão', 'hng-commerce' ); ?></label>
										<select name="hng_live_chat_bubble_icon" id="hng_live_chat_bubble_icon">
											<option value="chat" <?php selected( get_option( 'hng_live_chat_bubble_icon', 'chat' ), 'chat' ); ?>>💬 <?php esc_html_e( 'Chat', 'hng-commerce' ); ?></option>
											<option value="message" <?php selected( get_option( 'hng_live_chat_bubble_icon', 'chat' ), 'message' ); ?>>✉️ <?php esc_html_e( 'Mensagem', 'hng-commerce' ); ?></option>
											<option value="support" <?php selected( get_option( 'hng_live_chat_bubble_icon', 'chat' ), 'support' ); ?>>🎧 <?php esc_html_e( 'Suporte', 'hng-commerce' ); ?></option>
											<option value="headset" <?php selected( get_option( 'hng_live_chat_bubble_icon', 'chat' ), 'headset' ); ?>>🎤 <?php esc_html_e( 'Headset', 'hng-commerce' ); ?></option>
											<option value="whatsapp" <?php selected( get_option( 'hng_live_chat_bubble_icon', 'chat' ), 'whatsapp' ); ?>>📱 <?php esc_html_e( 'WhatsApp', 'hng-commerce' ); ?></option>
											<option value="help" <?php selected( get_option( 'hng_live_chat_bubble_icon', 'chat' ), 'help' ); ?>>❓ <?php esc_html_e( 'Ajuda', 'hng-commerce' ); ?></option>
										</select>
									</div>
								</div>
							</div>

							<!-- Cores do Chat -->
							<div class="hng-design-panel__section">
								<h4 class="hng-design-panel__subtitle">
									<span class="dashicons dashicons-art" style="color: #2984f1;"></span>
									<?php esc_html_e( 'Cores', 'hng-commerce' ); ?>
								</h4>
								<div class="hng-design-grid hng-design-grid--3">
									<div class="hng-design-card">
										<label for="hng_live_chat_primary_color"><?php esc_html_e( 'Cor Principal', 'hng-commerce' ); ?></label>
										<input type="color" id="hng_live_chat_primary_color" name="hng_live_chat_primary_color" value="<?php echo esc_attr( $chat_primary ); ?>">
									</div>
									<div class="hng-design-card">
										<label for="hng_live_chat_button_text_color"><?php esc_html_e( 'Cor do Texto do Botão', 'hng-commerce' ); ?></label>
										<input type="color" id="hng_live_chat_button_text_color" name="hng_live_chat_button_text_color" value="<?php echo esc_attr( $chat_btn_text ); ?>">
									</div>
									<div class="hng-design-card">
										<label for="hng_live_chat_header_color"><?php esc_html_e( 'Cor do Cabeçalho', 'hng-commerce' ); ?></label>
										<input type="color" id="hng_live_chat_header_color" name="hng_live_chat_header_color" value="<?php echo esc_attr( $chat_header ); ?>">
									</div>
									<div class="hng-design-card">
										<label for="hng_live_chat_header_text_color"><?php esc_html_e( 'Cor do Texto do Cabeçalho', 'hng-commerce' ); ?></label>
										<input type="color" id="hng_live_chat_header_text_color" name="hng_live_chat_header_text_color" value="<?php echo esc_attr( $chat_hdr_text ); ?>">
									</div>
									<div class="hng-design-card">
										<label for="hng_live_chat_bg_color"><?php esc_html_e( 'Cor de Fundo', 'hng-commerce' ); ?></label>
										<input type="color" id="hng_live_chat_bg_color" name="hng_live_chat_bg_color" value="<?php echo esc_attr( $chat_bg ); ?>">
									</div>
									<div class="hng-design-card">
										<label for="hng_live_chat_message_text_color"><?php esc_html_e( 'Cor do Texto das Mensagens', 'hng-commerce' ); ?></label>
										<input type="color" id="hng_live_chat_message_text_color" name="hng_live_chat_message_text_color" value="<?php echo esc_attr( $chat_msg_text ); ?>">
									</div>
									<div class="hng-design-card">
										<label for="hng_live_chat_start_button_color"><?php esc_html_e( 'Cor do Botão Iniciar', 'hng-commerce' ); ?></label>
										<input type="color" id="hng_live_chat_start_button_color" name="hng_live_chat_start_button_color" value="<?php echo esc_attr( $chat_start_btn ); ?>">
										<p class="description" style="font-size: 10px;"><?php esc_html_e( 'Deixe vazio para usar a cor principal', 'hng-commerce' ); ?></p>
									</div>
									<div class="hng-design-card">
										<label for="hng_live_chat_border_color"><?php esc_html_e( 'Cor das Bordas', 'hng-commerce' ); ?></label>
										<input type="color" id="hng_live_chat_border_color" name="hng_live_chat_border_color" value="<?php echo esc_attr( $chat_border ); ?>">
									</div>
								</div>
							</div>

							<!-- Degradê do Chat -->
							<div class="hng-design-panel__section">
								<h4 class="hng-design-panel__subtitle">
									<span class="dashicons dashicons-image-filter" style="color: #2984f1;"></span>
									<?php esc_html_e( 'Degradê (Gradient)', 'hng-commerce' ); ?>
								</h4>
								<p class="description" style="margin-bottom: 12px;">
									<?php esc_html_e( 'Ative para usar um degradê de cores no botão flutuante e no cabeçalho do chat.', 'hng-commerce' ); ?>
								</p>
								<div class="hng-design-grid hng-design-grid--3">
									<div class="hng-design-card">
										<label for="hng_live_chat_gradient_enabled">
											<input type="hidden" name="hng_live_chat_gradient_enabled" value="0">
											<input type="checkbox" id="hng_live_chat_gradient_enabled" name="hng_live_chat_gradient_enabled" value="1" <?php checked( $chat_grad_on, true ); ?>>
											<?php esc_html_e( 'Ativar Degradê', 'hng-commerce' ); ?>
										</label>
									</div>
									<div class="hng-design-card">
										<label for="hng_live_chat_gradient_color1"><?php esc_html_e( 'Cor 1 do Degradê', 'hng-commerce' ); ?></label>
										<input type="color" id="hng_live_chat_gradient_color1" name="hng_live_chat_gradient_color1" value="<?php echo esc_attr( $chat_grad_c1 ); ?>">
									</div>
									<div class="hng-design-card">
										<label for="hng_live_chat_gradient_color2"><?php esc_html_e( 'Cor 2 do Degradê', 'hng-commerce' ); ?></label>
										<input type="color" id="hng_live_chat_gradient_color2" name="hng_live_chat_gradient_color2" value="<?php echo esc_attr( $chat_grad_c2 ); ?>">
									</div>
									<div class="hng-design-card">
										<label for="hng_live_chat_gradient_direction"><?php esc_html_e( 'Direção do Degradê', 'hng-commerce' ); ?></label>
										<select id="hng_live_chat_gradient_direction" name="hng_live_chat_gradient_direction">
											<?php
											$chat_grad_options = array(
												'0'   => __( '↑ Para cima (0°)', 'hng-commerce' ),
												'45'  => __( '↗ Diagonal superior (45°)', 'hng-commerce' ),
												'90'  => __( '→ Para direita (90°)', 'hng-commerce' ),
												'135' => __( '↘ Diagonal inferior (135°)', 'hng-commerce' ),
												'180' => __( '↓ Para baixo (180°)', 'hng-commerce' ),
												'225' => __( '↙ Diagonal esq. inferior (225°)', 'hng-commerce' ),
												'270' => __( '← Para esquerda (270°)', 'hng-commerce' ),
												'315' => __( '↖ Diagonal esq. superior (315°)', 'hng-commerce' ),
											);
											foreach ( $chat_grad_options as $gval => $glabel ) :
												?>
												<option value="<?php echo esc_attr( $gval ); ?>" <?php selected( $chat_grad_dir, $gval ); ?>><?php echo esc_html( $glabel ); ?></option>
											<?php endforeach; ?>
										</select>
									</div>
									<div class="hng-design-card" style="grid-column: span 2;">
										<label for="hng_live_chat_gradient_balance"><?php esc_html_e( 'Posição das Cores (%)', 'hng-commerce' ); ?></label>
										<input type="range" id="hng_live_chat_gradient_balance" name="hng_live_chat_gradient_balance" min="10" max="90" step="5" value="<?php echo esc_attr( $chat_grad_bal ); ?>" style="width: 100%;">
										<div style="display: flex; justify-content: space-between; font-size: 11px; color: #666;">
											<span><?php esc_html_e( 'Cor 1', 'hng-commerce' ); ?></span>
											<span class="hng-range-display" data-target="hng_live_chat_gradient_balance"><?php echo esc_html( $chat_grad_bal ); ?>%</span>
											<span><?php esc_html_e( 'Cor 2', 'hng-commerce' ); ?></span>
										</div>
									</div>
								</div>
							</div>

							<!-- Tipografia do Chat -->
							<div class="hng-design-panel__section">
								<h4 class="hng-design-panel__subtitle">
									<span class="dashicons dashicons-editor-textcolor" style="color: #2984f1;"></span>
									<?php esc_html_e( 'Tipografia e Forma', 'hng-commerce' ); ?>
								</h4>
								<div class="hng-design-grid">
									<div class="hng-design-card">
										<label for="hng_live_chat_font_family"><?php esc_html_e( 'Fonte (CSS font-family)', 'hng-commerce' ); ?></label>
										<input type="text" id="hng_live_chat_font_family" name="hng_live_chat_font_family" value="<?php echo esc_attr( $chat_font_family ); ?>" placeholder="'Inter', sans-serif">
									</div>
									<div class="hng-design-card">
										<label for="hng_live_chat_font_size"><?php esc_html_e( 'Tamanho da Fonte (px)', 'hng-commerce' ); ?></label>
										<input type="number" id="hng_live_chat_font_size" name="hng_live_chat_font_size" min="10" max="24" value="<?php echo esc_attr( absint( $chat_font_size ) ); ?>">
									</div>
									<div class="hng-design-card">
										<label for="hng_live_chat_radius"><?php esc_html_e( 'Arredondamento (px)', 'hng-commerce' ); ?></label>
										<input type="number" id="hng_live_chat_radius" name="hng_live_chat_radius" min="0" max="32" value="<?php echo esc_attr( absint( $chat_radius ) ); ?>">
									</div>
								</div>
							</div>

							<!-- CSS Personalizado do Chat -->
							<div class="hng-design-panel__section">
								<h4 class="hng-design-panel__subtitle">
									<span class="dashicons dashicons-editor-code" style="color: #2984f1;"></span>
									<?php esc_html_e( 'CSS Personalizado', 'hng-commerce' ); ?>
								</h4>
								<div class="hng-design-grid">
									<div class="hng-design-card" style="grid-column: span 2;">
										<textarea id="hng_live_chat_custom_css" name="hng_live_chat_custom_css" placeholder=".hng-chat-bubble { box-shadow: 0 4px 12px rgba(0,0,0,0.2); }&#10;.hng-chat-header { border-radius: 16px 16px 0 0; }"><?php echo esc_textarea( $chat_custom_css ); ?></textarea>
									</div>
								</div>
							</div>
						</div>

						<!-- Range slider JS for gradient balance -->
						<script>
						(function() {
							document.querySelectorAll('.hng-range-display').forEach(function(el) {
								var targetId = el.getAttribute('data-target');
								var slider = document.getElementById(targetId);
								if (slider) {
									slider.addEventListener('input', function() {
										el.textContent = this.value + '%';
									});
								}
							});
						})();
						</script>

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
									<button type="button" class="button hng-device-btn" data-width="1200" data-height="760">
										<?php esc_html_e( 'Desktop', 'hng-commerce' ); ?>
									</button>
									<button type="button" class="button hng-device-btn" data-width="820" data-height="760">
										<?php esc_html_e( 'Tablet', 'hng-commerce' ); ?>
									</button>
									<button type="button" class="button hng-device-btn is-active" data-width="390" data-height="760">
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

							<button type="button" class="button hng-sticky-toggle" id="hng-sticky-toggle" title="<?php esc_attr_e( 'Fixar preview no topo ao rolar', 'hng-commerce' ); ?>">
								<span class="dashicons dashicons-admin-post" style="vertical-align: middle; margin-right: 2px;"></span>
								<span class="hng-sticky-label"><?php esc_html_e( 'Fixar Preview', 'hng-commerce' ); ?></span>
							</button>
							</div><!-- /.hng-live-preview-toolbar -->

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
							</div><!-- /.hng-live-preview-frame -->
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

			.hng-config-panel {
				grid-column: 1;
				grid-row: 1;
			}

			.hng-preview-panel {
				grid-column: 2;
				grid-row: 1;
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

			.hng-design-grid--3 {
				grid-template-columns: repeat(3, minmax(0, 1fr));
			}

			@media (max-width: 600px) {
				.hng-design-grid--3 {
					grid-template-columns: repeat(2, minmax(0, 1fr));
				}
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

			.hng-design-card input[type="color"] {
				height: 44px;
				padding: 4px;
				cursor: pointer;
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

			/* ============================================== */
			/* Painéis de Design (Carrinho / Chat)            */
			/* ============================================== */
			.hng-design-panel {
				border: 1px solid #e5e7eb;
				border-radius: 12px;
				padding: 0;
				margin: 24px 0;
				overflow: hidden;
				background: #fff;
				box-shadow: 0 1px 3px rgba(0,0,0,0.04);
			}

			.hng-design-panel--cart {
				border-left: 4px solid #2AFFA3;
			}

			.hng-design-panel--chat {
				border-left: 4px solid #2984f1;
			}

			.hng-design-panel__header {
				padding: 20px 24px 8px;
				border-bottom: 1px solid #f3f4f6;
				background: linear-gradient(to bottom, #fafbfc, #fff);
			}

			.hng-design-panel__header h3 {
				margin: 0 0 4px 0;
				font-size: 17px;
				font-weight: 700;
				color: #111827;
			}

			.hng-design-panel__header .description {
				margin: 0 0 12px 0;
				font-size: 13px;
				color: #6b7280;
			}

			.hng-design-panel__section {
				padding: 16px 24px;
				border-top: 1px solid #f3f4f6;
			}

			.hng-design-panel__section:first-of-type {
				border-top: none;
			}

			.hng-design-panel__subtitle {
				display: flex;
				align-items: center;
				gap: 8px;
				margin: 0 0 4px 0;
				font-size: 14px;
				font-weight: 600;
				color: #374151;
			}

			.hng-design-panel__subtitle .dashicons {
				font-size: 18px;
				width: 18px;
				height: 18px;
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

			.hng-settings-container.hng-desktop-mode .hng-config-panel,
			.hng-settings-container.hng-desktop-mode .hng-preview-panel {
				grid-column: 1;
				grid-row: auto;
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

			.hng-settings-container.hng-desktop-mode .hng-design-grid--3 {
				grid-template-columns: repeat(4, minmax(0, 1fr));
			}

			.hng-settings-container.hng-desktop-mode .hng-design-card[style*="span 2"] {
				grid-column: span 4 !important;
			}

			@media (max-width: 1024px) {
				.hng-settings-container {
					grid-template-columns: 1fr;
				}
				.hng-config-panel,
				.hng-preview-panel {
					grid-column: 1;
					grid-row: auto;
				}
			}

			/* Sticky preview -- activated by JS toggle (works on all screens) */
			.hng-preview-panel.is-sticky {
				position: sticky;
				top: 32px; /* WP admin bar height */
				z-index: 90;
				height: 90vh;
				max-height: 90vh;
				overflow-y: auto;
				align-self: start; /* critical for sticky inside CSS grid */
				transition: box-shadow 0.3s ease;
			}

			.hng-preview-panel.is-sticky .hng-live-preview-frame {
				height: calc(90vh - 200px); /* subtract toolbar + heading + padding */
				overflow: hidden;
			}

			.hng-preview-panel.is-sticky .hng-live-preview-frame iframe {
				height: 100% !important;
				max-height: 100% !important;
			}

			.hng-preview-panel.is-sticky.is-stuck {
				box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
			}

			/* Sticky toggle button */
			.hng-sticky-toggle {
				display: inline-flex !important;
				align-items: center;
				gap: 2px;
				transition: all 0.25s ease;
			}

			.hng-sticky-toggle .dashicons {
				font-size: 16px;
				width: 16px;
				height: 16px;
				transition: transform 0.3s ease;
			}

			.hng-sticky-toggle.is-active {
				border-color: #2AFFA3;
				color: #0f172a;
				background: #ecfdf5;
			}

			.hng-sticky-toggle.is-active .dashicons {
				transform: rotate(-45deg);
				color: #059669;
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

			/* ============================================== */
			/* Fieldsets para seções de configuração          */
			/* ============================================== */
			.hng-fieldset {
				border: 1px solid #e5e7eb;
				border-radius: 10px;
				padding: 16px 20px;
				margin: 20px 0;
				background: #fafbfc;
			}
			.hng-fieldset legend {
				font-size: 14px;
				font-weight: 600;
				color: #1f2937;
				padding: 0 10px;
				background: #fafbfc;
			}
			.hng-fieldset-content {
				display: flex;
				flex-direction: column;
				gap: 14px;
				margin-top: 10px;
			}
			.hng-fieldset-cart-hover {
				border-left: 3px solid #2AFFA3;
			}
			.hng-fieldset-chat-hover {
				border-left: 3px solid #2984f1;
			}
			
			/* Grupo inline: checkbox + input lado a lado */
			.hng-inline-group {
				display: flex;
				align-items: center;
				gap: 16px;
				flex-wrap: wrap;
			}
			.hng-inline-group .hng-form-group {
				margin-bottom: 0;
			}
			.hng-inline-group .hng-form-group-checkbox {
				flex-shrink: 0;
			}
			.hng-inline-group .hng-form-group-checkbox label {
				display: flex;
				align-items: center;
				gap: 8px;
				cursor: pointer;
				font-size: 13px;
			}
			.hng-inline-group .hng-form-group:not(.hng-form-group-checkbox) {
				flex: 1;
				min-width: 120px;
			}
			.hng-inline-group .hng-form-group input[type="text"] {
				padding: 8px 12px;
				border: 1px solid #d1d5db;
				border-radius: 6px;
				font-size: 14px;
				width: 100%;
			}
			.hng-fieldset .hng-form-group select {
				padding: 8px 12px;
				border: 1px solid #d1d5db;
				border-radius: 6px;
				font-size: 14px;
				max-width: 200px;
			}
			
			/* Input with unit suffix */
			.hng-input-with-unit {
				display: flex;
				align-items: center;
				gap: 6px;
			}
			.hng-input-with-unit input[type="number"] {
				padding: 8px 12px;
				border: 1px solid #d1d5db;
				border-radius: 6px;
				font-size: 14px;
				width: 80px;
			}
			.hng-input-with-unit .unit {
				color: #6b7280;
				font-size: 13px;
				font-weight: 500;
			}

			@media (max-width: 480px) {
				.hng-inline-group {
					flex-direction: column;
					align-items: stretch;
				}
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
				updateLiveFrameSize(390, 760);
				
				// Set initial mobile mode class
				$('.hng-settings-container').removeClass('hng-desktop-mode');

				// Store current device dimensions for resize recalculation
				var currentDeviceWidth = 390;
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

				// Sticky preview toggle
				$('#hng-sticky-toggle').on('click', function() {
					var btn = $(this);
					var panel = $('.hng-preview-panel');
					var label = btn.find('.hng-sticky-label');

					btn.toggleClass('is-active');
					panel.toggleClass('is-sticky');

					if (btn.hasClass('is-active')) {
						label.text('<?php echo esc_js( __( 'Desfixar', 'hng-commerce' ) ); ?>');
						// Observe when the panel is stuck
						if ('IntersectionObserver' in window && !window.hngStickyObserver) {
							var sentinel = document.getElementById('hng-sticky-sentinel');
							if (!sentinel) {
								sentinel = document.createElement('div');
								sentinel.id = 'hng-sticky-sentinel';
								sentinel.style.cssText = 'height:1px;margin:0;padding:0;visibility:hidden;pointer-events:none;';
								var gridContainer = panel[0].parentNode;
								gridContainer.parentNode.insertBefore(sentinel, gridContainer);
							}
							window.hngStickyObserver = new IntersectionObserver(function(entries) {
								if (panel.hasClass('is-sticky')) {
									panel.toggleClass('is-stuck', !entries[0].isIntersecting);
								}
							}, { threshold: 0 });
							window.hngStickyObserver.observe(sentinel);
						}
					} else {
						label.text('<?php echo esc_js( __( 'Fixar Preview', 'hng-commerce' ) ); ?>');
						panel.removeClass('is-stuck');
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
		$layouts = array(
			'default' => array(
				'label'       => '🛒 Carrinho Padrão',
				'description' => __( 'Usa o carrinho nativo do tema/sistema', 'hng-commerce' ),
			),
			'sidebar' => array(
				'label'       => '📌 Sidebar',
				'description' => __( 'Desliza do lado direito', 'hng-commerce' ),
			),
			'drawer'  => array(
				'label'       => '📊 Drawer',
				'description' => __( 'Abre de baixo para cima (mobile-friendly)', 'hng-commerce' ),
			),
			'modal'   => array(
				'label'       => '🎯 Modal',
				'description' => __( 'Pop-up centralizado elegante', 'hng-commerce' ),
			),
			'popup'   => array(
				'label'       => '🔔 Popup',
				'description' => __( 'Ícone flutuante no canto', 'hng-commerce' ),
			),
			'sticky'  => array(
				'label'       => '📍 Sticky Badge',
				'description' => __( 'Pequeno ícone que expande ao hover', 'hng-commerce' ),
			),
		);

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
