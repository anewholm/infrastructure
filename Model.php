<?php namespace Acorn;

use Winter\Storm\Database\Model as BaseModel;
use BackendAuth;
use \Backend\Models\User;
use \Backend\Models\UserGroup;
use ApplicationException;
use Winter\Storm\Support\Facades\Schema;

use Illuminate\Support\Str;
use Acorn\Builder;
use Acorn\Collection;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;

use Winter\Storm\Database\QueryBuilder;

use BadMethodCallException;
use Illuminate\Database\Eloquent\RelationNotFoundException;
use InvalidArgumentException;

// Allowed __get/set() caller classes
use Winter\Storm\Router\Helper;
use Backend\Widgets\Lists;
use Backend\Classes\ListColumn;
use Backend\Widgets\Form;
use Backend\Classes\FormField;
use Backend\Behaviors\FormController;
use Exception;

/*
class Saving {
    public function __construct(Model $eventPart)
    {
        // TODO: What shall we do with this?
        // TODO: look at the Dispatcher::until() method
        //throw new ApplicationException($eventPart->name . " was here");
    }
}
*/

class Model extends BaseModel
{
    use DeepReplicates;
    use DirtyWriteProtection;
    use ObjectLocking;
    use PostGreSQLFieldTypeUtilities;

    static $forceEncapsulate = TRUE;

    protected function checkFrameworkCallerEncapsulation($attributeName)
    {
        if (self::$forceEncapsulate) {
            $bt     = debug_backtrace();
            $called = $bt[1]; // Only called from our __get/set()
            $class  = get_class($this);
            $func   = $called['function'];

            if (count($bt) < 3) throw new Exception("Protected $class::$func($attributeName) called without context");
            $caller = $bt[2];

            if (!isset($caller['class'])) {
                throw new Exception("Protected $class::$func() called without an class");
            }
            $callerClass = $caller['class'];
            $callerLine  = $called['line'];
            $isRelation  = (property_exists($this, 'relations') && isset($this->relations[$attributeName]));

            if (   ! $isRelation
                && ! is_a($callerClass, Relation::class,   TRUE)
                && ! is_a($callerClass, Helper::class,     TRUE)
                && ! is_a($callerClass, Lists::class,      TRUE)
                && ! is_a($callerClass, ListColumn::class, TRUE)
                && ! is_a($callerClass, Form::class,       TRUE)
                && ! is_a($callerClass, FormField::class,  TRUE)
                && ! is_a($callerClass, FormController::class, TRUE)
                && ! is_a($callerClass, $class,            TRUE)
                && ! is_a($class, $callerClass,            TRUE)
            ) {
                throw new Exception("Protected $class::$func($attributeName) called by $callerClass:$callerLine");
            }
        }
    }

    public function __get($name)
    {
        $this->checkFrameworkCallerEncapsulation($name);
        return parent::__get($name);
    }

    public function __set($name, $value)
    {
        $this->checkFrameworkCallerEncapsulation($name);
        return parent::__set($name, $value);
    }

    public function id()
    {
        // Allow id checks, override in Derived Class if necessary
        return $this->id;
    }

    /*
    protected $dispatchesEvents = [
        'saving' => Saving::class, // TODO: Not used yet. See Saving event above
    ];
    */

    public function delete()
    {
        if ($user = BackendAuth::user())
            $this->unlock($user); // Does not save(), may throw ObjectIsLocked()

        return parent::delete();
    }

    public function save(?array $options = [], $sessionKey = null)
    {
        // Object locking
        if (!isset($options['UNLOCK']) || $options['UNLOCK'] == TRUE) {
            if ($user = BackendAuth::user())
                $this->unlock($user); // Does not save(), may throw ObjectIsLocked()
        }

        // Dirty Writing checks in fill() include a passed original updated_at field
        // but we do not want to override default behavior
        // This would error on create new
        if (!property_exists($this, 'timestamps') || $this->timestamps) $this->updated_at = NULL;

        return parent::save($options, $sessionKey);
    }

    // Acorn Builder extensions
    public function newEloquentBuilder($query): Builder
    {
        // Ensure we remain in the family
        // causes chained queries to always work with our Builder
        return new Builder($query);
    }

    protected static function getQualifiedColumnListing()
    {
        $model   = self::getModel();
        $table   = $model->getTable();
        $columns = Schema::getColumnListing($table);
        return $model->qualifyColumns($columns);
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

    public function newCollection(array $models = [])
    {
        return new Collection($models);
    }

    public static function dropdownOptions($form, $field)
    {
        $name = (isset($field->config['nameFrom'])
            ? $field->config['nameFrom']
            : 'name'
        );

        // TODO: where: clause

        return self::all()->lists($name, 'id');
    }
}
