@props([
    'name',
    'label'    => null,
    'value'    => '',
    'required' => false,
    'hint'     => null,
    'min'      => null,
    'max'      => null,
    'disabled' => false,
    'clearable'=> false,
])

@php
    $hasError  = $errors->has($name);
    $uid       = 'date_' . $name . '_' . Str::random(4);
    $ring      = $hasError
        ? 'border-red-400 focus-within:ring-red-400/30'
        : 'border-gray-300 focus-within:border-brand-500 focus-within:ring-brand-500/20';
@endphp

<div>
    @if($label)
        <label for="{{ $uid }}" class="block text-sm font-medium text-gray-700 mb-1.5">
            {{ $label }}@if($required) <span class="text-red-500">*</span>@endif
        </label>
    @endif

    <div class="relative flex items-center border {{ $ring }} rounded-xl bg-white transition-all focus-within:ring-2 {{ $disabled ? 'opacity-50' : '' }}">
        {{-- Calendar icon --}}
        <span class="pl-3 text-gray-400 pointer-events-none flex-shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
        </span>

        <input
            type="date"
            name="{{ $name }}"
            id="{{ $uid }}"
            value="{{ old($name, $value) }}"
            @if($min) min="{{ $min }}" @endif
            @if($max) max="{{ $max }}" @endif
            @disabled($disabled)
            {{ $attributes->merge(['class' =>
                'flex-1 min-w-0 pl-3 py-2.5 bg-transparent text-sm text-gray-800 focus:outline-none'
                . ($clearable ? ' pr-2' : ' pr-4')
            ]) }}
        >

        @if($clearable)
            <button type="button"
                    onclick="document.getElementById('{{ $uid }}').value=''"
                    class="pr-3 text-gray-300 hover:text-gray-500 transition-colors flex-shrink-0 focus:outline-none"
                    tabindex="-1" aria-label="ล้างวันที่">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
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
