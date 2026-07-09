@extends('layouts.app')

@section('title', 'ภาพรวม')
@section('breadcrumb', 'ยอดชำระเงินและสรุปการจอง')

@section('content')

@php
    $happyestPublic = rtrim(env('HAPPYEST_APP_URL', 'http://127.0.0.1/happyest/public'), '/');
    $thM = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
@endphp

{{-- ── Hero Header ─────────────────────────────────────────────────────────── --}}
<div class="relative overflow-hidden rounded-2xl bg-brand-900 mb-6 p-4 sm:p-5 lg:p-6"
     style="background: linear-gradient(135deg, #1c3514 0%, #2a4f1f 45%, #1c3514 100%);">

    {{-- Shimmer sweep --}}
    <div class="hero-shimmer-bar"></div>

    {{-- Glow accents --}}
    <div class="hero-glow" style="width:220px;height:220px;top:-60px;right:-60px;background:rgba(154,216,114,0.12);"></div>
    <div class="hero-glow" style="width:140px;height:140px;bottom:-40px;left:30%;background:rgba(70,132,50,0.10);animation-delay:2s;"></div>

    {{-- Decorative blobs --}}
    <div class="hero-blob-1 pointer-events-none absolute -top-10 -right-10 w-44 h-44 rounded-full bg-brand-700 opacity-30"></div>
    <div class="hero-blob-2 pointer-events-none absolute top-2 right-14 w-24 h-24 rounded-full bg-brand-600 opacity-20"></div>
    <div class="hero-blob-3 pointer-events-none absolute -bottom-8 right-4 w-32 h-32 rounded-full bg-brand-800 opacity-40"></div>

    {{-- Top row --}}
    <div class="relative flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 sm:gap-4" style="z-index:2;">
        <div class="hero-text-row min-w-0">
            <p class="text-xs font-medium mb-1" style="color: rgba(255,255,255,0.6)">
                {{ now()->day . ' ' . $thM[now()->month - 1] . ' ' . (now()->year + 543) }}
            </p>
            <h2 class="text-xl lg:text-2xl font-black text-white leading-tight truncate">
                สวัสดี, {{ session('agent_name') }} 👋
            </h2>
            <p class="text-sm mt-1.5" style="color: rgba(255,255,255,0.65)">
                สรุปภาพรวมพอร์ตโฟลิโอและการชำระเงิน
            </p>
        </div>
        <div class="hero-badge-row flex-shrink-0 flex flex-row sm:flex-col flex-wrap items-center sm:items-end gap-2">
            {{-- Agent code badge --}}
            <span class="inline-flex items-center gap-1.5 rounded-xl border px-3 py-1.5 text-xs font-bold text-white"
                  style="background:rgba(255,255,255,0.12); border-color:rgba(255,255,255,0.2); backdrop-filter:blur(8px);">
                <span class="w-1.5 h-1.5 rounded-full bg-green-400 animate-pulse flex-shrink-0"></span>
                {{ session('agent_code') }}
            </span>
            @if($overdueCount > 0)
            <span class="inline-flex items-center gap-1.5 rounded-xl border px-3 py-1.5 text-xs font-semibold text-red-200"
                  style="background:rgba(239,68,68,0.18); border-color:rgba(239,68,68,0.3); backdrop-filter:blur(8px);">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.07 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                </svg>
                ค้างชำระ {{ $overdueCount }} รายการ
            </span>
            @endif
        </div>
    </div>

    {{-- Quick stats strip --}}
    <div class="hero-stats-row relative mt-4 sm:mt-5 pt-4 grid grid-cols-3 gap-2 sm:gap-3"
         style="border-top:1px solid rgba(255,255,255,0.12); z-index:2;">
        <div class="text-center">
            <p class="text-2xl font-black text-white tabular-nums leading-none">{{ number_format($totalBookings) }}</p>
            <p class="text-[11px] font-medium mt-1" style="color:rgba(255,255,255,0.6)">การจองทั้งหมด</p>
        </div>
        <div class="text-center" style="border-left:1px solid rgba(255,255,255,0.12); border-right:1px solid rgba(255,255,255,0.12)">
            <p class="text-2xl font-black text-white tabular-nums leading-none">
                @if($paidAmount >= 1000000)
                    {{ number_format($paidAmount / 1000000, 1) }}<span class="text-sm" style="color:rgba(255,255,255,0.6)">ล.</span>
                @elseif($paidAmount >= 10000)
                    {{ number_format($paidAmount / 1000, 1) }}<span class="text-sm" style="color:rgba(255,255,255,0.6)">K</span>
                @else
                    {{ number_format($paidAmount, 0) }}
                @endif
            </p>
            <p class="text-[11px] font-medium mt-1" style="color:rgba(255,255,255,0.6)">ยอดชำระแล้ว</p>
        </div>
        <div class="text-center">
            <p class="text-2xl font-black text-white tabular-nums leading-none">{{ number_format($totalCustomers) }}</p>
            <p class="text-[11px] font-medium mt-1" style="color:rgba(255,255,255,0.6)">ลูกค้าทั้งหมด</p>
        </div>
    </div>
</div>

{{-- ── Ad Referral Links ────────────────────────────────────────────────────── --}}
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
            <p class="text-sm font-bold text-gray-900">ลิงก์แนะนำสำหรับยิงแอด</p>
            <p class="text-xs text-gray-500 mt-0.5">ลูกค้าที่คลิกลิงก์แล้วสมัครสมาชิกใหม่ จะผูกเป็นลูกค้าของคุณอัตโนมัติทุกการจอง</p>
        </div>
    </div>

    <div class="flex items-center gap-2 p-3 rounded-xl bg-green-50 border border-green-200 mb-3">
        <div class="flex-1 min-w-0">
            <p class="text-xs font-semibold text-green-700">ลิงก์หน้าแรก</p>
            <p class="text-xs text-gray-500 truncate">{{ $happyestPublic }}?ref={{ session('agent_code') }}</p>
        </div>
        <button type="button" onclick="copyAdLink('{{ $happyestPublic }}?ref={{ session('agent_code') }}')"
                class="flex-shrink-0 inline-flex items-center gap-1.5 rounded-lg bg-green-600 hover:bg-green-700 text-white text-xs font-semibold px-3 py-2 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
            </svg>
            คัดลอก
        </button>
    </div>

    @if($myListedProperties->isEmpty())
        <p class="text-xs text-gray-400 text-center py-2">คุณยังไม่มีทรัพย์ที่ลงประกาศ (ลงประกาศได้ที่หน้าเว็บหลัก)</p>
    @else
        @if($myListedPropertiesTotal > $myListedProperties->count())
            <p class="text-[11px] text-gray-400 mb-2">แสดง {{ $myListedProperties->count() }} รายการล่าสุด จากทั้งหมด {{ $myListedPropertiesTotal }} รายการ</p>
        @endif
        <div class="max-h-64 overflow-y-auto flex flex-col gap-2">
            @foreach($myListedProperties as $property)
                <div class="flex items-center gap-2 p-2.5 rounded-xl border border-gray-100 hover:border-gray-200 transition-colors">
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold text-gray-800 truncate">{{ $property->title }}</p>
                        <p class="text-[11px] text-gray-400">{{ $property->property_code }}</p>
                    </div>
                    @if($property->status === 'published')
                        <button type="button"
                                onclick="copyAdLink('{{ $happyestPublic }}/property/{{ $property->slug }}?ref={{ session('agent_code') }}')"
                                class="flex-shrink-0 inline-flex items-center gap-1.5 rounded-lg bg-white border border-green-300 hover:bg-green-50 text-green-700 text-[11px] font-semibold px-2.5 py-1.5 transition-colors">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                            คัดลอกลิงก์
                        </button>
                    @else
                        <span class="flex-shrink-0 text-[11px] font-medium text-gray-400 px-2.5 py-1.5" title="ต้องรอเผยแพร่ก่อนจึงจะยิงแอดได้">
                            รอเผยแพร่
                        </span>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>

{{-- ── Stats Cards ──────────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">

    {{-- การจองทั้งหมด --}}
    <div class="relative overflow-hidden bg-white rounded-2xl shadow-sm border border-gray-100 p-3.5 sm:p-4 transition-all duration-300 hover:shadow-lg hover:-translate-y-0.5">
        <div class="pointer-events-none absolute -top-4 -right-4 w-16 h-16 sm:w-20 sm:h-20 rounded-full bg-indigo-50 opacity-70"></div>
        <div class="relative flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-[11px] sm:text-xs text-gray-500 font-medium mb-1.5 truncate">การจองทั้งหมด</p>
                <p class="text-xl sm:text-2xl lg:text-3xl font-black text-gray-900 tabular-nums leading-none">
                    {{ number_format($totalBookings) }}
                </p>
            </div>
            <div class="w-9 h-9 sm:w-10 sm:h-10 flex-shrink-0 rounded-xl bg-indigo-500 bg-gradient-to-br from-indigo-400 to-indigo-600
                        flex items-center justify-center shadow-md shadow-indigo-200">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
        </div>
    </div>

    {{-- ยอดชำระแล้ว --}}
    <div class="relative overflow-hidden bg-white rounded-2xl shadow-sm border border-gray-100 p-3.5 sm:p-4 transition-all duration-300 hover:shadow-lg hover:-translate-y-0.5">
        <div class="pointer-events-none absolute -top-4 -right-4 w-16 h-16 sm:w-20 sm:h-20 rounded-full bg-emerald-50 opacity-70"></div>
        <div class="relative flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-[11px] sm:text-xs text-gray-500 font-medium mb-1.5 truncate">ยอดชำระแล้ว</p>
                <p class="text-xl sm:text-2xl lg:text-3xl font-black text-gray-900 tabular-nums leading-none">
                    @if($paidAmount >= 1000000)
                        {{ number_format($paidAmount / 1000000, 2) }}<span class="text-base font-semibold text-gray-400 ml-0.5">ล.</span>
                    @elseif($paidAmount >= 10000)
                        {{ number_format($paidAmount / 1000, 1) }}<span class="text-base font-semibold text-gray-400 ml-0.5">K</span>
                    @else
                        {{ number_format($paidAmount, 0) }}
                    @endif
                </p>
            </div>
            <div class="w-9 h-9 sm:w-10 sm:h-10 flex-shrink-0 rounded-xl bg-emerald-500 bg-gradient-to-br from-emerald-400 to-emerald-600
                        flex items-center justify-center shadow-md shadow-emerald-200">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
    </div>

    {{-- รออนุมัติสลิป --}}
    <div class="relative overflow-hidden bg-white rounded-2xl shadow-sm border border-gray-100 p-3.5 sm:p-4 transition-all duration-300 hover:shadow-lg hover:-translate-y-0.5">
        <div class="pointer-events-none absolute -top-4 -right-4 w-16 h-16 sm:w-20 sm:h-20 rounded-full bg-amber-50 opacity-70"></div>
        <div class="relative flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-[11px] sm:text-xs text-gray-500 font-medium mb-1.5 truncate">รออนุมัติสลิป</p>
                <div class="flex items-end gap-2 flex-wrap">
                    <p class="text-xl sm:text-2xl lg:text-3xl font-black text-gray-900 tabular-nums leading-none">
                        {{ number_format($pendingVerificationCount) }}
                    </p>
                    @if($pendingVerificationCount > 0)
                        <span class="mb-0.5 inline-flex items-center gap-1 text-[10px] font-bold text-amber-600 bg-amber-50 px-1.5 py-0.5 rounded-md">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                            รอดำเนินการ
                        </span>
                    @endif
                </div>
            </div>
            <div class="w-9 h-9 sm:w-10 sm:h-10 flex-shrink-0 rounded-xl bg-amber-500 bg-gradient-to-br from-amber-400 to-orange-500
                        flex items-center justify-center shadow-md shadow-amber-200">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
            </div>
        </div>
    </div>

    {{-- ค้างชำระ --}}
    <div class="relative overflow-hidden bg-white rounded-2xl shadow-sm border {{ $overdueCount > 0 ? 'border-red-100' : 'border-gray-100' }} p-3.5 sm:p-4 transition-all duration-300 hover:shadow-lg hover:-translate-y-0.5">
        <div class="pointer-events-none absolute -top-4 -right-4 w-16 h-16 sm:w-20 sm:h-20 rounded-full bg-red-50 opacity-70"></div>
        <div class="relative flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-[11px] sm:text-xs text-gray-500 font-medium mb-1.5 truncate">ค้างชำระ</p>
                <div class="flex items-end gap-2 flex-wrap">
                    <p class="text-xl sm:text-2xl lg:text-3xl font-black tabular-nums leading-none {{ $overdueCount > 0 ? 'text-red-600' : 'text-gray-900' }}">
                        {{ number_format($overdueCount) }}
                    </p>
                    @if($overdueCount > 0)
                        <span class="mb-0.5 text-[10px] font-bold text-red-600 bg-red-50 px-1.5 py-0.5 rounded-md">เลยกำหนด</span>
                    @endif
                </div>
            </div>
            <div class="w-9 h-9 sm:w-10 sm:h-10 flex-shrink-0 rounded-xl bg-red-500 bg-gradient-to-br from-red-400 to-red-600
                        flex items-center justify-center shadow-md shadow-red-200">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.07 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                </svg>
            </div>
        </div>
    </div>

</div>

{{-- ── Charts Row ───────────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-5 lg:items-stretch">

    {{-- Bar Chart (2/3) --}}
    <div class="md:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 p-4 sm:p-5">
        <div class="flex items-start justify-between mb-4">
            <div>
                <h3 class="text-sm font-bold text-gray-800">ยอดชำระรายเดือน</h3>
                <p class="text-xs text-gray-400 mt-0.5">6 เดือนล่าสุด · เฉพาะที่ยืนยันแล้ว</p>
            </div>
            <span class="inline-flex items-center gap-1.5 bg-brand-50 text-brand-700 text-xs font-semibold px-2.5 py-1.5 rounded-lg">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/>
                </svg>
                ชำระแล้ว
            </span>
        </div>
        <div class="relative" style="height:210px">
            <canvas id="barChart"></canvas>
        </div>
    </div>

        {{-- Donut 1: Payment Status --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 sm:p-5 flex flex-col">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <h3 class="text-sm font-bold text-gray-800">สถานะการชำระ</h3>
                    <p class="text-xs text-gray-400 mt-0.5">แยกตามสถานะ</p>
                </div>
                <span class="inline-flex items-center gap-1 bg-purple-50 text-purple-600 text-xs font-semibold px-2 py-1 rounded-lg">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/>
                    </svg>
                    สัดส่วน
                </span>
            </div>
            <div class="flex-1 flex flex-col items-center justify-center">
                <div class="flex items-center justify-center w-full" style="height:130px">
                    <canvas id="donutChart"></canvas>
                </div>
                @php
                    $payLegend = [
                        ['label' => 'รอชำระ',    'color' => '#F59E0B', 'bg' => 'bg-amber-50',  'text' => 'text-amber-700'],
                        ['label' => 'รออนุมัติ', 'color' => '#3B82F6', 'bg' => 'bg-blue-50',   'text' => 'text-blue-700'],
                        ['label' => 'ชำระแล้ว',  'color' => '#22C55E', 'bg' => 'bg-green-50',  'text' => 'text-green-700'],
                        ['label' => 'ไม่ผ่าน',   'color' => '#EF4444', 'bg' => 'bg-red-50',    'text' => 'text-red-700'],
                        ['label' => 'คืนเงิน',   'color' => '#94A3B8', 'bg' => 'bg-slate-50',  'text' => 'text-slate-600'],
                    ];
                @endphp
                <div class="mt-3 grid grid-cols-2 gap-x-3 gap-y-1.5 w-full">
                    @foreach($payLegend as $i => $item)
                        @if(($donutCounts[$i] ?? 0) > 0)
                        <div class="flex items-center gap-1.5 min-w-0">
                            <span class="w-2 h-2 rounded-full flex-shrink-0" style="background:{{ $item['color'] }}"></span>
                            <span class="text-[11px] text-gray-500 truncate">{{ $item['label'] }}</span>
                            <span class="text-[11px] font-bold {{ $item['text'] }} ml-auto tabular-nums flex-shrink-0">{{ $donutCounts[$i] }}</span>
                        </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Donut 2: Booking Status (NEW) --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 sm:p-5 flex flex-col">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <h3 class="text-sm font-bold text-gray-800">สถานะการจอง</h3>
                    <p class="text-xs text-gray-400 mt-0.5">แยกตามสถานะการจอง</p>
                </div>
                <span class="inline-flex items-center gap-1 bg-brand-50 text-brand-700 text-xs font-semibold px-2 py-1 rounded-lg">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    จอง
                </span>
            </div>
            <div class="flex-1 flex flex-col items-center justify-center">
                <div class="flex items-center justify-center w-full" style="height:130px">
                    <canvas id="bookingDonut"></canvas>
                </div>
                @php
                    $bookingLegendConfig = [
                        'pending'           => ['color' => '#F59E0B', 'bg' => 'bg-amber-50',   'text' => 'text-amber-700'],
                        'deposit_confirmed' => ['color' => '#3B82F6', 'bg' => 'bg-blue-50',    'text' => 'text-blue-700'],
                        'confirmed'         => ['color' => '#8B5CF6', 'bg' => 'bg-violet-50',  'text' => 'text-violet-700'],
                        'checked_in'        => ['color' => '#EF4444', 'bg' => 'bg-red-50',    'text' => 'text-red-700'],
                        'checked_out'       => ['color' => '#94A3B8', 'bg' => 'bg-slate-50',   'text' => 'text-slate-600'],
                        'completed'         => ['color' => '#10B981', 'bg' => 'bg-emerald-50', 'text' => 'text-emerald-700'],
                        'cancelled'         => ['color' => '#EF4444', 'bg' => 'bg-red-50',     'text' => 'text-red-700'],
                        'rejected'          => ['color' => '#6B7280', 'bg' => 'bg-gray-50',    'text' => 'text-gray-600'],
                    ];
                    $bKeys = array_keys($bookingLegendConfig);
                @endphp
                <div class="mt-3 grid grid-cols-2 gap-x-3 gap-y-1.5 w-full">
                    @foreach($bookingStatusLabels as $i => $label)
                        @if(($bookingStatusCounts[$i] ?? 0) > 0)
                        @php
                            $bKey = $bKeys[$i] ?? '';
                            $bCfg = $bookingLegendConfig[$bKey] ?? ['color' => '#94A3B8', 'text' => 'text-gray-600'];
                        @endphp
                        <div class="flex items-center gap-1.5 min-w-0">
                            <span class="w-2 h-2 rounded-full flex-shrink-0" style="background:{{ $bCfg['color'] }}"></span>
                            <span class="text-[11px] text-gray-500 truncate">{{ $label }}</span>
                            <span class="text-[11px] font-bold {{ $bCfg['text'] }} ml-auto tabular-nums flex-shrink-0">{{ $bookingStatusCounts[$i] }}</span>
                        </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>


</div>

{{-- ── Customers + Automation Row ──────────────────────────────────────────── --}}
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">

    {{-- ลูกค้าล่าสุด --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-4 sm:px-5 py-4 border-b border-gray-50 flex items-center justify-between gap-2">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="w-9 h-9 rounded-xl bg-brand-600 bg-gradient-to-br from-brand-500 to-brand-700
                            flex items-center justify-center shadow-sm flex-shrink-0">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <h3 class="text-sm font-bold text-gray-800 truncate">ลูกค้าล่าสุด</h3>
                    <p class="text-xs text-gray-400 truncate">ผู้เช่าในความรับผิดชอบ</p>
                </div>
            </div>
            <span class="flex-shrink-0 text-xs font-semibold text-brand-700 bg-brand-50 border border-brand-100 px-2.5 py-1 rounded-full">
                {{ $totalCustomers }} ราย
            </span>
        </div>

        @if($recentCustomers->isEmpty())
        <div class="py-12 text-center">
            <div class="w-12 h-12 bg-gray-50 rounded-xl flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
            <p class="text-sm text-gray-400">ยังไม่มีข้อมูลลูกค้า</p>
        </div>
        @else
        <div class="divide-y divide-gray-50">
            @foreach($recentCustomers as $customer)
            @php
                $photoUrl = null;
                if ($customer->photo) {
                    $p = $customer->photo;
                    $photoUrl = str_starts_with($p, 'http') ? $p : $happyestPublic . '/storage/' . $p;
                } elseif ($customer->avatar && $customer->provider_id) {
                    $av = $customer->avatar;
                    $photoUrl = str_starts_with($av, 'http') ? $av : $happyestPublic . '/storage/' . $av;
                }
                $fullName = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')) ?: '—';
                $initial  = mb_strtoupper(mb_substr($fullName, 0, 1));

                $statusMap = [
                    'checked_in'        => ['label' => 'ไม่ว่าง',      'bg' => 'bg-red-50',     'text' => 'text-red-700'],
                    'confirmed'         => ['label' => 'ยืนยันแล้ว',   'bg' => 'bg-red-50',     'text' => 'text-red-700'],
                    'deposit_confirmed' => ['label' => 'โครงการในอนาคต', 'bg' => 'bg-blue-50',    'text' => 'text-blue-700'],
                    'pending'           => ['label' => 'จองแล้ว',      'bg' => 'bg-yellow-50',  'text' => 'text-yellow-700'],
                    'checked_out'       => ['label' => 'ออกแล้ว',      'bg' => 'bg-gray-50',    'text' => 'text-gray-500'],
                    'completed'         => ['label' => 'เสร็จสิ้น',    'bg' => 'bg-gray-50',    'text' => 'text-gray-500'],
                    'cancelled'         => ['label' => 'ยกเลิก',       'bg' => 'bg-red-50',     'text' => 'text-red-600'],
                    'rejected'          => ['label' => 'ปฏิเสธ',       'bg' => 'bg-gray-50',    'text' => 'text-gray-500'],
                ];
                $st = $statusMap[$customer->booking_status] ?? ['label' => $customer->booking_status, 'bg' => 'bg-gray-50', 'text' => 'text-gray-500'];
            @endphp
            <div class="flex items-center gap-3 px-4 sm:px-5 py-3 hover:bg-gray-50/60 transition-colors">
                @if($photoUrl)
                    <img src="{{ $photoUrl }}" alt="{{ $fullName }}"
                         class="w-9 h-9 rounded-full object-cover flex-shrink-0 ring-2 ring-gray-100"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                    <div class="w-9 h-9 rounded-full bg-brand-600 items-center justify-center flex-shrink-0 hidden">
                        <span class="text-white text-xs font-bold">{{ $initial }}</span>
                    </div>
                @else
                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-brand-500 to-brand-700
                                flex items-center justify-center flex-shrink-0">
                        <span class="text-white text-xs font-bold leading-none">{{ $initial }}</span>
                    </div>
                @endif
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-800 truncate">{{ $fullName }}</p>
                    <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                        @if($customer->mobile)
                            <span class="text-[11px] text-gray-400">{{ $customer->mobile }}</span>
                        @endif
                        <span class="font-mono text-[10px] text-brand-600 bg-brand-50 px-1.5 py-0.5 rounded-md">
                            {{ $customer->booking_code ?? '—' }}
                        </span>
                    </div>
                </div>
                <div class="flex-shrink-0 text-right">
                    <span class="text-[10px] font-semibold {{ $st['text'] }} {{ $st['bg'] }} px-2 py-0.5 rounded-full whitespace-nowrap">
                        {{ $st['label'] }}
                    </span>
                    @if($customer->monthly_rent)
                        <p class="text-xs font-bold text-gray-700 tabular-nums mt-0.5">
                            {{ number_format($customer->monthly_rent, 0) }}<span class="font-normal text-gray-400">฿</span>
                        </p>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- ออโตเมชั่น --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-4 sm:px-5 py-4 border-b border-gray-50 flex items-center justify-between gap-2">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="w-9 h-9 rounded-xl bg-violet-600 bg-gradient-to-br from-violet-500 to-purple-700
                            flex items-center justify-center shadow-sm flex-shrink-0">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <h3 class="text-sm font-bold text-gray-800 truncate">ออโตเมชั่น</h3>
                    <p class="text-xs text-gray-400 truncate">รายการใกล้ถึงกำหนด 14 วัน</p>
                </div>
            </div>
            @if($upcomingDues->count() > 0)
                <span class="flex-shrink-0 inline-flex items-center gap-1.5 text-xs font-bold text-violet-700 bg-violet-50 border border-violet-100 px-2.5 py-1 rounded-full">
                    <span class="w-1.5 h-1.5 rounded-full bg-violet-400 animate-pulse"></span>
                    {{ $upcomingDues->count() }} รายการ
                </span>
            @endif
        </div>

        @if($upcomingDues->isEmpty() && $overdueCount === 0)
        <div class="py-12 text-center">
            <div class="w-12 h-12 bg-emerald-50 rounded-xl flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <p class="text-sm font-semibold text-gray-700">ทุกอย่างเรียบร้อย</p>
            <p class="text-xs text-gray-400 mt-1">ไม่มีรายการที่ต้องดำเนินการ</p>
        </div>
        @else
        <div class="divide-y divide-gray-50">

            @if($overdueCount > 0)
            <div class="flex items-center gap-3 px-4 sm:px-5 py-3.5 bg-red-50/50">
                <div class="w-8 h-8 rounded-xl bg-red-100 flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.07 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-bold text-red-700">ค้างชำระเลยกำหนด</p>
                    <p class="text-xs text-red-400">{{ $overdueCount }} รายการที่เลยวันครบกำหนดแล้ว</p>
                </div>
                <span class="text-xs font-black text-red-600 bg-red-100 px-2.5 py-1 rounded-xl tabular-nums">
                    {{ $overdueCount }}
                </span>
            </div>
            @endif

            @foreach($upcomingDues as $due)
            @php
                $dueDate  = \Carbon\Carbon::parse($due->due_date);
                $daysLeft = (int) now()->startOfDay()->diffInDays($dueDate->copy()->startOfDay(), false);
                $fullName = trim(($due->first_name ?? '') . ' ' . ($due->last_name ?? '')) ?: '—';

                $typeMap = [
                    'deposit'        => ['label' => 'มัดจำ',       'bg' => 'bg-violet-50', 'text' => 'text-violet-700'],
                    'monthly_rent'   => ['label' => 'ค่าเช่า',     'bg' => 'bg-blue-50',   'text' => 'text-blue-700'],
                    'processing_fee' => ['label' => 'ค่าดำเนินการ','bg' => 'bg-teal-50',   'text' => 'text-teal-700'],
                    'late_fee'       => ['label' => 'ค่าปรับ',     'bg' => 'bg-red-50',    'text' => 'text-red-700'],
                ];
                $type = $typeMap[strtolower($due->payment_type)] ?? ['label' => $due->payment_type, 'bg' => 'bg-gray-50', 'text' => 'text-gray-600'];

                if ($daysLeft <= 2) {
                    $dotColor   = 'bg-red-500';
                    $badgeCls   = 'bg-red-50 text-red-600';
                    $daysLabel  = $daysLeft === 0 ? 'วันนี้' : $daysLeft . 'วัน';
                } elseif ($daysLeft <= 5) {
                    $dotColor   = 'bg-amber-500';
                    $badgeCls   = 'bg-amber-50 text-amber-600';
                    $daysLabel  = $daysLeft . 'วัน';
                } else {
                    $dotColor   = 'bg-blue-400';
                    $badgeCls   = 'bg-blue-50 text-blue-600';
                    $daysLabel  = $daysLeft . 'วัน';
                }
            @endphp
            <div class="flex items-center gap-3 px-4 sm:px-5 py-3 hover:bg-gray-50/60 transition-colors">
                <span class="w-2 h-2 rounded-full flex-shrink-0 {{ $dotColor }}"></span>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-0.5 flex-wrap">
                        <p class="text-sm font-semibold text-gray-800 truncate">{{ $fullName }}</p>
                        <span class="text-[10px] font-semibold {{ $type['text'] }} {{ $type['bg'] }} px-1.5 py-0.5 rounded-md flex-shrink-0">
                            {{ $type['label'] }}
                        </span>
                    </div>
                    <p class="text-[11px] text-gray-400">
                        {{ $due->booking_code ?? '—' }} ·
                        {{ $dueDate->day . ' ' . $thM[$dueDate->month - 1] . ' ' . ($dueDate->year + 543) }}
                    </p>
                </div>
                <div class="flex-shrink-0 text-right">
                    <p class="text-sm font-black text-gray-800 tabular-nums">
                        {{ number_format((float)$due->amount, 0) }}<span class="text-xs font-normal text-gray-400">฿</span>
                    </p>
                    <span class="text-[10px] font-bold {{ $badgeCls }} px-1.5 py-0.5 rounded-md">{{ $daysLabel }}</span>
                </div>
            </div>
            @endforeach

        </div>

        <div class="px-4 sm:px-5 py-3 border-t border-gray-50">
            <p class="text-[11px] text-gray-400 text-center">ระบบแสดงรายการที่ต้องดำเนินการภายใน 14 วัน</p>
        </div>
        @endif
    </div>

</div>

{{-- ── สลิปรออนุมัติ ─────────────────────────────────────────────────────── --}}
@if($recentSlips->count() > 0)
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

    <div class="px-4 sm:px-5 py-4 border-b border-gray-50 flex items-center justify-between gap-2">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-9 h-9 rounded-xl bg-amber-500 bg-gradient-to-br from-amber-400 to-orange-500
                        flex items-center justify-center shadow-sm flex-shrink-0">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
            </div>
            <div class="min-w-0">
                <h3 class="text-sm font-bold text-gray-800 truncate">สลิปรออนุมัติ</h3>
                <p class="text-xs text-gray-400 truncate">รายการที่ยังรอการยืนยัน</p>
            </div>
        </div>
        <div class="flex-shrink-0 inline-flex items-center gap-1.5 bg-amber-50 border border-amber-100 text-amber-700
                    text-xs font-bold px-3 py-1.5 rounded-full">
            <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
            {{ $pendingVerificationCount }} รายการ
        </div>
    </div>

    {{-- Desktop Table --}}
    <div class="hidden md:block overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50/60">
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider whitespace-nowrap">รหัสการจอง</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">ชื่อลูกค้า</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">ประเภท</th>
                    <th class="text-right px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider whitespace-nowrap">จำนวนเงิน</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider whitespace-nowrap">อัพโหลดเมื่อ</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recentSlips as $slip)
                @php
                    $typeMap2 = [
                        'deposit'        => ['label' => 'เงินมัดจำ',     'bg' => 'bg-violet-50', 'text' => 'text-violet-700'],
                        'monthly_rent'   => ['label' => 'ค่าเช่า',        'bg' => 'bg-blue-50',   'text' => 'text-blue-700'],
                        'processing_fee' => ['label' => 'ค่าดำเนินการ',   'bg' => 'bg-teal-50',   'text' => 'text-teal-700'],
                        'late_fee'       => ['label' => 'ค่าปรับ',         'bg' => 'bg-red-50',    'text' => 'text-red-700'],
                    ];
                    $slipType = $typeMap2[strtolower($slip->payment_type)] ?? ['label' => $slip->payment_type, 'bg' => 'bg-gray-50', 'text' => 'text-gray-600'];
                    $ca = \Carbon\Carbon::parse($slip->created_at);
                    $dateStr = $ca->day . ' ' . $thM[$ca->month - 1] . ' ' . ($ca->year + 543) . ' ' . $ca->format('H:i') . ' น.';
                @endphp
                <tr class="border-t border-gray-100 hover:bg-amber-50/30 transition-colors">
                    <td class="px-5 py-3.5">
                        <span class="inline-flex items-center gap-1 font-mono text-xs font-bold
                                     text-brand-700 bg-brand-50 border border-brand-100 px-2.5 py-1 rounded-lg">
                            {{ $slip->booking_code ?? '—' }}
                        </span>
                    </td>
                    <td class="px-5 py-3.5">
                        <span class="text-sm font-medium text-gray-800">
                            {{ trim(($slip->first_name ?? '') . ' ' . ($slip->last_name ?? '')) ?: '—' }}
                        </span>
                    </td>
                    <td class="px-5 py-3.5">
                        <span class="text-xs font-semibold px-2.5 py-1 rounded-full {{ $slipType['bg'] }} {{ $slipType['text'] }}">
                            {{ $slipType['label'] }}
                        </span>
                    </td>
                    <td class="px-5 py-3.5 text-right">
                        <span class="text-sm font-extrabold text-gray-900 tabular-nums">{{ number_format((float)$slip->amount, 0) }}</span>
                        <span class="text-xs text-gray-400 ml-0.5">บาท</span>
                    </td>
                    <td class="px-5 py-3.5 text-xs text-gray-500 whitespace-nowrap">{{ $dateStr }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Mobile Cards --}}
    <div class="md:hidden divide-y divide-gray-100">
        @foreach($recentSlips as $slip)
        @php
            $typeMap2 = [
                'deposit'        => ['label' => 'เงินมัดจำ',   'bg' => 'bg-violet-50', 'text' => 'text-violet-700'],
                'monthly_rent'   => ['label' => 'ค่าเช่า',      'bg' => 'bg-blue-50',   'text' => 'text-blue-700'],
                'processing_fee' => ['label' => 'ค่าดำเนินการ', 'bg' => 'bg-teal-50',   'text' => 'text-teal-700'],
                'late_fee'       => ['label' => 'ค่าปรับ',       'bg' => 'bg-red-50',    'text' => 'text-red-700'],
            ];
            $slipType = $typeMap2[strtolower($slip->payment_type)] ?? ['label' => $slip->payment_type, 'bg' => 'bg-gray-50', 'text' => 'text-gray-600'];
            $ca = \Carbon\Carbon::parse($slip->created_at);
            $dateStr = $ca->day . ' ' . $thM[$ca->month - 1] . ' ' . ($ca->year + 543);
        @endphp
        <div class="px-4 py-4">
            <div class="flex items-center justify-between gap-2 mb-2">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="font-mono text-xs font-bold text-brand-700 bg-brand-50 border border-brand-100 px-2 py-0.5 rounded-lg">
                        {{ $slip->booking_code ?? '—' }}
                    </span>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $slipType['bg'] }} {{ $slipType['text'] }}">
                        {{ $slipType['label'] }}
                    </span>
                </div>
                <span class="text-xs text-gray-400 flex-shrink-0">{{ $dateStr }}</span>
            </div>
            <div class="flex items-center justify-between">
                <p class="text-sm text-gray-700 font-medium">
                    {{ trim(($slip->first_name ?? '') . ' ' . ($slip->last_name ?? '')) ?: '—' }}
                </p>
                <p class="text-sm font-extrabold text-gray-900 tabular-nums">
                    {{ number_format((float)$slip->amount, 0) }}<span class="text-xs font-normal text-gray-400"> บาท</span>
                </p>
            </div>
        </div>
        @endforeach
    </div>

</div>
@endif

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
    // คัดลอกลิงก์แอด (ผูก ref=agent_code)
    function copyAdLink(url) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(url).then(() => Toast.success('คัดลอกลิงก์แอดแล้ว'));
        } else {
            const el = document.createElement('textarea');
            el.value = url; el.style.cssText = 'position:fixed;opacity:0';
            document.body.appendChild(el); el.focus(); el.select();
            document.execCommand('copy'); document.body.removeChild(el);
            Toast.success('คัดลอกลิงก์แอดแล้ว');
        }
    }
</script>
<script>
(function () {
    Chart.defaults.font.family = 'inherit';

    // ── Tooltip defaults ────────────────────────────────────────────────────────
    const tooltipDefaults = {
        backgroundColor: '#1c3514',
        titleColor: '#9ad872',
        bodyColor: '#fff',
        padding: 12,
        cornerRadius: 10,
    };

    // ── Bar Chart ───────────────────────────────────────────────────────────────
    const barCanvas = document.getElementById('barChart');
    if (barCanvas) {
        const ctx = barCanvas.getContext('2d');
        const grad = ctx.createLinearGradient(0, 0, 0, 210);
        grad.addColorStop(0, 'rgba(70,132,50,0.65)');
        grad.addColorStop(1, 'rgba(70,132,50,0.04)');

        new Chart(barCanvas, {
            type: 'bar',
            data: {
                labels: @json($chartMonths),
                datasets: [{
                    label: 'ยอดชำระ',
                    data: @json($chartAmounts),
                    backgroundColor: grad,
                    borderColor: 'rgba(70,132,50,0.9)',
                    borderWidth: 2,
                    borderRadius: 10,
                    borderSkipped: false,
                    hoverBackgroundColor: 'rgba(70,132,50,0.80)',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        ...tooltipDefaults,
                        callbacks: { label: ctx => '  ' + Number(ctx.raw).toLocaleString('th-TH') + ' บาท' }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        border: { display: false },
                        ticks: { font: { size: 11, weight: '500' }, color: '#9CA3AF' }
                    },
                    y: {
                        grid: { color: '#F3F4F6' },
                        border: { display: false, dash: [4, 4] },
                        ticks: {
                            font: { size: 11 }, color: '#9CA3AF', maxTicksLimit: 5,
                            callback: v =>
                                v >= 1000000 ? (v/1000000).toFixed(1)+'ล.' :
                                v >= 1000    ? (v/1000).toFixed(0)+'K' : v
                        }
                    }
                }
            }
        });
    }

    // ── Donut helper ────────────────────────────────────────────────────────────
    function makeMiniDonut(canvasId, labels, counts, colors, centerLabel) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;
        const total = counts.reduce((a, b) => a + b, 0);
        new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{ data: counts, backgroundColor: colors, borderColor: '#fff', borderWidth: 3, hoverOffset: 6 }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                animation: { animateRotate: true, duration: 800, easing: 'easeOutQuart' },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        ...tooltipDefaults,
                        callbacks: {
                            label: ctx => {
                                const pct = total > 0 ? ((ctx.raw / total) * 100).toFixed(1) : 0;
                                return '  ' + ctx.label + ': ' + Number(ctx.raw).toLocaleString('th-TH') + ' (' + pct + '%)';
                            }
                        }
                    }
                }
            },
            plugins: [{
                id: 'center_' + canvasId,
                afterDraw(chart) {
                    const { ctx, chartArea: { left, right, top, bottom } } = chart;
                    const cx = (left + right) / 2, cy = (top + bottom) / 2;
                    ctx.save();
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    ctx.font = 'bold 18px inherit';
                    ctx.fillStyle = '#111827';
                    ctx.fillText(Number(total).toLocaleString('th-TH'), cx, cy - 8);
                    ctx.font = '500 10px inherit';
                    ctx.fillStyle = '#9CA3AF';
                    ctx.fillText(centerLabel, cx, cy + 10);
                    ctx.restore();
                }
            }]
        });
    }

    makeMiniDonut(
        'donutChart',
        @json($donutLabels),
        @json($donutCounts),
        @json($donutColors),
        'รายการ'
    );

    makeMiniDonut(
        'bookingDonut',
        @json($bookingStatusLabels),
        @json($bookingStatusCounts),
        @json($bookingStatusColors),
        'การจอง'
    );

})();
</script>
@endpush
