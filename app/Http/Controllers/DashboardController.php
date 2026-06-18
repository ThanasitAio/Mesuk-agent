<?php

namespace App\Http\Controllers;

use App\Models\AgentMember;
use App\Models\AgentLog;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total'    => AgentMember::count(),
            'active'   => AgentMember::where('status', 'active')->count(),
            'inactive' => AgentMember::where('status', 'inactive')->count(),
            'logs'     => AgentLog::count(),
        ];

        $recentLogs = AgentLog::orderByDesc('created_at')->limit(10)->get();

        return view('dashboard.index', compact('stats', 'recentLogs'));
    }
}
