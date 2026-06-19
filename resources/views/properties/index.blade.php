@extends('layouts.app')

@section('title', 'อสังหาริมทรัพย์')
@section('breadcrumb', 'รายการทรัพย์สินในความรับผิดชอบ')

@section('content')

<div x-data="{
    search: '',
    filter: 'all',
    matchCard(type, text) {
        if (this.filter !== 'all' && this.filter !== type) return false;
        if (!this.search.trim()) return true;
        return text.toLowerCase().includes(this.search.toLowerCase().trim());
    }
}">

{{-- Search + Filter Bar --}}
@if($withContract->count() + $withoutContract->count() > 0)
<div class="flex flex-col sm:flex-row gap-3 mb-6">
    <div class="relative flex-1">
        <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <input type="text"
               x-model="search"
               placeholder="ค้นหาชื่อ หรือรหัสทรัพย์..."
               class="w-full pl-11 pr-10 py-3 text-sm bg-white border border-gray-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 placeholder-gray-400 shadow-sm">
        <button x-show="search" x-cloak @click="search = ''"
                class="absolute right-3.5 top-1/2 -translate-y-1/2 w-6 h-6 flex items-center justify-center rounded-full text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
    <div class="flex gap-1 bg-gray-100 rounded-2xl p-1 self-start sm:self-auto flex-shrink-0">
        <button @click="filter = 'all'"
                :class="filter === 'all' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                class="px-4 py-2 text-xs font-semibold rounded-xl transition-all whitespace-nowrap">ทั้งหมด</button>
        <button @click="filter = 'active'"
                :class="filter === 'active' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                class="px-4 py-2 text-xs font-semibold rounded-xl transition-all whitespace-nowrap">มีการจอง</button>
        <button @click="filter = 'vacant'"
                :class="filter === 'vacant' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                class="px-4 py-2 text-xs font-semibold rounded-xl transition-all whitespace-nowrap">ว่าง</button>
    </div>
</div>
@endif

{{-- Summary Stats --}}
<div class="grid grid-cols-3 gap-3 mb-7">
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <div class="w-9 h-9 bg-slate-100 rounded-xl flex items-center justify-center mb-3">
            <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
        </div>
        <p class="text-2xl font-bold text-gray-900 leading-none tabular-nums">{{ $withContract->count() + $withoutContract->count() }}</p>
        <p class="text-xs text-gray-500 mt-1.5 font-medium">ทรัพย์ทั้งหมด</p>
    </div>
    <div class="bg-white rounded-2xl border border-emerald-100 shadow-sm p-4">
        <div class="w-9 h-9 bg-emerald-100 rounded-xl flex items-center justify-center mb-3">
            <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <p class="text-2xl font-bold text-emerald-700 leading-none tabular-nums">{{ $withContract->count() }}</p>
        <p class="text-xs text-emerald-600 mt-1.5 font-medium">มีผู้เช่า</p>
    </div>
    <div class="bg-white rounded-2xl border border-amber-100 shadow-sm p-4">
        <div class="w-9 h-9 bg-amber-100 rounded-xl flex items-center justify-center mb-3">
            <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <p class="text-2xl font-bold text-amber-700 leading-none tabular-nums">{{ $withoutContract->count() }}</p>
        <p class="text-xs text-amber-600 mt-1.5 font-medium">ว่างรอผู้เช่า</p>
    </div>
</div>

{{-- Active Contract Properties --}}
@if($withContract->count() > 0)
<div x-show="filter === 'all' || filter === 'active'" class="mb-8">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            <h2 class="text-sm font-bold text-gray-800">มีการจอง / กำลังเช่า</h2>
        </div>
        <span class="text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-1 rounded-full">
            {{ $withContract->count() }} รายการ
        </span>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($withContract as $property)
            @php
                $booking = $property->activeBooking;
                $tenant  = $booking?->customer;
                $bookingStatus = $booking?->status ?? 'pending';
                $statusBadge = match($bookingStatus) {
                    'pending'           => ['label' => 'จองแล้ว',     'class' => 'text-amber-700 bg-amber-50 border-amber-200',        'dot' => 'bg-amber-400'],
                    'deposit_confirmed' => ['label' => 'ยืนยันมัดจำ', 'class' => 'text-blue-700 bg-blue-50 border-blue-200',          'dot' => 'bg-blue-400'],
                    'confirmed'         => ['label' => 'ยืนยันแล้ว',  'class' => 'text-emerald-700 bg-emerald-50 border-emerald-200', 'dot' => 'bg-emerald-500'],
                    'checked_in'        => ['label' => 'เช่าอยู่',    'class' => 'text-emerald-700 bg-emerald-50 border-emerald-200', 'dot' => 'bg-emerald-500'],
                    default             => ['label' => 'มีสัญญา',     'class' => 'text-emerald-700 bg-emerald-50 border-emerald-200', 'dot' => 'bg-emerald-500'],
                };
                $leftBorder = match($bookingStatus) {
                    'pending'           => 'border-l-amber-400',
                    'deposit_confirmed' => 'border-l-blue-400',
                    default             => 'border-l-emerald-500',
                };
                $searchText    = strtolower(($property->title ?? '') . ' ' . ($property->property_code ?? ''));
                $tenantInitial = $tenant ? mb_substr($tenant->full_name ?? '?', 0, 1) : null;
            @endphp
            <div x-show="matchCard('active', @js($searchText))">
                <a href="{{ route('properties.show', $property->id) }}" class="block group">
                    <div class="bg-white rounded-2xl border border-gray-100 border-l-4 {{ $leftBorder }} shadow-sm overflow-hidden transition-all duration-200 group-hover:shadow-lg group-hover:-translate-y-0.5 group-active:scale-[0.99]">
                        <div class="p-4">

                            {{-- Title & Status --}}
                            <div class="flex items-start justify-between gap-2 mb-3">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-bold text-gray-900 leading-snug line-clamp-2 group-hover:text-brand-600 transition-colors">
                                        {{ $property->title ?? '—' }}
                                    </p>
                                    @if($property->property_code)
                                        <p class="text-[11px] text-gray-400 font-mono mt-0.5">{{ $property->property_code }}</p>
                                    @endif
                                </div>
                                <span class="flex-shrink-0 inline-flex items-center gap-1.5 text-[11px] font-semibold {{ $statusBadge['class'] }} border px-2 py-1 rounded-full whitespace-nowrap">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $statusBadge['dot'] }} {{ in_array($bookingStatus, ['checked_in', 'confirmed']) ? 'animate-pulse' : '' }}"></span>
                                    {{ $statusBadge['label'] }}
                                </span>
                            </div>

                            {{-- Tenant --}}
                            @if($tenant)
                            <div class="flex items-center gap-2.5 bg-gray-50 rounded-xl px-3 py-2.5 mb-3">
                                <div class="w-8 h-8 rounded-full bg-brand-600 flex items-center justify-center flex-shrink-0 text-white text-xs font-bold">
                                    {{ $tenantInitial }}
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[10px] text-gray-400 leading-none mb-0.5 font-medium">ผู้เช่า</p>
                                    <p class="text-sm font-semibold text-gray-800 truncate">{{ $tenant->full_name }}</p>
                                </div>
                            </div>
                            @endif

                            {{-- Rent + CTA --}}
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-[10px] text-gray-400 leading-none mb-0.5 font-medium">ค่าเช่า/เดือน</p>
                                    <p class="text-lg font-bold text-gray-900 leading-none tabular-nums">
                                        {{ $booking ? number_format($booking->monthly_rent, 0) : '—' }}<span class="text-xs font-normal text-gray-400 ml-1">฿</span>
                                    </p>
                                </div>
                                <div class="inline-flex items-center gap-1.5 text-xs font-bold text-brand-600 bg-brand-50 group-hover:bg-brand-600 group-hover:text-white px-3 py-2 rounded-xl transition-colors">
                                    ดูบิล
                                    <svg class="w-3.5 h-3.5 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </div>
                            </div>

                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
</div>
@endif

{{-- Vacant Properties --}}
@if($withoutContract->count() > 0)
<div x-show="filter === 'all' || filter === 'vacant'" class="mb-6">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-gray-400"></span>
            <h2 class="text-sm font-bold text-gray-600">ว่าง / ไม่มีสัญญา</h2>
        </div>
        <span class="text-xs font-medium text-gray-500 bg-gray-100 border border-gray-200 px-2.5 py-1 rounded-full">
            {{ $withoutContract->count() }} รายการ
        </span>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($withoutContract as $property)
            @php
                $searchText = strtolower(($property->title ?? '') . ' ' . ($property->property_code ?? ''));
            @endphp
            <div x-show="matchCard('vacant', @js($searchText))">
                <div class="bg-white rounded-2xl border-2 border-dashed border-gray-200 p-4 transition-colors hover:border-gray-300">
                    <div class="flex items-start justify-between gap-2 mb-1.5">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-gray-600 leading-snug line-clamp-2">
                                {{ $property->title ?? '—' }}
                            </p>
                            @if($property->property_code)
                                <p class="text-[11px] text-gray-400 font-mono mt-0.5">{{ $property->property_code }}</p>
                            @endif
                        </div>
                        <span class="flex-shrink-0 inline-flex items-center gap-1.5 text-[11px] font-semibold text-amber-600 bg-amber-50 border border-amber-200 px-2 py-1 rounded-full whitespace-nowrap">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                            ว่าง
                        </span>
                    </div>

                    @if($property->address || $property->price_per_month)
                        <div class="mt-3 pt-3 border-t border-gray-100 space-y-1.5">
                            @if($property->address)
                                <div class="flex items-start gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-gray-300 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    <p class="text-xs text-gray-400 line-clamp-1">{{ $property->address }}</p>
                                </div>
                            @endif
                            @if($property->price_per_month)
                                <p class="text-sm font-bold text-gray-700 tabular-nums">
                                    {{ number_format($property->price_per_month, 0) }}<span class="text-xs font-normal text-gray-400 ml-1">฿/เดือน</span>
                                </p>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
@endif

{{-- Empty State --}}
@if($withContract->count() === 0 && $withoutContract->count() === 0)
<div class="py-24 text-center">
    <div class="w-20 h-20 bg-gray-100 rounded-3xl flex items-center justify-center mx-auto mb-5">
        <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
        </svg>
    </div>
    <p class="text-gray-800 font-bold text-base">ยังไม่มีทรัพย์สินที่ดูแล</p>
    <p class="text-sm text-gray-400 mt-2 max-w-xs mx-auto leading-relaxed">ระบบจะแสดงรายการเมื่อมีการมอบหมายอสังหาริมทรัพย์ให้คุณ</p>
</div>
@endif

</div>{{-- end x-data --}}

@endsection
