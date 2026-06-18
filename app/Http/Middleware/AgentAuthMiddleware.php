<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AgentAuthMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!session('agent_logged_in') || !session('agent_id')) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('login')
                ->with('error', 'กรุณาเข้าสู่ระบบก่อนดำเนินการต่อ');
        }

        return $next($request);
    }
}
