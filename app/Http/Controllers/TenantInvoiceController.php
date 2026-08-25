<?php

namespace App\Http\Controllers;

use App\Models\HrCompany;
use App\Models\HrInvoice;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * ใบแจ้งหนี้ผู้เช่า ("ใบแจ้งหนี้ผู้เช่า") ฝั่งผู้บริหารโครงการ - ดูและดาวน์โหลดใบแจ้งหนี้ที่ออกให้
 * ผู้เช่าของทรัพย์ที่ตัวเองดูแล (manager_agent_code) เท่านั้น ไม่มีสิทธิ์แก้ไข/อนุมัติ (ควบคุมที่แอดมิน
 * เท่านั้น) - พอร์ตหลักการทำงานมาจาก happyest InvestorInvoiceController (ดูและดาวน์โหลดทั้งหมดของ
 * เจ้าของทรัพย์) แต่ขอบเขตข้อมูลเป็น "ทรัพย์ที่ตัวเองบริหาร" แทน "ทรัพย์ที่ตัวเองเป็นเจ้าของ"
 *
 * "ดาวน์โหลด" ในโปรเจกต์นี้คือหน้าพิมพ์ HTML (invoices.print - ดู PropertyBillingController::printInvoice)
 * ให้เบราว์เซอร์ print-to-PDF เอง เพราะไม่มี PDF library (mPDF/DomPDF) ติดตั้งอยู่ (CLAUDE.md ห้าม
 * composer require สำหรับ production) - "ดาวน์โหลดทั้งหมด/ที่เลือก" จึงรวมหลายใบไว้ในเอกสารเดียว
 * (printBulk) แล้วให้กด print ครั้งเดียวได้ PDF เดียวที่มีทุกใบ แทนการดาวน์โหลดไฟล์แยกทีละไฟล์แบบฝั่ง
 * happyest ที่มี InvoicePdfService สร้างไฟล์ PDF จริงอยู่แล้ว
 */
class TenantInvoiceController extends Controller
{
    private function ownedInvoiceQuery(string $agentCode)
    {
        return HrInvoice::approved()
            ->whereHas('property', fn ($q) => $q->where('manager_agent_code', $agentCode));
    }

    /**
     * โฟลเดอร์แยกตาม "วันที่เปิดใบแจ้งหนี้" (issued_date) ไม่ใช่ billing_month (งวดที่เรียกเก็บ)
     * ใบเก่าก่อนมีคอลัมน์นี้เป็น null จึง fallback ไปที่ created_at - เหมือนฝั่ง happyest ทุกประการ
     */
    private function issuedMonthExpr(): string
    {
        return "DATE_FORMAT(COALESCE(issued_date, created_at), '%Y-%m')";
    }

    private function resolveMonth(Request $request): string
    {
        $month = $request->get('month');

        return preg_match('/^\d{4}-\d{2}$/', (string) $month) ? $month : now()->format('Y-m');
    }

    private function monthLabel(string $ym): string
    {
        $thaiMonths = [
            1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม',
            4 => 'เมษายน', 5 => 'พฤษภาคม', 6 => 'มิถุนายน',
            7 => 'กรกฎาคม', 8 => 'สิงหาคม', 9 => 'กันยายน',
            10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม',
        ];
        [$year, $month] = explode('-', $ym);

        return $thaiMonths[(int) $month].' '.((int) $year + 543);
    }

    /**
     * หน้าแรกแสดงเป็น "โฟลเดอร์" ตามเดือนที่มีใบแจ้งหนี้จริงเท่านั้น (เหมือน Google Drive) กดเข้าไปดู
     * รายการของเดือนนั้นใน show() - แนวคิดเดียวกับ happyest investor.invoices.index
     */
    public function index()
    {
        $agentCode = session('agent_code');

        $months = $this->ownedInvoiceQuery($agentCode)
            ->selectRaw($this->issuedMonthExpr().' as issue_month, COUNT(*) as invoice_count')
            ->groupBy('issue_month')
            ->orderByDesc('issue_month')
            ->get()
            ->map(fn ($row) => [
                'month' => $row->issue_month,
                'label' => $this->monthLabel($row->issue_month),
                'invoice_count' => $row->invoice_count,
            ]);

        logSystem(
            userType: 'agent',
            userId: session('agent_id'),
            module: 'TenantInvoice',
            action: 'VIEW',
            description: 'ดูรายการโฟลเดอร์ใบแจ้งหนี้ผู้เช่า'
        );

        return view('tenant-invoices.folders', compact('months'));
    }

    /**
     * ใบแจ้งหนี้ทั้งหมดของผู้บริหารโครงการในเดือนนี้ที่ตรงกับคำค้นหา พร้อม pay_summary - แคชไว้ในตัว
     * ใช้ร่วมกันทั้ง show() (แบ่งหน้า) และ bulkList()/printBulk() (เลือกทั้งหมดข้ามหน้า) เพื่อให้ผลลัพธ์
     * ตรงกันเป๊ะ - กรองสถานะการชำระที่ระดับ SQL ไม่ได้เพราะมาจาก HrInvoice::paymentSummary() (จับคู่
     * hr_payment_records ผ่าน booking ไม่ใช่คอลัมน์ตรงๆ) จึงดึงมาทั้งเดือนแล้วกรองใน PHP แทน
     */
    private function matchingInvoices(string $agentCode, string $month, string $search)
    {
        $query = $this->ownedInvoiceQuery($agentCode)->whereRaw($this->issuedMonthExpr().' = ?', [$month]);

        if ($search !== '') {
            $query->where(function ($outer) use ($search) {
                $outer->where('invoice_code', 'like', "%{$search}%")
                    ->orWhereHas('property', fn ($q) => $q->where('property_code', 'like', "%{$search}%"))
                    ->orWhereHas('customer', function ($q) use ($search) {
                        $q->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        $all = $query->with([
            'property:id,property_code,title',
            'booking:id,booking_code',
            'booking.paymentRecords:id,booking_id,payment_type,deposit_phase,payment_status,due_date,paid_at,payment_slip_batches',
            'customer:id,first_name,last_name,company_name',
        ])
            ->orderByRaw('COALESCE(issued_date, created_at) DESC')
            ->get();

        $all->each(fn (HrInvoice $invoice) => $invoice->setAttribute('pay_summary', $invoice->paymentSummary()));

        return $all;
    }

    private function filterByPayStatus($invoices, string $payFilter)
    {
        return (match ($payFilter) {
            'paid' => $invoices->filter(fn ($i) => $i->pay_summary['status'] === 'paid'),
            'unpaid' => $invoices->filter(fn ($i) => $i->pay_summary['status'] === null),
            default => $invoices,
        })->values();
    }

    public function show(Request $request, string $month)
    {
        abort_unless(preg_match('/^\d{4}-\d{2}$/', $month), 404);

        $agentCode = session('agent_code');
        $search = trim((string) $request->get('q'));
        $payFilter = in_array($request->get('pay_status'), ['paid', 'unpaid'], true) ? $request->get('pay_status') : 'all';

        $all = $this->matchingInvoices($agentCode, $month, $search);

        $payCounts = [
            'all' => $all->count(),
            'paid' => $all->filter(fn ($i) => $i->pay_summary['status'] === 'paid')->count(),
            'unpaid' => $all->filter(fn ($i) => $i->pay_summary['status'] === null)->count(),
        ];

        $filtered = $this->filterByPayStatus($all, $payFilter);

        $perPage = 15;
        $currentPage = max(1, (int) $request->get('page', 1));
        $invoices = new LengthAwarePaginator(
            $filtered->slice(($currentPage - 1) * $perPage, $perPage)->values(),
            $filtered->count(),
            $perPage,
            $currentPage,
            ['path' => route('tenant-invoices.show', $month), 'query' => $request->except('page')]
        );

        $monthLabel = $this->monthLabel($month);

        logSystem(
            userType: 'agent',
            userId: session('agent_id'),
            module: 'TenantInvoice',
            action: 'VIEW',
            description: "ดูรายการใบแจ้งหนี้ผู้เช่า งวด {$monthLabel}"
        );

        return view('tenant-invoices.index', [
            'invoices' => $invoices, 'month' => $month, 'monthLabel' => $monthLabel,
            'search' => $search, 'payFilter' => $payFilter, 'payCounts' => $payCounts,
        ]);
    }

    /**
     * รายการ id/รหัส/ลิงก์ดาวน์โหลดของใบแจ้งหนี้ทั้งหมดที่ตรงกับตัวกรองปัจจุบัน (q + pay_status) - ใช้ตอน
     * ติ๊ก "เลือกทั้งหมด" (ต้องเลือกครบทุกหน้า ไม่ใช่แค่หน้าที่เห็น) ต้องรับ q/pay_status แบบเดียวกับ
     * show() เพื่อให้ผลลัพธ์ตรงกับสิ่งที่ผู้ใช้กำลังกรองอยู่จริง
     */
    public function bulkList(Request $request)
    {
        $agentCode = session('agent_code');
        $month = $this->resolveMonth($request);
        $search = trim((string) $request->get('q'));
        $payFilter = in_array($request->get('pay_status'), ['paid', 'unpaid'], true) ? $request->get('pay_status') : 'all';

        $all = $this->matchingInvoices($agentCode, $month, $search);
        $filtered = $this->filterByPayStatus($all, $payFilter);

        $items = $filtered->sortBy('id')->values()
            ->map(fn (HrInvoice $invoice) => [
                'invoice_id' => $invoice->id,
                'invoice_code' => $invoice->invoice_code,
                'property_code' => $invoice->snapshot_property['property_code'] ?? '',
                'customer_name' => $invoice->snapshot_customer['name'] ?? '',
            ])
            ->values();

        return response()->json(['ok' => true, 'month' => $month, 'items' => $items]);
    }

    /**
     * รวมหลายใบแจ้งหนี้ (ที่อยู่ในขอบเขตทรัพย์ที่ตัวเองดูแลเท่านั้น - กรองซ้ำที่นี่แม้ id จะมาจาก
     * bulkList()/show() ของตัวเองอยู่แล้วก็ตาม กัน id ที่ผู้ใช้แก้ query string เอง) เป็นเอกสารพิมพ์เดียว
     */
    public function printBulk(Request $request)
    {
        $agentCode = session('agent_code');

        $ids = array_filter(array_map('intval', explode(',', (string) $request->get('ids'))));
        abort_if(empty($ids), 404, 'ไม่พบรายการใบแจ้งหนี้ที่เลือก');

        $invoices = $this->ownedInvoiceQuery($agentCode)
            ->whereIn('id', $ids)
            ->orderByRaw('COALESCE(issued_date, created_at) ASC')
            ->get();

        abort_if($invoices->isEmpty(), 404, 'ไม่พบรายการใบแจ้งหนี้ที่เลือก');

        $company = HrCompany::getActive();
        $happyestPublic = rtrim(env('HAPPYEST_APP_URL', 'http://127.0.0.1/happyest/public'), '/');

        logSystem(
            userType: 'agent',
            userId: session('agent_id'),
            module: 'TenantInvoice',
            action: 'VIEW',
            description: 'พิมพ์ใบแจ้งหนี้ผู้เช่าแบบรวม '.$invoices->count().' ใบ'
        );

        return view('tenant-invoices.print-bulk', compact('invoices', 'company', 'happyestPublic'));
    }
}
