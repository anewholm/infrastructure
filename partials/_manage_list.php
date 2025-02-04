<?php
// partial overridden
// data-request-data fields added for _parent_model & id
?>
<div id="relationManagePopup" data-request-data="
        _relation_field: '<?= $relationField ?>', _relation_mode: 'list',
        _parent_model: '<?= str_replace('\\', '\\\\', e(get_class($formModel))); ?>',
        _parent_model_id: '<?= $formModel->id(); ?>'
    ">
    <?= Form::open() ?>
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="popup">&times;</button>
            <h4 class="modal-title"><?= e(trans($relationManageTitle, [
                'name' => trans($relationLabel)
            ])) ?></h4>
        </div>

        <div class="list-flush">
            <?php if ($relationSearchWidget): ?>
                <?= $relationSearchWidget->render() ?>
            <?php endif ?>
            <?php if ($relationManageFilterWidget): ?>
                <?= $relationManageFilterWidget->render() ?>
            <?php endif 
            // Call altered to pass through $relationField
            // thus maintaining field knowledge through the rendering process
            // and in the relationRenderView() call below
            // This is regarded as a bug in Winter
            ?>
            <?= $relationManageWidget->render($relationField) ?>
        </div>

        <div class="modal-footer">
            <?= $this->relationMakePartial('manage_list_footer') ?>
        </div>
    <?= Form::close() ?>
</div>
