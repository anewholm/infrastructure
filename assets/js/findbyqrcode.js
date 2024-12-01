// https://github.com/mebjas/html5-qrcode
var qrCodeObjects = {};
var qrScannerInitialized = false;

function onScanSuccess(decodeText, decodeResult) {
    var jQrScanner    = $(this);
    var controllerURL = decodeText;
    var actionsString = jQrScanner.attr("actions");
    var actions       = actionsString.split(",");
    var listSelector  = jQrScanner.attr("list-selector");
    var formSelector  = jQrScanner.attr("form-selector");

    var i = 0;
    var actionSuccess, action, isLast;
    do {
        actionSuccess = false;
        action        = actions[i++];
        isLast        = (i == actions.length);

        switch (action) {
            case "find-in-list": {
                var uuid = decodeText;
                var targetRow = $(listSelector).find('tr.rowlink:has(a[href*="' + uuid + '"])');
                if (targetRow.length) {
                    $(listSelector).find("tr.rowlink").removeClass("qrscan-highlight");
                    targetRow.addClass("qrscan-highlight");

                    // Scroll to row
                    // TODO: Untested scrollIntoView()
                    targetRow[0].scrollIntoView({
                        behavior: "smooth", // or "auto" or "instant"
                        block:    "start"   // or "end"
                    });

                    actionSuccess = true;
                } else if (isLast) {
                    // TODO: Translate JS Flash message
                    $.wn.flashMsg({
                        'text': 'Could not find QR code item in the list',
                        'class': 'error'
                    });
                }
                break;
            }

            case "redirect": {
                document.location = controllerURL;
                actionSuccess     = true;
                break;
            }

            case "form-field-complete": {
                var jInput = jQrScanner.siblings("input");
                var id     = jInput.attr('id');

                // Copy or find the input in the target form
                // Note that this target form may be blank
                // as this formField widget <input> may already be in the target form
                var jTargetForm = $(formSelector);
                if (jTargetForm.length) {
                    var jExists = jTargetForm.find('#' + id);
                    if (jExists.length) jInput = jExists;
                    else                jInput = jInput.appendTo(jTargetForm);
                }

                // Set the value, and trigger a change in the target form
                jInput.val(controllerURL);
                jInput.trigger("change", controllerURL);

                actionSuccess = true;
                break;
            }
        }
    } while (i < actions.length && !actionSuccess);

    if (actionSuccess) $(this).trigger('close.oc.popup');
}

function initializeQrScanner() {
    // Initialize the QR code scanner when the element exists
    $("#my-qr-reader").each(function () {
        var self = this;
        let htmlscanner = new Html5QrcodeScanner("my-qr-reader", {
            fps: 10,
            qrbos: 300,
            facingMode: "user",
            formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE],
        });
        htmlscanner.render(function (decodeText, decodeResult) {
            onScanSuccess.call(self, decodeText, decodeResult);
        });
        qrScannerInitialized = true;
    });
}

$(document).ready(function () {
    initializeQrScanner();
    console.log('initializeQrScanner is work  from domready ')
});

$(document).on("complete.oc.popup", function () {
    initializeQrScanner();
    console.log('initializeQrScanner is work from Complted popup');
});
