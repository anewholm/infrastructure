if (!String.prototype.plural) String.prototype.plural = function plural() {
    if (this.endsWith('y')) return this.substr(0, this.length-1) + 'ies';
    return this + 's';
};

function acorn_hashbangCommands() {
    var path, parts, command, hashbang = document.location.hash;
    if (hashbang && hashbang.substr(0,2) == '#!') {
        if (path = hashbang.substr(2)) {
            parts   = path.replace(/^\/+|\/+$/g, '').split('/');
            // SECURITY: we are allowing running of functions based on URL input
            command = 'acorn_public_' + parts[0];
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

$(document).ready(acorn_hashbangCommands);

String.prototype.toUCFirst = function(){
    return this.charAt(0).toUpperCase() + this.replace(/[^a-z]+/gi, '').slice(1);
}

$(document).ready(function(){
    $('.multi > li > a, .action-functions > li > a[data-control=popup]').click(function(event) {
        event.stopPropagation();
        event.preventDefault();
        $(this).popup(this.attributes);
        return false;
    });
});
