function acornassociated_hashbangCommands() {
    var path, parts, command, hashbang = document.location.hash;
    if (hashbang && hashbang.substr(0,2) == '#!') {
        if (path = hashbang.substr(2)) {
            parts   = path.replace(/^\/+|\/+$/g, '').split('/');
            // SECURITY: we are allowing running of functions based on URL input
            command = 'acornassociated_public_' + parts[0];
            if (window[command] instanceof Function) {
                // We timeout in case handlers need to be attached
                setTimeout(function(){
                    window[command].apply(this, parts.splice(1));
                }, 0);
            } else {
                if (window.console) console.error('command [' + command + '] not found');
            }
        }
    }
}

$(document).ready(acornassociated_hashbangCommands);
