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
.overflow-x-auto::-webkit-scrollbar { height: 6px; }
.overflow-x-auto::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
.overflow-x-auto::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }
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

    // ─── Badge color lookup (ใช้ร่วมกันทั้งการ์ดมือถือ และตารางเดสก์ท็อป) ───
    $badgeClasses = [
        'red'    => 'text-red-700 bg-red-50 border-red-200',
        'blue'   => 'text-blue-700 bg-blue-50 border-blue-200',
        'yellow' => 'text-yellow-700 bg-yellow-50 border-yellow-200',
        'green'  => 'text-green-700 bg-green-50 border-green-200',
        'gray'   => 'text-gray-600 bg-gray-50 border-gray-200',
    ];
    $dotClasses = [
        'red' => 'bg-red-500', 'blue' => 'bg-blue-500', 'yellow' => 'bg-yellow-500',
        'green' => 'bg-green-500', 'gray' => 'bg-gray-400',
    ];
    $statusMap = [
        'checked_in'        => ['color' => 'red',    'label' => 'ไม่ว่าง',        'pulse' => true],
        'confirmed'         => ['color' => 'red',    'label' => 'ยืนยันแล้ว',      'pulse' => false],
        'deposit_confirmed' => ['color' => 'blue',   'label' => 'โครงการในอนาคต', 'pulse' => false],
        'pending'           => ['color' => 'yellow', 'label' => 'จองแล้ว',        'pulse' => false],
    ];

    $resolveImageUrl = function ($property) use ($happyestPublic) {
        $media = $property->primaryImageMedia;
        if (! $media || ! $media->file_path) {
            return null;
        }

        return str_starts_with($media->file_path, 'http')
            ? $media->file_path
            : $happyestPublic . '/storage/' . $media->file_path;
    };

    // ─── เตรียมข้อมูลแสดงผลล่วงหน้า (คำนวณครั้งเดียว ใช้ได้ทั้งการ์ดมือถือ/ตารางเดสก์ท็อป) ───
    $contractRows = $withContract->map(function ($property) use ($happyestPublic, $duePendingRecords, $isPropertyVacant, $statusMap, $resolveImageUrl) {
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
        $tenantFullName = $tenant?->full_name ?? '(ไม่ระบุ)';
        $tenantNameShort = $tenant && mb_strlen($tenantFullName) > 10
            ? mb_substr($tenantFullName, 0, 10) . '...'
            : $tenantFullName;

        // Dates
        $checkIn       = $booking?->check_in;
        $actualMoveIn  = $booking?->actual_move_in_date;
        $contractStart = $booking?->contract_start_date;
        $contractEnd   = $booking?->check_out;
        $rentalMonths  = $booking?->rental_months ?? 0;

        // Payment records
        $records          = $booking?->paymentRecords ?? collect();
        $duePendingRecs   = $duePendingRecords($records);
        $hasPendingFailed = $duePendingRecs->isNotEmpty();
        $hasPendingVerify = $records->where('payment_status', 'pending_verification')->isNotEmpty();
        $isVacant         = $isPropertyVacant($property);

        $slipNeeded        = ! $isVacant && $bookingStatus !== 'deposit_confirmed' && $hasPendingFailed;
        $slipPendingVerify = $hasPendingVerify && ! $hasPendingFailed;
        $lastSlipAt        = $records->whereNotNull('paid_at')->sortByDesc('paid_at')->first()?->paid_at;

        // First pending record ที่ครบกำหนดแล้ว + due date
        $firstPendingRec = $duePendingRecs->first();
        $pendingDueDate  = $firstPendingRec?->due_date;
        $dueDatePast = $pendingDueDate && $pendingDueDate->lt(now()->startOfDay());
        $dueDateSoon = $pendingDueDate && ! $dueDatePast && $pendingDueDate->diffInDays(now()->startOfDay()) <= 3;
        $dueDateClass = $dueDatePast ? 'text-red-500 font-semibold' : ($dueDateSoon ? 'text-amber-500 font-semibold' : 'text-gray-400');

        // Slip label (เดือนที่ แทน รอบที่)
        $pendingSlipLabel = null;
        if ($slipNeeded && $firstPendingRec) {
            $pendingSlipLabel = match ($firstPendingRec->payment_type) {
                'monthly_rent'   => 'เดือนที่ ' . $firstPendingRec->month_number,
                'deposit'        => 'มัดจำงวด ' . ($firstPendingRec->deposit_phase ?? 1),
                'processing_fee' => 'ค่าดำเนินการ',
                'late_fee'       => 'ค่าปรับเดือน ' . $firstPendingRec->month_number,
                default          => null,
            };
        }

        $searchText = strtolower(
            ($property->title ?? '') . ' ' .
            ($property->property_code ?? '') . ' ' .
            ($tenant?->full_name ?? '') . ' ' .
            ($tenant?->mobile ?? '')
        );

        $status = $statusMap[$bookingStatus] ?? ['color' => 'gray', 'label' => 'มีสัญญา', 'pulse' => false];

        return (object) [
            'property'          => $property,
            'booking'           => $booking,
            'tenant'            => $tenant,
            'tenantPhotoUrl'    => $tenantPhotoUrl,
            'tenantInitial'     => $tenantInitial,
            'tenantFullName'    => $tenantFullName,
            'tenantNameShort'   => $tenantNameShort,
            'imageUrl'          => $resolveImageUrl($property),
            'checkIn'           => $checkIn,
            'actualMoveIn'      => $actualMoveIn,
            'contractStart'     => $contractStart,
            'contractEnd'       => $contractEnd,
            'rentalMonths'      => $rentalMonths,
            'slipNeeded'        => $slipNeeded,
            'slipPendingVerify' => $slipPendingVerify,
            'lastSlipAt'        => $lastSlipAt,
            'pendingDueDate'    => $pendingDueDate,
            'dueDatePast'       => $dueDatePast,
            'dueDateClass'      => $dueDateClass,
            'pendingSlipLabel'  => $pendingSlipLabel,
            'searchText'        => $searchText,
            'statusColor'       => $status['color'],
            'statusLabel'       => $status['label'],
            'statusPulse'       => $status['pulse'],
        ];
    });

    $vacantRows = $withoutContract->map(function ($property) use ($resolveImageUrl) {
        return (object) [
            'property'   => $property,
            'imageUrl'   => $resolveImageUrl($property),
            'searchText' => strtolower(($property->title ?? '') . ' ' . ($property->property_code ?? '')),
        ];
    });
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

{{-- ===== Summary Stats (KPI cards) ===== --}}
<div class="grid grid-cols-3 gap-2.5 sm:gap-3 mb-5">
    <div class="relative overflow-hidden bg-white rounded-2xl shadow-sm border border-gray-100 p-3 sm:p-4 transition-all duration-300 hover:shadow-lg hover:-translate-y-0.5">
        <div class="pointer-events-none absolute -top-4 -right-4 w-16 h-16 sm:w-20 sm:h-20 rounded-full bg-slate-50"></div>
        <div class="relative flex items-start justify-between gap-1.5">
            <div class="min-w-0">
                <p class="text-xl sm:text-2xl font-black text-gray-900 tabular-nums leading-none">{{ $totalAll }}</p>
                <p class="text-[10px] sm:text-xs text-gray-500 font-medium mt-1.5 leading-tight">ทรัพย์ทั้งหมด</p>
            </div>
            <div class="w-8 h-8 sm:w-10 sm:h-10 flex-shrink-0 rounded-xl bg-gradient-to-br from-slate-500 to-slate-700 flex items-center justify-center shadow-md shadow-slate-200">
                <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
            </div>
        </div>
    </div>
    <div class="relative overflow-hidden bg-white rounded-2xl shadow-sm border border-gray-100 p-3 sm:p-4 transition-all duration-300 hover:shadow-lg hover:-translate-y-0.5">
        <div class="pointer-events-none absolute -top-4 -right-4 w-16 h-16 sm:w-20 sm:h-20 rounded-full bg-red-50"></div>
        <div class="relative flex items-start justify-between gap-1.5">
            <div class="min-w-0">
                <p class="text-xl sm:text-2xl font-black text-red-600 tabular-nums leading-none">{{ $withContract->count() }}</p>
                <p class="text-[10px] sm:text-xs text-red-500 font-medium mt-1.5 leading-tight">ไม่ว่าง</p>
            </div>
            <div class="w-8 h-8 sm:w-10 sm:h-10 flex-shrink-0 rounded-xl bg-gradient-to-br from-red-400 to-red-600 flex items-center justify-center shadow-md shadow-red-200">
                <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
        </div>
    </div>
    <div class="relative overflow-hidden bg-white rounded-2xl shadow-sm border border-gray-100 p-3 sm:p-4 transition-all duration-300 hover:shadow-lg hover:-translate-y-0.5">
        <div class="pointer-events-none absolute -top-4 -right-4 w-16 h-16 sm:w-20 sm:h-20 rounded-full bg-emerald-50"></div>
        <div class="relative flex items-start justify-between gap-1.5">
            <div class="min-w-0">
                <p class="text-xl sm:text-2xl font-black text-green-600 tabular-nums leading-none">{{ $withoutContract->count() }}</p>
                <p class="text-[10px] sm:text-xs text-green-500 font-medium mt-1.5 leading-tight">ว่าง</p>
            </div>
            <div class="w-8 h-8 sm:w-10 sm:h-10 flex-shrink-0 rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center shadow-md shadow-emerald-200">
                <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
    </div>
</div>

@if($totalRent > 0)
<x-card class="flex items-center justify-between gap-3 px-4 py-3.5 mb-5">
    <div class="flex items-center gap-3 min-w-0">
        <div class="w-9 h-9 rounded-lg bg-brand-50 flex items-center justify-center flex-shrink-0">
            <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 10v2m9-8a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <p class="text-sm text-gray-500 truncate">รายรับค่าเช่า/เดือน (รวม)</p>
    </div>
    <p class="text-base font-bold text-gray-800 tabular-nums flex-shrink-0">{{ number_format($totalRent, 0) }} <span class="text-xs font-normal text-gray-400">฿</span></p>
</x-card>
@endif

{{-- ===== Slip Alert Banners ===== --}}
@if($totalSlipNeeded > 0)
<button type="button"
        x-on:click="filter = (filter === 'slip_needed' ? 'all' : 'slip_needed')"
        class="w-full flex items-center gap-3 bg-amber-50 border border-amber-200 rounded-2xl px-4 py-3 mb-3 text-left">
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
        class="w-full flex items-center gap-3 bg-blue-50 border border-blue-200 rounded-2xl px-4 py-3 mb-3 text-left">
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
<div class="flex flex-col gap-2.5 mb-4">
    <div class="relative border border-gray-300 rounded-xl bg-white transition-all focus-within:ring-2 focus-within:ring-brand-500/20 focus-within:border-brand-500">
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
    <div class="flex flex-wrap gap-1.5 bg-gray-100 rounded-xl p-1.5">
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

{{-- ============================================================ --}}
{{-- MOBILE CARDS (below md) --}}
{{-- ============================================================ --}}
<div class="md:hidden space-y-3">

    @foreach($contractRows as $row)
    <a href="{{ route('properties.show', $row->property->id) }}"
       x-show="matchRow('active', @js($row->searchText), @js($row->slipNeeded), @js($row->slipPendingVerify))"
       class="property-row block bg-white rounded-2xl shadow-sm border border-gray-100 p-4 active:bg-gray-50 transition-colors">

        <div class="flex items-start gap-3">
            {{-- Thumbnail --}}
            <div class="w-16 h-16 rounded-xl overflow-hidden flex-shrink-0 bg-gray-100 relative">
                @if($row->imageUrl)
                <img src="{{ $row->imageUrl }}" alt="{{ $row->property->title }}"
                     class="w-full h-full object-cover"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                @endif
                <div class="w-full h-full items-center justify-center absolute inset-0 {{ $row->imageUrl ? 'hidden' : 'flex' }}">
                    <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>
            </div>

            <div class="min-w-0 flex-1">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="font-semibold text-gray-800 truncate leading-snug">{{ $row->property->title ?? '—' }}</p>
                        @if($row->property->property_code)
                        <p class="text-[11px] text-gray-400 font-mono mt-0.5">{{ $row->property->property_code }}</p>
                        @endif
                    </div>
                    <svg class="w-4 h-4 text-gray-300 flex-shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                    </svg>
                </div>

                <div class="flex items-center justify-between gap-2 flex-wrap mt-2">
                    <span class="inline-flex items-center gap-1 text-[10px] font-semibold {{ $badgeClasses[$row->statusColor] }} border px-2 py-0.5 rounded-full">
                        <span class="w-1.5 h-1.5 rounded-full {{ $dotClasses[$row->statusColor] }} {{ $row->statusPulse ? 'animate-pulse' : '' }}"></span>{{ $row->statusLabel }}
                    </span>
                    @if($row->booking?->monthly_rent)
                    <span class="text-xs font-bold text-gray-700 tabular-nums">{{ number_format($row->booking->monthly_rent, 0) }} <span class="font-normal text-gray-400 text-[10px]">฿/ด.</span></span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Tenant + slip + dates --}}
        <div class="mt-3 pt-3 border-t border-gray-100 space-y-1.5">
            <div class="flex items-center gap-1.5">
                @if($row->tenantPhotoUrl)
                    <img src="{{ $row->tenantPhotoUrl }}" alt="{{ $row->tenant->full_name }}"
                         class="w-5 h-5 rounded-full object-cover flex-shrink-0 ring-1 ring-gray-200">
                @else
                    <div class="w-5 h-5 rounded-full bg-brand-600 flex items-center justify-center flex-shrink-0">
                        <span class="text-white text-[9px] font-bold leading-none">{{ $row->tenantInitial }}</span>
                    </div>
                @endif
                <span class="text-xs text-gray-700 font-medium truncate">{{ $row->tenant?->full_name ?? '(ไม่ระบุ)' }}</span>
                @if($row->tenant?->mobile)
                <span class="text-[10px] text-gray-400 flex-shrink-0">· {{ $row->tenant->mobile }}</span>
                @endif
            </div>

            @if($row->slipNeeded)
            <div class="flex items-center gap-1 text-[10px] font-semibold text-amber-600 flex-wrap">
                <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                รอแนบสลิป@if($row->pendingSlipLabel) <span class="font-normal text-amber-500">· {{ $row->pendingSlipLabel }}</span>@endif
                @if($row->pendingDueDate) <span class="{{ $row->dueDateClass }}">· ครบ {{ $row->pendingDueDate->locale('th')->translatedFormat('j M') }}</span>@endif
            </div>
            @elseif($row->slipPendingVerify)
            <div class="flex items-center gap-1 text-[10px] font-semibold text-blue-500">
                <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                รอตรวจสอบ@if($row->lastSlipAt) <span class="font-normal text-blue-400">· {{ $row->lastSlipAt->locale('th')->translatedFormat('j M') }}</span>@endif
            </div>
            @elseif($row->lastSlipAt)
            <div class="flex items-center gap-1 text-[10px] text-gray-400">
                <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
                แนบล่าสุด {{ $row->lastSlipAt->locale('th')->translatedFormat('j M Y') }}
            </div>
            @endif

            @php $displayDate = $row->actualMoveIn ?? $row->contractStart ?? $row->checkIn; @endphp
            @if($displayDate || $row->contractEnd || $row->rentalMonths)
            <div class="text-[10px] text-gray-400 leading-relaxed flex flex-wrap gap-x-1">
                @if($displayDate)
                <span>เข้าอยู่: {{ $displayDate->locale('th')->translatedFormat('j M Y') }}</span>
                @endif
                @if($row->contractEnd)
                <span>· สิ้นสุด: {{ $row->contractEnd->locale('th')->translatedFormat('j M Y') }}</span>
                @endif
                @if($row->rentalMonths)
                <span>· {{ $row->rentalMonths }} เดือน</span>
                @endif
            </div>
            @endif
        </div>
    </a>
    @endforeach

    @foreach($vacantRows as $row)
    <div x-show="matchRow('vacant', @js($row->searchText), false, false)"
         class="property-row bg-white rounded-2xl border border-gray-100 p-4 opacity-75">
        <div class="flex items-center gap-3">
            <div class="w-16 h-16 rounded-xl overflow-hidden flex-shrink-0 bg-gray-100 relative">
                @if($row->imageUrl)
                <img src="{{ $row->imageUrl }}" alt="{{ $row->property->title }}"
                     class="w-full h-full object-cover"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                @endif
                <div class="w-full h-full items-center justify-center absolute inset-0 {{ $row->imageUrl ? 'hidden' : 'flex' }}">
                    <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>
            </div>
            <div class="min-w-0 flex-1">
                <p class="font-medium text-gray-600 truncate leading-snug">{{ $row->property->title ?? '—' }}</p>
                @if($row->property->property_code)
                <p class="text-[11px] text-gray-400 font-mono">{{ $row->property->property_code }}</p>
                @endif
                <div class="flex items-center gap-2 mt-1.5 flex-wrap">
                    <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-green-700 bg-green-50 border border-green-200 px-2 py-0.5 rounded-full">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>ว่าง
                    </span>
                    @if($row->property->price_per_month)
                    <span class="text-xs text-gray-500 tabular-nums">{{ number_format($row->property->price_per_month, 0) }} <span class="text-[10px] text-gray-400">฿/เดือน</span></span>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endforeach

</div>

{{-- ============================================================ --}}
{{-- DESKTOP TABLE (md and up) --}}
{{-- ============================================================ --}}
<x-table>
    <x-slot:head>
        <th class="text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wide px-5 py-3.5">ทรัพย์สิน</th>
        <th class="text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wide px-5 py-3.5 md:w-32">ผู้เช่า</th>
        <th class="text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wide px-5 py-3.5">สถานะ / การชำระ</th>
        <th class="text-right text-[11px] font-semibold text-gray-400 uppercase tracking-wide px-5 py-3.5 hidden md:table-cell">ค่าเช่า/เดือน</th>
        <th class="text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wide px-5 py-3.5 hidden lg:table-cell lg:w-72 xl:w-80">ข้อมูลสัญญา</th>
        <th class="px-5 py-3.5 w-10"></th>
    </x-slot:head>

    @foreach($contractRows as $row)
    <tr x-show="matchRow('active', @js($row->searchText), @js($row->slipNeeded), @js($row->slipPendingVerify))"
        class="property-row hover:bg-gray-50 cursor-pointer group"
        onclick="window.location='{{ route('properties.show', $row->property->id) }}'"
        role="button"
        tabindex="0"
        @keypress.enter="window.location='{{ route('properties.show', $row->property->id) }}'">

        {{-- Col 1: ทรัพย์สิน --}}
        <td class="px-5 py-3.5 align-top">
            @if($row->property->property_code)
                <p class="font-mono font-bold text-sm text-gray-800 group-hover:text-brand-600 transition-colors leading-snug">{{ $row->property->property_code }}</p>
                <p class="text-[11px] text-gray-400 truncate mt-0.5">{{ $row->property->title ?? '—' }}</p>
            @else
                <p class="font-semibold text-gray-800 group-hover:text-brand-600 transition-colors leading-snug">{{ $row->property->title ?? '—' }}</p>
            @endif
        </td>

        {{-- Col 2: ผู้เช่า --}}
        <td class="px-5 py-3.5 align-top">
            <div class="flex items-center gap-2.5">
                @if($row->tenantPhotoUrl)
                    <img src="{{ $row->tenantPhotoUrl }}" alt="{{ $row->tenant->full_name }}"
                         class="w-8 h-8 rounded-full object-cover flex-shrink-0 ring-1 ring-gray-200">
                @else
                    <div class="w-8 h-8 rounded-full bg-brand-600 flex items-center justify-center flex-shrink-0">
                        <span class="text-white text-xs font-bold leading-none">{{ $row->tenantInitial }}</span>
                    </div>
                @endif
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-gray-700 whitespace-nowrap" title="{{ $row->tenantFullName }}">{{ $row->tenantNameShort }}</p>
                    @if($row->tenant?->mobile)
                    <p class="text-[11px] text-gray-400 mt-0.5">{{ $row->tenant->mobile }}</p>
                    @endif
                </div>
            </div>
        </td>

        {{-- Col 3: สถานะ + การชำระ --}}
        <td class="px-5 py-3.5 align-top">
            <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold {{ $badgeClasses[$row->statusColor] }} border px-2 py-0.5 rounded-full whitespace-nowrap">
                <span class="w-1.5 h-1.5 rounded-full {{ $dotClasses[$row->statusColor] }} {{ $row->statusPulse ? 'animate-pulse' : '' }}"></span>{{ $row->statusLabel }}
            </span>

            <div class="mt-2 space-y-1">
                @if($row->slipNeeded)
                    <div class="inline-flex items-center gap-1 text-[10px] font-semibold text-amber-600">
                        <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                        รอแนบสลิป@if($row->pendingSlipLabel) <span class="font-normal text-amber-500 ml-0.5">· {{ $row->pendingSlipLabel }}</span>@endif
                    </div>
                    @if($row->pendingDueDate)
                    <div class="flex items-center gap-1 text-[10px] {{ $row->dueDateClass }}">
                        <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        {{ $row->dueDatePast ? 'เกินกำหนด' : 'ครบกำหนด' }} {{ $row->pendingDueDate->locale('th')->translatedFormat('j M Y') }}
                    </div>
                    @endif
                @elseif($row->slipPendingVerify)
                    <div class="inline-flex items-center gap-1 text-[10px] font-semibold text-blue-500">
                        <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        รอตรวจสอบ@if($row->lastSlipAt) <span class="font-normal text-blue-400 ml-0.5">· {{ $row->lastSlipAt->locale('th')->translatedFormat('j M') }}</span>@endif
                    </div>
                @elseif($row->lastSlipAt)
                    <div class="inline-flex items-center gap-1 text-[10px] text-gray-400">
                        <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                        แนบล่าสุด {{ $row->lastSlipAt->locale('th')->translatedFormat('j M Y') }}
                    </div>
                @endif
            </div>
        </td>

        {{-- Col 4: ค่าเช่า (md+) --}}
        <td class="px-5 py-3.5 text-right tabular-nums hidden md:table-cell align-top">
            @if($row->booking)
                <span class="font-bold text-gray-800">{{ number_format($row->booking->monthly_rent, 0) }}</span>
                <span class="text-xs text-gray-400">฿</span>
            @else
                <span class="text-gray-400">—</span>
            @endif
        </td>

        {{-- Col 5: ข้อมูลสัญญา (lg+) --}}
        <td class="px-5 py-3.5 hidden lg:table-cell align-top">
            <div class="space-y-1.5 text-xs">
                @if($row->actualMoveIn)
                <div class="flex gap-3 text-gray-500">
                    <span class="text-gray-400 w-24 flex-shrink-0 whitespace-nowrap">เข้าอยู่จริง</span>
                    <span class="whitespace-nowrap">{{ $row->actualMoveIn->locale('th')->translatedFormat('j F Y') }}</span>
                </div>
                @endif
                @if($row->contractStart)
                <div class="flex gap-3 text-gray-500">
                    <span class="text-gray-400 w-24 flex-shrink-0 whitespace-nowrap">ตามสัญญา</span>
                    <span class="whitespace-nowrap">{{ $row->contractStart->locale('th')->translatedFormat('j F Y') }}</span>
                </div>
                @elseif($row->checkIn && !$row->actualMoveIn)
                <div class="flex gap-3 text-gray-500">
                    <span class="text-gray-400 w-24 flex-shrink-0 whitespace-nowrap">เริ่มเช่า</span>
                    <span class="whitespace-nowrap">{{ $row->checkIn->locale('th')->translatedFormat('j F Y') }}</span>
                </div>
                @endif
                @if($row->contractEnd)
                <div class="flex gap-3 text-gray-500">
                    <span class="text-gray-400 w-24 flex-shrink-0 whitespace-nowrap">สิ้นสุด</span>
                    <span class="whitespace-nowrap">{{ $row->contractEnd->locale('th')->translatedFormat('j F Y') }}</span>
                </div>
                @endif
                @if($row->rentalMonths)
                <div class="flex gap-3 text-gray-400">
                    <span class="w-24 flex-shrink-0 whitespace-nowrap">ระยะเวลาเช่า</span>
                    <span>{{ $row->rentalMonths }} เดือน</span>
                </div>
                @endif
                @if(!$row->actualMoveIn && !$row->contractStart && !$row->checkIn && !$row->contractEnd && !$row->rentalMonths)
                <span class="text-gray-300">—</span>
                @endif
            </div>
        </td>

        {{-- Action --}}
        <td class="px-5 py-3.5 align-top">
            <svg class="w-4 h-4 text-gray-300 group-hover:text-brand-500 transition-colors mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
            </svg>
        </td>
    </tr>
    @endforeach

    @foreach($vacantRows as $row)
    <tr x-show="matchRow('vacant', @js($row->searchText), false, false)"
        class="property-row hover:bg-gray-50/60 opacity-75">

        <td class="px-5 py-3.5">
            @if($row->property->property_code)
                <p class="font-mono font-bold text-sm text-gray-600 leading-snug">{{ $row->property->property_code }}</p>
                <p class="text-[11px] text-gray-400 truncate mt-0.5">{{ $row->property->title ?? '—' }}</p>
            @else
                <p class="font-medium text-gray-600 leading-snug">{{ $row->property->title ?? '—' }}</p>
            @endif
        </td>

        <td class="px-5 py-3.5 text-sm text-gray-400">—</td>

        <td class="px-5 py-3.5">
            <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-green-700 bg-green-50 border border-green-200 px-2 py-0.5 rounded-full">
                <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>ว่าง
            </span>
        </td>

        <td class="px-5 py-3.5 text-right tabular-nums hidden md:table-cell">
            @if($row->property->price_per_month)
                <span class="text-gray-500">{{ number_format($row->property->price_per_month, 0) }}</span>
                <span class="text-xs text-gray-400">฿</span>
            @else
                <span class="text-gray-400">—</span>
            @endif
        </td>

        <td class="px-5 py-3.5 hidden lg:table-cell text-gray-400">—</td>

        <td class="px-5 py-3.5"></td>
    </tr>
    @endforeach
</x-table>
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
