<?php namespace Acorn\Behaviors;

use Backend\Behaviors\FormController as BackendFormController;
use \Exception;

class FormController extends BackendFormController
{
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

    public function makeConfig($configFile = [], $requiredConfig = [])
    {
        $config = parent::makeConfig($configFile, $requiredConfig);
        $configFileParts = explode('/', $configFile);
        $configFileName  = last($configFileParts);

        if ($configFileName == 'fields.yaml') {
            // RECURSIVE!
            // Scan for include directives
            $subConfigs = array();
            foreach ($config->fields as $fieldName => $fieldConfig) {
                if (isset($fieldConfig['include'])) {
                    $path = NULL;
                    if (isset($fieldConfig['includeModel'])) {
                        $modeClass = $fieldConfig['includeModel'];
                        $model     = new $modeClass;
                        $modelDir  = $model->modelDirectoryPathRelative();
                        $path      = "$modelDir/fields.yaml";
                    }
                    if (isset($fieldConfig['path'])) $path = $fieldConfig['path'];
                    if ($path) $subConfigs[$fieldName] = $this->makeConfig($path, $requiredConfig);
                    else throw new Exception("Include directive without path or model in [$fieldName]");
                }
            }

            // Inject fields and tabs
            foreach ($subConfigs as $fieldName => $subConfig) {
                if (property_exists($subConfig, 'fields')) {
                    foreach ($subConfig->fields as $subFieldName => $subFieldConfig) {
                        // TODO: nested 1-X relations
                        $subType        = (isset($subFieldConfig['type']) ? $subFieldConfig['type'] : 'text');
                        $includeContext = (isset($subFieldConfig['includeContext']) ? $subFieldConfig['includeContext'] : 'include');
                        if ($subFieldName != 'id' && $includeContext != 'no-include') {
                            $isPseudoFieldName = (substr($subFieldName, 0, 1) == '_');
                            $nestedFieldName   = ($isPseudoFieldName ? $subFieldName : "${fieldName}[$subFieldName]");
                            // Insert before $fieldName
                            $this->array_insert($config->fields, $fieldName, array($nestedFieldName => $subFieldConfig));
                        }
                    }
                }
                if (property_exists($subConfig, 'tabs')) {
                    $inserts = array();
                    foreach ($subConfig->tabs['fields'] as $subFieldName => $subFieldConfig) {
                        // TODO: nested 1-X relations
                        $subType        = (isset($subFieldConfig['type']) ? $subFieldConfig['type'] : 'text');
                        $includeContext = (isset($subFieldConfig['includeContext']) ? $subFieldConfig['includeContext'] : 'include');
                        // TODO: It seems that 1toX is only supported on update
                        if ($subType == 'relation') $subFieldConfig['context'] = array('update');
                        if ($subFieldName != 'id' && $includeContext != 'no-include') {
                            $isPseudoFieldName = (substr($subFieldName, 0, 1) == '_');
                            $nestedFieldName   = ($isPseudoFieldName ? $subFieldName : "${fieldName}[$subFieldName]");
                        }
                        $inserts[$nestedFieldName] = $subFieldConfig;
                    }
                    // Insert at beginning
                    if (!isset($config->tabs['fields'])) $config->tabs['fields'] = array();
                    $this->array_insert($config->tabs['fields'], '', $inserts);
                }
            }

            // Remove include directives
            foreach ($subConfigs as $fieldName => $subConfig) unset($config->fields[$fieldName]);

            //if (count($subConfigs)) dd($config);
        }

        return $config;
    }
}
