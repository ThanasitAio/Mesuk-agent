@if ($paginator->hasPages())
<nav role="navigation" aria-label="Pagination Navigation"
     class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white border border-gray-100 rounded-2xl px-5 py-3.5 shadow-sm">

    {{-- Info text --}}
    <p class="text-sm text-gray-500 text-center sm:text-left order-2 sm:order-1">
        แสดง
        <span class="font-semibold text-gray-800">{{ $paginator->firstItem() }}</span>–<span class="font-semibold text-gray-800">{{ $paginator->lastItem() }}</span>
        จาก <span class="font-semibold text-gray-800">{{ number_format($paginator->total()) }}</span> รายการ
        <span class="text-gray-300 mx-1">·</span>
        หน้า <span class="font-semibold text-gray-800">{{ $paginator->currentPage() }}</span> / <span class="font-semibold text-gray-800">{{ $paginator->lastPage() }}</span>
    </p>

    {{-- Page controls --}}
    <div class="flex items-center justify-center gap-1 order-1 sm:order-2">

        {{-- Previous --}}
        @if ($paginator->onFirstPage())
            <span class="inline-flex items-center justify-center w-9 h-9 text-gray-300 bg-gray-50 border border-gray-200 rounded-xl cursor-not-allowed select-none">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}"
               class="inline-flex items-center justify-center w-9 h-9 text-gray-500 bg-white border border-gray-200 rounded-xl hover:bg-brand-50 hover:text-brand-700 hover:border-brand-300 hover:shadow-sm transition-all duration-150"
               aria-label="หน้าก่อนหน้า">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
            </a>
        @endif

        {{-- Page numbers --}}
        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="inline-flex items-center justify-center w-9 h-9 text-sm text-gray-400 select-none tracking-widest">···</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span aria-current="page"
                              class="inline-flex items-center justify-center w-9 h-9 text-sm font-bold text-white bg-brand-600 border border-brand-600 rounded-xl cursor-default shadow-md ring-2 ring-brand-200 ring-offset-1">
                            {{ $page }}
                        </span>
                    @else
                        <a href="{{ $url }}"
                           class="inline-flex items-center justify-center w-9 h-9 text-sm text-gray-600 bg-white border border-gray-200 rounded-xl hover:bg-brand-50 hover:text-brand-700 hover:border-brand-300 hover:shadow-sm transition-all duration-150"
                           aria-label="ไปหน้า {{ $page }}">
                            {{ $page }}
                        </a>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- Next --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}"
               class="inline-flex items-center justify-center w-9 h-9 text-gray-500 bg-white border border-gray-200 rounded-xl hover:bg-brand-50 hover:text-brand-700 hover:border-brand-300 hover:shadow-sm transition-all duration-150"
               aria-label="หน้าถัดไป">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
            </a>
        @else
            <span class="inline-flex items-center justify-center w-9 h-9 text-gray-300 bg-gray-50 border border-gray-200 rounded-xl cursor-not-allowed select-none">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
            </span>
        @endif

    </div>
</nav>
@endif
