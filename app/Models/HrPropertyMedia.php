<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrPropertyMedia extends Model
{
    protected $table = 'hr_property_media';

    protected $guarded = [];

    protected $casts = [
        'is_cover'   => 'boolean',
        'sort_order' => 'integer',
    ];
}
