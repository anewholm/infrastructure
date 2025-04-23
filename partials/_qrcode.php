<?php
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Str;

$model  = (isset($formModel) ? $formModel : $record);
$config = (isset($formField) ? $formField->config : $column->config);
$size   = (isset($config['size']) && is_numeric($config['size']) ? (int)$config['size'] : 96);
$action = (isset($config['action']) ? $config['action'] : 'current');

$class      = get_class($model);
$classParts = explode('\\', $class);
$author     = strtolower($classParts[0]);
$plugin     = strtolower($classParts[1]);
$modelName  = strtolower(Str::plural($classParts[3]));
$id         = $model->id;

// We default to the current URL that the QR code is displaying on
// this will therefore be different for preview or edit
switch ($action) {
    case 'edit':
        // We do not use the app.url because we may be running 127.0.0.1:8000 ./artisan serve
        // $schemeAndHttpHost = config('app.url');
        $schemeAndHttpHost = Request::schemeAndHttpHost();
        $url    = "{$schemeAndHttpHost}/backend/{$author}/{$plugin}/{$modelName}/update/{$id}";
        if (strstr($schemeAndHttpHost, '://') === FALSE) throw new \Exception('app.url does not contain the protocol://');
        break;
    case 'current':
    default:
        $url = Request::url();
        break;
}

print(QrCode::size($size)->generate($url));
?>
