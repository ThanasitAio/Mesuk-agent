@props([
    'nameMonth'  => 'month',
    'nameYear'   => 'year',
    'label'      => null,
    'valueMonth' => '',
    'valueYear'  => '',
    'required'   => false,
    'yearFrom'   => null,
    'yearTo'     => null,
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
    $baseClass     = 'w-full px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent bg-white transition appearance-none';
@endphp

<div>
    @if($label)
        <label class="block text-sm font-medium text-gray-700 mb-1.5">
            {{ $label }}@if($required) <span class="text-red-500">*</span>@endif
        </label>
    @endif

    <div class="grid grid-cols-2 gap-2">
        {{-- Month --}}
        <select name="{{ $nameMonth }}" id="{{ $nameMonth }}"
                class="{{ $baseClass }} {{ $hasMonthError ? 'border-red-400 bg-red-50' : 'border-gray-300' }}">
            <option value="">— เดือน —</option>
            @foreach($months as $num => $thai)
                <option value="{{ $num }}" @selected($selMonth === (string)$num)>{{ $thai }}</option>
            @endforeach
        </select>

        {{-- Year --}}
        <select name="{{ $nameYear }}" id="{{ $nameYear }}"
                class="{{ $baseClass }} {{ $hasYearError ? 'border-red-400 bg-red-50' : 'border-gray-300' }}">
            <option value="">— ปี —</option>
            @for($y = $yearTo; $y >= $yearFrom; $y--)
                <option value="{{ $y }}" @selected($selYear === (string)$y)>{{ $y + 543 }}</option>
            @endfor
        </select>
    </div>

    @error($nameMonth)
        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
    @enderror
    @error($nameYear)
        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
    @enderror
</div>
