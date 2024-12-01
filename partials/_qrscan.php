<?php
// actions: array of actions to carry out, in order
// formFieldName: Name of the input to write the scanned value into
if (!isset($formFieldName) || !$formFieldName) $formFieldName = '_qrscan';
// Remove the data array name
// Receipt[_qrcode_scan] => _qrcode_scan
if (!isset($dataFieldName)) $dataFieldName = preg_replace('/^.*\[|\]$/', '', $formFieldName);

// Popup relevant:
//   formSelector: CSS selector of the form to update
//   listSelector: CSS selector of the list to search
if (!isset($formSelector)) $formSelector = NULL;
if (!isset($listSelector)) $listSelector = NULL;
?>

<div id="my-qr-reader"
    actions="<?= e(implode(',', $actions)); ?>"
    form-selector="<?= e($formSelector); ?>"
    list-selector="<?= e($listSelector); ?>"
></div>
<input id="_qrscan_input" class="form-control" data-field-name="<?= e($dataFieldName) ?>" type="hidden" name="<?= e($formFieldName) ?>" value=""></input>

