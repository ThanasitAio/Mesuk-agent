{{-- เลขที่ใบแจ้งหนี้น้ำ/ไฟของงวดนี้ ใต้ชิปสถานะ (ล้อ happyest admin/meter-readings/partials/invoice-codes)
     แอปนี้ไม่มีหน้าใบแจ้งหนี้น้ำ/ไฟ จึงแสดงเป็นข้อความ ไม่ใช่ลิงก์ --}}
@if(! empty($row->invoice_codes))
    <div class="mt-1 flex flex-col items-center gap-0.5">
        @foreach($row->invoice_codes as $invoiceCode)
            <span class="font-mono text-[11px] font-semibold text-brand-700 whitespace-nowrap" title="เลขที่ใบแจ้งหนี้น้ำ/ไฟของงวดนี้">{{ $invoiceCode }}</span>
        @endforeach
    </div>
@endif
