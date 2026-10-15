<?php

namespace App\Http\Controllers;

use App\Models\HrAdmin;
use App\Models\HrBooking;
use App\Models\HrInvoice;
use App\Models\HrProperty;
use App\Models\HrPropertyMeter;
use App\Models\HrPropertyStatus;
use App\Models\MeterReading;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * บันทึกมิเตอร์น้ำ/ไฟ (ผู้บริหารโครงการ) - เงื่อนไข การแสดงผล และข้อความแจ้งเตือนต้องตรงกับหน้าแอดมิน
 * happyest AdminMeterReadingController (/admin/meter-readings) และคู่มือ "จดมิเตอร์น้ำ/ไฟ ทีละทรัพย์จนครบ"
 * (/admin/help/staff-meter-readings) ต่างแค่ขอบเขต: ตัวแทนเห็นเฉพาะทรัพย์ที่ตัวเองเป็นผู้บริหารโครงการ
 */
class MeterReadingController extends Controller
{
    /** สถานะงวดต่อทรัพย์ (ลำดับ = ลำดับแท็บ) - ดู periodStatuses() */
    private const PERIOD_STATUSES = ['unrecorded', 'partial', 'draft', 'recorded', 'invoiced'];

    /** สถานะที่ยังต้องจด/ยืนยัน - ปุ่ม "เริ่มจดมิเตอร์" และ "บันทึก แล้วไปทรัพย์ถัดไป" ข้ามทรัพย์ที่เสร็จแล้ว */
    private const NEEDS_WORK_STATUSES = ['unrecorded', 'partial', 'draft'];

    /** ตัวกรองของหน้ารายการที่พกไปหน้าบันทึกด้วย (ลำดับก่อนหน้า/ถัดไป ใช้ชุดเดียวกับรายการที่เห็น) */
    private const SCOPE_KEYS = ['search', 'property_status_id'];

    /** ป้ายบันทึกล่าสุด: log ของแอดมินต้องอยู่ภายในกี่วินาทีหลัง updated_at (เหมือน MeterReadingRecorderService ของ happyest) */
    private const RECORDER_LOG_WINDOW_SECONDS = 60;

    private const EXPORT_MAX_MONTHS = 24;

    private const THAI_MONTHS = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม',
    ];

    public function index(Request $request)
    {
        $agentCode = (string) session('agent_code');

        $year   = (int) $request->query('year', now()->year);
        $month  = (int) $request->query('month', now()->month);
        $status = (string) $request->query('status', 'all');
        if ($status !== 'all' && ! in_array($status, self::PERIOD_STATUSES, true)) {
            $status = 'all';
        }
        $scope = $this->scopeFrom($request);

        // ทรัพย์ทุกหลังที่มีมิเตอร์เปิดใช้งาน (รวมที่ปิดระบบมิเตอร์ไว้ - แสดง "ปิดใช้งานมิเตอร์" แทนปุ่ม เหมือนแอดมิน)
        // คำนวณจากชุดที่กรองแค่คำค้นก่อน เพื่อนับจำนวนบนปุ่มสถานะพื้นที่ได้ แล้วค่อยกรองสถานะพื้นที่ทีหลัง
        $searchOnlyById    = $this->scopedProperties($agentCode, array_diff_key($scope, ['property_status_id' => true]));
        $propertyStatusIds = HrProperty::whereIn('id', $searchOnlyById->keys())->pluck('property_status_id', 'id');
        $statusFilter      = (string) ($scope['property_status_id'] ?? '');
        $enabledById       = $statusFilter === ''
            ? $searchOnlyById
            : $searchOnlyById->filter(fn ($enabled, $id) => (string) ($propertyStatusIds[$id] ?? '') === $statusFilter);
        $propertyIds = $enabledById->keys();

        [
            'meterCounts'         => $meterCounts,
            'readingsByProperty'  => $readingsByProperty,
            'invoicedPropertyIds' => $invoicedPropertyIds,
            'status'              => $statusByPropertyId,
        ] = $this->periodStatuses($searchOnlyById->keys(), $year, $month);

        $counts = ['all' => $propertyIds->count()];
        foreach (self::PERIOD_STATUSES as $key) {
            $counts[$key] = $propertyIds->filter(fn ($id) => $statusByPropertyId[$id] === $key)->count();
        }

        // จำนวนบนปุ่มสถานะพื้นที่: นับตามคำค้น + แท็บสถานะงวดที่เลือกอยู่ (ไม่รวมตัวกรองสถานะพื้นที่เอง)
        $propertyStatusCounts = $searchOnlyById->keys()
            ->filter(fn ($id) => $status === 'all' || $statusByPropertyId[$id] === $status)
            ->countBy(fn ($id) => (string) ($propertyStatusIds[$id] ?? ''));

        // ปุ่ม "เริ่มจดมิเตอร์" = ทรัพย์แรก (ตามลำดับรายการ/ตัวกรองเดียวกัน) ที่ยังต้องจด/ยืนยัน แล้วใช้ "บันทึก แล้วไปทรัพย์ถัดไป" ต่อ
        // ทรัพย์ที่ปิดระบบมิเตอร์เปิดหน้าบันทึกไม่ได้ จึงไม่นับ
        $workIds          = $propertyIds->filter(fn ($id) => $enabledById[$id] && in_array($statusByPropertyId[$id] ?? null, self::NEEDS_WORK_STATUSES, true))->values();
        $needsWorkCount   = $workIds->count();
        $firstNeedsWorkId = $workIds->first();

        $filteredIds = $status === 'all'
            ? $propertyIds
            : $propertyIds->filter(fn ($id) => $statusByPropertyId[$id] === $status)->values();

        // แบ่งหน้าเองบนรายการ id ที่กรองแล้ว - status ไม่ใช่คอลัมน์จริง คำนวณจากหลายตาราง (เหมือนแอดมิน)
        $perPage     = 15;
        $currentPage = max(1, (int) $request->query('page', 1));
        $pagedIds    = $filteredIds->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $properties = HrProperty::whereIn('id', $pagedIds)
            ->with('propertyStatus:id,name,slug,color')
            ->get(['id', 'title', 'property_code', 'property_status_id', 'is_utility_metering_enabled'])
            ->sortBy(fn ($p) => $pagedIds->search($p->id))
            ->values();

        // เลขที่การจอง = การจองที่ใบแจ้งหนี้น้ำ/ไฟของงวดนี้เรียกเก็บ ให้ตรงกับหน้าแอดมิน: ออกใบแล้ว -> การจองของใบนั้น,
        // ยังไม่ออก -> กติกาเดียวกับ UtilityInvoiceBillingRowService ของ happyest (billingBookingsForPeriod())
        // เลขที่ใบแจ้งหนี้ใต้สถานะมาจากใบชุดเดียวกัน (1 ทรัพย์อาจมีหลายใบ เช่น แยกบริษัท/เจ้าของทรัพย์)
        $periodInvoices = HrInvoice::where('invoice_type', 'utility')
            ->where('billing_month', sprintf('%04d-%02d', $year, $month))
            ->whereNotIn('status', ['cancelled', 'voided'])
            ->whereIn('property_id', $pagedIds)
            ->with('booking:id,booking_code')
            ->orderBy('id')
            ->get(['id', 'property_id', 'booking_id', 'invoice_code'])
            ->groupBy('property_id');

        $invoiceCodesByProperty = $periodInvoices->map(fn ($g) => $g->pluck('invoice_code')->filter()->unique()->values()->all());

        $contractByProperty = $periodInvoices
            ->map(fn ($g) => $g->first(fn ($inv) => $inv->booking_id && $inv->booking)?->booking)
            ->filter();

        $fallbackContracts = $this->billingBookingsForPeriod($pagedIds->diff($contractByProperty->keys())->values()->all(), $year, $month);

        // บันทึกล่าสุดเมื่อไร โดยใคร (ผู้บริหารโครงการ หรือแอดมินที่บันทึกแทน) - เฉพาะทรัพย์ในหน้านี้
        $recordedBy = $this->latestRecorders($readingsByProperty->toBase()->only($pagedIds->all()));

        $rows = $properties->map(function ($property) use ($meterCounts, $readingsByProperty, $invoicedPropertyIds, $invoiceCodesByProperty, $contractByProperty, $fallbackContracts, $statusByPropertyId, $recordedBy) {
            $meters          = $meterCounts->get($property->id, collect());
            $periodReadings  = $readingsByProperty->get($property->id, collect());
            $readingsByMeter = $periodReadings->keyBy('property_meter_id');

            // เลขมิเตอร์เดือนก่อน → เดือนนี้ 1 บรรทัดต่อมิเตอร์ (น้ำก่อนไฟ) - มิเตอร์ที่ยังไม่จดงวดนี้ = missing
            $meterItems = $meters->sortBy('id')
                ->sortBy(fn ($m) => $m->meter_type === 'water' ? 0 : 1)
                ->map(function ($meter) use ($readingsByMeter) {
                    $reading = $readingsByMeter->get($meter->id);

                    return [
                        'meter_type'       => $meter->meter_type,
                        'missing'          => $reading === null,
                        'previous_reading' => $reading?->previous_reading,
                        'current_reading'  => $reading?->current_reading,
                        'units_used'       => $reading ? (float) $reading->units_used : null,
                    ];
                })
                ->values()
                ->all();

            return (object) [
                'property'         => $property,
                'meter_count'      => $meters->count(),
                'recorded_count'   => $periodReadings->count(),
                'meter_items'      => $meterItems,
                'recorded'         => $recordedBy->get($property->id),
                'already_invoiced' => isset($invoicedPropertyIds[$property->id]),
                'invoice_codes'    => $invoiceCodesByProperty->get($property->id, []),
                'status'           => $statusByPropertyId[$property->id] ?? 'unrecorded',
                'contract'         => $contractByProperty->get($property->id) ?? $fallbackContracts->get($property->id),
            ];
        });

        $paginator = new LengthAwarePaginator(
            $rows,
            $filteredIds->count(),
            $perPage,
            $currentPage,
            ['path' => route('meters.index'), 'query' => $request->except('page')]
        );

        // ตัวเลือกสถานะพื้นที่ = เฉพาะสถานะที่มีอยู่จริงในทรัพย์ (ที่มีมิเตอร์) ของตัวแทนคนนี้
        $propertyStatuses = HrPropertyStatus::whereIn('id', HrProperty::where('manager_agent_code', $agentCode)
                ->whereHas('activeMeters')
                ->whereNotNull('property_status_id')
                ->distinct()
                ->pluck('property_status_id'))
            ->orderBy('id')
            ->get(['id', 'name', 'slug', 'color']);

        $exportProperties = $this->eligibleProperties($agentCode);

        logSystem(
            userType: 'agent',
            userId: session('agent_id'),
            module: 'Meter',
            action: 'VIEW',
            description: "ดูรายการบันทึกมิเตอร์ งวด {$month}/{$year}"
        );

        return view('meters.index', compact(
            'paginator', 'year', 'month', 'status', 'scope', 'counts', 'propertyStatuses', 'propertyStatusCounts',
            'exportProperties', 'needsWorkCount', 'firstNeedsWorkId'
        ));
    }

    /**
     * Builds a 2-sheet Excel report (flat table + pivoted-by-month with photos) modeled on a
     * legacy standalone script's output. That legacy file queried an unrelated third-party
     * system's tables (ali_product/me_meter) - not ported, not reused. Only its visual/column
     * structure is replicated here, on top of this app's own ag_meter_readings data.
     */
    public function exportDownload(Request $request)
    {
        $agentCode = session('agent_code');

        $validated = $request->validate([
            'start_month'   => 'required|integer|between:1,12',
            'start_year'    => 'required|integer|min:2020|max:' . (now()->year + 1),
            'end_month'     => 'required|integer|between:1,12',
            'end_year'      => 'required|integer|min:2020|max:' . (now()->year + 1),
            'property_code' => 'nullable|string|max:50',
        ]);

        $startYear  = (int) $validated['start_year'];
        $startMonth = (int) $validated['start_month'];
        $endYear    = (int) $validated['end_year'];
        $endMonth   = (int) $validated['end_month'];
        $propertyCode = $validated['property_code'] ?? null;

        $spanMonths = ($endYear * 12 + $endMonth) - ($startYear * 12 + $startMonth) + 1;

        if ($spanMonths < 1) {
            return $this->exportError($request, 'งวดสิ้นสุดต้องไม่ก่อนงวดเริ่มต้น');
        }
        if ($spanMonths > self::EXPORT_MAX_MONTHS) {
            return $this->exportError(
                $request,
                'ช่วงเวลาที่เลือกยาวเกินไป (สูงสุด ' . self::EXPORT_MAX_MONTHS . ' เดือน) กรุณาเลือกช่วงที่สั้นลง'
            );
        }

        $properties = $this->eligibleProperties($agentCode, $propertyCode);

        if ($properties->isEmpty()) {
            return $this->exportError($request, 'ไม่พบทรัพย์สินที่ตรงกับเงื่อนไข');
        }

        ini_set('memory_limit', '512M');
        set_time_limit(300);

        $periods = $this->buildPeriodRange($startYear, $startMonth, $endYear, $endMonth);

        $readings = MeterReading::whereIn('property_id', $properties->pluck('id'))
            ->where(fn ($q) => $q->where('billing_year', '>', $startYear)
                ->orWhere(fn ($q2) => $q2->where('billing_year', $startYear)->where('billing_month', '>=', $startMonth)))
            ->where(fn ($q) => $q->where('billing_year', '<', $endYear)
                ->orWhere(fn ($q2) => $q2->where('billing_year', $endYear)->where('billing_month', '<=', $endMonth)))
            ->orderBy('billing_year')->orderBy('billing_month')
            ->get();

        $readingsByMeterPeriod = $readings->keyBy(
            fn ($r) => "{$r->property_meter_id}_{$r->billing_year}_{$r->billing_month}"
        );

        $tempImagePaths = [];

        try {
            $spreadsheet = new Spreadsheet();
            $this->buildFlatSheet($spreadsheet->getActiveSheet(), $properties, $periods, $readingsByMeterPeriod);

            $sheet2 = $spreadsheet->createSheet();
            $this->buildPivotSheet($sheet2, $properties, $periods, $readings, $readingsByMeterPeriod, $tempImagePaths);

            $spreadsheet->setActiveSheetIndex(0);

            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(false); // no formulas in either sheet - skip the recalculation pass
            $stream = fopen('php://temp', 'r+');
            $writer->save($stream);
            rewind($stream);
            $content = stream_get_contents($stream);
            fclose($stream);
        } finally {
            foreach ($tempImagePaths as $path) {
                @unlink($path);
            }
        }

        logSystem(
            userType: 'agent',
            userId: session('agent_id'),
            module: 'Meter',
            action: 'EXPORT',
            description: sprintf(
                'ส่งออกรายงานมิเตอร์ Excel งวด %02d/%d ถึง %02d/%d (%s)',
                $startMonth, $startYear + 543, $endMonth, $endYear + 543,
                $propertyCode ?: 'ทุกทรัพย์สิน'
            )
        );

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => $this->buildExportContentDisposition($startMonth, $startYear, $endMonth, $endYear),
            'Content-Length' => strlen($content),
        ]);
    }

    public function show(Request $request, HrProperty $property)
    {
        if ($property->manager_agent_code !== session('agent_code')) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงอสังหาริมทรัพย์นี้');
        }

        abort_if(! $property->is_utility_metering_enabled, 404, 'อสังหาริมทรัพย์นี้ไม่ได้เปิดใช้งานระบบมิเตอร์');

        $year  = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        $meters = $property->activeMeters()->get();
        abort_if($meters->isEmpty(), 404, 'อสังหาริมทรัพย์นี้ยังไม่มีมิเตอร์ที่เปิดใช้งาน');

        $currentReadings = MeterReading::forProperty($property->id)
            ->forPeriod($year, $month)
            ->get()
            ->keyBy('property_meter_id');

        $previousReadings = [];
        $previousReadingDates = [];
        $previousUnits = [];
        $suggestedPrevious = [];
        $nextReadings = [];
        foreach ($meters as $meter) {
            $priorReading = $this->findPriorReading($meter->id, $year, $month);
            $previousReadings[$meter->id] = $priorReading?->current_reading;
            $previousReadingDates[$meter->id] = $priorReading?->reading_date?->format('Y-m-d');
            $previousUnits[$meter->id] = $priorReading?->units_used;

            // เดือนก่อนไม่ได้จด: เสนอเลขล่าสุดของงวดที่เก่ากว่าเป็นค่าเริ่มต้น (สีเหลือง แก้ได้) แทนการปล่อยว่าง
            // - ค่าที่บันทึกจริงยังเป็นเลขที่ส่งมาจากฟอร์ม (เหมือนแอดมิน)
            if (! $priorReading && ! isset($currentReadings[$meter->id])) {
                $earlier = $this->findLatestEarlierReading($meter->id, $year, $month);
                if ($earlier) {
                    $suggestedPrevious[$meter->id] = [
                        'value'  => (int) $earlier->current_reading,
                        'period' => MeterReading::periodLabel((int) $earlier->billing_year, (int) $earlier->billing_month),
                    ];
                }
            }

            $next = $this->findNextReading($meter->id, $year, $month);
            if ($next) {
                $nextReadings[$meter->id] = [
                    'previous' => (int) $next->previous_reading,
                    'period'   => MeterReading::periodLabel((int) $next->billing_year, (int) $next->billing_month),
                    'locked'   => $this->hasIssuedUtilityInvoice($property->id, $next->rent_year, $next->rent_month),
                ];
            }
        }
        $meterLabels = $this->meterLabels($meters);

        $existingRentPeriod = $currentReadings->first(fn ($r) => $r->rent_year && $r->rent_month);
        $rentYear  = $existingRentPeriod->rent_year  ?? $year;
        $rentMonth = $existingRentPeriod->rent_month ?? $month;

        $allRecorded     = $currentReadings->count() === $meters->count();
        $allConfirmed    = $allRecorded && $currentReadings->every(fn ($r) => $r->status === 'confirmed');
        $alreadyInvoiced = $this->hasIssuedUtilityInvoice($property->id, $rentYear, $rentMonth);

        // ทรัพย์ก่อนหน้า/ถัดไป ตามตัวกรองเดียวกับหน้ารายการที่กดเข้ามา (ไม่ต้องกลับไปหน้ารายการทีละทรัพย์)
        // เฉพาะทรัพย์ที่เปิดระบบมิเตอร์ - ทรัพย์ที่ปิดไว้เปิดหน้านี้ไม่ได้
        $scope      = $this->scopeFrom($request);
        $scopeIds   = $this->scopedProperties((string) session('agent_code'), $scope)->filter()->keys()->values();
        $position   = $scopeIds->search($property->id);
        $prevId     = $position !== false && $position > 0 ? $scopeIds[$position - 1] : null;
        $nextId     = $position !== false ? $scopeIds->get($position + 1) : null;
        $navCodes   = HrProperty::whereIn('id', array_filter([$prevId, $nextId]))->pluck('property_code', 'id');
        $listStatus = in_array($request->query('status'), self::PERIOD_STATUSES, true) ? (string) $request->query('status') : '';
        $restartPhoto = MeterReading::supportsRestartPhoto();

        logSystem(
            userType: 'agent',
            userId: session('agent_id'),
            module: 'Meter',
            action: 'VIEW',
            description: "ดูข้อมูลมิเตอร์ {$property->title} งวด {$month}/{$year}"
        );

        return view('meters.show', compact(
            'property', 'meters', 'currentReadings', 'previousReadings', 'previousReadingDates', 'year', 'month',
            'previousUnits', 'suggestedPrevious', 'nextReadings', 'meterLabels',
            'rentYear', 'rentMonth', 'allRecorded', 'allConfirmed', 'alreadyInvoiced',
            'scope', 'scopeIds', 'position', 'prevId', 'nextId', 'navCodes', 'listStatus', 'restartPhoto'
        ));
    }

    public function store(Request $request, HrProperty $property)
    {
        if ($property->manager_agent_code !== session('agent_code')) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงอสังหาริมทรัพย์นี้');
        }

        $request->merge(array_map(fn ($v) => is_string($v) ? trim($v) : $v, $request->all()));

        $activeMeters = $property->activeMeters()->get();
        $labels       = $this->meterLabels($activeMeters);

        $validator = Validator::make($request->all(), [
            'billing_year'                       => 'required|integer|min:2020|max:' . (now()->year + 1),
            'billing_month'                      => 'required|integer|between:1,12',
            'rent_year'                          => 'required|integer|min:2020|max:' . (now()->year + 1),
            'rent_month'                         => 'required|integer|between:1,12',
            'readings'                           => 'required|array|min:1',
            // เลขมิเตอร์ตรวจเป็นข้อความใน MeterReading::checkInput() (ครบหลักพอดี 0 นำหน้าได้) - กฎ integer ไม่รับ "02345"
            'readings.*.current_reading'         => 'nullable|string|max:10',
            'readings.*.reading_date'            => 'nullable|date|before_or_equal:today',
            'readings.*.previous_reading'        => 'nullable|string|max:10',
            'readings.*.meter_changed'           => 'sometimes|boolean',
            'readings.*.old_meter_final_reading' => 'nullable|string|max:10',
            'readings.*.new_meter_start_reading' => 'nullable|string|max:10',
            'readings.*.image'                   => 'nullable|file|mimes:jpg,jpeg,png|max:10240',
            'readings.*.restart_image'           => 'nullable|file|mimes:jpg,jpeg,png|max:10240',
            'remark'                             => 'nullable|string|max:1000',
        ], MeterReading::validationMessages(), MeterReading::validationAttributes($labels));

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        $year      = (int) $validated['billing_year'];
        $month     = (int) $validated['billing_month'];
        $rentYear  = (int) $validated['rent_year'];
        $rentMonth = (int) $validated['rent_month'];

        // ข้อผิดพลาดของหน้านี้ส่งเป็น $errors ทั้งหมด หน้าบันทึกแสดงเป็น popup กลางจอรายการเดียว (meters/partials/reading-scripts)
        if ($this->hasIssuedUtilityInvoice($property->id, $rentYear, $rentMonth)) {
            return back()->withErrors(['form' => 'ออกใบแจ้งหนี้น้ำ/ไฟของงวดนี้ไปแล้ว แก้เลขมิเตอร์ไม่ได้'])->withInput();
        }

        // IDOR guard: re-verify every submitted meter id truly belongs to THIS
        // already-ownership-verified property, never trust the posted array keys.
        $submittedIds = array_map('intval', array_keys($validated['readings']));
        if (! empty(array_diff($submittedIds, $activeMeters->pluck('id')->all()))) {
            return back()->withErrors(['form' => 'รายการมิเตอร์ในหน้านี้ไม่ตรงกับข้อมูลล่าสุด (มีมิเตอร์ถูกเพิ่ม/ปิดใช้งานหลังเปิดหน้านี้) โหลดหน้าใหม่แล้วกรอกอีกครั้ง'])->withInput();
        }

        $meters         = $activeMeters->keyBy('id');
        $remark         = $validated['remark'] ?? null;
        $restartPhotoOn = MeterReading::supportsRestartPhoto();

        $existingRows = MeterReading::whereIn('property_meter_id', $submittedIds)
            ->forPeriod($year, $month)
            ->get()
            ->keyBy('property_meter_id');

        $checkMeta = [];
        foreach ($submittedIds as $meterId) {
            $next = $this->findNextReading($meterId, $year, $month);
            $checkMeta[$meterId] = [
                'type'        => $meters[$meterId]->meter_type,
                'label'       => $labels[$meterId],
                'prior'       => $this->findPriorReading($meterId, $year, $month),
                'existing'    => $existingRows->get($meterId),
                'next'        => $next,
                'next_locked' => $next && $this->hasIssuedUtilityInvoice($property->id, $next->rent_year, $next->rent_month),
                'next_label'  => $next ? MeterReading::periodLabel((int) $next->billing_year, (int) $next->billing_month) : null,
            ];
        }

        ['values' => $checked, 'errors' => $inputErrors, 'skipped' => $skipped] = MeterReading::checkInput($validated['readings'], $checkMeta);
        if ($inputErrors) {
            return back()->withErrors($inputErrors)->withInput();
        }

        // ไม่ได้กรอกเลขปัจจุบันเลยสักตัว = ไม่บันทึกอะไร (ไม่บังคับกรอก - ผู้ใช้ขอ 2026-10-15 ต่างจากแอดมินโดยตั้งใจ)
        // "บันทึก แล้วไปทรัพย์ถัดไป" จึงใช้ข้ามทรัพย์นี้ไปก่อนได้ ไม่มีข้อมูลเปลี่ยน จึงไม่เขียน log
        if (! $checked) {
            $scope = $this->scopeFrom($request);
            $code  = $property->property_code ?: $property->title;

            if ($request->boolean('next')) {
                $nextId = $this->nextPropertyNeedingWork($property->id, $scope, $year, $month);
                if ($nextId && $nextId !== $property->id) {
                    return redirect()
                        ->route('meters.show', ['property' => $nextId, 'year' => $year, 'month' => $month] + $scope)
                        ->with('success', "ข้าม {$code} ไปก่อน (ไม่ได้กรอกเลขมิเตอร์) - ต่อด้วยทรัพย์ถัดไป");
                }
            }

            $listStatus = (string) $request->input('list_status', '');

            return redirect()
                ->route('meters.index', ['year' => $year, 'month' => $month] + $scope
                    + (in_array($listStatus, self::PERIOD_STATUSES, true) ? ['status' => $listStatus] : []))
                ->with('success', "ไม่ได้กรอกเลขมิเตอร์ของ {$code} จึงยังไม่ได้บันทึก (จดทีหลังได้)");
        }

        $entries = [];
        foreach ($checked as $meterId => $value) {
            $meter = $meters->get($meterId);

            // "มิเตอร์เริ่มนับใหม่" (meter_changed) ใช้แทนทั้งรีเซ็ตและเปลี่ยนมิเตอร์ - ไม่มีโหมดรีเซ็ตแยกแล้ว
            // จึงบันทึก meter_reset = false เสมอ (แถวเก่าที่เคยรีเซ็ตไว้ บันทึกซ้ำแล้วจะถูกคำนวณใหม่ตามนี้)
            $calc = MeterReading::calculateUnits([
                'previous_reading'        => $value['previous_reading'],
                'current_reading'         => $value['current_reading'],
                'meter_reset'             => false,
                'meter_changed'           => $value['meter_changed'],
                'old_meter_final_reading' => $value['old_meter_final_reading'],
                'new_meter_start_reading' => $value['new_meter_start_reading'],
                'meter_max_value'         => null,
            ]);

            // เคยบันทึกงวดนี้ไว้แล้ว -> ยึดราคาต่อหน่วยที่บันทึกไว้ครั้งล่าสุด ไม่ดึงจาก
            // hr_property_meters.price_per_unit (master) ซ้ำอีก เพื่อไม่ให้ราคาของงวดที่บันทึก
            // แล้วขยับตามเวลาที่แอดมินแก้ราคา master ทีหลัง ใช้ราคา master เฉพาะการบันทึกครั้งแรกเท่านั้น
            $existingForPeriod = $existingRows->get($meterId);

            $pricePerUnit = $existingForPeriod && $existingForPeriod->price_per_unit !== null
                ? $existingForPeriod->price_per_unit
                : $meter->price_per_unit;

            $entries[$meterId] = [
                'meter'                    => $meter,
                'previous_reading'         => $value['previous_reading'],
                'current_reading'          => $value['current_reading'],
                'reading_date'             => $value['reading_date'],
                'meter_reset'              => false,
                'meter_changed'            => $value['meter_changed'],
                'old_meter_final_reading'  => $value['old_meter_final_reading'],
                'new_meter_start_reading'  => $value['new_meter_start_reading'],
                'meter_max_value'          => null,
                'units_used'               => $calc['units_used'],
                'price_per_unit'           => $pricePerUnit,
                'image'                    => $request->file("readings.$meterId.image"),
                // รูปเลขสุดท้ายก่อนเริ่มนับใหม่ (ไม่บังคับ) - ปิดสวิตช์ = ไม่รับรูป และรูปเดิมของงวดนี้ถูกลบ
                'restart_image'            => $restartPhotoOn && $value['meter_changed'] ? $request->file("readings.$meterId.restart_image") : null,
                'new_image_path'           => null,
                'new_restart_image_path'   => null,
            ];
        }

        // Write new files to disk before opening the transaction, so a storage failure
        // surfaces before any DB write. Old replaced files are deleted only after commit.
        $disk        = Storage::disk('meter_storage');
        $storedPaths = [];
        try {
            foreach (array_keys($entries) as $meterId) {
                foreach (['image' => 'new_image_path', 'restart_image' => 'new_restart_image_path'] as $fileKey => $pathKey) {
                    if (! $entries[$meterId][$fileKey]) {
                        continue;
                    }
                    $path = $entries[$meterId][$fileKey]->store('readings/' . $property->id, 'meter_storage');
                    if (! $path) {
                        throw new \RuntimeException("store() returned false for readings.{$meterId}.{$fileKey}");
                    }
                    $entries[$meterId][$pathKey] = $path;
                    $storedPaths[] = $path;
                }
            }
        } catch (\Throwable $e) {
            report($e);
            $disk->delete($storedPaths);

            return back()->withErrors(['form' => 'เก็บรูปมิเตอร์ไม่สำเร็จ ลองบันทึกอีกครั้ง ถ้ายังไม่ได้ให้แจ้งแอดมิน'])->withInput();
        }

        $oldImagePaths = [];
        $hadExisting   = false;

        try {
            DB::transaction(function () use ($entries, $property, $year, $month, $rentYear, $rentMonth, $remark, $restartPhotoOn, &$oldImagePaths, &$hadExisting) {
                foreach ($entries as $entry) {
                    $meter    = $entry['meter'];
                    $existing = MeterReading::where('property_meter_id', $meter->id)
                        ->forPeriod($year, $month)
                        ->first();

                    if ($existing) {
                        $hadExisting = true;
                        if ($entry['new_image_path'] && $existing->image_path) {
                            $oldImagePaths[] = $existing->image_path;
                        }
                    }

                    $restartValues = [];
                    if ($restartPhotoOn) {
                        $restartPath = $entry['meter_changed'] ? ($entry['new_restart_image_path'] ?: ($existing->old_meter_final_image_path ?? null)) : null;
                        if ($existing?->old_meter_final_image_path && $existing->old_meter_final_image_path !== $restartPath) {
                            $oldImagePaths[] = $existing->old_meter_final_image_path;
                        }
                        $restartValues['old_meter_final_image_path'] = $restartPath;
                    }

                    MeterReading::updateOrCreate(
                        [
                            'property_meter_id' => $meter->id,
                            'billing_year'      => $year,
                            'billing_month'     => $month,
                        ],
                        $restartValues + [
                            'property_id'             => $property->id,
                            'meter_type'              => $meter->meter_type,
                            'rent_year'               => $rentYear,
                            'rent_month'              => $rentMonth,
                            'previous_reading'        => $entry['previous_reading'],
                            'current_reading'         => $entry['current_reading'],
                            'reading_date'            => $entry['reading_date'],
                            'meter_reset'             => $entry['meter_reset'],
                            'meter_changed'           => $entry['meter_changed'],
                            'old_meter_final_reading' => $entry['old_meter_final_reading'],
                            'new_meter_start_reading' => $entry['new_meter_start_reading'],
                            'meter_max_value'         => $entry['meter_max_value'],
                            'units_used'              => $entry['units_used'],
                            'price_per_unit'          => $entry['price_per_unit'],
                            'amount'                  => $entry['units_used'] * (float) $entry['price_per_unit'],
                            'image_path'              => $entry['new_image_path'] ?: ($existing->image_path ?? null),
                            'remark'                  => $remark,
                            // บันทึก = ยืนยันทันที (ไม่มีขั้นตอนกดยืนยันแยกอีกต่อไป) - ตามที่ผู้ดูแลระบบขอ
                            // เพื่อลดขั้นตอนของผู้บริหารโครงการ ยังแก้ไขซ้ำได้ตามปกติจนกว่าจะออกใบแจ้งหนี้แล้ว
                            // (ดู hasIssuedUtilityInvoice() guard ด้านบน)
                            'status'                  => 'confirmed',
                            'confirmed_at'            => now(),
                            'confirmed_by'            => session('agent_id'),
                            'created_by'              => $existing->created_by ?? session('agent_id'),
                            'updated_by'              => session('agent_id'),
                        ]
                    );
                }
            });
        } catch (\Throwable $e) {
            // DB ล้ม = ไม่มีแถวไหนเปลี่ยน (transaction) - ลบรูปที่เพิ่งเขียน ไม่ให้ค้างเป็นไฟล์กำพร้า
            report($e);
            $disk->delete($storedPaths);

            return back()->withErrors(['form' => 'บันทึกเลขมิเตอร์ไม่สำเร็จ ลองบันทึกอีกครั้ง ถ้ายังไม่ได้ให้แจ้งแอดมิน'])->withInput();
        }

        $disk->delete($oldImagePaths);

        logSystem(
            userType: 'agent',
            userId: session('agent_id'),
            module: 'Meter',
            action: $hadExisting ? 'UPDATE' : 'CREATE',
            description: "บันทึกมิเตอร์ {$property->title} งวด {$month}/{$year}"
        );

        $scope = $this->scopeFrom($request);
        $code  = $property->property_code ?: $property->title;
        // มิเตอร์ที่เว้นเลขปัจจุบันว่างไว้ (ข้าม ไม่บันทึก) - บอกในข้อความว่ายังเหลือตัวไหน ทรัพย์เป็น "จดบางส่วน"
        $skippedNote = $skipped
            ? ' (ยังไม่ได้จด ' . collect($skipped)->map(fn ($id) => $labels[$id] ?? 'มิเตอร์')->implode(', ') . ' ต้องจดก่อนออกใบแจ้งหนี้น้ำ/ไฟ)'
            : '';

        // งวดถัดไปจดไว้แล้วแต่เริ่มจากเลขอื่น -> หน่วยงวดนั้นยังคิดจากเลขเดิม พาไปงวดนั้นให้กดบันทึกซ้ำทันที
        // (มาก่อนปุ่ม "บันทึก แล้วไปทรัพย์ถัดไป" เพราะถ้าไม่แก้ตอนนี้ ใบแจ้งหนี้งวดถัดไปจะคิดหน่วยผิด - เหมือนแอดมิน)
        $mismatched = collect($checked)->filter(fn ($v) => $v['next_mismatch'])->keys();
        if ($mismatched->isNotEmpty()) {
            [$nextYear, $nextMonth] = $month === 12 ? [$year + 1, 1] : [$year, $month + 1];

            return redirect()
                ->route('meters.show', ['property' => $property->id, 'year' => $nextYear, 'month' => $nextMonth] + $scope)
                ->with('meter_notice', [
                    'title' => 'บันทึกงวด ' . MeterReading::periodLabel($year, $month) . ' แล้ว - งวดนี้ต้องบันทึกซ้ำ',
                    'text'  => 'เลขเดือนก่อนของ ' . $mismatched->map(fn ($id) => $labels[$id])->implode(', ') . ' เปลี่ยนไป ตรวจเลข แล้วกด "บันทึกข้อมูลมิเตอร์"',
                ]);
        }

        // ปุ่ม "บันทึก แล้วไปทรัพย์ถัดไป" - ไปทรัพย์ถัดไปที่ยังต้องจด/ยืนยันในตัวกรองเดียวกัน
        if ($request->boolean('next')) {
            $nextId = $this->nextPropertyNeedingWork($property->id, $scope, $year, $month);
            if ($nextId) {
                return redirect()
                    ->route('meters.show', ['property' => $nextId, 'year' => $year, 'month' => $month] + $scope)
                    ->with('success', "บันทึกมิเตอร์ {$code} แล้ว{$skippedNote} - ต่อด้วยทรัพย์ถัดไป");
            }

            return redirect()
                ->route('meters.index', ['year' => $year, 'month' => $month] + $scope)
                ->with('success', $skipped
                    ? "บันทึกมิเตอร์ {$code} แล้ว{$skippedNote} - ทรัพย์อื่นในรายการนี้จดครบแล้ว"
                    : "บันทึกมิเตอร์ {$code} แล้ว - จดครบทุกทรัพย์ในรายการนี้แล้ว");
        }

        $listStatus = (string) $request->input('list_status', '');

        return redirect()
            ->route('meters.index', ['year' => $year, 'month' => $month] + $scope
                + (in_array($listStatus, self::PERIOD_STATUSES, true) ? ['status' => $listStatus] : []))
            ->with('success', "บันทึกและยืนยันข้อมูลมิเตอร์ {$code} เรียบร้อยแล้ว{$skippedNote}");
    }

    /**
     * Wipes every reading recorded for one property/period so the agent can start over.
     * Hard-deletes (not soft) because ag_meter_readings has a unique index on
     * (property_meter_id, billing_year, billing_month) that isn't scoped to deleted_at -
     * a soft-deleted row would collide with the re-insert that store() does afterward.
     */
    public function destroy(Request $request, HrProperty $property)
    {
        if ($property->manager_agent_code !== session('agent_code')) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงอสังหาริมทรัพย์นี้');
        }

        $year  = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        // Index rows are grouped by rent period (forRentPeriod); the per-property show
        // page is grouped by the calendar reading period (forPeriod) - match whichever
        // this delete button was clicked from so it clears exactly what's on screen.
        $byRentPeriod = $request->query('scope') === 'rent';

        $query = MeterReading::forProperty($property->id);
        $query = $byRentPeriod ? $query->forRentPeriod($year, $month) : $query->forPeriod($year, $month);

        $readings = $query->get();

        if ($readings->isEmpty()) {
            return back()->with('error', 'ไม่พบข้อมูลมิเตอร์ของงวดนี้');
        }

        $anyRentYear  = $readings->first()->rent_year;
        $anyRentMonth = $readings->first()->rent_month;
        if ($this->hasIssuedUtilityInvoice($property->id, $anyRentYear, $anyRentMonth)) {
            return back()->with('error', 'ออกใบแจ้งหนี้น้ำ/ไฟของงวดนี้ไปแล้ว ไม่สามารถลบข้อมูลมิเตอร์ได้');
        }

        $imagePaths = $readings->pluck('image_path');
        if (MeterReading::supportsRestartPhoto()) {
            $imagePaths = $imagePaths->merge($readings->pluck('old_meter_final_image_path'));
        }

        $deleteQuery = MeterReading::forProperty($property->id);
        $deleteQuery = $byRentPeriod ? $deleteQuery->forRentPeriod($year, $month) : $deleteQuery->forPeriod($year, $month);
        $deleteQuery->forceDelete();

        Storage::disk('meter_storage')->delete($imagePaths->filter()->values()->all());

        logSystem(
            userType: 'agent',
            userId: session('agent_id'),
            module: 'Meter',
            action: 'DELETE',
            description: "ลบข้อมูลมิเตอร์ {$property->title} งวด {$month}/{$year}"
        );

        return redirect()
            ->route('meters.index', ['year' => $year, 'month' => $month] + $this->scopeFrom($request))
            ->with('success', 'ลบข้อมูลมิเตอร์เรียบร้อยแล้ว ต้องบันทึกใหม่');
    }

    /**
     * Manual fallback for rows saved before store() started auto-confirming on save
     * (or any other way a row ends up stuck at 'draft'). Marks every reading recorded
     * for one property/calendar-period as confirmed, once every active meter has a
     * reading - this is the status happyest's utility-invoice feature requires before
     * a property becomes invoiceable. Scoped by calendar period (forPeriod), matching
     * what's actually on screen when the button is clicked.
     */
    public function confirm(Request $request, HrProperty $property)
    {
        if ($property->manager_agent_code !== session('agent_code')) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงอสังหาริมทรัพย์นี้');
        }

        $year  = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        $activeMeterIds = $property->activeMeters()->pluck('id');
        if ($activeMeterIds->isEmpty()) {
            return back()->with('error', 'อสังหาริมทรัพย์นี้ยังไม่มีมิเตอร์ที่เปิดใช้งาน');
        }

        $readings = MeterReading::forProperty($property->id)->forPeriod($year, $month)->get();

        $missingCount = $activeMeterIds->diff($readings->pluck('property_meter_id'))->count();
        if ($missingCount > 0) {
            return back()->with('error', 'กรุณาบันทึกข้อมูลมิเตอร์ให้ครบทุกตัวก่อนยืนยัน');
        }

        $anyRentYear  = $readings->first()?->rent_year;
        $anyRentMonth = $readings->first()?->rent_month;
        if ($this->hasIssuedUtilityInvoice($property->id, $anyRentYear, $anyRentMonth)) {
            return back()->with('error', 'ออกใบแจ้งหนี้น้ำ/ไฟของงวดนี้ไปแล้ว ไม่สามารถยืนยันซ้ำได้');
        }

        MeterReading::forProperty($property->id)->forPeriod($year, $month)->update([
            'status'       => 'confirmed',
            'confirmed_at' => now(),
            'confirmed_by' => session('agent_id'),
        ]);

        logSystem(
            userType: 'agent',
            userId: session('agent_id'),
            module: 'Meter',
            action: 'CONFIRM',
            description: "ยืนยันข้อมูลมิเตอร์ {$property->title} งวด {$month}/{$year}"
        );

        return redirect()
            ->route('meters.show', ['property' => $property->id, 'year' => $year, 'month' => $month] + $this->scopeFrom($request))
            ->with('success', 'ยืนยันข้อมูลมิเตอร์เรียบร้อยแล้ว พร้อมออกใบแจ้งหนี้');
    }

    /**
     * Soft cross-app read of happyest's hr_invoices (same shared MySQL DB, no FK - same
     * precedent as ag_meter_readings' own soft references). Blocks agents from silently
     * changing/deleting meter numbers after the admin side has already issued a utility
     * invoice from them, which would otherwise leave the invoice showing stale figures.
     */
    private function hasIssuedUtilityInvoice(int $propertyId, ?int $rentYear, ?int $rentMonth): bool
    {
        if (! $rentYear || ! $rentMonth) {
            return false;
        }

        return DB::table('hr_invoices')
            ->where('property_id', $propertyId)
            ->where('invoice_type', 'utility')
            ->where('billing_month', sprintf('%04d-%02d', $rentYear, $rentMonth))
            ->whereNotIn('status', ['cancelled', 'voided'])
            ->whereNull('deleted_at')
            ->exists();
    }

    /** ?kind=restart = รูปเลขสุดท้ายก่อนเริ่มนับใหม่ (old_meter_final_image_path) - เหมือนแอดมิน */
    public function viewImage(Request $request, MeterReading $reading)
    {
        HrProperty::where('id', $reading->property_id)
            ->where('manager_agent_code', session('agent_code'))
            ->firstOrFail();

        $path = $request->query('kind') === 'restart' && MeterReading::supportsRestartPhoto()
            ? $reading->old_meter_final_image_path
            : $reading->image_path;

        abort_if(! $path, 404, 'ไม่พบรูปภาพ');
        abort_if(! Storage::disk('meter_storage')->exists($path), 404, 'ไม่พบรูปภาพ');

        return response()->file(Storage::disk('meter_storage')->path($path));
    }

    /** ตัวกรองของหน้ารายการ (ค่าว่างตัดทิ้ง) - ใช้ทั้งรายการ ลิงก์ไปหน้าบันทึก และปุ่มทรัพย์ก่อนหน้า/ถัดไป */
    private function scopeFrom(Request $request): array
    {
        return array_filter(
            array_map(fn ($v) => is_string($v) ? trim($v) : $v, $request->only(self::SCOPE_KEYS)),
            fn ($v) => $v !== null && $v !== '' && ! is_array($v)
        );
    }

    /**
     * ทรัพย์ของตัวแทนคนนี้ที่มีมิเตอร์เปิดใช้งานอยู่จริง ตามตัวกรองของหน้ารายการ เรียงตามรหัสทรัพย์ = ลำดับของตาราง
     * และของปุ่มก่อนหน้า/ถัดไป (เงื่อนไขเดียวกับ scopedPropertyIds() ของแอดมิน)
     *
     * @return Collection id => เปิดระบบมิเตอร์อยู่ (bool)
     */
    private function scopedProperties(string $agentCode, array $scope): Collection
    {
        $search = (string) ($scope['search'] ?? '');

        return HrProperty::where('manager_agent_code', $agentCode)
            ->whereHas('activeMeters')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('title', 'like', "%{$search}%")
                        ->orWhere('property_code', 'like', "%{$search}%");
                });
            })
            ->when($scope['property_status_id'] ?? null, fn ($q, $v) => $q->where('property_status_id', $v))
            ->orderBy('property_code')
            ->pluck('is_utility_metering_enabled', 'id')
            ->map(fn ($enabled) => (bool) $enabled);
    }

    /**
     * สถานะงวดของแต่ละทรัพย์ - งวดนี้หมายถึงงวดค่าเช่าที่จะเรียกเก็บพร้อมกัน (rent_year/rent_month) ไม่ใช่เดือนปฏิทินที่จดจริง
     * ตรงกับ periodStatuses() ของแอดมินทุกข้อ:
     *   invoiced   = ออกใบแจ้งหนี้น้ำ/ไฟของงวดนี้แล้ว (ข้อมูลล็อก)
     *   recorded   = จดครบทุกตัวและยืนยันครบ = พร้อมออกใบ
     *   draft      = จดครบแต่ยังมีแถวที่ไม่ยืนยัน (ข้อมูลเก่าก่อนที่การบันทึกจะยืนยันทันที) - หน้าใบแจ้งหนี้น้ำ/ไฟของแอดมิน
     *                นับเป็น "ยังจดมิเตอร์ไม่ครบ" จึงต้องแยกให้เห็น
     *   partial    = จดแล้วบางตัว
     *   unrecorded = ยังไม่จดเลย
     */
    private function periodStatuses(Collection $propertyIds, int $year, int $month): array
    {
        $meterCounts = HrPropertyMeter::whereIn('property_id', $propertyIds)
            ->where('is_active', true)
            ->get(['id', 'property_id', 'meter_type'])
            ->groupBy('property_id');

        $readingsByProperty = MeterReading::whereIn('property_id', $propertyIds)
            ->forRentPeriod($year, $month)
            ->get()
            ->groupBy('property_id');

        $invoicedPropertyIds = HrInvoice::where('invoice_type', 'utility')
            ->where('billing_month', sprintf('%04d-%02d', $year, $month))
            ->whereNotIn('status', ['cancelled', 'voided'])
            ->whereIn('property_id', $propertyIds)
            ->pluck('property_id')
            ->unique()
            ->flip();

        $status = $propertyIds->mapWithKeys(function ($id) use ($meterCounts, $readingsByProperty, $invoicedPropertyIds) {
            if (isset($invoicedPropertyIds[$id])) {
                return [$id => 'invoiced'];
            }

            $meterCount = $meterCounts->get($id, collect())->count();
            $readings   = $readingsByProperty->get($id, collect());

            if ($meterCount > 0 && $readings->count() === $meterCount) {
                return [$id => $readings->every(fn ($r) => $r->status === 'confirmed') ? 'recorded' : 'draft'];
            }

            return [$id => $readings->isNotEmpty() ? 'partial' : 'unrecorded'];
        });

        return compact('meterCounts', 'readingsByProperty', 'invoicedPropertyIds', 'status');
    }

    /**
     * ทรัพย์ถัดไป (ตามลำดับรายการเดียวกับที่ตัวแทนเห็น) ที่ยังต้องจด/ยืนยันในงวดนี้ - หาต่อจากทรัพย์ปัจจุบันก่อน
     * แล้ววนกลับจากต้นรายการ ไม่มีแล้วคืน null (ข้ามทรัพย์ที่ปิดระบบมิเตอร์ เพราะเปิดหน้าบันทึกไม่ได้)
     */
    private function nextPropertyNeedingWork(int $currentId, array $scope, int $year, int $month): ?int
    {
        $ids    = $this->scopedProperties((string) session('agent_code'), $scope)->filter()->keys()->values();
        $status = $this->periodStatuses($ids, $year, $month)['status'];

        $position = $ids->search($currentId);
        $ordered  = $position === false
            ? $ids
            : $ids->slice($position + 1)->concat($ids->slice(0, $position));

        return $ordered->first(fn ($id) => in_array($status[$id] ?? null, self::NEEDS_WORK_STATUSES, true));
    }

    /**
     * การจองที่ใบแจ้งหนี้น้ำ/ไฟของงวดนี้จะเรียกเก็บ ต่อทรัพย์ - กติกาเดียวกับ happyest
     * UtilityInvoiceBillingRowService::billingBookingsForPeriod(): การจองที่ไม่ถูกยกเลิก/ปฏิเสธ มีลูกค้า และเริ่มก่อนสิ้นงวด
     * เลือกตามลำดับ เข้าใช้พื้นที่อยู่ + คาบเกี่ยวงวด > คาบเกี่ยวงวด > อื่นๆ (เท่ากันเอาที่เริ่มล่าสุด)
     *
     * @return Collection property_id => HrBooking
     */
    private function billingBookingsForPeriod(array $propertyIds, int $year, int $month): Collection
    {
        if (empty($propertyIds)) {
            return collect();
        }

        $periodStart = Carbon::create($year, $month, 1)->startOfMonth();
        $periodEnd   = $periodStart->copy()->endOfMonth();

        $priority = function (HrBooking $booking) use ($periodStart): int {
            $overlaps = $booking->check_out === null || $booking->check_out->gte($periodStart);

            return match (true) {
                $booking->status === 'checked_in' && $overlaps => 0,
                $overlaps => 1,
                default => 2,
            };
        };

        return HrBooking::whereIn('property_id', $propertyIds)
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->whereNotNull('customer_id')
            ->where('check_in', '<=', $periodEnd)
            ->orderByDesc('check_in')
            ->orderByDesc('id')
            ->get(['id', 'property_id', 'booking_code', 'status', 'check_in', 'check_out'])
            ->groupBy('property_id')
            ->map(fn ($g) => $g->sortBy($priority)->first());
    }

    /**
     * "บันทึกเมื่อไร โดยใคร" ของเลขมิเตอร์งวดนี้ต่อทรัพย์ - พอร์ตจาก happyest MeterReadingRecorderService
     * updated_by เป็น id ของผู้บริหารโครงการ (แอปนี้) หรือ id ของแอดมิน (happyest) โดยไม่มีคอลัมน์บอกฝั่ง และ id ชนกันได้
     * จึงแยกด้วย hr_audit_logs: แอดมินเขียน meter_reading.create/update (record_id = ทรัพย์, description มี "งวด {เดือน}/{ปี} ")
     * ทุกครั้งที่บันทึก - เจอ log ของแอดมินคนนั้นภายใน 60 วินาทีหลัง updated_at = แอดมินบันทึกแทน ไม่เจอ = ผู้บริหารโครงการ
     *
     * @param  Collection  $readingsByProperty  MeterReading ของงวดที่แสดง groupBy('property_id')
     * @return Collection property_id => object{at, first_at, edited, role ('admin'|'agent'), name}
     */
    private function latestRecorders(Collection $readingsByProperty): Collection
    {
        $latest = $readingsByProperty
            ->map(fn ($readings) => $readings->filter(fn ($r) => $r->updated_at)->sortByDesc('updated_at')->first())
            ->filter();

        if ($latest->isEmpty()) {
            return collect();
        }

        $adminLogs = DB::table('hr_audit_logs')
            ->where('table_name', 'ag_meter_readings')
            ->whereIn('record_id', $latest->keys()->map(fn ($id) => (string) $id)->all())
            ->whereIn('action', ['meter_reading.create', 'meter_reading.update'])
            ->whereBetween('created_at', [
                $latest->min('updated_at'),
                $latest->max('updated_at')->copy()->addSeconds(self::RECORDER_LOG_WINDOW_SECONDS),
            ])
            ->get(['record_id', 'user_id', 'description', 'created_at'])
            ->groupBy('record_id');

        $byAdmin = $latest->map(function ($reading, $propertyId) use ($adminLogs) {
            $period = " งวด {$reading->billing_month}/{$reading->billing_year} ";
            $until  = $reading->updated_at->copy()->addSeconds(self::RECORDER_LOG_WINDOW_SECONDS);

            return $adminLogs->get((string) $propertyId, collect())->contains(fn ($log) => (int) $log->user_id === (int) $reading->updated_by
                && Carbon::parse($log->created_at)->between($reading->updated_at, $until)
                && str_contains((string) $log->description, $period));
        });

        $adminIds   = $latest->filter(fn ($r, $pid) => $byAdmin[$pid])->pluck('updated_by')->filter()->unique();
        $adminNames = $adminIds->isEmpty() ? collect() : HrAdmin::withTrashed()->whereIn('id', $adminIds)->pluck('name', 'id');

        return $latest->map(fn ($reading, $propertyId) => (object) [
            'at'       => $reading->updated_at,
            'first_at' => $reading->created_at,
            'edited'   => $reading->created_at && $reading->updated_at->gt($reading->created_at),
            'role'     => $byAdmin[$propertyId] ? 'admin' : 'agent',
            'name'     => $byAdmin[$propertyId] ? $adminNames->get($reading->updated_by) : null,
        ]);
    }

    /**
     * MeterReading row for a meter from exactly the calendar month before the given
     * period - matches the legacy meter system (C:\laragon\www\meter), which only
     * looks at month-1 and never reaches further back on a gap. If that exact month
     * wasn't recorded, there is no auto-filled previous value - the agent enters it
     * manually instead of silently inheriting a stale reading from months ago.
     */
    private function findPriorReading(int $propertyMeterId, int $year, int $month): ?MeterReading
    {
        $prevMonth = $month - 1;
        $prevYear  = $year;
        if ($prevMonth < 1) {
            $prevMonth = 12;
            $prevYear--;
        }

        return MeterReading::where('property_meter_id', $propertyMeterId)
            ->where('billing_year', $prevYear)
            ->where('billing_month', $prevMonth)
            ->first();
    }

    /** แถวล่าสุดที่เก่ากว่าเดือน-1 - ใช้แค่เป็นค่าแนะนำของช่องเลขเดือนก่อนตอนเดือนก่อนไม่ได้จด (เหมือนแอดมิน) */
    private function findLatestEarlierReading(int $propertyMeterId, int $year, int $month): ?MeterReading
    {
        return MeterReading::where('property_meter_id', $propertyMeterId)
            ->where(fn ($q) => $q->where('billing_year', '<', $year)
                ->orWhere(fn ($q) => $q->where('billing_year', $year)->where('billing_month', '<', $month)))
            ->orderByDesc('billing_year')
            ->orderByDesc('billing_month')
            ->first();
    }

    /** แถวของงวดถัดไป (เดือน+1) - previous_reading ของแถวนั้นต้องเท่ากับเลขปัจจุบันของงวดนี้ */
    private function findNextReading(int $propertyMeterId, int $year, int $month): ?MeterReading
    {
        [$nextYear, $nextMonth] = $month === 12 ? [$year + 1, 1] : [$year, $month + 1];

        return MeterReading::where('property_meter_id', $propertyMeterId)
            ->forPeriod($nextYear, $nextMonth)
            ->first();
    }

    /** ชื่อมิเตอร์แบบเดียวกับหัวแถวในหน้าบันทึก ("ไฟฟ้า" / "ไฟฟ้า ตัวที่ 2") - ใช้ในข้อความแจ้งเตือน */
    private function meterLabels($meters): array
    {
        $labels = [];
        foreach ($meters->groupBy('meter_type') as $typeMeters) {
            $multi = $typeMeters->count() > 1;
            foreach ($typeMeters->values() as $i => $meter) {
                $labels[$meter->id] = $meter->type_label . ($multi ? ' ตัวที่ ' . ($i + 1) : '');
            }
        }

        return $labels;
    }

    /**
     * The export form is a modal (fetch-based, so the page never navigates away) - a plain
     * back()->with('error') redirect would be invisible to that fetch() call, so JSON-expecting
     * requests get a proper error body instead. $request->validate() already does this
     * automatically for its own failures; this covers the controller's own manual checks.
     */
    private function exportError(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 422);
        }

        return back()->with('error', $message)->withInput();
    }

    /**
     * Properties eligible for the Excel export: this agent's own, utility metering turned on,
     * with at least one active meter - stricter than index()'s listing on purpose (index() is an
     * operational to-do list and deliberately shows everything; a billing report should not).
     */
    private function eligibleProperties(string $agentCode, ?string $propertyCode = null)
    {
        return HrProperty::where('manager_agent_code', $agentCode)
            ->where('is_utility_metering_enabled', true)
            ->whereHas('meters', fn ($q) => $q->where('is_active', true))
            ->when($propertyCode, fn ($q, $code) => $q->where('property_code', $code))
            ->with('activeMeters')
            ->orderBy('property_code')
            ->get();
    }

    private function buildPeriodRange(int $startYear, int $startMonth, int $endYear, int $endMonth): array
    {
        $periods = [];
        $year = $startYear;
        $month = $startMonth;

        while ($year < $endYear || ($year === $endYear && $month <= $endMonth)) {
            $periods[] = ['year' => $year, 'month' => $month];
            $month++;
            if ($month > 12) {
                $month = 1;
                $year++;
            }
        }

        return $periods;
    }

    private function buildExportContentDisposition(int $startMonth, int $startYear, int $endMonth, int $endYear): string
    {
        $filename = sprintf(
            'รายงานมิเตอร์_%02d_%d_ถึง_%02d_%d.xlsx',
            $startMonth, $startYear, $endMonth, $endYear
        );
        $asciiFallback = sprintf('meter-export-%d%02d-%d%02d.xlsx', $startYear, $startMonth, $endYear, $endMonth);

        return sprintf(
            'attachment; filename="%s"; filename*=UTF-8\'\'%s',
            $asciiFallback,
            rawurlencode($filename)
        );
    }

    /**
     * Sheet 1 "รายงานตามช่วงเดือน" - flat, one row per property per month in range. Column
     * layout/styling mirrors the legacy reference file exactly, minus its ค่าขยะ column: no field
     * anywhere in the current schema maps to a garbage fee, so it's omitted rather than faked
     * (21 columns, A-U, instead of the legacy 22, A-V).
     *
     * @param  \Illuminate\Support\Collection  $properties
     * @param  \Illuminate\Support\Collection  $readingsByMeterPeriod  keyed "{property_meter_id}_{year}_{month}"
     */
    private function buildFlatSheet(Worksheet $sheet, $properties, array $periods, $readingsByMeterPeriod): void
    {
        $sheet->setTitle('รายงานตามช่วงเดือน');

        $headers = [
            'ลำดับ', 'รหัสห้อง', 'รายละเอียด', 'เดือน', 'ปี',
            'วันที่อ่านไฟ', 'มิเตอร์ไฟก่อนหน้า', 'มิเตอร์ไฟปัจจุบัน', 'หน่วยไฟใช้', 'ราคา/หน่วย(ไฟ)', 'ค่าไฟ',
            'วันที่อ่านน้ำ', 'มิเตอร์น้ำก่อนหน้า', 'มิเตอร์น้ำปัจจุบัน', 'หน่วยน้ำใช้', 'ราคา/หน่วย(น้ำ)', 'ค่าน้ำ',
            'ค่าส่วนกลาง', 'รวม', 'สถานะ', 'หมายเหตุ',
        ];

        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue("{$col}1", $header);
            $col++;
        }

        $sheet->getStyle('A1:U1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 12, 'name' => 'TH Sarabun New'],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(25);

        $row = 2;
        $seq = 1;

        foreach ($properties as $property) {
            $waterMeter = $property->activeMeters->firstWhere('meter_type', 'water');
            $electricMeter = $property->activeMeters->firstWhere('meter_type', 'electric');
            $commonFee = (float) ($property->common_fee_per_month ?? 0);

            foreach ($periods as $period) {
                $y = $period['year'];
                $m = $period['month'];

                $electricReading = $electricMeter ? ($readingsByMeterPeriod["{$electricMeter->id}_{$y}_{$m}"] ?? null) : null;
                $waterReading = $waterMeter ? ($readingsByMeterPeriod["{$waterMeter->id}_{$y}_{$m}"] ?? null) : null;

                $electricAmount = (float) ($electricReading->amount ?? 0);
                $waterAmount = (float) ($waterReading->amount ?? 0);
                $total = $electricAmount + $waterAmount + $commonFee;
                $recorded = $electricReading || $waterReading;
                $remark = $electricReading->remark ?? $waterReading->remark ?? '';

                $sheet->setCellValue("A{$row}", $seq);
                $sheet->setCellValue("B{$row}", $property->property_code);
                $sheet->setCellValue("C{$row}", $property->title);
                $sheet->setCellValueExplicit("D{$row}", sprintf('%02d', $m), DataType::TYPE_STRING);
                $sheet->setCellValue("E{$row}", $y);
                $sheet->setCellValue("F{$row}", $electricReading?->reading_date?->format('d/m/Y') ?? '');
                $sheet->setCellValue("G{$row}", $electricReading->previous_reading ?? null);
                $sheet->setCellValue("H{$row}", $electricReading->current_reading ?? null);
                $sheet->setCellValue("I{$row}", $electricReading->units_used ?? null);
                $sheet->setCellValue("J{$row}", $electricReading->price_per_unit ?? ($electricMeter->price_per_unit ?? 0));
                $sheet->setCellValue("K{$row}", $electricAmount);
                $sheet->setCellValue("L{$row}", $waterReading?->reading_date?->format('d/m/Y') ?? '');
                $sheet->setCellValue("M{$row}", $waterReading->previous_reading ?? null);
                $sheet->setCellValue("N{$row}", $waterReading->current_reading ?? null);
                $sheet->setCellValue("O{$row}", $waterReading->units_used ?? null);
                $sheet->setCellValue("P{$row}", $waterReading->price_per_unit ?? ($waterMeter->price_per_unit ?? 0));
                $sheet->setCellValue("Q{$row}", $waterAmount);
                $sheet->setCellValue("R{$row}", $commonFee);
                $sheet->setCellValue("S{$row}", $total);
                $sheet->setCellValue("T{$row}", $recorded ? 'บันทึกแล้ว' : 'ยังไม่บันทึก');
                $sheet->setCellValue("U{$row}", $remark);

                $row++;
                $seq++;
            }
        }

        $lastRow = $row - 1;

        if ($lastRow >= 2) {
            $sheet->getStyle("A2:U{$lastRow}")->applyFromArray([
                'font' => ['size' => 11, 'name' => 'TH Sarabun New'],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D0D0D0']]],
            ]);

            for ($i = 2; $i <= $lastRow; $i++) {
                if ($i % 2 === 0) {
                    $sheet->getStyle("A{$i}:U{$i}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F2F2F2');
                }
            }

            $sheet->getStyle("G2:K{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("M2:S{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("J2:K{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("P2:S{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("A2:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D2:E{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("T2:T{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        $widths = [
            'A' => 8, 'B' => 12, 'C' => 30, 'D' => 8, 'E' => 8, 'F' => 12, 'G' => 12, 'H' => 12,
            'I' => 10, 'J' => 12, 'K' => 12, 'L' => 12, 'M' => 12, 'N' => 12, 'O' => 10, 'P' => 12,
            'Q' => 12, 'R' => 12, 'S' => 12, 'T' => 12, 'U' => 30,
        ];
        foreach ($widths as $letter => $width) {
            $sheet->getColumnDimension($letter)->setWidth($width);
        }

        $sheet->freezePane('A2');
    }

    /**
     * Sheet 2 "รายละเอียดมิเตอร์" - pivoted, 5 columns per month in range, with per-property
     * electric/water blocks and optional embedded meter photos. Column D "สถานะมิเตอร์" is left
     * blank on purpose - confirmed the legacy file's own feeding query never actually selected
     * that field either, so this isn't new data loss, just faithful to what it really output.
     *
     * @param  \Illuminate\Support\Collection  $properties
     * @param  \Illuminate\Support\Collection  $allReadings  every reading in range, unkeyed
     * @param  \Illuminate\Support\Collection  $readingsByMeterPeriod  keyed "{property_meter_id}_{year}_{month}"
     */
    private function buildPivotSheet(
        Worksheet $sheet,
        $properties,
        array $periods,
        $allReadings,
        $readingsByMeterPeriod,
        array &$tempImagePaths
    ): void {
        $sheet->setTitle('รายละเอียดมิเตอร์');

        // Grouped once up front so the per-period date-range header (below) is an O(1) lookup
        // instead of re-scanning the whole readings collection for every month in range.
        $readingDatesByPeriod = $allReadings->filter(fn ($r) => $r->reading_date !== null)
            ->groupBy(fn ($r) => "{$r->billing_year}_{$r->billing_month}");

        $sheet->mergeCells('A1:A3');
        $sheet->setCellValue('A1', 'ลำดับที่');
        $sheet->mergeCells('B1:C3');
        $sheet->setCellValue('B1', 'รายการ');
        $sheet->mergeCells('D1:D3');
        $sheet->setCellValue('D1', 'สถานะมิเตอร์');

        $blockCols = [];
        $col = 'E';
        foreach ($periods as $i => $period) {
            $cols = [];
            for ($k = 0; $k < 5; $k++) {
                $cols[] = $col;
                $col++;
            }
            $blockCols[$i] = $cols;

            $y = $period['year'];
            $m = $period['month'];
            $monthName = self::THAI_MONTHS[$m] ?? "เดือน {$m}";

            $sheet->mergeCells("{$cols[0]}1:{$cols[4]}1");
            $sheet->setCellValue("{$cols[0]}1", "{$monthName} " . ($y + 543));

            $sheet->setCellValue("{$cols[0]}2", 'มิเตอร์ก่อน');
            $sheet->setCellValue("{$cols[1]}2", 'มิเตอร์ปัจจุบัน');
            $sheet->setCellValue("{$cols[2]}2", 'หน่วย');
            $sheet->setCellValue("{$cols[3]}2", 'ราคา/หน่วย');
            $sheet->setCellValue("{$cols[4]}2", 'รวมยอด');

            $sheet->mergeCells("{$cols[0]}3:{$cols[4]}3");
            $datesInPeriod = $readingDatesByPeriod->get("{$y}_{$m}", collect())->pluck('reading_date');
            if ($datesInPeriod->isNotEmpty()) {
                $min = $datesInPeriod->min()->format('d/m/Y');
                $max = $datesInPeriod->max()->format('d/m/Y');
                $sheet->setCellValue("{$cols[0]}3", $min === $max ? $min : "{$min} - {$max}");
            }
        }

        $lastCol = $blockCols[count($periods) - 1][4];

        $sheet->getStyle("A1:{$lastCol}3")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF'], 'name' => 'TH Sarabun New'],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '5B9BD5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
        ]);

        foreach ($blockCols as $cols) {
            $sheet->getStyle("{$cols[0]}1:{$cols[4]}1")->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('00B050');
        }

        $row = 4;
        $seq = 1;
        $isEvenDataRow = false;

        foreach ($properties as $property) {
            $pcode = $property->property_code;
            $seqWritten = false;

            foreach (['electric' => 'มิเตอร์ไฟฟ้า', 'water' => 'มิเตอร์น้ำ'] as $type => $label) {
                $meter = $property->activeMeters->firstWhere('meter_type', $type);
                if (! $meter) {
                    continue;
                }

                $headerFill = $type === 'electric' ? 'E7E6E6' : 'F2F2F2';

                $sheet->setCellValue("A{$row}", $seqWritten ? '' : $seq);
                $sheet->setCellValue("B{$row}", $pcode);
                $sheet->setCellValue("C{$row}", $label);
                $sheet->setCellValue("D{$row}", '');
                $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($headerFill);
                $sheet->getStyle("A{$row}:D{$row}")->getFont()->setBold(true);
                $seqWritten = true;
                $row++;

                $sheet->setCellValue("A{$row}", '');
                $sheet->setCellValue("B{$row}", $pcode);
                $sheet->setCellValue("C{$row}", 'เลขมิเตอร์');
                $sheet->setCellValue("D{$row}", '');

                $imagesToEmbed = [];

                foreach ($periods as $i => $period) {
                    $y = $period['year'];
                    $m = $period['month'];
                    $cols = $blockCols[$i];
                    $reading = $readingsByMeterPeriod["{$meter->id}_{$y}_{$m}"] ?? null;

                    if (! $reading) {
                        continue;
                    }

                    // (รีเซ็ต) เหลือไว้สำหรับแถวเก่าที่บันทึกก่อนตัดโหมดรีเซ็ต / แอดมินบันทึก
                    $suffix = $reading->meter_reset ? ' (รีเซ็ต)' : ($reading->meter_changed ? ' (เริ่มนับใหม่)' : '');
                    $sheet->setCellValue("{$cols[0]}{$row}", ($reading->previous_reading ?? '') . $suffix);
                    $sheet->setCellValue("{$cols[1]}{$row}", $reading->current_reading);
                    $sheet->setCellValue("{$cols[2]}{$row}", $reading->units_used);
                    $sheet->setCellValue("{$cols[3]}{$row}", $reading->price_per_unit);
                    $sheet->setCellValue("{$cols[4]}{$row}", $reading->amount);

                    if ($reading->image_path) {
                        $imagesToEmbed[$cols[0]] = $reading;
                    }
                }

                $isEvenDataRow = ! $isEvenDataRow;
                $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB($isEvenDataRow ? 'F8F9FA' : 'FFFFFF');
                $row++;

                if (! empty($imagesToEmbed)) {
                    $sheet->setCellValue("A{$row}", '');
                    $sheet->setCellValue("B{$row}", $pcode);
                    $sheet->setCellValue("C{$row}", 'รูปภาพมิเตอร์');
                    $sheet->setCellValue("D{$row}", '');

                    foreach ($imagesToEmbed as $colLetter => $reading) {
                        $this->embedMeterPhoto($sheet, $reading, "{$colLetter}{$row}", $tempImagePaths);
                    }

                    $sheet->getRowDimension($row)->setRowHeight(100);
                    $isEvenDataRow = ! $isEvenDataRow;
                    $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB($isEvenDataRow ? 'F8F9FA' : 'FFFFFF');
                    $row++;
                }
            }

            $row++;
            $seq++;
        }

        $lastDataRow = $row - 2;

        if ($lastDataRow >= 4) {
            $sheet->getStyle("A4:{$lastCol}{$lastDataRow}")->applyFromArray([
                'font' => ['size' => 11, 'name' => 'TH Sarabun New'],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D0D0D0']]],
            ]);
            $sheet->getStyle("A4:A{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B4:B{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("C4:D{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("E4:{$lastCol}{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            foreach ($blockCols as $cols) {
                $sheet->getStyle("{$cols[3]}4:{$cols[3]}{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("{$cols[4]}4:{$cols[4]}{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            }
        }

        $sheet->getColumnDimension('A')->setWidth(8);
        $sheet->getColumnDimension('B')->setWidth(12);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(15);
        foreach ($blockCols as $cols) {
            foreach ($cols as $c) {
                $sheet->getColumnDimension($c)->setWidth(14);
            }
        }

        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->getRowDimension(2)->setRowHeight(25);
        $sheet->getRowDimension(3)->setRowHeight(25);

        $sheet->freezePane('A4');
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);
    }

    private function embedMeterPhoto(Worksheet $sheet, MeterReading $reading, string $coordinate, array &$tempImagePaths): void
    {
        if (! Storage::disk('meter_storage')->exists($reading->image_path)) {
            return;
        }

        try {
            $resizedPath = $this->resizeImageForExcel(
                Storage::disk('meter_storage')->path($reading->image_path),
                400,
                400,
                85
            );
        } catch (\Throwable $e) {
            return;
        }

        if (! $resizedPath) {
            return;
        }

        $tempImagePaths[] = $resizedPath;

        (new Drawing())
            ->setPath($resizedPath)
            ->setCoordinates($coordinate)
            ->setOffsetX(5)
            ->setOffsetY(5)
            ->setWidth(120)
            ->setHeight(120)
            ->setWorksheet($sheet);
    }

    /**
     * Resize + re-encode to a fresh temp file at the given quality - ported from the legacy
     * script's own GD-based helper of the same purpose, just without the PHPExcel coupling.
     */
    private function resizeImageForExcel(string $imagePath, int $maxWidth, int $maxHeight, int $quality): ?string
    {
        if (! file_exists($imagePath)) {
            return null;
        }

        $imgInfo = @getimagesize($imagePath);
        if (! $imgInfo) {
            return null;
        }

        [$srcWidth, $srcHeight, $srcType] = $imgInfo;
        $ratio = min($maxWidth / $srcWidth, $maxHeight / $srcHeight, 1);
        $newWidth = (int) ($srcWidth * $ratio);
        $newHeight = (int) ($srcHeight * $ratio);

        $srcImg = match ($srcType) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($imagePath),
            IMAGETYPE_PNG => @imagecreatefrompng($imagePath),
            IMAGETYPE_GIF => @imagecreatefromgif($imagePath),
            default => null,
        };

        if (! $srcImg) {
            return null;
        }

        $dstImg = imagecreatetruecolor($newWidth, $newHeight);
        if ($srcType === IMAGETYPE_PNG || $srcType === IMAGETYPE_GIF) {
            imagealphablending($dstImg, false);
            imagesavealpha($dstImg, true);
            $transparent = imagecolorallocatealpha($dstImg, 255, 255, 255, 127);
            imagefilledrectangle($dstImg, 0, 0, $newWidth, $newHeight, $transparent);
        }
        imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newWidth, $newHeight, $srcWidth, $srcHeight);

        $tmpFile = tempnam(sys_get_temp_dir(), 'meterxls_');
        match ($srcType) {
            IMAGETYPE_JPEG => imagejpeg($dstImg, $tmpFile, $quality),
            IMAGETYPE_PNG => imagepng($dstImg, $tmpFile, 6),
            IMAGETYPE_GIF => imagegif($dstImg, $tmpFile),
            default => null,
        };

        imagedestroy($srcImg);
        imagedestroy($dstImg);

        return $tmpFile;
    }
}
