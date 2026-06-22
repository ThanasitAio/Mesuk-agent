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
    matchRow(type, text) {
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
<div class="flex flex-col sm:flex-row gap-2.5 mb-4">

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

{{-- ===== Unified Table ===== --}}
<x-card class="overflow-hidden p-0">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50/80">
                    <th class="text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wide px-4 py-3 w-5/12">ทรัพย์สิน</th>
                    <th class="text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wide px-4 py-3">ผู้เช่า</th>
                    <th class="text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wide px-4 py-3 hidden sm:table-cell">สถานะ</th>
                    <th class="text-right text-[11px] font-semibold text-gray-400 uppercase tracking-wide px-4 py-3 hidden md:table-cell">ค่าเช่า/เดือน</th>
                    <th class="text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wide px-4 py-3 hidden lg:table-cell">เริ่มเช่า</th>
                    <th class="px-4 py-3 w-10"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">

                {{-- ---- Active / With Contract rows ---- --}}
                @foreach($withContract as $property)
                @php
                    $booking       = $property->activeBooking;
                    $tenant        = $booking?->customer;
                    $bookingStatus = $booking?->status ?? 'pending';

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

                    $searchText = strtolower(
                        ($property->title ?? '') . ' ' .
                        ($property->property_code ?? '') . ' ' .
                        ($tenant?->full_name ?? '') . ' ' .
                        ($tenant?->mobile ?? '')
                    );
                @endphp
                <tr x-show="matchRow('active', @js($searchText))"
                    class="hover:bg-gray-50 transition-colors cursor-pointer group"
                    onclick="window.location='{{ route('properties.show', $property->id) }}'">

                    {{-- Property --}}
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2.5">
                            <div class="w-1.5 h-8 rounded-full flex-shrink-0
                                @if($bookingStatus === 'checked_in' || $bookingStatus === 'confirmed') bg-emerald-400
                                @elseif($bookingStatus === 'deposit_confirmed') bg-blue-400
                                @elseif($bookingStatus === 'pending') bg-amber-400
                                @else bg-gray-200 @endif">
                            </div>
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-800 truncate group-hover:text-brand-600 transition-colors leading-snug">{{ $property->title ?? '—' }}</p>
                                @if($property->property_code)
                                    <p class="text-[11px] text-gray-400 font-mono mt-0.5">{{ $property->property_code }}</p>
                                @endif
                            </div>
                        </div>
                    </td>

                    {{-- Tenant --}}
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            @if($tenantPhotoUrl)
                                <img src="{{ $tenantPhotoUrl }}"
                                     alt="{{ $tenant->full_name }}"
                                     class="w-8 h-8 rounded-full object-cover flex-shrink-0 ring-1 ring-gray-200">
                            @else
                                <div class="w-8 h-8 rounded-full bg-brand-600 flex items-center justify-center flex-shrink-0">
                                    <span class="text-white text-xs font-bold leading-none">{{ $tenantInitial }}</span>
                                </div>
                            @endif
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-700 truncate">{{ $tenant?->full_name ?? '(ไม่ระบุ)' }}</p>
                                @if($tenant?->mobile)
                                    <p class="text-[11px] text-gray-400">{{ $tenant->mobile }}</p>
                                @endif
                            </div>
                        </div>
                    </td>

                    {{-- Status --}}
                    <td class="px-4 py-3 hidden sm:table-cell">
                        @if($bookingStatus === 'checked_in')
                            <span class="text-[11px] font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full whitespace-nowrap">เช่าอยู่</span>
                        @elseif($bookingStatus === 'confirmed')
                            <span class="text-[11px] font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full whitespace-nowrap">ยืนยันแล้ว</span>
                        @elseif($bookingStatus === 'deposit_confirmed')
                            <span class="text-[11px] font-semibold text-blue-700 bg-blue-50 border border-blue-200 px-2 py-0.5 rounded-full whitespace-nowrap">ยืนยันมัดจำ</span>
                        @elseif($bookingStatus === 'pending')
                            <span class="text-[11px] font-semibold text-amber-700 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded-full whitespace-nowrap">จองแล้ว</span>
                        @else
                            <span class="text-[11px] font-semibold text-gray-600 bg-gray-50 border border-gray-200 px-2 py-0.5 rounded-full whitespace-nowrap">มีสัญญา</span>
                        @endif
                    </td>

                    {{-- Rent --}}
                    <td class="px-4 py-3 text-right tabular-nums hidden md:table-cell">
                        @if($booking)
                            <span class="font-bold text-gray-800">{{ number_format($booking->monthly_rent, 0) }}</span>
                            <span class="text-xs text-gray-400">฿</span>
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>

                    {{-- Check-in date --}}
                    <td class="px-4 py-3 text-xs text-gray-500 hidden lg:table-cell whitespace-nowrap">
                        {{ $checkIn ? $checkIn->locale('th')->translatedFormat('j M Y') : '—' }}
                    </td>

                    {{-- Action --}}
                    <td class="px-4 py-3">
                        <svg class="w-4 h-4 text-gray-300 group-hover:text-brand-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                        </svg>
                    </td>
                </tr>
                @endforeach

                {{-- ---- Vacant / No Contract rows ---- --}}
                @foreach($withoutContract as $property)
                @php
                    $searchText = strtolower(($property->title ?? '') . ' ' . ($property->property_code ?? ''));
                @endphp
                <tr x-show="matchRow('vacant', @js($searchText))"
                    class="hover:bg-gray-50/60 transition-colors opacity-75">

                    {{-- Property --}}
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2.5">
                            <div class="w-1.5 h-8 rounded-full bg-gray-200 flex-shrink-0"></div>
                            <div class="min-w-0">
                                <p class="font-medium text-gray-600 truncate leading-snug">{{ $property->title ?? '—' }}</p>
                                @if($property->property_code)
                                    <p class="text-[11px] text-gray-400 font-mono mt-0.5">{{ $property->property_code }}</p>
                                @endif
                            </div>
                        </div>
                    </td>

                    {{-- Tenant --}}
                    <td class="px-4 py-3 text-sm text-gray-400">—</td>

                    {{-- Status --}}
                    <td class="px-4 py-3 hidden sm:table-cell">
                        <span class="text-[11px] font-semibold text-amber-600 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded-full">ว่าง</span>
                    </td>

                    {{-- Rent --}}
                    <td class="px-4 py-3 text-right tabular-nums hidden md:table-cell">
                        @if($property->price_per_month)
                            <span class="text-gray-500">{{ number_format($property->price_per_month, 0) }}</span>
                            <span class="text-xs text-gray-400">฿</span>
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>

                    {{-- Check-in date --}}
                    <td class="px-4 py-3 hidden lg:table-cell text-gray-400">—</td>

                    {{-- Action --}}
                    <td class="px-4 py-3"></td>
                </tr>
                @endforeach

            </tbody>
        </table>
    </div>

</x-card>
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
