<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ใบค่าคอมมิชชั่น (งาน #163) - ฝ่ายบัญชีเปิดใบ/บันทึกโอนที่หลังบ้าน happyest (/admin/agent-commissions)
 * issued = เปิดใบแล้ว รอโอน · paid = โอนแล้ว · cancelled = ยกเลิกใบ (ไม่นับ เก็บเป็นประวัติ)
 * รายการในใบที่ยังไม่ยกเลิก (ag_commission_items.payout_id) ถูกตรึงไว้ ไม่เปลี่ยนตามข้อมูลการชำระภายหลัง
 */
class AgCommissionPayout extends Model
{
    protected $table = 'ag_commission_payouts';

    protected $guarded = [];

    public const STATUS_ISSUED    = 'issued';
    public const STATUS_PAID      = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    protected $casts = [
        'gross'        => 'decimal:2',
        'wht_rate'     => 'decimal:2',
        'wht_amount'   => 'decimal:2',
        'net'          => 'decimal:2',
        'issued_at'    => 'datetime',
        'paid_on'      => 'date',
        'cancelled_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(AgCommissionItem::class, 'payout_id');
    }

    public function scopeLive($query)
    {
        return $query->where('status', '<>', self::STATUS_CANCELLED);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }
}
