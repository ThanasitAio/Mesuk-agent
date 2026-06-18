<?php

namespace App\Http\Controllers;

use App\Models\HrAgent;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show()
    {
        $agent = HrAgent::findOrFail(session('agent_id'));
        return view('profile.show', compact('agent'));
    }

    public function update(Request $request)
    {
        $agent = HrAgent::findOrFail(session('agent_id'));

        $request->validate([
            'prefix'   => 'nullable|string|max:20',
            'name'     => 'required|string|max:255',
            'birthday' => 'nullable|date',
            'gender'   => 'nullable|in:ชาย,หญิง,อื่น ๆ',
            'phone'    => 'nullable|string|max:20',
            'email'    => 'nullable|email|max:255',
            'address'  => 'nullable|string|max:1000',
            'line_id'  => 'nullable|string|max:100',
            'facebook' => 'nullable|string|max:255',
        ]);

        $agent->update($request->only([
            'prefix', 'name', 'birthday', 'gender',
            'phone', 'email', 'address', 'line_id', 'facebook',
        ]));

        $fullName = trim(($agent->prefix ? $agent->prefix . ' ' : '') . $agent->name);
        session(['agent_name' => $fullName ?: $agent->agent_code]);

        logSystem('agent', $agent->id, 'Profile', 'UPDATE_INFO',
            'Updated profile info for agent: [' . $agent->agent_code . ']');

        return back()->with('success', 'อัปเดตข้อมูลส่วนตัวเรียบร้อยแล้ว');
    }

    public function updateBank(Request $request)
    {
        $agent = HrAgent::findOrFail(session('agent_id'));

        $request->validate([
            'bank_account_name' => 'nullable|string|max:255',
            'bank_name'         => 'nullable|string|max:100',
            'bank_branch'       => 'nullable|string|max:100',
            'bank_account_no'   => 'nullable|string|max:50',
        ]);

        $agent->update($request->only([
            'bank_account_name', 'bank_name', 'bank_branch', 'bank_account_no',
        ]));

        logSystem('agent', $agent->id, 'Profile', 'UPDATE_BANK',
            'Updated bank info for agent: [' . $agent->agent_code . ']');

        return back()->with('success', 'อัปเดตข้อมูลธนาคารเรียบร้อยแล้ว');
    }

    public function updatePassword(Request $request)
    {
        $agent = HrAgent::findOrFail(session('agent_id'));

        $request->validate([
            'current_password' => 'required|string',
            'password'         => 'required|string|min:6|confirmed',
        ], [
            'current_password.required' => 'กรุณากรอกรหัสผ่านปัจจุบัน',
            'password.required'         => 'กรุณากรอกรหัสผ่านใหม่',
            'password.min'              => 'รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร',
            'password.confirmed'        => 'รหัสผ่านใหม่ไม่ตรงกัน',
        ]);

        if ($request->current_password !== $agent->pass_decode) {
            return back()->withErrors(['current_password' => 'รหัสผ่านปัจจุบันไม่ถูกต้อง'])->withInput();
        }

        $agent->pass_decode = $request->password;
        $agent->save();

        logSystem('agent', $agent->id, 'Profile', 'CHANGE_PASSWORD',
            'Changed password for agent: [' . $agent->agent_code . ']');

        return back()->with('success', 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว');
    }
}
