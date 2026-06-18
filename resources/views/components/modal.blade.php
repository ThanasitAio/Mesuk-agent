@props([
    'id',
    'title'       => null,
    'size'        => 'md',
    'closeable'   => true,
    'icon'        => null,
    'iconVariant' => 'brand',
])

@php
    $sizes = [
        'sm'   => 'max-w-sm',
        'md'   => 'max-w-lg',
        'lg'   => 'max-w-2xl',
        'xl'   => 'max-w-4xl',
        'full' => 'max-w-full mx-4',
    ];
    $sizeClass = $sizes[$size] ?? $sizes['md'];

    $iconColors = [
        'brand'   => 'bg-brand-100 text-brand-600',
        'danger'  => 'bg-red-100 text-red-600',
        'warning' => 'bg-amber-100 text-amber-600',
        'info'    => 'bg-blue-100 text-blue-600',
        'success' => 'bg-green-100 text-green-600',
    ];
    $iconColor = $iconColors[$iconVariant] ?? $iconColors['brand'];
@endphp

{{-- Backdrop --}}
<div id="{{ $id }}_backdrop"
     class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-end sm:items-center justify-center p-0 sm:p-4"
     onclick="{{ $closeable ? 'closeModal(\''. $id .'\')' : '' }}"
     role="dialog"
     aria-modal="true"
     aria-labelledby="{{ $id }}_title">

    {{-- Panel --}}
    <div id="{{ $id }}_panel"
         class="relative w-full {{ $sizeClass }} bg-white rounded-t-2xl sm:rounded-2xl shadow-2xl
                translate-y-full sm:translate-y-0 sm:scale-95 sm:opacity-0
                transition-all duration-300 ease-out"
         onclick="event.stopPropagation()">

        {{-- Drag handle (mobile) --}}
        <div class="flex justify-center pt-3 sm:hidden">
            <div class="w-10 h-1 bg-gray-300 rounded-full"></div>
        </div>

        {{-- Header --}}
        @if($title || $icon)
            <div class="flex items-start gap-4 px-6 pt-5 pb-4 border-b border-gray-100">
                @if($icon)
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 {{ $iconColor }}">
                        {!! $icon !!}
                    </div>
                @endif
                <div class="flex-1 min-w-0">
                    @if($title)
                        <h3 id="{{ $id }}_title" class="text-base font-semibold text-gray-800">{{ $title }}</h3>
                    @endif
                    @if(isset($subtitle))
                        <p class="text-sm text-gray-500 mt-0.5">{{ $subtitle }}</p>
                    @endif
                </div>
                @if($closeable)
                    <button type="button"
                            onclick="closeModal('{{ $id }}')"
                            class="flex-shrink-0 p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors"
                            aria-label="ปิด">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                @endif
            </div>
        @elseif($closeable)
            <button type="button"
                    onclick="closeModal('{{ $id }}')"
                    class="absolute top-4 right-4 p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors z-10"
                    aria-label="ปิด">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        @endif

        {{-- Body --}}
        <div class="px-6 py-5 text-sm text-gray-600 leading-relaxed">
            {{ $slot }}
        </div>

        {{-- Footer --}}
        @if(isset($footer))
            <div class="px-6 pb-6 pt-0 flex flex-col sm:flex-row items-stretch sm:items-center justify-end gap-3 border-t border-gray-100 pt-4">
                {{ $footer }}
            </div>
        @endif

    </div>
</div>
