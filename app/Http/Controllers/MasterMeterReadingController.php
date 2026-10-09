<?php

namespace App\Http\Controllers;

use App\Models\HrMasterMeter;
use App\Models\HrMasterMeterReading;
use App\Models\HrPropertyGroup;
use App\Models\MeterReading;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * บันทึกมิเตอร์หลัก (ผู้บริหารโครงการ) - ล้อ happyest AdminMasterMeterReadingController ทุกขั้นตอน
 * (สูตรหน่วย, ดึงเลขเดือนก่อนจากเดือน-1 เท่านั้น, เก็บ snapshot ตอนจดครั้งแรก, เขียนรูปก่อน transaction,
 * ลบงวดแบบ hard delete) เขียนลงตารางเดียวกับแอดมิน (hr_master_meter_readings)
 * ต่างจากแอดมินโดยตั้งใจ: ไม่มีโหมด "มิเตอร์รีเซ็ต" - ใช้ "มิเตอร์เริ่มนับใหม่" (meter_changed) อย่างเดียว
 *
 * ต่างจากหน้าแอดมินแค่ขอบเขต: ตัวแทนเห็น บันทึก และลบได้เฉพาะมิเตอร์หลักที่ตัวเองเป็น "ผู้จด"
 * ตาม HrMasterMeter::resolveRecorder() (แอดมินกำหนด) ไม่ใช่ทั้งกลุ่ม
 */
class MasterMeterReadingController extends Controller
{
    /**
     * เฉพาะผู้บริหารโครงการ (session agent_is_manager - ค่าเดียวกับที่ใช้ซ่อนเมนู) - กันพิมพ์ URL เข้าตรง
     */
    private function authorize(): void
    {
        if (! session('agent_is_manager')) {
            abort(403, 'หน้านี้สำหรับผู้บริหารโครงการเท่านั้น');
        }
    }

    public function index(Request $request)
    {
        $this->authorize();

        $agentCode = (string) session('agent_code');

        $year  = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        $assigned    = HrMasterMeter::forRecorder($agentCode);
        $rowsByGroup = HrMasterMeter::periodRows($assigned['record'], $year, $month);

        $rows = $rowsByGroup->map(function ($meters, $groupId) {
            $recordedCount = $meters->filter(fn ($m) => $m->reading)->count();

            // หน่วยรวมรายประเภท: null = ไม่มีมิเตอร์ประเภทนี้ / ยังไม่จดสักตัว
            $units = [];
            foreach (['electric', 'water'] as $type) {
                $typeMeters = $meters->where('meter_type', $type);
                $recorded   = $typeMeters->filter(fn ($m) => $m->reading);
                $units[$type] = $typeMeters->isEmpty() ? null : [
                    'units'    => $recorded->isEmpty() ? null : (float) $recorded->sum(fn ($m) => $m->reading->units_used),
                    'recorded' => $recorded->count(),
                    'total'    => $typeMeters->count(),
                ];
            }

            return (object) [
                'group_id'       => (int) $groupId,
                'group_name'     => $meters->first()->meter->propertyGroup?->property_group_name ?? "กลุ่ม #{$groupId}",
                'property_codes' => $meters->flatMap(fn ($m) => $m->properties->pluck('code'))->filter()->unique()->values()->all(),
                'meter_count'    => $meters->count(),
                'water_count'    => $meters->where('meter_type', 'water')->count(),
                'electric_count' => $meters->where('meter_type', 'electric')->count(),
                'recorded_count' => $recordedCount,
                'record_status'  => $recordedCount === 0 ? 'unrecorded' : ($recordedCount === $meters->count() ? 'recorded' : 'partial'),
                'units'          => $units,
                'latest_reading' => $meters->pluck('reading')->filter()->sortByDesc('updated_at')->first(),
            ];
        })->sortBy('group_name', SORT_NATURAL)->values();

        $pendingChoice = $assigned['choose'];

        logSystem(
            userType: 'agent',
            userId: session('agent_id'),
            module: 'MasterMeter',
            action: 'VIEW',
            description: "ดูรายการบันทึกมิเตอร์หลัก งวด {$month}/{$year}"
        );

        return view('master-meters.index', compact('rows', 'year', 'month', 'pendingChoice'));
    }

    public function show(Request $request, HrPropertyGroup $group)
    {
        $this->authorize();

        $agentCode = (string) session('agent_code');

        $year  = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        $myMeters = HrMasterMeter::forRecorder($agentCode, $group->property_group_id)['record'];
        abort_if($myMeters->isEmpty(), 403, 'คุณไม่ได้เป็นผู้จดมิเตอร์หลักของกลุ่มนี้');

        $meters = HrMasterMeter::periodRows($myMeters, $year, $month)->get($group->property_group_id, collect());

        $currentReadings = $meters->pluck('reading')->filter()->keyBy('master_meter_id');

        $previousReadings = [];
        $previousReadingDates = [];
        foreach ($meters as $meter) {
            $priorReading = $this->findPriorReading($meter->id, $year, $month);
            $previousReadings[$meter->id] = $priorReading?->current_reading;
            $previousReadingDates[$meter->id] = $priorReading?->reading_date?->format('Y-m-d');
        }

        $allRecorded     = $currentReadings->count() === $meters->count();
        $propertyCount   = $group->properties()->count();
        $groupMeterCount = HrMasterMeter::active()->where('property_group_id', $group->property_group_id)->count();
        $remark          = $currentReadings->first()?->remark;

        // แถวของงวดนี้ที่คนอื่น (ผู้จดคนอื่น/แอดมิน) เป็นคนจด - ลบงวดจากหน้านี้ไม่แตะ แจ้งในกล่องยืนยันลบ
        $othersRecordedCount = HrMasterMeterReading::forGroup($group->property_group_id)
            ->forPeriod($year, $month)
            ->whereNotIn('master_meter_id', $myMeters->pluck('id'))
            ->count();

        logSystem(
            userType: 'agent',
            userId: session('agent_id'),
            module: 'MasterMeter',
            action: 'VIEW',
            description: "ดูข้อมูลมิเตอร์หลัก กลุ่ม {$group->property_group_name} งวด {$month}/{$year}"
        );

        return view('master-meters.show', compact(
            'group', 'meters', 'currentReadings', 'previousReadings', 'previousReadingDates', 'year', 'month',
            'allRecorded', 'propertyCount', 'groupMeterCount', 'remark', 'othersRecordedCount'
        ));
    }

    public function store(Request $request, HrPropertyGroup $group)
    {
        $this->authorize();

        $agentCode = (string) session('agent_code');

        $validated = $request->validate([
            'billing_year'                       => 'required|integer|min:2020|max:' . (now()->year + 1),
            'billing_month'                      => 'required|integer|between:1,12',
            'readings'                           => 'required|array|min:1',
            'readings.*.current_reading'         => 'nullable|integer|min:0',
            'readings.*.reading_date'            => 'nullable|date|before_or_equal:today',
            'readings.*.previous_reading'        => 'nullable|integer|min:0',
            'readings.*.meter_changed'           => 'sometimes|boolean',
            'readings.*.old_meter_final_reading' => 'nullable|integer|min:0',
            'readings.*.new_meter_start_reading' => 'nullable|integer|min:0',
            'readings.*.image'                   => 'nullable|file|mimes:jpg,jpeg,png|max:10240',
            'remark'                             => 'nullable|string|max:1000',
        ], [
            'readings.required'                       => 'กรุณากรอกข้อมูลมิเตอร์อย่างน้อย 1 รายการ',
            'readings.*.reading_date.before_or_equal' => 'วันที่อ่านมิเตอร์ต้องไม่เกินวันนี้',
            'readings.*.image.mimes'                  => 'ไฟล์รูปต้องเป็น JPG หรือ PNG เท่านั้น',
            'readings.*.image.max'                    => 'ขนาดรูปต้องไม่เกิน 10MB',
        ]);

        $year  = (int) $validated['billing_year'];
        $month = (int) $validated['billing_month'];

        // IDOR guard: key ของ readings[] ต้องเป็นมิเตอร์หลักที่เปิดใช้งานของกลุ่มนี้ "และ" ตัวแทนคนนี้เป็นผู้จดเท่านั้น
        // - ตรวจผู้จดใหม่ตอนบันทึกเสมอ เผื่อแอดมินเปลี่ยนผู้จด/ผู้บริหารทรัพย์ระหว่างที่หน้าเปิดค้างไว้
        $meters = HrMasterMeter::forRecorder($agentCode, $group->property_group_id)['record']->keyBy('id');
        abort_if($meters->isEmpty(), 403, 'คุณไม่ได้เป็นผู้จดมิเตอร์หลักของกลุ่มนี้');

        $submittedIds = array_map('intval', array_keys($validated['readings']));
        if (! empty(array_diff($submittedIds, $meters->keys()->all()))) {
            return back()->with('error', 'พบข้อมูลมิเตอร์ที่ไม่ถูกต้อง หรือคุณไม่ได้เป็นผู้จดมิเตอร์บางตัวแล้ว กรุณาโหลดหน้าใหม่')->withInput();
        }

        $remark = $validated['remark'] ?? null;

        $existingRows = HrMasterMeterReading::whereIn('master_meter_id', $submittedIds)
            ->forPeriod($year, $month)
            ->get()
            ->keyBy('master_meter_id');

        $entries = [];
        foreach ($validated['readings'] as $meterId => $data) {
            $meterId = (int) $meterId;
            $meter   = $meters->get($meterId);
            // ชื่อเดียวกับที่หน้าบันทึกแสดง (งวดที่จดแล้วแสดงชื่อตอนบันทึก)
            $label = $existingRows->get($meterId)?->master_meter_name ?: $meter->name;

            // "มิเตอร์เริ่มนับใหม่" (meter_changed) ใช้แทนทั้งรีเซ็ตและเปลี่ยนมิเตอร์ - ไม่มีโหมดรีเซ็ตแยกแล้ว
            // จึงบันทึก meter_reset = false เสมอ (แถวเก่าที่เคยรีเซ็ตไว้ บันทึกซ้ำแล้วจะถูกคำนวณใหม่ตามนี้)
            $meterChanged = (bool) ($data['meter_changed'] ?? false);

            if ($meterChanged && (blank($data['old_meter_final_reading'] ?? null) || blank($data['new_meter_start_reading'] ?? null))) {
                return back()->with('error', "{$label}: กรุณาระบุเลขสุดท้ายและเลขเริ่มใหม่ของ \"มิเตอร์เริ่มนับใหม่\" ให้ครบ")->withInput();
            }

            $priorRow = $this->findPriorReading($meterId, $year, $month);

            $previousReading = $priorRow
                ? (int) $priorRow->current_reading
                : (int) ($data['previous_reading'] ?? 0);

            $currentReading = (int) ($data['current_reading'] ?? 0);

            if (! $meterChanged && $currentReading < $previousReading) {
                return back()
                    ->with('error', "{$label}: เลขมิเตอร์ปัจจุบัน ({$currentReading}) น้อยกว่าเดือนก่อน ({$previousReading}) กรุณาตรวจสอบ หรือกด \"มิเตอร์เริ่มนับใหม่\" ถ้ามิเตอร์วนกลับเป็น 0 หรือเปลี่ยนมิเตอร์ใหม่")
                    ->withInput();
            }

            $calc = MeterReading::calculateUnits([
                'previous_reading'        => $previousReading,
                'current_reading'         => $currentReading,
                'meter_reset'             => false,
                'meter_changed'           => $meterChanged,
                'old_meter_final_reading' => $data['old_meter_final_reading'] ?? null,
                'new_meter_start_reading' => $data['new_meter_start_reading'] ?? null,
                'meter_max_value'         => null,
            ]);

            $entries[$meterId] = [
                'meter'                   => $meter,
                'previous_reading'        => $previousReading,
                'current_reading'         => $currentReading,
                'reading_date'            => $data['reading_date'] ?? null,
                'meter_reset'             => false,
                'meter_changed'           => $meterChanged,
                'old_meter_final_reading' => $data['old_meter_final_reading'] ?? null,
                'new_meter_start_reading' => $data['new_meter_start_reading'] ?? null,
                'meter_max_value'         => null,
                'units_used'              => $calc['units_used'],
                'image'                   => $request->file("readings.$meterId.image"),
                'new_image_path'          => null,
            ];
        }

        // เขียนไฟล์ใหม่ก่อนเปิด transaction ให้ error จากการเขียนไฟล์โผล่ก่อนเขียน DB - ไฟล์เก่าที่ถูกแทนที่
        // ลบหลัง commit สำเร็จเท่านั้น (เหมือนแอดมิน) - path เดียวกับแอดมิน แอดมินจึงเปิดรูปที่ตัวแทนอัปโหลดได้
        $photoDisk = HrMasterMeter::photoDisk();
        foreach ($entries as &$entry) {
            if ($entry['image']) {
                $entry['new_image_path'] = $photoDisk->putFile('master-meters/' . $group->property_group_id, $entry['image']);
            }
        }
        unset($entry);

        $oldImagePaths = [];
        $hadExisting   = false;

        DB::transaction(function () use ($entries, $group, $year, $month, $remark, &$oldImagePaths, &$hadExisting) {
            foreach ($entries as $entry) {
                $meter    = $entry['meter'];
                $existing = HrMasterMeterReading::where('master_meter_id', $meter->id)
                    ->forPeriod($year, $month)
                    ->first();

                if ($existing) {
                    $hadExisting = true;
                    if ($entry['new_image_path'] && $existing->image_path) {
                        $oldImagePaths[] = $existing->image_path;
                    }
                }

                HrMasterMeterReading::updateOrCreate(
                    [
                        'master_meter_id' => $meter->id,
                        'billing_year'    => $year,
                        'billing_month'   => $month,
                    ],
                    [
                        'property_group_id'       => $group->property_group_id,
                        'meter_type'              => $meter->meter_type,
                        // ข้อมูลมิเตอร์หลักตอนบันทึก - เก็บตอนจดครั้งแรก แก้เลขภายหลังไม่ดึงข้อมูลหลักใหม่มาทับ
                        'master_meter_name'       => $existing->master_meter_name ?? $meter->name,
                        'linked_properties'       => $existing->linked_properties ?? HrMasterMeterReading::snapshotProperties($meter),
                        'previous_reading'        => $entry['previous_reading'],
                        'current_reading'         => $entry['current_reading'],
                        'reading_date'            => $entry['reading_date'],
                        'meter_reset'             => $entry['meter_reset'],
                        'meter_changed'           => $entry['meter_changed'],
                        'old_meter_final_reading' => $entry['old_meter_final_reading'],
                        'new_meter_start_reading' => $entry['new_meter_start_reading'],
                        'meter_max_value'         => $entry['meter_max_value'],
                        'units_used'              => $entry['units_used'],
                        'image_path'              => $entry['new_image_path'] ?: ($existing->image_path ?? null),
                        'remark'                  => $remark,
                        // created_by/updated_by = hr_admins.id (หน้าแอดมิน join ไปตาราง admin) - ห้ามใส่ agent_id
                        // ไม่งั้นจะโชว์เป็นชื่อแอดมินที่ id ตรงกันโดยบังเอิญ ตัวแทนคนไหนบันทึกดูได้จาก ag_logs
                        'created_by'              => $existing->created_by ?? null,
                        'updated_by'              => null,
                    ]
                );
            }
        });

        $photoDisk->delete($oldImagePaths);

        logSystem(
            userType: 'agent',
            userId: session('agent_id'),
            module: 'MasterMeter',
            action: $hadExisting ? 'UPDATE' : 'CREATE',
            description: sprintf(
                'บันทึกมิเตอร์หลัก กลุ่ม %s งวด %d/%d: %s',
                $group->property_group_name,
                $month,
                $year,
                collect($entries)->map(fn ($e) => "{$e['meter']->name} {$e['previous_reading']}->{$e['current_reading']} ({$e['units_used']} หน่วย)")->implode(', ')
            )
        );

        return redirect()
            ->route('master-meters.index', ['year' => $year, 'month' => $month])
            ->with('success', 'บันทึกข้อมูลมิเตอร์หลักเรียบร้อยแล้ว');
    }

    /**
     * ลบข้อมูลมิเตอร์หลักของงวดนี้ "เฉพาะตัวที่ตัวแทนคนนี้เป็นผู้จด" - hard delete แบบเดียวกับแอดมิน เพราะ unique key
     * (master_meter_id, billing_year, billing_month) จะชนกับแถวที่บันทึกใหม่ แถวของมิเตอร์ที่คนอื่นจดไม่แตะ
     */
    public function destroy(Request $request, HrPropertyGroup $group)
    {
        $this->authorize();

        $agentCode = (string) session('agent_code');

        $year  = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        $myMeterIds = HrMasterMeter::forRecorder($agentCode, $group->property_group_id)['record']->pluck('id');
        abort_if($myMeterIds->isEmpty(), 403, 'คุณไม่ได้เป็นผู้จดมิเตอร์หลักของกลุ่มนี้');

        $readings = HrMasterMeterReading::forGroup($group->property_group_id)
            ->forPeriod($year, $month)
            ->whereIn('master_meter_id', $myMeterIds)
            ->get();

        if ($readings->isEmpty()) {
            return back()->with('error', 'ไม่พบข้อมูลมิเตอร์หลักของงวดนี้');
        }

        $imagePaths = $readings->pluck('image_path')->filter()->all();

        HrMasterMeterReading::whereIn('id', $readings->pluck('id'))->delete();

        HrMasterMeter::photoDisk()->delete($imagePaths);

        logSystem(
            userType: 'agent',
            userId: session('agent_id'),
            module: 'MasterMeter',
            action: 'DELETE',
            description: sprintf(
                'ลบข้อมูลมิเตอร์หลัก กลุ่ม %s งวด %d/%d: %s',
                $group->property_group_name,
                $month,
                $year,
                $readings->map(fn ($r) => "{$r->master_meter_name} {$r->previous_reading}->{$r->current_reading}")->implode(', ')
            )
        );

        return redirect()
            ->route('master-meters.index', ['year' => $year, 'month' => $month])
            ->with('success', 'ลบข้อมูลมิเตอร์หลักเรียบร้อยแล้ว ต้องบันทึกใหม่');
    }

    public function viewImage(HrMasterMeterReading $reading)
    {
        $this->authorize();

        // เปิดได้เฉพาะรูปของมิเตอร์หลักที่ตัวแทนคนนี้เป็นผู้จดอยู่ตอนนี้
        $isMine = HrMasterMeter::forRecorder((string) session('agent_code'), $reading->property_group_id)['record']
            ->contains('id', $reading->master_meter_id);
        abort_unless($isMine, 403, 'คุณไม่มีสิทธิ์ดูรูปภาพนี้');

        $disk = HrMasterMeter::photoDisk();
        abort_if(! $reading->image_path, 404, 'ไม่พบรูปภาพ');
        abort_if(! $disk->exists($reading->image_path), 404, 'ไม่พบรูปภาพ');

        return response()->file($disk->path($reading->image_path));
    }

    /**
     * แถวของมิเตอร์หลักตัวนี้จากเดือนก่อนหน้าพอดี - ดูแค่เดือน-1 เหมือนแอดมินและ findPriorReading() ของมิเตอร์ย่อย
     */
    private function findPriorReading(int $masterMeterId, int $year, int $month): ?HrMasterMeterReading
    {
        $prevMonth = $month - 1;
        $prevYear  = $year;
        if ($prevMonth < 1) {
            $prevMonth = 12;
            $prevYear--;
        }

        return HrMasterMeterReading::where('master_meter_id', $masterMeterId)
            ->forPeriod($prevYear, $prevMonth)
            ->first();
    }
}
