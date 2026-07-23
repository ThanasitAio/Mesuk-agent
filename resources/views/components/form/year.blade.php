@props([
    'name',
    'label'    => null,
    'value'    => '',
    'required' => false,
    'hint'     => null,
    'from'     => null,
    'to'       => null,
    'disabled' => false,
])

@php
    $from     = $from ?? (now()->year - 10);
    $to       = $to   ?? (now()->year + 2);
    $selected = (string) old($name, $value);

    $hasError   = $errors->has($name);
    $ring       = $hasError ? 'border-red-400 bg-red-50' : 'border-gray-300';
    $baseClass  = 'w-full pl-4 pr-10 py-2.5 border rounded-xl text-sm focus:outline-none focus:ring-2 bg-white transition appearance-none'
        . " $ring"
        . ($hasError ? ' focus:ring-red-400/30' : ' focus:border-brand-500 focus:ring-brand-500/20')
        . ($disabled ? ' opacity-50 cursor-not-allowed' : ' cursor-pointer');
@endphp

<div>
    @if($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-gray-700 mb-1.5">
            {{ $label }}@if($required) <span class="text-red-500">*</span>@endif
        </label>
    @endif

    <div class="relative">
        <select
            name="{{ $name }}"
            id="{{ $name }}"
            @disabled($disabled)
            {{ $attributes->merge(['class' => $baseClass]) }}
        >
            <option value="">- เลือกปี -</option>
            @for($y = $to; $y >= $from; $y--)
                <option value="{{ $y }}" @selected($selected === (string)$y)>{{ $y + 543 }} ({{ $y }})</option>
            @endfor
        </select>
        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-400">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </span>
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
