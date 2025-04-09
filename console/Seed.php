<?php

namespace AcornAssociated\Console;

use Winter\Storm\Console\Command;
use System\Models\PluginVersion;
use System\Classes\PluginManager;
use DB;
use File;
use Illuminate\Database\QueryException;

class Seed extends Command
{
    /**
     * @var string The console command name.
     */
    protected static $defaultName = 'acorn:seed';

    /**
     * @var string The name and signature of this command.
     */
    protected $signature = 'acorn:seed
        {plugin? : The qualified Plugin name like AcornAssociated.Lojistiks}
        {--i|interactive : Ask before each function|file is run.}';

    /**
     * @var string The console command description.
     */
    protected $description = 'Run plugin seedings';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle()
    {
        $plugin      = $this->argument('plugin');
        $interactive = $this->option( 'interactive');

        // Get relevant plugins
        $plugins = array();
        $pm      = PluginManager::instance();
        if ($plugin) $plugins = array($plugin => $pm->findByIdentifier($plugin));
        else         $plugins = $pm->getAllPlugins();

        foreach ($plugins as $name => $pluginObj) {
            // ---------------------------- Seeding db functions
            $slugSnake      = strtolower(str_replace('.', '_', $name));

            $dbFunctionBase = "fn_${slugSnake}_seed";
            $dbSubFunction  = "${dbFunctionBase}_%";
            try {
                $results = DB::select("select 
                        proname as name, 
                        'function' as type, 
                        proargnames as parameters, 
                        proargtypes as types, oid, 
                        obj_description(oid) as comment
                    from pg_proc
                    where proname = :dbFunctionBase or proname like(:dbSubFunction)
                    ORDER BY proname", 
                array(
                    'dbFunctionBase' => $dbFunctionBase,
                    'dbSubFunction'  => $dbSubFunction
                ));
            } catch (\Exception $e) {
                $results = array();
            }
            
            // ---------------------------- Seeding files
            $slugDir      = str_replace('_', '/', $slugSnake);
            $seedPath     = "plugins/$slugDir/updates/seed.sql";
            if (File::exists($seedPath)) {
                array_push($results, (object)array(
                    'name'    => 'seed.sql',
                    'path'    => $seedPath,
                    'type'    => 'file',
                    'comment' => '',
                ));
            }
            
            // ---------------------------- Run
            if ($results) {
                $this->info($name);
                foreach ($results as $result) {
                    $name   = $result->name;
                    $suffix = ($result->type == 'file' ? '' : '()');

                    print("  $name$suffix...");
                    switch ($result->type) {
                        case 'file':
                            if ($sql = trim(File::get($result->path))) {
                                try {
                                    DB::unprepared($sql);
                                } catch (QueryException $qe) {
                                    switch ($qe->getCode()) {
                                        case 'P0003': // Query returned more than one row
                                            break;
                                        default:
                                            throw $qe;
                                    }
                                }
                            } else {
                                print('(file empty!) ');
                            }
                            break;

                        case 'function':
                            DB::unprepared("select $name$suffix");
                            break;
                    }
                    print("DONE.\n");
                }
            }
        }
    }

    // TODO: Provide autocomplete suggestions for the "myCustomArgument" argument
    // public function suggestMyCustomArgumentValues(): array
    // {
    //     return ['value', 'another'];
    // }
}
