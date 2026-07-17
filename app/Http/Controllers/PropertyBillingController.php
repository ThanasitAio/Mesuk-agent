<?php

namespace App\Http\Controllers;

use App\Models\HrCompany;
use App\Models\HrInvoice;
use App\Models\HrPaymentRecord;
use App\Models\HrProperty;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class PropertyBillingController extends Controller
{
    public function index()
    {
        $agentCode = session('agent_code');

        $properties = HrProperty::where('manager_agent_code', $agentCode)
            ->with(['propertyStatus', 'activeBooking.customer', 'activeBooking.paymentRecords', 'activeBooking.invoices', 'primaryImageMedia'])
            ->get();

        $withContract    = $properties->filter(fn ($p) => $p->activeBooking !== null)->values();
        $withoutContract = $properties->reject(fn ($p) => $p->activeBooking !== null)->values();

        logSystem(
            userType: 'agent',
            userId: session('agent_id'),
            module: 'Property',
            action: 'VIEW',
            description: 'ดูรายการอสังหาริมทรัพย์ที่ดูแล'
        );

        return view('properties.index', compact('withContract', 'withoutContract'));
    }

    public function show(HrProperty $property)
    {
        if ($property->manager_agent_code !== session('agent_code')) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงอสังหาริมทรัพย์นี้');
        }

        $property->load(['owner', 'imageMedia']);

        $booking = $property->activeBooking()->with(['customer', 'paymentRecords'])->first();

        if (! $booking) {
            return redirect()->route('properties.index')
                ->with('error', 'อสังหาริมทรัพย์นี้ยังไม่มีการจอง');
        }

        $company = HrCompany::getActive();
        $billing = $this->buildBillingContext($booking, $property, $company);

        $invoices = HrInvoice::where('booking_id', $booking->id)
            ->where('status', 'approved')
            ->orderBy('approved_at', 'desc')
            ->get();

        logSystem(
            userType: 'agent',
            userId: session('agent_id'),
            module: 'Property',
            action: 'VIEW',
            description: "ดูรอบบิลของ {$property->title} (#{$property->property_code})"
        );

        return view('properties.show', array_merge(
            compact('property', 'booking', 'company', 'invoices'),
            $billing
        ));
    }

    public function togglePrePay(Request $request, HrProperty $property)
    {
        if ($property->manager_agent_code !== session('agent_code')) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงอสังหาริมทรัพย์นี้');
        }

        $booking = $property->activeBooking()->with('paymentRecords')->first();
        abort_if(! $booking, 404, 'ไม่พบข้อมูลการจอง');

        if ($booking->isContractSent()) {
            return redirect()
                ->route('properties.show', $property->id)
                ->with('error', 'สัญญาส่งแล้ว ไม่จำเป็นต้องเปิดเงื่อนไขนี้');
        }

        if (! $booking->hasInitialPaymentsUploaded()) {
            return redirect()
                ->route('properties.show', $property->id)
                ->with('error', 'ยังไม่ได้อัปโหลดสลิปมัดจำ ไม่สามารถสลับสถานะได้');
        }

        $newValue = ! $booking->allow_pay_before_contract;
        $booking->update(['allow_pay_before_contract' => $newValue]);

        logSystem(
            userType: 'agent',
            userId: session('agent_id'),
            module: 'Property',
            action: 'UPDATE',
            description: ($newValue ? 'เปิด' : 'ปิด') . "ชำระค่าเช่าก่อนสัญญา — {$property->title}"
        );

        return redirect()
            ->route('properties.show', $property->id)
            ->with('success', ($newValue ? 'เปิด' : 'ปิด') . 'การชำระค่าเช่าก่อนสัญญาเรียบร้อย');
    }

    public function uploadSlip(Request $request, HrPaymentRecord $record)
    {
        $record->load('booking.paymentRecords');
        $booking = $record->booking;
        abort_if(! $booking, 404, 'ไม่พบข้อมูลการจอง');

        $property = HrProperty::where('id', $booking->property_id)
            ->where('manager_agent_code', session('agent_code'))
            ->firstOrFail();

        if (! $record->canUploadSlip($booking)) {
            return back()->with('error', 'รายการนี้ไม่สามารถอัพโหลดสลิปได้ในขั้นตอนปัจจุบัน');
        }

        $request->validate([
            'payment_slips'    => 'required|array|min:1|max:5',
            'payment_slips.*'  => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'combo_mode'       => 'nullable|in:join,sep',
            'transfer_date'    => 'required|date|before_or_equal:today',
            'rental_types'     => 'nullable|array',
            'rental_types.*'   => 'string|in:rent,land_tax,utility,deposit,processing_fee',
        ], [
            'payment_slips.required'  => 'กรุณาเลือกไฟล์สลิปอย่างน้อย 1 ไฟล์',
            'payment_slips.max'       => 'อัพโหลดได้สูงสุด 5 ไฟล์',
            'payment_slips.*.mimes'   => 'ไฟล์ต้องเป็น JPG, PNG หรือ PDF เท่านั้น',
            'payment_slips.*.max'     => 'ขนาดแต่ละไฟล์ต้องไม่เกิน 5MB',
            'transfer_date.required'  => 'กรุณาระบุวันที่โอน',
            'transfer_date.date'      => 'วันที่โอนไม่ถูกต้อง',
            'transfer_date.before_or_equal' => 'วันที่โอนต้องไม่เกินวันนี้',
        ]);

        $bookingId = $record->booking_id;
        $comboMode = $request->input('combo_mode', 'sep');
        $isPhase2Deposit = $record->isPhase2Deposit();
        $paidAt = \Carbon\Carbon::parse($request->input('transfer_date'))->setTimeFrom(now());

        $rentalTypeLabels = [
            'rent'            => 'ค่าเช่า',
            'land_tax'        => 'ค่าภาษีที่ดิน',
            'utility'         => 'ค่าน้ำ/ไฟ',
            'deposit'         => 'เงินมัดจำ',
            'processing_fee'  => 'ค่าดำเนินการ',
        ];
        $rentalTypeTags = array_values($request->input('rental_types', []));
        $selectedRentalTypes = collect($rentalTypeTags)
            ->map(fn ($key) => $rentalTypeLabels[$key] ?? $key)
            ->implode(', ');

        // ตรวจสอบว่าเป็นการอัพโหลดแบบรวม (combo) หรือไม่
        $isComboUpload = $isPhase2Deposit && $comboMode === 'join';

        if ($record->payment_status === 'failed') {
            $oldSlips = $record->payment_slips ?? [];
            if ($record->payment_slip_path && ! in_array($record->payment_slip_path, $oldSlips, true)) {
                $oldSlips[] = $record->payment_slip_path;
            }
            foreach ($oldSlips as $oldPath) {
                Storage::disk('payment_storage')->delete($oldPath);
            }
            $existingSlips = [];
        } else {
            $existingSlips = $record->payment_slips ?? [];
        }

        $newPaths = [];
        foreach ($request->file('payment_slips') as $file) {
            $path       = $file->store("payment_slips/{$bookingId}", 'payment_storage');
            $newPaths[] = $path;
        }

        $allSlips     = array_merge($existingSlips, $newPaths);
        $mainSlipPath = $allSlips[0] ?? null;

        // อัพเดทรายการหลัก (มัดจำงวด 2)
        $record->update([
            'payment_slip_path' => $mainSlipPath,
            'payment_slips'     => $allSlips,
            'payment_status'    => 'pending_verification',
            'paid_at'           => $paidAt,
            'rental_type_tags'  => $rentalTypeTags,
        ]);

        // ถ้าเป็นโหมดรวม ให้อัพเดทรายการค่าเช่าเดือน 1 ด้วย
        if ($isComboUpload) {
            $month1Record = $booking->paymentRecords()
                ->where('payment_type', 'monthly_rent')
                ->where('month_number', 1)
                ->whereIn('payment_status', ['pending', 'failed'])
                ->first();

            if ($month1Record) {
                $month1Record->update([
                    'payment_slip_path' => $mainSlipPath,
                    'payment_slips'     => $allSlips,
                    'payment_status'    => 'pending_verification',
                    'paid_at'           => $paidAt,
                    'rental_type_tags'  => $rentalTypeTags,
                ]);
            }
        }

        $booking->updatePaymentStatus();

        $logDescription = $isComboUpload
            ? "แนบสลิปแทนลูกค้า (รวม): มัดจำงวด 2 + ค่าเช่าเดือน 1 — {$property->title}"
            : "แนบสลิปแทนลูกค้า: {$record->getTypeLabel()} — {$property->title}";
        $logDescription .= " | วันที่โอน: {$paidAt->format('d/m/Y')}";
        if ($selectedRentalTypes !== '') {
            $logDescription .= " | ประเภท: {$selectedRentalTypes}";
        }

        logSystem(
            userType: 'agent',
            userId: session('agent_id'),
            module: 'Property',
            action: 'CREATE',
            description: $logDescription
        );

        $successMessage = $isComboUpload
            ? 'อัพโหลดสลิปรวม (มัดจำงวด 2 + ค่าเช่าเดือน 1) เรียบร้อยแล้ว รอการตรวจสอบจากทีมงาน'
            : 'อัพโหลดสลิปเรียบร้อยแล้ว รอการตรวจสอบจากทีมงาน';

        return redirect()
            ->route('properties.show', $property->id)
            ->with('success', $successMessage);
    }

    public function cancelSlip(HrPaymentRecord $record)
    {
        $record->load('booking.paymentRecords');
        $booking = $record->booking;
        abort_if(! $booking, 404, 'ไม่พบข้อมูลการจอง');

        $property = HrProperty::where('id', $booking->property_id)
            ->where('manager_agent_code', session('agent_code'))
            ->firstOrFail();

        if ($record->payment_status !== 'pending_verification') {
            return back()->with('error', 'ไม่สามารถยกเลิกสลิปได้ — สถานะไม่ใช่รอตรวจสอบ');
        }

        $oldSlipPath = $record->payment_slip_path;
        $oldSlips    = $record->payment_slips ?? [];
        $allPaths    = $oldSlips;
        if ($oldSlipPath && ! in_array($oldSlipPath, $allPaths, true)) {
            $allPaths[] = $oldSlipPath;
        }
        foreach ($allPaths as $path) {
            Storage::disk('payment_storage')->delete($path);
        }

        $record->update([
            'payment_slip_path' => null,
            'payment_slips'     => null,
            'payment_status'    => 'pending',
            'paid_at'           => null,
        ]);

        // ถ้าเป็นมัดจำงวด 2 ที่อัพโหลดแบบรวม ให้รีเซ็ตค่าเช่าเดือน 1 ที่ใช้สลิปเดียวกันด้วย
        if ($record->isPhase2Deposit() && $oldSlipPath) {
            $month1 = $booking->paymentRecords
                ->where('payment_type', 'monthly_rent')
                ->where('month_number', 1)
                ->where('payment_status', 'pending_verification')
                ->filter(fn ($r) => $r->payment_slip_path === $oldSlipPath)
                ->first();

            if ($month1) {
                $month1->update([
                    'payment_slip_path' => null,
                    'payment_slips'     => null,
                    'payment_status'    => 'pending',
                    'paid_at'           => null,
                ]);
            }
        }

        $booking->updatePaymentStatus();

        logSystem(
            userType: 'agent',
            userId: session('agent_id'),
            module: 'Property',
            action: 'DELETE',
            description: "ยกเลิกสลิป: {$record->getTypeLabel()} — {$property->title}"
        );

        return redirect()
            ->route('properties.show', $property->id)
            ->with('success', 'ยกเลิกสลิปเรียบร้อยแล้ว สามารถแนบสลิปใหม่ได้');
    }

    public function viewSlip(Request $request, HrPaymentRecord $record)
    {
        $booking = $record->booking()->withTrashed()->first();
        abort_if(! $booking, 404);

        HrProperty::where('id', $booking->property_id)
            ->where('manager_agent_code', session('agent_code'))
            ->firstOrFail();

        $index = (int) $request->query('index', 0);
        $slips = $record->payment_slips ?? [];
        if (empty($slips) && $record->payment_slip_path) {
            $slips = [$record->payment_slip_path];
        }

        abort_if(empty($slips) || ! isset($slips[$index]), 404, 'ไม่พบไฟล์สลิป');

        $slipPath = $slips[$index];
        abort_if(! Storage::disk('payment_storage')->exists($slipPath), 404, 'ไม่พบไฟล์สลิป');

        return response()->file(Storage::disk('payment_storage')->path($slipPath));
    }

    public function printInvoice(HrInvoice $invoice)
    {
        $booking = $invoice->booking()->first();
        abort_if(! $booking, 404, 'ไม่พบข้อมูลการจอง');

        HrProperty::where('id', $booking->property_id)
            ->where('manager_agent_code', session('agent_code'))
            ->firstOrFail();

        abort_if($invoice->status !== 'approved', 403, 'ใบแจ้งหนี้นี้ยังไม่ได้รับการอนุมัติ');

        $company = HrCompany::getActive();
        $happyestPublic = rtrim(env('HAPPYEST_APP_URL', 'http://127.0.0.1/happyest/public'), '/');

        logSystem(
            userType: 'agent',
            userId: session('agent_id'),
            module: 'Property',
            action: 'VIEW',
            description: "พิมพ์ใบแจ้งหนี้ {$invoice->invoice_code}"
        );

        return view('invoices.print', compact('invoice', 'company', 'happyestPublic'));
    }

    /**
     * Build billing display context — mirrors happyest payment/show.blade.php logic.
     */
    private function buildBillingContext($booking, HrProperty $property, ?HrCompany $company): array
    {
        $paymentCondition    = $property->payment_condition ?? 'customer_company_investor';
        $depositRoute        = $property->deposit_payment_route ?? 'customer_company_investor';
        $isRentToInvestor    = $paymentCondition === 'customer_investor_company';
        $isDepositToInvestor = $depositRoute === 'customer_investor_company';

        $depositInvestorPct = (float) ($property->deposit_investor_percent ?? 90);
        $depositCompanyPct  = (float) ($property->deposit_company_percent ?? 10);
        $landTaxToInvestor  = $booking->land_tax_to_investor ?? ($property->land_tax_to_investor ?? false);

        $owner         = $property->owner;
        $companyName   = $company?->name_th ?? 'บริษัท';
        $investorName  = $owner ? trim(($owner->first_name ?? '') . ' ' . ($owner->last_name ?? '')) : 'นักลงทุน';
        $investorName  = $investorName ?: 'นักลงทุน';
        $happyestPublic = rtrim(env('HAPPYEST_APP_URL', 'http://127.0.0.1/happyest/public'), '/');

        $currentPaymentPhase   = $booking->currentPaymentPhase();
        $phasePaymentTypes     = $booking->allowedPaymentTypesForCurrentPhase();
        $isInitialPaymentPhase = $currentPaymentPhase === 'initial';
        $isWaitingContract     = $currentPaymentPhase === 'waiting_contract';
        $isFinalPaymentPhase   = $currentPaymentPhase === 'final';

        $phaseTitle = match ($currentPaymentPhase) {
            'initial'          => 'เฟสที่ 1: ชำระเงินมัดจำ',
            'waiting_contract' => 'รอดำเนินการสัญญา',
            default            => 'เฟสที่ 2: ค่าเช่าที่เหลือ',
        };

        $realFinalPhase = in_array($currentPaymentPhase, ['final', 'waiting_contract'], true);

        $ph1Records = $realFinalPhase
            ? $booking->paymentRecords
                ->whereIn('payment_type', ['deposit', 'processing_fee'])
                ->filter(fn ($r) => ! ($r->payment_type === 'deposit' && (int) $r->deposit_phase === 2))
                ->sortBy([['due_date', 'asc'], ['id', 'asc']])
                ->values()
            : collect();

        $isDepositPendingVerification = $realFinalPhase
            && $ph1Records->contains(fn ($r) => $r->payment_status === 'pending_verification');

        $allRecords = $booking->paymentRecords
            ->sortBy([['due_date', 'asc'], ['id', 'asc']])
            ->values();

        $pendingRecords   = $allRecords->where('payment_status', 'pending')->values();
        $verifyingRecords = $allRecords->where('payment_status', 'pending_verification')->values();
        $paidRecords      = $allRecords->where('payment_status', 'paid')->values();
        $failedRecords    = $allRecords->where('payment_status', 'failed')->values();

        $depositIsUploaded = $isInitialPaymentPhase
            && $pendingRecords->isEmpty()
            && $failedRecords->isEmpty()
            && ($verifyingRecords->isNotEmpty() || $paidRecords->isNotEmpty());

        $allowPay = (bool) ($booking->allow_pay_before_contract ?? false);

        $phaseDescription = match (true) {
            $isInitialPaymentPhase && $depositIsUploaded => 'สลิปมัดจำกำลังรอตรวจสอบ ทีมงานจะดำเนินการภายใน 1–3 ชั่วโมง',
            $isInitialPaymentPhase                       => 'ชำระเงินมัดจำก่อน ทีมงานจะตรวจสอบสลิปและดำเนินการจัดทำสัญญาให้ต่อไป',
            $isWaitingContract                           => 'เงินมัดจำได้รับการยืนยันแล้ว รอแอดมินดำเนินการจัดทำสัญญา',
            $isDepositPendingVerification                => 'สลิปมัดจำอยู่ระหว่างรอตรวจสอบ ลูกค้าสามารถชำระค่าเช่าที่เหลือได้เลย (หากเปิดชำระก่อนสัญญา)',
            default                                      => 'เงินมัดจำได้รับการยืนยันแล้ว ระบบแสดงเฉพาะรายการค่าเช่าที่เหลือให้ชำระต่อ',
        };

        $phase2DepositRecord = null;
        $firstPendingRent    = null;
        $actionableRecords   = collect();
        $lockedRecords       = collect();

        if ($isFinalPaymentPhase) {
            $firstPendingRent = $allRecords
                ->whereIn('payment_status', ['pending', 'failed'])
                ->where('payment_type', 'monthly_rent')
                ->first();

            $phase2DepositRecord = $allRecords
                ->where('payment_type', 'deposit')
                ->where('deposit_phase', 2)
                ->whereIn('payment_status', ['pending', 'failed'])
                ->first();

            if ($firstPendingRent) {
                $actionableRecords = $allRecords
                    ->whereIn('payment_status', ['pending', 'failed'])
                    ->filter(fn ($r) =>
                        ($r->payment_type === 'deposit' && (int) $r->deposit_phase === 2) ||
                        ($r->id === $firstPendingRent->id) ||
                        ($r->payment_type === 'late_fee' && $r->source_payment_record_id === $firstPendingRent->id)
                    )
                    ->sortBy(fn ($r) => [(int) $r->deposit_phase === 2 ? 0 : 1, $r->due_date?->timestamp ?? 0])
                    ->values();

                $firstBarrierRent = $allRecords
                    ->whereNotIn('payment_status', ['paid'])
                    ->where('payment_type', 'monthly_rent')
                    ->first();

                $lockedRecords = $allRecords
                    ->where('payment_type', 'monthly_rent')
                    ->whereIn('payment_status', ['pending', 'failed'])
                    ->filter(fn ($r) => $firstBarrierRent ? $r->id !== $firstBarrierRent->id : true)
                    ->values();
            } else {
                $actionableRecords = $allRecords->whereIn('payment_status', ['pending', 'failed'])->values();
            }
        } else {
            $actionableRecords = $allRecords->whereIn('payment_status', ['pending', 'failed'])->values();
        }

        // ─── Combo Payment Detection (Deposit Phase 2 + Month 1 Rent) ───
        $hasComboPayment   = false;
        $canCombinePayment = false;
        $comboMonth1Record = null;

        // ตรวจสอบ deposit phase 2 แยกออกมาเพื่อให้ combo detection ทำงานได้ทุก phase
        // (รวม waiting_contract) เนื่องจาก agent แสดง record ทุก phase รวมกัน
        if (! $phase2DepositRecord) {
            $phase2DepositRecord = $allRecords
                ->where('payment_type', 'deposit')
                ->filter(fn ($r) => (int) $r->deposit_phase === 2)
                ->whereIn('payment_status', ['pending', 'failed'])
                ->first();
        }

        if ($phase2DepositRecord) {
            $firstMonth1Rent = $allRecords
                ->where('payment_type', 'monthly_rent')
                ->where('month_number', 1)
                ->whereIn('payment_status', ['pending', 'failed'])
                ->first();

            if ($firstMonth1Rent) {
                $hasComboPayment   = true;
                $canCombinePayment = ($depositRoute === $paymentCondition);
                $comboMonth1Record = $firstMonth1Rent;
            }
        }

        // Agent: แสดง record ทุกเฟสรวมกัน ไม่มี locked section
        $actionableRecords = $allRecords->whereIn('payment_status', ['pending', 'failed'])->values();
        $lockedRecords     = collect();
        $displayRecords    = $allRecords;

        $allForMeta = $displayRecords->merge($lockedRecords)->unique('id');

        $recordMeta = $this->buildRecordMeta(
            $booking,
            $allForMeta,
            $allRecords,
            $hasComboPayment,
            $comboMonth1Record,
            $isRentToInvestor,
            $isDepositToInvestor,
            $depositInvestorPct,
            $depositCompanyPct,
            $landTaxToInvestor,
            $investorName,
            $companyName,
            $owner,
            $company,
            $happyestPublic
        );

        $billingRecords = $booking->paymentRecords;

        return compact(
            'billingRecords',
            'displayRecords',
            'lockedRecords',
            'ph1Records',
            'allRecords',
            'actionableRecords',
            'currentPaymentPhase',
            'phaseTitle',
            'phaseDescription',
            'isInitialPaymentPhase',
            'isWaitingContract',
            'isFinalPaymentPhase',
            'realFinalPhase',
            'isDepositPendingVerification',
            'allowPay',
            'hasComboPayment',
            'canCombinePayment',
            'comboMonth1Record',
            'phase2DepositRecord',
            'paymentCondition',
            'depositRoute',
            'isRentToInvestor',
            'isDepositToInvestor',
            'depositInvestorPct',
            'depositCompanyPct',
            'landTaxToInvestor',
            'companyName',
            'investorName',
            'happyestPublic',
            'recordMeta'
        );
    }

    private function buildRecordMeta(
        $booking,
        Collection $displayRecords,
        Collection $allRecords,
        bool $hasComboPayment,
        ?HrPaymentRecord $comboMonth1Record,
        bool $isRentToInvestor,
        bool $isDepositToInvestor,
        float $depositInvestorPct,
        float $depositCompanyPct,
        bool $landTaxToInvestor,
        string $investorName,
        string $companyName,
        $owner,
        ?HrCompany $company,
        string $happyestPublic
    ): array {
        $meta = [];

        foreach ($displayRecords as $record) {
            $isRentRec  = in_array($record->payment_type, ['monthly_rent', 'late_fee'], true);
            $recToInv   = $isRentRec ? $isRentToInvestor : $isDepositToInvestor;
            $isSplit    = false;
            $splitInv   = 0.0;
            $splitCom   = 0.0;

            if ($recToInv && in_array($record->payment_type, ['monthly_rent', 'deposit', 'processing_fee'], true)) {
                if ($record->payment_type === 'monthly_rent') {
                    $baseRent = (float) ($record->base_rent_amount ?? 0) > 0
                        ? (float) $record->base_rent_amount
                        : (float) $record->amount;
                    $splitInv = $landTaxToInvestor
                        ? $baseRent + (float) ($record->land_tax_amount ?? 0)
                        : $baseRent;
                    $splitCom = round((float) $record->amount - $splitInv, 2);
                } else {
                    $splitInv = round((float) $record->amount * $depositInvestorPct / 100, 2);
                    $splitCom = round((float) $record->amount - $splitInv, 2);
                }

                $isSplit = $splitCom > 0 && (
                    $record->payment_type === 'monthly_rent' ||
                    ($depositInvestorPct > 0 && $depositCompanyPct > 0 && ($depositInvestorPct + $depositCompanyPct) >= 99)
                );
            }

            $promptpayId = $recToInv
                ? ($owner->promptpay_id ?? ($owner->mobile ?? null))
                : ($company->promptpay_id ?? null);

            $qrPath = $recToInv ? ($owner->bank_qr_code_path ?? null) : ($company->qr_code_image ?? null);
            $qrUrl  = (! $promptpayId && $qrPath) ? $happyestPublic . '/storage/' . $qrPath : null;

            $meta[$record->id] = [
                'display_label'         => $record->getDisplayLabel($allRecords, $hasComboPayment, $comboMonth1Record),
                'combo_amount'          => $record->getComboAmount($allRecords, $hasComboPayment, $comboMonth1Record),
                'to_investor'           => $recToInv,
                'recipient_name'      => $recToInv ? $investorName : $companyName,
                'is_split_payment'      => $isSplit,
                'split_investor_amount' => $splitInv,
                'split_company_amount'  => $splitCom,
                'is_phase2_combo'       => $record->isPhase2Deposit() && $hasComboPayment,
                'is_combo_month1'       => $comboMonth1Record && $record->id === $comboMonth1Record->id && $hasComboPayment,
                'sep_display_label'     => $record->getTypeLabel(),
                'own_amount'            => (float) $record->amount,
                'can_upload'            => $record->canUploadSlip($booking),
                'is_locked'             => false,
                'bank_name'             => $recToInv ? ($owner->bank_name ?? null) : ($company->bank_name ?? null),
                'bank_account'          => $recToInv ? ($owner->bank_account_number ?? null) : ($company->bank_account_number ?? null),
                'bank_acct_name'        => $recToInv ? ($owner->bank_account_name ?? null) : ($company->bank_account_name ?? null),
                'bank_branch'           => $recToInv ? ($owner->bank_branch ?? null) : ($company->bank_branch ?? null),
                'qr_url'                => $qrUrl,
                'promptpay_id'          => $promptpayId,
                'payment_type'          => $record->payment_type,
                'has_land_tax'          => (float) ($record->land_tax_amount ?? 0) > 0,
            ];
        }

        return $meta;
    }
}
