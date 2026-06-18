@extends('layouts.app')

@section('title', 'แก้ไขตัวแทน')
@section('breadcrumb', 'ตัวแทน / แก้ไข — ' . $agent->name)

@section('content')
<div class="max-w-3xl mx-auto">
    <x-card>

        {{-- Card Header --}}
        <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-100">
            <div class="w-9 h-9 bg-amber-100 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
            </div>
            <div>
                <h2 class="text-base font-semibold text-gray-800">แก้ไขข้อมูลตัวแทน</h2>
                <p class="text-xs text-gray-500">
                    <span class="font-mono bg-gray-100 px-1.5 py-0.5 rounded text-gray-600">{{ $agent->member_code }}</span>
                    &nbsp;{{ $agent->name }}
                </p>
            </div>
        </div>

        <form action="{{ route('agents.update', $agent) }}" method="POST" class="p-6 space-y-6">
            @csrf
            @method('PUT')

            {{-- ข้อมูลส่วนตัว --}}
            <x-form.section title="ข้อมูลส่วนตัว" accent="amber">
                <div class="md:col-span-2">
                    <x-form.input
                        name="name"
                        label="ชื่อ-นามสกุล"
                        :value="$agent->name"
                        placeholder="กรอกชื่อ-นามสกุล"
                        required />
                </div>

                <x-form.input
                    name="email"
                    type="email"
                    label="อีเมล"
                    :value="$agent->email"
                    placeholder="email@example.com"
                    required
                    autocomplete="email"
                    autocapitalize="none"
                    autocorrect="off" />

                <x-form.input
                    name="phone"
                    type="tel"
                    label="เบอร์โทรศัพท์"
                    :value="$agent->phone"
                    placeholder="เช่น 0812345678"
                    required
                    inputmode="tel" />

                <x-form.select name="status" label="สถานะ" required>
                    <option value="active"   @selected(old('status', $agent->status) === 'active')>ใช้งาน</option>
                    <option value="inactive" @selected(old('status', $agent->status) === 'inactive')>ไม่ใช้งาน</option>
                </x-form.select>
            </x-form.section>

            {{-- เปลี่ยนรหัสผ่าน --}}
            <x-form.section title="เปลี่ยนรหัสผ่าน" accent="amber" subtitle="(เว้นว่างไว้ถ้าไม่ต้องการเปลี่ยน)">
                <x-form.input
                    name="password"
                    type="password"
                    label="รหัสผ่านใหม่"
                    placeholder="อย่างน้อย 6 ตัวอักษร" />

                <x-form.input
                    name="password_confirmation"
                    type="password"
                    label="ยืนยันรหัสผ่านใหม่"
                    placeholder="กรอกรหัสผ่านใหม่อีกครั้ง" />
            </x-form.section>

            {{-- ข้อมูลที่อยู่ --}}
            <x-form.section title="ข้อมูลที่อยู่" accent="amber">
                <div class="md:col-span-2">
                    <x-form.textarea
                        name="address"
                        label="ที่อยู่"
                        :value="$agent->address"
                        placeholder="บ้านเลขที่ ถนน ซอย..." />
                </div>

                <x-form.input
                    name="province"
                    label="จังหวัด"
                    :value="$agent->province"
                    placeholder="เช่น กรุงเทพฯ" />

                <x-form.input
                    name="zipcode"
                    label="รหัสไปรษณีย์"
                    :value="$agent->zipcode"
                    placeholder="เช่น 10100"
                    maxlength="10"
                    inputmode="numeric" />
            </x-form.section>

            {{-- Actions --}}
            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-4 border-t border-gray-100">
                <p class="text-xs text-gray-400">
                    สร้างเมื่อ: {{ $agent->created_at ? $agent->created_at->format('d M Y, H:i') : '—' }}
                    &nbsp;|&nbsp;
                    แก้ไขล่าสุด: {{ $agent->updated_at ? $agent->updated_at->format('d M Y, H:i') : '—' }}
                </p>
                <div class="flex flex-col sm:flex-row items-center gap-3 w-full sm:w-auto">
                    <x-btn href="{{ route('agents.index') }}" variant="secondary" class="w-full sm:w-auto">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        ยกเลิก
                    </x-btn>
                    <x-btn type="submit" variant="amber" class="w-full sm:w-auto">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M5 13l4 4L19 7"/>
                        </svg>
                        บันทึกการแก้ไข
                    </x-btn>
                </div>
            </div>

        </form>
    </x-card>
</div>
@endsection