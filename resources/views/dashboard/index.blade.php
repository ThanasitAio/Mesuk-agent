@extends('layouts.app')

@section('title', 'หน้าหลัก')
@section('breadcrumb', 'ภาพรวมและสรุป')

@section('content')

{{-- Stats Cards --}}
<div class="grid grid-cols-1 gap-4 mb-6">

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