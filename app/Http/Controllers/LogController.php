<?php

namespace App\Http\Controllers;

use App\Models\AgentLog;
use Illuminate\Http\Request;

class LogController extends Controller
{
    public function index(Request $request)
    {
        $query = AgentLog::query();

        if ($module = $request->get('module')) {
            $query->where('module', $module);
        }

        if ($action = $request->get('action')) {
            $query->where('action', $action);
        }

        if ($dateFrom = $request->get('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->get('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        if ($search = trim($request->get('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhere('user_type', 'like', "%{$search}%");
            });
        }

        $logs    = $query->orderByDesc('created_at')->paginate(20)->withQueryString();
        $modules = AgentLog::select('module')->whereNotNull('module')->distinct()->orderBy('module')->pluck('module');
        $actions = AgentLog::select('action')->whereNotNull('action')->distinct()->orderBy('action')->pluck('action');

        return view('logs.index', compact('logs', 'modules', 'actions'));
    }
}
