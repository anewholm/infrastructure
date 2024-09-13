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

if (isset($field->config['action'])) {
  $action = $field->config['action'];
} else if ($fieldNameParts[0]) {
  $action = $fieldNameParts[0];
} else {
  $action = 'create';
}

$label = (isset($field->config['label']) ? $field->config['label'] : "backend::lang.form.$action");

$route      = (isset($field->config['route'])       ? $field->config['route']       : "$controller@$action");
$handler    = (isset($field->config['handler'])     ? $field->config['handler']     : 'onPopupRoute');
$popupClass = (isset($field->config['popup-class']) ? $field->config['popup-class'] : "popup-$action");

// -------------------------------------------- Output
?>
<label>&nbsp;</label>
<input type="hidden" name="<?php print($fqFieldName); ?>" value="" class="form-control" data-disposable="data-disposable"></input>
<button
  class="btn btn-default form-button <?php print($popupClass); ?>"
  data-handler="<?php print($handler); ?>"
  data-request-data="
    route:'<?php print(str_replace('\\', '\\\\', $route)); ?>',
    fieldName:'<?php print($fieldName); ?>'
  "
  data-control="popup"
>
  <?= e(trans($label)) ?>
</button>
