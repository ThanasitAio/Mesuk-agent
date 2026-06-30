<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $agentId = session('agent_id');

        $bookingIds = DB::table('hr_bookings')
            ->where('agent_id', $agentId)
            ->whereNull('deleted_at')
            ->pluck('id');

        $totalBookings = $bookingIds->count();

        $paymentStats = DB::table('hr_payment_records')
            ->whereIn('booking_id', $bookingIds)
            ->whereNull('deleted_at')
            ->where('is_hidden', 0)
            ->selectRaw('payment_status, COUNT(*) as cnt, SUM(amount) as total')
            ->groupBy('payment_status')
            ->get()
            ->keyBy('payment_status');

        $paidAmount = (float) ($paymentStats->get('paid')?->total ?? 0);
        $pendingVerificationCount = (int) ($paymentStats->get('pending_verification')?->cnt ?? 0);

        $overdueCount = DB::table('hr_payment_records')
            ->whereIn('booking_id', $bookingIds)
            ->whereNull('deleted_at')
            ->where('is_hidden', 0)
            ->where('payment_status', 'pending')
            ->whereNotNull('due_date')
            ->whereRaw('DATE(due_date) < CURDATE()')
            ->count();

        // Monthly paid amounts — last 6 months
        $monthlyData = DB::table('hr_payment_records')
            ->whereIn('booking_id', $bookingIds)
            ->whereNull('deleted_at')
            ->where('is_hidden', 0)
            ->where('payment_status', 'paid')
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', now()->subMonths(5)->startOfMonth())
            ->selectRaw('DATE_FORMAT(paid_at, "%Y-%m") as ym, SUM(amount) as total')
            ->groupBy('ym')
            ->orderBy('ym')
            ->get()
            ->keyBy('ym');

        $thaiMonths = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
        $chartMonths = [];
        $chartAmounts = [];
        for ($i = 5; $i >= 0; $i--) {
            $d = now()->subMonths($i);
            $chartMonths[] = $thaiMonths[$d->month - 1];
            $chartAmounts[] = (float) ($monthlyData->get($d->format('Y-m'))?->total ?? 0);
        }

        // Donut — payment status breakdown by count
        $statusConfig = [
            'pending'              => ['label' => 'รอชำระ',     'color' => '#F59E0B'],
            'pending_verification' => ['label' => 'รออนุมัติ',  'color' => '#3B82F6'],
            'paid'                 => ['label' => 'ชำระแล้ว',   'color' => '#22C55E'],
            'failed'               => ['label' => 'ไม่ผ่าน',    'color' => '#EF4444'],
            'refunded'             => ['label' => 'คืนเงิน',    'color' => '#94A3B8'],
        ];
        $donutLabels = [];
        $donutCounts = [];
        $donutColors = [];
        foreach ($statusConfig as $key => $cfg) {
            $cnt = (int) ($paymentStats->get($key)?->cnt ?? 0);
            $donutLabels[] = $cfg['label'];
            $donutCounts[] = $cnt;
            $donutColors[] = $cfg['color'];
        }

        $recentSlips = DB::table('hr_payment_records as p')
            ->join('hr_bookings as b', 'p.booking_id', '=', 'b.id')
            ->leftJoin('hr_customer as c', 'b.customer_id', '=', 'c.id')
            ->whereIn('p.booking_id', $bookingIds)
            ->whereNull('p.deleted_at')
            ->where('p.payment_status', 'pending_verification')
            ->where('p.is_hidden', 0)
            ->select(
                'p.id', 'p.payment_type', 'p.amount', 'p.due_date', 'p.created_at',
                'b.booking_code', 'c.first_name', 'c.last_name'
            )
            ->orderByDesc('p.created_at')
            ->limit(5)
            ->get();

        // Total distinct customers
        $totalCustomers = DB::table('hr_customer as c')
            ->join('hr_bookings as b', 'b.customer_id', '=', 'c.id')
            ->whereIn('b.id', $bookingIds)
            ->whereNull('c.deleted_at')
            ->distinct()
            ->count('c.id');

        // Recent customers linked to this agent's bookings
        $recentCustomers = DB::table('hr_customer as c')
            ->join('hr_bookings as b', 'b.customer_id', '=', 'c.id')
            ->whereIn('b.id', $bookingIds)
            ->whereNull('c.deleted_at')
            ->select('c.id', 'c.first_name', 'c.last_name', 'c.mobile', 'c.photo', 'c.avatar', 'c.provider_id',
                     'b.booking_code', 'b.status as booking_status', 'b.monthly_rent', 'b.created_at')
            ->orderByDesc('b.created_at')
            ->limit(6)
            ->get();

        // Upcoming dues in next 14 days (automation alerts)
        $upcomingDues = DB::table('hr_payment_records as p')
            ->join('hr_bookings as b', 'p.booking_id', '=', 'b.id')
            ->leftJoin('hr_customer as c', 'b.customer_id', '=', 'c.id')
            ->whereIn('p.booking_id', $bookingIds)
            ->whereNull('p.deleted_at')
            ->where('p.is_hidden', 0)
            ->where('p.payment_status', 'pending')
            ->whereNotNull('p.due_date')
            ->whereRaw('DATE(p.due_date) >= CURDATE()')
            ->whereRaw('DATE(p.due_date) <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)')
            ->select('p.id', 'p.payment_type', 'p.amount', 'p.due_date', 'b.booking_code', 'c.first_name', 'c.last_name')
            ->orderBy('p.due_date')
            ->limit(7)
            ->get();

        // Booking status breakdown (3rd chart)
        $bookingStatusData = DB::table('hr_bookings')
            ->where('agent_id', $agentId)
            ->whereNull('deleted_at')
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $bookingStatusConfig = [
            'pending'           => ['label' => 'รอยืนยัน',    'color' => '#F59E0B'],
            'deposit_confirmed' => ['label' => 'ยืนยันมัดจำ', 'color' => '#3B82F6'],
            'confirmed'         => ['label' => 'ยืนยันแล้ว',  'color' => '#8B5CF6'],
            'checked_in'        => ['label' => 'เช่าอยู่',    'color' => '#22C55E'],
            'checked_out'       => ['label' => 'ออกแล้ว',     'color' => '#94A3B8'],
            'completed'         => ['label' => 'เสร็จสิ้น',   'color' => '#10B981'],
            'cancelled'         => ['label' => 'ยกเลิก',      'color' => '#EF4444'],
            'rejected'          => ['label' => 'ปฏิเสธ',      'color' => '#6B7280'],
        ];
        $bookingStatusLabels = [];
        $bookingStatusCounts = [];
        $bookingStatusColors = [];
        foreach ($bookingStatusConfig as $key => $cfg) {
            $cnt = (int)($bookingStatusData->get($key)?->cnt ?? 0);
            $bookingStatusLabels[] = $cfg['label'];
            $bookingStatusCounts[] = $cnt;
            $bookingStatusColors[] = $cfg['color'];
        }

        // ── Property occupancy overview ──────────────────────────────────────────
        $occupiedPropIds = array_flip(
            DB::table('hr_bookings')
                ->whereNull('deleted_at')
                ->where('status', 'checked_in')
                ->pluck('property_id')
                ->all()
        );

        $publishedPropsData = DB::table('hr_properties as p')
            ->leftJoin('hr_agents as a', 'p.manager_agent_code', '=', 'a.agent_code')
            ->whereNull('p.deleted_at')
            ->where('p.status', 'published')
            ->select('p.id', 'p.manager_agent_code',
                DB::raw("COALESCE(a.name, 'ไม่ระบุ') AS manager_name"),
                'a.avatar AS manager_avatar')
            ->get();

        $totalPublishedProps = $publishedPropsData->count();
        $occupiedPropsCount  = $publishedPropsData->filter(fn($p) => isset($occupiedPropIds[$p->id]))->count();
        $vacantPropsCount    = $totalPublishedProps - $occupiedPropsCount;

        $managerPerfStats = $publishedPropsData
            ->groupBy('manager_agent_code')
            ->map(function ($props) use ($occupiedPropIds) {
                $first    = $props->first();
                $total    = $props->count();
                $occupied = $props->filter(fn($p) => isset($occupiedPropIds[$p->id]))->count();
                return (object) [
                    'manager_name'   => $first->manager_name,
                    'manager_avatar' => $first->manager_avatar,
                    'total_props'    => $total,
                    'occupied_count' => $occupied,
                    'vacant_count'   => $total - $occupied,
                    'occupancy_rate' => $total > 0 ? round($occupied / $total * 100, 1) : 0,
                ];
            })
            ->sortByDesc('total_props')
            ->values()
            ->take(6);

        return view('dashboard.index', compact(
            'totalBookings',
            'totalCustomers',
            'paidAmount',
            'pendingVerificationCount',
            'overdueCount',
            'chartMonths',
            'chartAmounts',
            'donutLabels',
            'donutCounts',
            'donutColors',
            'bookingStatusLabels',
            'bookingStatusCounts',
            'bookingStatusColors',
            'recentSlips',
            'recentCustomers',
            'upcomingDues',
            'totalPublishedProps',
            'occupiedPropsCount',
            'vacantPropsCount',
            'managerPerfStats'
        ));
    }
}
