@extends('layouts.app')

@section('title', 'โฆษณา')
@section('breadcrumb', 'ลิงก์แอดสำหรับอสังหาริมทรัพย์ทั้งหมด')

@section('content')

@php
    $happyestPublic = rtrim(env('HAPPYEST_APP_URL', 'http://127.0.0.1/happyest/public'), '/');
    $homeAdLink     = $happyestPublic . '?ref=' . session('agent_code');

    $resolveImageUrl = function ($property) use ($happyestPublic) {
        $media = $property->primaryImageMedia;
        if (! $media || ! $media->file_path) {
            return null;
        }

        return str_starts_with($media->file_path, 'http')
            ? $media->file_path
            : $happyestPublic . '/storage/' . $media->file_path;
    };

    $badgeClasses = [
        'red'    => 'text-red-700 bg-red-50 border-red-200',
        'green'  => 'text-green-700 bg-green-50 border-green-200',
        'yellow' => 'text-yellow-700 bg-yellow-50 border-yellow-200',
        'blue'   => 'text-blue-700 bg-blue-50 border-blue-200',
    ];
    $dotClasses = [
        'red' => 'bg-red-500', 'green' => 'bg-green-500', 'yellow' => 'bg-yellow-500', 'blue' => 'bg-blue-500',
    ];

    $totalAll            = $properties->count();
    $totalAvailable      = $properties->where('status_slug', 'available')->count();
    $totalUnavailable    = $properties->where('status_slug', 'unavailable')->count();
    $totalBooked         = $properties->where('status_slug', 'booked')->count();
    $totalFutureProject  = $properties->where('status_slug', 'future_project')->count();

    $cardRows = $properties->map(fn ($property) => [
        'id'          => $property->id,
        'code'        => $property->property_code,
        'title'       => $property->title,
        'slug'        => $property->slug,
        'district'    => $property->district,
        'province'    => $property->province,
        'price'       => $property->price_per_month,
        'managerName' => $property->manager?->name,
        'managerCode' => $property->manager_agent_code,
        'imageUrl'    => $resolveImageUrl($property),
        'statusSlug'  => $property->status_slug,
        'statusColor' => $property->status_color,
        'statusLabel' => $property->status_label,
        'isPublished' => $property->is_published,
        'searchText'  => $property->search_text,
        'adLink'      => $happyestPublic . '/property/' . $property->slug . '?ref=' . session('agent_code'),
    ]);
@endphp

<div x-data="{
    search: '',
    filter: 'all',
    rows: @js($cardRows->map(fn ($r) => ['type' => $r['statusSlug'], 'text' => $r['searchText']])),
    matchRow(type, text) {
        if (this.filter !== 'all' && this.filter !== type) return false;
        if (!this.search.trim()) return true;
        return text.toLowerCase().includes(this.search.toLowerCase().trim());
    },
    get hasMatches() {
        return this.rows.some(r => this.matchRow(r.type, r.text));
    }
}">

{{-- ── ลิงก์หน้าแรก ─────────────────────────────────────────────────────────── --}}
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 sm:p-5 mb-5">
    <div class="flex items-start gap-3 mb-4">
        <div class="w-10 h-10 flex-shrink-0 rounded-xl bg-green-500 bg-gradient-to-br from-green-400 to-green-600
                    flex items-center justify-center shadow-md shadow-green-200">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
            </svg>
        </div>
        <div>
            <p class="text-sm font-bold text-gray-900">ลิงก์สำหรับยิงแอด / ส่งให้ลูกค้า</p>
            <p class="text-xs text-gray-500 mt-0.5">ส่งลิงก์นี้ให้ลูกค้าเพื่อดูรายละเอียดอสังหาริมทรัพย์ หรือใช้ยิงแอดได้ทันที — เมื่อลูกค้าทำการจอง ต้องเลือกรหัสตัวแทนของคุณเองในขั้นตอนจอง ระบบยังไม่ผูกตัวแทนให้อัตโนมัติจากลิงก์</p>
        </div>
    </div>

    <div class="flex items-center gap-2 p-3 rounded-xl bg-green-50 border border-green-200">
        <div class="flex-1 min-w-0">
            <p class="text-xs font-semibold text-green-700">ลิงก์หน้าแรก</p>
            <p class="text-xs text-gray-500 truncate">{{ $homeAdLink }}</p>
        </div>
        <button type="button" onclick="copyAdLink('{{ $homeAdLink }}')"
                class="flex-shrink-0 inline-flex items-center gap-1.5 rounded-lg bg-green-600 hover:bg-green-700 text-white text-xs font-semibold px-3 py-2 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
            </svg>
            คัดลอก
        </button>
    </div>
</div>

{{-- ── Summary Stats ────────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-2.5 sm:gap-3 mb-5">
    <div class="relative overflow-hidden bg-white rounded-2xl shadow-sm border border-gray-100 p-3 sm:p-4">
        <p class="text-xl sm:text-2xl font-black text-gray-900 tabular-nums leading-none">{{ $totalAll }}</p>
        <p class="text-[10px] sm:text-xs text-gray-500 font-medium mt-1.5 leading-tight">ทรัพย์ทั้งหมด</p>
    </div>
    <div class="relative overflow-hidden bg-white rounded-2xl shadow-sm border border-gray-100 p-3 sm:p-4">
        <p class="text-xl sm:text-2xl font-black text-green-600 tabular-nums leading-none">{{ $totalAvailable }}</p>
        <p class="text-[10px] sm:text-xs text-green-500 font-medium mt-1.5 leading-tight">ว่าง</p>
    </div>
    <div class="relative overflow-hidden bg-white rounded-2xl shadow-sm border border-gray-100 p-3 sm:p-4">
        <p class="text-xl sm:text-2xl font-black text-red-600 tabular-nums leading-none">{{ $totalUnavailable }}</p>
        <p class="text-[10px] sm:text-xs text-red-500 font-medium mt-1.5 leading-tight">ไม่ว่าง</p>
    </div>
    <div class="relative overflow-hidden bg-white rounded-2xl shadow-sm border border-gray-100 p-3 sm:p-4">
        <p class="text-xl sm:text-2xl font-black text-yellow-600 tabular-nums leading-none">{{ $totalBooked }}</p>
        <p class="text-[10px] sm:text-xs text-yellow-600 font-medium mt-1.5 leading-tight">จอง</p>
    </div>
    <div class="relative overflow-hidden bg-white rounded-2xl shadow-sm border border-gray-100 p-3 sm:p-4">
        <p class="text-xl sm:text-2xl font-black text-blue-600 tabular-nums leading-none">{{ $totalFutureProject }}</p>
        <p class="text-[10px] sm:text-xs text-blue-500 font-medium mt-1.5 leading-tight">โครงการในอนาคต</p>
    </div>
</div>

@if($totalAll > 0)
{{-- ── Search + Filter ──────────────────────────────────────────────────────── --}}
<div class="flex flex-col gap-2.5 mb-4">
    <div class="relative border border-gray-300 rounded-xl bg-white transition-all focus-within:ring-2 focus-within:ring-brand-500/20 focus-within:border-brand-500">
        <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <input type="text"
               x-model="search"
               placeholder="ค้นหาชื่อทรัพย์ รหัส หรือทำเล..."
               class="w-full pl-11 pr-9 py-3 text-sm bg-transparent focus:outline-none text-gray-800 placeholder-gray-400">
        <button x-show="search" x-cloak @click="search = ''"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-300 hover:text-gray-500 transition-colors focus:outline-none"
                tabindex="-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
    <div class="flex gap-1.5 bg-gray-100 rounded-xl p-1.5 overflow-x-auto">
        <button @click="filter = 'all'"
                :class="filter === 'all' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                class="flex-shrink-0 px-3 sm:px-3.5 py-2 sm:py-2.5 text-xs font-semibold rounded-lg transition-all whitespace-nowrap">ทั้งหมด</button>
        <button @click="filter = 'available'"
                :class="filter === 'available' ? 'bg-white text-green-700 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                class="flex-shrink-0 px-3 sm:px-3.5 py-2 sm:py-2.5 text-xs font-semibold rounded-lg transition-all whitespace-nowrap">ว่าง</button>
        <button @click="filter = 'unavailable'"
                :class="filter === 'unavailable' ? 'bg-white text-red-700 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                class="flex-shrink-0 px-3 sm:px-3.5 py-2 sm:py-2.5 text-xs font-semibold rounded-lg transition-all whitespace-nowrap">ไม่ว่าง</button>
        <button @click="filter = 'booked'"
                :class="filter === 'booked' ? 'bg-white text-yellow-700 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                class="flex-shrink-0 px-3 sm:px-3.5 py-2 sm:py-2.5 text-xs font-semibold rounded-lg transition-all whitespace-nowrap">จอง</button>
        @if($totalFutureProject > 0)
        <button @click="filter = 'future_project'"
                :class="filter === 'future_project' ? 'bg-white text-blue-700 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                class="flex-shrink-0 px-3 sm:px-3.5 py-2 sm:py-2.5 text-xs font-semibold rounded-lg transition-all whitespace-nowrap">โครงการในอนาคต</button>
        @endif
    </div>
</div>

{{-- ── Property Grid ────────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
    @foreach($cardRows as $row)
    <div x-show="matchRow('{{ $row['statusSlug'] }}', @js($row['searchText']))"
         class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow">

        {{-- Thumbnail --}}
        <div class="w-full h-36 bg-gray-100 relative">
            @if($row['imageUrl'])
            <img src="{{ $row['imageUrl'] }}" alt="{{ $row['title'] }}"
                 class="w-full h-full object-cover"
                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
            @endif
            <div class="w-full h-full items-center justify-center absolute inset-0 {{ $row['imageUrl'] ? 'hidden' : 'flex' }}">
                <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
            </div>
            <span class="absolute top-2 left-2 inline-flex items-center gap-1 text-[10px] font-semibold {{ $badgeClasses[$row['statusColor']] }} border px-2 py-0.5 rounded-full">
                <span class="w-1.5 h-1.5 rounded-full {{ $dotClasses[$row['statusColor']] }}"></span>{{ $row['statusLabel'] }}
            </span>
            @if(!$row['isPublished'])
            <span class="absolute top-2 right-2 inline-flex items-center text-[10px] font-semibold text-gray-500 bg-white/90 border border-gray-200 px-2 py-0.5 rounded-full">
                รอเผยแพร่
            </span>
            @endif
        </div>

        <div class="p-3.5">
            @if($row['code'])
            <p class="font-mono font-bold text-xs text-gray-500 truncate leading-snug">{{ $row['code'] }}</p>
            @endif
            <p class="text-sm font-bold text-gray-800 truncate leading-snug mt-0.5">{{ $row['title'] ?? '—' }}</p>

            @if($row['district'] || $row['province'])
            <p class="text-xs text-gray-400 truncate mt-1">
                {{ trim(($row['district'] ?? '') . ($row['district'] && $row['province'] ? ', ' : '') . ($row['province'] ?? '')) }}
            </p>
            @endif

            <div class="flex items-center justify-between gap-2 mt-2">
                @if($row['price'])
                <span class="text-sm font-bold text-gray-700 tabular-nums">{{ number_format($row['price'], 0) }} <span class="font-normal text-gray-400 text-[11px]">฿/ด.</span></span>
                @else
                <span></span>
                @endif
                @if($row['managerName'])
                <span class="text-[11px] text-gray-400 truncate max-w-[45%]" title="ผู้บริหารโครงการ: {{ $row['managerName'] }}">{{ $row['managerName'] }}</span>
                @endif
            </div>

            <div class="mt-3 pt-3 border-t border-gray-100">
                @if($row['isPublished'])
                <button type="button"
                        onclick="copyAdLink('{{ $row['adLink'] }}')"
                        class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg bg-green-600 hover:bg-green-700 text-white text-xs font-semibold px-3 py-2 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                    คัดลอกลิงก์แอด
                </button>
                @else
                <span class="w-full inline-flex items-center justify-center text-xs font-medium text-gray-400 bg-gray-50 border border-gray-100 rounded-lg px-3 py-2"
                      title="ต้องรอเผยแพร่ก่อนจึงจะยิงแอดได้">
                    ยังไม่เผยแพร่ ยิงแอดไม่ได้
                </span>
                @endif
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- ── No results ───────────────────────────────────────────────────────────── --}}
<div x-show="!hasMatches" x-cloak class="py-16 text-center">
    <div class="w-14 h-14 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-3">
        <svg class="w-7 h-7 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
    </div>
    <p class="text-gray-700 font-semibold">ไม่มีข้อมูล</p>
    <p class="text-sm text-gray-400 mt-1">ไม่พบรายการที่ตรงกับตัวกรองหรือคำค้นหานี้</p>
</div>
@endif

{{-- ── Empty State ──────────────────────────────────────────────────────────── --}}
@if($totalAll === 0)
<div class="py-24 text-center">
    <div class="w-16 h-16 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
        </svg>
    </div>
    <p class="text-gray-800 font-bold">ยังไม่มีอสังหาริมทรัพย์ในระบบ</p>
</div>
@endif

</div>{{-- /x-data --}}

@endsection
