<?php

namespace App\Http\Controllers;

use App\Models\HrProperty;
use App\Models\HrPropertyMeter;
use App\Models\MeterReading;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class MeterReadingController extends Controller
{
    private const EXPORT_MAX_MONTHS = 24;

    private const THAI_MONTHS = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม',
    ];

    public function index(Request $request)
    {
        $agentCode = session('agent_code');

        $year  = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);
        $searchQuery = (string) $request->query('q', '');

        // ไม่กรอง is_utility_metering_enabled ที่นี่แล้ว - แสดงทุกทรัพย์สินที่มีมิเตอร์ตั้งค่าไว้
        // เสมอไม่ว่าสถานะพื้นที่จะเป็นอะไร ฝั่ง view จะปิดการบันทึกเองถ้าระบบมิเตอร์ไม่ได้เปิด
        $properties = HrProperty::where('manager_agent_code', $agentCode)
            ->with(['activeMeters', 'propertyStatus', 'primaryImageMedia', 'activeBooking.customer'])
            ->get()
            ->filter(fn ($p) => $p->activeMeters->isNotEmpty())
            ->values();

        // Filtered by rent_year/rent_month (the ค่าเช่า period a reading gets billed
        // together with), not billing_year/billing_month (the calendar month the meter
        // was actually read) - agents think in terms of "which rent invoice", not the
        // technical reading month.
        $readingsByProperty = MeterReading::whereIn('property_id', $properties->pluck('id'))
            ->forRentPeriod($year, $month)
            ->get()
            ->groupBy('property_id');

        $rows = $properties->map(function ($property) use ($readingsByProperty, $year, $month) {
            $meterCount     = $property->activeMeters->count();
            $waterCount     = $property->activeMeters->where('meter_type', 'water')->count();
            $electricCount  = $property->activeMeters->where('meter_type', 'electric')->count();
            $periodReadings = $readingsByProperty->get($property->id, collect());

            $waterAmt    = $periodReadings->where('meter_type', 'water')->sum('amount');
            $electricAmt = $periodReadings->where('meter_type', 'electric')->sum('amount');
            $commonFee   = (float) ($property->common_fee_per_month ?? 0);

            return (object) [
                'property'         => $property,
                'meter_count'      => $meterCount,
                'water_count'      => $waterCount,
                'electric_count'   => $electricCount,
                'recorded_count'   => $periodReadings->count(),
                'water_units'      => $periodReadings->where('meter_type', 'water')->sum('units_used'),
                'electric_units'   => $periodReadings->where('meter_type', 'electric')->sum('units_used'),
                'water_amount'     => $waterAmt,
                'electric_amount'  => $electricAmt,
                'total_amount'     => $waterAmt + $electricAmt + $commonFee,
                // $year/$month on this page IS the rent period (rows are grouped via
                // forRentPeriod above), unlike show() where rentYear/rentMonth is derived
                // separately from the calendar billing period.
                'already_invoiced' => $this->hasIssuedUtilityInvoice($property->id, $year, $month),
            ];
        });

        logSystem(
            userType: 'agent',
            userId: session('agent_id'),
            module: 'Meter',
            action: 'VIEW',
            description: "ดูรายการบันทึกมิเตอร์ งวด {$month}/{$year}"
        );

        return view('meters.index', compact('rows', 'year', 'month', 'searchQuery'));
    }

    public function exportForm(Request $request)
    {
        $properties = $this->eligibleProperties(session('agent_code'));

        return view('meters.export', compact('properties'));
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
            return back()->with('error', 'งวดสิ้นสุดต้องไม่ก่อนงวดเริ่มต้น')->withInput();
        }
        if ($spanMonths > self::EXPORT_MAX_MONTHS) {
            return back()
                ->with('error', 'ช่วงเวลาที่เลือกยาวเกินไป (สูงสุด ' . self::EXPORT_MAX_MONTHS . ' เดือน) กรุณาเลือกช่วงที่สั้นลง')
                ->withInput();
        }

        $properties = $this->eligibleProperties($agentCode, $propertyCode);

        if ($properties->isEmpty()) {
            return back()->with('error', 'ไม่พบทรัพย์สินที่ตรงกับเงื่อนไข')->withInput();
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

            $stream = fopen('php://temp', 'r+');
            (new Xlsx($spreadsheet))->save($stream);
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
        foreach ($meters as $meter) {
            $priorReading = $this->findPriorReading($meter->id, $year, $month);
            $previousReadings[$meter->id] = $priorReading?->current_reading;
            $previousReadingDates[$meter->id] = $priorReading?->reading_date?->format('Y-m-d');
        }

        $existingRentPeriod = $currentReadings->first(fn ($r) => $r->rent_year && $r->rent_month);
        $rentYear  = $existingRentPeriod->rent_year  ?? $year;
        $rentMonth = $existingRentPeriod->rent_month ?? $month;

        $allRecorded     = $currentReadings->count() === $meters->count();
        $allConfirmed    = $allRecorded && $currentReadings->every(fn ($r) => $r->status === 'confirmed');
        $alreadyInvoiced = $this->hasIssuedUtilityInvoice($property->id, $rentYear, $rentMonth);

        logSystem(
            userType: 'agent',
            userId: session('agent_id'),
            module: 'Meter',
            action: 'VIEW',
            description: "ดูข้อมูลมิเตอร์ {$property->title} งวด {$month}/{$year}"
        );

        return view('meters.show', compact(
            'property', 'meters', 'currentReadings', 'previousReadings', 'previousReadingDates', 'year', 'month',
            'rentYear', 'rentMonth', 'allRecorded', 'allConfirmed', 'alreadyInvoiced'
        ));
    }

    public function store(Request $request, HrProperty $property)
    {
        if ($property->manager_agent_code !== session('agent_code')) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงอสังหาริมทรัพย์นี้');
        }

        $validated = $request->validate([
            'billing_year'                        => 'required|integer|min:2020|max:' . (now()->year + 1),
            'billing_month'                        => 'required|integer|between:1,12',
            'rent_year'                            => 'required|integer|min:2020|max:' . (now()->year + 1),
            'rent_month'                            => 'required|integer|between:1,12',
            'readings'                             => 'required|array|min:1',
            'readings.*.current_reading'           => 'nullable|integer|min:0',
            'readings.*.reading_date'              => 'nullable|date|before_or_equal:today',
            'readings.*.previous_reading'          => 'nullable|integer|min:0',
            'readings.*.meter_reset'               => 'sometimes|boolean',
            'readings.*.meter_changed'             => 'sometimes|boolean',
            'readings.*.old_meter_final_reading'   => 'nullable|integer|min:0',
            'readings.*.new_meter_start_reading'   => 'nullable|integer|min:0',
            'readings.*.meter_max_value'           => 'nullable|integer|min:1',
            'readings.*.image'                     => 'nullable|file|mimes:jpg,jpeg,png|max:10240',
            'remark'                               => 'nullable|string|max:1000',
        ], [
            'readings.required'                   => 'กรุณากรอกข้อมูลมิเตอร์อย่างน้อย 1 รายการ',
            'readings.*.reading_date.before_or_equal' => 'วันที่อ่านมิเตอร์ต้องไม่เกินวันนี้',
            'readings.*.image.mimes'              => 'ไฟล์รูปต้องเป็น JPG หรือ PNG เท่านั้น',
            'readings.*.image.max'                => 'ขนาดรูปต้องไม่เกิน 10MB',
        ]);

        $year  = (int) $validated['billing_year'];
        $month = (int) $validated['billing_month'];
        $rentYear  = (int) $validated['rent_year'];
        $rentMonth = (int) $validated['rent_month'];

        if ($this->hasIssuedUtilityInvoice($property->id, $rentYear, $rentMonth)) {
            return back()->with('error', 'ออกใบแจ้งหนี้น้ำ/ไฟของงวดนี้ไปแล้ว ไม่สามารถแก้ไขข้อมูลมิเตอร์ได้')->withInput();
        }

        // IDOR guard: re-verify every submitted meter id truly belongs to THIS
        // already-ownership-verified property, never trust the posted array keys.
        $activeMeterIds = $property->activeMeters()->pluck('id');
        $submittedIds   = array_map('intval', array_keys($validated['readings']));
        if (! empty(array_diff($submittedIds, $activeMeterIds->all()))) {
            return back()->with('error', 'พบข้อมูลมิเตอร์ที่ไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง')->withInput();
        }

        $meters = HrPropertyMeter::whereIn('id', $submittedIds)->get()->keyBy('id');

        $remark = $validated['remark'] ?? null;

        $entries = [];
        foreach ($validated['readings'] as $meterId => $data) {
            $meterId = (int) $meterId;
            $meter   = $meters->get($meterId);

            $meterReset   = (bool) ($data['meter_reset'] ?? false);
            $meterChanged = (bool) ($data['meter_changed'] ?? false);

            if ($meterReset && $meterChanged) {
                return back()->with('error', "มิเตอร์{$meter->type_label}: เลือกได้แค่ \"รีเซ็ต\" หรือ \"เปลี่ยนมิเตอร์\" อย่างใดอย่างหนึ่ง")->withInput();
            }

            if ($meterChanged && (blank($data['old_meter_final_reading'] ?? null) || blank($data['new_meter_start_reading'] ?? null))) {
                return back()->with('error', "มิเตอร์{$meter->type_label}: กรุณาระบุเลขมิเตอร์เก่าสุดท้ายและมิเตอร์ใหม่เริ่มต้นให้ครบ")->withInput();
            }

            $priorRow = $this->findPriorReading($meterId, $year, $month);

            // No forced required fields - a blank reading defaults to 0 so agents can
            // save a partial period and fill in exact numbers later.
            $previousReading = $priorRow
                ? (int) $priorRow->current_reading
                : (int) ($data['previous_reading'] ?? 0);

            $currentReading = (int) ($data['current_reading'] ?? 0);

            if (! $meterReset && ! $meterChanged && $currentReading < $previousReading) {
                return back()
                    ->with('error', "มิเตอร์{$meter->type_label}: เลขมิเตอร์ปัจจุบัน ({$currentReading}) น้อยกว่าเดือนก่อน ({$previousReading}) กรุณาตรวจสอบ หรือติ๊ก \"รีเซ็ต\"/\"เปลี่ยนมิเตอร์\" ถ้าถูกต้อง")
                    ->withInput();
            }

            $calc = MeterReading::calculateUnits([
                'previous_reading'        => $previousReading,
                'current_reading'         => $currentReading,
                'meter_reset'             => $meterReset,
                'meter_changed'           => $meterChanged,
                'old_meter_final_reading' => $data['old_meter_final_reading'] ?? null,
                'new_meter_start_reading' => $data['new_meter_start_reading'] ?? null,
                'meter_max_value'         => $data['meter_max_value'] ?? null,
            ]);

            // เคยบันทึกงวดนี้ไว้แล้ว -> ยึดราคาต่อหน่วยที่บันทึกไว้ครั้งล่าสุด ไม่ดึงจาก
            // hr_property_meters.price_per_unit (master) ซ้ำอีก เพื่อไม่ให้ราคาของงวดที่บันทึก
            // แล้วขยับตามเวลาที่แอดมินแก้ราคา master ทีหลัง ใช้ราคา master เฉพาะการบันทึกครั้งแรกเท่านั้น
            $existingForPeriod = MeterReading::where('property_meter_id', $meterId)
                ->forPeriod($year, $month)
                ->first();

            $pricePerUnit = $existingForPeriod && $existingForPeriod->price_per_unit !== null
                ? $existingForPeriod->price_per_unit
                : $meter->price_per_unit;

            $entries[$meterId] = [
                'meter'                    => $meter,
                'previous_reading'         => $previousReading,
                'current_reading'          => $currentReading,
                'reading_date'             => $data['reading_date'] ?? null,
                'meter_reset'              => $meterReset,
                'meter_changed'            => $meterChanged,
                'old_meter_final_reading'  => $data['old_meter_final_reading'] ?? null,
                'new_meter_start_reading'  => $data['new_meter_start_reading'] ?? null,
                'meter_max_value'          => $data['meter_max_value'] ?? null,
                'units_used'               => $calc['units_used'],
                'price_per_unit'           => $pricePerUnit,
                'image'                    => $request->file("readings.$meterId.image"),
                'new_image_path'           => null,
            ];
        }

        // Write new files to disk before opening the transaction, so a storage failure
        // surfaces before any DB write. Old replaced files are deleted only after commit.
        foreach ($entries as &$entry) {
            if ($entry['image']) {
                $entry['new_image_path'] = $entry['image']->store('readings/' . $property->id, 'meter_storage');
            }
        }
        unset($entry);

        $oldImagePaths = [];
        $hadExisting   = false;

        DB::transaction(function () use ($entries, $property, $year, $month, $rentYear, $rentMonth, $remark, &$oldImagePaths, &$hadExisting) {
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

                MeterReading::updateOrCreate(
                    [
                        'property_meter_id' => $meter->id,
                        'billing_year'      => $year,
                        'billing_month'     => $month,
                    ],
                    [
                        'property_id'              => $property->id,
                        'meter_type'               => $meter->meter_type,
                        'rent_year'                => $rentYear,
                        'rent_month'               => $rentMonth,
                        'previous_reading'         => $entry['previous_reading'],
                        'current_reading'          => $entry['current_reading'],
                        'reading_date'             => $entry['reading_date'],
                        'meter_reset'              => $entry['meter_reset'],
                        'meter_changed'            => $entry['meter_changed'],
                        'old_meter_final_reading'  => $entry['old_meter_final_reading'],
                        'new_meter_start_reading'  => $entry['new_meter_start_reading'],
                        'meter_max_value'          => $entry['meter_max_value'],
                        'units_used'               => $entry['units_used'],
                        'price_per_unit'           => $entry['price_per_unit'],
                        'amount'                   => $entry['units_used'] * (float) $entry['price_per_unit'],
                        'image_path'               => $entry['new_image_path'] ?: ($existing->image_path ?? null),
                        'remark'                   => $remark,
                        // บันทึก = ยืนยันทันที (ไม่มีขั้นตอนกดยืนยันแยกอีกต่อไป) - ตามที่ผู้ดูแลระบบขอ
                        // เพื่อลดขั้นตอนของผู้บริหารโครงการ ยังแก้ไขซ้ำได้ตามปกติจนกว่าจะออกใบแจ้งหนี้แล้ว
                        // (ดู hasIssuedUtilityInvoice() guard ด้านบน)
                        'status'                   => 'confirmed',
                        'confirmed_at'             => now(),
                        'confirmed_by'             => session('agent_id'),
                        'created_by'               => $existing->created_by ?? session('agent_id'),
                        'updated_by'               => session('agent_id'),
                    ]
                );
            }
        });

        foreach ($oldImagePaths as $path) {
            Storage::disk('meter_storage')->delete($path);
        }

        logSystem(
            userType: 'agent',
            userId: session('agent_id'),
            module: 'Meter',
            action: $hadExisting ? 'UPDATE' : 'CREATE',
            description: "บันทึกมิเตอร์ {$property->title} งวด {$month}/{$year}"
        );

        return redirect()
            ->route('meters.index', array_filter([
                'year'  => $year,
                'month' => $month,
                'q'     => $property->property_code,
            ]))
            ->with('success', 'บันทึกและยืนยันข้อมูลมิเตอร์เรียบร้อยแล้ว');
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

        $imagePaths = $readings->pluck('image_path')->filter();

        $deleteQuery = MeterReading::forProperty($property->id);
        $deleteQuery = $byRentPeriod ? $deleteQuery->forRentPeriod($year, $month) : $deleteQuery->forPeriod($year, $month);
        $deleteQuery->forceDelete();

        foreach ($imagePaths as $path) {
            Storage::disk('meter_storage')->delete($path);
        }

        logSystem(
            userType: 'agent',
            userId: session('agent_id'),
            module: 'Meter',
            action: 'DELETE',
            description: "ลบข้อมูลมิเตอร์ {$property->title} งวด {$month}/{$year}"
        );

        return back()->with('success', 'ลบข้อมูลมิเตอร์เรียบร้อยแล้ว ต้องบันทึกใหม่');
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
            ->route('meters.show', ['property' => $property->id, 'year' => $year, 'month' => $month])
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

    public function viewImage(MeterReading $reading)
    {
        HrProperty::where('id', $reading->property_id)
            ->where('manager_agent_code', session('agent_code'))
            ->firstOrFail();

        abort_if(! $reading->image_path, 404, 'ไม่พบรูปภาพ');
        abort_if(! Storage::disk('meter_storage')->exists($reading->image_path), 404, 'ไม่พบรูปภาพ');

        return response()->file(Storage::disk('meter_storage')->path($reading->image_path));
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
            $datesInPeriod = $allReadings->where('billing_year', $y)->where('billing_month', $m)
                ->pluck('reading_date')->filter();
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

                    $suffix = $reading->meter_reset ? ' (รีเซ็ต)' : ($reading->meter_changed ? ' (เปลี่ยนมิเตอร์)' : '');
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
