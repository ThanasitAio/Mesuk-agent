@props([
    'name',
    'label'       => null,
    'value'       => '',
    'required'    => false,
    'hint'        => null,
    'min'         => null,
    'max'         => null,
    'disabled'    => false,
    'clearable'   => false,
    'placeholder' => 'เลือกวันที่',
])

@php
    $hasError = $errors->has($name);
    $uid      = 'date_' . $name . '_' . Str::random(4);
    $initVal  = old($name, $value);

    $ring = $hasError
        ? 'border-red-400 bg-red-50'
        : 'border-gray-300';
@endphp

<div x-data="alpineDatePicker(@js($initVal))" x-init="init()">

    @if($label)
        <label for="{{ $uid }}" class="block text-sm font-medium text-gray-700 mb-1.5">
            {{ $label }}@if($required) <span class="text-red-500">*</span>@endif
        </label>
    @endif

    {{-- Flatpickr wrap: looks for [data-toggle] and [data-input] inside --}}
    <div x-ref="fpWrapper" class="relative">

        {{-- ── Display trigger (data-toggle) ─────────────────────────── --}}
        <div data-toggle
             class="w-full flex items-center justify-between px-4 py-2.5 border {{ $ring }} rounded-xl text-sm bg-white transition-all select-none focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 {{ $disabled ? 'opacity-50 pointer-events-none' : 'cursor-pointer' }}"
             :class="fp && fp.isOpen ? 'border-brand-500 ring-2 ring-brand-500/20' : ''"
             tabindex="{{ $disabled ? '-1' : '0' }}"
             role="button"
             aria-haspopup="true"
             aria-label="เลือกวันที่">

            <span :class="v ? 'text-gray-800' : 'text-gray-400'"
                  x-text="v ? formatThaiDate(v) : '{{ $placeholder }}'"></span>

            <div class="flex items-center gap-0.5 flex-shrink-0">
                @if($clearable)
                <button type="button"
                        x-show="v"
                        x-cloak
                        @click.stop="fp && fp.clear()"
                        class="w-5 h-5 flex items-center justify-center rounded-full text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors focus:outline-none"
                        tabindex="-1" aria-label="ล้างวันที่">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
                @endif

                <svg class="w-4 h-4 text-gray-400 pointer-events-none transition-transform duration-200"
                     :class="fp && fp.isOpen ? 'rotate-180' : ''"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </div>
        </div>

        {{-- Hidden input (data-input): Flatpickr writes ISO date here for form submission --}}
        <input
            data-input
            type="date"
            name="{{ $name }}"
            id="{{ $uid }}"
            value="{{ $initVal }}"
            @if($min) min="{{ $min }}" @endif
            @if($max) max="{{ $max }}" @endif
            @disabled($disabled)
            {{ $attributes->merge(['class' => 'absolute opacity-0 pointer-events-none w-0 h-0 overflow-hidden']) }}
        >
    </div>

    @if($hint && !$hasError)
        <p class="text-gray-400 text-xs mt-1.5 flex items-center gap-1">
            <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            {{ $hint }}
        </p>
    @endif

    @error($name)
        <p class="text-red-500 text-xs mt-1.5 flex items-center gap-1">
            <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            {{ $message }}
        </p>
    @enderror
</div>
