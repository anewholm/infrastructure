<?php
namespace Acorn\Behaviors;

use \Backend\Behaviors\ImportExportController as BackendImportExportController;

class ImportExportController extends BackendImportExportController
{
    public function __construct($controller)
    {
        parent::__construct($controller);

        // Access to backends original partials
        $this->addViewPath('~/modules/backend/behaviors/importexportcontroller/partials');
        $this->assetPath = '/modules/backend/behaviors/importexportcontroller/assets';
    }

    protected function getModelForType($type)
    {
        // We inject the config in to the Model
        // because it makes decisions about model retrieval
        $model         = parent::getModelForType($type);
        $model->config = $this->config->export;
            
        // Copied from ImportExportController::exportFromList()
        if (isset($model->config['useListQuery']) && $model->config['useListQuery']) {
            $lists         = $this->controller->makeLists();
            $widget        = $lists['list'] ?? reset($lists);
            $model->query  = $widget->prepareQuery(); // Also applies scopes and search
        }

        return $model;
    }
}
