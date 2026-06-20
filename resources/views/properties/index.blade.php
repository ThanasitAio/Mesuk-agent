@extends('layouts.app')

@section('title', 'อสังหาริมทรัพย์')
@section('breadcrumb', 'รายการทรัพย์สินในความรับผิดชอบ')

@section('content')

@php
    $happyestPublic = rtrim(env('HAPPYEST_APP_URL', 'http://127.0.0.1/happyest/public'), '/');
    $totalAll  = $withContract->count() + $withoutContract->count();
    $totalRent = $withContract->sum(fn($p) => (float) ($p->activeBooking?->monthly_rent ?? 0));
@endphp

<div x-data="{
    search: '',
    filter: 'all',
    matchCard(type, text) {
        if (this.filter !== 'all' && this.filter !== type) return false;
        if (!this.search.trim()) return true;
        return text.toLowerCase().includes(this.search.toLowerCase().trim());
    }
}">

{{-- ===== Summary Stats ===== --}}
<div class="grid grid-cols-3 gap-3 mb-5">
    <x-card class="p-4 flex flex-col gap-1">
        <p class="text-2xl font-bold text-gray-800 tabular-nums leading-none">{{ $totalAll }}</p>
        <p class="text-xs text-gray-400 font-medium">ทรัพย์ทั้งหมด</p>
    </x-card>
    <x-card class="p-4 flex flex-col gap-1">
        <p class="text-2xl font-bold text-emerald-600 tabular-nums leading-none">{{ $withContract->count() }}</p>
        <p class="text-xs text-emerald-500 font-medium">มีผู้เช่า</p>
    </x-card>
    <x-card class="p-4 flex flex-col gap-1">
        <p class="text-2xl font-bold text-amber-500 tabular-nums leading-none">{{ $withoutContract->count() }}</p>
        <p class="text-xs text-amber-500 font-medium">ว่างอยู่</p>
    </x-card>
</div>

@if($totalRent > 0)
<x-card class="flex items-center justify-between px-4 py-3 mb-5">
    <p class="text-sm text-gray-500">รายรับค่าเช่า/เดือน (รวม)</p>
    <p class="text-base font-bold text-gray-800 tabular-nums">{{ number_format($totalRent, 0) }} <span class="text-xs font-normal text-gray-400">฿</span></p>
</x-card>
@endif

{{-- ===== Search + Filter ===== --}}
@if($totalAll > 0)
<div class="flex flex-col sm:flex-row gap-2.5 mb-5">

    {{-- Search input --}}
    <div class="relative flex-1 border border-gray-300 rounded-xl bg-white transition-all focus-within:ring-2 focus-within:ring-brand-500/20 focus-within:border-brand-500">
        <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <input type="text"
               x-model="search"
               placeholder="ค้นหาชื่อทรัพย์ รหัส หรือชื่อลูกค้า..."
               class="w-full pl-11 pr-9 py-2.5 text-sm bg-transparent focus:outline-none text-gray-800 placeholder-gray-400">
        <button x-show="search" x-cloak @click="search = ''"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-300 hover:text-gray-500 transition-colors focus:outline-none"
                tabindex="-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    {{-- Filter tabs --}}
    <div class="flex gap-1 bg-gray-100 rounded-xl p-1 flex-shrink-0 self-start sm:self-auto">
        <button @click="filter = 'all'"
                :class="filter === 'all' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                class="px-3.5 py-2 text-xs font-semibold rounded-lg transition-all whitespace-nowrap">ทั้งหมด</button>
        <button @click="filter = 'active'"
                :class="filter === 'active' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                class="px-3.5 py-2 text-xs font-semibold rounded-lg transition-all whitespace-nowrap">มีผู้เช่า</button>
        <button @click="filter = 'vacant'"
                :class="filter === 'vacant' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                class="px-3.5 py-2 text-xs font-semibold rounded-lg transition-all whitespace-nowrap">ว่าง</button>
    </div>
</div>
@endif

{{-- ===== Active Tenants Section ===== --}}
@if($withContract->count() > 0)
<div x-show="filter === 'all' || filter === 'active'" class="mb-8">

    <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse inline-block"></span>
            <h2 class="text-sm font-bold text-gray-700">กำลังเช่า / มีการจอง</h2>
        </div>
        <span class="text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-1 rounded-full">{{ $withContract->count() }} รายการ</span>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        @foreach($withContract as $property)
        @php
            $booking       = $property->activeBooking;
            $tenant        = $booking?->customer;
            $bookingStatus = $booking?->status ?? 'pending';

            $primaryMedia  = $property->primaryImageMedia;
            $propertyCoverUrl = $primaryMedia?->file_path
                ? ($happyestPublic . '/storage/' . $primaryMedia->file_path)
                : null;

            // Customer photo — same logic as show.blade.php
            $tenantPhotoUrl = null;
            if ($tenant?->photo) {
                $p = $tenant->photo;
                $tenantPhotoUrl = str_starts_with($p, 'http') ? $p : $happyestPublic . '/storage/' . $p;
            } elseif ($tenant?->avatar && $tenant?->provider_id) {
                $av = $tenant->avatar;
                $tenantPhotoUrl = str_starts_with($av, 'http') ? $av : $happyestPublic . '/storage/' . $av;
            }
            $tenantInitial = $tenant ? mb_strtoupper(mb_substr($tenant->full_name ?? '?', 0, 1)) : '?';
            $checkIn = $booking?->check_in;

            // Search text includes tenant name + mobile
            $searchText = strtolower(
                ($property->title ?? '') . ' ' .
                ($property->property_code ?? '') . ' ' .
                ($tenant?->full_name ?? '') . ' ' .
                ($tenant?->mobile ?? '')
            );
        @endphp

        <div x-show="matchCard('active', @js($searchText))">
            <a href="{{ route('properties.show', $property->id) }}" class="block group">
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden transition-all duration-150 group-hover:shadow-md group-hover:-translate-y-0.5 group-active:scale-[0.99]">

                    {{-- Status strip --}}
                    @if($bookingStatus === 'checked_in' || $bookingStatus === 'confirmed')
                        <div class="h-1 bg-emerald-400 w-full"></div>
                    @elseif($bookingStatus === 'deposit_confirmed')
                        <div class="h-1 bg-blue-400 w-full"></div>
                    @elseif($bookingStatus === 'pending')
                        <div class="h-1 bg-amber-400 w-full"></div>
                    @else
                        <div class="h-1 bg-gray-200 w-full"></div>
                    @endif

                    <div class="p-4">
                        {{-- Customer Profile Row --}}
                        <div class="flex items-center gap-3 mb-4">
                            {{-- Avatar --}}
                            @if($tenantPhotoUrl)
                                <img src="{{ $tenantPhotoUrl }}"
                                     alt="{{ $tenant->full_name }}"
                                     class="w-12 h-12 rounded-full object-cover flex-shrink-0 ring-2 ring-gray-100">
                            @else
                                <div class="w-12 h-12 rounded-full bg-brand-600 flex items-center justify-center flex-shrink-0 ring-2 ring-brand-100">
                                    <span class="text-white text-lg font-bold leading-none">{{ $tenantInitial }}</span>
                                </div>
                            @endif

                            {{-- Name + status --}}
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-bold text-gray-900 truncate group-hover:text-brand-600 transition-colors">
                                    {{ $tenant?->full_name ?? '(ไม่ระบุ)' }}
                                </p>
                                @if($tenant?->mobile)
                                    <p class="text-xs text-gray-400 mt-0.5">{{ $tenant->mobile }}</p>
                                @endif
                            </div>

                            {{-- Status badge --}}
                            @if($bookingStatus === 'checked_in')
                                <span class="flex-shrink-0 text-[11px] font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full">เช่าอยู่</span>
                            @elseif($bookingStatus === 'confirmed')
                                <span class="flex-shrink-0 text-[11px] font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full">ยืนยันแล้ว</span>
                            @elseif($bookingStatus === 'deposit_confirmed')
                                <span class="flex-shrink-0 text-[11px] font-semibold text-blue-700 bg-blue-50 border border-blue-200 px-2 py-0.5 rounded-full">ยืนยันมัดจำ</span>
                            @elseif($bookingStatus === 'pending')
                                <span class="flex-shrink-0 text-[11px] font-semibold text-amber-700 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded-full">จองแล้ว</span>
                            @else
                                <span class="flex-shrink-0 text-[11px] font-semibold text-gray-600 bg-gray-50 border border-gray-200 px-2 py-0.5 rounded-full">มีสัญญา</span>
                            @endif
                        </div>

                        {{-- Property name --}}
                        <div class="mb-3">
                            <p class="text-xs text-gray-400 mb-0.5 font-medium">ทรัพย์สิน</p>
                            <p class="text-sm font-semibold text-gray-700 line-clamp-1">{{ $property->title ?? '—' }}</p>
                            @if($property->property_code)
                                <p class="text-[11px] text-gray-400 font-mono mt-0.5">{{ $property->property_code }}</p>
                            @endif
                        </div>

                        {{-- Rent + Check-in --}}
                        <div class="flex items-end justify-between">
                            <div>
                                <p class="text-[10px] text-gray-400 font-medium mb-0.5">ค่าเช่า/เดือน</p>
                                <p class="text-lg font-bold text-gray-900 tabular-nums leading-none">
                                    {{ $booking ? number_format($booking->monthly_rent, 0) : '—' }}<span class="text-xs font-normal text-gray-400 ml-0.5">฿</span>
                                </p>
                            </div>
                            @if($checkIn)
                            <div class="text-right">
                                <p class="text-[10px] text-gray-400 font-medium mb-0.5">เริ่มเช่า</p>
                                <p class="text-xs font-semibold text-gray-600">{{ $checkIn->locale('th')->translatedFormat('j M Y') }}</p>
                            </div>
                            @endif
                        </div>

                        {{-- CTA --}}
                        <div class="mt-3 flex items-center justify-center gap-1.5 text-xs font-bold text-brand-600 bg-brand-50 group-hover:bg-brand-600 group-hover:text-white py-2.5 rounded-lg transition-colors border border-brand-100 group-hover:border-brand-600">
                            ดูรอบบิลและสลิป
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                            </svg>
                        </div>
                    </div>

                </div>
            </a>
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- ===== Vacant Properties Section ===== --}}
@if($withoutContract->count() > 0)
<div x-show="filter === 'all' || filter === 'vacant'" class="mb-6">

    <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-gray-300 inline-block"></span>
            <h2 class="text-sm font-bold text-gray-500">ว่าง / ยังไม่มีผู้เช่า</h2>
        </div>
        <span class="text-xs font-medium text-gray-500 bg-gray-100 border border-gray-200 px-2.5 py-1 rounded-full">{{ $withoutContract->count() }} รายการ</span>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        @foreach($withoutContract as $property)
        @php
            $searchText = strtolower(($property->title ?? '') . ' ' . ($property->property_code ?? ''));
            $vacantPrimaryMedia = $property->primaryImageMedia;
            $vacantCoverUrl = $vacantPrimaryMedia?->file_path
                ? ($happyestPublic . '/storage/' . $vacantPrimaryMedia->file_path)
                : null;
        @endphp
        <div x-show="matchCard('vacant', @js($searchText))">
            <div class="bg-white rounded-xl border border-dashed border-gray-200 overflow-hidden hover:border-gray-300 hover:shadow-sm transition-all">
                <div class="p-4">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-600 line-clamp-2 leading-snug">{{ $property->title ?? '—' }}</p>
                                @if($property->property_code)
                                    <p class="text-[11px] text-gray-400 font-mono mt-0.5">{{ $property->property_code }}</p>
                                @endif
                            </div>
                            <span class="flex-shrink-0 text-[11px] font-semibold text-amber-600 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded-full whitespace-nowrap">ว่าง</span>
                        </div>
                        @if($property->address || $property->price_per_month)
                        <div class="mt-2 space-y-1">
                            @if($property->address)
                                <p class="text-xs text-gray-400 line-clamp-1">{{ $property->address }}</p>
                            @endif
                            @if($property->price_per_month)
                                <p class="text-sm font-bold text-gray-500 tabular-nums">
                                    {{ number_format($property->price_per_month, 0) }}<span class="text-xs font-normal text-gray-400 ml-0.5">฿/เดือน</span>
                                </p>
                            @endif
                        </div>
                        @endif
                    </div>
                </div>
                </div>{{-- /p-4 --}}
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- ===== Empty State ===== --}}
@if($totalAll === 0)
<div class="py-24 text-center">
    <div class="w-16 h-16 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
        </svg>
    </div>
    <p class="text-gray-800 font-bold">ยังไม่มีทรัพย์สินที่ดูแล</p>
    <p class="text-sm text-gray-400 mt-1.5 max-w-xs mx-auto leading-relaxed">ระบบจะแสดงรายการเมื่อมีการมอบหมายอสังหาริมทรัพย์ให้คุณ</p>
</div>
@endif

</div>{{-- /x-data --}}

@endsection
