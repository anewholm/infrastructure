<?php
use Acorn\Scopes\GlobalChainScope;
use Backend\Facades\BackendAuth;

$relationController = $this->controller->getClassExtension('\Acorn\Behaviors\RelationController');
$isRelationManager  = ($relationController && $relationController->relationModel);

// Only works for lists at the moment
if (isset($this->controller->widget->list->model) && !$isRelationManager) {
    $listModel          = $this->controller->widget->list->model;
    $globalScopeClasses = GlobalChainScope::endGlobalScopeClasses($listModel);
    $user = BackendAuth::user();
    
    if ($globalScopeClasses) {
        // TODO: Translation
        foreach ($globalScopeClasses as $classFQN => $model) {
            $settingName = GlobalChainScope::settingNameFor($model);
            $setting     = GlobalChainScope::getSettingFor($model);
            $classLabel  = trans($model->translationDomainModel());
            $noLabel     = trans('system::lang.plugins.check_no');
            $noneLabel   = e("$noLabel $classLabel");

            // TODO: Permissions check globalscope.view & globalscope.change
            // e.g. acorn.university.academicyears.globalscope.view|change
            $hasPermissions = (method_exists($model, 'permissionFQN'));
            $canView   = (!$hasPermissions || $user->hasPermission($model->permissionFQN(['globalscope', 'view'])));
            $canChange = (!$hasPermissions || $user->hasPermission($model->permissionFQN(['globalscope', 'change'])));
            
            if ($canView) {
                // <select>or
                $disabled = ($canChange ? '' : 'disabled="disabled"');
                print(<<<HTML
                    <form
                        data-request='onGlobalScopeChange'
                    >
                        <input type="submit" value="submit" class="hidden"/>
                        <div class="form-group dropdown-field is-required select-and-go" data-field-name="$settingName">
                            <select 
                                id="$settingName" 
                                $disabled
                                autocomplete="off" 
                                name="$settingName" 
                                class="form-control custom-select select2-hidden-accessible"
                                required="required"
                            >
                                <option value="0">$noneLabel</option>
HTML
                );
                foreach ($classFQN::withoutGlobalScopes()->get() as $model) {
                    $selected = ($setting == $model->id ? 'selected="1"' : '');
                    print("<option value='$model->id' $selected>$model->name</option>");
                }
                print("</select></div></form>");
            }
        }
    }
}