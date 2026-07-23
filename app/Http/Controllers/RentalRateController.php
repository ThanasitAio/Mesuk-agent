<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class RentalRateController extends Controller
{
    // เมนู "อัตราเช่า" แสดงข้อมูลอสังหาริมทรัพย์ของผู้บริหารโครงการทุกคนในบริษัท
    // จึงจำกัดให้เห็นเฉพาะรหัสตัวแทนที่กำหนดไว้เท่านั้น
    public const ALLOWED_AGENT_CODES = ['0000001', '0000305', '0000390', '9999999'];

    private function authorize(): void
    {
        if (!in_array(session('agent_code'), self::ALLOWED_AGENT_CODES, true)) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้');
        }
    }

    public function index()
    {
        $this->authorize();

        logSystem(
            userType: 'agent',
            userId: session('agent_id'),
            module: 'RentalRate',
            action: 'VIEW',
            description: 'Agent viewed rental rate overview'
        );

        // Properties currently occupied (has a checked_in booking)
        $occupiedIds = DB::table('hr_bookings')
            ->whereNull('deleted_at')
            ->where('status', 'checked_in')
            ->pluck('property_id')
            ->all();

        $occupiedSet = array_flip($occupiedIds);

        // Active booking details keyed by property_id
        $activeBookings = DB::table('hr_bookings as b')
            ->leftJoin('hr_customer as c', 'b.customer_id', '=', 'c.id')
            ->whereNull('b.deleted_at')
            ->where('b.status', 'checked_in')
            ->select(
                'b.property_id',
                'b.booking_code',
                'b.monthly_rent',
                'b.check_in',
                'b.check_out',
                'b.contract_start_date',
                DB::raw("TRIM(CONCAT(COALESCE(c.first_name,''), ' ', COALESCE(c.last_name,''))) AS tenant_name"),
                'c.mobile AS tenant_mobile'
            )
            ->get()
            ->keyBy('property_id');

        // หน้านี้โฟกัสเฉพาะสถานะ "ว่าง" และ "ไม่ว่าง" เท่านั้น - ไม่แสดงทรัพย์ที่สถานะ
        // "จอง" หรือ "โครงการในอนาคต" เพราะยังไม่ใช่ทรัพย์ที่พร้อมปล่อยเช่าจริง
        $vacantStatusMap = [
            'available'   => ['color' => 'green', 'label' => 'ว่าง'],
            'unavailable' => ['color' => 'red',   'label' => 'ไม่ว่าง'],
        ];

        // All published properties with manager info
        $properties = DB::table('hr_properties as p')
            ->leftJoin('hr_agents as a', 'p.manager_agent_code', '=', 'a.agent_code')
            ->leftJoin('hr_property_statuses as ps', 'p.property_status_id', '=', 'ps.id')
            ->whereNull('p.deleted_at')
            ->where('p.status', 'published')
            ->select(
                'p.id',
                'p.property_code',
                'p.title',
                'p.price_per_month',
                'p.district',
                'p.manager_agent_code',
                DB::raw("COALESCE(a.name, 'ไม่ระบุผู้บริหาร') AS manager_name"),
                'a.avatar AS manager_avatar',
                'a.agent_code AS manager_code',
                'a.pass_decode AS manager_pass_decode',
                'ps.slug AS property_status_slug'
            )
            ->orderBy('a.name')
            ->orderBy('p.property_code')
            ->get()
            ->each(function ($p) use ($occupiedSet, $activeBookings, $vacantStatusMap) {
                $p->is_occupied = isset($occupiedSet[$p->id]);
                $p->booking     = $p->is_occupied ? $activeBookings->get($p->id) : null;

                if ($p->is_occupied) {
                    $p->status_label = 'ไม่ว่าง';
                    $p->status_color = 'red';
                } else {
                    $status = $vacantStatusMap[$p->property_status_slug] ?? $vacantStatusMap['available'];
                    $p->status_label = $status['label'];
                    $p->status_color = $status['color'];
                }
            })
            // ตัดทรัพย์ที่สถานะ "จอง" หรือ "โครงการในอนาคต" ออก (เฉพาะรายการที่ไม่มีผู้เช่า checked_in อยู่จริง)
            // เพื่อให้หน้านี้แสดงและนับจำนวนเฉพาะสถานะว่าง/ไม่ว่างเท่านั้น
            ->filter(fn($p) => $p->is_occupied || in_array($p->property_status_slug, ['available', 'unavailable']) || is_null($p->property_status_slug))
            ->values();

        // Group by manager and compute stats
        $byManager = $properties
            ->groupBy('manager_agent_code')
            ->map(function ($props) use ($occupiedSet) {
                $first    = $props->first();
                $total    = $props->count();
                $occupied = $props->filter(fn($p) => $p->is_occupied)->count();
                return (object) [
                    'manager_agent_code' => $first->manager_agent_code ?? '',
                    'manager_code'       => $first->manager_code,
                    'manager_name'       => $first->manager_name,
                    'manager_avatar'     => $first->manager_avatar,
                    'manager_pass_decode' => $first->manager_pass_decode,
                    'total_props'        => $total,
                    'occupied_count'     => $occupied,
                    'vacant_count'       => $total - $occupied,
                    'occupancy_rate'     => $total > 0 ? round($occupied / $total * 100, 1) : 0,
                    'properties'         => $props->values(),
                ];
            })
            ->sortByDesc('total_props')
            ->values();

        $totalProps    = $properties->count();
        $totalOccupied = $properties->filter(fn($p) => $p->is_occupied)->count();
        $totalVacant   = $totalProps - $totalOccupied;
        $overallRate   = $totalProps > 0 ? round($totalOccupied / $totalProps * 100, 1) : 0;

        return view('rental-rates.index', compact(
            'byManager',
            'totalProps',
            'totalOccupied',
            'totalVacant',
            'overallRate'
        ));
    }
}
