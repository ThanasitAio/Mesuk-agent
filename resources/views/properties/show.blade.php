@extends('layouts.app')

@section('title', $property->title ?? 'รอบบิล')
@section('breadcrumb', 'อสังหาริมทรัพย์ / รอบบิลและสลิป')

@section('content')

@php
    $totalPaid    = (float) $allRecords->where('payment_status', 'paid')->sum('amount');
    $totalVerif   = (float) $allRecords->where('payment_status', 'pending_verification')->sum('amount');
    $totalPending = (float) $allRecords->whereIn('payment_status', ['pending', 'failed'])->sum('amount');
    $overdueList  = $allRecords->filter(fn($r) =>
        $r->due_date && $r->due_date->toDateString() < now()->toDateString() &&
        ! in_array($r->payment_status, ['paid', 'pending_verification', 'refunded'])
    );
    $depositType   = $booking->deposit_type   ?? 'full';
    $renterType    = $booking->renter_type    ?? 'individual';
    $paymentDueDay = $booking->payment_due_day ?? 5;
    $startDate     = $booking->contract_start_date ?? $booking->check_in;

    $depositPhase1Record = $allRecords
        ->where('payment_type', 'deposit')
        ->filter(fn($r) => ! $r->deposit_phase || (int) $r->deposit_phase === 1)
        ->first();

    $cdHasPending  = $isInitialPaymentPhase
        && $depositPhase1Record
        && in_array($depositPhase1Record->payment_status, ['pending', 'failed']);
    $cdDeadline    = $booking->created_at->copy()->addHours(8);
    $cdDeadlineIso = $cdHasPending ? $cdDeadline->toIso8601String() : null;
    $cdDeadlineTh  = $cdDeadline->locale('th')->translatedFormat('j M Y H:i น.');

    $hasProcessingFee = $allRecords->where('payment_type', 'processing_fee')->isNotEmpty();
    $cdBannerTitle = $hasProcessingFee
        ? 'เวลาคงเหลือในการชำระเงินมัดจำ + ค่าดำเนินการ'
        : ($depositType === 'half' ? 'เวลาคงเหลือในการชำระเงินมัดจำงวดที่ 1' : 'เวลาคงเหลือในการชำระเงินมัดจำ');

    $hasBothPhase1 = $hasProcessingFee
        && $allRecords
            ->whereIn('payment_type', ['deposit', 'processing_fee'])
            ->whereIn('payment_status', ['pending', 'failed'])
            ->count() >= 2;

    $owner = $property->owner;
    $companyBankName     = $company?->bank_name;
    $companyBankAcct     = $company?->bank_account_number;
    $companyBankAcctName = $company?->bank_account_name;
    $companyBankBranch   = $company?->bank_branch;
    $companyQrPath       = $company?->qr_code_image;
    $companyQrUrl        = $companyQrPath ? $happyestPublic . '/storage/' . $companyQrPath : null;

    $investorBankName     = $owner?->bank_name;
    $investorBankAcct     = $owner?->bank_account_number;
    $investorBankAcctName = $owner?->bank_account_name;
    $investorBankBranch   = $owner?->bank_branch;
    $investorQrPath       = $owner?->bank_qr_code_path;
    $investorQrUrl        = $investorQrPath ? $happyestPublic . '/storage/' . $investorQrPath : null;

    $customerPhoto    = $booking->customer?->photo;
    $customerPhotoUrl = null;
    if ($customerPhoto) {
        $customerPhotoUrl = str_starts_with($customerPhoto, 'http')
            ? $customerPhoto
            : $happyestPublic . '/storage/' . $customerPhoto;
    } elseif ($booking->customer?->avatar && $booking->customer?->provider_id) {
        $av = $booking->customer->avatar;
        $customerPhotoUrl = str_starts_with($av, 'http') ? $av : $happyestPublic . '/storage/' . $av;
    }
    $customerInitials = $booking->customer?->full_name
        ? mb_strtoupper(mb_substr($booking->customer->full_name, 0, 1))
        : '?';

    $depositTypes = ['deposit', 'processing_fee'];
    $rentTypes    = ['monthly_rent', 'late_fee'];
    $pendingForBank = $actionableRecords->isNotEmpty() ? $actionableRecords : $allRecords->whereIn('payment_status', ['pending', 'failed']);
    // รายการที่ต้องโอนแยก 2 บัญชี (ดู $meta['is_split_payment']) ต้องแสดงบัญชีทั้งบริษัทและนักลงทุนเสมอ
    $splitPendingRecords = $pendingForBank->filter(fn($r) => $recordMeta[$r->id]['is_split_payment'] ?? false);
    $hasPendingCompany  = $splitPendingRecords->isNotEmpty() || $pendingForBank->filter(fn($r) =>
        (in_array($r->payment_type, $depositTypes) && ! $isDepositToInvestor) ||
        (in_array($r->payment_type, $rentTypes) && ! $isRentToInvestor)
    )->isNotEmpty();
    $hasPendingInvestor = $splitPendingRecords->isNotEmpty() || $pendingForBank->filter(fn($r) =>
        (in_array($r->payment_type, $depositTypes) && $isDepositToInvestor) ||
        (in_array($r->payment_type, $rentTypes) && $isRentToInvestor)
    )->isNotEmpty();

    $phaseCardColor = $isInitialPaymentPhase ? 'border-brand-500' : (($isWaitingContract || $isDepositPendingVerification) ? 'border-amber-400' : 'border-emerald-500');
    $fmtAmt = fn($a) => ((float) $a != floor((float) $a)) ? number_format((float) $a, 2) : number_format((int) $a);

    // แต่ละรอบบิลอาจมีใบแจ้งหนี้เปิดพร้อมกัน 2 ใบ (แยกบริษัท/นักลงทุน ตาม billing_route)
    // เมื่อยอดของรอบบิลนั้นถูกแบ่งจ่ายให้ทั้งสองฝ่าย (ดู $meta['is_split_payment'] จากคอนโทรลเลอร์)
    $recordInvoiceMap = [];
    foreach ($displayRecords as $rec) {
        $matchedInvoices = $invoices->filter(function ($inv) use ($rec) {
            if ($rec->payment_type === 'monthly_rent') {
                return $inv->invoice_type === 'monthly_rent'
                    && $rec->due_date
                    && $inv->billing_month === $rec->due_date->format('Y-m');
            }
            if ($rec->payment_type === 'deposit') {
                return $inv->invoice_type === 'deposit'
                    && (int) ($inv->deposit_phase ?? 1) === (int) ($rec->deposit_phase ?? 1);
            }
            if ($rec->payment_type === 'processing_fee') {
                return $inv->invoice_type === 'service_fee';
            }
            return false;
        });

        $companyInv  = $matchedInvoices->first(fn ($inv) => $inv->billing_route !== 'investor');
        $investorInv = $matchedInvoices->first(fn ($inv) => $inv->billing_route === 'investor');

        $recordInvoiceMap[$rec->id] = [
            'company'  => $companyInv,
            'investor' => $investorInv,
            'primary'  => $companyInv ?? $investorInv,
            'has'      => $matchedInvoices->isNotEmpty(),
            'split'    => $companyInv && $investorInv,
        ];
    }
@endphp

<style>
/* ─── Countdown Banner ─── */
.cd-banner {
    display: flex; align-items: center; justify-content: space-between;
    gap: 10px; flex-wrap: wrap;
    padding: 12px 16px;
    background: linear-gradient(135deg,#0ea5e9,#0284c7);
    color: #fff;
}
.cd-banner.warn   { background: linear-gradient(135deg,#f59e0b,#d97706); }
.cd-banner.danger { background: linear-gradient(135deg,#ef4444,#dc2626); }
.cd-banner.expired{ background: linear-gradient(135deg,#ef4444,#b91c1c); }
.cd-units { display: flex; gap: 5px; align-items: stretch; }
.cd-unit {
    display: flex; flex-direction: column; align-items: center;
    background: rgba(255,255,255,0.20);
    border-radius: 9px; padding: 5px 9px 4px; min-width: 44px;
    backdrop-filter: blur(4px);
}
.cd-unit-num { font-size: 1.45rem; font-weight: 800; line-height: 1; font-variant-numeric: tabular-nums; letter-spacing: -0.5px; }
.cd-unit-lbl { font-size: 0.58rem; font-weight: 700; opacity: .8; text-transform: uppercase; margin-top: 2px; }
.cd-sep { font-size: 1.3rem; font-weight: 800; align-self: center; opacity: .7; }
.cd-bottom {
    background: rgba(255,255,255,0.10); padding: 8px 16px;
    display: flex; align-items: center; gap: 7px;
    font-size: 0.77rem; color: #fff; font-weight: 600;
    border-top: 1px solid rgba(255,255,255,0.15);
}

/* ─── Shimmer sweep on booking dark header ─── */
@keyframes shimmer-slide {
    0%   { transform: translateX(-100%) skewX(-12deg); }
    100% { transform: translateX(300%)  skewX(-12deg); }
}
.booking-header-shimmer::after {
    content: '';
    position: absolute; inset: 0;
    background: linear-gradient(105deg, transparent 40%, rgba(255,255,255,0.07) 50%, transparent 60%);
    animation: shimmer-slide 5s ease-in-out infinite;
    pointer-events: none;
}

/* ─── Pulse glow for upload CTA (brand green) ─── */
@keyframes upload-pulse {
    0%, 100% { box-shadow: 0 4px 14px -2px rgba(70,132,50,0.45); }
    50%       { box-shadow: 0 4px 22px -2px rgba(70,132,50,0.7), 0 0 0 5px rgba(70,132,50,0.12); }
}
.btn-upload-pulse {
    animation: upload-pulse 2.5s ease-in-out infinite;
}
</style>

{{-- ===== Page Header ===== --}}
<div class="flex items-center gap-3 mb-5">
    <a href="{{ route('properties.index') }}"
       class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-gray-200 text-gray-600 hover:bg-brand-600 hover:text-white hover:border-brand-600 hover:shadow-md transition-all duration-200 flex-shrink-0 active:scale-95">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
    </a>
    <div class="min-w-0 flex-1">
        {{-- Mobile: รหัสอสังหาเด่นอยู่บน ชื่อเล็กไม่เด่นอยู่ล่าง --}}
        <div class="md:hidden">
            <h1 class="text-lg font-bold font-mono text-brand-600 truncate leading-tight">{{ $property->property_code }}</h1>
            <p class="text-xs text-gray-400 truncate mt-0.5">{{ $property->title }}</p>
            @if($property->property_type ?? false)
                <span class="text-[11px] text-gray-400">{{ $property->property_type }}</span>
            @endif
        </div>
        {{-- Desktop: เหมือนเดิม --}}
        <div class="hidden md:block">
            <h1 class="text-base font-bold text-gray-900 truncate">{{ $property->title }}</h1>
            <div class="flex items-center gap-2 mt-0.5">
                <span class="text-[11px] font-bold font-mono text-brand-600 bg-brand-50 border border-brand-100 px-2 py-0.5 rounded-md">{{ $property->property_code }}</span>
                @if($property->property_type ?? false)
                    <span class="text-[11px] text-gray-400">{{ $property->property_type }}</span>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ===== Overdue Alert ===== --}}
@if($overdueList->count() > 0)
<div class="flex items-start gap-3 bg-gradient-to-r from-red-500 to-rose-600 rounded-2xl px-4 py-4 mb-5 shadow-lg shadow-red-500/25">
    <div class="relative flex-shrink-0 mt-0.5">
        <div class="absolute inset-0 bg-white/30 rounded-xl animate-ping"></div>
        <div class="relative w-9 h-9 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
        </div>
    </div>
    <div class="flex-1 min-w-0">
        <p class="text-sm font-bold text-white">มี {{ $overdueList->count() }} รายการเกินกำหนดชำระ</p>
        <p class="text-xs text-red-100 mt-0.5">ยอดค้างรวม <span class="font-bold tabular-nums text-white">฿{{ number_format($overdueList->sum('amount'), 0) }}</span> — กรุณาติดต่อผู้เช่าโดยด่วน</p>
    </div>
</div>
@endif

{{-- ===== Booking Summary Card ===== --}}
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden mb-5">

    {{-- Gradient Header --}}
    <div class="relative overflow-hidden booking-header-shimmer px-5 pt-4"
         style="padding-bottom:2.5rem; background: linear-gradient(135deg, #38692a 0%, #2a4f1f 55%, #1c3514 100%)">
        {{-- decorative overlays --}}
        <div class="absolute inset-0 pointer-events-none" style="background: radial-gradient(ellipse at top right, rgba(255,255,255,0.07) 0%, transparent 55%)"></div>
        <div class="absolute inset-0 pointer-events-none" style="background: radial-gradient(ellipse at bottom left, rgba(0,0,0,0.18) 0%, transparent 60%)"></div>

        {{-- Label row --}}
        <div class="flex items-center justify-between mb-3 relative">
            <div class="flex items-center gap-2">
                <span class="text-[10px] font-bold tracking-widest uppercase" style="color: rgba(154,216,114,0.9)">ผู้เช่าปัจจุบัน</span>
                <span class="text-[10px] font-mono" style="color:rgba(255,255,255,0.35)">·</span>
                <span class="text-[10px] font-mono font-semibold" style="color:rgba(255,255,255,0.5)">{{ $booking->booking_code }}</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-700 bg-white px-2.5 py-1 rounded-full shadow-sm">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    {{ $booking->getStatusLabel() }}
                </span>
                @if($renterType === 'juristic')
                    <span class="text-[10px] font-semibold px-2 py-1 rounded-full" style="color:#fde68a; background:rgba(245,158,11,0.25); border:1px solid rgba(245,158,11,0.35)">นิติบุคคล</span>
                @endif
            </div>
        </div>

        {{-- Main info row --}}
        <div class="flex items-start gap-4 relative">
            {{-- Avatar --}}
            <div class="flex-shrink-0 relative">
                @if($customerPhotoUrl)
                    <img src="{{ $customerPhotoUrl }}"
                         alt="{{ $booking->customer?->full_name }}"
                         class="w-16 h-16 rounded-2xl object-cover shadow-xl"
                         style="border: 2px solid rgba(255,255,255,0.3)"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                    <div class="w-16 h-16 rounded-2xl items-center justify-center shadow-xl hidden"
                         style="background:rgba(255,255,255,0.18); border:2px solid rgba(255,255,255,0.25)">
                        <span class="text-2xl font-bold text-white">{{ $customerInitials }}</span>
                    </div>
                @else
                    <div class="w-16 h-16 rounded-2xl flex items-center justify-center shadow-lg"
                         style="background:rgba(255,255,255,0.15); border:2px solid rgba(255,255,255,0.22)">
                        <span class="text-2xl font-bold text-white">{{ $customerInitials }}</span>
                    </div>
                @endif
                <div class="absolute -bottom-1 -right-1 w-4 h-4 bg-emerald-400 rounded-full shadow"
                     style="border: 2px solid #1c3514"></div>
            </div>

            {{-- Name + contact --}}
            <div class="min-w-0 flex-1">
                <p class="text-xl font-bold text-white leading-tight truncate">
                    {{ $booking->customer?->full_name ?? '—' }}
                </p>
                <div class="mt-2 space-y-1">
                    @if($booking->customer?->mobile)
                        <div class="flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:rgba(154,216,114,0.8)">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                            </svg>
                            <span class="text-sm font-semibold text-white">{{ $booking->customer->mobile }}</span>
                        </div>
                    @endif
                    @if($booking->customer?->email)
                        <div class="flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:rgba(154,216,114,0.8)">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            <span class="text-xs" style="color:rgba(255,255,255,0.75)">{{ $booking->customer->email }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

    </div>

    {{-- 4-stat grid --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 divide-y sm:divide-y-0 divide-x divide-gray-100 border-b border-gray-100">
        <div class="px-4 py-3.5">
            <p class="text-[11px] text-gray-400 mb-1 font-medium">ค่าเช่า/เดือน</p>
            <p class="text-sm font-bold text-gray-900 tabular-nums">{{ number_format($booking->monthly_rent, 0) }} <span class="text-xs font-normal text-gray-400">฿</span></p>
        </div>
        <div class="px-4 py-3.5">
            <p class="text-[11px] text-gray-400 mb-1 font-medium">เงินประกัน</p>
            <div class="flex items-center gap-1.5 flex-wrap">
                <p class="text-sm font-bold text-gray-900 tabular-nums">{{ number_format($booking->deposit, 0) }} <span class="text-xs font-normal text-gray-400">฿</span></p>
                @if($depositType === 'half')
                    <span class="text-[10px] font-semibold text-amber-700 bg-amber-50 border border-amber-200 px-1.5 py-0.5 rounded-md leading-none">แบ่ง 2 งวด</span>
                @endif
            </div>
        </div>
        <div class="px-4 py-3.5">
            <p class="text-[11px] text-gray-400 mb-1 font-medium">ระยะสัญญา</p>
            <p class="text-sm font-bold text-gray-900 tabular-nums">{{ $booking->rental_months ?? '—' }} <span class="text-xs font-normal text-gray-400">เดือน</span></p>
        </div>
        <div class="px-4 py-3.5">
            <p class="text-[11px] text-gray-400 mb-1 font-medium">ครบกำหนดชำระ</p>
            <p class="text-sm font-bold text-gray-900">ทุกวันที่ <span class="text-brand-600">{{ $paymentDueDay }}</span></p>
        </div>
    </div>

    {{-- Contract details row 1 --}}
    <div class="grid grid-cols-2 border-b border-gray-100">
        <div class="px-4 py-3 border-r border-gray-100">
            <p class="text-[11px] text-gray-400 mb-0.5 font-medium">เริ่มสัญญา</p>
            <p class="text-xs font-semibold text-gray-700">
                {{ $startDate ? $startDate->locale('th')->translatedFormat('d M Y') : '—' }}
            </p>
        </div>
        <div class="px-4 py-3">
            <p class="text-[11px] text-gray-400 mb-0.5 font-medium">สถานะสัญญา</p>
            @if($booking->contract_status === 'sent')
                <p class="text-xs font-semibold text-emerald-600">{{ $booking->getContractStatusLabel() }} ✓</p>
                @if($booking->contract_sent_at)
                    <p class="text-[10px] text-gray-400 mt-0.5">ส่งเมื่อ {{ $booking->contract_sent_at->locale('th')->translatedFormat('d M Y') }}</p>
                @endif
            @else
                <p class="text-xs font-semibold text-amber-600">{{ $booking->getContractStatusLabel() }}</p>
            @endif
        </div>
    </div>

    {{-- Contract details row 2 --}}
    <div class="grid grid-cols-2 border-t border-gray-100">
        <div class="px-4 py-3 border-r border-gray-100">
            <p class="text-[11px] text-gray-400 mb-0.5 font-medium">รูปแบบมัดจำ</p>
            <p class="text-xs font-semibold text-gray-700">
                {{ $depositType === 'half' ? 'แบ่งครึ่ง 2 งวด' : 'ชำระครั้งเดียว' }}
            </p>
        </div>
        @php
            $canTogglePrePay = ! $booking->isContractSent() && $booking->hasInitialPaymentsUploaded();
        @endphp
        <div class="px-4 py-3">
            <p class="text-[11px] text-gray-400 mb-0.5 font-medium">ชำระค่าเช่าก่อนสัญญา</p>
            <div class="flex items-center gap-2 mt-1">
                @if($allowPay)
                    <span class="text-xs font-semibold text-emerald-600">เปิดใช้งานแล้ว</span>
                @else
                    <span class="text-xs font-semibold text-gray-500">ปิดใช้งาน</span>
                @endif
                @if($canTogglePrePay)
                <form action="{{ route('properties.togglePrePay', $property->id) }}" method="POST" class="inline-block m-0 p-0">
                    @csrf
                    <button type="submit" class="text-[10px] font-bold bg-gray-100 hover:bg-gray-200 text-gray-600 px-2 py-0.5 rounded border border-gray-200 transition-colors">
                        สลับสถานะ
                    </button>
                </form>
                @endif
            </div>
            @if($isWaitingContract && !$allowPay)
                <p class="text-[10px] text-amber-600 mt-1">ต้องเปิดหากต้องการให้โอนก่อนสัญญา</p>
            @elseif($isInitialPaymentPhase && !$allowPay)
                <p class="text-[10px] text-gray-400 mt-1">เปิดได้เมื่อลูกค้าอัปโหลดสลิปมัดจำแล้ว</p>
            @endif
        </div>
    </div>
</div>



{{-- ===== Payment Summary Strip ===== --}}
<div class="grid grid-cols-3 gap-2.5 mb-5">
    <div class="bg-gradient-to-br from-emerald-50 to-green-50 border border-emerald-200 rounded-2xl px-3 py-3.5 text-center shadow-sm">
        <div class="w-7 h-7 bg-emerald-100 rounded-xl flex items-center justify-center mx-auto mb-1.5">
            <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <p class="text-[10px] font-semibold text-emerald-600 uppercase tracking-wide">ชำระแล้ว</p>
        <p class="text-sm font-bold text-emerald-700 tabular-nums mt-0.5">฿{{ number_format($totalPaid, 0) }}</p>
    </div>
    <div class="bg-gradient-to-br from-amber-50 to-yellow-50 border border-amber-200 rounded-2xl px-3 py-3.5 text-center shadow-sm">
        <div class="w-7 h-7 bg-amber-100 rounded-xl flex items-center justify-center mx-auto mb-1.5">
            <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <p class="text-[10px] font-semibold text-amber-600 uppercase tracking-wide">รอตรวจสอบ</p>
        <p class="text-sm font-bold text-amber-700 tabular-nums mt-0.5">฿{{ number_format($totalVerif, 0) }}</p>
    </div>
    <div class="bg-gradient-to-br from-red-50 to-rose-50 border border-red-200 rounded-2xl px-3 py-3.5 text-center shadow-sm">
        <div class="w-7 h-7 bg-red-100 rounded-xl flex items-center justify-center mx-auto mb-1.5">
            <svg class="w-3.5 h-3.5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <p class="text-[10px] font-semibold text-red-600 uppercase tracking-wide">ยังไม่ชำระ</p>
        <p class="text-sm font-bold text-red-700 tabular-nums mt-0.5">฿{{ number_format($totalPending, 0) }}</p>
    </div>
</div>

{{-- ===== Deposit Deadline Countdown ===== --}}
@if($cdDeadlineIso)
<div class="rounded-2xl overflow-hidden mb-5 shadow-md shadow-blue-500/20" id="cd-wrapper">
    <div class="cd-banner" id="cd-banner">
        <div class="flex items-center gap-3 flex-1 min-w-0">
            <div class="w-9 h-9 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center flex-shrink-0">
                <span class="text-lg leading-none">⏰</span>
            </div>
            <div class="min-w-0">
                <p class="text-xs font-bold text-white leading-tight">{{ $cdBannerTitle }}</p>
                <p class="text-[10px] mt-0.5" style="color:rgba(255,255,255,0.7)" id="cd-status-text">กำลังนับถอยหลัง...</p>
            </div>
        </div>
        <div class="cd-units flex-shrink-0">
            <div class="cd-unit">
                <span class="cd-unit-num" id="cd-h">--</span>
                <span class="cd-unit-lbl">ชม.</span>
            </div>
            <span class="cd-sep">:</span>
            <div class="cd-unit">
                <span class="cd-unit-num" id="cd-m">--</span>
                <span class="cd-unit-lbl">นาที</span>
            </div>
            <span class="cd-sep">:</span>
            <div class="cd-unit">
                <span class="cd-unit-num" id="cd-s">--</span>
                <span class="cd-unit-lbl">วิ.</span>
            </div>
        </div>
    </div>
    <div class="cd-bottom">
        <svg class="w-3.5 h-3.5 flex-shrink-0" style="opacity:.7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
        </svg>
        ครบกำหนด {{ $cdDeadlineTh }}
    </div>
</div>
@endif

{{-- ===== Payment Account Info ===== --}}
@if(($hasPendingCompany && $company) || ($hasPendingInvestor && $owner))
<div x-data="{ showAccount: false }" class="mb-5">

    {{-- Toggle button --}}
    <button @click="showAccount = !showAccount" type="button"
            class="w-full flex items-center justify-between bg-white rounded-2xl border border-gray-200 shadow-sm px-4 py-3.5 transition-all duration-200 active:scale-[0.99]"
            :class="showAccount ? 'rounded-b-none border-b-0 shadow-none' : ''">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-xl flex items-center justify-center flex-shrink-0" style="background:#f0f9eb">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:#468432">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                </svg>
            </div>
            <div class="text-left">
                <p class="text-sm font-bold text-gray-800">บัญชีรับชำระเงิน</p>
                <p class="text-[10px] text-gray-400 mt-0.5">
                    @if($hasPendingCompany && $hasPendingInvestor)
                        {{ $companyName }} · {{ $investorName }}
                    @elseif($hasPendingCompany)
                        {{ $companyName }}
                    @else
                        {{ $investorName }}
                    @endif
                </p>
            </div>
        </div>
        <div class="w-7 h-7 rounded-lg flex items-center justify-center transition-all"
             :class="showAccount ? 'bg-gray-100' : 'bg-gray-50'">
            <svg class="w-4 h-4 text-gray-400 transition-transform duration-200" :class="{'rotate-180': showAccount}"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </div>
    </button>

    {{-- Expanded panel --}}
    <div x-show="showAccount" x-collapse>
        <div class="bg-white rounded-b-2xl border border-t-0 border-gray-200 overflow-hidden">

            {{-- Company account --}}
            @if($hasPendingCompany && $company)
            @php
                $companyLabels = collect();
                if($pendingForBank->filter(fn($r) => in_array($r->payment_type, $depositTypes) && (!$isDepositToInvestor || ($recordMeta[$r->id]['is_split_payment'] ?? false)))->isNotEmpty())
                    $companyLabels->push('มัดจำ' . ($hasProcessingFee ? '/ค่าดำเนินการ' : ''));
                if($pendingForBank->filter(fn($r) => in_array($r->payment_type, $rentTypes) && (!$isRentToInvestor || ($recordMeta[$r->id]['is_split_payment'] ?? false)))->isNotEmpty())
                    $companyLabels->push('ค่าเช่า');
            @endphp
            <div class="flex items-stretch gap-0 {{ ($hasPendingInvestor && $owner) ? 'border-b border-gray-100' : '' }}">
                {{-- Green left accent --}}
                <div class="w-1 flex-shrink-0" style="background: linear-gradient(180deg, #52a038, #38692a)"></div>
                <div class="flex-1 px-4 py-4">
                    {{-- Header --}}
                    <div class="flex items-center gap-2 mb-3">
                        <span class="text-[10px] font-bold uppercase tracking-wide" style="color:#468432">🏢 {{ $companyName }}</span>
                        @if($companyLabels->isNotEmpty())
                            <span class="text-[10px] text-gray-400">· {{ $companyLabels->join(' / ') }}</span>
                        @endif
                    </div>
                    {{-- Info + QR --}}
                    <div class="flex items-start gap-4">
                        <div class="flex-1 min-w-0 space-y-1.5">
                            @if($companyBankName)
                            <p class="text-[11px] text-gray-500">{{ $companyBankName }}@if($companyBankBranch) · <span class="text-gray-400">สาขา{{ $companyBankBranch }}</span>@endif</p>
                            @endif
                            @if($companyBankAcct)
                            <p class="text-base font-bold text-gray-900 font-mono tracking-widest">{{ $companyBankAcct }}</p>
                            @endif
                            @if($companyBankAcctName)
                            <p class="text-xs font-semibold text-gray-600">{{ $companyBankAcctName }}</p>
                            @endif
                            @if(!$companyBankName && !$companyBankAcct)
                                <p class="text-xs text-gray-400 italic">ยังไม่ได้ตั้งค่าบัญชีธนาคาร</p>
                            @endif
                        </div>
                        @if($companyQrUrl)
                        <div class="flex-shrink-0">
                            <img src="{{ $companyQrUrl }}" alt="QR {{ $companyName }}"
                                 class="w-20 h-20 object-contain rounded-xl border border-gray-200 shadow-sm cursor-zoom-in"
                                 onclick="this.requestFullscreen?.()" loading="lazy">
                            <p class="text-[10px] text-gray-400 text-center mt-1">QR Code</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @endif

            {{-- Investor account --}}
            @if($hasPendingInvestor && $owner)
            @php
                $investorLabels = collect();
                if($pendingForBank->filter(fn($r) => in_array($r->payment_type, $depositTypes) && ($isDepositToInvestor || ($recordMeta[$r->id]['is_split_payment'] ?? false)))->isNotEmpty())
                    $investorLabels->push('มัดจำ' . ($hasProcessingFee ? '/ค่าดำเนินการ' : ''));
                if($pendingForBank->filter(fn($r) => in_array($r->payment_type, $rentTypes) && ($isRentToInvestor || ($recordMeta[$r->id]['is_split_payment'] ?? false)))->isNotEmpty())
                    $investorLabels->push('ค่าเช่า');
            @endphp
            <div class="flex items-stretch gap-0">
                {{-- Blue left accent --}}
                <div class="w-1 flex-shrink-0" style="background: linear-gradient(180deg, #60a5fa, #2563eb)"></div>
                <div class="flex-1 px-4 py-4">
                    {{-- Header --}}
                    <div class="flex items-center gap-2 mb-3">
                        <span class="text-[10px] font-bold uppercase tracking-wide text-blue-600">👤 {{ $investorName }}</span>
                        @if($investorLabels->isNotEmpty())
                            <span class="text-[10px] text-gray-400">· {{ $investorLabels->join(' / ') }}</span>
                        @endif
                    </div>
                    {{-- Info + QR --}}
                    <div class="flex items-start gap-4">
                        <div class="flex-1 min-w-0 space-y-1.5">
                            @if($investorBankName)
                            <p class="text-[11px] text-gray-500">{{ $investorBankName }}@if($investorBankBranch) · <span class="text-gray-400">สาขา{{ $investorBankBranch }}</span>@endif</p>
                            @endif
                            @if($investorBankAcct)
                            <p class="text-base font-bold text-gray-900 font-mono tracking-widest">{{ $investorBankAcct }}</p>
                            @endif
                            @if($investorBankAcctName)
                            <p class="text-xs font-semibold text-gray-600">{{ $investorBankAcctName }}</p>
                            @endif
                            @if(!$investorBankName && !$investorBankAcct)
                                <p class="text-xs text-gray-400 italic">นักลงทุนยังไม่ได้ตั้งค่าบัญชีธนาคาร</p>
                            @endif
                        </div>
                        @if($investorQrUrl)
                        <div class="flex-shrink-0">
                            <img src="{{ $investorQrUrl }}" alt="QR {{ $investorName }}"
                                 class="w-20 h-20 object-contain rounded-xl border border-gray-200 shadow-sm cursor-zoom-in"
                                 onclick="this.requestFullscreen?.()" loading="lazy">
                            <p class="text-[10px] text-gray-400 text-center mt-1">QR Code</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @endif

        </div>
    </div>
</div>
@endif

{{-- ===== Billing Records ===== --}}
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">

    {{-- Section Header --}}
    <div class="flex items-center justify-between gap-2 flex-wrap px-4 sm:px-5 py-3.5 sm:py-4 border-b border-gray-100">
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 bg-brand-100 rounded-xl flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <h2 class="text-sm font-bold text-gray-800">รอบบิลทั้งหมด</h2>
        </div>
        @if($hasComboPayment)
        <div class="flex flex-col items-end gap-1">
            <p class="text-[10px] text-violet-500 font-semibold">มัดจำงวด 2 + ค่าเช่าเดือน 1</p>
            <div class="flex items-center gap-1.5">
                @if($canCombinePayment)
                <button type="button" id="main-combo-btn-join" onclick="selectComboMode('join')"
                        class="main-combo-btn text-[11px] font-bold px-2.5 py-1.5 rounded-lg border-2 transition-all border-violet-500 bg-violet-50 text-violet-700">
                    รวม 1 แถว
                </button>
                @else
                <button type="button" id="main-combo-btn-join" disabled title="มัดจำงวด 2 โอนคนละบัญชีกับค่าเช่า ต้องแนบสลิปแยก"
                        class="main-combo-btn text-[11px] font-bold px-2.5 py-1.5 rounded-lg border-2 transition-all border-gray-200 text-gray-300 cursor-not-allowed opacity-50">
                    รวม 1 แถว
                </button>
                @endif
                <button type="button" id="main-combo-btn-sep" onclick="selectComboMode('sep')"
                        class="main-combo-btn text-[11px] font-bold px-2.5 py-1.5 rounded-lg border-2 transition-all border-gray-200 text-gray-400">
                    แยก 2 แถว
                </button>
            </div>
            @if(!$canCombinePayment)
            <p class="text-[10px] text-amber-600 mt-0.5">มัดจำงวด 2 โอนคนละบัญชีกับค่าเช่า — ต้องแนบสลิปแยก 2 รายการ</p>
            @endif
        </div>
        @else
        <span class="text-xs font-semibold text-white bg-brand-600 px-2.5 py-1 rounded-full tabular-nums">
            {{ $displayRecords->count() }} รายการ
            @if($lockedRecords->count() > 0)
                · {{ $lockedRecords->count() }} ล็อก
            @endif
        </span>
        @endif
    </div>

    @if($isWaitingContract && $displayRecords->isEmpty())
        <div class="py-16 text-center px-6">
            <div class="w-14 h-14 bg-amber-100 rounded-2xl flex items-center justify-center mx-auto mb-3">
                <svg class="w-7 h-7 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <p class="text-gray-700 font-semibold">รอแอดมินดำเนินการจัดทำสัญญา</p>
            <p class="text-xs text-gray-400 mt-1 max-w-sm mx-auto">เงินมัดจำได้รับการยืนยันแล้ว รายการค่าเช่าจะแสดงเมื่อสัญญาพร้อม หรือเมื่อตัวแทนเปิดชำระก่อนสัญญา</p>
        </div>
    @elseif($displayRecords->isEmpty())
        <div class="py-16 text-center">
            <div class="w-14 h-14 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-3">
                <svg class="w-7 h-7 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <p class="text-gray-600 font-semibold">ยังไม่มีรอบบิล</p>
            <p class="text-xs text-gray-400 mt-1">รอบบิลจะปรากฏที่นี่เมื่อระบบสร้างขึ้น</p>
        </div>
    @else

    {{-- ─── Tab Bar (segmented control) ─── --}}
    <div class="px-4 sm:px-5 pt-3.5 pb-3 border-b border-gray-100 bg-gray-50/40">
        <div class="flex gap-1 bg-gray-100 rounded-xl p-1">
            <button onclick="switchBillingTab('pending')" id="tab-btn-pending"
                    class="billing-tab-btn flex-1 text-center text-xs font-bold py-2.5 px-2 rounded-lg transition-all whitespace-nowrap bg-white text-brand-700 shadow-sm">
                รอชำระ/รอตรวจสอบ
            </button>
            <button onclick="switchBillingTab('paid')" id="tab-btn-paid"
                    class="billing-tab-btn flex-1 text-center text-xs font-semibold py-2.5 px-2 rounded-lg transition-all whitespace-nowrap text-gray-500 hover:text-gray-700">
                ชำระแล้ว
            </button>
            <button onclick="switchBillingTab('all')" id="tab-btn-all"
                    class="billing-tab-btn flex-1 text-center text-xs font-semibold py-2.5 px-2 rounded-lg transition-all whitespace-nowrap text-gray-500 hover:text-gray-700">
                ทั้งหมด
            </button>
        </div>
    </div>

    {{-- ─── Desktop Table ─── --}}
    <div id="billing-desktop-table" class="hidden md:block overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr style="background:#f8fafc; border-bottom:2px solid #f1f5f9">
                    <th class="p-0 w-1"></th>
                    <th class="text-left px-4 py-3 text-[11px] font-semibold text-gray-400 uppercase tracking-wider">รายการ</th>
                    <th class="text-right px-4 py-3 text-[11px] font-semibold text-gray-400 uppercase tracking-wider">ยอดชำระ</th>
                    <th class="text-left px-4 py-3 text-[11px] font-semibold text-gray-400 uppercase tracking-wider">ครบกำหนด</th>
                    <th class="text-left px-4 py-3 text-[11px] font-semibold text-gray-400 uppercase tracking-wider">สถานะ</th>
                    <th class="px-4 py-3 text-center text-[11px] font-semibold text-gray-400 uppercase tracking-wider">ดำเนินการ</th>
                </tr>
            </thead>
            <tbody>
                @foreach($displayRecords as $record)
                    @php
                        $meta = $recordMeta[$record->id] ?? [];
                        $colorMap = [
                            'yellow' => ['badge' => 'text-yellow-700 bg-yellow-50 border-yellow-200', 'bar' => '#f59e0b', 'rowBg' => ''],
                            'blue'   => ['badge' => 'text-blue-700 bg-blue-50 border-blue-200',       'bar' => '#60a5fa', 'rowBg' => 'background:rgba(219,234,254,0.25)'],
                            'green'  => ['badge' => 'text-emerald-700 bg-emerald-50 border-emerald-200', 'bar' => '#4ade80', 'rowBg' => ''],
                            'red'    => ['badge' => 'text-red-700 bg-red-50 border-red-200',           'bar' => '#f87171', 'rowBg' => 'background:rgba(254,226,226,0.35)'],
                            'gray'   => ['badge' => 'text-gray-600 bg-gray-50 border-gray-200',        'bar' => '#cbd5e1', 'rowBg' => ''],
                        ];
                        $colorKey   = $record->getStatusColor();
                        $badgeClass = $colorMap[$colorKey]['badge'] ?? $colorMap['gray']['badge'];
                        $barColor   = $colorMap[$colorKey]['bar']   ?? $colorMap['gray']['bar'];
                        $rowBg      = $colorMap[$colorKey]['rowBg'] ?? '';
                        $recInv         = $recordInvoiceMap[$record->id] ?? ['company' => null, 'investor' => null, 'primary' => null, 'has' => false, 'split' => false];
                        $recordInvoice  = $recInv['primary'];
                        $hasInvoice     = $recInv['has'];
                        $isSplitInvoice = $recInv['split'];
                        $invoiceCodesLabel = $isSplitInvoice
                            ? $recInv['company']->invoice_code . ' / ' . $recInv['investor']->invoice_code
                            : ($hasInvoice ? $recordInvoice->invoice_code : null);
                        if ($hasInvoice) { $rowBg = 'background:rgba(242,251,234,0.55)'; $barColor = '#86efac'; }
                        $isOverdue  = $record->due_date && $record->due_date->toDateString() < now()->toDateString()
                                      && ! in_array($record->payment_status, ['paid', 'pending_verification', 'refunded']);
                        $landTax    = (float) ($record->land_tax_amount   ?? 0);
                        $stampDuty  = (float) ($record->stamp_duty_amount ?? 0);
                        $whtAmount  = (float) ($record->withholding_tax_amount ?? 0);
                        $baseRent   = (float) ($record->base_rent_amount  ?? 0);
                        $hasBreakdown = $record->payment_type === 'monthly_rent'
                                        && ($landTax > 0 || $stampDuty > 0 || $whtAmount > 0);
                        $isProrated  = (bool) ($record->is_prorated ?? false);
                        $recSlips    = $record->payment_slips ?? [];
                        if (empty($recSlips) && $record->payment_slip_path) $recSlips = [$record->payment_slip_path];
                        $slipCount   = count($recSlips);

                        $displayLabel  = $meta['display_label'] ?? $record->getTypeLabel();
                        $displayAmount = ($meta['is_phase2_combo'] ?? false) ? ($meta['combo_amount'] ?? $record->amount) : $record->amount;
                        $canUpload     = $meta['can_upload'] ?? false;
                        $recToInvestor = $meta['to_investor'] ?? false;
                        $recRecipient  = $meta['recipient_name'] ?? $companyName;
                        $recRecipientBadge = $recToInvestor
                            ? 'text-blue-600 bg-blue-50 border-blue-200'
                            : 'text-emerald-600 bg-emerald-50 border-emerald-200';
                        $rentPeriod = ($record->payment_type === 'monthly_rent' && $record->due_date)
                            ? $record->due_date->locale('th')->translatedFormat('M Y')
                            : null;
                        $recordDetail = match ($record->payment_type) {
                            'deposit'        => $record->deposit_phase == 2 ? 'เงินมัดจำงวดที่ 2 (ชำระหลังสัญญาพร้อม)' : 'เงินมัดจำงวดแรก',
                            'processing_fee' => 'ค่าดำเนินการจัดหาที่พักอาศัย',
                            'late_fee'       => 'ค่าปรับล่าช้า' . ($record->month_number ? " (ค่าเช่าเดือนที่ {$record->month_number})" : ''),
                            default          => null,
                        };
                    @endphp
                    <tr class="transition-colors hover:brightness-95"
                        style="{{ $rowBg }}; border-bottom:1px solid rgba(241,245,249,0.8)"
                        data-billing-status="{{ $record->payment_status }}"
                        @if($meta['is_phase2_combo'] ?? false) data-phase2-row="1" @endif
                        @if($meta['is_combo_month1'] ?? false) data-combomonth1-row="1" @endif
                        @if($meta['is_combo_month1'] ?? false) x-data x-init="$el.style.display='none'" @endif
                    >
                        {{-- Color indicator strip --}}
                        <td class="p-0" style="width:3px; min-width:3px; background:{{ $barColor }}"></td>

                        {{-- Type --}}
                        <td class="px-4 py-3.5">
                            <div class="flex items-center gap-1.5 flex-wrap mb-0.5">
                                @if($meta['is_phase2_combo'] ?? false)
                                    <p class="font-semibold text-gray-800 text-sm"><span class="combo-join-label">{{ $displayLabel }}</span><span class="combo-sep-label" style="display:none;">{{ $meta['sep_display_label'] ?? $record->getTypeLabel() }}</span></p>
                                @else
                                    <p class="font-semibold text-gray-800 text-sm">{{ $displayLabel }}</p>
                                @endif
                                @if($meta['is_split_payment'] ?? false)
                                    <span class="text-[10px] font-semibold text-orange-700 bg-orange-50 border border-orange-200 px-1.5 py-0.5 rounded-md leading-none whitespace-nowrap">โอนแยก 2 บัญชี</span>
                                @endif
                                @if($isProrated && $record->prorated_days)
                                    <span class="text-[10px] font-semibold text-violet-700 bg-violet-50 border border-violet-200 px-1.5 py-0.5 rounded-md leading-none whitespace-nowrap">{{ $record->prorated_days }}/{{ $record->prorated_total_days_in_month ?? '?' }} วัน</span>
                                @endif
                            </div>
                            <span class="inline-flex items-center text-[10px] font-semibold border px-1.5 py-0.5 rounded-md leading-none {{ $recRecipientBadge }}">
                                {{ $recToInvestor ? '👤' : '🏢' }} {{ $recRecipient }}
                            </span>
                            @if($rentPeriod)
                                <p class="text-[10px] text-gray-400 mt-1">ช่วงเวลา: <span class="font-semibold text-gray-600">{{ $rentPeriod }}</span>@if($hasInvoice) <span class="font-bold font-mono" style="color:#468432">· {{ $invoiceCodesLabel }}</span>@endif</p>
                            @elseif($recordDetail)
                                <p class="text-[10px] text-gray-400 mt-1">{{ $recordDetail }}@if($hasInvoice) <span class="font-bold font-mono" style="color:#468432">· {{ $invoiceCodesLabel }}</span>@endif</p>
                            @elseif($hasInvoice)
                                <p class="text-[10px] font-mono font-bold mt-1" style="color:#468432">{{ $invoiceCodesLabel }}</p>
                            @endif
                            @if($record->payment_code)
                                <p class="text-[10px] text-gray-400 font-mono mt-0.5">{{ $record->payment_code }}</p>
                            @endif
                            @if($record->payment_status === 'pending_verification' && $record->paid_at)
                                <div class="flex items-center gap-1 mt-1">
                                    <svg class="w-3 h-3 text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                    </svg>
                                    <p class="text-[10px] text-blue-500">{{ $slipCount > 1 ? "{$slipCount} ไฟล์" : '1 ไฟล์' }} · {{ $record->paid_at->locale('th')->diffForHumans() }}</p>
                                </div>
                            @endif
                            @if($record->payment_status === 'failed' && $record->rejection_reason)
                                <p class="text-[10px] text-red-600 mt-0.5">เหตุผล: {{ Str::limit($record->rejection_reason, 40) }}</p>
                            @endif
                        </td>

                        {{-- Amount --}}
                        <td class="px-4 py-3.5 text-right">
                            @if($meta['is_phase2_combo'] ?? false)
                                <p class="font-bold text-gray-900 text-lg tabular-nums leading-none"><span class="combo-join-amount">{{ number_format($displayAmount, 0) }}</span><span class="combo-sep-amount" style="display:none;">{{ number_format($record->amount, 0) }}</span></p>
                                <p class="combo-join-note text-[10px] text-violet-600 mt-0.5">รวม 2 รายการ</p>
                            @else
                                <p class="font-bold text-gray-900 text-lg tabular-nums leading-none">{{ number_format($displayAmount, 0) }}</p>
                            @endif
                            <p class="text-[10px] text-gray-400 mt-0.5">บาท</p>
                            @if($hasBreakdown)
                                <div class="mt-1.5 space-y-0.5 text-right">
                                    @if($baseRent > 0)<p class="text-[10px] text-gray-400 tabular-nums">ค่าเช่า {{ number_format($baseRent, 0) }}</p>@endif
                                    @if($landTax > 0)<p class="text-[10px] text-gray-400 tabular-nums">+ ภาษีที่ดิน {{ number_format($landTax, 0) }}</p>@endif
                                    @if($stampDuty > 0)<p class="text-[10px] text-amber-600 tabular-nums">+ อากร {{ number_format($stampDuty, 0) }}</p>@endif
                                    @if($whtAmount > 0)<p class="text-[10px] text-indigo-600 tabular-nums">- หัก ณ ที่จ่าย {{ number_format($whtAmount, 0) }}</p>@endif
                                </div>
                            @endif
                        </td>

                        {{-- Due date --}}
                        <td class="px-4 py-3.5">
                            @if($record->due_date)
                                <p class="text-sm font-semibold {{ $isOverdue ? 'text-red-600' : 'text-gray-700' }}">
                                    {{ $record->due_date->locale('th')->translatedFormat('d M Y') }}
                                </p>
                                @if($isOverdue)
                                    <div class="flex items-center gap-1 mt-1">
                                        <svg class="w-3 h-3 text-red-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                        </svg>
                                        <p class="text-[10px] text-red-500 font-semibold">เกิน {{ (int) $record->due_date->diffInDays(now()) }} วัน</p>
                                    </div>
                                @endif
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>

                        {{-- Status --}}
                        <td class="px-4 py-3.5">
                            <div class="space-y-1.5">
                                <span class="inline-flex items-center text-xs font-semibold border px-2.5 py-1 rounded-full {{ $badgeClass }}">
                                    {{ $record->getStatusLabel() }}
                                </span>
                                @if($hasInvoice)
                                <span class="inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-1 rounded-full" style="color:#38692a; background:#f0f9eb; border:1px solid #c3ea8e">
                                    <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    มีใบแจ้งหนี้{{ $isSplitInvoice ? ' (2 ใบ)' : '' }}
                                </span>
                                @endif
                            </div>
                        </td>

                        {{-- Action --}}
                        <td class="px-4 py-3.5 text-center">
                            <div class="inline-flex flex-col items-center gap-2">
                                @if($canUpload)
                                    <button type="button"
                                            onclick="openSlipModalAuto({{ $record->id }})"
                                            class="btn-upload-pulse inline-flex items-center gap-1.5 text-xs font-bold text-white active:scale-95 px-3.5 py-2 rounded-xl transition-all"
                                            style="background:#468432">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                        </svg>
                                        แนบสลิป
                                    </button>
                                @elseif($record->payment_status === 'pending_verification')
                                    <div class="inline-flex flex-col items-center gap-1.5">
                                        <div class="inline-flex items-center gap-1.5 text-xs font-semibold text-blue-600 bg-blue-50 border border-blue-200 px-3 py-1.5 rounded-xl">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            รอตรวจสอบ
                                        </div>
                                        @foreach($recSlips as $si => $_)
                                        <a href="{{ route('billing.slip.view', $record->id) }}?index={{ $si }}" target="_blank"
                                           class="inline-flex items-center gap-1 text-[10px] font-semibold text-gray-500 hover:text-gray-700 transition-colors">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            ดูสลิป{{ $slipCount > 1 ? ' #'.($si+1) : '' }}
                                        </a>
                                        @endforeach
                                        <button type="button"
                                                onclick="openCancelSlipConfirm('{{ route('billing.slip.cancel', $record->id) }}')"
                                                class="inline-flex items-center gap-1 text-[10px] font-semibold text-red-500 hover:text-red-700 transition-colors">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                            ยกเลิกสลิป
                                        </button>
                                    </div>
                                @elseif($record->payment_status === 'paid')
                                    <div class="inline-flex flex-col items-center gap-1.5">
                                        <div class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-600 bg-emerald-50 border border-emerald-200 px-3 py-1.5 rounded-xl">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            ชำระแล้ว
                                        </div>
                                        @if($slipCount > 0)
                                        <a href="{{ route('billing.slip.view', $record->id) }}" target="_blank"
                                           class="inline-flex items-center gap-1 text-[10px] font-semibold text-gray-500 hover:text-gray-700 transition-colors">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            ดูสลิป{{ $slipCount > 1 ? " ({$slipCount})" : '' }}
                                        </a>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- ─── Mobile Cards ─── --}}
    <div id="billing-mobile-cards" class="md:hidden p-3 space-y-2.5 bg-gray-50/40">
        @foreach($displayRecords as $record)
            @php
                $meta = $recordMeta[$record->id] ?? [];
                $colorMap = [
                    'yellow' => ['badge' => 'text-yellow-700 bg-yellow-50 border-yellow-200', 'bar' => 'bg-yellow-400'],
                    'blue'   => ['badge' => 'text-blue-700 bg-blue-50 border-blue-200',       'bar' => 'bg-blue-400'],
                    'green'  => ['badge' => 'text-emerald-700 bg-emerald-50 border-emerald-200', 'bar' => 'bg-emerald-500'],
                    'red'    => ['badge' => 'text-red-700 bg-red-50 border-red-200',           'bar' => 'bg-red-500'],
                    'gray'   => ['badge' => 'text-gray-600 bg-gray-50 border-gray-200',        'bar' => 'bg-gray-300'],
                ];
                $colorKey   = $record->getStatusColor();
                $badgeClass = $colorMap[$colorKey]['badge'] ?? $colorMap['gray']['badge'];
                $barClass   = $colorMap[$colorKey]['bar']   ?? $colorMap['gray']['bar'];
                $recInv         = $recordInvoiceMap[$record->id] ?? ['company' => null, 'investor' => null, 'primary' => null, 'has' => false, 'split' => false];
                $recordInvoice  = $recInv['primary'];
                $hasInvoice     = $recInv['has'];
                $isSplitInvoice = $recInv['split'];
                $invoiceCodesLabel = $isSplitInvoice
                    ? $recInv['company']->invoice_code . ' / ' . $recInv['investor']->invoice_code
                    : ($hasInvoice ? $recordInvoice->invoice_code : null);
                if ($hasInvoice) { $barClass = 'bg-brand-400'; }
                $barWidth = $hasInvoice ? 'w-1.5' : 'w-1';
                $isOverdue  = $record->due_date && $record->due_date->toDateString() < now()->toDateString()
                              && ! in_array($record->payment_status, ['paid', 'pending_verification', 'refunded']);
                $landTax    = (float) ($record->land_tax_amount   ?? 0);
                $stampDuty  = (float) ($record->stamp_duty_amount ?? 0);
                $whtAmount  = (float) ($record->withholding_tax_amount ?? 0);
                $hasBreakdown = $record->payment_type === 'monthly_rent'
                                && ($landTax > 0 || $stampDuty > 0 || $whtAmount > 0);
                $isProrated  = (bool) ($record->is_prorated ?? false);
                $recSlips    = $record->payment_slips ?? [];
                if (empty($recSlips) && $record->payment_slip_path) $recSlips = [$record->payment_slip_path];
                $slipCount   = count($recSlips);

                $displayLabel  = $meta['display_label'] ?? $record->getTypeLabel();
                $displayAmount = ($meta['is_phase2_combo'] ?? false) ? ($meta['combo_amount'] ?? $record->amount) : $record->amount;
                $canUpload     = $meta['can_upload'] ?? false;
                $recToInvestor = $meta['to_investor'] ?? false;
                $recRecipient  = $meta['recipient_name'] ?? $companyName;
                $recRecipientBadge = $recToInvestor
                    ? 'text-blue-600 bg-blue-50 border-blue-200'
                    : 'text-emerald-600 bg-emerald-50 border-emerald-200';
                $rentPeriod = ($record->payment_type === 'monthly_rent' && $record->due_date)
                    ? $record->due_date->locale('th')->translatedFormat('M Y')
                    : null;
                $recordDetail = match ($record->payment_type) {
                    'deposit'        => $record->deposit_phase == 2 ? 'เงินมัดจำงวดที่ 2 (ชำระหลังสัญญาพร้อม)' : 'เงินมัดจำงวดแรก',
                    'processing_fee' => 'ค่าดำเนินการจัดหาที่พักอาศัย',
                    'late_fee'       => 'ค่าปรับล่าช้า' . ($record->month_number ? " (ค่าเช่าเดือนที่ {$record->month_number})" : ''),
                    default          => null,
                };
            @endphp
            <div class="relative rounded-2xl border shadow-sm py-4 pl-5 pr-4 transition-shadow hover:shadow-md {{ $hasInvoice ? 'border-brand-100 bg-brand-50/30' : ($isOverdue ? 'border-red-100 bg-red-50/40' : 'border-gray-100 bg-white') }}"
                 data-billing-status="{{ $record->payment_status }}"
                 @if($meta['is_phase2_combo'] ?? false) data-phase2-row="1" @endif
                 @if($meta['is_combo_month1'] ?? false) data-combomonth1-row="1" style="display:none;" @endif
            >
                {{-- Left accent bar --}}
                <span class="absolute left-1.5 top-3 bottom-3 {{ $barWidth }} rounded-full {{ $barClass }}"></span>

                {{-- Top row: type + status badge --}}
                <div class="flex items-start justify-between gap-3 mb-2 pl-1">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            @if($meta['is_phase2_combo'] ?? false)
                                <p class="font-bold text-gray-900 text-sm"><span class="combo-join-label">{{ $displayLabel }}</span><span class="combo-sep-label" style="display:none;">{{ $meta['sep_display_label'] ?? $record->getTypeLabel() }}</span></p>
                            @else
                                <p class="font-bold text-gray-900 text-sm">{{ $displayLabel }}</p>
                            @endif
                            @if($meta['is_split_payment'] ?? false)
                                <span class="text-[10px] font-semibold text-orange-700 bg-orange-50 border border-orange-200 px-1.5 py-0.5 rounded-md leading-none">โอนแยก 2 บัญชี</span>
                            @endif
                            @if($isProrated && $record->prorated_days)
                                <span class="text-[10px] font-semibold text-violet-700 bg-violet-50 border border-violet-200 px-1.5 py-0.5 rounded-md leading-none">
                                    {{ $record->prorated_days }}/{{ $record->prorated_total_days_in_month ?? '?' }} วัน
                                </span>
                            @endif
                        </div>
                        {{-- Recipient badge --}}
                        <span class="inline-flex items-center text-[10px] font-semibold border px-1.5 py-0.5 rounded-md leading-none mt-0.5 {{ $recRecipientBadge }}">
                            {{ $recToInvestor ? '👤' : '🏢' }} {{ $recRecipient }}
                        </span>
                        {{-- Record detail --}}
                        @if($rentPeriod)
                            <p class="text-[10px] text-gray-400 mt-1">ช่วงเวลา: <span class="font-semibold text-gray-600">{{ $rentPeriod }}</span>@if($hasInvoice) <span class="font-bold text-brand-600 font-mono">· {{ $invoiceCodesLabel }}</span>@endif</p>
                        @elseif($recordDetail)
                            <p class="text-[10px] text-gray-400 mt-1">{{ $recordDetail }}@if($hasInvoice) <span class="font-bold text-brand-600 font-mono">· {{ $invoiceCodesLabel }}</span>@endif</p>
                        @elseif($hasInvoice)
                            <p class="text-[10px] text-brand-600 font-mono font-bold mt-1">{{ $invoiceCodesLabel }}</p>
                        @endif
                        @if($record->payment_code)
                            <p class="text-xs text-gray-400 font-mono mt-0.5">{{ $record->payment_code }}</p>
                        @endif
                    </div>
                    <span class="flex-shrink-0 inline-flex items-center text-xs font-semibold border px-2 py-1 rounded-full {{ $badgeClass }}">
                        {{ $record->getStatusLabel() }}
                    </span>
                </div>

                {{-- Bottom row: amount + action --}}
                <div class="flex items-end justify-between pl-1">
                    <div>
                        @if($meta['is_phase2_combo'] ?? false)
                            <p class="text-2xl font-bold text-gray-900 leading-none tabular-nums">
                                <span class="combo-join-amount">{{ number_format($displayAmount, 0) }}</span><span class="combo-sep-amount" style="display:none;">{{ number_format($record->amount, 0) }}</span><span class="text-sm font-normal text-gray-400 ml-0.5">฿</span>
                            </p>
                            <p class="combo-join-note text-[10px] text-violet-600 mt-0.5">รวมมัดจำงวด 2 + เช่าเดือน 1</p>
                        @else
                            <p class="text-2xl font-bold text-gray-900 leading-none tabular-nums">
                                {{ number_format($displayAmount, 0) }}<span class="text-sm font-normal text-gray-400 ml-0.5">฿</span>
                            </p>
                        @endif
                        @if($hasBreakdown)
                            <div class="mt-1.5 space-y-0.5">
                                @if($landTax > 0)
                                    <p class="text-[10px] text-gray-400 tabular-nums">+ ภาษีที่ดิน {{ number_format($landTax, 0) }}</p>
                                @endif
                                @if($stampDuty > 0)
                                    <p class="text-[10px] text-amber-600 tabular-nums">+ อากร {{ number_format($stampDuty, 0) }}</p>
                                @endif
                                @if($whtAmount > 0)
                                    <p class="text-[10px] text-indigo-600 tabular-nums">- หัก ณ ที่จ่าย {{ number_format($whtAmount, 0) }}</p>
                                @endif
                            </div>
                        @endif
                        @if($record->due_date)
                            <p class="text-xs mt-1.5 {{ $isOverdue ? 'text-red-600 font-bold' : 'text-gray-400' }}">
                                ครบ {{ $record->due_date->locale('th')->translatedFormat('d M Y') }}
                                @if($isOverdue)
                                    · <span class="tabular-nums">เกิน {{ (int) $record->due_date->diffInDays(now()) }} วัน</span>
                                @endif
                            </p>
                        @endif
                        @if($record->payment_status === 'failed' && $record->rejection_reason)
                            <p class="text-[10px] text-red-600 mt-1">{{ Str::limit($record->rejection_reason, 50) }}</p>
                        @endif
                    </div>

                    {{-- Action button --}}
                    @if($canUpload)
                        <div class="flex flex-col items-end gap-2 flex-shrink-0">
                            <button type="button"
                                    onclick="openSlipModalAuto({{ $record->id }})"
                                    class="btn-upload-pulse flex items-center gap-1.5 text-xs font-bold text-white btn-upload-pulse bg-brand-600 hover:bg-brand-700 active:scale-95 px-4 py-2.5 rounded-xl transition-all">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                </svg>
                                แนบสลิป
                            </button>
                        </div>
                    @elseif($record->payment_status === 'pending_verification')
                        <div class="flex flex-col items-end gap-1.5 flex-shrink-0">
                            <div class="flex items-center gap-1.5 text-xs font-semibold text-blue-600 bg-blue-50 border border-blue-200 px-3 py-2 rounded-xl">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                รอตรวจสอบ
                            </div>
                            @foreach($recSlips as $si => $_)
                            <a href="{{ route('billing.slip.view', $record->id) }}?index={{ $si }}" target="_blank"
                               class="inline-flex items-center gap-1 text-[10px] font-semibold text-gray-500 hover:text-brand-600">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                ดูสลิป{{ $slipCount > 1 ? ' #'.($si+1) : '' }}
                            </a>
                            @endforeach
                            <button type="button"
                                    onclick="openCancelSlipConfirm('{{ route('billing.slip.cancel', $record->id) }}')"
                                    class="inline-flex items-center gap-1 text-[10px] font-semibold text-red-500 hover:text-red-700 transition-colors">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                                ยกเลิกสลิป
                            </button>
                        </div>
                    @elseif($record->payment_status === 'paid')
                        <div class="flex flex-col items-end gap-1.5 flex-shrink-0">
                            <div class="flex items-center gap-1.5 text-xs font-semibold text-emerald-600 bg-emerald-50 border border-emerald-200 px-3 py-2 rounded-xl">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                ชำระแล้ว
                            </div>
                            @if($slipCount > 0)
                            <a href="{{ route('billing.slip.view', $record->id) }}" target="_blank"
                               class="inline-flex items-center gap-1 text-[10px] font-semibold text-gray-500 hover:text-brand-600">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                ดูสลิป{{ $slipCount > 1 ? " ({$slipCount})" : '' }}
                            </a>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Slip attachment note (pending_verification) --}}
                @if($record->payment_status === 'pending_verification' && $record->paid_at)
                    <div class="flex items-center gap-1.5 mt-3 pl-1">
                        <svg class="w-3.5 h-3.5 text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                        </svg>
                        <p class="text-xs text-blue-500">แนบสลิปแล้ว · {{ $record->paid_at->locale('th')->diffForHumans() }}</p>
                    </div>
                @endif

                {{-- Invoice footer bar (hasInvoice) — รองรับกรณีมีใบแจ้งหนี้เปิดพร้อมกัน 2 ใบ (บริษัท + นักลงทุน) --}}
                @if($hasInvoice)
                    <div class="mt-3 pt-3 border-t border-gray-100 space-y-2">
                        @if($isSplitInvoice)
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-700 bg-brand-50 border border-brand-200 px-2.5 py-1.5 rounded-full flex-shrink-0">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    ใบแจ้งหนี้ บริษัท
                                </span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-blue-700 bg-blue-50 border border-blue-200 px-2.5 py-1.5 rounded-full flex-shrink-0">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    ใบแจ้งหนี้ นักลงทุน
                                </span>
                            </div>
                        @else
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-700 bg-brand-50 border border-brand-200 px-2.5 py-1.5 rounded-full flex-shrink-0">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    เปิดใบแจ้งหนี้แล้ว
                                </span>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Show more / collapse bar --}}
    <div id="billing-show-more-bar" style="display:none;" class="border-t border-gray-100 px-4 sm:px-5 py-3 flex items-center justify-center bg-gradient-to-r from-transparent via-gray-50/80 to-transparent">
        <button type="button" onclick="toggleBillingExpand()"
                class="inline-flex items-center gap-2 text-xs font-bold text-brand-600 hover:text-brand-800 transition-colors">
            <svg id="billing-show-more-icon" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
            <span id="billing-show-more-text"></span>
        </button>
    </div>

    {{-- Tab empty state --}}
    <div id="billing-tab-empty" style="display:none;" class="py-12 text-center px-6 border-t border-gray-50">
        <p class="text-sm font-semibold text-gray-400">ไม่มีรายการในหมวดนี้</p>
    </div>

    @if($lockedRecords->count() > 0)
    <div id="locked-records-section" class="border-t border-gray-100 bg-gradient-to-br from-gray-50 to-slate-50/50 px-4 sm:px-5 py-4">
        <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3 flex items-center gap-2">
            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
            รอบถัดไป (ล็อก — ชำระตามลำดับเดือน)
        </p>
        <div class="space-y-2">
            @foreach($lockedRecords as $locked)
            @php $lmeta = $recordMeta[$locked->id] ?? []; @endphp
            <div class="flex items-center justify-between bg-white border border-gray-200 rounded-xl px-4 py-3 opacity-70">
                <div>
                    <p class="text-sm font-semibold text-gray-600">{{ $lmeta['display_label'] ?? $locked->getTypeLabel() }}</p>
                    @if($locked->due_date)
                        <p class="text-xs text-gray-400">ครบ {{ $locked->due_date->locale('th')->translatedFormat('d M Y') }}</p>
                    @endif
                </div>
                <p class="text-sm font-bold text-gray-500 tabular-nums">{{ number_format($locked->amount, 0) }} ฿</p>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    @endif
</div>

{{-- ===== Slip Upload Modal ===== --}}
<div id="slip-modal_backdrop"
     class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-end sm:items-center justify-center p-0 sm:p-4"
     onclick="closeSlipModal()">

    <div id="slip-modal_panel"
         style="opacity:0; translate: 0 40px; scale: 0.98; transition: all 0.25s cubic-bezier(0.34,1.56,0.64,1);"
         class="bg-white rounded-t-3xl sm:rounded-2xl shadow-2xl ring-1 ring-black/5 w-full sm:max-w-lg max-h-[92vh] flex flex-col overflow-hidden"
         onclick="event.stopPropagation()"
         x-data="slipUploader()"
         x-init="init()">

        <div class="sm:hidden flex justify-center pt-2 pb-0.5 flex-shrink-0">
            <div class="w-10 h-1 bg-gray-200 rounded-full"></div>
        </div>

        {{-- Header (fixed, not part of the scroll area) --}}
        <div class="flex items-center justify-between gap-3 px-4 sm:px-5 py-2.5 border-b border-gray-100 flex-shrink-0">
            <div class="min-w-0 flex-1 flex items-center gap-2">
                <div class="w-7 h-7 bg-brand-600 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-bold text-gray-800 leading-tight">แนบสลิปแทนลูกค้า</p>
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span id="slip-record-label" class="text-xs text-gray-500 truncate"></span>
                        <span id="slip-record-amount-badge"
                              class="hidden text-xs font-bold text-brand-700 bg-brand-50 border border-brand-200 px-1.5 rounded leading-tight">
                            <span id="slip-record-amount"></span> ฿
                        </span>
                    </div>
                </div>
            </div>
            <button type="button"
                    onclick="closeSlipModal()"
                    class="w-7 h-7 flex items-center justify-center rounded-full text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors flex-shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Scrollable body — only this area scrolls, header/footer stay put --}}
        <form id="slip-form" method="POST" enctype="multipart/form-data" class="flex-1 overflow-y-auto px-4 sm:px-5 py-3 space-y-3">
            @csrf

            <div class="space-y-1">
                <x-form.date name="transfer_date" label="วันที่โอน" :value="now()->format('Y-m-d')" :max="now()->format('Y-m-d')" required />

                <div>
                    <p class="text-xs font-semibold text-gray-500 mb-1.5">ประเภทค่าเช่า</p>
                    <div class="flex flex-wrap gap-1.5">
                        <template x-for="opt in rentalTypeOptions" :key="opt.key">
                            <button type="button"
                                    @click="toggleRentalType(opt.key)"
                                    :class="rentalTypes.includes(opt.key) ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-gray-200 bg-white text-gray-500'"
                                    class="text-xs font-semibold px-3 py-1.5 rounded-full border-2 transition-all active:scale-95">
                                <span x-text="opt.label"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <div
                class="relative border-2 border-dashed rounded-xl px-4 py-3 text-center transition-all duration-200 cursor-pointer"
                :class="isDragging ? 'border-brand-500 bg-brand-50 scale-[1.01]' : 'border-gray-200 bg-gray-50/50 hover:border-brand-300 hover:bg-brand-50/30'"
                @dragover.prevent="isDragging = true"
                @dragleave.prevent="isDragging = false"
                @drop.prevent="handleDrop($event)"
                @click="$refs.fileInput.click()">

                <input type="file"
                       x-ref="fileInput"
                       multiple
                       accept="image/jpeg,image/png,application/pdf"
                       class="hidden"
                       @change="handleFileInput($event)">

                <div x-show="files.length === 0" class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-brand-600 rounded-xl flex items-center justify-center flex-shrink-0 shadow-md shadow-brand-700/25">
                        <svg style="width:18px;height:18px" class="text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                    </div>
                    <div class="min-w-0 text-left">
                        <p class="text-sm font-bold text-gray-700 leading-tight">แตะหรือวางไฟล์ที่นี่</p>
                        <p class="text-[11px] text-gray-400 leading-tight mt-0.5">JPG · PNG · PDF · ไม่เกิน 5MB/ไฟล์</p>
                    </div>
                </div>

                <div x-show="files.length > 0" class="space-y-1.5" @click.stop>
                    <template x-for="(file, index) in files" :key="index">
                        <div class="flex items-center gap-2.5 bg-white rounded-lg px-2.5 py-2 text-left shadow-sm border border-gray-100 hover:border-gray-200 transition-colors">
                            <div class="w-7 h-7 flex-shrink-0 rounded-lg flex items-center justify-center"
                                 :class="file.type === 'application/pdf' ? 'bg-red-100' : 'bg-sky-100'">
                                <svg style="width:14px;height:14px"
                                     :class="file.type === 'application/pdf' ? 'text-red-600' : 'text-sky-600'"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0 text-left">
                                <p class="text-xs font-semibold text-gray-800 truncate" x-text="file.name"></p>
                                <p class="text-[10px] text-gray-400" x-text="formatSize(file.size)"></p>
                            </div>
                            <button type="button"
                                    @click.stop="removeFile(index)"
                                    class="w-6 h-6 flex items-center justify-center rounded-full text-gray-300 hover:text-red-500 hover:bg-red-50 transition-colors flex-shrink-0">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </template>

                    <button type="button"
                            x-show="files.length < 5"
                            @click.stop="$refs.fileInput.click()"
                            class="w-full flex items-center justify-center gap-1.5 border-2 border-dashed border-brand-200 text-brand-600 hover:border-brand-400 hover:bg-brand-50 rounded-lg py-1.5 text-[11px] font-semibold transition-colors">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        เพิ่มไฟล์ (<span x-text="5 - files.length"></span> ไฟล์ที่เหลือ)
                    </button>
                </div>
            </div>

            <div x-show="errorMsg" x-cloak class="flex items-start gap-2 text-xs text-red-700 bg-red-50 border border-red-200 rounded-lg px-2.5 py-2">
                <svg class="w-4 h-4 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span x-text="errorMsg"></span>
            </div>

            <p class="text-[11px] text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-2.5 py-1.5 leading-snug">
                สลิปที่แนบจะถูกส่งไปรอการตรวจสอบจากทีมงาน และบันทึกในชื่อผู้เช่ารายนั้น
            </p>
        </form>

        {{-- Footer action — always visible, no scrolling needed to reach it. Cancel button removed: the header
             already has an X close button, and a lone full-width button can never get clipped on narrow screens. --}}
        <div class="px-4 sm:px-5 pt-3 border-t border-gray-100 bg-white flex-shrink-0"
             style="padding-bottom: max(env(safe-area-inset-bottom, 0px), 0.75rem)">
            <button type="button"
                    @click="submit()"
                    :disabled="files.length === 0 || submitting"
                    class="w-full py-3 text-sm font-bold text-white bg-brand-600 hover:bg-brand-700 disabled:opacity-40 disabled:cursor-not-allowed active:scale-95 rounded-xl transition-all flex items-center justify-center gap-2 shadow-md shadow-brand-700/25">
                <svg x-show="submitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <svg x-show="!submitting" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span x-text="submitting ? 'กำลังอัพโหลด...' : 'ยืนยันส่งสลิป'"></span>
            </button>
        </div>
    </div>
</div>

{{-- ── Cancel Slip Confirm Modal ── --}}
<x-confirm-modal
    id="cancel-slip-confirm"
    title="ยืนยันยกเลิกสลิป"
    action=""
    method="DELETE"
    icon-variant="danger"
    confirm-label="ยืนยันยกเลิก"
    cancel-label="ไม่ยกเลิก">
    <div class="flex flex-col gap-3">
        <p class="text-sm text-gray-700 leading-relaxed">
            คุณต้องการ<span class="font-semibold text-red-600">ยกเลิกสลิปที่แนบไว้</span>ใช่หรือไม่?
        </p>
        <div class="flex items-start gap-2.5 bg-amber-50 border border-amber-200 rounded-xl px-3.5 py-3">
            <svg class="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.07 16.5c-.77.833.192 2.5 1.732 2.5z"/>
            </svg>
            <p class="text-xs text-amber-700 leading-relaxed">
                สถานะจะกลับเป็น <span class="font-semibold">รอชำระ</span> และสามารถแนบสลิปใหม่ได้อีกครั้ง
            </p>
        </div>
    </div>
</x-confirm-modal>

@endsection

@push('scripts')
<script>
function openCancelSlipConfirm(url) {
    document.getElementById('cancel-slip-confirm_form').action = url;
    openModal('cancel-slip-confirm');
}

window.billingRecordMeta = @json($recordMeta);
let mainPageComboMode = 'join';
let currentBillingTab = 'pending';
let billingExpanded = false;
const BILLING_VISIBLE_LIMIT = 5;

function switchBillingTab(tab) {
    currentBillingTab = tab;
    billingExpanded = false;
    document.querySelectorAll('.billing-tab-btn').forEach(btn => {
        btn.classList.remove('bg-white', 'text-brand-700', 'font-bold', 'shadow-sm');
        btn.classList.add('text-gray-500', 'font-semibold');
    });
    const activeBtn = document.getElementById('tab-btn-' + tab);
    if (activeBtn) {
        activeBtn.classList.remove('text-gray-500', 'font-semibold');
        activeBtn.classList.add('bg-white', 'text-brand-700', 'font-bold', 'shadow-sm');
    }
    applyBillingTabFilter();
}

function toggleBillingExpand() {
    billingExpanded = !billingExpanded;
    applyBillingTabFilter();
}

function filterBillingGroup(containerSelector) {
    const pendingStatuses = ['pending', 'failed', 'pending_verification'];
    const matchedRows = [];

    document.querySelectorAll(containerSelector + ' [data-billing-status]').forEach(row => {
        const status = row.dataset.billingStatus;
        const isComboMonth1 = row.hasAttribute('data-combomonth1-row');

        let tabVisible;
        if (currentBillingTab === 'all') tabVisible = true;
        else if (currentBillingTab === 'pending') tabVisible = pendingStatuses.includes(status);
        else tabVisible = status === 'paid';

        if (isComboMonth1) {
            row.style.display = (mainPageComboMode === 'sep' && tabVisible) ? '' : 'none';
        } else {
            if (tabVisible) matchedRows.push(row);
            else row.style.display = 'none';
        }
    });

    return matchedRows;
}

function applyBillingTabFilter() {
    // ทั้ง desktop table และ mobile cards render รอบบิลเดียวกันคนละ markup —
    // ต้องนับ/ตัด 5 รายการแรกแยกกันต่อกลุ่ม ไม่งั้นรวมกันเป็น 2 เท่าแล้วตัดผิดกลุ่ม
    const desktopRows  = filterBillingGroup('#billing-desktop-table');
    const mobileCards  = filterBillingGroup('#billing-mobile-cards');

    const totalVisible = mobileCards.length;
    const needsTruncate = totalVisible > BILLING_VISIBLE_LIMIT;

    [desktopRows, mobileCards].forEach(group => {
        group.forEach((row, idx) => {
            if (!billingExpanded && needsTruncate && idx >= BILLING_VISIBLE_LIMIT) {
                row.style.display = 'none';
            } else {
                row.style.display = '';
            }
        });
    });

    const showMoreBar = document.getElementById('billing-show-more-bar');
    const showMoreText = document.getElementById('billing-show-more-text');
    const showMoreIcon = document.getElementById('billing-show-more-icon');
    if (showMoreBar) {
        if (needsTruncate) {
            showMoreBar.style.display = '';
            const hidden = totalVisible - BILLING_VISIBLE_LIMIT;
            showMoreText.textContent = billingExpanded
                ? 'ซ่อนรายการ'
                : `ดูทั้งหมด ${totalVisible} รายการ (ซ่อนอยู่ ${hidden} รายการ)`;
            showMoreIcon.style.transform = billingExpanded ? 'rotate(180deg)' : '';
        } else {
            showMoreBar.style.display = 'none';
        }
    }

    const lockedSection = document.getElementById('locked-records-section');
    if (lockedSection) {
        lockedSection.style.display = currentBillingTab === 'paid' ? 'none' : '';
    }

    const emptyState = document.getElementById('billing-tab-empty');
    if (emptyState) {
        emptyState.style.display = totalVisible === 0 ? '' : 'none';
    }
}
</script>
<script>
// ─── Countdown Timer ───
(function() {
    const deadlineIso = @json($cdDeadlineIso ?? null);
    if (!deadlineIso) return;

    const deadline = new Date(deadlineIso).getTime();
    const banner   = document.getElementById('cd-banner');
    const hEl      = document.getElementById('cd-h');
    const mEl      = document.getElementById('cd-m');
    const sEl      = document.getElementById('cd-s');
    const statusEl = document.getElementById('cd-status-text');
    if (!banner || !hEl) return;

    function pad(n) { return String(n).padStart(2, '0'); }

    function tick() {
        const diff = deadline - Date.now();
        if (diff <= 0) {
            hEl.textContent = '00'; mEl.textContent = '00'; sEl.textContent = '00';
            banner.classList.remove('warn', 'danger');
            banner.classList.add('expired');
            if (statusEl) statusEl.textContent = 'หมดเวลาชำระแล้ว';
            return;
        }
        const totalSecs = Math.floor(diff / 1000);
        const h = Math.floor(totalSecs / 3600);
        const m = Math.floor((totalSecs % 3600) / 60);
        const s = totalSecs % 60;
        hEl.textContent = pad(h);
        mEl.textContent = pad(m);
        sEl.textContent = pad(s);

        banner.classList.remove('warn', 'danger', 'expired');
        if      (diff < 30 * 60 * 1000)     { banner.classList.add('danger'); if (statusEl) statusEl.textContent = 'เหลือเวลาน้อยมาก!'; }
        else if (diff < 2 * 60 * 60 * 1000) { banner.classList.add('warn');   if (statusEl) statusEl.textContent = 'ใกล้ครบกำหนดแล้ว'; }
        else                                 {                                  if (statusEl) statusEl.textContent = 'กำลังนับถอยหลัง...'; }

        setTimeout(tick, 1000);
    }
    tick();
})();

    let currentRecordId = null;
    let currentIsPhase2Combo = false;
    let currentCanCombine = false;
    let _comboMode = 'join';
    const billingUploadBase = '{{ url("billing") }}';

    function selectComboMode(mode) {
        mainPageComboMode = mode;
        _comboMode = mode;

        // อัพเดท UI ปุ่ม
        const btnJoin = document.getElementById('main-combo-btn-join');
        const btnSep  = document.getElementById('main-combo-btn-sep');
        if (btnJoin && btnSep) {
            btnJoin.classList.remove('border-violet-500', 'bg-violet-50', 'text-violet-700');
            btnJoin.classList.add('border-gray-200', 'text-gray-400');
            btnSep.classList.remove('border-violet-500', 'bg-violet-50', 'text-violet-700');
            btnSep.classList.add('border-gray-200', 'text-gray-400');
            const activeBtn = mode === 'join' ? btnJoin : btnSep;
            activeBtn.classList.remove('border-gray-200', 'text-gray-400');
            activeBtn.classList.add('border-violet-500', 'bg-violet-50', 'text-violet-700');
        }

        // สลับแถว month1 combo ให้ซ่อน/แสดง
        document.querySelectorAll('[data-combomonth1-row]').forEach(row => {
            row.style.display = mode === 'join' ? 'none' : '';
        });

        // สลับเนื้อหาในแถว deposit phase2
        document.querySelectorAll('[data-phase2-row]').forEach(row => {
            row.querySelectorAll('.combo-join-label, .combo-join-amount, .combo-join-note').forEach(el => {
                el.style.display = mode === 'join' ? '' : 'none';
            });
            row.querySelectorAll('.combo-sep-label, .combo-sep-amount').forEach(el => {
                el.style.display = mode === 'sep' ? '' : 'none';
            });
        });

        applyBillingTabFilter();
    }

    function openSlipModalAuto(recordId) {
        const meta = window.billingRecordMeta[recordId] || {};
        const isPhase2Combo = !!meta.is_phase2_combo;
        const isJoin = isPhase2Combo && mainPageComboMode === 'join';
        const label  = isJoin
            ? (meta.display_label || '')
            : (meta.sep_display_label || meta.display_label || '');
        const amount = isJoin
            ? Math.round(meta.combo_amount || meta.own_amount || 0)
            : Math.round(meta.own_amount || 0);
        openSlipModal(recordId, label, amount.toLocaleString(), isPhase2Combo, isPhase2Combo);
    }

    function openSlipModal(recordId, label, amount, isPhase2Combo, canCombine) {
        currentRecordId = recordId;
        currentIsPhase2Combo = !!isPhase2Combo;
        currentCanCombine = !!canCombine;
        
        // ใช้ค่าจากการเลือกในหน้าหลัก
        _comboMode = mainPageComboMode;

        document.getElementById('slip-record-label').textContent = label;

        const amountBadge = document.getElementById('slip-record-amount-badge');
        const amountEl    = document.getElementById('slip-record-amount');
        if (amount) {
            amountEl.textContent = amount;
            amountBadge.classList.remove('hidden');
        } else {
            amountBadge.classList.add('hidden');
        }

        const panel = document.getElementById('slip-modal_panel');
        if (panel._x_dataStack) {
            const alpineData = Alpine.$data(panel);
            alpineData.files       = [];
            alpineData.errorMsg    = '';
            alpineData.submitting  = false;
            alpineData.rentalTypes = defaultRentalTypesFor(recordId);
        }

        openModal('slip-modal');
    }

    function defaultRentalTypesFor(recordId) {
        const meta = window.billingRecordMeta[recordId] || {};
        switch (meta.payment_type) {
            case 'monthly_rent':
            case 'late_fee':
                return meta.has_land_tax ? ['rent', 'land_tax'] : ['rent'];
            case 'deposit':
                return ['deposit'];
            case 'processing_fee':
                return ['processing_fee'];
            default:
                return [];
        }
    }

    function closeSlipModal() {
        closeModal('slip-modal');
        currentRecordId = null;
    }

    function slipUploader() {
        return {
            files: [],
            isDragging: false,
            submitting: false,
            errorMsg: '',
            rentalTypes: [],
            rentalTypeOptions: [
                { key: 'rent',           label: 'ค่าเช่า' },
                { key: 'land_tax',       label: 'ค่าภาษีที่ดิน' },
                { key: 'utility',        label: 'ค่าน้ำ/ไฟ' },
                { key: 'deposit',        label: 'เงินมัดจำ' },
                { key: 'processing_fee', label: 'ค่าดำเนินการ' },
            ],

            toggleRentalType(key) {
                const idx = this.rentalTypes.indexOf(key);
                if (idx === -1) this.rentalTypes.push(key);
                else this.rentalTypes.splice(idx, 1);
            },

            init() {},

            handleDrop(e) {
                this.isDragging = false;
                this.addFiles(e.dataTransfer.files);
            },

            handleFileInput(e) {
                this.addFiles(e.target.files);
                e.target.value = '';
            },

            addFiles(fileList) {
                const allowed = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
                const maxSize = 5 * 1024 * 1024;

                for (const file of Array.from(fileList)) {
                    if (this.files.length >= 5) {
                        this.errorMsg = 'อัพโหลดได้สูงสุด 5 ไฟล์';
                        break;
                    }
                    if (!allowed.includes(file.type)) {
                        this.errorMsg = `ไฟล์ "${file.name}" ไม่รองรับ — กรุณาใช้ JPG, PNG หรือ PDF`;
                        continue;
                    }
                    if (file.size > maxSize) {
                        this.errorMsg = `ไฟล์ "${file.name}" ขนาดเกิน 5MB`;
                        continue;
                    }
                    this.errorMsg = '';
                    this.files.push(file);
                }
            },

            removeFile(index) {
                this.files.splice(index, 1);
                if (this.files.length < 5) this.errorMsg = '';
            },

            formatSize(bytes) {
                if (bytes < 1024)        return bytes + ' B';
                if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
                return (bytes / 1024 / 1024).toFixed(1) + ' MB';
            },

            submit() {
                if (this.files.length === 0 || !currentRecordId) return;
                this.submitting = true;
                this.errorMsg   = '';

                const formData = new FormData();
                formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
                if (currentIsPhase2Combo) {
                    formData.append('combo_mode', _comboMode);
                }
                const transferDateInput = document.querySelector('#slip-form [name="transfer_date"]');
                formData.append('transfer_date', transferDateInput ? transferDateInput.value : '');
                this.rentalTypes.forEach(t => formData.append('rental_types[]', t));
                this.files.forEach(f => formData.append('payment_slips[]', f));

                fetch(`${billingUploadBase}/${currentRecordId}/slip`, {
                    method: 'POST',
                    body: formData,
                })
                .then(res => {
                    if (res.redirected) {
                        window.location.href = res.url;
                        return;
                    }
                    return res.text().then(html => {
                        document.open();
                        document.write(html);
                        document.close();
                    });
                })
                .catch(() => {
                    this.submitting = false;
                    this.errorMsg = 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง';
                });
            }
        };
    }

    // เริ่มต้นโหมด combo และ tab เมื่อโหลดหน้าเว็บ
    document.addEventListener('DOMContentLoaded', function() {
        if (document.getElementById('main-combo-btn-join')) {
            const canCombine = @json($canCombinePayment ?? false);
            selectComboMode(canCombine ? 'join' : 'sep');
        }
        switchBillingTab('pending');
    });
</script>
@endpush
