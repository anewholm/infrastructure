<?php namespace Acorn\Behaviors;

use \Backend\Behaviors\ListController as BackendListController;
use \Exception;

class ListController extends BackendListController
{
    public function __construct($controller)
    {
        parent::__construct($controller);
        $this->addViewPath('~/modules/backend/behaviors/listcontroller/partials');
    }
}
