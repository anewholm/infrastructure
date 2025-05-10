<?php
namespace Acorn\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Session;
use Exception;
use Flash;

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
        if ($allChainScopes = $model->getGlobalScopes()) { // For calling class
            foreach ($allChainScopes as $class => $chainScope) {
                if ($chainScope instanceof Closure) {
                    // We do not honour Closures
                    // $chainScope($builder);
                } else if ($chainScope instanceof GlobalChainScope) {
                    // We only honour Scopes that descend from our GlobalChainScope
                    $chainScopes[$class] = $chainScope;
                }
            }
        }

        if (count($chainScopes) > 1) {
            $class = get_class($model);
            throw new Exception("Multiple Scopes on $class not supported yet");
        }
        
        return $chainScopes;
    }

    public function shouldApply(Builder $builder, Model $model): bool
    {
        $shouldApply = FALSE;
        $globalScopeRelations = self::globalScopeRelations($model);
        foreach ($globalScopeRelations as $relation) {
            // Chain all global_scope relations
            // For calling class
            $relatedModel = $relation->getRelated();
            $chainScopes  = self::chainScopes($relatedModel);
            foreach ($chainScopes as $chainScope) {
                // Inherit your Scope from GlobalChainScope to activate this chain
                $shouldApply = $chainScope->shouldApply($builder, $relatedModel);
            }
        }
        
        return $shouldApply;
    }

    public static function hasSession(Model $model): bool
    {
        // Usually called with an apply() override:
        // public function shouldApply(Builder $builder, Model $model): bool {
        //     return self::hasSession($builder, $model);
        // }
        // Returing TRUE causes the scope chain to be recursively applied
        $class       = get_class($model);
        $settingName = "$class::globalScope";
        $setting     = Session::get($settingName);
        return (bool) $setting;
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
        return (bool) $setting;
    }

    public function apply(Builder $builder, Model $model): void {
        // Follow global_scope => TRUE relation(s)
        // This is overridden by
        //   YearScope::apply(...)
        if ($this->shouldApply($builder, $model)) 
            $this->applyRecursive($builder, $model);
    }

    public function applyRecursive(Builder $builder, Model $model): void {
        $globalScopeRelations = self::globalScopeRelations($model);
        foreach ($globalScopeRelations as $relation) {
            // TODO: $relation->addConstraints();
            // TODO: At least $relation->getQualifiedForeignKeyName()
            // $relation->setQuery($builder);
            $relatedModel = $relation->getRelated();
            $reverse      = ($relation instanceof BelongsTo);
            $key          = $relation->getForeignKeyName();
            $columnFrom   = ($reverse ? $key : 'id');
            $columnTo     = ($reverse ? 'id' : $key);
            $fqTableFrom  = "$model->table.$columnFrom";
            $fqTableTo    = "$relatedModel->table.$columnTo";
            $builder->join($relatedModel->table, $fqTableFrom, '=', $fqTableTo);
            
            // Chain all global_scope relations
            // For calling class
            $chainScopes  = self::chainScopes($relatedModel);
            foreach ($chainScopes as $chainScope) {
                // Inherit your Scope from GlobalChainScope to activate this chain
                $chainScope->apply($builder, $relatedModel);
            }
        }
    }
}
