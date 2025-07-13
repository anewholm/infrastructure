function acorn_updateViewSelectionLink() {
  var jInput    = $(this);
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

function acorn_dynamicElements(){
  // Callouts (hints) close button
  // NOTE: Not using data-dismiss="callout" because we want a cross and a slideUp effect
  $('.callout .close').on('click', function(){
    $(this).closest('.callout').slideUp();
  });

  $('.goto-form-group-selection').hide();

  // We do not know what was updated
  $(':input').each(acorn_updateViewSelectionLink);

  // Hide hide-empty tabs
  $('.control-tabs .nav-tabs > li').each(function(i){
    var jTab     = $(this);
    var jTabPane = $(this).closest('.control-tabs').find('> .tab-content > .tab-pane').eq(i);
    var noData   = jTabPane.find('> .hide-empty tr.no-data').length;
    if (noData) {
      jTab.remove();
      jTabPane.remove();
    }
  });

  // HTML Tooltips
  // TODO: It is possible we have not understood data-toggle="tooltip" "documentation"
  $('*:has(> .tooltip)').hover(function(){
    var jTooltip = $(this).children('.tooltip');
    jTooltip.addClass('in').fadeIn();

    if (jTooltip.hasClass('top')) {
      var height = jTooltip.height() + $(this).height();
      var width  = $(this).width() / 2;
      jTooltip.css({marginTop:-height + 'px', marginLeft:-width + 'px'});
    }
  }, function(){
    $(this).children('.tooltip').fadeOut();
  });

  // list-editable
  var fCheckDirty = function(event){
    var jTable = $(this).closest('table');
    var jSave  = $("button[data-request=onListEditableSave]");
    jSave.attr('disabled', 1);

    var tableIsDirty = false;
    jTable.children('tbody').children('tr').each(function(){
      var jCheck     = $(this).children('td.list-checkbox').first().find(':input');
      var rowIsDirty = false;
      $(this).find(':input.list-editable').each(function(){
        if ($(this).attr('original') != $(this).val()) {
          rowIsDirty   = true;
          tableIsDirty = true;
        }
      });
      
      if (rowIsDirty) {
        $(this).addClass('dirty');
        if (!jCheck.is(':checked')) jCheck.click();
      } else {
        $(this).removeClass('dirty');
        if (jCheck.is(':checked')) jCheck.click();
      }
    });

    if (tableIsDirty) {
      jSave.removeAttr('disabled');
    }

    event.stopPropagation();
    return false;
  };
  // Catch in the input because row should still be clickable
  $(':input.list-editable').click(function(event){
    event.stopPropagation();
  })
  .change(fCheckDirty)
  .keyup(fCheckDirty);

  // Enable read-only for radio buttons
  // HTML does not accept readonly on radio buttons
  // So we disable not-allowed options
  // NOTE: form.css will also gray the labels
  $('input[readonly]:radio:not(:checked)').attr('disabled', true);
}
$(document).ready(acorn_dynamicElements);
$(window).on('ajaxUpdateComplete', acorn_dynamicElements);
$(document).on('change', ':input', acorn_updateViewSelectionLink);

function acorn_setAdvanced(show, delay) {
    if (delay === undefined) delay = 0;

    // Fields
    var jAdvancedFields  = $('.form-group.advanced');
    if (show) jAdvancedFields.slideDown(delay, function(){
      jAdvancedFields.removeClass('hidden');
    });
    else jAdvancedFields.slideUp(delay, function(){
      jAdvancedFields.addClass('hidden');
    });

    // Show/hide empty tabs
    $('.nav-tabs').children('li').each(function() {
      // We want to show/hide any tabs that
      // only have advanced fields
      // that are now hidden
      var jLI  = $(this);
      var id   = jLI.children('a[data-target]').attr('data-target');
      var jTab = $(id);

      // Tab contents disappear
      var hasNormalFields = jTab.find('.form-group').not('.advanced').length;
      if (!hasNormalFields) {
        if (show) {
          jLI.removeClass('advanced-hidden');
          jLI.slideDown(delay, function(){
            jLI.removeAttr('style');
          });
        } else jLI.slideUp(delay, function(){
          jLI.addClass('advanced-hidden');
        });
      }
    });
}

function acorn_ready(){
  // Permissions screen
  $('.permissioneditor > table').addClass('collapsable');
  // README.md screen
  $('.plugin-details-content > h1').addClass('collapsable');
  
  $('div.control-toolbar .select2-container').click(function (event){
    // The toolbar dropdowns (e.g. ActionTemplates) are immediately submitting the form
    event.stopImmediatePropagation();
    event.stopPropagation();
    return false;
  });
  $('.select-and-go').change(function(event){
    if ($(this).find(':input').val()) {
      // trigger('submit') on the <form> doesn't seem to trigger the popup
      var jSubmitButton = $(this).closest('form').find('input[type=submit]');
      jSubmitButton.trigger('click');
    }
  });

  // Collapseable <table>s
  $('table.collapsable tr.section').click(function(){
    var jRows = $(this).nextUntil('tr.section');
    if (jRows.is(':visible')) jRows.hide();
    else jRows.show();
  });
  // Collapseable sibling <pre>s
  $('h1.collapsable').click(function(){
    var jRows = $(this).next('pre');
    if (jRows.is(':visible')) jRows.hide();
    else jRows.show();
  });

  $('.action-functions #advanced').click(function(event){
    // Show/hide fields
    var jButton          = $(this);
    var jAdvancedFields  = $('.form-group.advanced');
    var fieldsAreVisible = jAdvancedFields.not('.hidden').length;

    acorn_setAdvanced(!fieldsAreVisible, 400);

    if (fieldsAreVisible) jButton.removeClass('shown');
    else                  jButton.addClass('shown');

    // That's quite enough of that
    event.stopPropagation();
    event.stopImmediatePropagation();
    event.preventDefault();
    return false;
  });

  // Cookie remember advanced state
  acorn_setAdvanced(false, 0);
}
$(document).ready(acorn_ready);

function acorn_popupComplete(context, textStatus, jqXHR) {
  // When the popup closes, this function will set any passed value
  // on the original form popup button, indicated in field_name
  // and then trigger its change
  // So that other original form fields can dependsOn the results of the popup operation
  // Called with:
  //   data-request-success='acorn_popupComplete(context, textStatus, jqXHR);'
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