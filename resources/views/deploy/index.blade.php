@extends('layouts.app')

@section('title', 'ระบบ')
@section('breadcrumb', 'จัดการระบบ - Cache · Migrations · ฟีเจอร์')

@section('content')

@php
$commandList = [
    'config:clear' => ['label' => 'Config Cache',      'desc' => 'ล้าง cache การตั้งค่าระบบ'],
    'route:clear'  => ['label' => 'Route Cache',       'desc' => 'ล้าง cache เส้นทาง URL'],
    'view:clear'   => ['label' => 'View Cache',        'desc' => 'ล้าง compiled Blade templates'],
    'cache:clear'  => ['label' => 'Application Cache', 'desc' => 'ล้าง cache ข้อมูลแอปพลิเคชัน'],
    'event:clear'  => ['label' => 'Event Cache',       'desc' => 'ล้าง cached event listeners'],
];
$cacheResults   = session('cache_results');
$pendingCount   = count($migrationStatus['pending'] ?? []);
$completedCount = count($migrationStatus['completed'] ?? []);
$hasMigError    = isset($migrationStatus['error']);
@endphp

{{-- ===== ROW 1: Cache + Migrations ===== --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 items-start">

    {{-- ─── Cache Clear ─── --}}
    <x-card class="overflow-hidden !p-0">

        <div class="px-5 py-3.5 border-b border-gray-100 flex items-center gap-2">
            <div class="w-8 h-8 bg-amber-100 rounded-lg flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
            </div>
            <div>
                <h2 class="text-sm font-semibold text-gray-800">ล้าง Cache ระบบ</h2>
                <p class="text-xs text-gray-400">php artisan optimize:clear</p>
            </div>
            @if($cacheResults !== null)
                <span class="ml-auto text-xs font-semibold px-2.5 py-1 rounded-full
                    {{ $cacheResults['success'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                    {{ $cacheResults['success'] ? 'สำเร็จ' : 'ล้มเหลว' }}
                </span>
            @endif
        </div>

        <div class="divide-y divide-gray-100">
            @foreach($commandList as $cmd => $info)
            <div class="flex items-center gap-3 px-5 py-2.5
                @if($cacheResults !== null)
                    {{ $cacheResults['success'] ? 'bg-green-50/40' : 'bg-red-50/40' }}
                @else
                    bg-white hover:bg-gray-50/80
                @endif transition-colors">

                <div class="w-5 h-5 rounded-md flex-shrink-0 flex items-center justify-center
                    @if($cacheResults !== null)
                        {{ $cacheResults['success'] ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-500' }}
                    @else
                        bg-gray-100 text-gray-400
                    @endif">
                    @if($cacheResults?->offsetGet('success') ?? ($cacheResults['success'] ?? null) === true)
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                    @elseif($cacheResults !== null && !$cacheResults['success'])
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    @else
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4"/>
                        </svg>
                    @endif
                </div>

                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium
                        @if($cacheResults !== null)
                            {{ $cacheResults['success'] ? 'text-green-800' : 'text-red-800' }}
                        @else
                            text-gray-700
                        @endif leading-tight">{{ $info['label'] }}</p>
                    <p class="text-xs text-gray-400 font-mono">{{ $cmd }}</p>
                </div>
            </div>
            @endforeach
        </div>

        <div class="px-5 py-4 bg-gray-50 border-t border-gray-100 space-y-3">

            @if($cacheResults !== null)
                <div class="flex items-center gap-3 p-3 rounded-xl
                    {{ $cacheResults['success'] ? 'bg-green-100 border border-green-200' : 'bg-red-50 border border-red-200' }}">
                    @if($cacheResults['success'])
                        <svg class="w-4 h-4 text-green-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                        <div class="min-w-0">
                            <p class="text-green-900 text-sm font-semibold">ล้าง Cache สำเร็จทุกรายการ</p>
                            <p class="text-green-700 text-xs">{{ $cacheResults['time'] }}</p>
                        </div>
                    @else
                        <svg class="w-4 h-4 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        <div class="min-w-0">
                            <p class="text-red-900 text-sm font-semibold">เกิดข้อผิดพลาด</p>
                            <p class="text-red-600 text-xs break-all">{{ $cacheResults['error'] ?? 'ล้มเหลว' }}</p>
                        </div>
                    @endif
                </div>
            @endif

            <form method="POST" action="{{ route('deploy.run') }}">
                @csrf
                <button type="submit"
                        id="run-btn"
                        onclick="return handleCacheSubmit()"
                        class="w-full flex items-center justify-center gap-2
                               bg-slate-800 hover:bg-slate-700 active:bg-slate-900
                               text-white font-semibold text-sm
                               py-2.5 px-4 rounded-xl transition-all shadow-sm hover:shadow-md">
                    <svg id="btn-icon" class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    <svg id="btn-spin" class="w-4 h-4 flex-shrink-0 hidden animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    <span id="btn-text">{{ $cacheResults ? 'ล้าง Cache อีกครั้ง' : 'ล้าง Cache ทั้งหมด' }}</span>
                </button>
            </form>
        </div>
    </x-card>

    {{-- ─── Database Migrations ─── --}}
    <x-card class="overflow-hidden !p-0">

        <div class="px-5 py-3.5 border-b flex items-center gap-2
            {{ $hasMigError ? 'border-red-100 bg-red-50' : ($pendingCount > 0 ? 'border-amber-100 bg-amber-50' : 'border-green-100 bg-green-50') }}">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0
                {{ $hasMigError ? 'bg-red-100' : ($pendingCount > 0 ? 'bg-amber-100' : 'bg-green-100') }}">
                <svg class="w-4 h-4 {{ $hasMigError ? 'text-red-500' : ($pendingCount > 0 ? 'text-amber-600' : 'text-green-600') }}"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/>
                </svg>
            </div>
            <div>
                <h2 class="text-sm font-semibold {{ $hasMigError ? 'text-red-800' : ($pendingCount > 0 ? 'text-amber-800' : 'text-green-800') }}">
                    Database Migrations
                </h2>
                <p class="text-xs {{ $hasMigError ? 'text-red-500' : ($pendingCount > 0 ? 'text-amber-500' : 'text-green-500') }}">
                    php artisan migrate
                </p>
            </div>
            <div class="ml-auto">
                @if($hasMigError)
                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-red-100 text-red-700">ข้อผิดพลาด</span>
                @elseif($pendingCount > 0)
                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-amber-200 text-amber-800">⚠ ค้างอยู่ {{ $pendingCount }}</span>
                @else
                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-green-100 text-green-700">✓ ทันสมัย</span>
                @endif
            </div>
        </div>

        <div class="px-5 py-4 space-y-3">

            @if(session('migration_output'))
                <div class="bg-slate-900 text-green-400 font-mono text-xs rounded-xl p-3 whitespace-pre-wrap leading-relaxed max-h-40 overflow-y-auto">{{ session('migration_output') }}</div>
            @endif

            @if($hasMigError)
                <p class="text-sm text-red-600 bg-red-50 border border-red-100 rounded-xl px-4 py-3">{{ $migrationStatus['error'] }}</p>
            @else

                @if($pendingCount > 0)
                <div>
                    <p class="text-xs font-semibold text-amber-700 mb-1.5">รอรัน ({{ $pendingCount }} รายการ)</p>
                    <div class="space-y-1">
                        @foreach($migrationStatus['pending'] as $m)
                        <div class="flex items-center gap-2 px-3 py-2 bg-amber-50 border border-amber-100 rounded-xl text-xs font-mono text-amber-800">
                            <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span class="truncate">{{ $m }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                @if($completedCount > 0)
                <details class="group">
                    <summary class="cursor-pointer text-xs font-semibold text-gray-500 hover:text-gray-700 select-none flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                        รันแล้ว ({{ $completedCount }} รายการ)
                    </summary>
                    <div class="mt-2 space-y-1 max-h-48 overflow-y-auto">
                        @foreach(array_reverse($migrationStatus['completed']) as $m)
                        <div class="flex items-center gap-2 px-3 py-2 bg-green-50/60 rounded-xl text-xs font-mono text-green-800">
                            <svg class="w-3 h-3 flex-shrink-0 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span class="flex-1 truncate">{{ $m }}</span>
                            @if(isset($migrationStatus['ran'][$m]))
                                <span class="flex-shrink-0 text-green-500 opacity-60">batch {{ $migrationStatus['ran'][$m] }}</span>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </details>
                @endif

            @endif
        </div>

        @if(!$hasMigError)
        <div class="px-5 py-4 bg-gray-50 border-t border-gray-100">
            <button type="button"
                    onclick="openMigrateModal()"
                    class="w-full flex items-center justify-center gap-2
                           {{ $pendingCount > 0 ? 'bg-amber-600 hover:bg-amber-500 text-white' : 'bg-gray-100 hover:bg-gray-200 text-gray-600' }}
                           font-semibold text-sm py-2.5 px-4 rounded-xl transition-all">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3l14 9-14 9V3z"/>
                </svg>
                รัน Migrations
                @if($pendingCount > 0)
                    <span class="bg-white/20 text-white text-xs font-bold px-1.5 py-0.5 rounded-full">{{ $pendingCount }}</span>
                @endif
            </button>
            @if($pendingCount === 0)
                <p class="text-xs text-green-600 mt-2 text-center">✓ ไม่มี migration ค้างอยู่</p>
            @endif
        </div>
        @endif
    </x-card>

</div>

{{-- ===== ROW 2: ฟีเจอร์ระบบตัวแทน ===== --}}
@php
$features = [
    ['icon' => 'M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1',       'label' => 'ระบบล็อกอิน',       'desc' => 'รหัสตัวแทน + รหัสผ่าน',       'color' => 'bg-slate-100 text-slate-600'],
    ['icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6', 'label' => 'Dashboard',           'desc' => 'สรุปข้อมูล ตัวแทน อสังหา',    'color' => 'bg-blue-100 text-blue-600'],
    ['icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6', 'label' => 'อสังหาริมทรัพย์',    'desc' => 'ยูนิต การเช่า การจอง',       'color' => 'bg-green-100 text-green-600'],
    ['icon' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z', 'label' => 'บิลการชำระเงิน',    'desc' => 'สลิป มัดจำ ค่าเช่ารายเดือน', 'color' => 'bg-purple-100 text-purple-600'],
    ['icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',                                  'label' => 'ประวัติการใช้งาน',   'desc' => 'Log ทุก action ในระบบ',       'color' => 'bg-orange-100 text-orange-600'],
    ['icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',                                                                                     'label' => 'โปรไฟล์ตัวแทน',      'desc' => 'ข้อมูลส่วนตัว ธนาคาร รูป',   'color' => 'bg-pink-100 text-pink-600'],
];
@endphp

<x-card class="overflow-hidden !p-0 mt-5">
    <div class="px-5 py-3 border-b border-gray-100 flex items-center gap-2">
        <div class="w-8 h-8 bg-brand-100 rounded-lg flex items-center justify-center flex-shrink-0">
            <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
            </svg>
        </div>
        <div>
            <h2 class="text-sm font-semibold text-gray-800">ฟีเจอร์ระบบตัวแทน</h2>
            <p class="text-xs text-gray-400">ฟีเจอร์ที่เปิดใช้งานอยู่</p>
        </div>
        <span class="ml-auto text-xs font-mono text-gray-400 bg-gray-100 px-2 py-0.5 rounded">v.current</span>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 divide-x divide-y divide-gray-100">
        @foreach($features as $feat)
        <div class="flex flex-col items-center gap-2.5 px-3 py-4 hover:bg-gray-50/60 transition-colors text-center">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center {{ $feat['color'] }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $feat['icon'] }}"/>
                </svg>
            </div>
            <div>
                <p class="text-xs font-semibold text-gray-800 leading-tight">{{ $feat['label'] }}</p>
                <p class="text-[11px] text-gray-400 mt-0.5 leading-tight">{{ $feat['desc'] }}</p>
            </div>
            <span class="text-[11px] font-medium px-2 py-0.5 rounded-full bg-green-100 text-green-700 mt-auto">Active</span>
        </div>
        @endforeach
    </div>
</x-card>

{{-- ===== Migration Confirm Modal ===== --}}
<div id="migrate-modal-backdrop"
     class="hidden fixed inset-0 bg-black/50 z-50 backdrop-blur-sm flex items-center justify-center px-4"
     onclick="closeMigrateModal()">
    <div id="migrate-modal-panel"
         onclick="event.stopPropagation()"
         class="bg-white rounded-2xl w-full max-w-sm shadow-2xl overflow-hidden"
         style="transform: translateY(16px); opacity: 0; scale: 0.97; transition: all 0.25s cubic-bezier(0.21,1.02,0.73,1);">

        <div class="bg-amber-50 border-b border-amber-100 px-5 py-4 flex items-center gap-3">
            <div class="w-9 h-9 bg-amber-100 rounded-xl flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-bold text-amber-900">ยืนยันการรัน Migrations</p>
                <p class="text-xs text-amber-600 mt-0.5">php artisan migrate --force</p>
            </div>
        </div>

        <div class="px-5 py-4">
            <p class="text-sm text-gray-600 mb-3">
                พิมพ์ <code class="bg-gray-100 px-1.5 py-0.5 rounded font-mono text-xs font-bold">RUN_MIGRATIONS</code> เพื่อยืนยัน
            </p>
            <form action="{{ route('deploy.migrate') }}" method="POST">
                @csrf
                <input type="text"
                       name="confirm_migrate"
                       id="confirm-migrate-input"
                       placeholder="RUN_MIGRATIONS"
                       autocomplete="off"
                       oninput="checkMigrateInput(this.value)"
                       class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm font-mono
                              focus:outline-none focus:ring-2 focus:ring-amber-300 focus:border-amber-400 transition-all">
                <div class="flex justify-end gap-2 mt-4">
                    <button type="button"
                            onclick="closeMigrateModal()"
                            class="px-4 py-2 rounded-xl text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 transition-colors">
                        ยกเลิก
                    </button>
                    <button type="submit"
                            id="confirm-migrate-btn"
                            disabled
                            class="px-4 py-2 rounded-xl text-sm font-semibold text-white
                                   bg-amber-600 hover:bg-amber-500 disabled:opacity-40 disabled:cursor-not-allowed transition-all">
                        ยืนยัน - รัน Migrations
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
function handleCacheSubmit() {
    const btn   = document.getElementById('run-btn');
    const icon  = document.getElementById('btn-icon');
    const spin  = document.getElementById('btn-spin');
    const label = document.getElementById('btn-text');
    btn.disabled = true;
    btn.classList.add('opacity-70', 'cursor-not-allowed');
    icon.classList.add('hidden');
    spin.classList.remove('hidden');
    label.textContent = 'กำลังล้าง Cache...';
    return true;
}

function openMigrateModal() {
    const backdrop = document.getElementById('migrate-modal-backdrop');
    const panel    = document.getElementById('migrate-modal-panel');
    backdrop.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    requestAnimationFrame(() => {
        panel.style.transform = 'translateY(0)';
        panel.style.opacity   = '1';
        panel.style.scale     = '1';
    });
    document.getElementById('confirm-migrate-input').value = '';
    document.getElementById('confirm-migrate-btn').disabled = true;
    setTimeout(() => document.getElementById('confirm-migrate-input').focus(), 100);
}

function closeMigrateModal() {
    const backdrop = document.getElementById('migrate-modal-backdrop');
    const panel    = document.getElementById('migrate-modal-panel');
    panel.style.transform = 'translateY(16px)';
    panel.style.opacity   = '0';
    panel.style.scale     = '0.97';
    setTimeout(() => {
        backdrop.classList.add('hidden');
        document.body.style.overflow = '';
    }, 250);
}

function checkMigrateInput(val) {
    document.getElementById('confirm-migrate-btn').disabled = (val !== 'RUN_MIGRATIONS');
}
</script>
@endpush
