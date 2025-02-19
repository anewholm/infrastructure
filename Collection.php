<?php

namespace AcornAssociated;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * @package acornassociated\builder
 * @author Sanchez
 */
class Collection extends EloquentCollection
{
    /**
     * Generate an associative array for a dropdown options.
     *
     * @param string $key   The field to use as the dropdown key. Default is 'name'.
     * @param string $value The field to use as the dropdown value. Default is 'id'.
     * @return array        An associative array where the keys are the values of the specified key field,
     *                      and the values are the values of the specified value field.
     */
    public function lists($value = 'name', $key = 'id')
    {
        // parent::lists(...) does not work due to static::hasMacro() checking
        return $this->pluck($value, $key)->all();
    }

    public function ids()
    {
        return $this->pluck('id')->toArray();
    }
}
