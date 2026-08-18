@extends('layouts.app')

@section('title', 'บันทึกมิเตอร์')
@section('breadcrumb', 'บันทึกมิเตอร์น้ำ/ไฟรายเดือน')

@section('content')

@php
    $happyestPublic = rtrim(env('HAPPYEST_APP_URL', 'http://127.0.0.1/happyest/public'), '/');

    $resolveImageUrl = function ($property) use ($happyestPublic) {
        $media = $property->primaryImageMedia;
        if (! $media || ! $media->file_path) {
            return null;
        }

        return str_starts_with($media->file_path, 'http')
            ? $media->file_path
            : $happyestPublic . '/storage/' . $media->file_path;
    };

    $months = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม',
    ];
    $rentPeriodLabel = ($months[$month] ?? $month) . ' ' . ($year + 543);

    $totalCount      = $rows->count();
    $recordedCount   = $rows->where('recorded_count', '>', 0)->count();
    $unrecordedCount = $totalCount - $recordedCount;

    $statusFor = function ($row) {
        if ($row->already_invoiced) {
            return [
                'label'   => 'ออกใบแจ้งหนี้แล้ว',
                'short'   => 'ออกบิลแล้ว',
                'classes' => 'text-indigo-700 bg-indigo-50 border border-indigo-200',
                'icon'    => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z',
            ];
        }

        if ($row->meter_count > 0 && $row->recorded_count === $row->meter_count) {
            return [
                'label'   => 'บันทึกแล้ว',
                'short'   => 'ครบแล้ว',
                'classes' => 'text-emerald-700 bg-emerald-50 border border-emerald-200',
                'icon'    => 'M5 13l4 4L19 7',
            ];
        }

        if ($row->recorded_count > 0) {
            return [
                'label'   => "บันทึกแล้ว {$row->recorded_count}/{$row->meter_count}",
                'short'   => "{$row->recorded_count}/{$row->meter_count}",
                'classes' => 'text-blue-700 bg-blue-50 border border-blue-200',
                'icon'    => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
            ];
        }

        return [
            'label'   => 'ยังไม่บันทึก',
            'short'   => 'ยังไม่บันทึก',
            'classes' => 'text-gray-600 bg-gray-100 border border-gray-200',
            'icon'    => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        ];
    };

    $titleShort = function ($title) {
        return \Illuminate\Support\Str::limit($title, 25, '...');
    };

    // ─── สถานะพื้นที่ (hr_property_statuses - ชื่อ/สีกำหนดเองได้จากแอดมิน จึงใช้สีจริงจาก DB) ───
    $propertyStatusFor = function ($row) {
        $ps = $row->property->propertyStatus;

        return [
            'label' => $ps->name ?? 'ไม่ระบุสถานะ',
            'slug'  => $ps->slug ?? 'unknown',
            'color' => ($ps->color && preg_match('/^#[0-9A-Fa-f]{6}$/', $ps->color)) ? $ps->color : '#6b7280',
        ];
    };

    // ─── ข้อความค้นหา (รหัส/ชื่อทรัพย์/ชื่อลูกค้า) และข้อมูลสถานะพื้นที่/ระบบมิเตอร์ เตรียมไว้ล่วงหน้าเพื่อกรองฝั่ง client ───
    $rows = $rows->map(function ($row) use ($propertyStatusFor) {
        $tenant = $row->property->activeBooking?->customer;
        $row->search_text = strtolower(
            ($row->property->title ?? '') . ' ' .
            ($row->property->property_code ?? '') . ' ' .
            ($tenant?->full_name ?? '')
        );

        $propStatus = $propertyStatusFor($row);
        $row->property_status_label = $propStatus['label'];
        $row->property_status_slug  = $propStatus['slug'];
        $row->property_status_color = $propStatus['color'];
        $row->meter_enabled         = (bool) $row->property->is_utility_metering_enabled;

        return $row;
    });

    // ตัวเลือกตัวกรองสถานะพื้นที่ - เอาเฉพาะสถานะที่มีอยู่จริงในทรัพย์สินของตัวแทนคนนี้
    $propertyStatusOptions = $rows->unique('property_status_slug')
        ->map(fn ($row) => [
            'slug'  => $row->property_status_slug,
            'label' => $row->property_status_label,
            'color' => $row->property_status_color,
            'count' => $rows->where('property_status_slug', $row->property_status_slug)->count(),
        ])
        ->sortBy('label')
        ->values();
@endphp

<div x-data="{
        search: '',
        recordFilter: 'unrecorded',
        propertyStatusFilter: 'all',
        items: @js($rows->map(fn ($row) => [
            'search'   => $row->search_text,
            'recorded' => $row->recorded_count > 0,
            'status'   => $row->property_status_slug,
        ])->values()),
        matches(search_text, recorded, status_slug) {
            const q = this.search.toLowerCase().trim();
            return (q === '' || search_text.includes(q))
                && (this.recordFilter === 'all' || (this.recordFilter === 'recorded') === recorded)
                && (this.propertyStatusFilter === 'all' || this.propertyStatusFilter === status_slug);
        },
        get filteredCount() {
            return this.items.filter(it => this.matches(it.search, it.recorded, it.status)).length;
        },
        get hasMatches() {
            return this.filteredCount > 0;
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
            <p class="text-xs font-medium mb-1" style="color: rgba(255,255,255,0.6)">เรียกเก็บพร้อมค่าเช่า {{ $rentPeriodLabel }}</p>
            <h2 class="text-xl lg:text-2xl font-black text-white leading-tight truncate">บันทึกมิเตอร์น้ำ/ไฟ</h2>
            <p class="text-sm mt-1.5" style="color: rgba(255,255,255,0.65)">
                บันทึกและยืนยันค่ามิเตอร์รายเดือนของทรัพย์สินในความดูแลของคุณ
            </p>
        </div>
        <div class="hero-badge-row flex-shrink-0">
            <span class="inline-flex items-center gap-1.5 rounded-xl border px-3 py-1.5 text-xs font-bold text-white"
                  style="background:rgba(255,255,255,0.12); border-color:rgba(255,255,255,0.2); backdrop-filter:blur(8px);">
                {{ $totalCount }} ทรัพย์สิน
            </span>
        </div>
    </div>

    {{-- Quick stats strip --}}
    <div class="hero-stats-row relative mt-4 sm:mt-5 pt-4 grid grid-cols-3 gap-2 sm:gap-3"
         style="border-top:1px solid rgba(255,255,255,0.12); z-index:2;">
        <div class="text-center">
            <p class="text-xl sm:text-2xl font-black text-white tabular-nums leading-none">{{ $totalCount }}</p>
            <p class="text-[10px] sm:text-[11px] font-medium mt-1" style="color:rgba(255,255,255,0.6)">ทั้งหมด</p>
        </div>
        <div class="text-center" style="border-left:1px solid rgba(255,255,255,0.12)">
            <p class="text-xl sm:text-2xl font-black text-white tabular-nums leading-none">{{ $recordedCount }}</p>
            <p class="text-[10px] sm:text-[11px] font-medium mt-1" style="color:rgba(255,255,255,0.6)">บันทึกแล้ว</p>
        </div>
        <div class="text-center" style="border-left:1px solid rgba(255,255,255,0.12)">
            <p class="text-xl sm:text-2xl font-black tabular-nums leading-none {{ $unrecordedCount > 0 ? 'text-amber-300' : 'text-white' }}">{{ $unrecordedCount }}</p>
            <p class="text-[10px] sm:text-[11px] font-medium mt-1" style="color:rgba(255,255,255,0.6)">ยังไม่บันทึก</p>
        </div>
    </div>
</div>

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
                   placeholder="ค้นหารหัสอสังหา, ชื่อทรัพย์สิน หรือชื่อลูกค้า..."
                   class="w-full pl-11 pr-9 py-2.5 text-sm bg-transparent focus:outline-none text-gray-800 placeholder-gray-400">
            <button x-show="search" x-cloak @click="search = ''"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-300 hover:text-gray-500 transition-colors focus:outline-none"
                    tabindex="-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- งวดเรียกเก็บพร้อมค่าเช่า (เปลี่ยนแล้วส่งฟอร์มทันที - ไม่ต้องกดปุ่ม) --}}
        <div class="w-full lg:w-[30%]">
            <form method="GET" id="rentPeriodForm">
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
        {{-- สถานะการบันทึก --}}
        <div class="flex gap-1.5 bg-gray-100 rounded-xl p-1.5 overflow-x-auto">
            <button type="button" @click="recordFilter = 'unrecorded'"
                    :class="recordFilter === 'unrecorded' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                    class="flex-shrink-0 flex items-center gap-1.5 px-3 sm:px-3.5 py-2 sm:py-2.5 text-xs font-semibold rounded-lg transition-all whitespace-nowrap">
                ยังไม่บันทึก
                <span class="text-[10px] font-bold bg-gray-400 text-white rounded-full min-w-[18px] h-[18px] px-1 flex items-center justify-center leading-none flex-shrink-0">{{ $unrecordedCount }}</span>
            </button>
            <button type="button" @click="recordFilter = 'recorded'"
                    :class="recordFilter === 'recorded' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                    class="flex-shrink-0 flex items-center gap-1.5 px-3 sm:px-3.5 py-2 sm:py-2.5 text-xs font-semibold rounded-lg transition-all whitespace-nowrap">
                บันทึกแล้ว
                <span class="text-[10px] font-bold bg-brand-500 text-white rounded-full min-w-[18px] h-[18px] px-1 flex items-center justify-center leading-none flex-shrink-0">{{ $recordedCount }}</span>
            </button>
            <button type="button" @click="recordFilter = 'all'"
                    :class="recordFilter === 'all' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                    class="flex-shrink-0 px-3 sm:px-3.5 py-2 sm:py-2.5 text-xs font-semibold rounded-lg transition-all whitespace-nowrap">
                ทั้งหมด
            </button>
        </div>

        {{-- สถานะพื้นที่ --}}
        @if($propertyStatusOptions->count() > 1)
            <div class="flex gap-1.5 bg-gray-100 rounded-xl p-1.5 overflow-x-auto">
                <button type="button" @click="propertyStatusFilter = 'all'"
                        :class="propertyStatusFilter === 'all' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                        class="flex-shrink-0 px-3 sm:px-3.5 py-2 sm:py-2.5 text-xs font-semibold rounded-lg transition-all whitespace-nowrap">
                    สถานะพื้นที่ทั้งหมด
                </button>
                @foreach($propertyStatusOptions as $opt)
                    <button type="button" @click="propertyStatusFilter = '{{ $opt['slug'] }}'"
                            :class="propertyStatusFilter === '{{ $opt['slug'] }}' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                            class="flex-shrink-0 flex items-center gap-1.5 px-3 sm:px-3.5 py-2 sm:py-2.5 text-xs font-semibold rounded-lg transition-all whitespace-nowrap">
                        <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" style="background: {{ $opt['color'] }};"></span>
                        {{ $opt['label'] }}
                        <span class="text-[10px] font-bold text-white rounded-full min-w-[18px] h-[18px] px-1 flex items-center justify-center leading-none flex-shrink-0" style="background: {{ $opt['color'] }};">{{ $opt['count'] }}</span>
                    </button>
                @endforeach
            </div>
        @endif
        @if($rows->count() > 0)
            <span class="ml-auto text-xs text-gray-400 whitespace-nowrap">
                พบ <span class="font-semibold text-gray-600" x-text="filteredCount"></span> จาก {{ $rows->count() }} รายการ
            </span>
        @endif
    </div>
</div>

@push('scripts')
<script>
    document.getElementById('rentPeriodForm')?.querySelectorAll('[name="month"], [name="year"]').forEach(function (el) {
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
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
            </svg>
        </div>
        <p class="text-gray-400 text-sm">ไม่มีอสังหาริมทรัพย์ที่เปิดใช้งานระบบมิเตอร์ในความดูแลของคุณ</p>
    </div>
@else

    {{-- Mobile cards --}}
    <div class="md:hidden space-y-3">
        @foreach($rows as $row)
            @php $status = $statusFor($row); $imgUrl = $resolveImageUrl($row->property); @endphp
            <a href="{{ route('meters.show', ['property' => $row->property->id, 'year' => $year, 'month' => $month]) }}"
               x-show="matches(@js($row->search_text), @js($row->recorded_count > 0), @js($row->property_status_slug))"
               class="meter-card-in group block bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden transition-all duration-300 {{ $row->meter_enabled ? 'hover:shadow-lg hover:-translate-y-0.5 hover:border-gray-200' : 'opacity-60 grayscale-[40%] pointer-events-none' }}"
               style="animation-delay: {{ min($loop->index, 11) * 40 }}ms"
               @unless($row->meter_enabled) tabindex="-1" aria-disabled="true" @endunless>

                {{-- Card header --}}
                <div class="flex items-center gap-3 p-3 pb-2.5">
                    <span class="flex-shrink-0 w-6 text-center text-xs font-bold text-gray-400 tabular-nums">{{ $loop->iteration }}</span>
                    <div class="w-12 h-12 rounded-xl bg-gray-100 flex-shrink-0 overflow-hidden flex items-center justify-center ring-1 ring-black/5">
                        @if($imgUrl)
                            <img src="{{ $imgUrl }}" class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105" alt="">
                        @else
                            <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        @if($row->property->property_code)
                            <div class="flex items-center gap-2">
                                <p class="font-mono font-bold text-sm text-gray-800 truncate leading-snug">{{ $row->property->property_code }}</p>
                                <span class="inline-flex items-center gap-1 text-[10px] font-bold px-1.5 py-0.5 rounded-full flex-shrink-0 {{ $status['classes'] }}">
                                    <svg class="w-2.5 h-2.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="{{ $status['icon'] }}"/></svg>
                                    {{ $status['short'] }}
                                </span>
                            </div>
                            <p class="text-[11px] text-gray-400 truncate mt-0.5">{{ $titleShort($row->property->title) }}</p>
                        @else
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-semibold text-gray-800 truncate">{{ $titleShort($row->property->title) }}</p>
                                <span class="inline-flex items-center gap-1 text-[10px] font-bold px-1.5 py-0.5 rounded-full flex-shrink-0 {{ $status['classes'] }}">
                                    <svg class="w-2.5 h-2.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="{{ $status['icon'] }}"/></svg>
                                    {{ $status['short'] }}
                                </span>
                            </div>
                        @endif
                        <div class="flex items-center gap-1.5 mt-1 flex-wrap">
                            <span class="inline-flex items-center gap-1 text-[10px] font-semibold px-1.5 py-0.5 rounded-full border"
                                  style="color: {{ $row->property_status_color }}; background: {{ $row->property_status_color }}18; border-color: {{ $row->property_status_color }}40;">
                                <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" style="background: {{ $row->property_status_color }};"></span>
                                {{ $row->property_status_label }}
                            </span>
                            <span class="text-[10px] text-gray-400 tabular-nums">มิเตอร์: น้ำ {{ $row->water_count }} · ไฟ {{ $row->electric_count }}</span>
                        </div>
                    </div>
                    @if(! $row->meter_enabled)
                        <span title="ปิดใช้งานระบบมิเตอร์สำหรับทรัพย์สินนี้ ไม่สามารถบันทึกได้" class="flex-shrink-0 w-7 h-7 flex items-center justify-center rounded-lg text-gray-300">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </span>
                    @elseif($row->already_invoiced)
                        <span title="ออกใบแจ้งหนี้แล้ว ล็อกการลบ" class="flex-shrink-0 w-7 h-7 flex items-center justify-center rounded-lg text-indigo-300">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </span>
                    @elseif($row->recorded_count > 0)
                        <button type="button"
                                @click.prevent.stop="openDeleteMeterConfirm('{{ route('meters.destroy', ['property' => $row->property->id, 'year' => $year, 'month' => $month, 'scope' => 'rent']) }}', '{{ $row->property->property_code ?: $row->property->title }}')"
                                class="flex-shrink-0 w-7 h-7 flex items-center justify-center rounded-lg text-gray-300 hover:text-red-600 hover:bg-red-50 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M4 7h16M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/>
                            </svg>
                        </button>
                    @else
                        <svg class="w-4 h-4 text-gray-300 flex-shrink-0 transition-transform duration-300 group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    @endif
                </div>

                {{-- Utility breakdown --}}
                <div class="mx-3 mb-2 rounded-lg overflow-hidden border border-gray-100">
                    {{-- Row: ไฟฟ้า --}}
                    <div class="grid grid-cols-[1fr_auto] items-center gap-2 px-3 py-2 bg-amber-50/40 border-b border-gray-100/80">
                        <div class="flex items-center gap-1.5 min-w-0">
                            <span class="w-5 h-5 rounded-md bg-amber-100 flex items-center justify-center text-[11px] flex-shrink-0">⚡</span>
                            <span class="text-[11px] font-medium text-gray-600 truncate">ไฟฟ้า</span>
                        </div>
                        <div class="text-right leading-tight tabular-nums">
                            <p class="text-[10px] text-gray-400">{{ number_format($row->electric_units, 0) }} หน่วย</p>
                            <p class="text-xs font-semibold text-gray-800">{{ number_format($row->electric_amount, 0) }} ฿</p>
                        </div>
                    </div>
                    {{-- Row: น้ำ --}}
                    <div class="grid grid-cols-[1fr_auto] items-center gap-2 px-3 py-2 bg-blue-50/30 border-b border-gray-100/80">
                        <div class="flex items-center gap-1.5 min-w-0">
                            <span class="w-5 h-5 rounded-md bg-blue-100 flex items-center justify-center text-[11px] flex-shrink-0">💧</span>
                            <span class="text-[11px] font-medium text-gray-600 truncate">น้ำ</span>
                        </div>
                        <div class="text-right leading-tight tabular-nums">
                            <p class="text-[10px] text-gray-400">{{ number_format($row->water_units, 0) }} หน่วย</p>
                            <p class="text-xs font-semibold text-gray-800">{{ number_format($row->water_amount, 0) }} ฿</p>
                        </div>
                    </div>
                    {{-- Row: ส่วนกลาง --}}
                    <div class="grid grid-cols-[1fr_auto] items-center gap-2 px-3 py-2 bg-gray-50/50 border-b border-gray-100/80">
                        <div class="flex items-center gap-1.5 min-w-0">
                            <span class="w-5 h-5 rounded-md bg-gray-100 flex items-center justify-center flex-shrink-0">
                                <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            </span>
                            <span class="text-[11px] font-medium text-gray-600 truncate">ส่วนกลาง</span>
                        </div>
                        <span class="text-xs font-semibold text-gray-800 tabular-nums text-right">{{ $row->property->common_fee_per_month ? number_format($row->property->common_fee_per_month, 0) . ' ฿' : '-' }}</span>
                    </div>
                    {{-- Row: รวม --}}
                    <div class="grid grid-cols-[1fr_auto] items-center gap-2 px-3 py-2" style="background: linear-gradient(135deg, rgba(42,79,31,0.06) 0%, rgba(42,79,31,0.02) 100%);">
                        <span class="text-[11px] font-bold text-gray-700">รวมทั้งหมด</span>
                        <span class="text-sm font-black text-brand-700 tabular-nums text-right">{{ number_format($row->total_amount, 0) }} ฿</span>
                    </div>
                </div>
            </a>
        @endforeach
    </div>

    {{-- Desktop table --}}
    <div class="hidden md:block bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50/60">
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">ลำดับ</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">ทรัพย์สิน</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">สถานะพื้นที่</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">มิเตอร์</th>
                    <th class="text-right px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">หน่วยที่ใช้</th>
                    <th class="text-right px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">ราคาคำนวณ</th>
                    <th class="text-right px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">ค่าส่วนกลาง</th>
                    <th class="text-right px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">ราคารวม</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">สถานะ</th>
                    <th class="text-right px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    @php $status = $statusFor($row); @endphp
                    <tr x-show="matches(@js($row->search_text), @js($row->recorded_count > 0), @js($row->property_status_slug))"
                        class="meter-row-in border-t border-gray-100 hover:bg-brand-50/30 transition-colors duration-200 {{ ! $row->meter_enabled ? 'opacity-60' : '' }}"
                        style="animation-delay: {{ min($loop->index, 11) * 40 }}ms">
                        <td class="px-5 py-3.5 text-xs font-bold text-gray-400 tabular-nums">{{ $loop->iteration }}</td>
                        <td class="px-5 py-3.5">
                            @if($row->property->property_code)
                                <p class="font-mono font-bold text-sm text-gray-800 leading-snug">{{ $row->property->property_code }}</p>
                                <p class="text-[11px] text-gray-400 truncate mt-0.5">{{ $titleShort($row->property->title) }}</p>
                            @else
                                <p class="font-medium text-gray-800">{{ $titleShort($row->property->title) }}</p>
                            @endif
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full border"
                                  style="color: {{ $row->property_status_color }}; background: {{ $row->property_status_color }}18; border-color: {{ $row->property_status_color }}40;">
                                <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" style="background: {{ $row->property_status_color }};"></span>
                                {{ $row->property_status_label }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5">
                            <p class="text-sm text-gray-700">น้ำ {{ $row->water_count }} · ไฟ {{ $row->electric_count }}</p>
                            <p class="text-xs text-gray-400">{{ $row->meter_count }} จุด</p>
                        </td>
                        <td class="px-5 py-3.5 text-right tabular-nums">
                            <p class="text-xs text-gray-600">⚡ <span class="font-medium text-gray-800">{{ number_format($row->electric_units, 0) }}</span> <span class="text-gray-400">หน่วย</span></p>
                            <p class="text-xs text-gray-600">💧 <span class="font-medium text-gray-800">{{ number_format($row->water_units, 0) }}</span> <span class="text-gray-400">หน่วย</span></p>
                        </td>
                        <td class="px-5 py-3.5 text-right tabular-nums">
                            <p class="text-xs text-gray-600">⚡ <span class="font-medium text-gray-800">{{ number_format($row->electric_amount, 0) }}</span> <span class="text-gray-400">฿</span></p>
                            <p class="text-xs text-gray-600">💧 <span class="font-medium text-gray-800">{{ number_format($row->water_amount, 0) }}</span> <span class="text-gray-400">฿</span></p>
                        </td>
                        <td class="px-5 py-3.5 text-right tabular-nums">
                            @if($row->property->common_fee_per_month)
                                <span class="font-medium text-gray-800">{{ number_format($row->property->common_fee_per_month, 0) }}</span>
                                <span class="text-xs text-gray-400">฿</span>
                            @else
                                <span class="text-gray-300">-</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-right tabular-nums">
                            <span class="font-bold text-brand-700">{{ number_format($row->total_amount, 0) }}</span>
                            <span class="text-xs text-gray-400">฿</span>
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="inline-flex items-center gap-1.5 text-xs font-bold px-2.5 py-1.5 rounded-full {{ $status['classes'] }}">
                                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $status['icon'] }}"/></svg>
                                {{ $status['label'] }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <div class="inline-flex items-center gap-3">
                                @if(! $row->meter_enabled)
                                    <span title="ปิดใช้งานระบบมิเตอร์สำหรับทรัพย์สินนี้ ไม่สามารถบันทึกได้" class="inline-flex items-center gap-1 text-gray-300 text-sm font-medium cursor-not-allowed">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                        </svg>
                                        ปิดใช้งานมิเตอร์
                                    </span>
                                @else
                                    @if($row->already_invoiced)
                                        <span title="ออกใบแจ้งหนี้แล้ว ล็อกการลบ" class="inline-flex items-center gap-1 text-indigo-300 text-sm font-medium">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                            </svg>
                                            ล็อก
                                        </span>
                                    @elseif($row->recorded_count > 0)
                                        <button type="button"
                                                @click="openDeleteMeterConfirm('{{ route('meters.destroy', ['property' => $row->property->id, 'year' => $year, 'month' => $month, 'scope' => 'rent']) }}', '{{ $row->property->property_code ?: $row->property->title }}')"
                                                class="inline-flex items-center gap-1 text-gray-400 hover:text-red-600 text-sm font-medium transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M4 7h16M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/>
                                            </svg>
                                            ลบ
                                        </button>
                                    @endif
                                    <a href="{{ route('meters.show', ['property' => $row->property->id, 'year' => $year, 'month' => $month]) }}"
                                       class="inline-flex items-center gap-1 text-brand-600 hover:text-brand-700 text-sm font-medium transition-colors">
                                        บันทึก/ดูข้อมูล
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Empty state inside table wrapper so it stays attached to thead --}}
        <div x-show="!hasMatches" x-cloak class="border-t border-gray-100 p-10 text-center">
            <div class="w-12 h-12 bg-gray-50 rounded-xl flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            <p class="text-gray-700 font-semibold text-sm">ไม่พบรายการที่ตรงกับตัวกรอง</p>
            <p class="text-gray-400 text-xs mt-1">ลองเปลี่ยนคำค้นหาหรือตัวกรองสถานะการบันทึก</p>
        </div>
    </div>

    {{-- Mobile empty state --}}
    <div x-show="!hasMatches" x-cloak class="md:hidden bg-white rounded-2xl shadow-sm border border-gray-100 p-10 text-center">
        <div class="w-12 h-12 bg-gray-50 rounded-xl flex items-center justify-center mx-auto mb-3">
            <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
        </div>
        <p class="text-gray-700 font-semibold text-sm">ไม่พบรายการที่ตรงกับตัวกรอง</p>
        <p class="text-gray-400 text-xs mt-1">ลองเปลี่ยนคำค้นหาหรือตัวกรองสถานะการบันทึก</p>
    </div>
@endif

</div>{{-- /x-data --}}

{{-- ── Delete Meter Readings Confirm Modal (shared, action set per-row via JS) ── --}}
<x-confirm-modal
    id="delete-meter-confirm"
    title="ยืนยันลบข้อมูลมิเตอร์"
    action=""
    method="DELETE"
    icon-variant="danger"
    confirm-label="ลบข้อมูล"
    cancel-label="ยกเลิก">
    <div class="flex flex-col gap-3">
        <p class="text-sm text-gray-700 leading-relaxed">
            คุณต้องการ<span class="font-semibold text-red-600">ลบข้อมูลมิเตอร์ทั้งหมด</span>ของ
            <span id="delete-meter-confirm_target" class="font-semibold"></span> งวด {{ $rentPeriodLabel }} ใช่หรือไม่?
        </p>
        <div class="flex items-start gap-2.5 bg-amber-50 border border-amber-200 rounded-xl px-3.5 py-3">
            <svg class="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.07 16.5c-.77.833.192 2.5 1.732 2.5z"/>
            </svg>
            <p class="text-xs text-amber-700 leading-relaxed">
                สถานะจะกลับเป็น <span class="font-semibold">ยังไม่บันทึก</span> และต้องบันทึกเลขมิเตอร์ใหม่ทั้งหมด
            </p>
        </div>
    </div>
</x-confirm-modal>

@push('scripts')
<script>
    function openDeleteMeterConfirm(url, targetLabel) {
        document.getElementById('delete-meter-confirm_form').action = url;
        document.getElementById('delete-meter-confirm_target').textContent = targetLabel;
        openModal('delete-meter-confirm');
    }
</script>
@endpush

@endsection
