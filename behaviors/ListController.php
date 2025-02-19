<?php namespace AcornAssociated\Behaviors;

use \Backend\Behaviors\ListController as BackendListController;
use \Exception;
use Backend\Widgets\Search;

class ListController extends BackendListController
{
    use \AcornAssociated\Traits\MorphConfig;
    
    public function __construct($controller)
    {
        parent::__construct($controller);

        $this->addViewPath('~/modules/backend/behaviors/listcontroller/partials');

        Search::extend(function ($widget) {
            $widget->addViewPath('~/modules/acornassociated/partials/');
        });

    }
}
