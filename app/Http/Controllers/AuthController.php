<?php

namespace App\Http\Controllers;

use App\Models\HrAgent;
use App\Models\HrProperty;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
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

        // คีย์บอร์ดมือถือ/ตัวจำรหัสผ่านมักแทรกช่องว่าง (รวมถึง NBSP, zero-width) ต่อท้าย
        // agent_code ในฐานข้อมูลเป็นตัวเลข 7 หลักล้วน จึงตัดอักขระที่ไม่ใช่ตัวเลขออกได้
        $rawCode = preg_replace('/[^0-9]/u', '', (string) $request->agent_code);

        // Normalize: strip leading zeros then re-pad to 7 digits so "390" matches "0000390"
        $agentCode = str_pad(ltrim($rawCode, '0') ?: '0', 7, '0', STR_PAD_LEFT);

        // CRIT-2: Rate limit - 5 attempts per minute per IP + agent_code
        $throttleKey = 'login.' . $request->ip() . '.' . $agentCode;

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            logSystem('agent', null, 'Auth', 'LOGIN_RATE_LIMITED',
                'Rate limited login attempt for agent_code: ' . $agentCode);

            return back()
                ->withInput($request->only('agent_code'))
                ->with('error', 'พยายามเข้าสู่ระบบหลายครั้งเกินไป กรุณารอ ' . $seconds . ' วินาทีแล้วลองใหม่');
        }

        $agent = HrAgent::where('agent_code', $agentCode)->first();

        // ยอมรับรหัสผ่านที่มีช่องว่างหน้า/หลังติดมาจากมือถือ (เทียบแบบตรงตัวก่อนเสมอ)
        $passwordOk = $agent
            && trim((string) $agent->pass_decode) !== ''
            && ((string) $agent->pass_decode === (string) $request->password
                || trim((string) $agent->pass_decode) === trim((string) $request->password));

        if (!$passwordOk) {
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

        // ผู้บริหารโครงการ = มีอสังหาริมทรัพย์ที่ manager_agent_code ตรงกับตัวแทนคนนี้อย่างน้อย 1 รายการ
        $isManager = HrProperty::where('manager_agent_code', $agent->agent_code)
            ->whereNull('deleted_at')
            ->exists();

        session([
            'agent_logged_in'  => true,
            'agent_id'         => $agent->id,
            'agent_name'       => $fullName ?: $agent->agent_code,
            'agent_code'       => $agent->agent_code,
            'agent_avatar'     => $agent->avatar,
            'agent_is_manager' => $isManager,
        ]);

        RateLimiter::clear($throttleKey);

        logSystem('agent', $agent->id, 'Auth', 'LOGIN',
            'Agent logged in: [' . $agent->agent_code . ']');

        $response = redirect()->intended(route('dashboard'));

        if ($request->boolean('remember')) {
            // HIGH-5: secure flag reads SESSION_SECURE_COOKIE (.env) - true on HTTPS production
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

    /**
     * happyest admin "เข้าสู่ระบบในนามผู้บริหารโครงการ" - แอดมินกดชื่อผู้บริหารโครงการจาก
     * /admin/payments แล้วถูก redirect มาที่นี่พร้อม token ใช้ครั้งเดียว (mint ไว้ในตาราง
     * hr_admin_agent_sso_tokens ซึ่งอยู่ใน DB เดียวกันกับที่แอปนี้ใช้) - ตั้งค่า session ให้เหมือน
     * login() ปกติทุกประการ เพื่อให้ทั้งแอปทำงานกับ session นี้ได้ตามปกติ ไม่ต้องแก้จุดอื่นเพิ่ม
     */
    public function ssoLogin(Request $request, string $token)
    {
        $row = DB::table('hr_admin_agent_sso_tokens')
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (!$row) {
            return redirect()->route('login')->with('error', 'ลิงก์เข้าสู่ระบบหมดอายุหรือถูกใช้ไปแล้ว กรุณาเข้าสู่ระบบด้วยตนเอง');
        }

        // ปิด token ทันที (ใช้ได้ครั้งเดียว) ก่อนตั้งค่า session กันกดซ้ำ/แชร์ลิงก์
        DB::table('hr_admin_agent_sso_tokens')->where('id', $row->id)->update(['used_at' => now()]);

        $agent = HrAgent::where('agent_code', $row->agent_code)->first();

        if (!$agent || !$agent->is_active) {
            logSystem('agent', null, 'Auth', 'SSO_LOGIN_FAILED',
                'SSO login failed - agent not found/inactive: [' . $row->agent_code . ']');

            return redirect()->route('login')->with('error', 'ไม่พบบัญชีตัวแทน หรือถูกระงับการใช้งาน');
        }

        $request->session()->regenerate();

        $fullName = trim(($agent->prefix ? $agent->prefix . ' ' : '') . $agent->name);

        $isManager = HrProperty::where('manager_agent_code', $agent->agent_code)
            ->whereNull('deleted_at')
            ->exists();

        session([
            'agent_logged_in'  => true,
            'agent_id'         => $agent->id,
            'agent_name'       => $fullName ?: $agent->agent_code,
            'agent_code'       => $agent->agent_code,
            'agent_avatar'     => $agent->avatar,
            'agent_is_manager' => $isManager,
        ]);

        logSystem('agent', $agent->id, 'Auth', 'SSO_LOGIN',
            'Admin SSO login as agent: [' . $agent->agent_code . '] via admin_id ' . $row->admin_id);

        $redirectPath = (string) ($row->redirect_path ?? '');
        if (!str_starts_with($redirectPath, '/properties/')) {
            $redirectPath = route('dashboard', [], false);
        }

        return redirect($redirectPath);
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
