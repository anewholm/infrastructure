<?php namespace Acorn\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Acorn\Model;

class ModelAfterSave
{
    use Dispatchable;

    public $model;

    public function __construct(Model &$model)
    {
        $this->model = &$model;
    }
}
