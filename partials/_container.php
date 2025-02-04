<?php
// partial overridden
// data-request-data fields added for _parent_model & id
?>
<div
    id="<?= $this->relationGetId() ?>"
    data-request-data="
        _relation_field: '<?= $relationField ?>',
        _relation_extra_config: '<?= e(base64_encode(json_encode($relationExtraConfig))) ?>',
        _parent_model: '<?= str_replace('\\', '\\\\', e(get_class($formModel))); ?>',
        _parent_model_id: '<?= $formModel->id(); ?>'
        "
    class="relation-behavior relation-view-<?= $relationViewMode ?>">

    <?php 
    // Call altered to pass through $relationField
    // thus maintaining field knowledge through the rendering process
    // and in the relationRenderView() call below
    // This is regarded as a bug in Winter
    if ($toolbar = $this->relationRenderToolbar($relationField)): ?>
        <!-- Relation Toolbar -->
        <div id="<?= $this->relationGetId('toolbar') ?>" class="relation-toolbar">
            <?= $toolbar ?>
        </div>
    <?php endif ?>

    <!-- Relation View -->
    <div id="<?= $this->relationGetId('view') ?>" class="relation-manager">
        <?= $this->relationRenderView($relationField) ?>
    </div>

</div>
