<?php namespace Acorn;

use BackendMenu;
use Backend\Classes\Controller as BackEndController;
use System\Classes\PluginManager;
use File;
use ReflectionClass;

/**
 * Computer Product Backend Controller
 */
class Controller extends BackEndController
{
    public function __construct()
    {
        parent::__construct();

        // Include general plugin CSS/JS for this controller
        $reflection = new ReflectionClass($this);
        $absolutePluginPath = File::normalizePath(dirname(dirname($reflection->getFileName())));
        $relativePluginPath = str_replace($_SERVER['DOCUMENT_ROOT'], '', $absolutePluginPath);
        $relativeAssetPath  = 'assets/css/plugin.css';
        if (file_exists("$absolutePluginPath/$relativeAssetPath"))
            $this->addCss("$relativePluginPath/$relativeAssetPath");
        $relativeAssetPath  = 'assets/js/plugin.js';
        if (file_exists("$absolutePluginPath/$relativeAssetPath"))
            $this->addJs("$relativePluginPath/$relativeAssetPath");
    }

    public function formGetRedirectUrl($context = NULL, $model = NULL)
    {
        $action = post('action');
        $id     = ($model && method_exists($model, 'id') ? $model->id() : NULL);
        return ($action && !is_null($id) ? "$action/$id" : parent::formGetRedirectUrl($context, $model));
    }
}
