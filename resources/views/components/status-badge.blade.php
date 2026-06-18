@props(['status'])

@php
    $isActive  = $status === 'active';
    $base      = 'inline-flex items-center gap-1 text-xs font-medium px-2.5 py-0.5 rounded-full';
    $color     = $isActive ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600';
    $dotColor  = $isActive ? 'bg-green-500' : 'bg-red-400';
    $label     = $isActive ? 'ใช้งาน' : 'ไม่ใช้งาน';
@endphp

<span {{ $attributes->merge(['class' => "$base $color"]) }}>
    <span class="w-1.5 h-1.5 {{ $dotColor }} rounded-full"></span>
    {{ $label }}
</span>
