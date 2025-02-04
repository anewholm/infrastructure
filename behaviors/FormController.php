<?php namespace Acorn\Behaviors;

use Backend\Behaviors\FormController as BackendFormController;
use Model;

class FormController extends BackendFormController
{
    use \Acorn\Traits\MorphConfig;
}
