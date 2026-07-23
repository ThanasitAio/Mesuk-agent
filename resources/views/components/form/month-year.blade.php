@props([
    'nameMonth'  => 'month',
    'nameYear'   => 'year',
    'label'      => null,
    'valueMonth' => '',
    'valueYear'  => '',
    'required'   => false,
    'yearFrom'   => null,
    'yearTo'     => null,
    'disabled'   => false,
])

@php
    $yearFrom = $yearFrom ?? (now()->year - 10);
    $yearTo   = $yearTo   ?? (now()->year + 2);

    $months = [
        1  => 'มกราคม',    2  => 'กุมภาพันธ์', 3  => 'มีนาคม',
        4  => 'เมษายน',    5  => 'พฤษภาคม',   6  => 'มิถุนายน',
        7  => 'กรกฎาคม',   8  => 'สิงหาคม',   9  => 'กันยายน',
        10 => 'ตุลาคม',    11 => 'พฤศจิกายน', 12 => 'ธันวาคม',
    ];

    $selMonth = (string) old($nameMonth, $valueMonth);
    $selYear  = (string) old($nameYear,  $valueYear);

    $hasMonthError = $errors->has($nameMonth);
    $hasYearError  = $errors->has($nameYear);

    $baseClass = 'w-full pl-4 pr-10 py-2.5 border rounded-xl text-sm focus:outline-none focus:ring-2 bg-white transition appearance-none cursor-pointer';
    $monthClass = $baseClass . ' ' . ($hasMonthError
        ? 'border-red-400 bg-red-50 focus:ring-red-400/30'
        : 'border-gray-300 focus:border-brand-500 focus:ring-brand-500/20');
    $yearClass  = $baseClass . ' ' . ($hasYearError
        ? 'border-red-400 bg-red-50 focus:ring-red-400/30'
        : 'border-gray-300 focus:border-brand-500 focus:ring-brand-500/20');

    if ($disabled) {
        $monthClass .= ' opacity-50 cursor-not-allowed';
        $yearClass  .= ' opacity-50 cursor-not-allowed';
    }
@endphp

<div>
    @if($label)
        <label class="block text-sm font-medium text-gray-700 mb-1.5">
            {{ $label }}@if($required) <span class="text-red-500">*</span>@endif
        </label>
    @endif

    <div class="grid grid-cols-2 gap-2">
        {{-- Month --}}
        <div class="relative">
            <select name="{{ $nameMonth }}" id="{{ $nameMonth }}"
                    @disabled($disabled)
                    class="{{ $monthClass }}">
                <option value="">- เดือน -</option>
                @foreach($months as $num => $thai)
                    <option value="{{ $num }}" @selected($selMonth === (string)$num)>{{ $thai }}</option>
                @endforeach
            </select>
            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </span>
        </div>

        {{-- Year --}}
        <div class="relative">
            <select name="{{ $nameYear }}" id="{{ $nameYear }}"
                    @disabled($disabled)
                    class="{{ $yearClass }}">
                <option value="">- ปี -</option>
                @for($y = $yearTo; $y >= $yearFrom; $y--)
                    <option value="{{ $y }}" @selected($selYear === (string)$y)>{{ $y + 543 }}</option>
                @endfor
            </select>
            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </span>
        </div>
    </div>

    @error($nameMonth)
        <p class="text-red-500 text-xs mt-1.5 flex items-center gap-1">
            <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            {{ $message }}
        </p>
    @enderror
    @error($nameYear)
        <p class="text-red-500 text-xs mt-1.5 flex items-center gap-1">
            <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            {{ $message }}
        </p>
    @enderror
</div>
