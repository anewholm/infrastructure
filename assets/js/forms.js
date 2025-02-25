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

  // Enable read-only for radio buttons
  // HTML does not accept readonly on radio buttons
  // So we disable not-allowed options
  // NOTE: form.css will also gray the labels
  $('input[readonly]:radio:not(:checked)').attr('disabled', true);
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
    // NOTE: complete.oc.popup fires x 2!
    var validFormPopup = '.modal-content > div > form[data-request*=onRelationManage]';
    var $popups   = $(`body > div.control-popup.modal:has(${validFormPopup})`);
    var popupMain = $popups.first().data('oc.popup'); // Contains popup div collection
    var popupNew  = $popup.data('oc.popup'); // Whole new popup, to be injected and removed
    
    if ($popups.length > 1 && popupMain) {
      popupMain.lock(true);

      // Dim the old divs
      popupMain.$content.children('div').addClass('in-active');

      // TODO: Breadcrumb
      // The visible breadcrumb will be contained _within_ the appended popupNew.firstDiv
      // thus the popupNew.firstDiv breadcrumb should be altered
      // => latest div#Form breadcrumb + <li>popupNew.firstDiv breadcrumb
      var $newModalHeader       = popupNew.$content.find('.modal-header');
      var $newBreadcrumbH4      = $newModalHeader.children('h4');
      var newText               = $newBreadcrumbH4.text().trim();
      var $newItem              = $('<li>').text(newText);
      var $previousBreadcrumbH4 = popupMain.$content.children('div').last().find('.modal-header > h4');
      var $previousBreadCrumbCB = $previousBreadcrumbH4.children('div.control-breadcrumb').clone();
      if (!$previousBreadCrumbCB.length) {
        // Create the initial control-breadcrumb
        var $ul = $('<ul>').append($('<li>').text($previousBreadcrumbH4.text().trim()));
        $previousBreadCrumbCB = $('<div>', {class: 'control-breadcrumb'}).append($ul);
      }
      $newModalHeader.addClass('compact');
      $newBreadcrumbH4.addClass('modal-title');
      $previousBreadCrumbCB.children('ul').append($newItem);
      // Annotate LIs and texts
      var $lis = $previousBreadCrumbCB.children('ul').children('li');
      var i    = $lis.length - 1;
      $lis.each(function() {
        // Remove verb
        if (i) $(this).text($(this).text().replace(/^[^ ]+/, ''));
        $(this).attr('class', 'stage-' + i--);
      });
      $newBreadcrumbH4.html($previousBreadCrumbCB);
      
      // Append
      // We cannot position: absolute because the content dictates the popup size
      var $newDiv = popupNew.$content.children('div').first();
      popupMain.$content.append($newDiv);
      // Animate
      var $lastDiv = popupMain.$content.children('div').last();
      $lastDiv.css({left: '90%'}).animate({left: '0%'}, 1000);

      // Remove intended new popup
      // We do not use hide() as it will trigger the hide.oc.popup event process below
      // NOTE: complete.oc.popup fires x 2!
      // So it is necessary to remove the extra popup immediately
      $popup.remove();
      // The timeout is necessary because popup.js has already set a timer to act on the backdrop
      setTimeout(function() {
        popupNew.$backdrop.remove();
      }, 100);
    }
  });

  $(window).on('hide.oc.popup', function (event, $content, $popup) {
    var popupDivs  = '.modal-content > div:has(> form[data-request*=onRelationManage])';
    var $popupDivs = $popup.find(popupDivs);
    var popup      = $popup.data('oc.popup');
    
    if ($popupDivs.length > 1) {
      var $lastDiv = $popupDivs.last();
      $lastDiv.css({opacity: '100%'}).animate({left: '90%', opacity: '10%'}, 1000, 'swing', function(){
        // Remove the last div, and then Active the new last div
        $lastDiv.remove();
        $lastDiv = $popup.find(popupDivs).last();
        $lastDiv.removeClass('in-active');
      });
    } else if (popup.isOpen) {
      // This will trigger another hide.oc.popup event
      // but this time isOpen == false 
      popup.isOpen = false;
      popup.$modal.modal('hide'); // hide.bs.modal
    }
  });

  $(document).on('ajaxPromise', '[data-popup-load-indicator]', function(event, context) {
    // The standard functionality ajaxPromise event
    // hides the popup when a form submit request is made
    // and shows the loading indicator
    // then, after success, it remains hidden for some reason
    // We show both
    if ($(this).data('request') != context.handler) return;
    $(this).closest('.control-popup').addClass('in');
  });
}(window.jQuery);