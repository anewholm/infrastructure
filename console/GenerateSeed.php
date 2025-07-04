<?php

namespace Acorn\Console;

use Winter\Storm\Console\Command;
use System\Models\PluginVersion;
use System\Classes\PluginManager;
use DB;
use File;
use Illuminate\Database\QueryException;

class GenerateSeed extends Command
{

    const IGNORE_COLUMNS = array('created_at_event_id',
                    'updated_at_event_id', 'updated_by_user_id',
                    'created_by', 'updated_by', 'created_at', 'updated_at',
                    //'created_by_user_id', -- Needs to be set
                    'server_id',
                    'nest_left', 'nest_right', 'nest_depth');

    /**
     * @var string The console command name.
     */
    protected static $defaultName = 'acorn:generate-seed';

    /**
     * @var string The name and signature of this command.
     */
    protected $signature = 'acorn:generate-seed
        {table : The table to read values from}
        {--f|format=YAML : YAML or SQL, default: YAML.}
        {--c|condition=true : SQL where clause to limit records, Default: all records}
        {--x|onconflict= : on conflict on constraint do nothing clause. SQL format only}
        {--i|id=yes : Use the id field. Without the id, no existence check is possible. default: yes}
        {--w|white-space=normalize : Normalize in-array white-space, including replacing new-lines with a space. Options: normalize, no-new-lines}';

    /**
     * @var string The console command description.
     */
    protected $description = 'Create plugin seedings for a table';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle()
    {
        $table      = $this->argument('table');
        $format     = $this->option( 'format');
        $condition  = $this->option( 'condition');
        $onConflict = $this->option( 'onconflict');
        $whiteSpace = $this->option( 'white-space');
        $useIdField = ($this->option( 'id') == 'yes');

        $results      = DB::select("select * from $table where $condition");

        if ($results) {
            switch ($format) {
                case 'YAML':
                    print("  # $table\n");
                    print('  # ');
                    $first = TRUE;
                    foreach ($results[0] as $name => $value) {
                        if (!in_array($name, self::IGNORE_COLUMNS) && ($useIdField || $name != 'id')) {
                            if (!$first) print(', ');
                            print($name);
                            $first = FALSE;
                        }
                    }
                    print("\n");

                    foreach ($results as $result) {
                        print('  - [');
                        $first = TRUE;
                        foreach ($result as $name => $value) {
                            if (!in_array($name, self::IGNORE_COLUMNS) && ($useIdField || $name != 'id')) {
                                if (!$first) print(', ');
                                if      (is_null($value))    $value = 'NULL';
                                else if ($value == 'NULL')          $value = 'NULL';
                                else if (is_bool($value))    $value = ($value ? 'true' : 'false');
                                else if (is_numeric($value)) {}
                                else if (is_string($value))  $value = "'$value'";

                                switch ($whiteSpace) {
                                    case 'normalize':
                                        $value = preg_replace('/\s+/', ' ', $value);
                                        break;
                                    case 'no-new-lines':
                                        $value = preg_replace('/[\n\r]+/', ' ', $value);
                                        break;
                                }

                                print($value);
                                $first = FALSE;
                            }
                        }
                        print("]\n");
                    }
                    break;
                case 'SQL':
                    print("-- $table\n");
                    print("DO\n\$BODY\$\nBEGIN\n");
                    $onConflictClause = ($onConflict
                        ? "ON CONFLICT ON CONSTRAINT $onConflict DO NOTHING" 
                        : (property_exists($results[0], 'id') && $useIdField
                            ? "ON CONFLICT(id) DO NOTHING" 
                            : ''
                        )
                    );
                    $insert     = "INSERT INTO $table(";
                    $first      = TRUE;
                    foreach ($results[0] as $name => $value) {
                        if (!in_array($name, self::IGNORE_COLUMNS) && ($useIdField || $name != 'id')) {
                            if (!$first) $insert .= ', ';
                            $insert .= $name;
                            $first = FALSE;
                        }
                    }
                    $insert .= ") VALUES(";

                    foreach ($results as $result) {
                        $first = TRUE;
                        print("  $insert");
                        foreach ($result as $name => $value) {
                            if (!in_array($name, self::IGNORE_COLUMNS) && ($useIdField || $name != 'id')) {
                                if (!$first) print(', ');
                                if (is_null($value)) print('NULL');
                                if (is_string($value)) print("'$value'");
                                else print($value);
                                $first = FALSE;
                            }
                        }
                        print(") $onConflictClause;\n");
                    }
                    print("END;\n\$BODY\$\n");
                    break;
            }
        } else {
            print("Empty table\n");
        }
    }

    // TODO: Provide autocomplete suggestions for the "myCustomArgument" argument
    // public function suggestMyCustomArgumentValues(): array
    // {
    //     return ['value', 'another'];
    // }
}
