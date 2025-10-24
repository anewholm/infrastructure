<div class="report-widget widget-globalscopespreview theme-blue">
<?php if (!isset($error)): ?>
    <?php foreach ($scopes as $setting => $model): 
        // We need the standard controller
        // for the config_*.yaml form config
        $formController = NULL;
        $pageTitle      = NULL;
        if (method_exists($model, 'controllerFullyQualifiedClass')) {
            $standardControllerFQN = $model->controllerFullyQualifiedClass();
            if (class_exists($standardControllerFQN)) {
                $controller     = new $standardControllerFQN;
                $formController = new \Acorn\Behaviors\FormController($controller);
                // This will initForm() with the Model
                $formController->preview($model->id);
                $pageTitle = $controller->pageTitle;
                $updateUrl = $controller->actionUrl('update', $model->id);
                $view      = trans('acorn::lang.dashboard.globalscopespreview.view') . " $model->name";
            }
        }
    ?>
        <h3><?= e($pageTitle) ?>&nbsp;<?= e($model->name) ?></h3>
        <div class="welcome-container">
            <div class="backend-content">
                <?php if ($formController) {
                    print("<a href='$updateUrl'>$view</a>");
                    print($formController->formRender());
                } ?>
            </div>
        </div>
    <?php endforeach ?>
<?php else: ?>
    <div class="callout callout-warning">
        <div class="content"><?= e($error) ?></div>
    </div>
<?php endif ?>
</div>
