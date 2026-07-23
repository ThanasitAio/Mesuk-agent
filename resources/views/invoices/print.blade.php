<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ใบแจ้งหนี้ {{ $invoice->invoice_code }}</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700;800&display=swap');
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Sarabun', sans-serif; background: #c8d4e3; font-size: 9.5pt; color: #000; line-height: 1.45; }
.toolbar { background: #1e293b; height: 52px; display: flex; align-items: center; justify-content: flex-end; padding: 0 20px; position: sticky; top: 0; z-index: 200; gap: 8px; }
.tb-btn { display: inline-flex; align-items: center; gap: 5px; padding: 6px 14px; border-radius: 6px; font-size: 13px; font-weight: 600; font-family: 'Sarabun', sans-serif; border: none; cursor: pointer; text-decoration: none; white-space: nowrap; line-height: 1; }
.tb-print { background: #3b82f6; color: #fff; }
.tb-print:hover { background: #2563eb; }
.tb-close { background: #374151; color: #d1d5db; }
.tb-close:hover { background: #4b5563; color: #fff; }
.page-wrapper { display: flex; justify-content: center; align-items: flex-start; padding: 30px 16px 50px; min-height: calc(100vh - 52px); }
.invoice-sheet { background: #fff; width: 210mm; min-height: 297mm; padding: 10mm 14mm; box-shadow: 0 6px 36px rgba(0,0,0,0.18); font-family: 'Sarabun', sans-serif; font-size: 9.5pt; color: #000; line-height: 1.45; flex-shrink: 0; }

/* Items table */
.items-table { width: 100%; border-collapse: collapse; border: 1.5px solid #000; }
.items-table th { border: 1px solid #000; padding: 4px 5px; text-align: center; font-size: 8.5pt; font-weight: bold; line-height: 1.45; background: #fff; }
.items-table td { border-left: 1px solid #000; border-right: 1px solid #000; padding: 5px 6px; font-size: 9pt; vertical-align: top; }
.items-table td.num { text-align: center; }
.items-table td.amt { text-align: right; white-space: nowrap; }
.items-table tr.filler td { height: 20px; border-top: none; border-bottom: none; }
.items-table tr.note-row td { border-top: 1px solid #ccc; border-bottom: 1px solid #000; font-size: 8.5pt; color: #333; padding: 4px 6px; }

@media print {
  @page { size: A4 portrait; margin: 0; }
  html, body { margin:0!important; padding:0!important; background:#fff!important; width:210mm!important; overflow:hidden!important; -webkit-print-color-adjust:exact!important; print-color-adjust:exact!important; }
  .toolbar { display:none!important; }
  .page-wrapper { display:block!important; padding:0!important; margin:0!important; min-height:unset!important; width:210mm!important; }
  .invoice-sheet { width:210mm!important; min-height:297mm!important; padding:10mm 14mm!important; box-shadow:none!important; font-size:9.5pt!important; line-height:1.45!important; }
  * { -webkit-text-size-adjust:none!important; text-size-adjust:none!important; }
  table,th,td { -webkit-print-color-adjust:exact!important; print-color-adjust:exact!important; }
}
</style>
</head>
<body>

@php
    $snapProp  = $invoice->snapshot_property ?? [];
    $snapCust  = $invoice->snapshot_customer ?? [];
    $snapOwner = $invoice->snapshot_owner    ?? [];
    $snapBk    = $invoice->snapshot_booking  ?? [];
    $billingItems = $invoice->billing_items  ?? [];

    $isInvestorRoute = ($invoice->billing_route === 'investor');

    $billingMonthShort = null;
    if ($invoice->billing_month) {
        [$bYr, $bMo] = explode('-', $invoice->billing_month);
        $billingMonthShort = 'ด.' . (int)$bMo . '/' . ((int)$bYr + 543 - 2500);
    }

    $baseAmount  = (float) $invoice->amount;
    $vatAmount   = (float) $invoice->tax_amount;
    $totalAmount = (float) $invoice->total_amount;

    $whtRate = (float)($snapBk['withholding_tax_rate'] ?? 0);
    if ($whtRate > 0) {
        $whtAmountBooked = (float)($snapBk['withholding_tax_amount'] ?? 0);
        $whtAmount = $whtAmountBooked > 0 ? $whtAmountBooked : round($baseAmount * $whtRate / 100, 2);
    } else {
        $whtAmount = 0.0;
    }
    $netPayable = round($totalAmount - $whtAmount, 2);

    $invoiceDate   = $invoice->approved_at ?? $invoice->created_at;
    $invoiceDateDm = $invoiceDate->format('d/m/Y');

    $payDueDayNum = (int)($snapBk['payment_due_day'] ?? 5);
    $dueDate      = $invoiceDate->copy()->startOfMonth()->addMonth()->setDay($payDueDayNum);
    $dueDateDm    = $dueDate->format('d/m/Y');

    $propCode  = $snapProp['property_code'] ?? '';
    $propTitle = $snapProp['title'] ?? '';
    $propType  = $snapProp['type'] ?? '';
    $propPrice    = $snapProp['price'] ?? null;
    $propSideArea = $snapProp['side_area'] ?? null;
    $hasPropDetails = ($propPrice && (float)$propPrice > 0) || ($propSideArea && (float)$propSideArea > 0);

    $typeLabel = match($invoice->invoice_type ?? '') {
        'deposit'      => 'ค่ามัดจำ',
        'service_fee'  => 'ค่าดำเนินการ',
        'monthly_rent' => 'ค่าเช่า' . ($propType ?: ''),
        default        => 'ค่าเช่า' . ($propType ?: ''),
    };
    $defaultItemLabel = trim($typeLabel . ($propCode ? ' '.$propCode : '') . ($billingMonthShort ? ' '.$billingMonthShort : ''));

    $custName    = $snapCust['name']    ?? '';
    $custAddress = $snapCust['address'] ?? '';
    $custMobile  = $snapCust['mobile']  ?? '';
    $custTaxId   = $snapCust['tax_id']  ?? '';
    $custUid     = $snapCust['uid']     ?? '';
    $custLabel   = $custUid ? "({$custUid}) {$custName}" : $custName;

    // Company info (recipient when billing_route = 'company')
    $coNameTh      = $company?->name_th             ?? 'บริษัท มีสุข โซลูชั่นส์ จำกัด (มหาชน)';
    $coNameEn      = $company?->name_en             ?? 'MESUK CORPORATION (2006) CO.,LTD.';
    $coAddrTh      = $company?->address_th          ?? '';
    $coPhone       = $company?->phone               ?? '';
    $coFax         = $company?->fax                 ?? '';
    $coTaxId       = $company?->tax_id              ?? '';
    $coBankName    = $company?->bank_name           ?? '';
    $coBankBranch  = $company?->bank_branch         ?? '';
    $coBankAccNum  = $company?->bank_account_number ?? '';
    $coBankAccName = $company?->bank_account_name   ?? '';

    // Investor info (recipient when billing_route = 'investor')
    $invName    = $snapOwner['name']            ?? '';
    $invAddr    = $snapOwner['address']         ?? '';
    $invTaxId   = $snapOwner['tax_id']          ?? '';
    $invMobile  = $snapOwner['mobile']          ?? '';
    $invBankName    = $snapOwner['bank_name']           ?? '';
    $invBankBranch  = $snapOwner['bank_branch']         ?? '';
    $invBankAccNum  = $snapOwner['bank_account_number'] ?? '';
    $invBankAccName = $snapOwner['bank_account_name']   ?? '';

    // Recipient-based display values
    $recipientBankName    = $isInvestorRoute ? $invBankName    : $coBankName;
    $recipientBankBranch  = $isInvestorRoute ? $invBankBranch  : $coBankBranch;
    $recipientBankAccNum  = $isInvestorRoute ? $invBankAccNum  : $coBankAccNum;
    $recipientBankAccName = $isInvestorRoute ? $invBankAccName : $coBankAccName;
    $recipientName        = $isInvestorRoute ? $invName        : $coNameTh;

    // Logo & signature (from happyest public)
    $logoSrc = null;
    if (!empty($company?->logo)) {
        $logoSrc = $happyestPublic . '/storage/' . $company->logo;
    }

    $coSignatureSrc = null;
    if (!empty($company?->signature_image)) {
        $coSignatureSrc = $happyestPublic . '/storage/' . $company->signature_image;
    }

    $itemCount   = count($billingItems) ?: 1;
    $fillerCount = max(0, 7 - $itemCount);
@endphp

{{-- Toolbar --}}
<div class="toolbar">
    <button onclick="window.print()" class="tb-btn tb-print">🖨 พิมพ์ / Save PDF</button>
    <button onclick="window.close()" class="tb-btn tb-close">✕ ปิด</button>
</div>

<div class="page-wrapper">
<div class="invoice-sheet">

    {{-- ─── S1: Header (Company หรือ นักลงทุน ตาม billing_route) ─── --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; table-layout:fixed; margin-bottom:2mm;">
        <tr>
            @if(!$isInvestorRoute)
            <td valign="middle" style="width:34mm; padding-right:3mm;">
                @if($logoSrc)
                    <img src="{{ $logoSrc }}" alt="Logo" style="width:30mm; height:auto; display:block;">
                @endif
            </td>
            @endif
            <td valign="middle" style="text-align:center;">
                @if($isInvestorRoute)
                    <div style="font-size:13pt; font-weight:bold; line-height:1.3; margin-bottom:2mm;"><strong>{{ $invName }}</strong></div>
                    <div style="font-size:8pt; line-height:1.55;">
                        {{ $invAddr }}<br>
                        @if($invTaxId)เลขประจำตัวผู้เสียภาษี <strong>{{ $invTaxId }}</strong><br>@endif
                        @if($invMobile)โทร.&nbsp;{{ $invMobile }}@endif
                    </div>
                @else
                    <div style="font-size:13pt; font-weight:bold; line-height:1.3; margin-bottom:1mm;"><strong>{{ $coNameTh }}</strong></div>
                    <div style="font-size:10pt; font-weight:bold; line-height:1.3; margin-bottom:2mm;"><strong>{{ $coNameEn }}</strong></div>
                    <div style="font-size:8pt; line-height:1.55;">
                        {{ $coAddrTh }}<br>
                        สำนักงานใหญ่&nbsp;&nbsp;เลขประจำตัวผู้เสียภาษี <strong>{{ $coTaxId }}</strong><br>
                        โทร.&nbsp;{{ $coPhone }}@if($coFax)&nbsp;&nbsp;แฟกซ์.&nbsp;{{ $coFax }}@endif
                    </div>
                @endif
            </td>
            <td valign="top" style="width:34mm; text-align:right; font-size:7.5pt; white-space:nowrap; padding-top:1mm;">Page 1/1</td>
        </tr>
    </table>

    <hr style="border:none; border-top:2px solid #000; margin:2mm 0 2mm;">

    {{-- ─── S3: Customer Box + Invoice Info ─── --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; border:1.5px solid #000;">
        <tr>
            <td colspan="2" style="background:#000; color:#fff; text-align:center; font-size:12pt; font-weight:bold; padding:4px 8px; letter-spacing:1px; border:1.5px solid #000;">
                <strong>ใบแจ้งหนี้ / INVOICE</strong>
            </td>
        </tr>
        <tr>
            <td width="70%" valign="top" style="padding:5px 8px; border-left:1.5px solid #000; border-right:1px solid #000; border-bottom:1.5px solid #000; font-size:9pt;">
                <table width="100%" cellpadding="2" cellspacing="0" style="border-collapse:collapse;">
                    <tr>
                        <td nowrap valign="top" style="width:62px; font-weight:bold; padding-right:4px; white-space:nowrap;"><strong>ลูกค้า :</strong></td>
                        <td valign="top"><strong>{{ $custLabel }}</strong></td>
                    </tr>
                    <tr>
                        <td nowrap valign="top" style="font-size:7.5pt; color:#555; padding-right:4px; white-space:nowrap;">Customer</td>
                        <td valign="top">{{ $custAddress }}</td>
                    </tr>
                    <tr>
                        <td></td>
                        <td valign="top">Tel.&nbsp;{{ $custMobile ?: '-' }}</td>
                    </tr>
                    <tr>
                        <td nowrap valign="top" style="font-weight:bold; padding-right:4px; padding-top:5px; white-space:nowrap;"><strong>ผู้ติดต่อ :</strong></td>
                        <td valign="top" style="padding-top:5px;">
                            {{ $custName }}
                            @if($custTaxId)&nbsp;&nbsp;Personal ID : <span style="font-family:'Courier New',Courier,monospace;">{{ $custTaxId }}</span>@endif
                        </td>
                    </tr>
                </table>
            </td>
            <td width="30%" valign="top" style="padding:5px 10px; border-right:1.5px solid #000; border-bottom:1.5px solid #000;">
                <table width="100%" cellpadding="2" cellspacing="0" style="border-collapse:collapse;">
                    <tr>
                        <td nowrap valign="top" style="font-weight:bold; padding-right:6px;">
                            <strong>เลขที่</strong><br><span style="font-size:7.5pt; font-weight:normal;">No.</span>
                        </td>
                        <td valign="top" style="border-bottom:1px solid #aaa; padding-bottom:2px;"><strong>{{ $invoice->invoice_code }}</strong></td>
                    </tr>
                    <tr><td colspan="2" style="height:6px;"></td></tr>
                    <tr>
                        <td nowrap valign="top" style="font-weight:bold; padding-right:6px;">
                            <strong>วันที่</strong><br><span style="font-size:7.5pt; font-weight:normal;">Date</span>
                        </td>
                        <td valign="top" style="border-bottom:1px solid #aaa; padding-bottom:2px;"><strong>{{ $invoiceDateDm }}</strong></td>
                    </tr>
                    <tr><td colspan="2" style="height:6px;"></td></tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- ─── S4: Reference Row ─── --}}
    <table width="100%" cellpadding="3" cellspacing="0" style="border-collapse:collapse; border:1.5px solid #000; border-top:none;">
        <thead>
            <tr>
                <th width="22%" style="border:1px solid #000; text-align:center; font-size:8.5pt; font-weight:bold; line-height:1.4; background:#fff;">
                    เลขที่เอกสารอ้างอิง<br><span style="font-size:7.5pt; font-weight:normal;">Ref. Document</span>
                </th>
                <th width="14%" style="border:1px solid #000; text-align:center; font-size:8.5pt; font-weight:bold; line-height:1.4; background:#fff;">
                    วันที่<br><span style="font-size:7.5pt; font-weight:normal;">Date</span>
                </th>
                <th width="28%" style="border:1px solid #000; text-align:center; font-size:8.5pt; font-weight:bold; line-height:1.4; background:#fff;">
                    เลขที่ใบสั่งซื้อ/สัญญา<br><span style="font-size:7.5pt; font-weight:normal;">P.O. No. / Contract No.</span>
                </th>
                <th width="22%" style="border:1px solid #000; text-align:center; font-size:8.5pt; font-weight:bold; line-height:1.4; background:#fff;">
                    เงื่อนไขชำระเงิน<br><span style="font-size:7.5pt; font-weight:normal;">Term of Payment</span>
                </th>
                <th width="14%" style="border:1px solid #000; text-align:center; font-size:8.5pt; font-weight:bold; line-height:1.4; background:#fff;">
                    วันครบกำหนด<br><span style="font-size:7.5pt; font-weight:normal;">Due Date</span>
                </th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="border:1px solid #000; text-align:center; font-size:9pt;">&nbsp;</td>
                <td style="border:1px solid #000; text-align:center; font-size:9pt; white-space:nowrap;">{{ $invoiceDateDm }}</td>
                <td style="border:1px solid #000; text-align:center; font-size:9pt;">{{ $snapBk['booking_code'] ?? '' }}</td>
                <td style="border:1px solid #000; text-align:center; font-size:9pt;">วันที่ {{ $payDueDayNum }} ของเดือน</td>
                <td style="border:1px solid #000; text-align:center; font-size:9pt; white-space:nowrap;">{{ $dueDateDm }}</td>
            </tr>
        </tbody>
    </table>

    {{-- ─── S5: Project Box ─── --}}
    <div style="border:1.5px solid #000; border-top:none; padding:4px 8px; font-size:9pt; @if(!$hasPropDetails)margin-bottom:3mm;@endif">
        <strong>โครงการ / Project</strong>&nbsp;&nbsp;@if($propCode)<strong>{{ $propCode }}</strong>&nbsp;@endif<span>{{ $propTitle }}</span>
    </div>
    @if($hasPropDetails)
    <div style="border:1.5px solid #000; border-top:none; padding:3px 8px; font-size:8.5pt; margin-bottom:3mm;">
        @if((float)$propPrice > 0)<strong>ราคาทรัพย์</strong>&nbsp;{{ number_format((float)$propPrice, 2) }}&nbsp;บาท@endif
        @if((float)$propPrice > 0 && (float)$propSideArea > 0)&nbsp;&nbsp;|&nbsp;&nbsp;@endif
        @if((float)$propSideArea > 0)<strong>พื้นที่ด้านข้าง</strong>&nbsp;{{ number_format((float)$propSideArea, 2) }}&nbsp;ตร.ม.@endif
    </div>
    @else
    <div style="margin-bottom:3mm;"></div>
    @endif

    {{-- ─── S6: Items Table ─── --}}
    <table class="items-table">
        <thead>
            <tr>
                <th width="7%">ลำดับ<br><span style="font-size:7.5pt; font-weight:normal;">Item</span></th>
                <th width="43%">รายการ<br><span style="font-size:7.5pt; font-weight:normal;">Description</span></th>
                <th width="12%">จำนวน<br><span style="font-size:7.5pt; font-weight:normal;">Qty</span></th>
                <th width="9%">หน่วย<br><span style="font-size:7.5pt; font-weight:normal;">Unit</span></th>
                <th width="15%">ราคา/หน่วย<br><span style="font-size:7.5pt; font-weight:normal;">Unit Price</span></th>
                <th width="14%">จำนวนเงิน<br><span style="font-size:7.5pt; font-weight:normal;">Amount</span></th>
            </tr>
        </thead>
        <tbody>
            @if(count($billingItems) > 0)
                @foreach($billingItems as $idx => $item)
                    @php $amt = (float)($item['amount'] ?? 0); @endphp
                    <tr>
                        <td class="num">{{ $idx + 1 }}</td>
                        <td><strong>{{ $item['label'] ?? $defaultItemLabel }}</strong></td>
                        <td class="num">1.00</td>
                        <td class="num">เดือน</td>
                        <td class="amt">{{ number_format($amt, 2) }}</td>
                        <td class="amt">{{ number_format($amt, 2) }}</td>
                    </tr>
                @endforeach
            @else
                <tr>
                    <td class="num">1</td>
                    <td><strong>{{ $defaultItemLabel }}</strong></td>
                    <td class="num">1.00</td>
                    <td class="num">เดือน</td>
                    <td class="amt">{{ number_format($baseAmount, 2) }}</td>
                    <td class="amt">{{ number_format($baseAmount, 2) }}</td>
                </tr>
            @endif

            @for($f = 0; $f < $fillerCount; $f++)
                <tr class="filler"><td></td><td></td><td></td><td></td><td></td><td></td></tr>
            @endfor

            <tr class="note-row">
                <td colspan="6">หมายเหตุ : {{ $invoice->notes ?: 'ชำระค่าเช่าไม่เกินวันที่ ' . $payDueDayNum . ' ของเดือนถัดไป' }}</td>
            </tr>
        </tbody>
    </table>

    {{-- ─── S7: Summary ─── --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
        <tr>
            <td width="35%" style="padding:4px 8px; font-size:9pt; color:#555; vertical-align:middle; border-left:1.5px solid #000; border-bottom:1px solid #000;">
                หัก ณ ที่จ่าย&nbsp;&nbsp;{{ $whtRate > 0 ? number_format($whtRate, 2).'%' : '' }}
            </td>
            <td width="15%" style="padding:4px 8px; font-size:9pt; text-align:right; white-space:nowrap; vertical-align:middle; border-right:1.5px solid #000; border-bottom:1px solid #000;">
                {{ $whtAmount > 0 ? number_format($whtAmount, 2) : '0.00' }}
            </td>
            <td width="35%" style="padding:4px 8px; font-size:9pt; vertical-align:middle; border-bottom:1px solid #000;">
                รวมเงิน <span style="font-size:7.5pt;">Total</span>
            </td>
            <td width="15%" style="padding:4px 8px; font-size:9pt; text-align:right; white-space:nowrap; vertical-align:middle; border-right:1.5px solid #000; border-bottom:1px solid #000;">
                {{ number_format($baseAmount, 2) }}
            </td>
        </tr>
        <tr>
            <td style="padding:4px 8px; font-size:9pt; vertical-align:middle; border-left:1.5px solid #000; border-bottom:1px solid #000;">
                <strong>จ่ายสุทธิ</strong> <span style="font-size:7.5pt; font-weight:normal;">Net Amount</span>
            </td>
            <td style="padding:4px 8px; font-size:9pt; font-weight:bold; text-align:right; white-space:nowrap; vertical-align:middle; border-right:1.5px solid #000; border-bottom:1px solid #000;">
                <strong>{{ number_format($netPayable > 0 ? $netPayable : $totalAmount, 2) }}</strong>
            </td>
            <td style="padding:4px 8px; font-size:9pt; vertical-align:middle; border-bottom:1px solid #000;">
                ภาษีมูลค่าเพิ่ม 7.00&nbsp;%
            </td>
            <td style="padding:4px 8px; font-size:9pt; text-align:right; white-space:nowrap; vertical-align:middle; border-right:1.5px solid #000; border-bottom:1px solid #000;">
                {{ number_format($vatAmount, 2) }}
            </td>
        </tr>
        <tr>
            <td colspan="2" style="padding:5px 10px; font-size:8.5pt; font-weight:bold; vertical-align:middle; border-left:1.5px solid #000; border-right:1.5px solid #000; border-bottom:1.5px solid #000;">
                &nbsp;
            </td>
            <td style="padding:5px 8px; font-size:9.5pt; font-weight:bold; text-align:left; vertical-align:middle; border-bottom:1.5px solid #000;">
                <strong>จำนวนเงินรวมทั้งสิ้น</strong>&nbsp;<span style="font-size:7.5pt; font-weight:normal;">Total Amount</span>
            </td>
            <td style="padding:5px 10px; font-size:11pt; font-weight:bold; text-align:right; white-space:nowrap; vertical-align:middle; border-right:1.5px solid #000; border-bottom:1.5px solid #000;">
                <strong>{{ number_format($totalAmount, 2) }}</strong>
            </td>
        </tr>
    </table>

    {{-- ─── S9: Payment Instructions ─── --}}
    @php
        $line2Bank = 'ธนาคาร' . $recipientBankName;
        if ($recipientBankBranch) $line2Bank .= ' สาขา' . $recipientBankBranch;
        if (!$isInvestorRoute)    $line2Bank .= ' กระแสรายวัน';
        $line2Bank .= ' เลขที่ ' . $recipientBankAccNum;
    @endphp
    <div style="margin-top:5mm; font-size:9pt; line-height:1.9;">
        1.&nbsp;โปรดสั่งจ่ายเช็คขีดคร่อมในนาม <strong>{{ $recipientBankAccName ?: $recipientName }}</strong> และขีด "หรือผู้ถือ" ออก<br>
        2.&nbsp;โอนเงินเข้าบัญชี {{ $line2Bank }}<br>
        3.&nbsp;ได้รับสินค้าและบริการดังรายการข้างบนนี้ไว้โดยถูกต้อง และครบถ้วน
    </div>

    {{-- ─── S10: Signatures ─── --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; margin-top:8mm;">
        <tr>
            <td width="50%">&nbsp;</td>
            <td width="50%" style="text-align:center; font-size:9pt; line-height:1.5; padding-bottom:2mm;">
                @if($isInvestorRoute)
                    ในนาม : <strong>{{ $invName }}</strong>
                @else
                    ในนาม : <strong>{{ $coNameTh }}</strong><br>
                    <span style="font-size:8pt;">For : {{ $coNameEn }}</span>
                @endif
            </td>
        </tr>
        <tr>
            <td width="50%" style="height:12mm; text-align:center; vertical-align:bottom; padding:0 15mm; font-size:9pt;">
                &nbsp;
            </td>
            <td width="50%" style="height:12mm; text-align:center; vertical-align:bottom; padding:0 15mm;">
                @if(!$isInvestorRoute && $coSignatureSrc)
                    <img src="{{ $coSignatureSrc }}" alt="ลายเซ็น" style="max-height:11mm; max-width:100%;">
                @endif
            </td>
        </tr>
        <tr>
            <td width="50%" style="padding:0 15mm 2mm;">
                <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                    <tr><td style="border-bottom:1px solid #000; padding:0; font-size:1pt; line-height:1;">&nbsp;</td></tr>
                </table>
            </td>
            <td width="50%" style="padding:0 15mm 2mm;">
                <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                    <tr><td style="border-bottom:1px solid #000; padding:0; font-size:1pt; line-height:1;">&nbsp;</td></tr>
                </table>
            </td>
        </tr>
        <tr>
            <td width="50%" style="text-align:center; font-size:9pt; padding:0 15mm;">ผู้จัดทำ</td>
            <td width="50%" style="text-align:center; font-size:9pt; padding:0 15mm;">ผู้มีอำนาจลงนาม</td>
        </tr>
        <tr>
            <td width="50%" style="text-align:center; font-size:9pt; padding:2mm 15mm 0;">{{ $invoiceDateDm }}</td>
            <td width="50%" style="text-align:center; font-size:9pt; padding:2mm 15mm 0;">{{ $invoiceDateDm }}</td>
        </tr>
        <tr>
            <td width="50%" style="text-align:center; font-size:9pt; padding:0 15mm;">วันที่</td>
            <td width="50%" style="text-align:center; font-size:9pt; padding:0 15mm;">วันที่</td>
        </tr>
    </table>

</div>{{-- .invoice-sheet --}}
</div>{{-- .page-wrapper --}}

</body>
</html>
