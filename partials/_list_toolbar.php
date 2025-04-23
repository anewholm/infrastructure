<div data-control="toolbar">
    <?php // TODO: This should maybe be the toolbar.extend event instead?
    $model      = $this->widget?->list?->model;
    $isReadOnly = ((property_exists($this, 'readOnly') && $this->readOnly) 
        || ($model && property_exists($model, 'readOnly') && $model->readOnly));
    if (!$isReadOnly): ?>
        <a
            href="<?= $this->controllerUrl('create'); ?>"
            class="btn btn-primary wn-icon-plus">
            <?= e(trans('backend::lang.form.create_title', ['name' => trans($model?->translateModelKey())])); ?>
        </a>

        <button
            class="btn btn-danger wn-icon-trash-o"
            disabled="disabled"
            onclick="$(this).data('request-data', { checked: $('.control-list').listWidget('getChecked') })"
            data-request="onDelete"
            data-request-confirm="<?= e(trans('backend::lang.list.delete_selected_confirm')); ?>"
            data-trigger-action="enable"
            data-trigger=".control-list input[type=checkbox]"
            data-trigger-condition="checked"
            data-request-success="$(this).prop('disabled', 'disabled')"
            data-stripe-load-indicator>
            <?= e(trans('backend::lang.list.delete_selected')); ?>
        </button>

        <?php if ($model && method_exists($model, 'isListEditable') && $model->isListEditable()): ?>
            <button
                class="btn btn-primary"
                disabled="disabled"
                onclick="$(this).data('request-data', { checked: $('.control-list').listWidget('getChecked') })"
                data-request="onListEditableSave"
                data-request-success="$(this).prop('disabled', 'disabled')"
                data-hotkey="ctrl+s, cmd+s"
                data-load-indicator="<?= e(trans('backend::lang.form.saving_name', ['name' => trans('{{ model_lang_key }}.label')])); ?>"
                class="btn btn-primary">
                <?= e(trans('backend::lang.form.save')); ?>
            </button>
        <?php endif ?>
    <?php endif ?>

    <a
        href="javascript:print()"
        class="btn btn-primary wn-icon-print">
        <?= e(trans('acorn::lang.models.general.print')); ?>
    </a>
</div>
