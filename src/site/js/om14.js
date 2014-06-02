(function () {
	window._paq = window._paq || [];
	_paq.push(['enableLinkTracking']);
	(function () {
		_paq.push(['setTrackerUrl', 'https://stats.openmind-konferenz.de/piwik/piwik.php']);
		_paq.push(['setSiteId', pageinfo.piwikID]);
	})();
})();

jQuery(function ($) {
	"use strict";

	var $header = $("#header");

	var hasCanvas = "no";

	// Inspired by <http://stackoverflow.com/a/19129822/417040>, Ken Fyrstenberg Nilsen, Abdias Software, CC3.0-attribute
	var om14pixel = function ($container, src, px) {
		// Create a canvas.
		var $canvas = $("<canvas>");
		var canvas = $canvas.get(0);
		var ctx = canvas.getContext("2d"), img = new Image(), loaded = false;
		// Width and height of the original JPEG.
		var imgW = 0, imgH = 0;
		var drawCanvas = function () {
			if (!loaded) {
				// Do nothing if the image is not available yet. (Can happen if resizing before loading is complete.)
				return;
			}
			// The width and height of the container.
			var contW = $container.innerWidth(), contH = $container.innerHeight();
			// How large of an area (px) does the _small_ image need to cover?
			var coverW = Math.ceil(contW / px), coverH = Math.ceil(contH / px);
			// By what factor to shrink the original image to let it cover the area.
			var ratio = imgW / coverW;
			// Check whether that ratio is too much to cover the height as well, and adjust, if needed.
			if (imgH / ratio < coverH) {
				ratio = imgH / coverH;
			}
			// Final width and height of the small image: at least as large as "cover", but maintaining aspect ratio.
			var smallW = imgW / ratio, smallH = imgH / ratio;
			// And now if we stretch it out again to make every pixel "px" pixels large, how large is it then?
			var stretchedW = smallW * px, stretchedH = smallH * px;
			// Set the correct size of the canvas. This will empty it and reset all of its properties.
			canvas.width = contW; canvas.height = contH;
			// Make sure we don’t smooth the image when scaling it up. We want a pixel effect, after all.
			ctx.mozImageSmoothingEnabled = false;
			ctx.webkitImageSmoothingEnabled = false;
			ctx.imageSmoothingEnabled = false;
			// Draw the small image on the canvas.
			ctx.drawImage(img, 0, 0, smallW, smallH);
			// Resize it to "px" times its size, center it in the container.
			ctx.drawImage(canvas, 0, 0, smallW, smallH, (contW - stretchedW) / 2, (contH - stretchedH) / 2, stretchedW, stretchedH);
			// Set as background image.
			$container.css("background-image", "url(" + canvas.toDataURL() + ")");
		};
		// If the window resizes, we have to redraw.
		$(window).resize(drawCanvas);
		// As soon as the image has loaded, draw it on the canvas.
		img.onload = function () {
			loaded = true;
			imgW = img.width; imgH = img.height;
			drawCanvas();
		};
		// Set the URL.
		img.src = src;
	};

	// If we have a header image and canvas support, do the fancy pixelation stuff. Else, the fallback CSS applies.
	if ($header.length && Modernizr.canvas) {
		hasCanvas = "yes";
		om14pixel($header, "/img/header.jpg", 10);
	}
	_paq.push(["setCustomVariable", 1, "hasJS", "yes", "visit"]);
	_paq.push(["setCustomVariable", 2, "hasCanvas", hasCanvas, "visit"]);
	_paq.push(['trackPageView']);
});
