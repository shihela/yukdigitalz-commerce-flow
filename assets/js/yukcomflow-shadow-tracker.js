jQuery(document).ready(function ($) {
	var lastCapturedEmail = '';
	var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
	var emailSelector = '#billing_email, #email, .wc-block-checkout input[type="email"]';

	function captureCheckoutEmail(rawValue) {
		var email = $.trim(String(rawValue || ''));
		if (!email || email === lastCapturedEmail || !emailRegex.test(email)) {
			return;
		}

		if (typeof yukComFlowShadowCart === 'undefined' || !yukComFlowShadowCart.ajaxUrl) {
			return;
		}

		$.ajax({
			url: yukComFlowShadowCart.ajaxUrl,
			type: 'POST',
			data: {
				action: 'yukcomflow_capture_email',
				email: email,
				security: yukComFlowShadowCart.nonce,
			},
			success: function (response) {
				if (response && response.success) {
					lastCapturedEmail = email;
				}
			},
		});
	}

	// Delegated events ensure compatibility with both Classic Checkout and WooCommerce Block Checkout.
	$(document).on('blur change', emailSelector, function () {
		captureCheckoutEmail($(this).val());
	});
});
