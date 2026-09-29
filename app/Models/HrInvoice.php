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
        'issued_date'       => 'date',
        'approved_at'       => 'datetime',
        'rejected_at'       => 'datetime',
        'send_email_on_approve' => 'boolean',
    ];

    public function booking()
    {
        return $this->belongsTo(HrBooking::class, 'booking_id');
    }

    public function property()
    {
        return $this->belongsTo(HrProperty::class, 'property_id');
    }

    public function customer()
    {
        return $this->belongsTo(HrCustomer::class, 'customer_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(HrAdmin::class, 'approved_by');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /** mapping เดียวกับ happyest Invoice::getInvoiceTypeLabelAttribute() (ใช้เป็นป้ายตั้งต้นของชื่อไฟล์ PDF ด้วย) */
    public function getInvoiceTypeLabelAttribute(): string
    {
        return match ($this->invoice_type ?? 'monthly_rent') {
            'deposit'      => 'ค่ามัดจำ',
            'service_fee'  => 'ค่าดำเนินการ',
            'monthly_rent' => 'ค่าเช่ารายเดือน',
            'utility'      => 'ค่าน้ำ/ไฟ',
            'late_fee'     => 'ค่าปรับล่าช้า',
            'multi'        => 'หลายรายการ',
            default        => $this->invoice_type ?? '-',
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

    /**
     * ป้ายประเภทใบแจ้งหนี้แบบละเอียด - พอร์ตมาจาก happyest Invoice::getDetailedTypeLabelAttribute()
     * (ดู C:\laragon\www\happyest\app\Models\Invoice.php) เพื่อให้ป้ายที่ผู้บริหารโครงการเห็นตรงกับ
     * ที่ทั้งแอดมินและเจ้าของทรัพย์เห็น
     */
    public function getDetailedTypeLabelAttribute(): string
    {
        return match (true) {
            ($this->invoice_sub_type ?? null) === 'land_tax'   => 'ภาษีที่ดิน',
            ($this->invoice_sub_type ?? null) === 'rent'       => 'ค่าเช่า',
            ($this->invoice_sub_type ?? null) === 'stamp_duty' => 'อากรแสตมป์',
            ($this->invoice_sub_type ?? null) === 'side_area'  => 'ค่าเช่าพื้นที่ด้านข้าง',
            $this->invoice_type === 'utility' && ($this->invoice_sub_type ?? null) === 'water'    => 'ค่าน้ำ',
            $this->invoice_type === 'utility' && ($this->invoice_sub_type ?? null) === 'electric' => 'ค่าไฟ',
            $this->invoice_type === 'utility' && ($this->invoice_sub_type ?? null) === 'common_fee' => 'ค่าส่วนกลาง',
            $this->invoice_type === 'utility'      => 'ค่าน้ำไฟ',
            $this->invoice_type === 'deposit'      => 'ค่ามัดจำ',
            $this->invoice_type === 'service_fee'  => 'ค่าดำเนินการ',
            $this->invoice_type === 'monthly_rent' => 'ค่าเช่า',
            default => $this->invoice_type_label ?: 'ใบแจ้งหนี้',
        };
    }

    /**
     * ยอดแยกน้ำ/ไฟ/ส่วนกลางของใบแจ้งหนี้น้ำ/ไฟ จาก billing_items ที่ freeze ไว้ตอนออกใบ - สูตรเดียวกับ happyest
     * UtilityInvoiceBillingRowService (แถวที่ออกใบแล้ว): รายการมิเตอร์แยกตาม meter_type, รายการที่ไม่มี meter_type
     * (ค่าส่วนกลาง + หัก ณ ที่จ่ายค่าส่วนกลางที่เก็บเป็นยอดติดลบ) รวมเป็นค่าส่วนกลาง
     */
    public function utilityBreakdown(): array
    {
        $items = array_filter(is_array($this->billing_items) ? $this->billing_items : [], 'is_array');
        $sum = fn (callable $match) => (float) array_sum(array_column(array_filter($items, $match), 'amount'));

        return [
            'water' => $sum(fn ($item) => ($item['meter_type'] ?? null) === 'water'),
            'electric' => $sum(fn ($item) => ($item['meter_type'] ?? null) === 'electric'),
            'common_fee' => $sum(fn ($item) => ! array_key_exists('meter_type', $item)),
        ];
    }

    /**
     * PaymentRecord(s) ของ booking ที่ตรงกับใบแจ้งหนี้นี้ - hr_invoices ไม่มี FK ไป hr_payment_records
     * โดยตรง จับคู่ผ่าน invoice_type/deposit_phase/billing_month เหมือนฝั่ง happyest ต้อง
     * loadMissing(['booking.paymentRecords']) ไว้ก่อนเรียกกันวน N+1 ต่อแถวในหน้า list
     */
    public function matchingPaymentRecords()
    {
        $payType = match ($this->invoice_type) {
            'deposit' => 'deposit',
            'service_fee' => 'processing_fee',
            'monthly_rent' => 'monthly_rent',
            'utility' => 'utility',
            default => null,
        };
        if (! $payType) {
            return collect();
        }

        $records = ($this->booking?->paymentRecords ?? collect())->where('payment_type', $payType);

        if ($this->invoice_type === 'deposit' && $this->deposit_phase) {
            $records = $records->where('deposit_phase', $this->deposit_phase);
        }

        if ($this->invoice_type === 'monthly_rent' && $this->billing_month) {
            $records = $records->filter(fn ($r) => $r->due_date && $r->due_date->format('Y-m') === $this->billing_month);
        }

        if ($this->invoice_type === 'utility' && $this->billing_month) {
            $records = $records->filter(fn ($r) => $r->due_date && $r->due_date->copy()->subMonth()->format('Y-m') === $this->billing_month);
        }

        return $records->values();
    }

    /**
     * สรุปสถานะการชำระของใบแจ้งหนี้นี้ (ไม่ใช่ $this->status ที่เป็น workflow อนุมัติภายในของแอดมิน)
     * ให้ผู้บริหารโครงการเห็นว่าผู้เช่าแนบสลิปหรือยัง, รอตรวจสอบหรือชำระแล้ว, โอนวันไหนบ้าง
     */
    public function paymentSummary(): array
    {
        $records = $this->matchingPaymentRecords();

        $status = null;
        if ($records->contains(fn ($r) => $r->payment_status === 'paid')) {
            $status = 'paid';
        } elseif ($records->contains(fn ($r) => $r->payment_status === 'pending_verification')) {
            $status = 'pending_verification';
        }

        $transferDates = collect();
        foreach ($records as $r) {
            $batches = $r->payment_slip_batches ?? [];
            if (! empty($batches)) {
                foreach ($batches as $batch) {
                    if (! empty($batch['transfer_date'])) {
                        $transferDates->push(\Carbon\Carbon::parse($batch['transfer_date']));
                    }
                }
            } elseif ($r->paid_at) {
                $transferDates->push($r->paid_at);
            }
        }
        $transferDates = $transferDates->sortBy(fn ($d) => $d->timestamp)->unique(fn ($d) => $d->toDateString())->values();

        // แอดมินผู้อนุมัติ/ตรวจสอบสลิปโอนล่าสุด (hr_payment_records.verified_at/verified_by) - คนละอันกับ
        // hr_invoices.approved_at/approved_by (อนุมัติตัวใบแจ้งหนี้) เลือกรายการที่ verified_at ล่าสุดถ้า
        // มีมากกว่า 1 รายการจ่ายตรงกับใบแจ้งหนี้นี้
        $verifiedRecord = $records
            ->filter(fn ($r) => $r->payment_status === 'paid' && $r->verified_at)
            ->sortByDesc(fn ($r) => $r->verified_at->timestamp)
            ->first();

        return [
            'status' => $status, // null = ยังไม่แนบสลิป, 'pending_verification', 'paid'
            'transfer_dates' => $transferDates,
            'verified_at' => $verifiedRecord?->verified_at,
            'verified_by_name' => $verifiedRecord?->verifiedBy?->name,
        ];
    }
}
