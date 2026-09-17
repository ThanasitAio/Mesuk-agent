@extends('layouts.app')

@section('title', 'ส่งออกรายงานมิเตอร์')
@section('breadcrumb', 'ส่งออกรายงานมิเตอร์ (Excel)')

@section('content')

<div class="max-w-2xl mx-auto">
    <x-card class="p-5 sm:p-6">
        <h2 class="text-base font-semibold text-gray-800 mb-1">ส่งออกรายงานมิเตอร์น้ำ/ไฟ (Excel)</h2>
        <p class="text-sm text-gray-500 mb-5">เลือกช่วงงวดอ่านมิเตอร์และทรัพย์สินที่ต้องการ แล้วกดดาวน์โหลด ระบบจะสร้างไฟล์ Excel ให้ทันที</p>

        <form method="GET" action="{{ route('meters.export.download') }}" class="space-y-5">
            <x-form.month-year
                name-month="start_month"
                name-year="start_year"
                label="ตั้งแต่งวดอ่านมิเตอร์"
                required
                :value-month="old('start_month', now()->subMonths(5)->month)"
                :value-year="old('start_year', now()->subMonths(5)->year)"
                :year-from="2025"
                :year-to="now()->year + 1"
            />

            <x-form.month-year
                name-month="end_month"
                name-year="end_year"
                label="ถึงงวดอ่านมิเตอร์"
                required
                :value-month="old('end_month', now()->month)"
                :value-year="old('end_year', now()->year)"
                :year-from="2025"
                :year-to="now()->year + 1"
            />

            <x-form.select name="property_code" label="ทรัพย์สิน (ไม่ระบุ = ส่งออกทุกรายการ)" placeholder="ทุกรายการ">
                <option value="">ทุกรายการ</option>
                @foreach($properties as $p)
                    <option value="{{ $p->property_code }}" @selected(old('property_code') === $p->property_code)>
                        {{ $p->property_code }} - {{ $p->title }}
                    </option>
                @endforeach
            </x-form.select>

            <div class="pt-2">
                <x-btn type="submit" variant="primary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/>
                    </svg>
                    ดาวน์โหลดรายงาน Excel
                </x-btn>
            </div>
        </form>
    </x-card>
</div>

@endsection
