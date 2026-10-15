<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class MeterReading extends Model
{
    use SoftDeletes;

    protected $table = 'ag_meter_readings';

    /**
     * จำนวนหลักบนหน้าปัดมิเตอร์ (ผู้ใช้กำหนด 2026-10-12) - หน้าบันทึกมิเตอร์ย่อยและมิเตอร์หลักบังคับให้กรอกครบหลักพอดี
     * (0 นำหน้าได้ เช่น 02345) ค่าเก็บใน DB เป็น int เหมือนเดิม - ต้องตรงกับ happyest App\Models\MeterReading::READING_DIGITS
     * เพราะแอดมินกับตัวแทนบันทึกลงตารางเดียวกัน ดู checkInput()
     */
    public const READING_DIGITS = ['electric' => 5, 'water' => 4];

    private const THAI_SHORT_MONTHS = [1 => 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];

    protected $guarded = [];

    protected $casts = [
        'reading_date'   => 'date',
        'meter_reset'    => 'boolean',
        'meter_changed'  => 'boolean',
        'units_used'     => 'decimal:2',
        'price_per_unit' => 'decimal:14',
        'amount'         => 'decimal:2',
        'confirmed_at'   => 'datetime',
    ];

    public function meter()
    {
        // Null-safe on purpose: happyest can hard-delete hr_property_meters rows
        // independently of this app (see migration comment). Historical rows stay
        // meaningful via the denormalized meter_type/property_id columns even then.
        return $this->belongsTo(HrPropertyMeter::class, 'property_meter_id');
    }

    public function property()
    {
        return $this->belongsTo(HrProperty::class, 'property_id');
    }

    public function creator()
    {
        return $this->belongsTo(HrAgent::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(HrAgent::class, 'updated_by');
    }

    public function confirmer()
    {
        return $this->belongsTo(HrAgent::class, 'confirmed_by');
    }

    public function scopeForPeriod($query, int $year, int $month)
    {
        return $query->where('billing_year', $year)->where('billing_month', $month);
    }

    /**
     * Filters by the rent invoice period a reading is billed together with
     * (rent_year/rent_month) - independent of billing_year/billing_month, the
     * calendar month the meter was actually read.
     */
    public function scopeForRentPeriod($query, int $year, int $month)
    {
        return $query->where('rent_year', $year)->where('rent_month', $month);
    }

    public function scopeForProperty($query, int $propertyId)
    {
        return $query->where('property_id', $propertyId);
    }

    /** จำนวนหลักของมิเตอร์ประเภทนี้ (ไฟ 5 / น้ำ 4) */
    public static function readingDigits(string $meterType): int
    {
        return self::READING_DIGITS[$meterType] ?? 5;
    }

    /** เลขสูงสุดบนหน้าปัด (ไฟ 99999 / น้ำ 9999) - ค่าเริ่มต้นของ "เลขสุดท้ายก่อนเริ่มนับใหม่" */
    public static function readingMax(string $meterType): int
    {
        return (10 ** self::readingDigits($meterType)) - 1;
    }

    /** แสดงเลขแบบหน้าปัด เติม 0 ข้างหน้า (500 -> "00500") - null/ว่าง คืน "" */
    public static function padReading($value, string $meterType): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return str_pad((string) (int) $value, self::readingDigits($meterType), '0', STR_PAD_LEFT);
    }

    /** ชื่องวดสั้นสำหรับข้อความในหน้าบันทึก เช่น "ต.ค. 2569" */
    public static function periodLabel(int $year, int $month): string
    {
        return (self::THAI_SHORT_MONTHS[$month] ?? $month) . ' ' . ($year + 543);
    }

    /**
     * มีคอลัมน์รูปเลขสุดท้ายก่อนเริ่มนับใหม่ (old_meter_final_image_path) แล้วหรือยัง - happyest เพิ่มด้วย
     * add_meter_restart_photo.sql ยังไม่มี = หน้าบันทึกซ่อนช่องรูปนี้ และ controller ไม่เขียนคอลัมน์นี้
     * (อัปโหลดโค้ดก่อนรัน SQL บน production ได้) - เหมือน happyest MeterReading::supportsRestartPhoto()
     */
    public static function supportsRestartPhoto(): bool
    {
        static $has = null;

        return $has ??= Schema::hasColumn('ag_meter_readings', 'old_meter_final_image_path');
    }

    /**
     * ข้อความของ validate() ทั้งสองหน้าบันทึก (มิเตอร์ย่อย/มิเตอร์หลัก) - :attribute = ชื่อมิเตอร์ (validationAttributes())
     * ให้ทุกข้อความบอกว่ามิเตอร์ตัวไหน ครบทุกกฎที่ตกได้ ไม่ให้หลุดข้อความภาษาอังกฤษของ Laravel ไปถึงผู้ใช้
     * ข้อความเดียวกับ happyest MeterReadingInputService::validationMessages()
     *
     * @return array<string, string>
     */
    public static function validationMessages(): array
    {
        $reload       = 'โหลดหน้าใหม่แล้วกรอกอีกครั้ง';
        $uploadFailed = 'อัปโหลดไม่สำเร็จ (ไฟล์ใหญ่เกินที่เซิร์ฟเวอร์รับ หรือส่งมาไม่ครบ) เลือกรูปใหม่แล้วบันทึกอีกครั้ง';

        return [
            'billing_year'  => 'งวดที่จะบันทึกไม่ถูกต้อง ' . $reload,
            'billing_month' => 'งวดที่จะบันทึกไม่ถูกต้อง ' . $reload,
            'rent_year'     => 'งวดค่าเช่าของหน้านี้ไม่ถูกต้อง ' . $reload,
            'rent_month'    => 'งวดค่าเช่าของหน้านี้ไม่ถูกต้อง ' . $reload,
            'readings'      => 'ไม่พบช่องกรอกเลขมิเตอร์ในข้อมูลที่ส่งมา ' . $reload,
            // max:10 กันข้อความยาวผิดปกติเท่านั้น จำนวนหลักจริงตรวจใน checkInput()
            'readings.*.current_reading'              => ':attribute: เลขมิเตอร์ปัจจุบันไม่ถูกต้อง กรอกเป็นตัวเลขตามหน้าปัด',
            'readings.*.previous_reading'             => ':attribute: เลขมิเตอร์เดือนก่อนไม่ถูกต้อง กรอกเป็นตัวเลขตามหน้าปัด',
            'readings.*.old_meter_final_reading'      => ':attribute: เลขสุดท้ายก่อนเริ่มนับใหม่ไม่ถูกต้อง กรอกเป็นตัวเลขตามหน้าปัด',
            'readings.*.new_meter_start_reading'      => ':attribute: เลขเริ่มต้นใหม่ไม่ถูกต้อง กรอกเป็นตัวเลขตามหน้าปัด',
            'readings.*.meter_changed'                => ':attribute: ค่าของปุ่ม "มิเตอร์เริ่มนับใหม่" ไม่ถูกต้อง ' . $reload,
            'readings.*.reading_date.date'            => ':attribute: วันที่อ่านมิเตอร์ไม่ถูกต้อง เลือกวันที่ใหม่',
            'readings.*.reading_date.before_or_equal' => ':attribute: วันที่อ่านมิเตอร์ต้องไม่เกินวันนี้',
            'readings.*.image.mimes'                  => ':attribute: รูปมิเตอร์ต้องเป็นไฟล์ JPG หรือ PNG',
            'readings.*.image.max'                    => ':attribute: รูปมิเตอร์ใหญ่เกิน 10MB เลือกรูปที่เล็กกว่านี้',
            'readings.*.image.uploaded'               => ':attribute: รูปมิเตอร์' . $uploadFailed,
            'readings.*.image.file'                   => ':attribute: รูปมิเตอร์' . $uploadFailed,
            'readings.*.restart_image.mimes'          => ':attribute: รูปเลขสุดท้ายก่อนเริ่มนับใหม่ต้องเป็นไฟล์ JPG หรือ PNG',
            'readings.*.restart_image.max'            => ':attribute: รูปเลขสุดท้ายก่อนเริ่มนับใหม่ใหญ่เกิน 10MB เลือกรูปที่เล็กกว่านี้',
            'readings.*.restart_image.uploaded'       => ':attribute: รูปเลขสุดท้ายก่อนเริ่มนับใหม่' . $uploadFailed,
            'readings.*.restart_image.file'           => ':attribute: รูปเลขสุดท้ายก่อนเริ่มนับใหม่' . $uploadFailed,
            'remark.max'                              => 'หมายเหตุยาวเกิน 1,000 ตัวอักษร',
            'remark'                                  => 'หมายเหตุไม่ถูกต้อง',
        ];
    }

    /**
     * ชื่อ :attribute ของทุกช่องในแถวมิเตอร์ = ชื่อมิเตอร์ที่หน้าจอแสดง
     *
     * @param  array<int, string>  $labels  [meter id => ชื่อ]
     * @return array<string, string>
     */
    public static function validationAttributes(array $labels): array
    {
        $fields = [
            'previous_reading', 'current_reading', 'reading_date', 'meter_changed',
            'old_meter_final_reading', 'new_meter_start_reading', 'image', 'restart_image',
        ];

        $names = [];
        foreach ($labels as $meterId => $label) {
            foreach ($fields as $field) {
                $names["readings.{$meterId}.{$field}"] = $label;
            }
        }

        return $names;
    }

    /**
     * ตรวจเลขมิเตอร์ที่ส่งมาจากหน้าบันทึก - ใช้ร่วมทั้ง MeterReadingController และ MasterMeterReadingController
     * พอร์ตจาก happyest App\Services\MeterReadingInputService::check() - กติกาต้องตรงกับฝั่งแอดมินทุกข้อ
     * กติกาฝั่ง JS (meters/partials/reading-scripts) ต้องตรงกับฟังก์ชันนี้
     *
     * - ทุกช่องเลขมิเตอร์ต้องครบหลักพอดี (READING_DIGITS) 0 นำหน้าได้ - ตรวจเป็นข้อความ ไม่ใช้กฎ integer ของ Laravel
     *   เพราะ filter_var('02345', FILTER_VALIDATE_INT) ไม่ผ่าน
     * - เลขปัจจุบันห้ามน้อยกว่าเดือนก่อน ยกเว้นเปิด "มิเตอร์เริ่มนับใหม่"
     * - 2026-10-14: กรอกเลขปัจจุบันแล้วต้องมีรูปหน้าปัด (อัปโหลดใหม่ หรือรูปที่บันทึกไว้แล้วของงวดนี้) และต้องมีวันที่อ่าน
     *   รูปเลขสุดท้ายก่อนเริ่มนับใหม่ไม่บังคับ
     * - 2026-10-14: เว้นเลขปัจจุบันว่าง = ข้ามมิเตอร์นี้ (skipped) ไม่บังคับช่องอื่นของแถว แถวที่บันทึกไว้แล้วล้างเลขทิ้งไม่ได้
     * - 2026-10-15 (ผู้ใช้ขอ - ต่างจากแอดมินโดยตั้งใจ): ไม่บังคับกรอกเลขปัจจุบันเลย แม้ว่างทุกมิเตอร์ (แอดมินบังคับอย่างน้อย 1)
     *   ว่างทุกตัว = values ว่าง controller จะไม่บันทึกอะไร แค่พาไปหน้าถัดไป
     * - ตรวจครบทุกมิเตอร์ในครั้งเดียว คืน error ต่อช่อง (readings.{id}.{field}) ให้หน้าจอแสดงเป็น popup รายการเดียว
     *
     * $meters ต่อ id มิเตอร์: type, label (ชื่อเดียวกับหัวแถวในหน้า), prior = แถวเดือนก่อน (current_reading, reading_date),
     * existing = แถวงวดนี้ที่บันทึกไว้, next = แถวงวดถัดไป (previous_reading), next_locked = งวดถัดไปออกใบแจ้งหนี้แล้ว,
     * next_label = ชื่องวดถัดไปสำหรับข้อความ
     *
     * values[id].next_mismatch = งวดถัดไป (ที่ยังแก้ได้) เริ่มจากเลขอื่น ต้องบันทึกงวดนั้นซ้ำ
     * skipped = id มิเตอร์ที่เว้นเลขปัจจุบันว่าง (ไม่บันทึก ไม่ตรวจช่องอื่นของแถวนั้น)
     *
     * @param  array<int|string, array<string, mixed>>  $readings  readings[] ที่โพสต์มา (ผ่าน IDOR guard แล้ว)
     * @param  array<int, array<string, mixed>>  $meters
     * @return array{values: array<int, array<string, mixed>>, errors: array<string, string>, skipped: array<int, int>}
     */
    public static function checkInput(array $readings, array $meters): array
    {
        $values  = [];
        $errors  = [];
        $skipped = [];

        foreach ($readings as $meterId => $data) {
            $meterId = (int) $meterId;
            $meta    = $meters[$meterId] ?? null;
            if (! $meta) {
                continue;
            }

            $type   = $meta['type'];
            $label  = $meta['label'];
            $digits = self::readingDigits($type);
            $key    = fn (string $field) => "readings.{$meterId}.{$field}";
            $pad    = fn ($v) => self::padReading($v, $type);
            $add    = function (string $field, string $message) use (&$errors, $key) {
                $errors[$key($field)] ??= $message;
            };

            // เว้นเลขปัจจุบันว่าง = ยังไม่จดมิเตอร์นี้งวดนี้ - ข้ามทั้งแถว ไม่บังคับวันที่/รูป/เลขอื่น
            // ทรัพย์เป็น "จดบางส่วน" แล้วมาจดเพิ่มทีหลังได้ - แต่แถวที่บันทึกไว้แล้วล้างเลขทิ้งไม่ได้ (ใช้ลบข้อมูลงวด)
            $currentRaw = trim((string) ($data['current_reading'] ?? ''));
            if ($currentRaw === '') {
                $existing = $meta['existing'] ?? null;
                if ($existing) {
                    $add('current_reading', "{$label}: งวดนี้บันทึกเลข {$pad($existing->current_reading)} ไว้แล้ว ล้างเลขทิ้งไม่ได้ กรอกเลขปัจจุบัน หรือใช้ปุ่มลบข้อมูลงวดนี้");
                } else {
                    $skipped[] = $meterId;
                }

                continue;
            }

            // ช่องเลขมิเตอร์: ว่าง -> ยังไม่ได้กรอก, ไม่ครบหลัก -> บอกจำนวนหลัก + ตัวอย่าง
            $number = function (string $field, string $what) use ($data, $digits, $label, $add): ?int {
                $raw = trim((string) ($data[$field] ?? ''));
                if ($raw === '') {
                    $add($field, "{$label}: ยังไม่ได้กรอก{$what}");

                    return null;
                }
                if (! preg_match('/^\d{' . $digits . '}$/', $raw)) {
                    $add($field, "{$label}: {$what}ต้องมี {$digits} หลัก เช่น " . ($digits === 4 ? '0546' : '02345'));

                    return null;
                }

                return (int) $raw;
            };

            $changed = filter_var($data['meter_changed'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $prior   = $meta['prior'] ?? null;

            if ($prior) {
                $previous = (int) $prior->current_reading;
                // ข้อมูลเก่าที่บันทึกก่อนบังคับจำนวนหลัก - เลขเดือนก่อนยาวกว่าหน้าปัดแปลว่างวดนั้นกรอกผิด
                if ($previous > self::readingMax($type)) {
                    $add('current_reading', "{$label}: เลขเดือนก่อน {$previous} เกิน {$digits} หลัก แก้งวดเดือนก่อนให้ถูกก่อน");
                }
            } else {
                $previous = $number('previous_reading', 'เลขมิเตอร์เดือนก่อน');
            }

            $current  = $number('current_reading', 'เลขมิเตอร์ปัจจุบัน');
            $oldFinal = null;
            $newStart = null;

            if ($changed) {
                $oldFinal = $number('old_meter_final_reading', 'เลขสุดท้ายก่อนเริ่มนับใหม่');
                $newStart = $number('new_meter_start_reading', 'เลขเริ่มต้นใหม่');

                if ($oldFinal !== null && $previous !== null && $oldFinal < $previous) {
                    $add('old_meter_final_reading', "{$label}: เลขสุดท้ายก่อนเริ่มนับใหม่ {$pad($oldFinal)} น้อยกว่าเดือนก่อน {$pad($previous)}");
                }
                if ($current !== null && $newStart !== null && $current < $newStart) {
                    $add('current_reading', "{$label}: เลขปัจจุบัน {$pad($current)} น้อยกว่าเลขเริ่มต้นใหม่ {$pad($newStart)}");
                }
            } elseif ($current !== null && $previous !== null && $current < $previous) {
                $add('current_reading', "{$label}: เลขปัจจุบัน {$pad($current)} น้อยกว่าเดือนก่อน {$pad($previous)} ตรวจเลขอีกครั้ง หรือเปิด \"มิเตอร์เริ่มนับใหม่\"");
            }

            // กรอกเลขปัจจุบันแล้ว (ถึงบรรทัดนี้ = กรอกแล้ว) รูปหน้าปัดบังคับ - รูปที่บันทึกไว้แล้วของงวดนี้นับว่ามี
            $hasPhoto = ($data['image'] ?? null) instanceof UploadedFile || ! empty($meta['existing']?->image_path);
            if (! $hasPhoto) {
                $add('image', "{$label}: ยังไม่ได้แนบรูปมิเตอร์ ต้องมีรูปหน้าปัดที่เห็นเลขปัจจุบัน");
            }

            // วันที่อ่านบังคับเมื่อกรอกเลขปัจจุบัน - รายงานภาพรวมมิเตอร์ของแอดมินใช้วันที่จดปรับหน่วยเทียบมิเตอร์หลัก
            $readingDate = $data['reading_date'] ?? null;
            if (! $readingDate) {
                $add('reading_date', "{$label}: ยังไม่ได้เลือกวันที่อ่านมิเตอร์");
            }
            $priorDate = $prior?->reading_date;
            if ($readingDate && $priorDate && Carbon::parse($readingDate)->lte($priorDate)) {
                $add('reading_date', "{$label}: วันที่อ่านต้องหลัง " . self::thaiShortDate($priorDate) . ' (วันที่จดเดือนก่อน)');
            }

            // งวดถัดไปเริ่มจากเลขปัจจุบันของงวดนี้ - ถ้างวดถัดไปออกใบแจ้งหนี้แล้ว ห้ามเปลี่ยนเลข เพราะใบนั้นคิดจากเลขเดิม
            // (บันทึกซ้ำด้วยเลขเดิมได้ แม้ข้อมูลเก่าจะไม่ตรงกันอยู่ก่อน จะได้ไม่ติดล็อกโดยที่ไม่ได้แก้อะไร)
            $next            = $meta['next'] ?? null;
            $nextMismatch    = $next && $current !== null && $current !== (int) $next->previous_reading;
            $existing        = $meta['existing'] ?? null;
            $existingCurrent = $existing ? (int) $existing->current_reading : null;
            if ($nextMismatch && ! empty($meta['next_locked']) && $current !== $existingCurrent) {
                $add('current_reading', "{$label}: งวด {$meta['next_label']} ออกใบแจ้งหนี้แล้ว เริ่มจาก {$pad($next->previous_reading)} เลขปัจจุบันต้องเป็น {$pad($next->previous_reading)}");
            }

            $values[$meterId] = [
                'previous_reading'        => (int) $previous,
                'current_reading'         => (int) $current,
                'reading_date'            => $readingDate ?: null,
                'meter_changed'           => $changed,
                // ปิดสวิตช์อยู่ = ไม่เก็บเลขของโหมดเริ่มนับใหม่ (เดิมค่าที่เคยพิมพ์ไว้ยังถูกบันทึกลง DB)
                'old_meter_final_reading' => $changed ? $oldFinal : null,
                'new_meter_start_reading' => $changed ? $newStart : null,
                'next_mismatch'           => $nextMismatch && empty($meta['next_locked']),
            ];
        }

        return ['values' => $values, 'errors' => $errors, 'skipped' => $skipped];
    }

    /** วันที่แบบไทยสั้น เช่น "9 ต.ค. 2569" - แอปนี้ไม่มี thai_date_short() แบบ happyest */
    private static function thaiShortDate($date): string
    {
        $date = Carbon::parse($date);

        return $date->day . ' ' . self::THAI_SHORT_MONTHS[$date->month] . ' ' . ($date->year + 543);
    }

    public function getStatusLabelAttribute(): string
    {
        return [
            'draft'     => 'ฉบับร่าง',
            'confirmed' => 'ยืนยันแล้ว',
        ][$this->status] ?? $this->status;
    }

    /**
     * Single source of truth for unit calculation - the legacy system had 3 different,
     * mutually inconsistent formulas across the model and two export files. Everything
     * (store(), any future report/export) must call this instead of re-deriving units.
     *
     * @param  array{previous_reading: ?int, current_reading: int, meter_reset: bool, meter_changed: bool,
     *               old_meter_final_reading: ?int, new_meter_start_reading: ?int, meter_max_value: ?int}  $params
     * @return array{units_used: float, max_value_used: ?int}
     */
    public static function calculateUnits(array $params): array
    {
        $previous     = $params['previous_reading'] !== null ? (int) $params['previous_reading'] : null;
        $current      = (int) $params['current_reading'];
        $meterReset   = (bool) ($params['meter_reset'] ?? false);
        $meterChanged = (bool) ($params['meter_changed'] ?? false);
        $oldFinal     = $params['old_meter_final_reading'] ?? null;
        $newStart     = $params['new_meter_start_reading'] ?? null;
        $maxOverride  = $params['meter_max_value'] ?? null;

        $maxValueUsed = null;

        if ($meterChanged) {
            // Old meter ran from previous_reading up to old_meter_final_reading, then the
            // new meter picked up from new_meter_start_reading up to current_reading.
            $units = ((int) $oldFinal - ($previous ?? 0)) + ($current - (int) $newStart);
        } elseif ($meterReset) {
            $maxValueUsed = $maxOverride ?? self::heuristicMaxValue($previous ?? 0);
            $percentFull  = $maxValueUsed > 0 ? (($previous ?? 0) / $maxValueUsed) * 100 : 0;
            $units        = $percentFull > 90
                ? ($maxValueUsed - ($previous ?? 0)) + $current + 1
                : $current;
        } elseif ($previous === null) {
            // First-ever reading for this meter - nothing to diff against.
            $units = $current;
        } else {
            $units = $current - $previous;
        }

        return [
            'units_used'    => (float) max($units, 0),
            'max_value_used' => $maxValueUsed,
        ];
    }

    /**
     * Guess the meter's rollover point from the previous reading's digit count,
     * used only when meter_max_value isn't explicitly set. Same thresholds the
     * legacy system used, kept only as a fallback now that an explicit override exists.
     */
    private static function heuristicMaxValue(int $previous): int
    {
        if ($previous >= 10000) {
            return 99999;
        }
        if ($previous >= 1000) {
            return 9999;
        }
        if ($previous >= 100) {
            return 999;
        }

        return 9999;
    }
}
