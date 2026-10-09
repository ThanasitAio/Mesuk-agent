{{-- ฟิลด์กรอกมิเตอร์ 1 ตัว (เลขก่อน/ปัจจุบัน, วันที่, รูปภาพ, ปุ่มมิเตอร์เริ่มนับใหม่) --}}
{{-- ใช้ร่วมกันทั้งมิเตอร์ย่อย (meters/show, x-data="meterImageRow(...)") และมิเตอร์หลัก (master-meters/show,
     x-data="masterMeterRow(...)") - ต้องอยู่ภายใน element ที่มี x-data แบบใดแบบหนึ่งนั้น --}}
{{-- ไม่มีปุ่ม "มิเตอร์รีเซ็ต" แยกแล้ว (ผู้ใช้ขอตัด 2026-10-09): "มิเตอร์เริ่มนับใหม่" (meter_changed) ใช้ได้ทั้งมิเตอร์วนกลับเป็น 0
     และเปลี่ยนมิเตอร์ใหม่ ฝั่งเซิร์ฟเวอร์บันทึก meter_reset = false เสมอ --}}
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
                :disabled="$alreadyInvoiced"
                min="0" />
        @endif
    </div>

    <x-form.number
        :name="$namePrefix . '[current_reading]'"
        label="เลขมิเตอร์ปัจจุบัน"
        :value="$currentInit"
        class="text-right"
        x-model.number="currentReading"
        :disabled="$alreadyInvoiced"
        min="0" />

    <x-form.date
        :name="$namePrefix . '[reading_date]'"
        label="วันที่อ่านมิเตอร์"
        :value="old('readings.' . $meter->id . '.reading_date', optional($reading?->reading_date)->format('Y-m-d'))"
        :max="now()->format('Y-m-d')"
        :disabled="$alreadyInvoiced"
        data-prev-date="{{ $previousReadingDates[$meter->id] ?? '' }}" />

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">รูปภาพมิเตอร์</label>
        {{-- ปุ่มเลือกรูป - สูงเท่า input อื่นในแถวเดียวกันเสมอ ไม่ว่าจะมีรูปหรือไม่ เพื่อไม่ให้ layout ของแถวเพี้ยน --}}
        <label class="flex items-center justify-center gap-1.5 h-[42px] w-full px-3 rounded-xl border {{ $errors->has("readings.{$meter->id}.image") ? 'border-red-400 bg-red-50' : 'border-gray-200 bg-white hover:bg-gray-50 hover:border-gray-300' }} text-xs font-medium text-gray-500 transition-colors {{ $alreadyInvoiced ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' }}">
            <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 19.5h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5z"/>
            </svg>
            <span class="truncate" x-text="previewUrl ? 'เปลี่ยนรูปภาพ' : 'เลือกรูปภาพ'"></span>
            <input type="file" name="{{ $namePrefix }}[image]" accept="image/jpeg,image/png" class="hidden"
                   x-ref="fileInput"
                   @change="onFileChange($event)"
                   @disabled($alreadyInvoiced)>
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
                    @disabled($alreadyInvoiced)
                    class="absolute -top-2 -right-2 w-6 h-6 flex items-center justify-center rounded-full bg-red-500 text-white shadow hover:bg-red-600 transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
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
    <input type="hidden" name="{{ $namePrefix }}[meter_changed]" :value="changed ? 1 : 0">
    <button type="button"
            @click="changed = !changed"
            :class="changed ? 'bg-brand-600 border-brand-600 text-white' : 'bg-white border-gray-200 text-gray-500 hover:border-gray-300'"
            @disabled($alreadyInvoiced)
            class="inline-flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 rounded-full border text-xs font-medium transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/>
        </svg>
        มิเตอร์เริ่มนับใหม่
    </button>
    <span x-show="!changed" class="text-[11px] text-gray-400">มิเตอร์วนกลับเป็น 0 หรือเปลี่ยนมิเตอร์ใหม่ (เช่น มิเตอร์เสีย)</span>
</div>

@if($reading?->meter_reset)
    {{-- แถวเก่าที่บันทึกแบบ "มิเตอร์รีเซ็ต" ไว้ (ก่อนตัดปุ่มนี้ หรือแอดมินบันทึก) - บันทึกซ้ำจากหน้านี้จะไม่ใช้สูตรรีเซ็ตแล้ว --}}
    <p class="flex items-start gap-1.5 text-[11px] text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-2.5 py-1.5 mt-2">
        <svg class="w-3.5 h-3.5 flex-shrink-0 mt-px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span>งวดนี้บันทึกแบบ "มิเตอร์รีเซ็ต" (แบบเดิม) ไว้ ถ้าบันทึกซ้ำ ให้กด "มิเตอร์เริ่มนับใหม่" แล้วกรอกเลขให้ครบ</span>
    </p>
@endif

<div x-show="changed" x-cloak class="mt-2.5 sm:mt-3">
    <div class="grid grid-cols-2 items-end gap-2.5 sm:gap-3">
        <x-form.number
            :name="$namePrefix . '[old_meter_final_reading]'"
            label="เลขสุดท้าย"
            :value="$oldFinalInit"
            class="text-right"
            x-model.number="oldFinal"
            :disabled="$alreadyInvoiced"
            min="0" />
        <x-form.number
            :name="$namePrefix . '[new_meter_start_reading]'"
            label="เลขเริ่มใหม่"
            :value="$newStartInit"
            class="text-right"
            x-model.number="newStart"
            :disabled="$alreadyInvoiced"
            min="0" />
    </div>
    <p class="text-gray-400 text-xs mt-1.5">เลขสุดท้ายก่อนมิเตอร์วนกลับ/ก่อนถอดตัวเก่า และเลขที่เริ่มนับใหม่ - หน่วยที่ใช้ = (เลขสุดท้าย - เลขเดือนก่อน) + (เลขปัจจุบัน - เลขเริ่มใหม่)</p>
</div>
