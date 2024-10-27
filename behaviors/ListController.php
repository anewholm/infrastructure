<?php namespace Acorn\Behaviors;

use \Backend\Behaviors\ListController as BackendListController;
use \Exception;

class ListController extends BackendListController
{
    public function __construct($controller)
    {
        parent::__construct($controller);
        $this->addViewPath('~/modules/backend/behaviors/listcontroller/partials');
    }

    protected function array_insert(&$array, $positionKey, $insert)
    {
        // In-place!
        if (is_int($positionKey)) {
            array_splice($array, $positionKey, 0, $insert);
        } else {
            $posNum = ($positionKey ? array_search($positionKey, array_keys($array)) : 0);
            $array  = array_merge(
                array_slice($array, 0, $posNum),
                $insert,
                array_slice($array, $posNum)
            );
        }
    }

    protected function getSettingsModel(string $modelClass): string
    {
        $settingsClass = NULL;
        $modelClassParts = explode('\\', $modelClass);
        array_pop($modelClassParts);

        array_push($modelClassParts, 'Settings');
        $class = implode('\\', $modelClassParts);
        if (class_exists($class)) $settingsClass = $class;
        array_pop($modelClassParts);

        array_push($modelClassParts, 'Setting');
        $class = implode('\\', $modelClassParts);
        if (class_exists($class)) $settingsClass = $class;

        return $settingsClass;
    }

    public function makeConfig($configFile = [], $requiredConfig = [])
    {
        $config = parent::makeConfig($configFile, $requiredConfig);

        if (is_string($configFile) && $configFile) {
            $configFileParts = explode('/', $configFile);
            $configFileName  = last($configFileParts);

            if ($configFileName == 'columns.yaml') {
                // ------------------------------------------------- setting: directives
                // This allows fields to be conditionally shown
                // in the same way as permissions
                // setting: my_setting
                // where my_setting must be valid on a local Plugin Setting[s] class
                foreach ($config->columns as $fieldName => &$fieldConfig) {
                    if (isset($fieldConfig['setting'])) {
                        if ($settingsClass = $this->getSettingsModel($this->getConfig('modelClass'))) {
                            $setting = $settingsClass::get($fieldConfig['setting']);
                            if ($setting != '1') unset($config->columns[$fieldName]);
                        }
                    }
                }

                // ------------------------------------------------- include: directives
                // RECURSIVE!
                // Scan for include directives
                $subConfigs = array();
                foreach ($config->columns as $fieldName => &$fieldConfig) {
                    if (isset($fieldConfig['include'])) {
                        $path = NULL;
                        if (isset($fieldConfig['includeModel'])) {
                            $modeClass = $fieldConfig['includeModel'];
                            $model     = new $modeClass;
                            $modelDir  = $model->modelDirectoryPathRelative();
                            $path      = "$modelDir/columns.yaml";
                        }
                        if (isset($fieldConfig['path'])) $path = $fieldConfig['path'];
                        if ($path) $subConfigs[$fieldName] = $this->makeConfig($path, $requiredConfig);
                        else throw new Exception("Include directive without path or model in [$fieldName]");
                    }
                }

                // Inject columns
                foreach ($subConfigs as $fieldName => $subConfig) {
                    if (property_exists($subConfig, 'columns')) {
                        foreach ($subConfig->columns as $subFieldName => $subFieldConfig) {
                            // TODO: Nested 1from1 relations
                            $subType        = (isset($subFieldConfig['type']) ? $subFieldConfig['type'] : 'text');
                            $includeContext = (isset($subFieldConfig['includeContext']) ? $subFieldConfig['includeContext'] : 'include');
                            if ($subFieldName != 'id' && $includeContext != 'no-include') {
                                $isPseudoFieldName = (substr($subFieldName, 0, 1) == '_');
                                $nestedFieldName   = $subFieldName;
                                if (!$isPseudoFieldName) {
                                    // Sub-relation fields: The relation is added in to the name[relation]
                                    if (isset($subFieldConfig['relation'])) {
                                        $subFieldName = $subFieldConfig['relation'];
                                        unset($subFieldConfig['relation']);
                                    }
                                    $nestedFieldName = "${fieldName}[$subFieldName]";
                                    if (isset($subFieldConfig['valueFrom'])) {
                                        $nestedFieldName = "${nestedFieldName}[$subFieldConfig[valueFrom]]";
                                        unset($subFieldConfig['valueFrom']);
                                    }
                                }
                                // Insert before $fieldName
                                $this->array_insert($config->columns, $fieldName, array($nestedFieldName => $subFieldConfig));
                            }
                        }
                    }
                }

                // Remove include directives
                foreach ($subConfigs as $fieldName => $subConfig) unset($config->columns[$fieldName]);

//                 if (count($subConfigs)) dd($configFile, $config);
            }
        }

        return $config;
    }
}
