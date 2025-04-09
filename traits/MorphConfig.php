<?php namespace AcornAssociated\Traits;

use AcornAssociated\Behaviors\RelationController;
use Winter\Storm\Html\Helper as HtmlHelper;
use Winter\Storm\Database\Relations\BelongsTo;
use \Exception;
use BackendAuth;
use Str;

Trait MorphConfig
{
    public function makeConfig($configFile = [], $requiredConfig = [])
    {
        $config        = parent::makeConfig($configFile, $requiredConfig);
        $parentModel   = post(RelationController::PARAM_PARENT_MODEL);
        $parentModelId = post(RelationController::PARAM_PARENT_MODEL_ID);
        $primaryModel  = @$this->controller->widget->form->model;
        $modelClass    = ($this instanceof RelationController && $this->relationModel
            ? get_class($this->relationModel) 
            : $this->getConfig('modelClass')
        );
        $user = BackendAuth::user();

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

                    if ($parentModel) {
                        // ------------------------------- Auto-hide and set parent model drop-down
                        // We have a parent context
                        // TODO: This does not work if the parent field is nested
                        foreach ($config->fields as $fieldName => &$fieldConfig) {
                            // Look for a parent model selector
                            if (isset($fieldConfig['type']) 
                                && $fieldConfig['type'] == 'dropdown'
                                && isset($fieldConfig['options'])
                            ) {
                                // Only works for create-system standard drop-down specification
                                $optionsParts = explode('::', $fieldConfig['options']);
                                $model        = $optionsParts[0];

                                if ($model == $parentModel) {
                                    // Set and hide parentModel
                                    $fieldConfig['cssClass'] .= ' hidden';
                                    $fieldConfig['default']   = $parentModelId;
                                } 
                                else if ($primaryModel) {
                                    // Set and hide primaryModel
                                    if ($model == get_class($primaryModel)) {
                                        $fieldConfig['cssClass'] .= ' hidden';
                                        $fieldConfig['default']   = $primaryModel->id;
                                    }
                                    // Set and hide common singular parent BelongsTo models
                                    else if ($sameParent = $primaryModel->$fieldName) {
                                        if ($model == get_class($sameParent)
                                            && $primaryModel->$fieldName() instanceof BelongsTo
                                        ) {
                                            $fieldConfig['cssClass'] .= ' hidden';
                                            $fieldConfig['default']   = $sameParent->id;
                                        }
                                    }
                                } 
                            }
                        }
                        
                        // ------------------------------------- Auto-hide parent model reverse relation managers
                        // so that X-X relations do not have repeating relationmanager popup loops
                        // TODO: Secondary tabs
                        if (isset($config->tabs['fields'])) {
                            foreach ($config->tabs['fields'] as $fieldName => &$fieldConfig) {
                                if (   isset($fieldConfig['type']) 
                                    && isset($fieldConfig['relatedModel'])
                                    && $fieldConfig['type'] == 'relationmanager'
                                    && $fieldConfig['relatedModel'] == $parentModel
                                ) {
                                    unset($config->tabs['fields'][$fieldName]);
                                }
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
                            
                            if (     isset($fieldConfig['path'])) $path = $fieldConfig['path'];
                            else if (isset($fieldConfig['includeModel'])) {
                                $modelClass = $fieldConfig['includeModel'];
                                $model      = new $modelClass;
                                $modelDir   = $model->modelDirectoryPathRelative();
                                $path       = "$modelDir/$configFileName";
                            }
                            
                            if ($path) $subConfigs[$fieldName] = $this->makeConfig($path, $requiredConfig);
                            else throw new Exception("Include directive without path or model in [$fieldName]");
                            $subConfigs[$fieldName]->modelClass = $modelClass;
                        }
                    }

                    // Inject fields and tabs
                    foreach ($subConfigs as $fieldName => $subConfig) {
                        if (property_exists($subConfig, 'fields')) self::processFields($config->fields, $subConfig->fields, $fieldName, $subConfig->modelClass);
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

                    // ------------------------------------------------- permission-settings
                    // The permission name can be qualified _or_ un-qualified
                    // If it is un-qualified then the permission is understood as from this plugin
                    // permission-settings:
                    //     NOT=legalcases__legalcase_type_id__update@update:
                    //         field:
                    //              readOnly: true
                    //              disabled: true
                    //              type: dropdown
                    //         labels:
                    //              en: My Permission
                    if (property_exists($config, 'fields')) {
                        foreach ($config->fields as $fieldName => &$fieldConfig) {
                            if (isset($fieldConfig['permissionSettings'])) {
                                foreach ($fieldConfig['permissionSettings'] as $permissionDirective => &$permissionSettings) {
                                    $typeParts = explode('=', $permissionDirective);
                                    $negation  = FALSE;
                                    if (count($typeParts) == 2) {
                                        if ($typeParts[0] == 'NOT') $negation = TRUE;
                                        $permissionDirective = $typeParts[1];
                                    }
                                    $contextParts = explode('@', $permissionDirective);
                                    $permContext  = NULL;
                                    if (count($contextParts) == 2) {
                                        $permContext         = $contextParts[1];
                                        $permissionDirective = $contextParts[0];
                                    }

                                    // Conditionally qualify the permission name
                                    // $qualifiedPermissionName = $permissionDirective;
                                    // $isQualifiedName = (strstr($qualifiedPermissionName, '.') !== FALSE);
                                    // if (!$isQualifiedName) {
                                    //     $pluginDotPath = $modelClass::pluginAuthorDotPlugin();
                                    //     $qualifiedPermissionName = "$pluginDotPath.$qualifiedPermissionName";
                                    // }

                                    // Check access
                                    $hasAccess = $user->hasAccess($permissionDirective);
                                    $isContext = (is_null($permContext) || (property_exists($this, 'context') && $permContext == $this->context));

                                    if ($isContext && ($negation ? !$hasAccess : $hasAccess)) {
                                        if (isset($permissionSettings['field'])) {
                                            foreach ($permissionSettings['field'] as $setting => $value) {
                                                $setting = preg_replace('/^field-/', '', $setting);
                                                $setting = Str::camel($setting);
                                                $fieldConfig[$setting] = $value;
                                            }
                                        }
                                    }
                                }
                            }
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
                                    $isAlreadyNested   = self::isNested($subFieldName);
                                    $isPseudoFieldName = self::isPseudo($subFieldName);
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

    protected static function arrayToName(array $fieldPath): string
    {
        $fieldName = $fieldPath[0];
        if (count($fieldPath) > 1) {
            $fieldNests = implode('][', array_slice($fieldPath, 1));
            $fieldName .= "[$fieldNests]";
        }
        return $fieldName;
    }

    protected static function nestField(string|array $nest, string|array $fieldName, int &$nestlevel = NULL): string
    {
        if (!is_array($nest))      $nest      = HtmlHelper::nameToArray($nest);
        if (!is_array($fieldName)) $fieldName = HtmlHelper::nameToArray($fieldName);
        $nestedFieldPath = array_merge($nest, $fieldName);
        $nestlevel       = count($nestedFieldPath);
        return self::arrayToName($nestedFieldPath);
    }

    protected static function isNested(string $fieldName): bool
    {
        return (count(HtmlHelper::nameToArray($fieldName)) > 1);
    }

    protected static function isPseudo(string $fieldName): bool
    {
        return ($fieldName && $fieldName[0] == '_');
    }

    protected static function processFields(array &$configFields, array &$subConfigFields, string $fieldName, string $modelClass = NULL): void
    {
        $inserts = array();
        foreach ($subConfigFields as $subFieldName => $subFieldConfig) {
            // TODO: Nested 1from1 relations
            $subType           = (isset($subFieldConfig['type']) ? $subFieldConfig['type'] : 'text');
            $includeContext    = (isset($subFieldConfig['includeContext']) ? $subFieldConfig['includeContext'] : 'include');
            $isPseudoFieldName = self::isPseudo($subFieldName);

            if ($subFieldName != 'id' && $includeContext != 'no-include') {
                // Config changes
                // TODO: support relation and fileupload fields on create
                // Currently relation & fileupload will crash 
                // because the relations are null on create
                if ($subType == 'relation') {
                    if (isset($subFieldConfig['options'])) {
                        // This relation has been designed for a dropdown scenario also
                        // by additionally indicating the options during create
                        // TODO: Can this be auto-generated? We have:
                        //   get_class((new $modelClass)->$fieldName()->getRelated()) . '::dropdownOptions'
                        $subFieldConfig['type'] = 'dropdown';
                    } else {
                        // We cannot support embedded relation fields in create mode
                        $subFieldConfig['context'] = array('update');
                    }
                }
                else if ($subType == 'fileupload') {
                    // TODO: support emedded fileupload fields on create
                    $subFieldConfig['context'] = array('update');
                }

                if (isset($subFieldConfig['dependsOn'])) {
                    $dependsOn = $subFieldConfig['dependsOn'];
                    foreach ($dependsOn as $i => $dependsOnField) {
                        $isDependsPseudoFieldName = self::isPseudo($dependsOnField);
                        if (!$isDependsPseudoFieldName)
                            $subFieldConfig['dependsOn'][$i] = self::nestField($fieldName, $dependsOnField);
                    }
                }

                // Nesting in to existing form
                $nestedFieldName   = ($isPseudoFieldName ? $subFieldName : self::nestField($fieldName, $subFieldName));
                $formNestLevel     = $subFieldConfig['nestLevel'] ?? 0;
                $subFieldConfig['nested']    = TRUE;
                $subFieldConfig['nestLevel'] = $formNestLevel+1;
                $subFieldConfig['included']  = TRUE;
                if (!$modelClass || !self::settingRemove($subFieldConfig, $modelClass))
                    $inserts[$nestedFieldName] = $subFieldConfig;
            }
        }

        // Insert before $fieldName
        self::array_insert($configFields, $fieldName, $inserts);
    }
}
