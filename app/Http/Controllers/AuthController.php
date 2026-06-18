<?php

namespace App\Http\Controllers;

use App\Models\HrAgent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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

        $agent = HrAgent::where('agent_code', $request->agent_code)->first();

        if (!$agent || $agent->pass_decode !== $request->password) {
            logSystem('agent', null, 'Auth', 'LOGIN_FAILED',
                'Failed login attempt for agent_code: ' . $request->agent_code);

            return back()
                ->withInput($request->only('agent_code'))
                ->with('error', 'รหัสตัวแทนหรือรหัสผ่านไม่ถูกต้อง');
        }

        $fullName = trim(($agent->prefix ? $agent->prefix . ' ' : '') . $agent->name);

        session([
            'agent_logged_in' => true,
            'agent_id'        => $agent->id,
            'agent_name'      => $fullName ?: $agent->agent_code,
            'agent_code'      => $agent->agent_code,
        ]);

        logSystem('agent', $agent->id, 'Auth', 'LOGIN',
            'Agent logged in: [' . $agent->agent_code . ']');

        $response = redirect()->intended(route('dashboard'));

        if ($request->boolean('remember')) {
            $response->withCookie(
                cookie(self::REMEMBER_COOKIE, $agent->agent_code, 60 * 24 * self::REMEMBER_DAYS, '/', null, false, true)
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
