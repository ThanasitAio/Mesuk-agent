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

{{-- ── Hero: ลิงก์หน้าแรก + สรุปยอด ─────────────────────────────────────────── --}}
<div class="relative overflow-hidden rounded-2xl bg-brand-900 mb-5 p-4 sm:p-5 lg:p-6"
     style="background: linear-gradient(135deg, #1c3514 0%, #2a4f1f 45%, #1c3514 100%);">

    <div class="hero-shimmer-bar"></div>

    <div class="hero-glow" style="width:220px;height:220px;top:-60px;right:-60px;background:rgba(154,216,114,0.12);"></div>
    <div class="hero-glow" style="width:140px;height:140px;bottom:-40px;left:30%;background:rgba(70,132,50,0.10);animation-delay:2s;"></div>

    <div class="hero-blob-1 pointer-events-none absolute -top-10 -right-10 w-44 h-44 rounded-full bg-brand-700 opacity-30"></div>
    <div class="hero-blob-2 pointer-events-none absolute top-2 right-14 w-24 h-24 rounded-full bg-brand-600 opacity-20"></div>
    <div class="hero-blob-3 pointer-events-none absolute -bottom-8 right-4 w-32 h-32 rounded-full bg-brand-800 opacity-40"></div>

    {{-- Title row --}}
    <div class="hero-text-row relative flex items-start gap-3" style="z-index:2;">
        <div class="w-10 h-10 sm:w-11 sm:h-11 flex-shrink-0 rounded-xl flex items-center justify-center"
             style="background:rgba(255,255,255,0.14); backdrop-filter:blur(8px);">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
            </svg>
        </div>
        <div class="min-w-0">
            <h2 class="text-base sm:text-lg font-black text-white leading-tight">ลิงก์สำหรับยิงแอด / ส่งให้ลูกค้า</h2>
            <p class="text-xs sm:text-sm mt-1 leading-relaxed" style="color: rgba(255,255,255,0.65)">
                ส่งลิงก์นี้ให้ลูกค้าเพื่อดูรายละเอียดอสังหาริมทรัพย์ หรือใช้ยิงแอดได้ทันที — เมื่อลูกค้าทำการจอง
                ต้องเลือกรหัสตัวแทนของคุณเองในขั้นตอนจอง ระบบยังไม่ผูกตัวแทนให้อัตโนมัติจากลิงก์
            </p>
        </div>
    </div>

    {{-- Home ad link copy row --}}
    <div x-data="{ copied: false }"
         class="hero-badge-row relative mt-4 flex items-center gap-2 p-3 rounded-xl"
         style="z-index:2; background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.18); backdrop-filter:blur(8px);">
        <div class="flex-1 min-w-0">
            <p class="text-xs font-semibold text-white/90">ลิงก์หน้าแรก</p>
            <p class="text-xs text-white/55 truncate">{{ $homeAdLink }}</p>
        </div>
        <button type="button"
                @click="copyAdLink('{{ $homeAdLink }}'); copied = true; setTimeout(() => copied = false, 1600)"
                :class="copied ? 'ads-copied-pop bg-white text-green-700' : 'bg-white text-brand-700 hover:bg-white/90'"
                class="flex-shrink-0 inline-flex items-center gap-1.5 rounded-lg text-xs font-bold px-3 py-2 transition-all duration-150 active:scale-95 shadow-sm">
            <template x-if="!copied">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
            </template>
            <template x-if="copied">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </template>
            <span x-text="copied ? 'คัดลอกแล้ว' : 'คัดลอก'"></span>
        </button>
    </div>

    {{-- Summary stats strip --}}
    <div class="hero-stats-row relative mt-4 pt-4 grid grid-cols-5 divide-x divide-white/10"
         style="border-top:1px solid rgba(255,255,255,0.12); z-index:2;">
        <div class="text-center px-0.5">
            <p class="text-lg sm:text-xl font-black text-white tabular-nums leading-none">{{ $totalAll }}</p>
            <p class="text-[9px] sm:text-[10px] font-medium mt-1 leading-tight" style="color:rgba(255,255,255,0.6)">ทั้งหมด</p>
        </div>
        <div class="text-center px-0.5">
            <p class="text-lg sm:text-xl font-black text-green-300 tabular-nums leading-none">{{ $totalAvailable }}</p>
            <p class="text-[9px] sm:text-[10px] font-medium mt-1 leading-tight text-green-300/70">ว่าง</p>
        </div>
        <div class="text-center px-0.5">
            <p class="text-lg sm:text-xl font-black text-red-300 tabular-nums leading-none">{{ $totalUnavailable }}</p>
            <p class="text-[9px] sm:text-[10px] font-medium mt-1 leading-tight text-red-300/70">ไม่ว่าง</p>
        </div>
        <div class="text-center px-0.5">
            <p class="text-lg sm:text-xl font-black text-yellow-300 tabular-nums leading-none">{{ $totalBooked }}</p>
            <p class="text-[9px] sm:text-[10px] font-medium mt-1 leading-tight text-yellow-300/70">จอง</p>
        </div>
        <div class="text-center px-0.5">
            <p class="text-lg sm:text-xl font-black text-blue-300 tabular-nums leading-none">{{ $totalFutureProject }}</p>
            <p class="text-[9px] sm:text-[10px] font-medium mt-1 leading-tight text-blue-300/70">โครงการ</p>
        </div>
    </div>
</div>

@if($totalAll > 0)
{{-- ── Search + Filter (sticky) ─────────────────────────────────────────────── --}}
<div class="sticky top-14 z-10 -mx-4 px-4 lg:mx-0 lg:px-0 py-2.5 mb-1 bg-gray-50/90 backdrop-blur-sm">
    <div class="flex flex-col gap-2.5">
        <div class="relative border border-gray-300 rounded-xl bg-white transition-all duration-200 focus-within:ring-2 focus-within:ring-brand-500/20 focus-within:border-brand-500 shadow-sm">
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
                    :class="filter === 'all' ? 'bg-white text-gray-900 shadow-sm scale-[1.03]' : 'text-gray-500 hover:text-gray-700'"
                    class="ads-chip-in flex-shrink-0 px-3 sm:px-3.5 py-2 sm:py-2.5 text-xs font-semibold rounded-lg transition-all duration-200 whitespace-nowrap">
                ทั้งหมด <span class="opacity-60">({{ $totalAll }})</span>
            </button>
            <button @click="filter = 'available'"
                    :class="filter === 'available' ? 'bg-white text-green-700 shadow-sm scale-[1.03]' : 'text-gray-500 hover:text-gray-700'"
                    class="ads-chip-in flex-shrink-0 px-3 sm:px-3.5 py-2 sm:py-2.5 text-xs font-semibold rounded-lg transition-all duration-200 whitespace-nowrap"
                    style="animation-delay:40ms">
                ว่าง <span class="opacity-60">({{ $totalAvailable }})</span>
            </button>
            <button @click="filter = 'unavailable'"
                    :class="filter === 'unavailable' ? 'bg-white text-red-700 shadow-sm scale-[1.03]' : 'text-gray-500 hover:text-gray-700'"
                    class="ads-chip-in flex-shrink-0 px-3 sm:px-3.5 py-2 sm:py-2.5 text-xs font-semibold rounded-lg transition-all duration-200 whitespace-nowrap"
                    style="animation-delay:80ms">
                ไม่ว่าง <span class="opacity-60">({{ $totalUnavailable }})</span>
            </button>
            <button @click="filter = 'booked'"
                    :class="filter === 'booked' ? 'bg-white text-yellow-700 shadow-sm scale-[1.03]' : 'text-gray-500 hover:text-gray-700'"
                    class="ads-chip-in flex-shrink-0 px-3 sm:px-3.5 py-2 sm:py-2.5 text-xs font-semibold rounded-lg transition-all duration-200 whitespace-nowrap"
                    style="animation-delay:120ms">
                จอง <span class="opacity-60">({{ $totalBooked }})</span>
            </button>
            @if($totalFutureProject > 0)
            <button @click="filter = 'future_project'"
                    :class="filter === 'future_project' ? 'bg-white text-blue-700 shadow-sm scale-[1.03]' : 'text-gray-500 hover:text-gray-700'"
                    class="ads-chip-in flex-shrink-0 px-3 sm:px-3.5 py-2 sm:py-2.5 text-xs font-semibold rounded-lg transition-all duration-200 whitespace-nowrap"
                    style="animation-delay:160ms">
                โครงการในอนาคต <span class="opacity-60">({{ $totalFutureProject }})</span>
            </button>
            @endif
        </div>
    </div>
</div>

{{-- ── Property Grid ────────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5 mt-3">
    @foreach($cardRows as $row)
    <div x-data="{ copied: false }"
         x-show="matchRow('{{ $row['statusSlug'] }}', @js($row['searchText']))"
         class="ads-card-in group bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden transition-all duration-300 hover:shadow-xl hover:-translate-y-1"
         style="animation-delay: {{ min($loop->index, 11) * 40 }}ms">

        {{-- Thumbnail --}}
        <div class="w-full h-40 bg-gray-100 relative overflow-hidden">
            @if($row['imageUrl'])
            <img src="{{ $row['imageUrl'] }}" alt="{{ $row['title'] }}"
                 class="w-full h-full object-cover transition-transform duration-500 ease-out group-hover:scale-110"
                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
            @endif
            <div class="w-full h-full items-center justify-center absolute inset-0 bg-gradient-to-br from-gray-50 to-gray-100 {{ $row['imageUrl'] ? 'hidden' : 'flex' }}">
                <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
            </div>
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/20 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
            <span class="absolute top-2 left-2 inline-flex items-center gap-1 text-[10px] font-semibold {{ $badgeClasses[$row['statusColor']] }} border px-2 py-0.5 rounded-full backdrop-blur-sm shadow-sm">
                <span class="w-1.5 h-1.5 rounded-full {{ $dotClasses[$row['statusColor']] }}"></span>{{ $row['statusLabel'] }}
            </span>
            @if(!$row['isPublished'])
            <span class="absolute top-2 right-2 inline-flex items-center text-[10px] font-semibold text-gray-500 bg-white/90 border border-gray-200 px-2 py-0.5 rounded-full backdrop-blur-sm">
                รอเผยแพร่
            </span>
            @endif
        </div>

        <div class="p-3.5">
            @if($row['code'])
            <p class="font-mono font-bold text-xs text-gray-500 truncate leading-snug">{{ $row['code'] }}</p>
            @endif
            <p class="text-sm font-bold text-gray-800 leading-snug mt-0.5 line-clamp-2 min-h-[2.5em]">{{ $row['title'] ?? '—' }}</p>

            @if($row['district'] || $row['province'])
            <p class="flex items-center gap-1 text-xs text-gray-400 truncate mt-1.5">
                <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span class="truncate">{{ trim(($row['district'] ?? '') . ($row['district'] && $row['province'] ? ', ' : '') . ($row['province'] ?? '')) }}</span>
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
                        @click="copyAdLink('{{ $row['adLink'] }}'); copied = true; setTimeout(() => copied = false, 1600)"
                        :class="copied ? 'ads-copied-pop bg-green-700' : 'bg-green-600 hover:bg-green-700'"
                        class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg text-white text-xs font-semibold px-3 py-2 transition-all duration-150 active:scale-95">
                    <template x-if="!copied">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                    </template>
                    <template x-if="copied">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </template>
                    <span x-text="copied ? 'คัดลอกลิงก์แล้ว' : 'คัดลอกลิงก์แอด'"></span>
                </button>
                @else
                <span class="w-full inline-flex items-center justify-center gap-1.5 text-xs font-medium text-gray-400 bg-gray-50 border border-gray-100 rounded-lg px-3 py-2"
                      title="ต้องรอเผยแพร่ก่อนจึงจะยิงแอดได้">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
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
