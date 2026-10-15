@extends('layouts.app')

@section('title', 'บันทึกมิเตอร์')
@section('breadcrumb', 'บันทึกมิเตอร์น้ำ/ไฟรายเดือน')

@section('content')

{{-- หน้ารายการบันทึกมิเตอร์ - แท็บสถานะ ตัวกรอง คอลัมน์ และปุ่ม "เริ่มจดมิเตอร์" ล้อ happyest /admin/meter-readings
     และคู่มือ "จดมิเตอร์น้ำ/ไฟ ทีละทรัพย์จนครบ" (MeterReadingController::index()) - ตัวกรองทำฝั่งเซิร์ฟเวอร์ เพื่อให้
     ปุ่มทรัพย์ก่อนหน้า/ถัดไป และ "บันทึก แล้วไปทรัพย์ถัดไป" ในหน้าบันทึกใช้รายการชุดเดียวกับที่เห็นตรงนี้ --}}
@php
    $months = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม',
    ];
    $thaiShortMonths = [1 => 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $rentPeriodLabel = ($months[$month] ?? $month) . ' ' . ($year + 543);

    [$prevYear, $prevMonth] = $month === 1 ? [$year - 1, 12] : [$year, $month - 1];
    [$nextYear, $nextMonth] = $month === 12 ? [$year + 1, 1] : [$year, $month + 1];

    $statusQuery = $status !== 'all' ? ['status' => $status] : [];
    // ตัวกรองที่พกไปหน้าบันทึก - ปุ่มทรัพย์ก่อนหน้า/ถัดไปและ "บันทึก แล้วไปทรัพย์ถัดไป" ใช้รายการชุดเดียวกับที่เห็นตรงนี้
    $showQuery  = $scope + ['year' => $year, 'month' => $month] + $statusQuery;
    $hasFilters = ! empty($scope);

    // แท็บตามสถานะงวด (MeterReadingController::periodStatuses()) - "จดบางส่วน"/"รอยืนยัน" แสดงเฉพาะเมื่อมีรายการ
    $tabs = [
        'all'        => ['label' => 'ทั้งหมด'],
        'unrecorded' => ['label' => 'ยังไม่จด'],
        'partial'    => ['label' => 'จดบางส่วน', 'optional' => true],
        'draft'      => ['label' => 'รอยืนยัน', 'optional' => true],
        'recorded'   => ['label' => 'พร้อมออกใบ'],
        'invoiced'   => ['label' => 'ออกใบแจ้งหนี้แล้ว'],
    ];

    // ความหมายสถานะ + สิ่งที่ต้องทำต่อ (ล้อ config/admin_glossary.php 'admin.meter-readings.*' ของ happyest ปรับคำให้ตัวแทน)
    $statusMap = [
        'unrecorded' => ['label' => 'ยังไม่จด', 'cls' => 'text-amber-700 bg-amber-50 border-amber-200',
            'tip' => 'งวดนี้ยังไม่มีเลขมิเตอร์ของทรัพย์นี้ - กด "บันทึก/ดูข้อมูล" แล้วกรอกเลขมิเตอร์พร้อมรูป'],
        'partial'    => ['label' => 'จดบางส่วน', 'cls' => 'text-blue-700 bg-blue-50 border-blue-200',
            'tip' => 'จดแล้วบางมิเตอร์ ยังขาดบางตัว - เปิดทรัพย์นี้แล้วจดมิเตอร์ตัวที่เหลือ'],
        'draft'      => ['label' => 'รอยืนยัน', 'cls' => 'text-orange-700 bg-orange-50 border-orange-200',
            'tip' => 'จดครบแล้ว แต่ยังมีข้อมูลที่ไม่ได้ยืนยัน (ข้อมูลเก่า) - เปิดทรัพย์นี้แล้วกด "ยืนยันข้อมูล"'],
        'recorded'   => ['label' => 'พร้อมออกใบ', 'cls' => 'text-emerald-700 bg-emerald-50 border-emerald-200',
            'tip' => 'จดและยืนยันครบทุกมิเตอร์แล้ว - รอแอดมินออกใบแจ้งหนี้น้ำ/ไฟของงวดนี้'],
        'invoiced'   => ['label' => 'ออกใบแจ้งหนี้แล้ว', 'cls' => 'text-white bg-brand-700 border-brand-700',
            'tip' => 'ออกใบน้ำ/ไฟงวดนี้แล้ว ข้อมูลมิเตอร์ถูกล็อก - ถ้าต้องแก้เลขมิเตอร์ ให้แจ้งแอดมินลบใบแจ้งหนี้น้ำ/ไฟของงวดนี้ก่อน'],
    ];
    $statusFor = function ($row) use ($statusMap) {
        $sc = $statusMap[$row->status];
        if ($row->status === 'partial') {
            $sc['label'] = "จดแล้ว {$row->recorded_count}/{$row->meter_count}";
        }

        return $sc;
    };

    $todoCount = (int) (($counts['unrecorded'] ?? 0) + ($counts['partial'] ?? 0) + ($counts['draft'] ?? 0));
    $doneCount = (int) (($counts['recorded'] ?? 0) + ($counts['invoiced'] ?? 0));

    // วันเวลาแบบไทย เช่น "9 ต.ค. 2569 14:05 น." - เวลามี "น." เสมอ (เหมือนหน้าแอดมิน)
    $stamp = fn ($at) => $at->format('j') . ' ' . $thaiShortMonths[$at->month] . ' ' . ($at->year + 543) . ' ' . $at->format('H:i') . ' น.';

    $statusColor = fn ($ps) => ($ps && $ps->color && preg_match('/^#[0-9A-Fa-f]{6}$/', $ps->color)) ? $ps->color : '#6b7280';
@endphp

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
                จดเลขมิเตอร์ทุกทรัพย์ทุกเดือน เพื่อให้แอดมินออกใบค่าน้ำ/ไฟ
            </p>
        </div>
        <div class="hero-badge-row flex-shrink-0">
            @if($firstNeedsWorkId)
                {{-- เปิดทรัพย์แรกที่ยังต้องจด/ยืนยันในรายการนี้ แล้วกด "บันทึก แล้วไปทรัพย์ถัดไป" ต่อได้เลย (เหมือนแอดมิน) --}}
                <a href="{{ route('meters.show', ['property' => $firstNeedsWorkId] + $showQuery) }}"
                   title="เปิดทรัพย์แรกที่ยังต้องจด/ยืนยันในรายการนี้ แล้วกด &quot;บันทึก แล้วไปทรัพย์ถัดไป&quot; ต่อ"
                   class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-brand-800 shadow-sm hover:bg-brand-50 transition-colors">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    เริ่มจดมิเตอร์ ({{ number_format($needsWorkCount) }})
                </a>
            @elseif($counts['all'] > 0 && $todoCount === 0)
                <span class="inline-flex items-center gap-1.5 rounded-xl border px-3 py-1.5 text-xs font-bold text-white"
                      style="background:rgba(255,255,255,0.12); border-color:rgba(255,255,255,0.2); backdrop-filter:blur(8px);">
                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    จดครบทุกทรัพย์ของงวดนี้แล้ว
                </span>
            @endif
        </div>
    </div>

    {{-- Quick stats strip --}}
    <div class="hero-stats-row relative mt-4 sm:mt-5 pt-4 grid grid-cols-3 gap-2 sm:gap-3"
         style="border-top:1px solid rgba(255,255,255,0.12); z-index:2;">
        <div class="text-center">
            <p class="text-xl sm:text-2xl font-black text-white tabular-nums leading-none">{{ number_format($counts['all']) }}</p>
            <p class="text-[10px] sm:text-[11px] font-medium mt-1" style="color:rgba(255,255,255,0.6)">ทั้งหมด</p>
        </div>
        <div class="text-center" style="border-left:1px solid rgba(255,255,255,0.12)">
            <p class="text-xl sm:text-2xl font-black tabular-nums leading-none {{ $todoCount > 0 ? 'text-amber-300' : 'text-white' }}">{{ number_format($todoCount) }}</p>
            <p class="text-[10px] sm:text-[11px] font-medium mt-1" style="color:rgba(255,255,255,0.6)">ยังต้องจด</p>
        </div>
        <div class="text-center" style="border-left:1px solid rgba(255,255,255,0.12)">
            <p class="text-xl sm:text-2xl font-black text-white tabular-nums leading-none">{{ number_format($doneCount) }}</p>
            <p class="text-[10px] sm:text-[11px] font-medium mt-1" style="color:rgba(255,255,255,0.6)">จดครบแล้ว</p>
        </div>
    </div>
</div>

{{-- ── Filters (ส่งฟอร์ม GET - เปลี่ยนงวด/สถานะพื้นที่แล้วส่งทันที, ค้นหากด Enter หรือปุ่มค้นหา) ── --}}
<form method="GET" action="{{ route('meters.index') }}" id="meterFilterForm" class="flex flex-col gap-2.5 mb-4">
    @if($status !== 'all')
        <input type="hidden" name="status" value="{{ $status }}">
    @endif

    <div class="flex flex-col lg:flex-row gap-2.5">
        {{-- งวดเรียกเก็บพร้อมค่าเช่า: ‹ › เลื่อนทีละเดือน หรือเลือกเดือน/ปี --}}
        <div class="flex items-center gap-1.5 w-full lg:w-[24rem] flex-shrink-0">
            <a href="{{ route('meters.index', ['year' => $prevYear, 'month' => $prevMonth] + $scope + $statusQuery) }}"
               title="งวดก่อน" aria-label="งวดก่อน"
               class="w-10 h-[42px] flex-shrink-0 flex items-center justify-center rounded-xl border border-gray-300 bg-white text-gray-500 hover:bg-gray-50 hover:text-brand-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div class="flex-1 min-w-0">
                <x-form.month-year
                    name-month="month"
                    name-year="year"
                    :value-month="$month"
                    :value-year="$year"
                    :year-from="2025"
                    :year-to="now()->year + 1"
                />
            </div>
            <a href="{{ route('meters.index', ['year' => $nextYear, 'month' => $nextMonth] + $scope + $statusQuery) }}"
               title="งวดถัดไป" aria-label="งวดถัดไป"
               class="w-10 h-[42px] flex-shrink-0 flex items-center justify-center rounded-xl border border-gray-300 bg-white text-gray-500 hover:bg-gray-50 hover:text-brand-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>

        {{-- ค้นหา (รหัสทรัพย์ หรือชื่อทรัพย์ - เหมือนแอดมิน) --}}
        <div class="flex items-center gap-1.5 flex-1 min-w-0 lg:min-w-[14rem]">
            <div class="flex-1 min-w-0">
                <x-form.input name="search" :value="$scope['search'] ?? ''" placeholder="ค้นหารหัสทรัพย์ หรือชื่อทรัพย์..." clearable />
            </div>
            <x-btn type="submit" variant="outline" class="h-[42px] !px-4 flex-shrink-0">ค้นหา</x-btn>
        </div>

        {{-- ตัวกรองสถานะพื้นที่เป็นปุ่มข้างแท็บสถานะด้านล่าง - เก็บค่าไว้ให้ค้นหา/เปลี่ยนงวดแล้วไม่หลุด --}}
        @if(($scope['property_status_id'] ?? '') !== '')
            <input type="hidden" name="property_status_id" value="{{ $scope['property_status_id'] }}">
        @endif

        {{-- ส่งออกรายงาน Excel --}}
        <div class="self-start flex-shrink-0">
            <x-btn type="button" onclick="openModal('export-meters-modal')" variant="excel" size="md" class="whitespace-nowrap meter-export-btn">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/>
                </svg>
                ส่งออก Excel
            </x-btn>
        </div>
    </div>

    @if($hasFilters)
        {{-- หาทรัพย์ไม่เจอ -> กด "ล้างทั้งหมด" (คู่มือของแอดมิน) --}}
        <div class="flex items-center gap-2 flex-wrap text-xs">
            <span class="text-gray-400">ตัวกรอง:</span>
            @if(($scope['search'] ?? '') !== '')
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-brand-50 border border-brand-200 text-brand-800 font-semibold">ค้นหา: {{ $scope['search'] }}</span>
            @endif
            @if(($scope['property_status_id'] ?? '') !== '')
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-brand-50 border border-brand-200 text-brand-800 font-semibold">สถานะพื้นที่: {{ optional($propertyStatuses->firstWhere('id', $scope['property_status_id']))->name ?? $scope['property_status_id'] }}</span>
            @endif
            <a href="{{ route('meters.index', ['year' => $year, 'month' => $month] + $statusQuery) }}"
               class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-red-600 hover:bg-red-50 font-semibold transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                ล้างทั้งหมด
            </a>
        </div>
    @endif
</form>

@push('scripts')
<script>
    // เปลี่ยนเดือน/ปีของงวดแล้วส่งฟอร์มทันที - ไม่ต้องกดปุ่ม (หน้าแรกของรายการเสมอ)
    document.getElementById('meterFilterForm')?.querySelectorAll('[name="month"], [name="year"]').forEach(function (el) {
        el.addEventListener('change', function () {
            if (this.form.month.value && this.form.year.value) {
                this.form.submit();
            }
        });
    });
</script>
@endpush

{{-- ── แท็บสถานะงวด + ปุ่มสถานะพื้นที่ (อยู่แถวเดียวกัน ขึ้นบรรทัดใหม่เองเมื่อจอแคบ) ── --}}
@php
    $currentPs = (string) ($scope['property_status_id'] ?? '');
    $searchScope = array_diff_key($scope, ['property_status_id' => true]);
@endphp
<div class="flex items-center gap-2 flex-wrap mb-4">
    <div class="flex gap-1.5 bg-gray-100 rounded-xl p-1.5 overflow-x-auto max-w-full">
        @foreach($tabs as $key => $t)
            @continue(($t['optional'] ?? false) && ($counts[$key] ?? 0) === 0 && $status !== $key)
            @php $active = $status === $key; @endphp
            <a href="{{ route('meters.index', ['year' => $year, 'month' => $month] + $scope + ($key !== 'all' ? ['status' => $key] : [])) }}"
               @if($key !== 'all') title="{{ $statusMap[$key]['tip'] }}" @endif
               class="flex-shrink-0 flex items-center gap-1.5 px-3 sm:px-3.5 py-2 sm:py-2.5 text-xs font-semibold rounded-lg transition-all whitespace-nowrap {{ $active ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                {{ $t['label'] }}
                <span class="text-[10px] font-bold rounded-full min-w-[18px] h-[18px] px-1 flex items-center justify-center leading-none flex-shrink-0 {{ $active ? 'bg-brand-600 text-white' : 'bg-gray-400 text-white' }}">{{ number_format($counts[$key] ?? 0) }}</span>
            </a>
        @endforeach
    </div>

    {{-- สถานะพื้นที่ - จำนวนนับตามคำค้น + แท็บสถานะงวดที่เลือกอยู่ (MeterReadingController::index() $propertyStatusCounts) --}}
    @if($propertyStatuses->isNotEmpty())
        <div class="flex gap-1.5 bg-gray-100 rounded-xl p-1.5 overflow-x-auto max-w-full" aria-label="สถานะพื้นที่">
            <a href="{{ route('meters.index', ['year' => $year, 'month' => $month] + $searchScope + $statusQuery) }}"
               class="flex-shrink-0 flex items-center gap-1.5 px-3 sm:px-3.5 py-2 sm:py-2.5 text-xs font-semibold rounded-lg transition-all whitespace-nowrap {{ $currentPs === '' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                สถานะพื้นที่ทั้งหมด
            </a>
            @foreach($propertyStatuses as $ps)
                @php
                    $psColor  = $statusColor($ps);
                    $psActive = $currentPs === (string) $ps->id;
                @endphp
                <a href="{{ route('meters.index', ['year' => $year, 'month' => $month] + $searchScope + ['property_status_id' => $ps->id] + $statusQuery) }}"
                   class="flex-shrink-0 flex items-center gap-1.5 px-3 sm:px-3.5 py-2 sm:py-2.5 text-xs font-semibold rounded-lg transition-all whitespace-nowrap {{ $psActive ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                    <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" style="background: {{ $psColor }};"></span>
                    {{ $ps->name }}
                    <span class="text-[10px] font-bold text-white rounded-full min-w-[18px] h-[18px] px-1 flex items-center justify-center leading-none flex-shrink-0" style="background: {{ $psColor }};">{{ number_format($propertyStatusCounts[(string) $ps->id] ?? 0) }}</span>
                </a>
            @endforeach
        </div>
    @endif
</div>

@if($paginator->count() === 0)
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-10 text-center">
        <div class="w-12 h-12 bg-gray-50 rounded-xl flex items-center justify-center mx-auto mb-3">
            <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
            </svg>
        </div>
        <p class="text-gray-700 font-semibold text-sm">ไม่มีทรัพย์ที่เปิดใช้งานระบบมิเตอร์ในรายการนี้</p>
        @if($hasFilters)
            <p class="text-gray-400 text-xs mt-1">หาทรัพย์ไม่เจอ ลองกด "ล้างทั้งหมด" ที่ตัวกรองด้านบน</p>
        @endif
    </div>
@else

    {{-- Mobile cards --}}
    <div class="md:hidden space-y-3">
        @foreach($paginator as $row)
            @php
                $sc      = $statusFor($row);
                $ps      = $row->property->propertyStatus;
                $psColor = $statusColor($ps);
                $enabled = (bool) $row->property->is_utility_metering_enabled;
            @endphp
            <div class="meter-card-in bg-white rounded-2xl shadow-sm border border-gray-100 p-3 {{ $enabled ? '' : 'opacity-60' }}"
                 style="animation-delay: {{ min($loop->index, 11) * 40 }}ms">
                <div class="flex items-start justify-between gap-2.5">
                    <div class="flex items-start gap-2.5 min-w-0">
                        <span class="flex-shrink-0 w-6 pt-0.5 text-center text-xs font-bold text-gray-400 tabular-nums">{{ $paginator->firstItem() + $loop->index }}</span>
                        <div class="min-w-0">
                            @include('meters.partials.property-cell')
                        </div>
                    </div>
                    <div class="flex flex-col items-end flex-shrink-0">
                        <span title="{{ $sc['tip'] }}" class="inline-flex items-center text-[10px] font-bold px-2 py-0.5 rounded-full border {{ $sc['cls'] }}">{{ $sc['label'] }}</span>
                        @include('meters.partials.invoice-codes')
                    </div>
                </div>

                @if($ps)
                    <div class="mt-2 pl-8">
                        <span class="inline-flex items-center gap-1 text-[10px] font-semibold px-1.5 py-0.5 rounded-full border"
                              style="color: {{ $psColor }}; background: {{ $psColor }}18; border-color: {{ $psColor }}40;">
                            <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" style="background: {{ $psColor }};"></span>
                            {{ $ps->name }}
                        </span>
                    </div>
                @endif

                <div class="mt-2 pl-8">
                    @include('meters.partials.meter-items', ['items' => $row->meter_items])
                </div>

                @if($row->recorded)
                    <div class="mt-2 pl-8">
                        @include('meters.partials.recorded-by', ['recorded' => $row->recorded])
                    </div>
                @endif

                <div class="mt-3 pl-8">
                    @if(! $enabled)
                        <span title="ปิดใช้งานระบบมิเตอร์สำหรับทรัพย์สินนี้ ไม่สามารถบันทึกได้" class="text-xs text-gray-400">ปิดใช้งานมิเตอร์</span>
                    @else
                        <a href="{{ route('meters.show', ['property' => $row->property->id] + $showQuery) }}"
                           class="w-full inline-flex items-center justify-center gap-1 px-3 py-2 rounded-xl border border-brand-600 text-brand-700 text-sm font-semibold hover:bg-brand-50 transition-colors">
                            บันทึก/ดูข้อมูล
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- Desktop table --}}
    <div class="hidden md:block bg-white rounded-2xl shadow-sm border border-gray-100 overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50/60">
                    <th class="text-center px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider w-14">ลำดับ</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">ทรัพย์</th>
                    <th class="text-center px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">สถานะพื้นที่</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider whitespace-nowrap">เลขมิเตอร์ <span class="font-medium normal-case tracking-normal">(เดือนก่อน → เดือนนี้)</span></th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">บันทึกล่าสุด</th>
                    <th class="text-center px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">สถานะ</th>
                    <th class="text-center px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider w-px whitespace-nowrap">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                @foreach($paginator as $row)
                    @php
                        $sc      = $statusFor($row);
                        $ps      = $row->property->propertyStatus;
                        $psColor = $statusColor($ps);
                        $enabled = (bool) $row->property->is_utility_metering_enabled;
                    @endphp
                    <tr class="meter-row-in border-t border-gray-100 hover:bg-brand-50/30 transition-colors duration-200 {{ $enabled ? '' : 'opacity-60' }}"
                        style="animation-delay: {{ min($loop->index, 11) * 40 }}ms">
                        <td class="px-4 py-3 text-center text-xs font-bold text-gray-400 tabular-nums">{{ $paginator->firstItem() + $loop->index }}</td>
                        <td class="px-4 py-3">
                            @include('meters.partials.property-cell')
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($ps)
                                <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full border whitespace-nowrap"
                                      style="color: {{ $psColor }}; background: {{ $psColor }}18; border-color: {{ $psColor }}40;">
                                    <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" style="background: {{ $psColor }};"></span>
                                    {{ $ps->name }}
                                </span>
                            @else
                                <span class="text-xs text-gray-300">-</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @include('meters.partials.meter-items', ['items' => $row->meter_items])
                        </td>
                        <td class="px-4 py-3">
                            @include('meters.partials.recorded-by', ['recorded' => $row->recorded])
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span title="{{ $sc['tip'] }}" class="inline-flex items-center text-xs font-bold px-2.5 py-1 rounded-full border whitespace-nowrap {{ $sc['cls'] }}">{{ $sc['label'] }}</span>
                            @include('meters.partials.invoice-codes')
                        </td>
                        <td class="px-4 py-3 text-center whitespace-nowrap">
                            @if(! $enabled)
                                <span title="ปิดใช้งานระบบมิเตอร์สำหรับทรัพย์สินนี้ ไม่สามารถบันทึกได้" class="text-xs text-gray-400">ปิดใช้งานมิเตอร์</span>
                            @else
                                <a href="{{ route('meters.show', ['property' => $row->property->id] + $showQuery) }}"
                                   class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-brand-600 text-brand-700 text-xs font-semibold hover:bg-brand-50 transition-colors">
                                    บันทึก/ดูข้อมูล
                                </a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <x-pagination :paginator="$paginator" label="ทรัพย์" />
@endif

{{-- ── ส่งออกรายงานมิเตอร์น้ำ/ไฟ (Excel) ── --}}
<x-modal id="export-meters-modal" title="ส่งออกรายงานมิเตอร์น้ำ/ไฟ" size="md">
    <p class="text-sm text-gray-500 -mt-1 mb-4">เลือกช่วงงวดอ่านมิเตอร์และทรัพย์สินที่ต้องการ แล้วกดดาวน์โหลด</p>

    <form id="exportMetersForm" class="space-y-5" onsubmit="return false;">
        <x-form.month-year
            name-month="start_month"
            name-year="start_year"
            label="ตั้งแต่งวดอ่านมิเตอร์"
            required
            :value-month="now()->month"
            :value-year="now()->year"
            :year-from="2025"
            :year-to="now()->year + 1"
        />
        <x-form.month-year
            name-month="end_month"
            name-year="end_year"
            label="ถึงงวดอ่านมิเตอร์"
            required
            :value-month="now()->month"
            :value-year="now()->year"
            :year-from="2025"
            :year-to="now()->year + 1"
        />
        <x-form.select name="property_code" label="ทรัพย์สิน (ไม่ระบุ = ส่งออกทุกรายการ)" placeholder="ทุกรายการ">
            <option value="">ทุกรายการ</option>
            @foreach($exportProperties as $p)
                <option value="{{ $p->property_code }}">{{ $p->property_code }} - {{ $p->title }}</option>
            @endforeach
        </x-form.select>
    </form>

    <div id="exportMetersError" class="hidden mt-4 flex items-start gap-2.5 bg-red-50 border border-red-200 text-red-700 rounded-xl px-3.5 py-3 text-sm"></div>

    <x-slot:footer>
        <button type="button" onclick="closeModal('export-meters-modal')"
                class="px-4 py-2 rounded-xl text-sm font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 transition-colors">
            ยกเลิก
        </button>
        <button type="button" id="exportMetersSubmitBtn" onclick="submitExportMetersForm()"
                class="inline-flex items-center justify-center gap-2 px-5 py-2.5 text-sm font-semibold rounded-xl bg-brand-600 hover:bg-brand-700 text-white transition-colors disabled:opacity-60 disabled:cursor-not-allowed">
            <svg id="exportMetersSpinner" class="hidden w-4 h-4 animate-spin flex-shrink-0" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <span id="exportMetersSubmitLabel">ดาวน์โหลดรายงาน Excel</span>
        </button>
    </x-slot:footer>
</x-modal>

@push('scripts')
<script>
    async function submitExportMetersForm() {
        const form   = document.getElementById('exportMetersForm');
        const btn    = document.getElementById('exportMetersSubmitBtn');
        const spinner = document.getElementById('exportMetersSpinner');
        const label  = document.getElementById('exportMetersSubmitLabel');
        const errorBox = document.getElementById('exportMetersError');

        errorBox.classList.add('hidden');
        errorBox.textContent = '';

        const params = new URLSearchParams(new FormData(form));
        const url = "{{ route('meters.export.download') }}?" + params.toString();

        btn.disabled = true;
        spinner.classList.remove('hidden');
        label.textContent = 'กำลังสร้างไฟล์ กรุณารอสักครู่...';

        try {
            const res = await fetch(url, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!res.ok) {
                let message = (res.status === 401 || res.status === 419)
                    ? 'หมดเวลาเข้าสู่ระบบ กรุณารีเฟรชหน้าแล้วเข้าสู่ระบบใหม่'
                    : 'สร้างไฟล์ Excel ไม่สำเร็จ ลองเลือกช่วงเดือนให้สั้นลงแล้วลองใหม่';
                try {
                    const data = await res.json();
                    if (data.message) message = data.message;
                } catch (e) { /* non-JSON error body - keep message above */ }
                errorBox.textContent = message;
                errorBox.classList.remove('hidden');
                return;
            }

            // หมดเวลาเข้าสู่ระบบแล้ว fetch จะตามไปหน้า login (200 HTML) - อย่าบันทึกหน้านั้นเป็น .xlsx (เหมือนแอดมิน)
            const contentType = res.headers.get('content-type') || '';
            if (contentType.indexOf('spreadsheetml') === -1) {
                errorBox.textContent = 'หมดเวลาเข้าสู่ระบบ กรุณารีเฟรชหน้าแล้วเข้าสู่ระบบใหม่';
                errorBox.classList.remove('hidden');
                return;
            }

            const blob = await res.blob();
            const cd = res.headers.get('content-disposition') || '';
            const utf8Match = cd.match(/filename\*=UTF-8''([^;]+)/);
            const asciiMatch = cd.match(/filename="([^"]+)"/);
            const filename = utf8Match ? decodeURIComponent(utf8Match[1]) : (asciiMatch ? asciiMatch[1] : 'meter-export.xlsx');

            const blobUrl = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = blobUrl;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            a.remove();
            setTimeout(() => URL.revokeObjectURL(blobUrl), 2000);

            closeModal('export-meters-modal');
        } catch (e) {
            errorBox.textContent = 'เชื่อมต่อเซิร์ฟเวอร์ไม่ได้ ตรวจสอบอินเทอร์เน็ตแล้วลองใหม่';
            errorBox.classList.remove('hidden');
        } finally {
            btn.disabled = false;
            spinner.classList.add('hidden');
            label.textContent = 'ดาวน์โหลดรายงาน Excel';
        }
    }
</script>
@endpush

@endsection
