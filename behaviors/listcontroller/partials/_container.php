<?php if ($toolbar): ?>
    <?= $toolbar->render() ?>
<?php endif ?>

<?php if ($filter): ?>
    <?= $filter->render() ?>
<?php endif ?>

<?php 
if ($list->model->isListEditable()) {
    print('<form id="list-editable-form">');
    print($list->render());
    print('</form>');
} else {
    print($list->render());
}