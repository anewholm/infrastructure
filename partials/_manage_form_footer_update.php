<?php
use AcornAssociated\Behaviors\RelationController;

print($this->makePartial('actions'));

// We include extra passables here, rather than overriding the whole form partial
// Unfortunately Winter provides no events for extending the passables or form parts
$PARAM_PARENT_MODEL    = RelationController::PARAM_PARENT_MODEL;
$PARAM_PARENT_MODEL_ID = RelationController::PARAM_PARENT_MODEL_ID;
if (post($PARAM_PARENT_MODEL)) {
    $parentModel   = post($PARAM_PARENT_MODEL);
    $parentModelId = post($PARAM_PARENT_MODEL_ID);
    print('<!-- Passable fields extra -->');
    print("<input type='hidden' name='$PARAM_PARENT_MODEL'    value='$parentModel' />");
    print("<input type='hidden' name='$PARAM_PARENT_MODEL_ID' value='$parentModelId' />");
}

// To view all records of this type
$viewAll = trans('acornassociated::lang.models.general.view_all');
$href    = $formModel->controllerUrl();
print("<a class='view-all' href='$href' target='_blank'>$viewAll</a>");

require('modules/backend/behaviors/relationcontroller/partials/_manage_form_footer_update.php');
?>
