<?php 
if ($this->action == 'manage') {
    $action = $record->acornassociated_infrastructure ? 'set-acornassociated_infrastructure' : 'unset-acornassociated_infrastructure'; ?>
    <label class="custom-switch" data-check="wn-disable-<?= $record->id ?>" style="margin-bottom:0">
        <input data-request="onBulkAction"
            data-request-data="action: '<?= $action ?>', checked: [<?= $record->id ?>]"
            data-request-update="list_manage_toolbar: '#plugin-toolbar'"
            type="checkbox"
            name="disable_<?= $record->id ?>"
            value="<?= $record->acornassociated_infrastructure ?>"
            <?php if ($record->acornassociated_infrastructure): ?>
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