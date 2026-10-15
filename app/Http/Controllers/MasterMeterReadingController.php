<?php

namespace App\Http\Controllers;

use App\Models\HrMasterMeter;
use App\Models\HrMasterMeterReading;
use App\Models\HrPropertyGroup;
use App\Models\MeterReading;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * บันทึกมิเตอร์หลัก (ผู้บริหารโครงการ) - ล้อ happyest AdminMasterMeterReadingController ทุกขั้นตอน
 * (สูตรหน่วย, ดึงเลขเดือนก่อนจากเดือน-1 เท่านั้น, เก็บ snapshot ตอนจดครั้งแรก, เขียนรูปก่อน transaction,
 * ลบงวดแบบ hard delete) เขียนลงตารางเดียวกับแอดมิน (hr_master_meter_readings)
 * กติกากันกรอกผิด (ครบหลักพอดี, ห้ามน้อยกว่าเดือนก่อน ฯลฯ) ใช้ MeterReading::checkInput() ตัวเดียวกับมิเตอร์ย่อย
 * ทั้งแอดมินและตัวแทนไม่มีโหมด "มิเตอร์รีเซ็ต" แล้ว - ใช้ "มิเตอร์เริ่มนับใหม่" (meter_changed) อย่างเดียว
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
        $previousUnits = [];
        $suggestedPrevious = [];
        $nextReadings = [];
        foreach ($meters as $meter) {
            $priorReading = $this->findPriorReading($meter->id, $year, $month);
            $previousReadings[$meter->id] = $priorReading?->current_reading;
            $previousReadingDates[$meter->id] = $priorReading?->reading_date?->format('Y-m-d');
            $previousUnits[$meter->id] = $priorReading?->units_used;

            // เดือนก่อนไม่ได้จด: เสนอเลขล่าสุดของงวดที่เก่ากว่าเป็นค่าเริ่มต้น (สีเหลือง แก้ได้) - เหมือนแอดมินและมิเตอร์ย่อย
            if (! $priorReading && ! $meter->reading) {
                $earlier = $this->findLatestEarlierReading($meter->id, $year, $month);
                if ($earlier) {
                    $suggestedPrevious[$meter->id] = [
                        'value'  => (int) $earlier->current_reading,
                        'period' => MeterReading::periodLabel((int) $earlier->billing_year, (int) $earlier->billing_month),
                    ];
                }
            }

            // มิเตอร์หลักไม่มีใบแจ้งหนี้ - งวดถัดไปไม่เคยล็อก (เหมือนแอดมิน)
            $next = $this->findNextReading($meter->id, $year, $month);
            if ($next) {
                $nextReadings[$meter->id] = [
                    'previous' => (int) $next->previous_reading,
                    'period'   => MeterReading::periodLabel((int) $next->billing_year, (int) $next->billing_month),
                    'locked'   => false,
                ];
            }
        }

        $allRecorded     = $currentReadings->count() === $meters->count();
        $propertyCount   = $group->properties()->count();
        $groupMeterCount = HrMasterMeter::active()->where('property_group_id', $group->property_group_id)->count();
        $remark          = $currentReadings->first()?->remark;
        $restartPhoto    = HrMasterMeterReading::supportsRestartPhoto();

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
            'previousUnits', 'suggestedPrevious', 'nextReadings',
            'allRecorded', 'propertyCount', 'groupMeterCount', 'remark', 'othersRecordedCount', 'restartPhoto'
        ));
    }

    public function store(Request $request, HrPropertyGroup $group)
    {
        $this->authorize();

        $agentCode = (string) session('agent_code');

        // IDOR guard: key ของ readings[] ต้องเป็นมิเตอร์หลักที่เปิดใช้งานของกลุ่มนี้ "และ" ตัวแทนคนนี้เป็นผู้จดเท่านั้น
        // - ตรวจผู้จดใหม่ตอนบันทึกเสมอ เผื่อแอดมินเปลี่ยนผู้จด/ผู้บริหารทรัพย์ระหว่างที่หน้าเปิดค้างไว้
        $meters = HrMasterMeter::forRecorder($agentCode, $group->property_group_id)['record']->keyBy('id');
        abort_if($meters->isEmpty(), 403, 'คุณไม่ได้เป็นผู้จดมิเตอร์หลักของกลุ่มนี้');

        $validator = Validator::make($request->all(), [
            'billing_year'                       => 'required|integer|min:2020|max:' . (now()->year + 1),
            'billing_month'                      => 'required|integer|between:1,12',
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
        ], MeterReading::validationMessages(), MeterReading::validationAttributes($meters->map->name->all()));

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        $year  = (int) $validated['billing_year'];
        $month = (int) $validated['billing_month'];

        $submittedIds = array_map('intval', array_keys($validated['readings']));
        if (! empty(array_diff($submittedIds, $meters->keys()->all()))) {
            return back()->withErrors(['form' => 'พบข้อมูลมิเตอร์ที่ไม่ถูกต้อง หรือคุณไม่ได้เป็นผู้จดมิเตอร์บางตัวแล้ว กรุณาโหลดหน้าใหม่'])->withInput();
        }

        $remark         = $validated['remark'] ?? null;
        $restartPhotoOn = HrMasterMeterReading::supportsRestartPhoto();

        $existingRows = HrMasterMeterReading::whereIn('master_meter_id', $submittedIds)
            ->forPeriod($year, $month)
            ->get()
            ->keyBy('master_meter_id');

        $checkMeta = [];
        foreach ($submittedIds as $meterId) {
            $checkMeta[$meterId] = [
                'type'     => $meters[$meterId]->meter_type,
                // ชื่อเดียวกับที่หน้าบันทึกแสดง (งวดที่จดแล้วแสดงชื่อตอนบันทึก)
                'label'    => $existingRows->get($meterId)?->master_meter_name ?: $meters[$meterId]->name,
                'prior'    => $this->findPriorReading($meterId, $year, $month),
                'existing' => $existingRows->get($meterId),
                'next'     => $this->findNextReading($meterId, $year, $month),
            ];
        }

        // ข้อผิดพลาดส่งเป็น $errors ต่อช่อง หน้าบันทึกแสดงเป็น popup กลางจอรายการเดียว (meters/partials/reading-scripts)
        ['values' => $checked, 'errors' => $inputErrors, 'skipped' => $skipped] = MeterReading::checkInput($validated['readings'], $checkMeta);
        if ($inputErrors) {
            return back()->withErrors($inputErrors)->withInput();
        }

        // ไม่ได้กรอกเลขปัจจุบันเลยสักตัว = ไม่บันทึกอะไร (ไม่บังคับกรอก - ผู้ใช้ขอ 2026-10-15 เหมือนมิเตอร์ย่อย)
        if (! $checked) {
            return redirect()
                ->route('master-meters.index', ['year' => $year, 'month' => $month])
                ->with('success', "ไม่ได้กรอกเลขมิเตอร์หลักของกลุ่ม {$group->property_group_name} จึงยังไม่ได้บันทึก (จดทีหลังได้)");
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

            $entries[$meterId] = [
                'meter'                   => $meter,
                'previous_reading'        => $value['previous_reading'],
                'current_reading'         => $value['current_reading'],
                'reading_date'            => $value['reading_date'],
                'meter_reset'             => false,
                'meter_changed'           => $value['meter_changed'],
                'old_meter_final_reading' => $value['old_meter_final_reading'],
                'new_meter_start_reading' => $value['new_meter_start_reading'],
                'meter_max_value'         => null,
                'units_used'              => $calc['units_used'],
                'image'                   => $request->file("readings.$meterId.image"),
                // รูปเลขสุดท้ายก่อนเริ่มนับใหม่ (ไม่บังคับ) - ปิดสวิตช์ = ไม่รับรูป และรูปเดิมของงวดนี้ถูกลบ
                'restart_image'           => $restartPhotoOn && $value['meter_changed'] ? $request->file("readings.$meterId.restart_image") : null,
                'new_image_path'          => null,
                'new_restart_image_path'  => null,
            ];
        }

        // เขียนไฟล์ใหม่ก่อนเปิด transaction ให้ error จากการเขียนไฟล์โผล่ก่อนเขียน DB - ไฟล์เก่าที่ถูกแทนที่
        // ลบหลัง commit สำเร็จเท่านั้น (เหมือนแอดมิน) - path เดียวกับแอดมิน แอดมินจึงเปิดรูปที่ตัวแทนอัปโหลดได้
        $photoDisk   = HrMasterMeter::photoDisk();
        $storedPaths = [];
        try {
            foreach (array_keys($entries) as $meterId) {
                foreach (['image' => 'new_image_path', 'restart_image' => 'new_restart_image_path'] as $fileKey => $pathKey) {
                    if (! $entries[$meterId][$fileKey]) {
                        continue;
                    }
                    $path = $photoDisk->putFile('master-meters/' . $group->property_group_id, $entries[$meterId][$fileKey]);
                    if (! $path) {
                        throw new \RuntimeException("putFile() returned false for readings.{$meterId}.{$fileKey}");
                    }
                    $entries[$meterId][$pathKey] = $path;
                    $storedPaths[] = $path;
                }
            }
        } catch (\Throwable $e) {
            report($e);
            $photoDisk->delete($storedPaths);

            return back()->withErrors(['form' => 'เก็บรูปมิเตอร์หลักไม่สำเร็จ ลองบันทึกอีกครั้ง ถ้ายังไม่ได้ให้แจ้งแอดมิน'])->withInput();
        }

        $oldImagePaths = [];
        $hadExisting   = false;

        try {
            DB::transaction(function () use ($entries, $group, $year, $month, $remark, $restartPhotoOn, &$oldImagePaths, &$hadExisting) {
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

                    $restartValues = [];
                    if ($restartPhotoOn) {
                        $restartPath = $entry['meter_changed'] ? ($entry['new_restart_image_path'] ?: ($existing->old_meter_final_image_path ?? null)) : null;
                        if ($existing?->old_meter_final_image_path && $existing->old_meter_final_image_path !== $restartPath) {
                            $oldImagePaths[] = $existing->old_meter_final_image_path;
                        }
                        $restartValues['old_meter_final_image_path'] = $restartPath;
                    }

                    HrMasterMeterReading::updateOrCreate(
                        [
                            'master_meter_id' => $meter->id,
                            'billing_year'    => $year,
                            'billing_month'   => $month,
                        ],
                        $restartValues + [
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
        } catch (\Throwable $e) {
            // DB ล้ม = ไม่มีแถวไหนเปลี่ยน (transaction) - ลบรูปที่เพิ่งเขียน ไม่ให้ค้างเป็นไฟล์กำพร้า
            report($e);
            $photoDisk->delete($storedPaths);

            return back()->withErrors(['form' => 'บันทึกเลขมิเตอร์หลักไม่สำเร็จ ลองบันทึกอีกครั้ง ถ้ายังไม่ได้ให้แจ้งแอดมิน'])->withInput();
        }

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

        // งวดถัดไปจดไว้แล้วแต่เริ่มจากเลขอื่น -> พาไปงวดนั้นให้กดบันทึกซ้ำ (เหมือนแอดมินและมิเตอร์ย่อย)
        $mismatched = collect($checked)->filter(fn ($v) => $v['next_mismatch'])->keys();
        if ($mismatched->isNotEmpty()) {
            [$nextYear, $nextMonth] = $month === 12 ? [$year + 1, 1] : [$year, $month + 1];

            return redirect()
                ->route('master-meters.show', ['group' => $group->property_group_id, 'year' => $nextYear, 'month' => $nextMonth])
                ->with('meter_notice', [
                    'title' => 'บันทึกงวด ' . MeterReading::periodLabel($year, $month) . ' แล้ว - งวดนี้ต้องบันทึกซ้ำ',
                    'text'  => 'เลขเดือนก่อนของ ' . $mismatched->map(fn ($id) => $checkMeta[$id]['label'])->implode(', ') . ' เปลี่ยนไป ตรวจเลข แล้วกด "บันทึกข้อมูลมิเตอร์หลัก"',
                ]);
        }

        return redirect()
            ->route('master-meters.index', ['year' => $year, 'month' => $month])
            ->with('success', 'บันทึกข้อมูลมิเตอร์หลักเรียบร้อยแล้ว' . ($skipped
                ? ' (ยังไม่ได้จด ' . collect($skipped)->map(fn ($id) => $checkMeta[$id]['label'] ?? 'มิเตอร์หลัก')->implode(', ') . ')'
                : ''));
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

        $imagePaths = $readings->pluck('image_path');
        if (HrMasterMeterReading::supportsRestartPhoto()) {
            $imagePaths = $imagePaths->merge($readings->pluck('old_meter_final_image_path'));
        }
        $imagePaths = $imagePaths->filter()->values()->all();

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

    /** ?kind=restart = รูปเลขสุดท้ายก่อนเริ่มนับใหม่ (old_meter_final_image_path) - เหมือนแอดมิน */
    public function viewImage(Request $request, HrMasterMeterReading $reading)
    {
        $this->authorize();

        // เปิดได้เฉพาะรูปของมิเตอร์หลักที่ตัวแทนคนนี้เป็นผู้จดอยู่ตอนนี้
        $isMine = HrMasterMeter::forRecorder((string) session('agent_code'), $reading->property_group_id)['record']
            ->contains('id', $reading->master_meter_id);
        abort_unless($isMine, 403, 'คุณไม่มีสิทธิ์ดูรูปภาพนี้');

        $path = $request->query('kind') === 'restart' && HrMasterMeterReading::supportsRestartPhoto()
            ? $reading->old_meter_final_image_path
            : $reading->image_path;

        $disk = HrMasterMeter::photoDisk();
        abort_if(! $path, 404, 'ไม่พบรูปภาพ');
        abort_if(! $disk->exists($path), 404, 'ไม่พบรูปภาพ');

        return response()->file($disk->path($path));
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

    /** แถวล่าสุดที่เก่ากว่าเดือน-1 - ใช้แค่เป็นค่าแนะนำของช่องเลขเดือนก่อนตอนเดือนก่อนไม่ได้จด (เหมือนแอดมิน) */
    private function findLatestEarlierReading(int $masterMeterId, int $year, int $month): ?HrMasterMeterReading
    {
        return HrMasterMeterReading::where('master_meter_id', $masterMeterId)
            ->where(fn ($q) => $q->where('billing_year', '<', $year)
                ->orWhere(fn ($q) => $q->where('billing_year', $year)->where('billing_month', '<', $month)))
            ->orderByDesc('billing_year')
            ->orderByDesc('billing_month')
            ->first();
    }

    /** แถวของงวดถัดไป (เดือน+1) - previous_reading ของแถวนั้นต้องเท่ากับเลขปัจจุบันของงวดนี้ */
    private function findNextReading(int $masterMeterId, int $year, int $month): ?HrMasterMeterReading
    {
        [$nextYear, $nextMonth] = $month === 12 ? [$year + 1, 1] : [$year, $month + 1];

        return HrMasterMeterReading::where('master_meter_id', $masterMeterId)
            ->forPeriod($nextYear, $nextMonth)
            ->first();
    }
}
