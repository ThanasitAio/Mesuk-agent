<?php

namespace App\Services;

use App\Models\HrInvoice;

/**
 * ยอดสุทธิจริงของใบแจ้งหนี้ (gross-up หัก ณ ที่จ่าย + VAT ตาม billing_route) - พอร์ตมาจาก happyest
 * InvoicePdfService::computeDisplayBreakdown()/computeDisplayBreakdownPreview() (ดู
 * C:\laragon\www\happyest\app\Services\InvoicePdfService.php) ตัดเฉพาะส่วนคำนวณตัวเลข ไม่พอร์ต
 * 'items'/'breakdown'/'notes' ที่เป็น text สำหรับพิมพ์ใบ เพราะฝั่ง agent ใช้แค่ net_payable/vat_amount
 * ป้อนเข้า HrPaymentRecord::resolveInvoiceMatch()/previewNetTotal() เพื่อให้หน้ารอบบิลของ agent
 * แสดงยอด/ผู้รับเงินตรงกับใบแจ้งหนี้จริงในระบบ admin แทนที่จะคำนวณสดจาก Property/Booking config
 * ที่อาจเปลี่ยนไปแล้วหลังออกใบ
 */
class InvoiceBreakdownService
{
    public static function computeDisplayBreakdown(HrInvoice $invoice): array
    {
        // ค่าปรับล่าช้า: ยอดเดียวคงที่ เข้าบริษัท 100% เสมอ ไม่มี VAT/หัก ณ ที่จ่าย
        if ($invoice->invoice_type === 'late_fee') {
            $amount = round((float) $invoice->amount, 2);

            return [
                'billing_route' => $invoice->billing_route ?? 'company',
                'wht_amount' => 0.0,
                'gross_total' => $amount,
                'net_payable' => $amount,
                'vat_amount' => 0.0,
            ];
        }

        $snapProp = $invoice->snapshot_property ?? [];
        $snapBk = $invoice->snapshot_booking ?? [];
        $billingItems = $invoice->billing_items ?? [];

        $billingMonthShort = null;
        if ($invoice->billing_month) {
            [$bYr, $bMo] = explode('-', $invoice->billing_month);
            $billingMonthShort = 'ด.'.(int) $bMo.'/'.((int) $bYr + 543 - 2500);
        }

        $landTaxToInvestor = array_key_exists('land_tax_to_investor', $snapProp)
            ? (bool) $snapProp['land_tax_to_investor']
            : (bool) ($invoice->booking?->land_tax_to_investor ?? $invoice->property?->land_tax_to_investor ?? false);
        $sideAreaRoute = $landTaxToInvestor ? 'investor' : 'company';

        // เติมค่าเช่าพื้นที่ด้านข้างอัตโนมัติเฉพาะใบค่าเช่ารวม (invoice_sub_type=null) ของ property ที่ไม่ใช้
        // separate_invoice_file เท่านั้น - เหมือน happyest ทุกประการ (ดูคอมเมนต์ต้นฉบับ)
        if ($invoice->invoice_type === 'monthly_rent' && $invoice->invoice_sub_type === null && $invoice->billing_route === $sideAreaRoute) {
            $snapSideAreaRent = array_key_exists('side_area_rent_per_month', $snapProp)
                ? (float) $snapProp['side_area_rent_per_month']
                : (float) ($invoice->property?->side_area_rent_per_month ?? 0);
            if ($snapSideAreaRent > 0) {
                $hasSideAreaInItems = (bool) array_filter($billingItems, fn ($i) => str_contains($i['label'] ?? '', 'พื้นที่ด้านข้าง'));
                if ($invoice->invoice_sub_type === 'side_area') {
                    $hasDedicatedSideAreaInvoice = false;
                } elseif ($invoice->relationLoaded('booking') && $invoice->booking?->relationLoaded('invoices')) {
                    $hasDedicatedSideAreaInvoice = $invoice->booking->invoices
                        ->contains(fn ($inv) => $inv->billing_month === $invoice->billing_month
                            && $inv->invoice_sub_type === 'side_area'
                            && ! in_array($inv->status, ['cancelled', 'voided']));
                } else {
                    $hasDedicatedSideAreaInvoice = HrInvoice::where('booking_id', $invoice->booking_id)
                        ->where('billing_month', $invoice->billing_month)
                        ->where('invoice_sub_type', 'side_area')
                        ->whereNotIn('status', ['cancelled', 'voided'])
                        ->exists();
                }
                if (! $hasSideAreaInItems && ! $hasDedicatedSideAreaInvoice) {
                    $sideAreaLabel = 'ค่าเช่าพื้นที่ด้านข้าง'.($billingMonthShort ? ' '.$billingMonthShort : '');
                    $billingItems[] = ['label' => $sideAreaLabel, 'amount' => $snapSideAreaRent];
                }
            }
        }

        $whtRateRaw = (float) ($snapBk['withholding_tax_rate'] ?? $invoice->booking?->withholding_tax_rate ?? 0);
        $isJuristic = ($snapBk['renter_type'] ?? '') === 'juristic';
        $showWhtRent = array_key_exists('show_withholding_tax_rent', $snapProp)
            ? (bool) $snapProp['show_withholding_tax_rent']
            : (bool) ($invoice->property?->show_withholding_tax_rent ?? false);
        $showWhtLand = array_key_exists('show_withholding_tax_land', $snapProp)
            ? (bool) $snapProp['show_withholding_tax_land']
            : (bool) ($invoice->property?->show_withholding_tax_land ?? false);
        $showWhtSideArea = array_key_exists('show_withholding_tax_side_area', $snapProp)
            ? (bool) $snapProp['show_withholding_tax_side_area']
            : (bool) ($invoice->property?->show_withholding_tax_side_area ?? false);
        $whtRate = ($isJuristic && $showWhtRent) ? $whtRateRaw : 0.0;
        $whtRateLand = ($isJuristic && $showWhtLand) ? $whtRateRaw : 0.0;
        $whtRateSideArea = ($isJuristic && $showWhtSideArea) ? $whtRateRaw : 0.0;
        $itemRentHasVat = array_key_exists('rent_has_vat', $snapProp)
            ? (bool) $snapProp['rent_has_vat']
            : (bool) ($invoice->property?->rent_has_vat ?? false);
        $itemLandTaxHasVat = array_key_exists('land_tax_has_vat', $snapProp)
            ? (bool) $snapProp['land_tax_has_vat']
            : (bool) ($invoice->property?->land_tax_has_vat ?? false);
        $grossFactor = ($isJuristic && $whtRateRaw > 0) ? (100 / (100 - $whtRateRaw)) : 1.0;
        // มัดจำ/ค่าดำเนินการ ไม่ต้องหัก ณ ที่จ่าย ทุกกรณี
        if (in_array($invoice->invoice_type, ['deposit', 'service_fee'])) {
            $whtRate = $whtRateLand = $whtRateSideArea = 0.0;
            $grossFactor = 1.0;
        }

        $displayItems = array_values(array_filter(
            $billingItems,
            fn ($i) => ! (((float) ($i['amount'] ?? 0)) < 0 && str_contains($i['label'] ?? '', 'หัก ณ ที่จ่าย'))
        ));

        $grossBaseAmount = 0.0;
        $whtAmount = 0.0;
        $vatAmount = 0.0;

        if (count($displayItems) > 0) {
            foreach ($displayItems as $bi) {
                $amt = (float) ($bi['amount'] ?? 0);
                if ($amt <= 0) {
                    continue;
                }
                $isLandTax = str_contains($bi['label'] ?? '', 'ภาษีที่ดิน');
                $isSideArea = ! $isLandTax && str_contains($bi['label'] ?? '', 'พื้นที่ด้านข้าง');
                // อากรแสตมป์ไม่ต้องหัก ณ ที่จ่าย/ไม่มี VAT ทุกกรณี ไม่ gross-up
                $isStampDuty = ! $isLandTax && ! $isSideArea && str_contains($bi['label'] ?? '', 'อากรแสตมป์');
                $gross = $isStampDuty ? $amt : round($amt * $grossFactor, 2);
                $itemVatRate = $isStampDuty ? 0.0 : ($isLandTax ? ($itemLandTaxHasVat ? 7.0 : 0.0) : ($itemRentHasVat ? 7.0 : 0.0));
                $itemVat = $itemVatRate > 0 ? round($gross * $itemVatRate / 100, 2) : 0.0;
                $rate = $isLandTax ? $whtRateLand : ($isSideArea ? $whtRateSideArea : ($isStampDuty ? 0.0 : $whtRate));
                $itemWht = $rate > 0 ? round($gross * $rate / 100, 2) : 0.0;
                $grossBaseAmount += $gross;
                $vatAmount += $itemVat;
                $whtAmount += $itemWht;
            }
        } else {
            $gross = round((float) $invoice->amount * $grossFactor, 2);
            $itemVatRate = $itemRentHasVat ? 7.0 : 0.0;
            $vatAmount = $itemVatRate > 0 ? round($gross * $itemVatRate / 100, 2) : 0.0;
            $whtAmount = $whtRate > 0 ? round($gross * $whtRate / 100, 2) : 0.0;
            $grossBaseAmount = $gross;
        }

        $grossBaseAmount = round($grossBaseAmount, 2);
        $whtAmount = round($whtAmount, 2);
        // net = gross - หัก ณ ที่จ่าย, ส่วน VAT ของยอดใบจริงใช้ tax_amount ที่บันทึกไว้ตรงๆ (ไม่ใช่ผลรวม
        // itemVat ด้านบนซึ่งมีไว้แค่คำนวณ preview เพราะใบจริงอาจมี VAT ที่ถูกปัดยอดรวมต่างจาก per-item เล็กน้อย)
        $netPayable = round($grossBaseAmount - $whtAmount, 2);

        return [
            'billing_route' => $invoice->billing_route,
            'wht_amount' => $whtAmount,
            'gross_total' => $grossBaseAmount,
            'net_payable' => $netPayable,
            'vat_amount' => (float) $invoice->tax_amount,
        ];
    }

    /**
     * พรีวิวยอดสุทธิของ monthly_rent record ที่ยังไม่มีใบแจ้งหนี้จริงผูกอยู่ - สูตรเดียวกับ
     * computeDisplayBreakdown() ด้านบนแต่รับค่าดิบจาก Booking/Property ตรงๆ แทน Invoice+snapshot
     *
     * @param  array  $billingItems  [['label' => string, 'amount' => float], ...]
     */
    public static function computeDisplayBreakdownPreview(
        array $billingItems,
        string $invoiceType,
        string $renterType,
        float $whtRateRaw,
        bool $showWhtRent,
        bool $showWhtLand,
        bool $showWhtSideArea,
        bool $rentHasVat,
        bool $landTaxHasVat,
    ): array {
        $isJuristic = $renterType === 'juristic';
        $whtRate = ($isJuristic && $showWhtRent) ? $whtRateRaw : 0.0;
        $whtRateLand = ($isJuristic && $showWhtLand) ? $whtRateRaw : 0.0;
        $whtRateSideArea = ($isJuristic && $showWhtSideArea) ? $whtRateRaw : 0.0;
        $grossFactor = ($isJuristic && $whtRateRaw > 0) ? (100 / (100 - $whtRateRaw)) : 1.0;
        if (in_array($invoiceType, ['deposit', 'service_fee'])) {
            $whtRate = $whtRateLand = $whtRateSideArea = 0.0;
            $grossFactor = 1.0;
        }

        $displayItems = array_values(array_filter(
            $billingItems,
            fn ($i) => ! (((float) ($i['amount'] ?? 0)) < 0 && str_contains($i['label'] ?? '', 'หัก ณ ที่จ่าย'))
        ));

        $grossBaseAmount = 0.0;
        $whtAmount = 0.0;
        $vatAmount = 0.0;

        foreach ($displayItems as $bi) {
            $amt = (float) ($bi['amount'] ?? 0);
            if ($amt <= 0) {
                continue;
            }
            $isLandTax = str_contains($bi['label'] ?? '', 'ภาษีที่ดิน');
            $isSideArea = ! $isLandTax && str_contains($bi['label'] ?? '', 'พื้นที่ด้านข้าง');
            $isStampDuty = ! $isLandTax && ! $isSideArea && str_contains($bi['label'] ?? '', 'อากรแสตมป์');
            $gross = $isStampDuty ? $amt : round($amt * $grossFactor, 2);
            $itemVatRate = $isStampDuty ? 0.0 : ($isLandTax ? ($landTaxHasVat ? 7.0 : 0.0) : ($rentHasVat ? 7.0 : 0.0));
            $itemVat = $itemVatRate > 0 ? round($gross * $itemVatRate / 100, 2) : 0.0;
            $rate = $isLandTax ? $whtRateLand : ($isSideArea ? $whtRateSideArea : ($isStampDuty ? 0.0 : $whtRate));
            $itemWht = $rate > 0 ? round($gross * $rate / 100, 2) : 0.0;
            $grossBaseAmount += $gross;
            $vatAmount += $itemVat;
            $whtAmount += $itemWht;
        }

        $grossBaseAmount = round($grossBaseAmount, 2);
        $whtAmount = round($whtAmount, 2);
        $vatAmount = round($vatAmount, 2);
        $netPayable = round($grossBaseAmount - $whtAmount, 2);

        return [
            'net_payable' => $netPayable,
            'vat_amount' => $vatAmount,
        ];
    }
}
