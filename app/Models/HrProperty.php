<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrProperty extends Model
{
    protected $table = 'hr_properties';

    protected $guarded = [];

    public function activeBooking()
    {
        return $this->hasOne(HrBooking::class, 'property_id')
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['cancelled', 'rejected', 'completed', 'checked_out'])
            ->latest('id');
    }

    public function owner()
    {
        return $this->belongsTo(HrCustomer::class, 'customer_id');
    }
}
