<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 — ข้อผิดพลาดของระบบ</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="min-h-screen bg-gradient-to-br from-brand-950 via-brand-900 to-brand-950 flex items-center justify-center p-4">
<div class="w-full max-w-md text-center">

    <div class="inline-flex items-center justify-center w-20 h-20 bg-white/10 rounded-2xl mb-6">
        <svg class="w-10 h-10 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.07 16.5c-.77.833.192 2.5 1.732 2.5z"/>
        </svg>
    </div>

    <p class="text-red-400 text-sm font-semibold uppercase tracking-widest mb-2">Error 500</p>
    <h1 class="text-4xl font-bold text-white mb-3">ข้อผิดพลาดของระบบ</h1>
    <p class="text-brand-300 text-base mb-8">
        เกิดข้อผิดพลาดภายในเซิร์ฟเวอร์<br>
        กรุณาลองใหม่อีกครั้ง หรือติดต่อผู้ดูแลระบบ
    </p>

    <div class="flex flex-col sm:flex-row gap-3 justify-center">
        <button onclick="location.reload()"
                class="inline-flex items-center justify-center gap-2 bg-white/10 hover:bg-white/20 text-white font-semibold px-6 py-3 rounded-xl transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            ลองใหม่
        </button>
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
