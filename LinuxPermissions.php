<?php namespace AcornAssociated;

use BackendAuth;
use \Illuminate\Auth\Access\AuthorizationException;

trait LinuxPermissions
{
    public static $READ   = 1;
    public static $WRITE  = 2;
    public static $DELETE = 4;

    public static $USER   = 1;
    public static $GROUP  = 8;
    public static $OTHER  = 64;

    protected function can(int $accessType)
    {
        $user   = BackendAuth::user();
        $groups = $user->groups->keyBy('id');

        $isOwner = (property_exists($this, 'owner_user') && $user->id == $this->owner_user->id);
        $inGroup = ($this->owner_user_group && $groups->get($this->owner_user_group->id));
        $isSuperUser = $user->is_superuser;

        return $isSuperUser
            || ($isOwner && $this->permissions & $accessType * self::$USER)
            || ($inGroup && $this->permissions & $accessType * self::$GROUP)
            ||              $this->permissions & $accessType * self::$OTHER;
    }

    public function permissionsObject()
    {
        return (property_exists($this, 'permissionsObject') ? $this->permissionsObject : $this);
    }

    public function canRead()   { return $this->permissionsObject()->can(self::$READ); }
    public function canWrite()  { return $this->permissionsObject()->can(self::$WRITE); }
    public function canDelete() { return $this->permissionsObject()->can(self::$DELETE); }

    // TODO: SECURITY: Read security
    /*
    public function getAttributes()
    {
        $attributes = parent::getAttributes();

        if (!$this->canRead()) throw new AuthorizationException('Cannot read this object');

        return $attributes;
    }
    */

    // TODO: These are base Model methods so they are incompatible with a Model that also implements them
    // Move all this in to a base class and a Controller::$implement (like Winter does)?
    public function delete()
    {
        if (!$this->canDelete()) throw new AuthorizationException('Cannot delete this object');
        return parent::delete();
    }

    public function fill(array $attributes)
    {
        // This works on the original values, before fill()
        if ($this->attributes && !$this->canWrite()) throw new AuthorizationException('Cannot write this object');
        return parent::fill($attributes);
    }

    public function save(?array $options = [], $sessionKey = null)
    {
        // This works on the new values, because after fill()
        if (!$this->canWrite()) throw new AuthorizationException('Cannot write this object');
        return parent::save($options, $sessionKey);
    }
}
