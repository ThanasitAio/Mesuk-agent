{{-- ฟิลด์กรอกมิเตอร์ 1 ตัว (เลขก่อน/ปัจจุบัน, วันที่, รูปภาพ, ปุ่มมิเตอร์เริ่มนับใหม่) --}}
{{-- ใช้ร่วมกันทั้งมิเตอร์ย่อย (meters/show) และมิเตอร์หลัก (master-meters/show) - ต้องอยู่ภายใน element ที่มี
     x-data="meterReadingRow()" (meters/partials/reading-scripts) ซึ่งอ่านค่าตั้งต้นจาก data-* ของ [data-meter-reading] ด้านล่าง
     ตัวแปรที่ต้องมี: $meter, $reading, $previous, $namePrefix, $alreadyInvoiced, $meterLabel, $previousReadingDates,
     $previousUnits, $suggestedPrevious, $nextReadings / ไม่บังคับ: $pricePerUnit (มิเตอร์หลักไม่มีราคา), $imageRoute,
     $restartPhoto (มีคอลัมน์รูปเลขสุดท้ายก่อนเริ่มนับใหม่แล้ว - ไม่ส่ง = ดูของมิเตอร์ย่อย MeterReading::supportsRestartPhoto()) --}}
{{-- ไม่มีปุ่ม "มิเตอร์รีเซ็ต" แยกแล้ว (ผู้ใช้ขอตัด 2026-10-09): "มิเตอร์เริ่มนับใหม่" (meter_changed) ใช้ได้ทั้งมิเตอร์วนกลับเป็น 0
     และเปลี่ยนมิเตอร์ใหม่ ฝั่งเซิร์ฟเวอร์บันทึก meter_reset = false เสมอ --}}
{{-- เลขมิเตอร์ทุกช่องต้องครบหลักพอดี (ไฟ 5 / น้ำ 4, MeterReading::READING_DIGITS, ผู้ใช้กำหนด 2026-10-12) แสดงแบบหน้าปัด
     เติม 0 ข้างหน้า ช่องสีเหลือง = ค่าเริ่มต้นที่ระบบเติมให้ ผู้ใช้แก้แล้วเป็นสีขาว - ล้อ happyest admin/meter-readings/partials/reading-fields

     ช่องที่บังคับมีดอกจันแดง: เลขปัจจุบันไม่บังคับ (ผู้ใช้ขอ 2026-10-15 - แอดมินมีดอกจันและบังคับอย่างน้อย 1 มิเตอร์)
     เว้นว่าง = ข้ามมิเตอร์นี้งวดนี้ ส่วนเลขเดือนก่อน (เมื่อกรอกเอง) วันที่อ่าน รูปมิเตอร์ และเลขสุดท้าย/เลขเริ่มต้นใหม่
     บังคับเฉพาะเมื่อกรอกเลขปัจจุบันแล้ว ดอกจันจึงโผล่เมื่อกรอกเลขแล้ว - รูปเลขสุดท้ายไม่บังคับ
     ช่องรูป (data-photo-slot) ทำงานใน reading-scripts: ย่อรูปก่อนตรวจขนาด รูปที่บันทึกไว้แล้วนับว่ามีรูป
     ปุ่ม x ยกเลิกได้แค่รูปที่เพิ่งเลือก (ลบรูปที่บันทึกไว้แล้วไม่ได้ ให้เลือกรูปใหม่แทน) --}}
@php
    $type        = $meter->meter_type;
    $digits      = \App\Models\MeterReading::readingDigits($type);
    $max         = \App\Models\MeterReading::readingMax($type);
    $pad         = fn ($v) => \App\Models\MeterReading::padReading($v, $type);
    $example     = $digits === 4 ? '0546' : '02345';
    $suggested   = $suggestedPrevious[$meter->id] ?? null;
    $next        = $nextReadings[$meter->id] ?? null;
    $prevUnits   = $previousUnits[$meter->id] ?? null;
    $savedChanged = (bool) ($reading?->meter_changed);
    $imageRouteName = $imageRoute ?? 'meters.image';
    $restartPhotoOn = $restartPhoto ?? \App\Models\MeterReading::supportsRestartPhoto();

    // ส่งฟอร์มแล้วไม่ผ่าน -> แสดงค่าที่ส่งมาตามจริง (ช่องที่ลบทิ้งต้องว่าง ไม่เด้งกลับเป็นค่าที่บันทึกไว้)
    $submitted = old('readings') !== null;
    $old       = fn (string $field) => (string) old("readings.{$meter->id}.{$field}", '');

    // เลขเดือนก่อน: มีแถวเดือน-1 = ล็อก / ไม่มี = กรอกเอง (ค่าที่ส่งกลับมา > ที่บันทึกไว้ > เลขงวดเก่ากว่าเป็นค่าแนะนำสีเหลือง)
    $previousValue   = '';
    $previousDefault = false;
    if ($previous === null) {
        $previousValue = $submitted
            ? $old('previous_reading')
            : ($reading ? $pad($reading->previous_reading) : ($suggested ? $pad($suggested['value']) : ''));
        $previousDefault = ! $reading && $suggested && $previousValue === $pad($suggested['value']);
    }
    $currentValue  = $submitted ? $old('current_reading') : ($reading ? $pad($reading->current_reading) : '');
    $currentFilled = $currentValue !== '';

    // เริ่มนับใหม่: ค่าเริ่มต้น ไฟ 99999 / น้ำ 9999 และ 0 (สีเหลือง) - แถวเก่าที่ปิดสวิตช์อยู่แต่มีเลขค้างไว้ ไม่เอามาแสดง
    $changedInit       = $submitted ? $old('meter_changed') === '1' : $savedChanged;
    $oldFinalDefault   = $pad($max);
    $newStartDefault   = $pad(0);
    $oldFinalValue     = $submitted ? $old('old_meter_final_reading') : ($savedChanged ? $pad($reading->old_meter_final_reading) : '');
    $newStartValue     = $submitted ? $old('new_meter_start_reading') : ($savedChanged ? $pad($reading->new_meter_start_reading) : '');
    $oldFinalIsDefault = ! $savedChanged && $oldFinalValue === $oldFinalDefault;
    $newStartIsDefault = ! $savedChanged && $newStartValue === $newStartDefault;

    // รูปที่บันทึกไว้แล้ว (นับว่ามีรูป) - รูปเลขสุดท้ายแสดงเฉพาะแถวที่บันทึกแบบเริ่มนับใหม่
    $savedPhotoSrc = $reading?->image_path ? route($imageRouteName, $reading->id) : '';
    $savedRestartPhotoSrc = $restartPhotoOn && $savedChanged && $reading?->old_meter_final_image_path
        ? route($imageRouteName, ['reading' => $reading->id, 'kind' => 'restart']) : '';

    $condStar = fn () => $alreadyInvoiced ? '' : '<span class="text-red-500" title="ต้องกรอกเมื่อกรอกเลขปัจจุบัน" x-show="v.current !== \'\'"' . ($currentFilled ? '' : ' style="display: none"') . '>*</span>';
    $photoErr = fn (string $field) => $errors->has("readings.{$meter->id}.{$field}");
@endphp
<div data-meter-reading
     data-meter-id="{{ $meter->id }}"
     data-digits="{{ $digits }}"
     data-max="{{ $max }}"
     data-label="{{ $meterLabel }}"
     data-readonly="{{ $alreadyInvoiced ? '1' : '0' }}"
     data-changed="{{ $changedInit ? '1' : '0' }}"
     data-locked-previous="{{ $previous !== null ? (int) $previous : '' }}"
     data-saved-current="{{ $reading ? (int) $reading->current_reading : '' }}"
     data-prev-units="{{ $prevUnits !== null ? (float) $prevUnits : '' }}"
     @isset($pricePerUnit) data-price-per-unit="{{ (float) $pricePerUnit }}" @endisset
     @if($next) data-next-previous="{{ $next['previous'] }}" data-next-period="{{ $next['period'] }}" data-next-locked="{{ $next['locked'] ? '1' : '0' }}" @endif>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-3">
    @if($previous !== null)
        <x-form.meter-digits
            :name="$namePrefix . '[previous_reading_display]'"
            label="เลขมิเตอร์เดือนก่อน"
            :digits="$digits"
            :value="$pad($previous)"
            disabled>
            @if($previous > $max)
                <p class="text-red-500 text-xs mt-1.5">เกิน {{ $digits }} หลัก งวดเดือนก่อนกรอกผิด แก้งวดนั้นก่อน</p>
            @else
                <p class="text-gray-400 text-xs mt-1.5">ดึงจากเดือนก่อนอัตโนมัติ แก้ไขไม่ได้</p>
            @endif
        </x-form.meter-digits>
    @else
        <x-form.meter-digits
            :name="$namePrefix . '[previous_reading]'"
            field="previous"
            label="เลขมิเตอร์เดือนก่อน"
            :req="$alreadyInvoiced ? null : 'filled'"
            :current-filled="$currentFilled"
            :digits="$digits"
            :value="$previousValue"
            :default="$suggested ? $pad($suggested['value']) : null"
            :is-default="$previousDefault"
            :disabled="$alreadyInvoiced">
            @unless($alreadyInvoiced)
                <p class="text-gray-400 text-xs mt-1">
                    @if($suggested && ! $reading)
                        เดือนก่อนไม่ได้จด ใช้เลขงวด {{ $suggested['period'] }}
                    @elseif($reading)
                        เดือนก่อนไม่ได้จด เลขเริ่มต้นของงวดนี้
                    @else
                        ครั้งแรกของมิเตอร์นี้ กรอกเลขตั้งต้น
                    @endif
                </p>
            @endunless
        </x-form.meter-digits>
    @endif

    <x-form.meter-digits
        :name="$namePrefix . '[current_reading]'"
        field="current"
        label="เลขมิเตอร์ปัจจุบัน"
        :digits="$digits"
        :value="$currentValue"
        :disabled="$alreadyInvoiced">
        @unless($alreadyInvoiced)
            <p class="text-gray-400 text-xs mt-1">{{ $digits }} หลัก ตามหน้าปัด เช่น {{ $example }}@unless($reading) · เว้นว่าง = ยังไม่จดตัวนี้@endunless</p>
            @if($next)
                <p class="flex items-start gap-1 text-sky-700 text-xs mt-1">
                    <svg class="w-3.5 h-3.5 flex-shrink-0 mt-px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>งวด {{ $next['period'] }} จดไว้แล้ว เริ่มจาก {{ $pad($next['previous']) }}{{ $next['locked'] ? ' (ออกใบแจ้งหนี้แล้ว)' : '' }}</span>
                </p>
            @endif
        @endunless
    </x-form.meter-digits>

    <div data-date-field>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">วันที่อ่านมิเตอร์ {!! $condStar() !!}</label>
        <x-form.date
            :name="$namePrefix . '[reading_date]'"
            :value="old('readings.' . $meter->id . '.reading_date', optional($reading?->reading_date)->format('Y-m-d'))"
            :max="now()->format('Y-m-d')"
            :disabled="$alreadyInvoiced"
            data-prev-date="{{ $previousReadingDates[$meter->id] ?? '' }}" />
    </div>

    {{-- ปุ่มเลือกรูป + รูปย่อข้างปุ่ม สูงเท่าช่องเลขมิเตอร์ (เหมือนแอดมิน 2026-10-14) - แตะรูปย่อ = ดูรูปใหญ่ --}}
    <div data-photo-slot="main" data-saved-src="{{ $savedPhotoSrc }}">
        <label class="block text-sm font-medium text-gray-700 mb-1.5">รูปภาพมิเตอร์ {!! $condStar() !!}</label>
        @include('meters.partials.photo-picker', [
            'slot'      => 'main',
            'inputName' => $namePrefix . '[image]',
            'hasError'  => $photoErr('image'),
            'savedSrc'  => $savedPhotoSrc,
            'alt'       => 'รูปมิเตอร์' . $meterLabel,
            'disabled'  => $alreadyInvoiced,
        ])
        @unless($alreadyInvoiced)
            <p class="text-gray-400 text-xs mt-1">รูปหน้าปัดที่เห็นเลขปัจจุบัน JPG/PNG</p>
        @endunless
    </div>
</div>

<div class="flex flex-wrap items-center gap-1.5 sm:gap-2 mt-2.5 pt-2.5 sm:mt-3 sm:pt-3 border-t border-gray-100">
    <input type="hidden" name="{{ $namePrefix }}[meter_changed]" value="{{ $changedInit ? '1' : '0' }}" :value="changed ? '1' : '0'" @disabled($alreadyInvoiced)>
    <button type="button"
            data-changed-btn
            @click="toggleChanged()"
            :class="{ 'bg-brand-600 border-brand-600 text-white': changed, 'bg-white border-gray-200 text-gray-500 hover:border-gray-300': ! changed }"
            :aria-pressed="changed ? 'true' : 'false'"
            @disabled($alreadyInvoiced)
            class="inline-flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 rounded-full border text-xs font-medium transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed {{ $changedInit ? 'bg-brand-600 border-brand-600 text-white' : 'bg-white border-gray-200 text-gray-500' }}">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/>
        </svg>
        มิเตอร์เริ่มนับใหม่
    </button>
    <span x-show="!changed" @if($changedInit) style="display: none" @endif class="text-[11px] text-gray-400">มิเตอร์วนกลับเป็น 0 หรือเปลี่ยนมิเตอร์ใหม่ (เช่น มิเตอร์เสีย)</span>
</div>

@if($reading?->meter_reset)
    {{-- แถวเก่าที่บันทึกแบบ "มิเตอร์รีเซ็ต" ไว้ (ก่อนตัดปุ่มนี้ หรือแอดมินบันทึก) - บันทึกซ้ำจากหน้านี้จะไม่ใช้สูตรรีเซ็ตแล้ว --}}
    <p class="flex items-start gap-1.5 text-[11px] text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-2.5 py-1.5 mt-2">
        <svg class="w-3.5 h-3.5 flex-shrink-0 mt-px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span>งวดนี้บันทึกแบบ "มิเตอร์รีเซ็ต" (แบบเดิม) ไว้@unless($alreadyInvoiced) ถ้าบันทึกซ้ำ ให้กด "มิเตอร์เริ่มนับใหม่" แล้วกรอกเลขให้ครบ@endunless</span>
    </p>
@endif

{{-- ปิดสวิตช์อยู่ = ช่องในกล่องนี้ถูก disabled จึงไม่ถูกส่งไปกับฟอร์ม --}}
<div x-show="changed" @unless($changedInit) style="display: none" @endunless class="mt-2.5 sm:mt-3">
    <div class="grid grid-cols-2 {{ $restartPhotoOn ? 'lg:grid-cols-3' : '' }} gap-2.5 sm:gap-3">
        <x-form.meter-digits
            :name="$namePrefix . '[old_meter_final_reading]'"
            field="oldFinal"
            label="เลขสุดท้ายก่อนเริ่มนับใหม่"
            :req="$alreadyInvoiced ? null : 'filled'"
            :current-filled="$currentFilled"
            :digits="$digits"
            :value="$oldFinalValue"
            :default="$oldFinalDefault"
            :is-default="$oldFinalIsDefault"
            :disabled="$alreadyInvoiced || ! $changedInit"
            x-bind:disabled="readOnly || ! changed" />
        <x-form.meter-digits
            :name="$namePrefix . '[new_meter_start_reading]'"
            field="newStart"
            label="เลขเริ่มต้นใหม่"
            :req="$alreadyInvoiced ? null : 'filled'"
            :current-filled="$currentFilled"
            :digits="$digits"
            :value="$newStartValue"
            :default="$newStartDefault"
            :is-default="$newStartIsDefault"
            :disabled="$alreadyInvoiced || ! $changedInit"
            x-bind:disabled="readOnly || ! changed" />
        @if($restartPhotoOn)
            {{-- รูปเลขสุดท้ายก่อนเริ่มนับใหม่ ไม่บังคับ - ปิดสวิตช์ = ช่องนี้ถูก disable และรูปเดิมของงวดนี้ถูกลบตอนบันทึก --}}
            <div class="col-span-2 lg:col-span-1" data-photo-slot="restart" data-saved-src="{{ $savedRestartPhotoSrc }}">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">รูปเลขสุดท้ายก่อนเริ่มนับใหม่ <span class="text-xs font-normal text-gray-400">(ถ้ามี)</span></label>
                @include('meters.partials.photo-picker', [
                    'slot'      => 'restart',
                    'inputName' => $namePrefix . '[restart_image]',
                    'hasError'  => $photoErr('restart_image'),
                    'savedSrc'  => $savedRestartPhotoSrc,
                    'alt'       => 'รูปเลขสุดท้ายก่อนเริ่มนับใหม่',
                    'disabled'  => $alreadyInvoiced || ! $changedInit,
                ])
                @unless($alreadyInvoiced)
                    <p class="text-gray-400 text-xs mt-1">รูปหน้าปัดที่เห็นเลขสุดท้าย ก่อนวนกลับหรือก่อนเปลี่ยนมิเตอร์</p>
                @endunless
            </div>
        @endif
    </div>
    <p class="text-gray-400 text-xs mt-1.5 leading-relaxed">
        <span class="inline-block w-2.5 h-2.5 rounded-sm bg-yellow-50 border border-yellow-300 align-middle mr-0.5"></span>
        ช่องสีเหลือง = ค่าเริ่มต้น (มิเตอร์วนจาก {{ $oldFinalDefault }} กลับเป็น {{ $newStartDefault }}) ถ้าเปลี่ยนมิเตอร์ใหม่ ให้แก้เป็นเลขจริง
        &middot; หน่วยที่ใช้ = (เลขสุดท้าย - เลขเดือนก่อน) + (เลขปัจจุบัน - เลขเริ่มต้นใหม่)
    </p>
</div>

{{-- สูตรสด เช่น "02450 - 02310 = 140 หน่วย · เดือนก่อนใช้ 120 หน่วย" / เลขน้อยกว่าเดือนก่อน = ตัวแดง --}}
<p x-show="calcLine" x-cloak x-text="calcLine"
   :class="calcWarning ? 'text-red-600 font-semibold' : 'text-gray-500'"
   class="mt-2 text-xs tabular-nums"></p>

{{-- Lightbox แสดงรูปเต็มขนาด - ไม่เปิดแท็บใหม่ --}}
<div x-show="lightboxSrc" x-cloak
     @click="lightboxSrc = null"
     @keydown.escape.window="lightboxSrc = null"
     x-transition.opacity
     class="fixed inset-0 z-[60] bg-black/80 flex items-center justify-center p-4">
    <button type="button" @click.stop="lightboxSrc = null"
            class="absolute top-4 right-4 w-9 h-9 flex items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20 transition-colors"
            aria-label="ปิดรูป">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
    </button>
    <img :src="lightboxSrc" @click.stop class="max-w-full max-h-full rounded-xl shadow-2xl" alt="">
</div>
</div>
