<?php namespace Acorn\Behaviors;

use \Backend\Behaviors\ListController as BackendListController;
use \Exception;
use Backend\Widgets\Search;

class ListController extends BackendListController
{
    use \Acorn\Traits\MorphConfig;

    public $readOnly = FALSE;
    
    public function __construct($controller)
    {
        parent::__construct($controller);

        $this->addViewPath('~/modules/backend/behaviors/listcontroller/partials');
        $this->addViewPath('~/modules/acorn/behaviors/listcontroller/partials');

        Search::extend(function ($widget) {
            $widget->addViewPath('~/modules/acorn/partials/');
        });
    }
}
