@props([
    'variant' => 'primary',
    'type'    => 'button',
    'href'    => null,
    'size'    => 'md',
])

@php
    $variants = [
        'primary'      => 'bg-brand-600 hover:bg-brand-700 text-white',
        'secondary'    => 'bg-gray-100 hover:bg-gray-200 text-gray-700',
        'danger'       => 'bg-red-50 hover:bg-red-100 text-red-600',
        'danger-fill'  => 'bg-red-600 hover:bg-red-700 text-white',
        'amber'        => 'bg-[#FFA02E] hover:bg-[#e08820] text-white',
        'amber-ghost'  => 'bg-[#FFEF91] hover:bg-[#ffe566] text-[#6b4900]',
        'success'      => 'bg-brand-600 hover:bg-brand-700 text-white',
        'outline'      => 'border border-gray-300 hover:bg-gray-50 text-gray-700',
        'blue'         => 'bg-blue-600 hover:bg-blue-700 text-white',
    ];
    $sizes = [
        'sm' => 'text-xs px-3 py-1.5 rounded-lg',
        'md' => 'text-sm px-6 py-2.5 rounded-xl',
    ];
    $colorClass = $variants[$variant] ?? $variants['primary'];
    $sizeClass  = $sizes[$size] ?? $sizes['md'];
    $baseClass  = "inline-flex items-center justify-center gap-2 font-semibold transition-colors $colorClass $sizeClass";
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $baseClass]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $baseClass]) }}>{{ $slot }}</button>
@endif
