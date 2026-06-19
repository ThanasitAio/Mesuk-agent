<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentLog extends Model
{
    protected $table = 'ag_logs';

    public $timestamps = false;

    protected $fillable = [
        'user_type',
        'user_id',
        'module',
        'action',
        'description',
        'url',
        'method',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
