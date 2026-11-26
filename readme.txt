=== Yukdigitalz Commerce Flow ===
Contributors: shihela
Tags: woocommerce, checkout, redirect, plan, commerce
Requires at least: 6.0
Tested up to: 7.1
Stable tag: 1.1.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Direct checkout flow with plan-based routing for WooCommerce.

== Description ==

Yukdigitalz Commerce Flow is a lightweight SaaS-style direct checkout plugin for WooCommerce. It lets you route visitors straight to checkout using clean, plan-based URLs such as `/buy/product-slug/?plan=agency&cycle=yearly&coupon=LAUNCH20&qty=1`, automatically selecting the right product or variation and applying valid coupons.

This plugin also includes a lightweight abandoned-cart tracker (Shadow Cart) compatible with both Classic Checkout and WooCommerce Block Checkout, complete with expiring recovery links, native WooCommerce settings, a Product Links metabox, a `[yukcomflow_buy_button]` shortcode, and built-in GDPR privacy tools.

== Features ==

* Multi-dimensional direct checkout URL routing (`plan`, `cycle` / `billing`, `coupon`, `qty`) on root and subdirectory installs
* Native WooCommerce Settings tab (`WooCommerce -> Settings -> Commerce Flow`) for custom base slug, cart replace/append mode, fallback redirect, and retention rules
* One-click Direct Checkout Links metabox on the WooCommerce product edit screen and `[yukcomflow_buy_button]` shortcode
* Optional Lean Digital Checkout Mode (hides unnecessary physical address fields for virtual carts while keeping billing country for tax & gateway compliance)
* Optional In-Checkout Plan Switcher (Classic Checkout, Block Checkout, and `[yukcomflow_plan_switcher]` shortcode) preserving active coupons when switching plans
* Full compatibility with WooCommerce HPOS and Cart/Checkout Blocks (Store API)
* Lightweight abandoned-cart email capture with rate limiting, expiring recovery tokens, summary KPI metrics (recovery rate & recovered revenue), status filtering, and cart value display
* Built-in UTM attribution tracking persisted across direct checkout, abandoned carts, and completed orders
* Structured WooCommerce logging (`WC_Logger`) with toggleable debug mode under `WooCommerce -> Status -> Logs`
* Built-in WordPress Privacy Policy content, personal data exporter, eraser, and automated retention cleanup
* Idempotent order-completion hooks for license integration

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/yukdigitalz-commerce-flow` directory, or install it through the WordPress Plugins screen.
2. Activate the plugin from the Plugins menu.
3. Make sure WooCommerce is installed and activated before using the checkout flow.
4. Configure optional settings under `WooCommerce -> Settings -> Commerce Flow`, or copy ready-made direct checkout URLs and shortcodes from any product's **Commerce Flow Direct Links** metabox.

== Frequently Asked Questions ==

= How does the plugin work? =

The plugin listens for URLs beginning with your configured base slug (default `/buy/`), resolves the matching published product or multi-attribute variation (`plan` and optional `cycle`), optionally applies a valid `coupon` and `qty`, and redirects the visitor straight to WooCommerce checkout.

= How do I use the Buy Button shortcode? =

Use `[yukcomflow_buy_button product="product-slug" plan="agency" cycle="yearly" coupon="LAUNCH20" label="Get Agency Plan"]` anywhere in posts, pages, or page builders.

= Does this plugin work with WooCommerce Block Checkout and HPOS? =

Yes. Both High-Performance Order Storage (HPOS) and WooCommerce Cart/Checkout Blocks are supported and explicitly declared.

= How long are abandoned cart records stored? =

By default, abandoned cart records are automatically deleted after 30 days (configurable in `WooCommerce -> Settings -> Commerce Flow`), and users can export or erase their data through WordPress Privacy Tools.

== Changelog ==

= 1.1.0 =
* Added native WooCommerce Settings tab (`WooCommerce -> Settings -> Commerce Flow`)
* Added multi-dimensional routing (`plan`, `cycle`/`billing`, `coupon`, `qty`) with attribute-agnostic variation matching
* Added Product Links metabox on product edit screens and `[yukcomflow_buy_button]` shortcode
* Added Shadow Cart summary KPI metrics, status filtering, cart value column, and end-to-end UTM attribution tracking
* Added optional Lean Digital Checkout Mode (virtual carts) and In-Checkout Plan Switcher (`[yukcomflow_plan_switcher]`)
* Added structured WooCommerce logging (`WC_Logger`) with toggleable debug mode
* Added WooCommerce Block Checkout (Store API) and HPOS compatibility
* Hardened abandoned-cart capture with empty-cart guard, rate limiting, expiring recovery tokens, and GDPR privacy tools
* Added idempotency guard for order completion license hooks and admin order plan/cycle/UTM display

= 1.0.0 =
* Initial WordPress.org compatible release with direct checkout routing and lightweight abandoned-cart tracking

== Upgrade Notice ==

= 1.1.0 =
Major enterprise upgrade adding WooCommerce Block Checkout support, native WooCommerce Settings, multi-dimensional plan/cycle/coupon routing, Shadow Cart analytics & GDPR tools, and optional SaaS checkout UX enhancements.
