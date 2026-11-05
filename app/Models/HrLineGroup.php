<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ไลน์ของผู้เช่าที่รับเอกสารจากบัญชีบริษัท (hr_line_groups ของ happyest: แชทส่วนตัวตั้งแต่งาน #90, กลุ่มเดิมของงาน #53) - อ่านอย่างเดียว ไว้แสดงชื่อ
 */
class HrLineGroup extends Model
{
    protected $table = 'hr_line_groups';

    protected $guarded = [];
}
