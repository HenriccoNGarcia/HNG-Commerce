<?php
/**
 * HNG Commerce - Cart Display Manager
 * Gerencia diferentes tipos de layout para o carrinho
 *
 * @package HNG_Commerce
 * @since 1.3.0
 */

// phpcs:disable Squiz.Commenting.InlineComment.InvalidEndChar
// phpcs:disable WordPress.PHP.YodaConditions.NotYoda
// phpcs:disable Squiz.Commenting.FunctionComment.MissingParamTag
// phpcs:disable Universal.NamingConventions.NoReservedKeywordParameterNames
// phpcs:disable Squiz.Commenting.FunctionComment.ParamCommentFullStop
// phpcs:disable Squiz.Commenting.VariableComment.MissingVar
// phpcs:disable Squiz.Commenting.ClassComment.Missing

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HNG_Cart_Display {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Front-end preview reads GET params only for UI rendering (no state changes).

	/**
	 * Tipos de carrinho disponíveis
	 */
	const LAYOUTS = array(
		'default' => array(
			'label'       => 'Carrinho Padrão',
			'description' => 'Usa o carrinho padrão do sistema',
			'icon'        => '🛒',
			'position'    => 'theme-default',
			'animation'   => 'none',
		),
		'sidebar' => array(
			'label'       => 'Sidebar Flutuante',
			'description' => 'Carrinho desliza do lado direito',
			'icon'        => '📌',
			'position'    => 'right',
			'animation'   => 'slide-in-right',
		),
		'popup'   => array(
			'label'       => 'Popup com Ícone',
			'description' => 'Ícone de carrinho com badge de quantidade',
			'icon'        => '🔔',
			'position'    => 'fixed-bottom-right',
			'animation'   => 'scale-up',
		),
		'drawer'  => array(
			'label'       => 'Drawer Inferior',
			'description' => 'Abre de baixo, ideal para mobile',
			'icon'        => '📊',
			'position'    => 'bottom',
			'animation'   => 'slide-up',
		),
		'modal'   => array(
			'label'       => 'Modal Elegante',
			'description' => 'Pop-up centralizado com overlay',
			'icon'        => '🎯',
			'position'    => 'center',
			'animation'   => 'fade-in-scale',
		),
		'sticky'  => array(
			'label'       => 'Badge Sticky',
			'description' => 'Pequeno ícone fixo que se expande',
			'icon'        => '📍',
			'position'    => 'fixed-corner',
			'animation'   => 'expand',
		),
	);

	/**
	 * Tipo de carrinho atual
	 */
	private $cart_type;

	/**
	 * Preview ativo
	 */
	private $is_preview = false;

	/**
	 * Instância única
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->cart_type = $this->get_option_text( 'hng_cart_display_type', 'sidebar' );
		if ( ! array_key_exists( $this->cart_type, self::LAYOUTS ) ) {
			$this->cart_type = 'sidebar';
		}
		$this->is_preview = $this->is_preview_request();
		$this->cart_type  = $this->get_preview_layout( $this->cart_type );

		if ( $this->is_preview && isset( $_GET['hng_cart_preview_guest'] ) ) {
			add_filter( 'show_admin_bar', '__return_false' );
		}

		// Add body classes for chat/cart positioning
		add_filter( 'body_class', array( $this, 'add_body_classes' ) );

		// Carregar assets baseado no tipo de carrinho
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Output do carrinho no footer
		add_action( 'wp_footer', array( $this, 'render_cart_display' ) );

		// AJAX: retornar dados do carrinho para o JS
		add_action( 'wp_ajax_hng_get_cart_data', array( $this, 'ajax_get_cart_data' ) );
		add_action( 'wp_ajax_nopriv_hng_get_cart_data', array( $this, 'ajax_get_cart_data' ) );
	}

	/**
	 * AJAX handler — retorna itens e total do carrinho.
	 */
	public function ajax_get_cart_data() {
		check_ajax_referer( 'hng_cart_display', 'nonce' );

		$items = array();
		$total = 0;

		if ( class_exists( 'HNG_Cart' ) ) {
			$cart     = HNG_Cart::instance();
			$contents = $cart->get_cart();

			foreach ( $contents as $cart_id => $item ) {
				$product = isset( $item['data'] ) ? $item['data'] : null;
				if ( ! $product ) {
					continue;
				}

				$image_url = '';
				if ( method_exists( $product, 'get_image_url' ) ) {
					$image_url = $product->get_image_url( 'thumbnail' );
				}

				$price = method_exists( $product, 'get_price' ) ? floatval( $product->get_price() ) : 0;
				$qty   = isset( $item['quantity'] ) ? intval( $item['quantity'] ) : 1;

				$items[] = array(
					'id'       => $cart_id,
					'name'     => method_exists( $product, 'get_name' ) ? $product->get_name() : '',
					'image'    => $image_url,
					'price'    => number_format( $price, 2, '.', '' ),
					'quantity' => $qty,
				);
			}

			$total = method_exists( $cart, 'get_total' ) ? floatval( $cart->get_total() ) : 0;
		}

		wp_send_json_success(
			array(
				'items' => $items,
				'total' => number_format( $total, 2, '.', '' ),
				'count' => array_sum( array_column( $items, 'quantity' ) ),
			)
		);
	}

	/**
	 * Add body classes for chat/cart positioning coordination
	 */
	public function add_body_classes( $classes ) {
		// Check if live chat is enabled
		$chat_enabled = get_option( 'hng_live_chat_enabled', 'no' ) === 'yes';

		if ( ! $chat_enabled ) {
			$classes[] = 'hng-chat-disabled';
		} else {
			// Check chat position
			$chat_position = get_option( 'hng_live_chat_position', 'bottom-right' );
			if ( strpos( $chat_position, 'left' ) !== false ) {
				$classes[] = 'hng-chat-left';
			} else {
				$classes[] = 'hng-chat-right';
			}
		}

		return $classes;
	}

	/**
	 * Enqueue scripts e styles baseado no tipo de carrinho
	 */
	public function enqueue_assets() {
		if ( is_admin() ) {
			return;
		}

		// Se for 'default', não carrega CSS/JS custom
		if ( $this->cart_type === 'default' ) {
			return;
		}

		// Versão com timestamp para forçar atualização de cache
		$cache_buster = HNG_COMMERCE_VERSION . '.' . time();

		// CSS base para todos os layouts
		wp_enqueue_style(
			'hng-cart-display',
			HNG_COMMERCE_URL . 'assets/css/cart-display.css',
			array(),
			$cache_buster
		);

		// CSS de fix de z-index (importante carregar primeiro)
		wp_enqueue_style(
			'hng-cart-z-fix',
			HNG_COMMERCE_URL . 'assets/css/cart-z-index-fix.css',
			array( 'hng-cart-display' ),
			$cache_buster
		);

		// CSS específico do layout
		$layout_css = HNG_COMMERCE_PATH . 'assets/css/cart-' . $this->cart_type . '.css';
		if ( file_exists( $layout_css ) ) {
			wp_enqueue_style(
				'hng-cart-' . $this->cart_type,
				HNG_COMMERCE_URL . 'assets/css/cart-' . $this->cart_type . '.css',
				array( 'hng-cart-display' ),
				$cache_buster
			);
		}

		$design_css = $this->get_design_css();
		if ( $design_css !== '' ) {
			wp_add_inline_style( 'hng-cart-display', $design_css );
		}

		// JavaScript base
		wp_enqueue_script(
			'hng-cart-display',
			HNG_COMMERCE_URL . 'assets/js/cart-display.js',
			array( 'jquery' ),
			$cache_buster,
			true
		);

		// Adicionar dados necessários
		wp_localize_script(
			'hng-cart-display',
			'hngCartDisplay',
			array(
				'type'                => $this->cart_type,
				'ajaxUrl'             => admin_url( 'admin-ajax.php' ),
				'nonce'               => wp_create_nonce( 'hng_cart_display' ),
				'shippingNonce'       => wp_create_nonce( 'hng_calculate_shipping' ),
				'updateShippingNonce' => wp_create_nonce( 'hng_update_cart_shipping' ),
				'icon'                => self::get_cart_icon(),
				'label'               => self::LAYOUTS[ $this->cart_type ]['label'] ?? 'Carrinho',
				'overlay'             => $this->get_preview_overlay(),
				'checkoutUrl'         => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/checkout/' ),
				'i18n'                => array(
					'calculating'    => __( 'Calculando...', 'hng-commerce' ),
					'calculate'      => __( 'Calcular', 'hng-commerce' ),
					'enterCep'       => __( 'Digite o CEP', 'hng-commerce' ),
					'invalidCep'     => __( 'CEP inválido', 'hng-commerce' ),
					'noShipping'     => __( 'Nenhuma opção de frete disponível', 'hng-commerce' ),
					'quoteRequired'  => __( 'Frete sob consulta', 'hng-commerce' ),
					'selectShipping' => __( 'Selecione o frete', 'hng-commerce' ),
					'freeShipping'   => __( 'Frete Grátis', 'hng-commerce' ),
				),
			)
		);
	}

	/**
	 * Renderizar o carrinho no layout escolhido
	 */
	public function render_cart_display() {
		if ( is_admin() ) {
			return;
		}

		// Check if chat is synced with cart
		$sync_chat = $this->is_sync_chat_enabled();

		// In preview mode, assume chat is enabled when sync is requested
		$chat_enabled = get_option( 'hng_live_chat_enabled', 'no' ) === 'yes';
		if ( $this->is_preview && $sync_chat ) {
			$chat_enabled = true; // Force enabled in preview to show sync
		}

		// Render unified container if chat is synced
		if ( $sync_chat && $chat_enabled && $this->cart_type !== 'default' ) {
			$this->render_unified_floating_container();
		}

		$method = 'render_' . $this->cart_type;
		if ( method_exists( $this, $method ) ) {
			$this->$method();
		}

		// Auto-open cart in preview mode
		if ( $this->is_preview ) {
			$this->render_preview_script();
		}
	}

	/**
	 * Check if cart/chat sync is enabled (supports preview override)
	 */
	private function is_sync_chat_enabled() {
		$sync_chat = $this->get_option_bool( 'hng_cart_sync_chat', false );
		if ( $this->is_preview && isset( $_GET['hng_cart_sync_chat'] ) ) {
			$sync_chat = (bool) intval( $_GET['hng_cart_sync_chat'] );
		}

		return (bool) $sync_chat;
	}

	/**
	 * Read text option with fallback for empty values
	 */
	private function get_option_text( $key, $default ) {
		$value = get_option( $key, null );
		if ( $value === null || $value === '' ) {
			return (string) $default;
		}

		return (string) $value;
	}

	/**
	 * Read int option with fallback for empty values
	 */
	private function get_option_int( $key, $default ) {
		$value = get_option( $key, null );
		if ( $value === null || $value === '' ) {
			return absint( $default );
		}

		return absint( $value );
	}

	/**
	 * Read bool option with fallback for empty values
	 */
	private function get_option_bool( $key, $default ) {
		$value = get_option( $key, null );
		if ( $value === null || $value === '' ) {
			return (bool) $default;
		}

		return (bool) rest_sanitize_boolean( $value );
	}

	/**
	 * Render unified container with chat and cart buttons
	 * Server-side rendering: trigger is rendered directly inside the slot
	 */
	private function render_unified_floating_container() {
		$position = $this->get_option_text( 'hng_cart_position', 'bottom-right' );
		if ( $this->is_preview && isset( $_GET['hng_cart_position'] ) ) {
			$position = sanitize_text_field( wp_unslash( $_GET['hng_cart_position'] ) );
		}

		$spacing = $this->get_option_int( 'hng_cart_chat_spacing', 10 );
		if ( $this->is_preview && isset( $_GET['hng_cart_chat_spacing'] ) ) {
			$spacing = absint( $_GET['hng_cart_chat_spacing'] );
		}

		$order = $this->get_option_text( 'hng_cart_chat_order', 'chat-first' );
		if ( $this->is_preview && isset( $_GET['hng_cart_chat_order'] ) ) {
			$order = sanitize_text_field( wp_unslash( $_GET['hng_cart_chat_order'] ) );
		}

		// Sincronização sempre em pilha vertical (conforme UX da integração)
		$stack_vertical = true;

		$hide_chat_mobile = $this->get_option_bool( 'hng_cart_chat_hide_mobile', false );
		if ( $this->is_preview && isset( $_GET['hng_cart_chat_hide_mobile'] ) ) {
			$hide_chat_mobile = (bool) intval( $_GET['hng_cart_chat_hide_mobile'] );
		}

		$position_classes = array( 'hng-unified-floating-container' );
		if ( strpos( $position, 'left' ) !== false ) {
			$position_classes[] = 'hng-unified-left';
		} else {
			$position_classes[] = 'hng-unified-right';
		}
		if ( strpos( $position, 'top' ) !== false ) {
			$position_classes[] = 'hng-unified-top';
		} else {
			$position_classes[] = 'hng-unified-bottom';
		}
		if ( $stack_vertical ) {
			$position_classes[] = 'hng-unified-vertical';
		} else {
			$position_classes[] = 'hng-unified-horizontal';
		}
		if ( $hide_chat_mobile ) {
			$position_classes[] = 'hng-chat-hide-mobile';
		}

		// Get chat button HTML
		$chat_button = $this->get_chat_button_html();

		// Generate unique ID for dynamic spacing
		$container_id = 'hng-unified-container-' . wp_rand( 1000, 9999 );

		// Para posição bottom, invertemos a ordem visual (flex-direction: column-reverse)
		// Assim "chat-first" = chat fica mais próximo do canto (em baixo quando bottom)
		$is_bottom = strpos( $position, 'top' ) === false;

		// Determina o ID do trigger baseado no tipo de carrinho
		$trigger_id = 'hng-cart-trigger-' . $this->cart_type;

		// Para sticky, precisamos renderizar o sticky completo (com expanded)
		$is_sticky = $this->cart_type === 'sticky';

		?>
		<div id="<?php echo esc_attr( $container_id ); ?>" 
			class="<?php echo esc_attr( implode( ' ', $position_classes ) ); ?>" 
			data-order="<?php echo esc_attr( $order ); ?>"
			data-spacing="<?php echo esc_attr( $spacing ); ?>"
			data-position="<?php echo esc_attr( $is_bottom ? 'bottom' : 'top' ); ?>">
			<?php if ( $order === 'chat-first' ) : ?>
				<div class="hng-unified-chat-slot"><?php echo $chat_button; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<div class="hng-unified-cart-slot">
				<?php
				if ( $is_sticky ) {
					$this->render_sticky_in_sync();
				} else {
					$this->render_trigger_button( $trigger_id );
				}
				?>
				</div>
			<?php else : ?>
				<div class="hng-unified-cart-slot">
				<?php
				if ( $is_sticky ) {
					$this->render_sticky_in_sync();
				} else {
					$this->render_trigger_button( $trigger_id );
				}
				?>
				</div>
				<div class="hng-unified-chat-slot"><?php echo $chat_button; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<?php endif; ?>
		</div>
		<style>
			#<?php echo esc_attr( $container_id ); ?> {
				gap: <?php echo esc_attr( $spacing ); ?>px !important;
				row-gap: <?php echo esc_attr( $spacing ); ?>px !important;
				column-gap: <?php echo esc_attr( $spacing ); ?>px !important;
			}
			.hng-unified-floating-container {
				position: fixed;
				z-index: 99997;
				display: flex;
			}
			/* Vertical stacking */
			.hng-unified-floating-container.hng-unified-vertical {
				flex-direction: column;
			}
			/* Bottom position: reverse column so first item is at bottom */
			.hng-unified-floating-container.hng-unified-vertical.hng-unified-bottom {
				flex-direction: column-reverse;
			}
			/* Horizontal stacking */
			.hng-unified-floating-container.hng-unified-horizontal {
				flex-direction: row;
			}
			/* Right position horizontal: reverse so first is at right */
			.hng-unified-floating-container.hng-unified-horizontal.hng-unified-right {
				flex-direction: row-reverse;
			}
			.hng-unified-floating-container.hng-unified-left {
				left: 20px;
				align-items: flex-start;
			}
			.hng-unified-floating-container.hng-unified-right {
				right: 20px;
				align-items: flex-end;
			}
			.hng-unified-floating-container.hng-unified-bottom {
				bottom: 20px;
			}
			.hng-unified-floating-container.hng-unified-top {
				top: 20px;
			}
			/* Slots styling */
			.hng-unified-cart-slot,
			.hng-unified-chat-slot {
				position: relative;
				flex-shrink: 0;
				display: flex;
				justify-content: center;
				align-items: center;
				margin: 0 !important;
				padding: 0 !important;
			}
			/* Right aligned container */
			.hng-unified-right .hng-unified-cart-slot,
			.hng-unified-right .hng-unified-chat-slot {
				justify-content: flex-end;
			}
			/* Left aligned container */
			.hng-unified-left .hng-unified-cart-slot,
			.hng-unified-left .hng-unified-chat-slot {
				justify-content: flex-start;
			}
			/* When synced, hide independent chat bubble */
			body.hng-chat-synced #hng-live-chat-widget .hng-chat-bubble {
				display: none !important;
			}
			/* Trigger inside unified slot - reset positioning */
			.hng-unified-cart-slot .hng-cart-trigger-container {
				position: static !important;
				display: block !important;
				margin: 0 !important;
				padding: 0 !important;
				bottom: auto !important;
				right: auto !important;
				left: auto !important;
				top: auto !important;
				z-index: auto !important;
			}
			.hng-unified-cart-slot .hng-cart-trigger {
				position: static !important;
				margin: 0 !important;
				/* Reset browser default effects */
				outline: none !important;
				-webkit-tap-highlight-color: transparent !important;
			}
			/* Reset focus/hover browser defaults for cart trigger */
			.hng-unified-cart-slot .hng-cart-trigger:focus,
			.hng-unified-cart-slot .hng-cart-trigger:focus-visible,
			.hng-unified-cart-slot .hng-cart-trigger:active {
				outline: none !important;
			}
			/* Sem efeito hover padrão */
			.hng-unified-cart-slot .hng-cart-trigger:hover {
				transform: none !important;
			}
			/* Chat synced button styling */
			.hng-unified-chat-slot .hng-chat-bubble-synced {
				width: var(--hng-cart-button-width, 60px);
				height: var(--hng-cart-button-height, 60px);
				border-radius: var(--hng-cart-button-radius, 50%);
				background: var(--hng-chat-primary, #2984f1);
				border: none;
				cursor: pointer;
				display: flex;
				align-items: center;
				justify-content: center;
				box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
				transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
				position: relative;
				color: #fff;
				/* Reset browser default effects */
				outline: none !important;
				-webkit-tap-highlight-color: transparent !important;
				-webkit-appearance: none !important;
				-moz-appearance: none !important;
				appearance: none !important;
			}
			/* Reset focus/hover browser defaults */
			.hng-unified-chat-slot .hng-chat-bubble-synced:focus,
			.hng-unified-chat-slot .hng-chat-bubble-synced:focus-visible,
			.hng-unified-chat-slot .hng-chat-bubble-synced:active {
				outline: none !important;
			}
			/* Override global theme/Tailwind ::before ripple effect */
			.hng-unified-cart-slot .hng-cart-trigger::before,
			.hng-unified-cart-slot .hng-cart-trigger::after,
			.hng-unified-chat-slot .hng-chat-bubble-synced::before,
			.hng-unified-chat-slot .hng-chat-bubble-synced::after {
				content: none !important;
				display: none !important;
				width: 0 !important;
				height: 0 !important;
				background: none !important;
				transition: none !important;
			}
			/* Sem efeito hover padrão - use classes de efeito */
			.hng-unified-chat-slot .hng-chat-bubble-synced:hover {
				transform: none !important;
			}
			.hng-unified-chat-slot .hng-chat-bubble-synced svg {
				width: 24px;
				height: 24px;
				fill: white;
				flex-shrink: 0;
			}
			/* Chat hover text label */
			.hng-unified-chat-slot .hng-chat-bubble-synced .hng-chat-label {
				display: none;
				white-space: nowrap;
				font-weight: 600;
				font-size: 14px;
				color: #fff;
			}
			.hng-unified-chat-slot .hng-chat-bubble-synced.hng-chat-hover-text-enabled:hover {
				width: auto;
				min-width: 100px;
				max-width: 160px;
				border-radius: 28px;
				justify-content: flex-start;
				padding-left: 14px;
				padding-right: 18px;
				gap: 10px;
			}
			.hng-unified-chat-slot .hng-chat-bubble-synced.hng-chat-hover-text-enabled:hover .hng-chat-label {
				display: inline-block;
			}
			/* Hover text for cart when synced */
			.hng-unified-cart-slot .hng-cart-trigger,
			.hng-unified-cart-slot .hng-cart-trigger-container {
				overflow: visible !important;
			}
			.hng-unified-cart-slot .hng-cart-trigger .hng-cart-label {
				display: none;
				white-space: nowrap;
			}
			.hng-unified-cart-slot .hng-cart-trigger-container:not(.hng-no-hover-text) .hng-cart-trigger:hover .hng-cart-label {
				display: inline-block !important;
			}
			.hng-unified-chat-slot .hng-chat-badge {
				position: absolute;
				top: -5px;
				right: -5px;
				background: #ff3b30;
				color: white;
				font-size: 12px;
				font-weight: bold;
				min-width: 20px;
				height: 20px;
				border-radius: 10px;
				display: flex;
				align-items: center;
				justify-content: center;
				padding: 0 5px;
			}
			/* Mobile responsive */
			@media (max-width: 768px) {
				.hng-unified-floating-container.hng-chat-hide-mobile .hng-unified-chat-slot {
					display: none !important;
				}
				.hng-unified-floating-container.hng-unified-left {
					left: 15px;
				}
				.hng-unified-floating-container.hng-unified-right {
					right: 15px;
				}
				.hng-unified-floating-container.hng-unified-bottom {
					bottom: 15px;
				}
				.hng-unified-floating-container.hng-unified-top {
					top: 15px;
				}
			}
		</style>
		<script>
			(function() {
				// Add sync class to body
				document.body.classList.add('hng-chat-synced');
				
				// Handle synced chat button click
				document.addEventListener('click', function(e) {
					var syncedBtn = e.target.closest('.hng-chat-bubble-synced');
					if (syncedBtn) {
						var originalBubble = document.querySelector('#hng-live-chat-widget .hng-chat-bubble');
						if (originalBubble) {
							originalBubble.click();
						}
					}
				});
			})();
		</script>
		<?php
	}

	/**
	 * Get chat button HTML for unified container
	 */
	private function get_chat_button_html() {
		$primary_color = get_option( 'hng_live_chat_primary_color', '#2984f1' );
		$bubble_icon   = get_option( 'hng_live_chat_bubble_icon', 'chat' );

		// Hover effect
		$hover_effect = get_option( 'hng_live_chat_hover_effect', 'none' );
		$effect_class = ( $hover_effect && $hover_effect !== 'none' ) ? ' hng-hover-effect-' . sanitize_key( $hover_effect ) : '';

		// Hover text - use filter_var to ensure boolean conversion regardless of stored type
		$hover_text_enabled = filter_var( get_option( 'hng_live_chat_hover_text_enabled', false ), FILTER_VALIDATE_BOOLEAN );
		$hover_text         = get_option( 'hng_live_chat_hover_text', 'Chat' );
		$hover_text_class   = $hover_text_enabled ? ' hng-chat-hover-text-enabled' : '';

		// Get icon SVG
		$icon_svg = $this->get_chat_icon_svg( $bubble_icon );

		// Build hover text span
		$hover_text_html = '';
		if ( $hover_text_enabled && ! empty( $hover_text ) ) {
			$hover_text_html = '<span class="hng-chat-label">' . esc_html( $hover_text ) . '</span>';
		}

		return '<button type="button" class="hng-chat-bubble-synced' . esc_attr( $effect_class . $hover_text_class ) . '" style="--hng-chat-primary: ' . esc_attr( $primary_color ) . ';" aria-label="' . esc_attr__( 'Abrir chat', 'hng-commerce' ) . '">
            ' . $icon_svg . '
            ' . $hover_text_html . '
            <span class="hng-chat-badge" style="display: none;">0</span>
        </button>';
	}

	/**
	 * Get chat icon SVG
	 */
	private function get_chat_icon_svg( $icon_type ) {
		switch ( $icon_type ) {
			case 'message':
				return '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.17L4 17.17V4h16v12z"/></svg>';
			case 'support':
				return '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H8c0-2.21 1.79-4 4-4s4 1.79 4 4c0 .88-.36 1.68-.93 2.25z"/></svg>';
			case 'headset':
				return '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 1c-4.97 0-9 4.03-9 9v7c0 1.66 1.34 3 3 3h3v-8H5v-2c0-3.87 3.13-7 7-7s7 3.13 7 7v2h-4v8h3c1.66 0 3-1.34 3-3v-7c0-4.97-4.03-9-9-9z"/></svg>';
			case 'chat':
			default:
				return '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M21 6h-2V4.5c0-.83-.67-1.5-1.5-1.5h-15C1.67 3 1 3.67 1 4.5V16l4-4h1v4.5c0 .83.67 1.5 1.5 1.5H17l4 4V6z"/></svg>';
		}
	}

	/**
	 * Script para abrir carrinho automaticamente no modo preview
	 */
	private function render_preview_script() {
		?>
		<style>
		/* Estilos para produtos de exemplo no preview */
		.hng-cart-item {
			display: flex;
			align-items: center;
			gap: 12px;
			padding: 12px;
			border-bottom: 1px solid var(--hng-cart-border, #e5e7eb);
			position: relative;
		}
		
		.hng-cart-item-image {
			flex-shrink: 0;
			width: 60px;
			height: 60px;
			border-radius: var(--hng-cart-radius, 8px);
			overflow: hidden;
			background: #f3f4f6;
		}
		
		.hng-cart-item-image img {
			width: 100%;
			height: 100%;
			object-fit: cover;
		}
		
		.hng-cart-item-details {
			flex: 1;
		}
		
		.hng-cart-item-details h4 {
			margin: 0 0 4px 0;
			font-size: 14px;
			font-weight: 600;
			color: var(--hng-cart-text, #1f2937);
		}
		
		.hng-cart-item-details .price {
			margin: 0 0 8px 0;
			font-size: 14px;
			font-weight: 700;
			color: var(--hng-cart-primary, #2AFFA3);
		}
		
		.hng-cart-item-details .quantity {
			display: flex;
			align-items: center;
			gap: 8px;
		}
		
		.hng-cart-item-details .quantity .qty-btn {
			width: 24px;
			height: 24px;
			border: 1px solid var(--hng-cart-border, #d1d5db);
			background: var(--hng-cart-surface, #fff);
			border-radius: 4px;
			cursor: pointer;
			font-size: 14px;
			display: flex;
			align-items: center;
			justify-content: center;
			color: var(--hng-cart-text, #1f2937);
		}
		
		.hng-cart-item-details .quantity input {
			width: 40px;
			text-align: center;
			border: 1px solid var(--hng-cart-border, #d1d5db);
			border-radius: 4px;
			padding: 4px;
			font-size: 12px;
		}
		
		.hng-cart-item-remove {
			position: absolute;
			top: 8px;
			right: 8px;
			width: 24px;
			height: 24px;
			border: none;
			background: transparent;
			cursor: pointer;
			font-size: 18px;
			color: #9ca3af;
			line-height: 1;
		}
		
		.hng-cart-item-remove:hover {
			color: #ef4444;
		}
		
		.hng-mini-cart-item {
			display: flex;
			justify-content: space-between;
			padding: 8px 0;
			border-bottom: 1px solid var(--hng-cart-border, #e5e7eb);
			font-size: 12px;
		}
		
		.hng-mini-cart-item span:first-child {
			color: var(--hng-cart-text, #1f2937);
		}
		
		/* Forçar visibilidade do sticky no preview */
		.hng-cart-sticky.show-expanded .hng-cart-sticky-expanded {
			opacity: 1;
			pointer-events: auto;
			transform: translateY(0);
		}
		</style>
		
		<script>
		// Passa a configuração do tipo de carrinho para o JavaScript
		window.hngCartDisplay = {
			type: '<?php echo esc_js( $this->cart_type ); ?>',
			ajaxUrl: '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
			nonce: '<?php echo esc_js( wp_create_nonce( 'hng_cart_display' ) ); ?>',
			isPreview: true
		};
		</script>
		
		<script>
		(function() {
			function initPreview() {
				// Aguarda um pouco para garantir que tudo foi carregado
				setTimeout(function() {
					// Detecta o tipo de carrinho e abre automaticamente
					const cartType = '<?php echo esc_js( $this->cart_type ); ?>';
				
				console.log('Preview Cart Type:', cartType);
				
				// Abre o carrinho de acordo com o tipo
				switch(cartType) {
					case 'sidebar':
						console.log('Opening sidebar...');
						const sidebar = document.getElementById('hng-cart-sidebar');
						if (sidebar) {
							sidebar.classList.add('active');
							console.log('Sidebar opened');
						} else {
							console.error('Sidebar element not found');
						}
						break;
						
					case 'popup':
						console.log('Opening popup...');
						const popup = document.getElementById('hng-cart-popup');
						if (popup) {
							popup.classList.add('active');
							console.log('Popup opened');
						} else {
							console.error('Popup element not found');
						}
						break;
						
					case 'drawer':
						console.log('Opening drawer...');
						const drawer = document.getElementById('hng-cart-drawer');
						if (drawer) {
							drawer.classList.add('active');
							console.log('Drawer opened');
						} else {
							console.error('Drawer element not found');
						}
						break;
						
					case 'modal':
						console.log('Opening modal...');
						const modal = document.getElementById('hng-cart-modal');
						const modalOverlay = modal ? modal.querySelector('.hng-cart-modal-overlay') : null;
						if (modal) {
							modal.classList.add('active');
							console.log('Modal opened');
						} else {
							console.error('Modal element not found');
						}
						break;
						
					case 'sticky':
						console.log('Opening sticky...');
						const sticky = document.querySelector('.hng-cart-sticky');
						if (sticky) {
							sticky.classList.add('show-expanded');
							console.log('Sticky expanded');
						} else {
							console.error('Sticky element not found');
						}
						break;
						
					default:
						console.warn('Unknown cart type:', cartType);
				}
				
				// Atualiza badges com quantidade de exemplo
				const badges = document.querySelectorAll('.hng-cart-badge');
				badges.forEach(badge => {
					badge.textContent = '3';
					badge.style.display = 'flex';
				});
				
				console.log('Updated', badges.length, 'badges');
				
				// Garante que o JavaScript de interatividade está funcionando
				if (window.HNGCartDisplay) {
					window.HNGCartDisplay.state.layoutType = cartType;
					window.HNGCartDisplay.state.isOpen = true;
					console.log('HNGCartDisplay state updated');
				} else {
					console.warn('HNGCartDisplay not available yet');
				}
				
				// Handler de fechamento inline para preview (backup)
				document.querySelectorAll('.hng-cart-close, .hng-cart-close-modal').forEach(function(btn) {
					btn.addEventListener('click', function(e) {
						e.preventDefault();
						e.stopPropagation();
						
						console.log('Close button clicked');
						
						// Fechar sidebar
						const sidebar = document.getElementById('hng-cart-sidebar');
						if (sidebar) sidebar.classList.remove('active');
						
						// Fechar popup
						const popup = document.getElementById('hng-cart-popup');
						if (popup) popup.classList.remove('active');
						
						// Fechar drawer
						const drawer = document.getElementById('hng-cart-drawer');
						if (drawer) drawer.classList.remove('active');
						
						// Fechar modal
						const modal = document.getElementById('hng-cart-modal');
						if (modal) modal.classList.remove('active');
						
						// Fechar sticky
						const sticky = document.querySelector('.hng-cart-sticky');
						if (sticky) {
							sticky.classList.remove('show-expanded');
							sticky.classList.add('force-close');
							setTimeout(function() {
								sticky.classList.remove('force-close');
							}, 600);
						}
					});
				});
				
				// Handler de abertura inline para preview (backup)
				// Só adiciona se HNGCartDisplay não estiver disponível
				if (!window.HNGCartDisplay) {
					document.querySelectorAll('.hng-cart-trigger, .hng-cart-sticky-button, .hng-cart-floating-icon').forEach(function(btn) {
						btn.addEventListener('click', function(e) {
							e.preventDefault();
							e.stopPropagation();
						
							console.log('Open button clicked (backup handler), cart type:', cartType);
						
						// Abrir carrinho de acordo com o tipo
						switch(cartType) {
							case 'sidebar':
								const sidebar = document.getElementById('hng-cart-sidebar');
								if (sidebar) {
									sidebar.classList.toggle('active');
									console.log('Sidebar toggled');
								}
								break;
								
							case 'popup':
								const popup = document.getElementById('hng-cart-popup');
								if (popup) {
									popup.classList.toggle('active');
									console.log('Popup toggled');
								}
								break;
								
							case 'drawer':
								const drawer = document.getElementById('hng-cart-drawer');
								if (drawer) {
									drawer.classList.toggle('active');
									console.log('Drawer toggled');
								}
								break;
								
							case 'modal':
								const modal = document.getElementById('hng-cart-modal');
								if (modal) {
									modal.classList.toggle('active');
									console.log('Modal toggled');
								}
								break;
								
							case 'sticky':
								const sticky = document.querySelector('.hng-cart-sticky');
								if (sticky) {
									sticky.classList.toggle('show-expanded');
									console.log('Sticky toggled');
								}
								break;
						}
						});
					});
				}
			}, 300);
		}
		
		// Chamar a função - funciona tanto se o DOM já estiver carregado quanto não
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', initPreview);
		} else {
			initPreview();
		}
		})();
		</script>
		<?php
	}

	/**
	 * Get hover text enabled setting with preview support
	 *
	 * @return bool
	 */
	private function get_hover_text_enabled() {
		$enabled = $this->get_option_bool( 'hng_cart_hover_text_enabled', false );

		if ( $this->is_preview && isset( $_GET['hng_cart_hover_text_enabled'] ) ) {
			$enabled = (bool) intval( $_GET['hng_cart_hover_text_enabled'] );
		}

		return $enabled;
	}

	/**
	 * Get hover text with preview support
	 *
	 * @return string
	 */
	private function get_hover_text() {
		$text = $this->get_option_text( 'hng_cart_hover_text', __( 'Carrinho', 'hng-commerce' ) );
		if ( $this->is_preview && isset( $_GET['hng_cart_hover_text'] ) ) {
			$text = sanitize_text_field( wp_unslash( $_GET['hng_cart_hover_text'] ) );
		}
		if ( $text === '' ) {
			$text = __( 'Carrinho', 'hng-commerce' );
		}
		return $text;
	}

	/**
	 * Get CSS classes for the trigger button
	 *
	 * @param string $extra_classes Additional classes to add
	 * @return string CSS classes string
	 */
	private function get_trigger_classes( $extra_classes = '' ) {
		$classes = array( 'hng-cart-trigger' );

		// Add size class
		$size      = $this->get_preview_button_size();
		$classes[] = 'hng-cart-size-' . $size;

		// Add style class
		$style     = $this->get_preview_button_style();
		$classes[] = 'hng-cart-style-' . $style;

		// Add position class
		$position = $this->get_option_text( 'hng_cart_position', 'bottom-right' );
		if ( $this->is_preview && isset( $_GET['hng_cart_position'] ) ) {
			$position = sanitize_text_field( wp_unslash( $_GET['hng_cart_position'] ) );
		}
		if ( strpos( $position, 'left' ) !== false ) {
			$classes[] = 'hng-cart-position-left';
		}
		if ( strpos( $position, 'top' ) !== false ) {
			$classes[] = 'hng-cart-position-top';
		}

		// Add hover effect class
		$hover_effect = get_option( 'hng_cart_hover_effect', 'none' );
		if ( $hover_effect && $hover_effect !== 'none' ) {
			$classes[] = 'hng-hover-effect-' . sanitize_key( $hover_effect );
		}

		// Add extra classes
		if ( ! empty( $extra_classes ) ) {
			$classes[] = $extra_classes;
		}

		return implode( ' ', $classes );
	}

	/**
	 * Render the floating cart trigger button
	 * Used by both individual layouts and unified sync container
	 *
	 * @param string $button_id Optional ID for the button
	 * @param string $extra_classes Extra classes for trigger button
	 * @return void
	 */
	private function render_trigger_button( $button_id = 'hng-cart-trigger-btn', $extra_classes = '' ) {
		$trigger_classes    = $this->get_trigger_classes( $extra_classes );
		$hover_text_enabled = $this->get_hover_text_enabled();
		$hover_text         = $this->get_hover_text();

		// Container class
		$container_class = 'hng-cart-trigger-container';
		if ( ! $hover_text_enabled ) {
			$container_class .= ' hng-no-hover-text';
		}
		if ( $this->is_sync_chat_enabled() ) {
			$container_class .= ' hng-sync-active';
		}
		?>
		<div class="<?php echo esc_attr( $container_class ); ?>">
			<button type="button" id="<?php echo esc_attr( $button_id ); ?>" class="<?php echo esc_attr( $trigger_classes ); ?>" aria-label="<?php esc_attr_e( 'Abrir carrinho', 'hng-commerce' ); ?>">
				<span class="hng-cart-icon"><?php echo self::get_cart_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<span class="hng-cart-badge">0</span>
				<?php if ( $hover_text_enabled && ! empty( $hover_text ) ) : ?>
					<span class="hng-cart-label"><?php echo esc_html( $hover_text ); ?></span>
				<?php endif; ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Render Sidebar Flutuante
	 */
	private function render_sidebar() {
		$overlay_class = $this->get_preview_overlay() ? '' : 'no-overlay';
		?>
		<div id="hng-cart-sidebar" class="hng-cart-sidebar <?php echo esc_attr( $overlay_class ); ?>">
			<div class="hng-cart-sidebar-header">
				<h3><?php esc_html_e( 'Seu Carrinho', 'hng-commerce' ); ?></h3>
				<button type="button" class="hng-cart-close" aria-label="<?php esc_attr_e( 'Fechar carrinho', 'hng-commerce' ); ?>">
					<span>✕</span>
				</button>
			</div>
			<div class="hng-cart-sidebar-content">
				<?php $this->render_cart_contents(); ?>
			</div>
			<div class="hng-cart-sidebar-footer">
				<?php $this->render_cart_totals(); ?>
				<a href="<?php echo esc_url( home_url( '/checkout/' ) ); ?>" class="hng-btn hng-btn-primary hng-btn-block">
					<?php esc_html_e( 'Ir para Checkout', 'hng-commerce' ); ?>
				</a>
			</div>
		</div>
		
		<?php
		// Skip trigger if rendered in unified container
		if ( ! $this->is_sync_chat_enabled() ) {
			$this->render_trigger_button( 'hng-cart-trigger-sidebar' );
		}
	}

	/**
	 * Render Popup Flutuante com Ícone
	 */
	private function render_popup() {
		// Get position class for popup container
		$position = get_option( 'hng_cart_position', 'bottom-right' );
		if ( $this->is_preview && isset( $_GET['hng_cart_position'] ) ) {
			$position = sanitize_text_field( wp_unslash( $_GET['hng_cart_position'] ) );
		}
		$popup_classes = 'hng-cart-popup';
		if ( strpos( $position, 'left' ) !== false ) {
			$popup_classes .= ' hng-cart-position-left';
		}
		?>
		<div id="hng-cart-popup" class="<?php echo esc_attr( $popup_classes ); ?>">
			<div class="hng-cart-popup-content">
				<div class="hng-cart-popup-header">
					<h3><?php esc_html_e( 'Carrinho de Compras', 'hng-commerce' ); ?></h3>
					<button type="button" class="hng-cart-close">✕</button>
				</div>
				<div class="hng-cart-popup-body">
					<?php $this->render_cart_contents(); ?>
				</div>
				<div class="hng-cart-popup-footer">
					<?php $this->render_cart_totals(); ?>
					<a href="<?php echo esc_url( home_url( '/checkout/' ) ); ?>" class="hng-btn hng-btn-primary hng-btn-block">
						<?php esc_html_e( 'Finalizar Compra', 'hng-commerce' ); ?>
					</a>
				</div>
			</div>
		</div>
		
		<?php
		// Skip trigger if rendered in unified container
		if ( ! $this->is_sync_chat_enabled() ) {
			$this->render_trigger_button( 'hng-cart-trigger-popup', 'hng-cart-floating-icon' );
		}
	}

	/**
	 * Render Drawer Inferior
	 */
	private function render_drawer() {
		$overlay_class = $this->get_preview_overlay() ? '' : 'no-overlay';
		?>
		<div id="hng-cart-drawer" class="hng-cart-drawer <?php echo esc_attr( $overlay_class ); ?>">
			<div class="hng-cart-drawer-header">
				<h3><?php esc_html_e( 'Seu Carrinho', 'hng-commerce' ); ?></h3>
				<button type="button" class="hng-cart-close">✕</button>
			</div>
			<div class="hng-cart-drawer-content">
				<?php $this->render_cart_contents(); ?>
			</div>
			<div class="hng-cart-drawer-footer">
				<?php $this->render_cart_totals(); ?>
				<a href="<?php echo esc_url( home_url( '/checkout/' ) ); ?>" class="hng-btn hng-btn-primary hng-btn-block">
					<?php esc_html_e( 'Checkout', 'hng-commerce' ); ?>
				</a>
			</div>
		</div>
		
		<?php
		// Skip trigger if rendered in unified container
		if ( ! $this->is_sync_chat_enabled() ) {
			$this->render_trigger_button( 'hng-cart-trigger-drawer' );
		}
	}

	/**
	 * Render Modal Elegante
	 */
	private function render_modal() {
		$overlay_class = $this->get_preview_overlay() ? '' : 'no-overlay';
		?>
		<div id="hng-cart-modal" class="hng-cart-modal <?php echo esc_attr( $overlay_class ); ?>">
			<div class="hng-cart-modal-content">
				<div class="hng-cart-modal-header">
					<h2><?php esc_html_e( 'Carrinho de Compras', 'hng-commerce' ); ?></h2>
					<button type="button" class="hng-cart-close" aria-label="<?php esc_attr_e( 'Fechar', 'hng-commerce' ); ?>">✕</button>
				</div>
				<div class="hng-cart-modal-body">
					<?php $this->render_cart_contents(); ?>
				</div>
				<div class="hng-cart-modal-footer">
					<?php $this->render_cart_totals(); ?>
					<div class="hng-cart-actions">
						<button type="button" class="hng-btn hng-btn-secondary hng-cart-close-modal">
							<?php esc_html_e( 'Continuar Comprando', 'hng-commerce' ); ?>
						</button>
						<a href="<?php echo esc_url( home_url( '/checkout/' ) ); ?>" class="hng-btn hng-btn-primary">
							<?php esc_html_e( 'Finalizar Compra', 'hng-commerce' ); ?>
						</a>
					</div>
				</div>
			</div>
		</div>
		
		<?php
		// Skip trigger if rendered in unified container
		if ( ! $this->is_sync_chat_enabled() ) {
			$this->render_trigger_button( 'hng-cart-trigger-modal' );
		}
	}

	/**
	 * Render Sticky Badge
	 */
	private function render_sticky() {
		// When sync is enabled, the trigger is rendered in unified container
		// so we don't render the sticky badge (it would be redundant)
		if ( $this->is_sync_chat_enabled() ) {
			return;
		}

		$sticky_classes     = $this->get_trigger_classes( 'hng-cart-sticky-button' );
		$hover_text_enabled = $this->get_hover_text_enabled();
		$hover_text         = $this->get_hover_text();
		$container_class    = 'hng-cart-sticky';
		if ( ! $hover_text_enabled ) {
			$container_class .= ' hng-no-hover-text';
		}
		?>
		<div id="hng-cart-sticky" class="<?php echo esc_attr( $container_class ); ?>">
			<button type="button" id="hng-cart-trigger-sticky" class="<?php echo esc_attr( $sticky_classes ); ?>" aria-label="<?php esc_attr_e( 'Abrir carrinho', 'hng-commerce' ); ?>">
				<span class="hng-cart-icon"><?php echo self::get_cart_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<span class="hng-cart-badge">0</span>
				<?php if ( $hover_text_enabled && ! empty( $hover_text ) ) : ?>
					<span class="hng-cart-label"><?php echo esc_html( $hover_text ); ?></span>
				<?php endif; ?>
			</button>
			
			<!-- Expandable Cart -->
			<div class="hng-cart-sticky-expanded">
				<div class="hng-mini-cart">
					<?php $this->render_cart_mini(); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Sticky Badge inside unified sync container
	 * Versão especial para quando o sync está habilitado
	 */
	private function render_sticky_in_sync() {
		$sticky_classes     = $this->get_trigger_classes( 'hng-cart-sticky-button' );
		$hover_text_enabled = $this->get_hover_text_enabled();
		$hover_text         = $this->get_hover_text();
		$container_class    = 'hng-cart-sticky hng-cart-sticky-synced';
		if ( ! $hover_text_enabled ) {
			$container_class .= ' hng-no-hover-text';
		}
		?>
		<div id="hng-cart-sticky" class="<?php echo esc_attr( $container_class ); ?>">
			<button type="button" id="hng-cart-trigger-sticky" class="<?php echo esc_attr( $sticky_classes ); ?>" aria-label="<?php esc_attr_e( 'Abrir carrinho', 'hng-commerce' ); ?>">
				<span class="hng-cart-icon"><?php echo self::get_cart_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<span class="hng-cart-badge">0</span>
				<?php if ( $hover_text_enabled && ! empty( $hover_text ) ) : ?>
					<span class="hng-cart-label"><?php echo esc_html( $hover_text ); ?></span>
				<?php endif; ?>
			</button>
			
			<!-- Expandable Cart -->
			<div class="hng-cart-sticky-expanded">
				<div class="hng-mini-cart">
					<?php $this->render_cart_mini(); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Default (Native Cart)
	 * Não renderiza nada - usa o carrinho padrão do sistema/tema
	 */
	private function render_default() {
		// Carrinho padrão do sistema - não adiciona HTML custom
		// O tema/outro sistema gerencia o carrinho
		if ( $this->is_preview ) {
			?>
			<div style="padding: 40px; text-align: center; background: #f9fafb; border-radius: 8px; margin: 20px;">
				<h3 style="margin: 0 0 12px 0; color: #1f2937;">🛒 Modo Carrinho Padrão</h3>
				<p style="color: #6b7280; margin: 0;">
					Neste modo, o sistema usa o carrinho nativo do tema.<br>
					Não há preview disponível pois o layout é controlado pelo tema ativo.
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Renderizar conteúdo do carrinho
	 */
	private function render_cart_contents() {
		?>
		<div class="hng-cart-items">
			<?php if ( $this->is_preview ) : ?>
				<!-- Preview Mode: Produtos de Exemplo -->
				<div class="hng-cart-item">
					<div class="hng-cart-item-image">
						<img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='60' height='60'%3E%3Crect fill='%23e5e7eb' width='60' height='60'/%3E%3Ctext x='50%25' y='50%25' text-anchor='middle' dy='.3em' fill='%23666' font-size='24'%3E📦%3C/text%3E%3C/svg%3E" alt="Produto 1" />
					</div>
					<div class="hng-cart-item-details">
						<h4 class="hng-cart-item-name">Produto de Exemplo 1</h4>
						<p class="hng-cart-item-price">R$ 99,90</p>
						<div class="hng-cart-item-quantity">
							<button type="button" class="hng-qty-btn hng-qty-minus">−</button>
							<input type="number" class="hng-qty-input" value="1" min="1" readonly />
							<button type="button" class="hng-qty-btn hng-qty-plus">+</button>
						</div>
					</div>
					<button type="button" class="hng-cart-item-remove" aria-label="Remover item">×</button>
				</div>
				
				<div class="hng-cart-item">
					<div class="hng-cart-item-image">
						<img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='60' height='60'%3E%3Crect fill='%23e5e7eb' width='60' height='60'/%3E%3Ctext x='50%25' y='50%25' text-anchor='middle' dy='.3em' fill='%23666' font-size='24'%3E🎁%3C/text%3E%3C/svg%3E" alt="Produto 2" />
					</div>
					<div class="hng-cart-item-details">
						<h4 class="hng-cart-item-name">Produto de Exemplo 2</h4>
						<p class="hng-cart-item-price">R$ 149,00</p>
						<div class="hng-cart-item-quantity">
							<button type="button" class="hng-qty-btn hng-qty-minus">−</button>
							<input type="number" class="hng-qty-input" value="2" min="1" readonly />
							<button type="button" class="hng-qty-btn hng-qty-plus">+</button>
						</div>
					</div>
					<button type="button" class="hng-cart-item-remove" aria-label="Remover item">×</button>
				</div>
			<?php else : ?>
				<p class="hng-empty-cart"><?php esc_html_e( 'Seu carrinho está vazio', 'hng-commerce' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renderizar totais do carrinho
	 */
	private function render_cart_totals() {
		// Obter dados reais do carrinho se não for preview
		$cart              = null;
		$subtotal_value    = 0;
		$shipping_value    = 0;
		$has_quote_product = false;
		$needs_shipping    = false;
		$selected_shipping = null;
		$available_rates   = array();
		$saved_postcode    = '';

		if ( ! $this->is_preview && class_exists( 'HNG_Cart' ) ) {
			$cart              = HNG_Cart::instance();
			$subtotal_value    = $cart->get_subtotal();
			$shipping_value    = $cart->get_shipping_total();
			$needs_shipping    = $cart->needs_shipping();
			$selected_shipping = $cart->get_selected_shipping();
			$available_data    = $cart->get_available_shipping_rates();
			$available_rates   = $available_data['rates'] ?? array();
			$saved_postcode    = $available_data['postcode'] ?? '';

			// Verificar se há produtos do tipo orçamento
			foreach ( $cart->get_cart() as $item ) {
				$product = $item['data'] ?? null;
				if ( $product && method_exists( $product, 'get_product_type' ) && $product->get_product_type() === 'quote' ) {
					$has_quote_product = true;
					break;
				}
			}
		}

		$subtotal    = $this->is_preview ? 'R$ 397,80' : hng_price( $subtotal_value );
		$total_value = $subtotal_value + $shipping_value;
		$total       = $this->is_preview ? 'R$ 417,80' : hng_price( $total_value );

		// Sempre mostrar calculadora de frete (exceto para produtos de orçamento)
		$show_shipping_calculator = ! $has_quote_product;
		?>
		<div class="hng-cart-totals">
			<div class="hng-cart-subtotal">
				<span><?php esc_html_e( 'Subtotal:', 'hng-commerce' ); ?></span>
				<strong class="hng-subtotal-value"><?php echo esc_html( $subtotal ); ?></strong>
			</div>
			
			<!-- Linha de Frete -->
			<div class="hng-cart-shipping">
				<span><?php esc_html_e( 'Frete:', 'hng-commerce' ); ?></span>
				<strong class="hng-shipping-value">
					<?php
					if ( $has_quote_product ) {
						esc_html_e( 'Sob consulta', 'hng-commerce' );
					} elseif ( $this->is_preview ) {
						echo 'R$ 20,00';
					} elseif ( $shipping_value > 0 ) {
						echo esc_html( hng_price( $shipping_value ) );
					} else {
						echo '<span class="hng-shipping-pending">' . esc_html__( 'A calcular', 'hng-commerce' ) . '</span>';
					}
					?>
				</strong>
			</div>
			
			<!-- Campo de CEP compacto - sempre mostrar (exceto orçamento) -->
			<?php if ( $show_shipping_calculator ) : ?>
			<div class="hng-cart-cep-row">
				<input 
					type="text" 
					id="hng-cart-cep" 
					class="hng-cart-cep-input" 
					placeholder="<?php esc_attr_e( 'CEP', 'hng-commerce' ); ?>" 
					maxlength="9"
					value="<?php echo esc_attr( $saved_postcode ); ?>"
					aria-label="<?php esc_attr_e( 'CEP de entrega', 'hng-commerce' ); ?>"
				>
				<button type="button" class="hng-calc-shipping-btn" aria-label="<?php esc_attr_e( 'Calcular frete', 'hng-commerce' ); ?>">
					<span class="btn-text"><?php esc_html_e( 'Calcular', 'hng-commerce' ); ?></span>
					<span class="btn-loading" style="display:none;">
						<span class="hng-spinner"></span>
					</span>
				</button>
			</div>
			
			<!-- Opções de Frete (só aparecem após calcular) -->
			<div class="hng-shipping-options" style="<?php echo empty( $available_rates ) ? 'display:none;' : ''; ?>">
				<?php
				foreach ( $available_rates as $rate ) :
					$is_selected   = ( $selected_shipping && $selected_shipping['id'] === ( $rate['id'] ?? '' ) );
					$rate_price    = floatval( $rate['cost'] ?? 0 );
					$delivery_time = $rate['delivery_time'] ?? $rate['prazo'] ?? '';
					?>
				<label class="hng-shipping-option <?php echo $is_selected ? 'selected' : ''; ?>">
					<input type="radio" name="hng_shipping_rate" value="<?php echo esc_attr( $rate['id'] ?? '' ); ?>" <?php checked( $is_selected ); ?>>
					<span class="shipping-info">
						<span class="shipping-label"><?php echo esc_html( $rate['label'] ?? $rate['service_name'] ?? '' ); ?></span>
						<?php if ( $delivery_time ) : ?>
						<span class="shipping-time"><?php echo esc_html( $delivery_time ); ?></span>
						<?php endif; ?>
					</span>
					<span class="shipping-price"><?php echo $rate_price > 0 ? esc_html( hng_price( $rate_price ) ) : esc_html__( 'Grátis', 'hng-commerce' ); ?></span>
				</label>
				<?php endforeach; ?>
			</div>
			
			<!-- Mensagens -->
			<div class="hng-shipping-error" style="display:none;"></div>
			<div class="hng-shipping-message" style="display:none;"></div>
			<?php endif; ?>
			
			<div class="hng-cart-total">
				<span><?php esc_html_e( 'Total:', 'hng-commerce' ); ?></span>
				<strong class="hng-total-value"><?php echo esc_html( $total ); ?></strong>
			</div>
		</div>
		<?php
	}

	/**
	 * Renderizar carrinho em miniatura (para sticky)
	 */
	private function render_cart_mini() {
		// Obter dados reais do carrinho se não for preview
		$cart              = null;
		$subtotal_value    = 0;
		$shipping_value    = 0;
		$has_quote_product = false;
		$needs_shipping    = false;
		$selected_shipping = null;
		$available_rates   = array();
		$saved_postcode    = '';

		if ( ! $this->is_preview && class_exists( 'HNG_Cart' ) ) {
			$cart              = HNG_Cart::instance();
			$subtotal_value    = $cart->get_subtotal();
			$shipping_value    = $cart->get_shipping_total();
			$needs_shipping    = $cart->needs_shipping();
			$selected_shipping = $cart->get_selected_shipping();
			$available_data    = $cart->get_available_shipping_rates();
			$available_rates   = $available_data['rates'] ?? array();
			$saved_postcode    = $available_data['postcode'] ?? '';

			// Verificar se há produtos do tipo orçamento
			foreach ( $cart->get_cart() as $item ) {
				$product = $item['data'] ?? null;
				if ( $product && method_exists( $product, 'get_product_type' ) && $product->get_product_type() === 'quote' ) {
					$has_quote_product = true;
					break;
				}
			}
		}

		$subtotal    = $this->is_preview ? 'R$ 397,80' : hng_price( $subtotal_value );
		$total_value = $subtotal_value + $shipping_value;
		$total       = $this->is_preview ? 'R$ 417,80' : hng_price( $total_value );
		?>
		<div class="hng-mini-cart-header">
			<span><?php esc_html_e( 'Mini Carrinho', 'hng-commerce' ); ?></span>
			<button type="button" class="hng-cart-close" aria-label="<?php esc_attr_e( 'Fechar', 'hng-commerce' ); ?>">✕</button>
		</div>
		<div class="hng-mini-cart-content">
			<?php if ( $this->is_preview ) : ?>
				<div class="hng-mini-cart-item">
					<span>2x Produto 1</span>
					<span>R$ 199,80</span>
				</div>
				<div class="hng-mini-cart-item">
					<span>1x Produto 2</span>
					<span>R$ 149,00</span>
				</div>
			<?php else : ?>
				<p class="hng-mini-cart-empty"><?php esc_html_e( 'Nenhum item no carrinho', 'hng-commerce' ); ?></p>
			<?php endif; ?>
		</div>
		<div class="hng-mini-cart-footer">
			<div class="hng-mini-cart-totals">
				<div class="hng-mini-cart-subtotal">
					<span><?php esc_html_e( 'Subtotal:', 'hng-commerce' ); ?></span>
					<strong class="hng-subtotal-value"><?php echo esc_html( $subtotal ); ?></strong>
				</div>
				
				<?php if ( $needs_shipping || $this->is_preview ) : ?>
				<div class="hng-mini-cart-shipping">
					<span><?php esc_html_e( 'Frete:', 'hng-commerce' ); ?></span>
					<strong class="hng-shipping-value">
						<?php
						if ( $has_quote_product ) {
							esc_html_e( 'Sob consulta', 'hng-commerce' );
						} elseif ( $this->is_preview ) {
							echo 'R$ 20,00';
						} elseif ( $shipping_value > 0 ) {
							echo esc_html( hng_price( $shipping_value ) );
						} else {
							echo '<span class="hng-shipping-pending">' . esc_html__( 'A calcular', 'hng-commerce' ) . '</span>';
						}
						?>
					</strong>
				</div>
				
				<!-- Campo de CEP compacto -->
					<?php if ( ! $has_quote_product ) : ?>
				<div class="hng-mini-cart-cep-row">
					<input 
						type="text" 
						class="hng-cart-cep-input" 
						placeholder="<?php esc_attr_e( 'CEP', 'hng-commerce' ); ?>" 
						maxlength="9"
						value="<?php echo esc_attr( $saved_postcode ); ?>"
						aria-label="<?php esc_attr_e( 'CEP de entrega', 'hng-commerce' ); ?>"
					>
					<button type="button" class="hng-calc-shipping-btn" aria-label="<?php esc_attr_e( 'Calcular frete', 'hng-commerce' ); ?>">
						<span class="btn-text"><?php esc_html_e( 'Ok', 'hng-commerce' ); ?></span>
						<span class="btn-loading" style="display:none;">
							<span class="hng-spinner"></span>
						</span>
					</button>
				</div>
				
				<!-- Opções de Frete (só aparecem após calcular) -->
				<div class="hng-shipping-options" style="<?php echo empty( $available_rates ) ? 'display:none;' : ''; ?>">
						<?php
						foreach ( $available_rates as $rate ) :
							$is_selected   = ( $selected_shipping && $selected_shipping['id'] === ( $rate['id'] ?? '' ) );
							$rate_price    = floatval( $rate['cost'] ?? 0 );
							$delivery_time = $rate['delivery_time'] ?? $rate['prazo'] ?? '';
							?>
					<label class="hng-shipping-option <?php echo $is_selected ? 'selected' : ''; ?>">
						<input type="radio" name="hng_shipping_rate_mini" value="<?php echo esc_attr( $rate['id'] ?? '' ); ?>" <?php checked( $is_selected ); ?>>
						<span class="shipping-info">
							<span class="shipping-label"><?php echo esc_html( $rate['label'] ?? $rate['service_name'] ?? '' ); ?></span>
							<?php if ( $delivery_time ) : ?>
							<span class="shipping-time"><?php echo esc_html( $delivery_time ); ?></span>
							<?php endif; ?>
						</span>
						<span class="shipping-price"><?php echo $rate_price > 0 ? esc_html( hng_price( $rate_price ) ) : esc_html__( 'Grátis', 'hng-commerce' ); ?></span>
					</label>
						<?php endforeach; ?>
				</div>
				
				<!-- Mensagens -->
				<div class="hng-shipping-error" style="display:none;"></div>
				<div class="hng-shipping-message" style="display:none;"></div>
				<?php endif; ?>
				<?php endif; ?>
				
				<div class="hng-mini-cart-total">
					<span><?php esc_html_e( 'Total:', 'hng-commerce' ); ?></span>
					<strong class="hng-total-value"><?php echo esc_html( $total ); ?></strong>
				</div>
			</div>
			<div class="hng-mini-cart-actions">
				<a href="<?php echo esc_url( home_url( '/checkout/' ) ); ?>" class="hng-btn hng-btn-primary hng-btn-block">
					<?php esc_html_e( 'Checkout', 'hng-commerce' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * CSS de design baseado nas configuracoes do admin
	 */
	private function get_design_css() {
		$primary      = $this->get_preview_color( 'hng_cart_primary_color', '#2271b1' );
		$primary_dark = $this->get_preview_color( 'hng_cart_primary_dark_color', '#135e96' );
		$accent       = $this->get_preview_color( 'hng_cart_accent_color', '#FF7A00' );
		$text         = $this->get_preview_color( 'hng_cart_text_color', '#1f2937' );
		$surface      = $this->get_preview_color( 'hng_cart_surface_color', '#ffffff' );
		$header_bg    = $this->get_preview_color( 'hng_cart_header_bg', '#f9fafb' );
		$border_color = $this->get_preview_color( 'hng_cart_border_color', '#e5e7eb' );

		$radius = $this->get_preview_int( 'hng_cart_radius', 8, 0, 32 );
		if ( $radius > 32 ) {
			$radius = 32;
		}

		$font_size = $this->get_preview_int( 'hng_cart_font_size', 14, 12, 20 );

		$font_family = $this->get_preview_text( 'hng_cart_font_family', '' );
		$font_family = $font_family !== '' ? $font_family : 'inherit';

		$button_align      = $this->get_preview_text( 'hng_cart_button_align', 'center' );
		$button_align      = in_array( $button_align, array( 'left', 'center', 'right' ), true ) ? $button_align : 'center';
		$button_align_flex = $button_align === 'left' ? 'flex-start' : ( $button_align === 'right' ? 'flex-end' : 'center' );

		// Overlay opacity (0-100 to 0-1)
		$overlay_opacity = $this->get_preview_overlay_opacity() / 100;

		// Button size - Updated to match CSS classes
		$button_size   = $this->get_preview_button_size();
		$button_width  = $button_size === 'small' ? '40px' : ( $button_size === 'large' ? '60px' : '52px' );
		$button_height = $button_width;

		// Button style (border radius)
		$button_style  = $this->get_preview_button_style();
		$button_radius = $button_style === 'square' ? '0' : ( $button_style === 'pill' ? '50%' : '12px' );

		// Animation durations (in ms) - enforce minimum 100ms
		$animation_duration = $this->get_option_int( 'hng_cart_animation_duration', 400 );
		if ( $animation_duration < 100 ) {
			$animation_duration = 400;
		}
		$hover_text_duration = $this->get_option_int( 'hng_cart_hover_text_animation_duration', 300 );
		if ( $hover_text_duration < 100 ) {
			$hover_text_duration = 300;
		}

		$custom_css = $this->get_option_text( 'hng_cart_custom_css', '' );
		$custom_css = trim( wp_strip_all_tags( $custom_css ) );

		// New color options
		$btn_text_color = $this->get_preview_color( 'hng_cart_button_text_color', '#ffffff' );
		$hdr_text_color = $this->get_preview_color( 'hng_cart_header_text_color', '#1f2937' );

		// Cart gradient
		$cart_grad_on  = filter_var( get_option( 'hng_cart_gradient_enabled', false ), FILTER_VALIDATE_BOOLEAN );
		$cart_grad_c1  = get_option( 'hng_cart_gradient_color1', '#2271b1' );
		$cart_grad_c2  = get_option( 'hng_cart_gradient_color2', '#00c6ff' );
		$cart_grad_dir = get_option( 'hng_cart_gradient_direction', '135' );
		$cart_grad_bal = intval( get_option( 'hng_cart_gradient_balance', 50 ) );

		// Auto-calculate contrast color based on primary brightness.
		$contrast = $this->calculate_contrast_color( $primary );

		$css = ':root{' .
			'--hng-cart-primary:' . $primary . ';' .
			'--hng-cart-primary-dark:' . $primary_dark . ';' .
			'--hng-cart-accent:' . $accent . ';' .
			'--hng-cart-text:' . $text . ';' .
			'--hng-cart-surface:' . $surface . ';' .
			'--hng-cart-header-bg:' . $header_bg . ';' .
			'--hng-cart-border:' . $border_color . ';' .
			'--hng-cart-border-strong:#d1d5db;' .
			'--hng-cart-muted:#6b7280;' .
			'--hng-cart-contrast:' . $contrast . ';' .
			'--hng-cart-radius:' . $radius . 'px;' .
			'--hng-cart-font-family:' . $font_family . ';' .
			'--hng-cart-font-size:' . $font_size . 'px;' .
			'--hng-cart-button-align:' . $button_align . ';' .
			'--hng-cart-button-align-flex:' . $button_align_flex . ';' .
			'--hng-cart-overlay-opacity:' . $overlay_opacity . ';' .
			'--hng-cart-button-width:' . $button_width . ';' .
			'--hng-cart-button-height:' . $button_height . ';' .
			'--hng-cart-button-radius:' . $button_radius . ';' .
			'--hng-cart-animation-duration:' . $animation_duration . 'ms;' .
			'--hng-cart-hover-text-duration:' . $hover_text_duration . 'ms;' .
			'--hng-cart-btn-text-color:' . $btn_text_color . ';' .
			'--hng-cart-header-text-color:' . $hdr_text_color . ';' .
		'}';

		// Add gradient CSS if enabled
		if ( $cart_grad_on && ! empty( $cart_grad_c1 ) && ! empty( $cart_grad_c2 ) ) {
			$grad_css = 'linear-gradient(' . intval( $cart_grad_dir ) . 'deg, ' . $cart_grad_c1 . ' 0%, ' . $cart_grad_c1 . ' ' . $cart_grad_bal . '%, ' . $cart_grad_c2 . ' 100%)';
			$css     .= '.hng-cart-trigger{background:' . $grad_css . ' !important;}';
		}

		if ( $custom_css !== '' ) {
			$css .= '\n' . $custom_css;
		}

		return $css;
	}

	/**
	 * Preview detect
	 */
	private function is_preview_request() {
		return ! is_admin() && isset( $_GET['hng_cart_preview'] );
	}

	/**
	 * Get preview layout override
	 */
	private function get_preview_layout( $default ) {
		if ( ! $this->is_preview || ! isset( $_GET['hng_cart_display_type'] ) ) {
			return $default;
		}

		$layout = sanitize_key( wp_unslash( $_GET['hng_cart_display_type'] ) );
		return array_key_exists( $layout, self::LAYOUTS ) ? $layout : $default;
	}

	/**
	 * Get preview color
	 */
	private function get_preview_color( $key, $default ) {
		if ( $this->is_preview && isset( $_GET[ $key ] ) ) {
			$value = sanitize_hex_color( wp_unslash( $_GET[ $key ] ) );
			return $value ? $value : $default;
		}

		$value = sanitize_hex_color( get_option( $key, $default ) );
		return $value ? $value : $default;
	}

	/**
	 * Get preview text
	 */
	private function get_preview_text( $key, $default ) {
		if ( $this->is_preview && isset( $_GET[ $key ] ) ) {
			return sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
		}

		return $this->get_option_text( $key, $default );
	}

	/**
	 * Get preview int with range
	 */
	private function get_preview_int( $key, $default, $min, $max ) {
		$value = $default;
		if ( $this->is_preview && isset( $_GET[ $key ] ) ) {
			$value = absint( wp_unslash( $_GET[ $key ] ) );
		} else {
			$value = $this->get_option_int( $key, $default );
		}

		if ( $value < $min ) {
			return $min;
		}
		if ( $value > $max ) {
			return $max;
		}
		return $value;
	}

	/**
	 * Get preview overlay setting
	 */
	private function get_preview_overlay() {
		if ( $this->is_preview && isset( $_GET['hng_cart_overlay'] ) ) {
			return (bool) absint( wp_unslash( $_GET['hng_cart_overlay'] ) );
		}

		// Tratamento específico para overlay: respeitar false salvo (inclusive valor vazio)
		$missing = '__hng_missing_overlay_option__';
		$value   = get_option( 'hng_cart_overlay', $missing );
		if ( $value === $missing ) {
			return true;
		}

		return (bool) rest_sanitize_boolean( $value );
	}

	/**
	 * Get preview overlay opacity (0-100)
	 */
	private function get_preview_overlay_opacity() {
		if ( $this->is_preview && isset( $_GET['hng_cart_overlay_opacity'] ) ) {
			$val = absint( wp_unslash( $_GET['hng_cart_overlay_opacity'] ) );
			return min( max( $val, 0 ), 100 );
		}

		$val = $this->get_option_int( 'hng_cart_overlay_opacity', 50 );
		return min( max( $val, 0 ), 100 );
	}

	/**
	 * Get preview button size
	 */
	private function get_preview_button_size() {
		if ( $this->is_preview && isset( $_GET['hng_cart_button_size'] ) ) {
			$val     = sanitize_text_field( wp_unslash( $_GET['hng_cart_button_size'] ) );
			$allowed = array( 'small', 'medium', 'large' );
			return in_array( $val, $allowed, true ) ? $val : 'medium';
		}

		$val     = $this->get_option_text( 'hng_cart_button_size', 'medium' );
		$allowed = array( 'small', 'medium', 'large' );
		return in_array( $val, $allowed, true ) ? $val : 'medium';
	}

	/**
	 * Get preview button style
	 */
	private function get_preview_button_style() {
		if ( $this->is_preview && isset( $_GET['hng_cart_button_style'] ) ) {
			$val     = sanitize_text_field( wp_unslash( $_GET['hng_cart_button_style'] ) );
			$allowed = array( 'square', 'rounded', 'pill' );
			return in_array( $val, $allowed, true ) ? $val : 'rounded';
		}

		$val     = $this->get_option_text( 'hng_cart_button_style', 'rounded' );
		$allowed = array( 'square', 'rounded', 'pill' );
		return in_array( $val, $allowed, true ) ? $val : 'rounded';
	}

	/**
	 * Calculate contrast color (white or dark) based on background luminance
	 *
	 * @param string $hex_color Hex color string.
	 * @return string '#ffffff' for dark backgrounds, '#050505' for light backgrounds.
	 */
	private function calculate_contrast_color( $hex_color ) {
		$hex = ltrim( $hex_color, '#' );
		if ( strlen( $hex ) === 3 ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( strlen( $hex ) !== 6 ) {
			return '#ffffff';
		}
		$r         = hexdec( substr( $hex, 0, 2 ) );
		$g         = hexdec( substr( $hex, 2, 2 ) );
		$b         = hexdec( substr( $hex, 4, 2 ) );
		$luminance = ( 0.299 * $r + 0.587 * $g + 0.114 * $b ) / 255;
		return $luminance > 0.5 ? '#050505' : '#ffffff';
	}

	/**
	 * Get icon SVG para o carrinho
	 */
	public static function get_cart_icon() {
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
            <circle cx="9" cy="21" r="1"/>
            <circle cx="20" cy="21" r="1"/>
            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
        </svg>';
	}

	/**
	 * Get cart type
	 */
	public function get_type() {
		return $this->cart_type;
	}

	/**
	 * Get all layouts
	 */
	public static function get_layouts() {
		return self::LAYOUTS;
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
}

// Initialize
HNG_Cart_Display::instance();
