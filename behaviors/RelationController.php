<?php namespace Acorn\Behaviors;

use Backend\Behaviors\RelationController as RelationControllerBase;
use Acorn\Relationships\HasManyDeep;
use Log;
use Str;
use File;
use Acorn\Model;

class RelationController extends RelationControllerBase
{
    protected const PARAM_PARENT_MODEL = '_parent_model';
    protected const PARAM_PARENT_MODEL_ID = '_parent_model_id';
    protected $popupModel;
    protected $popupConfig;

    public function initRelation($model, $field = null)
    {
        /* ------------------------------------- RelationManagers in popups do not work natively
         * because the controller & model is from the parent RelationManager that triggered the popup
         * thus the config_relation.yaml entries are missing
         * The parent controller is necessary because, in update mode, the first call to this RelationController
         * is to get the values of the primary existing popup model from the relation on the parent controller
         * 
         * --- Names
         * parentModel: the model of the calling screen
         * popupModel:  the model behind the popup Relation Managers
         * model:       changing! model depending on type of initiRelation() call
         * 
         * --- $_POST
         * post(self::PARAM_FIELD) indicates that this is a Relation Manager popup for $paramField
         * that may have other Relation Managers embedded in the fields.yaml
         *
         * --- fields.yaml accepts (but we do not use):
         *   recordUrl:     acorn/criminal/legalcasevictims/update/:id
         *   recordOnClick: $.wn.relationBehavior.clickViewListRecord(':id', 'Legalcases-update-RelationController-criminal_legalcase_victims_legalcase', 'K2AKGIv0ZoqfDRC8t0LUukrRq63B867bURT5wxun')
         * Which would allow us to change the controller for the popup, _before_ the controller event method is called. 
         * However, this would not work because the first call, in update mode, is for the relation on the parent controller/model
         * Probably this would work for create mode.
         *
         * RelationController::__constructor() completed $this->originalConfig = $this->makeConfig($controller->relationConfig)
         * partials repeatedly render sub-partials, repeatedly calling validateField($field)
         * validateField($field) comes here if the $field != $this->field
         * so it is important to pass the $field continuously through the partial calls
         * 
         * ---- Type of initRelation() call:
         * Call order
         * Primary $form->fields loop is in modules/backend/widgets/form/partials/_form_fields.php
         *   1) $field is null. This is also the 1st call
         *   2) eventTarget is button-update. When updating a record, to get its model->values. Always the second call
         *   3) eventTarget is button-create. No (2) parent model query. Direct to form fields
         * ManageMode:
         *   is based on $this->eventTarget == button pressed: create|update, and $this->relationType
         *   usually form. list|pivot, usually caused by button-add, indicates a un|link list display operation
         * $this->field is set in initRelation()
         * 
         * --- _container.php 
         * We have changed this partial to pass the $field through to render() sub-calls
         * In main screen => popup situations we can wait for the relationModel to be indicated
         * on the second initRelation() call
         * and then set it for subsequent RelationManagers in the popup
         * This will then set $model, $this->model and $this->vars[formModel] below
         */
        $paramField         = post(self::PARAM_FIELD);           // Also used in the parent
        $paramParentModel   = post(self::PARAM_PARENT_MODEL);    // New parameter
        $paramParentModelId = post(self::PARAM_PARENT_MODEL_ID); // New parameter

        if ($paramField) {
            if (!$this->popupModel) {
                if ($paramParentModel) {
                    // Explicitly sent from _container.php data-request-data call 
                    // to onRelationClickViewList()
                    // to override the popupModel for this controller
                    // will only set the main $model below if it does not have the requested field
                    $paramParentModelObj = NULL;
                    if ($paramParentModelId) $paramParentModelObj = $paramParentModel::find($paramParentModelId);
                    else                     $paramParentModelObj = new $paramParentModel;
                    if (!$model->is($paramParentModelObj)) {
                        $model = $paramParentModelObj;
                        // None of this models config_relation.yaml is present
                        // so we pre-load
                        $this->loadModelRelationConfig($model);
                    }
                    if ($paramField) {
                        if (!$model->hasRelation($paramField)) {
                            throw new \Exception("The parent model param [$paramParentModel:$paramParentModelId] does not have the relation [$paramField] being requested");
                        }
                    }
                }
                
                if ($this->relationModel) { // An empty Model
                    // relationModel has been completed on the second call, so we can now use it
                    // as our new model == popupModel for subsequent in-popup RelationManagers
                    if ($this->manageId) {
                        // Update mode with form and manageId
                        // $this->popupModel->exists == TRUE
                        // Copied from makeManageWidget() Existing record section
                        $this->popupModel = $this->relationModel->find($this->manageId);
                    } else {
                        // Create mode without manageId
                        // $this->relationModel->exists == FALSE
                        // This will cause deferedBinding
                        $this->popupModel = $this->relationModel;
                    }
                }
                
                if ($this->popupModel) {
                    // Always pre-load the config_relation.yaml 
                    // from the real popup model controller immediately
                    // onto $this (wrong) controller
                    $this->loadModelRelationConfig($this->popupModel);
                }
            }

            if ($field) {
                // Might be the parent, primary model call for values if in update mode
                // However, it is better just to check if the model has the relation or not
                if (!$this->model->hasRelation($field)) {
                    // Change the model so it can be queried below in parent::initRelation($model, $field)
                    // $model->{$field}() to get the popup data
                    // $this->relationObject = $this->model->{$field}();
                    if (!$this->popupModel->hasRelation($field)) {
                        $parentModelClass = get_class($this->model);
                        $popupModelClass  = get_class($this->popupModel);
                        throw new \Exception("Neither the parent model [$parentModelClass], nor the popup model [$popupModelClass] seem to have the relation [$field] being requested");
                    }
                    // No need to ever change it back, because the other calls above always have already happened
                    // We only need the popup model from now on for form fields display
                    $model = $this->popupModel;
                    // Send the formModel to _container.php
                    $this->vars['formModel'] = $model;
                    // Here we prevent further update mode (manageMode:form) problems
                    // because $this->manageId will cause makeManageWidgets() to query their models
                    $this->forceManageMode = 'list';
                }
            }
        }

        // ------------------------------------- This initRelation($model, $field) call will setup:
        //     $this->config = $this->originalConfig;
        //     $this->model = $model;
        //     $this->field = $field;
        //     ...
        //     $this->alias = camel_case('relation ' . $field);
        //     $this->config = $this->makeConfig($this->getConfig($field), $this->requiredRelationProperties);
        //     $this->controller->relationExtendConfig($this->config, $this->field, $this->model);
        //     $this->relationName = $field;
        //     $this->relationType = $this->model->getRelationType($field);
        //     $this->relationObject = $this->model->{$field}();
        //     $this->relationModel = $this->relationObject->getRelated();
        // and the toolbar/manage/search/view widgets
        parent::initRelation($model, $field); // Initializes widgets on controller

        // Point to the parent view path
        if ($this->toolbarWidget) $this->toolbarWidget->addViewPath('~/modules/acorn/partials');
        $this->addViewPath('~/modules/backend/behaviors/relationcontroller/partials');
        $this->addViewPath('~/modules/acorn/partials');
    }

    protected function loadModelRelationConfig(Model &$model): void {
        $controllerDir  = $model->controllerDirectoryPathRelative(); // plugins/...
        $relationConfig = "$controllerDir/config_relation.yaml";
        if (File::exists($relationConfig)) {
            $config = $this->makeConfig($relationConfig);
            foreach ($config as $field => $fieldConfig) {
                if (!property_exists($this->originalConfig, $field)) 
                    $this->originalConfig->$field = $fieldConfig;
            }
        }
    }

    protected function evalViewMode()
    {
        // Includes forceViewMode
        $viewMode = parent::evalViewMode();

        if (!$viewMode) {
            // Similar to ~/modules/backend/behaviors/RelationController.php
            switch ($this->relationType) {
                case 'hasManyDeep':
                    $viewMode = 'multi';
                    break;
            }
        }

        return $viewMode;
    }

    protected function evalManageMode()
    {
        $manageMode = parent::evalManageMode();

        if (!$manageMode) {
            // Similar to ~/modules/backend/behaviors/RelationController.php
            switch ($this->relationType) {
                case 'hasManyDeep':
                    // Similar to hasMany clause
                    if ($this->eventTarget === 'button-add') {
                        $manageMode = 'list';
                    } else {
                        $manageMode = 'form';
                    }
                    break;
            }
        }
        
        return $manageMode;
    }
}