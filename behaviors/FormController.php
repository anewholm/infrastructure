<?php namespace Acorn\Behaviors;

use Backend\Behaviors\FormController as BackendFormController;
use Model;

class FormController extends BackendFormController
{
    use \Acorn\Traits\MorphConfig;

    public function create($context = NULL)
    {
        parent::create($context);

        // Allow post-action re-setting of body class
        // as ListController::index() resets it
        if (method_exists($this->controller, 'bodyClassAdjust')) 
            $this->controller->bodyClassAdjust();
    }

}
