<?php

namespace App\Helpers;

use App\Models\AgCommissionItem;
use App\Models\AgCommissionPayout;
use App\Models\AgCommissionRate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ค่าคอมมิชชั่นของผู้บริหารโครงการ/ตัวแทน (งาน #163) - แอปนี้แสดงผลอย่างเดียว
 *
 * รายการทุกบรรทัดคำนวณที่ happyest ที่เดียว (App\Services\AgentCommissionService: หน้า /admin/agent-commissions
 * + คำสั่ง agent-commissions:sync ทุก 15 นาที) ตามกระดาษ FM-MKT-03-13 + บันทึก 6 ต.ค. 69 (ผู้ใช้ตัดสิน 2026-11-18):
 *  - ค่าบริหารโครงการ (โครงการบริหาร) % ของค่าเช่าทุกเดือน · ค่านำทรัพย์ (ฝากทรัพย์) 2.5% · ค่าหาผู้เช่ารายเดือน 2.0/2.5%
 *  - ค่าหาผู้เช่าครั้งเดียว (โครงการบริษัท) 1/2 เดือน หรือ เดือน x 5% · ค่าหัวหน้าทีม 0.5%
 *  - งวดที่ได้รับ = เดือนที่พร้อมจ่าย (ไม่ก่อนเดือนของรายการ) ถ้างวดนั้นเปิดใบแล้ว ขยับไปงวดถัดไป · หัก ณ ที่จ่าย WHT_RATE%
 * ที่นี่เหลือเฉพาะการจัดงวด/แสดงผล (statementMonth ต้องตรงกับ happyest) - ห้ามเพิ่มการคำนวณกลับมาที่นี่
 */
class CommissionLedger
{
    /** งวดแรกของระบบ (ก่อนหน้านี้ฝ่ายบัญชีดึงรายการค้างเข้ามาเองเป็น "ยอดยกมา") */
    public const START_PERIOD = '2026-11';

    /** % ค่าบริหารโครงการเริ่มต้น (แบบฟอร์ม "โครงการบริหารโกดังเช่า" 2.0%) - ตรงกับ AgentCommissionService::DEFAULT_RATE */
    public const DEFAULT_RATE = 2.0;

    /** หัก ณ ที่จ่ายค่านายหน้า (6,808.80 -> 204.26) */
    public const WHT_RATE = 3.0;

    /** % ของสัญญาไม่ถึง 1 ปี (ค่าหาผู้เช่าครั้งเดียว) - ตรงกับ AgentCommissionService::SHORT_LEASE_RATE */
    public const SHORT_LEASE_RATE = 5.0;

    /** % ค่าหัวหน้าทีม (ก้อนเดียว = ค่าเช่า x เดือนตามสัญญา x %) - ตรงกับ AgentCommissionService::LEADER_RATE */
    public const LEADER_RATE = 0.5;

    /** เวลาที่ happyest คำนวณล่าสุด (hr_settings) */
    public const SYNCED_AT_KEY = 'agent_commission.synced_at';

    /** ชื่อ/คำอธิบาย/สี ของแต่ละประเภท เรียงตามลำดับที่แสดง - ตรงกับ App\Models\AgentCommissionItem::KINDS ของ happyest */
    public const KINDS = [
        'management'     => ['label' => 'ค่าบริหารโครงการ', 'hint' => 'ได้รับเมื่อผู้เช่าชำระค่าเช่าเดือนนั้น', 'tone' => 'brand'],
        'lister'         => ['label' => 'ค่านำทรัพย์มาฝาก', 'hint' => 'ได้รับเมื่อผู้เช่าชำระค่าเช่าเดือนนั้น ตลอดสัญญา', 'tone' => 'amber'],
        'finder_monthly' => ['label' => 'ค่าหาผู้เช่า (รายเดือน)', 'hint' => 'ได้รับเมื่อผู้เช่าชำระค่าเช่าเดือนนั้น ตลอดสัญญา', 'tone' => 'violet'],
        'finder'         => ['label' => 'ค่าหาผู้เช่า', 'hint' => 'ได้รับครั้งเดียวเมื่อผู้เช่าเข้าใช้งาน', 'tone' => 'violet'],
        'leader_once'    => ['label' => 'ค่าหัวหน้าทีม', 'hint' => 'ได้รับครั้งเดียวเมื่อผู้เช่าที่ลูกทีมหามาเข้าใช้งาน', 'tone' => 'teal'],
        'leader'         => ['label' => 'ค่าหัวหน้าทีม (รายเดือน)', 'hint' => 'ได้รับเมื่อผู้เช่าที่ลูกทีมหามาชำระค่าเช่าเดือนนั้น ตลอดสัญญา', 'tone' => 'teal'],
        'manual'         => ['label' => 'ปรับยอดโดยฝ่ายบัญชี', 'hint' => 'บวกหรือหักตามที่ฝ่ายบัญชีแจ้ง', 'tone' => 'sky'],
    ];

    /** ประเภทที่คิดเป็น % ของบิลค่าเช่ารายเดือน */
    public const RENT_KINDS = ['management', 'finder_monthly', 'lister', 'leader'];

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

    /** [property_id => rate] ของคนนี้ (0 = ทุกทรัพย์) - ฝ่ายบัญชีตั้งที่ happyest */
    public static function rates(string $agentCode): array
    {
        return AgCommissionRate::query()->where('agent_code', $agentCode)->pluck('rate', 'property_id')
            ->map(fn ($r) => (float) $r)->all();
    }

    public static function rateFor(array $rates, ?int $propertyId): float
    {
        return (float) ($rates[(int) $propertyId] ?? $rates[0] ?? self::DEFAULT_RATE);
    }

    /** เวลาที่ happyest คำนวณรายการของทุกคนล่าสุด (null = ยังไม่เคย) */
    public static function syncedAt(): ?Carbon
    {
        try {
            $v = DB::table('hr_settings')->where('key', self::SYNCED_AT_KEY)->value('value');

            return $v ? Carbon::parse($v) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ─── งวด ───────────────────────────────────────────────────────────────────

    public static function nextMonth(string $month): string
    {
        return Carbon::createFromFormat('Y-m-d', $month.'-01')->addMonthNoOverflow()->format('Y-m');
    }

    /**
     * งวดที่รายการนี้ได้รับ: งวดที่เปิดใบแล้ว (ตรึงไว้) หรือ max(เดือนของรายการ, เดือนที่พร้อมจ่าย, START_PERIOD)
     * ถ้างวดนั้นเปิดใบไปแล้ว เลื่อนไปงวดถัดไป (เหมือน AgentCommissionService::statementMonth ของ happyest)
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
     * ข้อมูลทั้งหมดของหน้า: รายการ + งวด + ยอดรวม (รายการมาจาก happyest แล้ว)
     *
     * @return array{items: \Illuminate\Support\Collection, payouts: \Illuminate\Support\Collection, months: array, waiting: \Illuminate\Support\Collection}
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

    /**
     * [หัวข้อ, วิธีคิด] ของรายการ - เหมือน AgentCommissionItem::describe() ของ happyest
     *
     * @return array{0: string, 1: ?string}
     */
    public static function describe(AgCommissionItem $item): array
    {
        $base = self::money((float) $item->base_amount);
        $factor = (float) $item->factor;
        if (in_array($item->kind, self::RENT_KINDS, true)) {
            return ['ค่าเช่า '.self::monthLabel($item->period), $base.' x '.self::pct($factor).'%'];
        }
        if ($item->kind === 'finder') {
            $head = 'ค่าหาผู้เช่า · '.($item->description ?: 'สัญญาเช่า');
            if ($factor >= 1) {
                return [$head, 'ค่าเช่า '.$base.' x '.self::pct($factor).' เดือน'];
            }
            if ($factor > 0) {
                return [$head, 'ค่าเช่า '.$base.' x '.(int) round($factor * 100 / self::SHORT_LEASE_RATE).' เดือน x '.self::pct(self::SHORT_LEASE_RATE).'%'];
            }

            return [$head, 'สัญญาไม่ถึง 1 เดือน ไม่มีค่าหาผู้เช่า'];
        }
        if ($item->kind === 'leader_once') {
            // factor = เดือนตามสัญญา x 0.5 / 100 (36 เดือน = 0.18)
            return ['ค่าหัวหน้าทีม · '.($item->description ?: 'สัญญาเช่า'),
                'ค่าเช่า '.$base.' x '.(int) round($factor * 100 / self::LEADER_RATE).' เดือน x '.self::pct(self::LEADER_RATE).'%'];
        }

        return [$item->description ?: 'รายการปรับยอด', null];
    }

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
}
