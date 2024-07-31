<?php namespace Acorn;

use Model;
use BackendAuth;
use \Backend\Models\User;
use \Backend\Models\UserGroup;
use ApplicationException;

use Illuminate\Support\Str;
// Illuminate\Database\Eloquent\Builder
use Winter\Storm\Database\Builder as BaseBuilder; 
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Winter\Storm\Database\QueryBuilder;

use BadMethodCallException;
use Illuminate\Database\Eloquent\RelationNotFoundException;
use InvalidArgumentException;

class Builder extends BaseBuilder
{
    /**
     * Add a "belongs to one|many" relationship(s) where clause to the query.
     * 
     * @param  Array of \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Collection<\Illuminate\Database\Eloquent\Model>  $related
     * @param  string|null  $relationshipName
     * @param  string  $boolean or|and
     * @param  bool    $not 
     * @return Builder
     *
     * @throws \Illuminate\Database\Eloquent\RelationNotFoundException
     */
    public function whereBelongsToAny(Array $relatedArray, ?string $boolean = 'or', ?bool $throwOnEmpty = FALSE): Builder
    {
        $query   = &$this;
        $base    = clone $this;
        $first   = TRUE;

        foreach ($relatedArray as $related) {
            if ($related instanceof Model) {
                $relatedCollection = $related->newCollection([$related]);
            } elseif ($related instanceof Collection) {
                $relatedCollection = $related;
                $related = $relatedCollection->first();
            }
    
            if ($relatedCollection->isEmpty()) {
                if ($throwOnEmpty)
                    throw new InvalidArgumentException('Collection given to whereBelongsToMany method may not be empty.');
            } else {
                if (!$first) $query = clone $base;

                $relationship = $this->guessRelationFor($query->model, $related);
                if ($relationship instanceof BelongsToMany)
                    $query->whereBelongsToMany($related, NULL, $boolean, $throwOnEmpty);
                else
                    $query->whereBelongsTo($related, NULL, $boolean);
                if (!$first) $this->union($query); 

                $first = FALSE;
            }
        }

        return $this;
    }

    protected function guessRelationFor(Model $model, Model|string $related, ?bool $throwOnUnknown = TRUE): Relation
    {
        // Search for the relationship: plural, then single
        $relationshipNameSingle = Str::camel($related instanceof Model
            ? class_basename($related)
            : preg_replace('#.*\\\\#', '', $related)
        );
        $relationshipNamePlural = Str::plural($relationshipNameSingle);
        try {
            $relationship = $model->{$relationshipNamePlural}();
        } catch (BadMethodCallException $exception) {
            try {
                $relationship = $model->{$relationshipNameSingle}();
            } catch (BadMethodCallException $exception) {
                if ($throwOnUnknown) 
                    throw RelationNotFoundException::make($model, "$relationshipNameSingle|$relationshipNamePlural");
            }
        }
        return $relationship;
    }

    protected function joinBelongsRelationship(BelongsToMany|BelongsTo $relationship, ?bool $fullManyJoin = TRUE): Builder
    {
        if ($relationship instanceof BelongsToMany) {
            // class Relation:
            //   $parent  Model EventPart
            //   $related Model UserGroup
            //
            // class BelongsToMany extends Relation:
            //   $table pivot table, 
            //     acorn_calendar_event_user_group
            // 
            //   getQualifiedParentKeyName()
            //     acorn_calendar_event_part.id
            //   getQualifiedForeignPivotKeyName()
            //     acorn_calendar_event_user_group.event_part_id
            //   getQualifiedRelatedPivotKeyName()
            //     acorn_calendar_event_user_group.user_group_id
            //   getQualifiedRelatedKeyName()
            //     backend_user_groups.id

            // Join TO pivot table only
            // TODO: Why does BelongsToMany not seem to have a function for joining?
            // performJoin() does not do this.
            $this->join(
                $relationship->getTable(), // pivot / intermediate table
                $relationship->getQualifiedParentKeyName(),
                '=',
                $relationship->getQualifiedForeignPivotKeyName(),
            );
            if ($fullManyJoin) {
                $this->join(
                    $relationship->getTable(), // pivot / intermediate table
                    $relationship->getQualifiedRelatedPivotKeyName(),
                    '=',
                    $relationship->getQualifiedRelatedKeyName(),
                );
            }
        } elseif  ($relationship instanceof BelongsTo) {
            $this->join(
                $relationship->getModel()->getTable(),
                $relationship->getQualifiedForeignKeyName(),
                '=',
                $relationship->getQualifiedOwnerKeyName(),
            );
        }

        return $this;
    }

    public function whereBelongsToAnyThrough(string|Array $through, Array $relatedArray, ?string $boolean = 'or', ?bool $throwOnEmpty = FALSE): Builder
    {
        $originalModel = clone $this->model;
        if (is_array($through)) {
            foreach ($through as $throughModel) {
                $this->joinBelongsRelationship(
                    $this->guessRelationFor($this->model, $throughModel)
                );
                // Need to step by step move through the models
                $this->model = new $throughModel();
            }
        } else {
            $this->joinBelongsRelationship(
                $this->guessRelationFor($this->model, $through)
            );
            $this->model = new $through();
        }

        // Working off the new final through model
        // make some joins
        $builder = $this->whereBelongsToAny($relatedArray, $boolean, $throwOnEmpty);

        // We want the get() process to create the original models
        // not the through models
        $this->model = $originalModel;

        return $builder;
    }

    public function whereBelongsToMany(Collection|Model $related, ?string $relationshipName = NULL, ?string $boolean = 'or', ?bool $throwOnEmpty = FALSE): Builder
    {
        if ($related instanceof Model) {
            $relatedCollection = $related->newCollection([$related]);
        } elseif ($related instanceof Collection) {
            $relatedCollection = $related;
            $related = $relatedCollection->first();
        }

        if ($relatedCollection->isEmpty()) {
            if ($throwOnEmpty)
                throw new InvalidArgumentException('Collection given to whereBelongsToMany method may not be empty.');
        } else {
            $relationship = $this->guessRelationFor($this->model, $related);
            if (! $relationship instanceof BelongsToMany) {
                throw RelationNotFoundException::make($this->model, $related::class, BelongsToMany::class);
            }

            $this->joinBelongsRelationship($relationship, FALSE);

            // Where in our collection of related objects
            $this->whereIn(
                $relationship->getQualifiedRelatedPivotKeyName(),
                $relatedCollection->pluck($relationship->getRelatedKeyName())->toArray(),
                $boolean
            );
        }
        
        return $this;
    }    
}
