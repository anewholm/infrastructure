<?php
use Acorn\Behaviors\RelationController;

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

require('modules/backend/behaviors/relationcontroller/partials/_manage_form_footer_create.php');
?>
