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
        'payment_slips' => 'array',
        'due_date'      => 'date',
        'paid_at'       => 'datetime',
        'verified_at'   => 'datetime',
        'amount'        => 'decimal:2',
    ];

    public function booking()
    {
        return $this->belongsTo(HrBooking::class, 'booking_id');
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

    public function canUploadSlip(?HrBooking $booking = null): bool
    {
        if (! in_array($this->payment_status, ['pending', 'failed'], true)) {
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
            $firstPendingRent = $records
                ->where('payment_type', 'monthly_rent')
                ->whereIn('payment_status', ['pending', 'failed'])
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
