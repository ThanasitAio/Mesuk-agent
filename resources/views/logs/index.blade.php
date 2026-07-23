@extends('layouts.app')

@section('title', 'ประวัติการใช้งาน')
@section('breadcrumb', 'บันทึกการดำเนินการทั้งหมด')

@section('content')

{{-- Filter Form --}}
<x-card class="mb-5">

    {{-- Mobile: collapsible toggle --}}
    <button type="button"
            onclick="toggleFilter()"
            class="lg:hidden w-full flex items-center justify-between px-4 py-3.5 text-sm font-semibold text-gray-700 tap-effect">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-brand-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
            </svg>
            กรองข้อมูล
            @if(request('search') || request('module') || request('action') || request('date_from') || request('date_to'))
                <span class="bg-brand-600 text-white text-xs font-bold px-1.5 py-0.5 rounded-full">!</span>
            @endif
        </div>
        <svg id="filter-chevron" class="w-4 h-4 text-gray-400 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <div id="filter-body" class="hidden lg:block p-4 border-t border-gray-100 lg:border-0">
        <form method="GET" action="{{ route('logs.index') }}">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-3">

            {{-- Search --}}
            <div class="relative sm:col-span-2 lg:col-span-1">
                <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text"
                       name="search"
                       value="{{ request('search') }}"
                       placeholder="ค้นหารายละเอียด, IP..."
                       class="w-full pl-9 pr-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent">
            </div>

            {{-- Module --}}
            <div>
                <x-form.select name="module">
                    <option value="">ทุกโมดูล</option>
                    @foreach($modules as $mod)
                        <option value="{{ $mod }}" @selected(request('module') === $mod)>{{ $mod }}</option>
                    @endforeach
                </x-form.select>
            </div>

            {{-- Action --}}
            <div>
                <x-form.select name="action">
                    <option value="">ทุกการกระทำ</option>
                    @foreach($actions as $act)
                        <option value="{{ $act }}" @selected(request('action') === $act)>{{ $act }}</option>
                    @endforeach
                </x-form.select>
            </div>

            {{-- Date From --}}
            <div>
                <x-form.date name="date_from" :value="request('date_from')" />
            </div>

            {{-- Date To + Buttons --}}
            <div class="flex gap-2">
                <div class="flex-1 min-w-0">
                    <x-form.date name="date_to" :value="request('date_to')" />
                </div>
                <button type="submit"
                        class="flex-shrink-0 bg-brand-600 hover:bg-brand-700 text-white px-3 py-2.5 rounded-xl transition-colors" title="กรอง">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                    </svg>
                </button>
                @if(request('search') || request('module') || request('action') || request('date_from') || request('date_to'))
                    <a href="{{ route('logs.index') }}"
                       class="flex-shrink-0 bg-gray-100 hover:bg-gray-200 text-gray-600 px-3 py-2.5 rounded-xl transition-colors" title="ล้างตัวกรอง">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </a>
                @endif
            </div>
        </div>
    </form>
    </div>
</x-card>

@push('scripts')
<script>
    function toggleFilter() {
        const body = document.getElementById('filter-body');
        const chevron = document.getElementById('filter-chevron');
        body.classList.toggle('hidden');
        chevron.style.transform = body.classList.contains('hidden') ? '' : 'rotate(180deg)';
    }
    @if(request('search') || request('module') || request('action') || request('date_from') || request('date_to'))
        document.addEventListener('DOMContentLoaded', function() {
            const body = document.getElementById('filter-body');
            const chevron = document.getElementById('filter-chevron');
            if (body) { body.classList.remove('hidden'); chevron.style.transform = 'rotate(180deg)'; }
        });
    @endif
</script>
@endpush

{{-- ============================================================ --}}
{{-- DESKTOP TABLE --}}
{{-- ============================================================ --}}
<x-table>
    <x-slot:head>
        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">เวลา</th>
        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">ผู้ใช้</th>
        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">โมดูล</th>
        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">การกระทำ</th>
        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">รายละเอียด</th>
        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">วิธี</th>
        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">IP</th>
    </x-slot:head>

    @forelse($logs as $log)
        <tr class="hover:bg-gray-50 transition-colors">
            <td class="px-4 py-3 text-gray-500 text-xs whitespace-nowrap">
                {{ $log->created_at ? $log->created_at->format('d/m/Y') : '-' }}<br>
                <span class="text-gray-400">{{ $log->created_at ? $log->created_at->format('H:i:s') : '' }}</span>
            </td>
            <td class="px-4 py-3">
                @if($log->user_id)
                    <span class="text-xs text-gray-600">#{{ $log->user_id }}</span>
                    <span class="text-xs text-gray-400 block capitalize">{{ $log->user_type }}</span>
                @else
                    <span class="text-xs text-gray-400">-</span>
                @endif
            </td>
            <td class="px-4 py-3">
                <span class="inline-block bg-brand-100 text-brand-700 text-xs font-medium px-2 py-0.5 rounded-full whitespace-nowrap">
                    {{ $log->module ?? '-' }}
                </span>
            </td>
            <td class="px-4 py-3">
                <x-log-action-badge :action="$log->action" />
            </td>
            <td class="px-4 py-3 text-gray-600 max-w-xs">
                <span class="line-clamp-2 text-xs">{{ $log->description ?? '-' }}</span>
            </td>
            <td class="px-4 py-3">
                <span class="inline-block bg-gray-100 text-gray-600 text-xs font-mono px-2 py-0.5 rounded">
                    {{ $log->method ?? '-' }}
                </span>
            </td>
            <td class="px-4 py-3 text-xs text-gray-500 font-mono">{{ $log->ip_address ?? '-' }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="7" class="px-4 py-14 text-center">
                <div class="flex flex-col items-center gap-2 text-gray-400">
                    <svg class="w-10 h-10 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p class="text-sm">ไม่พบข้อมูลที่ตรงกับเงื่อนไข</p>
                </div>
            </td>
        </tr>
    @endforelse
</x-table>

{{-- ============================================================ --}}
{{-- MOBILE CARDS --}}
{{-- ============================================================ --}}
<div class="md:hidden space-y-3">
    @forelse($logs as $log)
        <x-card class="p-4">
            {{-- Header row --}}
            <div class="flex items-start justify-between gap-2 mb-2">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="inline-block bg-brand-100 text-brand-700 text-xs font-medium px-2 py-0.5 rounded-full">
                        {{ $log->module ?? '-' }}
                    </span>
                    <x-log-action-badge :action="$log->action" />
                </div>
                <span class="text-xs text-gray-400 whitespace-nowrap flex-shrink-0">
                    {{ $log->created_at ? $log->created_at->format('d/m/y H:i') : '-' }}
                </span>
            </div>

            {{-- Description --}}
            <p class="text-sm text-gray-700 mb-2">{{ $log->description ?? '-' }}</p>

            {{-- Meta row --}}
            <div class="flex items-center gap-3 text-xs text-gray-400 flex-wrap">
                @if($log->user_id)
                    <span class="flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        {{ ucfirst($log->user_type) }} #{{ $log->user_id }}
                    </span>
                @endif
                @if($log->ip_address)
                    <span class="font-mono">{{ $log->ip_address }}</span>
                @endif
                @if($log->method)
                    <span class="bg-gray-100 px-1.5 py-0.5 rounded font-mono">{{ $log->method }}</span>
                @endif
            </div>
        </x-card>
    @empty
        <x-card class="p-10 text-center">
            <svg class="w-10 h-10 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <p class="text-sm text-gray-400">ไม่พบข้อมูลบันทึก</p>
        </x-card>
    @endforelse
</div>

{{-- Pagination --}}
<x-pagination :paginator="$logs" label="รายการ" />

@endsection