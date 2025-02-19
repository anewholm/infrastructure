<?php namespace AcornAssociated\Events;

use Illuminate\Foundation\Events\Dispatchable;
use AcornAssociated\Model;

class ModelAfterSave
{
    use Dispatchable;

    public $model;

    public function __construct(Model &$model)
    {
        $this->model = &$model;
    }
}
