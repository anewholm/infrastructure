<?php namespace Acorn\Traits;

use Acorn\Behaviors\RelationController;
use Str;
use \Exception;

Trait MorphConfig
{
    public function makeConfig($configFile = [], $requiredConfig = [])
    {
        $config     = parent::makeConfig($configFile, $requiredConfig);
        $modelClass = $this->getConfig('modelClass');

        if (is_string($configFile) && $configFile) {
            $configFileParts = explode('/', $configFile);
            $configFileName  = last($configFileParts);
            $configRole      = explode('.', $configFileName)[0];

            switch ($configRole) {
                case 'config_form':
                    // ------------------------------------------------- Action functions
                    if (property_exists($this->controller, 'actionFunctions') && property_exists($config, 'actionFunctions'))
                        $this->controller->actionFunctions = $config->actionFunctions;
                    break;

                case 'fields':
                    // ------------------------------------------------- Process comments for translate
                    if (isset($config->fields)) {
                        foreach ($config->fields as &$fieldConfig) {
                            self::processCommentEmbeddedTranslationKeys($fieldConfig);
                        }
                    }

                    // ------------------------------------------------- Auto-hide parent model
                    if ($parentModel = post(RelationController::PARAM_PARENT_MODEL)) {
                        // We have a parent context
                        $parentModelParts = explode('\\', $parentModel);
                        $parentFieldName  = Str::snake(end($parentModelParts));
                        $parentModelId    = post(RelationController::PARAM_PARENT_MODEL_ID);
                        foreach ($config->fields as $fieldName => &$fieldConfig) {
                            // Look for a parent model selector
                            if ($fieldName == $parentFieldName && $fieldConfig['type'] == 'dropdown') {
                                // TODO: This does not work if the parent field is nested
                                $fieldConfig['cssClass'] .= ' hidden';
                                $fieldConfig['default']   = $parentModelId;
                            }
                        }
                    }

                    // ------------------------------------------------- setting: directives
                    // This allows fields to be conditionally shown
                    // in the same way as permissions
                    // setting: my_setting
                    // where my_setting must be valid on a local Plugin Setting[s] class
                    if ($modelClass) {
                        foreach ($config->fields as $fieldName => &$fieldConfig) {
                            if (
                                   self::settingRemove($fieldConfig, $modelClass)
                                || self::envRemove($fieldConfig, $modelClass)
                            ) unset($config->fields[$fieldName]);
                        }
                        if (isset($config->tabs['fields'])) {
                            foreach ($config->tabs['fields'] as $fieldName => &$fieldConfig) {
                                if (
                                        self::settingRemove($fieldConfig, $modelClass)
                                     || self::envRemove($fieldConfig, $modelClass)
                                ) unset($config->tabs['fields'][$fieldName]);
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
                            $path       = NULL;
                            $modelClass = NULL;
                            if (isset($fieldConfig['includeModel'])) {
                                $modelClass = $fieldConfig['includeModel'];
                                $model      = new $modelClass;
                                $modelDir   = $model->modelDirectoryPathRelative();
                                $path       = "$modelDir/$configFileName";
                            }
                            if (isset($fieldConfig['path'])) $path = $fieldConfig['path'];
                            if ($path) $subConfigs[$fieldName] = $this->makeConfig($path, $requiredConfig);
                            else throw new Exception("Include directive without path or model in [$fieldName]");

                            // Stamp the modelClass on the fields
                            $subConfigs[$fieldName]->modelClass = $modelClass;
                        }
                    }

                    // Inject fields and tabs
                    foreach ($subConfigs as $fieldName => $subConfig) {
                        if (property_exists($subConfig, 'fields')) self::processFields($config->fields, $subConfig->fields, $fieldName);
                        if (property_exists($subConfig, 'tabs') && isset($subConfig->tabs['fields'])) {
                            if (!property_exists($config, 'tabs')) $config->tabs = array();
                            if (!isset($config->tabs['fields']))   $config->tabs['fields'] = array();
                            self::processFields($config->tabs['fields'], $subConfig->tabs['fields'], $fieldName, $subConfig->modelClass);
                        }
                        if (property_exists($subConfig, 'secondaryTabs') && isset($subConfig->secondaryTabs['fields'])) {
                            if (!property_exists($config, 'secondaryTabs')) $config->secondaryTabs = array();
                            if (!isset($config->secondaryTabs['fields']))   $config->secondaryTabs['fields'] = array();
                            self::processFields($config->secondaryTabs['fields'], $subConfig->secondaryTabs['fields'], $fieldName, $subConfig->modelClass);
                        }
                        if (property_exists($subConfig, 'tertiaryTabs') && isset($subConfig->tertiaryTabs['fields'])) {
                            if (!property_exists($config, 'tertiaryTabs')) $config->tertiaryTabs = array();
                            if (!isset($config->tertiaryTabs['fields']))   $config->tertiaryTabs['fields'] = array();
                            self::processFields($config->tertiaryTabs['fields'], $subConfig->tertiaryTabs['fields'], $fieldName, $subConfig->modelClass);
                        }
                    }

                    // Remove include directives
                    foreach ($subConfigs as $fieldName => $subConfig) unset($config->fields[$fieldName]);
                    //if (count($subConfigs)) dd($config);
                    break;

                case 'columns':
                    // ------------------------------------------------- setting: and env: directives
                    // This allows fields to be conditionally shown
                    // in the same way as permissions
                    // setting: my_setting
                    // where my_setting must be valid on a local Plugin Setting[s] class
                    if ($modelClass) {
                        foreach ($config->columns as $fieldName => &$fieldConfig) {
                            if (
                                   self::settingRemove($fieldConfig, $modelClass)
                                || self::envRemove($fieldConfig, $modelClass)
                            ) unset($config->columns[$fieldName]);
                        }
                    }

                    // ------------------------------------------------- include: directives
                    // RECURSIVE!
                    // Scan for include directives
                    // only allowed in the main fields collection
                    $subConfigs = array();
                    foreach ($config->columns as $fieldName => &$fieldConfig) {
                        if (isset($fieldConfig['include'])) {
                            $path       = NULL;
                            $modelClass = NULL;
                            if (isset($fieldConfig['includeModel'])) {
                                $modelClass = $fieldConfig['includeModel'];
                                $model      = new $modelClass;
                                $modelDir   = $model->modelDirectoryPathRelative();
                                $path       = "$modelDir/$configFileName";
                            }
                            if (isset($fieldConfig['path'])) $path = $fieldConfig['path'];
                            if ($path) $subConfigs[$fieldName] = $this->makeConfig($path, $requiredConfig);
                            else throw new Exception("Include directive without path or model in [$fieldName]");

                            // Stamp the modelClass on the fields
                            $subConfigs[$fieldName]->modelClass = $modelClass;
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
                                        // Sub-relation fields: The relation is added in to the name[relation][valueFrom|name]
                                        if (isset($subFieldConfig['relation'])) {
                                            // We cannot use the relation field because there already is one
                                            // relation: does work with nesting, but let's move to full nested name anyway
                                            // TODO: This does not work on teachers list => user[languages]
                                            $subFieldName    = $subFieldConfig['relation'];
                                            $nestedFieldName = "${fieldName}[$subFieldName]";
                                            unset($subFieldConfig['relation']);
                                            $valueFrom       = $subFieldConfig['valueFrom'] ?? 'name';
                                            $nestedFieldName = "${nestedFieldName}[$valueFrom]";
                                            unset($subFieldConfig['valueFrom']);
                                            
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
                                    self::array_insert($config->columns, $fieldName, array($nestedFieldName => $subFieldConfig));
                                }
                            }
                        }
                    }

                    // Remove include directives
                    foreach ($subConfigs as $fieldName => $subConfig) unset($config->columns[$fieldName]);

                    //if (count($subConfigs)) dd($configFile, $config);
                    break;
            }
        }

        return $config;
    }

    protected static function array_insert(&$array, $positionKey, $insert)
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

    protected static function getSettingsModel(string $modelClass): string|NULL
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

    protected static function settingRemove(array &$fieldConfig, string $modelClass): bool
    {
        $settingsClass = self::getSettingsModel($modelClass);
        $removeField   = ($settingsClass && isset($fieldConfig['setting']) && $settingsClass::get($fieldConfig['setting']) != '1');
        return $removeField;
    }

    protected static function envRemove(array &$fieldConfig, string $modelClass): bool
    {
        $removeField = FALSE;
        if (isset($fieldConfig['env'])) {
            $env = env($fieldConfig['env']);
            $removeField = ($env != 1 && strtolower($env) != 'true' && strtolower($env) != 'yes');
        }
        return $removeField;
    }

    /**
     * Process comments for translation
     *
     * @param array $fieldConfig
     */
    protected static function processCommentEmbeddedTranslationKeys(array &$fieldConfig): void
    {
        if (isset($fieldConfig['comment']) && is_string($fieldConfig['comment'])) {
            $fieldConfig['comment'] = preg_replace_callback(
                "/([a-zA-Z0-9_\.]+::lang\.[a-zA-Z0-9_\.\-]+)/",
                function ($matches) {
                    return trans($matches[1]) ?: $matches[1];
                },
                $fieldConfig['comment']
            );
        }
    }

    protected static function processFields(array &$configFields, array &$subConfigFields, string $fieldName, string $modelClass = NULL): void
    {
        $inserts = array();
        foreach ($subConfigFields as $subFieldName => $subFieldConfig) {
            // TODO: Nested 1from1 relations
            $subType           = (isset($subFieldConfig['type']) ? $subFieldConfig['type'] : 'text');
            $includeContext    = (isset($subFieldConfig['includeContext']) ? $subFieldConfig['includeContext'] : 'include');
            $isPseudoFieldName = (substr($subFieldName, 0, 1) == '_');

            if ($subFieldName != 'id' && $includeContext != 'no-include') {
                // Config changes
                // TODO: support relation and fileupload fields on create
                if ($subType == 'relation' || $subType == 'fileupload') $subFieldConfig['context'] = array('update');
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
                $nestLevel         = $subFieldConfig['nestLevel'] ?? 0;
                $subFieldConfig['nested']    = TRUE;
                $subFieldConfig['nestLevel'] = $nestLevel+1;
                $subFieldConfig['included']  = TRUE;
                if (!$modelClass || !self::settingRemove($subFieldConfig, $modelClass))
                    $inserts[$nestedFieldName] = $subFieldConfig;
            }
        }

        // Insert before $fieldName
        self::array_insert($configFields, $fieldName, $inserts);
    }
}
