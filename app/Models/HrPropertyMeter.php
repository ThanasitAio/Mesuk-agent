<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrPropertyMeter extends Model
{
    protected $table = 'hr_property_meters';

    protected $guarded = [];

    protected $casts = [
        'price_per_unit' => 'decimal:14',
        'is_active'      => 'boolean',
        'sort_order'     => 'integer',
    ];

    public function property()
    {
        return $this->belongsTo(HrProperty::class, 'property_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function typeLabels(): array
    {
        return [
            'water'    => 'น้ำ',
            'electric' => 'ไฟฟ้า',
        ];
    }

    public function getTypeLabelAttribute(): string
    {
        return self::typeLabels()[$this->meter_type] ?? $this->meter_type;
    }
}
