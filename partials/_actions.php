<?php
// TODO: The controller (FormController behaviour) can also have actionFunctions, from config_form.yaml
// Model action functions
// RelationManagers loading popups need the relationObject instead
if      (isset($listRecord)) $model = $listRecord;
else if (isset($relationManageWidget)) $model = $relationManageWidget->model;
else if (isset($formModel))  $model = $formModel;
else if (isset($record))     $model = $record;
else throw new \Exception("Cannot ascertain model for actions operation");

$formMode        = (isset($formModel) && $model == $formModel);
$modelArrayName  = $model->unqualifiedClassName();
$actionFunctions = $model->actionFunctions(); // Includes inherited 1to1 action functions

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
    if ($model->printable) {
        $print = e(trans('acorn::lang.models.general.print'));
        $previewLink = $model->controllerUrl('preview', $model->id());
        print("<li><a 
            target='_blank'
            href='$previewLink'
        >$print</a></li>");
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
?>
