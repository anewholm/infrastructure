<?php

namespace Acorn\Models;

use Acorn\Model;

/**
 * server Model
 */
class Server extends Model
{
    use \Winter\Storm\Database\Traits\Validation;

    /**
     * @var string The database table used by the model.
     */
    public $table = 'acorn_servers';

    /**
     * @var array Guarded fields
     */
    protected $guarded = [];

    /**
     * @var array Fillable fields
     */
    protected $fillable = [];

    /**
     * @var array Validation rules for attributes
     */
    public $rules = [];

    /**
     * @var array Attributes to be cast to native types
     */
    protected $casts = [];

    /**
     * @var array Attributes to be cast to JSON
     */
    protected $jsonable = [];

    /**
     * @var array Attributes to be appended to the API representation of the model (ex. toArray())
     */
    protected $appends = [];

    /**
     * @var array Attributes to be removed from the API representation of the model (ex. toArray())
     */
    protected $hidden = [];

    /**
     * @var array Attributes to be cast to Argon (Carbon) instances
     */
    public $timestamps = FALSE;
    protected $dates = [];

    /**
     * @var array Relations
     */
    public $hasOne = [];
    public $hasMany = [];
    public $hasOneThrough = [];
    public $hasManyThrough = [];
    public $belongsTo = [];
    public $belongsToMany = [];
    public $morphTo = [];
    public $morphOne = [];
    public $morphMany = [];
    public $attachOne = [];
    public $attachMany = [];

    public static function singleton(): Server
    {
        $hostname = gethostname();
        $server   = Server::where('hostname', $hostname)->first();
        if (!$server) $server = Server::create(['hostname' => $hostname]);
        return $server;
    }

    public function getNameAttribute(): string
    {
        $isLocal = ($this->replicated() ? '*' : '');
        return "$this->hostname$isLocal";
    }

    public function getReplicatedAttribute()
    {
        return (gethostname() != $this->hostname);
    }

    public function replicated()
    {
        return $this->replicated;
    }

    public function getReplicatedSourceAttribute()
    {
        return ($this->replicated() ? $this->hostname : '');
    }

    public function replicatedSource()
    {
        return $this->replicated_source;
    }

    public static function menuitemCount()
    {
        return self::count();
    }
}
