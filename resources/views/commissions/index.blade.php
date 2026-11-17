@extends('layouts.app')

@section('title', 'ค่าคอมมิชชั่น')
@section('breadcrumb', 'ค่าบริหารโครงการและค่าหาผู้เช่า แยกที่มาทุกรายการ')

@section('content')
@php
    use App\Helpers\CommissionLedger as L;

    $items      = $stmt['items'];
    $groups     = [
        'management' => ['label' => 'ค่าบริหารโครงการ', 'hint' => 'ได้รับเมื่อผู้เช่าชำระค่าเช่าเดือนนั้น', 'tone' => 'brand'],
        'finder'     => ['label' => 'ค่าหาผู้เช่า', 'hint' => 'ได้รับเมื่อผู้เช่าเข้าใช้งาน', 'tone' => 'violet'],
        'manual'     => ['label' => 'ปรับยอดโดยฝ่ายบัญชี', 'hint' => 'บวกหรือหักตามที่ฝ่ายบัญชีแจ้ง', 'tone' => 'sky'],
    ];
    $byKind     = $items->groupBy('kind');
    $sumOf      = fn ($rows) => round(collect($rows)->filter->counts()->sum(fn ($i) => $i->amount()), 2);
    $payout     = $stmt['payout'];
    $isCurrent  = $stmt['is_current'];
    $agentName  = $agent ? trim(($agent->prefix ? $agent->prefix.' ' : '').$agent->name) : $agentCode;
    $defaultRate = L::rateFor($rates, null);
    $bankNo     = $agent && $agent->bank_account_no ? preg_replace('/\D/', '', (string) $agent->bank_account_no) : '';
    $isPaid     = $payout && $payout->isPaid();
@endphp

{{-- แอดมินที่เข้าผ่านปุ่ม SSO: แก้ยอด/เปิดใบ/บันทึกโอน ทำที่หลังบ้าน (หน้านี้ดูอย่างเดียว) --}}
@if($viaAdmin)
<a href="{{ L::adminUrl($agentCode, $month) }}" target="_blank" rel="noopener"
   class="mb-4 flex items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 hover:bg-amber-100 transition-colors">
    <svg class="w-5 h-5 text-amber-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
    <span class="flex-1 min-w-0">หน้านี้คือสิ่งที่ผู้บริหารโครงการเห็น แก้ยอด เปิดใบ และบันทึกโอน ทำที่ <b>หลังบ้าน &gt; ค่าคอมมิชชั่นตัวแทน</b></span>
</a>
@endif

{{-- ── เลือกงวด ─────────────────────────────────────────────────────────────── --}}
<div class="mb-4">
    <p class="text-xs font-semibold text-gray-500 mb-2 px-0.5">งวดที่ได้รับ</p>
    <div class="cm-months flex gap-2 overflow-x-auto pb-1 -mx-1 px-1">
        @foreach($months as $m => $s)
            @php
                $active = $m === $month;
                $chipState = $s['payout'] ? ($s['payout']->isPaid() ? 'paid' : 'issued') : ($s['is_current'] ? 'current' : ($s['items']->isNotEmpty() ? 'due' : 'empty'));
                $params = ['month' => $m];
            @endphp
            <a href="{{ route('commissions.index', $params) }}"
               class="cm-chip flex-shrink-0 min-w-[7.25rem] rounded-2xl border px-3.5 py-2.5 transition-colors
                      {{ $active ? 'bg-brand-800 border-brand-800 text-white' : 'bg-white border-gray-200 text-gray-700 hover:border-brand-300' }}">
                <span class="flex items-center justify-between gap-2">
                    <span class="text-sm font-bold">{{ L::monthLabel($m) }}</span>
                    @if($chipState === 'paid')
                        <svg class="w-4 h-4 {{ $active ? 'text-brand-200' : 'text-emerald-600' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.4" d="M5 13l4 4L19 7"/></svg>
                    @elseif($chipState === 'current')
                        <span class="w-2 h-2 rounded-full {{ $active ? 'bg-brand-200' : 'bg-brand-500' }} animate-pulse"></span>
                    @elseif($chipState === 'due' || $chipState === 'issued')
                        <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                    @endif
                </span>
                <span class="block mt-0.5 text-xs tabular-nums {{ $active ? 'text-brand-100' : 'text-gray-500' }}">
                    {{ $s['items']->isNotEmpty() || $s['payout'] ? '฿'.L::money($s['net']) : 'ไม่มีรายการ' }}
                </span>
                <span class="block text-[11px] {{ $active ? 'text-brand-200' : 'text-gray-400' }}">
                    {{ ['paid' => 'โอนแล้ว', 'issued' => 'เปิดใบแล้ว รอโอน', 'current' => 'กำลังสะสม', 'due' => 'รอเปิดใบ', 'empty' => '-'][$chipState] }}
                </span>
            </a>
        @endforeach
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 lg:gap-6 items-start">

    {{-- ══ ซ้าย: งวดที่เลือก ══════════════════════════════════════════════════ --}}
    <div class="lg:col-span-2 space-y-4 min-w-0">

        {{-- สรุปงวด --}}
        <div class="cm-hero relative overflow-hidden rounded-2xl p-5 sm:p-6 text-white">
            <div class="relative flex flex-wrap items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-xs font-medium text-white/60">{{ $payout && $payout->doc_no ? 'ใบค่าคอม '.$payout->doc_no : 'ค่าคอมมิชชั่นของคุณ' }}</p>
                    <h2 class="text-lg sm:text-xl font-bold leading-tight mt-0.5">งวด{{ L::monthLabel($month, true) }}</h2>
                </div>
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold {{ $isPaid ? 'text-emerald-200' : ($payout ? 'text-amber-200' : ($isCurrent ? 'text-lime-200' : 'text-amber-200')) }}">
                    @if($isPaid)
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.4" d="M5 13l4 4L19 7"/></svg>
                        โอนแล้ว {{ L::dateLabel($payout->paid_on) }}
                    @elseif($payout)
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        เปิดใบแล้ว {{ L::dateLabel($payout->issued_at) }} รอโอน
                    @elseif($isCurrent)
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        กำลังสะสม ยอดเพิ่มได้ถึงสิ้นเดือน
                    @elseif($stmt['is_future'])
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        งวดล่วงหน้า
                    @else
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        รอฝ่ายบัญชีเปิดใบ
                    @endif
                </span>
            </div>

            <div class="relative mt-5">
                <p class="text-xs text-white/60">ยอดโอนสุทธิ</p>
                <p class="cm-net font-black tabular-nums leading-none mt-1">฿{{ L::money($stmt['net']) }}</p>
            </div>

            <div class="relative mt-5 grid grid-cols-3 gap-2 pt-4 border-t border-white/15 text-center">
                <div>
                    <p class="text-sm sm:text-base font-bold tabular-nums">{{ L::money($stmt['gross']) }}</p>
                    <p class="text-[11px] text-white/60 mt-0.5">รวมค่าคอม</p>
                </div>
                <div class="border-x border-white/15">
                    <p class="text-sm sm:text-base font-bold tabular-nums">-{{ L::money($stmt['wht']) }}</p>
                    <p class="text-[11px] text-white/60 mt-0.5">หัก ณ ที่จ่าย {{ L::pct($stmt['wht_rate']) }}%</p>
                </div>
                <div>
                    <p class="text-sm sm:text-base font-bold tabular-nums">{{ number_format($stmt['count']) }}</p>
                    <p class="text-[11px] text-white/60 mt-0.5">รายการ</p>
                </div>
            </div>
        </div>

        {{-- รายการในงวด แยกตามประเภท --}}
        @foreach($groups as $kind => $g)
            @continue(! isset($byKind[$kind]))
            @php $rows = $byKind[$kind]; @endphp
            <section class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <header class="flex items-center justify-between gap-3 px-4 sm:px-5 py-3 border-b border-gray-100">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <span class="w-8 h-8 rounded-xl flex items-center justify-center flex-shrink-0
                                     {{ $g['tone'] === 'brand' ? 'bg-brand-50 text-brand-700' : ($g['tone'] === 'violet' ? 'bg-violet-50 text-violet-700' : 'bg-sky-50 text-sky-700') }}">
                            @if($kind === 'management')
                                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            @elseif($kind === 'finder')
                                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                            @else
                                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                            @endif
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-gray-800">{{ $g['label'] }} <span class="font-normal text-gray-400">· {{ $rows->count() }} รายการ</span></p>
                            <p class="text-xs text-gray-400 truncate">{{ $g['hint'] }}</p>
                        </div>
                    </div>
                    <p class="text-sm font-bold tabular-nums text-gray-900 flex-shrink-0">{{ L::money($sumOf($rows)) }}</p>
                </header>
                <div class="divide-y divide-gray-100">
                    @foreach($rows as $item)
                        @include('commissions.partials.item-row', ['item' => $item, 'stmtMonth' => $month])
                    @endforeach
                </div>
            </section>
        @endforeach

        @if($items->isEmpty())
            <div class="bg-white rounded-2xl border border-dashed border-gray-200 px-6 py-10 text-center">
                <div class="w-14 h-14 mx-auto rounded-2xl bg-brand-50 text-brand-600 flex items-center justify-center">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                </div>
                <p class="mt-3 text-sm font-semibold text-gray-700">งวดนี้ยังไม่มีรายการที่ได้รับ</p>
                <p class="mt-1 text-xs text-gray-500 leading-relaxed max-w-sm mx-auto">
                    รายการจะเข้ามาในงวดนี้เองทันทีที่ผู้เช่าชำระค่าเช่าหรือเข้าใช้งาน ดูรายการที่รออยู่ได้ที่ "รอผู้เช่าชำระ"
                </p>
            </div>
        @endif

        {{-- สรุปการคำนวณ + การโอน --}}
        @if($items->isNotEmpty() || $payout)
            <section class="bg-white rounded-2xl border border-gray-100 shadow-sm px-4 sm:px-5 py-4">
                <p class="text-sm font-bold text-gray-800 mb-3">สรุปยอดงวด{{ L::monthLabel($month, true) }}</p>
                <dl class="space-y-2 text-sm">
                    @foreach($groups as $kind => $g)
                        @if(isset($byKind[$kind]))
                            <div class="flex justify-between gap-3">
                                <dt class="text-gray-500">{{ $g['label'] }}</dt>
                                <dd class="tabular-nums text-gray-800">{{ L::money($sumOf($byKind[$kind])) }}</dd>
                            </div>
                        @endif
                    @endforeach
                    <div class="flex justify-between gap-3 pt-2 border-t border-gray-100">
                        <dt class="font-semibold text-gray-700">รวมค่าคอม</dt>
                        <dd class="font-semibold tabular-nums text-gray-900">{{ L::money($stmt['gross']) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500">หัก ณ ที่จ่าย {{ L::pct($stmt['wht_rate']) }}%</dt>
                        <dd class="tabular-nums text-rose-600">-{{ L::money($stmt['wht']) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3 pt-2 border-t border-gray-100">
                        <dt class="font-bold text-gray-900">ยอดโอนสุทธิ</dt>
                        <dd class="font-bold tabular-nums text-brand-700 text-base">{{ L::money($stmt['net']) }}</dd>
                    </div>
                </dl>

                @if($isPaid)
                    <div class="mt-4 rounded-xl bg-emerald-50 px-3.5 py-3 text-xs text-emerald-900 leading-relaxed">
                        <p class="font-semibold flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M5 13l4 4L19 7"/></svg>
                            โอนแล้ววันที่ {{ L::dateLabel($payout->paid_on) }} · ใบ {{ $payout->doc_no }}
                        </p>
                        @if($payout->paid_note)<p class="mt-1">{{ $payout->paid_note }}</p>@endif
                        <p class="mt-1 text-emerald-800/70">ยอดของงวดนี้ถูกบันทึกไว้แล้ว จะไม่เปลี่ยนตามข้อมูลภายหลัง</p>
                    </div>
                @elseif($payout)
                    <div class="mt-4 rounded-xl bg-amber-50 px-3.5 py-3 text-xs text-amber-900 leading-relaxed">
                        <p class="font-semibold">ฝ่ายบัญชีเปิดใบ {{ $payout->doc_no }} แล้ว รอโอนเข้าบัญชี</p>
                        <p class="mt-1 text-amber-800/80">ยอดในใบนี้ถูกตรึงไว้ รายการที่ผู้เช่าชำระหลังเปิดใบจะไปอยู่งวดถัดไป</p>
                    </div>
                @endif
            </section>
        @endif
    </div>

    {{-- ══ ขวา: รอผู้เช่าชำระ + วิธีคิด ══════════════════════════════════════════ --}}
    <aside class="space-y-4 min-w-0">
        <section class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <header class="px-4 sm:px-5 pt-4 pb-3">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-bold text-gray-800 flex items-center gap-2">
                        <svg class="w-[18px] h-[18px] text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        รอผู้เช่าชำระ
                    </p>
                    <p class="text-sm font-bold tabular-nums text-amber-700">{{ L::money($waitingTotal) }}</p>
                </div>
                <p class="mt-1 text-xs text-gray-500 leading-relaxed">
                    {{ $waiting->count() }} รายการ ยังไม่ถึงรอบจ่าย จะย้ายเข้างวดที่ผู้เช่าชำระเองอัตโนมัติ ไม่ต้องติดตามเอง
                </p>
            </header>
            @if($waiting->isNotEmpty())
                <div class="divide-y divide-gray-100 border-t border-gray-100">
                    @foreach($waiting->take(6) as $item)
                        @include('commissions.partials.item-row', ['item' => $item, 'stmtMonth' => null])
                    @endforeach
                </div>
                @if($waiting->count() > 6)
                    <details class="cm-more border-t border-gray-100">
                        <summary class="px-4 sm:px-5 py-3 text-xs font-semibold text-brand-700 cursor-pointer select-none hover:bg-gray-50">
                            ดูอีก {{ $waiting->count() - 6 }} รายการ
                        </summary>
                        <div class="divide-y divide-gray-100 border-t border-gray-100">
                            @foreach($waiting->slice(6) as $item)
                                @include('commissions.partials.item-row', ['item' => $item, 'stmtMonth' => null])
                            @endforeach
                        </div>
                    </details>
                @endif
            @else
                <p class="px-4 sm:px-5 pb-4 text-xs text-emerald-700 flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M5 13l4 4L19 7"/></svg>
                    ไม่มีรายการค้าง
                </p>
            @endif
        </section>

        <section class="bg-white rounded-2xl border border-gray-100 shadow-sm px-4 sm:px-5 py-4">
            <p class="text-sm font-bold text-gray-800 mb-3">คิดค่าคอมอย่างไร</p>
            <ol class="space-y-3 text-xs text-gray-600 leading-relaxed">
                <li class="flex gap-2.5">
                    <span class="w-5 h-5 rounded-full bg-brand-50 text-brand-700 text-[11px] font-bold flex items-center justify-center flex-shrink-0">1</span>
                    <span><b class="text-gray-800">ค่าบริหารโครงการ</b> = {{ L::pct($defaultRate) }}% ของค่าเช่าแต่ละเดือนของทรัพย์ที่ดูแล (ไม่รวม VAT และภาษีที่ดิน) เดือนแรกที่เช่าไม่เต็มเดือนคิดตามวันเช่าจริง</span>
                </li>
                <li class="flex gap-2.5">
                    <span class="w-5 h-5 rounded-full bg-brand-50 text-brand-700 text-[11px] font-bold flex items-center justify-center flex-shrink-0">2</span>
                    <span>ได้รับใน<b class="text-gray-800">งวดของเดือนที่ผู้เช่าชำระ</b> ถ้าผู้เช่าจ่ายช้า รายการยังอยู่ใน "รอผู้เช่าชำระ" ไม่หายไปไหน และเข้างวดถัดไปเองเมื่อชำระ</span>
                </li>
                <li class="flex gap-2.5">
                    <span class="w-5 h-5 rounded-full bg-violet-50 text-violet-700 text-[11px] font-bold flex items-center justify-center flex-shrink-0">3</span>
                    <span><b class="text-gray-800">ค่าหาผู้เช่า</b> เมื่อผู้เช่าที่คุณหามาเข้าใช้งาน: สัญญา 1 ปีขึ้นไป ได้ค่าเช่า 1 เดือน สัญญา 3 ปีขึ้นไป ได้ค่าเช่า 2 เดือน</span>
                </li>
                <li class="flex gap-2.5">
                    <span class="w-5 h-5 rounded-full bg-gray-100 text-gray-600 text-[11px] font-bold flex items-center justify-center flex-shrink-0">4</span>
                    <span>หักภาษี ณ ที่จ่าย {{ L::pct(L::WHT_RATE) }}% จากยอดรวมของงวด ที่เหลือคือยอดโอนสุทธิ</span>
                </li>
            </ol>
            @if(count(array_diff_key($rates, [0 => true])) > 0)
                <p class="mt-3 text-[11px] text-gray-400">บางทรัพย์ใช้อัตราเฉพาะตามที่ตกลงไว้ อัตราของแต่ละรายการแสดงอยู่ใต้รายการนั้น</p>
            @endif
        </section>

        <section class="bg-white rounded-2xl border border-gray-100 shadow-sm px-4 sm:px-5 py-4">
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-bold text-gray-800">บัญชีรับเงิน</p>
                    <a href="{{ route('profile') }}" class="text-xs font-semibold text-brand-700 hover:text-brand-800">แก้ไข</a>
            </div>
            @if($bankNo !== '')
                <p class="mt-2 text-sm text-gray-700">{{ $agent->bank_name ?: 'ธนาคาร' }}</p>
                <p class="text-xs text-gray-500 font-mono">{{ str_repeat('x', max(0, strlen($bankNo) - 4)).substr($bankNo, -4) }} · {{ $agent->bank_account_name }}</p>
            @else
                <p class="mt-2 text-xs text-amber-700 leading-relaxed">ยังไม่มีบัญชีรับเงิน เพิ่มได้ที่หน้าโปรไฟล์ เพื่อให้ฝ่ายบัญชีโอนได้ทันที</p>
            @endif
            <div class="mt-3 pt-3 border-t border-gray-100 flex justify-between gap-3 text-xs">
                <span class="text-gray-500">โอนแล้วทั้งหมด</span>
                <span class="font-semibold tabular-nums text-gray-800">฿{{ L::money($paidTotal) }}</span>
            </div>
        </section>
    </aside>
</div>

@endsection

@push('styles')
<style>
    .cm-hero { background: linear-gradient(135deg, #1c3514 0%, #2a5a1f 55%, #35702a 100%); }
    .cm-hero::after { content: ''; position: absolute; right: -60px; top: -60px; width: 220px; height: 220px; border-radius: 9999px; background: rgba(154, 216, 114, .12); pointer-events: none; }
    .cm-net { font-size: clamp(2rem, 8vw, 2.75rem); }
    .cm-months { scrollbar-width: thin; scroll-snap-type: x proximity; }
    .cm-chip { scroll-snap-align: start; }
    .cm-more > summary { list-style: none; }
    .cm-more > summary::-webkit-details-marker { display: none; }
    .cm-more[open] > summary { display: none; }
</style>
@endpush

