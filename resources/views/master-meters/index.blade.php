@extends('layouts.app')

@section('title', 'บันทึกมิเตอร์หลัก')
@section('breadcrumb', 'บันทึกมิเตอร์หลักรายเดือน')

@section('content')

@php
    $months = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม',
    ];
    $periodLabel = ($months[$month] ?? $month) . ' ' . ($year + 543);

    $meterTotal      = $rows->sum('meter_count');
    $recordedTotal   = $rows->sum('recorded_count');
    $unrecordedTotal = $meterTotal - $recordedTotal;

    // สถานะการจดของกลุ่ม - ความหมายเดียวกับหน้าแอดมิน (master-meter-readings.index)
    $statusFor = function ($row) {
        return match ($row->record_status) {
            'recorded' => [
                'label'   => 'บันทึกครบแล้ว',
                'short'   => 'ครบแล้ว',
                'classes' => 'text-emerald-700 bg-emerald-50 border border-emerald-200',
                'icon'    => 'M5 13l4 4L19 7',
            ],
            'partial' => [
                'label'   => "บันทึกแล้ว {$row->recorded_count}/{$row->meter_count}",
                'short'   => "{$row->recorded_count}/{$row->meter_count}",
                'classes' => 'text-blue-700 bg-blue-50 border border-blue-200',
                'icon'    => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
            ],
            default => [
                'label'   => 'ยังไม่บันทึก',
                'short'   => 'ยังไม่บันทึก',
                'classes' => 'text-gray-600 bg-gray-100 border border-gray-200',
                'icon'    => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
            ],
        };
    };

    $typeStyles = [
        'electric' => ['label' => 'ไฟฟ้า', 'emoji' => '⚡', 'bg' => 'bg-amber-50/40', 'chip' => 'bg-amber-100'],
        'water'    => ['label' => 'น้ำ', 'emoji' => '💧', 'bg' => 'bg-blue-50/30', 'chip' => 'bg-blue-100'],
    ];
    $fmtUnits = fn ($value) => $value === null ? '-' : number_format((float) $value, 2);

    // บันทึก/แก้ไขล่าสุดของกลุ่ม - created_by/updated_by เป็น hr_admins.id, null = บันทึกจากแอปตัวแทน
    $thaiShortMonths = [1 => 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $stamp = fn ($at) => $at
        ? $at->format('j') . ' ' . $thaiShortMonths[$at->month] . ' ' . ($at->year + 543) . ' ' . $at->format('H:i') . ' น.'
        : '-';
    $latestLine = function ($reading) use ($stamp) {
        $edited  = $reading->wasEdited();
        $adminId = $edited ? $reading->updated_by : $reading->created_by;
        $admin   = $edited ? $reading->updater : $reading->creator;
        $who     = $adminId === null ? 'ผู้บริหารโครงการ' : ($admin?->name ? 'แอดมิน ' . $admin->name : 'ไม่ระบุ');

        return [
            'at' => ($edited ? 'แก้ไข ' : 'บันทึก ') . $stamp($edited ? $reading->updated_at : $reading->created_at),
            'by' => 'โดย ' . $who,
        ];
    };

    $rows = $rows->map(function ($row) {
        $row->search_text = mb_strtolower($row->group_name . ' ' . implode(' ', $row->property_codes));

        return $row;
    });
@endphp

<div x-data="{
        search: '',
        recordFilter: 'all',
        items: @js($rows->map(fn ($row) => ['search' => $row->search_text, 'status' => $row->record_status])->values()),
        matchesSearch(search_text) {
            const q = this.search.toLowerCase().trim();
            return q === '' || search_text.includes(q);
        },
        matches(search_text, status) {
            return this.matchesSearch(search_text) && (this.recordFilter === 'all' || this.recordFilter === status);
        },
        get filteredCount() {
            return this.items.filter(it => this.matches(it.search, it.status)).length;
        },
        get hasMatches() {
            return this.filteredCount > 0;
        },
        // จำนวนบน tab นับตามคำค้นหาที่ใช้อยู่ - ไม่รวม recordFilter เอง
        countByStatus(status) {
            return this.items.filter(it => this.matchesSearch(it.search) && (status === 'all' || it.status === status)).length;
        },
    }">

{{-- ── Hero Header ─────────────────────────────────────────────────────────── --}}
<div class="relative overflow-hidden rounded-2xl mb-6 p-4 sm:p-5 lg:p-6"
     style="background: linear-gradient(135deg, #1c3514 0%, #2a4f1f 45%, #1c3514 100%);">

    <div class="hero-shimmer-bar"></div>

    <div class="hero-glow" style="width:220px;height:220px;top:-60px;right:-60px;background:rgba(154,216,114,0.12);"></div>
    <div class="hero-glow" style="width:140px;height:140px;bottom:-40px;left:30%;background:rgba(70,132,50,0.10);animation-delay:2s;"></div>

    <div class="hero-blob-1 pointer-events-none absolute -top-10 -right-10 w-44 h-44 rounded-full bg-brand-700 opacity-30"></div>
    <div class="hero-blob-2 pointer-events-none absolute top-2 right-14 w-24 h-24 rounded-full bg-brand-600 opacity-20"></div>
    <div class="hero-blob-3 pointer-events-none absolute -bottom-8 right-4 w-32 h-32 rounded-full bg-brand-800 opacity-40"></div>

    <div class="relative flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 sm:gap-4" style="z-index:2;">
        <div class="hero-text-row min-w-0">
            <p class="text-xs font-medium mb-1" style="color: rgba(255,255,255,0.6)">งวดอ่านมิเตอร์ {{ $periodLabel }}</p>
            <h2 class="text-xl lg:text-2xl font-black text-white leading-tight truncate">บันทึกมิเตอร์หลัก</h2>
            <p class="text-sm mt-1.5" style="color: rgba(255,255,255,0.65)">
                จดเลขมิเตอร์หลักของกลุ่มอสังหาที่แอดมินกำหนดให้คุณเป็นผู้จด
            </p>
        </div>
        <div class="hero-badge-row flex-shrink-0">
            <span class="inline-flex items-center gap-1.5 rounded-xl border px-3 py-1.5 text-xs font-bold text-white"
                  style="background:rgba(255,255,255,0.12); border-color:rgba(255,255,255,0.2); backdrop-filter:blur(8px);">
                {{ $rows->count() }} กลุ่มอสังหา
            </span>
        </div>
    </div>

    {{-- Quick stats strip (นับเป็นจำนวนมิเตอร์หลัก) --}}
    <div class="hero-stats-row relative mt-4 sm:mt-5 pt-4 grid grid-cols-3 gap-2 sm:gap-3"
         style="border-top:1px solid rgba(255,255,255,0.12); z-index:2;">
        <div class="text-center">
            <p class="text-xl sm:text-2xl font-black text-white tabular-nums leading-none">{{ $meterTotal }}</p>
            <p class="text-[10px] sm:text-[11px] font-medium mt-1" style="color:rgba(255,255,255,0.6)">มิเตอร์หลักที่ต้องจด</p>
        </div>
        <div class="text-center" style="border-left:1px solid rgba(255,255,255,0.12)">
            <p class="text-xl sm:text-2xl font-black text-white tabular-nums leading-none">{{ $recordedTotal }}</p>
            <p class="text-[10px] sm:text-[11px] font-medium mt-1" style="color:rgba(255,255,255,0.6)">บันทึกแล้ว</p>
        </div>
        <div class="text-center" style="border-left:1px solid rgba(255,255,255,0.12)">
            <p class="text-xl sm:text-2xl font-black tabular-nums leading-none {{ $unrecordedTotal > 0 ? 'text-amber-300' : 'text-white' }}">{{ $unrecordedTotal }}</p>
            <p class="text-[10px] sm:text-[11px] font-medium mt-1" style="color:rgba(255,255,255,0.6)">ยังไม่บันทึก</p>
        </div>
    </div>
</div>

{{-- ── มิเตอร์หลักที่รอแอดมินเลือกผู้จด (ทรัพย์ที่ผูกมีผู้บริหารโครงการหลายคน) ── --}}
@if($pendingChoice->isNotEmpty())
    <div class="flex items-start gap-2.5 bg-amber-50 border border-amber-200 rounded-xl px-3.5 py-3 mb-6">
        <svg class="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.07 16.5c-.77.833.192 2.5 1.732 2.5z"/>
        </svg>
        <div class="min-w-0">
            <p class="text-xs text-amber-800 leading-relaxed">
                <span class="font-semibold">มิเตอร์หลัก {{ $pendingChoice->count() }} ตัวรอแอดมินเลือกผู้จด</span>
                - ทรัพย์ที่ผูกมีผู้บริหารโครงการหลายคน ยังบันทึกไม่ได้จนกว่าแอดมินจะเลือกผู้จด
            </p>
            <div class="flex flex-wrap gap-1 mt-1.5">
                @foreach($pendingChoice as $m)
                    <span class="text-[11px] font-medium text-amber-800 bg-white border border-amber-200 rounded-full px-2 py-0.5">
                        {{ $m->propertyGroup?->property_group_name ?? 'กลุ่ม #' . $m->property_group_id }} &middot; {{ $m->name }}
                    </span>
                @endforeach
            </div>
        </div>
    </div>
@endif

{{-- ── Filters ──────────────────────────────────────────────────────────────── --}}
<div class="flex flex-col gap-2.5 mb-6">
    <div class="flex flex-col lg:flex-row gap-2.5">
        {{-- ค้นหา (กรองฝั่ง client ทันทีที่พิมพ์ - ไม่ต้องกดปุ่ม) --}}
        <div class="w-full lg:w-[70%] relative border border-gray-300 rounded-xl bg-white transition-all focus-within:ring-2 focus-within:ring-brand-500/20 focus-within:border-brand-500">
            <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text"
                   x-model="search"
                   placeholder="ค้นหาชื่อกลุ่มอสังหา หรือรหัสอสังหาที่ผูก..."
                   class="w-full pl-11 pr-9 py-2.5 text-sm bg-transparent focus:outline-none text-gray-800 placeholder-gray-400">
            <button x-show="search" x-cloak @click="search = ''"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-300 hover:text-gray-500 transition-colors focus:outline-none"
                    tabindex="-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- งวดอ่านมิเตอร์ (เปลี่ยนแล้วส่งฟอร์มทันที - ไม่ต้องกดปุ่ม) --}}
        <div class="w-full lg:w-[30%]">
            <form method="GET" id="masterMeterPeriodForm">
                <x-form.month-year
                    name-month="month"
                    name-year="year"
                    :value-month="$month"
                    :value-year="$year"
                    :year-from="2025"
                    :year-to="now()->year + 1"
                />
            </form>
        </div>
    </div>

    <div class="flex items-center gap-2 flex-wrap">
        {{-- สถานะการบันทึก (ความหมายเดียวกับ tab หน้าแอดมิน) --}}
        <div class="flex gap-1.5 bg-gray-100 rounded-xl p-1.5 overflow-x-auto">
            <button type="button" @click="recordFilter = 'all'"
                    :class="recordFilter === 'all' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                    class="flex-shrink-0 flex items-center gap-1.5 px-3 sm:px-3.5 py-2 sm:py-2.5 text-xs font-semibold rounded-lg transition-all whitespace-nowrap">
                ทั้งหมด
                <span class="text-[10px] font-bold bg-gray-500 text-white rounded-full min-w-[18px] h-[18px] px-1 flex items-center justify-center leading-none flex-shrink-0" x-text="countByStatus('all')"></span>
            </button>
            <button type="button" @click="recordFilter = 'unrecorded'"
                    :class="recordFilter === 'unrecorded' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                    class="flex-shrink-0 flex items-center gap-1.5 px-3 sm:px-3.5 py-2 sm:py-2.5 text-xs font-semibold rounded-lg transition-all whitespace-nowrap">
                ยังไม่บันทึก
                <span class="text-[10px] font-bold bg-gray-400 text-white rounded-full min-w-[18px] h-[18px] px-1 flex items-center justify-center leading-none flex-shrink-0" x-text="countByStatus('unrecorded')"></span>
            </button>
            <button type="button" @click="recordFilter = 'recorded'"
                    :class="recordFilter === 'recorded' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                    class="flex-shrink-0 flex items-center gap-1.5 px-3 sm:px-3.5 py-2 sm:py-2.5 text-xs font-semibold rounded-lg transition-all whitespace-nowrap">
                บันทึกครบแล้ว
                <span class="text-[10px] font-bold bg-brand-500 text-white rounded-full min-w-[18px] h-[18px] px-1 flex items-center justify-center leading-none flex-shrink-0" x-text="countByStatus('recorded')"></span>
            </button>
        </div>

        @if($rows->count() > 0)
            <span class="ml-auto text-xs text-gray-400 whitespace-nowrap">
                พบ <span class="font-semibold text-gray-600" x-text="filteredCount"></span> จาก {{ $rows->count() }} กลุ่ม
            </span>
        @endif
    </div>
</div>

@push('scripts')
<script>
    document.getElementById('masterMeterPeriodForm')?.querySelectorAll('[name="month"], [name="year"]').forEach(function (el) {
        el.addEventListener('change', function () {
            if (this.form.month.value && this.form.year.value) {
                this.form.submit();
            }
        });
    });
</script>
@endpush

@if($rows->isEmpty())
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-10 text-center">
        <div class="w-12 h-12 bg-gray-50 rounded-xl flex items-center justify-center mx-auto mb-3">
            <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"/>
            </svg>
        </div>
        <p class="text-gray-700 font-semibold text-sm">ยังไม่มีมิเตอร์หลักที่คุณต้องจด</p>
        <p class="text-gray-400 text-xs mt-1 leading-relaxed">
            แอดมินเป็นผู้กำหนดผู้จดมิเตอร์หลักแต่ละตัว - ถ้าทรัพย์ที่ผูกกับมิเตอร์หลักมีคุณเป็นผู้บริหารโครงการคนเดียว คุณจะเป็นผู้จดอัตโนมัติ
        </p>
    </div>
@else

    {{-- Mobile cards --}}
    <div class="md:hidden space-y-3 meter-list-counter">
        @foreach($rows as $row)
            @php $status = $statusFor($row); @endphp
            <a href="{{ route('master-meters.show', ['group' => $row->group_id, 'year' => $year, 'month' => $month]) }}"
               x-show="matches(@js($row->search_text), @js($row->record_status))"
               class="meter-card-in group block bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden transition-all duration-300 hover:shadow-lg hover:-translate-y-0.5 hover:border-gray-200"
               style="animation-delay: {{ min($loop->index, 11) * 40 }}ms">

                {{-- Card header --}}
                <div class="flex items-center gap-3 p-3 pb-2.5">
                    <span class="meter-row-num flex-shrink-0 w-6 text-center text-xs font-bold text-gray-400 tabular-nums"></span>
                    <div class="w-11 h-11 rounded-xl flex-shrink-0 bg-gradient-to-br from-violet-400 to-purple-600 flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21h18M5 21V7l8-4v18M13 21V9l6 3v9M9 9v.01M9 12v.01M9 15v.01"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="font-bold text-sm text-gray-800 truncate leading-snug">{{ $row->group_name }}</p>
                            <span class="inline-flex items-center gap-1 text-[10px] font-bold px-1.5 py-0.5 rounded-full flex-shrink-0 {{ $status['classes'] }}">
                                <svg class="w-2.5 h-2.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="{{ $status['icon'] }}"/></svg>
                                {{ $status['short'] }}
                            </span>
                        </div>
                        <p class="text-[10px] text-gray-400 tabular-nums mt-1">มิเตอร์หลักที่คุณจด: ไฟ {{ $row->electric_count }} · น้ำ {{ $row->water_count }}</p>
                    </div>
                    <svg class="w-4 h-4 text-gray-300 flex-shrink-0 transition-transform duration-300 group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </div>

                {{-- หน่วยรวมรายประเภท --}}
                <div class="mx-3 mb-2 rounded-lg overflow-hidden border border-gray-100">
                    @foreach(['electric', 'water'] as $type)
                        @continue($row->units[$type] === null)
                        @php $u = $row->units[$type]; $ts = $typeStyles[$type]; @endphp
                        <div class="grid grid-cols-[1fr_auto] items-center gap-2 px-3 py-2 {{ $ts['bg'] }} {{ $loop->last ? '' : 'border-b border-gray-100/80' }}">
                            <div class="flex items-center gap-1.5 min-w-0">
                                <span class="w-5 h-5 rounded-md {{ $ts['chip'] }} flex items-center justify-center text-[11px] flex-shrink-0">{{ $ts['emoji'] }}</span>
                                <span class="text-[11px] font-medium text-gray-600 truncate">{{ $ts['label'] }}</span>
                            </div>
                            <div class="text-right leading-tight tabular-nums">
                                <p class="text-xs font-semibold text-gray-800">{{ $fmtUnits($u['units']) }} <span class="font-normal text-gray-400">หน่วย</span></p>
                                @if($u['total'] > 1)
                                    <p class="text-[10px] text-gray-400">จด {{ $u['recorded'] }}/{{ $u['total'] }} ตัว</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                @if($row->latest_reading)
                    @php $line = $latestLine($row->latest_reading); @endphp
                    <p class="px-3 pb-3 text-[11px] text-gray-500 truncate">{{ $line['at'] }} <span class="text-gray-400">{{ $line['by'] }}</span></p>
                @endif
            </a>
        @endforeach
    </div>

    {{-- Desktop table --}}
    <div class="hidden md:block bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50/60">
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">ลำดับ</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">กลุ่มอสังหา</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">มิเตอร์หลัก</th>
                    <th class="text-right px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">ไฟฟ้า (หน่วย)</th>
                    <th class="text-right px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">น้ำ (หน่วย)</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">การจด</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">บันทึก/แก้ไขล่าสุด</th>
                    <th class="text-right px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">จัดการ</th>
                </tr>
            </thead>
            <tbody class="meter-list-counter">
                @foreach($rows as $row)
                    @php $status = $statusFor($row); @endphp
                    <tr x-show="matches(@js($row->search_text), @js($row->record_status))"
                        class="meter-row-in border-t border-gray-100 hover:bg-brand-50/30 transition-colors duration-200"
                        style="animation-delay: {{ min($loop->index, 11) * 40 }}ms">
                        <td class="px-5 py-3.5 text-xs font-bold text-gray-400 tabular-nums"><span class="meter-row-num"></span></td>
                        <td class="px-5 py-3.5">
                            <p class="font-bold text-sm text-gray-800 leading-snug">{{ $row->group_name }}</p>
                        </td>
                        <td class="px-5 py-3.5">
                            <p class="text-sm text-gray-700 whitespace-nowrap">⚡ {{ $row->electric_count }} · 💧 {{ $row->water_count }}</p>
                            <p class="text-xs text-gray-400">{{ $row->meter_count }} ตัว</p>
                        </td>
                        @foreach(['electric', 'water'] as $type)
                            @php $u = $row->units[$type]; @endphp
                            <td class="px-5 py-3.5 text-right tabular-nums whitespace-nowrap">
                                @if($u === null)
                                    <span class="text-gray-300">-</span>
                                @else
                                    <p class="text-sm font-semibold text-gray-800">{{ $fmtUnits($u['units']) }}</p>
                                    @if($u['total'] > 1)
                                        <p class="text-[11px] text-gray-400">จด {{ $u['recorded'] }}/{{ $u['total'] }} ตัว</p>
                                    @endif
                                @endif
                            </td>
                        @endforeach
                        <td class="px-5 py-3.5">
                            <span class="inline-flex items-center gap-1.5 text-xs font-bold px-2.5 py-1.5 rounded-full whitespace-nowrap {{ $status['classes'] }}">
                                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $status['icon'] }}"/></svg>
                                {{ $status['label'] }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5">
                            @if($row->latest_reading)
                                @php $line = $latestLine($row->latest_reading); @endphp
                                <p class="text-xs text-gray-600 whitespace-nowrap">{{ $line['at'] }}</p>
                                <p class="text-[11px] text-gray-400">{{ $line['by'] }}</p>
                            @else
                                <span class="text-gray-300">-</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <a href="{{ route('master-meters.show', ['group' => $row->group_id, 'year' => $year, 'month' => $month]) }}"
                               class="inline-flex items-center gap-1 text-brand-600 hover:text-brand-700 text-sm font-medium transition-colors whitespace-nowrap">
                                บันทึก/ดูข้อมูล
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div x-show="!hasMatches" x-cloak class="border-t border-gray-100 p-10 text-center">
            <p class="text-gray-700 font-semibold text-sm">ไม่พบกลุ่มอสังหาที่ตรงกับตัวกรอง</p>
            <p class="text-gray-400 text-xs mt-1">ลองเปลี่ยนคำค้นหาหรือตัวกรองสถานะการบันทึก</p>
        </div>
    </div>

    {{-- Mobile empty state --}}
    <div x-show="!hasMatches" x-cloak class="md:hidden bg-white rounded-2xl shadow-sm border border-gray-100 p-10 text-center">
        <p class="text-gray-700 font-semibold text-sm">ไม่พบกลุ่มอสังหาที่ตรงกับตัวกรอง</p>
        <p class="text-gray-400 text-xs mt-1">ลองเปลี่ยนคำค้นหาหรือตัวกรองสถานะการบันทึก</p>
    </div>
@endif

</div>{{-- /x-data --}}

@endsection
