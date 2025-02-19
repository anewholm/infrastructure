<?php namespace Acorn\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;

use Backend\Models\User;

// TODO: When queued (ShouldBroadcast) it doesn't work
class UserNavigation implements ShouldBroadcast, ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $userID; // For transport
    public $url;

    public function __construct(User $_user, string $_url)
    {
        $this->userID = $_user->id;
        $this->url    = $_url;
    }

    public static function fromArray(array $data): UserNavigation
    {
        $user = User::findOrFail(intval($data['userID']));
        return new self($user, $data['url']);
    }

    public function isFor(User $user)
    {
        return ($user->id == $this->userID);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn()
    {
        return [
            new Channel('acorn')
        ];
    }

    public function broadcastAs()
    {
        return 'user.navigation';
    }
}
