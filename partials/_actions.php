<?php
// TODO: The controller (FormController behaviour) can also have actionFunctions, from config_form.yaml
// Model action functions
// RelationManagers loading popups need the relationObject instead
if      (isset($listRecord)) $model = $listRecord;
else if (isset($formModel))  $model = $formModel;
else if (isset($relationManageWidget)) $model = $relationManageWidget->model;
else if (isset($record))     $model = $record;
else throw new \Exception("Cannot ascertain model for actions operation");

// _manage_form_footer_create.php for Popups calls here without checking
if (method_exists($model, 'actionFunctions')) {
    $formMode        = (isset($formModel) && $model == $formModel);
    $modelArrayName  = $model->unqualifiedClassName();
    $actionFunctions = $model->actionFunctions('row'); // Includes inherited 1to1 action functions
    $user            = BackendAuth::user();
    $advancedDisplay = ($user->hasPermission('acorn.advanced') && Session::get('advanced'));

    if (count($actionFunctions) || $formMode || $model->printable) {
        print('<ul class="action-functions">');

        // --------------------------------- Advanced
        if ($model->advanced 
            && $this->action == 'update'
            && $user->hasPermission('acorn.advanced')
        ) {
            $toggle   = (Session::get('advanced') ? 0 : 1);
            $advanced = e(trans('acorn::lang.models.general.advanced'));
            $simple   = e(trans('acorn::lang.models.general.simple'));
            $title    = ($toggle ? $advanced : $simple);
            print("<li><a id='advanced' href='?advanced=$toggle'>$title</a></li>");
        }

        // --------------------------------- Actions
        if ($model->exists) {
            foreach ($actionFunctions as $fnName => &$definition) {
                $enDevLabel      = e(trans($definition['label']));
                $dataRequestData = e(substr(json_encode(array(
                    'name'       => $fnName, // SECURITY: We do not want to reveal the full function name
                    'arrayname'  => $modelArrayName,
                    'modelId'    => $definition['model_id'],
                    'model'      => $definition['model']
                )), 1,-1));

                // TODO: Translateable comments
                $title      = (isset($definition['comment']['en']) ? $definition['comment']['en'] : NULL);
                $advancedFn = (isset($definition['advanced']) && $definition['advanced']);
                $tooltip    = ($title 
                    ? "<div class='tooltip fade top'>
                        <div class='tooltip-arrow'></div>
                        <div class='tooltip-inner'>$title</div>
                    </div>"
                    : NULL
                );

                $class  = preg_replace('/^fn_[^_]+_[^_]+_action_/', '', $fnName);
                $class .= ($title      ? ' hover-indicator' : NULL);
                $class .= ($advancedFn ? ' advanced'        : NULL);

                if (!$advancedFn || $advancedDisplay) {
                    print(<<<HTML
                        <li>
                            $tooltip
                            <a
                                class="$class"
                                data-control="popup"
                                data-request-data='$dataRequestData'
                                data-load-indicator='$title...'
                                data-request-loading="loading-indicator"
                                data-request-success='acorn_popupComplete(context, textStatus, jqXHR);'
                                data-handler="onActionFunction"
                            >$enDevLabel</a>
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
            $ml       = System\Classes\MediaLibrary::instance();
            $class    = get_class($model);
            $location = "ActionTemplates\\$class";
            // MediaLibraryItem s
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
                    <li><form class="inline-block"
                    >
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
                if ($mli->getFileType() == "document") {
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
        if ($formMode && $model->exists && property_exists($model, 'actionLinks')) {
            foreach ($model->actionLinks as $name => $definition) {
                $title       = (isset($definition['title']) ? trans($definition['title']) : NULL);
                $url         = str_replace(':id', $model->id, $definition['url']);
                $target      = (isset($definition['target']) ? $definition['target'] : NULL);
                $icon        = (isset($definition['icon']) ? $definition['icon'] : NULL);
                $iconHTML    = ($icon ? "<i class='icon-$icon'></i>": NULL);
                $permissions = (isset($definition['permissions']) ? $definition['permissions'] : array());
                if (!is_array($permissions)) $permissions = array($permissions);

                $hasPermission = TRUE;
                foreach ($permissions as $permission) {
                    if (!$user->hasPermission($permission)) {
                        $hasPermission = FALSE;
                        break;
                    }
                }

                if ($hasPermission) {
                    print(<<<HTML
                        <li>
                            <a target='$target' title='$title' href='$url'>
                                $title
                                $iconHTML
                            </a>
                        </li>
    HTML
                    );
                }
            }
        }

        print('</ul>');
    }
}
?>
