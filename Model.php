<?php namespace Acorn;

use Winter\Storm\Database\Model as BaseModel;
use Winter\Storm\Database\Pivot;
use BackendAuth;
use \Backend\Models\User;
use \Backend\Models\UserGroup;
use ApplicationException;
use Winter\Storm\Support\Facades\Schema;
use Illuminate\Support\Facades\Redirect;
use Winter\Storm\Exception\ValidationException;

use Illuminate\Support\Str;
use Acorn\Builder;
use Acorn\Collection;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use \Acorn\Relationships\HasManyDeep;

use Illuminate\Database\QueryException;
use Winter\Storm\Database\QueryBuilder;
use Acorn\Scopes\GlobalChainScope;
use DB;
use Request;
use Config;
use Carbon\Carbon;

use BadMethodCallException;
use Illuminate\Database\Eloquent\RelationNotFoundException;
use InvalidArgumentException;

use Illuminate\Support\Facades\Route;
use Backend\Classes\BackendController;
use Acorn\BackendRequestController;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;
use Request as BaseRequest;

// Allowed __get/set() caller classes
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Winter\Storm\Router\Helper;
use Backend\Widgets\Lists;
use Backend\Classes\ListColumn;
use Backend\Widgets\Form;
use Backend\Classes\FormField;
use Backend\Behaviors\FormController;
use Winter\Storm\Database\TreeCollection;
use Backend\Widgets\Filter;
use \Acorn\Backendlocalization\Class\TranslateBackend;

use Exception;
use Flash;

use Acorn\Events\UserNavigation;
use Acorn\Events\DataChange;
use Acorn\Events\ModelBeforeSave;
use Acorn\Events\ModelAfterSave;
use Acorn\Models\Server;

/*
class Saving {
    public function __construct(Model $eventPart)
    {
        // TODO: What shall we do with this class Saving?
        // Look at the Dispatcher::until() method
        //throw new ApplicationException($eventPart->name . " was here");
    }
}
*/

class Model extends BaseModel
{
    use Traits\PathsHelper;
    use Traits\DeepReplicates;
    use Traits\DirtyWriteProtection;
    use Traits\ObjectLocking;
    use Traits\PostGreSQLFieldTypeUtilities;
    use \Illuminate\Database\Eloquent\Concerns\HasUuids; // Always distributed
    use TranslateBackend;
    use \Staudenmeir\EloquentHasManyDeep\HasRelationships; // hasOneOrManyDeep()
    use \Staudenmeir\EloquentHasManyDeep\HasTableAlias;

    public const LE_DELETE_ON_NULL = 2;

    public $printable = FALSE;
    public static $globalScope;

    public $readOnly = FALSE; // For VIEWS

    // --------------------------------------------- Translation
    public $implement = ['Winter.Translate.Behaviors.TranslatableModel'];
    public $translatable = ['name', 'description'];

    // --------------------------------------------- Star schema centre => leaf services
    public function getLeafTypeAttribute(?bool $throwIfNull = FALSE)
    {
        return $this->getLeafTypeModel($throwIfNull)?->unqualifiedClassName();
    }

    public function getLeafTypeModel(?bool $throwIfNull = FALSE)
    {
        // For base tables that have multiple possible leaf detail tables in a star schema
        // we search the hasOne relation to determine which leaf table has the 1-1
        $leafObject = NULL;
        $thisName   = $this->unqualifiedClassName();

        $relations = array_merge($this->hasOneThrough, $this->hasOne);
        foreach ($relations as $name => &$relativeModel) {
            if (is_array($relativeModel) && isset($relativeModel['leaf']) && $relativeModel['leaf']) {
                $this->load($name);
                if ($leafObject = $this->$name) break;
            }
        }

        if ($throwIfNull && !$leafObject) throw new Exception("Leaf $thisName not found for id($this->id)");
        return $leafObject;
    }

    protected static function boot()
    {
        static::extend(function ($model) {
            // NestedTree will overwrite out parent relation
            // removing any custom attributes like name
            if (isset($model->belongsTo['parent']))
                $model->belongsTo['parent_copy'] = $model->belongsTo['parent'];
        });
        parent::boot();
    }

    protected static function booted()
    {
        // Use locally defined GlobalScope classes, e.g. YearScope
        if (static::$globalScope) static::addGlobalScope(new static::$globalScope());
        // Follow all global_scope=>TRUE relations
        else                      static::addGlobalScope(new GlobalChainScope());
    }

    /* This was an attempt to enforce Encapsulation and accessors in a learning environment
    protected function checkFrameworkCallerEncapsulation($attributeName)
    {
        if (env('APP_DEBUG') && FALSE) { // TODO: checkFrameworkCallerEncapsulation()
            $bt     = debug_backtrace();

            // This and Called (Our __get/set())
            $called = $bt[1]; // Our __get/set()
            $thisClass  = get_class($this);
            $aThisClass = explode('\\', $thisClass);
            $thisAuthor = $aThisClass[0];
            $thisPlugin = (isset($aThisClass[1]) ? $aThisClass[1] : NULL);
            $func       = $called['function']; // Our __get/set()

            // External caller, potentially an external class
            // ViewMaker can call without calling context
            $caller       = (count($bt) > 2 ? $bt[2] : []);
            $callerClass  = (isset($caller['class']) ? $caller['class'] : 'None');
            $aCallerClass = explode('\\', $callerClass);
            $callerAuthor = $aCallerClass[0];
            $callerPlugin = (isset($aCallerClass[1]) ? $aCallerClass[1] : NULL);
            $callerLine   = $called['line'];
            $isRelation   = (property_exists($this, 'relations') && isset($this->relations[$attributeName]));

            if (
                // Plugins
                   ! ($thisPlugin   == 'Calendar') // TODO: Rewrite calendar for Encapsulation
                && ! ($callerPlugin == 'Calendar')
                && ! $isRelation
                // Classes
                && ! is_a($callerClass, EloquentBuilder::class,   TRUE)
                && ! is_a($callerClass, Relation::class,   TRUE)
                && ! is_a($callerClass, Helper::class,     TRUE)
                && ! is_a($callerClass, Lists::class,      TRUE)
                && ! is_a($callerClass, ListColumn::class, TRUE)
                && ! is_a($callerClass, Form::class,       TRUE)
                && ! is_a($callerClass, FormField::class,  TRUE)
                && ! is_a($callerClass, Filter::class,     TRUE)
                && ! is_a($callerClass, FormController::class,    TRUE)
                && ! is_a($callerClass, TreeCollection::class,    TRUE)
                && strstr($callerClass, 'Listeners') === FALSE
                // TODO: Settings implements && \System\Behaviors\SettingsModel::class
                && ! is_a($callerClass, $thisClass,        TRUE)
                && ! is_a($thisClass, $callerClass,        TRUE)
                // Traits
                && $callerClass != 'Winter\Translate\Behaviors\TranslatableModel'
            ) {
                throw new Exception("Protected $thisClass::$func($attributeName) called by $callerPlugin/$thisPlugin $callerClass:$callerLine");
            }
        }
    }
    */

    public function getNameModel(bool $checkHasNameAttribute = FALSE): Model|NULL
    {
        // Returns $this if no relation with ['name' => TRUE]
        $model = $this;
        
        do {
            // 1-1 name relation support, including hasManyDeep 1-1 name
            $nameObjectRelation = NULL;
            $relations1to1Name  = array_merge($model->hasManyDeep, $model->belongsTo);
            foreach ($relations1to1Name as $relationName => &$relationConfig) {
                $isNameRelation = (is_array($relationConfig) 
                    && isset($relationConfig['name']) 
                    && $relationConfig['name'] === TRUE
                );
                if ($isNameRelation) {
                    $nameObjectRelation = $model->$relationName();
                    $model              = $nameObjectRelation->sole(); 
                    // There may be multiple, but we take the first
                    // ordered by HasManyDeep first
                    break;
                }
            }
        } while ($nameObjectRelation);

        return $model;
    }

    /*
    protected $dispatchesEvents = [
        'saving' => Saving::class, // TODO: Not used yet. See Saving event above
    ];
    */

    // --------------------------------------------- Advanced record control
    public function delete()
    {
        if ($user = BackendAuth::user())
            $this->unlock($user); // Does not save(), may throw ObjectIsLocked()

        // We also allow RelationShip 'delete' for belongsTo relations
        // https://wintercms.com/docs/v1.2/docs/database/relations#detailed-relation-methods
        foreach ($this->belongsTo as $name => $relation) {
            if (isset($relation['delete']) && $relation['delete'] && $this->$name) 
                $this->$name->delete();
        }

        return parent::delete();
    }

    public function isListEditable(): bool
    {
        return (bool) $this->listEditable;
    }

    public function save(?array $options = [], $sessionKey = null)
    {
        // Useful for auto-completing auto-relations
        // like created_by_user and created_by_event
        ModelBeforeSave::dispatch($this);

        // TODO: This should be only done with triggers and pg_hostname
        if ($this->hasAttribute('server_id') && !$this->server_id) {
            $this->server_id = Server::singleton()->id;
        }

        // Object locking
        if (!isset($options['UNLOCK']) || $options['UNLOCK'] == TRUE) {
            if ($user = BackendAuth::user())
                $this->unlock($user); // Does not save(), may throw ObjectIsLocked()
        }

        // Dirty Writing checks in fill() include a passed original updated_at field
        // but we do not want to override default behavior
        // This would error on create new
        if (!property_exists($this, 'timestamps') || $this->timestamps) $this->updated_at = NULL;

        $qe     = NULL;
        $result = NULL;
        try {
            $result = parent::save($options, $sessionKey);
        } 
        // Last chance error formatting for some presentable & understandable SQL problems
        catch (QueryException $qe) {
            $messageAdvanced = $qe->getMessage();
            $messageNice     = NULL;

            switch ($qe->getCode()) {
                case 23514:
                    // SQLSTATE[23514]: Check violation: 7 ERROR: new row for relation "acorn_finance_invoices" violates check constraint "payee_either_or"
                    if (preg_match_all('/relation "([^"]+)" violates check constraint "([^"]+)"/', $messageAdvanced, $matches) == 1) {
                        if (count($matches) == 3) {
                            $check       = $matches[2][0];
                            $title       = Str::headline($check);
                            $messageNice = trans('acorn::lang.errors.sql.23514', ['check' => $title]);
                        }
                    }
                    break;
                case 23502:
                    // NotNullConstraintViolationException
                    // SQLSTATE[23502]: Not null violation: 7 ERROR:  null value in column "number" of relation "acorn_finance_receipts" violates not-null constraint
                    if (preg_match_all('/ERROR:  null value in column "([^"]+)" of relation "([^"]+)"/', $messageAdvanced, $matches) == 1) {
                        if (count($matches) == 3) {
                            $column      = $matches[1][0];
                            $messageNice = trans('acorn::lang.errors.sql.23502', ['column' => $column]);
                        }
                    }
                    break;
                case 23505:
                    // SQLSTATE[23505]: Unique violation: 7 ERROR: duplicate key value violates unique constraint "course_semester_year_academic_year_material"
                    // DETAIL: Key (course_id, semester_year_id, academic_year_id, material_id)=(66d3ca90-1b6c-11f0-90cc-a77dd8e640be, 9c6e1d20-2bd1-11f0-8119-93a057070d34, 5afc781c-2b47-11f0-bc2a-0bdc97d6ed09, cdc800ae-28be-11f0-a8a6-334555029afd) already exists.
                    if (preg_match_all('/ERROR: +duplicate key value violates unique constraint +"([^"]+)"/', $messageAdvanced, $matches) == 1) {
                        if (count($matches) == 2) {
                            // Data %constraint is not unique
                            $constraint  = $matches[1][0];
                            $title       = Str::headline($constraint);
                            $messageNice = trans('acorn::lang.errors.sql.23505', ['constraint' => $title]); 
                        }
                    }
                    break;
            }

            if ($messageNice) {
                if (env('APP_DEBUG')) {
                    $messageNice .= "\nAdvanced: \n";
                    $messageNice .= $messageAdvanced;  
                }
                throw new ValidationException(['error' => $messageNice]);
            } else {
                // Completely unhandled
                // Throw original in dev and live env
                throw $qe;
            }
        }

        if (!$qe) {
            if (!isset($options['list-editable']) || $options['list-editable']) {
                self::listEditableSave();
            }

            // Useful for auto-completing auto-relations
            // like created_by_user and created_by_event
            ModelAfterSave::dispatch($this);
        }

        return $result;
    }
    
    public static function nextNewModelId(): int
    {
        static $nextNewModelId = 0;
        return $nextNewModelId++;
    }

    public function ordinalText(): string
    {
        // 1st|2nd|3rd|...
        return $this->ordinal . self::ordinal($this->ordinal);
    }

    public static function ordinal(int $value): string
    {
        // st|nd|th
        $ordinal = '';

        if ($value > 0) {
            $ordinals = ['th','st','nd','rd','th','th','th','th','th','th'];
            $ordinal  = $ordinals[$value % 10];
            if ((($value % 100) >= 11) && (($value % 100) <= 13)) $ordinal = 'th';
        }
        
        return $ordinal;
    }

    public function buildName(bool $html, string $delimeter, ...$nameModels): string
    {
        $name           = '';
        $useDelimeter   = TRUE;
        foreach ($nameModels as $model) {
            $modelName = NULL;
            $class     = 'no-value';

            if ($model) { // Guard from NULLs
                if ($model instanceof BaseModel) {
                    if ($html) $modelName  = ($model->htmlName ?: e($model->name));
                    else       $modelName  = $model->name;
                    $classParts = explode('\\', get_class($model));
                    $class      = Str::kebab(end($classParts));
                } else if (is_string($model)) {
                    $modelName   = e($model);
                    $class       = 'string';
                    $useDelimeter = FALSE;
                }

                if ($html) {
                    $link  = (method_exists($model, 'controllerUrl')
                        ? $model->controllerUrl('update')
                        : NULL
                    );
                    
                    // Callers job to not escape the HTML
                    if ($name && $modelName && $useDelimeter) $name .= "<span class='delimeter'>$delimeter</span>";
                    $name .= "<span class='$class'>";
                    if ($link) $name .= "<a href='$link'>";
                    $name .= $modelName; // pre-escaped
                    if ($link) $name .= '</a>';
                    $name .= "</span>";
                } else {
                    if ($name && $modelName && $useDelimeter) $name .= $delimeter;
                    // Callers job to escape the name
                    $name .= $modelName;
                }
                if ($model instanceof BaseModel) $useDelimeter = TRUE;
            }
        }

        return $name;
    }

    public function buildNameFromRelations(bool $html = FALSE, string $delimeter = '::'): string
    {
        $nameModels = array();
        foreach ($this->belongsTo as $name => &$config) {
            if (isset($config['name']) && $config['name']) 
                array_push($nameModels, $this->$name);
        }
        return $this->buildName($html, $delimeter, ...$nameModels);
    }
    
    public static function listEditableSave(): bool
    {
        static $processed = FALSE;
        $changes = FALSE;

        if ($listEditable = post('ListEditable')) {
            if (!$processed) {
                $processed = TRUE;

                $checked   = post('checked');
                foreach ($listEditable as $modelName => $models) {
                    foreach ($models as $id => $columns) {
                        if (!$checked || in_array($id, $checked)) {
                            // Int|NULL id indicates that the model is new
                            // Existing models use string UUIDs
                            $model = (is_null($id) || is_int($id) 
                                ? new $modelName() 
                                : $modelName::find($id)
                            );

                            if ($model) { // Con-currency check
                                // Process inputs into model
                                $delete = FALSE;
                                foreach ($columns as $name => &$value) {
                                    // Mode: TRUE|FALSE|Model::LE_DELETE_ON_NULL
                                    if ($value === "") $value = NULL;
                                    if (is_null($value)) {
                                        $mode = (isset($model->listEditable[$name]) ? $model->listEditable[$name] : TRUE);
                                        if ($mode === self::LE_DELETE_ON_NULL) $delete = TRUE;
                                    }
                                }

                                if ($delete) {
                                    // Delete models with NULL data
                                    // this is usually because the score=NULL
                                    $model->delete();
                                    $changes = TRUE;
                                } else {
                                    $model->fill($columns);
                                    if ($model->isDirty()) {
                                        // Validate
                                        $model->validate(); // Will throw
                                        $model->save(['list-editable' => FALSE]);
                                        $changes = TRUE;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return $changes;
    }

    // --------------------------------------------- New Relations
    // composer require staudenmeir/eloquent-has-many-deep # New Deep relations

    // New $hasManyDeep 1-1 => 1-X relation examples:
    /*
    public $hasManyDeep = [
        'legalcase_justice_scanned_documents_legalcase' => [
            \Acorn\Justice\Models\ScannedDocument::class,
            'throughRelations'    => ['legalcase', 'justice_scanned_documents_legalcase']
        ],
        'legalcase_justice_legalcase_identifiers_legalcase' => [
            \Acorn\Justice\Models\LegalcaseIdentifier::class,
            'throughRelations'    => ['legalcase', 'justice_legalcase_identifiers_legalcase']
        ],
    ];
    */

    // New $hasManyDeep 1-1 => X-X (with pivot table) relation examples:
    /*
    public $hasManyDeep = [
        'legalcase_justice_legalcase_legalcase_category_legalcases' => [
            \Acorn\Justice\Models\LegalcaseCategory::class,
            'throughRelations'    => ['legalcase', 'justice_legalcase_legalcase_category_legalcases']
        ],
    ];

    // This results in the following:
    HasManyDeep(object) {
        parent:         Acorn\Justice\Models\LegalCase
        related:        Acorn\Justice\Models\LegalCaseCategory
        throughParent:  Acorn\Justice\Models\LegalCase
        farParent:      Acorn\Criminal\Models\LegalCase
        firstKey:       id
        secondKey:      legalcase_id
        localKey:       legalcase_id
        secondLocalKey: id
        throughParents: [Acorn\Justice\Models\LegalCase, Pivot(acorn_justice_legalcase_legalcase_category)]
        foreignKeys:    [id, legalcase_id, id]
        localKeys:      [legalcase_id, id, legalcase_category_id]
    }
    */
    public $hasManyDeep = [];

    // VERSION: Winter 1.2.6=>7 changes the relationTypes format:
    // https://stackoverflow.com/questions/39034442/preprocessing-like-if-defined-in-php
    protected static $relationTypes = [
        'hasOne',
        'hasMany',
        'belongsTo',
        'belongsToMany',
        'morphTo',
        'morphOne',
        'morphMany',
        'morphToMany',
        'morphedByMany',
        'attachOne',
        'attachMany',
        'hasOneThrough',
        'hasManyThrough',
        // Ours added
        'hasManyDeep',
    ];
    
    // VERSION: Winter 1.2.6=>7 change of function signature: 
    // https://stackoverflow.com/questions/39034442/preprocessing-like-if-defined-in-php
    //   $addConstraints and Relation return type hint added?
    //   1.2.6: protected function handleRelation($relationName)
    //   1.2.7: protected function handleRelation(string $relationName, bool $addConstraints = true): Relation
    // And passing through the $addConstraints in the parent call below
    protected function handleRelation($relationName): Relation
    {
        $relationObj  = NULL;
        $relationType = $this->getRelationType($relationName);
        switch ($relationType) {
            case 'hasManyDeep':
                // Get relation configuration
                $relationConfig       = $this->validateRelationArgs($relationName, ['throughRelations']);
                $relatedModel         = $relationConfig[0] ?? NULL; // Optional double-check

                // Translate the Model relation name chain to Relation Objects (has*|belongsTo*...)
                $throughRelationObjects   = [];
                $throughRelationInstance  = $this;
                $lastRelation = NULL;
                foreach ($relationConfig['throughRelations'] as $throughRelationName) {
                    // Traverse the Model instances / classes for the relations
                    $throughRelationObject = $throughRelationInstance->$throughRelationName();
                    array_push($throughRelationObjects, $throughRelationObject);
                    $lastRelation = $throughRelationObject;
                    
                    // Traverse the actual instances for the loaded Models, until we meet a collection
                    // then traverse the Models only
                    // This is to ensure that $parent is set on the throughRelations for save()ing
                    $throughRelationInstance = $throughRelationInstance->$throughRelationName;
                    if (! $throughRelationInstance instanceof BaseModel) 
                        $throughRelationInstance  = $throughRelationObject->getRelated();
                }

                // If we have the optional double-check configuration clause then
                // check that the chain arrived at the same final model in $relationConfig[0]
                $finalModelClass = get_class($throughRelationInstance);
                if ($relatedModel && $finalModelClass != $relatedModel) {
                    throw new Exception("Final HasManyDeep relation [$relationName] final model [$finalModelClass] does not yield the stated config Model [$relatedModel]");
                }

                // Assemble parameters for HasManyDeep __constructor()
                // https://github.com/staudenmeir/eloquent-has-many-deep?tab=readme-ov-file#manytomany
                // Double many-to-many chain from Models & tables: 
                //   $user->hasManyDeep(Permission::class, ['role_user', Role::class, 'permission_role']);
                //     return $this->newHasManyDeep(...$this->hasOneOrManyDeep($related, $through, $foreignKeys, $localKeys));
                //       return new HasManyDeep($query, $farParent, $throughParents, $foreignKeys, $localKeys);
                //   Note the 2 x stated intermediate pivot tables at each stage
                //
                // Double many-to-many chain from Relation Objects:
                //   $user->hasManyDeepFromRelations($user->roles(), (new Role)->permissions()) 
                //     $user->hasManyDeep(...$this->hasOneOrManyDeepFromRelations($relations));
                //       return $this->newHasManyDeep(...$this->hasOneOrManyDeep($related, $through, $foreignKeys, $localKeys));
                //         return new HasManyDeep($query, $farParent, $throughParents, $foreignKeys, $localKeys);
                [
                    $related, // string
                    $through,
                    $foreignKeys,
                    $localKeys,
                    $postGetCallbacks,
                    $customThroughKeyCallback,
                    $customEagerConstraintsCallback,
                    $customEagerMatchingCallback
                ]          = $this->hasOneOrManyDeepFromRelations($throughRelationObjects);
                $farParent = $this;
                // This may return an AA Builder or a Winter Builder
                // depending on the Model being passed in
                $query     = $this->newRelatedInstance($finalModelClass)->newQuery();

                // Translate strings to EMPTY Models or Pivot->setTable()s
                // Pivot->setTable() is used when the intermediate table has no associated Model
                $throughParents = [];
                foreach ($through as $i => $throughEntry) {
                    // This just creates an new empty Model() or a Pivot($table)
                    $model = $this->newRelatedDeepThroughInstance($throughEntry);
                    // TODO: For now, we turn timestamps OFF for AA Pivot situations
                    if ($model instanceof Pivot) $model->timestamps = FALSE;
                    // Aliases to prevent repeating model table duplication in the SQL from clause
                    if (method_exists($model, 'setAlias')) 
                        $model->setAlias("{$model->table}_$i");
                    array_push($throughParents, $model);
                }

                // Create relation object
                $relationObj = new HasManyDeep(
                    $query,
                    $farParent, // $this
                    $throughParents, // $parent = $throughParents[0]
                    $foreignKeys,
                    $localKeys,
                    $throughRelationObjects, // Extra parameter for us, useful for saving
                    $relationName // Extra parameter for Winter Relationships
                );

                /* TODO: customizeHasOneOrManyDeepRelationship()
                $relationObj = $this->customizeHasOneOrManyDeepRelationship(
                    $relationObj,
                    $postGetCallbacks,
                    $customThroughKeyCallback,
                    $customEagerConstraintsCallback,
                    $customEagerMatchingCallback
                );
                */
                break;
            default:
                // VERSION: Winter:1.2.6=>7: + $addConstraints
                $relationObj = parent::handleRelation($relationName); 
        }

        return $relationObj;
    }

    protected function validateRelationArgs($relationName, $optional, $required = [])
    {
        // VERSION: Copied from Winter 1.2.6=>7
        // because HasManyDeep processing requires it
        // left in now for VERSION 1.2.6 also as it is the same anyway
        $relation = $this->getRelationDefinition($relationName);

        // Query filter arguments
        $filters = ['scope', 'conditions', 'order', 'pivot', 'timestamps', 'push', 'count', 'default'];

        foreach (array_merge($optional, $filters) as $key) {
            if (!array_key_exists($key, $relation)) {
                $relation[$key] = null;
            }
        }

        $missingRequired = [];
        foreach ($required as $key) {
            if (!array_key_exists($key, $relation)) {
                $missingRequired[] = $key;
            }
        }

        if ($missingRequired) {
            throw new InvalidArgumentException(sprintf(
                'Relation "%s" on model "%s" should contain the following key(s): %s',
                $relationName,
                get_called_class(),
                implode(', ', $missingRequired)
            ));
        }

        return $relation;
    }

    protected function newRelatedDeepThroughInstance(string $class): BaseModel
    {
        // Staudenmeir\EloquentHasManyDeep\HasRelationships::newRelatedDeepThroughInstance()
        // Overridden to return a Storm Pivot
        // Copied
        return str_contains($class, '\\')
            ? (method_exists($this, 'newRelatedThroughInstance') // TODO[L10]
                ? $this->newRelatedThroughInstance($class) // new $class
                : new $class())
            : (new Pivot())->setTable($class);
    }

    // --------------------------------------------- Querying
    public function newEloquentBuilder($query): Builder
    {
        // Acorn Builder extensions
        // Ensure we remain in the family
        // causes chained queries to always work with our Builder
        return new Builder($query);
    }

    public function newCollection(array $models = [])
    {
        return new Collection($models);
    }

    public static function whereBelongsToAny(Array $relatedArray, ?string $boolean = 'or', ?bool $throwOnEmpty = FALSE): Builder
    {
        // Here we set all columns explicitly
        // because all queries in a union must have the same number of columns
        // * is not acceptable
        $allFields = self::getQualifiedColumnListing();
        return self::select($allFields)->whereBelongsToAny($relatedArray, $boolean, $throwOnEmpty);
    }

    public static function whereBelongsToAnyThrough(string|Array $through, Array $relatedArray, ?string $boolean = 'or', ?bool $throwOnEmpty = FALSE): Builder
    {
        // Here we set columns to id
        // because all queries in a union must have the same number of columns
        // * is not acceptable
        $allFields = self::getQualifiedColumnListing();
        return self::select($allFields)->whereBelongsToAnyThrough($through, $relatedArray, $boolean, $throwOnEmpty);
    }

    public static function whereBelongsToMany(Collection|BaseModel $related, ?string $relationshipName = NULL, ?string $boolean = 'or', ?bool $throwOnEmpty = FALSE): Builder
    {
        return self::select()->whereBelongsToMany($related, $relationshipName, $boolean, $throwOnEmpty);
    }

    // --------------------------------------------- Replication
    public function dbSequenceName()
    {
        $ret = NULL;
        $tableSpec   = explode('.', $this->getTable());
        $tableName   = end($tableSpec);
        $tableSchema = (count($tableSpec) > 1 ? $tableSpec[0] : 'public');

        $SQL = <<<SQL
            SELECT S.relname::regclass
                FROM pg_class AS S
                    inner join pg_depend    AS D on S.oid = D.objid
                    inner join pg_class     AS T on D.refobjid = T.oid
                    inner join pg_attribute AS C on D.refobjid = C.attrelid and D.refobjsubid = C.attnum
                    inner join pg_tables    AS PGT on T.relname = PGT.tablename
                WHERE S.relkind = 'S'
                    AND T.relname = '$tableName'
                    AND PGT.schemaname = '$tableSchema';
SQL;
        $res = DB::select(DB::raw($SQL));
        if (count($res)) $ret = $res[0]->relname;

        return $ret;
    }

    public function dbSequenceCurrval()
    {
        $ret = NULL;

        if ($seqName = $this->dbSequenceName()) {
            $res = DB::select(DB::raw("select last_value from $seqName"));
            if (count($res)) $ret = $res[0]->last_value;
        }

        return $ret;
    }

    public static function dbReplicationSlots(?string $dbConnectionName = NULL)
    {
        $connections = config('database.connections');
        if (isset($connections['replication_publisher'])) $dbConnectionName = 'replication_publisher';
        return DB::connection($dbConnectionName)->select(DB::raw("select * from pg_replication_slots"));
    }

    public static function dbSubscriptions()
    {
        return DB::select(DB::raw("select * from pg_subscription"));
    }

    public static function dbPublications()
    {
        return DB::select(DB::raw("select * from pg_publication"));
    }

    public static function dbSettings(string $filter = '')
    {
        $settings = [];
        $file     = file('/etc/postgresql/16/main/postgresql.conf');
        foreach ($file as $line) {
            if (strchr($line, '=') && substr($line, 0, 1) != '#') {
                $line = preg_replace('/#.*/', '', $line);
                if (!$filter || preg_match("/$filter/", $line)) {
                    $aClause = explode('=', $line);
                    $setting = new \stdClass();
                    $setting->name  = trim($aClause[0]);
                    $setting->value = trim($aClause[1]);
                    array_push($settings, $setting);
                }
            }
        }

        return $settings;
    }

    public static function dbLog(int $numEntries = 20, string $filter = ''): array
    {
        $log = array_reverse(file('/var/log/postgresql/postgresql-16-main.log'));
        return array_reverse(array_splice($log, 0, $numEntries));
    }

    public function renderReplicationMonitor()
    {
        print('<div class="debug-block">');

        print('<div>');
            // --------------------------------------------------------
            $dbSequenceCurrval = $this->dbSequenceCurrval();
            if ($dbSequenceCurrval) {
                print('<h2>Stats on local</h2>');
                print('<table>');
                if ($dbSequenceCurrval) {
                    $action = '<a href="#">reset all sequences</a>';
                    print("<tr><td>Brand::sequenceCurrval:</td><td class='debug-number'>$dbSequenceCurrval</td><td>$action</td></tr>");
                }
                print('</table>');
            }

            // --------------------------------------------------------
            print('<h2>Replication-slots on publisher</h2>');
            print('<table>');
            foreach (self::dbReplicationSlots() as $slot) {
                $active = ($slot->active ? 'active' : 'in-active');
                $action = ($slot->active ? '' : '<a href="#">activate</a>');
                print("<tr><td>$slot->slot_name:</td><td class='$active'>$active</td><td>$action</td></tr>");
            }
            print('</table>');

            // --------------------------------------------------------
            print('<h2>Subscriptions on local</h2>');
            print('<table>');
            foreach (self::dbSubscriptions() as $sub) {
                $enabled = ($sub->subenabled ? 'enabled' : 'disabled');
                $action  = ($enabled ? '' : '<a href="#">enable</a>');
                print("<tr><td>$sub->subname:</td><td class='$enabled'>$enabled</td><td>$action</td></tr>");
            }
            print('</table>');

            // --------------------------------------------------------
            print('<h2>Publications on local</h2>');
            print('<table>');
            foreach (self::dbPublications() as $pub) {
                print("<tr><td>$pub->pubname:</td></tr>");
            }
            print('</table>');
        print('</div>');


        // --------------------------------------------------------
        print('<div><h2>PostGres Settings on local</h2>');
        print('<table id="db-settings">');
        foreach (self::dbSettings('.*wal.*|.*listen.*|.*port.*') as $setting) {
            print("<tr class='setting-$setting->name'><td class='name'>$setting->name:</td><td class='value value-$setting->value'>$setting->value</td></tr>");
        }
        print('</table></div>');

        // --------------------------------------------------------
        print('<div><h2>Logs</h2>');
        print('<div id="logs"><table>');
        foreach (self::dbLog() as $line) {
            $line  = preg_replace('/ ([A-Z]+): /', ' <span class="\1">\1</span>: ', $line);
            $line  = preg_replace('/ ([0-9]{2}:[0-9]{2}):([0-9]{2}.[0-9]{3}) \+/', ' <span class="time">\1</span>:<span class="time-precise">\2</span> +', $line);
            print("<tr><td>$line</td></tr>");
        }
        print('</table></div></div>');

        // --------------------------------------------------------
        // TODO: Websocket dashboard
        // https://beyondco.de/docs/laravel-websockets/debugging/dashboard
        print('<div><h2>Websockets - <a href="/laravel-dashboard">view dashboard</a></h2>');
        print('<div id="websockets"><table>');
        foreach (config('websockets.apps')[0] as $name => $value) {
            print("<tr><td>$name:</td><td>$value</td></tr>");
        }
        print('</table></div></div>');

        print('<p style="clear:both;" />');

        print('</div>');
    }


    // --------------------------------------------- Hierarchies
    public function getParentId()
    {
        $this->load('parent');
        return $this->parent?->id;
    }

    public function getChildren()
    {
        $this->load('children');
        return $this->children;
    }


    // --------------------------------------------- Forms
    protected static function getQualifiedColumnListing()
    {
        $model   = self::getModel();
        $table   = $model->getTable();
        $columns = Schema::getColumnListing($table);
        return $model->qualifyColumns($columns);
    }

    public static function dropdownOptions($form, $field, $optionsModel = NULL)
    {
        $optionsModel = ($optionsModel ?:
            (isset($field->config['optionsModel'])
                ? $field->config['optionsModel']
                : NULL
            )
        );
        $models = ($optionsModel ? $optionsModel::all() : static::all());

        $name = (isset($field->config['nameFrom'])
            ? $field->config['nameFrom']
            : 'name'
        );

        // Hierarchies
        $hierarchical = (isset($field->config['hierarchical'])
            ? $field->config['hierarchical']
            : isset($models->first()?->hasMany['children'])
        );
        $indentationString = (isset($field->config['indentation-string'])
            ? $field->config['indentation-string']
            : "--&nbsp;"
        );
        $ancestor = (isset($field->config['ancestor'])
            ? $field->config['ancestor']
            : NULL
        );

        // Simple where options
        // options: Acorn\Lojistiks\Models\ProductInstance::dropdownOptions
        //     where:
        //       uses_quantity: false
        if (isset($field->config['where'])) {
            foreach ($field->config['where'] as $property => $value) {
                // Simple fixed property
                // array configs are dynamic, handled below in filterFields()
                if (!is_array($value)) {
                    $models = $models->where($property, $value);
                }
            }
        }

        // Hierarchies:
        //   hierarchy: false|true|reverse
        //   indentation_character: -
        //   start-model: x
        if ($hierarchical) {
            if ($ancestor) $models = [$ancestor];
            $treeCollection = new TreeCollection($models);
            $nested = $treeCollection->toNested(FALSE);
            $list   = $treeCollection->listsNested($name, 'id', $indentationString);
        } else {
            $list = $models->lists($name, 'id');
        }

        return $list;
    }

    public function actionFunctions(string $type = NULL, string $fnName = NULL): array {
        // Direct model action functions
        $actionFunctions = ($this->actionFunctions ?: array());
        foreach ($actionFunctions as $name => &$actionFunctionDefinition) {
            $condition = $actionFunctionDefinition['condition'] ?? NULL; 
            if ((  is_null($type) 
                || (isset($actionFunctionDefinition['type']) && $type == $actionFunctionDefinition['type'])
                || (!isset($actionFunctionDefinition['type']) && $type == 'row')
                )
                && (!$condition || $this::where('id', $this->id)->whereRaw($condition)->count() != 0)
            ) {
                // Populate the Model/Id for correct lookup later
                $actionFunctionDefinition['model']    = get_class($this);
                $actionFunctionDefinition['model_id'] = $this->id;
            } else {
                unset($actionFunctions[$name]);
            }
        }

        // Inherit 1to1 BelongsTo relations model action functions
        foreach ($this->belongsTo as $name => $relationDefinition) {
            if (isset($relationDefinition['type']) && $relationDefinition['type'] == '1to1') {
                $this->load($name);
                if ($relatedModel = $this->getRelation($name)) {
                    if ($relatedModel->actionFunctions) {
                        // Existing actions take precedence
                        // Write the sub-model id
                        foreach ($relatedModel->actionFunctions as $name => &$actionFunctionDefinition) {
                            $condition = $actionFunctionDefinition['condition'] ?? NULL;
                            if ((  is_null($type) 
                                || (isset($actionFunctionDefinition['type']) && $type == $actionFunctionDefinition['type'])
                                || (!isset($actionFunctionDefinition['type']) && $type == 'row')
                                )
                                && (!$condition || $relatedModel::where('id', $relatedModel->id)->whereRaw($condition)->count() != 0)
                            ) {
                                // Populate the Model/Id for correct lookup later
                                $actionFunctionDefinition['model']    = get_class($relatedModel);
                                $actionFunctionDefinition['model_id'] = $relatedModel->id;
                                $actionFunctions[$name] = $actionFunctionDefinition;
                            }
                        }
                    }
                }
            }
        }

        return $fnName ? $actionFunctions[$fnName] : $actionFunctions;
    }


    /**
    * Extract the record ID from the URL.
    *
    * @param string $url
    * @return int|string
    */
    protected function extractRecordIdFromUrl( string $url ): int|string {

        // Support for Integer and UUID record IDs
        $segments = explode( '/', $url );
        $recordId = end( $segments );

        if ( is_numeric( $recordId ) ) {
            $recordId = ( int ) $recordId;
        }

        return $recordId;
    }

    /**
    * Filter the fields based on QR code scanning.
    * @param string|null $context
    */
    public function filterFields($fields, $context = NULL)
    {
        $is_update = ($context == 'update');
        $is_create = ($context == 'create');

        if ($is_update || $is_create) {
            // ----------------------------------- User stateful Url
            // So that other devices can follow activity
            // for example, when a phone is used to scan a QR code, the desktop can follow it
            if (get('set-url') === '') {
                if ($user = BackendAuth::user()) {
                    if (array_key_exists('acorn_url', $user->getAttributes())) {
                        $removeQuery = '/\?.*/'; // To avoid a continuous request loop
                        $user->acorn_url = preg_replace($removeQuery, '', Request::getRequestUri());
                        $user->save();
                        // Raise websockets event
                        UserNavigation::dispatch($user, $user->acorn_url); // channel=acorn, user.navigation
                    }
                }
            }

            // ----------------------------------- QR code scanning form value completion
            $newQrcodeUrl = post('_qrscan');
            foreach ( $fields as $fieldName => &$qrCodeField ) {
                // FormWidget type:qrscan
                if (  $qrCodeField->getConfig( 'type' ) == 'qrscan' ) {
                    $newQrcodeUrl = $qrCodeField->value;
                }
                // Legacy type:partial support
                if (strstr($qrCodeField->getConfig( 'path' ), '_qrscan')) {
                    if ($post = post($qrCodeField->arrayName)) {
                        if (isset($post[$fieldName])) $newQrcodeUrl = $post[$fieldName];
                    }
                }
                if ($newQrcodeUrl) break;
            }

            if ($newQrcodeUrl) {
                // Winter makes a separate request from the front-end for every dependsOn field
                // Generate a lock with a unique key to contain the previous QR code value
                $lockKey = 'qr_code_lock_' . md5( $newQrcodeUrl );
                $lock = Cache::lock( $lockKey );
                // Wait to acquire the lock
                if ( $lock->get() ) {
                    // Check the last QR code value stored in the session
                    $lastQrcodeUrl = Session::get( 'last_qrcode', null );// Default to null if not set
                    // Store the new QR Code in the session
                    Session::put( 'last_qrcode', $newQrcodeUrl );
                    $lock->release();
                }

                // If the QR code scanned is the same as the last one, skip processing
                if ( $lastQrcodeUrl != $newQrcodeUrl ) {
                    // Get the requested controller using the copied method
                    if ( $controller = BackendRequestController::getController( $newQrcodeUrl ) ) {
                        // Check if the controller implements the FormController behavior
                        if ( $controller && in_array( FormController::class, $controller->implement ) ) {
                            // Extract $recordId from URL
                            $recordId = $this->extractRecordIdFromUrl( $newQrcodeUrl );

                            // Use formFindModelObject to get the model
                            $model = $controller->formFindModelObject( $recordId );

                            if ( $model ) {
                                $qrClass = get_class( $model );
                                $qrClassShort = class_basename( $model );
                                // Short class name for messages
                                $qrObjectName = $model->name;
                                // names => classes
                                $fieldsRelations = array_merge( $this->hasOne, $this->belongsTo, $this->hasMany, $this->belongsToMany );
                                $qrObjectRelations = array_merge( $model->hasOne, $model->belongsTo, $model->hasMany, $model->belongsToMany );

                                // Check each field for qr object and its relations
                                $field          = NULL;
                                $relevantObject = NULL;
                                foreach ( $fields as $fieldName => &$field ) {
                                    // We only accept relations at the moment
                                    if ( isset( $fieldsRelations[ $fieldName ] ) ) {
                                        $fieldRelationModel = $fieldsRelations[ $fieldName ];
                                        if ( is_array( $fieldRelationModel ) ) $fieldRelationModel = $fieldRelationModel[ 0 ];
                                        // We do not overwrite set values
                                        $canHaveValue = ( is_null( $field->value ) || is_array( $field->value ) || $is_update );

                                        // ----------------------------------------------- Direct set
                                        if ( $canHaveValue) {
                                            if ( $fieldRelationModel == $qrClass ) {
                                                $relevantObject = $model;
                                                $foundAtText = "$qrClassShort($qrObjectName) direct";
                                                break;
                                            }

                                            // ----------------------------------------------- Scanned Object Relations
                                            foreach ( $qrObjectRelations as $qrObjectRelationName => $qrObjectRelationModel ) {
                                                $model->load( $qrObjectRelationName );
                                                if ( is_array( $qrObjectRelationModel ) ) $qrObjectRelationModel = $qrObjectRelationModel[ 0 ];
                                                if ( isset( $model->$qrObjectRelationName ) && $fieldRelationModel == $qrObjectRelationModel ) {
                                                    $relevantObject = $model->$qrObjectRelationName;
                                                    $foundAtText = "$qrClassShort($qrObjectName)->$qrObjectRelationName";
                                                    break;
                                                }
                                            }
                                        }  // cannot Have a Value
                                    } // not a relation

                                    if ( $relevantObject ) break;// We accept the first  only
                                }// foreach &$field

                                if ( $relevantObject ) {
                                    // Set the field value
                                    $id = $relevantObject->id;
                                    if ( is_array( $field->value ) ) array_push( $field->value, $id );
                                    else                             $field->value = $id;
                                    // Response
                                    $foundOnForm = __( 'found on form' );
                                    Flash::success( __( "$foundAtText $foundOnForm @ $fieldName [$id] " ) );

                                } else {
                                    $notFoundOnForm = __( 'not found on form' );
                                    throw new ValidationException(['error' => "$qrClassShort $notFoundOnForm"]);
                                }
                            } else {
                                throw new ValidationException(['error' => "Model [$recordId] " . __( 'not found for the given QR code' )]);
                            }
                        } else {
                            throw new Exception( __( 'Controller does not implement FormController behavior' ) );
                        }
                    } else {
                        throw new Exception( __( 'Unable to resolve the controller for ' ) . $newQrcodeUrl );
                    }
                }
            } else {
                // We do not want to block a QRCode forever
                // so normal requests without one will reset the last_qrcode
                Session::put( 'last_qrcode', '' );
            }

            // --------------------------------------------- add_button
            // XtoX additions only
            // Using config
            //   from: _product_instance
            //   to: product_instances
            // TODO: This is not used anymore, relationmanagers handle this
            /*
            $thisModelClass = $this->fullyQualifiedClassName();
            foreach ($fields as $name => &$buttonField) {
                if (isset($buttonField->config['path']) && $buttonField->config['path'] == 'add_button'
                    && (!isset($buttonField->config['custom']) || $buttonField->config['custom'] == false)
                ) {
                    // _add_invoice defaults to add _invoice to invoices
                    $modelNameLower  = substr($name, 5);             // invoice
                    $modelNamePlural = Str::plural($modelNameLower); // invoices
                    $post   = post($buttonField->arrayName);
                    $addVal = (is_array($post) && isset($post[$name]) ? $post[$name] : NULL);

                    // Auto-From must always be the un-nested singular pseudo
                    // otherwise we cannot distinguish between a from and to
                    $from   = (isset($buttonField->config['from']) ? $buttonField->config['from'] : "_$modelNameLower"); // _invoice

                    // and auto-to can only be plural
                    $to     = NULL;
                    if (isset($buttonField->config['to'])) $to = $buttonField->config['to'];
                    else {
                        // AUTO-Search for something sensible, including nested model[field1][field2]
                        foreach ($fields as $searchName => &$searchField) {
                            // Remove any nesting
                            $baseName = preg_replace('/^.*\[|\]$/', '', $searchName);
                            if (   $baseName == "_$modelNamePlural" // _invoices
                                || $baseName == $modelNamePlural    // invoices
                            ) {
                                $to = $searchName;
                                break;
                            }
                        }
                    }

                    // Checks
                    if (!property_exists($fields, $from)) throw new \Exception("From field [$from] not found on [$thisModelClass] when processing [$name]");
                    if (!property_exists($fields, $to))   throw new \Exception("To field [$to] not found on [$thisModelClass] when processing [$name]");

                    // add_button value in POST must be set
                    if ($addVal) {
                        if ($fromId = $fields->$from->value) {
                            $toArray = &$fields->$to->value;
                            if (is_null($toArray)) $toArray = array();

                            // We always, and only, append to many relations
                            if ( is_array($fromId))  throw new \Exception("From field [$from] on [$thisModelClass] is array, when processing [$name]");
                            if (!is_array($toArray)) throw new \Exception("To field [$from] on [$thisModelClass] is not array, when processing [$name]");

                            // Add to target array value
                            array_push($toArray, $fromId);

                            // Reset the [dedicated] interface
                            $buttonField->value   = NULL;
                            $fields->$from->value = NULL;
                        }
                    }
                }
            }

            // --------------------------------------------- create_button / popup_button
            // Using config
            //   to: product_instances
            // TODO: Encapsulate these buttons in to the popup_button formField when it is written
            foreach ($fields as $name => &$buttonField) {
                if (isset($buttonField->config['path'])
                    && ($buttonField->config['path'] == 'popup_button' || $buttonField->config['path'] == 'create_button')
                    && (!isset($buttonField->config['custom']) || $buttonField->config['custom'] == false)
                ) {
                    // _add_invoice defaults $to to _invoice, then invoice depending on which exists
                    // because _invoice may be another pseudo field that manages the addition process
                    // like the add button above
                    $modelNameLower  = substr($name, 8);             // invoice
                    $modelNamePlural = Str::plural($modelNameLower); // invoices
                    $modelClass      = (isset($buttonField->config['model']) ? $buttonField->config['model'] : Str::studly($modelNameLower)); // Invoice

                    $to              = NULL;
                    if (isset($buttonField->config['to'])) $to = $buttonField->config['to'];
                    else {
                        // AUTO-Search for something sensible, including nested model[field1][field2]
                        foreach ($fields as $searchName => &$searchField) {
                            // Remove any nesting
                            $baseName = preg_replace('/^.*\[|\]$/', '', $searchName);
                            if (   $baseName == "_$modelNameLower"  // _invoice
                                || $baseName == $modelNameLower     // invoice
                                || $baseName == "_$modelNamePlural" // _invoices
                                || $baseName == $modelNamePlural    // invoices
                            ) {
                                $to = $searchName;
                                break;
                            }
                        }
                    }

                    // Checks
                    if (is_null($to) || !property_exists($fields, $to)) throw new \Exception("To field [$to] of [$modelClass] not found on [$thisModelClass] when processing [$name]");

                    if ($fromId = $buttonField->value) {
                        if (is_array($fromId))  throw new \Exception("From field [$from] on [$thisModelClass] is array, when processing [$name]");

                        // Add to/Set target value
                        $isArray = (isset($fields->$to->config['type']) && $fields->$to->config['type'] == 'relation');
                        if ($isArray) {
                            $toArray = &$fields->$to->value;
                            if (is_null($toArray)) $toArray = array();
                            array_push($toArray, $fromId);
                        } else {
                            $fields->$to->value = $fromId;
                        }

                        // Reset the create button
                        $buttonField->value = NULL;
                    }
                }
            }
            */

            // ----------------------------------- Extended config options
            // options: Acorn\Lojistiks\Models\ProductInstance::dropdownOptions
            // where:
            //   location: @source_location
            /* TODO: filterFields() live dynamic changes
            foreach ($fields as $name => &$field) {
                if (isset($field->config['options']) && is_callable($field->config['options']) && isset($field->config['where'])) {
                    $models = $field->config['options'](NULL, $field);
                    foreach ($field->config['where'] as $property => $whereClause) {
                        if (is_array($whereClause)) {
                            // Relation
                            $relationClass = $this->belongsTo[$property];
                            foreach ($whereClauses as $whereField => $value) {
                                if ($whereField == 'field') {
                                    $models = $models->where($whereField, $value);
                                } else {
                                    $models = $models->where($whereField, $value);
                                }
                            }
                        }
                    }
                    $field->value = $models;
                }
            }
            */


        } // ($is_update || $is_create)
    }
}
