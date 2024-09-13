<?php namespace Acorn\Controllers;

use BackendMenu;
use Acorn\Controller; // extends Backend\Classes\Controller
use Acorn\Model;
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
}
