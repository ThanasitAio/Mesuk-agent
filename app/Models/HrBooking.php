<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrBooking extends Model
{
    use SoftDeletes;

    protected $table = 'hr_bookings';

    protected $guarded = [];

    const DEPOSIT_TYPE_FULL = 'full';
    const DEPOSIT_TYPE_HALF = 'half';

    const CONTRACT_STATUS_DRAFTING = 'drafting';
    const CONTRACT_STATUS_SENT = 'sent';

    protected $casts = [
        'check_in'                  => 'date',
        'check_out'                 => 'date',
        'actual_move_in_date'       => 'date',
        'contract_start_date'       => 'date',
        'rental_months'             => 'integer',
        'paid_at'                   => 'datetime',
        'allow_pay_before_contract' => 'boolean',
        'land_tax_to_investor'      => 'boolean',
        'contract_sent_at'          => 'datetime',
    ];

    public static function initialPaymentTypes(): array
    {
        return ['deposit', 'processing_fee'];
    }

    public static function finalPaymentTypes(): array
    {
        return ['monthly_rent', 'late_fee'];
    }

    public function property()
    {
        return $this->belongsTo(HrProperty::class, 'property_id');
    }

    public function customer()
    {
        return $this->belongsTo(HrCustomer::class, 'customer_id');
    }

    public function paymentRecords()
    {
        return $this->hasMany(HrPaymentRecord::class, 'booking_id')
            ->whereNull('deleted_at')
            ->where('is_hidden', false)
            ->orderByRaw("CASE payment_type WHEN 'deposit' THEN 1 WHEN 'processing_fee' THEN 2 WHEN 'monthly_rent' THEN 3 WHEN 'late_fee' THEN 4 ELSE 5 END")
            ->orderBy('deposit_phase')
            ->orderBy('month_number')
            ->orderBy('id');
    }

    public function allPaymentRecords()
    {
        return $this->hasMany(HrPaymentRecord::class, 'booking_id')
            ->whereNull('deleted_at')
            ->orderBy('id');
    }

    public function invoices()
    {
        return $this->hasMany(HrInvoice::class, 'booking_id')->whereNull('deleted_at');
    }

    public function isContractSent(): bool
    {
        return $this->contract_status === self::CONTRACT_STATUS_SENT;
    }

    public function isHalfDeposit(): bool
    {
        return ($this->deposit_type ?? self::DEPOSIT_TYPE_FULL) === self::DEPOSIT_TYPE_HALF;
    }

    public function hasInitialPaymentsConfirmed(): bool
    {
        $records = $this->relationLoaded('paymentRecords')
            ? $this->paymentRecords
            : $this->paymentRecords()->get();

        $initialRecords = $records->filter(function ($record) {
            if ($record->is_hidden) {
                return false;
            }
            if (! in_array($record->payment_type, self::initialPaymentTypes(), true)) {
                return false;
            }
            if ($record->payment_type === 'deposit' && (int) $record->deposit_phase === 2) {
                return false;
            }

            return true;
        });

        return $initialRecords->isEmpty()
            || $initialRecords->every(fn ($record) => $record->payment_status === 'paid');
    }

    public function hasInitialPaymentsUploaded(): bool
    {
        $records = $this->relationLoaded('paymentRecords')
            ? $this->paymentRecords
            : $this->paymentRecords()->get();

        $initialRecords = $records->filter(function ($record) {
            if ($record->is_hidden) {
                return false;
            }
            if (! in_array($record->payment_type, self::initialPaymentTypes(), true)) {
                return false;
            }
            if ($record->payment_type === 'deposit' && (int) $record->deposit_phase === 2) {
                return false;
            }

            return true;
        });

        return $initialRecords->isEmpty()
            || $initialRecords->every(fn ($record) => in_array($record->payment_status, ['paid', 'pending_verification'], true));
    }

    public function currentPaymentPhase(): string
    {
        if (! $this->hasInitialPaymentsConfirmed()) {
            if ($this->hasInitialPaymentsUploaded() && $this->allow_pay_before_contract) {
                return 'final';
            }

            return 'initial';
        }

        if (! $this->isContractSent() && ! $this->allow_pay_before_contract) {
            return 'waiting_contract';
        }

        return 'final';
    }

    public function allowedPaymentTypesForCurrentPhase(): array
    {
        return match ($this->currentPaymentPhase()) {
            'initial' => self::initialPaymentTypes(),
            'final'   => self::finalPaymentTypes(),
            default   => [],
        };
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'pending'            => 'รอการยืนยัน',
            'deposit_confirmed'  => 'ยืนยันเงินมัดจำ',
            'confirmed'          => 'ยืนยันการจอง',
            'checked_in'         => 'เข้าพักแล้ว',
            'checked_out'        => 'ออกแล้ว',
            'cancelled'          => 'ยกเลิก',
            'rejected'           => 'ปฏิเสธ',
            'completed'          => 'เสร็จสิ้น',
            default              => $this->status ?? '—',
        };
    }

    public function getPaymentStatusLabel(): string
    {
        return match ($this->payment_status) {
            'pending'       => 'รอชำระ',
            'partial_paid'  => 'ชำระบางส่วน',
            'fully_paid'    => 'ชำระครบแล้ว',
            'paid'          => 'ชำระแล้ว',
            'refunded'      => 'คืนเงินแล้ว',
            'failed'        => 'ไม่ผ่าน',
            default         => $this->payment_status ?? '—',
        };
    }

    public function getContractStatusLabel(): string
    {
        return match ($this->contract_status) {
            self::CONTRACT_STATUS_SENT     => 'ส่งสัญญาแล้ว',
            self::CONTRACT_STATUS_DRAFTING => 'ร่างสัญญา (ยังไม่ส่ง)',
            default                        => $this->contract_status ?? '—',
        };
    }

    public function updatePaymentStatus(): void
    {
        $visibleQuery = $this->allPaymentRecords()->where('is_hidden', false);
        $totalRecords = (clone $visibleQuery)->count();
        $paidRecords = (clone $visibleQuery)->where('payment_status', 'paid')->count();
        $pendingVerification = (clone $visibleQuery)->where('payment_status', 'pending_verification')->count();

        if ($paidRecords === $totalRecords && $totalRecords > 0) {
            $this->update(['payment_status' => 'fully_paid']);
        } elseif ($paidRecords > 0 || $pendingVerification > 0) {
            $this->update(['payment_status' => 'partial_paid']);
        }
    }
}
