<?php
$user_read    = ($value & 1   ? 'checked="1"' : '');
$user_write   = ($value & 2   ? 'checked="1"' : '');
$user_delete  = ($value & 4   ? 'checked="1"' : '');

$group_read   = ($value & 8   ? 'checked="1"' : '');
$group_write  = ($value & 16  ? 'checked="1"' : '');
$group_delete = ($value & 32  ? 'checked="1"' : '');

$other_read   = ($value & 64  ? 'checked="1"' : '');
$other_write  = ($value & 128 ? 'checked="1"' : '');
$other_delete = ($value & 256 ? 'checked="1"' : '');

$name = '';
if (isset($field)) {
    $name = $field->fieldName;
    if ($field->arrayName) $name = "$field->arrayName[$name]";
    $name .= '[]';
}

$read   = e(trans('acorn.calendar::lang.models.general.security.read'));
$write  = e(trans('acorn.calendar::lang.models.general.security.write'));
$delete = e(trans('acorn.calendar::lang.models.general.security.delete'));

$user   = e(trans('acorn.calendar::lang.models.general.security.user'));
$group  = e(trans('acorn.calendar::lang.models.general.security.group'));
$other  = e(trans('acorn.calendar::lang.models.general.security.other'));
?>

<div class="permissions-field">
    <table class="permissions-table">
        <tr><th><?= $user ?></th>
            <td><input name="<?= $name ?>" id="user_read"    value="1"   <?= $user_read ?>    type="checkbox"></input> <label for="user_read"><?= $read ?></label></td>
            <td><input name="<?= $name ?>" id="user_write"   value="2"   <?= $user_write ?>   type="checkbox"></input> <label for="user_write"><?= $write ?></label></td>
            <td><input name="<?= $name ?>" id="user_delete"  value="4"   <?= $user_delete ?>  type="checkbox"></input> <label for="user_delete"><?= $delete ?></label></td>
        </tr>
        <tr><th><?= $group ?></th>
            <td><input name="<?= $name ?>" id="group_read"   value="8"   <?= $group_read ?>   type="checkbox"></input> <label for="group_read"><?= $read ?></label></td>
            <td><input name="<?= $name ?>" id="group_write"  value="16"  <?= $group_write ?>  type="checkbox"></input> <label for="group_write"><?= $write ?></label></td>
            <td><input name="<?= $name ?>" id="group_delete" value="32"  <?= $group_delete ?> type="checkbox"></input> <label for="group_delete"><?= $delete ?></label></td>
        </tr>
        <tr><th><?= $other ?></th>
            <td><input name="<?= $name ?>" id="other_read"   value="64"  <?= $other_read ?>   type="checkbox"></input> <label for="other_read"><?= $read ?></label></td>
            <td><input name="<?= $name ?>" id="other_write"  value="128" <?= $other_write ?>  type="checkbox"></input> <label for="other_write"><?= $write ?></label></td>
            <td><input name="<?= $name ?>" id="other_delete" value="256" <?= $other_delete ?> type="checkbox"></input> <label for="other_delete"><?= $delete ?></label></td>
        </tr>
    </table>
    <span class="permissions-number"><?= $value ?></span>
</div>