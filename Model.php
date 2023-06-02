<?php namespace AcornAssociated;

use Model as BaseModel;
use BackendAuth;
use \Backend\Models\User;
use \Backend\Models\UserGroup;
use ApplicationException;

/*
class Saving {
    public function __construct(Model $eventPart)
    {
        // TODO: What shall we do with this?
        // TODO: look at the Dispatcher::until() method
        //throw new ApplicationException($eventPart->name . " was here");
    }
}
*/

class Model extends BaseModel
{
    use DeepReplicates;
    use DirtyWriteProtection;
    use ObjectLocking;
    use PostGreSQLFieldTypeUtilities;

    /*
    protected $dispatchesEvents = [
        'saving' => Saving::class, // TODO: Not used yet. See Saving event above
    ];
    */

    public function delete()
    {
        if ($user = BackendAuth::user())
            $this->unlock($user); // Does not save(), may throw ObjectIsLocked()

        return parent::delete();
    }

    public function save(?array $options = [], $sessionKey = null)
    {
        // Object locking
        if (!isset($options['UNLOCK']) || $options['UNLOCK'] == TRUE) {
            if ($user = BackendAuth::user())
                $this->unlock($user); // Does not save(), may throw ObjectIsLocked()
        }

        // Dirty Writing checks in fill() include a passed original updated_at field
        // but we do not want to override default behavior
        // This would error on create new
        $this->updated_at = NULL;

        return parent::save($options, $sessionKey);
    }
}
