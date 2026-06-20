<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrAgent extends Model
{
    protected $table = 'hr_agents';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'agent_code',
        'pass_decode',
        'prefix',
        'name',
        'birthday',
        'gender',
        'phone',
        'email',
        'address',
        'line_id',
        'facebook',
        'bank_account_name',
        'bank_name',
        'bank_branch',
        'bank_account_no',
        'avatar',
    ];

    protected $hidden = ['pass_decode'];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
