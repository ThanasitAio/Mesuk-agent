<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrInvoice extends Model
{
    use SoftDeletes;

    protected $table = 'hr_invoices';

    protected $guarded = [];

    protected $casts = [
        'snapshot_property' => 'array',
        'snapshot_booking'  => 'array',
        'snapshot_customer' => 'array',
        'snapshot_owner'    => 'array',
        'billing_items'     => 'array',
        'amount'            => 'decimal:2',
        'tax_amount'        => 'decimal:2',
        'total_amount'      => 'decimal:2',
        'include_vat'       => 'boolean',
        'vat_rate'          => 'decimal:2',
        'deposit_phase'     => 'integer',
        'approved_at'       => 'datetime',
        'rejected_at'       => 'datetime',
        'send_email_on_approve' => 'boolean',
    ];

    public function booking()
    {
        return $this->belongsTo(HrBooking::class, 'booking_id');
    }

    public function getInvoiceTypeLabelAttribute(): string
    {
        return match ($this->invoice_type ?? 'monthly_rent') {
            'deposit'      => 'ค่ามัดจำ',
            'service_fee'  => 'ค่าดำเนินการ',
            'monthly_rent' => 'ค่าเช่ารายเดือน',
            'multi'        => 'หลายรายการ',
            default        => $this->invoice_type ?? '—',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending'   => 'รอดำเนินการ',
            'approved'  => 'อนุมัติแล้ว',
            'rejected'  => 'ปฏิเสธ',
            'cancelled' => 'ยกเลิก',
            'voided'    => 'ยกเลิก (Void)',
            default     => $this->status,
        };
    }

    public function getBillingRouteLabelAttribute(): string
    {
        return match ($this->billing_route ?? 'company') {
            'investor' => 'นักลงทุน',
            default    => 'บริษัท',
        };
    }
}
