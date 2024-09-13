<?php namespace Acorn\Traits;

use BackendAuth;
use \Backend\Models\User;
use \Backend\Models\UserGroup;
use \Acorn\Exception\ObjectIsLocked;

trait ObjectLocking
{
    // TODO: Write supportsLocking like softDelete for updates
    // TODO: We do not want to update updated_at when updating locks. It will trigger an "updated by someone else"
    public function lock(User $user, ?bool $save = TRUE, ?bool $throw = TRUE, ?bool $superuserOverride = FALSE)
    {
        $user = BackendAuth::user();
        if (!is_null($this->locked_by)) {
            if ($this->locked_by != $user->id) {
                if ($superuserOverride && $user->is_superuser) {
                    $this->locked_by = $user->id;
                    if ($save) $this->save(array('UNLOCK' => FALSE));
                } elseif ($throw) {
                    $className = preg_replace('#.*\\\#', '', get_class($this));
                    $user      = User::find($this->locked_by);
                    throw new ObjectIsLocked($className . trans(' is already locked for editing by ') . $user->first_name);
                }
            }
        } else {
            $this->locked_by = $user->id;
            if ($save) $this->save(array('UNLOCK' => FALSE));
        }

        return ($this->locked_by == $user->id);
    }

    public function unlock(User $user, ?bool $save = FALSE, ?bool $throw = TRUE)
    {
        if (!is_null($this->locked_by)) {
            if ($user->id != $this->locked_by) {
                if ($throw) {
                    $className = preg_replace('#.*\\\#', '', get_class($this));
                    $user      = User::find($this->locked_by);
                    throw new ObjectIsLocked($className . trans(' is already locked for editing by ') . $user->first_name);
                }
            } else {
                $this->locked_by = NULL;
                if ($save) $this->save(array('UNLOCK' => FALSE));
            }
        }

        return is_null($this->locked_by);
    }

    public function isLocked()
    {
        $user = BackendAuth::user();
        return (!is_null($this->locked_by) && $this->locked_by != $user->id);
    }
}

