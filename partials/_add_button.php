<?php
// Auto field names should be in the form _<action>_<singular unqualified controller class>
$fieldName      = &$field->fieldName;
$arrayName      = &$field->arrayName;
$fieldNameParts = explode('_', trim($fieldName, '_'));
$fqFieldName    = "${arrayName}[${fieldName}]";

$action   = 'add';
$hasCustomLabel = isset($field->config['label']);
$disabled = (isset($field->config['disabled']) ? $field->config['disabled'] : FALSE);
$label    = ($hasCustomLabel                   ? $field->config['label']    : "backend::lang.form.$action");

// Escaping
$disabledAttr = ($disabled ? 'disabled="disabled"' : '');
$labelTrans   = e(trans($label));

// TODO: ajax-complete reset val()
$onClick        = "$(this).siblings('input').val(1);"; // Indicate that the add_button needs processing by filterFields()
$onClick       .= "$(this).trigger('change', 'add');"; // Trigger those fields depndsOn this one
$onClick       .= "return false;";                     // Prevent any normal actions around or attached to this button
$onClickEscaped = htmlentities($onClick);
$labelPlaceholder = ($hasCustomLabel ? '' : '<label>&nbsp;</label>');

// -------------------------------------------- Output
echo <<<HTML
  $labelPlaceholder
  <input
    type="hidden"
    name="$fqFieldName"
    value=""
    class="form-control"
    data-disposable="data-disposable"
  ></input>
  <button
    $disabledAttr
    class="btn btn-default form-button add-button"
    onclick="$onClickEscaped">
    $labelTrans
  </button>
HTML;
?>
