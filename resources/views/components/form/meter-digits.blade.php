{{-- ช่องเลขมิเตอร์แบบหน้าปัด: ต้องกรอกครบหลักพอดี (ไฟ 5 / น้ำ 4) และใส่ 0 นำหน้าได้ เช่น 02345 (ผู้ใช้กำหนด 2026-10-12)
     ใช้ type="text" ไม่ใช่ number เพราะ number ทำ 0 นำหน้าหาย และกรอกเกินหลักได้

     ใช้ภายใน x-data="meterReadingRow()" เท่านั้น (meters/partials/reading-fields) - $field = key ใน state v/def/invalid
     ถ้าไม่ส่ง $field = แสดงผลอย่างเดียว (เช่นเลขเดือนก่อนที่ล็อกไว้) ไม่ผูก Alpine

     สีช่อง: ขาว = ปกติ / เหลืองอ่อน = ค่าเริ่มต้นที่ระบบเติมให้ แก้ได้ (แก้แล้วกลับเป็นขาว) / แดง = ต้องแก้

     $req (ดอกจันแดงหลังชื่อช่อง เหมือนหน้าแอดมิน): null = ไม่มี / 'always' = มีเสมอ
     / 'filled' = โผล่เมื่อกรอกเลขปัจจุบันของแถวแล้ว (ช่องที่บังคับเฉพาะเมื่อไม่ข้ามมิเตอร์นี้) - $currentFilled = สถานะตอน render --}}
@props([
    'name',
    'field'         => null,
    'label'         => null,
    'digits'        => 5,
    'value'         => '',
    'default'       => null,
    'isDefault'     => false,
    'disabled'      => false,
    'req'           => null,
    'currentFilled' => false,
])

@php
    $uid       = 'md_' . preg_replace('/\W+/', '_', $name);
    $errorKey  = str_replace(['[', ']'], ['.', ''], $name);
    $hasError  = $errors->has($errorKey);

    // has-[:disabled] = ตามสถานะ disabled ของ input ตอนนั้น (ช่องเริ่มนับใหม่เปิด/ปิดด้วย Alpine)
    $base      = 'flex items-center w-full border rounded-xl transition-colors focus-within:ring-2 has-[:disabled]:opacity-50 has-[:disabled]:cursor-not-allowed';
    $states    = [
        'normal'  => 'bg-white border-gray-300 focus-within:border-brand-500 focus-within:ring-brand-500/20',
        'default' => 'bg-yellow-50 border-yellow-300 focus-within:border-yellow-400 focus-within:ring-yellow-400/30',
        'invalid' => 'bg-red-50 border-red-400 focus-within:border-red-400 focus-within:ring-red-400/30',
    ];
    $initState = $hasError ? 'invalid' : ($isDefault ? 'default' : 'normal');
@endphp

<div>
    @if($label)
        <label for="{{ $uid }}" class="block text-sm font-medium text-gray-700 mb-1.5">{{ $label }}@if($req === 'always') <span class="text-red-500" title="ต้องกรอก">*</span>@elseif($req === 'filled') <span class="text-red-500" title="ต้องกรอกเมื่อกรอกเลขปัจจุบัน" x-show="v.current !== ''" @unless($currentFilled) style="display: none" @endunless>*</span>@endif</label>
    @endif

    <div class="{{ $base }} {{ $states[$initState] }}"
         @if($field)
             x-bind:class="{
                 @js($states['normal']): ! invalid.{{ $field }} && ! def.{{ $field }},
                 @js($states['default']): ! invalid.{{ $field }} && def.{{ $field }},
                 @js($states['invalid']): invalid.{{ $field }},
             }"
         @endif>
        <input type="text"
               name="{{ $name }}"
               id="{{ $uid }}"
               value="{{ $value }}"
               placeholder="{{ str_repeat('0', $digits) }}"
               inputmode="numeric"
               pattern="[0-9]*"
               autocomplete="off"
               autocorrect="off"
               spellcheck="false"
               enterkeyhint="next"
               data-digit-input
               @if($field) data-field="{{ $field }}" data-is-default="{{ $isDefault ? '1' : '0' }}" @endif
               @if($default !== null) data-default="{{ $default }}" @endif
               @if($hasError) aria-invalid="true" @endif
               @disabled($disabled)
               @if($field)
                   x-model="v.{{ $field }}"
                   x-on:input="onDigits('{{ $field }}', $event)"
                   x-on:change="checkLater('{{ $field }}')"
               @endif
               {{ $attributes->merge(['class' => 'flex-1 min-w-0 w-full h-10 py-0 px-3 bg-transparent focus:outline-none text-center font-mono text-base font-bold tracking-[0.2em] tabular-nums text-gray-800 placeholder:text-gray-300 placeholder:font-semibold disabled:cursor-not-allowed']) }}>
    </div>

    @if($field)
        <p class="text-[11px] font-semibold text-yellow-700 mt-1"
           x-show="def.{{ $field }} && ! invalid.{{ $field }}"
           @unless($isDefault && ! $hasError) style="display: none" @endunless>ค่าเริ่มต้น แก้ได้</p>
    @endif

    @if($hasError)
        <p class="text-red-500 text-xs mt-1.5" @if($field) x-show="invalid.{{ $field }}" @endif>{{ $errors->first($errorKey) }}</p>
    @endif

    {{ $slot }}
</div>
