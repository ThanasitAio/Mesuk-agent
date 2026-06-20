@extends('layouts.app')

@section('title', 'โปรไฟล์ของฉัน')
@section('breadcrumb', 'บัญชีผู้ใช้ / โปรไฟล์')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-[1fr_360px] gap-5 items-start">

    {{-- ===== LEFT: ข้อมูลส่วนตัว ===== --}}
    <x-card>

        {{-- Card Header --}}
        <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-100">
            <div class="w-9 h-9 bg-brand-100 rounded-lg flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
            </div>
            <div>
                <h2 class="text-base font-semibold text-gray-800">ข้อมูลส่วนตัว</h2>
                <p class="text-xs text-gray-500">ข้อมูลทั่วไปและช่องทางติดต่อ</p>
            </div>
        </div>

        {{-- Profile Banner --}}
        @php
            $happyestPublic = rtrim(env('HAPPYEST_APP_URL', 'http://127.0.0.1/happyest/public'), '/');
            $avatarUrl = $agent->avatar ? ($happyestPublic . '/storage/' . $agent->avatar) : null;
        @endphp
        <div class="px-6 py-4 bg-gradient-to-r from-brand-50 to-white border-b border-gray-100 flex items-center gap-4">

            {{-- Avatar + Upload --}}
            <form action="{{ route('profile.photo') }}" method="POST" enctype="multipart/form-data" id="avatar-upload-form">
                @csrf
                <input type="file" id="avatar-file-input" name="avatar"
                       accept="image/jpeg,image/png,image/webp"
                       class="hidden"
                       onchange="document.getElementById('avatar-upload-form').submit()">
                <label for="avatar-file-input"
                       class="relative w-16 h-16 flex-shrink-0 cursor-pointer group block"
                       title="คลิกเพื่อเปลี่ยนรูปโปรไฟล์">
                    @if($avatarUrl)
                        <img src="{{ $avatarUrl }}"
                             alt="{{ $agent->name }}"
                             id="avatar-preview"
                             class="w-16 h-16 rounded-2xl object-cover shadow-sm ring-2 ring-brand-100 group-hover:ring-brand-400 transition-all"
                             onerror="this.style.display='none';document.getElementById('avatar-fallback').style.display='flex'">
                        <div id="avatar-fallback"
                             class="w-16 h-16 bg-brand-600 rounded-2xl items-center justify-center shadow-sm hidden">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                                      d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                    @else
                        <div class="w-16 h-16 bg-brand-600 rounded-2xl flex items-center justify-center shadow-sm group-hover:bg-brand-700 transition-colors">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                                      d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                    @endif
                    {{-- Camera badge --}}
                    <div class="absolute -bottom-1 -right-1 w-6 h-6 bg-white rounded-full border-2 border-brand-100 shadow flex items-center justify-center group-hover:bg-brand-600 group-hover:border-brand-600 transition-all">
                        <svg class="w-3 h-3 text-gray-500 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                </label>
            </form>

            <div class="min-w-0 flex-1">
                <p class="text-base font-bold text-gray-900 truncate">
                    {{ trim(($agent->prefix ? $agent->prefix . ' ' : '') . $agent->name) ?: $agent->agent_code }}
                </p>
                <div class="flex flex-wrap items-center gap-1.5 mt-1">
                    <span class="inline-flex items-center gap-1 text-xs font-mono bg-white text-brand-700 px-2 py-0.5 rounded-full border border-brand-200 shadow-sm">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0"/>
                        </svg>
                        {{ $agent->agent_code }}
                    </span>
                    @if($agent->is_active)
                        <span class="inline-flex items-center gap-1 text-xs bg-green-50 text-green-700 px-2 py-0.5 rounded-full border border-green-200">
                            <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span>
                            ใช้งานอยู่
                        </span>
                    @endif
                </div>
                <p class="text-[11px] text-gray-400 mt-1.5">คลิกรูปเพื่อเปลี่ยนรูปโปรไฟล์ · JPG, PNG, WebP ไม่เกิน 3MB</p>
            </div>
        </div>

        {{-- Form --}}
        <form action="{{ route('profile.update') }}" method="POST" class="p-6">
            @csrf
            @method('PUT')

            {{-- ─── ส่วนที่ 1: ชื่อและข้อมูลพื้นฐาน ─── --}}
            <div class="space-y-4">
                <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-widest">ข้อมูลพื้นฐาน</p>

                {{-- คำนำหน้า + ชื่อ-นามสกุล --}}
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <x-form.select name="prefix" label="คำนำหน้า">
                            <option value="">— ไม่ระบุ —</option>
                            @foreach(['นาย','นาง','นางสาว','ดร.','ผศ.ดร.','รศ.ดร.'] as $p)
                                <option value="{{ $p }}" @selected(old('prefix', $agent->prefix) === $p)>{{ $p }}</option>
                            @endforeach
                        </x-form.select>
                    </div>
                    <div class="col-span-2">
                        <x-form.input name="name" label="ชื่อ-นามสกุล" :value="$agent->name"
                                      placeholder="กรอกชื่อ-นามสกุล" required />
                    </div>
                </div>

                {{-- วันเกิด + เพศ --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <x-form.date name="birthday" label="วันเกิด" clearable
                        :value="old('birthday', $agent->birthday
                            ? \Carbon\Carbon::parse($agent->birthday)->format('Y-m-d')
                            : '')" />

                    <div>
                        <x-form.select name="gender" label="เพศ">
                            <option value="">— ไม่ระบุ —</option>
                            @foreach(['ชาย','หญิง','อื่น ๆ'] as $g)
                                <option value="{{ $g }}" @selected(old('gender', $agent->gender) === $g)>{{ $g }}</option>
                            @endforeach
                        </x-form.select>
                    </div>
                </div>

                {{-- เบอร์โทร + อีเมล --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <x-form.input name="phone" label="เบอร์โทรศัพท์" :value="$agent->phone"
                                  placeholder="0xx-xxx-xxxx" />
                    <x-form.input name="email" type="email" label="อีเมล" :value="$agent->email"
                                  placeholder="example@email.com" />
                </div>
            </div>

            {{-- Divider --}}
            <div class="my-5 border-t border-dashed border-gray-200"></div>

            {{-- ─── ส่วนที่ 2: ช่องทางติดต่อ ─── --}}
            <div class="space-y-4">
                <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-widest">ช่องทางโซเชียล & ที่อยู่</p>

                {{-- Line + Facebook --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <x-form.input name="line_id" label="Line ID" :value="$agent->line_id"
                                  placeholder="Line ID ของคุณ" prefix="LINE" />
                    <x-form.input name="facebook" label="Facebook" :value="$agent->facebook"
                                  placeholder="ชื่อหรือ URL" prefix="FB" />
                </div>

                {{-- ที่อยู่ --}}
                <x-form.textarea name="address" label="ที่อยู่" :value="$agent->address"
                                 placeholder="บ้านเลขที่ ซอย ถนน แขวง/ตำบล เขต/อำเภอ จังหวัด รหัสไปรษณีย์"
                                 :rows="3" />
            </div>

            {{-- Submit --}}
            <div class="flex justify-end mt-6">
                <x-btn type="submit" variant="primary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    บันทึกข้อมูล
                </x-btn>
            </div>
        </form>
    </x-card>

    {{-- ===== RIGHT COLUMN ===== --}}
    <div class="space-y-5">

        {{-- ─── Card: เปลี่ยนรหัสผ่าน ─── --}}
        <x-card>
            <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-100">
                <div class="w-8 h-8 bg-amber-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-sm font-semibold text-gray-800">เปลี่ยนรหัสผ่าน</h2>
                    <p class="text-xs text-gray-500">อัปเดตรหัสผ่านสำหรับเข้าสู่ระบบ</p>
                </div>
            </div>

            <form action="{{ route('profile.password') }}" method="POST" class="p-5 space-y-3">
                @csrf
                @method('PUT')

                <x-form.input name="current_password" type="password"
                              label="รหัสผ่านปัจจุบัน"
                              placeholder="••••••••"
                              required autocomplete="current-password" />

                <x-form.input name="password" type="password"
                              label="รหัสผ่านใหม่"
                              placeholder="อย่างน้อย 6 ตัวอักษร"
                              required autocomplete="new-password" />

                <x-form.input name="password_confirmation" type="password"
                              label="ยืนยันรหัสผ่านใหม่"
                              placeholder="กรอกอีกครั้ง"
                              required autocomplete="new-password" />

                <div class="flex justify-end pt-1">
                    <x-btn type="submit" variant="amber">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        เปลี่ยนรหัสผ่าน
                    </x-btn>
                </div>
            </form>
        </x-card>

        {{-- ─── Card: ข้อมูลธนาคาร ─── --}}
        <x-card>
            <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-100">
                <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-sm font-semibold text-gray-800">ข้อมูลธนาคาร</h2>
                    <p class="text-xs text-gray-500">บัญชีสำหรับรับค่าคอมมิชชั่น</p>
                </div>
            </div>

            <form action="{{ route('profile.bank') }}" method="POST" class="p-5 space-y-3">
                @csrf
                @method('PUT')

                <x-form.input name="bank_account_name" label="ชื่อบัญชี"
                              :value="$agent->bank_account_name"
                              placeholder="ชื่อ-นามสกุลตามบัญชี" />

                <x-form.input name="bank_name" label="ธนาคาร"
                              :value="$agent->bank_name"
                              placeholder="เช่น กสิกรไทย, ไทยพาณิชย์" />

                <x-form.input name="bank_branch" label="สาขา"
                              :value="$agent->bank_branch"
                              placeholder="สาขาที่เปิดบัญชี" />

                <x-form.input name="bank_account_no" label="เลขบัญชี"
                              :value="$agent->bank_account_no"
                              placeholder="xxx-x-xxxxx-x" />

                <div class="flex justify-end pt-1">
                    <x-btn type="submit" variant="blue">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        บันทึกข้อมูลธนาคาร
                    </x-btn>
                </div>
            </form>
        </x-card>

    </div>
</div>
@endsection
