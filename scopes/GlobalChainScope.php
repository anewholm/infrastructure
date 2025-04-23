<?php
namespace Acorn\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Session;

class GlobalChainScope implements Scope
{
    public static function globalScopeRelations(Model $model): array
    {
        $globalScopeRelations = array();

        $relationConfigs = $model->belongsTo + $model->hasMany;
        foreach ($relationConfigs as $relationName => $relationConfig) {
            if (isset($relationConfig['global_scope']) 
                && $relationConfig['global_scope'] 
                && isset($relationConfig[0])
            ) {
                $globalScopeRelations[$relationName] = $model->$relationName();
            }
        }

        return $globalScopeRelations;
    }

    public static function globalScopeClasses(Model $model): array
    {
        $globalScopeClasses = array();

        // TODO: This only returns the last global-scope chain. Should return all
        while ($globalScopeRelations = self::globalScopeRelations($model)) {
            foreach ($globalScopeRelations as $relation) {
                $model = $relation->getRelated();
            }
        }
        if (property_exists($model, 'globalScope') && $model::$globalScope)
            array_push($globalScopeClasses, $model);

        return $globalScopeClasses;
    }

    public static function chainScopes(Model $model): array
    {
        $chainScopes = array();
        if ($chainScopes = $model->getGlobalScopes()) { // For calling class
            foreach ($chainScopes as $class => $chainScope) {
                if ($chainScope instanceof Closure) {
                    // We do not honour Closures
                    // $chainScope($builder);
                } else if ($chainScope instanceof static) {
                    // We only honour Scopes that descend from our GlobalChainScope
                    $chainScopes[$class] = $chainScope;
                }
            }
        }
        return $chainScopes;
    }

    public static function applySession(Builder $builder, Model $model): bool 
    {
        // Usually called with an apply() override:
        // public function apply(Builder $builder, Model $model): bool {
        //     return self::applySession($builder, $model);
        // }
        // Returing TRUE causes the scope chain to be recursively applied
        $class       = get_class($model);
        $settingName = "$class::globalScope";
        $setting     = Session::get($settingName);
        if ($setting)
            $builder->where("$model->table.id", '=', $setting);
        return !is_null($setting);
    }

    public function apply(Builder $builder, Model $model): bool {
        // Follow global_scope => TRUE relation(s)
        $apply = FALSE;
        $globalScopeRelations = self::globalScopeRelations($model);
        foreach ($globalScopeRelations as $relation) {
            // Chain all global_scope relations
            // For calling class
            $relatedModel = $relation->getRelated();
            $chainScopes  = self::chainScopes($relatedModel);
            foreach ($chainScopes as $chainScope) {
                // Inherit your Scope from GlobalChainScope to activate this chain
                $apply = $chainScope->apply($builder, $relatedModel);
            }

            // We conditionally apply the contraints afterwards 
            // in case there is no setting at the end of the chain
            if ($apply) {
                // TODO: $relation->addConstraints();
                // TODO: At least $relation->getQualifiedForeignKeyName()
                // $relation->setQuery($builder);
                $reverse      = ($relation instanceof BelongsTo);
                $key          = $relation->getForeignKeyName();
                $columnFrom   = ($reverse ? $key : 'id');
                $columnTo     = ($reverse ? 'id' : $key);
                $fqTableFrom  = "$model->table.$columnFrom";
                $fqTableTo    = "$relatedModel->table.$columnTo";
                $builder->join($relatedModel->table, $fqTableFrom, '=', $fqTableTo);
            }
        }

        return $apply;
    }
}
