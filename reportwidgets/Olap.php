<?php namespace Acorn\ReportWidgets;

use Lang;
use Backend\Classes\ReportWidgetBase;
use Exception;
use Str;
use DomDocument;

/**
 * User welcome report widget.
 *
 * @package winter\wn-backend-module
 * @author Alexey Bobkov, Samuel Georges
 */
class Olap extends ReportWidgetBase
{
    /**
     * @var string A unique alias to identify this widget.
     */
    protected $defaultAlias = 'olap';

    /**
     * Renders the widget.
     */
    public function render()
    {
        try {
            $this->loadData();
        }
        catch (Exception $ex) {
            $this->vars['error'] = $ex->getMessage();
        }

        return $this->makePartial('widget');
    }

    public function defineProperties()
    {
        return [
            'title' => [
                'title'             => 'backend::lang.dashboard.widget_title_label',
                'default'           => 'acorn::lang.dashboard.olap.widget_title_default',
                'type'              => 'string',
                'validationPattern' => '^.+$',
                'validationMessage' => 'backend::lang.dashboard.widget_title_error',
            ],
            'tomcat_root' => [
                'title'             => 'acorn::lang.dashboard.widget_tomcat_root_label',
                'default'           => '/var/lib/tomcat9/webapps',
                'type'              => 'string',
                'validationPattern' => '^.+$',
                'validationMessage' => 'acorn::lang.dashboard.widget_tomcat_root_error',
            ],
            'tomcat_port' => [
                'title'             => 'acorn::lang.dashboard.widget_tomcat_port_label',
                'default'           => '80',
                'type'              => 'string',
                'validationPattern' => '^.+$',
                'validationMessage' => 'acorn::lang.dashboard.widget_tomcat_port_error',
            ],
            'tomcat_domain' => [
                'title'             => 'acorn::lang.dashboard.widget_tomcat_domain_label',
                'default'           => '',
                'type'              => 'string',
                'validationPattern' => '^.+$',
                'validationMessage' => 'acorn::lang.dashboard.widget_tomcat_domain_error',
            ]

        ];
    }

    /**
     * @inheritDoc
     */
    protected function loadAssets()
    {
        $this->addCss('css/olap.css', 'core');
    }

    protected function loadData()
    {
        $tomcatRoot   = $this->property('tomcat_root', '/var/lib/tomcat9/webapps');
        $tomcatPort   = $this->property('tomcat_port', '80');
        $tomcatDomain = $this->property('tomcat_domain', $_SERVER['HTTP_HOST']);
        if (!$tomcatRoot)   $tomcatRoot   = '/var/lib/tomcat9/webapps';
        if (!$tomcatDomain) $tomcatDomain = $_SERVER['HTTP_HOST'];
        $locale = Lang::getLocale();

        $tomcatURI = "http://$tomcatDomain";
        if ($tomcatPort && $tomcatPort != '80') $tomcatURI .= ":$tomcatPort";
        $standardSchemaPath = 'WEB-INF/schema/cubes.xml';

        $tomcatRoot = trim($tomcatRoot, '/');
        $tomcatRoot = "/$tomcatRoot/";
        if (file_exists($tomcatRoot)) {
            $this->vars['webapps'] = array();
            foreach (new \DirectoryIterator($tomcatRoot) as $item) {
                if ($item->isDir() && !$item->isDot()) {
                    // TODO: Check if there is a schema.xml in the directory
                    $webapp     = $item->getFileName();
                    $schemaPath = "$tomcatRoot/$webapp/$standardSchemaPath";
                    if (file_exists($schemaPath)) {
                        // TODO: Load schema.xml and list cubes
                        $xCubes = new DomDocument();
                        $xCubes->load($schemaPath);
                        $xSchema = $xCubes->firstElementChild;
                        $cubes   = array();
                        foreach ($xSchema->childNodes as $xCube) {
                            if ($xCube->nodeType == 1) {
                                $name       = $xCube->getAttribute('name');
                                $cubeLocale = $xCube->getAttribute('locale');
                                if (!$cubeLocale || $locale == $cubeLocale) $cubes[$name]   = $xCube;
                            }
                        }

                        $imageFile = strtolower($webapp);
                        $imagePath = "images/$imageFile.png";
                        if (file_exists("$tomcatRoot/ROOT/$imagePath")) $imagePath = "$tomcatURI/$imagePath";
                        else $imagePath = NULL;

                        $password = 'fryace4';
                        $index    = ($locale == 'en' ? 'index' : "index-$locale");
                        $url      = "$tomcatURI/$webapp/xavier/$index.html?password=$password";
                        $this->vars['webapps'][$webapp] = array(
                            'url'   => $url,
                            'title' => Str::title($webapp),
                            'image' => $imagePath,
                            'cubes' => $cubes,
                        );
                    }
                }
            }
        } else {
            $this->vars['error'] = "No reports. TomCat9 server root $this->tomcatRoot not found.";
        }
    }
}
