@props([
    'id',
    'title'        => 'ยืนยันการดำเนินการ',
    'action',
    'method'       => 'POST',
    'confirmLabel' => 'ยืนยัน',
    'cancelLabel'  => 'ยกเลิก',
    'iconVariant'  => 'danger',
])

@php
    $iconColors = [
        'danger'  => 'bg-red-100 text-red-600',
        'warning' => 'bg-amber-100 text-amber-600',
        'info'    => 'bg-blue-100 text-blue-600',
        'success' => 'bg-green-100 text-green-600',
        'brand'   => 'bg-brand-100 text-brand-600',
    ];
    $iconColor = $iconColors[$iconVariant] ?? $iconColors['danger'];

    $confirmVariants = [
        'danger'  => 'bg-red-600 hover:bg-red-700 text-white',
        'warning' => 'bg-amber-500 hover:bg-amber-600 text-white',
        'info'    => 'bg-blue-600 hover:bg-blue-700 text-white',
        'success' => 'bg-green-600 hover:bg-green-700 text-white',
        'brand'   => 'bg-brand-600 hover:bg-brand-700 text-white',
    ];
    $confirmClass = $confirmVariants[$iconVariant] ?? $confirmVariants['danger'];

    $spoofMethod = !in_array(strtoupper($method), ['GET', 'POST']);
@endphp

<div id="{{ $id }}_backdrop"
     class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-end sm:items-center justify-center p-0 sm:p-4"
     onclick="closeModal('{{ $id }}')"
     role="dialog" aria-modal="true" aria-labelledby="{{ $id }}_title">

    <div id="{{ $id }}_panel"
         class="relative w-full max-w-sm bg-white rounded-t-2xl sm:rounded-2xl shadow-2xl
                translate-y-full sm:translate-y-0 sm:scale-95 sm:opacity-0
                transition-all duration-300 ease-out"
         onclick="event.stopPropagation()">

        <div class="flex justify-center pt-3 sm:hidden">
            <div class="w-10 h-1 bg-gray-300 rounded-full"></div>
        </div>

        <div class="flex items-start gap-4 px-6 pt-5 pb-4 border-b border-gray-100">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 {{ $iconColor }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.07 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                </svg>
            </div>
            <div class="flex-1 min-w-0 pt-1">
                <h3 id="{{ $id }}_title" class="text-base font-semibold text-gray-800">{{ $title }}</h3>
            </div>
            <button type="button"
                    onclick="closeModal('{{ $id }}')"
                    class="flex-shrink-0 p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors"
                    aria-label="ปิด">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="px-6 py-5 text-sm text-gray-600 leading-relaxed">
            {{ $slot }}
        </div>

        <div class="px-6 pt-4 flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-3 border-t border-gray-100"
             style="padding-bottom: max(env(safe-area-inset-bottom, 0px), 1.5rem)">
            <button type="button"
                    onclick="closeModal('{{ $id }}')"
                    class="px-4 py-2 rounded-xl text-sm font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 transition-colors">
                {{ $cancelLabel }}
            </button>
            <form id="{{ $id }}_form" action="{{ $action }}" method="POST">
                @csrf
                @if($spoofMethod)
                    @method(strtoupper($method))
                @endif
                <button type="submit"
                        class="w-full sm:w-auto px-4 py-2 rounded-xl text-sm font-semibold {{ $confirmClass }} transition-colors">
                    {{ $confirmLabel }}
                </button>
            </form>
        </div>

    </div>
</div>
