@extends('layouts.app')

@section('title', 'บันทึกมิเตอร์หลัก')
@section('breadcrumb', 'กลุ่ม ' . $group->property_group_name)

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

    // วันเวลาแบบไทย เช่น "9 ต.ค. 2569 14:05 น." - ล้อรูปแบบบรรทัดบันทึก/แก้ไขของหน้าแอดมิน
    $thaiShortMonths = [1 => 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $stamp = fn ($at) => $at
        ? $at->format('j') . ' ' . $thaiShortMonths[$at->month] . ' ' . ($at->year + 543) . ' ' . $at->format('H:i') . ' น.'
        : '-';
    // created_by/updated_by เป็น hr_admins.id - null = บันทึกจากแอปตัวแทน (ดู MasterMeterReadingController::store())
    $byName = fn ($adminId, $admin) => $adminId === null
        ? 'ผู้บริหารโครงการ'
        : ($admin?->name ? 'แอดมิน ' . $admin->name : 'ไม่ระบุ');
@endphp

{{-- ── Compact header ───────────────────────────────────────────────────────── --}}
<div class="relative overflow-hidden rounded-xl mb-2.5 sm:mb-3 bg-white border border-gray-100 shadow-sm p-2.5 sm:p-3">
    <div class="pointer-events-none absolute -top-10 -right-10 w-32 h-32 rounded-full bg-sky-50 opacity-70"></div>

    <div class="relative flex items-center justify-between gap-2 mb-2">
        <a href="{{ route('master-meters.index', ['year' => $year, 'month' => $month]) }}"
           class="inline-flex items-center gap-1 -ml-1 px-1.5 py-1 rounded-lg text-xs font-semibold text-gray-500 hover:text-brand-600 hover:bg-gray-50 transition-colors flex-shrink-0">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            รายการกลุ่มอสังหา
        </a>

        <div class="flex items-center gap-1.5 sm:gap-2">
            @if($allRecorded)
                <span class="inline-flex items-center gap-1 sm:gap-1.5 text-[11px] sm:text-xs font-semibold px-2 sm:px-2.5 py-1 rounded-full border text-green-700 bg-green-50 border-green-200 whitespace-nowrap">
                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    บันทึกครบแล้ว
                </span>
            @elseif($currentReadings->count() > 0)
                <span class="inline-flex items-center gap-1 sm:gap-1.5 text-[11px] sm:text-xs font-semibold px-2 sm:px-2.5 py-1 rounded-full border text-blue-700 bg-blue-50 border-blue-200 whitespace-nowrap">
                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    {{ $currentReadings->count() }}/{{ $meters->count() }}
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 text-[11px] sm:text-xs font-semibold px-2 sm:px-2.5 py-1 rounded-full border text-gray-600 bg-gray-50 border-gray-200 whitespace-nowrap">
                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400 flex-shrink-0"></span>
                    ยังไม่บันทึก
                </span>
            @endif

            @if($currentReadings->count() > 0)
                <button type="button"
                        onclick="openModal('delete-master-meter-confirm')"
                        class="inline-flex items-center justify-center w-7 h-7 sm:w-auto sm:h-auto sm:gap-1.5 text-xs font-semibold sm:px-2.5 sm:py-1 rounded-full border text-red-600 bg-white border-gray-200 hover:bg-red-50 hover:border-red-200 transition-colors flex-shrink-0">
                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M4 7h16M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/>
                    </svg>
                    <span class="hidden sm:inline">ลบข้อมูล</span>
                </button>
            @endif
        </div>
    </div>

    <div class="relative flex items-center justify-center gap-1.5">
        <a href="{{ route('master-meters.show', ['group' => $group->property_group_id, 'year' => $prevYear, 'month' => $prevMonth]) }}"
           aria-label="เดือนก่อน"
           class="w-7 h-7 sm:w-8 sm:h-8 flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 hover:border-gray-300 transition-colors flex-shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <p class="text-sm font-bold text-gray-800 leading-tight px-1 whitespace-nowrap">งวด {{ $periodLabel }}</p>
        <a href="{{ route('master-meters.show', ['group' => $group->property_group_id, 'year' => $nextYear, 'month' => $nextMonth]) }}"
           aria-label="เดือนถัดไป"
           class="w-7 h-7 sm:w-8 sm:h-8 flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 hover:border-gray-300 transition-colors flex-shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>
    </div>
</div>

<form method="POST" action="{{ route('master-meters.store', $group->property_group_id) }}" enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="billing_year" value="{{ $year }}">
    <input type="hidden" name="billing_month" value="{{ $month }}">

    {{-- ── ข้อมูลกลุ่ม ── --}}
    <div class="meter-row-in bg-white rounded-xl shadow-sm border border-gray-100 px-3 py-2 sm:px-3.5 sm:py-2.5 mb-2.5 sm:mb-3">
        <div class="flex items-center gap-2.5">
            <div class="w-7 h-7 sm:w-8 sm:h-8 flex-shrink-0 rounded-lg bg-gradient-to-br from-violet-400 to-purple-600 flex items-center justify-center">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21h18M5 21V7l8-4v18M13 21V9l6 3v9M9 9v.01M9 12v.01M9 15v.01"/>
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-sm font-bold text-gray-800 leading-tight truncate">กลุ่ม {{ $group->property_group_name }}</p>
                <p class="text-[11px] text-gray-400 leading-tight mt-0.5">
                    อสังหาในกลุ่ม {{ number_format($propertyCount) }} หลัง &middot;
                    มิเตอร์หลักที่คุณจด {{ $meters->count() }}@if($groupMeterCount > $meters->count()) จาก {{ $groupMeterCount }}@endif ตัว
                </p>
            </div>
        </div>
        @if($groupMeterCount > $meters->count())
            <p class="flex items-start gap-1.5 text-[11px] text-gray-500 leading-relaxed mt-2 pt-2 border-t border-gray-100">
                <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0 mt-px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                แสดงเฉพาะมิเตอร์หลักที่แอดมินกำหนดให้คุณเป็นผู้จด ตัวอื่นในกลุ่มมีผู้จดคนอื่นหรือแอดมินจด
            </p>
        @endif
    </div>

    <div class="space-y-2 sm:space-y-2.5">
        @foreach($metersByType as $type => $typeMeters)
            @php
                $style          = $typeStyles[$type] ?? ['label' => $type, 'ring' => 'border-gray-200', 'badge' => 'text-gray-700 bg-gray-100', 'icon' => 'from-gray-400 to-gray-600', 'path' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'];
                $typeReadings   = $typeMeters->pluck('reading')->filter();
                $typeUnitsTotal = $typeReadings->sum('units_used');
                $multiMeter     = $typeMeters->count() > 1;
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
                                <span class="text-xs text-gray-400 ml-1">{{ $typeMeters->count() }} ตัว</span>
                            @endif
                        </div>
                    </div>
                    @if($typeReadings->isNotEmpty())
                        <span class="text-xs sm:text-sm font-semibold text-gray-700">{{ number_format($typeUnitsTotal, 2) }} หน่วย</span>
                    @endif
                </div>

                <div class="space-y-2.5 sm:space-y-3">
                    @foreach($typeMeters->values() as $i => $meter)
                        @php
                            $reading      = $meter->reading;
                            $previous     = $previousReadings[$meter->id] ?? null;
                            $namePrefix   = 'readings[' . $meter->id . ']';
                            $renamed      = $reading && $meter->live_name !== $meter->name;
                            $snapshotIds  = $meter->properties->pluck('id')->sort()->values()->all();
                            $linksChanged = $reading && $snapshotIds !== $meter->live_property_ids;

                            $previousInit = $previous !== null
                                ? $previous
                                : old('readings.' . $meter->id . '.previous_reading', $reading->previous_reading ?? 0);
                            $currentInit  = old('readings.' . $meter->id . '.current_reading', $reading->current_reading ?? 0);
                            $oldFinalInit = old('readings.' . $meter->id . '.old_meter_final_reading', $reading->old_meter_final_reading ?? null);
                            $newStartInit = old('readings.' . $meter->id . '.new_meter_start_reading', $reading->new_meter_start_reading ?? null);
                        @endphp
                        <div class="meter-row-in relative rounded-lg border {{ $style['ring'] }} bg-gray-50/70 p-2.5 pl-3.5 sm:p-3 sm:pl-4 overflow-hidden"
                             style="animation-delay: {{ $i * 70 }}ms"
                             x-data="masterMeterRow({
                                 existingImageUrl: @js($reading?->image_path ? route('master-meters.image', $reading->id) : null),
                                 changed: {{ ($reading->meter_changed ?? false) ? 'true' : 'false' }},
                                 previousReading: {{ (int) $previousInit }},
                                 currentReading: {{ (int) $currentInit }},
                                 oldFinal: @js($oldFinalInit === null || $oldFinalInit === '' ? null : (int) $oldFinalInit),
                                 newStart: @js($newStartInit === null || $newStartInit === '' ? null : (int) $newStartInit),
                             })">
                            <span class="meter-accent-bar absolute left-0 top-0 bottom-0 w-1 bg-gradient-to-b {{ $style['icon'] }}" style="animation-delay: {{ $i * 70 }}ms"></span>

                            <div class="flex items-center justify-between mb-1.5 flex-wrap gap-1">
                                <span class="text-sm font-semibold text-gray-800 flex items-center gap-1.5 min-w-0">
                                    @if($multiMeter)
                                        <span class="meter-badge-pop inline-flex items-center justify-center w-5 h-5 rounded-full text-[10px] font-bold text-white bg-gradient-to-br {{ $style['icon'] }} shadow-sm flex-shrink-0"
                                              style="animation-delay: {{ $i * 70 + 80 }}ms">{{ $i + 1 }}</span>
                                    @endif
                                    <span class="truncate">{{ $meter->name }}</span>
                                </span>
                                {{-- มิเตอร์หลักไม่มีราคา - สรุปสดแสดงแค่จำนวนหน่วย (เหมือนหน้าแอดมิน) --}}
                                <span x-show="currentReading > 0" x-cloak class="text-xs font-semibold text-gray-600" x-text="'≈ ' + liveSummary"></span>
                            </div>

                            @if($reading)
                                <div class="flex flex-wrap gap-x-3.5 gap-y-0.5 text-[11px] text-gray-500 mb-2">
                                    <span class="inline-flex items-center gap-1">
                                        <svg class="w-3 h-3 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        บันทึก {{ $stamp($reading->created_at) }} โดย {{ $byName($reading->created_by, $reading->creator) }}
                                    </span>
                                    @if($reading->wasEdited())
                                        <span class="inline-flex items-center gap-1">
                                            <svg class="w-3 h-3 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            แก้ไข {{ $stamp($reading->updated_at) }} โดย {{ $byName($reading->updated_by, $reading->updater) }}
                                        </span>
                                    @endif
                                </div>
                            @endif

                            @if($renamed)
                                <p class="flex items-start gap-1.5 text-[11px] text-gray-500 mb-2">
                                    <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0 mt-px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span>ชื่อตอนบันทึก - ข้อมูลหลักตอนนี้ชื่อ "{{ $meter->live_name }}"</span>
                                </p>
                            @endif

                            @if($meter->properties->isNotEmpty())
                                <details class="group mb-2.5">
                                    <summary class="inline-flex items-center gap-1 text-xs font-semibold text-brand-700 cursor-pointer select-none list-none [&::-webkit-details-marker]:hidden">
                                        <svg class="w-3 h-3 flex-shrink-0 transition-transform duration-200 group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                                        {{ $reading ? 'อสังหาที่ผูกตอนบันทึก' : 'อสังหาที่ผูก' }} {{ $meter->properties->count() }} หลัง
                                    </summary>
                                    <div class="flex flex-wrap gap-1 mt-1.5">
                                        @foreach($meter->properties as $p)
                                            <span class="text-[11px] font-semibold text-gray-600 bg-white border border-gray-200 rounded-full px-2 py-0.5" title="{{ $p->title }}">{{ $p->code ?: 'ไม่มีรหัส' }}</span>
                                        @endforeach
                                    </div>
                                    @if($linksChanged)
                                        <p class="text-[11px] text-gray-500 mt-1.5">ข้อมูลหลักตอนนี้ผูก {{ count($meter->live_property_ids) }} หลัง - งวดนี้ยังใช้ชุดตอนบันทึก</p>
                                    @endif
                                </details>
                            @endif

                            @include('meters.partials.reading-fields', [
                                'meter'                => $meter,
                                'reading'              => $reading,
                                'previous'             => $previous,
                                'namePrefix'           => $namePrefix,
                                'previousInit'         => $previousInit,
                                'currentInit'          => $currentInit,
                                'oldFinalInit'         => $oldFinalInit,
                                'newStartInit'         => $newStartInit,
                                'previousReadingDates' => $previousReadingDates,
                                // มิเตอร์หลักไม่มีล็อกใบแจ้งหนี้ และหน้านี้แสดงเฉพาะตัวที่ตัวแทนจดได้ - แก้ไขได้เสมอ
                                'alreadyInvoiced'      => false,
                            ])
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
                :value="$remark ?? ''" />
        </div>
    </div>

    {{-- ปุ่มบันทึก - เดสก์ท็อปใช้ปุ่มปกติท้ายฟอร์ม --}}
    <div class="mt-4 hidden lg:flex justify-end">
        <x-btn type="submit" variant="primary">บันทึกข้อมูลมิเตอร์หลัก</x-btn>
    </div>

    {{-- ปุ่มบันทึกลอย (มือถือ) - อยู่เหนือแถบเมนูล่างเสมอ กดได้ทันทีโดยไม่ต้องเลื่อนหน้าจอ --}}
    <div class="lg:hidden fixed inset-x-0 z-30 bg-white/95 backdrop-blur border-t border-gray-200 px-4 py-2.5 shadow-[0_-4px_12px_rgba(0,0,0,0.06)] flex gap-2"
         style="bottom: calc(64px + env(safe-area-inset-bottom, 0px));">
        <button type="submit"
                class="flex-1 flex items-center justify-center gap-2 bg-brand-600 active:bg-brand-700 text-white font-semibold text-sm py-3 rounded-xl transition-colors tap-effect shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            บันทึก
        </button>
    </div>
    <div class="lg:hidden" style="height: 84px;" aria-hidden="true"></div>
</form>

@if($currentReadings->count() > 0)
{{-- ── Delete Master Meter Readings Confirm Modal ── --}}
<x-confirm-modal
    id="delete-master-meter-confirm"
    title="ยืนยันลบข้อมูลมิเตอร์หลัก"
    :action="route('master-meters.destroy', ['group' => $group->property_group_id, 'year' => $year, 'month' => $month])"
    method="DELETE"
    icon-variant="danger"
    confirm-label="ลบข้อมูล"
    cancel-label="ยกเลิก">
    <div class="flex flex-col gap-3">
        <p class="text-sm text-gray-700 leading-relaxed">
            คุณต้องการ<span class="font-semibold text-red-600">ลบข้อมูลมิเตอร์หลักที่คุณจด</span>ของกลุ่ม {{ $group->property_group_name }} งวด {{ $periodLabel }} ใช่หรือไม่?
        </p>
        <div class="flex items-start gap-2.5 bg-amber-50 border border-amber-200 rounded-xl px-3.5 py-3">
            <svg class="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.07 16.5c-.77.833.192 2.5 1.732 2.5z"/>
            </svg>
            <p class="text-xs text-amber-700 leading-relaxed">
                สถานะจะกลับเป็น <span class="font-semibold">ยังไม่บันทึก</span> และต้องบันทึกเลขมิเตอร์หลักใหม่ทั้งหมด
                @if($othersRecordedCount > 0)
                    <br><span class="text-amber-600/80">ข้อมูลของมิเตอร์หลักที่ผู้อื่นจด {{ $othersRecordedCount }} ตัว ยังเก็บไว้</span>
                @endif
            </p>
        </div>
    </div>
</x-confirm-modal>
@endif

@push('scripts')
<script>
    const METER_IMAGE_MAX_BYTES = 10 * 1024 * 1024; // ต้องตรงกับ max:10240 ใน MasterMeterReadingController::store()

    // scope ของมิเตอร์หลัก 1 ตัว - ชื่อ state ต้องตรงกับที่ meters/partials/reading-fields ใช้
    function masterMeterRow(cfg) {
        return {
            previewUrl: cfg.existingImageUrl,
            lightboxOpen: false,
            sizeError: '',

            changed: cfg.changed,
            previousReading: cfg.previousReading,
            currentReading: cfg.currentReading,
            oldFinal: cfg.oldFinal,
            newStart: cfg.newStart,

            // มิเรอร์สูตรเดียวกับ App\Models\MeterReading::calculateUnits() (ไม่มีกรณีรีเซ็ตแล้ว)
            get liveUnits() {
                const current  = Number(this.currentReading) || 0;
                const previous = (this.previousReading === '' || this.previousReading === null)
                    ? null : (Number(this.previousReading) || 0);

                let units;
                if (this.changed) {
                    const oldFinal = Number(this.oldFinal) || 0;
                    const newStart = Number(this.newStart) || 0;
                    units = (oldFinal - (previous ?? 0)) + (current - newStart);
                } else if (previous === null) {
                    units = current;
                } else {
                    units = current - previous;
                }

                return Math.max(units, 0);
            },
            get liveSummary() {
                return this.liveUnits.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' หน่วย';
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

    // วันที่เริ่มงวด = วันที่จดของเดือนก่อน + 1 วัน - เติมหมายเหตุอัตโนมัติเหมือนหน้าแอดมิน
    // (ไม่ทับหมายเหตุที่มีอยู่แล้ว)
    function addOneDay(dateStr) {
        const d = new Date(dateStr + 'T00:00:00');
        d.setDate(d.getDate() + 1);
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    function updateRemarkFromDates() {
        const inputs = Array.from(document.querySelectorAll('input[name$="[reading_date]"]')).filter(el => el.value);
        if (inputs.length === 0) return;
        const remarkEl = document.getElementById('remark');
        if (! remarkEl || remarkEl.value) return;

        const starts = inputs.map(el => el.dataset.prevDate ? addOneDay(el.dataset.prevDate) : el.value).sort();
        const ends   = inputs.map(el => el.value).sort();
        const first  = starts[0];
        const last   = ends[ends.length - 1];

        remarkEl.value = first === last
            ? 'จดวันที่ ' + formatThaiDate(first)
            : 'จดวันที่ ' + formatThaiDate(first) + ' ถึง ' + formatThaiDate(last);
    }

    document.addEventListener('change', function (e) {
        if (! e.target.matches('input[name$="[reading_date]"]')) return;
        updateRemarkFromDates();
    });
    document.addEventListener('DOMContentLoaded', updateRemarkFromDates);

    // เลือกข้อความในช่องตัวเลขทั้งหมดทันทีที่โฟกัส - พิมพ์ทับเลข 0 เดิมได้เลยโดยไม่ต้องลบก่อน
    document.addEventListener('focus', function (e) {
        if (e.target.matches('input[type="number"]:not([disabled])')) {
            e.target.select();
        }
    }, true);
</script>
@endpush

@endsection
