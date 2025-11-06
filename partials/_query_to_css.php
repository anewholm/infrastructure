<style>
<?php
$model  = (isset($formModel) ? $formModel : $record);
$config = (isset($formField) ? $formField->config : $column->config);

if (isset($config['whitelist'])) {
  $whitelist = $config['whitelist'];
  foreach ($whitelist as $name) {
    if ($action = get($name)) {
      switch ($action) {
        case 'hide':
          print("body div .$name {display:none!important; visibility:none!important;}");
          break;
        case 'show':
        default:
          print("body div .$name {display:inherit!important; visibility:inherit!important;}");
      }
    } else {
      // TODO: When class not present? hide?
    }
  }
}
?>
</style>
