<?php namespace AcornAssociated;

use Model as BaseModel;
use BackendAuth;
use \Backend\Models\User;
use \Backend\Models\UserGroup;
use ApplicationException;

// TODO: Split these traits out into their own PHP files for SPL
class Saving {
    public function __construct(Model $eventPart)
    {
        // TODO: What shall we do with this?
        // TODO: look at the Dispatcher::until() method
        //throw new ApplicationException($eventPart->name . " was here");
    }
}

class Model extends BaseModel
{
    use DeepReplicates;
    use DirtyWriteProtection;
    use ObjectLocking;
    use WebSocketInform;
    use PostGreSQLFieldTypeUtilities;

    protected $dispatchesEvents = [
        'saving' => Saving::class, // TODO: Not used yet. See Saving event above
    ];

    public function save(?array $options = [], $sessionKey = null)
    {
        // Object locking
        if (!isset($options['UNLOCK']) || $options['UNLOCK'] == TRUE) {
            $user = BackendAuth::user();
            $this->unlock($user); // Does not save(), may throw ObjectIsLocked()
        }

        // Dirty Writing checks in fill() include a passed original updated_at field
        // but we do not want to override default behavior
        // This would error on create new
        $this->updated_at = NULL;

        $result = parent::save($options, $sessionKey);

        if (!isset($options['WEBSOCKET']) || $options['WEBSOCKET'] == TRUE)
            $this->informClients($options);

        return $result;
    }
}
