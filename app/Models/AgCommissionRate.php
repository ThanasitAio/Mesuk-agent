<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * อัตราค่าบริหารโครงการ (% ของค่าเช่า) ที่ฝ่ายบัญชีตั้ง (งาน #163)
 * property_id = 0 คืออัตราของคนนี้ทุกทรัพย์ ไม่มีแถว = ใช้ CommissionLedger::DEFAULT_RATE
 */
class AgCommissionRate extends Model
{
    protected $table = 'ag_commission_rates';

    protected $guarded = [];

    protected $casts = [
        'rate' => 'decimal:3',
    ];
}
