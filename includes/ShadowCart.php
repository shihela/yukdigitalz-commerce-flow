<?php
/**
 * Abandoned cart tracker, recovery tokens, analytics summary, and custom post type.
 *
 * @package Yukdigitalz_Commerce_Flow
 */

defined( 'ABSPATH' ) || exit;

class YukComFlow_ShadowCart {

	/**
	 * Cron hook name for automated retention cleanup.
	 *
	 * @var string
	 */
	const CLEANUP_HOOK = 'yukcomflow_cleanup_abandoned_carts';

	/**
	 * Transient key for cached summary metrics.
	 *
	 * @var string
	 */
	const METRICS_TRANSIENT = 'yukcomflow_shadow_cart_metrics';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( self::class, 'register_shadow_cart_cpt' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue_tracker_assets' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_yukcomflow_capture_email', array( self::class, 'ajax_capture_email' ) );
		add_action( 'wp_ajax_nopriv_yukcomflow_capture_email', array( self::class, 'ajax_capture_email' ) );

		// Reliable recovery hooks across Classic Checkout, Block Checkout (Store API), and webhook payment gateways.
		add_action( 'woocommerce_checkout_order_processed', array( self::class, 'mark_cart_as_recovered' ) );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( self::class, 'mark_cart_as_recovered' ) );
		add_action( 'woocommerce_payment_complete', array( self::class, 'mark_cart_as_recovered' ) );
		add_action( 'woocommerce_order_status_processing', array( self::class, 'mark_cart_as_recovered' ) );
		add_action( 'woocommerce_order_status_completed', array( self::class, 'mark_cart_as_recovered' ) );
		add_action( 'woocommerce_thankyou', array( self::class, 'mark_cart_as_recovered' ) );

		// Admin list table columns, status filter, and summary KPI bar.
		add_filter( 'manage_yukcomflow_shadow_cart_posts_columns', array( self::class, 'set_custom_columns' ) );
		add_action( 'manage_yukcomflow_shadow_cart_posts_custom_column', array( self::class, 'custom_column_data' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( self::class, 'render_status_filter' ) );
		add_action( 'pre_get_posts', array( self::class, 'apply_status_filter' ) );
		add_action( 'admin_notices', array( self::class, 'render_metrics_summary_bar' ) );

		// Invalidate cached metrics when shadow cart records are deleted or trashed.
		add_action( 'deleted_post', array( self::class, 'maybe_invalidate_metrics_on_post_change' ) );
		add_action( 'trashed_post', array( self::class, 'maybe_invalidate_metrics_on_post_change' ) );
		add_action( 'untrashed_post', array( self::class, 'maybe_invalidate_metrics_on_post_change' ) );

		// Automated retention cleanup.
		add_action( self::CLEANUP_HOOK, array( self::class, 'cleanup_expired_records' ) );
		self::schedule_cleanup();
	}

	/**
	 * Schedule daily retention cleanup cron event if not already scheduled.
	 *
	 * @return void
	 */
	public static function schedule_cleanup() {
		if ( ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK );
		}
	}

	/**
	 * Unschedule retention cleanup cron event.
	 *
	 * @return void
	 */
	public static function unschedule_cleanup() {
		$timestamp = wp_next_scheduled( self::CLEANUP_HOOK );
		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CLEANUP_HOOK );
			$timestamp = wp_next_scheduled( self::CLEANUP_HOOK );
		}
	}

	/**
	 * Invalidate the cached summary metrics transient.
	 *
	 * @return void
	 */
	public static function invalidate_metrics_cache() {
		delete_transient( self::METRICS_TRANSIENT );
	}

	/**
	 * Invalidate metrics cache when a shadow cart post is deleted or trashed.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function maybe_invalidate_metrics_on_post_change( $post_id ) {
		if ( 'yukcomflow_shadow_cart' === get_post_type( absint( $post_id ) ) ) {
			self::invalidate_metrics_cache();
		}
	}

	/**
	 * Register the internal Abandoned Cart custom post type restricted to WooCommerce managers.
	 *
	 * @return void
	 */
	public static function register_shadow_cart_cpt() {
		register_post_type(
			'yukcomflow_shadow_cart',
			array(
				'labels'       => array(
					'name'          => __( 'Abandoned Carts', 'yukdigitalz-commerce-flow' ),
					'singular_name' => __( 'Abandoned Cart', 'yukdigitalz-commerce-flow' ),
					'menu_name'     => __( 'Abandoned Carts', 'yukdigitalz-commerce-flow' ),
					'all_items'     => __( 'All Abandoned Records', 'yukdigitalz-commerce-flow' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'woocommerce',
				'supports'     => array( 'title' ),
				'menu_icon'    => 'dashicons-cart',
				'capabilities' => array(
					'edit_post'           => 'manage_woocommerce',
					'read_post'           => 'manage_woocommerce',
					'delete_post'         => 'manage_woocommerce',
					'edit_posts'          => 'manage_woocommerce',
					'edit_others_posts'   => 'manage_woocommerce',
					'publish_posts'       => 'manage_woocommerce',
					'read_private_posts'  => 'manage_woocommerce',
					'delete_posts'        => 'manage_woocommerce',
					'delete_others_posts' => 'manage_woocommerce',
					'create_posts'        => 'do_not_allow',
				),
				'map_meta_cap' => false,
			)
		);
	}

	/**
	 * Enqueue frontend checkout email tracker script (supports Classic and Block Checkout).
	 *
	 * @return void
	 */
	public static function enqueue_tracker_assets() {
		if ( ! YukComFlow_Settings::is_shadow_cart_enabled() ) {
			return;
		}

		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
			return;
		}

		wp_enqueue_script(
			'yukcomflow-shadow-tracker',
			YUKCOMFLOW_PLUGIN_URL . 'assets/js/yukcomflow-shadow-tracker.js',
			array( 'jquery' ),
			YUKCOMFLOW_VERSION,
			true
		);

		wp_localize_script(
			'yukcomflow-shadow-tracker',
			'yukComFlowShadowCart',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'yukcomflow-shadow-nonce' ),
			)
		);
	}

	/**
	 * Enqueue admin stylesheet and script only on the Abandoned Carts list screen.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public static function enqueue_admin_assets( $hook_suffix ) {
		if ( 'edit.php' !== $hook_suffix ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'yukcomflow_shadow_cart' !== $screen->post_type ) {
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
	 * Handle AJAX email capture on checkout with cart validation, value tracking, UTM capture, and rate limiting.
	 *
	 * @return void
	 */
	public static function ajax_capture_email() {
		check_ajax_referer( 'yukcomflow-shadow-nonce', 'security' );

		if ( ! YukComFlow_Settings::is_shadow_cart_enabled() ) {
			wp_send_json_error( array( 'message' => __( 'Tracking is disabled.', 'yukdigitalz-commerce-flow' ) ) );
		}

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid email address.', 'yukdigitalz-commerce-flow' ) ), 400 );
		}

		if ( self::is_rate_limited() ) {
			YukComFlow_Logger::warning( 'Shadow cart email capture rate limit reached.' );
			wp_send_json_error( array( 'message' => __( 'Too many requests. Please try again later.', 'yukdigitalz-commerce-flow' ) ), 429 );
		}

		if ( ! function_exists( 'WC' ) ) {
			wp_send_json_error();
		}

		if ( function_exists( 'wc_load_cart' ) && is_null( WC()->cart ) ) {
			wc_load_cart();
		}

		$wc = WC();
		if ( ! is_object( $wc ) || ! isset( $wc->cart ) || $wc->cart->is_empty() ) {
			wp_send_json_error( array( 'message' => __( 'Cart is empty.', 'yukdigitalz-commerce-flow' ) ) );
		}

		// Check whether this email already has an active record within the last 12 hours.
		$existing = get_posts(
			array(
				'post_type'      => 'yukcomflow_shadow_cart',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Bounded deduplication lookup for active checkout capture.
				'meta_query'     => array(
					array(
						'key'   => '_yukcomflow_abandoned_email',
						'value' => $email,
					),
				),
				'date_query'     => array(
					array( 'after' => '12 hours ago' ),
				),
			)
		);

		if ( ! empty( $existing ) ) {
			wp_send_json_success( array( 'message' => __( 'Already captured', 'yukdigitalz-commerce-flow' ) ) );
		}

		$cart_items   = array();
		$cart_details = array();
		$raw_cart     = $wc->cart->get_cart();
		$plan         = '';
		$utm          = array();

		foreach ( $raw_cart as $cart_item ) {
			if ( empty( $cart_item['data'] ) || ! is_object( $cart_item['data'] ) ) {
				continue;
			}

			$product      = $cart_item['data'];
			$quantity     = isset( $cart_item['quantity'] ) ? max( 1, absint( $cart_item['quantity'] ) ) : 1;
			$cart_items[] = $product->get_name() . ' (x' . $quantity . ')';

			$cart_details[] = array(
				'product_id'   => isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0,
				'variation_id' => isset( $cart_item['variation_id'] ) ? absint( $cart_item['variation_id'] ) : 0,
				'quantity'     => $quantity,
				'variation'    => isset( $cart_item['variation'] ) && is_array( $cart_item['variation'] ) ? $cart_item['variation'] : array(),
			);
		}

		if ( empty( $cart_details ) ) {
			wp_send_json_error( array( 'message' => __( 'Cart is empty.', 'yukdigitalz-commerce-flow' ) ) );
		}

		if ( isset( $wc->session ) ) {
			$plan        = (string) $wc->session->get( 'yukcomflow_active_plan' );
			$session_utm = $wc->session->get( 'yukcomflow_active_utm' );
			if ( is_array( $session_utm ) ) {
				$utm = array_map( 'sanitize_text_field', $session_utm );
			}
		}

		$cart_total    = (float) $wc->cart->get_total( 'edit' );
		$cart_currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '';

		$products_string = implode( ', ', $cart_items );
		$post_id         = wp_insert_post(
			array(
				'post_title'   => $email,
				'post_type'    => 'yukcomflow_shadow_cart',
				'post_status'  => 'publish',
				'post_content' => '',
			),
			true
		);

		if ( ! is_wp_error( $post_id ) && $post_id > 0 ) {
			self::record_rate_limit_hit();

			update_post_meta( $post_id, '_yukcomflow_abandoned_products', $products_string );
			update_post_meta( $post_id, '_yukcomflow_abandoned_status', 'pending' );
			update_post_meta( $post_id, '_yukcomflow_abandoned_email', $email );
			update_post_meta( $post_id, '_yukcomflow_cart_details', $cart_details );
			update_post_meta( $post_id, '_yukcomflow_abandoned_plan', sanitize_text_field( $plan ) );
			update_post_meta( $post_id, '_yukcomflow_cart_total', $cart_total );
			update_post_meta( $post_id, '_yukcomflow_cart_currency', sanitize_text_field( $cart_currency ) );

			if ( ! empty( $utm ) ) {
				update_post_meta( $post_id, '_yukcomflow_utm', $utm );
			}

			self::generate_recovery_token( $post_id );
			self::invalidate_metrics_cache();

			/**
			 * Filter affiliate ID detected in the cart. Extensions can provide
			 * affiliate identification by hooking this filter.
			 *
			 * @param int   $affiliate_id Default affiliate ID (0).
			 * @param array $cart_items   Human-readable cart item strings.
			 * @param array $raw_cart     Raw WooCommerce cart contents.
			 */
			$affiliate_id = absint( apply_filters( 'yukcomflow_detect_affiliate_id', 0, $cart_items, $raw_cart ) );

			/**
			 * Action for extensions to persist affiliate data as needed.
			 *
			 * @param int $post_id      Shadow cart post ID.
			 * @param int $affiliate_id Detected affiliate ID.
			 */
			do_action( 'yukcomflow_shadow_cart_affiliate_save', $post_id, $affiliate_id );

			/**
			 * Action after a shadow cart record is captured.
			 *
			 * @param int   $post_id Shadow cart post ID.
			 * @param array $data    Captured summary data.
			 */
			do_action(
				'yukcomflow_shadow_cart_captured',
				$post_id,
				array(
					'email'        => $email,
					'products'     => $products_string,
					'affiliate_id' => $affiliate_id,
					'cart_total'   => $cart_total,
					'currency'     => $cart_currency,
					'utm'          => $utm,
				)
			);

			YukComFlow_Logger::debug(
				'Captured shadow cart record.',
				array(
					'post_id'    => $post_id,
					'cart_total' => $cart_total,
					'plan'       => $plan,
				)
			);

			wp_send_json_success( array( 'message' => __( 'Captured', 'yukdigitalz-commerce-flow' ) ) );
		}

		wp_send_json_error();
	}

	/**
	 * Generate and store a cryptographic, expiring recovery token for a shadow cart record.
	 *
	 * @param int $post_id Shadow cart post ID.
	 * @return string Generated token.
	 */
	public static function generate_recovery_token( $post_id ) {
		$post_id = absint( $post_id );
		$token   = wp_generate_password( 32, false, false );
		$ttl     = YukComFlow_Settings::get_recovery_ttl_seconds();
		$expires = time() + $ttl;

		update_post_meta( $post_id, '_yukcomflow_recovery_token', $token );
		update_post_meta( $post_id, '_yukcomflow_recovery_expires', $expires );

		return $token;
	}

	/**
	 * Verify that a recovery token is valid, unexpired, and belongs to an active pending shadow cart.
	 *
	 * @param int    $post_id Shadow cart post ID.
	 * @param string $token   Provided recovery token.
	 * @return bool
	 */
	public static function verify_recovery_token( $post_id, $token ) {
		$post_id = absint( $post_id );
		$token   = (string) $token;

		if ( $post_id <= 0 || '' === $token ) {
			return false;
		}

		if ( 'yukcomflow_shadow_cart' !== get_post_type( $post_id ) || 'publish' !== get_post_status( $post_id ) ) {
			return false;
		}

		$status = (string) get_post_meta( $post_id, '_yukcomflow_abandoned_status', true );
		if ( 'recovered' === $status ) {
			return false;
		}

		$stored_token = (string) get_post_meta( $post_id, '_yukcomflow_recovery_token', true );
		$expires      = absint( get_post_meta( $post_id, '_yukcomflow_recovery_expires', true ) );

		if ( '' === $stored_token || $expires < time() ) {
			return false;
		}

		return hash_equals( $stored_token, $token );
	}

	/**
	 * Mark matching abandoned carts as recovered once an order is created or paid.
	 *
	 * Accepts either a WC_Order object (Store API) or an order ID (Classic / payment hooks).
	 *
	 * @param int|WC_Order $order_or_id WooCommerce order object or order ID.
	 * @return void
	 */
	public static function mark_cart_as_recovered( $order_or_id ) {
		$order = is_a( $order_or_id, 'WC_Order' ) ? $order_or_id : wc_get_order( absint( $order_or_id ) );
		if ( ! $order ) {
			return;
		}

		$order_id    = $order->get_id();
		$order_total = (float) $order->get_total();
		$currency    = (string) $order->get_currency();
		$email       = sanitize_email( $order->get_billing_email() );
		if ( ! is_email( $email ) ) {
			return;
		}

		$abandoned_cart_ids = get_posts(
			array(
				'post_type'      => 'yukcomflow_shadow_cart',
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Bounded query to mark active abandoned carts as recovered.
				'meta_query'     => array(
					array(
						'key'   => '_yukcomflow_abandoned_email',
						'value' => $email,
					),
				),
			)
		);

		if ( empty( $abandoned_cart_ids ) ) {
			return;
		}

		$first = true;
		foreach ( $abandoned_cart_ids as $cart_id ) {
			$cart_id = absint( $cart_id );
			update_post_meta( $cart_id, '_yukcomflow_abandoned_status', 'recovered' );
			update_post_meta( $cart_id, '_yukcomflow_recovered_order_id', $order_id );
			// Attribute recovered revenue once (to the latest matching record) to avoid double-counting.
			update_post_meta( $cart_id, '_yukcomflow_recovered_total', $first ? $order_total : 0.0 );
			update_post_meta( $cart_id, '_yukcomflow_cart_currency', sanitize_text_field( $currency ) );
			delete_post_meta( $cart_id, '_yukcomflow_recovery_token' );
			delete_post_meta( $cart_id, '_yukcomflow_recovery_expires' );

			wp_update_post(
				array(
					'ID'          => $cart_id,
					'post_status' => 'draft',
				)
			);

			$first = false;
		}

		self::invalidate_metrics_cache();

		YukComFlow_Logger::info(
			'Marked abandoned cart record(s) as recovered.',
			array(
				'order_id'       => $order_id,
				'recovered_rows' => count( $abandoned_cart_ids ),
				'order_total'    => $order_total,
			)
		);
	}

	/**
	 * Delete abandoned cart records older than the configured retention period in bounded batches.
	 *
	 * @return void
	 */
	public static function cleanup_expired_records() {
		$retention_days = YukComFlow_Settings::get_retention_days();
		if ( 0 === $retention_days ) {
			return;
		}

		$old_post_ids = get_posts(
			array(
				'post_type'      => 'yukcomflow_shadow_cart',
				'post_status'    => 'any',
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'date_query'     => array(
					array(
						'before' => $retention_days . ' days ago',
					),
				),
			)
		);

		if ( empty( $old_post_ids ) ) {
			return;
		}

		foreach ( $old_post_ids as $post_id ) {
			wp_delete_post( absint( $post_id ), true );
		}

		self::invalidate_metrics_cache();
	}

	/**
	 * Compute or retrieve cached Shadow Cart summary metrics.
	 *
	 * @return array{total:int,pending:int,recovered:int,recovery_rate:float,recovered_revenue:float}
	 */
	public static function get_summary_metrics() {
		$cached = get_transient( self::METRICS_TRANSIENT );
		if ( is_array( $cached ) && isset( $cached['total'] ) ) {
			return $cached;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Result is cached via transient below.
		$status_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.meta_value AS status, COUNT(DISTINCT p.ID) AS cnt
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type = %s
				AND p.post_status IN ('publish', 'draft')
				AND pm.meta_key = %s
				GROUP BY pm.meta_value",
				'yukcomflow_shadow_cart',
				'_yukcomflow_abandoned_status'
			),
			ARRAY_A
		);

		$pending   = 0;
		$recovered = 0;

		if ( is_array( $status_rows ) ) {
			foreach ( $status_rows as $row ) {
				if ( 'recovered' === $row['status'] ) {
					$recovered = absint( $row['cnt'] );
				} elseif ( 'pending' === $row['status'] ) {
					$pending = absint( $row['cnt'] );
				}
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Result is cached via transient below.
		$recovered_revenue = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(CAST(pm_total.meta_value AS DECIMAL(18,4))), 0)
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = %s AND pm_status.meta_value = %s
				INNER JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = %s
				WHERE p.post_type = %s
				AND p.post_status IN ('publish', 'draft')",
				'_yukcomflow_abandoned_status',
				'recovered',
				'_yukcomflow_recovered_total',
				'yukcomflow_shadow_cart'
			)
		);

		$total         = $pending + $recovered;
		$recovery_rate = $total > 0 ? round( ( $recovered / $total ) * 100, 1 ) : 0.0;

		$metrics = array(
			'total'             => $total,
			'pending'           => $pending,
			'recovered'         => $recovered,
			'recovery_rate'     => $recovery_rate,
			'recovered_revenue' => $recovered_revenue,
		);

		set_transient( self::METRICS_TRANSIENT, $metrics, 10 * MINUTE_IN_SECONDS );

		return $metrics;
	}

	/**
	 * Render the Shadow Cart KPI summary bar on the Abandoned Carts list screen.
	 *
	 * @return void
	 */
	public static function render_metrics_summary_bar() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-yukcomflow_shadow_cart' !== $screen->id ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$metrics           = self::get_summary_metrics();
		$formatted_revenue = function_exists( 'wc_price' )
			? wc_price( $metrics['recovered_revenue'] )
			: esc_html( number_format_i18n( $metrics['recovered_revenue'], 2 ) );

		echo '<div class="yukcomflow-metrics-bar">';

		printf(
			'<div class="yukcomflow-metric-card"><span class="yukcomflow-metric-label">%1$s</span><strong class="yukcomflow-metric-value">%2$s</strong></div>',
			esc_html__( 'Total Captured', 'yukdigitalz-commerce-flow' ),
			esc_html( number_format_i18n( $metrics['total'] ) )
		);

		printf(
			'<div class="yukcomflow-metric-card"><span class="yukcomflow-metric-label">%1$s</span><strong class="yukcomflow-metric-value yukcomflow-metric-value--pending">%2$s</strong></div>',
			esc_html__( 'Pending', 'yukdigitalz-commerce-flow' ),
			esc_html( number_format_i18n( $metrics['pending'] ) )
		);

		printf(
			'<div class="yukcomflow-metric-card"><span class="yukcomflow-metric-label">%1$s</span><strong class="yukcomflow-metric-value yukcomflow-metric-value--recovered">%2$s <small>(%3$s%%)</small></strong></div>',
			esc_html__( 'Recovered', 'yukdigitalz-commerce-flow' ),
			esc_html( number_format_i18n( $metrics['recovered'] ) ),
			esc_html( number_format_i18n( $metrics['recovery_rate'], 1 ) )
		);

		printf(
			'<div class="yukcomflow-metric-card"><span class="yukcomflow-metric-label">%1$s</span><strong class="yukcomflow-metric-value yukcomflow-metric-value--revenue">%2$s</strong></div>',
			esc_html__( 'Recovered Revenue', 'yukdigitalz-commerce-flow' ),
			wp_kses_post( $formatted_revenue )
		);

		echo '</div>';
	}

	/**
	 * Render status filter dropdown above the Abandoned Carts list table.
	 *
	 * @param string $post_type Current post type.
	 * @return void
	 */
	public static function render_status_filter( $post_type ) {
		if ( 'yukcomflow_shadow_cart' !== $post_type ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin list table filter.
		$current = isset( $_GET['yukcomflow_status'] ) ? sanitize_key( wp_unslash( $_GET['yukcomflow_status'] ) ) : '';

		echo '<select name="yukcomflow_status">';
		printf(
			'<option value="">%s</option>',
			esc_html__( 'All Statuses', 'yukdigitalz-commerce-flow' )
		);
		printf(
			'<option value="pending"%1$s>%2$s</option>',
			selected( $current, 'pending', false ),
			esc_html__( 'Pending', 'yukdigitalz-commerce-flow' )
		);
		printf(
			'<option value="recovered"%1$s>%2$s</option>',
			selected( $current, 'recovered', false ),
			esc_html__( 'Recovered', 'yukdigitalz-commerce-flow' )
		);
		echo '</select>';
	}

	/**
	 * Apply status filter to the Abandoned Carts admin list query.
	 *
	 * @param WP_Query $query Main admin query.
	 * @return void
	 */
	public static function apply_status_filter( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( 'yukcomflow_shadow_cart' !== $query->get( 'post_type' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin list table filter.
		$status = isset( $_GET['yukcomflow_status'] ) ? sanitize_key( wp_unslash( $_GET['yukcomflow_status'] ) ) : '';
		if ( ! in_array( $status, array( 'pending', 'recovered' ), true ) ) {
			return;
		}

		$meta_query   = (array) $query->get( 'meta_query' );
		$meta_query[] = array(
			'key'   => '_yukcomflow_abandoned_status',
			'value' => $status,
		);

		$query->set( 'meta_query', $meta_query );
	}

	/**
	 * Customize admin list table columns.
	 *
	 * @param array $columns Default columns.
	 * @return array
	 */
	public static function set_custom_columns( $columns ) {
		$new_columns                       = array();
		$new_columns['cb']                 = $columns['cb'];
		$new_columns['title']              = __( 'Prospect Email', 'yukdigitalz-commerce-flow' );
		$new_columns['abandoned_products'] = __( 'Abandoned Products', 'yukdigitalz-commerce-flow' );
		$new_columns['cart_value']         = __( 'Cart Value', 'yukdigitalz-commerce-flow' );
		$new_columns['utm_source']         = __( 'Source (UTM)', 'yukdigitalz-commerce-flow' );
		$new_columns['affiliate_id']       = __( 'Affiliate ID', 'yukdigitalz-commerce-flow' );
		$new_columns['status']             = __( 'Status', 'yukdigitalz-commerce-flow' );
		$new_columns['actions']            = __( 'Actions', 'yukdigitalz-commerce-flow' );
		$new_columns['date']               = $columns['date'];

		return $new_columns;
	}

	/**
	 * Render custom column content on the Abandoned Carts admin list table.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Shadow cart post ID.
	 * @return void
	 */
	public static function custom_column_data( $column, $post_id ) {
		switch ( $column ) {
			case 'abandoned_products':
				$products = get_post_meta( $post_id, '_yukcomflow_abandoned_products', true );
				echo ! empty( $products )
					? esc_html( $products )
					: '<em>' . esc_html__( 'Empty Cart', 'yukdigitalz-commerce-flow' ) . '</em>';
				break;

			case 'cart_value':
				$total    = get_post_meta( $post_id, '_yukcomflow_cart_total', true );
				$currency = (string) get_post_meta( $post_id, '_yukcomflow_cart_currency', true );
				if ( '' !== $total && is_numeric( $total ) ) {
					if ( function_exists( 'wc_price' ) ) {
						echo wp_kses_post( wc_price( (float) $total, $currency ? array( 'currency' => $currency ) : array() ) );
					} else {
						echo esc_html( number_format_i18n( (float) $total, 2 ) );
					}
				} else {
					echo '<span class="yukcomflow-badge yukcomflow-badge--organic">&mdash;</span>';
				}
				break;

			case 'utm_source':
				$utm = get_post_meta( $post_id, '_yukcomflow_utm', true );
				if ( is_array( $utm ) && ! empty( $utm ) ) {
					$source   = isset( $utm['utm_source'] ) ? (string) $utm['utm_source'] : '';
					$campaign = isset( $utm['utm_campaign'] ) ? (string) $utm['utm_campaign'] : '';
					$label    = '' !== $source ? $source : (string) reset( $utm );
					if ( '' !== $campaign ) {
						$label .= ' / ' . $campaign;
					}
					echo '<span class="yukcomflow-badge yukcomflow-badge--utm">' . esc_html( $label ) . '</span>';
				} else {
					echo '<span class="yukcomflow-badge yukcomflow-badge--organic">' . esc_html__( 'Direct', 'yukdigitalz-commerce-flow' ) . '</span>';
				}
				break;

			case 'affiliate_id':
				$aff_id = get_post_meta( $post_id, '_yukcomflow_affiliate_id', true );
				if ( '' === $aff_id ) {
					// Backwards-compatible fallback for older meta key naming.
					$aff_id = get_post_meta( $post_id, '_' . YUKCOMFLOW_PLUGIN_SLUG . '_affiliate_id', true );
				}

				if ( $aff_id ) {
					$user_info = get_userdata( absint( $aff_id ) );
					/* translators: %d is the affiliate user ID. */
					$name = $user_info ? $user_info->display_name : sprintf( __( 'ID: %d', 'yukdigitalz-commerce-flow' ), absint( $aff_id ) );
					echo '<span class="yukcomflow-badge yukcomflow-badge--affiliate">' . esc_html( $name ) . '</span>';
				} else {
					echo '<span class="yukcomflow-badge yukcomflow-badge--organic">' . esc_html__( '- Organic -', 'yukdigitalz-commerce-flow' ) . '</span>';
				}
				break;

			case 'status':
				$status = get_post_meta( $post_id, '_yukcomflow_abandoned_status', true );
				if ( 'recovered' === $status ) {
					echo '<span class="yukcomflow-badge yukcomflow-badge--recovered">' . esc_html__( 'Recovered', 'yukdigitalz-commerce-flow' ) . '</span>';
				} else {
					echo '<span class="yukcomflow-badge yukcomflow-badge--pending">' . esc_html__( 'Pending', 'yukdigitalz-commerce-flow' ) . '</span>';
				}
				break;

			case 'actions':
				$status = get_post_meta( $post_id, '_yukcomflow_abandoned_status', true );
				if ( 'recovered' === $status ) {
					$order_id = absint( get_post_meta( $post_id, '_yukcomflow_recovered_order_id', true ) );
					if ( $order_id > 0 && function_exists( 'wc_get_order' ) ) {
						$order = wc_get_order( $order_id );
						if ( $order && method_exists( $order, 'get_edit_order_url' ) ) {
							printf(
								'<a href="%1$s" class="button">%2$s</a>',
								esc_url( $order->get_edit_order_url() ),
								/* translators: %d is the WooCommerce order ID. */
								esc_html( sprintf( __( 'Order #%d', 'yukdigitalz-commerce-flow' ), $order_id ) )
							);
							break;
						}
					}
					echo '<span class="yukcomflow-badge yukcomflow-badge--organic">' . esc_html__( 'Completed', 'yukdigitalz-commerce-flow' ) . '</span>';
					break;
				}

				$email = get_post_meta( $post_id, '_yukcomflow_abandoned_email', true );
				if ( empty( $email ) ) {
					$post  = get_post( $post_id );
					$email = $post ? $post->post_title : '';
				}
				$email = sanitize_email( $email );

				if ( is_email( $email ) ) {
					$token   = (string) get_post_meta( $post_id, '_yukcomflow_recovery_token', true );
					$expires = absint( get_post_meta( $post_id, '_yukcomflow_recovery_expires', true ) );

					if ( '' === $token || $expires < time() ) {
						$token = self::generate_recovery_token( $post_id );
					}

					$recovery_url = add_query_arg(
						array(
							'yukcomflow_recover' => absint( $post_id ),
							'key'                => $token,
						),
						home_url( '/' )
					);

					$subject = rawurlencode( __( 'Complete your order', 'yukdigitalz-commerce-flow' ) );
					$body    = rawurlencode(
						sprintf(
							/* translators: %s is the recovery checkout URL. */
							__( "Hi,\n\nWe noticed you left items in your cart. You can complete your purchase here:\n%s\n\nBest regards,", 'yukdigitalz-commerce-flow' ),
							$recovery_url
						)
					);

					$mailto_url = 'mailto:' . rawurlencode( $email ) . '?subject=' . $subject . '&body=' . $body;

					echo '<div class="yukcomflow-action-buttons">';
					printf(
						'<a href="%1$s" class="button button-primary">%2$s</a>',
						esc_url( $mailto_url, array( 'mailto' ) ),
						esc_html__( 'Send Email', 'yukdigitalz-commerce-flow' )
					);
					printf(
						'<button type="button" class="button yukcomflow-copy-btn" data-url="%1$s">%2$s</button>',
						esc_url( $recovery_url ),
						esc_html__( 'Copy Link', 'yukdigitalz-commerce-flow' )
					);
					echo '</div>';
				}
				break;
		}
	}

	/**
	 * Build a privacy-safe rate limit transient key for the current visitor.
	 *
	 * @return string
	 */
	private static function get_rate_limit_key() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		return 'yukcomflow_rl_' . md5( $ip . '|' . wp_salt( 'nonce' ) );
	}

	/**
	 * Check whether the current visitor has exceeded the capture rate limit.
	 *
	 * @return bool
	 */
	private static function is_rate_limited() {
		$max_requests = absint( apply_filters( 'yukcomflow_capture_rate_limit', 5 ) );
		if ( 0 === $max_requests ) {
			return false;
		}

		$count = absint( get_transient( self::get_rate_limit_key() ) );
		return $count >= $max_requests;
	}

	/**
	 * Increment the visitor's rate limit counter.
	 *
	 * @return void
	 */
	private static function record_rate_limit_hit() {
		$key    = self::get_rate_limit_key();
		$window = absint( apply_filters( 'yukcomflow_capture_rate_window', 10 * MINUTE_IN_SECONDS ) );
		$count  = absint( get_transient( $key ) );

		set_transient( $key, $count + 1, max( MINUTE_IN_SECONDS, $window ) );
	}
}
