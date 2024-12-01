<?php
// The popup will update a form with the scanned value after scanning
// A form CSS selector is neccessary
// of the popup JavaScript will update the normal in-page main form:
//   #layout-body form

// Actions can be sent as a var for this partial
// or in a $formField->config
if (!isset($actions)) {
    if (isset($formField) && isset($formField->config['actions'])) $actions = $formField->config['actions'];
    else $actions = ['find-in-list', 'redirect'];
}
if (is_string($actions)) $actions = array($actions);

// The CSS selector for the form to update, or the list to search and highlight
// For form-field-complete, it will default to #layout-body form
// For find-in-list, it will default to #Lists
if (!isset($formSelector)) {
    if (isset($formField) && isset($formField->config['form'])) $formSelector = $formField->config['form'];
    else $formSelector = '#layout-body form';
}
if (!isset($listSelector)) {
    if (isset($formField) && isset($formField->config['list'])) $listSelector = $formField->config['list'];
    else $listSelector = '#Lists';
}

// This is the name of the <input> that will recieve the scanned value
// Important if it is going to be copied in to a form for form-field-complete
if (!isset($formFieldName)) {
    if (isset($formField)) {
        $arrayName     = $formField->arrayName;
        $formFieldName = "${arrayName}[$formField->fieldName]";
    } else $formFieldName = '_qrscan';
}

$dataRequestData = array(
    'formFieldName' => $formFieldName,
    'actions'       => $actions,
    'formSelector'  => $formSelector,
    'listSelector'  => $listSelector,
);
$dataRequestDataEscaped = e(substr(json_encode($dataRequestData), 1, -1));
?>
<div id="popup-qrscan" class="toolbar-item">
    <i class="icon-qrcode"
        data-handler="onLoadQrScanPopup"
        data-request-data='<?= $dataRequestDataEscaped; ?>'
        data-control="popup"
        data-size="small">
    </i>
</div>
