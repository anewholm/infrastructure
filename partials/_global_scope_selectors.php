<?php
use Acorn\Scopes\GlobalChainScope;

// Only works for lists at the moment
if (isset($this->controller->widget->list->model)) {
    $listModel          = $this->controller->widget->list->model;
    $globalScopeClasses = GlobalChainScope::globalScopeClasses($listModel);
    
    if ($globalScopeClasses) {
        // TODO: Translation
        foreach ($globalScopeClasses as $classFQN => $model) {
            $classParts  = explode('\\', $classFQN);
            $class       = end($classParts);
            $settingName = "$classFQN::globalScope";
            $setting     = Session::get($settingName);
            $none        = "No $class";
            
            // <select>or
            print(<<<HTML
                <form><select name="$settingName"
                    data-request="onGlobalScopeChange"
                >
HTML
            );
            print("<option value=''>$none</option>");
            foreach ($classFQN::withoutGlobalScopes()->get() as $model) {
                $selected = ($setting == $model->id ? 'selected' : '');
                print("<option value='$model->id' $selected>$model->name</option>");
            }
            print("</select></form>");
        }
    }
}