<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index()
    {
        return view('admin.services.index', ['items' => Service::orderBy('order')->get()]);
    }

    public function create()
    {
        return view('admin.services.form', ['item' => new Service()]);
    }

    public function store(Request $request)
    {
        Service::create($this->validated($request));
        return redirect()->route('admin.services.index')->with('status', 'Service created.');
    }

    public function edit(Service $service)
    {
        return view('admin.services.form', ['item' => $service]);
    }

    public function update(Request $request, Service $service)
    {
        $service->update($this->validated($request));
        return redirect()->route('admin.services.index')->with('status', 'Service updated.');
    }

    public function destroy(Service $service)
    {
        $service->delete();
        return back()->with('status', 'Service deleted.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'icon'        => ['nullable', 'string', 'max:100'],
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'order'       => ['nullable', 'integer'],
        ]);
    }
}
