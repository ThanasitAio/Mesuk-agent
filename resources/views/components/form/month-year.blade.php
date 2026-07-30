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
@endphp

<div>
    @if($label)
        <label class="block text-sm font-medium text-gray-700 mb-1.5">
            {{ $label }}@if($required) <span class="text-red-500">*</span>@endif
        </label>
    @endif

    <div class="grid grid-cols-2 gap-2">
        <x-form.select :name="$nameMonth" :disabled="$disabled" placeholder="- เดือน -">
            <option value="">- เดือน -</option>
            @foreach($months as $num => $thai)
                <option value="{{ $num }}" @selected($selMonth === (string)$num)>{{ $thai }}</option>
            @endforeach
        </x-form.select>

        <x-form.select :name="$nameYear" :disabled="$disabled" placeholder="- ปี -">
            <option value="">- ปี -</option>
            @for($y = $yearTo; $y >= $yearFrom; $y--)
                <option value="{{ $y }}" @selected($selYear === (string)$y)>{{ $y + 543 }}</option>
            @endfor
        </x-form.select>
    </div>
</div>
