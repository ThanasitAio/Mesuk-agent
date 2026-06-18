@props(['action' => null])

@php
    $colors = [
        'LOGIN'         => 'bg-green-100 text-green-700',
        'LOGOUT'        => 'bg-gray-100 text-gray-600',
        'CREATE'        => 'bg-blue-100 text-blue-700',
        'UPDATE'        => 'bg-amber-100 text-amber-700',
        'DELETE'        => 'bg-red-100 text-red-700',
        'LOGIN_FAILED'  => 'bg-red-100 text-red-700',
        'LOGIN_BLOCKED' => 'bg-orange-100 text-orange-700',
    ];
    $colorClass = $colors[$action] ?? 'bg-gray-100 text-gray-600';
@endphp

<span {{ $attributes->merge(['class' => "inline-block $colorClass text-xs font-medium px-2 py-0.5 rounded-full whitespace-nowrap"]) }}>
    {{ $action ?? '—' }}
</span>
