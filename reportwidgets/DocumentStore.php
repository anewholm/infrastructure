<?php namespace Acorn\ReportWidgets;

use BackendAuth;
use Backend\Models\AccessLog;
use Backend\Classes\ReportWidgetBase;
use Backend\Models\BrandSetting;
use System\Classes\MediaLibrary;
use Exception;
use Str;
use Yaml;
use Lang;

/**
 * User welcome report widget.
 *
 * @package winter\wn-backend-module
 * @author Alexey Bobkov, Samuel Georges
 */
class DocumentStore extends ReportWidgetBase
{
    /**
     * @var string A unique alias to identify this widget.
     */
    protected $defaultAlias = 'documentstore';

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
                'default'           => 'acorn::lang.dashboard.documentstore.widget_title_default',
                'type'              => 'string',
                'validationPattern' => '^.+$',
                'validationMessage' => 'backend::lang.dashboard.widget_title_error',
            ]
        ];
    }

    /**
     * @inheritDoc
     */
    protected function loadAssets()
    {
        $this->addCss('css/welcome.css', 'core');
    }

    protected function loadData()
    {
        // --------------------------------- Video help
        // MediaLibraryItem s
        $ml     = MediaLibrary::instance();
        $locale = Lang::getLocale();
        $dirs   = $ml->listAllDirectories(['/ActionTemplates']);
        foreach ($dirs as $dir) {
            $mlis = $ml->listFolderContents($dir, 'title', NULL, TRUE);
            foreach ($mlis as $mli) {
                $type       = $mli->getFileType();
                $ext        = strtolower(pathinfo($mli->path, PATHINFO_EXTENSION));
                $basePath   = preg_replace('/\.[a-zA-Z0-9]+$/', '', $mli->path);
                $baseName   = basename($basePath);
                if ($ext != 'yaml') {
                    // Translation of video names
                    $label = Str::title(preg_replace('/[_-]+/', ' ', $baseName));
                    if ($ml->exists("$basePath.yaml")) {
                        $settings = Yaml::parse($ml->get("$basePath.yaml"));
                        if (isset($settings['labels'][$locale])) {
                            $label = $settings['labels'][$locale];
                        } else if (isset($settings['labels']['en'])) {
                            $label = $settings['labels']['en'];
                        }
                    }

                    $typePlural = "{$type}s";
                    $url        = $ml->url($mli->path);
                    $baseDir    = basename($dir);
                    
                    if (!isset($this->vars[$typePlural])) $this->vars[$typePlural] = array();
                    if (!isset($this->vars[$typePlural][$baseDir])) $this->vars[$typePlural][$baseDir] = array();
                    $this->vars[$typePlural][$baseDir][$label] = $url; 
                }
            }
        }
    }
}
