@extends('layouts.app')

@section('title', 'บันทึกมิเตอร์')
@section('breadcrumb', $property->title)

@section('content')

@php
    $months = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม',
    ];
    $periodLabel = ($months[$month] ?? $month) . ' ' . ($year + 543);

    $prevMonth = $month === 1 ? 12 : $month - 1;
    $prevYear  = $month === 1 ? $year - 1 : $year;
    $nextMonth = $month === 12 ? 1 : $month + 1;
    $nextYear  = $month === 12 ? $year + 1 : $year;

    $typeStyles = [
        'water'    => [
            'label' => 'น้ำ', 'ring' => 'border-cyan-200', 'badge' => 'text-cyan-700 bg-cyan-100',
            'icon'  => 'from-cyan-400 to-sky-600',
            'path'  => 'M12 2C12 2 5 10.5 5 15a7 7 0 0014 0c0-4.5-7-13-7-13z',
        ],
        'electric' => [
            'label' => 'ไฟฟ้า', 'ring' => 'border-amber-200', 'badge' => 'text-amber-700 bg-amber-100',
            'icon'  => 'from-amber-400 to-orange-500',
            'path'  => 'M13 10V3L4 14h7v7l9-11h-7z',
        ],
    ];

    $metersByType = $meters->groupBy('meter_type');

    // Show the recorded unit price exactly as entered (e.g. 8.698542 stays 8.698542, 8 stays 8) -
    // price_per_unit is decimal:14 cast, so trim the padding zeros rather than fixing a decimal count.
    $fmtUnitPrice = function ($value) {
        $str = (string) $value;
        if (str_contains($str, '.')) {
            $str = rtrim(rtrim($str, '0'), '.');
        }
        return $str;
    };
@endphp

@if($allConfirmed && ! $alreadyInvoiced)
    {{-- ── ยืนยันข้อมูลแล้ว - แสดงบนสุดของหน้า ── --}}
    <div class="relative overflow-hidden rounded-2xl mb-2.5 sm:mb-3 border border-emerald-200/80 shadow-sm"
         style="background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);">
        <div class="pointer-events-none absolute -top-10 -right-10 w-32 h-32 rounded-full bg-emerald-300/25"></div>
        <div class="pointer-events-none absolute -bottom-8 left-10 w-20 h-20 rounded-full bg-emerald-300/15"></div>
        <div class="relative flex items-center gap-3 px-4 py-3 sm:px-5 sm:py-3.5">
            <div class="flex-shrink-0 w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-emerald-500 shadow-md shadow-emerald-500/30 flex items-center justify-center">
                <svg class="w-5 h-5 sm:w-6 sm:h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-sm sm:text-base font-bold text-emerald-900 leading-tight">ยืนยันข้อมูลมิเตอร์แล้ว</p>
                <p class="text-xs sm:text-sm text-emerald-700/80 leading-relaxed mt-0.5">
                    พร้อมให้แอดมินออกใบแจ้งหนี้น้ำ/ไฟของงวดนี้
                </p>
            </div>
        </div>
    </div>
@endif

{{-- ── Compact header ───────────────────────────────────────────────────────── --}}
<div class="relative overflow-hidden rounded-xl mb-2.5 sm:mb-3 bg-white border border-gray-100 shadow-sm p-2.5 sm:p-3">
    <div class="pointer-events-none absolute -top-10 -right-10 w-32 h-32 rounded-full bg-sky-50 opacity-70"></div>

    <div class="relative flex items-center justify-between gap-2 mb-2">
        <a href="{{ route('meters.index', ['year' => $year, 'month' => $month]) }}"
           class="inline-flex items-center gap-1 -ml-1 px-1.5 py-1 rounded-lg text-xs font-semibold text-gray-500 hover:text-brand-600 hover:bg-gray-50 transition-colors flex-shrink-0">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            รายการทรัพย์สิน
        </a>

        <div class="flex items-center gap-1.5 sm:gap-2">
            @if($alreadyInvoiced)
                <span class="inline-flex items-center gap-1 sm:gap-1.5 text-[11px] sm:text-xs font-semibold px-2 sm:px-2.5 py-1 rounded-full border text-indigo-700 bg-indigo-50 border-indigo-200 whitespace-nowrap">
                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    ออกใบแจ้งหนี้แล้ว
                </span>
            @elseif($allConfirmed)
                <span class="inline-flex items-center gap-1 sm:gap-1.5 text-[11px] sm:text-xs font-semibold px-2 sm:px-2.5 py-1 rounded-full border text-green-700 bg-green-50 border-green-200 whitespace-nowrap">
                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    ยืนยันแล้ว
                </span>
                <button type="button"
                        onclick="openDeleteMeterConfirm()"
                        class="inline-flex items-center justify-center w-7 h-7 sm:w-auto sm:h-auto sm:gap-1.5 text-xs font-semibold sm:px-2.5 sm:py-1 rounded-full border text-red-600 bg-white border-gray-200 hover:bg-red-50 hover:border-red-200 transition-colors flex-shrink-0">
                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M4 7h16M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/>
                    </svg>
                    <span class="hidden sm:inline">ลบข้อมูล</span>
                </button>
            @elseif($currentReadings->count() > 0)
                <span class="inline-flex items-center gap-1 sm:gap-1.5 text-[11px] sm:text-xs font-semibold px-2 sm:px-2.5 py-1 rounded-full border text-blue-700 bg-blue-50 border-blue-200 whitespace-nowrap">
                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    {{ $currentReadings->count() }}/{{ $meters->count() }}
                </span>
                <button type="button"
                        onclick="openDeleteMeterConfirm()"
                        class="inline-flex items-center justify-center w-7 h-7 sm:w-auto sm:h-auto sm:gap-1.5 text-xs font-semibold sm:px-2.5 sm:py-1 rounded-full border text-red-600 bg-white border-gray-200 hover:bg-red-50 hover:border-red-200 transition-colors flex-shrink-0">
                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M4 7h16M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/>
                    </svg>
                    <span class="hidden sm:inline">ลบข้อมูล</span>
                </button>
            @else
                <span class="inline-flex items-center gap-1.5 text-[11px] sm:text-xs font-semibold px-2 sm:px-2.5 py-1 rounded-full border text-gray-600 bg-gray-50 border-gray-200 whitespace-nowrap">
                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400 flex-shrink-0"></span>
                    ยังไม่บันทึก
                </span>
            @endif
        </div>
    </div>

    <div class="relative flex items-center justify-center gap-1.5">
        <a href="{{ route('meters.show', ['property' => $property->id, 'year' => $prevYear, 'month' => $prevMonth]) }}"
           class="w-7 h-7 sm:w-8 sm:h-8 flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 hover:border-gray-300 transition-colors flex-shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <p class="text-sm font-bold text-gray-800 leading-tight px-1 whitespace-nowrap">งวด {{ $periodLabel }}</p>
        <a href="{{ route('meters.show', ['property' => $property->id, 'year' => $nextYear, 'month' => $nextMonth]) }}"
           class="w-7 h-7 sm:w-8 sm:h-8 flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 hover:border-gray-300 transition-colors flex-shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>
    </div>
</div>

<form method="POST" action="{{ route('meters.store', $property->id) }}" enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="billing_year" value="{{ $year }}">
    <input type="hidden" name="billing_month" value="{{ $month }}">

    {{-- ── Summary strip: common fee + invoice display settings + rent period (รวมเป็นการ์ดเดียวเพื่อลดพื้นที่บนมือถือ) ── --}}
    <div class="meter-row-in bg-white rounded-xl shadow-sm border border-gray-100 px-3 py-2 sm:px-3.5 sm:py-2.5 mb-2.5 sm:mb-3">
        <div class="flex items-center gap-2.5 flex-wrap">
            <div class="w-7 h-7 sm:w-8 sm:h-8 flex-shrink-0 rounded-lg bg-gradient-to-br from-violet-400 to-purple-600 flex items-center justify-center">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21h18M5 21V7l8-4v18M13 21V9l6 3v9M9 9v.01M9 12v.01M9 15v.01"/>
                </svg>
            </div>
            <div class="min-w-0 mr-auto">
                <p class="text-[11px] text-gray-400 truncate leading-tight">ค่าส่วนกลาง/เดือน</p>
                <p class="text-sm font-bold text-gray-800 leading-tight">฿{{ number_format($property->common_fee_per_month ?? 0, 2) }}</p>
            </div>
            <span title="รูปภาพบนใบแจ้งหนี้: {{ $property->show_meter_image_on_invoice ? 'แสดง' : 'ไม่แสดง' }}"
                  class="inline-flex items-center gap-1 text-[11px] font-medium px-2 py-0.5 rounded-full border whitespace-nowrap {{ $property->show_meter_image_on_invoice ? 'text-emerald-700 bg-emerald-50 border-emerald-200' : 'text-gray-400 bg-gray-50 border-gray-200' }}">
                <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M4 6h16v12H4V6z"/></svg>
                รูปภาพ: {{ $property->show_meter_image_on_invoice ? 'แสดง' : 'ไม่แสดง' }}
            </span>
            <span title="ที่อยู่จัดส่งหน้าถัดไป: {{ $property->show_shipping_address_on_invoice ? 'แสดง' : 'ไม่แสดง' }}"
                  class="inline-flex items-center gap-1 text-[11px] font-medium px-2 py-0.5 rounded-full border whitespace-nowrap {{ $property->show_shipping_address_on_invoice ? 'text-emerald-700 bg-emerald-50 border-emerald-200' : 'text-gray-400 bg-gray-50 border-gray-200' }}">
                <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                ที่อยู่: {{ $property->show_shipping_address_on_invoice ? 'แสดง' : 'ไม่แสดง' }}
            </span>
        </div>

        <input type="hidden" name="rent_month" value="{{ $rentMonth }}">
        <input type="hidden" name="rent_year" value="{{ $rentYear }}">
    </div>

    <div class="space-y-2 sm:space-y-2.5">
        @foreach($metersByType as $type => $typeMeters)
            @php
                $style           = $typeStyles[$type] ?? ['label' => $type, 'ring' => 'border-gray-200', 'badge' => 'text-gray-700 bg-gray-100', 'icon' => 'from-gray-400 to-gray-600', 'path' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'];
                $typeReadings    = $typeMeters->map(fn ($m) => $currentReadings[$m->id] ?? null)->filter();
                $typeUnitsTotal  = $typeReadings->sum('units_used');
                $typeAmountTotal = $typeReadings->sum('amount');
                $multiMeter      = $typeMeters->count() > 1;
            @endphp
            @if($multiMeter)
                <div class="meter-card-in bg-white rounded-xl shadow-sm border {{ $style['ring'] }} p-2.5 sm:p-3.5 transition-shadow duration-300 hover:shadow-md"
                     style="animation-delay: {{ $loop->index * 60 }}ms">
                    <div class="flex items-center justify-between mb-2.5 sm:mb-3 flex-wrap gap-2">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 sm:w-8 sm:h-8 flex-shrink-0 rounded-lg bg-gradient-to-br {{ $style['icon'] }} flex items-center justify-center">
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $style['path'] }}"/>
                                </svg>
                            </div>
                            <div>
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $style['badge'] }}">{{ $style['label'] }}</span>
                                <span class="text-xs text-gray-400 ml-1">{{ $typeMeters->count() }} จุด</span>
                            </div>
                        </div>
                        @if($typeReadings->isNotEmpty())
                            <span class="text-xs sm:text-sm font-semibold text-gray-700">{{ number_format($typeUnitsTotal, 2) }} หน่วย &middot; ฿{{ number_format($typeAmountTotal, 2) }}</span>
                        @endif
                    </div>

                    <div class="space-y-2.5 sm:space-y-3">
                        @foreach($typeMeters as $i => $meter)
                            @php
                                $reading    = $currentReadings[$meter->id] ?? null;
                                $previous   = $previousReadings[$meter->id] ?? null;
                                $namePrefix = 'readings[' . $meter->id . ']';

                                $previousInit = $previous !== null
                                    ? $previous
                                    : old('readings.' . $meter->id . '.previous_reading', $reading->previous_reading ?? 0);
                                $currentInit  = old('readings.' . $meter->id . '.current_reading', $reading->current_reading ?? 0);
                                $oldFinalInit = old('readings.' . $meter->id . '.old_meter_final_reading', $reading->old_meter_final_reading ?? null);
                                $newStartInit = old('readings.' . $meter->id . '.new_meter_start_reading', $reading->new_meter_start_reading ?? null);
                                $maxValueInit = old('readings.' . $meter->id . '.meter_max_value', $reading->meter_max_value ?? null);
                                // เคยบันทึกมิเตอร์งวดนี้แล้ว -> ยึดราคาต่อหน่วยที่บันทึกไว้ (snapshot ใน ag_meter_readings)
                                // ไม่ใช้ราคาจาก master (hr_property_meters) ซ้ำ เพื่อไม่ให้ราคาขยับถ้าแอดมินแก้ราคา master ทีหลัง
                                $effectiveUnitPrice = $reading?->price_per_unit ?? $meter->price_per_unit;
                            @endphp
                            <div class="meter-row-in relative rounded-lg border {{ $style['ring'] }} bg-gray-50/70 p-2.5 pl-3.5 sm:p-3 sm:pl-4 overflow-hidden"
                                 style="animation-delay: {{ $i * 70 }}ms"
                                 x-data="meterImageRow({
                                     existingImageUrl: @js($reading?->image_path ? route('meters.image', $reading->id) : null),
                                     reset: {{ ($reading->meter_reset ?? false) ? 'true' : 'false' }},
                                     changed: {{ ($reading->meter_changed ?? false) ? 'true' : 'false' }},
                                     pricePerUnit: {{ (float) $effectiveUnitPrice }},
                                     previousReading: {{ (int) $previousInit }},
                                     currentReading: {{ (int) $currentInit }},
                                     oldFinal: @js($oldFinalInit === null || $oldFinalInit === '' ? null : (int) $oldFinalInit),
                                     newStart: @js($newStartInit === null || $newStartInit === '' ? null : (int) $newStartInit),
                                     maxOverride: @js($maxValueInit === null || $maxValueInit === '' ? null : (int) $maxValueInit),
                                 })">
                                <span class="meter-accent-bar absolute left-0 top-0 bottom-0 w-1 bg-gradient-to-b {{ $style['icon'] }}" style="animation-delay: {{ $i * 70 }}ms"></span>
                                <div class="flex items-center justify-between mb-2 sm:mb-2.5 flex-wrap gap-1">
                                    <span class="text-xs font-medium text-gray-500 flex items-center gap-1.5">
                                        <span class="meter-badge-pop inline-flex items-center justify-center w-5 h-5 rounded-full text-[10px] font-bold text-white bg-gradient-to-br {{ $style['icon'] }} shadow-sm"
                                              style="animation-delay: {{ $i * 70 + 80 }}ms">{{ $i + 1 }}</span>
                                        {{ $style['label'] }} ตัวที่ {{ $i + 1 }} &middot;
                                        ฿{{ $fmtUnitPrice($effectiveUnitPrice) }}/หน่วย
                                    </span>
                                    <span x-show="currentReading > 0" x-cloak class="text-xs font-semibold text-gray-600" x-text="'≈ ' + liveSummary"></span>
                                </div>

                                @include('meters.partials.reading-fields')
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                @php
                    $meter      = $typeMeters->first();
                    $reading    = $currentReadings[$meter->id] ?? null;
                    $previous   = $previousReadings[$meter->id] ?? null;
                    $namePrefix = 'readings[' . $meter->id . ']';

                    $previousInit = $previous !== null
                        ? $previous
                        : old('readings.' . $meter->id . '.previous_reading', $reading->previous_reading ?? 0);
                    $currentInit  = old('readings.' . $meter->id . '.current_reading', $reading->current_reading ?? 0);
                    $oldFinalInit = old('readings.' . $meter->id . '.old_meter_final_reading', $reading->old_meter_final_reading ?? null);
                    $newStartInit = old('readings.' . $meter->id . '.new_meter_start_reading', $reading->new_meter_start_reading ?? null);
                    $maxValueInit = old('readings.' . $meter->id . '.meter_max_value', $reading->meter_max_value ?? null);
                    // เคยบันทึกมิเตอร์งวดนี้แล้ว -> ยึดราคาต่อหน่วยที่บันทึกไว้ (snapshot ใน ag_meter_readings)
                    // ไม่ใช้ราคาจาก master (hr_property_meters) ซ้ำ เพื่อไม่ให้ราคาขยับถ้าแอดมินแก้ราคา master ทีหลัง
                    $effectiveUnitPrice = $reading?->price_per_unit ?? $meter->price_per_unit;
                @endphp
                <div class="meter-card-in bg-white rounded-xl shadow-sm border {{ $style['ring'] }} p-2.5 sm:p-3.5 transition-shadow duration-300 hover:shadow-md"
                     style="animation-delay: {{ $loop->index * 60 }}ms"
                     x-data="meterImageRow({
                         existingImageUrl: @js($reading?->image_path ? route('meters.image', $reading->id) : null),
                         reset: {{ ($reading->meter_reset ?? false) ? 'true' : 'false' }},
                         changed: {{ ($reading->meter_changed ?? false) ? 'true' : 'false' }},
                         pricePerUnit: {{ (float) $effectiveUnitPrice }},
                         previousReading: {{ (int) $previousInit }},
                         currentReading: {{ (int) $currentInit }},
                         oldFinal: @js($oldFinalInit === null || $oldFinalInit === '' ? null : (int) $oldFinalInit),
                         newStart: @js($newStartInit === null || $newStartInit === '' ? null : (int) $newStartInit),
                         maxOverride: @js($maxValueInit === null || $maxValueInit === '' ? null : (int) $maxValueInit),
                     })">
                    <div class="flex items-center justify-between mb-2.5 sm:mb-3 flex-wrap gap-2">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 sm:w-8 sm:h-8 flex-shrink-0 rounded-lg bg-gradient-to-br {{ $style['icon'] }} flex items-center justify-center">
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $style['path'] }}"/>
                                </svg>
                            </div>
                            <span class="text-xs font-medium text-gray-500 flex items-center gap-1.5 flex-wrap">
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $style['badge'] }}">{{ $style['label'] }}</span>
                                ฿{{ $fmtUnitPrice($effectiveUnitPrice) }}/หน่วย
                            </span>
                        </div>
                        <span x-show="currentReading > 0" x-cloak class="text-xs font-semibold text-gray-600" x-text="'≈ ' + liveSummary"></span>
                    </div>

                    @include('meters.partials.reading-fields')
                </div>
            @endif
        @endforeach

        <div class="meter-card-in bg-white rounded-xl shadow-sm border border-gray-100 p-2.5 sm:p-3.5">
            <x-form.textarea
                name="remark"
                label="หมายเหตุ"
                rows="2"
                :value="old('remark', $currentReadings->first()?->remark ?? '')"
                :disabled="$alreadyInvoiced" />
        </div>
    </div>

    {{--
        ปุ่ม "ยืนยันข้อมูล" ใช้ formaction/formmethod ยิงไป meters.confirm แทนที่จะเป็น <form> แยก -
        เพราะปุ่มลอย (fixed) ที่อยู่ด้านล่างสุดของหน้าจอมือถือจะบังปุ่มที่อยู่นอกฟอร์มนี้เสมอ (ทดสอบแล้วกดไม่ติด
        เพราะโดนแถบ "บันทึกข้อมูลมิเตอร์" ทับ) การอยู่ในฟอร์มเดียวกันทำให้ทั้งสองปุ่มอยู่ใน tap target
        ที่ไม่ทับกันแน่นอน และใช้ CSRF token เดียวกับฟอร์มหลักได้เลย
    --}}
    @unless($alreadyInvoiced)
    {{-- ปุ่มบันทึก/ยืนยัน - เดสก์ท็อปใช้ปุ่มปกติท้ายฟอร์ม --}}
    <div class="mt-4 hidden lg:flex justify-end gap-2">
        @if($allRecorded && !$allConfirmed)
        <button type="submit"
                formaction="{{ route('meters.confirm', ['property' => $property->id, 'year' => $year, 'month' => $month]) }}"
                formmethod="POST"
                class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            ยืนยันข้อมูล
        </button>
        @endif
        <x-btn type="submit" variant="primary">บันทึกข้อมูลมิเตอร์</x-btn>
    </div>

    {{-- ปุ่มบันทึก/ยืนยันลอย (มือถือ) - อยู่เหนือแถบเมนูล่างเสมอ กดได้ทันทีโดยไม่ต้องเลื่อนหน้าจอ --}}
    <div class="lg:hidden fixed inset-x-0 z-30 bg-white/95 backdrop-blur border-t border-gray-200 px-4 py-2.5 shadow-[0_-4px_12px_rgba(0,0,0,0.06)] flex gap-2"
         style="bottom: calc(64px + env(safe-area-inset-bottom, 0px));">
        @if($allRecorded && !$allConfirmed)
        <button type="submit"
                formaction="{{ route('meters.confirm', ['property' => $property->id, 'year' => $year, 'month' => $month]) }}"
                formmethod="POST"
                class="flex-1 flex items-center justify-center gap-2 bg-emerald-600 active:bg-emerald-700 text-white font-semibold text-sm py-3 rounded-xl transition-colors tap-effect shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            ยืนยันข้อมูล
        </button>
        @endif
        <button type="submit"
                class="flex-1 flex items-center justify-center gap-2 bg-brand-600 active:bg-brand-700 text-white font-semibold text-sm py-3 rounded-xl transition-colors tap-effect shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            บันทึก
        </button>
    </div>
    <div class="lg:hidden" style="height: 84px;" aria-hidden="true"></div>
    @endunless
</form>

{{-- ── Confirm / status banner (informational only - action buttons are above, inside the form) ── --}}
@if($alreadyInvoiced)
    <div class="mt-2.5 sm:mt-3 rounded-2xl border border-indigo-200 bg-indigo-50 shadow-sm px-4 py-3.5 sm:px-5 sm:py-4">
        <div class="flex items-start gap-3">
            <div class="flex-shrink-0 w-9 h-9 rounded-xl bg-indigo-100 border border-indigo-200 flex items-center justify-center">
                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-sm font-bold text-indigo-900 leading-tight">ออกใบแจ้งหนี้แล้ว &middot; ข้อมูลถูกล็อก</p>
                <p class="text-xs text-indigo-700/80 leading-relaxed mt-1">
                    ออกใบแจ้งหนี้น้ำ/ไฟของงวดนี้ไปแล้ว ข้อมูลมิเตอร์งวดนี้ถูกล็อกไว้ ไม่สามารถแก้ไข ลบ หรือยืนยันซ้ำได้
                </p>
            </div>
        </div>
    </div>
@elseif($allRecorded)
    <div class="mt-2.5 sm:mt-3 flex items-start gap-2.5 bg-emerald-50 border border-emerald-200 rounded-xl px-3.5 py-3">
        <svg class="w-4 h-4 text-emerald-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        <p class="text-xs text-emerald-700 leading-relaxed">
            <span class="font-semibold">บันทึกข้อมูลมิเตอร์ครบทุกตัวแล้ว</span> — กด "ยืนยันข้อมูล" ด้านล่างเพื่อให้พร้อมออกใบแจ้งหนี้ที่หน้าแอดมิน
        </p>
    </div>
@endif

@if($currentReadings->count() > 0 && !$alreadyInvoiced)
{{-- ── Delete Meter Readings Confirm Modal ── --}}
<x-confirm-modal
    id="delete-meter-confirm"
    title="ยืนยันลบข้อมูลมิเตอร์"
    :action="route('meters.destroy', ['property' => $property->id, 'year' => $year, 'month' => $month])"
    method="DELETE"
    icon-variant="danger"
    confirm-label="ลบข้อมูล"
    cancel-label="ยกเลิก">
    <div class="flex flex-col gap-3">
        <p class="text-sm text-gray-700 leading-relaxed">
            คุณต้องการ<span class="font-semibold text-red-600">ลบข้อมูลมิเตอร์ทั้งหมด</span>ของ {{ $property->title }} งวด {{ $periodLabel }} ใช่หรือไม่?
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

<script>
    function openDeleteMeterConfirm() {
        openModal('delete-meter-confirm');
    }
</script>
@endif

@push('scripts')
<script>
    const METER_IMAGE_MAX_BYTES = 10 * 1024 * 1024; // ต้องตรงกับ max:10240 ใน MeterReadingController::store()

    // ประมาณจุดสูงสุดของมิเตอร์จากจำนวนหลักของเลขเดือนก่อน - ต้องตรงกับ
    // MeterReading::heuristicMaxValue() ฝั่งเซิร์ฟเวอร์ เพื่อให้ตัวเลขพรีวิวตรงกับค่าที่บันทึกจริง
    function heuristicMaxValue(previous) {
        if (previous >= 10000) return 99999;
        if (previous >= 1000) return 9999;
        if (previous >= 100) return 999;
        return 9999;
    }

    function meterImageRow(cfg) {
        return {
            previewUrl: cfg.existingImageUrl,
            lightboxOpen: false,
            sizeError: '',

            reset: cfg.reset,
            changed: cfg.changed,
            pricePerUnit: cfg.pricePerUnit,
            previousReading: cfg.previousReading,
            currentReading: cfg.currentReading,
            oldFinal: cfg.oldFinal,
            newStart: cfg.newStart,
            maxOverride: cfg.maxOverride,

            toggleReset() {
                this.reset = !this.reset;
                if (this.reset) this.changed = false;
            },
            toggleChanged() {
                this.changed = !this.changed;
                if (this.changed) this.reset = false;
            },

            // พรีวิวจำนวนหน่วย/ยอดเงินแบบเรียลไทม์ - สูตรเดียวกับ MeterReading::calculateUnits()
            // เพื่อให้ผู้ใช้เห็นผลลัพธ์ทันทีที่พิมพ์ โดยไม่ต้องกดบันทึกก่อน
            get liveUnits() {
                const current  = Number(this.currentReading) || 0;
                const previous = (this.previousReading === '' || this.previousReading === null)
                    ? null : (Number(this.previousReading) || 0);

                let units;
                if (this.changed) {
                    const oldFinal = Number(this.oldFinal) || 0;
                    const newStart = Number(this.newStart) || 0;
                    units = (oldFinal - (previous ?? 0)) + (current - newStart);
                } else if (this.reset) {
                    const maxVal = Number(this.maxOverride) || heuristicMaxValue(previous ?? 0);
                    const percentFull = maxVal > 0 ? Math.floor((previous ?? 0) / maxVal) * 100 : 0;
                    units = percentFull > 90 ? (maxVal - (previous ?? 0)) + current + 1 : current;
                } else if (previous === null) {
                    units = current;
                } else {
                    units = current - previous;
                }

                return Math.max(units, 0);
            },
            get liveAmount() {
                return this.liveUnits * this.pricePerUnit;
            },
            get liveSummary() {
                const fmt = (n) => n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                return fmt(this.liveUnits) + ' หน่วย · ฿' + fmt(this.liveAmount);
            },

            onFileChange(e) {
                const file = e.target.files[0];
                if (! file) return;

                if (file.size > METER_IMAGE_MAX_BYTES) {
                    this.sizeError = 'ไฟล์รูปใหญ่เกินไป (สูงสุด 10MB) กรุณาเลือกรูปใหม่';
                    e.target.value = '';
                    return;
                }

                this.sizeError = '';
                if (this.previewUrl && this.previewUrl.startsWith('blob:')) {
                    URL.revokeObjectURL(this.previewUrl);
                }
                this.previewUrl = URL.createObjectURL(file);
            },

            clearImage() {
                this.sizeError = '';
                if (this.previewUrl && this.previewUrl.startsWith('blob:')) {
                    URL.revokeObjectURL(this.previewUrl);
                }
                this.previewUrl = null;
                if (this.$refs.fileInput) {
                    this.$refs.fileInput.value = '';
                }
            },
        };
    }
</script>
<script>
    // เมื่อเลือก/แก้ไขวันที่อ่านมิเตอร์ตัวใดก็ตาม ให้เติมช่วงวันที่ลงหมายเหตุอัตโนมัติ
    function updateRemarkFromDates() {
        const dates = Array.from(document.querySelectorAll('input[name$="[reading_date]"]'))
            .map(function (el) { return el.value; })
            .filter(Boolean)
            .sort();

        if (dates.length === 0) return;

        const remarkEl = document.getElementById('remark');
        if (! remarkEl) return;

        const first = dates[0];
        const last  = dates[dates.length - 1];

        remarkEl.value = first === last
            ? 'จดวันที่ ' + formatThaiDate(first)
            : 'จดวันที่ ' + formatThaiDate(first) + ' ถึง ' + formatThaiDate(last);
    }

    document.addEventListener('change', function (e) {
        if (! e.target.matches('input[name$="[reading_date]"]')) return;
        updateRemarkFromDates();
    });

    // ถ้ามีวันที่อ่านมิเตอร์ถูกกรอกไว้แล้วตั้งแต่โหลดหน้า (เช่นค่าที่บันทึกไว้เดิม) ให้เติมหมายเหตุให้ตรงกัน
    // แต่ไม่ทับหมายเหตุเดิมที่ผู้ใช้เคยกรอกไว้ก่อนหน้า
    document.addEventListener('DOMContentLoaded', function () {
        const remarkEl = document.getElementById('remark');
        if (remarkEl && ! remarkEl.value) {
            updateRemarkFromDates();
        }
    });

    // เลือกข้อความในช่องตัวเลขทั้งหมดทันทีที่โฟกัส - พิมพ์ทับเลข 0 เดิมได้เลยโดยไม่ต้องลบก่อน
    document.addEventListener('focus', function (e) {
        if (e.target.matches('input[type="number"]:not([disabled])')) {
            e.target.select();
        }
    }, true);
</script>
@endpush

@endsection
