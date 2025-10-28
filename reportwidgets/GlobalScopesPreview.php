<?php namespace Acorn\ReportWidgets;

use BackendAuth;
use Backend\Models\AccessLog;
use Backend\Classes\ReportWidgetBase;
use Backend\Models\BrandSetting;
use System\Classes\MediaLibrary;
use Exception;
use Str;
use DomDocument;
use Acorn\Scopes\GlobalChainScope;

class GlobalScopesPreview extends ReportWidgetBase
{
    /**
     * @var string A unique alias to identify this widget.
     */
    protected $defaultAlias = 'globalscopespreview';

    /**
     * Renders the widget.
     */
    public function render()
    {
        try {
            $this->loadData();
        }
        catch (Exception $ex) {
            $this->vars['error'] = $ex->getMessage();
        }

        return $this->makePartial('widget');
    }

    public function defineProperties()
    {
        return [
            'title' => [
                'title'             => 'backend::lang.dashboard.widget_title_label',
                'default'           => 'acorn::lang.dashboard.globalscopespreview.widget_title_default',
                'type'              => 'string',
                'validationPattern' => '^.+$',
                'validationMessage' => 'backend::lang.dashboard.widget_title_error',
            ],
        ];
    }

    /**
     * @inheritDoc
     */
    protected function loadAssets()
    {
        $this->addCss('css/globalscopespreview.css', 'core');
    }

    protected function loadData()
    {
        // Only those with a setting
        $this->vars['scopes'] = array();
        foreach (GlobalChainScope::allUserSettings() as $setting => $details) {
            $modelClass = $details['modelClass'];
            $modelId    = $details['setting'];
            if ($model = $modelClass::find($modelId)) {
                if ($leafModel = $model->getLeafTypeModel())
                    $model = $leafModel;
                $this->vars['scopes'][$setting] = $model;
            }
        }
    }
}
