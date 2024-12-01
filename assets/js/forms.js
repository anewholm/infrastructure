$(document).ready(function(){
  // Callouts (hints) close button
  // NOTE: Not using data-dismiss="callout" because we want a cross and a slideUp effect
  $('.callout .close').on('click', function(){
    $(this).closest('.callout').slideUp();
  });

  $('.goto-form-group-selection').hide();

  function updateViewSelectionLink(jInput) {
    var modelUuid = jInput.val();
    var jGotoLink = jInput.closest('.form-group,.custom-checkbox').find('.goto-form-group-selection');

    if (modelUuid && jGotoLink.attr('href')) {
      // Remove any update/id parts from URL
      var urlParts = jGotoLink.attr('href').split('/').filter(n => n);
      if (urlParts[urlParts.length-2] == 'update') {
        urlParts.splice(urlParts.length-2);
      }
      urlParts.push('update');
      urlParts.push(modelUuid);
      var updateUrl = '/' + urlParts.join('/');
      var viewselection = $.wn.lang.get('links.viewselection');
      jGotoLink.attr('href', updateUrl).text(viewselection).show();
    } else {
      jGotoLink.hide();
    }
  }

  // Bind updateViewSelectionLinks to all input and change events
  $(document).on('change', ':input', function (event) {
    updateViewSelectionLink($(this));
  });
  $(window).on('ajaxUpdateComplete', function (event) {
    // We do not know what was updated
    $(':input').each(function (event) {
      updateViewSelectionLink($(this));
    });
  });
});

function acorn_popupComplete(context, textStatus, jqXHR) {
  // When the popup closes, this function will set any passed value
  // on the original form popup button, indicated in field_name
  // and then trigger its change
  // So that other original form fields can dependsOn the results of the popup operation
  var responseId, fieldName;
  if (textStatus == 'success') {
    if (fieldName = context.options.data.field_name) {
      var jField = $("[data-field-name='" + fieldName + "']");
      if (jField.length) {
        if (responseId = jqXHR.responseJSON.id) jField.find(':input').val(responseId);
        jField.find(':input').trigger('change');
      } else {
        var sError = `Field [${fieldName}] not found during refresh`;
        if (window.console) window.console.error(sError);
        $.wn.flashMsg({
          'text': sError,
          'class': 'error'
        });
      }
    }
  }
}
  
+function ($) { "use strict";
  // --------------------------------------- Scrolling popups

  $(window).on('complete.oc.popup', function (event, $content, $popup) {
    // Move extra popups in to the main popup
    var $popups   = $('body > div.control-popup.modal');
    var popupMain = $popups.first().data('oc.popup'); // Contains popup div collection
    var popupNew  = $popup.data('oc.popup'); // Whole new popup, to be injected and removed
    popupMain.lock(true);

    if ($popups.length > 1) {
      // Dim the old content
      popupMain.$content.children('div').addClass('in-active');

      // TODO: Breadcrumb
      // TODO: This should come from the server
      // TODO: oldBreadcrumb = oldBreadcrumb.replace(/^[^ ]+/, ''); // Remove the action: Update / Create
      /*
      var $newBreadcrumbH4      = popupNew.$content.find('.modal-header > h4');
      var newText               = $newBreadcrumbH4.text().trim();
      var $previousBreadcrumbH4 = popupMain.$content.children('div').last().find('.modal-header > h4');
      var $crumbs = $('ul', {class: 'breadcrumb'});
      $crumbs.append($('li').text());
      if ($previousBreadcrumbH4.children('ul.breadcrumb').length) 
      $newBreadcrumbH4.html($previousBreadcrumbH4);
      $newBreadcrumbH4.children('ul.breadcrumb').append($('<li>').text(newText));
      */

      // Append
      // We cannot position: absolute because the content dictates the popup size
      popupNew.$content.children('script').remove();
      popupMain.$content.append(popupNew.$content);
      // Animate
      popupNew.$content.css({left: '90%'}).animate({left: '0%'}, 1000);

      // Remove intended new popup
      // We do not use hide() as it will trigger the hide.oc.popup event process below
      $popup.remove();
      popupNew.setBackdrop(false);
    }
  });

  $(window).on('hide.oc.popup', function (event, $content, $popup) {
    var popupMain  = $popup.data('oc.popup');
    var $popupDivs = popupMain.$content.children('div');

    if ($popupDivs.length > 1) {
      var $lastDiv = $popupDivs.last();
      $lastDiv.css({opacity: '100%'}).animate({left: '90%', opacity: '10%'}, 1000, 'swing', function(){
        $lastDiv.remove();
        popupMain.$content.children('div').last().removeClass('in-active');
      });
    } else if (popupMain.isOpen) {
      popupMain.isOpen = false;
      popupMain.$modal.modal('hide');
    }
  });
}(window.jQuery);