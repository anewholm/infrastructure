<?php 
if ($this->action == 'manage') {
    $action = $record->acorn_infrastructure ? 'set-acorn_infrastructure' : 'unset-acorn_infrastructure'; ?>
    <label class="custom-switch" data-check="wn-disable-<?= $record->id ?>" style="margin-bottom:0">
        <input data-request="onBulkAction"
            data-request-data="action: '<?= $action ?>', checked: [<?= $record->id ?>]"
            data-request-update="list_manage_toolbar: '#plugin-toolbar'"
            type="checkbox"
            name="disable_<?= $record->id ?>"
            value="<?= $record->acorn_infrastructure ?>"
            <?php if ($record->acorn_infrastructure): ?>
                checked="checked"
            <?php endif ?>
            data-stripe-load-indicator
        >

        <span>
            <span><?= e(trans('system::lang.plugins.check_yes')) ?></span>
            <span><?= e(trans('system::lang.plugins.check_no')) ?></span>
        </span>
        <a class="slide-button"></a>
    </label>
<?php } else {
    print($value ? trans('backend::lang.list.column_switch_true') : '');
}
?>