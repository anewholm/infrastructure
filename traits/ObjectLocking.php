<?php namespace AcornAssociated\Traits;

use BackendAuth;
use \Backend\Models\User;
use \Backend\Models\UserGroup;
use \AcornAssociated\Exception\ObjectIsLocked;

trait ObjectLocking
{
    // TODO: Write supportsLocking like softDelete for updates
    // TODO: We do not want to update updated_at when updating locks. It will trigger an "updated by someone else"
    public function lock(User $user, ?bool $save = TRUE, ?bool $throw = TRUE, ?bool $superuserOverride = FALSE)
    {
        $user = BackendAuth::user();
        if (!is_null($this->locked_by_user_id)) {
            if ($this->locked_by_user_id != $user->id) {
                if ($superuserOverride && $user->is_superuser) {
                    $this->locked_by_user_id = $user->id;
                    if ($save) $this->save(array('UNLOCK' => FALSE));
                } elseif ($throw) {
                    $className = preg_replace('#.*\\\#', '', get_class($this));
                    $user      = User::find($this->locked_by_user_id);
                    throw new ObjectIsLocked($className . trans(' is already locked for editing by ') . $user->first_name);
                }
            }
        } else {
            $this->locked_by_user_id = $user->id;
            if ($save) $this->save(array('UNLOCK' => FALSE));
        }

        return ($this->locked_by_user_id == $user->id);
    }

    public function unlock(User $user, ?bool $save = FALSE, ?bool $throw = TRUE)
    {
        if (!is_null($this->locked_by_user_id)) {
            if ($user->id != $this->locked_by_user_id) {
                if ($throw) {
                    $className = preg_replace('#.*\\\#', '', get_class($this));
                    $user      = User::find($this->locked_by_user_id);
                    throw new ObjectIsLocked($className . trans(' is already locked for editing by ') . $user->first_name);
                }
            } else {
                $this->locked_by_user_id = NULL;
                if ($save) $this->save(array('UNLOCK' => FALSE));
            }
        }

        return is_null($this->locked_by_user_id);
    }

    public function isLocked()
    {
        $user = BackendAuth::user();
        return (!is_null($this->locked_by_user_id) && $this->locked_by_user_id != $user->id);
    }
}

