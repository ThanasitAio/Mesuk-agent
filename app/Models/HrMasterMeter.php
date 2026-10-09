<?php

namespace App\Models;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * มิเตอร์หลักของกลุ่มอสังหา (hr_master_meters ของ happyest - DB เดียวกัน แอดมินเป็นคนตั้งค่า แอปนี้อ่านอย่างเดียว)
 * ล้อ happyest App\Models\MasterMeter - กติกาผู้จด (resolveRecorder/recorderFor) ต้องตรงกับฝั่งนั้นเป๊ะ
 * ไม่งั้นแอดมินกับตัวแทนจะเห็นผู้จดของมิเตอร์ตัวเดียวกันไม่ตรงกัน
 */
class HrMasterMeter extends Model
{
    use SoftDeletes;

    /** สถานะผู้จดจาก resolveRecorder() */
    public const RECORDER_AUTO = 'auto';         // ทรัพย์ที่ผูกมีผู้บริหารโครงการคนเดียว - คนนั้นจด

    public const RECORDER_ASSIGNED = 'assigned'; // มีหลายคน แอดมินเลือกไว้ 1 คน

    public const RECORDER_CHOOSE = 'choose';     // มีหลายคน แต่ยังไม่ได้เลือก (หรือคนที่เลือกไม่ได้ดูแลทรัพย์ที่ผูกแล้ว)

    public const RECORDER_NONE = 'none';         // ทรัพย์ที่ผูกไม่มีผู้บริหารโครงการ - แอดมินจด

    protected $table = 'hr_master_meters';

    protected $guarded = [];

    protected $casts = [
        'property_group_id' => 'integer',
        'is_active'         => 'boolean',
        'sort_order'        => 'integer',
    ];

    /**
     * disk ที่เก็บรูปมิเตอร์หลัก = disk "local" ของ happyest (storage/app/private, MasterMeter::PHOTO_DISK)
     * image_path ในตารางเป็น path นับจาก root นั้น (master-meters/{property_group_id}/...) ต้องตรงกันทั้งสองระบบ
     *
     * ใช้ root ของ payment_storage (HAPPYEST_STORAGE_PATH) เป็นฐาน แต่ค่านั้นตั้งเป็นได้ทั้ง happyest/storage/app
     * (ตามคอมเมนต์ใน .env) หรือ .../storage/app/private จึงเติม /private เองถ้ายังไม่มี - build ตรงนี้แทนการเพิ่ม disk
     * ใน config/filesystems.php เพื่อไม่ต้องเพิ่ม env และไม่พังถ้า production cache config ไว้
     */
    public static function photoDisk(): Filesystem
    {
        $root = rtrim(str_replace('\\', '/', (string) config('filesystems.disks.payment_storage.root')), '/');

        return Storage::build([
            'driver' => 'local',
            'root'   => str_ends_with($root, '/private') ? $root : $root . '/private',
            'throw'  => false,
            'report' => false,
        ]);
    }

    public function getTypeLabelAttribute(): string
    {
        return HrPropertyMeter::typeLabels()[$this->meter_type] ?? $this->meter_type;
    }

    public function propertyGroup()
    {
        return $this->belongsTo(HrPropertyGroup::class, 'property_group_id', 'property_group_id');
    }

    /**
     * อสังหาที่มิเตอร์หลักตัวนี้จ่ายไฟ/น้ำให้ - ตัดทรัพย์ที่ถูกลบออกเอง เพราะ HrProperty ไม่มี SoftDeletes
     * (ฝั่ง happyest Property มี SoftDeletes จึงตัดให้อัตโนมัติ ต้องได้ชุดเดียวกัน)
     */
    public function properties()
    {
        return $this->belongsToMany(HrProperty::class, 'hr_master_meter_properties', 'master_meter_id', 'property_id')
            ->whereNull('hr_properties.deleted_at')
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * ใครต้องจดมิเตอร์หลักตัวนี้ - ดูจากผู้บริหารโครงการ (manager_agent_code) ของทรัพย์ที่ผูก "ตอนนี้":
     * 1 คน = คนนั้น, หลายคน = recorder_agent_code ที่แอดมินเลือก (ต้องเป็น 1 ในนั้น ไม่งั้นถือว่ายังไม่ได้เลือก),
     * 0 คน = แอดมินจดเอง
     *
     * ต้องโหลด properties พร้อม manager_agent_code ไว้ก่อน
     *
     * @return object{status: string, agent_code: ?string, managers: array<string, int>} managers = รหัส => จำนวนหลัง
     */
    public function resolveRecorder(): object
    {
        return self::recorderFor($this->properties->pluck('manager_agent_code')->all(), $this->recorder_agent_code);
    }

    /**
     * @param  array<int, ?string>  $managerCodes
     */
    public static function recorderFor(array $managerCodes, ?string $chosenCode): object
    {
        $managers = collect($managerCodes)
            ->filter(fn ($code) => is_string($code) && $code !== '')
            ->countBy()
            ->sortKeys()
            ->all();

        [$status, $agentCode] = match (true) {
            $managers === [] => [self::RECORDER_NONE, null],
            count($managers) === 1 => [self::RECORDER_AUTO, (string) array_key_first($managers)],
            $chosenCode !== null && isset($managers[$chosenCode]) => [self::RECORDER_ASSIGNED, $chosenCode],
            default => [self::RECORDER_CHOOSE, null],
        };

        return (object) ['status' => $status, 'agent_code' => $agentCode, 'managers' => $managers];
    }

    /**
     * มิเตอร์หลักที่เปิดใช้งานอยู่ของตัวแทน 1 คน - ล้อ happyest MasterMeter::forRecorders() แบบคนเดียว
     * record = ตัวที่คนนี้เป็นผู้จด (บันทึกได้), choose = คนนี้เป็น 1 ในผู้บริหารแต่แอดมินยังไม่ได้เลือกผู้จด (บันทึกไม่ได้)
     *
     * @return array{record: Collection<int, HrMasterMeter>, choose: Collection<int, HrMasterMeter>} แต่ละตัวมี ->recorder
     */
    public static function forRecorder(string $agentCode, ?int $groupId = null): array
    {
        $result = ['record' => collect(), 'choose' => collect()];
        if ($agentCode === '') {
            return $result;
        }

        $meters = self::active()
            ->when($groupId !== null, fn ($q) => $q->where('property_group_id', $groupId))
            ->whereHas('properties', fn ($q) => $q->where('manager_agent_code', $agentCode))
            ->with(['properties:id,property_code,title,manager_agent_code', 'propertyGroup:property_group_id,property_group_name'])
            ->orderBy('property_group_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($meters as $meter) {
            $meter->recorder = $meter->resolveRecorder();
            if ($meter->recorder->agent_code === $agentCode) {
                $result['record']->push($meter);
            } elseif ($meter->recorder->status === self::RECORDER_CHOOSE) {
                $result['choose']->push($meter);
            }
        }

        return $result;
    }

    /**
     * แถวของงวด $year/$month สำหรับมิเตอร์หลักที่ตัวแทนเป็นผู้จด ($meters จาก forRecorder()['record']) แยกตามกลุ่ม
     *
     * ต่างจาก happyest MasterMeter::periodRows() โดยตั้งใจ: ไม่มีแถว retired (ปิดใช้งาน/ลบ/ย้ายกลุ่ม) เพราะมิเตอร์พวกนั้น
     * ไม่มีผู้จดแล้ว ตัวแทนเห็นเฉพาะตัวที่ตัวเองเป็นผู้จด "ตอนนี้" - ส่วนที่เหมือนกัน: แถวที่จดแล้วแสดงชื่อ/อสังหาที่ผูก
     * "ตอนบันทึก" จาก snapshot ในแถวที่จด ไม่ใช่ข้อมูลหลักปัจจุบัน
     *
     * @param  Collection<int, HrMasterMeter>  $meters  ต้องโหลด properties ไว้แล้ว
     * @return Collection<int, Collection<int, object>> key = property_group_id
     */
    public static function periodRows(Collection $meters, int $year, int $month): Collection
    {
        if ($meters->isEmpty()) {
            return collect();
        }

        $readings = HrMasterMeterReading::whereIn('master_meter_id', $meters->pluck('id'))
            ->forPeriod($year, $month)
            ->with(['creator:id,name', 'updater:id,name'])
            ->get()
            ->keyBy('master_meter_id');

        return $meters
            ->map(fn ($meter) => self::periodRow($meter, $readings->get($meter->id)))
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->groupBy('property_group_id');
    }

    private static function periodRow(HrMasterMeter $meter, ?HrMasterMeterReading $reading): object
    {
        $liveProperties = $meter->properties->sortBy('property_code', SORT_NATURAL)
            ->map(fn ($p) => (object) ['id' => (int) $p->id, 'code' => $p->property_code, 'title' => $p->title])
            ->values();

        $properties = $reading && is_array($reading->linked_properties)
            ? collect($reading->linked_properties)->map(fn ($p) => (object) [
                'id'    => (int) ($p['id'] ?? 0),
                'code'  => $p['code'] ?? null,
                'title' => $p['title'] ?? null,
            ])
            : $liveProperties;

        $type = $reading?->meter_type ?? $meter->meter_type;

        return (object) [
            'id'                => $meter->id,
            'property_group_id' => $meter->property_group_id,
            'meter'             => $meter,
            'reading'           => $reading,
            'name'              => $reading?->master_meter_name ?: $meter->name,
            'meter_type'        => $type,
            'type_label'        => HrPropertyMeter::typeLabels()[$type] ?? $type,
            'properties'        => $properties,
            'live_name'         => $meter->name,
            'live_property_ids' => $liveProperties->pluck('id')->sort()->values()->all(),
            'sort_order'        => $meter->sort_order,
        ];
    }
}
