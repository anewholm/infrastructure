<?php
namespace Acorn\Behaviors;

use Acorn\Behaviors\ImportExportController;
use Event;
use \Backend\Widgets\Filter;

class BatchPrintController extends ImportExportController
{
    public function __construct($controller)
    {
        parent::__construct($controller);

        $this->addViewPath('~/modules/backend/behaviors/batchprintcontroller/partials');
        $this->addViewPath('~/modules/acorn/behaviors/batchprintcontroller/partials');
    }

    protected function makeExportFormatFormWidget()
    {
        // Copied from parent. YAML config changed
        // TODO: Change back... Doesn't work...
        $yamlFile = '~/modules/acorn/behaviors/batchprintcontroller/partials/fields_export.yaml';
        $yamlFile = '~/modules/backend/behaviors/importexportcontroller/partials/fields_export.yaml';

        if (!$this->getConfig('export') || $this->getConfig('export[useList]')) {
            return null;
        }

        $widgetConfig = $this->makeConfig($yamlFile);
        $widgetConfig->model = $this->exportGetModel();
        $widgetConfig->alias = 'exportUploadForm';

        $widget = $this->makeWidget('Backend\Widgets\Form', $widgetConfig);

        $widget->bindEvent('form.beforeRefresh', function ($holder) {
            $holder->data = [];
        });

        return $widget;
    }
}
