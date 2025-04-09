<?php

use Winter\Storm\Database\Schema\Blueprint;
use AcornAssociated\Migration;

class DbFunctions extends Migration
{
    public function up()
    {
      // Useful for replication initiation
      // string $name, array $parameters, string $returnType, array $declares, string $body, ?string $language = 'plpgsql'
      $this->createFunction('fn_acornassociated_truncate_database', ['schema_like character varying', 'table_like character varying'], 'void', ['reset_query varchar(32596)'], <<<SQL
        reset_query = (SELECT 'TRUNCATE TABLE '
              || string_agg(format('%I.%I', schemaname, tablename), ', ')
              || ' CASCADE'
            FROM   pg_tables
            WHERE  schemaname like(schema_like)
            AND tablename like(table_like)
          );
        if not reset_query is null then
          execute reset_query;
        end if;
SQL
      );

      $this->createExtension('http');
      $this->createFunction('fn_acornassociated_new_replicated_row', [], 'trigger', [
          'server_domain varchar(1024)',
          'plugin_path varchar(1024)',
          'action varchar(2048)',
          'params varchar(2048)',
          'url varchar(2048)',
          'res public.http_response',
        ], <<<SQL
            -- https://www.postgresql.org/docs/current/plpgsql-trigger.html
            select "domain" into server_domain from acornassociated_servers where hostname = hostname();
            if server_domain is null then
              new.response = 'No domain specified';
            else
                    plugin_path = '/api';
                    action = '/datachange';
                    params = concat('TG_NAME=', TG_NAME, '&TG_OP=', TG_OP, '&TG_TABLE_SCHEMA=', TG_TABLE_SCHEMA, '&TG_TABLE_NAME=', TG_TABLE_NAME, '&ID=', new.id);
                    url = concat('http://', server_domain, plugin_path, action, '?', params);
        
                    res = public.http_get(url);
                    new.response = concat(res.status, ' ', res.content);
            end if;

            return new;
SQL
      );

      $this->createFunction('fn_acornassociated_add_websockets_triggers', ['schema character varying', 'table_prefix character varying'], 'void', [], <<<SQL
        -- SELECT * FROM information_schema.tables;
        -- This assumes that fn_acornassociated_new_replicated_row() exists
        -- Trigger on replpica also: ENABLE ALWAYS
        execute (
          SELECT string_agg(concat(
            'ALTER TABLE IF EXISTS ', table_schema, '.', table_name, ' ADD COLUMN IF NOT EXISTS response text;',
            'CREATE OR REPLACE TRIGGER tr_', table_name, '_new_replicated_row
                BEFORE INSERT
                ON ', table_schema, '.', table_name, '
                FOR EACH ROW
                EXECUTE FUNCTION public.fn_acornassociated_new_replicated_row();',
            'ALTER TABLE IF EXISTS ', table_schema, '.', table_name, ' ENABLE ALWAYS TRIGGER tr_', table_name, '_new_replicated_row;'
          ), ' ')
          FROM information_schema.tables
          where table_catalog = current_database()
          and table_schema like(schema)
          and table_name like(table_prefix)
          and table_type = 'BASE TABLE'
        );
SQL
      );

      $this->createFunction('fn_acornassociated_reset_sequences', ['schema_like character varying', 'table_like character varying'], 'void', ['reset_query varchar(32596)'], <<<SQL
        reset_query = (SELECT string_agg(
                concat('SELECT SETVAL(''',
              PGT.schemaname, '.', S.relname,
              ''', COALESCE(MAX(', C.attname, '), 1) ) FROM ',
              PGT.schemaname, '.', T.relname, ';'),
            '')
          FROM pg_class AS S,
            pg_depend AS D,
            pg_class AS T,
            pg_attribute AS C,
            pg_tables AS PGT
          WHERE S.relkind = 'S'
            AND S.oid = D.objid
            AND D.refobjid = T.oid
            AND D.refobjid = C.attrelid
            AND D.refobjsubid = C.attnum
            AND T.relname = PGT.tablename
            AND PGT.schemaname like(schema_like)
            AND T.relname like(table_like)
        );
        if not reset_query is null then
          execute reset_query;
        end if;
SQL
      );

      $this->createFunction('fn_acornassociated_table_counts', ['_schema character varying'], 'TABLE("table" text, count bigint)', [], <<<SQL
          -- SELECT * FROM information_schema.tables;
          return query execute (select concat(
          'select "table", "count" from (',
            (
              SELECT string_agg(
              concat('select ''', table_name, ''' as "table", count(*) as "count" from ', table_name),
              ' union all '
            )
            FROM information_schema.tables
            where table_catalog = current_database()
            and table_schema = _schema
            and table_type = 'BASE TABLE'
          ),
          ') data order by "count" desc, "table" asc'
        ));
SQL
      );

      $this->createExtension('hostname');
      $this->createFunction('fn_acornassociated_server_id', [], 'trigger', ['pid uuid'], <<<SQL
        if new.server_id is null then
          select "id" into pid from acornassociated_servers where hostname = hostname();
          if pid is null then
            insert into acornassociated_servers(hostname) values(hostname()) returning id into pid;
          end if;
          new.server_id = pid;
        end if;
        return new;
SQL
      );

      // Useful aggregates
      // string $baseName, array $parameters, string $body, ?array $declares, ?string $parameterType, ?string $parallel, ?string $language, ?array $modifiers
      $this->createFunctionAndAggregate('acornassociated_first', ['anyelement', 'anyelement'], 'SELECT $1;');
      $this->createFunctionAndAggregate('acornassociated_last',  ['anyelement', 'anyelement'], 'SELECT $2;');
    }

    public function down()
    {
      // TODO: Drop my shit man
    }
}

