<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrCompany extends Model
{
    protected $table = 'hr_company';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public static function getActive(): ?self
    {
        return self::where('is_active', true)->first();
    }
}
