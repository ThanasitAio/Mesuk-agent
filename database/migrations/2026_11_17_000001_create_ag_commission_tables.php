<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ค่าคอมมิชชั่น (งาน #163): รายการค่าคอมทีละบรรทัด + งวดที่โอนแล้ว + อัตราค่าบริหารโครงการรายคน/รายทรัพย์
 * อ้างถึงตาราง hr_* ของ happyest แบบ soft reference (ไม่มี FK) เหมือน ag_meter_readings - ฝั่ง happyest
 * ลบ/ซ่อนรายการชำระได้ตามปกติโดยไม่ชนกับตารางนี้
 * Idempotent: รันซ้ำได้ (กด Migration ที่หน้า /deploy ของระบบจริง)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ag_commission_payouts')) {
            // ใบค่าคอม (เปิดใบที่หลังบ้าน happyest /admin/agent-commissions): issued = เปิดใบแล้ว รอโอน (ตรึงรายการ) ->
            // paid = โอนแล้ว · cancelled = ยกเลิกใบ (เก็บไว้เป็นประวัติ รายการกลับไปคำนวณสด) - ใบที่ไม่ยกเลิกมีได้ 1 ใบต่อคนต่องวด
            Schema::create('ag_commission_payouts', function (Blueprint $table) {
                $table->id();
                $table->string('doc_no', 30)->nullable()->unique();  // CM6911-0001
                $table->string('agent_code', 20);
                $table->char('month', 7);                       // งวด YYYY-MM
                $table->string('status', 20)->default('issued'); // issued | paid | cancelled
                $table->string('payee_name', 255)->nullable();   // ชื่อผู้รับ ณ วันที่เปิดใบ
                $table->string('bank_name', 100)->nullable();
                $table->string('bank_account_no', 50)->nullable();
                $table->string('bank_account_name', 255)->nullable();
                $table->decimal('gross', 14, 2)->default(0);     // รวมค่าคอมก่อนหัก
                $table->decimal('wht_rate', 5, 2)->default(0);
                $table->decimal('wht_amount', 14, 2)->default(0);
                $table->decimal('net', 14, 2)->default(0);       // ยอดโอนสุทธิ
                $table->unsignedInteger('item_count')->default(0);
                $table->string('note', 500)->nullable();
                $table->unsignedBigInteger('issued_by')->nullable(); // hr_admins.id
                $table->timestamp('issued_at')->nullable();
                $table->date('paid_on')->nullable();             // วันที่โอน
                $table->string('paid_note', 500)->nullable();
                $table->unsignedBigInteger('paid_by')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->unsignedBigInteger('cancelled_by')->nullable();
                $table->string('cancel_reason', 500)->nullable();
                $table->timestamps();

                $table->index(['agent_code', 'month']);
                $table->index(['month', 'status']);
            });
        }

        if (! Schema::hasTable('ag_commission_items')) {
            Schema::create('ag_commission_items', function (Blueprint $table) {
                $table->id();
                $table->string('agent_code', 20)->index();      // ผู้รับค่าคอม
                $table->string('kind', 20);                     // management | finder | manual
                $table->string('source', 20)->default('auto');  // auto | carried | manual
                $table->string('source_key', 40)->nullable()->unique(); // rent:{payment_record_id} | finder:{booking_id}
                $table->unsignedBigInteger('property_id')->nullable()->index();
                $table->string('property_code', 50)->nullable();
                $table->unsignedBigInteger('booking_id')->nullable();
                $table->unsignedBigInteger('payment_record_id')->nullable();
                $table->char('period', 7);                      // เดือนค่าเช่า / เดือนเข้าใช้งาน / งวดของรายการเพิ่มเอง
                $table->string('description', 255)->nullable();
                $table->decimal('base_amount', 14, 2)->default(0); // ฐานคำนวณ (ค่าเช่า)
                $table->decimal('factor', 8, 4)->default(0);       // % (ค่าบริหาร) หรือ จำนวนเดือน (ค่าหาผู้เช่า)
                $table->decimal('calc_amount', 14, 2)->default(0); // ยอดที่ระบบคำนวณ
                $table->decimal('override_amount', 14, 2)->nullable(); // ยอดที่ฝ่ายบัญชีแก้
                $table->string('note', 500)->nullable();
                $table->boolean('is_excluded')->default(false);
                $table->string('state', 20)->default('waiting'); // waiting | ready
                $table->string('wait_reason', 120)->nullable();
                $table->date('due_date')->nullable();              // ครบกำหนดชำระค่าเช่า / วันเริ่มสัญญา
                $table->timestamp('ready_at')->nullable();
                $table->unsignedBigInteger('payout_id')->nullable()->index();
                $table->unsignedBigInteger('edited_by')->nullable(); // hr_admins.id
                $table->timestamp('edited_at')->nullable();
                $table->timestamps();

                $table->index(['agent_code', 'state']);
            });
        }

        if (! Schema::hasTable('ag_commission_rates')) {
            Schema::create('ag_commission_rates', function (Blueprint $table) {
                $table->id();
                $table->string('agent_code', 20);
                $table->unsignedBigInteger('property_id')->default(0); // 0 = ทุกทรัพย์ของคนนี้
                $table->decimal('rate', 6, 3);                          // % ของค่าเช่า
                $table->unsignedBigInteger('updated_by')->nullable();   // hr_admins.id
                $table->timestamps();

                $table->unique(['agent_code', 'property_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ag_commission_rates');
        Schema::dropIfExists('ag_commission_items');
        Schema::dropIfExists('ag_commission_payouts');
    }
};
