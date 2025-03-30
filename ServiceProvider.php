<?php namespace Acorn;

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
use Acorn\FormWidgets\QrCode;
use SimpleSoftwareIO\QrCode\Facades\QrCode as SimpleSoftwareQrCode;
use Backend\Widgets\Lists as BackendLists;

use Winter\Storm\Support\ModuleServiceProvider;
use BeyondCode\LaravelWebSockets\Console\StartWebSocketServer;
use Acorn\Messaging\Console\RunCommand;
use \System\Controllers\Updates;
use Acorn\Models\InterfaceSetting;
use Acorn\Console\SetConfig;
use Acorn\Console\ConfigPlugin;
use Acorn\Console\Seed;

class ServiceProvider extends ModuleServiceProvider
{
    public function boot()
    {
        // -------------------------------------- Global CSS
        if (self::isDebugAny()) {
            Event::listen('backend.page.beforeDisplay', function ($controller, $action, $params) {
                $controller->addCss('~/modules/acorn/assets/css/debug.css');
                $controller->addJs( '~/modules/acorn/assets/js/debug.js');
            });
        }
        Event::listen('backend.page.beforeDisplay', function ($controller, $action, $params) {
            $controller->addCss('~/modules/acorn/assets/css/module.css');
            $controller->addJs('~/modules/acorn/assets/js/acorn.js');
            $controller->addJs('~/modules/acorn/assets/js/html5-qrcode.js');
            $controller->addJs('~/modules/acorn/assets/js/findbyqrcode.js');
            $controller->addJs('~/modules/acorn/assets/js/forms.js');
            $controller->addJs('~/modules/acorn/assets/js/tabbing.js');
            $controller->addJs('~/modules/acorn/assets/js/lang/lang.'.App::getLocale().'.js');//Translate JS [en][ar][ku]

            if (InterfaceSetting::get('enable_websockets')) $controller->addJs('~/modules/acorn/assets/js/acorn.websocket.js', array('type' => 'module'));
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

        // --------------------------------------------- acorn_infrastructure
        Event::listen('backend.menu.extendItems', function (&$navigationManager) {
            // TODO: Maybe we can get pluginFlags from PluginManager
            $mainMenuItems = $navigationManager->listMainMenuItems();
            $pluginFlags   = DB::select('select * from public.system_plugin_versions');
            foreach ($pluginFlags as $plugin) {
                if (property_exists($plugin, 'acorn_infrastructure') && $plugin->acorn_infrastructure) {
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
            $widget->getController()->addViewPath('modules/acorn/partials');
            $widget->addColumns([
                'acorn_infrastructure' => [
                    'label'   => 'acorn::lang.settings.infrastructure',
                    'type'    => 'partial',
                    'path'    => 'is_infrastructure',
                ],
                'acorn_seeding' => [
                    'label'   => 'acorn::lang.settings.seeding_functions',
                    'type'    => 'partial',
                    'path'    => 'seeding_functions',
                ],
            ]);
        });

        parent::boot('acorn');
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

        $this->registerConsoleCommand('acorn.set-config', SetConfig::class);
        $this->registerConsoleCommand('acorn.config-plugin', ConfigPlugin::class);
        $this->registerConsoleCommand('acorn.seed', Seed::class);

        // Settings placeholders
        SettingsManager::instance()->registerCallback(function ($manager) {
            $manager->registerSettingItems('Acorn.Module', [
                'interface' => [
                    'label'       => 'acorn::lang.settings.interface.menu_label',
                    'description' => 'acorn::lang.settings.interface.menu_description',
                    'category'    => 'Acorn',
                    'icon'        => 'icon-paint-brush',
                    'class'       => 'Acorn\Models\InterfaceSetting',
                    'permissions' => ['acorn.manage_interface'],
                    'order'       => 500,
                    'keywords'    => 'interface'
                ],
                'phpinfo' => [
                    'label'       => 'acorn::lang.settings.phpinfo.menu_label',
                    'description' => 'acorn::lang.settings.phpinfo.menu_description',
                    'category'    => 'Acorn',
                    'icon'        => 'icon-chart-simple',
                    'class'       => 'Acorn\Models\PhpInfo',
                    'permissions' => ['acorn.manage_reporting'],
                    'order'       => 500,
                    'keywords'    => 'reporting'
                ],
            ]);
        });

        // Register FormWidgets
        WidgetManager::instance()->registerFormWidgets(function($manager) {
            $manager->registerFormWidget('Acorn\FormWidgets\QrScan', [
                'label' => 'QR Scan Field',
                'code'  => 'qrscan'
            ]);
            $manager->registerFormWidget('Acorn\FormWidgets\QrCode', [
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
