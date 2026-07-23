<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>419 - Session หมดอายุ</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo-icon.svg') }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="min-h-screen bg-gradient-to-br from-brand-950 via-brand-900 to-brand-950 flex items-center justify-center p-4">
<div class="w-full max-w-md text-center">

    <div class="inline-flex items-center justify-center w-20 h-20 bg-white/10 rounded-2xl mb-6">
        <svg class="w-10 h-10 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
    </div>

    <p class="text-amber-400 text-sm font-semibold uppercase tracking-widest mb-2">Session Expired</p>
    <h1 class="text-4xl font-bold text-white mb-3">Session หมดอายุ</h1>
    <p class="text-brand-300 text-base mb-8">
        เซสชันของคุณหมดอายุแล้ว<br>
        กรุณาโหลดหน้าใหม่และลองอีกครั้ง
    </p>

    <div class="flex flex-col sm:flex-row gap-3 justify-center">
        <button onclick="location.reload()"
                class="inline-flex items-center justify-center gap-2 bg-brand-600 hover:bg-brand-700 text-white font-semibold px-6 py-3 rounded-xl transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            โหลดหน้าใหม่
        </button>
        <a href="{{ route('login') }}"
           class="inline-flex items-center justify-center gap-2 bg-white/10 hover:bg-white/20 text-white font-semibold px-6 py-3 rounded-xl transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
            </svg>
            เข้าสู่ระบบ
        </a>
    </div>

    <p class="text-brand-400/50 text-xs mt-10">ระบบจัดการตัวแทน &copy; {{ date('Y') }}</p>
</div>
</body>
</html>
