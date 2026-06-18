@extends('layouts.app')

@section('title', 'ตัวแทน')
@section('breadcrumb', 'จัดการสมาชิกตัวแทน')

@section('content')

{{-- Header Bar --}}
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
    <h2 class="text-lg font-semibold text-gray-800">รายชื่อตัวแทน</h2>
    <x-btn href="{{ route('agents.create') }}" variant="primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
        </svg>
        เพิ่มตัวแทนใหม่
    </x-btn>
</div>

{{-- Search & Filter --}}
<x-card class="p-4 mb-5">
    <form method="GET" action="{{ route('agents.index') }}">
        <div class="flex flex-col sm:flex-row gap-3">
            {{-- Search with icon --}}
            <div class="flex-1 relative">
                <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text"
                       name="search"
                       value="{{ request('search') }}"
                       placeholder="ค้นหาชื่อ, อีเมล, เบอร์โทร หรือรหัสตัวแทน..."
                       class="w-full pl-9 pr-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent">
            </div>

            {{-- Status select --}}
            <div class="sm:w-40">
                <x-form.select name="status">
                    <option value="">ทุกสถานะ</option>
                    <option value="active"   @selected(request('status') === 'active')>ใช้งาน</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>ไม่ใช้งาน</option>
                </x-form.select>
            </div>

            {{-- Buttons --}}
            <div class="flex gap-2">
                <button type="submit"
                        class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    ค้นหา
                </button>
                @if(request('search') || request('status'))
                    <a href="{{ route('agents.index') }}"
                       class="inline-flex items-center gap-1 bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm font-medium px-4 py-2.5 rounded-xl transition-colors">
                        ล้าง
                    </a>
                @endif
            </div>
        </div>
    </form>
</x-card>

{{-- ============================================================ --}}
{{-- DESKTOP TABLE (md and up) --}}
{{-- ============================================================ --}}
<x-table>
    <x-slot:head>
        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">#</th>
        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">รหัส</th>
        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">ชื่อ</th>
        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">อีเมล</th>
        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">เบอร์โทร</th>
        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">จังหวัด</th>
        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">สถานะ</th>
        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">จัดการ</th>
    </x-slot:head>

    @forelse($agents as $agent)
        <tr class="hover:bg-gray-50 transition-colors">
            <td class="px-5 py-3.5 text-gray-400 text-xs">{{ $agents->firstItem() + $loop->index }}</td>
            <td class="px-5 py-3.5">
                <span class="font-mono text-xs bg-gray-100 text-gray-700 px-2 py-0.5 rounded">{{ $agent->member_code }}</span>
            </td>
            <td class="px-5 py-3.5 font-medium text-gray-800">{{ $agent->name }}</td>
            <td class="px-5 py-3.5 text-gray-600">{{ $agent->email }}</td>
            <td class="px-5 py-3.5 text-gray-600">{{ $agent->phone }}</td>
            <td class="px-5 py-3.5 text-gray-500 text-xs">{{ $agent->province ?: '—' }}</td>
            <td class="px-5 py-3.5">
                <x-status-badge :status="$agent->status" />
            </td>
            <td class="px-5 py-3.5">
                <div class="flex items-center gap-2">
                    <x-btn href="{{ route('agents.edit', $agent) }}" size="sm" variant="amber-ghost">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        แก้ไข
                    </x-btn>
                    <form action="{{ route('agents.destroy', $agent) }}" method="POST"
                          class="contents"
                          onsubmit="return confirm('ลบตัวแทน {{ addslashes($agent->name) }}? ไม่สามารถกู้คืนได้')">
                        @csrf
                        @method('DELETE')
                        <x-btn type="submit" size="sm" variant="danger">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            ลบ
                        </x-btn>
                    </form>
                </div>
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="8" class="px-5 py-14 text-center">
                <div class="flex flex-col items-center gap-2 text-gray-400">
                    <svg class="w-10 h-10 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <p class="text-sm font-medium">ไม่พบตัวแทน</p>
                    <p class="text-xs">ลองเปลี่ยนเงื่อนไขค้นหา หรือ <a href="{{ route('agents.create') }}" class="text-brand-500 hover:underline">เพิ่มตัวแทนใหม่</a></p>
                </div>
            </td>
        </tr>
    @endforelse
</x-table>

{{-- ============================================================ --}}
{{-- MOBILE CARD LIST (below md) --}}
{{-- ============================================================ --}}
<div class="md:hidden space-y-3">
    @forelse($agents as $agent)
        <x-card class="p-4">
            <div class="flex items-start justify-between gap-2 mb-3">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 bg-brand-100 rounded-full flex items-center justify-center text-brand-600 font-bold text-sm flex-shrink-0">
                        {{ strtoupper(substr($agent->name, 0, 1)) }}
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800 text-sm">{{ $agent->name }}</p>
                        <p class="text-xs text-gray-400 font-mono mt-0.5">{{ $agent->member_code }}</p>
                    </div>
                </div>
                <x-status-badge :status="$agent->status" class="flex-shrink-0" />
            </div>

            <div class="space-y-1.5 mb-4">
                <a href="mailto:{{ $agent->email }}" class="flex items-center gap-2 text-sm text-gray-600 hover:text-brand-600 transition-colors">
                    <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    <span class="truncate">{{ $agent->email }}</span>
                </a>
                <a href="tel:{{ $agent->phone }}" class="flex items-center gap-2 text-sm text-gray-600 hover:text-brand-600 transition-colors">
                    <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                    </svg>
                    {{ $agent->phone }}
                </a>
                @if($agent->province)
                    <div class="flex items-center gap-2 text-sm text-gray-500">
                        <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        {{ $agent->province }}@if($agent->zipcode), {{ $agent->zipcode }}@endif
                    </div>
                @endif
            </div>

            <div class="flex gap-2 pt-3 border-t border-gray-100">
                <x-btn href="{{ route('agents.edit', $agent) }}" variant="amber-ghost" size="sm" class="flex-1 py-2.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    แก้ไข
                </x-btn>
                <form action="{{ route('agents.destroy', $agent) }}" method="POST" class="flex-1"
                      onsubmit="return confirm('ลบ {{ addslashes($agent->name) }}? ไม่สามารถกู้คืนได้')">
                    @csrf
                    @method('DELETE')
                    <x-btn type="submit" variant="danger" size="sm" class="w-full py-2.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        ลบ
                    </x-btn>
                </form>
            </div>
        </x-card>
    @empty
        <x-card class="p-10 text-center">
            <svg class="w-10 h-10 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <p class="text-sm text-gray-400">ไม่พบตัวแทน</p>
        </x-card>
    @endforelse
</div>

{{-- Pagination --}}
<x-pagination :paginator="$agents" label="รายการ">
    @if(request('search')) สำหรับ "<strong>{{ request('search') }}</strong>" @endif
</x-pagination>

@endsection