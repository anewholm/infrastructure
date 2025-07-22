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
    // TODO: Re-organise this class to just recurse once per model
    // collecting builder joins on the way
    // and only applying them back at the root call if necessary
    public const IS_THIS = TRUE;

    // ------------------------------------ Direct situation on this model
    public static function globalScopeRelationsOn(Model $model): array
    {
        // These can branch in to a tree of multiple global scopes
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

    protected static function ourGlobalChainScopesOn(Model $model): array
    {
        // Get _our_ scopes on this $model
        // Not recursive at all
        $chainScopes = array();
        if ($allChainScopes = $model->getGlobalScopes()) {
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

    public static function getSettingFor(Model $model): string|NULL
    {
        $class       = get_class($model);
        $settingName = "$class::globalScope";
        return Session::get($settingName);
    }

    public static function hasSessionFor(Model $model, bool $isThis = FALSE): bool
    {
        // Usually called with an apply() override:
        // public function shouldApply(Builder $builder, Model $model): bool {
        //     return self::hasSessionFor($builder, $model);
        // }
        // Returing TRUE causes the scope chain to be recursively applied
        $setting = self::getSettingFor($model);
        return ($isThis
            ? ($setting && $model->id == $setting)
            : (bool) $setting
        );
    }

    // --------------------------------------------- Recursive
    // Searching down global-scope relations to the end
    public static function isEndSelectedFrom(Model $model): bool
    {
        $isSelected = FALSE;
        foreach (self::endGlobalScopeClasses($model) as $scopeModel) {
            $setting    = self::getSettingFor($scopeModel);
            $isSelected = ($setting && $scopeModel->id == $setting);
            if ($isSelected) break;
        }

        return $isSelected;
    }

    public static function endGlobalScopeClasses(Model $model, array $fromEndChainModels = NULL): array
    {
        // Recursive
        // Get the (existing) Models on the ends of the global-scope relation chain(s)
        // Includes this $model parameter, if a $globalScope
        $endChainModels       = array();
        if (property_exists($model, 'globalScope') && $model::$globalScope)
            $endChainModels[get_class($model)] = $model;
        
        $globalScopeRelations = self::globalScopeRelationsOn($model);
        foreach ($globalScopeRelations as $name => $relation) {
            $relatedModel = NULL;
            if ($model->exists) $relatedModel = $model->{$name}()->first();
            if (!$relatedModel) $relatedModel = $relation->getRelated();
            $relatedClass   = get_class($relatedModel);
            if (isset($fromEndChainModels[$relatedClass])) {
                $chain = implode(' => ', array_keys($fromEndChainModels));
                throw new Exception("Infinite global-scope recursion on $chain");
            }
            $endChainModels = array_merge($endChainModels, self::endGlobalScopeClasses($relatedModel, $endChainModels));
        }

        return $endChainModels;
    }

    public function shouldApply(Model $model, bool $isThis = FALSE): bool
    {
        // Recursive
        // Works out if the Final Scope(s) have a setting or not
        // override shouldApply() on an end GlobalScope to return the setting
        // usually with hasSessionFor()
        $shouldApply = FALSE;
        $globalScopeRelations = self::globalScopeRelationsOn($model);
        foreach ($globalScopeRelations as $name => $relation) {
            // Chain all global_scope relations
            // For calling class
            // We traverse the existing models if possible, for $isThis
            $relatedModel = NULL;
            if ($model->exists) $relatedModel = $model->{$name}()->first();
            if (!$relatedModel) $relatedModel = $relation->getRelated();

            // TODO: It's possible that it has no model set, what to do?
            if ($relatedModel) {
                $chainScopes  = self::ourGlobalChainScopesOn($relatedModel);
                foreach ($chainScopes as $chainScope) {
                    // Inherit your Scope from GlobalChainScope to activate this chain
                    $shouldApply = $chainScope->shouldApply($relatedModel, $isThis);
                    // TODO: This returns the first positive scope setting only, should return...?
                    if ($shouldApply) break;
                }
            }
            if ($shouldApply) break;
        }
        
        return $shouldApply;
    }

    public static function applySession(Builder $builder, Model $model): bool 
    {
        // From this model only
        // Not recursive at all
        // Usually called with an apply() override:
        // public function apply(Builder $builder, Model $model): bool {
        //     return self::applySession($builder, $model);
        // }
        // Returing TRUE causes the scope chain to be recursively applied
        $setting = self::getSettingFor($model);

        if ($setting) {
            if (isset($model::$globalScope::$scopingFunction)) {
                $scopingFunction = $model::$globalScope::$scopingFunction;
                $settingEscaped  = str_replace("'", "\\'", $setting);
                $builder->whereRaw("$scopingFunction($model->table.id, '$settingEscaped')");
            } else {
                $builder->where("$model->table.id", '=', $setting);
            }

            // TODO: Allow NULLs on the first join to show Models without an explicit setting
            /*
            $query = $builder->getQuery();
            if (isset($query->joins[0])) {
                // Joins:
                // type     = "Column"
                // first    = "acorn_exam_calculations.academic_year_id"
                // operator = "="
                // second   = "acorn_university_academic_years.id"
                // boolean  = "and"
                $firstJoin = $query->joins[0];
                if (isset($firstJoin->wheres[0])) {
                    $firstWhere = $firstJoin->wheres[0];
                    $builder->orWhere($firstWhere['first'], '=', NULL);
                }
            }
            */
        }

        return (bool) $setting;
    }

    public function apply(Builder $builder, Model $model): void {
        // Follow global_scope => TRUE relation(s)
        // This is overridden by
        //   YearScope::apply(...)
        if ($this->shouldApply($model)) 
            $this->applyRecursive($builder, $model);
    }

    public function applyRecursive(Builder $builder, Model $model): void {
        $globalScopeRelations = self::globalScopeRelationsOn($model);
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
            $chainScopes  = self::ourGlobalChainScopesOn($relatedModel);
            foreach ($chainScopes as $chainScope) {
                // Inherit your Scope from GlobalChainScope to activate this chain
                $chainScope->apply($builder, $relatedModel);
            }
        }
    }
}
