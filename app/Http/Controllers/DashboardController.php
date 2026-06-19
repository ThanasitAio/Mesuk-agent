<?php

namespace App\Http\Controllers;

use App\Models\AgentLog;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'logs' => AgentLog::count(),
        ];

        $recentLogs = AgentLog::orderByDesc('created_at')->limit(10)->get();

        return view('dashboard.index', compact('stats', 'recentLogs'));
    }
}
