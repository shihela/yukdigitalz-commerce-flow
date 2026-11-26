<?php
/**
 * Product edit metabox for direct checkout links and [yukcomflow_buy_button] shortcode.
 *
 * @package Yukdigitalz_Commerce_Flow
 */

defined( 'ABSPATH' ) || exit;

class YukComFlow_ProductLinks {

	/**
	 * Register hooks and shortcode.
	 *
	 * @return void
	 */
	public static function init() {
		add_shortcode( 'yukcomflow_buy_button', array( __CLASS__, 'render_buy_button_shortcode' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_product_metabox' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_product_admin_assets' ) );
	}

	/**
	 * Build a direct checkout URL for a product and optional parameters.
	 *
	 * @param string $product_slug Product post_name slug.
	 * @param array  $args         Optional keys: plan, cycle, coupon, qty.
	 * @return string
	 */
	public static function build_checkout_link( $product_slug, $args = array() ) {
		$product_slug = sanitize_title( (string) $product_slug );
		if ( '' === $product_slug ) {
			return '';
		}

		$base_slug = YukComFlow_Settings::get_base_slug();
		$url       = home_url( '/' . $base_slug . '/' . $product_slug . '/' );

		$query_args = array();
		if ( ! empty( $args['plan'] ) ) {
			$query_args['plan'] = sanitize_text_field( (string) $args['plan'] );
		}
		if ( ! empty( $args['cycle'] ) ) {
			$query_args['cycle'] = sanitize_text_field( (string) $args['cycle'] );
		}
		if ( ! empty( $args['coupon'] ) ) {
			$query_args['coupon'] = sanitize_text_field( (string) $args['coupon'] );
		}
		if ( ! empty( $args['qty'] ) && absint( $args['qty'] ) > 1 ) {
			$query_args['qty'] = absint( $args['qty'] );
		}

		return ! empty( $query_args ) ? add_query_arg( $query_args, $url ) : $url;
	}

	/**
	 * Render the [yukcomflow_buy_button] shortcode.
	 *
	 * Example:
	 * [yukcomflow_buy_button product="pro-suite" plan="agency" cycle="yearly" coupon="LAUNCH20" qty="1" label="Get Agency Plan"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function render_buy_button_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'product' => '',
				'plan'    => '',
				'cycle'   => '',
				'coupon'  => '',
				'qty'     => 1,
				'label'   => __( 'Buy Now', 'yukdigitalz-commerce-flow' ),
				'class'   => 'button yukcomflow-buy-button',
				'target'  => '_self',
			),
			$atts,
			'yukcomflow_buy_button'
		);

		$product_slug = '';
		$raw_product  = trim( (string) $atts['product'] );

		if ( '' === $raw_product && is_singular( 'product' ) ) {
			$product_slug = (string) get_post_field( 'post_name', get_queried_object_id() );
		} elseif ( is_numeric( $raw_product ) ) {
			$product_slug = (string) get_post_field( 'post_name', absint( $raw_product ) );
		} else {
			$product_slug = sanitize_title( $raw_product );
		}

		if ( '' === $product_slug ) {
			return '';
		}

		$url = self::build_checkout_link(
			$product_slug,
			array(
				'plan'   => $atts['plan'],
				'cycle'  => $atts['cycle'],
				'coupon' => $atts['coupon'],
				'qty'    => absint( $atts['qty'] ),
			)
		);

		if ( '' === $url ) {
			return '';
		}

		$classes = array_filter( array_map( 'sanitize_html_class', preg_split( '/\s+/', (string) $atts['class'] ) ) );
		$target  = '_blank' === $atts['target'] ? '_blank' : '_self';
		$rel     = '_blank' === $target ? ' rel="noopener noreferrer"' : '';

		return sprintf(
			'<a href="%1$s" class="%2$s" target="%3$s"%4$s>%5$s</a>',
			esc_url( $url ),
			esc_attr( implode( ' ', $classes ) ),
			esc_attr( $target ),
			$rel,
			esc_html( (string) $atts['label'] )
		);
	}

	/**
	 * Register the Commerce Flow Links metabox on the WooCommerce product editor.
	 *
	 * @return void
	 */
	public static function register_product_metabox() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		add_meta_box(
			'yukcomflow_product_links',
			__( 'Commerce Flow Direct Links', 'yukdigitalz-commerce-flow' ),
			array( __CLASS__, 'render_product_metabox' ),
			'product',
			'normal',
			'default'
		);
	}

	/**
	 * Enqueue admin CSS/JS on product edit screens for the copy buttons.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public static function enqueue_product_admin_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style(
			'yukcomflow-admin',
			YUKCOMFLOW_PLUGIN_URL . 'assets/css/yukcomflow-admin.css',
			array(),
			YUKCOMFLOW_VERSION
		);

		wp_enqueue_script(
			'yukcomflow-admin',
			YUKCOMFLOW_PLUGIN_URL . 'assets/js/yukcomflow-admin.js',
			array(),
			YUKCOMFLOW_VERSION,
			true
		);

		wp_localize_script(
			'yukcomflow-admin',
			'yukComFlowAdmin',
			array(
				'copiedText' => __( 'Copied!', 'yukdigitalz-commerce-flow' ),
			)
		);
	}

	/**
	 * Render the Commerce Flow Links metabox content.
	 *
	 * @param WP_Post $post Current product post object.
	 * @return void
	 */
	public static function render_product_metabox( $post ) {
		$product = wc_get_product( $post->ID );
		if ( ! $product instanceof WC_Product ) {
			echo '<p>' . esc_html__( 'Save this product first to generate direct checkout links.', 'yukdigitalz-commerce-flow' ) . '</p>';
			return;
		}

		$slug = $post->post_name;
		if ( '' === $slug ) {
			echo '<p>' . esc_html__( 'Publish or save a draft with a product slug to generate direct checkout links.', 'yukdigitalz-commerce-flow' ) . '</p>';
			return;
		}

		$rows = array();

		if ( $product->is_type( 'simple' ) ) {
			$url       = self::build_checkout_link( $slug );
			$shortcode = sprintf( '[yukcomflow_buy_button product="%s" label="%s"]', $slug, __( 'Buy Now', 'yukdigitalz-commerce-flow' ) );
			$rows[]    = array(
				'label'     => __( 'Simple Product', 'yukdigitalz-commerce-flow' ),
				'url'       => $url,
				'shortcode' => $shortcode,
			);
		} elseif ( $product->is_type( 'variable' ) ) {
			$children = $product->get_children();
			foreach ( $children as $variation_id ) {
				$variation = wc_get_product( $variation_id );
				if ( ! $variation instanceof WC_Product_Variation || 'publish' !== $variation->get_status() ) {
					continue;
				}

				$attrs        = array_values( array_filter( array_map( 'strval', $variation->get_variation_attributes() ) ) );
				$plan_val     = isset( $attrs[0] ) ? $attrs[0] : '';
				$cycle_val    = isset( $attrs[1] ) ? $attrs[1] : '';
				$url          = self::build_checkout_link(
					$slug,
					array(
						'plan'  => $plan_val,
						'cycle' => $cycle_val,
					)
				);
				$label_parts  = ! empty( $attrs ) ? implode( ' / ', $attrs ) : sprintf( '#%d', $variation->get_id() );
				$sc_parts     = array( 'product="' . $slug . '"' );
				if ( '' !== $plan_val ) {
					$sc_parts[] = 'plan="' . $plan_val . '"';
				}
				if ( '' !== $cycle_val ) {
					$sc_parts[] = 'cycle="' . $cycle_val . '"';
				}
				$sc_parts[] = 'label="' . __( 'Buy Now', 'yukdigitalz-commerce-flow' ) . '"';

				$rows[] = array(
					'label'     => $label_parts,
					'url'       => $url,
					'shortcode' => '[yukcomflow_buy_button ' . implode( ' ', $sc_parts ) . ']',
				);
			}
		}

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No published simple product or variations available yet. Add variations to generate plan links.', 'yukdigitalz-commerce-flow' ) . '</p>';
			return;
		}

		echo '<p class="description">' . esc_html__( 'Copy a direct checkout link or shortcode below. You can also append &coupon=CODE or &qty=2 to any URL.', 'yukdigitalz-commerce-flow' ) . '</p>';
		echo '<table class="widefat striped yukcomflow-links-table">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Plan / Variation', 'yukdigitalz-commerce-flow' ) . '</th>';
		echo '<th>' . esc_html__( 'Direct Checkout URL', 'yukdigitalz-commerce-flow' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'yukdigitalz-commerce-flow' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			echo '<tr>';
			echo '<td><strong>' . esc_html( $row['label'] ) . '</strong></td>';
			echo '<td><code>' . esc_html( $row['url'] ) . '</code></td>';
			echo '<td><div class="yukcomflow-action-buttons">';
			printf(
				'<button type="button" class="button yukcomflow-copy-btn" data-url="%1$s">%2$s</button>',
				esc_url( $row['url'] ),
				esc_html__( 'Copy URL', 'yukdigitalz-commerce-flow' )
			);
			printf(
				'<button type="button" class="button yukcomflow-copy-btn" data-url="%1$s">%2$s</button>',
				esc_attr( $row['shortcode'] ),
				esc_html__( 'Copy Shortcode', 'yukdigitalz-commerce-flow' )
			);
			echo '</div></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}
}
