<?php namespace Acorn\Relationships;

use \Staudenmeir\EloquentHasManyDeep\HasManyDeep as StaudenmeirHasManyDeep;
use Acorn\Collection;
use Acorn\Collection as CollectionBase;
use Acorn\Model;
use Acorn\Builder;

class HasManyDeep extends StaudenmeirHasManyDeep
{
    use \Winter\Storm\Database\Relations\Concerns\HasOneOrMany;
    use \Winter\Storm\Database\Relations\Concerns\DefinedConstraints;

    /**
     * Create a new has many relationship instance.
     * @return void
     */
    public function __construct(Builder $query, Model $farParent, array $throughParents, array $foreignKeys, array $localKeys, string $relationName = null)
    {
        $this->relationName = $relationName;

        parent::__construct($query, $farParent, $throughParents, $foreignKeys, $localKeys);

        $this->addDefinedConstraints();
    }

    public function getParentKey()
    {
        // Additional Storm method
        return $this->parent->getAttribute($this->localKey);
    }

    /**
     * Helper for setting this relationship using various expected
     * values. For example, $model->relation = $value;
     */
    public function setSimpleValue($value)
    {
        // Nulling the relationship
        if (!$value) {
            if ($this->parent->exists) {
                $this->parent->bindEventOnce('model.afterSave', function () {
                    $this->update([$this->getForeignKeyName() => null]);
                });
            }
            return;
        }

        if ($value instanceof Model) {
            $value = new Collection([$value]);
        }

        if ($value instanceof CollectionBase) {
            $collection = $value;

            if ($this->parent->exists) {
                $collection->each(function ($instance) {
                    $instance->setAttribute($this->getForeignKeyName(), $this->getParentKey());
                });
            }
        }
        else {
            $collection = $this->getRelated()->whereIn($this->localKey, (array) $value)->get();
        }

        if ($collection) {
            $this->parent->setRelation($this->relationName, $collection);

            $this->parent->bindEventOnce('model.afterSave', function () use ($collection) {
                $existingIds = $collection->pluck($this->localKey)->all();
                $this->whereNotIn($this->localKey, $existingIds)->update([$this->getForeignKeyName() => null]);
                $collection->each(function ($instance) {
                    $instance->setAttribute($this->getForeignKeyName(), $this->getParentKey());
                    $instance->save(['timestamps' => false]);
                });
            });
        }
    }

    /**
     * Helper for getting this relationship simple value,
     * generally useful with form values.
     */
    public function getSimpleValue()
    {
        $value = null;
        $relationName = $this->relationName;

        if ($relation = $this->parent->$relationName) {
            $value = $relation->pluck($this->localKey)->all();
        }

        return $value;
    }
}
