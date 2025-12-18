<?php namespace Acorn\Traits;

use Backend\Facades\BackendAuth;
use Str;
use Acorn\Model;
use Exception;

trait Leaf
{
    // Star schema centre => leaf services
    public function getLeafTypeAttribute(?bool $throwIfNull = FALSE)
    {
        return $this->getLeafTypeModel($throwIfNull)?->unqualifiedClassName();
    }

    public function getLeafTableAttribute($value): string|NULL
    {
        $ret = NULL;
        if ($key = $this->getLeafTableTranslationKey($value)) {
            $ret = trans($key);
        } else {
            // Maybe not translated, but we will offer
            $ret = trans($this->getLeafTableCacheClass());
        }
        return $ret;
    }

    public function getLeafTableTranslationKey(string|NULL $value = NULL): string|NULL
    {
        $key = NULL;
        
        if (is_null($value) && isset($this->attributes['leaf_table']))
            $value  = $this->attributes['leaf_table'];
        
        if ($value) {
            $tableParts = explode('_', $value);
            if (isset($tableParts[2])) {
                $modelParts = array_slice($tableParts, 2);
                $localKey   = strtolower(Str::singular(implode('', $modelParts)));
                $key        = "$tableParts[0].$tableParts[1]::lang.models.$localKey.label";
            }
        }

        return $key;
    }

    public function getLeafTableCacheClass(bool $fqn = FALSE): string|NULL
    {
        $class = NULL;
        if (isset($this->attributes['leaf_table'])) {
            // acorn_university_schools 
            //   => Schools 
            //   => Acorn\University\Models\School
            $leafTable      = $this->attributes['leaf_table'];
            $leafTableParts = explode('_', $leafTable);
            array_shift($leafTableParts); // acorn
            array_shift($leafTableParts); // university
            $leafTableName = implode(' ', $leafTableParts); // schools
            $class         = Str::singular($leafTableName);
            $class         = Str::title($class);
            if ($fqn) {
                // Swap in last name for this FQN
                $class        = str_replace(' ', '', $class);
                $thisFQNParts = explode('\\', get_class($this));
                array_pop($thisFQNParts);
                array_push($thisFQNParts, $class);
                $class  = implode('\\', $thisFQNParts);
                if (!class_exists($class)) $class = NULL;
            }
        }
        return $class;
    }

    public function getLeafTableCacheModel(): Model|NULL
    {
        $leafObject = NULL;
        if ($leafModelFQN = $this->getLeafTableCacheClass(TRUE)) {
            // Check hasOne relations for this leaf model
            $relations = array_merge($this->hasOneThrough, $this->hasOne);
            foreach ($relations as $name => &$definition) {
                if (is_array($definition) && isset($definition[0]) && $definition[0] == $leafModelFQN) {
                    $this->load($name);
                    if ($leafObject = $this->$name) break;
                }
            }
        }
        return $leafObject;
    }

    public function getLeafTypeModel(?bool $throwIfNull = FALSE, bool $withoutGlobalScopes = FALSE)
    {
        // For base tables that have multiple possible leaf detail tables in a star schema
        // we search the hasOne relation to determine which leaf table has the 1-1
        // Check for and use the leaf_table cache column
        $leafObject = $this->getLeafTableCacheModel();
        
        if (!$leafObject) {
            $relations  = array_merge($this->hasOneThrough, $this->hasOne);
            foreach ($relations as $name => $definition) {
                if (is_array($definition) && isset($definition['leaf']) && $definition['leaf']) {
                    $leafObject = ($withoutGlobalScopes 
                        ? $this->$name()->withoutGlobalScopes()->first()
                        : $this->$name
                    ); 
                    if ($leafObject) break;
                }
                
                // Need to check the reverse belongsTo relation(s), 
                // because that will be the leaf relation
                if (isset($definition[0])) {
                    $relationTo = new $definition[0];
                    if ($relationTo->belongsTo) {
                        foreach ($relationTo->belongsTo as $backName => $backDefinition) {
                            if (isset($backDefinition[0])
                                && $backDefinition[0] == get_class($this) 
                                && isset($backDefinition['leaf']) 
                                && $backDefinition['leaf']
                            ) {
                                $leafObject = ($withoutGlobalScopes 
                                    ? $this->$name()->withoutGlobalScopes()->first()
                                    : $this->$name
                                ); 
                                if ($leafObject) break;
                            }
                        }
                    }
                }
                if ($leafObject) break;
            }
        }

        if ($throwIfNull && !$leafObject) {
            $className = get_class($this);
            throw new Exception("Leaf $className not found for id($this->id)");
        }
        
        return $leafObject;
    }
}