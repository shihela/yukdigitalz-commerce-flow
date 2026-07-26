=== Yukdigitalz Commerce Flow ===
Contributors: shihela
Tags: woocommerce, checkout, redirect, plan, commerce
Requires at least: 6.0
Tested up to: 7.0
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Direct checkout flow with plan-based routing for WooCommerce.

== Description ==

Yukdigitalz Commerce Flow is a WooCommerce helper plugin designed to simplify and customize the purchase journey. It lets you redirect visitors through a plan-based checkout flow using a friendly URL structure such as `/buy/product-slug/?plan=agency` and then continue to WooCommerce checkout with the selected product or variation.

This plugin also includes a lightweight abandoned-cart tracking feature that captures a prospect email during checkout and stores it safely in a dedicated admin view under WooCommerce.

== Features ==

* Custom URL routing for product and plan-based checkout flow
* Automatic redirect to WooCommerce checkout with selected product/variation
* Support for plan-based product matching for variable products
* Safe storage of prospect emails for abandoned-cart follow-up
* WordPress-friendly structure for plugin repository readiness

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/yukdigitalz-commerce-flow` directory, or install it through the WordPress Plugins screen.
2. Activate the plugin from the Plugins menu.
3. Make sure WooCommerce is installed and activated before using the checkout flow.
4. Create a product or variation that matches your desired plan logic and use a URL such as `/buy/product-slug/?plan=agency`.

== Frequently Asked Questions ==

= How does the plugin work? =

The plugin listens for URLs that begin with `/buy/`, reads the product slug and the `plan` query parameter, resolves the matching WooCommerce product or variation, and redirects the visitor to checkout.

= Does this plugin work without WooCommerce? =

No. WooCommerce must be installed and activated for the checkout flow and order-related features to work.

= Can I use it with variable products? =

Yes. The plugin can match a plan value against variation attributes and redirect to the correct variation.

= Is the email tracking feature enabled by default? =

Yes, the tracking feature is active when the plugin is enabled and the checkout page is viewed. It captures the billing email and stores it in a dedicated admin view for review.

== Changelog ==

= 1.0.0 =
* Refactored for WordPress.org compatibility
* Improved sanitization, escaping, and WooCommerce guards
* Added localization support and safer asset loading
* Improved plugin metadata and packaging structure