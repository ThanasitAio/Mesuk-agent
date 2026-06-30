@extends('layouts.app')

@section('title', 'อัตราเช่า')
@section('breadcrumb', 'ภาพรวมและประสิทธิภาพผู้บริหารโครงการ')

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
                    <div class="rounded-xl p-3 text-center" style="background:rgba(255,255,255,.07)">
                        <p class="text-2xl font-black text-white tabular-nums leading-none">{{ $totalProps }}</p>
                        <p class="text-[10px] mt-1" style="color:rgba(255,255,255,.38)">ทรัพย์ทั้งหมด</p>
                    </div>
                    <div class="rounded-xl p-3 text-center" style="background:rgba(74,222,128,.13)">
                        <p class="text-2xl font-black tabular-nums leading-none" style="color:#4ade80">{{ $totalVacant }}</p>
                        <p class="text-[10px] mt-1" style="color:rgba(74,222,128,.60)">ว่าง</p>
                    </div>
                    <div class="rounded-xl p-3 text-center" style="background:rgba(248,113,113,.13)">
                        <p class="text-2xl font-black tabular-nums leading-none" style="color:#f87171">{{ $totalOccupied }}</p>
                        <p class="text-[10px] mt-1" style="color:rgba(248,113,113,.60)">ไม่ว่าง</p>
                    </div>
                    <div class="rounded-xl p-3 text-center" style="background:rgba(255,255,255,.07)">
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
                        <span class="text-[17px] font-black tabular-nums leading-none" style="color:#4ade80">{{ $vacancyRate }}%</span>
                        <span class="text-[8px] mt-0.5" style="color:rgba(255,255,255,.38)">ว่าง</span>
                    </div>
                </div>
                <div class="flex flex-col gap-0.5">
                    <div class="flex items-center gap-1">
                        <span class="w-2 h-2 rounded-full flex-shrink-0" style="background:#4ade80"></span>
                        <span class="text-[9px] tabular-nums" style="color:rgba(255,255,255,.50)">ว่าง {{ $totalVacant }} ห้อง</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="w-2 h-2 rounded-full flex-shrink-0" style="background:#f87171"></span>
                        <span class="text-[9px] tabular-nums" style="color:rgba(255,255,255,.50)">ไม่ว่าง {{ $totalOccupied }} ห้อง</span>
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
        'occupied' => $m->occupied_count,
        'vacant'   => $m->vacant_count,
        'rate'     => $m->occupancy_rate,
        'vrate'    => $m->total_props > 0 ? round($m->vacant_count / $m->total_props * 100, 1) : 0,
    ])->values()->toArray();
    $chartH = max(72, $byManager->count() * 44);
@endphp

<div class="grid grid-cols-1 lg:grid-cols-2 gap-3 mb-4">

    {{-- Chart 1: จำนวนทรัพย์ (absolute count) --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
        <div class="flex items-start justify-between mb-1">
            <div>
                <h3 class="text-sm font-bold text-gray-800">จำนวนทรัพย์ต่อผู้บริหาร</h3>
                <p class="text-[11px] text-gray-400 mt-0.5">จำนวนห้องจริง (ว่าง + ไม่ว่าง)</p>
            </div>
            <div class="flex items-center gap-3 text-[11px] text-gray-400 flex-shrink-0 ml-2">
                <span class="flex items-center gap-1">
                    <span class="inline-block w-2.5 h-2.5 rounded-sm" style="background:rgba(74,222,128,.80)"></span>ว่าง
                </span>
                <span class="flex items-center gap-1">
                    <span class="inline-block w-2.5 h-2.5 rounded-sm" style="background:rgba(248,113,113,.80)"></span>ไม่ว่าง
                </span>
            </div>
        </div>
        <div style="position:relative;height:{{ $chartH }}px">
            <canvas id="managerChart"></canvas>
        </div>
    </div>

    {{-- Chart 2: สัดส่วน % ว่าง vs ไม่ว่าง (normalized 100%) --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
        <div class="flex items-start justify-between mb-1">
            <div>
                <h3 class="text-sm font-bold text-gray-800">สัดส่วนว่าง / ไม่ว่าง</h3>
                <p class="text-[11px] text-gray-400 mt-0.5">เปอร์เซ็นต์ต่อผู้บริหาร (รวม 100%)</p>
            </div>
            <div class="flex items-center gap-3 text-[11px] text-gray-400 flex-shrink-0 ml-2">
                <span class="flex items-center gap-1">
                    <span class="inline-block w-2.5 h-2.5 rounded-sm" style="background:rgba(74,222,128,.80)"></span>ว่าง
                </span>
                <span class="flex items-center gap-1">
                    <span class="inline-block w-2.5 h-2.5 rounded-sm" style="background:rgba(248,113,113,.80)"></span>ไม่ว่าง
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
    @foreach($byManager as $mgr)
    @php
        $rate       = $mgr->occupancy_rate;
        $vRate      = $mgr->total_props > 0 ? round($mgr->vacant_count / $mgr->total_props * 100, 1) : 0;
        $initial    = mb_strtoupper(mb_substr($mgr->manager_name, 0, 1));
        $mgrRevenue = $mgr->properties
            ->filter(fn($p) => $p->is_occupied && $p->booking)
            ->sum(fn($p) => (float)($p->booking->monthly_rent ?? 0));
    @endphp

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden"
         x-data="{ open: false }">

        {{-- Accordion header --}}
        <button type="button" @click="open = !open"
                class="w-full text-left px-3 py-2.5 flex items-center gap-2.5 hover:bg-gray-50/60 transition-colors">

            {{-- Avatar --}}
            <div class="flex-shrink-0">
                @if($mgr->manager_avatar)
                    <img src="{{ $happyestPublic.'/storage/'.$mgr->manager_avatar }}"
                         alt="{{ $mgr->manager_name }}"
                         class="w-9 h-9 rounded-full object-cover ring-2 ring-gray-100"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                    <div class="w-9 h-9 rounded-full bg-brand-600 items-center justify-center hidden">
                        <span class="text-white text-sm font-bold leading-none">{{ $initial }}</span>
                    </div>
                @else
                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-brand-500 to-brand-700 flex items-center justify-center">
                        <span class="text-white text-sm font-bold leading-none">{{ $initial }}</span>
                    </div>
                @endif
            </div>

            {{-- Name + compact progress bar --}}
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1">
                    <p class="text-sm font-bold text-gray-900 truncate">{{ $mgr->manager_name }}</p>
                    @if($mgr->manager_code)
                        <span class="hidden sm:inline font-mono text-[10px] text-brand-600 bg-brand-50 border border-brand-100 px-1.5 py-0.5 rounded-md flex-shrink-0">
                            {{ $mgr->manager_code }}
                        </span>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    {{-- Bar: red = ไม่ว่าง, green = ว่าง --}}
                    <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden flex">
                        @if($rate > 0)
                            <div class="h-full rounded-l-full" style="width:{{ $rate }}%;background:#f87171"></div>
                        @endif
                        @if($vRate > 0)
                            <div class="h-full {{ $rate > 0 ? '' : 'rounded-l-full' }} rounded-r-full" style="width:{{ $vRate }}%;background:#4ade80"></div>
                        @endif
                    </div>
                    <span class="text-[11px] tabular-nums flex-shrink-0 font-semibold" style="color:#16a34a">
                        ว่าง {{ $mgr->vacant_count }}/{{ $mgr->total_props }}
                    </span>
                </div>
            </div>

            {{-- Desktop quick stats --}}
            <div class="hidden md:flex items-center gap-3 flex-shrink-0 text-right">
                <div>
                    <div class="flex items-center gap-1.5 justify-end">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-400 flex-shrink-0"></span>
                        <span class="text-[11px] text-gray-500 tabular-nums">{{ $mgr->vacant_count }} ว่าง</span>
                        <span class="text-gray-300 text-[10px]">·</span>
                        <span class="w-1.5 h-1.5 rounded-full bg-red-400 flex-shrink-0"></span>
                        <span class="text-[11px] text-gray-500 tabular-nums">{{ $mgr->occupied_count }} ไม่ว่าง</span>
                    </div>
                    <p class="text-[10px] text-gray-400 mt-0.5">จาก {{ $mgr->total_props }} ทรัพย์</p>
                </div>
                @if($mgrRevenue > 0)
                    <div class="pl-3" style="border-left:1px solid #f0f0f0">
                        <p class="text-xs font-bold text-brand-700 tabular-nums leading-none">{{ number_format($mgrRevenue, 0) }}</p>
                        <p class="text-[10px] text-gray-400 mt-0.5">฿/เดือน</p>
                    </div>
                @endif
            </div>

            <svg class="w-4 h-4 text-gray-400 flex-shrink-0 transition-transform duration-200"
                 :class="open ? 'rotate-180' : ''"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>

        {{-- Expandable content --}}
        <div x-show="open"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="border-t border-gray-100">

            {{-- Desktop table --}}
            <div class="hidden sm:block overflow-x-auto">
                <table class="w-full text-left">
                    <thead style="background:#fafafa">
                        <tr>
                            <th class="px-4 py-2 text-[11px] font-semibold text-gray-400 uppercase tracking-wide whitespace-nowrap">รหัส</th>
                            <th class="px-4 py-2 text-[11px] font-semibold text-gray-400 uppercase tracking-wide">ชื่อทรัพย์</th>
                            <th class="px-4 py-2 text-[11px] font-semibold text-gray-400 uppercase tracking-wide text-right whitespace-nowrap">฿/เดือน</th>
                            <th class="px-4 py-2 text-[11px] font-semibold text-gray-400 uppercase tracking-wide hidden lg:table-cell">ผู้เช่า</th>
                            <th class="px-4 py-2 text-[11px] font-semibold text-gray-400 uppercase tracking-wide whitespace-nowrap hidden md:table-cell">เข้าพัก</th>
                            <th class="px-4 py-2 text-[11px] font-semibold text-gray-400 uppercase tracking-wide text-center">สถานะ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($mgr->properties as $prop)
                        @php
                            $rent = $prop->is_occupied && $prop->booking
                                ? (float)($prop->booking->monthly_rent ?? 0)
                                : (float)($prop->price_per_month ?? 0);
                            $tenantName  = trim($prop->booking?->tenant_name ?? '');
                            $checkInDate = null;
                            if ($prop->booking?->check_in) {
                                $ci = \Carbon\Carbon::parse($prop->booking->check_in);
                                $checkInDate = $ci->day . ' ' . $thM[$ci->month-1] . ' ' . ($ci->year+543);
                            }
                        @endphp
                        <tr class="hover:bg-gray-50/40 transition-colors">
                            <td class="px-4 py-2 whitespace-nowrap">
                                <span class="font-mono text-[11px] font-bold text-brand-700 bg-brand-50 px-1.5 py-0.5 rounded">
                                    {{ $prop->property_code ?: '—' }}
                                </span>
                            </td>
                            <td class="px-4 py-2">
                                <p class="text-sm text-gray-800 font-medium max-w-xs truncate">{{ $prop->title ?: '—' }}</p>
                            </td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">
                                @if($rent > 0)
                                    <span class="text-sm font-bold text-gray-900 tabular-nums">{{ number_format($rent, 0) }}</span>
                                    <span class="text-[11px] text-gray-400">฿</span>
                                @else
                                    <span class="text-gray-300 text-xs">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 hidden lg:table-cell">
                                @if($prop->is_occupied && $tenantName)
                                    <p class="text-xs font-medium text-gray-700 max-w-xs truncate">{{ $tenantName }}</p>
                                    @if($prop->booking?->tenant_mobile)
                                        <p class="text-[10px] text-gray-400">{{ $prop->booking->tenant_mobile }}</p>
                                    @endif
                                @else
                                    <span class="text-gray-300 text-xs">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-xs text-gray-500 whitespace-nowrap hidden md:table-cell">
                                {{ $checkInDate ?? '—' }}
                            </td>
                            <td class="px-4 py-2 text-center">
                                @if($prop->is_occupied)
                                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-red-700 bg-red-50 border border-red-100 px-2 py-0.5 rounded-full whitespace-nowrap">
                                        <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span>
                                        ไม่ว่าง
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 bg-emerald-50 border border-emerald-100 px-2 py-0.5 rounded-full whitespace-nowrap">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
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
            <div class="sm:hidden divide-y divide-gray-50">
                @foreach($mgr->properties as $prop)
                @php
                    $rent       = $prop->is_occupied && $prop->booking
                        ? (float)($prop->booking->monthly_rent ?? 0)
                        : (float)($prop->price_per_month ?? 0);
                    $tenantName = trim($prop->booking?->tenant_name ?? '');
                @endphp
                <div class="px-3 py-2">
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <span class="font-mono text-[11px] font-bold text-brand-700 bg-brand-50 px-1.5 py-0.5 rounded">
                            {{ $prop->property_code ?: '—' }}
                        </span>
                        @if($prop->is_occupied)
                            <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-red-700 bg-red-50 px-2 py-0.5 rounded-full">
                                <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span>
                                ไม่ว่าง
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                ว่าง
                            </span>
                        @endif
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-gray-800 truncate">{{ $prop->title ?: '—' }}</p>
                            @if($prop->is_occupied && $tenantName)
                                <p class="text-[11px] text-gray-400 truncate mt-0.5">{{ $tenantName }}</p>
                            @endif
                        </div>
                        @if($rent > 0)
                            <span class="text-sm font-bold text-gray-900 tabular-nums flex-shrink-0">
                                {{ number_format($rent, 0) }}<span class="text-[11px] font-normal text-gray-400 ml-0.5">฿</span>
                            </span>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>

            {{-- Card footer --}}
            <div class="px-4 py-2 border-t border-gray-50 flex items-center justify-between" style="background:#fafafa">
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="text-[11px] text-gray-400 tabular-nums">{{ $mgr->total_props }} รายการ</span>
                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold tabular-nums" style="color:#16a34a">
                        <span class="w-1.5 h-1.5 rounded-full" style="background:#4ade80"></span>
                        {{ $mgr->vacant_count }} ว่าง
                    </span>
                    @if($mgr->occupied_count > 0)
                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold tabular-nums" style="color:#dc2626">
                        <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                        {{ $mgr->occupied_count }} ไม่ว่าง
                    </span>
                    @endif
                </div>
                @if($mgrRevenue > 0)
                    <span class="text-xs font-bold text-brand-700 tabular-nums flex-shrink-0">
                        {{ number_format($mgrRevenue, 0) }}<span class="text-gray-400 font-normal ml-1">฿/เดือน</span>
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

    // Inline plugin: draw value labels on bar segments
    function makeLabelPlugin(suffix, minPx) {
        return {
            id: 'barLabel' + (suffix || '_n'),
            afterDatasetsDraw(chart) {
                const ctx = chart.ctx;
                const zero = chart.scales.x.getPixelForValue(0);
                chart.data.datasets.forEach((ds, di) => {
                    chart.getDatasetMeta(di).data.forEach((bar, i) => {
                        const val = ds.data[i];
                        if (!val || val <= 0) return;
                        if (Math.abs(bar.x - zero) < (minPx || 20)) return;
                        const c = bar.getCenterPoint();
                        ctx.save();
                        ctx.font = 'bold 11px Sarabun, sans-serif';
                        ctx.fillStyle = 'rgba(255,255,255,0.95)';
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'middle';
                        ctx.fillText(suffix ? val + suffix : val, c.x, c.y);
                        ctx.restore();
                    });
                });
            }
        };
    }
    const countLabel = makeLabelPlugin('', 22);
    const pctLabel   = makeLabelPlugin('%', 18);

    // Hero donut
    const heroCtx = document.getElementById('heroDonut');
    if (heroCtx) {
        new Chart(heroCtx, {
            type: 'doughnut',
            data: {
                datasets: [{
                    data: [{{ $totalVacant }}, {{ max(0, $totalOccupied) }}],
                    backgroundColor: ['rgba(74,222,128,.90)', 'rgba(248,113,113,.70)'],
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
    const labels = raw.map(r => r.name.length > 16 ? r.name.slice(0, 15) + '…' : r.name);

    // Chart 1: Stacked count bar (absolute numbers)
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
                        backgroundColor: 'rgba(74,222,128,.80)',
                        borderRadius: 3,
                        borderSkipped: false,
                    },
                    {
                        label: 'ไม่ว่าง',
                        data: raw.map(r => r.occupied),
                        backgroundColor: 'rgba(248,113,113,.80)',
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
                        callbacks: {
                            label: ctx => ' ' + ctx.dataset.label + ': ' + ctx.parsed.x + ' ห้อง',
                        }
                    }
                },
                scales: {
                    x: { stacked: true, grid: { display: false }, border: { display: false }, ticks: { display: false } },
                    y: { stacked: true, grid: { display: false }, border: { display: false }, ticks: { color: '#374151', font: { size: 12 } } }
                }
            },
            plugins: [countLabel]
        });
    }

    // Chart 2: Proportional stacked bar (% ว่าง + % ไม่ว่าง = 100%)
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
                        backgroundColor: 'rgba(74,222,128,.80)',
                        borderRadius: 3,
                        borderSkipped: false,
                    },
                    {
                        label: 'ไม่ว่าง',
                        data: raw.map(r => r.rate),
                        backgroundColor: 'rgba(248,113,113,.80)',
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
                        callbacks: {
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
                        ticks: { color: '#374151', font: { size: 12 } }
                    }
                }
            },
            plugins: [pctLabel]
        });
    }
    @endif
})();
</script>
@endpush
