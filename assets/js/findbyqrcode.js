// https://github.com/mebjas/html5-qrcode
var qrCodeObjects = {};

function domReady(fn) {
	if (
		document.readyState === "complete" ||
		document.readyState === "interactive"
	) {
		setTimeout(fn, 1000);
	} else {
		document.addEventListener("DOMContentLoaded", fn);
	}
}

domReady(function () {
    // If found you qr code
    function onScanSuccess(decodeText, decodeResult) {
            // https://github.com/mebjas/html5-qrcode
            var jQrScanner = $('#my-qr-reader');
            var object     = JSON.parse(decodeText);
            var urlOptions = document.location.search.substr(1).split('&');
            object.debug   = (urlOptions.indexOf('debug') !== -1);

            // Only allow each object to be scanned once
            if (!qrCodeObjects[decodeText]) {
                qrCodeObjects[decodeText] = object;

                $('#qr-code-objects tbody').append('<tr><td>' + object.model + '</td><td>' + object.id + '</td></tr>');

                switch (jQrScanner.attr('mode')) {
                    case 'redirect':
                        // Object finding
                        var authorFolder     = object.author.toLowerCase();
                        var pluginFolder     = object.plugin.toLowerCase();
                        var modelFolder      = object.model.toLowerCase();
                        // TODO: This will all be replaced by URL QRCodes
                        var controllerFolder = modelFolder + 's';
                        var action           = 'update';
                        var pluginRoot       = '/backend/' + authorFolder + '/' + pluginFolder;
                        var controllerRoot   = pluginRoot + '/' + controllerFolder;
                        var query            = 'set-url'; // => UserNavigation
                        document.location    = controllerRoot + '/' + action + '/' + object.id + '?' + query;
                        break;
                    case 'field':
                    default:
                        // Useful for dependsOn:
                        if (window.console) console.log(object);
                        jQrScanner.siblings('input').val(JSON.stringify(object));
                        jQrScanner.trigger('change', object);
                        break;
                }
            } else {
                if (window.console) console.warn(object);
            }
    }

    if ($("#my-qr-reader").length) {
        let htmlscanner = new Html5QrcodeScanner(
            "my-qr-reader",
            {
                fps: 10,
                qrbos: 300,
                facingMode: "user",
                formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE]
            }
        );
        htmlscanner.render(onScanSuccess);
    }
});
