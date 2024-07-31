<?php

namespace Acorn;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;


/**
 * @package acorn\builder
 * @author Jaber Rasul , Sanchez 
 */
class Collection extends EloquentCollection
{
    /**
     * If you want to use this algorithm you must:
     * On Your Model Class Add 
     * This method 
     * 
     * public function newCollection(array $models = [])
     *   {   
     *        return new Collection($models);
     *   }
     * 
     * this method its change model proparty $model from Illuminate\Database\Eloquent\Collection to Acorn/Colleciton
     */



    /**
     * Generate an associative array for a dropdown menu.
     *
     * This method maps the collection to an array of key-value pairs, using the
     * specified fields from the collection items.
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
}
