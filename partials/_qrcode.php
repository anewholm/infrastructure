<?php
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Str;

$model  = (isset($formModel) ? $formModel : $record);
$config = (isset($formField) ? $formField->config : $column->config);
$size   = (isset($config['size']) && is_numeric($config['size'])) ? (int)$config['size'] : 96;

$class      = get_class($model);
$classParts = explode('\\', $class);
$author     = strtolower($classParts[0]);
$plugin     = strtolower($classParts[1]);
$modelName  = strtolower(Str::plural($classParts[3]));

$id     = $model->id();

// Get url from config
$baseUrl = config('app.url');
$url     = "{$baseUrl}/backend/{$author}/{$plugin}/{$modelName}/update/{$id}";
if (strstr($baseUrl, '://') === FALSE) throw new \Exception('app.url does not contain the protocol://');

print(QrCode::size($size)->generate($url));
?>
