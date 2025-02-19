<?php namespace Acorn;

use Request;
use Config;
use Backend\Classes\Controller;
use Backend\Classes\BackendController;

// Helper class to allow access to the requested controller
class BackendRequestController extends BackendController {
    protected function __construct() {}

    public static function getControllerInfo(string $url = NULL): ?array {
        if (!$url) $url = Request::url();
        $url = trim($url, '/');

        // Copied from BackendController
        $pathParts = explode('/', str_replace(Request::root() . '/', '', $url));
        // Drop off preceding backend URL part if needed
        if (!empty(Config::get('cms.backendUri', 'backend'))) array_shift($pathParts);
        $path = implode('/', $pathParts);

        // Call the protected method on the base class
        $brc = new self();
        return $brc->getRequestedController($path); // Actually an array of controller info
    }

    public static function getController(string $url = NULL): ?Controller {
        $controllerInfo = self::getControllerInfo($url);
        return (isset($controllerInfo['controller']) ? $controllerInfo['controller'] : NULL);
    }

    public static function isUpdate(string $url = NULL): bool {
        $controllerInfo = self::getControllerInfo($url);
        return (isset($controllerInfo['action']) && $controllerInfo['action'] == 'update');
    }
}
