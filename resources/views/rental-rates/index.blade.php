@extends('layouts.app')

@section('title', 'อัตราเช่า')
@section('breadcrumb', 'ภาพรวมและประสิทธิภาพผู้บริหารโครงการ')

@push('styles')
<style>
@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.animate-slide-in-up {
    animation: slideInUp 0.4s ease-out;
}

.animate-fade-in {
    animation: fadeIn 0.5s ease-out;
}

.stat-card {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(0,0,0,0.1);
}

@media (prefers-reduced-motion: reduce) {
    .animate-slide-in-up,
    .animate-fade-in,
    .stat-card {
        animation: none;
        transition: none;
    }
}
</style>
@endpush

@section('content')

@php
    $happyestPublic = rtrim(env('HAPPYEST_APP_URL', 'http://127.0.0.1/happyest/public'), '/');
    $thM = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    $vacancyRate = $totalProps > 0 ? round($totalVacant / $totalProps * 100, 1) : 0;
@endphp

{{-- ── Hero ────────────────────────────────────────────────────────────────── --}}
<div class="relative overflow-hidden rounded-2xl mb-4"
     style="background:linear-gradient(135deg,#0f2027 0%,#203a43 50%,#2c5364 100%)">

    <div class="pointer-events-none absolute -top-16 -right-16 w-60 h-60 rounded-full"
         style="background:radial-gradient(circle,rgba(99,179,237,.10),transparent)"></div>
    <div class="pointer-events-none absolute -bottom-12 left-1/3 w-48 h-48 rounded-full"
         style="background:radial-gradient(circle,rgba(74,222,128,.07),transparent)"></div>

    <div class="relative p-4 lg:p-5" style="z-index:2">
        <div class="flex items-start gap-3">

            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-3">
                    <h2 class="text-sm font-black text-white tracking-wide">ภาพรวมการเช่า</h2>
                    <span class="text-[10px] px-2 py-0.5 rounded-full" style="background:rgba(255,255,255,.09);color:rgba(255,255,255,.45)">
                        {{ now()->day . ' ' . $thM[now()->month-1] . ' ' . (now()->year+543) }}
                    </span>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    <div class="stat-card rounded-xl p-3 text-center" style="background:rgba(255,255,255,.07)">
                        <p class="text-2xl font-black text-white tabular-nums leading-none">{{ $totalProps }}</p>
                        <p class="text-[10px] mt-1" style="color:rgba(255,255,255,.38)">ทรัพย์ทั้งหมด</p>
                    </div>
                    <div class="stat-card rounded-xl p-3 text-center" style="background:rgba(34,197,94,.13)">
                        <p class="text-2xl font-black tabular-nums leading-none" style="color:#22c55e">{{ $totalVacant }}</p>
                        <p class="text-[10px] mt-1" style="color:rgba(34,197,94,.70)">ว่าง</p>
                    </div>
                    <div class="stat-card rounded-xl p-3 text-center" style="background:rgba(239,68,68,.13)">
                        <p class="text-2xl font-black tabular-nums leading-none" style="color:#ef4444">{{ $totalOccupied }}</p>
                        <p class="text-[10px] mt-1" style="color:rgba(239,68,68,.70)">ไม่ว่าง</p>
                    </div>
                    <div class="stat-card rounded-xl p-3 text-center" style="background:rgba(255,255,255,.07)">
                        <p class="text-2xl font-black text-white tabular-nums leading-none">{{ $byManager->count() }}</p>
                        <p class="text-[10px] mt-1" style="color:rgba(255,255,255,.38)">ผู้บริหาร</p>
                    </div>
                </div>
            </div>

            {{-- Donut with mini legend --}}
            <div class="flex-shrink-0 flex flex-col items-center gap-2 pt-0.5">
                <div class="relative" style="width:78px;height:78px">
                    <canvas id="heroDonut" width="78" height="78"></canvas>
                    <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                        <span class="text-[17px] font-black tabular-nums leading-none" style="color:#22c55e">{{ $vacancyRate }}%</span>
                        <span class="text-[8px] mt-0.5" style="color:rgba(255,255,255,.38)">ว่าง</span>
                    </div>
                </div>
                <div class="flex flex-col gap-0.5">
                    <div class="flex items-center gap-1">
                        <span class="w-2 h-2 rounded-full flex-shrink-0" style="background:#22c55e"></span>
                        <span class="text-[9px] tabular-nums" style="color:rgba(255,255,255,.50)">ว่าง {{ $totalVacant }} อสังหา</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="w-2 h-2 rounded-full flex-shrink-0" style="background:#ef4444"></span>
                        <span class="text-[9px] tabular-nums" style="color:rgba(255,255,255,.50)">ไม่ว่าง {{ $totalOccupied }} อสังหา</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── Charts ───────────────────────────────────────────────────────────────── --}}
@if($byManager->count() > 0)
@php
    $chartData = $byManager->map(fn($m) => [
        'name'     => $m->manager_name,
        'code'     => $m->manager_code,
        'occupied' => $m->occupied_count,
        'vacant'   => $m->vacant_count,
        'rate'     => $m->occupancy_rate,
        'vrate'    => $m->total_props > 0 ? round($m->vacant_count / $m->total_props * 100, 1) : 0,
    ])->values()->toArray();
    $chartH = max(72, $byManager->count() * 40);
@endphp

<div class="grid grid-cols-1 lg:grid-cols-2 gap-3 mb-4">

    {{-- Chart 1: จำนวนอสังหาแต่ละผู้บริหาร --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 animate-fade-in">
        <div class="flex items-start justify-between mb-2">
            <div>
                <h3 class="text-sm font-bold text-gray-800">จำนวนอสังหาแต่ละผู้บริหาร</h3>
                <p class="text-[11px] text-gray-400 mt-0.5">อสังหาทั้งหมดที่ดูแล (แยกตามสถานะ)</p>
            </div>
            <div class="flex items-center gap-2 text-[10px] text-gray-400 flex-shrink-0 ml-2">
                <span class="flex items-center gap-1">
                    <span class="inline-block w-2 h-2 rounded-sm" style="background:#22c55e"></span>ว่าง
                </span>
                <span class="flex items-center gap-1">
                    <span class="inline-block w-2 h-2 rounded-sm" style="background:#ef4444"></span>ไม่ว่าง
                </span>
            </div>
        </div>
        <div style="position:relative;height:{{ $chartH }}px">
            <canvas id="managerChart"></canvas>
        </div>
    </div>

    {{-- Chart 2: เปอร์เซ็นต์อสังหาว่าง --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 animate-fade-in" style="animation-delay: 0.1s">
        <div class="flex items-start justify-between mb-2">
            <div>
                <h3 class="text-sm font-bold text-gray-800">เปอร์เซ็นต์อสังหาว่างแต่ละผู้บริหาร</h3>
                <p class="text-[11px] text-gray-400 mt-0.5">สัดส่วนอสังหาว่างเทียบกับอสังหาทั้งหมด</p>
            </div>
            <div class="flex items-center gap-2 text-[10px] text-gray-400 flex-shrink-0 ml-2">
                <span class="flex items-center gap-1">
                    <span class="inline-block w-2 h-2 rounded-sm" style="background:#22c55e"></span>ว่าง
                </span>
                <span class="flex items-center gap-1">
                    <span class="inline-block w-2 h-2 rounded-sm" style="background:#ef4444"></span>ไม่ว่าง
                </span>
            </div>
        </div>
        <div style="position:relative;height:{{ $chartH }}px">
            <canvas id="rateChart"></canvas>
        </div>
    </div>
</div>
@endif

{{-- ── Manager Cards ─────────────────────────────────────────────────────────── --}}
@if($byManager->isEmpty())
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-10 text-center">
    <div class="w-12 h-12 bg-gray-50 rounded-2xl flex items-center justify-center mx-auto mb-3">
        <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/>
        </svg>
    </div>
    <p class="text-sm font-semibold text-gray-500">ยังไม่มีข้อมูลอสังหาริมทรัพย์</p>
    <p class="text-xs text-gray-400 mt-1">ข้อมูลจะปรากฏเมื่อมีการเพิ่มอสังหาในระบบ</p>
</div>
@else
<div class="space-y-2">
    @foreach($byManager as $idx => $mgr)
    @php
        $rate       = $mgr->occupancy_rate;
        $vRate      = $mgr->total_props > 0 ? round($mgr->vacant_count / $mgr->total_props * 100, 1) : 0;
        $initial    = mb_strtoupper(mb_substr($mgr->manager_name, 0, 1));
        $mgrRevenue = $mgr->properties
            ->filter(fn($p) => $p->is_occupied && $p->booking)
            ->sum(fn($p) => (float)($p->booking->monthly_rent ?? 0));
    @endphp

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-all duration-300 animate-slide-in-up"
         style="animation-delay: {{ $idx * 0.05 }}s"
         x-data="{ open: false }">

        {{-- Accordion header (compact & beautiful) --}}
        <button type="button" @click="open = !open"
                class="w-full text-left px-4 py-3 flex items-center gap-3 hover:bg-gray-50/60 transition-all duration-200">

            {{-- Avatar --}}
            <div class="flex-shrink-0">
                @if($mgr->manager_avatar)
                    <img src="{{ $happyestPublic.'/storage/'.$mgr->manager_avatar }}"
                         alt="{{ $mgr->manager_name }}"
                         class="w-10 h-10 rounded-full object-cover ring-2 ring-gray-100"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                    <div class="w-10 h-10 rounded-full bg-brand-600 items-center justify-center hidden">
                        <span class="text-white text-sm font-bold leading-none">{{ $initial }}</span>
                    </div>
                @else
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-brand-500 to-brand-700 flex items-center justify-center shadow-sm">
                        <span class="text-white text-sm font-bold leading-none">{{ $initial }}</span>
                    </div>
                @endif
            </div>

            {{-- Info section --}}
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                    <p class="text-base font-bold text-gray-900 truncate">{{ $mgr->manager_name }}</p>
                    @if($mgr->manager_code)
                        <span class="font-mono text-[10px] text-brand-600 bg-brand-50 border border-brand-100 px-1.5 py-0.5 rounded flex-shrink-0">
                            {{ $mgr->manager_code }}
                        </span>
                    @endif
                    @if($mgr->manager_pass_decode)
                        <span class="font-mono text-[10px] text-purple-600 bg-purple-50 border border-purple-100 px-1.5 py-0.5 rounded flex-shrink-0">
                            🔑 {{ $mgr->manager_pass_decode }}
                        </span>
                    @endif
                </div>
                
                <div class="flex items-center gap-3 text-xs flex-wrap">
                    <span class="inline-flex items-center gap-1.5 font-semibold text-green-700">
                        <span class="w-2 h-2 rounded-full bg-green-500 flex-shrink-0"></span>
                        {{ $mgr->vacant_count }} ว่าง
                    </span>
                    <span class="inline-flex items-center gap-1.5 font-semibold text-red-700">
                        <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse flex-shrink-0"></span>
                        {{ $mgr->occupied_count }} ไม่ว่าง
                    </span>
                    <span class="text-gray-400">·</span>
                    <span class="text-gray-600 font-medium">ทั้งหมด {{ $mgr->total_props }} อสังหา</span>
                    @if($mgrRevenue > 0)
                        <span class="hidden md:inline text-gray-400">·</span>
                        <span class="hidden md:inline text-brand-700 font-bold tabular-nums">
                            ฿{{ number_format($mgrRevenue, 0) }}/ด.
                        </span>
                    @endif
                </div>
            </div>

            {{-- Expand icon --}}
            <svg class="w-5 h-5 text-gray-400 flex-shrink-0 transition-transform duration-200"
                 :class="open ? 'rotate-180' : ''"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>

        {{-- Expandable content --}}
        <div x-show="open"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-2"
             class="border-t border-gray-100">

            {{-- Desktop table --}}
            <div class="hidden sm:block overflow-x-auto">
                <table class="w-full text-left">
                    <thead style="background:#f9fafb">
                        <tr>
                            <th class="px-3 py-2 text-[10px] font-semibold text-gray-500 uppercase tracking-wide">รหัส</th>
                            <th class="px-3 py-2 text-[10px] font-semibold text-gray-500 uppercase tracking-wide">ชื่อทรัพย์</th>
                            <th class="px-3 py-2 text-[10px] font-semibold text-gray-500 uppercase tracking-wide text-right">ราคา/ด.</th>
                            <th class="px-3 py-2 text-[10px] font-semibold text-gray-500 uppercase tracking-wide hidden lg:table-cell">ผู้เช่า</th>
                            <th class="px-3 py-2 text-[10px] font-semibold text-gray-500 uppercase tracking-wide hidden xl:table-cell">วันเข้าอยู่</th>
                            <th class="px-3 py-2 text-[10px] font-semibold text-gray-500 uppercase tracking-wide hidden xl:table-cell">วันสิ้นสุด</th>
                            <th class="px-3 py-2 text-[10px] font-semibold text-gray-500 uppercase tracking-wide hidden xl:table-cell text-center">จำนวนเดือน</th>
                            <th class="px-3 py-2 text-[10px] font-semibold text-gray-500 uppercase tracking-wide text-center">สถานะ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($mgr->properties as $prop)
                        @php
                            $rent = $prop->is_occupied && $prop->booking
                                ? (float)($prop->booking->monthly_rent ?? 0)
                                : (float)($prop->price_per_month ?? 0);
                            $tenantName  = trim($prop->booking?->tenant_name ?? '');
                            
                            // วันเข้าอยู่ตามสัญญา (contract_start_date)
                            $contractStartDate = null;
                            if ($prop->booking?->contract_start_date) {
                                $csd = \Carbon\Carbon::parse($prop->booking->contract_start_date);
                                $contractStartDate = $csd->day . ' ' . $thM[$csd->month-1] . ' ' . ($csd->year+543);
                            }
                            
                            // วันสิ้นสุดสัญญา (check_out)
                            $contractEndDate = null;
                            if ($prop->booking?->check_out) {
                                $ced = \Carbon\Carbon::parse($prop->booking->check_out);
                                $contractEndDate = $ced->day . ' ' . $thM[$ced->month-1] . ' ' . ($ced->year+543);
                            }
                            
                            // จำนวนเดือน
                            $monthCount = null;
                            if ($prop->booking?->contract_start_date && $prop->booking?->check_out) {
                                $start = \Carbon\Carbon::parse($prop->booking->contract_start_date);
                                $end = \Carbon\Carbon::parse($prop->booking->check_out);
                                $monthCount = $start->diffInMonths($end);
                            }
                        @endphp
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-3 py-2">
                                <span class="font-mono text-[10px] font-bold text-brand-700 bg-brand-50 border border-brand-100 px-1.5 py-0.5 rounded">
                                    {{ $prop->property_code ?: '—' }}
                                </span>
                            </td>
                            <td class="px-3 py-2">
                                <p class="text-sm text-gray-800 font-medium max-w-xs truncate">{{ $prop->title ?: '—' }}</p>
                            </td>
                            <td class="px-3 py-2 text-right">
                                @if($rent > 0)
                                    <span class="text-sm font-bold text-gray-900 tabular-nums">{{ number_format($rent, 0) }}</span>
                                    <span class="text-[10px] text-gray-400">฿</span>
                                @else
                                    <span class="text-gray-300 text-xs">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 hidden lg:table-cell">
                                @if($prop->is_occupied && $tenantName)
                                    <p class="text-xs font-medium text-gray-700 max-w-xs truncate">{{ $tenantName }}</p>
                                    @if($prop->booking?->tenant_mobile)
                                        <p class="text-[10px] text-gray-400 mt-0.5">{{ $prop->booking->tenant_mobile }}</p>
                                    @endif
                                @else
                                    <span class="text-gray-300 text-xs">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-xs text-gray-600 hidden xl:table-cell">
                                {{ $contractStartDate ?? '—' }}
                            </td>
                            <td class="px-3 py-2 text-xs text-gray-600 hidden xl:table-cell">
                                {{ $contractEndDate ?? '—' }}
                            </td>
                            <td class="px-3 py-2 text-xs text-gray-700 font-semibold hidden xl:table-cell text-center">
                                @if($monthCount !== null)
                                    <span class="inline-flex items-center gap-0.5">
                                        {{ $monthCount }}
                                        <span class="text-[10px] text-gray-400">ด.</span>
                                    </span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-center">
                                @if($prop->is_occupied)
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold text-red-700 bg-red-50 border border-red-200 px-2 py-1 rounded-full transition-all duration-200 hover:shadow-md">
                                        <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span>
                                        ไม่ว่าง
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold text-green-700 bg-green-50 border border-green-200 px-2 py-1 rounded-full transition-all duration-200 hover:shadow-md">
                                        <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                                        ว่าง
                                    </span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile cards --}}
            <div class="sm:hidden divide-y divide-gray-100">
                @foreach($mgr->properties as $prop)
                @php
                    $rent       = $prop->is_occupied && $prop->booking
                        ? (float)($prop->booking->monthly_rent ?? 0)
                        : (float)($prop->price_per_month ?? 0);
                    $tenantName = trim($prop->booking?->tenant_name ?? '');
                    
                    // วันเข้าอยู่ตามสัญญา
                    $contractStartDate = null;
                    if ($prop->booking?->contract_start_date) {
                        $csd = \Carbon\Carbon::parse($prop->booking->contract_start_date);
                        $contractStartDate = $csd->day . ' ' . $thM[$csd->month-1] . ' ' . ($csd->year+543);
                    }
                    
                    // วันสิ้นสุดสัญญา
                    $contractEndDate = null;
                    if ($prop->booking?->check_out) {
                        $ced = \Carbon\Carbon::parse($prop->booking->check_out);
                        $contractEndDate = $ced->day . ' ' . $thM[$ced->month-1] . ' ' . ($ced->year+543);
                    }
                    
                    // จำนวนเดือน
                    $monthCount = null;
                    if ($prop->booking?->contract_start_date && $prop->booking?->check_out) {
                        $start = \Carbon\Carbon::parse($prop->booking->contract_start_date);
                        $end = \Carbon\Carbon::parse($prop->booking->check_out);
                        $monthCount = $start->diffInMonths($end);
                    }
                @endphp
                <div class="px-3 py-3">
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <span class="font-mono text-[10px] font-bold text-brand-700 bg-brand-50 border border-brand-100 px-1.5 py-0.5 rounded">
                            {{ $prop->property_code ?: '—' }}
                        </span>
                        @if($prop->is_occupied)
                            <span class="inline-flex items-center gap-1 text-[10px] font-bold text-red-700 bg-red-50 border border-red-200 px-2 py-1 rounded-full transition-all duration-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span>
                                ไม่ว่าง
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 text-[10px] font-bold text-green-700 bg-green-50 border border-green-200 px-2 py-1 rounded-full transition-all duration-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                                ว่าง
                            </span>
                        @endif
                    </div>
                    
                    <div class="mb-2">
                        <p class="text-sm font-semibold text-gray-800">{{ $prop->title ?: '—' }}</p>
                        @if($rent > 0)
                            <p class="text-xs text-gray-500 mt-1">
                                ราคา: <span class="font-bold text-gray-900 tabular-nums">{{ number_format($rent, 0) }} ฿/ด.</span>
                            </p>
                        @endif
                    </div>
                    
                    @if($prop->is_occupied)
                        <div class="space-y-1.5 text-xs">
                            @if($tenantName)
                                <div class="flex items-start gap-2">
                                    <span class="text-gray-400 flex-shrink-0 w-16">ผู้เช่า:</span>
                                    <span class="text-gray-700 font-medium">{{ $tenantName }}</span>
                                </div>
                            @endif
                            @if($contractStartDate)
                                <div class="flex items-start gap-2">
                                    <span class="text-gray-400 flex-shrink-0 w-16">เข้าอยู่:</span>
                                    <span class="text-gray-600">{{ $contractStartDate }}</span>
                                </div>
                            @endif
                            @if($contractEndDate)
                                <div class="flex items-start gap-2">
                                    <span class="text-gray-400 flex-shrink-0 w-16">สิ้นสุด:</span>
                                    <span class="text-gray-600">{{ $contractEndDate }}</span>
                                </div>
                            @endif
                            @if($monthCount !== null)
                                <div class="flex items-start gap-2">
                                    <span class="text-gray-400 flex-shrink-0 w-16">ระยะเวลา:</span>
                                    <span class="text-gray-700 font-semibold">{{ $monthCount }} เดือน</span>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
                @endforeach
            </div>

            {{-- Card footer --}}
            <div class="px-4 py-2.5 border-t border-gray-100 flex items-center justify-between bg-gray-50/50">
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="text-xs text-gray-500 tabular-nums">{{ $mgr->total_props }} รายการ</span>
                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-green-700">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                        {{ $mgr->vacant_count }} ว่าง
                    </span>
                    @if($mgr->occupied_count > 0)
                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-red-700">
                        <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                        {{ $mgr->occupied_count }} ไม่ว่าง
                    </span>
                    @endif
                </div>
                @if($mgrRevenue > 0)
                    <span class="text-sm font-bold text-brand-700 tabular-nums flex-shrink-0">
                        ฿{{ number_format($mgrRevenue, 0) }}<span class="text-gray-400 font-normal text-xs ml-1">/ด.</span>
                    </span>
                @endif
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    Chart.defaults.font.family = "Sarabun, 'Noto Sans Thai', sans-serif";
    Chart.defaults.font.size   = 12;

    // Plugin: แสดงตัวเลขบนแต่ละส่วนของ stacked bar
    const stackedBarLabelPlugin = {
        id: 'stackedBarLabel',
        afterDatasetsDraw(chart) {
            const ctx = chart.ctx;
            const xAxis = chart.scales.x;
            const yAxis = chart.scales.y;
            
            ctx.save();
            ctx.font = 'bold 11px Sarabun, sans-serif';
            ctx.textBaseline = 'middle';
            
            // วนลูปแต่ละแถว (แต่ละผู้บริหาร)
            chart.data.labels.forEach((label, index) => {
                let stackX = xAxis.getPixelForValue(0); // เริ่มจาก 0
                
                // วนลูปแต่ละ dataset (ว่าง, ไม่ว่าง)
                chart.data.datasets.forEach((dataset, datasetIndex) => {
                    const meta = chart.getDatasetMeta(datasetIndex);
                    if (!meta.visible) return;
                    
                    const bar = meta.data[index];
                    const value = dataset.data[index];
                    
                    if (!value || value <= 0) return;
                    
                    // คำนวณความกว้างของส่วนนี้
                    const barWidth = xAxis.getPixelForValue(value) - xAxis.getPixelForValue(0);
                    const centerX = stackX + barWidth / 2;
                    const centerY = bar.y;
                    
                    // ตรวจสอบว่าพื้นที่พอแสดงตัวเลขไหม (ต้องกว้างอย่างน้อย 25px)
                    if (Math.abs(barWidth) >= 25) {
                        // แสดงในแถบ
                        ctx.fillStyle = 'rgba(255,255,255,0.95)';
                        ctx.textAlign = 'center';
                        ctx.fillText(value, centerX, centerY);
                    } else if (Math.abs(barWidth) >= 12) {
                        // แถบเล็กแต่ยังพอแสดงได้ - ใช้ฟอนต์เล็กลง
                        ctx.save();
                        ctx.font = 'bold 9px Sarabun, sans-serif';
                        ctx.fillStyle = 'rgba(255,255,255,0.90)';
                        ctx.textAlign = 'center';
                        ctx.fillText(value, centerX, centerY);
                        ctx.restore();
                    }
                    // ถ้าแถบเล็กกว่า 12px ไม่แสดงตัวเลข
                    
                    stackX += barWidth; // เลื่อนตำแหน่งไปส่วนถัดไป
                });
            });
            
            ctx.restore();
        }
    };

    // Plugin สำหรับกราฟเปอร์เซ็นต์
    const percentBarLabelPlugin = {
        id: 'percentBarLabel',
        afterDatasetsDraw(chart) {
            const ctx = chart.ctx;
            const xAxis = chart.scales.x;
            
            ctx.save();
            ctx.font = 'bold 11px Sarabun, sans-serif';
            ctx.textBaseline = 'middle';
            
            chart.data.labels.forEach((label, index) => {
                let stackX = xAxis.getPixelForValue(0);
                
                chart.data.datasets.forEach((dataset, datasetIndex) => {
                    const meta = chart.getDatasetMeta(datasetIndex);
                    if (!meta.visible) return;
                    
                    const bar = meta.data[index];
                    const value = dataset.data[index];
                    
                    if (!value || value <= 0) return;
                    
                    const barWidth = xAxis.getPixelForValue(value) - xAxis.getPixelForValue(0);
                    const centerX = stackX + barWidth / 2;
                    const centerY = bar.y;
                    
                    if (Math.abs(barWidth) >= 30) {
                        ctx.fillStyle = 'rgba(255,255,255,0.95)';
                        ctx.textAlign = 'center';
                        ctx.fillText(value + '%', centerX, centerY);
                    } else if (Math.abs(barWidth) >= 15) {
                        ctx.save();
                        ctx.font = 'bold 9px Sarabun, sans-serif';
                        ctx.fillStyle = 'rgba(255,255,255,0.90)';
                        ctx.textAlign = 'center';
                        ctx.fillText(value + '%', centerX, centerY);
                        ctx.restore();
                    }
                    
                    stackX += barWidth;
                });
            });
            
            ctx.restore();
        }
    };

    // Hero donut: green=ว่าง, red=ไม่ว่าง
    const heroCtx = document.getElementById('heroDonut');
    if (heroCtx) {
        new Chart(heroCtx, {
            type: 'doughnut',
            data: {
                datasets: [{
                    data: [{{ $totalVacant }}, {{ max(0, $totalOccupied) }}],
                    backgroundColor: ['#22c55e', '#ef4444'], // green-500, red-500
                    borderWidth: 0,
                    borderRadius: 6,
                    spacing: 2,
                }]
            },
            options: {
                cutout: '72%',
                plugins: { legend: { display: false }, tooltip: { enabled: false } },
                animation: { duration: 1200, easing: 'easeInOutQuart' },
            }
        });
    }

    @if($byManager->count() > 0)
    const raw = @json($chartData);
    const labels = raw.map(r => {
        const name = r.name.length > 20 ? r.name.slice(0, 19) + '…' : r.name;
        const code = r.code ? ' (' + r.code + ')' : '';
        return name + code;
    });

    // Chart 1: Stacked count bar (green=ว่าง, red=ไม่ว่าง)
    const mgrCtx = document.getElementById('managerChart');
    if (mgrCtx) {
        new Chart(mgrCtx, {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {
                        label: 'ว่าง',
                        data: raw.map(r => r.vacant),
                        backgroundColor: '#22c55e', // green-500
                        borderRadius: 3,
                        borderSkipped: false,
                    },
                    {
                        label: 'ไม่ว่าง',
                        data: raw.map(r => r.occupied),
                        backgroundColor: '#ef4444', // red-500
                        borderRadius: 3,
                        borderSkipped: false,
                    }
                ]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.85)',
                        padding: 12,
                        titleFont: { size: 13, weight: 'bold' },
                        bodyFont: { size: 12 },
                        cornerRadius: 8,
                        callbacks: {
                            title: ctx => {
                                const idx = ctx[0].dataIndex;
                                return raw[idx].name + (raw[idx].code ? ' (' + raw[idx].code + ')' : '');
                            },
                            label: ctx => ' ' + ctx.dataset.label + ': ' + ctx.parsed.x + ' อสังหา',
                        }
                    }
                },
                scales: {
                    x: { stacked: true, grid: { display: false }, border: { display: false }, ticks: { display: false } },
                    y: { 
                        stacked: true, 
                        grid: { display: false }, 
                        border: { display: false }, 
                        ticks: { 
                            color: '#374151', 
                            font: { size: 11, weight: '500' },
                            padding: 8,
                            autoSkip: false
                        } 
                    }
                }
            },
            plugins: [stackedBarLabelPlugin]
        });
    }

    // Chart 2: Proportional stacked bar (green=ว่าง, red=ไม่ว่าง)
    const rateCtx = document.getElementById('rateChart');
    if (rateCtx) {
        new Chart(rateCtx, {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {
                        label: 'ว่าง',
                        data: raw.map(r => r.vrate),
                        backgroundColor: '#22c55e', // green-500
                        borderRadius: 3,
                        borderSkipped: false,
                    },
                    {
                        label: 'ไม่ว่าง',
                        data: raw.map(r => r.rate),
                        backgroundColor: '#ef4444', // red-500
                        borderRadius: 3,
                        borderSkipped: false,
                    }
                ]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.85)',
                        padding: 12,
                        titleFont: { size: 13, weight: 'bold' },
                        bodyFont: { size: 12 },
                        cornerRadius: 8,
                        callbacks: {
                            title: ctx => {
                                const idx = ctx[0].dataIndex;
                                return raw[idx].name + (raw[idx].code ? ' (' + raw[idx].code + ')' : '');
                            },
                            label: ctx => ' ' + ctx.dataset.label + ': ' + ctx.parsed.x + '%',
                        }
                    }
                },
                scales: {
                    x: {
                        stacked: true,
                        min: 0,
                        max: 100,
                        grid: { display: false },
                        border: { display: false },
                        ticks: { display: false }
                    },
                    y: {
                        stacked: true,
                        grid: { display: false },
                        border: { display: false },
                        ticks: { 
                            color: '#374151', 
                            font: { size: 11, weight: '500' },
                            padding: 8,
                            autoSkip: false
                        }
                    }
                }
            },
            plugins: [percentBarLabelPlugin]
        });
    }
    @endif
})();
</script>
@endpush
