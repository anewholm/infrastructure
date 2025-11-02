<?php 
// If $this->fatalError, vars will not be available
$modelLabelKey     = (isset($formModel) ? $formModel->translateModelKey() : '');
$modelsLabelKey    = (isset($formModel) ? $formModel->translateModelKey('label_plural') : '');
$count             = (isset($formModel) ? $formModel::count() : NULL);
$controllerListUri = new \GuzzleHttp\Psr7\Uri($this->actionUrl(''));
$controllerListUrl = (string) $controllerListUri;

// We want to go back to the correct place
// if it was the same domain and the backend
if ($backToReferrer = get('back-to-referrer')) {
    $referrerUri             = new \GuzzleHttp\Psr7\Uri(request()->headers->get('referer'));
    $referrerPathParts       = explode('/', trim($referrerUri->getPath(), '/'));
    $controllerListPathParts = explode('/', trim($controllerListUri->getPath(), '/'));
    if (   $referrerUri->getHost()   == $controllerListUri->getHost()
        && $referrerUri->getScheme() == $controllerListUri->getScheme()
        && isset($referrerPathParts[2])
        && isset($controllerListPathParts[2])
        && $referrerPathParts[0]       == 'backend'
        && $controllerListPathParts[0] == 'backend'
        && end($referrerPathParts) != end($controllerListPathParts)
    ) {
        // This includes the query string also
        // Session will apply the same filters
        $controllerListUrl = (string) $referrerUri;
        $modelsLabelKey    = trans($backToReferrer);
    }
}

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
            <?= $this->formRender() ?>
        </div>

        <div class="form-buttons">
            <div class="loading-indicator-container">
                <button
                    type="button"
                    data-request="onSave"
                    data-hotkey="ctrl+s, cmd+s"
                    data-load-indicator="<?= e(trans('backend::lang.form.creating_name', ['name' => $modelLabelKey])); ?>"
                    class="btn btn-primary">
                    <?= e(trans('backend::lang.form.create')); ?>
                </button>
                <button
                    type="button"
                    data-request="onSaveAndAddNew"
                    data-load-indicator="<?= e(trans('backend::lang.form.creating_name', ['name' => $modelLabelKey])); ?>"
                    class="btn btn-default">
                    <?= e(trans('acorn::lang.models.general.create_and_add_new')); ?>
                </button>
                <?php
                    // Would access a custom redirect in the form config    
                    // 'action' => 'course-planner', 
                    $dataRequestData = array(
                        'close'    => 1,
                        'redirect' => $controllerListUrl,
                    );
                    $dataRequestDataString = e(substr(json_encode($dataRequestData), 1, -1));
                ?>
                <button
                    type="button"
                    data-request="onSave"
                    data-request-data="<?= $dataRequestDataString ?>"
                    data-hotkey="ctrl+enter, cmd+enter"
                    data-load-indicator="<?= e(trans('backend::lang.form.creating_name', ['name' => $modelLabelKey])); ?>"
                    class="btn btn-default">
                    <?= e(trans('backend::lang.form.create_and_close')); ?>
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
        <!-- <ul> May contain forms -->
        <?= $this->makePartial('actions'); ?>
        
        <?= Form::open(['class'=>'layout stretch']) ?>
            <?= $this->makeLayout('form-with-sidebar') ?>
        <?= Form::close() ?>
    <?php Block::endPut() ?>

<?php else: ?>

    <p class="flash-message static error"><?= e($this->fatalError) ?></p>
    <p><a href="<?= $controllerListUrl ?>" class="btn btn-default"><?= e(trans('backend::lang.form.return_to_list')); ?></a></p>

<?php endif ?>
