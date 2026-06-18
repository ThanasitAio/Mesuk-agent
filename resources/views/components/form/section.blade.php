@props([
    'title',
    'accent'   => 'brand',
    'subtitle' => null,
    'cols'     => 2,
])

@php
    $barColors = [
        'brand'  => 'bg-brand-400',
        'indigo' => 'bg-brand-400',
        'amber'  => 'bg-[#FFA02E]',
        'green'  => 'bg-brand-400',
        'red'    => 'bg-red-400',
        'gray'   => 'bg-gray-400',
    ];
    $barColor = $barColors[$accent] ?? 'bg-brand-400';

    $colsMap = ['1' => 'md:grid-cols-1', '2' => 'md:grid-cols-2', '3' => 'md:grid-cols-3', '4' => 'md:grid-cols-4'];
    $colsClass = $colsMap[(string)$cols] ?? 'md:grid-cols-2';
@endphp

<div>
    <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider mb-4 flex items-center gap-2">
        <span class="w-5 h-0.5 {{ $barColor }} rounded flex-shrink-0"></span>
        {{ $title }}
        @if($subtitle)
            <span class="text-xs text-gray-400 font-normal normal-case tracking-normal">{{ $subtitle }}</span>
        @endif
    </h3>
    <div class="grid grid-cols-1 {{ $colsClass }} gap-4">
        {{ $slot }}
    </div>
</div>
