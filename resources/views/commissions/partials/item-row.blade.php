{{-- หนึ่งรายการค่าคอม (ดูอย่างเดียว): $item, $properties, $stmtMonth (null = อยู่ในรายการรอ) --}}
@php
    use App\Helpers\CommissionLedger as L;
    $prop      = $item->property_id ? ($properties[$item->property_id] ?? null) : null;
    $amount    = $item->amount();
    $isMgmt    = $item->kind === 'management';
    $isFinder  = $item->kind === 'finder';
    $isManual  = $item->kind === 'manual';
    $late      = $stmtMonth && $isMgmt && $item->period < $stmtMonth;
    $overdue   = (! $stmtMonth && $item->due_date && $item->due_date->lt(today())) ? (int) $item->due_date->diffInDays(today()) : 0;

    if ($isMgmt) {
        $headline = 'ค่าเช่า '.L::monthLabel($item->period);
        $formula  = L::money((float) $item->base_amount).' x '.L::pct((float) $item->factor).'%';
    } elseif ($isFinder) {
        $headline = 'ค่าหาผู้เช่า · '.($item->description ?: 'สัญญาเช่า');
        $formula  = (float) $item->factor > 0
            ? 'ค่าเช่า '.L::money((float) $item->base_amount).' x '.(int) $item->factor.' เดือน'
            : 'สัญญาไม่ถึง 1 ปี ไม่มีค่าหาผู้เช่า';
    } else {
        $headline = $item->description ?: 'รายการปรับยอด';
        $formula  = null;
    }
@endphp
<div class="cm-row flex items-start gap-3 px-4 sm:px-5 py-3.5 {{ $item->is_excluded ? 'bg-gray-50/70' : '' }}">
    {{-- รหัสทรัพย์ / ไอคอนรายการปรับยอด --}}
    @if($item->property_code)
        <span class="flex-shrink-0 mt-0.5 inline-flex items-center justify-center min-w-[3.5rem] px-2 py-1 rounded-lg bg-brand-50 text-brand-800 font-mono text-xs font-bold">
            {{ $item->property_code }}
        </span>
    @else
        <span class="flex-shrink-0 mt-0.5 inline-flex items-center justify-center w-14 h-7 rounded-lg {{ $amount < 0 ? 'bg-rose-50 text-rose-600' : 'bg-sky-50 text-sky-700' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                @if($amount < 0)
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M20 12H4"/>
                @else
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 4v16m8-8H4"/>
                @endif
            </svg>
        </span>
    @endif

    <div class="flex-1 min-w-0">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-sm font-semibold text-gray-800 leading-snug {{ $item->is_excluded ? 'line-through text-gray-400' : '' }}">{{ $headline }}</p>
                @if($prop && $prop->title)
                    <p class="text-xs text-gray-400 truncate">{{ \Illuminate\Support\Str::limit($prop->title, 25) }}</p>
                @endif
            </div>
            <div class="text-right flex-shrink-0">
                @if($item->override_amount !== null)
                    <p class="text-[11px] text-gray-400 line-through tabular-nums leading-none mb-0.5">{{ L::money((float) $item->calc_amount) }}</p>
                @endif
                <p class="text-sm font-bold tabular-nums {{ $item->is_excluded ? 'text-gray-300 line-through' : ($amount < 0 ? 'text-rose-600' : 'text-gray-900') }}">
                    {{ $amount < 0 ? '-' : '' }}{{ L::money(abs($amount)) }}
                </p>
            </div>
        </div>

        {{-- ที่มาของตัวเลข --}}
        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500">
            @if($formula)
                <span class="inline-flex items-center gap-1 tabular-nums">
                    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    {{ $formula }}
                </span>
            @endif
            @if($isMgmt && $item->description)
                <span>{{ $item->description }}</span>
            @endif

            @if($stmtMonth)
                @if($item->ready_at && ! $isManual)
                    <span class="inline-flex items-center gap-1 text-emerald-700">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M5 13l4 4L19 7"/></svg>
                        {{ $isFinder ? 'เข้าใช้งาน' : 'ผู้เช่าชำระ' }} {{ L::dateLabel($item->ready_at) }}
                    </span>
                @endif
            @else
                <span class="inline-flex items-center gap-1 {{ $overdue > 0 ? 'text-rose-600' : 'text-amber-700' }}">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ $item->wait_reason ?: 'รอดำเนินการ' }}@if($overdue > 0) · เลยกำหนด {{ $overdue }} วัน @elseif($item->due_date) · {{ $isFinder ? 'เริ่มสัญญา' : 'ครบกำหนด' }} {{ L::dateLabel($item->due_date) }} @endif
                </span>
            @endif

            @if($late)
                <span class="inline-flex items-center gap-1 text-amber-700">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                    ค้างจากงวด {{ L::monthLabel($item->period) }}
                </span>
            @endif
            @if($item->source === 'carried')
                <span class="inline-flex items-center gap-1 text-sky-700">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/></svg>
                    ยอดยกมา
                </span>
            @endif
            @if($isManual)
                <span class="inline-flex items-center gap-1 text-sky-700">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    ฝ่ายบัญชีเพิ่ม
                </span>
            @endif
        </div>

        {{-- เหตุผลที่ฝ่ายบัญชีแก้ --}}
        @if(($item->isEdited() || $isManual) && $item->note)
            <p class="mt-1.5 text-xs text-gray-600 bg-amber-50 rounded-lg px-2.5 py-1.5 leading-relaxed">
                <span class="font-semibold text-amber-800">{{ $item->is_excluded ? 'ไม่จ่ายรายการนี้' : ($isManual ? 'หมายเหตุ' : 'ฝ่ายบัญชีแก้ยอด') }}:</span>
                {{ $item->note }}
            </p>
        @elseif($item->is_excluded)
            <p class="mt-1.5 text-xs text-amber-800">ไม่จ่ายรายการนี้</p>
        @endif
    </div>

</div>
