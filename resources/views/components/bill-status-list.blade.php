{{--
    รายการสถานะสลิปรายบิลของทรัพย์ 1 แถว (ใช้ร่วมกันการ์ดมือถือ + ตารางเดสก์ท็อปของหน้า /properties)
    แสดงทุกบิลที่ต้องรู้ แยกตามสถานะ ไม่ใช่แค่สถานะที่แย่ที่สุด - ผู้บริหารเห็นว่าบิลไหนแนบแล้ว บิลไหนยังค้าง
    $items = รายการจาก properties/index (state, label, due, at) · $lastSlipAt = แนบล่าสุด (ใช้ตอนไม่มีรายการค้าง)
--}}
@props(['items', 'lastSlipAt' => null, 'max' => 3])

@php
    $styles = [
        'rejected'  => ['dot' => 'bg-red-500',    'text' => 'text-red-600',    'label' => 'สลิปถูกปฏิเสธ แนบใหม่'],
        'needed'    => ['dot' => 'bg-amber-500',  'text' => 'text-amber-600',  'label' => 'ยังไม่แนบสลิป'],
        'partial'   => ['dot' => 'bg-orange-500', 'text' => 'text-orange-600', 'label' => 'ชำระบางส่วน'],
        'verify'    => ['dot' => 'bg-blue-500',   'text' => 'text-blue-600',   'label' => 'แนบแล้ว รอตรวจสอบ'],
        'noinvoice' => ['dot' => 'bg-gray-300',   'text' => 'text-gray-500',   'label' => 'รอเปิดใบแจ้งหนี้'],
    ];
    $shown  = $items->take($max);
    $hidden = $items->count() - $shown->count();
@endphp

<div {{ $attributes->merge(['class' => 'space-y-1']) }}>
    @forelse($shown as $it)
        @php $st = $styles[$it->state]; @endphp
        <div class="flex items-start gap-1.5 text-[11px] leading-snug">
            <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 mt-[5px] {{ $st['dot'] }}"></span>
            <span class="min-w-0">
                <span class="font-semibold text-gray-700">{{ $it->label }}</span>
                <span class="font-semibold {{ $st['text'] }}">· {{ $st['label'] }}</span>
                @if($it->state === 'verify' && $it->at)
                    <span class="text-gray-400">· แนบ {{ $it->at->locale('th')->translatedFormat('j M') }}</span>
                @elseif(in_array($it->state, ['needed', 'rejected'], true) && $it->due)
                    <span class="{{ $it->due->lt(now()->startOfDay()) ? 'text-red-500 font-semibold' : 'text-gray-400' }}">· {{ $it->due->lt(now()->startOfDay()) ? 'เกินกำหนด' : 'ครบ' }} {{ $it->due->locale('th')->translatedFormat('j M') }}</span>
                @endif
            </span>
        </div>
    @empty
        <div class="flex items-center gap-1.5 text-[11px] font-semibold text-green-600">
            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
            </svg>
            <span>ไม่มีรายการค้างแนบสลิป@if($lastSlipAt) <span class="font-normal text-gray-400">· แนบล่าสุด {{ $lastSlipAt->locale('th')->translatedFormat('j M Y') }}</span>@endif</span>
        </div>
    @endforelse
    @if($hidden > 0)
        <p class="text-[10px] text-gray-400 pl-3">และอีก {{ $hidden }} รายการ - แตะเพื่อดูทั้งหมด</p>
    @endif
</div>
