<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ใบแจ้งหนี้ที่ happyest ส่งถึงไลน์ของผู้เช่า (hr_line_invoice_sends, งาน #53/#90) - อ่านอย่างเดียว
 * 1 แถว = ใบ 1 ใบ ต่อกลุ่ม 1 กลุ่ม · status sent = ไลน์รับการ์ดแล้ว · ตารางเป็นของ happyest (happyest .claude/rules/line-invoice.md)
 */
class HrLineInvoiceSend extends Model
{
    protected $table = 'hr_line_invoice_sends';

    protected $guarded = [];

    protected $casts = [
        'sent_at' => 'datetime',
        'last_opened_at' => 'datetime',
        'open_count' => 'integer',
    ];

    public function group()
    {
        return $this->belongsTo(HrLineGroup::class, 'line_group_id');
    }
}
