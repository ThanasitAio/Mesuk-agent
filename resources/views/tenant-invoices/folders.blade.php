@extends('layouts.app')

@section('title', 'ใบแจ้งหนี้ผู้เช่า')
@section('breadcrumb', 'ใบแจ้งหนี้ผู้เช่าตามเดือนที่ออก')

@section('content')

{{-- ── Hero Header ─────────────────────────────────────────────────────────── --}}
<div class="relative overflow-hidden rounded-2xl mb-6 p-4 sm:p-5 lg:p-6"
     style="background: linear-gradient(135deg, #1c3514 0%, #2a4f1f 45%, #1c3514 100%);">

    <div class="hero-shimmer-bar"></div>
    <div class="hero-glow" style="width:220px;height:220px;top:-60px;right:-60px;background:rgba(154,216,114,0.12);"></div>
    <div class="hero-glow" style="width:140px;height:140px;bottom:-40px;left:30%;background:rgba(70,132,50,0.10);animation-delay:2s;"></div>
    <div class="hero-blob-1 pointer-events-none absolute -top-10 -right-10 w-44 h-44 rounded-full bg-brand-700 opacity-30"></div>
    <div class="hero-blob-2 pointer-events-none absolute top-2 right-14 w-24 h-24 rounded-full bg-brand-600 opacity-20"></div>
    <div class="hero-blob-3 pointer-events-none absolute -bottom-8 right-4 w-32 h-32 rounded-full bg-brand-800 opacity-40"></div>

    <div class="relative flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 sm:gap-4" style="z-index:2;">
        <div class="min-w-0">
            <p class="text-xs font-medium mb-1" style="color: rgba(255,255,255,0.6)">ทรัพย์สินในความดูแลของคุณ</p>
            <h2 class="text-xl lg:text-2xl font-black text-white leading-tight truncate">ใบแจ้งหนี้ผู้เช่า</h2>
            <p class="text-sm mt-1.5" style="color: rgba(255,255,255,0.65)">
                เลือกเดือนที่ต้องการดู แล้วดาวน์โหลดใบแจ้งหนี้ของผู้เช่าในทรัพย์ที่คุณดูแลได้ทันที
            </p>
        </div>
        <div class="flex-shrink-0">
            <span class="inline-flex items-center gap-1.5 rounded-xl border px-3 py-1.5 text-xs font-bold text-white"
                  style="background:rgba(255,255,255,0.12); border-color:rgba(255,255,255,0.2); backdrop-filter:blur(8px);">
                {{ $months->count() }} เดือน
            </span>
        </div>
    </div>
</div>

@if($months->isEmpty())
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-10 text-center">
        <div class="w-12 h-12 bg-gray-50 rounded-xl flex items-center justify-center mx-auto mb-3">
            <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
        </div>
        <p class="text-gray-700 font-semibold text-sm">ยังไม่มีใบแจ้งหนี้สำหรับทรัพย์ที่คุณดูแล</p>
        <p class="text-gray-400 text-xs mt-1">เมื่อแอดมินออกใบแจ้งหนี้ให้ผู้เช่าแล้ว จะแสดงเป็นโฟลเดอร์ตามเดือนที่นี่</p>
    </div>
@else
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
        @foreach($months as $i => $m)
            <a href="{{ route('tenant-invoices.show', $m['month']) }}"
               class="group relative flex flex-col gap-2.5 bg-white border border-gray-100 rounded-2xl p-3.5 shadow-sm hover:shadow-lg hover:-translate-y-0.5 hover:border-brand-200 transition-all duration-300">
                <div class="flex items-center justify-between gap-1.5">
                    <span class="w-9 h-9 rounded-xl bg-brand-50 text-brand-600 flex items-center justify-center flex-shrink-0 transition-colors group-hover:bg-brand-600 group-hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                        </svg>
                    </span>
                    @if($i === 0)
                        <span class="inline-flex items-center gap-1 text-[10px] font-bold text-white bg-amber-500 rounded-full px-2 py-0.5 whitespace-nowrap">ล่าสุด</span>
                    @endif
                </div>
                <div class="font-bold text-sm text-gray-800 leading-tight">{{ $m['label'] }}</div>
                <div class="text-xs font-semibold text-gray-400">{{ $m['invoice_count'] }} รายการ</div>
            </a>
        @endforeach
    </div>
@endif

@endsection
