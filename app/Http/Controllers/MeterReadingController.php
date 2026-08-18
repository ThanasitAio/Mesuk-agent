<?php

namespace App\Http\Controllers;

use App\Models\HrProperty;
use App\Models\HrPropertyMeter;
use App\Models\MeterReading;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MeterReadingController extends Controller
{
    public function index(Request $request)
    {
        $agentCode = session('agent_code');

        $year  = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

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

        return view('meters.index', compact('rows', 'year', 'month'));
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
        foreach ($meters as $meter) {
            $previousReadings[$meter->id] = $this->findPriorReading($meter->id, $year, $month)?->current_reading;
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
            'property', 'meters', 'currentReadings', 'previousReadings', 'year', 'month',
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
            ->route('meters.index', ['year' => $year, 'month' => $month])
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
}
