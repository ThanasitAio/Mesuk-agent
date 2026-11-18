<?php

namespace App\Http\Controllers;

use App\Helpers\CommissionLedger as L;
use App\Models\HrAgent;
use App\Models\HrProperty;
use Illuminate\Http\Request;

/**
 * ค่าคอมมิชชั่น (งาน #163) - ผู้บริหารโครงการ/ตัวแทนดูของตัวเองทีละงวด พร้อมที่มาของทุกตัวเลข (ดูอย่างเดียว)
 * รายการคำนวณที่ happyest (agent-commissions:sync ทุก 15 นาที + หน้าแอดมิน) หน้านี้ไม่คำนวณเอง
 * ฝ่ายบัญชีแก้ยอด เพิ่ม/หักรายการ ตั้งอัตรา เปิดใบ และบันทึกการโอน ที่หลังบ้าน happyest /admin/agent-commissions
 */
class CommissionController extends Controller
{
    public function index(Request $request)
    {
        $agentCode = (string) session('agent_code');
        $agent = HrAgent::query()->where('agent_code', $agentCode)->first();

        $book = L::book($agentCode);
        $months = $book['months'];

        $month = (string) $request->get('month', '');
        if (! isset($months[$month])) {
            $month = $this->defaultMonth($months);
        }

        $propIds = $book['items']->pluck('property_id')->filter()->unique();
        $properties = $propIds->isEmpty() ? collect()
            : HrProperty::query()->whereIn('id', $propIds)->get(['id', 'property_code', 'title'])->keyBy('id');

        return view('commissions.index', [
            'agent'        => $agent,
            'agentCode'    => $agentCode,
            'months'       => $months,
            'month'        => $month,
            'stmt'         => $months[$month],
            'waiting'      => $book['waiting'],
            'waitingTotal' => round($book['waiting']->filter->counts()->sum(fn ($i) => $i->amount()), 2),
            'paidTotal'    => round((float) $book['payouts']->filter->isPaid()->sum('net'), 2),
            'properties'   => $properties,
            'viaAdmin'     => L::viaAdmin(),
            'rates'        => L::rates($agentCode),
            'syncedAt'     => L::syncedAt(),
        ]);
    }

    /** งวดที่เปิดมาครั้งแรก: งวดล่าสุดที่ยังไม่โอนและมีรายการ (ก่อนเดือนนี้) ไม่มีก็เดือนนี้ */
    private function defaultMonth(array $months): string
    {
        $current = now()->format('Y-m');
        foreach ($months as $m => $s) {
            if ($m < $current && ! ($s['payout'] && $s['payout']->isPaid()) && $s['items']->isNotEmpty()) {
                return $m;
            }
        }

        return isset($months[$current]) ? $current : (string) array_key_first($months);
    }
}
