import Echo   from './laravel-echo.js';
import Pusher from './pusher-js.js';

window.Pusher = Pusher;
window.Echo   = new Echo({
    broadcaster: 'pusher',
    key:     'intranet', // import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: 'intranet', // import.meta.env.VITE_PUSHER_APP_CLUSTER,
    wsHost: window.location.hostname,
    wsPort: 6001,
    wssHost: window.location.hostname,
    wssPort: 6001,
    forceTLS: false, // Echo will copy the Page protocol
    disableStats: true,
});

let acorn_wsConnections = {};
$('[websocket-listen]').each(function(){
    if (!acorn_wsConnections[location]) {
        let channel, event,
            channelEvent = $(this).attr('websocket-listen').split(':');

        if (channelEvent.length == 1) {
            channel = channelEvent[0];
            window.Echo
                .private(channel)
                .listenToAll(function(channelEvent, eventMessage) {
                    acorn_onEvent(channel, channelEvent.substring(1), eventMessage);
                });
        } else {
            channel = channelEvent[0];
            event   = channelEvent[1];
            event   = '.' + event; // TODO: Why?

            window.Echo
                .channel(channel)
                .private(event, function(eventMessage) {
                    acorn_onEvent(channel, event.substring(1), eventMessage, eventObject);
                });
        }
        acorn_wsConnections[location] = true;
    }
});

function removeUserId(attrBase){
    let dotIdx = attrBase.indexOf(".");
    if (dotIdx != -1){
        let suffixIdx = attrBase.slice(dotIdx).indexOf("-");
        if (suffixIdx != -1){
            attrBase = attrBase.slice(0, dotIdx) + attrBase.slice(dotIdx + suffixIdx);
        }
        else {
            attrBase = attrBase.slice(0, dotIdx);
        }
    }
    return attrBase;
}

function acorn_onEvent(channel, event, eventMessage) {
    let eventObject = {};
    if (eventMessage instanceof Object) {
        // Extract the first and only object and its class name
        let keys = Object.keys(eventMessage);
        if (keys.length) {
            let className = keys[0];
            eventObject = eventMessage[className];
            eventObject.className = className;
        }
    }
    
    let attributeBases = [],
        eventCum       = '',
        eventSplit     = event.split(/[^a-zA-Z0-9]+/g), // [event, updated]
        contexts       = (eventMessage.contexts ? eventMessage.contexts : []),
        websocketBase  = 'websocket-on',          // websocket-on
        channelBase    = websocketBase + channel; // websocket-oncalendar
    
    // @attributes are hierarchical
    attributeBases.push(removeUserId(websocketBase + 'any')); // websocket-onany
    attributeBases.push(removeUserId(channelBase));           // websocket-oncalendar
    // websocket-oncalendar-event
    // websocket-oncalendar-event-updated
    for (let eventPart of eventSplit) {
        if (eventPart) {
            eventCum += '-' + eventPart;
            attributeBases.push(removeUserId(channelBase + eventCum));
            // Add context to the cumulated event specs
            // websocket-oncalendar-event-1-12
            // websocket-oncalendar-event-updated-1-12
            // NOTE: contexts might unserialize as an object, not an array
            for (let i in contexts) {
                let context = contexts[i];
                if (context) {
                    attributeBases.push(removeUserId(channelBase + eventCum + '-' + context));
                }
            }
        }
    }


    // Attribute processing
    // e.g. @websocket-oncalendar-*-update='conversation: #c1-2'
    for (let attrBase of attributeBases) {
        let context      = attrBase.replace(/^websocket-on/, '').split(/-/g),
            updateAttrC  = attrBase + '-update',  // websocket-oncalendar-*-update
            requestAttrC = attrBase + '-request', // websocket-oncalendar-*-request
            successAttrC = attrBase + '-success', // websocket-oncalendar-*-success
            soundAttrC   = attrBase + '-sound';   // websocket-oncalendar-*-sound
        $('[' + updateAttrC + '],[' + requestAttrC + ']').each(function(){
            let request = $(this).attr(requestAttrC) || 'onWebSocket',
                update  = $(this).attr(updateAttrC),
                success = $(this).attr(successAttrC),
                sound   = $(this).attr(soundAttrC);

            // Send the whole event through, with context
            // for the AJAX handler to consider 
            $(this).request(request, {
                data: {event:eventObject, context:context},
                //update:  JSON.parse(update), // See below
                success: function(response, result, jXHR){
                    if (window.console) console.log(response);
                    if (update) {
                        // TODO: Do this AJAX update properly!
                        // We do this update manually
                        // for efficiency, and to work off same backend Object 
                        let jUpdate = JSON.parse('{' + update.replace(/'/g, '"') + '}');
                        for (var partial in jUpdate) {
                            let path    = jUpdate[partial];
                            // TODO: Should work off the path, not the partial
                            // The onSearch() & updateList() works off getId()
                            let content = response[partial] || response[path];
                            if (content) $(path).html(content);
                            
                            $(path).trigger(jQuery.Event('ajaxUpdate'));
                            $(path).trigger(jQuery.Event('ajaxSuccess'));
                        }
                    }
                    if (sound) {
                        let audio = new Audio(sound);
                        audio.play();
                    }
                    // TODO: SECURITY: XSS possibilities
                    //if (success) eval(success);
                },
            });
        });
    }

    // Dirty write protection
    // editing-eventpart-id-130
    if (eventObject && eventObject.className && eventObject.id) {
        let cssClassClass = eventObject.className.replace(/.*\\/, '').toLowerCase();
        let cssClass = 'editing-' + cssClassClass + '-id-' + eventObject.id;
        $('.' + cssClass).addClass('dirty-read');
    }

    // Trigger a global event
    for (let attrBase of attributeBases) {
        $(document).trigger(jQuery.Event(attrBase), [event, eventObject]); 
    }
}
