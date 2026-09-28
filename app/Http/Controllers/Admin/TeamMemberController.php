<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TeamMember;
use Illuminate\Http\Request;

class TeamMemberController extends Controller
{
    public function index()
    {
        return view('admin.team.index', ['items' => TeamMember::orderBy('order')->get()]);
    }

    public function create()
    {
        return view('admin.team.form', [
            'item'      => new TeamMember(),
            'nextOrder' => (TeamMember::max('order') ?? 0) + 1,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['order'] = (TeamMember::max('order') ?? 0) + 1;

        TeamMember::create($data);
        return redirect()->route('admin.team.index')->with('status', 'Team member created.');
    }

    public function edit(TeamMember $team)
    {
        return view('admin.team.form', ['item' => $team, 'nextOrder' => $team->order]);
    }

    public function update(Request $request, TeamMember $team)
    {
        $team->update($this->validated($request));
        return redirect()->route('admin.team.index')->with('status', 'Team member updated.');
    }

    public function destroy(TeamMember $team)
    {
        $team->delete();
        return back()->with('status', 'Team member deleted.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'role'      => ['nullable', 'string', 'max:255'],
            'image'     => ['nullable', 'string', 'max:255'],
            'facebook'  => ['nullable', 'string', 'max:255'],
            'twitter'   => ['nullable', 'string', 'max:255'],
            'instagram' => ['nullable', 'string', 'max:255'],
        ]);
    }
}