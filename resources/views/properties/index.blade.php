@extends('layouts.app')

@section('title', 'อสังหาริมทรัพย์')
@section('breadcrumb', 'รายการทรัพย์สินในความรับผิดชอบ')

@push('styles')
<style>
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(6px); }
    to   { opacity: 1; transform: translateY(0); }
}
.property-row {
    animation: fadeIn 0.25s ease-out;
    transition: background-color 0.15s;
}
.overflow-x-auto::-webkit-scrollbar { height: 6px; }
.overflow-x-auto::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
.overflow-x-auto::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }

/* ลำดับในตารางนับเฉพาะแถวที่แสดงอยู่จริง (ไม่นับแถวที่ถูกซ่อนด้วย x-show ระหว่างกรอง/ค้นหา) */
tbody { counter-reset: rownum; }
tbody tr.property-row { counter-increment: rownum; }
tbody tr.property-row .row-num::before { content: counter(rownum); }
</style>
@endpush

@section('content')

@php
    $happyestPublic  = rtrim(env('HAPPYEST_APP_URL', 'http://127.0.0.1/happyest/public'), '/');
    $totalAll        = $withContract->count() + $withoutContract->count();

    $today          = now()->startOfDay();
    $currentMonthEnd = now()->endOfMonth();

    // เป็น "รอแนบสลิป" เฉพาะรายการที่ครบกำหนดชำระแล้ว หรืออยู่ในรอบเดือนปัจจุบัน
    // (due_date <= สิ้นเดือนนี้) เพื่อให้ผู้บริหารเห็นรายการค่าเช่าเดือนปัจจุบันที่ถึงรอบ
    // ตั้งแต่ต้นเดือน ไม่ใช่รอจนถึงวันครบกำหนดจริงถึงจะขึ้นเตือน - ไม่รวมรายการเดือนถัดไป
    // รายการสถานะสลิปรายบิล (ค่าเช่า/ค่าน้ำไฟ/มัดจำ แต่ละเดือน) ของ 1 สัญญา - สถานะคำนวณจาก
    // HrPaymentRecord::slipUiState() ตัวเดียวกับหน้ารอบบิล ให้ข้อความตรงกันทั้งสองหน้า
    // เรียง: ปฏิเสธ → ยังไม่แนบ → บางส่วน → รอตรวจ → รอใบแจ้งหนี้ แล้วตามวันครบกำหนด
    $billItemsFor = function ($recs, $booking, bool $eligible) use ($currentMonthEnd) {
        $order = ['rejected' => 0, 'needed' => 1, 'partial' => 2, 'verify' => 3, 'noinvoice' => 4];

        return $recs
            ->map(function ($r) use ($booking, $eligible, $currentMonthEnd, $order) {
                $state = $r->slipUiState($booking, $eligible, $currentMonthEnd);
                if (! isset($order[$state])) {
                    return null;
                }

                return (object) [
                    'state' => $state,
                    'label' => $r->getBillLabel(),
                    'type'  => $r->payment_type,
                    'due'   => $r->due_date,
                    'at'    => $r->lastSlipUploadedAt(),
                ];
            })
            ->filter()
            ->sortBy(fn ($i) => [$order[$i->state], $i->due?->timestamp ?? 0])
            ->values();
    };

    // แสดงรอแนบสลิปได้ก็ต่อเมื่ออสังหาสถานะไม่ว่าง (property_status_id ไม่ใช่ available)
    $isPropertyVacant = fn($p) => optional($p->propertyStatus)->slug === 'available';

    // ─── Badge color lookup (ใช้ร่วมกันทั้งการ์ดมือถือ และตารางเดสก์ท็อป) ───
    $badgeClasses = [
        'red'    => 'text-red-700 bg-red-50 border-red-200',
        'blue'   => 'text-blue-700 bg-blue-50 border-blue-200',
        'yellow' => 'text-yellow-700 bg-yellow-50 border-yellow-200',
        'green'  => 'text-green-700 bg-green-50 border-green-200',
        'gray'   => 'text-gray-600 bg-gray-50 border-gray-200',
    ];
    $dotClasses = [
        'red' => 'bg-red-500', 'blue' => 'bg-blue-500', 'yellow' => 'bg-yellow-500',
        'green' => 'bg-green-500', 'gray' => 'bg-gray-400',
    ];
    // สถานะพื้นที่ของทรัพย์ (ทั้งที่มีและไม่มีสัญญา) - อ้างอิงจาก property_status_id จริง ให้ตรงกับ
    // แอดมิน happyest และหน้าเว็บ (ว่าง / ไม่ว่าง / จอง / โครงการในอนาคต) ไม่ใช้สถานะ booking มาแปลงเป็นสถานะพื้นที่
    $vacantStatusMap = [
        'available'      => ['color' => 'green',  'label' => 'ว่าง'],
        'unavailable'    => ['color' => 'red',    'label' => 'ไม่ว่าง'],
        'booked'         => ['color' => 'yellow', 'label' => 'จอง'],
        'future_project' => ['color' => 'blue',   'label' => 'โครงการในอนาคต'],
    ];

    $areaStatusOf = function ($property) use ($vacantStatusMap) {
        $slug   = optional($property->propertyStatus)->slug ?? 'available';
        $status = $vacantStatusMap[$slug] ?? $vacantStatusMap['available'];

        // ประเภทสำหรับใช้กรองแท็บ - สถานะ "ไม่ว่าง" (unavailable) ใช้แท็บ "ไม่ว่าง" (active)
        $filterType = match ($slug) {
            'unavailable'    => 'active',
            'booked'         => 'booked',
            'future_project' => 'future_project',
            default          => 'available',
        };

        return (object) [
            'slug'       => $slug,
            'color'      => $status['color'],
            'label'      => $status['label'],
            'filterType' => $filterType,
        ];
    };

    $resolveImageUrl = function ($property) use ($happyestPublic) {
        $media = $property->primaryImageMedia;
        if (! $media || ! $media->file_path) {
            return null;
        }

        return str_starts_with($media->file_path, 'http')
            ? $media->file_path
            : $happyestPublic . '/storage/' . $media->file_path;
    };

    // ─── เตรียมข้อมูลแสดงผลล่วงหน้า (คำนวณครั้งเดียว ใช้ได้ทั้งการ์ดมือถือ/ตารางเดสก์ท็อป) ───
    $contractRows = $withContract->flatMap(function ($property) use ($happyestPublic, $billItemsFor, $isPropertyVacant, $areaStatusOf, $resolveImageUrl) {
        // ปกติมี active booking แค่รายการเดียว แต่ช่วงต่อสัญญา (สัญญาเดิมยังไม่ปิด + สัญญาใหม่เปิดแล้ว)
        // อาจซ้อนกัน 2 รายการ - สร้างแยกเป็นคนละแถวเพื่อไม่ให้รายการใดถูกซ่อนไป
        $bookings      = $property->activeBookings->sortBy('id')->values();
        $isOverlapping = $bookings->count() > 1;

        return $bookings->map(function ($booking) use ($property, $happyestPublic, $billItemsFor, $isPropertyVacant, $areaStatusOf, $resolveImageUrl, $isOverlapping) {
        $tenant        = $booking?->customer;
        $bookingStatus = $booking?->status ?? 'pending';

        $tenantPhotoUrl = null;
        if ($tenant?->photo) {
            $ph = $tenant->photo;
            $tenantPhotoUrl = str_starts_with($ph, 'http') ? $ph : $happyestPublic . '/storage/' . $ph;
        } elseif ($tenant?->avatar && $tenant?->provider_id) {
            $av = $tenant->avatar;
            $tenantPhotoUrl = str_starts_with($av, 'http') ? $av : $happyestPublic . '/storage/' . $av;
        }
        $tenantInitial = $tenant ? mb_strtoupper(mb_substr($tenant->full_name ?? '?', 0, 1)) : '?';
        $tenantFullName = $tenant?->full_name ?? '(ไม่ระบุ)';
        $tenantNameShort = $tenant && mb_strlen($tenantFullName) > 10
            ? mb_substr($tenantFullName, 0, 10) . '...'
            : $tenantFullName;

        // Dates
        $checkIn       = $booking?->check_in;
        $actualMoveIn  = $booking?->actual_move_in_date;
        $contractStart = $booking?->contract_start_date;
        $contractEnd   = $booking?->check_out;
        $rentalMonths  = $booking?->rental_months ?? 0;

        // Payment records
        $records      = $booking?->paymentRecords ?? collect();
        $isVacant     = $isPropertyVacant($property);
        $slipEligible = ! $isVacant && $bookingStatus !== 'deposit_confirmed';

        // สถานะสลิปรายบิล - แสดงทุกบิลที่ค้างหรือรอตรวจ (ไม่ใช่แค่สถานะที่แย่ที่สุดของทรัพย์) กันเข้าใจผิดว่า
        // "แนบแล้วแต่ข้อมูลหาย" เมื่อทรัพย์มีทั้งบิลที่แนบแล้วและบิลที่ยังค้างอยู่พร้อมกัน
        $billItems   = $billItemsFor($records, $booking, $slipEligible);
        $actionItems = $billItems->whereIn('state', ['needed', 'rejected']);

        $slipNeeded        = $slipEligible && $actionItems->isNotEmpty();
        $slipPendingVerify = $billItems->where('state', 'verify')->isNotEmpty();
        $lastSlipAt        = $records->whereNotNull('paid_at')->sortByDesc('paid_at')->first()?->paid_at;
        $pendingSlipHasRent    = $actionItems->whereIn('type', ['monthly_rent', 'late_fee'])->isNotEmpty();
        $pendingSlipHasUtility = $actionItems->where('type', 'utility')->isNotEmpty();

        // สถานะการแนบสลิปแยกตามเดือนที่ครบกำหนด (due_date) สำหรับตัวกรอง "เดือนการแนบสลิป" -
        // needed = ยังไม่แนบ (นิยามเดียวกับ $slipNeeded) · verify = แนบแล้วรอตรวจ · paid = ชำระแล้ว
        // ค่าน้ำ/ไฟมี due_date พร้อมค่าเช่าของเดือนนั้น จึงอยู่เดือนเดียวกับค่าเช่าที่เรียกเก็บด้วยกัน
        $neededMonths = $slipEligible
            ? $actionItems->filter(fn ($i) => $i->due)->map(fn ($i) => $i->due->format('Y-m'))->unique()
            : collect();
        $slipMonths = [];
        foreach ($records->filter(fn ($r) => $r->due_date)->groupBy(fn ($r) => $r->due_date->format('Y-m')) as $ym => $monthRecs) {
            $monthStatuses = $monthRecs->pluck('payment_status');
            $monthState = match (true) {
                $neededMonths->contains($ym)                         => 'needed',
                $monthStatuses->contains('pending_verification')     => 'verify',
                $monthStatuses->contains('paid')                     => 'paid',
                default                                              => null,
            };
            if ($monthState) {
                $slipMonths[(string) $ym] = $monthState;
            }
        }

        $searchText = strtolower(
            ($property->title ?? '') . ' ' .
            ($property->property_code ?? '') . ' ' .
            ($booking?->booking_code ?? '') . ' ' .
            ($tenant?->full_name ?? '') . ' ' .
            ($tenant?->mobile ?? '')
        );

        $area = $areaStatusOf($property);

        return (object) [
            'property'          => $property,
            'booking'           => $booking,
            'bookingId'         => $booking->id,
            'isOverlapping'     => $isOverlapping,
            'tenant'            => $tenant,
            'tenantPhotoUrl'    => $tenantPhotoUrl,
            'tenantInitial'     => $tenantInitial,
            'tenantFullName'    => $tenantFullName,
            'tenantNameShort'   => $tenantNameShort,
            'imageUrl'          => $resolveImageUrl($property),
            'checkIn'           => $checkIn,
            'actualMoveIn'      => $actualMoveIn,
            'contractStart'     => $contractStart,
            'contractEnd'       => $contractEnd,
            'rentalMonths'      => $rentalMonths,
            'slipNeeded'        => $slipNeeded,
            'pendingSlipHasRent' => $pendingSlipHasRent,
            'pendingSlipHasUtility' => $pendingSlipHasUtility,
            'slipPendingVerify' => $slipPendingVerify,
            'slipMonths'        => $slipMonths,
            'lastSlipAt'        => $lastSlipAt,
            'billItems'         => $billItems,
            'searchText'        => $searchText,
            'statusColor'       => $area->color,
            'statusLabel'       => $area->label,
            'statusPulse'       => $area->slug === 'unavailable',
            'slug'              => $area->slug,
            'filterType'        => $area->filterType,
        ];
        });
    })->values();

    $vacantRows = $withoutContract->map(function ($property) use ($resolveImageUrl, $areaStatusOf) {
        $area = $areaStatusOf($property);

        return (object) [
            'property'    => $property,
            'imageUrl'    => $resolveImageUrl($property),
            'searchText'  => strtolower(($property->title ?? '') . ' ' . ($property->property_code ?? '')),
            'statusColor' => $area->color,
            'statusLabel' => $area->label,
            'slug'        => $area->slug,
            'filterType'  => $area->filterType,
        ];
    });

    // ─── รวมยอด/รายการรอสลิป คำนวณจากแถว (ต่อ booking) ไม่ใช่ต่อทรัพย์ เพราะทรัพย์ที่มีสัญญาซ้อนกัน
    // (ต่อสัญญา) ควรนับภาระของทั้ง 2 สัญญาแยกกัน ───
    $fmtAmt           = fn($a) => ((float) $a != floor((float) $a)) ? number_format((float) $a, 2) : number_format((int) $a);
    $totalSlipNeeded  = $contractRows->where('slipNeeded', true)->count();
    $totalSlipNeededRent = $contractRows
        ->filter(fn($row) => $row->slipNeeded && $row->pendingSlipHasRent)
        ->pluck('property.id')
        ->unique()
        ->count();
    $totalSlipNeededUtility = $contractRows
        ->filter(fn($row) => $row->slipNeeded && $row->pendingSlipHasUtility)
        ->pluck('property.id')
        ->unique()
        ->count();
    $totalSlipVerify  = $contractRows->where('slipPendingVerify', true)->count();

    // ─── จำนวนแยกตามสถานะพื้นที่จริง (ว่าง / ไม่ว่าง / จอง / โครงการในอนาคต) ───
    // นับตามจำนวนทรัพย์จริง (unique) ไม่ใช่ตามจำนวนแถว เพราะทรัพย์ที่มีสัญญาซ้อนกัน
    // (ต่อสัญญา) จะมีมากกว่า 1 แถวใน $contractRows แต่ยังนับเป็นทรัพย์เดียว
    $propertyAreaRows  = $contractRows->unique(fn ($row) => $row->property->id)->concat($vacantRows);
    $totalActive       = $propertyAreaRows->where('slug', 'unavailable')->count();
    $totalAvailable    = $propertyAreaRows->where('slug', 'available')->count();
    $totalBooked       = $propertyAreaRows->where('slug', 'booked')->count();
    $totalFutureProject = $propertyAreaRows->where('slug', 'future_project')->count();

    // ─── ตัวเลือกตัวกรอง "เดือนการแนบสลิป" - เดือนที่มีรายการชำระของทรัพย์ที่ดูแล เรียงใหม่ → เก่า ───
    $thaiMonths = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม',
    ];
    $slipMonthOptions = $contractRows
        ->flatMap(fn ($row) => array_keys($row->slipMonths))
        ->unique()
        ->sortDesc()
        ->values()
        ->mapWithKeys(function ($ym) use ($thaiMonths) {
            [$y, $m] = array_map('intval', explode('-', $ym));

            return [$ym => ($thaiMonths[$m] ?? $m) . ' ' . ($y + 543)];
        })
        ->all();

    // ─── รายการทั้งหมดแบบย่อ ใช้เช็คว่ามีแถวที่ตรงกับแท็บ/คำค้นหาที่เลือกอยู่หรือไม่ ───
    $allRowsForMatch = $contractRows
        ->map(fn ($row) => [
            'type'          => $row->filterType,
            'text'          => $row->searchText,
            'slipNeeded'    => $row->slipNeeded,
            'slipVerifying' => $row->slipPendingVerify,
            'months'        => $row->slipMonths,
        ])
        ->concat($vacantRows->map(fn ($row) => [
            'type'          => $row->filterType,
            'text'          => $row->searchText,
            'slipNeeded'    => false,
            'slipVerifying' => false,
            'months'        => [],
        ]))
        ->values();
@endphp

<div x-data="{
    search: '',
    filter: 'all',
    slipMonth: '',
    monthLabels: @js($slipMonthOptions),
    rows: @js($allRowsForMatch),
    // เลือกเดือนแล้ว: แท็บรอสลิป/แนบแล้ว และตัวเลขนับ ดูเฉพาะรายการที่ครบกำหนดในเดือนนั้น (ทรัพย์ที่ไม่มีรายการเดือนนั้นถูกซ่อน)
    monthStateOf(months) {
        return (this.slipMonth && months && months[this.slipMonth]) || null;
    },
    matchRow(type, text, slipNeeded, slipVerifying, months) {
        if (this.slipMonth) {
            const st = this.monthStateOf(months);
            if (!st) return false;
            if (this.filter === 'slip_needed' && st !== 'needed') return false;
            if (this.filter === 'slip_verify' && st !== 'verify') return false;
        } else {
            if (this.filter === 'slip_needed') return slipNeeded;
            if (this.filter === 'slip_verify') return slipVerifying;
        }
        if (this.filter !== 'all' && this.filter !== 'slip_needed' && this.filter !== 'slip_verify' && this.filter !== type) return false;
        if (!this.search.trim()) return true;
        return text.toLowerCase().includes(this.search.toLowerCase().trim());
    },
    get hasMatches() {
        return this.rows.some(r => this.matchRow(r.type, r.text, r.slipNeeded, r.slipVerifying, r.months));
    },
    get slipNeededCount() {
        return this.rows.filter(r => this.slipMonth ? this.monthStateOf(r.months) === 'needed' : r.slipNeeded).length;
    },
    get slipVerifyCount() {
        return this.rows.filter(r => this.slipMonth ? this.monthStateOf(r.months) === 'verify' : r.slipVerifying).length;
    },
    get slipMonthLabel() {
        return this.monthLabels[this.slipMonth] || '';
    },
    monthStatusText(months) {
        return { needed: 'ยังไม่แนบสลิป', verify: 'แนบแล้ว รอตรวจสอบ', paid: 'ชำระแล้ว' }[this.monthStateOf(months)] || '';
    },
    monthStatusClass(months) {
        return {
            needed: 'text-amber-700 bg-amber-50 border-amber-200',
            verify: 'text-blue-700 bg-blue-50 border-blue-200',
            paid:   'text-green-700 bg-green-50 border-green-200',
        }[this.monthStateOf(months)] || '';
    }
}">

{{-- ===== Summary Stats (KPI cards) ===== --}}
<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-2.5 sm:gap-3 mb-5">
    <div class="relative overflow-hidden bg-white rounded-2xl shadow-sm border border-gray-100 p-3 sm:p-4 transition-all duration-300 hover:shadow-lg hover:-translate-y-0.5">
        <div class="pointer-events-none absolute -top-4 -right-4 w-16 h-16 sm:w-20 sm:h-20 rounded-full bg-slate-50"></div>
        <div class="relative flex items-start justify-between gap-1.5">
            <div class="min-w-0">
                <p class="text-xl sm:text-2xl font-black text-gray-900 tabular-nums leading-none">{{ $totalAll }}</p>
                <p class="text-[10px] sm:text-xs text-gray-500 font-medium mt-1.5 leading-tight">ทรัพย์ทั้งหมด</p>
            </div>
            <div class="w-8 h-8 sm:w-10 sm:h-10 flex-shrink-0 rounded-xl bg-gradient-to-br from-slate-500 to-slate-700 flex items-center justify-center shadow-md shadow-slate-200">
                <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
            </div>
        </div>
    </div>
    <div class="relative overflow-hidden bg-white rounded-2xl shadow-sm border border-gray-100 p-3 sm:p-4 transition-all duration-300 hover:shadow-lg hover:-translate-y-0.5">
        <div class="pointer-events-none absolute -top-4 -right-4 w-16 h-16 sm:w-20 sm:h-20 rounded-full bg-red-50"></div>
        <div class="relative flex items-start justify-between gap-1.5">
            <div class="min-w-0">
                <p class="text-xl sm:text-2xl font-black text-red-600 tabular-nums leading-none">{{ $totalActive }}</p>
                <p class="text-[10px] sm:text-xs text-red-500 font-medium mt-1.5 leading-tight">ไม่ว่าง</p>
            </div>
            <div class="w-8 h-8 sm:w-10 sm:h-10 flex-shrink-0 rounded-xl bg-gradient-to-br from-red-400 to-red-600 flex items-center justify-center shadow-md shadow-red-200">
                <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
        </div>
    </div>
    <div class="relative overflow-hidden bg-white rounded-2xl shadow-sm border border-gray-100 p-3 sm:p-4 transition-all duration-300 hover:shadow-lg hover:-translate-y-0.5">
        <div class="pointer-events-none absolute -top-4 -right-4 w-16 h-16 sm:w-20 sm:h-20 rounded-full bg-emerald-50"></div>
        <div class="relative flex items-start justify-between gap-1.5">
            <div class="min-w-0">
                <p class="text-xl sm:text-2xl font-black text-green-600 tabular-nums leading-none">{{ $totalAvailable }}</p>
                <p class="text-[10px] sm:text-xs text-green-500 font-medium mt-1.5 leading-tight">ว่าง</p>
            </div>
            <div class="w-8 h-8 sm:w-10 sm:h-10 flex-shrink-0 rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center shadow-md shadow-emerald-200">
                <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
    </div>
    <div class="relative overflow-hidden bg-white rounded-2xl shadow-sm border border-gray-100 p-3 sm:p-4 transition-all duration-300 hover:shadow-lg hover:-translate-y-0.5">
        <div class="pointer-events-none absolute -top-4 -right-4 w-16 h-16 sm:w-20 sm:h-20 rounded-full bg-amber-50"></div>
        <div class="relative flex items-start justify-between gap-1.5">
            <div class="min-w-0">
                <p class="text-xl sm:text-2xl font-black text-yellow-600 tabular-nums leading-none">{{ $totalBooked }}</p>
                <p class="text-[10px] sm:text-xs text-yellow-600 font-medium mt-1.5 leading-tight">จอง</p>
            </div>
            <div class="w-8 h-8 sm:w-10 sm:h-10 flex-shrink-0 rounded-xl bg-gradient-to-br from-yellow-400 to-yellow-600 flex items-center justify-center shadow-md shadow-yellow-200">
                <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
        </div>
    </div>
    <div class="relative overflow-hidden bg-white rounded-2xl shadow-sm border border-gray-100 p-3 sm:p-4 transition-all duration-300 hover:shadow-lg hover:-translate-y-0.5">
        <div class="pointer-events-none absolute -top-4 -right-4 w-16 h-16 sm:w-20 sm:h-20 rounded-full bg-blue-50"></div>
        <div class="relative flex items-start justify-between gap-1.5">
            <div class="min-w-0">
                <p class="text-xl sm:text-2xl font-black text-blue-600 tabular-nums leading-none">{{ $totalFutureProject }}</p>
                <p class="text-[10px] sm:text-xs text-blue-500 font-medium mt-1.5 leading-tight">โครงการในอนาคต</p>
            </div>
            <div class="w-8 h-8 sm:w-10 sm:h-10 flex-shrink-0 rounded-xl bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center shadow-md shadow-blue-200">
                <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21h18M5 21V7l8-4v18M13 21V11l6 4v6M9 9h0M9 12h0M9 15h0"/>
                </svg>
            </div>
        </div>
    </div>
</div>

{{-- ===== Slip Alert Banners ===== --}}
{{-- แบนเนอร์/แท็บรอสลิป-แนบแล้ว ตัวเลขและการแสดงตามตัวกรองเดือน (x-show) - ไม่เลือกเดือน = ตัวเลขเดิม --}}
@if($totalSlipNeeded > 0 || $totalSlipVerify > 0 || ! empty($slipMonthOptions))
<button type="button"
        x-show="slipNeededCount > 0" @if($totalSlipNeeded === 0) x-cloak @endif
        x-on:click="filter = (filter === 'slip_needed' ? 'all' : 'slip_needed')"
        class="w-full flex items-center gap-3 bg-amber-50 border border-amber-200 rounded-2xl px-4 py-3.5 mb-3 text-left transition-transform active:scale-[0.99]">
    <div class="w-8 h-8 bg-amber-100 rounded-lg flex items-center justify-center flex-shrink-0">
        <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
        </svg>
    </div>
    <div class="flex-1 min-w-0">
        <p class="text-sm font-semibold text-amber-700">ยังไม่แนบสลิป<span x-text="slipMonth ? ' เดือน' + slipMonthLabel : ''"></span> <span class="font-bold"><span x-text="slipNeededCount">{{ $totalSlipNeeded }}</span> อสังหา</span></p>
        @if($totalSlipNeededRent > 0 || $totalSlipNeededUtility > 0)
        <div x-show="!slipMonth" class="flex flex-wrap items-center gap-x-3 gap-y-0.5 mt-1 text-[11px] text-amber-600">
            @if($totalSlipNeededRent > 0)
                <span>ค่าเช่า {{ $totalSlipNeededRent }} อสังหา</span>
            @endif
            @if($totalSlipNeededUtility > 0)
                <span>ค่าน้ำ/ไฟ {{ $totalSlipNeededUtility }} อสังหา</span>
            @endif
        </div>
        @endif
        <p class="text-xs text-amber-500 mt-0.5" x-text="filter === 'slip_needed' ? 'กำลังกรองอยู่ - แตะอีกครั้งเพื่อดูทั้งหมด' : 'แตะเพื่อกรองดูเฉพาะอสังหาที่ยังไม่แนบสลิป'"></p>
    </div>
    <svg class="w-4 h-4 flex-shrink-0 transition-transform"
         :class="filter === 'slip_needed' ? 'rotate-90 text-amber-600' : 'text-amber-400'"
         fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
</button>
<button type="button"
        x-show="slipVerifyCount > 0" @if($totalSlipVerify === 0) x-cloak @endif
        x-on:click="filter = (filter === 'slip_verify' ? 'all' : 'slip_verify')"
        class="w-full flex items-center gap-3 bg-blue-50 border border-blue-200 rounded-2xl px-4 py-3.5 mb-3 text-left transition-transform active:scale-[0.99]">
    <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
        <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
    </div>
    <div class="flex-1 min-w-0">
        <p class="text-sm font-semibold text-blue-700">แนบสลิปแล้ว รอตรวจสอบ<span x-text="slipMonth ? ' เดือน' + slipMonthLabel : ''"></span> <span class="font-bold"><span x-text="slipVerifyCount">{{ $totalSlipVerify }}</span> อสังหา</span></p>
        <p class="text-xs text-blue-400 mt-0.5" x-text="filter === 'slip_verify' ? 'กำลังกรองอยู่ - แตะอีกครั้งเพื่อดูทั้งหมด' : 'แตะเพื่อกรองดูอสังหาที่รอแอดมินตรวจสอบสลิป'"></p>
    </div>
    <svg class="w-4 h-4 flex-shrink-0 transition-transform"
         :class="filter === 'slip_verify' ? 'rotate-90 text-blue-600' : 'text-blue-400'"
         fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
</button>
@endif
@if($totalSlipNeeded > 0 || $totalSlipVerify > 0 || ! empty($slipMonthOptions))
<div class="mb-5" x-show="slipNeededCount > 0 || slipVerifyCount > 0"></div>
@endif

{{-- ===== Search + Filter ===== --}}
@if($totalAll > 0)
<div class="flex flex-col gap-2.5 mb-4">
    <div class="flex flex-col sm:flex-row gap-2.5">
    <div class="relative flex-1 min-w-0 border border-gray-300 rounded-xl bg-white transition-all focus-within:ring-2 focus-within:ring-brand-500/20 focus-within:border-brand-500">
        <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <input type="text"
               x-model="search"
               placeholder="ค้นหาชื่อทรัพย์ รหัส หรือชื่อลูกค้า..."
               class="w-full pl-11 pr-9 py-3 text-sm bg-transparent focus:outline-none text-gray-800 placeholder-gray-400">
        <button x-show="search" x-cloak @click="search = ''"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-300 hover:text-gray-500 transition-colors focus:outline-none"
                tabindex="-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
    @if(! empty($slipMonthOptions))
    {{-- ตัวกรองเดือนการแนบสลิป (ตามเดือนที่ครบกำหนดชำระ) - ใช้ร่วมกับแท็บรอสลิป/แนบแล้ว เช่น เลือก ตุลาคม + รอสลิป = ทรัพย์ที่ยังไม่แนบสลิปของเดือนตุลาคม --}}
    <div class="sm:w-60 flex-shrink-0">
        <x-form.select name="slip_month" :searchable="false" x-model="slipMonth" aria-label="เดือนการแนบสลิป">
            <option value="">เดือนการแนบสลิป: ทุกเดือน</option>
            @foreach($slipMonthOptions as $ym => $monthLabel)
            <option value="{{ $ym }}">{{ $monthLabel }}</option>
            @endforeach
        </x-form.select>
    </div>
    @endif
    </div>
    <div class="flex gap-1.5 bg-gray-100 rounded-xl p-1.5 overflow-x-auto">
        <button @click="filter = 'all'"
                :class="filter === 'all' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                class="flex-shrink-0 px-3 sm:px-3.5 py-2 sm:py-2.5 text-xs font-semibold rounded-lg transition-all whitespace-nowrap">ทั้งหมด</button>
        <button @click="filter = 'active'"
                :class="filter === 'active' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                class="flex-shrink-0 px-3 sm:px-3.5 py-2 sm:py-2.5 text-xs font-semibold rounded-lg transition-all whitespace-nowrap">ไม่ว่าง</button>
        <button @click="filter = 'available'"
                :class="filter === 'available' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                class="flex-shrink-0 px-3 sm:px-3.5 py-2 sm:py-2.5 text-xs font-semibold rounded-lg transition-all whitespace-nowrap">ว่าง</button>
        <button @click="filter = 'booked'"
                :class="filter === 'booked' ? 'bg-white text-yellow-700 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                class="flex-shrink-0 px-3 sm:px-3.5 py-2 sm:py-2.5 text-xs font-semibold rounded-lg transition-all whitespace-nowrap">จอง</button>
        @if($totalFutureProject > 0)
        <button @click="filter = 'future_project'"
                :class="filter === 'future_project' ? 'bg-white text-blue-700 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                class="flex-shrink-0 px-3 sm:px-3.5 py-2 sm:py-2.5 text-xs font-semibold rounded-lg transition-all whitespace-nowrap">โครงการในอนาคต</button>
        @endif
        @if($totalSlipNeeded > 0 || $totalSlipVerify > 0 || ! empty($slipMonthOptions))
        <button @click="filter = 'slip_needed'"
                x-show="slipNeededCount > 0 || filter === 'slip_needed'" @if($totalSlipNeeded === 0) x-cloak @endif
                :class="filter === 'slip_needed' ? 'bg-white text-amber-700 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                class="flex-shrink-0 px-3 sm:px-3.5 py-2 sm:py-2.5 text-xs font-semibold rounded-lg transition-all whitespace-nowrap flex items-center gap-1">
            รอสลิป
            <span x-text="slipNeededCount" class="text-[10px] font-bold bg-amber-500 text-white rounded-full min-w-4 h-4 px-1 flex items-center justify-center leading-none flex-shrink-0">{{ $totalSlipNeeded }}</span>
        </button>
        <button @click="filter = 'slip_verify'"
                x-show="slipVerifyCount > 0 || filter === 'slip_verify'" @if($totalSlipVerify === 0) x-cloak @endif
                :class="filter === 'slip_verify' ? 'bg-white text-blue-700 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                class="flex-shrink-0 px-3 sm:px-3.5 py-2 sm:py-2.5 text-xs font-semibold rounded-lg transition-all whitespace-nowrap flex items-center gap-1">
            แนบแล้ว
            <span x-text="slipVerifyCount" class="text-[10px] font-bold bg-blue-500 text-white rounded-full min-w-4 h-4 px-1 flex items-center justify-center leading-none flex-shrink-0">{{ $totalSlipVerify }}</span>
        </button>
        @endif
    </div>
</div>

{{-- ============================================================ --}}
{{-- MOBILE CARDS (below md) --}}
{{-- ============================================================ --}}
<div class="md:hidden space-y-3">

    @foreach($contractRows as $row)
    <a href="{{ route('properties.show', $row->property->id) }}?booking={{ $row->bookingId }}"
       x-show="matchRow('{{ $row->filterType }}', @js($row->searchText), @js($row->slipNeeded), @js($row->slipPendingVerify), @js($row->slipMonths))"
       class="property-row block bg-white rounded-2xl shadow-sm border border-gray-100 p-4 active:bg-gray-50 active:scale-[0.99] transition-all">

        <div class="flex items-start gap-3">
            {{-- Thumbnail --}}
            <div class="w-16 h-16 rounded-xl overflow-hidden flex-shrink-0 bg-gray-100 relative">
                @if($row->imageUrl)
                <img src="{{ $row->imageUrl }}" alt="{{ $row->property->title }}"
                     class="w-full h-full object-cover"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                @endif
                <div class="w-full h-full items-center justify-center absolute inset-0 {{ $row->imageUrl ? 'hidden' : 'flex' }}">
                    <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>
            </div>

            <div class="min-w-0 flex-1">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        @if($row->property->property_code)
                        <p class="font-mono font-bold text-sm text-gray-800 truncate leading-snug">{{ $row->property->property_code }}</p>
                        @else
                        <p class="font-semibold text-gray-800 truncate leading-snug">{{ $row->property->title ?? '-' }}</p>
                        @endif
                        @if($row->booking?->booking_code)
                        <p class="font-mono text-[11px] text-gray-400 mt-0.5" title="เลขที่จอง">{{ $row->booking->booking_code }}</p>
                        @endif
                    </div>
                    <svg class="w-4 h-4 text-gray-300 flex-shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                    </svg>
                </div>

                <div class="flex items-center justify-between gap-2 flex-wrap mt-2">
                    <span class="inline-flex items-center gap-1 text-[10px] font-semibold {{ $badgeClasses[$row->statusColor] }} border px-2 py-0.5 rounded-full">
                        <span class="w-1.5 h-1.5 rounded-full {{ $dotClasses[$row->statusColor] }} {{ $row->statusPulse ? 'animate-pulse' : '' }}"></span>{{ $row->statusLabel }}
                    </span>
                    @if($row->isOverlapping)
                    <span class="text-[10px] font-semibold text-purple-500">· ต่อสัญญา</span>
                    @endif
                    @if($row->booking?->monthly_rent)
                    <span class="text-xs font-bold text-gray-700 tabular-nums">{{ $fmtAmt($row->booking->monthly_rent) }} <span class="font-normal text-gray-400 text-[10px]">฿/ด.</span></span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Tenant + slip + dates --}}
        <div class="mt-3 pt-3 border-t border-gray-100 space-y-1.5">
            <div class="flex items-center gap-1.5">
                @if($row->tenantPhotoUrl)
                    <img src="{{ $row->tenantPhotoUrl }}" alt="{{ $row->tenant->full_name }}"
                         class="w-5 h-5 rounded-full object-cover flex-shrink-0 ring-1 ring-gray-200">
                @else
                    <div class="w-5 h-5 rounded-full bg-brand-600 flex items-center justify-center flex-shrink-0">
                        <span class="text-white text-[9px] font-bold leading-none">{{ $row->tenantInitial }}</span>
                    </div>
                @endif
                <span class="min-w-0 text-xs text-gray-700 font-medium">{{ $row->tenantFullName }}</span>
                @if($row->tenant?->mobile)
                <span class="text-[10px] text-gray-400 flex-shrink-0">· {{ $row->tenant->mobile }}</span>
                @endif
            </div>

            <div x-show="monthStatusText(@js($row->slipMonths))" x-cloak>
                <span class="inline-flex items-center text-[10px] font-semibold border px-2 py-0.5 rounded-full"
                      :class="monthStatusClass(@js($row->slipMonths))"
                      x-text="slipMonthLabel + ': ' + monthStatusText(@js($row->slipMonths))"></span>
            </div>

            <x-bill-status-list :items="$row->billItems" :last-slip-at="$row->lastSlipAt" class="pt-0.5" />

            @php $displayDate = $row->actualMoveIn ?? $row->contractStart ?? $row->checkIn; @endphp
            @if($displayDate || $row->contractEnd || $row->rentalMonths)
            <div class="text-[10px] text-gray-400 leading-relaxed flex flex-wrap gap-x-1">
                @if($displayDate)
                <span>เข้าอยู่: {{ $displayDate->locale('th')->translatedFormat('j M Y') }}</span>
                @endif
                @if($row->contractEnd)
                <span>· สิ้นสุด: {{ $row->contractEnd->locale('th')->translatedFormat('j M Y') }}</span>
                @endif
                @if($row->rentalMonths)
                <span>· {{ $row->rentalMonths }} เดือน</span>
                @endif
            </div>
            @endif
        </div>
    </a>
    @endforeach

    @foreach($vacantRows as $row)
    <div x-show="matchRow('{{ $row->filterType }}', @js($row->searchText), false, false)"
         class="property-row bg-white rounded-2xl border border-gray-100 p-4 opacity-75">
        <div class="flex items-center gap-3">
            <div class="w-16 h-16 rounded-xl overflow-hidden flex-shrink-0 bg-gray-100 relative">
                @if($row->imageUrl)
                <img src="{{ $row->imageUrl }}" alt="{{ $row->property->title }}"
                     class="w-full h-full object-cover"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                @endif
                <div class="w-full h-full items-center justify-center absolute inset-0 {{ $row->imageUrl ? 'hidden' : 'flex' }}">
                    <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>
            </div>
            <div class="min-w-0 flex-1">
                @if($row->property->property_code)
                <p class="font-mono font-bold text-sm text-gray-600 truncate leading-snug">{{ $row->property->property_code }}</p>
                @else
                <p class="font-medium text-gray-600 truncate leading-snug">{{ $row->property->title ?? '-' }}</p>
                @endif
                <div class="flex items-center gap-2 mt-1.5 flex-wrap">
                    <span class="inline-flex items-center gap-1 text-[10px] font-semibold {{ $badgeClasses[$row->statusColor] }} border px-2 py-0.5 rounded-full">
                        <span class="w-1.5 h-1.5 rounded-full {{ $dotClasses[$row->statusColor] }}"></span>{{ $row->statusLabel }}
                    </span>
                    @if($row->property->price_per_month)
                    <span class="text-xs text-gray-500 tabular-nums">{{ $fmtAmt($row->property->price_per_month) }} <span class="text-[10px] text-gray-400">฿/เดือน</span></span>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endforeach

</div>

{{-- ============================================================ --}}
{{-- DESKTOP TABLE (md and up) --}}
{{-- ============================================================ --}}
<x-table>
    <x-slot:head>
        <th class="text-right text-[11px] font-semibold text-gray-400 uppercase tracking-wide px-5 py-3.5 w-12">ลำดับ</th>
        <th class="text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wide px-5 py-3.5">ทรัพย์สิน</th>
        <th class="text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wide px-5 py-3.5">ผู้เช่า</th>
        <th class="text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wide px-5 py-3.5">สถานะ / การชำระ</th>
        <th class="text-right text-[11px] font-semibold text-gray-400 uppercase tracking-wide px-5 py-3.5 hidden md:table-cell">ค่าเช่า/เดือน</th>
        <th class="text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wide px-5 py-3.5 hidden lg:table-cell lg:w-72 xl:w-80">ข้อมูลสัญญา</th>
        <th class="px-5 py-3.5 w-10"></th>
    </x-slot:head>

    @foreach($contractRows as $row)
    <tr x-show="matchRow('{{ $row->filterType }}', @js($row->searchText), @js($row->slipNeeded), @js($row->slipPendingVerify), @js($row->slipMonths))"
        class="property-row hover:bg-gray-50 cursor-pointer group"
        onclick="window.location='{{ route('properties.show', $row->property->id) }}?booking={{ $row->bookingId }}'"
        role="button"
        tabindex="0"
        @keypress.enter="window.location='{{ route('properties.show', $row->property->id) }}?booking={{ $row->bookingId }}'">

        {{-- Col ลำดับ --}}
        <td class="px-5 py-3.5 text-right align-top text-xs text-gray-400 tabular-nums row-num"></td>

        {{-- Col 1: ทรัพย์สิน --}}
        <td class="px-5 py-3.5 align-top">
            @if($row->property->property_code)
                <p class="font-mono font-bold text-sm text-gray-800 group-hover:text-brand-600 transition-colors leading-snug">{{ $row->property->property_code }}</p>
            @else
                <p class="font-semibold text-gray-800 group-hover:text-brand-600 transition-colors leading-snug">{{ $row->property->title ?? '-' }}</p>
            @endif
            @if($row->booking?->booking_code)
                <p class="font-mono text-[11px] text-gray-400 mt-0.5 whitespace-nowrap" title="เลขที่จอง">{{ $row->booking->booking_code }}</p>
            @endif
        </td>

        {{-- Col 2: ผู้เช่า --}}
        <td class="px-5 py-3.5 align-top">
            <div class="flex items-center gap-2.5">
                @if($row->tenantPhotoUrl)
                    <img src="{{ $row->tenantPhotoUrl }}" alt="{{ $row->tenant->full_name }}"
                         class="w-8 h-8 rounded-full object-cover flex-shrink-0 ring-1 ring-gray-200">
                @else
                    <div class="w-8 h-8 rounded-full bg-brand-600 flex items-center justify-center flex-shrink-0">
                        <span class="text-white text-xs font-bold leading-none">{{ $row->tenantInitial }}</span>
                    </div>
                @endif
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-gray-700">{{ $row->tenantFullName }}</p>
                    @if($row->tenant?->mobile)
                    <p class="text-[11px] text-gray-400 mt-0.5">{{ $row->tenant->mobile }}</p>
                    @endif
                </div>
            </div>
        </td>

        {{-- Col 3: สถานะ + การชำระ --}}
        <td class="px-5 py-3.5 align-top">
            <div class="flex flex-wrap items-center gap-x-1.5 gap-y-1">
                <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold {{ $badgeClasses[$row->statusColor] }} border px-2 py-0.5 rounded-full whitespace-nowrap">
                    <span class="w-1.5 h-1.5 rounded-full {{ $dotClasses[$row->statusColor] }} {{ $row->statusPulse ? 'animate-pulse' : '' }}"></span>{{ $row->statusLabel }}
                </span>
                @if($row->isOverlapping)
                <span class="text-[10px] font-semibold text-purple-500 whitespace-nowrap">· ต่อสัญญา</span>
                @endif
            </div>

            <div class="mt-2 space-y-1">
                <div x-show="monthStatusText(@js($row->slipMonths))" x-cloak>
                    <span class="inline-flex items-center text-[10px] font-semibold border px-2 py-0.5 rounded-full whitespace-nowrap"
                          :class="monthStatusClass(@js($row->slipMonths))"
                          x-text="slipMonthLabel + ': ' + monthStatusText(@js($row->slipMonths))"></span>
                </div>
                <x-bill-status-list :items="$row->billItems" :last-slip-at="$row->lastSlipAt" />
            </div>
        </td>

        {{-- Col 4: ค่าเช่า (md+) --}}
        <td class="px-5 py-3.5 text-right tabular-nums hidden md:table-cell align-top">
            @if($row->booking)
                <span class="font-bold text-gray-800">{{ $fmtAmt($row->booking->monthly_rent) }}</span>
                <span class="text-xs text-gray-400">฿</span>
            @else
                <span class="text-gray-400">-</span>
            @endif
        </td>

        {{-- Col 5: ข้อมูลสัญญา (lg+) --}}
        <td class="px-5 py-3.5 hidden lg:table-cell align-top">
            <div class="space-y-1.5 text-xs">
                @if($row->actualMoveIn)
                <div class="flex gap-3 text-gray-500">
                    <span class="text-gray-400 w-24 flex-shrink-0 whitespace-nowrap">เข้าอยู่จริง</span>
                    <span class="whitespace-nowrap">{{ $row->actualMoveIn->locale('th')->translatedFormat('j F Y') }}</span>
                </div>
                @endif
                @if($row->contractStart)
                <div class="flex gap-3 text-gray-500">
                    <span class="text-gray-400 w-24 flex-shrink-0 whitespace-nowrap">ตามสัญญา</span>
                    <span class="whitespace-nowrap">{{ $row->contractStart->locale('th')->translatedFormat('j F Y') }}</span>
                </div>
                @elseif($row->checkIn && !$row->actualMoveIn)
                <div class="flex gap-3 text-gray-500">
                    <span class="text-gray-400 w-24 flex-shrink-0 whitespace-nowrap">เริ่มเช่า</span>
                    <span class="whitespace-nowrap">{{ $row->checkIn->locale('th')->translatedFormat('j F Y') }}</span>
                </div>
                @endif
                @if($row->contractEnd)
                <div class="flex gap-3 text-gray-500">
                    <span class="text-gray-400 w-24 flex-shrink-0 whitespace-nowrap">สิ้นสุด</span>
                    <span class="whitespace-nowrap">{{ $row->contractEnd->locale('th')->translatedFormat('j F Y') }}</span>
                </div>
                @endif
                @if($row->rentalMonths)
                <div class="flex gap-3 text-gray-400">
                    <span class="w-24 flex-shrink-0 whitespace-nowrap">ระยะเวลาเช่า</span>
                    <span>{{ $row->rentalMonths }} เดือน</span>
                </div>
                @endif
                @if(!$row->actualMoveIn && !$row->contractStart && !$row->checkIn && !$row->contractEnd && !$row->rentalMonths)
                <span class="text-gray-300">-</span>
                @endif
            </div>
        </td>

        {{-- Action --}}
        <td class="px-5 py-3.5 align-top">
            <svg class="w-4 h-4 text-gray-300 group-hover:text-brand-500 transition-colors mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
            </svg>
        </td>
    </tr>
    @endforeach

    @foreach($vacantRows as $row)
    <tr x-show="matchRow('{{ $row->filterType }}', @js($row->searchText), false, false)"
        class="property-row hover:bg-gray-50/60 opacity-75">

        <td class="px-5 py-3.5 text-right text-xs text-gray-400 tabular-nums row-num"></td>

        <td class="px-5 py-3.5">
            @if($row->property->property_code)
                <p class="font-mono font-bold text-sm text-gray-600 leading-snug">{{ $row->property->property_code }}</p>
            @else
                <p class="font-medium text-gray-600 leading-snug">{{ $row->property->title ?? '-' }}</p>
            @endif
        </td>

        <td class="px-5 py-3.5 text-sm text-gray-400">-</td>

        <td class="px-5 py-3.5">
            <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold {{ $badgeClasses[$row->statusColor] }} border px-2 py-0.5 rounded-full">
                <span class="w-1.5 h-1.5 rounded-full {{ $dotClasses[$row->statusColor] }}"></span>{{ $row->statusLabel }}
            </span>
        </td>

        <td class="px-5 py-3.5 text-right tabular-nums hidden md:table-cell">
            @if($row->property->price_per_month)
                <span class="text-gray-500">{{ $fmtAmt($row->property->price_per_month) }}</span>
                <span class="text-xs text-gray-400">฿</span>
            @else
                <span class="text-gray-400">-</span>
            @endif
        </td>

        <td class="px-5 py-3.5 hidden lg:table-cell text-gray-400">-</td>

        <td class="px-5 py-3.5"></td>
    </tr>
    @endforeach
</x-table>

{{-- ===== No results for current tab/search ===== --}}
<div x-show="!hasMatches" x-cloak class="py-16 text-center">
    <div class="w-14 h-14 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-3">
        <svg class="w-7 h-7 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
    </div>
    <p class="text-gray-700 font-semibold">ไม่มีข้อมูล</p>
    <p class="text-sm text-gray-400 mt-1">ไม่พบรายการที่ตรงกับตัวกรองหรือคำค้นหานี้</p>
</div>
@endif

{{-- ===== Empty State ===== --}}
@if($totalAll === 0)
<div class="py-24 text-center">
    <div class="w-16 h-16 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
        </svg>
    </div>
    <p class="text-gray-800 font-bold">ยังไม่มีทรัพย์สินที่ดูแล</p>
    <p class="text-sm text-gray-400 mt-1.5 max-w-xs mx-auto leading-relaxed">ระบบจะแสดงรายการเมื่อมีการมอบหมายอสังหาริมทรัพย์ให้คุณ</p>
</div>
@endif

</div>{{-- /x-data --}}

@endsection
