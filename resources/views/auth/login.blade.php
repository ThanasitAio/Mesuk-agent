<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ — ระบบจัดการตัวแทน</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gradient-to-br from-brand-950 via-brand-900 to-brand-950 flex items-center justify-center p-4">

<div class="w-full max-w-md">

    {{-- Brand --}}
    <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-brand-600 rounded-2xl shadow-2xl mb-4">
            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
        </div>
        <h1 class="text-2xl font-bold text-white">ระบบจัดการตัวแทน</h1>
        <p class="text-brand-300 text-sm mt-1">บริหารจัดการสมาชิกตัวแทน</p>
    </div>

    {{-- Card --}}
    <div class="bg-white rounded-2xl shadow-2xl p-8">
        <h2 class="text-xl font-semibold text-gray-800 mb-6">เข้าสู่ระบบ</h2>

        {{-- Error / Success Alerts --}}
        @if(session('error'))
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-5 text-sm flex items-center gap-2">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                {{ session('error') }}
            </div>
        @endif

        @if(session('success'))
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-5 text-sm flex items-center gap-2">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                {{ session('success') }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.post') }}" class="space-y-5">
            @csrf

            {{-- Agent Code --}}
            <div>
                <label for="agent_code" class="block text-sm font-medium text-gray-700 mb-1.5">รหัสตัวแทน</label>
                <input type="text"
                       id="agent_code"
                       name="agent_code"
                       value="{{ old('agent_code', request()->cookie('agent_remember_code')) }}"
                       placeholder="กรอกรหัสตัวแทน"
                       required
                       autofocus
                       autocomplete="username"
                       autocapitalize="none"
                       autocorrect="off"
                       class="w-full px-4 py-3 border rounded-xl text-sm transition focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent
                              {{ $errors->has('agent_code') ? 'border-red-400 bg-red-50' : 'border-gray-300' }}">
                @error('agent_code')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Password --}}
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">รหัสผ่าน</label>
                <div class="relative">
                    <input type="password"
                           id="password"
                           name="password"
                           placeholder="••••••••"
                           required
                           autocomplete="current-password"
                           class="w-full px-4 py-3 pr-11 border rounded-xl text-sm transition focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent
                                  {{ $errors->has('password') ? 'border-red-400 bg-red-50' : 'border-gray-300' }}">
                    <button type="button"
                            onclick="togglePwd()"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
                        <svg id="eye-open" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <svg id="eye-closed" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                        </svg>
                    </button>
                </div>
                @error('password')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Remember Me --}}
            <div class="flex items-center gap-2">
                <input type="checkbox"
                       id="remember"
                       name="remember"
                       value="1"
                       {{ request()->cookie('agent_remember_code') ? 'checked' : '' }}
                       class="w-4 h-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500 cursor-pointer">
                <label for="remember" class="text-sm text-gray-600 cursor-pointer select-none">จำรหัสตัวแทนในครั้งถัดไป</label>
            </div>

            {{-- Submit --}}
            <button type="submit"
                    class="w-full bg-brand-600 hover:bg-brand-700 active:bg-brand-800 text-white font-semibold py-3 px-4 rounded-xl transition-colors duration-200 flex items-center justify-center gap-2 text-sm mt-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                </svg>
                เข้าสู่ระบบ
            </button>
        </form>
    </div>

    <p class="text-center text-brand-400/60 text-xs mt-6">
        ระบบจัดการตัวแทน &copy; {{ date('Y') }}
    </p>
</div>

<script>
    function togglePwd() {
        const pwd = document.getElementById('password');
        const open = document.getElementById('eye-open');
        const closed = document.getElementById('eye-closed');
        if (pwd.type === 'password') {
            pwd.type = 'text';
            open.classList.add('hidden');
            closed.classList.remove('hidden');
        } else {
            pwd.type = 'password';
            open.classList.remove('hidden');
            closed.classList.add('hidden');
        }
    }
</script>
</body>
</html>
