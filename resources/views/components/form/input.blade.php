@props([
    'name',
    'type'        => 'text',
    'label'       => null,
    'value'       => '',
    'placeholder' => '',
    'required'    => false,
    'hint'        => null,
    'disabled'    => false,
    'icon'        => null,
    'prefix'      => null,
    'suffix'      => null,
    'clearable'   => false,
])

@php
    $hasError   = $errors->has($name);
    $isPassword = $type === 'password';
    $uid        = 'inp_' . $name . '_' . Str::random(4);

    $ring       = $hasError
        ? 'border-red-400 bg-red-50 focus-within:ring-red-400/30'
        : 'border-gray-300 focus-within:border-brand-500 focus-within:ring-brand-500/20';

    $wrapClass  = "flex items-center w-full border rounded-xl text-sm transition-all focus-within:ring-2 $ring bg-white"
        . ($disabled ? ' opacity-50 cursor-not-allowed' : '');
@endphp

<div>
    @if($label)
        <label for="{{ $uid }}" class="block text-sm font-medium text-gray-700 mb-1.5">
            {{ $label }}@if($required) <span class="text-red-500">*</span>@endif
        </label>
    @endif

    <div class="{{ $wrapClass }}">

        {{-- Left icon --}}
        @if($icon)
            <span class="pl-3 text-gray-400 flex-shrink-0 pointer-events-none">
                {!! $icon !!}
            </span>
        @elseif($prefix)
            <span class="pl-3 text-gray-500 text-sm font-medium flex-shrink-0 select-none border-r border-gray-200 pr-3">{{ $prefix }}</span>
        @endif

        {{-- Input --}}
        <input
            type="{{ $isPassword ? 'password' : $type }}"
            name="{{ $name }}"
            id="{{ $uid }}"
            value="{{ old($name, $value) }}"
            placeholder="{{ $placeholder }}"
            @disabled($disabled)
            {{ $attributes->merge(['class' =>
                'flex-1 min-w-0 py-2.5 bg-transparent focus:outline-none text-gray-800 placeholder-gray-400'
                . ($icon || $prefix ? ' pl-3' : ' pl-4')
                . ($isPassword || $clearable || $suffix ? ' pr-2' : ' pr-4')
            ]) }}
        >

        {{-- Suffix text --}}
        @if($suffix && !$isPassword)
            <span class="pr-3 text-gray-500 text-sm flex-shrink-0 select-none">{{ $suffix }}</span>
        @endif

        {{-- Clear button --}}
        @if($clearable && !$isPassword)
            <button type="button"
                    onclick="clearInput('{{ $uid }}')"
                    class="pr-2 text-gray-300 hover:text-gray-500 transition-colors flex-shrink-0 focus:outline-none"
                    tabindex="-1" aria-label="ล้างข้อมูล">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        @endif

        {{-- Password toggle --}}
        @if($isPassword)
            <button type="button"
                    onclick="togglePassword('{{ $uid }}')"
                    class="pr-3 text-gray-400 hover:text-gray-600 transition-colors flex-shrink-0 focus:outline-none"
                    tabindex="-1" aria-label="แสดง/ซ่อนรหัสผ่าน">
                <svg id="{{ $uid }}_eye_off" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                <svg id="{{ $uid }}_eye_on" class="w-4 h-4 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                </svg>
            </button>
        @endif
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
