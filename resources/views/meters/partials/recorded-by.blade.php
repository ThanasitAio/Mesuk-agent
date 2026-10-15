{{-- บันทึกล่าสุดเมื่อไร โดยใคร (แถว desktop + การ์ด mobile) - $recorded มาจาก MeterReadingController::latestRecorders()
     null = ยังไม่จดงวดนี้ / role: agent = ผู้บริหารโครงการบันทึกจากแอปนี้ (สีเขียว), admin = แอดมินบันทึกแทน (สีน้ำเงิน)
     ล้อ happyest admin/meter-readings/partials/recorded-by - ข้อความเต็ม (บันทึก/แก้ไข, บันทึกครั้งแรก) อยู่ใน title
     ต้องมี $stamp (วันเวลาแบบไทย) จากหน้าที่ include --}}
@if($recorded)
    @php
        $isAgent = $recorded->role === 'agent';
        $who     = $isAgent ? 'ผู้บริหารโครงการ' : 'แอดมิน' . ($recorded->name ? ' ' . $recorded->name : '');
        $tip     = ($recorded->edited ? 'แก้ไข ' : 'บันทึก ') . $stamp($recorded->at) . ' โดย ' . $who
            . ($recorded->edited && $recorded->first_at ? ' · บันทึกครั้งแรก ' . $stamp($recorded->first_at) : '');
    @endphp
    <div class="flex flex-col max-w-[11rem] text-xs leading-snug" title="{{ $tip }}">
        <span class="font-semibold text-gray-600 whitespace-nowrap">{{ $stamp($recorded->at) }}</span>
        <span class="font-semibold truncate {{ $isAgent ? 'text-emerald-700' : 'text-blue-700' }}">{{ $who }}</span>
    </div>
@else
    <span class="text-xs text-gray-300">-</span>
@endif
