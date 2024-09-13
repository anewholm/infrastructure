<?php namespace Acorn;

use Winter\Storm\Database\Model as BaseModel;
use BackendAuth;
use \Backend\Models\User;
use \Backend\Models\UserGroup;
use ApplicationException;
use Winter\Storm\Support\Facades\Schema;
use Illuminate\Support\Facades\Redirect;

use Illuminate\Support\Str;
use Acorn\Builder;
use Acorn\Collection;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;

use Winter\Storm\Database\QueryBuilder;
use DB;
use Request;

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
use Backend\Widgets\Filter;
use Winter\Translate\Behaviors\TranslatableModel;

use Exception;
use Flash;

use Acorn\Events\UserNavigation;
use Acorn\Events\DataChange;

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

    // --------------------------------------------- Translation
    use \Acorn\Backendlocalization\Class\TranslateBackend;
    public $translatable = ['name'];
    public $implement = ['Winter.Translate.Behaviors.TranslatableModel'];

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
            $this->load($name);
            if ($leafObject = $this->$name) break;
        }

        if ($throwIfNull && !$leafObject) throw new Exception("Leaf $thisName not found for id($this->id)");
        return $leafObject;
    }

    // --------------------------------------------- Encapuslation and Standard Accessors
    protected function checkFrameworkCallerEncapsulation($attributeName)
    {
        if (env('APP_DEBUG')) {
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
                && ! is_a($callerClass, Filter::class, TRUE)
                && ! is_a($callerClass, TranslatableModel::class, TRUE)
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

    // --------------------------------------------- Standard fields
    public function id()
    {
        // Allow id checks, override in Derived Class if necessary
        return $this->id;
    }

    public function name() {return $this->name;}
    public function fullyQualifiedName() {return $this->name;}
    public function fullName() {return $this->name;}

    protected function getFullyQualifiedNameAttribute() {return $this->fullyQualifiedName();}
    protected function getFullNameAttribute() {return $this->fullName();}

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
        $optionsModel = (isset($field->config['optionsModel'])
            ? $field->config['optionsModel']
            : NULL
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
            $treeCollection = new TreeCollection($models);
            $nested = $treeCollection->toNested(FALSE);
            $list   = $treeCollection->listsNested($name, 'id', $indentationString);
        } else {
            $list = $models->lists($name, 'id');
        }

        return $list;
    }

    public function filterFields($fields, $context = NULL)
    {
        $is_update = ($context == 'update');
        $is_create = ($context == 'create');

        if ($is_update || $is_create) {
            // ----------------------------------- User stateful Url
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
            if ($post = post($this->unqualifiedClassName())) { // Transfer[...]
                if (isset($post['qrcode']) && $post['qrcode']) {
                    if ($qrcode = json_decode($post['qrcode'])) {
                        $qrClass      = "$qrcode->author\\$qrcode->plugin\\Models\\$qrcode->model";
                        $qrObject     = $qrClass::findOrFail($qrcode->id); // throws Exception
                        $qrObjectName = (method_exists($qrObject, 'name') ?  $qrObject->name() : $qrObject->id());
                        // names => classes
                        $fieldsRelations   = array_merge($this->hasOne,     $this->belongsTo,     $this->hasMany,     $this->belongsToMany);
                        $qrObjectRelations = array_merge($qrObject->hasOne, $qrObject->belongsTo, $qrObject->hasMany, $qrObject->belongsToMany);

                        // Check each field for qr object and its relations
                        $field          = NULL;
                        $relevantObject = NULL;
                        foreach ($fields as $fieldName => &$field) {
                            // We only accept relations at the moment
                            if (isset($fieldsRelations[$fieldName])) {
                                $fieldRelationModel = $fieldsRelations[$fieldName];
                                if (is_array($fieldRelationModel)) $fieldRelationModel = $fieldRelationModel[0];

                                // We do not overwrite set values
                                $canHaveValue = (is_null($field->value) || is_array($field->value) || $is_update);
                                if ($canHaveValue) {
                                    // ----------------------------------------------- Direct set
                                    if ($fieldRelationModel == $qrClass) {
                                        $relevantObject = $qrObject;
                                        $foundAtText    = "$qrcode->model($qrObjectName) direct";
                                        break;
                                    }

                                    // ----------------------------------------------- Scanned Object Relations
                                    foreach ($qrObjectRelations as $qrObjectRelationName => $qrObjectRelationModel) {
                                        $qrObject->load($qrObjectRelationName);
                                        if (is_array($qrObjectRelationModel)) $qrObjectRelationModel = $qrObjectRelationModel[0];
                                        if (isset($qrObject->$qrObjectRelationName) && $fieldRelationModel == $qrObjectRelationModel) {
                                            $relevantObject = $qrObject->$qrObjectRelationName;
                                            $foundAtText    = "$qrcode->model($qrObjectName)->$qrObjectRelationName";
                                            break;
                                        }
                                    }
                                } // cannot Have a Value
                            } // not a relation

                            if ($relevantObject) break; // We accept the first only
                        } // foreach &$field

                        if ($relevantObject) {
                            // Set the field value
                            if (is_array($field->value)) array_push($field->value, $relevantObject->id());
                            else                         $field->value = $relevantObject->id();

                            // Response
                            $foundOnForm = trans("found on form");
                            Flash::success(trans("$foundAtText $foundOnForm @ $fieldName"));
                        } else {
                            $notFoundOnForm = trans("not found on form");
                            Flash::error("$qrcode->model $notFoundOnForm");
                        }
                    }
                }
            }

            // --------------------------------------------- add_button
            // Using config
            //   from: _product_instance
            //   to: product_instances
            foreach ($fields as $name => &$field) {
                if (isset($field->config['path']) && $field->config['path'] == 'add_button') {
                    // _add_invoice defaults to add _invoice to invoices
                    $modelName = substr($name, 5); // invoice
                    $from = (isset($field->config['from']) ? $field->config['from'] : "_$modelName");
                    $to   = (isset($field->config['to'])   ? $field->config['to']   : Str::plural($modelName));

                    // Silent ignore if $to is not available
                    // Custom filterFields() must handle these cases
                    if (property_exists($fields, $to)) {
                        $collection = &$fields->$to->value;
                        if (is_null($collection)) $collection = array();

                        if (isset($post[$from])) {
                            if ($id = $post[$from]) {
                                array_push($collection, $id);
                                // Clear the form
                                $fields->$from->value = NULL;
                            }
                        }
                    }
                }
            }

            // --------------------------------------------- popup_button
            // TODO: Encapsulate this in to the popup_button formField when it is written
            foreach ($fields as $name => &$field) {
                if (isset($field->config['path']) && $field->config['path'] == 'popup_button') {
                    // _add_invoice defaults to add _invoice to invoices
                    $modelNameLower = substr($name, 8); // invoice
                    $modelClass     = (isset($field->config['model']) ? $field->config['model'] : Str::studly($modelNameLower)); // Invoice
                    $to             = (isset($field->config['to'])    ? $field->config['to']    : "_$modelNameLower"); // _invoice

                    if (property_exists($fields, $to)) $fields->$to->value = $field->value;
                }
            }

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

    // --------------------------------------------- Hierarchies
    public function getParentId()
    {
        return $this->parent_area_id;
    }

    public function getChildren(): Collection
    {
        $this->load('children');
        return $this->children;
    }
}
