<?php

namespace App\Http\Controllers;

use App\Models\AgentMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
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
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $member = AgentMember::where('email', $request->email)->first();

        if (!$member || !Hash::check($request->password, $member->password)) {
            logSystem('agent', null, 'Auth', 'LOGIN_FAILED',
                'Failed login attempt for: ' . $request->email);

            return back()
                ->withInput($request->only('email'))
                ->with('error', 'อีเมลหรือรหัสผ่านไม่ถูกต้อง');
        }

        if ($member->status !== 'active') {
            logSystem('agent', $member->id, 'Auth', 'LOGIN_BLOCKED',
                'Inactive account login attempt: ' . $member->email);

            return back()
                ->withInput($request->only('email'))
                ->with('error', 'บัญชีของคุณถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ');
        }

        session([
            'agent_logged_in' => true,
            'agent_id'        => $member->id,
            'agent_name'      => $member->name,
            'agent_email'     => $member->email,
            'agent_code'      => $member->member_code,
        ]);

        logSystem('agent', $member->id, 'Auth', 'LOGIN',
            'Member logged in: ' . $member->name . ' [' . $member->member_code . ']');

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        $agentId   = session('agent_id');
        $agentName = session('agent_name');

        logSystem('agent', $agentId, 'Auth', 'LOGOUT',
            'Member logged out: ' . $agentName);

        $request->session()->flush();

        return redirect()->route('login')
            ->with('success', 'ออกจากระบบเรียบร้อยแล้ว');
    }
}
