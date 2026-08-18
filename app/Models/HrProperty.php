<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrProperty extends Model
{
    protected $table = 'hr_properties';

    protected $guarded = [];

    public function activeBooking()
    {
        return $this->hasOne(HrBooking::class, 'property_id')
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['cancelled', 'rejected', 'completed', 'checked_out'])
            ->latest('id');
    }

    /**
     * ทุกการจองที่ยัง active อยู่ (ไม่ใช่แค่รายการล่าสุด) — ใช้ตอนมีการต่อสัญญาที่ทำให้เกิด
     * booking ซ้อนกัน 2 รายการ (สัญญาเดิมยังไม่ปิด + สัญญาใหม่เปิดแล้ว) เพื่อไม่ให้ตัวใดตัวหนึ่งถูกซ่อน
     */
    public function activeBookings()
    {
        return $this->hasMany(HrBooking::class, 'property_id')
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['cancelled', 'rejected', 'completed', 'checked_out']);
    }

    public function owner()
    {
        return $this->belongsTo(HrCustomer::class, 'customer_id');
    }

    public function manager()
    {
        return $this->belongsTo(HrAgent::class, 'manager_agent_code', 'agent_code');
    }

    /**
     * ตัวแทนที่ทำการฝากทรัพย์ (สร้างรายการนี้)
     */
    public function creatorAgent()
    {
        return $this->belongsTo(HrAgent::class, 'agent_code', 'agent_code');
    }

    public function propertyStatus()
    {
        return $this->belongsTo(HrPropertyStatus::class, 'property_status_id');
    }

    /**
     * รูปภาพหลักของทรัพย์ (cover ก่อน ถ้าไม่มีให้ใช้รูปแรกตาม sort_order)
     */
    public function primaryImageMedia()
    {
        return $this->hasOne(HrPropertyMedia::class, 'property_id')
            ->where('media_type', 'image')
            ->orderByDesc('is_cover')
            ->orderBy('sort_order');
    }

    /**
     * รูปภาพทั้งหมดของทรัพย์
     */
    public function imageMedia()
    {
        return $this->hasMany(HrPropertyMedia::class, 'property_id')
            ->where('media_type', 'image')
            ->orderByDesc('is_cover')
            ->orderBy('sort_order');
    }

    public function meters()
    {
        return $this->hasMany(HrPropertyMeter::class, 'property_id')->orderBy('sort_order');
    }

    public function activeMeters()
    {
        return $this->meters()->where('is_active', true);
    }
}
