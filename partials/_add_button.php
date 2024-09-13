<?php
$action = 'add';
$label  = (isset($field->config['label']) ? $field->config['label'] : "backend::lang.form.$action");
?>
<label>&nbsp;</label>
<button class="btn btn-default form-button add-button" onclick="$(this).trigger('change', 'add'); return false;">
  <?= e(trans($label)) ?>
</button>
