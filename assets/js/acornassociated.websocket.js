function onMessage(message) {
    var event = JSON.parse(message.data);

    if (!event.name) {
        if (window.console) console.error(this, message);
    } else {
        var namespace   = event.namespace || 'websocket';
            attrBase    = namespace + '-on' + event.name; // websocket-oncalendar
            updateAttr  = attrBase + '-update',           // websocket-oncalendar-update
            requestAttr = attrBase + '-request',          // websocket-oncalendar-request
            eventName   = namespace + ':' + event.name;   // websocket:calendar
        if (window.console) console.log(this, event);

        // Auto-partial updates
        $('[' + updateAttr + ']').each(function(){ // [websocket-oncalendar-update]
            var request = $(this).attr(requestAttr) || 'onWebSocket',
                update  = '{' + $(this).attr(updateAttr) + '}';
            if (update) {
                update = update.replace(/'/g, '"');
                $(this).request(request, {
                    data: event,
                    update: JSON.parse(update),
                });
            }
        });

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

function wsConnect() {
    var connections = {};
    $('[websocket-listen]').each(function(){
        var self      = this;
        var location  = $(this).attr('websocket-listen') || 'localhost:8081';
        if (!connections[location]) {
            connections[location] = new WebSocket("ws://" + location);
            connections[location].onmessage = function(){onMessage.apply(self, arguments);};
        }
    });
}

$(document).ready(wsConnect);


