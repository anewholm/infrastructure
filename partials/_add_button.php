<?php
$action   = 'add';
$label    = (isset($field->config['label']) ? $field->config['label'] : "backend::lang.form.$action");
$disabled = (isset($field->config['disabled']) ? $field->config['disabled'] : FALSE);

// Escaping
$disabledAttr = ($disabled ? 'disabled="disabled"' : '');
$labelTrans   = e(trans($label));

// -------------------------------------------- Output
echo <<<HTML
  <label>&nbsp;</label>
  <button
    $disabledAttr
    class="btn btn-default form-button add-button"
    onclick="$(this).trigger('change', 'add'); return false;">
    $labelTrans
  </button>
HTML;
?>
