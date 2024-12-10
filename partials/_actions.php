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
$actionFunctions = ($model->actionFunctions ?: array());

// Populate the Id
foreach ($actionFunctions as $name => &$definition) {
    $include = (!isset($model['condition']) || $model::where('id', $relatedModel->id())->whereRaw($model['condition'])->count() != 0);
    if ($include) {
        $definition['parameters']['id'] = $model->id();
    } else {
        unset($actionFunctions[$name]);
    }
}

// 1to1 BelongsTo relations
foreach ($model->belongsTo as $name => $relationDefinition) {
    if (isset($relationDefinition['type']) && $relationDefinition['type'] == '1to1') {
        $model->load($name);
        if ($relatedModel = $model->getRelation($name)) {
            if ($relatedModel->actionFunctions) {
                // Existing actions take precedence
                // Write the sub-model id
                foreach ($relatedModel->actionFunctions as $name => $relatedModelDefinition) {
                    $include = (!isset($relatedModelDefinition['condition'])
                        || $relatedModel::where('id', $relatedModel->id())->whereRaw($relatedModelDefinition['condition'])->count() != 0);
                    if ($include) {
                        $relatedModelDefinition['parameters']['id'] = $relatedModel->id();
                        $actionFunctions[$name] = $relatedModelDefinition;
                    }
                }
            }
        }
    }
}

if (count($actionFunctions) || $formMode || $model->printable) {
    print('<ul class="action-functions">');

    // --------------------------------- Actions
    foreach ($actionFunctions as $name => &$definition) {
        $enDevLabel      = e(trans($definition['label']));
        $dataRequestData = e(substr(json_encode(array(
            'name'       => $definition['fnName'],
            'parameters' => $definition['parameters'],
            'arrayname'  => $modelArrayName,
            'id'         => $definition['parameters']['id'],
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
