<?php

namespace App\Http\Controllers;

use App\Models\AgentMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AgentController extends Controller
{
    public function index(Request $request)
    {
        $query = AgentMember::query();

        if ($search = trim($request->get('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('member_code', 'like', "%{$search}%");
            });
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $agents = $query->latest()->paginate(10)->withQueryString();

        return view('agents.index', compact('agents'));
    }

    public function create()
    {
        return view('agents.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'                  => 'required|string|max:255',
            'email'                 => 'required|email|max:255|unique:agent_members,email',
            'phone'                 => 'required|string|max:20',
            'password'              => 'required|string|min:6|confirmed',
            'status'                => 'required|in:active,inactive',
            'address'               => 'nullable|string|max:500',
            'province'              => 'nullable|string|max:100',
            'zipcode'               => 'nullable|string|max:10',
        ]);

        // Generate unique member_code
        $maxId = AgentMember::max('id') ?? 0;
        $memberCode = 'AGT-' . str_pad($maxId + 1, 4, '0', STR_PAD_LEFT);
        while (AgentMember::where('member_code', $memberCode)->exists()) {
            $maxId++;
            $memberCode = 'AGT-' . str_pad($maxId + 1, 4, '0', STR_PAD_LEFT);
        }

        $agent = AgentMember::create([
            'member_code' => $memberCode,
            'name'        => $request->name,
            'email'       => $request->email,
            'phone'       => $request->phone,
            'password'    => Hash::make($request->password),
            'status'      => $request->status,
            'address'     => $request->address,
            'province'    => $request->province,
            'zipcode'     => $request->zipcode,
        ]);

        logSystem('agent', session('agent_id'), 'Agents', 'CREATE',
            'Created agent: ' . $agent->name . ' [' . $agent->member_code . ']');

        return redirect()->route('agents.index')
            ->with('success', 'เพิ่มตัวแทน "' . $agent->name . '" สำเร็จแล้ว');
    }

    public function edit(AgentMember $agent)
    {
        return view('agents.edit', compact('agent'));
    }

    public function update(Request $request, AgentMember $agent)
    {
        $request->validate([
            'name'                  => 'required|string|max:255',
            'email'                 => ['required', 'email', 'max:255', Rule::unique('agent_members', 'email')->ignore($agent->id)],
            'phone'                 => 'required|string|max:20',
            'password'              => 'nullable|string|min:6|confirmed',
            'status'                => 'required|in:active,inactive',
            'address'               => 'nullable|string|max:500',
            'province'              => 'nullable|string|max:100',
            'zipcode'               => 'nullable|string|max:10',
        ]);

        $data = [
            'name'     => $request->name,
            'email'    => $request->email,
            'phone'    => $request->phone,
            'status'   => $request->status,
            'address'  => $request->address,
            'province' => $request->province,
            'zipcode'  => $request->zipcode,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $agent->update($data);

        logSystem('agent', session('agent_id'), 'Agents', 'UPDATE',
            'Updated agent: ' . $agent->name . ' [' . $agent->member_code . ']');

        return redirect()->route('agents.index')
            ->with('success', 'อัปเดตข้อมูลตัวแทน "' . $agent->name . '" สำเร็จแล้ว');
    }

    public function destroy(AgentMember $agent)
    {
        $name = $agent->name;
        $code = $agent->member_code;

        $agent->delete();

        logSystem('agent', session('agent_id'), 'Agents', 'DELETE',
            'Deleted agent: ' . $name . ' [' . $code . ']');

        return redirect()->route('agents.index')
            ->with('success', 'ลบตัวแทน "' . $name . '" เรียบร้อยแล้ว');
    }
}
