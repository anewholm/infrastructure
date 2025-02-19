function acornassociated_debug(){
    $('*[debug-pane]').hover(function(){
        if (!$(this).children('div.debug').length) {
            var $debugPane = $($(this).attr('debug-pane'));
            var $container = ($(this).hasClass('layout') ? $(this).children().first() : $(this));
            $container.append($debugPane);
            $container.addClass('debug-relative');
        }
    });
    $('.create-system-comment-edit-link').click(function(){
        var self = this;

        switch ($(this).text()) {
            case 'edit':
                var $pre    = $(this).siblings('pre.create-system-comment');
                var comment = $pre.text();
                $pre.replaceWith($('<textarea>', {class: 'create-system-comment'}).text(comment));
                $(this).text('save');
                break;
            case 'save':
                var $textarea   = $(this).siblings('textarea.create-system-comment');
                var $dbLangPath = $(this).siblings('div.create-system-db-lang-path');
                var comment     = $textarea.val();

                $textarea.attr('disabled', 1);
                $.post('/api/comment', {
                    dbLangPath: $dbLangPath.text(), 
                    comment: comment
                }, function(){
                    $textarea.replaceWith($('<pre>', {class: 'create-system-comment comment-dirty'}).text(comment));
                    $(self).text('edit');
                });
                break;
        }
    });
}
$(document).ready(acornassociated_debug);
$(document).on('complete.oc.popup', acornassociated_debug);
