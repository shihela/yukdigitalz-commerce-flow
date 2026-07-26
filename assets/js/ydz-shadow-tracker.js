jQuery(document).ready(function ($) {
	let emailCaptured = false;

	$('#billing_email').on('blur', function () {
		if (emailCaptured) {
			return;
		}

		let email = $(this).val();
		let emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

		if (email && emailRegex.test(email)) {
			$.ajax({
				url: ydzShadowCart.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ydz_capture_email',
					email: email,
					security: ydzShadowCart.nonce,
				},
				success: function (response) {
					if (response.success) {
						emailCaptured = true;
					}
				},
			});
		}
	});
});
