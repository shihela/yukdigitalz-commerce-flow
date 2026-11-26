<?php
/**
 * Optional SaaS Checkout UX enhancements:
 * - Lean Digital Checkout Mode (virtual/non-shippable carts only)
 * - In-Checkout Plan Switcher (Classic Checkout, Block Checkout, and shortcode)
 *
 * @package Yukdigitalz_Commerce_Flow
 */

defined( 'ABSPATH' ) || exit;

class YukComFlow_CheckoutUX {

	/**
	 * Prevent rendering the plan switcher twice on the same request.
	 *
	 * @var bool
	 */
	private static $switcher_rendered = false;

	/**
	 * Register checkout UX hooks and shortcode.
	 *
	 * @return void
	 */
	public static function init() {
		add_shortcode( 'yukcomflow_plan_switcher', array( __CLASS__, 'render_plan_switcher_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_checkout_assets' ) );
		add_filter( 'body_class', array( __CLASS__, 'add_body_classes' ) );

		// Lean Digital Checkout Mode hooks (Classic + Store API / Block Checkout).
		add_filter( 'woocommerce_checkout_fields', array( __CLASS__, 'filter_classic_checkout_fields' ), 20 );
		add_filter( 'woocommerce_default_address_fields', array( __CLASS__, 'filter_default_address_fields' ), 20 );
		add_filter( 'woocommerce_enable_order_notes_field', array( __CLASS__, 'filter_order_notes_field' ), 20 );

		// In-Checkout Plan Switcher hooks (Classic Checkout + Block Checkout).
		add_action( 'woocommerce_before_checkout_form', array( __CLASS__, 'render_classic_plan_switcher' ), 15 );
		add_filter( 'render_block_woocommerce/checkout', array( __CLASS__, 'prepend_block_plan_switcher' ), 10, 2 );
	}

	/**
	 * Determine whether Lean Digital Checkout Mode should apply to the current cart.
	 *
	 * Strictly requires the setting to be enabled AND a non-empty virtual (non-shippable) cart.
	 *
	 * @return bool
	 */
	public static function should_apply_lean_checkout() {
		if ( ! YukComFlow_Settings::is_lean_checkout_enabled() ) {
			return false;
		}

		if ( ! function_exists( 'WC' ) ) {
			return false;
		}

		$wc = WC();
		if ( ! is_object( $wc ) || ! isset( $wc->cart ) || $wc->cart->is_empty() ) {
			return false;
		}

		// Never strip address fields if any item in the cart requires physical shipping.
		if ( $wc->cart->needs_shipping() ) {
			return false;
		}

		/**
		 * Filter whether Lean Digital Checkout Mode should apply to the current cart.
		 *
		 * @param bool $should_apply Default true for virtual carts when setting is enabled.
		 */
		return (bool) apply_filters( 'yukcomflow_should_apply_lean_checkout', true );
	}

	/**
	 * Enqueue frontend checkout stylesheet only when Lean Checkout or Plan Switcher is active.
	 *
	 * @return void
	 */
	public static function enqueue_checkout_assets() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
			return;
		}

		if ( ! self::should_apply_lean_checkout() && ! YukComFlow_Settings::is_plan_switcher_enabled() ) {
			return;
		}

		wp_enqueue_style(
			'yukcomflow-checkout',
			YUKCOMFLOW_PLUGIN_URL . 'assets/css/yukcomflow-checkout.css',
			array(),
			YUKCOMFLOW_VERSION
		);
	}

	/**
	 * Add scoped body classes on checkout for Block Checkout field visibility rules.
	 *
	 * @param string[] $classes Existing body classes.
	 * @return string[]
	 */
	public static function add_body_classes( $classes ) {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
			return $classes;
		}

		if ( self::should_apply_lean_checkout() ) {
			$fields    = YukComFlow_Settings::get_lean_checkout_fields();
			$classes[] = 'yukcomflow-lean-checkout';

			if ( $fields['address'] ) {
				$classes[] = 'yukcomflow-hide-address';
			}
			if ( $fields['company'] ) {
				$classes[] = 'yukcomflow-hide-company';
			}
			if ( $fields['phone'] ) {
				$classes[] = 'yukcomflow-hide-phone';
			}
			if ( $fields['notes'] ) {
				$classes[] = 'yukcomflow-hide-notes';
			}
		}

		return $classes;
	}

	/**
	 * Simplify Classic Checkout fields for virtual carts while keeping billing country intact.
	 *
	 * @param array $fields WooCommerce checkout fields.
	 * @return array
	 */
	public static function filter_classic_checkout_fields( $fields ) {
		if ( ! self::should_apply_lean_checkout() ) {
			return $fields;
		}

		$hidden = YukComFlow_Settings::get_lean_checkout_fields();

		if ( $hidden['address'] ) {
			unset(
				$fields['billing']['billing_address_1'],
				$fields['billing']['billing_address_2'],
				$fields['billing']['billing_city'],
				$fields['billing']['billing_state'],
				$fields['billing']['billing_postcode']
			);
		}

		if ( $hidden['company'] ) {
			unset( $fields['billing']['billing_company'] );
		}

		if ( $hidden['phone'] ) {
			unset( $fields['billing']['billing_phone'] );
		}

		if ( $hidden['notes'] ) {
			unset( $fields['order']['order_comments'] );
		}

		return $fields;
	}

	/**
	 * Mark hidden address fields as non-required so WooCommerce Block Checkout / Store API validates cleanly.
	 *
	 * @param array $address_fields Default address fields.
	 * @return array
	 */
	public static function filter_default_address_fields( $address_fields ) {
		if ( ! self::should_apply_lean_checkout() ) {
			return $address_fields;
		}

		$hidden = YukComFlow_Settings::get_lean_checkout_fields();

		if ( $hidden['address'] ) {
			foreach ( array( 'address_1', 'address_2', 'city', 'state', 'postcode' ) as $key ) {
				if ( isset( $address_fields[ $key ] ) ) {
					$address_fields[ $key ]['required'] = false;
				}
			}
		}

		if ( $hidden['company'] && isset( $address_fields['company'] ) ) {
			$address_fields['company']['required'] = false;
		}

		return $address_fields;
	}

	/**
	 * Hide order notes field when configured in Lean Digital Checkout Mode.
	 *
	 * @param bool $enabled Current order notes visibility.
	 * @return bool
	 */
	public static function filter_order_notes_field( $enabled ) {
		if ( ! self::should_apply_lean_checkout() ) {
			return $enabled;
		}

		$hidden = YukComFlow_Settings::get_lean_checkout_fields();
		return $hidden['notes'] ? false : $enabled;
	}

	/**
	 * Render the In-Checkout Plan Switcher above Classic Checkout form.
	 *
	 * @return void
	 */
	public static function render_classic_plan_switcher() {
		if ( ! YukComFlow_Settings::is_plan_switcher_enabled() || self::$switcher_rendered ) {
			return;
		}

		$html = self::get_plan_switcher_html();
		if ( '' !== $html ) {
			self::$switcher_rendered = true;
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside get_plan_switcher_html().
		}
	}

	/**
	 * Prepend the In-Checkout Plan Switcher above the WooCommerce Checkout Block.
	 *
	 * @param string $block_content Rendered block HTML.
	 * @param array  $block         Parsed block data.
	 * @return string
	 */
	public static function prepend_block_plan_switcher( $block_content, $block ) {
		if ( is_admin() || ! YukComFlow_Settings::is_plan_switcher_enabled() || self::$switcher_rendered ) {
			return $block_content;
		}

		if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
			return $block_content;
		}

		$html = self::get_plan_switcher_html();
		if ( '' !== $html ) {
			self::$switcher_rendered = true;
			return $html . $block_content;
		}

		return $block_content;
	}

	/**
	 * Render the [yukcomflow_plan_switcher] shortcode.
	 *
	 * @return string
	 */
	public static function render_plan_switcher_shortcode() {
		wp_enqueue_style(
			'yukcomflow-checkout',
			YUKCOMFLOW_PLUGIN_URL . 'assets/css/yukcomflow-checkout.css',
			array(),
			YUKCOMFLOW_VERSION
		);

		$html = self::get_plan_switcher_html();
		if ( '' !== $html ) {
			self::$switcher_rendered = true;
		}

		return $html;
	}

	/**
	 * Build the escaped HTML for the compact In-Checkout Plan Switcher.
	 *
	 * Only displays when the active cart contains a variable product with at least
	 * two published, purchasable variations.
	 *
	 * @return string
	 */
	public static function get_plan_switcher_html() {
		if ( ! function_exists( 'WC' ) ) {
			return '';
		}

		$wc = WC();
		if ( ! is_object( $wc ) || ! isset( $wc->cart ) || $wc->cart->is_empty() ) {
			return '';
		}

		$target_parent_id    = 0;
		$active_variation_id = 0;
		$active_quantity     = 1;

		foreach ( $wc->cart->get_cart() as $cart_item ) {
			$var_id = isset( $cart_item['variation_id'] ) ? absint( $cart_item['variation_id'] ) : 0;
			$prd_id = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;

			if ( $var_id > 0 && $prd_id > 0 ) {
				$target_parent_id    = $prd_id;
				$active_variation_id = $var_id;
				$active_quantity     = isset( $cart_item['quantity'] ) ? max( 1, absint( $cart_item['quantity'] ) ) : 1;
				break;
			}
		}

		if ( 0 === $target_parent_id ) {
			return '';
		}

		$parent_product = wc_get_product( $target_parent_id );
		if ( ! $parent_product instanceof WC_Product_Variable ) {
			return '';
		}

		$product_slug = (string) get_post_field( 'post_name', $target_parent_id );
		if ( '' === $product_slug ) {
			return '';
		}

		// Preserve active coupon when switching plans.
		$applied_coupons = $wc->cart->get_applied_coupons();
		$active_coupon   = ! empty( $applied_coupons ) ? (string) reset( $applied_coupons ) : '';

		$options = array();
		foreach ( $parent_product->get_children() as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation instanceof WC_Product_Variation || 'publish' !== $variation->get_status() ) {
				continue;
			}
			if ( ! $variation->is_purchasable() || ! $variation->is_in_stock() ) {
				continue;
			}

			$attrs     = array_values( array_filter( array_map( 'strval', $variation->get_variation_attributes() ) ) );
			$plan_val  = isset( $attrs[0] ) ? $attrs[0] : '';
			$cycle_val = isset( $attrs[1] ) ? $attrs[1] : '';

			if ( '' === $plan_val ) {
				continue;
			}

			$label = implode( ' · ', array_map( 'ucwords', $attrs ) );
			$url   = YukComFlow_ProductLinks::build_checkout_link(
				$product_slug,
				array(
					'plan'   => $plan_val,
					'cycle'  => $cycle_val,
					'coupon' => $active_coupon,
					'qty'    => $active_quantity,
				)
			);

			$options[] = array(
				'id'        => $variation->get_id(),
				'label'     => $label,
				'price'     => $variation->get_price_html(),
				'url'       => $url,
				'is_active' => $variation->get_id() === $active_variation_id,
			);
		}

		if ( count( $options ) < 2 ) {
			return '';
		}

		ob_start();
		?>
		<div class="yukcomflow-plan-switcher" role="region" aria-label="<?php esc_attr_e( 'Switch Plan', 'yukdigitalz-commerce-flow' ); ?>">
			<div class="yukcomflow-plan-switcher__header">
				<span class="yukcomflow-plan-switcher__title">
					<?php
					printf(
						/* translators: %s is the product name. */
						esc_html__( 'Selected Plan for %s', 'yukdigitalz-commerce-flow' ),
						esc_html( $parent_product->get_name() )
					);
					?>
				</span>
				<span class="yukcomflow-plan-switcher__hint">
					<?php esc_html_e( 'Switch plan anytime before completing payment', 'yukdigitalz-commerce-flow' ); ?>
				</span>
			</div>
			<div class="yukcomflow-plan-switcher__options">
				<?php foreach ( $options as $opt ) : ?>
					<?php if ( $opt['is_active'] ) : ?>
						<span class="yukcomflow-plan-option is-active" aria-current="true">
							<span class="yukcomflow-plan-option__label"><?php echo esc_html( $opt['label'] ); ?></span>
							<span class="yukcomflow-plan-option__price"><?php echo wp_kses_post( $opt['price'] ); ?></span>
						</span>
					<?php else : ?>
						<a href="<?php echo esc_url( $opt['url'] ); ?>" class="yukcomflow-plan-option">
							<span class="yukcomflow-plan-option__label"><?php echo esc_html( $opt['label'] ); ?></span>
							<span class="yukcomflow-plan-option__price"><?php echo wp_kses_post( $opt['price'] ); ?></span>
						</a>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
