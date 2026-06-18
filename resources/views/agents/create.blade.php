@extends('layouts.app')

@section('title', 'เพิ่มตัวแทนใหม่')
@section('breadcrumb', 'ตัวแทน / เพิ่มใหม่')

@section('content')
<div class="max-w-3xl mx-auto">
    <x-card>

        {{-- Card Header --}}
        <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-100">
            <div class="w-9 h-9 bg-brand-100 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                </svg>
            </div>
            <div>
                <h2 class="text-base font-semibold text-gray-800">เพิ่มตัวแทนใหม่</h2>
                <p class="text-xs text-gray-500">กรอกข้อมูลที่จำเป็นทั้งหมด</p>
            </div>
        </div>

        <form action="{{ route('agents.store') }}" method="POST" class="p-6 space-y-6">
            @csrf

            {{-- ข้อมูลส่วนตัว --}}
            <x-form.section title="ข้อมูลส่วนตัว" accent="brand">
                <div class="md:col-span-2">
                    <x-form.input
                        name="name"
                        label="ชื่อ-นามสกุล"
                        placeholder="กรอกชื่อ-นามสกุล"
                        required />
                </div>

                <x-form.input
                    name="email"
                    type="email"
                    label="อีเมล"
                    placeholder="email@example.com"
                    required
                    autocomplete="email"
                    autocapitalize="none"
                    autocorrect="off" />

                <x-form.input
                    name="phone"
                    type="tel"
                    label="เบอร์โทรศัพท์"
                    placeholder="เช่น 0812345678"
                    required
                    inputmode="tel" />

                <x-form.select name="status" label="สถานะ" required>
                    <option value="">— เลือกสถานะ —</option>
                    <option value="active"   @selected(old('status') === 'active')>ใช้งาน</option>
                    <option value="inactive" @selected(old('status') === 'inactive')>ไม่ใช้งาน</option>
                </x-form.select>
            </x-form.section>

            {{-- รหัสผ่าน --}}
            <x-form.section title="รหัสผ่าน" accent="brand">
                <x-form.input
                    name="password"
                    type="password"
                    label="รหัสผ่าน"
                    placeholder="อย่างน้อย 6 ตัวอักษร"
                    required
                    autocomplete="new-password" />

                <x-form.input
                    name="password_confirmation"
                    type="password"
                    label="ยืนยันรหัสผ่าน"
                    placeholder="กรอกรหัสผ่านอีกครั้ง" />
            </x-form.section>

            {{-- ข้อมูลที่อยู่ --}}
            <x-form.section title="ข้อมูลที่อยู่" accent="brand">
                <div class="md:col-span-2">
                    <x-form.textarea
                        name="address"
                        label="ที่อยู่"
                        placeholder="บ้านเลขที่ ถนน ซอย..." />
                </div>

                <x-form.input
                    name="province"
                    label="จังหวัด"
                    placeholder="เช่น กรุงเทพฯ" />

                <x-form.input
                    name="zipcode"
                    label="รหัสไปรษณีย์"
                    placeholder="เช่น 10100"
                    maxlength="10"
                    inputmode="numeric" />
            </x-form.section>

            {{-- Actions --}}
            <div class="flex flex-col sm:flex-row items-center justify-end gap-3 pt-4 border-t border-gray-100">
                <x-btn href="{{ route('agents.index') }}" variant="secondary" class="w-full sm:w-auto">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    ยกเลิก
                </x-btn>
                <x-btn type="submit" variant="primary" class="w-full sm:w-auto">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    บันทึก
                </x-btn>
            </div>

        </form>
    </x-card>
</div>
@endsection
