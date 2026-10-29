<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * กลุ่มไลน์ของผู้เช่าที่บอทของบริษัทอยู่ (hr_line_groups ของ happyest, งาน #53) - อ่านอย่างเดียว ไว้แสดงชื่อกลุ่ม
 */
class HrLineGroup extends Model
{
    protected $table = 'hr_line_groups';

    protected $guarded = [];
}
