@props([
    'name',
    'label'       => null,
    'value'       => '',
    'placeholder' => '',
    'rows'        => 3,
    'required'    => false,
    'hint'        => null,
    'disabled'    => false,
])

@php
    $hasError   = $errors->has($name);
    $stateClass = $hasError
        ? 'border-red-400 bg-red-50 focus:ring-red-400/30'
        : 'border-gray-300 focus:border-brand-500 focus:ring-brand-500/20';
    $baseClass  = 'w-full px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:ring-2 transition resize-none';
@endphp

<div>
    @if($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-gray-700 mb-1.5">
            {{ $label }}@if($required) <span class="text-red-500">*</span>@endif
        </label>
    @endif

    <textarea
        name="{{ $name }}"
        id="{{ $name }}"
        rows="{{ $rows }}"
        placeholder="{{ $placeholder }}"
        @disabled($disabled)
        {{ $attributes->merge(['class' => "$baseClass $stateClass" . ($disabled ? ' opacity-50 cursor-not-allowed' : '')]) }}
    >{{ old($name, $value) }}</textarea>

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
