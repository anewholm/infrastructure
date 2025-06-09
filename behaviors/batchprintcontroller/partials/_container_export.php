<div class="export-behavior">

    <!-- TODO: Dirty hack to hide export form -->
    <div style="display:none">
        <?= $exportFormatFormWidget->render() ?>
    </div>

    <?php if ($exportOptionsFormWidget): ?>
        <?= $exportOptionsFormWidget->render() ?>
    <?php endif ?>

</div>
