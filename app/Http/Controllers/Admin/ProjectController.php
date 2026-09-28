<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index()
    {
        return view('admin.projects.index', ['items' => Project::orderBy('order')->get()]);
    }

    public function create()
    {
        return view('admin.projects.form', [
            'item'      => new Project(),
            'nextOrder' => (Project::max('order') ?? 0) + 1,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['order'] = (Project::max('order') ?? 0) + 1;

        Project::create($data);
        return redirect()->route('admin.projects.index')->with('status', 'Project created.');
    }

    public function edit(Project $project)
    {
        return view('admin.projects.form', ['item' => $project, 'nextOrder' => $project->order]);
    }

    public function update(Request $request, Project $project)
    {
        $project->update($this->validated($request));
        return redirect()->route('admin.projects.index')->with('status', 'Project updated.');
    }

    public function destroy(Project $project)
    {
        $project->delete();
        return back()->with('status', 'Project deleted.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'image'    => ['nullable', 'string', 'max:255'],
            'title'    => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
        ]);
    }
}