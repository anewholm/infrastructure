<?php namespace Acorn;

use Winter\Storm\Database\Updates\Migration as StormMigration;
use DB;

class Migration extends StormMigration
{
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

    public function createFunction(string $name, array $parameters, string $returnType, string $body, ?string $language = 'plpgsql')
    {
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
