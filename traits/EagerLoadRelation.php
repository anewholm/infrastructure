<?php

namespace Acorn\Traits;

use Winter\Storm\Database\Builder;

/**
 * This algorithm changes the relationship from lazy to load relationship
 *
 * @use
 * on Class add
 *
 * use Acorn\Backendlocalization\Class\EagerLoadRelation;
 *
 *  @author JaberRasul
 *  @package Acorn
 */

trait EagerLoadRelation
{
    protected static function booted(): void
    {
        $instance = new static;
        
        $belongsToRelation = array_keys(array_filter($instance->belongsTo, function ($item) {
            return (isset($item['eager_load']) && $item['eager_load']);
        }));

        if ($belongsToRelation) {
            static::addGlobalScope('loadRelation', function (Builder $builder) use ($belongsToRelation) {
                $builder->with($belongsToRelation);
            });
        }
    }
}
