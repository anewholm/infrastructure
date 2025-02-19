<?php namespace Acorn\Controllers;

use BackendMenu;
use Acorn\Controller; // extends Backend\Classes\Controller
use Acorn\Migration;
use Acorn\Events\DataChange;
use \Exception;

/**
 * DB Backend Controller
 */
class DB extends Controller
{
    public function datachange()
    {
        // TODO: What about /laravel-websockets/event?
        // e.g. /api/datachange?TG_NAME=tr_acorn_lojistiks_new_replicated_row&TG_OP=INSERT&TG_TABLE_SCHEMA=product&TG_TABLE_NAME=acorn_lojistiks_products&ID=1
        // Process:
        //   Database trigger function: fn_acorn_new_replicated_row()
        //   with http_get('/api/datachange') pg_http extension
        //   passes in parameters on the query String $_GET
        //   routes.php: API route /api/datachange
        $response = NULL;
        try {
            $TG_NAME = $_GET['TG_NAME'];
            $TG_OP   = $_GET['TG_OP'];
            $TG_TABLE_SCHEMA = $_GET['TG_TABLE_SCHEMA'];
            $TG_TABLE_NAME   = $_GET['TG_TABLE_NAME'];
            $ID      = $_GET['ID']; // UUID
        } catch (Exception $ex) {
            http_response_code(404);
            $response = "API DataChange event construction failed with " . $ex->getMessage();
        }

        if (!$response) {
            DataChange::dispatch($TG_NAME, $TG_OP, $TG_TABLE_SCHEMA, $TG_TABLE_NAME, $ID);
            $response = "DataChange event for $this->modelClass($this->ID) Dispatched";
        }

        return $response;
    }

    public function comment(): string
    {
        $response = 'Not understood';

        if ($dbLangPath = post('dbLangPath')) {
            // dbLangPath: tables.public.acorn.criminal.legalcase_defendants.foreignkeys.legalcase_id
            $dbPath = explode('.', $dbLangPath);
            if ($comment = post('comment')) {
                // Update request
                switch ($dbPath[0]) {
                    case 'tables':
                        $schema = $dbPath[1];
                        $table  = implode('_', array_slice($dbPath, 2,3));
                        switch ($dbPath[5]) {
                            case 'columns':
                                $name     = $dbPath[6];
                                // EloquentDB::select();
                                $response = "Update Ok [$schema.$table column $name]";
                                Migration::setColumnComment("$schema.$table", $name, $comment);
                                break;
                            case 'foreignkeys':
                                $name     = $dbPath[6];
                                Migration::setForeignKeyComment("$schema.$table", $name, $comment);
                                $response = "Update Ok [$schema.$table foreign key $name]";
                                break;
                            default:
                                throw new \Exception("Unconsidered functionality");
                                Migration::setTableComment("$schema.$table", $comment);
                        }
                        break;
                }
            }
        } else {
            // TODO: Get and return the comment
        }

        return $response;
    }
}
