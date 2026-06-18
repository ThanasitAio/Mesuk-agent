@props([
    'name',
    'label'    => null,
    'value'    => '',
    'required' => false,
    'hint'     => null,
    'from'     => null,
    'to'       => null,
])

@php
    $from     = $from ?? (now()->year - 10);
    $to       = $to   ?? (now()->year + 2);
    $selected = (string) old($name, $value);

    $hasError   = $errors->has($name);
    $baseClass  = 'w-full px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent bg-white transition appearance-none';
    $stateClass = $hasError ? 'border-red-400 bg-red-50' : 'border-gray-300';
@endphp

<div>
    @if($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-gray-700 mb-1.5">
            {{ $label }}@if($required) <span class="text-red-500">*</span>@endif
        </label>
    @endif

    <select
        name="{{ $name }}"
        id="{{ $name }}"
        {{ $attributes->merge(['class' => "$baseClass $stateClass"]) }}
    >
        <option value="">— เลือกปี —</option>
        @for($y = $to; $y >= $from; $y--)
            <option value="{{ $y }}" @selected($selected === (string)$y)>{{ $y + 543 }} ({{ $y }})</option>
        @endfor
    </select>

    @if($hint && !$hasError)
        <p class="text-gray-400 text-xs mt-1">{{ $hint }}</p>
    @endif

    @error($name)
        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
    @enderror
</div>
