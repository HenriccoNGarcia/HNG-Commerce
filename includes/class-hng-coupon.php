<?php
/**
 * Classe de Cupom
 *
 * @package HNG_Commerce
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Helpers DB.
if ( file_exists( HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php' ) ) {
	require_once HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php';
}

/**
 * HNG Coupon Class.
 *
 * Handles coupon functionality for HNG Commerce.
 *
 * @since 1.0.0
 */
class HNG_Coupon {

	/**
	 * ID do cupom.
	 *
	 * @var int
	 */
	private $id = 0;

	/**
	 * Dados do cupom.
	 *
	 * @var array
	 */
	private $data = array();

	/**
	 * Construtor.
	 *
	 * @param int $coupon_id Coupon ID.
	 */
	public function __construct( $coupon_id = 0 ) {
		if ( $coupon_id > 0 ) {
			$this->load( $coupon_id );
		}
	}

	/**
	 * Carregar cupom do banco.
	 *
	 * @param int $coupon_id Coupon ID.
	 * @return bool
	 */
	private function load( $coupon_id ) {
		$post = get_post( $coupon_id );

		if ( ! $post || ! isset( $post->post_type ) || 'hng_coupon' !== $post->post_type ) {
			return false;
		}

		$this->id = $post->ID;

		// Carregar meta dados.
		$discount_type = get_post_meta( $post->ID, '_discount_type', true );
		$this->data    = array(
			'code'                 => isset( $post->post_title ) ? $post->post_title : '',
			'description'          => isset( $post->post_content ) ? $post->post_content : '',
			'discount_type'        => $discount_type ? $discount_type : 'percent',
			'discount_amount'      => floatval( get_post_meta( $post->ID, '_discount_amount', true ) ),
			'minimum_amount'       => floatval( get_post_meta( $post->ID, '_minimum_amount', true ) ),
			'maximum_amount'       => floatval( get_post_meta( $post->ID, '_maximum_amount', true ) ),
			'individual_use'       => get_post_meta( $post->ID, '_individual_use', true ) === 'yes',
			'exclude_sale_items'   => get_post_meta( $post->ID, '_exclude_sale_items', true ) === 'yes',
			'product_ids'          => array_filter( array_map( 'intval', (array) get_post_meta( $post->ID, '_product_ids', true ) ) ),
			'exclude_product_ids'  => array_filter( array_map( 'intval', (array) get_post_meta( $post->ID, '_exclude_product_ids', true ) ) ),
			'product_categories'   => array_filter( array_map( 'intval', (array) get_post_meta( $post->ID, '_product_categories', true ) ) ),
			'exclude_categories'   => array_filter( array_map( 'intval', (array) get_post_meta( $post->ID, '_exclude_categories', true ) ) ),
			'usage_limit'          => intval( get_post_meta( $post->ID, '_usage_limit', true ) ),
			'usage_limit_per_user' => intval( get_post_meta( $post->ID, '_usage_limit_per_user', true ) ),
			'usage_count'          => intval( get_post_meta( $post->ID, '_usage_count', true ) ),
			'expiry_date'          => get_post_meta( $post->ID, '_expiry_date', true ),
			'free_shipping'        => get_post_meta( $post->ID, '_free_shipping', true ) === 'yes',
		);

		return true;
	}

	/**
	 * Obter ID
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Obter cï¿½digo
	 */
	public function get_code() {
		return $this->data['code'] ?? '';
	}

	/**
	 * Obter tipo de desconto
	 */
	public function get_discount_type() {
		return $this->data['discount_type'] ?? 'percent';
	}

	/**
	 * Obter valor do desconto
	 */
	public function get_amount() {
		return $this->data['discount_amount'] ?? 0;
	}

	/**
	 * Obter valor mï¿½nimo
	 */
	public function get_minimum_amount() {
		return $this->data['minimum_amount'] ?? 0;
	}

	/**
	 * Obter valor mï¿½ximo de desconto
	 */
	public function get_maximum_amount() {
		return $this->data['maximum_amount'] ?? 0;
	}

	/**
	 * Verificar se é válido.
	 *
	 * @return bool
	 */
	public function is_valid() {
		if ( ! $this->id ) {
			return false;
		}

		// Verificar se está publicado.
		if ( 'publish' !== get_post_status( $this->id ) ) {
			return false;
		}

		// Verificar data de expiração.
		if ( ! empty( $this->data['expiry_date'] ) ) {
			$expiry = strtotime( $this->data['expiry_date'] );
			if ( $expiry < time() ) {
				return false;
			}
		}

		// Verificar limite de uso.
		if ( $this->data['usage_limit'] > 0 ) {
			if ( $this->data['usage_count'] >= $this->data['usage_limit'] ) {
				return false;
			}
		}

		// Verificar limite de uso por usuário.
		if ( $this->data['usage_limit_per_user'] > 0 && is_user_logged_in() ) {
			$user_id    = get_current_user_id();
			$user_usage = $this->get_user_usage_count( $user_id );

			if ( $user_usage >= $this->data['usage_limit_per_user'] ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Validar cupom para o carrinho.
	 *
	 * @param HNG_Cart $cart Cart object.
	 * @return bool
	 * @throws Exception When validation fails.
	 */
	public function validate_for_cart( $cart ) {
		if ( ! $this->is_valid() ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not output directly.
			throw new Exception( __( 'Este cupom não é válido.', 'hng-commerce' ) );
		}

		// Verificar valor mínimo.
		if ( $this->get_minimum_amount() > 0 ) {
			if ( $cart->get_subtotal() < $this->get_minimum_amount() ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not output directly.
				throw new Exception(
					sprintf(
						/* translators: %s: formatted minimum amount (e.g. R$ 10,00) */
						esc_html__( 'O valor mínimo para este cupom é %s', 'hng-commerce' ),
						esc_html( hng_price( $this->get_minimum_amount() ) )
					)
				);
			}
		}

		// Verificar uso individual.
		if ( $this->data['individual_use'] && count( $cart->get_applied_coupons() ) > 0 ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not output directly.
			throw new Exception( __( 'Este cupom não pode ser usado com outros cupons.', 'hng-commerce' ) );
		}

		// Verificar produtos específicos.
		if ( ! empty( $this->data['product_ids'] ) || ! empty( $this->data['exclude_product_ids'] ) ) {
			$valid_products = false;

			foreach ( $cart->get_cart() as $item ) {
				$product_id = $item['product_id'];

				// Produtos excluídos.
				if ( in_array( $product_id, $this->data['exclude_product_ids'], true ) ) {
					continue;
				}

				// Produtos permitidos (se especificados).
				if ( ! empty( $this->data['product_ids'] ) ) {
					if ( in_array( $product_id, $this->data['product_ids'], true ) ) {
						$valid_products = true;
						break;
					}
				} else {
					$valid_products = true;
					break;
				}
			}

			if ( ! $valid_products ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not output directly.
				throw new Exception( __( 'Este cupom não é válido para os produtos no carrinho.', 'hng-commerce' ) );
			}
		}

		// Verificar categorias.
		if ( ! empty( $this->data['product_categories'] ) || ! empty( $this->data['exclude_categories'] ) ) {
			$valid_categories = false;

			foreach ( $cart->get_cart() as $item ) {
				$product_id = $item['product_id'];
				$categories = wp_get_post_terms( $product_id, 'hng_product_cat', array( 'fields' => 'ids' ) );

				// Categorias excluídas.
				if ( array_intersect( $categories, $this->data['exclude_categories'] ) ) {
					continue;
				}

				// Categorias permitidas (se especificadas).
				if ( ! empty( $this->data['product_categories'] ) ) {
					if ( array_intersect( $categories, $this->data['product_categories'] ) ) {
						$valid_categories = true;
						break;
					}
				} else {
					$valid_categories = true;
					break;
				}
			}

			if ( ! $valid_categories ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not output directly.
				throw new Exception( __( 'Este cupom não é válido para as categorias no carrinho.', 'hng-commerce' ) );
			}
		}

		// Verificar itens em promoção.
		if ( $this->data['exclude_sale_items'] ) {
			$has_regular_items = false;

			foreach ( $cart->get_cart() as $item ) {
				$product = hng_get_product( $item['product_id'] );

				if ( ! $product->is_on_sale() ) {
					$has_regular_items = true;
					break;
				}
			}

			if ( ! $has_regular_items ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not output directly.
				throw new Exception( __( 'Este cupom não é válido para itens em promoção.', 'hng-commerce' ) );
			}
		}

		return true;
	}

	/**
	 * Calcular desconto.
	 *
	 * @param HNG_Cart $cart Cart object.
	 * @return float
	 */
	public function get_discount_amount_for_cart( $cart ) {
		$discount = 0;
		$subtotal = $cart->get_subtotal();

		if ( 'percent' === $this->get_discount_type() ) {
			// Desconto percentual.
			$discount = ( $subtotal * $this->get_amount() ) / 100;

			// Verificar valor máximo.
			if ( $this->get_maximum_amount() > 0 ) {
				$discount = min( $discount, $this->get_maximum_amount() );
			}
		} else {
			// Desconto fixo.
			$discount = $this->get_amount();

			// Não pode ser maior que o subtotal.
			$discount = min( $discount, $subtotal );
		}

		return $discount;
	}

	/**
	 * Oferece frete grátis?
	 *
	 * @return bool
	 */
	public function get_free_shipping() {
		return $this->data['free_shipping'] ?? false;
	}

	/**
	 * Incrementar uso.
	 *
	 * @param int $user_id  User ID.
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function increment_usage_count( $user_id = 0, $order_id = 0 ) {
		// Incrementar contador geral.
		++$this->data['usage_count'];
		update_post_meta( $this->id, '_usage_count', $this->data['usage_count'] );

		// Registrar uso por usuário.
		if ( $user_id > 0 ) {
			global $wpdb;

			$usage_table = function_exists( 'hng_db_full_table_name' ) ? hng_db_full_table_name( 'hng_coupon_usage' ) : ( $wpdb->prefix . 'hng_coupon_usage' );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$usage_table,
				array(
					'coupon_id' => $this->id,
					'user_id'   => $user_id,
					'order_id'  => $order_id,
					'used_at'   => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%d', '%s' )
			);
		}
	}

	/**
	 * Obter contagem de uso por usuário.
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	private function get_user_usage_count( $user_id ) {
		global $wpdb;

		$usage_table = function_exists( 'hng_db_full_table_name' ) ? hng_db_full_table_name( 'hng_coupon_usage' ) : ( $wpdb->prefix . 'hng_coupon_usage' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Dynamic table name is controlled.
				"SELECT COUNT(*) FROM {$usage_table} WHERE coupon_id = %d AND user_id = %d",
				$this->id,
				$user_id
			)
		);
	}

	/**
	 * Buscar cupom por código.
	 *
	 * @param string $code Coupon code.
	 * @return HNG_Coupon|null
	 */
	public static function get_by_code( $code ) {
		$posts = get_posts(
			array(
				'post_type'      => 'hng_coupon',
				'post_status'    => 'publish',
				'title'          => $code,
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		if ( empty( $posts ) ) {
			// Tentar busca exata via query direta.
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$coupon_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'hng_coupon' AND post_status = 'publish' AND post_title = %s LIMIT 1",
					$code
				)
			);

			if ( ! $coupon_id ) {
				return false;
			}

			return new self( $coupon_id );
		}

		return new self( $posts[0] );
	}
}
