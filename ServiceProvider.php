<?php namespace AcornAssociated;

use App;
use Url;
use Lang;
use File;
use Event;
use Config;
use Backend;
use BackendMenu;
use BackendAuth;
use Backend\Models\UserRole;
use System\Classes\CombineAssets;
use Backend\Classes\WidgetManager;
use System\Classes\MarkupManager;
use System\Classes\SettingsManager;

use Winter\Storm\Support\ModuleServiceProvider;

class ServiceProvider extends ModuleServiceProvider
{
    public function boot()
    {
        if (self::isDebug()) {
            print('<style>');
            print('.debug {');
                print('position:relative; display:table-caption; z-index:1000; width:fit-content; z-index:1000;');
                print('background-color:#ffffca; border:1px solid #aaa; padding:0px 2px; color:#533;');
            print('}');
            print('.debug-view {color:darkgreen;}');
            print('.debug-controller {font-weight:bold; color:darkblue;}');
            print('.debug-behavior {font-weight:bold; font-style:italic; color:blue;}');
            print('.debug-widgetbase {font-weight:bold; color:blue;}');
            print('.debug-path {font-style:italic; color:#444;}');
            print('.debug-viewpaths {font-style:italic; color:#444; padding-left:10px; border-left:2px double}');
            print('.debug-configpaths {font-style:italic; color:#444; padding-left:10px; border-left:2px double red}');
            print('</style>');
        }
    }

    static protected function isDebug()
    {
        return isset($_GET['debug']);
    }

    static protected function isAJAX()
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest';
    }

    static public function showException(string $message, $dd = FALSE, ?bool $onlyWhenDebug = TRUE)
    {
        static $sentCSS = FALSE;

        if (!$onlyWhenDebug || self::isDebug() || self::isAJAX()) {
            if (!$sentCSS) {
                $sentCSS = TRUE;
                print("<link href='/modules/system/assets/css/styles.css' rel='stylesheet'>");
            }
            print("<div class='exception-name-block'><div>$message</div><p>");
            $backtrace = debug_backtrace();
            for ($i = 0; $i < count($backtrace) && $i < 8; $i++) {
                $entry = &$backtrace[$i];
                $file  = str_replace($_SERVER['DOCUMENT_ROOT'], '~/', $entry['file']);
                print("$file <span>line</span> $entry[line] $entry[function]()</br>");
            }
            print("</p></div>");

            if ($dd === TRUE)  die();
            if ($dd !== FALSE) dd($dd);
        }
    }
}
