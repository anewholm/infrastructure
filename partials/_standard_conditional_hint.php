<?php
$config = $field->config;

// Self-policing hints
// SQL Conditional display
$display = TRUE;
if (isset($config['conditions'])) {
    $models  = $formModel::where('id', $formModel->id)->whereRaw($config['conditions']);
    $display = $models->count();
}

if ($display) {
    if (isset($config['managedPartial'])) {
        print($this->makePartial($config['managedPartial']));
    } else {
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
        <i class="icon-<?= $level ?>"></i>
        <h3><?= $titleEscaped ?></h3>
        <p class="content"><?= $contentEscaped ?></p>
    </div>
</div>
<?php }} ?>