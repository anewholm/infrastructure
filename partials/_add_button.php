<?php
// Auto field names should be in the form _<action>_<singular unqualified controller class>
$fieldName      = &$field->fieldName;
$arrayName      = &$field->arrayName;
$fieldNameParts = explode('_', trim($fieldName, '_'));
$fqFieldName    = "${arrayName}[${fieldName}]";

$action   = 'add';
$label    = (isset($field->config['label']) ? $field->config['label'] : "backend::lang.form.$action");
$disabled = (isset($field->config['disabled']) ? $field->config['disabled'] : FALSE);

// Escaping
$disabledAttr = ($disabled ? 'disabled="disabled"' : '');
$labelTrans   = e(trans($label));

// TODO: ajax-complete reset val()

// -------------------------------------------- Output
echo <<<HTML
  <label>&nbsp;</label>
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
    onclick="$(this).siblings('input').val(1); $(this).trigger('change', 'add'); return false;">
    $labelTrans
  </button>
HTML;
?>
