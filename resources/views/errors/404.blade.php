<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - ไม่พบหน้าที่ต้องการ</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo-icon.svg') }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="min-h-screen bg-gradient-to-br from-brand-950 via-brand-900 to-brand-950 flex items-center justify-center p-4">
<div class="w-full max-w-md text-center">

    <div class="inline-flex items-center justify-center w-20 h-20 bg-white/10 rounded-2xl mb-6">
        <svg class="w-10 h-10 text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
    </div>

    <p class="text-brand-300 text-sm font-semibold uppercase tracking-widest mb-2">Error 404</p>
    <h1 class="text-4xl font-bold text-white mb-3">ไม่พบหน้าที่ต้องการ</h1>
    <p class="text-brand-300 text-base mb-8">
        ขออภัย ไม่พบหน้าที่คุณกำลังมองหา<br>
        อาจถูกย้าย ลบ หรือ URL ไม่ถูกต้อง
    </p>

    <div class="flex flex-col sm:flex-row gap-3 justify-center">
        <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('dashboard') }}"
           onclick="history.length > 1 ? (history.back(), event.preventDefault()) : null"
           class="inline-flex items-center justify-center gap-2 bg-white/10 hover:bg-white/20 text-white font-semibold px-6 py-3 rounded-xl transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            ย้อนกลับ
        </a>
        <a href="{{ route('dashboard') }}"
           class="inline-flex items-center justify-center gap-2 bg-brand-600 hover:bg-brand-700 text-white font-semibold px-6 py-3 rounded-xl transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            หน้าหลัก
        </a>
    </div>

    <p class="text-brand-400/50 text-xs mt-10">ระบบจัดการตัวแทน &copy; {{ date('Y') }}</p>
</div>
</body>
</html>
