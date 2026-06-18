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
])

@php
    $hasError   = $errors->has($name);
    $baseClass  = 'w-full px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition';
    $stateClass = $hasError ? 'border-red-400 bg-red-50' : 'border-gray-300';
@endphp

<div>
    @if($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-gray-700 mb-1.5">
            {{ $label }}@if($required) <span class="text-red-500">*</span>@endif
        </label>
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
        {{ $attributes->merge(['class' => "$baseClass $stateClass"]) }}
    >

    @if($hint && !$hasError)
        <p class="text-gray-400 text-xs mt-1">{{ $hint }}</p>
    @endif

    @error($name)
        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
    @enderror
</div>
