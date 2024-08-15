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
use DB;

use BadMethodCallException;
use Illuminate\Database\Eloquent\RelationNotFoundException;
use InvalidArgumentException;

// Allowed __get/set() caller classes
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Winter\Storm\Router\Helper;
use Backend\Widgets\Lists;
use Backend\Classes\ListColumn;
use Backend\Widgets\Form;
use Backend\Classes\FormField;
use Backend\Behaviors\FormController;
use Winter\Storm\Database\TreeCollection;
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
    use \Illuminate\Database\Eloquent\Concerns\HasUuids; // Always distributed

    // --------------------------------------------- Misc
    protected function getShortClassName($object = NULL)
    {
        // Short name for debugging output
        // Acorn\Lojistiks\Model\Area => Area
        if (is_null($object)) $object = &$this;
        return last(explode('\\', get_class($object)));
    }

    // --------------------------------------------- Star schema centre => leaf services
    public function getTypeAttribute(?bool $throwIfNull = FALSE)
    {
        return $this->getLeafTypeObject($throwIfNull)?->getShortClassName();
    }

    protected function getLeafTypeObject(?bool $throwIfNull = FALSE)
    {
        // For base tables that have multiple possible leaf detail tables in a star schema
        // we search the hasOne relation to determine which leaf table has the 1-1
        $leafObject = NULL;
        $thisName   = $this->getShortClassName();
        foreach ($this->hasOneThrough as $name => &$relativeModel) {
            $this->load($name);
            if ($leafObject = $this->$name) break;
        }
        if (!$leafObject) {
            foreach ($this->hasOne as $name => &$relativeModel) {
                $this->load($name);
                if ($leafObject = $this->$name) break;
            }
        }

        if ($throwIfNull && !$leafObject) throw new Exception("Leaf $thisName not found for id($this->id)");
        return $leafObject;
    }

    // --------------------------------------------- Encapuslation and Standard Accessors
    protected function checkFrameworkCallerEncapsulation($attributeName)
    {
        if (TRUE) { // TODO: Tie this to debug mode
            $bt     = debug_backtrace();
            $called = $bt[1]; // Only called from our __get/set()
            $class  = get_class($this);
            $aClass = explode('\\', $class);
            $author = $aClass[0];
            $plugin = (isset($aClass[1]) ? $aClass[1] : NULL);
            $func   = $called['function'];

            // ViewMaker can call without calling context
            $caller      = (count($bt) > 2 ? $bt[2] : []);
            $callerClass = (isset($caller['class']) ? $caller['class'] : 'None');
            $callerLine  = $called['line'];
            $isRelation  = (property_exists($this, 'relations') && isset($this->relations[$attributeName]));

            if (   ! ($plugin == 'Calendar') // TODO: Rewrite calendar for Encapsulation
                && ! $isRelation
                && ! is_a($callerClass, EloquentBuilder::class,   TRUE)
                && ! is_a($callerClass, Relation::class,   TRUE)
                && ! is_a($callerClass, Helper::class,     TRUE)
                && ! is_a($callerClass, Lists::class,      TRUE)
                && ! is_a($callerClass, ListColumn::class, TRUE)
                && ! is_a($callerClass, Form::class,       TRUE)
                && ! is_a($callerClass, FormField::class,  TRUE)
                && ! is_a($callerClass, FormController::class, TRUE)
                && ! is_a($callerClass, TreeCollection::class, TRUE)
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

    public function name()
    {
        // Often fullName() or fullyQualifiedName() are used instead
        return $this->name;
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

    // --------------------------------------------- Querying
    public static function fromTableName($table)
    {
        foreach (get_declared_classes() as $class) {
            if (is_subclass_of($class, self::class)) {
                $model = new $class;
                if ($model->getTable() === $table) return $class;
            }
        }

        return false;
    }
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
                $action = '';
                print("<tr><td>$pub->pubname:</td><td class='$enabled'>$enabled</td><td>$action</td></tr>");
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

        print('</div>');
    }

    // --------------------------------------------- Forms
    protected static function getQualifiedColumnListing()
    {
        $model   = self::getModel();
        $table   = $model->getTable();
        $columns = Schema::getColumnListing($table);
        return $model->qualifyColumns($columns);
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
