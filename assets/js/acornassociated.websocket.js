import Echo   from './laravel-echo.js';
import Pusher from './pusher-js.js';

//window.Pusher = Pusher;
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
if (window.console) console.log(window.Echo);

window.Echo
    .channel('acornassociated')
    .listen('.user.navigation', function(eventObject) {
        let url        = eventObject.url;
        let pathname   = url.replace(/^[a-z]+:\/\/[^/]+|\?.*/g, '');
        if (document.location.pathname != pathname) document.location.pathname = pathname;
    });


let acornassociated_wsConnections = {};
$('[websocket-listen]').each(function(){
    if (!acornassociated_wsConnections[location]) {
        let channel, eventName,
            channelEvent = $(this).attr('websocket-listen').split(':');

        if (channelEvent.length == 1) {
            channel = channelEvent[0];
            window.Echo
                .channel(channel)
                .listenToAll(function(_eventName, eventObject) {
                    acornassociated_onEvent(channel, _eventName.substring(1), eventObject);
                });
        } else {
            channel   = channelEvent[0];
            eventName = channelEvent[1];
            eventName = '.' + eventName; // TODO: Why .event?

            window.Echo
                .channel(channel)
                .listen(eventName, function(eventObject) {
                    acornassociated_onEvent(channel, eventName.substring(1), eventObject);
                });
        }
        if (window.console) console.info('Listening to websocket channel [' + channel + ']');
        acornassociated_wsConnections[location] = true;
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

function acornassociated_onEvent(channel, eventName, eventObject) {
    if (window.console) console.log(eventObject); // e.g. DataChange
    
    // TODO: This recently changed, so probably broken the Calendar system
    // the eventObject contained the eventObject before. Now it is the eventObject
    let attributeBases = [],
        eventCum       = '',
        eventSplit     = eventName.split(/[^a-zA-Z0-9]+/g), // [event, updated]
        contexts       = (eventObject.contexts ? eventObject.contexts : []),
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
    let foundHandler = false;
    for (let attrBase of attributeBases) {
        // TODO: attrBase-restrict would limit server requests
        // for example:
        //   ondata-change-restrict=modelClass:~Brand
        //   onuser-navigation-restrict=ID:12
        let context      = attrBase.replace(/^websocket-on/, '').split(/-/g),
            updateAttrC  = attrBase + '-update',  // websocket-oncalendar-*-update
            requestAttrC = attrBase + '-request', // websocket-oncalendar-*-request
            successAttrC = attrBase + '-success', // websocket-oncalendar-*-success
            soundAttrC   = attrBase + '-sound';   // websocket-oncalendar-*-sound
        $('[' + updateAttrC + '],[' + requestAttrC + ']').each(function(){
            let request = $(this).attr(requestAttrC) || 'onWebSocket',
                update  = $(this).attr(updateAttrC),
                success = $(this).attr(successAttrC),
                sound   = $(this).attr(soundAttrC)   || '/modules/acornassociated/assets/sounds/notification.mp3',
                jUpdate = (update ? JSON.parse('{' + update.replace(/'/g, '"') + '}') : null);

            // Send the whole event through, with context
            // for the AJAX handler to consider 
            if (window.console) console.info(request);
            foundHandler = true;
            $(this).request(request, {
                data: {event:eventObject, context:context},
                update:  jUpdate,
                success: function(response, result, jXHR){
                    if (window.console) console.log(response);

                    // Process the update: clause
                    // https://wintercms.com/docs/v1.2/docs/ajax/javascript-api
                    // "If this option is supplied it overrides the default framework's functionality
                    // However, you can still call the default framework functionality calling this.success(...) inside your function."
                    this.success(response, result, jXHR);

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
    if (!foundHandler &&  window.console) {
        console.warn('Handler not found for [' + channel + ':' + eventName + ']');
        console.log(attributeBases);
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
        $(document).trigger(jQuery.Event(attrBase), [eventName, eventObject]);
    }
}
