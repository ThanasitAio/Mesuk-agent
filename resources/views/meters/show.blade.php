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
            @if($currentReadings->count() > 0)
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

    {{-- ── Summary strip: common fee + invoice display settings + rent period ── --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-2.5 mb-2.5 sm:mb-3">
        <div class="meter-row-in bg-white rounded-xl shadow-sm border border-gray-100 px-3 py-2 sm:px-3.5 sm:py-2.5">
            <div class="flex items-center gap-2.5">
                <div class="w-7 h-7 sm:w-8 sm:h-8 flex-shrink-0 rounded-lg bg-gradient-to-br from-violet-400 to-purple-600 flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21h18M5 21V7l8-4v18M13 21V9l6 3v9M9 9v.01M9 12v.01M9 15v.01"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-[11px] text-gray-400 truncate leading-tight">ค่าส่วนกลาง/เดือน</p>
                    <p class="text-sm font-bold text-gray-800 leading-tight">฿{{ number_format($property->common_fee_per_month ?? 0, 2) }}</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-1.5 mt-2.5 pt-2.5 sm:mt-3 sm:pt-3.5 border-t border-gray-100">
                <span class="inline-flex items-center gap-1 text-[11px] font-medium px-2 py-0.5 rounded-full border {{ $property->show_meter_image_on_invoice ? 'text-emerald-700 bg-emerald-50 border-emerald-200' : 'text-gray-400 bg-gray-50 border-gray-200' }}">
                    <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M4 6h16v12H4V6z"/></svg>
                    รูปภาพบนใบแจ้งหนี้: {{ $property->show_meter_image_on_invoice ? 'แสดง' : 'ไม่แสดง' }}
                </span>
                <span class="inline-flex items-center gap-1 text-[11px] font-medium px-2 py-0.5 rounded-full border {{ $property->show_shipping_address_on_invoice ? 'text-emerald-700 bg-emerald-50 border-emerald-200' : 'text-gray-400 bg-gray-50 border-gray-200' }}">
                    <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    ที่อยู่จัดส่งหน้าถัดไป: {{ $property->show_shipping_address_on_invoice ? 'แสดง' : 'ไม่แสดง' }}
                </span>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-3 py-2 sm:px-3.5 sm:py-2.5">
            <x-form.month-year
                name-month="rent_month"
                name-year="rent_year"
                label="เรียกเก็บพร้อมค่าเช่าเดือน/ปี"
                :value-month="$rentMonth"
                :value-year="$rentYear"
                :year-from="2025"
                :year-to="now()->year + 1"
                required
            />
        </div>
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
                            @if($multiMeter)
                                <span class="text-xs text-gray-400 ml-1">{{ $typeMeters->count() }} จุด</span>
                            @endif
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
                        @endphp
                        <div class="meter-row-in relative rounded-lg border {{ $style['ring'] }} bg-gray-50/70 p-2.5 pl-3.5 sm:p-3 sm:pl-4 overflow-hidden"
                             style="animation-delay: {{ $i * 70 }}ms"
                             x-data="meterImageRow({
                                 existingImageUrl: @js($reading?->image_path ? route('meters.image', $reading->id) : null),
                                 reset: {{ ($reading->meter_reset ?? false) ? 'true' : 'false' }},
                                 changed: {{ ($reading->meter_changed ?? false) ? 'true' : 'false' }},
                                 pricePerUnit: {{ (float) $meter->price_per_unit }},
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
                                    ฿{{ $fmtUnitPrice($meter->price_per_unit) }}/หน่วย
                                </span>
                                <span x-show="currentReading > 0" x-cloak class="text-xs font-semibold text-gray-600" x-text="'≈ ' + liveSummary"></span>
                            </div>

                            <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">เลขมิเตอร์เดือนก่อน</label>
                                    @if($previous !== null)
                                        <x-form.number
                                            :name="$namePrefix . '[previous_reading_display]'"
                                            :value="$previous"
                                            class="text-right"
                                            disabled />
                                        <p class="text-gray-400 text-xs mt-1.5">ดึงจากเดือนก่อนอัตโนมัติ แก้ไขไม่ได้</p>
                                    @else
                                        <x-form.number
                                            :name="$namePrefix . '[previous_reading]'"
                                            :value="$previousInit"
                                            placeholder="เลขเดือนก่อน"
                                            class="text-right"
                                            x-model.number="previousReading"
                                            min="0" />
                                    @endif
                                </div>

                                <x-form.number
                                    :name="$namePrefix . '[current_reading]'"
                                    label="เลขมิเตอร์ปัจจุบัน"
                                    :value="$currentInit"
                                    class="text-right"
                                    x-model.number="currentReading"
                                    min="0" />

                                <x-form.date
                                    :name="$namePrefix . '[reading_date]'"
                                    label="วันที่อ่านมิเตอร์"
                                    :value="old('readings.' . $meter->id . '.reading_date', optional($reading?->reading_date)->format('Y-m-d') ?: now()->format('Y-m-d'))"
                                    :max="now()->format('Y-m-d')" />

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">รูปภาพมิเตอร์</label>
                                    {{-- ปุ่มเลือกรูป - สูงเท่า input อื่นในแถวเดียวกันเสมอ ไม่ว่าจะมีรูปหรือไม่ เพื่อไม่ให้ layout ของแถวเพี้ยน --}}
                                    <label class="flex items-center justify-center gap-1.5 h-[42px] w-full px-3 rounded-xl border {{ $errors->has("readings.{$meter->id}.image") ? 'border-red-400 bg-red-50' : 'border-gray-200 bg-white hover:bg-gray-50 hover:border-gray-300' }} text-xs font-medium text-gray-500 cursor-pointer transition-colors">
                                        <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 19.5h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5z"/>
                                        </svg>
                                        <span class="truncate" x-text="previewUrl ? 'เปลี่ยนรูปภาพ' : 'เลือกรูปภาพ'"></span>
                                        <input type="file" name="{{ $namePrefix }}[image]" accept="image/jpeg,image/png" class="hidden"
                                               x-ref="fileInput"
                                               @change="onFileChange($event)">
                                    </label>
                                    <p x-show="sizeError" x-cloak class="text-red-500 text-xs mt-1.5" x-text="sizeError"></p>
                                    @error("readings.{$meter->id}.image")
                                        <p class="text-red-500 text-xs mt-1.5">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            {{-- พรีวิวรูปขนาดใหญ่ - อยู่นอก grid ด้านบนเสมอ เพื่อไม่ให้ความสูงของรูปไปดันแถว input อื่นเพี้ยน --}}
                            <template x-if="previewUrl">
                                <div class="flex items-start gap-3 mt-2.5 pt-2.5 sm:mt-3 sm:pt-3 border-t border-gray-100">
                                    <div class="relative flex-shrink-0">
                                        <button type="button" @click="lightboxOpen = true" class="block" title="ดูรูปขนาดใหญ่">
                                            <img :src="previewUrl" class="w-20 h-20 sm:w-28 sm:h-28 object-cover rounded-xl border border-gray-200 shadow-sm cursor-zoom-in">
                                        </button>
                                        <button type="button" @click="clearImage()"
                                                title="ลบรูปภาพ"
                                                class="absolute -top-2 -right-2 w-6 h-6 flex items-center justify-center rounded-full bg-red-500 text-white shadow hover:bg-red-600 transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </template>

                            {{-- Lightbox popup แสดงรูปเต็มขนาด - ไม่เปิดแท็บใหม่ --}}
                            <div x-show="lightboxOpen" x-cloak
                                 @click="lightboxOpen = false"
                                 @keydown.escape.window="lightboxOpen = false"
                                 x-transition.opacity
                                 class="fixed inset-0 z-[60] bg-black/80 flex items-center justify-center p-4">
                                <button type="button" @click.stop="lightboxOpen = false"
                                        class="absolute top-4 right-4 w-9 h-9 flex items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20 transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                                <img :src="previewUrl" @click.stop class="max-w-full max-h-full rounded-xl shadow-2xl" alt="">
                            </div>

                            <div class="flex flex-wrap items-center gap-1.5 sm:gap-2 mt-2.5 pt-2.5 sm:mt-3 sm:pt-3 border-t border-gray-100">
                                <input type="hidden" name="{{ $namePrefix }}[meter_reset]" :value="reset ? 1 : 0">
                                <button type="button"
                                        @click="toggleReset()"
                                        :class="reset ? 'bg-brand-600 border-brand-600 text-white' : 'bg-white border-gray-200 text-gray-500 hover:border-gray-300'"
                                        class="inline-flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 rounded-full border text-xs font-medium transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/>
                                    </svg>
                                    มิเตอร์รีเซ็ต
                                </button>

                                <input type="hidden" name="{{ $namePrefix }}[meter_changed]" :value="changed ? 1 : 0">
                                <button type="button"
                                        @click="toggleChanged()"
                                        :class="changed ? 'bg-brand-600 border-brand-600 text-white' : 'bg-white border-gray-200 text-gray-500 hover:border-gray-300'"
                                        class="inline-flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 rounded-full border text-xs font-medium transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h18M16.5 3L21 7.5m0 0L16.5 12M21 7.5H3"/>
                                    </svg>
                                    เปลี่ยนมิเตอร์ใหม่
                                </button>
                            </div>

                            <div x-show="changed" x-cloak class="grid grid-cols-2 gap-2.5 sm:gap-3 mt-2.5 sm:mt-3">
                                <x-form.number
                                    :name="$namePrefix . '[old_meter_final_reading]'"
                                    label="เลขมิเตอร์เก่าสุดท้าย"
                                    :value="$oldFinalInit"
                                    class="text-right"
                                    x-model.number="oldFinal"
                                    min="0" />
                                <x-form.number
                                    :name="$namePrefix . '[new_meter_start_reading]'"
                                    label="เลขมิเตอร์ใหม่เริ่มต้น"
                                    :value="$newStartInit"
                                    class="text-right"
                                    x-model.number="newStart"
                                    min="0" />
                            </div>

                            <div x-show="reset" x-cloak class="mt-2.5 sm:mt-3">
                                <x-form.number
                                    :name="$namePrefix . '[meter_max_value]'"
                                    label="จุดสูงสุดของมิเตอร์ (ถ้าทราบ)"
                                    :value="$maxValueInit"
                                    hint="ถ้าไม่ระบุ ระบบจะประมาณจากจำนวนหลักของเลขมิเตอร์เดือนก่อนให้อัตโนมัติ"
                                    class="text-right"
                                    x-model.number="maxOverride"
                                    min="1" />
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        <div class="meter-card-in bg-white rounded-xl shadow-sm border border-gray-100 p-2.5 sm:p-3.5">
            <x-form.textarea
                name="remark"
                label="หมายเหตุ"
                rows="2"
                :value="old('remark', $currentReadings->first()?->remark ?? '')" />
        </div>
    </div>

    {{-- ปุ่มบันทึก - เดสก์ท็อปใช้ปุ่มปกติท้ายฟอร์ม --}}
    <div class="mt-4 hidden lg:flex justify-end">
        <x-btn type="submit" variant="primary">บันทึกข้อมูลมิเตอร์</x-btn>
    </div>

    {{-- ปุ่มบันทึกลอย (มือถือ) - อยู่เหนือแถบเมนูล่างเสมอ กดบันทึกได้ทันทีโดยไม่ต้องเลื่อนหน้าจอ --}}
    <div class="lg:hidden fixed inset-x-0 z-30 bg-white/95 backdrop-blur border-t border-gray-200 px-4 py-2.5 shadow-[0_-4px_12px_rgba(0,0,0,0.06)]"
         style="bottom: calc(64px + env(safe-area-inset-bottom, 0px));">
        <button type="submit"
                class="w-full flex items-center justify-center gap-2 bg-brand-600 active:bg-brand-700 text-white font-semibold text-sm py-3 rounded-xl transition-colors tap-effect shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            บันทึกข้อมูลมิเตอร์
        </button>
    </div>
    <div class="lg:hidden" style="height: 84px;" aria-hidden="true"></div>
</form>

@if($currentReadings->count() > 0)
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

    // วันที่อ่านมิเตอร์ตั้งค่าเริ่มต้นเป็นวันนี้ให้อัตโนมัติ (ลดการแตะ) - เติมหมายเหตุให้ตรงกัน
    // ตั้งแต่โหลดหน้า แต่ไม่ทับหมายเหตุเดิมที่ผู้ใช้เคยกรอกไว้ก่อนหน้า
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
