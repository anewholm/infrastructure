<?php
$config = $field->config;

// SQL Conditional display
$display = (isset($config['conditions'])
    ? (bool) ($formModel->whereRaw($config['conditions'])->count())
    : TRUE
);
if ($display):
    // TODO: Hide by key
    // TODO: Translate content
    // TODO: Field & tab appearance
    $class    = get_class($formModel);
    $title    = (isset($config['labels']['en'])  ? $config['labels']['en']  : NULL);
    $content  = (isset($config['content']['en']) ? $config['content']['en'] : NULL);
    $level    = (isset($config['level'])         ? $config['level']   : 'warning');
    $partial  = (isset($config['partial'])       ? $config['partial'] : 'standard_hint');
    $key      = "$class::$field->fieldName";
    $dataRequestData = e(substr(json_encode(array(
        'name'   => $key,
    )), 1,-1));
    $titleEscaped   = e(trans($title));
    $contentEscaped = trans($content);
    if (!isset($config['contentHtml']) || !$config['contentHtml']) $contentEscaped = e($contentEscaped);
?>
<div class="callout fade in callout-<?= $level ?>">
    <div class="header">
        <button 
            type="button" 
            class="close"  
            data-request="onHideBackendHint" 
            data-request-data="<?= $dataRequestData ?>" 
            data-dismiss="callout" 
            aria-hidden="true">×</button>
        <i class="icon-warning"></i>
        <h3><?= $titleEscaped ?></h3>
        <p class="content"><?= $contentEscaped ?></p>
    </div>
</div>
<?php endif ?>