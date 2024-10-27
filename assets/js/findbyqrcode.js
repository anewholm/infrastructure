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
    // If found your QR code
    function onScanSuccess(decodeText, decodeResult) {
        // https://github.com/mebjas/html5-qrcode
        var jQrScanner = $('#my-qr-reader');
        var object;

        if (window.console) console.info('QRCode:' + decodeText);

        // If the QR code has already been scanned, show a message and do nothing
        if (qrCodeObjects[decodeText]) {
            $.oc.flashMsg({
                'text': 'Qrcode has already been scanned.',
                'class': 'error',
                'interval': 3
            })
            if (window.console) console.warn('QRCode already scanned');
            return; //Do nothing else
        }
        // If the code has not been scanned before
        qrCodeObjects[decodeText] = true;

        // Check if decodeText is a URL
        var controllerURL = decodeText;
        if (decodeText.startsWith("{")) {
            // Legacy JSON object support
            // "{"author":"Acorn","plugin":"Lojistiks","model":"Transfer","id":"ead5e26f-6ea9-4882-9412-fe5815c04e12"}"
            var decodeJSON  = JSON.parse(decodeText);
            var pluralModel = decodeJSON.model.plural();
            var action      = 'update'; // TODO: Permissions?
            controllerURL   = '/backend/' + decodeJSON.author.toLowerCase() + '/' + decodeJSON.plugin.toLowerCase() + '/' + pluralModel.toLowerCase() + '/' + action + '/' + decodeJSON.id;
        }
        if (window.console) console.log(controllerURL);

        // Handle based on scanner mode (redirect or field)
        switch (jQrScanner.attr('mode')) {
            case 'redirect':
                document.location = controllerURL;
                break;
            case 'field':
            default:
                // Useful for dependsOn:
                jQrScanner.siblings('input').val(controllerURL);
                jQrScanner.trigger('change', controllerURL);
                break;
        }
    }

    // Initialize the QR code scanner if the element exists
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
