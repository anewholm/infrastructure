<?php namespace Acorn\Behaviors;

use \Backend\Behaviors\ListController as BackendListController;
use \Exception;
use Backend\Widgets\Search;

class ListController extends BackendListController
{
    public function __construct($controller)
    {
        parent::__construct($controller);

        $this->addViewPath('~/modules/backend/behaviors/listcontroller/partials');

        Search::extend(function ($widget) {
            $widget->addViewPath('~/modules/acorn/partials/');
        });

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
                            $subType        = ($subFieldConfig['type']           ?? 'text');
                            $includeContext = ($subFieldConfig['includeContext'] ?? 'include');
                            if (   $subFieldName != 'id' 
                                && $includeContext != 'no-include'
                                // Dates will not work because this model does not have the same $dates casting
                                // TODO: Support datetime types include
                                && $subType != 'timetense' 
                                && $subType != 'date' 
                            ) {
                                $isAlreadyNested   = (strstr($subFieldName, '[') !== FALSE);
                                $isPseudoFieldName = (substr($subFieldName, 0, 1) == '_');
                                $nestedFieldName   = $subFieldName;
                                if (!$isPseudoFieldName) {
                                    // Sub-relation fields: The relation is added in to the name[relation][valueFrom]
                                    if (isset($subFieldConfig['relation'])) {
                                        // We cannot use the relation field because there already is one
                                        // relation: does work with nesting, but let's move to full nested name anyway
                                        $subFieldName    = $subFieldConfig['relation'];
                                        $nestedFieldName = "${fieldName}[$subFieldName]";
                                        unset($subFieldConfig['relation']);
                                        /* 
                                        if (isset($subFieldConfig['valueFrom'])) {
                                            $valueFrom = $subFieldConfig['valueFrom'];
                                            $nestedFieldName = "${nestedFieldName}[$valueFrom]";
                                            unset($subFieldConfig['valueFrom']);
                                        }
                                        */
                                        $subFieldConfig['searchable'] = FALSE;
                                        $subFieldConfig['sortable']   = FALSE;
                                    } else {
                                        // No relation field, so lets just set it
                                        // relation: user
                                        // no additional field name nesting
                                        // TODO: select: ?
                                        $subFieldConfig['relation']   = $fieldName;
                                        // valueFrom: is necessary for the relation to work
                                        $subFieldConfig['valueFrom'] = ($subFieldConfig['valueFrom'] ?? 'name');
                                        // TODO: Should be able to maintain searcheable in certain conditions
                                        // $subFieldConfig['searchable'] = TRUE; // Leave as before
                                        $subFieldConfig['sortable']   = FALSE;
                                    }
                                }

                                // Custom nesting information
                                $nestLevel = $subFieldConfig['nestLevel'] ?? 0;
                                $subFieldConfig['nested']    = TRUE;
                                $subFieldConfig['nestLevel'] = $nestLevel+1;
                                $subFieldConfig['included']  = TRUE;
                
                                // Insert before $fieldName
                                $this->array_insert($config->columns, $fieldName, array($nestedFieldName => $subFieldConfig));
                            }
                        }
                    }
                }

                // Remove include directives
                foreach ($subConfigs as $fieldName => $subConfig) unset($config->columns[$fieldName]);

                //if (count($subConfigs)) dd($configFile, $config);
            }
        }

        return $config;
    }
}
