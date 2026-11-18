<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * แบบค่าคอมของทรัพย์ (งาน #163 รอบตรงกระดาษ, ผู้ใช้ 2026-11-18): ฝ่ายบัญชียืนยันที่หลังบ้าน happyest
 * /admin/agent-commissions/properties ว่าทรัพย์นี้คิดค่าคอมแบบไหน
 *  - managed = โครงการบริหาร (ผู้บริหารโครงการ 2.0% + ผู้หาผู้เช่า 2.0% ทุกเดือน)
 *  - company = โครงการบริษัท (ผู้หาผู้เช่าได้ครั้งเดียว 1-2 เดือน หรือ 5% ต่อเดือนที่เช่าถ้าสัญญาไม่ถึง 1 ปี)
 *  - consign = ฝากทรัพย์ (ผู้นำทรัพย์ 2.5% + ผู้หาผู้เช่า 2.5% ทุกเดือน)
 *  - none    = ไม่คิดค่าคอม
 * ไม่มีแถว = ระบบเดาให้ (AgentCommissionService::guessScheme) · มีแถว = ฝ่ายบัญชียืนยันแล้ว
 * lister_agent_code = ผู้นำทรัพย์ที่ฝ่ายบัญชีเลือก (null = ตัวแทนผู้เพิ่มรายการ hr_properties.agent_code)
 * อ้าง hr_properties แบบ soft reference (ไม่มี FK) เหมือนตาราง ag_commission_* อื่น
 * Idempotent: รันซ้ำได้ (กด Migration ที่หน้า /deploy ของระบบจริง)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ag_commission_property_settings')) {
            return;
        }
        Schema::create('ag_commission_property_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('property_id')->unique();   // hr_properties.id
            $table->string('scheme', 20);                           // managed | company | consign | none
            $table->string('lister_agent_code', 20)->nullable();    // ผู้นำทรัพย์ (null = ตัวแทนผู้เพิ่มรายการ)
            $table->string('note', 500)->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();   // hr_admins.id
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ag_commission_property_settings');
    }
};
