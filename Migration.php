<?php namespace Acorn;

use Winter\Storm\Database\Updates\Migration as StormMigration;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use DB;
use Exception;

class Migration extends StormMigration
{
    // --------------------------------------- Replication
    public function isCentralPublisher(): bool
    {
        return (gethostname() == 'acornssociated-local'
            || DB::connection()->getConfig('is_central_publisher') == true
        );
    }

    public function truncateDatabase(string $tablePrefix): bool
    {
        DB::unprepared("select fn_acorn_lojistiks_truncate_database('%', '$tablePrefix%')");
        return TRUE;
    }

    public function resetSequences(string $tablePrefix): bool
    {
        DB::unprepared("select fn_acorn_lojistiks_reset_sequences('%', '$tablePrefix%')");
        return TRUE;
    }

    public function cleanDatabase(string $tablePrefix)
    {
        $this->truncateDatabase($tablePrefix);
        $this->resetSequences($tablePrefix);
        return TRUE;
    }

    public function tableCounts(?string $tablePrefix = NULL): array
    {
        // TODO: This should be in an Acorn\DB
        if (!$tablePrefix) $tablePrefix = $this->paths('tableMask');
        return DB::select("select * from fn_acorn_lojistiks_table_counts('public') where \"table\" like('$tablePrefix')");
    }

    public function tableNames(?string $tablePrefix = NULL): array
    {
        return array_column($this->tableCounts($tablePrefix), 'table');
    }

    public function refreshSubscriptionTo(array|string $subscriberInfo): bool
    {
        // TODO: ALTER SUBSCRIPTION sub_acorn_lojistiks_all_tables REFRESH PUBLICATION WITH (COPY_DATA=false);
        throw new Exception('Not complete');
        return TRUE;
    }

    public function subscribeTo(array|string $subscriberInfo = 'replication_publisher'): bool
    {
        // ------------------------------------------------- Config
        $tablePrefix = $this->paths('tablePrefix');
        if (is_string($subscriberInfo)) $subscriberInfo = [
            'connection'   => $subscriberInfo,
        ];
        // Programmatic sub/pub naming
        if (!isset($subscriberInfo['table_prefix'])) $subscriberInfo['table_prefix'] = $tablePrefix; // acorn_lojistiks_
        if (!isset($subscriberInfo['publication']))  $subscriberInfo['publication']  = "pub_$subscriberInfo[table_prefix]tables"; // pub_acorn_lojistiks_tables
        if (!isset($subscriberInfo['subscription'])) $subscriberInfo['subscription'] = "sub_$subscriberInfo[table_prefix]tables";
        if (!isset($subscriberInfo['copy_data']))    $subscriberInfo['copy_data'] = true;
        if (!isset($subscriberInfo['streaming']))    $subscriberInfo['streaming'] = true;

        // ------------------------------------------------- Replication publisher DB connection
        $publisherConnection = config("database.connections.$subscriberInfo[connection]");
        // Inherit main connection settings
        foreach (DB::connection()->getConfig() as $name => $value) {
            if ($name != 'subscribe_to') {
                if (!isset($publisherConnection[$name])) $publisherConnection[$name] = $value;
            }
        }
        if (!isset($publisherConnection['sslmode'])) $publisherConnection['sslmode'] = 'disable';
        print("\t\t\tChecking the connection to $publisherConnection[host]:$publisherConnection[port]");
        DB::connection($subscriberInfo['connection'])->select('select 1');


        // TODO: ------------------------------------------------- Check / create publication exists
        // print("\t\t\t\tChecking publication on $subscriberInfo[connection]\n");
        print("\t\t\tSubscribing to publication $subscriberInfo[publication]\n");

        // Always cleaning is necessary before copy_data runs
        if ($subscriberInfo['copy_data']) {
            print("\t\t\t\tCleaning database\n");
            $this->cleanDatabase($subscriberInfo['table_prefix']);
        }

        /* ------------------------------------------------- Create the subscription
         * https://postgrespro.com/docs/postgresql/16/sql-altersubscription
         * By default, PG waits for the WAL log to fill (16MB) before updating subscribers
         * Streaming ships each new WAL log entry (DB change) immediately
         * Replication slots provide an automated way to ensure that the primary does not remove WAL segments until they have been received by all standbys, and that the primary does not remove rows which could cause a recovery conflict even when the standby is disconnected.
         * The initial data in existing subscribed tables are snapshotted and copied in a parallel instance of a special kind of apply process. This process will create its own replication slot and copy the existing data. As soon as the copy is finished the table contents will become visible to other backends. Once existing data is copied, the worker enters synchronization mode, which ensures that the table is brought up to a synchronized state with the main apply process by streaming any changes that happened during the initial data copy using standard logical replication. During this synchronization phase, the changes are applied and committed in the same order as they happened on the publisher. Once synchronization is done, control of the replication of the table is given back to the main apply process where replication continues as normal.
         * Binary requires exact column data type matching, whereas non-binary, for example, allows integer to be mapped to bigint
         */
        $streaming = ($subscriberInfo['streaming'] ? 'True' : 'False');
        $copyData  = ($subscriberInfo['copy_data'] ? 'true' : 'false');
        print("\t\t\t\tDropping existing subscription (if exists)\n");
        DB::unprepared("DROP SUBSCRIPTION IF EXISTS $subscriberInfo[subscription]");

        print("\t\t\t\tCreating subscription\n");
        $SQL = <<<SQL
            CREATE SUBSCRIPTION $subscriberInfo[subscription]
                CONNECTION 'host=$publisherConnection[host] port=$publisherConnection[port] dbname=$publisherConnection[database] user=$publisherConnection[username] password=$publisherConnection[password] sslmode=$publisherConnection[sslmode]'
                PUBLICATION $subscriberInfo[publication]
                WITH (
                    streaming = '$streaming',
                    create_slot = true,
                    slot_name = '$subscriberInfo[subscription]',
                    binary = false,
                    copy_data = $copyData,

                    connect = true,
                    enabled = true,
                    synchronous_commit = 'off',
                    two_phase = false,
                    disable_on_error = false,
                    run_as_owner = false,
                    password_required = true,
                    origin = 'any'
                );
SQL;
        DB::unprepared($SQL);

        // ------------------------------------------------- Bi-directional local publication
        print("\t\t\t\tDropping existing local publication (if exists)\n");
        DB::unprepared("DROP PUBLICATION IF EXISTS $subscriberInfo[publication]");

        print("\t\t\t\tCreating publication to $subscriberInfo[connection]\n");
        $pub_tables = implode(', ', $this->tableNames());
        $SQL = <<<SQL
            CREATE PUBLICATION $subscriberInfo[publication]
                FOR TABLE $pub_tables
                WITH (publish = 'insert, update, delete, truncate', publish_via_partition_root = false);
SQL;
        DB::unprepared($SQL);

        // ------------------------------------------------- Create subscription on server
        // print("\t\t\t\tCreating subscription on $subscriberInfo[connection]\n");
        $SQL = <<<SQL
            CREATE SUBSCRIPTION $subscriberInfo[subscription]
            CONNECTION 'host=192.168.88.252 port=5433 dbname=acornlojistiks user=sz password=xxxxxx sslmode=disable'
            PUBLICATION $subscriberInfo[publication]
            WITH (
                streaming = 'True',
                slot_name = 'sub_acorn_lojistiks_all_tables_laptop',
                copy_data = false,
            );
SQL;
        // TODO: DB::connection($subscriberInfo[connection])->unprepared($SQL);

        // ------------------------------------------------- Asynchronous results!
        // Now copy_data will run, creating an additional temporary slot on the publisher during operation
        // All data will come down
        // Local Sequences WILL NOT be updated by the copy_data copies
        // UUIDs are recommended

        return TRUE;
    }

    // --------------------------------------- Up & Down from SQL files
    public function up()
    {
        print("\n");

        // Schema
        $this->runSQL('pre-up'); // Custom written
        $this->runSQL('up');     // Generated by update_sqls

        // Initial data
        $subscribeTo = DB::connection()->getConfig('subscribe_to');
        if ($subscribeTo) $this->subscribeTo($subscribeTo);
        else $this->runSQL('seed'); // Custom written

        $this->runSQL('post-up'); // Custom written
    }

    public function down()
    {
        print("\n");
        $this->runSQL('down'); // Generated by update_sqls
    }

    protected function paths(?string $key = NULL): array|string
    {
        $docRoot     = getcwd();
        $class       = get_class($this);
        $aClass      = explode('\\', $class);
        $author      = $aClass[0];
        $plugin      = $aClass[1];
        $shortClass  = last($aClass);

        $authorDir   = strtolower($author);
        $pluginDir   = strtolower($plugin);
        $pluginRel   = "plugins/$authorDir/$pluginDir";
        $tablePrefix = "${authorDir}_${pluginDir}_";
        $tableMask   = "$tablePrefix%";

        $paths = [
            'fqClass'     => $class,
            'class'       => $shortClass,
            'author'      => $author,
            'plugin'      => $plugin,
            'authorDir'   => $authorDir,
            'pluginDir'   => $pluginDir,
            'docRoot'     => $docRoot,
            'pluginRel'   => $pluginRel,
            'pluginAbs'   => "$docRoot/$pluginRel",
            'classPath'   => "$docRoot/$pluginRel/models/$shortClass.php",

            'tablePrefix' => $tablePrefix,
            'tableMask'   => $tableMask,
        ];

        return ($key ? $paths[$key] : $paths);
    }

    public function runSQL(string $filename)
    {
        $contents  = NULL;
        $pluginRel = $this->paths('pluginRel');
        $filepath  = "$pluginRel/updates/$filename.sql";

        try {
            // TODO: Make this path generic
            // SECURITY: Sanitize $filename
            $file     = new File($filepath, TRUE);
            $contents = $file->getContent();
        } catch (FileNotFoundException $ex) {}

        if ($contents && trim($contents)) {
            print("\t\t\t$filename.sql ");
            $yn = 'y'; //readline("Execute $filename.sql (y)?");
            if ($yn == 'n') {
                print(" USER CANCELLED\n");
            } else {
                DB::unprepared($contents);
                print(" DONE\n");
            }
        }
    }

    // ------------------------------------------ Extended table management
    // TODO: Make these methods on an Acorn Table Class
    public function dropIfExistsCascade($table)
    {
        DB::unprepared("drop table if exists $table cascade");
    }

    public function dropCascade($table)
    {
        $this->dropIfExistsCascade($table);
    }

    public function dropForeignIfExists($table, $foreignKey)
    {
        DB::unprepared("SELECT exists(select * FROM information_schema.table_constraints WHERE constraint_name='$foreignKey' AND table_name='$table')");
    }

    // ------------------------------------------ Extended Objects
    public function createFunction(string $name, array $parameters, string $returnType, string $body, ?string $language = 'plpgsql')
    {
        // TODO: Check name starts with fn_<author>_<plugin>_
        // TODO: Introduce DECLARE section
        $BODY = '$BODY$';
        $parametersString = implode(',', $parameters);
        DB::unprepared(<<<SQL
            create or replace function $name($parametersString) returns $returnType
            as $BODY
            begin
                $body
            end;
            $BODY language $language;
SQL
        );
    }

    public function createTrigger(string $name, string $stage, string $action, string $table, bool $forEachRow, string $function)
    {
        // TODO: Check name starts with tr_<author>_<plugin>_
        $forEachRowString = ($forEachRow ? 'FOR EACH ROW' : '');
        $parametersString = implode(',', $parameters);
        DB::unprepared(<<<SQL
            CREATE OR REPLACE TRIGGER $name
            $stage $action
            ON $table
            $forEachRowString
            EXECUTE FUNCTION $function();
SQL
        );
    }

    public function createFunctionAndTrigger(string $baseName, string $stage, string $action, string $table, bool $forEachRow, string $body, ?string $language = 'plpgsql')
    {
        $functionName = "fn_$baseName";
        $this->createFunction($functionName, [], 'trigger', $body, $language);
        $this->createTrigger("tr_$baseName", $stage, $action, $table, $forEachRow, $functionName);
    }

    // ------------------------------------------ Standard triggers
    // TODO: server_id field and trigger

    // TODO: correction fields and trigger

    // TODO: created_by_user_id field and trigger

    // ------------------------------------------ Extended Fields
    // TODO: Make these methods on an Acorn Table Class
    public function interval(string $table, string $column, ?bool $nullable = FALSE)
    {
        $null = ($nullable ? '' : 'NOT NULL');
        DB::unprepared("alter table $table add column $column interval $null;");
    }

    public function intervalWithDefault(string $table, string $column, ?bool $nullable = FALSE, $default = 0)
    {
        $null = ($nullable ? '' : 'NOT NULL');
        DB::unprepared("alter table $table add column $column interval $null default '00:00:00';");
    }

    public function integerArray(string $table, string $column, ?bool $nullable = FALSE)
    {
        $null = ($nullable ? '' : 'NOT NULL');
        DB::unprepared("alter table $table add column $column integer[] $null;");
    }
}
