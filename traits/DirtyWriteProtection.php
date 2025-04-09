<?php namespace Acorn\Traits;

use \Acorn\Exception\DirtyWrite;

trait DirtyWriteProtection
{
    public function checkDirtyWrite(array $attributes, ?bool $throw = TRUE)
    {
        $isDirty = FALSE;

        if (isset($attributes['updated_at']) && !is_null($this->updated_at)) {
            $updatedAt = $attributes['updated_at'];
            if ($updatedAt != $this->updated_at) {
                $class = preg_replace('#.*\\\#', '', get_class($this));
                if ($throw) throw new DirtyWrite($class . trans(' was updated by someone else.'));
                $isDirty = TRUE;
            }
        }

        return $isDirty;
    }

    public function fill(array $attributes)
    {
        $this->checkDirtyWrite($attributes); // throw

        return parent::fill($attributes);
    }
}

