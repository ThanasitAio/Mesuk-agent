<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class RentalRateController extends Controller
{
    public function index()
    {
        // เฉพาะ agent_id = 9999999 เท่านั้นที่เข้าถึงได้
        if (session('agent_id') != 9999999) {
            return redirect()->route('dashboard')
                ->with('error', 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้');
        }

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

        // All published properties with manager info
        $properties = DB::table('hr_properties as p')
            ->leftJoin('hr_agents as a', 'p.manager_agent_code', '=', 'a.agent_code')
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
                'a.pass_decode AS manager_pass_decode'
            )
            ->orderBy('a.name')
            ->orderBy('p.property_code')
            ->get()
            ->each(function ($p) use ($occupiedSet, $activeBookings) {
                $p->is_occupied = isset($occupiedSet[$p->id]);
                $p->booking     = $p->is_occupied ? $activeBookings->get($p->id) : null;
            });

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
