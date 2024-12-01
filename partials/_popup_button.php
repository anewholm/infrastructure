<?php
// Auto field names should be in the form _<action>_<singular unqualified controller class>
$fieldName      = &$field->fieldName;
$arrayName      = &$field->arrayName;
$fieldNameParts = explode('_', trim($fieldName, '_'));
$fqFieldName    = "${arrayName}[${fieldName}]";

// -------------------------------------------- Configuration of route
if (isset($field->config['controller'])) {
  $unqualifiedControllerClass = $field->config['controller'];
} else {
  if (count($fieldNameParts) < 2) throw new \Exception('Controller not specified for _create_button');
  $unqualifiedControllerLowerSingle = $fieldNameParts[1]; // invoice
  $unqualifiedControllerClass       = Str::studly(Str::plural($unqualifiedControllerLowerSingle)); // Invoices
}
$controller = $this->qualifyClassName($unqualifiedControllerClass);

$action = 'create';
if      (isset($field->config['action'])) $action = $field->config['action'];
else if ($fieldNameParts[0])              $action = $fieldNameParts[0];

$hasCustomLabel = isset($field->config['label']);
$label    = ($hasCustomLabel
    ? $field->config['label']
    : ($action == 'create' ? "backend::lang.form.$action" : 'backend::lang.form.save')
);
$disabled = (isset($field->config['disabled']) ? $field->config['disabled'] : FALSE);

$route      = (isset($field->config['route'])       ? $field->config['route']       : "$controller@$action");
$handler    = (isset($field->config['handler'])     ? $field->config['handler']     : 'onPopupRoute');
$popupClass = (isset($field->config['popup-class']) ? $field->config['popup-class'] : "popup-$action");
$dataRequestData = array(
    'route' => $route,
    'fieldName' => $fieldName
);
// Pass through any field configuration requests from the config
if (isset($field->config['fields'])) {
    foreach ($field->config['fields'] as $fieldName => $fieldConfig) {
        $dataRequestData["Fields[$fieldName]"] = $fieldConfig;
    }
}

// Escaping
$disabledAttr = ($disabled ? 'disabled="disabled"' : '');
$labelTrans   = e(trans($label));
$dataRequestDataString = substr(json_encode($dataRequestData), 1, -1);
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
    class="btn btn-default form-button $popupClass"
    data-handler="$handler"
    data-request-data='$dataRequestDataString'
    data-control="popup"
  >$labelTrans</button>
HTML;
?>
