<?php
// Model action functions and more
// TODO: The controller (FormController behaviour) can also have actionFunctions, from config_form.yaml
// RelationManagers loading popups need the relationObject instead
// _manage_form_footer_create.php for Popups calls here without checking
if      (isset($listRecord)) $model = $listRecord;
else if (isset($formModel))  $model = $formModel;
else if (isset($relationManageWidget)) $model = $relationManageWidget->model;
else if (isset($record))     $model = $record;
else throw new \Exception("Cannot ascertain model for actions operation");

$formMode        = (isset($formModel) && $model == $formModel);
$rowMode         = !$formMode;
$class           = get_class($model);
$user            = BackendAuth::user();
$canAdvanced     = $user->hasPermission('acorn.advanced');
$isAdvanced      = ($canAdvanced && Session::get('advanced'));
$ml              = System\Classes\MediaLibrary::instance();
$locale          = Lang::getLocale();

// This will be hidden by CSS if there are no entries
print('<ul class="action-functions">');

// --------------------------------- Video help
// MediaLibraryItem s
if (!$isAdvanced && $formMode) {
    $location = "HelpVideos\\$class";
    $mlis     = $ml->listFolderContents($location, 'title', 'video', TRUE);
    if (count($mlis)) {
        $useDropDown = (count($mlis) > 1);
        $useSearch   = (count($mlis) < 10 ? 'select-no-search' : '');
        if ($useDropDown) {
            $helpVideos = e(trans('acorn::lang.models.general.helpvideos'));
            print('<li class="video-help"><div class="form-group dropdown-field">');
            print("<i class='icon-video'></i>&nbsp;");
            print("<select name='video' class='select-and-url form-control $useSearch custom-select select2-hidden-accessible' autocomplete='off' tabindex='-1'>");
            print("<option value='' selected='selected'>$helpVideos...</option>");
        }
        foreach ($mlis as $mli) {
            $basePath   = preg_replace('/\.[a-zA-Z0-9]+$/', '', $mli->path);
            $baseName   = basename($basePath);
            // Translation of video names
            $label = Str::title(preg_replace('/[_-]+/', ' ', $baseName));
            if ($ml->exists("$basePath.yaml")) {
                $settings = Yaml::parse($ml->get("$basePath.yaml"));
                if (isset($settings['labels'][$locale])) {
                    $label = $settings['labels'][$locale];
                } else if (isset($settings['labels']['en'])) {
                    $label = $settings['labels']['en'];
                }
            }

            $url = $ml->url($mli->path);
            if ($useDropDown) print("<option value='$url'>$label</option>");
            else print("<li class='video-help'><i class='icon-video'></i>&nbsp;<a target='_blank' href='$url'>$label</a></li>");
        }
        if ($useDropDown) print('</select></div></li>');
    }
}

// --------------------------------- Advanced
if ($model->advanced && $canAdvanced && $formMode) {
    $toggle   = ($isAdvanced ? 0 : 1);
    $advanced = e(trans('acorn::lang.models.general.advanced'));
    $simple   = e(trans('acorn::lang.models.general.simple'));
    $title    = ($toggle ? $advanced : $simple);
    print("<li><a id='advanced' href='?advanced=$toggle'>$title</a></li>");
}

// --------------------------------- Action Functions
// Includes inherited 1to1 action functions
// This partial is only used from fields|columns.yaml
// Only _list_toolbar.php partial calls actionFunctions('list')
if ($model->exists && method_exists($model, 'actionFunctions')) {
    $actionFunctions = $model->actionFunctions('row'); 
    if ($rowMode) $actionFunctions = array_merge($actionFunctions, $model->actionFunctions('row-only')); 
    foreach ($actionFunctions as $fnName => &$definition) {
        // SECURITY: Action Function Premissions
        $hasPermission = TRUE;
        if (isset($definition['permissions'])) {
            $hasPermission = FALSE;
            if ($user) {
                foreach ($definition['permissions'] as $permission) {
                    if ($user->hasPermission($permission)) $hasPermission = TRUE;
                }
            }
        }
        
        $advancedFn = (isset($definition['advanced']) && $definition['advanced']);
        if ($hasPermission && (!$advancedFn || $isAdvanced)) {
            $labelEscaped    = e(trans($definition['label']));
            $modelArrayName  = $model->unqualifiedClassName();
            $dataRequestData = e(substr(json_encode(array(
                'name'       => $fnName, // SECURITY: We do not want to reveal the full function name
                'arrayname'  => $modelArrayName,
                'modelId'    => $definition['model_id'],
                'model'      => $definition['model']
            )), 1,-1));

            // Translateable comments
            $commentEscaped = (isset($definition['comment']) ? e(trans($definition['comment'])) : NULL);
            $tooltipHtml    = ($commentEscaped 
                ? "<div class='tooltip fade top'>
                    <div class='tooltip-arrow'></div>
                    <div class='tooltip-inner'>$commentEscaped</div>
                </div>"
                : NULL
            );

            // CSS class
            $fnNameParts = explode('_', $fnName);
            $fnNameSpec  = implode('-', array_slice($fnNameParts, 4));
            $cssClass    = $fnNameSpec;
            $cssClass   .= ($commentEscaped ? ' hover-indicator' : NULL);
            $cssClass   .= ($advancedFn     ? ' advanced'        : NULL);

            print(<<<HTML
                <li>
                    $tooltipHtml
                    <a
                        class="$cssClass"
                        data-control="popup"
                        data-request-data='$dataRequestData'
                        data-load-indicator='$commentEscaped...'
                        data-request-loading="loading-indicator"
                        data-request-success='acorn_popupComplete(context, textStatus, jqXHR);'
                        data-handler="onActionFunction"
                    >$labelEscaped</a>
                </li>
HTML
            );
        }
    }
}

// --------------------------------- Printing
// Can include conditions and permissions
if ($model->printable && $model->exists) {
    $canPrint = TRUE;
    if (is_array($model->printable)) {
        if (isset($model->printable['condition'])) {
            // TODO: condition: for printing
        }
        if (isset($model->printable['permissions'])) {
            $canPrint = FALSE;
            $permissions = (is_array($model->printable['permissions']) ? $model->printable['permissions'] : array($model->printable['permissions']));
            foreach ($model->printable['permissions'] as $permission => $config) {
                if ($user->hasPermission($permission)) $canPrint = TRUE;
            }
        }
    }
    if ($canPrint) {
        $print = e(trans('acorn::lang.models.general.print'));
        $previewLink = $model->controllerUrl('preview', $model->id);
        print("<li><a 
            target='_blank'
            href='$previewLink'
        >$print</a></li>");
    }
}

// --------------------------------- PDF ActionTemplates
// This takes a while so we don't want to do it in lists
if ($formMode && $model->exists) {
    // MediaLibraryItem s
    $location = "ActionTemplates\\$class";
    $mlis        = $ml->listFolderContents($location, 'title', 'document', TRUE);
    $useDropDown = (count($mlis) > 2);
    $useSearch   = (count($mlis) < 10 ? 'select-no-search' : '');
    $print       = e(trans('acorn::lang.models.general.print'));
    $dataLoadIndicator = e(trans('backend::lang.form.saving_name', ['name' => trans('{{ model_lang_key }}.label')]));;
    $dataRequestData = e(substr(json_encode(array(
        'model'      => $class,
        'model_id'    => $model->id,
    )), 1,-1));
    
    if ($useDropDown) {
        print(<<<HTML
            <li><form class="inline-block">
                <input 
                    data-control="popup" 
                    data-size="huge"
                    data-handler="onActionTemplate"
                    data-request-data="$dataRequestData"

                    type="submit" 
                    value="submit" 
                    class="hidden"
                />
                <div class="form-group dropdown-field select-and-go-clear" data-field-name="template">
                    <select 
                        name="template" 
                        class="form-control $useSearch custom-select select2-hidden-accessible" 
                        autocomplete="off" 
                        data-placeholder="$print" 
                        tabindex="-1" 
                    >
                        <option value="" selected="selected">$print</option>
HTML
        );
    }
    
    foreach ($mlis as $mli) {
        $pdfTemplate = NULL;
        try {
            $pdfTemplate = new \Acorn\PdfTemplate($mli->path);
        } catch (Exception $ex) {
            // The media cache is out-of-date
            // this can happen during imports
            // or on filesystem permission changes
        }
        if ($pdfTemplate) {
            $printName       = e($pdfTemplate->label()); // From FODT comment
            $dataRequestData = e(substr(json_encode(array(
                'template'   => $mli->path,
                'model'      => $class,
                'model_id'   => $model->id,
            )), 1,-1));

            if ($pdfTemplate->forContext($this->action)) {
                if ($useDropDown) {
                    print("<option value='$mli->path'>$printName</option>");
                } else              print(<<<HTML
                    <li><a
                        data-control="popup"
                        data-size="huge"
                        data-request-data='$dataRequestData'
                        data-handler="onActionTemplate"
                        data-load-indicator="$dataLoadIndicator"
                    >
                        $print $printName
                    </a></li>
HTML
                );
            }
        } else {
            if (env('APP_DEBUG')) {
                $printName   = e("Not found: $mli->path");
                if ($useDropDown) {
                    print("<option value='$mli->path'>$printName</option>");
                } else              print(<<<HTML
                    <li><button
                        disabled="disabled"
                        class="btn disabled">
                        $printName
                    </button></li>
HTML
                );
            }
        }
    } 
    if ($useDropDown) print('</select></div></form></li>');
}

// --------------------------------- QR scan
if ($formMode && $user->hasPermission('acorn.scan_qrcode')) {
    $popupQrScan = $this->makePartial('popup_qrscan', array(
        'actions'       => array('form-field-complete'),
    ));
    print("<li>$popupQrScan</li>");
}

// --------------------------------- Links
if (property_exists($model, 'actionLinks')) {
    foreach ($model->actionLinks as $name => $definition) {
        // TODO: Let the links decide their criteria for display
        // TODO: Use WinterCMS URL parameter replace
        $url         = str_replace(':id', $model->id, $definition['url']);
        $label       = (isset($definition['label'])       ? trans($definition['label']) : NULL);
        $type        = (isset($definition['type'])        ? $definition['type']   : NULL);
        $target      = (isset($definition['target'])      ? $definition['target'] : NULL);
        $icon        = (isset($definition['icon'])        ? $definition['icon']   : NULL);
        $permissions = (isset($definition['permissions']) ? $definition['permissions'] : array());
        $iconHTML    = ($icon ? "<i class='icon-$icon'></i>": NULL);
        if (!is_array($permissions)) $permissions = array($permissions);

        // Permissions
        $hasPermission = TRUE;
        foreach ($permissions as $permission) {
            if (!$user->hasPermission($permission)) {
                $hasPermission = FALSE;
                break;
            }
        }
        
        // Context
        $show = FALSE;
        switch ($type) {
            case 'list':    
                $show = (!$formMode); 
                break;
            case 'create':  
                $show = ($formMode && !$model->exists); 
                break;
            case 'all':
                $show = TRUE;
                break;
            case 'update':  
            default:
                $show = ($formMode && $model->exists);
        }

        if ($show && $hasPermission) {
            $labelEscaped = e($label);
            $urlEscaped   = e($url);
            print(<<<HTML
                <li>
                    <a target='$target' title='$labelEscaped' href='$urlEscaped'>
                        $labelEscaped
                        $iconHTML
                    </a>
                </li>
HTML
            );
        }
    }
}

print('</ul>');
?>
