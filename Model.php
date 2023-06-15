<?php namespace AcornAssociated;

use Model as BaseModel;
use BackendAuth;
use \Backend\Models\User;
use \Backend\Models\UserGroup;
use ApplicationException;
use Winter\Storm\Support\Facades\Schema;

use Illuminate\Support\Str;
use AcornAssociated\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Winter\Storm\Database\QueryBuilder;

use BadMethodCallException;
use Illuminate\Database\Eloquent\RelationNotFoundException;
use InvalidArgumentException;
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
        $this->updated_at = NULL;

        return parent::save($options, $sessionKey);
    }

    // AcornAssociated Builder extensions
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
}
