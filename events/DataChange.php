<?php namespace AcornAssociated\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;

use AcornAssociated\Model;
use \Exception;

// TODO: When queued (ShouldBroadcast) it doesn't work
class DataChange implements ShouldBroadcast, ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $eventClass;

    public $TG_NAME; // Trigger name
    public $TG_OP;   // INSERT
    public $TG_TABLE_SCHEMA;
    public $TG_TABLE_NAME;
    public $ID;

    public $tableName;
    public $modelClass;

    public function __construct(string $TG_NAME, string $TG_OP, string $TG_TABLE_SCHEMA, string $TG_TABLE_NAME, string $ID)
    {
        $this->TG_NAME = $TG_NAME;
        $this->TG_OP   = $TG_OP;
        $this->TG_TABLE_SCHEMA = $TG_TABLE_SCHEMA;
        $this->TG_TABLE_NAME   = $TG_TABLE_NAME;
        $this->ID      = $ID; // UUID

        $this->eventClass = get_class($this);
        $this->tableName  = "$this->TG_TABLE_SCHEMA.$this->TG_TABLE_NAME";
        if ($model = Model::newModelFromTableName($this->tableName)) {
            // NOTE: The DB transaction is not complete at this point
            // So we cannot load the new model yet
            $this->modelClass = get_class($model);
        } else {
            throw new Exception("$this->tableName did not resolve to a Model");
        }
    }

    public function id()
    {
        return $this->ID;
    }

    public function operation()
    {
        return strtolower($this->TG_OP) . 'ed';
    }

    public function model()
    {
        return new $this->modelClass;
    }

    public static function fromArray(array $data): DataChange
    {
        return new self(
            $data['TG_NAME'],
            $data['TG_OP'],
            $data['TG_TABLE_SCHEMA'],
            $data['TG_TABLE_NAME'],
            $data['ID']
        );
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn()
    {
        return [
            new Channel('acornassociated')
        ];
    }

    public function broadcastAs()
    {
        return 'data.change';
    }
}
