@extends('layouts.app')

@section('title', 'อสังหาริมทรัพย์')
@section('breadcrumb', 'รายการทรัพย์สินในความรับผิดชอบ')

@push('styles')
<style>
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(6px); }
    to   { opacity: 1; transform: translateY(0); }
}
.property-row {
    animation: fadeIn 0.25s ease-out;
    transition: background-color 0.15s;
}
@keyframes shimmer {
    0%   { background-position: -1000px 0; }
    100% { background-position:  1000px 0; }
}
@media (min-width: 1024px) {
    .overflow-x-auto::-webkit-scrollbar { height: 6px; }
    .overflow-x-auto::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
    .overflow-x-auto::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }
}
</style>
@endpush

@section('content')

@php
    $happyestPublic  = rtrim(env('HAPPYEST_APP_URL', 'http://127.0.0.1/happyest/public'), '/');
    $totalAll        = $withContract->count() + $withoutContract->count();
    $totalRent       = $withContract->sum(fn($p) => (float) ($p->activeBooking?->monthly_rent ?? 0));

    $today = now()->startOfDay();

    // เป็น "รอแนบสลิป" เฉพาะรายการที่ครบกำหนดชำระแล้ว (due_date <= วันนี้) ไม่ใช่ทุกรายการค้างจ่าย
    $duePendingRecords = fn($recs) => $recs->whereIn('payment_status', ['pending', 'failed'])
        ->filter(fn($r) => ! $r->due_date || $r->due_date->lte($today));

    // แสดงรอแนบสลิปได้ก็ต่อเมื่ออสังหาสถานะไม่ว่าง (property_status_id ไม่ใช่ available)
    $isPropertyVacant = fn($p) => optional($p->propertyStatus)->slug === 'available';

    $totalSlipNeeded = $withContract->filter(function ($p) use ($isPropertyVacant, $duePendingRecords) {
        $booking = $p->activeBooking;
        if (! $booking || $booking->status === 'deposit_confirmed' || $isPropertyVacant($p)) {
            return false;
        }

        return $duePendingRecords($booking->paymentRecords ?? collect())->isNotEmpty();
    })->count();

    $totalSlipVerify = $withContract->filter(function ($p) use ($duePendingRecords) {
        $recs = $p->activeBooking?->paymentRecords ?? collect();

        return $recs->where('payment_status', 'pending_verification')->isNotEmpty()
            && $duePendingRecords($recs)->isEmpty();
    })->count();
@endphp

<div x-data="{
    search: '',
    filter: 'all',
    matchRow(type, text, slipNeeded, slipVerifying) {
        if (this.filter === 'slip_needed') return type === 'active' && slipNeeded;
        if (this.filter === 'slip_verify') return type === 'active' && slipVerifying;
        if (this.filter !== 'all' && this.filter !== type) return false;
        if (!this.search.trim()) return true;
        return text.toLowerCase().includes(this.search.toLowerCase().trim());
    }
}">

{{-- ===== Summary Stats ===== --}}
<div class="grid grid-cols-3 gap-3 mb-5">
    <x-card class="p-4 flex flex-col gap-1 transition-all duration-300 hover:shadow-lg hover:-translate-y-0.5">
        <p class="text-2xl font-bold text-gray-800 tabular-nums leading-none">{{ $totalAll }}</p>
        <p class="text-xs text-gray-400 font-medium">ทรัพย์ทั้งหมด</p>
    </x-card>
    <x-card class="p-4 flex flex-col gap-1 transition-all duration-300 hover:shadow-lg hover:-translate-y-0.5">
        <p class="text-2xl font-bold text-red-600 tabular-nums leading-none">{{ $withContract->count() }}</p>
        <p class="text-xs text-red-500 font-medium">ไม่ว่าง</p>
    </x-card>
    <x-card class="p-4 flex flex-col gap-1 transition-all duration-300 hover:shadow-lg hover:-translate-y-0.5">
        <p class="text-2xl font-bold text-green-600 tabular-nums leading-none">{{ $withoutContract->count() }}</p>
        <p class="text-xs text-green-500 font-medium">ว่าง</p>
    </x-card>
</div>

@if($totalRent > 0)
<x-card class="flex items-center justify-between px-4 py-3 mb-5">
    <p class="text-sm text-gray-500">รายรับค่าเช่า/เดือน (รวม)</p>
    <p class="text-base font-bold text-gray-800 tabular-nums">{{ number_format($totalRent, 0) }} <span class="text-xs font-normal text-gray-400">฿</span></p>
</x-card>
@endif

{{-- ===== Slip Alert Banners ===== --}}
@if($totalSlipNeeded > 0)
<button type="button"
        x-on:click="filter = (filter === 'slip_needed' ? 'all' : 'slip_needed')"
        class="w-full flex items-center gap-3 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 mb-3 text-left">
    <div class="w-8 h-8 bg-amber-100 rounded-lg flex items-center justify-center flex-shrink-0">
        <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
        </svg>
    </div>
    <div class="flex-1 min-w-0">
        <p class="text-sm font-semibold text-amber-700">ยังไม่แนบสลิป <span class="font-bold">{{ $totalSlipNeeded }} ห้อง</span></p>
        <p class="text-xs text-amber-500 mt-0.5" x-text="filter === 'slip_needed' ? 'กำลังกรองอยู่ — แตะอีกครั้งเพื่อดูทั้งหมด' : 'แตะเพื่อกรองดูเฉพาะห้องที่ยังไม่แนบสลิป'"></p>
    </div>
    <svg class="w-4 h-4 flex-shrink-0 transition-transform"
         :class="filter === 'slip_needed' ? 'rotate-90 text-amber-600' : 'text-amber-400'"
         fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
</button>
@endif
@if($totalSlipVerify > 0)
<button type="button"
        x-on:click="filter = (filter === 'slip_verify' ? 'all' : 'slip_verify')"
        class="w-full flex items-center gap-3 bg-blue-50 border border-blue-200 rounded-xl px-4 py-3 mb-3 text-left">
    <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
        <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
    </div>
    <div class="flex-1 min-w-0">
        <p class="text-sm font-semibold text-blue-700">แนบสลิปแล้ว รอตรวจสอบ <span class="font-bold">{{ $totalSlipVerify }} ห้อง</span></p>
        <p class="text-xs text-blue-400 mt-0.5" x-text="filter === 'slip_verify' ? 'กำลังกรองอยู่ — แตะอีกครั้งเพื่อดูทั้งหมด' : 'แตะเพื่อกรองดูห้องที่รอแอดมินตรวจสอบสลิป'"></p>
    </div>
    <svg class="w-4 h-4 flex-shrink-0 transition-transform"
         :class="filter === 'slip_verify' ? 'rotate-90 text-blue-600' : 'text-blue-400'"
         fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
</button>
@endif
@if($totalSlipNeeded > 0 || $totalSlipVerify > 0)
<div class="mb-5"></div>
@endif

{{-- ===== Search + Filter ===== --}}
@if($totalAll > 0)
<div class="flex flex-col sm:flex-row gap-2.5 mb-4">
    <div class="relative flex-1 border border-gray-300 rounded-xl bg-white transition-all focus-within:ring-2 focus-within:ring-brand-500/20 focus-within:border-brand-500">
        <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
    <div class="flex gap-1 bg-gray-100 rounded-xl p-1 flex-shrink-0 self-start sm:self-auto overflow-x-auto">
        <button @click="filter = 'all'"
                :class="filter === 'all' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                class="px-3.5 py-2 text-xs font-semibold rounded-lg transition-all whitespace-nowrap">ทั้งหมด</button>
        <button @click="filter = 'active'"
                :class="filter === 'active' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                class="px-3.5 py-2 text-xs font-semibold rounded-lg transition-all whitespace-nowrap">ไม่ว่าง</button>
        <button @click="filter = 'vacant'"
                :class="filter === 'vacant' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                class="px-3.5 py-2 text-xs font-semibold rounded-lg transition-all whitespace-nowrap">ว่าง</button>
        @if($totalSlipNeeded > 0)
        <button @click="filter = 'slip_needed'"
                :class="filter === 'slip_needed' ? 'bg-white text-amber-700 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                class="px-3.5 py-2 text-xs font-semibold rounded-lg transition-all whitespace-nowrap flex items-center gap-1">
            รอสลิป
            <span class="text-[10px] font-bold bg-amber-500 text-white rounded-full w-4 h-4 flex items-center justify-center leading-none">{{ $totalSlipNeeded }}</span>
        </button>
        @endif
        @if($totalSlipVerify > 0)
        <button @click="filter = 'slip_verify'"
                :class="filter === 'slip_verify' ? 'bg-white text-blue-700 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                class="px-3.5 py-2 text-xs font-semibold rounded-lg transition-all whitespace-nowrap flex items-center gap-1">
            แนบแล้ว
            <span class="text-[10px] font-bold bg-blue-500 text-white rounded-full w-4 h-4 flex items-center justify-center leading-none">{{ $totalSlipVerify }}</span>
        </button>
        @endif
    </div>
</div>

{{-- ===== Unified Table ===== --}}
<x-card class="overflow-hidden p-0">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50/80">
                    <th class="text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wide px-4 py-3">ทรัพย์สิน</th>
                    <th class="text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wide px-4 py-3 hidden sm:table-cell">ผู้เช่า</th>
                    <th class="text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wide px-4 py-3 hidden sm:table-cell">สถานะ / การชำระ</th>
                    <th class="text-right text-[11px] font-semibold text-gray-400 uppercase tracking-wide px-4 py-3 hidden md:table-cell">ค่าเช่า/เดือน</th>
                    <th class="text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wide px-4 py-3 hidden lg:table-cell">ข้อมูลสัญญา</th>
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
                        $ph = $tenant->photo;
                        $tenantPhotoUrl = str_starts_with($ph, 'http') ? $ph : $happyestPublic . '/storage/' . $ph;
                    } elseif ($tenant?->avatar && $tenant?->provider_id) {
                        $av = $tenant->avatar;
                        $tenantPhotoUrl = str_starts_with($av, 'http') ? $av : $happyestPublic . '/storage/' . $av;
                    }
                    $tenantInitial = $tenant ? mb_strtoupper(mb_substr($tenant->full_name ?? '?', 0, 1)) : '?';

                    // Dates
                    $checkIn       = $booking?->check_in;
                    $actualMoveIn  = $booking?->actual_move_in_date;
                    $contractStart = $booking?->contract_start_date;
                    $contractEnd   = $booking?->check_out;
                    $rentalMonths  = $booking?->rental_months ?? 0;

                    // Payment records
                    $records           = $booking?->paymentRecords ?? collect();
                    $duePendingRecs    = $duePendingRecords($records);
                    $hasPendingFailed  = $duePendingRecs->isNotEmpty();
                    $hasPendingVerify  = $records->where('payment_status', 'pending_verification')->isNotEmpty();
                    $isVacant          = $isPropertyVacant($property);

                    $slipNeeded        = ! $isVacant && $bookingStatus !== 'deposit_confirmed' && $hasPendingFailed;
                    $slipPendingVerify = $hasPendingVerify && !$hasPendingFailed;
                    $lastSlipAt        = $records->whereNotNull('paid_at')->sortByDesc('paid_at')->first()?->paid_at;

                    // First pending record ที่ครบกำหนดแล้ว + due date
                    $firstPendingRec = $duePendingRecs->first();
                    $pendingDueDate  = $firstPendingRec?->due_date;
                    $dueDatePast = $pendingDueDate && $pendingDueDate->lt(now()->startOfDay());
                    $dueDateSoon = $pendingDueDate && !$dueDatePast && $pendingDueDate->diffInDays(now()->startOfDay()) <= 3;
                    $dueDateClass = $dueDatePast ? 'text-red-500 font-semibold' : ($dueDateSoon ? 'text-amber-500 font-semibold' : 'text-gray-400');

                    // Slip label (เดือนที่ แทน รอบที่)
                    $pendingSlipLabel = null;
                    if ($slipNeeded && $firstPendingRec) {
                        $pendingSlipLabel = match($firstPendingRec->payment_type) {
                            'monthly_rent'   => 'เดือนที่ ' . $firstPendingRec->month_number,
                            'deposit'        => 'มัดจำงวด ' . ($firstPendingRec->deposit_phase ?? 1),
                            'processing_fee' => 'ค่าดำเนินการ',
                            'late_fee'       => 'ค่าปรับเดือน ' . $firstPendingRec->month_number,
                            default          => null,
                        };
                    }

                    // Approved invoices
                    $approvedInvoices = ($booking?->invoices ?? collect())->where('status', 'approved')->values();

                    $searchText = strtolower(
                        ($property->title ?? '') . ' ' .
                        ($property->property_code ?? '') . ' ' .
                        ($tenant?->full_name ?? '') . ' ' .
                        ($tenant?->mobile ?? '')
                    );
                @endphp
                <tr x-show="matchRow('active', @js($searchText), @js($slipNeeded), @js($slipPendingVerify))"
                    class="property-row hover:bg-gray-50 cursor-pointer group"
                    onclick="window.location='{{ route('properties.show', $property->id) }}'"
                    role="button"
                    tabindex="0"
                    @keypress.enter="window.location='{{ route('properties.show', $property->id) }}'">

                    {{-- Col 1: ทรัพย์สิน + mobile stacked info --}}
                    <td class="px-4 py-3">
                        <div class="flex items-start gap-2.5">
                            {{-- Status bar --}}
                            <div class="w-1.5 rounded-full flex-shrink-0 mt-0.5
                                @if($bookingStatus === 'checked_in' || $bookingStatus === 'confirmed') bg-red-500
                                @elseif($bookingStatus === 'deposit_confirmed') bg-blue-400
                                @elseif($bookingStatus === 'pending') bg-yellow-400
                                @else bg-gray-300 @endif"
                                style="height: 1.75rem">
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-gray-800 truncate group-hover:text-brand-600 transition-colors leading-snug">{{ $property->title ?? '—' }}</p>
                                @if($property->property_code)
                                <p class="text-[11px] text-gray-400 font-mono">{{ $property->property_code }}</p>
                                @endif

                                {{-- Mobile-only stacked content (hidden on sm+) --}}
                                <div class="sm:hidden mt-2.5 pt-2.5 border-t border-gray-100 space-y-1.5">

                                    {{-- Tenant --}}
                                    <div class="flex items-center gap-1.5">
                                        @if($tenantPhotoUrl)
                                            <img src="{{ $tenantPhotoUrl }}" alt="{{ $tenant->full_name }}"
                                                 class="w-5 h-5 rounded-full object-cover flex-shrink-0 ring-1 ring-gray-200">
                                        @else
                                            <div class="w-5 h-5 rounded-full bg-brand-600 flex items-center justify-center flex-shrink-0">
                                                <span class="text-white text-[9px] font-bold leading-none">{{ $tenantInitial }}</span>
                                            </div>
                                        @endif
                                        <span class="text-xs text-gray-700 font-medium truncate">{{ $tenant?->full_name ?? '(ไม่ระบุ)' }}</span>
                                        @if($tenant?->mobile)
                                        <span class="text-[10px] text-gray-400 flex-shrink-0">· {{ $tenant->mobile }}</span>
                                        @endif
                                    </div>

                                    {{-- Status badge + Rent --}}
                                    <div class="flex items-center justify-between gap-2 flex-wrap">
                                        @if($bookingStatus === 'checked_in')
                                            <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-red-700 bg-red-50 border border-red-200 px-2 py-0.5 rounded-full">
                                                <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span>ไม่ว่าง
                                            </span>
                                        @elseif($bookingStatus === 'confirmed')
                                            <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-red-700 bg-red-50 border border-red-200 px-2 py-0.5 rounded-full">
                                                <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>ยืนยันแล้ว
                                            </span>
                                        @elseif($bookingStatus === 'deposit_confirmed')
                                            <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-blue-700 bg-blue-50 border border-blue-200 px-2 py-0.5 rounded-full">
                                                <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>โครงการในอนาคต
                                            </span>
                                        @elseif($bookingStatus === 'pending')
                                            <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-yellow-700 bg-yellow-50 border border-yellow-200 px-2 py-0.5 rounded-full">
                                                <span class="w-1.5 h-1.5 rounded-full bg-yellow-500"></span>จองแล้ว
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-gray-600 bg-gray-50 border border-gray-200 px-2 py-0.5 rounded-full">
                                                <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>มีสัญญา
                                            </span>
                                        @endif
                                        @if($booking?->monthly_rent)
                                        <span class="text-xs font-bold text-gray-700 tabular-nums">{{ number_format($booking->monthly_rent, 0) }} <span class="font-normal text-gray-400 text-[10px]">฿/เดือน</span></span>
                                        @endif
                                    </div>

                                    {{-- Slip + Due date --}}
                                    @if($slipNeeded)
                                    <div class="flex items-center gap-1 text-[10px] font-semibold text-amber-600">
                                        <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                        </svg>
                                        รอแนบสลิป@if($pendingSlipLabel) <span class="font-normal text-amber-500">· {{ $pendingSlipLabel }}</span>@endif
                                        @if($pendingDueDate) <span class="{{ $dueDateClass }}">· ครบ {{ $pendingDueDate->locale('th')->translatedFormat('j M') }}</span>@endif
                                    </div>
                                    @elseif($slipPendingVerify)
                                    <div class="flex items-center gap-1 text-[10px] font-semibold text-blue-500">
                                        <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        รอตรวจสอบ@if($lastSlipAt) <span class="font-normal text-blue-400">· {{ $lastSlipAt->locale('th')->translatedFormat('j M') }}</span>@endif
                                    </div>
                                    @elseif($lastSlipAt)
                                    <div class="flex items-center gap-1 text-[10px] text-gray-400">
                                        <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        แนบล่าสุด {{ $lastSlipAt->locale('th')->translatedFormat('j M Y') }}
                                    </div>
                                    @endif

                                    {{-- Compact dates --}}
                                    @php
                                        $displayDate = $actualMoveIn ?? $contractStart ?? $checkIn;
                                    @endphp
                                    @if($displayDate || $contractEnd || $rentalMonths)
                                    <div class="text-[10px] text-gray-400 leading-relaxed">
                                        @if($displayDate)
                                        เข้าอยู่: {{ $displayDate->locale('th')->translatedFormat('j M Y') }}
                                        @endif
                                        @if($contractEnd)
                                        · สิ้นสุด: {{ $contractEnd->locale('th')->translatedFormat('j M Y') }}
                                        @endif
                                        @if($rentalMonths)
                                        · {{ $rentalMonths }} เดือน
                                        @endif
                                    </div>
                                    @endif

                                </div>{{-- /mobile stacked --}}
                            </div>
                        </div>
                    </td>

                    {{-- Col 2: ผู้เช่า (sm+) --}}
                    <td class="px-4 py-3 hidden sm:table-cell">
                        <div class="flex items-center gap-2">
                            @if($tenantPhotoUrl)
                                <img src="{{ $tenantPhotoUrl }}" alt="{{ $tenant->full_name }}"
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

                    {{-- Col 3: สถานะ + การชำระ (sm+) --}}
                    <td class="px-4 py-3 hidden sm:table-cell align-top">
                        {{-- Booking status badge --}}
                        @if($bookingStatus === 'checked_in')
                            <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-red-700 bg-red-50 border border-red-200 px-2 py-0.5 rounded-full whitespace-nowrap">
                                <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span>ไม่ว่าง
                            </span>
                        @elseif($bookingStatus === 'confirmed')
                            <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-red-700 bg-red-50 border border-red-200 px-2 py-0.5 rounded-full whitespace-nowrap">
                                <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>ยืนยันแล้ว
                            </span>
                        @elseif($bookingStatus === 'deposit_confirmed')
                            <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-blue-700 bg-blue-50 border border-blue-200 px-2 py-0.5 rounded-full whitespace-nowrap">
                                <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>โครงการในอนาคต
                            </span>
                        @elseif($bookingStatus === 'pending')
                            <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-yellow-700 bg-yellow-50 border border-yellow-200 px-2 py-0.5 rounded-full whitespace-nowrap">
                                <span class="w-1.5 h-1.5 rounded-full bg-yellow-500"></span>จองแล้ว
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-gray-600 bg-gray-50 border border-gray-200 px-2 py-0.5 rounded-full whitespace-nowrap">
                                <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>มีสัญญา
                            </span>
                        @endif

                        {{-- Slip + due date --}}
                        <div class="mt-1.5 space-y-0.5">
                            @if($slipNeeded)
                                <div class="inline-flex items-center gap-1 text-[10px] font-semibold text-amber-600">
                                    <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                    </svg>
                                    รอแนบสลิป@if($pendingSlipLabel) <span class="font-normal text-amber-500 ml-0.5">· {{ $pendingSlipLabel }}</span>@endif
                                </div>
                                @if($pendingDueDate)
                                <div class="flex items-center gap-1 text-[10px] {{ $dueDateClass }}">
                                    <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    {{ $dueDatePast ? 'เกินกำหนด' : 'ครบกำหนด' }} {{ $pendingDueDate->locale('th')->translatedFormat('j M Y') }}
                                </div>
                                @endif
                            @elseif($slipPendingVerify)
                                <div class="inline-flex items-center gap-1 text-[10px] font-semibold text-blue-500">
                                    <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    รอตรวจสอบ@if($lastSlipAt) <span class="font-normal text-blue-400 ml-0.5">· {{ $lastSlipAt->locale('th')->translatedFormat('j M') }}</span>@endif
                                </div>
                            @elseif($lastSlipAt)
                                <div class="inline-flex items-center gap-1 text-[10px] text-gray-400">
                                    <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    แนบล่าสุด {{ $lastSlipAt->locale('th')->translatedFormat('j M Y') }}
                                </div>
                            @endif
                        </div>
                    </td>

                    {{-- Col 4: ค่าเช่า (md+) --}}
                    <td class="px-4 py-3 text-right tabular-nums hidden md:table-cell align-top">
                        @if($booking)
                            <span class="font-bold text-gray-800">{{ number_format($booking->monthly_rent, 0) }}</span>
                            <span class="text-xs text-gray-400">฿</span>
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>

                    {{-- Col 5: ข้อมูลสัญญา (lg+) --}}
                    <td class="px-4 py-3 hidden lg:table-cell align-top">
                        <div class="space-y-0.5 text-[11px]">
                            @if($actualMoveIn)
                            <div class="flex gap-1.5 text-gray-500">
                                <span class="text-gray-400 w-18 flex-shrink-0 whitespace-nowrap">เข้าอยู่จริง</span>
                                <span>{{ $actualMoveIn->locale('th')->translatedFormat('j M Y') }}</span>
                            </div>
                            @endif
                            @if($contractStart)
                            <div class="flex gap-1.5 text-gray-500">
                                <span class="text-gray-400 w-18 flex-shrink-0 whitespace-nowrap">ตามสัญญา</span>
                                <span>{{ $contractStart->locale('th')->translatedFormat('j M Y') }}</span>
                            </div>
                            @elseif($checkIn && !$actualMoveIn)
                            <div class="flex gap-1.5 text-gray-500">
                                <span class="text-gray-400 w-18 flex-shrink-0 whitespace-nowrap">เริ่มเช่า</span>
                                <span>{{ $checkIn->locale('th')->translatedFormat('j M Y') }}</span>
                            </div>
                            @endif
                            @if($contractEnd)
                            <div class="flex gap-1.5 text-gray-500">
                                <span class="text-gray-400 w-18 flex-shrink-0 whitespace-nowrap">สิ้นสุด</span>
                                <span>{{ $contractEnd->locale('th')->translatedFormat('j M Y') }}</span>
                            </div>
                            @endif
                            @if($rentalMonths)
                            <div class="text-gray-400">{{ $rentalMonths }} เดือน</div>
                            @endif
                            @if(!$actualMoveIn && !$contractStart && !$checkIn && !$contractEnd && !$rentalMonths)
                            <span class="text-gray-300">—</span>
                            @endif
                        </div>
                    </td>

                    {{-- Action --}}
                    <td class="px-4 py-3 align-top">
                        <svg class="w-4 h-4 text-gray-300 group-hover:text-brand-500 transition-colors mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                <tr x-show="matchRow('vacant', @js($searchText), false)"
                    class="property-row hover:bg-gray-50/60 opacity-75">

                    {{-- Col 1 --}}
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2.5">
                            <div class="w-1.5 h-7 rounded-full bg-green-200 flex-shrink-0"></div>
                            <div class="min-w-0">
                                <p class="font-medium text-gray-600 truncate leading-snug">{{ $property->title ?? '—' }}</p>
                                @if($property->property_code)
                                <p class="text-[11px] text-gray-400 font-mono">{{ $property->property_code }}</p>
                                @endif
                                {{-- Mobile: show status + rent --}}
                                <div class="sm:hidden mt-1.5 flex items-center gap-2">
                                    <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-green-700 bg-green-50 border border-green-200 px-2 py-0.5 rounded-full">
                                        <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>ว่าง
                                    </span>
                                    @if($property->price_per_month)
                                    <span class="text-xs text-gray-500 tabular-nums">{{ number_format($property->price_per_month, 0) }} <span class="text-[10px] text-gray-400">฿/เดือน</span></span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </td>

                    {{-- Col 2: ผู้เช่า --}}
                    <td class="px-4 py-3 hidden sm:table-cell text-sm text-gray-400">—</td>

                    {{-- Col 3: สถานะ --}}
                    <td class="px-4 py-3 hidden sm:table-cell">
                        <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-green-700 bg-green-50 border border-green-200 px-2 py-0.5 rounded-full">
                            <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>ว่าง
                        </span>
                    </td>

                    {{-- Col 4: ค่าเช่า --}}
                    <td class="px-4 py-3 text-right tabular-nums hidden md:table-cell">
                        @if($property->price_per_month)
                            <span class="text-gray-500">{{ number_format($property->price_per_month, 0) }}</span>
                            <span class="text-xs text-gray-400">฿</span>
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>

                    {{-- Col 5: ข้อมูลสัญญา --}}
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
