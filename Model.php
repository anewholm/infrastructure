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

use Illuminate\Database\QueryException;
use Winter\Storm\Database\QueryBuilder;
use DB;
use Request;
use Config;

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
    use TranslateBackend {
        __get as protected tb__get;
    }

    // --------------------------------------------- Translation
    // TODO: Jabers code, when reviewed: use \Acorn\Backendlocalization\Class\TranslateBackend;
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

    // --------------------------------------------- Encapuslation and Standard Accessors
    protected function checkFrameworkCallerEncapsulation($attributeName)
    {
        if (env('APP_DEBUG')) {
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
                && ! is_a($callerClass, $thisClass,        TRUE)
                && ! is_a($thisClass, $callerClass,        TRUE)
                // Traits
                && $callerClass != 'Winter\Translate\Behaviors\TranslatableModel'
            ) {
                throw new Exception("Protected $thisClass::$func($attributeName) called by $callerPlugin/$thisPlugin $callerClass:$callerLine");
            }
        }
    }

    public function __get($name)
    {
        $this->checkFrameworkCallerEncapsulation($name);
        // Pass through to TranslateBackend trait
        return $this->tb__get($name);
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

    public function name() {
        $name = NULL;

        if ($this->hasAttribute('name') && $this->name) $name = $this->name;
        else {
            // We allow 1-1 relations to define the name
            foreach ($this->belongsTo as $relation => &$belongsTo) {
                if (is_array($belongsTo) && isset($belongsTo['name']) && $belongsTo['name'] === TRUE) {
                    $this->load($relation);
                    $relatedObject = $this->$relation;
                    if ($relatedObject) {
                        if (!$relatedObject->hasAttribute('name')) {
                            $unqualifiedClassName = $this->unqualifiedClassName();
                            throw new Exception("Name relation on $unqualifiedClassName::belongsTo[$relation] does not have a name attribute");
                        }
                        $name = $relatedObject->name;
                        break;
                    }
                }
            }
        }
        if (is_null($name) && $this->hasAttribute('id')) $name = $this->id;

        return $name;
    }
    public function fullyQualifiedName() {return $this->name();}
    public function fullName()           {return $this->name();}

    protected function getNameAttribute() {return $this->name();}
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
        // Useful for auto-completing auto-relations
        // like created_by_user and created_by_event
        ModelBeforeSave::dispatch($this);

        // Object locking
        if (!isset($options['UNLOCK']) || $options['UNLOCK'] == TRUE) {
            if ($user = BackendAuth::user())
                $this->unlock($user); // Does not save(), may throw ObjectIsLocked()
        }

        // Dirty Writing checks in fill() include a passed original updated_at field
        // but we do not want to override default behavior
        // This would error on create new
        if (!property_exists($this, 'timestamps') || $this->timestamps) $this->updated_at = NULL;

        try {
            $result = parent::save($options, $sessionKey);
        } catch (QueryException $qe) {
            if (env('APP_DEBUG')) {
                throw $qe;
            } else {
                $message = $qe->getMessage();
                switch ($qe->getCode()) {
                    case 23514:
                        // SQLSTATE[23514]: Check violation: 7 ERROR: new row for relation "acorn_finance_invoices" violates check constraint "payee_either_or"
                        if (preg_match_all('/relation "([^"]+)" violates check constraint "([^"]+)"/', $message, $matches) == 1) {
                            if (count($matches) == 3) {
                                // TODO: Make this a nice validation Flash message
                                $table   = $matches[1][0];
                                $check   = $matches[2][0];
                                $message = "Check $check is required";
                            }
                        }
                        break;
                    case 23502:
                        // NotNullConstraintViolationException
                        // SQLSTATE[23502]: Not null violation: 7 ERROR:  null value in column "number" of relation "acorn_finance_receipts" violates not-null constraint
                        if (preg_match_all('/column "([^"]+)" of relation "([^"]+)"/', $message, $matches) == 1) {
                            if (count($matches) == 3) {
                                // TODO: Make this a nice validation Flash message
                                $column  = $matches[1][0];
                                $table   = $matches[2][0];
                                $message = "$column is required";
                            }
                        }
                        break;
                }
                if ($message) throw new Exception($message);
            }
        }

        // Useful for auto-completing auto-relations
        // like created_by_user and created_by_event
        ModelAfterSave::dispatch($this);

        return $result;
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
        return $this->parent?->id();
    }

    public function getChildren(): Collection
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
            if (strstr(static::class, 'Defendant')) dd(static::class, $hierarchical, $list, $name, $models, $field->config, $models->first()?->hasMany['children']);
        } else {
            $list = $models->lists($name, 'id');
        }

        return $list;
    }

    /**
    * Extract the record ID from the URL.
    *
    * @param string $url
    * @return int|string
    */
    protected function extractRecordIdFromUrl( string $url ): int|string {

        $segments = explode( '/', $url );
        $recordId = end( $segments );

        //if it is integer return int
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
            $qrCodeField = NULL;
            $field2 = NULL;
            foreach ( $fields as $fieldName => &$field2 ) {
                if (   $field2->getConfig( 'type' ) == 'qrscan' ) {
                    $qrCodeField = &$field2;
                    // get the type and put fildename  in qrCodeField
                    break;
                }
            }
            // Legacy support
            foreach ( $fields as $fieldName => &$field2 ) {
                if ( ! $qrCodeField && strstr($field2->getConfig( 'path' ), '_qrcode_scan') ) {
                    $qrCodeField = &$field2;
                    if ($post = post($qrCodeField->arrayName)) {
                        if (isset($post[$fieldName])) $qrCodeField->value = $post[$fieldName];
                    }
                    break;
                }
            }

            if ( $qrCodeField && !empty( $qrCodeField->value ) ) {
                $newQrcodeUrl = $qrCodeField->value;

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
                                $qrObjectName = ( method_exists( $model, 'name' ) ? $model->name() : $model->id() );
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
                                    $id = $relevantObject->id();
                                    if ( is_array( $field->value ) ) array_push( $field->value, $id );
                                    else                             $field->value = $id;
                                    // Response
                                    $foundOnForm = __( 'found on form' );
                                    Flash::success( __( "$foundAtText $foundOnForm @ $fieldName [$id] " ) );

                                } else {
                                    $notFoundOnForm = __( 'not found on form' );
                                    Flash::error( " $qrClassShort $notFoundOnForm" );
                                }
                            } else {
                                Flash::error( "Model [$recordId] " . __( 'not found for the given QR code' ) );
                            }
                        } else {
                            Flash::error( __( 'Controller does not implement FormController behavior' ) );
                        }
                    } else {
                        Flash::error( __( 'Unable to resolve the controller for ' ) . $newQrcodeUrl );
                    }
                }
            } else {
                // We do not want to block a QRCode forever
                // so normal requests without one will reset the last_qrcode
                Session::put( 'last_qrcode', '' );
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
}
