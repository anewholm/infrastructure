<?php namespace AcornAssociated\Behaviors;

use Backend\Behaviors\FormController as BackendFormController;
use Model;

class FormController extends BackendFormController
{
    use \AcornAssociated\Traits\MorphConfig;
}
