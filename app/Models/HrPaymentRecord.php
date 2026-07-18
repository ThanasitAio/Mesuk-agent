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
     * แนบสลิปรอบใหม่ (batch) เข้า payment_slip_batches — รองรับหลายรอบโอน/หลายวันที่ต่อ 1 บิลเดียวกัน
     * (เช่น จ่ายค่าเช่าบางส่วนวันนี้ ส่วนที่เหลือวันหลัง) โดยไม่ล้างของเดิม ต่างจาก update() ตรงๆ
     * ที่เขียนทับ payment_slips/paid_at ทั้งก้อน — ยังคง mirror payment_slips/payment_slip_path (flat)
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

    public function canUploadSlip(?HrBooking $booking = null): bool
    {
        if (! in_array($this->payment_status, ['pending', 'failed', 'pending_verification'], true)) {
            return false;
        }

        $booking = $booking ?? $this->booking;
        if (! $booking) {
            return false;
        }

        $records = $booking->relationLoaded('paymentRecords')
            ? $booking->paymentRecords
            : $booking->paymentRecords()->get();

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

        if ($this->payment_type === 'monthly_rent') {
            // รวม pending_verification ด้วย — เดือนที่กำลังรอตรวจสลิปอยู่ยังนับเป็น "เดือนแรกที่ยังไม่จบ"
            // เพื่อให้แนบสลิปเพิ่ม (อีกรอบโอน) ของเดือนเดียวกันได้ ไม่โดนบล็อกว่าเป็นการ "ข้ามเดือน"
            $firstPendingRent = $records
                ->where('payment_type', 'monthly_rent')
                ->whereIn('payment_status', ['pending', 'failed', 'pending_verification'])
                ->sortBy('due_date')
                ->first();

            if ($firstPendingRent && $firstPendingRent->id !== $this->id) {
                return false;
            }
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
}
