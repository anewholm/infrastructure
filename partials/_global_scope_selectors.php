<?php
use Acorn\Scopes\GlobalChainScope;

// Only works for lists at the moment
if (isset($this->controller->widget->list->model)) {
    $listModel          = $this->controller->widget->list->model;
    $globalScopeClasses = GlobalChainScope::globalScopeClasses($listModel);
    
    if ($globalScopeClasses) {
        // TODO: Translation
        $none = 'None';
        foreach ($globalScopeClasses as $model) {
            $class       = get_class($model);
            $settingName = "$class::globalScope";
            $setting     = Session::get($settingName);

            // <select>or
            print(<<<HTML
                <form><select name="$settingName"
                    data-request="onGlobalScopeChange"
                >
HTML
            );
            print("<option value=''>$none</option>");
            foreach ($class::withoutGlobalScopes()->get() as $model) {
                $selected = ($setting == $model->id ? 'selected' : '');
                print("<option value='$model->id' $selected>$model->name</option>");
            }
            print("</select></form>");
        }
    }
}