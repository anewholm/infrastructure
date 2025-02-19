<?php namespace AcornAssociated;

use DB;
use App;
use Url;
use Lang;
use File;
use Event;
use Config;
use Backend;
use BackendMenu;
use BackendAuth;
use Backend\Models\User;
use Backend\Models\UserRole;
use System\Classes\CombineAssets;
use Backend\Classes\WidgetManager;
use System\Classes\MarkupManager;
use System\Classes\SettingsManager;
use Backend\Classes\FormTabs;
use AcornAssociated\FormWidgets\QrCode;
use SimpleSoftwareIO\QrCode\Facades\QrCode as SimpleSoftwareQrCode;
use Backend\Widgets\Lists as BackendLists;

use Winter\Storm\Support\ModuleServiceProvider;
use BeyondCode\LaravelWebSockets\Console\StartWebSocketServer;
use AcornAssociated\Messaging\Console\RunCommand;
use \System\Controllers\Updates;

class ServiceProvider extends ModuleServiceProvider
{
    static $pluginFlags = array();

    public function boot()
    {
        // -------------------------------------- Global CSS
        if (self::isDebugAny()) {
            Event::listen('backend.page.beforeDisplay', function ($controller, $action, $params) {
                $controller->addCss('~/modules/acornassociated/assets/css/debug.css');
                $controller->addJs( '~/modules/acornassociated/assets/js/debug.js');
            });
        }
        Event::listen('backend.page.beforeDisplay', function ($controller, $action, $params) {
            $controller->addCss('~/modules/acornassociated/assets/css/module.css');
            $controller->addJs('~/modules/acornassociated/assets/js/acornassociated.js');
            $controller->addJs('~/modules/acornassociated/assets/js/acornassociated.websocket.js', array('type' => 'module'));
            $controller->addJs('~/modules/acornassociated/assets/js/html5-qrcode.js');
            $controller->addJs('~/modules/acornassociated/assets/js/findbyqrcode.js');
            $controller->addJs('~/modules/acornassociated/assets/js/forms.js');
            $controller->addJs('~/modules/acornassociated/assets/js/tabbing.js');
            $controller->addJs('~/modules/acornassociated/assets/js/lang/lang.'.App::getLocale().'.js');//Translate JS [en][ar][ku]
        });

        Event::listen('backend.form.extendFields', function ($widget) {
            if (property_exists($widget->config, 'tertiaryTabs')) {
                $tabConfig      = &$widget->config->tertiaryTabs;

                // Add the fields to the outside and allFields section
                $count          = count($widget->getFields());
                $widget->addFields($tabConfig['fields']);
                $allFields      = $widget->getFields();
                $tertiaryFields = array_splice($allFields, $count);

                // Create a new Tertiary area FormTab for reference
                // Use of a form-with-sidebar layout is necessary to show this area
                // using formTertiaryTabs()
                $allTabs = $widget->getTabs();
                $allTabs->tertiary = new FormTabs('tertiary', $tabConfig);
                foreach ($tertiaryFields as $name => &$field) {
                    $allTabs->tertiary->addField($name, $field);
                    // Remove the fields from the outside area
                    $allTabs->outside->removeField($name);
                }
            }
        });

        // --------------------------------------------- acornassociated_infrastructure
        if (!self::$pluginFlags) {
            $results = DB::select('select * from public.system_plugin_versions');
            foreach ($results as $result) self::$pluginFlags[$result->code] = $result;
        }

        Event::listen('backend.menu.extendItems', function (&$navigationManager) {
            $mainMenuItems = $navigationManager->listMainMenuItems();
            foreach (self::$pluginFlags as $plugin) {
                if (property_exists($plugin, 'acornassociated_infrastructure') && $plugin->acornassociated_infrastructure) {
                    foreach ($mainMenuItems as $mainMenu) {
                        if ($plugin->code == $mainMenu->owner) 
                            $navigationManager->removeMainMenuItem($plugin->code, $mainMenu->code);
                    }
                }
            }
        });

        Updates::extendListColumns(function ($widget, $model) {
            // We need to be careful when using the database
            // during migrations, tables may not exist
            $widget->getController()->addViewPath('modules/acornassociated/partials');
            $widget->addColumns([
                'acornassociated_infrastructure' => [
                    'label'   => 'acornassociated::lang.settings.infrastructure',
                    'type'    => 'partial',
                    'path'    => 'is_infrastructure',
                ],
                'acornassociated_seeding' => [
                    'label'   => 'acornassociated::lang.settings.seeding_functions',
                    'type'    => 'partial',
                    'path'    => 'seeding_functions',
                ],
            ]);
        });

        parent::boot('acornassociated');
    }

    protected function missingServices(): array
    {
        // -------------------------------------- Daemons manager
        // We do not want our artisan commands below to also run this!
        // continuous loop would it be
        // TODO: Alert administrator to any missing commands
        // TODO: Allow plugins to register there own persistent commands
        // using an interface and a call in their Plugin.php
        // maybe inheriting from AA Command that can run itself
        if (self::isHTTPCall() && self::isDebug()) {
            $commands = array(
                'messaging:run'    => '',
                'websockets:serve' => '--port 6001',
                //'calendar:run' => FALSE,
            );

            // Check for running commands
            $running = array();
            $ps      = exec('ps ax | grep -v grep | grep artisan', $running);
            foreach ($running as $line) {
                $pdetails = preg_split('/\s+/', trim($line));
                if (isset($pdetails[6])) {
                    //$id      = $pdetails[0];
                    //$artisan = $pdetails[5];
                    $command = $pdetails[6];
                    $options = implode(' ', array_slice($pdetails, 7));
                    if (isset($commands[$command])) unset($commands[$command]);
                }
            }
        }

        return $commands;
    }

    public function register()
    {
        parent::register();

        // Settings placeholders
        SettingsManager::instance()->registerCallback(function ($manager) {
            $manager->registerSettingItems('AcornAssociated.Module', [
                'interface' => [
                    'label'       => 'acornassociated::lang.settings.interface.menu_label',
                    'description' => 'acornassociated::lang.settings.interface.menu_description',
                    'category'    => 'AcornAssociated',
                    'icon'        => 'icon-paint-brush',
                    'class'       => 'AcornAssociated\Models\InterfaceSetting',
                    'permissions' => ['acornassociated.manage_interface'],
                    'order'       => 500,
                    'keywords'    => 'interface'
                ],
                'reporting' => [
                    'label'       => 'acornassociated::lang.settings.reporting.menu_label',
                    'description' => 'acornassociated::lang.settings.reporting.menu_description',
                    'category'    => 'AcornAssociated',
                    'icon'        => 'icon-book',
                    'class'       => 'AcornAssociated\Models\ReportingSetting',
                    'permissions' => ['acornassociated.manage_reporting'],
                    'order'       => 500,
                    'keywords'    => 'reporting'
                ],
                'phpinfo' => [
                    'label'       => 'acornassociated::lang.settings.phpinfo.menu_label',
                    'description' => 'acornassociated::lang.settings.phpinfo.menu_description',
                    'category'    => 'AcornAssociated',
                    'icon'        => 'icon-chart-simple',
                    'class'       => 'AcornAssociated\Models\PhpInfo',
                    'permissions' => ['acornassociated.manage_reporting'],
                    'order'       => 500,
                    'keywords'    => 'reporting'
                ],
            ]);
        });

        // Register FormWidgets
        WidgetManager::instance()->registerFormWidgets(function($manager) {
            $manager->registerFormWidget('AcornAssociated\FormWidgets\QrScan', [
                'label' => 'QR Scan Field',
                'code'  => 'qrscan'
            ]);
            $manager->registerFormWidget('AcornAssociated\FormWidgets\QrCode', [
                'label' => 'QR Generate Field',
                'code'  => 'qrcode'
            ]);
        });
    }

    // ---------------------------------------- Status helpers
    protected static function isCommandLine(): bool
    {
        return !self::isHTTPCall();
    }

    protected static function isHTTPCall(): bool
    {
        return isset($_SERVER['HTTP_HOST']);
    }

    static public function isDebugAny(): bool
    {
        $isDebugAny = FALSE;
        if (env('APP_DEBUG')) {
            foreach ($_GET as $name => $value) {
                if (substr($name, 0, 5) == 'debug') $isDebugAny = TRUE;
            }
        }
        return $isDebugAny;
    }

    static public function isDebug(string $type): bool
    {
        return (env('APP_DEBUG') && (isset($_GET["debug-$type"]) || isset($_GET['debug-all'])));
    }

    static protected function isAJAX(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest';
    }
}
