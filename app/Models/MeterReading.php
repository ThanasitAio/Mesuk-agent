<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MeterReading extends Model
{
    use SoftDeletes;

    protected $table = 'ag_meter_readings';

    protected $guarded = [];

    protected $casts = [
        'reading_date'   => 'date',
        'meter_reset'    => 'boolean',
        'meter_changed'  => 'boolean',
        'units_used'     => 'decimal:2',
        'price_per_unit' => 'decimal:14',
        'amount'         => 'decimal:2',
        'confirmed_at'   => 'datetime',
    ];

    public function meter()
    {
        // Null-safe on purpose: happyest can hard-delete hr_property_meters rows
        // independently of this app (see migration comment). Historical rows stay
        // meaningful via the denormalized meter_type/property_id columns even then.
        return $this->belongsTo(HrPropertyMeter::class, 'property_meter_id');
    }

    public function property()
    {
        return $this->belongsTo(HrProperty::class, 'property_id');
    }

    public function creator()
    {
        return $this->belongsTo(HrAgent::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(HrAgent::class, 'updated_by');
    }

    public function confirmer()
    {
        return $this->belongsTo(HrAgent::class, 'confirmed_by');
    }

    public function scopeForPeriod($query, int $year, int $month)
    {
        return $query->where('billing_year', $year)->where('billing_month', $month);
    }

    /**
     * Filters by the rent invoice period a reading is billed together with
     * (rent_year/rent_month) - independent of billing_year/billing_month, the
     * calendar month the meter was actually read.
     */
    public function scopeForRentPeriod($query, int $year, int $month)
    {
        return $query->where('rent_year', $year)->where('rent_month', $month);
    }

    public function scopeForProperty($query, int $propertyId)
    {
        return $query->where('property_id', $propertyId);
    }

    public function getStatusLabelAttribute(): string
    {
        return [
            'draft'     => 'ฉบับร่าง',
            'confirmed' => 'ยืนยันแล้ว',
        ][$this->status] ?? $this->status;
    }

    /**
     * Single source of truth for unit calculation - the legacy system had 3 different,
     * mutually inconsistent formulas across the model and two export files. Everything
     * (store(), any future report/export) must call this instead of re-deriving units.
     *
     * @param  array{previous_reading: ?int, current_reading: int, meter_reset: bool, meter_changed: bool,
     *               old_meter_final_reading: ?int, new_meter_start_reading: ?int, meter_max_value: ?int}  $params
     * @return array{units_used: float, max_value_used: ?int}
     */
    public static function calculateUnits(array $params): array
    {
        $previous     = $params['previous_reading'] !== null ? (int) $params['previous_reading'] : null;
        $current      = (int) $params['current_reading'];
        $meterReset   = (bool) ($params['meter_reset'] ?? false);
        $meterChanged = (bool) ($params['meter_changed'] ?? false);
        $oldFinal     = $params['old_meter_final_reading'] ?? null;
        $newStart     = $params['new_meter_start_reading'] ?? null;
        $maxOverride  = $params['meter_max_value'] ?? null;

        $maxValueUsed = null;

        if ($meterChanged) {
            // Old meter ran from previous_reading up to old_meter_final_reading, then the
            // new meter picked up from new_meter_start_reading up to current_reading.
            $units = ((int) $oldFinal - ($previous ?? 0)) + ($current - (int) $newStart);
        } elseif ($meterReset) {
            $maxValueUsed = $maxOverride ?? self::heuristicMaxValue($previous ?? 0);
            $percentFull  = $maxValueUsed > 0 ? (($previous ?? 0) / $maxValueUsed) * 100 : 0;
            $units        = $percentFull > 90
                ? ($maxValueUsed - ($previous ?? 0)) + $current + 1
                : $current;
        } elseif ($previous === null) {
            // First-ever reading for this meter - nothing to diff against.
            $units = $current;
        } else {
            $units = $current - $previous;
        }

        return [
            'units_used'    => (float) max($units, 0),
            'max_value_used' => $maxValueUsed,
        ];
    }

    /**
     * Guess the meter's rollover point from the previous reading's digit count,
     * used only when meter_max_value isn't explicitly set. Same thresholds the
     * legacy system used, kept only as a fallback now that an explicit override exists.
     */
    private static function heuristicMaxValue(int $previous): int
    {
        if ($previous >= 10000) {
            return 99999;
        }
        if ($previous >= 1000) {
            return 9999;
        }
        if ($previous >= 100) {
            return 999;
        }

        return 9999;
    }
}
