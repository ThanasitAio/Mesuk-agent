<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * เลขที่จดของมิเตอร์หลัก (hr_master_meter_readings ของ happyest - DB เดียวกัน) 1 แถว = 1 มิเตอร์หลัก x 1 เดือนที่จด
 * ล้อ happyest App\Models\MasterMeterReading - หน่วยคำนวณด้วย MeterReading::calculateUnits() ตัวเดียวกับมิเตอร์ย่อย
 *
 * created_by/updated_by อ้าง hr_admins.id (ฝั่งแอดมินเป็นคนเขียนคอลัมน์นี้) - ตัวแทนบันทึกจากแอปนี้ต้องไม่เขียน
 * hr_agents.id ลงไป ไม่งั้นหน้าแอดมินจะแสดงชื่อแอดมินผิดคน ดู MasterMeterReadingController::store()
 */
class HrMasterMeterReading extends Model
{
    protected $table = 'hr_master_meter_readings';

    protected $guarded = [];

    protected $casts = [
        'property_group_id' => 'integer',
        'reading_date'      => 'date',
        'meter_reset'       => 'boolean',
        'meter_changed'     => 'boolean',
        'units_used'        => 'decimal:2',
        'linked_properties' => 'array',
    ];

    /**
     * อสังหาที่มิเตอร์หลักตัวนี้ผูกอยู่ตอนบันทึก [{id, code, title}] ไว้เก็บลง linked_properties
     *
     * @return array<int, array{id: int, code: ?string, title: ?string}>
     */
    public static function snapshotProperties(HrMasterMeter $meter): array
    {
        return $meter->properties
            ->sortBy('property_code', SORT_NATURAL)
            ->map(fn ($p) => ['id' => (int) $p->id, 'code' => $p->property_code, 'title' => $p->title])
            ->values()
            ->all();
    }

    public function masterMeter()
    {
        return $this->belongsTo(HrMasterMeter::class, 'master_meter_id')->withTrashed();
    }

    public function creator()
    {
        return $this->belongsTo(HrAdmin::class, 'created_by')->withTrashed();
    }

    public function updater()
    {
        return $this->belongsTo(HrAdmin::class, 'updated_by')->withTrashed();
    }

    /** แก้ไขหลังบันทึกครั้งแรก - updateOrCreate ที่ค่าไม่เปลี่ยนไม่ save จึงไม่ขยับ updated_at */
    public function wasEdited(): bool
    {
        return $this->created_at && $this->updated_at && $this->updated_at->gt($this->created_at);
    }

    public function scopeForPeriod($query, int $year, int $month)
    {
        return $query->where('billing_year', $year)->where('billing_month', $month);
    }

    public function scopeForGroup($query, int $groupId)
    {
        return $query->where('property_group_id', $groupId);
    }
}
