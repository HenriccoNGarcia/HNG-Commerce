<?php // phpcs:disable Squiz.Commenting.FileComment.Missing
// phpcs:disable Squiz.Commenting.ClassComment.Missing
// phpcs:disable Squiz.Commenting.FunctionComment.Missing
// phpcs:disable Squiz.Commenting.InlineComment.InvalidEndChar
// phpcs:disable WordPress.PHP.YodaConditions.NotYoda
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class HNG_Widget_Upsell_Products extends HNG_Commerce_Elementor_Widget_Base {
	public function get_name() {
		return 'hng_upsell_products'; }
	public function get_title() {
		return __( 'Produtos Upsell', 'hng-commerce' ); }
	public function get_icon() {
		return 'eicon-products'; }

	protected function register_controls() {
				// Títulos, descrições e placeholders editáveis
				$this->start_controls_section(
					'labels_section',
					array(
						'label' => __( 'Textos e Placeholders', 'hng-commerce' ),
						'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
					)
				);
				$this->add_control(
					'label_upsell_title',
					array(
						'label'   => __( 'Título Upsell', 'hng-commerce' ),
						'type'    => \Elementor\Controls_Manager::TEXT,
						'default' => __( 'Produtos Recomendados', 'hng-commerce' ),
					)
				);
				$this->add_control(
					'label_empty',
					array(
						'label'   => __( 'Mensagem Sem Produtos', 'hng-commerce' ),
						'type'    => \Elementor\Controls_Manager::TEXT,
						'default' => __( 'Nenhum produto recomendado.', 'hng-commerce' ),
					)
				);
				$this->end_controls_section();

				// Variação de layout (grid/lista/carrossel)
				$this->start_controls_section(
					'layout_section',
					array(
						'label' => __( 'Layout', 'hng-commerce' ),
						'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
					)
				);
				$this->add_control(
					'upsell_layout',
					array(
						'label'   => __( 'Layout dos Produtos', 'hng-commerce' ),
						'type'    => \Elementor\Controls_Manager::SELECT,
						'options' => array(
							'grid'     => __( 'Grid', 'hng-commerce' ),
							'list'     => __( 'Lista', 'hng-commerce' ),
							'carousel' => __( 'Carrossel', 'hng-commerce' ),
						),
						'default' => 'grid',
					)
				);
				$this->end_controls_section();
		// Content Section
		$this->start_controls_section(
			'content_section',
			array(
				'label' => __( 'Configurações', 'hng-commerce' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'columns',
			array(
				'label'   => __( 'Colunas', 'hng-commerce' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'default' => 4,
				'min'     => 1,
				'max'     => 6,
			)
		);

		$this->add_control(
			'products_count',
			array(
				'label'   => __( 'Número de Produtos', 'hng-commerce' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'default' => 4,
				'min'     => 1,
				'max'     => 12,
			)
		);

		$this->add_control(
			'show_image',
			array(
				'label'   => __( 'Mostrar Imagem', 'hng-commerce' ),
				'type'    => \Elementor\Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'show_title',
			array(
				'label'   => __( 'Mostrar Título', 'hng-commerce' ),
				'type'    => \Elementor\Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'show_price',
			array(
				'label'   => __( 'Mostrar Preço', 'hng-commerce' ),
				'type'    => \Elementor\Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'show_button',
			array(
				'label'   => __( 'Mostrar Botão', 'hng-commerce' ),
				'type'    => \Elementor\Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->end_controls_section();

		// Container Style
		$this->start_controls_section(
			'container_style_section',
			array(
				'label' => __( 'Container', 'hng-commerce' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'container_gap',
			array(
				'label'      => __( 'Espaçamento', 'hng-commerce' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 100,
					),
				),
				'default'    => array(
					'unit' => 'px',
					'size' => 20,
				),
				'selectors'  => array(
					'{{WRAPPER}} .hng-upsell-grid' => 'gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		// Card Style
		$this->start_controls_section(
			'card_style_section',
			array(
				'label' => __( 'Card', 'hng-commerce' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'card_background',
			array(
				'label'     => __( 'Cor de Fundo', 'hng-commerce' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .hng-upsell-card' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'card_border',
				'selector' => '{{WRAPPER}} .hng-upsell-card',
			)
		);

		$this->add_responsive_control(
			'card_border_radius',
			array(
				'label'      => __( 'Border Radius', 'hng-commerce' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .hng-upsell-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'card_shadow',
				'selector' => '{{WRAPPER}} .hng-upsell-card',
			)
		);

		$this->add_responsive_control(
			'card_padding',
			array(
				'label'      => __( 'Padding', 'hng-commerce' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .hng-upsell-card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		// Title, Price, Button styles (similar to Product Grid)
		$this->start_controls_section(
			'title_style_section',
			array(
				'label' => __( 'Título', 'hng-commerce' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'title_color',
			array(
				'label'     => __( 'Cor', 'hng-commerce' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .hng-upsell-title' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'title_typography',
				'selector' => '{{WRAPPER}} .hng-upsell-title',
			)
		);

		$this->end_controls_section();

		// Price Style
		$this->start_controls_section(
			'price_style_section',
			array(
				'label' => __( 'Preço', 'hng-commerce' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'price_color',
			array(
				'label'     => __( 'Cor', 'hng-commerce' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .hng-upsell-price' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'price_typography',
				'selector' => '{{WRAPPER}} .hng-upsell-price',
			)
		);

		$this->end_controls_section();

		// Button Style
		$this->start_controls_section(
			'button_style_section',
			array(
				'label' => __( 'Botão', 'hng-commerce' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->start_controls_tabs( 'button_tabs' );

		$this->start_controls_tab( 'button_normal', array( 'label' => __( 'Normal', 'hng-commerce' ) ) );

		$this->add_control(
			'button_color',
			array(
				'label'     => __( 'Cor', 'hng-commerce' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .hng-add-to-cart-button' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'button_background',
			array(
				'label'     => __( 'Fundo', 'hng-commerce' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .hng-add-to-cart-button' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'button_hover', array( 'label' => __( 'Hover', 'hng-commerce' ) ) );

		$this->add_control(
			'button_hover_color',
			array(
				'label'     => __( 'Cor', 'hng-commerce' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .hng-add-to-cart-button:hover' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'button_hover_background',
			array(
				'label'     => __( 'Fundo', 'hng-commerce' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .hng-add-to-cart-button:hover' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_tab();
		$this->end_controls_tabs();

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'      => 'button_typography',
				'selector'  => '{{WRAPPER}} .hng-add-to-cart-button',
				'separator' => 'before',
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$columns  = intval( $settings['columns'] );
		$count    = intval( $settings['products_count'] );

		// Buscar produtos aleatórios ou relacionados
		$args = array(
			'post_type'      => 'hng_product',
			'posts_per_page' => $count,
			'orderby'        => 'rand',
			'post_status'    => 'publish',
		);

		$query = new WP_Query( $args );

		if ( ! $query->have_posts() ) {
			echo '<div class="hng-upsell-empty">';
			echo '<p>' . esc_html__( 'Nenhum produto disponível.', 'hng-commerce' ) . '</p>';
			echo '</div>';
			return;
		}

		?>
		<div class="hng-upsell-grid" data-columns="<?php echo esc_attr( $columns ); ?>">
			<?php
			while ( $query->have_posts() ) :
				$query->the_post();
				$product = new HNG_Product( get_the_ID() );
				?>
				<div class="hng-upsell-card">
					<?php if ( $settings['show_image'] === 'yes' ) : ?>
						<div class="hng-upsell-image">
							<?php
							if ( has_post_thumbnail() ) {
								the_post_thumbnail( 'medium' );
							} else {
								echo '<img src="' . esc_url( HNG_COMMERCE_URL . 'assets/images/placeholder.svg' ) . '" alt="' . esc_attr( $product->get_name() ) . '" />';
							}
							?>
						</div>
					<?php endif; ?>

					<?php if ( $settings['show_title'] === 'yes' ) : ?>
						<h3 class="hng-upsell-title">
							<a href="<?php the_permalink(); ?>"><?php echo esc_html( $product->get_name() ); ?></a>
						</h3>
					<?php endif; ?>

					<?php if ( $settings['show_price'] === 'yes' ) : ?>
						<div class="hng-upsell-price">
							<?php echo esc_html( hng_price( $product->get_price() ) ); ?>
						</div>
					<?php endif; ?>

					<?php if ( $settings['show_button'] === 'yes' ) : ?>
						<button class="hng-add-to-cart-button" data-product-id="<?php echo esc_attr( get_the_ID() ); ?>">
							<?php esc_html_e( 'Adicionar', 'hng-commerce' ); ?>
						</button>
					<?php endif; ?>
				</div>
				<?php
			endwhile;
			wp_reset_postdata();
			?>
		</div>

		<?php
		$w = esc_attr( $this->get_unique_selector() );
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Static CSS with escaped selector.
		echo '<style>
			' . $w . ' .hng-upsell-grid {
				display: grid;
				grid-template-columns: repeat(' . esc_attr( $columns ) . ', 1fr);
			}
			' . $w . ' .hng-upsell-card {
				transition: all 0.3s ease;
			}
			' . $w . ' .hng-upsell-image {
				width: 100%;
				overflow: hidden;
				margin-bottom: 15px;
			}
			' . $w . ' .hng-upsell-image img {
				width: 100%;
				height: 250px;
				object-fit: cover;
			}
			' . $w . ' .hng-upsell-title a {
				text-decoration: none;
				color: inherit;
			}
			' . $w . ' .hng-add-to-cart-button {
				width: 100%;
				padding: 10px 20px;
				cursor: pointer;
				border: none;
				transition: all 0.3s ease;
			}
			@media (max-width: 768px) {
				' . $w . ' .hng-upsell-grid {
					grid-template-columns: repeat(2, 1fr);
				}
			}
		</style>';
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		?>

		<script>
		jQuery(document).ready(function($) {
			$('.hng-add-to-cart-button').on('click', function() {
				var $button = $(this);
				var productId = $button.data('product-id');
				
				$button.prop('disabled', true).text('<?php esc_html_e( 'Adicionando...', 'hng-commerce' ); ?>');
				
				$.ajax({
					url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
					type: 'POST',
					data: {
						action: 'hng_add_to_cart',
						product_id: productId,
						quantity: 1,
						nonce: '<?php echo esc_attr( wp_create_nonce( 'hng-add-to-cart' ) ); ?>'
					},
					success: function(response) {
						if (response.success) {
							$button.text('<?php esc_html_e( 'Adicionado!', 'hng-commerce' ); ?>');
							setTimeout(function() {
								$button.prop('disabled', false).text('<?php esc_html_e( 'Adicionar', 'hng-commerce' ); ?>');
							}, 2000);
						}
					}
				});
			});
		});
		</script>
		<?php
	}
}
