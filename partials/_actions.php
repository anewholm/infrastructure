<?php
// TODO: The controller (FormController behaviour) can also have actionFunctions, from config_form.yaml
// Model action functions
// RelationManagers loading popups need the relationObject instead
if      (isset($listRecord)) $model = $listRecord;
else if (isset($relationManageWidget)) $model = $relationManageWidget->model;
else if (isset($formModel))  $model = $formModel;
else if (isset($record))     $model = $record;
else throw new \Exception("Cannot ascertain model for actions operation");

// _manage_form_footer_create.php for Popups calls here without checking
if (method_exists($model, 'actionFunctions')) {
    $formMode        = (isset($formModel) && $model == $formModel);
    $modelArrayName  = $model->unqualifiedClassName();
    $actionFunctions = $model->actionFunctions(); // Includes inherited 1to1 action functions
    $user            = BackendAuth::user();

    if (count($actionFunctions) || $formMode || $model->printable) {
        print('<ul class="action-functions">');

        // --------------------------------- Actions
        foreach ($actionFunctions as $name => &$definition) {
            $enDevLabel      = e(trans($definition['label']));
            $dataRequestData = e(substr(json_encode(array(
                'name'       => $name, // SECURITY: We do not want to reveal the full function name
                'arrayname'  => $modelArrayName,
                'modelId'    => $definition['model_id'],
                'model'      => $definition['model']
            )), 1,-1));

            // TODO: icon
            $icon = (isset($definition['icon']) ? '' : '');
            print(<<<HTML
                <li>
                    $icon
                    <a
                        data-control="popup"
                        data-request-data='$dataRequestData'
                        data-handler="onActionFunction"
                    >$enDevLabel</a>
                </li>
    HTML
            );
        }

        // --------------------------------- Printing
        // Can include conditions and permissions
        if ($model->printable) {
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
                $previewLink = $model->controllerUrl('preview', $model->id());
                print("<li><a 
                    target='_blank'
                    href='$previewLink'
                >$print</a></li>");
            }
        }

        // --------------------------------- QR scan
        if ($formMode) {
            $popupQrScan = $this->makePartial('popup_qrscan', array(
                'actions'       => array('form-field-complete'),
            ));
            print("<li>$popupQrScan</li>");
        }

        print('</ul>');
    }
}
?>
