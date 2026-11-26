(function () {
	'use strict';

	function fallbackCopyText(text) {
		var textarea = document.createElement('textarea');
		textarea.value = text;
		textarea.setAttribute('readonly', '');
		textarea.style.position = 'fixed';
		textarea.style.opacity = '0';
		document.body.appendChild(textarea);
		textarea.select();

		var succeeded = false;
		try {
			succeeded = document.execCommand('copy');
		} catch (e) {
			succeeded = false;
		}

		document.body.removeChild(textarea);
		return succeeded;
	}

	function showCopiedFeedback(button) {
		var copiedLabel =
			window.yukComFlowAdmin && window.yukComFlowAdmin.copiedText
				? window.yukComFlowAdmin.copiedText
				: 'Copied!';
		var originalText = button.getAttribute('data-original-text') || button.textContent;

		button.setAttribute('data-original-text', originalText);
		button.textContent = copiedLabel;
		button.classList.add('is-copied');

		window.setTimeout(function () {
			button.textContent = originalText;
			button.classList.remove('is-copied');
		}, 2000);
	}

	document.addEventListener('click', function (event) {
		var button = event.target.closest('.yukcomflow-copy-btn');
		if (!button) {
			return;
		}

		var url = button.getAttribute('data-url');
		if (!url) {
			return;
		}

		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard
				.writeText(url)
				.then(function () {
					showCopiedFeedback(button);
				})
				.catch(function () {
					if (fallbackCopyText(url)) {
						showCopiedFeedback(button);
					}
				});
		} else if (fallbackCopyText(url)) {
			showCopiedFeedback(button);
		}
	});
})();
