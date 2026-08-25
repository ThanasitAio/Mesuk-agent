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
 * "ดาวน์โหลด" สร้างไฟล์ PDF จริงที่ server ด้วย mPDF (composer require mpdf/mpdf เพิ่มเข้ามาสำหรับ
 * ฟีเจอร์นี้โดยเฉพาะ - ดู download()/renderPdf()) แล้วส่งกลับพร้อม Content-Disposition: attachment
 * เหมือน happyest InvestorInvoiceController::download() ทุกประการ (คนละ endpoint กับ invoices.print
 * เดิมที่ยังเป็นหน้าพิมพ์ HTML แบบ print-to-PDF สำหรับใช้งานจุดอื่นในแอปนี้อยู่ - ไม่แตะ)
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
     * ใช้ร่วมกันทั้ง show() (แบ่งหน้า) และ bulkList() (เลือกทั้งหมดข้ามหน้า) เพื่อให้ผลลัพธ์ตรงกันเป๊ะ -
     * กรองสถานะการชำระที่ระดับ SQL ไม่ได้เพราะมาจาก HrInvoice::paymentSummary() (จับคู่
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
            'booking.paymentRecords:id,booking_id,payment_type,deposit_phase,payment_status,due_date,paid_at,payment_slip_batches,verified_at,verified_by',
            'booking.paymentRecords.verifiedBy:id,name',
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
                'download_url' => route('tenant-invoices.download', $invoice->id),
            ])
            ->values();

        return response()->json(['ok' => true, 'month' => $month, 'items' => $items]);
    }

    /**
     * ดาวน์โหลดใบแจ้งหนี้ผู้เช่า 1 ใบเป็นไฟล์ PDF จริง (Content-Disposition: attachment) - ใช้ mPDF
     * (ไม่ใช่ DomPDF) เพราะ mPDF รองรับ OpenType Layout (GSUB/GPOS) ที่จำเป็นสำหรับวรรณยุกต์/สระไทยซ้อน
     * ถูกต้อง ตรงกับที่ happyest InvoicePdfService::getPdfContent() เลือกใช้จริงใน production (ดูคอมเมนต์
     * ในไฟล์นั้น: "Both company and investor invoices → mPDF (proper Thai OTL shaping)") - หน้ารายการ
     * (tenant-invoices/index.blade.php) เรียก route นี้ทีละใบทั้งตอนกดดาวน์โหลดรายการเดียวและตอนดาวน์โหลด
     * หลายใบพร้อมกัน (fetch+blob วนทีละไฟล์ เหมือนฝั่ง happyest investor.invoices)
     */
    public function download(HrInvoice $invoice)
    {
        $agentCode = session('agent_code');

        if ($invoice->property?->manager_agent_code !== $agentCode) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงใบแจ้งหนี้นี้');
        }
        abort_if($invoice->status !== 'approved', 403, 'ใบแจ้งหนี้นี้ยังไม่ได้รับการอนุมัติ');

        $company = HrCompany::getActive();
        $happyestPublic = rtrim(env('HAPPYEST_APP_URL', 'http://127.0.0.1/happyest/public'), '/');

        $content = $this->renderPdf($invoice, $company, $happyestPublic);

        logSystem(
            userType: 'agent',
            userId: session('agent_id'),
            module: 'TenantInvoice',
            action: 'VIEW',
            description: "ดาวน์โหลดใบแจ้งหนี้ผู้เช่า {$invoice->invoice_code}"
        );

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $this->buildContentDisposition($invoice),
            'Content-Length' => strlen($content),
        ]);
    }

    private function renderPdf(HrInvoice $invoice, HrCompany $company, string $happyestPublic): string
    {
        $html = view('invoices.partials.pdf-wrapper', compact('invoice', 'company', 'happyestPublic'))->render();

        $tempDir = storage_path('app/mpdf');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $defaultConfig = (new \Mpdf\Config\ConfigVariables)->getDefaults();
        $defaultFontConfig = (new \Mpdf\Config\FontVariables)->getDefaults();

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_top' => 10,
            'margin_right' => 14,
            'margin_bottom' => 10,
            'margin_left' => 14,
            'fontDir' => array_merge($defaultConfig['fontDir'], [storage_path('fonts')]),
            'fontdata' => $defaultFontConfig['fontdata'] + [
                'sarabun' => [
                    'R' => 'Sarabun-Regular.ttf',
                    'B' => 'Sarabun-Bold.ttf',
                    'useOTL' => 0xFF,
                ],
            ],
            'default_font' => 'sarabun',
            'default_font_size' => 9,
            'tempDir' => $tempDir,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);

        // ตัด UTF-8 BOM ถ้ามี (ทำให้ mPDF โยน "invalid UTF-8" exception ได้) - เหมือนฝั่ง happyest
        $mpdf->WriteHTML(ltrim($html, "\xEF\xBB\xBF"));
        $mpdf->SetTitle($this->buildDisplayName($invoice));

        return $mpdf->OutputBinaryData();
    }

    /**
     * ชื่อไฟล์ (ไม่รวม .pdf) - พอร์ตมาจาก happyest InvoicePdfService::buildDisplayName() เพื่อให้ชื่อไฟล์
     * ที่ผู้บริหารโครงการเห็นตรงกับที่เจ้าของทรัพย์เห็นจากหน้า investor.invoices รูปแบบเดียวกัน
     */
    private function buildDisplayName(HrInvoice $invoice): string
    {
        $propCode = $invoice->snapshot_property['property_code'] ?? $invoice->property?->property_code ?? '';
        $custName = $invoice->snapshot_customer['name'] ?? $invoice->customer?->full_name ?? '';

        $typeLabel = match (true) {
            ($invoice->invoice_sub_type ?? null) === 'land_tax' => 'ภาษีที่ดิน',
            ($invoice->invoice_sub_type ?? null) === 'rent' => 'ค่าเช่า',
            ($invoice->invoice_sub_type ?? null) === 'stamp_duty' => 'อากรแสตมป์',
            ($invoice->invoice_sub_type ?? null) === 'side_area' => 'ค่าเช่าพื้นที่ด้านข้าง',
            $invoice->invoice_type === 'utility' => 'ค่าน้ำไฟ',
            $invoice->invoice_type === 'deposit' => 'ค่ามัดจำ',
            $invoice->invoice_type === 'service_fee' => 'ค่าดำเนินการ',
            $invoice->invoice_type === 'monthly_rent' => 'ค่าเช่า',
            default => $invoice->detailed_type_label ?: 'ใบแจ้งหนี้',
        };

        // billing_month_short() ของ happyest คืน "ด.8/69" - "/" เป็น path separator จริง เขียนเป็นชื่อไฟล์
        // ตรงๆ ไม่ได้ แทนด้วย "-" ธรรมดา
        $monthPart = '';
        if ($invoice->billing_month) {
            [$byr, $bmo] = explode('-', $invoice->billing_month);
            $monthPart = ' ด.'.ltrim($bmo, '0').'-'.(((int) $byr + 543) - 2500);
        }

        $name = trim("{$propCode}({$typeLabel})ใบแจ้งหนี้ {$custName}{$monthPart}");

        // กัน HTTP header injection (CR/LF) + อักขระต้องห้ามของชื่อไฟล์ Windows/Unix
        $name = trim(preg_replace('/[\/\\\\:*?"<>|\r\n]+/u', '-', $name) ?? $name);

        return $name !== '' ? $name : ($invoice->invoice_code ?: 'invoice');
    }

    /**
     * RFC 5987/6266 Content-Disposition: filename* (UTF-8 percent-encoded ชื่อไทยจริง) เป็นตัวหลักที่
     * เบราว์เซอร์สมัยใหม่ทุกตัวอ่าน, filename="" (ASCII-only invoice-{code}.pdf) เป็น fallback -
     * เหมือน happyest InvestorInvoiceController::buildContentDisposition() ทุกประการ
     */
    private function buildContentDisposition(HrInvoice $invoice): string
    {
        $filename = $this->buildDisplayName($invoice).'.pdf';
        $asciiFallback = "invoice-{$invoice->invoice_code}.pdf";

        return sprintf(
            'attachment; filename="%s"; filename*=UTF-8\'\'%s',
            $asciiFallback,
            rawurlencode($filename)
        );
    }
}
