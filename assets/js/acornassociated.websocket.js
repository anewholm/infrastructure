function acornassociated_onMessage(message) {
    var event = JSON.parse(message.data);

    if (!event.name) {
        if (window.console) console.error(this, message);
    } else {
        var contexts    = (event.contexts ? event.contexts : ['']),
            namespace   = event.namespace || 'websocket',
            attrBase    = namespace + '-on' + event.name, // websocket-oncalendar
            eventName   = namespace + ':'   + event.name; // websocket:calendar
        if (window.console) console.log(this, event);

        // Attribute processing
        // e.g. @websocket-oncalendar-1-12-update='conversation: #c1-2'
        for (var i in contexts) {
            var context      = contexts[i], // 1-12, thursday
                contextDash  = (context ? '-' + context : ''),
                contextBase  = attrBase + contextDash,   // websocket-oncalendar-1-12
                updateAttrC  = contextBase + '-update',  // websocket-oncalendar-1-12-update
                requestAttrC = contextBase + '-request', // websocket-oncalendar-1-12-request
                successAttrC = contextBase + '-success', // websocket-oncalendar-1-12-success
                soundAttrC   = contextBase + '-sound';   // websocket-oncalendar-1-12-sound
            $('[' + updateAttrC + ']').each(function(){
                var request = $(this).attr(requestAttrC) || 'onWebSocket',
                    update  = $(this).attr(updateAttrC),
                    success = $(this).attr(successAttrC),
                    sound   = $(this).attr(soundAttrC);
                if (update) {
                    // Let us indicate which context we are using exactly
                    var eventContext = JSON.parse(JSON.stringify(event));
                    eventContext.contexts = [context];
                    update = '{' + update.replace(/'/g, '"') + '}';

                    // Send the whole event through, with context
                    // for the AJAX handler to consider 
                    $(this).request(request, {
                        data:    eventContext,
                        update:  JSON.parse(update),
                        beforeUpdate: function(){
                            if (sound) {
                                var audio = new Audio(sound);
                                audio.play();
                            }
                            if (success) eval(success);
                        },
                    });
                }
            });
        }

        // Dirty write protection
        // editing-eventpart-id-130
        if (event && event.class && event.ID) {
            var cssClassClass = event.class.replace(/.*\\/, '').toLowerCase();
            var cssClass = 'editing-' + cssClassClass + '-id-' + event.ID;
            $('.' + cssClass).addClass('dirty-read');
        }

        // Trigger a global event
        $(document).trigger(jQuery.Event(eventName), event); // websocket:calendar
    }
}

var acornassociated_wsConnections = {};
function acornassociated_wsConnect() {
    $('[websocket-listen]').each(function(){
        var self      = this;
        var location  = $(this).attr('websocket-listen') || document.location.host + ':8081';
        if (!acornassociated_wsConnections[location]) {
            acornassociated_wsConnections[location] = new WebSocket("ws://" + location);
            acornassociated_wsConnections[location].onmessage = function(){
                acornassociated_onMessage.apply(self, arguments);
            };
        }
    });
}

$(document).ready(acornassociated_wsConnect);
$(window).on('ajaxUpdateComplete', acornassociated_wsConnect);


