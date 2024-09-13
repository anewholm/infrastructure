<?php
use SimpleSoftwareIO\QrCode\Facades\QrCode;


$model  = (isset($formModel) ? $formModel : $record);
$config = (isset($formField) ? $formField->config : $column->config);
$size   = (isset($config['size']) ? $config['size'] : 96);
$class  = get_class($model);
$id     = $model->id();
$info   = explode('\\', $class);
$data   = [
    'author' => $info[0],
    'plugin' => $info[1],
    'model'  => $info[3],
    'id'     => $id,
];
print(QrCode::size($size)->generate(json_encode($data)));
?>
