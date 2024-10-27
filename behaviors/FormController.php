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

            if ($configFileName == 'fields.yaml') {
                // ------------------------------------------------- setting: directives
                // This allows fields to be conditionally shown
                // in the same way as permissions
                // setting: my_setting
                // where my_setting must be valid on a local Plugin Setting[s] class
                foreach ($config->fields as $fieldName => &$fieldConfig) {
                    if (isset($fieldConfig['setting'])) {
                        if ($settingsClass = $this->getSettingsModel($this->getConfig('modelClass'))) {
                            $setting = $settingsClass::get($fieldConfig['setting']);
                            if ($setting != '1') unset($config->tabs['fields'][$fieldName]);
                        }
                    }
                }
                if (isset($config->tabs['fields'])) {
                    foreach ($config->tabs['fields'] as $fieldName => &$fieldConfig) {
                        if (isset($fieldConfig['setting'])) {
                            if ($settingsClass = $this->getSettingsModel($this->getConfig('modelClass'))) {
                                $setting = $settingsClass::get($fieldConfig['setting']);
                                if ($setting != '1') unset($config->tabs['fields'][$fieldName]);
                            }
                        }
                    }
                }

                // ------------------------------------------------- include: directives
                // RECURSIVE!
                // Scan for include directives
                // only allowed in the main fields collection
                $subConfigs = array();
                foreach ($config->fields as $fieldName => &$fieldConfig) {
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
                    if (property_exists($subConfig, 'fields')) $this->processFields($config->fields, $subConfig->fields, $fieldName);
                    if (property_exists($subConfig, 'tabs') && isset($subConfig->tabs['fields'])) {
                        if (!property_exists($config, 'tabs')) $config->tabs = array();
                        if (!isset($config->tabs['fields']))   $config->tabs['fields'] = array();
                        $this->processFields($config->tabs['fields'], $subConfig->tabs['fields'], $fieldName);
                    }
                    if (property_exists($subConfig, 'secondaryTabs') && isset($subConfig->secondaryTabs['fields'])) {
                        if (!property_exists($config, 'secondaryTabs')) $config->secondaryTabs = array();
                        if (!isset($config->secondaryTabs['fields']))   $config->secondaryTabs['fields'] = array();
                        $this->processFields($config->secondaryTabs['fields'], $subConfig->secondaryTabs['fields'], $fieldName);
                    }
                    if (property_exists($subConfig, 'tertiaryTabs') && isset($subConfig->tertiaryTabs['fields'])) {
                        if (!property_exists($config, 'tertiaryTabs')) $config->tertiaryTabs = array();
                        if (!isset($config->tertiaryTabs['fields']))   $config->tertiaryTabs['fields'] = array();
                        $this->processFields($config->tertiaryTabs['fields'], $subConfig->tertiaryTabs['fields'], $fieldName);
                    }
                }

                // Remove include directives
                foreach ($subConfigs as $fieldName => $subConfig) unset($config->fields[$fieldName]);

                //if (count($subConfigs)) dd($config);
            }
        }

        return $config;
    }

    protected function processFields(array &$configFields, array &$subConfigFields, string $fieldName): void
    {
        $inserts = array();
        foreach ($subConfigFields as $subFieldName => $subFieldConfig) {
            // TODO: Nested 1from1 relations
            $subType        = (isset($subFieldConfig['type']) ? $subFieldConfig['type'] : 'text');
            $includeContext = (isset($subFieldConfig['includeContext']) ? $subFieldConfig['includeContext'] : 'include');
            if ($subFieldName != 'id' && $includeContext != 'no-include') {
                $isPseudoFieldName = (substr($subFieldName, 0, 1) == '_');

                // Config changes
                if ($subType == 'relation') $subFieldConfig['context'] = array('update');
                if (isset($subFieldConfig['dependsOn'])) {
                    $dependsOn = $subFieldConfig['dependsOn'];
                    foreach ($dependsOn as $i => $dependsOnField) {
                        $isDepndsPseudoFieldName = (substr($dependsOnField, 0, 1) == '_');
                        if (!$isDepndsPseudoFieldName)
                            $subFieldConfig['dependsOn'][$i] = "${fieldName}[$dependsOnField]";
                    }
                }

                // Nesting in to existing form
                $nestedFieldName   = ($isPseudoFieldName ? $subFieldName : "${fieldName}[$subFieldName]");
                $inserts[$nestedFieldName] = $subFieldConfig;
            }
        }

        // Insert before $fieldName
        $this->array_insert($configFields, $fieldName, $inserts);
    }
}
