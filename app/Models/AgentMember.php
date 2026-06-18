<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AgentMember extends Model
{
    use HasFactory;

    protected $table = 'agent_members';

    protected $fillable = [
        'member_code',
        'name',
        'email',
        'phone',
        'password',
        'status',
        'address',
        'province',
        'zipcode',
    ];

    protected $hidden = ['password'];
}
