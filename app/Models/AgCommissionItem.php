<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * รายการค่าคอมมิชชั่นหนึ่งบรรทัด (งาน #163) - สร้าง/อัปเดตโดย App\Helpers\CommissionLedger::sync()
 *
 * kind:
 *  - management = ค่าบริหารโครงการ: % ของค่าเช่าหนึ่งเดือนของทรัพย์ที่ดูแล (หนึ่งรายการต่อ hr_payment_records ค่าเช่า)
 *  - finder     = ค่าหาผู้เช่า: ค่าเช่า x จำนวนเดือนตามอายุสัญญา (หนึ่งรายการต่อการจอง)
 *  - manual     = ฝ่ายบัญชีเพิ่มเอง (บวก/หัก)
 * state: waiting = ยังไม่ถึงรอบจ่าย (ผู้เช่ายังไม่ชำระ/ยังไม่เข้าใช้งาน), ready = พร้อมจ่ายในงวดถัดไป
 * payout_id != null = อยู่ในงวดที่โอนแล้ว ระบบไม่แก้ยอดอีก
 */
class AgCommissionItem extends Model
{
    protected $table = 'ag_commission_items';

    protected $guarded = [];

    protected $casts = [
        'base_amount'     => 'decimal:2',
        'factor'          => 'decimal:4',
        'calc_amount'     => 'decimal:2',
        'override_amount' => 'decimal:2',
        'is_excluded'     => 'boolean',
        'due_date'        => 'date',
        'ready_at'        => 'datetime',
        'edited_at'       => 'datetime',
    ];

    public const KIND_MANAGEMENT = 'management';
    public const KIND_FINDER     = 'finder';
    public const KIND_MANUAL     = 'manual';

    public function payout()
    {
        return $this->belongsTo(AgCommissionPayout::class, 'payout_id');
    }

    public function editor()
    {
        return $this->belongsTo(HrAdmin::class, 'edited_by');
    }

    /** ยอดที่ใช้จริง: ยอดที่ฝ่ายบัญชีแก้ ถ้าไม่มีใช้ยอดที่ระบบคำนวณ */
    public function amount(): float
    {
        return (float) ($this->override_amount ?? $this->calc_amount);
    }

    /** นับเข้ายอดรวมหรือไม่ (รายการที่ฝ่ายบัญชีตั้ง "ไม่จ่าย" ไม่นับ) */
    public function counts(): bool
    {
        return ! $this->is_excluded;
    }

    public function isFrozen(): bool
    {
        return $this->payout_id !== null;
    }

    public function isEdited(): bool
    {
        return $this->override_amount !== null || $this->is_excluded;
    }
}
