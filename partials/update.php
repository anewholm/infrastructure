<?php 
// If $this->fatalError, vars will not be available
$modelLabelKey     = (isset($formModel) ? $formModel->translateModelKey() : '');
$modelsLabelKey    = (isset($formModel) ? $formModel->translateModelKey('label_plural') : '');
$controllerListUrl = $this->actionUrl('');
$count             = NULL; // (isset($formModel) ? $formModel::menuItemCount() : NULL);

Block::put('breadcrumb') ?>
    <ul>
        <li>
            <a href="<?= $controllerListUrl ?>"><?= e($modelsLabelKey); ?></a>
            <?php if (!is_null($count)) print("<span class='counter'>$count</span>"); ?>
        </li>
        <li><?= e($this->pageTitle) ?></li>
    </ul>
<?php Block::endPut() ?>

<?php if (!$this->fatalError): ?>

    <?php Block::put('form-contents') ?>

        <div class="layout-row">
            <?= $this->makePartial('actions'); ?>
            <?= $this->formRender() ?>
        </div>

        <div class="form-buttons">
            <div class="loading-indicator-container">
                <button
                    type="button"
                    data-request="onSave"
                    data-request-data="redirect:0"
                    data-hotkey="ctrl+s, cmd+s"
                    data-load-indicator="<?= e(trans('backend::lang.form.saving_name', ['name' => $modelLabelKey])); ?>"
                    class="btn btn-primary">
                    <?= e(trans('backend::lang.form.save')); ?>
                </button>
                <button
                    type="button"
                    data-request="onSave"
                    data-request-data="close:1"
                    data-hotkey="ctrl+enter, cmd+enter"
                    data-load-indicator="<?= e(trans('backend::lang.form.saving_name', ['name' => $modelLabelKey])); ?>"
                    class="btn btn-default">
                    <?= e(trans('backend::lang.form.save_and_close')); ?>
                </button>
                <button
                    type="button"
                    class="wn-icon-trash-o btn-icon danger pull-right"
                    data-request="onDelete"
                    data-load-indicator="<?= e(trans('backend::lang.form.deleting_name', ['name' => $modelLabelKey])); ?>"
                    data-request-confirm="<?= e(trans('backend::lang.form.confirm_delete')); ?>">
                </button>
                <span class="btn-text">
                    or <a href="<?= $controllerListUrl ?>"><?= e(trans('backend::lang.form.cancel')); ?></a>
                </span>
            </div>
        </div>
    <?php Block::endPut() ?>

    <?php Block::put('form-sidebar') ?>
        <div data-control="formwidget" data-refresh-handler="form::onRefresh" class="form-widget form-elements layout" role="form" id="Form" data-disposable="">
            <div class="hide-tabs"><?= $this->formTertiaryTabs() ?></div>
        </div>
    <?php Block::endPut() ?>

    <?php Block::put('body') ?>
        <?= Form::open(['class'=>'layout stretch']) ?>
            <?= $this->makeLayout('form-with-sidebar') ?>
        <?= Form::close() ?>
    <?php Block::endPut() ?>

<?php else: ?>

    <p class="flash-message static error"><?= e($this->fatalError) ?></p>
    <p><a href="<?= $controllerListUrl ?>" class="btn btn-default"><?= e(trans('backend::lang.form.return_to_list')); ?></a></p>

<?php endif ?>
