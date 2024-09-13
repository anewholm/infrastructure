$(document).ready(function(){
  $('.goto-form-group-selection').click(function(){
    var jInput  = $(this).closest('.form-group,.custom-checkbox').find(':input').first();
    var rootURL = $(this).attr('href').replace(/\/$/, '');
    var id      = jInput.val();
    $(this).attr('href', rootURL + '/update/' + id);
    return id.length;
  })
  .text('view selection');

  $('*[goto-form-group-selection]').each(function(){
    var rootURL = $(this).attr('goto-form-group-selection').replace(/\/$/, '');
    $(this).find('.field-checkboxlist').each(function(){
      $(this).find('.custom-checkbox').each(function(){
        var id     = $(this).find(':input').val();
        var jLabel = $(this).find('label');
        var jA     = $('<a>');
        jA.attr('href',  rootURL + '/update/' + id);
        jA.attr('class', 'goto-form-group-selection');
        jA.text('view');
        jLabel.append(jA);
      });
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
      if (responseId = jqXHR.responseJSON.id) {
        var jField = $("[data-field-name='" + fieldName + "'] input");
        jField.val(responseId);
        jField.trigger('change', context);
      }
    } else {
      // TODO: $.request('onRefreshField', {data:{fields:'_purchase'}});
    }
  }
}
