<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * อ่านอย่างเดียว - พนักงาน/แอดมินฝั่ง happyest (hr_admins) เป็นผู้อนุมัติใบแจ้งหนี้เพียงฝ่ายเดียว
 * (hr_invoices.approved_by อ้างมาที่ตารางนี้เท่านั้น ไม่มีช่องทางให้เจ้าของทรัพย์อนุมัติเองแยกต่างหาก -
 * ดู happyest AdminInvoiceController::approve() ที่ approved_by = Auth::guard('admin')->id() เสมอ)
 */
class HrAdmin extends Model
{
    use SoftDeletes;

    protected $table = 'hr_admins';

    protected $guarded = [];

    protected $hidden = [
        'password',
        'remember_token',
    ];
}
