<?php namespace Acorn\Traits;

use Acorn\Behaviors\RelationController;
use Winter\Storm\Html\Helper as HtmlHelper;
use Winter\Storm\Database\Relations\BelongsTo;
use Illuminate\Support\Facades\Session;
use \Exception;
use BackendAuth;
use Str;
// For debug output
use Yaml;
use File;

Trait MorphConfig
{
    public function makeConfig($configFile = [], $requiredConfig = [])
    {
        $debugOutput     = FALSE;
        $config          = parent::makeConfig($configFile, $requiredConfig);
        $controllerModel = @$this->controller->widget->form->model;
        // Popup situations, with parent model context
        $parentModel     = post(RelationController::PARAM_PARENT_MODEL);
        $parentModelId   = post(RelationController::PARAM_PARENT_MODEL_ID);
        // RelationManager aware
        $modelClass      = ($this instanceof RelationController && $this->relationModel
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

                    // ------------------------------------------------- Advanced
                    if (isset($_GET['advanced'])) Session::put('advanced', ($_GET['advanced'] == '1'));
                    $advanced = Session::get('advanced');
                    
                    if (isset($config->fields)) {
                        foreach ($config->fields as $name => &$fieldConfig) {
                            if (isset($fieldConfig['advanced']) && $fieldConfig['advanced']) {
                                if ($advanced) {
                                    $cssClass = (isset($fieldConfig['cssClass']) ? $fieldConfig['cssClass'] : '');
                                    $fieldConfig['cssClass'] = "$cssClass advanced";
                                } else {
                                    unset($config->fields[$name]);
                                }
                            }
                        }
                    }
                    if (isset($config->tabs['fields'])) {
                        foreach ($config->tabs['fields'] as $name => &$fieldConfig) {
                            if (isset($fieldConfig['advanced']) && $fieldConfig['advanced']) {
                                if ($advanced) {
                                    $cssClass = (isset($fieldConfig['cssClass']) ? $fieldConfig['cssClass'] : '');
                                    $fieldConfig['cssClass'] = "$cssClass advanced";
                                } else {
                                    unset($config->tabs['fields'][$name]);
                                }
                            }
                        }
                    }
                    if (isset($config->secondaryTabs['fields'])) {
                        foreach ($config->secondaryTabs['fields'] as $name => &$fieldConfig) {
                            if (isset($fieldConfig['advanced']) && $fieldConfig['advanced']) {
                                if ($advanced) {
                                    $cssClass = (isset($fieldConfig['cssClass']) ? $fieldConfig['cssClass'] : '');
                                    $fieldConfig['cssClass'] = "$cssClass advanced";
                                } else {
                                    unset($config->secondaryTabs['fields'][$name]);
                                }
                            }
                        }
                    }
                    if (isset($config->tertiaryTabs['fields'])) {
                        foreach ($config->tertiaryTabs['fields'] as $name => &$fieldConfig) {
                            if (isset($fieldConfig['advanced']) && $fieldConfig['advanced']) {
                                if ($advanced) {
                                    $cssClass = (isset($fieldConfig['cssClass']) ? $fieldConfig['cssClass'] : '');
                                    $fieldConfig['cssClass'] = "$cssClass advanced";
                                } else {
                                    unset($config->tertiaryTabs['fields'][$name]);
                                }
                            }
                        }
                    }

                    // ------------------------------- Auto-hide and set parent model drop-down
                    // TODO: This does not work if the parent field is 1-1 nested
                    // e.g. Student.user[languages] => user_user_language.user (student)
                    foreach ($config->fields as $fieldName => &$fieldConfig) {
                        // Look for a parent model selector
                        $dropDownModel = NULL;
                        
                        // type: dropdown + options call
                        // Create-system standard drop-down specification
                        // options: Acorn\University\Models\Student::dropdownOptions
                        if (isset($fieldConfig['type']) 
                            && $fieldConfig['type'] == 'dropdown'
                            && isset($fieldConfig['options'])
                            && is_string($fieldConfig['options'])
                        ) {
                            $optionsParts  = explode('::', $fieldConfig['options']);
                            $dropDownModel = $optionsParts[0];
                        } 

                        // Yaml configs often use type: relation
                        // type: relation
                        else if (isset($fieldConfig['type']) 
                            && $fieldConfig['type'] == 'relation'
                            && $modelClass 
                        ) {
                            $model = new $modelClass();
                            if ($model->hasRelation($fieldName)
                                && ($relation = $model->$fieldName())
                                && ($relation instanceof BelongsTo)
                            ) {
                                $dropDownModel = get_class($relation->getRelated());
                            }
                        }

                        if ($dropDownModel) { 
                            // Set and hide parentModel
                            // can be useful in nested popups
                            if ($parentModel
                                && $dropDownModel == $parentModel
                            ) {
                                $fieldConfig['cssClass'] .= ' hidden';
                                $fieldConfig['default']   = $parentModelId;
                            } 
                            
                            // Set and hide the main controllerModel
                            else if ($controllerModel 
                                && $dropDownModel == get_class($controllerModel)
                            ) {
                                $fieldConfig['cssClass'] .= ' hidden';
                                $fieldConfig['default']   = $controllerModel->id;
                            }
                            
                            // Set and hide common singular parent BelongsTo models
                            // Student->user has common with UserUserLanguage->user
                            // when adding XfromXSemi languages
                            // We need the actual controller object
                            else if ($controllerModel
                                && $controllerModel->hasRelation($fieldName)
                                && ($relation = $controllerModel->$fieldName())
                                && ($relation instanceof BelongsTo)
                                && ($controllerFieldModel = $controllerModel->$fieldName)
                                && ($dropDownModel == get_class($controllerFieldModel))
                            ) {
                                $fieldConfig['cssClass'] .= ' hidden';
                                $fieldConfig['default']   = $controllerFieldModel->id;
                            }
                        }
                    }
                        
                    // ------------------------------------- Auto-hide parent model reverse relation managers
                    // so that X-X relations do not have repeating relationmanager popup loops
                    // TODO: Secondary tabs
                    if ($parentModel && isset($config->tabs['fields'])) {
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

                    break;
            }
        }

        if (env('APP_DEBUG') && \is_string($configFile) && $debugOutput) {
            // Copied from parent::makeConfig()
            if (isset($this->controller) && method_exists($this->controller, 'getConfigPath')) {
                $configFile = $this->controller->getConfigPath($configFile);
            }
            else {
                $configFile = $this->getConfigPath($configFile);
            }

            $configFile = str_replace('.yaml', '_morphed.yaml', $configFile);
            try {
                File::put($configFile, Yaml::render((array)$config));
            } catch (Exception $ex) {}

            // Debug checks, e.g. relation validity
            if ($modelClass) {
                $model = new $modelClass(); 
                $validRelations = array_merge($model->belongsTo, $model->hasMany);
                if (property_exists($model, 'hasManyDeep')) 
                    $validRelations = array_merge($validRelations, $model->hasManyDeep);
                $fieldList = array();
                if (property_exists($config, 'columns')) $fieldList = $config->columns;
                if (property_exists($config, 'fields'))  $fieldList = $config->fields;
                foreach ($fieldList as $fieldName => $fieldConfig) {
                    if (isset($fieldConfig['relation'])) {
                        $relationName = $fieldConfig['relation'];
                        if (strstr($relationName, '[') !== FALSE)
                            throw new Exception("[$modelClass] config contains a [$relationName] nested syntax relation name");
                        if (!isset($validRelations[$relationName]))
                            throw new Exception("[$modelClass] class does not have a [$relationName] relation");
                    }
                }
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
        // TODO: This will fail for create-system embedded 1-1 fields
        // inherited from a belongsTo Model
        // like Student belongsTo User with password: setting: has_front_end
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
        $removeField   = FALSE;
        if ($settingsClass && isset($fieldConfig['setting']) && $settingsClass::get($fieldConfig['setting']) != '1')
            $removeField = TRUE;
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
