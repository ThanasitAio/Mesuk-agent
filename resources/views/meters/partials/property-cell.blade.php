{{-- ช่องทรัพย์ (แถว desktop + การ์ด mobile): รหัสทรัพย์ (ชื่อทรัพย์ใน title) → เลขที่การจองของงวดนี้
     ล้อ happyest admin/meter-readings/partials/property-cell - $row มาจาก MeterReadingController::index() --}}
<p class="font-mono font-bold text-sm text-gray-800 leading-snug truncate" title="{{ $row->property->title }}">{{ $row->property->property_code ?: 'ไม่มีรหัส' }}</p>
<p class="text-[11px] text-gray-400 leading-snug truncate max-w-[14rem]">{{ $row->property->title }}</p>
@if($row->contract)
    <p class="mt-0.5 text-[11px] font-semibold text-gray-600 font-mono whitespace-nowrap" title="เลขที่การจองที่ใบแจ้งหนี้น้ำ/ไฟของงวดนี้เรียกเก็บ">{{ $row->contract->booking_code }}</p>
@else
    <p class="mt-0.5 text-[11px] text-gray-400">ไม่มีการจองในงวดนี้</p>
@endif
