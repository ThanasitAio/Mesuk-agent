<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class HrPaymentRecord extends Model
{
    use SoftDeletes;

    protected $table = 'hr_payment_records';

    protected $guarded = [];

    protected $casts = [
        'payment_slips'         => 'array',
        'payment_slip_batches'  => 'array',
        'rental_type_tags'      => 'array',
        'due_date'              => 'date',
        'paid_at'               => 'datetime',
        'verified_at'           => 'datetime',
        'amount'                => 'decimal:2',
    ];

    public function booking()
    {
        return $this->belongsTo(HrBooking::class, 'booking_id');
    }

    /**
     * แอดมินผู้อนุมัติ/ตรวจสอบสลิปโอนเงินของรายการนี้ (verified_by) - คนละคนกับ hr_invoices.approved_by
     * ที่เป็นผู้อนุมัติตัวใบแจ้งหนี้ ดู happyest PaymentRecord::verifiedBy()
     */
    public function verifiedBy()
    {
        return $this->belongsTo(HrAdmin::class, 'verified_by');
    }

    public static function rentalTypeLabels(): array
    {
        return [
            'rent'           => 'ค่าเช่า',
            'land_tax'       => 'ค่าภาษีที่ดิน',
            'utility'        => 'ค่าน้ำ/ไฟ',
            'deposit'        => 'เงินมัดจำ',
            'processing_fee' => 'ค่าดำเนินการ',
        ];
    }

    public function getTypeLabel(): string
    {
        if ($this->payment_type === 'deposit') {
            $phase = (int) $this->deposit_phase;
            if ($phase === 1) {
                return 'เงินมัดจำงวดที่ 1';
            }
            if ($phase === 2) {
                return 'เงินมัดจำงวดที่ 2';
            }

            return 'เงินมัดจำ';
        }

        return match ($this->payment_type) {
            'monthly_rent'   => "ค่าเช่ารายเดือน เดือนที่ {$this->month_number}",
            'processing_fee' => 'ค่าดำเนินการ',
            'late_fee'       => 'ค่าปรับล่าช้า' . ($this->month_number ? " เดือนที่ {$this->month_number}" : ''),
            default          => $this->payment_type,
        };
    }

    public function getStatusLabel(): string
    {
        return match ($this->payment_status) {
            'pending'              => 'รอชำระ',
            'pending_verification' => 'รอตรวจสอบ',
            'paid'                 => 'ชำระแล้ว',
            'failed'               => 'ถูกปฏิเสธ',
            'refunded'             => 'คืนเงิน',
            default                => $this->payment_status,
        };
    }

    public function getStatusColor(): string
    {
        return match ($this->payment_status) {
            'pending'              => 'yellow',
            'pending_verification' => 'blue',
            'paid'                 => 'green',
            'failed'               => 'red',
            'refunded'             => 'gray',
            default                => 'gray',
        };
    }

    public function isPhase2Deposit(): bool
    {
        return $this->payment_type === 'deposit' && (int) $this->deposit_phase === 2;
    }

    public function isCombinedMonth1Hidden(Collection $records): bool
    {
        if ($this->payment_type !== 'monthly_rent' || (int) $this->month_number !== 1 || ! $this->payment_slip_path) {
            return false;
        }

        return $records
            ->where('payment_type', 'deposit')
            ->where('deposit_phase', 2)
            ->whereIn('payment_status', ['pending_verification', 'paid'])
            ->where('payment_slip_path', $this->payment_slip_path)
            ->isNotEmpty();
    }

    /**
     * แนบสลิปรอบใหม่ (batch) เข้า payment_slip_batches - รองรับหลายรอบโอน/หลายวันที่ต่อ 1 บิลเดียวกัน
     * (เช่น จ่ายค่าเช่าบางส่วนวันนี้ ส่วนที่เหลือวันหลัง) โดยไม่ล้างของเดิม ต่างจาก update() ตรงๆ
     * ที่เขียนทับ payment_slips/paid_at ทั้งก้อน - ยังคง mirror payment_slips/payment_slip_path (flat)
     * ไว้เหมือนเดิมเพื่อไม่ให้โค้ดจุดอื่นที่อ่าน field เดิม (viewSlip by index ฯลฯ) พัง
     */
    public function appendSlipBatch(array $paths, \Carbon\Carbon $transferDate, array $tags = [], string $uploadedByType = 'agent_manager', ?string $uploadedByName = null): void
    {
        $batches = $this->payment_slip_batches ?? [];
        $batches[] = [
            'paths' => $paths,
            'transfer_date' => $transferDate->toDateString(),
            'rental_type_tags' => array_values($tags),
            'uploaded_by_type' => $uploadedByType,
            'uploaded_by_name' => $uploadedByName,
            'uploaded_at' => now()->toIso8601String(),
        ];

        $existingSlips = $this->payment_slips ?? [];
        $allSlips = array_merge($existingSlips, $paths);

        $this->update([
            'payment_slip_batches' => $batches,
            'payment_slips' => $allSlips,
            'payment_slip_path' => $allSlips[0] ?? null,
            'payment_status' => 'pending_verification',
            'paid_at' => $transferDate,
        ]);
    }

    /**
     * ค่าเช่ารายเดือนต้องมีใบแจ้งหนี้ (hr_invoices, status=approved) ของงวดนั้นก่อนถึงจะแนบสลิปได้
     * mirror ของ resolveInvoiceMatch() ฝั่ง happyest - ประเภทอื่น (มัดจำ/ค่าดำเนินการ) ไม่ต้องมีใบแจ้งหนี้
     */
    public function hasIssuedInvoice(?HrBooking $booking = null): bool
    {
        if ($this->payment_type !== 'monthly_rent' || ! $this->due_date) {
            return true;
        }

        $booking = $booking ?? $this->booking;
        if (! $booking) {
            return false;
        }

        $invoices = $booking->relationLoaded('invoices')
            ? $booking->invoices
            : $booking->invoices()->get();

        $billingMonth = $this->due_date->format('Y-m');

        return $invoices->contains(fn ($inv) => $inv->invoice_type === 'monthly_rent'
            && $inv->status === 'approved'
            && $inv->billing_month === $billingMonth);
    }

    public function canUploadSlip(?HrBooking $booking = null, bool $ignoreInvoiceCheck = false): bool
    {
        if (! in_array($this->payment_status, ['pending', 'failed', 'pending_verification'], true)) {
            return false;
        }

        $booking = $booking ?? $this->booking;
        if (! $booking) {
            return false;
        }

        $isPhase2Deposit = $this->isPhase2Deposit();

        if (
            (in_array($this->payment_type, HrBooking::finalPaymentTypes(), true) || $isPhase2Deposit)
            && ! $booking->isContractSent()
            && ! $booking->allow_pay_before_contract
        ) {
            return false;
        }

        if (! $isPhase2Deposit && ! in_array($this->payment_type, $booking->allowedPaymentTypesForCurrentPhase(), true)) {
            return false;
        }

        if (! $ignoreInvoiceCheck && ! $this->hasIssuedInvoice($booking)) {
            return false;
        }

        return true;
    }

    public function getDisplayLabel(Collection $records, bool $hasComboPayment, ?HrPaymentRecord $comboMonth1 = null): string
    {
        if ($this->isPhase2Deposit() && $hasComboPayment && $comboMonth1) {
            return 'มัดจำงวดที่ 2 + ค่าเช่ารายเดือน เดือนที่ 1';
        }

        return $this->getTypeLabel();
    }

    public function getComboAmount(Collection $records, bool $hasComboPayment, ?HrPaymentRecord $comboMonth1 = null): float
    {
        if ($this->isPhase2Deposit() && $hasComboPayment && $comboMonth1) {
            return round((float) $this->amount + (float) $comboMonth1->amount, 2);
        }

        return (float) $this->amount;
    }

    /**
     * จับคู่กับใบแจ้งหนี้จริง (hr_invoices) ถ้ามี แล้วคืนยอดสุทธิ/ผู้รับเงินจริงจากใบแจ้งหนี้นั้น แทนการคำนวณสด
     * จาก Property/Booking config - พอร์ตมาจาก happyest PaymentRecord::resolveInvoiceMatch() (ดู
     * C:\laragon\www\happyest\app\Models\PaymentRecord.php) เพื่อให้หน้ารอบบิลของ agent ยึดยอด/ผู้รับเงินตาม
     * ใบแจ้งหนี้จริงในระบบ admin/invoices เหมือนฝั่งลูกค้า ไม่ใช่ config สดที่อาจเปลี่ยนไปแล้วหลังออกใบ
     *
     * ต้อง setRelation('booking', ...) ไว้ก่อนเรียกเป็นชุด กัน lazy-load ต่อ record (ดู
     * PropertyBillingController::show())
     *
     * $approvedOnly = true จำกัดเฉพาะใบแจ้งหนี้ที่อนุมัติแล้ว (status=approved) - ใช้เสมอที่นี่เพราะหน้ารอบบิล
     * ของ agent แสดงยอด/บัญชีให้ผู้จัดการทรัพย์เห็นตรง ไม่ควรอิงใบที่ยังไม่อนุมัติซึ่งอาจเปลี่ยนได้
     */
    public function resolveInvoiceMatch(bool $approvedOnly = false): array
    {
        $pool = $this->booking?->invoices?->whereNotIn('status', ['cancelled', 'voided']) ?? collect();
        if ($approvedOnly) {
            $pool = $pool->where('status', 'approved');
        }
        $list = match ($this->payment_type) {
            // ไม่ ->take(1) เพราะ billing_route='both' ตอนสร้างใบแยกเป็น 2 แถวเสมอ (company + investor) -
            // เอาแค่ 1 จะทิ้งอีกฝั่งไป ทำให้ยอดรวมจริงต่ำกว่าความเป็นจริง
            'deposit' => $pool->where('invoice_type', 'deposit')
                ->filter(fn ($inv) => is_null($this->deposit_phase) || is_null($inv->deposit_phase) || $inv->deposit_phase == $this->deposit_phase)
                ->values(),
            'processing_fee' => $pool->where('invoice_type', 'service_fee')->values(),
            'monthly_rent' => $pool->where('invoice_type', 'monthly_rent')
                ->when($this->due_date, fn ($c) => $c->where('billing_month', $this->due_date->format('Y-m')))
                ->values(),
            'late_fee' => $pool->where('invoice_type', 'late_fee')
                ->when($this->source_payment_record_id, function ($c) {
                    $source = $this->booking?->paymentRecords?->firstWhere('id', $this->source_payment_record_id);
                    $ym = $source?->due_date?->format('Y-m');

                    return $ym ? $c->where('billing_month', $ym) : $c;
                })
                ->values(),
            default => collect(),
        };

        if ($list->isEmpty()) {
            // ยังไม่มีใบแจ้งหนี้จริงผูกอยู่ - พรีวิวยอดที่ใบแจ้งหนี้จะออกจริงด้วยสูตรเดียวกัน (เฉพาะค่าเช่ารายเดือน)
            // แทนการคืน null เฉยๆ กันไม่ให้ต่ำกว่ายอดจริงที่ใบแจ้งหนี้จะออกในที่สุด (เช่นภาษีที่ดินที่ไม่ได้หัก
            // ณ ที่จ่ายของตัวเอง)
            return [
                'invoices' => $list,
                'net_total' => $this->payment_type === 'monthly_rent' ? $this->previewNetTotal() : null,
                'split' => null,
            ];
        }

        $list->each(function ($inv) {
            $inv->setRelation('booking', $this->booking);
            $inv->setRelation('property', $this->booking?->property);
        });

        $netTotal = 0.0;
        $split = ['company' => 0.0, 'investor' => 0.0];
        foreach ($list as $inv) {
            $bd = \App\Services\InvoiceBreakdownService::computeDisplayBreakdown($inv);
            // ยอดที่ต้องโอนจริงเสมอคือ net (หลังหัก ณ ที่จ่าย) + VAT ไม่ว่า billing_route ใด - ห้ามใช้ total_due
            // แบบ happyest เพราะฝั่ง company ไม่ได้หัก WHT ออก (คือยอดตามใบกำกับภาษี ไม่ใช่ยอดเงินที่โอนจริง)
            $bdNetWithVat = round($bd['net_payable'] + $bd['vat_amount'], 2);
            $routeKey = $inv->billing_route === 'investor' ? 'investor' : 'company';
            $split[$routeKey] += $bdNetWithVat;
            $netTotal += $bdNetWithVat;
        }
        $netTotal = round($netTotal, 2);
        $split['company'] = round($split['company'], 2);
        $split['investor'] = round($split['investor'], 2);

        return [
            'invoices' => $list,
            'net_total' => $netTotal,
            'split' => $split,
        ];
    }

    /**
     * ยอด net_total แบบพรีวิว สำหรับ monthly_rent record ที่ยังไม่มีใบแจ้งหนี้จริงผูกอยู่ - พอร์ตมาจาก happyest
     * PaymentRecord::previewNetTotal() คืน null เมื่อไม่ใช่นิติบุคคล/ไม่มีอัตราหัก ณ ที่จ่าย (grossFactor=1 →
     * เท่ากับ amount ดิบอยู่แล้ว ไม่ต้อง override)
     */
    public function previewNetTotal(): ?float
    {
        $booking = $this->booking;
        $property = $booking?->property;
        if (! $booking || ! $property) {
            return null;
        }

        $isJuristic = ($booking->renter_type ?? '') === 'juristic';
        $whtRateRaw = (float) ($booking->withholding_tax_rate ?? 0);
        if (! $isJuristic || $whtRateRaw <= 0) {
            return null;
        }

        $items = [];
        if ((float) ($this->base_rent_amount ?? 0) > 0) {
            $items[] = ['label' => 'ค่าเช่ารายเดือน', 'amount' => (float) $this->base_rent_amount];
        }
        if ((float) ($this->land_tax_amount ?? 0) > 0) {
            $items[] = ['label' => 'ภาษีที่ดินและสิ่งปลูกสร้าง', 'amount' => (float) $this->land_tax_amount];
        }
        if ((float) ($this->stamp_duty_amount ?? 0) > 0) {
            $items[] = ['label' => 'อากรแสตมป์', 'amount' => (float) $this->stamp_duty_amount];
        }
        if (empty($items)) {
            return null;
        }

        $bd = \App\Services\InvoiceBreakdownService::computeDisplayBreakdownPreview(
            $items,
            'monthly_rent',
            $booking->renter_type ?? 'individual',
            $whtRateRaw,
            (bool) ($property->show_withholding_tax_rent ?? true),
            (bool) ($property->show_withholding_tax_land ?? true),
            (bool) ($property->show_withholding_tax_side_area ?? true),
            (bool) ($property->rent_has_vat ?? false),
            (bool) ($property->land_tax_has_vat ?? false),
        );

        return round($bd['net_payable'] + $bd['vat_amount'], 2);
    }
}
