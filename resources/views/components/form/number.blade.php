@props([
    'name',
    'label'       => null,
    'value'       => '',
    'placeholder' => '',
    'required'    => false,
    'hint'        => null,
    'min'         => null,
    'max'         => null,
    'step'        => null,
    'disabled'    => false,
    'prefix'      => null,
    'suffix'      => null,
])

@php
    $hasError  = $errors->has($name);
    $ring      = $hasError
        ? 'border-red-400 bg-red-50 focus-within:ring-red-400/30'
        : 'border-gray-300 focus-within:border-brand-500 focus-within:ring-brand-500/20';
    $wrapClass = "flex items-center w-full border rounded-xl text-sm transition-all focus-within:ring-2 $ring bg-white"
        . ($disabled ? ' opacity-50 cursor-not-allowed' : '');
@endphp

<div>
    @if($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-gray-700 mb-1.5">
            {{ $label }}@if($required) <span class="text-red-500">*</span>@endif
        </label>
    @endif

    <div class="{{ $wrapClass }}">
        @if($prefix)
            <span class="pl-3 text-gray-500 text-sm font-medium flex-shrink-0 select-none border-r border-gray-200 pr-3">{{ $prefix }}</span>
        @endif

        <input
            type="number"
            name="{{ $name }}"
            id="{{ $name }}"
            value="{{ old($name, $value) }}"
            placeholder="{{ $placeholder }}"
            inputmode="numeric"
            @if($min !== null) min="{{ $min }}" @endif
            @if($max !== null) max="{{ $max }}" @endif
            @if($step !== null) step="{{ $step }}" @endif
            @disabled($disabled)
            {{ $attributes->merge(['class' =>
                'flex-1 min-w-0 py-2.5 bg-transparent focus:outline-none text-gray-800 placeholder-gray-400'
                . ($prefix ? ' pl-3' : ' pl-4')
                . ($suffix ? ' pr-2' : ' pr-4')
            ]) }}
        >

        @if($suffix)
            <span class="pr-3 text-gray-500 text-sm flex-shrink-0 select-none">{{ $suffix }}</span>
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
