<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#468432">
    <title>@yield('title', 'หน้าหลัก') — ระบบจัดการตัวแทน</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo-icon.svg') }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { -webkit-tap-highlight-color: transparent; }
        .tap-effect:active { opacity: 0.7; transition: opacity 0.1s; }
        @keyframes sheetUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
        .sheet-animate { animation: sheetUp 0.28s cubic-bezier(0.32,0.72,0,1); }
        /* Toast */
        @keyframes toastIn  { from { opacity:0; transform: translateX(110%); } to { opacity:1; transform: translateX(0); } }
        @keyframes toastOut { from { opacity:1; transform: translateX(0); } to { opacity:0; transform: translateX(110%); } }
        .toast-in  { animation: toastIn  0.35s cubic-bezier(0.21,1.02,0.73,1) forwards; }
        .toast-out { animation: toastOut 0.3s ease-in forwards; }
        .toast-progress { transition: width linear; }
        /* Modal */
        .modal-open .modal-panel { transform: translateY(0) !important; opacity: 1 !important; scale: 1 !important; }
        /* Date input overlay: hide native UI, full-area click to open picker */
        input[type="date"] { position: relative; cursor: pointer; }
        input[type="date"]::-webkit-calendar-picker-indicator {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            margin: 0;
            padding: 0;
            opacity: 0;
            cursor: pointer;
        }
        /* When date input is used as a transparent overlay (.date-overlay), hide all native text */
        .date-overlay::-webkit-datetime-edit { opacity: 0; }
        .date-overlay::-webkit-inner-spin-button { display: none; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50 font-sans antialiased">

<div class="flex min-h-screen">

    {{-- ===== SIDEBAR (Desktop only) ===== --}}
    <aside class="hidden lg:flex lg:flex-col fixed inset-y-0 left-0 w-64 bg-brand-900 text-white z-30">

        {{-- Logo --}}
        <div class="flex items-center px-5 py-4 border-b border-brand-800">
            <img src="{{ asset('images/logo-white.svg') }}"
                 alt="Mesuk ระบบจัดการตัวแทน"
                 class="h-10">
        </div>

        {{-- Navigation --}}
        @php
            $rentalRateAllowed = in_array(session('agent_code'), \App\Http\Controllers\RentalRateController::ALLOWED_AGENT_CODES, true);
        @endphp
        <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
            <a href="{{ route('dashboard') }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                      {{ request()->routeIs('dashboard') ? 'bg-brand-600 text-white' : 'text-slate-300 hover:bg-brand-800 hover:text-white' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                หน้าหลัก
            </a>

            @if(session('agent_is_manager'))
            <a href="{{ route('properties.index') }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                      {{ request()->routeIs('properties.*') ? 'bg-brand-600 text-white' : 'text-slate-300 hover:bg-brand-800 hover:text-white' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                อสังหาริมทรัพย์
            </a>
            @endif

            @if($rentalRateAllowed)
            <a href="{{ route('rental-rates.index') }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                      {{ request()->routeIs('rental-rates.*') ? 'bg-brand-600 text-white' : 'text-slate-300 hover:bg-brand-800 hover:text-white' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                อัตราเช่า
            </a>
            @endif

            <a href="{{ route('ads.index') }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                      {{ request()->routeIs('ads.*') ? 'bg-brand-600 text-white' : 'text-slate-300 hover:bg-brand-800 hover:text-white' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
                </svg>
                โฆษณา
            </a>

            <a href="{{ route('logs.index') }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                      {{ request()->routeIs('logs.*') ? 'bg-brand-600 text-white' : 'text-slate-300 hover:bg-brand-800 hover:text-white' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                ประวัติการใช้งาน
            </a>

            @if(session('agent_code') == '9999999')
            <a href="{{ route('deploy.show') }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                      {{ request()->routeIs('deploy.*') ? 'bg-amber-600 text-white' : 'text-slate-300 hover:bg-brand-800 hover:text-white' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                ระบบ
            </a>
            @endif
        </nav>

        {{-- User Info at Bottom --}}
        <div class="border-t border-brand-800 p-4">
            <a href="{{ route('profile') }}"
               class="flex items-center gap-3 rounded-xl p-1 transition-colors
                      {{ request()->routeIs('profile*') ? 'bg-brand-700' : 'hover:bg-brand-800' }}">
                @if(session('agent_avatar'))
                    @php
                        $happyestPublic = rtrim(env('HAPPYEST_APP_URL', 'http://127.0.0.1/happyest/public'), '/');
                        $avatarUrl = $happyestPublic . '/storage/' . session('agent_avatar');
                    @endphp
                    <img src="{{ $avatarUrl }}"
                         alt="{{ session('agent_name', 'ผู้ใช้') }}"
                         class="w-9 h-9 rounded-full object-cover flex-shrink-0 ring-2 ring-brand-600"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                    <div class="w-9 h-9 bg-brand-600 rounded-full items-center justify-center flex-shrink-0 hidden">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>
                @else
                    <div class="w-9 h-9 bg-brand-600 rounded-full flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>
                @endif
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-white truncate">{{ session('agent_name', 'ผู้ใช้') }}</p>
                    <p class="text-xs text-brand-300 truncate">{{ session('agent_code', '') }}</p>
                </div>
                <button type="button"
                        title="ออกจากระบบ"
                        onclick="event.preventDefault(); openModal('logout-confirm')"
                        class="p-2 text-brand-300 hover:text-white hover:bg-brand-700 rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                </button>
            </a>
        </div>
    </aside>

    {{-- ===== MAIN CONTENT AREA ===== --}}
    <div class="flex-1 min-w-0 lg:ml-64 flex flex-col min-h-screen">

        {{-- Topbar --}}
        <header class="bg-white border-b border-gray-200 px-4 sticky top-0 z-10 flex-shrink-0 h-14 flex items-center justify-between">
            <div class="flex items-center gap-3">
                {{-- App icon (mobile only) --}}
                <img src="{{ asset('images/logo-icon.svg') }}"
                     alt="Mesuk"
                     class="lg:hidden w-8 h-8 flex-shrink-0">
                <div>
                    <h1 class="text-base font-semibold text-gray-800 leading-tight">@yield('title', 'หน้าหลัก')</h1>
                    @hasSection('breadcrumb')
                        <p class="text-xs text-gray-400 hidden sm:block leading-tight">@yield('breadcrumb')</p>
                    @endif
                </div>
            </div>

            {{-- User Dropdown (desktop only) --}}
            <div class="hidden lg:block relative" id="user-dropdown-container">
                <button onclick="toggleUserMenu()"
                        class="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-gray-100 transition-colors text-sm">
                    @if(session('agent_avatar'))
                        @php
                            $happyestPublic = rtrim(env('HAPPYEST_APP_URL', 'http://127.0.0.1/happyest/public'), '/');
                            $avatarUrl = $happyestPublic . '/storage/' . session('agent_avatar');
                        @endphp
                        <img src="{{ $avatarUrl }}"
                             alt="{{ session('agent_name', 'ผู้ใช้') }}"
                             class="w-7 h-7 rounded-full object-cover ring-2 ring-brand-600"
                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                        <div class="w-7 h-7 bg-brand-600 rounded-full items-center justify-center hidden">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                    @else
                        <div class="w-7 h-7 bg-brand-600 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                    @endif
                    <span class="text-gray-700 font-medium max-w-32 truncate">{{ session('agent_name', 'ผู้ใช้') }}</span>
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                <div id="user-menu"
                     class="hidden absolute right-0 mt-1 w-52 bg-white rounded-xl shadow-lg border border-gray-100 py-1 z-50">
                    <div class="px-4 py-3 border-b border-gray-100">
                        <p class="text-sm font-semibold text-gray-800 truncate">{{ session('agent_name') }}</p>
                        <p class="text-xs text-gray-500 truncate font-mono">{{ session('agent_code') }}</p>
                    </div>
                    <a href="{{ route('profile') }}"
                       class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        โปรไฟล์ของฉัน
                    </a>
                    <div class="border-t border-gray-100 my-1"></div>
                    <button type="button"
                            onclick="openModal('logout-confirm')"
                            class="w-full text-left flex items-center gap-2 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        ออกจากระบบ
                    </button>
                </div>
            </div>
        </header>

        {{-- Flash Messages --}}
        <div id="flash-container" class="px-4 pt-3 space-y-2 flex-shrink-0">
            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl flex items-start justify-between gap-3 text-sm">
                    <div class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>{{ session('success') }}</span>
                    </div>
                    <button onclick="this.parentElement.remove()" class="text-green-400 hover:text-green-600 text-xl leading-none flex-shrink-0">&times;</button>
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl flex items-start justify-between gap-3 text-sm">
                    <div class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>{{ session('error') }}</span>
                    </div>
                    <button onclick="this.parentElement.remove()" class="text-red-400 hover:text-red-600 text-xl leading-none flex-shrink-0">&times;</button>
                </div>
            @endif

            @if($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-sm">
                    <div class="flex items-start gap-2 mb-1.5">
                        <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <strong>กรุณาแก้ไขข้อมูลต่อไปนี้:</strong>
                    </div>
                    <ul class="list-disc list-inside space-y-0.5 ml-7">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        {{-- Page Content --}}
        <main class="flex-1 p-4 lg:p-6 pb-24 lg:pb-8">
            @yield('content')
        </main>

        {{-- Footer (desktop only) --}}
        <footer class="hidden lg:block flex-shrink-0 border-t border-gray-100"
                style="background: linear-gradient(135deg, #f8faf7 0%, #f0f9eb 100%);">
            <div class="px-6 py-3 flex items-center justify-between gap-6">

                {{-- Branding --}}
                <div class="flex items-center gap-2.5 flex-shrink-0">
                    <div class="leading-none">
                        <p class="text-xs font-bold text-gray-600">Mesuk Agent System</p>
                        <p class="text-[10px] text-gray-400 mt-0.5">&copy; {{ date('Y') }} Happy Realestate. All rights reserved.</p>
                    </div>
                </div>

                {{-- Quick Nav --}}
                <nav class="flex items-center gap-5">
                    <a href="{{ route('dashboard') }}"
                       class="text-xs font-medium transition-colors
                              {{ request()->routeIs('dashboard') ? 'text-brand-600 font-semibold' : 'text-gray-400 hover:text-brand-600' }}">
                        ภาพรวม
                    </a>
                    @if(session('agent_is_manager'))
                    <a href="{{ route('properties.index') }}"
                       class="text-xs font-medium transition-colors
                              {{ request()->routeIs('properties.*') ? 'text-brand-600 font-semibold' : 'text-gray-400 hover:text-brand-600' }}">
                        อสังหาริมทรัพย์
                    </a>
                    @endif
                    @if($rentalRateAllowed)
                    <a href="{{ route('rental-rates.index') }}"
                       class="text-xs font-medium transition-colors
                              {{ request()->routeIs('rental-rates.*') ? 'text-brand-600 font-semibold' : 'text-gray-400 hover:text-brand-600' }}">
                        อัตราเช่า
                    </a>
                    @endif
                    <a href="{{ route('ads.index') }}"
                       class="text-xs font-medium transition-colors
                              {{ request()->routeIs('ads.*') ? 'text-brand-600 font-semibold' : 'text-gray-400 hover:text-brand-600' }}">
                        โฆษณา
                    </a>
                    <a href="{{ route('logs.index') }}"
                       class="text-xs font-medium transition-colors
                              {{ request()->routeIs('logs.*') ? 'text-brand-600 font-semibold' : 'text-gray-400 hover:text-brand-600' }}">
                        ประวัติ
                    </a>
                    <a href="{{ route('profile') }}"
                       class="text-xs font-medium transition-colors
                              {{ request()->routeIs('profile*') ? 'text-brand-600 font-semibold' : 'text-gray-400 hover:text-brand-600' }}">
                        โปรไฟล์
                    </a>
                </nav>

                {{-- Agent Badge --}}
                <div class="flex items-center gap-2 bg-white/80 border border-brand-100 rounded-xl px-3 py-1.5 flex-shrink-0 shadow-sm">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse flex-shrink-0"></span>
                    <span class="font-mono text-xs font-bold text-brand-700">{{ session('agent_code', '') }}</span>
                    <span class="text-gray-200 text-sm leading-none select-none">|</span>
                    <span class="text-xs text-gray-500 truncate max-w-[140px]">{{ session('agent_name', 'ผู้ใช้') }}</span>
                </div>

            </div>
        </footer>
    </div>
</div>

{{-- ===== MOBILE BOTTOM NAVIGATION BAR ===== --}}
<nav class="lg:hidden fixed bottom-0 inset-x-0 bg-white border-t border-gray-200 z-40"
     style="padding-bottom: env(safe-area-inset-bottom, 0px)">
    <div class="flex items-stretch" style="height:64px">

        {{-- Dashboard --}}
        <a href="{{ route('dashboard') }}"
           class="flex-1 flex flex-col items-center justify-center gap-1 tap-effect transition-colors
                  {{ request()->routeIs('dashboard') ? 'text-brand-600' : 'text-gray-400' }}">
            <div class="relative">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                @if(request()->routeIs('dashboard'))
                    <span class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-1 h-1 bg-brand-600 rounded-full"></span>
                @endif
            </div>
            <span class="text-[11px] leading-none {{ request()->routeIs('dashboard') ? 'font-semibold' : '' }}">หน้าหลัก</span>
        </a>

        {{-- Ads --}}
        <a href="{{ route('ads.index') }}"
           class="flex-1 flex flex-col items-center justify-center gap-1 tap-effect transition-colors
                  {{ request()->routeIs('ads.*') ? 'text-brand-600' : 'text-gray-400' }}">
            <div class="relative">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
                </svg>
                @if(request()->routeIs('ads.*'))
                    <span class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-1 h-1 bg-brand-600 rounded-full"></span>
                @endif
            </div>
            <span class="text-[11px] leading-none {{ request()->routeIs('ads.*') ? 'font-semibold' : '' }}">โฆษณา</span>
        </a>

        {{-- Logs --}}
        <a href="{{ route('logs.index') }}"
           class="flex-1 flex flex-col items-center justify-center gap-1 tap-effect transition-colors
                  {{ request()->routeIs('logs.*') ? 'text-brand-600' : 'text-gray-400' }}">
            <div class="relative">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                @if(request()->routeIs('logs.*'))
                    <span class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-1 h-1 bg-brand-600 rounded-full"></span>
                @endif
            </div>
            <span class="text-[11px] leading-none {{ request()->routeIs('logs.*') ? 'font-semibold' : '' }}">ประวัติ</span>
        </a>

        {{-- More --}}
        <button id="more-tab-btn"
                onclick="openMoreSheet()"
                class="flex-1 flex flex-col items-center justify-center gap-1 text-gray-400 tap-effect transition-colors">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7"/>
            </svg>
            <span class="text-[11px] leading-none">เพิ่มเติม</span>
        </button>

    </div>
</nav>

{{-- ===== MOBILE MORE BOTTOM SHEET ===== --}}
<div id="more-overlay"
     class="hidden lg:hidden fixed inset-0 bg-black/50 z-50 backdrop-blur-sm"
     onclick="closeMoreSheet()"></div>

<div id="more-sheet"
     class="hidden lg:hidden fixed inset-x-0 bottom-0 bg-white rounded-t-3xl z-50 shadow-2xl"
     style="padding-bottom: max(env(safe-area-inset-bottom, 0px), 20px)">

    {{-- Drag handle --}}
    <div class="flex justify-center pt-3 pb-2">
        <div class="w-10 h-1 bg-gray-200 rounded-full"></div>
    </div>

    {{-- User Info --}}
    <div class="px-5 pt-2 pb-4 flex items-center gap-4">
        @if(session('agent_avatar'))
            @php
                $happyestPublic = rtrim(env('HAPPYEST_APP_URL', 'http://127.0.0.1/happyest/public'), '/');
                $avatarUrl = $happyestPublic . '/storage/' . session('agent_avatar');
            @endphp
            <img src="{{ $avatarUrl }}"
                 alt="{{ session('agent_name', 'ผู้ใช้') }}"
                 class="w-14 h-14 rounded-2xl object-cover flex-shrink-0 shadow-sm ring-2 ring-brand-600"
                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
            <div class="w-14 h-14 bg-brand-600 rounded-2xl items-center justify-center flex-shrink-0 shadow-sm hidden">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
            </div>
        @else
            <div class="w-14 h-14 bg-brand-600 rounded-2xl flex items-center justify-center flex-shrink-0 shadow-sm">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
            </div>
        @endif
        <div class="min-w-0 flex-1">
            <p class="text-base font-bold text-gray-900 truncate">{{ session('agent_name', 'ผู้ใช้') }}</p>
            <p class="text-sm text-gray-400 font-mono truncate">{{ session('agent_code', '') }}</p>
        </div>
        {{-- Close button --}}
        <button onclick="closeMoreSheet()"
                class="w-9 h-9 flex items-center justify-center bg-gray-100 rounded-full text-gray-500 hover:bg-gray-200 transition-colors flex-shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    {{-- ─── เพิ่มเมนูใหม่ที่นี่ (grid 3 คอลัมน์) ─────────────────────────────
         ตัวอย่าง:
         <a href="{{ route('reports.index') }}"
            onclick="closeMoreSheet()"
            class="more-menu-item {{ request()->routeIs('reports.*') ? 'bg-brand-50 text-brand-700' : 'bg-gray-50 text-gray-600' }}">
             <div class="w-12 h-12 rounded-2xl bg-white shadow-sm flex items-center justify-center mb-2">
                 <svg class="w-6 h-6" .../>
             </div>
             <span class="text-xs font-medium text-center leading-tight">รายงาน</span>
         </a>
    ─────────────────────────────────────────────────────────────────────── --}}
    @php
        $extraMenus = [];
        if (session('agent_is_manager')) {
            $extraMenus[] = [
                'route'   => 'properties.index',
                'pattern' => 'properties.*',
                'label'   => 'อสังหาริมทรัพย์',
                'icon'    => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>',
            ];
        }
        if ($rentalRateAllowed) {
            $extraMenus[] = [
                'route'   => 'rental-rates.index',
                'pattern' => 'rental-rates.*',
                'label'   => 'อัตราเช่า',
                'icon'    => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>',
            ];
        }
    @endphp

    @if(count($extraMenus) > 0)
    <div class="px-5 pb-4">
        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">เมนูอื่น ๆ</p>
        <div class="grid grid-cols-3 gap-3">
            @foreach($extraMenus as $menu)
            <a href="{{ route($menu['route']) }}"
               onclick="closeMoreSheet()"
               class="flex flex-col items-center p-3 rounded-2xl transition-colors tap-effect
                      {{ request()->routeIs($menu['pattern']) ? 'bg-brand-50 text-brand-700' : 'bg-gray-50 text-gray-600' }}">
                <div class="w-12 h-12 rounded-2xl bg-white shadow-sm flex items-center justify-center mb-2">
                    {!! $menu['icon'] !!}
                </div>
                <span class="text-xs font-medium text-center leading-tight">{{ $menu['label'] }}</span>
            </a>
            @endforeach
        </div>
    </div>
    @endif

    <div class="h-px bg-gray-100 mx-5 mb-4"></div>

    {{-- Profile link --}}
    <div class="px-5 mb-3">
        <a href="{{ route('profile') }}"
           onclick="closeMoreSheet()"
           class="w-full flex items-center gap-3 bg-gray-50 active:bg-gray-100 text-gray-700 font-semibold py-4 rounded-2xl transition-colors text-sm tap-effect">
            <div class="w-10 h-10 bg-brand-100 rounded-xl flex items-center justify-center flex-shrink-0 ml-2">
                <svg class="w-5 h-5 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
            </div>
            โปรไฟล์ของฉัน
        </a>
    </div>

    @if(session('agent_code') == '9999999')
    {{-- Deploy (admin only) --}}
    <div class="px-5 mb-3">
        <a href="{{ route('deploy.show') }}"
           onclick="closeMoreSheet()"
           class="w-full flex items-center gap-3 bg-amber-50 active:bg-amber-100 text-amber-700 font-semibold py-4 rounded-2xl transition-colors text-sm tap-effect">
            <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center flex-shrink-0 ml-2">
                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
            </div>
            ระบบ — ล้าง Cache
        </a>
    </div>
    @endif

    {{-- Logout --}}
    <div class="px-5">
        <button type="button"
                onclick="closeMoreSheet(); openModal('logout-confirm')"
                class="w-full flex items-center justify-center gap-3 bg-red-50 active:bg-red-100 text-red-600 font-semibold py-4 rounded-2xl transition-colors text-sm tap-effect">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
            ออกจากระบบ
        </button>
    </div>
</div>

{{-- Logout Confirm Modal --}}
<x-confirm-modal
    id="logout-confirm"
    title="ออกจากระบบ"
    action="{{ route('logout') }}"
    confirm-label="ออกจากระบบ"
    icon-variant="warning"
>
    ต้องการออกจากระบบหรือไม่?
</x-confirm-modal>

{{-- ===== TOAST CONTAINER ===== --}}
<div id="toast-container"
     class="fixed top-4 right-4 z-[9999] flex flex-col gap-2 pointer-events-none"
     style="max-width: min(calc(100vw - 2rem), 380px)">
</div>

<script>
    // ─── Toast Notification System ────────────────────────────────────────────
    const Toast = (() => {
        const cfg = {
            success: { icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>', bg: 'bg-white', bar: 'bg-green-500', iconClass: 'text-green-500', border: 'border-green-200', label: 'สำเร็จ', labelClass: 'text-green-700' },
            error:   { icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>', bg: 'bg-white', bar: 'bg-red-500', iconClass: 'text-red-500', border: 'border-red-200', label: 'ผิดพลาด', labelClass: 'text-red-700' },
            warning: { icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.07 16.5c-.77.833.192 2.5 1.732 2.5z"/>', bg: 'bg-white', bar: 'bg-amber-500', iconClass: 'text-amber-500', border: 'border-amber-200', label: 'คำเตือน', labelClass: 'text-amber-700' },
            info:    { icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>', bg: 'bg-white', bar: 'bg-blue-500', iconClass: 'text-blue-500', border: 'border-blue-200', label: 'แจ้งเตือน', labelClass: 'text-blue-700' },
        };
        let _id = 0;
        function show({ type = 'info', message = '', duration = 4000, title = null }) {
            const c = cfg[type] || cfg.info;
            const id = 'toast_' + (++_id);
            const el = document.createElement('div');
            el.id = id;
            el.className = `pointer-events-auto w-full ${c.bg} border ${c.border} rounded-2xl shadow-lg overflow-hidden toast-in`;
            el.innerHTML = `
                <div class="flex items-start gap-3 px-4 pt-4 pb-3">
                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5 ${c.iconClass}" fill="none" stroke="currentColor" viewBox="0 0 24 24">${c.icon}</svg>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold ${c.labelClass} mb-0.5">${title || c.label}</p>
                        <p class="text-sm text-gray-700 leading-snug">${message}</p>
                    </div>
                    <button onclick="Toast.dismiss('${id}')" class="flex-shrink-0 text-gray-300 hover:text-gray-500 transition-colors -mt-0.5 focus:outline-none" aria-label="ปิด">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="h-1 w-full ${c.bar} rounded-b-2xl toast-progress" id="${id}_bar" style="width:100%"></div>`;

            const container = document.getElementById('toast-container');
            container.appendChild(el);

            // Progress bar
            const bar = document.getElementById(id + '_bar');
            bar.style.transitionDuration = duration + 'ms';
            requestAnimationFrame(() => { bar.style.width = '0%'; });

            const timer = setTimeout(() => Toast.dismiss(id), duration);
            el._timer = timer;
        }
        function dismiss(id) {
            const el = document.getElementById(id);
            if (!el) return;
            clearTimeout(el._timer);
            el.classList.remove('toast-in');
            el.classList.add('toast-out');
            el.addEventListener('animationend', () => el.remove(), { once: true });
        }
        return {
            show,
            dismiss,
            success: (msg, opts = {}) => show({ type: 'success', message: msg, ...opts }),
            error:   (msg, opts = {}) => show({ type: 'error',   message: msg, ...opts }),
            warning: (msg, opts = {}) => show({ type: 'warning', message: msg, ...opts }),
            info:    (msg, opts = {}) => show({ type: 'info',    message: msg, ...opts }),
        };
    })();

    // ─── คัดลอกลิงก์แอด (ผูก ref=agent_code) ──────────────────────────────────
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

    // ─── Modal helpers ─────────────────────────────────────────────────────────
    function openModal(id) {
        const backdrop = document.getElementById(id + '_backdrop');
        const panel    = document.getElementById(id + '_panel');
        if (!backdrop || !panel) return;
        backdrop.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        requestAnimationFrame(() => {
            // Tailwind v4 compiles translate-y-full / scale-95 to the native
            // `translate` / `scale` CSS properties, not `transform` — must
            // clear those inline, not `transform`, or the panel stays off-screen.
            panel.style.translate = '0 0';
            panel.style.opacity   = '1';
            panel.style.scale     = '1';
        });
    }
    function closeModal(id) {
        const backdrop = document.getElementById(id + '_backdrop');
        const panel    = document.getElementById(id + '_panel');
        if (!backdrop || !panel) return;
        panel.style.translate = '';
        panel.style.opacity   = '';
        panel.style.scale     = '';
        setTimeout(() => {
            backdrop.classList.add('hidden');
            document.body.style.overflow = '';
        }, 300);
    }
    document.addEventListener('keydown', e => {
        if (e.key !== 'Escape') return;
        document.querySelectorAll('[id$="_backdrop"]:not(.hidden)').forEach(bd => {
            const id = bd.id.replace('_backdrop', '');
            closeModal(id);
        });
    });

    // ─── Input helpers ─────────────────────────────────────────────────────────
    function togglePassword(id) {
        const input  = document.getElementById(id);
        const eyeOff = document.getElementById(id + '_eye_off');
        const eyeOn  = document.getElementById(id + '_eye_on');
        if (!input) return;
        const isHidden = input.type === 'password';
        input.type = isHidden ? 'text' : 'password';
        eyeOff.classList.toggle('hidden', isHidden);
        eyeOn.classList.toggle('hidden', !isHidden);
    }
    function clearInput(id) {
        const input = document.getElementById(id);
        if (input) { input.value = ''; input.focus(); }
    }

    // Desktop user dropdown
    function toggleUserMenu() {
        document.getElementById('user-menu').classList.toggle('hidden');
    }
    document.addEventListener('click', function(e) {
        const c = document.getElementById('user-dropdown-container');
        if (c && !c.contains(e.target)) {
            const m = document.getElementById('user-menu');
            if (m) m.classList.add('hidden');
        }
    });

    // Mobile more sheet
    function openMoreSheet() {
        const sheet   = document.getElementById('more-sheet');
        const overlay = document.getElementById('more-overlay');
        const btn     = document.getElementById('more-tab-btn');
        sheet.classList.remove('hidden');
        overlay.classList.remove('hidden');
        sheet.classList.add('sheet-animate');
        document.body.style.overflow = 'hidden';
        btn.classList.add('text-brand-600');
        btn.classList.remove('text-gray-400');
    }
    function closeMoreSheet() {
        const sheet   = document.getElementById('more-sheet');
        const overlay = document.getElementById('more-overlay');
        const btn     = document.getElementById('more-tab-btn');
        sheet.classList.add('hidden');
        overlay.classList.add('hidden');
        document.body.style.overflow = '';
        btn.classList.remove('text-brand-600');
        btn.classList.add('text-gray-400');
    }

    // ─── Thai Date Formatter (YYYY-MM-DD → "19 มิ.ย. 2569") ──────────────────
    function formatThaiDate(str) {
        if (!str) return '';
        const p = str.split('-');
        if (p.length !== 3) return str;
        const y = parseInt(p[0], 10), m = parseInt(p[1], 10), d = parseInt(p[2], 10);
        const months = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
        return d + ' ' + months[m - 1] + ' ' + (y + 543);
    }

    // ─── Alpine: Flatpickr Date Picker ────────────────────────────────────────
    function alpineDatePicker(initVal) {
        return {
            v: initVal || '',
            fp: null,
            init() {
                if (typeof flatpickr === 'undefined') return;
                const wrapper = this.$refs.fpWrapper;
                if (!wrapper) return;
                this.fp = flatpickr(wrapper, {
                    wrap: true,
                    locale: typeof flatpickr.l10ns !== 'undefined' && flatpickr.l10ns.th ? 'th' : 'default',
                    dateFormat: 'Y-m-d',
                    defaultDate: this.v || null,
                    disableMobile: false,
                    onChange: (selectedDates, dateStr) => { this.v = dateStr; }
                });
            }
        };
    }

    // ─── Alpine: Searchable Select ─────────────────────────────────────────────
    function selectSearch(uid) {
        return {
            open: false, search: '', selected: '', selectedLabel: '',
            placeholder: '— เลือก —',
            options: [],
            get filteredOptions() {
                if (!this.search) return this.options;
                const q = this.search.toLowerCase();
                return this.options.filter(o => o.label.toLowerCase().includes(q));
            },
            init() {
                const native = document.getElementById(uid + '_native');
                if (!native) return;
                const emptyOpt = native.querySelector('option[value=""]');
                if (emptyOpt && emptyOpt.text.trim()) this.placeholder = emptyOpt.text.trim();
                this.options = Array.from(native.options)
                    .filter(o => o.value !== '')
                    .map(o => ({ value: o.value, label: o.text }));
                const preselected = native.querySelector('option[selected]');
                if (preselected) { this.selected = preselected.value; this.selectedLabel = preselected.text; }
            },
            toggle() { this.open = !this.open; if (this.open) this.$nextTick(() => this.$refs.searchInput && this.$refs.searchInput.focus()); },
            close() { this.open = false; this.search = ''; },
            select(opt) { this.selected = opt.value; this.selectedLabel = opt.label; this.close(); },
        };
    }

    // Auto-dismiss flash messages after 4s
    setTimeout(function() {
        const fc = document.getElementById('flash-container');
        if (!fc) return;
        Array.from(fc.children).forEach(function(el) {
            el.style.transition = 'opacity 0.5s, transform 0.4s';
            el.style.opacity = '0';
            el.style.transform = 'translateY(-6px)';
            setTimeout(function() { el.remove(); }, 500);
        });
    }, 4000);
</script>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/th.js"></script>
@stack('scripts')
</body>
</html>
