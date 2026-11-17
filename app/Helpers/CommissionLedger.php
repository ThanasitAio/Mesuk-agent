<?php

namespace App\Helpers;

use App\Models\AgCommissionItem;
use App\Models\AgCommissionPayout;
use App\Models\AgCommissionRate;
use App\Models\HrBooking;
use App\Models\HrPaymentRecord;
use App\Models\HrProperty;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * ค่าคอมมิชชั่นของผู้บริหารโครงการ/ตัวแทน (งาน #163, CEO 2026-11-17)
 *
 * ปัญหาเดิม: แต่ละเดือนได้ยอดเดียว "ค่าบริหารเดือนนี้ + ของเดือนก่อน - รายการที่ผู้เช่ายังไม่จ่าย" โดยไม่เห็นที่มา
 * รายการที่ถูกหักไปแล้วไม่มีใครตามคืน (เช่น R05B ส.ค./ก.ย. 69 ผู้เช่าจ่ายแล้วแต่ยังอยู่ในยอดค้าง)
 *
 * หลักใหม่: ค่าคอมเป็นรายการทีละบรรทัดที่ผูกกับข้อมูลจริงใน happyest
 *  - ค่าบริหารโครงการ = RATE% x ค่าเช่าของเดือนนั้น (ไม่รวม VAT/ภาษีที่ดิน) ต่อทรัพย์ที่ดูแล หนึ่งรายการต่อบิลค่าเช่า
 *  - ค่าหาผู้เช่า = ค่าเช่าต่อเดือน x จำนวนเดือนตามอายุสัญญา (1 ปี = 1 เดือน, 3 ปี = 2 เดือน) เมื่อผู้เช่าเข้าใช้งาน
 *  - รายการรอ (waiting) จะย้ายเป็นพร้อมจ่าย (ready) เองทันทีที่ผู้เช่าชำระ/เข้าใช้งาน ไม่ต้องมีใครจำ
 *  - งวดที่ได้รับ = เดือนที่พร้อมจ่าย (ไม่ก่อนเดือนของรายการ) ถ้างวดนั้นโอนไปแล้ว ขยับไปงวดถัดไป
 *  - หัก ณ ที่จ่าย WHT_RATE% จากยอดรวมของงวด
 * แอปนี้ดูอย่างเดียว: ฝ่ายบัญชีแก้ยอด/เพิ่มรายการ/ตั้งอัตรา/เปิดใบ/บันทึกโอน ที่หลังบ้าน happyest /admin/agent-commissions
 * (App\Services\AgentCommissionService ของ happyest = กติกาเดียวกับไฟล์นี้ แก้ฝั่งหนึ่งต้องแก้อีกฝั่งด้วย)
 */
class CommissionLedger
{
    /** งวดแรกที่ระบบสร้างรายการเอง (ก่อนหน้านี้ฝ่ายบัญชีดึงรายการค้างเข้ามาเองเป็น "ยอดยกมา") */
    public const START_PERIOD = '2026-11';

    /** % ค่าบริหารโครงการของค่าเช่า (ข้อมูลจริง R05B 21,000 -> 525, R05E 120,000 -> 3,000) */
    public const DEFAULT_RATE = 2.5;

    /** หัก ณ ที่จ่ายค่านายหน้า (6,808.80 -> 204.26) */
    public const WHT_RATE = 3.0;

    /** รหัสบริษัทเอง - ไม่มีค่าหาผู้เช่า */
    public const COMPANY_AGENT_CODES = ['0000001'];

    /** อายุสัญญาขั้นต่ำ (เดือน) => ค่าเช่ากี่เดือน (CEO: 1 ปี = 1 เดือน, 3 ปี = 2 เดือน) เรียงจากมากไปน้อย */
    public const FINDER_TIERS = [36 => 2, 12 => 1];

    private const ACTIVE_BOOKING = ['deposit_confirmed', 'confirmed', 'checked_in', 'checked_out', 'completed'];
    private const MOVED_IN       = ['checked_in', 'checked_out', 'completed'];

    private static array $columnCache = [];

    /** แอดมิน happyest ที่เข้ามาทางปุ่ม SSO - เห็นลิงก์ไปหน้าจัดการค่าคอมที่หลังบ้าน */
    public static function viaAdmin(): bool
    {
        return (int) session('agent_sso_admin_id') > 0;
    }

    /** หน้าค่าคอมของคนนี้ที่หลังบ้าน happyest (HAPPYEST_APP_URL อาจลงท้าย /public) */
    public static function adminUrl(string $agentCode, ?string $month = null): string
    {
        $base = preg_replace('#/public/?$#', '', rtrim((string) env('HAPPYEST_APP_URL', 'http://127.0.0.1/happyest'), '/'));

        return $base.'/admin/agent-commissions/'.$agentCode.($month ? '?month='.$month : '');
    }

    // ─── อัตรา ─────────────────────────────────────────────────────────────────

    /** [property_id => rate] ของคนนี้ (0 = ทุกทรัพย์) */
    public static function rates(string $agentCode): array
    {
        return AgCommissionRate::query()->where('agent_code', $agentCode)->pluck('rate', 'property_id')
            ->map(fn ($r) => (float) $r)->all();
    }

    public static function rateFor(array $rates, ?int $propertyId): float
    {
        return (float) ($rates[(int) $propertyId] ?? $rates[0] ?? self::DEFAULT_RATE);
    }

    public static function finderMonths(int $contractMonths): int
    {
        foreach (self::FINDER_TIERS as $min => $months) {
            if ($contractMonths >= $min) {
                return $months;
            }
        }

        return 0;
    }

    // ─── สร้าง/อัปเดตรายการจากข้อมูลจริง ─────────────────────────────────────────

    public static function sync(string $agentCode): void
    {
        $rates = self::rates($agentCode);
        self::syncManagement($agentCode, $rates);
        self::syncFinder($agentCode);
    }

    private static function syncManagement(string $agentCode, array $rates): void
    {
        $start = self::START_PERIOD.'-01';
        $end = now()->endOfMonth()->toDateString();

        $propQuery = HrProperty::query()->where('manager_agent_code', $agentCode)->whereNull('deleted_at');
        if (self::hasColumn('hr_properties', 'is_test')) {
            $propQuery->where(fn ($q) => $q->whereNull('is_test')->orWhere('is_test', false));
        }
        $properties = $propQuery->get()->keyBy('id');

        $bookings = $properties->isEmpty() ? collect()
            : HrBooking::query()->whereIn('property_id', $properties->keys())->get()->keyBy('id');

        $records = $bookings->isEmpty() ? collect()
            : HrPaymentRecord::query()
                ->whereIn('booking_id', $bookings->keys())
                ->where('payment_type', 'monthly_rent')
                ->where(fn ($q) => $q->whereNull('is_hidden')->orWhere('is_hidden', false))
                ->whereBetween('due_date', [$start, $end])
                ->get();

        $keys = $records->map(fn ($r) => 'rent:'.$r->id)->all();
        $existing = AgCommissionItem::query()->whereIn('source_key', $keys)->get()->keyBy('source_key');
        $seen = [];

        foreach ($records as $record) {
            $booking = $bookings->get($record->booking_id);
            $property = $booking ? $properties->get($booking->property_id) : null;
            if (! $booking || ! $property || ! self::rentQualifies($record, $booking)) {
                continue;
            }
            $key = 'rent:'.$record->id;
            $item = $existing->get($key);
            if ($item && ($item->agent_code !== $agentCode || $item->isFrozen())) {
                $seen[$key] = true;
                continue;
            }
            $item ??= new AgCommissionItem([
                'agent_code' => $agentCode,
                'kind'       => AgCommissionItem::KIND_MANAGEMENT,
                'source'     => 'auto',
                'source_key' => $key,
            ]);
            self::fillManagement($item, $record, $booking, $property, self::rateFor($rates, $property->id));
            self::persist($item);
            $seen[$key] = true;
        }

        // รายการเปิดของคนนี้ที่ไม่อยู่ในชุดด้านบน: ยอดยกมา (ก่อน START_PERIOD), ทรัพย์ที่เปลี่ยนผู้ดูแลไปแล้ว,
        // บิลที่ถูกยกเลิก - อ่านบิลของตัวเองแล้วอัปเดต หรือลบถ้าบิลไม่มีแล้ว
        $others = AgCommissionItem::query()
            ->where('agent_code', $agentCode)
            ->where('kind', AgCommissionItem::KIND_MANAGEMENT)
            ->whereNull('payout_id')
            ->get()
            ->reject(fn ($i) => isset($seen[$i->source_key]));
        if ($others->isEmpty()) {
            return;
        }

        $recs = HrPaymentRecord::withTrashed()->whereIn('id', $others->pluck('payment_record_id')->filter())->get()->keyBy('id');
        $bks = HrBooking::withTrashed()->whereIn('id', $recs->pluck('booking_id')->filter()->unique())->get()->keyBy('id');
        $props = HrProperty::query()->whereIn('id', $bks->pluck('property_id')->filter()->unique())->get()->keyBy('id');

        foreach ($others as $item) {
            $record = $recs->get($item->payment_record_id);
            $booking = $record ? $bks->get($record->booking_id) : null;
            $property = $booking ? $props->get($booking->property_id) : null;
            $gone = ! $record || $record->trashed() || (bool) $record->is_hidden
                || ! $booking || $booking->trashed() || ! $property || $property->deleted_at
                || ! self::rentQualifies($record, $booking);
            // รายการอัตโนมัติที่บิลยังอยู่ในช่วงแต่ไม่ได้อยู่ในชุดด้านบน = ทรัพย์เปลี่ยนผู้ดูแลแล้ว ยังเป็นของคนเดิม
            if ($gone) {
                $item->delete();
                continue;
            }
            self::fillManagement($item, $record, $booking, $property, self::rateFor($rates, $property->id));
            self::persist($item);
        }
    }

    /** บิลค่าเช่านี้นับค่าบริหารหรือไม่: ไม่คืนเงิน/ไม่ล้มเหลว และการจองยังดำเนินอยู่ (ยกเลิกแล้วนับเฉพาะเดือนที่ชำระแล้ว) */
    private static function rentQualifies(HrPaymentRecord $record, HrBooking $booking): bool
    {
        $status = strtolower((string) $record->payment_status);
        if (in_array($status, ['refunded', 'failed'], true)) {
            return false;
        }

        return in_array($booking->status, self::ACTIVE_BOOKING, true) || $status === 'paid';
    }

    private static function fillManagement(AgCommissionItem $item, HrPaymentRecord $record, HrBooking $booking, HrProperty $property, float $rate): void
    {
        $base = (float) ($record->base_rent_amount ?? 0);
        if ($base <= 0) {
            $base = (float) $booking->monthly_rent;
        }
        $days = (int) ($record->prorated_days ?? 0);
        $total = (int) ($record->prorated_total_days_in_month ?? 0);

        $item->fill([
            'property_id'       => $property->id,
            'property_code'     => $property->property_code,
            'booking_id'        => $booking->id,
            'payment_record_id' => $record->id,
            'period'            => $record->due_date->format('Y-m'),
            'description'       => ($record->is_prorated && $days > 0)
                ? 'คิดตามวันเช่าจริง '.$days.($total > 0 ? '/'.$total : '').' วัน' : null,
            'base_amount'       => round($base, 2),
            'factor'            => $rate,
            'calc_amount'       => round($base * $rate / 100, 2),
            'due_date'          => $record->due_date->toDateString(),
        ]);

        $status = strtolower((string) $record->payment_status);
        if ($status === 'paid') {
            $readyAt = $record->verified_at ?? $record->investor_verified_at ?? $record->paid_at ?? $record->updated_at;
            $item->state = 'ready';
            $item->wait_reason = null;
            $item->ready_at = $readyAt ? Carbon::parse($readyAt) : now();

            return;
        }
        $item->state = 'waiting';
        $item->ready_at = null;
        $item->wait_reason = match ($status) {
            'pending_verification' => 'ผู้เช่าแนบสลิปแล้ว รอตรวจสอบ',
            'partially_paid'       => 'ผู้เช่าชำระบางส่วน',
            default                => 'ผู้เช่ายังไม่ชำระ',
        };
    }

    private static function syncFinder(string $agentCode): void
    {
        $open = AgCommissionItem::query()
            ->where('agent_code', $agentCode)
            ->where('kind', AgCommissionItem::KIND_FINDER)
            ->whereNull('payout_id')
            ->get()
            ->keyBy('source_key');

        $bookings = collect();
        if (! in_array($agentCode, self::COMPANY_AGENT_CODES, true)) {
            $agentId = (int) \App\Models\HrAgent::query()->where('agent_code', $agentCode)->value('id');
            $q = HrBooking::query()
                ->whereIn('status', self::ACTIVE_BOOKING)
                ->where('check_in', '>=', self::START_PERIOD.'-01')
                ->where(function ($q) use ($agentCode, $agentId) {
                    $q->where('agent_code', $agentCode);
                    if ($agentId > 0) {
                        $q->orWhere(fn ($q2) => $q2->where(fn ($q3) => $q3->whereNull('agent_code')->orWhere('agent_code', ''))
                            ->where('agent_id', $agentId));
                    }
                });
            if (self::hasColumn('hr_bookings', 'previous_booking_id')) {
                // ต่อสัญญา/จองต่อของผู้เช่าเดิม ไม่ใช่การหาผู้เช่าใหม่
                $q->whereNull('previous_booking_id');
            }
            $bookings = $q->get();
            $renewals = self::renewalIds($bookings);
            $bookings = $bookings->reject(fn ($b) => in_array((int) $b->id, $renewals, true));
        }

        $props = $bookings->isEmpty() ? collect()
            : HrProperty::query()->whereIn('id', $bookings->pluck('property_id')->unique())->whereNull('deleted_at')->get()->keyBy('id');
        $existing = $bookings->isEmpty() ? collect()
            : AgCommissionItem::query()->whereIn('source_key', $bookings->map(fn ($b) => 'finder:'.$b->id))->get()->keyBy('source_key');
        $seen = [];

        foreach ($bookings as $booking) {
            $property = $props->get($booking->property_id);
            if (! $property || (self::hasColumn('hr_properties', 'is_test') && $property->is_test)) {
                continue;
            }
            $key = 'finder:'.$booking->id;
            $seen[$key] = true;
            $item = $existing->get($key);
            if ($item && ($item->agent_code !== $agentCode || $item->isFrozen())) {
                continue;
            }
            $item ??= new AgCommissionItem([
                'agent_code' => $agentCode,
                'kind'       => AgCommissionItem::KIND_FINDER,
                'source'     => 'auto',
                'source_key' => $key,
            ]);

            $months = self::contractMonths($booking);
            $factor = self::finderMonths($months);
            $rent = (float) $booking->monthly_rent;
            $checkIn = $booking->check_in ? Carbon::parse($booking->check_in) : null;
            $movedIn = in_array($booking->status, self::MOVED_IN, true) && $checkIn && $checkIn->lte(now());

            $item->fill([
                'property_id'   => $property->id,
                'property_code' => $property->property_code,
                'booking_id'    => $booking->id,
                'period'        => $checkIn ? $checkIn->format('Y-m') : now()->format('Y-m'),
                'description'   => 'สัญญา '.self::contractLabel($months),
                'base_amount'   => round($rent, 2),
                'factor'        => $factor,
                'calc_amount'   => round($rent * $factor, 2),
                'due_date'      => $checkIn?->toDateString(),
                'state'         => $movedIn ? 'ready' : 'waiting',
                'wait_reason'   => $movedIn ? null : 'รอผู้เช่าเข้าใช้งาน',
                'ready_at'      => $movedIn ? $checkIn->copy()->startOfDay() : null,
            ]);
            self::persist($item);
        }

        // การจองที่ยกเลิก/เปลี่ยนตัวแทนไปแล้ว (ยังไม่ได้โอน) = ไม่มีค่าหาผู้เช่า
        foreach ($open as $key => $item) {
            if (! isset($seen[$key])) {
                $item->delete();
            }
        }
    }


    /**
     * การจองที่ผู้เช่าคนเดิมเคยเช่าทรัพย์เดียวกันมาก่อน (ต่อสัญญา หรือสัญญายาวที่แยกเป็นช่วง เช่น R08 ช่วงละ 3 ปี)
     * = ไม่ใช่การหาผู้เช่าใหม่ ไม่มีค่าหาผู้เช่า (เหมือน AgentCommissionService::renewalIds() ของ happyest)
     *
     * @return list<int>
     */
    private static function renewalIds(Collection $bookings): array
    {
        if ($bookings->isEmpty()) {
            return [];
        }
        $history = HrBooking::query()->whereIn('property_id', $bookings->pluck('property_id')->unique())
            ->whereNotIn('status', ['cancelled', 'rejected'])->whereNotNull('customer_id')->whereNotNull('check_in')
            ->get(['id', 'property_id', 'customer_id', 'check_in'])->groupBy(fn ($b) => $b->property_id.'|'.$b->customer_id);
        $ids = [];
        foreach ($bookings as $b) {
            $earlier = ($history[$b->property_id.'|'.$b->customer_id] ?? collect())
                ->contains(fn ($o) => (int) $o->id !== (int) $b->id && Carbon::parse($o->check_in)->lt(Carbon::parse($b->check_in)));
            if ($b->customer_id && $earlier) {
                $ids[] = (int) $b->id;
            }
        }

        return $ids;
    }

    /** อายุสัญญาเป็นเดือนเต็มจากวันเริ่ม-วันสิ้นสุด (1 ก.ย. 69 - 31 ส.ค. 70 = 12) ไม่มีวันที่ใช้ rental_months */
    public static function contractMonths(HrBooking $booking): int
    {
        if ($booking->check_in && $booking->check_out) {
            $in = Carbon::parse($booking->check_in)->startOfDay();
            $out = Carbon::parse($booking->check_out)->startOfDay()->addDay();
            $months = 0;
            while ($in->copy()->addMonthsNoOverflow($months + 1)->lte($out)) {
                $months++;
                if ($months > 600) {
                    break;
                }
            }

            return $months;
        }

        return (int) $booking->rental_months;
    }

    public static function contractLabel(int $months): string
    {
        if ($months > 0 && $months % 12 === 0) {
            return ($months / 12).' ปี';
        }
        if ($months > 12) {
            return intdiv($months, 12).' ปี '.($months % 12).' เดือน';
        }

        return $months.' เดือน';
    }

    private static function persist(AgCommissionItem $item): void
    {
        if ($item->exists && ! $item->isDirty()) {
            return;
        }
        try {
            $item->save();
        } catch (QueryException $e) {
            // เปิดหน้าพร้อมกันสองแท็บ: อีกคำขอสร้าง source_key เดียวกันไปก่อน - ข้ามได้
            if (! str_contains($e->getMessage(), 'Duplicate')) {
                throw $e;
            }
        }
    }

    // ─── งวด ───────────────────────────────────────────────────────────────────

    public static function nextMonth(string $month): string
    {
        return Carbon::createFromFormat('Y-m-d', $month.'-01')->addMonthNoOverflow()->format('Y-m');
    }

    /**
     * งวดที่รายการนี้ได้รับ: งวดที่โอนแล้ว (ตรึงไว้) หรือ max(เดือนของรายการ, เดือนที่พร้อมจ่าย, START_PERIOD)
     * ถ้างวดนั้นโอนไปแล้ว เลื่อนไปงวดถัดไปที่ยังไม่โอน
     */
    public static function statementMonth(AgCommissionItem $item, array $payoutMonthById, array $paidMonths): ?string
    {
        if ($item->payout_id) {
            return $payoutMonthById[$item->payout_id] ?? null;
        }
        if ($item->state !== 'ready') {
            return null;
        }
        $month = max((string) $item->period, self::START_PERIOD, $item->ready_at ? $item->ready_at->format('Y-m') : (string) $item->period);
        $guard = 0;
        while (isset($paidMonths[$month]) && $guard++ < 240) {
            $month = self::nextMonth($month);
        }

        return $month;
    }

    /**
     * ข้อมูลทั้งหมดของหน้า: รายการ + งวด + ยอดรวม (เรียก sync() ก่อน)
     *
     * @return array{items: Collection, payouts: Collection, months: array, waiting: Collection}
     */
    public static function book(string $agentCode): array
    {
        $items = AgCommissionItem::query()->where('agent_code', $agentCode)
            ->orderBy('period')->orderBy('property_code')->orderBy('id')->get();
        $payouts = AgCommissionPayout::query()->where('agent_code', $agentCode)->live()->get()->keyBy('month');
        $payoutMonthById = $payouts->mapWithKeys(fn ($p) => [$p->id => $p->month])->all();
        $paidMonths = $payouts->mapWithKeys(fn ($p) => [$p->month => true])->all();

        $current = now()->format('Y-m');
        $months = [];
        foreach ($items as $item) {
            $m = self::statementMonth($item, $payoutMonthById, $paidMonths);
            $item->setAttribute('stmt_month', $m);
            if ($m !== null) {
                $months[$m] ??= [];
                $months[$m][] = $item;
            }
        }

        // งวดตั้งแต่ START_PERIOD ถึงเดือนนี้ (หรือถึงงวดล่าสุดที่มีรายการ) เรียงใหม่สุดก่อน
        $last = max($current, $months ? max(array_keys($months)) : $current, $payouts->keys()->max() ?? $current);
        $list = [];
        for ($m = self::START_PERIOD; $m <= $last; $m = self::nextMonth($m)) {
            $rows = collect($months[$m] ?? []);
            $payout = $payouts->get($m);
            $gross = round($rows->filter->counts()->sum(fn ($i) => $i->amount()), 2);
            $wht = $gross > 0 ? round($gross * self::WHT_RATE / 100, 2) : 0.0;
            $list[$m] = [
                'month'   => $m,
                'items'   => $rows,
                'payout'  => $payout,
                'gross'   => $payout ? (float) $payout->gross : $gross,
                'wht'     => $payout ? (float) $payout->wht_amount : $wht,
                'wht_rate'=> $payout ? (float) $payout->wht_rate : self::WHT_RATE,
                'net'     => $payout ? (float) $payout->net : round($gross - $wht, 2),
                'count'   => $rows->filter->counts()->count(),
                'is_current' => $m === $current,
                'is_future'  => $m > $current,
            ];
        }
        krsort($list);

        $waiting = $items->filter(fn ($i) => $i->state === 'waiting' && ! $i->payout_id)
            ->sortBy(fn ($i) => $i->period.'|'.$i->property_code)->values();

        return [
            'items'   => $items,
            'payouts' => $payouts,
            'months'  => $list,
            'waiting' => $waiting,
        ];
    }


    // ─── แสดงผล ────────────────────────────────────────────────────────────────

    public static function monthLabel(?string $month, bool $long = false): string
    {
        if (! $month || ! preg_match('/^(\d{4})-(\d{2})$/', $month, $m)) {
            return '-';
        }
        $short = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
        $full = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
        $y = (int) $m[1] + 543;

        return $long ? $full[(int) $m[2] - 1].' '.$y : $short[(int) $m[2] - 1].' '.substr((string) $y, -2);
    }

    public static function dateLabel($date): string
    {
        if (! $date) {
            return '-';
        }
        $d = $date instanceof Carbon ? $date : Carbon::parse($date);
        $short = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];

        return $d->day.' '.$short[$d->month - 1].' '.substr((string) ($d->year + 543), -2);
    }

    public static function money(float $v): string
    {
        return number_format($v, 2);
    }

    /** % แบบไม่มีศูนย์ท้าย (2.500 -> 2.5) */
    public static function pct(float $v): string
    {
        return rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.');
    }

    private static function hasColumn(string $table, string $column): bool
    {
        $k = $table.'.'.$column;
        if (! array_key_exists($k, self::$columnCache)) {
            try {
                self::$columnCache[$k] = Schema::hasColumn($table, $column);
            } catch (\Throwable $e) {
                self::$columnCache[$k] = false;
            }
        }

        return self::$columnCache[$k];
    }
}
