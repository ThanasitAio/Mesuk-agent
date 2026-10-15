{{-- เลขมิเตอร์เดือนก่อน → เดือนนี้ 1 บรรทัดต่อมิเตอร์ (น้ำก่อนไฟ) - $items มาจาก MeterReadingController::index() (meter_items)
     มิเตอร์ที่ยังไม่จดงวดนี้ (missing) = "ยังไม่จด" - ล้อ happyest admin/utility-invoices/partials/meter-readings --}}
@php $meterItems = collect($items ?? [])->filter(fn ($it) => ! empty($it['meter_type'])); @endphp
@if($meterItems->isEmpty())
    <span class="text-xs text-gray-300">-</span>
@else
    <div class="flex flex-col gap-0.5">
        @foreach($meterItems as $it)
            @php $isWater = $it['meter_type'] === 'water'; @endphp
            <div class="flex items-center gap-1.5 text-xs tabular-nums whitespace-nowrap">
                <span class="w-4 text-center flex-shrink-0" title="{{ $isWater ? 'น้ำ' : 'ไฟฟ้า' }}">{{ $isWater ? '💧' : '⚡' }}</span>
                @if(! empty($it['missing']))
                    <span class="font-semibold text-amber-600">ยังไม่จด</span>
                @else
                    <span class="text-gray-500">{{ isset($it['previous_reading']) ? number_format($it['previous_reading']) : '-' }}</span>
                    <svg class="w-3 h-3 text-gray-300 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                    <span class="font-semibold text-gray-800">{{ isset($it['current_reading']) ? number_format($it['current_reading']) : '-' }}</span>
                    @isset($it['units_used'])
                        <span class="text-gray-400">({{ number_format($it['units_used'], 2) }} หน่วย)</span>
                    @endisset
                @endif
            </div>
        @endforeach
    </div>
@endif
