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
        $this->assetPath = '/modules/backend/behaviors/batchprintcontroller/assets';
    }

    protected function makeExportFormatFormWidget()
    {
        // Copied from parent. YAML config changed
        // TODO: Provide / set the list filters from session
        // TODO: Load the on-board ListController, its widget and thus session filter settings
        if (!$this->getConfig('export') || $this->getConfig('export[useList]')) {
            return null;
        }

        $widgetConfig = $this->makeConfig('~/modules/acorn/behaviors/batchprintcontroller/partials/fields_export.yaml');
        $widgetConfig->model = $this->exportGetModel();
        $widgetConfig->alias = 'exportUploadForm';

        $widget = $this->makeWidget('Backend\Widgets\Form', $widgetConfig);

        $widget->bindEvent('form.beforeRefresh', function ($holder) {
            $holder->data = [];
        });

        // Custom++
        // This event happens after this function runs above
        Event::listen('backend.filter.extendScopes', function (Filter $filterWidget) use($widget) {
            /*
            foreach ($filterWidget->allScopes as $name => $scope) {
                $fields = &$widget->config['fields'];
                $fields[$name] = array(
                    'type' => 'text',
                );
            }
                */
            $test = 9;
        });
        
        return $widget;
    }
}
