(function () {
	'use strict';

	// Source: https://developers.google.com/speed/webp/faq#in_your_own_javascript.
	function check_webp_feature(feature, callback) {
		var kTestImages = {
			alpha: "UklGRkoAAABXRUJQVlA4WAoAAAAQAAAAAAAAAAAAQUxQSAwAAAARBxAR/Q9ERP8DAABWUDggGAAAABQBAJ0BKgEAAQAAAP4AAA3AAP7mtQAAAA==",
		};
		var img = new Image();
		img.onload = function () {
			var result = (img.width > 0) && (img.height > 0);
			callback(result);
		};
		img.onerror = function () {
			callback(false);
		};
		img.src = "data:image/webp;base64," + kTestImages[feature];
	}

	check_webp_feature('alpha', (isSupportedWebP) => {
		document.documentElement.classList.add(isSupportedWebP ? 'webp' : 'no-webp');
		if (isSupportedWebP) {
			return;
		}

		const originalGetAttribute = Object.getOwnPropertyDescriptor(Element.prototype, 'getAttribute');

		// Redefine the getAttribute function with a custom implementation
		Object.defineProperty(Element.prototype, 'getAttribute', {
			value: function (attributeName) {
				// data-smush-webp-fallback.
				if (!this.dataset.smushWebpFallback) {
					return originalGetAttribute.value.call(this, attributeName);
				}

				const webpFallbackValue = JSON.parse(this.dataset.smushWebpFallback);

				if (attributeName in webpFallbackValue) {
					return webpFallbackValue[attributeName];
				}

				return originalGetAttribute.value.call(this, attributeName);
			}
		});

		const webpFallbackElements = document.querySelectorAll('[data-smush-webp-fallback]:not(.lazyload)');
		if (webpFallbackElements.length) {
			// Update background image, src, srcset.
			const imageDisplayAttrs = ['src', 'srcset'];
			webpFallbackElements.forEach((element) => {
				const webpFallbackValue = JSON.parse(element.dataset.smushWebpFallback);
				imageDisplayAttrs.forEach(function (attrName) {
					if (attrName in webpFallbackValue) {
						element.setAttribute(attrName, webpFallbackValue[attrName]);
					}
				});

				// Update background image.
				if ('bg' in webpFallbackValue) {
					element.style.background = webpFallbackValue.bg;
				}
				if ('bg-image' in webpFallbackValue) {
					element.style.backgroundImage = webpFallbackValue['bg-image'];
				}
			});
		}
	});
})();
