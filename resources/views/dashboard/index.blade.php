@extends('layouts.app')

@section('title', 'หน้าหลัก')
@section('breadcrumb', 'ภาพรวมและสรุป')

@section('content')

{{-- Stats Cards --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

    <x-card class="p-5 flex items-center gap-4">
        <div class="w-12 h-12 bg-brand-100 rounded-xl flex items-center justify-center flex-shrink-0">
            <svg class="w-6 h-6 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
        </div>
        <div>
            <p class="text-2xl font-bold text-gray-800">{{ number_format($stats['total']) }}</p>
            <p class="text-sm text-gray-500">ตัวแทนทั้งหมด</p>
        </div>
    </x-card>

    <x-card class="p-5 flex items-center gap-4">
        <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center flex-shrink-0">
            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <div>
            <p class="text-2xl font-bold text-gray-800">{{ number_format($stats['active']) }}</p>
            <p class="text-sm text-gray-500">ตัวแทนที่ใช้งาน</p>
        </div>
    </x-card>

    <x-card class="p-5 flex items-center gap-4">
        <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center flex-shrink-0">
            <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
            </svg>
        </div>
        <div>
            <p class="text-2xl font-bold text-gray-800">{{ number_format($stats['inactive']) }}</p>
            <p class="text-sm text-gray-500">ตัวแทนที่ไม่ใช้งาน</p>
        </div>
    </x-card>

    <x-card class="p-5 flex items-center gap-4">
        <div class="w-12 h-12 bg-[#FFEF91] rounded-xl flex items-center justify-center flex-shrink-0">
            <svg class="w-6 h-6 text-[#FFA02E]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
        </div>
        <div>
            <p class="text-2xl font-bold text-gray-800">{{ number_format($stats['logs']) }}</p>
            <p class="text-sm text-gray-500">บันทึกระบบ</p>
        </div>
    </x-card>
</div>

{{-- Quick Actions --}}
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
    <a href="{{ route('agents.index') }}"
       class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-center gap-4 hover:border-brand-300 hover:shadow-md transition-all group">
        <div class="w-10 h-10 bg-brand-50 group-hover:bg-brand-100 rounded-lg flex items-center justify-center transition-colors">
            <svg class="w-5 h-5 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
            </svg>
        </div>
        <div>
            <p class="text-sm font-semibold text-gray-800">จัดการตัวแทน</p>
            <p class="text-xs text-gray-500">ดู เพิ่ม และจัดการตัวแทน</p>
        </div>
        <svg class="w-4 h-4 text-gray-400 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
    </a>

    <a href="{{ route('agents.create') }}"
       class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-center gap-4 hover:border-green-300 hover:shadow-md transition-all group">
        <div class="w-10 h-10 bg-green-50 group-hover:bg-green-100 rounded-lg flex items-center justify-center transition-colors">
            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
            </svg>
        </div>
        <div>
            <p class="text-sm font-semibold text-gray-800">เพิ่มตัวแทนใหม่</p>
            <p class="text-xs text-gray-500">ลงทะเบียนตัวแทนใหม่</p>
        </div>
        <svg class="w-4 h-4 text-gray-400 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
    </a>
</div>

{{-- Recent Activity Logs --}}
<x-card>
    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
        <h2 class="text-base font-semibold text-gray-800">กิจกรรมล่าสุด</h2>
        <a href="{{ route('logs.index') }}" class="text-xs text-brand-600 hover:text-brand-700 font-medium">ดูทั้งหมด →</a>
    </div>

    {{-- Desktop Table --}}
    <x-table :card="false">
        <x-slot:head>
            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">เวลา</th>
            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">โมดูล</th>
            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">การกระทำ</th>
            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">รายละเอียด</th>
            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">IP</th>
        </x-slot:head>

        @forelse($recentLogs as $log)
            <tr class="hover:bg-gray-50 transition-colors">
                <td class="px-5 py-3 text-gray-500 whitespace-nowrap text-xs">
                    {{ $log->created_at ? $log->created_at->format('d/m/y H:i') : '—' }}
                </td>
                <td class="px-5 py-3">
                    <span class="inline-block bg-brand-100 text-brand-700 text-xs font-medium px-2 py-0.5 rounded-full">
                        {{ $log->module ?? '—' }}
                    </span>
                </td>
                <td class="px-5 py-3">
                    <x-log-action-badge :action="$log->action" />
                </td>
                <td class="px-5 py-3 text-gray-600 max-w-xs truncate text-xs">{{ $log->description ?? '—' }}</td>
                <td class="px-5 py-3 text-gray-500 font-mono text-xs">{{ $log->ip_address ?? '—' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="px-5 py-10 text-center text-gray-400 text-sm">ยังไม่มีกิจกรรมใดๆ</td>
            </tr>
        @endforelse
    </x-table>

    {{-- Mobile Cards --}}
    <div class="md:hidden divide-y divide-gray-100">
        @forelse($recentLogs as $log)
            <div class="px-4 py-4">
                <div class="flex items-start justify-between gap-2 mb-2">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="inline-block bg-brand-100 text-brand-700 text-xs font-medium px-2 py-0.5 rounded-full">{{ $log->module ?? '—' }}</span>
                        <x-log-action-badge :action="$log->action" />
                    </div>
                    <span class="text-xs text-gray-400 whitespace-nowrap flex-shrink-0">
                        {{ $log->created_at ? $log->created_at->format('d/m/y H:i') : '—' }}
                    </span>
                </div>
                <p class="text-sm text-gray-600">{{ $log->description ?? '—' }}</p>
                <p class="text-xs text-gray-400 mt-1 font-mono">{{ $log->ip_address ?? '' }}</p>
            </div>
        @empty
            <div class="px-4 py-10 text-center text-gray-400 text-sm">ยังไม่มีกิจกรรมใดๆ</div>
        @endforelse
    </div>
</x-card>

@endsection