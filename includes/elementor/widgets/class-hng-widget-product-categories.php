<?php
/**
 * HNG Widget Product Categories
 *
 * @package HNG_Commerce
 */

// phpcs:disable Squiz.Commenting
// phpcs:disable WordPress.PHP.YodaConditions

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class HNG_Widget_Product_Categories extends HNG_Commerce_Elementor_Widget_Base {
	public function get_name() {
		return 'hng_product_categories'; }
	public function get_title() {
		return __( 'Categorias de Produtos', 'hng-commerce' ); }
	public function get_icon() {
		return 'eicon-folder'; }

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
					'label_categories_title',
					array(
						'label'   => __( 'Título das Categorias', 'hng-commerce' ),
						'type'    => \Elementor\Controls_Manager::TEXT,
						'default' => __( 'Categorias', 'hng-commerce' ),
					)
				);
				$this->add_control(
					'label_empty',
					array(
						'label'   => __( 'Mensagem Sem Categorias', 'hng-commerce' ),
						'type'    => \Elementor\Controls_Manager::TEXT,
						'default' => __( 'Nenhuma categoria encontrada.', 'hng-commerce' ),
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
					'categories_layout',
					array(
						'label'   => __( 'Layout das Categorias', 'hng-commerce' ),
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
			'show_image',
			array(
				'label'        => __( 'Mostrar Imagem', 'hng-commerce' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Sim', 'hng-commerce' ),
				'label_off'    => __( 'Não', 'hng-commerce' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_count',
			array(
				'label'        => __( 'Mostrar Contagem', 'hng-commerce' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Sim', 'hng-commerce' ),
				'label_off'    => __( 'Não', 'hng-commerce' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'orderby',
			array(
				'label'   => __( 'Ordenar por', 'hng-commerce' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'name',
				'options' => array(
					'name'  => __( 'Nome', 'hng-commerce' ),
					'count' => __( 'Contagem', 'hng-commerce' ),
					'id'    => __( 'ID', 'hng-commerce' ),
				),
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
				'label'      => __( 'Espaçamento entre Cards', 'hng-commerce' ),
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
					'{{WRAPPER}} .hng-categories-grid' => 'gap: {{SIZE}}{{UNIT}};',
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
					'{{WRAPPER}} .hng-category-card' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'card_border',
				'selector' => '{{WRAPPER}} .hng-category-card',
			)
		);

		$this->add_responsive_control(
			'card_border_radius',
			array(
				'label'      => __( 'Border Radius', 'hng-commerce' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .hng-category-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'card_shadow',
				'selector' => '{{WRAPPER}} .hng-category-card',
			)
		);

		$this->add_responsive_control(
			'card_padding',
			array(
				'label'      => __( 'Padding', 'hng-commerce' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .hng-category-card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'card_hover_background',
			array(
				'label'     => __( 'Cor de Fundo (Hover)', 'hng-commerce' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .hng-category-card:hover' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();

		// Image Style
		$this->start_controls_section(
			'image_style_section',
			array(
				'label'     => __( 'Imagem', 'hng-commerce' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array(
					'show_image' => 'yes',
				),
			)
		);

		$this->add_responsive_control(
			'image_height',
			array(
				'label'      => __( 'Altura', 'hng-commerce' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 100,
						'max' => 500,
					),
				),
				'default'    => array(
					'unit' => 'px',
					'size' => 200,
				),
				'selectors'  => array(
					'{{WRAPPER}} .hng-category-image' => 'height: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'image_border_radius',
			array(
				'label'      => __( 'Border Radius', 'hng-commerce' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .hng-category-image' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		// Title Style
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
					'{{WRAPPER}} .hng-category-title' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'title_typography',
				'selector' => '{{WRAPPER}} .hng-category-title',
			)
		);

		$this->add_control(
			'title_hover_color',
			array(
				'label'     => __( 'Cor (Hover)', 'hng-commerce' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .hng-category-card:hover .hng-category-title' => 'color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();

		// Count Style
		$this->start_controls_section(
			'count_style_section',
			array(
				'label'     => __( 'Contagem', 'hng-commerce' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array(
					'show_count' => 'yes',
				),
			)
		);

		$this->add_control(
			'count_color',
			array(
				'label'     => __( 'Cor', 'hng-commerce' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .hng-category-count' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'count_typography',
				'selector' => '{{WRAPPER}} .hng-category-count',
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$columns  = intval( $settings['columns'] );

		$terms = get_terms(
			array(
				'taxonomy'   => 'hng_product_category',
				'hide_empty' => true,
				'orderby'    => $settings['orderby'],
			)
		);

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			echo '<div class="hng-categories-empty">';
			echo '<p>' . esc_html__( 'Nenhuma categoria encontrada.', 'hng-commerce' ) . '</p>';
			echo '</div>';
			return;
		}

		?>
		<div class="hng-categories-grid" data-columns="<?php echo esc_attr( $columns ); ?>">
			<?php
			foreach ( $terms as $term ) :
				$thumbnail_id  = get_term_meta( $term->term_id, 'thumbnail_id', true );
				$category_link = get_term_link( $term );
				?>
				<a href="<?php echo esc_url( $category_link ); ?>" class="hng-category-card">
					<?php if ( $settings['show_image'] === 'yes' ) : ?>
						<div class="hng-category-image">
							<?php
							if ( $thumbnail_id ) {
								echo wp_get_attachment_image( $thumbnail_id, 'medium' );
							} else {
								echo '<img src="' . esc_url( HNG_COMMERCE_URL . 'assets/images/placeholder.svg' ) . '" alt="' . esc_attr( $term->name ) . '" />';
							}
							?>
						</div>
					<?php endif; ?>
					
					<h3 class="hng-category-title"><?php echo esc_html( $term->name ); ?></h3>
					
					<?php if ( $settings['show_count'] === 'yes' ) : ?>
						<span class="hng-category-count">
							<?php /* translators: %s: number of products in the category */ ?>
							<?php echo esc_html( sprintf( _n( '%s produto', '%s produtos', $term->count, 'hng-commerce' ), number_format_i18n( $term->count ) ) ); ?>
						</span>
					<?php endif; ?>
				</a>
			<?php endforeach; ?>
		</div>

		<?php
		$w = esc_attr( $this->get_unique_selector() );
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Static CSS with escaped selector.
		echo '<style>
			' . $w . ' .hng-categories-grid {
				display: grid;
				grid-template-columns: repeat(' . esc_attr( $columns ) . ', 1fr);
			}
			' . $w . ' .hng-category-card {
				display: block;
				text-decoration: none;
				transition: all 0.3s ease;
			}
			' . $w . ' .hng-category-image {
				width: 100%;
				overflow: hidden;
				margin-bottom: 15px;
			}
			' . $w . ' .hng-category-image img {
				width: 100%;
				object-fit: cover;
			}
			' . $w . ' .hng-category-title {
				margin: 10px 0;
			}
			' . $w . ' .hng-category-count {
				display: block;
				font-size: 0.9em;
				opacity: 0.7;
			}
			@media (max-width: 1024px) {
				' . $w . ' .hng-categories-grid {
					grid-template-columns: repeat(3, 1fr);
				}
			}
			@media (max-width: 768px) {
				' . $w . ' .hng-categories-grid {
					grid-template-columns: repeat(2, 1fr);
				}
			}
			@media (max-width: 480px) {
				' . $w . ' .hng-categories-grid {
					grid-template-columns: 1fr;
				}
			}
		</style>';
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
		<?php
	}
}
