{{-- ปุ่มเลือกรูป + รูปย่อข้างปุ่ม สูงเท่าช่องเลขมิเตอร์ (ใช้ใน meters/partials/reading-fields เท่านั้น ล้อหน้าแอดมิน)
     สถานะอยู่ใน photos[$slot] ของ meterReadingRow() (meters/partials/reading-scripts): main = รูปหน้าปัด,
     restart = รูปเลขสุดท้ายก่อนเริ่มนับใหม่ - แตะรูปย่อ = ดูรูปใหญ่, ปุ่ม x ยกเลิกได้แค่รูปที่เพิ่งเลือก (กลับไปรูปที่บันทึกไว้)
     ตัวแปร: $slot, $inputName, $hasError (error จากเซิร์ฟเวอร์), $savedSrc, $alt, $disabled (สถานะตอน render) --}}
@php
    $okCls  = 'border-gray-200 bg-white text-gray-500 hover:bg-gray-50 hover:border-gray-300';
    $errCls = 'border-red-400 bg-red-50 text-red-600';
@endphp
<div class="flex items-center gap-2">
    <label data-photo-pick
           data-photo-invalid="{{ $hasError ? '1' : '0' }}"
           class="flex-1 min-w-0 flex items-center justify-center gap-1.5 h-[42px] px-3 rounded-xl border text-xs font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-brand-500/20 {{ $hasError ? $errCls : $okCls }} {{ $disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' }}"
           x-bind:class="{
               @js($errCls): photos.{{ $slot }}.invalid,
               @js($okCls): ! photos.{{ $slot }}.invalid,
               'opacity-50 cursor-not-allowed': photoDisabled('{{ $slot }}'),
               'cursor-pointer': ! photoDisabled('{{ $slot }}') && ! photos.{{ $slot }}.busy,
               'cursor-wait': photos.{{ $slot }}.busy,
           }"
           x-bind:tabindex="photoDisabled('{{ $slot }}') ? -1 : 0"
           role="button"
           @keydown.enter.prevent="pickPhoto('{{ $slot }}')"
           @keydown.space.prevent="pickPhoto('{{ $slot }}')">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
        <span class="truncate" x-text="photoLabel('{{ $slot }}')">{{ $savedSrc ? 'เปลี่ยนรูป' : 'เลือกรูป' }}</span>
        <input type="file" name="{{ $inputName }}" accept="image/jpeg,image/png" class="hidden"
               data-photo-input="{{ $slot }}"
               @change="onPhotoChange('{{ $slot }}', $event)"
               x-bind:disabled="photoDisabled('{{ $slot }}')"
               @disabled($disabled)>
    </label>
    <div class="relative flex-shrink-0" x-show="photoSrc('{{ $slot }}')" @unless($savedSrc) style="display: none" @endunless>
        <button type="button" @click="lightboxSrc = photoSrc('{{ $slot }}')" class="block" title="แตะเพื่อดูรูปใหญ่">
            <img src="{{ $savedSrc }}" x-bind:src="photoSrc('{{ $slot }}')" width="42" height="42"
                 class="w-[42px] h-[42px] object-cover rounded-lg border border-gray-200 shadow-sm cursor-zoom-in" alt="{{ $alt }}">
        </button>
        <button type="button" x-show="photos.{{ $slot }}.picked" style="display: none"
                @click="resetPhoto('{{ $slot }}')"
                title="ยกเลิกรูปที่เพิ่งเลือก" aria-label="ยกเลิกรูปที่เพิ่งเลือก"
                class="absolute -top-1.5 -right-1.5 w-5 h-5 flex items-center justify-center rounded-full bg-red-500 text-white shadow hover:bg-red-600 transition-colors">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
</div>
