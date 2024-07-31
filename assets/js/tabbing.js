// ---------------------------------------------- Tab key navigation
function acorn_tabbing(){
  $(':input[tab-preshow],.form-group[tab-preshow],.radio-field[tab-preshow]').keydown(function(event){
    if (event.keyCode == 9 && !event.shiftKey) {
      var tabPreShow = $(this).attr('tab-preshow');
      var tabIfNot   = $(this).attr('tab-ifnot');

      if (!tabIfNot || !$(tabIfNot).is(":visible")) {
        $(tabPreShow).tab('show');
        event.preventDefault();

        // Prevent focusing on the tab control
        var jFormGroup = $('.tab-pane.active').find('.form-group:has(:input,a)').eq(0);
        if      (jFormGroup.find('.select2-selection').length) jFormGroup.find('.select2-selection').focus();
        else if (jFormGroup.find('.field-fileupload' ).length) jFormGroup.find('a').focus();
        else jFormGroup.find(':input').focus();
      }
    }
  })
  .keyup(function(){
    if (event.keyCode == 9 && !event.shiftKey) {
      var tabIfNot = $(this).attr('tab-ifnot');

      if (!tabIfNot || !$(tabIfNot).is(":visible")) {
        event.preventDefault();
      }
    }
  });

  $(':input[tab-next],.form-group[tab-next],.radio-field[tab-next]').keydown(function(event){
    if (event.keyCode == 9 && !event.shiftKey) {
      var tabNext  = $(this).attr('tab-next');
      var tabIfNot = $(this).attr('tab-ifnot');
      var inputNot = $(event.target).hasAttr('tab-no');

      if (!inputNot && (!tabIfNot || !$(tabIfNot).is(":visible"))) {
        $(tabNext).focus();
        event.preventDefault();
      }
    }
  })
  .keyup(function(){
    if (event.keyCode == 9 && !event.shiftKey) {
      var tabIfNot = $(this).attr('tab-ifnot');

      if (!tabIfNot || !$(tabIfNot).is(":visible")) {
        event.preventDefault();
      }
    }
  });

  // Focus highlighting
  $('input[type=checkbox]').focus(function(){
    $(this).closest('.form-group').addClass('switch-container--focus');
    return false;
  })
  .blur(function(){
    $(this).closest('.form-group').removeClass('switch-container--focus');
  });

  $('select').focus(function(){
    $(this).closest('.form-group').find('.select2-container').addClass('select2-container--focus');
    return false;
  })
  .blur(function(){
    $(this).closest('.form-group').find('.select2-container').removeClass('select2-container--focus');
  });

  // Radio field key selection
  $('div.form-group.radio-field').keydown(function(event){
    // Select radio
    var jCustomRadios = $(this).find('.custom-radio');
    var letterPressed = event.key.toUpperCase();
    var jTarget;
    jCustomRadios.each(function(){
      if ($(this).children('label').text().replace(' [','').startsWith(letterPressed)) jTarget = $(this);
    });
    if (jTarget) jTarget.children('input').prop('checked', 1).trigger('click');
  })
  // Adorn labels
  .find('.custom-radio > label').each(function(){
    var text = $(this).text().trim();
    var firstLetter  = text.substr(0,1);
    var otherLetters = text.substr(1);
    $(this).html(' [' + firstLetter + ']' + otherLetters);
  });

  if (window.console) console.info('Tabbing setup');
}
$(document).ready(acorn_tabbing);
$(window).on('ajaxUpdateComplete', acorn_tabbing);

// ---------------------------------------------- Initial Focus
function acorn_initialFocus() {
  // Initial focusing
  $('*[tabindex=1]').focus();
  $('.initial-focus').focus();
  $('.initial-focus .form-control').focus();

  if (window.console) console.info('Initial focus');
};
$(document).ready(acorn_initialFocus);

// ---------------------------------------------- Page load tab select
function acorn_public_tabselect(tabHash, fieldHashClick, fieldHashFocus) {
  // hash bang direct multi-function:
  // #!<tab select>/<field click>/<field highlight>
  // e.g. http://university-acceptance.laptop/backend/university/mofadala/controllerstudent/create#!tabselect/primarytab-universitymofadalalangmofadalacandidacy-intent/Form-field-ModelStudent-attending_the_nomination_examination/Form-field-ModelStudent-candidacy_examination_score
  if (tabHash) {
    $('*[href=#' + tabHash + ']').tab('show');
    if (window.console) console.info('Tab selected: ' + tabHash);
  }
  if (fieldHashClick) {
    var jFieldClick = $('#' + fieldHashClick);
    if (!jFieldClick.is(':checked')) {
      jFieldClick.trigger('click');
      if (window.console) console.info('Field clicked: ' + fieldHashClick);
    } else if (window.console) console.info('Field already checked: ' + fieldHashClick);
  }
  if (fieldHashFocus) {
    $('#' + fieldHashFocus).focus();
    if (window.console) console.info('Field focus: ' + fieldHashFocus);
  }
}
