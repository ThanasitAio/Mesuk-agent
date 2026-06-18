@props(['paginator', 'label' => 'รายการ'])

<div {{ $attributes->merge(['class' => 'mt-6']) }}>
    @if(!$paginator->hasPages() && $paginator->total() > 0)
        <p class="text-sm text-gray-500 text-center">
            ทั้งหมด <strong class="text-gray-700">{{ number_format($paginator->total()) }}</strong> {{ $label }}@unless($slot->isEmpty()) · {{ $slot }}@endunless
        </p>
    @elseif($paginator->hasPages())
        {{ $paginator->links() }}
        @unless($slot->isEmpty())
            <p class="text-xs text-gray-400 text-center mt-2">{{ $slot }}</p>
        @endunless
    @endif
</div>
