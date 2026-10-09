<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * อ่านอย่างเดียว - กลุ่มอสังหาของ happyest (hr_property_group) ใช้แสดงชื่อกลุ่มในหน้าบันทึกมิเตอร์หลัก
 */
class HrPropertyGroup extends Model
{
    use SoftDeletes;

    protected $table = 'hr_property_group';

    protected $primaryKey = 'property_group_id';

    protected $guarded = [];

    public function properties()
    {
        return $this->hasMany(HrProperty::class, 'property_group_id', 'property_group_id')
            ->whereNull('deleted_at');
    }
}
