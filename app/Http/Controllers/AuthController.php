<?php

namespace App\Http\Controllers;

use App\Models\HrAgent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    private const REMEMBER_COOKIE = 'agent_remember_code';
    private const REMEMBER_DAYS   = 30;

    public function showLogin()
    {
        if (session('agent_logged_in')) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'agent_code' => 'required|string',
            'password'   => 'required|string',
        ]);

        // CRIT-2: Rate limit — 5 attempts per minute per IP + agent_code
        $throttleKey = 'login.' . $request->ip() . '.' . strtolower($request->agent_code);

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            logSystem('agent', null, 'Auth', 'LOGIN_RATE_LIMITED',
                'Rate limited login attempt for agent_code: ' . $request->agent_code);

            return back()
                ->withInput($request->only('agent_code'))
                ->with('error', 'พยายามเข้าสู่ระบบหลายครั้งเกินไป กรุณารอ ' . $seconds . ' วินาทีแล้วลองใหม่');
        }

        $agent = HrAgent::where('agent_code', $request->agent_code)->first();

        if (!$agent || $agent->pass_decode !== $request->password) {
            RateLimiter::hit($throttleKey);

            logSystem('agent', null, 'Auth', 'LOGIN_FAILED',
                'Failed login attempt for agent_code: ' . $request->agent_code);

            return back()
                ->withInput($request->only('agent_code'))
                ->with('error', 'รหัสตัวแทนหรือรหัสผ่านไม่ถูกต้อง');
        }

        // HIGH-4: Block inactive agents
        if (!$agent->is_active) {
            RateLimiter::hit($throttleKey);

            logSystem('agent', $agent->id, 'Auth', 'LOGIN_INACTIVE',
                'Inactive agent login attempt: [' . $agent->agent_code . ']');

            return back()
                ->withInput($request->only('agent_code'))
                ->with('error', 'บัญชีตัวแทนนี้ถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ');
        }

        // CRIT-3: Regenerate session ID to prevent session fixation
        $request->session()->regenerate();

        $fullName = trim(($agent->prefix ? $agent->prefix . ' ' : '') . $agent->name);

        session([
            'agent_logged_in' => true,
            'agent_id'        => $agent->id,
            'agent_name'      => $fullName ?: $agent->agent_code,
            'agent_code'      => $agent->agent_code,
            'agent_avatar'    => $agent->avatar,
        ]);

        RateLimiter::clear($throttleKey);

        logSystem('agent', $agent->id, 'Auth', 'LOGIN',
            'Agent logged in: [' . $agent->agent_code . ']');

        $response = redirect()->intended(route('dashboard'));

        if ($request->boolean('remember')) {
            // HIGH-5: secure flag reads SESSION_SECURE_COOKIE (.env) — true on HTTPS production
            $response->withCookie(
                cookie(self::REMEMBER_COOKIE, $agent->agent_code, 60 * 24 * self::REMEMBER_DAYS, '/', null, config('session.secure'), true)
            );
        } else {
            $response->withCookie(
                cookie()->forget(self::REMEMBER_COOKIE)
            );
        }

        return $response;
    }

    public function logout(Request $request)
    {
        $agentId   = session('agent_id');
        $agentName = session('agent_code');

        logSystem('agent', $agentId, 'Auth', 'LOGOUT',
            'Agent logged out: [' . $agentName . ']');

        $request->session()->flush();

        return redirect()->route('login')
            ->withCookie(cookie()->forget(self::REMEMBER_COOKIE))
            ->with('success', 'ออกจากระบบเรียบร้อยแล้ว');
    }
}
