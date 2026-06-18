<?php

use App\Models\AgentLog;

if (!function_exists('logSystem')) {
    /**
     * Log a system action to agent_logs.
     *
     * @param string      $userType   e.g. 'agent'
     * @param int|null    $userId     ID of the acting user
     * @param string      $module     e.g. 'Auth', 'Agents', 'Logs'
     * @param string      $action     e.g. 'LOGIN', 'CREATE', 'UPDATE', 'DELETE'
     * @param string      $description Human-readable description
     */
    function logSystem(string $userType, ?int $userId, string $module, string $action, string $description = ''): void
    {
        try {
            AgentLog::create([
                'user_type'   => $userType,
                'user_id'     => $userId,
                'module'      => $module,
                'action'      => $action,
                'description' => $description,
                'url'         => request()->fullUrl(),
                'method'      => request()->method(),
                'ip_address'  => request()->ip(),
                'user_agent'  => request()->userAgent(),
                'created_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            // Logging must never break the application
        }
    }
}
