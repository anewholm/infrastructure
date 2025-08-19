<?php namespace Acorn\Controllers;

use BackendMenu;
use Acorn\Controller; // extends Backend\Classes\Controller
use Acorn\Models\Name;

/**
 * DB Backend Controller
 */
class Names extends Controller
{
    public $implement = [
        '\Acorn\Behaviors\ListController',
    ];

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Acorn', 'names');

        // List widget
        $listConfig           = $this->makeConfig($this->getConfig());
        $columnsConfig        = $this->makeConfig($this->getConfig('list'));
        $listConfig->columns  = $columnsConfig->columns;
        $listConfig->model    = new Name;
        $listConfig->alias    = 'list';
        $this->widget->list   = $this->makeWidget('Backend\Widgets\Lists', $listConfig);

        // Search Widget
        // TODO: search not working
        $searchConfig = $listConfig;
        $searchConfig->alias = 'search';
        $this->widget->search = $this->makeWidget('Backend\Widgets\Search', $searchConfig);
    }

    public function index()
    {
        $this->pageTitle = trans('acorn::lang.models.name.label_plural');
        $html  = $this->widget->search->render();
        $html .= $this->widget->list->render();
        return $html;
    }
}
